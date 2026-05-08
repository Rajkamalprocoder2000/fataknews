<?php
require_once BASE_PATH . '/includes/bootstrap.php';
if (Auth::check()) Helper::redirect('/');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
$pageTitle = 'Forgot Password - FatakNews.in';
$appCssPath = BASE_PATH . '/public/assets/css/app.css';
$appMinCssPath = BASE_PATH . '/public/assets/css/app.min.css';
$appCssFile = 'app.css';
if (!is_file($appCssPath) && is_file($appMinCssPath)) {
    $appCssFile = 'app.min.css';
}
$metaRobots = $metaRobots ?? 'noindex,nofollow';
$canonicalUrl = Helper::siteUrl('forgot-password');
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
      <div class="logo-icon" style="width:52px;height:52px;font-size:24px"><i class="fa fa-key"></i></div>
      <h1 style="font-family:'Baloo 2',cursive">Reset Password</h1>
      <p>Apna email dalo. Reset link generate ho jayega.</p>
    </div>

    <form id="forgotPasswordForm">
      <?= Csrf::field() ?>
      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" class="form-control" placeholder="you@example.com" required autofocus>
      </div>
      <button type="submit" class="btn-block" id="forgotPasswordBtn">Send Reset Link</button>
    </form>

    <div class="auth-footer">
      Remembered your password? <a href="/login">Back to login</a>
    </div>
  </div>
</div>
<div class="toast-container" id="toastContainer"></div>
<script>
const APP = { url:'<?= Helper::appUrl() ?>', csrfToken:'<?= Csrf::token() ?>', isLoggedIn:false };
</script>
<script src="/public/assets/js/app.js"></script>
<script>
async function refreshForgotCsrf(form) {
  const response = await fetch(resolveAppUrl(`/api/csrf?ts=${Date.now()}`), {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    cache: 'no-store'
  });
  const data = await response.json();
  if (!data.success || !data.token) throw new Error('Unable to refresh session');
  APP.csrfToken = data.token;
  form.querySelector('[name="csrf_token"]').value = data.token;
}

document.getElementById('forgotPasswordForm').addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = event.target;
  const button = document.getElementById('forgotPasswordBtn');
  APP.csrfToken = form.querySelector('[name="csrf_token"]')?.value || APP.csrfToken;

  button.disabled = true;
  button.textContent = 'Sending...';

  try {
    let retried = false;
    while (true) {
      const data = await API.post('/api/auth/forgot-password', Object.fromEntries(new FormData(form)));
      if (data.success) {
        Toast.show(data.message || 'Reset link generated', 'success');
        return;
      }
      if (data.error === 'Invalid CSRF token' && !retried) {
        retried = true;
        await refreshForgotCsrf(form);
        continue;
      }
      Toast.show(data.error || 'Request failed', 'error');
      return;
    }
  } catch {
    Toast.show('Reset request failed', 'error');
  } finally {
    button.disabled = false;
    button.textContent = 'Send Reset Link';
  }
});
</script>
</body>
</html>
