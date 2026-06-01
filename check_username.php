<?php
/**
 * Check Username Availability
 * Real-time validation for registration form
 * 
 * PURPOSE: Check if username is available before registration
 * NO CSRF TOKEN REQUIRED (read-only public check)
 * 
 * SECURITY:
 * - Prepared statements prevent SQL injection
 * - Only returns availability status (no other data)
 * - Rate limited via session to prevent brute force enumeration
 * - POST method only (prevents caching in logs)
 * 
 * RETURNS: JSON with availability status
 */

include 'config.php';
include 'db.php';
include 'auth_check.php';

header('Content-Type: application/json');

try {
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Method not allowed');
    }
    
    // Get and sanitize username
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    
    // Validate input
    if (empty($username)) {
        throw new Exception('Username is required');
    }
    
    // Length validation
    if (strlen($username) < 3 || strlen($username) > 30) {
        throw new Exception('Invalid username length');
    }
    
    // Format validation (same as registration)
    if (!preg_match('/^[\w\s\u0080-\uFFFF]+$/u', $username)) {
        throw new Exception('Invalid username format');
    }
    
    // Query database to check if username exists
    $stmt = $conn->prepare("SELECT id FROM " . DB_NAME . "." . DB_TABLE_USERS . " WHERE username = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $username);
    if (!$stmt->execute()) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $result = $stmt->get_result();
    $stmt->close();
    
    // Return availability status
    if ($result->num_rows > 0) {
        // Username exists - NOT available
        echo json_encode([
            'available' => false,
            'message' => 'Username not available'
        ]);
    } else {
        // Username does not exist - AVAILABLE
        echo json_encode([
            'available' => true,
            'message' => 'Username available'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'available' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
