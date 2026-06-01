<?php
/**
 * CSRF Token Generator
 * Returns a fresh CSRF token for the client
 * Must be called before form submission
 */

include 'auth_check.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    // Generate new CSRF token
    $token = generateCSRFToken();
    
    if (!$token) {
        throw new Exception('Failed to generate CSRF token');
    }
    
    echo json_encode([
        'success' => true,
        'token' => $token,
        'expiry' => CSRF_TOKEN_EXPIRY
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to generate security token: ' . $e->getMessage()
    ]);
}
?>