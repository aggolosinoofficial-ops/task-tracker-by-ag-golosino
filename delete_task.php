<?php
/**
 * Delete Task Handler - Enhanced Version
 * Deletes a task for the authenticated user
 * - Requires authentication with session timeout check
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

    $task_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($task_id <= 0) {
        throw new Exception('Invalid task ID');
    }

    // First, get the task details to archive it
    // Retrieve all necessary info for archiving and backup
    $getStmt = $conn->prepare("SELECT id, title, description, status, created_at FROM " . DB_NAME . "." . DB_TABLE_TASKS . " WHERE id = ? AND user_id = ?");
    if (!$getStmt) {
        throw new Exception('Database error');
    }

    $getStmt->bind_param("ii", $task_id, $user_id);
    $getStmt->execute();
    $result = $getStmt->get_result();

    if ($result->num_rows === 0) {
        $getStmt->close();
        if ($isAjax) {
            http_response_code(403);
            throw new Exception('Task not found or permission denied');
        } else {
            header("Location: tasks.php?error=Task not found or permission denied");
            exit();
        }
    }

    $task = $result->fetch_assoc();
    $getStmt->close();

    // Archive the task (insert into archive_tasks table)
    // This moves the task from active to archive, preserving data for recovery
    $archiveStmt = $conn->prepare(
        "INSERT INTO " . ARCHIVE_TABLE . " (user_id, title, description, status, created_at, archived_at)
        VALUES (?, ?, ?, ?, NOW(), NOW())"
    );
    if (!$archiveStmt) {
        throw new Exception('Database error');
    }

    $archiveStmt->bind_param("isss", $user_id, $task['title'], $task['description'], $task['status']);
    if (!$archiveStmt->execute()) {
        $archiveStmt->close();
        throw new Exception('Failed to archive task');
    }
    
    // Get the archive ID that was just created
    $archive_id = $archiveStmt->insert_id;
    $archiveStmt->close();

    // Delete task from active table
    $stmt = $conn->prepare("DELETE FROM " . DB_NAME . "." . DB_TABLE_TASKS . " WHERE id = ? AND user_id = ?");
    if (!$stmt) {
        throw new Exception('Database error');
    }

    $stmt->bind_param("ii", $task_id, $user_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
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