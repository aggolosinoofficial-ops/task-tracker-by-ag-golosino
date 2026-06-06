<?php
/**
 * Database Setup Script
 * Run this file ONCE to create the necessary tables
 * * SECURITY: This script exposes schema details. 
 * DELETE THIS FILE or RESTRICT ACCESS after setup.
 */

require_once 'config.php';
require_once 'db.php';

header('Content-Type: text/html; charset=UTF-8');

$setup_complete = false;
$messages = [];
$db = DB_NAME; // Use your config-defined database

// --- TABLE CREATION LOGIC ---

// 1. Create Users Table
$sql_users = "CREATE TABLE IF NOT EXISTS `$db`.users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$conn->query($sql_users) ? $messages[] = "✓ Users table ready" : $messages[] = "✗ Error users: " . $conn->error;

// 2. Check/Add Role Column
$check = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db' AND TABLE_NAME='users' AND COLUMN_NAME='role'");
if ($check && $check->num_rows == 0) {
    $conn->query("ALTER TABLE `$db`.users ADD COLUMN role ENUM('user','admin','moderator') NOT NULL DEFAULT 'user' AFTER password_hash");
    $messages[] = "✓ Role column added to users table";
}

// 3. Update Tasks Table (Add user_id if missing)
$check_tasks = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db' AND TABLE_NAME='tasks' AND COLUMN_NAME='user_id'");
if ($check_tasks && $check_tasks->num_rows == 0) {
    $conn->query("ALTER TABLE `$db`.tasks ADD COLUMN user_id INT NOT NULL DEFAULT 0 AFTER id, 
                  ADD FOREIGN KEY (user_id) REFERENCES `$db`.users(id) ON DELETE CASCADE,
                  ADD INDEX idx_user_id (user_id)");
    $messages[] = "✓ user_id column added to tasks table";
}

// 4. Create Archive Table
$sql_archive = "CREATE TABLE IF NOT EXISTS `$db`.archive_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('pending', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP NULL,
    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES `$db`.users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_archived_at (archived_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
$conn->query($sql_archive) ? $messages[] = "✓ Archive tasks table created" : $messages[] = "✗ Error archive: " . $conn->error;

// 5. Create Deleted Table
$sql_deleted = "CREATE TABLE IF NOT EXISTS `$db`.deleted_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('pending', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP NULL,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES `$db`.users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
$conn->query($sql_deleted) ? $messages[] = "✓ Deleted tasks table created" : $messages[] = "✗ Error deleted: " . $conn->error;

// 6. Create Stats Table
$sql_stats = "CREATE TABLE IF NOT EXISTS `$db`.task_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    total_tasks INT DEFAULT 0,
    completed_tasks INT DEFAULT 0,
    pending_tasks INT DEFAULT 0,
    archived_tasks INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES `$db`.users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
$conn->query($sql_stats) ? $messages[] = "✓ Task stats table created" : $messages[] = "✗ Error stats: " . $conn->error;

$setup_complete = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - To-Do App</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background-color: #f5f5f5; }
        .container { background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        .message { margin: 10px 0; padding: 10px; border-left: 4px solid #28a745; background-color: #f0f8f4; }
        .message.error { border-left-color: #dc3545; background-color: #fef5f5; color: #721c24; }
        .success-info { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0; }
        .action-links { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; }
        .action-links a { display: inline-block; margin-right: 10px; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; }
        .action-links a:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Setup</h1>
        <?php foreach ($messages as $msg): ?>
            <div class="message <?php echo strpos($msg, '✗') === 0 ? 'error' : ''; ?>">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endforeach; ?>

        <?php if ($setup_complete): ?>
            <div class="success-info">
                <h3>✓ Setup Complete!</h3>
                <p>Your database tables have been verified/created.</p>
            </div>
        <?php endif; ?>

        <div class="action-links">
            <a href="register.html">Register Account</a>
            <a href="login.html">Login</a>
            <a href="index.php">Go to App</a>
        </div>
    </div>
</body>
</html>