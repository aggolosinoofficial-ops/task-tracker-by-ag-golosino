<?php
declare(strict_types=1);

/**
 * CSRF Token Generator
 * Returns a fresh CSRF token for the client.
 * Works on login page (no auth required) and authenticated pages.
 */

include 'config.php';

// Start session securely
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_COOKIE_DURATION,
        'path'     => '/',
        'secure'   => SESSION_SECURE,
        'httponly' => SESSION_HTTPONLY,
        'samesite' => 'Strict'
    ]);
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

try {
    // Check if token exists and if it is still valid based on time
    $isExpired = false;
    if (isset($_SESSION['csrf_token'], $_SESSION['csrf_token_time'])) {
        if ((time() - (int)$_SESSION['csrf_token_time']) > CSRF_TOKEN_EXPIRY) {
            $isExpired = true;
        }
    }

    // Generate or regenerate CSRF token if not set or expired
    if (!isset($_SESSION['csrf_token']) || $isExpired) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        $_SESSION['csrf_token_time'] = time();
    }
    
    $token = $_SESSION['csrf_token'];
    
    // Safety check
    if (empty($token)) {
        throw new Exception('Failed to generate token');
    }
    
    echo json_encode([
        'success' => true,
        'token'   => $token,
        'expiry'  => CSRF_TOKEN_EXPIRY
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error'   => 'Token error: ' . $e->getMessage()
    ]);
}