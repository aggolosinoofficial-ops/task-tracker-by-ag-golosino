<?php
/**
 * Edit Task Handler - Enhanced Version
 * Updates a task for the authenticated user
 * - Requires authentication with session timeout check
 * - Verifies user owns the task before updating
 * - Validates input
 * - Handles both AJAX and form submissions
 * - Returns JSON for AJAX, redirects for form submissions
 * - Protects against unauthorized access
 * - Synchronizes updates to XML backup
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
            header("Location: tasks.php");
            exit();
        }
    }

    // Get and validate input
    $task_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    // Validate input
    if ($task_id <= 0) {
        throw new Exception('Invalid task ID');
    }

    if (empty($title)) {
        throw new Exception('Task title is required');
    }

    if (strlen($title) > 255) {
        throw new Exception('Task title is too long');
    }

    if (strlen($description) > 1000) {
        throw new Exception('Task description is too long');
    }

    // Update task ONLY if it belongs to the logged-in user
    // This prevents users from updating other users' tasks
    $stmt = $conn->prepare("UPDATE " . DB_NAME . "." . DB_TABLE_TASKS . " SET title = ?, description = ? WHERE id = ? AND user_id = ?");
    if (!$stmt) {
        throw new Exception('Database error');
    }

    $stmt->bind_param("ssii", $title, $description, $task_id, $user_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Sync task update to XML backup
            $sync = getXMLSyncHandler();
            $sync->syncTaskUpdateToXML($task_id, ['title' => $title, 'description' => $description]);
            
            // Task was updated successfully
            if ($isAjax) {
                echo json_encode(['success' => true, 'message' => 'Task updated']);
            } else {
                header("Location: tasks.php?success=Task updated");
            }
        } else {
            // Task not found or doesn't belong to user
            if ($isAjax) {
                http_response_code(403);
                throw new Exception('Task not found or permission denied');
            } else {
                header("Location: tasks.php?error=Task not found or permission denied");
            }
        }
    } else {
        throw new Exception('Failed to update task');
    }

} catch (Exception $e) {
    if (isset($stmt)) {
        $stmt->close();
    }
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