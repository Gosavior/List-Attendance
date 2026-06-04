<?php
 
header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['administrator', 'direktur'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.username, u.avatar, u.role,
               COALESCE(gl.latitude, a.check_in_lat) as latitude,
               COALESCE(gl.longitude, a.check_in_lng) as longitude,
               COALESCE(gl.location_name, a.check_in_location) as location_name,
               COALESCE(gl.timestamp, a.check_in_time) as last_update,
               a.status,
               a.check_in_time
        FROM users u
        LEFT JOIN (
            SELECT g1.user_id, g1.latitude, g1.longitude, g1.location_name, g1.timestamp
            FROM gps_logs g1
            INNER JOIN (
                SELECT user_id, MAX(timestamp) as max_ts
                FROM gps_logs
                WHERE DATE(timestamp) = CURDATE()
                GROUP BY user_id
            ) g2 ON g1.user_id = g2.user_id AND g1.timestamp = g2.max_ts
        ) gl ON u.id = gl.user_id
        LEFT JOIN (
            SELECT user_id, check_in_lat, check_in_lng, check_in_location, check_in_time, status
            FROM attendances
            WHERE attendance_date = CURDATE()
        ) a ON u.id = a.user_id
        WHERE u.role NOT IN ('administrator','direktur')
        ORDER BY u.full_name ASC
    ");
    $stmt->execute();
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'staff' => $staff,
        'server_time' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
