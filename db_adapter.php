<?php
declare(strict_types=1);

/**
 * Database Adapter
 * Unified Interface for MySQL and XML Storage
 * Part 1 of 2
 */

class DatabaseAdapter
{
    private string $backend;
    private ?mysqli $conn;
    private ?XMLTaskHandler $xmlHandler = null;

    public function __construct(string $backend = 'mysql', ?mysqli $conn = null)
    {
        $this->backend = $backend;

        if ($backend === 'mysql') {
            $this->conn = $conn;
        } elseif ($backend === 'xml') {
            if (file_exists('xml_handler.php')) {
                require_once 'xml_handler.php';
                $this->xmlHandler = new XMLTaskHandler();
            } else {
                throw new Exception("XML Handler file not found.");
            }
        }
    }

    // --- PUBLIC INTERFACE ---

    public function getTasks(int $userId): array
    {
        return ($this->backend === 'mysql') ? $this->getTasksMySQL($userId) : $this->getTasksXML($userId);
    }

    public function addTask(int $userId, string $title, string $description = ''): array
    {
        return ($this->backend === 'mysql') ? $this->addTaskMySQL($userId, $title, $description) : $this->addTaskXML($userId, $title, $description);
    }

    public function updateTaskStatus(int $taskId, string $status): array
    {
        return ($this->backend === 'mysql') ? $this->updateTaskStatusMySQL($taskId, $status) : $this->updateTaskStatusXML($taskId, $status);
    }

    public function deleteTask(int $taskId): array
    {
        return ($this->backend === 'mysql') ? $this->deleteTaskMySQL($taskId) : $this->deleteTaskXML($taskId);
    }
    // --- PRIVATE MYSQL METHODS ---

    private function getTasksMySQL(int $userId): array
    {
        if (!$this->conn) return ['error' => 'No database connection'];
        
        $sql = "SELECT id, title, description, status, created_at FROM test.tasks WHERE user_id = ? ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return ['error' => $this->conn->error];

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $tasks = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $tasks[] = $row;
            }
        }
        $stmt->close();
        return $tasks;
    }

    private function addTaskMySQL(int $userId, string $title, string $description): array
    {
        if (!$this->conn) return ['success' => false, 'error' => 'No database connection'];

        $stmt = $this->conn->prepare("INSERT INTO test.tasks (user_id, title, description, status) VALUES (?, ?, ?, 'pending')");
        $stmt->bind_param("iss", $userId, $title, $description);

        if ($stmt->execute()) {
            $id = $stmt->insert_id;
            $stmt->close();
            return ['success' => true, 'id' => $id];
        }
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'error' => $error];
    }

    private function updateTaskStatusMySQL(int $taskId, string $status): array
    {
        if (!$this->conn) return ['success' => false, 'error' => 'No database connection'];
        
        $stmt = $this->conn->prepare("UPDATE test.tasks SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $taskId);
        $result = $stmt->execute();
        $stmt->close();
        return ['success' => (bool)$result];
    }

    private function deleteTaskMySQL(int $taskId): array
    {
        if (!$this->conn) return ['success' => false, 'error' => 'No database connection'];

        $stmt = $this->conn->prepare("DELETE FROM test.tasks WHERE id = ?");
        $stmt->bind_param("i", $taskId);
        $result = $stmt->execute();
        $stmt->close();
        return ['success' => (bool)$result];
    }

    // --- PRIVATE XML METHODS ---

    private function getTasksXML(int $userId): array
    {
        return $this->xmlHandler ? $this->xmlHandler->getTasks($userId) : [];
    }

    private function addTaskXML(int $userId, string $title, string $description): array
    {
        if (!$this->xmlHandler) return ['success' => false];
        
        $taskId = $this->xmlHandler->getNextTaskId();
        $taskData = [
            'id' => $taskId,
            'user_id' => $userId,
            'title' => $title,
            'description' => $description,
            'status' => 'pending',
            'created_at' => date('c')
        ];
        return array_merge($this->xmlHandler->addTask($taskData), ['id' => $taskId]);
    }

    private function updateTaskStatusXML(int $taskId, string $status): array
    {
        return $this->xmlHandler ? $this->xmlHandler->updateTask($taskId, ['status' => $status]) : ['success' => false];
    }

    private function deleteTaskXML(int $taskId): array
    {
        return $this->xmlHandler ? $this->xmlHandler->deleteTask($taskId) : ['success' => false];
    }
}
?>