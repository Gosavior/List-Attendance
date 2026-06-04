<?php
@date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../helpers/report-number.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$uploadsDir = asa_get_reports_upload_dir();
if (!is_dir($uploadsDir)) {
    if (!mkdir($uploadsDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal membuat folder penyimpanan report']);
        exit;
    }
}

$indexFile = $uploadsDir . 'index.json';
$currentYear = (int)date('Y');
$indexData = asa_read_report_index($indexFile, LOCK_SH);
$items = $indexData['items'];
$indexHandle = $indexData['handle'];
$sequence = asa_count_reports_for_year($items, $currentYear) + 1;
$numbers = asa_build_report_number($currentYear, $sequence);
asa_release_report_index($indexHandle);

echo json_encode([
    'success' => true,
    'data' => [
        'year' => $numbers['year'],
        'year_short' => $numbers['year_short'] ?? substr((string)$numbers['year'], -2),
        'sequence' => $numbers['sequence'],
        'full' => $numbers['full'],
        'compact' => $numbers['compact'] ?? str_replace(' ', '', $numbers['full']),
        'input' => $numbers['input'],
    ],
]);
