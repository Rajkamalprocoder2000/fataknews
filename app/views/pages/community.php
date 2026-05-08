<?php
$db = Database::getInstance();
$storyModel = new StoryModel();
$page = max(1, (int)($_GET['page'] ?? 1));
$pageTitle = 'Community News, Opinions & Reader Stories' . ($page > 1 ? ' - Page ' . $page : '') . ' | FatakNews';
$pageDesc = 'Read community stories, opinions, and reader posts published on FatakNews.';
$community = $db->paginate(
    "SELECT p.*, u.username, u.full_name, u.avatar, u.is_verified, u.badge_level,
            c.name AS category_name, c.slug AS category_slug, c.color AS category_color
     FROM posts p
     JOIN users u ON p.user_id=u.id
     LEFT JOIN categories c ON p.category_id=c.id
     WHERE p.status='published' AND p.type IN ('community_post','thought')
     ORDER BY COALESCE(p.published_at, p.created_at) DESC",
    [],
    $page,
    10
);
$storyGroups = $storyModel->getActiveGroups(Auth::id());
$communityStories = array_slice($storyGroups, 0, 10);
$communityStoryPalette = ['#2D2244', '#4D2A5A', '#233C62', '#4B263C', '#244B4B'];
$authCommunityUser = Auth::check() ? Auth::user() : null;
$canCreateCommunityStories = Auth::check();
$communityOwnStoryCount = Auth::check() ? $storyModel->getActiveCountForUser((int)Auth::id()) : 0;
$canonicalUrl = Helper::siteUrl('community' . ($page > 1 ? '?page=' . $page : ''));
$prevUrl = $page > 1 ? Helper::siteUrl('community' . ($page > 2 ? '?page=' . ($page - 1) : '')) : null;
$nextUrl = $page < (int)($community['pages'] ?? 1) ? Helper::siteUrl('community?page=' . ($page + 1)) : null;
$breadcrumbItems = [
    ['name' => 'Home', 'url' => Helper::siteUrl()],
    ['name' => 'Community', 'url' => $canonicalUrl],
];
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $pageTitle,
        'description' => $pageDesc,
        'url' => $canonicalUrl,
    ],
    Helper::breadcrumbSchema($breadcrumbItems),
    Helper::collectionItemListSchema($community['data'] ?? [], $canonicalUrl),
];
$bodyClass = 'community-page';
include VIEW . 'layouts/header.php';
?>
<div class="home-grid">
  <main class="feed-col">
    <?= Helper::breadcrumbNav($breadcrumbItems) ?>
    <?php if (!empty($communityStories) || $canCreateCommunityStories): ?>
    <section class="sidebar-widget community-stories-card">
      <div class="widget-title"><i class="fa fa-circle-play"></i> Stories</div>
      <div class="community-story-strip">
        <?php if ($canCreateCommunityStories): ?>
        <button
          type="button"
          class="mobile-story-chip mobile-story-entry mobile-story-self <?= $communityOwnStoryCount > 0 ? 'has-unseen' : 'is-empty' ?>"
          <?= $communityOwnStoryCount > 0 ? 'data-story-user="' . (int)Auth::id() . '"' : 'id="mobileOwnStoryBtn"' ?>
        >
          <span class="mobile-story-ring">
            <img src="<?= Helper::avatarUrl($authCommunityUser['avatar'] ?? null) ?>" alt="<?= Helper::sanitize($authCommunityUser['full_name'] ?? 'Your story') ?>" width="72" height="72" decoding="async">
            <?php if ($communityOwnStoryCount > 0): ?>
            <span class="mobile-story-count"><?= $communityOwnStoryCount ?></span>
            <?php endif; ?>
            <span class="mobile-story-plus" data-story-create><i class="fa fa-plus"></i></span>
          </span>
          <span>Your Story</span>
        </button>
        <?php endif; ?>

        <?php foreach ($communityStories as $storyUser): ?>
        <?php if (Auth::check() && (int)$storyUser['user_id'] === (int)Auth::id()) { continue; } ?>
        <button
          type="button"
          class="mobile-story-chip mobile-story-entry <?= !empty($storyUser['has_unseen']) ? 'has-unseen' : 'is-seen' ?>"
          data-story-user="<?= (int)$storyUser['user_id'] ?>"
          data-story-name="<?= Helper::sanitize($storyUser['full_name']) ?>"
        >
          <span class="mobile-story-ring" style="--story-color:<?= !empty($storyUser['has_unseen']) ? '#FF6A42' : '#C9C0E8' ?>">
            <img src="<?= Helper::avatarUrl($storyUser['avatar'] ?? null) ?>" alt="<?= Helper::sanitize($storyUser['full_name']) ?>" width="72" height="72" decoding="async">
          </span>
          <span><?= Helper::sanitize($storyUser['full_name']) ?></span>
        </button>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <div class="community-create-cta">
      <a href="/community/create" class="btn-write"><i class="fa fa-pen"></i> Create community post</a>
    </div>

    <div class="community-feed">
      <?php foreach ($community['data'] as $index => $post):
        $postUrl = '/' . ($post['category_slug'] ?: 'community') . '/' . $post['slug'];
      ?>
      <article class="community-card" style="--card-cat-color:<?= Helper::sanitize($post['category_color'] ?: '#AA00FF') ?>">
        <div class="community-card-header">
          <img src="<?= Helper::avatarUrl($post['avatar']) ?>" alt="<?= Helper::sanitize($post['full_name']) ?>" class="community-avatar" width="44" height="44" decoding="async">
          <div class="community-user">
            <strong><a href="/@<?= $post['username'] ?>"><?= Helper::sanitize($post['full_name']) ?></a><?php if ($post['is_verified']): ?><i class="fa fa-check-circle verified-icon"></i><?php endif; ?></strong>
            <span>@<?= $post['username'] ?> - <?= Helper::timeAgo($post['published_at'] ?? $post['created_at']) ?></span>
          </div>
        </div>
        <div class="community-card-title"><a href="<?= $postUrl ?>"><?= Helper::sanitize($post['title']) ?></a></div>
        <div class="community-card-excerpt"><?= Helper::sanitize(Helper::excerpt($post['excerpt'] ?: $post['content'], 220)) ?></div>
        <?php if (!empty($post['thumbnail'])): ?>
        <img src="<?= Helper::thumbnailUrl($post['thumbnail']) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($post['image_alt'] ?? '', $post['title'] ?? '')) ?>" class="community-card-img" width="1200" height="675" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>" decoding="async">
        <?php endif; ?>
        <div class="community-card-footer">
          <button class="action-btn" data-react="<?= $post['id'] ?>" data-type="like"><i class="fa fa-heart"></i> <?= Helper::formatNumber($post['likes_count']) ?></button>
          <a class="action-btn" href="<?= $postUrl ?>#comments"><i class="fa fa-comment"></i> <?= Helper::formatNumber($post['comments_count']) ?></a>
          <button class="action-btn" data-bookmark="<?= $post['id'] ?>"><i class="fa fa-bookmark"></i></button>
        </div>
      </article>
      <?php endforeach; ?>

      <?php if (empty($community['data'])): ?>
      <div class="empty-state"><i class="fa fa-comments"></i><h3>No community posts yet</h3><p>The first community story can start here.</p></div>
      <?php endif; ?>
    </div>
  </main>

  <aside class="sidebar-col">
    <div class="sidebar-widget">
      <div class="widget-title"><i class="fa fa-circle-question"></i> Posting Rules</div>
      <p style="font-size:14px;color:var(--muted)">Community posts should be clear, civil, and topical. In this build, submissions go through the existing post API.</p>
    </div>
  </aside>
</div>

<?php if ($canCreateCommunityStories): ?>
<div class="story-composer-modal" id="storyComposerModal" hidden>
  <div class="story-modal-backdrop" data-story-close></div>
  <div class="story-composer-panel">
    <button type="button" class="story-modal-close" data-story-close aria-label="Close story composer"><i class="fa fa-times"></i></button>
    <div class="story-modal-kicker">FatakNews Story</div>
    <h2>Share a 24-hour story</h2>
    <p>Post a quick text update or upload an image story for your followers.</p>
    <form id="storyComposerForm" class="story-composer-form">
      <label>
        <span>Story text</span>
        <textarea name="caption" placeholder="What's happening right now?"></textarea>
      </label>
      <div>
        <span style="display:block;font-size:13px;font-weight:700;color:#D7D0F3;margin-bottom:8px">Background color</span>
        <div class="story-color-grid">
          <?php foreach ($communityStoryPalette as $index => $storyColor): ?>
          <label class="story-color-option">
            <input type="radio" name="background_color" value="<?= $storyColor ?>" <?= $index === 0 ? 'checked' : '' ?>>
            <span style="--story-bg:<?= $storyColor ?>"></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <label class="story-upload-field">
        <span>Optional image</span>
        <input type="file" name="story_image" accept="image/*">
      </label>
      <div class="story-upload-preview" id="storyUploadPreview" hidden></div>
      <button type="submit" class="btn-primary" id="storyComposerSubmit">Publish Story</button>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="story-viewer-modal" id="storyViewerModal" hidden>
  <div class="story-modal-backdrop" data-story-viewer-close></div>
  <div class="story-viewer-panel">
    <div class="story-viewer-progress" id="storyViewerProgress"></div>
    <button type="button" class="story-modal-close" data-story-viewer-close aria-label="Close story viewer"><i class="fa fa-times"></i></button>
    <button type="button" class="story-viewer-hit story-viewer-hit--prev" id="storyViewerPrev" aria-label="Previous story"></button>
    <button type="button" class="story-viewer-hit story-viewer-hit--next" id="storyViewerNext" aria-label="Next story"></button>
    <div class="story-viewer-media" id="storyViewerMedia"></div>
    <div class="story-viewer-overlay"></div>
    <div class="story-viewer-header">
      <div class="story-viewer-author">
        <img src="<?= Helper::avatarUrl($authCommunityUser['avatar'] ?? null) ?>" alt="Story author" id="storyViewerAvatar" width="42" height="42" decoding="async">
        <div>
          <strong id="storyViewerName">FatakNews</strong>
          <span id="storyViewerTime">Just now</span>
        </div>
      </div>
      <button type="button" class="story-viewer-delete" id="storyViewerDelete" hidden><i class="fa fa-trash"></i> Delete</button>
    </div>
    <div class="story-viewer-caption" id="storyViewerCaption"></div>
  </div>
</div>
<?php include VIEW . 'layouts/mobile_bottom_nav.php'; ?>
<?php include VIEW . 'layouts/footer.php'; ?>
