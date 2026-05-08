<?php
// app/models/NotificationModel.php
class NotificationModel extends Model {
    protected string $table = 'notifications';

    public function send(int $userId, string $type, string $message, ?int $actorId = null, ?string $link = null): void {
        $this->create(['user_id'=>$userId,'actor_id'=>$actorId,'type'=>$type,'message'=>$message,'link'=>$link]);
    }

    public function getUnread(int $userId): array {
        return $this->db->fetchAll(
            "SELECT n.*, u.username AS actor_username, u.avatar AS actor_avatar
             FROM notifications n LEFT JOIN users u ON n.actor_id=u.id
             WHERE n.user_id=? AND n.is_read=0 ORDER BY n.created_at DESC LIMIT 30",
            [$userId]
        );
    }

    public function markAllRead(int $userId): void {
        $this->db->query("UPDATE notifications SET is_read=1 WHERE user_id=?", [$userId]);
    }

    public function countUnread(int $userId): int {
        return (int)$this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM notifications WHERE user_id=? AND is_read=0",
            [$userId]
        )['cnt'];
    }
}
