<?php

class ContentPipeline {
    private const STOPWORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'but', 'by', 'for', 'from', 'has', 'have',
        'in', 'into', 'is', 'it', 'its', 'of', 'on', 'or', 'that', 'the', 'their', 'this',
        'to', 'was', 'were', 'will', 'with', 'after', 'amid', 'before', 'over', 'under',
        'how', 'what', 'why', 'when', 'where', 'who', 'latest', 'live', 'today', 'update',
        'updates', 'says', 'say', 'said', 'india', 'indian', 'news'
    ];
    private const MIN_CONTEXT_CHARS = 120;
    private const AUTO_QUEUE_MAX_AGE_HOURS = 36;

    public static function feedConfigs(): array {
        $configured = defined('CONTENT_PIPELINE_FEEDS') ? CONTENT_PIPELINE_FEEDS : [];
        if (!is_array($configured)) {
            return [];
        }

        $feeds = [];
        foreach ($configured as $index => $feed) {
            $normalized = is_array($feed) ? $feed : ['url' => (string)$feed];
            $url = trim((string)($normalized['url'] ?? ''));
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $host = (string)(parse_url($url, PHP_URL_HOST) ?? ('Feed ' . ($index + 1)));
            $feeds[] = [
                'name' => trim((string)($normalized['name'] ?? ucwords(str_replace(['.', '-'], ' ', $host)))) ?: $host,
                'url' => $url,
                'category_slug' => trim((string)($normalized['category_slug'] ?? '')),
                'source_type' => trim((string)($normalized['source_type'] ?? 'rss')) ?: 'rss',
                'weight' => max(0.5, min(2.5, (float)($normalized['weight'] ?? 1.0))),
            ];
        }

        return $feeds;
    }

    public static function ingestFeeds(): array {
        $feeds = self::feedConfigs();
        if (empty($feeds)) {
            throw new InvalidArgumentException('No content pipeline feeds are configured.');
        }

        $candidateModel = new ContentCandidateModel();
        $categoryModel = new CategoryModel();
        $categories = $categoryModel->getTopLevel();
        $categorySlugs = [];
        foreach ($categories as $category) {
            $slug = trim((string)($category['slug'] ?? ''));
            if ($slug !== '') {
                $categorySlugs[$slug] = true;
            }
        }
        $stats = [
            'feeds' => count($feeds),
            'items' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($feeds as $feed) {
            try {
                $body = self::fetchUrl((string)$feed['url']);
                $items = self::parseFeed($body, $feed);
            } catch (Throwable $e) {
                $stats['failed']++;
                $stats['errors'][] = ($feed['name'] ?? $feed['url']) . ': ' . $e->getMessage();
                continue;
            }

            foreach ($items as $item) {
                $normalized = self::normalizeCandidateInput($item, $feed, $categories);
                if ($normalized === null) {
                    continue;
                }

                $candidateCategorySlug = trim((string)($normalized['category_slug_hint'] ?? ''));
                if ($candidateCategorySlug !== '' && !isset($categorySlugs[$candidateCategorySlug])) {
                    $createdCategory = $categoryModel->ensureTopLevelCategory($candidateCategorySlug);
                    if ($createdCategory) {
                        $categorySlugs[(string)$createdCategory['slug']] = true;
                        $categories[] = $createdCategory;
                    }
                }

                $result = $candidateModel->upsertCandidate($normalized);
                $stats['items']++;
                if (($result['action'] ?? '') === 'created') {
                    $stats['created']++;
                } else {
                    $stats['updated']++;
                }

                $topicKey = trim((string)($normalized['topic_key'] ?? ''));
                if ($topicKey !== '') {
                    self::recalculateTopicScores($candidateModel, $topicKey);
                }
            }
        }

        return $stats;
    }

    public static function runAutomatic(int $authorId = 0): array {
        $stats = self::ingestFeeds();
        return array_merge($stats, self::processAutomaticQueue($authorId));
    }

    public static function processAutomaticQueue(int $authorId = 0, ?float $minScore = null, ?int $maxPerRun = null, ?bool $publish = null): array {
        $autoWriteEnabled = defined('CONTENT_PIPELINE_AUTO_WRITE') ? (bool)CONTENT_PIPELINE_AUTO_WRITE : false;
        $autoPublishEnabled = defined('CONTENT_PIPELINE_AUTO_PUBLISH') ? (bool)CONTENT_PIPELINE_AUTO_PUBLISH : false;
        $minScore = $minScore ?? (float)CONTENT_PIPELINE_AUTO_MIN_SCORE;
        $maxPerRun = $maxPerRun ?? (int)CONTENT_PIPELINE_AUTO_MAX_PER_RUN;
        $publish = $publish ?? $autoPublishEnabled;

        $stats = [
            'auto_write_enabled' => $autoWriteEnabled,
            'auto_publish_enabled' => $autoWriteEnabled && $publish,
            'auto_min_score' => $minScore,
            'auto_max_per_run' => $maxPerRun,
            'auto_queue_count' => 0,
            'auto_processed' => 0,
            'auto_published' => 0,
            'auto_failed' => 0,
            'auto_rate_limited' => false,
            'auto_results' => [],
            'auto_errors' => [],
        ];

        if (!$autoWriteEnabled || $maxPerRun <= 0) {
            return $stats;
        }

        $candidateModel = new ContentCandidateModel();
        $queue = $candidateModel->getAutoQueue($minScore, $maxPerRun, ['new', 'reviewed'], self::AUTO_QUEUE_MAX_AGE_HOURS);
        $stats['auto_queue_count'] = count($queue);

        foreach ($queue as $candidate) {
            $candidateId = (int)($candidate['id'] ?? 0);
            if ($candidateId <= 0) {
                continue;
            }

            try {
                $result = self::generateDraftFromCandidate($candidateId, $authorId, $publish);
                $stats['auto_processed']++;
                if (!empty($result['published'])) {
                    $stats['auto_published']++;
                }

                $stats['auto_results'][] = [
                    'candidate_id' => $candidateId,
                    'post_id' => (int)($result['post_id'] ?? 0),
                    'published' => !empty($result['published']),
                    'created' => !empty($result['created']),
                ];
            } catch (Throwable $e) {
                $stats['auto_failed']++;
                $stats['auto_errors'][] = 'Candidate #' . $candidateId . ': ' . $e->getMessage();
                if (self::isRateLimitError($e)) {
                    $stats['auto_rate_limited'] = true;
                    break;
                }
            }
        }

        return $stats;
    }

    public static function generateDraftFromCandidate(int $candidateId, int $authorId = 0, bool $publish = false): array {
        $candidateModel = new ContentCandidateModel();
        $candidate = $candidateModel->findById($candidateId);
        if (!$candidate) {
            throw new InvalidArgumentException('Candidate not found.');
        }

        $existingDraftId = (int)($candidate['draft_post_id'] ?? 0);
        if ($existingDraftId > 0) {
            $existingPost = (new PostModel())->findById($existingDraftId);
            if ($existingPost) {
                return [
                    'candidate_id' => $candidateId,
                    'post_id' => $existingDraftId,
                    'edit_url' => '/employee/create?edit=' . $existingDraftId,
                    'message' => 'Existing draft opened.',
                    'created' => false,
                    'published' => (string)($existingPost['status'] ?? '') === 'published',
                ];
            }
        }

        $authorId = self::resolveAuthorId($authorId);
        if ($authorId <= 0) {
            throw new RuntimeException('No active editorial user is available for generated drafts.');
        }

        $categoryModel = new CategoryModel();
        $candidateCategorySlug = trim((string)($candidate['category_slug_hint'] ?? ''));
        if ($candidateCategorySlug !== '') {
            $categoryModel->ensureTopLevelCategory($candidateCategorySlug);
        }

        $topCategories = $categoryModel->getTopLevel();
        $categoryOptions = array_map(static function (array $category): array {
            return [
                'id' => (int)$category['id'],
                'name' => (string)$category['name'],
                'slug' => (string)$category['slug'],
            ];
        }, $topCategories);

        $topic = trim((string)($candidate['topic_name'] ?? $candidate['title'] ?? ''));
        $angleParts = array_filter([
            trim((string)($candidate['excerpt'] ?? '')),
            trim((string)($candidate['content_snippet'] ?? '')),
            trim((string)($candidate['source_name'] ?? '')) !== '' ? 'Source: ' . trim((string)$candidate['source_name']) : null,
            trim((string)($candidate['external_url'] ?? '')) !== '' ? 'Reference URL: ' . trim((string)$candidate['external_url']) : null,
        ]);

        $draft = XaiWriter::generateArticle([
            'topic' => $topic,
            'angle' => implode("\n", array_slice($angleParts, 0, 3)),
            'tone' => (float)($candidate['trend_score'] ?? 0) >= CONTENT_PIPELINE_TRENDING_THRESHOLD ? 'breaking' : 'standard',
            'language' => 'english',
            'word_count' => 450,
            'preferred_type' => self::guessPostType((string)($candidate['published_at'] ?? ''), (float)($candidate['trend_score'] ?? 0)),
            'selected_category_slug' => $candidateCategorySlug,
            'category_options' => $categoryOptions,
            'source_name' => trim((string)($candidate['source_name'] ?? '')),
            'source_url' => trim((string)($candidate['external_url'] ?? '')) !== ''
                ? (string)$candidate['external_url']
                : (string)($candidate['source_url'] ?? ''),
            'published_at' => trim((string)($candidate['published_at'] ?? '')),
            'newsroom_mode' => 'fresh_trending_seo',
        ]);

        $categorySlug = trim((string)($draft['category_slug_hint'] ?? $candidateCategorySlug));
        $category = $categorySlug !== '' ? $categoryModel->ensureTopLevelCategory($categorySlug) : null;
        $categoryId = $category ? (int)$category['id'] : null;
        $categoryName = $category ? (string)$category['name'] : null;

        $candidateTags = json_decode((string)($candidate['keyword_tags'] ?? '[]'), true);
        if (!is_array($candidateTags)) {
            $candidateTags = [];
        }

        $tags = array_values(array_unique(array_filter(array_merge(
            array_map('strval', $draft['tags'] ?? []),
            array_map('strval', $candidateTags)
        ))));

        $thumbnail = self::importCandidateImage($candidate);
        $postModel = new PostModel();
        $draftTitle = self::limitText((string)($draft['title'] ?? ''), 300);
        $postId = (int)$postModel->create([
            'user_id' => $authorId,
            'category_id' => $categoryId,
            'type' => in_array($draft['post_type_hint'] ?? '', ['news', 'article', 'breaking'], true)
                ? $draft['post_type_hint']
                : self::guessPostType((string)($candidate['published_at'] ?? ''), (float)($candidate['trend_score'] ?? 0)),
            'title' => $draftTitle,
            'content' => (string)$draft['content_html'],
            'excerpt' => (string)$draft['excerpt'],
            'thumbnail' => $thumbnail,
            'image_alt' => Helper::defaultImageAlt($draftTitle, $categoryName),
            'source_name' => self::limitText((string)($candidate['source_name'] ?? ''), 150),
            'source_url' => trim((string)($candidate['external_url'] ?? '')) !== ''
                ? (string)$candidate['external_url']
                : (string)($candidate['source_url'] ?? ''),
            'status' => $publish ? 'published' : 'draft',
            'approved_by' => $publish ? $authorId : null,
            'is_trending' => (float)($candidate['trend_score'] ?? 0) >= CONTENT_PIPELINE_TRENDING_THRESHOLD ? 1 : 0,
            'location' => 'both',
            'seo_title' => self::limitText((string)($draft['seo_title'] ?? ''), 300),
            'seo_description' => self::limitText((string)($draft['seo_description'] ?? ''), 500),
            'seo_keywords' => Helper::seoKeywordsFromPost($draftTitle, $categoryName, $tags),
        ]);

        self::attachTagsToPost($postId, $tags);
        $candidateModel->attachDraft($candidateId, $postId);

        return [
            'candidate_id' => $candidateId,
            'post_id' => $postId,
            'edit_url' => '/employee/create?edit=' . $postId,
            'message' => $publish ? 'Article generated and published automatically.' : 'Draft generated successfully.',
            'created' => true,
            'published' => $publish,
        ];
    }

    public static function resolveAuthorId(int $preferredUserId = 0): int {
        $db = Database::getInstance();

        foreach ([$preferredUserId, (int)CONTENT_PIPELINE_DEFAULT_USER_ID] as $candidateUserId) {
            if ($candidateUserId <= 0) {
                continue;
            }

            $user = $db->fetchOne(
                "SELECT id
                 FROM users
                 WHERE id=? AND is_active=1",
                [$candidateUserId]
            );

            if ($user) {
                return (int)$user['id'];
            }
        }

        $editor = $db->fetchOne(
            "SELECT u.id
             FROM users u
             JOIN roles r ON r.id=u.role_id
             WHERE u.is_active=1
               AND r.slug IN ('editor','admin','super_admin','manager','reporter')
             ORDER BY FIELD(r.slug, 'editor', 'admin', 'super_admin', 'manager', 'reporter'), u.id ASC
             LIMIT 1"
        );

        return (int)($editor['id'] ?? 0);
    }

    private static function fetchUrl(string $url): string {
        $timeout = max(5, (int)CONTENT_PIPELINE_TIMEOUT);
        $verifySsl = defined('CONTENT_PIPELINE_SSL_VERIFY') ? (bool)CONTENT_PIPELINE_SSL_VERIFY : true;
        $userAgent = 'FatakNewsContentPipeline/1.0 (+'
            . (Helper::appUrl() !== '' ? Helper::appUrl() : 'localhost')
            . ')';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 4,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => $userAgent,
                CURLOPT_ENCODING => '',
                CURLOPT_SSL_VERIFYPEER => $verifySsl,
                CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
                CURLOPT_HTTPHEADER => ['Accept: application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9, */*;q=0.8'],
            ]);

            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($body === false || $status >= 400) {
                throw new RuntimeException($error !== '' ? $error : ('HTTP ' . $status));
            }

            return (string)$body;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'user_agent' => $userAgent,
                'follow_location' => 1,
                'max_redirects' => 4,
                'header' => "Accept: application/rss+xml, application/atom+xml, application/xml, text/xml\r\n",
            ],
            'ssl' => [
                'verify_peer' => $verifySsl,
                'verify_peer_name' => $verifySsl,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new RuntimeException('Unable to fetch remote feed.');
        }

        return $body;
    }

    private static function parseFeed(string $xml, array $feed): array {
        if (!function_exists('simplexml_load_string')) {
            throw new RuntimeException('SimpleXML extension is required for RSS ingestion.');
        }

        libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$document) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $message = !empty($errors) ? trim((string)$errors[0]->message) : 'Invalid feed XML.';
            throw new RuntimeException($message);
        }

        $items = [];
        if (isset($document->channel->item)) {
            $sourceName = trim((string)($document->channel->title ?? $feed['name']));
            foreach ($document->channel->item as $item) {
                $items[] = self::parseRssItem($item, $feed, $sourceName);
            }
        } elseif (isset($document->entry)) {
            $sourceName = trim((string)($document->title ?? $feed['name']));
            foreach ($document->entry as $entry) {
                $items[] = self::parseAtomEntry($entry, $feed, $sourceName);
            }
        } else {
            throw new RuntimeException('Feed format is not supported.');
        }

        return $items;
    }

    private static function parseRssItem(SimpleXMLElement $item, array $feed, string $sourceName): array {
        $namespaces = $item->getNamespaces(true);
        $contentNode = isset($namespaces['content']) ? $item->children($namespaces['content']) : null;
        $mediaNode = isset($namespaces['media']) ? $item->children($namespaces['media']) : null;

        $imageUrl = '';
        if ($mediaNode && isset($mediaNode->content)) {
            $attrs = $mediaNode->content->attributes();
            $imageUrl = trim((string)($attrs['url'] ?? ''));
        }
        if ($imageUrl === '' && $mediaNode && isset($mediaNode->thumbnail)) {
            $attrs = $mediaNode->thumbnail->attributes();
            $imageUrl = trim((string)($attrs['url'] ?? ''));
        }
        if ($imageUrl === '' && isset($item->enclosure)) {
            $attrs = $item->enclosure->attributes();
            $imageUrl = trim((string)($attrs['url'] ?? ''));
        }

        $categories = [];
        foreach ($item->category as $category) {
            $value = trim((string)$category);
            if ($value !== '') {
                $categories[] = $value;
            }
        }

        return [
            'source_name' => $sourceName !== '' ? $sourceName : (string)$feed['name'],
            'source_url' => (string)($feed['url'] ?? ''),
            'source_type' => (string)($feed['source_type'] ?? 'rss'),
            'external_url' => trim((string)($item->link ?? '')),
            'title' => trim((string)($item->title ?? '')),
            'excerpt' => trim((string)($item->description ?? '')),
            'content' => trim((string)($contentNode->encoded ?? '')),
            'author_name' => trim((string)($item->author ?? '')),
            'published_at' => trim((string)($item->pubDate ?? '')),
            'categories' => $categories,
            'guid' => trim((string)($item->guid ?? '')),
            'image_url' => $imageUrl,
        ];
    }

    private static function parseAtomEntry(SimpleXMLElement $entry, array $feed, string $sourceName): array {
        $link = '';
        foreach ($entry->link as $entryLink) {
            $attrs = $entryLink->attributes();
            $rel = trim((string)($attrs['rel'] ?? ''));
            if ($rel === '' || $rel === 'alternate') {
                $link = trim((string)($attrs['href'] ?? ''));
                if ($link !== '') {
                    break;
                }
            }
        }

        $categories = [];
        foreach ($entry->category as $category) {
            $attrs = $category->attributes();
            $value = trim((string)($attrs['term'] ?? $category));
            if ($value !== '') {
                $categories[] = $value;
            }
        }

        return [
            'source_name' => $sourceName !== '' ? $sourceName : (string)$feed['name'],
            'source_url' => (string)($feed['url'] ?? ''),
            'source_type' => (string)($feed['source_type'] ?? 'atom'),
            'external_url' => $link,
            'title' => trim((string)($entry->title ?? '')),
            'excerpt' => trim((string)($entry->summary ?? '')),
            'content' => trim((string)($entry->content ?? '')),
            'author_name' => trim((string)($entry->author->name ?? '')),
            'published_at' => trim((string)($entry->published ?? $entry->updated ?? '')),
            'categories' => $categories,
            'guid' => trim((string)($entry->id ?? '')),
            'image_url' => '',
        ];
    }

    private static function normalizeCandidateInput(array $item, array $feed, array $topCategories): ?array {
        $title = self::cleanText((string)($item['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $excerpt = Helper::normalizedExcerpt((string)($item['excerpt'] ?? ''), 260);
        $contentSnippet = Helper::plainText((string)($item['content'] ?? ''));
        if ($contentSnippet === '' && $excerpt !== '') {
            $contentSnippet = $excerpt;
        }

        $publishedAt = self::normalizeTimestamp((string)($item['published_at'] ?? ''));
        $combinedText = mb_strtolower(trim(implode(' ', array_filter([
            $title,
            $excerpt,
            $contentSnippet,
            implode(' ', array_map('strval', $item['categories'] ?? [])),
        ]))));
        $categorySlugHint = self::inferCategorySlug($combinedText, trim((string)($feed['category_slug'] ?? '')), $topCategories);
        $keywordTags = self::extractKeywordTags(trim($title . ' ' . $excerpt . ' ' . implode(' ', array_map('strval', $item['categories'] ?? []))));
        $topicKey = self::buildTopicKey($title);

        $freshnessScore = self::freshnessScore($publishedAt);
        $keywordScore = self::keywordScore($combinedText);
        $sourceScore = self::sourceScore((float)($feed['weight'] ?? 1.0));

        $externalUrl = trim((string)($item['external_url'] ?? ''));
        if ($externalUrl !== '' && !filter_var($externalUrl, FILTER_VALIDATE_URL)) {
            $externalUrl = '';
        }
        if (self::shouldSkipCandidate($title, $excerpt, $contentSnippet, (string)($feed['name'] ?? ''), $externalUrl)) {
            return null;
        }

        $dedupeSeed = $externalUrl !== ''
            ? mb_strtolower($externalUrl)
            : mb_strtolower(trim((string)($feed['url'] ?? '') . '|' . $topicKey . '|' . ($publishedAt !== null ? substr($publishedAt, 0, 10) : '')));

        return [
            'source_name' => self::limitText((string)($item['source_name'] ?? $feed['name'] ?? 'Feed'), 150),
            'source_url' => trim((string)($item['source_url'] ?? $feed['url'] ?? '')),
            'source_type' => trim((string)($item['source_type'] ?? $feed['source_type'] ?? 'rss')) ?: 'rss',
            'external_url' => $externalUrl !== '' ? $externalUrl : null,
            'title' => self::limitText($title, 500),
            'excerpt' => $excerpt !== '' ? $excerpt : null,
            'content_snippet' => $contentSnippet !== '' ? mb_substr($contentSnippet, 0, 2000) : null,
            'author_name' => self::limitText(self::cleanText((string)($item['author_name'] ?? '')), 150) ?: null,
            'published_at' => $publishedAt,
            'topic_name' => self::limitText($title, 255),
            'topic_key' => $topicKey,
            'category_slug_hint' => $categorySlugHint !== '' ? $categorySlugHint : null,
            'keyword_tags' => json_encode(array_values($keywordTags), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'dedupe_key' => sha1($dedupeSeed),
            'trend_score' => self::scoreTotal($freshnessScore, $keywordScore, $sourceScore, 0),
            'freshness_score' => $freshnessScore,
            'keyword_score' => $keywordScore,
            'source_score' => $sourceScore,
            'cluster_score' => 0,
            'meta_json' => json_encode([
                'feed_name' => (string)($feed['name'] ?? ''),
                'feed_url' => (string)($feed['url'] ?? ''),
                'source_categories' => array_values(array_map('strval', $item['categories'] ?? [])),
                'guid' => (string)($item['guid'] ?? ''),
                'image_url' => trim((string)($item['image_url'] ?? '')),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    private static function shouldSkipCandidate(string $title, string $excerpt, string $contentSnippet, string $feedName, string $externalUrl): bool {
        if ($externalUrl === '') {
            return true;
        }

        $context = self::cleanText(trim($title . ' ' . $excerpt . ' ' . $contentSnippet));
        if (mb_strlen($context) < self::MIN_CONTEXT_CHARS) {
            return true;
        }

        $qualityText = mb_strtolower(trim($title . ' ' . $excerpt . ' ' . $feedName . ' ' . $externalUrl));
        $lowValueSignals = [
            'opinion', 'editorial', 'op-ed', 'analysis', 'explainer', 'how to', 'guide',
            'gallery', 'photos', 'photo story', 'horoscope', 'review roundup',
            'watch live', 'live tv', 'streaming on', 'secret of'
        ];

        return self::containsAny($qualityText, $lowValueSignals);
    }

    private static function recalculateTopicScores(ContentCandidateModel $candidateModel, string $topicKey): void {
        $candidates = $candidateModel->findRecentByTopicKey($topicKey, 72);
        $count = count($candidates);
        if ($count === 0) {
            return;
        }

        $clusterScore = min(18.0, max(0, $count - 1) * 6.0);
        foreach ($candidates as $candidate) {
            $total = self::scoreTotal(
                (float)($candidate['freshness_score'] ?? 0),
                (float)($candidate['keyword_score'] ?? 0),
                (float)($candidate['source_score'] ?? 0),
                $clusterScore
            );

            $candidateModel->update((int)$candidate['id'], [
                'cluster_score' => $clusterScore,
                'trend_score' => $total,
            ]);
        }
    }

    private static function inferCategorySlug(string $text, string $fallbackSlug, array $topCategories): string {
        $text = mb_strtolower(trim($text));
        $fallbackSlug = Helper::slug($fallbackSlug);
        $available = [];
        foreach ($topCategories as $category) {
            $slug = trim((string)($category['slug'] ?? ''));
            if ($slug !== '') {
                $available[$slug] = $category;
            }
        }

        if ($text === '') {
            return $fallbackSlug;
        }

        $keywordMap = [
            'politics' => ['politics', 'election', 'minister', 'parliament', 'bjp', 'congress', 'cm', 'pm', 'government', 'cabinet'],
            'business' => ['business', 'market', 'stocks', 'stock', 'economy', 'gdp', 'rupee', 'startup funding', 'company', 'ipo'],
            'markets' => ['market', 'stocks', 'shares', 'sensex', 'nifty', 'dow', 'nasdaq', 'ipo', 'earnings'],
            'sports' => ['sports', 'match', 'cricket', 'ipl', 'football', 'goal', 'tournament', 'odi', 'test match', 'kabaddi'],
            'technology' => ['technology', 'tech', 'ai', 'artificial intelligence', 'startup', 'app', 'smartphone', 'gadget', 'cyber', 'software'],
            'entertainment' => ['entertainment', 'film', 'movie', 'actor', 'actress', 'bollywood', 'ott', 'music', 'trailer', 'celebrity'],
            'health' => ['health', 'hospital', 'disease', 'covid', 'doctor', 'medical', 'vaccine', 'wellness', 'fitness'],
            'education' => ['education', 'school', 'college', 'exam', 'neet', 'jee', 'university', 'student', 'results'],
            'international' => ['international', 'world', 'us', 'china', 'russia', 'ukraine', 'global', 'foreign'],
            'crime' => ['crime', 'police', 'arrest', 'murder', 'fraud', 'raid', 'accused', 'court'],
            'lifestyle' => ['lifestyle', 'travel', 'food', 'fashion', 'festival', 'culture', 'home decor'],
            'science' => ['science', 'research', 'space', 'nasa', 'isro', 'lab', 'scientists', 'discovery'],
            'climate' => ['climate', 'weather', 'heatwave', 'flood', 'rainfall', 'environment', 'emissions', 'warming'],
            'travel' => ['travel', 'tourism', 'aviation', 'airline', 'visa', 'destination', 'hotel'],
            'auto' => ['auto', 'car', 'ev', 'electric vehicle', 'bike', 'automaker', 'mobility'],
            'defence' => ['defence', 'defense', 'army', 'navy', 'air force', 'military', 'security', 'missile'],
            'startups' => ['startup', 'founder', 'funding', 'vc', 'valuation', 'seed round', 'series a'],
            'world' => ['world', 'global', 'international', 'diplomacy', 'foreign affairs', 'geopolitics'],
        ];

        $scores = [];
        foreach ($available as $slug => $category) {
            $score = 0;
            if ($fallbackSlug !== '' && $slug === $fallbackSlug) {
                $score += 4;
            }

            $name = mb_strtolower((string)($category['name'] ?? ''));
            if ($name !== '' && str_contains($text, $name)) {
                $score += 5;
            }

            foreach ($keywordMap[$slug] ?? [] as $keyword) {
                if (str_contains($text, $keyword)) {
                    $score += 3;
                }
            }

            if ($score > 0) {
                $scores[$slug] = $score;
            }
        }

        if (empty($scores)) {
            if ($fallbackSlug !== '') {
                return $fallbackSlug;
            }

            $discovered = [];
            foreach ($keywordMap as $slug => $keywords) {
                $score = 0;
                foreach ($keywords as $keyword) {
                    if (str_contains($text, $keyword)) {
                        $score += 3;
                    }
                }

                if ($score > 0) {
                    $discovered[$slug] = $score;
                }
            }

            if (empty($discovered)) {
                return '';
            }

            arsort($discovered);
            return (string)array_key_first($discovered);
        }

        arsort($scores);
        return (string)array_key_first($scores);
    }

    private static function extractKeywordTags(string $text, int $limit = 6): array {
        $normalized = mb_strtolower(trim(preg_replace('/[^\pL\pN]+/u', ' ', $text) ?? ''));
        if ($normalized === '') {
            return [];
        }

        $tokens = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tags = [];
        foreach ($tokens as $token) {
            if (mb_strlen($token) < 4 || in_array($token, self::STOPWORDS, true)) {
                continue;
            }

            $slug = Helper::slug($token);
            if ($slug === '' || in_array($slug, $tags, true)) {
                continue;
            }

            $tags[] = $slug;
            if (count($tags) >= $limit) {
                break;
            }
        }

        return $tags;
    }

    private static function buildTopicKey(string $title): string {
        $normalized = mb_strtolower(trim(preg_replace('/[^\pL\pN]+/u', ' ', $title) ?? ''));
        if ($normalized === '') {
            return 'topic-' . substr(sha1($title), 0, 16);
        }

        $tokens = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $important = [];
        foreach ($tokens as $token) {
            if (mb_strlen($token) < 3 || in_array($token, self::STOPWORDS, true)) {
                continue;
            }

            $important[] = $token;
            if (count($important) >= 8) {
                break;
            }
        }

        $slug = Helper::slug(implode(' ', $important));
        if ($slug === '') {
            $slug = 'topic-' . substr(sha1($normalized), 0, 16);
        }

        return mb_substr($slug, 0, 170);
    }

    private static function freshnessScore(?string $publishedAt): float {
        if ($publishedAt === null || $publishedAt === '') {
            return 14.0;
        }

        $timestamp = strtotime($publishedAt);
        if ($timestamp === false) {
            return 14.0;
        }

        $hours = max(0, (time() - $timestamp) / 3600);
        if ($hours <= 2) {
            return 45.0;
        }
        if ($hours <= 6) {
            return 38.0;
        }
        if ($hours <= 12) {
            return 32.0;
        }
        if ($hours <= 24) {
            return 26.0;
        }
        if ($hours <= 48) {
            return 18.0;
        }
        if ($hours <= 72) {
            return 12.0;
        }
        if ($hours <= 168) {
            return 6.0;
        }

        return 2.0;
    }

    private static function keywordScore(string $text): float {
        if ($text === '') {
            return 0.0;
        }

        $positiveSignals = [
            'breaking', 'exclusive', 'live', 'wins', 'win', 'loss', 'budget', 'crash', 'alert',
            'election', 'policy', 'verdict', 'launch', 'funding', 'acquisition', 'record',
            'update', 'updates', 'results', 'score', 'ban', 'raid', 'arrest', 'announces',
            'approved', 'deadline', 'files', 'raises', 'merger', 'probe'
        ];
        $negativeSignals = [
            'opinion', 'editorial', 'analysis', 'explainer', 'guide', 'how to',
            'gallery', 'photos', 'horoscope', 'review'
        ];

        $score = 0.0;
        foreach ($positiveSignals as $signal) {
            if (str_contains($text, $signal)) {
                $score += 3.5;
            }
        }

        foreach ($negativeSignals as $signal) {
            if (str_contains($text, $signal)) {
                $score -= 5.0;
            }
        }

        return max(0.0, min(24.0, $score));
    }

    private static function sourceScore(float $weight): float {
        return min(18.0, max(6.0, round($weight * 12, 2)));
    }

    private static function scoreTotal(float $freshness, float $keyword, float $source, float $cluster): float {
        return round($freshness + $keyword + $source + $cluster, 2);
    }

    private static function guessPostType(string $publishedAt, float $trendScore): string {
        $freshness = self::freshnessScore(self::normalizeTimestamp($publishedAt));
        if ($trendScore >= CONTENT_PIPELINE_TRENDING_THRESHOLD && $freshness >= 32) {
            return 'breaking';
        }

        if ($trendScore >= 40) {
            return 'news';
        }

        return 'article';
    }

    private static function isRateLimitError(Throwable $e): bool {
        return stripos($e->getMessage(), 'rate limit') !== false;
    }

    private static function containsAny(string $text, array $needles): bool {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeTimestamp(string $value): ?string {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private static function cleanText(string $value): string {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');
        return trim($value);
    }

    private static function limitText(string $value, int $maxLength): string {
        $value = self::cleanText($value);
        if ($maxLength <= 0 || $value === '') {
            return $value;
        }

        return mb_strlen($value) > $maxLength ? rtrim(mb_substr($value, 0, $maxLength - 1)) . '…' : $value;
    }

    private static function attachTagsToPost(int $postId, array $tags): void {
        if ($postId <= 0 || empty($tags)) {
            return;
        }

        $db = Database::getInstance();
        $db->delete('post_tags', 'post_id=?', [$postId]);

        foreach ($tags as $tagName) {
            $tagName = trim((string)$tagName);
            if ($tagName === '') {
                continue;
            }

            $slug = Helper::slug($tagName);
            if ($slug === '') {
                continue;
            }

            $existing = $db->fetchOne("SELECT id FROM tags WHERE slug=?", [$slug]);
            $tagId = $existing ? (int)$existing['id'] : (int)$db->insert('tags', ['name' => $tagName, 'slug' => $slug]);
            $db->insert('post_tags', ['post_id' => $postId, 'tag_id' => $tagId]);
        }
    }

    private static function importCandidateImage(array $candidate): ?string {
        $meta = json_decode((string)($candidate['meta_json'] ?? ''), true);
        if (!is_array($meta)) {
            return null;
        }

        $imageUrl = trim((string)($meta['image_url'] ?? ''));
        if ($imageUrl === '' || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            return self::downloadRemoteImage($imageUrl);
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function downloadRemoteImage(string $url): ?string {
        $binary = self::fetchUrl($url);
        if ($binary === '' || strlen($binary) > MAX_IMAGE_SIZE) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_buffer($finfo, $binary) ?: '';
        finfo_close($finfo);

        if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
            return null;
        }

        $extension = 'jpg';
        if ($mime === 'image/png') {
            $extension = 'png';
        } elseif ($mime === 'image/webp') {
            $extension = 'webp';
        } elseif ($mime === 'image/gif') {
            $extension = 'gif';
        }

        $targetDir = UPLOAD_PATH . '/thumbnails';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            return null;
        }

        $filename = uniqid('pipe_', true) . '.' . $extension;
        $target = $targetDir . '/' . $filename;

        if (file_put_contents($target, $binary) === false) {
            return null;
        }

        return $filename;
    }
}
