<?php
/**
 * Simple Database Admin - Alternative to phpMyAdmin
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(10); // 10 second max execution

$host = '127.0.0.1';
$port = 3307;
$user = 'root';
$pass = '';

?>
<!DOCTYPE html>
<html>
<head>
    <title>DB Admin</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; background: #1a1a2e; color: #eee; }
        h1 { color: #00d4aa; }
        .success { color: #00d4aa; padding: 10px; background: #16213e; margin: 10px 0; border-radius: 4px; }
        .error { color: #ff6b6b; padding: 10px; background: #16213e; margin: 10px 0; border-radius: 4px; }
        .info { color: #4dabf7; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #333; }
        th { background: #16213e; }
        button, input[type="submit"] { background: #00d4aa; color: #1a1a2e; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; margin: 5px; }
        button:hover, input[type="submit"]:hover { background: #00b894; }
        textarea { width: 100%; height: 100px; background: #16213e; color: #eee; border: 1px solid #333; padding: 10px; font-family: monospace; }
        a { color: #00d4aa; }
    </style>
</head>
<body>
    <h1>🗄️ Simple Database Admin</h1>
    
    <p class="info">Connecting to MySQL at <?= $host ?>:<?= $port ?>...</p>
    
<?php
try {
    $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    echo '<p class="success">✅ Connected to MySQL!</p>';
    
    // Handle actions
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $db = $_GET['db'] ?? '';
    
    if ($action === 'create_avantguard') {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS avantguard_payroll CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo '<p class="success">✅ Database avantguard_payroll created!</p>';
        
        // Connect to new database
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=avantguard_payroll;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create tables from schema
        $schema = file_get_contents(__DIR__ . '/schema.sql');
        $statements = array_filter(array_map('trim', preg_split('/;[\r\n]+/', $schema)));
        
        $created = 0;
        foreach ($statements as $sql) {
            if (!empty($sql) && stripos($sql, 'CREATE TABLE') !== false) {
                try {
                    $pdo->exec($sql);
                    $created++;
                } catch (PDOException $e) {
                    // Ignore if already exists
                }
            }
        }
        echo '<p class="success">✅ Created ' . $created . ' tables!</p>';
        
        // Create admin user
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
        if ($stmt->fetchColumn() == 0) {
            $hash = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES ('admin', ?, 'admin')")->execute([$hash]);
            echo '<p class="success">✅ Admin user created (admin / admin123)</p>';
        }
        
        echo '<p><a href="/avantguard/login.php">→ Go to Login</a></p>';
    }
    
    if ($action === 'run_sql' && !empty($_POST['sql'])) {
        $targetDb = $_POST['database'] ?? 'avantguard_payroll';
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$targetDb;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = $_POST['sql'];
        if (stripos($sql, 'SELECT') === 0 || stripos($sql, 'SHOW') === 0 || stripos($sql, 'DESCRIBE') === 0) {
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                echo '<table><tr>';
                foreach (array_keys($rows[0]) as $col) {
                    echo '<th>' . htmlspecialchars($col) . '</th>';
                }
                echo '</tr>';
                foreach ($rows as $row) {
                    echo '<tr>';
                    foreach ($row as $val) {
                        echo '<td>' . htmlspecialchars($val ?? 'NULL') . '</td>';
                    }
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p class="info">No results</p>';
            }
        } else {
            $affected = $pdo->exec($sql);
            echo '<p class="success">✅ Query executed. Rows affected: ' . $affected . '</p>';
        }
    }
    
    // Show databases
    echo '<h2>Databases</h2>';
    $stmt = $pdo->query('SHOW DATABASES');
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo '<table><tr><th>Database</th><th>Tables</th><th>Actions</th></tr>';
    foreach ($databases as $dbName) {
        if (in_array($dbName, ['information_schema', 'performance_schema', 'mysql'])) continue;
        
        try {
            $pdo->exec("USE `$dbName`");
            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            $tableCount = count($tables);
        } catch (Exception $e) {
            $tableCount = '?';
        }
        
        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($dbName) . '</strong></td>';
        echo '<td>' . $tableCount . '</td>';
        echo '<td><a href="?db=' . urlencode($dbName) . '">Browse</a></td>';
        echo '</tr>';
    }
    echo '</table>';
    
    // Quick actions
    echo '<h2>Quick Actions</h2>';
    
    if (!in_array('avantguard_payroll', $databases)) {
        echo '<form method="post"><input type="hidden" name="action" value="create_avantguard">';
        echo '<button type="submit">🚀 Create Avantguard Database + Tables</button></form>';
    } else {
        echo '<p class="success">✅ avantguard_payroll database exists</p>';
        echo '<p><a href="/avantguard/database/migrate.php">→ Migrate JSON data to MySQL</a></p>';
        echo '<p><a href="/avantguard/login.php">→ Go to Login</a></p>';
    }
    
    // SQL query box
    echo '<h2>Run SQL Query</h2>';
    echo '<form method="post">';
    echo '<input type="hidden" name="action" value="run_sql">';
    echo '<select name="database"><option>avantguard_payroll</option>';
    foreach ($databases as $d) {
        if ($d !== 'avantguard_payroll' && !in_array($d, ['information_schema', 'performance_schema', 'mysql'])) {
            echo '<option>' . htmlspecialchars($d) . '</option>';
        }
    }
    echo '</select><br><br>';
    echo '<textarea name="sql" placeholder="SELECT * FROM users;">' . htmlspecialchars($_POST['sql'] ?? '') . '</textarea><br>';
    echo '<button type="submit">Run Query</button>';
    echo '</form>';
    
} catch (PDOException $e) {
    echo '<p class="error">❌ Connection failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p class="info">Make sure MySQL is running on port ' . $port . '</p>';
}
?>

</body>
</html>
