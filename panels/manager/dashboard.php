<?php
require_once BASE_PATH . '/includes/bootstrap.php';
Auth::requireRole('super_admin','admin','manager');
$pageTitle = 'Manager Dashboard - FatakNews';
$db        = Database::getInstance();
$postModel = new PostModel();
$pending   = $postModel->getPending();
$stats = [
  'pending'   => $db->fetchOne("SELECT COUNT(*) c FROM posts WHERE status='pending'")['c'],
  'published' => $db->fetchOne("SELECT COUNT(*) c FROM posts WHERE status='published'")['c'],
  'rejected'  => $db->fetchOne("SELECT COUNT(*) c FROM posts WHERE status='rejected'")['c'],
  'reporters' => $db->fetchOne("SELECT COUNT(*) c FROM users WHERE role_id IN (SELECT id FROM roles WHERE slug IN ('reporter','editor'))")['c'],
  'today'     => $db->fetchOne("SELECT COUNT(*) c FROM posts WHERE DATE(published_at)=CURDATE() AND status='published'")['c'],
];
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
      <div style="font-size:11px;color:#FF6B1A;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-top:6px">Manager Panel</div>
    </div>
    <nav class="panel-nav">
      <div class="panel-nav-section">Overview</div>
      <a href="/manager" class="panel-nav-link active"><i class="fa fa-th-large"></i> Dashboard</a>
      <div class="panel-nav-section">Content</div>
      <a href="/manager/approve" class="panel-nav-link">
        <i class="fa fa-check-circle"></i> Approve Posts
        <?php if ($stats['pending'] > 0): ?>
        <span style="margin-left:auto;background:var(--red);color:#fff;font-size:10px;padding:2px 7px;border-radius:50px"><?= $stats['pending'] ?></span>
        <?php endif; ?>
      </a>
      <a href="/manager/posts" class="panel-nav-link"><i class="fa fa-newspaper"></i> All Posts</a>
      <div class="panel-nav-section">Team</div>
      <a href="/manager/reporters" class="panel-nav-link"><i class="fa fa-id-badge"></i> Reporters</a>
      <div class="panel-nav-section">Navigation</div>
      <a href="/employee/create" class="panel-nav-link"><i class="fa fa-pen"></i> Write Post</a>
      <a href="/" class="panel-nav-link" target="_blank"><i class="fa fa-external-link-alt"></i> View Site</a>
      <a href="/logout" class="panel-nav-link" style="color:var(--red)"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </nav>
  </aside>

  <main class="panel-main">
    <div class="panel-header">
      <div>
        <h1>Manager Dashboard</h1>
        <p style="color:var(--muted);font-size:13px;margin-top:2px">Review and manage all content submissions</p>
      </div>
      <div style="display:flex;gap:10px">
        <a href="/manager/approve" class="btn-write" style="background:var(--yellow);color:#000"><i class="fa fa-clock"></i> <?= $stats['pending'] ?> Pending</a>
        <a href="/employee/create" class="btn-write"><i class="fa fa-pen"></i> Write Post</a>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,215,0,0.15);color:var(--yellow)"><i class="fa fa-hourglass-half"></i></div>
        <div class="stat-info"><strong><?= $stats['pending'] ?></strong><span>Pending Review</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,200,83,0.15);color:var(--green)"><i class="fa fa-check-circle"></i></div>
        <div class="stat-info"><strong><?= $stats['published'] ?></strong><span>Published</span><span class="stat-trend"><i class="fa fa-arrow-up"></i> <?= $stats['today'] ?> today</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,45,45,0.15);color:var(--red)"><i class="fa fa-times-circle"></i></div>
        <div class="stat-info"><strong><?= $stats['rejected'] ?></strong><span>Rejected</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(41,121,255,0.15);color:var(--blue)"><i class="fa fa-id-badge"></i></div>
        <div class="stat-info"><strong><?= $stats['reporters'] ?></strong><span>Reporters/Editors</span></div>
      </div>
    </div>

    <div class="data-table-wrap">
      <div class="table-header">
        <h3><i class="fa fa-inbox" style="color:var(--yellow)"></i> Pending Approval Queue</h3>
        <a href="/manager/approve" style="font-size:13px;color:var(--red)">View all pending</a>
      </div>
      <?php if (!empty($pending['data'])): ?>
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr><th>Post</th><th>By</th><th>Category</th><th>Type</th><th>Submitted</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($pending['data'] as $p): ?>
            <tr>
              <td style="max-width:300px">
                <div style="font-weight:600;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= Helper::sanitize($p['title']) ?></div>
                <div style="font-size:12px;color:var(--muted);margin-top:3px"><?= Helper::excerpt($p['excerpt'] ?? '', 80) ?></div>
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:8px">
                  <img src="<?= Helper::avatarUrl($p['avatar']) ?>" style="width:28px;height:28px;border-radius:50%;object-fit:cover">
                  <span style="font-size:13px"><?= Helper::sanitize($p['full_name']) ?></span>
                </div>
              </td>
              <td style="font-size:13px"><?php if ($p['category_name']): ?><span style="color:<?= $p['category_color'] ?>"><?= Helper::sanitize($p['category_name']) ?></span><?php else: ?>-<?php endif; ?></td>
              <td><span class="status-badge status-draft"><?= ucfirst($p['type']) ?></span></td>
              <td style="font-size:12px;color:var(--muted)"><?= Helper::timeAgo($p['created_at']) ?></td>
              <td>
                <div style="display:flex;gap:6px">
                  <a href="/<?= $p['category_slug'] ?>/<?= $p['slug'] ?>" target="_blank" class="btn-sm btn-edit" title="Preview"><i class="fa fa-eye"></i></a>
                  <button class="btn-sm btn-approve" onclick="managerAction('approve',<?= $p['id'] ?>)"><i class="fa fa-check"></i> Approve</button>
                  <button class="btn-sm btn-reject"  onclick="managerAction('reject',<?= $p['id'] ?>)"><i class="fa fa-times"></i> Reject</button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="empty-state"><i class="fa fa-check-double" style="color:var(--green)"></i><h3>All clear!</h3><p>No posts pending review.</p></div>
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
async function managerAction(action, postId) {
  const reason = action === 'reject' ? prompt('Rejection reason:') : null;
  if (action === 'reject' && reason === null) return;
  const data = await API.post('/api/admin/posts', { action, post_id: postId, reason });
  if (data.success) { Toast.show(data.message, 'success'); setTimeout(() => location.reload(), 1000); }
  else Toast.show(data.error || 'Error', 'error');
}
</script>
</body>
</html>
