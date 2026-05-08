<?php
require_once BASE_PATH . '/includes/bootstrap.php';

Auth::requireRole('super_admin', 'admin', 'manager', 'editor', 'reporter', 'hr');

$pageTitle = 'Leave Requests - FatakNews';
$db = Database::getInstance();
$userId = (int)Auth::id();
$hrModel = new HrModel();

$leaveTypes = $db->fetchAll("SELECT * FROM leave_types ORDER BY name ASC");
$leaves = $hrModel->getLeaves($userId);
$balances = [];
foreach ($leaveTypes as $type) {
    $used = (int)($db->fetchOne(
        "SELECT COALESCE(SUM(days), 0) AS c FROM leaves WHERE user_id=? AND leave_type_id=? AND status='approved'",
        [$userId, $type['id']]
    )['c'] ?? 0);
    $balances[] = [
        'id' => (int)$type['id'],
        'name' => $type['name'],
        'allowed' => (int)$type['days_allowed'],
        'used' => $used,
        'remaining' => max(0, (int)$type['days_allowed'] - $used),
    ];
}

$stats = [
    'pending' => count(array_filter($leaves, static fn($row) => $row['status'] === 'pending')),
    'approved' => count(array_filter($leaves, static fn($row) => $row['status'] === 'approved')),
    'rejected' => count(array_filter($leaves, static fn($row) => $row['status'] === 'rejected')),
    'days_requested' => array_reduce($leaves, static fn($carry, $row) => $carry + (int)$row['days'], 0),
];
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
      <a href="/employee/attendance" class="panel-nav-link"><i class="fa fa-clock"></i> My Attendance</a>
      <a href="/employee/leaves" class="panel-nav-link active"><i class="fa fa-calendar-times"></i> Leave Apply</a>
      <div class="panel-nav-section">Site</div>
      <a href="/" class="panel-nav-link" target="_blank"><i class="fa fa-external-link-alt"></i> View Site</a>
      <a href="/logout" class="panel-nav-link" style="color:var(--red)"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </nav>
  </aside>

  <main class="panel-main">
    <div class="panel-header">
      <div>
        <h1>Leave Requests</h1>
        <p style="color:var(--muted);font-size:13px;margin-top:2px">Apply for leave, track approvals, and monitor remaining balances.</p>
      </div>
      <span class="status-badge status-pending"><?= Helper::formatNumber($stats['pending']) ?> Pending</span>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,215,0,0.15);color:var(--yellow)"><i class="fa fa-hourglass-half"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['pending']) ?></strong><span>Pending</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,200,83,0.15);color:var(--green)"><i class="fa fa-check-circle"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['approved']) ?></strong><span>Approved</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,45,45,0.15);color:var(--red)"><i class="fa fa-times-circle"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['rejected']) ?></strong><span>Rejected</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(170,0,255,0.15);color:var(--purple)"><i class="fa fa-calendar-days"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['days_requested']) ?></strong><span>Total Days Requested</span></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:minmax(320px,380px) 1fr;gap:20px;align-items:start">
      <div class="data-table-wrap">
        <div class="table-header">
          <h3>Apply Leave</h3>
        </div>
        <form id="leaveForm" style="padding:20px;display:flex;flex-direction:column;gap:14px">
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
            <textarea name="reason" class="form-control" rows="5" placeholder="Briefly explain the reason for leave" required></textarea>
          </div>
          <button type="submit" class="btn-sm btn-approve">Submit Request</button>
        </form>
      </div>

      <div style="display:grid;gap:20px">
        <div class="data-table-wrap">
          <div class="table-header">
            <h3>Leave Balance</h3>
            <span style="font-size:13px;color:var(--muted)"><?= count($balances) ?> leave types</span>
          </div>
          <div style="padding:20px;display:grid;gap:12px">
            <?php foreach ($balances as $balance): ?>
            <div style="padding:14px;border:1px solid var(--border);border-radius:14px;background:var(--bg2)">
              <div style="display:flex;justify-content:space-between;gap:12px">
                <strong><?= Helper::sanitize($balance['name']) ?></strong>
                <span style="font-size:12px;color:var(--muted)"><?= $balance['remaining'] ?>/<?= $balance['allowed'] ?> left</span>
              </div>
              <div style="height:8px;background:var(--bg3);border-radius:999px;overflow:hidden;margin-top:10px">
                <div style="height:100%;width:<?= $balance['allowed'] > 0 ? min(100, round(($balance['used'] / $balance['allowed']) * 100)) : 0 ?>%;background:var(--red)"></div>
              </div>
              <div style="font-size:12px;color:var(--muted);margin-top:8px"><?= $balance['used'] ?> used</div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="data-table-wrap">
          <div class="table-header">
            <h3>My Leave History</h3>
            <span style="font-size:13px;color:var(--muted)"><?= count($leaves) ?> requests</span>
          </div>
          <div style="overflow-x:auto">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Leave Type</th>
                  <th>Duration</th>
                  <th>Days</th>
                  <th>Status</th>
                  <th>Remarks</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($leaves)): ?>
                <?php foreach ($leaves as $leave): ?>
                <tr>
                  <td style="font-size:13px;font-weight:600"><?= Helper::sanitize($leave['leave_type']) ?></td>
                  <td style="font-size:12px;color:var(--muted)">
                    <div><?= date('d M Y', strtotime($leave['from_date'])) ?></div>
                    <div>to <?= date('d M Y', strtotime($leave['to_date'])) ?></div>
                  </td>
                  <td style="font-size:13px"><?= (int)$leave['days'] ?></td>
                  <td><span class="status-badge status-<?= $leave['status'] === 'approved' ? 'published' : ($leave['status'] === 'pending' ? 'pending' : 'rejected') ?>"><?= ucfirst($leave['status']) ?></span></td>
                  <td style="font-size:12px;color:var(--muted)"><?= Helper::sanitize($leave['remarks'] ?: '-') ?></td>
                  <td>
                    <?php if ($leave['status'] === 'pending'): ?>
                    <button class="btn-sm btn-delete" data-confirm="Cancel this leave request?" onclick="cancelLeave(<?= (int)$leave['id'] ?>)">Cancel</button>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--muted)"><?= Helper::timeAgo($leave['applied_at']) ?></span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                  <td colspan="6">
                    <div class="empty-state" style="padding:28px 0">
                      <i class="fa fa-calendar-times"></i>
                      <h3>No leave requests yet</h3>
                      <p>Your requests will appear here after submission.</p>
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
document.getElementById('leaveForm').addEventListener('submit', async (event) => {
  event.preventDefault();

  const formData = new FormData(event.currentTarget);
  const payload = {
    action: 'apply',
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

  Toast.show(data.message || 'Leave request submitted', 'success');
  setTimeout(() => window.location.reload(), 700);
});

async function cancelLeave(leaveId) {
  if (!confirm('Cancel this pending leave request?')) return;

  const data = await API.post('/api/hr/leaves', { action: 'cancel_own', leave_id: leaveId });
  if (!data.success) {
    Toast.show(data.error || 'Action failed', 'error');
    return;
  }

  Toast.show(data.message || 'Leave cancelled', 'success');
  setTimeout(() => window.location.reload(), 700);
}
</script>
</body>
</html>
