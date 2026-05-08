<?php
Auth::requireLogin();
$user = Auth::user();
$pageTitle = 'Settings - FatakNews';
include VIEW . 'layouts/header.php';
?>
<div class="settings-page">
  <section class="sidebar-widget settings-hero">
    <div class="widget-title"><i class="fa fa-cog"></i> Account Settings</div>
    <h1 class="settings-title">Profile and account settings</h1>
    <p class="settings-subtitle">Update your public profile details, upload a profile image, and optionally change your password.</p>
  </section>

  <form id="accountSettingsForm" class="settings-form">
    <div class="sidebar-widget settings-card">
      <div class="widget-title"><i class="fa fa-image"></i> Profile Image</div>
      <input type="hidden" name="avatar" id="settingsAvatarInput" value="<?= Helper::sanitize($user['avatar']) ?>">
      <div class="settings-avatarpanel">
        <img src="<?= Helper::avatarUrl($user['avatar']) ?>" id="settingsAvatarPreview" class="profile-avatar settings-avatarpreview">
        <div class="settings-helptext">Visible across profile, comments, posts, and admin panels.</div>
        <div class="settings-avataractions">
          <button type="button" class="btn-ghost" onclick="document.getElementById('settingsAvatarFile').click()">Upload Photo</button>
          <button type="button" class="btn-ghost" id="settingsAvatarRemove" <?= empty($user['avatar']) || $user['avatar'] === 'default.png' ? 'style="display:none"' : '' ?>>Remove</button>
        </div>
        <input type="file" id="settingsAvatarFile" accept="image/*" style="display:none">
        <div class="settings-caption">JPG, PNG, WebP up to 5MB</div>
      </div>
    </div>

    <div class="settings-mainstack">
      <div class="sidebar-widget settings-card">
        <div class="widget-title"><i class="fa fa-user"></i> Public Profile</div>
        <div class="form-group">
          <label>Full Name</label>
          <input class="form-control" name="full_name" value="<?= Helper::sanitize($user['full_name']) ?>" required>
        </div>
        <div class="settings-splitgrid">
          <div class="form-group">
            <label>Username</label>
            <input class="form-control" value="@<?= Helper::sanitize($user['username']) ?>" readonly>
          </div>
          <div class="form-group">
            <label>Email</label>
            <input class="form-control" value="<?= Helper::sanitize($user['email']) ?>" readonly>
          </div>
        </div>
        <div class="settings-splitgrid">
          <div class="form-group">
            <label>Phone</label>
            <input class="form-control" name="phone" value="<?= Helper::sanitize($user['phone']) ?>">
          </div>
          <div class="form-group">
            <label>Location</label>
            <input class="form-control" name="location" value="<?= Helper::sanitize($user['location']) ?>">
          </div>
        </div>
        <div class="form-group">
          <label>Website</label>
          <input class="form-control" name="website" value="<?= Helper::sanitize($user['website']) ?>" placeholder="https://your-site.com">
        </div>
        <div class="form-group settings-formgroup-last">
          <label>Bio</label>
          <textarea class="form-control" name="bio" rows="5" placeholder="Tell readers about yourself"><?= Helper::sanitize($user['bio']) ?></textarea>
        </div>
      </div>

      <div class="sidebar-widget settings-card">
        <div class="widget-title"><i class="fa fa-lock"></i> Security</div>
        <div class="form-group">
          <label>New Password</label>
          <input class="form-control" type="password" name="password" placeholder="Leave blank to keep current password" minlength="8">
        </div>
        <div class="settings-splitgrid">
          <div class="form-group">
            <label>Role</label>
            <input class="form-control" value="<?= Helper::sanitize($user['role_name']) ?>" readonly>
          </div>
          <div class="form-group">
            <label>Status</label>
            <input class="form-control" value="<?= $user['is_active'] ? 'Active' : 'Inactive' ?>" readonly>
          </div>
        </div>
        <div class="settings-actions">
          <button type="submit" class="btn-primary">Save Changes</button>
          <a href="/profile" class="btn-ghost">View Profile</a>
        </div>
      </div>
    </div>
  </form>
</div>
<div class="toast-container" id="toastContainer"></div>
<?php
$appJsPreloaded = true;
$appJsVersion = @filemtime(BASE_PATH . '/public/assets/js/app.js') ?: time();
?>
<script>
const APP = { url:'<?= Helper::appUrl() ?>', csrfToken:'<?= Csrf::token() ?>', isLoggedIn:true, userId:<?= Auth::id() ?> };
</script>
<script src="/public/assets/js/app.js?v=<?= $appJsVersion ?>"></script>
<script>
document.getElementById('settingsAvatarFile').addEventListener('change', async (event) => {
  const file = event.target.files[0];
  if (!file) return;

  const data = await API.upload('/api/upload', file, { dir: 'avatars' });
  if (!data.success) {
    Toast.show(data.error || 'Upload failed', 'error');
    event.target.value = '';
    return;
  }

  document.getElementById('settingsAvatarInput').value = data.filename;
  document.getElementById('settingsAvatarPreview').src = data.url;
  document.getElementById('settingsAvatarRemove').style.display = 'inline-flex';
  Toast.show('Profile image uploaded', 'success');
  event.target.value = '';
});

document.getElementById('settingsAvatarRemove').addEventListener('click', () => {
  document.getElementById('settingsAvatarInput').value = '';
  document.getElementById('settingsAvatarPreview').src = '<?= Helper::avatarUrl(null) ?>';
  document.getElementById('settingsAvatarRemove').style.display = 'none';
});

document.getElementById('accountSettingsForm').addEventListener('submit', async (event) => {
  event.preventDefault();

  const formData = new FormData(event.currentTarget);
  const payload = {
    action: 'update_profile',
    full_name: formData.get('full_name'),
    phone: formData.get('phone'),
    location: formData.get('location'),
    website: formData.get('website'),
    bio: formData.get('bio'),
    avatar: formData.get('avatar'),
    password: formData.get('password')
  };

  const data = await API.post('/api/account/profile', payload);
  if (!data.success) {
    Toast.show(data.error || 'Save failed', 'error');
    return;
  }

  Toast.show(data.message || 'Profile updated', 'success');
  setTimeout(() => window.location.reload(), 700);
});
</script>
<?php include VIEW . 'layouts/footer.php'; ?>
