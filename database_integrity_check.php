<?php
/**
 * Database & XML Integrity Checker
 * Full Implementation - Includes Sync/Repair functionality
 */

require_once 'config.php';
require_once 'db.php';
require_once 'auth_check.php';
require_once 'xml_sync_handler.php';

// 1. SECURITY: Enforce Admin access
$isAdmin = (php_sapi_name() === 'cli');
if (!$isAdmin) {
    // Only allow logged-in users who possess the 'admin' role
    if (!isAdmin()) { 
        http_response_code(403);
        die(json_encode(['error' => 'Unauthorized', 'message' => 'Admin access required']));
    }
}

header('Content-Type: application/json');
$sync = getXMLSyncHandler();

// 2. ACTION HANDLER (Sync/Repair)
// Triggered via POST request, e.g., integrity_check.php?action=rebuild_all
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
            echo json_encode(['error' => 'Unknown or invalid action']);
            break;
    }
    exit; // Stop execution after handling POST
}

// 3. GENERATE INTEGRITY REPORT (GET request)
try {
    $report = [
        'users'         => getIntegrityReport(DB_TABLE_USERS, 'users.xml', 'user'),
        'tasks'         => getIntegrityReport(DB_TABLE_TASKS, 'tasks.xml', 'task'),
        'archive_tasks' => getIntegrityReport(ARCHIVE_TABLE, 'archive_tasks.xml', 'archive_task'),
        'deleted_tasks' => getIntegrityReport(DELETED_TABLE, 'deleted_tasks.xml', 'deleted_task'),
    ];

    // Calculate Global Status
    $report['status'] = 'OK';
    foreach ($report as $key => $data) {
        if (isset($data['mismatches']) && count($data['mismatches']) > 0) {
            $report['status'] = 'MISMATCH DETECTED';
            break;
        }
    }

    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'System error: ' . $e->getMessage()]);
}

/**
 * Generic Integrity Checker
 * Helper function to reduce code duplication and save RAM
 */
function getIntegrityReport(string $tableName, string $xmlFilename, string $tagName): array
{
    global $conn;
    
    $report = ['mysql_count' => 0, 'xml_count' => 0, 'mismatches' => []];
    $xmlPath = __DIR__ . '/' . $xmlFilename;
    $mysqlData = [];
    $xmlData = [];

    // 1. Get MySQL Data
    $query = "SELECT * FROM " . DB_NAME . "." . $tableName . " ORDER BY id";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $mysqlData[$row['id']] = $row;
            $report['mysql_count']++;
        }
    }

    // 2. Get XML Data
    if (file_exists($xmlPath)) {
        $dom = new DOMDocument();
        // Suppress warnings for malformed XML
        if (@$dom->load($xmlPath)) {
            $items = $dom->getElementsByTagName($tagName);
            $report['xml_count'] = $items->length;
            foreach ($items as $item) {
                $itemData = [];
                foreach ($item->childNodes as $child) {
                    if ($child->nodeType === XML_ELEMENT_NODE) {
                        $itemData[$child->nodeName] = $child->nodeValue;
                    }
                }
                if (isset($itemData['id'])) {
                    $xmlData[$itemData['id']] = $itemData;
                }
            }
        }
    }

    // 3. Compare Results
    if ($report['mysql_count'] !== $report['xml_count']) {
        $report['mismatches'][] = "Count mismatch: MySQL={$report['mysql_count']}, XML={$report['xml_count']}";
    }

    foreach ($mysqlData as $id => $data) {
        if (!isset($xmlData[$id])) {
            $report['mismatches'][] = "ID $id missing in XML";
        } else {
            // Simple validation: check if critical titles/names match
            if (isset($data['title']) && isset($xmlData[$id]['title']) && $xmlData[$id]['title'] !== $data['title']) {
                $report['mismatches'][] = "ID $id title mismatch";
            }
        }
    }

    return $report;
}
?>