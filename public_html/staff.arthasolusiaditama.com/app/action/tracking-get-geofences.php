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
        'error' => 'Access denied. Administrator only.'
    ]);
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT 
            id,
            name,
            description,
            latitude,
            longitude,
            radius_meters,
            color,
            is_active,
            created_at
        FROM geofence_zones
        ORDER BY name
    ");
    
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    
    foreach ($zones as &$zone) {
        $zone['latitude'] = (float)$zone['latitude'];
        $zone['longitude'] = (float)$zone['longitude'];
        $zone['radius_meters'] = (int)$zone['radius_meters'];
        $zone['is_active'] = (bool)$zone['is_active'];
    }
    
    echo json_encode([
        'success' => true,
        'zones' => $zones,
        'count' => count($zones)
    ]);
    
} catch (PDOException $e) {
    error_log("Get geofences DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Get geofences error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'details' => $e->getMessage()
    ]);
}
