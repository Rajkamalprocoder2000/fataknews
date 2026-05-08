<?php
$pageTitle = $pageTitle ?? 'Coming Soon';
$pageDesc = $message ?? 'This section is not available yet.';
$placeholderIcon = $placeholderIcon ?? 'fa-compass-drafting';
include VIEW . 'layouts/header.php';
?>
<section style="max-width:980px;margin:0 auto;padding:56px 20px 24px">
  <div style="background:linear-gradient(135deg,rgba(255,45,45,0.14),rgba(41,121,255,0.1));border:1px solid var(--border2);border-radius:var(--radius-lg);padding:34px;overflow:hidden;position:relative">
    <div style="position:absolute;right:-30px;top:-40px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,0.04)"></div>
    <div style="position:relative;max-width:640px">
      <div style="width:58px;height:58px;border-radius:16px;background:rgba(255,45,45,0.16);display:flex;align-items:center;justify-content:center;color:var(--red);font-size:22px;margin-bottom:18px">
        <i class="fa <?= $placeholderIcon ?>"></i>
      </div>
      <div class="widget-title" style="margin-bottom:10px">FatakNews Module</div>
      <h1 style="font-family:'Baloo 2',cursive;font-size:38px;line-height:1.1;margin-bottom:10px"><?= Helper::sanitize($pageTitle) ?></h1>
      <p style="font-size:16px;color:var(--muted);max-width:560px"><?= Helper::sanitize($message ?? 'This section is not available yet.') ?></p>
      <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:22px">
        <a href="/" class="btn-write"><i class="fa fa-house"></i> Back Home</a>
        <a href="/feed" class="btn-ghost">Open Feed</a>
      </div>
    </div>
  </div>
</section>
<?php include VIEW . 'layouts/footer.php'; ?>
