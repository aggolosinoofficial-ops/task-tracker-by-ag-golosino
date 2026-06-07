<?php

/**
 * Simple Connection Test
 * Tests database and basic functionality
 */

header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Connection Test</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>To-Do App - Connection Test</h1>";

// Test 1: Database Connection
echo "<h2>Test 1: Database Connection</h2>";
try {
    $conn = new mysqli('localhost', 'root', '');
    if ($conn->connect_error) {
        echo "<p class='error'>✗ Failed: " . htmlspecialchars($conn->connect_error) . "</p>";
    } else {
        echo "<p class='success'>✓ MySQL Connected</p>";

        // Test 2: Database Selection
        echo "<h2>Test 2: Database & Tables</h2>";
        if (!$conn->select_db('test')) {
            echo "<p class='error'>✗ Database 'test' not found</p>";
        } else {
            echo "<p class='success'>✓ Database 'test' selected</p>";

            // Check tables
            $tables = ['users', 'tasks', 'archive_tasks', 'task_stats'];
            foreach ($tables as $table) {
                $result = $conn->query("SHOW TABLES LIKE '$table'");
                if ($result && $result->num_rows > 0) {
                    echo "<p class='success'>✓ Table '$table' exists</p>";
                } else {
                    echo "<p class='error'>✗ Table '$table' missing</p>";
                }
            }
        }

        // Test 3: Admin User
        echo "<h2>Test 3: Admin User</h2>";
        $result = $conn->query("SELECT id, username FROM test.users WHERE username = 'admin123'");
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            echo "<p class='success'>✓ Admin user exists (ID: " . $user['id'] . ")</p>";
        } else {
            echo "<p class='error'>✗ Admin user 'admin123' not found</p>";
        }

        // Test 4: File Permissions
        echo "<h2>Test 4: File Checks</h2>";
        $files = [
            'db.php',
            'auth_check.php',
            'config.php',
            'script.js',
            'add_task.php',
            'get_tasks.php',
            'index.php',
            'login.html'
        ];

        foreach ($files as $file) {
            $path = __DIR__ . '/' . $file;
            if (file_exists($path)) {
                echo "<p class='success'>✓ $file exists</p>";
            } else {
                echo "<p class='error'>✗ $file missing</p>";
            }
        }

        $conn->close();
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>Next Steps</h2>";
echo "<p>1. Go to <a href='login.html'>Login Page</a></p>";
echo "<p>2. Login with admin123 / Admin_123</p>";
echo "<p>3. Go to <a href='index.php'>Add New Task</a> and add a task</p>";
echo "<p>4. Go to <a href='tasks.php'>All Tasks</a> to see your tasks</p>";
echo "</body>
</html>";

?>