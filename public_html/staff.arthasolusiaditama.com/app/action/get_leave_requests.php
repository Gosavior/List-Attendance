<?php
@date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$roleStr = strtolower(trim($_SESSION['role'] ?? ''));
$isAdmin = $roleStr && preg_match('/admin|administrator/', $roleStr);
if (!$isAdmin) {
    echo json_encode(['requests' => []]);
    exit;
}

try {
    $limit = 10;
    $stmt = $pdo->prepare("SELECT lr.*, u.full_name AS user_name, u.username
                            FROM leave_requests lr
                            LEFT JOIN users u ON u.id = lr.user_id
                            WHERE lr.status = 'pending'
                            ORDER BY lr.created_at DESC
                            LIMIT $limit");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['requests' => $rows]);
} catch (Exception $e) {
    echo json_encode(['requests' => [], 'error' => $e->getMessage()]);
}
?>
