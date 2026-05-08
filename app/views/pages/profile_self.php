<?php
Auth::requireLogin();
$user = Auth::user();
$pageTitle = 'My Profile - FatakNews';
$postModel = new PostModel();
$posts = $postModel->getByUser($user['id'], max(1, (int)($_GET['page'] ?? 1)));
$bodyClass = 'profile-page';
include VIEW . 'layouts/header.php';
?>
<div class="post-page" style="grid-template-columns:1fr">
  <main class="post-main">
    <div class="profile-cover"></div>
    <div class="profile-info">
      <div class="profile-avatar-wrap">
        <div style="display:flex;align-items:flex-end;gap:14px;flex-wrap:wrap">
          <img src="<?= Helper::avatarUrl($user['avatar']) ?>" alt="<?= Helper::sanitize($user['full_name']) ?>" class="profile-avatar" width="100" height="100" decoding="async">
          <a href="/settings" class="btn-ghost">Change Photo</a>
        </div>
        <a href="/settings" class="btn-ghost">Account settings</a>
      </div>
      <h1 style="font-family:'Baloo 2',cursive;font-size:34px;line-height:1.1"><?= Helper::sanitize($user['full_name']) ?></h1>
      <p style="color:var(--muted);margin-top:4px">@<?= $user['username'] ?></p>
      <p style="color:var(--muted);margin-top:12px;max-width:760px"><?= Helper::sanitize($user['bio'] ?: 'No bio added yet.') ?></p>
      <div class="profile-stats" style="margin-top:22px">
        <div class="profile-stat"><strong><?= Helper::formatNumber((int)$user['posts_count']) ?></strong><span>Posts</span></div>
        <div class="profile-stat"><strong><?= Helper::formatNumber((int)$user['followers_count']) ?></strong><span>Followers</span></div>
        <div class="profile-stat"><strong><?= Helper::formatNumber((int)$user['following_count']) ?></strong><span>Following</span></div>
        <div class="profile-stat"><strong><?= Helper::formatNumber((int)$user['points']) ?></strong><span>Points</span></div>
      </div>
    </div>

    <div class="widget-title"><i class="fa fa-newspaper"></i> Recent Posts</div>
    <div class="news-feed">
      <?php foreach ($posts['data'] as $index => $post):
        $postUrl = '/' . ($post['category_slug'] ?: 'news') . '/' . $post['slug'];
      ?>
      <article class="news-card">
        <a href="<?= $postUrl ?>"><img src="<?= Helper::thumbnailUrl($post['thumbnail']) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($post['image_alt'] ?? '', $post['title'] ?? '')) ?>" class="news-card-img" width="160" height="110" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>"<?= $index === 0 ? ' fetchpriority="high"' : '' ?> decoding="async"></a>
        <div style="flex:1">
          <div class="news-card-body">
            <h3 class="news-card-title"><a href="<?= $postUrl ?>"><?= Helper::sanitize($post['title']) ?></a></h3>
            <div class="news-card-meta"><span><?= ucfirst($post['status']) ?></span><span><?= Helper::timeAgo($post['created_at']) ?></span></div>
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
      <div class="empty-state"><i class="fa fa-user-pen"></i><h3>No posts from this profile yet</h3></div>
      <?php endif; ?>
    </div>
  </main>
</div>
<?php include VIEW . 'layouts/footer.php'; ?>
