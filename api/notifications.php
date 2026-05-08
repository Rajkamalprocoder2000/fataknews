<?php
// api/notifications.php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$notifModel = new NotificationModel();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $notifModel->markAllRead(Auth::id());
    Helper::json(['success' => true]);
}
$notifs = $notifModel->getUnread(Auth::id());
$result = array_map(fn($n) => [
    'id'           => $n['id'],
    'type'         => $n['type'],
    'message'      => $n['message'],
    'link'         => $n['link'],
    'is_read'      => (bool)$n['is_read'],
    'time_ago'     => Helper::timeAgo($n['created_at']),
    'actor_avatar' => Helper::avatarUrl($n['actor_avatar'] ?? null),
], $notifs);
Helper::json(['success' => true, 'notifications' => $result]);
