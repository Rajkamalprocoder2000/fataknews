<?php
$pageTitle = 'About, Contact, Topics & Support | FatakNews';
$pageDesc = 'Browse FatakNews topics, company pages, legal information, support links, and sitemap resources in one place.';
$canonicalUrl = Helper::siteUrl('more');
$metaRobots = 'noindex,follow';
$bodyClass = 'footer-links-page';
$breadcrumbItems = [
    ['name' => 'Home', 'url' => Helper::siteUrl()],
    ['name' => 'More', 'url' => $canonicalUrl],
];
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
$cats = (new CategoryModel())->getTopLevel();
include VIEW . 'layouts/header.php';
?>
<section class="footer-links-shell">
  <div class="footer-links-hero">
    <?= Helper::breadcrumbNav($breadcrumbItems) ?>
    <div class="widget-title"><i class="fa fa-grid-2"></i> More</div>
    <h1>FatakNews links, company info, and quick access</h1>
    <p>Footer me jo links the, unko yahan ek jagah group kar diya gaya hai for faster mobile access.</p>
  </div>

  <div class="footer-links-grid">
    <article class="footer-links-card">
      <h2><i class="fa fa-layer-group"></i> Topics</h2>
      <div class="footer-links-list">
        <?php foreach (array_slice($cats, 0, 8) as $cat): ?>
        <a href="/category/<?= Helper::sanitize($cat['slug']) ?>" class="footer-links-item">
          <span><i class="fa <?= Helper::sanitize($cat['icon']) ?>" style="color:<?= Helper::sanitize($cat['color']) ?>"></i> <?= Helper::sanitize($cat['name']) ?></span>
          <i class="fa fa-angle-right"></i>
        </a>
        <?php endforeach; ?>
      </div>
    </article>

    <article class="footer-links-card">
      <h2><i class="fa fa-building"></i> Company</h2>
      <div class="footer-links-list">
        <a href="/about" class="footer-links-item"><span>About Us</span><i class="fa fa-angle-right"></i></a>
        <a href="/careers" class="footer-links-item"><span>Careers</span><i class="fa fa-angle-right"></i></a>
        <a href="/advertise" class="footer-links-item"><span>Advertise</span><i class="fa fa-angle-right"></i></a>
        <a href="/contact" class="footer-links-item"><span>Contact</span><i class="fa fa-angle-right"></i></a>
        <a href="/press" class="footer-links-item"><span>Press Room</span><i class="fa fa-angle-right"></i></a>
      </div>
    </article>

    <article class="footer-links-card">
      <h2><i class="fa fa-shield-halved"></i> Legal</h2>
      <div class="footer-links-list">
        <a href="/privacy" class="footer-links-item"><span>Privacy Policy</span><i class="fa fa-angle-right"></i></a>
        <a href="/terms" class="footer-links-item"><span>Terms of Service</span><i class="fa fa-angle-right"></i></a>
        <a href="/disclaimer" class="footer-links-item"><span>Disclaimer</span><i class="fa fa-angle-right"></i></a>
        <a href="/corrections" class="footer-links-item"><span>Corrections</span><i class="fa fa-angle-right"></i></a>
        <a href="/sitemap.xml" class="footer-links-item"><span>Sitemap</span><i class="fa fa-angle-right"></i></a>
      </div>
    </article>

    <article class="footer-links-card">
      <h2><i class="fa fa-circle-info"></i> FatakNews</h2>
      <p class="footer-links-copy">India's fastest, youth-first digital news platform. Breaking news, community stories, and visual updates in one stream.</p>
      <div class="footer-links-social">
        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
        <a href="#" aria-label="Twitter/X"><i class="fab fa-x-twitter"></i></a>
        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
        <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
        <a href="#" aria-label="Telegram"><i class="fab fa-telegram-plane"></i></a>
      </div>
    </article>
  </div>
</section>
<?php include VIEW . 'layouts/footer.php'; ?>
