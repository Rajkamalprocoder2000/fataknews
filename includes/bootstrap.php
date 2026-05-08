<?php
// includes/bootstrap.php

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        if ($needle === '') {
            return true;
        }

        $needleLength = strlen($needle);
        return substr($haystack, -$needleLength) === $needle;
    }
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../app/helpers/Helper.php';
require_once __DIR__ . '/../app/helpers/Mailer.php';
require_once __DIR__ . '/../app/helpers/GoogleAuth.php';
require_once __DIR__ . '/../app/helpers/XaiWriter.php';
require_once __DIR__ . '/../app/helpers/ContentPipeline.php';
require_once __DIR__ . '/../app/helpers/Auth.php';
require_once __DIR__ . '/../app/helpers/Csrf.php';
require_once __DIR__ . '/../app/helpers/Upload.php';
require_once __DIR__ . '/../app/helpers/RateLimiter.php';
require_once __DIR__ . '/../app/models/Model.php';
require_once __DIR__ . '/../app/models/ContentCandidateModel.php';
require_once __DIR__ . '/../app/models/UserModel.php';
require_once __DIR__ . '/../app/models/PostModel.php';
require_once __DIR__ . '/../app/models/CategoryModel.php';
require_once __DIR__ . '/../app/models/CommentModel.php';
require_once __DIR__ . '/../app/models/NotificationModel.php';
require_once __DIR__ . '/../app/models/HrModel.php';
require_once __DIR__ . '/../app/models/StoryModel.php';

Helper::ensureTrustedHost();
Helper::ensureHttps();

if (!is_dir(BASE_PATH . '/tmp')) {
    mkdir(BASE_PATH . '/tmp', 0700, true);
}
if (!is_dir(BASE_PATH . '/tmp/sessions')) {
    mkdir(BASE_PATH . '/tmp/sessions', 0700, true);
}

session_save_path(BASE_PATH . '/tmp/sessions');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_trans_sid', '0');
ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
session_name(SESSION_NAME);

$sessionCookiePath = Helper::basePath();
if ($sessionCookiePath === '') {
    $sessionCookiePath = '/';
}

$secureCookies = (bool)SESSION_COOKIE_SECURE;
if (APP_ENV !== 'production' && Helper::requestScheme() !== 'https') {
    $secureCookies = false;
}

session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path' => $sessionCookiePath,
    'secure' => $secureCookies,
    'httponly' => true,
    'samesite' => 'Lax',
]);
$requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$requestPath = Helper::requestPath();
$forceSessionPaths = [
    '/login',
    '/register',
    '/forgot-password',
    '/reset-password',
    '/auth/google',
    '/auth/google/callback',
];
$hasSessionCookie = !empty($_COOKIE[SESSION_NAME]);
$shouldStartSession = $hasSessionCookie
    || !in_array($requestMethod, ['GET', 'HEAD'], true)
    || in_array($requestPath, $forceSessionPaths, true);

if ($shouldStartSession) {
    session_cache_limiter('');
    session_start();
} elseif (!isset($_SESSION) || !is_array($_SESSION)) {
    $_SESSION = [];
}

date_default_timezone_set('Asia/Kolkata');

if (DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

Helper::sendSecurityHeaders();
Helper::sendCacheHeaders(Auth::check());
Csrf::init();

if (APP_ENABLE_SCHEMA_CHECK && empty($_SESSION['_schema_posts_image_alt_checked'])) {
    try {
        $db = Database::getInstance();
        $hasImageAlt = $db->fetchOne(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'image_alt'",
            [DB_NAME]
        );

        if (!$hasImageAlt) {
            $db->query("ALTER TABLE posts ADD COLUMN image_alt VARCHAR(255) DEFAULT NULL AFTER thumbnail");
        }
    } catch (Throwable $e) {
        if (DEBUG) {
            error_log('Schema check failed for posts.image_alt: ' . $e->getMessage());
        }
    }

    $_SESSION['_schema_posts_image_alt_checked'] = 1;
}
