<?php
require_once BASE_PATH . '/includes/bootstrap.php';

Auth::requireRole('super_admin', 'admin', 'hr');

$pageTitle = 'Departments - FatakNews';
$db = Database::getInstance();
$editId = max(0, (int)($_GET['edit'] ?? 0));
$query = trim((string)($_GET['q'] ?? ''));

$where = [];
$params = [];
if ($query !== '') {
    $where[] = '(d.name LIKE ? OR d.description LIKE ? OR u.full_name LIKE ?)';
    $like = '%' . $query . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$departments = $db->fetchAll(
    "SELECT d.id, d.name, d.head_id, d.description, d.created_at,
            u.full_name AS head_name,
            COUNT(ep.user_id) AS employee_count
     FROM departments d
     LEFT JOIN users u ON u.id=d.head_id
     LEFT JOIN employee_profiles ep ON ep.department_id=d.id
     $whereSql
     GROUP BY d.id, d.name, d.head_id, d.description, d.created_at, u.full_name
     ORDER BY d.name ASC",
    $params
);

$editDepartment = $editId > 0 ? $db->fetchOne("SELECT * FROM departments WHERE id=?", [$editId]) : null;
$headOptions = $db->fetchAll(
    "SELECT u.id, u.full_name, ep.employee_code, d.name AS dept_name
     FROM employee_profiles ep
     JOIN users u ON u.id=ep.user_id
     LEFT JOIN departments d ON d.id=ep.department_id
     WHERE ep.is_active=1
     ORDER BY u.full_name ASC"
);

$stats = [
    'total' => count($departments),
    'staff' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM employee_profiles")['c'] ?? 0),
    'heads_assigned' => count(array_filter($departments, fn($row) => !empty($row['head_id']))),
    'avg_team_size' => count($departments) > 0
        ? round(array_sum(array_map(fn($row) => (int)$row['employee_count'], $departments)) / count($departments), 1)
        : 0,
];

$defaultDepartment = [
    'id' => 0,
    'name' => '',
    'head_id' => '',
    'description' => '',
];
$formDepartment = array_merge($defaultDepartment, $editDepartment ?? []);
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
      <div style="font-size:11px;color:#00BCD4;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-top:6px">HR Panel</div>
    </div>
    <nav class="panel-nav">
      <div class="panel-nav-section">HR Overview</div>
      <a href="/hr" class="panel-nav-link"><i class="fa fa-th-large"></i> Dashboard</a>
      <div class="panel-nav-section">People</div>
      <a href="/hr/employees" class="panel-nav-link"><i class="fa fa-users"></i> Employees</a>
      <a href="/hr/departments" class="panel-nav-link active"><i class="fa fa-sitemap"></i> Departments</a>
      <div class="panel-nav-section">Attendance</div>
      <a href="/hr/attendance" class="panel-nav-link"><i class="fa fa-clock"></i> Attendance</a>
      <a href="/hr/leaves" class="panel-nav-link"><i class="fa fa-calendar-times"></i> Leave Requests</a>
      <div class="panel-nav-section">Payroll</div>
      <a href="/hr/payroll" class="panel-nav-link"><i class="fa fa-money-bill-wave"></i> Payroll</a>
      <div class="panel-nav-section">System</div>
      <a href="/admin" class="panel-nav-link"><i class="fa fa-shield-alt"></i> Admin Panel</a>
      <a href="/" class="panel-nav-link" target="_blank"><i class="fa fa-external-link-alt"></i> View Site</a>
      <a href="/logout" class="panel-nav-link" style="color:var(--red)"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </nav>
  </aside>

  <main class="panel-main">
    <div class="panel-header">
      <div>
        <h1>Departments</h1>
        <p style="color:var(--muted);font-size:13px;margin-top:2px">Structure teams, assign department heads, and track staffing at a glance.</p>
      </div>
      <div style="display:flex;gap:10px;align-items:center">
        <?php if ($editDepartment): ?>
        <a href="/hr/departments" class="btn-sm btn-edit">New Department</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,188,212,0.15);color:#00BCD4"><i class="fa fa-sitemap"></i></div>
        <div class="stat-info"><strong><?= $stats['total'] ?></strong><span>Total Departments</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(41,121,255,0.15);color:var(--blue)"><i class="fa fa-users"></i></div>
        <div class="stat-info"><strong><?= $stats['staff'] ?></strong><span>Mapped Employees</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,200,83,0.15);color:var(--green)"><i class="fa fa-user-tie"></i></div>
        <div class="stat-info"><strong><?= $stats['heads_assigned'] ?></strong><span>Heads Assigned</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,215,0,0.15);color:var(--yellow)"><i class="fa fa-layer-group"></i></div>
        <div class="stat-info"><strong><?= $stats['avg_team_size'] ?></strong><span>Avg Team Size</span></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:minmax(340px,430px) 1fr;gap:20px;align-items:start">
      <div class="data-table-wrap">
        <div class="table-header">
          <h3><?= $editDepartment ? 'Edit Department' : 'Create Department' ?></h3>
        </div>
        <form id="departmentForm" style="padding:20px;display:flex;flex-direction:column;gap:14px">
          <input type="hidden" name="department_id" value="<?= (int)$formDepartment['id'] ?>">
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Department Name</label>
            <input type="text" name="name" class="form-control" value="<?= Helper::sanitize($formDepartment['name']) ?>" required>
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Department Head</label>
            <select name="head_id" class="form-control">
              <option value="">No head assigned</option>
              <?php foreach ($headOptions as $head): ?>
              <option value="<?= (int)$head['id'] ?>" <?= (string)$formDepartment['head_id'] === (string)$head['id'] ? 'selected' : '' ?>>
                <?= Helper::sanitize($head['full_name']) ?> (<?= Helper::sanitize($head['employee_code']) ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Description</label>
            <textarea name="description" class="form-control" rows="5"><?= Helper::sanitize($formDepartment['description']) ?></textarea>
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button type="submit" class="btn-sm btn-approve"><?= $editDepartment ? 'Update Department' : 'Create Department' ?></button>
            <?php if ($editDepartment): ?>
            <a href="/hr/departments" class="btn-sm btn-edit">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div class="data-table-wrap">
        <div class="table-header" style="gap:14px;flex-wrap:wrap">
          <h3>Department Directory</h3>
          <form method="get" action="/hr/departments" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <input type="text" name="q" value="<?= Helper::sanitize($query) ?>" class="form-control" placeholder="Search department or head..." style="width:260px">
            <button type="submit" class="btn-sm btn-approve">Apply</button>
            <a href="/hr/departments" class="btn-sm btn-edit">Reset</a>
          </form>
        </div>
        <div style="overflow-x:auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Department</th>
                <th>Head</th>
                <th>Employees</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($departments as $department): ?>
              <tr>
                <td style="max-width:320px">
                  <div style="font-weight:700"><?= Helper::sanitize($department['name']) ?></div>
                  <div style="font-size:12px;color:var(--muted);margin-top:4px"><?= Helper::sanitize($department['description'] ?: 'No description added yet.') ?></div>
                </td>
                <td style="font-size:13px"><?= Helper::sanitize($department['head_name'] ?? 'Unassigned') ?></td>
                <td style="font-size:13px;font-weight:700"><?= Helper::formatNumber((int)$department['employee_count']) ?></td>
                <td style="font-size:12px;color:var(--muted)">
                  <div><?= date('d M Y', strtotime($department['created_at'])) ?></div>
                  <div><?= Helper::timeAgo($department['created_at']) ?></div>
                </td>
                <td>
                  <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <a href="/hr/departments?edit=<?= (int)$department['id'] ?>" class="btn-sm btn-edit">Edit</a>
                    <button class="btn-sm btn-delete" data-confirm="Delete this department?" onclick="departmentAction('delete', <?= (int)$department['id'] ?>)">Delete</button>
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
document.getElementById('departmentForm').addEventListener('submit', async (event) => {
  event.preventDefault();

  const form = event.currentTarget;
  const formData = new FormData(form);
  const departmentId = formData.get('department_id');
  const payload = {
    action: departmentId && departmentId !== '0' ? 'update' : 'create',
    department_id: Number(departmentId || 0),
    name: formData.get('name'),
    head_id: formData.get('head_id'),
    description: formData.get('description')
  };

  const data = await API.post('/api/hr/departments', payload);
  if (!data.success) {
    Toast.show(data.error || 'Action failed', 'error');
    return;
  }

  Toast.show(data.message || 'Saved', 'success');
  setTimeout(() => window.location.href = '/hr/departments', 700);
});

async function departmentAction(action, departmentId) {
  const data = await API.post('/api/hr/departments', { action, department_id: departmentId });
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
