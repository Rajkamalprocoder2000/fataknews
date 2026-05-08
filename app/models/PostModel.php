<?php
// app/models/PostModel.php
class PostModel extends Model {
    protected string $table = 'posts';
    private static bool $sourceUrlColumnEnsured = false;

    public function __construct() {
        parent::__construct();
        $this->ensureWideSourceUrlColumn();
    }

    private string $baseSelect = "
        SELECT p.*,
               u.username, u.full_name, u.avatar, u.is_verified, u.badge_level, u.bio, u.website,
               u.location AS author_location,
               r.name AS role_name,
               c.name AS category_name, c.slug AS category_slug, c.color AS category_color
        FROM posts p
        JOIN users u ON p.user_id=u.id
        LEFT JOIN roles r ON u.role_id=r.id
        LEFT JOIN categories c ON p.category_id=c.id
    ";

    private function ensureWideSourceUrlColumn(): void {
        if (self::$sourceUrlColumnEnsured) {
            return;
        }

        $column = $this->db->fetchOne(
            "SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = 'source_url'",
            [DB_NAME, $this->table]
        );

        if ($column) {
            $dataType = strtolower((string)($column['DATA_TYPE'] ?? ''));
            $maxLength = (int)($column['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
            if (!in_array($dataType, ['text', 'mediumtext', 'longtext'], true) && $maxLength > 0 && $maxLength < 2048) {
                $this->db->query("ALTER TABLE `{$this->table}` MODIFY `source_url` TEXT DEFAULT NULL");
            }
        }

        self::$sourceUrlColumnEnsured = true;
    }

    public function getHomeVisibilityFilter(string $alias = 'p'): string {
        return "COALESCE({$alias}.location,'') IN ('', 'home', 'both')";
    }

    public function getCategoryVisibilityFilter(string $alias = 'p'): string {
        return "COALESCE({$alias}.location,'') IN ('', 'category', 'both')";
    }

    public function getNonExploreVisibilityFilter(string $alias = 'p'): string {
        return "COALESCE({$alias}.location,'') <> 'explore'";
    }

    public function getExploreVisibilityFilter(string $alias = 'p'): string {
        return "{$alias}.location='explore'";
    }

    public function getLatest(int $page = 1, string $type = ''): array {
        $where = "WHERE p.status='published' AND " . $this->getHomeVisibilityFilter('p');
        $params = [];

        if ($type !== '') {
            $where .= " AND p.type=?";
            $params[] = $type;
        } else {
            $where .= " AND p.type IN ('news','article','breaking')";
        }

        return $this->db->paginate(
            $this->baseSelect . $where . "
             ORDER BY p.published_at DESC",
            $params, $page
        );
    }

    public function getBreaking(): array {
        return $this->db->fetchAll(
            $this->baseSelect . "WHERE p.status='published' AND " . $this->getHomeVisibilityFilter('p') . " AND p.is_breaking=1
             ORDER BY p.published_at DESC LIMIT 5"
        );
    }

    public function getFeatured(): array {
        return $this->db->fetchAll(
            $this->baseSelect . "WHERE p.status='published' AND " . $this->getHomeVisibilityFilter('p') . " AND p.is_featured=1
             ORDER BY p.published_at DESC LIMIT 8"
        );
    }

    public function getTrending(int $limit = 10): array {
        $limit = max(1, $limit);
        return $this->db->fetchAll(
            $this->baseSelect . "WHERE p.status='published' AND " . $this->getNonExploreVisibilityFilter('p') . "
             ORDER BY p.views_count DESC, COALESCE(p.published_at, p.created_at) DESC LIMIT {$limit}"
        );
    }

    public function getByCategory(int $catId, int $page = 1): array {
        $categoryIds = (new CategoryModel())->getSelfAndDescendantIds($catId);
        if (empty($categoryIds)) {
            return [
                'data' => [],
                'page' => max(1, $page),
                'pages' => 0,
                'total' => 0,
            ];
        }

        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

        return $this->db->paginate(
            $this->baseSelect . "WHERE p.status='published' AND " . $this->getCategoryVisibilityFilter('p') . " AND p.category_id IN ($placeholders)
             ORDER BY p.published_at DESC",
            $categoryIds, $page
        );
    }

    public function getByUser(int $userId, int $page = 1): array {
        return $this->db->paginate(
            $this->baseSelect . "WHERE p.user_id=? AND p.status='published'
             ORDER BY p.published_at DESC",
            [$userId], $page
        );
    }

    public function getBySlug(string $slug): ?array {
        return $this->db->fetchOne($this->baseSelect . "WHERE p.slug=?", [$slug]);
    }

    public function getPending(int $page = 1): array {
        return $this->db->paginate(
            $this->baseSelect . "WHERE p.status='pending' ORDER BY p.created_at ASC",
            [], $page
        );
    }

    public function search(string $q, int $page = 1): array {
        return $this->db->paginate(
            $this->baseSelect . "WHERE p.status='published' AND " . $this->getNonExploreVisibilityFilter('p') . "
             AND MATCH(p.title,p.excerpt,p.content) AGAINST(? IN BOOLEAN MODE)
             ORDER BY p.published_at DESC",
            [$q . '*'], $page
        );
    }

    public function getFeed(int $userId, int $page = 1): array {
        return $this->db->paginate(
            $this->baseSelect . "WHERE p.status='published' AND " . $this->getNonExploreVisibilityFilter('p') . "
             AND (p.user_id IN (SELECT following_id FROM follows WHERE follower_id=?)
                  OR p.is_featured=1)
             ORDER BY p.published_at DESC",
            [$userId], $page
        );
    }

    public function create(array $data) {
        $data['slug']         = $this->buildSlug((string)($data['title'] ?? ''), (string)($data['slug'] ?? ''));
        $data['reading_time'] = Helper::readingTime($data['content']);
        if (empty($data['excerpt'])) {
            $data['excerpt'] = Helper::excerpt($data['content']);
        }
        $data = $this->prepareSeoPayload($data);
        if (($data['status'] ?? '') === 'published') {
            $data['published_at'] = date('Y-m-d H:i:s');
        }
        $id = parent::create($data);
        $this->db->query(
            "UPDATE users SET posts_count=posts_count+1 WHERE id=?",
            [$data['user_id']]
        );
        return $id;
    }

    public function update(int $id, array $data): int {
        $current = $this->findById($id);
        if (!$current) {
            return 0;
        }

        $title = array_key_exists('title', $data)
            ? (string)$data['title']
            : (string)($current['title'] ?? '');

        $data['slug'] = $this->buildSlug(
            $title,
            (string)($data['slug'] ?? ''),
            $id,
            (string)($current['slug'] ?? '')
        );

        if (array_key_exists('content', $data) && trim((string)$data['content']) !== '') {
            $data['reading_time'] = Helper::readingTime((string)$data['content']);

            if (array_key_exists('excerpt', $data) && trim((string)$data['excerpt']) === '') {
                $data['excerpt'] = Helper::excerpt((string)$data['content']);
            }
        }

        $data = $this->prepareSeoPayload($data, $current);

        if (($data['status'] ?? null) === 'published' && empty($current['published_at'])) {
            $data['published_at'] = date('Y-m-d H:i:s');
        }

        return parent::update($id, $data);
    }

    public function incrementViews(int $id): void {
        $this->db->query("UPDATE posts SET views_count=views_count+1 WHERE id=?", [$id]);
    }

    public function react(int $postId, int $userId, string $type = 'like'): array {
        $existing = $this->db->fetchOne(
            "SELECT * FROM reactions WHERE user_id=? AND target_type='post' AND target_id=?",
            [$userId, $postId]
        );
        if ($existing) {
            if ($existing['reaction_type'] === $type) {
                $this->db->delete('reactions', 'id=?', [$existing['id']]);
                $this->db->query("UPDATE posts SET likes_count=GREATEST(0,likes_count-1) WHERE id=?", [$postId]);
                return ['action' => 'removed', 'count' => $this->getLikesCount($postId)];
            }
            $this->db->update('reactions', ['reaction_type' => $type], 'id=?', [$existing['id']]);
            return ['action' => 'changed', 'count' => $this->getLikesCount($postId)];
        }
        $this->db->insert('reactions', [
            'user_id' => $userId, 'target_type' => 'post',
            'target_id' => $postId, 'reaction_type' => $type
        ]);
        $this->db->query("UPDATE posts SET likes_count=likes_count+1 WHERE id=?", [$postId]);
        return ['action' => 'added', 'count' => $this->getLikesCount($postId)];
    }

    public function bookmark(int $postId, int $userId): string {
        $existing = $this->db->fetchOne(
            "SELECT 1 FROM bookmarks WHERE user_id=? AND post_id=?", [$userId, $postId]
        );
        if ($existing) {
            $this->db->delete('bookmarks', 'user_id=? AND post_id=?', [$userId, $postId]);
            $this->db->query("UPDATE posts SET bookmarks_count=GREATEST(0,bookmarks_count-1) WHERE id=?", [$postId]);
            return 'removed';
        }
        $this->db->insert('bookmarks', ['user_id' => $userId, 'post_id' => $postId]);
        $this->db->query("UPDATE posts SET bookmarks_count=bookmarks_count+1 WHERE id=?", [$postId]);
        return 'added';
    }

    private function getLikesCount(int $postId): int {
        return (int)$this->db->fetchOne(
            "SELECT likes_count FROM posts WHERE id=?", [$postId]
        )['likes_count'];
    }

    public function approve(int $id, int $approverId): void {
        $this->update($id, [
            'status' => 'published',
            'approved_by' => $approverId,
            'published_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function reject(int $id, string $reason): void {
        $this->update($id, ['status' => 'rejected', 'rejected_reason' => $reason]);
    }

    public function buildSlug(string $title, string $requestedSlug = '', int $ignoreId = 0, string $currentSlug = ''): string {
        $requestedSlug = trim($requestedSlug);
        $currentSlug = trim($currentSlug);

        if ($requestedSlug !== '') {
            $baseSlug = Helper::slug($requestedSlug);
            if ($baseSlug === '') {
                throw new InvalidArgumentException('Custom slug must use letters, numbers, or hyphens.');
            }

            return $this->uniqueSlugForPost($baseSlug, $ignoreId);
        }

        if ($ignoreId > 0 && $currentSlug !== '') {
            return $currentSlug;
        }

        $baseSlug = Helper::slug($title);
        if ($baseSlug === '') {
            $baseSlug = 'post';
        }

        return $this->uniqueSlugForPost($baseSlug, $ignoreId);
    }

    private function uniqueSlugForPost(string $baseSlug, int $ignoreId = 0): string {
        $baseSlug = trim(mb_substr($baseSlug, 0, 320), '-');
        if ($baseSlug === '') {
            $baseSlug = 'post';
        }

        $slug = $baseSlug;
        $i = 1;
        while ($this->db->fetchOne("SELECT id FROM posts WHERE slug=? AND id<>?", [$slug, $ignoreId])) {
            $suffix = '-' . $i++;
            $slug = rtrim(mb_substr($baseSlug, 0, 320 - strlen($suffix)), '-') . $suffix;
        }

        return $slug;
    }

    private function prepareSeoPayload(array $data, array $current = []): array {
        $merged = array_merge($current, $data);
        $title = trim((string)($merged['title'] ?? ''));
        $content = trim((string)($merged['content'] ?? ''));
        $excerpt = Helper::normalizedExcerpt((string)($merged['excerpt'] ?? ''), 220);
        if ($excerpt === '' && $content !== '') {
            $excerpt = Helper::normalizedExcerpt($content, 220);
        }

        $categoryName = '';
        $categoryId = isset($merged['category_id']) ? (int)$merged['category_id'] : 0;
        if ($categoryId > 0) {
            $categoryRow = $this->db->fetchOne("SELECT name FROM categories WHERE id=?", [$categoryId]);
            $categoryName = trim((string)($categoryRow['name'] ?? ''));
        }

        $hasThumbnail = trim((string)($merged['thumbnail'] ?? '')) !== '';

        if ($excerpt !== '') {
            $data['excerpt'] = $excerpt;
        }

        if (trim((string)($merged['seo_title'] ?? '')) === '' && $title !== '') {
            $data['seo_title'] = Helper::defaultSeoTitle($title, $categoryName);
        }

        if (trim((string)($merged['seo_description'] ?? '')) === '') {
            $data['seo_description'] = Helper::defaultSeoDescription($excerpt, $content, $categoryName);
        }

        if ($hasThumbnail && trim((string)($merged['image_alt'] ?? '')) === '') {
            $data['image_alt'] = Helper::defaultImageAlt($title, $categoryName);
        }

        if (trim((string)($merged['seo_keywords'] ?? '')) === '') {
            $data['seo_keywords'] = Helper::seoKeywordsFromPost($title, $categoryName);
        }

        return $data;
    }
}
