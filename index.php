<?php
/**
 * Add Task Page - Task Tracker App
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
<link rel="stylesheet" href="style.css?v=20260605">
</head>

<body>
    <div id="notificationContainer"></div>

    <nav class="sidebar" id="appSidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <div class="sidebar-logo">🗂️</div>
                <div class="sidebar-title">Task Tracker</div>
            </div>
            <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Toggle navigation">
                ☰
            </button>
        </div>

        <div class="sidebar-nav">
            <a class="side-link" href="tasks.php">
                <span class="side-icon">📝</span>
                <span class="side-text">All Tasks</span>
            </a>
            <a class="side-link" href="insights.php">
                <span class="side-icon">📊</span>
                <span class="side-text">Insights</span>
            </a>
            <a class="side-link" href="index.php">
                <span class="side-icon">➕</span>
                <span class="side-text">Add Task</span>
            </a>
            <a class="side-link" href="archive.php">
                <span class="side-icon">📦</span>
                <span class="side-text">Archive</span>
            </a>

        </div>

        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <script>
        (function () {
            const sidebar = document.getElementById('appSidebar');
            const toggleBtn = document.getElementById('sidebarToggle');
            if (!sidebar || !toggleBtn) return;
            toggleBtn.addEventListener('click', function () {
                sidebar.classList.toggle('collapsed');
            });
        })();
    </script>

    <main class="main">

        <div class="user-info">
            <span class="username">Welcome, <strong><?php echo $username; ?></strong>!</span>
        </div>
        <!-- removed old top user bar actions; replaced by sidebar -->


    <div class="container">
        <h1>Add New Task</h1>
        <form id="taskForm">
            <input type="hidden" id="csrf_token" name="csrf_token" value="">
            <input type="text" id="title" name="title" placeholder="Task Title" required>
            <textarea id="description" name="description" placeholder="Task Description"></textarea>

            <div class="category-control">
                <label for="category">Category:</label>
                <select id="category" name="category">
                    <option value="personal">📝 Personal Task</option>
                    <option value="technical">⚙️ Technical Note</option>
                </select>
            </div>
            <button type="submit">Add Task</button>
        </form>
    </div>
    </main>
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