<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

Csrf::check();
Auth::requireRole('super_admin', 'admin', 'manager', 'editor', 'reporter');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Helper::json(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$categoryOptions = [];
foreach ((new CategoryModel())->getTopLevel() as $category) {
    $categoryOptions[] = [
        'id' => (int)$category['id'],
        'name' => (string)$category['name'],
        'slug' => (string)$category['slug'],
    ];
}

try {
    $result = XaiWriter::generateArticle($input + ['category_options' => $categoryOptions]);
    Helper::json(['success' => true, 'draft' => $result]);
} catch (Throwable $e) {
    $status = 500;
    if ($e instanceof InvalidArgumentException) {
        $status = 422;
    } elseif (str_contains($e->getMessage(), 'not configured') || str_contains($e->getMessage(), 'required')) {
        $status = 503;
    } elseif (str_contains($e->getMessage(), 'AI provider error')) {
        $status = 502;
    }
    Helper::json(['error' => $e->getMessage()], $status);
}
