<?php
/*
 * Strict repair for tasks.xml so that it becomes a single well-formed XML document.
 *
 * Strategy:
 * - Extract the FIRST well-formed <tasks>...</tasks> block.
 * - Extract ALL <task>...</task> blocks from the whole file.
 * - Build a fresh <tasks> root containing those <task> nodes.
 *
 * This removes "junk after document element" problems caused by concatenated XML documents.
 */

$path = __DIR__ . '/tasks.xml';
$raw = @file_get_contents($path);
if ($raw === false) {
  echo "Failed reading tasks.xml\n";
  exit(1);
}

// Capture all <task>...</task> nodes (non-greedy)
if (!preg_match_all('~<task\b[^>]*>.*?</task>~s', $raw, $taskMatches)) {
  echo "No <task> nodes found; writing empty <tasks/>.\n";
  $taskNodes = '';
} else {
  $taskNodes = implode("\n", array_map(fn($m) => $m[0], $taskMatches[0]));
}

$fixed = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<tasks>\n";
if (trim($taskNodes) !== '') {
  $fixed .= $taskNodes . "\n";
}
$fixed .= "</tasks>\n";

if (@file_put_contents($path, $fixed) === false) {
  echo "Failed writing repaired tasks.xml\n";
  exit(1);
}

echo "tasks.xml repaired strictly (rebuilt single <tasks> root).\n";
?>

