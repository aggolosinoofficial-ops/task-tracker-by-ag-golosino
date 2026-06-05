<?php
/**
 * Delete Task Handler - XML-First (Archive via XML)
 * Fixes previous PHP parse error and returns valid JSON for AJAX.
 */

include 'auth_check.php';
include 'xml_sync_handler.php';

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($isAjax) {
    header('Content-Type: application/json');
}

try {
    $user_id = checkAuth();
    if (!$user_id) {
        if ($isAjax) {
            http_response_code(401);
            throw new Exception('Please log in');
        }
        header('Location: login.html');
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        if ($isAjax) {
            http_response_code(405);
            throw new Exception('Invalid request method');
        }
        header('Location: tasks.php');
        exit();
    }

    $csrf_token = isset($_POST['csrf_token']) ? trim($_POST['csrf_token']) : '';
    if (!verifyCSRFToken($csrf_token)) {
        if ($isAjax) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit();
        }
        header('Location: tasks.php?error=Invalid request token');
        exit();
    }


    $task_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($task_id <= 0) {
        throw new Exception('Invalid task ID');
    }

    $sync = getXMLSyncHandler();

    // STEP 1: Load task from XML (primary)
    $task = $sync->getTaskFromXML($task_id, $user_id);
    if (!$task) {
        if ($isAjax) {
            http_response_code(403);
            throw new Exception('Task not found or permission denied');
        }
        header('Location: tasks.php?error=Task not found or permission denied');
        exit();
    }

    // STEP 2: Archive from XML
    // (move to archive_tasks.xml backup)
    $archiveOk = $sync->archiveTaskFromXML($task_id, $user_id);
    if (!$archiveOk) {
        if ($isAjax) {
            http_response_code(403);
            throw new Exception('Failed to archive task in primary storage');
        }
        header('Location: tasks.php?error=Failed to archive task');
        exit();
    }

    // STEP 3: Best-effort sync to MySQL (non-critical)
    $db_sync_success = false;
    $db_error = null;

    if (isset($conn) && $conn && $conn->ping()) {
        try {
            // Insert into archive table
            $status = $task['status'] ?? 'pending';
            $created_at = $task['created_at'] ?? date('Y-m-d H:i:s');

            $insert = $conn->prepare(
                "INSERT INTO " . DB_NAME . "." . ARCHIVE_TABLE . " (user_id, title, description, status, created_at) VALUES (?, ?, ?, ?, ?)"
            );
            if ($insert) {
                $title = $task['title'] ?? '';
                $description = $task['description'] ?? '';
                $insert->bind_param('issss', $user_id, $title, $description, $status, $created_at);
                $insert->execute();
                $insert->close();
            }

            // Delete from active tasks
            $stmt = $conn->prepare(
                "DELETE FROM " . DB_NAME . "." . DB_TABLE_TASKS . " WHERE id = ? AND user_id = ?"
            );
            if ($stmt) {
                $stmt->bind_param('ii', $task_id, $user_id);
                $db_sync_success = $stmt->execute();
                if (!$db_sync_success) {
                    $db_error = $stmt->error;
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            $db_error = $e->getMessage();
        }
    }

    if ($isAjax) {
        echo json_encode([
            'success' => true,
            'message' => 'Task archived successfully',
            'storage' => [
                'xml' => 'primary ✓',
                'database' => $db_sync_success ? 'synced ✓' : ($db_error ? 'failed: ' . $db_error : 'unavailable')
            ]
        ]);
        exit();
    }

    header('Location: tasks.php?success=Task archived successfully');
    exit();

} catch (Exception $e) {
    if ($isAjax) {
        http_response_code($e->getCode() ?: 400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } else {
        header('Location: tasks.php?error=' . urlencode($e->getMessage()));
    }
    exit();
}
