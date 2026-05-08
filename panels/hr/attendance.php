<?php
require_once BASE_PATH . '/includes/bootstrap.php';

Auth::requireRole('super_admin', 'admin', 'hr');

$pageTitle = 'Attendance - FatakNews';
$db = Database::getInstance();
$editId = max(0, (int)($_GET['edit'] ?? 0));
$dateFilter = trim((string)($_GET['date'] ?? date('Y-m-d')));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$query = trim((string)($_GET['q'] ?? ''));

$employeeOptions = $db->fetchAll(
    "SELECT u.id, u.full_name, ep.employee_code
     FROM employee_profiles ep
     JOIN users u ON u.id=ep.user_id
     ORDER BY u.full_name ASC"
);

$where = ['a.date=?'];
$params = [$dateFilter];
if ($statusFilter !== '') {
    $where[] = 'a.status=?';
    $params[] = $statusFilter;
}
if ($query !== '') {
    $where[] = '(u.full_name LIKE ? OR ep.employee_code LIKE ? OR u.email LIKE ?)';
    $like = '%' . $query . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$records = $db->fetchAll(
    "SELECT a.*, u.full_name, u.email, u.avatar, ep.employee_code, ep.designation, d.name AS dept_name
     FROM attendance a
     JOIN users u ON u.id=a.user_id
     LEFT JOIN employee_profiles ep ON ep.user_id=u.id
     LEFT JOIN departments d ON d.id=ep.department_id
     $whereSql
     ORDER BY FIELD(a.status,'late','present','half_day','leave','holiday','absent'), a.check_in ASC, u.full_name ASC",
    $params
);

$editRecord = $editId > 0
    ? $db->fetchOne(
        "SELECT a.*, u.full_name, ep.employee_code
         FROM attendance a
         JOIN users u ON u.id=a.user_id
         LEFT JOIN employee_profiles ep ON ep.user_id=u.id
         WHERE a.id=?",
        [$editId]
    )
    : null;

$stats = [
    'present' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM attendance WHERE date=? AND status='present'", [$dateFilter])['c'] ?? 0),
    'late' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM attendance WHERE date=? AND status='late'", [$dateFilter])['c'] ?? 0),
    'half_day' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM attendance WHERE date=? AND status='half_day'", [$dateFilter])['c'] ?? 0),
    'absent' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM attendance WHERE date=? AND status='absent'", [$dateFilter])['c'] ?? 0),
];
$stats['total_marked'] = array_sum($stats);

$weekLabels = [];
$weekCounts = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $weekLabels[] = date('D', strtotime($date));
    $weekCounts[] = (int)($db->fetchOne(
        "SELECT COUNT(*) AS c FROM attendance WHERE date=? AND status IN ('present','late','half_day')",
        [$date]
    )['c'] ?? 0);
}

$defaultRecord = [
    'id' => 0,
    'user_id' => '',
    'date' => $dateFilter,
    'check_in' => '',
    'check_out' => '',
    'status' => 'present',
    'notes' => '',
];
$formRecord = array_merge($defaultRecord, $editRecord ?? []);
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
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
      <a href="/hr/attendance" class="panel-nav-link active"><i class="fa fa-clock"></i> Attendance</a>
      <a href="/hr/leaves" class="panel-nav-link"><i class="fa fa-calendar-times"></i> Leave Requests</a>
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
        <h1>Attendance</h1>
        <p style="color:var(--muted);font-size:13px;margin-top:2px">Track daily presence, update late check-ins, and correct attendance logs.</p>
      </div>
      <div style="display:flex;gap:10px;align-items:center">
        <?php if ($editRecord): ?>
        <a href="/hr/attendance?date=<?= urlencode($dateFilter) ?>" class="btn-sm btn-edit">New Record</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,200,83,0.15);color:var(--green)"><i class="fa fa-user-check"></i></div>
        <div class="stat-info"><strong><?= $stats['present'] ?></strong><span>Present</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,215,0,0.15);color:var(--yellow)"><i class="fa fa-hourglass-half"></i></div>
        <div class="stat-info"><strong><?= $stats['late'] ?></strong><span>Late</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(41,121,255,0.15);color:var(--blue)"><i class="fa fa-adjust"></i></div>
        <div class="stat-info"><strong><?= $stats['half_day'] ?></strong><span>Half Day</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,45,45,0.15);color:var(--red)"><i class="fa fa-user-times"></i></div>
        <div class="stat-info"><strong><?= $stats['absent'] ?></strong><span>Absent</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,188,212,0.15);color:#00BCD4"><i class="fa fa-list-check"></i></div>
        <div class="stat-info"><strong><?= $stats['total_marked'] ?></strong><span>Records on <?= Helper::sanitize($dateFilter) ?></span></div>
      </div>
    </div>

    <div class="panel-grid-2">
      <div class="data-table-wrap">
        <div class="table-header">
          <h3><?= $editRecord ? 'Edit Attendance' : 'Add Attendance' ?></h3>
        </div>
        <form id="attendanceForm" style="padding:20px;display:flex;flex-direction:column;gap:14px">
          <input type="hidden" name="attendance_id" value="<?= (int)$formRecord['id'] ?>">
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Employee</label>
            <select name="user_id" class="form-control" required>
              <option value="">Select employee</option>
              <?php foreach ($employeeOptions as $employee): ?>
              <option value="<?= (int)$employee['id'] ?>" <?= (string)$formRecord['user_id'] === (string)$employee['id'] ? 'selected' : '' ?>>
                <?= Helper::sanitize($employee['full_name']) ?> (<?= Helper::sanitize($employee['employee_code']) ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="panel-split-2">
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Date</label>
              <input type="date" name="date" class="form-control" value="<?= Helper::sanitize($formRecord['date']) ?>" required>
            </div>
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Status</label>
              <select name="status" class="form-control">
                <?php foreach (['present','late','half_day','absent','leave','holiday'] as $status): ?>
                <option value="<?= $status ?>" <?= $formRecord['status'] === $status ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $status)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="panel-split-2">
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Check In</label>
              <input type="time" name="check_in" class="form-control" value="<?= Helper::sanitize(substr((string)$formRecord['check_in'], 0, 5)) ?>">
            </div>
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Check Out</label>
              <input type="time" name="check_out" class="form-control" value="<?= Helper::sanitize(substr((string)$formRecord['check_out'], 0, 5)) ?>">
            </div>
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Notes</label>
            <textarea name="notes" class="form-control" rows="4"><?= Helper::sanitize($formRecord['notes']) ?></textarea>
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button type="submit" class="btn-sm btn-approve"><?= $editRecord ? 'Update Record' : 'Save Record' ?></button>
            <?php if ($editRecord): ?>
            <a href="/hr/attendance?date=<?= urlencode($dateFilter) ?>" class="btn-sm btn-edit">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div style="display:flex;flex-direction:column;gap:20px">
        <div class="data-table-wrap">
          <div class="table-header"><h3>Weekly Presence Trend</h3></div>
          <div style="padding:20px"><canvas id="attendanceTrendChart" height="160"></canvas></div>
        </div>

        <div class="data-table-wrap">
          <div class="table-header" style="gap:14px;flex-wrap:wrap">
            <h3>Daily Attendance Report</h3>
            <form method="get" action="/hr/attendance" class="panel-filter-form">
              <input type="date" name="date" value="<?= Helper::sanitize($dateFilter) ?>" class="form-control">
              <select name="status" class="form-control">
                <option value="">Any status</option>
                <?php foreach (['present','late','half_day','absent','leave','holiday'] as $status): ?>
                <option value="<?= $status ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $status)) ?></option>
                <?php endforeach; ?>
              </select>
              <input type="text" name="q" value="<?= Helper::sanitize($query) ?>" class="form-control" placeholder="Search employee...">
              <button type="submit" class="btn-sm btn-approve">Apply</button>
              <a href="/hr/attendance?date=<?= urlencode(date('Y-m-d')) ?>" class="btn-sm btn-edit">Reset</a>
            </form>
          </div>
          <div style="overflow-x:auto">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Employee</th>
                  <th>Department</th>
                  <th>Time</th>
                  <th>Status</th>
                  <th>Hours</th>
                  <th>Notes</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($records)): ?>
                <?php foreach ($records as $record): ?>
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;gap:12px">
                      <img src="<?= Helper::avatarUrl($record['avatar'] ?? null) ?>" style="width:38px;height:38px;border-radius:50%;object-fit:cover">
                      <div>
                        <div style="font-weight:700"><?= Helper::sanitize($record['full_name']) ?></div>
                        <div style="font-size:12px;color:var(--muted)"><?= Helper::sanitize($record['employee_code'] ?? '') ?></div>
                      </div>
                    </div>
                  </td>
                  <td style="font-size:13px">
                    <div><?= Helper::sanitize($record['dept_name'] ?? 'Unassigned') ?></div>
                    <div style="font-size:12px;color:var(--muted)"><?= Helper::sanitize($record['designation'] ?? '') ?></div>
                  </td>
                  <td style="font-size:12px;color:var(--muted)">
                    <div>In: <?= $record['check_in'] ?: 'N/A' ?></div>
                    <div>Out: <?= $record['check_out'] ?: 'N/A' ?></div>
                  </td>
                  <td><span class="status-badge status-<?= $record['status'] === 'present' ? 'published' : ($record['status'] === 'late' || $record['status'] === 'half_day' ? 'pending' : ($record['status'] === 'absent' ? 'rejected' : 'draft')) ?>"><?= ucwords(str_replace('_', ' ', $record['status'])) ?></span></td>
                  <td style="font-size:13px"><?= $record['work_hours'] !== null ? Helper::sanitize((string)$record['work_hours']) . 'h' : 'N/A' ?></td>
                  <td style="font-size:12px;color:var(--muted);max-width:170px"><?= Helper::sanitize($record['notes'] ?? '') ?></td>
                  <td>
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                      <a href="/hr/attendance?date=<?= urlencode($dateFilter) ?>&edit=<?= (int)$record['id'] ?>" class="btn-sm btn-edit">Edit</a>
                      <button class="btn-sm btn-delete" data-confirm="Delete this attendance record?" onclick="attendanceAction('delete_record', <?= (int)$record['id'] ?>)">Delete</button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                  <td colspan="7">
                    <div class="empty-state" style="padding:28px 0">
                      <i class="fa fa-clock"></i>
                      <h3>No attendance records found</h3>
                      <p>Use the form to add the first entry for this date.</p>
                    </div>
                  </td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
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
const trendCanvas = document.getElementById('attendanceTrendChart');
if (trendCanvas && typeof Chart !== 'undefined') {
  new Chart(trendCanvas, {
    type: 'bar',
    data: {
      labels: <?= json_encode($weekLabels) ?>,
      datasets: [{
        label: 'Present or Working',
        data: <?= json_encode($weekCounts) ?>,
        backgroundColor: 'rgba(0,188,212,0.75)',
        borderColor: '#00BCD4',
        borderWidth: 1,
        borderRadius: 8
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        x: { ticks: { color: '#A7A7BA' }, grid: { display: false } },
        y: { ticks: { color: '#A7A7BA', stepSize: 1 }, grid: { color: 'rgba(255,255,255,0.04)' } }
      }
    }
  });
}

document.getElementById('attendanceForm').addEventListener('submit', async (event) => {
  event.preventDefault();

  const form = event.currentTarget;
  const formData = new FormData(form);
  const payload = {
    action: 'upsert_record',
    attendance_id: Number(formData.get('attendance_id') || 0),
    user_id: Number(formData.get('user_id') || 0),
    date: formData.get('date'),
    status: formData.get('status'),
    check_in: formData.get('check_in'),
    check_out: formData.get('check_out'),
    notes: formData.get('notes')
  };

  const data = await API.post('/api/hr/attendance', payload);
  if (!data.success) {
    Toast.show(data.error || 'Action failed', 'error');
    return;
  }

  Toast.show(data.message || 'Saved', 'success');
  setTimeout(() => window.location.href = '/hr/attendance?date=' + encodeURIComponent(payload.date), 700);
});

async function attendanceAction(action, attendanceId) {
  const data = await API.post('/api/hr/attendance', { action, attendance_id: attendanceId });
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
