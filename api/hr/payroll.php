<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

Csrf::check();
Auth::requireRole('super_admin', 'admin', 'hr');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = trim((string)($input['action'] ?? ''));
$db = Database::getInstance();
$pdo = $db->getConnection();

$findPayroll = static function (int $id) use ($db): ?array {
    return $db->fetchOne("SELECT * FROM payroll WHERE id=?", [$id]);
};

$calculateNet = static function (float $basic, float $hra, float $allowances, float $deductions, float $pf, float $tds): float {
    return round($basic + $hra + $allowances - $deductions - $pf - $tds, 2);
};

$buildPayload = static function (array $input, bool $requireUser = true) use ($db, $calculateNet): array {
    $userId = (int)($input['user_id'] ?? 0);
    $month = (int)($input['month'] ?? 0);
    $year = (int)($input['year'] ?? 0);

    if ($requireUser && $userId <= 0) {
        Helper::json(['error' => 'Employee is required'], 422);
    }
    if ($month < 1 || $month > 12 || $year < 2020 || $year > 2100) {
        Helper::json(['error' => 'Payroll month and year are invalid'], 422);
    }
    if ($userId > 0) {
        $employee = $db->fetchOne("SELECT id FROM employee_profiles WHERE user_id=?", [$userId]);
        if (!$employee) {
            Helper::json(['error' => 'Employee profile not found'], 404);
        }
    }

    $basic = (float)($input['basic'] ?? 0);
    $hra = (float)($input['hra'] ?? 0);
    $allowances = (float)($input['allowances'] ?? 0);
    $deductions = (float)($input['deductions'] ?? 0);
    $pf = (float)($input['pf'] ?? 0);
    $tds = (float)($input['tds'] ?? 0);

    return [
        'user_id' => $userId,
        'month' => $month,
        'year' => $year,
        'basic' => $basic,
        'hra' => $hra,
        'allowances' => $allowances,
        'deductions' => $deductions,
        'pf' => $pf,
        'tds' => $tds,
        'net_salary' => $calculateNet($basic, $hra, $allowances, $deductions, $pf, $tds),
    ];
};

try {
    switch ($action) {
        case 'generate_month':
            $month = (int)($input['month'] ?? date('n'));
            $year = (int)($input['year'] ?? date('Y'));
            if ($month < 1 || $month > 12 || $year < 2020 || $year > 2100) {
                Helper::json(['error' => 'Payroll month and year are invalid'], 422);
            }

            $employees = $db->fetchAll(
                "SELECT ep.user_id, ep.salary
                 FROM employee_profiles ep
                 WHERE ep.is_active=1 AND ep.salary IS NOT NULL"
            );

            $created = 0;
            $updated = 0;
            $pdo->beginTransaction();
            foreach ($employees as $employee) {
                $salary = (float)$employee['salary'];
                $basic = round($salary * 0.72, 2);
                $hra = round($salary * 0.18, 2);
                $allowances = round($salary * 0.10, 2);
                $pf = round($basic * 0.12, 2);
                $tds = round($salary > 60000 ? $salary * 0.04 : $salary * 0.02, 2);
                $deductions = 0.0;
                $netSalary = $calculateNet($basic, $hra, $allowances, $deductions, $pf, $tds);

                $existing = $db->fetchOne(
                    "SELECT id FROM payroll WHERE user_id=? AND month=? AND year=?",
                    [$employee['user_id'], $month, $year]
                );

                $payload = [
                    'basic' => $basic,
                    'hra' => $hra,
                    'allowances' => $allowances,
                    'deductions' => $deductions,
                    'pf' => $pf,
                    'tds' => $tds,
                    'net_salary' => $netSalary,
                ];

                if ($existing) {
                    $db->update('payroll', $payload, 'id=?', [$existing['id']]);
                    $updated++;
                } else {
                    $payload['user_id'] = $employee['user_id'];
                    $payload['month'] = $month;
                    $payload['year'] = $year;
                    $db->insert('payroll', $payload);
                    $created++;
                }
            }
            $pdo->commit();
            Helper::json(['success' => true, 'message' => "Payroll generated. Created: $created, Updated: $updated"]);

        case 'upsert':
            $payrollId = (int)($input['payroll_id'] ?? 0);
            $payload = $buildPayload($input);
            $existing = $db->fetchOne(
                "SELECT id, paid FROM payroll WHERE user_id=? AND month=? AND year=?",
                [$payload['user_id'], $payload['month'], $payload['year']]
            );

            if ($payrollId > 0) {
                $row = $findPayroll($payrollId);
                if (!$row) {
                    Helper::json(['error' => 'Payroll record not found'], 404);
                }
                $db->update('payroll', $payload, 'id=?', [$payrollId]);
                Helper::json(['success' => true, 'message' => 'Payroll updated.']);
            }

            if ($existing) {
                $db->update('payroll', $payload, 'id=?', [$existing['id']]);
                Helper::json(['success' => true, 'message' => 'Payroll updated.']);
            }

            $db->insert('payroll', $payload);
            Helper::json(['success' => true, 'message' => 'Payroll created.']);

        case 'mark_paid':
            $payrollId = (int)($input['payroll_id'] ?? 0);
            $row = $findPayroll($payrollId);
            if (!$row) {
                Helper::json(['error' => 'Payroll record not found'], 404);
            }
            $next = $row['paid'] ? 0 : 1;
            $db->update('payroll', [
                'paid' => $next,
                'paid_at' => $next ? date('Y-m-d H:i:s') : null,
            ], 'id=?', [$payrollId]);
            Helper::json(['success' => true, 'message' => $next ? 'Payroll marked as paid.' : 'Payment status reset.']);

        case 'delete':
            $payrollId = (int)($input['payroll_id'] ?? 0);
            $row = $findPayroll($payrollId);
            if (!$row) {
                Helper::json(['error' => 'Payroll record not found'], 404);
            }
            $db->delete('payroll', 'id=?', [$payrollId]);
            Helper::json(['success' => true, 'message' => 'Payroll record deleted.']);

        default:
            Helper::json(['error' => 'Unknown action'], 400);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
