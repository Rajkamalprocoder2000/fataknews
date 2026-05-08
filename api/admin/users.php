<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

Csrf::check();
Auth::requireRole('super_admin', 'admin');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = trim((string)($input['action'] ?? ''));
$db = Database::getInstance();

$findUser = static function (int $id) use ($db): ?array {
    return $db->fetchOne(
        "SELECT u.*, r.slug AS role_slug, r.name AS role_name
         FROM users u
         JOIN roles r ON r.id=u.role_id
         WHERE u.id=?",
        [$id]
    );
};

$validateRole = static function (int $roleId) use ($db): array {
    $role = $db->fetchOne("SELECT id, slug, name FROM roles WHERE id=?", [$roleId]);
    if (!$role) {
        Helper::json(['error' => 'Selected role was not found'], 404);
    }
    return $role;
};

$buildPayload = static function (array $input, ?array $existing = null) use ($db, $validateRole): array {
    $fullName = trim((string)($input['full_name'] ?? ''));
    $username = trim((string)($input['username'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $roleId = (int)($input['role_id'] ?? ($existing['role_id'] ?? 7));

    if ($fullName === '' || $username === '' || $email === '') {
        Helper::json(['error' => 'Full name, username, and email are required'], 422);
    }

    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        Helper::json(['error' => 'Username must be 3-30 chars using letters, numbers, or underscore'], 422);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Helper::json(['error' => 'A valid email is required'], 422);
    }

    $userId = (int)($existing['id'] ?? 0);
    $usernameTaken = $db->fetchOne("SELECT id FROM users WHERE username=? AND id<>?", [$username, $userId]);
    if ($usernameTaken) {
        Helper::json(['error' => 'Username is already in use'], 409);
    }

    $emailTaken = $db->fetchOne("SELECT id FROM users WHERE email=? AND id<>?", [$email, $userId]);
    if ($emailTaken) {
        Helper::json(['error' => 'Email is already in use'], 409);
    }

    $validateRole($roleId);

    $payload = [
        'role_id' => $roleId,
        'username' => $username,
        'email' => $email,
        'full_name' => $fullName,
        'phone' => trim((string)($input['phone'] ?? '')),
        'bio' => trim((string)($input['bio'] ?? '')),
        'location' => trim((string)($input['location'] ?? '')),
        'website' => trim((string)($input['website'] ?? '')),
        'is_verified' => !empty($input['is_verified']) ? 1 : 0,
        'is_active' => !empty($input['is_active']) ? 1 : 0,
        'email_verified' => !empty($input['email_verified']) ? 1 : 0,
        'badge_level' => trim((string)($input['badge_level'] ?? ($existing['badge_level'] ?? 'bronze'))) ?: 'bronze',
    ];

    $avatar = trim((string)($input['avatar'] ?? ($existing['avatar'] ?? '')));
    if ($avatar !== '') {
        $payload['avatar'] = basename($avatar);
    }

    $password = (string)($input['password'] ?? '');
    if ($password !== '') {
        if (strlen($password) < 8) {
            Helper::json(['error' => 'Password must be at least 8 characters'], 422);
        }
        $payload['password_hash'] = Auth::hashPassword($password);
    } elseif ($existing === null) {
        Helper::json(['error' => 'Password is required for new users'], 422);
    }

    return $payload;
};

switch ($action) {
    case 'create':
        $payload = $buildPayload($input);
        $newId = $db->insert('users', $payload);
        Helper::json(['success' => true, 'message' => 'User created.', 'id' => $newId]);

    case 'update':
        $userId = (int)($input['user_id'] ?? 0);
        $existing = $findUser($userId);
        if (!$existing) {
            Helper::json(['error' => 'User not found'], 404);
        }

        $payload = $buildPayload($input, $existing);
        $db->update('users', $payload, 'id=?', [$userId]);
        Helper::json(['success' => true, 'message' => 'User updated.']);

    case 'toggle_active':
        $userId = (int)($input['user_id'] ?? 0);
        $existing = $findUser($userId);
        if (!$existing) {
            Helper::json(['error' => 'User not found'], 404);
        }
        if ($userId === (int)Auth::id()) {
            Helper::json(['error' => 'You cannot suspend your own account'], 409);
        }

        $next = $existing['is_active'] ? 0 : 1;
        $db->update('users', ['is_active' => $next], 'id=?', [$userId]);
        Helper::json(['success' => true, 'message' => $next ? 'User activated.' : 'User suspended.']);

    case 'toggle_verified':
        $userId = (int)($input['user_id'] ?? 0);
        $existing = $findUser($userId);
        if (!$existing) {
            Helper::json(['error' => 'User not found'], 404);
        }

        $next = $existing['is_verified'] ? 0 : 1;
        $db->update('users', ['is_verified' => $next], 'id=?', [$userId]);
        Helper::json(['success' => true, 'message' => $next ? 'User verified.' : 'Verification removed.']);

    case 'delete':
        $userId = (int)($input['user_id'] ?? 0);
        $existing = $findUser($userId);
        if (!$existing) {
            Helper::json(['error' => 'User not found'], 404);
        }
        if ($userId === (int)Auth::id()) {
            Helper::json(['error' => 'You cannot delete your own account'], 409);
        }
        if (in_array($existing['role_slug'], ['super_admin', 'admin'], true)) {
            Helper::json(['error' => 'Delete admin accounts manually if you really need to'], 409);
        }
        $postCount = (int)($db->fetchOne("SELECT COUNT(*) AS c FROM posts WHERE user_id=?", [$userId])['c'] ?? 0);
        if ($postCount > 0) {
            Helper::json(['error' => 'This user has posts attached and cannot be deleted directly'], 409);
        }

        $db->delete('users', 'id=?', [$userId]);
        Helper::json(['success' => true, 'message' => 'User deleted.']);

    default:
        Helper::json(['error' => 'Unknown action'], 400);
}
