<?php
/**
 * XML Handler for Local Database
 * Provides unified interface to work with both MySQL and XML storage
 * Includes XML validation against XSD schema
 */

class XMLTaskHandler
{
    private $xmlFilePath;
    private $xsdFilePath;
    private $dom;

    public function __construct($xmlPath = null)
    {
        $this->xmlFilePath = $xmlPath ?? __DIR__ . '/tasks.xml';
        $this->xsdFilePath = __DIR__ . '/tasks.xsd';
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->loadXML();
    }

    /**
     * Load XML file or create new if doesn't exist
     */
    private function loadXML()
    {
        if (file_exists($this->xmlFilePath)) {
            $this->dom->load($this->xmlFilePath);
        } else {
            $this->dom->appendChild($this->dom->createElement('tasks'));
            $this->saveXML();
        }
    }

    /**
     * Save XML file to disk
     */
    public function saveXML()
    {
        $this->dom->formatOutput = true;
        return $this->dom->save($this->xmlFilePath);
    }

    /**
     * Validate XML against XSD schema
     */
    public function validateXML()
    {
        if (!file_exists($this->xsdFilePath)) {
            return ['valid' => false, 'error' => 'XSD schema file not found'];
        }

        libxml_use_internal_errors(true);
        $valid = $this->dom->schemaValidate($this->xsdFilePath);

        if (!$valid) {
            $errors = libxml_get_errors();
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = trim($error->message);
            }
            libxml_clear_errors();
            return ['valid' => false, 'errors' => $errorMessages];
        }

        return ['valid' => true];
    }

    /**
     * Add a task to XML
     */
    public function addTask($taskData)
    {
        // Validate required fields
        $required = ['id', 'user_id', 'title', 'status', 'created_at'];
        foreach ($required as $field) {
            if (!isset($taskData[$field])) {
                return ['success' => false, 'error' => "Missing required field: $field"];
            }
        }

        $root = $this->dom->documentElement;
        $taskElement = $this->dom->createElement('task');

        // Create task elements
        foreach ($taskData as $key => $value) {
            $element = $this->dom->createElement($key, htmlspecialchars((string) $value, ENT_XML1));
            $taskElement->appendChild($element);
        }

        $root->appendChild($taskElement);

        // Validate after adding
        $validation = $this->validateXML();
        if (!$validation['valid']) {
            $root->removeChild($taskElement);
            return ['success' => false, 'error' => 'Validation failed: ' . implode('; ', $validation['errors'] ?? [])];
        }

        if ($this->saveXML()) {
            return ['success' => true, 'message' => 'Task added successfully'];
        }
        return ['success' => false, 'error' => 'Failed to save XML'];
    }

    /**
     * Get all tasks from XML
     */
    public function getTasks($userId = null)
    {
        $tasks = [];
        $taskElements = $this->dom->getElementsByTagName('task');

        foreach ($taskElements as $taskElement) {
            $task = $this->elementToArray($taskElement);

            // Filter by user_id if provided
            if ($userId === null || intval($task['user_id']) === intval($userId)) {
                $tasks[] = $task;
            }
        }

        return $tasks;
    }

    /**
     * Get single task by ID
     */
    public function getTaskById($taskId)
    {
        $taskElements = $this->dom->getElementsByTagName('task');

        foreach ($taskElements as $taskElement) {
            $task = $this->elementToArray($taskElement);
            if (intval($task['id']) === intval($taskId)) {
                return $task;
            }
        }

        return null;
    }

    /**
     * Update task in XML
     */
    public function updateTask($taskId, $updates)
    {
        $taskElements = $this->dom->getElementsByTagName('task');
        $found = false;

        foreach ($taskElements as $taskElement) {
            $idElement = $taskElement->getElementsByTagName('id')->item(0);
            if ($idElement && intval($idElement->nodeValue) === intval($taskId)) {
                // Update each field
                foreach ($updates as $key => $value) {
                    $elements = $taskElement->getElementsByTagName($key);
                    if ($elements->length > 0) {
                        $elements->item(0)->nodeValue = htmlspecialchars((string) $value, ENT_XML1);
                    } else {
                        $newElement = $this->dom->createElement($key, htmlspecialchars((string) $value, ENT_XML1));
                        $taskElement->appendChild($newElement);
                    }
                }
                $found = true;
                break;
            }
        }

        if (!$found) {
            return ['success' => false, 'error' => 'Task not found'];
        }

        // Validate after updating
        $validation = $this->validateXML();
        if (!$validation['valid']) {
            return ['success' => false, 'error' => 'Validation failed: ' . implode('; ', $validation['errors'] ?? [])];
        }

        if ($this->saveXML()) {
            return ['success' => true, 'message' => 'Task updated successfully'];
        }
        return ['success' => false, 'error' => 'Failed to save XML'];
    }

    /**
     * Delete task from XML
     */
    public function deleteTask($taskId)
    {
        $taskElements = $this->dom->getElementsByTagName('task');
        $found = false;

        for ($i = $taskElements->length - 1; $i >= 0; $i--) {
            $taskElement = $taskElements->item($i);
            $idElement = $taskElement->getElementsByTagName('id')->item(0);

            if ($idElement && intval($idElement->nodeValue) === intval($taskId)) {
                $taskElement->parentNode->removeChild($taskElement);
                $found = true;
                break;
            }
        }

        if (!$found) {
            return ['success' => false, 'error' => 'Task not found'];
        }

        if ($this->saveXML()) {
            return ['success' => true, 'message' => 'Task deleted successfully'];
        }
        return ['success' => false, 'error' => 'Failed to save XML'];
    }

    /**
     * Convert DOM element to associative array
     */
    private function elementToArray($element)
    {
        $array = [];

        foreach ($element->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $array[$child->nodeName] = $child->nodeValue;
            }
        }

        return $array;
    }

    /**
     * Get next available task ID
     */
    public function getNextTaskId()
    {
        $maxId = 0;
        $taskElements = $this->dom->getElementsByTagName('task');

        foreach ($taskElements as $taskElement) {
            $idElement = $taskElement->getElementsByTagName('id')->item(0);
            if ($idElement) {
                $id = intval($idElement->nodeValue);
                if ($id > $maxId) {
                    $maxId = $id;
                }
            }
        }

        return $maxId + 1;
    }

    /**
     * Export all tasks as JSON (for compatibility with existing code)
     */
    public function exportJSON($userId = null)
    {
        return json_encode($this->getTasks($userId));
    }

    /**
     * Get validation status of XML file
     */
    public function getValidationStatus()
    {
        return $this->validateXML();
    }
}

// Helper function to use XML handler globally
function getXMLHandler()
{
    static $handler = null;
    if ($handler === null) {
        $handler = new XMLTaskHandler();
    }
    return $handler;
}
?>