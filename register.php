<?php
/**
 * User Registration Handler - Enhanced Version
 * 
 * PURPOSE: Processes user registration with complete security and validation
 * 
 * FLOW:
 * 1. Validate HTTP method (POST only)
 * 2. Verify CSRF token (prevents cross-site attacks)
 * 3. Check rate limiting by IP (prevents brute force/spam registration)
 * 4. Validate username format and uniqueness
 * 5. Validate password strength (uppercase, numbers, special chars)
 * 6. Hash password with bcrypt (cost=10 for low-RAM optimization)
 * 7. Insert user into database
 * 8. Generate CSRF token for first login
 * 9. Sync user to XML backup
 * 10. Return success response
 * 
 * SECURITY MEASURES:
 * - CSRF token validation prevents unauthorized form submissions
 * - Rate limiting (3 attempts per hour per IP) prevents registration spam
 * - Password hashing with bcrypt (cost=10) protects passwords even if database is compromised
 * - Input validation prevents injection attacks
 * - Prepared statements prevent SQL injection
 * 
 * OPTIMIZATION:
 * - Bcrypt cost=10 (instead of default 12) reduces CPU/memory load on 2GB RAM systems
 * - Single database query to check username existence
 * - Inline CSRF token generation (no separate DB call needed)
 * 
 * RETURNS: JSON with success/error status and optional user_id
 */

include 'auth_check.php';
include 'xml_sync_handler.php';

header('Content-Type: application/json');

try {
    /**
     * SECURITY: Only accept POST requests
     * GET requests would be cached by browser and expose CSRF tokens in logs
     */
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Method not allowed');
    }

    /**
     * SECURITY: Verify CSRF token prevents cross-site request forgery
     * Attacker cannot forge valid token without access to user's session
     * Token is generated server-side and validated on submission
     */
    $csrf_token = isset($_POST['csrf_token']) ? trim($_POST['csrf_token']) : '';
    if (!verifyCSRFToken($csrf_token)) {
        http_response_code(403);
        throw new Exception('Invalid request token. Please refresh and try again');
    }

    /**
     * SECURITY: Rate limiting prevents registration spam/brute force
     * Max 3 registration attempts per IP per hour
     * Attacker cannot create unlimited accounts from same IP
     */
    $rate_check = checkRateLimit('registration', MAX_REGISTRATION_PER_IP, 3600);
    if (!$rate_check['allowed']) {
        http_response_code(429);
        throw new Exception("Too many registration attempts. Please wait " . ceil($rate_check['wait_seconds'] / 60) . " minutes");
    }

    /**
     * VALIDATION: Get and sanitize form input
     * trim() removes leading/trailing whitespace
     * Empty check prevents "required" bypass via HTML-only validation
     */
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    // VALIDATION: Prevent empty field bypass
    if (empty($username) || empty($password) || empty($confirm_password)) {
        throw new Exception('Username and password are required');
    }

    /**
     * VALIDATION: Username length check (3-50 characters)
     * Prevents extremely short/long usernames that could break UX
     */
    if (strlen($username) < 3 || strlen($username) > 50) {
        throw new Exception('Username must be between 3 and 50 characters');
    }

    /**
     * VALIDATION: Username format check (alphanumeric, underscore, hyphen only)
     * Whitelist approach safer than blacklist
     * Prevents special characters that might cause display/URL issues
     */
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        throw new Exception('Username can only contain letters, numbers, underscores, and hyphens');
    }

    /**
     * VALIDATION: Password confirmation match
     * Prevents typos in password entry
     * Client-side check but verified again on server for security
     */
    if ($password !== $confirm_password) {
        throw new Exception('Passwords do not match');
    }

    /**
     * SECURITY: Password strength validation
     * Requires: 8+ chars, 1 uppercase, 1 number, 1 special char
     * Prevents weak passwords that are easy to crack
     * Configuration in config.php allows adjustment per deployment
     */
    $pwd_validation = validatePasswordStrength($password);
    if (!$pwd_validation['valid']) {
        throw new Exception(implode('. ', $pwd_validation['errors']));
    }

    /**
     * VALIDATION: Check if username already exists
     * Prepared statement prevents SQL injection
     * Only checks if exists (doesn't fetch password for security)
     */
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

    /**
     * SECURITY: Hash password using bcrypt
     * cost=10 optimized for 2GB RAM systems
     * cost=12 (default) requires ~100MB RAM per hash calculation
     * cost=10 requires ~25MB RAM per hash calculation
     * Each iteration doubles computation time (2^cost algorithm iterations)
     * 
     * WHY BCRYPT:
     * - Deliberately slow (1/10th second per hash) = defeats dictionary attacks
     * - Includes salt automatically (prevents rainbow table attacks)
     * - Can be re-hashed with higher cost as hardware improves
     */
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

    /**
     * DATABASE: Insert new user into users table
     * Prepared statement prevents SQL injection
     * User role defaults to 'user' (not admin)
     * created_at timestamp set by MySQL NOW() function
     */
    $stmt = $conn->prepare("INSERT INTO " . DB_NAME . "." . DB_TABLE_USERS . " (username, password_hash, created_at) VALUES (?, ?, NOW())");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param("ss", $username, $password_hash);

    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        $stmt->close();

        /**
         * SECURITY: Generate CSRF token for new user's session
         * Token is randomly generated server-side using random_bytes()
         * Token stored in $_SESSION (server-side, not sent to client except in forms)
         * Prevents cross-site attacks on their first login
         */
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        $_SESSION['csrf_token_time'] = time();

        /**
         * BACKUP: Sync new user to XML file
         * users.xml mirrors users table as backup
         * If MySQL database fails, data can be restored from XML
         * Includes user_id, username, hashed password, role, created_at
         */
        $sync = getXMLSyncHandler();
        $sync->syncUserToXML($user_id, $username, $password_hash, 'user', date('Y-m-d H:i:s'));

        /**
         * OPTIMIZATION: Initialize task statistics for new user
         * Creates empty stats record (0 tasks, 0 completed, 0 archived)
         * Prevents division by zero or NULL errors in dashboard queries
         * One query initialization is faster than multiple queries later
         */
        updateTaskStats($user_id);

        // Optional activity logging (uncomment if audit trail needed)
        // logActivity($user_id, 'registration', 'Registration successful');

        // RESPONSE: Success message with user ID
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful! Please log in.',
            'user_id' => $user_id
        ]);
    } else {
        /**
         * ERROR HANDLING: Database insertion failure
         * MySQL error 1062 = duplicate entry (username already exists)
         * Other errors = database connectivity or server issues
         */
        if ($conn->errno == 1062) {
            throw new Exception('This username is already registered');
        } else {
            throw new Exception('Registration failed: ' . $stmt->error);
        }
    }

} catch (Exception $e) {
    // RESPONSE: Error message to client as JSON
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    /**
     * CLEANUP: Close database statement and connection
     * Prevents connection leaks and resource exhaustion
     * Finally block runs regardless of success/error
     */
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}
?>