<?php
/**
 * Check Username Availability
 * Real-time validation for registration form
 * * SECURITY:
 * - Rate limited via session to prevent brute force enumeration
 * - Prepared statements prevent SQL injection
 * - Generic error handling prevents info leakage
 */

// Include necessary environment and security modules
require_once 'config.php';
require_once 'db.php';
require_once 'auth_check.php';

// Set response to JSON format
header('Content-Type: application/json');

try {
    // 1. Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Method not allowed');
    }

    // 2. Rate Limiting (Prevents automated enumeration bots)
    // Only allows 10 checks per 60 seconds per user session/IP
    $rate = checkRateLimit('check_username', 10, 60);
    if (!$rate['allowed']) {
        http_response_code(429);
        echo json_encode(['available' => false, 'message' => 'Too many attempts. Please wait.']);
        exit;
    }

    // 3. Retrieve and sanitize input
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';

    if (empty($username)) {
        throw new Exception('Username is required');
    }

    // 4. Input Validation (Checks length/format)
    if (file_exists('validation.php')) {
        require_once 'validation.php';
        // validateUsername is assumed to be in your validation.php file
        if (function_exists('validateUsername')) {
            $validation = validateUsername($username, false);
            if (!$validation['valid']) {
                echo json_encode(['available' => false, 'message' => 'Invalid username']);
                exit;
            }
        }
    }

    // 5. Availability Check (Database + XML)
    $isTaken = false;

    // Check Database first (primary)
    if (isset($conn) && defined('DB_TABLE_USERS')) {
        $stmt = $conn->prepare("SELECT id FROM " . DB_TABLE_USERS . " WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $isTaken = ($result->num_rows > 0);
        $stmt->close();
    }

    // Check XML fallback (secondary)
    if (!$isTaken && file_exists(__DIR__ . '/users.xml')) {
        $xml = @simplexml_load_file(__DIR__ . '/users.xml');
        if ($xml) {
            foreach ($xml->user as $user) {
                if ((string)$user->username === $username) {
                    $isTaken = true;
                    break;
                }
            }
        }
    }

    // 6. Return Result
    echo json_encode([
        'available' => !$isTaken,
        'message' => $isTaken ? 'Username not available' : 'Username available'
    ]);

} catch (Exception $e) {
    // Generic error message for security (Do not reveal $e->getMessage() in production)
    http_response_code(400);
    echo json_encode([
        'available' => false,
        'message' => 'An error occurred during verification.'
    ]);
}
?>