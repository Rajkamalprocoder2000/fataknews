<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

Csrf::check();
Auth::requireLogin();

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$hrModel = new HrModel();
$action = trim((string)($input['action'] ?? ''));
$db = Database::getInstance();

$createLeavePayload = static function (int $userId, array $input): array {
    $required = ['leave_type_id', 'from_date', 'to_date', 'reason'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            Helper::json(['error' => "$field is required"], 400);
        }
    }

    $from = new DateTime((string)$input['from_date']);
    $to = new DateTime((string)$input['to_date']);
    if ($to < $from) {
        Helper::json(['error' => 'To date cannot be earlier than from date'], 422);
    }

    $days = max(1, $from->diff($to)->days + 1);

    return [
        'user_id' => $userId,
        'leave_type_id' => (int)$input['leave_type_id'],
        'from_date' => (string)$input['from_date'],
        'to_date' => (string)$input['to_date'],
        'days' => $days,
        'reason' => trim((string)$input['reason']),
    ];
};

if ($action === 'apply') {
    $payload = $createLeavePayload((int)Auth::id(), $input);
    $id = $hrModel->applyLeave($payload);
    Helper::json(['success' => true, 'message' => 'Leave application submitted!', 'id' => $id]);
}

if ($action === 'create_request') {
    Auth::requireRole('super_admin', 'admin', 'hr', 'manager');
    $userId = (int)($input['user_id'] ?? 0);
    if ($userId <= 0 || !$db->fetchOne("SELECT id FROM users WHERE id=?", [$userId])) {
        Helper::json(['error' => 'Employee not found'], 404);
    }
    $payload = $createLeavePayload($userId, $input);
    $id = $hrModel->applyLeave($payload);
    Helper::json(['success' => true, 'message' => 'Leave request created.', 'id' => $id]);
}

if (in_array($action, ['approve_leave', 'reject_leave'], true)) {
    Auth::requireRole('super_admin', 'admin', 'hr', 'manager');
    $id = (int)($input['leave_id'] ?? 0);
    if ($id <= 0) {
        Helper::json(['error' => 'Leave request not found'], 404);
    }
    if ($action === 'approve_leave') {
        $hrModel->approveLeave($id, (int)Auth::id());
        if (!empty($input['remarks'])) {
            $db->update('leaves', ['remarks' => trim((string)$input['remarks'])], 'id=?', [$id]);
        }
        Helper::json(['success' => true, 'message' => 'Leave approved!']);
    }

    $db->update(
        'leaves',
        ['status' => 'rejected', 'approved_by' => Auth::id(), 'remarks' => trim((string)($input['remarks'] ?? ''))],
        'id=?',
        [$id]
    );
    Helper::json(['success' => true, 'message' => 'Leave rejected.']);
}

if ($action === 'cancel_own') {
    $id = (int)($input['leave_id'] ?? 0);
    if ($id <= 0) {
        Helper::json(['error' => 'Leave request not found'], 404);
    }

    $leave = $db->fetchOne("SELECT * FROM leaves WHERE id=? AND user_id=?", [$id, Auth::id()]);
    if (!$leave) {
        Helper::json(['error' => 'Leave request not found'], 404);
    }
    if ($leave['status'] !== 'pending') {
        Helper::json(['error' => 'Only pending leave requests can be cancelled'], 409);
    }

    $db->delete('leaves', 'id=?', [$id]);
    Helper::json(['success' => true, 'message' => 'Leave request cancelled.']);
}

Helper::json(['error' => 'Unknown action'], 400);
