<?php
/**
 * rebuild_xml_from_db.php
 *
 * One-time (or repeated) utility endpoint to rebuild XML files
 * from MySQL (DB_NAME = test).
 *
 * Use it after backend fixes or when XML becomes inconsistent.
 *
 * SECURITY NOTE:
 * This script requires authentication (same as the app) and
 * only allows users with role = admin (if role exists).
 */

include 'auth_check.php';
include 'db.php';
include 'xml_sync_handler.php';


header('Content-Type: application/json');

try {
    $user_id = checkAuth();
    if (!$user_id) {
        http_response_code(401);
        throw new Exception('Please log in');
    }

    // Optional: only allow admins if users table has role
    // (if not present, we still allow rebuild to avoid breaking dev)
    $isAdmin = true;
    try {
        if (isset($conn) && $conn) {
            $stmt = $conn->prepare("SELECT role FROM " . DB_NAME . "." . DB_TABLE_USERS . " WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $role = $row['role'] ?? 'user';
                $isAdmin = ($role === 'admin');
                $stmt->close();
            }
        }
    } catch (Exception $e) {
        // if role check fails, do not block rebuild in dev
        $isAdmin = true;
    }

    if (!$isAdmin) {
        http_response_code(403);
        throw new Exception('Permission denied');
    }

    if (!defined('DB_AVAILABLE') || !DB_AVAILABLE || !isset($conn) || !$conn || $conn->connect_error) {
        http_response_code(503);
        throw new Exception('MySQL is not available');
    }

    $sync = new XMLSyncHandler();

    // Rebuild tasks
    $tasksOk = $sync->syncAllTasksToXML($conn);

    // Rebuild users (optional, but keeps users.xml consistent)
    $usersOk = $sync->syncAllUsersToXML($conn);

    // Rebuild archive
    $archiveOk = $sync->syncAllArchiveTasksToXML($conn);

    // Rebuild deleted logs
    $deletedOk = $sync->syncAllDeletedTasksToXML($conn);

    $result = [
        'success' => true,
        'storage' => [
            'tasks_xml' => $tasksOk ? 'rebuilt ✓' : 'failed',
            'users_xml' => $usersOk ? 'rebuilt ✓' : 'failed',
            'archive_tasks_xml' => $archiveOk ? 'rebuilt ✓' : 'failed',
            'deleted_tasks_xml' => $deletedOk ? 'rebuilt ✓' : 'failed',
        ]
    ];

    echo json_encode($result);
    exit();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit();
}

