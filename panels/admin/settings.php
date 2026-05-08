<?php
require_once BASE_PATH . '/includes/bootstrap.php';

Auth::requireRole('super_admin', 'admin');

$pageTitle = 'Admin Settings - FatakNews';
$db = Database::getInstance();
$rows = $db->fetchAll("SELECT `key`, `value`, `group`, `updated_at` FROM settings ORDER BY FIELD(`group`, 'general', 'ticker', 'social', 'analytics', 'mail'), `key` ASC");

$defaults = [
    'site_name' => 'FatakNews.in',
    'site_tagline' => 'Breaking News Every Second',
    'site_email' => 'info@fataknews.in',
    'site_phone' => '',
    'posts_per_page' => '20',
    'allow_registration' => '1',
    'maintenance_mode' => '0',
    'breaking_news' => '',
    'facebook_url' => '',
    'twitter_url' => '',
    'instagram_url' => '',
    'youtube_url' => '',
    'whatsapp_url' => '',
    'indeed_url' => '',
    'google_analytics' => '',
    'smtp_host' => '',
    'smtp_port' => '587',
    'smtp_user' => '',
    'smtp_pass' => '',
];

$settings = $defaults;
$groupCounts = [
    'general' => 0,
    'ticker' => 0,
    'social' => 0,
    'analytics' => 0,
    'mail' => 0,
];
$lastUpdated = null;

foreach ($rows as $row) {
    $settings[$row['key']] = (string)($row['value'] ?? '');
    if (isset($groupCounts[$row['group']]) && trim((string)$row['value']) !== '') {
        $groupCounts[$row['group']]++;
    }
    if ($lastUpdated === null || strtotime($row['updated_at']) > strtotime($lastUpdated)) {
        $lastUpdated = $row['updated_at'];
    }
}

$socialActive = 0;
foreach (['facebook_url', 'twitter_url', 'instagram_url', 'youtube_url', 'whatsapp_url', 'indeed_url'] as $socialKey) {
    if (trim($settings[$socialKey]) !== '') {
        $socialActive++;
    }
}

$stats = [
    'configured' => count(array_filter($settings, static fn($value) => trim((string)$value) !== '')),
    'maintenance' => $settings['maintenance_mode'] === '1',
    'registration' => $settings['allow_registration'] === '1',
    'social_active' => $socialActive,
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
      <a href="/admin/ads" class="panel-nav-link"><i class="fa fa-ad"></i> Advertisements</a>
      <div class="panel-nav-section">Users</div>
      <a href="/admin/users" class="panel-nav-link"><i class="fa fa-users"></i> All Users</a>
      <a href="/hr" class="panel-nav-link"><i class="fa fa-id-card"></i> HR Module</a>
      <div class="panel-nav-section">System</div>
      <a href="/admin/settings" class="panel-nav-link active"><i class="fa fa-cog"></i> Settings</a>
      <a href="/" class="panel-nav-link" target="_blank"><i class="fa fa-external-link-alt"></i> View Site</a>
      <a href="/logout" class="panel-nav-link" style="color:var(--red)"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </nav>
  </aside>

  <main class="panel-main">
    <div class="panel-header">
      <div>
        <h1>Admin Settings</h1>
        <p style="color:var(--muted);font-size:13px;margin-top:2px">Manage site identity, ticker text, integrations, and mail delivery from one place.</p>
      </div>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <span class="status-badge <?= $stats['maintenance'] ? 'status-rejected' : 'status-published' ?>">
          <?= $stats['maintenance'] ? 'Maintenance On' : 'Live' ?>
        </span>
        <span style="font-size:12px;color:var(--muted)">
          <?= $lastUpdated ? 'Updated ' . Helper::timeAgo($lastUpdated) : 'Using defaults' ?>
        </span>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,45,45,0.15);color:var(--red)"><i class="fa fa-sliders-h"></i></div>
        <div class="stat-info"><strong><?= $stats['configured'] ?></strong><span>Configured Values</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,200,83,0.15);color:var(--green)"><i class="fa fa-user-plus"></i></div>
        <div class="stat-info"><strong><?= $stats['registration'] ? 'Open' : 'Closed' ?></strong><span>Registration</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(41,121,255,0.15);color:var(--blue)"><i class="fa fa-share-nodes"></i></div>
        <div class="stat-info"><strong><?= $stats['social_active'] ?>/6</strong><span>Social Links Live</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,215,0,0.15);color:var(--yellow)"><i class="fa fa-envelope"></i></div>
        <div class="stat-info"><strong><?= $groupCounts['mail'] ?></strong><span>Mail Fields Filled</span></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:minmax(340px,1.25fr) minmax(300px,0.75fr);gap:20px;align-items:start">
      <form id="settingsForm" class="data-table-wrap">
        <div class="table-header">
          <h3>System Configuration</h3>
          <button type="submit" class="btn-sm btn-approve">Save Settings</button>
        </div>

        <div style="padding:20px;display:grid;gap:18px">
          <section style="display:grid;gap:14px">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
              <h4 style="margin:0;font-size:15px">General</h4>
              <span style="font-size:12px;color:var(--muted)"><?= $groupCounts['general'] ?> filled</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Site Name</label>
                <input type="text" name="site_name" class="form-control" value="<?= Helper::sanitize($settings['site_name']) ?>" required>
              </div>
              <div>
                <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Posts Per Page</label>
                <input type="number" name="posts_per_page" class="form-control" min="1" max="100" value="<?= (int)$settings['posts_per_page'] ?>">
              </div>
            </div>
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Site Tagline</label>
              <input type="text" name="site_tagline" class="form-control" value="<?= Helper::sanitize($settings['site_tagline']) ?>">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Site Email</label>
                <input type="email" name="site_email" class="form-control" value="<?= Helper::sanitize($settings['site_email']) ?>" required>
              </div>
              <div>
                <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Site Phone</label>
                <input type="text" name="site_phone" class="form-control" value="<?= Helper::sanitize($settings['site_phone']) ?>" placeholder="+91-XXXXXXXXXX">
              </div>
            </div>
            <div style="display:flex;gap:18px;flex-wrap:wrap">
              <label style="display:flex;gap:10px;align-items:center;font-size:13px;color:var(--text)">
                <input type="checkbox" name="allow_registration" value="1" <?= $settings['allow_registration'] === '1' ? 'checked' : '' ?>>
                Allow registration
              </label>
              <label style="display:flex;gap:10px;align-items:center;font-size:13px;color:var(--text)">
                <input type="checkbox" name="maintenance_mode" value="1" <?= $settings['maintenance_mode'] === '1' ? 'checked' : '' ?>>
                Maintenance mode
              </label>
            </div>
          </section>

          <section style="display:grid;gap:14px;padding-top:18px;border-top:1px solid var(--border)">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
              <h4 style="margin:0;font-size:15px">Ticker and Social</h4>
              <span style="font-size:12px;color:var(--muted)"><?= $groupCounts['ticker'] + $groupCounts['social'] ?> filled</span>
            </div>
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Breaking News Ticker</label>
              <textarea name="breaking_news" class="form-control" rows="3" placeholder="Headline one | Headline two | Headline three"><?= Helper::sanitize($settings['breaking_news']) ?></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Facebook URL</label>
                <input type="url" name="facebook_url" class="form-control" value="<?= Helper::sanitize($settings['facebook_url']) ?>" placeholder="https://facebook.com/yourpage">
              </div>
              <div>
                <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Twitter/X URL</label>
                <input type="url" name="twitter_url" class="form-control" value="<?= Helper::sanitize($settings['twitter_url']) ?>" placeholder="https://x.com/yourhandle">
              </div>
              <div>
                <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Instagram URL</label>
                <input type="url" name="instagram_url" class="form-control" value="<?= Helper::sanitize($settings['instagram_url']) ?>" placeholder="https://instagram.com/yourhandle">
              </div>
              <div>
                <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">YouTube URL</label>
                <input type="url" name="youtube_url" class="form-control" value="<?= Helper::sanitize($settings['youtube_url']) ?>" placeholder="https://youtube.com/@channel">
              </div>
              <div>
                <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">WhatsApp URL</label>
                <input type="url" name="whatsapp_url" class="form-control" value="<?= Helper::sanitize($settings['whatsapp_url']) ?>" placeholder="https://wa.me/911234567890">
              </div>
              <div>
                <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Indeed URL</label>
                <input type="url" name="indeed_url" class="form-control" value="<?= Helper::sanitize($settings['indeed_url']) ?>" placeholder="https://www.indeed.com/cmp/Your-Company">
              </div>
            </div>
          </section>

          <section style="display:grid;gap:14px;padding-top:18px;border-top:1px solid var(--border)">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
              <h4 style="margin:0;font-size:15px">Analytics and Mail</h4>
              <span style="font-size:12px;color:var(--muted)"><?= $groupCounts['analytics'] + $groupCounts['mail'] ?> filled</span>
            </div>
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Google Analytics ID / Script</label>
              <textarea name="google_analytics" class="form-control" rows="3" placeholder="G-XXXXXXXXXX or embed code"><?= Helper::sanitize($settings['google_analytics']) ?></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 120px;gap:12px">
              <div>
                <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">SMTP Host</label>
                <input type="text" name="smtp_host" class="form-control" value="<?= Helper::sanitize($settings['smtp_host']) ?>" placeholder="smtp.hostinger.com">
              </div>
              <div>
                <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">SMTP Port</label>
                <input type="number" name="smtp_port" class="form-control" min="1" max="65535" value="<?= (int)$settings['smtp_port'] ?>">
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">SMTP User</label>
                <input type="text" name="smtp_user" class="form-control" value="<?= Helper::sanitize($settings['smtp_user']) ?>" placeholder="info@fataknews.in">
              </div>
              <div>
                <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">SMTP Password</label>
                <input type="text" name="smtp_pass" class="form-control" value="<?= Helper::sanitize($settings['smtp_pass']) ?>" placeholder="App password or SMTP password">
              </div>
            </div>
          </section>
        </div>
      </form>

      <div style="display:grid;gap:20px">
        <div class="data-table-wrap">
          <div class="table-header"><h3>Current Snapshot</h3></div>
          <div style="padding:20px;display:grid;gap:12px">
            <div style="padding:14px;border:1px solid var(--border);border-radius:14px;background:var(--bg2)">
              <div style="font-size:12px;color:var(--muted);margin-bottom:6px">Brand</div>
              <div style="font-size:16px;font-weight:700"><?= Helper::sanitize($settings['site_name']) ?></div>
              <div style="font-size:12px;color:var(--muted);margin-top:4px"><?= Helper::sanitize($settings['site_tagline']) ?></div>
            </div>
            <div style="padding:14px;border:1px solid var(--border);border-radius:14px;background:var(--bg2)">
              <div style="font-size:12px;color:var(--muted);margin-bottom:8px">Operations</div>
              <div style="display:flex;gap:8px;flex-wrap:wrap">
                <span class="status-badge <?= $settings['allow_registration'] === '1' ? 'status-published' : 'status-rejected' ?>">
                  <?= $settings['allow_registration'] === '1' ? 'Registration Open' : 'Registration Closed' ?>
                </span>
                <span class="status-badge <?= $settings['maintenance_mode'] === '1' ? 'status-rejected' : 'status-published' ?>">
                  <?= $settings['maintenance_mode'] === '1' ? 'Maintenance On' : 'Site Live' ?>
                </span>
              </div>
            </div>
            <div style="padding:14px;border:1px solid var(--border);border-radius:14px;background:var(--bg2)">
              <div style="font-size:12px;color:var(--muted);margin-bottom:6px">Ticker Preview</div>
              <div style="font-size:13px;line-height:1.5;color:var(--text)">
                <?= Helper::sanitize($settings['breaking_news'] !== '' ? $settings['breaking_news'] : 'No ticker text configured yet.') ?>
              </div>
            </div>
          </div>
        </div>

        <div class="data-table-wrap">
          <div class="table-header"><h3>Configuration Checklist</h3></div>
          <div style="padding:20px;display:grid;gap:10px">
            <?php
            $checks = [
                ['Site email', trim($settings['site_email']) !== ''],
                ['Breaking ticker', trim($settings['breaking_news']) !== ''],
                ['At least one social link', $stats['social_active'] > 0],
                ['SMTP host', trim($settings['smtp_host']) !== ''],
                ['SMTP user', trim($settings['smtp_user']) !== ''],
                ['Analytics configured', trim($settings['google_analytics']) !== ''],
            ];
            foreach ($checks as [$label, $ok]):
            ?>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:var(--bg2)">
              <span style="font-size:13px"><?= $label ?></span>
              <span class="status-badge <?= $ok ? 'status-published' : 'status-draft' ?>"><?= $ok ? 'Ready' : 'Pending' ?></span>
            </div>
            <?php endforeach; ?>
          </div>
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
document.getElementById('settingsForm').addEventListener('submit', async (event) => {
  event.preventDefault();

  const form = event.currentTarget;
  const formData = new FormData(form);
  const payload = {
    action: 'save',
    settings: {
      site_name: formData.get('site_name'),
      site_tagline: formData.get('site_tagline'),
      site_email: formData.get('site_email'),
      site_phone: formData.get('site_phone'),
      posts_per_page: formData.get('posts_per_page'),
      allow_registration: form.querySelector('[name=\"allow_registration\"]').checked ? 1 : 0,
      maintenance_mode: form.querySelector('[name=\"maintenance_mode\"]').checked ? 1 : 0,
      breaking_news: formData.get('breaking_news'),
      facebook_url: formData.get('facebook_url'),
      twitter_url: formData.get('twitter_url'),
      instagram_url: formData.get('instagram_url'),
      youtube_url: formData.get('youtube_url'),
      whatsapp_url: formData.get('whatsapp_url'),
      indeed_url: formData.get('indeed_url'),
      google_analytics: formData.get('google_analytics'),
      smtp_host: formData.get('smtp_host'),
      smtp_port: formData.get('smtp_port'),
      smtp_user: formData.get('smtp_user'),
      smtp_pass: formData.get('smtp_pass')
    }
  };

  const data = await API.post('/api/admin/settings', payload);
  if (!data.success) {
    Toast.show(data.error || 'Action failed', 'error');
    return;
  }

  Toast.show(data.message || 'Settings saved', 'success');
  setTimeout(() => window.location.reload(), 700);
});
</script>
</body>
</html>
