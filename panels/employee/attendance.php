<?php
require_once BASE_PATH . '/includes/bootstrap.php';

Auth::requireRole('super_admin', 'admin', 'manager', 'editor', 'reporter', 'hr');

$pageTitle = 'My Attendance - FatakNews';
$db = Database::getInstance();
$userId = (int)Auth::id();

$today = $db->fetchOne("SELECT * FROM attendance WHERE user_id=? AND date=CURDATE()", [$userId]);
$history = $db->fetchAll(
    "SELECT * FROM attendance WHERE user_id=? ORDER BY date DESC, id DESC LIMIT 60",
    [$userId]
);

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$summary = [
    'present' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM attendance WHERE user_id=? AND date BETWEEN ? AND ? AND status='present'", [$userId, $monthStart, $monthEnd])['c'] ?? 0),
    'late' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM attendance WHERE user_id=? AND date BETWEEN ? AND ? AND status='late'", [$userId, $monthStart, $monthEnd])['c'] ?? 0),
    'half_day' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM attendance WHERE user_id=? AND date BETWEEN ? AND ? AND status='half_day'", [$userId, $monthStart, $monthEnd])['c'] ?? 0),
    'hours' => (float)($db->fetchOne("SELECT COALESCE(SUM(work_hours),0) AS c FROM attendance WHERE user_id=? AND date BETWEEN ? AND ?", [$userId, $monthStart, $monthEnd])['c'] ?? 0),
];

$pendingLeaves = (new HrModel())->getLeaves($userId, 'pending');
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
      <div style="font-size:11px;color:#AA00FF;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-top:6px">Employee Panel</div>
    </div>
    <nav class="panel-nav">
      <div class="panel-nav-section">My Work</div>
      <a href="/employee" class="panel-nav-link"><i class="fa fa-th-large"></i> Dashboard</a>
      <a href="/employee/create" class="panel-nav-link"><i class="fa fa-pen"></i> Write Article</a>
      <a href="/employee/my-posts" class="panel-nav-link"><i class="fa fa-newspaper"></i> My Posts</a>
      <div class="panel-nav-section">HR Self-Service</div>
      <a href="/employee/attendance" class="panel-nav-link active"><i class="fa fa-clock"></i> My Attendance</a>
      <a href="/employee/leaves" class="panel-nav-link">
        <i class="fa fa-calendar-times"></i> Leave Apply
        <?php if (count($pendingLeaves) > 0): ?>
        <span style="margin-left:auto;background:var(--yellow);color:#000;font-size:10px;padding:2px 7px;border-radius:50px;font-weight:700"><?= count($pendingLeaves) ?></span>
        <?php endif; ?>
      </a>
      <div class="panel-nav-section">Site</div>
      <a href="/" class="panel-nav-link" target="_blank"><i class="fa fa-external-link-alt"></i> View Site</a>
      <a href="/logout" class="panel-nav-link" style="color:var(--red)"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </nav>
  </aside>

  <main class="panel-main">
    <div class="panel-header">
      <div>
        <h1>My Attendance</h1>
        <p style="color:var(--muted);font-size:13px;margin-top:2px">Mark today and review your attendance history for the current month.</p>
      </div>
      <span class="status-badge <?= $today ? 'status-published' : 'status-draft' ?>"><?= $today ? 'Marked Today' : 'Awaiting Mark' ?></span>
    </div>

    <div style="background:linear-gradient(135deg,var(--card),var(--card2));border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
      <div>
        <div style="font-weight:700;font-size:16px">Today</div>
        <div style="font-size:13px;color:var(--muted);margin-top:3px"><?= date('l, d F Y') ?></div>
        <div style="font-size:13px;color:var(--text);margin-top:8px">
          <?php if ($today): ?>
          Status: <strong><?= ucfirst(str_replace('_', ' ', $today['status'])) ?></strong>
          <?php if ($today['check_in']): ?> | Check in: <?= Helper::sanitize($today['check_in']) ?><?php endif; ?>
          <?php if ($today['check_out']): ?> | Check out: <?= Helper::sanitize($today['check_out']) ?><?php endif; ?>
          <?php else: ?>
          No attendance entry for today yet.
          <?php endif; ?>
        </div>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn-write" style="background:var(--green)" onclick="markAttendance('present')"><i class="fa fa-sign-in-alt"></i> Check In</button>
        <button class="btn-write" style="background:var(--yellow);color:#000" onclick="markAttendance('late')"><i class="fa fa-clock"></i> Mark Late</button>
        <button class="btn-write" style="background:var(--card);border:1px solid var(--border)" onclick="markAttendance('half_day')"><i class="fa fa-adjust"></i> Half Day</button>
        <button class="btn-write" style="background:var(--bg3);border:1px solid var(--border)" onclick="markAttendance('present')"><i class="fa fa-sign-out-alt"></i> Check Out</button>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,200,83,0.15);color:var(--green)"><i class="fa fa-user-check"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($summary['present']) ?></strong><span>Present This Month</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,215,0,0.15);color:var(--yellow)"><i class="fa fa-user-clock"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($summary['late']) ?></strong><span>Late Marks</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(41,121,255,0.15);color:var(--blue)"><i class="fa fa-adjust"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($summary['half_day']) ?></strong><span>Half Days</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(170,0,255,0.15);color:var(--purple)"><i class="fa fa-hourglass"></i></div>
        <div class="stat-info"><strong><?= number_format($summary['hours'], 1) ?></strong><span>Hours Logged</span></div>
      </div>
    </div>

    <div class="data-table-wrap">
      <div class="table-header">
        <h3>Recent Attendance</h3>
        <span style="font-size:13px;color:var(--muted)">Last <?= count($history) ?> entries</span>
      </div>
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Check In</th>
              <th>Check Out</th>
              <th>Hours</th>
              <th>Status</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($history)): ?>
            <?php foreach ($history as $record): ?>
            <tr>
              <td style="font-size:13px">
                <div><?= date('d M Y', strtotime($record['date'])) ?></div>
                <div style="font-size:12px;color:var(--muted)"><?= date('D', strtotime($record['date'])) ?></div>
              </td>
              <td style="font-size:13px"><?= Helper::sanitize($record['check_in'] ?? '-') ?></td>
              <td style="font-size:13px"><?= Helper::sanitize($record['check_out'] ?? '-') ?></td>
              <td style="font-size:13px"><?= $record['work_hours'] !== null ? number_format((float)$record['work_hours'], 2) . 'h' : '-' ?></td>
              <td><span class="status-badge status-<?= $record['status'] === 'present' ? 'published' : ($record['status'] === 'late' ? 'pending' : ($record['status'] === 'half_day' ? 'draft' : 'rejected')) ?>"><?= ucfirst(str_replace('_', ' ', $record['status'])) ?></span></td>
              <td style="font-size:12px;color:var(--muted)"><?= Helper::sanitize($record['notes'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr>
              <td colspan="6">
                <div class="empty-state" style="padding:28px 0">
                  <i class="fa fa-calendar-check"></i>
                  <h3>No attendance history</h3>
                  <p>Start marking attendance from today.</p>
                </div>
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
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
async function markAttendance(status) {
  const data = await API.post('/api/hr/attendance', { action: 'mark_self', status });
  if (!data.success) {
    Toast.show(data.error || 'Action failed', 'error');
    return;
  }

  Toast.show(data.message || 'Attendance updated', 'success');
  setTimeout(() => window.location.reload(), 700);
}
</script>
</body>
</html>
