<?php
/**
 * Delete Task Handler - Enhanced Version
 * Deletes a task for the authenticated user
 * - Requires authentication with session timeout check
 * - CSRF token validation to prevent CSRF attacks
 * - Verifies user owns the task before deletion
 * - Handles both AJAX and form submissions
 * - Returns JSON for AJAX, redirects for form submissions
 * - Synchronizes deletion to XML backup
 */

include 'auth_check.php';
include 'xml_sync_handler.php';

// Check if this is an AJAX request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
    header('Content-Type: application/json');
}

try {
    // Check if user is authenticated
    $user_id = checkAuth();
    if (!$user_id) {
        if ($isAjax) {
            http_response_code(401);
            throw new Exception('Please log in');
        } else {
            header('Location: login.html');
            exit();
        }
    }

    // Only POST requests allowed
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        if ($isAjax) {
            http_response_code(405);
            throw new Exception('Invalid request method');
        } else {
            header('Location: tasks.php');
            exit();
        }
    }

    // ✅ SECURITY: Validate CSRF token
    $csrf_token = isset($_POST['csrf_token']) ? trim($_POST['csrf_token']) : '';
    if (!verifyCSRFToken($csrf_token)) {
        if ($isAjax) {
            http_response_code(403);
            throw new Exception('Invalid request token. Please refresh and try again');
        } else {
            header("Location: tasks.php?error=Invalid request token");
            exit();
        }
    }

    $task_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($task_id <= 0) {
        throw new Exception('Invalid task ID');
    }

    $sync = getXMLSyncHandler();

    // STEP 1: GET TASK FROM XML (PRIMARY STORAGE)
    $task = $sync->getTaskFromXML($task_id, $user_id);
    if (!$task) {
        // FALLBACK: Try database if XML fails
        if (isset($conn) && $conn->ping()) {
            $getStmt = $conn->prepare("SELECT id, title, description, status, created_at FROM " . DB_NAME . "." . DB_TABLE_TASKS . " WHERE id = ? AND user_id = ?");
            if ($getStmt) {
                $getStmt->bind_param("ii", $task_id, $user_id);
                $getStmt->execute();
                $result = $getStmt->get_result();
                if ($result->num_rows > 0) {
                    $task = $result->fetch_assoc();
                }
                $getStmt->close();
            }
        }
        
        if (!$task) {
            if ($isAjax) {
                http_response_code(403);
                throw new Exception('Task not found or permission denied');
            } else {
                header("Location: tasks.php?error=Task not found or permission denied");
                exit();
            }
        }
    }

    // STEP 2: DELETE FROM XML (PRIMARY STORAGE - CRITICAL)
    $xml_delete_success = $sync->deleteTaskFromXML($task_id, $user_id);
    if (!$xml_delete_success) {
        if ($isAjax) {
            http_response_code(403);
            throw new Exception('Failed to delete task from primary storage');
        } else {
            header("Location: tasks.php?error=Failed to delete task");
            exit();
        }
    }

    // STEP 3: SYNC DELETE TO DATABASE (SECONDARY STORAGE - NON-CRITICAL)
    $db_sync_success = false;
    $db_error = null;
    
    if (isset($conn) && $conn->ping()) {
        $stmt = $conn->prepare("DELETE FROM " . DB_NAME . "." . DB_TABLE_TASKS . " WHERE id = ? AND user_id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $task_id, $user_id);
            $db_sync_success = $stmt->execute();
            if (!$db_sync_success) {
                $db_error = $stmt->error;
            }
            $stmt->close();
        } else {
            $db_error = $conn->error;
        }
    }

    if ($isAjax) {
        echo json_encode([
            'success' => true, 
            'message' => 'Task deleted successfully',
            'storage' => [
                'xml' => 'primary ✓',
                'database' => $db_sync_success ? 'synced ✓' : ($db_error ? 'failed: ' . $db_error : 'unavailable')
            ]
        ]);
    } else {
            // Sync task deletion from active tasks to XML backup
            $sync = getXMLSyncHandler();
            $sync->syncTaskDeleteToXML($task_id);
            
            // Sync the archived task to archive_tasks.xml for backup recovery
            // This ensures the archived task is preserved in XML alongside MySQL
            $sync->syncArchiveTaskToXML(
                $archive_id, 
                $user_id, 
                $task['title'], 
                $task['description'], 
                $task['status'], 
                $task['created_at'],  // Original task creation time
                date('Y-m-d H:i:s')   // Current timestamp for archive time
            );
            
            // Task was archived successfully
            $stmt->close();

            // Update task statistics (decrease active count, increase archive count)
            updateTaskStats($user_id);

            if ($isAjax) {
                echo json_encode(['success' => true, 'message' => 'Task archived']);
            } else {
                header("Location: tasks.php?success=Task archived successfully");
            }
        } else {
            // Task not found (shouldn't happen since we checked earlier)
            $stmt->close();
            if ($isAjax) {
                http_response_code(403);
                throw new Exception('Failed to archive task');
            } else {
                header("Location: tasks.php?error=Failed to archive task");
            }
        }
    } else {
        throw new Exception('Database error while archiving task');
    }

} catch (Exception $e) {
    if ($isAjax) {
        http_response_code($e->getCode() ?: 400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } else {
        header("Location: tasks.php?error=" . urlencode($e->getMessage()));
    }
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}
?>