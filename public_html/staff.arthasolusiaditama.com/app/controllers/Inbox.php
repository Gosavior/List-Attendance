<?php
@date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/avatar.php';

function asset_path($p) {
  if (!$p) {
    if (defined('BASE_URL')) {
      return BASE_URL . '/public/assets/images/avatar-default.png';
    }
    return '../public/assets/images/avatar-default.png';
  }
  $p = ltrim($p, '/');
  if (strpos($p, 'storage/uploads/') === 0) {
    
    if (defined('BASE_URL')) {
      return BASE_URL . '/serve_image.php?path=' . rawurlencode($p);
    }
    return '/serve_image.php?path=' . rawurlencode($p);
  }
  if (strpos($p, 'public/') === 0) {
    if (defined('BASE_URL')) {
      return BASE_URL . '/' . $p;
    }
    return '../' . $p;
  }
  if (defined('BASE_URL')) {
    return BASE_URL . '/public/assets/images/avatar-default.png';
  }
  return '../public/assets/images/avatar-default.png';
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: ../login.php');
    exit;
}

$roleStr = strtolower(trim($_SESSION['role'] ?? ''));
$isAdmin = $roleStr && preg_match('/admin|administrator|direktur/', $roleStr);
$isDriver = ($roleStr === 'driver');
$sectionParam = $_GET['section'] ?? null;
$defaultSection = $isAdmin ? 'leaves' : ($isDriver ? 'deliveries' : 'loans');
if ($isAdmin && !$sectionParam) {
  try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM password_reset_requests WHERE status = 'pending'");
    $pendingPasswordForDefault = (int)$stmt->fetchColumn();
    if ($pendingPasswordForDefault > 0) {
      $defaultSection = 'password';
    }
  } catch (Throwable $ignoreMissingTable) {
  }
}
if ($isAdmin) {
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        attendance_date DATE NOT NULL,
        gps_lat DECIMAL(10, 8) DEFAULT NULL,
        gps_lng DECIMAL(11, 8) DEFAULT NULL,
        gps_accuracy DECIMAL(10, 2) DEFAULT NULL,
        location_name VARCHAR(500) DEFAULT NULL,
        photo_path VARCHAR(500) DEFAULT NULL,
        today_plan TEXT DEFAULT NULL,
        reason TEXT DEFAULT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        decided_by INT DEFAULT NULL,
        decided_at DATETIME DEFAULT NULL,
        attendance_id INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_attendance_requests_user_date (user_id, attendance_date),
        INDEX idx_attendance_requests_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  } catch (Throwable $e) { }
}

$section = $sectionParam ?? $defaultSection;
if ($isAdmin) {
  $allowedSections = ['leaves', 'loans', 'password'];
} elseif ($isDriver) {
  $allowedSections = ['deliveries'];
} else {
  $allowedSections = ['loans'];
}
if (!in_array($section, $allowedSections, true)) {
  $section = $allowedSections[0];
}
$status = strtolower(trim($_GET['status'] ?? 'pending'));
$allowedStatuses = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($status, $allowedStatuses, true)) {
  $status = 'pending';
}
$type = $_GET['type'] ?? 'all';
$mineOnly = true;


if ($isAdmin) {
    
    $where = [];
    $params = [];
} else {
    
    $where = [
        '(tp.to_user_id = :uid OR tp.from_user_id = :uid2)',
    ];
    $params = [ ':uid' => $userId, ':uid2' => $userId ];
}

if ($status !== 'all') {
    $where[] = 'tp.status = :status';
    $params[':status'] = $status;
}
if ($type !== 'all') {
    $where[] = 'tp.permit_type = :ptype';
    $params[':ptype'] = $type;
}
$whereSql = $where ? implode(' AND ', $where) : '1=1';

if ($section === 'password' && $isAdmin) {
  $whereP = [];
  $paramsP = [];
  if ($status !== 'all') {
    $whereP[] = 'prr.status = :pstatus';
    $paramsP[':pstatus'] = $status;
  }
  $whereSqlP = $whereP ? ('WHERE ' . implode(' AND ', $whereP)) : '';
  $sql = "SELECT prr.*, u.full_name, u.username, u.email, admin.full_name AS processed_by_name
          FROM password_reset_requests prr
          JOIN users u ON prr.user_id = u.id
          LEFT JOIN users admin ON admin.id = prr.processed_by
          $whereSqlP
          ORDER BY prr.created_at DESC";
  try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($paramsP);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $ex) {
    if ($ex->getCode() === '42S02') {
      $rows = [];
      $passwordTableMissing = true;
    } else {
      throw $ex;
    }
  }
} elseif ($section === 'leaves' && $isAdmin) {
  $whereSqlL = $status !== 'all' ? "WHERE lr.status = " . $pdo->quote($status) : '';
  $whereSqlA = $status !== 'all' ? "WHERE ar.status = " . $pdo->quote($status) : '';
  $sql = "SELECT lr.id, lr.user_id, lr.type, lr.reason, lr.start_date, lr.end_date, lr.proof_path, lr.status, lr.created_at, u.full_name, u.username, 'leave' AS request_source, NULL AS gps_lat, NULL AS gps_lng, NULL AS gps_accuracy, NULL AS location_name, NULL AS today_plan, NULL AS request_type, NULL AS missed_checkout_date, NULL AS requested_check_in_time, NULL AS requested_check_out_time
          FROM leave_requests lr LEFT JOIN users u ON u.id = lr.user_id $whereSqlL
          UNION ALL
          SELECT ar.id, ar.user_id, 'attendance_request' AS type, ar.reason, ar.attendance_date AS start_date, ar.attendance_date AS end_date, ar.photo_path AS proof_path, ar.status, ar.created_at, u.full_name, u.username, 'attendance_request' AS request_source, ar.gps_lat, ar.gps_lng, ar.gps_accuracy, ar.location_name, ar.today_plan, ar.request_type, ar.missed_checkout_date, ar.requested_check_in_time, ar.requested_check_out_time
          FROM attendance_requests ar LEFT JOIN users u ON u.id = ar.user_id $whereSqlA
          ORDER BY created_at DESC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($section === 'deliveries' && $isDriver) {
  
  $statusFilter = '';
  $paramsD = [':uid' => $userId];
  if ($status === 'pending') {
    $statusFilter = "AND mr.status = 'admin_approved'";
  } elseif ($status === 'approved') {
    $statusFilter = "AND mr.status IN ('delivered','completed')";
  } elseif ($status === 'rejected') {
    $statusFilter = "AND mr.status = 'driver_pickup'";
  }
  $sql = "SELECT mr.*, u.full_name AS requester_name
          FROM material_requests mr
          JOIN users u ON mr.user_id = u.id
          WHERE mr.status IN ('admin_approved','driver_pickup','delivered','completed')
          AND (mr.driver_pickup_by = :uid OR mr.driver_pickup_by IS NULL)
          $statusFilter
          ORDER BY FIELD(mr.status, 'admin_approved', 'driver_pickup', 'delivered', 'completed'), mr.updated_at DESC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($paramsD);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  $itemStmt = $pdo->prepare("SELECT * FROM material_request_items WHERE request_id = ?");
  foreach ($rows as &$row) {
    $itemStmt->execute([$row['id']]);
    $row['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
  }
  unset($row);
  
  try {
    $salesCfg = (function() { $host=$dbname=$username=$password=null; require __DIR__.'/../config/database_sales.php'; return compact('host','dbname','username','password'); })();
    $salesPdo = new PDO("mysql:host={$salesCfg['host']};dbname={$salesCfg['dbname']};charset=utf8mb4", $salesCfg['username'], $salesCfg['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $projIds = array_unique(array_filter(array_column($rows, 'project_id')));
    $projNames = [];
    if ($projIds) {
      $ph = implode(',', array_fill(0, count($projIds), '?'));
      $ps = $salesPdo->prepare("SELECT id, project_name FROM projects WHERE id IN ($ph)");
      $ps->execute(array_values($projIds));
      foreach ($ps->fetchAll() as $p) $projNames[$p['id']] = $p['project_name'];
    }
    foreach ($rows as &$row) {
      $row['project_name'] = $projNames[$row['project_id']] ?? 'Unknown Project';
    }
    unset($row);
  } catch (Throwable $e) {
    foreach ($rows as &$row) { $row['project_name'] = 'Project #'.$row['project_id']; }
    unset($row);
  }
} else {
  $sql = "SELECT tp.*, 
           ufrom.full_name AS from_user_name, uto.full_name AS to_user_name,
           t.name AS tool_name, t.code AS tool_code
      FROM tool_permits tp
      LEFT JOIN users ufrom ON ufrom.id = tp.from_user_id
      LEFT JOIN users uto ON uto.id = tp.to_user_id
      LEFT JOIN tools t ON t.id = tp.tool_id
      WHERE $whereSql
      ORDER BY tp.created_at DESC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<link rel="stylesheet" href="src/output.css">

<style>
.inbox-filter-form {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 16px;
    padding: 20px; margin-bottom: 24px;
}
.dark .inbox-filter-form { background: #1e293b; border-color: #334155; }

.inbox-filter-grid {
    display: grid; grid-template-columns: 1fr 1fr auto; gap: 12px; align-items: end;
}
@media (max-width: 640px) {
    .inbox-filter-grid { grid-template-columns: 1fr 1fr; }
    .inbox-filter-grid .inbox-filter-btn-wrap { grid-column: 1 / -1; }
}
@media (max-width: 380px) {
    .inbox-filter-grid { grid-template-columns: 1fr; }
}

.inbox-filter-label {
    display: block; font-size: 0.8rem; font-weight: 600; color: #475569;
    margin-bottom: 6px;
}
.dark .inbox-filter-label { color: #94a3b8; }
.inbox-filter-label i { margin-right: 6px; color: #94a3b8; }

.inbox-filter-select {
    width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 10px;
    font-size: 0.9rem; background: #fff; color: #0f172a; font-family: inherit;
    cursor: pointer; transition: border 0.15s; -webkit-appearance: none; appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8.825a.5.5 0 0 1-.354-.146l-4-4a.5.5 0 0 1 .708-.708L6 7.617l3.646-3.646a.5.5 0 0 1 .708.708l-4 4A.5.5 0 0 1 6 8.825z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 12px center; padding-right: 32px;
    box-sizing: border-box;
}
.inbox-filter-select:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
.dark .inbox-filter-select { background-color: #0f172a; border-color: #475569; color: #f1f5f9; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M6 8.825a.5.5 0 0 1-.354-.146l-4-4a.5.5 0 0 1 .708-.708L6 7.617l3.646-3.646a.5.5 0 0 1 .708.708l-4 4A.5.5 0 0 1 6 8.825z'/%3E%3C/svg%3E"); }

.inbox-filter-btn {
    width: 100%; padding: 10px 20px; background: linear-gradient(135deg, #3b82f6, #6366f1);
    color: #fff; border: none; border-radius: 10px; font-weight: 600; font-size: 0.9rem;
    cursor: pointer; transition: all 0.2s; font-family: inherit;
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
}
.inbox-filter-btn:hover { box-shadow: 0 4px 12px rgba(59,130,246,0.3); }

.inbox-card {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 16px;
    overflow: hidden; transition: box-shadow 0.25s, transform 0.25s;
    margin-bottom: 16px;
}
.inbox-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.08); transform: translateY(-1px); }
.dark .inbox-card { background: #1e293b; border-color: #334155; }
.dark .inbox-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.3); }

.inbox-card-header {
    display: flex; align-items: center; gap: 14px; padding: 16px 16px 12px;
    border-bottom: 1px solid #f1f5f9;
}
.dark .inbox-card-header { border-bottom-color: #334155; }

.inbox-card-img {
    width: 56px; height: 56px; border-radius: 12px; object-fit: cover;
    border: 2px solid #e2e8f0; flex-shrink: 0;
}
.dark .inbox-card-img { border-color: #475569; }

.inbox-card-title-wrap { flex: 1; min-width: 0; }

.inbox-card-title {
    font-size: 0.95rem; font-weight: 700; color: #0f172a;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    display: flex; align-items: center; gap: 6px;
}
.dark .inbox-card-title { color: #f1f5f9; }
.inbox-card-title i { color: #3b82f6; font-size: 0.85rem; flex-shrink: 0; }

.inbox-card-code {
    font-size: 0.7rem; font-family: monospace; color: #64748b; margin-top: 2px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.dark .inbox-card-code { color: #94a3b8; }

.inbox-card-status {
    padding: 4px 10px; border-radius: 20px; font-size: 0.68rem; font-weight: 700;
    white-space: nowrap; flex-shrink: 0; text-transform: uppercase; letter-spacing: 0.3px;
}
.inbox-status-pending { background: #fef3c7; color: #92400e; }
.dark .inbox-status-pending { background: #78350f; color: #fde68a; }
.inbox-status-approved { background: #d1fae5; color: #065f46; }
.dark .inbox-status-approved { background: #064e3b; color: #6ee7b7; }
.inbox-status-rejected { background: #fee2e2; color: #991b1b; }
.dark .inbox-status-rejected { background: #7f1d1d; color: #fca5a5; }

.inbox-card-body { padding: 12px 16px 16px; }

.inbox-card-info {
    display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px;
}
@media (max-width: 380px) {
    .inbox-card-info { grid-template-columns: 1fr; }
}

.inbox-info-item {
    display: flex; align-items: center; gap: 8px; font-size: 0.78rem; color: #475569;
}
.dark .inbox-info-item { color: #94a3b8; }
.inbox-info-item i { color: #94a3b8; font-size: 0.7rem; width: 14px; text-align: center; flex-shrink: 0; }
.dark .inbox-info-item i { color: #64748b; }
.inbox-info-item span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.inbox-type-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600;
}
.inbox-type-loan { background: #dbeafe; color: #1e40af; }
.dark .inbox-type-loan { background: #1e3a8a; color: #93c5fd; }
.inbox-type-handover { background: #e0e7ff; color: #3730a3; }
.dark .inbox-type-handover { background: #312e81; color: #a5b4fc; }
.inbox-type-return { background: #f3e8ff; color: #6b21a8; }
.dark .inbox-type-return { background: #581c87; color: #d8b4fe; }

.inbox-card-purpose {
    background: #f8fafc; border-radius: 10px; padding: 10px 12px;
    margin-bottom: 12px; font-size: 0.8rem; color: #334155; line-height: 1.5;
}
.dark .inbox-card-purpose { background: #0f172a; color: #cbd5e1; }
.inbox-card-purpose i { color: #3b82f6; margin-right: 6px; }

.inbox-card-dates {
    display: flex; align-items: center; gap: 6px; font-size: 0.72rem; color: #94a3b8;
    margin-bottom: 12px; flex-wrap: wrap;
}
.inbox-card-dates i { font-size: 0.65rem; }

.inbox-card-actions { display: flex; gap: 8px; }
.inbox-card-actions button {
    flex: 1; padding: 10px 0; border: none; border-radius: 10px;
    font-weight: 600; font-size: 0.82rem; cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 6px;
    transition: all 0.2s; font-family: inherit;
}
.inbox-btn-approve {
    background: linear-gradient(135deg, #059669, #047857); color: #fff;
}
.inbox-btn-approve:hover { box-shadow: 0 4px 12px rgba(5,150,105,0.35); }
.inbox-btn-reject {
    background: linear-gradient(135deg, #e11d48, #be123c); color: #fff;
}
.inbox-btn-reject:hover { box-shadow: 0 4px 12px rgba(225,29,72,0.35); }

.inbox-leave-header {
    display: flex; align-items: flex-start; gap: 14px; padding: 16px 16px 12px;
    border-bottom: 1px solid #f1f5f9;
}
.dark .inbox-leave-header { border-bottom-color: #334155; }
.inbox-leave-img {
    width: 56px; height: 56px; border-radius: 12px; object-fit: cover;
    border: 2px solid #e2e8f0; flex-shrink: 0;
}
.dark .inbox-leave-img { border-color: #475569; }
.inbox-leave-name {
    font-size: 0.95rem; font-weight: 700; color: #0f172a;
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
}
.dark .inbox-leave-name { color: #f1f5f9; }
.inbox-leave-type {
    padding: 2px 8px; border-radius: 20px; font-size: 0.68rem; font-weight: 600;
    text-transform: uppercase;
}
.inbox-leave-sick { background: #fee2e2; color: #991b1b; }
.dark .inbox-leave-sick { background: #7f1d1d; color: #fca5a5; }
.inbox-leave-other { background: #dbeafe; color: #1e40af; }
.dark .inbox-leave-other { background: #1e3a8a; color: #93c5fd; }

.inbox-pw-header {
    display: flex; align-items: center; gap: 14px; padding: 16px 16px 12px;
    border-bottom: 1px solid #f1f5f9;
}
.dark .inbox-pw-header { border-bottom-color: #334155; }
.inbox-pw-icon {
    width: 48px; height: 48px; border-radius: 12px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: #dbeafe; color: #2563eb; font-size: 1.2rem;
}
.dark .inbox-pw-icon { background: #1e3a8a; color: #93c5fd; }
.inbox-pw-name { font-size: 0.95rem; font-weight: 700; color: #0f172a; }
.dark .inbox-pw-name { color: #f1f5f9; }
.inbox-pw-username {
    font-size: 0.72rem; font-family: monospace; background: #f1f5f9; color: #64748b;
    padding: 1px 8px; border-radius: 20px; display: inline-block; margin-top: 2px;
}
.dark .inbox-pw-username { background: #0f172a; color: #94a3b8; }
.inbox-pw-link {
    display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px;
    background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff;
    border-radius: 10px; font-size: 0.82rem; font-weight: 600; text-decoration: none;
    transition: box-shadow 0.2s;
}
.inbox-pw-link:hover { box-shadow: 0 4px 12px rgba(59,130,246,0.35); color: #fff; }

.inbox-stats-mobile {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 20px;
}
@media (min-width: 768px) { .inbox-stats-mobile { display: none; } }
.inbox-stat-box {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
    padding: 10px 8px; text-align: center;
}
.dark .inbox-stat-box { background: #1e293b; border-color: #334155; }
.inbox-stat-label { font-size: 0.65rem; color: #64748b; }
.dark .inbox-stat-label { color: #94a3b8; }
.inbox-stat-value { font-size: 1.3rem; font-weight: 700; }
.inbox-stat-amber { color: #d97706; }
.inbox-stat-green { color: #059669; }
.inbox-stat-rose { color: #e11d48; }

.adm-photo-modal { position:fixed;inset:0;z-index:9990;background:rgba(0,0,0,0.6);align-items:center;justify-content:center;padding:16px; }
.adm-photo-card { background:#fff;border-radius:16px;max-width:420px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.3);overflow:hidden; }
.dark .adm-photo-card { background:#1e293b; }
.adm-photo-header { padding:20px 24px 16px;border-bottom:1px solid #e2e8f0; }
.dark .adm-photo-header { border-bottom-color:#334155; }
.adm-photo-title { font-weight:700;font-size:0.95rem;color:#1e293b; }
.dark .adm-photo-title { color:#f1f5f9; }
.adm-photo-subtitle { font-size:0.75rem;color:#64748b; }
.dark .adm-photo-subtitle { color:#94a3b8; }
.adm-photo-badge { display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:8px;background:#fef3c7;color:#92400e;font-size:0.8rem;font-weight:600; }
.dark .adm-photo-badge { background:#78350f;color:#fbbf24; }
.adm-photo-dropzone { border:2px dashed #cbd5e1;border-radius:12px;padding:24px 16px;text-align:center;background:#f8fafc;cursor:pointer; }
.dark .adm-photo-dropzone { background:#334155;border-color:#475569; }
.adm-photo-droptext { font-size:0.85rem;font-weight:600;color:#475569; }
.dark .adm-photo-droptext { color:#cbd5e1; }
.adm-photo-warning { margin-top:8px;padding:10px;border-radius:8px;background:#fef2f2;border:1px solid #fecaca;display:flex;align-items:flex-start;gap:8px;font-size:0.72rem;color:#991b1b; }
.dark .adm-photo-warning { background:rgba(127,29,29,0.2);border-color:#991b1b;color:#fca5a5; }
.adm-photo-btn-cancel { flex:1;padding:10px;border-radius:10px;border:1px solid #e2e8f0;background:#fff;color:#475569;font-weight:600;font-size:0.85rem;cursor:pointer; }
.dark .adm-photo-btn-cancel { background:#334155;border-color:#475569;color:#cbd5e1; }
.adm-photo-btn-submit { flex:2;padding:10px;border-radius:10px;border:none;background:linear-gradient(135deg,#059669,#047857);color:#fff;font-weight:600;font-size:0.85rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;box-shadow:0 4px 12px rgba(5,150,105,0.3); }

html[data-theme="dark"] [data-theme-container] h1[style*="color:#0f172a"] {
    color: #f1f5f9 !important;
}
html[data-theme="dark"] [data-theme-container] p[style*="color:#64748b"] {
    color: #94a3b8 !important;
}
.inbox-tab-container {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 6px;
    margin-bottom: 20px;
    overflow: hidden;
}
.dark .inbox-tab-container,
html[data-theme="dark"] .inbox-tab-container {
    background: #1e293b !important;
    border-color: #334155 !important;
}
html[data-theme="dark"] .inbox-tab-container a[style*="color:#64748b"] {
    color: #94a3b8 !important;
}
html[data-theme="dark"] .inbox-stats-mobile .inbox-stat-box,
html[data-theme="dark"] #inbox-desktop-stats .inbox-stat-box {
    background: #1e293b !important;
    border-color: #334155 !important;
}
</style>

<div style="min-height:100vh;" data-theme-container>
  <div style="max-width:1200px;margin:0 auto;padding:24px 16px;">
    
    <?php
      if ($section === 'deliveries' && $isDriver) {
        $pendingCount = count(array_filter($rows, fn($r) => $r['status'] === 'admin_approved'));
        $approvedCount = count(array_filter($rows, fn($r) => $r['status'] === 'driver_pickup'));
        $rejectedCount = count(array_filter($rows, fn($r) => in_array($r['status'], ['delivered','completed'])));
        $statLabels = ['Siap Antar', 'Dalam Antar', 'Selesai'];
      } else {
        $pendingCount = count(array_filter($rows, fn($r) => $r['status'] === 'pending'));
        $approvedCount = count(array_filter($rows, fn($r) => $r['status'] === 'approved'));
        $rejectedCount = count(array_filter($rows, fn($r) => $r['status'] === 'rejected'));
        $statLabels = ['Pending', 'Approved', 'Rejected'];
      }
    ?>
    
    <div style="margin-bottom:24px;">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
        <div style="width:44px;height:44px;background:linear-gradient(135deg,#3b82f6,#2563eb);border-radius:12px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(59,130,246,0.3);">
          <i class="fas fa-inbox" style="color:#fff;font-size:1.1rem;"></i>
        </div>
        <div>
          <h1 style="font-size:1.5rem;font-weight:700;color:#0f172a;margin:0;">Inbox</h1>
          <p style="font-size:0.78rem;color:#64748b;margin:2px 0 0;">Manage notifications &amp; approvals</p>
        </div>
      </div>
    </div>
    
    
    <div class="inbox-stats-mobile">
      <div class="inbox-stat-box">
        <div class="inbox-stat-label"><?= $statLabels[0] ?></div>
        <div class="inbox-stat-value inbox-stat-amber"><?= $pendingCount ?></div>
      </div>
      <div class="inbox-stat-box">
        <div class="inbox-stat-label"><?= $statLabels[1] ?></div>
        <div class="inbox-stat-value inbox-stat-green"><?= $approvedCount ?></div>
      </div>
      <div class="inbox-stat-box">
        <div class="inbox-stat-label"><?= $statLabels[2] ?></div>
        <div class="inbox-stat-value inbox-stat-rose"><?= $rejectedCount ?></div>
      </div>
    </div>
    
    
    <div style="display:none;gap:16px;margin-bottom:24px;" id="inbox-desktop-stats">
      <div class="inbox-stat-box">
        <div class="inbox-stat-label"><?= $statLabels[0] ?></div>
        <div class="inbox-stat-value inbox-stat-amber"><?= $pendingCount ?></div>
      </div>
      <div class="inbox-stat-box">
        <div class="inbox-stat-label"><?= $statLabels[1] ?></div>
        <div class="inbox-stat-value inbox-stat-green"><?= $approvedCount ?></div>
      </div>
      <div class="inbox-stat-box">
        <div class="inbox-stat-label"><?= $statLabels[2] ?></div>
        <div class="inbox-stat-value inbox-stat-rose"><?= $rejectedCount ?></div>
      </div>
    </div>
    <style>
      @media (min-width:768px) {
      }
    </style>

    <?php if ($isAdmin): ?>
    <div class="inbox-tab-container">
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;">
        <a href="?page=inbox&section=leaves&status=<?= urlencode($status) ?>" 
           style="display:flex;align-items:center;justify-content:center;gap:6px;padding:10px 8px;border-radius:10px;font-weight:600;font-size:0.82rem;text-decoration:none;transition:all 0.2s;
                  <?= $section==='leaves' 
                    ? 'background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;box-shadow:0 2px 8px rgba(37,99,235,0.3);' 
                    : 'color:#64748b;' ?>">
          <i class="fas fa-calendar-alt"></i>
          <span>Leaves</span>
        </a>
        
        <a href="?page=inbox&section=loans&status=<?= urlencode($status) ?>&type=<?= urlencode($type) ?>" 
           style="display:flex;align-items:center;justify-content:center;gap:6px;padding:10px 8px;border-radius:10px;font-weight:600;font-size:0.82rem;text-decoration:none;transition:all 0.2s;
                  <?= $section==='loans' 
                    ? 'background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;box-shadow:0 2px 8px rgba(37,99,235,0.3);' 
                    : 'color:#64748b;' ?>">
          <i class="fas fa-tools"></i>
          <span>Tools</span>
        </a>
        
        <a href="?page=inbox&section=password&status=<?= urlencode($status) ?>" 
           style="display:flex;align-items:center;justify-content:center;gap:6px;padding:10px 8px;border-radius:10px;font-weight:600;font-size:0.82rem;text-decoration:none;transition:all 0.2s;position:relative;
                  <?= $section==='password' 
                    ? 'background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;box-shadow:0 2px 8px rgba(37,99,235,0.3);' 
                    : 'color:#64748b;' ?>">
          <i class="fas fa-key"></i>
          <span>Password</span>
          <?php
          try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM password_reset_requests WHERE status = 'pending'");
            $pendingPasswordCount = (int)$stmt->fetchColumn();
            if ($pendingPasswordCount > 0 && $section !== 'password'):
          ?>
          <span style="position:absolute;top:-4px;right:-4px;width:18px;height:18px;background:#ef4444;color:#fff;font-size:0.65rem;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;animation:pulse 2s infinite;">
            <?= $pendingPasswordCount ?>
          </span>
          <?php 
            endif;
          } catch (Throwable $e) {
          }
          ?>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <form method="GET" class="inbox-filter-form">
      <input type="hidden" name="page" value="inbox">
      <input type="hidden" name="section" value="<?= htmlspecialchars($section) ?>">
      
      <div class="inbox-filter-grid">
        <div>
          <label class="inbox-filter-label"><i class="fas fa-filter"></i>Status</label>
          <select name="status" class="inbox-filter-select">
            <?php if ($section === 'deliveries'): ?>
            <option value="all" <?= $status==='all'?'selected':'' ?>>Semua Status</option>
            <option value="pending" <?= $status==='pending'?'selected':'' ?>>Siap Antar</option>
            <option value="rejected" <?= $status==='rejected'?'selected':'' ?>>Dalam Antar</option>
            <option value="approved" <?= $status==='approved'?'selected':'' ?>>Selesai</option>
            <?php else: ?>
            <option value="all" <?= $status==='all'?'selected':'' ?>>All Status</option>
            <option value="pending" <?= $status==='pending'?'selected':'' ?>><i class="fas fa-hourglass-half"></i> Pending</option>
            <option value="approved" <?= $status==='approved'?'selected':'' ?>><i class="fas fa-check-circle"></i> Approved</option>
            <option value="rejected" <?= $status==='rejected'?'selected':'' ?>><i class="fas fa-times-circle"></i> Rejected</option>
            <?php endif; ?>
          </select>
        </div>
        
        <?php if ($section==='loans'): ?>
        <div>
          <label class="inbox-filter-label"><i class="fas fa-tag"></i>Type</label>
          <select name="type" class="inbox-filter-select">
            <option value="all" <?= $type==='all'?'selected':'' ?>>All Types</option>
            <option value="loan" <?= $type==='loan'?'selected':'' ?>><i class="fas fa-box"></i> Loan</option>
            <option value="handover" <?= $type==='handover'?'selected':'' ?>><i class="fas fa-exchange-alt"></i> Handover</option>
            <option value="return" <?= $type==='return'?'selected':'' ?>><i class="fas fa-undo"></i> Return</option>
          </select>
        </div>
        <?php endif; ?>
        
        <div class="inbox-filter-btn-wrap" style="display:flex;align-items:flex-end;">
          <button type="submit" class="inbox-filter-btn">
            <i class="fas fa-search"></i>Apply Filters
          </button>
        </div>
      </div>
    </form>

    <div>
      <?php if(empty($rows)): ?>
        <div class="inbox-card" style="padding:48px 16px;text-align:center;">
          <div style="width:64px;height:64px;background:#f1f5f9;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
            <i class="fas fa-inbox" style="font-size:1.8rem;color:#94a3b8;"></i>
          </div>
          <h3 style="font-size:1rem;font-weight:600;color:#475569;margin:0 0 4px;">No notifications found</h3>
          <p style="font-size:0.8rem;color:#94a3b8;margin:0;">No items match your current filters.</p>
        </div>
      <?php endif; ?>
      
      <?php if ($section==='password' && $isAdmin): ?>
        <?php if (!empty($passwordTableMissing)): ?>
        <div class="inbox-card" style="padding:20px;">
          <div style="display:flex;align-items:flex-start;gap:10px;color:#92400e;font-size:0.82rem;">
            <i class="fas fa-exclamation-triangle" style="font-size:1.1rem;margin-top:2px;"></i>
            <div>
              <p style="font-weight:600;margin:0;">Password reset table is missing.</p>
              <p style="margin:6px 0 0;">Create table <code>password_reset_requests</code> or run the pending migration.</p>
            </div>
          </div>
        </div>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
        <div class="inbox-card">
          <div class="inbox-pw-header">
            <div class="inbox-pw-icon"><i class="fas fa-key"></i></div>
            <div style="flex:1;min-width:0;">
              <div class="inbox-pw-name"><?= htmlspecialchars($r['full_name'] ?? $r['username'] ?? 'User') ?></div>
              <span class="inbox-pw-username"><?= htmlspecialchars($r['username'] ?? '-') ?></span>
            </div>
            <span class="inbox-card-status <?= $r['status']==='pending' ? 'inbox-status-pending' : ($r['status']==='approved' ? 'inbox-status-approved' : 'inbox-status-rejected') ?>">
              <?= $r['status']==='pending' ? '<i class="fas fa-hourglass-half"></i> Pending' : ($r['status']==='approved' ? '<i class="fas fa-check-circle"></i> Approved' : '<i class="fas fa-times-circle"></i> Rejected') ?>
            </span>
          </div>
          <div class="inbox-card-body">
            <div class="inbox-card-info">
              <?php if (!empty($r['email'])): ?>
              <div class="inbox-info-item"><i class="far fa-envelope"></i><span><?= htmlspecialchars($r['email']) ?></span></div>
              <?php endif; ?>
              <div class="inbox-info-item"><i class="far fa-clock"></i><span><?= date('d M Y H:i', strtotime($r['created_at'])) ?></span></div>
              <?php if (!empty($r['processed_at'])): ?>
              <div class="inbox-info-item"><i class="far fa-user-circle"></i><span>by <?= htmlspecialchars($r['processed_by_name'] ?? 'System') ?></span></div>
              <div class="inbox-info-item"><i class="far fa-calendar-check"></i><span><?= date('d M Y H:i', strtotime($r['processed_at'])) ?></span></div>
              <?php endif; ?>
            </div>
            <?php if (!empty($r['notes'])): ?>
            <div class="inbox-card-purpose"><i class="fas fa-sticky-note"></i><?= htmlspecialchars($r['notes']) ?></div>
            <?php endif; ?>
            <a href="?page=account&tab=approval" class="inbox-pw-link">
              <i class="fas fa-external-link-alt"></i>Open Approval Panel
            </a>
          </div>
        </div>
        <?php endforeach; ?>

      <?php elseif ($section==='leaves' && $isAdmin): ?>
        <?php foreach ($rows as $r): ?>
        <div class="inbox-card">
          <div class="inbox-leave-header">
            <img src="<?= htmlspecialchars(asset_path($r['proof_path'] ?? null)) ?>" 
                 class="inbox-leave-img" alt="proof">
            <div style="flex:1;min-width:0;">
              <div class="inbox-leave-name">
                <?= htmlspecialchars($r['full_name'] ?? $r['username'] ?? 'User') ?>
                <span class="inbox-leave-type <?= $r['type']==='sick' ? 'inbox-leave-sick' : 'inbox-leave-other' ?>">
                  <?php
                    if (($r['type'] ?? '') === 'attendance_request') {
                      $reqType = $r['request_type'] ?? 'checkin';
                      echo $reqType === 'missed_checkout' ? 'REQUEST PULANG' : 'REQUEST ABSENSI';
                    } else {
                      echo strtoupper($r['type'] ?? '');
                    }
                  ?>
                </span>
              </div>
              <div class="inbox-card-dates" style="margin-top:6px;margin-bottom:0;">
                <i class="far fa-calendar"></i>
                <span><?= date('d M Y', strtotime($r['start_date'])) ?></span>
                <i class="fas fa-arrow-right"></i>
                <span><?= date('d M Y', strtotime($r['end_date'])) ?></span>
              </div>
            </div>
            <span class="inbox-card-status <?= $r['status']==='pending' ? 'inbox-status-pending' : ($r['status']==='approved' ? 'inbox-status-approved' : 'inbox-status-rejected') ?>">
              <?= $r['status']==='pending' ? '<i class="fas fa-hourglass-half"></i> Pending' : ($r['status']==='approved' ? '<i class="fas fa-check-circle"></i> Approved' : '<i class="fas fa-times-circle"></i> Rejected') ?>
            </span>
          </div>
          <div class="inbox-card-body">
            <?php if (!empty($r['reason'])): ?>
            <div class="inbox-card-purpose">
              <i class="fas fa-quote-left" style="font-size:0.7rem;"></i>
              <?= htmlspecialchars($r['reason']) ?>
            </div>
            <?php endif; ?>
            <?php if (($r['type'] ?? '') === 'attendance_request'): ?>
              <?php if (($r['request_type'] ?? 'checkin') === 'missed_checkout'): ?>
              <div class="inbox-card-purpose">
                <i class="fas fa-sign-out-alt" style="font-size:0.7rem;"></i>
                <strong>Tanggal Pulang Terlewat:</strong> <?= htmlspecialchars(date('d M Y', strtotime($r['missed_checkout_date'] ?? $r['start_date']))) ?>
              </div>
              <?php if (!empty($r['requested_check_out_time'])): ?>
              <div class="inbox-card-purpose">
                <i class="fas fa-clock" style="font-size:0.7rem;"></i>
                <strong>Jam Pulang:</strong> <?= htmlspecialchars(substr($r['requested_check_out_time'], 0, 5)) ?>
              </div>
              <?php endif; ?>
              <?php else: ?>
              <?php if (!empty($r['today_plan'])): ?>
              <div class="inbox-card-purpose">
                <i class="fas fa-tasks" style="font-size:0.7rem;"></i>
                <strong>Plan Hari Ini:</strong> <?= htmlspecialchars($r['today_plan']) ?>
              </div>
              <?php endif; ?>
              <?php if (!empty($r['requested_check_in_time'])): ?>
              <div class="inbox-card-purpose">
                <i class="fas fa-clock" style="font-size:0.7rem;"></i>
                <strong>Jam Masuk:</strong> <?= htmlspecialchars(substr($r['requested_check_in_time'], 0, 5)) ?>
              </div>
              <?php endif; ?>
              <div class="inbox-card-purpose">
                <i class="fas fa-map-marker-alt" style="font-size:0.7rem;"></i>
                <strong>Lokasi GPS:</strong>
                <?= htmlspecialchars(trim(($r['gps_lat'] ?? '') . ', ' . ($r['gps_lng'] ?? ''), ', ')) ?>
                <?= !empty($r['gps_accuracy']) ? ' (akurasi ' . htmlspecialchars((string)round((float)$r['gps_accuracy'])) . 'm)' : '' ?>
                <?php if (!empty($r['location_name'])): ?>
                  <br><span style="font-size:0.75rem;color:#64748b;"><?= htmlspecialchars($r['location_name']) ?></span>
                <?php endif; ?>
              </div>
              <?php endif; ?>
            <?php endif; ?>
            <?php if ($r['status']==='pending'): ?>
            <div class="inbox-card-actions">
              <button class="approve-leave inbox-btn-approve" data-id="<?= (int)$r['id'] ?>" data-source="<?= htmlspecialchars($r['request_source'] ?? 'leave') ?>">
                <i class="fas fa-check-circle"></i>Approve
              </button>
              <button class="reject-leave inbox-btn-reject" data-id="<?= (int)$r['id'] ?>" data-source="<?= htmlspecialchars($r['request_source'] ?? 'leave') ?>">
                <i class="fas fa-times-circle"></i>Reject
              </button>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>

      <?php elseif ($section==='deliveries' && $isDriver): ?>
        <?php foreach ($rows as $r): 
          $statusMap = [
            'admin_approved' => ['label' => 'Siap Antar', 'class' => 'inbox-status-pending', 'icon' => 'fa-box'],
            'driver_pickup' => ['label' => 'Dalam Antar', 'class' => 'inbox-status-approved', 'icon' => 'fa-shipping-fast'],
            'delivered' => ['label' => 'Terkirim', 'class' => 'inbox-status-approved', 'icon' => 'fa-check-circle'],
            'completed' => ['label' => 'Selesai', 'class' => 'inbox-status-approved', 'icon' => 'fa-check-double'],
          ];
          $st = $statusMap[$r['status']] ?? ['label' => $r['status'], 'class' => '', 'icon' => 'fa-question'];
          $hasPurchase = false;
          foreach ($r['items'] as $item) {
            if (!empty($item['source_type']) && in_array($item['source_type'], ['purchase','split'])) { $hasPurchase = true; break; }
          }
        ?>
        <div class="inbox-card" style="position:relative;overflow:visible;">
          <?php if ($r['status'] === 'admin_approved'): ?>
          <div style="position:absolute;top:-8px;right:12px;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;font-size:0.65rem;font-weight:700;padding:3px 10px;border-radius:20px;box-shadow:0 2px 8px rgba(249,115,22,0.3);animation:pulse 2s infinite;">
            BARU
          </div>
          <?php endif; ?>
          <div style="display:flex;align-items:flex-start;gap:14px;padding:16px 16px 12px;border-bottom:1px solid #f1f5f9;">
            <div style="width:48px;height:48px;border-radius:12px;flex-shrink:0;display:flex;align-items:center;justify-content:center;
              <?= $r['status']==='admin_approved' ? 'background:#fff7ed;color:#ea580c;' : ($r['status']==='driver_pickup' ? 'background:#dbeafe;color:#2563eb;' : 'background:#dcfce7;color:#059669;') ?>font-size:1.2rem;">
              <i class="fas <?= $st['icon'] ?>"></i>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="font-size:0.95rem;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <i class="fas fa-project-diagram" style="color:#3b82f6;font-size:0.8rem;"></i>
                <?= htmlspecialchars($r['project_name'] ?? 'Unknown Project') ?>
              </div>
              <div style="font-size:0.72rem;color:#64748b;margin-top:2px;">
                <i class="far fa-user"></i> <?= htmlspecialchars($r['requester_name'] ?? '-') ?>
                &bull; <?= date('d M Y H:i', strtotime($r['updated_at'] ?? $r['created_at'])) ?>
              </div>
            </div>
            <span class="inbox-card-status <?= $st['class'] ?>">
              <i class="fas <?= $st['icon'] ?>"></i> <?= $st['label'] ?>
            </span>
          </div>
          <div class="inbox-card-body">
            <?php if ($hasPurchase): ?>
            <div style="background:#fef3c7;border:1px solid #fbbf24;border-radius:10px;padding:8px 12px;margin-bottom:12px;font-size:0.78rem;color:#92400e;display:flex;align-items:center;gap:8px;">
              <i class="fas fa-store" style="font-size:0.9rem;"></i>
              <span><strong>Perlu beli di toko!</strong> Ada item yang harus dibeli sebelum diantar.</span>
            </div>
            <?php
              
              $storeMap = [];
              foreach ($r['items'] as $it) {
                if (!empty($it['source_type']) && $it['source_type'] !== 'warehouse' && !empty($it['store_name'])) {
                  $sn = $it['store_name'];
                  if (!isset($storeMap[$sn])) $storeMap[$sn] = ['address' => $it['store_address'] ?? '', 'items' => []];
                  $storeMap[$sn]['items'][] = $it;
                }
              }
            ?>
            <?php foreach ($storeMap as $sName => $sData): ?>
            <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:10px;padding:10px 12px;margin-bottom:8px;">
              <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                <i class="fas fa-store" style="color:#d97706;font-size:0.8rem;"></i>
                <span style="font-size:0.82rem;font-weight:700;color:#92400e;"><?= htmlspecialchars($sName) ?></span>
              </div>
              <?php if ($sData['address']): ?>
              <p style="font-size:0.75rem;color:#a16207;margin:2px 0 6px 0;"><?= htmlspecialchars($sData['address']) ?></p>
              <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($sData['address']) ?>" target="_blank" style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;border-radius:8px;font-size:0.75rem;font-weight:600;text-decoration:none;">
                <i class="fas fa-map-marked-alt"></i> Buka di Google Maps
              </a>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
            
            <div style="margin-bottom:12px;">
              <div style="font-size:0.75rem;font-weight:600;color:#64748b;margin-bottom:6px;"><i class="fas fa-list"></i> Material (<?= count($r['items']) ?> item)</div>
              <?php foreach ($r['items'] as $item): ?>
              <div style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:#f8fafc;border-radius:8px;margin-bottom:4px;font-size:0.8rem;">
                <i class="fas fa-cube" style="color:#94a3b8;font-size:0.7rem;"></i>
                <span style="flex:1;color:#0f172a;font-weight:500;"><?= htmlspecialchars($item['material_name']) ?></span>
                <span style="color:#64748b;font-weight:600;"><?= (int)$item['quantity'] ?> <?= htmlspecialchars($item['unit'] ?? 'pcs') ?></span>
                <?php if (!empty($item['source_type']) && $item['source_type'] !== 'warehouse'): ?>
                <span style="background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:10px;font-size:0.65rem;font-weight:600;">Beli</span>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
            
            <?php if (!empty($r['notes'])): ?>
            <div class="inbox-card-purpose">
              <i class="fas fa-sticky-note"></i>
              <?= htmlspecialchars($r['notes']) ?>
            </div>
            <?php endif; ?>
            
            <?php if ($r['status'] === 'admin_approved'): ?>
            <div style="display:flex;gap:8px;margin-top:8px;">
              <a href="?page=request-material" style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;border-radius:10px;font-size:0.82rem;font-weight:600;text-decoration:none;transition:box-shadow 0.2s;">
                <i class="fas fa-truck"></i> Ambil & Antar
              </a>
            </div>
            <?php elseif ($r['status'] === 'driver_pickup'): ?>
            <div style="display:flex;gap:8px;margin-top:8px;">
              <a href="?page=request-material" style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;background:linear-gradient(135deg,#059669,#047857);color:#fff;border-radius:10px;font-size:0.82rem;font-weight:600;text-decoration:none;transition:box-shadow 0.2s;">
                <i class="fas fa-check-circle"></i> Konfirmasi Antar
              </a>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>

      <?php else: ?>
        <?php
          
          $pendingReturnIds = [];
          foreach ($rows as $r) {
            if ($r['status'] === 'pending' && in_array($r['permit_type'], ['return'])) {
              $pendingReturnIds[] = (int)$r['id'];
            }
          }
        ?>
        <?php if ($isAdmin && count($pendingReturnIds) > 1): ?>
        <div class="inbox-card" style="padding:14px 20px;margin-bottom:16px;position:sticky;top:0;z-index:20;">
          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.85rem;font-weight:600;color:#475569;" class="dark:!text-slate-300">
              <input type="checkbox" id="selectAllReturns" style="width:18px;height:18px;accent-color:#2563eb;cursor:pointer;border-radius:4px;">
              Pilih Semua Return (<span id="selectedReturnCount">0</span>/<?= count($pendingReturnIds) ?>)
            </label>
            <button id="btnBulkApprove" onclick="bulkApproveReturns()" disabled
              style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:10px;border:none;cursor:pointer;font-weight:600;font-size:0.82rem;
                     background:linear-gradient(135deg,#059669,#047857);color:#fff;box-shadow:0 2px 8px rgba(5,150,105,0.3);opacity:0.5;transition:all 0.2s;">
              <i class="fas fa-check-double"></i> Approve Semua yang Dipilih
            </button>
          </div>
        </div>
        <?php endif; ?>

        <?php foreach ($rows as $r): ?>
        <?php
          $inbox_photos = json_decode($r['photo_proof_path'] ?? '', true);
          if (!is_array($inbox_photos)) $inbox_photos = [$r['photo_proof_path'] ?? ''];
          $first_photo = $inbox_photos[0] ?? '';
          $isPendingReturn = ($r['status'] === 'pending' && in_array($r['permit_type'], ['return']));
        ?>
        <div class="inbox-card" data-permit-id="<?= (int)$r['id'] ?>">
          <div class="inbox-card-header">
            <img src="<?= htmlspecialchars(asset_path($first_photo ?: null)) ?>" 
                 class="inbox-card-img" alt="proof">
            <div class="inbox-card-title-wrap">
              <div class="inbox-card-title">
                <i class="fas fa-tools"></i>
                <?= htmlspecialchars($r['tool_name'] ?? 'Equipment') ?>
              </div>
              <div class="inbox-card-code"><?= htmlspecialchars($r['tool_code'] ?? '-') ?></div>
            </div>
            <span class="inbox-card-status <?= $r['status']==='pending' ? 'inbox-status-pending' : ($r['status']==='approved' ? 'inbox-status-approved' : 'inbox-status-rejected') ?>">
              <?= $r['status']==='pending' ? '<i class="fas fa-hourglass-half"></i> Pending' : ($r['status']==='approved' ? '<i class="fas fa-check-circle"></i> Approved' : '<i class="fas fa-times-circle"></i> Rejected') ?>
            </span>
          </div>
          <div class="inbox-card-body">
            <div class="inbox-card-info">
              <div class="inbox-info-item">
                <i class="fas fa-user"></i>
                <span><?= htmlspecialchars($r['from_user_name'] ?? '-') ?></span>
              </div>
              <div class="inbox-info-item">
                <i class="fas fa-user-check"></i>
                <span><?= htmlspecialchars($r['to_user_name'] ?? '-') ?></span>
              </div>
              <div class="inbox-info-item" style="grid-column:1/-1;">
                <span class="inbox-type-badge <?php
                  if ($r['permit_type']==='handover') echo 'inbox-type-handover';
                  elseif ($r['permit_type']==='loan') echo 'inbox-type-loan';
                  else echo 'inbox-type-return';
                ?>">
                  <?= $r['permit_type']==='handover' ? '<i class="fas fa-exchange-alt"></i> Handover' : ($r['permit_type']==='loan' ? '<i class="fas fa-box"></i> Loan' : '<i class="fas fa-undo"></i> Return') ?>
                </span>
              </div>
            </div>
            
            <?php if (!empty($r['start_date'])): ?>
            <div class="inbox-card-dates">
              <i class="far fa-calendar"></i>
              <span><?= date('d M Y', strtotime($r['start_date'])) ?></span>
              <i class="fas fa-arrow-right"></i>
              <span><?= date('d M Y', strtotime($r['end_date'])) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($r['reason'])): ?>
            <div class="inbox-card-purpose">
              <i class="fas fa-bullseye"></i>
              <strong>Purpose:</strong> <?= htmlspecialchars($r['reason']) ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($r['location'])): ?>
            <div class="inbox-card-purpose">
              <i class="fas fa-map-marker-alt"></i>
              <strong>Lokasi:</strong> <?= htmlspecialchars($r['location']) ?>
            </div>
            <?php endif; ?>
            
            <?php if ($r['status']==='pending'): ?>
            <div class="inbox-card-actions" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
              <?php if ($isAdmin && $isPendingReturn && count($pendingReturnIds) > 1): ?>
              <label style="display:flex;align-items:center;gap:6px;cursor:pointer;margin-right:auto;">
                <input type="checkbox" class="return-checkbox" data-id="<?= (int)$r['id'] ?>" style="width:18px;height:18px;accent-color:#2563eb;cursor:pointer;border-radius:4px;">
                <span style="font-size:0.75rem;color:#64748b;">Pilih</span>
              </label>
              <?php endif; ?>
              <button class="approve-loan inbox-btn-approve" data-id="<?= (int)$r['id'] ?>" data-type="<?= htmlspecialchars($r['permit_type']) ?>" data-tool="<?= htmlspecialchars($r['tool_name'] ?? '') ?> (<?= htmlspecialchars($r['tool_code'] ?? '') ?>)">
                <i class="fas fa-check-circle"></i>Approve
              </button>
              <button class="reject-loan inbox-btn-reject" data-id="<?= (int)$r['id'] ?>">
                <i class="fas fa-times-circle"></i>Reject
              </button>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>


<div id="modalAdminPhoto" class="adm-photo-modal" style="display:none;">
  <div class="adm-photo-card">
    <div class="adm-photo-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center;">
          <i class="fas fa-camera" style="color:#fff;font-size:1.1rem;"></i>
        </div>
        <div>
          <div class="adm-photo-title">Foto Verifikasi Alat</div>
          <div class="adm-photo-subtitle">Wajib foto alat sebelum diserahkan</div>
        </div>
        <button onclick="closeAdminPhotoModal()" style="margin-left:auto;padding:4px;border:none;background:none;cursor:pointer;color:#94a3b8;font-size:1.2rem;">
          <i class="fas fa-times"></i>
        </button>
      </div>
    </div>
    <div style="padding:20px 24px;">
      <input type="hidden" id="adminPhotoPermitId" value="">
      <div style="text-align:center;margin-bottom:16px;">
        <div class="adm-photo-badge">
          <i class="fas fa-tools"></i>
          <span id="adminPhotoToolName">Tools</span>
        </div>
      </div>
      <div class="adm-photo-dropzone" onclick="document.getElementById('adminPhotoInput').click()">
        <div id="adminPhotoPreview" style="display:none;margin-bottom:12px;"></div>
        <i class="fas fa-camera" style="font-size:2rem;color:#94a3b8;margin-bottom:8px;display:block;"></i>
        <div class="adm-photo-droptext">Tap untuk ambil foto alat</div>
        <div style="font-size:0.72rem;color:#94a3b8;margin-top:4px;">Foto kondisi alat sebelum diserahkan</div>
        <input type="file" id="adminPhotoInput" accept="image/*" capture="environment" style="display:none;">
      </div>
      <div class="adm-photo-warning">
        <i class="fas fa-exclamation-triangle" style="color:#dc2626;font-size:0.8rem;margin-top:2px;"></i>
        <div>
          <strong>Penting:</strong> Foto ini sebagai bukti kondisi alat saat diserahkan ke peminjam. Jika alat hilang/rusak, foto ini menjadi bukti pertanggungjawaban.
        </div>
      </div>
    </div>
    <div style="padding:12px 24px 20px;display:flex;gap:8px;">
      <button onclick="closeAdminPhotoModal()" class="adm-photo-btn-cancel">
        Batal
      </button>
      <button id="btnAdminPhotoSubmit" onclick="submitAdminPhotoApprove()" class="adm-photo-btn-submit">
        <i class="fas fa-check-circle"></i>Approve dengan Foto
      </button>
    </div>
  </div>
</div>

<script>
function showToast(message, type = 'success') {
  const toast = document.createElement('div');
  const bg = type === 'success' ? '#059669' : '#e11d48';
  const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
  toast.style.cssText = `position:fixed;top:16px;right:16px;padding:12px 20px;border-radius:12px;z-index:9999;
    background:${bg};color:#fff;font-weight:500;font-size:0.88rem;display:flex;align-items:center;gap:10px;
    box-shadow:0 8px 24px rgba(0,0,0,0.2);transition:opacity 0.3s;font-family:Inter,sans-serif;`;
  toast.innerHTML = `<i class="fas ${icon}" style="font-size:1.1rem;"></i><span>${message}</span>`;
  document.body.appendChild(toast);
  
  setTimeout(() => toast.style.opacity = '0', 3000);
  setTimeout(() => toast.remove(), 3500);
}

function handleLeaveAction(id, action, source) {
  const btn = document.querySelector(`button[data-id="${id}"]`) || null;
  const originalHTML = btn ? btn.innerHTML : null;
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
  }
  
  fetch('./app/action/handle_leave_request.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `id=${encodeURIComponent(id)}&action=${encodeURIComponent(action)}&source=${encodeURIComponent(source || 'leave')}`
  })
  .then(res => res.json())
  .then(data => {
    if(data.success) {
      showToast(`Leave request ${action}d successfully!`, 'success');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(data.message || 'Failed to process request', 'error');
      btn.disabled = false;
      btn.innerHTML = originalHTML;
    }
  })
  .catch(err => {
    showToast('Error: ' + err.message, 'error');
    btn.disabled = false;
    btn.innerHTML = originalHTML;
  });
}

function handleLoanAction(permitId, action, permitType, toolName) {
  const needsPhoto = (action === 'approve' && ['loan', 'handover', 'project'].includes(permitType));
  
  if (needsPhoto) {
    const modal = document.getElementById('modalAdminPhoto');
    document.getElementById('adminPhotoPermitId').value = permitId;
    document.getElementById('adminPhotoToolName').textContent = toolName || 'Tools';
    document.getElementById('adminPhotoInput').value = '';
    document.getElementById('adminPhotoPreview').innerHTML = '';
    document.getElementById('adminPhotoPreview').style.display = 'none';
    const submitBtn = document.getElementById('btnAdminPhotoSubmit');
    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Approve dengan Foto';
    modal.style.display = 'flex';
    return;
  }

  const btn = document.querySelector(`button[data-id="${permitId}"][data-type="${permitType}"]`) || document.querySelector(`button[data-id="${permitId}"]`) || null;
  const originalHTML = btn ? btn.innerHTML : null;
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
  }
  
  fetch('./app/action/handle_loan_action.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `id=${permitId}&action=${action}`
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showToast(`Permit ${action}d successfully!`, 'success');
      setTimeout(() => location.reload(), 1000);
      } else {
        showToast('Failed: ' + data.message, 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = originalHTML; }
      }
  })
  .catch(err => {
    showToast('Error: ' + err.message, 'error');
    if (btn) { btn.disabled = false; btn.innerHTML = originalHTML; }
  });
}

function _inboxCompressPhoto(file, maxW, quality) {
  maxW = maxW || 1280; quality = quality || 0.7;
  if (!file || !file.type.startsWith('image/') || file.size < 204800) return Promise.resolve(file);
  return new Promise(function(resolve) {
    var img = new Image();
    img.onload = function() {
      var w = img.width, h = img.height;
      if (w > maxW) { h = Math.round(h * maxW / w); w = maxW; }
      if (h > maxW) { w = Math.round(w * maxW / h); h = maxW; }
      var c = document.createElement('canvas');
      c.width = w; c.height = h;
      c.getContext('2d').drawImage(img, 0, 0, w, h);
      c.toBlob(function(b) {
        URL.revokeObjectURL(img.src);
        if (!b) return resolve(file);
        resolve(new File([b], file.name.replace(/\.[^.]+$/, '.jpg'), { type: 'image/jpeg' }));
      }, 'image/jpeg', quality);
    };
    img.onerror = function() { resolve(file); };
    img.src = URL.createObjectURL(file);
  });
}

function submitAdminPhotoApprove() {
  const fileInput = document.getElementById('adminPhotoInput');
  const permitId = document.getElementById('adminPhotoPermitId').value;
  const btn = document.getElementById('btnAdminPhotoSubmit');
  
  if (!fileInput.files || fileInput.files.length === 0) {
    showToast('Foto alat wajib diupload sebelum approve!', 'error');
    return;
  }
  
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Uploading & Approving...';
  
  _inboxCompressPhoto(fileInput.files[0]).then(function(compressedFile) {
    const fd = new FormData();
    fd.append('id', permitId);
    fd.append('action', 'approve');
    fd.append('admin_photo', compressedFile);
    
    fetch('./app/action/handle_loan_action.php', {
      method: 'POST',
      body: fd
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showToast('Berhasil di-approve dengan foto verifikasi!', 'success');
        document.getElementById('modalAdminPhoto').style.display = 'none';
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast('Gagal: ' + (data.message || 'Unknown error'), 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Approve dengan Foto';
      }
    })
    .catch(err => {
      showToast('Error: ' + err.message, 'error');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Approve dengan Foto';
    });
  });
}

function closeAdminPhotoModal() {
  document.getElementById('modalAdminPhoto').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function() {
  var photoInput = document.getElementById('adminPhotoInput');
  if (photoInput) {
    photoInput.addEventListener('change', function() {
      var preview = document.getElementById('adminPhotoPreview');
      if (this.files && this.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
          preview.innerHTML = '<img src="' + e.target.result + '" style="max-height:200px;border-radius:8px;object-fit:cover;width:100%;" alt="Preview">';
          preview.style.display = 'block';
        };
        reader.readAsDataURL(this.files[0]);
      } else {
        preview.innerHTML = '';
        preview.style.display = 'none';
      }
    });
  }
});

document.querySelectorAll('.approve-loan').forEach(btn => 
  btn.addEventListener('click', function(){ handleLoanAction(this.dataset.id, 'approve', this.dataset.type, this.dataset.tool); })
);
document.querySelectorAll('.reject-loan').forEach(btn => 
  btn.addEventListener('click', function(){ handleLoanAction(this.dataset.id, 'reject', this.dataset.type, this.dataset.tool); })
);
document.querySelectorAll('.approve-leave').forEach(btn => 
  btn.addEventListener('click', function(){ handleLeaveAction(this.dataset.id, 'approve', this.dataset.source || 'leave'); })
);
document.querySelectorAll('.reject-leave').forEach(btn => 
  btn.addEventListener('click', function(){ handleLeaveAction(this.dataset.id, 'reject', this.dataset.source || 'leave'); })
);

document.documentElement.style.scrollBehavior = 'smooth';

var _selectedReturnIds = new Set();

function updateBulkUI() {
  var countEl = document.getElementById('selectedReturnCount');
  var btn = document.getElementById('btnBulkApprove');
  if (countEl) countEl.textContent = _selectedReturnIds.size;
  if (btn) {
    btn.disabled = _selectedReturnIds.size === 0;
    btn.style.opacity = _selectedReturnIds.size === 0 ? '0.5' : '1';
  }
}

document.querySelectorAll('.return-checkbox').forEach(function(cb) {
  cb.addEventListener('change', function() {
    var id = this.dataset.id;
    if (this.checked) {
      _selectedReturnIds.add(id);
    } else {
      _selectedReturnIds.delete(id);
    }
    var selectAll = document.getElementById('selectAllReturns');
    if (selectAll) {
      var total = document.querySelectorAll('.return-checkbox').length;
      selectAll.checked = _selectedReturnIds.size === total;
      selectAll.indeterminate = _selectedReturnIds.size > 0 && _selectedReturnIds.size < total;
    }
    updateBulkUI();
  });
});

var selectAllEl = document.getElementById('selectAllReturns');
if (selectAllEl) {
  selectAllEl.addEventListener('change', function() {
    var checked = this.checked;
    document.querySelectorAll('.return-checkbox').forEach(function(cb) {
      cb.checked = checked;
      if (checked) {
        _selectedReturnIds.add(cb.dataset.id);
      } else {
        _selectedReturnIds.delete(cb.dataset.id);
      }
    });
    updateBulkUI();
  });
}

function bulkApproveReturns() {
  if (_selectedReturnIds.size === 0) return;
  var count = _selectedReturnIds.size;
  if (!confirm('Approve ' + count + ' return sekaligus?')) return;
  
  var btn = document.getElementById('btnBulkApprove');
  var originalHTML = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
  
  var body = new URLSearchParams();
  body.append('action', 'approve');
  _selectedReturnIds.forEach(function(id) {
    body.append('ids[]', id);
  });
  
  fetch('./app/action/handle_loan_action.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body.toString()
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data.success) {
      showToast(data.message || count + ' return berhasil di-approve', 'success');
      setTimeout(function() { location.reload(); }, 1000);
    } else {
      showToast('Gagal: ' + (data.message || 'Unknown error'), 'error');
      btn.disabled = false;
      btn.innerHTML = originalHTML;
    }
  })
  .catch(function(err) {
    showToast('Error: ' + err.message, 'error');
    btn.disabled = false;
    btn.innerHTML = originalHTML;
  });
}
</script>
