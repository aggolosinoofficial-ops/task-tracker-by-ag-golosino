<?php
declare(strict_types=1);

class XMLStorageCore {
    private string $xmlDir;
    private string $usersFile = 'users.xml';
    private string $tasksFile = 'tasks.xml';
    private string $archiveFile = 'archive_tasks.xml';

    public function __construct(array $config = []) {
        $this->xmlDir = ($config['xml_dir'] ?? __DIR__ . DIRECTORY_SEPARATOR);
        if (!is_dir($this->xmlDir)) mkdir($this->xmlDir, 0755, true);
    }

    private function loadXML(string $filename): ?SimpleXMLElement {
        $filepath = $this->xmlDir . $filename;
        if (!file_exists($filepath)) return null;
        $content = file_get_contents($filepath);
        if (!$content) return null;
        return simplexml_load_string($content);
    }

    private function saveXML(SimpleXMLElement $xml, string $filename): bool {
        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;
        return (bool)$dom->save($this->xmlDir . $filename);
    }

    public function addUser(string $username, string $passwordHash, string $role = 'user'): array {
        $xml = $this->loadXML($this->usersFile) ?? new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><users></users>');
        $newId = 1;
        foreach ($xml->user as $user) {
            $id = (int)$user->attributes()->id;
            if ($id >= $newId) $newId = $id + 1;
        }
        $userNode = $xml->addChild('user');
        $userNode->addAttribute('id', (string)$newId);
        $userNode->addChild('username', $username);
        $userNode->addChild('password_hash', $passwordHash);
        $userNode->addChild('role', $role);
        $this->saveXML($xml, $this->usersFile);
        return ['success' => true, 'id' => $newId];
    }

    public function getUserByUsername(string $username): ?array {
        $xml = $this->loadXML($this->usersFile);
        if (!$xml) return null;
        foreach ($xml->user as $u) {
            if ((string)$u->username === $username) {
                return ['id' => (int)$u->attributes()->id, 'username' => (string)$u->username, 'role' => (string)$u->role];
            }
        }
        return null;
    }

    public function getUserById(int $userId): ?array {
        $xml = $this->loadXML($this->usersFile);
        if (!$xml) return null;
        foreach ($xml->user as $u) {
            if ((int)$u->attributes()->id === $userId) {
                return ['id' => (int)$u->attributes()->id, 'username' => (string)$u->username, 'role' => (string)$u->role];
            }
        }
        return null;
    }

    public function addTask(int $userId, string $title, string $description = ''): array {
        $xml = $this->loadXML($this->tasksFile) ?? new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tasks></tasks>');
        $newId = 1;
        foreach ($xml->task as $task) {
            $id = (int)$task->attributes()->id;
            if ($id >= $newId) $newId = $id + 1;
        }
        $t = $xml->addChild('task');
        $t->addAttribute('id', (string)$newId);
        $t->addAttribute('user_id', (string)$userId);
        $t->addChild('title', $title);
        $t->addChild('description', $description);
        $t->addChild('status', 'pending');
        $this->saveXML($xml, $this->tasksFile);
        return ['success' => true, 'id' => $newId];
    }

    public function getTasksByUser(int $userId, int $limit = 50, int $offset = 0): array {
        $xml = $this->loadXML($this->tasksFile);
        if (!$xml) return [];
        $tasks = [];
        $count = 0;
        foreach ($xml->task as $t) {
            if ((int)$t->attributes()->user_id === $userId) {
                if ($count >= $offset && count($tasks) < $limit) {
                    $tasks[] = ['id' => (int)$t->attributes()->id, 'title' => (string)$t->title, 'description' => (string)$t->description, 'status' => (string)$t->status];
                }
                $count++;
            }
        }
        return $tasks;
    }

    public function getArchivedTasks(int $userId, int $limit = 50, int $offset = 0): array {
        $xml = $this->loadXML($this->archiveFile);
        if (!$xml) return [];
        $tasks = [];
        $count = 0;
        foreach ($xml->task as $t) {
            if ((int)$t->attributes()->user_id === $userId) {
                if ($count >= $offset && count($tasks) < $limit) {
                    $tasks[] = ['id' => (int)$t->attributes()->id, 'title' => (string)$t->title, 'status' => (string)$t->status];
                }
                $count++;
            }
        }
        return $tasks;
    }

    public function updateTask(int $taskId, int $userId, string $title, string $description, string $status): bool {
        $xml = $this->loadXML($this->tasksFile);
        if (!$xml) return false;
        foreach ($xml->task as $t) {
            if ((int)$t->attributes()->id === $taskId && (int)$t->attributes()->user_id === $userId) {
                $t->title = $title;
                $t->description = $description;
                $t->status = $status;
                return $this->saveXML($xml, $this->tasksFile);
            }
        }
        return false;
    }

    public function deleteTask(int $taskId, int $userId): bool {
        $xml = $this->loadXML($this->tasksFile);
        if (!$xml) return false;
        foreach ($xml->task as $k => $t) {
            if ((int)$t->attributes()->id === $taskId && (int)$t->attributes()->user_id === $userId) {
                unset($xml->task[$k]);
                return $this->saveXML($xml, $this->tasksFile);
            }
        }
        return false;
    }

    public function archiveTask(int $taskId, int $userId): bool {
        $xml = $this->loadXML($this->tasksFile);
        if (!$xml) return false;
        $target = null;
        foreach ($xml->task as $k => $t) {
            if ((int)$t->attributes()->id === $taskId && (int)$t->attributes()->user_id === $userId) {
                $target = $t;
                unset($xml->task[$k]);
                break;
            }
        }
        if (!$target) return false;
        $this->saveXML($xml, $this->tasksFile);
        $arch = $this->loadXML($this->archiveFile) ?? new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tasks></tasks>');
        $n = $arch->addChild('task');
        $n->addAttribute('id', (string)$target->attributes()->id);
        $n->addAttribute('user_id', (string)$target->attributes()->user_id);
        $n->addChild('title', (string)$target->title);
        $n->addChild('status', (string)$target->status);
        return $this->saveXML($arch, $this->archiveFile);
    }

    public function restoreTask(int $taskId, int $userId): bool {
        $arch = $this->loadXML($this->archiveFile);
        if (!$arch) return false;
        $target = null;
        foreach ($arch->task as $k => $t) {
            if ((int)$t->attributes()->id === $taskId && (int)$t->attributes()->user_id === $userId) {
                $target = $t;
                unset($arch->task[$k]);
                break;
            }
        }
        if (!$target) return false;
        $this->saveXML($arch, $this->archiveFile);
        $xml = $this->loadXML($this->tasksFile) ?? new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tasks></tasks>');
        $n = $xml->addChild('task');
        $n->addAttribute('id', (string)$target->attributes()->id);
        $n->addAttribute('user_id', (string)$target->attributes()->user_id);
        $n->addChild('title', (string)$target->title);
        $n->addChild('status', (string)$target->status);
        return $this->saveXML($xml, $this->tasksFile);
    }
}
?>