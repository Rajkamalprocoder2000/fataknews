<?php
// app/helpers/Helper.php

class Helper {
    public static function requestScheme(): string {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        if (APP_TRUST_PROXY_HEADERS && self::isTrustedProxy((string)($_SERVER['REMOTE_ADDR'] ?? ''))) {
            $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
            if (in_array($forwardedProto, ['http', 'https'], true)) {
                $scheme = $forwardedProto;
            }
        }

        return $scheme;
    }

    public static function requestHost(): string {
        $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
        if ($host === '') {
            return '';
        }

        if (!preg_match('/^[a-z0-9.-]+(?::\d{1,5})?$/', $host)) {
            return '';
        }

        return $host;
    }

    public static function trustedHostName(string $host): string {
        $host = strtolower(trim($host));
        if ($host === '') {
            return '';
        }

        return explode(':', $host, 2)[0] ?? '';
    }

    public static function isTrustedHost(?string $host = null): bool {
        $host = self::trustedHostName($host ?? self::requestHost());
        if ($host === '') {
            return false;
        }

        $allowedHosts = is_array(APP_ALLOWED_HOSTS) ? APP_ALLOWED_HOSTS : [];
        if (empty($allowedHosts)) {
            return APP_ENV !== 'production';
        }

        return in_array($host, array_map([self::class, 'trustedHostName'], $allowedHosts), true);
    }

    public static function ensureTrustedHost(): void {
        $host = self::requestHost();
        if ($host === '') {
            return;
        }

        if (!self::isTrustedHost($host)) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Untrusted Host header.';
            exit;
        }
    }

    public static function ensureHttps(): void {
        if (!APP_FORCE_HTTPS || self::requestScheme() === 'https') {
            return;
        }

        $host = self::requestHost();
        if ($host === '' || !self::isTrustedHost($host)) {
            return;
        }

        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? self::path('/'));
        header('Location: https://' . $host . $requestUri, true, 301);
        exit;
    }

    public static function basePath(): string {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptName === '') {
            return '';
        }

        $base = str_replace('\\', '/', dirname(dirname($scriptName)));
        $base = rtrim($base, '/.');

        return $base === '' || $base === '/' ? '' : $base;
    }

    public static function path(string $path = ''): string {
        $base = self::basePath();
        $path = ltrim($path, '/');

        if ($path === '') {
            return $base !== '' ? $base . '/' : '/';
        }

        return ($base !== '' ? $base : '') . '/' . $path;
    }

    public static function appUrl(): string {
        $configured = rtrim(trim((string)APP_URL), '/');
        if ($configured !== '') {
            return $configured;
        }

        $host = self::requestHost();
        if ($host !== '' && self::isTrustedHost($host)) {
            return self::requestScheme() . '://' . $host . self::basePath();
        }

        return '';
    }

    public static function siteUrl(string $path = ''): string {
        $base = rtrim(self::appUrl(), '/');
        $path = ltrim($path, '/');

        if ($base === '') {
            return self::path($path);
        }

        return $base . ($path !== '' ? '/' . $path : '');
    }

    public static function publicUrl(string $path = ''): string {
        $path = ltrim($path, '/');
        $base = rtrim(self::appUrl(), '/');

        if ($base === '') {
            return self::path('public' . ($path !== '' ? '/' . $path : ''));
        }

        return $base . '/public' . ($path !== '' ? '/' . $path : '');
    }

    public static function sitePublicUrl(string $path = ''): string {
        $path = ltrim($path, '/');
        return self::siteUrl('public' . ($path !== '' ? '/' . $path : ''));
    }

    public static function requestPath(): string {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = self::basePath();

        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }

        return $path === '' ? '/' : $path;
    }

    public static function inlinePlaceholder(string $label, string $bg = '#1E1E2A', string $fg = '#F0F0F5'): string {
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="675" viewBox="0 0 1200 675">
  <rect width="1200" height="675" fill="{$bg}"/>
  <circle cx="600" cy="260" r="72" fill="{$fg}" opacity="0.12"/>
  <text x="600" y="365" text-anchor="middle" font-family="Arial, sans-serif" font-size="48" font-weight="700" fill="{$fg}">{$label}</text>
</svg>
SVG;

        return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
    }

    public static function slug(string $text): string {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        return trim($text, '-');
    }

    public static function excerpt(string $html, int $len = 160): string {
        $text = strip_tags($html);
        return mb_strlen($text) > $len ? mb_substr($text, 0, $len) . '...' : $text;
    }

    public static function plainText(?string $html): string {
        $text = preg_replace('/\s+/', ' ', strip_tags((string)$html)) ?? '';
        return trim($text);
    }

    public static function normalizedExcerpt(?string $html, int $len = 180): string {
        $text = self::plainText($html);
        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= $len) {
            return $text;
        }

        $slice = rtrim(mb_substr($text, 0, $len));
        $lastSpace = mb_strrpos($slice, ' ');
        if ($lastSpace !== false && $lastSpace > (int)($len * 0.6)) {
            $slice = rtrim(mb_substr($slice, 0, $lastSpace));
        }

        return $slice . '...';
    }

    public static function imageAlt(?string $customAlt, ?string $fallback = null, string $default = 'FatakNews image'): string {
        $customAlt = trim(strip_tags((string)$customAlt));
        if ($customAlt !== '') {
            return $customAlt;
        }

        $fallback = trim(strip_tags((string)$fallback));
        if ($fallback !== '') {
            return $fallback;
        }

        return $default;
    }

    public static function metaDescription(?string $text, int $len = 155, string $fallback = 'FatakNews brings breaking news, latest headlines, live updates, and trending stories from India across politics, business, sports, technology, and entertainment.'): string {
        $text = trim((string)$text);
        if ($text === '') {
            return $fallback;
        }

        $text = preg_replace('/\s+/', ' ', strip_tags($text)) ?? '';
        if ($text === '') {
            return $fallback;
        }

        return mb_strlen($text) > $len ? rtrim(mb_substr($text, 0, $len - 1)) . '...' : $text;
    }

    public static function defaultImageAlt(?string $title, ?string $categoryName = null): string {
        $title = self::plainText($title);
        $categoryName = self::plainText($categoryName);

        if ($title !== '' && $categoryName !== '') {
            return $title . ' - ' . $categoryName . ' image';
        }

        if ($title !== '') {
            return $title . ' image';
        }

        if ($categoryName !== '') {
            return $categoryName . ' news image';
        }

        return 'FatakNews image';
    }

    public static function defaultSeoTitle(?string $title, ?string $categoryName = null, int $len = 65): string {
        $title = self::plainText($title);
        $categoryName = self::plainText($categoryName);

        if ($title === '') {
            return 'FatakNews';
        }

        $base = $title;
        if ($categoryName !== '' && stripos($title, $categoryName) === false && mb_strlen($title) <= 48) {
            $base .= ' | ' . $categoryName . ' News';
        }

        if (stripos($base, 'FatakNews') === false) {
            $base .= ' | FatakNews';
        }

        return mb_strlen($base) > $len ? rtrim(mb_substr($base, 0, $len - 1)) . '...' : $base;
    }

    public static function defaultSeoDescription(?string $excerpt, ?string $content = null, ?string $categoryName = null): string {
        $excerpt = self::plainText($excerpt);
        $content = self::plainText($content);
        $categoryName = self::plainText($categoryName);
        $source = $excerpt !== '' ? $excerpt : $content;

        if ($source === '') {
            $source = $categoryName !== '' ? ('Latest ' . $categoryName . ' news and updates on FatakNews.') : '';
        }

        if ($categoryName !== '' && stripos($source, $categoryName) === false) {
            $source .= ' Read more ' . $categoryName . ' updates on FatakNews.';
        } elseif (stripos($source, 'FatakNews') === false) {
            $source .= ' Read the full story on FatakNews.';
        }

        return self::metaDescription($source);
    }

    public static function seoKeywordsFromPost(?string $title, ?string $categoryName = null, array $tags = []): string {
        $parts = [];

        foreach (array_merge([$categoryName, $title], $tags, ['FatakNews']) as $part) {
            $part = self::plainText((string)$part);
            if ($part === '') {
                continue;
            }

            $parts[] = $part;
        }

        return implode(', ', array_values(array_unique($parts)));
    }

    public static function siteLogoUrl(): string {
        return self::sitePublicUrl('assets/images/fataknew_logo.webp');
    }

    public static function organizationSchema(): array {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsMediaOrganization',
            'name' => 'FatakNews.in',
            'url' => self::siteUrl(),
            'description' => 'FatakNews brings breaking news, latest headlines, live updates, and trending stories from India across politics, business, sports, technology, and entertainment.',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => self::siteLogoUrl(),
            ],
            'image' => self::siteLogoUrl(),
            'areaServed' => 'IN',
            'knowsAbout' => ['Breaking News', 'Politics', 'Business', 'Sports', 'Technology', 'Entertainment', 'Culture'],
            'publishingPrinciples' => self::siteUrl('about'),
            'correctionsPolicy' => self::siteUrl('corrections'),
            'ethicsPolicy' => self::siteUrl('terms'),
            'contactPoint' => [
                [
                    '@type' => 'ContactPoint',
                    'contactType' => 'newsroom',
                    'email' => 'info@fataknews.in',
                    'url' => self::siteUrl('contact'),
                ],
                [
                    '@type' => 'ContactPoint',
                    'contactType' => 'customer support',
                    'email' => 'info@fataknews.in',
                    'url' => self::siteUrl('contact'),
                ],
            ],
        ];

        return $schema;
    }

    public static function publisherSchema(): array {
        return [
            '@type' => 'NewsMediaOrganization',
            'name' => 'FatakNews.in',
            'url' => self::siteUrl(),
            'description' => 'FatakNews brings breaking news, latest headlines, live updates, and trending stories from India across politics, business, sports, technology, and entertainment.',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => self::siteLogoUrl(),
            ],
            'image' => self::siteLogoUrl(),
            'areaServed' => 'IN',
            'publishingPrinciples' => self::siteUrl('about'),
            'correctionsPolicy' => self::siteUrl('corrections'),
            'ethicsPolicy' => self::siteUrl('terms'),
        ];
    }

    public static function authorSchema(array $author): array {
        $name = trim((string)($author['full_name'] ?? $author['name'] ?? 'FatakNews Desk'));
        $username = trim((string)($author['username'] ?? ''));
        $profileUrl = $username !== '' ? self::siteUrl('@' . $username) : self::siteUrl();
        $avatar = self::avatarAssetUrl($author['avatar'] ?? null);
        $bio = trim((string)($author['bio'] ?? ''));
        $location = trim((string)($author['author_location'] ?? $author['location'] ?? ''));
        $website = trim((string)($author['website'] ?? ''));
        $roleName = trim((string)($author['role_name'] ?? ''));

        $schema = [
            '@type' => 'Person',
            'name' => $name !== '' ? $name : 'FatakNews Desk',
            'url' => $profileUrl,
            'worksFor' => [
                '@type' => 'NewsMediaOrganization',
                'name' => 'FatakNews.in',
                'url' => self::siteUrl(),
            ],
        ];

        if ($avatar !== null) {
            $schema['image'] = $avatar;
        }

        if ($bio !== '') {
            $schema['description'] = $bio;
        }

        if ($location !== '') {
            $schema['homeLocation'] = [
                '@type' => 'Place',
                'name' => $location,
            ];
        }

        if ($roleName !== '') {
            $schema['jobTitle'] = $roleName;
        }

        if ($website !== '' && filter_var($website, FILTER_VALIDATE_URL)) {
            $schema['sameAs'] = [$website];
        }

        return $schema;
    }

    public static function breadcrumbSchema(array $items): array {
        $list = [];
        $position = 1;

        foreach ($items as $item) {
            $name = trim((string)($item['name'] ?? ''));
            $url = trim((string)($item['url'] ?? ''));
            if ($name === '' || $url === '') {
                continue;
            }

            $list[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $name,
                'item' => $url,
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $list,
        ];
    }

    public static function breadcrumbNav(array $items, string $className = 'site-breadcrumb'): string {
        $crumbs = [];

        foreach ($items as $item) {
            $name = trim((string)($item['name'] ?? ''));
            $url = trim((string)($item['url'] ?? ''));
            if ($name === '') {
                continue;
            }

            $crumbs[] = [
                'name' => $name,
                'url' => $url,
            ];
        }

        if (empty($crumbs)) {
            return '';
        }

        $html = '<nav class="' . self::sanitize($className) . '" aria-label="Breadcrumb"><ol>';
        $lastIndex = count($crumbs) - 1;

        foreach ($crumbs as $index => $crumb) {
            $html .= '<li>';
            if ($crumb['url'] !== '' && $index !== $lastIndex) {
                $html .= '<a href="' . self::sanitize($crumb['url']) . '">' . self::sanitize($crumb['name']) . '</a>';
            } else {
                $html .= '<span aria-current="page">' . self::sanitize($crumb['name']) . '</span>';
            }

            if ($index !== $lastIndex) {
                $html .= '<span class="site-breadcrumb-sep" aria-hidden="true"><i class="fa fa-angle-right"></i></span>';
            }

            $html .= '</li>';
        }

        $html .= '</ol></nav>';

        return $html;
    }

    public static function profilePageSchema(array $user, string $pageTitle, string $pageDesc, string $canonicalUrl): array {
        $person = self::authorSchema($user);
        $username = trim((string)($user['username'] ?? ''));
        $followers = max(0, (int)($user['followers_count'] ?? 0));
        $posts = max(0, (int)($user['posts_count'] ?? 0));

        if ($username !== '') {
            $person['alternateName'] = '@' . $username;
        }

        if (!empty($user['id'])) {
            $person['identifier'] = (string)$user['id'];
        }

        $interactionStats = [];
        if ($followers > 0) {
            $interactionStats[] = [
                '@type' => 'InteractionCounter',
                'interactionType' => ['@type' => 'FollowAction'],
                'userInteractionCount' => $followers,
            ];
        }
        if ($posts > 0) {
            $interactionStats[] = [
                '@type' => 'InteractionCounter',
                'interactionType' => ['@type' => 'WriteAction'],
                'userInteractionCount' => $posts,
            ];
        }
        if (!empty($interactionStats)) {
            $person['interactionStatistic'] = $interactionStats;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ProfilePage',
            'name' => $pageTitle,
            'description' => $pageDesc,
            'url' => $canonicalUrl,
            'mainEntity' => $person,
        ];
    }

    public static function collectionItemListSchema(array $items, string $canonicalUrl): array {
        $listItems = [];
        $position = 1;

        foreach ($items as $item) {
            $slug = trim((string)($item['slug'] ?? ''));
            $title = trim((string)($item['title'] ?? ''));
            if ($slug === '' || $title === '') {
                continue;
            }

            $categorySlug = trim((string)($item['category_slug'] ?? ''));
            $type = trim((string)($item['type'] ?? ''));
            $baseSlug = $categorySlug !== ''
                ? $categorySlug
                : (in_array($type, ['community_post', 'thought'], true) ? 'community' : 'news');

            $listItems[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'url' => self::siteUrl($baseSlug . '/' . $slug),
                'name' => $title,
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'url' => $canonicalUrl,
            'itemListElement' => $listItems,
        ];
    }

    public static function profileIndexableReport(array $user): array {
        $roleSlug = trim((string)($user['role_slug'] ?? ''));
        $posts = max(0, (int)($user['posts_count'] ?? 0));
        $followers = max(0, (int)($user['followers_count'] ?? 0));
        $isVerified = !empty($user['is_verified']);
        $hasBio = trim((string)($user['bio'] ?? '')) !== '';
        $website = trim((string)($user['website'] ?? ''));
        $hasWebsite = $website !== '' && filter_var($website, FILTER_VALIDATE_URL);
        $editorialRoles = ['super_admin', 'admin', 'manager', 'editor', 'reporter'];

        if ($isVerified || in_array($roleSlug, $editorialRoles, true)) {
            return [
                'indexable' => true,
                'reasons' => ['Verified or editorial staff profile.'],
            ];
        }

        if ($posts >= 5) {
            return [
                'indexable' => true,
                'reasons' => ['Profile has enough published posts to stand alone in search.'],
            ];
        }

        if ($posts >= 3 && ($followers >= 10 || $hasBio || $hasWebsite)) {
            return [
                'indexable' => true,
                'reasons' => ['Profile has content depth plus public identity signals.'],
            ];
        }

        return [
            'indexable' => false,
            'reasons' => ['Thin profile page with limited public value for search.'],
        ];
    }

    public static function tagQualityReport(array $tag): array {
        $name = strtolower(trim((string)($tag['name'] ?? '')));
        $slug = strtolower(trim((string)($tag['slug'] ?? '')));
        $postsCount = max(0, (int)($tag['posts_count'] ?? $tag['total_posts'] ?? 0));
        $normalized = preg_replace('/[^a-z0-9]+/', '', $slug !== '' ? $slug : $name) ?? '';
        $tagKeys = array_values(array_unique(array_filter([
            self::normalizeTagRuleValue($slug),
            self::normalizeTagRuleValue($name),
        ])));
        $whitelist = array_map([self::class, 'normalizeTagRuleValue'], is_array(TAG_INDEX_WHITELIST) ? TAG_INDEX_WHITELIST : []);
        $blacklist = array_map([self::class, 'normalizeTagRuleValue'], is_array(TAG_NOINDEX_BLACKLIST) ? TAG_NOINDEX_BLACKLIST : []);
        $genericTags = [
            'news', 'new', 'article', 'articles', 'post', 'posts', 'tag', 'tags',
            'update', 'updates', 'latest', 'breaking', 'story', 'stories',
            'general', 'misc', 'other', 'uncategorized', 'test', 'testing',
            'temp', 'sample',
        ];
        $reasons = [];

        foreach ($tagKeys as $tagKey) {
            if ($tagKey !== '' && in_array($tagKey, $blacklist, true)) {
                return [
                    'indexable' => false,
                    'posts_count' => $postsCount,
                    'reasons' => ['This tag is forced to noindex by manual blacklist.'],
                ];
            }
        }

        foreach ($tagKeys as $tagKey) {
            if ($tagKey !== '' && in_array($tagKey, $whitelist, true)) {
                return [
                    'indexable' => true,
                    'posts_count' => $postsCount,
                    'reasons' => ['This tag is manually approved for indexing.'],
                ];
            }
        }

        if ($postsCount < 2) {
            $reasons[] = 'This tag has fewer than 2 published stories.';
        }

        if (mb_strlen($name) > 0 && mb_strlen($name) < 3) {
            $reasons[] = 'This tag name is too short to be a strong search landing page.';
        }

        if ($normalized !== '' && strlen($normalized) < 3) {
            $reasons[] = 'This tag slug is too short to signal a clear topic.';
        }

        if ($normalized !== '' && in_array($normalized, $genericTags, true)) {
            $reasons[] = 'This tag is too generic and overlaps with broader archive pages.';
        }

        if ($normalized !== '' && preg_match('/^\d+$/', $normalized)) {
            $reasons[] = 'Numeric-only tags are low-signal for search.';
        }

        return [
            'indexable' => empty($reasons),
            'posts_count' => $postsCount,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    private static function normalizeTagRuleValue(string $value): string {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        return trim(preg_replace('/[^a-z0-9]+/', '-', $value) ?? '', '-');
    }

    public static function readingTime(string $content): int {
        $words = str_word_count(strip_tags($content));
        return max(1, (int)ceil($words / 200));
    }

    public static function timeAgo($datetime): string {
        $dt = is_string($datetime) ? new \DateTime($datetime) : $datetime;

        if (!$dt instanceof \DateTimeInterface) {
            return 'Just now';
        }

        $diff = (new \DateTime())->diff($dt);
        if ($diff->y) return $diff->y . 'y ago';
        if ($diff->m) return $diff->m . 'mo ago';
        if ($diff->d) return $diff->d . 'd ago';
        if ($diff->h) return $diff->h . 'h ago';
        if ($diff->i) return $diff->i . 'm ago';
        return 'Just now';
    }

    public static function formatNumber(int $n): string {
        if ($n >= 1_000_000) return round($n / 1_000_000, 1) . 'M';
        if ($n >= 1_000)     return round($n / 1_000, 1) . 'K';
        return (string)$n;
    }

    public static function sanitize($input): string {
        if ($input === null) {
            return '';
        }

        if (is_bool($input)) {
            $input = $input ? '1' : '0';
        } elseif (is_scalar($input) || $input instanceof Stringable) {
            $input = (string)$input;
        } else {
            return '';
        }

        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    public static function analyticsHeadHtml(): string {
        $parts = [];
        $gtmId = self::googleTagManagerId();
        if ($gtmId !== '') {
            $parts[] = '<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({\'gtm.start\':new Date().getTime(),event:\'gtm.js\'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!==\'dataLayer\'?\'&l=\'+l:\'\';j.async=true;j.src=\'https://www.googletagmanager.com/gtm.js?id=\'+i+dl;f.parentNode.insertBefore(j,f);})(window,document,\'script\',\'dataLayer\',\'' . self::sanitize($gtmId) . '\');</script>';
        }

        $gaId = self::googleAnalyticsMeasurementId();
        if ($gaId !== '' && $gtmId === '') {
            $safeGaId = self::sanitize($gaId);
            $parts[] = '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $safeGaId . '"></script>';
            $parts[] = "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js', new Date());gtag('config', '" . $safeGaId . "');</script>";
        }

        return implode("\n", $parts);
    }

    public static function analyticsBodyOpenHtml(): string {
        $gtmId = self::googleTagManagerId();
        if ($gtmId === '') {
            return '';
        }

        return '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . self::sanitize($gtmId) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
    }

    private static function googleTagManagerId(): string {
        $id = strtoupper(trim((string)GTM_CONTAINER_ID));
        return preg_match('/^GTM-[A-Z0-9]+$/', $id) ? $id : '';
    }

    private static function googleAnalyticsMeasurementId(): string {
        $id = strtoupper(trim((string)GA_MEASUREMENT_ID));
        return preg_match('/^G-[A-Z0-9]+$/', $id) ? $id : '';
    }

    public static function safeLocalPath(string $path, string $fallback = '/'): string {
        $path = trim($path);
        if ($path === '') {
            return $fallback;
        }

        if (preg_match('#^https?://#i', $path)) {
            $targetHost = self::trustedHostName((string)(parse_url($path, PHP_URL_HOST) ?? ''));
            if ($targetHost === '' || !self::isTrustedHost($targetHost)) {
                return $fallback;
            }

            $parsedPath = (string)(parse_url($path, PHP_URL_PATH) ?? '/');
            $query = (string)(parse_url($path, PHP_URL_QUERY) ?? '');
            $fragment = (string)(parse_url($path, PHP_URL_FRAGMENT) ?? '');
            $path = $parsedPath
                . ($query !== '' ? '?' . $query : '')
                . ($fragment !== '' ? '#' . $fragment : '');
        }

        if (!str_starts_with($path, '/') || preg_match('#^//+#', $path)) {
            return $fallback;
        }

        $base = self::basePath();
        if ($base !== '' && str_starts_with($path, $base . '/')) {
            $path = substr($path, strlen($base)) ?: '/';
        } elseif ($base !== '' && $path === $base) {
            $path = '/';
        }

        return preg_match('#^//+#', $path) ? $fallback : $path;
    }

    public static function redirect(string $url): void {
        $url = trim($url);
        if ($url === '') {
            $url = '/';
        }

        if (str_starts_with($url, '/')) {
            $url = self::path(self::safeLocalPath($url));
        } elseif (preg_match('#^https?://#i', $url)) {
            $targetHost = self::trustedHostName((string)(parse_url($url, PHP_URL_HOST) ?? ''));
            if ($targetHost === '' || !self::isTrustedHost($targetHost)) {
                $url = self::path('/');
            }
        } else {
            $url = self::path('/');
        }

        header("Location: $url");
        exit;
    }

    public static function json($data, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data);
        exit;
    }

    public static function cacheRemember(string $key, int $ttl, callable $callback) {
        $ttl = max(1, $ttl);
        $cacheDir = BASE_PATH . '/tmp/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        $cacheFile = $cacheDir . '/' . sha1($key) . '.cache';
        if (is_file($cacheFile) && (time() - (int)@filemtime($cacheFile)) < $ttl) {
            $cached = @file_get_contents($cacheFile);
            if ($cached !== false) {
                $value = @unserialize($cached, ['allowed_classes' => false]);
                if ($value !== false || $cached === 'b:0;') {
                    return $value;
                }
            }
        }

        $value = $callback();
        @file_put_contents($cacheFile, serialize($value), LOCK_EX);

        return $value;
    }

    public static function isMobileClient(): bool {
        $userAgent = strtolower(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')));
        if ($userAgent === '') {
            return false;
        }

        return preg_match('/android.+mobile|iphone|ipod|blackberry|opera mini|windows phone|iemobile|webos|mobile safari|silk-accelerated|ucbrowser/i', $userAgent) === 1;
    }

    public static function uniqueSlug(string $table, string $baseSlug, string $field = 'slug'): string {
        $db   = Database::getInstance();
        $slug = $baseSlug;
        $i    = 1;
        while ($db->fetchOne("SELECT id FROM `$table` WHERE `$field`=?", [$slug])) {
            $slug = "$baseSlug-" . $i++;
        }
        return $slug;
    }

    public static function avatarUrl(?string $avatar): string {
        $avatar = trim((string)$avatar);
        $file = PUBLIC_PATH . '/uploads/avatars/' . $avatar;

        if ($avatar !== '' && is_file($file) && basename($avatar) !== '.gitkeep') {
            return self::publicUrl('uploads/avatars/' . rawurlencode($avatar));
        }

        return self::inlinePlaceholder('FN', '#AA00FF', '#F0F0F5');
    }

    public static function avatarAssetUrl(?string $avatar): ?string {
        $avatar = trim((string)$avatar);
        $file = PUBLIC_PATH . '/uploads/avatars/' . $avatar;

        if ($avatar !== '' && is_file($file) && basename($avatar) !== '.gitkeep') {
            return self::sitePublicUrl('uploads/avatars/' . rawurlencode($avatar));
        }

        return null;
    }

    public static function thumbnailUrl(?string $thumb): string {
        $thumb = trim((string)$thumb);
        $file = PUBLIC_PATH . '/uploads/thumbnails/' . $thumb;

        if ($thumb !== '' && is_file($file) && basename($thumb) !== '.gitkeep') {
            return self::publicUrl('uploads/thumbnails/' . rawurlencode($thumb));
        }

        return self::inlinePlaceholder('FatakNews');
    }

    public static function thumbnailAssetUrl(?string $thumb): ?string {
        $thumb = trim((string)$thumb);
        $file = PUBLIC_PATH . '/uploads/thumbnails/' . $thumb;

        if ($thumb !== '' && is_file($file) && basename($thumb) !== '.gitkeep') {
            return self::sitePublicUrl('uploads/thumbnails/' . rawurlencode($thumb));
        }

        return null;
    }

    public static function firstImageFromHtml(?string $html): ?string {
        $html = trim((string)$html);
        if ($html === '') {
            return null;
        }

        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches) !== 1) {
            return null;
        }

        $src = trim((string)($matches[1] ?? ''));
        return $src !== '' ? $src : null;
    }

    public static function prepareArticleHtml(?string $html, ?string $fallbackAlt = null): string {
        $html = trim((string)$html);
        if ($html === '' || !class_exists('DOMDocument')) {
            return $html;
        }

        $previousState = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="article-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        if (!$loaded) {
            return $html;
        }

        $root = $document->getElementById('article-root');
        if (!$root) {
            return $html;
        }

        $xpath = new DOMXPath($document);
        foreach ($xpath->query('.//script|.//style|.//object|.//embed', $root) ?: [] as $node) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }

        foreach ($xpath->query('.//*[@*]', $root) ?: [] as $node) {
            if (!$node instanceof DOMElement || !$node->hasAttributes()) {
                continue;
            }

            $removeAttributes = [];
            foreach ($node->attributes as $attribute) {
                $attributeName = strtolower($attribute->name);
                $attributeValue = self::normalizeHtmlUrl($attribute->value);

                if (str_starts_with($attributeName, 'on')) {
                    $removeAttributes[] = $attribute->name;
                    continue;
                }

                if (in_array($attributeName, ['src', 'href', 'poster'], true) && !self::isSafeHtmlUrl($attributeValue)) {
                    $removeAttributes[] = $attribute->name;
                }
            }

            foreach ($removeAttributes as $attributeName) {
                $node->removeAttribute($attributeName);
            }
        }

        foreach ($xpath->query('.//iframe', $root) ?: [] as $iframe) {
            if (!$iframe instanceof DOMElement) {
                continue;
            }

            $src = self::normalizeHtmlUrl($iframe->getAttribute('src'));
            if ($src === '' || !self::isSafeHtmlUrl($src, false)) {
                if ($iframe->parentNode) {
                    $iframe->parentNode->removeChild($iframe);
                }
                continue;
            }

            $iframe->setAttribute('src', $src);
            $iframe->setAttribute('loading', 'lazy');
            $iframe->setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        }

        foreach ($xpath->query('.//img', $root) ?: [] as $image) {
            if (!$image instanceof DOMElement) {
                continue;
            }

            $src = self::normalizeHtmlUrl($image->getAttribute('src'));
            if ($src === '' || !self::isSafeHtmlUrl($src)) {
                if ($image->parentNode) {
                    $image->parentNode->removeChild($image);
                }
                continue;
            }

            $image->setAttribute('src', $src);
            $image->setAttribute('loading', 'lazy');
            $image->setAttribute('decoding', 'async');

            $alt = self::imageAlt($image->getAttribute('alt'), $fallbackAlt, 'FatakNews article image');
            $image->setAttribute('alt', $alt);

            if (!$image->hasAttribute('width') || !$image->hasAttribute('height')) {
                $dimensions = self::imageDimensionsFromUrl($src);
                if (!empty($dimensions['width']) && !empty($dimensions['height'])) {
                    if (!$image->hasAttribute('width')) {
                        $image->setAttribute('width', (string)$dimensions['width']);
                    }
                    if (!$image->hasAttribute('height')) {
                        $image->setAttribute('height', (string)$dimensions['height']);
                    }
                }
            }
        }

        $output = '';
        foreach ($root->childNodes as $childNode) {
            $output .= $document->saveHTML($childNode);
        }

        return trim($output) !== '' ? $output : $html;
    }

    public static function youtubeEmbedUrl(?string $url): ?string {
        $url = trim((string)$url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $path = trim((string)($parts['path'] ?? ''), '/');
        parse_str((string)($parts['query'] ?? ''), $query);

        $videoId = '';
        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            $videoId = explode('/', $path)[0] ?? '';
        } elseif (str_contains($host, 'youtube.com')) {
            if ($path === 'watch') {
                $videoId = (string)($query['v'] ?? '');
            } elseif (preg_match('#^(embed|shorts)/([^/?]+)#', $path, $matches)) {
                $videoId = $matches[2] ?? '';
            }
        }

        $videoId = preg_replace('/[^a-zA-Z0-9_-]/', '', $videoId ?? '');
        if ($videoId === '') {
            return null;
        }

        return 'https://www.youtube.com/embed/' . $videoId;
    }

    public static function socialPlatformLabel(?string $url): string {
        $url = trim((string)$url);
        if ($url === '') {
            return 'Social';
        }

        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        if (str_contains($host, 'instagram.com')) {
            return 'Instagram';
        }
        if (str_contains($host, 'threads.net')) {
            return 'Threads';
        }
        if (str_contains($host, 'twitter.com') || str_contains($host, 'x.com')) {
            return 'X';
        }
        if (str_contains($host, 'tiktok.com')) {
            return 'TikTok';
        }
        if (str_contains($host, 'facebook.com') || str_contains($host, 'fb.watch')) {
            return 'Facebook';
        }

        return 'Social';
    }

    public static function socialEmbedHtml(?string $url): string {
        $url = trim((string)$url);
        if ($url === '') {
            return '';
        }

        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        $embedUrl = $url;

        if (str_contains($host, 'twitter.com') || str_contains($host, 'x.com')) {
            $path = trim((string)(parse_url($url, PHP_URL_PATH) ?? ''), '/');
            if (preg_match('#^([^/]+)/status/([0-9]+)$#', $path, $matches)) {
                $embedUrl = 'https://twitter.com/' . rawurlencode($matches[1]) . '/status/' . $matches[2];
            } else {
                $embedUrl = preg_replace('#^https?://(www\.)?x\.com#i', 'https://twitter.com', $url);
                $embedUrl = preg_replace('/\?.*$/', '', (string)$embedUrl);
            }
        }

        $safeUrl = self::sanitize($embedUrl);

        if (str_contains($host, 'instagram.com')) {
            return '<blockquote class="instagram-media" data-instgrm-permalink="' . $safeUrl . '" data-instgrm-version="14"></blockquote>';
        }

        if (str_contains($host, 'twitter.com') || str_contains($host, 'x.com')) {
            return '<blockquote class="twitter-tweet" data-dnt="true"><a href="' . $safeUrl . '">View post</a></blockquote>';
        }

        if (str_contains($host, 'tiktok.com')) {
            return '<blockquote class="tiktok-embed" cite="' . $safeUrl . '" data-video-id=""><section><a target="_blank" rel="noreferrer" href="' . $safeUrl . '">View TikTok post</a></section></blockquote>';
        }

        return '<a href="' . $safeUrl . '" target="_blank" rel="noreferrer" class="explore-social-fallback"><i class="fa fa-arrow-up-right-from-square"></i><span>Open social post</span></a>';
    }

    public static function isAjax(): bool {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    public static function sendSecurityHeaders(): void {
        static $sent = false;
        if ($sent || headers_sent() || !APP_SEND_SECURITY_HEADERS) {
            return;
        }

        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-site');

        $csp = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "img-src 'self' data: https: blob:",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
            "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
            "script-src 'self' 'unsafe-inline' https://platform.twitter.com https://www.instagram.com https://www.tiktok.com",
            "connect-src 'self' https://accounts.google.com https://oauth2.googleapis.com https://openidconnect.googleapis.com https://api.x.ai https://api.groq.com",
            "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://platform.twitter.com https://www.instagram.com https://www.tiktok.com",
            "form-action 'self' https://accounts.google.com",
            "manifest-src 'self'",
            "media-src 'self' https: data: blob:",
        ];

        if (self::requestScheme() === 'https') {
            $csp[] = 'upgrade-insecure-requests';
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        header('Content-Security-Policy: ' . implode('; ', $csp));
        $sent = true;
    }

    public static function sendCacheHeaders(bool $isAuthenticated = false): void {
        static $sent = false;
        if ($sent || headers_sent()) {
            return;
        }

        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $path = self::requestPath();
        $noStorePaths = [
            '/login',
            '/register',
            '/forgot-password',
            '/reset-password',
            '/settings',
            '/notifications',
            '/bookmarks',
            '/profile',
            '/community/create',
            '/logout',
            '/auth/google',
            '/auth/google/callback',
        ];

        if (!in_array($method, ['GET', 'HEAD'], true) || $isAuthenticated || in_array($path, $noStorePaths, true) || str_starts_with($path, '/api/')) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
            $sent = true;
            return;
        }

        $shortTtlPaths = ['/', '/feed', '/trending', '/explore', '/community', '/sitemap', '/sitemap.xml', '/sitemap-index.xml', '/news-sitemap.xml', '/robots.txt'];
        $maxAge = in_array($path, $shortTtlPaths, true) || str_starts_with($path, '/sitemap-') ? 60 : 300;
        header('Cache-Control: public, max-age=' . $maxAge . ', stale-while-revalidate=300');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
        header_remove('Pragma');
        $sent = true;
    }

    public static function ip(): string {
        $remoteAddr = trim((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));

        if (APP_TRUST_PROXY_HEADERS && self::isTrustedProxy($remoteAddr)) {
            $forwardedFor = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
            if ($forwardedFor !== '') {
                $firstIp = trim(explode(',', $forwardedFor)[0] ?? '');
                if (filter_var($firstIp, FILTER_VALIDATE_IP)) {
                    return $firstIp;
                }
            }

            $realIp = trim((string)($_SERVER['HTTP_X_REAL_IP'] ?? ''));
            if ($realIp !== '' && filter_var($realIp, FILTER_VALIDATE_IP)) {
                return $realIp;
            }
        }

        return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '0.0.0.0';
    }

    private static function isTrustedProxy(string $ip): bool {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        $trusted = is_array(APP_TRUSTED_PROXY_IPS) ? APP_TRUSTED_PROXY_IPS : [];
        if (empty($trusted)) {
            return false;
        }

        return in_array($ip, $trusted, true);
    }

    private static function normalizeHtmlUrl(?string $url): string {
        return trim(html_entity_decode((string)$url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function isSafeHtmlUrl(string $url, bool $allowData = true): bool {
        $url = self::normalizeHtmlUrl($url);
        if ($url === '' || str_starts_with($url, '//')) {
            return false;
        }

        $scheme = strtolower((string)(parse_url($url, PHP_URL_SCHEME) ?? ''));
        if ($scheme === '') {
            return !preg_match('/^\s*(javascript|vbscript|data):/i', $url);
        }

        $allowedSchemes = $allowData
            ? ['http', 'https', 'mailto', 'tel', 'data']
            : ['http', 'https'];

        return in_array($scheme, $allowedSchemes, true);
    }

    private static function imageDimensionsFromUrl(string $url): ?array {
        $file = self::publicPathFromUrl($url);
        if ($file === null || !is_file($file)) {
            return null;
        }

        $size = @getimagesize($file);
        if ($size === false || empty($size[0]) || empty($size[1])) {
            return null;
        }

        return [
            'width' => (int)$size[0],
            'height' => (int)$size[1],
        ];
    }

    private static function publicPathFromUrl(string $url): ?string {
        $url = self::normalizeHtmlUrl($url);
        if ($url === '') {
            return null;
        }

        $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
        if ($path === '') {
            return null;
        }

        $appPath = (string)(parse_url(self::appUrl(), PHP_URL_PATH) ?? '');
        if ($appPath !== '' && str_starts_with($path, $appPath . '/')) {
            $path = substr($path, strlen($appPath));
        }

        if (str_starts_with($path, '/public/')) {
            return PUBLIC_PATH . '/' . ltrim(substr($path, 8), '/');
        }

        if (str_starts_with($path, '/uploads/')) {
            return PUBLIC_PATH . $path;
        }

        return null;
    }
}
