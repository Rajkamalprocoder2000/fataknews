<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

Csrf::check();
Auth::requireRole('super_admin', 'admin');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = trim((string)($input['action'] ?? ''));
$db = Database::getInstance();
$categoryModel = new CategoryModel();

$findCategory = static function (int $id) use ($db): ?array {
    return $db->fetchOne("SELECT * FROM categories WHERE id=?", [$id]);
};

$buildPayload = static function (array $input, ?array $existing = null) use ($db, $categoryModel): array {
    $name = trim((string)($input['name'] ?? ''));
    if ($name === '') {
        Helper::json(['error' => 'Category name is required'], 422);
    }

    $slug = trim((string)($input['slug'] ?? ''));
    $slug = $slug !== '' ? Helper::slug($slug) : Helper::slug($name);
    if ($slug === '') {
        Helper::json(['error' => 'A valid slug is required'], 422);
    }

    $categoryId = (int)($existing['id'] ?? 0);
    $slugConflict = $db->fetchOne(
        "SELECT id FROM categories WHERE slug=? AND id<>?",
        [$slug, $categoryId]
    );
    if ($slugConflict) {
        Helper::json(['error' => 'That slug is already in use'], 409);
    }

    $parentId = isset($input['parent_id']) && $input['parent_id'] !== '' ? (int)$input['parent_id'] : null;
    if ($parentId !== null && $parentId > 0) {
        if ($parentId === $categoryId && $categoryId > 0) {
            Helper::json(['error' => 'A category cannot be its own parent'], 422);
        }

        $parent = $categoryModel->findById($parentId);
        if (!$parent) {
            Helper::json(['error' => 'Selected parent category was not found'], 404);
        }
        $level = (int)$parent['level'] + 1;
    } else {
        $parentId = null;
        $level = 1;
    }

    $color = strtoupper(trim((string)($input['color'] ?? '#FF2D2D')));
    if (!preg_match('/^#[0-9A-F]{6}$/', $color)) {
        $color = '#FF2D2D';
    }

    $icon = trim((string)($input['icon'] ?? 'fa-folder'));
    if ($icon === '') {
        $icon = 'fa-folder';
    }

    return [
        'parent_id' => $parentId,
        'name' => $name,
        'slug' => $slug,
        'description' => trim((string)($input['description'] ?? '')),
        'icon' => $icon,
        'color' => $color,
        'cover_image' => trim((string)($input['cover_image'] ?? ($existing['cover_image'] ?? ''))),
        'level' => $level,
        'sort_order' => max(0, (int)($input['sort_order'] ?? ($existing['sort_order'] ?? 0))),
        'is_active' => !empty($input['is_active']) ? 1 : 0,
        'is_featured' => !empty($input['is_featured']) ? 1 : 0,
    ];
};

switch ($action) {
    case 'create':
        $payload = $buildPayload($input);
        $newId = $db->insert('categories', $payload);
        Helper::json(['success' => true, 'message' => 'Category created.', 'id' => $newId]);

    case 'update':
        $categoryId = (int)($input['category_id'] ?? 0);
        $existing = $findCategory($categoryId);
        if (!$existing) {
            Helper::json(['error' => 'Category not found'], 404);
        }

        $payload = $buildPayload($input, $existing);
        $db->update('categories', $payload, 'id=?', [$categoryId]);
        Helper::json(['success' => true, 'message' => 'Category updated.']);

    case 'toggle_active':
        $categoryId = (int)($input['category_id'] ?? 0);
        $existing = $findCategory($categoryId);
        if (!$existing) {
            Helper::json(['error' => 'Category not found'], 404);
        }

        $next = $existing['is_active'] ? 0 : 1;
        $db->update('categories', ['is_active' => $next], 'id=?', [$categoryId]);
        Helper::json(['success' => true, 'message' => $next ? 'Category activated.' : 'Category disabled.']);

    case 'toggle_featured':
        $categoryId = (int)($input['category_id'] ?? 0);
        $existing = $findCategory($categoryId);
        if (!$existing) {
            Helper::json(['error' => 'Category not found'], 404);
        }

        $next = $existing['is_featured'] ? 0 : 1;
        $db->update('categories', ['is_featured' => $next], 'id=?', [$categoryId]);
        Helper::json(['success' => true, 'message' => $next ? 'Category marked featured.' : 'Category removed from featured.']);

    case 'delete':
        $categoryId = (int)($input['category_id'] ?? 0);
        $existing = $findCategory($categoryId);
        if (!$existing) {
            Helper::json(['error' => 'Category not found'], 404);
        }

        $childCount = (int)($db->fetchOne("SELECT COUNT(*) AS c FROM categories WHERE parent_id=?", [$categoryId])['c'] ?? 0);
        if ($childCount > 0) {
            Helper::json(['error' => 'Delete child categories first'], 409);
        }

        $postCount = (int)($db->fetchOne("SELECT COUNT(*) AS c FROM posts WHERE category_id=?", [$categoryId])['c'] ?? 0);
        if ($postCount > 0) {
            Helper::json(['error' => 'This category is attached to existing posts'], 409);
        }

        $db->delete('categories', 'id=?', [$categoryId]);
        Helper::json(['success' => true, 'message' => 'Category deleted.']);

    default:
        Helper::json(['error' => 'Unknown action'], 400);
}
