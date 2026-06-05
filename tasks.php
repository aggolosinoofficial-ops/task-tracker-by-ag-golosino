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
    <title>All Tasks - Task - tracker - app</title>
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
    </div>

    <!-- navigation links now live inside the sidebar -->
    <div class="container">
        <h1>All Tasks</h1>
        <div id="loading" style="display: none;">Loading tasks...</div>
        <input type="hidden" id="csrf_token" name="csrf_token" value="">
        <div id="taskList" class="cards-container">
            <!-- Tasks will be loaded here -->
        </div>
    </div>
    </main>
    <script src="script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            fetch('get_csrf_token.php')
                .then(r => r.json())
                .then(data => {
                    if (data.token) {
                        document.getElementById('csrf_token').value = data.token;
                    }
                })
                .catch(err => {
                    console.error('CSRF token fetch failed:', err);
                })
                .finally(() => {
                    loadTasks();
                });
        });
    </script>
</body>

</html>
