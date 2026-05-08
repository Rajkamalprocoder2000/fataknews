<?php
require_once BASE_PATH . '/includes/bootstrap.php';
if (Auth::check()) Helper::redirect('/');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
$pageTitle = 'Create Account - FatakNews.in';
$error = $_SESSION['register_error'] ?? null;
unset($_SESSION['register_error']);
$googleRegisterUrl = Helper::siteUrl('auth/google?return_to=' . urlencode('/register') . '&redirect=' . urlencode('/'));
$googleRedirectUri = GoogleAuth::redirectUri();
$appCssPath = BASE_PATH . '/public/assets/css/app.css';
$appMinCssPath = BASE_PATH . '/public/assets/css/app.min.css';
$appCssFile = 'app.css';
if (!is_file($appCssPath) && is_file($appMinCssPath)) {
    $appCssFile = 'app.min.css';
}
$metaRobots = $metaRobots ?? 'noindex,nofollow';
$canonicalUrl = Helper::siteUrl('register');
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
      <h1 style="font-family:'Baloo 2',cursive">Join FatakNews</h1>
      <p>Create your account and join the community</p>
    </div>

    <?php if ($error): ?>
    <div style="background:rgba(255,45,45,0.1);border:1px solid rgba(255,45,45,0.3);border-radius:var(--radius);padding:12px 16px;font-size:14px;color:var(--red);margin-bottom:16px;display:flex;align-items:center;gap:8px">
      <i class="fa fa-exclamation-circle"></i> <?= Helper::sanitize($error) ?>
    </div>
    <?php endif; ?>

    <?php if (GoogleAuth::isConfigured()): ?>
    <a href="<?= Helper::sanitize($googleRegisterUrl) ?>" class="btn-ghost" style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;padding:11px;margin-bottom:14px">
      <i class="fab fa-google" style="color:#EA4335"></i> Continue with Google
    </a>
    <?php else: ?>
    <button type="button" class="btn-ghost" style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;padding:11px;margin-bottom:14px;opacity:.65;cursor:not-allowed" disabled>
      <i class="fab fa-google" style="color:#EA4335"></i> Continue with Google
    </button>
    <div style="font-size:12px;color:var(--muted);margin:-4px 0 14px;text-align:center;line-height:1.55">
      Add <code>GOOGLE_CLIENT_ID</code> and <code>GOOGLE_CLIENT_SECRET</code> in <code>config/local.php</code> or environment variables.<br>
      Authorized redirect URI: <code><?= Helper::sanitize($googleRedirectUri) ?></code>
    </div>
    <?php endif; ?>

    <div class="auth-divider" style="margin-top:0">or sign up with email</div>

    <form id="registerForm">
      <?= Csrf::field() ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group" style="margin:0">
          <label>First Name</label>
          <input type="text" name="first_name" class="form-control" placeholder="Rahul" required>
        </div>
        <div class="form-group" style="margin:0">
          <label>Last Name</label>
          <input type="text" name="last_name" class="form-control" placeholder="Sharma" required>
        </div>
      </div>
      <div class="form-group" style="margin-top:12px">
        <label>Username</label>
        <div style="position:relative">
          <span style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted)">@</span>
          <input type="text" name="username" class="form-control" style="padding-left:30px" placeholder="rahulsharma" required id="usernameField" pattern="[a-zA-Z0-9_]{3,30}">
        </div>
        <div id="usernameFeedback" style="font-size:12px;margin-top:4px"></div>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" class="form-control" placeholder="rahul@example.com" required>
      </div>
      <div class="form-group">
        <label>Password</label>
        <div style="position:relative">
          <input type="password" name="password" class="form-control" id="regPass" placeholder="Min 8 characters" required minlength="8">
          <button type="button" onclick="document.getElementById('regPass').type=document.getElementById('regPass').type==='password'?'text':'password'" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:14px"><i class="fa fa-eye"></i></button>
        </div>
        <div style="display:flex;gap:4px;margin-top:8px" id="strengthMeter">
          <div style="height:3px;flex:1;border-radius:2px;background:var(--border)" id="s1"></div>
          <div style="height:3px;flex:1;border-radius:2px;background:var(--border)" id="s2"></div>
          <div style="height:3px;flex:1;border-radius:2px;background:var(--border)" id="s3"></div>
          <div style="height:3px;flex:1;border-radius:2px;background:var(--border)" id="s4"></div>
        </div>
      </div>
      <div class="form-group">
        <label>I'm interested in</label>
        <div style="display:flex;flex-wrap:wrap;gap:6px" id="interests">
          <?php foreach (['Politics','Sports','Technology','Business','Entertainment','Health','Education'] as $t): ?>
          <label style="display:flex;align-items:center;gap:5px;padding:5px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:50px;font-size:12px;cursor:pointer;transition:0.2s">
            <input type="checkbox" name="interests[]" value="<?= strtolower($t) ?>" style="display:none">
            <?= $t ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <label style="display:flex;align-items:flex-start;gap:8px;font-size:13px;cursor:pointer;margin-bottom:16px;line-height:1.5">
        <input type="checkbox" name="agree_terms" value="1" style="accent-color:var(--red);margin-top:2px;flex-shrink:0" required>
        I agree to FatakNews <a href="/terms" style="color:var(--red)">Terms of Service</a> and <a href="/privacy" style="color:var(--red)">Privacy Policy</a>
      </label>
      <button type="submit" class="btn-block" id="regBtn">Create Account</button>
    </form>

    <div class="auth-footer">
      Already have an account? <a href="/login">Login</a>
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

document.getElementById('regPass').addEventListener('input', function() {
  const v = this.value;
  let s = 0;
  if (v.length >= 8) s++;
  if (/[A-Z]/.test(v)) s++;
  if (/[0-9]/.test(v)) s++;
  if (/[^A-Za-z0-9]/.test(v)) s++;
  const colors = ['#FF2D2D','#FF6B1A','#FFD700','#00C853'];
  for (let i = 1; i <= 4; i++) {
    document.getElementById('s' + i).style.background = i <= s ? colors[s - 1] : 'var(--border)';
  }
});

let utimer;
document.getElementById('usernameField').addEventListener('input', function() {
  clearTimeout(utimer);
  const fb = document.getElementById('usernameFeedback');
  if (this.value.length < 3) return;
  utimer = setTimeout(async () => {
    const data = await API.get(`/api/check-username?u=${encodeURIComponent(this.value)}`);
    fb.textContent = data.available ? 'Username available' : 'Username taken';
    fb.style.color = data.available ? 'var(--green)' : 'var(--red)';
  }, 400);
});

document.querySelectorAll('#interests label').forEach(label => {
  const cb = label.querySelector('input');
  label.addEventListener('click', () => {
    setTimeout(() => {
      label.style.background = cb.checked ? 'rgba(255,45,45,0.15)' : 'var(--bg3)';
      label.style.borderColor = cb.checked ? 'var(--red)' : 'var(--border)';
      label.style.color = cb.checked ? 'var(--red)' : '';
    }, 0);
  });
});

document.getElementById('registerForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('regBtn');
  const form = e.target;
  APP.csrfToken = form.querySelector('[name="csrf_token"]')?.value || APP.csrfToken;
  btn.textContent = 'Creating account...';
  btn.disabled = true;

  try {
    let retried = false;
    while (true) {
      const formData = new FormData(form);
      const data = await API.post('/api/auth/register', Object.fromEntries(formData));
      if (data.success) {
        Toast.show('Account created! Welcome to FatakNews', 'success');
        setTimeout(() => window.location.href = resolveAppUrl('/'), 1200);
        return;
      }
      if (data.error === 'Invalid CSRF token' && !retried) {
        retried = true;
        await refreshAuthCsrfToken(form);
        continue;
      }
      if (data.error === 'Invalid CSRF token') {
        Toast.show('Session refreshed. Please submit again.', 'info');
        return;
      }
      Toast.show(data.error || 'Registration failed', 'error');
      return;
    }
  } catch {
    Toast.show('Registration request failed', 'error');
  } finally {
    btn.textContent = 'Create Account';
    btn.disabled = false;
  }
});
</script>
</body>
</html>
