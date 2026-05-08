<?php
require_once __DIR__ . '/../includes/bootstrap.php';

Csrf::check();
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Helper::json(['error' => 'Method not allowed'], 405);
}

$targetDir = trim((string)($_POST['dir'] ?? 'thumbnails'));
$allowedDirs = ['thumbnails', 'avatars', 'content', 'community', 'stories'];
if (!in_array($targetDir, $allowedDirs, true)) {
    $targetDir = 'thumbnails';
}

$file = $_FILES['file'] ?? $_FILES['image'] ?? $_FILES['thumbnail'] ?? null;
if (!$file || !is_array($file)) {
    Helper::json(['error' => 'No file uploaded'], 422);
}

$filename = Upload::image($file, $targetDir);
if (!$filename) {
    Helper::json(['error' => 'Upload failed. Check file type and size.'], 422);
}

Helper::json([
    'success' => true,
    'message' => 'Upload complete.',
    'filename' => $filename,
    'url' => Helper::publicUrl('uploads/' . $targetDir . '/' . $filename),
    'path' => '/public/uploads/' . $targetDir . '/' . $filename,
]);
