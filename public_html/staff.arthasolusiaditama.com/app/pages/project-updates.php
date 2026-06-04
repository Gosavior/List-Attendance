<?php
 
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/avatar.php';

$currentUserId = (int)$_SESSION['user_id'];
$currentRole   = $_SESSION['role'] ?? '';
$isAdmin       = in_array($currentRole, ['administrator', 'technician_manager']);
$isViewer      = in_array($currentRole, ['administrator', 'technician_manager', 'sales']);
$canSubmit     = in_array($currentRole, ['technician', 'hse', 'internship', 'daily']);

$DAILY_RATES = [
    'technician' => 300000,
    'hse'        => 300000,
    'internship' => 150000,
    'daily'      => 150000,
];


try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `project_daily_updates` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `work_date` DATE NOT NULL,
        `description` TEXT NOT NULL,
        `photos` JSON DEFAULT NULL,
        `daily_rate` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_project_user_date` (`project_id`, `user_id`, `work_date`),
        INDEX `idx_project` (`project_id`),
        INDEX `idx_user` (`user_id`),
        INDEX `idx_date` (`work_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {   }


if (isset($_REQUEST['action'])) {
    header('Content-Type: application/json');

    switch ($_REQUEST['action']) {

    
    case 'get_projects':
        $stmt = $pdo->query("SELECT id, project_name, customer_name FROM asasystem_sales.projects WHERE status = 'ONGOING' ORDER BY project_name");
        echo json_encode(['projects' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;

    
    case 'get_workers':
        $stmt = $pdo->query("SELECT id, full_name, role FROM users WHERE role IN ('technician','hse','internship','daily') AND is_active = 1 ORDER BY full_name");
        echo json_encode(['workers' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;

    
    case 'create':
        if (!$canSubmit) { echo json_encode(['error' => 'Akses ditolak']); exit; }

        $projectId   = (int)($_POST['project_id'] ?? 0);
        $workDate    = trim($_POST['work_date'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!$projectId || !$workDate || !$description) {
            echo json_encode(['error' => 'Project, tanggal, dan deskripsi wajib diisi']);
            exit;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate) || $workDate > date('Y-m-d')) {
            echo json_encode(['error' => 'Tanggal tidak valid atau di masa depan']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM asasystem_sales.projects WHERE id = ? AND status = 'ONGOING'");
        $stmt->execute([$projectId]);
        if (!$stmt->fetch()) { echo json_encode(['error' => 'Project bukan status ONGOING']); exit; }

        
        $stmt = $pdo->prepare("SELECT id, photos FROM project_daily_updates WHERE project_id = ? AND user_id = ? AND work_date = ?");
        $stmt->execute([$projectId, $currentUserId, $workDate]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        
        $newPhotos = [];
        $allowed = ['jpg','jpeg','png','gif','webp','heic','heif','avif','svg'];
        $dir = __DIR__ . '/../../storage/uploads/project-updates/';
        if (!empty($_FILES['photos']['name'][0])) {
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            foreach ($_FILES['photos']['tmp_name'] as $i => $tmp) {
                if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $ext = strtolower(pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed) || $_FILES['photos']['size'][$i] > 10485760) continue;
                $fn = 'pu_' . $currentUserId . '_' . $projectId . '_' . str_replace('-','',$workDate) . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($tmp, $dir . $fn)) {
                    // Compress image for faster loading
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                        require_once __DIR__ . '/../helpers/image-compress.php';
                        compressUploadedImage($dir . $fn, 1280, 1280, 75);
                    }
                    $newPhotos[] = 'storage/uploads/project-updates/' . $fn;
                }
            }
        }

        
        if ($existing) {
            $oldPhotos = json_decode($existing['photos'] ?? '[]', true) ?: [];
            if ($newPhotos) {
                foreach ($oldPhotos as $op) { $p = __DIR__ . '/../../' . $op; if (file_exists($p)) @unlink($p); }
                $finalPhotos = $newPhotos;
            } else {
                $finalPhotos = $oldPhotos;
            }
        } else {
            $finalPhotos = $newPhotos;
        }

        $dailyRate = $DAILY_RATES[$currentRole] ?? 0;

        try {
            $stmt = $pdo->prepare("INSERT INTO project_daily_updates (project_id, user_id, work_date, description, photos, daily_rate)
                VALUES (?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE description=VALUES(description), photos=VALUES(photos), daily_rate=VALUES(daily_rate), updated_at=NOW()");
            $stmt->execute([$projectId, $currentUserId, $workDate, $description, json_encode($finalPhotos), $dailyRate]);
            echo json_encode(['success' => true, 'message' => $existing ? 'Update diperbarui' : 'Update berhasil disimpan']);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Gagal menyimpan: ' . $e->getMessage()]);
        }
        exit;

    
    case 'list':
        $pg     = max(1, (int)($_GET['pg'] ?? 1));
        $limit  = $isViewer ? 100 : 20;
        $offset = ($pg - 1) * $limit;

        $where = []; $params = [];
        if (!$isViewer) { $where[] = 'pdu.user_id = ?'; $params[] = $currentUserId; }
        if (!empty($_GET['project_id']))                     { $where[] = 'pdu.project_id = ?'; $params[] = (int)$_GET['project_id']; }
        if (!empty($_GET['user_id']) && $isViewer)           { $where[] = 'pdu.user_id = ?';    $params[] = (int)$_GET['user_id']; }
        if (!empty($_GET['date_from']))                      { $where[] = 'pdu.work_date >= ?'; $params[] = $_GET['date_from']; }
        if (!empty($_GET['date_to']))                        { $where[] = 'pdu.work_date <= ?'; $params[] = $_GET['date_to']; }

        $wc = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM project_daily_updates pdu $wc");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $orderBy = $isViewer ? 'p.project_name, u.full_name, pdu.work_date DESC' : 'pdu.work_date DESC, pdu.created_at DESC';
        $sql = "SELECT pdu.*, p.project_name, p.customer_name, u.full_name, u.role, u.avatar
                FROM project_daily_updates pdu
                LEFT JOIN asasystem_sales.projects p ON p.id = pdu.project_id
                LEFT JOIN users u ON u.id = pdu.user_id
                $wc ORDER BY $orderBy
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['photos'] = json_decode($r['photos'] ?? '[]', true) ?: [];
            $r['avatar_url'] = getAvatarUrl($r);
        }

        echo json_encode([
            'updates'     => $rows,
            'total'       => $total,
            'page'        => $pg,
            'total_pages' => max(1, (int)ceil($total / $limit)),
        ]);
        exit;

    
    case 'delete':
        if (!$isAdmin) { echo json_encode(['error' => 'Hanya admin yang bisa menghapus']); exit; }
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID tidak valid']); exit; }

        $stmt = $pdo->prepare("SELECT * FROM project_daily_updates WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['error' => 'Tidak ditemukan']); exit; }

        $photos = json_decode($row['photos'] ?? '[]', true) ?: [];
        foreach ($photos as $ph) { $p = __DIR__ . '/../../' . $ph; if (file_exists($p)) @unlink($p); }

        $pdo->prepare("DELETE FROM project_daily_updates WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;

    
    case 'edit':
        if (!$canSubmit) { echo json_encode(['error' => 'Akses ditolak']); exit; }
        $id = (int)($_POST['id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        if (!$id || !$description) { echo json_encode(['error' => 'ID dan deskripsi wajib diisi']); exit; }

        $stmt = $pdo->prepare("SELECT * FROM project_daily_updates WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['error' => 'Tidak ditemukan']); exit; }
        if ($row['user_id'] != $currentUserId) { echo json_encode(['error' => 'Hanya bisa edit milik sendiri']); exit; }

        
        $newPhotos = [];
        $allowed = ['jpg','jpeg','png','gif','webp','heic','heif','avif','svg'];
        $dir = __DIR__ . '/../../storage/uploads/project-updates/';
        if (!empty($_FILES['photos']['name'][0])) {
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            foreach ($_FILES['photos']['tmp_name'] as $i => $tmp) {
                if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $ext = strtolower(pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed) || $_FILES['photos']['size'][$i] > 10485760) continue;
                $fn = 'pu_' . $currentUserId . '_' . $row['project_id'] . '_' . str_replace('-','', $row['work_date']) . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($tmp, $dir . $fn)) {
                    // Compress image for faster loading
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                        require_once __DIR__ . '/../helpers/image-compress.php';
                        compressUploadedImage($dir . $fn, 1280, 1280, 75);
                    }
                    $newPhotos[] = 'storage/uploads/project-updates/' . $fn;
                }
            }
        }

        if ($newPhotos) {
            
            $oldPhotos = json_decode($row['photos'] ?? '[]', true) ?: [];
            foreach ($oldPhotos as $op) { $p = __DIR__ . '/../../' . $op; if (file_exists($p)) @unlink($p); }
            $finalPhotos = $newPhotos;
        } else {
            $finalPhotos = json_decode($row['photos'] ?? '[]', true) ?: [];
        }

        $pdo->prepare("UPDATE project_daily_updates SET description = ?, photos = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$description, json_encode($finalPhotos), $id]);
        echo json_encode(['success' => true, 'message' => 'Update berhasil diperbarui']);
        exit;

    
    case 'summary':
        if (!$isViewer) { echo json_encode(['error' => 'Akses ditolak']); exit; }

        $where = []; $params = [];
        if (!empty($_GET['project_id'])) { $where[] = 'pdu.project_id = ?'; $params[] = (int)$_GET['project_id']; }
        if (!empty($_GET['date_from']))  { $where[] = 'pdu.work_date >= ?'; $params[] = $_GET['date_from']; }
        if (!empty($_GET['date_to']))    { $where[] = 'pdu.work_date <= ?'; $params[] = $_GET['date_to']; }
        $wc = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT pdu.project_id, p.project_name, pdu.user_id, u.full_name, u.role,
                       COUNT(*) AS total_days, pdu.daily_rate, SUM(pdu.daily_rate) AS total_cost
                FROM project_daily_updates pdu
                LEFT JOIN asasystem_sales.projects p ON p.id = pdu.project_id
                LEFT JOIN users u ON u.id = pdu.user_id
                $wc GROUP BY pdu.project_id, pdu.user_id, pdu.daily_rate
                ORDER BY p.project_name, u.full_name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['summary' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    echo json_encode(['error' => 'Action tidak valid']);
    exit;
}


$inputCls = '';
?>

<div class="max-w-5xl mx-auto px-4 py-6" id="projectUpdatesPage">

  <style>
    html, body { overflow-y: auto !important; height: auto !important; }
    @media (max-width: 768px) {
    }
  </style>

  
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
    <h1 style="font-size:22px;font-weight:700;margin:0" class="text-gray-900 dark:text-white">
      <i class="fas fa-clipboard-list" style="color:#3b82f6;margin-right:8px"></i>Update Project
    </h1>
    <div class="pu-tab-bar" id="tabBar">
      <?php if ($canSubmit): ?>
      <button class="pu-tab active" onclick="switchTab('form')" id="tabForm">
        <i class="fas fa-plus-circle"></i>Laporan
      </button>
      <?php endif; ?>
      <button class="pu-tab<?= !$canSubmit ? ' active' : '' ?>" onclick="switchTab('timeline')" id="tabTimeline">
        <i class="fas fa-stream"></i>Timeline
      </button>
      <?php if ($isViewer): ?>
      <button class="pu-tab" onclick="switchTab('summary')" id="tabSummary">
        <i class="fas fa-calculator"></i>Ringkasan Biaya
      </button>
      <?php endif; ?>
    </div>
  </div>

  
  <style>
    .pu-tab-bar{display:flex;background:#f1f5f9;border-radius:12px;padding:4px;gap:4px}
    .dark .pu-tab-bar{background:#1e293b}
    .pu-tab{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:none;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;transition:all 0.2s;background:transparent;color:#64748b}
    .pu-tab:hover{color:#1e293b;background:rgba(255,255,255,0.5)}
    .dark .pu-tab{color:#94a3b8}
    .dark .pu-tab:hover{color:#e2e8f0;background:rgba(255,255,255,0.05)}
    .pu-tab.active{background:#fff;color:#1e293b;box-shadow:0 1px 3px rgba(0,0,0,0.1)}
    .dark .pu-tab.active{background:#334155;color:#f1f5f9}
    .pu-input{width:100%;border:2px solid #e5e7eb;background:#fff;color:#111827;border-radius:12px;padding:10px 14px;font-size:14px;transition:all 0.2s;outline:none;box-sizing:border-box}
    .pu-input:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,0.15)}
    .pu-input::placeholder{color:#9ca3af}
    .dark .pu-input{background:#1f2937;color:#f3f4f6;border-color:#4b5563}
    .dark .pu-input:focus{border-color:#60a5fa;box-shadow:0 0 0 3px rgba(96,165,250,0.2)}
    select.pu-input option{background:#fff;color:#111827;padding:8px 12px}
    select.pu-input option:checked{background:#2563eb;color:#fff}
    .dark select.pu-input option{background:#1f2937;color:#f3f4f6}
    .dark select.pu-input option:checked{background:#3b82f6;color:#fff}
    select.pu-input{appearance:auto}
    .pu-panel{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
    .dark .pu-panel{background:#1f2937;border-color:#374151}
    .pu-th{padding:12px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b}
    .dark .pu-th{color:#94a3b8}
    .pu-td{padding:12px 16px;border-bottom:1px solid #f1f5f9}
    .dark .pu-td{border-color:#1e293b}
    @keyframes puSlideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    .pu-chev{transition:transform 0.2s}
    .pu-chev-open{transform:rotate(0deg)}
    .pu-chev:not(.pu-chev-open){transform:rotate(-90deg)}
  </style>

  
  <?php if ($canSubmit): ?>
  <style>
    .pu-card{background:linear-gradient(135deg,#eff6ff 0%,#e0e7ff 100%);border:1px solid #bfdbfe;border-radius:1rem;padding:1.5rem;box-shadow:0 4px 6px -1px rgba(0,0,0,0.07),0 2px 4px -2px rgba(0,0,0,0.05)}
    .dark .pu-card{background:linear-gradient(135deg,#1e293b 0%,#1e1b4b 100%);border-color:#374151}
    .pu-icon-box{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#2563eb,#4f46e5);display:flex;align-items:center;justify-content:center;box-shadow:0 8px 16px -4px rgba(37,99,235,0.4)}
    textarea.pu-input{resize:none}
    .pu-drop{display:flex;flex-direction:column;align-items:center;justify-content:center;width:100%;min-height:120px;border:2px dashed #d1d5db;border-radius:12px;cursor:pointer;transition:all 0.2s;background:#f9fafb}
    .pu-drop:hover{border-color:#60a5fa;background:#eff6ff}
    .dark .pu-drop{background:#111827;border-color:#4b5563}
    .dark .pu-drop:hover{border-color:#60a5fa;background:#1e293b}
    .pu-btn{display:inline-flex;align-items:center;gap:8px;padding:12px 32px;background:linear-gradient(135deg,#2563eb,#4f46e5);color:#fff;border:none;border-radius:12px;font-weight:600;font-size:14px;cursor:pointer;transition:all 0.2s;box-shadow:0 8px 16px -4px rgba(37,99,235,0.35)}
    .pu-btn:hover{transform:translateY(-1px);box-shadow:0 12px 20px -4px rgba(37,99,235,0.45)}
    .pu-btn:active{transform:translateY(0);box-shadow:0 4px 8px -2px rgba(37,99,235,0.3)}
    .pu-btn:disabled{opacity:0.5;cursor:not-allowed;transform:none;box-shadow:none}
    .pu-label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
    .pu-label i{margin-right:4px;color:#3b82f6}
    .dark .pu-label{color:#d1d5db}
  </style>
  <div id="panelForm">
  <div class="pu-card">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
      <div class="pu-icon-box"><i class="fas fa-plus" style="color:#fff;font-size:14px"></i></div>
      <div>
        <h2 style="font-size:18px;font-weight:700;margin:0" class="text-gray-900 dark:text-white">Laporan Harian</h2>
        <p style="font-size:12px;margin:2px 0 0" class="text-gray-500 dark:text-gray-400">Update progres pekerjaan project hari ini</p>
      </div>
    </div>
    <form id="formUpdate" enctype="multipart/form-data">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
        <div>
          <label class="pu-label"><i class="fas fa-building"></i>Project</label>
          <select name="project_id" id="selProject" required class="pu-input">
            <option value="">-- Pilih Project --</option>
          </select>
        </div>
        <div>
          <label class="pu-label"><i class="fas fa-calendar-alt"></i>Tanggal Kerja</label>
          <input type="hidden" name="work_date" id="inpDate" value="<?= date('Y-m-d') ?>">
          <div class="pu-input" style="display:flex;align-items:center;gap:8px;cursor:default;background:#f0f7ff">
            <i class="fas fa-calendar-check" style="color:#3b82f6"></i>
            <span id="displayDate" style="font-weight:600"><?= strftime('%A, %d %B %Y', strtotime('now')) !== false ? strftime('%A, %d %B %Y', strtotime('now')) : date('d F Y') ?></span>
          </div>
          <p style="font-size:11px;color:#9ca3af;margin-top:4px"><i class="fas fa-info-circle" style="margin-right:3px"></i>Otomatis hari ini</p>
        </div>
      </div>
      <div style="margin-bottom:16px">
        <label class="pu-label"><i class="fas fa-pen"></i>Deskripsi Pekerjaan</label>
        <textarea name="description" id="txtDesc" rows="4" required class="pu-input"
          placeholder="Jelaskan pekerjaan hari ini...&#10;Contoh: Pasang instalasi listrik lantai 2, cek panel utama, ganti MCB 16A"></textarea>
      </div>
      <div style="margin-bottom:20px">
        <label class="pu-label"><i class="fas fa-camera"></i>Foto Dokumentasi</label>
        <label for="inpPhotos" class="pu-drop" id="dropZone">
          <i class="fas fa-cloud-upload-alt" style="font-size:28px;color:#9ca3af;margin-bottom:6px"></i>
          <span style="font-size:13px;color:#6b7280">Klik untuk upload atau drag & drop foto</span>
          <span style="font-size:11px;color:#9ca3af;margin-top:2px">JPG, PNG, WebP (maks 10MB per foto)</span>
        </label>
        <input type="file" name="photos[]" id="inpPhotos" multiple accept="image/*,.heic,.heif,.avif" style="display:none">
        <div id="photoPreview" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px"></div>
      </div>
      <div style="display:flex;justify-content:flex-end">
        <button type="submit" id="btnSubmit" class="pu-btn">
          <i class="fas fa-paper-plane"></i>Kirim Update
        </button>
      </div>
    </form>
  </div>
  </div>
  <?php endif; ?>

  
  <div id="panelTimeline" <?= $canSubmit ? 'style="display:none"' : '' ?>>
    
    <div class="pu-panel" style="margin-bottom:16px">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
        <select id="filterProject" onchange="loadUpdates()" class="pu-input">
          <option value="">Semua Project</option>
        </select>
        <?php if ($isViewer): ?>
        <select id="filterWorker" onchange="loadUpdates()" class="pu-input">
          <option value="">Semua Pekerja</option>
        </select>
        <?php endif; ?>
        <input type="date" id="filterFrom" onchange="loadUpdates()" class="pu-input">
        <input type="date" id="filterTo" onchange="loadUpdates()" class="pu-input">
      </div>
    </div>

    
    <div id="updateList" style="display:flex;flex-direction:column;gap:16px"></div>

    
    <div id="pagination" style="display:flex;justify-content:center;gap:8px;margin-top:24px"></div>
  </div>

  
  <?php if ($isViewer): ?>
  <div id="panelSummary" style="display:none">
    <div class="pu-panel" style="margin-bottom:16px">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
        <select id="sumProject" onchange="loadSummary()" class="pu-input">
          <option value="">Semua Project</option>
        </select>
        <input type="date" id="sumFrom" onchange="loadSummary()" class="pu-input">
        <input type="date" id="sumTo" onchange="loadSummary()" class="pu-input">
      </div>
    </div>
    <div class="pu-panel" style="overflow-x:auto;padding:0">
      <table style="width:100%;font-size:14px;text-align:left;border-collapse:collapse">
        <thead>
          <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0">
            <th class="pu-th">Project</th>
            <th class="pu-th">Pekerja</th>
            <th class="pu-th">Role</th>
            <th class="pu-th" style="text-align:center">Hari Kerja</th>
            <th class="pu-th" style="text-align:right">Rate/Hari</th>
            <th class="pu-th" style="text-align:right">Total Biaya</th>
          </tr>
        </thead>
        <tbody id="summaryBody"></tbody>
        <tfoot id="summaryFoot"></tfoot>
      </table>
      <div id="summaryEmpty" style="display:none;padding:48px;text-align:center;color:#9ca3af">Belum ada data</div>
    </div>
  </div>
  <?php endif; ?>
</div>


<?php if ($canSubmit): ?>
<div id="editModal" style="display:none;position:fixed;inset:0;z-index:9998;background:rgba(0,0,0,0.5);align-items:center;justify-content:center" onclick="if(event.target===this)closeEditModal()">
  <div style="background:#fff;border-radius:16px;padding:24px;width:90%;max-width:500px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25)" class="dark:bg-gray-800" onclick="event.stopPropagation()">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
      <h3 style="font-size:16px;font-weight:700;margin:0" class="text-gray-900 dark:text-white"><i class="fas fa-edit" style="color:#3b82f6;margin-right:8px"></i>Edit Update</h3>
      <button onclick="closeEditModal()" style="background:none;border:none;cursor:pointer;font-size:20px;color:#9ca3af">&times;</button>
    </div>
    <input type="hidden" id="editId">
    <div style="margin-bottom:16px">
      <label class="pu-label"><i class="fas fa-pen"></i>Deskripsi Pekerjaan</label>
      <textarea id="editDesc" rows="5" class="pu-input" placeholder="Deskripsi pekerjaan..."></textarea>
    </div>
    <div style="margin-bottom:20px">
      <label class="pu-label"><i class="fas fa-camera"></i>Ganti Foto (opsional)</label>
      <input type="file" id="editPhotos" multiple accept="image/*,.heic,.heif,.avif" class="pu-input" style="padding:8px">
      <p style="font-size:11px;color:#9ca3af;margin-top:4px">Kosongkan jika tidak ingin mengganti foto</p>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:8px">
      <button onclick="closeEditModal()" style="padding:10px 20px;border-radius:10px;border:1px solid #e5e7eb;background:#fff;color:#374151;font-weight:600;font-size:13px;cursor:pointer">Batal</button>
      <button onclick="submitEdit()" id="btnEdit" class="pu-btn" style="padding:10px 24px;font-size:13px"><i class="fas fa-save"></i>Simpan</button>
    </div>
  </div>
</div>
<?php endif; ?>


<div id="lightbox" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.8);align-items:center;justify-content:center" onclick="closeLightbox()">
  <img id="lightboxImg" src="" alt="" style="max-width:90vw;max-height:90vh;border-radius:12px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5)">
  <button style="position:absolute;top:16px;right:16px;color:#fff;font-size:32px;background:none;border:none;cursor:pointer">&times;</button>
</div>

<script>
(() => {
  const API       = 'dashboard.php?page=project-updates';
  const isViewer  = <?= json_encode($isViewer) ?>;
  const canSubmit = <?= json_encode($canSubmit) ?>;
  const myId      = <?= $currentUserId ?>;
  let currentPage = 1;

  function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container-pu');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container-pu';
      Object.assign(container.style, { position:'fixed', top:'16px', right:'16px', maxWidth:'400px', zIndex:'9999', display:'flex', flexDirection:'column', gap:'8px' });
      document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    const cfg = { success: ['#16a34a','✅'], error: ['#dc2626','❌'], warning: ['#eab308','⚠️'] };
    const [bg, icon] = cfg[type] || cfg.success;
    Object.assign(toast.style, { background:bg, color:'#fff', padding:'12px 20px', borderRadius:'12px', boxShadow:'0 10px 25px -5px rgba(0,0,0,0.2)', display:'flex', alignItems:'center', gap:'10px', animation:'puSlideIn 0.3s ease-out', fontSize:'14px', fontWeight:'500' });
    toast.innerHTML = `<span style="font-size:18px">${icon}</span><span style="flex:1">${message}</span><button style="color:rgba(255,255,255,0.7);background:none;border:none;cursor:pointer;font-weight:700;font-size:16px;margin-left:8px" onclick="this.parentElement.remove()">&times;</button>`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100%)'; toast.style.transition = 'all 0.3s'; setTimeout(() => toast.remove(), 300); }, 3500);
  }

  function formatRupiah(n) {
    return 'Rp ' + Number(n).toLocaleString('id-ID');
  }
  function formatDate(d) {
    return new Date(d + 'T00:00:00').toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
  }
  function roleBadge(role) {
    const m = {
      technician: ['Teknisi', 'background:#dbeafe;color:#1d4ed8;'],
      internship: ['Magang', 'background:#f3e8ff;color:#7c3aed;'],
      daily:      ['Daily', 'background:#fef3c7;color:#b45309;'],
    };
    const [label, style] = m[role] || [role, 'background:#f3f4f6;color:#374151;'];
    return `<span style="font-size:11px;padding:2px 10px;border-radius:999px;font-weight:600;${style}">${label}</span>`;
  }
  function escHtml(s) {
    const d = document.createElement('div'); d.textContent = s; return d.innerHTML;
  }

  async function loadProjects() {
    try {
      const res = await fetch(API + '&action=get_projects');
      const data = await res.json();
      const opts = data.projects.map(p => {
        const name = escHtml(p.project_name);
        const cust = escHtml(p.customer_name);
        const label = p.project_name.toLowerCase().includes(p.customer_name.toLowerCase()) ? name : `${name} — ${cust}`;
        return `<option value="${p.id}">${label}</option>`;
      }).join('');
      const selForm = document.getElementById('selProject');
      if (selForm) selForm.innerHTML = '<option value="">-- Pilih Project --</option>' + opts;
      ['filterProject','sumProject'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = '<option value="">Semua Project</option>' + opts;
      });
    } catch (e) { console.error('loadProjects', e); }
  }

  async function loadWorkers() {
    try {
      const res = await fetch(API + '&action=get_workers');
      const data = await res.json();
      const el = document.getElementById('filterWorker');
      if (!el) return;
      el.innerHTML = '<option value="">Semua Pekerja</option>' +
        data.workers.map(w => `<option value="${w.id}">${escHtml(w.full_name)} (${w.role})</option>`).join('');
    } catch (e) { console.error('loadWorkers', e); }
  }

  window.loadUpdates = async function(page) {
    if (page) currentPage = page;
    const params = new URLSearchParams({ action: 'list', pg: currentPage });
    const fp = document.getElementById('filterProject');
    const fw = document.getElementById('filterWorker');
    const ff = document.getElementById('filterFrom');
    const ft = document.getElementById('filterTo');
    if (fp && fp.value) params.set('project_id', fp.value);
    if (fw && fw.value) params.set('user_id', fw.value);
    if (ff && ff.value) params.set('date_from', ff.value);
    if (ft && ft.value) params.set('date_to', ft.value);

    try {
      const res = await fetch(API + '&' + params.toString());
      const data = await res.json();
      renderTimeline(data.updates || []);
      renderPagination(data.page, data.total_pages);
    } catch (e) {
      document.getElementById('updateList').innerHTML = '<div class="text-center text-red-500 py-8">Gagal memuat data</div>';
    }
  };

  function renderTimeline(updates) {
    const container = document.getElementById('updateList');
    if (!updates.length) {
      container.innerHTML = `
        <div class="pu-panel" style="padding:48px;text-align:center">
          <i class="fas fa-clipboard-list" style="font-size:36px;color:#d1d5db;margin-bottom:12px"></i>
          <p style="color:#9ca3af">Belum ada update project</p>
        </div>`;
      return;
    }

    if (isViewer) {
      const grouped = {};
      updates.forEach(u => {
        const pk = u.project_id;
        if (!grouped[pk]) grouped[pk] = { name: u.project_name || '-', customer: u.customer_name || '', users: {} };
        const uk = u.user_id;
        if (!grouped[pk].users[uk]) grouped[pk].users[uk] = { name: u.full_name || 'User', role: u.role, avatar: u.avatar_url, entries: [] };
        grouped[pk].users[uk].entries.push(u);
      });

      let html = '';
      for (const pk in grouped) {
        const proj = grouped[pk];
        const projLabel = proj.name + (proj.customer && !proj.name.toLowerCase().includes(proj.customer.toLowerCase()) ? ' — ' + escHtml(proj.customer) : '');
        html += `<div class="pu-panel" style="padding:0;overflow:hidden">`;
        html += `<div onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='none'?'':'none';this.querySelector('.pu-chev').classList.toggle('pu-chev-open')" style="display:flex;align-items:center;gap:10px;padding:16px 20px;cursor:pointer;background:linear-gradient(135deg,#eff6ff,#e0e7ff);user-select:none">
          <i class="fas fa-building" style="color:#2563eb;font-size:16px"></i>
          <span style="font-weight:700;font-size:15px;flex:1" class="text-gray-900 dark:text-white">${escHtml(projLabel)}</span>
          <i class="fas fa-chevron-down pu-chev pu-chev-open" style="color:#64748b;transition:transform 0.2s"></i>
        </div>`;
        html += `<div style="padding:0">`;
        for (const uk in proj.users) {
          const usr = proj.users[uk];
          html += `<div style="border-top:1px solid #e5e7eb">`;
          html += `<div onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='none'?'':'none';this.querySelector('.pu-chev').classList.toggle('pu-chev-open')" style="display:flex;align-items:center;gap:10px;padding:12px 20px 12px 36px;cursor:pointer;background:#f8fafc;user-select:none">
            <img src="${escHtml(usr.avatar || 'public/assets/images/default-avatar.png')}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb">
            <span style="font-weight:600;font-size:14px;flex:1" class="text-gray-900 dark:text-white">${escHtml(usr.name)}</span>
            ${roleBadge(usr.role)}
            <span style="font-size:12px;color:#6b7280;margin-left:8px">${usr.entries.length} laporan</span>
            <i class="fas fa-chevron-down pu-chev pu-chev-open" style="color:#94a3b8;font-size:12px;transition:transform 0.2s"></i>
          </div>`;
          html += `<div style="padding:0">`;
          usr.entries.forEach(u => {
            const photos = (u.photos || []).map(ph =>
              `<img src="${escHtml(ph)}" alt="Foto" onclick="event.stopPropagation();openLightbox('${escHtml(ph)}')" style="width:72px;height:72px;object-fit:cover;border-radius:8px;cursor:pointer;border:2px solid #e5e7eb;transition:opacity 0.2s" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">`
            ).join('');
            const canDel = <?= json_encode($isAdmin) ?>;
            const delBtn = canDel ? `<button onclick="event.stopPropagation();deleteUpdate(${u.id})" style="font-size:11px;color:#ef4444;background:none;border:none;cursor:pointer;padding:2px 6px" title="Hapus"><i class="fas fa-trash"></i></button>` : '';
            html += `<div style="padding:12px 20px 12px 56px;border-top:1px solid #f1f5f9">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <i class="fas fa-calendar" style="color:#3b82f6;font-size:12px"></i>
                <span style="font-size:13px;font-weight:600;color:#374151">${formatDate(u.work_date)}</span>
                <span style="font-size:12px;color:#16a34a;font-weight:600;margin-left:auto">${formatRupiah(u.daily_rate)}</span>
                ${delBtn}
              </div>
              <p style="font-size:13px;white-space:pre-line;margin:0 0 8px;color:#4b5563;line-height:1.5">${escHtml(u.description)}</p>
              ${photos ? `<div style="display:flex;flex-wrap:wrap;gap:6px">${photos}</div>` : '<span style="font-size:12px;color:#9ca3af;font-style:italic">Tidak ada foto</span>'}
            </div>`;
          });
          html += `</div></div>`; // close entries + user
        }
        html += `</div></div>`; // close users + project panel
      }
      container.innerHTML = html;
      return;
    }

    container.innerHTML = updates.map(u => {
      const photos = (u.photos || []).map(ph =>
        `<img src="${escHtml(ph)}" alt="Foto" onclick="openLightbox('${escHtml(ph)}')" style="width:80px;height:80px;object-fit:cover;border-radius:10px;cursor:pointer;border:2px solid #e5e7eb;transition:opacity 0.2s" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">`
      ).join('');

      const canEdit = u.user_id == myId;
      const editBtn = canEdit
        ? `<button onclick="openEditModal(${u.id})" data-desc="${escHtml(u.description)}" style="font-size:12px;color:#3b82f6;background:none;border:none;cursor:pointer;padding:4px" title="Edit"><i class="fas fa-edit"></i></button>`
        : '';

      return `
        <div class="pu-panel" style="padding:20px">
          <div style="display:flex;align-items:flex-start;gap:12px">
            <img src="${escHtml(u.avatar_url || 'public/assets/images/default-avatar.png')}" alt="" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;flex-shrink:0">
            <div style="flex:1;min-width:0">
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <span style="font-weight:600;font-size:14px" class="text-gray-900 dark:text-white">${escHtml(u.full_name || 'User')}</span>
                ${roleBadge(u.role)}
                <span style="margin-left:auto">${editBtn}</span>
              </div>
              <div style="display:flex;align-items:center;gap:8px;margin-top:4px;font-size:12px;color:#6b7280;flex-wrap:wrap">
                <span><i class="fas fa-building" style="margin-right:4px"></i>${escHtml(u.project_name || '')}</span>
                <span>•</span>
                <span><i class="fas fa-calendar" style="margin-right:4px"></i>${formatDate(u.work_date)}</span>
              </div>
              <p style="margin-top:12px;font-size:14px;white-space:pre-line" class="text-gray-700 dark:text-gray-300">${escHtml(u.description)}</p>
              ${photos ? `<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px">${photos}</div>` : ''}
            </div>
          </div>
        </div>`;
    }).join('');
  }

  function renderPagination(page, totalPages) {
    const el = document.getElementById('pagination');
    if (totalPages <= 1) { el.innerHTML = ''; return; }
    let html = '';
    const base = 'padding:6px 14px;border-radius:10px;font-size:13px;font-weight:600;border:none;cursor:pointer;transition:all 0.2s;';
    const inactive = base + 'background:#f1f5f9;color:#64748b;';
    const active   = base + 'background:#2563eb;color:#fff;box-shadow:0 4px 6px -1px rgba(37,99,235,0.3);';
    if (page > 1) html += `<button onclick="loadUpdates(${page - 1})" style="${inactive}"><i class="fas fa-chevron-left"></i></button>`;
    for (let i = Math.max(1, page - 2); i <= Math.min(totalPages, page + 2); i++) {
      html += `<button onclick="loadUpdates(${i})" style="${i === page ? active : inactive}">${i}</button>`;
    }
    if (page < totalPages) html += `<button onclick="loadUpdates(${page + 1})" style="${inactive}"><i class="fas fa-chevron-right"></i></button>`;
    el.innerHTML = html;
  }

  if (canSubmit) {
    document.getElementById('formUpdate').addEventListener('submit', async function(e) {
      e.preventDefault();
      const btn = document.getElementById('btnSubmit');
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Menyimpan...';

      try {
        const fd = new FormData(this);
        fd.append('action', 'create');
        const res = await fetch(API, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.error) {
          showToast(data.error, 'error');
        } else {
          showToast(data.message || 'Update berhasil disimpan', 'success');
          document.getElementById('txtDesc').value = '';
          document.getElementById('inpPhotos').value = '';
          document.getElementById('photoPreview').innerHTML = '';
          loadUpdates(1);
        }
      } catch (err) {
        showToast('Gagal menyimpan update', 'error');
      } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane mr-1"></i>Kirim Update';
      }
    });

    document.getElementById('inpPhotos').addEventListener('change', function() {
      const preview = document.getElementById('photoPreview');
      preview.innerHTML = '';
      Array.from(this.files).forEach(file => {
        if (!file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = e => {
          preview.innerHTML += `<img src="${e.target.result}" style="width:64px;height:64px;object-fit:cover;border-radius:10px;border:2px solid #e5e7eb">`;
        };
        reader.readAsDataURL(file);
      });
    });
  }

  window.deleteUpdate = async function(id) {
    if (!confirm('Hapus update ini?')) return;
    try {
      const fd = new FormData();
      fd.append('action', 'delete');
      fd.append('id', id);
      const res = await fetch(API, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.error) showToast(data.error, 'error');
      else { showToast('Update berhasil dihapus', 'success'); loadUpdates(currentPage); }
    } catch (e) { showToast('Gagal menghapus', 'error'); }
  };

  window.openEditModal = function(id) {
    const btn = event.currentTarget;
    const desc = btn.getAttribute('data-desc') || '';
    document.getElementById('editId').value = id;
    document.getElementById('editDesc').value = desc;
    document.getElementById('editPhotos').value = '';
    document.getElementById('editModal').style.display = 'flex';
  };
  window.closeEditModal = function() {
    document.getElementById('editModal').style.display = 'none';
  };
  window.submitEdit = async function() {
    const id = document.getElementById('editId').value;
    const desc = document.getElementById('editDesc').value.trim();
    if (!desc) { showToast('Deskripsi tidak boleh kosong', 'error'); return; }
    const btn = document.getElementById('btnEdit');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    try {
      const fd = new FormData();
      fd.append('action', 'edit');
      fd.append('id', id);
      fd.append('description', desc);
      const files = document.getElementById('editPhotos').files;
      for (let i = 0; i < files.length; i++) fd.append('photos[]', files[i]);
      const res = await fetch(API, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.error) showToast(data.error, 'error');
      else { showToast(data.message || 'Berhasil diperbarui', 'success'); closeEditModal(); loadUpdates(currentPage); }
    } catch (e) { showToast('Gagal menyimpan', 'error'); }
    finally { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Simpan'; }
  };

  window.loadSummary = async function() {
    const params = new URLSearchParams({ action: 'summary' });
    const sp = document.getElementById('sumProject');
    const sf = document.getElementById('sumFrom');
    const st = document.getElementById('sumTo');
    if (sp && sp.value) params.set('project_id', sp.value);
    if (sf && sf.value) params.set('date_from', sf.value);
    if (st && st.value) params.set('date_to', st.value);

    try {
      const res = await fetch(API + '&' + params.toString());
      const data = await res.json();
      renderSummary(data.summary || []);
    } catch (e) {
      document.getElementById('summaryBody').innerHTML = '<tr><td colspan="6" class="text-center text-red-500 py-4">Gagal memuat</td></tr>';
    }
  };

  function renderSummary(rows) {
    const body = document.getElementById('summaryBody');
    const foot = document.getElementById('summaryFoot');
    const empty = document.getElementById('summaryEmpty');

    if (!rows.length) {
      body.innerHTML = '';
      foot.innerHTML = '';
      empty.style.display = '';
      return;
    }
    empty.style.display = 'none';

    let grandDays = 0, grandCost = 0;

    body.innerHTML = rows.map(r => {
      grandDays += parseInt(r.total_days);
      grandCost += parseFloat(r.total_cost);
      return `<tr style="transition:background 0.15s" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
        <td class="pu-td" style="font-weight:600" >${escHtml(r.project_name || '-')}</td>
        <td class="pu-td">${escHtml(r.full_name || '-')}</td>
        <td class="pu-td">${roleBadge(r.role)}</td>
        <td class="pu-td" style="text-align:center;font-weight:600">${r.total_days}</td>
        <td class="pu-td" style="text-align:right">${formatRupiah(r.daily_rate)}</td>
        <td class="pu-td" style="text-align:right;font-weight:700">${formatRupiah(r.total_cost)}</td>
      </tr>`;
    }).join('');

    foot.innerHTML = `
      <tr style="background:linear-gradient(135deg,#eff6ff,#e0e7ff);font-weight:700;font-size:14px">
        <td style="padding:14px 16px" colspan="3">Grand Total</td>
        <td style="padding:14px 16px;text-align:center">${grandDays} hari</td>
        <td style="padding:14px 16px"></td>
        <td style="padding:14px 16px;text-align:right;color:#2563eb;font-size:16px">${formatRupiah(grandCost)}</td>
      </tr>`;
  }

  window.switchTab = function(tab) {
    ['panelForm','panelTimeline','panelSummary'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.style.display = 'none';
    });
    document.querySelectorAll('.pu-tab').forEach(t => t.classList.remove('active'));

    const panelId = 'panel' + tab.charAt(0).toUpperCase() + tab.slice(1);
    const tabId   = 'tab'   + tab.charAt(0).toUpperCase() + tab.slice(1);
    const panel = document.getElementById(panelId);
    const btn   = document.getElementById(tabId);
    if (panel) panel.style.display = '';
    if (btn) btn.classList.add('active');

    if (tab === 'timeline') loadUpdates(1);
    if (tab === 'summary') loadSummary();
  };

  window.openLightbox = function(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').style.display = 'flex';
  };
  window.closeLightbox = function() {
    document.getElementById('lightbox').style.display = 'none';
    document.getElementById('lightboxImg').src = '';
  };

  (function() {
    const el = document.getElementById('displayDate');
    if (el) {
      const today = new Date();
      el.textContent = today.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    }
  })();

  loadProjects();
  if (isViewer) loadWorkers();
  loadUpdates(1);
})();
</script>
