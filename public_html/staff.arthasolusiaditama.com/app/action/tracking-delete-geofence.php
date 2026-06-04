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

if (!isset($input['zone_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing zone_id']);
    exit;
}

try {
    
    $stmt = $pdo->prepare("SELECT name FROM geofence_zones WHERE id = ?");
    $stmt->execute([$input['zone_id']]);
    $zone = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$zone) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Lokasi tidak ditemukan']);
        exit;
    }
    
    
    try {
        $stmt = $pdo->prepare("DELETE FROM geofence_alerts WHERE geofence_id = ?");
        $stmt->execute([$input['zone_id']]);
    } catch (PDOException $ignored) {
        
    }
    
    
    $stmt = $pdo->prepare("DELETE FROM geofence_zones WHERE id = ?");
    $stmt->execute([$input['zone_id']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Lokasi "' . $zone['name'] . '" berhasil dihapus'
    ]);
    
} catch (PDOException $e) {
    error_log("Delete geofence DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Delete geofence error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'details' => $e->getMessage()
    ]);
}
