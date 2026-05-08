<?php
require_once BASE_PATH . '/includes/bootstrap.php';

Auth::requireRole('super_admin', 'admin');

$pageTitle = 'User Management - FatakNews';
$db = Database::getInstance();
$editId = max(0, (int)($_GET['edit'] ?? 0));
$roleFilter = trim((string)($_GET['role'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$query = trim((string)($_GET['q'] ?? ''));

$roles = $db->fetchAll("SELECT id, slug, name FROM roles ORDER BY id ASC");
$roleMap = [];
foreach ($roles as $role) {
    $roleMap[$role['id']] = $role;
}

$where = [];
$params = [];
if ($roleFilter !== '') {
    $where[] = 'r.slug=?';
    $params[] = $roleFilter;
}
if ($statusFilter === 'active') {
    $where[] = 'u.is_active=1';
}
if ($statusFilter === 'suspended') {
    $where[] = 'u.is_active=0';
}
if ($query !== '') {
    $where[] = '(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
    $like = '%' . $query . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$users = $db->fetchAll(
    "SELECT u.*, r.slug AS role_slug, r.name AS role_name
     FROM users u
     JOIN roles r ON r.id=u.role_id
     $whereSql
     ORDER BY FIELD(r.slug,'super_admin','admin','manager','editor','reporter','hr','user'), u.created_at DESC",
    $params
);

$editUser = $editId > 0
    ? $db->fetchOne("SELECT u.*, r.slug AS role_slug, r.name AS role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?", [$editId])
    : null;

$stats = [
    'total' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM users")['c'] ?? 0),
    'active' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM users WHERE is_active=1")['c'] ?? 0),
    'verified' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM users WHERE is_verified=1")['c'] ?? 0),
    'staff' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM users WHERE role_id <> 7")['c'] ?? 0),
];

$defaultForm = [
    'id' => 0,
    'role_id' => 7,
    'username' => '',
    'email' => '',
    'phone' => '',
    'full_name' => '',
    'bio' => '',
    'location' => '',
    'website' => '',
    'avatar' => '',
    'is_verified' => 0,
    'is_active' => 1,
    'email_verified' => 0,
    'badge_level' => 'bronze',
];

$formUser = array_merge($defaultForm, $editUser ?? []);

function userFilterUrl(array $overrides = []): string {
    $query = array_merge($_GET, $overrides);
    $query = array_filter($query, fn($value) => $value !== '' && $value !== null);
    return '/admin/users' . ($query ? '?' . http_build_query($query) : '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?></title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/public/assets/css/app.css">
</head>
<body>
<div class="panel-layout">
  <aside class="panel-sidebar" id="panelSidebar">
    <div class="panel-logo">
      <div class="nav-logo" style="padding:0">
        <div class="logo-icon"><i class="fa fa-bolt"></i></div>
        <span class="logo-text" style="font-family:'Space Grotesk',sans-serif;font-size:18px">Fatak<strong>News</strong></span>
      </div>
      <div style="font-size:11px;color:var(--red);font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-top:6px">Admin Panel</div>
    </div>
    <nav class="panel-nav">
      <div class="panel-nav-section">Dashboard</div>
      <a href="/admin" class="panel-nav-link"><i class="fa fa-th-large"></i> Overview</a>
      <a href="/admin/analytics" class="panel-nav-link"><i class="fa fa-chart-bar"></i> Analytics</a>
      <div class="panel-nav-section">Content</div>
      <a href="/admin/pipeline" class="panel-nav-link"><i class="fa fa-tower-broadcast"></i> AI Pipeline</a>
      <a href="/admin/news" class="panel-nav-link"><i class="fa fa-newspaper"></i> All Posts</a>
      <a href="/admin/engagement" class="panel-nav-link"><i class="fa fa-chart-line"></i> Like &amp; View Manage</a>
      <a href="/admin/categories" class="panel-nav-link"><i class="fa fa-folder"></i> Categories</a>
      <a href="/admin/ads" class="panel-nav-link"><i class="fa fa-ad"></i> Advertisements</a>
      <div class="panel-nav-section">Users</div>
      <a href="/admin/users" class="panel-nav-link active"><i class="fa fa-users"></i> All Users</a>
      <a href="/hr" class="panel-nav-link"><i class="fa fa-id-card"></i> HR Module</a>
      <div class="panel-nav-section">System</div>
      <a href="/admin/settings" class="panel-nav-link"><i class="fa fa-cog"></i> Settings</a>
      <a href="/" class="panel-nav-link" target="_blank"><i class="fa fa-external-link-alt"></i> View Site</a>
      <a href="/logout" class="panel-nav-link" style="color:var(--red)"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </nav>
  </aside>

  <main class="panel-main">
    <div class="panel-header">
      <div>
        <h1>User Management</h1>
        <p style="color:var(--muted);font-size:13px;margin-top:2px">Manage roles, account state, verification, and profile metadata.</p>
      </div>
      <div style="display:flex;gap:10px;align-items:center">
        <?php if ($editUser): ?>
        <a href="/admin/users" class="btn-sm btn-edit">New User</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,45,45,0.15);color:var(--red)"><i class="fa fa-users"></i></div>
        <div class="stat-info"><strong><?= $stats['total'] ?></strong><span>Total Users</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,200,83,0.15);color:var(--green)"><i class="fa fa-user-check"></i></div>
        <div class="stat-info"><strong><?= $stats['active'] ?></strong><span>Active Accounts</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(41,121,255,0.15);color:var(--blue)"><i class="fa fa-badge-check"></i></div>
        <div class="stat-info"><strong><?= $stats['verified'] ?></strong><span>Verified Users</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,215,0,0.15);color:var(--yellow)"><i class="fa fa-briefcase"></i></div>
        <div class="stat-info"><strong><?= $stats['staff'] ?></strong><span>Staff Accounts</span></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:minmax(340px,430px) 1fr;gap:20px;align-items:start">
      <div class="data-table-wrap">
        <div class="table-header">
          <h3><?= $editUser ? 'Edit User' : 'Create User' ?></h3>
        </div>
        <form id="userForm" style="padding:20px;display:flex;flex-direction:column;gap:14px">
          <input type="hidden" name="user_id" value="<?= (int)$formUser['id'] ?>">
          <input type="hidden" name="avatar" id="userAvatarInput" value="<?= Helper::sanitize($formUser['avatar']) ?>">
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Profile Image</label>
            <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
              <img src="<?= Helper::avatarUrl($formUser['avatar'] ?? null) ?>" id="userAvatarPreview" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid var(--border)">
              <div style="display:flex;flex-direction:column;gap:8px">
                <div style="font-size:12px;color:var(--muted)">JPG, PNG, WebP up to 5MB</div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                  <button type="button" class="btn-sm btn-edit" onclick="document.getElementById('userAvatarFile').click()">Upload Image</button>
                  <button type="button" class="btn-sm btn-reject" id="userAvatarRemove" <?= empty($formUser['avatar']) || $formUser['avatar'] === 'default.png' ? 'style="display:none"' : '' ?>>Remove</button>
                </div>
              </div>
            </div>
            <input type="file" id="userAvatarFile" accept="image/*" style="display:none">
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Full Name</label>
            <input type="text" name="full_name" class="form-control" value="<?= Helper::sanitize($formUser['full_name']) ?>" required>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Username</label>
              <input type="text" name="username" class="form-control" value="<?= Helper::sanitize($formUser['username']) ?>" required>
            </div>
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Email</label>
              <input type="email" name="email" class="form-control" value="<?= Helper::sanitize($formUser['email']) ?>" required>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Role</label>
              <select name="role_id" class="form-control">
                <?php foreach ($roles as $role): ?>
                <option value="<?= (int)$role['id'] ?>" <?= (int)$formUser['role_id'] === (int)$role['id'] ? 'selected' : '' ?>><?= Helper::sanitize($role['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Badge</label>
              <select name="badge_level" class="form-control">
                <?php foreach (['bronze','silver','gold','platinum','press'] as $badge): ?>
                <option value="<?= $badge ?>" <?= $formUser['badge_level'] === $badge ? 'selected' : '' ?>><?= ucfirst($badge) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Password <?= $editUser ? '(leave blank to keep current)' : '' ?></label>
            <input type="password" name="password" class="form-control" <?= $editUser ? '' : 'required' ?>>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Phone</label>
              <input type="text" name="phone" class="form-control" value="<?= Helper::sanitize($formUser['phone']) ?>">
            </div>
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Location</label>
              <input type="text" name="location" class="form-control" value="<?= Helper::sanitize($formUser['location']) ?>">
            </div>
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Website</label>
            <input type="text" name="website" class="form-control" value="<?= Helper::sanitize($formUser['website']) ?>">
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Bio</label>
            <textarea name="bio" class="form-control" rows="4"><?= Helper::sanitize($formUser['bio']) ?></textarea>
          </div>
          <label style="display:flex;gap:10px;align-items:center;font-size:13px;color:var(--text)">
            <input type="checkbox" name="is_active" value="1" <?= !empty($formUser['is_active']) ? 'checked' : '' ?>>
            Active
          </label>
          <label style="display:flex;gap:10px;align-items:center;font-size:13px;color:var(--text)">
            <input type="checkbox" name="is_verified" value="1" <?= !empty($formUser['is_verified']) ? 'checked' : '' ?>>
            Verified
          </label>
          <label style="display:flex;gap:10px;align-items:center;font-size:13px;color:var(--text)">
            <input type="checkbox" name="email_verified" value="1" <?= !empty($formUser['email_verified']) ? 'checked' : '' ?>>
            Email Verified
          </label>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button type="submit" class="btn-sm btn-approve"><?= $editUser ? 'Update User' : 'Create User' ?></button>
            <?php if ($editUser): ?>
            <a href="/admin/users" class="btn-sm btn-edit">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div class="data-table-wrap">
        <div class="table-header" style="gap:14px;flex-wrap:wrap">
          <h3>All Users</h3>
          <form method="get" action="/admin/users" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <input type="text" name="q" value="<?= Helper::sanitize($query) ?>" class="form-control" placeholder="Search name, username, email..." style="width:240px">
            <select name="role" class="form-control" style="width:170px">
              <option value="">All roles</option>
              <?php foreach ($roles as $role): ?>
              <option value="<?= Helper::sanitize($role['slug']) ?>" <?= $roleFilter === $role['slug'] ? 'selected' : '' ?>><?= Helper::sanitize($role['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <select name="status" class="form-control" style="width:160px">
              <option value="">Any status</option>
              <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
              <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
            </select>
            <button type="submit" class="btn-sm btn-approve">Apply</button>
            <a href="/admin/users" class="btn-sm btn-edit">Reset</a>
          </form>
        </div>
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap">
          <a href="<?= userFilterUrl(['role' => null, 'status' => null, 'q' => null]) ?>" class="tag-chip" style="<?= $roleFilter === '' && $statusFilter === '' && $query === '' ? 'background:rgba(255,45,45,0.14);color:var(--red)' : '' ?>">All</a>
          <a href="<?= userFilterUrl(['status' => 'active']) ?>" class="tag-chip" style="<?= $statusFilter === 'active' ? 'background:rgba(0,200,83,0.14);color:var(--green)' : '' ?>">Active</a>
          <a href="<?= userFilterUrl(['status' => 'suspended']) ?>" class="tag-chip" style="<?= $statusFilter === 'suspended' ? 'background:rgba(255,45,45,0.14);color:var(--red)' : '' ?>">Suspended</a>
          <a href="<?= userFilterUrl(['role' => 'admin']) ?>" class="tag-chip" style="<?= $roleFilter === 'admin' ? 'background:rgba(41,121,255,0.14);color:var(--blue)' : '' ?>">Admins</a>
          <a href="<?= userFilterUrl(['role' => 'user']) ?>" class="tag-chip" style="<?= $roleFilter === 'user' ? 'background:rgba(255,215,0,0.14);color:var(--yellow)' : '' ?>">Users</a>
        </div>
        <div style="overflow-x:auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Role</th>
                <th>Status</th>
                <th>Stats</th>
                <th>Joined</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $user): ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:12px">
                    <img src="<?= Helper::avatarUrl($user['avatar'] ?? null) ?>" style="width:38px;height:38px;border-radius:50%;object-fit:cover">
                    <div>
                      <div style="font-weight:700"><?= Helper::sanitize($user['full_name']) ?></div>
                      <div style="font-size:12px;color:var(--muted)">@<?= Helper::sanitize($user['username']) ?> · <?= Helper::sanitize($user['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="status-badge status-draft"><?= Helper::sanitize($user['role_name']) ?></span>
                </td>
                <td>
                  <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <span class="status-badge <?= (int)$user['is_active'] === 1 ? 'status-published' : 'status-rejected' ?>">
                      <?= (int)$user['is_active'] === 1 ? 'Active' : 'Suspended' ?>
                    </span>
                    <?php if ((int)$user['is_verified'] === 1): ?>
                    <span class="status-badge status-pending">Verified</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td style="font-size:12px;color:var(--muted)">
                  <div><?= Helper::formatNumber((int)$user['followers_count']) ?> followers</div>
                  <div><?= Helper::formatNumber((int)$user['posts_count']) ?> posts · <?= Helper::formatNumber((int)$user['points']) ?> pts</div>
                </td>
                <td style="font-size:12px;color:var(--muted)">
                  <div><?= date('d M Y', strtotime($user['created_at'])) ?></div>
                  <div><?= Helper::timeAgo($user['created_at']) ?></div>
                </td>
                <td>
                  <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <a href="/admin/users?edit=<?= (int)$user['id'] ?>" class="btn-sm btn-edit">Edit</a>
                    <button class="btn-sm btn-approve" onclick="userAction('toggle_verified', <?= (int)$user['id'] ?>)">
                      <?= (int)$user['is_verified'] === 1 ? 'Unverify' : 'Verify' ?>
                    </button>
                    <button class="btn-sm <?= (int)$user['is_active'] === 1 ? 'btn-reject' : 'btn-approve' ?>" onclick="userAction('toggle_active', <?= (int)$user['id'] ?>)">
                      <?= (int)$user['is_active'] === 1 ? 'Suspend' : 'Activate' ?>
                    </button>
                    <?php if (!in_array($user['role_slug'], ['super_admin', 'admin'], true)): ?>
                    <button class="btn-sm btn-delete" data-confirm="Delete this user?" onclick="userAction('delete', <?= (int)$user['id'] ?>)">Delete</button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<div class="toast-container" id="toastContainer"></div>
<script>
const APP = { url:'<?= Helper::appUrl() ?>', csrfToken:'<?= Csrf::token() ?>', userId:<?= Auth::id() ?>, isLoggedIn:true };
</script>
<script src="/public/assets/js/app.js"></script>
<script>
document.getElementById('userForm').addEventListener('submit', async (event) => {
  event.preventDefault();

  const form = event.currentTarget;
  const formData = new FormData(form);
  const userId = formData.get('user_id');
  const payload = {
    action: userId && userId !== '0' ? 'update' : 'create',
    user_id: Number(userId || 0),
    full_name: formData.get('full_name'),
    username: formData.get('username'),
    email: formData.get('email'),
    role_id: Number(formData.get('role_id') || 7),
    badge_level: formData.get('badge_level'),
    password: formData.get('password'),
    phone: formData.get('phone'),
    location: formData.get('location'),
    website: formData.get('website'),
    bio: formData.get('bio'),
    avatar: formData.get('avatar'),
    is_active: formData.get('is_active') ? 1 : 0,
    is_verified: formData.get('is_verified') ? 1 : 0,
    email_verified: formData.get('email_verified') ? 1 : 0
  };

  const data = await API.post('/api/admin/users', payload);
  if (!data.success) {
    Toast.show(data.error || 'Action failed', 'error');
    return;
  }

  Toast.show(data.message || 'Saved', 'success');
  setTimeout(() => window.location.href = '/admin/users', 700);
});

document.getElementById('userAvatarFile').addEventListener('change', async (event) => {
  const file = event.target.files[0];
  if (!file) return;

  const data = await API.upload('/api/upload', file, { dir: 'avatars' });
  if (!data.success) {
    Toast.show(data.error || 'Upload failed', 'error');
    event.target.value = '';
    return;
  }

  document.getElementById('userAvatarInput').value = data.filename;
  document.getElementById('userAvatarPreview').src = data.url;
  document.getElementById('userAvatarRemove').style.display = 'inline-flex';
  Toast.show('Profile image uploaded', 'success');
  event.target.value = '';
});

document.getElementById('userAvatarRemove').addEventListener('click', () => {
  document.getElementById('userAvatarInput').value = '';
  document.getElementById('userAvatarPreview').src = '<?= Helper::avatarUrl(null) ?>';
  document.getElementById('userAvatarRemove').style.display = 'none';
});

async function userAction(action, userId) {
  const data = await API.post('/api/admin/users', { action, user_id: userId });
  if (!data.success) {
    Toast.show(data.error || 'Action failed', 'error');
    return;
  }

  Toast.show(data.message || 'Updated', 'success');
  setTimeout(() => window.location.reload(), 700);
}
</script>
</body>
</html>
