<?php

class ContentCandidateModel extends Model {
    protected string $table = 'content_candidates';
    private static bool $schemaEnsured = false;
    private static bool $urlColumnsEnsured = false;

    public function __construct() {
        parent::__construct();
        $this->ensureSchema();
    }

    public function ensureSchema(): void {
        if (self::$schemaEnsured) {
            return;
        }

        $existing = $this->db->fetchOne(
            "SELECT TABLE_NAME
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
            [DB_NAME, $this->table]
        );
        if ($existing) {
            $this->ensureWideUrlColumns();
            self::$schemaEnsured = true;
            return;
        }

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `content_candidates` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `source_name` VARCHAR(150) NOT NULL,
                `source_url` TEXT DEFAULT NULL,
                `source_type` VARCHAR(30) NOT NULL DEFAULT 'rss',
                `external_url` TEXT DEFAULT NULL,
                `title` VARCHAR(500) NOT NULL,
                `excerpt` TEXT DEFAULT NULL,
                `content_snippet` MEDIUMTEXT DEFAULT NULL,
                `author_name` VARCHAR(150) DEFAULT NULL,
                `published_at` DATETIME DEFAULT NULL,
                `topic_name` VARCHAR(255) DEFAULT NULL,
                `topic_key` VARCHAR(180) DEFAULT NULL,
                `category_slug_hint` VARCHAR(120) DEFAULT NULL,
                `keyword_tags` TEXT DEFAULT NULL,
                `dedupe_key` CHAR(40) NOT NULL,
                `trend_score` DECIMAL(6,2) NOT NULL DEFAULT 0,
                `freshness_score` DECIMAL(6,2) NOT NULL DEFAULT 0,
                `keyword_score` DECIMAL(6,2) NOT NULL DEFAULT 0,
                `source_score` DECIMAL(6,2) NOT NULL DEFAULT 0,
                `cluster_score` DECIMAL(6,2) NOT NULL DEFAULT 0,
                `status` ENUM('new','reviewed','drafted','ignored') NOT NULL DEFAULT 'new',
                `draft_post_id` BIGINT UNSIGNED DEFAULT NULL,
                `drafted_at` DATETIME DEFAULT NULL,
                `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `meta_json` LONGTEXT DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_content_candidates_dedupe` (`dedupe_key`),
                KEY `idx_content_candidates_status` (`status`),
                KEY `idx_content_candidates_topic_key` (`topic_key`),
                KEY `idx_content_candidates_category` (`category_slug_hint`),
                KEY `idx_content_candidates_trend` (`trend_score`),
                KEY `idx_content_candidates_published` (`published_at`),
                KEY `idx_content_candidates_draft_post` (`draft_post_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$schemaEnsured = true;
    }

    private function ensureWideUrlColumns(): void {
        if (self::$urlColumnsEnsured) {
            return;
        }

        $columns = $this->db->fetchAll(
            "SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME IN ('source_url', 'external_url')",
            [DB_NAME, $this->table]
        );

        if (!$columns) {
            self::$urlColumnsEnsured = true;
            return;
        }

        $alterParts = [];
        foreach ($columns as $column) {
            $name = (string)($column['COLUMN_NAME'] ?? '');
            $dataType = strtolower((string)($column['DATA_TYPE'] ?? ''));
            $maxLength = (int)($column['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
            if (!in_array($dataType, ['text', 'mediumtext', 'longtext'], true) && $maxLength > 0 && $maxLength < 2048) {
                $alterParts[] = "MODIFY `{$name}` TEXT DEFAULT NULL";
            }
        }

        if ($alterParts) {
            $this->db->query("ALTER TABLE `{$this->table}` " . implode(', ', $alterParts));
        }

        self::$urlColumnsEnsured = true;
    }

    public function getStats(): array {
        $row = $this->db->fetchOne(
            "SELECT
                COUNT(*) AS all_count,
                SUM(CASE WHEN status='new' THEN 1 ELSE 0 END) AS new_count,
                SUM(CASE WHEN status='reviewed' THEN 1 ELSE 0 END) AS reviewed_count,
                SUM(CASE WHEN status='drafted' THEN 1 ELSE 0 END) AS drafted_count,
                SUM(CASE WHEN status='ignored' THEN 1 ELSE 0 END) AS ignored_count,
                SUM(CASE WHEN DATE(created_at)=CURDATE() THEN 1 ELSE 0 END) AS today_count,
                AVG(trend_score) AS avg_score
             FROM `{$this->table}`"
        ) ?? [];

        return [
            'all' => (int)($row['all_count'] ?? 0),
            'new' => (int)($row['new_count'] ?? 0),
            'reviewed' => (int)($row['reviewed_count'] ?? 0),
            'drafted' => (int)($row['drafted_count'] ?? 0),
            'ignored' => (int)($row['ignored_count'] ?? 0),
            'today' => (int)($row['today_count'] ?? 0),
            'avg_score' => round((float)($row['avg_score'] ?? 0), 2),
        ];
    }

    public function getAdminList(array $filters, int $page = 1, int $perPage = 20): array {
        $where = [];
        $params = [];

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, ['new', 'reviewed', 'drafted', 'ignored'], true)) {
            $where[] = 'status=?';
            $params[] = $status;
        }

        $category = trim((string)($filters['category'] ?? ''));
        if ($category !== '') {
            $where[] = 'category_slug_hint=?';
            $params[] = $category;
        }

        $query = trim((string)($filters['q'] ?? ''));
        if ($query !== '') {
            $like = '%' . $query . '%';
            $where[] = '(title LIKE ? OR source_name LIKE ? OR topic_name LIKE ? OR excerpt LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->db->paginate(
            "SELECT *
             FROM `{$this->table}`
             {$whereSql}
             ORDER BY trend_score DESC, COALESCE(published_at, created_at) DESC, id DESC",
            $params,
            $page,
            $perPage
        );
    }

    public function upsertCandidate(array $payload): array {
        $existing = $this->findBy('dedupe_key', (string)$payload['dedupe_key']);
        $now = date('Y-m-d H:i:s');

        if ($existing) {
            $status = (string)($existing['status'] ?? 'new');
            $draftPostId = (int)($existing['draft_post_id'] ?? 0);
            $draftedAt = trim((string)($existing['drafted_at'] ?? ''));

            $update = $payload;
            $update['last_seen_at'] = $now;
            $update['status'] = $status;
            $update['draft_post_id'] = $draftPostId > 0 ? $draftPostId : null;
            $update['drafted_at'] = $draftedAt !== '' ? $draftedAt : null;

            $this->db->update($this->table, $update, 'id=?', [(int)$existing['id']]);

            return [
                'action' => 'updated',
                'candidate' => $this->findById((int)$existing['id']),
            ];
        }

        $payload['last_seen_at'] = $now;
        $payload['status'] = $payload['status'] ?? 'new';
        $payload['draft_post_id'] = $payload['draft_post_id'] ?? null;
        $payload['drafted_at'] = $payload['drafted_at'] ?? null;
        $id = (int)$this->create($payload);

        return [
            'action' => 'created',
            'candidate' => $this->findById($id),
        ];
    }

    public function setStatus(int $candidateId, string $status): int {
        if (!in_array($status, ['new', 'reviewed', 'drafted', 'ignored'], true)) {
            throw new InvalidArgumentException('Invalid candidate status.');
        }

        return $this->update($candidateId, ['status' => $status]);
    }

    public function attachDraft(int $candidateId, int $postId): int {
        return $this->update($candidateId, [
            'status' => 'drafted',
            'draft_post_id' => $postId,
            'drafted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function findRecentByTopicKey(string $topicKey, int $hours = 72): array {
        $topicKey = trim($topicKey);
        $hours = max(1, min(168, $hours));

        if ($topicKey === '') {
            return [];
        }

        return $this->db->fetchAll(
            "SELECT *
             FROM `{$this->table}`
             WHERE topic_key=?
               AND COALESCE(published_at, created_at) >= DATE_SUB(NOW(), INTERVAL {$hours} HOUR)
             ORDER BY trend_score DESC, COALESCE(published_at, created_at) DESC",
            [$topicKey]
        );
    }

    public function getAutoQueue(float $minScore, int $limit = 5, array $statuses = ['new', 'reviewed'], int $maxAgeHours = 36): array {
        $limit = max(0, $limit);
        if ($limit === 0) {
            return [];
        }
        $maxAgeHours = max(6, min(72, $maxAgeHours));

        $allowedStatuses = ['new', 'reviewed', 'drafted', 'ignored'];
        $statuses = array_values(array_filter(array_map('strval', $statuses), static function (string $status) use ($allowedStatuses): bool {
            return in_array($status, $allowedStatuses, true);
        }));

        if (empty($statuses)) {
            $statuses = ['new', 'reviewed'];
        }

        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $params = array_merge($statuses, [$minScore], $statuses);

        return $this->db->fetchAll(
            "SELECT candidate.*
             FROM `{$this->table}` candidate
             WHERE candidate.status IN ({$placeholders})
               AND (candidate.draft_post_id IS NULL OR candidate.draft_post_id = 0)
               AND candidate.trend_score >= ?
               AND COALESCE(candidate.published_at, candidate.created_at) >= DATE_SUB(NOW(), INTERVAL {$maxAgeHours} HOUR)
               AND NOT EXISTS (
                    SELECT 1
                    FROM `{$this->table}` newer
                    WHERE COALESCE(newer.topic_key, '') = COALESCE(candidate.topic_key, '')
                      AND newer.id <> candidate.id
                      AND newer.status IN ({$placeholders})
                      AND (newer.draft_post_id IS NULL OR newer.draft_post_id = 0)
                      AND COALESCE(newer.published_at, newer.created_at) >= DATE_SUB(NOW(), INTERVAL {$maxAgeHours} HOUR)
                      AND (
                            newer.trend_score > candidate.trend_score
                         OR (newer.trend_score = candidate.trend_score AND COALESCE(newer.published_at, newer.created_at) > COALESCE(candidate.published_at, candidate.created_at))
                         OR (newer.trend_score = candidate.trend_score AND COALESCE(newer.published_at, newer.created_at) = COALESCE(candidate.published_at, candidate.created_at) AND newer.id > candidate.id)
                      )
               )
             ORDER BY candidate.trend_score DESC, COALESCE(candidate.published_at, candidate.created_at) DESC, candidate.id DESC
             LIMIT {$limit}",
            $params
        );
    }
}
