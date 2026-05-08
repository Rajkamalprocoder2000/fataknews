<?php
// api/follow.php
require_once __DIR__ . '/../includes/bootstrap.php';
Csrf::check();
Auth::requireLogin();
$input       = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$followingId = (int)($input['following_id'] ?? 0);
if (!$followingId || $followingId === Auth::id()) Helper::json(['error' => 'Invalid'], 400);
$userModel = new UserModel();
if ($userModel->isFollowing(Auth::id(), $followingId)) {
    $userModel->unfollow(Auth::id(), $followingId);
    Helper::json(['success' => true, 'action' => 'unfollowed']);
} else {
    $userModel->follow(Auth::id(), $followingId);
    (new NotificationModel())->send($followingId, 'follow', Auth::user()['full_name'] . ' started following you', Auth::id(), '/@' . Auth::user()['username']);
    Helper::json(['success' => true, 'action' => 'followed']);
}
