<?php
/**
 * Get Tasks Handler - Enhanced Version
 * Retrieves all tasks for the authenticated user with pagination
 * - Session timeout validation
 * - Filters tasks by user_id for data isolation
 * - Implements pagination to reduce memory usage
 * - Returns tasks in order of creation
 * - Improved error handling
 */

include 'auth_check.php';

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

    // OPTIMIZED: Single query with LIMIT for memory efficiency
    $sql = "SELECT id, title, description, status, created_at FROM " . DB_NAME . "." . DB_TABLE_TASKS . " 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param("iii", $user_id, $per_page, $offset);
    if (!$stmt->execute()) {
        throw new Exception('Failed to fetch tasks: ' . $stmt->error);
    }

    $result = $stmt->get_result();

    // OPTIMIZED: Stream results to reduce memory
    $tasks = [];
    while ($row = $result->fetch_assoc()) {
        $tasks[] = [
            'id' => intval($row['id']),
            'title' => htmlspecialchars($row['title']),
            'description' => htmlspecialchars($row['description']),
            'status' => $row['status'],
            'created_at' => $row['created_at']
        ];
    }

    $stmt->close();

    // Get total count for pagination
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM " . DB_NAME . "." . DB_TABLE_TASKS . " WHERE user_id = ?");
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total = intval($count_result->fetch_assoc()['total'] ?? 0);
    $count_stmt->close();

    // Return response with pagination info
    echo json_encode([
        'success' => true,
        'data' => $tasks,
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => ceil($total / $per_page)
        ]
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