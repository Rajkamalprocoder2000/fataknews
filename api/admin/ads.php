<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

Csrf::check();
Auth::requireRole('super_admin', 'admin');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = trim((string)($input['action'] ?? ''));
$db = Database::getInstance();

$findAd = static function (int $id) use ($db): ?array {
    return $db->fetchOne("SELECT * FROM ads WHERE id=?", [$id]);
};

$buildPayload = static function (array $input): array {
    $title = trim((string)($input['title'] ?? ''));
    if ($title === '') {
        Helper::json(['error' => 'Ad title is required'], 422);
    }

    $type = trim((string)($input['type'] ?? 'banner'));
    $allowedTypes = ['banner', 'sidebar', 'inline', 'popup', 'video'];
    if (!in_array($type, $allowedTypes, true)) {
        Helper::json(['error' => 'Invalid ad type'], 422);
    }

    $position = trim((string)($input['position'] ?? 'homepage_top'));
    if ($position === '') {
        Helper::json(['error' => 'Ad position is required'], 422);
    }

    $link = trim((string)($input['link'] ?? ''));
    if ($link !== '' && filter_var($link, FILTER_VALIDATE_URL) === false) {
        Helper::json(['error' => 'Ad link must be a valid URL'], 422);
    }

    $startDate = trim((string)($input['start_date'] ?? ''));
    $endDate = trim((string)($input['end_date'] ?? ''));
    if ($startDate !== '' && $endDate !== '' && strtotime($endDate) < strtotime($startDate)) {
        Helper::json(['error' => 'End date cannot be earlier than start date'], 422);
    }

    return [
        'title' => $title,
        'type' => $type,
        'position' => $position,
        'image' => trim((string)($input['image'] ?? '')),
        'link' => $link,
        'code' => trim((string)($input['code'] ?? '')),
        'is_active' => !empty($input['is_active']) ? 1 : 0,
        'start_date' => $startDate !== '' ? $startDate : null,
        'end_date' => $endDate !== '' ? $endDate : null,
    ];
};

switch ($action) {
    case 'create':
        $payload = $buildPayload($input);
        $newId = $db->insert('ads', $payload);
        Helper::json(['success' => true, 'message' => 'Advertisement created.', 'id' => $newId]);

    case 'update':
        $adId = (int)($input['ad_id'] ?? 0);
        $existing = $findAd($adId);
        if (!$existing) {
            Helper::json(['error' => 'Advertisement not found'], 404);
        }
        $payload = $buildPayload($input);
        $db->update('ads', $payload, 'id=?', [$adId]);
        Helper::json(['success' => true, 'message' => 'Advertisement updated.']);

    case 'toggle_active':
        $adId = (int)($input['ad_id'] ?? 0);
        $existing = $findAd($adId);
        if (!$existing) {
            Helper::json(['error' => 'Advertisement not found'], 404);
        }
        $next = $existing['is_active'] ? 0 : 1;
        $db->update('ads', ['is_active' => $next], 'id=?', [$adId]);
        Helper::json(['success' => true, 'message' => $next ? 'Advertisement activated.' : 'Advertisement paused.']);

    case 'delete':
        $adId = (int)($input['ad_id'] ?? 0);
        $existing = $findAd($adId);
        if (!$existing) {
            Helper::json(['error' => 'Advertisement not found'], 404);
        }
        $db->delete('ads', 'id=?', [$adId]);
        Helper::json(['success' => true, 'message' => 'Advertisement deleted.']);

    default:
        Helper::json(['error' => 'Unknown action'], 400);
}
