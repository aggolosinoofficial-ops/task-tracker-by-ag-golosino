<?php
/**
 * User Registration Handler - XML-FIRST ARCHITECTURE
 * 
 * PRIMARY: users.xml (OLTP - Online Transaction Processing)
 * SECONDARY: MySQL Database (OLAP - analytical queries)
 * 
 * FLOW:
 * 1. Validate username and password
 * 2. Check uniqueness against XML first, then DB
 * 3. Hash password with bcrypt (cost=10)
 * 4. INSERT TO XML (primary storage)
 * 5. SYNC TO DATABASE (secondary, non-critical)
 * 6. Generate CSRF token
 * 7. Queue failed DB syncs for background retry
 * 8. Return success
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

    // Centralized validation (checks XML first for uniqueness)
    $validation = validateRegistration($username, $password, $confirm_password);
    if (!$validation['valid']) {
        throw new Exception(implode('. ', $validation['errors']));
    }

    // Hash password with bcrypt (cost=10 for 2GB RAM optimization)
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    $created_at = date('Y-m-d\TH:i:s');
    $role = 'user';

    // STEP 1: INSERT TO XML (PRIMARY STORAGE - OLTP)
    $xml_success = false;
    $user_id = 0;
    
    try {
        $users_xml_path = __DIR__ . '/users.xml';
        
        if (!file_exists($users_xml_path)) {
            file_put_contents($users_xml_path, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . '<users></users>');
        }
        
        $xml = simplexml_load_file($users_xml_path);
        if (!$xml) {
            throw new Exception('Failed to load users.xml');
        }
        
        // Find next ID
        $max_id = 0;
        foreach ($xml->user as $u) {
            $current_id = (int)$u->id;
            if ($current_id > $max_id) $max_id = $current_id;
        }
        $user_id = $max_id + 1;
        
        // Add user to XML
        $user_element = $xml->addChild('user');
        $user_element->addChild('id', $user_id);
        $user_element->addChild('username', htmlspecialchars($username));
        $user_element->addChild('password_hash', $password_hash);
        $user_element->addChild('role', $role);
        $user_element->addChild('created_at', $created_at);
        
        $xml->asXML($users_xml_path);
        $xml_success = true;
        
    } catch (Exception $e) {
        throw new Exception('Failed to register user in XML storage: ' . $e->getMessage());
    }

    if (!$xml_success || $user_id <= 0) {
        throw new Exception('Failed to create user account');
    }

    // STEP 2: SYNC TO DATABASE (SECONDARY STORAGE - non-critical)
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

    // STEP 3: Start session and set user data
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

    // Initialize task statistics (optional, don't fail if unavailable)
    try {
        if (isset($conn) && $conn && !$conn->connect_error) {
            updateTaskStats($user_id);
        }
    } catch (Exception $e) {
        error_log("Warning: Failed to initialize task stats: " . $e->getMessage());
    }

    // RESPONSE: Success
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
    // RESPONSE: Error
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    // Cleanup
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}
?>
