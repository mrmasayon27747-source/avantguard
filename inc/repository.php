<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

/**
 * Repository Layer (MySQL Only)
 *
 * All data operations go directly to MySQL database.
 * Changes in the web app immediately save to MySQL.
 * Changes in phpMyAdmin immediately reflect in the web app.
 */

// ========================================
// USERS
// ========================================
function repo_users(): array {
    $stmt = get_db()->query('SELECT * FROM users ORDER BY id');
    return $stmt->fetchAll();
}

function repo_find_user_by_username(string $username): ?array {
    $stmt = get_db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function repo_find_user_by_id(int $id): ?array {
    $stmt = get_db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function repo_create_user(array $data): int {
    $stmt = get_db()->prepare('
        INSERT INTO users (username, password_hash, role, employee_id, must_change_password)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $data['username'],
        $data['password_hash'],
        $data['role'] ?? 'worker',
        $data['employee_id'] ?? null,
        $data['must_change_password'] ?? 0
    ]);
    return (int)get_db()->lastInsertId();
}

function repo_update_user(int $id, array $data): void {
    $allowed = ['username', 'password_hash', 'role', 'employee_id', 'must_change_password'];
    $sets = [];
    $params = [];
    foreach ($data as $key => $value) {
        if (in_array($key, $allowed)) {
            $sets[] = "$key = ?";
            $params[] = $value;
        }
    }
    if (empty($sets)) return;
    $params[] = $id;
    $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?';
    get_db()->prepare($sql)->execute($params);
}

function repo_delete_user(int $id): void {
    get_db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
}

// ========================================
// EMPLOYEES
// ========================================
function repo_employees(): array {
    $stmt = get_db()->query('SELECT * FROM employees ORDER BY employee_code');
    return $stmt->fetchAll();
}

function repo_find_employee_by_id(int $id): ?array {
    $stmt = get_db()->prepare('SELECT * FROM employees WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $emp = $stmt->fetch();
    return $emp ?: null;
}

function repo_find_employee_by_code(string $code): ?array {
    $stmt = get_db()->prepare('SELECT * FROM employees WHERE employee_code = ? LIMIT 1');
    $stmt->execute([$code]);
    $emp = $stmt->fetch();
    return $emp ?: null;
}

function repo_create_employee(array $data): int {
    $stmt = get_db()->prepare('
        INSERT INTO employees (employee_code, name, position, pay_type, hourly_rate, 
            fixed_default_hours, fixed_daily_rate, schedule_start, schedule_end, schedule_days, schedules, active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $data['employee_code'],
        $data['name'],
        $data['position'] ?? null,
        $data['pay_type'] ?? 'hourly',
        $data['hourly_rate'] ?? 0,
        $data['fixed_default_hours'] ?? 0,
        $data['fixed_daily_rate'] ?? 0,
        $data['schedule_start'] ?? '07:00',
        $data['schedule_end'] ?? '20:00',
        $data['schedule_days'] ?? 'Mon,Tue,Wed,Thu,Fri',
        $data['schedules'] ?? null,
        $data['active'] ?? 1
    ]);
    return (int)get_db()->lastInsertId();
}

function repo_update_employee(int $id, array $data): void {
    $allowed = ['name', 'position', 'pay_type', 'hourly_rate', 'fixed_default_hours', 
                'fixed_daily_rate', 'schedule_start', 'schedule_end', 'schedule_days', 'schedules', 'active'];
    $sets = [];
    $params = [];
    foreach ($data as $key => $value) {
        if (in_array($key, $allowed)) {
            $sets[] = "$key = ?";
            $params[] = $value;
        }
    }
    if (empty($sets)) return;
    $params[] = $id;
    $sql = 'UPDATE employees SET ' . implode(', ', $sets) . ' WHERE id = ?';
    get_db()->prepare($sql)->execute($params);
}

function repo_delete_employee(int $id): void {
    get_db()->prepare('DELETE FROM employees WHERE id = ?')->execute([$id]);
}

// ========================================
// ATTENDANCE
// ========================================
function repo_attendance(): array {
    $stmt = get_db()->query('SELECT * FROM attendance ORDER BY date DESC, id DESC');
    return $stmt->fetchAll();
}

function repo_create_attendance(array $data): int {
    $stmt = get_db()->prepare('
        INSERT INTO attendance (employee_id, date, mode, time_in, time_out, fixed_hours, hours, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');
    // Ensure empty strings are stored as NULL for time fields
    $time_in = !empty($data['time_in']) ? $data['time_in'] : null;
    $time_out = !empty($data['time_out']) ? $data['time_out'] : null;
    $stmt->execute([
        $data['employee_id'],
        $data['date'],
        $data['mode'] ?? 'hourly',
        $time_in,
        $time_out,
        $data['fixed_hours'] ?? 0,
        $data['hours'] ?? 0,
        $data['created_by'] ?? null
    ]);
    return (int)get_db()->lastInsertId();
}

function repo_update_attendance(int $id, array $data): void {
    $allowed = ['date', 'time_in', 'time_out', 'hours', 'updated_by'];
    $sets = ['updated_at = NOW()'];
    $params = [];
    foreach ($data as $key => $value) {
        if (in_array($key, $allowed)) {
            $sets[] = "$key = ?";
            $params[] = $value;
        }
    }
    $params[] = $id;
    $sql = 'UPDATE attendance SET ' . implode(', ', $sets) . ' WHERE id = ?';
    get_db()->prepare($sql)->execute($params);
}

function repo_delete_attendance(int $id): void {
    get_db()->prepare('DELETE FROM attendance WHERE id = ?')->execute([$id]);
}

function repo_find_attendance_by_employee_date(int $employee_id, string $date): ?array {
    $stmt = get_db()->prepare('SELECT * FROM attendance WHERE employee_id = ? AND date = ? LIMIT 1');
    $stmt->execute([$employee_id, $date]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// ========================================
// PAYSLIPS
// ========================================
function repo_payslips(): array {
    $stmt = get_db()->query('SELECT * FROM payslips ORDER BY id DESC');
    return $stmt->fetchAll();
}

function repo_create_payslip(array $data): int {
    $stmt = get_db()->prepare('
        INSERT INTO payslips (employee_id, employee_name, period_start, period_end, pay_type, 
            rate, days_present, total_hours, overtime_hours, gross_pay, overtime_pay, 
            manual_overtime_bonus, total_deductions, net_pay, status, calculated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $data['employee_id'],
        $data['employee_name'] ?? null,
        $data['period_start'],
        $data['period_end'],
        $data['pay_type'] ?? 'hourly',
        $data['rate'] ?? 0,
        $data['days_present'] ?? 0,
        $data['total_hours'] ?? 0,
        $data['overtime_hours'] ?? 0,
        $data['gross_pay'] ?? 0,
        $data['overtime_pay'] ?? 0,
        $data['manual_overtime_bonus'] ?? 0,
        $data['total_deductions'] ?? 0,
        $data['net_pay'] ?? 0,
        $data['status'] ?? 'draft',
        $data['calculated_by'] ?? null
    ]);
    return (int)get_db()->lastInsertId();
}

function repo_update_payslip(int $id, array $data): void {
    $allowed = ['employee_name', 'period_start', 'period_end', 'pay_type', 'rate', 
                'days_present', 'total_hours', 'overtime_hours', 'gross_pay', 'overtime_pay',
                'manual_overtime_bonus', 'total_deductions', 'net_pay', 'status', 'calculated_by'];
    $sets = [];
    $params = [];
    foreach ($data as $key => $value) {
        if (in_array($key, $allowed)) {
            $sets[] = "$key = ?";
            $params[] = $value;
        }
    }
    if (empty($sets)) return;
    $params[] = $id;
    $sql = 'UPDATE payslips SET ' . implode(', ', $sets) . ' WHERE id = ?';
    get_db()->prepare($sql)->execute($params);
}

function repo_delete_payslip(int $id): void {
    get_db()->prepare('DELETE FROM payslips WHERE id = ?')->execute([$id]);
}

/**
 * Returns all payslips for the employee whose period overlaps with the given range.
 * Overlap condition: existing.period_start <= new_end AND existing.period_end >= new_start
 */
function repo_payslip_overlaps(int $employee_id, string $period_start, string $period_end): array {
    $stmt = get_db()->prepare(
        'SELECT * FROM payslips WHERE employee_id = ? AND period_start <= ? AND period_end >= ?'
    );
    $stmt->execute([$employee_id, $period_end, $period_start]);
    return $stmt->fetchAll();
}

function repo_find_payslip_by_id(int $id): ?array {
    $stmt = get_db()->prepare('SELECT * FROM payslips WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// ========================================
// EOD REPORTS
// ========================================
function repo_eod(): array {
    $stmt = get_db()->query('SELECT * FROM eod_reports ORDER BY date DESC, id DESC');
    return $stmt->fetchAll();
}

function repo_create_eod(array $data): int {
    $stmt = get_db()->prepare('
        INSERT INTO eod_reports (employee_id, date, tasks_completed, pending_concerns, notes_status, photo)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $data['employee_id'],
        $data['date'],
        $data['tasks_completed'] ?? null,
        $data['pending_concerns'] ?? null,
        $data['notes_status'] ?? null,
        $data['photo'] ?? null
    ]);
    return (int)get_db()->lastInsertId();
}

function repo_delete_eod(int $id): void {
    get_db()->prepare('DELETE FROM eod_reports WHERE id = ?')->execute([$id]);
}

// ========================================
// DEVOTIONALS
// ========================================
function repo_devotionals(): array {
    $stmt = get_db()->query('SELECT * FROM devotionals ORDER BY date DESC, id DESC');
    return $stmt->fetchAll();
}

function repo_create_devotional(array $data): int {
    $stmt = get_db()->prepare('
        INSERT INTO devotionals (employee_id, date, photo)
        VALUES (?, ?, ?)
    ');
    $stmt->execute([
        $data['employee_id'],
        $data['date'],
        $data['photo'] ?? null
    ]);
    return (int)get_db()->lastInsertId();
}

function repo_delete_devotional(int $id): void {
    get_db()->prepare('DELETE FROM devotionals WHERE id = ?')->execute([$id]);
}

// ========================================
// TASKS
// ========================================
function repo_tasks(): array {
    $stmt = get_db()->query('SELECT * FROM tasks ORDER BY due_date ASC, id DESC');
    return $stmt->fetchAll();
}

function repo_create_task(array $data): int {
    $stmt = get_db()->prepare('
        INSERT INTO tasks (employee_id, title, description, priority, due_date, status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $data['employee_id'],
        $data['title'],
        $data['description'] ?? null,
        $data['priority'] ?? 'medium',
        $data['due_date'] ?? null,
        $data['status'] ?? 'pending',
        $data['created_by'] ?? null
    ]);
    return (int)get_db()->lastInsertId();
}

function repo_update_task(int $id, array $data): void {
    $allowed = ['title', 'description', 'priority', 'due_date', 'status', 'completed_at'];
    $sets = [];
    $params = [];
    foreach ($data as $key => $value) {
        if (in_array($key, $allowed)) {
            $sets[] = "$key = ?";
            $params[] = $value;
        }
    }
    if (empty($sets)) return;
    $params[] = $id;
    $sql = 'UPDATE tasks SET ' . implode(', ', $sets) . ' WHERE id = ?';
    get_db()->prepare($sql)->execute($params);
}

function repo_delete_task(int $id): void {
    get_db()->prepare('DELETE FROM tasks WHERE id = ?')->execute([$id]);
}

function repo_find_task_by_id(int $id): ?array {
    $stmt = get_db()->prepare('SELECT * FROM tasks WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// ========================================
// PROFILES
// ========================================
function repo_profiles(): array {
    $stmt = get_db()->query('SELECT * FROM profiles');
    return $stmt->fetchAll();
}

function repo_find_profile_by_employee(int $employee_id): ?array {
    $stmt = get_db()->prepare('SELECT * FROM profiles WHERE employee_id = ? LIMIT 1');
    $stmt->execute([$employee_id]);
    $profile = $stmt->fetch();
    return $profile ?: null;
}

function repo_upsert_profile(int $employee_id, array $data): void {
    $stmt = get_db()->prepare('
        INSERT INTO profiles (employee_id, name, contact_number, home_address, email_address, birthdate)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            name = VALUES(name),
            contact_number = VALUES(contact_number),
            home_address = VALUES(home_address),
            email_address = VALUES(email_address),
            birthdate = VALUES(birthdate),
            updated_at = NOW()
    ');
    $stmt->execute([
        $employee_id,
        $data['name'] ?? null,
        $data['contact_number'] ?? null,
        $data['home_address'] ?? null,
        $data['email_address'] ?? null,
        $data['birthdate'] ?? null
    ]);
}

// ========================================
// DEDUCTIONS
// ========================================
function repo_deductions(): array {
    $stmt = get_db()->query('SELECT * FROM deductions ORDER BY date DESC, id DESC');
    return $stmt->fetchAll();
}

function repo_create_deduction(array $data): int {
    $stmt = get_db()->prepare('
        INSERT INTO deductions (employee_id, date, reason, reason_type, amount, notes, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $data['employee_id'],
        $data['date'],
        $data['reason'],
        $data['reason_type'] ?? null,
        $data['amount'] ?? 0,
        $data['notes'] ?? null,
        $data['created_by'] ?? null
    ]);
    return (int)get_db()->lastInsertId();
}

function repo_update_deduction(int $id, array $data): void {
    $allowed = ['date', 'reason', 'reason_type', 'amount', 'notes'];
    $sets = [];
    $params = [];
    foreach ($data as $key => $value) {
        if (in_array($key, $allowed)) {
            $sets[] = "$key = ?";
            $params[] = $value;
        }
    }
    if (empty($sets)) return;
    $params[] = $id;
    $sql = 'UPDATE deductions SET ' . implode(', ', $sets) . ' WHERE id = ?';
    get_db()->prepare($sql)->execute($params);
}

function repo_delete_deduction(int $id): void {
    get_db()->prepare('DELETE FROM deductions WHERE id = ?')->execute([$id]);
}

// ========================================
// HELPER FUNCTIONS (for backward compatibility)
// ========================================

/**
 * Find employee by code from array (helper used in employees.php)
 */
function find_employee_by_code(array $employees, string $code): ?array {
    foreach ($employees as $e) {
        if (strcasecmp($e['employee_code'] ?? '', $code) === 0) {
            return $e;
        }
    }
    return null;
}
