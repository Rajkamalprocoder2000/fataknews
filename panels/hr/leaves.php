<?php
require_once BASE_PATH . '/includes/bootstrap.php';

Auth::requireRole('super_admin', 'admin', 'hr');

$pageTitle = 'Leave Requests - FatakNews';
$db = Database::getInstance();
$statusFilter = trim((string)($_GET['status'] ?? ''));
$query = trim((string)($_GET['q'] ?? ''));

$employees = $db->fetchAll(
    "SELECT u.id, u.full_name, ep.employee_code
     FROM employee_profiles ep
     JOIN users u ON u.id=ep.user_id
     ORDER BY u.full_name ASC"
);
$leaveTypes = $db->fetchAll("SELECT id, name, days_allowed FROM leave_types ORDER BY name ASC");

$where = [];
$params = [];
if ($statusFilter !== '') {
    $where[] = 'l.status=?';
    $params[] = $statusFilter;
}
if ($query !== '') {
    $where[] = '(u.full_name LIKE ? OR lt.name LIKE ? OR l.reason LIKE ?)';
    $like = '%' . $query . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$leaves = $db->fetchAll(
    "SELECT l.*, u.full_name, u.avatar, ep.employee_code, ep.designation,
            lt.name AS leave_type, lt.days_allowed,
            approver.full_name AS approver_name
     FROM leaves l
     JOIN users u ON u.id=l.user_id
     LEFT JOIN employee_profiles ep ON ep.user_id=u.id
     JOIN leave_types lt ON lt.id=l.leave_type_id
     LEFT JOIN users approver ON approver.id=l.approved_by
     $whereSql
     ORDER BY FIELD(l.status,'pending','approved','rejected'), l.applied_at DESC",
    $params
);

$stats = [
    'pending' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM leaves WHERE status='pending'")['c'] ?? 0),
    'approved' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM leaves WHERE status='approved'")['c'] ?? 0),
    'rejected' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM leaves WHERE status='rejected'")['c'] ?? 0),
    'days' => (int)($db->fetchOne("SELECT COALESCE(SUM(days),0) AS c FROM leaves WHERE status IN ('pending','approved')")['c'] ?? 0),
];

function leaveFilterUrl(array $overrides = []): string {
    $query = array_merge($_GET, $overrides);
    $query = array_filter($query, fn($value) => $value !== '' && $value !== null);
    return '/hr/leaves' . ($query ? '?' . http_build_query($query) : '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?></title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/public/assets/css/app.css">
</head>
<body>
<div class="panel-layout">
  <aside class="panel-sidebar" id="panelSidebar">
    <div class="panel-logo">
      <div class="nav-logo" style="padding:0">
        <div class="logo-icon"><i class="fa fa-bolt"></i></div>
        <span class="logo-text" style="font-family:'Space Grotesk',sans-serif;font-size:18px">Fatak<strong>News</strong></span>
      </div>
      <div style="font-size:11px;color:#00BCD4;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-top:6px">HR Panel</div>
    </div>
    <nav class="panel-nav">
      <div class="panel-nav-section">HR Overview</div>
      <a href="/hr" class="panel-nav-link"><i class="fa fa-th-large"></i> Dashboard</a>
      <div class="panel-nav-section">People</div>
      <a href="/hr/employees" class="panel-nav-link"><i class="fa fa-users"></i> Employees</a>
      <a href="/hr/departments" class="panel-nav-link"><i class="fa fa-sitemap"></i> Departments</a>
      <div class="panel-nav-section">Attendance</div>
      <a href="/hr/attendance" class="panel-nav-link"><i class="fa fa-clock"></i> Attendance</a>
      <a href="/hr/leaves" class="panel-nav-link active"><i class="fa fa-calendar-times"></i> Leave Requests</a>
      <div class="panel-nav-section">Payroll</div>
      <a href="/hr/payroll" class="panel-nav-link"><i class="fa fa-money-bill-wave"></i> Payroll</a>
      <div class="panel-nav-section">System</div>
      <a href="/admin" class="panel-nav-link"><i class="fa fa-shield-alt"></i> Admin Panel</a>
      <a href="/" class="panel-nav-link" target="_blank"><i class="fa fa-external-link-alt"></i> View Site</a>
      <a href="/logout" class="panel-nav-link" style="color:var(--red)"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </nav>
  </aside>

  <main class="panel-main">
    <div class="panel-header">
      <div>
        <h1>Leave Requests</h1>
        <p style="color:var(--muted);font-size:13px;margin-top:2px">Review pending leave applications and log new requests for employees.</p>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,215,0,0.15);color:var(--yellow)"><i class="fa fa-clock"></i></div>
        <div class="stat-info"><strong><?= $stats['pending'] ?></strong><span>Pending</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,200,83,0.15);color:var(--green)"><i class="fa fa-check-circle"></i></div>
        <div class="stat-info"><strong><?= $stats['approved'] ?></strong><span>Approved</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,45,45,0.15);color:var(--red)"><i class="fa fa-times-circle"></i></div>
        <div class="stat-info"><strong><?= $stats['rejected'] ?></strong><span>Rejected</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,188,212,0.15);color:#00BCD4"><i class="fa fa-calendar-days"></i></div>
        <div class="stat-info"><strong><?= $stats['days'] ?></strong><span>Total Days Requested</span></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:minmax(340px,430px) 1fr;gap:20px;align-items:start">
      <div class="data-table-wrap">
        <div class="table-header"><h3>Create Leave Request</h3></div>
        <form id="leaveForm" style="padding:20px;display:flex;flex-direction:column;gap:14px">
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Employee</label>
            <select name="user_id" class="form-control" required>
              <option value="">Select employee</option>
              <?php foreach ($employees as $employee): ?>
              <option value="<?= (int)$employee['id'] ?>"><?= Helper::sanitize($employee['full_name']) ?> (<?= Helper::sanitize($employee['employee_code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Leave Type</label>
            <select name="leave_type_id" class="form-control" required>
              <option value="">Select leave type</option>
              <?php foreach ($leaveTypes as $type): ?>
              <option value="<?= (int)$type['id'] ?>"><?= Helper::sanitize($type['name']) ?> (<?= (int)$type['days_allowed'] ?> days)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">From Date</label>
              <input type="date" name="from_date" class="form-control" required>
            </div>
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">To Date</label>
              <input type="date" name="to_date" class="form-control" required>
            </div>
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Reason</label>
            <textarea name="reason" class="form-control" rows="5" required></textarea>
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button type="submit" class="btn-sm btn-approve">Create Request</button>
          </div>
        </form>
      </div>

      <div class="data-table-wrap">
        <div class="table-header" style="gap:14px;flex-wrap:wrap">
          <h3>Leave Queue</h3>
          <form method="get" action="/hr/leaves" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <input type="text" name="q" value="<?= Helper::sanitize($query) ?>" class="form-control" placeholder="Search employee or leave type..." style="width:240px">
            <select name="status" class="form-control" style="width:160px">
              <option value="">Any status</option>
              <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
              <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
              <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
            <button type="submit" class="btn-sm btn-approve">Apply</button>
            <a href="/hr/leaves" class="btn-sm btn-edit">Reset</a>
          </form>
        </div>
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap">
          <a href="<?= leaveFilterUrl(['status' => null, 'q' => null]) ?>" class="tag-chip" style="<?= $statusFilter === '' && $query === '' ? 'background:rgba(0,188,212,0.14);color:#00BCD4' : '' ?>">All</a>
          <a href="<?= leaveFilterUrl(['status' => 'pending']) ?>" class="tag-chip" style="<?= $statusFilter === 'pending' ? 'background:rgba(255,215,0,0.14);color:var(--yellow)' : '' ?>">Pending</a>
          <a href="<?= leaveFilterUrl(['status' => 'approved']) ?>" class="tag-chip" style="<?= $statusFilter === 'approved' ? 'background:rgba(0,200,83,0.14);color:var(--green)' : '' ?>">Approved</a>
          <a href="<?= leaveFilterUrl(['status' => 'rejected']) ?>" class="tag-chip" style="<?= $statusFilter === 'rejected' ? 'background:rgba(255,45,45,0.14);color:var(--red)' : '' ?>">Rejected</a>
        </div>
        <div style="overflow-x:auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Employee</th>
                <th>Leave</th>
                <th>Duration</th>
                <th>Status</th>
                <th>Reason</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($leaves)): ?>
              <?php foreach ($leaves as $leave): ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:12px">
                    <img src="<?= Helper::avatarUrl($leave['avatar'] ?? null) ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover">
                    <div>
                      <div style="font-weight:700"><?= Helper::sanitize($leave['full_name']) ?></div>
                      <div style="font-size:12px;color:var(--muted)"><?= Helper::sanitize($leave['employee_code'] ?? '') ?> · <?= Helper::sanitize($leave['designation'] ?? '') ?></div>
                    </div>
                  </div>
                </td>
                <td style="font-size:13px">
                  <div style="font-weight:700"><?= Helper::sanitize($leave['leave_type']) ?></div>
                  <div style="font-size:12px;color:var(--muted)">Allowance <?= (int)$leave['days_allowed'] ?> days</div>
                </td>
                <td style="font-size:12px;color:var(--muted)">
                  <div><?= Helper::sanitize($leave['from_date']) ?> to <?= Helper::sanitize($leave['to_date']) ?></div>
                  <div><?= (int)$leave['days'] ?> day(s)</div>
                </td>
                <td>
                  <span class="status-badge status-<?= $leave['status'] === 'approved' ? 'published' : ($leave['status'] === 'pending' ? 'pending' : 'rejected') ?>">
                    <?= ucfirst($leave['status']) ?>
                  </span>
                  <?php if (!empty($leave['approver_name'])): ?>
                  <div style="font-size:12px;color:var(--muted);margin-top:6px">By <?= Helper::sanitize($leave['approver_name']) ?></div>
                  <?php endif; ?>
                </td>
                <td style="font-size:12px;color:var(--muted);max-width:220px">
                  <div><?= Helper::sanitize($leave['reason'] ?? '') ?></div>
                  <?php if (!empty($leave['remarks'])): ?>
                  <div style="margin-top:6px;color:var(--text)">Remarks: <?= Helper::sanitize($leave['remarks']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <?php if ($leave['status'] === 'pending'): ?>
                    <button class="btn-sm btn-approve" onclick="leaveAction('approve_leave', <?= (int)$leave['id'] ?>)">Approve</button>
                    <button class="btn-sm btn-reject" onclick="leaveAction('reject_leave', <?= (int)$leave['id'] ?>)">Reject</button>
                    <?php else: ?>
                    <span class="btn-sm btn-edit" style="cursor:default">
                      <?= $leave['status'] === 'approved' ? 'Closed' : 'Reviewed' ?>
                    </span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php else: ?>
              <tr>
                <td colspan="6">
                  <div class="empty-state" style="padding:28px 0">
                    <i class="fa fa-calendar-times"></i>
                    <h3>No leave requests found</h3>
                    <p>Adjust the filters or create a new request from the form.</p>
                  </div>
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<div class="toast-container" id="toastContainer"></div>
<script>
const APP = { url:'<?= Helper::appUrl() ?>', csrfToken:'<?= Csrf::token() ?>', userId:<?= Auth::id() ?>, isLoggedIn:true };
</script>
<script src="/public/assets/js/app.js"></script>
<script>
document.getElementById('leaveForm').addEventListener('submit', async (event) => {
  event.preventDefault();

  const form = event.currentTarget;
  const formData = new FormData(form);
  const payload = {
    action: 'create_request',
    user_id: Number(formData.get('user_id') || 0),
    leave_type_id: Number(formData.get('leave_type_id') || 0),
    from_date: formData.get('from_date'),
    to_date: formData.get('to_date'),
    reason: formData.get('reason')
  };

  const data = await API.post('/api/hr/leaves', payload);
  if (!data.success) {
    Toast.show(data.error || 'Action failed', 'error');
    return;
  }

  Toast.show(data.message || 'Saved', 'success');
  setTimeout(() => window.location.reload(), 700);
});

async function leaveAction(action, leaveId) {
  const remarks = prompt(action === 'approve_leave' ? 'Approval remarks (optional):' : 'Rejection reason (optional):') ?? '';
  const data = await API.post('/api/hr/leaves', { action, leave_id: leaveId, remarks });
  if (!data.success) {
    Toast.show(data.error || 'Action failed', 'error');
    return;
  }

  Toast.show(data.message || 'Updated', 'success');
  setTimeout(() => window.location.reload(), 700);
}
</script>
</body>
</html>
