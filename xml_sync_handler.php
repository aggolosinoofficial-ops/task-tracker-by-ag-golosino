<?php
declare(strict_types=1);

/**
 * xml_sync_handler.php
 * Fixed: Variables assigned before bind_param to avoid "pass by reference" errors.
 */

class XMLSyncHandler {
    private mysqli $db;
    // --- ADD THESE MISSING METHODS TO YOUR CLASS ---

    public function syncAllTasksToXML(): bool {
        // Logic to fetch from database and write to tasks.xml
        // Use $this->db for database queries
        return true; 
    }

    public function syncAllUsersToXML(): bool {
        // Logic to fetch from database and write to users.xml
        return true; 
    }

    public function syncAllArchiveTasksToXML(): bool {
        // Logic to fetch from database and write to archive_tasks.xml
        return true; 
    }

    public function syncAllDeletedTasksToXML(): bool {
        // Logic to fetch from database and write to deleted_tasks.xml
        return true; 
    }
    public function __construct(mysqli $dbConnection) {
        $this->db = $dbConnection;
    }

    public function syncTaskToDB(array $data): bool {
        $sql = "INSERT INTO tasks (id, user_id, title, description, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    title = VALUES(title), 
                    description = VALUES(description), 
                    status = VALUES(status)";
        
        $stmt = $this->db->prepare($sql);
        
        // FIX: Extract to variables first
        $id = $data['id'];
        $user_id = $data['user_id'];
        $title = $data['title'];
        $description = $data['description'];
        $status = $data['status'];
        $created_at = $data['created_at'];

        $stmt->bind_param("iissss", $id, $user_id, $title, $description, $status, $created_at);
        
        return $stmt->execute();
    }

    public function rebuildDatabaseFromXML(string $xmlFilePath): bool {
        if (!file_exists($xmlFilePath)) return false;
        
        $xml = simplexml_load_file($xmlFilePath);
        $this->db->begin_transaction();
        
        try {
            $this->db->query("TRUNCATE TABLE tasks");
            $stmt = $this->db->prepare("INSERT INTO tasks (id, user_id, title, description, status, created_at) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($xml->task as $task) {
                // FIX: Extract to variables first
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
?>