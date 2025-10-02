<?php
// Check database status
try {
    // Test connection without specifying database
    $dsn = 'mysql:host=localhost;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', '');
    
    echo "<h2>Database Status Check</h2>";
    echo "<p style='color: green;'>✓ MySQL connection successful</p>";
    
    // Check if database exists
    $stmt = $pdo->query("SHOW DATABASES LIKE 'hospital_management'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ Database 'hospital_management' exists</p>";
        
        // Connect to the specific database
        $pdo_db = new PDO('mysql:host=localhost;dbname=hospital_management;charset=utf8mb4', 'root', '');
        
        // Check if users table exists
        $stmt = $pdo_db->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✓ Users table exists</p>";
            
            // Check users count
            $stmt = $pdo_db->query("SELECT COUNT(*) as count FROM users");
            $result = $stmt->fetch();
            echo "<p>Current users count: " . $result['count'] . "</p>";
        } else {
            echo "<p style='color: red;'>✗ Users table does not exist</p>";
            echo "<p><strong>Action needed:</strong> Import the database SQL file first</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Database 'hospital_management' does not exist</p>";
        echo "<p><strong>Action needed:</strong> Create database and import SQL file</p>";
        
        // Try to create the database
        try {
            $pdo->exec("CREATE DATABASE hospital_management");
            echo "<p style='color: blue;'>✓ Created database 'hospital_management'</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Failed to create database: " . $e->getMessage() . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<h2>Database Connection Error</h2>";
    echo "<p style='color: red;'>✗ MySQL connection failed: " . $e->getMessage() . "</p>";
    echo "<p><strong>Make sure XAMPP Apache and MySQL services are running!</strong></p>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Make sure XAMPP Apache and MySQL services are running</li>";
echo "<li>If database doesn't exist, go to <a href='http://localhost/phpmyadmin' target='_blank'>phpMyAdmin</a></li>";
echo "<li>Create database 'hospital_management' and import the SQL file from database/hospital_management.sql</li>";
echo "<li>Then run <a href='setup_users.php'>setup_users.php</a> to fix login credentials</li>";
echo "<li>Finally test <a href='login.php'>login.php</a></li>";
echo "</ol>";
?>