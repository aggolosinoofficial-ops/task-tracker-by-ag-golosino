<?php
/**
 * Task Insights Page - Analytics & Statistics
 * View personal task analytics and productivity metrics
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
    <title>Insights - To-Do List App</title>
    <link rel="stylesheet" href="style.css?v=20260605">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .insights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .stat-card {
            background: linear-gradient(to bottom, rgba(255, 255, 255, 0.95), rgba(230, 245, 250, 0.95));
            padding: 25px;
            border-radius: 12px;
            border-left: 5px solid #7cb3d4;
            box-shadow: 0 3px 10px rgba(122, 179, 212, 0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(122, 179, 212, 0.2);
        }

        .stat-card h3 {
            color: #666;
            font-size: 0.95em;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-card .stat-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
            margin: 10px 0;
        }

        .stat-card .stat-unit {
            color: #999;
            font-size: 0.85em;
        }

        .productivity-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            margin-top: 10px;
            font-size: 0.85em;
        }

        .productivity-badge.excellent {
            background-color: #d4edda;
            color: #155724;
        }

        .productivity-badge.good {
            background-color: #cfe2ff;
            color: #084298;
        }

        .productivity-badge.moderate {
            background-color: #fff3cd;
            color: #664d03;
        }

        .productivity-badge.active {
            background-color: #e2e3e5;
            color: #383d41;
        }

        .productivity-badge.starting {
            background-color: #f8d7da;
            color: #721c24;
        }

        .charts-container {
            background: linear-gradient(to bottom, rgba(255, 255, 255, 0.95), rgba(230, 245, 250, 0.95));
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(122, 179, 212, 0.1);
            margin: 30px 0;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
            margin: 30px 0;
        }

        .chart-title {
            color: #333;
            font-size: 1.2em;
            font-weight: 600;
            margin-bottom: 15px;
            border-bottom: 2px solid #7cb3d4;
            padding-bottom: 10px;
        }

        #loading {
            text-align: center;
            padding: 40px;
            color: #7cb3d4;
            font-weight: 600;
            font-size: 1.1em;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        @media (max-width: 768px) {
            .insights-grid {
                grid-template-columns: 1fr;
            }

            .chart-wrapper {
                height: 250px;
            }
        }
    </style>
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

        <div class="container">
            <h1>📊 Insights</h1>
        <p>Your productivity analytics and statistics</p>

        <div id="loading">Loading insights...</div>

        <div id="insightsContent" style="display: none;">
            <!-- Statistics Grid -->
            <div class="insights-grid">
                <div class="stat-card">
                    <h3>Active Tasks</h3>
                    <div class="stat-value" id="totalActive">0</div>
                    <div class="stat-unit">Pending + Completed</div>
                </div>

                <div class="stat-card">
                    <h3>Pending</h3>
                    <div class="stat-value" id="pendingCount">0</div>
                    <div class="stat-unit">Awaiting completion</div>
                </div>

                <div class="stat-card">
                    <h3>Completed</h3>
                    <div class="stat-value" id="completedCount">0</div>
                    <div class="stat-unit">Well done!</div>
                </div>

                <div class="stat-card">
                    <h3>Archived</h3>
                    <div class="stat-value" id="archivedCount">0</div>
                    <div class="stat-unit">Historical tasks</div>
                </div>

                <div class="stat-card">
                    <h3>Completion Rate</h3>
                    <div class="stat-value" id="completionRate">0<span style="font-size: 0.6em;">%</span></div>
                    <div class="stat-unit">Of active tasks</div>
                </div>

                <div class="stat-card">
                    <h3>Productivity</h3>
                    <div id="productivityBadge" class="productivity-badge"></div>
                    <div class="stat-unit" style="margin-top: 10px;" id="avgPerDay">Avg: 0 tasks/day</div>
                </div>
            </div>

            <!-- Charts -->
            <div class="charts-container">
                <div class="chart-title">📈 Task Status Distribution</div>
                <div class="chart-wrapper">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <div class="charts-container">
                <div class="chart-title">📅 Daily Task Creation (Last 7 Days)</div>
                <div class="chart-wrapper">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>

            <div class="charts-container">
                <div class="chart-title">🎯 All-Time Summary</div>
                <div class="chart-wrapper">
                    <canvas id="summaryChart"></canvas>
                </div>
            </div>
        </div>

        <div id="emptyState" style="display: none;" class="empty-state">
            <p>📭 No data available yet</p>
            <p>Start creating tasks to see your analytics!</p>
        </div>
    </div>

    <script>
        let statusChart = null;
        let dailyChart = null;
        let summaryChart = null;

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

        async function loadInsights() {
            try {
                const response = await fetch('get_insights.php');
                if (!response.ok) {
                    throw new Error('Failed to load insights');
                }

                const insights = await response.json();
                displayInsights(insights);
            } catch (error) {
                console.error('Error loading insights:', error);
                showNotification('Error loading insights', 'error');
                document.getElementById('loading').style.display = 'none';
                document.getElementById('emptyState').style.display = 'block';
            }
        }

        function displayInsights(data) {
            document.getElementById('loading').style.display = 'none';

            if (data.total_all_time === 0) {
                document.getElementById('emptyState').style.display = 'block';
                return;
            }

            document.getElementById('insightsContent').style.display = 'block';

            // Update stat cards
            document.getElementById('totalActive').textContent = data.total_active;
            document.getElementById('pendingCount').textContent = data.pending;
            document.getElementById('completedCount').textContent = data.completed;
            document.getElementById('archivedCount').textContent = data.archived;
            document.getElementById('completionRate').innerHTML =
                `${data.completion_rate}<span style="font-size: 0.6em;">%</span>`;
            document.getElementById('avgPerDay').textContent =
                `Avg: ${data.avg_per_day} tasks/day`;

            // Update productivity badge
            const badge = document.getElementById('productivityBadge');
            const level = data.productivity_level.toLowerCase();
            badge.className = `productivity-badge ${level}`;
            badge.textContent = `🚀 ${data.productivity_level}`;

            // Create charts
            createStatusChart(data);
            createDailyChart(data);
            createSummaryChart(data);
        }

        function createStatusChart(data) {
            const ctx = document.getElementById('statusChart').getContext('2d');

            if (statusChart) statusChart.destroy();

            statusChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'Completed'],
                    datasets: [{
                        data: [data.pending, data.completed],
                        backgroundColor: [
                            'rgba(255, 193, 7, 0.8)',
                            'rgba(76, 175, 80, 0.8)'
                        ],
                        borderColor: [
                            'rgba(255, 193, 7, 1)',
                            'rgba(76, 175, 80, 1)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: { size: 14 }
                            }
                        }
                    }
                }
            });
        }

        function createDailyChart(data) {
            const ctx = document.getElementById('dailyChart').getContext('2d');
            const dates = Object.keys(data.daily_data);
            const counts = Object.values(data.daily_data);

            if (dailyChart) dailyChart.destroy();

            dailyChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates.map(d => new Date(d).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })),
                    datasets: [{
                        label: 'Tasks Created',
                        data: counts,
                        borderColor: 'rgba(102, 126, 234, 1)',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 6,
                        pointBackgroundColor: 'rgba(102, 126, 234, 1)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                font: { size: 14 },
                                padding: 20
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            min: 0,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        function createSummaryChart(data) {
            const ctx = document.getElementById('summaryChart').getContext('2d');

            if (summaryChart) summaryChart.destroy();

            summaryChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Active', 'Archived', 'Total'],
                    datasets: [{
                        label: 'Task Count',
                        data: [data.total_active, data.archived, data.total_all_time],
                        backgroundColor: [
                            'rgba(124, 179, 212, 0.8)',
                            'rgba(200, 200, 200, 0.8)',
                            'rgba(102, 126, 234, 0.8)'
                        ],
                        borderColor: [
                            'rgba(124, 179, 212, 1)',
                            'rgba(200, 200, 200, 1)',
                            'rgba(102, 126, 234, 1)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                font: { size: 14 },
                                padding: 20
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            min: 0,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Load insights on page load
        document.addEventListener('DOMContentLoaded', loadInsights);
    </script>
</body>

</html>