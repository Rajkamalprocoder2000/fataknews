<?php
$q = trim($_GET['q'] ?? '');
$pageTitle = $q !== '' ? 'Search results for "' . $q . '" - FatakNews' : 'Search - FatakNews';
$pageDesc = $q !== '' ? 'Search results on FatakNews for "' . $q . '".' : 'Search FatakNews stories, topics, and people.';
$metaRobots = 'noindex,follow';
$db = Database::getInstance();
$userModel = new UserModel();
$page = max(1, (int)($_GET['page'] ?? 1));
$results = ['data' => [], 'pages' => 1, 'page' => 1];
$users = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $results = $db->paginate(
        "SELECT p.*, u.full_name, u.avatar, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
         FROM posts p
         JOIN users u ON p.user_id=u.id
         LEFT JOIN categories c ON p.category_id=c.id
         WHERE p.status='published' AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ?)
         ORDER BY p.published_at DESC",
        [$like, $like, $like],
        $page
    );
    $users = $userModel->search($q, 6);
}
$searchQueryString = [];
if ($q !== '') {
    $searchQueryString['q'] = $q;
}
if ($page > 1) {
    $searchQueryString['page'] = $page;
}
$canonicalUrl = Helper::siteUrl('search' . (!empty($searchQueryString) ? '?' . http_build_query($searchQueryString) : ''));
$prevQuery = $q !== '' ? ['q' => $q] : [];
$prevUrl = $page > 1 ? Helper::siteUrl('search' . '?' . http_build_query($prevQuery + ($page > 2 ? ['page' => $page - 1] : []))) : null;
$nextUrl = $page < (int)($results['pages'] ?? 1) ? Helper::siteUrl('search' . '?' . http_build_query(($q !== '' ? ['q' => $q] : []) + ['page' => $page + 1])) : null;
$bodyClass = 'search-page';
include VIEW . 'layouts/header.php';
?>
<div class="home-grid">
  <main class="feed-col">
    <section class="sidebar-widget" style="margin-bottom:24px">
      <div class="widget-title"><i class="fa fa-magnifying-glass"></i> Search</div>
      <form action="/search" method="get">
        <input class="form-control" type="text" name="q" value="<?= Helper::sanitize($q) ?>" placeholder="Search posts, topics, and people...">
      </form>
    </section>

    <?php if ($q === ''): ?>
    <div class="empty-state"><i class="fa fa-search"></i><h3>Type something to search</h3><p>Results will appear for matching posts and people.</p></div>
    <?php else: ?>
      <?php if (!empty($users)): ?>
      <section class="sidebar-widget" style="margin-bottom:24px">
        <div class="widget-title"><i class="fa fa-users"></i> People</div>
        <?php foreach ($users as $user): ?>
        <div class="suggest-item">
          <a href="/@<?= $user['username'] ?>"><img src="<?= Helper::avatarUrl($user['avatar']) ?>" alt="<?= Helper::sanitize($user['full_name']) ?>" class="suggest-avatar" width="40" height="40" loading="lazy" decoding="async"></a>
          <div class="suggest-info">
            <strong><a href="/@<?= $user['username'] ?>"><?= Helper::sanitize($user['full_name']) ?></a></strong>
            <span>@<?= $user['username'] ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </section>
      <?php endif; ?>

      <div class="news-feed">
        <?php foreach ($results['data'] as $index => $post):
          $postUrl = '/' . ($post['category_slug'] ?: 'news') . '/' . $post['slug'];
        ?>
        <article class="news-card">
          <a href="<?= $postUrl ?>"><img src="<?= Helper::thumbnailUrl($post['thumbnail']) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($post['image_alt'] ?? '', $post['title'] ?? '')) ?>" class="news-card-img" width="160" height="110" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>"<?= $index === 0 ? ' fetchpriority="high"' : '' ?> decoding="async"></a>
          <div style="flex:1">
            <div class="news-card-body">
              <?php if (!empty($post['category_name'])): ?>
              <a href="/category/<?= $post['category_slug'] ?>" class="news-card-cat" style="color:<?= $post['category_color'] ?>"><?= Helper::sanitize($post['category_name']) ?></a>
              <?php endif; ?>
              <h3 class="news-card-title"><a href="<?= $postUrl ?>"><?= Helper::sanitize($post['title']) ?></a></h3>
              <div style="font-size:13px;color:var(--muted)"><?= Helper::sanitize(Helper::excerpt($post['excerpt'] ?: $post['content'], 140)) ?></div>
            </div>
            <div class="news-card-actions">
              <button class="action-btn" data-react="<?= $post['id'] ?>" data-type="like"><i class="fa fa-heart"></i><span class="react-count"><?= Helper::formatNumber($post['likes_count']) ?></span></button>
              <a href="<?= $postUrl ?>#comments" class="action-btn"><i class="fa fa-comment"></i><span><?= Helper::formatNumber($post['comments_count']) ?></span></a>
              <button class="action-btn" data-bookmark="<?= $post['id'] ?>"><i class="fa fa-bookmark"></i></button>
              <span class="action-btn" style="margin-left:auto;cursor:default"><i class="fa fa-eye"></i> <?= Helper::formatNumber($post['views_count']) ?></span>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>

      <?php if (empty($results['data']) && empty($users)): ?>
      <div class="empty-state"><i class="fa fa-ban"></i><h3>No results for "<?= Helper::sanitize($q) ?>"</h3><p>Try broader keywords or check spelling.</p></div>
      <?php endif; ?>
    <?php endif; ?>
  </main>
  <aside class="sidebar-col">
    <div class="sidebar-widget">
      <div class="widget-title"><i class="fa fa-lightbulb"></i> Search Tips</div>
      <p style="font-size:14px;color:var(--muted)">Use names, topics, or category words. The local search uses title, excerpt, content, and usernames.</p>
    </div>
  </aside>
</div>
<?php include VIEW . 'layouts/footer.php'; ?>
