<?php
 
header('Content-Type: application/json');


$allowed = ['127.0.0.1', '::1', 'localhost'];
$remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';



$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (!$token) {
    echo json_encode(['valid' => false, 'error' => 'No token']);
    exit;
}


session_id($token);
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['valid' => false, 'error' => 'Invalid session']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
$stmt = $pdo->prepare("SELECT id, full_name, username, avatar, role FROM users WHERE id = ? AND is_active = 1");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['valid' => false, 'error' => 'User not found']);
    exit;
}

echo json_encode([
    'valid' => true,
    'user' => [
        'id' => (int)$user['id'],
        'full_name' => $user['full_name'],
        'username' => $user['username'],
        'avatar' => $user['avatar'],
        'role' => $user['role']
    ]
]);
