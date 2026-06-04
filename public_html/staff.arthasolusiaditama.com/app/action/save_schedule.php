<?php
@date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

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

$date = trim($_POST['schedule_date'] ?? date('Y-m-d', strtotime('+1 day')));

if (strtotime($date) < strtotime(date('Y-m-d'))) {
  echo json_encode(['success' => false, 'message' => 'Tidak bisa membuat jadwal untuk tanggal yang sudah lewat']);
  exit;
}

$isBulk = isset($_POST['destinations']) && is_array($_POST['destinations']);

$destination = trim($_POST['destination'] ?? '');

$details = $isBulk ? '' : trim($_POST['details'] ?? '');
$assignees = $_POST['assignees'] ?? [];


if (!$isBulk) {
  $assignees = array_values(array_unique(array_map('intval', (array)$assignees)));
}

if (!$isBulk && (!$destination || empty($assignees))) {
  echo json_encode(['success' => false, 'message' => 'Tempat dan minimal 1 user wajib diisi']);
  exit;
}

try {
  

  if ($isBulk) {
    $destinations = array_map('trim', (array)$_POST['destinations']);
    $detailsList = isset($_POST['details']) ? (array)$_POST['details'] : [];
    $assigneesGroups = isset($_POST['assignees']) ? (array)$_POST['assignees'] : [];

    
    if (empty($destinations)) {
      echo json_encode(['success' => false, 'message' => 'Minimal satu tempat harus diisi']);
      exit;
    }

    
    $allUsers = [];
    foreach ($destinations as $i => $dst) {
      if ($dst === '') {
        echo json_encode(['success' => false, 'message' => 'Tempat tidak boleh kosong']);
        exit;
      }
      $grpAssignees = isset($assigneesGroups[$i]) ? (array)$assigneesGroups[$i] : [];
      $grpAssignees = array_values(array_unique(array_map('intval', $grpAssignees)));
      if (empty($grpAssignees)) {
        echo json_encode(['success' => false, 'message' => 'Pilih minimal satu user untuk tempat: ' . $dst]);
        exit;
      }
      
      foreach ($grpAssignees as $uid) {
        if (isset($allUsers[$uid])) {
          echo json_encode(['success' => false, 'message' => 'User tidak boleh di dua tempat pada hari yang sama']);
          exit;
        }
        $allUsers[$uid] = true;
      }
    }

    
    if (!empty($allUsers)) {
      $uids = array_keys($allUsers);
      $ph = implode(',', array_fill(0, count($uids), '?'));
      $params = array_merge([$date], $uids);
      $sql = "SELECT DISTINCT u.full_name
              FROM schedule_assignees sa
              INNER JOIN schedules s ON s.id = sa.schedule_id
              INNER JOIN users u ON u.id = sa.user_id
              WHERE s.schedule_date = ? AND sa.user_id IN ($ph)";
      $stmtC = $pdo->prepare($sql);
      $stmtC->execute($params);
      $conflicts = $stmtC->fetchAll(PDO::FETCH_COLUMN);
      if ($conflicts) {
        echo json_encode(['success' => false, 'message' => 'User sudah punya jadwal hari ini: ' . implode(', ', $conflicts)]);
        exit;
      }
    }

    try {
      $pdo->beginTransaction();
      $stmtS = $pdo->prepare("INSERT INTO schedules (schedule_date, destination, details, created_by) VALUES (?, ?, ?, ?)");
      $stmtA = $pdo->prepare("INSERT INTO schedule_assignees (schedule_id, user_id) VALUES (?, ?)");
      $createdIds = [];
      foreach ($destinations as $i => $dst) {
        $det = isset($detailsList[$i]) ? trim((string)$detailsList[$i]) : '';
        $stmtS->execute([$date, $dst, $det !== '' ? $det : null, (int)$_SESSION['user_id']]);
        $sid = (int)$pdo->lastInsertId();
        $createdIds[] = $sid;
        $grpAssignees = array_values(array_unique(array_map('intval', (array)($assigneesGroups[$i] ?? []))));
        foreach ($grpAssignees as $uid) {
          $stmtA->execute([$sid, $uid]);
        }
      }
      $pdo->commit();
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['success' => true, 'ids' => $createdIds]);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      http_response_code(500);
      echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
  }

  
  
  if (!empty($assignees)) {
    $uids = $assignees;
    $ph = implode(',', array_fill(0, count($uids), '?'));
    $params = array_merge([$date], $uids);
    $sql = "SELECT DISTINCT u.full_name
            FROM schedule_assignees sa
            INNER JOIN schedules s ON s.id = sa.schedule_id
            INNER JOIN users u ON u.id = sa.user_id
            WHERE s.schedule_date = ? AND sa.user_id IN ($ph)";
    $stmtC = $pdo->prepare($sql);
    $stmtC->execute($params);
    $conflicts = $stmtC->fetchAll(PDO::FETCH_COLUMN);
    if ($conflicts) {
      echo json_encode(['success' => false, 'message' => 'User sudah punya jadwal hari ini: ' . implode(', ', $conflicts)]);
      exit;
    }
  }

  $pdo->beginTransaction();
  $stmt = $pdo->prepare("INSERT INTO schedules (schedule_date, destination, details, created_by) VALUES (?, ?, ?, ?)");
  $stmt->execute([$date, $destination, $details ?: null, (int)$_SESSION['user_id']]);
  $scheduleId = (int)$pdo->lastInsertId();

  $stmtA = $pdo->prepare("INSERT INTO schedule_assignees (schedule_id, user_id) VALUES (?, ?)");
  foreach ($assignees as $uid) {
    $stmtA->execute([$scheduleId, (int)$uid]);
  }

  $pdo->commit();

  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['success' => true, 'id' => $scheduleId]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;
