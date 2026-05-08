<?php
// config/config.php - Master configuration

if (!function_exists('cfg_value')) {
    function cfg_value(string $key, $default = null) {
        $value = getenv($key);
        return $value !== false ? $value : $default;
    }

    function cfg_bool(string $key, bool $default = false): bool {
        $value = getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    function cfg_list(string $key, array $default = []): array {
        $value = getenv($key);
        if ($value === false || $value === null || trim((string)$value) === '') {
            return $default;
        }

        $items = array_map('trim', explode(',', (string)$value));
        return array_values(array_filter($items, static fn(string $item): bool => $item !== ''));
    }

    function cfg_request_base_path(): string {
        $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($scriptName === '') {
            return '';
        }

        $base = str_replace('\\', '/', dirname(dirname($scriptName)));
        $base = rtrim($base, '/.');

        return ($base === '' || $base === '/') ? '' : $base;
    }

    function cfg_normalize_host(string $host): string {
        $host = strtolower(trim($host));
        if ($host === '') {
            return '';
        }

        if (!preg_match('/^[a-z0-9.-]+(?::\d{1,5})?$/', $host)) {
            return '';
        }

        return explode(':', $host, 2)[0] ?? '';
    }
}

$localConfig = __DIR__ . '/local.php';
if (is_file($localConfig)) {
    require_once $localConfig;
}

$appEnv = trim((string)(defined('APP_ENV') ? APP_ENV : cfg_value('APP_ENV', 'production')));
if (!in_array($appEnv, ['development', 'staging', 'production'], true)) {
    $appEnv = 'production';
}
if (!defined('APP_ENV')) define('APP_ENV', $appEnv);
if (!defined('DEBUG')) define('DEBUG', cfg_bool('APP_DEBUG', APP_ENV !== 'production'));

$trustProxyHeaders = defined('APP_TRUST_PROXY_HEADERS')
    ? (bool)APP_TRUST_PROXY_HEADERS
    : cfg_bool('APP_TRUST_PROXY_HEADERS', false);

$appScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
if ($trustProxyHeaders) {
    $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if (in_array($forwardedProto, ['http', 'https'], true)) {
        $appScheme = $forwardedProto;
    }
}

$dynamicHost = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
$dynamicUrl = $dynamicHost !== '' ? $appScheme . '://' . $dynamicHost . cfg_request_base_path() : '';
$configuredAppUrl = trim((string)(defined('APP_URL') ? APP_URL : cfg_value('APP_URL', '')));
if ($configuredAppUrl === '' && APP_ENV !== 'production') {
    $configuredAppUrl = $dynamicUrl;
}
$configuredAppUrl = rtrim($configuredAppUrl, '/');

$allowedHosts = [];
if (defined('APP_ALLOWED_HOSTS')) {
    $allowedHosts = is_array(APP_ALLOWED_HOSTS) ? APP_ALLOWED_HOSTS : [(string)APP_ALLOWED_HOSTS];
} else {
    $allowedHosts = cfg_list('APP_ALLOWED_HOSTS', []);
}

$appUrlHost = cfg_normalize_host((string)(parse_url($configuredAppUrl, PHP_URL_HOST) ?? ''));
if ($appUrlHost !== '') {
    $allowedHosts[] = $appUrlHost;
}

if (APP_ENV !== 'production' && empty($allowedHosts)) {
    $normalizedDynamicHost = cfg_normalize_host($dynamicHost);
    if ($normalizedDynamicHost !== '') {
        $allowedHosts[] = $normalizedDynamicHost;
    }
}

$allowedHosts = array_values(array_unique(array_filter(array_map(
    static fn($host): string => cfg_normalize_host((string)$host),
    $allowedHosts
))));

define('APP_NAME', 'FatakNews.in');
define('APP_VERSION', '1.0.0');
if (!defined('APP_URL')) define('APP_URL', $configuredAppUrl);
if (!defined('APP_ALLOWED_HOSTS')) define('APP_ALLOWED_HOSTS', $allowedHosts);
define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', BASE_PATH . '/public');
define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');

if (!defined('DB_HOST')) define('DB_HOST', (string)cfg_value('DB_HOST', 'localhost'));
if (!defined('DB_NAME')) define('DB_NAME', (string)cfg_value('DB_NAME', APP_ENV === 'development' ? 'fataknews_db' : ''));
if (!defined('DB_USER')) define('DB_USER', (string)cfg_value('DB_USER', APP_ENV === 'development' ? 'root' : ''));
if (!defined('DB_PASS')) define('DB_PASS', (string)cfg_value('DB_PASS', APP_ENV === 'development' ? '' : ''));
if (!defined('DB_CHARSET')) define('DB_CHARSET', (string)cfg_value('DB_CHARSET', 'utf8mb4'));

if (!defined('SESSION_NAME')) define('SESSION_NAME', (string)cfg_value('SESSION_NAME', 'fn_session'));
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', (int)cfg_value('SESSION_LIFETIME', 86400 * 7));
if (!defined('SESSION_COOKIE_SECURE')) define('SESSION_COOKIE_SECURE', cfg_bool('SESSION_COOKIE_SECURE', APP_ENV === 'production'));

if (!defined('POSTS_PER_PAGE')) define('POSTS_PER_PAGE', (int)cfg_value('POSTS_PER_PAGE', 20));

if (!defined('MAX_IMAGE_SIZE')) define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);
if (!defined('MAX_VIDEO_SIZE')) define('MAX_VIDEO_SIZE', 100 * 1024 * 1024);
if (!defined('ALLOWED_IMAGE_TYPES')) define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

if (!defined('JWT_SECRET')) define('JWT_SECRET', (string)cfg_value('JWT_SECRET', APP_ENV === 'development' ? 'dev-jwt-secret-change-me' : ''));
if (!defined('JWT_EXPIRE')) define('JWT_EXPIRE', (int)cfg_value('JWT_EXPIRE', 86400 * 7));

if (!defined('MAIL_FROM')) define('MAIL_FROM', (string)cfg_value('MAIL_FROM', 'info@fataknews.in'));
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', (string)cfg_value('MAIL_FROM_NAME', 'FatakNews.in'));

if (!defined('GOOGLE_CLIENT_ID')) define('GOOGLE_CLIENT_ID', (string)cfg_value('GOOGLE_CLIENT_ID', ''));
if (!defined('GOOGLE_CLIENT_SECRET')) define('GOOGLE_CLIENT_SECRET', (string)cfg_value('GOOGLE_CLIENT_SECRET', ''));
if (!defined('GOOGLE_AUTH_BASE_URL')) define('GOOGLE_AUTH_BASE_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
if (!defined('GOOGLE_TOKEN_URL')) define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
if (!defined('GOOGLE_USERINFO_URL')) define('GOOGLE_USERINFO_URL', 'https://openidconnect.googleapis.com/v1/userinfo');
if (!defined('GOOGLE_OAUTH_SCOPES')) define('GOOGLE_OAUTH_SCOPES', 'openid email profile');
if (!defined('GTM_CONTAINER_ID')) define('GTM_CONTAINER_ID', (string)cfg_value('GTM_CONTAINER_ID', ''));
if (!defined('GA_MEASUREMENT_ID')) define('GA_MEASUREMENT_ID', (string)cfg_value('GA_MEASUREMENT_ID', ''));

if (!defined('AI_PROVIDER')) define('AI_PROVIDER', (string)cfg_value('AI_PROVIDER', 'auto'));
if (!defined('AI_TIMEOUT')) define('AI_TIMEOUT', (int)cfg_value('AI_TIMEOUT', 90));
if (!defined('AI_CA_BUNDLE')) define('AI_CA_BUNDLE', BASE_PATH . '/config/cacert.pem');

if (!defined('XAI_BASE_URL')) define('XAI_BASE_URL', (string)cfg_value('XAI_BASE_URL', 'https://api.x.ai/v1'));
if (!defined('XAI_API_KEY')) define('XAI_API_KEY', (string)cfg_value('XAI_API_KEY', ''));
if (!defined('XAI_MODEL')) define('XAI_MODEL', (string)cfg_value('XAI_MODEL', 'grok-4-fast-non-reasoning'));

if (!defined('GROQ_BASE_URL')) define('GROQ_BASE_URL', (string)cfg_value('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'));
if (!defined('GROQ_API_KEY')) define('GROQ_API_KEY', (string)cfg_value('GROQ_API_KEY', ''));
if (!defined('GROQ_MODEL')) define('GROQ_MODEL', (string)cfg_value('GROQ_MODEL', 'llama-3.3-70b-versatile'));
if (!defined('CONTENT_PIPELINE_FEEDS')) define('CONTENT_PIPELINE_FEEDS', cfg_list('CONTENT_PIPELINE_FEEDS', []));
if (!defined('CONTENT_PIPELINE_DEFAULT_USER_ID')) define('CONTENT_PIPELINE_DEFAULT_USER_ID', (int)cfg_value('CONTENT_PIPELINE_DEFAULT_USER_ID', 0));
if (!defined('CONTENT_PIPELINE_TIMEOUT')) define('CONTENT_PIPELINE_TIMEOUT', (int)cfg_value('CONTENT_PIPELINE_TIMEOUT', 20));
if (!defined('CONTENT_PIPELINE_SSL_VERIFY')) define('CONTENT_PIPELINE_SSL_VERIFY', cfg_bool('CONTENT_PIPELINE_SSL_VERIFY', true));
if (!defined('CONTENT_PIPELINE_TRENDING_THRESHOLD')) define('CONTENT_PIPELINE_TRENDING_THRESHOLD', (float)cfg_value('CONTENT_PIPELINE_TRENDING_THRESHOLD', 55));
if (!defined('CONTENT_PIPELINE_AUTO_WRITE')) define('CONTENT_PIPELINE_AUTO_WRITE', cfg_bool('CONTENT_PIPELINE_AUTO_WRITE', false));
if (!defined('CONTENT_PIPELINE_AUTO_PUBLISH')) define('CONTENT_PIPELINE_AUTO_PUBLISH', cfg_bool('CONTENT_PIPELINE_AUTO_PUBLISH', false));
if (!defined('CONTENT_PIPELINE_AUTO_MIN_SCORE')) define('CONTENT_PIPELINE_AUTO_MIN_SCORE', (float)cfg_value('CONTENT_PIPELINE_AUTO_MIN_SCORE', CONTENT_PIPELINE_TRENDING_THRESHOLD));
if (!defined('CONTENT_PIPELINE_AUTO_MAX_PER_RUN')) define('CONTENT_PIPELINE_AUTO_MAX_PER_RUN', max(0, (int)cfg_value('CONTENT_PIPELINE_AUTO_MAX_PER_RUN', 3)));

if (!defined('BCRYPT_COST')) define('BCRYPT_COST', 12);
if (!defined('CSRF_EXPIRE')) define('CSRF_EXPIRE', (int)cfg_value('CSRF_EXPIRE', 3600));
if (!defined('APP_FORCE_HTTPS')) define('APP_FORCE_HTTPS', cfg_bool('APP_FORCE_HTTPS', APP_ENV === 'production'));
if (!defined('APP_SEND_SECURITY_HEADERS')) define('APP_SEND_SECURITY_HEADERS', cfg_bool('APP_SEND_SECURITY_HEADERS', true));
if (!defined('APP_TRUST_PROXY_HEADERS')) define('APP_TRUST_PROXY_HEADERS', $trustProxyHeaders);
if (!defined('APP_TRUSTED_PROXY_IPS')) define('APP_TRUSTED_PROXY_IPS', cfg_list('APP_TRUSTED_PROXY_IPS', []));
if (!defined('APP_ENABLE_SCHEMA_CHECK')) define('APP_ENABLE_SCHEMA_CHECK', cfg_bool('APP_ENABLE_SCHEMA_CHECK', false));
if (!defined('TAG_INDEX_WHITELIST')) define('TAG_INDEX_WHITELIST', cfg_list('TAG_INDEX_WHITELIST', []));
if (!defined('TAG_NOINDEX_BLACKLIST')) define('TAG_NOINDEX_BLACKLIST', cfg_list('TAG_NOINDEX_BLACKLIST', []));
if (!defined('AUTH_LOGIN_RATE_LIMIT')) define('AUTH_LOGIN_RATE_LIMIT', (int)cfg_value('AUTH_LOGIN_RATE_LIMIT', 10));
if (!defined('AUTH_LOGIN_RATE_WINDOW')) define('AUTH_LOGIN_RATE_WINDOW', (int)cfg_value('AUTH_LOGIN_RATE_WINDOW', 900));
if (!defined('AUTH_REGISTER_RATE_LIMIT')) define('AUTH_REGISTER_RATE_LIMIT', (int)cfg_value('AUTH_REGISTER_RATE_LIMIT', 5));
if (!defined('AUTH_REGISTER_RATE_WINDOW')) define('AUTH_REGISTER_RATE_WINDOW', (int)cfg_value('AUTH_REGISTER_RATE_WINDOW', 3600));
if (!defined('AUTH_FORGOT_RATE_LIMIT')) define('AUTH_FORGOT_RATE_LIMIT', (int)cfg_value('AUTH_FORGOT_RATE_LIMIT', 5));
if (!defined('AUTH_FORGOT_RATE_WINDOW')) define('AUTH_FORGOT_RATE_WINDOW', (int)cfg_value('AUTH_FORGOT_RATE_WINDOW', 3600));
