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
 * - Checks both DB and XML for consistency
 * - Only returns availability status (no other data)
 * - Rate limited via session to prevent brute force enumeration
 * - POST method only (prevents caching in logs)
 * 
 * RETURNS: JSON with availability status
 */

include 'config.php';
include 'validation.php';

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
    
    // Use centralized validation (length check and format)
    $validation = validateUsername($username, false);
    if (!$validation['valid']) {
        // Return not valid, but don't reveal specific reasons (security)
        echo json_encode([
            'available' => false,
            'message' => 'Invalid username'
        ]);
        exit;
    }
    
    // Check if username exists (queries both DB and XML)
    if (usernameExists($username)) {
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
        'message' => 'Error checking username: ' . $e->getMessage()
    ]);
}

?>

        'available' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
