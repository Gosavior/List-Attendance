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

try {
    $allowedExt = ['html','htm','php','pdf'];
    $entries = [];
    if (is_dir($reportsDir)) {
        foreach (scandir($reportsDir) as $child) {
            if ($child === '.' || $child === '..') continue;
            $childPath = $reportsDir . $child;
            if (!is_dir($childPath)) continue;
            foreach (scandir($childPath) as $f) {
                if ($f === '.' || $f === '..') continue;
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExt, true)) continue;
                $url = '/public/reports/' . rawurlencode($child) . '/' . rawurlencode($f);
                $entries[] = [
                    'id' => uniqid('fs_', true),
                    'title' => pathinfo($f, PATHINFO_FILENAME),
                    'description' => '',
                    'section' => $child,
                    'filename' => $f,
                    'url' => $url,
                    'creator_id' => null,
                    'creator_name' => 'Unknown',
                    'created_at' => date('Y-m-d H:i:s', filemtime($childPath . DIRECTORY_SEPARATOR . $f)),
                    'deleted' => false
                ];
            }
        }
    }
    if (!is_dir($reportsDir)) @mkdir($reportsDir, 0755, true);
    if (@file_put_contents($indexFile, json_encode($entries, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to write index.json']);
        exit;
    }
    echo json_encode(['success' => true, 'count' => count($entries)]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
