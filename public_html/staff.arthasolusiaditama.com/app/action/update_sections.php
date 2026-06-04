<?php
@date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../auth/auth.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}


$role = strtolower(trim($_SESSION['role'] ?? ''));
if ($role === '' || !preg_match('/admin|administrator/', $role)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$baseDir = $_SERVER['DOCUMENT_ROOT'] . '/storage/uploads/reports/';
if (!is_dir($baseDir)) {
    if (!mkdir($baseDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Cannot create storage dir']);
        exit;
    }
}
$file = $baseDir . 'sections.json';
$sections = [];
if (file_exists($file)) {
    $raw = file_get_contents($file);
    $arr = json_decode($raw, true);
    if (is_array($arr)) $sections = $arr;
}

$payload = json_decode(file_get_contents('php://input'), true);
$action = trim($payload['action'] ?? '');
$name = trim($payload['name'] ?? '');

if ($action === 'add') {
    if ($name === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nama section diperlukan']);
        exit;
    }
    
    foreach ($sections as $s) {
        if (strcasecmp($s, $name) === 0) {
            echo json_encode(['success' => true, 'sections' => $sections]);
            exit;
        }
    }
    $sections[] = $name;
    file_put_contents($file, json_encode($sections, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    echo json_encode(['success' => true, 'sections' => $sections]);
    exit;
}

if ($action === 'delete') {
    if ($name === '') { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Nama section diperlukan']); exit; }
    $new = [];
    foreach ($sections as $s) { if (strcasecmp($s,$name) !== 0) $new[] = $s; }
    $sections = $new;
    file_put_contents($file, json_encode($sections, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    echo json_encode(['success'=>true,'sections'=>$sections]); exit;
}

if ($action === 'rename') {
    $newName = trim($payload['new_name'] ?? '');
    if ($name === '' || $newName === '') { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Nama lama dan baru diperlukan']); exit; }
    foreach ($sections as &$s) { if (strcasecmp($s,$name) === 0) $s = $newName; }
    file_put_contents($file, json_encode($sections, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    echo json_encode(['success'=>true,'sections'=>$sections]); exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);

