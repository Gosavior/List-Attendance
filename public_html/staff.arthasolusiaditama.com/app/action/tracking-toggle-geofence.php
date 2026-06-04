<?php
 


header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');


session_start();
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrator') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Administrator only.'
    ]);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['zone_id']) || !isset($input['is_active'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE geofence_zones SET is_active = ? WHERE id = ?");
    $stmt->execute([
        $input['is_active'] ? 1 : 0,
        $input['zone_id']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Geofence zone updated'
    ]);
    
} catch (PDOException $e) {
    error_log("Toggle geofence DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Toggle geofence error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'details' => $e->getMessage()
    ]);
}
