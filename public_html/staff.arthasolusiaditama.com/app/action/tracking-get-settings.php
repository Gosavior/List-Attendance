<?php
 

header('Content-Type: application/json');
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';


if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM tracking_system_settings");
    $systemSettings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $systemSettings[$row['setting_key']] = $row['setting_value'];
    }
    
    
    $stmt = $pdo->prepare("
        SELECT tracking_mode, skip_geofence_check, custom_tracking_interval 
        FROM user_tracking_settings 
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $userSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    
    if (!$userSettings) {
        $userSettings = [
            'tracking_mode' => 'standard',
            'skip_geofence_check' => false,
            'custom_tracking_interval' => null
        ];
    }
    
    
    $trackingInterval = $userSettings['custom_tracking_interval'] 
        ? (int)$userSettings['custom_tracking_interval']
        : (int)($systemSettings['tracking_interval'] ?? 120);
    
    
    $stmt = $pdo->query("
        SELECT setting_key, setting_value 
        FROM company_settings 
        WHERE setting_key IN ('work_start_time', 'work_end_time')
    ");
    $workHours = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $workHours[$row['setting_key']] = $row['setting_value'];
    }
    
    
    $stmt = $pdo->prepare("
        SELECT id 
        FROM attendances 
        WHERE user_id = ? 
        AND DATE(check_in_time) = CURDATE() 
        AND check_out_time IS NULL
        ORDER BY check_in_time DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    
    $hasActiveSession = false;
    if ($attendance) {
        $stmt = $pdo->prepare("
            SELECT id FROM tracking_sessions 
            WHERE attendance_id = ? AND is_active = 1
        ");
        $stmt->execute([$attendance['id']]);
        $hasActiveSession = (bool)$stmt->fetch();
    }
    
    
    $shouldTrack = ($attendance !== false);
    
    echo json_encode([
        'success' => true,
        'settings' => [
            'tracking_interval_minutes' => $trackingInterval / 60,
            'tracking_interval_seconds' => $trackingInterval,
            'tracking_mode' => $userSettings['tracking_mode'],
            'enable_geofencing' => (bool)($systemSettings['enable_geofencing'] ?? 1),
            'skip_geofence_check' => (bool)$userSettings['skip_geofence_check'],
            'break_start_time' => $systemSettings['break_time_start'] ?? '12:00:00',
            'break_end_time' => $systemSettings['break_time_end'] ?? '13:00:00',
            'work_start_time' => $workHours['work_start_time'] ?? '08:00:00',
            'work_end_time' => $workHours['work_end_time'] ?? '17:00:00',
            'min_accuracy_meters' => (int)($systemSettings['min_accuracy_meters'] ?? 50),
            'offline_queue_enabled' => (bool)($systemSettings['offline_queue_enabled'] ?? 1),
            'should_track' => $shouldTrack
        ],
        'attendance' => $attendance,
        'has_active_session' => $hasActiveSession
    ]);
    
} catch (PDOException $e) {
    error_log("Get tracking settings error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
