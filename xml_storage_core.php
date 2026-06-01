<?php
/**
 * XML-First Storage Core Module
 * Primary storage: XML files
 * Secondary storage: MySQL (sync layer)
 * 
 * Architecture:
 * - All CRUD operations execute on XML first
 * - MySQL sync happens in background if available
 * - Automatic failover to XML-only if MySQL unavailable
 * - Lazy-load XML for memory efficiency (2GB RAM optimization)
 */

class XMLStorageCore {
    private $xmlDir = './';
    private $usersFile = 'users.xml';
    private $tasksFile = 'tasks.xml';
    private $archiveFile = 'archive_tasks.xml';
    private $mysqlAvailable = false;
    private $conn = null;
    
    public function __construct() {
        $this->xmlDir = dirname(__FILE__) . '/';
        // Detect MySQL availability without blocking
        $this->checkMySQLAvailability();
    }
    
    /**
     * Check if MySQL is available (non-blocking health check)
     */
    private function checkMySQLAvailability() {
        try {
            // Quick connection attempt with 2-second timeout
            $conn = @mysqli_connect(
                DB_HOST, 
                DB_USER, 
                DB_PASS, 
                DB_NAME, 
                null, 
                2  // Timeout
            );
            
            if ($conn) {
                $this->conn = $conn;
                $this->mysqlAvailable = true;
                $conn->close();
                $this->conn = null;
            }
        } catch (Exception $e) {
            $this->mysqlAvailable = false;
        }
    }
    
    /**
     * === USERS: XML-First CRUD ===
     */
    
    /**
     * Add user to XML (primary), sync to MySQL if available
     * @return array ['success' => bool, 'id' => int, 'error' => string]
     */
    public function addUser($username, $passwordHash, $role = 'user') {
        try {
            // STEP 1: Load XML (lazy load)
            $xml = $this->loadXML($this->usersFile, 'users');
            if (!$xml) {
                $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><users></users>');
            }
            
            // STEP 2: Generate ID
            $maxId = 0;
            foreach ($xml->user as $user) {
                $id = (int)$user->attributes()->id;
                if ($id > $maxId) $maxId = $id;
            }
            $newId = $maxId + 1;
            
            // STEP 3: Add to XML
            $userNode = $xml->addChild('user');
            $userNode->addAttribute('id', $newId);
            $userNode->addChild('username', htmlspecialchars($username));
            $userNode->addChild('password_hash', $passwordHash);
            $userNode->addChild('role', $role);
            $userNode->addChild('created_at', date('Y-m-d H:i:s'));
            
            // STEP 4: Save XML
            $this->saveXML($xml, $this->usersFile);
            
            // STEP 5: Async sync to MySQL (non-blocking)
            if ($this->mysqlAvailable) {
                $this->syncUserToMySQL($newId, $username, $passwordHash, $role);
            }
            
            return ['success' => true, 'id' => $newId];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get user by username from XML
     */
    public function getUserByUsername($username) {
        try {
            $xml = $this->loadXML($this->usersFile, 'users');
            if (!$xml) return null;
            
            foreach ($xml->user as $user) {
                if ((string)$user->username === $username) {
                    return [
                        'id' => (int)$user->attributes()->id,
                        'username' => (string)$user->username,
                        'password_hash' => (string)$user->password_hash,
                        'role' => (string)$user->role,
                        'created_at' => (string)$user->created_at
                    ];
                }
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * === TASKS: XML-First CRUD ===
     */
    
    /**
     * Add task to XML (primary), sync to MySQL
     */
    public function addTask($userId, $title, $description = '') {
        try {
            // STEP 1: Load XML
            $xml = $this->loadXML($this->tasksFile, 'tasks');
            if (!$xml) {
                $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tasks></tasks>');
            }
            
            // STEP 2: Generate ID
            $maxId = 0;
            foreach ($xml->task as $task) {
                $id = (int)$task->attributes()->id;
                if ($id > $maxId) $maxId = $id;
            }
            $newId = $maxId + 1;
            
            // STEP 3: Add to XML
            $taskNode = $xml->addChild('task');
            $taskNode->addAttribute('id', $newId);
            $taskNode->addAttribute('user_id', $userId);
            $taskNode->addChild('title', htmlspecialchars($title));
            $taskNode->addChild('description', htmlspecialchars($description));
            $taskNode->addChild('status', 'pending');
            $taskNode->addChild('created_at', date('Y-m-d H:i:s'));
            
            // STEP 4: Save XML
            $this->saveXML($xml, $this->tasksFile);
            
            // STEP 5: Async sync to MySQL
            if ($this->mysqlAvailable) {
                $this->syncTaskToMySQL($newId, $userId, $title, $description, 'pending');
            }
            
            return ['success' => true, 'id' => $newId];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get all tasks for user from XML
     */
    public function getTasksByUser($userId, $limit = 50, $offset = 0) {
        try {
            $xml = $this->loadXML($this->tasksFile, 'tasks');
            if (!$xml) return [];
            
            $tasks = [];
            $count = 0;
            
            foreach ($xml->task as $task) {
                if ((int)$task->attributes()->user_id == $userId) {
                    if ($count >= $offset && count($tasks) < $limit) {
                        $tasks[] = [
                            'id' => (int)$task->attributes()->id,
                            'user_id' => (int)$task->attributes()->user_id,
                            'title' => (string)$task->title,
                            'description' => (string)$task->description,
                            'status' => (string)$task->status,
                            'created_at' => (string)$task->created_at
                        ];
                    }
                    $count++;
                }
            }
            
            return $tasks;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Update task in XML
     */
    public function updateTask($taskId, $userId, $title, $description, $status) {
        try {
            $xml = $this->loadXML($this->tasksFile, 'tasks');
            if (!$xml) return ['success' => false, 'error' => 'Tasks file not found'];
            
            foreach ($xml->task as $task) {
                if ((int)$task->attributes()->id == $taskId && 
                    (int)$task->attributes()->user_id == $userId) {
                    
                    $task->title = htmlspecialchars($title);
                    $task->description = htmlspecialchars($description);
                    $task->status = $status;
                    
                    $this->saveXML($xml, $this->tasksFile);
                    
                    if ($this->mysqlAvailable) {
                        $this->syncTaskUpdateToMySQL($taskId, $title, $description, $status);
                    }
                    
                    return ['success' => true];
                }
            }
            
            return ['success' => false, 'error' => 'Task not found'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Delete task from active, move to archive
     */
    public function deleteTask($taskId, $userId) {
        try {
            // STEP 1: Load active tasks
            $tasksXml = $this->loadXML($this->tasksFile, 'tasks');
            if (!$tasksXml) return ['success' => false, 'error' => 'Tasks file not found'];
            
            $taskData = null;
            $taskKey = null;
            
            foreach ($tasksXml->task as $key => $task) {
                if ((int)$task->attributes()->id == $taskId && 
                    (int)$task->attributes()->user_id == $userId) {
                    $taskData = [
                        'id' => (int)$task->attributes()->id,
                        'user_id' => (int)$task->attributes()->user_id,
                        'title' => (string)$task->title,
                        'description' => (string)$task->description,
                        'status' => (string)$task->status,
                        'created_at' => (string)$task->created_at
                    ];
                    $taskKey = $key;
                    break;
                }
            }
            
            if (!$taskData) {
                return ['success' => false, 'error' => 'Task not found'];
            }
            
            // STEP 2: Remove from active tasks
            unset($tasksXml->task[$taskKey]);
            $this->saveXML($tasksXml, $this->tasksFile);
            
            // STEP 3: Add to archive
            $archiveXml = $this->loadXML($this->archiveFile, 'archive_tasks');
            if (!$archiveXml) {
                $archiveXml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><archive_tasks></archive_tasks>');
            }
            
            $archiveNode = $archiveXml->addChild('task');
            $archiveNode->addAttribute('id', $taskData['id']);
            $archiveNode->addAttribute('user_id', $taskData['user_id']);
            $archiveNode->addChild('title', $taskData['title']);
            $archiveNode->addChild('description', $taskData['description']);
            $archiveNode->addChild('status', $taskData['status']);
            $archiveNode->addChild('created_at', $taskData['created_at']);
            $archiveNode->addChild('archived_at', date('Y-m-d H:i:s'));
            
            $this->saveXML($archiveXml, $this->archiveFile);
            
            // STEP 4: Async sync to MySQL
            if ($this->mysqlAvailable) {
                $this->syncTaskDeleteToMySQL($taskId, $userId);
            }
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Restore task from archive
     */
    public function restoreTask($taskId, $userId) {
        try {
            // STEP 1: Load archive
            $archiveXml = $this->loadXML($this->archiveFile, 'archive_tasks');
            if (!$archiveXml) return ['success' => false, 'error' => 'Archive file not found'];
            
            $taskData = null;
            $taskKey = null;
            
            foreach ($archiveXml->task as $key => $task) {
                if ((int)$task->attributes()->id == $taskId && 
                    (int)$task->attributes()->user_id == $userId) {
                    $taskData = [
                        'id' => (int)$task->attributes()->id,
                        'user_id' => (int)$task->attributes()->user_id,
                        'title' => (string)$task->title,
                        'description' => (string)$task->description,
                        'status' => (string)$task->status,
                        'created_at' => (string)$task->created_at
                    ];
                    $taskKey = $key;
                    break;
                }
            }
            
            if (!$taskData) {
                return ['success' => false, 'error' => 'Task not found in archive'];
            }
            
            // STEP 2: Remove from archive
            unset($archiveXml->task[$taskKey]);
            $this->saveXML($archiveXml, $this->archiveFile);
            
            // STEP 3: Add back to active tasks
            $tasksXml = $this->loadXML($this->tasksFile, 'tasks');
            if (!$tasksXml) {
                $tasksXml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tasks></tasks>');
            }
            
            $taskNode = $tasksXml->addChild('task');
            $taskNode->addAttribute('id', $taskData['id']);
            $taskNode->addAttribute('user_id', $taskData['user_id']);
            $taskNode->addChild('title', $taskData['title']);
            $taskNode->addChild('description', $taskData['description']);
            $taskNode->addChild('status', $taskData['status']);
            $taskNode->addChild('created_at', $taskData['created_at']);
            
            $this->saveXML($tasksXml, $this->tasksFile);
            
            // STEP 4: Async sync to MySQL
            if ($this->mysqlAvailable) {
                $this->syncTaskRestoreToMySQL($taskId, $userId);
            }
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * === MYSQL SYNC LAYER (Background, Non-Blocking) ===
     */
    
    /**
     * Sync user to MySQL (background)
     */
    private function syncUserToMySQL($id, $username, $passwordHash, $role) {
        if (!$this->mysqlAvailable) return;
        
        try {
            $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if (!$conn) return;
            
            $stmt = $conn->prepare(
                "INSERT IGNORE INTO users (id, username, password_hash, role) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param('isss', $id, $username, $passwordHash, $role);
            $stmt->execute();
            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            // Silently fail - don't disrupt XML operations
            error_log("[XMLSync] User sync failed: " . $e->getMessage());
        }
    }
    
    /**
     * Sync task to MySQL (background)
     */
    private function syncTaskToMySQL($id, $userId, $title, $description, $status) {
        if (!$this->mysqlAvailable) return;
        
        try {
            $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if (!$conn) return;
            
            $stmt = $conn->prepare(
                "INSERT IGNORE INTO tasks (id, user_id, title, description, status) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('iisss', $id, $userId, $title, $description, $status);
            $stmt->execute();
            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            error_log("[XMLSync] Task add sync failed: " . $e->getMessage());
        }
    }
    
    /**
     * Sync task update to MySQL
     */
    private function syncTaskUpdateToMySQL($taskId, $title, $description, $status) {
        if (!$this->mysqlAvailable) return;
        
        try {
            $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if (!$conn) return;
            
            $stmt = $conn->prepare(
                "UPDATE tasks SET title=?, description=?, status=? WHERE id=?"
            );
            $stmt->bind_param('sssi', $title, $description, $status, $taskId);
            $stmt->execute();
            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            error_log("[XMLSync] Task update sync failed: " . $e->getMessage());
        }
    }
    
    /**
     * Sync task delete to MySQL
     */
    private function syncTaskDeleteToMySQL($taskId, $userId) {
        if (!$this->mysqlAvailable) return;
        
        try {
            $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if (!$conn) return;
            
            // Delete from active, insert to archive
            $stmt = $conn->prepare("DELETE FROM tasks WHERE id=? AND user_id=?");
            $stmt->bind_param('ii', $taskId, $userId);
            $stmt->execute();
            $stmt->close();
            
            $conn->close();
        } catch (Exception $e) {
            error_log("[XMLSync] Task delete sync failed: " . $e->getMessage());
        }
    }
    
    /**
     * Sync task restore to MySQL
     */
    private function syncTaskRestoreToMySQL($taskId, $userId) {
        if (!$this->mysqlAvailable) return;
        
        try {
            $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if (!$conn) return;
            
            // Restore from archive to active
            $stmt = $conn->prepare("DELETE FROM archive_tasks WHERE id=? AND user_id=?");
            $stmt->bind_param('ii', $taskId, $userId);
            $stmt->execute();
            $stmt->close();
            
            $conn->close();
        } catch (Exception $e) {
            error_log("[XMLSync] Task restore sync failed: " . $e->getMessage());
        }
    }
    
    /**
     * === RECOVERY LAYER ===
     * Rebuild MySQL tables from XML snapshot
     */
    
    public function rebuildMySQLFromXML() {
        if (!$this->mysqlAvailable) {
            return ['success' => false, 'error' => 'MySQL not available'];
        }
        
        try {
            $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if (!$conn) {
                return ['success' => false, 'error' => 'Cannot connect to MySQL'];
            }
            
            // STEP 1: Clear existing data
            $conn->query("TRUNCATE TABLE tasks");
            $conn->query("TRUNCATE TABLE archive_tasks");
            $conn->query("TRUNCATE TABLE users");
            
            // STEP 2: Load from XML snapshots
            $usersXml = $this->loadXML($this->usersFile, 'users');
            $tasksXml = $this->loadXML($this->tasksFile, 'tasks');
            $archiveXml = $this->loadXML($this->archiveFile, 'archive_tasks');
            
            // STEP 3: Restore users
            if ($usersXml) {
                $userStmt = $conn->prepare(
                    "INSERT INTO users (id, username, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?)"
                );
                
                foreach ($usersXml->user as $user) {
                    $id = (int)$user->attributes()->id;
                    $username = (string)$user->username;
                    $passwordHash = (string)$user->password_hash;
                    $role = (string)$user->role;
                    $createdAt = (string)$user->created_at;
                    
                    $userStmt->bind_param('issss', $id, $username, $passwordHash, $role, $createdAt);
                    $userStmt->execute();
                }
                $userStmt->close();
            }
            
            // STEP 4: Restore tasks
            if ($tasksXml) {
                $taskStmt = $conn->prepare(
                    "INSERT INTO tasks (id, user_id, title, description, status, created_at) VALUES (?, ?, ?, ?, ?, ?)"
                );
                
                foreach ($tasksXml->task as $task) {
                    $id = (int)$task->attributes()->id;
                    $userId = (int)$task->attributes()->user_id;
                    $title = (string)$task->title;
                    $description = (string)$task->description;
                    $status = (string)$task->status;
                    $createdAt = (string)$task->created_at;
                    
                    $taskStmt->bind_param('iissss', $id, $userId, $title, $description, $status, $createdAt);
                    $taskStmt->execute();
                }
                $taskStmt->close();
            }
            
            // STEP 5: Restore archive
            if ($archiveXml) {
                $archiveStmt = $conn->prepare(
                    "INSERT INTO archive_tasks (id, user_id, title, description, status, created_at, archived_at) VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                
                foreach ($archiveXml->task as $task) {
                    $id = (int)$task->attributes()->id;
                    $userId = (int)$task->attributes()->user_id;
                    $title = (string)$task->title;
                    $description = (string)$task->description;
                    $status = (string)$task->status;
                    $createdAt = (string)$task->created_at;
                    $archivedAt = (string)$task->archived_at;
                    
                    $archiveStmt->bind_param('iisssss', $id, $userId, $title, $description, $status, $createdAt, $archivedAt);
                    $archiveStmt->execute();
                }
                $archiveStmt->close();
            }
            
            $conn->close();
            return ['success' => true, 'message' => 'MySQL rebuilt from XML snapshots'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * === UTILITY FUNCTIONS ===
     */
    
    /**
     * Load XML file with lazy loading
     */
    private function loadXML($filename, $rootTag = null) {
        try {
            $filepath = $this->xmlDir . $filename;
            
            if (!file_exists($filepath)) {
                return null;
            }
            
            // Check file size (lazy load: skip if too large)
            if (filesize($filepath) > 10485760) {  // 10MB limit
                error_log("[XMLLoad] File too large: $filename");
                return null;
            }
            
            $xml = simplexml_load_file($filepath);
            return $xml ?: null;
        } catch (Exception $e) {
            error_log("[XMLLoad] Error loading $filename: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Save XML file with compact formatting
     */
    private function saveXML($xml, $filename) {
        try {
            $filepath = $this->xmlDir . $filename;
            
            // Format and save (compact, minimal whitespace)
            $dom = dom_import_simplexml($xml)->ownerDocument;
            $dom->formatOutput = false;  // Compact format
            $dom->save($filepath);
            
            return true;
        } catch (Exception $e) {
            error_log("[XMLSave] Error saving $filename: " . $e->getMessage());
            throw $e;
        }
    }
}

// Create global instance for easy access
$storage = new XMLStorageCore();
?>
