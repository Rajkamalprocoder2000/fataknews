<?php
require_once BASE_PATH . '/includes/bootstrap.php';
Auth::requireRole('super_admin', 'admin');

$pageTitle = 'News Management - FatakNews';
$db = Database::getInstance();

$status = trim($_GET['status'] ?? '');
$type = trim($_GET['type'] ?? '');
$q = trim($_GET['q'] ?? '');
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
     ORDER BY FIELD(p.status, 'pending', 'draft', 'published', 'rejected', 'archived'), p.created_at DESC",
    $params,
    $page,
    12
);

$stats = [
    'all' => (int)$db->fetchOne("SELECT COUNT(*) c FROM posts")['c'],
    'pending' => (int)$db->fetchOne("SELECT COUNT(*) c FROM posts WHERE status='pending'")['c'],
    'published' => (int)$db->fetchOne("SELECT COUNT(*) c FROM posts WHERE status='published'")['c'],
    'draft' => (int)$db->fetchOne("SELECT COUNT(*) c FROM posts WHERE status='draft'")['c'],
    'rejected' => (int)$db->fetchOne("SELECT COUNT(*) c FROM posts WHERE status='rejected'")['c'],
    'featured' => (int)$db->fetchOne("SELECT COUNT(*) c FROM posts WHERE is_featured=1")['c'],
    'breaking' => (int)$db->fetchOne("SELECT COUNT(*) c FROM posts WHERE is_breaking=1")['c'],
];

function adminFilterUrl(array $overrides = []): string {
    $query = array_merge($_GET, $overrides);
    $query = array_filter($query, fn($value) => $value !== '' && $value !== null);
    return '/admin/news' . ($query ? '?' . http_build_query($query) : '');
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
      <a href="/admin/news" class="panel-nav-link active"><i class="fa fa-newspaper"></i> All Posts <span style="margin-left:auto;background:var(--red);color:#fff;font-size:10px;padding:2px 7px;border-radius:50px"><?= $stats['pending'] ?></span></a>
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
      <div>
        <h1>News Management</h1>
        <p style="color:var(--muted);font-size:13px;margin-top:2px">Moderate, publish, reject, and edit the current story pipeline.</p>
      </div>
      <div style="display:flex;gap:10px;align-items:center">
        <a href="/employee/create#ai-generator" class="btn-ghost"><i class="fa fa-wand-magic-sparkles"></i> AI Draft</a>
        <a href="/employee/create" class="btn-write"><i class="fa fa-pen"></i> New Post</a>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,45,45,0.15);color:var(--red)"><i class="fa fa-newspaper"></i></div>
        <div class="stat-info"><strong><?= $stats['all'] ?></strong><span>Total Posts</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,215,0,0.15);color:var(--yellow)"><i class="fa fa-clock"></i></div>
        <div class="stat-info"><strong><?= $stats['pending'] ?></strong><span>Pending Review</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,200,83,0.15);color:var(--green)"><i class="fa fa-check-circle"></i></div>
        <div class="stat-info"><strong><?= $stats['published'] ?></strong><span>Published</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(41,121,255,0.15);color:var(--blue)"><i class="fa fa-star"></i></div>
        <div class="stat-info"><strong><?= $stats['featured'] ?></strong><span>Featured</span><span class="stat-trend"><?= $stats['breaking'] ?> breaking</span></div>
      </div>
    </div>

    <div class="data-table-wrap" style="margin-bottom:24px">
      <div class="table-header" style="gap:14px;flex-wrap:wrap">
        <h3>Filters</h3>
        <form method="get" action="/admin/news" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
          <input type="text" name="q" value="<?= Helper::sanitize($q) ?>" class="form-control" placeholder="Search title, author, category..." style="width:260px">
          <select name="status" class="form-control" style="width:170px">
            <option value="">All statuses</option>
            <?php foreach (['pending','published','draft','rejected','archived'] as $opt): ?>
            <option value="<?= $opt ?>" <?= $status === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="type" class="form-control" style="width:180px">
            <option value="">All types</option>
            <?php foreach (['news','article','community_post','thought','breaking'] as $opt): ?>
            <option value="<?= $opt ?>" <?= $type === $opt ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $opt)) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn-sm btn-approve">Apply</button>
          <a href="/admin/news" class="btn-sm btn-edit">Reset</a>
        </form>
      </div>
      <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap">
        <a href="<?= adminFilterUrl(['status' => null, 'page' => null]) ?>" class="tag-chip" style="<?= $status === '' ? 'background:rgba(255,45,45,0.14);color:var(--red)' : '' ?>">All (<?= $stats['all'] ?>)</a>
        <a href="<?= adminFilterUrl(['status' => 'pending', 'page' => null]) ?>" class="tag-chip" style="<?= $status === 'pending' ? 'background:rgba(255,215,0,0.14);color:var(--yellow)' : '' ?>">Pending (<?= $stats['pending'] ?>)</a>
        <a href="<?= adminFilterUrl(['status' => 'published', 'page' => null]) ?>" class="tag-chip" style="<?= $status === 'published' ? 'background:rgba(0,200,83,0.14);color:var(--green)' : '' ?>">Published (<?= $stats['published'] ?>)</a>
        <a href="<?= adminFilterUrl(['status' => 'draft', 'page' => null]) ?>" class="tag-chip" style="<?= $status === 'draft' ? 'background:rgba(41,121,255,0.14);color:var(--blue)' : '' ?>">Draft (<?= $stats['draft'] ?>)</a>
        <a href="<?= adminFilterUrl(['status' => 'rejected', 'page' => null]) ?>" class="tag-chip" style="<?= $status === 'rejected' ? 'background:rgba(255,45,45,0.14);color:var(--red)' : '' ?>">Rejected (<?= $stats['rejected'] ?>)</a>
      </div>

      <?php if (!empty($posts['data'])): ?>
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>Post</th>
              <th>Author</th>
              <th>Category</th>
              <th>Type</th>
              <th>Status</th>
              <th>Signals</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($posts['data'] as $post):
              $postUrl = '/' . ($post['category_slug'] ?: 'news') . '/' . $post['slug'];
            ?>
            <tr>
              <td style="max-width:340px">
                <div style="font-weight:700;font-size:13px;line-height:1.4"><?= Helper::sanitize($post['title']) ?></div>
                <div style="font-size:12px;color:var(--muted);margin-top:4px">
                  <?= Helper::sanitize(Helper::excerpt($post['excerpt'] ?: $post['content'], 90)) ?>
                </div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">
                  <?php if ($post['is_breaking']): ?><span class="status-badge status-rejected">Breaking</span><?php endif; ?>
                  <?php if ($post['is_featured']): ?><span class="status-badge status-published">Featured</span><?php endif; ?>
                  <?php if ($post['is_trending']): ?><span class="status-badge status-pending">Trending</span><?php endif; ?>
                </div>
              </td>
              <td style="font-size:13px">
                <div style="font-weight:600"><?= Helper::sanitize($post['full_name']) ?></div>
                <div style="color:var(--muted);font-size:12px">@<?= Helper::sanitize($post['username']) ?></div>
              </td>
              <td style="font-size:13px">
                <?php if ($post['category_name']): ?>
                <span style="color:<?= $post['category_color'] ?>;font-weight:600"><?= Helper::sanitize($post['category_name']) ?></span>
                <?php else: ?>
                <span style="color:var(--muted)">Uncategorized</span>
                <?php endif; ?>
              </td>
              <td><span class="status-badge status-draft"><?= ucwords(str_replace('_', ' ', $post['type'])) ?></span></td>
              <td><span class="status-badge status-<?= $post['status'] ?>"><?= ucfirst($post['status']) ?></span></td>
              <td style="font-size:12px;color:var(--muted)">
                <div><i class="fa fa-eye"></i> <?= Helper::formatNumber($post['views_count']) ?></div>
                <div><i class="fa fa-heart"></i> <?= Helper::formatNumber($post['likes_count']) ?></div>
                <div><i class="fa fa-comment"></i> <?= Helper::formatNumber($post['comments_count']) ?></div>
              </td>
              <td style="font-size:12px;color:var(--muted)">
                <div><?= date('d M Y', strtotime($post['created_at'])) ?></div>
                <div><?= Helper::timeAgo($post['created_at']) ?></div>
              </td>
              <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                  <a href="<?= $postUrl ?>" target="_blank" class="btn-sm btn-edit"><i class="fa fa-eye"></i></a>
                  <a href="/employee/create?edit=<?= $post['id'] ?>" class="btn-sm btn-edit"><i class="fa fa-pen"></i></a>
                  <?php if ($post['status'] === 'pending'): ?>
                  <button class="btn-sm btn-approve" onclick="adminAction('approve',<?= $post['id'] ?>)">Approve</button>
                  <button class="btn-sm btn-reject" onclick="adminAction('reject',<?= $post['id'] ?>)">Reject</button>
                  <?php endif; ?>
                  <button class="btn-sm btn-approve" onclick="adminAction('toggle_featured',<?= $post['id'] ?>)"><?= $post['is_featured'] ? 'Unfeature' : 'Feature' ?></button>
                  <button class="btn-sm btn-edit" onclick="adminAction('toggle_breaking',<?= $post['id'] ?>)"><?= $post['is_breaking'] ? 'Unbreak' : 'Breaking' ?></button>
                  <button class="btn-sm btn-delete" data-confirm="Delete this post permanently?" onclick="adminAction('delete',<?= $post['id'] ?>)">Delete</button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($posts['pages'] > 1): ?>
      <div class="pagination">
        <?php if ($posts['page'] > 1): ?>
        <a href="<?= adminFilterUrl(['page' => $posts['page'] - 1]) ?>" class="page-btn"><i class="fa fa-chevron-left"></i></a>
        <?php endif; ?>
        <?php for ($i = max(1, $posts['page'] - 2); $i <= min($posts['pages'], $posts['page'] + 2); $i++): ?>
        <a href="<?= adminFilterUrl(['page' => $i]) ?>" class="page-btn <?= $i === $posts['page'] ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($posts['page'] < $posts['pages']): ?>
        <a href="<?= adminFilterUrl(['page' => $posts['page'] + 1]) ?>" class="page-btn"><i class="fa fa-chevron-right"></i></a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php else: ?>
      <div class="empty-state">
        <i class="fa fa-newspaper"></i>
        <h3>No posts match this filter</h3>
        <p>Try broadening the search or clearing one of the filters.</p>
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
async function adminAction(action, postId) {
  const reason = action === 'reject' ? prompt('Rejection reason:') : null;
  if (action === 'reject' && reason === null) return;

  const data = await API.post('/api/admin/posts', { action, post_id: postId, reason });
  if (!data.success) {
    Toast.show(data.error || 'Action failed', 'error');
    return;
  }

  const messageMap = {
    toggle_breaking: 'Breaking flag updated',
    toggle_featured: 'Featured flag updated'
  };

  Toast.show(messageMap[action] || data.message || 'Updated', 'success');
  setTimeout(() => window.location.reload(), 700);
}
</script>
</body>
</html>
