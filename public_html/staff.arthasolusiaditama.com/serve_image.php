<?php



declare(strict_types=1);


require_once __DIR__ . '/app/auth/auth.php';


$raw = (string)($_GET['path'] ?? '');
$raw = trim($raw);
if ($raw === '') {
    http_response_code(400);
    echo 'Missing path';
    exit;
}


$raw = str_replace(["\\\0", "\\\\"], ['', '/'], $raw);
$raw = preg_replace('#^/+#', '', $raw);
if (strpos($raw, '..') !== false) {
    http_response_code(400);
    echo 'Invalid path';
    exit;
}


if (!preg_match('#^storage/uploads/#', $raw)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$projectRoot = realpath(__DIR__);
$absCandidate = realpath($projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $raw));
if ($absCandidate === false) {
    
    $defaultAvatar = $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'avatar-default.png';
    if (file_exists($defaultAvatar)) {
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=86400');
        readfile($defaultAvatar);
        exit;
    }
    http_response_code(404);
    echo 'Not found';
    exit;
}


$storagePrefix = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR;
if (strpos($absCandidate, $storagePrefix) !== 0) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}


$allowed = false;
$sessionUser = (int)($_SESSION['user_id'] ?? 0);
$isAdmin = (function_exists('has_role') && has_role(['administrator', 'direktur']));
$isManager = (function_exists('has_role') && has_role(['technician_manager']));
$currentRole = strtolower($_SESSION['role'] ?? '');


if (preg_match('#^storage/uploads/attendance/(\d+)/#', $raw, $m)) {
    $ownerId = (int)$m[1];
    if ($sessionUser && ($sessionUser === $ownerId || $isAdmin || $isManager || $sessionUser > 0)) {
        
        
        $allowed = true;
    }
} elseif (preg_match('#^storage/uploads/avatar/\d+/#', $raw)) {
    
    $allowed = true;
} else {
    
    if ($sessionUser) $allowed = true;
}

if (!$allowed) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}


$mime = @mime_content_type($absCandidate) ?: 'application/octet-stream';
$filesize = filesize($absCandidate);


header_remove('Cache-Control');
header('Cache-Control: public, max-age=604800');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 604800) . ' GMT');
header('Content-Type: ' . $mime);
header('Content-Length: ' . $filesize);

if (function_exists('readfile')) {
    readfile($absCandidate);
    exit;
}


$fp = fopen($absCandidate, 'rb');
if ($fp) {
    while (!feof($fp)) {
        echo fread($fp, 8192);
    }
    fclose($fp);
}
exit;
