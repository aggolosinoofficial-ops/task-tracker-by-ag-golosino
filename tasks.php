<?php
/**
 * All Tasks Page - To-Do List App
 * View and manage all personal tasks
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
    <title>All Tasks - To-Do List App</title>
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
            <a href="index.php" class="nav-link">Add New Task</a>
            <a href="insights.php" class="nav-link">📊 Insights</a>
            <a href="archive.php" class="nav-link">📦 Archive</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <h1>All Tasks</h1>
        <div id="loading" style="display: none;">Loading tasks...</div>
        <ul id="taskList">
            <!-- Tasks will be loaded here -->
        </ul>
    </div>
    <script src="script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            loadTasks();
        });
    </script>
</body>

</html>