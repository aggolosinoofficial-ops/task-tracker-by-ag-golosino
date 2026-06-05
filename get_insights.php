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
    }
    return 'Starting';
}

try {
    $user_id = checkAuth();
    if (!$user_id) {
        http_response_code(401);
        throw new Exception('Not authenticated');
    }

    $pending_count = 0;
    $completed_count = 0;
    $archived_count = 0;

    $total_all_time = 0;
    $completion_rate = 0; // numeric percent
    $avg_per_day = 0;
    $productivity_level = 'Starting';

    $daily_data = []; // YYYY-MM-DD => count

    $source = 'xml';

    // STEP 1: Try XML first (PRIMARY)
    try {
        $sync = getXMLSyncHandler();
        $xml_tasks = $sync->getTasksFromXML($user_id);

        $all_dates = [];
        if ($xml_tasks && is_array($xml_tasks)) {
            foreach ($xml_tasks as $task) {
                $status = $task['status'] ?? 'pending';
                if ($status === 'pending') {
                    $pending_count++;
                } elseif ($status === 'completed') {
                    $completed_count++;
                }

                $createdAt = $task['created_at'] ?? null;
                if ($createdAt) {
                    $dt = null;
                    try {
                        $dt = new DateTime($createdAt);
                    } catch (Exception $e) {
                        $dt = null;
                    }

                    if ($dt) {
                        $day = $dt->format('Y-m-d');
                        $daily_data[$day] = ($daily_data[$day] ?? 0) + 1;
                        $all_dates[] = $day;
                    }
                }
            }
        }

        // archived count from XML
        $xml_path = __DIR__ . '/archive_tasks.xml';
        if (file_exists($xml_path)) {
            $xml = simplexml_load_file($xml_path);
            if ($xml) {
                foreach ($xml->task as $task) {
                    if ((int)$task->user_id === (int)$user_id) {
                        $archived_count++;
                    }
                }
            }
        }

        $total_active = $pending_count + $completed_count;
        $total_all_time = $total_active + $archived_count;

        // completion rate (active only)
        $completion_rate = $total_active > 0 ? round(($completed_count / $total_active) * 100, 2) : 0;

        // avg per day over the last 7 days based on available xml data
        // (frontend only needs avg_per_day + daily_data; keep it simple)
        $days_span = 7;
        $sum_last_7 = 0;
        $today = new DateTime('today');
        for ($i = 0; $i < $days_span; $i++) {
            $d = (clone $today)->modify('-' . $i . ' days')->format('Y-m-d');
            $sum_last_7 += (int)($daily_data[$d] ?? 0);
        }
        $avg_per_day = $days_span > 0 ? round($sum_last_7 / $days_span, 2) : 0;

        $productivity_level = getProductivityLevel($completion_rate, $avg_per_day);

    } catch (Exception $e) {
        error_log('[Insights] XML read failed: ' . $e->getMessage());
    }

    // STEP 2: Fallback to DB if XML produced no data at all
    if ($pending_count === 0 && $completed_count === 0 && defined('DB_AVAILABLE') && DB_AVAILABLE && isset($conn)) {
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

    $total_active = $pending_count + $completed_count;
    $total_all_time = $total_active + $archived_count;
    $completion_rate = $total_active > 0 ? round(($completed_count / $total_active) * 100, 2) : 0;

    // If daily_data still empty, compute a minimal default daily_data last 7 days = 0
    if (empty($daily_data)) {
        $today = new DateTime('today');
        for ($i = 0; $i < 7; $i++) {
            $d = (clone $today)->modify('-' . $i . ' days')->format('Y-m-d');
            $daily_data[$d] = 0;
        }
    }

    // Recompute avg_per_day based on daily_data (works for both xml and db)
    $days_span = 7;
    $sum_last_7 = 0;
    $today = new DateTime('today');
    for ($i = 0; $i < $days_span; $i++) {
        $d = (clone $today)->modify('-' . $i . ' days')->format('Y-m-d');
        $sum_last_7 += (int)($daily_data[$d] ?? 0);
    }
    $avg_per_day = $days_span > 0 ? round($sum_last_7 / $days_span, 2) : 0;

    $productivity_level = getProductivityLevel($completion_rate, $avg_per_day);

    // IMPORTANT: insights.php expects flat properties (not nested under `data`).
    echo json_encode([
        'success' => true,
        'total_all_time' => (int)$total_all_time,
        'total_active' => (int)$total_active,
        'pending' => (int)$pending_count,
        'completed' => (int)$completed_count,
        'archived' => (int)$archived_count,
        'completion_rate' => $completion_rate,
        'avg_per_day' => $avg_per_day,
        'productivity_level' => $productivity_level,
        'daily_data' => $daily_data,
        'source' => $source
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
