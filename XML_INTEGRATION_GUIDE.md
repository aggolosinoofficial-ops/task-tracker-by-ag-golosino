# XML Database Integration Guide

## Overview

This implementation adds XML and XSD file support to your to-do app while maintaining full compatibility with the existing MySQL database. You can now choose to use either MySQL or XML for data storage, or even use both simultaneously.

## Files Created

### 1. **tasks.xml**

- Local XML database file storing all tasks
- Automatically created with proper XML structure
- Contains task elements with user_id, title, description, status, and created_at

### 2. **tasks.xsd**

- XML Schema Definition for validation
- Validates structure and data types
- Defines valid status values: pending, completed, in_progress, cancelled
- Enforces required fields and data constraints

### 3. **xml_handler.php**

- Core XML handling class: `XMLTaskHandler`
- Provides methods for CRUD operations on XML tasks
- Includes automatic XSD validation
- Features:
  - `addTask()` - Add new task
  - `getTasks()` - Retrieve tasks (with optional user_id filtering)
  - `getTaskById()` - Get specific task
  - `updateTask()` - Update task data
  - `deleteTask()` - Remove task
  - `validateXML()` - Validate against XSD schema
  - `exportJSON()` - Export tasks as JSON

### 4. **db_adapter.php**

- Unified database adapter supporting MySQL and XML backends
- Switch between backends by changing constructor parameter
- Methods:
  - `getTasks($userId)`
  - `addTask($userId, $title, $description)`
  - `updateTaskStatus($taskId, $status)`
  - `deleteTask($taskId)`

## Usage Examples

### Using XML Handler Directly

```php
<?php
include 'xml_handler.php';

// Create handler instance
$xmlHandler = new XMLTaskHandler();

// Add a task
$taskData = [
    'id' => 1,
    'user_id' => 5,
    'title' => 'My Task',
    'description' => 'Task description',
    'status' => 'pending',
    'created_at' => date('c')
];
$result = $xmlHandler->addTask($taskData);

// Get all tasks for user 5
$tasks = $xmlHandler->getTasks(5);

// Get specific task
$task = $xmlHandler->getTaskById(1);

// Update task
$xmlHandler->updateTask(1, ['status' => 'completed']);

// Delete task
$xmlHandler->deleteTask(1);

// Validate XML
$validation = $xmlHandler->validateXML();
if ($validation['valid']) {
    echo "XML is valid!";
} else {
    print_r($validation['errors']);
}
?>
```

### Using Database Adapter (Recommended for Switching Backends)

```php
<?php
include 'db_adapter.php';
include 'db.php'; // For MySQL connection

// Use MySQL backend
$adapter = new DatabaseAdapter('mysql', $conn);

// OR use XML backend
$adapter = new DatabaseAdapter('xml');

// Now use same interface for both backends
$tasks = $adapter->getTasks($userId);
$result = $adapter->addTask($userId, 'Task Title', 'Description');
$adapter->updateTaskStatus($taskId, 'completed');
$adapter->deleteTask($taskId);
?>
```

### Integrating with Existing Code

**Option 1: Modify get_tasks.php to support both backends**

```php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

include 'auth_check.php';
include 'db_adapter.php';
include 'db.php';

header('Content-Type: application/json');

$user_id = checkAuth();
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in to view tasks']);
    exit();
}

// Choose your backend: 'mysql' or 'xml'
$adapter = new DatabaseAdapter('mysql', $conn);
// $adapter = new DatabaseAdapter('xml');

$tasks = $adapter->getTasks($user_id);
echo json_encode($tasks);
?>
```

**Option 2: Modify add_task.php**

```php
<?php
include 'auth_check.php';
include 'db_adapter.php';
include 'db.php';

header('Content-Type: application/json');

$user_id = checkAuth();
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in to add tasks']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';

if (empty($title)) {
    echo json_encode(['success' => false, 'error' => 'Task title is required']);
    exit();
}

// Choose your backend
$adapter = new DatabaseAdapter('mysql', $conn);
$result = $adapter->addTask($user_id, $title, $description);

echo json_encode($result);
?>
```

## XML Structure Example

```xml
<?xml version="1.0" encoding="UTF-8"?>
<tasks xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="tasks.xsd">
    <task>
        <id>1</id>
        <user_id>5</user_id>
        <title>Buy groceries</title>
        <description>Milk, eggs, bread</description>
        <status>completed</status>
        <created_at>2026-05-08T10:30:00+00:00</created_at>
    </task>
    <task>
        <id>2</id>
        <user_id>5</user_id>
        <title>Finish project</title>
        <description>Complete the XML integration</description>
        <status>in_progress</status>
        <created_at>2026-05-08T11:00:00+00:00</created_at>
    </task>
</tasks>
```

## Valid Status Values

The XSD schema enforces these status values:

- `pending` - Task not started
- `completed` - Task finished
- `in_progress` - Task in progress
- `cancelled` - Task cancelled

## Validation

All XML operations are automatically validated against the XSD schema. Invalid data will be rejected with descriptive error messages.

```php
$validation = $xmlHandler->validateXML();
if (!$validation['valid']) {
    echo "Errors: " . implode(', ', $validation['errors']);
}
```

## Migration from XML to MySQL

If you want to migrate data from XML to MySQL:

```php
<?php
include 'xml_handler.php';
include 'db.php';

$xmlHandler = new XMLTaskHandler();
$allTasks = $xmlHandler->getTasks();

foreach ($allTasks as $task) {
    $stmt = $conn->prepare("INSERT INTO test.tasks (id, user_id, title, description, status, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissss", $task['id'], $task['user_id'], $task['title'], $task['description'], $task['status'], $task['created_at']);
    $stmt->execute();
}

echo "Migration complete!";
?>
```

## Benefits

✅ **Local Storage** - No database server needed for XML mode
✅ **Version Control** - XML files can be tracked in Git
✅ **Validation** - XSD ensures data integrity
✅ **Dual Support** - Run MySQL and XML simultaneously
✅ **Easy Switching** - Change backends with single parameter change
✅ **Backward Compatible** - Existing code works unchanged

## Notes

- Tasks.xml is saved with proper formatting for readability
- All user data is properly XML-escaped to prevent injection
- Timestamps use ISO 8601 format for compatibility
- The adapter maintains the same return format for both backends
