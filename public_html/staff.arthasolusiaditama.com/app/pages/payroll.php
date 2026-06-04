<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
@date_default_timezone_set('Asia/Jakarta');

$stmt = $pdo->prepare("SELECT id, full_name, username, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);


$stmtAccess = $pdo->prepare("SELECT 1 FROM payroll_admins WHERE user_id = ?");
$stmtAccess->execute([$_SESSION['user_id']]);
$hasAccess = (bool)$stmtAccess->fetchColumn();

if (!$hasAccess) {
    echo '<div class="flex items-center justify-center min-h-[60vh]"><div class="text-center">
        <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center"><i class="fas fa-lock text-3xl text-red-500"></i></div>
        <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-2">Akses Ditolak</h2>
        <p class="text-gray-500 dark:text-gray-400">Halaman ini hanya bisa diakses oleh pengelola payroll.</p>
    </div></div>';
    return;
}

$RATES = ['daily' => 150000, 'internship' => 50000];
$isOwner = ($currentUser['username'] === 'idhoo'); 


if (isset($_GET['ajax_count'])) {
    header('Content-Type: application/json');
    $uid = (int)($_GET['user_id'] ?? 0);
    $start = $_GET['start'] ?? '';
    $end = $_GET['end'] ?? '';
    if ($uid && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendances WHERE user_id = ? AND attendance_date BETWEEN ? AND ? AND status IN ('Hadir','Terlambat')");
        $stmt->execute([$uid, $start, $end]);
        echo json_encode(['count' => (int)$stmt->fetchColumn()]);
    } else {
        echo json_encode(['count' => 0]);
    }
    exit;
}


if (isset($_GET['ajax_detail'])) {
    header('Content-Type: application/json');
    $uid = (int)($_GET['user_id'] ?? 0);
    $start = $_GET['start'] ?? '';
    $end = $_GET['end'] ?? '';
    if ($uid && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        $stmt = $pdo->prepare("SELECT a.attendance_date, a.status, a.check_in_time, a.check_out_time, a.check_in_photo, a.check_out_photo FROM attendances a WHERE a.user_id = ? AND a.attendance_date BETWEEN ? AND ? AND a.status IN ('Hadir','Terlambat') ORDER BY a.attendance_date ASC");
        $stmt->execute([$uid, $start, $end]);
        echo json_encode(['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } else {
        echo json_encode(['rows' => []]);
    }
    exit;
}


if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']); exit;
    }

    if ($_POST['action'] === 'process_payment') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $periodStart = $_POST['period_start'] ?? '';
        $periodEnd = $_POST['period_end'] ?? '';
        if (!$userId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodEnd)) {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']); exit;
        }
        $stmt = $pdo->prepare("SELECT id, role, full_name FROM users WHERE id = ? AND role IN ('daily','internship') AND is_active = 1");
        $stmt->execute([$userId]);
        $worker = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$worker) { echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']); exit; }
        $rate = $RATES[$worker['role']] ?? 0;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendances WHERE user_id = ? AND attendance_date BETWEEN ? AND ? AND status IN ('Hadir','Terlambat')");
        $stmt->execute([$userId, $periodStart, $periodEnd]);
        $totalDays = (int)$stmt->fetchColumn();
        if ($totalDays === 0) { echo json_encode(['success' => false, 'message' => 'Tidak ada hari kerja di periode ini']); exit; }
        $stmt = $pdo->prepare("SELECT id FROM payroll_payments WHERE user_id = ? AND NOT (period_end < ? OR period_start > ?)");
        $stmt->execute([$userId, $periodStart, $periodEnd]);
        if ($stmt->fetch()) { echo json_encode(['success' => false, 'message' => 'Sudah ada pembayaran di periode ini']); exit; }
        $totalAmount = $totalDays * $rate;
        $notes = trim($_POST['notes'] ?? '');
        $stmt = $pdo->prepare("INSERT INTO payroll_payments (user_id, period_start, period_end, total_days, daily_rate, total_amount, notes, paid_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $periodStart, $periodEnd, $totalDays, $rate, $totalAmount, $notes ?: null, $_SESSION['user_id']]);
        echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil diproses']); exit;
    }

    if ($_POST['action'] === 'delete_payment') {
        $paymentId = (int)($_POST['payment_id'] ?? 0);
        if (!$paymentId) { echo json_encode(['success' => false, 'message' => 'ID tidak valid']); exit; }
        $stmt = $pdo->prepare("DELETE FROM payroll_payments WHERE id = ?");
        $stmt->execute([$paymentId]);
        echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil dihapus']); exit;
    }

    if ($_POST['action'] === 'add_admin' && $isOwner) {
        $adminId = (int)($_POST['admin_id'] ?? 0);
        if (!$adminId) { echo json_encode(['success' => false, 'message' => 'ID tidak valid']); exit; }
        $chk = $pdo->prepare("SELECT 1 FROM payroll_admins WHERE user_id = ?");
        $chk->execute([$adminId]);
        if ($chk->fetchColumn()) { echo json_encode(['success' => false, 'message' => 'Admin sudah terdaftar']); exit; }
        $stmt = $pdo->prepare("INSERT INTO payroll_admins (user_id, added_by) VALUES (?, ?)");
        $stmt->execute([$adminId, $_SESSION['user_id']]);
        echo json_encode(['success' => true, 'message' => 'Admin berhasil ditambahkan']); exit;
    }

    if ($_POST['action'] === 'remove_admin' && $isOwner) {
        $adminId = (int)($_POST['admin_id'] ?? 0);
        if ($adminId == $_SESSION['user_id']) { echo json_encode(['success' => false, 'message' => 'Tidak bisa menghapus diri sendiri']); exit; }
        $stmt = $pdo->prepare("DELETE FROM payroll_admins WHERE user_id = ?");
        $stmt->execute([$adminId]);
        echo json_encode(['success' => true, 'message' => 'Admin berhasil dihapus']); exit;
    }

    echo json_encode(['success' => false, 'message' => 'Action tidak dikenal']); exit;
}


if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $uid = (int)($_GET['user_id'] ?? 0);
    $start = $_GET['start'] ?? $currentMonthStart ?? date('Y-m-01');
    $end = $_GET['end'] ?? date('Y-m-d');

    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = date('Y-m-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) $end = date('Y-m-d');

    $whereUser = '';
    $params = [$start, $end];
    if ($uid) {
        $whereUser = 'AND a.user_id = ?';
        $params[] = $uid;
    }

    $stmt = $pdo->prepare("
        SELECT a.attendance_date, a.status, a.check_in_time, a.check_out_time, a.check_in_photo, a.check_out_photo,
               u.full_name, u.username, u.role
        FROM attendances a
        JOIN users u ON u.id = a.user_id
        WHERE u.role IN ('daily','internship') AND u.is_active = 1
        AND a.attendance_date BETWEEN ? AND ?
        AND a.status IN ('Hadir','Terlambat')
        $whereUser
        ORDER BY u.full_name, a.attendance_date
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $siteBase = get_base_url() . '/';

    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="Payroll_' . $start . '_' . $end . '.xls"');
    header('Cache-Control: max-age=0');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="utf-8"><style>td,th{border:1px solid #ccc;padding:6px 10px;font-family:Arial;font-size:11px;vertical-align:middle}th{background:#10b981;color:#fff;font-weight:bold}tr:nth-child(even){background:#f0fdf4}.rate{text-align:right}.center{text-align:center}img{max-width:120px;max-height:90px}</style></head>';
    echo '<body>';
    echo '<table>';
    echo '<tr><th colspan="8" style="font-size:16px;text-align:center;background:#065f46">LAPORAN ABSENSI & PAYROLL</th></tr>';
    echo '<tr><th colspan="8" style="text-align:center;background:#047857">Periode: ' . date('d M Y', strtotime($start)) . ' - ' . date('d M Y', strtotime($end)) . '</th></tr>';
    echo '<tr><th>Nama</th><th>Role</th><th>Tanggal</th><th>Status</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Foto Masuk</th><th>Foto Pulang</th></tr>';

    $summary = [];
    foreach ($rows as $r) {
        $key = $r['full_name'] . '|' . $r['role'];
        if (!isset($summary[$key])) $summary[$key] = ['name' => $r['full_name'], 'role' => $r['role'], 'days' => 0, 'rate' => $RATES[$r['role']] ?? 0];
        $summary[$key]['days']++;

        $ciPhoto = '';
        $coPhoto = '';
        if (!empty($r['check_in_photo'])) {
            $ciPhoto = '<img src="' . $siteBase . htmlspecialchars($r['check_in_photo']) . '">';
        }
        if (!empty($r['check_out_photo'])) {
            $coPhoto = '<img src="' . $siteBase . htmlspecialchars($r['check_out_photo']) . '">';
        }

        $ciTime = ($r['check_in_time'] && $r['check_in_time'] !== '0000-00-00 00:00:00') ? date('H:i', strtotime($r['check_in_time'])) : '-';
        $coTime = ($r['check_out_time'] && $r['check_out_time'] !== '0000-00-00 00:00:00') ? date('H:i', strtotime($r['check_out_time'])) : '-';

        echo '<tr>';
        echo '<td>' . htmlspecialchars($r['full_name']) . '</td>';
        echo '<td class="center">' . ucfirst($r['role']) . '</td>';
        echo '<td class="center">' . date('d M Y', strtotime($r['attendance_date'])) . '</td>';
        echo '<td class="center">' . htmlspecialchars($r['status']) . '</td>';
        echo '<td class="center">' . $ciTime . '</td>';
        echo '<td class="center">' . $coTime . '</td>';
        echo '<td class="center">' . $ciPhoto . '</td>';
        echo '<td class="center">' . $coPhoto . '</td>';
        echo '</tr>';
    }

    
    echo '<tr><td colspan="8" style="background:#fff;height:10px;border:none"></td></tr>';
    echo '<tr><th colspan="8" style="text-align:center;background:#065f46">RINGKASAN GAJI</th></tr>';
    echo '<tr><th>Nama</th><th>Role</th><th>Hari Kerja</th><th>Rate/Hari</th><th colspan="2">Total Gaji</th><th colspan="2"></th></tr>';
    $grandTotal = 0;
    foreach ($summary as $s) {
        $total = $s['days'] * $s['rate'];
        $grandTotal += $total;
        echo '<tr>';
        echo '<td>' . htmlspecialchars($s['name']) . '</td>';
        echo '<td class="center">' . ucfirst($s['role']) . '</td>';
        echo '<td class="center">' . $s['days'] . '</td>';
        echo '<td class="rate">Rp ' . number_format($s['rate'], 0, ',', '.') . '</td>';
        echo '<td class="rate" colspan="2">Rp ' . number_format($total, 0, ',', '.') . '</td>';
        echo '<td colspan="2"></td>';
        echo '</tr>';
    }
    echo '<tr><th colspan="3">GRAND TOTAL</th><th></th><th colspan="2" style="text-align:right;font-size:13px">Rp ' . number_format($grandTotal, 0, ',', '.') . '</th><th colspan="2"></th></tr>';
    echo '</table></body></html>';
    exit;
}


$workers = $pdo->query("SELECT u.id, u.full_name, u.username, u.role, u.avatar, u.gender FROM users u WHERE u.role IN ('daily','internship') AND u.is_active = 1 ORDER BY u.role, u.full_name")->fetchAll(PDO::FETCH_ASSOC);
$currentMonthStart = date('Y-m-01');
$currentMonthEnd = date('Y-m-t');
$today = date('Y-m-d');

$attendanceCounts = [];
$todayAttendance = [];
foreach ($workers as $w) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendances WHERE user_id = ? AND attendance_date BETWEEN ? AND ? AND status IN ('Hadir','Terlambat')");
    $stmt->execute([$w['id'], $currentMonthStart, $currentMonthEnd]);
    $attendanceCounts[$w['id']] = (int)$stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT status, check_in_time FROM attendances WHERE user_id = ? AND attendance_date = ? LIMIT 1");
    $stmt->execute([$w['id'], $today]);
    $todayAttendance[$w['id']] = $stmt->fetch(PDO::FETCH_ASSOC);
}

$paymentHistory = $pdo->query("SELECT pp.*, u.full_name, u.role as worker_role, pb.full_name as paid_by_name FROM payroll_payments pp JOIN users u ON u.id = pp.user_id JOIN users pb ON pb.id = pp.paid_by ORDER BY pp.paid_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM payroll_payments WHERE paid_at BETWEEN ? AND ?");
$stmt->execute([$currentMonthStart . ' 00:00:00', $currentMonthEnd . ' 23:59:59']);
$totalPaidThisMonth = (int)$stmt->fetchColumn();

$totalUnpaidDays = 0;
$workerData = [];
foreach ($workers as $w) {
    $rate = $RATES[$w['role']] ?? 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendances a WHERE a.user_id = ? AND a.attendance_date BETWEEN ? AND ? AND a.status IN ('Hadir','Terlambat') AND NOT EXISTS (SELECT 1 FROM payroll_payments pp WHERE pp.user_id = a.user_id AND a.attendance_date BETWEEN pp.period_start AND pp.period_end)");
    $stmt->execute([$w['id'], $currentMonthStart, $currentMonthEnd]);
    $unpaid = (int)$stmt->fetchColumn();
    $totalUnpaidDays += $unpaid;
    $stmtLast = $pdo->prepare("SELECT paid_at, total_amount, total_days FROM payroll_payments WHERE user_id = ? ORDER BY paid_at DESC LIMIT 1");
    $stmtLast->execute([$w['id']]);
    $workerData[$w['id']] = ['unpaid' => $unpaid, 'amount' => $unpaid * $rate, 'last' => $stmtLast->fetch(PDO::FETCH_ASSOC)];
}


$payrollAdmins = $pdo->query("SELECT pa.id, pa.user_id, pa.created_at, u.full_name, u.username, u.avatar, u.gender FROM payroll_admins pa JOIN users u ON u.id = pa.user_id ORDER BY pa.created_at")->fetchAll(PDO::FETCH_ASSOC);
$allAdmins = $pdo->query("SELECT id, full_name, username FROM users WHERE role IN ('administrator','direktur') AND is_active = 1 ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

$csrf = $_SESSION['csrf'] ?? '';
$todayPresent = count(array_filter($todayAttendance, fn($a) => $a && in_array($a['status'], ['Hadir','Terlambat'])));
$dailyCount = count(array_filter($workers, fn($w) => $w['role'] === 'daily'));
$internCount = count(array_filter($workers, fn($w) => $w['role'] === 'internship'));
?>

<div class="max-w-7xl mx-auto">

    
    <div class="mb-6 flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg" style="background:linear-gradient(135deg,#10b981,#0d9488)">
                <i class="fas fa-wallet text-white text-lg"></i>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight">Payroll</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Kelola gaji harian untuk Daily & Internship</p>
            </div>
        </div>
        <button onclick="prExportExcel()" class="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl transition shadow-sm">
            <i class="fas fa-file-excel"></i> Export Excel
        </button>
    </div>

    
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 border border-gray-100 dark:border-gray-700 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <div class="w-9 h-9 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center"><i class="fas fa-users text-blue-600 dark:text-blue-400 text-sm"></i></div>
                <span class="text-[10px] font-medium text-gray-400 uppercase tracking-wider">Pekerja</span>
            </div>
            <p class="text-xl font-extrabold text-gray-900 dark:text-white"><?= count($workers) ?></p>
            <p class="text-[11px] text-gray-500 mt-0.5"><?= $dailyCount ?> Daily · <?= $internCount ?> Internship</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 border border-gray-100 dark:border-gray-700 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <div class="w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center"><i class="fas fa-calendar-check text-emerald-600 dark:text-emerald-400 text-sm"></i></div>
                <span class="text-[10px] font-medium text-gray-400 uppercase tracking-wider">Hadir Hari Ini</span>
            </div>
            <p class="text-xl font-extrabold text-gray-900 dark:text-white"><?= $todayPresent ?> <span class="text-xs font-normal text-gray-400">/ <?= count($workers) ?></span></p>
            <p class="text-[11px] text-gray-500 mt-0.5"><?= date('d M Y') ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 border border-gray-100 dark:border-gray-700 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <div class="w-9 h-9 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center"><i class="fas fa-clock text-amber-600 dark:text-amber-400 text-sm"></i></div>
                <span class="text-[10px] font-medium text-gray-400 uppercase tracking-wider">Belum Dibayar</span>
            </div>
            <p class="text-xl font-extrabold text-gray-900 dark:text-white"><?= $totalUnpaidDays ?> <span class="text-xs font-normal text-gray-400">hari</span></p>
            <p class="text-[11px] text-gray-500 mt-0.5"><?= count(array_filter($workerData, fn($d) => $d['unpaid'] > 0)) ?> pekerja · Bulan <?= date('M Y') ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 border border-gray-100 dark:border-gray-700 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <div class="w-9 h-9 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center"><i class="fas fa-money-bill-wave text-purple-600 dark:text-purple-400 text-sm"></i></div>
                <span class="text-[10px] font-medium text-gray-400 uppercase tracking-wider">Total Dibayar</span>
            </div>
            <p class="text-xl font-extrabold text-gray-900 dark:text-white">Rp <?= number_format($totalPaidThisMonth, 0, ',', '.') ?></p>
            <p class="text-[11px] text-gray-500 mt-0.5">Bulan <?= date('F Y') ?></p>
        </div>
    </div>

    
    <div class="flex gap-1 mb-6 bg-gray-100 dark:bg-gray-800 rounded-xl p-1 overflow-x-auto">
        <button onclick="payrollTab('workers')" id="tabBtnW" class="shrink-0 px-4 py-2 rounded-lg text-sm font-semibold transition-all bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-white">
            <i class="fas fa-users mr-1"></i> Pekerja
        </button>
        <button onclick="payrollTab('history')" id="tabBtnH" class="shrink-0 px-4 py-2 rounded-lg text-sm font-semibold transition-all text-gray-500 dark:text-gray-400">
            <i class="fas fa-history mr-1"></i> Riwayat
        </button>
        <button onclick="payrollTab('attendance')" id="tabBtnA" class="shrink-0 px-4 py-2 rounded-lg text-sm font-semibold transition-all text-gray-500 dark:text-gray-400">
            <i class="fas fa-camera mr-1"></i> Absensi & Foto
        </button>
        <?php if ($isOwner): ?>
        <button onclick="payrollTab('settings')" id="tabBtnS" class="shrink-0 px-4 py-2 rounded-lg text-sm font-semibold transition-all text-gray-500 dark:text-gray-400">
            <i class="fas fa-cog mr-1"></i> Akses
        </button>
        <?php endif; ?>
    </div>

    
    <div id="panelW">
        <?php if (count($workers) > 0): ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <?php foreach ($workers as $w):
                $rate = $RATES[$w['role']] ?? 0;
                $days = $attendanceCounts[$w['id']] ?? 0;
                $att = $todayAttendance[$w['id']] ?? null;
                $present = $att && in_array($att['status'], ['Hadir', 'Terlambat']);
                $wd = $workerData[$w['id']];
                $avatarGrad = ($w['gender'] === 'female') ? 'linear-gradient(135deg,#f472b6,#f43f5e)' : 'linear-gradient(135deg,#60a5fa,#6366f1)';
                $rc = $w['role'] === 'daily' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300';
                $rl = $w['role'] === 'daily' ? 'Daily' : 'Internship';
                $barColor = $w['role'] === 'daily' ? 'bg-blue-400' : 'bg-violet-400';
            ?>
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                <div class="h-1 <?= $barColor ?>"></div>
                <div class="p-4 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="relative shrink-0">
                            <?php if (!empty($w['avatar'])): ?>
                            <img src="<?= htmlspecialchars($w['avatar']) ?>" class="w-10 h-10 rounded-xl object-cover" alt="">
                            <?php else: ?>
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white font-bold text-sm" style="background:<?= $avatarGrad ?>">
                                <?= strtoupper(mb_substr($w['full_name'], 0, 1)) ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($present): ?>
                            <div class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-emerald-500 rounded-full border-2 border-white dark:border-gray-800"></div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-bold text-gray-900 dark:text-white text-sm truncate"><?= htmlspecialchars($w['full_name']) ?></h3>
                                <span class="shrink-0 px-1.5 py-0.5 rounded text-[10px] font-bold <?= $rc ?>"><?= $rl ?></span>
                            </div>
                            <p class="text-[11px] text-gray-400 mt-0.5">@<?= htmlspecialchars($w['username']) ?> · Rp <?= number_format($rate, 0, ',', '.') ?>/hari</p>
                        </div>
                    </div>
                </div>
                <div class="px-4 pb-3">
                    <div class="grid grid-cols-3 gap-1.5">
                        <div class="text-center p-2 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                            <p class="text-base font-extrabold text-gray-900 dark:text-white"><?= $days ?></p>
                            <p class="text-[10px] text-gray-400 font-medium">Hari Masuk</p>
                        </div>
                        <div class="text-center p-2 rounded-lg <?= $wd['unpaid'] > 0 ? 'bg-amber-50 dark:bg-amber-900/20' : 'bg-gray-50 dark:bg-gray-700/50' ?>">
                            <p class="text-base font-extrabold <?= $wd['unpaid'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white' ?>"><?= $wd['unpaid'] ?></p>
                            <p class="text-[10px] text-gray-400 font-medium">Belum Bayar</p>
                        </div>
                        <div class="text-center p-2 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                            <p class="text-xs font-extrabold text-gray-900 dark:text-white"><?= $wd['amount'] > 0 ? 'Rp ' . number_format($wd['amount'], 0, ',', '.') : '-' ?></p>
                            <p class="text-[10px] text-gray-400 font-medium">Total</p>
                        </div>
                    </div>
                </div>
                <div class="px-4 pb-2">
                    <?php if ($present): ?>
                    <div class="flex items-center gap-2 text-[11px]">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-emerald-600 dark:text-emerald-400 font-medium">Hadir hari ini</span>
                        <span class="text-gray-400 ml-auto"><?= date('H:i', strtotime($att['check_in_time'])) ?></span>
                    </div>
                    <?php else: ?>
                    <div class="flex items-center gap-2 text-[11px] text-gray-400"><span class="w-1.5 h-1.5 rounded-full bg-gray-300 dark:bg-gray-600"></span> Belum hadir hari ini</div>
                    <?php endif; ?>
                </div>
                <?php if ($wd['last']): ?>
                <div class="px-4 pb-2">
                    <div class="text-[10px] text-gray-400 bg-gray-50 dark:bg-gray-700/30 rounded-lg px-2.5 py-1.5">
                        <i class="fas fa-receipt mr-1"></i> Bayar terakhir: <?= date('d M Y', strtotime($wd['last']['paid_at'])) ?> (<?= $wd['last']['total_days'] ?> hari · Rp <?= number_format($wd['last']['total_amount'], 0, ',', '.') ?>)
                    </div>
                </div>
                <?php endif; ?>
                <div class="px-4 pb-4">
                    <?php if ($wd['unpaid'] > 0): ?>
                    <button type="button" onclick="prOpenPay(<?= $w['id'] ?>,'<?= htmlspecialchars($w['full_name'], ENT_QUOTES) ?>','<?= $w['role'] ?>',<?= $rate ?>,<?= $wd['unpaid'] ?>,<?= $wd['amount'] ?>)"
                            class="w-full py-2 text-white text-xs font-bold rounded-xl transition-all shadow-sm hover:shadow-md flex items-center justify-center gap-2" style="background:linear-gradient(90deg,#10b981,#0d9488)">
                        <i class="fas fa-money-bill-wave"></i> Bayar Gaji (<?= $wd['unpaid'] ?> hari)
                    </button>
                    <?php else: ?>
                    <div class="w-full py-2 bg-gray-100 dark:bg-gray-700/50 text-gray-400 dark:text-gray-500 text-xs font-medium rounded-xl text-center"><i class="fas fa-check-circle mr-1"></i> Semua sudah dibayar</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-16 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center"><i class="fas fa-users text-2xl text-gray-400"></i></div>
            <p class="text-gray-500 dark:text-gray-400 font-medium">Belum ada pekerja Daily / Internship</p>
        </div>
        <?php endif; ?>
    </div>

    
    <div id="panelH" style="display:none">
        <?php if (!empty($paymentHistory)): ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-700/50 text-left">
                            <th class="px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Pekerja</th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Periode</th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase text-center">Hari</th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase text-right">Rate</th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase text-right">Total</th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Waktu</th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <?php foreach ($paymentHistory as $ph):
                            $prc = $ph['worker_role'] === 'daily' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300';
                        ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-900 dark:text-white text-sm"><?= htmlspecialchars($ph['full_name']) ?></div>
                                <span class="inline-block mt-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold <?= $prc ?>"><?= ucfirst($ph['worker_role']) ?></span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300 whitespace-nowrap text-xs"><?= date('d M', strtotime($ph['period_start'])) ?> – <?= date('d M Y', strtotime($ph['period_end'])) ?></td>
                            <td class="px-4 py-3 text-center font-semibold text-gray-900 dark:text-white"><?= $ph['total_days'] ?></td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300 whitespace-nowrap text-xs">Rp <?= number_format($ph['daily_rate'], 0, ',', '.') ?></td>
                            <td class="px-4 py-3 text-right font-bold text-emerald-600 dark:text-emerald-400 whitespace-nowrap text-xs">Rp <?= number_format($ph['total_amount'], 0, ',', '.') ?></td>
                            <td class="px-4 py-3">
                                <div class="text-[11px] text-gray-500 whitespace-nowrap"><?= date('d M Y, H:i', strtotime($ph['paid_at'])) ?></div>
                                <div class="text-[10px] text-gray-400">oleh <?= htmlspecialchars($ph['paid_by_name']) ?></div>
                                <?php if (!empty($ph['notes'])): ?>
                                <div class="text-[10px] text-gray-400 italic mt-0.5"><i class="fas fa-comment-dots mr-0.5"></i> <?= htmlspecialchars($ph['notes']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button type="button" onclick="prDelConfirm(<?= $ph['id'] ?>,'<?= htmlspecialchars($ph['full_name'], ENT_QUOTES) ?>')" class="text-red-400 hover:text-red-600 transition p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20" title="Hapus"><i class="fas fa-trash-alt text-xs"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="text-center py-16 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center"><i class="fas fa-receipt text-2xl text-gray-400"></i></div>
            <p class="text-gray-500 dark:text-gray-400 font-medium">Belum ada riwayat pembayaran</p>
        </div>
        <?php endif; ?>
    </div>

    
    <div id="panelA" style="display:none">
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-5 mb-4">
            <div class="flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">Pekerja</label>
                    <select id="attWorker" onchange="prLoadAtt()" class="px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white">
                        <option value="0">-- Semua --</option>
                        <?php foreach ($workers as $w): ?>
                        <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['full_name']) ?> (<?= ucfirst($w['role']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">Dari</label>
                    <input type="date" id="attStart" value="<?= $currentMonthStart ?>" onchange="prLoadAtt()" class="px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">Sampai</label>
                    <input type="date" id="attEnd" value="<?= date('Y-m-d') ?>" onchange="prLoadAtt()" class="px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white">
                </div>
                <button onclick="prLoadAtt()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition"><i class="fas fa-search mr-1"></i> Tampilkan</button>
            </div>
        </div>
        <div id="attResult">
            <div class="text-center py-12 text-gray-400"><i class="fas fa-camera text-3xl mb-3 block"></i> Pilih pekerja dan periode, lalu klik Tampilkan</div>
        </div>
    </div>

    
    <?php if ($isOwner): ?>
    <div id="panelS" style="display:none">
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                <h3 class="font-bold text-gray-900 dark:text-white"><i class="fas fa-shield-alt mr-2 text-emerald-500"></i> Kelola Akses Payroll</h3>
                <p class="text-xs text-gray-500 mt-1">Hanya admin yang terdaftar di sini yang bisa mengakses halaman Payroll.</p>
            </div>
            <div class="p-5">
                
                <div class="space-y-3 mb-6">
                    <?php foreach ($payrollAdmins as $pa):
                        $paGrad = ($pa['gender'] === 'female') ? 'linear-gradient(135deg,#f472b6,#f43f5e)' : 'linear-gradient(135deg,#60a5fa,#6366f1)';
                        $isSelf = ($pa['user_id'] == $_SESSION['user_id']);
                    ?>
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-700/30">
                        <?php if (!empty($pa['avatar'])): ?>
                        <img src="<?= htmlspecialchars($pa['avatar']) ?>" class="w-9 h-9 rounded-lg object-cover" alt="">
                        <?php else: ?>
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center text-white font-bold text-xs" style="background:<?= $paGrad ?>"><?= strtoupper(mb_substr($pa['full_name'], 0, 1)) ?></div>
                        <?php endif; ?>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm text-gray-900 dark:text-white truncate"><?= htmlspecialchars($pa['full_name']) ?></p>
                            <p class="text-[11px] text-gray-400">@<?= htmlspecialchars($pa['username']) ?><?= $isSelf ? ' · <span class="text-emerald-500 font-medium">Owner</span>' : '' ?></p>
                        </div>
                        <?php if (!$isSelf): ?>
                        <button onclick="prRemoveAdmin(<?= $pa['user_id'] ?>,'<?= htmlspecialchars($pa['full_name'], ENT_QUOTES) ?>')" class="text-red-400 hover:text-red-600 p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition" title="Hapus akses"><i class="fas fa-times text-xs"></i></button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="flex gap-3">
                    <select id="addAdminSelect" class="flex-1 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white">
                        <option value="">-- Pilih Administrator --</option>
                        <?php
                        $existingIds = array_column($payrollAdmins, 'user_id');
                        foreach ($allAdmins as $aa):
                            if (in_array($aa['id'], $existingIds)) continue;
                        ?>
                        <option value="<?= $aa['id'] ?>"><?= htmlspecialchars($aa['full_name']) ?> (@<?= htmlspecialchars($aa['username']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button onclick="prAddAdmin()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg transition"><i class="fas fa-plus mr-1"></i> Tambah</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>


<div id="prPayModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="prClosePay()"></div>
    <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md mx-auto overflow-hidden" style="animation:prSlide .2s ease-out">
        <div class="px-6 py-4 flex items-center justify-between" style="background:linear-gradient(90deg,#10b981,#0d9488)">
            <h3 class="text-lg font-bold text-white"><i class="fas fa-money-bill-wave mr-2"></i> Proses Pembayaran</h3>
            <button onclick="prClosePay()" class="text-white/70 hover:text-white transition"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6">
            <div class="mb-4 p-3 rounded-xl bg-gray-50 dark:bg-gray-700/50">
                <p class="font-bold text-gray-900 dark:text-white text-lg" id="prName"></p>
                <div class="flex items-center gap-2 mt-1">
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold" id="prBadge"></span>
                    <span class="text-xs text-gray-500" id="prRateText"></span>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">Dari Tanggal</label>
                    <input type="date" id="prStart" value="<?= $currentMonthStart ?>" onchange="prRecalc()" class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">Sampai Tanggal</label>
                    <input type="date" id="prEnd" value="<?= date('Y-m-d') ?>" onchange="prRecalc()" class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white">
                </div>
            </div>
            <div class="mb-4 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm text-gray-600 dark:text-gray-300">Hari kerja:</span>
                    <span class="font-bold text-gray-900 dark:text-white" id="prDays">0 hari</span>
                </div>
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm text-gray-600 dark:text-gray-300">Rate:</span>
                    <span class="font-medium text-gray-700 dark:text-gray-300" id="prRateVal">Rp 0</span>
                </div>
                <div class="border-t border-emerald-200 dark:border-emerald-700 my-2"></div>
                <div class="flex justify-between items-center">
                    <span class="text-sm font-bold text-emerald-700 dark:text-emerald-300">Total Bayar:</span>
                    <span class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400" id="prTotal">Rp 0</span>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">Catatan <span class="font-normal text-gray-400">(opsional)</span></label>
                <textarea id="prNotes" rows="2" class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white resize-none" placeholder="Contoh: Gaji minggu ke-2 Maret"></textarea>
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="prClosePay()" class="flex-1 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-semibold rounded-xl hover:bg-gray-200 dark:hover:bg-gray-600 transition">Batal</button>
                <button type="button" onclick="prSubmitPay()" id="prPayBtn" class="flex-1 py-2.5 text-white text-sm font-bold rounded-xl transition-all shadow-sm disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2" style="background:linear-gradient(90deg,#10b981,#0d9488)">
                    <i class="fas fa-check-circle"></i> <span>Konfirmasi Bayar</span>
                </button>
            </div>
        </div>
    </div>
</div>


<div id="prDelModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="prCloseDel()"></div>
    <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm mx-auto p-6" style="animation:prSlide .2s ease-out">
        <div class="text-center">
            <div class="w-14 h-14 mx-auto mb-3 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center"><i class="fas fa-exclamation-triangle text-2xl text-red-500"></i></div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">Hapus Pembayaran?</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Pembayaran untuk <strong id="prDelName"></strong> akan dihapus.</p>
            <div class="flex gap-3">
                <button onclick="prCloseDel()" class="flex-1 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-semibold rounded-xl hover:bg-gray-200 dark:hover:bg-gray-600 transition">Batal</button>
                <button onclick="prExecDel()" id="prDelBtn" class="flex-1 py-2.5 bg-red-500 hover:bg-red-600 text-white text-sm font-bold rounded-xl transition disabled:opacity-50 flex items-center justify-center gap-2"><i class="fas fa-trash-alt"></i> <span>Hapus</span></button>
            </div>
        </div>
    </div>
</div>


<div id="prLightbox" class="fixed inset-0 z-[60] flex items-center justify-center p-4" style="display:none" onclick="this.style.display='none'">
    <div class="fixed inset-0 bg-black/80"></div>
    <img id="prLightboxImg" class="relative max-w-full max-h-[85vh] rounded-xl shadow-2xl object-contain" src="" alt="">
</div>


<div id="prToast" class="fixed inset-0 z-[70] flex items-center justify-center pointer-events-none" style="display:none">
    <div class="pointer-events-auto bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-8 text-center border-2" id="prToastBox" style="min-width:260px">
        <div class="w-14 h-14 mx-auto mb-3 rounded-full flex items-center justify-center" id="prToastIW"><i id="prToastI" class="text-3xl"></i></div>
        <p class="font-bold text-lg" id="prToastT"></p>
        <p class="text-sm text-gray-500 mt-1" id="prToastM"></p>
    </div>
</div>

<style>
@keyframes prSlide { from { opacity:0; transform:translateY(20px) } to { opacity:1; transform:translateY(0) } }
</style>

<script>
(function(){
    var S = {uid:0, rate:0, days:0, total:0, delId:0};
    var csrf = '<?= $csrf ?>';
    var fmt = function(n){ return new Intl.NumberFormat('id-ID').format(n); };
    var tabIds = ['W','H','A','S'];

    window.payrollTab = function(t){
        var map = {workers:'W',history:'H',attendance:'A',settings:'S'};
        var key = map[t] || 'W';
        tabIds.forEach(function(k){
            var p = document.getElementById('panel'+k);
            var b = document.getElementById('tabBtn'+k);
            if(p) p.style.display = k===key ? '' : 'none';
            if(b) b.className = 'shrink-0 px-4 py-2 rounded-lg text-sm font-semibold transition-all ' + (k===key ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400');
        });
    };

    function updLabels(){
        document.getElementById('prDays').textContent = S.days + ' hari';
        document.getElementById('prRateVal').textContent = 'Rp ' + fmt(S.rate);
        document.getElementById('prTotal').textContent = 'Rp ' + fmt(S.total);
        document.getElementById('prPayBtn').disabled = S.days === 0;
    }

    window.prOpenPay = function(uid, name, role, rate, days, total){
        S.uid = uid; S.rate = rate; S.days = days; S.total = total;
        document.getElementById('prName').textContent = name;
        var b = document.getElementById('prBadge');
        b.textContent = role==='daily' ? 'Daily' : 'Internship';
        b.className = 'px-2 py-0.5 rounded text-[10px] font-bold ' + (role==='daily' ? 'bg-blue-100 text-blue-700' : 'bg-violet-100 text-violet-700');
        document.getElementById('prRateText').textContent = 'Rp ' + fmt(rate) + '/hari';
        document.getElementById('prStart').value = '<?= $currentMonthStart ?>';
        document.getElementById('prEnd').value = '<?= date('Y-m-d') ?>';
        document.getElementById('prNotes').value = '';
        updLabels();
        document.getElementById('prPayModal').style.display = '';
    };
    window.prClosePay = function(){ document.getElementById('prPayModal').style.display = 'none'; };

    window.prRecalc = async function(){
        var s = document.getElementById('prStart').value, e = document.getElementById('prEnd').value;
        if(!s||!e) return;
        try {
            var r = await fetch('dashboard.php?page=payroll&ajax_count=1&user_id='+S.uid+'&start='+encodeURIComponent(s)+'&end='+encodeURIComponent(e));
            var d = await r.json();
            if(d.count !== undefined){ S.days = d.count; S.total = d.count * S.rate; updLabels(); }
        } catch(x){}
    };

    window.prSubmitPay = async function(){
        var btn = document.getElementById('prPayBtn');
        btn.disabled = true; btn.querySelector('span').textContent = 'Memproses...';
        btn.querySelector('i').className = 'fas fa-spinner fa-spin';
        try {
            var fd = new FormData();
            fd.append('action','process_payment'); fd.append('csrf',csrf);
            fd.append('user_id',S.uid);
            fd.append('period_start',document.getElementById('prStart').value);
            fd.append('period_end',document.getElementById('prEnd').value);
            fd.append('notes',document.getElementById('prNotes').value);
            var r = await fetch('dashboard.php?page=payroll',{method:'POST',body:fd});
            var d = await r.json();
            prClosePay();
            prToast(d.success?'ok':'err', d.message);
            if(d.success) setTimeout(function(){ location.reload(); },1500);
        } catch(x){ prClosePay(); prToast('err','Terjadi kesalahan'); }
        btn.disabled = false; btn.querySelector('span').textContent = 'Konfirmasi Bayar';
        btn.querySelector('i').className = 'fas fa-check-circle';
    };

    window.prDelConfirm = function(id, name){
        S.delId = id;
        document.getElementById('prDelName').textContent = name;
        document.getElementById('prDelModal').style.display = '';
    };
    window.prCloseDel = function(){ document.getElementById('prDelModal').style.display = 'none'; };

    window.prExecDel = async function(){
        var btn = document.getElementById('prDelBtn');
        btn.disabled = true; btn.querySelector('span').textContent = 'Menghapus...';
        try {
            var fd = new FormData();
            fd.append('action','delete_payment'); fd.append('csrf',csrf);
            fd.append('payment_id',S.delId);
            var r = await fetch('dashboard.php?page=payroll',{method:'POST',body:fd});
            var d = await r.json();
            prCloseDel();
            prToast(d.success?'ok':'err', d.message);
            if(d.success) setTimeout(function(){ location.reload(); },1500);
        } catch(x){ prCloseDel(); prToast('err','Terjadi kesalahan'); }
        btn.disabled = false; btn.querySelector('span').textContent = 'Hapus';
    };

    window.prLoadAtt = async function(){
        var uid = document.getElementById('attWorker').value;
        var s = document.getElementById('attStart').value;
        var e = document.getElementById('attEnd').value;
        var res = document.getElementById('attResult');
        res.innerHTML = '<div class="text-center py-8 text-gray-400"><i class="fas fa-spinner fa-spin text-2xl"></i></div>';

        var workers = <?= json_encode(array_map(fn($w) => ['id'=>$w['id'],'name'=>$w['full_name'],'role'=>$w['role']], $workers)) ?>;
        var targets = uid == 0 ? workers : workers.filter(function(w){ return w.id == uid; });

        var allRows = [];
        for (var i = 0; i < targets.length; i++) {
            try {
                var r = await fetch('dashboard.php?page=payroll&ajax_detail=1&user_id='+targets[i].id+'&start='+encodeURIComponent(s)+'&end='+encodeURIComponent(e));
                var d = await r.json();
                (d.rows||[]).forEach(function(row){ row._name = targets[i].name; row._role = targets[i].role; allRows.push(row); });
            } catch(x){}
        }

        if (allRows.length === 0) {
            res.innerHTML = '<div class="text-center py-12 text-gray-400"><i class="fas fa-folder-open text-3xl mb-3 block"></i> Tidak ada data absensi di periode ini</div>';
            return;
        }

        var html = '<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden"><div class="overflow-x-auto"><table class="w-full text-sm">';
        html += '<thead><tr class="bg-gray-50 dark:bg-gray-700/50"><th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Nama</th><th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Tanggal</th><th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase text-center">Status</th><th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase text-center">Masuk</th><th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase text-center">Pulang</th><th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase text-center">Foto Masuk</th><th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase text-center">Foto Pulang</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-700">';

        allRows.forEach(function(r){
            var ci = (r.check_in_time && r.check_in_time !== '0000-00-00 00:00:00') ? r.check_in_time.substr(11,5) : '-';
            var co = (r.check_out_time && r.check_out_time !== '0000-00-00 00:00:00') ? r.check_out_time.substr(11,5) : '-';
            var ciImg = r.check_in_photo ? '<img src="'+r.check_in_photo+'" class="w-16 h-12 object-cover rounded-lg border cursor-pointer hover:opacity-80 transition" onclick="prLB(this.src)">' : '<span class="text-gray-300 text-xs">-</span>';
            var coImg = r.check_out_photo ? '<img src="'+r.check_out_photo+'" class="w-16 h-12 object-cover rounded-lg border cursor-pointer hover:opacity-80 transition" onclick="prLB(this.src)">' : '<span class="text-gray-300 text-xs">-</span>';
            var dt = new Date(r.attendance_date);
            var dtStr = dt.toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'});
            var badge = r._role === 'daily' ? 'bg-blue-100 text-blue-700' : 'bg-violet-100 text-violet-700';
            html += '<tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">';
            html += '<td class="px-4 py-3"><div class="font-semibold text-gray-900 dark:text-white text-sm">'+r._name+'</div><span class="px-1.5 py-0.5 rounded text-[10px] font-bold '+badge+'">'+r._role.charAt(0).toUpperCase()+r._role.slice(1)+'</span></td>';
            html += '<td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300 whitespace-nowrap">'+dtStr+'</td>';
            html += '<td class="px-4 py-3 text-center"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold '+(r.status==='Hadir'?'bg-emerald-100 text-emerald-700':'bg-amber-100 text-amber-700')+'">'+r.status+'</span></td>';
            html += '<td class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300">'+ci+'</td>';
            html += '<td class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300">'+co+'</td>';
            html += '<td class="px-4 py-3 text-center">'+ciImg+'</td>';
            html += '<td class="px-4 py-3 text-center">'+coImg+'</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div></div>';
        res.innerHTML = html;
    };

    window.prLB = function(src){
        document.getElementById('prLightboxImg').src = src;
        document.getElementById('prLightbox').style.display = '';
    };

    window.prExportExcel = function(){
        var uid = (document.getElementById('attWorker')||{}).value || 0;
        var s = (document.getElementById('attStart')||{}).value || '<?= $currentMonthStart ?>';
        var e = (document.getElementById('attEnd')||{}).value || '<?= date('Y-m-d') ?>';
        window.location.href = 'dashboard.php?page=payroll&export=excel&user_id='+uid+'&start='+encodeURIComponent(s)+'&end='+encodeURIComponent(e);
    };

    window.prAddAdmin = async function(){
        var sel = document.getElementById('addAdminSelect');
        var id = sel.value;
        if(!id) return;
        var fd = new FormData();
        fd.append('action','add_admin'); fd.append('csrf',csrf); fd.append('admin_id',id);
        try {
            var r = await fetch('dashboard.php?page=payroll',{method:'POST',body:fd});
            var d = await r.json();
            prToast(d.success?'ok':'err', d.message);
            if(d.success) setTimeout(function(){ location.reload(); },1200);
        } catch(x){ prToast('err','Terjadi kesalahan'); }
    };

    window.prRemoveAdmin = async function(id, name){
        if(!confirm('Hapus akses payroll untuk '+name+'?')) return;
        var fd = new FormData();
        fd.append('action','remove_admin'); fd.append('csrf',csrf); fd.append('admin_id',id);
        try {
            var r = await fetch('dashboard.php?page=payroll',{method:'POST',body:fd});
            var d = await r.json();
            prToast(d.success?'ok':'err', d.message);
            if(d.success) setTimeout(function(){ location.reload(); },1200);
        } catch(x){ prToast('err','Terjadi kesalahan'); }
    };

    function prToast(type, msg){
        var t = document.getElementById('prToast');
        var box = document.getElementById('prToastBox');
        var iw = document.getElementById('prToastIW');
        var ic = document.getElementById('prToastI');
        var tt = document.getElementById('prToastT');
        var tm = document.getElementById('prToastM');
        var ok = type==='ok';
        box.className = 'pointer-events-auto bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-8 text-center border-2 '+(ok?'border-emerald-200 dark:border-emerald-700':'border-red-200 dark:border-red-700');
        iw.className = 'w-14 h-14 mx-auto mb-3 rounded-full flex items-center justify-center '+(ok?'bg-emerald-100 dark:bg-emerald-900/30':'bg-red-100 dark:bg-red-900/30');
        ic.className = 'text-3xl '+(ok?'fas fa-check-circle text-emerald-500':'fas fa-times-circle text-red-500');
        tt.className = 'font-bold text-lg '+(ok?'text-emerald-600':'text-red-600');
        tt.textContent = ok?'Berhasil!':'Gagal!';
        tm.textContent = msg;
        t.style.display = '';
        setTimeout(function(){ t.style.display = 'none'; },2500);
    }
})();
</script>
