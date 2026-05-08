<?php $mobileNavPath = rtrim(Helper::requestPath(), '/') ?: '/'; ?>
<nav class="mobile-section-bottomnav" aria-label="Section Navigation">
  <a href="/" class="<?= $mobileNavPath === '/' ? 'active' : '' ?>">
    <i class="fa fa-home"></i><span>Home</span>
  </a>
  <a href="/trending" class="<?= $mobileNavPath === '/trending' ? 'active' : '' ?>">
    <i class="fa fa-fire"></i><span>Trending</span>
  </a>
  <?php if (Auth::check()): ?>
    <?php if ($mobileNavPath === '/community'): ?>
  <button type="button" class="mobile-section-compose" id="mobileStoryComposeFab" aria-label="Create Story">
    <i class="fa fa-plus"></i><span>Story</span>
  </button>
    <?php else: ?>
  <a href="/community" class="mobile-section-compose" aria-label="Story">
    <i class="fa fa-plus"></i><span>Story</span>
  </a>
    <?php endif; ?>
  <?php else: ?>
  <a href="/login" class="mobile-section-compose" aria-label="Story Login">
    <i class="fa fa-plus"></i><span>Story</span>
  </a>
  <?php endif; ?>
  <a href="/community" class="<?= $mobileNavPath === '/community' ? 'active' : '' ?>">
    <i class="fa fa-users"></i><span>Community</span>
  </a>
  <a href="/explore" class="<?= $mobileNavPath === '/explore' ? 'active' : '' ?>">
    <i class="fa fa-compass"></i><span>Explore</span>
  </a>
</nav>
