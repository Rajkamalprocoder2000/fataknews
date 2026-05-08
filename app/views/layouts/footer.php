</div><!-- /page-wrapper -->

<!-- ===== FOOTER ===== -->
<footer class="site-footer">
  <div class="footer-container">
    <div class="footer-top">
      <!-- Brand -->
      <div class="footer-brand">
        <div class="footer-logo">
          <div class="logo-icon"><i class="fa fa-bolt"></i></div>
          <span>Fatak<strong>News</strong></span>
        </div>
        <p>India's fastest, youth-first digital news platform. Breaking news, community stories, and more - Fatafat!</p>
        <div class="footer-social">
          <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
          <a href="#" aria-label="Twitter/X"><i class="fab fa-x-twitter"></i></a>
          <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
          <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
          <a href="#" aria-label="Telegram"><i class="fab fa-telegram-plane"></i></a>
        </div>
      </div>

      <!-- Categories -->
      <div class="footer-col">
        <h4>Topics</h4>
        <?php
          $footerCats = Helper::cacheRemember('layout_footer_topics_v1', 300, static function (): array {
              return (new CategoryModel())->getTopLevel();
          });
          foreach (array_slice($footerCats, 0, 6) as $cat):
        ?>
        <a href="/category/<?= $cat['slug'] ?>"><?= $cat['name'] ?></a>
        <?php endforeach; ?>
      </div>

      <!-- Company -->
      <div class="footer-col">
        <h4>Company</h4>
        <a href="/about">About Us</a>
        <a href="/careers">Careers</a>
        <a href="/advertise">Advertise</a>
        <a href="/contact">Contact</a>
        <a href="/press">Press Room</a>
      </div>

      <!-- Legal -->
      <div class="footer-col">
        <h4>Legal</h4>
        <a href="/privacy">Privacy Policy</a>
        <a href="/terms">Terms of Service</a>
        <a href="/disclaimer">Disclaimer</a>
        <a href="/corrections">Corrections</a>
        <a href="/sitemap.xml">Sitemap</a>
      </div>
    </div>

    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> FatakNews.in - All rights reserved.</span>
      <span>Made with <i class="fa fa-heart text-red"></i> in India</span>
    </div>
  </div>
</footer>

<!-- ===== TOAST CONTAINER ===== -->
<div class="toast-container" id="toastContainer"></div>

<!-- ===== SCRIPTS ===== -->
<script>
window.APP = Object.assign({}, window.APP || {}, {
  url: '<?= Helper::appUrl() ?>',
  csrfToken: '<?= Csrf::token() ?>',
  userId: <?= Auth::id() ?? 'null' ?>,
  isLoggedIn: <?= Auth::check() ? 'true' : 'false' ?>,
  avatarFallback: '<?= Helper::avatarUrl(null) ?>'
});
</script>
<?php if (empty($appJsPreloaded)): ?>
<?php $appJsVersion = @filemtime(BASE_PATH . '/public/assets/js/app.js') ?: time(); ?>
<script src="/public/assets/js/app.js?v=<?= $appJsVersion ?>"></script>
<?php endif; ?>
<?= $extraScripts ?? '' ?>
</body>
</html>
