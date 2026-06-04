<?php
 
header('Content-Type: application/json');
header('Cache-Control: no-cache');

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

if (empty($input['latitude']) || empty($input['longitude'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing latitude/longitude']);
    exit;
}

$lat = (float)$input['latitude'];
$lng = (float)$input['longitude'];
$accuracy = isset($input['accuracy']) ? (float)$input['accuracy'] : null;
$locationName = isset($input['location_name']) ? substr(trim($input['location_name']), 0, 255) : null;


if ($accuracy !== null && $accuracy > 200) {
    echo json_encode(['success' => false, 'message' => 'Accuracy too low', 'accuracy' => $accuracy]);
    exit;
}

try {
    
    $stmt = $pdo->prepare("
        SELECT id, latitude, longitude FROM gps_logs 
        WHERE user_id = ? AND timestamp > DATE_SUB(NOW(), INTERVAL 2 MINUTE)
        ORDER BY timestamp DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    $recent = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($recent) {
        
        $dist = 6371000 * 2 * asin(sqrt(
            pow(sin(deg2rad(($recent['latitude'] - $lat) / 2)), 2) +
            cos(deg2rad($lat)) * cos(deg2rad($recent['latitude'])) *
            pow(sin(deg2rad(($recent['longitude'] - $lng) / 2)), 2)
        ));
        if ($dist < 10) {
            echo json_encode(['success' => true, 'message' => 'Skipped (no significant movement)', 'distance' => round($dist, 1)]);
            exit;
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO gps_logs (user_id, latitude, longitude, accuracy, location_name, timestamp) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$userId, $lat, $lng, $accuracy, $locationName]);

    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
