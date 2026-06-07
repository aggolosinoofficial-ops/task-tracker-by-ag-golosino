<?php
/**
 * User Login Handler - Enhanced Version
 * Processes login form submission with security features
 * - Rate limiting to prevent brute force
 * - Account lockout on failed attempts
 * - CSRF token validation
 * - Session timeout handling
 * - Secure session creation
 */

// Ensure API endpoints never break JSON parsing in the browser.
// 1) Avoid accidental output before JSON
// 2) Convert any PHP warnings/notices into controlled JSON error (logged)
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Buffer any stray output (warnings/whitespace)
if (ob_get_level() === 0) {
    ob_start();
}

// 1. Load the database blueprint
require_once 'db.php';

// 2. Actually run the connection and assign it to $conn
// NOTE: db may be unavailable (XAMPP not configured, missing tables, etc.).
// We support XML-first login, so we must NOT fatal if $conn is null.
$conn = getDatabaseConnection();

require_once 'AuthService.php';
include 'validation.php';


header('Content-Type: application/json; charset=UTF-8');

try {
    // Only POST requests allowed
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Method not allowed');
    }

// Verify CSRF token
    $csrf_token = isset($_POST['csrf_token']) ? trim($_POST['csrf_token']) : '';

    $auth = new AuthService();

    if (!$auth->verifyCSRFToken($csrf_token)) {
        http_response_code(403);
        throw new Exception('Invalid request token. Please refresh and try again');
    }

    // Check rate limiting per IP
    // NOTE: use SESSION-based rate limiting. If DEV_MODE is enabled, checkRateLimit() will bypass.
    $rate_check = $auth->checkRateLimit('login_attempts', MAX_LOGIN_ATTEMPTS_PER_IP, 3600);


    if (!$rate_check['allowed']) {
        http_response_code(429);
        throw new Exception("Too many login attempts. Please wait " . ceil(($rate_check['wait_seconds'] ?? 0) / 60) . " minutes");
    }


    // Get and sanitize input
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Validate input
    if (empty($username) || empty($password)) {
        throw new Exception('Username and password are required');
    }

    // Get user by username - CHECK XML FIRST (PRIMARY), then DATABASE (SECONDARY)
    $user = null;
    $source = '';
    
// STEP 1: Check DATABASE first (primary storage)
    if ($conn === null) {
        // No DB connection; fall back to XML-only login.
    } else {
        try {
            // Avoid get_result() (requires mysqlnd). Use bind_result/fetch instead.
            $stmt = $conn->prepare("SELECT id, username, password_hash FROM " . DB_NAME . "." . DB_TABLE_USERS . " WHERE username = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $username);
                if ($stmt->execute()) {
                    $stmt->bind_result($uid, $uname, $phash);
                    if ($stmt->fetch()) {
                        $user = [
                            'id' => (int)$uid,
                            'username' => (string)$uname,
                            'password_hash' => (string)$phash
                        ];
                        $source = 'database';
                    }
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log("Database login check error: " . $e->getMessage());
        }
    }

    // STEP 2: If not found in DATABASE, check XML fallback
    if (!$user) {
        try {
            $xml_path = __DIR__ . '/users.xml';
            if (file_exists($xml_path)) {
                $xml = simplexml_load_file($xml_path);
                if ($xml) {
                    foreach ($xml->user as $xmlUser) {
                        if ((string)$xmlUser->username === $username) {
                            $user = [
                                'id' => (int)$xmlUser->id,
                                'username' => (string)$xmlUser->username,
                                'password_hash' => (string)$xmlUser->password_hash
                            ];
                            $source = 'xml';
                            break;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("XML login check error: " . $e->getMessage());
        }
    }

    // Use generic error message for security (don't reveal if user exists)
    if (!$user) {
        throw new Exception('Invalid username or password');
    }


    // Verify password hash using constant-time comparison
    if (!password_verify($password, $user['password_hash'])) {
        // Log failed attempt (optional)
        // logActivity(null, 'failed_login', 'Failed login for: ' . $username);
        throw new Exception('Invalid username or password');
    }

    // Password verified - log in the user
    $token = $auth->loginUser($user['id'], $user['username']);


    // Regenerate session ID to prevent session fixation attacks
    session_regenerate_id(true);

    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user_id' => $user['id'],
        'username' => $user['username'],
        'token' => $token
    ]);

} catch (Exception $e) {
    http_response_code(401);
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Throwable $t) {
    // Convert unexpected PHP errors into JSON so login.js doesn't crash on response.json()
    http_response_code(500);
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Server error'
    ]);
} finally {
    // If there is buffered stray output, drop it to keep JSON clean.
    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (isset($stmt) && $stmt) {
        $stmt->close();
    }
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>

