<?php
require_once BASE_PATH . '/includes/bootstrap.php';
if (Auth::check()) Helper::redirect('/');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
$pageTitle = 'Set New Password - FatakNews.in';
$resetToken = trim((string)($resetToken ?? ''));
$resetUser = $resetUser ?? null;
$isValidToken = $resetToken !== '' && !empty($resetUser);
$appCssPath = BASE_PATH . '/public/assets/css/app.css';
$appMinCssPath = BASE_PATH . '/public/assets/css/app.min.css';
$appCssFile = 'app.css';
if (!is_file($appCssPath) && is_file($appMinCssPath)) {
    $appCssFile = 'app.min.css';
}
$metaRobots = $metaRobots ?? 'noindex,nofollow';
$canonicalUrl = Helper::siteUrl('reset-password');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $pageTitle ?></title>
<meta name="robots" content="<?= Helper::sanitize($metaRobots) ?>">
<link rel="canonical" href="<?= Helper::sanitize($canonicalUrl) ?>">
<?= Helper::analyticsHeadHtml() ?>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/public/assets/css/<?= $appCssFile ?>">
</head>
<body>
<?= Helper::analyticsBodyOpenHtml() ?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="logo-icon" style="width:52px;height:52px;font-size:24px"><i class="fa fa-lock"></i></div>
      <h1 style="font-family:'Baloo 2',cursive">Set New Password</h1>
      <p><?= $isValidToken ? 'Choose a new password for your account.' : 'This reset link is invalid or expired.' ?></p>
    </div>

    <?php if ($isValidToken): ?>
    <form id="resetPasswordForm">
      <?= Csrf::field() ?>
      <input type="hidden" name="token" value="<?= Helper::sanitize($resetToken) ?>">
      <div class="form-group">
        <label>New Password</label>
        <input type="password" name="password" class="form-control" placeholder="Minimum 8 characters" required minlength="8">
      </div>
      <div class="form-group">
        <label>Confirm Password</label>
        <input type="password" name="password_confirm" class="form-control" placeholder="Repeat password" required minlength="8">
      </div>
      <button type="submit" class="btn-block" id="resetPasswordBtn">Update Password</button>
    </form>
    <?php else: ?>
    <div style="background:rgba(255,45,45,0.1);border:1px solid rgba(255,45,45,0.25);border-radius:var(--radius);padding:14px 16px;color:var(--red);font-size:14px">
      Request a new link from the forgot password page.
    </div>
    <?php endif; ?>

    <div class="auth-footer">
      <a href="/forgot-password">Request another reset link</a>
    </div>
  </div>
</div>
<div class="toast-container" id="toastContainer"></div>
<script>
const APP = { url:'<?= Helper::appUrl() ?>', csrfToken:'<?= Csrf::token() ?>', isLoggedIn:false };
</script>
<script src="/public/assets/js/app.js"></script>
<?php if ($isValidToken): ?>
<script>
async function refreshResetCsrf(form) {
  const response = await fetch(resolveAppUrl(`/api/csrf?ts=${Date.now()}`), {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    cache: 'no-store'
  });
  const data = await response.json();
  if (!data.success || !data.token) throw new Error('Unable to refresh session');
  APP.csrfToken = data.token;
  form.querySelector('[name="csrf_token"]').value = data.token;
}

document.getElementById('resetPasswordForm').addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = event.target;
  const button = document.getElementById('resetPasswordBtn');
  APP.csrfToken = form.querySelector('[name="csrf_token"]')?.value || APP.csrfToken;

  button.disabled = true;
  button.textContent = 'Updating...';

  try {
    let retried = false;
    while (true) {
      const data = await API.post('/api/auth/reset-password', Object.fromEntries(new FormData(form)));
      if (data.success) {
        Toast.show(data.message || 'Password updated', 'success');
        setTimeout(() => window.location.href = resolveAppUrl(data.redirect || '/login'), 1000);
        return;
      }
      if (data.error === 'Invalid CSRF token' && !retried) {
        retried = true;
        await refreshResetCsrf(form);
        continue;
      }
      Toast.show(data.error || 'Password reset failed', 'error');
      return;
    }
  } catch {
    Toast.show('Password reset failed', 'error');
  } finally {
    button.disabled = false;
    button.textContent = 'Update Password';
  }
});
</script>
<?php endif; ?>
</body>
</html>
