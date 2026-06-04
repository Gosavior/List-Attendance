<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];


if (isset($_GET['export']) && $_GET['export'] === 'excel' && $role === 'administrator') {
    $exportTab = $_GET['tab'] ?? 'company';
    $exportMonth = $_GET['month'] ?? 'all';
    $exportType = $_GET['type'] ?? 'all';

    $monthWhere = '';
    $params = [];
    if ($exportMonth !== 'all') {
        $monthWhere = " AND DATE_FORMAT(tp.created_at, '%Y-%m') = :month";
        $params[':month'] = $exportMonth;
    }
    $typeWhere = '';
    if ($exportType !== 'all') {
        $typeWhere = " AND tp.permit_type = :ptype";
        $params[':ptype'] = $exportType;
    }

    if ($exportTab === 'project') {
        
        $sql = "SELECT 
                tp.id as 'ID Permit',
                t.name as 'Nama Tools',
                t.code as 'Kode Tools',
                'Project' as 'Tipe',
                u_to.id as 'ID Peminjam',
                u_to.full_name as 'Nama Peminjam',
                tp.reason as 'PIC / Keterangan',
                COALESCE(tp.location, '-') as 'Lokasi',
                DATE_FORMAT(tp.start_date, '%d/%m/%Y') as 'Tgl Mulai',
                DATE_FORMAT(tp.end_date, '%d/%m/%Y') as 'Tgl Selesai',
                tp.status as 'Status',
                DATE_FORMAT(tp.created_at, '%d/%m/%Y %H:%i') as 'Tgl Pengajuan'
            FROM tool_permits tp
            JOIN tools t ON tp.tool_id = t.id
            JOIN users u_to ON tp.to_user_id = u_to.id
            WHERE tp.permit_type = 'project'
            {$monthWhere}
            ORDER BY tp.created_at DESC";
    } else {
        $sql = "SELECT 
                tp.id as 'ID Permit',
                t.name as 'Nama Tools',
                t.code as 'Kode Tools',
                CASE tp.permit_type 
                    WHEN 'loan' THEN 'Peminjaman'
                    WHEN 'return' THEN 'Pengembalian'
                    WHEN 'handover' THEN 'Serah Terima'
                    WHEN 'force_return' THEN 'Return Paksa'
                    WHEN 'project' THEN 'Project'
                END as 'Tipe Aktivitas',
                u_from.id as 'ID Dari',
                u_from.full_name as 'Dari',
                u_to.id as 'ID Kepada',
                COALESCE(u_to.full_name, 'PT. Artha Solusi Aditama') as 'Kepada',
                tp.reason as 'Keterangan',
                COALESCE(tp.location, '-') as 'Lokasi',
                DATE_FORMAT(tp.start_date, '%d/%m/%Y') as 'Tgl Mulai',
                DATE_FORMAT(tp.end_date, '%d/%m/%Y') as 'Tgl Selesai',
                tp.status as 'Status',
                DATE_FORMAT(tp.created_at, '%d/%m/%Y %H:%i') as 'Tgl Dibuat',
                DATE_FORMAT(tp.approved_at, '%d/%m/%Y %H:%i') as 'Tgl Disetujui'
            FROM tool_permits tp
            JOIN tools t ON tp.tool_id = t.id
            LEFT JOIN users u_from ON tp.from_user_id = u_from.id
            LEFT JOIN users u_to ON tp.to_user_id = u_to.id
            WHERE (
                (t.tool_type = 'company' AND tp.permit_type IN ('loan','handover','return','force_return'))
                OR tp.permit_type = 'project'
            )
            {$monthWhere}
            {$typeWhere}
            ORDER BY tp.created_at DESC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'history-tools-' . $exportTab . '-' . date('Y-m-d') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF"; 
    if (!empty($rows)) {
        echo '<table border="1">';
        echo '<tr>';
        foreach (array_keys($rows[0]) as $header) {
            echo '<th style="background:#4472C4;color:#fff;font-weight:bold;padding:6px 12px;">' . htmlspecialchars($header) . '</th>';
        }
        echo '</tr>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $val) {
                echo '<td style="padding:4px 10px;">' . htmlspecialchars($val ?? '-') . '</td>';
            }
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<table border="1"><tr><td>Tidak ada data</td></tr></table>';
    }
    exit;
}


$filter_month = $_GET['month'] ?? 'all';
$filter_type = $_GET['type'] ?? 'all';


$monthWhere = '';
$companyParams = [];
if ($filter_month !== 'all') {
    $monthWhere = " AND DATE_FORMAT(tp.created_at, '%Y-%m') = :month";
    $companyParams[':month'] = $filter_month;
}
$typeWhere = '';
if ($filter_type !== 'all') {
    $typeWhere = " AND tp.permit_type = :ptype";
    $companyParams[':ptype'] = $filter_type;
}


$userWhere = '';
if ($role !== 'administrator') {
    $userWhere = " AND (tp.from_user_id = :uid OR tp.to_user_id = :uid2)";
    $companyParams[':uid'] = $user_id;
    $companyParams[':uid2'] = $user_id;
}

$stmt_company = $pdo->prepare("
    SELECT 
        tp.id,
        tp.permit_type,
        tp.tool_id,
        t.name as tool_name,
        t.code as tool_code,
        t.photo_path as tool_photo,
        tp.from_user_id,
        u_from.full_name as from_user,
        tp.to_user_id,
        COALESCE(u_to.full_name, 'PT. Artha Solusi Aditama') as to_user,
        tp.reason,
        tp.location,
        tp.start_date,
        tp.end_date,
        tp.status,
        tp.created_at,
        tp.approved_at,
        tp.photo_proof_path,
        tp.admin_photo_path,
        tp.approved_by,
        approver.full_name as approved_by_name
    FROM tool_permits tp
    JOIN tools t ON tp.tool_id = t.id
    LEFT JOIN users u_from ON tp.from_user_id = u_from.id
    LEFT JOIN users u_to ON tp.to_user_id = u_to.id
    LEFT JOIN users approver ON tp.approved_by = approver.id
    WHERE (
      (t.tool_type = 'company' AND tp.permit_type IN ('loan','handover','return','force_return'))
      OR tp.permit_type = 'project'
    )
    {$monthWhere}
    {$typeWhere}
    {$userWhere}
    ORDER BY tp.created_at DESC
");
$stmt_company->execute($companyParams);
$company_data = $stmt_company->fetchAll(PDO::FETCH_ASSOC);


$available_months = $pdo->query("
    SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') as ym 
    FROM tool_permits 
    ORDER BY ym DESC
")->fetchAll(PDO::FETCH_COLUMN);


$apdMonthWhere = '';
$apdParams = [];
if ($filter_month !== 'all') {
    $apdMonthWhere = " AND DATE_FORMAT(tp.created_at, '%Y-%m') = :month";
    $apdParams[':month'] = $filter_month;
}
$apdUserWhere = '';
if ($role !== 'administrator') {
    $apdUserWhere = " AND (tp.from_user_id = :uid OR tp.to_user_id = :uid2)";
    $apdParams[':uid'] = $user_id;
    $apdParams[':uid2'] = $user_id;
}
$stmt_apd = $pdo->prepare("
    SELECT 
        tp.id,
        tp.permit_type,
        tp.tool_id,
        t.name as tool_name,
        t.code as tool_code,
        t.photo_path as tool_photo,
        tp.from_user_id,
        u_from.full_name as from_user,
        tp.to_user_id,
        COALESCE(u_to.full_name, 'PT. Artha Solusi Aditama') as to_user,
        tp.reason,
        tp.location,
        tp.start_date,
        tp.end_date,
        tp.status,
        tp.created_at,
        tp.approved_at,
        tp.photo_proof_path,
        tp.admin_photo_path,
        tp.approved_by,
        approver.full_name as approved_by_name
    FROM tool_permits tp
    JOIN tools t ON tp.tool_id = t.id
    JOIN users u_from ON tp.from_user_id = u_from.id
    LEFT JOIN users u_to ON tp.to_user_id = u_to.id
    LEFT JOIN users approver ON tp.approved_by = approver.id
    WHERE t.tool_type = 'apd'
    AND tp.permit_type IN ('loan','return','force_return')
    {$apdMonthWhere}
    {$apdUserWhere}
    ORDER BY tp.created_at DESC
");
$stmt_apd->execute($apdParams);
$apd_data = $stmt_apd->fetchAll(PDO::FETCH_ASSOC);


$overdue_loans = [];
if ($role === 'administrator') {
    $stmt_overdue = $pdo->prepare("
        SELECT 
            tp.id,
            t.name AS tool_name,
            t.code AS tool_code,
            uto.id AS technician_id,
            uto.full_name AS technician_name,
            COALESCE(tp.start_date, tp.approved_at, tp.created_at) AS borrowed_at,
            tp.end_date,
            TIMESTAMPDIFF(HOUR, COALESCE(tp.start_date, tp.approved_at, tp.created_at), NOW()) AS hours_out
        FROM tool_permits tp
        JOIN tools t ON t.id = tp.tool_id
        JOIN users uto ON uto.id = tp.to_user_id
        WHERE tp.status = 'approved'
          AND tp.permit_type IN ('loan', 'handover', 'project')
          AND t.tool_type = 'company'
          AND t.current_status IN ('Loan', 'Handover', 'Project')
          AND uto.role = 'technician'
          AND uto.is_active = 1
          AND (
              (tp.end_date IS NOT NULL AND tp.end_date < NOW())
              OR (tp.end_date IS NULL AND TIMESTAMPDIFF(HOUR, COALESCE(tp.start_date, tp.approved_at, tp.created_at), NOW()) > 72)
          )
          AND tp.id = (
              SELECT tp2.id FROM tool_permits tp2
              WHERE tp2.tool_id = tp.tool_id AND tp2.status = 'approved'
                AND tp2.permit_type IN ('loan', 'handover', 'project')
              ORDER BY COALESCE(tp2.approved_at, tp2.created_at) DESC, tp2.id DESC LIMIT 1
          )
        ORDER BY hours_out DESC
    ");
    $stmt_overdue->execute();
    foreach ($stmt_overdue->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $hoursOut = (int)$row['hours_out'];
        if (!empty($row['end_date']) && strtotime($row['end_date']) < time()) {
            $lateHours = max((int)round((time() - strtotime($row['end_date'])) / 3600), 0);
        } else {
            $lateHours = max($hoursOut - 72, 0);
        }
        $lateDays = intdiv($lateHours, 24);
        $remainingH = $lateHours % 24;
        $parts = [];
        if ($lateDays > 0) $parts[] = $lateDays . ' hari';
        if ($remainingH > 0) $parts[] = $remainingH . ' jam';
        $row['overdue_text'] = $parts ? 'Terlambat ' . implode(' ', $parts) : 'Melewati batas 3 hari';
        $overdue_loans[] = $row;
    }
}


$monthly_check_overview = [];
if ($role === 'administrator') {
    $check_month = date('Y-m');
    $stmt_mc = $pdo->prepare("
        SELECT 
            u.id as user_id,
            u.full_name,
            u.username,
            COUNT(DISTINCT t.id) as total_tools,
            mc.id as check_id,
            mc.checked_at,
            COUNT(DISTINCT mci.tool_id) as checked_tools
        FROM users u
        INNER JOIN tool_assignments ta ON ta.user_id = u.id
        INNER JOIN tools t ON t.id = ta.tool_id AND t.tool_type = 'personal'
        LEFT JOIN monthly_checks mc ON mc.user_id = u.id AND mc.check_month = :cm
        LEFT JOIN monthly_check_items mci ON mci.check_id = mc.id
        WHERE u.role IN ('technician', 'technician_manager')
          AND u.is_active = 1
        GROUP BY u.id, u.full_name, u.username, mc.id, mc.checked_at
        ORDER BY mc.checked_at IS NOT NULL ASC, u.full_name ASC
    ");
    $stmt_mc->execute([':cm' => $check_month]);
    $monthly_check_overview = $stmt_mc->fetchAll(PDO::FETCH_ASSOC);
}


if ($role === 'administrator') {
    $stats = $pdo->query("
        SELECT 
            COUNT(1) as total,
            SUM(permit_type = 'loan') as loans,
            SUM(permit_type = 'return') as returns,
            SUM(permit_type = 'handover') as handovers,
            SUM(permit_type = 'project') as projects,
            SUM(permit_type = 'force_return') as force_returns
        FROM tool_permits
    ")->fetch(PDO::FETCH_ASSOC);
} else {
    $stStats = $pdo->prepare("
        SELECT 
            COUNT(1) as total,
            SUM(permit_type = 'loan') as loans,
            SUM(permit_type = 'return') as returns,
            SUM(permit_type = 'handover') as handovers,
            SUM(permit_type = 'project') as projects,
            SUM(permit_type = 'force_return') as force_returns
        FROM tool_permits
        WHERE from_user_id = :uid OR to_user_id = :uid2
    ");
    $stStats->execute([':uid' => $user_id, ':uid2' => $user_id]);
    $stats = $stStats->fetch(PDO::FETCH_ASSOC);
}

$typeLabels = [
    'loan' => ['Peminjaman', 'text-blue-700 bg-blue-50 dark:text-blue-300 dark:bg-blue-900/40', 'fas fa-hand-holding'],
    'return' => ['Pengembalian', 'text-green-700 bg-green-50 dark:text-green-300 dark:bg-green-900/40', 'fas fa-undo'],
    'handover' => ['Serah Terima', 'text-purple-700 bg-purple-50 dark:text-purple-300 dark:bg-purple-900/40', 'fas fa-exchange-alt'],
    'force_return' => ['Return Paksa', 'text-red-700 bg-red-50 dark:text-red-300 dark:bg-red-900/40', 'fas fa-exclamation-circle'],
    'project' => ['Project', 'text-amber-700 bg-amber-50 dark:text-amber-300 dark:bg-amber-900/40', 'fas fa-project-diagram'],
];

$statusLabels = [
    'pending' => ['Menunggu', 'text-yellow-700 bg-yellow-50 dark:text-yellow-300 dark:bg-yellow-900/40'],
    'approved' => ['Disetujui', 'text-green-700 bg-green-50 dark:text-green-300 dark:bg-green-900/40'],
    'rejected' => ['Ditolak', 'text-red-700 bg-red-50 dark:text-red-300 dark:bg-red-900/40'],
];
?>

<style>
.ht-stat-card {
    border-radius: 12px;
    padding: 20px;
    border: 1px solid #e5e7eb;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.ht-stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.dark .ht-stat-card { border-color: #374151; }
.ht-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.ht-table th { 
    position: sticky; top: 0; z-index: 10;
    padding: 12px 16px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
    text-align: left; white-space: nowrap;
    background: #f8fafc; color: #64748b; border-bottom: 2px solid #e2e8f0;
}
.dark .ht-table th { background: #1e293b; color: #94a3b8; border-color: #334155; }
.ht-table td { padding: 12px 16px; font-size: 13px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.dark .ht-table td { border-color: #1e293b; }
.ht-table tbody tr { transition: background 0.1s ease; }
.ht-table tbody tr:hover { background: #f8fafc; }
.dark .ht-table tbody tr:hover { background: #1e293b; }
.ht-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.ht-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.ht-toolbar select, .ht-toolbar input { 
    height: 38px; padding: 0 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 13px; background: #fff;
}
.dark .ht-toolbar select, .dark .ht-toolbar input { background: #1e293b; border-color: #334155; color: #e2e8f0; }
.ht-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.15s ease; border: none; }
.ht-btn-green { background: #059669; color: #fff; }
.ht-btn-green:hover { background: #047857; }
.ht-user-id { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 11px; color: #94a3b8; }
.ht-empty { padding: 60px 20px; text-align: center; }
.ht-empty i { font-size: 48px; color: #d1d5db; margin-bottom: 16px; display: block; }
.dark .ht-empty i { color: #4b5563; }

.ht-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 12px;
    transition: box-shadow 0.15s ease;
}
.ht-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.dark .ht-card { background: #1e293b; border-color: #334155; }
.ht-card-header { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
.ht-card-body { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.ht-card-field { display: flex; flex-direction: column; gap: 2px; }
.ht-card-field .ht-label { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; }
.dark .ht-card-field .ht-label { color: #64748b; }
.ht-card-field .ht-value { font-size: 13px; color: #1e293b; font-weight: 500; }
.dark .ht-card-field .ht-value { color: #e2e8f0; }
.ht-type-stripe { width: 4px; border-radius: 4px; min-height: 100%; flex-shrink: 0; }
.ht-type-loan { background: #3b82f6; }
.ht-type-return { background: #10b981; }
.ht-type-handover { background: #8b5cf6; }
.ht-type-force_return { background: #ef4444; }
.ht-type-project { background: #f59e0b; }

@media (max-width: 640px) {
    .ht-toolbar { flex-direction: column; }
    .ht-toolbar select, .ht-toolbar input { width: 100%; }
    .ht-card-body { grid-template-columns: 1fr; }
}
</style>

<div class="max-w-[1400px] mx-auto">
    
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
            <i class="fas fa-history text-blue-500"></i>
            History Tools
        </h1>
        <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Rekapan lengkap semua aktivitas peminjaman, pengembalian, dan serah terima tools</p>
    </div>

    
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
        <div class="ht-stat-card bg-white dark:bg-gray-800">
            <div class="text-2xl font-bold text-gray-900 dark:text-white"><?= (int)$stats['total'] ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Total Aktivitas</div>
        </div>
        <div class="ht-stat-card bg-white dark:bg-gray-800">
            <div class="text-2xl font-bold text-blue-600"><?= (int)$stats['loans'] ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Peminjaman</div>
        </div>
        <div class="ht-stat-card bg-white dark:bg-gray-800">
            <div class="text-2xl font-bold text-green-600"><?= (int)$stats['returns'] + (int)$stats['force_returns'] ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Pengembalian</div>
        </div>
        <div class="ht-stat-card bg-white dark:bg-gray-800">
            <div class="text-2xl font-bold text-purple-600"><?= (int)$stats['handovers'] ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Serah Terima</div>
        </div>
        <div class="ht-stat-card bg-white dark:bg-gray-800">
            <div class="text-2xl font-bold text-amber-600"><?= (int)$stats['projects'] ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Project</div>
        </div>
    </div>

    
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 mb-6 p-1.5">
        <div class="flex gap-1">
            <button type="button" data-tab="tools" class="ht-tab-btn flex-1 py-2.5 px-4 rounded-lg text-sm font-semibold transition-all bg-blue-600 text-white">
                <i class="fas fa-tools mr-1"></i> Tools
            </button>
            <button type="button" data-tab="apd" class="ht-tab-btn flex-1 py-2.5 px-4 rounded-lg text-sm font-semibold transition-all text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                <i class="fas fa-hard-hat mr-1"></i> APD
                <?php if (count($apd_data) > 0): ?>
                <span class="ml-1 inline-flex items-center justify-center px-1.5 h-5 text-[10px] font-bold bg-teal-500 text-white rounded-full"><?= count($apd_data) ?></span>
                <?php endif; ?>
            </button>
            <?php if ($role === 'administrator'): ?>
            <button type="button" data-tab="personal" class="ht-tab-btn flex-1 py-2.5 px-4 rounded-lg text-sm font-semibold transition-all text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                <i class="fas fa-user-check mr-1"></i> Personal Tools
                <?php if (!empty($overdue_loans)): ?>
                <span class="ml-1 inline-flex items-center justify-center w-5 h-5 text-[10px] font-bold bg-red-500 text-white rounded-full"><?= count($overdue_loans) ?></span>
                <?php endif; ?>
            </button>
            <?php endif; ?>
        </div>
    </div>

    
    <div id="panel-tools" class="ht-panel">
        
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-4">
            <div class="ht-toolbar">
                <select id="filterMonth" onchange="applyCompanyFilters()">
                    <option value="all">Semua Bulan</option>
                    <?php foreach ($available_months as $m): ?>
                    <option value="<?= $m ?>" <?= $filter_month === $m ? 'selected' : '' ?>><?= date('F Y', strtotime($m . '-01')) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filterType" onchange="applyCompanyFilters()">
                    <option value="all">Semua Tipe</option>
                    <option value="loan" <?= $filter_type === 'loan' ? 'selected' : '' ?>>Peminjaman</option>
                    <option value="return" <?= $filter_type === 'return' ? 'selected' : '' ?>>Pengembalian</option>
                    <option value="handover" <?= $filter_type === 'handover' ? 'selected' : '' ?>>Serah Terima</option>
                    <option value="force_return" <?= $filter_type === 'force_return' ? 'selected' : '' ?>>Return Paksa</option>
                    <option value="project" <?= $filter_type === 'project' ? 'selected' : '' ?>>Project</option>
                </select>
                <input type="text" id="searchCompanyHistory" placeholder="Cari nama tools / user..." oninput="filterCompanyTable()" class="flex-1 min-w-[200px]">
                <?php if ($role === 'administrator'): ?>
                <div class="ml-auto flex gap-2">
                    <button type="button" onclick="exportExcel('tools')" class="ht-btn ht-btn-green">
                        <i class="fas fa-file-excel"></i> Download Excel
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <?php if (empty($company_data)): ?>
            <div class="ht-empty">
                <i class="fas fa-inbox"></i>
                <h3 class="text-lg font-medium text-gray-500 dark:text-gray-400">Belum ada data history</h3>
                <p class="text-gray-400 dark:text-gray-500 text-sm mt-1">Data akan muncul setelah ada aktivitas peminjaman tools</p>
            </div>
            <?php else: ?>
            <div class="p-4 space-y-3" id="companyCards">
                <?php foreach ($company_data as $i => $item):
                    $tl = $typeLabels[$item['permit_type']] ?? ['?','text-gray-600 bg-gray-100','fas fa-question'];
                    $sl = $statusLabels[$item['status']] ?? ['?','text-gray-600 bg-gray-100'];
                    $stripeClass = 'ht-type-' . $item['permit_type'];
                ?>
                <div class="ht-card company-row flex gap-3 cursor-pointer hover:ring-2 hover:ring-blue-300 dark:hover:ring-blue-600" onclick="openHistoryDetail(<?= (int)$item['id'] ?>)">
                    <div class="ht-type-stripe <?= $stripeClass ?>"></div>
                    <div class="flex-1 min-w-0">
                        
                        <div class="ht-card-header">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="ht-badge <?= $tl[1] ?>">
                                    <i class="<?= $tl[2] ?>" style="font-size:10px"></i>
                                    <?= $tl[0] ?>
                                </span>
                                <span class="font-semibold text-gray-900 dark:text-white text-sm"><?= htmlspecialchars($item['tool_name']) ?></span>
                                <span class="text-xs text-gray-400 font-mono"><?= htmlspecialchars($item['tool_code']) ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="ht-badge <?= $sl[1] ?>"><?= $sl[0] ?></span>
                                <span class="text-xs text-gray-400"><?= date('d M Y, H:i', strtotime($item['created_at'])) ?></span>
                            </div>
                        </div>
                        
                        <div class="ht-card-body">
                            <div class="ht-card-field">
                                <span class="ht-label"><i class="fas fa-user mr-1"></i><?= $item['permit_type'] === 'project' ? 'Pengaju' : 'Peminjam' ?></span>
                                <span class="ht-value"><?= htmlspecialchars($item['to_user']) ?></span>
                            </div>
                            <div class="ht-card-field">
                                <span class="ht-label"><i class="fas fa-map-marker-alt mr-1"></i>Lokasi</span>
                                <span class="ht-value"><?= htmlspecialchars($item['location'] ?? '-') ?></span>
                            </div>
                            <div class="ht-card-field">
                                <?php if ($item['permit_type'] === 'project'): ?>
                                <span class="ht-label"><i class="fas fa-user-tie mr-1"></i>PIC</span>
                                <span class="ht-value text-xs"><?= htmlspecialchars($item['reason'] ?? '-') ?></span>
                                <?php else: ?>
                                <span class="ht-label"><i class="fas fa-arrow-right mr-1"></i>Dari</span>
                                <span class="ht-value text-xs"><?= htmlspecialchars($item['from_user'] ?? '-') ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="ht-card-field">
                                <span class="ht-label"><i class="fas fa-calendar-alt mr-1"></i>Periode</span>
                                <span class="ht-value text-xs">
                                    <?php if ($item['start_date'] && $item['end_date']): ?>
                                        <?= date('d/m/Y', strtotime($item['start_date'])) ?> - <?= date('d/m/Y', strtotime($item['end_date'])) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if (!empty($item['reason']) && $item['permit_type'] !== 'project'): ?>
                            <div class="ht-card-field col-span-2">
                                <span class="ht-label"><i class="fas fa-info-circle mr-1"></i>Keterangan</span>
                                <span class="ht-value text-xs"><?= htmlspecialchars($item['reason']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                            <?php 
                            $hasAnyPhoto = !empty($item['photo_proof_path']) || !empty($item['admin_photo_path']);
                            if ($hasAnyPhoto):
                                $thumbPhotos = [];
                                if (!empty($item['admin_photo_path'])) $thumbPhotos[] = $item['admin_photo_path'];
                                if (!empty($item['photo_proof_path'])) {
                                    $pp = json_decode($item['photo_proof_path'], true);
                                    if (!is_array($pp)) $pp = [$item['photo_proof_path']];
                                    $thumbPhotos = array_merge($thumbPhotos, $pp);
                                }
                            ?>
                            <div class="flex gap-1.5">
                                <?php foreach (array_slice($thumbPhotos, 0, 3) as $tp): 
                                    $tpSrc = $tp;
                                    if (!str_starts_with($tp, 'http')) $tpSrc = (defined('BASE_URL') ? BASE_URL : '..') . '/' . ltrim($tp, '/');
                                ?>
                                <img src="<?= htmlspecialchars($tpSrc) ?>" class="w-8 h-8 rounded-md object-cover border border-gray-200 dark:border-gray-600" onerror="this.style.display='none'">
                                <?php endforeach; ?>
                                <?php if (count($thumbPhotos) > 3): ?>
                                <div class="w-8 h-8 rounded-md bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-[10px] font-bold text-gray-500">+<?= count($thumbPhotos) - 3 ?></div>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div></div>
                            <?php endif; ?>
                            <span class="text-[10px] text-gray-400 italic"><i class="fas fa-expand-alt mr-1"></i>Tap untuk detail</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-100 dark:border-gray-700 text-xs text-gray-500">
                Menampilkan <?= count($company_data) ?> data
            </div>
            <?php endif; ?>
        </div>
    </div>

    
    <div id="panel-apd" class="ht-panel hidden">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            
            <div class="p-4 border-b border-gray-100 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <input type="text" id="searchApdHistory" placeholder="Cari APD / user..." oninput="filterApdTable()" class="flex-1 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100">
                </div>
            </div>

            <?php if (empty($apd_data)): ?>
            <div class="p-8 text-center text-gray-400 text-sm">Belum ada riwayat APD</div>
            <?php else: ?>
            <div class="divide-y divide-gray-100 dark:divide-gray-700" id="apdHistoryList">
                <?php foreach ($apd_data as $item):
                    $apdTypeLabels = [
                        'loan' => ['Peminjaman', 'text-blue-700 bg-blue-50 dark:text-blue-300 dark:bg-blue-900/40', 'fas fa-hand-holding'],
                        'return' => ['Pengembalian', 'text-green-700 bg-green-50 dark:text-green-300 dark:bg-green-900/40', 'fas fa-undo'],
                        'force_return' => ['Return Paksa', 'text-red-700 bg-red-50 dark:text-red-300 dark:bg-red-900/40', 'fas fa-exclamation-circle'],
                    ];
                    $tl = $apdTypeLabels[$item['permit_type']] ?? ['Unknown', 'text-gray-700 bg-gray-50', 'fas fa-question'];
                    $sl = $statusLabels[$item['status']] ?? ['Unknown', 'text-gray-700 bg-gray-50'];
                ?>
                <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-800/50 apd-history-row cursor-pointer hover:ring-2 hover:ring-teal-300 dark:hover:ring-teal-600" onclick="openHistoryDetail(<?= (int)$item['id'] ?>)">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1.5">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold <?= $tl[1] ?>">
                                    <i class="<?= $tl[2] ?>"></i> <?= $tl[0] ?>
                                </span>
                                <span class="font-bold text-sm text-gray-800 dark:text-gray-100"><?= htmlspecialchars($item['tool_name']) ?></span>
                                <span class="text-xs text-gray-400 font-mono"><?= htmlspecialchars($item['tool_code']) ?></span>
                            </div>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                <div>
                                    <span class="text-xs text-gray-400"><i class="fas fa-user mr-1"></i>PEMINJAM</span>
                                    <p class="font-medium text-gray-700 dark:text-gray-200"><?= htmlspecialchars($item['to_user']) ?></p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-400"><i class="fas fa-map-marker-alt mr-1"></i>LOKASI</span>
                                    <p class="text-gray-600 dark:text-gray-300"><?= htmlspecialchars($item['location'] ?? '-') ?></p>
                                </div>
                                <?php if ($item['permit_type'] !== 'loan'): ?>
                                <div>
                                    <span class="text-xs text-gray-400"><i class="fas fa-arrow-right mr-1"></i>DARI</span>
                                    <p class="text-gray-600 dark:text-gray-300"><?= htmlspecialchars($item['from_user']) ?></p>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <span class="text-xs text-gray-400"><i class="fas fa-calendar mr-1"></i>PERIODE</span>
                                    <p class="text-gray-600 dark:text-gray-300">
                                        <?= $item['start_date'] ? date('d M Y', strtotime($item['start_date'])) : '-' ?>
                                        <?= $item['end_date'] ? ' - ' . date('d M Y', strtotime($item['end_date'])) : '' ?>
                                    </p>
                                </div>
                                <?php if (!empty($item['reason'])): ?>
                                <div class="col-span-2">
                                    <span class="text-xs text-gray-400"><i class="fas fa-info-circle mr-1"></i>KETERANGAN</span>
                                    <p class="text-gray-600 dark:text-gray-300"><?= htmlspecialchars($item['reason']) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold <?= $sl[1] ?>"><?= $sl[0] ?></span>
                            <p class="text-xs text-gray-400 mt-1"><?= date('d M Y, H:i', strtotime($item['created_at'])) ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-100 dark:border-gray-700 text-xs text-gray-500">
                Menampilkan <?= count($apd_data) ?> data
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($role === 'administrator'): ?>
    
    <div id="panel-personal" class="ht-panel hidden">
        
        <?php
        
        $overdue_by_technician = [];
        foreach ($overdue_loans as $ol) {
            $tid = $ol['technician_id'];
            if (!isset($overdue_by_technician[$tid])) {
                $overdue_by_technician[$tid] = [
                    'id' => $tid,
                    'name' => $ol['technician_name'],
                    'count' => 0,
                ];
            }
            $overdue_by_technician[$tid]['count']++;
        }
        
        usort($overdue_by_technician, function($a, $b) { return $b['count'] - $a['count']; });
        ?>

        
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden mb-4">
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-2">
                    <i class="fas fa-exclamation-triangle text-amber-500"></i>
                    <span class="font-semibold text-sm text-gray-900 dark:text-white">Peminjaman Melebihi 3 Hari</span>
                    <?php if (!empty($overdue_loans)): ?>
                    <span class="inline-flex items-center justify-center px-2 py-0.5 text-[10px] font-bold bg-red-500 text-white rounded-full"><?= count($overdue_loans) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($overdue_by_technician)): ?>
                <div class="flex items-center gap-2">
                    <select id="filterOverdueTechnician" onchange="filterOverdueByTechnician()" class="h-[34px] px-3 rounded-lg border border-gray-200 dark:border-gray-600 text-xs bg-white dark:bg-gray-700 dark:text-gray-200">
                        <option value="all">Semua Teknisi</option>
                        <?php foreach ($overdue_by_technician as $tech): ?>
                        <option value="<?= (int)$tech['id'] ?>"><?= htmlspecialchars($tech['name']) ?> (<?= $tech['count'] ?> alat)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($overdue_by_technician)): ?>
            
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2 uppercase tracking-wide">Ringkasan per Teknisi</div>
                <div class="flex flex-wrap gap-2" id="overdueTechCards">
                    <?php foreach ($overdue_by_technician as $tech): ?>
                    <button type="button" onclick="selectOverdueTechnician(<?= (int)$tech['id'] ?>)" 
                        class="overdue-tech-card flex items-center gap-2 px-3 py-1.5 rounded-lg border text-xs font-medium transition-all cursor-pointer border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 hover:border-red-300 dark:hover:border-red-500" data-tech-id="<?= (int)$tech['id'] ?>">
                        <i class="fas fa-user-circle text-gray-400"></i>
                        <span class="text-gray-700 dark:text-gray-200"><?= htmlspecialchars($tech['name']) ?></span>
                        <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[10px] font-bold bg-red-500 text-white rounded-full"><?= $tech['count'] ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (empty($overdue_loans)): ?>
            <div class="px-6 py-10 text-center">
                <i class="fas fa-check-circle text-4xl text-green-400 mb-3 block"></i>
                <h3 class="text-base font-medium text-gray-500 dark:text-gray-400">Tidak ada peminjaman melebihi 3 hari</h3>
                <p class="text-gray-400 dark:text-gray-500 text-sm mt-1">Semua tools company telah dikembalikan tepat waktu</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="ht-table">
                    <thead>
                        <tr>
                            <th>No</th><th>ID User</th><th>Teknisi</th><th>Tools</th><th>Dipinjam</th><th>Keterlambatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overdue_loans as $idx => $ol): ?>
                        <tr class="overdue-row" data-technician-id="<?= (int)$ol['technician_id'] ?>">
                            <td class="text-gray-400 text-xs overdue-row-num"><?= $idx + 1 ?></td>
                            <td><span class="ht-user-id">#<?= $ol['technician_id'] ?></span></td>
                            <td class="font-medium text-gray-900 dark:text-white text-sm"><?= htmlspecialchars($ol['technician_name']) ?></td>
                            <td>
                                <div class="font-medium text-gray-900 dark:text-white text-sm"><?= htmlspecialchars($ol['tool_name']) ?></div>
                                <div class="text-xs text-gray-400"><?= htmlspecialchars($ol['tool_code']) ?></div>
                            </td>
                            <td class="text-xs text-gray-700 dark:text-gray-300"><?= $ol['borrowed_at'] ? date('d/m/Y H:i', strtotime($ol['borrowed_at'])) : '-' ?></td>
                            <td><span class="ht-badge text-red-700 bg-red-50 dark:text-red-300 dark:bg-red-900/40"><i class="fas fa-clock" style="font-size:10px"></i> <?= htmlspecialchars($ol['overdue_text']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-100 dark:border-gray-700 text-xs text-gray-500" id="overdueFooter">
                <?= count($overdue_loans) ?> tools melebihi batas peminjaman
            </div>
            <?php endif; ?>
        </div>

        
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-2">
                    <i class="fas fa-clipboard-check text-blue-500"></i>
                    <span class="font-semibold text-sm text-gray-900 dark:text-white">Pengecekan Tools Bulanan — <?= date('F Y') ?></span>
                </div>
                <a href="dashboard.php?page=check-monthly-tools" class="ht-btn ht-btn-green text-xs">
                    <i class="fas fa-external-link-alt"></i> Buka Halaman Pengecekan
                </a>
            </div>
            <?php if (empty($monthly_check_overview)): ?>
            <div class="px-6 py-10 text-center">
                <i class="fas fa-clipboard-list text-4xl text-gray-300 dark:text-gray-600 mb-3 block"></i>
                <h3 class="text-base font-medium text-gray-500 dark:text-gray-400">Belum ada data pengecekan</h3>
                <p class="text-gray-400 dark:text-gray-500 text-sm mt-1">Belum ada teknisi yang memiliki tools personal</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="ht-table">
                    <thead>
                        <tr>
                            <th>No</th><th>ID</th><th>Nama Teknisi</th><th>Username</th><th>Total Tools</th><th>Sudah Dicek</th><th>Status</th><th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly_check_overview as $idx => $mc): 
                            $is_done = !empty($mc['checked_at']);
                            $checked = (int)$mc['checked_tools'];
                            $total = (int)$mc['total_tools'];
                            $pct = $total > 0 ? round(($checked / $total) * 100) : 0;
                        ?>
                        <tr>
                            <td class="text-gray-400 text-xs"><?= $idx + 1 ?></td>
                            <td><span class="ht-user-id">#<?= $mc['user_id'] ?></span></td>
                            <td class="font-medium text-gray-900 dark:text-white text-sm"><?= htmlspecialchars($mc['full_name']) ?></td>
                            <td class="text-xs text-gray-500"><?= htmlspecialchars($mc['username']) ?></td>
                            <td class="text-center font-semibold"><?= $total ?></td>
                            <td class="text-center"><?= $checked ?> / <?= $total ?></td>
                            <td>
                                <?php if ($is_done): ?>
                                <span class="ht-badge text-green-700 bg-green-50 dark:text-green-300 dark:bg-green-900/40"><i class="fas fa-check-circle" style="font-size:10px"></i> Selesai</span>
                                <?php elseif ($checked > 0): ?>
                                <span class="ht-badge text-yellow-700 bg-yellow-50 dark:text-yellow-300 dark:bg-yellow-900/40"><i class="fas fa-spinner" style="font-size:10px"></i> Proses (<?= $pct ?>%)</span>
                                <?php else: ?>
                                <span class="ht-badge text-gray-600 bg-gray-100 dark:text-gray-400 dark:bg-gray-700"><i class="fas fa-clock" style="font-size:10px"></i> Belum Dicek</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="dashboard.php?page=check-monthly-tools&user_id=<?= $mc['user_id'] ?>&month=<?= date('Y-m') ?>" class="text-blue-600 hover:underline text-xs font-medium">
                                    <i class="fas fa-arrow-right"></i> Cek
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-100 dark:border-gray-700 text-xs text-gray-500">
                <?php 
                    $done_count = count(array_filter($monthly_check_overview, function($m) { return !empty($m['checked_at']); }));
                    $total_staff = count($monthly_check_overview);
                ?>
                <?= $done_count ?> / <?= $total_staff ?> teknisi selesai dicek bulan ini
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>


<div id="historyDetailModal" class="fixed inset-0 z-[9999] hidden" style="background:rgba(0,0,0,0.5);backdrop-filter:blur(4px);">
  <div class="flex items-center justify-center min-h-screen p-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-lg w-full shadow-2xl overflow-hidden max-h-[90vh] flex flex-col" id="historyDetailCard">
      
      <div id="hdHeader" class="p-5 border-b border-gray-100 dark:border-gray-700"></div>
      
      <div id="hdBody" class="p-5 overflow-y-auto flex-1"></div>
      
      <div class="px-5 py-3 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-100 dark:border-gray-700 flex justify-end">
        <button onclick="closeHistoryDetail()" class="px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">Tutup</button>
      </div>
    </div>
  </div>
</div>


<div id="htLightbox" class="fixed inset-0 z-[10000] hidden" style="background:rgba(0,0,0,0.85);" onclick="this.classList.add('hidden')">
  <div class="flex items-center justify-center min-h-screen p-4">
    <img id="htLightboxImg" src="" class="max-w-full max-h-[90vh] rounded-lg shadow-2xl" onclick="event.stopPropagation()">
  </div>
  <button onclick="document.getElementById('htLightbox').classList.add('hidden')" class="absolute top-4 right-4 text-white text-2xl bg-black/50 rounded-full w-10 h-10 flex items-center justify-center hover:bg-black/70"><i class="fas fa-times"></i></button>
</div>

<?php

$allPermits = [];
foreach ($company_data as $d) {
    $d['_tab'] = ($d['permit_type'] === 'project') ? 'project' : 'tools';
    $allPermits[$d['id']] = $d;
}
foreach ($apd_data as $d) { $d['_tab'] = 'apd'; $allPermits[$d['id']] = $d; }
?>

<script>
var _htPermits = <?= json_encode($allPermits, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var _htBaseUrl = '<?= defined("BASE_URL") ? BASE_URL : ".." ?>';

function _htResolvePhoto(p) {
  if (!p) return null;
  if (p.startsWith('http://') || p.startsWith('https://')) return p;
  return _htBaseUrl + '/' + p.replace(/^\/?/, '');
}

function openHistoryDetail(permitId) {
  var d = _htPermits[permitId];
  if (!d) return;

  var typeMap = {
    'loan': { label:'Peminjaman', color:'#3b82f6', icon:'fas fa-hand-holding', bg:'#dbeafe', text:'#1e40af' },
    'handover': { label:'Serah Terima', color:'#8b5cf6', icon:'fas fa-exchange-alt', bg:'#ede9fe', text:'#5b21b6' },
    'return': { label:'Pengembalian', color:'#10b981', icon:'fas fa-undo', bg:'#d1fae5', text:'#065f46' },
    'force_return': { label:'Return Paksa', color:'#ef4444', icon:'fas fa-exclamation-circle', bg:'#fee2e2', text:'#991b1b' },
    'project': { label:'Project', color:'#f59e0b', icon:'fas fa-project-diagram', bg:'#fef3c7', text:'#92400e' }
  };
  var statusMap = {
    'pending': { label:'Menunggu', bg:'#fef3c7', text:'#92400e' },
    'approved': { label:'Disetujui', bg:'#d1fae5', text:'#065f46' },
    'rejected': { label:'Ditolak', bg:'#fee2e2', text:'#991b1b' }
  };
  var t = typeMap[d.permit_type] || { label:d.permit_type, color:'#6b7280', icon:'fas fa-question', bg:'#f3f4f6', text:'#374151' };
  var s = statusMap[d.status] || { label:d.status, bg:'#f3f4f6', text:'#374151' };

  var toolPhotoHtml = '';
  var tp = d.tool_photo ? _htResolvePhoto(d.tool_photo) : null;
  if (tp) {
    toolPhotoHtml = '<img src="' + tp + '" class="w-14 h-14 rounded-xl object-cover border-2 border-gray-200 dark:border-gray-600 cursor-pointer" onclick="htShowLightbox(this.src)" onerror="this.style.display=\'none\'">';
  } else {
    toolPhotoHtml = '<div class="w-14 h-14 rounded-xl bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-400 text-xl"><i class="fas fa-wrench"></i></div>';
  }

  document.getElementById('hdHeader').innerHTML = 
    '<div class="flex items-start gap-4">' +
      toolPhotoHtml +
      '<div class="flex-1 min-w-0">' +
        '<div class="flex items-center gap-2 flex-wrap">' +
          '<span style="background:' + t.bg + ';color:' + t.text + ';" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold"><i class="' + t.icon + '" style="font-size:10px"></i>' + t.label + '</span>' +
          '<span style="background:' + s.bg + ';color:' + s.text + ';" class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold">' + s.label + '</span>' +
        '</div>' +
        '<h3 class="font-bold text-gray-900 dark:text-white text-lg mt-1 truncate">' + _htEsc(d.tool_name) + '</h3>' +
        '<span class="text-xs text-gray-400 font-mono">' + _htEsc(d.tool_code) + '</span>' +
      '</div>' +
      '<button onclick="closeHistoryDetail()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-xl shrink-0"><i class="fas fa-times"></i></button>' +
    '</div>';

  var body = '';

  body += '<div class="grid grid-cols-2 gap-3 mb-4">';
  if (d.permit_type === 'project') {
    body += _htField('fas fa-user', 'Pengaju / Peminjam', d.to_user || d.requester);
    body += _htField('fas fa-user-tie', 'PIC', d.reason || d.pic_name || '-');
  } else {
    body += _htField('fas fa-user', 'Peminjam', d.to_user);
    body += _htField('fas fa-arrow-right', 'Dari', d.from_user || '-');
  }
  body += _htField('fas fa-map-marker-alt', 'Lokasi', d.location || '-');
  var periode = '-';
  if (d.start_date && d.end_date) {
    periode = _htFmtDate(d.start_date) + ' — ' + _htFmtDate(d.end_date);
  }
  body += _htField('fas fa-calendar-alt', 'Periode', periode);
  body += '</div>';

  if (d.reason && d.permit_type !== 'project') {
    body += '<div class="mb-4 p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">' +
      '<div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1"><i class="fas fa-info-circle mr-1"></i>KETERANGAN</div>' +
      '<div class="text-sm text-gray-700 dark:text-gray-200">' + _htEsc(d.reason) + '</div>' +
    '</div>';
  }

  if (d.status === 'approved' || d.approved_by_name) {
    body += '<div class="mb-4 p-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">' +
      '<div class="text-xs font-semibold text-green-700 dark:text-green-400 mb-1"><i class="fas fa-check-circle mr-1"></i>DISETUJUI OLEH</div>' +
      '<div class="text-sm font-medium text-green-800 dark:text-green-300">' + _htEsc(d.approved_by_name || '-') + '</div>' +
      (d.approved_at ? '<div class="text-xs text-green-600 dark:text-green-500 mt-0.5">' + _htFmtDateTime(d.approved_at) + '</div>' : '') +
    '</div>';
  }

  body += '<div class="mb-4">' +
    '<div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2 uppercase tracking-wide"><i class="fas fa-clock mr-1"></i>TIMELINE</div>' +
    '<div class="space-y-2">';
  body += _htTimeline('fas fa-plus-circle', 'text-blue-500', 'Pengajuan dibuat', _htFmtDateTime(d.created_at));
  if (d.approved_at && d.status === 'approved') {
    body += _htTimeline('fas fa-check-circle', 'text-green-500', 'Disetujui' + (d.approved_by_name ? ' oleh ' + _htEsc(d.approved_by_name) : ''), _htFmtDateTime(d.approved_at));
  } else if (d.status === 'rejected') {
    body += _htTimeline('fas fa-times-circle', 'text-red-500', 'Ditolak', d.approved_at ? _htFmtDateTime(d.approved_at) : '-');
  } else if (d.status === 'pending') {
    body += _htTimeline('fas fa-hourglass-half', 'text-amber-500', 'Menunggu persetujuan', '-');
  }
  body += '</div></div>';

  var hasPhotos = false;
  var photoHtml = '<div class="mb-2"><div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2 uppercase tracking-wide"><i class="fas fa-images mr-1"></i>FOTO DOKUMENTASI</div><div class="grid grid-cols-3 gap-2">';
  
  if (d.admin_photo_path) {
    var adminSrc = _htResolvePhoto(d.admin_photo_path);
    if (adminSrc) {
      hasPhotos = true;
      photoHtml += '<div class="relative group">' +
        '<img src="' + adminSrc + '" class="w-full h-24 object-cover rounded-lg border border-gray-200 dark:border-gray-600 cursor-pointer hover:opacity-80 transition-opacity" onclick="htShowLightbox(this.src)" onerror="this.parentElement.style.display=\'none\'">' +
        '<div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent rounded-b-lg px-2 py-1">' +
          '<span class="text-[10px] font-semibold text-white"><i class="fas fa-shield-alt mr-1"></i>Verifikasi Admin</span>' +
        '</div>' +
      '</div>';
    }
  }

  if (d.photo_proof_path) {
    var proofs = [];
    try { proofs = JSON.parse(d.photo_proof_path); } catch(e) { proofs = [d.photo_proof_path]; }
    if (!Array.isArray(proofs)) proofs = [proofs];
    proofs.forEach(function(p, idx) {
      var src = _htResolvePhoto(p);
      if (src) {
        hasPhotos = true;
        photoHtml += '<div class="relative group">' +
          '<img src="' + src + '" class="w-full h-24 object-cover rounded-lg border border-gray-200 dark:border-gray-600 cursor-pointer hover:opacity-80 transition-opacity" onclick="htShowLightbox(this.src)" onerror="this.parentElement.style.display=\'none\'">' +
          '<div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent rounded-b-lg px-2 py-1">' +
            '<span class="text-[10px] font-semibold text-white"><i class="fas fa-camera mr-1"></i>Bukti ' + (idx + 1) + '</span>' +
          '</div>' +
        '</div>';
      }
    });
  }

  photoHtml += '</div></div>';
  
  if (hasPhotos) {
    body += photoHtml;
  } else {
    body += '<div class="text-center py-4 text-gray-400 dark:text-gray-500 text-sm"><i class="fas fa-image text-2xl mb-2 block"></i>Tidak ada foto dokumentasi</div>';
  }

  document.getElementById('hdBody').innerHTML = body;
  document.getElementById('historyDetailModal').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeHistoryDetail() {
  document.getElementById('historyDetailModal').classList.add('hidden');
  document.body.style.overflow = '';
}

function htShowLightbox(src) {
  document.getElementById('htLightboxImg').src = src;
  document.getElementById('htLightbox').classList.remove('hidden');
}

function _htEsc(s) { 
  if (!s) return '';
  var el = document.createElement('span');
  el.textContent = s;
  return el.innerHTML;
}

function _htFmtDate(d) {
  if (!d) return '-';
  var dt = new Date(d);
  return dt.toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' });
}

function _htFmtDateTime(d) {
  if (!d) return '-';
  var dt = new Date(d);
  return dt.toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' }) + ', ' + dt.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' });
}

function _htField(icon, label, value) {
  return '<div class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-700/50">' +
    '<div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-0.5"><i class="' + icon + ' mr-1"></i>' + label + '</div>' +
    '<div class="text-sm font-medium text-gray-800 dark:text-gray-100 truncate">' + _htEsc(value) + '</div>' +
  '</div>';
}

function _htTimeline(icon, color, text, time) {
  return '<div class="flex items-start gap-3">' +
    '<i class="' + icon + ' ' + color + ' mt-0.5"></i>' +
    '<div class="flex-1">' +
      '<div class="text-sm text-gray-700 dark:text-gray-200">' + text + '</div>' +
      '<div class="text-xs text-gray-400">' + time + '</div>' +
    '</div>' +
  '</div>';
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeHistoryDetail();
    document.getElementById('htLightbox').classList.add('hidden');
  }
});
document.getElementById('historyDetailModal').addEventListener('click', function(e) {
  if (e.target === this || e.target === this.firstElementChild) closeHistoryDetail();
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.ht-tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tab = this.dataset.tab;
            document.querySelectorAll('.ht-tab-btn').forEach(function(b) {
                b.classList.remove('bg-blue-600', 'text-white');
                b.classList.add('text-gray-500', 'dark:text-gray-400');
            });
            this.classList.add('bg-blue-600', 'text-white');
            this.classList.remove('text-gray-500', 'dark:text-gray-400');
            document.querySelectorAll('.ht-panel').forEach(function(p) { p.classList.add('hidden'); });
            var target = document.getElementById('panel-' + tab);
            if (target) target.classList.remove('hidden');
        });
    });
});

function applyCompanyFilters() {
    var month = document.getElementById('filterMonth').value;
    var type = document.getElementById('filterType').value;
    var url = new URL(window.location.href);
    url.searchParams.set('page', 'tool-history');
    url.searchParams.set('month', month);
    url.searchParams.set('type', type);
    window.location.href = url.toString();
}

function filterCompanyTable() {
    var q = (document.getElementById('searchCompanyHistory').value || '').toLowerCase();
    document.querySelectorAll('.company-row').forEach(function(row) {
        row.style.display = row.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
    });
}

function filterApdTable() {
    var q = (document.getElementById('searchApdHistory').value || '').toLowerCase();
    document.querySelectorAll('.apd-history-row').forEach(function(row) {
        row.style.display = row.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
    });
}

function filterOverdueByTechnician() {
    var sel = document.getElementById('filterOverdueTechnician');
    if (!sel) return;
    var val = sel.value;
    var rows = document.querySelectorAll('.overdue-row');
    var num = 0;
    rows.forEach(function(row) {
        if (val === 'all' || row.dataset.technicianId === val) {
            num++;
            row.style.display = '';
            row.querySelector('.overdue-row-num').textContent = num;
        } else {
            row.style.display = 'none';
        }
    });
    var footer = document.getElementById('overdueFooter');
    if (footer) {
        var techName = sel.options[sel.selectedIndex].text;
        if (val === 'all') {
            footer.textContent = num + ' tools melebihi batas peminjaman';
        } else {
            footer.textContent = num + ' tools terlambat dikembalikan oleh ' + techName.replace(/ \(\d+ alat\)$/, '');
        }
    }
    document.querySelectorAll('.overdue-tech-card').forEach(function(card) {
        card.classList.remove('border-red-500', 'bg-red-50', 'dark:bg-red-900/30', 'dark:border-red-500');
        card.classList.add('border-gray-200', 'dark:border-gray-600', 'bg-gray-50', 'dark:bg-gray-700');
        if (card.dataset.techId === val) {
            card.classList.remove('border-gray-200', 'dark:border-gray-600', 'bg-gray-50', 'dark:bg-gray-700');
            card.classList.add('border-red-500', 'bg-red-50', 'dark:bg-red-900/30', 'dark:border-red-500');
        }
    });
}

function selectOverdueTechnician(techId) {
    var sel = document.getElementById('filterOverdueTechnician');
    if (!sel) return;
    var newVal = String(techId);
    if (sel.value === newVal) {
        sel.value = 'all';
    } else {
        sel.value = newVal;
    }
    filterOverdueByTechnician();
}

function exportExcel(tab) {
    var month = document.getElementById('filterMonth') ? document.getElementById('filterMonth').value : 'all';
    var type = (tab === 'company' || tab === 'tools')
        ? (document.getElementById('filterType') ? document.getElementById('filterType').value : 'all')
        : 'all';
    var exportTab = (tab === 'tools') ? 'company' : tab;
    
    var params = 'export=excel&tab=' + encodeURIComponent(exportTab) + '&month=' + encodeURIComponent(month) + '&type=' + encodeURIComponent(type);
    window.open('app/dashboard/history-tools.php?' + params, '_blank');
}
</script>
