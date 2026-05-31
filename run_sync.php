<?php
// CLI script to rebuild XML files from MySQL
require 'config.php';
require 'db.php';
require 'xml_sync_handler.php';

if (PHP_SAPI === 'cli') {
    ini_set('max_execution_time', '0');
    set_time_limit(0);
    ini_set('memory_limit', '256M');
}

$sync = getXMLSyncHandler();

echo "Rebuilding tasks.xml...\n";
$tasks_result = $sync->syncAllTasksToXML($conn);
if ($tasks_result) {
    echo "tasks.xml rebuilt successfully\n";
} else {
    echo "Failed to rebuild tasks.xml\n";
}

echo "Rebuilding users.xml...\n";
$users_result = $sync->syncAllUsersToXML($conn);
if ($users_result) {
    echo "users.xml rebuilt successfully\n";
} else {
    echo "Failed to rebuild users.xml\n";
}

echo "Rebuilding archive_tasks.xml...\n";
$archive_result = $sync->syncAllArchiveTasksToXML($conn);
if ($archive_result) {
    echo "archive_tasks.xml rebuilt successfully\n";
} else {
    echo "Failed to rebuild archive_tasks.xml\n";
}

echo "Rebuilding deleted_tasks.xml...\n";
$deleted_result = $sync->syncAllDeletedTasksToXML($conn);
if ($deleted_result) {
    echo "deleted_tasks.xml rebuilt successfully\n";
} else {
    echo "Failed to rebuild deleted_tasks.xml\n";
}

if ($tasks_result && $users_result) {
    echo "All sync operations completed successfully\n";
    exit(0);
} else {
    echo "One or more sync operations failed. Check PHP error log for details.\n";
    exit(1);
}
?>