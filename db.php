<?php
declare(strict_types=1);

/**
 * db.php
 * This file handles the database connection and auto-initialization.
 * * Setup:
 * 1. Ensure your MySQL server is running.
 * 2. Update the credentials in the configuration section below.
 */

function getDatabaseConnection(): ?mysqli {


    // --- CONFIGURATION ---
    $host = "localhost";
    $user = "root";       // Change this if your username is different
    $pass = "";           // Change this if you have a password set
    $db   = "task_tracker";       // The database name
    // ---------------------

    // Attempt to connect. The '@' suppresses direct error output so we can handle it.
    $conn = @new mysqli($host, $user, $pass);

    // If connection fails, return null. The Adapter will detect this and switch to XML.
    if ($conn->connect_error) {
        return null;
    }

    // 1. Create the database if it doesn't exist
    $conn->query("CREATE DATABASE IF NOT EXISTS `$db`");

    // 2. Select the database
    $conn->select_db($db);

    // 3. Create the tasks table if it doesn't exist
    // This ensures your table structure is ready immediately.
    $conn->query("CREATE TABLE IF NOT EXISTS tasks (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 
        user_id INT NOT NULL, 
        title VARCHAR(255) NOT NULL, 
        description TEXT, 
        status ENUM('pending', 'completed') DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    return $conn;
}
?>