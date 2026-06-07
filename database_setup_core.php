<?php
// Helper: run the schema SQL for the current DB.
require_once 'config.php';
require_once 'db.php';

$c = getDatabaseConnection();
if (!$c) {
  echo "NO CONNECTION\n";
  exit;
}

$db = DB_NAME;

$sqls = [];
$sqls['users'] = "CREATE TABLE IF NOT EXISTS `$db`.users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$sqls['archive_tasks'] = "CREATE TABLE IF NOT EXISTS `$db`.archive_tasks (
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

$sqls['deleted_tasks'] = "CREATE TABLE IF NOT EXISTS `$db`.deleted_tasks (
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

$sqls['task_stats'] = "CREATE TABLE IF NOT EXISTS `$db`.task_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    total_tasks INT DEFAULT 0,
    completed_tasks INT DEFAULT 0,
    pending_tasks INT DEFAULT 0,
    archived_tasks INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES `$db`.users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// Ensure role column exists in users
$roleAlter = "ALTER TABLE `$db`.users ADD COLUMN role ENUM('user','admin','moderator') NOT NULL DEFAULT 'user' AFTER password_hash";

$checkRole = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db' AND TABLE_NAME='users' AND COLUMN_NAME='role'";

// Ensure tasks.user_id exists
$checkUserId = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db' AND TABLE_NAME='tasks' AND COLUMN_NAME='user_id'";
$tasksAlter = "ALTER TABLE `$db`.tasks ADD COLUMN user_id INT NOT NULL DEFAULT 0 AFTER id, 
                  ADD FOREIGN KEY (user_id) REFERENCES `$db`.users(id) ON DELETE CASCADE,
                  ADD INDEX idx_user_id (user_id)";

$did = [];
foreach ($sqls as $name => $sql) {
  $ok = $c->query($sql);
  if (!$ok) {
    echo "✗ $name error: " . $c->error . "\n";
    exit(1);
  }
  echo "✓ $name ready\n";
}

$check = $c->query($checkRole);
if ($check && $check->num_rows == 0) {
  $ok = $c->query($roleAlter);
  echo $ok ? "✓ role column added\n" : "✗ role alter error: " . $c->error . "\n";
}

$checkTasks = $c->query($checkUserId);
if ($checkTasks && $checkTasks->num_rows == 0) {
  $ok = $c->query($tasksAlter);
  echo $ok ? "✓ tasks.user_id added\n" : "✗ tasks alter error: " . $c->error . "\n";
}

echo "DONE\n";
?>
