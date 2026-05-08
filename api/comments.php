<?php
// api/comments.php
require_once __DIR__ . '/../includes/bootstrap.php';
Csrf::check();
Auth::requireLogin();
$input        = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$commentModel = new CommentModel();
$content      = trim($input['content'] ?? '');
$postId       = (int)($input['post_id'] ?? 0);
$parentId     = (int)($input['parent_id'] ?? 0) ?: null;
if (!$content || !$postId) Helper::json(['error' => 'Content and post ID required'], 400);
$id = $commentModel->addComment($postId, Auth::id(), htmlspecialchars($content, ENT_QUOTES, 'UTF-8'), $parentId);
$user = Auth::user();
$html = '<div class="comment-item">
  <img src="' . Helper::avatarUrl($user['avatar']) . '" class="comment-avatar" alt="">
  <div class="comment-body">
    <div class="comment-header">
      <strong>' . Helper::sanitize($user['full_name']) . '</strong>
      <time>Just now</time>
    </div>
    <p class="comment-text">' . htmlspecialchars($content) . '</p>
  </div>
</div>';
Helper::json(['success' => true, 'html' => $html, 'id' => $id]);
