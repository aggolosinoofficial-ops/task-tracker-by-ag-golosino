<?php
/**
 * Configuration file for the To-Do App
 * Optimized for 2GB RAM production environments
 */

// Database configuration (Uses ENV variables if available, otherwise defaults)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: ''); // Ensure this is not committed to git/version control
define('DB_NAME', getenv('DB_NAME') ?: 'test');

define('DB_TABLE_USERS', 'users');
define('DB_TABLE_TASKS', 'tasks');
define('ARCHIVE_TABLE', 'archive_tasks');
define('DELETED_TABLE', 'deleted_tasks');
define('STATS_TABLE', 'task_stats');

// Pagination and Performance
define('DEFAULT_PAGE_SIZE', 50);
define('MAX_PAGE_SIZE', 100);
define('QUERY_TIMEOUT', 30);

// Session Security
define('SESSION_TIMEOUT', 3600);
define('TOKEN_LENGTH', 32);
define('SESSION_NAME', 'todo_app');
define('SESSION_SECURE', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'); // Auto-detect HTTPS
define('SESSION_HTTPONLY', true);

// Password Policy
define('MIN_PASSWORD_LENGTH', 8);
define('MAX_PASSWORD_LENGTH', 256);

// Rate Limiting
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900);
define('MAX_REGISTRATION_PER_IP', 3);
define('MAX_LOGIN_ATTEMPTS_PER_IP', 10);

// CSRF
define('CSRF_TOKEN_LENGTH', 32);
define('CSRF_TOKEN_EXPIRY', 86400);

// Development Mode
define('DEV_MODE', false);

// Path Calculation
$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
define('BASE_URL', $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/task-tracker-by-ag-golosino/');

// Error Handling & Performance Optimization
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// Ensure your web server has write permissions to this file
ini_set('error_log', __DIR__ . '/php-error.log'); 

// Memory/Execution limits for 2GB RAM
ini_set('memory_limit', '128M');
ini_set('max_execution_time', 30);
ini_set('default_socket_timeout', 15);
ini_set('mysql.connect_timeout', 10);
?>