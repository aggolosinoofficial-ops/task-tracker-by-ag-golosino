<?php

/**
 * Quick System Test
 * Checks database, authentication, and task operations
 */

header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>";
echo "<html><head><title>System Test</title>";
echo "<style>body{font-family:Arial;margin:20px;} .ok{color:green;} .error{color:red;} .test{padding:10px;border:1px solid #ccc;margin:10px 0;}</style>";
echo "</head><body>";
echo "<h1>🔧 To-Do App System Test</h1>";

// Test 1: Database Connection
echo "<div class='test'>";
echo "<h2>Test 1: Database Connection</h2>";
include 'db.php';
if ($conn && !$conn->connect_error) {
    echo "<p class='ok'>✓ MySQL connected successfully</p>";

    // Check if users table exists
    $result = $conn->query("SELECT COUNT(*) as count FROM test.users");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p class='ok'>✓ Users table exists (" . $row['count'] . " users)</p>";
    } else {
        echo "<p class='error'>✗ Users table not found</p>";
    }

    // Check if tasks table exists
    $result = $conn->query("SELECT COUNT(*) as count FROM test.tasks");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p class='ok'>✓ Tasks table exists (" . $row['count'] . " tasks)</p>";
    } else {
        echo "<p class='error'>✗ Tasks table not found</p>";
    }
} else {
    echo "<p class='error'>✗ Database connection failed: " . $conn->connect_error . "</p>";
}
echo "</div>";

// Test 2: Admin User
echo "<div class='test'>";
echo "<h2>Test 2: Admin Account</h2>";
$stmt = $conn->prepare("SELECT id, username FROM test.users WHERE username = 'admin123'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        echo "<p class='ok'>✓ Admin account exists</p>";
    } else {
        echo "<p class='error'>✗ Admin account not found</p>";
    }
    $stmt->close();
} else {
    echo "<p class='error'>✗ Query error: " . $conn->error . "</p>";
}
echo "</div>";

// Test 3: Session & Auth Functions
echo "<div class='test'>";
echo "<h2>Test 3: Auth Functions</h2>";
include 'auth_check.php';
if (function_exists('checkAuth')) {
    echo "<p class='ok'>✓ checkAuth() function exists</p>";
} else {
    echo "<p class='error'>✗ checkAuth() function not found</p>";
}
if (function_exists('loginUser')) {
    echo "<p class='ok'>✓ loginUser() function exists</p>";
} else {
    echo "<p class='error'>✗ loginUser() function not found</p>";
}
if (function_exists('generateCSRFToken')) {
    echo "<p class='ok'>✓ generateCSRFToken() function exists</p>";
} else {
    echo "<p class='error'>✗ generateCSRFToken() function not found</p>";
}
echo "</div>";

// Test 4: JavaScript Files
echo "<div class='test'>";
echo "<h2>Test 4: Static Files</h2>";
$files = [
    'script.js' => 'JavaScript',
    'style.css' => 'CSS',
    'login.html' => 'Login Page',
    'register.html' => 'Register Page',
    'index.php' => 'Add Task Page',
    'tasks.php' => 'All Tasks Page'
];

foreach ($files as $file => $desc) {
    if (file_exists($file)) {
        echo "<p class='ok'>✓ $desc ($file)</p>";
    } else {
        echo "<p class='error'>✗ $desc ($file) not found</p>";
    }
}
echo "</div>";

// Test 5: API Endpoints
echo "<div class='test'>";
echo "<h2>Test 5: API Endpoints Check</h2>";
$endpoints = [
    'get_csrf_token.php',
    'login.php',
    'register.php',
    'add_task.php',
    'get_tasks.php',
    'edit_task.php',
    'toggle_task.php',
    'delete_task.php'
];

foreach ($endpoints as $endpoint) {
    if (file_exists($endpoint)) {
        echo "<p class='ok'>✓ $endpoint exists</p>";
    } else {
        echo "<p class='error'>✗ $endpoint missing</p>";
    }
}
echo "</div>";

echo "<hr>";
echo "<p><a href='login.html'>← Go to Login</a> | <a href='index.php'>Go to App</a></p>";
echo "</body></html>";
