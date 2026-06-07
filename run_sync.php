<?php
/**
 * run_sync.php
 * Fixed: Now calls getDatabaseConnection() from db.php correctly.
 */

// 1. Ensure this is only run from the Command Line
if (PHP_SAPI !== 'cli') {
    die("Error: This script must be run from the command line.");
}

// 2. Set environment settings
ini_set('max_execution_time', '0');
set_time_limit(0);
ini_set('memory_limit', '256M');

// 3. Load Dependencies
require_once 'config.php';
require_once 'db.php';          // Contains getDatabaseConnection()
require_once 'xml_sync_handler.php';

// 4. Initialize Database Connection
// We call the function defined in your db.php
$conn = getDatabaseConnection();

if ($conn === null) {
    die("FATAL ERROR: Could not connect to the database. Check your db.php credentials.\n");
}

// 5. Initialize Sync Handler
// We pass the connection to the class constructor
$sync = new XMLSyncHandler($conn);

// 6. Execute Sync Operations
echo "--- Starting Sync ---\n";

echo "Rebuilding tasks.xml... ";
$sync->syncAllTasksToXML(); 
echo "Done.\n";

echo "Rebuilding users.xml... ";
$sync->syncAllUsersToXML();
echo "Done.\n";

echo "Rebuilding archive_tasks.xml... ";
$sync->syncAllArchiveTasksToXML();
echo "Done.\n";

echo "Rebuilding deleted_tasks.xml... ";
$sync->syncAllDeletedTasksToXML();
echo "Done.\n";

echo "--- All operations completed ---\n";
?>