<?php
require_once BASE_PATH . '/includes/bootstrap.php';

Auth::requireRole('super_admin', 'admin');

$pageTitle = 'Like & View Management - FatakNews';
$db = Database::getInstance();

$status = trim((string)($_GET['status'] ?? ''));
$type = trim((string)($_GET['type'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));

$where = [];
$params = [];

if ($status !== '' && in_array($status, ['draft', 'pending', 'published', 'rejected', 'archived'], true)) {
    $where[] = 'p.status=?';
    $params[] = $status;
}

if ($type !== '' && in_array($type, ['news', 'article', 'community_post', 'thought', 'breaking'], true)) {
    $where[] = 'p.type=?';
    $params[] = $type;
}

if ($q !== '') {
    $where[] = '(p.title LIKE ? OR u.full_name LIKE ? OR c.name LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$posts = $db->paginate(
    "SELECT p.*, u.username, u.full_name, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
     FROM posts p
     JOIN users u ON p.user_id=u.id
     LEFT JOIN categories c ON p.category_id=c.id
     $whereSql
     ORDER BY COALESCE(p.published_at, p.created_at) DESC",
    $params,
    $page,
    12
);

$stats = [
    'posts' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM posts")['c'] ?? 0),
    'published' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM posts WHERE status='published'")['c'] ?? 0),
    'total_likes' => (int)($db->fetchOne("SELECT COALESCE(SUM(likes_count),0) AS c FROM posts")['c'] ?? 0),
    'total_views' => (int)($db->fetchOne("SELECT COALESCE(SUM(views_count),0) AS c FROM posts")['c'] ?? 0),
];

function adminEngagementUrl(array $overrides = []): string {
    $query = array_merge($_GET, $overrides);
    $query = array_filter($query, static fn($value) => $value !== '' && $value !== null);
    return '/admin/engagement' . ($query ? '?' . http_build_query($query) : '');
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
      <div style="font-size:11px;color:var(--red);font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-top:6px">Admin Panel</div>
    </div>
    <nav class="panel-nav">
      <div class="panel-nav-section">Dashboard</div>
      <a href="/admin" class="panel-nav-link"><i class="fa fa-th-large"></i> Overview</a>
      <a href="/admin/analytics" class="panel-nav-link"><i class="fa fa-chart-bar"></i> Analytics</a>
      <div class="panel-nav-section">Content</div>
      <a href="/admin/pipeline" class="panel-nav-link"><i class="fa fa-tower-broadcast"></i> AI Pipeline</a>
      <a href="/admin/news" class="panel-nav-link"><i class="fa fa-newspaper"></i> All Posts</a>
      <a href="/admin/engagement" class="panel-nav-link active"><i class="fa fa-chart-line"></i> Like &amp; View Manage</a>
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
      <div>
        <h1>Like &amp; View Management</h1>
        <p style="color:var(--muted);font-size:13px;margin-top:2px">Manually set likes and views for any post. User likes and normal page views will continue from these values.</p>
      </div>
      <div style="display:flex;gap:10px;align-items:center">
        <a href="/admin/news" class="btn-sm btn-edit"><i class="fa fa-newspaper"></i> Open Posts</a>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,45,45,0.15);color:var(--red)"><i class="fa fa-layer-group"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['posts']) ?></strong><span>Total Posts</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,200,83,0.15);color:var(--green)"><i class="fa fa-check-circle"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['published']) ?></strong><span>Published</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,45,45,0.15);color:var(--red)"><i class="fa fa-heart"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['total_likes']) ?></strong><span>Total Likes</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(41,121,255,0.15);color:var(--blue)"><i class="fa fa-eye"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['total_views']) ?></strong><span>Total Views</span></div>
      </div>
    </div>

    <div class="data-table-wrap" style="margin-bottom:24px">
      <div class="table-header" style="gap:14px;flex-wrap:wrap">
        <h3>Find Posts</h3>
        <form method="get" action="/admin/engagement" class="panel-filter-form">
          <input type="text" name="q" value="<?= Helper::sanitize($q) ?>" class="form-control" placeholder="Search title, author, category...">
          <select name="status" class="form-control">
            <option value="">All statuses</option>
            <?php foreach (['pending','published','draft','rejected','archived'] as $opt): ?>
            <option value="<?= $opt ?>" <?= $status === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="type" class="form-control">
            <option value="">All types</option>
            <?php foreach (['news','article','community_post','thought','breaking'] as $opt): ?>
            <option value="<?= $opt ?>" <?= $type === $opt ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $opt)) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn-sm btn-approve">Apply</button>
          <a href="/admin/engagement" class="btn-sm btn-edit">Reset</a>
        </form>
      </div>

      <?php if (!empty($posts['data'])): ?>
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>Post</th>
              <th>Author</th>
              <th>Status</th>
              <th>Current</th>
              <th>Manual Set</th>
              <th>Save</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($posts['data'] as $post):
              $postUrl = '/' . ($post['category_slug'] ?: 'news') . '/' . $post['slug'];
              $postId = (int)$post['id'];
            ?>
            <tr>
              <td style="max-width:360px">
                <div style="font-weight:700;font-size:13px;line-height:1.4"><?= Helper::sanitize($post['title']) ?></div>
                <div style="font-size:12px;color:var(--muted);margin-top:4px">
                  <?= Helper::sanitize($post['category_name'] ?: 'Uncategorized') ?> • <?= ucwords(str_replace('_', ' ', $post['type'])) ?>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
                  <a href="<?= $postUrl ?>" target="_blank" class="btn-sm btn-edit"><i class="fa fa-eye"></i> Open</a>
                  <a href="/employee/create?edit=<?= $postId ?>" class="btn-sm btn-edit"><i class="fa fa-pen"></i> Edit</a>
                </div>
              </td>
              <td style="font-size:13px">
                <div style="font-weight:600"><?= Helper::sanitize($post['full_name']) ?></div>
                <div style="color:var(--muted);font-size:12px">@<?= Helper::sanitize($post['username']) ?></div>
              </td>
              <td><span class="status-badge status-<?= $post['status'] ?>"><?= ucfirst($post['status']) ?></span></td>
              <td style="font-size:12px;color:var(--muted)">
                <div id="engagementCurrentLikes<?= $postId ?>"><i class="fa fa-heart"></i> <?= Helper::formatNumber((int)$post['likes_count']) ?></div>
                <div id="engagementCurrentViews<?= $postId ?>"><i class="fa fa-eye"></i> <?= Helper::formatNumber((int)$post['views_count']) ?></div>
              </td>
              <td>
                <div class="panel-split-2" style="min-width:220px">
                  <div>
                    <label style="display:block;font-size:11px;color:var(--muted);margin-bottom:6px">Likes</label>
                    <input id="engagementLikes<?= $postId ?>" type="number" min="0" class="form-control" value="<?= (int)$post['likes_count'] ?>">
                  </div>
                  <div>
                    <label style="display:block;font-size:11px;color:var(--muted);margin-bottom:6px">Views</label>
                    <input id="engagementViews<?= $postId ?>" type="number" min="0" class="form-control" value="<?= (int)$post['views_count'] ?>">
                  </div>
                </div>
              </td>
              <td>
                <button type="button" class="btn-sm btn-approve" onclick="saveEngagement(<?= $postId ?>)">Save</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if (($posts['pages'] ?? 1) > 1): ?>
      <div class="pagination">
        <?php if ($posts['page'] > 1): ?>
        <a href="<?= adminEngagementUrl(['page' => $posts['page'] - 1]) ?>" class="page-btn"><i class="fa fa-chevron-left"></i></a>
        <?php endif; ?>
        <?php for ($i = max(1, $posts['page'] - 2); $i <= min($posts['pages'], $posts['page'] + 2); $i++): ?>
        <a href="<?= adminEngagementUrl(['page' => $i]) ?>" class="page-btn <?= $i === $posts['page'] ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($posts['page'] < $posts['pages']): ?>
        <a href="<?= adminEngagementUrl(['page' => $posts['page'] + 1]) ?>" class="page-btn"><i class="fa fa-chevron-right"></i></a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php else: ?>
      <div class="empty-state">
        <i class="fa fa-chart-line"></i>
        <h3>No posts found</h3>
        <p>Filters clear karke dubara try karo.</p>
      </div>
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
async function saveEngagement(postId) {
  const likesInput = document.getElementById(`engagementLikes${postId}`);
  const viewsInput = document.getElementById(`engagementViews${postId}`);
  if (!likesInput || !viewsInput) return;

  const likes = Math.max(0, parseInt(likesInput.value || '0', 10) || 0);
  const views = Math.max(0, parseInt(viewsInput.value || '0', 10) || 0);

  likesInput.value = likes;
  viewsInput.value = views;

  const data = await API.post('/api/admin/posts', {
    action: 'update_engagement',
    post_id: postId,
    likes_count: likes,
    views_count: views
  });

  if (!data.success) {
    Toast.show(data.error || 'Update failed', 'error');
    return;
  }

  document.getElementById(`engagementCurrentLikes${postId}`).innerHTML = `<i class="fa fa-heart"></i> ${Number(data.likes_count || 0).toLocaleString('en-IN')}`;
  document.getElementById(`engagementCurrentViews${postId}`).innerHTML = `<i class="fa fa-eye"></i> ${Number(data.views_count || 0).toLocaleString('en-IN')}`;
  Toast.show(data.message || 'Updated', 'success');
}
</script>
</body>
</html>
