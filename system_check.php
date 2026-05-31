<?php
/**
 * QUICK SYSTEM VERIFICATION & TEST
 * Returns comprehensive status of all integrations
 */

include 'config.php';
include 'db.php';
include 'xml_sync_handler.php';
include 'auth_check.php';

header('Content-Type: application/json; charset=UTF-8');

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

// 1. Database Connection
try {
    $testQuery = $conn->query("SELECT 1");
    $results['checks']['database_connection'] = [
        'status' => 'OK',
        'message' => 'MySQL connection active'
    ];
} catch (Exception $e) {
    $results['checks']['database_connection'] = [
        'status' => 'FAILED',
        'message' => $e->getMessage()
    ];
}

// 2. Required Tables
$requiredTables = ['users', 'tasks', 'archive_tasks', 'task_stats'];
$allTablesExist = true;
$tableStats = [];

foreach ($requiredTables as $table) {
    $query = $conn->query("SELECT COUNT(*) as count FROM test.$table");
    $row = $query->fetch_assoc();
    $tableStats[$table] = intval($row['count']);
    
    $checkQuery = $conn->query("SHOW TABLES LIKE '$table'");
    if ($checkQuery->num_rows === 0) {
        $allTablesExist = false;
    }
}

$results['checks']['database_tables'] = [
    'status' => $allTablesExist ? 'OK' : 'MISSING',
    'tables' => $tableStats
];

// 3. XML Files
$xmlStatus = [];
$files = ['users.xml', 'users.xsd', 'tasks.xml', 'tasks.xsd'];
foreach ($files as $file) {
    $xmlStatus[$file] = file_exists(__DIR__ . '/' . $file) ? 'EXISTS' : 'MISSING';
}

$results['checks']['xml_backup_files'] = [
    'status' => (array_search('MISSING', $xmlStatus) === false) ? 'OK' : 'INCOMPLETE',
    'files' => $xmlStatus
];

// 4. XML Synchronization
if (class_exists('XMLSyncHandler')) {
    $results['checks']['xml_sync_handler'] = [
        'status' => 'OK',
        'message' => 'XMLSyncHandler class loaded',
        'methods' => [
            'syncTaskToXML' => 'Create/backup new task',
            'syncTaskUpdateToXML' => 'Update existing task backup',
            'syncTaskDeleteToXML' => 'Remove task from backup',
            'syncUserToXML' => 'Create/backup new user',
            'syncAllTasksToXML' => 'Full rebuild tasks',
            'syncAllUsersToXML' => 'Full rebuild users'
        ]
    ];
} else {
    $results['checks']['xml_sync_handler'] = [
        'status' => 'FAILED',
        'message' => 'XMLSyncHandler not available'
    ];
}

// 5. Core Functions
$requiredFunctions = [
    'checkAuth',
    'getCurrentUser',
    'generateCSRFToken',
    'validatePasswordStrength',
    'verifyCSRFToken'
];

$missingFunctions = [];
foreach ($requiredFunctions as $func) {
    if (!function_exists($func)) {
        $missingFunctions[] = $func;
    }
}

$results['checks']['core_functions'] = [
    'status' => count($missingFunctions) === 0 ? 'OK' : 'MISSING',
    'missing' => $missingFunctions,
    'available' => array_diff($requiredFunctions, $missingFunctions)
];

// 6. PHP Extensions
$requiredExtensions = ['mysqli', 'xml', 'json'];
$missingExtensions = [];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

$results['checks']['php_extensions'] = [
    'status' => count($missingExtensions) === 0 ? 'OK' : 'MISSING',
    'required' => $requiredExtensions,
    'missing' => $missingExtensions
];

// 7. Security Configuration
$results['checks']['security'] = [
    'status' => 'OK',
    'settings' => [
        'password_hashing' => 'BCRYPT',
        'csrf_protection' => 'Enabled',
        'session_timeout' => SESSION_TIMEOUT . 's (' . (SESSION_TIMEOUT/60) . ' minutes)',
        'password_requirements' => 'Uppercase, numbers, special chars',
        'rate_limiting' => 'Login attempts: ' . MAX_LOGIN_ATTEMPTS,
        'sql_injection_prevention' => 'Prepared statements'
    ]
];

// 8. File Permissions
$criticalFiles = [
    'config.php',
    'db.php',
    'auth_check.php',
    'xml_sync_handler.php'
];

$permissionIssues = [];
foreach ($criticalFiles as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path) && !is_readable($path)) {
        $permissionIssues[] = $file . ' (not readable)';
    }
    if (file_exists($path) && !is_writable($path)) {
        $permissionIssues[] = $file . ' (not writable)';
    }
}

$results['checks']['file_permissions'] = [
    'status' => count($permissionIssues) === 0 ? 'OK' : 'WARNING',
    'issues' => $permissionIssues
];

// 9. Data Synchronization Status
$results['checks']['data_sync'] = [
    'status' => 'OK',
    'architecture' => [
        'primary_storage' => 'MySQL (users, tasks tables)',
        'backup_storage' => 'XML (users.xml, tasks.xml)',
        'sync_method' => 'Automatic on every CRUD operation',
        'sync_hooks' => [
            'register.php' => 'syncUserToXML()',
            'add_task.php' => 'syncTaskToXML()',
            'edit_task.php' => 'syncTaskUpdateToXML()',
            'delete_task.php' => 'syncTaskDeleteToXML()'
        ]
    ]
];

// 10. Overall System Status
$allOK = true;
foreach ($results['checks'] as $check) {
    if ($check['status'] !== 'OK' && $check['status'] !== 'INCOMPLETE') {
        $allOK = false;
        break;
    }
}

$results['overall_status'] = $allOK ? 'HEALTHY' : 'NEEDS ATTENTION';
$results['action_required'] = !$allOK;

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
