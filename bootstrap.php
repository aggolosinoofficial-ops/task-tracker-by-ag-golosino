<?php
/**
 * bootstrap.php
 * Single unified initialization file
 * Prevents duplicate includes and ensures consistent startup sequence
 */

if (!defined('_BOOTSTRAP_LOADED')) {
    define('_BOOTSTRAP_LOADED', true);
    
    // Load configuration
    require_once __DIR__ . '/config.php';
    
    // Initialize database connection
    require_once __DIR__ . '/db.php';
    
    // Load authentication service (must come after config and db)
    require_once __DIR__ . '/AuthService.php';
}
