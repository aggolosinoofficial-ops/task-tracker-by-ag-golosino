<?php
/**
 * Get User Task Insights - XML-First
 * Provides task analytics
 * PRIMARY: XML (fast, no DB required)
 * SECONDARY: DB fallback
 */

include 'auth_check.php';
include 'xml_sync_handler.php';

header('Content-Type: application/json');

try {
    $user_id = checkAuth();
    if (!$user_id) {
        http_response_code(401);
        throw new Exception('Not authenticated');
    }
    
    $pending_count = 0;
    $completed_count = 0;
    $archived_count = 0;
    $source = 'xml';
    
    // STEP 1: Try XML first (PRIMARY)
    try {
        $sync = getXMLSyncHandler();
        $xml_tasks = $sync->getTasksFromXML($user_id);
        
        if ($xml_tasks && is_array($xml_tasks)) {
            foreach ($xml_tasks as $task) {
                if ($task['status'] === 'pending') $pending_count++;
                elseif ($task['status'] === 'completed') $completed_count++;
            }
            
            // Get archived count from XML
            $xml_path = __DIR__ . '/archive_tasks.xml';
            if (file_exists($xml_path)) {
                $xml = simplexml_load_file($xml_path);
                if ($xml) {
                    foreach ($xml->task as $task) {
                        if ((int)$task->user_id === $user_id) $archived_count++;
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log('[Insights] XML read failed: ' . $e->getMessage());
    }
    
    // STEP 2: Fallback to DB if XML failed
    if ($pending_count == 0 && $completed_count == 0 && defined('DB_AVAILABLE') && DB_AVAILABLE && isset($conn)) {
        try {
            $stmt = $conn->prepare(
                "SELECT 
                    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed
                 FROM " . DB_NAME . "." . DB_TABLE_TASKS . " WHERE user_id = ?"
            );
            if ($stmt) {
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $pending_count = (int)($row['pending'] ?? 0);
                    $completed_count = (int)($row['completed'] ?? 0);
                }
                $stmt->close();
                
                $archive_stmt = $conn->prepare("SELECT COUNT(*) as count FROM " . DB_NAME . ".archive_tasks WHERE user_id = ?");
                if ($archive_stmt) {
                    $archive_stmt->bind_param('i', $user_id);
                    $archive_stmt->execute();
                    $archive_result = $archive_stmt->get_result();
                    if ($archive_result && $archive_result->num_rows > 0) {
                        $archived_count = (int)$archive_result->fetch_assoc()['count'];
                    }
                    $archive_stmt->close();
                }
                $source = 'database';
            }
        } catch (Exception $e) {
            error_log('[Insights] DB fallback failed: ' . $e->getMessage());
        }
    }
    
    $total_tasks = $pending_count + $completed_count;
    $completion_rate = $total_tasks > 0 ? round(($completed_count / $total_tasks) * 100, 2) : 0;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'pending' => $pending_count,
            'completed' => $completed_count,
            'archived' => $archived_count,
            'total' => $total_tasks,
            'completion_rate' => $completion_rate . '%'
        ],
        'source' => $source
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
    if ($pending_count === 0 && $completed_count === 0 && isset($conn) && $conn->ping()) {
        try {
            // Count pending tasks
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM " . DB_NAME . "." . DB_TABLE_TASKS . " WHERE user_id = ? AND status = 'pending'");
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $pending_count = intval($row['count']);
                }
                $stmt->close();
            }

            // Count completed tasks
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM " . DB_NAME . "." . DB_TABLE_TASKS . " WHERE user_id = ? AND status = 'completed'");
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $completed_count = intval($row['count']);
                }
                $stmt->close();
            }

            // Count archived tasks
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM " . DB_NAME . ".archive_tasks WHERE user_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $archived_count = intval($row['count']);
                }
                $stmt->close();
            }
            $data_source = 'database';
        } catch (Exception $e) {
            error_log('[GetInsights] Database fallback failed: ' . $e->getMessage());
        }
    }

    $total_active = $pending_count + $completed_count;
    $total_all = $total_active + $archived_count;

    // Calculate completion rate
    $completion_rate = ($total_active > 0) ? round(($completed_count / $total_active) * 100) : 0;

    // Get daily task creation data for last 7 days
    $daily_data = [];
    
    if ($data_source === 'xml' && $xml_tasks) {
        // Calculate from XML
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $daily_data[$date] = 0;
            foreach ($xml_tasks as $task) {
                if (date('Y-m-d', strtotime($task['created_at'])) === $date) {
                    $daily_data[$date]++;
                }
            }
        }
    } elseif (isset($conn) && $conn->ping()) {
        // Fallback to database
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM " . DB_NAME . "." . DB_TABLE_TASKS . " WHERE user_id = ? AND DATE(created_at) = ?");
            if ($stmt) {
                $stmt->bind_param("is", $user_id, $date);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $daily_data[$date] = intval($row['count']);
                } else {
                    $daily_data[$date] = 0;
                }
                $stmt->close();
            }
        }
    }

    // Calculate task creation trend
    $week_total = array_sum($daily_data);
    $avg_per_day = $week_total > 0 ? round($week_total / 7, 1) : 0;

    $insights = [
        'pending' => $pending_count,
        'completed' => $completed_count,
        'archived' => $archived_count,
        'total_active' => $total_active,
        'total_all_time' => $total_all,
        'completion_rate' => $completion_rate,
        'week_total' => $week_total,
        'avg_per_day' => $avg_per_day,
        'daily_data' => $daily_data,
        'productivity_level' => getProductivityLevel($completion_rate, $avg_per_day),
        'data_source' => $data_source
    ];

    echo json_encode($insights);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Determine productivity level based on metrics
 */
function getProductivityLevel($completion_rate, $avg_per_day)
{
    if ($completion_rate >= 80 && $avg_per_day >= 2) {
        return 'Excellent';
    } elseif ($completion_rate >= 60 && $avg_per_day >= 1) {
        return 'Good';
    } elseif ($completion_rate >= 40) {
        return 'Moderate';
    } elseif ($avg_per_day > 0) {
        return 'Active';
    } else {
        return 'Starting';
    }
}
?>