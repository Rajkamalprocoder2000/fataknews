<?php
Auth::requireLogin();
$pageTitle = 'Bookmarks - FatakNews';
$db = Database::getInstance();
$items = $db->fetchAll(
    "SELECT p.*, u.full_name, u.avatar, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
     FROM bookmarks b
     JOIN posts p ON b.post_id=p.id
     JOIN users u ON p.user_id=u.id
     LEFT JOIN categories c ON p.category_id=c.id
     WHERE b.user_id=?
     ORDER BY b.created_at DESC",
    [Auth::id()]
);
include VIEW . 'layouts/header.php';
?>
<div class="home-grid">
  <main class="feed-col">
    <section class="sidebar-widget" style="margin-bottom:24px">
      <div class="widget-title"><i class="fa fa-bookmark"></i> Saved</div>
      <h1 style="font-family:'Baloo 2',cursive;font-size:38px;line-height:1.1">Your bookmarked stories</h1>
    </section>
    <div class="news-feed">
      <?php foreach ($items as $index => $post):
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
      <?php if (empty($items)): ?>
      <div class="empty-state"><i class="fa fa-bookmark"></i><h3>No bookmarks yet</h3><p>Save a story and it will appear here.</p></div>
      <?php endif; ?>
    </div>
  </main>
  <aside class="sidebar-col">
    <div class="sidebar-widget">
      <div class="widget-title"><i class="fa fa-circle-info"></i> Tip</div>
      <p style="font-size:14px;color:var(--muted)">Use the bookmark action on any story card to build a personal reading list.</p>
    </div>
  </aside>
</div>
<?php include VIEW . 'layouts/footer.php'; ?>
