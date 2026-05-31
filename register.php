<?php
/**
 * User Registration Handler - Enhanced Version
 * Processes registration form submission with comprehensive validation
 * - Validates input format and strength
 * - Checks password confirmation
 * - Creates bcrypt hash from password
 * - Rate limiting and CSRF protection
 * - Stores user in database
 */

include 'auth_check.php';
include 'xml_sync_handler.php';

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
    $rate_check = checkRateLimit('registration', MAX_REGISTRATION_PER_IP, 3600);
    if (!$rate_check['allowed']) {
        http_response_code(429);
        throw new Exception("Too many registration attempts. Please wait " . ceil($rate_check['wait_seconds'] / 60) . " minutes");
    }

    // Get and sanitize input
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    // Validate empty fields
    if (empty($username) || empty($password) || empty($confirm_password)) {
        throw new Exception('Username and password are required');
    }

    // Username validation
    if (strlen($username) < 3 || strlen($username) > 50) {
        throw new Exception('Username must be between 3 and 50 characters');
    }

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        throw new Exception('Username can only contain letters, numbers, underscores, and hyphens');
    }

    // Password confirmation
    if ($password !== $confirm_password) {
        throw new Exception('Passwords do not match');
    }

    // Password strength validation
    $pwd_validation = validatePasswordStrength($password);
    if (!$pwd_validation['valid']) {
        throw new Exception(implode('. ', $pwd_validation['errors']));
    }

    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM " . DB_NAME . "." . DB_TABLE_USERS . " WHERE username = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param("s", $username);
    if (!$stmt->execute()) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $stmt->close();
        throw new Exception('Username already exists. Please choose a different one');
    }
    $stmt->close();

    // Hash password using bcrypt with cost optimization for low-resource systems
    // Using cost=10 instead of 12 for faster hashing on 2GB RAM systems
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

    // Insert new user into database
    $stmt = $conn->prepare("INSERT INTO " . DB_NAME . "." . DB_TABLE_USERS . " (username, password_hash, created_at) VALUES (?, ?, NOW())");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param("ss", $username, $password_hash);

    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        $stmt->close();

        // Sync new user to XML backup
        $sync = getXMLSyncHandler();
        $sync->syncUserToXML($user_id, $username, $password_hash, 'user', date('Y-m-d H:i:s'));

        // Log successful registration (optional - uncomment if you add logging)
        // logActivity($user_id, 'registration', 'Registration successful');

        echo json_encode([
            'success' => true,
            'message' => 'Registration successful! Please log in.',
            'user_id' => $user_id
        ]);
    } else {
        // Check for specific error
        if ($conn->errno == 1062) {
            throw new Exception('This username is already registered');
        } else {
            throw new Exception('Registration failed: ' . $stmt->error);
        }
    }

} catch (Exception $e) {
    http_response_code(400);
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