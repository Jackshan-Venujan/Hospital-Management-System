<?php
/**
 * Hospital Management System
 * Database Configuration File
 * 
 * This file contains database connection settings
 * Make sure to update these settings according to your local server configuration
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Default password for XAMPP/WAMP is usually empty
define('DB_NAME', 'hospital_management');

// Application Configuration
define('SITE_URL', 'http://localhost/Hospital_Management_System/');
define('SITE_NAME', 'Hospital Management System');
define('ADMIN_EMAIL', 'admin@hospital.com');

// Session Configuration
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    session_start();
}

// Database Connection Class
class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    private $dbh;
    private $stmt;
    private $error;

    public function __construct() {
        // Set DSN
        $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->dbname . ';charset=utf8mb4';
        
        // Set Options
        $options = array(
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        );

        // Create PDO instance
        try {
            $this->dbh = new PDO($dsn, $this->user, $this->pass, $options);
        } catch(PDOException $e) {
            $this->error = $e->getMessage();
            die('Database connection failed: ' . $this->error);
        }
    }

    // Prepare statement with query
    public function query($sql) {
        $this->stmt = $this->dbh->prepare($sql);
        return $this;
    }

    // Bind parameters
    public function bind($param, $value, $type = null) {
        if (is_null($type)) {
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }
        $this->stmt->bindValue($param, $value, $type);
        return $this;
    }

    // Execute the prepared statement
    public function execute() {
        return $this->stmt->execute();
    }

    // Get result set as array of objects
    public function resultSet() {
        $this->execute();
        return $this->stmt->fetchAll();
    }

    // Get single record as object
    public function single() {
        $this->execute();
        return $this->stmt->fetch();
    }

    // Get row count
    public function rowCount() {
        return $this->stmt->rowCount();
    }

    // Get last insert ID
    public function lastInsertId() {
        return $this->dbh->lastInsertId();
    }

    // Begin transaction
    public function beginTransaction() {
        return $this->dbh->beginTransaction();
    }

    // End transaction
    public function endTransaction() {
        return $this->dbh->commit();
    }

    // Cancel transaction
    public function cancelTransaction() {
        return $this->dbh->rollback();
    }
}

// Create global database instance
$db = new Database();

// MySQLi Connection for backward compatibility
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check MySQLi connection
if ($conn->connect_error) {
    die("MySQLi Connection failed: " . $conn->connect_error);
}

// Set charset for MySQLi
$conn->set_charset("utf8mb4");

// Helper Functions
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function redirect($location) {
    // Handle both absolute and relative URLs
    if (strpos($location, 'http') === 0) {
        header("Location: " . $location);
    } else {
        // For relative paths, check if it starts with a slash
        if (strpos($location, '/') === 0) {
            header("Location: " . $location);
        } else {
            header("Location: " . SITE_URL . $location);
        }
    }
    exit();
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function get_user_role() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

function check_role_access($allowed_roles) {
    if (!is_logged_in()) {
        redirect('login.php');
    }
    
    $user_role = get_user_role();
    if (!in_array($user_role, $allowed_roles)) {
        redirect('unauthorized.php');
    }
}

function generate_id($prefix = '', $length = 6) {
    return $prefix . str_pad(mt_rand(1, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

function format_date($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

function format_time($time, $format = 'g:i A') {
    return date($format, strtotime($time));
}

// Error and Success Message Functions
function set_message($type, $message) {
    $_SESSION['message'] = [
        'type' => $type,
        'text' => $message
    ];
}

function display_message() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $class = $message['type'] === 'error' ? 'alert-danger' : 'alert-success';
        echo '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">';
        echo $message['text'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        unset($_SESSION['message']);
    }
}

// Auto-include common functions
spl_autoload_register(function($class_name) {
    $file_path = 'classes/' . $class_name . '.php';
    if (file_exists($file_path)) {
        include $file_path;
    }
});
?>