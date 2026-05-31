<?php
/**
 * Authentication Check Helper
 * Include this file at the top of any page that requires authentication
 * Enhanced with session timeout, CSRF protection, and rate limiting
 **/

include 'config.php';
include 'db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_COOKIE_DURATION,
        'path' => '/',
        'secure' => SESSION_SECURE,
        'httponly' => SESSION_HTTPONLY,
        'samesite' => 'Strict'
    ]);
    session_start();
}

/**
 * Check if user is authenticated with session timeout validation
 * Returns user_id if authenticated, false otherwise
 * OPTIMIZED: Reduced database queries for 2GB RAM systems
 */
function checkAuth()
{
    // Quick validation without database queries
    if (!isset($_SESSION['token']) || !isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
        return false;
    }

    $user_id = intval($_SESSION['user_id']);

    // Verify basic validity
    if (empty($_SESSION['token']) || $user_id <= 0) {
        session_destroy();
        return false;
    }

    // Check session timeout
    $elapsed = time() - $_SESSION['login_time'];
    if ($elapsed > SESSION_TIMEOUT) {
        session_destroy();
        return false;
    }

    // Update activity time only every 60 seconds to reduce writes
    if (!isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > 60) {
        $_SESSION['last_activity'] = time();
    }

    return $user_id;
}

/**
 * Require authentication - redirect to login if not authenticated
 * Use this function at the start of protected pages
 */
function requireAuth()
{
    if (!checkAuth()) {
        header('Location: login.html');
        exit();
    }
}

/**
 * Get current logged-in user information
 * Returns array with user_id and username, or false if not logged in
 * OPTIMIZED: Uses session cache when available
 */
function getCurrentUser()
{
    global $conn;

    if (!checkAuth()) {
        return false;
    }

    $user_id = intval($_SESSION['user_id']);

    // Return cached username and role if available (reduces DB queries)
    if (isset($_SESSION['username']) && isset($_SESSION['role'])) {
        return [
            'user_id' => $user_id,
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role']
        ];
    }

    // Return cached username if available, but still check DB for role if missing
    if (isset($_SESSION['username'])) {
        $stmt = $conn->prepare("SELECT id, username, role FROM " . DB_NAME . "." . DB_TABLE_USERS . " WHERE id = ?");
        if (!$stmt) {
            return [
                'user_id' => $user_id,
                'username' => $_SESSION['username']
            ];
        }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $stmt->close();
            if (isset($user['role'])) {
                $_SESSION['role'] = $user['role'];
            }
            return $user;
        }
        $stmt->close();
        return [
            'user_id' => $user_id,
            'username' => $_SESSION['username']
        ];
    }

    // Only query database if not cached
    $stmt = $conn->prepare("SELECT id, username, role FROM " . DB_NAME . "." . DB_TABLE_USERS . " WHERE id = ?");
    if (!$stmt) {
        // Fallback if role column does not exist in current schema
        $stmt = $conn->prepare("SELECT id, username FROM " . DB_NAME . "." . DB_TABLE_USERS . " WHERE id = ?");
        if (!$stmt) {
            return false;
        }
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user;
    }

    $stmt->close();
    return false;
}

/**
 * Check whether the current user has admin privileges
 */
function isAdmin()
{
    $user = getCurrentUser();
    return $user && isset($user['role']) && strtolower($user['role']) === 'admin';
}

/**
 * Login user by creating session with secure token
 * Called after successful credential verification
 */
function loginUser($user_id, $username)
{
    // Generate secure random token
    $token = bin2hex(random_bytes(TOKEN_LENGTH));

    // Store in session
    $_SESSION['user_id'] = intval($user_id);
    $_SESSION['username'] = $username;
    $_SESSION['token'] = $token;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();

    return $token;
}

/**
 * Logout user by destroying session
 */
function logoutUser()
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
 * Update task statistics for the current user (OPTIMIZED)
 * Combines multiple COUNT queries into a single query
 * Called after task operations (add, delete, toggle, archive, restore)
 */
function updateTaskStats($user_id = null)
{
    global $conn;

    // Use current user if not provided
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        $user_id = intval($_SESSION['user_id']);
    } else {
        $user_id = intval($user_id);
    }

    try {
        // OPTIMIZED: Single query with conditional aggregation instead of 3 queries
        $stmt = $conn->prepare(
            "SELECT 
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                COUNT(*) as total
            FROM " . DB_TABLE_TASKS . " WHERE user_id = ?"
        );

        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $counts = $result->fetch_assoc();
        $stmt->close();

        $pending = intval($counts['pending'] ?? 0);
        $completed = intval($counts['completed'] ?? 0);
        $total_active = intval($counts['total'] ?? 0);

        // Get archived count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM " . ARCHIVE_TABLE . " WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $archived = intval($result->fetch_assoc()['count'] ?? 0);
        $stmt->close();

        // Update or insert task_stats
        $stmt = $conn->prepare(
            "INSERT INTO " . STATS_TABLE . " (user_id, total_tasks, completed_tasks, pending_tasks, archived_tasks, last_updated)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            total_tasks = VALUES(total_tasks),
            completed_tasks = VALUES(completed_tasks),
            pending_tasks = VALUES(pending_tasks),
            archived_tasks = VALUES(archived_tasks),
            last_updated = NOW()"
        );
        $stmt->bind_param("iiiii", $user_id, $total_active, $completed, $pending, $archived);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    } catch (Exception $e) {
        error_log("Failed to update task stats: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate CSRF token
 */
function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token)
{
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }

    // Check token expiry
    $elapsed = time() - $_SESSION['csrf_token_time'];
    if ($elapsed > CSRF_TOKEN_EXPIRY) {
        unset($_SESSION['csrf_token']);
        return false;
    }

    // Verify token matches
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get client IP address (with proxy support)
 */
function getClientIP()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ?: 'unknown';
}

/**
 * Check rate limiting for IP
 * Returns: ['allowed' => bool, 'wait_seconds' => int|null]
 * OPTIMIZATION: Bypass rate limiting in DEV_MODE for testing
 */
function checkRateLimit($action, $max_attempts, $window_seconds)
{
    // DEVELOPMENT BYPASS: If DEV_MODE is enabled, skip all rate limiting checks
    // This allows unlimited login/registration attempts during testing
    if (defined('DEV_MODE') && DEV_MODE === true) {
        // Allow all attempts when in development mode
        return ['allowed' => true, 'wait_seconds' => null];
    }

    $ip = getClientIP();
    $key = "ratelimit_{$action}_{$ip}";

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'attempts' => 0,
            'first_attempt' => time(),
            'locked_until' => null
        ];
    }

    $data = &$_SESSION[$key];
    $now = time();

    // Check if currently locked out
    if ($data['locked_until'] && $now < $data['locked_until']) {
        $wait = $data['locked_until'] - $now;
        return ['allowed' => false, 'wait_seconds' => $wait];
    }

    // Reset if window expired
    if ($now - $data['first_attempt'] > $window_seconds) {
        $data['attempts'] = 0;
        $data['first_attempt'] = $now;
        $data['locked_until'] = null;
    }

    // Increment attempts
    $data['attempts']++;

    // Check if exceeded limit
    if ($data['attempts'] > $max_attempts) {
        $data['locked_until'] = $now + LOCKOUT_DURATION;
        return ['allowed' => false, 'wait_seconds' => LOCKOUT_DURATION];
    }

    return ['allowed' => true, 'wait_seconds' => null];
}

/**
 * Validate password strength
 * Returns: ['valid' => bool, 'errors' => array]
 */
function validatePasswordStrength($password)
{
    $errors = [];

    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        $errors[] = "Password must be at least " . MIN_PASSWORD_LENGTH . " characters";
    }

    if (strlen($password) > MAX_PASSWORD_LENGTH) {
        $errors[] = "Password must not exceed " . MAX_PASSWORD_LENGTH . " characters";
    }

    if (PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }

    if (PASSWORD_REQUIRE_NUMBERS && !preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }

    if (PASSWORD_REQUIRE_SPECIAL && !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
        $errors[] = "Password must contain at least one special character (!@#$%^&* etc)";
    }

    return [
        'valid' => count($errors) === 0,
        'errors' => $errors
    ];
}

?>