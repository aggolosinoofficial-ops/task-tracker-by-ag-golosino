<?php
/**
 * Database Adapter - Unified Interface for MySQL and XML Storage
 * Allows seamless switching between database backends
 */

class DatabaseAdapter
{
    private $backend; // 'mysql' or 'xml'
    private $conn;
    private $xmlHandler;

    public function __construct($backend = 'mysql', $conn = null)
    {
        $this->backend = $backend;

        if ($backend === 'mysql') {
            $this->conn = $conn;
        } else if ($backend === 'xml') {
            require_once 'xml_handler.php';
            $this->xmlHandler = new XMLTaskHandler();
        }
    }

    /**
     * Get all tasks for a user
     */
    public function getTasks($userId)
    {
        if ($this->backend === 'mysql') {
            return $this->getTasksMySQL($userId);
        } else {
            return $this->getTasksXML($userId);
        }
    }

    /**
     * Add a new task
     */
    public function addTask($userId, $title, $description = '')
    {
        if ($this->backend === 'mysql') {
            return $this->addTaskMySQL($userId, $title, $description);
        } else {
            return $this->addTaskXML($userId, $title, $description);
        }
    }

    /**
     * Update task status
     */
    public function updateTaskStatus($taskId, $status)
    {
        if ($this->backend === 'mysql') {
            return $this->updateTaskStatusMySQL($taskId, $status);
        } else {
            return $this->updateTaskStatusXML($taskId, $status);
        }
    }

    /**
     * Delete a task
     */
    public function deleteTask($taskId)
    {
        if ($this->backend === 'mysql') {
            return $this->deleteTaskMySQL($taskId);
        } else {
            return $this->deleteTaskXML($taskId);
        }
    }

    // ==================== MYSQL METHODS ====================

    private function getTasksMySQL($userId)
    {
        $sql = "SELECT id, title, description, status, created_at FROM test.tasks 
                WHERE user_id = ? 
                ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return ['error' => 'Database error: ' . $this->conn->error];
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $tasks = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $tasks[] = $row;
            }
        }

        $stmt->close();
        return $tasks;
    }

    private function addTaskMySQL($userId, $title, $description)
    {
        $stmt = $this->conn->prepare("INSERT INTO test.tasks (user_id, title, description, status) VALUES (?, ?, ?, 'pending')");
        if (!$stmt) {
            return ['success' => false, 'error' => 'Database error: ' . $this->conn->error];
        }

        $stmt->bind_param("iss", $userId, $title, $description);

        if ($stmt->execute()) {
            $result = [
                'success' => true,
                'message' => 'Task added successfully',
                'id' => $stmt->insert_id
            ];
            $stmt->close();
            return $result;
        } else {
            $result = ['success' => false, 'error' => 'Error: ' . $stmt->error];
            $stmt->close();
            return $result;
        }
    }

    private function updateTaskStatusMySQL($taskId, $status)
    {
        $stmt = $this->conn->prepare("UPDATE test.tasks SET status = ? WHERE id = ?");
        if (!$stmt) {
            return ['success' => false, 'error' => 'Database error: ' . $this->conn->error];
        }

        $stmt->bind_param("si", $status, $taskId);

        if ($stmt->execute()) {
            $result = ['success' => true, 'message' => 'Task updated successfully'];
            $stmt->close();
            return $result;
        } else {
            $result = ['success' => false, 'error' => 'Error: ' . $stmt->error];
            $stmt->close();
            return $result;
        }
    }

    private function deleteTaskMySQL($taskId)
    {
        $stmt = $this->conn->prepare("DELETE FROM test.tasks WHERE id = ?");
        if (!$stmt) {
            return ['success' => false, 'error' => 'Database error: ' . $this->conn->error];
        }

        $stmt->bind_param("i", $taskId);

        if ($stmt->execute()) {
            $result = ['success' => true, 'message' => 'Task deleted successfully'];
            $stmt->close();
            return $result;
        } else {
            $result = ['success' => false, 'error' => 'Error: ' . $stmt->error];
            $stmt->close();
            return $result;
        }
    }

    // ==================== XML METHODS ====================

    private function getTasksXML($userId)
    {
        $tasks = $this->xmlHandler->getTasks($userId);
        return $tasks ?: [];
    }

    private function addTaskXML($userId, $title, $description)
    {
        $taskId = $this->xmlHandler->getNextTaskId();

        $taskData = [
            'id' => $taskId,
            'user_id' => $userId,
            'title' => $title,
            'description' => $description,
            'status' => 'pending',
            'created_at' => date('c') // ISO 8601 format
        ];

        return array_merge(
            $this->xmlHandler->addTask($taskData),
            ['id' => $taskId]
        );
    }

    private function updateTaskStatusXML($taskId, $status)
    {
        return $this->xmlHandler->updateTask($taskId, ['status' => $status]);
    }

    private function deleteTaskXML($taskId)
    {
        return $this->xmlHandler->deleteTask($taskId);
    }
}
?>