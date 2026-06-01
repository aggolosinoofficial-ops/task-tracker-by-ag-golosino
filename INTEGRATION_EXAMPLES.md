<?php
/**
 * INTEGRATION EXAMPLE: Register Page
 * Shows how to replace old DB calls with XML-first storage adapter
 * 
 * BEFORE: register.php used direct MySQL only
 * AFTER: Uses storage adapter (XML primary, MySQL sync in background)
 */

include 'config.php';
include 'storage_adapter.php';

// Handle registration request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    
    // Validation
    $errors = [];
    
    if (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters";
    }
    
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        $errors[] = "Password must be at least " . MIN_PASSWORD_LENGTH . " characters";
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }
    
    // Check if user exists (XML-first check)
    if (!$errors) {
        $existingUser = $storageAdapter->getUserByUsername($username);
        if ($existingUser) {
            $errors[] = "Username already exists";
        }
    }
    
    if (!$errors) {
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        
        // Register user (XML primary, MySQL sync in background)
        $result = $storageAdapter->registerUser($username, $passwordHash, 'user');
        
        if ($result['success']) {
            // Redirect to login
            header('Location: login.html');
            exit;
        } else {
            $errors[] = $result['error'] ?? 'Registration failed';
        }
    }
    
    // Return JSON for AJAX
    header('Content-Type: application/json');
    echo json_encode([
        'success' => empty($errors),
        'errors' => $errors
    ]);
    exit;
}
?>
