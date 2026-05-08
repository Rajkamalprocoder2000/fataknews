<?php
// app/models/HrModel.php
class HrModel extends Model {
    protected string $table = 'employee_profiles';

    public function getEmployees(int $page = 1, ?int $deptId = null): array {
        $where = $deptId ? "WHERE ep.department_id=?" : "";
        $params = $deptId ? [$deptId] : [];

        return $this->db->paginate(
            "SELECT u.id, u.full_name, u.email, u.avatar, u.is_active,
                    ep.designation, ep.employee_code, ep.joining_date,
                    d.name AS dept_name, r.name AS role_name
             FROM users u
             JOIN employee_profiles ep ON u.id=ep.user_id
             LEFT JOIN departments d ON ep.department_id=d.id
             JOIN roles r ON u.role_id=r.id $where
             ORDER BY u.full_name",
            $params,
            $page
        );
    }

    public function applyLeave(array $data) {
        return $this->db->insert('leaves', $data);
    }

    public function getLeaves(int $userId, ?string $status = null): array {
        $where = $status ? "AND l.status=?" : "";
        $params = [$userId];
        if ($status) {
            $params[] = $status;
        }

        return $this->db->fetchAll(
            "SELECT l.*, lt.name AS leave_type FROM leaves l
             JOIN leave_types lt ON l.leave_type_id=lt.id
             WHERE l.user_id=? $where ORDER BY l.applied_at DESC",
            $params
        );
    }

    public function getPendingLeaves(): array {
        return $this->db->fetchAll(
            "SELECT l.*, u.full_name, u.avatar, lt.name AS leave_type
             FROM leaves l JOIN users u ON l.user_id=u.id JOIN leave_types lt ON l.leave_type_id=lt.id
             WHERE l.status='pending' ORDER BY l.applied_at ASC"
        );
    }

    public function approveLeave(int $id, int $approverId): void {
        $this->db->update('leaves', ['status'=>'approved','approved_by'=>$approverId], 'id=?', [$id]);
    }

    public function markAttendance(int $userId, string $status, ?string $checkIn = null): void {
        $today = date('Y-m-d');
        $existing = $this->db->fetchOne("SELECT id FROM attendance WHERE user_id=? AND date=?", [$userId, $today]);

        if ($existing) {
            $this->db->update('attendance', ['status'=>$status,'check_out'=>date('H:i:s')], 'id=?', [$existing['id']]);
            return;
        }

        $this->db->insert('attendance', [
            'user_id'=>$userId,
            'date'=>$today,
            'status'=>$status,
            'check_in'=>$checkIn ?? date('H:i:s'),
        ]);
    }

    public function getDashboardStats(): array {
        return [
            'total_employees' => (int)$this->db->fetchOne("SELECT COUNT(*) c FROM employee_profiles WHERE is_active=1")['c'],
            'pending_leaves' => (int)$this->db->fetchOne("SELECT COUNT(*) c FROM leaves WHERE status='pending'")['c'],
            'present_today' => (int)$this->db->fetchOne("SELECT COUNT(*) c FROM attendance WHERE date=CURDATE() AND status='present'")['c'],
            'departments' => $this->db->fetchAll(
                "SELECT d.name, COUNT(ep.user_id) cnt
                 FROM departments d
                 LEFT JOIN employee_profiles ep ON d.id=ep.department_id
                 GROUP BY d.id"
            ),
        ];
    }
}
