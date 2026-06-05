<?php
/**
 * Database Configuration
 * Switch between MySQL and XML backends here
 */

// ============================================
// BACKEND CONFIGURATION
// ============================================
// Set to 'mysql' to use MySQL database
// Set to 'xml' to use XML local storage
// ============================================
define('DB_BACKEND', 'mysql');

// Enable this to use both backends for redundancy
define('USE_DUAL_STORAGE', true);

// ============================================
// DATABASE CONFIGURATION
// ============================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'test');

// ============================================
// XML CONFIGURATION
// ============================================
define('XML_FILE_PATH', __DIR__ . '/tasks.xml');
define('XSD_FILE_PATH', __DIR__ . '/tasks.xsd');
define('VALIDATE_XML', true); // Enable/disable XSD validation

// ============================================
// APPLICATION SETTINGS
// ============================================
define('LOG_ERRORS', true);
define('ERROR_LOG_FILE', __DIR__ . '/error.log');

/**
 * Get configured database adapter
 * @return DatabaseAdapter
 */
function getDatabase($conn = null)
{
    require_once 'db_adapter.php';

    if (DB_BACKEND === 'mysql') {
        return new DatabaseAdapter('mysql', $conn);
    } else if (DB_BACKEND === 'xml') {
        return new DatabaseAdapter('xml');
    }

    throw new Exception('Invalid DB_BACKEND configuration');
}

/**
 * Get XML handler directly (if needed)
 * @return XMLTaskHandler
 */
function getXMLHandler()
{
    require_once 'xml_handler.php';
    return new XMLTaskHandler(XML_FILE_PATH);
}

/**
 * Log error to file
 */
function logError($message)
{
    if (LOG_ERRORS) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        error_log($logMessage, 3, ERROR_LOG_FILE);
    }
}
?>