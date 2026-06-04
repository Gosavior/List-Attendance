<?php
 

header('Content-Type: application/json');
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    
    $stmt = $pdo->prepare("
        SELECT id FROM attendances 
        WHERE user_id = ? AND DATE(check_in_time) = CURDATE() AND check_out_time IS NULL
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$attendance) {
        echo json_encode(['error' => 'No active attendance found', 'user_id' => $userId]);
        exit;
    }
    
    
    $testData = [
        'user_id' => $userId,
        'attendance_id' => $attendance['id'],
        'latitude' => 1.10927560,
        'longitude' => 104.08797700,
        'accuracy' => 12.942,
        'speed' => null,
        'heading' => null,
        'address' => null,
        'is_break_time' => 0,
        'battery_level' => null, 
        'tracked_at' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode([
        'step' => 'Before INSERT',
        'test_data' => $testData
    ]) . "\n\n";
    
    $stmt = $pdo->prepare("
        INSERT INTO location_tracking (
            user_id, 
            attendance_id, 
            latitude, 
            longitude, 
            accuracy, 
            speed, 
            heading, 
            address,
            is_break_time,
            battery_level,
            tracked_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        $testData['user_id'],
        $testData['attendance_id'],
        $testData['latitude'],
        $testData['longitude'],
        $testData['accuracy'],
        $testData['speed'],
        $testData['heading'],
        $testData['address'],
        $testData['is_break_time'],
        $testData['battery_level'],
        $testData['tracked_at']
    ]);
    
    $locationId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Location inserted successfully',
        'location_id' => $locationId,
        'test_data' => $testData
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'sql_state' => $e->errorInfo[0] ?? null,
        'driver_code' => $e->errorInfo[1] ?? null,
        'driver_message' => $e->errorInfo[2] ?? null
    ], JSON_PRETTY_PRINT);
}
