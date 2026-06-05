<?php
/**
 * Get Archived Tasks Handler - XML-First Architecture
 * Retrieves archived tasks for user
 * PRIMARY: archive_tasks.xml
 * SECONDARY: DB fallback
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
    
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = isset($_GET['limit']) ? min(intval($_GET['limit']), 100) : 50;
    $offset = ($page - 1) * $per_page;
    
    $tasks = [];
    $total = 0;
    $source = 'xml';
    
    // STEP 1: Try XML first (PRIMARY)
    try {
        $xml_path = __DIR__ . '/archive_tasks.xml';
        if (file_exists($xml_path)) {
            $xml = simplexml_load_file($xml_path);
            if ($xml) {
                $count = 0;
                foreach ($xml->task as $task) {
                    if ((int)$task->user_id === $user_id) {
                        if ($count >= $offset && count($tasks) < $per_page) {
                            $tasks[] = [
                                'id' => (int)$task->id,
                                'title' => (string)$task->title,
                                'description' => (string)$task->description,
                                'status' => (string)$task->status,
                                'created_at' => (string)$task->created_at,
                                'archived_at' => (string)$task->archived_at ?? ''
                            ];
                        }
                        $count++;
                    }
                }
                $total = $count;
            }
        }
    } catch (Exception $e) {
        error_log('[GetArchive] XML read failed: ' . $e->getMessage());
    }
    
    // STEP 2: Fallback to DB if XML empty
    if (empty($tasks) && defined('DB_AVAILABLE') && DB_AVAILABLE && isset($conn)) {
        try {
            $sql = "SELECT id, title, description, status, created_at, archived_at FROM " . DB_NAME . ".archive_tasks WHERE user_id = ? ORDER BY archived_at DESC LIMIT ? OFFSET ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('iii', $user_id, $per_page, $offset);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $tasks[] = $row;
                }
                $stmt->close();
                
                $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM " . DB_NAME . ".archive_tasks WHERE user_id = ?");
                if ($count_stmt) {
                    $count_stmt->bind_param('i', $user_id);
                    $count_stmt->execute();
                    $count_result = $count_stmt->get_result();
                    $total = (int)$count_result->fetch_assoc()['total'];
                    $count_stmt->close();
                }
                $source = 'database';
            }
        } catch (Exception $e) {
            error_log('[GetArchive] DB fallback failed: ' . $e->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $tasks,
        'pagination' => ['page' => $page, 'limit' => $per_page, 'total' => $total],
        'source' => $source
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>