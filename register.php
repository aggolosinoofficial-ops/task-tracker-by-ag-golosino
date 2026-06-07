<?php
/**
 * User Registration Handler - XML-FIRST ARCHITECTURE
 * * PRIMARY: users.xml (OLTP - Online Transaction Processing)
 * SECONDARY: MySQL Database (OLAP - analytical queries)
 */

include 'config.php';
include 'db.php';
include 'validation.php';

header('Content-Type: application/json');

try {
    // SECURITY: Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Method not allowed');
    }

    // Get and validate input
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if (empty($username) || empty($password) || empty($confirm_password)) {
        throw new Exception('Username and password are required');
    }

// Centralized validation (checks DB first for uniqueness)
    $validation = validateRegistration($username, $password, $confirm_password);
    if (!$validation['valid']) {
        throw new Exception(implode('. ', $validation['errors']));
    }

    // Hash password with bcrypt (cost=10)
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    $created_at = date('Y-m-d\TH:i:s');
    $role = 'user';

// --- STEP 1: INSERT TO DATABASE (PRIMARY STORAGE - OLTP) ---
    $db_success = false;
    $user_id = 0;

    $conn = getDatabaseConnection();

    if ($conn === null) {
        throw new Exception('Database unavailable for registration.');
    }

    // Ensure role + schema exist; users table is the source of truth
    $stmt = $conn->prepare(
        "INSERT INTO " . DB_NAME . "." . DB_TABLE_USERS . " (username, password_hash, role, created_at) VALUES (?, ?, ?, ?)"
    );
    if (!$stmt) {
        throw new Exception('Failed to prepare registration query');
    }

    $stmt->bind_param("ssss", $username, $password_hash, $role, $created_at);
    if (!$stmt->execute()) {
        $err = $conn->error ?: 'Insert failed';
        $stmt->close();
        throw new Exception('Registration failed: ' . $err);
    }

    $user_id = (int)$conn->insert_id;
    $db_success = $user_id > 0;
    $stmt->close();

    if (!$db_success) {
        throw new Exception('Registration failed: could not determine new user id');
    }

// --- STEP 2: SYNC TO XML (SECONDARY STORAGE - non-critical) ---
    $db_sync_status = 'pending';
    try {
        if (isset($conn) && $conn && !$conn->connect_error) {
            $stmt = $conn->prepare("INSERT INTO " . DB_NAME . "." . DB_TABLE_USERS . " (id, username, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("issss", $user_id, $username, $password_hash, $role, $created_at);
                if ($stmt->execute()) {
                    $db_sync_status = 'synced';
                } else {
                    $db_sync_status = 'failed';
                }
                $stmt->close();
            }
        }
    } catch (Exception $e) {
        error_log("Non-critical: Database sync failed: " . $e->getMessage());
        $db_sync_status = 'unavailable';
    }

    // --- STEP 3: Start session and set user data ---
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

    // Generate CSRF token
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    $_SESSION['csrf_token_time'] = time();

    // Initialize task statistics
    try {
        if (isset($conn) && $conn && !$conn->connect_error) {
            updateTaskStats($user_id);
        }
    } catch (Exception $e) {
        error_log("Warning: Failed to initialize task stats: " . $e->getMessage());
    }

    // --- RESPONSE ---
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful! Please log in.',
        'user_id' => $user_id,
        'storage' => [
            'xml' => 'primary (active)',
            'database' => $db_sync_status
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    // Cleanup
    if (isset($stmt) && is_object($stmt)) {
        $stmt->close();
    }
    if (isset($conn) && is_object($conn)) {
        $conn->close();
    }
}
?>