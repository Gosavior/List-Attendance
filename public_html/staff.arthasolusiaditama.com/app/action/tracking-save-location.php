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


$required = ['latitude', 'longitude', 'attendance_id'];
foreach ($required as $field) {
    if (!isset($input[$field]) || $input[$field] === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

function tracking_setting($pdo, $key, $default = null) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM tracking_system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

try {
    
    $stmt = $pdo->prepare("
        SELECT id, check_in_time FROM attendances 
        WHERE id = ? AND user_id = ? AND DATE(check_in_time) = CURDATE()
    ");
    $stmt->execute([$input['attendance_id'], $userId]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$attendance) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid attendance record']);
        exit;
    }

    
    $stmt = $pdo->prepare(" 
        SELECT id FROM tracking_sessions 
        WHERE attendance_id = ? AND user_id = ? AND is_active = 1
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$input['attendance_id'], $userId]);
    $activeSession = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($activeSession) {
        $sessionId = (int)$activeSession['id'];
    } else {
        $sessionStartCol = db_first_existing_column($pdo, 'tracking_sessions', ['start_time', 'started_at']);
        $sessionInsertCols = ['user_id', 'attendance_id', 'is_active'];
        $sessionInsertVals = ['?', '?', '1'];
        $sessionInsertParams = [$userId, $input['attendance_id']];

        if ($sessionStartCol) {
            $sessionInsertCols[] = $sessionStartCol;
            $sessionInsertVals[] = '?';
            $sessionInsertParams[] = $attendance['check_in_time'];
        }

        $sql = 'INSERT INTO tracking_sessions (`' . implode('`, `', $sessionInsertCols) . '`) VALUES (' . implode(', ', $sessionInsertVals) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($sessionInsertParams);
        $sessionId = (int)$pdo->lastInsertId();
    }
    
    
    $minAccuracy = (int)tracking_setting($pdo, 'min_accuracy_meters', 50);
    
    $accuracy = isset($input['accuracy']) ? (float)$input['accuracy'] : null;
    if ($accuracy !== null && $accuracy > $minAccuracy) {
        echo json_encode([
            'success' => false, 
            'message' => 'GPS accuracy too low',
            'required_accuracy' => $minAccuracy,
            'current_accuracy' => $accuracy
        ]);
        exit;
    }
    
    
    $isBreakTime = false;
    $breakStart = (string)tracking_setting($pdo, 'break_time_start', '12:00:00');
    $breakEnd = (string)tracking_setting($pdo, 'break_time_end', '13:00:00');
    if ($breakStart !== '' && $breakEnd !== '') {
        $currentTime = date('H:i:s');
        $isBreakTime = ($currentTime >= $breakStart && $currentTime <= $breakEnd);
    }
    
    
    
    $trackedAt = date('Y-m-d H:i:s');
    if (isset($input['timestamp']) && !empty($input['timestamp'])) {
        try {
            $dt = new DateTime($input['timestamp']);
            $trackedAt = $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            error_log("[tracking-save-location] Invalid timestamp format: {$input['timestamp']}, using current time");
        }
    }
    
    
    $batteryLevel = null;
    if (isset($input['battery']) && $input['battery'] > 0) {
        $batteryLevel = (int)$input['battery'];
    }
    
    
    $locationColumns = db_table_columns($pdo, 'tracking_locations');
    if (empty($locationColumns)) {
        throw new Exception('tracking_locations table not found or inaccessible');
    }

    $insertCols = [];
    $insertVals = [];
    $executeParams = [];

    $appendColumn = function ($name, $value) use (&$insertCols, &$insertVals, &$executeParams, $locationColumns) {
        if (in_array($name, $locationColumns, true)) {
            $insertCols[] = $name;
            $insertVals[] = '?';
            $executeParams[] = $value;
        }
    };

    $appendColumn('session_id', $sessionId);
    $appendColumn('attendance_id', $input['attendance_id']);
    $appendColumn('user_id', $userId);
    $appendColumn('latitude', $input['latitude']);
    $appendColumn('longitude', $input['longitude']);
    $appendColumn('accuracy', $accuracy);
    $appendColumn('speed', isset($input['speed']) ? (float)$input['speed'] : null);
    $appendColumn('heading', isset($input['heading']) ? (float)$input['heading'] : null);
    $appendColumn('address', isset($input['address']) ? $input['address'] : null);
    $appendColumn('battery_level', $batteryLevel);
    $appendColumn('is_break_time', $isBreakTime ? 1 : 0);
    $appendColumn('is_inside_geofence', null);

    $timeCol = db_first_existing_column($pdo, 'tracking_locations', ['tracked_at', 'recorded_at']);
    if ($timeCol) {
        $appendColumn($timeCol, $trackedAt);
    }

    if (empty($insertCols)) {
        throw new Exception('No compatible columns found for tracking_locations insert');
    }

    $sql = 'INSERT INTO tracking_locations (`' . implode('`, `', $insertCols) . '`) VALUES (' . implode(', ', $insertVals) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($executeParams);
    
    $locationId = $pdo->lastInsertId();
    
    
    $sessionSet = [];
    $sessionParams = [];
    if (db_table_has_column($pdo, 'tracking_sessions', 'locations_count')) {
        $sessionSet[] = 'locations_count = COALESCE(locations_count, 0) + 1';
    }
    if (db_table_has_column($pdo, 'tracking_sessions', 'total_points')) {
        $sessionSet[] = 'total_points = COALESCE(total_points, 0) + 1';
    }
    if (db_table_has_column($pdo, 'tracking_sessions', 'updated_at')) {
        $sessionSet[] = 'updated_at = NOW()';
    }
    if (!empty($sessionSet)) {
        $sql = 'UPDATE tracking_sessions SET ' . implode(', ', $sessionSet) . ' WHERE id = ?';
        $sessionParams[] = $sessionId;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($sessionParams);
    }
    
    
    $alerts = [];
    try {
        $enableGeofence = ((string)tracking_setting($pdo, 'enable_geofencing', '0') === '1');
        if ($enableGeofence && db_table_exists($pdo, 'geofence_zones')) {
            $skipCheck = false;
            if (db_table_exists($pdo, 'user_tracking_settings')) {
                $stmt = $pdo->prepare('SELECT skip_geofence_check FROM user_tracking_settings WHERE user_id = ? LIMIT 1');
                $stmt->execute([$userId]);
                $skipRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($skipRow) {
                    $skipCheck = (bool)$skipRow['skip_geofence_check'];
                }
            }

            if (!$skipCheck) {
                $stmt = $pdo->query('SELECT id, name, latitude, longitude, radius_meters FROM geofence_zones WHERE is_active = 1');
                $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $insideAnyZone = false;
                $nearest = null;
                foreach ($zones as $zone) {
                    $distance = calculateDistance(
                        $input['latitude'],
                        $input['longitude'],
                        $zone['latitude'],
                        $zone['longitude']
                    );

                    if ($nearest === null || $distance < $nearest['distance']) {
                        $nearest = [
                            'zone_name' => $zone['name'],
                            'distance' => round($distance, 2)
                        ];
                    }

                    if ($distance <= (float)$zone['radius_meters']) {
                        $insideAnyZone = true;
                        break;
                    }
                }

                if (!$insideAnyZone && !$isBreakTime && db_table_exists($pdo, 'geofence_alerts')) {
                    $alertColumns = db_table_columns($pdo, 'geofence_alerts');
                    if (!empty($alertColumns)) {
                        $insertAlertCols = [];
                        $insertAlertVals = [];
                        $insertAlertParams = [];

                        $appendAlert = function ($name, $value) use (&$insertAlertCols, &$insertAlertVals, &$insertAlertParams, $alertColumns) {
                            if (in_array($name, $alertColumns, true)) {
                                $insertAlertCols[] = $name;
                                $insertAlertVals[] = '?';
                                $insertAlertParams[] = $value;
                            }
                        };

                        $appendAlert('user_id', $userId);
                        $appendAlert('attendance_id', $input['attendance_id']);
                        $appendAlert('geofence_id', 1);
                        $appendAlert('geofence_zone_id', 1);
                        $appendAlert('alert_type', 'outside_all');
                        $appendAlert('latitude', $input['latitude']);
                        $appendAlert('longitude', $input['longitude']);
                        if ($nearest && in_array('distance_from_center', $alertColumns, true)) {
                            $appendAlert('distance_from_center', $nearest['distance']);
                        }

                        if (!empty($insertAlertCols)) {
                            $sql = 'INSERT INTO geofence_alerts (`' . implode('`, `', $insertAlertCols) . '`) VALUES (' . implode(', ', $insertAlertVals) . ')';
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($insertAlertParams);
                        }
                    }
                }

                if ($nearest && !$insideAnyZone) {
                    $alerts[] = [
                        'type' => 'outside_geofence',
                        'zone_name' => $nearest['zone_name'],
                        'distance' => $nearest['distance'],
                        'message' => 'You are outside all designated work areas'
                    ];
                }
            }
        }
    } catch (Exception $e) {
        error_log('[tracking-save-location] Geofence check error: ' . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Location saved successfully',
        'location_id' => $locationId,
        'is_break_time' => $isBreakTime,
        'alerts' => $alerts,
        'geofence_alert' => count($alerts) > 0 ? $alerts[0] : null
    ]);
    
} catch (PDOException $e) {
    error_log("[tracking-save-location] Database error: " . $e->getMessage());
    error_log("[tracking-save-location] Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error', 'details' => $e->getMessage()]);
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
