<?php
require_once BASE_PATH . '/includes/bootstrap.php';

Auth::requireRole('super_admin', 'admin', 'manager', 'editor', 'reporter', 'hr');

$pageTitle = 'My Posts - FatakNews';
$db = Database::getInstance();
$userId = (int)Auth::id();
$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));

$allowedStatuses = ['draft', 'pending', 'published', 'rejected'];
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$where = ['p.user_id=?'];
$params = [$userId];

if ($q !== '') {
    $where[] = '(p.title LIKE ? OR p.excerpt LIKE ? OR c.name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}

if ($statusFilter !== '') {
    $where[] = 'p.status=?';
    $params[] = $statusFilter;
}

$posts = $db->fetchAll(
    "SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
     FROM posts p
     LEFT JOIN categories c ON c.id=p.category_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY p.created_at DESC",
    $params
);

$stats = [
    'total' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM posts WHERE user_id=?", [$userId])['c'] ?? 0),
    'draft' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM posts WHERE user_id=? AND status='draft'", [$userId])['c'] ?? 0),
    'pending' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM posts WHERE user_id=? AND status='pending'", [$userId])['c'] ?? 0),
    'published' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM posts WHERE user_id=? AND status='published'", [$userId])['c'] ?? 0),
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
      <a href="/employee/my-posts" class="panel-nav-link active"><i class="fa fa-newspaper"></i> My Posts</a>
      <div class="panel-nav-section">HR Self-Service</div>
      <a href="/employee/attendance" class="panel-nav-link"><i class="fa fa-clock"></i> My Attendance</a>
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
        <h1>My Posts</h1>
        <p style="color:var(--muted);font-size:13px;margin-top:2px">Track every draft, pending review item, and published story from one screen.</p>
      </div>
      <a href="/employee/create" class="btn-write"><i class="fa fa-pen"></i> New Post</a>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(170,0,255,0.15);color:var(--purple)"><i class="fa fa-file-lines"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['total']) ?></strong><span>Total Posts</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(41,121,255,0.15);color:var(--blue)"><i class="fa fa-file-pen"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['draft']) ?></strong><span>Drafts</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,215,0,0.15);color:var(--yellow)"><i class="fa fa-hourglass-half"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['pending']) ?></strong><span>Pending</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,200,83,0.15);color:var(--green)"><i class="fa fa-check-circle"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['published']) ?></strong><span>Published</span></div>
      </div>
    </div>

    <div class="data-table-wrap">
      <div class="table-header" style="gap:14px;flex-wrap:wrap">
        <h3>Editorial Inventory</h3>
        <form method="get" action="/employee/my-posts" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
          <input type="text" name="q" value="<?= Helper::sanitize($q) ?>" class="form-control" placeholder="Search title, excerpt, category..." style="width:240px">
          <select name="status" class="form-control" style="width:160px">
            <option value="">Any status</option>
            <?php foreach ($allowedStatuses as $status): ?>
            <option value="<?= $status ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn-sm btn-approve">Apply</button>
          <a href="/employee/my-posts" class="btn-sm btn-edit">Reset</a>
        </form>
      </div>

      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>Post</th>
              <th>Category</th>
              <th>Status</th>
              <th>Performance</th>
              <th>Updated</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($posts)): ?>
            <?php foreach ($posts as $post): ?>
            <?php $previewUrl = !empty($post['category_slug']) ? '/' . $post['category_slug'] . '/' . $post['slug'] : '/news/' . $post['slug']; ?>
            <tr>
              <td style="max-width:320px">
                <div style="font-weight:700;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= Helper::sanitize($post['title']) ?></div>
                <div style="font-size:12px;color:var(--muted);margin-top:4px"><?= Helper::excerpt($post['excerpt'] ?? '', 100) ?></div>
              </td>
              <td style="font-size:13px"><?php if ($post['category_name']): ?><span style="color:<?= Helper::sanitize($post['category_color']) ?>"><?= Helper::sanitize($post['category_name']) ?></span><?php else: ?>-<?php endif; ?></td>
              <td><span class="status-badge status-<?= Helper::sanitize($post['status']) ?>"><?= ucfirst($post['status']) ?></span></td>
              <td style="font-size:12px;color:var(--muted)">
                <div><?= Helper::formatNumber((int)$post['views_count']) ?> views</div>
                <div><?= Helper::formatNumber((int)$post['likes_count']) ?> likes | <?= Helper::formatNumber((int)$post['comments_count']) ?> comments</div>
              </td>
              <td style="font-size:12px;color:var(--muted)">
                <div><?= date('d M Y', strtotime($post['updated_at'] ?? $post['created_at'])) ?></div>
                <div><?= Helper::timeAgo($post['updated_at'] ?? $post['created_at']) ?></div>
              </td>
              <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                  <a href="/employee/create?edit=<?= (int)$post['id'] ?>" class="btn-sm btn-edit">Edit</a>
                  <?php if ($post['status'] === 'published'): ?>
                  <a href="<?= $previewUrl ?>" target="_blank" class="btn-sm btn-edit">View</a>
                  <?php endif; ?>
                  <button class="btn-sm btn-delete" data-confirm="Delete this post?" onclick="deletePost(<?= (int)$post['id'] ?>)">Delete</button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr>
              <td colspan="6">
                <div class="empty-state" style="padding:28px 0">
                  <i class="fa fa-newspaper"></i>
                  <h3>No posts found</h3>
                  <p>Start writing or adjust the filters.</p>
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
async function deletePost(postId) {
  if (!confirm('Are you sure you want to delete this post?')) return;

  const data = await API.post('/api/admin/posts', { action: 'delete', post_id: postId });
  if (!data.success) {
    Toast.show(data.error || 'Action failed', 'error');
    return;
  }

  Toast.show(data.message || 'Deleted', 'success');
  setTimeout(() => window.location.reload(), 700);
}
</script>
</body>
</html>
