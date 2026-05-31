<?php
/**
 * EXAMPLE: Modified toggle_task.php for XML and MySQL compatibility
 * Shows how to update status toggle
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
$status = isset($_POST['status']) ? trim($_POST['status']) : 'completed';

if (!$task_id) {
    echo json_encode(['success' => false, 'error' => 'Task ID is required']);
    exit();
}

// Update task status using configured backend
function exampleToggleTask($conn, $taskId, $status)
{
    $db = getDatabase($conn);
    return $db->updateTaskStatus($taskId, $status);
}

$result = exampleToggleTask($conn, $task_id, $status);
echo json_encode($result);
?>