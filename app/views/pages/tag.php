<?php
$db = Database::getInstance();
$tag = $db->fetchOne(
    "SELECT t.*, COUNT(DISTINCT p.id) AS posts_count,
            MAX(COALESCE(p.updated_at, p.published_at, p.created_at)) AS lastmod
     FROM tags t
     LEFT JOIN post_tags pt ON pt.tag_id=t.id
     LEFT JOIN posts p ON p.id=pt.post_id AND p.status='published'
     WHERE t.slug=?
     GROUP BY t.id
     LIMIT 1",
    [$dynamic['slug']]
);
if (!$tag) {
    http_response_code(404);
    include VIEW . 'pages/404.php';
    return;
}
$quality = Helper::tagQualityReport($tag);
$page = max(1, (int)($_GET['page'] ?? 1));
$pageTitle = $quality['indexable']
    ? ($tag['name'] . ' News & Tagged Stories' . ($page > 1 ? ' - Page ' . $page : '') . ' | FatakNews')
    : ('#' . $tag['name'] . ' Archive' . ($page > 1 ? ' - Page ' . $page : '') . ' | FatakNews');
$pageDesc = $quality['indexable']
    ? Helper::metaDescription('Read the latest stories, headlines, and updates tagged ' . $tag['name'] . ' on FatakNews.')
    : ('Archive page for #' . $tag['name'] . ' on FatakNews. This tag is available for browsing but is not treated as a primary search landing page.');
$posts = $db->paginate(
    "SELECT p.*, u.full_name, u.avatar, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
     FROM post_tags pt
     JOIN posts p ON pt.post_id=p.id
     JOIN users u ON p.user_id=u.id
     LEFT JOIN categories c ON p.category_id=c.id
     WHERE pt.tag_id=? AND p.status='published'
     ORDER BY p.published_at DESC",
    [$tag['id']],
    $page
);
$canonicalUrl = Helper::siteUrl('tag/' . $tag['slug'] . ($page > 1 ? '?page=' . $page : ''));
$prevUrl = $page > 1 ? Helper::siteUrl('tag/' . $tag['slug'] . ($page > 2 ? '?page=' . ($page - 1) : '')) : null;
$nextUrl = $page < (int)($posts['pages'] ?? 1) ? Helper::siteUrl('tag/' . $tag['slug'] . '?page=' . ($page + 1)) : null;
$metaRobots = $quality['indexable'] ? 'index,follow,max-image-preview:large' : 'noindex,follow';
$breadcrumbItems = [
    ['name' => 'Home', 'url' => Helper::siteUrl()],
    ['name' => '#' . $tag['name'], 'url' => $canonicalUrl],
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
$bodyClass = 'tag-page';
include VIEW . 'layouts/header.php';
?>
<div class="home-grid">
  <main class="feed-col">
    <section class="sidebar-widget" style="margin-bottom:24px">
      <?= Helper::breadcrumbNav($breadcrumbItems) ?>
      <div class="widget-title"><i class="fa fa-hashtag"></i> Tag</div>
      <h1 style="font-family:'Baloo 2',cursive;font-size:38px;line-height:1.1">#<?= Helper::sanitize($tag['name']) ?></h1>
      <p style="color:var(--muted);margin-top:8px"><?= (int)$quality['posts_count'] ?> published stor<?= (int)$quality['posts_count'] === 1 ? 'y' : 'ies' ?> linked to this tag.</p>
      <?php if (!$quality['indexable']): ?>
      <div style="margin-top:14px;padding:12px 14px;border-radius:16px;border:1px solid rgba(255,159,26,.22);background:rgba(255,159,26,.10);color:#8A5A00;font-size:13px;line-height:1.55">
        <strong style="display:block;margin-bottom:6px;color:#A54F00">Low-value tag archive</strong>
        <?= Helper::sanitize($quality['reasons'][0] ?? 'This tag is available for browsing, but it is not indexed for search.') ?>
      </div>
      <?php endif; ?>
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
      <div class="empty-state"><i class="fa fa-hashtag"></i><h3>No stories are linked to this tag yet</h3></div>
      <?php endif; ?>
    </div>
  </main>
  <aside class="sidebar-col">
    <div class="sidebar-widget">
      <div class="widget-title"><i class="fa fa-tag"></i> Tag SEO</div>
      <p style="font-size:14px;color:var(--muted);margin-bottom:8px">Slug: <?= Helper::sanitize($tag['slug']) ?></p>
      <p style="font-size:14px;color:var(--muted);margin-bottom:0">Search status: <strong style="color:var(--text)"><?= $quality['indexable'] ? 'Indexable' : 'Noindex' ?></strong></p>
      <?php if (!$quality['indexable'] && !empty($quality['reasons'])): ?>
      <div style="margin-top:12px;display:flex;flex-direction:column;gap:8px">
        <?php foreach ($quality['reasons'] as $reason): ?>
        <div style="padding:10px 12px;border-radius:12px;background:var(--bg3);border:1px solid var(--border);font-size:12px;line-height:1.5;color:var(--muted)"><?= Helper::sanitize($reason) ?></div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </aside>
</div>
<?php include VIEW . 'layouts/footer.php'; ?>
