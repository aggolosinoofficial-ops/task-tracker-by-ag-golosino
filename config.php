<?php
/**
 * Configuration file for the To-Do App
 * Optimized for 2GB RAM production environments
 */
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'task_tracker');

// Optional: You can also use this for your conditional checks
define('DB_AVAILABLE', true);
// 1. Session Configuration
if (!defined('SESSION_NAME')) define('SESSION_NAME', 'todo_app');
if (!defined('SESSION_COOKIE_DURATION')) define('SESSION_COOKIE_DURATION', 3600);
if (!defined('SESSION_SECURE')) define('SESSION_SECURE', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
if (!defined('SESSION_HTTPONLY')) define('SESSION_HTTPONLY', true);
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 3600);

// 2. Database configuration
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'task_tracker');

if (!defined('DB_TABLE_USERS')) define('DB_TABLE_USERS', 'users');
if (!defined('DB_TABLE_TASKS')) define('DB_TABLE_TASKS', 'tasks');
if (!defined('ARCHIVE_TABLE')) define('ARCHIVE_TABLE', 'archive_tasks');
if (!defined('DELETED_TABLE')) define('DELETED_TABLE', 'deleted_tasks');
if (!defined('STATS_TABLE')) define('STATS_TABLE', 'task_stats');

// 3. Pagination and Performance
if (!defined('DEFAULT_PAGE_SIZE')) define('DEFAULT_PAGE_SIZE', 50);
if (!defined('MAX_PAGE_SIZE')) define('MAX_PAGE_SIZE', 100);
if (!defined('QUERY_TIMEOUT')) define('QUERY_TIMEOUT', 30);

// 4. Password Policy
if (!defined('MIN_PASSWORD_LENGTH')) define('MIN_PASSWORD_LENGTH', 8);
if (!defined('MAX_PASSWORD_LENGTH')) define('MAX_PASSWORD_LENGTH', 256);

// 5. Rate Limiting
if (!defined('MAX_LOGIN_ATTEMPTS')) define('MAX_LOGIN_ATTEMPTS', 5);
if (!defined('LOCKOUT_DURATION')) define('LOCKOUT_DURATION', 900);
if (!defined('MAX_REGISTRATION_PER_IP')) define('MAX_REGISTRATION_PER_IP', 3);
if (!defined('MAX_LOGIN_ATTEMPTS_PER_IP')) define('MAX_LOGIN_ATTEMPTS_PER_IP', 10);

// 6. CSRF
if (!defined('CSRF_TOKEN_LENGTH')) define('CSRF_TOKEN_LENGTH', 32);
if (!defined('CSRF_TOKEN_EXPIRY')) define('CSRF_TOKEN_EXPIRY', 3600);

// 7. Development Mode
if (!defined('DEV_MODE')) define('DEV_MODE', false);

// 8. Path Calculation
if (!defined('BASE_URL')) {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    define('BASE_URL', $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/task-tracker-by-ag-golosino/');
}

// 9. Error Handling & Performance Optimization
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log'); 

// Memory/Execution limits
ini_set('memory_limit', '128M');
ini_set('max_execution_time', 30);
ini_set('default_socket_timeout', 15);
ini_set('mysql.connect_timeout', 10);
?>