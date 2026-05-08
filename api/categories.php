<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Helper::json(['error' => 'Method not allowed'], 405);
}

$parentId = max(0, (int)($_GET['parent'] ?? 0));
if ($parentId <= 0) {
    Helper::json(['success' => true, 'categories' => []]);
}

$categories = array_map(static function (array $category): array {
    return [
        'id' => (int)$category['id'],
        'name' => (string)$category['name'],
        'slug' => (string)$category['slug'],
    ];
}, (new CategoryModel())->getChildren($parentId));

Helper::json(['success' => true, 'categories' => $categories]);
