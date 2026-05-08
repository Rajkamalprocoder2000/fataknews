<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

Csrf::check();
Auth::requireRole('super_admin', 'admin', 'hr');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = trim((string)($input['action'] ?? ''));
$db = Database::getInstance();

$findDepartment = static function (int $id) use ($db): ?array {
    return $db->fetchOne("SELECT * FROM departments WHERE id=?", [$id]);
};

$buildPayload = static function (array $input, ?array $existing = null) use ($db): array {
    $name = trim((string)($input['name'] ?? ''));
    if ($name === '') {
        Helper::json(['error' => 'Department name is required'], 422);
    }

    $departmentId = (int)($existing['id'] ?? 0);
    $duplicate = $db->fetchOne("SELECT id FROM departments WHERE name=? AND id<>?", [$name, $departmentId]);
    if ($duplicate) {
        Helper::json(['error' => 'Department name is already in use'], 409);
    }

    $headId = isset($input['head_id']) && $input['head_id'] !== '' ? (int)$input['head_id'] : null;
    if ($headId !== null) {
        $head = $db->fetchOne(
            "SELECT u.id
             FROM users u
             JOIN employee_profiles ep ON ep.user_id=u.id
             WHERE u.id=?",
            [$headId]
        );
        if (!$head) {
            Helper::json(['error' => 'Selected department head is not a valid employee'], 404);
        }
    }

    return [
        'name' => $name,
        'head_id' => $headId,
        'description' => trim((string)($input['description'] ?? '')),
    ];
};

switch ($action) {
    case 'create':
        $payload = $buildPayload($input);
        $newId = $db->insert('departments', $payload);
        Helper::json(['success' => true, 'message' => 'Department created.', 'id' => $newId]);

    case 'update':
        $departmentId = (int)($input['department_id'] ?? 0);
        $existing = $findDepartment($departmentId);
        if (!$existing) {
            Helper::json(['error' => 'Department not found'], 404);
        }
        $payload = $buildPayload($input, $existing);
        $db->update('departments', $payload, 'id=?', [$departmentId]);
        Helper::json(['success' => true, 'message' => 'Department updated.']);

    case 'delete':
        $departmentId = (int)($input['department_id'] ?? 0);
        $existing = $findDepartment($departmentId);
        if (!$existing) {
            Helper::json(['error' => 'Department not found'], 404);
        }
        $employeeCount = (int)($db->fetchOne("SELECT COUNT(*) AS c FROM employee_profiles WHERE department_id=?", [$departmentId])['c'] ?? 0);
        if ($employeeCount > 0) {
            Helper::json(['error' => 'Move employees out of this department before deleting it'], 409);
        }
        $db->delete('departments', 'id=?', [$departmentId]);
        Helper::json(['success' => true, 'message' => 'Department deleted.']);

    default:
        Helper::json(['error' => 'Unknown action'], 400);
}
