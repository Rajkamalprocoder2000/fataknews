<?php
$pageTitle = $pageTitle ?? 'FatakNews';
$pageDesc = $pageDesc ?? 'FatakNews information page.';
$canonicalUrl = $canonicalUrl ?? Helper::siteUrl(ltrim(Helper::requestPath(), '/'));
$sections = $sections ?? [];
$icon = $icon ?? 'fa-circle-info';
$bodyClass = $bodyClass ?? 'static-info-page';
$breadcrumbItems = [
    ['name' => 'Home', 'url' => Helper::siteUrl()],
    ['name' => $pageTitle, 'url' => $canonicalUrl],
];
$extraHead = trim((string)($extraHead ?? '') . <<<HTML

<style>
.static-info-page{
  background:
    radial-gradient(circle at top right, rgba(255,107,26,.12), transparent 28%),
    radial-gradient(circle at top left, rgba(255,45,45,.10), transparent 30%),
    linear-gradient(180deg,#fff8f7 0%,#fffdfd 22%,#ffffff 100%);
}
.static-info-page .ticker-wrap{
  background:#fff6ef;
  border-bottom:1px solid rgba(201,47,18,.12);
}
.static-info-page .ticker-label{
  background:#c92f12;
  color:#fff7f2;
}
.static-info-page .ticker-track span{
  color:#2c1b1f;
}
.static-info-page .ticker-track span::before{
  color:#ff8a2a;
}
.static-info-page .ticker-track a{
  color:#2c1b1f;
}
.static-info-page .navbar{
  background:#fff6ef;
  border-bottom:1px solid rgba(201,47,18,.12);
  backdrop-filter:none;
}
.static-info-page .logo-text{
  color:#2c1b1f;
}
.static-info-page .nav-brand-slogan{
  color:#8a5d56;
}
.static-info-page .nav-search input{
  background:#fffaf6;
  border-color:#efd8cf;
  color:#2b2126;
}
.static-info-page .nav-search i{
  color:#a17770;
}
.static-info-page .nav-search input:focus{
  background:#ffffff;
  border-color:#ff6b1a;
}
.static-info-page .nav-link{
  color:#7c5b5f;
}
.static-info-page .nav-link:hover,
.static-info-page .nav-link.active{
  color:#2c1b1f;
  background:#ffe9df;
}
.static-info-page .btn-ghost{
  border-color:#efd8cf;
  color:#7c5b5f;
}
.static-info-page .btn-ghost:hover{
  border-color:#ff6b1a;
  color:#c92f12;
}
.static-info-page .nav-notif,
.static-info-page .nav-mobile-search{
  background:#ffece4;
  color:#a45c4b;
  box-shadow:inset 0 0 0 1px rgba(201,47,18,.10);
}
.static-info-page .nav-notif:hover,
.static-info-page .nav-mobile-search:hover{
  color:#2c1b1f;
  background:#ffe1d4;
}
.static-info-page .nav-avatar{
  border-color:#efd8cf;
}
.static-info-page .user-menu,
.static-info-page .notif-panel,
.static-info-page .search-dropdown,
.static-info-page .mega-dropdown,
.static-info-page .mobile-nav{
  background:#fff9f5;
  border-color:#efd8cf;
}
.static-info-page .user-menu-header,
.static-info-page .notif-item:hover,
.static-info-page .user-menu-links a:hover,
.static-info-page .search-result-item:hover,
.static-info-page .mobile-nav-link:hover{
  background:#fff0e8;
}
.static-info-page .notif-header,
.static-info-page .notif-item,
.static-info-page .user-menu-links hr,
.static-info-page .search-result-item,
.static-info-page .mobile-nav-adminlinks .mobile-nav-link{
  border-color:#f3e1d9;
}
.static-info-page .notif-item p,
.static-info-page .user-menu-header span,
.static-info-page .user-menu-links a,
.static-info-page .search-result-item,
.static-info-page .mega-item,
.static-info-page .mobile-nav-link{
  color:#7c5b5f;
}
.static-info-page .notif-item p strong,
.static-info-page .user-menu-header strong{
  color:#2c1b1f;
}
.static-info-page .mobile-nav-link i,
.static-info-page .mobile-nav-adminlinks .mobile-nav-link i{
  color:#c92f12;
}
.static-info-shell{
  max-width:1180px;
  margin:0 auto;
  padding:40px 20px 28px;
}
.static-info-hero{
  background:linear-gradient(135deg,#fff0ea 0%,#fff5ec 48%,#fff9f1 100%);
  border:1px solid #efc7b8;
  border-radius:28px;
  padding:34px 32px;
  overflow:hidden;
  position:relative;
  margin-bottom:22px;
  box-shadow:0 24px 58px rgba(80,35,20,.12),0 4px 14px rgba(80,35,20,.06);
}
.static-info-hero::after{
  content:"";
  position:absolute;
  right:-40px;
  top:-46px;
  width:220px;
  height:220px;
  border-radius:50%;
  background:rgba(255,255,255,.28);
}
.static-info-hero-inner{
  position:relative;
  max-width:760px;
}
.static-info-eyebrow{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:7px 14px;
  border-radius:999px;
  background:rgba(255,45,45,.08);
  color:#a12818;
  font-size:12px;
  font-weight:800;
  letter-spacing:.08em;
  text-transform:uppercase;
  margin-bottom:16px;
}
.static-info-icon{
  width:58px;
  height:58px;
  border-radius:16px;
  background:linear-gradient(135deg,#ffede4,#ffd5c0);
  display:flex;
  align-items:center;
  justify-content:center;
  color:#c92f12;
  font-size:22px;
  margin-bottom:18px;
  box-shadow:0 12px 28px rgba(201,47,18,.16);
}
.static-info-title{
  font-family:'Baloo 2',cursive;
  font-size:clamp(32px,4vw,44px);
  line-height:1.08;
  margin:0 0 14px;
  color:#1f1522;
}
.static-info-lead{
  font-size:17px;
  line-height:1.85;
  color:#4d3b43;
  max-width:680px;
  margin:0;
}
.static-info-grid{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
  gap:18px;
}
.static-info-card{
  margin-bottom:0;
  background:linear-gradient(180deg,#ffffff 0%,#fff9f6 100%);
  border:1px solid #efd8cf;
  border-radius:24px;
  padding:24px 22px;
  box-shadow:0 18px 40px rgba(80,35,20,.10),0 4px 12px rgba(80,35,20,.05);
}
.static-info-card h2{
  font-size:21px;
  font-weight:800;
  line-height:1.35;
  color:#23181f;
  margin:0 0 12px;
}
.static-info-card p{
  font-size:15px;
  line-height:1.9;
  color:#463942;
  margin:0;
}
.static-info-card a{
  color:#c92f12;
  font-weight:700;
  text-decoration:none;
}
.static-info-card a:hover,
.static-info-card a:focus{
  color:#9f220b;
  text-decoration:underline;
}
.static-info-page .breadcrumb,
.static-info-page .breadcrumb a{
  color:#7b4b3d;
}
@media (min-width: 992px){
  .static-info-shell{
    padding-top:46px;
  }
  .static-info-hero{
    padding:42px 40px;
    border-radius:32px;
  }
  .static-info-grid{
    grid-template-columns:repeat(3,minmax(0,1fr));
  }
}
</style>
HTML);
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $pageTitle,
        'description' => $pageDesc,
        'url' => $canonicalUrl,
    ],
    Helper::breadcrumbSchema($breadcrumbItems),
];
include VIEW . 'layouts/header.php';
?>
<section class="static-info-shell">
  <div class="static-info-hero">
    <div class="static-info-hero-inner">
      <?= Helper::breadcrumbNav($breadcrumbItems) ?>
      <div class="static-info-eyebrow">FatakNews Information</div>
      <div class="static-info-icon">
        <i class="fa <?= Helper::sanitize($icon) ?>"></i>
      </div>
      <h1 class="static-info-title"><?= Helper::sanitize($pageTitle) ?></h1>
      <p class="static-info-lead"><?= Helper::sanitize($pageDesc) ?></p>
    </div>
  </div>

  <div class="static-info-grid">
    <?php foreach ($sections as $section): ?>
    <article class="sidebar-widget static-info-card">
      <h2><?= Helper::sanitize($section['title'] ?? '') ?></h2>
      <p><?= Helper::sanitize($section['body'] ?? '') ?></p>
    </article>
    <?php endforeach; ?>
  </div>
</section>
<?php include VIEW . 'layouts/footer.php'; ?>
