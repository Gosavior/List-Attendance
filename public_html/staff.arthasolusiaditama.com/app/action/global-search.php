<?php
 
@date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 1) {
    echo json_encode(['success' => true, 'staff' => [], 'tools' => []]);
    exit;
}

$like = '%' . $q . '%';
$results = ['success' => true, 'staff' => [], 'tools' => []];


try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.username, u.role, u.is_active,
               d.name AS division_name
        FROM users u
        LEFT JOIN divisions d ON d.id = u.division_id
        WHERE u.is_active = 1
          AND (u.full_name LIKE :q1 OR u.username LIKE :q2 OR u.role LIKE :q3)
        ORDER BY u.full_name ASC
        LIMIT 8
    ");
    $stmt->execute([':q1' => $like, ':q2' => $like, ':q3' => $like]);
    $results['staff'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, full_name, username, role, is_active
            FROM users
            WHERE is_active = 1
              AND (full_name LIKE :q1 OR username LIKE :q2 OR role LIKE :q3)
            ORDER BY full_name ASC
            LIMIT 8
        ");
        $stmt->execute([':q1' => $like, ':q2' => $like, ':q3' => $like]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) { $row['division_name'] = null; }
        $results['staff'] = $rows;
    } catch (Throwable $e2) {
        $results['staff'] = [];
    }
}


try {
    $stmt = $pdo->prepare("
        SELECT id, name, code, current_status
        FROM tools
        WHERE (name LIKE :q1 OR code LIKE :q2)
        ORDER BY name ASC
        LIMIT 8
    ");
    $stmt->execute([':q1' => $like, ':q2' => $like]);
    $results['tools'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $results['tools'] = [];
}

echo json_encode($results);
