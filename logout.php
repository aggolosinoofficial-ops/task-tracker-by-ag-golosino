<?php
/**
 * Logout Handler
 * Destroys the user session and redirects to login page
 */
function logoutUser() {
    // 1. Start the session to ensure we are working with the correct one
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 2. Unset all session variables
    $_SESSION = array();

    // 3. Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // 4. Destroy the session
    session_destroy();
}
include 'auth_check.php';

// Logout the user
logoutUser();

// Redirect to login page
header('Location: login.html');
exit();
?>