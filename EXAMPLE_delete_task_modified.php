<?php
/**
 * EXAMPLE: Modified delete_task.php for XML and MySQL compatibility
 * Shows how to delete tasks from either backend
 */

include 'auth_check.php';
include 'config_xml.php';
include 'db.php';

header('Content-Type: application/json');

$user_id = checkAuth();
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in']);
    exit();
}

$task_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (!$task_id) {
    echo json_encode(['success' => false, 'error' => 'Task ID is required']);
    exit();
}

// Delete task using configured backend
function exampleDeleteTask($conn, $taskId)
{
    $db = getDatabase($conn);
    return $db->deleteTask($taskId);
}

$result = exampleDeleteTask($conn, $task_id);
echo json_encode($result);
?>