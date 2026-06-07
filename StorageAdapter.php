<?php
declare(strict_types=1);

require_once 'xml_storage_core.php';

class StorageAdapter {
    private XMLStorageCore $storage;
    private array $config;

    /**
     * @param array $config Configuration settings
     */
    public function __construct(array $config = []) {
        $this->config = $config;
        $this->storage = new XMLStorageCore($config);
    }

    /**
     * Security: Verify that the user attempting to access data is the owner.
     */
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

    public function addTask(int $userId, string $title, string $description = '', int $requestingUserId = 0): array {
        $this->verifyOwnership($userId, $requestingUserId);
        return $this->storage->addTask($userId, $title, $description);
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

    /**
     * Calculates task statistics for the user's Insights page.
     */
    public function getTaskStats(int $userId, int $requestingUserId): array {
        $this->verifyOwnership($userId, $requestingUserId);
        
        $stats = ['total' => 0, 'pending' => 0, 'completed' => 0];
        
        // Load tasks XML to count statuses
        $path = (__DIR__ . '/data/tasks.xml');
        if (file_exists($path)) {
            $xml = simplexml_load_file($path);
            if ($xml) {
                foreach ($xml->task as $task) {
                    if ((int)$task->attributes()['user_id'] === $userId) {
                        $stats['total']++;
                        $status = (string)$task->status;
                        // Increment specific status if tracked
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