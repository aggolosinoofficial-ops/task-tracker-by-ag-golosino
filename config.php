<?php
/**
 * Configuration file for the To-Do App
 * Store all constants and configuration settings here
 * OPTIMIZED for 2GB RAM systems
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Default XAMPP password - change if needed
define('DB_NAME', 'test');
define('DB_TABLE_USERS', 'users');
define('DB_TABLE_TASKS', 'tasks');
define('ARCHIVE_TABLE', 'archive_tasks');
define('DELETED_TABLE', 'deleted_tasks');
define('STATS_TABLE', 'task_stats');

// OPTIMIZATION: Query limits and pagination
define('DEFAULT_PAGE_SIZE', 50);      // Tasks per page
define('MAX_PAGE_SIZE', 100);         // Maximum allowed page size
define('QUERY_TIMEOUT', 30);          // Max execution time for queries

// Session configuration
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('TOKEN_LENGTH', 32); // Bytes for random_bytes()
define('SESSION_NAME', 'todo_app');
define('SESSION_SECURE', false); // Set to true in production with HTTPS
define('SESSION_HTTPONLY', true); // Prevent JavaScript access

// OPTIMIZATION: Reduce memory footprint
define('SESSION_CACHE_LIMITER', 'nocache');
define('SESSION_CACHE_EXPIRE', 60);

// Password policy
define('MIN_PASSWORD_LENGTH', 8);
define('MAX_PASSWORD_LENGTH', 128);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_NUMBERS', true);
define('PASSWORD_REQUIRE_SPECIAL', true);

// Rate limiting
define('MAX_LOGIN_ATTEMPTS', 5); // Max failed attempts before lockout
define('LOCKOUT_DURATION', 900); // 15 minutes in seconds
define('MAX_REGISTRATION_PER_IP', 3); // Per hour
define('MAX_LOGIN_ATTEMPTS_PER_IP', 10); // Per hour

// CSRF protection
define('CSRF_TOKEN_LENGTH', 32);
define('CSRF_TOKEN_EXPIRY', 86400); // 24 hours

// DEVELOPMENT MODE - Set to false in production!
// WARNING: This disables rate limiting for testing
// ENABLED BY DEFAULT FOR LOCAL DEVELOPMENT
define('DEV_MODE', true);

// Security headers
define('SESSION_COOKIE_DURATION', 3600);

// Paths
$scheme = 'http';
if (isset($_SERVER['REQUEST_SCHEME'])) {
    $scheme = $_SERVER['REQUEST_SCHEME'];
} elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $scheme = 'https';
}
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $scheme . '://' . $host . '/task-tracker-by-ag-golosino/');

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// OPTIMIZATION: Memory and performance settings for 2GB RAM
ini_set('memory_limit', '128M');              // Reasonable limit for shared hosting
ini_set('max_execution_time', 30);            // Prevent long-running queries
ini_set('default_socket_timeout', 15);        // Quick timeout
ini_set('mysql.connect_timeout', 10);         // Database connection timeout
?>