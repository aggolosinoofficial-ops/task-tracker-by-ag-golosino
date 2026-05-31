<?php
/**
 * Database & XML Integrity Checker
 * Compares MySQL database with XML files and reports mismatches
 * Provides tools to rebuild and synchronize data
 */

include 'config.php';
include 'db.php';
include 'xml_sync_handler.php';
include 'auth_check.php';

// Only allow access for logged-in admins or from command line
$isAdmin = false;
if (php_sapi_name() === 'cli') {
    $isAdmin = true;
} else {
    $user_id = checkAuth();
    if (!$user_id) {
        // Redirect browser users to login if not authenticated
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            http_response_code(401);
            die(json_encode(['error' => 'Unauthorized', 'message' => 'Please log in to access this page']));
        }
        header('Location: login.html');
        exit();
    }

    $stmt = $conn->prepare("SELECT id FROM " . DB_NAME . "." . DB_TABLE_USERS . " WHERE id = ? AND role = 'admin'");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $isAdmin = true;
        }
        $stmt->close();
    }
}

if (!$isAdmin && php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized', 'message' => 'Admin access required']);
    exit();
}

header('Content-Type: application/json');

$sync = getXMLSyncHandler();
$report = [];

try {
    // ============ CHECK USERS ============
    $report['users'] = checkUserIntegrity();
    
    // ============ CHECK TASKS ============
    $report['tasks'] = checkTaskIntegrity();
    
    // ============ CHECK ARCHIVE TASKS ============
    $report['archive_tasks'] = checkArchiveTaskIntegrity();
    
    // ============ CHECK DELETED TASKS ============
    $report['deleted_tasks'] = checkDeletedTaskIntegrity();
    
    // ============ OVERALL STATUS ============
    $report['status'] = (
        count($report['users']['mismatches']) === 0 && 
        count($report['tasks']['mismatches']) === 0 &&
        count($report['archive_tasks']['mismatches']) === 0 &&
        count($report['deleted_tasks']['mismatches']) === 0
    ) ? 'OK' : 'MISMATCH DETECTED';
    
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// ============ HELPER FUNCTIONS ============

function checkUserIntegrity()
{
    global $conn;
    
    $report = [
        'mysql_count' => 0,
        'xml_count' => 0,
        'mismatches' => [],
        'mysql_users' => [],
        'xml_users' => []
    ];
    
    // Get MySQL users
    $result = $conn->query("SELECT id, username, role, created_at FROM test.users ORDER BY id");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $report['mysql_users'][] = $row;
            $report['mysql_count']++;
        }
    }
    
    // Get XML users
    if (file_exists(__DIR__ . '/users.xml')) {
        $dom = new DOMDocument();
        if (@$dom->load(__DIR__ . '/users.xml')) {
            $users = $dom->getElementsByTagName('user');
            $report['xml_count'] = $users->length;
            
            foreach ($users as $user) {
                $userData = [];
                foreach ($user->childNodes as $child) {
                    if ($child->nodeType === XML_ELEMENT_NODE) {
                        $userData[$child->nodeName] = $child->nodeValue;
                    }
                }
                $report['xml_users'][] = $userData;
            }
        }
    }
    
    // Compare
    if ($report['mysql_count'] !== $report['xml_count']) {
        $report['mismatches'][] = "User count mismatch: MySQL=" . $report['mysql_count'] . ", XML=" . $report['xml_count'];
    }
    
    foreach ($report['mysql_users'] as $mysqlUser) {
        $found = false;
        foreach ($report['xml_users'] as $xmlUser) {
            if ($xmlUser['id'] === $mysqlUser['id']) {
                $found = true;
                if ($xmlUser['username'] !== $mysqlUser['username']) {
                    $report['mismatches'][] = "User ID " . $mysqlUser['id'] . ": username mismatch";
                }
                break;
            }
        }
        if (!$found) {
            $report['mismatches'][] = "User ID " . $mysqlUser['id'] . " in MySQL but NOT in XML";
        }
    }
    
    return $report;
}

function checkArchiveTaskIntegrity()
{
    global $conn;
    
    $report = [
        'mysql_count' => 0,
        'xml_count' => 0,
        'mismatches' => [],
        'mysql_archive_tasks' => [],
        'xml_archive_tasks' => []
    ];
    
    $result = $conn->query("SELECT id, user_id, title, description, status, created_at, archived_at FROM " . DB_NAME . "." . ARCHIVE_TABLE . " ORDER BY id");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $report['mysql_archive_tasks'][] = $row;
            $report['mysql_count']++;
        }
    }
    
    if (file_exists(__DIR__ . '/archive_tasks.xml')) {
        $dom = new DOMDocument();
        if (@$dom->load(__DIR__ . '/archive_tasks.xml')) {
            $tasks = $dom->getElementsByTagName('archive_task');
            $report['xml_count'] = $tasks->length;
            foreach ($tasks as $task) {
                $taskData = [];
                foreach ($task->childNodes as $child) {
                    if ($child->nodeType === XML_ELEMENT_NODE) {
                        $taskData[$child->nodeName] = $child->nodeValue;
                    }
                }
                $report['xml_archive_tasks'][] = $taskData;
            }
        }
    }
    
    if ($report['mysql_count'] !== $report['xml_count']) {
        $report['mismatches'][] = "Archive task count mismatch: MySQL=" . $report['mysql_count'] . ", XML=" . $report['xml_count'];
    }
    
    foreach ($report['mysql_archive_tasks'] as $mysqlTask) {
        $found = false;
        foreach ($report['xml_archive_tasks'] as $xmlTask) {
            if (isset($xmlTask['id']) && $xmlTask['id'] === $mysqlTask['id']) {
                $found = true;
                if ($xmlTask['title'] !== $mysqlTask['title']) {
                    $report['mismatches'][] = "Archive task ID " . $mysqlTask['id'] . ": title mismatch";
                }
                break;
            }
        }
        if (!$found) {
            $report['mismatches'][] = "Archive task ID " . $mysqlTask['id'] . " in MySQL but NOT in XML";
        }
    }
    
    return $report;
}

function checkDeletedTaskIntegrity()
{
    global $conn;
    
    $report = [
        'mysql_count' => 0,
        'xml_count' => 0,
        'mismatches' => [],
        'mysql_deleted_tasks' => [],
        'xml_deleted_tasks' => []
    ];
    
    $result = $conn->query("SELECT id, user_id, title, description, status, created_at, deleted_at FROM " . DB_NAME . "." . DELETED_TABLE . " ORDER BY id");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $report['mysql_deleted_tasks'][] = $row;
            $report['mysql_count']++;
        }
    }
    
    if (file_exists(__DIR__ . '/deleted_tasks.xml')) {
        $dom = new DOMDocument();
        if (@$dom->load(__DIR__ . '/deleted_tasks.xml')) {
            $tasks = $dom->getElementsByTagName('deleted_task');
            $report['xml_count'] = $tasks->length;
            foreach ($tasks as $task) {
                $taskData = [];
                foreach ($task->childNodes as $child) {
                    if ($child->nodeType === XML_ELEMENT_NODE) {
                        $taskData[$child->nodeName] = $child->nodeValue;
                    }
                }
                $report['xml_deleted_tasks'][] = $taskData;
            }
        }
    }
    
    if ($report['mysql_count'] !== $report['xml_count']) {
        $report['mismatches'][] = "Deleted task count mismatch: MySQL=" . $report['mysql_count'] . ", XML=" . $report['xml_count'];
    }
    
    foreach ($report['mysql_deleted_tasks'] as $mysqlTask) {
        $found = false;
        foreach ($report['xml_deleted_tasks'] as $xmlTask) {
            if (isset($xmlTask['id']) && $xmlTask['id'] === $mysqlTask['id']) {
                $found = true;
                if ($xmlTask['title'] !== $mysqlTask['title']) {
                    $report['mismatches'][] = "Deleted task ID " . $mysqlTask['id'] . ": title mismatch";
                }
                break;
            }
        }
        if (!$found) {
            $report['mismatches'][] = "Deleted task ID " . $mysqlTask['id'] . " in MySQL but NOT in XML";
        }
    }
    
    return $report;
}

function checkTaskIntegrity()
{
    global $conn;
    
    $report = [
        'mysql_count' => 0,
        'xml_count' => 0,
        'mismatches' => [],
        'mysql_tasks' => [],
        'xml_tasks' => []
    ];
    
    // Get MySQL tasks
    $result = $conn->query("SELECT id, user_id, title, status, created_at FROM test.tasks ORDER BY id");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $report['mysql_tasks'][] = $row;
            $report['mysql_count']++;
        }
    }
    
    // Get XML tasks
    if (file_exists(__DIR__ . '/tasks.xml')) {
        $dom = new DOMDocument();
        if (@$dom->load(__DIR__ . '/tasks.xml')) {
            $tasks = $dom->getElementsByTagName('task');
            $report['xml_count'] = $tasks->length;
            
            foreach ($tasks as $task) {
                $taskData = [];
                foreach ($task->childNodes as $child) {
                    if ($child->nodeType === XML_ELEMENT_NODE) {
                        $taskData[$child->nodeName] = $child->nodeValue;
                    }
                }
                $report['xml_tasks'][] = $taskData;
            }
        }
    }
    
    // Compare
    if ($report['mysql_count'] !== $report['xml_count']) {
        $report['mismatches'][] = "Task count mismatch: MySQL=" . $report['mysql_count'] . ", XML=" . $report['xml_count'];
    }
    
    foreach ($report['mysql_tasks'] as $mysqlTask) {
        $found = false;
        foreach ($report['xml_tasks'] as $xmlTask) {
            if ($xmlTask['id'] === $mysqlTask['id']) {
                $found = true;
                if ($xmlTask['title'] !== $mysqlTask['title']) {
                    $report['mismatches'][] = "Task ID " . $mysqlTask['id'] . ": title mismatch";
                }
                break;
            }
        }
        if (!$found) {
            $report['mismatches'][] = "Task ID " . $mysqlTask['id'] . " in MySQL but NOT in XML";
        }
    }
    
    return $report;
}

// Sync operations (if requested)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'rebuild_all_tasks':
            $result = $sync->syncAllTasksToXML($conn);
            echo json_encode(['success' => $result, 'message' => 'Tasks synchronized to XML']);
            break;
            
        case 'rebuild_all_users':
            $result = $sync->syncAllUsersToXML($conn);
            echo json_encode(['success' => $result, 'message' => 'Users synchronized to XML']);
            break;
            
        case 'rebuild_all':
            $tasks_result = $sync->syncAllTasksToXML($conn);
            $users_result = $sync->syncAllUsersToXML($conn);
            $archive_result = $sync->syncAllArchiveTasksToXML($conn);
            $deleted_result = $sync->syncAllDeletedTasksToXML($conn);
            echo json_encode([
                'success' => ($tasks_result && $users_result && $archive_result && $deleted_result),
                'message' => 'All data synchronized to XML',
                'tasks' => $tasks_result,
                'users' => $users_result,
                'archive_tasks' => $archive_result,
                'deleted_tasks' => $deleted_result
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
    }
}
?>
