<?php
require_once BASE_PATH . '/includes/bootstrap.php';
Auth::requireRole('super_admin','admin','hr');
$pageTitle = 'HR Dashboard â€” FatakNews';
$hrModel   = new HrModel();
$stats     = $hrModel->getDashboardStats();
$pending   = $hrModel->getPendingLeaves();
$db        = Database::getInstance();
$recentAtt = $db->fetchAll(
  "SELECT a.*, u.full_name, u.avatar FROM attendance a
   JOIN users u ON a.user_id=u.id
   WHERE a.date=CURDATE() ORDER BY a.check_in DESC LIMIT 15"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $pageTitle ?></title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/public/assets/css/app.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<body>
<div class="panel-layout">
  <!-- Sidebar -->
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
      <a href="/hr" class="panel-nav-link active"><i class="fa fa-th-large"></i> Dashboard</a>
      <div class="panel-nav-section">People</div>
      <a href="/hr/employees" class="panel-nav-link"><i class="fa fa-users"></i> Employees</a>
      <a href="/hr/departments" class="panel-nav-link"><i class="fa fa-sitemap"></i> Departments</a>
      <div class="panel-nav-section">Attendance</div>
      <a href="/hr/attendance" class="panel-nav-link"><i class="fa fa-clock"></i> Attendance</a>
      <a href="/hr/leaves" class="panel-nav-link">
        <i class="fa fa-calendar-times"></i> Leave Requests
        <?php if (count($pending)): ?>
        <span style="margin-left:auto;background:var(--yellow);color:#000;font-size:10px;padding:2px 7px;border-radius:50px;font-weight:700"><?= count($pending) ?></span>
        <?php endif; ?>
      </a>
      <div class="panel-nav-section">Payroll</div>
      <a href="/hr/payroll" class="panel-nav-link"><i class="fa fa-money-bill-wave"></i> Payroll</a>
      <div class="panel-nav-section">System</div>
      <a href="/admin" class="panel-nav-link"><i class="fa fa-shield-alt"></i> Admin Panel</a>
      <a href="/" class="panel-nav-link" target="_blank"><i class="fa fa-external-link-alt"></i> View Site</a>
      <a href="/logout" class="panel-nav-link" style="color:var(--red)"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="panel-main">
    <div class="panel-header">
      <div>
        <h1>HR Dashboard</h1>
        <p style="color:var(--muted);font-size:13px;margin-top:2px"><?= date('l, d F Y') ?> - Good <?= date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening') ?>!</p>
      </div>
      <a href="/hr/employees?action=add" class="btn-write"><i class="fa fa-user-plus"></i> Add Employee</a>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,188,212,0.15);color:#00BCD4"><i class="fa fa-users"></i></div>
        <div class="stat-info">
          <strong><?= $stats['total_employees'] ?></strong>
          <span>Total Employees</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,200,83,0.15);color:var(--green)"><i class="fa fa-user-check"></i></div>
        <div class="stat-info">
          <strong><?= $stats['present_today'] ?></strong>
          <span>Present Today</span>
          <span class="stat-trend" style="color:var(--green)">
            <?php
              $rate = $stats['total_employees'] > 0 ? round(($stats['present_today']/$stats['total_employees'])*100) : 0;
              echo $rate . '% attendance rate';
            ?>
          </span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,215,0,0.15);color:var(--yellow)"><i class="fa fa-calendar-times"></i></div>
        <div class="stat-info">
          <strong><?= $stats['pending_leaves'] ?></strong>
          <span>Pending Leaves</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,45,45,0.15);color:var(--red)"><i class="fa fa-user-minus"></i></div>
        <div class="stat-info">
          <strong><?= $stats['total_employees'] - $stats['present_today'] ?></strong>
          <span>Absent Today</span>
        </div>
      </div>
    </div>

    <!-- 2 Column Layout -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">

      <!-- Department Breakdown -->
      <div class="data-table-wrap">
        <div class="table-header"><h3>Department Strength</h3></div>
        <div style="padding:20px">
          <?php foreach ($stats['departments'] as $dept): ?>
          <div style="margin-bottom:16px">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">
              <span style="font-weight:600"><?= Helper::sanitize($dept['name']) ?></span>
              <span style="color:var(--muted)"><?= $dept['cnt'] ?> employees</span>
            </div>
            <div style="height:6px;background:var(--bg3);border-radius:3px;overflow:hidden">
              <?php $pct = $stats['total_employees'] > 0 ? ($dept['cnt']/$stats['total_employees'])*100 : 0; ?>
              <div style="height:100%;width:<?= $pct ?>%;background:var(--red);border-radius:3px;transition:width 0.8s ease"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Attendance Chart -->
      <div class="data-table-wrap">
        <div class="table-header"><h3>Weekly Attendance</h3></div>
        <div style="padding:20px"><canvas id="attChart" height="160"></canvas></div>
      </div>
    </div>

    <!-- Pending Leaves -->
    <?php if (!empty($pending)): ?>
    <div class="data-table-wrap" style="margin-bottom:24px">
      <div class="table-header">
        <h3><i class="fa fa-clock" style="color:var(--yellow)"></i> Pending Leave Requests</h3>
        <a href="/hr/leaves" style="font-size:13px;color:var(--red)">View all</a>
      </div>
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr><th>Employee</th><th>Leave Type</th><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($pending as $leave): ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <img src="<?= Helper::avatarUrl($leave['avatar']) ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover">
                  <span style="font-size:13px;font-weight:600"><?= Helper::sanitize($leave['full_name']) ?></span>
                </div>
              </td>
              <td><span style="font-size:13px"><?= $leave['leave_type'] ?></span></td>
              <td style="font-size:13px"><?= date('d M Y', strtotime($leave['from_date'])) ?></td>
              <td style="font-size:13px"><?= date('d M Y', strtotime($leave['to_date'])) ?></td>
              <td style="font-size:13px;font-weight:700"><?= $leave['days'] ?></td>
              <td style="font-size:12px;color:var(--muted);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= Helper::sanitize($leave['reason'] ?? '-') ?></td>
              <td>
                <div style="display:flex;gap:6px">
                  <button class="btn-sm btn-approve" onclick="hrAction('approve_leave',<?= $leave['id'] ?>)"><i class="fa fa-check"></i> Approve</button>
                  <button class="btn-sm btn-reject" onclick="hrAction('reject_leave',<?= $leave['id'] ?>)"><i class="fa fa-times"></i> Reject</button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Today's Attendance -->
    <div class="data-table-wrap">
      <div class="table-header">
        <h3><i class="fa fa-calendar-check" style="color:var(--green)"></i> Today's Attendance</h3>
        <a href="/hr/attendance" style="font-size:13px;color:var(--red)">Full Report</a>
      </div>
      <?php if (!empty($recentAtt)): ?>
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr><th>Employee</th><th>Check In</th><th>Check Out</th><th>Hours</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php foreach ($recentAtt as $att): ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <img src="<?= Helper::avatarUrl($att['avatar']) ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover">
                  <span style="font-size:13px;font-weight:600"><?= Helper::sanitize($att['full_name']) ?></span>
                </div>
              </td>
              <td style="font-size:13px"><?= $att['check_in'] ?? '-' ?></td>
              <td style="font-size:13px"><?= $att['check_out'] ?? '-' ?></td>
              <td style="font-size:13px;font-weight:600"><?= $att['work_hours'] ? $att['work_hours'] . 'h' : '-' ?></td>
              <td>
                <?php
                  $colors = ['present'=>'status-published','absent'=>'status-rejected','late'=>'status-pending','half_day'=>'status-pending','leave'=>'status-draft'];
                ?>
                <span class="status-badge <?= $colors[$att['status']] ?? 'status-draft' ?>"><?= ucfirst(str_replace('_',' ',$att['status'])) ?></span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="empty-state"><i class="fa fa-calendar"></i><h3>No attendance recorded today</h3></div>
      <?php endif; ?>
    </div>
  </main>
</div>

<div class="toast-container" id="toastContainer"></div>
<script>
const APP = { url:'<?= Helper::appUrl() ?>', csrfToken:'<?= Csrf::token() ?>', userId:<?= Auth::id() ?>, isLoggedIn:true };
</script>
<script src="/public/assets/js/app.js"></script>
<script>
// Weekly attendance chart
(function(){
  const canvas = document.getElementById('attChart');
  if(!canvas) return;
  new Chart(canvas, {
    type: 'bar',
    data: {
      labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
      datasets: [{
        label: 'Present',
        data: [<?= implode(',', array_map(fn($d) => (int)(Database::getInstance()->fetchOne("SELECT COUNT(*) c FROM attendance WHERE date=? AND status='present'", [date('Y-m-d', strtotime('this week '.$d))])['c'] ?? 0), ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'])) ?>],
        backgroundColor: 'rgba(0,200,83,0.7)',
        borderColor: '#00C853',
        borderWidth: 1,
        borderRadius: 6,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#7A7A95' } },
        y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#7A7A95', stepSize: 1 } }
      }
    }
  });
})();

async function hrAction(action, id) {
  const remarks = action === 'reject_leave' ? prompt('Rejection reason (optional):') ?? '' : '';
  const data = await API.post('/api/hr/leaves', { action, leave_id: id, remarks });
  if (data.success) { Toast.show(data.message, 'success'); setTimeout(() => location.reload(), 1000); }
  else Toast.show(data.error || 'Error', 'error');
}
</script>
</body>
</html>

