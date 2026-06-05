<?php
/**
 * Toggle Task Status Handler - XML-First Architecture
 * Changes task status between 'pending' and 'completed'
 * - XML is PRIMARY storage (OLTP - always works)
 * - Database is SECONDARY storage (OLAP - optional sync)
 * - Works even if MySQL is unavailable
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

    // ✅ SECURITY: Validate CSRF token
    $csrf_token = isset($_POST['csrf_token']) ? trim($_POST['csrf_token']) : '';

    if (!verifyCSRFToken($csrf_token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit();
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

    $sync = getXMLSyncHandler();

    // STEP 1: UPDATE IN XML (PRIMARY STORAGE - CRITICAL)
    // Verify user owns task and update
    $xml_update_success = $sync->updateTaskStatusInXML($task_id, $user_id, $status);
    
    if (!$xml_update_success) {
        http_response_code(403);
        throw new Exception('Task not found or permission denied');
    }

    // STEP 2: SYNC TO DATABASE (SECONDARY STORAGE - NON-CRITICAL)
    $db_sync_success = false;
    $db_error = null;
    
    if (isset($conn) && $conn->ping()) {
        $stmt = $conn->prepare("UPDATE " . DB_NAME . "." . DB_TABLE_TASKS . " SET status = ? WHERE id = ? AND user_id = ?");
        if ($stmt) {
            $stmt->bind_param("sii", $status, $task_id, $user_id);
            $db_sync_success = $stmt->execute() && $stmt->affected_rows > 0;
            if (!$db_sync_success) {
                $db_error = $stmt->error;
            }
            $stmt->close();
        } else {
            $db_error = $conn->error;
        }
    }

    // Return success (XML update succeeded, DB sync is bonus)
    echo json_encode([
        'success' => true,
        'message' => 'Task status updated',
        'storage' => [
            'xml' => 'primary ✓',
            'database' => $db_sync_success ? 'synced ✓' : ($db_error ? 'failed: ' . $db_error : 'unavailable')
        ]
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>