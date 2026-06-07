<?php
/**
 * Authentication Check Helper
 * Full Implementation with Strict Type Hinting
 */

require_once 'config.php';
require_once 'db.php';

// Session is intentionally NOT started at include-time.
// Starting sessions and/or sending headers before an API endpoint sets its
// response headers can cause non-JSON output. 

function ensureSessionStarted(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    // Configure session parameters BEFORE starting session
    session_name(defined('SESSION_NAME') ? SESSION_NAME : 'TASK_TRACKER_SESS');
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', defined('SESSION_HTTPONLY') ? (int)SESSION_HTTPONLY : 1);
    ini_set('session.cookie_secure', defined('SESSION_SECURE') ? (int)SESSION_SECURE : 0);
    ini_set('session.cookie_samesite', 'Strict');

    // Avoid any output/headers issues: session_start() should not echo anything.
    session_start();
}


/**
 * Check if user is authenticated with session timeout validation
 * @return int|false Returns user_id if valid, false otherwise.
 */
function checkAuth(): int|false
{
    ensureSessionStarted();

    if (!isset($_SESSION['token'], $_SESSION['user_id'], $_SESSION['login_time'])) {
        return false;
    }


    $user_id = (int)$_SESSION['user_id'];

    if (empty($_SESSION['token']) || $user_id <= 0) {
        session_destroy();
        return false;
    }

    $timeout = defined('SESSION_TIMEOUT') ? (int)SESSION_TIMEOUT : 3600;
    if ((time() - (int)$_SESSION['login_time']) > $timeout) {
        session_destroy();
        return false;
    }

    if (!isset($_SESSION['last_activity']) || (time() - (int)$_SESSION['last_activity']) > 60) {
        $_SESSION['last_activity'] = time();
    }

    return $user_id;
}

/**
 * Require authentication - redirect to login if not authenticated
 */
function requireAuth(): void
{
    if (checkAuth() === false) {
        header('Location: login.html');
        exit();
    }
}

/**
 * Get current logged-in user information
 * @return array{user_id: int, username: string, role: string}|false
 */
function getCurrentUser(): array|false
{
    global $conn;

    $user_id = checkAuth();
    if ($user_id === false) return false;

    if (isset($_SESSION['username'])) {
        return [
            'user_id' => $user_id,
            'username' => (string)$_SESSION['username'],
            'role' => (string)($_SESSION['role'] ?? 'user')
        ];
    }

    $xml_path = __DIR__ . '/users.xml';
    if (file_exists($xml_path)) {
        $xml = @simplexml_load_file($xml_path);
        if ($xml) {
            foreach ($xml->user as $user) {
                if ((int)$user->id === $user_id) {
                    $_SESSION['username'] = (string)$user->username;
                    $_SESSION['role'] = (string)($user->role ?? 'user');
                    return ['user_id' => $user_id, 'username' => $_SESSION['username'], 'role' => $_SESSION['role']];
                }
            }
        }
    }

    if (isset($conn) && defined('DB_AVAILABLE') && DB_AVAILABLE) {
        $stmt = $conn->prepare("SELECT id, username, role FROM " . DB_NAME . "." . DB_TABLE_USERS . " WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($res) {
            $_SESSION['username'] = $res['username'];
            $_SESSION['role'] = $res['role'] ?? 'user';
            return $res;
        }
    }

    return ['user_id' => $user_id, 'username' => $_SESSION['username'] ?? 'User', 'role' => 'user'];
}

/**
 * Check whether the current user has admin privileges
 */
function isAdmin(): bool
{
    $user = getCurrentUser();
    return $user !== false && isset($user['role']) && strtolower($user['role']) === 'admin';
}

/**
 * Login user - includes session fixation protection
 */
function loginUser(int $user_id, string $username): string
{
    session_regenerate_id(true);
    $token = bin2hex(random_bytes(defined('TOKEN_LENGTH') ? (int)TOKEN_LENGTH : 32));

    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['token'] = $token;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();

    return $token;
}

/**
 * Logout user by destroying session
 */
function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), 
            '', 
            time() - 42000, 
            $params["path"], 
            $params["domain"], 
            $params["secure"], 
            $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Update task stats (Optimized)
 */
function updateTaskStats(?int $user_id = null): bool
{
    global $conn;
    $user_id = $user_id ?? (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
    if ($user_id === null) return false;

    try {
        $stmt = $conn->prepare("SELECT 
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                COUNT(*) as total
            FROM " . DB_TABLE_TASKS . " WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $counts = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM " . ARCHIVE_TABLE . " WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $archived = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO " . STATS_TABLE . " (user_id, total_tasks, completed_tasks, pending_tasks, archived_tasks, last_updated)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                total_tasks=VALUES(total_tasks), 
                completed_tasks=VALUES(completed_tasks), 
                pending_tasks=VALUES(pending_tasks), 
                archived_tasks=VALUES(archived_tasks), 
                last_updated=NOW()");
        
        $stmt->bind_param("iiiii", $user_id, $counts['total'], $counts['completed'], $counts['pending'], $archived);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        error_log("Stats update failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate CSRF token
 */
function generateCSRFToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return (string)$_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get client IP address
 */
function getClientIP(): string 
{
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Rate Limiting
 * @return array{allowed: bool, wait_seconds: int|null}
 */
function checkRateLimit(string $action, int $max_attempts, int $window_seconds): array
{
    if (defined('DEV_MODE') && DEV_MODE === true) return ['allowed' => true, 'wait_seconds' => null];
    
    $key = "ratelimit_{$action}_" . getClientIP();
    if (!isset($_SESSION[$key])) $_SESSION[$key] = ['attempts' => 0, 'first' => time()];
    
    if ((time() - (int)$_SESSION[$key]['first']) > $window_seconds) {
        $_SESSION[$key] = ['attempts' => 1, 'first' => time()];
        return ['allowed' => true, 'wait_seconds' => null];
    }
    
    $_SESSION[$key]['attempts']++;
    return $_SESSION[$key]['attempts'] <= $max_attempts 
        ? ['allowed' => true, 'wait_seconds' => null] 
        : ['allowed' => false, 'wait_seconds' => 60];
}

/**
 * Validate password strength
 * @return array{valid: bool, errors: string[]}
 */
function validatePasswordStrength(string $password): array
{
    $len = strlen($password);
    return ($len >= 8 && $len <= 255) 
        ? ['valid' => true, 'errors' => []] 
        : ['valid' => false, 'errors' => ['Password must be between 8 and 255 characters.']];
}
?>