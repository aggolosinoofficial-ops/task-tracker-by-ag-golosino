<?php
/**
 * Debug Test Page
 * Tests all components of the to-do app
 */

include 'config.php';
include 'db.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Test - To-Do App</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .test-section {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #667eea;
            border-radius: 4px;
        }
        .test-item {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 4px;
        }
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-weight: bold;
            margin-left: 10px;
        }
        .success {
            background: #d4edda;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        button {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin: 10px 5px 10px 0;
        }
        button:hover {
            background: #764ba2;
        }
        #testOutput {
            margin-top: 20px;
            padding: 15px;
            background: #f4f4f4;
            border-radius: 4px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Debug Test Suite</h1>
        
        <div class="test-section">
            <h2>Database Connection</h2>
            <div class="test-item">
                <?php
                try {
                    if ($conn && !$conn->connect_error) {
                        echo "MySQL Connection: <span class='status success'>✓ Connected</span><br>";
                        echo "Host: " . DB_HOST . " | User: " . DB_USER . "<br>";
                        
                        // Check tables exist
                        $tables = ['users', 'tasks', 'archive_tasks', 'task_stats'];
                        foreach ($tables as $table) {
                            $result = $conn->query("SHOW TABLES FROM " . DB_NAME . " LIKE '$table'");
                            if ($result && $result->num_rows > 0) {
                                echo "Table <code>$table</code>: <span class='status success'>✓ Exists</span><br>";
                            } else {
                                echo "Table <code>$table</code>: <span class='status error'>✗ Missing</span><br>";
                            }
                        }
                    } else {
                        echo "MySQL Connection: <span class='status error'>✗ Failed</span>";
                    }
                } catch (Exception $e) {
                    echo "Error: " . $e->getMessage();
                }
                ?>
            </div>
        </div>

        <div class="test-section">
            <h2>Test Authentication</h2>
            <button onclick="testLogin('admin123', 'Admin_123')">Test Admin Login</button>
            <button onclick="testLogin('testuser', 'password123')">Test Regular User Login</button>
        </div>

        <div class="test-section">
            <h2>Test Task Operations</h2>
            <button onclick="testAddTask()">Add Test Task</button>
            <button onclick="testGetTasks()">Get All Tasks</button>
            <button onclick="testCSRFToken()">Get CSRF Token</button>
        </div>

        <div class="test-section">
            <h2>Test Output</h2>
            <div id="testOutput">Ready to test...</div>
        </div>
    </div>

    <script>
        function log(message) {
            const output = document.getElementById('testOutput');
            output.textContent += message + '\n';
            output.scrollTop = output.scrollHeight;
        }

        function clearLog() {
            document.getElementById('testOutput').textContent = '';
        }

        function testLogin(username, password) {
            clearLog();
            log(`Testing login with username: ${username}`);
            
            fetch('get_csrf_token.php')
                .then(r => r.json())
                .then(data => {
                    log(`✓ CSRF Token: ${data.token.substring(0, 10)}...`);
                    
                    const formData = new FormData();
                    formData.append('username', username);
                    formData.append('password', password);
                    formData.append('csrf_token', data.token);
                    
                    return fetch('login.php', {
                        method: 'POST',
                        body: formData
                    });
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        log(`✓ Login successful for user: ${data.username}`);
                        log(`✓ User ID: ${data.user_id}`);
                    } else {
                        log(`✗ Login failed: ${data.error}`);
                    }
                })
                .catch(e => log(`✗ Error: ${e.message}`));
        }

        function testGetTasks() {
            clearLog();
            log('Fetching tasks...');
            
            fetch('get_tasks.php')
                .then(r => {
                    if (r.status === 401) {
                        throw new Error('Not authenticated - login first');
                    }
                    return r.json();
                })
                .then(data => {
                    log(`✓ Response received`);
                    log(`✓ Format: ${data.success ? 'paginated' : 'unknown'}`);
                    if (data.data) {
                        log(`✓ Tasks count: ${data.data.length}`);
                        log(`✓ Pagination: Page ${data.pagination.page} of ${data.pagination.total_pages}`);
                        if (data.data.length > 0) {
                            log(`\nFirst task:`);
                            log(JSON.stringify(data.data[0], null, 2));
                        }
                    }
                })
                .catch(e => log(`✗ Error: ${e.message}`));
        }

        function testAddTask() {
            clearLog();
            log('Adding test task...');
            
            const title = `Test Task ${Date.now()}`;
            const description = 'This is a test task created at ' + new Date().toLocaleString();
            
            fetch('add_task.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `title=${encodeURIComponent(title)}&description=${encodeURIComponent(description)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    log(`✓ Task added successfully`);
                    log(`✓ Task ID: ${data.task_id}`);
                    log(`\nRefetching task list...`);
                    setTimeout(testGetTasks, 500);
                } else {
                    log(`✗ Failed: ${data.error}`);
                }
            })
            .catch(e => log(`✗ Error: ${e.message}`));
        }

        function testCSRFToken() {
            clearLog();
            log('Getting CSRF token...');
            
            fetch('get_csrf_token.php')
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.token) {
                        log(`✓ Token generated successfully`);
                        log(`✓ Token: ${data.token.substring(0, 20)}...`);
                        log(`✓ Length: ${data.token.length} characters`);
                    } else {
                        log(`✗ Failed: ${data.error || 'Unknown error'}`);
                    }
                })
                .catch(e => log(`✗ Error: ${e.message}`));
        }

        // Log initial state
        log('Debug test page ready. Click buttons to run tests.');
    </script>
</body>
</html>
