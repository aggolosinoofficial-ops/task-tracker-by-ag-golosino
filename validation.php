<?php
/**
 * Centralized Validation Module
 * 
 * RELAXED VALIDATION RULES:
 * - Username: 2-30 chars, ANY characters allowed (letters, numbers, emojis, spaces, symbols)
 * - Password: Minimum 8 chars, no forced uppercase/numbers/special chars
 * - Warnings for weak passwords optional, but all are allowed
 * 
 * This module replaces scattered validation functions and provides:
 * - Centralized rules (single source of truth)
 * - Consistent validation across login/registration
 * - XML and DB query support
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * Validate username format and uniqueness
 * 
 * @param string $username Username to validate
 * @param bool $check_existence Check if username exists in DB/XML (for registration)
 * @return array ['valid' => bool, 'errors' => array, 'message' => string]
 */
function validateUsername($username, $check_existence = false)
{
    $username = trim($username);
    $errors = [];

    // Length validation: 2-30 characters
    if (strlen($username) < 2) {
        $errors[] = "Username must be at least 2 characters";
    }

    if (strlen($username) > 30) {
        $errors[] = "Username must not exceed 30 characters";
    }

    // Accept ANY characters (no format restrictions)
    // UTF-8 compatible check
    if (!mb_check_encoding($username, 'UTF-8')) {
        $errors[] = "Username contains invalid characters";
    }

    // If registration, check uniqueness
    if ($check_existence && count($errors) === 0) {
        if (usernameExists($username)) {
            $errors[] = "Username not available";
        }
    }

    return [
        'valid' => count($errors) === 0,
        'errors' => $errors,
        'message' => count($errors) === 0 ? 'Username valid' : implode(', ', $errors)
    ];
}

/**
 * Validate password
 * 
 * RELAXED RULES:
 * - Minimum 8 characters
 * - No forced uppercase, numbers, or special characters
 * - All passwords are accepted
 * - Optional: Warn for weak passwords (no enforcement)
 * 
 * @param string $password Password to validate
 * @param bool $warn_weak Warn for weak passwords (optional, no rejection)
 * @return array ['valid' => bool, 'errors' => array, 'warnings' => array]
 */
function validatePassword($password, $warn_weak = true)
{
    $errors = [];
    $warnings = [];

    // REQUIRED: Minimum 8 characters
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }

    // OPTIONAL: Maximum length check (no real need for 128 if just storing hash)
    if (strlen($password) > 256) {
        $errors[] = "Password is too long";
    }

    // WARNINGS ONLY (not enforced)
    if ($warn_weak) {
        if (!preg_match('/[A-Z]/', $password)) {
            $warnings[] = "Password does not contain uppercase letters";
        }
        if (!preg_match('/[0-9]/', $password)) {
            $warnings[] = "Password does not contain numbers";
        }
        if (!preg_match('/[!@#$%^&*()\-_=+\[\]{};:\'",.<>?\/\\|`~]/', $password)) {
            $warnings[] = "Password does not contain special characters";
        }
    }

    return [
        'valid' => count($errors) === 0,
        'errors' => $errors,
        'warnings' => $warnings,
        'message' => count($errors) === 0 ? 'Password valid' : implode(', ', $errors)
    ];
}

/**
 * Check if username exists in XML or DB (XML-FIRST)
 * PRIMARY: Checks XML storage first (OLTP)
 * SECONDARY: Checks database if XML unavailable
 * 
 * @param string $username Username to check
 * @return bool True if username exists in XML or DB, false otherwise
 */
function usernameExists($username)
{
    try {
        // STEP 1: Check XML FIRST (PRIMARY STORAGE)
        if (file_exists(__DIR__ . '/users.xml')) {
            try {
                $xml = simplexml_load_file(__DIR__ . '/users.xml');
                if ($xml) {
                    foreach ($xml->user as $user) {
                        if ((string)$user->username === $username) {
                            return true; // Found in primary storage
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("XML check warning: " . $e->getMessage());
            }
        }

        // STEP 2: Check DATABASE (SECONDARY STORAGE) if available
        try {
            global $conn;
            if (isset($conn) && $conn && !$conn->connect_error) {
                $stmt = $conn->prepare("SELECT id FROM " . DB_NAME . "." . DB_TABLE_USERS . " WHERE username = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $stmt->close();
                    
                    if ($result->num_rows > 0) {
                        return true; // Found in secondary storage
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Database check warning: " . $e->getMessage());
        }

        return false; // Not found in either storage
    } catch (Exception $e) {
        error_log("Error checking username existence: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate login credentials
 * Used by login.php to verify username and password format
 * 
 * @param string $username Username
 * @param string $password Password
 * @return array ['valid' => bool, 'errors' => array]
 */
function validateLoginCredentials($username, $password)
{
    $errors = [];

    $username_validation = validateUsername($username, false);
    if (!$username_validation['valid']) {
        $errors[] = "Invalid username format";
    }

    $password_validation = validatePassword($password, false);
    if (!$password_validation['valid']) {
        $errors[] = "Invalid password format";
    }

    return [
        'valid' => count($errors) === 0,
        'errors' => $errors,
        'message' => count($errors) === 0 ? 'Credentials format valid' : implode(', ', $errors)
    ];
}

/**
 * Validate registration data (username + password)
 * Performs all validation including uniqueness check
 * 
 * @param string $username Username
 * @param string $password Password
 * @param string $confirm_password Confirmation password
 * @return array ['valid' => bool, 'errors' => array, 'warnings' => array]
 */
function validateRegistration($username, $password, $confirm_password = null)
{
    $all_errors = [];
    $all_warnings = [];

    // Validate username (with existence check)
    $username_validation = validateUsername($username, true);
    if (!$username_validation['valid']) {
        $all_errors = array_merge($all_errors, $username_validation['errors']);
    }

    // Validate password
    $password_validation = validatePassword($password, true);
    if (!$password_validation['valid']) {
        $all_errors = array_merge($all_errors, $password_validation['errors']);
    }
    $all_warnings = array_merge($all_warnings, $password_validation['warnings']);

    // Check password confirmation if provided
    if ($confirm_password !== null && $password !== $confirm_password) {
        $all_errors[] = "Passwords do not match";
    }

    return [
        'valid' => count($all_errors) === 0,
        'errors' => $all_errors,
        'warnings' => $all_warnings,
        'message' => count($all_errors) === 0 
            ? 'Registration data valid' 
            : implode(', ', $all_errors)
    ];
}

/**
 * Get password strength indicator
 * Returns a visual indicator for password strength (informational only)
 * 
 * @param string $password Password to check
 * @return string 'weak' | 'fair' | 'good' | 'strong'
 */
function getPasswordStrength($password)
{
    $length = strlen($password);
    $has_upper = preg_match('/[A-Z]/', $password);
    $has_lower = preg_match('/[a-z]/', $password);
    $has_number = preg_match('/[0-9]/', $password);
    $has_special = preg_match('/[!@#$%^&*()\-_=+\[\]{};:\'",.<>?\/\\|`~]/', $password);

    $score = 0;
    if ($length >= 8) $score++;
    if ($length >= 12) $score++;
    if ($length >= 16) $score++;
    if ($has_upper) $score++;
    if ($has_lower) $score++;
    if ($has_number) $score++;
    if ($has_special) $score++;

    if ($score <= 1) return 'weak';
    if ($score <= 3) return 'fair';
    if ($score <= 5) return 'good';
    return 'strong';
}

?>
