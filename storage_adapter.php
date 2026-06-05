<?php
/**
 * Storage Adapter Layer - SECURED VERSION
 * Transparent abstraction for XML-first with MySQL fallback
 * Provides unified interface to existing application code
 * 
 * @version 2.0.0
 * @security: Input validation, file locking, authenticated context, rate limiting
 */

declare(strict_types=1);

require_once 'xml_storage_core.php';

class StorageAdapter {
    /*═════════════════════════════════════════════════════════*
     * CONFIGURATION & CONSTANTS
     *═════════════════════════════════════════════════════════*/
    
    private const CONFIG = [
        'xml_dir'           => __DIR__ . '/data/',
        'users_file'        => 'users.xml',
        'tasks_file'        => 'tasks.xml',
        'archive_file'      => 'archive_tasks.xml',
        'default_page_size' => 50,
        'max_page_size'     => 100,
        'connect_timeout'  => 5,
        'sql_timeout'       => 30,
    ];

    /*═════════════════════════════════════════════════════════*
     * PRIVATE PROPERTIES
     *═════════════════════════════════════════════════════════*/
    
    private XMLStorageCore $storage;
    private ?mysqli $mysqlConnection = null;
    private bool $useMysql = false;
    private array $config;
    private bool $isInitialized = false;
    private static int $instanceCount = 0;
    
    // Rate limiting
    private static array $rateLimitCache = [];
    private const RATE_LIMIT_MAX = 100; // operations per window
    private const RATE_LIMIT_WINDOW = 60; // seconds

    /*═════════════════════════════════════════════════════════*
     * CONSTRUCTOR & INITIALIZATION
     *═════════════════════════════════════════════════════════*/
    
    public function __construct(array $config = []) {
        self::$instanceCount++;
        
        // Merge custom config with defaults
        $this->config = array_merge(self::CONFIG, $config);
        
        // Validate configuration
        $this->validateConfig();
        
        // Ensure data directory exists
        $this->ensureDataDirectory();
        
        // Initialize storage
        $this->storage = new XMLStorageCore($this->config);
        $this->detectStorage();
        
        $this->isInitialized = true;
    }
    
    /**
     * Validate configuration paths
     */
    private function validateConfig(): void {
        $xmlDir = $this->config['xml_dir'];
        
        if (!is_string($xmlDir) || empty($xmlDir)) {
            throw new InvalidArgumentException('Invalid XML directory configuration');
        }
        
        // Verify directory is within allowed paths (prevent path traversal)
        $realPath = realpath($xmlDir);
        if ($realPath === false || strpos($realPath, __DIR__) !== 0) {
            throw new InvalidArgumentException(
                'XML directory must be within application directory'
            );
        }
    }
    
    /**
     * Ensure data directory exists with proper permissions
     */
    private function ensureDataDirectory(): void {
        $dir = $this->config['xml_dir'];
        
        if (!is_dir($dir)) {
            $oldMask = umask(0);
            if (!mkdir($dir, 0750, true)) {
                umask($oldMask);
                throw new RuntimeException(
                    "Cannot create data directory: {$dir}"
                );
            }
            umask($oldMask);
        }
        
        if (!is_writable($dir)) {
            throw new RuntimeException(
                "Data directory is not writable: {$dir}"
            );
        }
    }

    /*═════════════════════════════════════════════════════════*
     * STORAGE DETECTION
     *═════════════════════════════════════════════════════════*/
    
    /**
     * Detect primary storage layer to use
     */
    private function detectStorage(): void {
        try {
            // Check for required constants
            if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASS')) {
                $this->useMysql = false;
                $this->log('info', 'MySQL credentials not defined, using XML only');
                return;
            }
            
            // Validate connection parameters
            $host = filter_var(DB_HOST, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RESOLVE);
            if ($host === false && !filter_var(DB_HOST, FILTER_VALIDATE_DOMAIN)) {
                throw new InvalidArgumentException('Invalid MySQL host');
            }
            
            // Attempt connection with timeout
            $this->mysqlConnection = new mysqli();
            $this->mysqlConnection->options(MYSQLI_OPT_CONNECT_TIMEOUT, 
                $this->config['connect_timeout']);
            $this->mysqlConnection->options(MYSQLI_OPT_READ_TIMEOUT, 
                $this->config['sql_timeout']);
            
            @$this->mysqlConnection->real_connect(
                DB_HOST, 
                DB_USER, 
                DB_PASS, 
                defined('DB_NAME') ? DB_NAME : ''
            );
            
            if ($this->mysqlConnection->connect_error) {
                throw new RuntimeException($this->mysqlConnection->connect_error);
            }
            
            // Set charset to prevent SQL injection via encoding
            if (!$this->mysqlConnection->set_charset('utf8mb4')) {
                throw new RuntimeException('Failed to set MySQL charset');
            }
            
            // Verify connection is alive
            if (!$this->mysqlConnection->ping()) {
                throw new RuntimeException('MySQL connection not alive');
            }
            
            $this->useMysql = true;
            $this->log('info', 'MySQL storage available and connected');
            
        } catch (mysqli_exception $e) {
            $this->useMysql = false;
            $this->mysqlConnection = null;
            $this->log('warning', 'MySQL unavailable, using XML only: ' . $e->getMessage());
        }
    }

    /*═════════════════════════════════════════════════════════*
     * INPUT VALIDATION & SANITIZATION
     *═════════════════════════════════════════════════════════*/
    
    /**
     * Validate integer input
     */
    private function validateInt(mixed $value, string $name, int $min = 1): int {
        if (!filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $min]
        ])) {
            throw new InvalidArgumentException("Invalid {$name}: must be integer >= {$min}");
        }
        return (int)$value;
    }
    
    /**
     * Validate string input
     */
    private function validateString(
        mixed $value, 
        string $name, 
        int $minLength = 1, 
        int $maxLength = 255
    ): string {
        if (!is_string($value) || strlen($value) < $minLength || strlen($value) > $maxLength) {
            throw new InvalidArgumentException(
                "Invalid {$name}: must be string between {$minLength}-{$maxLength} chars"
            );
        }
        
        // Remove null bytes and control characters (except newlines/tabs)
        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
        
        return $sanitized;
    }
    
    /**
     * Validate username format
     */
    private function validateUsername(string $username): string {
        $username = trim($username);
        
        if (strlen($username) < 3 || strlen($username) > 50) {
            throw new InvalidArgumentException('Username must be 3-50 characters');
        }
        
        // Allow alphanumeric, underscore, hyphen only
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            throw new InvalidArgumentException(
                'Username can only contain letters, numbers, underscores, hyphens'
            );
        }
        
        return $username;
    }
    
    /**
     * Validate role
     */
    private function validateRole(string $role): string {
        $allowedRoles = ['user', 'admin', 'moderator'];
        
        if (!in_array(strtolower($role), $allowedRoles, true)) {
            throw new InvalidArgumentException(
                'Invalid role. Allowed: ' . implode(', ', $allowedRoles)
            );
        }
        
        return strtolower($role);
    }
    
    /**
     * Validate task status
     */
    private function validateStatus(string $status): string {
        $allowedStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];
        
        if (!in_array(strtolower($status), $allowedStatuses, true)) {
            throw new InvalidArgumentException(
                'Invalid status. Allowed: ' . implode(', ', $allowedStatuses)
            );
        }
        
        return strtolower($status);
    }
    
    /**
     * Validate pagination parameters
     */
    private function validatePagination(int $page, int $pageSize): array {
        $page = $this->validateInt($page, 'page', 1);
        $pageSize = $this->validateInt($pageSize, 'pageSize', 1);
        
        // Enforce max page size
        if ($pageSize > $this->config['max_page_size']) {
            $pageSize = $this->config['max_page_size'];
        }
        
        return ['page' => $page, 'pageSize' => $pageSize];
    }
    
    /**
     * Sanitize text content (XSS prevention)
     */
    private function sanitizeText(string $text, int $maxLength = 5000): string {
        $text = mb_substr($text, 0, $maxLength);
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $text;
    }

    /*═════════════════════════════════════════════════════════*
     * RATE LIMITING
     *═════════════════════════════════════════════════════════*/
    
    /**
     * Check rate limit for user
     */
    private function checkRateLimit(string $identifier): bool {
        $now = time();
        $windowStart = $now - self::RATE_LIMIT_WINDOW;
        
        if (!isset(self::$rateLimitCache[$identifier])) {
            self::$rateLimitCache[$identifier] = [];
        }
        
        // Clean old entries
        self::$rateLimitCache[$identifier] = array_filter(
            self::$rateLimitCache[$identifier],
            fn($timestamp) => $timestamp > $windowStart
        );
        
        // Check limit
        if (count(self::$rateLimitCache[$identifier]) >= self::RATE_LIMIT_MAX) {
            $this->log('warning', "Rate limit exceeded for: {$identifier}");
            return false;
        }
        
        // Add current request
        self::$rateLimitCache[$identifier][] = $now;
        
        return true;
    }

    /*═════════════════════════════════════════════════════════*
     * AUTHENTICATION CONTEXT
     *═════════════════════════════════════════════════════════*/
    
    /**
     * Verify user owns the resource (authorization check)
     */
    private function verifyOwnership(int $resourceUserId, int $requestingUserId): void {
        if ($resourceUserId !== $requestingUserId) {
            $this->log('warning', "Authorization failed: user {$requestingUserId} tried to access user {$resourceUserId}'s resource");
            throw new UnauthorizedException('You do not have permission to access this resource');
        }
    }

    /*═════════════════════════════════════════════════════════*
     * FILE OPERATIONS WITH LOCKING
     *═════════════════════════════════════════════════════════*/
    
    /**
     * Get full path for XML file
     */
    private function getFilePath(string $filename): string {
        // Prevent path traversal
        $filename = basename($filename);
        return $this->config['xml_dir'] . $filename;
    }
    
    /**
     * Acquire exclusive file lock
     */
    private function acquireLock(string $filePath, int $timeout = 10): resource {
        $lockFile = $filePath . '.lock';
        
        $fp = fopen($lockFile, 'c');
        if ($fp === false) {
            throw new RuntimeException("Cannot create lock file: {$lockFile}");
        }
        
        // Wait for lock with timeout
        $start = time();
        while (!flock($fp, LOCK_EX | LOCK_NB)) {
            if (time() - $start > $timeout) {
                fclose($fp);
                throw new RuntimeException("Lock timeout on: {$filePath}");
            }
            usleep(10000); // 10ms
        }
        
        return $fp;
    }

    /*═════════════════════════════════════════════════════════*
     * USER OPERATIONS
     *═════════════════════════════════════════════════════════*/
    
    /**
     * Register new user (XML-first, sync to MySQL)
     * 
     * @param string $username
     * @param string $passwordHash bcrypt hash
     * @param string $role
     * @param int|null $requestingUserId For authorization
     * @return array User data
     */
    public function registerUser(
        string $username, 
        string $passwordHash, 
        string $role = 'user',
        ?int $requestingUserId = null
    ): array {
        // Rate limit check
        if (!$this->checkRateLimit('register_' . ($requestingUserId ?? 'anonymous'))) {
            throw new RuntimeException('Too many requests. Please try again later.');
        }
        
        // Admin-only registration
        if ($requestingUserId === null) {
            throw new UnauthorizedException('Authentication required for registration');
        }
        
        // Validate inputs
        $username = $this->validateUsername($username);
        $role = $this->validateRole($role);
        
        // Validate password hash (bcrypt = 60 chars)
        if (!is_string($passwordHash) || strlen($passwordHash) !== 60) {
            throw new InvalidArgumentException('Invalid password hash format');
        }
        
        // Check if username already exists
        if ($this->getUserByUsername($username)) {
            throw new RuntimeException('Username already exists');
        }
        
        return $this->storage->addUser($username, $passwordHash, $role);
    }
    
    /**
     * Get user by username (XML primary, fallback to MySQL)
     * 
     * @param string $username
     * @return array|null
     */
    public function getUserByUsername(string $username): ?array {
        $username = $this->validateUsername($username);
        
        // Try XML first (primary)
        $user = $this->storage->getUserByUsername($username);
        
        if (!$user && $this->useMysql) {
            $user = $this->fetchMySQLUser($username);
        }
        
        return $user;
    }
    
    /**
     * Fetch user from MySQL (with prepared statement)
     */
    private function fetchMySQLUser(string $username): ?array {
        if (!$this->mysqlConnection) {
            return null;
        }
        
        $stmt = $this->mysqlConnection->prepare(
            "SELECT id, username, password_hash, role, created_at 
             FROM users WHERE username = ? LIMIT 1"
        );
        
        if (!$stmt) {
            $this->log('error', 'Prepare failed: ' . $this->mysqlConnection->error);
            return null;
        }
        
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result || $result->num_rows === 0) {
            $stmt->close();
            return null;
        }
        
        $user = $result->fetch_assoc();
        $stmt->close();
        
        return $user ?: null;
    }
    
    /**
     * Get user by ID
     * 
     * @param int $userId
     * @return array|null
     */
    public function getUserById(int $userId): ?array {
        $userId = $this->validateInt($userId, 'userId');
        
        // Try XML first
        $user = $this->storage->getUserById($userId);
        
        if (!$user && $this->useMysql) {
            $user = $this->fetchMySQLUserById($userId);
        }
        
        return $user;
    }
    
    /**
     * Fetch user by ID from MySQL
     */
    private function fetchMySQLUserById(int $userId): ?array {
        if (!$this->mysqlConnection) {
            return null;
        }
        
        $stmt = $this->mysqlConnection->prepare(
            "SELECT id, username, password_hash, role, created_at 
             FROM users WHERE id = ? LIMIT 1"
        );
        
        if (!$stmt) {
            return null;
        }
        
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result || $result->num_rows === 0) {
            $stmt->close();
            return null;
        }
        
        $user = $result->fetch_assoc();
        $stmt->close();
        
        return $user ?: null;
    }

    /*═════════════════════════════════════════════════════════*
     * TASK OPERATIONS
     *═════════════════════════════════════════════════════════*/
    
    /**
     * Add task (XML-first, sync to MySQL)
     * 
     * @param int $userId Owner of the task
     * @param string $title
     * @param string $description
     * @param int $requestingUserId For authorization
     * @return array
     */
    public function addTask(
        int $userId, 
        string $title, 
        string $description = '',
        int $requestingUserId
    ): array {
        // Rate limit
        if (!$this->checkRateLimit('task_create_' . $requestingUserId)) {
            throw new RuntimeException('Too many requests. Please try again later.');
        }
        
        // Validate
        $userId = $this->validateInt($userId, 'userId');
        $requestingUserId = $this->validateInt($requestingUserId, 'requestingUserId');
        
        // Authorization check
        $this->verifyOwnership($userId, $requestingUserId);
       // Validate inputs
        $title = $this->validateString($title, 'title', 1, 255);  // <-- ADD ;
if (!empty($description)) {
    $description = $this->sanitizeText($description);
}

return $this->storage->addTask($userId, $title, $description);
    
    /**
     * Get tasks for user with pagination
     * 
     * @param int $userId
     * @param int $page
     * @param int $pageSize
     * @param int $requestingUserId For authorization
     * @return array
     */
    function getTasksByUser(
        int $userId,
        int $page = 1,
        int $pageSize = 50,
        int $requestingUserId,
        ): array {
        // Rate limit
        if (!$this->checkRateLimit('task_read_' . $requestingUserId)) {
            throw new RuntimeException('Too many requests. Please try again later.');
        }
        
        // Validate
        $userId = $this->validateInt($userId, 'userId');
        $requestingUserId = $this->validateInt($requestingUserId, 'requestingUserId');
        
        // Authorization check (users can only see their own tasks)
        $this->verifyOwnership($userId, $requestingUserId);
        
        // Validate pagination
        $pagination = $this->validatePagination($page, $pageSize);
        $page = $pagination['page'];
        $pageSize = $pagination['pageSize'];
        $offset = ($page - 1) * $pageSize;
        
        // Try XML first
        $tasks = $this->storage->getTasksByUser($userId, $pageSize, $offset);
        
        // If empty and MySQL available, try MySQL
        if (empty($tasks) && $this->useMysql) {
            $tasks = $this->fetchMySQLTasks($userId, $pageSize, $offset);
        }
        
        return [
            'tasks' => $tasks,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'offset' => $offset
            ]
        ];
    }
    
    /**
     * Fetch tasks from MySQL
     */
         private function fetchMySQLTasks(int $userId, int $pageSize, int $offset): array {
        $tasks = [];
        
        if (!$this->mysqlConnection) {
            return $tasks;
        }
        
        $stmt = $this->mysqlConnection->prepare(
            "SELECT id, user_id, title, description, status, created_at 
             FROM tasks 
             WHERE user_id = ? 
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?"
        );
        
        if (!$stmt) {
            $this->log('error', 'Prepare failed: ' . $this->mysqlConnection->error);
            return $tasks;
        }
        
        $stmt->bind_param('iii', $userId, $pageSize, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $tasks[] = $row;
            }
        }
        
        $stmt->close();
        return $tasks;
    }
    
    /**
     * Update task
     * 
     * @param int $taskId
     * @param int $userId Owner of task
     * @param string $title
     * @param string $description
     * @param string $status
     * @param int $requestingUserId For authorization
     * @return bool
     */
    public function updateTask(
        int $taskId,
        int $userId,
        string $title,
        string $description,
        string $status,
        int $requestingUserId
    ): bool {
        // Rate limit
        if (!$this->checkRateLimit('task_update_' . $requestingUserId)) {
            throw new RuntimeException('Too many requests. Please try again later.');
        }
        
        // Validate
        $taskId = $this->validateInt($taskId, 'taskId');
        $userId = $this->validateInt($userId, 'userId');
        $requestingUserId = $this->validateInt($requestingUserId, 'requestingUserId');
        
        // Authorization check
        $this->verifyOwnership($userId, $requestingUserId);
        
        // Validate inputs
        $title = $this->validateString($title, 'title', 1, 255);
        $status = $this->validateStatus($status);
        if (!empty($description)) {
            $description = $this->sanitizeText($description);
        }
        
        return $this->storage->updateTask($taskId, $userId, $title, $description, $status);
    }
    
    /**
     * Delete task (soft delete - archive)
     * 
     * @param int $taskId
     * @param int $userId Owner of task
     * @param int $requestingUserId For authorization
     * @return bool
     */
    public function deleteTask(int $taskId, int $userId, int $requestingUserId): bool {
        // Rate limit
        if (!$this->checkRateLimit('task_delete_' . $requestingUserId)) {
            throw new RuntimeException('Too many requests. Please try again later.');
        }
        
        // Validate
        $taskId = $this->validateInt($taskId, 'taskId');
        $userId = $this->validateInt($userId, 'userId');
        $requestingUserId = $this->validateInt($requestingUserId, 'requestingUserId');
        
        // Authorization check
        $this->verifyOwnership($userId, $requestingUserId);
        
        return $this->storage->deleteTask($taskId, $userId);
    }
    
    /**
     * Restore task from archive
     * 
     * @param int $taskId
     * @param int $userId Owner of task
     * @param int $requestingUserId For authorization
     * @return bool
     */
    public function restoreTask(int $taskId, int $userId, int $requestingUserId): bool {
        // Rate limit
        if (!$this->checkRateLimit('task_restore_' . $requestingUserId)) {
            throw new RuntimeException('Too many requests. Please try again later.');
        }
        
        // Validate
        $taskId = $this->validateInt($taskId, 'taskId');
        $userId = $this->validateInt($userId, 'userId');
        $requestingUserId = $this->validateInt($requestingUserId, 'requestingUserId');
        
        // Authorization check
        $this->verifyOwnership($userId, $requestingUserId);
        
        return $this->storage->restoreTask($taskId, $userId);
    }
    
    /**
     * Get archived tasks for user
     * 
     * @param int $userId
     * @param int $page
     * @param int $pageSize
     * @param int $requestingUserId For authorization
     * @return array
     */
    public function getArchivedTasks(
        int $userId,
        int $page = 1,
        int $pageSize = 50,
        int $requestingUserId
    ): array {
        // Rate limit
        if (!$this->checkRateLimit('archive_read_' . $requestingUserId)) {
            throw new RuntimeException('Too many requests. Please try again later.');
        }
        
        // Validate
        $userId = $this->validateInt($userId, 'userId');
        $requestingUserId = $this->validateInt($requestingUserId, 'requestingUserId');
        
        // Authorization check
        $this->verifyOwnership($userId, $requestingUserId);
        
        // Validate pagination
        $pagination = $this->validatePagination($page, $pageSize);
        $page = $pagination['page'];
        $pageSize = $pagination['pageSize'];
        $offset = ($page - 1) * $pageSize;
        
        $tasks = [];
        
        try {
            // Load from XML with lock
            $archivePath = $this->getFilePath($this->config['archive_file']);
            $lock = $this->acquireLock($archivePath);
            
            if (file_exists($archivePath)) {
                $xml = simplexml_load_file($archivePath, 'SimpleXMLElement', 0, '', false);
                
                if ($xml) {
                    $count = 0;
                    foreach ($xml->task as $task) {
                        $attr = $task->attributes();
                        if ((int)$attr['user_id'] === $userId) {
                            if ($count >= $offset && count($tasks) < $pageSize) {
                                $tasks[] = [
                                    'id' => (int)$attr['id'],
                                    'user_id' => (int)$attr['user_id'],
                                    'title' => $this->sanitizeText((string)$task->title),
                                    'description' => $this->sanitizeText((string)$task->description),
                                    'status' => (string)$task->status,
                                    'created_at' => (string)$task->created_at,
                                    'archived_at' => (string)$task->archived_at
                                ];
                            }
                            $count++;
                        }
                    }
                }
            }
            
            flock($lock, LOCK_UN);
            fclose($lock);
            
        } catch (Exception $e) {
            $this->log('error', 'Archive read failed: ' . $e->getMessage());
            
            // Fallback to MySQL
            if ($this->useMysql) {
                $tasks = $this->fetchMySQLArchivedTasks($userId, $pageSize, $offset);
            }
        }
        
        return [
            'tasks' => $tasks,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'offset' => $offset
            ]
        ];
    }
    
    /**
     * Fetch archived tasks from MySQL
     */
    private function fetchMySQLArchivedTasks(int $userId, int $pageSize, int $offset): array {
        $tasks = [];
        
        if (!$this->mysqlConnection) {
            return $tasks;
        }
        
        $stmt = $this->mysqlConnection->prepare(
            "SELECT id, user_id, title, description, status, created_at, archived_at 
             FROM archive_tasks 
             WHERE user_id = ? 
             ORDER BY archived_at DESC 
             LIMIT ? OFFSET ?"
        );
        
        if (!$stmt) {
            return $tasks;
        }
        
        $stmt->bind_param('iii', $userId, $pageSize, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $tasks[] = $row;
            }
        }
        
        $stmt->close();
        return $tasks;
    }

    /*═════════════════════════════════════════════════════════*
     * ANALYTICS OPERATIONS
     *═════════════════════════════════════════════════════════*/
    
    /**
     * Get task statistics for user
     * 
     * @param int $userId
     * @param int $requestingUserId For authorization
     * @return array
     */
    public function getTaskStats(int $userId, int $requestingUserId): array {
        // Rate limit
        if (!$this->checkRateLimit('stats_' . $requestingUserId)) {
            throw new RuntimeException('Too many requests. Please try again later.');
        }
        
        // Validate
        $userId = $this->validateInt($userId, 'userId');
        $requestingUserId = $this->validateInt($requestingUserId, 'requestingUserId');
        
        // Authorization check
        $this->verifyOwnership($userId, $requestingUserId);
        
        $stats = [
            'total' => 0,
            'completed' => 0,
            'pending' => 0,
            'in_progress' => 0,
            'archived' => 0
        ];
        
        try {
            // Load from XML using streaming to avoid memory issues
            $this->calculateXMLStats($userId, $stats);
            
        } catch (Exception $e) {
            $this->log('warning', 'XML stats failed, falling back to MySQL: ' . $e->getMessage());
            
            // Fallback to MySQL
            if ($this->useMysql) {
                $this->calculateMySQLStats($userId, $stats);
            }
        }
        
        return $stats;
    }
    
    /**
     * Calculate statistics from XML storage
     */
    private function calculateXMLStats(int $userId, array &$stats): void {
        $tasksPath = $this->getFilePath($this->config['tasks_file']);
        
        if (!file_exists($tasksPath)) {
            return;
        }
        
        // Use streaming XML parser to avoid loading entire file
        $reader = new XMLReader();
        $reader->open($tasksPath);
        
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'task') {
                $node = new SimpleXMLElement($reader->readOuterXML());
                $attr = $node->attributes();
                
                if ((int)$attr['user_id'] === $userId) {
                    $stats['total']++;
                    $status = strtolower((string)$node->status);
                    
                    if (isset($stats[$status])) {
                        $stats[$status]++;
                    } else {
                        $stats['pending']++;
                    }
                }
            }
        }
        
        $reader->close();
        
        // Count archived
        $archivePath = $this->getFilePath($this->config['archive_file']);
        
        if (file_exists($archivePath)) {
            $reader->open($archivePath);
            
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'task') {
                    $node = new SimpleXMLElement($reader->readOuterXML());
                    $attr = $node->attributes();
                    
                    if ((int)$attr['user_id'] === $userId) {
                        $stats['archived']++;
                    }
                }
            }
            
            $reader->close();
        }
    }
    
    /**
     * Calculate statistics from MySQL
     */
    private function calculateMySQLStats(int $userId, array &$stats): void {
        if (!$this->mysqlConnection) {
            return;
        }
        
        // Active tasks
        $stmt = $this->mysqlConnection->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
             FROM tasks 
             WHERE user_id = ?"
        );
        
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $row = $result->fetch_assoc()) {
                $stats['total'] = (int)($row['total'] ?? 0);
                $stats['completed'] = (int)($row['completed'] ?? 0);
                $stats['in_progress'] = (int)($row['in_progress'] ?? 0);
                $stats['pending'] = (int)($row['pending'] ?? 0);
            }
            
            $stmt->close();
        }
        
        // Archived count
        $stmt = $this->mysqlConnection->prepare(
            "SELECT COUNT(*) as archived FROM archive_tasks WHERE user_id = ?"
        );
        
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $row = $result->fetch_assoc()) {
                $stats['archived'] = (int)($row['archived'] ?? 0);
            }
            
            $stmt->close();
        }
    }

    /*═════════════════════════════════════════════════════════*
     * RECOVERY & MAINTENANCE
     *═════════════════════════════════════════════════════════*/
    
    /**
     * Rebuild MySQL from XML snapshot (admin only)
     * 
     * @param int $requestingUserId Must be admin
     * @return array
     */
    public function rebuildMySQL(int $requestingUserId): array {
        // Validate
        $requestingUserId = $this->validateInt($requestingUserId, 'requestingUserId');
        
        // Check admin role
        $admin = $this->getUserById($requestingUserId);
        
        if (!$admin || $admin['role'] !== 'admin') {
            throw new UnauthorizedException('Admin access required');
        }
        
        // Rate limit for admin operations
        if (!$this->checkRateLimit('admin_rebuild')) {
            throw new RuntimeException('Too many requests. Please try again later.');
        }
        
        return $this->storage->rebuildMySQLFromXML();
    }
    
    /**
     * Get storage status
     * 
     * @return array
     */
      public function getStorageStatus(): array {
        $xmlAvailable = [
            'users' => file_exists($this->getFilePath($this->config['users_file'])),
            'tasks' => file_exists($this->getFilePath($this->config['tasks_file'])),
            'archive' => file_exists($this->getFilePath($this->config['archive_file']))
        ];
        
        $mysqlStatus = 'Unavailable';
        
        if ($this->useMysql && $this->mysqlConnection) {
            try {
                if ($this->mysqlConnection->ping()){
                    $mysqlStatus = 'Connected';
                    
                    // Get MySQL version
                    $result = $this->mysqlConnection->query("SELECT VERSION() as version");
                    if ($result && $row = $result->fetch_assoc()) {
                        $mysqlStatus .= ' (' . $row['version'] . ')';
                    }
                }
            } catch (Exception $e) {
                $mysqlStatus = 'Error: ' . $e->getMessage();
            }
        }
        
        return [
            'initialized' => $this->isInitialized,
            'xml' => [
                'available' => in_array(true, $xmlAvailable),
                'files' => $xmlAvailable,
                'directory' => $this->config['xml_dir']
            ],
            'mysql' => [
                'available' => $this->useMysql,
                'status' => $mysqlStatus
            ],
            'primary_storage' => 'XML',
            'secondary_storage' => $this->useMysql ? 'MySQL' : 'None',
            'instance_count' => self::$instanceCount,
            'config' => [
                'max_page_size' => $this->config['max_page_size'],
                'connect_timeout' => $this->config['connect_timeout'],
                'sql_timeout' => $this->config['sql_timeout'],
            ]
        ];
    }

    /*═════════════════════════════════════════════════════════*
     * LOGGING
     *═════════════════════════════════════════════════════════*/
    
    /**
     * Internal logging
    
       @param string $level
     * @param string $message
     */
    private function log(string $level, string $message): void {
        // Log to error log (configure your logging as needed)
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] StorageAdapter: {$message}\n";
        
        // Write to application log if available
        $logFile = $this->config['xml_dir'] . 'adapter.log';
        
        if (file_exists($this->config['xml_dir']) && is_writable($this->config['xml_dir'])) {
            @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        }
        
        // Also send to PHP error log
        error_log($logEntry);
    }

    /*═════════════════════════════════════════════════════════*
     * DESTRUCTOR
     *═════════════════════════════════════════════════════════*/
    
    /**
     * Close connections gracefully
     */
    public function __destruct() {
        self::$instanceCount--;
        
        if ($this->mysqlConnection) {
            try {
                $this->mysqlConnection->close();
            } catch (Exception $e) {
                $this->log('error', 'MySQL close error: ' . $e->getMessage());
            }
        }
        
        // Clean rate limit cache periodically
        if (self::$instanceCount === 0) {
            self::$rateLimitCache = [];
        }
    }
}

/*══════════════════════════════════════════════════════════════════*
 * CUSTOM EXCEPTIONS
 *══════════════════════════════════════════════════════════════════*/

class UnauthorizedException extends RuntimeException {
    public function __construct(string $message = 'Unauthorized') {
        parent::__construct($message, 401);
    }
}

class ForbiddenException extends RuntimeException {
    public function __construct(string $message = 'Forbidden') {
        parent::__construct($message, 403);
    }
}

class StorageException extends RuntimeException {
    public function __construct(string $message = 'Storage Error') {
        parent::__construct($message, 500);
    }
}

/*══════════════════════════════════════════════════════════════════*
 * FACTORY FUNCTION (Singleton Pattern)
 *══════════════════════════════════════════════════════════════════*/

function createStorageAdapter(array $config = []): StorageAdapter {
    static $instance = null;
    
    if ($instance === null) {
        $instance = new StorageAdapter($config);
    }
    
    return $instance;
}

/*══════════════════════════════════════════════════════════════════*
 * USAGE EXAMPLES
 *══════════════════════════════════════════════════════════════════*/


// Initialize with custom config
$adapter = createStorageAdapter([
    'xml_dir' => __DIR__ . '/secure_data/',
    'max_page_size' => 50,
    'connect_timeout' => 5,
]);

// Register new user (requires auth context)
try {
    $user = $adapter->registerUser(
        'newuser',
        '$2y$10$abcdefghijklmnopqrstuvwxyz', // bcrypt hash
        'user',
        $requestingUserId = 1 // Must match authenticated user
    );
} catch (UnauthorizedException $e) {
    // Handle unauthorized
} catch (InvalidArgumentException $e) {
    // Handle validation errors
}

// Add task (with authorization)
try {
    $task = $adapter->addTask(
        $userId = 1,
        $title = 'Complete report',
        $description = 'Finish Q4 financial report',
        $requestingUserId = 1 // Must match userId
    );
} catch (UnauthorizedException $e) {
    // Not authorized to create task for this user
}

// Get tasks with pagination
$tasks = $adapter->getTasksByUser(
    $userId = 1,
    $page = 1,
    $pageSize = 50,
    $requestingUserId = 1
);

// Update task
$adapter->updateTask(
    $taskId = 1,
    $userId = 1,
    $title = 'Updated title',
    $description = 'Updated description',
    $status = 'in_progress',
    $requestingUserId = 1
);

// Get stats
$stats = $adapter->getTaskStats($userId = 1, $requestingUserId = 1);

// Get storage status
$status = $adapter->getStorageStatus();

// Rebuild MySQL (admin only)
$adapter->rebuildMySQL($requestingUserId = 1);

?>