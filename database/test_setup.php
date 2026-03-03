<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Setup Test</h2>";

$host = 'localhost';
$port = 3307;
$user = 'root';
$pass = '';

echo "<p>Connecting to MySQL on port $port...</p>";

try {
    $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "<p style='color:green'>✅ Connected to MySQL!</p>";
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS avantguard_payroll CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p style='color:green'>✅ Database 'avantguard_payroll' created!</p>";
    
    // Connect to database
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=avantguard_payroll;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // Create tables
    $tables = [
        "users" => "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin', 'worker') DEFAULT 'worker',
            employee_id INT NULL,
            must_change_password TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "employees" => "CREATE TABLE IF NOT EXISTS employees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_code VARCHAR(20) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            position VARCHAR(100),
            pay_type ENUM('hourly', 'fixed') DEFAULT 'hourly',
            hourly_rate DECIMAL(10,2) DEFAULT 0,
            fixed_default_hours DECIMAL(5,2) DEFAULT 0,
            fixed_daily_rate DECIMAL(10,2) DEFAULT 0,
            schedule_start TIME DEFAULT '07:00:00',
            schedule_end TIME DEFAULT '20:00:00',
            active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "profiles" => "CREATE TABLE IF NOT EXISTS profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL UNIQUE,
            name VARCHAR(100),
            contact_number VARCHAR(50),
            home_address TEXT,
            email_address VARCHAR(100),
            birthdate DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
        )",
        "attendance" => "CREATE TABLE IF NOT EXISTS attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            date DATE NOT NULL,
            mode ENUM('hourly', 'fixed') DEFAULT 'hourly',
            time_in TIME,
            time_out TIME,
            fixed_hours DECIMAL(5,2) DEFAULT 0,
            hours DECIMAL(5,2) DEFAULT 0,
            created_by INT,
            updated_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_emp_date (employee_id, date)
        )",
        "payslips" => "CREATE TABLE IF NOT EXISTS payslips (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            employee_name VARCHAR(100),
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            pay_type ENUM('hourly', 'fixed') DEFAULT 'hourly',
            rate DECIMAL(10,2) DEFAULT 0,
            days_present INT DEFAULT 0,
            total_hours DECIMAL(6,2) DEFAULT 0,
            overtime_hours DECIMAL(6,2) DEFAULT 0,
            gross_pay DECIMAL(12,2) DEFAULT 0,
            overtime_pay DECIMAL(12,2) DEFAULT 0,
            manual_overtime_bonus DECIMAL(12,2) DEFAULT 0,
            total_deductions DECIMAL(12,2) DEFAULT 0,
            net_pay DECIMAL(12,2) DEFAULT 0,
            status ENUM('draft', 'approved', 'paid') DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "deductions" => "CREATE TABLE IF NOT EXISTS deductions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            date DATE NOT NULL,
            reason VARCHAR(255) NOT NULL,
            reason_type VARCHAR(50),
            amount DECIMAL(10,2) DEFAULT 0,
            notes TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "tasks" => "CREATE TABLE IF NOT EXISTS tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
            due_date DATE,
            status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "eod_reports" => "CREATE TABLE IF NOT EXISTS eod_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            date DATE NOT NULL,
            tasks_completed TEXT,
            photo VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "devotionals" => "CREATE TABLE IF NOT EXISTS devotionals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            date DATE NOT NULL,
            photo VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    ];
    
    foreach ($tables as $name => $sql) {
        $pdo->exec($sql);
        echo "<p style='color:green'>✅ Table '$name' created!</p>";
    }
    
    // Check for admin user
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES ('admin', ?, 'admin')")
            ->execute([$hash]);
        echo "<p style='color:blue'>ℹ️ Default admin user created (username: admin, password: admin123)</p>";
    }
    
    echo "<h3 style='color:green'>🎉 Database setup complete!</h3>";
    echo "<p><a href='/avantguard/database/migrate.php'>Click here to migrate JSON data</a></p>";
    echo "<p><a href='/avantguard/login.php'>Click here to login</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
