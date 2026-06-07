<?php

class XMLHandler
{
    private DOMDocument $dom;
    private string $filePath;

    /**
     * Constructor: Initializes the DOMDocument and loads the XML file.
     */
    public function __construct(string $filePath = __DIR__ . '/tasks.xml')
    {
        $this->filePath = $filePath;
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;

        if (file_exists($this->filePath)) {
            $this->dom->load($this->filePath);
        } else {
            $root = $this->dom->createElement('tasks');
            $this->dom->appendChild($root);
            $this->save();
        }
    }

    /**
     * Atomic Save: Protects against file corruption.
     */
    private function save(): bool
    {
        $tmpFile = $this->filePath . '.tmp';
        if ($this->dom->save($tmpFile)) {
            return rename($tmpFile, $this->filePath);
        }
        return false;
    }

    /**
     * Adds a new task to the XML structure.
     */
    public function addTask(array $data): bool
    {
        $task = $this->dom->createElement('task');
        foreach ($data as $key => $value) {
            $node = $this->dom->createElement($key, htmlspecialchars((string)$value));
            $task->appendChild($node);
        }
        $this->dom->documentElement->appendChild($task);
        return $this->save();
    }

    /**
     * Returns an array of all tasks found in the XML.
     */
    public function getTasks(): array
    {
        $tasks = [];
        foreach ($this->dom->getElementsByTagName('task') as $taskElement) {
            $task = [];
            foreach ($taskElement->childNodes as $node) {
                if ($node->nodeType === XML_ELEMENT_NODE) {
                    $task[$node->nodeName] = $node->nodeValue;
                }
            }
            $tasks[] = $task;
        }
        return $tasks;
    }

    /**
     * Updates an existing task by searching for its ID.
     */
    public function updateTask(int $id, array $updates): bool
    {
        foreach ($this->dom->getElementsByTagName('task') as $taskElement) {
            $idNode = $taskElement->getElementsByTagName('id')->item(0);
            if ($idNode && (int)$idNode->nodeValue === $id) {
                foreach ($updates as $key => $value) {
                    $node = $taskElement->getElementsByTagName($key)->item(0);
                    if ($node) {
                        $node->nodeValue = htmlspecialchars((string)$value);
                    }
                }
                return $this->save();
            }
        }
        return false;
    }

    /**
     * Deletes a task by searching for its ID.
     */
    public function deleteTask(int $id): bool
    {
        foreach ($this->dom->getElementsByTagName('task') as $taskElement) {
            $idNode = $taskElement->getElementsByTagName('id')->item(0);
            if ($idNode && (int)$idNode->nodeValue === $id) {
                $taskElement->parentNode->removeChild($taskElement);
                return $this->save();
            }
        }
        return false;
    }
} // End of Class

?>