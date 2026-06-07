<?php
declare(strict_types=1);

/**
 * xml_sync_handler.php
 * XML-first toggle/update support.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/xml_storage_core.php';

class XMLSyncHandler {
    private ?mysqli $db;
    private XMLStorageCore $xml;

    public function __construct(?mysqli $dbConnection = null) {
        $this->db = $dbConnection;
        $this->xml = new XMLStorageCore();
    }

    public function syncAllTasksToXML(): bool {
        // Placeholder
        return true;
    }

    public function syncAllUsersToXML(): bool {
        // Placeholder
        return true;
    }

    public function syncAllArchiveTasksToXML(): bool {
        // Placeholder
        return true;
    }

    public function syncAllDeletedTasksToXML(): bool {
        // Placeholder
        return true;
    }

    public function syncTaskToDB(array $data): bool {
        if (!$this->db) return false;

        $sql = "INSERT INTO tasks (id, user_id, title, description, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                    title = VALUES(title), 
                    description = VALUES(description), 
                    status = VALUES(status)";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;

        $id = (int)$data['id'];
        $user_id = (int)$data['user_id'];
        $title = (string)$data['title'];
        $description = (string)$data['description'];
        $status = (string)$data['status'];
        $created_at = (string)$data['created_at'];

        $stmt->bind_param("iissss", $id, $user_id, $title, $description, $status, $created_at);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }

    /**
     * Primary storage update: updates task status in tasks.xml.
     * Returns true if at least one task node for (id,user_id) was updated.
     */
    public function updateTaskStatusInXML(int $taskId, int $userId, string $status): bool {
        // tasks.xml is stored in the same directory as xml_storage_core.php
        $tasksFile = __DIR__ . '/tasks.xml';
        if (!file_exists($tasksFile)) return false;

        $xml = simplexml_load_file($tasksFile);

        if (!$xml) return false;

        $updated = 0;

        // Update all matching nodes because tasks.xml may contain duplicates.
        foreach ($xml->task as $task) {
            $id = isset($task->id) ? (int)$task->id : 0;
            $uid = isset($task->user_id) ? (int)$task->user_id : 0;

            if ($id === $taskId && $uid === $userId) {
                if (isset($task->status)) {
                    $task->status[0] = $status;
                } else {
                    $task->addChild('status', $status);
                }
                $updated++;
            }
        }

        if ($updated <= 0) return false;

        // Preserve formatting best-effort.
        $dom = dom_import_simplexml($xml);
        if ($dom) {
            $domDoc = $dom->ownerDocument;
            $domDoc->formatOutput = true;
            return (bool)$domDoc->save($tasksFile);
        }

        // Fallback
        return (bool)file_put_contents($tasksFile, $xml->asXML());
    }

    public function rebuildDatabaseFromXML(string $xmlFilePath): bool {
        if (!file_exists($xmlFilePath)) return false;
        if (!$this->db) return false;

        $xml = simplexml_load_file($xmlFilePath);
        if (!$xml) return false;

        $this->db->begin_transaction();

        try {
            $this->db->query("TRUNCATE TABLE tasks");
            $stmt = $this->db->prepare("INSERT INTO tasks (id, user_id, title, description, status, created_at) VALUES (?, ?, ?, ?, ?, ?)");

            if (!$stmt) throw new Exception('Prepare failed');

            foreach ($xml->task as $task) {
                $id = (int)$task->id;
                $uid = (int)$task->user_id;
                $title = (string)$task->title;
                $desc = (string)$task->description;
                $status = (string)$task->status;
                $created = (string)$task->created_at;

                $stmt->bind_param("iissss", $id, $uid, $title, $desc, $status, $created);
                $stmt->execute();
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
}

function getXMLSyncHandler(): XMLSyncHandler {
    // xml_sync_handler.php is used by toggle_task.php and others.
    // DB is optional; XML-first must still work.
    global $conn;
    $db = (isset($conn) && $conn instanceof mysqli) ? $conn : null;
    return new XMLSyncHandler($db);
}
?>

