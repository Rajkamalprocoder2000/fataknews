<?php
Auth::requireLogin();
$pageTitle = 'Notifications - FatakNews';
$db = Database::getInstance();
$items = $db->fetchAll(
    "SELECT n.*, u.username AS actor_username, u.full_name AS actor_name, u.avatar AS actor_avatar
     FROM notifications n
     LEFT JOIN users u ON n.actor_id=u.id
     WHERE n.user_id=?
     ORDER BY n.created_at DESC
     LIMIT 50",
    [Auth::id()]
);
include VIEW . 'layouts/header.php';
?>
<div class="home-grid">
  <main class="feed-col">
    <section class="sidebar-widget" style="margin-bottom:24px">
      <div class="widget-title"><i class="fa fa-bell"></i> Notifications</div>
      <h1 style="font-family:'Baloo 2',cursive;font-size:38px;line-height:1.1">Latest account activity</h1>
    </section>
    <div class="sidebar-widget">
      <?php foreach ($items as $item): ?>
      <div class="notif-item <?= $item['is_read'] ? '' : 'unread' ?>" style="padding-left:0;padding-right:0">
        <img src="<?= Helper::avatarUrl($item['actor_avatar'] ?? '') ?>" alt="<?= Helper::sanitize($item['actor_name'] ?: 'System') ?>" width="20" height="20" decoding="async" onerror="this.onerror=null;this.src='<?= Helper::avatarUrl(null) ?>'">
        <div>
          <p><strong><?= Helper::sanitize($item['actor_name'] ?: 'System') ?></strong> <?= Helper::sanitize($item['message'] ?? '') ?></p>
          <time><?= Helper::timeAgo($item['created_at']) ?></time>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($items)): ?>
      <div class="empty-state"><i class="fa fa-bell-slash"></i><h3>No notifications yet</h3><p>Follows, comments, and other actions will show up here.</p></div>
      <?php endif; ?>
    </div>
  </main>
  <aside class="sidebar-col">
    <div class="sidebar-widget">
      <div class="widget-title"><i class="fa fa-check"></i> Actions</div>
      <button id="markAllRead" class="btn-write" style="width:100%;justify-content:center">Mark all read</button>
    </div>
  </aside>
</div>
<?php include VIEW . 'layouts/footer.php'; ?>
