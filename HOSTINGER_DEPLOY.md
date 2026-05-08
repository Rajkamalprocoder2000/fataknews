# Hostinger Deployment

This app can run on Hostinger shared hosting without changing the router, but the deployment folder layout matters.

## Recommended layout

Upload the whole project so the folder containing `.htaccess`, `api/`, `app/`, `config/`, `includes/`, `panels/`, `public/`, and `tmp/` stays together.

Example:

```text
/home/u123456789/domains/example.com/public_html/fataknews/
  .htaccess
  api/
  app/
  config/
  includes/
  panels/
  public/
  tmp/
```

Then point the domain or subdomain root to that project folder if possible.

If your domain must stay on `public_html`, upload the project contents directly inside `public_html`.

## Why this layout is required

The app serves assets from `/public/...` and the root `.htaccess` forwards requests to `public/index.php`. Because of that, this project expects the repository root itself to be web-reachable, while direct access to internal folders is blocked by `.htaccess`.

Do not deploy only the `public/` folder by itself unless you also rewrite the app paths.

## Server steps

1. Create a MySQL database in Hostinger.
2. Import [schema.sql](/c:/wamp64/www/fataknews_complete_2/fataknews/database/schema.sql).
3. Copy [local.hostinger.example.php](/c:/wamp64/www/fataknews_complete_2/fataknews/config/local.hostinger.example.php) to `config/local.php`.
4. Fill `APP_URL`, `APP_ALLOWED_HOSTS`, database credentials, mail sender, and any API keys.
5. Make sure these paths are writable:
   `tmp/`
   `public/uploads/`
   `public/uploads/avatars/`
   `public/uploads/news/`
   `public/uploads/thumbnails/`
6. In Hostinger PHP settings, use PHP 8.3 if available.
7. Open the site once and verify login, uploads, and routing.

## Suggested `config/local.php`

```php
<?php
if (!defined('APP_ENV')) define('APP_ENV', 'production');
if (!defined('APP_URL')) define('APP_URL', 'https://example.com');
if (!defined('APP_ALLOWED_HOSTS')) define('APP_ALLOWED_HOSTS', ['example.com', 'www.example.com']);
if (!defined('APP_FORCE_HTTPS')) define('APP_FORCE_HTTPS', true);
if (!defined('APP_TRUST_PROXY_HEADERS')) define('APP_TRUST_PROXY_HEADERS', false);

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'hostinger_database_name');
if (!defined('DB_USER')) define('DB_USER', 'hostinger_database_user');
if (!defined('DB_PASS')) define('DB_PASS', 'hostinger_database_password');
```

## Mail / newsletter

Newsletter and password reset email now support SMTP. After deployment:

1. Log in as admin.
2. Open Admin Settings.
3. Fill `SMTP Host`, `SMTP Port`, `SMTP User`, `SMTP Pass`.
4. Keep `Site Email` valid.

If delivery fails, check `tmp/mail.log`.

## Google login

If you use Google login, update the OAuth callback URL to:

```text
https://example.com/auth/google/callback
```

Or if deployed in a subfolder:

```text
https://example.com/fataknews/auth/google/callback
```

## Common mistakes

- Uploading only `public/` and not the full project
- Forgetting to create `config/local.php`
- Leaving `APP_ALLOWED_HOSTS` on `localhost`
- Using HTTP in `APP_URL` on production
- Not giving write permission to `tmp/` and `public/uploads/`
- Uploading your local development `config/local.php` with WAMP credentials
