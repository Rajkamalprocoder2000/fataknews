<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

Csrf::check();
Auth::requireLogin();

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = trim((string)($input['action'] ?? 'update_profile'));
$db = Database::getInstance();
$userId = (int)Auth::id();
$user = (new UserModel())->findById($userId);

if (!$user) {
    Helper::json(['error' => 'Account not found'], 404);
}

switch ($action) {
    case 'update_profile':
        $fullName = trim((string)($input['full_name'] ?? $user['full_name']));
        if ($fullName === '') {
            Helper::json(['error' => 'Full name is required'], 422);
        }

        $website = trim((string)($input['website'] ?? ''));
        if ($website !== '' && filter_var($website, FILTER_VALIDATE_URL) === false) {
            Helper::json(['error' => 'Website must be a valid URL'], 422);
        }

        $payload = [
            'full_name' => $fullName,
            'phone' => trim((string)($input['phone'] ?? '')),
            'bio' => trim((string)($input['bio'] ?? '')),
            'location' => trim((string)($input['location'] ?? '')),
            'website' => $website,
        ];

        $avatar = trim((string)($input['avatar'] ?? ''));
        if ($avatar !== '') {
            $payload['avatar'] = basename($avatar);
        }

        $password = (string)($input['password'] ?? '');
        if ($password !== '') {
            if (strlen($password) < 8) {
                Helper::json(['error' => 'Password must be at least 8 characters'], 422);
            }
            $payload['password_hash'] = Auth::hashPassword($password);
        }

        $db->update('users', $payload, 'id=?', [$userId]);
        Helper::json(['success' => true, 'message' => 'Profile updated.']);

    default:
        Helper::json(['error' => 'Unknown action'], 400);
}
