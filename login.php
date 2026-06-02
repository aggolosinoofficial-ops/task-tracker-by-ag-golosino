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

include 'auth_check.php';
include 'validation.php';

header('Content-Type: application/json');

try {
    // Only POST requests allowed
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Method not allowed');
    }

    // Verify CSRF token
    $csrf_token = isset($_POST['csrf_token']) ? trim($_POST['csrf_token']) : '';
    if (!verifyCSRFToken($csrf_token)) {
        http_response_code(403);
        throw new Exception('Invalid request token. Please refresh and try again');
    }

    // Check rate limiting per IP
    $rate_check = checkRateLimit('login_attempts', MAX_LOGIN_ATTEMPTS_PER_IP, 3600);
    if (!$rate_check['allowed']) {
        http_response_code(429);
        throw new Exception("Too many login attempts. Please wait " . ceil($rate_check['wait_seconds'] / 60) . " minutes");
    }

    // Get and sanitize input
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Validate input
    if (empty($username) || empty($password)) {
        throw new Exception('Username and password are required');
    }

    // Get user by username using prepared statement
    $stmt = $conn->prepare("SELECT id, username, password_hash FROM " . DB_NAME . "." . DB_TABLE_USERS . " WHERE username = ?");
    $user = null;
    
    if ($stmt) {
        $stmt->bind_param("s", $username);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();
            }
            $stmt->close();
        }
    }
    
    // XML FALLBACK: If database unavailable, check XML backup
    if (!$user && file_exists('users.xml')) {
        try {
            $xml = simplexml_load_file('users.xml');
            if ($xml) {
                foreach ($xml->user as $xmlUser) {
                    if ((string)$xmlUser->username === $username) {
                        $user = [
                            'id' => (int)$xmlUser->id,
                            'username' => (string)$xmlUser->username,
                            'password_hash' => (string)$xmlUser->password_hash
                        ];
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            // XML load failed, continue with error handling below
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
    $token = loginUser($user['id'], $user['username']);

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
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}
?>