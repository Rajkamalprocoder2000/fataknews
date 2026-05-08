<?php
require_once BASE_PATH . '/includes/bootstrap.php';

Auth::requireRole('super_admin', 'admin', 'manager');

$pageTitle = 'Reporters - FatakNews';
$db = Database::getInstance();
$q = trim((string)($_GET['q'] ?? ''));
$roleFilter = trim((string)($_GET['role'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));

$allowedRoles = ['reporter', 'editor'];
$allowedStatuses = ['active', 'suspended'];
if ($roleFilter !== '' && !in_array($roleFilter, $allowedRoles, true)) {
    $roleFilter = '';
}
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$where = ["r.slug IN ('reporter', 'editor')"];
$params = [];

if ($q !== '') {
    $where[] = '(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR d.name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}

if ($roleFilter !== '') {
    $where[] = 'r.slug=?';
    $params[] = $roleFilter;
}

if ($statusFilter === 'active') {
    $where[] = 'u.is_active=1';
} elseif ($statusFilter === 'suspended') {
    $where[] = 'u.is_active=0';
}

$reporters = $db->fetchAll(
    "SELECT u.id, u.full_name, u.username, u.email, u.avatar, u.is_active, u.is_verified, u.last_login,
            r.slug AS role_slug, r.name AS role_name,
            d.name AS department_name,
            ep.designation,
            COALESCE((SELECT COUNT(*) FROM posts p WHERE p.user_id=u.id), 0) AS posts_count,
            COALESCE((SELECT COUNT(*) FROM posts p WHERE p.user_id=u.id AND p.status='pending'), 0) AS pending_count,
            COALESCE((SELECT COUNT(*) FROM posts p WHERE p.user_id=u.id AND p.status='published'), 0) AS published_count,
            COALESCE((SELECT SUM(p.views_count) FROM posts p WHERE p.user_id=u.id), 0) AS total_views
     FROM users u
     JOIN roles r ON r.id=u.role_id
     LEFT JOIN employee_profiles ep ON ep.user_id=u.id
     LEFT JOIN departments d ON d.id=ep.department_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY u.is_active DESC, published_count DESC, u.full_name ASC",
    $params
);

$stats = [
    'total' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug IN ('reporter', 'editor')")['c'] ?? 0),
    'active' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug IN ('reporter', 'editor') AND u.is_active=1")['c'] ?? 0),
    'verified' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug IN ('reporter', 'editor') AND u.is_verified=1")['c'] ?? 0),
    'pending_posts' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM posts p JOIN users u ON u.id=p.user_id JOIN roles r ON r.id=u.role_id WHERE r.slug IN ('reporter', 'editor') AND p.status='pending'")['c'] ?? 0),
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
      <div style="font-size:11px;color:#FF6B1A;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-top:6px">Manager Panel</div>
    </div>
    <nav class="panel-nav">
      <div class="panel-nav-section">Overview</div>
      <a href="/manager" class="panel-nav-link"><i class="fa fa-th-large"></i> Dashboard</a>
      <div class="panel-nav-section">Content</div>
      <a href="/manager/approve" class="panel-nav-link"><i class="fa fa-check-circle"></i> Approve Posts</a>
      <a href="/manager/posts" class="panel-nav-link"><i class="fa fa-newspaper"></i> All Posts</a>
      <div class="panel-nav-section">Team</div>
      <a href="/manager/reporters" class="panel-nav-link active"><i class="fa fa-id-badge"></i> Reporters</a>
      <div class="panel-nav-section">Navigation</div>
      <a href="/employee/create" class="panel-nav-link"><i class="fa fa-pen"></i> Write Post</a>
      <a href="/" class="panel-nav-link" target="_blank"><i class="fa fa-external-link-alt"></i> View Site</a>
      <a href="/logout" class="panel-nav-link" style="color:var(--red)"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </nav>
  </aside>

  <main class="panel-main">
    <div class="panel-header">
      <div>
        <h1>Reporter Directory</h1>
        <p style="color:var(--muted);font-size:13px;margin-top:2px">Track editorial capacity, publishing output, and who needs follow-up.</p>
      </div>
      <a href="/manager/posts?status=pending" class="btn-write" style="background:var(--yellow);color:#000"><i class="fa fa-hourglass-half"></i> <?= $stats['pending_posts'] ?> Pending Posts</a>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,107,26,0.16);color:#FF6B1A"><i class="fa fa-users"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['total']) ?></strong><span>Editors and Reporters</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,200,83,0.15);color:var(--green)"><i class="fa fa-user-check"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['active']) ?></strong><span>Active Accounts</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(41,121,255,0.15);color:var(--blue)"><i class="fa fa-badge-check"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['verified']) ?></strong><span>Verified Staff</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,215,0,0.15);color:var(--yellow)"><i class="fa fa-clock"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['pending_posts']) ?></strong><span>Pending Stories</span></div>
      </div>
    </div>

    <div class="data-table-wrap">
      <div class="table-header" style="gap:14px;flex-wrap:wrap">
        <h3>Team View</h3>
        <form method="get" action="/manager/reporters" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
          <input type="text" name="q" value="<?= Helper::sanitize($q) ?>" class="form-control" placeholder="Search name, desk, email..." style="width:240px">
          <select name="role" class="form-control" style="width:160px">
            <option value="">Any role</option>
            <?php foreach ($allowedRoles as $role): ?>
            <option value="<?= $role ?>" <?= $roleFilter === $role ? 'selected' : '' ?>><?= ucfirst($role) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="status" class="form-control" style="width:160px">
            <option value="">Any status</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
          </select>
          <button type="submit" class="btn-sm btn-approve">Apply</button>
          <a href="/manager/reporters" class="btn-sm btn-edit">Reset</a>
        </form>
      </div>

      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>Reporter</th>
              <th>Role</th>
              <th>Desk</th>
              <th>Output</th>
              <th>Reach</th>
              <th>Last Login</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($reporters)): ?>
            <?php foreach ($reporters as $reporter): ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:12px">
                  <img src="<?= Helper::avatarUrl($reporter['avatar']) ?>" style="width:38px;height:38px;border-radius:50%;object-fit:cover">
                  <div>
                    <div style="font-weight:700"><?= Helper::sanitize($reporter['full_name']) ?></div>
                    <div style="font-size:12px;color:var(--muted)">@<?= Helper::sanitize($reporter['username']) ?> | <?= Helper::sanitize($reporter['email']) ?></div>
                  </div>
                </div>
              </td>
              <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                  <span class="status-badge status-draft"><?= Helper::sanitize($reporter['role_name']) ?></span>
                  <?php if ((int)$reporter['is_verified'] === 1): ?><span class="status-badge status-pending">Verified</span><?php endif; ?>
                  <span class="status-badge <?= (int)$reporter['is_active'] === 1 ? 'status-published' : 'status-rejected' ?>"><?= (int)$reporter['is_active'] === 1 ? 'Active' : 'Suspended' ?></span>
                </div>
              </td>
              <td style="font-size:13px;color:var(--muted)">
                <div><?= Helper::sanitize($reporter['department_name'] ?: 'Unassigned') ?></div>
                <div><?= Helper::sanitize($reporter['designation'] ?: 'No designation') ?></div>
              </td>
              <td style="font-size:12px;color:var(--muted)">
                <div><?= Helper::formatNumber((int)$reporter['posts_count']) ?> total posts</div>
                <div><?= Helper::formatNumber((int)$reporter['published_count']) ?> published | <?= Helper::formatNumber((int)$reporter['pending_count']) ?> pending</div>
              </td>
              <td style="font-size:12px;color:var(--muted)">
                <div><?= Helper::formatNumber((int)$reporter['total_views']) ?> views</div>
                <div><?= (int)$reporter['posts_count'] > 0 ? round((int)$reporter['total_views'] / max(1, (int)$reporter['posts_count'])) : 0 ?> avg/post</div>
              </td>
              <td style="font-size:12px;color:var(--muted)">
                <?= $reporter['last_login'] ? Helper::timeAgo($reporter['last_login']) : 'Never logged in' ?>
              </td>
              <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                  <a href="/manager/posts?author=<?= (int)$reporter['id'] ?>" class="btn-sm btn-edit">Posts</a>
                  <a href="/@<?= Helper::sanitize($reporter['username']) ?>" target="_blank" class="btn-sm btn-edit">Profile</a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr>
              <td colspan="7">
                <div class="empty-state" style="padding:28px 0">
                  <i class="fa fa-id-badge"></i>
                  <h3>No team members found</h3>
                  <p>Try changing the filters.</p>
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
</body>
</html>
