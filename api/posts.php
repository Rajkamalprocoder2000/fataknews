<?php
// api/posts.php
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (trim((string)($_GET['mode'] ?? '')) === 'seo-check') {
        $postModel = new PostModel();
        $db = Database::getInstance();
        $postId = (int)($_GET['post_id'] ?? 0);
        $title = trim((string)($_GET['title'] ?? ''));
        $requestedSlug = trim((string)($_GET['slug'] ?? ''));
        $excerpt = trim((string)($_GET['excerpt'] ?? ''));
        $content = trim((string)($_GET['content'] ?? ''));
        $categoryId = (int)($_GET['category_id'] ?? 0);
        $existingPost = $postId > 0 ? $postModel->findById($postId) : null;
        $currentSlug = trim((string)($existingPost['slug'] ?? ''));
        $finalSlug = $postModel->buildSlug($title, $requestedSlug, $postId, $currentSlug);
        $titleConflicts = 0;
        $categoryName = '';

        if ($categoryId > 0) {
            $category = $db->fetchOne("SELECT name FROM categories WHERE id=?", [$categoryId]);
            $categoryName = trim((string)($category['name'] ?? ''));
        }

        if ($title !== '') {
            $titleRow = $db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM posts
                 WHERE LOWER(TRIM(title)) = LOWER(TRIM(?)) AND id <> ?",
                [$title, $postId]
            );
            $titleConflicts = (int)($titleRow['total'] ?? 0);
        }

        Helper::json([
            'success' => true,
            'title_conflicts' => $titleConflicts,
            'custom_slug_conflict' => $requestedSlug !== '' && $finalSlug !== Helper::slug($requestedSlug),
            'final_slug' => $finalSlug,
            'recommended_excerpt' => Helper::normalizedExcerpt($excerpt !== '' ? $excerpt : $content, 220),
            'recommended_seo_title' => Helper::defaultSeoTitle($title, $categoryName),
            'recommended_seo_description' => Helper::defaultSeoDescription($excerpt, $content, $categoryName),
            'recommended_image_alt' => Helper::defaultImageAlt($title, $categoryName),
        ]);
    }

    $db = Database::getInstance();
    $postModel = new PostModel();
    $categoryModel = new CategoryModel();
    $categoryId = (int)($_GET['category'] ?? 0);
    $page = max(1, (int)($_GET['page'] ?? 1));
    $visibilityFilter = $categoryId > 0
        ? "COALESCE(p.location,'') IN ('', 'home', 'category', 'both')"
        : $postModel->getHomeVisibilityFilter('p');
    $where = "WHERE p.status='published' AND " . $visibilityFilter . " AND p.type IN ('news','article','breaking')";
    $params = [];

    if ($categoryId > 0) {
        $categoryIds = $categoryModel->getSelfAndDescendantIds($categoryId);

        if (empty($categoryIds)) {
            $categoryIds = [$categoryId];
        }

        $where .= " AND p.category_id IN (" . implode(',', array_fill(0, count($categoryIds), '?')) . ")";
        array_push($params, ...$categoryIds);
    }

    $feed = $db->paginate(
        "SELECT p.*, u.username, u.full_name, u.avatar, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
         FROM posts p
         JOIN users u ON p.user_id=u.id
         LEFT JOIN categories c ON p.category_id=c.id
         $where
         ORDER BY p.published_at DESC",
        $params,
        $page
    );

    ob_start();
    foreach ($feed['data'] as $post):
        $postUrl = Helper::siteUrl(($post['category_slug'] ?: 'news') . '/' . $post['slug']);
        $categoryUrl = Helper::siteUrl('category/' . $post['category_slug']);
        ?>
        <article class="news-card">
          <a href="<?= $postUrl ?>"><img src="<?= Helper::thumbnailUrl($post['thumbnail']) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($post['image_alt'] ?? '', $post['title'] ?? '')) ?>" class="news-card-img"></a>
          <div style="flex:1">
            <div class="news-card-body">
              <?php if (!empty($post['category_name'])): ?>
              <a href="<?= $categoryUrl ?>" class="news-card-cat" style="color:<?= $post['category_color'] ?>"><?= Helper::sanitize($post['category_name']) ?></a>
              <?php endif; ?>
              <h3 class="news-card-title"><a href="<?= $postUrl ?>"><?= Helper::sanitize($post['title']) ?></a></h3>
              <div class="news-card-meta">
                <img src="<?= Helper::avatarUrl($post['avatar']) ?>" alt="<?= Helper::sanitize($post['full_name'] ?? 'FatakNews Desk') ?>">
                <span><?= Helper::sanitize($post['full_name']) ?></span>
                <span><?= Helper::timeAgo($post['published_at'] ?? $post['created_at']) ?></span>
              </div>
            </div>
            <div class="news-card-actions">
              <button class="action-btn" data-react="<?= $post['id'] ?>" data-type="like"><i class="fa fa-heart"></i><span class="react-count"><?= Helper::formatNumber($post['likes_count']) ?></span></button>
              <a href="<?= $postUrl ?>#comments" class="action-btn"><i class="fa fa-comment"></i><span><?= Helper::formatNumber($post['comments_count']) ?></span></a>
              <button class="action-btn" data-bookmark="<?= $post['id'] ?>"><i class="fa fa-bookmark"></i></button>
              <span class="action-btn" style="margin-left:auto;cursor:default"><i class="fa fa-eye"></i> <?= Helper::formatNumber($post['views_count']) ?></span>
            </div>
          </div>
        </article>
        <?php
    endforeach;

    if (empty($feed['data'])) {
        echo '<div class="empty-state"><i class="fa fa-newspaper"></i><h3>No posts found</h3></div>';
    }

    Helper::json(['success' => true, 'html' => ob_get_clean()]);
}

Csrf::check();
Auth::requireLogin();
$input     = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$postModel = new PostModel();
$uri       = strtok($_SERVER['REQUEST_URI'], '?');

if (str_ends_with($uri, '/react')) {
    $postId = (int)($input['post_id'] ?? 0);
    $type   = $input['type'] ?? 'like';
    if (!$postId) Helper::json(['error' => 'Invalid post'], 400);
    $result = $postModel->react($postId, Auth::id(), $type);
    Helper::json(array_merge($result, ['success' => true]));
}

if (str_ends_with($uri, '/bookmark')) {
    $postId = (int)($input['post_id'] ?? 0);
    if (!$postId) Helper::json(['error' => 'Invalid post'], 400);
    $action = $postModel->bookmark($postId, Auth::id());
    Helper::json(['success' => true, 'action' => $action]);
}

// Create/Update post
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $editId = (int)($_POST['post_id'] ?? 0);
    $existingPost = $editId ? $postModel->findById($editId) : null;
    if ($editId && !$existingPost) {
        Helper::json(['error' => 'Post not found'], 404);
    }

    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $status  = $_POST['status'] ?? 'draft';
    if (!$title || !$content) Helper::json(['error' => 'Title and content are required'], 400);
    $requestedLocation = trim((string)($_POST['location'] ?? 'both'));
    $allowedLocations = ['home', 'category', 'both', 'explore'];
    $normalizedLocation = in_array($requestedLocation, $allowedLocations, true) ? $requestedLocation : 'both';

    $requestedSubcategoryId = (int)($_POST['subcategory_id'] ?? 0);
    $requestedCategoryId = (int)($_POST['category_id'] ?? 0);
    $resolvedCategoryId = $requestedSubcategoryId > 0
        ? $requestedSubcategoryId
        : ($requestedCategoryId > 0 ? $requestedCategoryId : null);

    $data = [
        'user_id'        => Auth::id(),
        'title'          => $title,
        'slug'           => trim($_POST['slug'] ?? ''),
        'content'        => $content,
        'excerpt'        => trim($_POST['excerpt'] ?? ''),
        'category_id'    => $resolvedCategoryId,
        'type'           => in_array($_POST['type'] ?? '', ['news','article','community_post','thought','breaking']) ? $_POST['type'] : 'news',
        'location'       => $normalizedLocation,
        'video_url'      => trim($_POST['video_url'] ?? ''),
        'status'         => $status === 'published' && !Auth::isManager() ? 'pending' : $status,
        'is_featured'    => isset($_POST['is_featured']) ? 1 : 0,
        'is_breaking'    => isset($_POST['is_breaking']) && Auth::isManager() ? 1 : 0,
        'allow_comments' => isset($_POST['allow_comments']) ? 1 : 0,
        'source_name'    => trim($_POST['source_name'] ?? ''),
        'source_url'     => trim($_POST['source_url'] ?? ''),
        'image_alt'      => trim($_POST['image_alt'] ?? ''),
        'seo_title'      => trim($_POST['seo_title'] ?? ''),
        'seo_description'=> trim($_POST['seo_description'] ?? ''),
    ];

    $isCommunityType = in_array($data['type'], ['community_post', 'thought'], true);

    if (!$isCommunityType && $data['category_id'] === null) {
        Helper::json(['error' => 'Category select karna required hai.'], 422);
    }

    // Thumbnail upload
    $thumbnailUploaded = isset($_FILES['thumbnail']) && (int)($_FILES['thumbnail']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($thumbnailUploaded) {
        $imageError = Upload::imageError($_FILES['thumbnail']);
        if ($imageError !== null) {
            Helper::json(['error' => $imageError], 422);
        }

        $filename = Upload::image($_FILES['thumbnail'], 'thumbnails');
        if (!$filename) {
            Helper::json(['error' => 'Cover image save nahi ho paayi. Please retry.'], 500);
        }

        $data['thumbnail'] = $filename;
    }

    if (!$isCommunityType && $data['location'] !== 'explore' && empty($data['thumbnail']) && empty($existingPost['thumbnail'])) {
        Helper::json(['error' => 'Standard article/news post ke liye cover image required hai.'], 422);
    }

    try {
        if ($editId) {
            $postModel->update($editId, $data);
            $postId = $editId;
        } else {
            $postId = $postModel->create($data);
        }

        // Tags
        if (!empty($_POST['tags'])) {
            $db   = Database::getInstance();
            $tags = array_map('trim', explode(',', $_POST['tags']));
            $db->delete('post_tags', 'post_id=?', [$postId]);
            foreach ($tags as $tagName) {
                if (!$tagName) continue;
                $slug   = Helper::slug($tagName);
                $tagRow = $db->fetchOne("SELECT id FROM tags WHERE slug=?", [$slug]);
                $tagId  = $tagRow ? $tagRow['id'] : $db->insert('tags', ['name' => $tagName, 'slug' => $slug]);
                $db->insert('post_tags', ['post_id' => $postId, 'tag_id' => $tagId]);
            }
        }
    } catch (InvalidArgumentException $e) {
        Helper::json(['error' => $e->getMessage()], 422);
    }

    Helper::json([
        'success' => true,
        'post_id' => $postId,
        'message' => $status === 'draft' ? 'Draft saved' : 'Submitted for review',
        'csrf_token' => Csrf::token(),
    ]);
}
