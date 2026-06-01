<?php
/**
 * Storage Adapter Layer
 * Transparent abstraction for XML-first with MySQL fallback
 * Provides unified interface to existing application code
 * 
 * Usage: Replace all direct DB calls with this adapter
 * Example: Instead of db->query(), use $adapter->queryTasks($userId)
 */

require_once 'xml_storage_core.php';

class StorageAdapter {
    private $storage;
    private $mysqlConnection = null;
    private $useMysql = false;
    
    public function __construct() {
        $this->storage = new XMLStorageCore();
        // Auto-detect if MySQL is available
        $this->detectStorage();
    }
    
    /**
     * Detect primary storage layer to use
     */
    private function detectStorage() {
        try {
            $conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, null, 2);
            if ($conn) {
                $this->mysqlConnection = $conn;
                $this->useMysql = true;
            } else {
                $this->useMysql = false;
            }
        } catch (Exception $e) {
            $this->useMysql = false;
        }
    }
    
    /**
     * === USER OPERATIONS ===
     */
    
    /**
     * Register new user (XML-first, sync to MySQL)
     */
    public function registerUser($username, $passwordHash, $role = 'user') {
        return $this->storage->addUser($username, $passwordHash, $role);
    }
    
    /**
     * Get user by username (XML primary, fallback to MySQL)
     */
    public function getUserByUsername($username) {
        // Try XML first (primary)
        $user = $this->storage->getUserByUsername($username);
        
        if (!$user && $this->useMysql) {
            // Fallback to MySQL
            $stmt = $this->mysqlConnection->prepare(
                "SELECT id, username, password_hash, role, created_at FROM users WHERE username = ?"
            );
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $user = $row;
            }
            $stmt->close();
        }
        
        return $user;
    }
    
    /**
     * === TASK OPERATIONS ===
     */
    
    /**
     * Add task (XML-first, sync to MySQL)
     */
    public function addTask($userId, $title, $description = '') {
        return $this->storage->addTask($userId, $title, $description);
    }
    
    /**
     * Get tasks for user (XML primary, pagination)
     */
    public function getTasksByUser($userId, $page = 1, $pageSize = 50) {
        $offset = ($page - 1) * $pageSize;
        
        // Try XML first
        $tasks = $this->storage->getTasksByUser($userId, $pageSize, $offset);
        
        // If empty and MySQL available, try MySQL
        if (empty($tasks) && $this->useMysql) {
            $stmt = $this->mysqlConnection->prepare(
                "SELECT id, user_id, title, description, status, created_at FROM tasks 
                 WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?"
            );
            $stmt->bind_param('iii', $userId, $pageSize, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $tasks[] = $row;
            }
            $stmt->close();
        }
        
        return $tasks;
    }
    
    /**
     * Update task (XML-first)
     */
    public function updateTask($taskId, $userId, $title, $description, $status) {
        return $this->storage->updateTask($taskId, $userId, $title, $description, $status);
    }
    
    /**
     * Delete task (move to archive in XML)
     */
    public function deleteTask($taskId, $userId) {
        return $this->storage->deleteTask($taskId, $userId);
    }
    
    /**
     * Restore task from archive (XML-first)
     */
    public function restoreTask($taskId, $userId) {
        return $this->storage->restoreTask($taskId, $userId);
    }
    
    /**
     * Get archived tasks for user
     */
    public function getArchivedTasks($userId, $page = 1, $pageSize = 50) {
        $offset = ($page - 1) * $pageSize;
        $tasks = [];
        
        try {
            // Load from XML
            $xml = simplexml_load_file('archive_tasks.xml');
            if ($xml) {
                $count = 0;
                foreach ($xml->task as $task) {
                    if ((int)$task->attributes()->user_id == $userId) {
                        if ($count >= $offset && count($tasks) < $pageSize) {
                            $tasks[] = [
                                'id' => (int)$task->attributes()->id,
                                'user_id' => (int)$task->attributes()->user_id,
                                'title' => (string)$task->title,
                                'description' => (string)$task->description,
                                'status' => (string)$task->status,
                                'created_at' => (string)$task->created_at,
                                'archived_at' => (string)$task->archived_at
                            ];
                        }
                        $count++;
                    }
                }
            }
        } catch (Exception $e) {
            // Fall back to MySQL if XML fails
            if ($this->useMysql) {
                $stmt = $this->mysqlConnection->prepare(
                    "SELECT id, user_id, title, description, status, created_at, archived_at 
                     FROM archive_tasks WHERE user_id = ? ORDER BY archived_at DESC LIMIT ? OFFSET ?"
                );
                $stmt->bind_param('iii', $userId, $pageSize, $offset);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $tasks[] = $row;
                }
                $stmt->close();
            }
        }
        
        return $tasks;
    }
    
    /**
     * === INSIGHTS/ANALYTICS ===
     */
    
    /**
     * Get task statistics (compute from XML)
     */
    public function getTaskStats($userId) {
        $stats = [
            'total' => 0,
            'completed' => 0,
            'pending' => 0,
            'archived' => 0
        ];
        
        try {
            // Load tasks from XML
            $tasksXml = @simplexml_load_file('tasks.xml');
            if ($tasksXml) {
                foreach ($tasksXml->task as $task) {
                    if ((int)$task->attributes()->user_id == $userId) {
                        $stats['total']++;
                        if ((string)$task->status === 'completed') {
                            $stats['completed']++;
                        } else {
                            $stats['pending']++;
                        }
                    }
                }
            }
            
            // Load archived from XML
            $archiveXml = @simplexml_load_file('archive_tasks.xml');
            if ($archiveXml) {
                foreach ($archiveXml->task as $task) {
                    if ((int)$task->attributes()->user_id == $userId) {
                        $stats['archived']++;
                    }
                }
            }
        } catch (Exception $e) {
            // Fallback to MySQL
            if ($this->useMysql) {
                $stmt = $this->mysqlConnection->prepare(
                    "SELECT COUNT(*) as total, 
                            SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed,
                            SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending
                     FROM tasks WHERE user_id = ?"
                );
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                
                if ($row) {
                    $stats['total'] = $row['total'] ?: 0;
                    $stats['completed'] = $row['completed'] ?: 0;
                    $stats['pending'] = $row['pending'] ?: 0;
                }
                $stmt->close();
                
                // Get archived count
                $stmt = $this->mysqlConnection->prepare(
                    "SELECT COUNT(*) as archived FROM archive_tasks WHERE user_id = ?"
                );
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stats['archived'] = $row['archived'] ?: 0;
                $stmt->close();
            }
        }
        
        return $stats;
    }
    
    /**
     * === RECOVERY & MAINTENANCE ===
     */
    
    /**
     * Rebuild MySQL from XML snapshot
     */
    public function rebuildMySQL() {
        return $this->storage->rebuildMySQLFromXML();
    }
    
    /**
     * Get storage status (XML/MySQL availability)
     */
    public function getStorageStatus() {
        return [
            'xml_available' => file_exists('users.xml') || file_exists('tasks.xml'),
            'mysql_available' => $this->useMysql,
            'primary_storage' => 'XML',
            'secondary_storage' => $this->useMysql ? 'MySQL' : 'None'
        ];
    }
    
    /**
     * Close connections gracefully
     */
    public function __destruct() {
        if ($this->mysqlConnection) {
            $this->mysqlConnection->close();
        }
    }
}

// Create global instance
$storageAdapter = new StorageAdapter();
?>
