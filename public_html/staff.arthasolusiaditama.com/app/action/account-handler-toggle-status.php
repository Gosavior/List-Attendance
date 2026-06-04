<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/audit-log.php';

if ($_SESSION['role'] !== 'administrator') {
    http_response_code(403); exit('Akses ditolak.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['to'])) {
    $userId = intval($_POST['user_id']);
    $to = intval($_POST['to']) ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    if ($stmt->execute([$to, $userId])) {
        auditLog($pdo, 'toggle_user_status', [
            'target_type' => 'user',
            'target_id' => $userId,
            'target_user_id' => $userId,
            'details' => ['is_active' => $to]
        ]);
        echo "OK";
    } else {
        echo "ERROR";
    }
    exit;
}
http_response_code(400); echo 'Invalid request.';
