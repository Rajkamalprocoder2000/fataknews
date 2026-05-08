<?php
$category = (new CategoryModel())->getBySlug($dynamic['slug']);
if (!$category) {
    http_response_code(404);
    include VIEW . 'pages/404.php';
    return;
}
$pageNumber = max(1, (int)($_GET['page'] ?? 1));
$pageTitle = $category['name'] . ' News' . ($pageNumber > 1 ? ' - Page ' . $pageNumber : '') . ' | FatakNews';
$pageDesc = Helper::metaDescription($category['description'] ?: ('Read latest ' . $category['name'] . ' news, headlines, analysis, and live updates from India on FatakNews.'));
$canonicalUrl = Helper::siteUrl('category/' . $category['slug'] . ($pageNumber > 1 ? '?page=' . $pageNumber : ''));
$prevUrl = $pageNumber > 1 ? Helper::siteUrl('category/' . $category['slug'] . ($pageNumber > 2 ? '?page=' . ($pageNumber - 1) : '')) : null;
$pageImage = Helper::sitePublicUrl('assets/images/og-default.svg');
$page = $pageNumber;
$posts = (new PostModel())->getByCategory((int)$category['id'], $page);
$nextUrl = $page < (int)($posts['pages'] ?? 1) ? Helper::siteUrl('category/' . $category['slug'] . '?page=' . ($page + 1)) : null;
$children = (new CategoryModel())->getChildren((int)$category['id']);
$breadcrumbItems = [
    ['name' => 'Home', 'url' => Helper::siteUrl()],
    ['name' => $category['name'], 'url' => $canonicalUrl],
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
    Helper::collectionItemListSchema($posts['data'] ?? [], $canonicalUrl),
];
$bodyClass = 'category-page';
include VIEW . 'layouts/header.php';
?>
<div class="home-grid">
  <main class="feed-col">
    <section class="sidebar-widget category-page-hero" style="--category-accent:<?= Helper::sanitize($category['color']) ?>;margin-bottom:24px">
      <?= Helper::breadcrumbNav($breadcrumbItems) ?>
      <div class="widget-title"><i class="fa <?= $category['icon'] ?>"></i> Category</div>
      <h1 class="category-page-title"><?= Helper::sanitize($category['name']) ?></h1>
      <p class="category-page-desc"><?= Helper::sanitize($category['description'] ?: 'Stories filed under this topic appear here.') ?></p>
    </section>

    <div class="news-feed">
      <?php foreach ($posts['data'] as $index => $post):
        $postUrl = '/' . ($post['category_slug'] ?: 'news') . '/' . $post['slug'];
      ?>
      <article class="news-card">
        <a href="<?= $postUrl ?>"><img src="<?= Helper::thumbnailUrl($post['thumbnail']) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($post['image_alt'] ?? '', $post['title'] ?? '')) ?>" class="news-card-img" width="160" height="110" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>"<?= $index === 0 ? ' fetchpriority="high"' : '' ?> decoding="async"></a>
        <div style="flex:1">
          <div class="news-card-body">
            <h3 class="news-card-title"><a href="<?= $postUrl ?>"><?= Helper::sanitize($post['title']) ?></a></h3>
            <div class="news-card-meta">
              <img src="<?= Helper::avatarUrl($post['avatar']) ?>" alt="<?= Helper::sanitize($post['full_name']) ?>" width="20" height="20" decoding="async">
              <span><?= Helper::sanitize($post['full_name']) ?></span>
              <span><?= Helper::timeAgo($post['published_at'] ?? $post['created_at']) ?></span>
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
      <?php if (empty($posts['data'])): ?>
      <div class="empty-state"><i class="fa fa-folder-open"></i><h3>No posts in <?= Helper::sanitize($category['name']) ?> yet</h3></div>
      <?php endif; ?>
    </div>
  </main>
  <aside class="sidebar-col">
    <div class="sidebar-widget">
      <div class="widget-title"><i class="fa fa-sitemap"></i> Subtopics</div>
      <?php foreach ($children as $child): ?>
      <a href="/category/<?= $child['slug'] ?>" class="tag-chip" style="display:inline-flex;margin:0 8px 8px 0"><?= Helper::sanitize($child['name']) ?></a>
      <?php endforeach; ?>
      <?php if (empty($children)): ?>
      <p style="font-size:14px;color:var(--muted)">This category currently has no child topics.</p>
      <?php endif; ?>
    </div>
  </aside>
</div>
<?php include VIEW . 'layouts/mobile_bottom_nav.php'; ?>
<?php include VIEW . 'layouts/footer.php'; ?>
