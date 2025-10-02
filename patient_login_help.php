<?php
require_once 'includes/config.php';

echo "<h2>Patient Login Troubleshooting</h2>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.success { color: green; }
.error { color: red; }
.info { color: blue; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>";

try {
    // Check all users with patient role
    echo "<h3>Current Patient Accounts</h3>";
    $db->query('SELECT u.id, u.username, u.email, u.status, u.created_at, p.patient_id, p.first_name, p.last_name 
                FROM users u 
                LEFT JOIN patients p ON u.id = p.user_id 
                WHERE u.role = "patient"
                ORDER BY u.created_at DESC');
    $patients = $db->resultSet();
    
    if (empty($patients)) {
        echo "<p class='info'>No patient accounts found in the database.</p>";
    } else {
        echo "<table>";
        echo "<tr><th>Username</th><th>Patient ID</th><th>Name</th><th>Email</th><th>Status</th><th>Created</th></tr>";
        foreach ($patients as $patient) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($patient['username']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($patient['patient_id'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($patient['first_name'] ?? '') . " " . htmlspecialchars($patient['last_name'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($patient['email']) . "</td>";
            echo "<td>" . htmlspecialchars($patient['status']) . "</td>";
            echo "<td>" . htmlspecialchars($patient['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check what happens when a patient tries to log in
    echo "<h3>Login System Analysis</h3>";
    echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
    echo "<h4>How Patient Login Works:</h4>";
    echo "<ol>";
    echo "<li><strong>Username:</strong> This is the custom username you chose during registration (NOT the Patient ID)</li>";
    echo "<li><strong>Password:</strong> This is the password you set during registration</li>";
    echo "<li><strong>Patient ID:</strong> This is automatically generated (like PAT123456) and is for hospital records only</li>";
    echo "<li><strong>Login Redirect:</strong> After successful login, patients are redirected to 'pages/patient_dashboard.php'</li>";
    echo "</ol>";
    echo "</div>";
    
    // Check if patient dashboard exists
    $dashboard_file = 'pages/patient_dashboard.php';
    if (file_exists($dashboard_file)) {
        echo "<p class='success'>✓ Patient dashboard exists at: {$dashboard_file}</p>";
    } else {
        echo "<p class='error'>✗ Patient dashboard missing: {$dashboard_file}</p>";
        echo "<p class='info'>This might cause login redirect errors for patients.</p>";
    }
    
    // Test login process simulation
    if (!empty($patients)) {
        echo "<h3>Testing Login Process</h3>";
        $latest_patient = $patients[0];
        echo "<p><strong>Latest registered patient:</strong> " . htmlspecialchars($latest_patient['username']) . "</p>";
        
        // Note: We can't test the actual password without knowing it, but we can explain the process
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
        echo "<h4>To login as a patient:</h4>";
        echo "<ol>";
        echo "<li>Go to: <a href='login.php' target='_blank'>login.php</a></li>";
        echo "<li>Enter <strong>Username:</strong> " . htmlspecialchars($latest_patient['username']) . "</li>";
        echo "<li>Enter <strong>Password:</strong> [the password you set during registration]</li>";
        echo "<li>Click 'Sign In'</li>";
        echo "</ol>";
        echo "<p><strong>Note:</strong> Use the USERNAME (not Patient ID) to login!</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>Database Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>Common Login Issues and Solutions:</h3>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
echo "<h4>❌ Issue: 'Invalid username or password'</h4>";
echo "<ul>";
echo "<li><strong>Wrong field:</strong> Make sure you're using the <strong>username</strong> (not Patient ID) to login</li>";
echo "<li><strong>Case sensitive:</strong> Username might be case-sensitive</li>";
echo "<li><strong>Typos:</strong> Double-check spelling of username and password</li>";
echo "<li><strong>Account status:</strong> Account might be inactive</li>";
echo "</ul>";

echo "<h4>✅ Solutions:</h4>";
echo "<ul>";
echo "<li><strong>Check your registration confirmation:</strong> It should show your username</li>";
echo "<li><strong>Try password reset:</strong> If you forgot your password</li>";
echo "<li><strong>Contact admin:</strong> If account is inactive</li>";
echo "<li><strong>Re-register:</strong> If all else fails, create a new account</li>";
echo "</ul>";
echo "</div>";

echo "<p><a href='pages/patient_registration.php'>Register New Patient</a> | <a href='login.php'>Try Login Again</a></p>";
?>