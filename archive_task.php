<?php
/**
 * Archive Task Handler - XML-First Architecture
 */
include 'auth_check.php';
include 'xml_sync_handler.php';

header('Content-Type: application/json');

try {
    $user_id = checkAuth();
    if (!$user_id) {
        http_response_code(401);
        throw new Exception('Not authenticated');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Invalid method');
    }
    $task_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($task_id <= 0) throw new Exception('Invalid task ID');
    
    $sync = getXMLSyncHandler();
    $xml_success = $sync->archiveTaskFromXML($task_id, $user_id);
    if (!$xml_success) {
        http_response_code(400);
        throw new Exception('Failed to archive task');
    }
    
   // DB sync block
    $db_synced = false;
    if (defined('DB_AVAILABLE') && DB_AVAILABLE && isset($conn)) {
        try {
            // 1. Start transaction
            $conn->begin_transaction();

            // 2. Fetch task (Guard Clause)
            $stmt = $conn->prepare("SELECT * FROM " . DB_NAME . "." . DB_TABLE_TASKS . " WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $task_id, $user_id);
            $stmt->execute();
            $task = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$task) {
                throw new Exception("Task not found in DB.");
            }

            // 3. Insert into Archive
            $archive_stmt = $conn->prepare("INSERT INTO " . DB_NAME . ".archive_tasks (user_id, title, description, status, created_at) VALUES (?, ?, ?, ?, ?)");
            $archive_stmt->bind_param('issss', $user_id, $task['title'], $task['description'], $task['status'], $task['created_at']);
            $archive_stmt->execute();
            $archive_stmt->close();

            // 4. Delete from Tasks
            $delete_stmt = $conn->prepare("DELETE FROM " . DB_NAME . "." . DB_TABLE_TASKS . " WHERE id = ? AND user_id = ?");
            $delete_stmt->bind_param('ii', $task_id, $user_id);
            $delete_stmt->execute();
            $delete_stmt->close();

            // 5. Commit all changes
            $conn->commit();
            $db_synced = true;

        } catch (Exception $e) {
            // Rollback if anything fails
            $conn->rollback();
            error_log("[ArchiveTask] DB sync error: " . $e->getMessage());
        }
    }
    echo json_encode([
        'success' => true,
        'message' => 'Task archived successfully',
        'xml_archived' => true,
        'db_synced' => $db_synced
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>