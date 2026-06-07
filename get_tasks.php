<?php
declare(strict_types=1);

/**
 * get_tasks.php
 * Fetches tasks via the DatabaseAdapter (MySQL or XML) and returns JSON.
 */

header('Content-Type: application/json; charset=UTF-8');

// 1. Include necessary files
require_once 'auth_check.php'; // Ensure your auth logic is included
require_once 'db.php';         // Includes your getDatabaseConnection function
require_once 'db_adapter.php'; // Includes the DatabaseAdapter class

try {
    // 2. Validate User Authentication
    $user_id = checkAuth(); 
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    // 3. Initialize Database Connection and Adapter
    // If getDatabaseConnection returns a link, use MySQL; otherwise, default to XML
    $conn = getDatabaseConnection();
    $db = ($conn) ? new DatabaseAdapter('mysql', $conn) : new DatabaseAdapter('xml');

    // 4. Fetch Tasks
    $tasks = $db->getTasks((int)$user_id);

    // 5. Output JSON Response
    echo json_encode([
        'success' => true,
        'count'   => count($tasks),
        'tasks'   => $tasks
    ]);

} catch (Exception $e) {
    // Log the error internally and return a clean JSON error to the user
    error_log('[get_tasks.php Error]: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'An internal server error occurred.'
    ]);
}
?>