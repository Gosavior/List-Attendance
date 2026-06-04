<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/avatar.php';
require_once __DIR__ . '/../helpers/socket-notify.php';
require_once __DIR__ . '/../helpers/audit-log.php';

$currentRole  = strtolower($_SESSION['role'] ?? '');
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentYear   = (int)date('Y');


try {
    $pdo->exec("ALTER TABLE cuti_requests ADD COLUMN is_retroactive TINYINT(1) NOT NULL DEFAULT 0 AFTER year");
} catch (Exception $e) {
    
}


$action = $_POST['action'] ?? $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($action && $method === 'POST') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Unknown action'];
    try {
        switch ($action) {

            
            case 'submit_cuti':
                $startDate = trim($_POST['start_date'] ?? '');
                $endDate   = trim($_POST['end_date'] ?? '');
                $reason    = trim($_POST['reason'] ?? '');
                $isRetroactive = isset($_POST['is_retroactive']) && $_POST['is_retroactive'] === '1';

                if (!$startDate || !$endDate || !$reason) {
                    throw new Exception('Tanggal dan alasan wajib diisi');
                }
                if ($startDate > $endDate) throw new Exception('Tanggal mulai tidak boleh setelah tanggal selesai');
                
                
                if (!$isRetroactive && $startDate < date('Y-m-d')) {
                    throw new Exception('Untuk cuti tanggal yang sudah lewat, gunakan opsi "Cuti Susulan"');
                }
                
                
                if ($isRetroactive) {
                    $maxPastDate = date('Y-m-d', strtotime('-30 days'));
                    if ($startDate < $maxPastDate) {
                        throw new Exception('Cuti susulan maksimal 30 hari ke belakang');
                    }
                    if ($startDate >= date('Y-m-d')) {
                        throw new Exception('Cuti susulan hanya untuk tanggal yang sudah lewat');
                    }
                }

                
                $totalDays = 0;
                $d = new DateTime($startDate);
                $end = new DateTime($endDate);
                while ($d <= $end) {
                    if ($d->format('N') < 6) $totalDays++; 
                    $d->modify('+1 day');
                }
                if ($totalDays < 1) throw new Exception('Minimal cuti 1 hari kerja');

                
                $year = (int)date('Y', strtotime($startDate));
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_days),0) FROM cuti_requests WHERE user_id=? AND year=? AND status IN ('pending','manager_approved','approved')");
                $stmt->execute([$currentUserId, $year]);
                $used = (int)$stmt->fetchColumn();
                $remaining = 10 - $used;
                if ($totalDays > $remaining) {
                    throw new Exception("Sisa kuota cuti Anda tahun $year tinggal $remaining hari. Anda mengajukan $totalDays hari.");
                }

                
                $proofPath = null;
                if (!empty($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../../storage/uploads/cuti/' . $currentUserId . '/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $ext = pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION);
                    $allowed = ['jpg','jpeg','png','gif','webp','pdf'];
                    if (!in_array(strtolower($ext), $allowed)) throw new Exception('Format file tidak didukung');
                    $fname = 'cuti-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                    move_uploaded_file($_FILES['proof']['tmp_name'], $uploadDir . $fname);

                    // Compress image for faster loading
                    if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'webp'])) {
                        require_once __DIR__ . '/../helpers/image-compress.php';
                        compressUploadedImage($uploadDir . $fname, 1280, 1280, 75);
                    }

                    $proofPath = 'storage/uploads/cuti/' . $currentUserId . '/' . $fname;
                }

                $stmt = $pdo->prepare("INSERT INTO cuti_requests (user_id, start_date, end_date, total_days, reason, proof_path, status, year, is_retroactive) VALUES (?,?,?,?,?,?,'pending',?,?)");
                $stmt->execute([$currentUserId, $startDate, $endDate, $totalDays, $reason, $proofPath, $year, $isRetroactive ? 1 : 0]);

                
                $staffName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Staff';
                $retroLabel = $isRetroactive ? ' (Cuti Susulan)' : '';
                $mgrStmt = $pdo->prepare("SELECT id FROM users WHERE role='technician_manager' AND is_active=1");
                $mgrStmt->execute();
                $mgrIds = array_column($mgrStmt->fetchAll(PDO::FETCH_ASSOC), 'id');
                if ($mgrIds) {
                    socketNotify($mgrIds, 'cuti_request', "$staffName mengajukan cuti $totalDays hari$retroLabel ($startDate s/d $endDate)");
                }

                $response = ['success' => true, 'message' => "Pengajuan cuti $totalDays hari$retroLabel berhasil dikirim"];
                auditLog($pdo, 'leave_submit', [
                    'target_type' => 'leave_request',
                    'target_id' => (int)$pdo->lastInsertId(),
                    'details' => ['start_date' => $startDate, 'end_date' => $endDate, 'days' => $totalDays, 'type' => $leaveType ?? '']
                ]);
                break;

            
            case 'manager_approve_cuti':
                if ($currentRole !== 'technician_manager') throw new Exception('Akses ditolak');
                $id = (int)($_POST['cuti_id'] ?? 0);
                if (!$id) throw new Exception('ID tidak valid');

                $stmt = $pdo->prepare("UPDATE cuti_requests SET status='manager_approved', manager_decided_by=?, manager_decided_at=NOW() WHERE id=? AND status='pending'");
                $stmt->execute([$currentUserId, $id]);
                if ($stmt->rowCount() === 0) throw new Exception('Request tidak ditemukan atau sudah diproses');

                
                $cutiRow = $pdo->prepare("SELECT cr.user_id, u.full_name FROM cuti_requests cr JOIN users u ON u.id=cr.user_id WHERE cr.id=?");
                $cutiRow->execute([$id]);
                $cutiInfo = $cutiRow->fetch(PDO::FETCH_ASSOC);
                $adminStmt = $pdo->prepare("SELECT id FROM users WHERE role IN ('administrator','direktur') AND is_active=1");
                $adminStmt->execute();
                $adminIds = array_column($adminStmt->fetchAll(PDO::FETCH_ASSOC), 'id');
                if ($adminIds) {
                    socketNotify($adminIds, 'cuti_request', 'Cuti ' . ($cutiInfo['full_name'] ?? 'Staff') . ' disetujui Manager, menunggu approval Admin');
                }
                if ($cutiInfo) {
                    socketNotify([(int)$cutiInfo['user_id']], 'cuti_update', 'Cuti Anda telah disetujui oleh Manager');
                }

                $response = ['success' => true, 'message' => 'Cuti disetujui oleh Manager, menunggu approval Admin'];
                auditLog($pdo, 'leave_manager_approve', [
                    'target_type' => 'leave_request',
                    'target_id' => $id,
                    'target_user_id' => $cutiInfo['user_id'] ?? null,
                ]);
                break;

            
            case 'admin_approve_cuti':
                if ($currentRole !== 'administrator') throw new Exception('Akses ditolak');
                $id = (int)($_POST['cuti_id'] ?? 0);
                if (!$id) throw new Exception('ID tidak valid');

                $stmt = $pdo->prepare("UPDATE cuti_requests SET status='admin_approved', admin_decided_by=?, admin_decided_at=NOW() WHERE id=? AND status='manager_approved'");
                $stmt->execute([$currentUserId, $id]);
                if ($stmt->rowCount() === 0) throw new Exception('Request tidak ditemukan atau belum di-approve Manager');

                
                $cutiRow = $pdo->prepare("SELECT cr.user_id, u.full_name FROM cuti_requests cr JOIN users u ON u.id=cr.user_id WHERE cr.id=?");
                $cutiRow->execute([$id]);
                $cutiInfo = $cutiRow->fetch(PDO::FETCH_ASSOC);
                $dirStmt = $pdo->prepare("SELECT id FROM users WHERE role='direktur' AND is_active=1");
                $dirStmt->execute();
                $dirIds = array_column($dirStmt->fetchAll(PDO::FETCH_ASSOC), 'id');
                if ($dirIds) {
                    socketNotify($dirIds, 'cuti_request', 'Cuti ' . ($cutiInfo['full_name'] ?? 'Staff') . ' disetujui Admin, menunggu approval Direktur');
                }
                if ($cutiInfo) {
                    socketNotify([(int)$cutiInfo['user_id']], 'cuti_update', 'Cuti Anda telah disetujui oleh Admin, menunggu Direktur');
                }

                $response = ['success' => true, 'message' => 'Cuti disetujui oleh Admin, menunggu approval Direktur'];
                auditLog($pdo, 'leave_admin_approve', [
                    'target_type' => 'leave_request',
                    'target_id' => $id,
                    'target_user_id' => $cutiInfo['user_id'] ?? null,
                ]);
                break;

            
            case 'director_approve_cuti':
                if ($currentRole !== 'direktur') throw new Exception('Akses ditolak');
                $id = (int)($_POST['cuti_id'] ?? 0);
                if (!$id) throw new Exception('ID tidak valid');

                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE cuti_requests SET status='approved', director_decided_by=?, director_decided_at=NOW() WHERE id=? AND status='admin_approved'");
                $stmt->execute([$currentUserId, $id]);
                if ($stmt->rowCount() === 0) { $pdo->rollBack(); throw new Exception('Request tidak ditemukan atau belum di-approve Admin'); }

                
                $cuti = $pdo->prepare("SELECT * FROM cuti_requests WHERE id=?");
                $cuti->execute([$id]);
                $cuti = $cuti->fetch(PDO::FETCH_ASSOC);

                
                $d = new DateTime($cuti['start_date']);
                $end = new DateTime($cuti['end_date']);
                while ($d <= $end) {
                    if ($d->format('N') < 6) { 
                        $dateStr = $d->format('Y-m-d');
                        $chk = $pdo->prepare("SELECT status FROM attendances WHERE user_id=? AND attendance_date=?");
                        $chk->execute([$cuti['user_id'], $dateStr]);
                        $existing = $chk->fetchColumn();
                        if (!$existing || $existing === 'Alpha') {
                            $pdo->prepare("INSERT INTO attendances (user_id, attendance_date, status, notes, today_plan, check_in_time, check_in_photo, check_out_location) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status='Cuti', notes=VALUES(notes)")
                                ->execute([$cuti['user_id'], $dateStr, 'Cuti', 'Cuti: ' . $cuti['reason'], 'Cuti', $dateStr . ' 00:00:00', '', '']);
                        }
                    }
                    $d->modify('+1 day');
                }
                $pdo->commit();

                
                socketNotify([(int)$cuti['user_id']], 'cuti_update', 'Cuti Anda telah DISETUJUI oleh Direktur dan tercatat di attendance');

                $response = ['success' => true, 'message' => 'Cuti disetujui Direktur dan tercatat di attendance'];
                auditLog($pdo, 'leave_director_approve', [
                    'target_type' => 'leave_request',
                    'target_id' => $id,
                    'target_user_id' => $cuti['user_id'] ?? null,
                    'details' => ['start_date' => $cuti['start_date'] ?? '', 'end_date' => $cuti['end_date'] ?? '']
                ]);
                break;

            
            case 'reject_cuti':
                if (!in_array($currentRole, ['administrator', 'technician_manager', 'direktur'])) throw new Exception('Akses ditolak');
                $id = (int)($_POST['cuti_id'] ?? 0);
                $rejectReason = trim($_POST['reject_reason'] ?? '');
                if (!$id) throw new Exception('ID tidak valid');

                $statusMap = [
                    'technician_manager' => "'pending'",
                    'administrator' => "'pending','manager_approved'",
                    'direktur' => "'pending','manager_approved','admin_approved'"
                ];
                $allowedStatuses = $statusMap[$currentRole] ?? "'pending'";
                
                $cutiRow = $pdo->prepare("SELECT user_id FROM cuti_requests WHERE id=?");
                $cutiRow->execute([$id]);
                $cutiUserId = (int)$cutiRow->fetchColumn();

                $stmt = $pdo->prepare("UPDATE cuti_requests SET status='rejected', rejected_by=?, rejected_at=NOW(), reject_reason=? WHERE id=? AND status IN ($allowedStatuses)");
                $stmt->execute([$currentUserId, $rejectReason, $id]);
                if ($stmt->rowCount() === 0) throw new Exception('Request tidak ditemukan atau sudah diproses');

                
                $roleName = ucfirst($currentRole === 'technician_manager' ? 'Manager' : ($currentRole === 'direktur' ? 'Direktur' : 'Admin'));
                if ($cutiUserId) {
                    socketNotify([$cutiUserId], 'cuti_update', "Cuti Anda ditolak oleh $roleName" . ($rejectReason ? ": $rejectReason" : ''));
                }

                $response = ['success' => true, 'message' => 'Pengajuan cuti ditolak'];
                auditLog($pdo, 'leave_reject', [
                    'target_type' => 'leave_request',
                    'target_id' => $id,
                    'target_user_id' => $cutiUserId,
                    'details' => ['reason' => $rejectReason]
                ]);
                break;

            
            case 'get_quota':
                $userId = ($currentRole === 'administrator' || $currentRole === 'technician_manager')
                    ? (int)($_GET['user_id'] ?? $currentUserId)
                    : $currentUserId;
                $year = (int)($_GET['year'] ?? $currentYear);
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_days),0) FROM cuti_requests WHERE user_id=? AND year=? AND status IN ('pending','manager_approved','approved')");
                $stmt->execute([$userId, $year]);
                $used = (int)$stmt->fetchColumn();
                $response = ['success' => true, 'used' => $used, 'total' => 10, 'remaining' => max(0, 10 - $used)];
                break;
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    echo json_encode($response);
    exit;
}



$stmtQuota = $pdo->prepare("SELECT COALESCE(SUM(total_days),0) FROM cuti_requests WHERE user_id=? AND year=? AND status IN ('pending','manager_approved','admin_approved','approved')");
$stmtQuota->execute([$currentUserId, $currentYear]);
$quotaUsed = (int)$stmtQuota->fetchColumn();
$quotaRemaining = max(0, 10 - $quotaUsed);


$stmtMy = $pdo->prepare("
    SELECT cr.*, u.full_name as user_name,
           m.full_name as manager_name, a.full_name as admin_name, dir.full_name as director_name
    FROM cuti_requests cr
    JOIN users u ON cr.user_id = u.id
    LEFT JOIN users m ON cr.manager_decided_by = m.id
    LEFT JOIN users a ON cr.admin_decided_by = a.id
    LEFT JOIN users dir ON cr.director_decided_by = dir.id
    WHERE cr.user_id = ?
    ORDER BY cr.created_at DESC
    LIMIT 50
");
$stmtMy->execute([$currentUserId]);
$myRequests = $stmtMy->fetchAll(PDO::FETCH_ASSOC);


$pendingRequests = [];
if ($currentRole === 'technician_manager') {
    $stmtPending = $pdo->query("
        SELECT cr.*, u.full_name as user_name
        FROM cuti_requests cr
        JOIN users u ON cr.user_id = u.id
        WHERE cr.status = 'pending'
        ORDER BY cr.created_at ASC
    ");
    $pendingRequests = $stmtPending->fetchAll(PDO::FETCH_ASSOC);
} elseif ($currentRole === 'administrator') {
    $stmtPending = $pdo->query("
        SELECT cr.*, u.full_name as user_name, m.full_name as manager_name
        FROM cuti_requests cr
        JOIN users u ON cr.user_id = u.id
        LEFT JOIN users m ON cr.manager_decided_by = m.id
        WHERE cr.status IN ('pending','manager_approved')
        ORDER BY FIELD(cr.status, 'manager_approved', 'pending'), cr.created_at ASC
    ");
    $pendingRequests = $stmtPending->fetchAll(PDO::FETCH_ASSOC);
} elseif ($currentRole === 'direktur') {
    $stmtPending = $pdo->query("
        SELECT cr.*, u.full_name as user_name, m.full_name as manager_name, a.full_name as admin_name
        FROM cuti_requests cr
        JOIN users u ON cr.user_id = u.id
        LEFT JOIN users m ON cr.manager_decided_by = m.id
        LEFT JOIN users a ON cr.admin_decided_by = a.id
        WHERE cr.status IN ('admin_approved')
        ORDER BY cr.created_at ASC
    ");
    $pendingRequests = $stmtPending->fetchAll(PDO::FETCH_ASSOC);
}


$allRequests = [];
if (in_array($currentRole, ['administrator', 'direktur'])) {
    $stmtAll = $pdo->query("
        SELECT cr.*, u.full_name as user_name
        FROM cuti_requests cr
        JOIN users u ON cr.user_id = u.id
        WHERE cr.year = " . $currentYear . "
        ORDER BY cr.created_at DESC
        LIMIT 100
    ");
    $allRequests = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

    
    $stmtRecap = $pdo->prepare("
        SELECT u.id, u.full_name, u.username, u.role,
               COALESCE(SUM(CASE WHEN cr.status = 'approved' THEN cr.total_days ELSE 0 END), 0) as approved_days,
               COALESCE(SUM(CASE WHEN cr.status IN ('pending','manager_approved','admin_approved') THEN cr.total_days ELSE 0 END), 0) as pending_days,
               COALESCE(SUM(CASE WHEN cr.status = 'rejected' THEN cr.total_days ELSE 0 END), 0) as rejected_days
        FROM users u
        LEFT JOIN cuti_requests cr ON cr.user_id = u.id AND cr.year = ?
        WHERE u.is_active = 1 AND u.role NOT IN ('customer')
        GROUP BY u.id, u.full_name, u.username, u.role
        ORDER BY u.full_name ASC
    ");
    $stmtRecap->execute([$currentYear]);
    $recapData = $stmtRecap->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="space-y-6">
    
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Pengajuan Cuti</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Kelola pengajuan cuti tahunan Anda</p>
        </div>
        <?php if (!in_array($currentRole, ['administrator'])): ?>
        <button onclick="_origOpenCuti()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition font-medium text-sm shadow">
            <i class="fas fa-plus-circle"></i> Ajukan Cuti
        </button>
        <?php endif; ?>
    </div>

    
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center">
                    <i class="fas fa-calendar-check text-blue-600 dark:text-blue-400"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Kuota <?= $currentYear ?></p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">10 <span class="text-sm font-normal text-gray-500">hari</span></p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center">
                    <i class="fas fa-hourglass-half text-amber-600 dark:text-amber-400"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Terpakai / Proses</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white"><?= $quotaUsed ?> <span class="text-sm font-normal text-gray-500">hari</span></p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/40 flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 dark:text-green-400"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sisa Kuota</p>
                    <p class="text-2xl font-bold <?= $quotaRemaining <= 2 ? 'text-red-600' : 'text-green-600' ?>"><?= $quotaRemaining ?> <span class="text-sm font-normal text-gray-500">hari</span></p>
                </div>
            </div>
        </div>
    </div>

    <?php if (in_array($currentRole, ['administrator', 'direktur']) && !empty($recapData)): ?>
    
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="p-5 border-b border-slate-200 dark:border-slate-700">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center">
                        <i class="fas fa-chart-bar text-indigo-500"></i>
                    </div>
                    <div>
                        <h2 class="font-semibold text-gray-900 dark:text-white">Rekap Kuota Cuti <?= $currentYear ?></h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Ringkasan penggunaan cuti seluruh karyawan</p>
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('recapTable').classList.toggle('hidden'); this.querySelector('i').classList.toggle('fa-chevron-down'); this.querySelector('i').classList.toggle('fa-chevron-up')" class="px-3 py-1.5 text-sm text-gray-500 hover:text-indigo-600 transition">
                    <i class="fas fa-chevron-up"></i>
                </button>
            </div>
        </div>
        <div id="recapTable">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-700/50 text-left">
                            <th class="px-5 py-3 font-semibold text-gray-600 dark:text-gray-300">Karyawan</th>
                            <th class="px-4 py-3 font-semibold text-gray-600 dark:text-gray-300 text-center">Role</th>
                            <th class="px-4 py-3 font-semibold text-gray-600 dark:text-gray-300 text-center">Kuota</th>
                            <th class="px-4 py-3 font-semibold text-green-600 dark:text-green-400 text-center">Disetujui</th>
                            <th class="px-4 py-3 font-semibold text-amber-600 dark:text-amber-400 text-center">Proses</th>
                            <th class="px-4 py-3 font-semibold text-gray-600 dark:text-gray-300 text-center">Sisa</th>
                            <th class="px-4 py-3 font-semibold text-gray-600 dark:text-gray-300 text-center">Bar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        <?php 
                        $roleLabels = [
                            'administrator' => 'Admin', 'technician_manager' => 'Manager', 
                            'technician' => 'Teknisi', 'sales' => 'Sales', 'internship' => 'Magang',
                            'driver' => 'Driver', 'daily' => 'Harian', 'direktur' => 'Direktur'
                        ];
                        foreach ($recapData as $emp): 
                            $used = $emp['approved_days'];
                            $pending = $emp['pending_days'];
                            $remaining = max(0, 10 - $used - $pending);
                            $usedPct = min(100, ($used / 10) * 100);
                            $pendingPct = min(100 - $usedPct, ($pending / 10) * 100);
                            $barColor = ($remaining <= 2) ? 'bg-red-500' : (($remaining <= 5) ? 'bg-amber-500' : 'bg-green-500');
                        ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-5 py-3">
                                <div class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($emp['full_name'] ?: $emp['username']) ?></div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-xs text-gray-500 dark:text-gray-400"><?= $roleLabels[$emp['role']] ?? ucfirst($emp['role']) ?></span>
                            </td>
                            <td class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">10</td>
                            <td class="px-4 py-3 text-center">
                                <span class="<?= $used > 0 ? 'font-semibold text-green-600 dark:text-green-400' : 'text-gray-400' ?>"><?= $used ?></span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="<?= $pending > 0 ? 'font-semibold text-amber-600 dark:text-amber-400' : 'text-gray-400' ?>"><?= $pending ?></span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="font-bold <?= $remaining <= 2 ? 'text-red-600 dark:text-red-400' : ($remaining <= 5 ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400') ?>"><?= $remaining ?></span>
                            </td>
                            <td class="px-4 py-3">
                                <div style="width:96px;height:10px;border-radius:9999px;overflow:hidden;display:flex;background:#e5e7eb" class="dark:bg-slate-600">
                                    <?php if ($used > 0): ?><div style="width:<?= $usedPct ?>%;height:100%;background:<?= $remaining <= 2 ? '#ef4444' : ($remaining <= 5 ? '#f59e0b' : '#22c55e') ?>"></div><?php endif; ?>
                                    <?php if ($pending > 0): ?><div style="width:<?= $pendingPct ?>%;height:100%;background:#fcd34d"></div><?php endif; ?>
                                </div>
                                <div class="text-[10px] text-gray-400 mt-0.5 text-center"><?= $used + $pending ?>/10</div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 bg-slate-50 dark:bg-slate-700/30 border-t border-slate-100 dark:border-slate-700 flex flex-wrap gap-4 text-xs text-gray-500 dark:text-gray-400">
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-green-500 inline-block"></span> Disetujui</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-amber-300 inline-block"></span> Dalam proses</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-gray-200 dark:bg-slate-600 inline-block"></span> Tersisa</span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($pendingRequests) && in_array($currentRole, ['administrator', 'technician_manager', 'direktur'])): ?>
    
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="p-5 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-orange-100 dark:bg-orange-900/40 flex items-center justify-center">
                    <i class="fas fa-bell text-orange-500"></i>
                </div>
                <h2 class="font-semibold text-gray-900 dark:text-white">Menunggu Persetujuan</h2>
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400"><?= count($pendingRequests) ?></span>
            </div>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-slate-700">
            <?php foreach ($pendingRequests as $r): ?>
            <div class="p-5 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition" id="cuti-row-<?= $r['id'] ?>">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($r['user_name']) ?></span>
                            <?php
                            $badge = match($r['status']) {
                                'pending' => '<span class="px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">Pending</span>',
                                'manager_approved' => '<span class="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">ACC Manager</span>',
                                'admin_approved' => '<span class="px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">ACC Admin</span>',
                                default => ''
                            };
                            echo $badge;
                            ?>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-300 mb-1">
                            <i class="fas fa-calendar-day mr-1 text-gray-400"></i>
                            <?= date('d M Y', strtotime($r['start_date'])) ?> — <?= date('d M Y', strtotime($r['end_date'])) ?>
                            <span class="text-xs text-gray-400 ml-1">(<?= $r['total_days'] ?> hari kerja)</span>
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400"><i class="fas fa-comment-alt mr-1"></i><?= htmlspecialchars($r['reason']) ?></p>
                        <?php if (!empty($r['proof_path'])): ?>
                        <a href="<?= htmlspecialchars($r['proof_path']) ?>" target="_blank" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 mt-1 hover:underline"><i class="fas fa-paperclip"></i> Lihat Bukti</a>
                        <?php endif; ?>
                        <?php if (!empty($r['manager_name'])): ?>
                        <p class="text-xs text-green-600 dark:text-green-400 mt-1"><i class="fas fa-check mr-1"></i>Manager: <?= htmlspecialchars($r['manager_name']) ?></p>
                        <?php endif; ?>
                        <?php if ($r['status'] === 'admin_approved' && !empty($r['admin_name'])): ?>
                        <p class="text-xs text-purple-600 dark:text-purple-400 mt-1"><i class="fas fa-check mr-1"></i>Admin: <?= htmlspecialchars($r['admin_name']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="flex gap-2 flex-shrink-0">
                        <?php if ($currentRole === 'technician_manager' && $r['status'] === 'pending'): ?>
                        <button onclick="approveCuti(<?= $r['id'] ?>, 'manager_approve_cuti')" class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition"><i class="fas fa-check mr-1"></i>Approve</button>
                        <button onclick="rejectCuti(<?= $r['id'] ?>)" class="px-3 py-1.5 bg-red-500 text-white rounded-lg text-sm font-medium hover:bg-red-600 transition"><i class="fas fa-times mr-1"></i>Tolak</button>
                        <?php elseif ($currentRole === 'administrator' && $r['status'] === 'manager_approved'): ?>
                        <button onclick="approveCuti(<?= $r['id'] ?>, 'admin_approve_cuti')" class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition"><i class="fas fa-check mr-1"></i>Approve</button>
                        <button onclick="rejectCuti(<?= $r['id'] ?>)" class="px-3 py-1.5 bg-red-500 text-white rounded-lg text-sm font-medium hover:bg-red-600 transition"><i class="fas fa-times mr-1"></i>Tolak</button>
                        <?php elseif ($currentRole === 'administrator' && $r['status'] === 'pending'): ?>
                        <span class="text-xs text-gray-400 italic">Menunggu Manager</span>
                        <button onclick="rejectCuti(<?= $r['id'] ?>)" class="px-3 py-1.5 bg-red-500 text-white rounded-lg text-sm font-medium hover:bg-red-600 transition"><i class="fas fa-times mr-1"></i>Tolak</button>
                        <?php elseif ($currentRole === 'direktur' && $r['status'] === 'admin_approved'): ?>
                        <button onclick="approveCuti(<?= $r['id'] ?>, 'director_approve_cuti')" class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition"><i class="fas fa-check mr-1"></i>Approve Final</button>
                        <button onclick="rejectCuti(<?= $r['id'] ?>)" class="px-3 py-1.5 bg-red-500 text-white rounded-lg text-sm font-medium hover:bg-red-600 transition"><i class="fas fa-times mr-1"></i>Tolak</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="p-5 border-b border-slate-200 dark:border-slate-700">
            <h2 class="font-semibold text-gray-900 dark:text-white">
                <?= in_array($currentRole, ['administrator','direktur']) ? 'Semua Pengajuan Cuti ' . $currentYear : 'Riwayat Pengajuan Saya' ?>
            </h2>
        </div>
        <?php
        $displayList = (in_array($currentRole, ['administrator','direktur']) && !empty($allRequests)) ? $allRequests : $myRequests;
        ?>
        <?php if (empty($displayList)): ?>
        <div class="p-8 text-center text-gray-400">
            <i class="fas fa-calendar-times text-3xl mb-3 block"></i>
            <p>Belum ada pengajuan cuti</p>
        </div>
        <?php else: ?>
        <div class="divide-y divide-slate-100 dark:divide-slate-700">
            <?php foreach ($displayList as $r): ?>
            <div class="p-4 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <?php if (in_array($currentRole, ['administrator','direktur'])): ?>
                        <span class="font-medium text-gray-900 dark:text-white text-sm"><?= htmlspecialchars($r['user_name']) ?></span>
                        <span class="text-gray-300 mx-1">·</span>
                        <?php endif; ?>
                        <span class="text-sm text-gray-600 dark:text-gray-300">
                            <?= date('d M Y', strtotime($r['start_date'])) ?> — <?= date('d M Y', strtotime($r['end_date'])) ?>
                            <span class="text-xs text-gray-400">(<?= $r['total_days'] ?> hari)</span>
                        </span>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5 truncate"><?= htmlspecialchars($r['reason']) ?></p>
                        <?php if ($r['status'] === 'rejected' && !empty($r['reject_reason'])): ?>
                        <p class="text-xs text-red-500 mt-0.5"><i class="fas fa-info-circle mr-1"></i>Alasan ditolak: <?= htmlspecialchars($r['reject_reason']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="flex-shrink-0">
                        <?php
                        echo match($r['status']) {
                            'pending' => '<span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400"><i class="fas fa-clock mr-1"></i>Pending</span>',
                            'manager_approved' => '<span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400"><i class="fas fa-user-check mr-1"></i>ACC Manager</span>',
                            'admin_approved' => '<span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400"><i class="fas fa-user-shield mr-1"></i>ACC Admin</span>',
                            'approved' => '<span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400"><i class="fas fa-check-circle mr-1"></i>Disetujui</span>',
                            'rejected' => '<span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400"><i class="fas fa-times-circle mr-1"></i>Ditolak</span>',
                            default => ''
                        };
                        ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>


<div id="modalCuti" class="hidden fixed inset-0 z-[70] bg-black/60 backdrop-blur-sm items-center justify-center p-3 sm:p-4 overflow-y-auto" style="display:none">
  <div class="bg-white dark:bg-gray-900 rounded-2xl w-full mx-auto max-h-[95vh] overflow-y-auto shadow-2xl border border-gray-100 dark:border-gray-800" style="max-width:640px">
    
    <div class="sticky top-0 z-10 px-5 py-4 rounded-t-2xl" style="background:linear-gradient(to right,#4f46e5,#6366f1,#7c3aed)">
      <div class="flex justify-between items-center">
        <h3 class="font-bold text-lg text-white flex items-center gap-2">
          <span class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center"><i class="fas fa-calendar-plus text-sm"></i></span>
          Ajukan Cuti
        </h3>
        <button onclick="document.getElementById('modalCuti').style.display='none'" class="w-8 h-8 rounded-lg bg-white/20 hover:bg-white/30 flex items-center justify-center text-white transition">&times;</button>
      </div>
      
      <div class="mt-3 flex items-center justify-between bg-white/15 rounded-xl px-3 py-2">
        <span class="text-white/90 text-sm">Sisa kuota: <strong id="cutiQuotaRemain" class="text-white"><?= $quotaRemaining ?></strong> / 10 hari</span>
        <span id="cutiDaysBadge" class="hidden px-2.5 py-1 rounded-full text-xs font-bold bg-white text-indigo-600 shadow-sm"></span>
      </div>
    </div>

    <form id="formCuti" class="p-5 space-y-5" enctype="multipart/form-data">
      <input type="hidden" name="start_date" id="cutiStartDate">
      <input type="hidden" name="end_date" id="cutiEndDate">

      
      <div>
        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Pilih Tanggal Cuti <span class="text-red-500">*</span></label>
        <div class="border border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden shadow-sm bg-white dark:bg-gray-800">
          
          <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-800/80 border-b border-gray-100 dark:border-gray-700">
            <button type="button" id="calPrev" class="w-9 h-9 rounded-xl bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:border-indigo-300 flex items-center justify-center transition-all shadow-sm"><i class="fas fa-chevron-left text-xs text-gray-500 dark:text-gray-400"></i></button>
            <span id="calTitle" class="font-bold text-base text-gray-800 dark:text-white tracking-wide"></span>
            <button type="button" id="calNext" class="w-9 h-9 rounded-xl bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:border-indigo-300 flex items-center justify-center transition-all shadow-sm"><i class="fas fa-chevron-right text-xs text-gray-500 dark:text-gray-400"></i></button>
          </div>
          
          <div class="border-b border-gray-100 dark:border-gray-700" style="display:grid;grid-template-columns:repeat(7,1fr)">
            <div class="text-center py-2.5 text-[11px] sm:text-xs font-bold text-red-400 uppercase tracking-wider">Min</div>
            <div class="text-center py-2.5 text-[11px] sm:text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Sen</div>
            <div class="text-center py-2.5 text-[11px] sm:text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Sel</div>
            <div class="text-center py-2.5 text-[11px] sm:text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Rab</div>
            <div class="text-center py-2.5 text-[11px] sm:text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Kam</div>
            <div class="text-center py-2.5 text-[11px] sm:text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Jum</div>
            <div class="text-center py-2.5 text-[11px] sm:text-xs font-bold text-red-400 uppercase tracking-wider">Sab</div>
          </div>
          
          <div id="calGrid" class="gap-1 p-2 sm:p-3 bg-white dark:bg-gray-850" style="display:grid;grid-template-columns:repeat(7,1fr);min-height:220px"></div>
        </div>
        
        <div id="calSummary" class="mt-3 hidden">
          <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800/30 rounded-xl px-4 py-2.5 text-sm text-center">
            <span id="calSummaryText" class="text-indigo-700 dark:text-indigo-300"></span>
          </div>
        </div>
        <p class="text-[11px] text-gray-400 mt-2 text-center leading-relaxed">Klik tanggal mulai, lalu klik tanggal selesai. Sabtu & Minggu tidak dihitung.</p>
      </div>

      
      <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-3">
        <label class="flex items-start gap-3 cursor-pointer">
          <input type="checkbox" name="is_retroactive" id="isRetroactive" value="1" class="mt-0.5 w-4 h-4 text-amber-600 border-amber-300 rounded focus:ring-amber-500">
          <div class="flex-1">
            <span class="text-sm font-semibold text-amber-800 dark:text-amber-300">Cuti Susulan</span>
            <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">Centang jika lupa mengajukan cuti sebelumnya (maksimal 30 hari ke belakang). Tetap memerlukan approval admin.</p>
          </div>
        </label>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Alasan Cuti <span class="text-red-500">*</span></label>
        <textarea name="reason" required rows="3" placeholder="Jelaskan alasan pengajuan cuti..." class="w-full border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white p-3 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"></textarea>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Bukti / Lampiran <span class="text-xs text-gray-400 font-normal">(opsional)</span></label>
        <input type="file" name="proof" accept="image/*,.pdf" class="w-full text-sm text-gray-500 dark:text-gray-400 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-600 dark:file:bg-indigo-900/30 dark:file:text-indigo-300 hover:file:bg-indigo-100 file:transition file:cursor-pointer">
      </div>
      <div class="flex justify-end gap-2 pt-1 border-t border-gray-100 dark:border-gray-800">
        <button type="button" onclick="document.getElementById('modalCuti').style.display='none'" class="px-5 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-white rounded-xl text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition">Batal</button>
        <button type="submit" id="btnSubmitCuti" disabled class="px-5 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-semibold hover:bg-indigo-700 transition shadow-sm disabled:opacity-40 disabled:cursor-not-allowed"><i class="fas fa-paper-plane mr-1.5"></i>Ajukan Cuti</button>
      </div>
    </form>
  </div>
</div>


<div id="modalReject" style="display:none" class="fixed inset-0 z-[80] bg-black/50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-md p-6">
    <h3 class="font-semibold text-gray-900 dark:text-white mb-3">Tolak Pengajuan Cuti</h3>
    <form id="formReject">
      <input type="hidden" id="rejectCutiId">
      <textarea id="rejectReasonInput" rows="3" placeholder="Alasan penolakan (opsional)" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2.5 rounded-lg text-sm mb-3"></textarea>
      <div class="flex justify-end gap-2">
        <button type="button" onclick="document.getElementById('modalReject').style.display='none'" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-white rounded-lg text-sm">Batal</button>
        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">Tolak</button>
      </div>
    </form>
  </div>
</div>

<script>
const LEAVE_API = <?= json_encode(rtrim(BASE_URL, '/') . '/app/pages/leave-request.php') ?>;
const CUTI_YEAR = <?= $currentYear ?>;
const CUTI_REMAINING = <?= $quotaRemaining ?>;
const MONTHS_ID = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

const cal = {
    month: new Date().getMonth(),  // 0-11
    year: CUTI_YEAR,
    startDate: null,
    endDate: null,
    selecting: 'start'  // 'start' or 'end'
};

function pad(n) { return n < 10 ? '0' + n : '' + n; }
function fmtDate(y,m,d) { return y + '-' + pad(m+1) + '-' + pad(d); }
function parseDate(s) { const p = s.split('-'); return new Date(+p[0], +p[1]-1, +p[2]); }
function isWeekend(y,m,d) { const dow = new Date(y,m,d).getDay(); return dow === 0 || dow === 6; }
function today() { const t = new Date(); return fmtDate(t.getFullYear(), t.getMonth(), t.getDate()); }

function countBusinessDays(start, end) {
    if (!start || !end) return 0;
    let count = 0;
    const d = parseDate(start);
    const e = parseDate(end);
    while (d <= e) {
        if (d.getDay() !== 0 && d.getDay() !== 6) count++;
        d.setDate(d.getDate() + 1);
    }
    return count;
}

function renderCalendar() {
    const grid = document.getElementById('calGrid');
    const title = document.getElementById('calTitle');
    if (!grid || !title) return;

    title.textContent = MONTHS_ID[cal.month + 1] + ' ' + cal.year;

    const firstDay = new Date(cal.year, cal.month, 1).getDay(); // 0=Sun
    const daysInMonth = new Date(cal.year, cal.month + 1, 0).getDate();
    const todayStr = today();
    let html = '';

    for (let i = 0; i < firstDay; i++) {
        html += '<div class="p-1"></div>';
    }

    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = fmtDate(cal.year, cal.month, d);
        const weekend = isWeekend(cal.year, cal.month, d);
        const isPast = dateStr < todayStr;
        const isRetroactive = document.getElementById('isRetroactive')?.checked || false;
        const maxPastDate = new Date(); maxPastDate.setDate(maxPastDate.getDate() - 30);
        const maxPastStr = fmtDate(maxPastDate.getFullYear(), maxPastDate.getMonth(), maxPastDate.getDate());
        const isDisabled = (isPast && !isRetroactive) || (isRetroactive && dateStr < maxPastStr) || (isRetroactive && dateStr >= todayStr) || (cal.year !== CUTI_YEAR);
        const isStart = cal.startDate === dateStr;
        const isEnd = cal.endDate === dateStr;
        const isInRange = cal.startDate && cal.endDate && dateStr > cal.startDate && dateStr < cal.endDate;
        const isToday = dateStr === todayStr;

        let cls = 'cal-cell relative w-full aspect-square flex items-center justify-center text-xs sm:text-sm rounded-xl transition-all duration-150 select-none ';

        if (isDisabled) {
            cls += 'text-gray-300 dark:text-gray-600 cursor-not-allowed opacity-40 ';
        } else if (isStart) {
            cls += 'bg-indigo-600 text-white font-bold shadow-lg shadow-indigo-200 dark:shadow-indigo-900/50 ring-2 ring-indigo-400/50 scale-105 cursor-pointer ';
        } else if (isEnd) {
            cls += 'bg-purple-600 text-white font-bold shadow-lg shadow-purple-200 dark:shadow-purple-900/50 ring-2 ring-purple-400/50 scale-105 cursor-pointer ';
        } else if (isInRange && !weekend) {
            cls += 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold cursor-pointer hover:bg-indigo-200 dark:hover:bg-indigo-800/40 ';
        } else if (isInRange && weekend) {
            cls += 'bg-gray-100 dark:bg-gray-800 text-gray-300 dark:text-gray-600 line-through cursor-pointer ';
        } else if (weekend) {
            cls += 'text-red-300 dark:text-red-700 cursor-pointer hover:bg-red-50 dark:hover:bg-red-900/10 ';
        } else {
            cls += 'text-gray-700 dark:text-gray-200 cursor-pointer hover:bg-indigo-50 dark:hover:bg-indigo-900/20 hover:text-indigo-600 dark:hover:text-indigo-400 ';
        }

        if (isToday && !isStart && !isEnd) {
            cls += 'ring-2 ring-indigo-400 dark:ring-indigo-500 ';
        }

        html += `<div class="${cls}" data-date="${dateStr}" ${isDisabled ? '' : `onclick="pickDate('${dateStr}')"`}>
            <span class="relative z-10">${d}</span>
            ${isToday ? '<span class="absolute bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-indigo-500"></span>' : ''}
        </div>`;
    }

    grid.innerHTML = html;

    const prevBtn = document.getElementById('calPrev');
    const nextBtn = document.getElementById('calNext');
    prevBtn.disabled = (cal.month <= 0);
    prevBtn.style.opacity = (cal.month <= 0) ? '0.3' : '1';
    nextBtn.disabled = (cal.month >= 11);
    nextBtn.style.opacity = (cal.month >= 11) ? '0.3' : '1';
}

function pickDate(dateStr) {
    if (cal.selecting === 'start') {
        cal.startDate = dateStr;
        cal.endDate = null;
        cal.selecting = 'end';
    } else {
        if (dateStr < cal.startDate) {
            cal.startDate = dateStr;
            cal.endDate = null;
            cal.selecting = 'end';
        } else {
            const bdays = countBusinessDays(cal.startDate, dateStr);
            if (bdays > CUTI_REMAINING) {
                if (typeof showToast === 'function') showToast('Melebihi sisa kuota cuti (' + CUTI_REMAINING + ' hari)', 'error');
                else alert('Melebihi sisa kuota cuti (' + CUTI_REMAINING + ' hari)');
                return;
            }
            cal.endDate = dateStr;
            cal.selecting = 'start';
        }
    }
    updateCalendarUI();
}

function updateCalendarUI() {
    renderCalendar();

    const badge = document.getElementById('cutiDaysBadge');
    const summary = document.getElementById('calSummary');
    const summaryText = document.getElementById('calSummaryText');
    const submitBtn = document.getElementById('btnSubmitCuti');
    const startInput = document.getElementById('cutiStartDate');
    const endInput = document.getElementById('cutiEndDate');

    if (cal.startDate && cal.endDate) {
        const bdays = countBusinessDays(cal.startDate, cal.endDate);
        const sD = parseDate(cal.startDate);
        const eD = parseDate(cal.endDate);
        const fmt = d => d.getDate() + ' ' + MONTHS_ID[d.getMonth()+1] + ' ' + d.getFullYear();

        badge.textContent = bdays + ' hari kerja';
        badge.classList.remove('hidden');
        badge.className = 'px-3 py-1 rounded-full text-xs font-bold bg-white text-indigo-600 shadow-sm';
        summary.classList.remove('hidden');
        summaryText.innerHTML = '<i class="fas fa-calendar-check text-green-500 mr-1"></i><strong>' + fmt(sD) + '</strong> <i class="fas fa-arrow-right text-indigo-400 mx-1"></i> <strong>' + fmt(eD) + '</strong>';

        startInput.value = cal.startDate;
        endInput.value = cal.endDate;
        submitBtn.disabled = false;
    } else if (cal.startDate) {
        const sD = parseDate(cal.startDate);
        const fmt = d => d.getDate() + ' ' + MONTHS_ID[d.getMonth()+1];
        badge.textContent = 'Pilih tanggal akhir';
        badge.classList.remove('hidden');
        badge.className = 'px-3 py-1 rounded-full text-xs font-bold bg-amber-400 text-amber-900 shadow-sm animate-pulse';
        summary.classList.remove('hidden');
        summaryText.innerHTML = '<i class="fas fa-hand-pointer text-amber-500 mr-1"></i>Mulai: <strong>' + fmt(sD) + '</strong> — klik tanggal selesai';

        startInput.value = '';
        endInput.value = '';
        submitBtn.disabled = true;
    } else {
        badge.classList.add('hidden');
        summary.classList.add('hidden');
        startInput.value = '';
        endInput.value = '';
        submitBtn.disabled = true;
    }

    if (cal.startDate && cal.endDate) {
        badge.className = 'px-3 py-1 rounded-full text-xs font-bold bg-white text-indigo-600 shadow-sm';
    } else if (cal.startDate) {
        badge.className = 'px-3 py-1 rounded-full text-xs font-bold bg-amber-400 text-amber-900 shadow-sm animate-pulse';
    }
}

document.getElementById('calPrev')?.addEventListener('click', () => {
    if (cal.month > 0) { cal.month--; renderCalendar(); }
});
document.getElementById('calNext')?.addEventListener('click', () => {
    if (cal.month < 11) { cal.month++; renderCalendar(); }
});

document.getElementById('isRetroactive')?.addEventListener('change', () => {
    cal.startDate = null;
    cal.endDate = null;
    cal.selecting = 'start';
    renderCalendar();
    updateCalendarUI();
});

function _origOpenCuti() {
    document.getElementById('modalCuti').style.display = 'flex';
    cal.month = new Date().getMonth();
    cal.startDate = null;
    cal.endDate = null;
    cal.selecting = 'start';
    updateCalendarUI();
};

renderCalendar();

document.getElementById('formCuti')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const startVal = document.getElementById('cutiStartDate').value;
    const endVal = document.getElementById('cutiEndDate').value;
    if (!startVal || !endVal) {
        if (typeof showToast === 'function') showToast('Pilih tanggal cuti di kalender', 'error');
        else alert('Pilih tanggal cuti di kalender');
        return;
    }
    const btn = document.getElementById('btnSubmitCuti');
    btn.disabled = true; btn.textContent = 'Mengirim...';
    try {
        const fd = new FormData(this);
        fd.append('action', 'submit_cuti');
        const resp = await fetch(LEAVE_API, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await resp.json();
        if (data.success) {
            if (typeof showToast === 'function') showToast(data.message, 'success');
            else alert(data.message);
            document.getElementById('modalCuti').style.display = 'none';
            setTimeout(() => location.reload(), 800);
        } else {
            if (typeof showToast === 'function') showToast(data.message, 'error');
            else alert(data.message);
        }
    } catch(err) {
        alert('Error: ' + err.message);
    } finally {
        btn.disabled = false; btn.textContent = 'Ajukan Cuti';
    }
});

async function approveCuti(id, action) {
    if (!confirm('Setujui pengajuan cuti ini?')) return;
    try {
        const fd = new FormData();
        fd.append('action', action);
        fd.append('cuti_id', id);
        const resp = await fetch(LEAVE_API, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await resp.json();
        if (data.success) {
            if (typeof showToast === 'function') showToast(data.message, 'success');
            else alert(data.message);
            const row = document.getElementById('cuti-row-' + id);
            if (row) row.style.opacity = '0.4';
            setTimeout(() => location.reload(), 800);
        } else {
            if (typeof showToast === 'function') showToast(data.message, 'error');
            else alert(data.message);
        }
    } catch(err) { alert('Error: ' + err.message); }
}

function rejectCuti(id) {
    document.getElementById('rejectCutiId').value = id;
    document.getElementById('rejectReasonInput').value = '';
    document.getElementById('modalReject').style.display = 'flex';
}

document.getElementById('formReject')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const id = document.getElementById('rejectCutiId').value;
    const reason = document.getElementById('rejectReasonInput').value;
    try {
        const fd = new FormData();
        fd.append('action', 'reject_cuti');
        fd.append('cuti_id', id);
        fd.append('reject_reason', reason);
        const resp = await fetch(LEAVE_API, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await resp.json();
        if (data.success) {
            if (typeof showToast === 'function') showToast(data.message, 'success');
            document.getElementById('modalReject').style.display = 'none';
            setTimeout(() => location.reload(), 800);
        } else {
            if (typeof showToast === 'function') showToast(data.message, 'error');
            else alert(data.message);
        }
    } catch(err) { alert('Error: ' + err.message); }
});
</script>
