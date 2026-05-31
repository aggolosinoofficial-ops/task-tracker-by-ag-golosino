# BEFORE & AFTER: Exact Changes Made

## FILE 1: config.php

### LOCATION: End of file (after line 45)

### BEFORE:

```php
// CSRF protection
define('CSRF_TOKEN_LENGTH', 32);
define('CSRF_TOKEN_EXPIRY', 86400); // 24 hours
```

### AFTER:

```php
// CSRF protection
define('CSRF_TOKEN_LENGTH', 32);
define('CSRF_TOKEN_EXPIRY', 86400); // 24 hours

// DEVELOPMENT MODE - Set to true to bypass rate limiting for testing
// WARNING: Only for development/testing. Set to false in production!
define('DEV_MODE', false); // Set to true to disable rate limiting and bypass checks
```

### WHY: Allows bypassing rate limiting during development for unlimited test registrations/logins

---

## FILE 2: auth_check.php

### LOCATION: Line 277 - checkRateLimit() function

### BEFORE:

```php
/**
 * Check rate limiting for IP
 * Returns: ['allowed' => bool, 'wait_seconds' => int|null]
 */
function checkRateLimit($action, $max_attempts, $window_seconds)
{
    $ip = getClientIP();
    $key = "ratelimit_{$action}_{$ip}";

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'attempts' => 0,
            'first_attempt' => time(),
            'locked_until' => null
        ];
    }

    $data = &$_SESSION[$key];
    $now = time();

    // Check if currently locked out
    if ($data['locked_until'] && $now < $data['locked_until']) {
        $wait = $data['locked_until'] - $now;
        return ['allowed' => false, 'wait_seconds' => $wait];
    }

    // Reset if window expired
    if ($now - $data['first_attempt'] > $window_seconds) {
        $data['attempts'] = 0;
        $data['first_attempt'] = $now;
        $data['locked_until'] = null;
```

### AFTER:

```php
/**
 * Check rate limiting for IP
 * Returns: ['allowed' => bool, 'wait_seconds' => int|null]
 * OPTIMIZATION: Bypass rate limiting in DEV_MODE for testing
 */
function checkRateLimit($action, $max_attempts, $window_seconds)
{
    // DEVELOPMENT BYPASS: If DEV_MODE is enabled, skip all rate limiting checks
    // This allows unlimited login/registration attempts during testing
    if (defined('DEV_MODE') && DEV_MODE === true) {
        // Allow all attempts when in development mode
        return ['allowed' => true, 'wait_seconds' => null];
    }

    $ip = getClientIP();
    $key = "ratelimit_{$action}_{$ip}";

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'attempts' => 0,
            'first_attempt' => time(),
            'locked_until' => null
        ];
    }

    $data = &$_SESSION[$key];
    $now = time();

    // Check if currently locked out
    if ($data['locked_until'] && $now < $data['locked_until']) {
        $wait = $data['locked_until'] - $now;
        return ['allowed' => false, 'wait_seconds' => $wait];
    }

    // Reset if window expired
    if ($now - $data['first_attempt'] > $window_seconds) {
        $data['attempts'] = 0;
        $data['first_attempt'] = $now;
        $data['locked_until'] = null;
```

### WHY: Checks DEV_MODE flag before rate limiting logic; skips if enabled

---

## FILE 3: xml_sync_handler.php

### LOCATION A: Line 8-10 - Class Properties

### BEFORE:

```php
    private $tasksXmlPath;
    private $tasksXsdPath;
    private $usersXmlPath;
    private $usersXsdPath;
    private $tasksDom;
    private $usersDom;
```

### AFTER:

```php
    private $tasksXmlPath;
    private $tasksXsdPath;
    private $usersXmlPath;
    private $usersXsdPath;
    private $archiveTasksXmlPath;
    private $archiveTasksXsdPath;
    private $tasksDom;
    private $usersDom;
    private $archiveTasksDom;
```

### REASON: Store paths and DOM objects for archive_tasks.xml

---

### LOCATION B: Line 22-32 - Constructor

### BEFORE:

```php
    public function __construct()
    {
        $this->tasksXmlPath = __DIR__ . '/tasks.xml';
        $this->tasksXsdPath = __DIR__ . '/tasks.xsd';
        $this->usersXmlPath = __DIR__ . '/users.xml';
        $this->usersXsdPath = __DIR__ . '/users.xsd';
    }
```

### AFTER:

```php
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
    }
```

### REASON: Initialize paths for archive_tasks files

---

### LOCATION C: After syncTaskDeleteToXML() - NEW METHODS ADDED

### ADDED CODE (after line ~145):

```php
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
            $this->addXMLElement($archiveTaskElement, 'id', $archiveId);
            $this->addXMLElement($archiveTaskElement, 'user_id', $userId);
            $this->addXMLElement($archiveTaskElement, 'title', $title);
            $this->addXMLElement($archiveTaskElement, 'description', $description);
            $this->addXMLElement($archiveTaskElement, 'status', $status);
            $this->addXMLElement($archiveTaskElement, 'created_at', $createdAt);
            $this->addXMLElement($archiveTaskElement, 'archived_at', $archivedAt);

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
    public function syncRestoreTaskFromArchiveXML($archiveId)
    {
        try {
            // Step 1: Load the existing archive_tasks.xml file
            $this->loadArchiveTasksXML();

            // Step 2: Get all <archive_task> elements
            $archiveTasks = $this->archiveTasksDom->getElementsByTagName('archive_task');

            // Step 3: Iterate backwards to safely remove elements while iterating
            for ($i = $archiveTasks->length - 1; $i >= 0; $i--) {
                $archiveTaskElement = $archiveTasks->item($i);
                // Get the <id> child element of this archive task
                $idElement = $archiveTaskElement->getElementsByTagName('id')->item(0);

                // Check if this is the archive task we're looking for
                if ($idElement && intval($idElement->nodeValue) === intval($archiveId)) {
                    // Step 4: Remove this element from the XML document
                    $archiveTaskElement->parentNode->removeChild($archiveTaskElement);
                    break;
                }
            }

            // Step 5: Validate and save the updated archive XML
            if ($this->validateArchiveTasksXML()) {
                return $this->archiveTasksDom->save($this->archiveTasksXmlPath);
            }

            return false;
        } catch (Exception $e) {
            // Log any errors for debugging
            error_log('[XMLSync] Error restoring task from archive XML: ' . $e->getMessage());
            return false;
        }
    }
```

### REASON: New functions to sync archived tasks to archive_tasks.xml

---

### LOCATION D: After loadUsersXML() - NEW LOAD FUNCTION

### ADDED CODE:

```php
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
```

### REASON: Load or create archive_tasks.xml file

---

### LOCATION E: After validateUsersXML() - NEW VALIDATION FUNCTION

### ADDED CODE:

```php
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

        // Clear any accumulated libxml errors from memory
        libxml_clear_errors();

        // Return validation result (true = valid, false = invalid)
        return $valid;
    }
```

### REASON: Validate archive_tasks.xml against schema

---

## FILE 4: delete_task.php

### LOCATION A: Line 50-58 - SELECT Query

### BEFORE:

```php
    // First, get the task details to archive it
    $getStmt = $conn->prepare("SELECT id, title, description, status FROM " . DB_NAME . "." . DB_TABLE_TASKS . " WHERE id = ? AND user_id = ?");
```

### AFTER:

```php
    // First, get the task details to archive it
    // Retrieve all necessary info for archiving and backup
    $getStmt = $conn->prepare("SELECT id, title, description, status, created_at FROM " . DB_NAME . "." . DB_TABLE_TASKS . " WHERE id = ? AND user_id = ?");
```

### REASON: Get created_at for archive_tasks.xml timestamp

---

### LOCATION B: Line 85-92 - Archive Insert

### BEFORE:

```php
    $archiveStmt->bind_param("isss", $user_id, $task['title'], $task['description'], $task['status']);
    if (!$archiveStmt->execute()) {
        $archiveStmt->close();
        throw new Exception('Failed to archive task');
    }
    $archiveStmt->close();
```

### AFTER:

```php
    $archiveStmt->bind_param("isss", $user_id, $task['title'], $task['description'], $task['status']);
    if (!$archiveStmt->execute()) {
        $archiveStmt->close();
        throw new Exception('Failed to archive task');
    }

    // Get the archive ID that was just created
    $archive_id = $archiveStmt->insert_id;
    $archiveStmt->close();
```

### REASON: Store archive_id for use in XML sync

---

### LOCATION C: Line 105-121 - XML Sync

### BEFORE:

```php
        if ($stmt->affected_rows > 0) {
            // Sync task deletion to XML backup
            $sync = getXMLSyncHandler();
            $sync->syncTaskDeleteToXML($task_id);

            // Task was archived successfully
            $stmt->close();

            // Update task statistics
            updateTaskStats($user_id);
```

### AFTER:

```php
        if ($stmt->affected_rows > 0) {
            // Sync task deletion from active tasks to XML backup
            $sync = getXMLSyncHandler();
            $sync->syncTaskDeleteToXML($task_id);

            // Sync the archived task to archive_tasks.xml for backup recovery
            // This ensures the archived task is preserved in XML alongside MySQL
            $sync->syncArchiveTaskToXML(
                $archive_id,
                $user_id,
                $task['title'],
                $task['description'],
                $task['status'],
                $task['created_at'],  // Original task creation time
                date('Y-m-d H:i:s')   // Current timestamp for archive time
            );

            // Task was archived successfully
            $stmt->close();

            // Update task statistics (decrease active count, increase archive count)
            updateTaskStats($user_id);
```

### REASON: Add dual XML sync - remove from tasks.xml, add to archive_tasks.xml

---

## FILE 5: restore_task.php

### LOCATION A: Line 1-12 - Includes

### BEFORE:

```php
/**
 * Restore Task Handler
 * Restores an archived task back to active tasks
 * - Requires authentication
 * - Verifies user owns the archived task
 * - Returns JSON response
 */

include 'auth_check.php';

header('Content-Type: application/json');
```

### AFTER:

```php
/**
 * Restore Task Handler
 * Restores an archived task back to active tasks
 * - Requires authentication
 * - Verifies user owns the archived task
 * - Syncs restoration to both tasks.xml and archive_tasks.xml
 * - Returns JSON response
 */

include 'auth_check.php';
include 'xml_sync_handler.php';

header('Content-Type: application/json');
```

### REASON: Include XML sync handler for restoration sync

---

### LOCATION B: Line 52-98 - Task Insertion and XML Sync

### BEFORE:

```php
    // Insert back into tasks table
    $stmt = $conn->prepare("INSERT INTO " . DB_NAME . "." . DB_TABLE_TASKS . " (user_id, title, description, status) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception('Database error');
    }

    $stmt->bind_param("isss", $user_id, $task['title'], $task['description'], $task['status']);

    if ($stmt->execute()) {
        $stmt->close();

        // Delete from archive table
        $stmt = $conn->prepare("DELETE FROM " . DB_NAME . ".archive_tasks WHERE id = ? AND user_id = ?");
        if (!$stmt) {
            throw new Exception('Database error');
        }

        $stmt->bind_param("ii", $archive_id, $user_id);

        if ($stmt->execute()) {
            // Update stats
            updateTaskStats($user_id);

            echo json_encode(['success' => true, 'message' => 'Task restored successfully']);
        } else {
            throw new Exception('Failed to restore task');
        }
    } else {
        throw new Exception('Failed to restore task');
```

### AFTER:

```php
    // Insert back into tasks table
    // This moves the task from archive back to active tasks
    $stmt = $conn->prepare("INSERT INTO " . DB_NAME . "." . DB_TABLE_TASKS . " (user_id, title, description, status) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception('Database error');
    }

    $stmt->bind_param("isss", $user_id, $task['title'], $task['description'], $task['status']);

    if ($stmt->execute()) {
        // Get the newly created task ID for XML sync
        $new_task_id = $stmt->insert_id;
        $stmt->close();

        // Delete from archive table in MySQL
        // This removes the task from the archive in the database
        $stmt = $conn->prepare("DELETE FROM " . DB_NAME . ".archive_tasks WHERE id = ? AND user_id = ?");
        if (!$stmt) {
            throw new Exception('Database error');
        }

        $stmt->bind_param("ii", $archive_id, $user_id);

        if ($stmt->execute()) {
            $stmt->close();

            // Sync the restoration to XML files
            // 1. Add the restored task to active tasks.xml
            // 2. Remove it from archive_tasks.xml
            $sync = getXMLSyncHandler();

            // Sync the restored task to active tasks.xml
            $sync->syncTaskToXML(
                $new_task_id,                  // New task ID generated after insert
                $user_id,                      // Task owner
                $task['title'],                // Task name
                $task['description'],          // Task details
                $task['status'],               // Restored status
                date('Y-m-d H:i:s')           // Mark as restored now
            );

            // Remove from archive_tasks.xml to maintain sync
            $sync->syncRestoreTaskFromArchiveXML($archive_id);

            // Update task statistics (increase active count, decrease archive count)
            updateTaskStats($user_id);

            echo json_encode(['success' => true, 'message' => 'Task restored successfully']);
        } else {
            throw new Exception('Failed to restore task');
        }
    } else {
        throw new Exception('Failed to restore task');
```

### REASON: Add dual XML sync on restore - add to tasks.xml, remove from archive_tasks.xml

---

## FILE 6: NEW FILE - archive_tasks.xsd

### LOCATION: Created at root level

### FILE CONTENT:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema" elementFormDefault="qualified">

    <!-- Root element for archived tasks collection -->
    <xs:element name="archive_tasks">
        <xs:complexType>
            <xs:sequence>
                <!-- Allows zero or more archived task elements -->
                <xs:element name="archive_task" type="archiveTaskType" minOccurs="0" maxOccurs="unbounded"/>
            </xs:sequence>
        </xs:complexType>
    </xs:element>

    <!-- Archived task element definition with metadata -->
    <xs:complexType name="archiveTaskType">
        <xs:sequence>
            <!-- Unique archive record ID (auto-generated) -->
            <xs:element name="id" type="xs:positiveInteger"/>
            <!-- User ID who owns this archived task -->
            <xs:element name="user_id" type="xs:positiveInteger"/>
            <!-- Task title/name (1-255 characters required) -->
            <xs:element name="title" type="xs:string" minLength="1" maxLength="255"/>
            <!-- Task description/details (0-1000 characters allowed) -->
            <xs:element name="description" type="xs:string" minLength="0" maxLength="1000"/>
            <!-- Final status when archived (pending/completed/in_progress/cancelled) -->
            <xs:element name="status" type="statusType"/>
            <!-- When the original task was created -->
            <xs:element name="created_at" type="xs:dateTime"/>
            <!-- When the task was archived/deleted -->
            <xs:element name="archived_at" type="xs:dateTime"/>
        </xs:sequence>
    </xs:complexType>

    <!-- Status enumeration - same as tasks for consistency -->
    <!-- Defines all valid task statuses that can be archived -->
    <xs:simpleType name="statusType">
        <xs:restriction base="xs:string">
            <!-- Task has not been started -->
            <xs:enumeration value="pending"/>
            <!-- Task has been completed -->
            <xs:enumeration value="completed"/>
            <!-- Task is currently in progress -->
            <xs:enumeration value="in_progress"/>
            <!-- Task was cancelled/discarded -->
            <xs:enumeration value="cancelled"/>
        </xs:restriction>
    </xs:simpleType>

</xs:schema>
```

### REASON: Define validation schema for archived tasks

---

## SUMMARY OF CHANGES

| File                 | Type     | Lines Changed                                                                                         | Reason                     |
| -------------------- | -------- | ----------------------------------------------------------------------------------------------------- | -------------------------- |
| config.php           | Added    | 3 new lines                                                                                           | DEV_MODE flag              |
| auth_check.php       | Modified | 10 lines                                                                                              | Rate limit bypass          |
| xml_sync_handler.php | Enhanced | 3 new properties, constructor +10 lines, 3 new methods (150+ lines), 2 new private methods (30 lines) | Archive sync functionality |
| delete_task.php      | Modified | 10 lines modified, 20 new lines                                                                       | Archive XML sync           |
| restore_task.php     | Modified | 1 include added, 40 new lines                                                                         | Restoration XML sync       |
| archive_tasks.xsd    | NEW      | Full file (50 lines)                                                                                  | Validation schema          |

### TOTAL: 6 files touched, ~300+ lines of new/modified code with full inline documentation

---

**All changes maintain backward compatibility and add new functionality without breaking existing code.**
