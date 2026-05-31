<?php
/**
 * Get Archived Tasks Handler
 * Retrieves all archived tasks for the authenticated user
 * - Requires login
 * - Filters tasks by user_id
 * - Returns archived tasks
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

    // Fetch archived tasks for the logged-in user
    $sql = "SELECT id, title, description, status, created_at, archived_at FROM " . DB_NAME . ".archive_tasks 
            WHERE user_id = ? 
            ORDER BY archived_at DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database error');
    }

    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to fetch archived tasks');
    }

    $result = $stmt->get_result();

    $tasks = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
    }

    echo json_encode($tasks);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}
?>