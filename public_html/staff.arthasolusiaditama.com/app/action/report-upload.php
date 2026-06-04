<?php
@date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../auth/auth.php';

header('Content-Type: application/json; charset=utf-8');


$roleStr = strtolower(trim($user['role'] ?? ($_SESSION['role'] ?? '')));
$isAdmin = ($roleStr !== '') && (preg_match('/admin|administrator/', $roleStr) === 1);
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$reportsDir = dirname(__DIR__, 2) . '/public/reports/';
$indexFile = $reportsDir . 'index.json';

$allowedSections = [
    'Artha Solusi Aditama',
    'GMS Report'
];

$section = trim($_POST['section'] ?? '');
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');

if (!$section || !in_array($section, $allowedSections, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid section']);
    exit;
}

if (empty($_FILES['report_file']) || $_FILES['report_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}


function slugify($s) {
    $s = preg_replace('/[^A-Za-z0-9\- ]/', '', $s);
    $s = trim(preg_replace('/\s+/', '_', $s));
    return $s;
}

$sectionSlug = slugify($section);
$targetDir = $reportsDir . $sectionSlug . '/';
if (!is_dir($targetDir)) {
    @mkdir($targetDir, 0755, true);
}

$origName = $_FILES['report_file']['name'];
$ext = pathinfo($origName, PATHINFO_EXTENSION);
$basename = pathinfo($origName, PATHINFO_FILENAME);
$basename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $basename);
$filename = $basename . '_' . time() . '.' . $ext;
$targetPath = $targetDir . $filename;

if (!move_uploaded_file($_FILES['report_file']['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save uploaded file']);
    exit;
}


$id = uniqid('rpt_', true);
$created_at = date('Y-m-d H:i:s');
$urlPath = '/public/reports/' . $sectionSlug . '/' . $filename;

$newEntry = [
    'id' => $id,
    'title' => $title ?: $basename,
    'description' => $description ?: '',
    'section' => $section,
    'filename' => $filename,
    'url' => $urlPath,
    'creator_id' => $_SESSION['user_id'] ?? null,
    'creator_name' => $user['full_name'] ?? ($_SESSION['full_name'] ?? 'Admin'),
    'created_at' => $created_at,
    'deleted' => false
];


$index = [];
if (file_exists($indexFile)) {
    $raw = @file_get_contents($indexFile);
    $arr = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($arr)) {
        $index = $arr;
    }
}

$index[] = $newEntry;
if (@file_put_contents($indexFile, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    
    @unlink($targetPath);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update index file']);
    exit;
}

echo json_encode(['success' => true, 'entry' => $newEntry]);
exit;
