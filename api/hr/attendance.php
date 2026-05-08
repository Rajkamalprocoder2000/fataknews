<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

Csrf::check();
Auth::requireLogin();

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = trim((string)($input['action'] ?? 'mark_self'));
$db = Database::getInstance();
$hrModel = new HrModel();

$allowedStatuses = ['present', 'absent', 'late', 'half_day', 'holiday', 'leave'];

switch ($action) {
    case 'mark_self':
        $status = in_array($input['status'] ?? '', ['present', 'late', 'half_day'], true) ? $input['status'] : 'present';
        $hrModel->markAttendance(Auth::id(), $status, date('H:i:s'));
        Helper::json(['success' => true, 'message' => 'Attendance marked: ' . ucfirst(str_replace('_', ' ', $status))]);

    case 'upsert_record':
        Auth::requireRole('super_admin', 'admin', 'hr');
        $userId = (int)($input['user_id'] ?? 0);
        $date = trim((string)($input['date'] ?? ''));
        $status = trim((string)($input['status'] ?? 'present'));
        $checkIn = trim((string)($input['check_in'] ?? ''));
        $checkOut = trim((string)($input['check_out'] ?? ''));
        $notes = trim((string)($input['notes'] ?? ''));

        if ($userId <= 0 || $date === '') {
            Helper::json(['error' => 'Employee and date are required'], 422);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            Helper::json(['error' => 'Date must be valid'], 422);
        }
        if (!in_array($status, $allowedStatuses, true)) {
            Helper::json(['error' => 'Invalid attendance status'], 422);
        }
        if ($checkIn !== '' && !preg_match('/^\d{2}:\d{2}$/', $checkIn) && !preg_match('/^\d{2}:\d{2}:\d{2}$/', $checkIn)) {
            Helper::json(['error' => 'Check-in time must be valid'], 422);
        }
        if ($checkOut !== '' && !preg_match('/^\d{2}:\d{2}$/', $checkOut) && !preg_match('/^\d{2}:\d{2}:\d{2}$/', $checkOut)) {
            Helper::json(['error' => 'Check-out time must be valid'], 422);
        }
        if (!$db->fetchOne("SELECT id FROM users WHERE id=?", [$userId])) {
            Helper::json(['error' => 'Employee not found'], 404);
        }

        $checkIn = $checkIn !== '' && strlen($checkIn) === 5 ? $checkIn . ':00' : $checkIn;
        $checkOut = $checkOut !== '' && strlen($checkOut) === 5 ? $checkOut . ':00' : $checkOut;
        $workHours = null;
        if ($checkIn !== '' && $checkOut !== '') {
            $start = strtotime($date . ' ' . $checkIn);
            $end = strtotime($date . ' ' . $checkOut);
            if ($start !== false && $end !== false && $end >= $start) {
                $workHours = round(($end - $start) / 3600, 2);
            }
        }

        $existing = $db->fetchOne("SELECT id FROM attendance WHERE user_id=? AND date=?", [$userId, $date]);
        $payload = [
            'check_in' => $checkIn !== '' ? $checkIn : null,
            'check_out' => $checkOut !== '' ? $checkOut : null,
            'status' => $status,
            'work_hours' => $workHours,
            'notes' => $notes !== '' ? $notes : null,
        ];

        if ($existing) {
            $db->update('attendance', $payload, 'id=?', [$existing['id']]);
            Helper::json(['success' => true, 'message' => 'Attendance record updated.']);
        }

        $payload['user_id'] = $userId;
        $payload['date'] = $date;
        $db->insert('attendance', $payload);
        Helper::json(['success' => true, 'message' => 'Attendance record created.']);

    case 'delete_record':
        Auth::requireRole('super_admin', 'admin', 'hr');
        $attendanceId = (int)($input['attendance_id'] ?? 0);
        if ($attendanceId <= 0) {
            Helper::json(['error' => 'Attendance record not found'], 404);
        }
        $db->delete('attendance', 'id=?', [$attendanceId]);
        Helper::json(['success' => true, 'message' => 'Attendance record deleted.']);

    default:
        Helper::json(['error' => 'Unknown action'], 400);
}
