<?php

 //Add Task Handler - Enhanced Version
 // Creates a new task for the authenticated user
 //- Session timeout validation
// - User ownership verification
 // - Input validation
 // - Prepared statements for SQL safety
 // - XML synchronization for backup
 

include 'auth_check.php';
include 'xml_sync_handler.php';

header('Content-Type: application/json');

try {
    // Check if user is authenticated
    $user_id = checkAuth();
    if (!$user_id) {
        http_response_code(401);
        throw new Exception('Please log in to add tasks');
    }

    // Only POST requests allowed
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Invalid request method');
    }

    // Get and sanitize input
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    // Validate input
    if (empty($title)) {
        throw new Exception('Task title is required');
    }

    if (strlen($title) > 255) {
        throw new Exception('Task title is too long');
    }

    if (strlen($description) > 1000) {
        throw new Exception('Task description is too long');
    }

    // Insert task for the logged-in user using config constants
    $stmt = $conn->prepare("INSERT INTO " . DB_NAME . "." . DB_TABLE_TASKS . " (user_id, title, description, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param("iss", $user_id, $title, $description);

    if ($stmt->execute()) {
        $task_id = $stmt->insert_id;
        
        // Sync new task to XML backup
        $sync = getXMLSyncHandler();
        $sync->syncTaskToXML($task_id, $user_id, $title, $description, 'pending', date('Y-m-d H:i:s'));
        
        echo json_encode([
            'success' => true,
            'task_id' => $task_id,
            'message' => 'Task added successfully'
        ]);
    } else {
        throw new Exception('Failed to add task: ' . $stmt->error);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
}
?>