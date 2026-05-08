<?php
// public/index.php - Front Controller

if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $publicFile = dirname(__DIR__) . $requestPath;

    if (is_file($publicFile)) {
        return false;
    }
}

require_once __DIR__ . '/../includes/bootstrap.php';

define('VIEW', BASE_PATH . '/app/views/');

function rewriteAppHtml(string $html): string {
    $base = Helper::basePath();
    if ($base === '') {
        return $html;
    }

    return preg_replace('/\b(href|src|action)="\/(?!\/)/', '$1="' . $base . '/', $html) ?? $html;
}

function renderView(string $file, array $vars = [], int $status = 200): void {
    http_response_code($status);
    extract($vars, EXTR_SKIP);

    if (is_file($file)) {
        ob_start();
        include $file;
        echo rewriteAppHtml(ob_get_clean());
        return;
    }

    $pageTitle = $pageTitle ?? 'Coming Soon';
    $message = $message ?? 'This section is not available yet.';
    ob_start();
    include VIEW . 'pages/placeholder.php';
    echo rewriteAppHtml(ob_get_clean());
}

function renderText(string $content, string $contentType = 'text/plain; charset=UTF-8', int $status = 200): void {
    http_response_code($status);
    header('Content-Type: ' . $contentType);
    echo $content;
    exit;
}

function renderXml(string $content, int $status = 200): void {
    renderText($content, 'application/xml; charset=UTF-8', $status);
}

function xmlEscape(string $value): string {
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function renderRobotsTxt(): void {
    $host = (string)(parse_url(Helper::siteUrl(), PHP_URL_HOST) ?? '');
    $lines = [
        'User-agent: *',
        'Allow: /',
        'Disallow: /admin',
        'Disallow: /manager',
        'Disallow: /employee',
        'Disallow: /hr',
        'Disallow: /api',
        'Disallow: /login',
        'Disallow: /register',
        'Disallow: /settings',
        'Disallow: /notifications',
        'Disallow: /bookmarks',
        'Disallow: /community/create',
        'Disallow: /forgot-password',
        'Disallow: /reset-password',
        'Disallow: /profile',
        'Disallow: /logout',
        'Disallow: /auth/google',
        'Disallow: /auth/google/callback',
        '',
        'Sitemap: ' . Helper::siteUrl('sitemap.xml'),
    ];

    if (!empty(collectNewsSitemapPosts())) {
        $lines[] = 'Sitemap: ' . Helper::siteUrl('news-sitemap.xml');
    }

    if ($host !== '') {
        $lines[] = 'Host: ' . $host;
    }

    renderText(implode("\n", $lines) . "\n");
}

function buildUrlSitemapXml(array $urls): string {
    $hasImages = false;
    foreach ($urls as $url) {
        if (!empty($url['image'])) {
            $hasImages = true;
            break;
        }
    }

    $xml = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . ($hasImages ? ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' : '') . '>',
    ];

    foreach ($urls as $url) {
        $path = trim((string)($url['path'] ?? ''));
        if ($path === '' && $path !== '0') {
            $path = '';
        }

        $xml[] = '  <url>';
        $xml[] = '    <loc>' . xmlEscape(Helper::siteUrl($path)) . '</loc>';
        if (!empty($url['lastmod'])) {
            $xml[] = '    <lastmod>' . xmlEscape((string)$url['lastmod']) . '</lastmod>';
        }
        if (!empty($url['changefreq'])) {
            $xml[] = '    <changefreq>' . xmlEscape((string)$url['changefreq']) . '</changefreq>';
        }
        if (!empty($url['priority'])) {
            $xml[] = '    <priority>' . xmlEscape((string)$url['priority']) . '</priority>';
        }
        if (!empty($url['image'])) {
            $xml[] = '    <image:image>';
            $xml[] = '      <image:loc>' . xmlEscape((string)$url['image']) . '</image:loc>';
            if (!empty($url['image_title'])) {
                $xml[] = '      <image:title>' . xmlEscape((string)$url['image_title']) . '</image:title>';
            }
            if (!empty($url['image_caption'])) {
                $xml[] = '      <image:caption>' . xmlEscape((string)$url['image_caption']) . '</image:caption>';
            }
            $xml[] = '    </image:image>';
        }
        $xml[] = '  </url>';
    }

    $xml[] = '</urlset>';

    return implode("\n", $xml) . "\n";
}

function renderUrlSitemap(array $urls): void {
    renderXml(buildUrlSitemapXml($urls));
}

function buildSitemapIndexXml(array $sitemaps): string {
    $xml = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
    ];

    foreach ($sitemaps as $sitemap) {
        $xml[] = '  <sitemap>';
        $xml[] = '    <loc>' . xmlEscape(Helper::siteUrl((string)$sitemap['path'])) . '</loc>';
        if (!empty($sitemap['lastmod'])) {
            $xml[] = '    <lastmod>' . xmlEscape((string)$sitemap['lastmod']) . '</lastmod>';
        }
        $xml[] = '  </sitemap>';
    }

    $xml[] = '</sitemapindex>';

    return implode("\n", $xml) . "\n";
}

function sitemapDate(?string $value = null, ?string $fallback = null): string {
    $candidate = trim((string)($value ?? ''));
    if ($candidate === '') {
        $candidate = trim((string)($fallback ?? ''));
    }
    if ($candidate === '') {
        $candidate = 'now';
    }

    return date('c', strtotime($candidate));
}

function sitemapTemplateLastmod(array $paths): string {
    $timestamps = [];

    foreach ($paths as $path) {
        if (is_file($path)) {
            $timestamps[] = (int)filemtime($path);
        }
    }

    return date('c', max($timestamps ?: [time()]));
}

function sitemapMaxLastmod(array $entries, ?string $fallback = null): string {
    $timestamps = [];

    foreach ($entries as $entry) {
        $lastmod = trim((string)($entry['lastmod'] ?? ''));
        if ($lastmod === '') {
            continue;
        }

        $timestamp = strtotime($lastmod);
        if ($timestamp !== false) {
            $timestamps[] = $timestamp;
        }
    }

    if (empty($timestamps)) {
        return sitemapDate($fallback);
    }

    return date('c', max($timestamps));
}

function sitemapLatestPostLastmod(string $whereSql = '1=1', array $params = []): ?string {
    $db = Database::getInstance();
    $row = $db->fetchOne(
        "SELECT MAX(COALESCE(updated_at, published_at, created_at)) AS lastmod
         FROM posts
         WHERE status='published' AND {$whereSql}",
        $params
    );

    $lastmod = trim((string)($row['lastmod'] ?? ''));

    return $lastmod !== '' ? $lastmod : null;
}

function collectPageSitemapEntries(): array {
    $homeLastmod = sitemapDate(
        sitemapLatestPostLastmod("COALESCE(location,'') <> 'explore'"),
        sitemapTemplateLastmod([
            VIEW . 'pages/home.php',
            __FILE__,
        ])
    );
    $exploreLastmod = sitemapDate(
        sitemapLatestPostLastmod("location='explore'"),
        sitemapTemplateLastmod([
            VIEW . 'pages/explore.php',
            __FILE__,
        ])
    );
    $communityLastmod = sitemapDate(
        sitemapLatestPostLastmod("type IN ('community_post','thought')"),
        sitemapTemplateLastmod([
            VIEW . 'pages/community.php',
            __FILE__,
        ])
    );
    $staticLastmod = sitemapTemplateLastmod([
        VIEW . 'pages/static_page.php',
        __FILE__,
    ]);

    return [
        ['path' => '', 'priority' => '1.0', 'changefreq' => 'hourly', 'lastmod' => $homeLastmod],
        ['path' => 'feed', 'priority' => '0.9', 'changefreq' => 'hourly', 'lastmod' => $homeLastmod],
        ['path' => 'trending', 'priority' => '0.9', 'changefreq' => 'hourly', 'lastmod' => $homeLastmod],
        ['path' => 'explore', 'priority' => '0.8', 'changefreq' => 'hourly', 'lastmod' => $exploreLastmod],
        ['path' => 'community', 'priority' => '0.8', 'changefreq' => 'daily', 'lastmod' => $communityLastmod],
        ['path' => 'about', 'priority' => '0.5', 'changefreq' => 'monthly', 'lastmod' => $staticLastmod],
        ['path' => 'contact', 'priority' => '0.5', 'changefreq' => 'monthly', 'lastmod' => $staticLastmod],
        ['path' => 'privacy', 'priority' => '0.3', 'changefreq' => 'yearly', 'lastmod' => $staticLastmod],
        ['path' => 'terms', 'priority' => '0.3', 'changefreq' => 'yearly', 'lastmod' => $staticLastmod],
        ['path' => 'advertise', 'priority' => '0.4', 'changefreq' => 'monthly', 'lastmod' => $staticLastmod],
        ['path' => 'careers', 'priority' => '0.4', 'changefreq' => 'monthly', 'lastmod' => $staticLastmod],
        ['path' => 'press', 'priority' => '0.4', 'changefreq' => 'monthly', 'lastmod' => $staticLastmod],
        ['path' => 'disclaimer', 'priority' => '0.3', 'changefreq' => 'yearly', 'lastmod' => $staticLastmod],
        ['path' => 'corrections', 'priority' => '0.4', 'changefreq' => 'monthly', 'lastmod' => $staticLastmod],
    ];
}

function collectCategorySitemapEntries(): array {
    $db = Database::getInstance();
    $categories = $db->fetchAll(
        "SELECT id, parent_id, slug, created_at
         FROM categories
         WHERE is_active=1
         ORDER BY sort_order, name"
    );
    $postRows = $db->fetchAll(
        "SELECT category_id, MAX(COALESCE(updated_at, published_at, created_at)) AS lastmod
         FROM posts
         WHERE status='published' AND category_id IS NOT NULL
         GROUP BY category_id"
    );
    $postsByCategory = [];
    foreach ($postRows as $row) {
        $postsByCategory[(int)($row['category_id'] ?? 0)] = trim((string)($row['lastmod'] ?? ''));
    }
    $childrenByParent = [];
    foreach ($categories as $category) {
        $parentId = isset($category['parent_id']) ? (int)$category['parent_id'] : 0;
        $childrenByParent[$parentId][] = (int)$category['id'];
    }
    $lastmodCache = [];
    $resolveCategoryLastmod = static function (int $categoryId) use (&$resolveCategoryLastmod, &$lastmodCache, $childrenByParent, $postsByCategory, $categories): string {
        if (isset($lastmodCache[$categoryId])) {
            return $lastmodCache[$categoryId];
        }

        $categoryCreatedAt = '';
        foreach ($categories as $category) {
            if ((int)$category['id'] === $categoryId) {
                $categoryCreatedAt = trim((string)($category['created_at'] ?? ''));
                break;
            }
        }

        $timestamps = [];
        $directLastmod = trim((string)($postsByCategory[$categoryId] ?? ''));
        if ($directLastmod !== '') {
            $timestamps[] = strtotime($directLastmod);
        }

        foreach (($childrenByParent[$categoryId] ?? []) as $childId) {
            $timestamps[] = strtotime($resolveCategoryLastmod((int)$childId));
        }

        if ($categoryCreatedAt !== '') {
            $timestamps[] = strtotime($categoryCreatedAt);
        }

        $lastmodCache[$categoryId] = date('c', max(array_filter($timestamps) ?: [time()]));

        return $lastmodCache[$categoryId];
    };
    $urls = [];

    foreach ($categories as $category) {
        $urls[] = [
            'path' => 'category/' . $category['slug'],
            'priority' => '0.7',
            'changefreq' => 'daily',
            'lastmod' => $resolveCategoryLastmod((int)$category['id']),
        ];
    }

    return $urls;
}

function collectPostSitemapEntries(): array {
    $db = Database::getInstance();
    $posts = $db->fetchAll(
        "SELECT p.slug, p.title, p.thumbnail, p.image_alt, p.created_at, p.published_at, p.updated_at, c.slug AS category_slug
         FROM posts p
         LEFT JOIN categories c ON p.category_id=c.id
         WHERE p.status='published'
           AND p.type IN ('news','article','breaking')
           AND COALESCE(p.location,'') <> 'explore'
         ORDER BY COALESCE(p.published_at, p.created_at) DESC"
    );

    return array_map(
        static fn(array $post): array => [
            'path' => ($post['category_slug'] ?: 'news') . '/' . $post['slug'],
            'priority' => '0.8',
            'changefreq' => 'daily',
            'lastmod' => sitemapDate($post['updated_at'] ?: $post['published_at'] ?: $post['created_at'] ?: null),
            'image' => Helper::thumbnailAssetUrl($post['thumbnail'] ?? null),
            'image_title' => trim((string)($post['title'] ?? '')),
            'image_caption' => trim((string)($post['image_alt'] ?? $post['title'] ?? '')),
        ],
        $posts
    );
}

function collectExploreSitemapEntries(): array {
    $db = Database::getInstance();
    $posts = $db->fetchAll(
        "SELECT p.slug, p.title, p.thumbnail, p.image_alt, p.created_at, p.published_at, p.updated_at, c.slug AS category_slug
         FROM posts p
         LEFT JOIN categories c ON p.category_id=c.id
         WHERE p.status='published' AND p.location='explore'
         ORDER BY COALESCE(p.published_at, p.created_at) DESC"
    );

    return array_map(
        static fn(array $post): array => [
            'path' => ($post['category_slug'] ?: 'news') . '/' . $post['slug'],
            'priority' => '0.7',
            'changefreq' => 'hourly',
            'lastmod' => sitemapDate($post['updated_at'] ?: $post['published_at'] ?: $post['created_at'] ?: null),
            'image' => Helper::thumbnailAssetUrl($post['thumbnail'] ?? null),
            'image_title' => trim((string)($post['title'] ?? '')),
            'image_caption' => trim((string)($post['image_alt'] ?? $post['title'] ?? '')),
        ],
        $posts
    );
}

function collectCommunitySitemapEntries(): array {
    $db = Database::getInstance();
    $posts = $db->fetchAll(
        "SELECT p.slug, p.title, p.thumbnail, p.image_alt, p.created_at, p.published_at, p.updated_at, c.slug AS category_slug
         FROM posts p
         LEFT JOIN categories c ON p.category_id=c.id
         WHERE p.status='published' AND p.type IN ('community_post','thought')
         ORDER BY COALESCE(p.published_at, p.created_at) DESC"
    );

    return array_map(
        static fn(array $post): array => [
            'path' => ($post['category_slug'] ?: 'community') . '/' . $post['slug'],
            'priority' => '0.7',
            'changefreq' => 'daily',
            'lastmod' => sitemapDate($post['updated_at'] ?: $post['published_at'] ?: $post['created_at'] ?: null),
            'image' => Helper::thumbnailAssetUrl($post['thumbnail'] ?? null),
            'image_title' => trim((string)($post['title'] ?? '')),
            'image_caption' => trim((string)($post['image_alt'] ?? $post['title'] ?? '')),
        ],
        $posts
    );
}

function collectTagSitemapEntries(): array {
    $db = Database::getInstance();
    $tags = $db->fetchAll(
        "SELECT t.name, t.slug, COUNT(DISTINCT p.id) AS posts_count,
                MAX(COALESCE(p.updated_at, p.published_at, p.created_at)) AS lastmod
         FROM tags t
         JOIN post_tags pt ON pt.tag_id=t.id
         JOIN posts p ON p.id=pt.post_id
         WHERE p.status='published'
         GROUP BY t.id, t.name, t.slug
         ORDER BY lastmod DESC"
    );

    $eligibleTags = array_values(array_filter(
        $tags,
        static fn(array $tag): bool => Helper::tagQualityReport($tag)['indexable']
    ));

    return array_map(
        static fn(array $tag): array => [
            'path' => 'tag/' . $tag['slug'],
            'priority' => '0.6',
            'changefreq' => 'daily',
            'lastmod' => sitemapDate($tag['lastmod'] ?: null),
        ],
        $eligibleTags
    );
}

function collectProfileSitemapEntries(): array {
    $db = Database::getInstance();
    $profiles = $db->fetchAll(
        "SELECT u.username, r.slug AS role_slug, u.is_verified, u.followers_count, u.posts_count, u.bio, u.website,
                MAX(COALESCE(p.updated_at, p.published_at, p.created_at)) AS lastmod
         FROM users u
         JOIN roles r ON u.role_id=r.id
         JOIN posts p ON p.user_id=u.id
         WHERE u.is_active=1 AND p.status='published'
         GROUP BY u.id, u.username, r.slug, u.is_verified, u.followers_count, u.posts_count, u.bio, u.website
         ORDER BY lastmod DESC"
    );

    $profiles = array_values(array_filter(
        $profiles,
        static fn(array $profile): bool => Helper::profileIndexableReport($profile)['indexable']
    ));

    return array_map(
        static fn(array $profile): array => [
            'path' => '@' . $profile['username'],
            'priority' => '0.5',
            'changefreq' => 'weekly',
            'lastmod' => sitemapDate($profile['lastmod'] ?: null),
        ],
        $profiles
    );
}

function collectAllSitemapEntries(): array {
    return array_values(array_merge(
        collectPageSitemapEntries(),
        collectCategorySitemapEntries(),
        collectPostSitemapEntries(),
        collectExploreSitemapEntries(),
        collectCommunitySitemapEntries(),
        collectTagSitemapEntries(),
        collectProfileSitemapEntries()
    ));
}

function renderSectionSitemapXml(string $section): void {
    switch ($section) {
        case 'pages':
            $urls = collectPageSitemapEntries();
            break;
        case 'categories':
            $urls = collectCategorySitemapEntries();
            break;
        case 'posts':
            $urls = collectPostSitemapEntries();
            break;
        case 'explore':
            $urls = collectExploreSitemapEntries();
            break;
        case 'community':
            $urls = collectCommunitySitemapEntries();
            break;
        case 'tags':
            $urls = collectTagSitemapEntries();
            break;
        case 'profiles':
            $urls = collectProfileSitemapEntries();
            break;
        case 'all':
            $urls = collectAllSitemapEntries();
            break;
        default:
            $urls = [];
            break;
    }

    renderUrlSitemap($urls);
}

function renderSitemapIndexXml(): void {
    $indexFallbackLastmod = sitemapTemplateLastmod([__FILE__]);
    $pageEntries = collectPageSitemapEntries();
    $categoryEntries = collectCategorySitemapEntries();
    $postEntries = collectPostSitemapEntries();
    $exploreEntries = collectExploreSitemapEntries();
    $communityEntries = collectCommunitySitemapEntries();
    $tagEntries = collectTagSitemapEntries();
    $profileEntries = collectProfileSitemapEntries();
    $newsPosts = collectNewsSitemapPosts();
    $allEntries = array_values(array_merge(
        $pageEntries,
        $categoryEntries,
        $postEntries,
        $exploreEntries,
        $communityEntries,
        $tagEntries,
        $profileEntries
    ));

    $sitemaps = [
        ['path' => 'sitemap-pages.xml', 'lastmod' => sitemapMaxLastmod($pageEntries, $indexFallbackLastmod)],
        ['path' => 'sitemap-categories.xml', 'lastmod' => sitemapMaxLastmod($categoryEntries, $indexFallbackLastmod)],
        ['path' => 'sitemap-posts.xml', 'lastmod' => sitemapMaxLastmod($postEntries, $indexFallbackLastmod)],
        ['path' => 'sitemap-explore.xml', 'lastmod' => sitemapMaxLastmod($exploreEntries, $indexFallbackLastmod)],
        ['path' => 'sitemap-community.xml', 'lastmod' => sitemapMaxLastmod($communityEntries, $indexFallbackLastmod)],
        ['path' => 'sitemap-tags.xml', 'lastmod' => sitemapMaxLastmod($tagEntries, $indexFallbackLastmod)],
        ['path' => 'sitemap-profiles.xml', 'lastmod' => sitemapMaxLastmod($profileEntries, $indexFallbackLastmod)],
        ['path' => 'sitemap-all.xml', 'lastmod' => sitemapMaxLastmod($allEntries, $indexFallbackLastmod)],
    ];

    if (!empty($newsPosts)) {
        $newsLastmod = sitemapDate(
            (string)($newsPosts[0]['published_at'] ?? $newsPosts[0]['created_at'] ?? null),
            $indexFallbackLastmod
        );
        $sitemaps[] = ['path' => 'news-sitemap.xml', 'lastmod' => $newsLastmod];
    }

    renderXml(buildSitemapIndexXml($sitemaps));
}

function renderSitemapXml(): void {
    renderSitemapIndexXml();
}

function collectNewsSitemapPosts(): array {
    $db = Database::getInstance();
    $cutoff = date('Y-m-d H:i:s', strtotime('-2 days'));

    return $db->fetchAll(
        "SELECT p.slug, p.title, p.created_at, p.published_at, p.updated_at, c.slug AS category_slug
         FROM posts p
         LEFT JOIN categories c ON p.category_id=c.id
         WHERE p.status='published'
           AND p.type IN ('news','article','breaking')
           AND COALESCE(p.location,'') <> 'explore'
           AND COALESCE(p.published_at, p.created_at) >= ?
         ORDER BY COALESCE(p.published_at, p.created_at) DESC
         LIMIT 1000",
        [$cutoff]
    );
}

function renderNewsSitemapXml(): void {
    $publicationName = 'FatakNews.in';
    $publicationLanguage = 'en';
    $posts = collectNewsSitemapPosts();

    if (empty($posts)) {
        renderText('News sitemap unavailable: no eligible news posts from the last 2 days.', 'text/plain; charset=UTF-8', 404);
    }

    $xml = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">',
    ];

    foreach ($posts as $post) {
        $loc = Helper::siteUrl(($post['category_slug'] ?: 'news') . '/' . $post['slug']);
        $publishedAt = date('c', strtotime($post['published_at'] ?: $post['created_at'] ?: 'now'));
        $updatedAt = date('c', strtotime($post['updated_at'] ?: $post['published_at'] ?: $post['created_at'] ?: 'now'));

        $xml[] = '  <url>';
        $xml[] = '    <loc>' . xmlEscape($loc) . '</loc>';
        $xml[] = '    <news:news>';
        $xml[] = '      <news:publication>';
        $xml[] = '        <news:name>' . xmlEscape($publicationName) . '</news:name>';
        $xml[] = '        <news:language>' . xmlEscape($publicationLanguage) . '</news:language>';
        $xml[] = '      </news:publication>';
        $xml[] = '      <news:publication_date>' . xmlEscape($publishedAt) . '</news:publication_date>';
        $xml[] = '      <news:title>' . xmlEscape((string)($post['title'] ?? 'Untitled story')) . '</news:title>';
        $xml[] = '    </news:news>';
        $xml[] = '    <lastmod>' . xmlEscape($updatedAt) . '</lastmod>';
        $xml[] = '  </url>';
    }

    $xml[] = '</urlset>';

    renderXml(implode("\n", $xml) . "\n");
}

$staticPages = [
    '/about' => [
        'pageTitle' => 'About FatakNews',
        'pageDesc' => 'Learn about FatakNews, its editorial focus, and how the platform covers fast, youth-first news and community stories.',
        'icon' => 'fa-bolt',
        'sections' => [
            ['title' => 'What We Cover', 'body' => "FatakNews covers breaking updates, politics, business, sports, technology, entertainment, and culture with a fast digital-first workflow."],
            ['title' => 'Editorial Approach', 'body' => "The newsroom prioritizes speed, clarity, and useful context. Each story is written for mobile readers and updated as events evolve."],
            ['title' => 'Community Layer', 'body' => "Alongside staff reporting, the platform highlights community voices, opinion posts, and issue-based local discussions."],
        ],
    ],
    '/contact' => [
        'pageTitle' => 'Contact FatakNews',
        'pageDesc' => 'Contact the FatakNews editorial, advertising, and support teams.',
        'icon' => 'fa-envelope',
        'sections' => [
            ['title' => 'Editorial Desk', 'body' => 'For story tips, corrections, and newsroom queries, email info@fataknews.in.'],
            ['title' => 'Advertising', 'body' => 'For campaigns, sponsored content, and media kits, email info@fataknews.in.'],
            ['title' => 'Support', 'body' => 'For account and platform support, email info@fataknews.in.'],
        ],
    ],
    '/privacy' => [
        'pageTitle' => 'Privacy Policy',
        'pageDesc' => 'Read the FatakNews privacy policy for information about data collection, cookies, and account usage.',
        'icon' => 'fa-user-shield',
        'sections' => [
            ['title' => 'Data We Collect', 'body' => 'FatakNews stores account details, profile settings, and on-platform activity needed to operate feeds, community features, and moderation tools.'],
            ['title' => 'Cookies and Sessions', 'body' => 'Cookies and session storage are used to keep users signed in, protect forms, and support analytics and personalization.'],
            ['title' => 'User Controls', 'body' => 'Users can update profile information, remove optional content, and contact support for account-level requests.'],
        ],
    ],
    '/terms' => [
        'pageTitle' => 'Terms of Service',
        'pageDesc' => 'Review the terms that govern use of the FatakNews website, community, and publishing tools.',
        'icon' => 'fa-file-contract',
        'sections' => [
            ['title' => 'Platform Use', 'body' => 'Users must not misuse publishing tools, impersonate others, or submit unlawful or infringing content.'],
            ['title' => 'Content Standards', 'body' => 'FatakNews may moderate, edit, unpublish, or remove submissions that violate editorial or community rules.'],
            ['title' => 'Accounts', 'body' => 'Users are responsible for their login credentials and for activity performed through their accounts.'],
        ],
    ],
    '/advertise' => [
        'pageTitle' => 'Advertise With FatakNews',
        'pageDesc' => 'Explore advertising opportunities across the FatakNews homepage, topic pages, and premium placements.',
        'icon' => 'fa-bullhorn',
        'sections' => [
            ['title' => 'Campaign Options', 'body' => 'Available inventory includes homepage placements, category modules, newsletter promos, and branded storytelling formats.'],
            ['title' => 'Audience Fit', 'body' => 'FatakNews focuses on mobile-first readers who follow fast-moving politics, business, and youth culture.'],
            ['title' => 'Reach Out', 'body' => 'For rates and custom plans, contact info@fataknews.in.'],
        ],
    ],
    '/careers' => [
        'pageTitle' => 'Careers at FatakNews',
        'pageDesc' => 'Learn about hiring opportunities at FatakNews across editorial, product, community, and operations.',
        'icon' => 'fa-briefcase',
        'sections' => [
            ['title' => 'Editorial Roles', 'body' => 'The newsroom hires reporters, editors, video producers, and community moderators across beats.'],
            ['title' => 'Product and Growth', 'body' => 'The platform also hires for audience growth, design, product operations, and technical publishing workflows.'],
            ['title' => 'Apply', 'body' => 'Send your resume and role preference to info@fataknews.in.'],
        ],
    ],
    '/press' => [
        'pageTitle' => 'Press Room',
        'pageDesc' => 'Find press information, brand references, and media contacts for FatakNews.',
        'icon' => 'fa-newspaper',
        'sections' => [
            ['title' => 'Media Inquiries', 'body' => 'For interviews, founder commentary, and media coordination, contact info@fataknews.in.'],
            ['title' => 'Brand References', 'body' => 'Please use the FatakNews name and logo in line with editorial context and attribution norms.'],
            ['title' => 'Announcements', 'body' => 'Major product, newsroom, and partnership updates will be listed here as the platform expands.'],
        ],
    ],
    '/disclaimer' => [
        'pageTitle' => 'Disclaimer',
        'pageDesc' => 'Review the FatakNews disclaimer for editorial, community, and third-party content references.',
        'icon' => 'fa-circle-info',
        'sections' => [
            ['title' => 'Editorial Content', 'body' => 'FatakNews aims for accuracy but developing stories can change. Readers should verify time-sensitive information independently when needed.'],
            ['title' => 'Community Posts', 'body' => 'User-generated posts reflect the views of their authors and may be moderated without implying endorsement.'],
            ['title' => 'External Links', 'body' => 'External links are provided for reference. FatakNews is not responsible for third-party site content or policies.'],
        ],
    ],
    '/corrections' => [
        'pageTitle' => 'Corrections Policy',
        'pageDesc' => 'See how FatakNews handles corrections, updates, and clarifications.',
        'icon' => 'fa-pen-to-square',
        'sections' => [
            ['title' => 'How To Report an Error', 'body' => 'Readers can report factual issues by contacting info@fataknews.in with the article URL and the correction detail.'],
            ['title' => 'Update Workflow', 'body' => 'Substantive corrections are reviewed by editors and updated in the relevant story as quickly as possible.'],
            ['title' => 'Transparency', 'body' => 'When a correction materially changes the article, FatakNews aims to note that an update was made.'],
        ],
    ],
];

$uri = Helper::requestPath();
$uri = rtrim($uri, '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'HEAD') {
    $method = 'GET';
}

$routes = [
    'GET' => [
        '/' => fn() => renderView(VIEW . 'pages/home.php'),
        '/robots.txt' => fn() => renderRobotsTxt(),
        '/sitemap' => fn() => renderSitemapXml(),
        '/sitemap.xml' => fn() => renderSitemapXml(),
        '/sitemap-index.xml' => fn() => renderSitemapIndexXml(),
        '/sitemap-pages.xml' => fn() => renderSectionSitemapXml('pages'),
        '/sitemap-categories.xml' => fn() => renderSectionSitemapXml('categories'),
        '/sitemap-posts.xml' => fn() => renderSectionSitemapXml('posts'),
        '/sitemap-explore.xml' => fn() => renderSectionSitemapXml('explore'),
        '/sitemap-community.xml' => fn() => renderSectionSitemapXml('community'),
        '/sitemap-tags.xml' => fn() => renderSectionSitemapXml('tags'),
        '/sitemap-profiles.xml' => fn() => renderSectionSitemapXml('profiles'),
        '/sitemap-all.xml' => fn() => renderSectionSitemapXml('all'),
        '/news-sitemap.xml' => fn() => renderNewsSitemapXml(),
        '/login' => fn() => renderView(VIEW . 'pages/login.php', ['metaRobots' => 'noindex,nofollow']),
        '/register' => fn() => renderView(VIEW . 'pages/register.php', ['metaRobots' => 'noindex,nofollow']),
        '/auth/google' => function () {
            $returnTo = trim((string)($_GET['return_to'] ?? '/login'));
            $redirect = trim((string)($_GET['redirect'] ?? '/'));
            GoogleAuth::beginAuthentication($returnTo, $redirect);
        },
        '/auth/google/callback' => fn() => GoogleAuth::handleCallback(),
        '/forgot-password' => fn() => renderView(VIEW . 'pages/forgot_password.php', ['metaRobots' => 'noindex,nofollow']),
        '/reset-password' => function () {
            $token = trim((string)($_GET['token'] ?? ''));
            $resetUser = $token !== '' ? (new UserModel())->findByResetToken($token) : null;
            renderView(VIEW . 'pages/reset_password.php', [
                'metaRobots' => 'noindex,nofollow',
                'resetToken' => $token,
                'resetUser' => $resetUser,
            ]);
        },
        '/logout' => fn() => Auth::logout(),
        '/feed' => fn() => renderView(VIEW . 'pages/feed.php'),
        '/trending' => fn() => renderView(VIEW . 'pages/trending.php'),
        '/explore' => fn() => renderView(VIEW . 'pages/explore.php'),
        '/search' => fn() => renderView(VIEW . 'pages/search.php'),
        '/community' => fn() => renderView(VIEW . 'pages/community.php'),
        '/more' => fn() => renderView(VIEW . 'pages/footer_links.php'),
        '/community/create' => fn() => renderView(VIEW . 'pages/community_create.php', ['metaRobots' => 'noindex,nofollow']),
        '/bookmarks' => fn() => renderView(VIEW . 'pages/bookmarks.php', ['metaRobots' => 'noindex,nofollow']),
        '/notifications' => fn() => renderView(VIEW . 'pages/notifications.php', ['metaRobots' => 'noindex,nofollow']),
        '/profile' => fn() => renderView(VIEW . 'pages/profile_self.php', ['metaRobots' => 'noindex,nofollow']),
        '/settings' => fn() => renderView(VIEW . 'pages/settings.php', ['metaRobots' => 'noindex,nofollow']),
        '/about' => fn() => renderView(VIEW . 'pages/static_page.php', $staticPages['/about']),
        '/contact' => fn() => renderView(VIEW . 'pages/static_page.php', $staticPages['/contact']),
        '/privacy' => fn() => renderView(VIEW . 'pages/static_page.php', $staticPages['/privacy']),
        '/terms' => fn() => renderView(VIEW . 'pages/static_page.php', $staticPages['/terms']),
        '/advertise' => fn() => renderView(VIEW . 'pages/static_page.php', $staticPages['/advertise']),
        '/careers' => fn() => renderView(VIEW . 'pages/static_page.php', $staticPages['/careers']),
        '/press' => fn() => renderView(VIEW . 'pages/static_page.php', $staticPages['/press']),
        '/disclaimer' => fn() => renderView(VIEW . 'pages/static_page.php', $staticPages['/disclaimer']),
        '/corrections' => fn() => renderView(VIEW . 'pages/static_page.php', $staticPages['/corrections']),
        '/api/notifications' => fn() => include __DIR__ . '/../api/notifications.php',
        '/api/posts' => fn() => include __DIR__ . '/../api/posts.php',
        '/api/search' => fn() => include __DIR__ . '/../api/search.php',
        '/api/categories' => fn() => include __DIR__ . '/../api/categories.php',
        '/api/csrf' => function () {
            Csrf::regenerate();
            Helper::json(['success' => true, 'token' => Csrf::token()]);
        },
        '/api/stories' => fn() => include __DIR__ . '/../api/stories.php',
        '/admin' => fn() => renderView(BASE_PATH . '/panels/admin/dashboard.php'),
        '/admin/pipeline' => fn() => renderView(BASE_PATH . '/panels/admin/pipeline.php'),
        '/admin/news' => fn() => renderView(BASE_PATH . '/panels/admin/news.php'),
        '/admin/engagement' => fn() => renderView(BASE_PATH . '/panels/admin/engagement.php'),
        '/admin/users' => fn() => renderView(BASE_PATH . '/panels/admin/users.php'),
        '/admin/categories' => fn() => renderView(BASE_PATH . '/panels/admin/categories.php'),
        '/admin/ads' => fn() => renderView(BASE_PATH . '/panels/admin/ads.php'),
        '/admin/settings' => fn() => renderView(BASE_PATH . '/panels/admin/settings.php'),
        '/admin/analytics' => fn() => renderView(BASE_PATH . '/panels/admin/analytics.php'),
        '/manager' => fn() => renderView(BASE_PATH . '/panels/manager/dashboard.php'),
        '/manager/posts' => fn() => renderView(BASE_PATH . '/panels/manager/posts.php'),
        '/manager/approve' => fn() => renderView(BASE_PATH . '/panels/manager/approve.php'),
        '/manager/reporters' => fn() => renderView(BASE_PATH . '/panels/manager/reporters.php'),
        '/employee' => fn() => renderView(BASE_PATH . '/panels/employee/dashboard.php'),
        '/employee/create' => fn() => renderView(BASE_PATH . '/panels/employee/create_post.php'),
        '/employee/my-posts' => fn() => renderView(BASE_PATH . '/panels/employee/my_posts.php'),
        '/employee/attendance' => fn() => renderView(BASE_PATH . '/panels/employee/attendance.php'),
        '/employee/leaves' => fn() => renderView(BASE_PATH . '/panels/employee/leaves.php'),
        '/hr' => fn() => renderView(BASE_PATH . '/panels/hr/dashboard.php'),
        '/hr/employees' => fn() => renderView(BASE_PATH . '/panels/hr/employees.php'),
        '/hr/leaves' => fn() => renderView(BASE_PATH . '/panels/hr/leaves.php'),
        '/hr/payroll' => fn() => renderView(BASE_PATH . '/panels/hr/payroll.php'),
        '/hr/attendance' => fn() => renderView(BASE_PATH . '/panels/hr/attendance.php'),
        '/hr/departments' => fn() => renderView(BASE_PATH . '/panels/hr/departments.php'),
    ],
    'POST' => [
        '/api/auth/login' => fn() => include __DIR__ . '/../api/auth.php',
        '/api/auth/register' => fn() => include __DIR__ . '/../api/auth.php',
        '/api/auth/forgot-password' => fn() => include __DIR__ . '/../api/auth.php',
        '/api/auth/reset-password' => fn() => include __DIR__ . '/../api/auth.php',
        '/api/posts' => fn() => include __DIR__ . '/../api/posts.php',
        '/api/posts/react' => fn() => include __DIR__ . '/../api/posts.php',
        '/api/posts/bookmark' => fn() => include __DIR__ . '/../api/posts.php',
        '/api/comments' => fn() => include __DIR__ . '/../api/comments.php',
        '/api/follow' => fn() => include __DIR__ . '/../api/follow.php',
        '/api/account/profile' => fn() => include __DIR__ . '/../api/account/profile.php',
        '/api/notifications/read' => fn() => include __DIR__ . '/../api/notifications.php',
        '/api/upload' => fn() => include __DIR__ . '/../api/upload.php',
        '/api/stories' => fn() => include __DIR__ . '/../api/stories.php',
        '/api/ai/generate' => fn() => include __DIR__ . '/../api/ai/generate.php',
        '/api/newsletter' => fn() => include __DIR__ . '/../api/newsletter.php',
        '/api/admin/posts' => fn() => include __DIR__ . '/../api/admin/posts.php',
        '/api/admin/pipeline' => fn() => include __DIR__ . '/../api/admin/pipeline.php',
        '/api/admin/users' => fn() => include __DIR__ . '/../api/admin/users.php',
        '/api/admin/categories' => fn() => include __DIR__ . '/../api/admin/categories.php',
        '/api/admin/ads' => fn() => include __DIR__ . '/../api/admin/ads.php',
        '/api/admin/settings' => fn() => include __DIR__ . '/../api/admin/settings.php',
        '/api/hr/leaves' => fn() => include __DIR__ . '/../api/hr/leaves.php',
        '/api/hr/attendance' => fn() => include __DIR__ . '/../api/hr/attendance.php',
        '/api/hr/employees' => fn() => include __DIR__ . '/../api/hr/employees.php',
        '/api/hr/departments' => fn() => include __DIR__ . '/../api/hr/departments.php',
        '/api/hr/payroll' => fn() => include __DIR__ . '/../api/hr/payroll.php',
    ],
];

function matchDynamic(string $uri): ?array {
    $patterns = [
        '#^/category/([a-z0-9-]+)$#' => fn($m) => ['type' => 'category', 'slug' => $m[1]],
        '#^/tag/([a-z0-9-]+)$#' => fn($m) => ['type' => 'tag', 'slug' => $m[1]],
        '#^/@([a-zA-Z0-9_]+)$#' => fn($m) => ['type' => 'profile', 'username' => $m[1]],
        '#^/([a-z0-9-]+)/([a-z0-9-]+)$#' => fn($m) => ['type' => 'post', 'cat' => $m[1], 'slug' => $m[2]],
    ];

    foreach ($patterns as $pattern => $handler) {
        if (preg_match($pattern, $uri, $m)) {
            return $handler($m);
        }
    }

    return null;
}

if (isset($routes[$method][$uri])) {
    ($routes[$method][$uri])();
} elseif ($dynamic = matchDynamic($uri)) {
    switch ($dynamic['type']) {
        case 'post':
            renderView(VIEW . 'pages/single_post.php', ['dynamic' => $dynamic]);
            break;
        case 'category':
            renderView(VIEW . 'pages/category.php', ['dynamic' => $dynamic]);
            break;
        case 'profile':
            renderView(VIEW . 'pages/profile.php', ['dynamic' => $dynamic]);
            break;
        case 'tag':
            renderView(VIEW . 'pages/tag.php', ['dynamic' => $dynamic]);
            break;
        default:
            renderView(VIEW . 'pages/404.php', [], 404);
            break;
    }
} else {
    renderView(VIEW . 'pages/404.php', [], 404);
}
