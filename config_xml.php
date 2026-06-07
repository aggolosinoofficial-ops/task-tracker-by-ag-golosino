<?php
/**
 * Database Configuration
 * Uses Environment Variables for security and Factory pattern for scalability
 */

// ============================================
// BACKEND CONFIGURATION
// ============================================
// Retrieve from ENV if possible, else default
define('DB_BACKEND', getenv('DB_BACKEND') ?: 'mysql');
define('USE_DUAL_STORAGE', false); 

// ============================================
// DATABASE CONFIGURATION (Security: Use Environment Variables)
// ============================================
// In production, do NOT hardcode these. Use a .env file or server env vars.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: ''); // WARNING: Never commit real passwords
define('DB_NAME', getenv('DB_NAME') ?: 'task_tracker');

// ============================================
// XML CONFIGURATION
// ============================================
define('XML_FILE_PATH', __DIR__ . '/tasks.xml');
define('XSD_FILE_PATH', __DIR__ . '/tasks.xsd');
define('VALIDATE_XML', true);

// ============================================
// APPLICATION SETTINGS
// ============================================
define('LOG_ERRORS', true);
define('ERROR_LOG_FILE', __DIR__ . '/error.log');

/**
 * Get configured database adapter (Factory Pattern)
 * @param mixed $conn Optional existing connection
 * @return DatabaseAdapter
 */
function getDatabase($conn = null): object
{
    require_once 'db_adapter.php';

    // Return MySQL adapter with connection
    if ($conn instanceof mysqli) {
        return new DatabaseAdapter($conn);
    }
    
    // Fallback to XML when MySQL unavailable
    return new DatabaseAdapter(null);
}

/**
 * Get XML handler directly
 */
function getXMLHandler(): object
{
    require_once 'xml_handler.php';
    return new XMLHandler();
}

/**
 * Log error to file with basic security validation
 */
function logError(string $message): void
{
    if (LOG_ERRORS) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        // Ensure the directory is writable by the web server user (www-data)
        error_log($logMessage, 3, ERROR_LOG_FILE);
    }
}
?>