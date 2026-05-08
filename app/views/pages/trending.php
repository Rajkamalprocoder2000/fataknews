<?php
$pageTitle = 'Trending News & Top Stories | FatakNews';
$pageDesc = 'See trending news, most-read stories, and top headlines gaining attention on FatakNews right now.';
$postModel = new PostModel();
$posts = $postModel->getTrending(20);
$canonicalUrl = Helper::siteUrl('trending');
$breadcrumbItems = [
    ['name' => 'Home', 'url' => Helper::siteUrl()],
    ['name' => 'Trending', 'url' => $canonicalUrl],
];
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $pageTitle,
        'description' => $pageDesc,
        'url' => $canonicalUrl,
    ],
    Helper::breadcrumbSchema($breadcrumbItems),
    Helper::collectionItemListSchema($posts ?? [], $canonicalUrl),
];
$bodyClass = 'trending-page';
include VIEW . 'layouts/header.php';
?>
<div class="home-grid">
  <main class="feed-col">
    <div style="background:#fffdfd;border:1px solid #e8e1f3;border-radius:28px;box-shadow:0 22px 54px rgba(53,45,88,.12),0 3px 14px rgba(53,45,88,.06);padding:24px 22px;overflow:hidden">
      <section class="sidebar-widget page-hero page-hero--trending" style="background:transparent;border:none;box-shadow:none;padding:0 0 18px;margin-bottom:18px;border-radius:0">
        <?= Helper::breadcrumbNav($breadcrumbItems) ?>
        <div class="widget-title"><i class="fa fa-fire"></i> Trending</div>
        <p style="color:var(--muted)">This list ranks stories by views first, then freshness.</p>
      </section>
      <div class="news-feed">
        <?php foreach ($posts as $index => $post):
          $postUrl = '/' . ($post['category_slug'] ?: 'news') . '/' . $post['slug'];
        ?>
        <article class="news-card" style="--card-cat-color:<?= Helper::sanitize($post['category_color'] ?: '#FF6B1A') ?>;background:#fffdfd;border:1px solid #e8e1f3;box-shadow:0 18px 40px rgba(53,45,88,.12),0 4px 14px rgba(53,45,88,.06)">
          <a href="<?= $postUrl ?>"><img src="<?= Helper::thumbnailUrl($post['thumbnail']) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($post['image_alt'] ?? '', $post['title'] ?? '')) ?>" class="news-card-img" width="160" height="110" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>"<?= $index === 0 ? ' fetchpriority="high"' : '' ?> decoding="async"></a>
          <div style="flex:1">
            <div class="news-card-body">
              <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                <span style="font-size:12px;font-weight:800;color:var(--border2)">#<?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <?php if (!empty($post['category_name'])): ?>
                <a href="/category/<?= $post['category_slug'] ?>" class="news-card-cat" style="color:<?= $post['category_color'] ?>"><?= Helper::sanitize($post['category_name']) ?></a>
                <?php endif; ?>
              </div>
              <h3 class="news-card-title"><a href="<?= $postUrl ?>"><?= Helper::sanitize($post['title']) ?></a></h3>
              <div class="news-card-meta">
                <img src="<?= Helper::avatarUrl($post['avatar']) ?>" alt="<?= Helper::sanitize($post['full_name']) ?>" width="20" height="20" decoding="async">
                <span><?= Helper::sanitize($post['full_name']) ?></span>
                <span><?= Helper::timeAgo($post['published_at'] ?? $post['created_at']) ?></span>
              </div>
            </div>
            <div class="news-card-actions">
              <span class="action-btn" style="cursor:default"><i class="fa fa-eye"></i> <?= Helper::formatNumber($post['views_count']) ?></span>
              <button class="action-btn" data-react="<?= $post['id'] ?>" data-type="like"><i class="fa fa-heart"></i><span class="react-count"><?= Helper::formatNumber($post['likes_count']) ?></span></button>
              <a href="<?= $postUrl ?>#comments" class="action-btn"><i class="fa fa-comment"></i><span><?= Helper::formatNumber($post['comments_count']) ?></span></a>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
        <?php if (empty($posts)): ?>
        <div class="empty-state"><i class="fa fa-fire"></i><h3>No trending stories yet</h3><p>Publish a few posts and the ranking will start filling in.</p></div>
        <?php endif; ?>
      </div>
    </div>
  </main>
  <aside class="sidebar-col">
    <div class="sidebar-widget" style="background:#fffdfd;border:1px solid #e8e1f3;border-radius:28px;box-shadow:0 22px 54px rgba(53,45,88,.12),0 3px 14px rgba(53,45,88,.06);padding:24px 22px;overflow:hidden">
      <div class="widget-title"><i class="fa fa-circle-info"></i> About This List</div>
      <p style="font-size:14px;color:var(--muted)">Trending is based on `views_count` and recent publish time in the current local database.</p>
    </div>
  </aside>
</div>
<?php include VIEW . 'layouts/mobile_bottom_nav.php'; ?>
<?php include VIEW . 'layouts/footer.php'; ?>
