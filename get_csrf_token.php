<?php
/**
 * CSRF Token Generator
 * Called via AJAX to get a fresh CSRF token for form protection
 * Used by registration and login forms
 */

include 'auth_check.php';

header('Content-Type: application/json');

try {
    // Generate or retrieve CSRF token
    $token = generateCSRFToken();

    if (!$token) {
        throw new Exception('Failed to generate security token');
    }

    echo json_encode([
        'success' => true,
        'token' => $token
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>