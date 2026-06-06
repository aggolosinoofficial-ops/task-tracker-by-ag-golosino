<?php
/**
 * Task Form Debug & Test Page
 * Tests the entire add task workflow with live feedback
 */
include 'auth_check.php';

// Check if user is authenticated
$user_id = checkAuth();
if (!$user_id) {
    header('Location: login.html');
    exit();
}

// Get current user
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Add Task Test</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; margin: 20px; color: #333; }
        .container { max-width: 800px; margin: auto; }
        .debug-section { background: #f5f5f5; padding: 15px; margin: 20px 0; border-left: 4px solid #667eea; border-radius: 4px; }
        .debug-section h3 { margin-top: 0; color: #667eea; }
        .test-button { background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; margin: 5px; }
        .test-button:hover { background: #5568d3; }
        .result { padding: 10px; margin: 10px 0; border-radius: 4px; font-weight: bold; }
        .success-result { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error-result { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* Notification Styling */
        #notificationContainer { position: fixed; top: 20px; right: 20px; z-index: 1000; }
        .notification { padding: 15px; margin-bottom: 10px; border-radius: 4px; color: white; }
        .notification.success { background-color: #28a745; }
        .notification.error { background-color: #dc3545; }

        #console-output { background: #1e1e1e; color: #00ff00; padding: 15px; border-radius: 4px; font-family: monospace; max-height: 300px; overflow-y: auto; font-size: 12px; }
        .user-bar { display: flex; justify-content: space-between; padding: 10px; border-bottom: 1px solid #ddd; }
    </style>
</head>
<body>
    <div id="notificationContainer"></div>

    <div class="user-bar">
        <div class="user-info">
            Welcome, <strong><?php echo htmlspecialchars($user['username']); ?></strong>!
        </div>
        <div class="user-actions">
            <a href="index.php">Add Task</a> | <a href="tasks.php">All Tasks</a> | <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <h1>🐛 Task Debug & Test</h1>

        <div class="debug-section">
            <h3>User Information</h3>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
            <p><strong>User ID:</strong> <?php echo $user['id']; ?></p>
            <p><strong>Status:</strong> <span class="result success-result">Authenticated</span></p>
        </div>

        <div class="debug-section">
            <h3>Test Add Task Form</h3>
            <form id="testForm">
                <input type="text" id="testTitle" placeholder="Test Task Title" value="Debug Test Task" required style="width: 100%; padding: 8px;">
                <textarea id="testDescription" placeholder="Test Description" style="width: 100%; padding: 8px; margin: 10px 0;">Testing...</textarea>
                <button type="submit" class="test-button">Test Add Task</button>
            </form>
            <div id="testResult"></div>
        </div>

        <div class="debug-section">
            <h3>API Tests</h3>
            <button class="test-button" onclick="testDatabase()">Test Database</button>
            <button class="test-button" onclick="testGetTasks()">Test Get Tasks</button>
            <div id="apiResult"></div>
        </div>

        <div class="debug-section">
            <h3>Console Output</h3>
            <div id="console-output">Waiting for output...</div>
        </div>
    </div>

    <script>
        const consoleOutput = document.getElementById('console-output');
        
        function addLog(message) {
            const timestamp = new Date().toLocaleTimeString();
            consoleOutput.innerHTML += `\n[${timestamp}] ${message}`;
            consoleOutput.scrollTop = consoleOutput.scrollHeight;
        }

        function showNotification(message, type) {
            const container = document.getElementById('notificationContainer');
            const div = document.createElement('div');
            div.className = `notification ${type}`;
            div.textContent = message;
            container.appendChild(div);
            setTimeout(() => div.remove(), 3000);
        }

        function showResultDiv(divId, type, message) {
            const resultDiv = document.getElementById(divId);
            const div = document.createElement('div');
            div.className = 'result ' + (type === 'success' ? 'success-result' : 'error-result');
            div.textContent = message;
            resultDiv.innerHTML = '';
            resultDiv.appendChild(div);
        }

        // --- HANDLERS ---
        document.getElementById('testForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const title = document.getElementById('testTitle').value;
            const description = document.getElementById('testDescription').value;

            fetch('add_task.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `title=${encodeURIComponent(title)}&description=${encodeURIComponent(description)}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showResultDiv('testResult', 'success', 'Task added! ID: ' + data.task_id);
                    showNotification('Task Added!', 'success');
                } else {
                    showResultDiv('testResult', 'error', 'Error: ' + (data.error || 'Unknown'));
                    showNotification('Failed to add task', 'error');
                }
            });
        });

        function testDatabase() {
            addLog('Testing Database...');
            fetch('test_connection.php')
                .then(res => res.text())
                .then(text => addLog(text.includes('MySQL Connected') ? '✓ DB Success' : '✗ DB Failed'));
        }

        function testGetTasks() {
            addLog('Testing Get Tasks...');
            fetch('get_tasks.php')
                .then(res => res.json())
                .then(data => addLog('Found ' + (data.data ? data.data.length : 0) + ' tasks'));
        }
    </script>
</body>
</html>