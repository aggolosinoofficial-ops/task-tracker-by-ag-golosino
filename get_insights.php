<?php
/**
 * Get User Task Insights
 * Provides analytics about user's tasks
 * - Requires authentication
 * - Returns per-user statistics
 * - Includes graphs data
 */

include 'auth_check.php';

header('Content-Type: application/json');

try {
    // Check if user is authenticated
    $user_id = checkAuth();
    if (!$user_id) {
        http_response_code(401);
        throw new Exception('Please log in');
    }

    // Get task counts
    $pending_count = 0;
    $completed_count = 0;
    $total_active = 0;
    $archived_count = 0;

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

    $total_active = $pending_count + $completed_count;
    $total_all = $total_active + $archived_count;

    // Calculate completion rate
    $completion_rate = ($total_active > 0) ? round(($completed_count / $total_active) * 100) : 0;

    // Get daily task creation data for last 7 days
    $daily_data = [];
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
        'productivity_level' => getProductivityLevel($completion_rate, $avg_per_day)
    ];

    echo json_encode($insights);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
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