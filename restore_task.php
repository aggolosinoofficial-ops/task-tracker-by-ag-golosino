<?php
/**
 * Restore Task Handler
 * Restores an archived task back to active tasks
 * - Requires authentication
 * - Verifies user owns the archived task
 * - Syncs restoration to both tasks.xml and archive_tasks.xml
 * - Returns JSON response
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

    // Get archived task details
    $stmt = $conn->prepare("SELECT id, title, description, status, created_at FROM " . DB_NAME . ".archive_tasks WHERE id = ? AND user_id = ?");
    if (!$stmt) {
        throw new Exception('Database error');
    }

    $stmt->bind_param("ii", $archive_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Archived task not found');
    }

    $task = $result->fetch_assoc();
    $stmt->close();

    // Insert back into tasks table
    // This moves the task from archive back to active tasks while preserving the original creation timestamp
    $stmt = $conn->prepare("INSERT INTO " . DB_NAME . "." . DB_TABLE_TASKS . " (user_id, title, description, status, created_at) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception('Database error');
    }

    $stmt->bind_param("issss", $user_id, $task['title'], $task['description'], $task['status'], $task['created_at']);

    if ($stmt->execute()) {
        // Get the newly created task ID for XML sync
        $new_task_id = $stmt->insert_id;
        $stmt->close();

        // Delete from archive table in MySQL
        // This removes the task from the archive in the database
        $stmt = $conn->prepare("DELETE FROM " . DB_NAME . ".archive_tasks WHERE id = ? AND user_id = ?");
        if (!$stmt) {
            throw new Exception('Database error');
        }

        $stmt->bind_param("ii", $archive_id, $user_id);

        if ($stmt->execute()) {
            $stmt->close();
            
            // Sync the restoration to XML files
            // 1. Add the restored task to active tasks.xml
            // 2. Remove it from archive_tasks.xml
            $sync = getXMLSyncHandler();
            
            // Sync the restored task to active tasks.xml
            $sync->syncTaskToXML(
                $new_task_id,                  // New task ID generated after insert
                $user_id,                      // Task owner
                $task['title'],                // Task name
                $task['description'],          // Task details
                $task['status'],               // Restored status
                $task['created_at']            // Preserve original created_at value
            );
            
            // Remove from archive_tasks.xml to maintain sync
            $sync->syncRemoveArchiveTaskFromXML($archive_id);
            
            // Update task statistics (increase active count, decrease archive count)
            updateTaskStats($user_id);

            echo json_encode(['success' => true, 'message' => 'Task restored successfully']);
        } else {
            throw new Exception('Failed to restore task');
        }
    } else {
        throw new Exception('Failed to restore task');
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