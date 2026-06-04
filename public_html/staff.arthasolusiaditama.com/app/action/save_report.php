<?php
@date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/report-number.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}



$csrf = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_SERVER['HTTP_X_CSRF'] ?? null));


if ($csrf !== null && $csrf !== '' && !verify_csrf($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$title = trim($_POST['title'] ?? 'Report');
if (!isset($_FILES['pdf'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File PDF tidak ditemukan (pdf field missing)']);
    exit;
}
if ($_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['pdf']['error'];
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => "Upload error code: $err"]);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$creatorName = trim(($user['full_name'] ?? $_SESSION['full_name'] ?? 'Pengguna'));

$uploadsDir = asa_get_reports_upload_dir();
if (!is_dir($uploadsDir)) {
    if (!mkdir($uploadsDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal membuat folder upload']);
        exit;
    }
}


$time = date('Ymd_His');
$baseName = preg_replace('/[^A-Za-z0-9_-]+/','-', strtolower($title));
$fileName = $baseName . '_' . $time . '_' . bin2hex(random_bytes(4)) . '.pdf';
$destPath = $uploadsDir . $fileName;
$publicUrl = '/storage/uploads/reports/' . $fileName;

if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $destPath)) {
    
    $logFile = $uploadsDir . 'last_save_error.log';
    $info = [
        'time' => date('c'),
        'error' => error_get_last(),
        'files' => array_map(function($v){ return is_array($v)?$v:null; }, $_FILES),
        'post' => $_POST,
        'server' => ['REQUEST_METHOD'=>$_SERVER['REQUEST_METHOD'] ?? '', 'REMOTE_ADDR'=>$_SERVER['REMOTE_ADDR'] ?? '']
    ];
    @file_put_contents($logFile, json_encode($info, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file (cek ' . basename($logFile) . ' untuk detail)']);
    exit;
}


$indexFile = $uploadsDir . 'index.json';
$currentYear = (int)date('Y');
$indexData = asa_read_report_index($indexFile, LOCK_EX);
$items = $indexData['items'];
$indexHandle = $indexData['handle'];
$sequence = asa_count_reports_for_year($items, $currentYear) + 1;
$numbers = asa_build_report_number($currentYear, $sequence);

$clientFullNumber = trim($_POST['report_number'] ?? '');
$clientRawNumber = trim($_POST['report_number_input'] ?? '');
$clientCompactNumber = trim($_POST['report_number_compact'] ?? '');
$clientYearShort = trim($_POST['report_number_year_short'] ?? '');

$record = [
    'id' => bin2hex(random_bytes(8)),
    'title' => $title,
    'creator_id' => $userId,
    'creator_name' => $creatorName,
    'url' => $publicUrl,
    'file' => $fileName,
    'created_at' => date('d M Y H:i'),
    'report_number' => $numbers['full'],
    'report_number_compact' => $numbers['compact'] ?? str_replace(' ', '', $numbers['full']),
    'report_number_input' => $numbers['input'],
    'report_sequence' => $sequence,
    'report_year' => $currentYear,
    'report_year_short' => $numbers['year_short'] ?? substr((string)$currentYear, -2),
    'report_number_client' => $clientFullNumber,
    'report_number_input_client' => $clientRawNumber,
    'report_number_compact_client' => $clientCompactNumber,
    'report_number_year_short_client' => $clientYearShort
];
$items[] = $record;

if (!asa_write_report_index($indexHandle, $indexFile, $items)) {
    asa_release_report_index($indexHandle);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui index report']);
    exit;
}

asa_release_report_index($indexHandle);

echo json_encode(['success' => true, 'data' => $record]);
