<?php
require_once BASE_PATH . '/includes/bootstrap.php';

Auth::requireRole('super_admin', 'admin', 'manager');

$pageTitle = 'Manager Posts - FatakNews';
$db = Database::getInstance();
$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$authorFilter = (int)($_GET['author'] ?? 0);

$allowedStatuses = ['draft', 'pending', 'published', 'rejected'];
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(p.title LIKE ? OR p.excerpt LIKE ? OR u.full_name LIKE ? OR u.username LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}

if ($statusFilter !== '') {
    $where[] = 'p.status=?';
    $params[] = $statusFilter;
}

if ($authorFilter > 0) {
    $where[] = 'p.user_id=?';
    $params[] = $authorFilter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$posts = $db->fetchAll(
    "SELECT p.*, u.full_name, u.username, u.avatar, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
     FROM posts p
     JOIN users u ON u.id=p.user_id
     LEFT JOIN categories c ON c.id=p.category_id
     $whereSql
     ORDER BY FIELD(p.status, 'pending', 'draft', 'published', 'rejected'), p.created_at DESC",
    $params
);

$authors = $db->fetchAll(
    "SELECT u.id, u.full_name, u.username, r.name AS role_name
     FROM users u
     JOIN roles r ON r.id=u.role_id
     WHERE r.slug IN ('reporter', 'editor')
     ORDER BY u.full_name ASC"
);

$stats = [
    'total' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM posts")['c'] ?? 0),
    'pending' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM posts WHERE status='pending'")['c'] ?? 0),
    'published' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM posts WHERE status='published'")['c'] ?? 0),
    'rejected' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM posts WHERE status='rejected'")['c'] ?? 0),
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
      <a href="/manager/posts" class="panel-nav-link active"><i class="fa fa-newspaper"></i> All Posts</a>
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
        <h1>Post Control Room</h1>
        <p style="color:var(--muted);font-size:13px;margin-top:2px">Search the newsroom inventory, review status, and take publishing actions.</p>
      </div>
      <div style="display:flex;gap:10px">
        <a href="/manager/approve" class="btn-write" style="background:var(--yellow);color:#000"><i class="fa fa-clock"></i> <?= $stats['pending'] ?> Pending</a>
        <a href="/employee/create" class="btn-write"><i class="fa fa-pen"></i> Write Post</a>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,107,26,0.16);color:#FF6B1A"><i class="fa fa-layer-group"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['total']) ?></strong><span>Total Posts</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,215,0,0.15);color:var(--yellow)"><i class="fa fa-hourglass-half"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['pending']) ?></strong><span>Pending Review</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,200,83,0.15);color:var(--green)"><i class="fa fa-check-circle"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['published']) ?></strong><span>Published</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,45,45,0.15);color:var(--red)"><i class="fa fa-times-circle"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['rejected']) ?></strong><span>Rejected</span></div>
      </div>
    </div>

    <div class="data-table-wrap">
      <div class="table-header" style="gap:14px;flex-wrap:wrap">
        <h3>All Editorial Posts</h3>
        <form method="get" action="/manager/posts" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
          <input type="text" name="q" value="<?= Helper::sanitize($q) ?>" class="form-control" placeholder="Search title, author, excerpt..." style="width:240px">
          <select name="status" class="form-control" style="width:160px">
            <option value="">Any status</option>
            <?php foreach ($allowedStatuses as $status): ?>
            <option value="<?= $status ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="author" class="form-control" style="width:200px">
            <option value="0">All authors</option>
            <?php foreach ($authors as $author): ?>
            <option value="<?= (int)$author['id'] ?>" <?= $authorFilter === (int)$author['id'] ? 'selected' : '' ?>><?= Helper::sanitize($author['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn-sm btn-approve">Apply</button>
          <a href="/manager/posts" class="btn-sm btn-edit">Reset</a>
        </form>
      </div>

      <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap">
        <a href="/manager/posts" class="tag-chip" style="<?= $q === '' && $statusFilter === '' && $authorFilter === 0 ? 'background:rgba(255,107,26,0.16);color:#FF6B1A' : '' ?>">All</a>
        <a href="/manager/posts?status=pending" class="tag-chip" style="<?= $statusFilter === 'pending' ? 'background:rgba(255,215,0,0.16);color:var(--yellow)' : '' ?>">Pending</a>
        <a href="/manager/posts?status=published" class="tag-chip" style="<?= $statusFilter === 'published' ? 'background:rgba(0,200,83,0.16);color:var(--green)' : '' ?>">Published</a>
        <a href="/manager/posts?status=draft" class="tag-chip" style="<?= $statusFilter === 'draft' ? 'background:rgba(41,121,255,0.16);color:var(--blue)' : '' ?>">Drafts</a>
        <a href="/manager/posts?status=rejected" class="tag-chip" style="<?= $statusFilter === 'rejected' ? 'background:rgba(255,45,45,0.16);color:var(--red)' : '' ?>">Rejected</a>
      </div>

      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>Post</th>
              <th>Author</th>
              <th>Category</th>
              <th>Status</th>
              <th>Views</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($posts)): ?>
            <?php foreach ($posts as $post): ?>
            <?php $previewUrl = !empty($post['category_slug']) ? '/' . $post['category_slug'] . '/' . $post['slug'] : '/news/' . $post['slug']; ?>
            <tr>
              <td style="max-width:300px">
                <div style="font-weight:700;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= Helper::sanitize($post['title']) ?></div>
                <div style="font-size:12px;color:var(--muted);margin-top:4px"><?= Helper::excerpt($post['excerpt'] ?? '', 90) ?></div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">
                  <?php if ((int)$post['is_breaking'] === 1): ?><span class="status-badge status-rejected">Breaking</span><?php endif; ?>
                  <?php if ((int)$post['is_featured'] === 1): ?><span class="status-badge status-pending">Featured</span><?php endif; ?>
                </div>
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <img src="<?= Helper::avatarUrl($post['avatar']) ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover">
                  <div>
                    <div style="font-size:13px;font-weight:600"><?= Helper::sanitize($post['full_name']) ?></div>
                    <div style="font-size:12px;color:var(--muted)">@<?= Helper::sanitize($post['username']) ?></div>
                  </div>
                </div>
              </td>
              <td style="font-size:13px"><?php if ($post['category_name']): ?><span style="color:<?= Helper::sanitize($post['category_color']) ?>"><?= Helper::sanitize($post['category_name']) ?></span><?php else: ?>-<?php endif; ?></td>
              <td><span class="status-badge status-<?= Helper::sanitize($post['status']) ?>"><?= ucfirst($post['status']) ?></span></td>
              <td style="font-size:13px"><?= Helper::formatNumber((int)$post['views_count']) ?></td>
              <td style="font-size:12px;color:var(--muted)">
                <div><?= date('d M Y', strtotime($post['created_at'])) ?></div>
                <div><?= Helper::timeAgo($post['created_at']) ?></div>
              </td>
              <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                  <a href="<?= $previewUrl ?>" target="_blank" class="btn-sm btn-edit">Preview</a>
                  <a href="/employee/create?edit=<?= (int)$post['id'] ?>" class="btn-sm btn-edit">Edit</a>
                  <?php if ($post['status'] === 'pending'): ?>
                  <button class="btn-sm btn-approve" onclick="postAction('approve', <?= (int)$post['id'] ?>)">Approve</button>
                  <button class="btn-sm btn-reject" onclick="postAction('reject', <?= (int)$post['id'] ?>)">Reject</button>
                  <?php endif; ?>
                  <button class="btn-sm btn-approve" onclick="postAction('toggle_featured', <?= (int)$post['id'] ?>)"><?= (int)$post['is_featured'] === 1 ? 'Unfeature' : 'Feature' ?></button>
                  <button class="btn-sm <?= (int)$post['is_breaking'] === 1 ? 'btn-reject' : 'btn-approve' ?>" onclick="postAction('toggle_breaking', <?= (int)$post['id'] ?>)"><?= (int)$post['is_breaking'] === 1 ? 'Normal' : 'Breaking' ?></button>
                  <button class="btn-sm btn-delete" data-confirm="Delete this post?" onclick="postAction('delete', <?= (int)$post['id'] ?>)">Delete</button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr>
              <td colspan="7">
                <div class="empty-state" style="padding:28px 0">
                  <i class="fa fa-newspaper"></i>
                  <h3>No posts found</h3>
                  <p>Try a different filter or search term.</p>
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
async function postAction(action, postId) {
  const reason = action === 'reject' ? prompt('Rejection reason:') : null;
  if (action === 'reject' && reason === null) return;

  const data = await API.post('/api/admin/posts', { action, post_id: postId, reason });
  if (!data.success) {
    Toast.show(data.error || 'Action failed', 'error');
    return;
  }

  Toast.show(data.message || 'Updated', 'success');
  setTimeout(() => window.location.reload(), 700);
}
</script>
</body>
</html>
