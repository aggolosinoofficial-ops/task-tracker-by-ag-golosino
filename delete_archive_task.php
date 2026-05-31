<?php
/**
 * Permanently Delete Archived Task Handler
 * Removes an archived task permanently from the database
 * - Requires authentication
 * - Verifies user owns the archived task
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

    $archive_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    // Validate input
    if ($archive_id <= 0) {
        throw new Exception('Invalid archive ID');
    }

    // Fetch the archived task row so we can record its deletion in deleted_tasks.xml
    $getStmt = $conn->prepare("SELECT id, user_id, title, description, status, created_at, archived_at FROM " . DB_NAME . ".archive_tasks WHERE id = ? AND user_id = ?");
    if (!$getStmt) {
        throw new Exception('Database error');
    }
    $getStmt->bind_param("ii", $archive_id, $user_id);
    $getStmt->execute();
    $res = $getStmt->get_result();
    if ($res->num_rows === 0) {
        $getStmt->close();
        throw new Exception('Task not found');
    }
    $archived = $res->fetch_assoc();
    $getStmt->close();

    // Permanently delete archived task
    $stmt = $conn->prepare("DELETE FROM " . DB_NAME . ".archive_tasks WHERE id = ? AND user_id = ?");
    if (!$stmt) {
        throw new Exception('Database error');
    }

    $stmt->bind_param("ii", $archive_id, $user_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Record the permanent deletion in the deleted_tasks MySQL table for audit and recovery.
            // This keeps a database-level history of every permanent deletion.
            $deleteStmt = $conn->prepare("INSERT INTO " . DB_NAME . "." . DELETED_TABLE . " (user_id, title, description, status, created_at, deleted_at) VALUES (?, ?, ?, ?, ?, NOW())");
            if ($deleteStmt) {
                $createdAt = $archived['created_at'] ?: date('Y-m-d H:i:s');
                $deleteStmt->bind_param("issss", $archived['user_id'], $archived['title'], $archived['description'], $archived['status'], $createdAt);
                $deleteStmt->execute();
                $deletedTaskId = $deleteStmt->insert_id;
                $deleteStmt->close();

                // Synchronize the deleted task record to the XML backup file too.
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
            }

            // Remove the archived task from archive_tasks.xml because it no longer exists in the archive table.
            $sync = getXMLSyncHandler();
            $sync->syncRemoveArchiveTaskFromXML($archive_id);

            // Update task statistics to keep counts consistent
            updateTaskStats($user_id);

            echo json_encode(['success' => true, 'message' => 'Task permanently deleted']);
        } else {
            throw new Exception('Task not found');
        }
    } else {
        throw new Exception('Failed to delete task');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}
?>