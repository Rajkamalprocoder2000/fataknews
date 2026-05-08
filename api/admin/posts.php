<?php
// api/admin/posts.php
require_once __DIR__ . '/../../includes/bootstrap.php';
Csrf::check();
Auth::requireRole('super_admin','admin','manager','editor');
$input     = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action    = $input['action'] ?? '';
$postId    = (int)($input['post_id'] ?? 0);
$postModel = new PostModel();
switch ($action) {
    case 'approve':
        $postModel->approve($postId, Auth::id());
        $post = $postModel->findById($postId);
        if ($post) (new NotificationModel())->send($post['user_id'], 'post_approved', 'Your post "' . mb_substr($post['title'],0,60) . '" was approved!', Auth::id(), '/' . $post['slug']);
        Helper::json(['success' => true, 'message' => 'Post approved and published!']);
    case 'reject':
        $reason = $input['reason'] ?? 'Does not meet our editorial standards';
        $postModel->reject($postId, $reason);
        $post = $postModel->findById($postId);
        if ($post) (new NotificationModel())->send($post['user_id'], 'post_rejected', 'Your post was rejected: ' . $reason, Auth::id());
        Helper::json(['success' => true, 'message' => 'Post rejected.']);
    case 'delete':
        $postModel->delete($postId);
        Helper::json(['success' => true, 'message' => 'Post deleted.']);
    case 'toggle_breaking':
        $post = $postModel->findById($postId);
        if (!$post) Helper::json(['error' => 'Not found'], 404);
        $postModel->update($postId, ['is_breaking' => $post['is_breaking'] ? 0 : 1]);
        Helper::json(['success' => true]);
    case 'toggle_featured':
        $post = $postModel->findById($postId);
        if (!$post) Helper::json(['error' => 'Not found'], 404);
        $postModel->update($postId, ['is_featured' => $post['is_featured'] ? 0 : 1]);
        Helper::json(['success' => true]);
    case 'update_engagement':
        Auth::requireRole('super_admin', 'admin');
        $post = $postModel->findById($postId);
        if (!$post) Helper::json(['error' => 'Not found'], 404);

        $likes = max(0, (int)($input['likes_count'] ?? $post['likes_count'] ?? 0));
        $views = max(0, (int)($input['views_count'] ?? $post['views_count'] ?? 0));

        $postModel->update($postId, [
            'likes_count' => $likes,
            'views_count' => $views,
        ]);

        Helper::json([
            'success' => true,
            'message' => 'Likes and views updated.',
            'likes_count' => $likes,
            'views_count' => $views,
        ]);
    default:
        Helper::json(['error' => 'Unknown action'], 400);
}
