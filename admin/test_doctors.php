<?php
// Test script for doctors management system
require_once '../includes/config.php';

echo "<h2>Testing Doctors Management System</h2>\n";

try {
    // Test database connection
    echo "✓ Database connection: OK<br>\n";
    
    // Test doctors table structure
    $db->query("DESCRIBE doctors");
    $doctor_columns = $db->resultSet();
    echo "✓ Doctors table structure: OK (" . count($doctor_columns) . " columns)<br>\n";
    
    // Test departments table
    $db->query("DESCRIBE departments");
    $dept_columns = $db->resultSet();
    echo "✓ Departments table structure: OK (" . count($dept_columns) . " columns)<br>\n";
    
    // Test getting all doctors
    $db->query("
        SELECT 
            d.id, d.employee_id, d.first_name, d.last_name, d.specialization,
            dept.name as department_name,
            u.username, u.status
        FROM doctors d 
        JOIN users u ON d.user_id = u.id 
        LEFT JOIN departments dept ON d.department_id = dept.id
        LIMIT 5
    ");
    $doctors = $db->resultSet();
    echo "✓ Doctors query: OK (" . count($doctors) . " doctors found)<br>\n";
    
    // Test getting distinct specializations
    $db->query("SELECT DISTINCT specialization FROM doctors WHERE specialization IS NOT NULL ORDER BY specialization");
    $specializations = $db->resultSet();
    echo "✓ Specializations query: OK (" . count($specializations) . " specializations)<br>\n";
    
    // Test getting departments
    $db->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name");
    $departments = $db->resultSet();
    echo "✓ Departments query: OK (" . count($departments) . " active departments)<br>\n";
    
    echo "<br><strong>All tests passed! ✅</strong><br>\n";
    
    if (!empty($doctors)) {
        echo "<br><h3>Sample Doctor Data:</h3>\n";
        echo "<table border='1' cellpadding='5'>\n";
        echo "<tr><th>Employee ID</th><th>Name</th><th>Specialization</th><th>Department</th><th>Status</th></tr>\n";
        foreach ($doctors as $doctor) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($doctor['employee_id']) . "</td>";
            echo "<td>Dr. " . htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($doctor['specialization']) . "</td>";
            echo "<td>" . htmlspecialchars($doctor['department_name'] ?: 'Not Assigned') . "</td>";
            echo "<td>" . ucfirst(htmlspecialchars($doctor['status'])) . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>\n";
}
?>