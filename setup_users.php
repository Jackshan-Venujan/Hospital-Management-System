<?php
require_once 'includes/config.php';

echo "<h2>Hospital Management System - User Setup</h2>";

// Check if database connection works
try {
    $test = $db->query('SELECT 1')->single();
    echo "<p style='color: green;'>✓ Database connection successful</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

// Check if users table exists
try {
    $db->query('SELECT COUNT(*) as count FROM users');
    $db->execute();
    echo "<p style='color: green;'>✓ Users table exists</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Users table doesn't exist. Please run the SQL file first.</p>";
    exit;
}

// Check existing users
$db->query('SELECT username, email, role, status FROM users');
$existing_users = $db->resultSet();

echo "<h3>Existing Users:</h3>";
if (empty($existing_users)) {
    echo "<p>No users found in database.</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Username</th><th>Email</th><th>Role</th><th>Status</th></tr>";
    foreach ($existing_users as $user) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td>" . htmlspecialchars($user['role']) . "</td>";
        echo "<td>" . htmlspecialchars($user['status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Create/update users with proper password hashing
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

echo "<h3>Creating/Updating Users:</h3>";

foreach ($users_to_create as $user_data) {
    // Check if user already exists
    $db->query('SELECT id FROM users WHERE username = :username');
    $db->bind(':username', $user_data['username']);
    $existing_user = $db->single();
    
    $hashed_password = password_hash($user_data['password'], PASSWORD_DEFAULT);
    
    if ($existing_user) {
        // Update existing user
        $db->query('UPDATE users SET email = :email, password = :password, role = :role, status = :status WHERE username = :username');
        $db->bind(':email', $user_data['email']);
        $db->bind(':password', $hashed_password);
        $db->bind(':role', $user_data['role']);
        $db->bind(':status', 'active');
        $db->bind(':username', $user_data['username']);
        
        if ($db->execute()) {
            echo "<p style='color: blue;'>✓ Updated user: " . $user_data['username'] . "</p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to update user: " . $user_data['username'] . "</p>";
        }
    } else {
        // Create new user
        $db->query('INSERT INTO users (username, email, password, role, status) VALUES (:username, :email, :password, :role, :status)');
        $db->bind(':username', $user_data['username']);
        $db->bind(':email', $user_data['email']);
        $db->bind(':password', $hashed_password);
        $db->bind(':role', $user_data['role']);
        $db->bind(':status', 'active');
        
        if ($db->execute()) {
            echo "<p style='color: green;'>✓ Created user: " . $user_data['username'] . "</p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to create user: " . $user_data['username'] . "</p>";
        }
    }
}

echo "<h3>Testing Login Credentials:</h3>";

foreach ($users_to_create as $user_data) {
    $db->query('SELECT * FROM users WHERE username = :username AND status = :status');
    $db->bind(':username', $user_data['username']);
    $db->bind(':status', 'active');
    $user = $db->single();
    
    if ($user && password_verify($user_data['password'], $user['password'])) {
        echo "<p style='color: green;'>✓ Login test successful for: " . $user_data['username'] . " / " . $user_data['password'] . "</p>";
    } else {
        echo "<p style='color: red;'>✗ Login test failed for: " . $user_data['username'] . " / " . $user_data['password'] . "</p>";
    }
}

echo "<h3>Instructions:</h3>";
echo "<ul>";
echo "<li>Admin Login: <strong>username:</strong> admin, <strong>password:</strong> secret</li>";
echo "<li>Doctor Login: <strong>username:</strong> dr.smith, <strong>password:</strong> secret</li>";
echo "<li>After confirming login works, you can delete this file for security.</li>";
echo "</ul>";

echo "<p><a href='login.php'>Go to Login Page</a></p>";
?>