<?php
/**
 * Archive Page - Manage Archived Tasks
 * View and restore previously archived tasks
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
    <title>Archive - To-Do List App</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .archive-container {
            background: linear-gradient(to bottom, rgba(255, 255, 255, 0.95), rgba(230, 245, 250, 0.95));
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(122, 179, 212, 0.1);
            margin: 20px 0;
        }

        #archiveList li {
            background: white;
            border-left: 5px solid #7cb3d4;
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.2s;
        }

        #archiveList li:hover {
            transform: translateX(5px);
            box-shadow: 0 3px 10px rgba(122, 179, 212, 0.15);
        }

        #archiveList li.completed {
            opacity: 0.8;
        }

        #archiveList li.completed div strong {
            text-decoration: line-through;
            color: #999;
        }

        .archive-actions {
            display: flex;
            gap: 10px;
        }

        .archive-actions button {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 600;
            transition: all 0.3s;
        }

        .archive-actions button:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
        }

        .restore-btn {
            background-color: #28a745;
            color: white;
        }

        .restore-btn:hover {
            background-color: #218838;
        }

        .delete-btn {
            background-color: #dc3545;
            color: white;
        }

        .delete-btn:hover {
            background-color: #c82333;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state p {
            font-size: 1.1em;
            margin: 10px 0;
        }

        #loading {
            text-align: center;
            padding: 40px;
            color: #7cb3d4;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div id="notificationContainer"></div>

    <!-- User Info Bar -->
    <div class="user-bar">
        <div class="user-info">
            <span class="username">Welcome, <strong>
                    <?php echo $username; ?>
                </strong>!</span>
        </div>
        <div class="user-actions">
            <a href="index.php" class="nav-link">Add Task</a>
            <a href="tasks.php" class="nav-link">All Tasks</a>
            <a href="insights.php" class="nav-link">📊 Insights</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <h1>📦 Archived Tasks</h1>
        <p>View and restore your archived tasks</p>

        <div id="loading">Loading archived tasks...</div>

        <ul id="archiveList" style="display: none; list-style: none; padding: 0;">
            <!-- Archived tasks will be loaded here -->
        </ul>

        <div id="emptyState" style="display: none;" class="empty-state">
            <p>📭 No archived tasks yet</p>
            <p>When you delete tasks, they'll appear here</p>
        </div>
    </div>

    <script>
        function showNotification(message, type = 'info', duration = 4000) {
            const container = document.getElementById('notificationContainer');
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            container.appendChild(notification);

            setTimeout(() => {
                notification.classList.add('hide');
                setTimeout(() => {
                    container.removeChild(notification);
                }, 300);
            }, duration);
        }

        async function loadArchiveTasks() {
            try {
                const response = await fetch('get_archive_tasks.php');
                if (!response.ok) {
                    throw new Error('Failed to load archive');
                }

                const tasks = await response.json();
                displayArchiveTasks(tasks);
            } catch (error) {
                console.error('Error loading archive:', error);
                showNotification('Error loading archive', 'error');
                document.getElementById('loading').style.display = 'none';
                document.getElementById('emptyState').style.display = 'block';
            }
        }

        function displayArchiveTasks(tasks) {
            document.getElementById('loading').style.display = 'none';

            if (!tasks || tasks.length === 0) {
                document.getElementById('emptyState').style.display = 'block';
                return;
            }

            document.getElementById('archiveList').style.display = 'block';
            const archiveList = document.getElementById('archiveList');
            archiveList.innerHTML = '';

            tasks.forEach(task => {
                const li = document.createElement('li');
                li.className = task.status === 'completed' ? 'completed' : '';

                const taskDiv = document.createElement('div');
                taskDiv.style.flex = '1';

                const strong = document.createElement('strong');
                strong.textContent = task.title;
                strong.style.display = 'block';
                strong.style.marginBottom = '5px';

                const p = document.createElement('p');
                p.textContent = task.description;
                p.style.color = '#666';
                p.style.margin = '5px 0';
                p.style.fontSize = '0.95em';

                const small = document.createElement('small');
                small.textContent = '📦 Archived: ' + new Date(task.archived_at).toLocaleString();
                small.style.color = '#999';
                small.style.display = 'block';
                small.style.marginTop = '8px';

                taskDiv.appendChild(strong);
                taskDiv.appendChild(p);
                taskDiv.appendChild(small);

                const actionsDiv = document.createElement('div');
                actionsDiv.className = 'archive-actions';

                const restoreBtn = document.createElement('button');
                restoreBtn.textContent = '↩️ Restore';
                restoreBtn.className = 'restore-btn';
                restoreBtn.onclick = () => restoreTask(task.id);

                const deleteBtn = document.createElement('button');
                deleteBtn.textContent = '🗑️ Delete';
                deleteBtn.className = 'delete-btn';
                deleteBtn.onclick = () => permanentlyDeleteTask(task.id);

                actionsDiv.appendChild(restoreBtn);
                actionsDiv.appendChild(deleteBtn);

                li.appendChild(taskDiv);
                li.appendChild(actionsDiv);
                archiveList.appendChild(li);
            });
        }

        async function restoreTask(id) {
            try {
                const response = await fetch('restore_task.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${id}`
                });

                const result = await response.json();
                if (result.success) {
                    showNotification('✓ Task restored!', 'success');
                    loadArchiveTasks();
                } else {
                    showNotification('✗ ' + (result.error || 'Failed'), 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('✗ Network error', 'error');
            }
        }

        async function permanentlyDeleteTask(id) {
            if (!confirm('Permanently delete this task? This cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('delete_archive_task.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${id}`
                });

                const result = await response.json();
                if (result.success) {
                    showNotification('✓ Permanently deleted', 'success');
                    loadArchiveTasks();
                } else {
                    showNotification('✗ ' + (result.error || 'Failed'), 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('✗ Network error', 'error');
            }
        }

        // Load on page load
        document.addEventListener('DOMContentLoaded', loadArchiveTasks);
    </script>
</body>

</html>