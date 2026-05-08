<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

Csrf::check();
Auth::requireRole('super_admin', 'admin');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = trim((string)($input['action'] ?? ''));
$db = Database::getInstance();

$allowedSettings = [
    'site_name' => ['group' => 'general', 'type' => 'string', 'required' => true],
    'site_tagline' => ['group' => 'general', 'type' => 'string'],
    'site_email' => ['group' => 'general', 'type' => 'email', 'required' => true],
    'site_phone' => ['group' => 'general', 'type' => 'string'],
    'posts_per_page' => ['group' => 'general', 'type' => 'int', 'min' => 1, 'max' => 100],
    'allow_registration' => ['group' => 'general', 'type' => 'bool'],
    'maintenance_mode' => ['group' => 'general', 'type' => 'bool'],
    'breaking_news' => ['group' => 'ticker', 'type' => 'string'],
    'facebook_url' => ['group' => 'social', 'type' => 'url'],
    'twitter_url' => ['group' => 'social', 'type' => 'url'],
    'instagram_url' => ['group' => 'social', 'type' => 'url'],
    'youtube_url' => ['group' => 'social', 'type' => 'url'],
    'whatsapp_url' => ['group' => 'social', 'type' => 'url'],
    'indeed_url' => ['group' => 'social', 'type' => 'url'],
    'google_analytics' => ['group' => 'analytics', 'type' => 'string'],
    'smtp_host' => ['group' => 'mail', 'type' => 'string'],
    'smtp_port' => ['group' => 'mail', 'type' => 'int', 'min' => 1, 'max' => 65535],
    'smtp_user' => ['group' => 'mail', 'type' => 'string'],
    'smtp_pass' => ['group' => 'mail', 'type' => 'string'],
];

$normalizeValue = static function (string $key, $value, array $meta): string {
    $type = $meta['type'];

    if ($type === 'bool') {
        return !empty($value) ? '1' : '0';
    }

    $value = trim((string)$value);

    if (!empty($meta['required']) && $value === '') {
        Helper::json(['error' => sprintf('%s is required', ucwords(str_replace('_', ' ', $key)))], 422);
    }

    if ($type === 'email' && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
        Helper::json(['error' => 'Site email must be a valid email address'], 422);
    }

    if ($type === 'url' && $value !== '' && filter_var($value, FILTER_VALIDATE_URL) === false) {
        Helper::json(['error' => sprintf('%s must be a valid URL', ucwords(str_replace('_', ' ', $key)))], 422);
    }

    if ($type === 'int') {
        if ($value === '' || filter_var($value, FILTER_VALIDATE_INT) === false) {
            Helper::json(['error' => sprintf('%s must be a valid number', ucwords(str_replace('_', ' ', $key)))], 422);
        }

        $number = (int)$value;
        $min = (int)($meta['min'] ?? PHP_INT_MIN);
        $max = (int)($meta['max'] ?? PHP_INT_MAX);
        if ($number < $min || $number > $max) {
            Helper::json(['error' => sprintf('%s must be between %d and %d', ucwords(str_replace('_', ' ', $key)), $min, $max)], 422);
        }

        return (string)$number;
    }

    return $value;
};

switch ($action) {
    case 'save':
        $settings = $input['settings'] ?? null;
        if (!is_array($settings) || $settings === []) {
            Helper::json(['error' => 'No settings were submitted'], 422);
        }

        $pdo = $db->getConnection();
        $pdo->beginTransaction();

        try {
            foreach ($allowedSettings as $key => $meta) {
                $hasValue = array_key_exists($key, $settings);
                if (!$hasValue && $meta['type'] !== 'bool') {
                    continue;
                }

                $normalized = $normalizeValue($key, $settings[$key] ?? null, $meta);
                $existing = $db->fetchOne("SELECT `key` FROM settings WHERE `key`=?", [$key]);

                if ($existing) {
                    $db->update('settings', ['value' => $normalized, 'group' => $meta['group']], '`key`=?', [$key]);
                } else {
                    $db->insert('settings', ['key' => $key, 'value' => $normalized, 'group' => $meta['group']]);
                }
            }

            $pdo->commit();
            Helper::json(['success' => true, 'message' => 'Settings updated.']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if (DEBUG) {
                throw $e;
            }

            Helper::json(['error' => 'Failed to update settings'], 500);
        }

    default:
        Helper::json(['error' => 'Unknown action'], 400);
}
