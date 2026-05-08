<?php
$db = Database::getInstance();
$page = max(1, (int)($_GET['page'] ?? 1));
$pageTitle = 'Explore Viral News, Videos & Social Updates' . ($page > 1 ? ' - Page ' . $page : '') . ' | FatakNews';
$pageDesc = 'Discover viral videos, social media posts, and fast visual news updates curated by the FatakNews team.';
$items = $db->paginate(
    "SELECT p.*, u.username, u.full_name, u.avatar, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
     FROM posts p
     JOIN users u ON p.user_id=u.id
     LEFT JOIN categories c ON p.category_id=c.id
     WHERE p.status='published' AND p.location='explore'
     ORDER BY COALESCE(p.published_at, p.created_at) DESC",
    [],
    $page,
    9
);
$canonicalUrl = Helper::siteUrl('explore' . ($page > 1 ? '?page=' . $page : ''));
$prevUrl = $page > 1 ? Helper::siteUrl('explore' . ($page > 2 ? '?page=' . ($page - 1) : '')) : null;
$nextUrl = $page < (int)($items['pages'] ?? 1) ? Helper::siteUrl('explore?page=' . ($page + 1)) : null;
$breadcrumbItems = [
    ['name' => 'Home', 'url' => Helper::siteUrl()],
    ['name' => 'Explore', 'url' => $canonicalUrl],
];
$extraHead = <<<HTML
<style>
.explore-page .explore-meta,
.explore-page .explore-meta * {
  color: #5f5878 !important;
  opacity: 1 !important;
}
.explore-page .explore-summary,
.explore-page .explore-summary * {
  color: #4c465f !important;
  opacity: 1 !important;
}
.explore-page .explore-social-label,
.explore-page .explore-social-label * {
  color: #5b5572 !important;
  opacity: 1 !important;
}
.explore-page .explore-caption,
.explore-page .explore-caption *,
.explore-page .explore-caption p,
.explore-page .explore-caption li,
.explore-page .explore-caption span {
  color: #3f3955 !important;
  opacity: 1 !important;
}
.explore-page .explore-caption {
  font-size: 15px !important;
  font-weight: 500 !important;
  line-height: 1.82 !important;
}
.explore-page .explore-actions,
.explore-page .explore-actions .action-btn,
.explore-page .explore-actions .action-btn * {
  color: #5f5878 !important;
  opacity: 1 !important;
}
</style>
HTML;
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $pageTitle,
        'description' => $pageDesc,
        'url' => $canonicalUrl,
        'mainEntity' => [
            '@type' => 'ItemList',
            'itemListElement' => array_values(array_map(
                static function (array $post, int $index): array {
                    return [
                        '@type' => 'ListItem',
                        'position' => $index + 1,
                        'url' => Helper::siteUrl(($post['category_slug'] ?: 'news') . '/' . $post['slug']),
                        'name' => $post['title'],
                    ];
                },
                $items['data'] ?? [],
                array_keys($items['data'] ?? [])
            )),
        ],
    ],
    Helper::breadcrumbSchema($breadcrumbItems),
];
$bodyClass = 'explore-page';
include VIEW . 'layouts/header.php';
?>
<div class="home-grid">
  <main class="feed-col">
    <?= Helper::breadcrumbNav($breadcrumbItems) ?>
    <div class="explore-grid">
      <?php foreach ($items['data'] as $index => $post):
        $postUrl = '/' . ($post['category_slug'] ?: 'news') . '/' . $post['slug'];
        $youtubeEmbed = Helper::youtubeEmbedUrl($post['video_url'] ?? '');
        $socialUrl = trim((string)($post['source_url'] ?? ''));
        $socialEmbed = Helper::socialEmbedHtml($socialUrl);
      ?>
      <article class="explore-card" style="--card-cat-color:<?= Helper::sanitize($post['category_color'] ?: '#2979FF') ?>;border:1px solid <?= Helper::sanitize(($post['category_color'] ?: '#2979FF') . '55') ?>;box-shadow:0 16px 34px <?= Helper::sanitize(($post['category_color'] ?: '#2979FF') . '20') ?>,0 3px 10px rgba(53,45,88,.06)">
        <div class="explore-card-head">
          <div>
            <div class="explore-kicker">
              <span><i class="fa fa-compass"></i> Explore</span>
              <?php if (!empty($post['category_name'])): ?>
              <span style="color:<?= $post['category_color'] ?>"><?= Helper::sanitize($post['category_name']) ?></span>
              <?php endif; ?>
            </div>
            <h2><a href="<?= $postUrl ?>"><?= Helper::sanitize($post['title']) ?></a></h2>
          </div>
          <div class="explore-meta">
            <span><?= Helper::sanitize($post['full_name']) ?></span>
            <span><?= Helper::timeAgo($post['published_at'] ?? $post['created_at']) ?></span>
          </div>
        </div>

        <?php if (!empty($post['excerpt'])): ?>
        <p class="explore-summary"><?= Helper::sanitize($post['excerpt']) ?></p>
        <?php endif; ?>

        <?php if (!$youtubeEmbed && $socialEmbed === '' && !empty($post['thumbnail'])): ?>
        <a href="<?= $postUrl ?>" class="explore-embed explore-embed-image">
          <img src="<?= Helper::thumbnailUrl($post['thumbnail']) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($post['image_alt'] ?? '', $post['title'] ?? '')) ?>" class="explore-image" width="1200" height="675" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>" decoding="async">
        </a>
        <?php endif; ?>

        <?php if ($youtubeEmbed): ?>
        <div class="explore-embed explore-embed-video">
          <iframe src="<?= Helper::sanitize($youtubeEmbed) ?>" title="<?= Helper::sanitize($post['title']) ?>" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </div>
        <?php endif; ?>

        <?php if ($socialEmbed !== ''): ?>
        <div class="explore-embed explore-embed-social">
          <div class="explore-social-label"><?= Helper::socialPlatformLabel($socialUrl) ?> post</div>
          <div class="explore-social-host" data-platform="<?= Helper::sanitize(strtolower(Helper::socialPlatformLabel($socialUrl))) ?>" data-social-url="<?= Helper::sanitize($socialUrl) ?>">
            <?= $socialEmbed ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($post['content'])): ?>
        <div class="explore-caption"><?= $post['content'] ?></div>
        <?php endif; ?>

        <div class="explore-actions">
          <button class="action-btn" data-react="<?= $post['id'] ?>" data-type="like"><i class="fa fa-heart"></i><span class="react-count"><?= Helper::formatNumber((int)$post['likes_count']) ?></span></button>
          <a href="<?= $postUrl ?>#comments" class="action-btn"><i class="fa fa-comment"></i><span><?= Helper::formatNumber((int)$post['comments_count']) ?></span></a>
          <button class="action-btn" data-bookmark="<?= $post['id'] ?>"><i class="fa fa-bookmark"></i></button>
          <a href="<?= $postUrl ?>" class="action-btn" style="margin-left:auto"><i class="fa fa-arrow-up-right-from-square"></i><span>Open</span></a>
        </div>
      </article>
      <?php endforeach; ?>

      <?php if (empty($items['data'])): ?>
      <div class="empty-state" style="grid-column:1/-1"><i class="fa fa-compass"></i><h3>No Explore items yet</h3><p>The team can publish embeds and videos from the backend content panel.</p></div>
      <?php endif; ?>
    </div>
  </main>

  <aside class="sidebar-col">
    <div class="sidebar-widget">
      <div class="widget-title"><i class="fa fa-circle-info"></i> Publishing Note</div>
      <p style="font-size:14px;color:var(--muted)">Team members can mark content as `Explore` from the shared content creation panel and attach social URLs or YouTube links.</p>
    </div>
  </aside>
</div>
<?php include VIEW . 'layouts/mobile_bottom_nav.php'; ?>
<?php include VIEW . 'layouts/footer.php'; ?>
