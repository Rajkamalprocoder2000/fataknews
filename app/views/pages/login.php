<?php
require_once BASE_PATH . '/includes/bootstrap.php';
if (Auth::check()) Helper::redirect('/');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
$pageTitle = 'Login - FatakNews.in';
$error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
$authRedirect = Helper::safeLocalPath((string)($_GET['redirect'] ?? '/'), '/');
$googleLoginUrl = Helper::siteUrl('auth/google?return_to=' . urlencode('/login') . '&redirect=' . urlencode($authRedirect !== '' ? $authRedirect : '/'));
$googleRedirectUri = GoogleAuth::redirectUri();
$appCssPath = BASE_PATH . '/public/assets/css/app.css';
$appMinCssPath = BASE_PATH . '/public/assets/css/app.min.css';
$appCssFile = 'app.css';
if (!is_file($appCssPath) && is_file($appMinCssPath)) {
    $appCssFile = 'app.min.css';
}
$metaRobots = $metaRobots ?? 'noindex,nofollow';
$canonicalUrl = Helper::siteUrl('login');
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
      <div class="logo-icon" style="width:52px;height:52px;font-size:24px"><i class="fa fa-bolt"></i></div>
      <h1 style="font-family:'Baloo 2',cursive">FatakNews<span style="color:var(--red)">.in</span></h1>
      <p>Welcome back! Login to continue</p>
    </div>

    <?php if ($error): ?>
    <div style="background:rgba(255,45,45,0.1);border:1px solid rgba(255,45,45,0.3);border-radius:var(--radius);padding:12px 16px;font-size:14px;color:var(--red);margin-bottom:16px;display:flex;align-items:center;gap:8px">
      <i class="fa fa-exclamation-circle"></i> <?= Helper::sanitize($error) ?>
    </div>
    <?php endif; ?>

    <form id="loginForm">
      <?= Csrf::field() ?>
      <div class="form-group">
        <label>Email or Username</label>
        <input type="text" name="email" class="form-control" placeholder="you@example.com" required autofocus>
      </div>
      <div class="form-group">
        <label style="display:flex;justify-content:space-between">
          Password <a href="/forgot-password" style="color:var(--red);font-weight:400;font-size:12px">Forgot password?</a>
        </label>
        <div style="position:relative">
          <input type="password" name="password" class="form-control" id="passField" placeholder="********" required>
          <button type="button" onclick="togglePass()" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:14px"><i class="fa fa-eye" id="eyeIcon"></i></button>
        </div>
      </div>
      <label style="display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer;margin-bottom:4px">
        <input type="checkbox" name="remember" value="1" style="accent-color:var(--red)"> Remember me for 7 days
      </label>
      <button type="submit" class="btn-block" id="loginBtn">Login</button>
    </form>

    <div class="auth-divider">or continue with</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <?php if (GoogleAuth::isConfigured()): ?>
      <a href="<?= Helper::sanitize($googleLoginUrl) ?>" class="btn-ghost" style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;padding:11px">
        <i class="fab fa-google" style="color:#EA4335"></i> Google
      </a>
      <?php else: ?>
      <button type="button" class="btn-ghost" style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;padding:11px;opacity:.65;cursor:not-allowed" disabled>
        <i class="fab fa-google" style="color:#EA4335"></i> Google
      </button>
      <?php endif; ?>
      <button type="button" class="btn-ghost" style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;padding:11px">
        <i class="fab fa-facebook-f" style="color:#1877F2"></i> Facebook
      </button>
    </div>
    <?php if (!GoogleAuth::isConfigured()): ?>
    <div style="font-size:12px;color:var(--muted);margin-top:10px;text-align:center;line-height:1.55">
      Add <code>GOOGLE_CLIENT_ID</code> and <code>GOOGLE_CLIENT_SECRET</code> in <code>config/local.php</code> or environment variables.<br>
      Authorized redirect URI: <code><?= Helper::sanitize($googleRedirectUri) ?></code>
    </div>
    <?php endif; ?>

    <div class="auth-footer">
      Don't have an account? <a href="/register">Sign up free</a>
    </div>
  </div>
</div>
<div class="toast-container" id="toastContainer"></div>
<script>
const APP = { url:'<?= Helper::appUrl() ?>', csrfToken:'<?= Csrf::token() ?>', isLoggedIn:false };
</script>
<script src="/public/assets/js/app.js"></script>
<script>
async function refreshAuthCsrfToken(form) {
  const response = await fetch(resolveAppUrl(`/api/csrf?ts=${Date.now()}`), {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    cache: 'no-store'
  });
  const data = await response.json();
  if (!data.success || !data.token) throw new Error('Unable to refresh session');
  APP.csrfToken = data.token;
  const hidden = form.querySelector('[name="csrf_token"]');
  if (hidden) hidden.value = data.token;
  return data.token;
}

function togglePass() {
  const f = document.getElementById('passField');
  const e = document.getElementById('eyeIcon');
  f.type = f.type === 'password' ? 'text' : 'password';
  e.className = f.type === 'password' ? 'fa fa-eye' : 'fa fa-eye-slash';
}

document.getElementById('loginForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('loginBtn');
  const form = e.target;
  APP.csrfToken = form.querySelector('[name="csrf_token"]')?.value || APP.csrfToken;
  btn.textContent = 'Logging in...';
  btn.disabled = true;

  try {
    let retried = false;
    while (true) {
      const formData = new FormData(form);
      const data = await API.post('/api/auth/login', Object.fromEntries(formData));
      if (data.success) {
        Toast.show('Welcome back!', 'success');
        setTimeout(() => window.location.href = resolveAppUrl(data.redirect || '/'), 800);
        return;
      }
      if (data.error === 'Invalid CSRF token' && !retried) {
        retried = true;
        await refreshAuthCsrfToken(form);
        continue;
      }
      if (data.error === 'Invalid CSRF token') {
        Toast.show('Session refreshed. Please try login again.', 'info');
        return;
      }
      Toast.show(data.error || 'Login failed', 'error');
      return;
    }
  } catch {
    Toast.show('Login request failed', 'error');
  } finally {
    btn.textContent = 'Login';
    btn.disabled = false;
  }
});
</script>
</body>
</html>
