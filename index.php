<?php
/**
 * Add Task Page - To-Do List App
 * Create new tasks and view them 
 * Requires authentication
 */

include 'auth_check.php';

// Check authentication
requireAuth();

// Get current user info
$user = getCurrentUser();
$username = $user ? htmlspecialchars($user['username']) : 'User';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Task - To-Do List App</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div id="notificationContainer"></div>

    <!-- User Info Bar -->
    <div class="user-bar">
        <div class="user-info">
            <span class="username">Welcome, <strong><?php echo $username; ?></strong>!</span>
        </div>
        <div class="user-actions">
            <a href="tasks.php" class="nav-link">All Tasks</a>
            <a href="insights.php" class="nav-link">📊 Insights</a>
            <a href="archive.php" class="nav-link">📦 Archive</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <h1>Add New Task</h1>
        <form id="taskForm">
            <input type="hidden" id="csrf_token" name="csrf_token" value="">
            <input type="text" id="title" placeholder="Task Title" required>
            <textarea id="description" placeholder="Task Description"></textarea>
            <button type="submit">Add Task</button>
        </form>
    </div>
    <script src="script.js"></script>
    <script>
        // Initialize page on load
        document.addEventListener('DOMContentLoaded', function() {
            // Load CSRF token
            fetch('get_csrf_token.php')
                .then(r => r.json())
                .then(data => {
                    if (data.token) {
                        document.getElementById('csrf_token').value = data.token;
                    }
                });
            initializeTaskForm();
        });
    </script>
</body>

</html>