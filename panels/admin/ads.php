<?php
require_once BASE_PATH . '/includes/bootstrap.php';

Auth::requireRole('super_admin', 'admin');

$pageTitle = 'Advertisements - FatakNews';
$db = Database::getInstance();
$editId = max(0, (int)($_GET['edit'] ?? 0));

$ads = $db->fetchAll("SELECT * FROM ads ORDER BY is_active DESC, created_at DESC, id DESC");
$editAd = $editId > 0 ? $db->fetchOne("SELECT * FROM ads WHERE id=?", [$editId]) : null;

$stats = [
    'total' => count($ads),
    'active' => count(array_filter($ads, fn($row) => (int)$row['is_active'] === 1)),
    'banner' => count(array_filter($ads, fn($row) => $row['type'] === 'banner')),
    'clicks' => array_reduce($ads, fn($carry, $row) => $carry + (int)$row['clicks'], 0),
];

$defaultForm = [
    'id' => 0,
    'title' => '',
    'type' => 'banner',
    'position' => 'homepage_top',
    'image' => '',
    'link' => '',
    'code' => '',
    'is_active' => 1,
    'start_date' => '',
    'end_date' => '',
];

$formAd = array_merge($defaultForm, $editAd ?? []);

$positionOptions = [
    'homepage_top' => 'Homepage Top',
    'homepage_sidebar' => 'Homepage Sidebar',
    'article_inline' => 'Article Inline',
    'article_sidebar' => 'Article Sidebar',
    'footer_strip' => 'Footer Strip',
    'popup_global' => 'Popup Global',
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
      <div style="font-size:11px;color:var(--red);font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-top:6px">Admin Panel</div>
    </div>
    <nav class="panel-nav">
      <div class="panel-nav-section">Dashboard</div>
      <a href="/admin" class="panel-nav-link"><i class="fa fa-th-large"></i> Overview</a>
      <a href="/admin/analytics" class="panel-nav-link"><i class="fa fa-chart-bar"></i> Analytics</a>
      <div class="panel-nav-section">Content</div>
      <a href="/admin/pipeline" class="panel-nav-link"><i class="fa fa-tower-broadcast"></i> AI Pipeline</a>
      <a href="/admin/news" class="panel-nav-link"><i class="fa fa-newspaper"></i> All Posts</a>
      <a href="/admin/engagement" class="panel-nav-link"><i class="fa fa-chart-line"></i> Like &amp; View Manage</a>
      <a href="/admin/categories" class="panel-nav-link"><i class="fa fa-folder"></i> Categories</a>
      <a href="/admin/ads" class="panel-nav-link active"><i class="fa fa-ad"></i> Advertisements</a>
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
        <h1>Advertisements</h1>
        <p style="color:var(--muted);font-size:13px;margin-top:2px">Manage campaign slots, creative links, and active ad placements.</p>
      </div>
      <div style="display:flex;gap:10px;align-items:center">
        <?php if ($editAd): ?>
        <a href="/admin/ads" class="btn-sm btn-edit">New Ad</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,45,45,0.15);color:var(--red)"><i class="fa fa-ad"></i></div>
        <div class="stat-info"><strong><?= $stats['total'] ?></strong><span>Total Ads</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,200,83,0.15);color:var(--green)"><i class="fa fa-toggle-on"></i></div>
        <div class="stat-info"><strong><?= $stats['active'] ?></strong><span>Active Ads</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(41,121,255,0.15);color:var(--blue)"><i class="fa fa-image"></i></div>
        <div class="stat-info"><strong><?= $stats['banner'] ?></strong><span>Banner Creatives</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,215,0,0.15);color:var(--yellow)"><i class="fa fa-mouse-pointer"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber($stats['clicks']) ?></strong><span>Total Clicks</span></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:minmax(320px,430px) 1fr;gap:20px;align-items:start">
      <div class="data-table-wrap">
        <div class="table-header">
          <h3><?= $editAd ? 'Edit Advertisement' : 'Create Advertisement' ?></h3>
        </div>
        <form id="adForm" style="padding:20px;display:flex;flex-direction:column;gap:14px">
          <input type="hidden" name="ad_id" value="<?= (int)$formAd['id'] ?>">
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Title</label>
            <input type="text" name="title" class="form-control" value="<?= Helper::sanitize($formAd['title']) ?>" required>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Type</label>
              <select name="type" class="form-control">
                <?php foreach (['banner','sidebar','inline','popup','video'] as $type): ?>
                <option value="<?= $type ?>" <?= $formAd['type'] === $type ? 'selected' : '' ?>><?= ucfirst($type) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Position</label>
              <select name="position" class="form-control">
                <?php foreach ($positionOptions as $value => $label): ?>
                <option value="<?= $value ?>" <?= $formAd['position'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Image URL</label>
            <input type="text" name="image" class="form-control" value="<?= Helper::sanitize($formAd['image']) ?>" placeholder="https://example.com/banner.jpg">
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Target Link</label>
            <input type="text" name="link" class="form-control" value="<?= Helper::sanitize($formAd['link']) ?>" placeholder="https://advertiser.example/campaign">
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Embed Code</label>
            <textarea name="code" class="form-control" rows="4" placeholder="<script>...</script>"><?= Helper::sanitize($formAd['code']) ?></textarea>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Start Date</label>
              <input type="date" name="start_date" class="form-control" value="<?= Helper::sanitize($formAd['start_date']) ?>">
            </div>
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">End Date</label>
              <input type="date" name="end_date" class="form-control" value="<?= Helper::sanitize($formAd['end_date']) ?>">
            </div>
          </div>
          <label style="display:flex;gap:10px;align-items:center;font-size:13px;color:var(--text)">
            <input type="checkbox" name="is_active" value="1" <?= !empty($formAd['is_active']) ? 'checked' : '' ?>>
            Active
          </label>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button type="submit" class="btn-sm btn-approve"><?= $editAd ? 'Update Ad' : 'Create Ad' ?></button>
            <?php if ($editAd): ?>
            <a href="/admin/ads" class="btn-sm btn-edit">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div class="data-table-wrap">
        <div class="table-header">
          <h3>Active Inventory</h3>
          <span style="font-size:13px;color:var(--muted)"><?= $stats['total'] ?> records</span>
        </div>
        <div style="overflow-x:auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Creative</th>
                <th>Type</th>
                <th>Placement</th>
                <th>Status</th>
                <th>Performance</th>
                <th>Schedule</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($ads)): ?>
              <?php foreach ($ads as $ad): ?>
              <?php
                $ctr = (int)$ad['impressions'] > 0 ? round(((int)$ad['clicks'] / (int)$ad['impressions']) * 100, 2) : 0;
              ?>
              <tr>
                <td style="max-width:280px">
                  <div style="font-weight:700"><?= Helper::sanitize($ad['title']) ?></div>
                  <div style="font-size:12px;color:var(--muted);margin-top:4px">
                    <?= $ad['image'] ? Helper::sanitize($ad['image']) : 'Custom code creative' ?>
                  </div>
                </td>
                <td><span class="status-badge status-draft"><?= ucfirst($ad['type']) ?></span></td>
                <td style="font-size:13px"><?= Helper::sanitize($positionOptions[$ad['position']] ?? $ad['position']) ?></td>
                <td>
                  <span class="status-badge <?= (int)$ad['is_active'] === 1 ? 'status-published' : 'status-rejected' ?>">
                    <?= (int)$ad['is_active'] === 1 ? 'Live' : 'Paused' ?>
                  </span>
                </td>
                <td style="font-size:12px;color:var(--muted)">
                  <div><?= Helper::formatNumber((int)$ad['impressions']) ?> impressions</div>
                  <div><?= Helper::formatNumber((int)$ad['clicks']) ?> clicks · <?= $ctr ?>% CTR</div>
                </td>
                <td style="font-size:12px;color:var(--muted)">
                  <div><?= $ad['start_date'] ? Helper::sanitize($ad['start_date']) : 'Immediate' ?></div>
                  <div><?= $ad['end_date'] ? 'to ' . Helper::sanitize($ad['end_date']) : 'No end date' ?></div>
                </td>
                <td>
                  <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <a href="/admin/ads?edit=<?= (int)$ad['id'] ?>" class="btn-sm btn-edit">Edit</a>
                    <button class="btn-sm <?= (int)$ad['is_active'] === 1 ? 'btn-reject' : 'btn-approve' ?>" onclick="adAction('toggle_active', <?= (int)$ad['id'] ?>)">
                      <?= (int)$ad['is_active'] === 1 ? 'Pause' : 'Activate' ?>
                    </button>
                    <button class="btn-sm btn-delete" data-confirm="Delete this advertisement?" onclick="adAction('delete', <?= (int)$ad['id'] ?>)">Delete</button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php else: ?>
              <tr>
                <td colspan="7">
                  <div class="empty-state" style="padding:28px 0">
                    <i class="fa fa-ad"></i>
                    <h3>No advertisements yet</h3>
                    <p>Create your first campaign from the form.</p>
                  </div>
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
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
document.getElementById('adForm').addEventListener('submit', async (event) => {
  event.preventDefault();

  const form = event.currentTarget;
  const formData = new FormData(form);
  const adId = formData.get('ad_id');
  const payload = {
    action: adId && adId !== '0' ? 'update' : 'create',
    ad_id: Number(adId || 0),
    title: formData.get('title'),
    type: formData.get('type'),
    position: formData.get('position'),
    image: formData.get('image'),
    link: formData.get('link'),
    code: formData.get('code'),
    start_date: formData.get('start_date'),
    end_date: formData.get('end_date'),
    is_active: formData.get('is_active') ? 1 : 0
  };

  const data = await API.post('/api/admin/ads', payload);
  if (!data.success) {
    Toast.show(data.error || 'Action failed', 'error');
    return;
  }

  Toast.show(data.message || 'Saved', 'success');
  setTimeout(() => window.location.href = '/admin/ads', 700);
});

async function adAction(action, adId) {
  const data = await API.post('/api/admin/ads', { action, ad_id: adId });
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
