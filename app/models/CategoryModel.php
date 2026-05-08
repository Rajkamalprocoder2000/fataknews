<?php
// app/models/CategoryModel.php
class CategoryModel extends Model {
    protected string $table = 'categories';

    public function ensureTopLevelCategory(string $slug, ?string $name = null): ?array {
        $slug = Helper::slug($slug);
        if ($slug === '') {
            return null;
        }

        $existing = $this->getBySlug($slug);
        if ($existing) {
            return $existing;
        }

        $meta = $this->autoCategoryMeta($slug, $name);
        $maxSortOrder = (int)($this->db->fetchOne(
            "SELECT COALESCE(MAX(sort_order), 0) AS max_sort
             FROM categories
             WHERE parent_id IS NULL"
        )['max_sort'] ?? 0);

        $newId = (int)$this->create([
            'parent_id' => null,
            'name' => $meta['name'],
            'slug' => $slug,
            'description' => $meta['description'],
            'icon' => $meta['icon'],
            'color' => $meta['color'],
            'cover_image' => '',
            'level' => 1,
            'sort_order' => $maxSortOrder + 10,
            'is_active' => 1,
            'is_featured' => 0,
        ]);

        return $newId > 0 ? $this->findById($newId) : null;
    }

    public function getTree(): array {
        $all  = $this->db->fetchAll("SELECT * FROM categories WHERE is_active=1 ORDER BY level,sort_order,name");
        $tree = [];
        $map  = [];
        foreach ($all as $cat) $map[$cat['id']] = $cat + ['children' => []];
        foreach ($map as &$cat) {
            if ($cat['parent_id']) $map[$cat['parent_id']]['children'][] = &$cat;
            else $tree[] = &$cat;
        }
        return $tree;
    }

    public function getTopLevel(): array {
        return $this->db->fetchAll("SELECT * FROM categories WHERE parent_id IS NULL AND is_active=1 ORDER BY sort_order");
    }

    public function getChildren(int $parentId): array {
        return $this->db->fetchAll("SELECT * FROM categories WHERE parent_id=? AND is_active=1 ORDER BY sort_order", [$parentId]);
    }

    public function getBySlug(string $slug): ?array {
        return $this->db->fetchOne("SELECT * FROM categories WHERE slug=?", [$slug]);
    }

    public function getSelfAndDescendantIds(int $categoryId): array {
        if ($categoryId <= 0) {
            return [];
        }

        $allCategories = $this->db->fetchAll(
            "SELECT id, parent_id FROM categories WHERE is_active=1 ORDER BY level,sort_order,name"
        );

        if (empty($allCategories)) {
            return [];
        }

        $childrenByParent = [];
        $existingIds = [];

        foreach ($allCategories as $category) {
            $id = (int)($category['id'] ?? 0);
            $parentId = isset($category['parent_id']) ? (int)$category['parent_id'] : 0;
            $existingIds[$id] = true;
            $childrenByParent[$parentId][] = $id;
        }

        if (!isset($existingIds[$categoryId])) {
            return [];
        }

        $ids = [];
        $stack = [$categoryId];

        while (!empty($stack)) {
            $currentId = (int)array_pop($stack);
            if (isset($ids[$currentId])) {
                continue;
            }

            $ids[$currentId] = true;

            foreach (($childrenByParent[$currentId] ?? []) as $childId) {
                if (!isset($ids[$childId])) {
                    $stack[] = (int)$childId;
                }
            }
        }

        return array_map('intval', array_keys($ids));
    }

    private function autoCategoryMeta(string $slug, ?string $name = null): array {
        $name = trim((string)$name);
        $nameMap = [
            'ai' => 'AI',
            'auto' => 'Auto',
            'china-watch' => 'China Watch',
            'climate' => 'Climate',
            'defence' => 'Defence',
            'india' => 'India',
            'japan-news' => 'Japan News',
            'markets' => 'Markets',
            'science' => 'Science',
            'startups' => 'Startups',
            'technology' => 'Technology',
            'us-news' => 'US News',
            'world' => 'World',
        ];
        $colorMap = [
            'ai' => '#6C5CE7',
            'auto' => '#FF7043',
            'business' => '#00A86B',
            'china-watch' => '#D84315',
            'climate' => '#2E7D32',
            'culture' => '#8E24AA',
            'defence' => '#455A64',
            'education' => '#1E88E5',
            'entertainment' => '#EC407A',
            'health' => '#E53935',
            'india' => '#FF6B1A',
            'japan-news' => '#C2185B',
            'markets' => '#00897B',
            'opinion' => '#6D4C41',
            'politics' => '#C62828',
            'science' => '#3949AB',
            'sports' => '#F9A825',
            'startups' => '#00ACC1',
            'technology' => '#2979FF',
            'travel' => '#26A69A',
            'us-news' => '#1565C0',
            'world' => '#5E35B1',
        ];
        $iconMap = [
            'ai' => 'fa-robot',
            'auto' => 'fa-car-side',
            'business' => 'fa-briefcase',
            'china-watch' => 'fa-earth-asia',
            'climate' => 'fa-leaf',
            'culture' => 'fa-masks-theater',
            'defence' => 'fa-shield-halved',
            'education' => 'fa-graduation-cap',
            'entertainment' => 'fa-film',
            'health' => 'fa-heart-pulse',
            'india' => 'fa-bolt',
            'japan-news' => 'fa-torii-gate',
            'markets' => 'fa-chart-line',
            'opinion' => 'fa-comments',
            'politics' => 'fa-landmark',
            'science' => 'fa-flask',
            'sports' => 'fa-futbol',
            'startups' => 'fa-rocket',
            'technology' => 'fa-microchip',
            'travel' => 'fa-plane-departure',
            'us-news' => 'fa-earth-americas',
            'world' => 'fa-globe',
        ];

        $resolvedName = $name !== '' ? $name : ($nameMap[$slug] ?? $this->humanizeSlug($slug));

        return [
            'name' => $resolvedName,
            'description' => 'Auto-created by the AI content pipeline for incoming stories.',
            'icon' => $iconMap[$slug] ?? 'fa-folder',
            'color' => $colorMap[$slug] ?? '#FF2D2D',
        ];
    }

    private function humanizeSlug(string $slug): string {
        $parts = preg_split('/-+/', $slug, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (empty($parts)) {
            return 'News';
        }

        $upperMap = [
            'ai' => 'AI',
            'ev' => 'EV',
            'us' => 'US',
            'uk' => 'UK',
        ];

        $parts = array_map(static function (string $part) use ($upperMap): string {
            return $upperMap[$part] ?? ucfirst($part);
        }, $parts);

        return implode(' ', $parts);
    }
}
