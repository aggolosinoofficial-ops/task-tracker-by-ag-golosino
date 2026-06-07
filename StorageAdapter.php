<?php
declare(strict_types=1);

require_once 'xml_storage_core.php';

class StorageAdapter {
    private XMLStorageCore $storage;
    private array $config;

    /**
     * @param array $config Configuration settings (must include 'tasks_file')
     */
    public function __construct(array $config = []) {
        $this->config = $config;
        $this->storage = new XMLStorageCore($config);
    }

    /**
     * HELPER: Calculates the next available ID.
     */
    private function getNextTaskId(): int {
        $path = $this->config['tasks_file'] ?? (__DIR__ . '/data/tasks.xml');
        if (!file_exists($path)) return 1;

        $xml = simplexml_load_file($path);
        if (!$xml || !isset($xml->task)) return 1;

        $maxId = 0;
        foreach ($xml->task as $task) {
            $id = (int)$task->id;
            if ($id > $maxId) {
                $maxId = $id;
            }
        }
        return $maxId + 1;
    }

    private function verifyOwnership(int $resourceUserId, int $requestingUserId): void {
        if ($resourceUserId !== $requestingUserId) {
            throw new \RuntimeException('Unauthorized access: You do not own this resource.');
        }
    }

    // --- User Management ---

    public function registerUser(string $username, string $passwordHash, string $role = 'user'): array {
        return $this->storage->addUser($username, $passwordHash, $role);
    }

    public function getUserByUsername(string $username): ?array {
        return $this->storage->getUserByUsername($username);
    }

    public function getUserById(int $userId): ?array {
        return $this->storage->getUserById($userId);
    }

    // --- Task Management ---

   // In StorageAdapter.php
public function addTask(int $userId, string $title, string $description = '', int $requestingUserId = 0): array {
    $this->verifyOwnership($userId, $requestingUserId);
    
    // Get the integer ID
    $nextId = $this->getNextTaskId(); 
    
    // Pass it as an integer (no (string) cast needed)
    return $this->storage->addTask($nextId, $userId, $title, $description);
}

    public function getTasksByUser(int $userId, int $page, int $pageSize, int $requestingUserId): array {
        $this->verifyOwnership($userId, $requestingUserId);
        $offset = ($page - 1) * $pageSize;
        return [
            'tasks' => $this->storage->getTasksByUser($userId, $pageSize, $offset),
            'pagination' => ['page' => $page, 'pageSize' => $pageSize]
        ];
    }

    public function getArchivedTasks(int $userId, int $page, int $pageSize, int $requestingUserId): array {
        $this->verifyOwnership($userId, $requestingUserId);
        $offset = ($page - 1) * $pageSize;
        return [
            'tasks' => $this->storage->getArchivedTasks($userId, $pageSize, $offset),
            'pagination' => ['page' => $page, 'pageSize' => $pageSize]
        ];
    }

    public function updateTask(int $taskId, int $userId, string $title, string $description, string $status, int $requestingUserId): bool {
        $this->verifyOwnership($userId, $requestingUserId);
        return $this->storage->updateTask($taskId, $userId, $title, $description, $status);
    }

    public function deleteTask(int $taskId, int $userId, int $requestingUserId): bool {
        $this->verifyOwnership($userId, $requestingUserId);
        return $this->storage->deleteTask($taskId, $userId);
    }

    public function archiveTask(int $taskId, int $userId, int $requestingUserId): bool {
        $this->verifyOwnership($userId, $requestingUserId);
        return $this->storage->archiveTask($taskId, $userId);
    }

    public function restoreTask(int $taskId, int $userId, int $requestingUserId): bool {
        $this->verifyOwnership($userId, $requestingUserId);
        return $this->storage->restoreTask($taskId, $userId);
    }

    // --- Insights / Statistics ---

    public function getTaskStats(int $userId, int $requestingUserId): array {
        $this->verifyOwnership($userId, $requestingUserId);
        
        $stats = ['total' => 0, 'pending' => 0, 'completed' => 0];
        
        $path = $this->config['tasks_file'] ?? (__DIR__ . '/data/tasks.xml');
        
        if (file_exists($path)) {
            $xml = simplexml_load_file($path);
            if ($xml) {
                foreach ($xml->task as $task) {
                    if ((int)$task->user_id === $userId) {
                        $stats['total']++;
                        $status = (string)$task->status;
                        if (isset($stats[$status])) {
                            $stats[$status]++;
                        } else {
                            $stats[$status] = 1;
                        }
                    }
                }
            }
        }
        return $stats;
    }
}
?>