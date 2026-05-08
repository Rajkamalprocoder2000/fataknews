<?php
require_once BASE_PATH . '/includes/bootstrap.php';

Auth::requireRole('super_admin', 'admin');

$pageTitle = 'Analytics - FatakNews';
$db = Database::getInstance();

$overview = [
    'published_posts' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM posts WHERE status='published'")['c'] ?? 0),
    'total_views' => (int)($db->fetchOne("SELECT COALESCE(SUM(views_count),0) AS c FROM posts WHERE status='published'")['c'] ?? 0),
    'total_likes' => (int)($db->fetchOne("SELECT COALESCE(SUM(likes_count),0) AS c FROM posts WHERE status='published'")['c'] ?? 0),
    'total_comments' => (int)($db->fetchOne("SELECT COALESCE(SUM(comments_count),0) AS c FROM posts WHERE status='published'")['c'] ?? 0),
    'total_bookmarks' => (int)($db->fetchOne("SELECT COALESCE(SUM(bookmarks_count),0) AS c FROM posts WHERE status='published'")['c'] ?? 0),
    'active_users' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM users WHERE is_active=1")['c'] ?? 0),
    'verified_users' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM users WHERE is_verified=1")['c'] ?? 0),
    'live_ads' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM ads WHERE is_active=1")['c'] ?? 0),
];

$engagementRate = $overview['total_views'] > 0
    ? round((($overview['total_likes'] + $overview['total_comments'] + $overview['total_bookmarks']) / $overview['total_views']) * 100, 2)
    : 0;

$chartDays = [];
$chartViews = [];
$chartPosts = [];
for ($i = 13; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartDays[] = date('d M', strtotime($date));
    $chartViews[] = (int)($db->fetchOne(
        "SELECT COALESCE(SUM(views_count),0) AS c FROM posts WHERE status='published' AND DATE(created_at)=?",
        [$date]
    )['c'] ?? 0);
    $chartPosts[] = (int)($db->fetchOne(
        "SELECT COUNT(*) AS c FROM posts WHERE status='published' AND DATE(created_at)=?",
        [$date]
    )['c'] ?? 0);
}

$topCategories = $db->fetchAll(
    "SELECT c.name,
            c.color,
            COUNT(p.id) AS post_count,
            COALESCE(SUM(p.views_count),0) AS total_views
     FROM categories c
     LEFT JOIN posts p ON p.category_id=c.id AND p.status='published'
     GROUP BY c.id, c.name, c.color
     ORDER BY total_views DESC, post_count DESC, c.name ASC
     LIMIT 8"
);

$topStories = $db->fetchAll(
    "SELECT p.id, p.title, p.slug, p.views_count, p.likes_count, p.comments_count, p.reading_time,
            c.slug AS category_slug, c.name AS category_name, u.full_name
     FROM posts p
     JOIN users u ON u.id=p.user_id
     LEFT JOIN categories c ON c.id=p.category_id
     WHERE p.status='published'
     ORDER BY p.views_count DESC, p.likes_count DESC, p.comments_count DESC
     LIMIT 8"
);

$authorLeaders = $db->fetchAll(
    "SELECT u.full_name,
            u.username,
            COUNT(p.id) AS post_count,
            COALESCE(SUM(p.views_count),0) AS total_views,
            COALESCE(SUM(p.likes_count),0) AS total_likes
     FROM users u
     LEFT JOIN posts p ON p.user_id=u.id AND p.status='published'
     WHERE u.is_active=1
     GROUP BY u.id, u.full_name, u.username
     HAVING post_count > 0
     ORDER BY total_views DESC, total_likes DESC
     LIMIT 6"
);

$typeBreakdown = $db->fetchAll(
    "SELECT type, COUNT(*) AS item_count, COALESCE(SUM(views_count),0) AS total_views
     FROM posts
     WHERE status='published'
     GROUP BY type
     ORDER BY item_count DESC, total_views DESC"
);

$adPerformance = $db->fetchAll(
    "SELECT id, title, type, position, is_active, impressions, clicks,
            CASE WHEN impressions > 0 THEN ROUND((clicks / impressions) * 100, 2) ELSE 0 END AS ctr
     FROM ads
     ORDER BY is_active DESC, clicks DESC, impressions DESC, id DESC
     LIMIT 8"
);

$categoryNames = array_map(static fn($row) => $row['name'], $topCategories);
$categoryViews = array_map(static fn($row) => (int)$row['total_views'], $topCategories);
$typeLabels = array_map(static fn($row) => ucwords(str_replace('_', ' ', $row['type'])), $typeBreakdown);
$typeCounts = array_map(static fn($row) => (int)$row['item_count'], $typeBreakdown);
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
      <a href="/admin" class="panel-nav-link"><i class="fa fa-th-large"></i> Overview</a>
      <a href="/admin/analytics" class="panel-nav-link active"><i class="fa fa-chart-bar"></i> Analytics</a>
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
          <h1>Analytics</h1>
          <p style="color:var(--muted);font-size:13px;margin-top:2px">Editorial performance, category reach, and campaign health in one view.</p>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        <span style="font-size:13px;color:var(--muted)"><?= date('D, d M Y') ?></span>
        <a href="/admin/news" class="btn-sm btn-edit">Open Posts</a>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,45,45,0.15);color:var(--red)"><i class="fa fa-eye"></i></div>
        <div class="stat-info">
          <strong><?= Helper::formatNumber($overview['total_views']) ?></strong>
          <span>Total Published Views</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(41,121,255,0.15);color:var(--blue)"><i class="fa fa-newspaper"></i></div>
        <div class="stat-info">
          <strong><?= Helper::formatNumber($overview['published_posts']) ?></strong>
          <span>Published Stories</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,200,83,0.15);color:var(--green)"><i class="fa fa-bolt"></i></div>
        <div class="stat-info">
          <strong><?= $engagementRate ?>%</strong>
          <span>Engagement Rate</span>
          <span class="stat-trend"><?= Helper::formatNumber($overview['total_likes']) ?> likes + <?= Helper::formatNumber($overview['total_comments']) ?> comments</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,215,0,0.15);color:var(--yellow)"><i class="fa fa-users"></i></div>
        <div class="stat-info">
          <strong><?= Helper::formatNumber($overview['active_users']) ?></strong>
          <span>Active Users</span>
          <span class="stat-trend"><?= Helper::formatNumber($overview['verified_users']) ?> verified</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(170,0,255,0.15);color:#AA00FF"><i class="fa fa-ad"></i></div>
        <div class="stat-info">
          <strong><?= Helper::formatNumber($overview['live_ads']) ?></strong>
          <span>Live Campaigns</span>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1.6fr 1fr;gap:20px;margin-bottom:24px">
      <div class="data-table-wrap">
        <div class="table-header"><h3>Views vs Published Posts</h3></div>
        <div style="padding:20px"><canvas id="trafficChart" height="110"></canvas></div>
      </div>
      <div class="data-table-wrap">
        <div class="table-header"><h3>Content Type Mix</h3></div>
        <div style="padding:20px"><canvas id="typeChart" height="220"></canvas></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
      <div class="data-table-wrap">
        <div class="table-header"><h3>Top Categories By Reach</h3></div>
        <div style="padding:20px"><canvas id="categoryChart" height="220"></canvas></div>
      </div>
      <div class="data-table-wrap">
        <div class="table-header"><h3>Top Authors</h3></div>
        <div style="padding:14px 18px">
          <?php foreach ($authorLeaders as $author): ?>
          <div style="display:flex;justify-content:space-between;gap:12px;padding:12px 4px;border-bottom:1px solid var(--border)">
            <div>
              <div style="font-weight:700;font-size:13px"><?= Helper::sanitize($author['full_name']) ?></div>
              <div style="font-size:12px;color:var(--muted)">@<?= Helper::sanitize($author['username']) ?> · <?= Helper::formatNumber((int)$author['post_count']) ?> posts</div>
            </div>
            <div style="text-align:right">
              <div style="font-size:13px;font-weight:700"><?= Helper::formatNumber((int)$author['total_views']) ?> views</div>
              <div style="font-size:12px;color:var(--muted)"><?= Helper::formatNumber((int)$author['total_likes']) ?> likes</div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($authorLeaders)): ?>
          <div class="empty-state" style="padding:28px 0">
            <i class="fa fa-users"></i>
            <h3>No author analytics yet</h3>
            <p>Publish a few stories to populate rankings.</p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1.3fr 1fr;gap:20px">
      <div class="data-table-wrap">
        <div class="table-header"><h3>Top Stories</h3></div>
        <div style="overflow-x:auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Story</th>
                <th>Author</th>
                <th>Category</th>
                <th>Views</th>
                <th>Signals</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($topStories as $story): ?>
              <tr>
                <td style="max-width:320px">
                  <div style="font-weight:700;font-size:13px"><?= Helper::sanitize($story['title']) ?></div>
                  <div style="font-size:12px;color:var(--muted);margin-top:4px">
                    <a href="/<?= Helper::sanitize($story['category_slug'] ?? 'news') ?>/<?= Helper::sanitize($story['slug']) ?>" target="_blank" style="color:var(--muted)">Open story</a>
                  </div>
                </td>
                <td style="font-size:13px"><?= Helper::sanitize($story['full_name']) ?></td>
                <td style="font-size:13px"><?= Helper::sanitize($story['category_name'] ?? 'General') ?></td>
                <td style="font-size:13px;font-weight:700"><?= Helper::formatNumber((int)$story['views_count']) ?></td>
                <td style="font-size:12px;color:var(--muted)">
                  <div><?= Helper::formatNumber((int)$story['likes_count']) ?> likes</div>
                  <div><?= Helper::formatNumber((int)$story['comments_count']) ?> comments · <?= (int)$story['reading_time'] ?> min</div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($topStories)): ?>
              <tr>
                <td colspan="5">
                  <div class="empty-state" style="padding:28px 0">
                    <i class="fa fa-newspaper"></i>
                    <h3>No story analytics yet</h3>
                    <p>Once published posts exist, performance rows will appear here.</p>
                  </div>
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="data-table-wrap">
        <div class="table-header"><h3>Ad Performance</h3></div>
        <div style="padding:12px 18px">
          <?php foreach ($adPerformance as $ad): ?>
          <div style="display:flex;justify-content:space-between;gap:12px;padding:12px 4px;border-bottom:1px solid var(--border)">
            <div>
              <div style="font-weight:700;font-size:13px"><?= Helper::sanitize($ad['title']) ?></div>
              <div style="font-size:12px;color:var(--muted)"><?= Helper::sanitize($ad['position']) ?> · <?= ucfirst($ad['type']) ?></div>
            </div>
            <div style="text-align:right">
              <div style="font-size:13px;font-weight:700"><?= Helper::formatNumber((int)$ad['clicks']) ?> clicks</div>
              <div style="font-size:12px;color:var(--muted)"><?= Helper::formatNumber((int)$ad['impressions']) ?> imp · <?= $ad['ctr'] ?>% CTR</div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($adPerformance)): ?>
          <div class="empty-state" style="padding:28px 0">
            <i class="fa fa-ad"></i>
            <h3>No ad data yet</h3>
            <p>Create campaigns in the ads module to start tracking them.</p>
          </div>
          <?php endif; ?>
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
const trafficCtx = document.getElementById('trafficChart');
if (trafficCtx && typeof Chart !== 'undefined') {
  new Chart(trafficCtx, {
    type: 'line',
    data: {
      labels: <?= json_encode($chartDays) ?>,
      datasets: [
        {
          label: 'Views',
          data: <?= json_encode($chartViews) ?>,
          borderColor: '#FF2D2D',
          backgroundColor: 'rgba(255,45,45,0.14)',
          fill: true,
          tension: 0.35,
          yAxisID: 'y'
        },
        {
          label: 'Posts',
          data: <?= json_encode($chartPosts) ?>,
          borderColor: '#2979FF',
          backgroundColor: 'rgba(41,121,255,0.14)',
          tension: 0.35,
          yAxisID: 'y1'
        }
      ]
    },
    options: {
      responsive: true,
      plugins: { legend: { labels: { color: '#F0F0F5' } } },
      scales: {
        x: { ticks: { color: '#A7A7BA' }, grid: { color: 'rgba(255,255,255,0.04)' } },
        y: { ticks: { color: '#A7A7BA' }, grid: { color: 'rgba(255,255,255,0.04)' } },
        y1: { position: 'right', ticks: { color: '#A7A7BA' }, grid: { drawOnChartArea: false } }
      }
    }
  });
}

const categoryCtx = document.getElementById('categoryChart');
if (categoryCtx && typeof Chart !== 'undefined') {
  new Chart(categoryCtx, {
    type: 'bar',
    data: {
      labels: <?= json_encode($categoryNames) ?>,
      datasets: [{
        label: 'Views',
        data: <?= json_encode($categoryViews) ?>,
        backgroundColor: ['#FF2D2D','#FF6B1A','#00C853','#2979FF','#AA00FF','#00BCD4','#FFD700','#FF6B6B'],
        borderRadius: 10
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        x: { ticks: { color: '#A7A7BA' }, grid: { display: false } },
        y: { ticks: { color: '#A7A7BA' }, grid: { color: 'rgba(255,255,255,0.04)' } }
      }
    }
  });
}

const typeCtx = document.getElementById('typeChart');
if (typeCtx && typeof Chart !== 'undefined') {
  new Chart(typeCtx, {
    type: 'doughnut',
    data: {
      labels: <?= json_encode($typeLabels) ?>,
      datasets: [{
        data: <?= json_encode($typeCounts) ?>,
        backgroundColor: ['#FF2D2D','#2979FF','#00C853','#FF6B1A','#AA00FF'],
        borderWidth: 0
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: {
          position: 'bottom',
          labels: { color: '#F0F0F5', padding: 16 }
        }
      }
    }
  });
}

</script>
</body>
</html>
