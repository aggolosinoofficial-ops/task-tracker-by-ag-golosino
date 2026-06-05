<?php
/**
 * XML Synchronization Handler
 * Manages automatic synchronization between MySQL database and XML files
 * Handles both tasks and users synchronization
 */

class XMLSyncHandler
{
    // File paths for XML task storage
    private string $tasksXmlPath;
    private string $tasksXsdPath;
    // File paths for XML user storage
    private string $usersXmlPath;
    private string $usersXsdPath;
    // File paths for archived tasks storage
    private string $archiveTasksXmlPath;
    private string $archiveTasksXsdPath;
    // File paths for deleted task logs
    private string $deletedTasksXmlPath;
    private string $deletedTasksXsdPath;
    // DOM documents for manipulation and validation
    private ?DOMDocument $tasksDom;
    private ?DOMDocument $usersDom;
    private ?DOMDocument $archiveTasksDom;
    private ?DOMDocument $deletedTasksDom;

    public function __construct()
    {
        // Initialize paths for active tasks and users
        $this->tasksXmlPath = __DIR__ . '/tasks.xml';
        $this->tasksXsdPath = __DIR__ . '/tasks.xsd';
        // Initialize paths for users
        $this->usersXmlPath = __DIR__ . '/users.xml';
        $this->usersXsdPath = __DIR__ . '/users.xsd';
        // Initialize paths for archived tasks
        $this->archiveTasksXmlPath = __DIR__ . '/archive_tasks.xml';
        $this->archiveTasksXsdPath = __DIR__ . '/archive_tasks.xsd';
        // Initialize paths for deleted tasks logs
        $this->deletedTasksXmlPath = __DIR__ . '/deleted_tasks.xml';
        $this->deletedTasksXsdPath = __DIR__ . '/deleted_tasks.xsd';
    }

    /**
     * Sync new task to XML - Creates a backup copy of newly added task
     * Called after task is created in MySQL to keep XML file synchronized
     * 
     * WHAT IT DOES:
     * 1. Loads the tasks.xml file (or creates it if it doesn't exist)
     * 2. Creates a new <task> element with all task details
     * 3. Adds the new task element to the XML file
     * 4. Validates the XML against the schema (tasks.xsd)
     * 5. Saves the updated XML file to disk
     * 
     * WHY IT MATTERS:
     * - Provides automatic backup of all tasks
     * - If MySQL database is lost, data can be recovered from XML
     * - Dual-storage ensures data safety
     * 
     * @param int $taskId - Unique task identifier
     * @param int $userId - Owner of the task (for data isolation)
     * @param string $title - Task title/name
     * @param string $description - Task description/details
     * @param string $status - Current status (pending/completed/etc)
     * @param string $createdAt - Timestamp when task was created
     * @return boolean - true if sync successful, false on error
     */
    public function syncTaskToXML($taskId, $userId, $title, $description, $status, $createdAt, $category = 'personal')
    {
        try {
            // Step 1: Load the existing tasks.xml file into memory
            $this->loadTasksXML();
            
            // Step 2: Get the root <tasks> element
            $root = $this->tasksDom->documentElement;
            
            // Step 3: Create a new <task> element (this is one task entry)
            $taskElement = $this->tasksDom->createElement('task');
            
            // Step 4: Add all task data as child elements
            $this->addXMLElement($taskElement, 'id', $taskId);                           // <id>123</id>
            $this->addXMLElement($taskElement, 'user_id', $userId);                     // <user_id>456</user_id>
            $this->addXMLElement($taskElement, 'title', $title);                        // <title>My Task</title>
            $this->addXMLElement($taskElement, 'description', $description);            // <description>Details here</description>
            $this->addXMLElement($taskElement, 'category', $category);                  // <category>personal|technical</category>
            $this->addXMLElement($taskElement, 'status', $status);                      // <status>pending</status>
            $this->addXMLElement($taskElement, 'created_at', $this->normalizeDateTime($createdAt));
            
            // Step 5: Attach this new task to the root element
            $root->appendChild($taskElement);
            
            // Step 6: Validate the XML structure matches tasks.xsd schema before saving
            if ($this->validateTasksXML()) {
                // If valid, save to file and return success
                return $this->tasksDom->save($this->tasksXmlPath);
            }
            
            return false;
        } catch (Exception $e) {
            // Log any errors for debugging
            error_log('[XMLSync] Error syncing task to XML: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync task update to XML
     * Called after task is updated in MySQL
     */
    public function syncTaskUpdateToXML($taskId, $updates)
    {
        try {
            $this->loadTasksXML();
            
            // Use for loop to properly get DOMElement instances
            $tasks = $this->tasksDom->getElementsByTagName('task');
            for ($i = 0; $i < $tasks->length; $i++) {
                // Get each task element using item() to ensure proper type
                $taskElement = $tasks->item($i);
                // Find the id element within this task
                $idElement = $taskElement->getElementsByTagName('id')->item(0);
                if ($idElement && intval($idElement->nodeValue) === intval($taskId)) {
                    foreach ($updates as $key => $value) {
                        // Find all elements matching the key name
                        $elements = $taskElement->getElementsByTagName($key);
                        if ($elements->length > 0) {
                            // Get the element to update
                            $element = $elements->item(0);
                            // Clear existing child nodes
                            while ($element->firstChild) {
                                $element->removeChild($element->firstChild);
                            }
                            // Add the new text content with proper escaping
                            $element->appendChild($this->tasksDom->createTextNode($value));
                        }
                    }
                    break;
                }
            }
            
            if ($this->validateTasksXML()) {
                return $this->tasksDom->save($this->tasksXmlPath);
            }
            
            return false;
        } catch (Exception $e) {
            error_log('[XMLSync] Error updating task in XML: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync task deletion to XML (archive)
     * Called after task is deleted/archived in MySQL
     */
    public function syncTaskDeleteToXML($taskId)
    {
        try {
            $this->loadTasksXML();
            
            $tasks = $this->tasksDom->getElementsByTagName('task');
            for ($i = $tasks->length - 1; $i >= 0; $i--) {
                $taskElement = $tasks->item($i);
                $idElement = $taskElement->getElementsByTagName('id')->item(0);
                
                if ($idElement && intval($idElement->nodeValue) === intval($taskId)) {
                    $taskElement->parentNode->removeChild($taskElement);
                    break;
                }
            }
            
            return $this->tasksDom->save($this->tasksXmlPath);
        } catch (Exception $e) {
            error_log('[XMLSync] Error deleting task from XML: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync archived task to XML - Creates backup of archived task
     * Called when a task is moved to archive_tasks table
     * 
     * WHAT IT DOES:
     * 1. Loads the archive_tasks.xml file (or creates if missing)
     * 2. Creates a new <archive_task> element with all archived task details
     * 3. Adds the task to the archive XML along with archive timestamp
     * 4. Validates the XML against archive_tasks.xsd schema
     * 5. Saves the updated archive XML file
     * 
     * WHY IT MATTERS:
     * - Provides backup of archived/deleted tasks for recovery
     * - Maintains complete history of all user actions
     * - Dual-storage for archive ensures no data loss
     * 
     * @param int $archiveId - Unique archive record ID from archive_tasks table
     * @param int $userId - Owner of the archived task
     * @param string $title - Task title
     * @param string $description - Task description
     * @param string $status - Status when archived (pending/completed/etc)
     * @param string $createdAt - When original task was created
     * @param string $archivedAt - When task was archived/deleted
     * @return boolean - true if sync successful, false on error
     */
    public function syncArchiveTaskToXML($archiveId, $userId, $title, $description, $status, $createdAt, $archivedAt)
    {
        try {
            // Step 1: Load the existing archive_tasks.xml file into memory
            $this->loadArchiveTasksXML();
            
            // Step 2: Get the root <archive_tasks> element
            $root = $this->archiveTasksDom->documentElement;
            
            // Step 3: Create a new <archive_task> element for this archived entry
            $archiveTaskElement = $this->archiveTasksDom->createElement('archive_task');
            
            // Step 4: Add all archived task data as child elements
            $this->addXMLElement($archiveTaskElement, 'id', $archiveId);                     // <id>123</id>
            $this->addXMLElement($archiveTaskElement, 'user_id', $userId);                 // <user_id>456</user_id>
            $this->addXMLElement($archiveTaskElement, 'title', $title);                    // <title>My Task</title>
            $this->addXMLElement($archiveTaskElement, 'description', $description);        // <description>Details</description>
            $this->addXMLElement($archiveTaskElement, 'status', $status);                  // <status>completed</status>
            $this->addXMLElement($archiveTaskElement, 'created_at', $this->normalizeDateTime($createdAt));           // <created_at>2024-01-01T12:00:00</created_at>
            $this->addXMLElement($archiveTaskElement, 'archived_at', $this->normalizeDateTime($archivedAt));         // <archived_at>2024-01-15T10:30:00</archived_at>
            
            // Step 5: Attach this archived task to the root element
            $root->appendChild($archiveTaskElement);
            
            // Step 6: Validate the XML structure matches archive_tasks.xsd schema before saving
            if ($this->validateArchiveTasksXML()) {
                // If valid, save to file and return success
                return $this->archiveTasksDom->save($this->archiveTasksXmlPath);
            }
            
            return false;
        } catch (Exception $e) {
            // Log any errors for debugging
            error_log('[XMLSync] Error syncing archived task to XML: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync deleted task to XML - Creates a separate log of deleted tasks
     */
    public function syncDeletedTaskToXML($taskId, $userId, $title, $description, $status, $createdAt, $deletedAt)
    {
        try {
            $this->loadDeletedTasksXML();
            $root = $this->deletedTasksDom->documentElement;
            $deletedTaskElement = $this->deletedTasksDom->createElement('deleted_task');

            $this->addXMLElement($deletedTaskElement, 'id', $taskId);
            $this->addXMLElement($deletedTaskElement, 'user_id', $userId);
            $this->addXMLElement($deletedTaskElement, 'title', $title);
            $this->addXMLElement($deletedTaskElement, 'description', $description);
            $this->addXMLElement($deletedTaskElement, 'status', $status);
            $this->addXMLElement($deletedTaskElement, 'created_at', $this->normalizeDateTime($createdAt));
            $this->addXMLElement($deletedTaskElement, 'deleted_at', $this->normalizeDateTime($deletedAt));

            $root->appendChild($deletedTaskElement);
            if ($this->validateDeletedTasksXML()) {
                return $this->deletedTasksDom->save($this->deletedTasksXmlPath);
            }
            return false;
        } catch (Exception $e) {
            error_log('[XMLSync] Error syncing deleted task to XML: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync task restore from archive to XML - Removes from archive backup
     * Called when a task is restored from archive_tasks table back to tasks table
     * 
     * WHAT IT DOES:
     * 1. Loads the archive_tasks.xml file
     * 2. Finds and removes the specified archived task entry by ID
     * 3. Validates the updated XML against schema
     * 4. Saves the modified archive XML file
     * 
     * WHY IT MATTERS:
     * - Keeps archive XML in sync with archive_tasks MySQL table
     * - Maintains accurate history after task restoration
     * - Prevents duplicate entries in archive
     * 
     * @param int $archiveId - Archive record ID to restore/remove
     * @return boolean - true if sync successful, false on error
     */
    public function syncRemoveArchiveTaskFromXML($archiveId)
    {
        try {
            // Step 1: Load the existing archive_tasks.xml file
            $this->loadArchiveTasksXML();
            
            // Step 2: Get all <archive_task> elements
            $archiveTasks = $this->archiveTasksDom->getElementsByTagName('archive_task');
            
            // Step 3: Iterate backwards to safely remove elements while iterating
            for ($i = $archiveTasks->length - 1; $i >= 0; $i--) {
                $archiveTaskElement = $archiveTasks->item($i);
                $idElement = $archiveTaskElement->getElementsByTagName('id')->item(0);
                
                if ($idElement && intval($idElement->nodeValue) === intval($archiveId)) {
                    $archiveTaskElement->parentNode->removeChild($archiveTaskElement);
                    break;
                }
            }
            
            // Step 4: Validate and save the updated archive XML
            if ($this->validateArchiveTasksXML()) {
                return $this->archiveTasksDom->save($this->archiveTasksXmlPath);
            }
            
            return false;
        } catch (Exception $e) {
            error_log('[XMLSync] Error removing archived task from XML: ' . $e->getMessage());
            return false;
        }
    }

    public function syncRestoreTaskFromArchiveXML($archiveId)
    {
        return $this->syncRemoveArchiveTaskFromXML($archiveId);
    }

    public function generateNextTaskId($userId = null) {
        try {
            $this->loadTasksXML();
            $maxId = 0;
            foreach ($this->tasksDom->getElementsByTagName('task') as $taskElement) {
                // Try to get id from child element first
                $idElements = $taskElement->getElementsByTagName('id');
                if ($idElements->length > 0) {
                    $id = intval($idElements->item(0)->nodeValue);
                } else {
                    // Fallback to attribute
                    $id = intval($taskElement->getAttribute('id') ?? 0);
                }
                if ($id > $maxId) $maxId = $id;
            }
            return $maxId + 1;
        } catch (Exception $e) {
            error_log('[XMLSync] Error generating task ID: ' . $e->getMessage());
            return 1;
        }
    }

    public function getTasksFromXML($userId, $limit = null, $offset = 0) {
        try {
            $this->loadTasksXML();
            $tasks = [];
            $count = 0;
            $returned = 0;
            foreach ($this->tasksDom->getElementsByTagName('task') as $taskElement) {
                // tasks.xml stores user_id as a child element, not an attribute
                $userIdEl = $taskElement->getElementsByTagName('user_id')->item(0);
                $userIdVal = $userIdEl ? intval($userIdEl->nodeValue) : null;

                if ($userIdVal === null || $userIdVal !== intval($userId)) continue;

                if ($count < $offset) {
                    $count++;
                    continue;
                }
                if ($limit && $returned >= $limit) break;

                $task = [];
                foreach ($taskElement->childNodes as $node) {
                    if ($node->nodeType === XML_ELEMENT_NODE) {
                        $task[$node->nodeName] = $node->nodeValue;
                    }
                }

                // id is also stored as child element
                if (isset($task['id'])) {
                    $task['id'] = intval($task['id']);
                }

                $tasks[] = $task;
                $returned++;
                $count++;
            }
            return $tasks;
        } catch (Exception $e) {
            error_log('[XMLSync] Error reading tasks: ' . $e->getMessage());
            return false;
        }
    }


    public function getTaskFromXML($taskId, $userId) {
        try {
            $this->loadTasksXML();
            foreach ($this->tasksDom->getElementsByTagName('task') as $taskElement) {
                $elementId = intval($taskElement->getAttribute('id') ?? 0);
                $elementUserId = intval($taskElement->getAttribute('user_id') ?? 0);
                if ($elementId === intval($taskId) && $elementUserId === intval($userId)) {
                    $task = ['id' => $elementId, 'user_id' => $elementUserId];
                    foreach ($taskElement->childNodes as $node) {
                        if ($node->nodeType === XML_ELEMENT_NODE) {
                            $task[$node->nodeName] = $node->nodeValue;
                        }
                    }
                    return $task;
                }
            }
            return false;
        } catch (Exception $e) {
            error_log('[XMLSync] Error reading task: ' . $e->getMessage());
            return false;
        }
    }

    public function updateTaskInXML($taskId, $userId, $updates) {
        try {
            $this->loadTasksXML();
            $updated = false;
            foreach ($this->tasksDom->getElementsByTagName('task') as $taskElement) {
                $elementId = intval($taskElement->getAttribute('id') ?? 0);
                $elementUserId = intval($taskElement->getAttribute('user_id') ?? 0);
                if ($elementId === intval($taskId) && $elementUserId === intval($userId)) {
                    foreach ($updates as $key => $value) {
                        $elements = $taskElement->getElementsByTagName($key);
                        if ($elements->length > 0) {
                            $elements->item(0)->nodeValue = htmlspecialchars($value);
                        } else {
                            $this->addXMLElement($taskElement, $key, $value, $this->tasksDom);
                        }
                    }
                    $updated = true;
                    break;
                }
            }
            if ($updated && $this->validateTasksXML()) {
                return $this->tasksDom->save($this->tasksXmlPath);
            }
            return $updated;
        } catch (Exception $e) {
            error_log('[XMLSync] Error updating task: ' . $e->getMessage());
            return false;
        }
    }

    public function updateTaskStatusInXML($taskId, $userId, $status) {
        try {
            $this->loadTasksXML();
            foreach ($this->tasksDom->getElementsByTagName('task') as $taskElement) {
                $elementId = intval($taskElement->getAttribute('id') ?? 0);
                $elementUserId = intval($taskElement->getAttribute('user_id') ?? 0);
                if ($elementId === intval($taskId) && $elementUserId === intval($userId)) {
                    $statusElements = $taskElement->getElementsByTagName('status');
                    if ($statusElements->length > 0) {
                        $statusElements->item(0)->nodeValue = $status;
                    } else {
                        $this->addXMLElement($taskElement, 'status', $status, $this->tasksDom);
                    }
                    if ($this->validateTasksXML()) {
                        return $this->tasksDom->save($this->tasksXmlPath);
                    }
                    return true;
                }
            }
            return false;
        } catch (Exception $e) {
            error_log('[XMLSync] Error updating status: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteTaskFromXML($taskId, $userId) {
        try {
            $this->loadTasksXML();
            $found = false;
            $tasks = $this->tasksDom->getElementsByTagName('task');
            for ($i = $tasks->length - 1; $i >= 0; $i--) {
                $taskElement = $tasks->item($i);
                $elementId = intval($taskElement->getAttribute('id') ?? 0);
                $elementUserId = intval($taskElement->getAttribute('user_id') ?? 0);
                if ($elementId === intval($taskId) && $elementUserId === intval($userId)) {
                    $taskElement->parentNode->removeChild($taskElement);
                    $found = true;
                    break;
                }
            }
            if ($found && $this->validateTasksXML()) {
                return $this->tasksDom->save($this->tasksXmlPath);
            }
            return $found;
        } catch (Exception $e) {
            error_log('[XMLSync] Error deleting task: ' . $e->getMessage());
            return false;
        }
    }

    public function archiveTaskFromXML($taskId, $userId) {
        try {
            $task = $this->getTaskFromXML($taskId, $userId);
            if (!$task) return false;
            $archiveSuccess = $this->syncArchiveTaskToXML(
                $task['id'],
                $task['user_id'],
                $task['title'] ?? '',
                $task['description'] ?? '',
                $task['status'] ?? 'pending',
                $task['created_at'] ?? date('Y-m-d\TH:i:s'),
                date('Y-m-d\TH:i:s')
            );
            if (!$archiveSuccess) return false;
            return $this->deleteTaskFromXML($taskId, $userId);
        } catch (Exception $e) {
            error_log('[XMLSync] Error archiving task: ' . $e->getMessage());
            return false;
        }
    }

    public function getArchivedTaskFromXML($archiveId, $userId) {
        try {
            $this->loadArchiveTasksXML();
            foreach ($this->archiveTasksDom->getElementsByTagName('archive_task') as $taskElement) {
                $elementId = intval($taskElement->getAttribute('id') ?? 0);
                $elementUserId = intval($taskElement->getAttribute('user_id') ?? 0);
                if ($elementId === intval($archiveId) && $elementUserId === intval($userId)) {
                    $task = ['id' => $elementId, 'user_id' => $elementUserId];
                    foreach ($taskElement->childNodes as $node) {
                        if ($node->nodeType === XML_ELEMENT_NODE) {
                            $task[$node->nodeName] = $node->nodeValue;
                        }
                    }
                    return $task;
                }
            }
            return false;
        } catch (Exception $e) {
            error_log('[XMLSync] Error reading archived task: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync new user to XML
     * Called after user is created in MySQL
     */
    public function syncUserToXML($userId, $username, $passwordHash, $role, $createdAt)
    {
        try {
            $this->loadUsersXML();
            
            $root = $this->usersDom->documentElement;
            $userElement = $this->usersDom->createElement('user');
            
            $this->addXMLElement($userElement, 'id', $userId);
            $this->addXMLElement($userElement, 'username', $username);
            $this->addXMLElement($userElement, 'password_hash', $passwordHash);
            $this->addXMLElement($userElement, 'role', $role);
            $this->addXMLElement($userElement, 'created_at', $this->normalizeDateTime($createdAt));
            
            $root->appendChild($userElement);
            
            // Validate before saving
            if ($this->validateUsersXML()) {
                return $this->usersDom->save($this->usersXmlPath);
            }
            
            return false;
        } catch (Exception $e) {
            error_log('[XMLSync] Error syncing user to XML: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync all tasks from MySQL to XML (full rebuild)
     * Use this for data recovery or rebuilding XML from DB
     */
    public function syncAllTasksToXML($conn)
    {
        try {
            // Create new DOM for tasks
            $this->tasksDom = new DOMDocument('1.0', 'UTF-8');
            $root = $this->tasksDom->createElement('tasks');
            $this->tasksDom->appendChild($root);
            
            // Get all tasks from database
            $result = $conn->query("SELECT id, user_id, title, description, status, created_at FROM test.tasks ORDER BY id");
            
            if (!$result) {
                error_log('[XMLSync] Error querying tasks: ' . $conn->error);
                return false;
            }
            
            while ($row = $result->fetch_assoc()) {
                $taskElement = $this->tasksDom->createElement('task');
                
                $this->addXMLElement($taskElement, 'id', $row['id']);
                $this->addXMLElement($taskElement, 'user_id', $row['user_id']);
                $this->addXMLElement($taskElement, 'title', $row['title']);
                $this->addXMLElement($taskElement, 'description', $row['description']);
                $this->addXMLElement($taskElement, 'status', $row['status']);
                $this->addXMLElement($taskElement, 'created_at', $this->normalizeDateTime($row['created_at']));
                
                $root->appendChild($taskElement);
            }
            
            if ($this->validateTasksXML()) {
                $this->tasksDom->formatOutput = true;
                return $this->tasksDom->save($this->tasksXmlPath);
            }
            
            return false;
        } catch (Exception $e) {
            error_log('[XMLSync] Error syncing all tasks: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync all users from MySQL to XML (full rebuild)
     * Use this for data recovery or rebuilding XML from DB
     */
    public function syncAllUsersToXML($conn)
    {
        try {
            // Create new DOM for users
            $this->usersDom = new DOMDocument('1.0', 'UTF-8');
            $root = $this->usersDom->createElement('users');
            $this->usersDom->appendChild($root);
            
            // Get all users from database
            $result = $conn->query("SELECT id, username, password_hash, role, created_at FROM test.users ORDER BY id");
            
            if (!$result) {
                error_log('[XMLSync] Error querying users: ' . $conn->error);
                return false;
            }
            
            while ($row = $result->fetch_assoc()) {
                $userElement = $this->usersDom->createElement('user');
                
                $this->addXMLElement($userElement, 'id', $row['id']);
                $this->addXMLElement($userElement, 'username', $row['username']);
                $this->addXMLElement($userElement, 'password_hash', $row['password_hash']);
                $this->addXMLElement($userElement, 'role', $row['role']);
                $this->addXMLElement($userElement, 'created_at', $this->normalizeDateTime($row['created_at']));
                
                $root->appendChild($userElement);
            }
            
            if ($this->validateUsersXML()) {
                $this->usersDom->formatOutput = true;
                return $this->usersDom->save($this->usersXmlPath);
            }
            
            return false;
        } catch (Exception $e) {
            error_log('[XMLSync] Error syncing all users: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync all archived tasks from MySQL to XML (full rebuild)
     */
    public function syncAllArchiveTasksToXML($conn)
    {
        try {
            $this->archiveTasksDom = new DOMDocument('1.0', 'UTF-8');
            $root = $this->archiveTasksDom->createElement('archive_tasks');
            $this->archiveTasksDom->appendChild($root);

            $result = $conn->query("SELECT id, user_id, title, description, status, created_at, archived_at FROM test.archive_tasks ORDER BY id");
            if (!$result) {
                error_log('[XMLSync] Error querying archived tasks: ' . $conn->error);
                return false;
            }

            while ($row = $result->fetch_assoc()) {
                $archiveTaskElement = $this->archiveTasksDom->createElement('archive_task');
                $this->addXMLElement($archiveTaskElement, 'id', $row['id']);
                $this->addXMLElement($archiveTaskElement, 'user_id', $row['user_id']);
                $this->addXMLElement($archiveTaskElement, 'title', $row['title']);
                $this->addXMLElement($archiveTaskElement, 'description', $row['description']);
                $this->addXMLElement($archiveTaskElement, 'status', $row['status']);
                $this->addXMLElement($archiveTaskElement, 'created_at', $this->normalizeDateTime($row['created_at']));
                $this->addXMLElement($archiveTaskElement, 'archived_at', $this->normalizeDateTime($row['archived_at']));
                $root->appendChild($archiveTaskElement);
            }

            if ($this->validateArchiveTasksXML()) {
                $this->archiveTasksDom->formatOutput = true;
                return $this->archiveTasksDom->save($this->archiveTasksXmlPath);
            }

            return false;
        } catch (Exception $e) {
            error_log('[XMLSync] Error syncing all archived tasks: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync all deleted task logs from MySQL to XML (full rebuild)
     */
    public function syncAllDeletedTasksToXML($conn)
    {
        try {
            $this->deletedTasksDom = new DOMDocument('1.0', 'UTF-8');
            $root = $this->deletedTasksDom->createElement('deleted_tasks');
            $this->deletedTasksDom->appendChild($root);

            $result = $conn->query("SELECT id, user_id, title, description, status, created_at, deleted_at FROM " . DB_NAME . ".deleted_tasks ORDER BY id");
            if (!$result) {
                error_log('[XMLSync] Error querying deleted tasks: ' . $conn->error);
                return false;
            }

            while ($row = $result->fetch_assoc()) {
                $deletedTaskElement = $this->deletedTasksDom->createElement('deleted_task');
                $this->addXMLElement($deletedTaskElement, 'id', $row['id']);
                $this->addXMLElement($deletedTaskElement, 'user_id', $row['user_id']);
                $this->addXMLElement($deletedTaskElement, 'title', $row['title']);
                $this->addXMLElement($deletedTaskElement, 'description', $row['description']);
                $this->addXMLElement($deletedTaskElement, 'status', $row['status']);
                $this->addXMLElement($deletedTaskElement, 'created_at', $this->normalizeDateTime($row['created_at']));
                $this->addXMLElement($deletedTaskElement, 'deleted_at', $this->normalizeDateTime($row['deleted_at']));
                $root->appendChild($deletedTaskElement);
            }

            if ($this->validateDeletedTasksXML()) {
                $this->deletedTasksDom->formatOutput = true;
                return $this->deletedTasksDom->save($this->deletedTasksXmlPath);
            }

            return false;
        } catch (Exception $e) {
            error_log('[XMLSync] Error syncing all deleted tasks: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Load tasks XML file
     */
    private function loadTasksXML()
    {
        $this->tasksDom = new DOMDocument('1.0', 'UTF-8');
        if (file_exists($this->tasksXmlPath)) {
            $this->tasksDom->load($this->tasksXmlPath);
        } else {
            $root = $this->tasksDom->createElement('tasks');
            $this->tasksDom->appendChild($root);
        }
    }

    /**
     * Load users XML file
     */
    private function loadUsersXML()
    {
        $this->usersDom = new DOMDocument('1.0', 'UTF-8');
        if (file_exists($this->usersXmlPath)) {
            $this->usersDom->load($this->usersXmlPath);
        } else {
            $root = $this->usersDom->createElement('users');
            $this->usersDom->appendChild($root);
        }
    }

    /**
     * Load archive tasks XML file
     * Creates file if it doesn't exist with empty root element
     */
    private function loadArchiveTasksXML()
    {
        // Create new DOMDocument for archive tasks
        $this->archiveTasksDom = new DOMDocument('1.0', 'UTF-8');
        
        // Check if archive_tasks.xml file exists on disk
        if (file_exists($this->archiveTasksXmlPath)) {
            // Load existing file into the DOMDocument
            $this->archiveTasksDom->load($this->archiveTasksXmlPath);
        } else {
            // Create new empty XML structure with root element
            $root = $this->archiveTasksDom->createElement('archive_tasks');
            $this->archiveTasksDom->appendChild($root);
        }
    }

    /**
     * Load deleted tasks XML file
     */
    private function loadDeletedTasksXML()
    {
        $this->deletedTasksDom = new DOMDocument('1.0', 'UTF-8');
        
        if (file_exists($this->deletedTasksXmlPath)) {
            $this->deletedTasksDom->load($this->deletedTasksXmlPath);
        } else {
            $root = $this->deletedTasksDom->createElement('deleted_tasks');
            $this->deletedTasksDom->appendChild($root);
        }
    }

    /**
     * Validate tasks XML against schema
     */
    private function validateTasksXML()
    {
        if (!file_exists($this->tasksXsdPath)) {
            return true; // Skip validation if schema doesn't exist
        }
        
        libxml_use_internal_errors(true);
        $valid = $this->tasksDom->schemaValidate($this->tasksXsdPath);
        if (!$valid) {
            $errors = libxml_get_errors();
            foreach ($errors as $err) {
                error_log('[XMLSync][tasks.xsd] ' . trim($err->message));
            }
            libxml_clear_errors();
            // Don't block saving on validation errors to avoid data loss
            return true;
        }
        libxml_clear_errors();
        return true;
    }

    /**
     * Validate users XML against schema
     */
    private function validateUsersXML()
    {
        if (!file_exists($this->usersXsdPath)) {
            return true; // Skip validation if schema doesn't exist
        }
        
        libxml_use_internal_errors(true);
        $valid = $this->usersDom->schemaValidate($this->usersXsdPath);
        if (!$valid) {
            $errors = libxml_get_errors();
            foreach ($errors as $err) {
                error_log('[XMLSync][users.xsd] ' . trim($err->message));
            }
            libxml_clear_errors();
            return true;
        }
        libxml_clear_errors();
        return true;
    }

    /**
     * Validate archive tasks XML against schema
     * Checks if archive_tasks.xml conforms to archive_tasks.xsd structure
     * Logs any validation errors for debugging
     */
    private function validateArchiveTasksXML()
    {
        // Check if the XSD schema file exists before validation
        if (!file_exists($this->archiveTasksXsdPath)) {
            // Skip validation if schema file is missing (still return true to save)
            return true;
        }
        
        // Enable internal error handling for better error messages
        libxml_use_internal_errors(true);

        // Validate the archive tasks XML against the schema
        $valid = $this->archiveTasksDom->schemaValidate($this->archiveTasksXsdPath);
        if (!$valid) {
            $errors = libxml_get_errors();
            foreach ($errors as $err) {
                error_log('[XMLSync][archive_tasks.xsd] ' . trim($err->message));
            }
            libxml_clear_errors();
            return true;
        }

        // Clear any accumulated libxml errors from memory
        libxml_clear_errors();
        return true;
    }

    /**
     * Validate deleted tasks XML against schema
     */
    private function validateDeletedTasksXML()
    {
        if (!file_exists($this->deletedTasksXsdPath)) {
            return true;
        }

        libxml_use_internal_errors(true);
        $valid = $this->deletedTasksDom->schemaValidate($this->deletedTasksXsdPath);
        if (!$valid) {
            $errors = libxml_get_errors();
            foreach ($errors as $err) {
                error_log('[XMLSync][deleted_tasks.xsd] ' . trim($err->message));
            }
            libxml_clear_errors();
            return true;
        }
        libxml_clear_errors();
        return true;
    }

    /**
     * Helper: Add element to XML with value
     */
    private function addXMLElement(&$parentElement, $name, $value, $dom = null)
    {
        $domToUse = $dom;
        if (!$domToUse && $parentElement instanceof DOMElement) {
            $domToUse = $parentElement->ownerDocument;
        }
        $domToUse = $domToUse ?? $this->tasksDom ?? $this->usersDom;
        if (!$domToUse) {
            $domToUse = new DOMDocument('1.0', 'UTF-8');
        }

        $element = $domToUse->createElement($name);
        $textNode = $domToUse->createTextNode((string)$value);
        $element->appendChild($textNode);
        $parentElement->appendChild($element);
    }

    /**
     * Normalize date/time values to XML xs:dateTime format
     * Supports DB timestamps with spaces and optional fractional seconds
     */
    private function normalizeDateTime($value)
    {
        if (empty($value)) {
            return '';
        }

        try {
            $date = new DateTime($value);
            return $date->format('Y-m-d\TH:i:s');
        } catch (Exception $e) {
            // Fall back to raw value if parsing fails
            return (string)$value;
        }
    }
}

// Global sync handler instance
function getXMLSyncHandler()
{
    static $handler = null;
    if ($handler === null) {
        $handler = new XMLSyncHandler();
    }
    return $handler;
}
?>
