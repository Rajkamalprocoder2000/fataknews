<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

Csrf::check();
Auth::requireRole('super_admin', 'admin', 'hr');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = trim((string)($input['action'] ?? ''));
$db = Database::getInstance();
$pdo = $db->getConnection();

$allowedRoleSlugs = ['manager', 'editor', 'reporter', 'hr'];

$findEmployee = static function (int $userId) use ($db): ?array {
    return $db->fetchOne(
        "SELECT u.*, ep.department_id, ep.designation, ep.employee_code, ep.joining_date, ep.salary,
                ep.bank_account, ep.pan_number, ep.aadhar_number, ep.address, ep.emergency_contact,
                ep.reporting_to, ep.is_active AS employee_active, r.slug AS role_slug
         FROM users u
         JOIN employee_profiles ep ON ep.user_id=u.id
         JOIN roles r ON r.id=u.role_id
         WHERE u.id=?",
        [$userId]
    );
};

$resolveRole = static function (int $roleId) use ($db, $allowedRoleSlugs): array {
    $role = $db->fetchOne("SELECT id, slug, name FROM roles WHERE id=?", [$roleId]);
    if (!$role || !in_array($role['slug'], $allowedRoleSlugs, true)) {
        Helper::json(['error' => 'Please select a staff role'], 422);
    }
    return $role;
};

$validatePayload = static function (array $input, ?array $existing = null) use ($db, $resolveRole): array {
    $fullName = trim((string)($input['full_name'] ?? ''));
    $username = trim((string)($input['username'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $employeeCode = trim((string)($input['employee_code'] ?? ''));
    $designation = trim((string)($input['designation'] ?? ''));
    $roleId = (int)($input['role_id'] ?? ($existing['role_id'] ?? 0));
    $departmentId = (int)($input['department_id'] ?? ($existing['department_id'] ?? 0));
    $joiningDate = trim((string)($input['joining_date'] ?? ''));
    $salary = trim((string)($input['salary'] ?? ''));
    $userId = (int)($existing['id'] ?? 0);

    if ($fullName === '' || $username === '' || $email === '' || $employeeCode === '' || $designation === '') {
        Helper::json(['error' => 'Full name, username, email, employee code, and designation are required'], 422);
    }

    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        Helper::json(['error' => 'Username must be 3-30 chars using letters, numbers, or underscore'], 422);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Helper::json(['error' => 'A valid email is required'], 422);
    }

    if ($joiningDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $joiningDate)) {
        Helper::json(['error' => 'Joining date must be valid'], 422);
    }

    if ($salary !== '' && !is_numeric($salary)) {
        Helper::json(['error' => 'Salary must be numeric'], 422);
    }

    $resolveRole($roleId);

    if ($departmentId > 0 && !$db->fetchOne("SELECT id FROM departments WHERE id=?", [$departmentId])) {
        Helper::json(['error' => 'Selected department not found'], 404);
    }

    $usernameTaken = $db->fetchOne("SELECT id FROM users WHERE username=? AND id<>?", [$username, $userId]);
    if ($usernameTaken) {
        Helper::json(['error' => 'Username is already in use'], 409);
    }

    $emailTaken = $db->fetchOne("SELECT id FROM users WHERE email=? AND id<>?", [$email, $userId]);
    if ($emailTaken) {
        Helper::json(['error' => 'Email is already in use'], 409);
    }

    $codeTaken = $db->fetchOne("SELECT user_id FROM employee_profiles WHERE employee_code=? AND user_id<>?", [$employeeCode, $userId]);
    if ($codeTaken) {
        Helper::json(['error' => 'Employee code is already in use'], 409);
    }

    $reportingTo = isset($input['reporting_to']) && $input['reporting_to'] !== '' ? (int)$input['reporting_to'] : null;
    if ($reportingTo !== null) {
        if ($reportingTo === $userId && $userId > 0) {
            Helper::json(['error' => 'An employee cannot report to themselves'], 422);
        }
        $manager = $db->fetchOne("SELECT id FROM users WHERE id=?", [$reportingTo]);
        if (!$manager) {
            Helper::json(['error' => 'Reporting manager not found'], 404);
        }
    }

    $payloadUser = [
        'role_id' => $roleId,
        'username' => $username,
        'email' => $email,
        'full_name' => $fullName,
        'phone' => trim((string)($input['phone'] ?? '')),
        'location' => trim((string)($input['location'] ?? '')),
        'website' => trim((string)($input['website'] ?? '')),
        'bio' => trim((string)($input['bio'] ?? '')),
        'is_active' => !empty($input['is_active']) ? 1 : 0,
        'is_verified' => !empty($input['is_verified']) ? 1 : 0,
        'email_verified' => !empty($input['email_verified']) ? 1 : 0,
        'badge_level' => trim((string)($input['badge_level'] ?? ($existing['badge_level'] ?? 'press'))) ?: 'press',
    ];

    $password = (string)($input['password'] ?? '');
    if ($password !== '') {
        if (strlen($password) < 8) {
            Helper::json(['error' => 'Password must be at least 8 characters'], 422);
        }
        $payloadUser['password_hash'] = Auth::hashPassword($password);
    } elseif ($existing === null) {
        Helper::json(['error' => 'Password is required for new employees'], 422);
    }

    $payloadProfile = [
        'department_id' => $departmentId > 0 ? $departmentId : null,
        'designation' => $designation,
        'employee_code' => $employeeCode,
        'joining_date' => $joiningDate !== '' ? $joiningDate : null,
        'salary' => $salary !== '' ? $salary : null,
        'bank_account' => trim((string)($input['bank_account'] ?? '')),
        'pan_number' => trim((string)($input['pan_number'] ?? '')),
        'aadhar_number' => trim((string)($input['aadhar_number'] ?? '')),
        'address' => trim((string)($input['address'] ?? '')),
        'emergency_contact' => trim((string)($input['emergency_contact'] ?? '')),
        'reporting_to' => $reportingTo,
        'is_active' => !empty($input['is_active']) ? 1 : 0,
    ];

    return [$payloadUser, $payloadProfile];
};

try {
    switch ($action) {
        case 'create':
            [$payloadUser, $payloadProfile] = $validatePayload($input);
            $pdo->beginTransaction();
            $newUserId = $db->insert('users', $payloadUser);
            $payloadProfile['user_id'] = $newUserId;
            $db->insert('employee_profiles', $payloadProfile);
            $pdo->commit();
            Helper::json(['success' => true, 'message' => 'Employee created.', 'id' => $newUserId]);

        case 'update':
            $userId = (int)($input['user_id'] ?? 0);
            $existing = $findEmployee($userId);
            if (!$existing) {
                Helper::json(['error' => 'Employee not found'], 404);
            }
            [$payloadUser, $payloadProfile] = $validatePayload($input, $existing);
            $pdo->beginTransaction();
            $db->update('users', $payloadUser, 'id=?', [$userId]);
            $db->update('employee_profiles', $payloadProfile, 'user_id=?', [$userId]);
            $pdo->commit();
            Helper::json(['success' => true, 'message' => 'Employee updated.']);

        case 'toggle_active':
            $userId = (int)($input['user_id'] ?? 0);
            $existing = $findEmployee($userId);
            if (!$existing) {
                Helper::json(['error' => 'Employee not found'], 404);
            }
            if ($userId === (int)Auth::id()) {
                Helper::json(['error' => 'You cannot disable your own employee account'], 409);
            }
            $next = $existing['is_active'] ? 0 : 1;
            $pdo->beginTransaction();
            $db->update('users', ['is_active' => $next], 'id=?', [$userId]);
            $db->update('employee_profiles', ['is_active' => $next], 'user_id=?', [$userId]);
            $pdo->commit();
            Helper::json(['success' => true, 'message' => $next ? 'Employee activated.' : 'Employee deactivated.']);

        case 'delete':
            $userId = (int)($input['user_id'] ?? 0);
            $existing = $findEmployee($userId);
            if (!$existing) {
                Helper::json(['error' => 'Employee not found'], 404);
            }
            if ($userId === (int)Auth::id()) {
                Helper::json(['error' => 'You cannot delete your own account'], 409);
            }
            $postCount = (int)($db->fetchOne("SELECT COUNT(*) AS c FROM posts WHERE user_id=?", [$userId])['c'] ?? 0);
            if ($postCount > 0) {
                Helper::json(['error' => 'This employee has posts attached and cannot be deleted directly'], 409);
            }
            $db->delete('users', 'id=?', [$userId]);
            Helper::json(['success' => true, 'message' => 'Employee deleted.']);

        default:
            Helper::json(['error' => 'Unknown action'], 400);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
