<?php
/**
 * Restore Task Handler - Updated for StorageAdapter
 */
declare(strict_types=1);
require_once 'auth_check.php';
require_once 'StorageAdapter.php'; // Updated: Using Adapter now

header('Content-Type: application/json');

try {
    $user_id = checkAuth();
    if (!$user_id) {
        http_response_code(401);
        throw new Exception('Not authenticated');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Invalid method');
    }

    $archive_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($archive_id <= 0) throw new Exception('Invalid archive ID');

    // Instantiate the Adapter (not the Core)
    $storage = new StorageAdapter();

    // The Adapter will verify that $user_id actually owns the task
    // before it calls the restore function.
    $success = $storage->restoreTask($archive_id, $user_id, $user_id);

    if (!$success) {
        throw new Exception('Failed to restore task.');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Task restored successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>