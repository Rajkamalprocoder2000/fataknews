<?php
require_once BASE_PATH . '/includes/bootstrap.php';

Auth::requireRole('super_admin', 'admin');

$pageTitle = 'Category Management - FatakNews';
$db = Database::getInstance();
$categoryModel = new CategoryModel();
$editId = max(0, (int)($_GET['edit'] ?? 0));

$categories = $db->fetchAll(
    "SELECT c.*,
            p.name AS parent_name,
            (SELECT COUNT(*) FROM posts px WHERE px.category_id=c.id) AS actual_posts_count,
            (SELECT COUNT(*) FROM categories cx WHERE cx.parent_id=c.id) AS child_count
     FROM categories c
     LEFT JOIN categories p ON c.parent_id=p.id
     ORDER BY c.level ASC, c.sort_order ASC, c.name ASC"
);

$editCategory = $editId > 0 ? $db->fetchOne("SELECT * FROM categories WHERE id=?", [$editId]) : null;
$parentOptions = $db->fetchAll(
    "SELECT id, name, level FROM categories
     WHERE id <> ?
     ORDER BY level ASC, sort_order ASC, name ASC",
    [$editId]
);

$stats = [
    'total' => count($categories),
    'active' => count(array_filter($categories, fn($row) => (int)$row['is_active'] === 1)),
    'featured' => count(array_filter($categories, fn($row) => (int)$row['is_featured'] === 1)),
    'top_level' => count(array_filter($categories, fn($row) => empty($row['parent_id']))),
];

$defaultForm = [
    'id' => 0,
    'parent_id' => '',
    'name' => '',
    'slug' => '',
    'description' => '',
    'icon' => 'fa-folder',
    'color' => '#FF2D2D',
    'sort_order' => 0,
    'is_active' => 1,
    'is_featured' => 0,
];

$formCategory = array_merge($defaultForm, $editCategory ?? []);
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
      <a href="/admin/categories" class="panel-nav-link active"><i class="fa fa-folder"></i> Categories</a>
      <a href="/admin/ads" class="panel-nav-link"><i class="fa fa-ad"></i> Advertisements</a>
      <div class="panel-nav-section">Users</div>
      <a href="/admin/users" class="panel-nav-link"><i class="fa fa-users"></i> All Users</a>
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
        <h1>Category Management</h1>
        <p style="color:var(--muted);font-size:13px;margin-top:2px">Create, organize, and manage topic hierarchy for the newsroom.</p>
      </div>
      <div style="display:flex;gap:10px;align-items:center">
        <?php if ($editCategory): ?>
        <a href="/admin/categories" class="btn-sm btn-edit">New Category</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,45,45,0.15);color:var(--red)"><i class="fa fa-folder-tree"></i></div>
        <div class="stat-info"><strong><?= $stats['total'] ?></strong><span>Total Categories</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,200,83,0.15);color:var(--green)"><i class="fa fa-toggle-on"></i></div>
        <div class="stat-info"><strong><?= $stats['active'] ?></strong><span>Active</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(41,121,255,0.15);color:var(--blue)"><i class="fa fa-star"></i></div>
        <div class="stat-info"><strong><?= $stats['featured'] ?></strong><span>Featured</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,215,0,0.15);color:var(--yellow)"><i class="fa fa-layer-group"></i></div>
        <div class="stat-info"><strong><?= $stats['top_level'] ?></strong><span>Top Level</span></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:minmax(320px,420px) 1fr;gap:20px;align-items:start">
      <div class="data-table-wrap">
        <div class="table-header">
          <h3><?= $editCategory ? 'Edit Category' : 'Create Category' ?></h3>
        </div>
        <form id="categoryForm" style="padding:20px;display:flex;flex-direction:column;gap:14px">
          <input type="hidden" name="category_id" value="<?= (int)$formCategory['id'] ?>">
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Name</label>
            <input type="text" name="name" class="form-control" value="<?= Helper::sanitize($formCategory['name']) ?>" required>
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Slug</label>
            <input type="text" name="slug" class="form-control" value="<?= Helper::sanitize($formCategory['slug']) ?>" placeholder="auto-generated-from-name">
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Parent</label>
            <select name="parent_id" class="form-control">
              <option value="">Top Level Category</option>
              <?php foreach ($parentOptions as $parent): ?>
              <option value="<?= (int)$parent['id'] ?>" <?= (string)$formCategory['parent_id'] === (string)$parent['id'] ? 'selected' : '' ?>>
                <?= str_repeat('- ', max(0, (int)$parent['level'] - 1)) . Helper::sanitize($parent['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Description</label>
            <textarea name="description" class="form-control" rows="4"><?= Helper::sanitize($formCategory['description']) ?></textarea>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Icon</label>
              <input type="text" name="icon" class="form-control" value="<?= Helper::sanitize($formCategory['icon']) ?>" placeholder="fa-folder">
            </div>
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Color</label>
              <input type="text" name="color" class="form-control" value="<?= Helper::sanitize($formCategory['color']) ?>" placeholder="#FF2D2D">
            </div>
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Sort Order</label>
            <input type="number" name="sort_order" class="form-control" value="<?= (int)$formCategory['sort_order'] ?>" min="0">
          </div>
          <label style="display:flex;gap:10px;align-items:center;font-size:13px;color:var(--text)">
            <input type="checkbox" name="is_active" value="1" <?= !empty($formCategory['is_active']) ? 'checked' : '' ?>>
            Active
          </label>
          <label style="display:flex;gap:10px;align-items:center;font-size:13px;color:var(--text)">
            <input type="checkbox" name="is_featured" value="1" <?= !empty($formCategory['is_featured']) ? 'checked' : '' ?>>
            Featured
          </label>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button type="submit" class="btn-sm btn-approve"><?= $editCategory ? 'Update Category' : 'Create Category' ?></button>
            <?php if ($editCategory): ?>
            <a href="/admin/categories" class="btn-sm btn-edit">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div class="data-table-wrap">
        <div class="table-header">
          <h3>All Categories</h3>
          <span style="font-size:13px;color:var(--muted)"><?= $stats['total'] ?> records</span>
        </div>
        <div style="overflow-x:auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Category</th>
                <th>Parent</th>
                <th>Level</th>
                <th>Status</th>
                <th>Posts</th>
                <th>Order</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($categories as $category): ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:12px">
                    <span style="width:34px;height:34px;border-radius:10px;background:<?= Helper::sanitize($category['color']) ?>22;color:<?= Helper::sanitize($category['color']) ?>;display:flex;align-items:center;justify-content:center">
                      <i class="fa <?= Helper::sanitize($category['icon'] ?: 'fa-folder') ?>"></i>
                    </span>
                    <div>
                      <div style="font-weight:700"><?= str_repeat('- ', max(0, (int)$category['level'] - 1)) . Helper::sanitize($category['name']) ?></div>
                      <div style="font-size:12px;color:var(--muted)">/category/<?= Helper::sanitize($category['slug']) ?></div>
                    </div>
                  </div>
                </td>
                <td style="font-size:13px"><?= Helper::sanitize($category['parent_name'] ?? 'Top Level') ?></td>
                <td><span class="status-badge status-draft">L<?= (int)$category['level'] ?></span></td>
                <td>
                  <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <span class="status-badge <?= (int)$category['is_active'] === 1 ? 'status-published' : 'status-rejected' ?>">
                      <?= (int)$category['is_active'] === 1 ? 'Active' : 'Disabled' ?>
                    </span>
                    <?php if ((int)$category['is_featured'] === 1): ?>
                    <span class="status-badge status-pending">Featured</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td style="font-size:13px;color:var(--muted)">
                  <div><?= Helper::formatNumber((int)$category['actual_posts_count']) ?> posts</div>
                  <div><?= Helper::formatNumber((int)$category['child_count']) ?> children</div>
                </td>
                <td><?= (int)$category['sort_order'] ?></td>
                <td>
                  <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <a href="/admin/categories?edit=<?= (int)$category['id'] ?>" class="btn-sm btn-edit">Edit</a>
                    <button class="btn-sm btn-approve" onclick="categoryAction('toggle_featured', <?= (int)$category['id'] ?>)">
                      <?= (int)$category['is_featured'] === 1 ? 'Unfeature' : 'Feature' ?>
                    </button>
                    <button class="btn-sm <?= (int)$category['is_active'] === 1 ? 'btn-reject' : 'btn-approve' ?>" onclick="categoryAction('toggle_active', <?= (int)$category['id'] ?>)">
                      <?= (int)$category['is_active'] === 1 ? 'Disable' : 'Enable' ?>
                    </button>
                    <button class="btn-sm btn-delete" data-confirm="Delete this category?" onclick="categoryAction('delete', <?= (int)$category['id'] ?>)">Delete</button>
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
document.getElementById('categoryForm').addEventListener('submit', async (event) => {
  event.preventDefault();

  const form = event.currentTarget;
  const formData = new FormData(form);
  const categoryId = formData.get('category_id');
  const payload = {
    action: categoryId && categoryId !== '0' ? 'update' : 'create',
    category_id: Number(categoryId || 0),
    name: formData.get('name'),
    slug: formData.get('slug'),
    parent_id: formData.get('parent_id'),
    description: formData.get('description'),
    icon: formData.get('icon'),
    color: formData.get('color'),
    sort_order: Number(formData.get('sort_order') || 0),
    is_active: formData.get('is_active') ? 1 : 0,
    is_featured: formData.get('is_featured') ? 1 : 0
  };

  const data = await API.post('/api/admin/categories', payload);
  if (!data.success) {
    Toast.show(data.error || 'Action failed', 'error');
    return;
  }

  Toast.show(data.message || 'Saved', 'success');
  setTimeout(() => window.location.href = '/admin/categories', 700);
});

async function categoryAction(action, categoryId) {
  const data = await API.post('/api/admin/categories', { action, category_id: categoryId });
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
