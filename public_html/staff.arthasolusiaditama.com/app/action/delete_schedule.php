<?php
@date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!has_role(['administrator'])) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Forbidden']);
  exit;
}

$id = (int)($_POST['id'] ?? 0);
$csrf = $_POST['csrf'] ?? '';
if (!$id || !verify_csrf($csrf)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Invalid request']);
  exit;
}

try {
  $pdo->beginTransaction();
  $pdo->prepare("DELETE FROM schedule_assignees WHERE schedule_id = ?")->execute([$id]);
  $pdo->prepare("DELETE FROM schedules WHERE id = ?")->execute([$id]);
  $pdo->commit();
  echo json_encode(['success' => true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
