<?php
/**
 * Task Form Debug & Test Page
 * Tests the entire add task workflow
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
    <link rel="stylesheet" href="style.css">
    <style>
        .debug-section {
            background: #f5f5f5;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
            border-radius: 4px;
        }
        .debug-section h3 {
            margin-top: 0;
            color: #667eea;
        }
        .test-button {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            margin: 5px;
        }
        .test-button:hover {
            background: #5568d3;
        }
        .result {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            font-weight: bold;
        }
        .success-result {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error-result {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info-result {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        #console-output {
            background: #1e1e1e;
            color: #00ff00;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            max-height: 300px;
            overflow-y: auto;
            font-size: 12px;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <div id="notificationContainer"></div>

    <div class="user-bar">
        <div class="user-info">
            <span class="username">Welcome, <strong><?php echo htmlspecialchars($user['username']); ?></strong>!</span>
        </div>
        <div class="user-actions">
            <a href="index.php" class="nav-link">Add New Task</a>
            <a href="tasks.php" class="nav-link">All Tasks</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <h1>🐛 Task Debug & Test</h1>

        <!-- User Info Section -->
        <div class="debug-section">
            <h3>User Information</h3>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
            <p><strong>User ID:</strong> <?php echo $user['id']; ?></p>
            <p><strong>Session Status:</strong> <span class="result success-result">✓ Authenticated</span></p>
        </div>

        <!-- Test Form -->
        <div class="debug-section">
            <h3>Test Add Task Form</h3>
            <form id="testForm">
                <input type="text" id="testTitle" placeholder="Test Task Title" value="Debug Test Task" required>
                <textarea id="testDescription" placeholder="Test Description" style="width: 100%; padding: 8px; margin: 10px 0;">This is a test task created from the debug page.</textarea>
                <button type="submit" class="test-button">Test Add Task</button>
            </form>
            <div id="testResult"></div>
        </div>

        <!-- API Tests -->
        <div class="debug-section">
            <h3>API Tests</h3>
            <button class="test-button" onclick="testCSRFToken()">Test 1: CSRF Token</button>
            <button class="test-button" onclick="testDatabase()">Test 2: Database Connection</button>
            <button class="test-button" onclick="testGetTasks()">Test 3: Get Tasks</button>
            <button class="test-button" onclick="testAddTask()">Test 4: Add Task (Direct)</button>
            <div id="apiResult"></div>
        </div>

        <!-- Console Output -->
        <div class="debug-section">
            <h3>Console Output</h3>
            <div id="console-output">Waiting for output...</div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        // Redirect console.log to page
        const consoleOutput = document.getElementById('console-output');
        let logBuffer = [];

        function addLog(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            const logLine = `[${timestamp}] ${message}`;
            logBuffer.push(logLine);
            consoleOutput.innerHTML = logBuffer.join('\n');
            consoleOutput.scrollTop = consoleOutput.scrollHeight;
        }

        // Override console.log
        const originalLog = console.log;
        console.log = function(...args) {
            originalLog.apply(console, args);
            addLog(args.join(' '));
        };

        // Test Functions
        function testCSRFToken() {
            addLog('Testing CSRF token generation...');
            fetch('get_csrf_token.php')
                .then(response => response.json())
                .then(data => {
                    if (data.token) {
                        addLog('✓ CSRF Token: ' + data.token.substring(0, 10) + '...');
                        showResultDiv('apiResult', 'success', '✓ CSRF Token test passed');
                    } else {
                        addLog('✗ No token in response');
                        showResultDiv('apiResult', 'error', '✗ CSRF Token test failed');
                    }
                })
                .catch(error => {
                    addLog('✗ CSRF Token Error: ' + error);
                    showResultDiv('apiResult', 'error', '✗ Error: ' + error);
                });
        }

        function testDatabase() {
            addLog('Testing database connection...');
            fetch('test_connection.php')
                .then(response => response.text())
                .then(html => {
                    if (html.includes('MySQL Connected')) {
                        addLog('✓ Database connected');
                        showResultDiv('apiResult', 'success', '✓ Database test passed');
                    } else {
                        addLog('✗ Database connection failed');
                        showResultDiv('apiResult', 'error', '✗ Database test failed');
                    }
                })
                .catch(error => {
                    addLog('✗ Database Error: ' + error);
                    showResultDiv('apiResult', 'error', '✗ Error: ' + error);
                });
        }

        function testGetTasks() {
            addLog('Testing get_tasks.php...');
            fetch('get_tasks.php?page=1&limit=10')
                .then(response => response.json())
                .then(data => {
                    if (data.data !== undefined) {
                        addLog('✓ Get Tasks Response: ' + data.data.length + ' tasks found');
                        showResultDiv('apiResult', 'success', '✓ Get Tasks test passed (found ' + data.data.length + ' tasks)');
                    } else {
                        addLog('✗ Invalid response format');
                        showResultDiv('apiResult', 'error', '✗ Invalid response format');
                    }
                })
                .catch(error => {
                    addLog('✗ Get Tasks Error: ' + error);
                    showResultDiv('apiResult', 'error', '✗ Error: ' + error);
                });
        }

        function testAddTask() {
            addLog('Testing add_task.php...');
            const title = 'API Test Task ' + Date.now();
            const description = 'Testing via debug page';

            fetch('add_task.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `title=${encodeURIComponent(title)}&description=${encodeURIComponent(description)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    addLog('✓ Task added: ID ' + data.task_id);
                    showResultDiv('apiResult', 'success', '✓ Task added successfully (ID: ' + data.task_id + ')');
                } else {
                    addLog('✗ Add Task Error: ' + data.error);
                    showResultDiv('apiResult', 'error', '✗ Error: ' + data.error);
                }
            })
            .catch(error => {
                addLog('✗ Add Task Error: ' + error);
                showResultDiv('apiResult', 'error', '✗ Error: ' + error);
            });
        }

        function showResultDiv(divId, type, message) {
            const resultDiv = document.getElementById(divId);
            const div = document.createElement('div');
            div.className = 'result ' + (type === 'success' ? 'success-result' : type === 'error' ? 'error-result' : 'info-result');
            div.textContent = message;
            resultDiv.innerHTML = '';
            resultDiv.appendChild(div);
        }

        // Test Form Submission
        document.getElementById('testForm').addEventListener('submit', function(e) {
            e.preventDefault();
            addLog('Test form submitted');

            const title = document.getElementById('testTitle').value;
            const description = document.getElementById('testDescription').value;

            addLog('Title: ' + title);
            addLog('Description: ' + description);

            fetch('add_task.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `title=${encodeURIComponent(title)}&description=${encodeURIComponent(description)}`
            })
            .then(response => {
                addLog('Response status: ' + response.status);
                return response.json();
            })
            .then(data => {
                addLog('Response data: ' + JSON.stringify(data));
                if (data.success) {
                    showResultDiv('testResult', 'success', '✓ Task added! Task ID: ' + data.task_id);
                    showNotification('✓ Task added successfully!', 'success');
                } else {
                    showResultDiv('testResult', 'error', '✗ Error: ' + (data.error || 'Unknown error'));
                    showNotification('✗ Error: ' + (data.error || 'Failed to add task'), 'error');
                }
            })
            .catch(error => {
                addLog('Network error: ' + error);
                showResultDiv('testResult', 'error', '✗ Network error: ' + error);
                showNotification('✗ Network error', 'error');
            });
        });

        // Initialize
        addLog('Debug page loaded');
        addLog('User: <?php echo htmlspecialchars($user['username']); ?>');
        addLog('User ID: <?php echo $user['id']; ?>');
    </script>
</body>
</html>
