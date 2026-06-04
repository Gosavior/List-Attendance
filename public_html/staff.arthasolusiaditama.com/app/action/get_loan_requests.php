<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];


if ($role === 'administrator') {
    
    $sql = "SELECT tp.*, u1.full_name AS from_user_name, u2.full_name AS to_user_name, 
                   t.name AS tool_name, t.code AS tool_code
            FROM tool_permits tp
            JOIN users u1 ON tp.from_user_id = u1.id
            JOIN users u2 ON tp.to_user_id = u2.id
            JOIN tools t ON tp.tool_id = t.id
            WHERE tp.status = 'pending'
            ORDER BY tp.created_at DESC";
    $stmt = $pdo->query($sql);
} else {
    
    $sql = "SELECT tp.*, u1.full_name AS from_user_name, u2.full_name AS to_user_name, 
                   t.name AS tool_name, t.code AS tool_code
            FROM tool_permits tp
            JOIN users u1 ON tp.from_user_id = u1.id
            JOIN users u2 ON tp.to_user_id = u2.id
            JOIN tools t ON tp.tool_id = t.id
            WHERE tp.status = 'pending' 
            AND tp.to_user_id = ?
            AND tp.permit_type IN ('handover', 'return')  // Tambahkan return untuk user biasa
            ORDER BY tp.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
}

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);


foreach ($requests as &$r) {
    $r['reason'] = $r['reason'] ?: 'Tidak disebutkan';
    $r['from_user_name'] = $r['from_user_name'] ?: 'Administrator';
    $r['to_user_name'] = $r['to_user_name'] ?: 'Tidak diketahui';
}
unset($r);

echo json_encode(['requests' => $requests]);
?>