<?php
/**
 * Restore Task Handler - XML-First Architecture
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
    
    $archive_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($archive_id <= 0) throw new Exception('Invalid archive ID');
    
    $sync = getXMLSyncHandler();
    $archived_task = $sync->getArchivedTaskFromXML($archive_id, $user_id);
    
    if (!$archived_task && defined('DB_AVAILABLE') && DB_AVAILABLE && isset($conn)) {
        try {
            $stmt = $conn->prepare("SELECT id, title, description, status, created_at FROM " . DB_NAME . ".archive_tasks WHERE id = ? AND user_id = ?");
            if ($stmt) {
                $stmt->bind_param('ii', $archive_id, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $archived_task = $result->fetch_assoc();
                }
                $stmt->close();
            }
        } catch (Exception $e) {}
    }
    
    if (!$archived_task) throw new Exception('Archived task not found');
    
    $sync_handler = getXMLSyncHandler();
    $task_id = $archived_task['id'];
    $maxId = $sync_handler->generateNextTaskId();
    
    $sync_handler->syncTaskToXML(
        $task_id,
        $user_id,
        $archived_task['title'],
        $archived_task['description'],
        $archived_task['status'],
        $archived_task['created_at']
    );
    
    $sync_handler->syncRemoveArchiveTaskFromXML($archive_id);
    
    $db_synced = false;
    if (defined('DB_AVAILABLE') && DB_AVAILABLE && isset($conn)) {
        try {
            $insert_stmt = $conn->prepare("INSERT INTO " . DB_NAME . "." . DB_TABLE_TASKS . " (user_id, title, description, status, created_at) VALUES (?, ?, ?, ?, ?)");
            if ($insert_stmt) {
                $insert_stmt->bind_param('issss', $user_id, $archived_task['title'], $archived_task['description'], $archived_task['status'], $archived_task['created_at']);
                if ($insert_stmt->execute()) {
                    $delete_stmt = $conn->prepare("DELETE FROM " . DB_NAME . ".archive_tasks WHERE id = ? AND user_id = ?");
                    if ($delete_stmt) {
                        $delete_stmt->bind_param('ii', $archive_id, $user_id);
                        $db_synced = $delete_stmt->execute();
                        $delete_stmt->close();
                    }
                }
                $insert_stmt->close();
            }
        } catch (Exception $e) {
            error_log("[RestoreTask] DB sync error: " . $e->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Task restored successfully',
        'xml_restored' => true,
        'db_synced' => $db_synced
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>"