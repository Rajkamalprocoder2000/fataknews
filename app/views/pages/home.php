<?php
require_once BASE_PATH . '/includes/bootstrap.php';

$pageTitle = 'FatakNews: Breaking News, Latest Headlines & Live Updates';
$pageDesc = 'Read breaking news, latest headlines, trending stories, and live updates from India across politics, business, sports, technology, and entertainment on FatakNews.';
$postModel = new PostModel();
$catModel = new CategoryModel();
$storyModel = new StoryModel();
$isAuthenticatedHome = Auth::check();
$breaking = $isAuthenticatedHome
    ? $postModel->getBreaking()
    : Helper::cacheRemember('home_breaking_v1', 60, static function () use ($postModel): array {
        return $postModel->getBreaking();
    });
$featured = $isAuthenticatedHome
    ? $postModel->getFeatured()
    : Helper::cacheRemember('home_featured_v1', 120, static function () use ($postModel): array {
        return $postModel->getFeatured();
    });
$trending = $isAuthenticatedHome
    ? $postModel->getTrending()
    : Helper::cacheRemember('home_trending_v1', 120, static function () use ($postModel): array {
        return $postModel->getTrending();
    });
$categories = $isAuthenticatedHome
    ? $catModel->getTopLevel()
    : Helper::cacheRemember('home_categories_v1', 300, static function () use ($catModel): array {
        return $catModel->getTopLevel();
    });
$categoryTree = $isAuthenticatedHome
    ? $catModel->getTree()
    : Helper::cacheRemember('home_category_tree_v1', 300, static function () use ($catModel): array {
        return $catModel->getTree();
    });
$homeStoryRoles = ['super_admin', 'admin', 'manager', 'editor', 'reporter', 'hr'];
$storyGroups = $isAuthenticatedHome
    ? $storyModel->getActiveGroups(Auth::id(), 12, $homeStoryRoles)
    : Helper::cacheRemember('home_story_groups_v1', 60, static function () use ($storyModel, $homeStoryRoles): array {
        return $storyModel->getActiveGroups(null, 12, $homeStoryRoles);
    });
$page = max(1, (int)($_GET['page'] ?? 1));
$feed = $isAuthenticatedHome
    ? $postModel->getLatest($page)
    : Helper::cacheRemember('home_feed_v1_' . $page, 60, static function () use ($postModel, $page): array {
        return $postModel->getLatest($page);
    });
$db = Database::getInstance();
$renderMobileHome = Helper::isMobileClient();
$categoryTreeById = [];
foreach ($categoryTree as $treeNode) {
    $categoryTreeById[(int)$treeNode['id']] = $treeNode;
}
$collectCategoryIds = static function (array $node) use (&$collectCategoryIds): array {
    $ids = [(int)($node['id'] ?? 0)];
    foreach (($node['children'] ?? []) as $childNode) {
        $ids = array_merge($ids, $collectCategoryIds($childNode));
    }
    return $ids;
};
$categoryTopMap = [];
foreach ($categories as $categoryItem) {
    $categoryId = (int)($categoryItem['id'] ?? 0);
    $categoryNode = $categoryTreeById[$categoryId] ?? ($categoryItem + ['children' => []]);
    $categoryIds = array_values(array_filter(array_unique($collectCategoryIds($categoryNode))));
    foreach ($categoryIds as $descendantId) {
        $categoryTopMap[(int)$descendantId] = $categoryId;
    }
}

$categorySpotlights = Helper::cacheRemember(
    'home_category_spotlights_v3_' . md5(json_encode(array_column($categories, 'id'))),
    300,
    static function () use ($categories, $db, $postModel, $categoryTopMap): array {
        if (empty($categories) || empty($categoryTopMap)) {
            return [];
        }

        $allCategoryIds = array_values(array_unique(array_map('intval', array_keys($categoryTopMap))));
        $placeholders = implode(',', array_fill(0, count($allCategoryIds), '?'));
        $rows = $db->fetchAll(
            "SELECT p.title, p.slug, p.category_id, p.views_count, p.published_at, c.slug AS category_slug
             FROM posts p
             LEFT JOIN categories c ON p.category_id=c.id
             WHERE p.status='published'
               AND " . $postModel->getHomeVisibilityFilter('p') . "
               AND p.type IN ('news','article','breaking')
               AND p.category_id IN ($placeholders)
             ORDER BY COALESCE(p.published_at, p.created_at) DESC",
            $allCategoryIds
        );

        $postsByTop = [];
        $remainingSlots = max(1, count($categories)) * 5;

        foreach ($rows as $row) {
            $topCategoryId = (int)($categoryTopMap[(int)($row['category_id'] ?? 0)] ?? 0);
            if ($topCategoryId <= 0) {
                continue;
            }

            $postsByTop[$topCategoryId] ??= [];
            if (count($postsByTop[$topCategoryId]) >= 5) {
                continue;
            }

            $postsByTop[$topCategoryId][] = $row;
            $remainingSlots--;

            if ($remainingSlots <= 0) {
                break;
            }
        }

        $spotlights = [];
        foreach ($categories as $categoryItem) {
            $categoryId = (int)($categoryItem['id'] ?? 0);
            $spotlights[] = [
                'category' => $categoryItem,
                'posts' => $postsByTop[$categoryId] ?? [],
            ];
        }

        return $spotlights;
    }
);
$exploreLatest = $isAuthenticatedHome
    ? $db->fetchAll(
        "SELECT p.*, u.full_name, u.avatar, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
         FROM posts p
         JOIN users u ON p.user_id=u.id
         LEFT JOIN categories c ON p.category_id=c.id
         WHERE p.status='published' AND p.location='explore'
         ORDER BY COALESCE(p.published_at, p.created_at) DESC
         LIMIT 5"
    )
    : Helper::cacheRemember('home_explore_latest_v1', 120, static function () use ($db): array {
        return $db->fetchAll(
            "SELECT p.*, u.full_name, u.avatar, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
             FROM posts p
             JOIN users u ON p.user_id=u.id
             LEFT JOIN categories c ON p.category_id=c.id
             WHERE p.status='published' AND p.location='explore'
             ORDER BY COALESCE(p.published_at, p.created_at) DESC
             LIMIT 5"
        );
    });
$communityLatest = $isAuthenticatedHome
    ? $db->fetchAll(
        "SELECT p.*, u.full_name, u.avatar, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
         FROM posts p
         JOIN users u ON p.user_id=u.id
         LEFT JOIN categories c ON p.category_id=c.id
         WHERE p.status='published' AND p.type IN ('community_post','thought')
         ORDER BY COALESCE(p.published_at, p.created_at) DESC
         LIMIT 5"
    )
    : Helper::cacheRemember('home_community_latest_v1', 120, static function () use ($db): array {
        return $db->fetchAll(
            "SELECT p.*, u.full_name, u.avatar, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
             FROM posts p
             JOIN users u ON p.user_id=u.id
             LEFT JOIN categories c ON p.category_id=c.id
             WHERE p.status='published' AND p.type IN ('community_post','thought')
             ORDER BY COALESCE(p.published_at, p.created_at) DESC
             LIMIT 5"
        );
    });
$suggestedUsers = $isAuthenticatedHome
    ? $db->fetchAll(
        "SELECT u.id,u.username,u.full_name,u.avatar,u.is_verified,u.badge_level,u.followers_count
         FROM users u
         WHERE u.role_id IN (1,2,3,4,5) AND u.is_active=1
         ORDER BY u.followers_count DESC
         LIMIT 5"
    )
    : Helper::cacheRemember('home_suggested_users_v1', 300, static function () use ($db): array {
        return $db->fetchAll(
            "SELECT u.id,u.username,u.full_name,u.avatar,u.is_verified,u.badge_level,u.followers_count
             FROM users u
             WHERE u.role_id IN (1,2,3,4,5) AND u.is_active=1
             ORDER BY u.followers_count DESC
             LIMIT 5"
        );
    });
$newsletterStatus = trim((string)($_GET['newsletter'] ?? ''));
$canonicalUrl = Helper::siteUrl();
$pageImage = Helper::thumbnailAssetUrl($breaking[0]['thumbnail'] ?? null) ?? Helper::thumbnailAssetUrl($featured[0]['thumbnail'] ?? null) ?? Helper::sitePublicUrl('assets/images/og-default.svg');
$heroPreloadImage = Helper::thumbnailAssetUrl($breaking[0]['thumbnail'] ?? null)
    ?? Helper::thumbnailAssetUrl($featured[0]['thumbnail'] ?? null)
    ?? Helper::thumbnailAssetUrl($feed['data'][0]['thumbnail'] ?? null);
if ($heroPreloadImage) {
    $extraHead = trim((string)($extraHead ?? '') . "\n" . '<link rel="preload" as="image" href="' . Helper::sanitize($heroPreloadImage) . '">');
}
$bodyClass = 'home-page';
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => 'FatakNews.in',
        'url' => $canonicalUrl,
        'description' => $pageDesc,
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => Helper::siteUrl('search') . '?q={search_term_string}',
            'query-input' => 'required name=search_term_string',
        ],
    ],
    Helper::organizationSchema(),
    Helper::collectionItemListSchema(array_slice(array_values(array_merge($breaking ?: [], $featured ?: [], $feed['data'] ?? [])), 0, 12), $canonicalUrl),
];

include VIEW . 'layouts/header.php';
?>
<?php if ($renderMobileHome): ?>
<?php
$mobileLead = $breaking[0] ?? ($featured[0] ?? ($feed['data'][0] ?? null));
$mobileBreaking = array_slice($breaking ?? [], 0, 3);
$mobileFeatured = array_slice($featured ?? [], 0, 3);
$mobileStories = array_slice($storyGroups, 0, 10);
$mobileCards = array_slice($feed['data'] ?? [], 0, 6);
$mobileTrending = array_slice($trending ?? [], 0, 5);
$mobileExplore = array_slice($exploreLatest ?? [], 0, 5);
$mobileSuggestedUsers = array_slice($suggestedUsers ?? [], 0, 5);
$categoryIconMap = [];
foreach ($categories as $categoryItem) {
    $categoryIconMap[$categoryItem['slug']] = $categoryItem['icon'];
}
$mobileStoryPalette = ['#2D2244', '#4D2A5A', '#233C62', '#4B263C', '#244B4B'];
$authHomeUser = Auth::check() ? Auth::user() : null;
$canCreateStories = Auth::isEmployee();
$isAdminHome = Auth::isAdmin();
$mobileNotifCount = Auth::check() ? (new NotificationModel())->countUnread((int)Auth::id()) : 0;
$ownStoryCount = Auth::check() ? $storyModel->getActiveCountForUser((int)Auth::id()) : 0;
?>
<section class="mobile-home-shell">
  <div class="mobile-home-frame">
    <div class="mobile-home-topbar">
      <a href="/" class="mobile-home-brand">
        <span class="mobile-home-brandmark">
          <img src="/public/assets/images/fataknew_logo.webp" alt="FatakNews logo" width="62" height="62" decoding="async">
        </span>
        <span class="mobile-home-brandcopy">
          <span class="mobile-home-brandtext">Fatak<span>News</span></span>
          <span class="mobile-home-brandslogan">News That Matter</span>
        </span>
      </a>
      <div class="mobile-home-topactions">
        <a href="/search" class="mobile-home-iconbtn" aria-label="Search">
          <i class="fa fa-search"></i>
        </a>
        <?php if (Auth::check()): ?>
        <a href="/notifications" class="mobile-home-iconbtn" aria-label="Notifications">
          <i class="fa fa-bell"></i>
          <?php if ($mobileNotifCount > 0): ?>
          <span class="mobile-home-dot"></span>
          <?php endif; ?>
        </a>
        <?php endif; ?>
        <button type="button" class="mobile-home-menubtn" id="mobileHomeMenuBtn" aria-label="Open menu" aria-expanded="false" aria-controls="mobileHomeMenu">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>

    <div class="mobile-home-menuoverlay" id="mobileHomeMenuOverlay" hidden></div>
    <aside class="mobile-home-menu" id="mobileHomeMenu" aria-label="Mobile Home Menu" hidden>
      <div class="mobile-home-menuhead">
        <strong>Menu</strong>
        <button type="button" class="mobile-home-menuclose" id="mobileHomeMenuClose" aria-label="Close menu"><i class="fa fa-times"></i></button>
      </div>

      <?php if (Auth::check() && $isAdminHome): ?>
      <div class="mobile-home-adminmenu">
        <div class="mobile-home-adminmenuhead">
          <img src="<?= Helper::avatarUrl($authHomeUser['avatar'] ?? null) ?>" alt="<?= Helper::sanitize($authHomeUser['full_name'] ?? 'Admin') ?>" class="mobile-home-adminavatar" width="68" height="68" decoding="async">
          <div class="mobile-home-adminmeta">
            <strong><?= Helper::sanitize($authHomeUser['full_name'] ?? 'Admin') ?></strong>
            <span>@<?= Helper::sanitize($authHomeUser['username'] ?? 'admin') ?></span>
          </div>
        </div>
        <div class="mobile-home-adminlinks">
          <a href="/profile"><i class="fa fa-user"></i><span>Profile</span></a>
          <a href="/bookmarks"><i class="fa fa-bookmark"></i><span>Bookmarks</span></a>
          <a href="/settings"><i class="fa fa-cog"></i><span>Settings</span></a>
          <a href="/admin"><i class="fa fa-shield-halved"></i><span>Admin Panel</span></a>
          <a href="/manager"><i class="fa fa-list-check"></i><span>Manager Panel</span></a>
          <a href="/employee"><i class="fa fa-briefcase"></i><span>Employee Panel</span></a>
          <a href="/hr"><i class="fa fa-id-card"></i><span>HR Panel</span></a>
        </div>
        <div class="mobile-home-adminfooter">
          <a href="/logout" class="mobile-home-adminlogout"><i class="fa fa-right-from-bracket"></i><span>Logout</span></a>
        </div>
      </div>
      <?php else: ?>
      <div class="mobile-home-menugroup">
        <div class="mobile-home-menutitle">Account</div>
        <div class="mobile-home-menusub">
          <a href="<?= Auth::check() ? '/profile' : '/login' ?>"><i class="fa fa-user"></i><span><?= Auth::check() ? 'Profile' : 'Login' ?></span></a>
          <?php if (Auth::check()): ?>
          <a href="/bookmarks"><i class="fa fa-bookmark"></i><span>Bookmarks</span></a>
          <a href="/settings"><i class="fa fa-cog"></i><span>Settings</span></a>
          <?php else: ?>
          <a href="/register"><i class="fa fa-user-plus"></i><span>Sign Up</span></a>
          <?php endif; ?>
        </div>
      </div>

      <div class="mobile-home-menugroup">
        <div class="mobile-home-menutitle">Discover</div>
        <div class="mobile-home-menusub">
          <a href="/trending"><i class="fa fa-fire"></i><span>Trending</span></a>
          <a href="/explore"><i class="fa fa-compass"></i><span>Explore</span></a>
          <a href="/community"><i class="fa fa-users"></i><span>Community</span></a>
        </div>
      </div>

      <div class="mobile-home-menugroup">
        <div class="mobile-home-menutitle">Topics</div>
        <div class="mobile-home-menusub">
          <?php foreach (array_slice($categories, 0, 6) as $menuCategory): ?>
          <a href="/category/<?= Helper::sanitize($menuCategory['slug']) ?>"><i class="fa <?= Helper::sanitize($menuCategory['icon']) ?>" style="color:<?= Helper::sanitize($menuCategory['color']) ?>"></i><span><?= Helper::sanitize($menuCategory['name']) ?></span></a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="mobile-home-menugroup">
        <div class="mobile-home-menutitle">Company</div>
        <div class="mobile-home-menusub">
          <a href="/about"><span>About Us</span></a>
          <a href="/careers"><span>Careers</span></a>
          <a href="/advertise"><span>Advertise</span></a>
          <a href="/contact"><span>Contact</span></a>
          <a href="/press"><span>Press Room</span></a>
        </div>
      </div>

      <div class="mobile-home-menugroup">
        <div class="mobile-home-menutitle">Legal</div>
        <div class="mobile-home-menusub">
          <a href="/privacy"><span>Privacy Policy</span></a>
          <a href="/terms"><span>Terms of Service</span></a>
          <a href="/disclaimer"><span>Disclaimer</span></a>
          <a href="/corrections"><span>Corrections</span></a>
          <a href="/sitemap.xml"><span>Sitemap</span></a>
        </div>
      </div>
      <?php endif; ?>

      <div class="mobile-home-menusocial">
        <div class="mobile-home-menutitle">Follow Us</div>
        <div class="mobile-home-menusociallinks">
          <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
          <a href="#" aria-label="Twitter/X"><i class="fab fa-x-twitter"></i></a>
          <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
          <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
          <a href="#" aria-label="Telegram"><i class="fab fa-telegram-plane"></i></a>
        </div>
      </div>
    </aside>

    <div class="mobile-home-stories">
      <?php if ($canCreateStories && Auth::check()): ?>
      <button
        type="button"
        class="mobile-story-chip mobile-story-entry mobile-story-self <?= $ownStoryCount > 0 ? 'has-unseen' : 'is-empty' ?>"
        <?= $ownStoryCount > 0 ? 'data-story-user="' . (int)Auth::id() . '"' : 'id="mobileOwnStoryBtn"' ?>
      >
        <span class="mobile-story-ring">
          <img src="<?= Helper::avatarUrl($authHomeUser['avatar'] ?? null) ?>" alt="<?= Helper::sanitize($authHomeUser['full_name'] ?? 'Your story') ?>" width="72" height="72" decoding="async">
          <?php if ($ownStoryCount > 0): ?>
          <span class="mobile-story-count"><?= $ownStoryCount ?></span>
          <?php endif; ?>
          <span class="mobile-story-plus" data-story-create><i class="fa fa-plus"></i></span>
        </span>
        <span>Your Story</span>
      </button>
      <?php endif; ?>
      <?php foreach ($mobileStories as $storyUser): ?>
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

    <div class="mobile-home-pills">
      <a href="/" class="mobile-home-pill active" style="--pill-color:#111">All</a>
      <?php foreach (array_slice($categories, 0, 4) as $pillCategory): ?>
      <a href="/category/<?= Helper::sanitize($pillCategory['slug']) ?>" class="mobile-home-pill" style="--pill-color:<?= Helper::sanitize($pillCategory['color']) ?>"><?= Helper::sanitize($pillCategory['name']) ?></a>
      <?php endforeach; ?>
    </div>

    <?php if ($mobileLead): ?>
    <?php
      $mobileLeadCategorySlug = $mobileLead['category_slug'] ?? 'news';
      $mobileLeadCategoryName = $mobileLead['category_name'] ?? 'Breaking';
      $mobileLeadTitle = $mobileLead['title'] ?? 'Untitled story';
      $mobileLeadSlug = $mobileLead['slug'] ?? '';
      $mobileLeadThumb = $mobileLead['thumbnail'] ?? '';
      $mobileLeadMetaAuthor = $mobileLead['full_name'] ?? 'FatakNews Desk';
    ?>
    <article class="mobile-home-hero">
      <?php if ($mobileLeadThumb !== ''): ?>
      <img src="<?= Helper::thumbnailUrl($mobileLeadThumb) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($mobileLead['image_alt'] ?? '', $mobileLeadTitle)) ?>" class="mobile-home-heroimg" width="720" height="960" loading="eager" fetchpriority="high" decoding="async">
      <?php else: ?>
      <div class="mobile-home-heroimg mobile-home-heroimg--placeholder"></div>
      <?php endif; ?>
      <div class="mobile-home-herogradient"></div>
      <div class="mobile-home-herocontent">
        <span class="mobile-home-herobadge"><i class="fa fa-bolt"></i> Breaking</span>
        <h2><a href="/<?= $mobileLeadCategorySlug ?>/<?= $mobileLeadSlug ?>"><?= Helper::sanitize($mobileLeadTitle) ?></a></h2>
        <div class="mobile-home-herometa">
          <span><?= Helper::sanitize($mobileLeadMetaAuthor) ?></span>
          <span><?= !empty($mobileLead['published_at']) ? Helper::timeAgo($mobileLead['published_at']) : 'Just now' ?></span>
          <span><?= Helper::formatNumber((int)($mobileLead['views_count'] ?? 0)) ?> views</span>
        </div>
      </div>
      <div class="mobile-home-herodots">
        <span class="active"></span><span></span><span></span>
      </div>
    </article>
    <?php endif; ?>

    <?php if (!empty($mobileBreaking)): ?>
    <div class="mobile-home-sectionhead">Breaking News</div>
    <div class="mobile-home-breaking">
      <?php foreach ($mobileBreaking as $breakingIndex => $breakingItem): ?>
      <?php
        $breakingCategorySlug = $breakingItem['category_slug'] ?? 'news';
        $breakingCategoryName = $breakingItem['category_name'] ?? 'Breaking';
        $breakingCategoryColor = $breakingItem['category_color'] ?? '#FF3B57';
        $breakingTitle = $breakingItem['title'] ?? 'Untitled story';
        $breakingSlug = $breakingItem['slug'] ?? '';
        $breakingThumb = $breakingItem['thumbnail'] ?? '';
        $breakingUrl = '/' . $breakingCategorySlug . '/' . $breakingSlug;
      ?>
      <article class="mobile-home-trendingcard" style="--mobile-card-color:<?= Helper::sanitize($breakingCategoryColor) ?>">
        <a href="<?= $breakingUrl ?>" class="mobile-home-trendingmedia" style="background:<?= Helper::sanitize($breakingCategoryColor) ?>22">
          <?php if ($breakingThumb !== ''): ?>
          <img src="<?= Helper::thumbnailUrl($breakingThumb) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($breakingItem['image_alt'] ?? '', $breakingTitle)) ?>" width="176" height="176" loading="lazy" decoding="async">
          <?php else: ?>
          <i class="fa <?= Helper::sanitize($categoryIconMap[$breakingCategorySlug] ?? 'fa-bolt') ?>"></i>
          <?php endif; ?>
        </a>
        <div class="mobile-home-trendingbody">
          <div class="mobile-home-trendingtopline">
            <span class="mobile-home-trendingrank"><i class="fa fa-bolt"></i> <?= str_pad((string)($breakingIndex + 1), 2, '0', STR_PAD_LEFT) ?></span>
            <span class="mobile-home-trendingtag" style="color:<?= Helper::sanitize($breakingCategoryColor) ?>"><?= Helper::sanitize($breakingCategoryName) ?></span>
          </div>
          <h3><a href="<?= $breakingUrl ?>"><?= Helper::sanitize($breakingTitle) ?></a></h3>
          <div class="mobile-home-trendingmeta">
            <span><?= Helper::sanitize($breakingItem['full_name'] ?? 'Desk') ?></span>
            <span><?= !empty($breakingItem['published_at']) ? Helper::timeAgo($breakingItem['published_at']) : 'Just now' ?></span>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($mobileFeatured)): ?>
    <div class="mobile-home-sectionhead">Featured</div>
    <div class="mobile-home-featuredslider" aria-label="Featured stories">
      <?php foreach ($mobileFeatured as $featuredItem): ?>
      <?php
        $featuredCategorySlug = $featuredItem['category_slug'] ?? 'news';
        $featuredCategoryName = $featuredItem['category_name'] ?? 'Featured';
        $featuredCategoryColor = $featuredItem['category_color'] ?? '#FF5F66';
        $featuredTitle = $featuredItem['title'] ?? 'Untitled story';
        $featuredSlug = $featuredItem['slug'] ?? '';
        $featuredThumb = $featuredItem['thumbnail'] ?? '';
        $featuredUrl = '/' . $featuredCategorySlug . '/' . $featuredSlug;
        $featuredSummary = Helper::excerpt($featuredItem['excerpt'] ?: $featuredItem['content'], 120);
      ?>
      <article class="mobile-home-featuredslide" style="--mobile-card-color:<?= Helper::sanitize($featuredCategoryColor) ?>">
        <a href="<?= $featuredUrl ?>" class="mobile-home-featuredvisual" style="background:linear-gradient(140deg, <?= Helper::sanitize($featuredCategoryColor) ?>55, #1D2431 78%)">
          <?php if ($featuredThumb !== ''): ?>
          <img src="<?= Helper::thumbnailUrl($featuredThumb) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($featuredItem['image_alt'] ?? '', $featuredTitle)) ?>" class="mobile-home-featuredimg" width="1200" height="675" loading="lazy" decoding="async">
          <?php else: ?>
          <span class="mobile-home-featuredicon"><i class="fa <?= Helper::sanitize($categoryIconMap[$featuredCategorySlug] ?? 'fa-star') ?>"></i></span>
          <?php endif; ?>
          <div class="mobile-home-featuredoverlay"></div>
          <div class="mobile-home-featuredcontent">
            <div class="mobile-home-featuredtopline">
              <span class="mobile-home-featuredkicker">Featured</span>
              <span class="mobile-home-featuredtag"><?= Helper::sanitize($featuredCategoryName) ?></span>
            </div>
            <h3><?= Helper::sanitize($featuredTitle) ?></h3>
            <p><?= Helper::sanitize($featuredSummary) ?></p>
            <div class="mobile-home-featuredmeta">
              <span><?= Helper::sanitize($featuredItem['full_name'] ?? 'Desk') ?></span>
              <span><?= !empty($featuredItem['published_at']) ? Helper::timeAgo($featuredItem['published_at']) : 'Just now' ?></span>
              <span><?= Helper::formatNumber((int)($featuredItem['views_count'] ?? 0)) ?> views</span>
            </div>
          </div>
        </a>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="mobile-home-sectionhead">Latest News</div>
    <div class="mobile-home-list">
      <?php foreach ($mobileCards as $card): ?>
      <?php
        $cardCategorySlug = $card['category_slug'] ?? 'news';
        $cardCategoryName = $card['category_name'] ?? 'General';
        $cardCategoryColor = $card['category_color'] ?? '#6E6A86';
        $cardTitle = $card['title'] ?? 'Untitled story';
        $cardSlug = $card['slug'] ?? '';
        $cardThumb = $card['thumbnail'] ?? '';
        $cardUrl = '/' . $cardCategorySlug . '/' . $cardSlug;
        $cardShareUrl = Helper::siteUrl(ltrim($cardUrl, '/'));
      ?>
      <article class="mobile-home-card" style="--mobile-card-color:<?= Helper::sanitize($cardCategoryColor) ?>">
        <a href="<?= $cardUrl ?>" class="mobile-home-cardmedia" style="background:<?= Helper::sanitize($cardCategoryColor) ?>22">
          <?php if ($cardThumb !== ''): ?>
          <img src="<?= Helper::thumbnailUrl($cardThumb) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($card['image_alt'] ?? '', $cardTitle)) ?>" width="640" height="480" loading="lazy" decoding="async">
          <?php else: ?>
          <i class="fa <?= Helper::sanitize($categoryIconMap[$cardCategorySlug] ?? 'fa-newspaper') ?>"></i>
          <?php endif; ?>
        </a>
        <div class="mobile-home-cardbody">
          <div class="mobile-home-cardtag" style="color:<?= Helper::sanitize($cardCategoryColor) ?>"><?= Helper::sanitize($cardCategoryName) ?></div>
          <h3><a href="<?= $cardUrl ?>"><?= Helper::sanitize($cardTitle) ?></a></h3>
          <div class="mobile-home-cardmeta">
            <span><?= Helper::sanitize($card['full_name'] ?? 'Desk') ?></span>
            <span><?= !empty($card['published_at']) ? Helper::timeAgo($card['published_at']) : 'Just now' ?></span>
          </div>
        </div>
        <div class="mobile-home-cardactions">
          <button type="button" class="mobile-home-cardactionbtn" data-react="<?= (int)($card['id'] ?? 0) ?>" data-type="like" aria-label="Like story">
            <i class="fa fa-heart"></i>
            <span class="react-count"><?= Helper::formatNumber((int)($card['likes_count'] ?? 0)) ?></span>
          </button>
          <a href="<?= $cardUrl ?>#comments" class="mobile-home-cardactionbtn" aria-label="View comments">
            <i class="fa fa-comment"></i>
            <span><?= Helper::formatNumber((int)($card['comments_count'] ?? 0)) ?></span>
          </a>
          <button type="button" class="mobile-home-cardactionbtn" data-bookmark="<?= (int)($card['id'] ?? 0) ?>" aria-label="Save story">
            <i class="fa fa-thumbtack"></i>
          </button>
          <button
            type="button"
            class="mobile-home-cardactionbtn"
            data-share-title="<?= Helper::sanitize($cardTitle) ?>"
            data-share-url="<?= Helper::sanitize($cardShareUrl) ?>"
            aria-label="Share story"
          >
            <i class="fa fa-arrow-up-right-from-square"></i>
          </button>
        </div>
      </article>
      <?php endforeach; ?>
    </div>

    <?php if (!empty($mobileTrending)): ?>
    <div class="mobile-home-sectionhead">
      Trending
      <a href="/trending" class="mobile-home-sectionlink">View More</a>
    </div>
    <div class="mobile-home-trending">
      <?php foreach ($mobileTrending as $trendIndex => $trend): ?>
      <?php
        $trendCategorySlug = $trend['category_slug'] ?? 'news';
        $trendCategoryName = $trend['category_name'] ?? 'General';
        $trendCategoryColor = $trend['category_color'] ?? '#FF6B1A';
        $trendTitle = $trend['title'] ?? 'Untitled story';
        $trendSlug = $trend['slug'] ?? '';
        $trendThumb = $trend['thumbnail'] ?? '';
        $trendUrl = '/' . $trendCategorySlug . '/' . $trendSlug;
      ?>
      <article class="mobile-home-trendingcard" style="--mobile-card-color:<?= Helper::sanitize($trendCategoryColor) ?>">
        <a href="<?= $trendUrl ?>" class="mobile-home-trendingmedia" style="background:<?= Helper::sanitize($trendCategoryColor) ?>22">
          <?php if ($trendThumb !== ''): ?>
          <img src="<?= Helper::thumbnailUrl($trendThumb) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($trend['image_alt'] ?? '', $trendTitle)) ?>" width="176" height="176" loading="lazy" decoding="async">
          <?php else: ?>
          <i class="fa <?= Helper::sanitize($categoryIconMap[$trendCategorySlug] ?? 'fa-fire') ?>"></i>
          <?php endif; ?>
        </a>
        <div class="mobile-home-trendingbody">
          <div class="mobile-home-trendingtopline">
            <span class="mobile-home-trendingrank">#<?= str_pad((string)($trendIndex + 1), 2, '0', STR_PAD_LEFT) ?></span>
            <span class="mobile-home-trendingtag" style="color:<?= Helper::sanitize($trendCategoryColor) ?>"><?= Helper::sanitize($trendCategoryName) ?></span>
          </div>
          <h3><a href="<?= $trendUrl ?>"><?= Helper::sanitize($trendTitle) ?></a></h3>
          <div class="mobile-home-trendingmeta">
            <span><?= Helper::formatNumber((int)($trend['views_count'] ?? 0)) ?> views</span>
            <span><?= !empty($trend['published_at']) ? Helper::timeAgo($trend['published_at']) : 'Just now' ?></span>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($mobileExplore)): ?>
    <div class="mobile-home-sectionhead">
      Explore
      <a href="/explore" class="mobile-home-sectionlink">View More</a>
    </div>
    <div class="mobile-home-trending">
      <?php foreach ($mobileExplore as $exploreIndex => $exploreItem): ?>
      <?php
        $exploreCategorySlug = $exploreItem['category_slug'] ?? 'news';
        $exploreCategoryName = $exploreItem['category_name'] ?? 'Explore';
        $exploreCategoryColor = $exploreItem['category_color'] ?? '#2979FF';
        $exploreTitle = $exploreItem['title'] ?? 'Untitled story';
        $exploreSlug = $exploreItem['slug'] ?? '';
        $exploreThumb = $exploreItem['thumbnail'] ?? '';
        $exploreUrl = '/' . $exploreCategorySlug . '/' . $exploreSlug;
      ?>
      <article class="mobile-home-trendingcard" style="--mobile-card-color:<?= Helper::sanitize($exploreCategoryColor) ?>">
        <a href="<?= $exploreUrl ?>" class="mobile-home-trendingmedia" style="background:<?= Helper::sanitize($exploreCategoryColor) ?>22">
          <?php if ($exploreThumb !== ''): ?>
          <img src="<?= Helper::thumbnailUrl($exploreThumb) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($exploreItem['image_alt'] ?? '', $exploreTitle)) ?>" width="640" height="480" loading="lazy" decoding="async">
          <?php else: ?>
          <i class="fa <?= Helper::sanitize($categoryIconMap[$exploreCategorySlug] ?? 'fa-compass') ?>"></i>
          <?php endif; ?>
        </a>
        <div class="mobile-home-trendingbody">
          <div class="mobile-home-trendingtopline">
            <span class="mobile-home-trendingrank">#<?= str_pad((string)($exploreIndex + 1), 2, '0', STR_PAD_LEFT) ?></span>
            <span class="mobile-home-trendingtag" style="color:<?= Helper::sanitize($exploreCategoryColor) ?>"><?= Helper::sanitize($exploreCategoryName) ?></span>
          </div>
          <h3><a href="<?= $exploreUrl ?>"><?= Helper::sanitize($exploreTitle) ?></a></h3>
          <div class="mobile-home-trendingmeta">
            <span><?= Helper::sanitize($exploreItem['full_name'] ?? 'Desk') ?></span>
            <span><?= !empty($exploreItem['published_at']) ? Helper::timeAgo($exploreItem['published_at']) : 'Just now' ?></span>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($categorySpotlights)): ?>
    <div class="mobile-home-sectionhead">
      Category Top 5
      <span class="mobile-home-sectionhint">Swipe</span>
    </div>
    <div class="mobile-home-categoryrail" aria-label="Category top 5 stories">
      <?php foreach ($categorySpotlights as $spotlight): ?>
      <?php
        $spotlightCategory = $spotlight['category'];
        $spotlightPosts = $spotlight['posts'];
        $spotlightColor = $spotlightCategory['color'] ?? '#FF6B1A';
        $spotlightIcon = $spotlightCategory['icon'] ?? 'fa-newspaper';
        $spotlightCategorySlug = $spotlightCategory['slug'] ?? '';
      ?>
      <article class="mobile-home-categorycard" style="--mobile-category-color:<?= Helper::sanitize($spotlightColor) ?>">
        <div class="mobile-home-categorycardhead">
          <div>
            <span class="mobile-home-categorycardkicker"><i class="fa <?= Helper::sanitize($spotlightIcon) ?>"></i> Category</span>
            <h3>
              <a href="/category/<?= Helper::sanitize($spotlightCategorySlug) ?>"><?= Helper::sanitize($spotlightCategory['name'] ?? 'Category') ?></a>
            </h3>
          </div>
          <a href="/category/<?= Helper::sanitize($spotlightCategorySlug) ?>" class="mobile-home-categorycardlink">View All</a>
        </div>
        <div class="mobile-home-categorycardlist">
          <?php if (!empty($spotlightPosts)): ?>
            <?php foreach ($spotlightPosts as $index => $spotlightPost): ?>
            <a href="/<?= Helper::sanitize($spotlightPost['category_slug'] ?? ($spotlightCategorySlug ?: 'news')) ?>/<?= Helper::sanitize($spotlightPost['slug'] ?? '') ?>" class="mobile-home-categoryitem">
              <span class="mobile-home-categoryrank"><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
              <span class="mobile-home-categorytitle"><?= Helper::sanitize($spotlightPost['title'] ?? 'Untitled story') ?></span>
            </a>
            <?php endforeach; ?>
          <?php else: ?>
          <div class="mobile-home-categoryitem mobile-home-categoryitem-empty">
            <span class="mobile-home-categoryrank">--</span>
            <span class="mobile-home-categorytitle">Stories will appear here soon.</span>
          </div>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <section class="mobile-home-newsletter" id="mobileNewsletter">
      <div class="mobile-home-newsletterhead">
        <span class="mobile-home-newsletterbadge"><i class="fa fa-envelope"></i> Newsletter</span>
        <h2>Get breaking updates in your inbox</h2>
        <p>Daily headlines aur fast alerts ke liye subscribe karo.</p>
      </div>
      <?php if ($newsletterStatus === 'success'): ?>
      <div class="mobile-home-newslettermsg mobile-home-newslettermsg--success">Subscribed successfully.</div>
      <?php elseif ($newsletterStatus === 'exists'): ?>
      <div class="mobile-home-newslettermsg mobile-home-newslettermsg--success">This email is already subscribed.</div>
      <?php elseif ($newsletterStatus === 'invalid'): ?>
      <div class="mobile-home-newslettermsg mobile-home-newslettermsg--error">Valid email enter karo.</div>
      <?php elseif ($newsletterStatus === 'csrf'): ?>
      <div class="mobile-home-newslettermsg mobile-home-newslettermsg--error">Session expired. Please try again.</div>
      <?php endif; ?>
      <form action="<?= Helper::siteUrl('api/newsletter') ?>" method="POST" class="mobile-home-newsletterform">
        <?= Csrf::field() ?>
        <input type="hidden" name="newsletter_context" value="mobile">
        <input type="email" name="email" class="mobile-home-newsletterinput" placeholder="your@email.com" required>
        <button type="submit" class="mobile-home-newsletterbtn">Subscribe</button>
      </form>
    </section>

    <?php if (!empty($categories)): ?>
    <div class="mobile-home-sectionhead">
      Topics
      <span class="mobile-home-sectionhint">Auto</span>
    </div>
    <div class="mobile-home-topicsrail" aria-label="Topics">
      <?php foreach ($categories as $cat): ?>
      <a
        href="/category/<?= Helper::sanitize($cat['slug'] ?? '') ?>"
        class="mobile-home-topicchip"
        style="--mobile-topic-color:<?= Helper::sanitize($cat['color'] ?? '#111') ?>"
      >
        <?= Helper::sanitize($cat['name'] ?? 'Topic') ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($mobileSuggestedUsers)): ?>
    <div class="mobile-home-sectionhead">
      Who to Follow
      <span class="mobile-home-sectionhint">Auto</span>
    </div>
    <div class="mobile-home-followrail" aria-label="Who to Follow">
      <?php foreach ($mobileSuggestedUsers as $su): ?>
      <article class="mobile-home-followcard">
        <a href="/@<?= Helper::sanitize($su['username'] ?? '') ?>" class="mobile-home-followavatarlink">
          <img
            src="<?= Helper::avatarUrl($su['avatar'] ?? null) ?>"
            alt="<?= Helper::sanitize($su['full_name'] ?? 'FatakNews User') ?>"
            class="mobile-home-followavatar"
            width="64"
            height="64"
            loading="lazy"
            decoding="async"
          >
        </a>
        <div class="mobile-home-followbody">
          <strong>
            <a href="/@<?= Helper::sanitize($su['username'] ?? '') ?>"><?= Helper::sanitize($su['full_name'] ?? 'FatakNews User') ?></a>
            <?php if (!empty($su['is_verified'])): ?><i class="fa fa-check-circle verified-icon"></i><?php endif; ?>
          </strong>
          <span>@<?= Helper::sanitize($su['username'] ?? '') ?></span>
          <span><?= Helper::formatNumber((int)($su['followers_count'] ?? 0)) ?> followers</span>
        </div>
        <?php if (Auth::check() && Auth::id() !== (int)($su['id'] ?? 0)): ?>
        <button class="btn-follow" data-follow="<?= (int)($su['id'] ?? 0) ?>">Follow</button>
        <?php endif; ?>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <nav class="mobile-home-bottomnav" aria-label="Mobile Home Navigation">
      <a href="/" class="active"><i class="fa fa-home"></i><span>Home</span></a>
      <a href="/trending"><i class="fa fa-fire"></i><span>Trending</span></a>
      <?php if ($canCreateStories): ?>
      <button type="button" class="mobile-home-compose" id="mobileStoryComposeFab"><i class="fa fa-plus"></i><span>Story</span></button>
      <?php else: ?>
      <a href="<?= Auth::check() ? '/community/create' : '/login' ?>" class="mobile-home-compose"><i class="fa <?= Auth::check() ? 'fa-pen-to-square' : 'fa-right-to-bracket' ?>"></i><span><?= Auth::check() ? 'Write' : 'Login' ?></span></a>
      <?php endif; ?>
      <a href="/community"><i class="fa fa-users"></i><span>Community</span></a>
      <a href="/explore"><i class="fa fa-compass"></i><span>Explore</span></a>
    </nav>

    <?php if ($canCreateStories): ?>
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
            <textarea name="caption" maxlength="280" placeholder="What's happening right now?"></textarea>
          </label>
          <label>
            <span>Background color</span>
            <div class="story-color-grid">
              <?php foreach ($mobileStoryPalette as $index => $storyColor): ?>
              <label class="story-color-option">
                <input type="radio" name="background_color" value="<?= $storyColor ?>" <?= $index === 0 ? 'checked' : '' ?>>
                <span style="--story-bg:<?= $storyColor ?>"></span>
              </label>
              <?php endforeach; ?>
            </div>
          </label>
          <label class="story-upload-field">
            <span>Optional image</span>
            <input type="file" name="media" accept="image/*">
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
            <img src="<?= Helper::avatarUrl($authHomeUser['avatar'] ?? null) ?>" alt="Story author" id="storyViewerAvatar" width="42" height="42" decoding="async">
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
  </div>
</section>
<?php else: ?>
<div class="home-grid home-desktop-only">
  <main class="feed-col">
    <h1 class="sr-only">FatakNews latest breaking news, trending stories, and category updates</h1>
    <?php if (!empty($breaking)): ?>
    <section class="hero-section" aria-label="Breaking News">
      <?php foreach ($breaking as $i => $b): ?>
      <?php
        $heroThumb = $b['thumbnail'] ?? '';
        $heroCategorySlug = $b['category_slug'] ?? 'news';
        $heroTitle = $b['title'] ?? 'Untitled story';
        $heroSlug = $b['slug'] ?? '';
        $heroAuthor = $b['full_name'] ?? 'FatakNews Desk';
      ?>
      <div class="hero-slide <?= $i === 0 ? 'active' : '' ?>">
        <?php if ($heroThumb !== ''): ?>
        <img src="<?= Helper::thumbnailUrl($heroThumb) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($b['image_alt'] ?? '', $heroTitle)) ?>" width="1200" height="675" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>"<?= $i === 0 ? ' fetchpriority="high"' : '' ?> decoding="async">
        <?php else: ?>
        <div style="width:100%;height:100%;background:linear-gradient(135deg,var(--bg3),var(--card2))"></div>
        <?php endif; ?>
        <div class="hero-overlay"></div>
        <div class="hero-content">
          <span class="hero-badge"><i class="fa fa-bolt"></i> BREAKING</span>
          <h2 class="hero-title">
            <a href="/<?= $heroCategorySlug ?>/<?= $heroSlug ?>"><?= Helper::sanitize($heroTitle) ?></a>
          </h2>
          <div class="hero-meta">
            <span><i class="fa fa-user"></i> <?= Helper::sanitize($heroAuthor) ?></span>
            <span><i class="fa fa-clock"></i> <?= !empty($b['published_at']) ? Helper::timeAgo($b['published_at']) : 'Just now' ?></span>
            <span><i class="fa fa-eye"></i> <?= Helper::formatNumber((int)($b['views_count'] ?? 0)) ?></span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <div class="hero-dots">
        <?php foreach ($breaking as $i => $b): ?>
        <div class="hero-dot <?= $i === 0 ? 'active' : '' ?>"></div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($featured)): ?>
    <section>
      <div class="widget-title"><i class="fa fa-star"></i> Featured</div>
      <div class="featured-grid">
        <?php foreach (array_slice($featured, 0, 3) as $f): ?>
        <?php
          $featuredCategorySlug = $f['category_slug'] ?? 'news';
          $featuredCategoryName = $f['category_name'] ?? 'General';
          $featuredCategoryColor = $f['category_color'] ?? 'var(--red)';
          $featuredTitle = $f['title'] ?? 'Untitled story';
          $featuredSlug = $f['slug'] ?? '';
        ?>
        <article class="featured-card">
          <a href="/<?= $featuredCategorySlug ?>/<?= $featuredSlug ?>">
            <img src="<?= Helper::thumbnailUrl($f['thumbnail'] ?? '') ?>" alt="<?= Helper::sanitize(Helper::imageAlt($f['image_alt'] ?? '', $featuredTitle)) ?>" class="featured-card-img" width="480" height="270" loading="lazy" decoding="async">
          </a>
          <div class="featured-card-body">
            <a href="/category/<?= $featuredCategorySlug ?>" class="featured-card-cat" style="color:<?= $featuredCategoryColor ?>"><?= Helper::sanitize($featuredCategoryName) ?></a>
            <h3 class="featured-card-title">
              <a href="/<?= $featuredCategorySlug ?>/<?= $featuredSlug ?>"><?= Helper::sanitize($featuredTitle) ?></a>
            </h3>
            <div class="featured-card-meta">
              <span><?= Helper::sanitize($f['full_name'] ?? 'FatakNews Desk') ?></span>
              <span>&middot;</span>
              <span><?= !empty($f['published_at']) ? Helper::timeAgo($f['published_at']) : 'Just now' ?></span>
              <span>&middot;</span>
              <span><?= (int)($f['reading_time'] ?? 1) ?> min read</span>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <div class="category-tabs">
      <div class="cat-tab active" data-cat="">All</div>
      <?php foreach ($categories as $cat): ?>
      <div class="cat-tab" data-cat="<?= $cat['id'] ?>" style="--cat-color:<?= $cat['color'] ?>">
        <i class="fa <?= $cat['icon'] ?>" style="color:<?= $cat['color'] ?>"></i>
        <?= Helper::sanitize($cat['name']) ?>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="home-latest-grid">
      <div class="home-latest-main">
        <div class="widget-title"><i class="fa fa-newspaper"></i> Latest Articles</div>
        <div class="news-feed" id="newsFeed">
          <?php foreach ($feed['data'] as $post): ?>
          <?php
            $postThumb = $post['thumbnail'] ?? '';
            $postCategorySlug = $post['category_slug'] ?? 'news';
            $postCategoryName = $post['category_name'] ?? '';
            $postCategoryColor = $post['category_color'] ?? 'var(--red)';
            $postTitle = $post['title'] ?? 'Untitled story';
            $postSlug = $post['slug'] ?? '';
            $postShareUrl = Helper::siteUrl($postCategorySlug . '/' . $postSlug);
          ?>
          <article class="news-card" style="--card-cat-color:<?= Helper::sanitize($postCategoryColor) ?>">
            <?php if ($postThumb !== ''): ?>
            <a href="/<?= $postCategorySlug ?>/<?= $postSlug ?>">
              <img src="<?= Helper::thumbnailUrl($postThumb) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($post['image_alt'] ?? '', $postTitle)) ?>" class="news-card-img" width="160" height="110" loading="lazy" decoding="async">
            </a>
            <?php endif; ?>
            <div class="news-card-body">
              <?php if ($postCategoryName !== ''): ?>
              <a href="/category/<?= $postCategorySlug ?>" class="news-card-cat" style="color:<?= $postCategoryColor ?>"><?= Helper::sanitize($postCategoryName) ?></a>
              <?php endif; ?>
              <h3 class="news-card-title">
                <a href="/<?= $postCategorySlug ?>/<?= $postSlug ?>"><?= Helper::sanitize($postTitle) ?></a>
              </h3>
              <div class="news-card-meta">
                <img src="<?= Helper::avatarUrl($post['avatar'] ?? null) ?>" alt="<?= Helper::sanitize($post['full_name'] ?? 'FatakNews Desk') ?>" width="20" height="20" decoding="async">
                <span><?= Helper::sanitize($post['full_name'] ?? 'FatakNews Desk') ?></span>
                <span>&middot;</span>
                <span><?= !empty($post['published_at']) ? Helper::timeAgo($post['published_at']) : 'Just now' ?></span>
                <span>&middot;</span>
                <span><?= (int)($post['reading_time'] ?? 1) ?> min read</span>
              </div>
              <div class="news-card-actions">
                <button type="button" class="action-btn <?= Auth::check() ? 'liked' : '' ?>" data-react="<?= (int)$post['id'] ?>" data-type="like">
                  <i class="fa fa-heart"></i>
                  <span class="react-count"><?= Helper::formatNumber((int)($post['likes_count'] ?? 0)) ?></span>
                </button>
                <a href="/<?= $postCategorySlug ?>/<?= $postSlug ?>#comments" class="action-btn">
                  <i class="fa fa-comment"></i>
                  <span><?= Helper::formatNumber((int)($post['comments_count'] ?? 0)) ?></span>
                </a>
                <button type="button" class="action-btn" data-bookmark="<?= (int)$post['id'] ?>">
                  <i class="fa fa-bookmark"></i>
                </button>
                <button
                  type="button"
                  class="action-btn"
                  data-share-title="<?= Helper::sanitize($postTitle) ?>"
                  data-share-url="<?= Helper::sanitize($postShareUrl) ?>"
                  aria-label="Share story"
                >
                  <i class="fa fa-share-alt"></i>
                </button>
                <span class="action-btn" style="margin-left:auto;cursor:default">
                  <i class="fa fa-eye"></i> <?= Helper::formatNumber((int)($post['views_count'] ?? 0)) ?>
                </span>
              </div>
            </div>
          </article>
          <?php endforeach; ?>

          <?php if (empty($feed['data'])): ?>
          <div class="empty-state">
            <i class="fa fa-newspaper"></i>
            <h3>No news yet</h3>
            <p>Check back soon for the latest updates!</p>
          </div>
          <?php endif; ?>
        </div>

        <?php if (($feed['pages'] ?? 0) > 1): ?>
        <div class="pagination">
          <?php if (($feed['page'] ?? 1) > 1): ?>
          <a href="?page=<?= $feed['page'] - 1 ?>" class="page-btn"><i class="fa fa-chevron-left"></i></a>
          <?php endif; ?>
          <?php for ($i = max(1, ($feed['page'] ?? 1) - 2); $i <= min(($feed['pages'] ?? 1), ($feed['page'] ?? 1) + 2); $i++): ?>
          <a href="?page=<?= $i ?>" class="page-btn <?= $i === ($feed['page'] ?? 1) ? 'active' : '' ?>"><?= $i ?></a>
          <?php endfor; ?>
          <?php if (($feed['page'] ?? 1) < ($feed['pages'] ?? 1)): ?>
          <a href="?page=<?= $feed['page'] + 1 ?>" class="page-btn"><i class="fa fa-chevron-right"></i></a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <aside class="home-latest-sidebar">
        <div class="sidebar-widget home-sidebar-feed-widget">
          <div class="widget-title"><i class="fa fa-fire" style="color:var(--red)"></i> Trending Now</div>
          <?php foreach ($trending as $i => $t): ?>
          <?php
            $trendCategorySlug = $t['category_slug'] ?? 'news';
            $trendCategoryColor = $t['category_color'] ?? 'var(--red)';
          ?>
          <div class="trending-item">
            <span class="trending-num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
            <div>
              <div class="trending-title">
                <a href="/<?= $trendCategorySlug ?>/<?= $t['slug'] ?? '' ?>"><?= Helper::sanitize($t['title'] ?? 'Untitled story') ?></a>
              </div>
              <div class="trending-meta">
                <span style="color:<?= $trendCategoryColor ?>"><?= Helper::sanitize($t['category_name'] ?? 'General') ?></span>
                &middot; <?= Helper::formatNumber((int)($t['views_count'] ?? 0)) ?> views
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="sidebar-widget home-sidebar-feed-widget">
          <div class="widget-title"><i class="fa fa-compass" style="color:#5A6475"></i> Explore Latest</div>
          <?php if (!empty($exploreLatest)): ?>
            <?php foreach ($exploreLatest as $i => $item): ?>
            <?php
              $exploreCategorySlug = $item['category_slug'] ?? 'news';
              $exploreCategoryColor = $item['category_color'] ?? '#5A6475';
            ?>
            <div class="trending-item">
              <span class="trending-num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
              <div>
                <div class="trending-title">
                  <a href="/<?= $exploreCategorySlug ?>/<?= $item['slug'] ?? '' ?>"><?= Helper::sanitize($item['title'] ?? 'Untitled post') ?></a>
                </div>
                <div class="trending-meta">
                  <span style="color:<?= $exploreCategoryColor ?>"><?= Helper::sanitize($item['category_name'] ?? 'Explore') ?></span>
                  &middot; <?= Helper::timeAgo($item['published_at'] ?? $item['created_at'] ?? null) ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
          <p style="font-size:13px;color:var(--muted);margin:0">No Explore posts yet.</p>
          <?php endif; ?>
        </div>

        <div class="sidebar-widget home-sidebar-feed-widget">
          <div class="widget-title"><i class="fa fa-users" style="color:#6F5DA8"></i> Community Latest</div>
          <?php if (!empty($communityLatest)): ?>
            <?php foreach ($communityLatest as $i => $item): ?>
            <?php
              $communityCategorySlug = $item['category_slug'] ?? 'community';
              $communityCategoryColor = $item['category_color'] ?? '#6F5DA8';
            ?>
            <div class="trending-item">
              <span class="trending-num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
              <div>
                <div class="trending-title">
                  <a href="/<?= $communityCategorySlug ?>/<?= $item['slug'] ?? '' ?>"><?= Helper::sanitize($item['title'] ?? 'Untitled post') ?></a>
                </div>
                <div class="trending-meta">
                  <span style="color:<?= $communityCategoryColor ?>"><?= Helper::sanitize($item['full_name'] ?? 'Community') ?></span>
                  &middot; <?= Helper::timeAgo($item['published_at'] ?? $item['created_at'] ?? null) ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
          <p style="font-size:13px;color:var(--muted);margin:0">No Community posts yet.</p>
          <?php endif; ?>
        </div>

        <div class="sidebar-widget" id="homeNewsletter" style="background:linear-gradient(135deg,rgba(255,45,45,0.15),rgba(255,107,26,0.1));border-color:rgba(255,45,45,0.2)">
          <div class="widget-title" style="color:var(--red)"><i class="fa fa-envelope"></i> Newsletter</div>
          <p style="font-size:13px;color:var(--muted);margin-bottom:14px">Get breaking news delivered to your inbox daily.</p>
          <?php if ($newsletterStatus === 'success'): ?>
          <div class="newsletter-inline-msg newsletter-inline-msg--success">Subscribed successfully.</div>
          <?php elseif ($newsletterStatus === 'exists'): ?>
          <div class="newsletter-inline-msg newsletter-inline-msg--success">This email is already subscribed.</div>
          <?php elseif ($newsletterStatus === 'invalid'): ?>
          <div class="newsletter-inline-msg newsletter-inline-msg--error">Valid email enter karo.</div>
          <?php elseif ($newsletterStatus === 'csrf'): ?>
          <div class="newsletter-inline-msg newsletter-inline-msg--error">Session expired. Please try again.</div>
          <?php endif; ?>
          <form action="<?= Helper::siteUrl('api/newsletter') ?>" method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="newsletter_context" value="desktop">
            <input type="email" name="email" class="form-control" placeholder="your@email.com" style="margin-bottom:10px" required>
            <button type="submit" class="btn-block" style="padding:10px">Subscribe</button>
          </form>
        </div>
      </aside>
    </div>

    <section class="home-topics-section">
      <div class="sidebar-widget home-topics-widget">
        <div class="widget-title"><i class="fa fa-hashtag"></i> Topics</div>
        <div class="home-topics-row">
          <?php foreach ($categories as $cat): ?>
          <a href="/category/<?= Helper::sanitize($cat['slug'] ?? '') ?>" class="tag-chip" style="--topic-color:<?= Helper::sanitize($cat['color'] ?? '#111') ?>;border-left:3px solid <?= Helper::sanitize($cat['color'] ?? '#111') ?>">
            <?= Helper::sanitize($cat['name']) ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <?php if (!empty($categorySpotlights)): ?>
    <section class="home-category-rail-section">
      <div class="widget-title"><i class="fa fa-layer-group"></i> Category Top 5</div>
      <div class="home-category-rail-wrap">
        <button type="button" class="home-category-rail-btn home-category-rail-btn-prev" data-rail-prev="categoryRail" aria-label="Scroll categories left">
          <i class="fa fa-chevron-left"></i>
        </button>
        <div class="home-category-rail" id="categoryRail">
          <?php foreach ($categorySpotlights as $spotlight): ?>
          <?php
            $spotlightCategory = $spotlight['category'];
            $spotlightPosts = $spotlight['posts'];
            $spotlightColor = $spotlightCategory['color'] ?? '#FF6B1A';
            $spotlightIcon = $spotlightCategory['icon'] ?? 'fa-newspaper';
          ?>
          <article class="home-category-card" style="--category-card-accent:<?= Helper::sanitize($spotlightColor) ?>">
            <div class="home-category-card-head">
              <div>
                <span class="home-category-card-kicker"><i class="fa <?= Helper::sanitize($spotlightIcon) ?>"></i> Category</span>
                <h3>
                  <a href="/category/<?= Helper::sanitize($spotlightCategory['slug'] ?? '') ?>"><?= Helper::sanitize($spotlightCategory['name'] ?? 'Category') ?></a>
                </h3>
              </div>
              <a href="/category/<?= Helper::sanitize($spotlightCategory['slug'] ?? '') ?>" class="home-category-card-link">View All</a>
            </div>
            <div class="home-category-card-list">
              <?php if (!empty($spotlightPosts)): ?>
                <?php foreach ($spotlightPosts as $index => $spotlightPost): ?>
                <a href="/<?= Helper::sanitize($spotlightPost['category_slug'] ?? ($spotlightCategory['slug'] ?? 'news')) ?>/<?= Helper::sanitize($spotlightPost['slug'] ?? '') ?>" class="home-category-card-item">
                  <span class="home-category-card-rank"><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                  <span class="home-category-card-title"><?= Helper::sanitize($spotlightPost['title'] ?? 'Untitled story') ?></span>
                </a>
                <?php endforeach; ?>
              <?php else: ?>
              <div class="home-category-card-item home-category-card-item-empty">
                <span class="home-category-card-rank">--</span>
                <span class="home-category-card-title">Stories will appear here soon.</span>
              </div>
              <?php endif; ?>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
        <button type="button" class="home-category-rail-btn home-category-rail-btn-next" data-rail-next="categoryRail" aria-label="Scroll categories right">
          <i class="fa fa-chevron-right"></i>
        </button>
      </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($suggestedUsers)): ?>
    <section class="home-follow-section">
      <div class="sidebar-widget home-follow-widget">
        <div class="widget-title"><i class="fa fa-users"></i> Who to Follow</div>
        <div class="home-follow-row">
        <?php foreach ($suggestedUsers as $su): ?>
        <div class="suggest-item">
          <a href="/@<?= Helper::sanitize($su['username'] ?? '') ?>">
            <img src="<?= Helper::avatarUrl($su['avatar'] ?? null) ?>" alt="<?= Helper::sanitize($su['full_name'] ?? 'FatakNews User') ?>" class="suggest-avatar" width="40" height="40" loading="lazy" decoding="async">
          </a>
          <div class="suggest-info">
            <strong>
              <a href="/@<?= Helper::sanitize($su['username'] ?? '') ?>"><?= Helper::sanitize($su['full_name'] ?? 'FatakNews User') ?></a>
              <?php if (!empty($su['is_verified'])): ?><i class="fa fa-check-circle verified-icon"></i><?php endif; ?>
            </strong>
            <span>@<?= Helper::sanitize($su['username'] ?? '') ?> &middot; <?= Helper::formatNumber((int)($su['followers_count'] ?? 0)) ?> followers</span>
          </div>
          <?php if (Auth::check() && Auth::id() !== (int)($su['id'] ?? 0)): ?>
          <button class="btn-follow" data-follow="<?= (int)($su['id'] ?? 0) ?>">Follow</button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>
  </main>
</div>
<?php endif; ?>
<?php include VIEW . 'layouts/footer.php'; ?>
