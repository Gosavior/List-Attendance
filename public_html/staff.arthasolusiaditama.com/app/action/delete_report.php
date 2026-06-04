<?php
@date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../auth/auth.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}


$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!verify_csrf($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF']);
    exit;
}


$role = strtolower(trim($_SESSION['role'] ?? ''));
if ($role === '' || !preg_match('/admin|administrator/', $role)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$id = trim($body['id'] ?? '');
if ($id === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID is required']);
    exit;
}

$baseDir = $_SERVER['DOCUMENT_ROOT'] . '/storage/uploads/reports/';
$indexFile = $baseDir . 'index.json';
if (!file_exists($indexFile)) {
    echo json_encode(['success' => true]);
    exit;
}

$list = json_decode(file_get_contents($indexFile), true);
if (!is_array($list)) $list = [];

$found = false; $new = [];
foreach ($list as $item) {
    if (($item['id'] ?? '') === $id) {
        $found = true;
        $file = $baseDir . ($item['file'] ?? '');
        $recycleDir = $baseDir . 'recycle/';
        if (!is_dir($recycleDir)) {
            mkdir($recycleDir, 0755, true);
        }
        if ($file && is_file($file)) {
            $recycleFile = $recycleDir . basename($file);
            rename($file, $recycleFile);
            $item['file'] = 'recycle/' . basename($file);
        }
        $item['deleted'] = true;
        $item['deleted_at'] = date('d M Y H:i');
        $new[] = $item;
        continue;
    }
    $new[] = $item;
}

file_put_contents($indexFile, json_encode($new, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

echo json_encode(['success' => true, 'deleted' => $found, 'redirect_url' => '/dashboard.php?page=report&tab=recycle']);

