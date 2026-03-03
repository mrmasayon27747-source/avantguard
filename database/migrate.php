<?php
/**
 * Migration Script: JSON to MySQL
 * 
 * Run this once after creating the database schema to import existing JSON data.
 * Access via browser: http://localhost/avantguard/database/migrate.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/storage.php';
require_once __DIR__ . '/../inc/database.php';

// Temporarily force JSON mode to read existing data
define('FORCE_JSON_READ', true);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WageWise - Database Migration</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #1a1a2e; color: #eee; }
        h1 { color: #00d4aa; }
        .success { color: #00d4aa; }
        .error { color: #ff6b6b; }
        .info { color: #4dabf7; }
        pre { background: #16213e; padding: 15px; border-radius: 8px; overflow-x: auto; }
        .step { margin: 20px 0; padding: 15px; background: #16213e; border-radius: 8px; border-left: 4px solid #00d4aa; }
        .step.error { border-left-color: #ff6b6b; }
        button { background: #00d4aa; color: #1a1a2e; border: none; padding: 12px 24px; font-size: 16px; cursor: pointer; border-radius: 6px; margin: 10px 5px 10px 0; }
        button:hover { background: #00b894; }
        button.danger { background: #ff6b6b; }
        button.danger:hover { background: #ee5a5a; }
    </style>
</head>
<body>
    <h1>🗄️ WageWise Database Migration</h1>
    
<?php
// Check if migration is requested
$action = $_GET['action'] ?? '';

// Test database connection first
echo '<div class="step">';
echo '<h3>Step 1: Database Connection</h3>';
try {
    $db = get_db();
    echo '<p class="success">✅ Connected to MySQL database: ' . DB_NAME . '</p>';
} catch (Exception $e) {
    echo '<p class="error">❌ Connection failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p class="info">Make sure you have:</p>';
    echo '<ol>';
    echo '<li>Started MySQL in XAMPP</li>';
    echo '<li>Created the database by running <code>database/schema.sql</code> in phpMyAdmin</li>';
    echo '</ol>';
    echo '</div></body></html>';
    exit;
}
echo '</div>';

if ($action === 'migrate') {
    // Perform migration
    $db->beginTransaction();
    
    try {
        // Migrate Users
        echo '<div class="step"><h3>Migrating Users...</h3>';
        $users = read_json(USERS_FILE);
        $stmt = $db->prepare('INSERT INTO users (id, username, password_hash, role, employee_id, must_change_password) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE username=VALUES(username)');
        $count = 0;
        foreach ($users as $u) {
            $stmt->execute([
                $u['id'] ?? null,
                $u['username'],
                $u['password_hash'] ?? $u['password'] ?? '',
                $u['role'] ?? 'worker',
                $u['employee_id'] ?? null,
                $u['must_change_password'] ?? 0
            ]);
            $count++;
        }
        echo '<p class="success">✅ Migrated ' . $count . ' users</p></div>';

        // Migrate Employees
        echo '<div class="step"><h3>Migrating Employees...</h3>';
        $employees = read_json(EMPLOYEES_FILE);
        $stmt = $db->prepare('INSERT INTO employees (id, employee_code, name, position, pay_type, hourly_rate, fixed_default_hours, fixed_daily_rate, schedule_start, schedule_end, active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name)');
        $count = 0;
        foreach ($employees as $e) {
            $stmt->execute([
                $e['id'] ?? null,
                $e['employee_code'] ?? 'EMP' . str_pad((string)($e['id'] ?? 0), 3, '0', STR_PAD_LEFT),
                $e['name'] ?? '',
                $e['position'] ?? null,
                $e['pay_type'] ?? 'hourly',
                $e['hourly_rate'] ?? $e['rate'] ?? 0,
                $e['fixed_default_hours'] ?? 0,
                $e['fixed_daily_rate'] ?? 0,
                $e['schedule_start'] ?? '07:00',
                $e['schedule_end'] ?? '20:00',
                $e['active'] ?? 1,
                $e['created_at'] ?? date('Y-m-d H:i:s')
            ]);
            $count++;
        }
        echo '<p class="success">✅ Migrated ' . $count . ' employees</p></div>';

        // Migrate Profiles
        echo '<div class="step"><h3>Migrating Profiles...</h3>';
        $profiles = read_json(PROFILES_FILE);
        $stmt = $db->prepare('INSERT INTO profiles (employee_id, name, contact_number, home_address, email_address, birthdate) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name)');
        $count = 0;
        foreach ($profiles as $p) {
            $stmt->execute([
                $p['employee_id'],
                $p['name'] ?? null,
                $p['contact_number'] ?? null,
                $p['home_address'] ?? null,
                $p['email_address'] ?? null,
                $p['birthdate'] ?? null
            ]);
            $count++;
        }
        echo '<p class="success">✅ Migrated ' . $count . ' profiles</p></div>';

        // Migrate Attendance
        echo '<div class="step"><h3>Migrating Attendance...</h3>';
        $attendance = read_json(ATTENDANCE_FILE);
        $stmt = $db->prepare('INSERT INTO attendance (employee_id, date, mode, time_in, time_out, fixed_hours, hours, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE hours=VALUES(hours)');
        $count = 0;
        foreach ($attendance as $a) {
            $stmt->execute([
                $a['employee_id'],
                $a['date'],
                $a['mode'] ?? 'hourly',
                $a['time_in'] ?? null,
                $a['time_out'] ?? null,
                $a['fixed_hours'] ?? 0,
                $a['hours'] ?? 0,
                $a['created_at'] ?? date('Y-m-d H:i:s')
            ]);
            $count++;
        }
        echo '<p class="success">✅ Migrated ' . $count . ' attendance records</p></div>';

        // Migrate Payslips
        echo '<div class="step"><h3>Migrating Payslips...</h3>';
        $payslips = read_json(PAYSLIPS_FILE);
        $stmt = $db->prepare('INSERT INTO payslips (employee_id, employee_name, period_start, period_end, pay_type, rate, days_present, total_hours, overtime_hours, gross_pay, overtime_pay, manual_overtime_bonus, total_deductions, net_pay, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $count = 0;
        foreach ($payslips as $p) {
            $stmt->execute([
                $p['employee_id'],
                $p['employee_name'] ?? $p['name'] ?? null,
                $p['period_start'],
                $p['period_end'],
                $p['pay_type'] ?? 'hourly',
                $p['rate'] ?? 0,
                $p['days_present'] ?? 0,
                $p['total_hours'] ?? 0,
                $p['overtime_hours'] ?? 0,
                $p['gross_pay'] ?? $p['gross'] ?? 0,
                $p['overtime_pay'] ?? 0,
                $p['manual_overtime_bonus'] ?? 0,
                $p['total_deductions'] ?? $p['deductions'] ?? 0,
                $p['net_pay'] ?? $p['net'] ?? 0,
                $p['status'] ?? 'draft',
                $p['created_at'] ?? date('Y-m-d H:i:s')
            ]);
            $count++;
        }
        echo '<p class="success">✅ Migrated ' . $count . ' payslips</p></div>';

        // Migrate Deductions
        echo '<div class="step"><h3>Migrating Deductions...</h3>';
        $deductions = read_json(DEDUCTIONS_FILE);
        $stmt = $db->prepare('INSERT INTO deductions (employee_id, date, reason, reason_type, amount, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $count = 0;
        foreach ($deductions as $d) {
            $stmt->execute([
                $d['employee_id'],
                $d['date'],
                $d['reason'] ?? '',
                $d['reason_type'] ?? null,
                $d['amount'] ?? 0,
                $d['notes'] ?? null,
                $d['created_at'] ?? date('Y-m-d H:i:s')
            ]);
            $count++;
        }
        echo '<p class="success">✅ Migrated ' . $count . ' deductions</p></div>';

        // Migrate Tasks
        echo '<div class="step"><h3>Migrating Tasks...</h3>';
        $tasks = read_json(TASKS_FILE);
        $stmt = $db->prepare('INSERT INTO tasks (employee_id, title, description, priority, due_date, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $count = 0;
        foreach ($tasks as $t) {
            $stmt->execute([
                $t['employee_id'],
                $t['title'] ?? '',
                $t['description'] ?? null,
                $t['priority'] ?? 'medium',
                $t['due_date'] ?? null,
                $t['status'] ?? 'pending',
                $t['created_at'] ?? date('Y-m-d H:i:s')
            ]);
            $count++;
        }
        echo '<p class="success">✅ Migrated ' . $count . ' tasks</p></div>';

        // Migrate EOD Reports
        echo '<div class="step"><h3>Migrating EOD Reports...</h3>';
        $eod = read_json(EOD_FILE);
        $stmt = $db->prepare('INSERT INTO eod_reports (employee_id, date, tasks_completed, photo, created_at) VALUES (?, ?, ?, ?, ?)');
        $count = 0;
        foreach ($eod as $e) {
            $stmt->execute([
                $e['employee_id'],
                $e['date'],
                $e['tasks_completed'] ?? null,
                $e['photo'] ?? null,
                $e['created_at'] ?? date('Y-m-d H:i:s')
            ]);
            $count++;
        }
        echo '<p class="success">✅ Migrated ' . $count . ' EOD reports</p></div>';

        // Migrate Devotionals
        echo '<div class="step"><h3>Migrating Devotionals...</h3>';
        $devotionals = read_json(DEVOTIONAL_FILE);
        $stmt = $db->prepare('INSERT INTO devotionals (employee_id, date, photo, created_at) VALUES (?, ?, ?, ?)');
        $count = 0;
        foreach ($devotionals as $d) {
            $stmt->execute([
                $d['employee_id'],
                $d['date'],
                $d['photo'] ?? null,
                $d['created_at'] ?? date('Y-m-d H:i:s')
            ]);
            $count++;
        }
        echo '<p class="success">✅ Migrated ' . $count . ' devotionals</p></div>';

        $db->commit();
        
        echo '<div class="step">';
        echo '<h3>🎉 Migration Complete!</h3>';
        echo '<p class="success">All data has been migrated to MySQL.</p>';
        echo '<p class="info">The system is now using the MySQL database. You can verify the data in phpMyAdmin.</p>';
        echo '</div>';
        
    } catch (Exception $e) {
        $db->rollBack();
        echo '<div class="step error">';
        echo '<h3>❌ Migration Failed</h3>';
        echo '<p class="error">' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        echo '</div>';
    }
    
} else {
    // Show migration options
    echo '<div class="step">';
    echo '<h3>Step 2: Review JSON Data</h3>';
    
    $files = [
        'Users' => USERS_FILE,
        'Employees' => EMPLOYEES_FILE,
        'Profiles' => PROFILES_FILE,
        'Attendance' => ATTENDANCE_FILE,
        'Payslips' => PAYSLIPS_FILE,
        'Deductions' => DEDUCTIONS_FILE,
        'Tasks' => TASKS_FILE,
        'EOD Reports' => EOD_FILE,
        'Devotionals' => DEVOTIONAL_FILE
    ];
    
    echo '<table style="width:100%; border-collapse: collapse;">';
    echo '<tr><th style="text-align:left; padding:8px; border-bottom:1px solid #333;">Data Type</th><th style="text-align:right; padding:8px; border-bottom:1px solid #333;">Records</th></tr>';
    
    $total = 0;
    foreach ($files as $name => $file) {
        $data = read_json($file);
        $count = count($data);
        $total += $count;
        echo '<tr><td style="padding:8px; border-bottom:1px solid #333;">' . $name . '</td><td style="text-align:right; padding:8px; border-bottom:1px solid #333;">' . $count . '</td></tr>';
    }
    echo '<tr><td style="padding:8px; font-weight:bold;">Total</td><td style="text-align:right; padding:8px; font-weight:bold;">' . $total . '</td></tr>';
    echo '</table>';
    echo '</div>';
    
    echo '<div class="step">';
    echo '<h3>Step 3: Run Migration</h3>';
    echo '<p class="info">This will copy all JSON data into the MySQL database. Existing MySQL data will be preserved (duplicates ignored).</p>';
    echo '<form method="get">';
    echo '<button type="submit" name="action" value="migrate">🚀 Start Migration</button>';
    echo '</form>';
    echo '</div>';
    
    echo '<div class="step">';
    echo '<h3>Manual Steps Required</h3>';
    echo '<ol>';
    echo '<li>Open <a href="http://localhost/phpmyadmin" target="_blank" style="color:#00d4aa;">phpMyAdmin</a></li>';
    echo '<li>Create a new database named <code>avantguard_payroll</code></li>';
    echo '<li>Select the database and go to "Import" tab</li>';
    echo '<li>Import <code>database/schema.sql</code></li>';
    echo '<li>Return here and click "Start Migration"</li>';
    echo '</ol>';
    echo '</div>';
}
?>

</body>
</html>
