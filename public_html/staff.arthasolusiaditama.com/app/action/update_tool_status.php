<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}


if ($_SESSION['role'] !== 'administrator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

try {
    $tool_id = (int)($_POST['tool_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);
    $month = $_POST['month'] ?? date('Y-m');
    
    
    if (!$tool_id || !in_array($status, ['Good', 'Repair', 'Missing'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid input data']);
        exit;
    }
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'error' => 'User ID not provided']);
        exit;
    }
    
    
    $check_stmt = $pdo->prepare("
        SELECT id FROM monthly_checks 
        WHERE user_id = ? AND check_month = ?
    ");
    $check_stmt->execute([$user_id, $month]);
    $monthly_check = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    $monthly_check_id = null;
    if ($monthly_check) {
        $monthly_check_id = $monthly_check['id'];
    } else {
        
        $insert_stmt = $pdo->prepare("
            INSERT INTO monthly_checks (user_id, check_month, checked_by) 
            VALUES (?, ?, ?)
        ");
        $insert_stmt->execute([$user_id, $month, $_SESSION['user_id']]);
        $monthly_check_id = $pdo->lastInsertId();
    }
    
    
    $item_stmt = $pdo->prepare("
        INSERT INTO monthly_check_items (check_id, tool_id, status, notes) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), notes = VALUES(notes)
    ");
    $item_stmt->execute([$monthly_check_id, $tool_id, $status, $notes]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Tool status update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>