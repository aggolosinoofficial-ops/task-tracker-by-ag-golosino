<?php
require_once 'config.php';
require_once 'db.php';

$c = getDatabaseConnection();
if (!$c) {
  echo "NO CONNECTION\n";
  exit;
}

$tables = ['users','tasks','archive_tasks','deleted_tasks','task_stats'];
foreach ($tables as $t) {
  $r = $c->query("SHOW TABLES LIKE '$t'");
  $exists = ($r && $r->num_rows > 0);
  echo ($exists ? '✓' : '✗') . " $t " . ($exists ? 'exists' : 'missing') . "\n";
}

echo "DB used: " . DB_NAME . "\n";
?>
