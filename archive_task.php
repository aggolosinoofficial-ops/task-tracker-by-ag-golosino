<?php
/**
 * Archive Task Handler
 * Moves a task to archive instead of permanent deletion
 * - Requires authentication
 * - Verifies user owns the task
 * - Returns JSON response
 */




include 'auth_check.php';

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

    $task_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    // Validate input
    if ($task_id <= 0) {
        throw new Exception('Invalid task ID');
    }

    // Get task details before archiving
    $stmt = $conn->prepare("SELECT id, title, description, status, created_at FROM " . DB_NAME . "." . DB_TABLE_TASKS . " WHERE id = ? AND user_id = ?");
    if (!$stmt) {
        throw new Exception('Database error');
    }

    $stmt->bind_param("ii", $task_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Task not found or permission denied');
    }

    $task = $result->fetch_assoc();
    $stmt->close();

    // Insert into archive table
    $stmt = $conn->prepare("INSERT INTO " . DB_NAME . ".archive_tasks (user_id, title, description, status, created_at) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception('Database error');
    }

    $stmt->bind_param("issss", $user_id, $task['title'], $task['description'], $task['status'], $task['created_at']);

    if ($stmt->execute()) {
        $stmt->close();

        // Delete from tasks table
        $stmt = $conn->prepare("DELETE FROM " . DB_NAME . "." . DB_TABLE_TASKS . " WHERE id = ? AND user_id = ?");
        if (!$stmt) {
            throw new Exception('Database error');
        }

        $stmt->bind_param("ii", $task_id, $user_id);

        if ($stmt->execute()) {
            // Update stats
            updateTaskStats($user_id);

            echo json_encode(['success' => true, 'message' => 'Task archived successfully']);
        } else {
            throw new Exception('Failed to archive task');
        }
    } else {
        throw new Exception('Failed to archive task');
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