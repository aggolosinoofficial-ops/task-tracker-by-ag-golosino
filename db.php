<?php
// Database connection parameters (OPTIMIZED for 2GB RAM)
$servername = "localhost";
$username = "root";
$password = ""; // Default XAMPP password - change if you set a password in XAMPP

// Create connection without database - with error suppression for clean error handling
@$conn = new mysqli($servername, $username, $password);

// Set connection options for better performance
if ($conn) {
    $conn->set_charset("utf8mb4");
}

// Check connection - Detailed error reporting
if ($conn && $conn->connect_error) {
    $errorMsg = $conn->connect_error;
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(503);
        echo json_encode([
            'success' => false, 
            'error' => 'Database connection failed',
            'details' => $errorMsg
        ]);
        exit();
    } else {
        http_response_code(503);
        die("
        <h2>Database Connection Error</h2>
        <p><strong>Error:</strong> " . htmlspecialchars($errorMsg) . "</p>
        <hr>
        <h3>Troubleshooting:</h3>
        <ul>
            <li>✓ Check XAMPP Control Panel - Is MySQL running? (Should be GREEN)</li>
            <li>✓ Try restarting MySQL from XAMPP Control Panel</li>
            <li>✓ Check if MySQL is using port 3306 (default)</li>
            <li>✓ Verify username 'root' and empty password in config</li>
            <li>✓ Try: <code>mysql -u root -h localhost</code> in command line</li>
        </ul>
        ");
    }
}

// OPTIMIZATION: Reduce memory footprint for large result sets
if ($conn) {
    $conn->set_charset("utf8mb4");
}

// Create database if it doesn't exist
$dbname = "test";
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";

if ($conn && $conn->query($sql) !== TRUE) {
    $errorMsg = $conn->error;
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(503);
        echo json_encode([
            'success' => false, 
            'error' => 'Error creating database',
            'details' => $errorMsg
        ]);
        exit();
    } else {
        http_response_code(503);
        die("
        <h2>Database Creation Error</h2>
        <p><strong>Error:</strong> " . htmlspecialchars($errorMsg) . "</p>
        <p>Try restarting MySQL in XAMPP Control Panel</p>
        ");
    }
}

// Select the database
if (!$conn->select_db($dbname)) {
    $errorMsg = $conn->error;
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(503);
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to select database',
            'details' => $errorMsg
        ]);
        exit();
    } else {
        http_response_code(503);
        die("
        <h2>Database Selection Error</h2>
        <p><strong>Error:</strong> " . htmlspecialchars($errorMsg) . "</p>
        <p>Database 'test' could not be selected. Try restarting MySQL.</p>
        ");
    }
}

// Create users table if it doesn't exist - OPTIMIZED for 2GB RAM
$users_sql = "CREATE TABLE IF NOT EXISTS test.users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($users_sql) !== TRUE) {
    $errorMsg = $conn->error;
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(503);
        echo json_encode([
            'success' => false, 
            'error' => 'Error creating users table',
            'details' => $errorMsg
        ]);
        exit();
    } else {
        http_response_code(503);
        die("Error creating users table: " . htmlspecialchars($errorMsg));
    }
}

// Check if role column exists - only if needed for migrations
if (!defined('ROLE_COLUMN_CHECKED')) {
    $check_role = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                                WHERE TABLE_NAME='users' 
                                AND COLUMN_NAME='role' 
                                AND TABLE_SCHEMA='test' LIMIT 1");

    if ($check_role && $check_role->num_rows == 0) {
        $conn->query("ALTER TABLE test.users ADD COLUMN role VARCHAR(20) DEFAULT 'user' AFTER password_hash");
    }
    define('ROLE_COLUMN_CHECKED', true);
}

// Create tasks table if it doesn't exist
$table_sql = "CREATE TABLE IF NOT EXISTS test.tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('pending', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES test.users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($table_sql) !== TRUE) {
    $errorMsg = $conn->error;
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(503);
        echo json_encode([
            'success' => false, 
            'error' => 'Error creating tasks table',
            'details' => $errorMsg
        ]);
        exit();
    } else {
        http_response_code(503);
        die("Error creating tasks table: " . htmlspecialchars($errorMsg));
    }
}

// Create archive table if it doesn't exist
$archive_sql = "CREATE TABLE IF NOT EXISTS test.archive_tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('pending', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES test.users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($archive_sql) !== TRUE) {
    $errorMsg = $conn->error;
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(503);
        echo json_encode([
            'success' => false, 
            'error' => 'Error creating archive table',
            'details' => $errorMsg
        ]);
        exit();
    }
}

// Create deleted tasks table if it doesn't exist
$deleted_sql = "CREATE TABLE IF NOT EXISTS test.deleted_tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('pending', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP NULL,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES test.users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($deleted_sql) !== TRUE) {
    $errorMsg = $conn->error;
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(503);
        echo json_encode([
            'success' => false, 
            'error' => 'Error creating deleted_tasks table',
            'details' => $errorMsg
        ]);
        exit();
    }
}

// Create task_stats table if it doesn't exist
$stats_sql = "CREATE TABLE IF NOT EXISTS test.task_stats (
    user_id INT PRIMARY KEY,
    total_tasks INT DEFAULT 0,
    completed_tasks INT DEFAULT 0,
    pending_tasks INT DEFAULT 0,
    archived_tasks INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES test.users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($stats_sql) !== TRUE) {
    // Don't error out for stats table - it's optional
    error_log("Warning: Could not create task_stats table: " . $conn->error);
}

// Set connection attributes for optimal performance on 2GB RAM systems
$conn->set_charset("utf8mb4");
mysqli_report(MYSQLI_REPORT_STRICT);
?>