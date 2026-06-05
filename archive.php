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


    <div class="container">
        <h1>📦 Archived Tasks</h1>
        <p>View and restore your archived tasks</p>

        <div id="loading">Loading archived tasks...</div>

        <input type="hidden" id="csrf_token" name="csrf_token" value="">

        <div id="archiveList" class="cards-container archive-hidden">
            <!-- Archived tasks will be loaded here -->
        </div>

        <div id="emptyState" class="empty-state" style="display: none;">
            <p>📭 No archived tasks yet</p>
            <p>When you delete tasks, they'll appear here</p>
        </div>
    </div>
        </main>

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

                const json = await response.json();
                if (!json.success) {
                    throw new Error(json.error || 'Failed to load archive');
                }
                
                displayArchiveTasks(json.data || []);
            } catch (error) {
                console.error('Error loading archive:', error);
                showNotification('Error loading archive', 'error');
                document.getElementById('loading').style.display = 'none';
                document.getElementById('emptyState').style.display = 'block';
                document.getElementById('archiveList').classList.add('archive-hidden');
            }
        }

        function displayArchiveTasks(tasks) {
            const loading = document.getElementById('loading');
            const emptyState = document.getElementById('emptyState');
            const archiveList = document.getElementById('archiveList');

            loading.style.display = 'none';

            if (!tasks || tasks.length === 0) {
                emptyState.style.display = 'block';
                archiveList.classList.add('archive-hidden');
                return;
            }

            emptyState.style.display = 'none';
            archiveList.classList.remove('archive-hidden');
            archiveList.innerHTML = '';

            tasks.forEach(task => {
                const card = document.createElement('div');
                card.className = task.status === 'completed' ? 'card completed' : 'card';

                const titleDiv = document.createElement('div');
                const strong = document.createElement('strong');
                strong.className = 'card-title';
                strong.textContent = task.title;
                titleDiv.appendChild(strong);

                const descDiv = document.createElement('div');
                const p = document.createElement('p');
                p.className = 'card-text';
                p.textContent = task.description;
                descDiv.appendChild(p);

                const infoDiv = document.createElement('div');
                const small = document.createElement('small');
                small.textContent = '📦 Archived: ' + new Date(task.archived_at).toLocaleString();
                small.style.color = '#999';
                small.style.display = 'block';
                small.style.fontSize = '0.85em';
                small.style.marginTop = '8px';
                infoDiv.appendChild(small);

                const actionsDiv = document.createElement('div');
                actionsDiv.className = 'card-actions';

                const restoreBtn = document.createElement('button');
                restoreBtn.textContent = '↩️ Restore';
                restoreBtn.onclick = () => restoreTask(task.id);

                const deleteBtn = document.createElement('button');
                deleteBtn.textContent = '🗑️ Delete';
                deleteBtn.onclick = () => permanentlyDeleteTask(task.id);

                actionsDiv.appendChild(restoreBtn);
                actionsDiv.appendChild(deleteBtn);

                card.appendChild(titleDiv);
                card.appendChild(descDiv);
                card.appendChild(infoDiv);
                card.appendChild(actionsDiv);
                archiveList.appendChild(card);
            });
        }

        async function restoreTask(id) {
            const csrfToken = document.getElementById('csrf_token')?.value;
            
            if (!csrfToken) {
                showNotification('✗ Security token missing. Refreshing page...', 'error');
                location.reload();
                return;
            }

            try {
                const response = await fetch('restore_task.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: `id=${encodeURIComponent(id)}&csrf_token=${encodeURIComponent(csrfToken)}`
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

            const csrfToken = document.getElementById('csrf_token')?.value;
            
            if (!csrfToken) {
                showNotification('✗ Security token missing. Refreshing page...', 'error');
                location.reload();
                return;
            }

            try {
                const response = await fetch('delete_archive_task.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: `id=${encodeURIComponent(id)}&csrf_token=${encodeURIComponent(csrfToken)}`
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
                    loadArchiveTasks();
                });
        });
    </script>
</body>

</html>