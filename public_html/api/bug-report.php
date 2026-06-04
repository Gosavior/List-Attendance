<?php
 
header('Content-Type: application/json');


session_start();
$now = time();
$lastSubmit = $_SESSION['last_bug_report'] ?? 0;
if ($now - $lastSubmit < 60) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Mohon tunggu 1 menit sebelum mengirim laporan lagi.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $reporterName = trim($input['reporter_name'] ?? '');
    $description = trim($input['description'] ?? '');
    
    if (!$reporterName || !$description) {
        throw new Exception('Nama dan laporan wajib diisi');
    }
    
    if (mb_strlen($reporterName) > 100) {
        throw new Exception('Nama terlalu panjang (maks 100 karakter)');
    }
    if (mb_strlen($description) > 5000) {
        throw new Exception('Laporan terlalu panjang (maks 5000 karakter)');
    }
    
    
    $host = "145.79.8.194";
    $dbname = "arth_Staff";
    $username = "arth_Staff_database";
    $password = "Info-asa1.com";
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    $stmt = $pdo->prepare("
        INSERT INTO bug_reports (user_id, reporter_name, title, description, category, priority)
        VALUES (NULL, ?, ?, ?, 'bug', 'medium')
    ");
    $title = mb_substr($description, 0, 100);
    $stmt->execute([$reporterName, $title, $description]);
    
    $_SESSION['last_bug_report'] = $now;
    
    echo json_encode(['success' => true, 'message' => 'Laporan berhasil dikirim! Terima kasih.']);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
