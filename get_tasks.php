<?php
declare(strict_types=1);

/**
 * get_tasks.php
 * Fetches tasks via the DatabaseAdapter (MySQL or XML) with pagination.
 */

// Ensure no content is output before JSON headers
if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=UTF-8');

// =============================================================================
// INCLUDES
// =============================================================================

require_once __DIR__ . '/AuthService.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/db_adapter.php';

// =============================================================================
// FALLBACKS & HELPERS
// =============================================================================

if (!function_exists('getDatabaseConnection')) {
    function getDatabaseConnection() { return null; }
}

function getDbConnection() {
    try {
        return (function_exists('getDatabaseConnection')) ? getDatabaseConnection() : null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Creates the appropriate adapter based on connection status.
 */
/**
 * Create database adapter instance.
 *
 * @param mysqli|PDO|null $conn
 * @return DatabaseAdapter
 */
function createDatabaseAdapter(mysqli|PDO|null $conn): DatabaseAdapter {
    // 1. If we have a valid connection object, use MySQL
    if ($conn instanceof mysqli || $conn instanceof PDO) {
        return new DatabaseAdapter($conn);
    }

    // Default fallback to XML
    $xmlPath = __DIR__ . '/data/tasks.xml';
    return new DatabaseAdapter(null, $xmlPath);
}

/**
 * Validates authentication and returns user_id.
 */
function validateAuthentication(): int {
    $auth = new AuthService();
    $user_id = $auth->checkAuth();
    
    if (!$user_id) {
        throw new RuntimeException('Unauthorized', 401);
    }
    return (int)$user_id;
}

/**
 * Parses and validates pagination parameters.
 */
function getPaginationParams(): array {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    
    // Safety bounds
    $page = max(1, $page);
    $limit = max(1, min(100, $limit)); // Max 100 items per request
    
    return [
        'page'   => $page,
        'limit'  => $limit,
        'offset' => ($page - 1) * $limit
    ];
}

/**
 * JSON Response Helper
 */
function sendResponse(bool $success, array $data, int $httpCode = 200): void {
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        $success ? 'data' : 'error' => $data
    ]);
    exit;
}

// =============================================================================
// MAIN EXECUTION
// =============================================================================

try {
    // 1. Auth
    $user_id = validateAuthentication();

    // 2. Setup
    $conn = getDbConnection();
    $db = createDatabaseAdapter($conn);
    $params = getPaginationParams();

    // 3. Fetch Data
    // Note: If using SQL, ensure your DatabaseAdapter uses LIMIT/OFFSET 
    // for high performance.
    $tasks = $db->getTasks($user_id);
    
    // 4. Manual Pagination (if DB isn't doing it)
    $total = count($tasks);
    $offset = $params['offset'];
    $limit = $params['limit'];
    
    $pagedTasks = array_slice($tasks, $offset, $limit);

    // 5. Success
    sendResponse(true, [
        'tasks' => $pagedTasks,
        'meta'  => [
            'total' => $total,
            'page'  => $params['page'],
            'pages' => (int)ceil($total / $limit)
        ]
    ]);

} catch (RuntimeException $e) {
    // Expected errors (like 401)
    sendResponse(false, ['message' => $e->getMessage()], $e->getCode() ?: 500);
} catch (Exception $e) {
    // Unexpected errors
    error_log('[get_tasks.php Error]: ' . $e->getMessage());
    sendResponse(false, ['message' => 'An internal server error occurred.'], 500);
} finally {
    // Cleanup
    if (isset($conn) && $conn) {
        if ($conn instanceof mysqli) {
            $conn->close();
        } elseif ($conn instanceof PDO) {
            $conn = null; // PDO cleanup
        }
    }
}

?>