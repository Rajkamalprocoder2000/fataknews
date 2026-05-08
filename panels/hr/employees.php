<?php
require_once BASE_PATH . '/includes/bootstrap.php';

Auth::requireRole('super_admin', 'admin', 'hr');

$pageTitle = 'Employees - FatakNews';
$db = Database::getInstance();
$editId = max(0, (int)($_GET['edit'] ?? 0));
$deptFilter = (int)($_GET['department'] ?? 0);
$statusFilter = trim((string)($_GET['status'] ?? ''));
$query = trim((string)($_GET['q'] ?? ''));

$departments = $db->fetchAll("SELECT id, name FROM departments ORDER BY name ASC");
$staffRoles = $db->fetchAll("SELECT id, slug, name FROM roles WHERE slug IN ('manager','editor','reporter','hr') ORDER BY id ASC");

$where = [];
$params = [];
if ($deptFilter > 0) {
    $where[] = 'ep.department_id=?';
    $params[] = $deptFilter;
}
if ($statusFilter === 'active') {
    $where[] = 'ep.is_active=1';
}
if ($statusFilter === 'inactive') {
    $where[] = 'ep.is_active=0';
}
if ($query !== '') {
    $where[] = '(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR ep.employee_code LIKE ? OR ep.designation LIKE ?)';
    $like = '%' . $query . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$employees = $db->fetchAll(
    "SELECT u.id, u.full_name, u.username, u.email, u.avatar, u.phone, u.is_active, u.is_verified, u.badge_level,
            ep.department_id, ep.designation, ep.employee_code, ep.joining_date, ep.salary,
            ep.reporting_to, ep.is_active AS employee_active,
            d.name AS dept_name, r.name AS role_name, r.slug AS role_slug,
            manager.full_name AS reporting_name
     FROM employee_profiles ep
     JOIN users u ON u.id=ep.user_id
     JOIN roles r ON r.id=u.role_id
     LEFT JOIN departments d ON d.id=ep.department_id
     LEFT JOIN users manager ON manager.id=ep.reporting_to
     $whereSql
     ORDER BY ep.is_active DESC, d.name ASC, u.full_name ASC",
    $params
);

$managerOptions = $db->fetchAll(
    "SELECT u.id, u.full_name, ep.employee_code
     FROM employee_profiles ep
     JOIN users u ON u.id=ep.user_id
     WHERE ep.is_active=1
     ORDER BY u.full_name ASC"
);

$editEmployee = $editId > 0
    ? $db->fetchOne(
        "SELECT u.id, u.role_id, u.full_name, u.username, u.email, u.phone, u.location, u.website, u.bio,
                u.is_active, u.is_verified, u.email_verified, u.badge_level,
                ep.department_id, ep.designation, ep.employee_code, ep.joining_date, ep.salary,
                ep.bank_account, ep.pan_number, ep.aadhar_number, ep.address, ep.emergency_contact, ep.reporting_to
         FROM employee_profiles ep
         JOIN users u ON u.id=ep.user_id
         WHERE u.id=?",
        [$editId]
    )
    : null;

$stats = [
    'total' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM employee_profiles")['c'] ?? 0),
    'active' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM employee_profiles WHERE is_active=1")['c'] ?? 0),
    'departments' => (int)($db->fetchOne("SELECT COUNT(*) AS c FROM departments")['c'] ?? 0),
    'payroll_base' => (float)($db->fetchOne("SELECT COALESCE(SUM(salary),0) AS c FROM employee_profiles WHERE is_active=1")['c'] ?? 0),
];

$defaultEmployee = [
    'id' => 0,
    'role_id' => 5,
    'full_name' => '',
    'username' => '',
    'email' => '',
    'phone' => '',
    'location' => '',
    'website' => '',
    'bio' => '',
    'is_active' => 1,
    'is_verified' => 0,
    'email_verified' => 1,
    'badge_level' => 'press',
    'department_id' => '',
    'designation' => '',
    'employee_code' => '',
    'joining_date' => '',
    'salary' => '',
    'bank_account' => '',
    'pan_number' => '',
    'aadhar_number' => '',
    'address' => '',
    'emergency_contact' => '',
    'reporting_to' => '',
];

$formEmployee = array_merge($defaultEmployee, $editEmployee ?? []);

function hrEmployeeFilterUrl(array $overrides = []): string {
    $query = array_merge($_GET, $overrides);
    $query = array_filter($query, fn($value) => $value !== '' && $value !== null);
    return '/hr/employees' . ($query ? '?' . http_build_query($query) : '');
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
      <div style="font-size:11px;color:#00BCD4;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-top:6px">HR Panel</div>
    </div>
    <nav class="panel-nav">
      <div class="panel-nav-section">HR Overview</div>
      <a href="/hr" class="panel-nav-link"><i class="fa fa-th-large"></i> Dashboard</a>
      <div class="panel-nav-section">People</div>
      <a href="/hr/employees" class="panel-nav-link active"><i class="fa fa-users"></i> Employees</a>
      <a href="/hr/departments" class="panel-nav-link"><i class="fa fa-sitemap"></i> Departments</a>
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
        <h1>Employees</h1>
        <p style="color:var(--muted);font-size:13px;margin-top:2px">Maintain employee records, reporting lines, and core HR profile details.</p>
      </div>
      <div style="display:flex;gap:10px;align-items:center">
        <?php if ($editEmployee): ?>
        <a href="/hr/employees" class="btn-sm btn-edit">New Employee</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,188,212,0.15);color:#00BCD4"><i class="fa fa-users"></i></div>
        <div class="stat-info"><strong><?= $stats['total'] ?></strong><span>Total Employee Profiles</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,200,83,0.15);color:var(--green)"><i class="fa fa-user-check"></i></div>
        <div class="stat-info"><strong><?= $stats['active'] ?></strong><span>Active Employees</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,215,0,0.15);color:var(--yellow)"><i class="fa fa-sitemap"></i></div>
        <div class="stat-info"><strong><?= $stats['departments'] ?></strong><span>Departments</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(41,121,255,0.15);color:var(--blue)"><i class="fa fa-money-bill-wave"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber((int)$stats['payroll_base']) ?></strong><span>Approx. Salary Base</span></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:minmax(360px,450px) 1fr;gap:20px;align-items:start">
      <div class="data-table-wrap">
        <div class="table-header">
          <h3><?= $editEmployee ? 'Edit Employee' : 'Add Employee' ?></h3>
        </div>
        <form id="employeeForm" style="padding:20px;display:flex;flex-direction:column;gap:14px">
          <input type="hidden" name="user_id" value="<?= (int)$formEmployee['id'] ?>">
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Full Name</label>
            <input type="text" name="full_name" class="form-control" value="<?= Helper::sanitize($formEmployee['full_name']) ?>" required>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Username</label>
              <input type="text" name="username" class="form-control" value="<?= Helper::sanitize($formEmployee['username']) ?>" required>
            </div>
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Email</label>
              <input type="email" name="email" class="form-control" value="<?= Helper::sanitize($formEmployee['email']) ?>" required>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Role</label>
              <select name="role_id" class="form-control">
                <?php foreach ($staffRoles as $role): ?>
                <option value="<?= (int)$role['id'] ?>" <?= (int)$formEmployee['role_id'] === (int)$role['id'] ? 'selected' : '' ?>><?= Helper::sanitize($role['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Department</label>
              <select name="department_id" class="form-control">
                <option value="">Select department</option>
                <?php foreach ($departments as $department): ?>
                <option value="<?= (int)$department['id'] ?>" <?= (string)$formEmployee['department_id'] === (string)$department['id'] ? 'selected' : '' ?>><?= Helper::sanitize($department['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Designation</label>
              <input type="text" name="designation" class="form-control" value="<?= Helper::sanitize($formEmployee['designation']) ?>" required>
            </div>
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Employee Code</label>
              <input type="text" name="employee_code" class="form-control" value="<?= Helper::sanitize($formEmployee['employee_code']) ?>" required>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Joining Date</label>
              <input type="date" name="joining_date" class="form-control" value="<?= Helper::sanitize($formEmployee['joining_date']) ?>">
            </div>
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Salary</label>
              <input type="number" step="0.01" name="salary" class="form-control" value="<?= Helper::sanitize((string)$formEmployee['salary']) ?>">
            </div>
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Password <?= $editEmployee ? '(leave blank to keep current)' : '' ?></label>
            <input type="password" name="password" class="form-control" <?= $editEmployee ? '' : 'required' ?>>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Phone</label>
              <input type="text" name="phone" class="form-control" value="<?= Helper::sanitize($formEmployee['phone']) ?>">
            </div>
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Emergency Contact</label>
              <input type="text" name="emergency_contact" class="form-control" value="<?= Helper::sanitize($formEmployee['emergency_contact']) ?>">
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Location</label>
              <input type="text" name="location" class="form-control" value="<?= Helper::sanitize($formEmployee['location']) ?>">
            </div>
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Reporting To</label>
              <select name="reporting_to" class="form-control">
                <option value="">No manager</option>
                <?php foreach ($managerOptions as $manager): ?>
                <?php if ((int)$manager['id'] === (int)$formEmployee['id']) continue; ?>
                <option value="<?= (int)$manager['id'] ?>" <?= (string)$formEmployee['reporting_to'] === (string)$manager['id'] ? 'selected' : '' ?>>
                  <?= Helper::sanitize($manager['full_name']) ?> (<?= Helper::sanitize($manager['employee_code']) ?>)
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Bank Account</label>
              <input type="text" name="bank_account" class="form-control" value="<?= Helper::sanitize($formEmployee['bank_account']) ?>">
            </div>
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">PAN</label>
              <input type="text" name="pan_number" class="form-control" value="<?= Helper::sanitize($formEmployee['pan_number']) ?>">
            </div>
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Aadhar</label>
            <input type="text" name="aadhar_number" class="form-control" value="<?= Helper::sanitize($formEmployee['aadhar_number']) ?>">
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Address</label>
            <textarea name="address" class="form-control" rows="3"><?= Helper::sanitize($formEmployee['address']) ?></textarea>
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Bio</label>
            <textarea name="bio" class="form-control" rows="3"><?= Helper::sanitize($formEmployee['bio']) ?></textarea>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <label style="display:flex;gap:10px;align-items:center;font-size:13px;color:var(--text)">
              <input type="checkbox" name="is_active" value="1" <?= !empty($formEmployee['is_active']) ? 'checked' : '' ?>>
              Active
            </label>
            <label style="display:flex;gap:10px;align-items:center;font-size:13px;color:var(--text)">
              <input type="checkbox" name="is_verified" value="1" <?= !empty($formEmployee['is_verified']) ? 'checked' : '' ?>>
              Verified
            </label>
          </div>
          <label style="display:flex;gap:10px;align-items:center;font-size:13px;color:var(--text)">
            <input type="checkbox" name="email_verified" value="1" <?= !empty($formEmployee['email_verified']) ? 'checked' : '' ?>>
            Email Verified
          </label>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button type="submit" class="btn-sm btn-approve"><?= $editEmployee ? 'Update Employee' : 'Create Employee' ?></button>
            <?php if ($editEmployee): ?>
            <a href="/hr/employees" class="btn-sm btn-edit">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div class="data-table-wrap">
        <div class="table-header" style="gap:14px;flex-wrap:wrap">
          <h3>Employee Directory</h3>
          <form method="get" action="/hr/employees" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <input type="text" name="q" value="<?= Helper::sanitize($query) ?>" class="form-control" placeholder="Search employee, code, email..." style="width:240px">
            <select name="department" class="form-control" style="width:190px">
              <option value="">All departments</option>
              <?php foreach ($departments as $department): ?>
              <option value="<?= (int)$department['id'] ?>" <?= $deptFilter === (int)$department['id'] ? 'selected' : '' ?>><?= Helper::sanitize($department['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <select name="status" class="form-control" style="width:150px">
              <option value="">Any status</option>
              <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
              <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <button type="submit" class="btn-sm btn-approve">Apply</button>
            <a href="/hr/employees" class="btn-sm btn-edit">Reset</a>
          </form>
        </div>
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap">
          <a href="<?= hrEmployeeFilterUrl(['department' => null, 'status' => null, 'q' => null]) ?>" class="tag-chip" style="<?= $deptFilter === 0 && $statusFilter === '' && $query === '' ? 'background:rgba(0,188,212,0.14);color:#00BCD4' : '' ?>">All</a>
          <a href="<?= hrEmployeeFilterUrl(['status' => 'active']) ?>" class="tag-chip" style="<?= $statusFilter === 'active' ? 'background:rgba(0,200,83,0.14);color:var(--green)' : '' ?>">Active</a>
          <a href="<?= hrEmployeeFilterUrl(['status' => 'inactive']) ?>" class="tag-chip" style="<?= $statusFilter === 'inactive' ? 'background:rgba(255,45,45,0.14);color:var(--red)' : '' ?>">Inactive</a>
        </div>
        <div style="overflow-x:auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Employee</th>
                <th>Department</th>
                <th>Designation</th>
                <th>Manager</th>
                <th>Salary</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($employees as $employee): ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:12px">
                    <img src="<?= Helper::avatarUrl($employee['avatar'] ?? null) ?>" style="width:38px;height:38px;border-radius:50%;object-fit:cover">
                    <div>
                      <div style="font-weight:700"><?= Helper::sanitize($employee['full_name']) ?></div>
                      <div style="font-size:12px;color:var(--muted)"><?= Helper::sanitize($employee['employee_code']) ?> · @<?= Helper::sanitize($employee['username']) ?></div>
                    </div>
                  </div>
                </td>
                <td style="font-size:13px"><?= Helper::sanitize($employee['dept_name'] ?? 'Unassigned') ?></td>
                <td style="font-size:13px">
                  <div><?= Helper::sanitize($employee['designation']) ?></div>
                  <div style="font-size:12px;color:var(--muted)"><?= Helper::sanitize($employee['role_name']) ?></div>
                </td>
                <td style="font-size:13px"><?= Helper::sanitize($employee['reporting_name'] ?? 'Independent') ?></td>
                <td style="font-size:13px"><?= $employee['salary'] !== null ? 'Rs. ' . number_format((float)$employee['salary'], 0) : 'N/A' ?></td>
                <td>
                  <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <span class="status-badge <?= (int)$employee['employee_active'] === 1 ? 'status-published' : 'status-rejected' ?>">
                      <?= (int)$employee['employee_active'] === 1 ? 'Active' : 'Inactive' ?>
                    </span>
                    <?php if ((int)$employee['is_verified'] === 1): ?>
                    <span class="status-badge status-pending">Verified</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <a href="/hr/employees?edit=<?= (int)$employee['id'] ?>" class="btn-sm btn-edit">Edit</a>
                    <button class="btn-sm <?= (int)$employee['employee_active'] === 1 ? 'btn-reject' : 'btn-approve' ?>" onclick="employeeAction('toggle_active', <?= (int)$employee['id'] ?>)">
                      <?= (int)$employee['employee_active'] === 1 ? 'Disable' : 'Activate' ?>
                    </button>
                    <button class="btn-sm btn-delete" data-confirm="Delete this employee profile?" onclick="employeeAction('delete', <?= (int)$employee['id'] ?>)">Delete</button>
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
document.getElementById('employeeForm').addEventListener('submit', async (event) => {
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
    role_id: Number(formData.get('role_id') || 5),
    department_id: formData.get('department_id'),
    designation: formData.get('designation'),
    employee_code: formData.get('employee_code'),
    joining_date: formData.get('joining_date'),
    salary: formData.get('salary'),
    password: formData.get('password'),
    phone: formData.get('phone'),
    emergency_contact: formData.get('emergency_contact'),
    location: formData.get('location'),
    reporting_to: formData.get('reporting_to'),
    bank_account: formData.get('bank_account'),
    pan_number: formData.get('pan_number'),
    aadhar_number: formData.get('aadhar_number'),
    address: formData.get('address'),
    bio: formData.get('bio'),
    is_active: formData.get('is_active') ? 1 : 0,
    is_verified: formData.get('is_verified') ? 1 : 0,
    email_verified: formData.get('email_verified') ? 1 : 0,
    badge_level: 'press'
  };

  const data = await API.post('/api/hr/employees', payload);
  if (!data.success) {
    Toast.show(data.error || 'Action failed', 'error');
    return;
  }

  Toast.show(data.message || 'Saved', 'success');
  setTimeout(() => window.location.href = '/hr/employees', 700);
});

async function employeeAction(action, userId) {
  const data = await API.post('/api/hr/employees', { action, user_id: userId });
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
