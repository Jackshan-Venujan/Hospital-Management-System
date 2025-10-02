<?php
// Simple admin test page to debug the issue
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
    </style>
</head>
<body>
    <h1>Admin Dashboard Test</h1>
    
    <?php
    // Test 1: Check if config file exists and loads
    echo "<h3>Test 1: Config File</h3>";
    if (file_exists('../includes/config.php')) {
        echo "<p class='success'>✓ Config file exists</p>";
        try {
            require_once '../includes/config.php';
            echo "<p class='success'>✓ Config file loaded successfully</p>";
            
            // Test database connection
            try {
                $test = $db->query('SELECT 1')->single();
                echo "<p class='success'>✓ Database connection working</p>";
            } catch (Exception $e) {
                echo "<p class='error'>✗ Database connection failed: " . $e->getMessage() . "</p>";
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>✗ Config file error: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p class='error'>✗ Config file not found</p>";
    }
    
    // Test 2: Check session
    echo "<h3>Test 2: Session Status</h3>";
    if (session_status() == PHP_SESSION_ACTIVE) {
        echo "<p class='success'>✓ Session is active</p>";
        
        if (isset($_SESSION['user_id'])) {
            echo "<p class='success'>✓ User logged in: " . htmlspecialchars($_SESSION['username'] ?? 'Unknown') . "</p>";
            echo "<p class='info'>Role: " . htmlspecialchars($_SESSION['role'] ?? 'Unknown') . "</p>";
        } else {
            echo "<p class='error'>✗ No user logged in</p>";
        }
    } else {
        echo "<p class='error'>✗ Session not active</p>";
    }
    
    // Test 3: Check admin users
    echo "<h3>Test 3: Admin Users</h3>";
    try {
        if (isset($db)) {
            $db->query('SELECT username, role FROM users WHERE role = "admin"');
            $admins = $db->resultSet();
            
            if (!empty($admins)) {
                echo "<p class='success'>✓ Admin users found:</p>";
                foreach ($admins as $admin) {
                    echo "<li>" . htmlspecialchars($admin['username']) . " (" . htmlspecialchars($admin['role']) . ")</li>";
                }
            } else {
                echo "<p class='error'>✗ No admin users found</p>";
            }
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error checking admin users: " . $e->getMessage() . "</p>";
    }
    
    // Test 4: File paths
    echo "<h3>Test 4: File Paths</h3>";
    $files_to_check = [
        '../includes/header.php',
        '../includes/footer.php',
        'dashboard.php',
        'patients.php'
    ];
    
    foreach ($files_to_check as $file) {
        if (file_exists($file)) {
            echo "<p class='success'>✓ $file exists</p>";
        } else {
            echo "<p class='error'>✗ $file missing</p>";
        }
    }
    ?>
    
    <hr>
    <h3>Quick Actions:</h3>
    <p><a href="../login.php">Login Page</a></p>
    <p><a href="../complete_setup.php">Complete Setup</a></p>
    <p><a href="dashboard.php">Admin Dashboard</a></p>
    <p><a href="patients.php">Patients Page</a></p>
    
    <hr>
    <h3>Login Instructions:</h3>
    <ol>
        <li>Go to <a href="../login.php" target="_blank">Login Page</a></li>
        <li>Username: <strong>admin</strong></li>
        <li>Password: <strong>secret</strong></li>
        <li>After login, try accessing <a href="dashboard.php">Admin Dashboard</a></li>
    </ol>

</body>
</html>