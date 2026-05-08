<?php
$page = max(1, (int)($_GET['page'] ?? 1));
$isPersonalizedFeed = Auth::check();
$pageTitle = $isPersonalizedFeed
    ? 'Your Feed | FatakNews'
    : ('Latest News Feed' . ($page > 1 ? ' - Page ' . $page : '') . ' | FatakNews');
$pageDesc = $isPersonalizedFeed
    ? 'Your personalized FatakNews feed with stories from followed accounts and featured coverage.'
    : 'Browse the latest breaking news, headlines, and published stories from the FatakNews newsroom.';
$metaRobots = $isPersonalizedFeed ? 'noindex,follow' : 'index,follow,max-image-preview:large';
$postModel = new PostModel();
$feed = $isPersonalizedFeed ? $postModel->getFeed(Auth::id(), $page) : $postModel->getLatest($page);
$basePath = '/feed';
$canonicalUrl = Helper::siteUrl(ltrim($basePath, '/') . ($page > 1 ? '?page=' . $page : ''));
$prevUrl = $page > 1 ? Helper::siteUrl(ltrim($basePath, '/') . ($page > 2 ? '?page=' . ($page - 1) : '')) : null;
$nextUrl = $page < (int)($feed['pages'] ?? 1) ? Helper::siteUrl(ltrim($basePath, '/') . '?page=' . ($page + 1)) : null;
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $pageTitle,
        'description' => $pageDesc,
        'url' => $canonicalUrl,
    ],
    Helper::collectionItemListSchema($feed['data'] ?? [], $canonicalUrl),
];
include VIEW . 'layouts/header.php';
?>
<div class="home-grid">
  <main class="feed-col">
    <section class="sidebar-widget" style="margin-bottom:24px;background:linear-gradient(135deg,rgba(255,45,45,0.15),rgba(170,0,255,0.08))">
      <div class="widget-title"><i class="fa fa-layer-group"></i> Feed</div>
      <h1 style="font-family:'Baloo 2',cursive;font-size:36px;line-height:1.1;margin-bottom:8px"><?= Auth::check() ? 'Your personalized stream' : 'Latest newsroom updates' ?></h1>
      <p style="color:var(--muted);max-width:620px"><?= Auth::check() ? 'Featured and followed content appears here first.' : 'Sign in to turn this into a personalized feed. Until then, you are seeing the latest published news.' ?></p>
    </section>

    <div class="news-feed">
      <?php foreach ($feed['data'] as $index => $post):
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
            <div class="news-card-meta">
              <img src="<?= Helper::avatarUrl($post['avatar']) ?>" alt="<?= Helper::sanitize($post['full_name']) ?>" width="20" height="20" decoding="async">
              <span><?= Helper::sanitize($post['full_name']) ?></span>
              <span><?= Helper::timeAgo($post['published_at'] ?? $post['created_at']) ?></span>
              <span><?= $post['reading_time'] ?> min read</span>
            </div>
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

      <?php if (empty($feed['data'])): ?>
      <div class="empty-state"><i class="fa fa-stream"></i><h3>No items in the feed yet</h3><p>Once posts are published, they will appear here.</p></div>
      <?php endif; ?>
    </div>
  </main>

  <aside class="sidebar-col">
    <div class="sidebar-widget">
      <div class="widget-title"><i class="fa fa-sparkles"></i> Quick Links</div>
      <a href="/trending" class="tag-chip">Trending</a>
      <a href="/community" class="tag-chip">Community</a>
      <a href="/search" class="tag-chip">Search</a>
      <?php if (!Auth::check()): ?>
      <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
        <a href="/login" class="btn-write" style="width:100%;justify-content:center">Login for personalized feed</a>
      </div>
      <?php endif; ?>
    </div>
  </aside>
</div>
<?php include VIEW . 'layouts/footer.php'; ?>
