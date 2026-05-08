<?php
require_once BASE_PATH . '/includes/bootstrap.php';
Auth::requireRole('super_admin','admin','manager','editor','reporter','hr');
$pageTitle = 'Employee Dashboard â€” FatakNews';
$db        = Database::getInstance();
$userId    = Auth::id();
$myStats   = [
  'total'     => $db->fetchOne("SELECT COUNT(*) c FROM posts WHERE user_id=?",[$userId])['c'],
  'published' => $db->fetchOne("SELECT COUNT(*) c FROM posts WHERE user_id=? AND status='published'",[$userId])['c'],
  'pending'   => $db->fetchOne("SELECT COUNT(*) c FROM posts WHERE user_id=? AND status='pending'",[$userId])['c'],
  'views'     => $db->fetchOne("SELECT COALESCE(SUM(views_count),0) c FROM posts WHERE user_id=?",[$userId])['c'],
];
$myPosts = $db->fetchAll(
  "SELECT p.*,c.name cat_name,c.color cat_color FROM posts p LEFT JOIN categories c ON p.category_id=c.id
   WHERE p.user_id=? ORDER BY p.created_at DESC LIMIT 10", [$userId]
);
$authUser = Auth::user();
$leaveModel = new HrModel();
$myLeaves = $leaveModel->getLeaves($userId, 'pending');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
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
      <a href="/employee" class="panel-nav-link active"><i class="fa fa-th-large"></i> Dashboard</a>
      <a href="/employee/create" class="panel-nav-link"><i class="fa fa-pen"></i> Write Article</a>
      <a href="/employee/my-posts" class="panel-nav-link"><i class="fa fa-newspaper"></i> My Posts</a>
      <div class="panel-nav-section">HR Self-Service</div>
      <a href="/employee/attendance" class="panel-nav-link"><i class="fa fa-clock"></i> My Attendance</a>
      <a href="/employee/leaves" class="panel-nav-link">
        <i class="fa fa-calendar-times"></i> Leave Apply
        <?php if (count($myLeaves) > 0): ?>
        <span style="margin-left:auto;background:var(--yellow);color:#000;font-size:10px;padding:2px 7px;border-radius:50px;font-weight:700"><?= count($myLeaves) ?></span>
        <?php endif; ?>
      </a>
      <div class="panel-nav-section">Site</div>
      <a href="/" class="panel-nav-link" target="_blank"><i class="fa fa-external-link-alt"></i> View Site</a>
      <a href="/logout" class="panel-nav-link" style="color:var(--red)"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </nav>
  </aside>

  <main class="panel-main">
    <!-- Welcome Header -->
    <div class="panel-header">
      <div style="display:flex;align-items:center;gap:14px">
        <img src="<?= Helper::avatarUrl($authUser['avatar']) ?>" style="width:52px;height:52px;border-radius:50%;object-fit:cover;border:3px solid rgba(170,0,255,0.4)">
        <div>
          <h1>Hey, <?= Helper::sanitize(explode(' ', $authUser['full_name'])[0]) ?>!</h1>
          <p style="color:var(--muted);font-size:13px"><?= Helper::sanitize($authUser['role_name']) ?> · <?= date('D, d M Y') ?></p>
        </div>
      </div>
      <a href="/employee/create" class="btn-write"><i class="fa fa-pen"></i> Write Article</a>
    </div>

    <!-- Attendance Quick Action -->
    <div style="background:linear-gradient(135deg,var(--card),var(--card2));border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
      <div>
        <div style="font-weight:700;font-size:16px">Today's Attendance</div>
        <div style="font-size:13px;color:var(--muted);margin-top:2px"><?= date('l, d F Y') ?></div>
      </div>
      <div style="display:flex;gap:10px">
        <button class="btn-write" style="background:var(--green)" onclick="markAttendance('present')"><i class="fa fa-sign-in-alt"></i> Check In</button>
        <button class="btn-write" style="background:var(--bg3);border:1px solid var(--border)" onclick="markAttendance('present')"><i class="fa fa-sign-out-alt"></i> Check Out</button>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(170,0,255,0.15);color:var(--purple)"><i class="fa fa-pen"></i></div>
        <div class="stat-info"><strong><?= $myStats['total'] ?></strong><span>Total Written</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,200,83,0.15);color:var(--green)"><i class="fa fa-check-circle"></i></div>
        <div class="stat-info"><strong><?= $myStats['published'] ?></strong><span>Published</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,215,0,0.15);color:var(--yellow)"><i class="fa fa-hourglass-half"></i></div>
        <div class="stat-info"><strong><?= $myStats['pending'] ?></strong><span>Pending</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(41,121,255,0.15);color:var(--blue)"><i class="fa fa-eye"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($myStats['views']) ?></strong><span>Total Views</span></div>
      </div>
    </div>

    <!-- My Recent Posts -->
    <div class="data-table-wrap">
      <div class="table-header">
        <h3>My Recent Posts</h3>
        <a href="/employee/create" class="btn-sm btn-approve"><i class="fa fa-plus"></i> New Post</a>
      </div>
      <?php if (!empty($myPosts)): ?>
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr><th>Title</th><th>Category</th><th>Status</th><th>Views</th><th>Date</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($myPosts as $p): ?>
            <tr>
              <td style="max-width:260px">
                <div style="font-weight:600;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= Helper::sanitize($p['title']) ?></div>
              </td>
              <td><?php if ($p['cat_name']): ?><span style="font-size:12px;color:<?= $p['cat_color'] ?>"><?= $p['cat_name'] ?></span><?php else: ?>-<?php endif; ?></td>
              <td><span class="status-badge status-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
              <td style="font-size:13px"><?= Helper::formatNumber($p['views_count']) ?></td>
              <td style="font-size:12px;color:var(--muted)"><?= Helper::timeAgo($p['created_at']) ?></td>
              <td>
                <div style="display:flex;gap:6px">
                  <a href="/employee/create?edit=<?= $p['id'] ?>" class="btn-sm btn-edit"><i class="fa fa-edit"></i></a>
                  <button class="btn-sm btn-delete" data-confirm="Delete this post?" onclick="deletePost(<?= $p['id'] ?>)"><i class="fa fa-trash"></i></button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="empty-state"><i class="fa fa-pen"></i><h3>No posts yet</h3><p><a href="/employee/create" style="color:var(--red)">Write your first article!</a></p></div>
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
async function markAttendance(status) {
  const data = await API.post('/api/hr/attendance', { status });
  if (data.success) Toast.show(data.message, 'success');
  else Toast.show(data.error || 'Error', 'error');
}
async function deletePost(id) {
  if (!confirm('Are you sure you want to delete this post?')) return;
  const data = await API.post('/api/admin/posts', { action:'delete', post_id: id });
  if (data.success) { Toast.show('Post deleted', 'success'); setTimeout(() => location.reload(), 800); }
  else Toast.show(data.error || 'Error', 'error');
}
</script>
</body>
</html>

