<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    Helper::json(['success' => true, 'results' => []]);
}

$db = Database::getInstance();
$like = '%' . $q . '%';
$postRows = $db->fetchAll(
    "SELECT p.title, p.slug, p.type, p.thumbnail, p.image_alt, p.published_at, c.slug AS category_slug
     FROM posts p
     LEFT JOIN categories c ON p.category_id=c.id
     WHERE p.status='published' AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ?)
     ORDER BY p.published_at DESC
     LIMIT 6",
    [$like, $like, $like]
);
$userRows = (new UserModel())->search($q, 4);

$results = [];
foreach ($postRows as $row) {
    $results[] = [
        'title' => $row['title'],
        'type' => ucfirst(str_replace('_', ' ', $row['type'])),
        'thumbnail' => Helper::thumbnailUrl($row['thumbnail']),
        'alt' => Helper::imageAlt($row['image_alt'] ?? '', $row['title'] ?? ''),
        'time_ago' => Helper::timeAgo($row['published_at'] ?? 'now'),
        'url' => '/' . ($row['category_slug'] ?: 'news') . '/' . $row['slug'],
    ];
}
foreach ($userRows as $row) {
    $results[] = [
        'title' => $row['full_name'],
        'type' => 'Profile',
        'thumbnail' => Helper::avatarUrl($row['avatar']),
        'alt' => $row['full_name'],
        'time_ago' => '@' . $row['username'],
        'url' => '/@' . $row['username'],
    ];
}

Helper::json(['success' => true, 'results' => array_slice($results, 0, 10)]);
