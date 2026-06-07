<?php
declare(strict_types=1);

// 1. Load your actual file
require_once __DIR__ . '/xml_handler.php';

class DatabaseAdapter
{
    private bool $useMySQL;
    private ?mysqli $conn;
    
    // 2. Correctly type-hinted to the class name in your xml_handler.php
    private XMLHandler $xmlHandler; 

    public function __construct(?mysqli $conn = null)
    {
        // 3. Initialize using the exact class name found in your file
        $this->xmlHandler = new XMLHandler();
        
        if ($conn instanceof mysqli && !$conn->connect_error) {
            $this->conn = $conn;
            $this->useMySQL = true;
        } else {
            $this->conn = null;
            $this->useMySQL = false;
        }
    }

    // --- PUBLIC INTERFACE ---

    public function getTasks(int $userId): array
    {
        return $this->useMySQL ? $this->getTasksMySQL($userId) : $this->getTasksXML($userId);
    }

    public function addTask(int $userId, string $title, string $description = ''): array
    {
        return $this->useMySQL ? $this->addTaskMySQL($userId, $title, $description) : $this->addTaskXML($userId, $title, $description);
    }

    public function updateTaskStatus(int $taskId, string $status): array
    {
        return $this->useMySQL 
            ? $this->updateTaskStatusMySQL($taskId, $status) 
            : $this->xmlHandler->updateTask($taskId, ['status' => $status]);
    }

    public function deleteTask(int $taskId): array
    {
        return $this->useMySQL 
            ? $this->deleteTaskMySQL($taskId) 
            : $this->xmlHandler->deleteTask($taskId);
    }

    // --- PRIVATE MYSQL METHODS ---

    private function getTasksMySQL(int $userId): array
    {
        $stmt = $this->conn->prepare("SELECT id, title, description, status, created_at FROM tasks WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    private function addTaskMySQL(int $userId, string $title, string $description): array
    {
        $stmt = $this->conn->prepare("INSERT INTO tasks (user_id, title, description, status) VALUES (?, ?, ?, 'pending')");
        $stmt->bind_param("iss", $userId, $title, $description);
        $success = $stmt->execute();
        $id = $success ? $stmt->insert_id : 0;
        return ['success' => $success, 'id' => $id];
    }

    private function updateTaskStatusMySQL(int $taskId, string $status): array
    {
        $stmt = $this->conn->prepare("UPDATE tasks SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $taskId);
        $result = $stmt->execute();
        return ['success' => $result];
    }

    private function deleteTaskMySQL(int $taskId): array
    {
        $stmt = $this->conn->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->bind_param("i", $taskId);
        $result = $stmt->execute();
        return ['success' => $result];
    }

    // --- PRIVATE XML WRAPPER METHODS ---

    private function getTasksXML(int $userId): array
    {
        $allTasks = $this->xmlHandler->getTasks();
        // XMLHandler returns ALL tasks, so we filter by user_id
        return array_values(array_filter($allTasks, function($task) use ($userId) {
            return (int)($task['user_id'] ?? 0) === $userId;
        }));
    }

    private function addTaskXML(int $userId, string $title, string $description): array
    {
        // XMLHandler needs an array of data. We mimic the DB structure.
        $data = [
            'id' => time(), // Simple unique ID generator for XML
            'user_id' => $userId,
            'title' => $title,
            'description' => $description,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $success = $this->xmlHandler->addTask($data);
        return ['success' => $success, 'id' => $data['id']];
    }
}
?>