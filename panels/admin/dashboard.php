<?php
require_once BASE_PATH . '/includes/bootstrap.php';
Auth::requireRole('super_admin','admin');
$pageTitle = 'Admin Dashboard - FatakNews';
$db = Database::getInstance();
$stats = [
  'total_posts'   => $db->fetchOne("SELECT COUNT(*) c FROM posts WHERE status='published'")['c'],
  'total_users'   => $db->fetchOne("SELECT COUNT(*) c FROM users")['c'],
  'pending_posts' => $db->fetchOne("SELECT COUNT(*) c FROM posts WHERE status='pending'")['c'],
  'total_views'   => $db->fetchOne("SELECT COALESCE(SUM(views_count),0) c FROM posts")['c'],
  'today_posts'   => $db->fetchOne("SELECT COUNT(*) c FROM posts WHERE DATE(created_at)=CURDATE()")['c'],
  'breaking_count'=> $db->fetchOne("SELECT COUNT(*) c FROM posts WHERE is_breaking=1 AND status='published'")['c'],
];
$recentPosts = $db->fetchAll("SELECT p.*,u.full_name,c.name cat_name FROM posts p JOIN users u ON p.user_id=u.id LEFT JOIN categories c ON p.category_id=c.id ORDER BY p.created_at DESC LIMIT 10");
$recentUsers = $db->fetchAll("SELECT u.*,r.name role_name FROM users u JOIN roles r ON u.role_id=r.id ORDER BY u.created_at DESC LIMIT 8");
$chartDays = [];
$chartViews = [];
$appCssVersion = @filemtime(BASE_PATH . '/public/assets/css/app.css') ?: time();
for ($i = 6; $i >= 0; $i--) {
  $date = date('Y-m-d', strtotime("-$i days"));
  $chartDays[] = date('D', strtotime($date));
  $chartViews[] = (int)$db->fetchOne("SELECT COALESCE(SUM(views_count),0) c FROM posts WHERE DATE(created_at)=?",[$date])['c'];
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
<link rel="stylesheet" href="/public/assets/css/app.css?v=<?= $appCssVersion ?>">
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
      <div style="font-size:11px;color:var(--red);font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-top:6px">Admin Panel</div>
    </div>
    <nav class="panel-nav">
      <div class="panel-nav-section">Dashboard</div>
      <a href="/admin" class="panel-nav-link active"><i class="fa fa-th-large"></i> Overview</a>
      <a href="/admin/analytics" class="panel-nav-link"><i class="fa fa-chart-bar"></i> Analytics</a>
      <div class="panel-nav-section">Content</div>
      <a href="/admin/pipeline" class="panel-nav-link"><i class="fa fa-tower-broadcast"></i> AI Pipeline</a>
      <a href="/admin/news" class="panel-nav-link"><i class="fa fa-newspaper"></i> All Posts <span style="margin-left:auto;background:var(--red);color:#fff;font-size:10px;padding:2px 7px;border-radius:50px"><?= $stats['pending_posts'] ?></span></a>
      <a href="/admin/engagement" class="panel-nav-link"><i class="fa fa-chart-line"></i> Like &amp; View Manage</a>
      <a href="/admin/categories" class="panel-nav-link"><i class="fa fa-folder"></i> Categories</a>
      <a href="/admin/ads" class="panel-nav-link"><i class="fa fa-ad"></i> Advertisements</a>
      <div class="panel-nav-section">Users</div>
      <a href="/admin/users" class="panel-nav-link"><i class="fa fa-users"></i> All Users</a>
      <a href="/hr" class="panel-nav-link"><i class="fa fa-id-card"></i> HR Module</a>
      <div class="panel-nav-section">System</div>
      <a href="/admin/settings" class="panel-nav-link"><i class="fa fa-cog"></i> Settings</a>
      <a href="/" class="panel-nav-link" target="_blank"><i class="fa fa-external-link-alt"></i> View Site</a>
      <a href="/logout" class="panel-nav-link" style="color:var(--red)"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </nav>
  </aside>

  <main class="panel-main">
    <div class="panel-header">
      <div style="display:flex;align-items:center;gap:14px">
        <button id="panelSidebarToggle" class="panel-sidebar-toggle" aria-label="Open admin menu">
          <i class="fa fa-bars"></i>
        </button>
        <div>
          <h1>Dashboard</h1>
          <p style="color:var(--muted);font-size:13px;margin-top:2px">Welcome back! Here's what's happening.</p>
        </div>
      </div>
      <div class="panel-header-actions">
        <span style="font-size:13px;color:var(--muted)"><?= date('D, d M Y') ?></span>
        <a href="/employee/create#ai-generator" class="btn-ghost"><i class="fa fa-wand-magic-sparkles"></i> AI Draft</a>
        <a href="/employee/create" class="btn-write"><i class="fa fa-pen"></i> New Post</a>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,45,45,0.15);color:var(--red)"><i class="fa fa-newspaper"></i></div>
        <div class="stat-info">
          <strong><?= Helper::formatNumber($stats['total_posts']) ?></strong>
          <span>Published Posts</span>
          <span class="stat-trend"><i class="fa fa-arrow-up"></i> <?= $stats['today_posts'] ?> today</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(41,121,255,0.15);color:var(--blue)"><i class="fa fa-users"></i></div>
        <div class="stat-info">
          <strong><?= Helper::formatNumber($stats['total_users']) ?></strong>
          <span>Total Users</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,215,0,0.15);color:var(--yellow)"><i class="fa fa-clock"></i></div>
        <div class="stat-info">
          <strong><?= $stats['pending_posts'] ?></strong>
          <span>Pending Approval</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,200,83,0.15);color:var(--green)"><i class="fa fa-eye"></i></div>
        <div class="stat-info">
          <strong><?= Helper::formatNumber($stats['total_views']) ?></strong>
          <span>Total Views</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,45,45,0.15);color:var(--red)"><i class="fa fa-bolt"></i></div>
        <div class="stat-info">
          <strong><?= $stats['breaking_count'] ?></strong>
          <span>Breaking News Live</span>
        </div>
      </div>
    </div>

    <div class="panel-inline-grid" style="grid-template-columns:minmax(0,1fr) minmax(280px,360px);margin-bottom:24px">
      <div class="data-table-wrap">
        <div class="table-header"><h3>Views Last 7 Days</h3></div>
        <div style="padding:20px"><canvas id="viewsChart" height="100"></canvas></div>
      </div>
      <div class="data-table-wrap">
        <div class="table-header"><h3>New Users</h3><a href="/admin/users" style="font-size:13px;color:var(--red)">View all</a></div>
        <div style="padding:12px">
          <?php foreach ($recentUsers as $u): ?>
          <div style="display:flex;align-items:center;gap:10px;padding:10px 8px;border-bottom:1px solid var(--border)">
            <img src="<?= Helper::avatarUrl($u['avatar']) ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover">
            <div style="flex:1">
              <div style="font-size:13px;font-weight:600"><?= Helper::sanitize($u['full_name']) ?></div>
              <div style="font-size:11px;color:var(--muted)"><?= Helper::sanitize($u['role_name']) ?> | <?= Helper::timeAgo($u['created_at']) ?></div>
            </div>
            <span class="status-badge <?= $u['is_active'] ? 'status-published' : 'status-rejected' ?>"><?= $u['is_active'] ? 'Active' : 'Banned' ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="data-table-wrap">
      <div class="table-header">
        <h3>Recent Posts</h3>
        <div style="display:flex;gap:10px">
          <a href="/admin/news?status=pending" class="btn-sm btn-edit">Pending (<?= $stats['pending_posts'] ?>)</a>
          <a href="/admin/news" class="btn-sm btn-approve">All Posts</a>
        </div>
      </div>
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>Title</th><th>Author</th><th>Category</th><th>Status</th><th>Views</th><th>Date</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentPosts as $p): ?>
            <tr>
              <td style="max-width:280px">
                <div style="font-weight:600;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= Helper::sanitize($p['title']) ?></div>
                <?php if ($p['is_breaking']): ?><span style="font-size:10px;color:var(--red);font-weight:700">BREAKING</span><?php endif; ?>
              </td>
              <td style="font-size:13px"><?= Helper::sanitize($p['full_name']) ?></td>
              <td style="font-size:13px"><?= Helper::sanitize($p['cat_name'] ?? '-') ?></td>
              <td><span class="status-badge status-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
              <td style="font-size:13px"><?= Helper::formatNumber($p['views_count']) ?></td>
              <td style="font-size:12px;color:var(--muted)"><?= Helper::timeAgo($p['created_at']) ?></td>
              <td>
                <div style="display:flex;gap:6px">
                  <?php if ($p['status'] === 'pending'): ?>
                  <button class="btn-sm btn-approve" onclick="adminAction('approve',<?= $p['id'] ?>)">Approve</button>
                  <button class="btn-sm btn-reject" onclick="adminAction('reject',<?= $p['id'] ?>)">Reject</button>
                  <?php endif; ?>
                  <a href="/admin/news?edit=<?= $p['id'] ?>" class="btn-sm btn-edit">Edit</a>
                  <button class="btn-sm btn-delete" data-confirm="Delete this post?" onclick="adminAction('delete',<?= $p['id'] ?>)">Del</button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
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
initChart('viewsChart', <?= json_encode($chartDays) ?>, <?= json_encode($chartViews) ?>, 'Views');

async function adminAction(action, postId) {
  const reason = action === 'reject' ? prompt('Rejection reason:') : null;
  if (action === 'reject' && reason === null) return;
  const data = await API.post('/api/admin/posts', { action, post_id: postId, reason });
  if (data.success) { Toast.show(data.message, 'success'); setTimeout(() => location.reload(), 1000); }
  else Toast.show(data.error || 'Error', 'error');
}

</script>
</body>
</html>
