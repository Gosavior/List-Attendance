<?php
 


header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');


session_start();
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/db-schema.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrator') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Access denied. Administrator only.'
    ]);
    exit;
}

try {
    $today = date('Y-m-d');

    $sessionStartCol = db_first_existing_column($pdo, 'tracking_sessions', ['start_time', 'started_at']);
    $locationTimeCol = db_first_existing_column($pdo, 'tracking_locations', ['tracked_at', 'recorded_at']);
    $locationColumns = db_table_columns($pdo, 'tracking_locations');

    $locExpr = function ($column, $fallback = 'NULL') use ($locationColumns) {
        if (in_array($column, $locationColumns, true)) {
            return "lt.`{$column}`";
        }
        return $fallback;
    };

    $sessionJoin = 'LEFT JOIN tracking_sessions ts ON a.id = ts.attendance_id';
    $params = [$today];
    if ($sessionStartCol) {
        $sessionJoin .= " AND DATE(ts.`{$sessionStartCol}`) = ?";
        $params[] = $today;
    }

    $jsonFields = [
        "'latitude', " . $locExpr('latitude'),
        "'longitude', " . $locExpr('longitude'),
        "'accuracy', " . $locExpr('accuracy'),
        "'speed', " . $locExpr('speed'),
        "'heading', " . $locExpr('heading'),
        "'battery_level', " . $locExpr('battery_level'),
        "'is_break_time', " . $locExpr('is_break_time', '0'),
        "'tracked_at', " . ($locationTimeCol ? "lt.`{$locationTimeCol}`" : 'NULL'),
    ];
    $jsonSelect = implode(",\n                    ", $jsonFields);
    
    
    $sql = "
        SELECT 
            u.id as user_id,
            u.full_name,
            u.email,
            a.id as attendance_id,
            a.check_in_time,
            a.check_out_time,
            ts.id as session_id,
            ts.total_distance as distance_traveled_km,
            ts.is_active as is_tracking,
            (
                SELECT JSON_OBJECT(
                    {$jsonSelect}
                )
                FROM tracking_locations lt
                WHERE lt.attendance_id = a.id
                ORDER BY " . ($locationTimeCol ? "lt.`{$locationTimeCol}`" : 'lt.id') . " DESC
                LIMIT 1
            ) as last_location
        FROM users u
        LEFT JOIN attendances a ON u.id = a.user_id AND a.attendance_date = ?
        {$sessionJoin}
        WHERE u.role != 'administrator'
        ORDER BY u.full_name
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    
    foreach ($staff as &$s) {
        if ($s['last_location']) {
            $s['last_location'] = json_decode($s['last_location'], true);
        }
        $s['is_tracking'] = (bool)$s['is_tracking'];
        
        $s['distance_traveled_km'] = $s['distance_traveled_km'] !== null ? (float)$s['distance_traveled_km'] : 0.0;
    }
    
    
    $activeTracking = 0;
    $onBreak = 0;
    foreach ($staff as $s) {
        if ($s['is_tracking']) {
            $activeTracking++;
            if ($s['last_location'] && $s['last_location']['is_break_time']) {
                $onBreak++;
            }
        }
    }
    
    
    $alertCount = 0;
    if (db_table_exists($pdo, 'geofence_alerts')) {
        $stmt = $pdo->prepare(" 
            SELECT COUNT(DISTINCT user_id) as alert_count
            FROM geofence_alerts
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        ");
        $stmt->execute();
        $alertData = $stmt->fetch(PDO::FETCH_ASSOC);
        $alertCount = (int)($alertData['alert_count'] ?? 0);
    }
    
    echo json_encode([
        'success' => true,
        'staff' => $staff,
        'summary' => [
            'total_staff' => count($staff),
            'active_tracking' => $activeTracking,
            'on_break' => $onBreak,
            'geofence_alerts' => $alertCount
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Get all locations DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Get all locations error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'details' => $e->getMessage()
    ]);
}
