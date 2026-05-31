<?php
/**
 * Toggle Task Status Handler - Enhanced Version
 * Changes task status between 'pending' and 'completed'
 * - Requires authentication with session timeout check
 * - Verifies user owns the task
 * - Input validation and error handling
 * - Returns JSON response
 */

include 'auth_check.php';
include 'xml_sync_handler.php';

header('Content-Type: application/json');

try {
    // Check if user is authenticated
    $user_id = checkAuth();
    if (!$user_id) {
        http_response_code(401);
        throw new Exception('Please log in');
    }

    // Only POST requests allowed
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Invalid request method');
    }

    // Get and validate input
    $task_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';

    // Validate input
    if ($task_id <= 0) {
        throw new Exception('Invalid task ID');
    }

    if (!in_array($status, ['pending', 'completed'])) {
        throw new Exception('Invalid status');
    }

    // Update task status ONLY if it belongs to the logged-in user
    $stmt = $conn->prepare("UPDATE " . DB_NAME . "." . DB_TABLE_TASKS . " SET status = ? WHERE id = ? AND user_id = ?");
    if (!$stmt) {
        throw new Exception('Database error');
    }

    $stmt->bind_param("sii", $status, $task_id, $user_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $sync = getXMLSyncHandler();
            $sync->syncTaskUpdateToXML($task_id, ['status' => $status]);

            echo json_encode(['success' => true, 'message' => 'Task status updated']);
        } else {
            http_response_code(403);
            throw new Exception('Task not found or permission denied');
        }
    } else {
        throw new Exception('Failed to update task');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
}
?>