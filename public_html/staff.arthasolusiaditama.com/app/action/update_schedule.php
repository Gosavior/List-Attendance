<?php
@date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!has_role(['administrator', 'technician_manager'])) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Forbidden']);
  exit;
}

$csrf = $_POST['csrf'] ?? '';
if (!verify_csrf($csrf)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Invalid CSRF']);
  exit;
}

$id = (int)($_POST['id'] ?? 0);
$schedule_date = trim($_POST['schedule_date'] ?? '');
$destination = trim($_POST['destination'] ?? '');
$details = trim($_POST['details'] ?? '');
$assignees = array_values(array_unique(array_map('intval', (array)($_POST['assignees'] ?? []))));

if (!$id || !$destination || !$schedule_date) {
  echo json_encode(['success' => false, 'message' => 'ID, tanggal, dan tempat wajib diisi']);
  exit;
}

try {
  
  if (!empty($assignees)) {
    $uids = $assignees;
    $ph = implode(',', array_fill(0, count($uids), '?'));
    $params = array_merge([$schedule_date, $id], $uids);
    $sql = "SELECT DISTINCT u.full_name
            FROM schedule_assignees sa
            INNER JOIN schedules s ON s.id = sa.schedule_id
            INNER JOIN users u ON u.id = sa.user_id
            WHERE s.schedule_date = ? AND sa.schedule_id <> ? AND sa.user_id IN ($ph)";
    $stmtC = $pdo->prepare($sql);
    $stmtC->execute($params);
    $conflicts = $stmtC->fetchAll(PDO::FETCH_COLUMN);
    if ($conflicts) {
      echo json_encode(['success' => false, 'message' => 'User sudah punya jadwal pada tanggal tersebut: ' . implode(', ', $conflicts)]);
      exit;
    }
  }

  $pdo->beginTransaction();

  
  $stmt = $pdo->prepare("UPDATE schedules SET schedule_date = ?, destination = ?, details = ? WHERE id = ?");
  $stmt->execute([$schedule_date, $destination, $details ?: null, $id]);

  if (!empty($assignees)) {
    $pdo->prepare("DELETE FROM schedule_assignees WHERE schedule_id = ?")->execute([$id]);
    $stmtA = $pdo->prepare("INSERT INTO schedule_assignees (schedule_id, user_id) VALUES (?, ?)");
    foreach ($assignees as $uid) {
      $stmtA->execute([$id, $uid]);
    }
  }

  $pdo->commit();
  echo json_encode(['success' => true]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
