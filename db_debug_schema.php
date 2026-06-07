<?php
require_once 'config.php';
require_once 'db.php';

$c = getDatabaseConnection();
if (!$c) {
  echo "NO CONNECTION\n";
  exit;
}

$db = DB_NAME;
$tables = ['users','tasks','archive_tasks','deleted_tasks','task_stats'];

foreach ($tables as $t) {
  $r = $c->query("SHOW TABLES LIKE '$t'");
  $exists = ($r && $r->num_rows > 0);
  echo ($exists ? '✓' : '✗') . " $t\n";
}

echo "\n-- users DDL (if exists) --\n";
$r = $c->query("SHOW CREATE TABLE `$db`.users");
if ($r && $r->num_rows > 0) {
  $row = $r->fetch_assoc();
  echo $row['Create Table'] . "\n";
} else {
  echo "users missing, cannot show DDL\n";
}

echo "\n-- tasks DDL (if exists) --\n";
$r = $c->query("SHOW CREATE TABLE `$db`.tasks");
if ($r && $r->num_rows > 0) {
  $row = $r->fetch_assoc();
  echo $row['Create Table'] . "\n";
} else {
  echo "tasks missing, cannot show DDL\n";
}
?>
