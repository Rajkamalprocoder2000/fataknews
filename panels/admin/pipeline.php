<?php
require_once BASE_PATH . '/includes/bootstrap.php';
Auth::requireRole('super_admin', 'admin');

$pageTitle = 'AI Content Pipeline - FatakNews';
$candidateModel = new ContentCandidateModel();
$categoryModel = new CategoryModel();
$stats = $candidateModel->getStats();
$categories = $categoryModel->getTopLevel();
$categoryMap = [];
foreach ($categories as $category) {
    $categoryMap[(string)$category['slug']] = $category;
}

$status = trim((string)($_GET['status'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$candidates = $candidateModel->getAdminList([
    'status' => $status,
    'category' => $category,
    'q' => $q,
], $page, 15);

$statusBadgeMap = [
    'new' => 'status-pending',
    'reviewed' => 'status-draft',
    'drafted' => 'status-published',
    'ignored' => 'status-rejected',
];
$feeds = ContentPipeline::feedConfigs();
$isAiConfigured = XaiWriter::isConfigured();
$isAutoWriteEnabled = defined('CONTENT_PIPELINE_AUTO_WRITE') && CONTENT_PIPELINE_AUTO_WRITE;
$isAutoPublishEnabled = defined('CONTENT_PIPELINE_AUTO_PUBLISH') && CONTENT_PIPELINE_AUTO_PUBLISH;
$autoMinScore = defined('CONTENT_PIPELINE_AUTO_MIN_SCORE') ? (float)CONTENT_PIPELINE_AUTO_MIN_SCORE : 0;
$autoMaxPerRun = defined('CONTENT_PIPELINE_AUTO_MAX_PER_RUN') ? (int)CONTENT_PIPELINE_AUTO_MAX_PER_RUN : 0;

function pipelineFilterUrl(array $overrides = []): string {
    $query = array_merge($_GET, $overrides);
    $query = array_filter($query, static fn($value) => $value !== '' && $value !== null);
    return '/admin/pipeline' . ($query ? '?' . http_build_query($query) : '');
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
      <a href="/admin/pipeline" class="panel-nav-link active"><i class="fa fa-tower-broadcast"></i> AI Pipeline</a>
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
      <div>
        <h1>AI Content Pipeline</h1>
        <p style="color:var(--muted);font-size:13px;margin-top:2px">Ingest RSS feeds, rank candidates, and automatically turn strong leads into <?= $isAutoPublishEnabled ? 'published articles' : 'editable drafts' ?>.</p>
      </div>
      <div class="panel-header-actions">
        <span style="font-size:13px;color:var(--muted)"><?= count($feeds) ?> feeds configured</span>
        <?php if ($isAutoWriteEnabled): ?>
        <span style="font-size:13px;color:var(--muted)">Auto <?= $isAutoPublishEnabled ? 'publish' : 'draft' ?>: score <?= Helper::sanitize(number_format($autoMinScore, 0)) ?>+, max <?= $autoMaxPerRun ?>/run</span>
        <?php endif; ?>
        <button type="button" class="btn-ghost" onclick="pipelineAction('ingest')"><i class="fa fa-rotate"></i> Run Auto Pipeline</button>
        <a href="/employee/create#ai-generator" class="btn-write"><i class="fa fa-pen"></i> Open Writer</a>
      </div>
    </div>

    <?php if (empty($feeds)): ?>
    <div class="create-widget" style="margin-bottom:18px;background:rgba(255,215,0,0.08);border-color:rgba(255,215,0,0.28)">
      <strong style="display:block;margin-bottom:6px;color:var(--text)">No feeds configured</strong>
      <p style="color:var(--muted);font-size:13px;line-height:1.6">Add `CONTENT_PIPELINE_FEEDS` in `config/local.php` or environment config. Sample definitions are included in `config/local.example.php`.</p>
    </div>
    <?php endif; ?>

    <?php if (!$isAiConfigured): ?>
    <div class="create-widget" style="margin-bottom:18px;background:rgba(41,121,255,0.08);border-color:rgba(41,121,255,0.24)">
      <strong style="display:block;margin-bottom:6px;color:var(--text)">AI provider not configured</strong>
      <p style="color:var(--muted);font-size:13px;line-height:1.6">Candidate ingestion will still work. Draft generation requires `XAI_API_KEY` or `GROQ_API_KEY`.</p>
    </div>
    <?php endif; ?>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,45,45,0.15);color:var(--red)"><i class="fa fa-stream"></i></div>
        <div class="stat-info"><strong><?= $stats['all'] ?></strong><span>Total Candidates</span><span class="stat-trend"><?= $stats['today'] ?> added today</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,215,0,0.15);color:var(--yellow)"><i class="fa fa-signal"></i></div>
        <div class="stat-info"><strong><?= $stats['new'] ?></strong><span>Ready For Review</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(41,121,255,0.15);color:var(--blue)"><i class="fa fa-eye"></i></div>
        <div class="stat-info"><strong><?= $stats['reviewed'] ?></strong><span>Reviewed</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,200,83,0.15);color:var(--green)"><i class="fa fa-file-pen"></i></div>
        <div class="stat-info"><strong><?= $stats['drafted'] ?></strong><span>Drafted</span><span class="stat-trend">Avg score <?= $stats['avg_score'] ?></span></div>
      </div>
    </div>

    <div class="data-table-wrap" style="margin-bottom:24px">
      <div class="table-header" style="gap:14px;flex-wrap:wrap">
        <h3>Filters</h3>
        <form method="get" action="/admin/pipeline" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
          <input type="text" name="q" value="<?= Helper::sanitize($q) ?>" class="form-control" placeholder="Search title, topic, source..." style="width:260px">
          <select name="status" class="form-control" style="width:170px">
            <option value="">All statuses</option>
            <?php foreach (['new', 'reviewed', 'drafted', 'ignored'] as $opt): ?>
            <option value="<?= $opt ?>" <?= $status === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="category" class="form-control" style="width:180px">
            <option value="">All categories</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= Helper::sanitize($cat['slug']) ?>" <?= $category === $cat['slug'] ? 'selected' : '' ?>><?= Helper::sanitize($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn-sm btn-approve">Apply</button>
          <a href="/admin/pipeline" class="btn-sm btn-edit">Reset</a>
        </form>
      </div>

      <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap">
        <a href="<?= pipelineFilterUrl(['status' => null, 'page' => null]) ?>" class="tag-chip" style="<?= $status === '' ? 'background:rgba(255,45,45,0.14);color:var(--red)' : '' ?>">All (<?= $stats['all'] ?>)</a>
        <a href="<?= pipelineFilterUrl(['status' => 'new', 'page' => null]) ?>" class="tag-chip" style="<?= $status === 'new' ? 'background:rgba(255,215,0,0.14);color:var(--yellow)' : '' ?>">New (<?= $stats['new'] ?>)</a>
        <a href="<?= pipelineFilterUrl(['status' => 'reviewed', 'page' => null]) ?>" class="tag-chip" style="<?= $status === 'reviewed' ? 'background:rgba(41,121,255,0.14);color:var(--blue)' : '' ?>">Reviewed (<?= $stats['reviewed'] ?>)</a>
        <a href="<?= pipelineFilterUrl(['status' => 'drafted', 'page' => null]) ?>" class="tag-chip" style="<?= $status === 'drafted' ? 'background:rgba(0,200,83,0.14);color:var(--green)' : '' ?>">Drafted (<?= $stats['drafted'] ?>)</a>
        <a href="<?= pipelineFilterUrl(['status' => 'ignored', 'page' => null]) ?>" class="tag-chip" style="<?= $status === 'ignored' ? 'background:rgba(255,45,45,0.14);color:var(--red)' : '' ?>">Ignored (<?= $stats['ignored'] ?>)</a>
      </div>

      <?php if (!empty($candidates['data'])): ?>
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>Candidate</th>
              <th>Source</th>
              <th>Category</th>
              <th>Score</th>
              <th>Status</th>
              <th>Timing</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($candidates['data'] as $candidate):
              $categoryMeta = $categoryMap[(string)($candidate['category_slug_hint'] ?? '')] ?? null;
              $tags = json_decode((string)($candidate['keyword_tags'] ?? '[]'), true);
              if (!is_array($tags)) {
                  $tags = [];
              }
            ?>
            <tr>
              <td style="max-width:380px">
                <div style="font-weight:700;font-size:13px;line-height:1.45"><?= Helper::sanitize($candidate['title']) ?></div>
                <div style="font-size:12px;color:var(--muted);margin-top:4px"><?= Helper::sanitize(Helper::excerpt((string)($candidate['excerpt'] ?? $candidate['content_snippet'] ?? ''), 120)) ?></div>
                <?php if (!empty($tags)): ?>
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">
                  <?php foreach (array_slice($tags, 0, 4) as $tag): ?>
                  <span class="tag-chip"><?= Helper::sanitize((string)$tag) ?></span>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
              </td>
              <td style="font-size:12px">
                <div style="font-weight:700;color:var(--text)"><?= Helper::sanitize($candidate['source_name']) ?></div>
                <?php if (!empty($candidate['external_url'])): ?>
                <a href="<?= Helper::sanitize($candidate['external_url']) ?>" target="_blank" style="color:var(--red);display:inline-flex;align-items:center;gap:6px;margin-top:4px">
                  <i class="fa fa-arrow-up-right-from-square"></i> Open Source
                </a>
                <?php elseif (!empty($candidate['source_url'])): ?>
                <a href="<?= Helper::sanitize($candidate['source_url']) ?>" target="_blank" style="color:var(--red);display:inline-flex;align-items:center;gap:6px;margin-top:4px">
                  <i class="fa fa-rss"></i> Feed
                </a>
                <?php endif; ?>
              </td>
              <td style="font-size:12px">
                <?php if ($categoryMeta): ?>
                <div style="font-weight:700;color:<?= Helper::sanitize($categoryMeta['color']) ?>"><?= Helper::sanitize($categoryMeta['name']) ?></div>
                <div style="color:var(--muted);margin-top:4px"><?= Helper::sanitize($candidate['topic_key'] ?? '') ?></div>
                <?php else: ?>
                <span style="color:var(--muted)">Unmapped</span>
                <?php endif; ?>
              </td>
              <td style="font-size:12px;color:var(--muted)">
                <div style="font-weight:700;color:var(--text)"><?= Helper::sanitize(number_format((float)$candidate['trend_score'], 2)) ?></div>
                <div>Fresh <?= Helper::sanitize(number_format((float)$candidate['freshness_score'], 1)) ?></div>
                <div>Keyword <?= Helper::sanitize(number_format((float)$candidate['keyword_score'], 1)) ?></div>
                <div>Cluster <?= Helper::sanitize(number_format((float)$candidate['cluster_score'], 1)) ?></div>
              </td>
              <td>
                <span class="status-badge <?= $statusBadgeMap[(string)$candidate['status']] ?? 'status-draft' ?>"><?= ucfirst((string)$candidate['status']) ?></span>
                <?php if (!empty($candidate['draft_post_id'])): ?>
                <div style="font-size:11px;color:var(--muted);margin-top:6px">Post #<?= (int)$candidate['draft_post_id'] ?></div>
                <?php endif; ?>
              </td>
              <td style="font-size:12px;color:var(--muted)">
                <div><?= !empty($candidate['published_at']) ? date('d M Y H:i', strtotime($candidate['published_at'])) : 'No publish time' ?></div>
                <div style="margin-top:4px">Seen <?= Helper::timeAgo($candidate['last_seen_at']) ?></div>
              </td>
              <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                  <?php if (!empty($candidate['draft_post_id'])): ?>
                  <a href="/employee/create?edit=<?= (int)$candidate['draft_post_id'] ?>" class="btn-sm btn-edit">Edit Draft</a>
                  <?php else: ?>
                  <button type="button" class="btn-sm btn-approve" onclick="pipelineAction('generate_draft', <?= (int)$candidate['id'] ?>)">Generate Draft</button>
                  <?php endif; ?>
                  <?php if ($candidate['status'] !== 'reviewed'): ?>
                  <button type="button" class="btn-sm btn-edit" onclick="pipelineAction('set_status', <?= (int)$candidate['id'] ?>, { status: 'reviewed' })">Review</button>
                  <?php endif; ?>
                  <?php if ($candidate['status'] !== 'new'): ?>
                  <button type="button" class="btn-sm btn-edit" onclick="pipelineAction('set_status', <?= (int)$candidate['id'] ?>, { status: 'new' })">Reset</button>
                  <?php endif; ?>
                  <?php if ($candidate['status'] !== 'ignored'): ?>
                  <button type="button" class="btn-sm btn-delete" onclick="pipelineAction('set_status', <?= (int)$candidate['id'] ?>, { status: 'ignored' })">Ignore</button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if (($candidates['pages'] ?? 1) > 1): ?>
      <div class="pagination">
        <?php if (($candidates['page'] ?? 1) > 1): ?>
        <a href="<?= pipelineFilterUrl(['page' => $candidates['page'] - 1]) ?>" class="page-btn"><i class="fa fa-chevron-left"></i></a>
        <?php endif; ?>
        <?php for ($i = max(1, ($candidates['page'] ?? 1) - 2); $i <= min(($candidates['pages'] ?? 1), ($candidates['page'] ?? 1) + 2); $i++): ?>
        <a href="<?= pipelineFilterUrl(['page' => $i]) ?>" class="page-btn <?= $i === ($candidates['page'] ?? 1) ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if (($candidates['page'] ?? 1) < ($candidates['pages'] ?? 1)): ?>
        <a href="<?= pipelineFilterUrl(['page' => $candidates['page'] + 1]) ?>" class="page-btn"><i class="fa fa-chevron-right"></i></a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php else: ?>
      <div class="empty-state">
        <i class="fa fa-tower-broadcast"></i>
        <h3>No candidates found</h3>
        <p>Run ingestion or broaden the current filters.</p>
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
async function pipelineAction(action, candidateId = 0, extra = {}) {
  const payload = { action, candidate_id: candidateId, ...extra };
  const data = await API.post('/api/admin/pipeline', payload);
  if (!data.success) {
    Toast.show(data.error || 'Action failed', 'error');
    return;
  }

  if (action === 'generate_draft' && data.edit_url) {
    Toast.show(data.message || 'Draft ready', 'success');
    setTimeout(() => { window.location.href = data.edit_url; }, 400);
    return;
  }

  if (action === 'ingest' && data.stats) {
    const autoPart = data.stats.auto_write_enabled
      ? `, auto: ${data.stats.auto_processed || 0}${data.stats.auto_publish_enabled ? ` published ${data.stats.auto_published || 0}` : ''}`
      : '';
    const rateLimitPart = data.stats.auto_rate_limited ? ', AI rate-limited, next run will resume' : '';
    Toast.show(`Feeds: ${data.stats.feeds}, items: ${data.stats.items}, created: ${data.stats.created}${autoPart}${rateLimitPart}`, 'success');
    setTimeout(() => window.location.reload(), 700);
    return;
  }

  Toast.show(data.message || 'Updated', 'success');
  setTimeout(() => window.location.reload(), 500);
}
</script>
</body>
</html>
