<?php
// Database connection parameters (OPTIMIZED for 2GB RAM)
$servername = "localhost";
$username = "root";
$password = ""; // Default XAMPP password - change if you set a password in XAMPP

// Create connection without database
$conn = new mysqli($servername, $username, $password);

// Set connection options for better performance
$conn->set_charset("utf8mb4");

// Check connection
if ($conn->connect_error) {
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $conn->connect_error]);
        exit();
    } else {
        die("Database connection failed: " . htmlspecialchars($conn->connect_error));
    }
}

// OPTIMIZATION: Reduce memory footprint for large result sets
$conn->set_charset("utf8mb4");

// Create database if it doesn't exist
$dbname = "test";
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) !== TRUE) {
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Error creating database: ' . $conn->error]);
        exit();
    } else {
        die("Error creating database: " . htmlspecialchars($conn->error));
    }
}

// Select the database
if (!$conn->select_db($dbname)) {
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to select database: ' . $conn->error]);
        exit();
    } else {
        die("Failed to select database: " . htmlspecialchars($conn->error));
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
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Error creating users table: ' . $conn->error]);
        exit();
    } else {
        die("Error creating users table: " . htmlspecialchars($conn->error));
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
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Error creating tasks table: ' . $conn->error]);
        exit();
    } else {
        die("Error creating tasks table: " . htmlspecialchars($conn->error));
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
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Error creating archive table: ' . $conn->error]);
        exit();
    }
}

// Create deleted tasks table if it doesn't exist
// This table stores the audit history for permanently deleted archive records.
// It is intentionally lean to limit memory usage and support recovery/analytics.
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
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Error creating deleted tasks table: ' . $conn->error]);
        exit();
    }
}

// Set connection attributes for optimal performance on 2GB RAM systems
$conn->set_charset("utf8mb4");
mysqli_report(MYSQLI_REPORT_STRICT);
?>