<?php
/**
 * EXAMPLE: Modified get_tasks.php for XML and MySQL compatibility
 * This shows how to update your existing code with minimal changes
 * 
 * To use this:
 * 1. Include config_xml.php at the top of your existing files
 * 2. Replace $conn->prepare() calls with the database adapter
 * 3. Change DB_BACKEND in config_xml.php to switch between backends
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

include 'auth_check.php';
include 'config_xml.php';
include 'db.php';

header('Content-Type: application/json');

// Check if user is authenticated
$user_id = checkAuth();
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in to view tasks']);
    exit();
}

// Get configured database adapter
function exampleGetTasks($conn, $userId)
{
    $db = getDatabase($conn);
    return $db->getTasks($userId);
}

// Fetch tasks from configured backend
$tasks = exampleGetTasks($conn, $user_id);

// Check for errors
if (isset($tasks['error'])) {
    http_response_code(500);
    echo json_encode(['error' => $tasks['error']]);
    exit();
}

echo json_encode($tasks);
?>