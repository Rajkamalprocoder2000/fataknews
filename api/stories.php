<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$storyModel = new StoryModel();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $userId = (int)($_GET['user_id'] ?? 0);
    if ($userId < 1) {
        Helper::json(['error' => 'Story user is required'], 422);
    }

    $stories = $storyModel->getUserStories($userId, Auth::id());
    if (!$stories) {
        Helper::json(['success' => true, 'stories' => []]);
    }

    $items = array_map(static function (array $story): array {
        $mediaPath = trim((string)($story['media_path'] ?? ''));

        return [
            'id' => (int)$story['id'],
            'user_id' => (int)$story['user_id'],
            'is_owner' => Auth::id() ? ((int)$story['user_id'] === (int)Auth::id()) : false,
            'username' => $story['username'],
            'full_name' => $story['full_name'],
            'avatar' => Helper::avatarUrl($story['avatar'] ?? null),
            'media_type' => $story['media_type'],
            'media_url' => $mediaPath !== '' ? Helper::publicUrl('uploads/stories/' . rawurlencode($mediaPath)) : null,
            'caption' => $story['caption'] ?? '',
            'background_color' => $story['background_color'] ?? '#2D2244',
            'text_color' => $story['text_color'] ?? '#FFFFFF',
            'created_at' => $story['created_at'],
            'time_ago' => Helper::timeAgo($story['created_at']),
            'is_viewed' => (int)($story['is_viewed'] ?? 0),
            'views_count' => (int)($story['views_count'] ?? 0),
        ];
    }, $stories);

    Helper::json([
        'success' => true,
        'stories' => $items,
        'user' => [
            'id' => (int)$stories[0]['user_id'],
            'username' => $stories[0]['username'],
            'full_name' => $stories[0]['full_name'],
            'avatar' => Helper::avatarUrl($stories[0]['avatar'] ?? null),
        ],
    ]);
}

Csrf::check();
Auth::requireLogin();

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = trim((string)($input['action'] ?? ''));

switch ($action) {
    case 'create':
        $caption = trim((string)($input['caption'] ?? ''));
        $mediaPath = basename(trim((string)($input['media_path'] ?? '')));
        $mediaType = $mediaPath !== '' ? 'image' : 'text';

        if ($caption === '' && $mediaType === 'text') {
            Helper::json(['error' => 'Add text or upload an image for the story'], 422);
        }

        if ($storyModel->getActiveCountForUser((int)Auth::id()) >= 10) {
            Helper::json(['error' => 'You already have 10 active stories. Wait for some to expire.'], 422);
        }

        $storyId = $storyModel->createStory((int)Auth::id(), [
            'media_type' => $mediaType,
            'media_path' => $mediaPath,
            'caption' => $caption,
            'background_color' => $input['background_color'] ?? '#2D2244',
            'text_color' => $input['text_color'] ?? '#FFFFFF',
        ]);

        Helper::json([
            'success' => true,
            'message' => 'Story published.',
            'story_id' => (int)$storyId,
        ]);

    case 'view':
        $storyId = (int)($input['story_id'] ?? 0);
        if ($storyId < 1) {
            Helper::json(['error' => 'Invalid story'], 422);
        }

        $storyModel->markViewed($storyId, (int)Auth::id());
        Helper::json(['success' => true]);

    case 'delete':
        $storyId = (int)($input['story_id'] ?? 0);
        if ($storyId < 1) {
            Helper::json(['error' => 'Invalid story'], 422);
        }

        if (!$storyModel->deleteOwnedStory($storyId, (int)Auth::id())) {
            Helper::json(['error' => 'Story not found or not allowed'], 404);
        }

        Helper::json(['success' => true, 'message' => 'Story deleted.']);

    default:
        Helper::json(['error' => 'Unknown action'], 400);
}
