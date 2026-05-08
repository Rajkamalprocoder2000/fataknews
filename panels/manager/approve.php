<?php
require_once BASE_PATH . '/includes/bootstrap.php';

Auth::requireRole('super_admin', 'admin', 'manager');

$pageTitle = 'Approve Posts - FatakNews';
$db = Database::getInstance();
$q = trim((string)($_GET['q'] ?? ''));
$typeFilter = trim((string)($_GET['type'] ?? ''));
$allowedTypes = ['news', 'article', 'breaking', 'community_post', 'thought'];
if ($typeFilter !== '' && !in_array($typeFilter, $allowedTypes, true)) {
    $typeFilter = '';
}

$where = ["p.status='pending'"];
$params = [];

if ($q !== '') {
    $where[] = '(p.title LIKE ? OR p.excerpt LIKE ? OR u.full_name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}

if ($typeFilter !== '') {
    $where[] = 'p.type=?';
    $params[] = $typeFilter;
}

$queue = $db->fetchAll(
    "SELECT p.*, u.full_name, u.username, u.avatar, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
     FROM posts p
     JOIN users u ON u.id=p.user_id
     LEFT JOIN categories c ON c.id=p.category_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY p.created_at ASC",
    $params
);

$stats = [
    'pending' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM posts WHERE status='pending'")['c'] ?? 0),
    'today' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM posts WHERE status='pending' AND DATE(created_at)=CURDATE()")['c'] ?? 0),
    'breaking' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM posts WHERE status='pending' AND is_breaking=1")['c'] ?? 0),
    'oldest_hours' => (int)($db->fetchOne("SELECT COALESCE(TIMESTAMPDIFF(HOUR, MIN(created_at), NOW()), 0) AS c FROM posts WHERE status='pending'")['c'] ?? 0),
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
      <a href="/manager/approve" class="panel-nav-link active"><i class="fa fa-check-circle"></i> Approve Posts</a>
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
        <h1>Approval Queue</h1>
        <p style="color:var(--muted);font-size:13px;margin-top:2px">Review all pending editorial submissions in submission order.</p>
      </div>
      <a href="/manager/posts?status=pending" class="btn-write"><i class="fa fa-list"></i> Full Pending List</a>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,215,0,0.15);color:var(--yellow)"><i class="fa fa-inbox"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['pending']) ?></strong><span>Total Pending</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(41,121,255,0.15);color:var(--blue)"><i class="fa fa-calendar-day"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['today']) ?></strong><span>Submitted Today</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,45,45,0.15);color:var(--red)"><i class="fa fa-bolt"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['breaking']) ?></strong><span>Breaking Requests</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,107,26,0.16);color:#FF6B1A"><i class="fa fa-stopwatch"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['oldest_hours']) ?>h</strong><span>Oldest Pending Age</span></div>
      </div>
    </div>

    <div class="data-table-wrap">
      <div class="table-header" style="gap:14px;flex-wrap:wrap">
        <h3>Pending Editorial Queue</h3>
        <form method="get" action="/manager/approve" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
          <input type="text" name="q" value="<?= Helper::sanitize($q) ?>" class="form-control" placeholder="Search title or author..." style="width:240px">
          <select name="type" class="form-control" style="width:180px">
            <option value="">Any type</option>
            <?php foreach ($allowedTypes as $type): ?>
            <option value="<?= $type ?>" <?= $typeFilter === $type ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $type)) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn-sm btn-approve">Apply</button>
          <a href="/manager/approve" class="btn-sm btn-edit">Reset</a>
        </form>
      </div>

      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>Submission</th>
              <th>Reporter</th>
              <th>Category</th>
              <th>Type</th>
              <th>Wait Time</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($queue)): ?>
            <?php foreach ($queue as $post): ?>
            <?php $previewUrl = !empty($post['category_slug']) ? '/' . $post['category_slug'] . '/' . $post['slug'] : '/news/' . $post['slug']; ?>
            <tr>
              <td style="max-width:340px">
                <div style="font-weight:700;font-size:13px"><?= Helper::sanitize($post['title']) ?></div>
                <div style="font-size:12px;color:var(--muted);margin-top:4px"><?= Helper::excerpt($post['excerpt'] ?? '', 110) ?></div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">
                  <?php if ((int)$post['is_breaking'] === 1): ?><span class="status-badge status-rejected">Breaking Flag</span><?php endif; ?>
                  <?php if ((int)$post['allow_comments'] === 1): ?><span class="status-badge status-draft">Comments On</span><?php endif; ?>
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
              <td><span class="status-badge status-draft"><?= ucfirst(str_replace('_', ' ', $post['type'])) ?></span></td>
              <td style="font-size:12px;color:var(--muted)">
                <div><?= Helper::timeAgo($post['created_at']) ?></div>
                <div><?= date('d M Y, h:i A', strtotime($post['created_at'])) ?></div>
              </td>
              <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                  <a href="<?= $previewUrl ?>" target="_blank" class="btn-sm btn-edit">Preview</a>
                  <a href="/employee/create?edit=<?= (int)$post['id'] ?>" class="btn-sm btn-edit">Edit</a>
                  <button class="btn-sm btn-approve" onclick="queueAction('approve', <?= (int)$post['id'] ?>)">Approve</button>
                  <button class="btn-sm btn-reject" onclick="queueAction('reject', <?= (int)$post['id'] ?>)">Reject</button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr>
              <td colspan="6">
                <div class="empty-state" style="padding:28px 0">
                  <i class="fa fa-check-double"></i>
                  <h3>Queue cleared</h3>
                  <p>No pending posts match the current filter.</p>
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
async function queueAction(action, postId) {
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
