<?php
// Hospital Management System - Complete Database Setup
// This script will create the database, import tables, and set up user credentials

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.info { color: blue; font-weight: bold; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>";

echo "<h1>Hospital Management System - Database Setup</h1>";

// Step 1: Test MySQL connection
echo "<h2>Step 1: Testing MySQL Connection</h2>";
try {
    $pdo = new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p class='success'>✓ MySQL connection successful</p>";
} catch (Exception $e) {
    echo "<p class='error'>✗ MySQL connection failed: " . $e->getMessage() . "</p>";
    echo "<p class='warning'>Make sure XAMPP Apache and MySQL services are running!</p>";
    echo "<p>You can start them from XAMPP Control Panel or by running these commands:</p>";
    echo "<ul><li>net start apache2.4</li><li>net start mysql</li></ul>";
    exit;
}

// Step 2: Create database if it doesn't exist
echo "<h2>Step 2: Creating Database</h2>";
try {
    $stmt = $pdo->query("SHOW DATABASES LIKE 'hospital_management'");
    if ($stmt->rowCount() > 0) {
        echo "<p class='info'>Database 'hospital_management' already exists</p>";
    } else {
        $pdo->exec("CREATE DATABASE hospital_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "<p class='success'>✓ Created database 'hospital_management'</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Failed to create database: " . $e->getMessage() . "</p>";
}

// Step 3: Connect to the hospital_management database
echo "<h2>Step 3: Connecting to Hospital Management Database</h2>";
try {
    $pdo = new PDO('mysql:host=localhost;dbname=hospital_management;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p class='success'>✓ Connected to hospital_management database</p>";
} catch (Exception $e) {
    echo "<p class='error'>✗ Failed to connect to database: " . $e->getMessage() . "</p>";
    exit;
}

// Step 4: Read and execute SQL file
echo "<h2>Step 4: Setting Up Database Tables</h2>";
$sql_file = __DIR__ . '/database/hospital_management.sql';

if (!file_exists($sql_file)) {
    echo "<p class='error'>✗ SQL file not found at: " . $sql_file . "</p>";
} else {
    $sql_content = file_get_contents($sql_file);
    
    // Remove comments and split by semicolon
    $sql_content = preg_replace('/--.*$/m', '', $sql_content);
    $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
    $statements = array_filter(array_map('trim', explode(';', $sql_content)));
    
    echo "<p class='info'>Processing " . count($statements) . " SQL statements...</p>";
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement) || strtoupper(substr(trim($statement), 0, 3)) === 'USE') {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $success_count++;
        } catch (Exception $e) {
            $error_count++;
            if (strpos($e->getMessage(), 'already exists') === false && 
                strpos($e->getMessage(), 'Duplicate entry') === false) {
                echo "<p class='warning'>Warning: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    echo "<p class='success'>✓ Executed {$success_count} statements successfully</p>";
    if ($error_count > 0) {
        echo "<p class='warning'>⚠ {$error_count} statements had warnings (likely tables already exist)</p>";
    }
}

// Step 5: Verify tables exist
echo "<h2>Step 5: Verifying Database Structure</h2>";
$required_tables = ['users', 'doctors', 'patients', 'appointments', 'departments'];
$existing_tables = [];

foreach ($required_tables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            $existing_tables[] = $table;
            echo "<p class='success'>✓ Table '{$table}' exists</p>";
        } else {
            echo "<p class='error'>✗ Table '{$table}' missing</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error checking table '{$table}': " . $e->getMessage() . "</p>";
    }
}

// Step 6: Setup user credentials
echo "<h2>Step 6: Setting Up User Credentials</h2>";

$users_to_create = [
    [
        'username' => 'admin',
        'email' => 'admin@hospital.com',
        'password' => 'secret',
        'role' => 'admin'
    ],
    [
        'username' => 'dr.smith',
        'email' => 'dr.smith@hospital.com',
        'password' => 'secret',
        'role' => 'doctor'
    ]
];

if (in_array('users', $existing_tables)) {
    foreach ($users_to_create as $user_data) {
        try {
            // Check if user exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$user_data['username']]);
            $existing_user = $stmt->fetch();
            
            $hashed_password = password_hash($user_data['password'], PASSWORD_DEFAULT);
            
            if ($existing_user) {
                // Update existing user
                $stmt = $pdo->prepare('UPDATE users SET email = ?, password = ?, role = ?, status = ? WHERE username = ?');
                $result = $stmt->execute([
                    $user_data['email'],
                    $hashed_password,
                    $user_data['role'],
                    'active',
                    $user_data['username']
                ]);
                
                if ($result) {
                    echo "<p class='info'>✓ Updated user: " . $user_data['username'] . "</p>";
                }
            } else {
                // Create new user
                $stmt = $pdo->prepare('INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)');
                $result = $stmt->execute([
                    $user_data['username'],
                    $user_data['email'],
                    $hashed_password,
                    $user_data['role'],
                    'active'
                ]);
                
                if ($result) {
                    echo "<p class='success'>✓ Created user: " . $user_data['username'] . "</p>";
                }
            }
        } catch (Exception $e) {
            echo "<p class='error'>✗ Error with user " . $user_data['username'] . ": " . $e->getMessage() . "</p>";
        }
    }
} else {
    echo "<p class='error'>✗ Cannot create users - users table doesn't exist</p>";
}

// Step 7: Test login credentials
echo "<h2>Step 7: Testing Login Credentials</h2>";

if (in_array('users', $existing_tables)) {
    foreach ($users_to_create as $user_data) {
        try {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND status = ?');
            $stmt->execute([$user_data['username'], 'active']);
            $user = $stmt->fetch();
            
            if ($user && password_verify($user_data['password'], $user['password'])) {
                echo "<p class='success'>✓ Login test successful for: " . $user_data['username'] . " / " . $user_data['password'] . "</p>";
            } else {
                echo "<p class='error'>✗ Login test failed for: " . $user_data['username'] . " / " . $user_data['password'] . "</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>✗ Error testing login for " . $user_data['username'] . ": " . $e->getMessage() . "</p>";
        }
    }
} else {
    echo "<p class='error'>✗ Cannot test login - users table doesn't exist</p>";
}

// Step 8: Display current users
echo "<h2>Step 8: Current Users in Database</h2>";

if (in_array('users', $existing_tables)) {
    try {
        $stmt = $pdo->query('SELECT username, email, role, status, created_at FROM users ORDER BY created_at');
        $users = $stmt->fetchAll();
        
        if (empty($users)) {
            echo "<p class='warning'>No users found in database</p>";
        } else {
            echo "<table>";
            echo "<tr><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th></tr>";
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                echo "<td>" . htmlspecialchars($user['role']) . "</td>";
                echo "<td>" . htmlspecialchars($user['status']) . "</td>";
                echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error fetching users: " . $e->getMessage() . "</p>";
    }
}

// Final instructions
echo "<h2>Setup Complete!</h2>";
echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>Login Credentials:</h3>";
echo "<ul>";
echo "<li><strong>Admin:</strong> username = <code>admin</code>, password = <code>secret</code></li>";
echo "<li><strong>Doctor:</strong> username = <code>dr.smith</code>, password = <code>secret</code></li>";
echo "</ul>";

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li><a href='login.php' target='_blank'>Test the login page</a></li>";
echo "<li><a href='index.php' target='_blank'>Go to the main page</a></li>";
echo "<li>Delete this setup file for security: <code>complete_setup.php</code></li>";
echo "</ol>";
echo "</div>";

echo "<p><small>Setup completed on: " . date('Y-m-d H:i:s') . "</small></p>";
?>