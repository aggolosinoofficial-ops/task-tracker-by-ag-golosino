<?php
/**
 * Debug Test Page
 * Tests all components of the to-do app
 */
session_start();

// 1. SECURITY: Only allow access if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Access Denied: You must be logged in to view this debug page.");
}

// 2. DEPENDENCIES
if (file_exists('config.php')) include 'config.php';
if (file_exists('db.php')) include 'db.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Test - To-Do App</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        .test-section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #667eea; border-radius: 4px; }
        .test-item { margin: 10px 0; padding: 10px; background: white; border-radius: 4px; }
        .status { display: inline-block; padding: 5px 10px; border-radius: 3px; font-weight: bold; margin-left: 10px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        button { background: #667eea; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin: 10px 5px 10px 0; }
        button:hover { background: #764ba2; }
        #testOutput { margin-top: 20px; padding: 15px; background: #1e1e1e; color: #00ff00; border-radius: 4px; font-family: monospace; white-space: pre-wrap; max-height: 400px; overflow-y: auto; }
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
                    if (isset($conn) && !$conn->connect_error) {
                        echo "MySQL Connection: <span class='status success'>✓ Connected</span><br>";
                        $tables = ['users', 'tasks'];
                        foreach ($tables as $table) {
                            $result = $conn->query("SHOW TABLES LIKE '$table'");
                            if ($result && $result->num_rows > 0) {
                                echo "Table <code>$table</code>: <span class='status success'>✓ Exists</span><br>";
                            } else {
                                echo "Table <code>$table</code>: <span class='status error'>✗ Missing</span><br>";
                            }
                        }
                    } else {
                        echo "MySQL Connection: <span class='status error'>✗ Failed/Not Initialized</span>";
                    }
                } catch (Exception $e) { echo "Error: " . $e->getMessage(); }
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

        function clearLog() { document.getElementById('testOutput').textContent = ''; }

        async function fetchWrapper(url, options = {}) {
            const response = await fetch(url, options);
            if (!response.ok) throw new Error(`HTTP Error: ${response.status}`);
            return await response.json();
        }

        async function testLogin(username, password) {
            clearLog();
            log(`Testing login for: ${username}`);
            try {
                const tokenData = await fetchWrapper('get_csrf_token.php');
                const formData = new FormData();
                formData.append('username', username);
                formData.append('password', password);
                formData.append('csrf_token', tokenData.token);
                
                const loginData = await fetchWrapper('login.php', { method: 'POST', body: formData });
                log(loginData.success ? `✓ Login successful: ${loginData.username}` : `✗ Login failed: ${loginData.error}`);
            } catch (e) { log('✗ Error: ' + e.message); }
        }

        async function testGetTasks() {
            clearLog();
            log('Fetching tasks...');
            try {
                const data = await fetchWrapper('get_tasks.php');
                log('✓ Response received');
                log(JSON.stringify(data, null, 2));
            } catch (e) { log('✗ Error: ' + e.message); }
        }

        async function testAddTask() {
            clearLog();
            log('Adding test task...');
            try {
                const data = await fetchWrapper('add_task.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `title=Test+Task+${Date.now()}&description=Auto-generated+test`
                });
                log(data.success ? `✓ Task added successfully (ID: ${data.task_id})` : `✗ Failed: ${data.error}`);
            } catch (e) { log('✗ Error: ' + e.message); }
        }

        async function testCSRFToken() {
            clearLog();
            try {
                const data = await fetchWrapper('get_csrf_token.php');
                log('✓ Token: ' + data.token.substring(0, 15) + '...');
            } catch (e) { log('✗ Error: ' + e.message); }
        }

        log('Debug test page ready.');
    </script>
</body>
</html>