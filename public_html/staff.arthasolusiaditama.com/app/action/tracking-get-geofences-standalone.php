<?php
 


header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');


require_once __DIR__ . '/../config/database.php';

try {
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS geofence_zones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            latitude DECIMAL(10, 8) NOT NULL,
            longitude DECIMAL(11, 8) NOT NULL,
            radius_meters INT NOT NULL DEFAULT 100,
            color VARCHAR(20) DEFAULT '#3b82f6',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    
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
        WHERE is_active = 1
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
        'count' => count($zones),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    error_log("[tracking-get-geofences-standalone] DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => 'Failed to fetch geofence zones'
    ]);
} catch (Exception $e) {
    error_log("[tracking-get-geofences-standalone] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => 'An unexpected error occurred'
    ]);
}
