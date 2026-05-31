<?php
/**
 * EXAMPLE: Modified add_task.php for XML and MySQL compatibility
 * Shows minimal changes needed to use both backends
 */

include 'auth_check.php';
include 'config_xml.php';
include 'db.php';

header('Content-Type: application/json');

// Check if user is authenticated
$user_id = checkAuth();
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in to add tasks']);
    exit();
}

// Only POST requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';

// Validate input
if (empty($title)) {
    echo json_encode(['success' => false, 'error' => 'Task title is required']);
    exit();
}

// Add task using configured backend
function exampleAddTask($conn, $userId, $title, $description)
{
    $db = getDatabase($conn);
    return $db->addTask($userId, $title, $description);
}

$result = exampleAddTask($conn, $user_id, $title, $description);
echo json_encode($result);
?>