<?php
/**
 * Get Tasks Handler - XML-First Architecture
 * Retrieves all tasks for the authenticated user with pagination
 * - XML is PRIMARY storage (OLTP - reads first)
 * - Database is SECONDARY storage (OLAP - fallback)
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
        throw new Exception('Please log in to view tasks');
    }

    // Get pagination parameters (default: 50 tasks per page)
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = isset($_GET['limit']) ? min(intval($_GET['limit']), 100) : 50;
    $offset = ($page - 1) * $per_page;

    $sync = getXMLSyncHandler();
    $tasks = [];
    $total = 0;
    $data_source = 'xml';

    // STEP 1: TRY TO READ FROM XML (PRIMARY STORAGE)
    try {
        $xml_tasks = $sync->getTasksFromXML($user_id);
        if ($xml_tasks !== false && is_array($xml_tasks)) {
            // Sort by created_at DESC
            usort($xml_tasks, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            
            $total = count($xml_tasks);
            $tasks = array_slice($xml_tasks, $offset, $per_page);
            $data_source = 'xml';
        }
    } catch (Exception $e) {
        error_log('[GetTasks] XML read failed: ' . $e->getMessage());
    }

    // STEP 2: FALLBACK TO DATABASE if XML failed or empty
    if (empty($tasks) && isset($conn) && $conn->ping()) {
        try {
            $sql = "SELECT id, title, description, status, created_at FROM " . DB_NAME . "." . DB_TABLE_TASKS . " 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT ? OFFSET ?";

            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("iii", $user_id, $per_page, $offset);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $tasks[] = [
                            'id' => intval($row['id']),
                            'title' => htmlspecialchars($row['title']),
                            'description' => htmlspecialchars($row['description']),
                            'status' => $row['status'],
                            'created_at' => $row['created_at']
                        ];
                    }
                }
                $stmt->close();
                
                // Get total count
                $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM " . DB_NAME . "." . DB_TABLE_TASKS . " WHERE user_id = ?");
                if ($count_stmt) {
                    $count_stmt->bind_param("i", $user_id);
                    $count_stmt->execute();
                    $count_result = $count_stmt->get_result();
                    $total = intval($count_result->fetch_assoc()['total'] ?? 0);
                    $count_stmt->close();
                }
                $data_source = 'database';
            }
        } catch (Exception $e) {
            error_log('[GetTasks] Database fallback failed: ' . $e->getMessage());
        }
    }

    // Sanitize output
    foreach ($tasks as &$task) {
        $task['title'] = htmlspecialchars($task['title'] ?? '');
        $task['description'] = htmlspecialchars($task['description'] ?? '');
    }

    // Return response with pagination info and data source
    echo json_encode([
        'success' => true,
        'data' => $tasks,
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => ceil($total / $per_page)
        ],
        'data_source' => $data_source
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($count_stmt)) {
        $count_stmt->close();
    }
}
?>