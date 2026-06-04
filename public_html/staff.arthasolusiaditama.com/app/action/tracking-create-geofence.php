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


if (!isset($input['name']) || !isset($input['latitude']) || !isset($input['longitude']) || !isset($input['radius'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}


$name = trim($input['name']);
$latitude = (float)$input['latitude'];
$longitude = (float)$input['longitude'];
$radius = (int)$input['radius'];

if (empty($name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nama lokasi tidak boleh kosong']);
    exit;
}

if ($radius < 50 || $radius > 5000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Radius harus antara 50-5000 meter']);
    exit;
}


if ($latitude < -90 || $latitude > 90) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Latitude tidak valid (-90 sampai 90)']);
    exit;
}

if ($longitude < -180 || $longitude > 180) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Longitude tidak valid (-180 sampai 180)']);
    exit;
}

try {
    
    $stmt = $pdo->prepare("SELECT id FROM geofence_zones WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nama lokasi sudah digunakan']);
        exit;
    }
    
    
    $stmt = $pdo->prepare("
        INSERT INTO geofence_zones 
        (name, latitude, longitude, radius_meters, is_active, created_at)
        VALUES (?, ?, ?, ?, 1, NOW())
    ");
    
    $stmt->execute([$name, $latitude, $longitude, $radius]);
    $newId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Lokasi berhasil ditambahkan',
        'zone' => [
            'id' => $newId,
            'name' => $name,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_meters' => $radius,
            'is_active' => true
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Create geofence DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Create geofence error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'details' => $e->getMessage()
    ]);
}
