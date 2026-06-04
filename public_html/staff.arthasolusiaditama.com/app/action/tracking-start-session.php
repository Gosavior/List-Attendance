<?php
 

header('Content-Type: application/json');
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/db-schema.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}


if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];


$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['attendance_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing attendance_id']);
    exit;
}

try {
    $sessionStartCol = db_first_existing_column($pdo, 'tracking_sessions', ['start_time', 'started_at']);

    
    $stmt = $pdo->prepare("
        SELECT id, check_in_time 
        FROM attendances 
        WHERE id = ? AND user_id = ? AND DATE(check_in_time) = CURDATE()
    ");
    $stmt->execute([$input['attendance_id'], $userId]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$attendance) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid attendance record']);
        exit;
    }
    
    
    $stmt = $pdo->prepare("
        SELECT id FROM tracking_sessions 
        WHERE attendance_id = ? AND is_active = 1
    ");
    $stmt->execute([$input['attendance_id']]);
    $existingSession = $stmt->fetch();
    
    if ($existingSession) {
        echo json_encode([
            'success' => true,
            'message' => 'Tracking session already active',
            'session_id' => $existingSession['id'],
            'already_active' => true
        ]);
        exit;
    }
    
    
    $insertColumns = ['user_id', 'attendance_id', 'is_active'];
    $insertValues = ['?', '?', '1'];
    $insertParams = [$userId, $input['attendance_id']];

    if ($sessionStartCol) {
        $insertColumns[] = $sessionStartCol;
        $insertValues[] = '?';
        $insertParams[] = $attendance['check_in_time'];
    }

    $sql = 'INSERT INTO tracking_sessions (`' . implode('`, `', $insertColumns) . '`) VALUES (' . implode(', ', $insertValues) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($insertParams);
    
    $sessionId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Tracking session started',
        'session_id' => $sessionId,
        'started_at' => $attendance['check_in_time'],
        'start_time' => $attendance['check_in_time']
    ]);
    
} catch (PDOException $e) {
    error_log("[tracking-start-session] Database error: " . $e->getMessage());
    error_log("[tracking-start-session] Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error', 'details' => $e->getMessage()]);
}
