<?php
// app/models/CommentModel.php
class CommentModel extends Model {
    protected string $table = 'comments';

    public function getByPost(int $postId): array {
        return $this->db->fetchAll(
            "SELECT c.*, u.username, u.full_name, u.avatar, u.is_verified
             FROM comments c JOIN users u ON c.user_id=u.id
             WHERE c.post_id=? AND c.parent_id IS NULL AND c.is_approved=1
             ORDER BY c.is_pinned DESC, c.created_at DESC",
            [$postId]
        );
    }

    public function getReplies(int $parentId): array {
        return $this->db->fetchAll(
            "SELECT c.*, u.username, u.full_name, u.avatar, u.is_verified
             FROM comments c JOIN users u ON c.user_id=u.id
             WHERE c.parent_id=? AND c.is_approved=1 ORDER BY c.created_at ASC",
            [$parentId]
        );
    }

    public function addComment(int $postId, int $userId, string $content, ?int $parentId = null) {
        $id = $this->create(['post_id'=>$postId,'user_id'=>$userId,'content'=>$content,'parent_id'=>$parentId]);
        $this->db->query("UPDATE posts SET comments_count=comments_count+1 WHERE id=?", [$postId]);
        return $id;
    }
}
