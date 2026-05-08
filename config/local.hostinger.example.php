<?php
// Hostinger production example. Copy to config/local.php on the server and fill real values.
// Do not commit the real file with secrets.

if (!defined('APP_ENV')) define('APP_ENV', 'production');
if (!defined('APP_URL')) define('APP_URL', 'https://example.com');
if (!defined('APP_ALLOWED_HOSTS')) define('APP_ALLOWED_HOSTS', ['example.com', 'www.example.com']);
if (!defined('APP_FORCE_HTTPS')) define('APP_FORCE_HTTPS', true);
if (!defined('APP_TRUST_PROXY_HEADERS')) define('APP_TRUST_PROXY_HEADERS', false);

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'hostinger_database_name');
if (!defined('DB_USER')) define('DB_USER', 'hostinger_database_user');
if (!defined('DB_PASS')) define('DB_PASS', 'hostinger_database_password');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

if (!defined('MAIL_FROM')) define('MAIL_FROM', 'info@example.com');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', 'FatakNews');

if (!defined('GOOGLE_CLIENT_ID')) define('GOOGLE_CLIENT_ID', '');
if (!defined('GOOGLE_CLIENT_SECRET')) define('GOOGLE_CLIENT_SECRET', '');
if (!defined('GTM_CONTAINER_ID')) define('GTM_CONTAINER_ID', '');
if (!defined('GA_MEASUREMENT_ID')) define('GA_MEASUREMENT_ID', '');

if (!defined('AI_PROVIDER')) define('AI_PROVIDER', 'auto'); // or 'groq' / 'xai'
if (!defined('XAI_API_KEY')) define('XAI_API_KEY', '');
if (!defined('XAI_MODEL')) define('XAI_MODEL', 'grok-4-fast-non-reasoning');
if (!defined('GROQ_API_KEY')) define('GROQ_API_KEY', '');
if (!defined('GROQ_MODEL')) define('GROQ_MODEL', 'llama-3.3-70b-versatile');

if (!defined('TAG_INDEX_WHITELIST')) define('TAG_INDEX_WHITELIST', []);
if (!defined('TAG_NOINDEX_BLACKLIST')) define('TAG_NOINDEX_BLACKLIST', []);
