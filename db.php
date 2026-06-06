<?php
declare(strict_types=1);

// --- 1. XML HANDLER ---
class XMLTaskHandler {
    private string $filePath = 'tasks.xml';
    public function __construct(string $filePath = 'tasks.xml') {
        $this->filePath = $filePath;
        if (!file_exists($this->filePath)) file_put_contents($this->filePath, '<?xml version="1.0" encoding="UTF-8"?><tasks></tasks>');
    }
    public function getTasks(int $userId): array {
        $xml = simplexml_load_file($this->filePath);
        $tasks = [];
        foreach ($xml->task as $task) {
            if ((int)$task->user_id === $userId) $tasks[] = ['id'=>(int)$task->id, 'title'=>(string)$task->title, 'status'=>(string)$task->status];
        }
        return $tasks;
    }
    public function getNextTaskId(): int {
        $xml = simplexml_load_file($this->filePath);
        $ids = [];
        foreach ($xml->task as $task) $ids[] = (int)$task->id;
        return count($ids) > 0 ? max($ids) + 1 : 1;
    }
    public function addTask(array $data): array {
        $xml = simplexml_load_file($this->filePath);
        $t = $xml->addChild('task');
        foreach($data as $k => $v) $t->addChild($k, (string)$v);
        $xml->asXML($this->filePath);
        return ['success' => true];
    }
}

// --- 2. DATABASE SETUP ---
function getDatabaseConnection(): ?mysqli {
    $conn = @new mysqli("localhost", "root", "");
    if ($conn->connect_error) return null;
    $conn->query("CREATE DATABASE IF NOT EXISTS test");
    $conn->select_db("test");
    $conn->query("CREATE TABLE IF NOT EXISTS tasks (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, title VARCHAR(255) NOT NULL, description TEXT, status ENUM('pending', 'completed') DEFAULT 'pending') ENGINE=InnoDB");
    return $conn;
}

// --- 3. DATABASE ADAPTER ---
class DatabaseAdapter {
    private string $backend;
    private ?mysqli $conn;
    private ?XMLTaskHandler $xmlHandler = null;

    public function __construct(string $backend = 'mysql', ?mysqli $conn = null) {
        $this->backend = $backend;
        if ($backend === 'mysql') {
            $this->conn = $conn;
        } else {
            $this->xmlHandler = new XMLTaskHandler();
        }
    }

    public function getTasks(int $userId): array {
        if ($this->backend === 'mysql' && $this->conn) {
            $stmt = $this->conn->prepare("SELECT id, title, status FROM tasks WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        return $this->xmlHandler->getTasks($userId);
    }

    public function addTask(int $userId, string $title, string $description = ''): array {
        if ($this->backend === 'mysql' && $this->conn) {
            $stmt = $this->conn->prepare("INSERT INTO tasks (user_id, title, description, status) VALUES (?, ?, ?, 'pending')");
            $stmt->bind_param("iss", $userId, $title, $description);
            $res = $stmt->execute();
            return ['success' => (bool)$res, 'id' => $this->conn->insert_id];
        }
        $data = ['id' => $this->xmlHandler->getNextTaskId(), 'user_id' => $userId, 'title' => $title, 'description' => $description, 'status' => 'pending'];
        return $this->xmlHandler->addTask($data);
    }
}

// --- 4. EXECUTION ---
$conn = getDatabaseConnection();
$db = ($conn) ? new DatabaseAdapter('mysql', $conn) : new DatabaseAdapter('xml');

$userId = 1;
$db->addTask($userId, "Test Task", "This works automatically!");
$tasks = $db->getTasks($userId);

echo "<h1>Tasks</h1><pre>";
print_r($tasks);
echo "</pre>";
?>