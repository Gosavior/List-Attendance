<?php
 

header('Content-Type: application/json');
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';


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

if (!isset($input['locations']) || !is_array($input['locations'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing locations array']);
    exit;
}

try {
    $successCount = 0;
    $failedCount = 0;
    $errors = [];
    
    
    foreach ($input['locations'] as $index => $location) {
        
        if (!isset($location['latitude']) || !isset($location['longitude']) || 
            !isset($location['attendance_id']) || !isset($location['timestamp'])) {
            $failedCount++;
            $errors[] = "Location #$index: Missing required fields";
            continue;
        }
        
        
        $stmt = $pdo->prepare("SELECT id FROM attendances WHERE id = ? AND user_id = ?");
        $stmt->execute([$location['attendance_id'], $userId]);
        if (!$stmt->fetch()) {
            $failedCount++;
            $errors[] = "Location #$index: Invalid attendance_id";
            continue;
        }
        
        
        $stmt = $pdo->prepare("
            SELECT tracking_enabled FROM user_tracking_settings WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($settings && $settings['tracking_enabled'] == 0) {
            $failedCount++;
            $errors[] = "Location #$index: Tracking disabled for user";
            continue;
        }
        
        
        $timestamp = strtotime($location['timestamp']);
        $now = time();
        if (($now - $timestamp) > 86400) { 
            $failedCount++;
            $errors[] = "Location #$index: Timestamp too old (>24h)";
            continue;
        }
        
        
        $stmt = $pdo->query("SELECT min_accuracy_meters FROM tracking_system_settings LIMIT 1");
        $systemSettings = $stmt->fetch(PDO::FETCH_ASSOC);
        $minAccuracy = $systemSettings['min_accuracy_meters'] ?? 50;
        
        
        $accuracy = $location['accuracy'] ?? 999;
        if ($accuracy > $minAccuracy) {
            $failedCount++;
            $errors[] = "Location #$index: Accuracy too low ($accuracy > $minAccuracy)";
            continue;
        }
        
        
        $trackedTime = new DateTime($location['timestamp']);
        $isBreakTime = isBreakTime($trackedTime);
        
        
        $stmt = $pdo->prepare("
            INSERT INTO tracking_locations 
            (user_id, attendance_id, latitude, longitude, accuracy, speed, heading, 
             battery_level, is_break_time, tracked_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $location['attendance_id'],
            $location['latitude'],
            $location['longitude'],
            $accuracy,
            $location['speed'] ?? null,
            $location['heading'] ?? null,
            $location['battery'] ?? null,
            $isBreakTime ? 1 : 0,
            $location['timestamp']
        ]);
        
        
        if (!$isBreakTime) {
            checkGeofencing($pdo, $userId, $location['attendance_id'], 
                          $location['latitude'], $location['longitude']);
        }
        
        $successCount++;
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Synced $successCount locations",
        'summary' => [
            'total' => count($input['locations']),
            'success' => $successCount,
            'failed' => $failedCount
        ],
        'errors' => $errors
    ]);
    
} catch (PDOException $e) {
    error_log("Offline sync error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

 
function isBreakTime($datetime) {
    global $pdo;
    
    $stmt = $pdo->query("SELECT break_start_time, break_end_time FROM tracking_system_settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings) {
        return false;
    }
    
    $currentTime = $datetime->format('H:i:s');
    return ($currentTime >= $settings['break_start_time'] && 
            $currentTime <= $settings['break_end_time']);
}

 
function checkGeofencing($pdo, $userId, $attendanceId, $lat, $lon) {
    
    $stmt = $pdo->prepare("SELECT id, name, center_latitude, center_longitude, radius_meters FROM geofence_zones WHERE is_active = 1");
    $stmt->execute();
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($zones as $zone) {
        $distance = calculateDistance(
            $lat, $lon,
            $zone['center_latitude'], $zone['center_longitude']
        );
        
        
        if ($distance > $zone['radius_meters']) {
            $stmt = $pdo->prepare("
                SELECT id FROM geofence_alerts 
                WHERE user_id = ? AND geofence_zone_id = ? 
                  AND alert_type = 'exit'
                  AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            ");
            $stmt->execute([$userId, $zone['id']]);
            
            if (!$stmt->fetch()) {
                
                $stmt = $pdo->prepare("
                    INSERT INTO geofence_alerts 
                    (user_id, attendance_id, geofence_zone_id, alert_type, latitude, longitude, distance_from_center)
                    VALUES (?, ?, ?, 'exit', ?, ?, ?)
                ");
                $stmt->execute([$userId, $attendanceId, $zone['id'], $lat, $lon, $distance]);
            }
        }
    }
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
