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
    
    $db_synced = false;
    if (defined('DB_AVAILABLE') && DB_AVAILABLE && isset($conn)) {
        try {
            $stmt = $conn->prepare("SELECT * FROM " . DB_NAME . "." . DB_TABLE_TASKS . " WHERE id = ? AND user_id = ?");
            if ($stmt) {
                $stmt->bind_param('ii', $task_id, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $task = $result->fetch_assoc();
                    $archive_stmt = $conn->prepare("INSERT INTO " . DB_NAME . ".archive_tasks (user_id, title, description, status, created_at) VALUES (?, ?, ?, ?, ?)");
                    if ($archive_stmt) {
                        $archive_stmt->bind_param('issss', $user_id, $task['title'], $task['description'], $task['status'], $task['created_at']);
                        if ($archive_stmt->execute()) {
                            $delete_stmt = $conn->prepare("DELETE FROM " . DB_NAME . "." . DB_TABLE_TASKS . " WHERE id = ? AND user_id = ?");
                            if ($delete_stmt) {
                                $delete_stmt->bind_param('ii', $task_id, $user_id);
                                $db_synced = $delete_stmt->execute();
                                $delete_stmt->close();
                            }
                        }
                        $archive_stmt->close();
                    }
                }
                $stmt->close();
            }
        } catch (Exception $e) {
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