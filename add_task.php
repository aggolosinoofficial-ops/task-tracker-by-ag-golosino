<?php
/**
 * Add Task Handler - XML-First Architecture
 * Creates a new task for the authenticated user
 * - XML is PRIMARY storage (OLTP - always works)
 * - Database is SECONDARY storage (OLAP - optional sync)
 * - Works even if MySQL is unavailable
 * - Auto-syncs to database when available
 */

include 'auth_check.php';
include 'xml_sync_handler.php';

header('Content-Type: application/json');

try {
    // Check if user is authenticated
    $user_id = checkAuth();
    if (!$user_id) {
        http_response_code(401);
        throw new Exception('Please log in to add tasks');
    }

    // Only POST requests allowed
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Invalid request method');
    }

    // Get and sanitize input
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    // Validate input
    if (empty($title)) {
        throw new Exception('Task title is required');
    }

    if (strlen($title) > 255) {
        throw new Exception('Task title is too long');
    }

    if (strlen($description) > 1000) {
        throw new Exception('Task description is too long');
    }

    // Generate task ID (using max ID + 1 from XML or DB)
    $sync = getXMLSyncHandler();
    $task_id = $sync->generateNextTaskId($user_id);
    $created_at = date('Y-m-d H:i:s');

    // STEP 1: INSERT TO XML (PRIMARY STORAGE - CRITICAL)
    // This MUST succeed for operation to continue
    $xml_sync_success = $sync->syncTaskToXML($task_id, $user_id, $title, $description, 'pending', $created_at);
    
    if (!$xml_sync_success) {
        throw new Exception('Failed to save task to primary storage');
    }

    // STEP 2: SYNC TO DATABASE (SECONDARY STORAGE - NON-CRITICAL)
    // Try to sync to database, but don't fail if unavailable
    $db_sync_success = false;
    $db_error = null;
    
    if (isset($conn) && $conn->ping()) {
        $stmt = $conn->prepare("INSERT INTO " . DB_NAME . "." . DB_TABLE_TASKS . " (id, user_id, title, description, status, created_at) VALUES (?, ?, ?, ?, 'pending', ?)");
        if ($stmt) {
            $status = 'pending';
            $stmt->bind_param("iisss", $task_id, $user_id, $title, $description, $created_at);
            $db_sync_success = $stmt->execute();
            if (!$db_sync_success) {
                $db_error = $stmt->error;
            }
            $stmt->close();
        } else {
            $db_error = $conn->error;
        }
    }

    // Return success (XML write succeeded, DB sync is bonus)
    echo json_encode([
        'success' => true,
        'task_id' => $task_id,
        'message' => 'Task added successfully',
        'storage' => [
            'xml' => 'primary ✓',
            'database' => $db_sync_success ? 'synced ✓' : ($db_error ? 'failed: ' . $db_error : 'unavailable')
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>