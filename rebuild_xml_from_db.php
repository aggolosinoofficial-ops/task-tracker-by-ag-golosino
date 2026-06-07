<?php
declare(strict_types=1);

/**
 * XMLSyncHandler
 * Handles the logic to pull data from MySQL and save it to XML
 */
class XMLSyncHandler
{
    /**
     * Helper to save a DOMDocument to a file
     */
    private function saveXml(DOMDocument $dom, string $filename): bool
    {
        $path = __DIR__ . '/' . $filename;
        return (bool)$dom->save($path);
    }

    public function syncAllTasksToXML(mysqli $conn): bool
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElement('tasks');
        $dom->appendChild($root);

        $res = $conn->query("SELECT * FROM tasks");
        if (!$res) return false;
        
        while ($row = $res->fetch_assoc()) {
            $task = $dom->createElement('task');
            foreach ($row as $key => $val) {
                $task->appendChild($dom->createElement($key, htmlspecialchars((string)$val)));
            }
            $root->appendChild($task);
        }
        return $this->saveXml($dom, 'tasks.xml');
    }

    public function syncAllUsersToXML(mysqli $conn): bool
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElement('users');
        $dom->appendChild($root);

        $res = $conn->query("SELECT * FROM users");
        if (!$res) return false;

        while ($row = $res->fetch_assoc()) {
            $user = $dom->createElement('user');
            foreach ($row as $key => $val) {
                $user->appendChild($dom->createElement($key, htmlspecialchars((string)$val)));
            }
            $root->appendChild($user);
        }
        return $this->saveXml($dom, 'users.xml');
    }

    public function syncAllArchiveTasksToXML(mysqli $conn): bool
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElement('archive');
        $dom->appendChild($root);

        $res = $conn->query("SELECT * FROM archive_tasks");
        if (!$res) return false;
        
        while ($row = $res->fetch_assoc()) {
            $item = $dom->createElement('task');
            foreach ($row as $key => $val) {
                $item->appendChild($dom->createElement($key, htmlspecialchars((string)$val)));
            }
            $root->appendChild($item);
        }
        return $this->saveXml($dom, 'archive_tasks.xml');
    }

    public function syncAllDeletedTasksToXML(mysqli $conn): bool
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElement('deleted');
        $dom->appendChild($root);

        $res = $conn->query("SELECT * FROM deleted_tasks");
        if (!$res) return false;

        while ($row = $res->fetch_assoc()) {
            $item = $dom->createElement('task');
            foreach ($row as $key => $val) {
                $item->appendChild($dom->createElement($key, htmlspecialchars((string)$val)));
            }
            $root->appendChild($item);
        }
        return $this->saveXml($dom, 'deleted_tasks.xml');
    }
}
?>