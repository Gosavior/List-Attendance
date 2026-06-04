<?php
 

header('Content-Type: application/json');
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/db-schema.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}


if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];


$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['attendance_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing attendance_id']);
    exit;
}

try {
    $sessionStartCol = db_first_existing_column($pdo, 'tracking_sessions', ['start_time', 'started_at']);
    $sessionEndCol = db_first_existing_column($pdo, 'tracking_sessions', ['end_time', 'ended_at']);

    
    $selectCols = ['id'];
    if ($sessionStartCol) {
        $selectCols[] = $sessionStartCol . ' AS session_start_time';
    }

    $stmt = $pdo->prepare(" 
        SELECT " . implode(', ', $selectCols) . "
        FROM tracking_sessions 
        WHERE attendance_id = ? AND user_id = ? AND is_active = 1
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$input['attendance_id'], $userId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        echo json_encode([
            'success' => true,
            'message' => 'No active tracking session found',
            'already_stopped' => true
        ]);
        exit;
    }
    
    
    $totalDuration = 0;
    $startValue = $session['session_start_time'] ?? null;
    if (!empty($startValue) && $startValue !== '0000-00-00 00:00:00') {
        $startTime = new DateTime($startValue);
        $endTime = new DateTime();
        $totalDuration = ($endTime->getTimestamp() - $startTime->getTimestamp()) / 60;
    }
    
    
    $breakDuration = 0;
    if (db_table_has_column($pdo, 'tracking_locations', 'is_break_time')) {
        $stmt = $pdo->prepare(" 
            SELECT COUNT(*) * 5 as break_minutes
            FROM tracking_locations 
            WHERE attendance_id = ? AND is_break_time = 1
        ");
        $stmt->execute([$input['attendance_id']]);
        $breakData = $stmt->fetch(PDO::FETCH_ASSOC);
        $breakDuration = $breakData['break_minutes'] ?? 0;
    }
    
    
    $trackedAtCol = db_first_existing_column($pdo, 'tracking_locations', ['tracked_at', 'recorded_at']);
    $orderByCol = $trackedAtCol ?: 'id';
    $stmt = $pdo->prepare("
        SELECT latitude, longitude 
        FROM tracking_locations 
        WHERE attendance_id = ?
        ORDER BY {$orderByCol} ASC
    ");
    $stmt->execute([$input['attendance_id']]);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalDistance = 0;
    for ($i = 1; $i < count($locations); $i++) {
        $totalDistance += calculateDistance(
            $locations[$i-1]['latitude'],
            $locations[$i-1]['longitude'],
            $locations[$i]['latitude'],
            $locations[$i]['longitude']
        );
    }
    $totalDistance = $totalDistance / 1000; 
    
    
    $setParts = ['is_active = 0'];
    $updateParams = [];

    if ($sessionEndCol) {
        $setParts[] = "{$sessionEndCol} = NOW()";
    }
    if (db_table_has_column($pdo, 'tracking_sessions', 'total_distance')) {
        $setParts[] = 'total_distance = ?';
        $updateParams[] = $totalDistance;
    }
    if (db_table_has_column($pdo, 'tracking_sessions', 'total_duration')) {
        $setParts[] = 'total_duration = ?';
        $updateParams[] = $totalDuration;
    }
    if (db_table_has_column($pdo, 'tracking_sessions', 'break_duration')) {
        $setParts[] = 'break_duration = ?';
        $updateParams[] = $breakDuration;
    }
    if (db_table_has_column($pdo, 'tracking_sessions', 'total_points')) {
        $setParts[] = 'total_points = ?';
        $updateParams[] = count($locations);
    }
    if (db_table_has_column($pdo, 'tracking_sessions', 'updated_at')) {
        $setParts[] = 'updated_at = NOW()';
    }

    $updateParams[] = $session['id'];
    $sql = 'UPDATE tracking_sessions SET ' . implode(', ', $setParts) . ' WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($updateParams);
    
    echo json_encode([
        'success' => true,
        'message' => 'Tracking session stopped',
        'total_distance' => round($totalDistance, 2),
        'session_summary' => [
            'total_duration_minutes' => round($totalDuration, 2),
            'break_duration_minutes' => $breakDuration,
            'work_duration_minutes' => round($totalDuration - $breakDuration, 2),
            'total_distance_km' => round($totalDistance, 2),
            'locations_tracked' => count($locations)
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Stop tracking session error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

 
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; 
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}
