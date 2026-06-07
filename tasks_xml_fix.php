<?php
// Fix tasks.xml beginning-of-file corruption (example: starts with "<<<?xml" or duplicated content).
// This script trims any leading junk before the first real <?xml header and also trims after the last </tasks>.

$path = __DIR__ . '/tasks.xml';
if (!file_exists($path)) {
  echo "Missing tasks.xml\n";
  exit(1);
}

$raw = file_get_contents($path);
if ($raw === false) {
  echo "Failed reading tasks.xml\n";
  exit(1);
}

$start = strpos($raw, '<?xml');
$end = strrpos($raw, '</tasks>');

if ($start === false || $end === false) {
  echo "Could not find <?xml or </tasks> markers\n";
  exit(1);
}

$fixed = substr($raw, $start, $end - $start + strlen('</tasks>'));

file_put_contents($path, $fixed);

echo "tasks.xml fixed (trimmed to single XML document)\n";
?>
