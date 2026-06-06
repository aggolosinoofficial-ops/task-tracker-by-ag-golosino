<?php
/**
 * Permanently Delete Archived Task Handler
 * Fixed: Explicitly handles database connection scope
 */

// 1. Include dependencies
// Make sure these paths are correct relative to your file location
include_once 'config.php';
include_once 'db.php';
include_once 'auth_check.php';
include_once 'xml_sync_handler.php';

// 2. Bring $conn into global scope
// This ensures that even if $conn was initialized in db.php, 
// this script can access it.
global $conn;

header('Content-Type: application/json');

try {
    // 3. Authenticate user
    $user_id = checkAuth();
    if (!$user_id) {
        throw new Exception('Please log in');
    }

    // 4. Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // 5. Get and validate input
    $archive_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($archive_id <= 0) {
        throw new Exception('Invalid archive ID');
    }

    // 6. Check if connection exists
    if (!isset($conn) || ($conn instanceof mysqli && $conn->connect_error)) {
        throw new Exception('Database connection failed');
    }

    // 7. Start Database Transaction
    $conn->begin_transaction();

    // 8. Fetch the task data
    $stmt = $conn->prepare("SELECT user_id, title, description, status, created_at FROM " . DB_NAME . ".archive_tasks WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $archive_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $archived = $res->fetch_assoc();
    $stmt->close();

    if (!$archived) {
        throw new Exception('Task not found');
    }

    // 9. Delete the record
    $delStmt = $conn->prepare("DELETE FROM " . DB_NAME . ".archive_tasks WHERE id = ? AND user_id = ?");
    $delStmt->bind_param("ii", $archive_id, $user_id);
    $delStmt->execute();
    
    if ($delStmt->affected_rows === 0) {
        throw new Exception('Failed to delete task');
    }
    $delStmt->close();

    // 10. Insert into the deleted_tasks table (Audit Log)
    $logStmt = $conn->prepare("INSERT INTO " . DB_NAME . "." . DELETED_TABLE . " (user_id, title, description, status, created_at, deleted_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $createdAt = $archived['created_at'] ?: date('Y-m-d H:i:s');
    $logStmt->bind_param("issss", $archived['user_id'], $archived['title'], $archived['description'], $archived['status'], $createdAt);
    $logStmt->execute();
    $deletedTaskId = $logStmt->insert_id;
    $logStmt->close();

    // 11. Commit the transaction
    $conn->commit();

    // 12. Sync with XML
    $sync = getXMLSyncHandler();
    $sync->syncDeletedTaskToXML(
        $deletedTaskId,
        $archived['user_id'],
        $archived['title'],
        $archived['description'],
        $archived['status'],
        $createdAt,
        date('Y-m-d H:i:s')
    );
    $sync->syncRemoveArchiveTaskFromXML($archive_id);

    // 13. Update Statistics
    updateTaskStats($user_id);

    echo json_encode(['success' => true, 'message' => 'Task permanently deleted']);

} catch (Exception $e) {
    // Rollback if something failed
    if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
        $conn->rollback();
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} finally {
    // Always close connection
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>