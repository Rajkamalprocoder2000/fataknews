<?php
require_once BASE_PATH . '/includes/bootstrap.php';

Auth::requireRole('super_admin', 'admin', 'hr');

$pageTitle = 'Payroll - FatakNews';
$db = Database::getInstance();
$month = max(1, min(12, (int)($_GET['month'] ?? date('n'))));
$year = max(2020, min(2100, (int)($_GET['year'] ?? date('Y'))));
$editId = max(0, (int)($_GET['edit'] ?? 0));
$query = trim((string)($_GET['q'] ?? ''));

$employeeOptions = $db->fetchAll(
    "SELECT ep.user_id, u.full_name, ep.employee_code, ep.salary
     FROM employee_profiles ep
     JOIN users u ON u.id=ep.user_id
     ORDER BY u.full_name ASC"
);

$where = ['p.month=?', 'p.year=?'];
$params = [$month, $year];
if ($query !== '') {
    $where[] = '(u.full_name LIKE ? OR ep.employee_code LIKE ? OR d.name LIKE ?)';
    $like = '%' . $query . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$records = $db->fetchAll(
    "SELECT p.*, u.full_name, u.avatar, ep.employee_code, ep.salary AS base_salary,
            ep.designation, d.name AS dept_name
     FROM payroll p
     JOIN users u ON u.id=p.user_id
     LEFT JOIN employee_profiles ep ON ep.user_id=u.id
     LEFT JOIN departments d ON d.id=ep.department_id
     $whereSql
     ORDER BY p.paid ASC, u.full_name ASC",
    $params
);

$editRecord = $editId > 0
    ? $db->fetchOne(
        "SELECT p.*, u.full_name, ep.employee_code
         FROM payroll p
         JOIN users u ON u.id=p.user_id
         LEFT JOIN employee_profiles ep ON ep.user_id=u.id
         WHERE p.id=?",
        [$editId]
    )
    : null;

$summary = [
    'records' => count($records),
    'paid' => count(array_filter($records, fn($row) => (int)$row['paid'] === 1)),
    'gross' => array_reduce($records, fn($carry, $row) => $carry + (float)$row['basic'] + (float)$row['hra'] + (float)$row['allowances'], 0.0),
    'net' => array_reduce($records, fn($carry, $row) => $carry + (float)$row['net_salary'], 0.0),
];

$defaultRecord = [
    'id' => 0,
    'user_id' => '',
    'month' => $month,
    'year' => $year,
    'basic' => '',
    'hra' => '',
    'allowances' => '',
    'deductions' => '',
    'pf' => '',
    'tds' => '',
];
$formRecord = array_merge($defaultRecord, $editRecord ?? []);

$monthLabels = [];
$monthlyNet = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime("-$i months");
    $m = (int)date('n', $ts);
    $y = (int)date('Y', $ts);
    $monthLabels[] = date('M Y', $ts);
    $monthlyNet[] = (float)($db->fetchOne(
        "SELECT COALESCE(SUM(net_salary),0) AS c FROM payroll WHERE month=? AND year=?",
        [$m, $y]
    )['c'] ?? 0);
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
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
      <a href="/hr/departments" class="panel-nav-link"><i class="fa fa-sitemap"></i> Departments</a>
      <div class="panel-nav-section">Attendance</div>
      <a href="/hr/attendance" class="panel-nav-link"><i class="fa fa-clock"></i> Attendance</a>
      <a href="/hr/leaves" class="panel-nav-link"><i class="fa fa-calendar-times"></i> Leave Requests</a>
      <div class="panel-nav-section">Payroll</div>
      <a href="/hr/payroll" class="panel-nav-link active"><i class="fa fa-money-bill-wave"></i> Payroll</a>
      <div class="panel-nav-section">System</div>
      <a href="/admin" class="panel-nav-link"><i class="fa fa-shield-alt"></i> Admin Panel</a>
      <a href="/" class="panel-nav-link" target="_blank"><i class="fa fa-external-link-alt"></i> View Site</a>
      <a href="/logout" class="panel-nav-link" style="color:var(--red)"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </nav>
  </aside>

  <main class="panel-main">
    <div class="panel-header">
      <div>
        <h1>Payroll</h1>
        <p style="color:var(--muted);font-size:13px;margin-top:2px">Generate monthly salary sheets, adjust deductions, and mark payouts.</p>
      </div>
      <div style="display:flex;gap:10px;align-items:center">
        <button class="btn-sm btn-approve" onclick="generatePayroll()">Generate Month</button>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,188,212,0.15);color:#00BCD4"><i class="fa fa-file-invoice-dollar"></i></div>
        <div class="stat-info"><strong><?= $summary['records'] ?></strong><span>Payroll Records</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,200,83,0.15);color:var(--green)"><i class="fa fa-check-circle"></i></div>
        <div class="stat-info"><strong><?= $summary['paid'] ?></strong><span>Paid Records</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(41,121,255,0.15);color:var(--blue)"><i class="fa fa-sack-dollar"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber((int)$summary['gross']) ?></strong><span>Gross Outflow</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,215,0,0.15);color:var(--yellow)"><i class="fa fa-wallet"></i></div>
        <div class="stat-info"><strong><?= Helper::formatNumber((int)$summary['net']) ?></strong><span>Net Payable</span></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:minmax(360px,450px) 1fr;gap:20px;align-items:start">
      <div style="display:flex;flex-direction:column;gap:20px">
        <div class="data-table-wrap">
          <div class="table-header"><h3><?= $editRecord ? 'Edit Payroll' : 'Create Payroll' ?></h3></div>
          <form id="payrollForm" style="padding:20px;display:flex;flex-direction:column;gap:14px">
            <input type="hidden" name="payroll_id" value="<?= (int)$formRecord['id'] ?>">
            <div>
              <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Employee</label>
              <select name="user_id" class="form-control" required>
                <option value="">Select employee</option>
                <?php foreach ($employeeOptions as $employee): ?>
                <option value="<?= (int)$employee['user_id'] ?>" <?= (string)$formRecord['user_id'] === (string)$employee['user_id'] ? 'selected' : '' ?>>
                  <?= Helper::sanitize($employee['full_name']) ?> (<?= Helper::sanitize($employee['employee_code']) ?>)
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Month</label>
                <select name="month" class="form-control">
                  <?php for ($m = 1; $m <= 12; $m++): ?>
                  <option value="<?= $m ?>" <?= (int)$formRecord['month'] === $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                  <?php endfor; ?>
                </select>
              </div>
              <div>
                <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Year</label>
                <input type="number" name="year" class="form-control" value="<?= (int)$formRecord['year'] ?>" min="2020" max="2100">
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Basic</label>
                <input type="number" step="0.01" name="basic" class="form-control" value="<?= Helper::sanitize((string)$formRecord['basic']) ?>" required>
              </div>
              <div>
                <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">HRA</label>
                <input type="number" step="0.01" name="hra" class="form-control" value="<?= Helper::sanitize((string)$formRecord['hra']) ?>" required>
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Allowances</label>
                <input type="number" step="0.01" name="allowances" class="form-control" value="<?= Helper::sanitize((string)$formRecord['allowances']) ?>" required>
              </div>
              <div>
                <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">Deductions</label>
                <input type="number" step="0.01" name="deductions" class="form-control" value="<?= Helper::sanitize((string)$formRecord['deductions']) ?>" required>
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">PF</label>
                <input type="number" step="0.01" name="pf" class="form-control" value="<?= Helper::sanitize((string)$formRecord['pf']) ?>" required>
              </div>
              <div>
                <label style="display:block;font-size:12px;color:var(--muted);margin-bottom:8px">TDS</label>
                <input type="number" step="0.01" name="tds" class="form-control" value="<?= Helper::sanitize((string)$formRecord['tds']) ?>" required>
              </div>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
              <button type="submit" class="btn-sm btn-approve"><?= $editRecord ? 'Update Payroll' : 'Save Payroll' ?></button>
              <?php if ($editRecord): ?>
              <a href="/hr/payroll?month=<?= $month ?>&year=<?= $year ?>" class="btn-sm btn-edit">Cancel</a>
              <?php endif; ?>
            </div>
          </form>
        </div>

        <div class="data-table-wrap">
          <div class="table-header"><h3>6-Month Net Salary Trend</h3></div>
          <div style="padding:20px"><canvas id="payrollTrendChart" height="180"></canvas></div>
        </div>
      </div>

      <div class="data-table-wrap">
        <div class="table-header" style="gap:14px;flex-wrap:wrap">
          <h3>Payroll Register</h3>
          <form method="get" action="/hr/payroll" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <select name="month" class="form-control" style="width:160px">
              <?php for ($m = 1; $m <= 12; $m++): ?>
              <option value="<?= $m ?>" <?= $month === $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
              <?php endfor; ?>
            </select>
            <input type="number" name="year" class="form-control" value="<?= $year ?>" min="2020" max="2100" style="width:120px">
            <input type="text" name="q" value="<?= Helper::sanitize($query) ?>" class="form-control" placeholder="Search employee..." style="width:220px">
            <button type="submit" class="btn-sm btn-approve">Apply</button>
            <a href="/hr/payroll?month=<?= date('n') ?>&year=<?= date('Y') ?>" class="btn-sm btn-edit">Reset</a>
          </form>
        </div>
        <div style="overflow-x:auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Employee</th>
                <th>Components</th>
                <th>Net</th>
                <th>Status</th>
                <th>Paid At</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($records)): ?>
              <?php foreach ($records as $record): ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:12px">
                    <img src="<?= Helper::avatarUrl($record['avatar'] ?? null) ?>" style="width:38px;height:38px;border-radius:50%;object-fit:cover">
                    <div>
                      <div style="font-weight:700"><?= Helper::sanitize($record['full_name']) ?></div>
                      <div style="font-size:12px;color:var(--muted)"><?= Helper::sanitize($record['employee_code'] ?? '') ?> · <?= Helper::sanitize($record['dept_name'] ?? 'Unassigned') ?></div>
                    </div>
                  </div>
                </td>
                <td style="font-size:12px;color:var(--muted)">
                  <div>Basic <?= number_format((float)$record['basic'], 0) ?> + HRA <?= number_format((float)$record['hra'], 0) ?></div>
                  <div>Allow <?= number_format((float)$record['allowances'], 0) ?> | Ded <?= number_format((float)$record['deductions'] + (float)$record['pf'] + (float)$record['tds'], 0) ?></div>
                </td>
                <td style="font-size:13px;font-weight:700">Rs. <?= number_format((float)$record['net_salary'], 0) ?></td>
                <td>
                  <span class="status-badge <?= (int)$record['paid'] === 1 ? 'status-published' : 'status-pending' ?>">
                    <?= (int)$record['paid'] === 1 ? 'Paid' : 'Pending' ?>
                  </span>
                </td>
                <td style="font-size:12px;color:var(--muted)"><?= $record['paid_at'] ? Helper::sanitize($record['paid_at']) : 'Not paid yet' ?></td>
                <td>
                  <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <a href="/hr/payroll?month=<?= $month ?>&year=<?= $year ?>&edit=<?= (int)$record['id'] ?>" class="btn-sm btn-edit">Edit</a>
                    <button class="btn-sm <?= (int)$record['paid'] === 1 ? 'btn-edit' : 'btn-approve' ?>" onclick="payrollAction('mark_paid', <?= (int)$record['id'] ?>)">
                      <?= (int)$record['paid'] === 1 ? 'Reset Paid' : 'Mark Paid' ?>
                    </button>
                    <button class="btn-sm btn-delete" data-confirm="Delete this payroll record?" onclick="payrollAction('delete', <?= (int)$record['id'] ?>)">Delete</button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php else: ?>
              <tr>
                <td colspan="6">
                  <div class="empty-state" style="padding:28px 0">
                    <i class="fa fa-money-bill-wave"></i>
                    <h3>No payroll records for this month</h3>
                    <p>Generate the month or create a manual entry from the form.</p>
                  </div>
                </td>
              </tr>
              <?php endif; ?>
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
const payrollTrendCanvas = document.getElementById('payrollTrendChart');
if (payrollTrendCanvas && typeof Chart !== 'undefined') {
  new Chart(payrollTrendCanvas, {
    type: 'line',
    data: {
      labels: <?= json_encode($monthLabels) ?>,
      datasets: [{
        label: 'Net Salary',
        data: <?= json_encode($monthlyNet) ?>,
        borderColor: '#00BCD4',
        backgroundColor: 'rgba(0,188,212,0.12)',
        fill: true,
        tension: 0.35
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { labels: { color: '#F0F0F5' } } },
      scales: {
        x: { ticks: { color: '#A7A7BA' }, grid: { color: 'rgba(255,255,255,0.04)' } },
        y: { ticks: { color: '#A7A7BA' }, grid: { color: 'rgba(255,255,255,0.04)' } }
      }
    }
  });
}

document.getElementById('payrollForm').addEventListener('submit', async (event) => {
  event.preventDefault();

  const form = event.currentTarget;
  const formData = new FormData(form);
  const payload = {
    action: 'upsert',
    payroll_id: Number(formData.get('payroll_id') || 0),
    user_id: Number(formData.get('user_id') || 0),
    month: Number(formData.get('month') || 0),
    year: Number(formData.get('year') || 0),
    basic: Number(formData.get('basic') || 0),
    hra: Number(formData.get('hra') || 0),
    allowances: Number(formData.get('allowances') || 0),
    deductions: Number(formData.get('deductions') || 0),
    pf: Number(formData.get('pf') || 0),
    tds: Number(formData.get('tds') || 0)
  };

  const data = await API.post('/api/hr/payroll', payload);
  if (!data.success) {
    Toast.show(data.error || 'Action failed', 'error');
    return;
  }

  Toast.show(data.message || 'Saved', 'success');
  setTimeout(() => window.location.href = `/hr/payroll?month=${payload.month}&year=${payload.year}`, 700);
});

async function generatePayroll() {
  const data = await API.post('/api/hr/payroll', { action: 'generate_month', month: <?= $month ?>, year: <?= $year ?> });
  if (!data.success) {
    Toast.show(data.error || 'Action failed', 'error');
    return;
  }
  Toast.show(data.message || 'Generated', 'success');
  setTimeout(() => window.location.reload(), 700);
}

async function payrollAction(action, payrollId) {
  const data = await API.post('/api/hr/payroll', { action, payroll_id: payrollId });
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
