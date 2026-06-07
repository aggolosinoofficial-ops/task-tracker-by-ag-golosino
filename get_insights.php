<?php
declare(strict_types=1);

/**
 * Get User Task Insights - Adapter Pattern
 */

require_once 'auth_check.php';
require_once 'db.php';          // Defines the function
require_once 'db_adapter.php';  // Defines the class

header('Content-Type: application/json; charset=UTF-8');

try {
    $user_id = checkAuth();
    if (!$user_id) {
        throw new Exception('Not authenticated');
    }

    // --- FIX: Run the function to create the variable ---
    $conn = getDatabaseConnection(); 

    // 1. Initialize Adapters
    $xmlAdapter = new DatabaseAdapter('xml'); 
    
    // Pass the valid $conn to the adapter
    $mysqlAdapter = new DatabaseAdapter('mysql', $conn);

    $all_tasks = [];
    $source = 'xml';

    // 2. Try XML First
    $all_tasks = $xmlAdapter->getTasks($user_id);

    // 3. Fallback to MySQL if XML returns empty
    if (empty($all_tasks)) {
        $all_tasks = $mysqlAdapter->getTasks($user_id);
        $source = 'database';
    }

    // ... (rest of your logic remains the same)
    $pending_count = 0;
    $completed_count = 0;
    $daily_data = [];

    foreach ($all_tasks as $task) {
        $status = $task['status'] ?? 'pending';
        if ($status === 'pending') $pending_count++;
        elseif ($status === 'completed') $completed_count++;

        if (!empty($task['created_at'])) {
            $day = (new DateTime($task['created_at']))->format('Y-m-d');
            $daily_data[$day] = ($daily_data[$day] ?? 0) + 1;
        }
    }

    $total_active = $pending_count + $completed_count;
    $completion_rate = $total_active > 0 ? round(($completed_count / $total_active) * 100, 2) : 0.0;

    echo json_encode([
        'success'         => true,
        'pending'         => $pending_count,
        'completed'       => $completed_count,
        'completion_rate' => $completion_rate,
        'source'          => $source,
        'tasks'           => $all_tasks
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>