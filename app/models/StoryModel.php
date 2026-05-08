<?php

class StoryModel extends Model {
    protected string $table = 'stories';

    private string $baseSelect = "
        SELECT s.*,
               u.username, u.full_name, u.avatar, u.is_verified, u.badge_level
        FROM stories s
        JOIN users u ON s.user_id=u.id
        WHERE s.is_active=1
          AND s.expires_at > NOW()
          AND u.is_active=1
    ";

    public function getActiveGroups(?int $viewerId = null, int $limit = 12, array $roleSlugs = []): array {
        $params = [];
        $viewerClause = '';
        $roleJoin = '';
        $roleWhere = '';

        if ($viewerId) {
            $viewerClause = ",
                SUM(CASE WHEN sv.viewer_id IS NULL THEN 1 ELSE 0 END) AS unseen_count,
                MAX(CASE WHEN sv.viewer_id IS NULL THEN 1 ELSE 0 END) AS has_unseen
            ";
            $params[] = $viewerId;
        } else {
            $viewerClause = ",
                COUNT(*) AS unseen_count,
                1 AS has_unseen
            ";
        }

        $roleSlugs = array_values(array_filter(array_map(static fn($role) => trim((string)$role), $roleSlugs)));
        if ($roleSlugs) {
            $roleJoin = "JOIN roles r ON u.role_id=r.id";
            $roleWhere = " AND r.slug IN (" . implode(',', array_fill(0, count($roleSlugs), '?')) . ")";
            array_push($params, ...$roleSlugs);
        }

        $sql = "
            SELECT s.user_id,
                   MAX(s.id) AS latest_story_id,
                   COUNT(*) AS story_count,
                   MAX(s.created_at) AS latest_created_at,
                   u.username,
                   u.full_name,
                   u.avatar,
                   u.is_verified,
                   " . ($roleSlugs ? "r.slug AS role_slug" : "NULL AS role_slug") . "
                   {$viewerClause}
            FROM stories s
            JOIN users u ON s.user_id=u.id
            {$roleJoin}
            " . ($viewerId ? "LEFT JOIN story_views sv ON sv.story_id=s.id AND sv.viewer_id=?" : '') . "
            WHERE s.is_active=1
              AND s.expires_at > NOW()
              AND u.is_active=1
              {$roleWhere}
            GROUP BY s.user_id, u.username, u.full_name, u.avatar, u.is_verified" . ($roleSlugs ? ", r.slug" : '') . "
            ORDER BY has_unseen DESC, latest_created_at DESC
            LIMIT " . (int)$limit;

        return $this->db->fetchAll($sql, $params);
    }

    public function getUserStories(int $userId, ?int $viewerId = null): array {
        $params = [$userId];
        $viewerSelect = '0 AS is_viewed';
        $viewerJoin = '';

        if ($viewerId) {
            $viewerSelect = 'CASE WHEN sv.viewer_id IS NULL THEN 0 ELSE 1 END AS is_viewed';
            $viewerJoin = 'LEFT JOIN story_views sv ON sv.story_id=s.id AND sv.viewer_id=?';
            array_unshift($params, $viewerId);
        }

        $sql = "
            SELECT s.*,
                   u.username, u.full_name, u.avatar, u.is_verified,
                   {$viewerSelect}
            FROM stories s
            JOIN users u ON s.user_id=u.id
            {$viewerJoin}
            WHERE s.user_id=?
              AND s.is_active=1
              AND s.expires_at > NOW()
              AND u.is_active=1
            ORDER BY s.created_at ASC
        ";

        return $this->db->fetchAll($sql, $params);
    }

    public function createStory(int $userId, array $data) {
        $payload = [
            'user_id' => $userId,
            'media_type' => in_array(($data['media_type'] ?? 'text'), ['image', 'text'], true) ? $data['media_type'] : 'text',
            'media_path' => trim((string)($data['media_path'] ?? '')) ?: null,
            'caption' => trim((string)($data['caption'] ?? '')) ?: null,
            'background_color' => $this->normalizeColor($data['background_color'] ?? '#2D2244', '#2D2244'),
            'text_color' => $this->normalizeColor($data['text_color'] ?? '#FFFFFF', '#FFFFFF'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
        ];

        return $this->create($payload);
    }

    public function markViewed(int $storyId, int $viewerId): void {
        $story = $this->db->fetchOne(
            "SELECT id, user_id FROM stories WHERE id=? AND is_active=1 AND expires_at > NOW()",
            [$storyId]
        );

        if (!$story || (int)$story['user_id'] === $viewerId) {
            return;
        }

        $exists = $this->db->fetchOne(
            "SELECT 1 FROM story_views WHERE story_id=? AND viewer_id=?",
            [$storyId, $viewerId]
        );

        if ($exists) {
            return;
        }

        $this->db->insert('story_views', [
            'story_id' => $storyId,
            'viewer_id' => $viewerId,
        ]);

        $this->db->query(
            "UPDATE stories SET views_count=views_count+1 WHERE id=?",
            [$storyId]
        );
    }

    public function getActiveCountForUser(int $userId): int {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM stories WHERE user_id=? AND is_active=1 AND expires_at > NOW()",
            [$userId]
        );

        return (int)($row['total'] ?? 0);
    }

    public function deleteOwnedStory(int $storyId, int $userId): bool {
        $story = $this->db->fetchOne(
            "SELECT id, user_id, media_path FROM stories WHERE id=? AND user_id=?",
            [$storyId, $userId]
        );

        if (!$story) {
            return false;
        }

        $deleted = $this->db->delete('stories', 'id=? AND user_id=?', [$storyId, $userId]) > 0;
        if (!$deleted) {
            return false;
        }

        $mediaPath = trim((string)($story['media_path'] ?? ''));
        if ($mediaPath !== '') {
            $file = PUBLIC_PATH . '/uploads/stories/' . basename($mediaPath);
            if (is_file($file)) {
                @unlink($file);
            }
        }

        return true;
    }

    private function normalizeColor($value, string $fallback): string {
        $value = strtoupper(trim((string)$value));
        if (preg_match('/^#[0-9A-F]{6}$/', $value)) {
            return $value;
        }

        return $fallback;
    }
}
