<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
@date_default_timezone_set('Asia/Jakarta');

$stmt = $pdo->prepare("SELECT id, full_name, username, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);


if (in_array($user['role'], ['technician', 'hse', 'staff'])) {
    try {
        $pdo->prepare("UPDATE material_requests SET sales_edit_read = 1 WHERE user_id = ? AND sales_edit_read = 0 AND sales_edited_at IS NOT NULL")->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {}
}

$isDriverOnly = false;
try {
    $stmtDiv = $pdo->prepare("SELECT LOWER(d.name) as div_name FROM user_divisions ud JOIN divisions d ON d.id = ud.division_id WHERE ud.user_id = ? AND d.is_active = 1");
    $stmtDiv->execute([$_SESSION['user_id']]);
    $userDivNames = array_column($stmtDiv->fetchAll(PDO::FETCH_ASSOC), 'div_name');
    $isDriverOnly = in_array('driver', $userDivNames) && !in_array('technician', $userDivNames);
} catch (Exception $e) {}

$userView = $user['role'];
if ($userView === 'staff') {
    $userView = 'technician';
} elseif ($userView === 'admin') {
    $userView = 'administrator';
}
if ($user['role'] === 'hse') {
    $userView = 'technician';
}
if ($isDriverOnly && !in_array($user['role'], ['administrator', 'direktur', 'admin'])) {
    $userView = 'driver';
}


$suppliersList = [];
try {
    $stmtSup = $pdo->prepare("SELECT id, name, address, phone FROM suppliers WHERE is_active = 1 ORDER BY name");
    $stmtSup->execute();
    $suppliersList = $stmtSup->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$projects = [];
$allProjects = [];
$doneProjectIds = [];
$salesDbError = null;
try {
    $salesConfigPath = __DIR__ . '/../config/database_sales.php';
    if (!is_readable($salesConfigPath)) {
        throw new RuntimeException('File app/config/database_sales.php belum ada. Salin dari database_sales.php.example');
    }
    $salesConfig = (function () use ($salesConfigPath) {
        $host = $dbname = $username = $password = null;
        require $salesConfigPath;
        return compact('host', 'dbname', 'username', 'password');
    })();
    $salesPdo = new PDO(
        "mysql:host={$salesConfig['host']};dbname={$salesConfig['dbname']};charset=utf8mb4",
        $salesConfig['username'],
        $salesConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $stmt = $salesPdo->prepare("SELECT id, project_name, status, customer_name FROM projects ORDER BY project_name");
    $stmt->execute();
    $allProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $projects = array_values(array_filter($allProjects, fn($p) => strtolower($p['status']) === 'ongoing'));
    $doneProjectIds = array_map(fn($p) => $p['id'], array_filter($allProjects, fn($p) => strtolower($p['status']) === 'done'));
} catch (Throwable $e) {
    $salesDbError = $e->getMessage();
    error_log("Sales DB Error: " . $salesDbError);
}

function getRequestItems($pdo, $request_id) {
    $stmt = $pdo->prepare("SELECT * FROM material_request_items WHERE request_id = ?");
    $stmt->execute([$request_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getRequestApproval($pdo, $request_id) {
    $stmt = $pdo->prepare("SELECT * FROM material_request_approvals WHERE request_id = ?");
    $stmt->execute([$request_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
function getProjectName($allProjects, $project_id) {
    foreach ($allProjects as $proj) { if ($proj['id'] == $project_id) return $proj['project_name']; }
    return 'Unknown';
}
function groupByProject($requests, $allProjects) {
    $groups = [];
    foreach ($requests as $req) {
        $pid = $req['project_id'];
        if (!isset($groups[$pid])) $groups[$pid] = ['project_name' => getProjectName($allProjects, $pid), 'requests' => []];
        $groups[$pid]['requests'][] = $req;
    }
    return $groups;
}

$statusLabels = [
    'pending' => ['label'=>'Menunggu Sales','icon'=>'<i class="fas fa-clock"></i>','badge'=>'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200','bar'=>'bg-amber-400'],
    'sales_approved' => ['label'=>'Menunggu Admin','icon'=>'<i class="fas fa-clipboard-list"></i>','badge'=>'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200','bar'=>'bg-blue-400'],
    'admin_review' => ['label'=>'Admin Review','icon'=>'<i class="fas fa-search"></i>','badge'=>'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200','bar'=>'bg-indigo-400'],
    'admin_approved' => ['label'=>'Siap Kirim','icon'=>'<i class="fas fa-check-circle"></i>','badge'=>'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200','bar'=>'bg-emerald-500'],
    'driver_pickup' => ['label'=>'Dalam Perjalanan','icon'=>'<i class="fas fa-truck"></i>','badge'=>'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-200','bar'=>'bg-purple-400'],
    'delivered' => ['label'=>'Delivered','icon'=>'<i class="fas fa-box"></i>','badge'=>'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200','bar'=>'bg-green-500'],
    'completed' => ['label'=>'Selesai','icon'=>'<i class="fas fa-check-circle"></i>','badge'=>'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200','bar'=>'bg-emerald-600'],
    'rejected' => ['label'=>'Ditolak','icon'=>'<i class="fas fa-times-circle"></i>','badge'=>'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200','bar'=>'bg-red-500'],
    'under_review' => ['label'=>'Under Review','icon'=>'<i class="fas fa-search"></i>','badge'=>'bg-blue-100 text-blue-800','bar'=>'bg-blue-400'],
    'approved' => ['label'=>'Approved','icon'=>'<i class="fas fa-check-circle"></i>','badge'=>'bg-emerald-100 text-emerald-800','bar'=>'bg-emerald-500'],
];

$techActiveRequests = [];
$techHistoryGrouped = [];
$techReturns = [];
$adminPendingRequests = [];
$adminReviewRequests = [];
$adminHistoryRequests = [];
$adminDoRequests = [];
$adminReturns = [];
$driverActiveGrouped = [];
$driverHistoryGrouped = [];
$driverActiveCount = 0;

if ($userView === 'technician') {
    try {
        $stmt = $pdo->prepare("SELECT mr.*, u.full_name as requester_name FROM material_requests mr JOIN users u ON mr.user_id = u.id WHERE mr.user_id = ? ORDER BY mr.created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        $allReqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $allReqs = [];
        error_log('Material requests load error: ' . $e->getMessage());
    }
    $techActiveRequests = array_values(array_filter($allReqs, fn($r) => !in_array($r['status'], ['completed','rejected'])));
    $histReqs = array_values(array_filter($allReqs, fn($r) => in_array($r['status'], ['completed','rejected']) && !in_array($r['project_id'], $doneProjectIds)));
    $techHistoryGrouped = groupByProject($histReqs, $allProjects);

    
    try {
        $retStmt = $pdo->prepare("SELECT mr.*, u.full_name as requester_name FROM material_returns mr JOIN users u ON mr.user_id = u.id WHERE mr.user_id = ? ORDER BY mr.created_at DESC");
        $retStmt->execute([$_SESSION['user_id']]);
        $techReturns = $retStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($techReturns as &$ret) {
            $riStmt = $pdo->prepare("SELECT * FROM material_return_items WHERE return_id = ?");
            $riStmt->execute([$ret['id']]);
            $ret['items'] = $riStmt->fetchAll(PDO::FETCH_ASSOC);
            $ret['project_name'] = '';
            foreach ($allProjects as $p) { if ($p['id'] == $ret['project_id']) { $ret['project_name'] = $p['project_name']; break; } }
            if (!$ret['project_name']) $ret['project_name'] = 'Unknown';
        }
        unset($ret);
    } catch (Exception $e) {
        $techReturns = [];
    }

} elseif ($userView === 'administrator') {
    
    $stmt = $pdo->prepare("SELECT mr.*, u.full_name as requester_name FROM material_requests mr JOIN users u ON mr.user_id = u.id WHERE mr.status IN ('sales_approved','admin_review') ORDER BY mr.created_at ASC");
    $stmt->execute();
    $adminReviewRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $adminPendingRequests = []; 

    $stmt = $pdo->prepare("SELECT mr.*, u.full_name as requester_name FROM material_requests mr JOIN users u ON mr.user_id = u.id WHERE mr.status IN ('admin_approved','driver_pickup','delivered','completed','rejected','approved') ORDER BY mr.updated_at DESC LIMIT 50");
    $stmt->execute();
    $adminHistoryRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $adminHistoryGrouped = groupByProject($adminHistoryRequests, $allProjects);

    $stmt = $pdo->prepare("SELECT mr.*, u.full_name as requester_name FROM material_requests mr JOIN users u ON mr.user_id = u.id WHERE mr.status IN ('admin_approved','driver_pickup','delivered','completed') ORDER BY mr.admin_approved_at DESC, mr.updated_at DESC LIMIT 100");
    $stmt->execute();
    $adminDoRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    
    try {
        $retStmt = $pdo->prepare("SELECT mr.*, u.full_name as requester_name FROM material_returns mr JOIN users u ON mr.user_id = u.id ORDER BY mr.created_at DESC");
        $retStmt->execute();
        $adminReturns = $retStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($adminReturns as &$ret) {
            $riStmt = $pdo->prepare("SELECT * FROM material_return_items WHERE return_id = ?");
            $riStmt->execute([$ret['id']]);
            $ret['items'] = $riStmt->fetchAll(PDO::FETCH_ASSOC);
            $ret['project_name'] = '';
            foreach ($allProjects as $p) { if ($p['id'] == $ret['project_id']) { $ret['project_name'] = $p['project_name']; break; } }
            if (!$ret['project_name']) $ret['project_name'] = 'Unknown';
        }
        unset($ret);
    } catch (Exception $e) {
        $adminReturns = [];
    }

} elseif ($userView === 'driver') {
    $stmt = $pdo->prepare("SELECT mr.*, u.full_name as requester_name FROM material_requests mr JOIN users u ON mr.user_id = u.id WHERE mr.status IN ('admin_approved','driver_pickup','delivered','completed') AND (mr.driver_pickup_by = ? OR mr.driver_pickup_by IS NULL) ORDER BY mr.created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $allDriverReqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allDriverReqs = array_values(array_filter($allDriverReqs, fn($r) => !in_array($r['project_id'], $doneProjectIds)));
    $activeReqs = array_values(array_filter($allDriverReqs, fn($r) => in_array($r['status'], ['admin_approved','driver_pickup'])));
    $histReqs = array_values(array_filter($allDriverReqs, fn($r) => in_array($r['status'], ['delivered','completed'])));
    $driverActiveGrouped = groupByProject($activeReqs, $allProjects);
    $driverHistoryGrouped = groupByProject($histReqs, $allProjects);
    $driverActiveCount = count($activeReqs);
}
?>

<div class="max-w-6xl mx-auto px-4 py-5 sm:px-6">
    <div class="mb-5">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2"><i class="fas fa-boxes"></i> Request Material</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            <?php if ($userView === 'technician'): ?>Request material untuk project
            <?php elseif ($userView === 'administrator'): ?>Sediakan material untuk request
            <?php elseif ($userView === 'driver'): ?>Pickup &amp; delivery material
            <?php else: ?>Modul request material<?php endif; ?>
        </p>
    </div>

    <?php if ($salesDbError): ?>
    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-800 px-4 py-3 text-sm text-amber-800 dark:text-amber-200">
        <strong>Database project belum siap.</strong> Salin <code>app/config/database_sales.php.example</code> ke <code>database_sales.php</code>, lalu jalankan <code>.\scripts\seed-dummy-db.ps1</code>.
        <span class="block mt-1 text-xs opacity-80"><?= htmlspecialchars($salesDbError) ?></span>
    </div>
    <?php endif; ?>

<?php if ($userView === 'technician'): ?>



<div class="flex gap-1.5 mb-5 bg-gray-100 dark:bg-gray-700/50 p-1 rounded-xl">
    <button class="view-tab flex-1 flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg text-xs font-bold transition bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 shadow-sm" data-tab="tech-request">
        <i class="fas fa-clipboard-list"></i> Request <span class="bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200 px-1.5 py-0.5 rounded-full text-[10px] ml-0.5"><?= count($techActiveRequests) ?></span>
    </button>
    <button class="view-tab flex-1 flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg text-xs font-bold transition text-gray-500 dark:text-gray-400" data-tab="tech-history">
        <i class="fas fa-folder-open"></i> Riwayat
    </button>
    <button class="view-tab flex-1 flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg text-xs font-bold transition text-gray-500 dark:text-gray-400" data-tab="tech-return">
        <i class="fas fa-undo-alt"></i> Pengembalian <?php $techRetPending = count(array_filter($techReturns, fn($r) => $r['status'] === 'pending')); if ($techRetPending > 0): ?><span class="bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded-full text-[10px] ml-0.5"><?= $techRetPending ?></span><?php endif; ?>
    </button>
</div>


<div class="view-tab-content" id="tech-request">
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    
    <div class="lg:col-span-1">
    
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 mb-5 lg:mb-0 overflow-hidden">
        <button type="button" id="toggleFormBtn" class="w-full flex items-center justify-between px-4 py-3.5 text-left active:bg-gray-50 dark:active:bg-gray-700/50 transition">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100">Buat Request Baru</h2>
                    <p class="text-xs text-gray-400 dark:text-gray-500">Tap untuk buka form</p>
                </div>
            </div>
            <svg id="toggleFormIcon" class="w-5 h-5 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div id="requestFormSection" class="hidden">
            <form id="requestForm" method="POST" action="./app/action/handle-material-request.php" class="border-t border-gray-100 dark:border-gray-700 p-4">
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">Project</label>
                    <select id="project_id" name="project_id" required class="w-full px-3.5 py-3 border-2 border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-sm text-gray-800 dark:text-gray-100 focus:border-blue-500 focus:outline-none">
                        <option value="">Pilih project...</option>
                        <?php foreach ($projects as $project): ?>
                        <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($projects)): ?>
                    <p class="mt-1.5 text-xs text-amber-600 dark:text-amber-400"><i class="fas fa-exclamation-triangle"></i> Tidak ada project ongoing</p>
                    <?php endif; ?>
                </div>
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">Material</label>
                    <div id="materialsContainer" class="bg-gray-50 dark:bg-gray-700/50 rounded-xl overflow-hidden divide-y divide-gray-100 dark:divide-gray-600">
                        <div class="material-row p-3">
                            <div class="flex items-center gap-2">
                                <input type="text" name="material_names[]" required placeholder="Nama material" class="flex-1 min-w-0 px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg text-sm text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:outline-none">
                                <input type="number" name="quantities[]" required min="1" placeholder="Qty" inputmode="numeric" class="w-[70px] px-2 py-2.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg text-sm text-center text-gray-800 dark:text-gray-100 focus:border-blue-500 focus:outline-none">
                                <select name="units[]" class="w-[80px] px-1.5 py-2.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg text-xs text-gray-800 dark:text-gray-100 focus:border-blue-500 focus:outline-none">
                                    <option value="PCS">PCS</option>
                                    <option value="BOX">BOX</option>
                                    <option value="UNIT">UNIT</option>
                                    <option value="SET">SET</option>
                                    <option value="ROLL">ROLL</option>
                                    <option value="MTR">MTR</option>
                                    <option value="KG">KG</option>
                                    <option value="LTR">LTR</option>
                                    <option value="BTG">BTG</option>
                                    <option value="LBR">LBR</option>
                                </select>
                                <button type="button" class="remove-material hidden w-10 h-10 shrink-0 flex items-center justify-center text-red-400 hover:text-red-600 rounded-lg transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <textarea name="notes[]" placeholder="Catatan (opsional)" rows="1" class="mt-2 w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg text-xs text-gray-600 dark:text-gray-300 placeholder-gray-400 focus:border-blue-500 focus:outline-none resize-none"></textarea>
                        </div>
                    </div>
                    <button type="button" id="addMaterialBtn" class="mt-2 w-full py-2.5 border-2 border-dashed border-gray-200 dark:border-gray-600 rounded-xl text-sm font-medium text-gray-400 hover:text-blue-500 hover:border-blue-400 transition flex items-center justify-center gap-1.5 active:bg-gray-50 dark:active:bg-gray-700/50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Tambah Material
                    </button>
                </div>
                <button type="submit" id="submitRequestBtn" class="w-full py-3.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-sm font-bold rounded-xl transition shadow-sm">Kirim Request</button>
            </form>
        </div>
    </div>
    </div>

    
    <div class="lg:col-span-2">
    
    <div class="flex gap-2 mb-4">
        <div class="flex-1 relative">
            <svg class="absolute left-3 top-0 bottom-0 my-auto w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" id="techSearchInput" placeholder="Cari..." class="w-full pl-9 pr-3 py-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl text-sm text-gray-800 dark:text-gray-100 focus:border-blue-500 focus:outline-none">
        </div>
        <select id="techStatusFilter" class="px-3 py-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl text-sm text-gray-800 dark:text-gray-100 focus:border-blue-500 focus:outline-none min-w-[110px]">
            <option value="all">Semua</option>
            <option value="pending">Pending</option>
            <option value="sales_approved">Sales Approved</option>
            <option value="admin_review">Review</option>
            <option value="admin_approved">Siap</option>
            <option value="driver_pickup">Kirim</option>
            <option value="delivered">Sampai</option>
        </select>
    </div>

    <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-bold text-gray-700 dark:text-gray-300">Request Aktif</h2>
        <span class="text-xs text-gray-400" id="techResultCount"><?= count($techActiveRequests) ?> request</span>
    </div>

    <?php if (count($techActiveRequests) > 0): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3" id="techRequestList">
        <?php foreach ($techActiveRequests as $req): ?>
        <?php
            $items = getRequestItems($pdo, $req['id']);
            $approval = getRequestApproval($pdo, $req['id']);
            $projectName = getProjectName($allProjects, $req['project_id']);
            $sc = $statusLabels[$req['status']] ?? $statusLabels['pending'];
            $materialNames = array_map(fn($i) => $i['material_name'], $items);
            $pickupItems = array_map(function ($i) {
                $qty = (int)($i['quantity'] ?? 0);
                return [
                    'id' => (int)($i['id'] ?? 0),
                    'name' => (string)($i['material_name'] ?? ''),
                    'qty' => $qty,
                    'half' => max(1, (int)ceil($qty / 2)),
                ];
            }, $items);
            $pickupItemsJson = htmlspecialchars(json_encode($pickupItems), ENT_QUOTES, 'UTF-8');
        ?>
        <div class="tech-request-card bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden transition hover:shadow-md"
             data-status="<?= $req['status'] ?>" data-project="<?= htmlspecialchars(strtolower($projectName)) ?>" data-materials="<?= htmlspecialchars(strtolower(implode(' ', $materialNames))) ?>">
            <div class="h-1 <?= $sc['bar'] ?>"></div>
            <div class="p-4">
                <div class="flex items-start justify-between gap-2 mb-2.5">
                    <div class="min-w-0 flex-1">
                        <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100 truncate"><?= htmlspecialchars($projectName) ?></h3>
                        <p class="text-[11px] text-gray-400 mt-0.5"><?= date('d M Y, H:i', strtotime($req['created_at'])) ?></p>
                    </div>
                    <span class="shrink-0 inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold <?= $sc['badge'] ?>">
                        <?= $sc['icon'] ?> <?= $sc['label'] ?>
                    </span>
                </div>

                <?php if ($req['status'] !== 'rejected'): ?>
                <?php $statusOrder = ['pending','sales_approved','admin_review','admin_approved','driver_pickup','delivered','completed']; $curIdx = array_search($req['status'], $statusOrder); ?>
                <div class="flex items-center gap-0.5 mb-3">
                    <?php foreach (['pending','sales_approved','admin_approved','driver_pickup','delivered','completed'] as $step): ?>
                    <div class="flex-1 h-1 rounded-full <?= ($curIdx !== false && array_search($step, $statusOrder) <= $curIdx) ? 'bg-emerald-500' : 'bg-gray-100 dark:bg-gray-700' ?>"></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="flex flex-wrap gap-1 mb-3">
                    <?php foreach ($items as $item): ?>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-gray-50 dark:bg-gray-700/50 text-[11px] font-medium text-gray-600 dark:text-gray-300 border border-gray-100 dark:border-gray-600">
                        <?= htmlspecialchars($item['material_name']) ?> <span class="text-gray-400">×<?= $item['quantity'] ?></span>
                    </span>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($req['sales_edited_at'])): ?>
                <div class="mb-2 p-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700">
                    <div class="flex items-center gap-1.5 text-xs font-semibold text-amber-700 dark:text-amber-300">
                        <i class="fas fa-pen-to-square"></i> Diedit oleh Sales
                        <span class="font-normal text-amber-500 dark:text-amber-400 ml-auto"><?= date('d M, H:i', strtotime($req['sales_edited_at'])) ?></span>
                    </div>
                    <?php if (!empty($req['sales_edit_note'])): ?>
                    <p class="text-[11px] text-amber-600 dark:text-amber-400 mt-1"><i class="fas fa-comment-dots"></i> <?= htmlspecialchars($req['sales_edit_note']) ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($req['status'] === 'delivered'): ?>
                <div class="mt-1">
                    <p class="text-xs text-green-600 dark:text-green-400 mb-2"><i class="fas fa-box-open"></i> Material sudah diantar</p>
                    <button type="button" class="tech-confirm-btn w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-xs font-bold rounded-xl transition flex items-center justify-center gap-1.5" data-id="<?= $req['id'] ?>">
                        <i class="fas fa-check-circle"></i> Konfirmasi Diterima
                    </button>
                </div>
                <?php elseif ($req['status'] === 'pending'): ?>
                <div class="flex items-center gap-2 text-xs text-amber-600 dark:text-amber-400"><span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span> Menunggu approval Sales...</div>
                <?php elseif ($req['status'] === 'sales_approved'): ?>
                <div class="flex items-center gap-2 text-xs text-blue-600 dark:text-blue-400"><span class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse"></span> Sales approved, menunggu Admin...</div>
                <?php elseif ($req['status'] === 'admin_review'): ?>
                <div class="flex items-center gap-2 text-xs text-indigo-600 dark:text-indigo-400"><span class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-pulse"></span> Admin sedang review...</div>
                <?php elseif ($req['status'] === 'admin_approved' && $approval): ?>
                    <?php if (($req['delivery_method'] ?? 'driver') === 'technician_pickup'): ?>
                    <div class="mt-1">
                        <p class="text-xs text-emerald-600 dark:text-emerald-400 mb-2">
                            <i class="fas fa-box-open"></i> Material siap diambil sendiri
                        </p>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                class="tech-partial-pickup-btn py-2.5 bg-amber-500 hover:bg-amber-600 active:bg-amber-700 text-white text-xs font-bold rounded-xl transition"
                                data-id="<?= (int)$req['id'] ?>"
                                data-items="<?= $pickupItemsJson ?>"
                            >
                                <i class="fas fa-divide"></i> Ambil Setengah
                            </button>
                            <button
                                type="button"
                                class="tech-self-pickup-btn py-2.5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-xs font-bold rounded-xl transition"
                                data-id="<?= (int)$req['id'] ?>"
                            >
                                <i class="fas fa-check-circle"></i> Ambil Semua
                            </button>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="text-xs text-emerald-600 dark:text-emerald-400"><i class="fas fa-check-circle"></i> Material siap, menunggu Driver</div>
                    <?php endif; ?>
                <?php elseif ($req['status'] === 'driver_pickup'): ?>
                <div class="flex items-center gap-2 text-xs text-purple-600 dark:text-purple-400"><span class="w-1.5 h-1.5 rounded-full bg-purple-400 animate-pulse"></span> <i class="fas fa-truck"></i> Driver sedang mengantar...</div>
                <?php if (!empty($req['driver_delivery_eta'])): ?>
                <div class="mt-1.5 delivery-eta-countdown bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700 rounded-lg px-3 py-2" data-eta="<?= htmlspecialchars($req['driver_delivery_eta']) ?>">
                    <p class="text-xs font-bold text-purple-700 dark:text-purple-300 eta-text"><i class="fas fa-clock"></i> Menghitung...</p>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-center py-8 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
        <p class="text-gray-400 text-sm font-medium">Tidak ada request aktif</p>
        <p class="text-xs text-gray-300 dark:text-gray-500 mt-1">Buat request baru di atas</p>
    </div>
    <?php endif; ?>
    </div>
    </div>
</div>


<div class="view-tab-content hidden" id="tech-history">
    
    <div class="relative mb-4">
        <svg class="absolute left-3 top-0 bottom-0 my-auto w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" id="historySearchInput" placeholder="Cari project atau material..." class="w-full pl-9 pr-3 py-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl text-sm text-gray-800 dark:text-gray-100 focus:border-blue-500 focus:outline-none">
    </div>

    <?php if (!empty($techHistoryGrouped)): ?>
    <div id="historyProjectList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
    <?php foreach ($techHistoryGrouped as $projectId => $group): ?>
    <?php
        
        $completedReqs = array_filter($group['requests'], fn($r) => $r['status'] === 'completed');
        $rejectedReqs = array_filter($group['requests'], fn($r) => $r['status'] === 'rejected');
    ?>
    <div class="history-project-group bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden"
         data-project-name="<?= htmlspecialchars(strtolower($group['project_name'])) ?>"
         data-materials="<?= htmlspecialchars(strtolower(implode(' ', array_map(function($r) use ($pdo) { $items = getRequestItems($pdo, $r['id']); return implode(' ', array_map(fn($i) => $i['material_name'], $items)); }, $group['requests'])))) ?>">
        
        <button type="button" class="history-toggle-btn w-full flex items-center justify-between px-4 py-3.5 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors" data-project="<?= $projectId ?>">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                </div>
                <div class="min-w-0 text-left">
                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100 truncate"><?= htmlspecialchars($group['project_name']) ?></h3>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400"><?= count($group['requests']) ?> request</span>
                <svg class="w-4 h-4 text-gray-400 transition-transform duration-200 history-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </button>

        
        <div class="history-content hidden border-t border-gray-100 dark:border-gray-700">
            <?php if (!empty($completedReqs)): ?>
            
            <div class="border-b border-gray-50 dark:border-gray-700 last:border-b-0">
                <button type="button" class="history-status-toggle w-full flex items-center justify-between px-4 py-2.5 bg-emerald-50/80 dark:bg-emerald-900/20 hover:bg-emerald-100/80 dark:hover:bg-emerald-900/30 transition-colors">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="text-xs"><i class="fas fa-check-circle"></i></span>
                        <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">Selesai</span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300"><?= count($completedReqs) ?></span>
                        <svg class="w-3.5 h-3.5 text-emerald-400 transition-transform duration-200 status-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </button>
                <div class="history-status-content hidden divide-y divide-gray-50 dark:divide-gray-700">
                    <?php foreach ($completedReqs as $req): ?>
                    <?php
                        $items = getRequestItems($pdo, $req['id']);
                        $materialList = array_map(fn($i) => $i['material_name'] . ' ×' . $i['quantity'], $items);
                    ?>
                    <div class="px-4 py-3">
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <span class="text-[11px] font-medium text-gray-600 dark:text-gray-300"><?= date('d M Y, H:i', strtotime($req['created_at'])) ?></span>
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-300"><?= htmlspecialchars(implode(', ', $materialList)) ?></p>
                        <?php if (!empty($req['driver_delivered_photo'])): ?>
                        <div class="mt-2">
                            <p class="text-[10px] text-gray-400 dark:text-gray-500 mb-1"><i class="fas fa-camera"></i> Bukti pengiriman:</p>
                            <img src="./<?= ltrim($req['driver_delivered_photo'], '/') ?>" alt="Bukti" class="w-full h-28 object-cover rounded-lg border border-gray-200 dark:border-gray-600 cursor-pointer" onclick="window.open(this.src,'_blank')" />
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($rejectedReqs)): ?>
            
            <div class="border-b border-gray-50 dark:border-gray-700 last:border-b-0">
                <button type="button" class="history-status-toggle w-full flex items-center justify-between px-4 py-2.5 bg-red-50/80 dark:bg-red-900/20 hover:bg-red-100/80 dark:hover:bg-red-900/30 transition-colors">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="text-xs"><i class="fas fa-times-circle"></i></span>
                        <span class="text-xs font-semibold text-red-700 dark:text-red-300">Ditolak</span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300"><?= count($rejectedReqs) ?></span>
                        <svg class="w-3.5 h-3.5 text-red-400 transition-transform duration-200 status-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </button>
                <div class="history-status-content hidden divide-y divide-gray-50 dark:divide-gray-700">
                    <?php foreach ($rejectedReqs as $req): ?>
                    <?php
                        $items = getRequestItems($pdo, $req['id']);
                        $materialList = array_map(fn($i) => $i['material_name'] . ' ×' . $i['quantity'], $items);
                    ?>
                    <div class="px-4 py-3">
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <span class="text-[11px] font-medium text-gray-600 dark:text-gray-300"><?= date('d M Y, H:i', strtotime($req['created_at'])) ?></span>
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-300"><?= htmlspecialchars(implode(', ', $materialList)) ?></p>
                        <?php if (!empty($req['rejection_reason'])): ?>
                        <p class="text-xs text-red-500 dark:text-red-400 mt-1.5"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($req['rejection_reason']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
        <p class="text-gray-400 text-sm">Belum ada riwayat request</p>
    </div>
    <?php endif; ?>
</div>


<div class="view-tab-content hidden" id="tech-return">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    
    <div class="lg:col-span-1">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="px-4 py-3.5 border-b border-gray-100 dark:border-gray-700">
            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2"><i class="fas fa-undo-alt"></i> Ajukan Pengembalian Material</h3>
            <p class="text-xs text-gray-400 mt-0.5">Kembalikan material yang berlebih dari project</p>
        </div>
        <form id="returnForm" class="p-4 space-y-4">
            <div>
                <label class="text-xs font-bold text-gray-600 dark:text-gray-300 mb-1.5 block">Project</label>
                <select name="return_project_id" id="returnProjectSelect" required class="w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl text-sm">
                    <option value="">— Pilih project —</option>
                    <?php foreach ($allProjects as $proj): ?>
                    <?php if (in_array(strtoupper($proj['status'] ?? ''), ['NEAREST', 'ONGOING', 'DONE'])): ?>
                    <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['project_name']) ?></option>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="returnItemsContainer">
                <label class="text-xs font-bold text-gray-600 dark:text-gray-300 mb-1.5 block">Item yang dikembalikan</label>
                <div id="returnItemsLoading" class="hidden text-center py-4">
                    <i class="fas fa-spinner fa-spin text-gray-400"></i>
                    <p class="text-xs text-gray-400 mt-1">Memuat data RAB...</p>
                </div>
                <div id="returnItemsEmpty" class="hidden text-center py-4">
                    <i class="fas fa-box-open text-gray-300 text-2xl"></i>
                    <p class="text-xs text-gray-400 mt-1">Belum ada material di RAB untuk project ini</p>
                </div>
                <div class="space-y-2" id="returnItemsList">
                    <p class="text-xs text-gray-400 italic">Pilih project terlebih dahulu</p>
                </div>
            </div>
            <div>
                <label class="text-xs font-bold text-gray-600 dark:text-gray-300 mb-1.5 block">Catatan (opsional)</label>
                <textarea name="return_note" rows="2" placeholder="Alasan pengembalian..." class="w-full px-3 py-2.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl text-sm"></textarea>
            </div>
            <button type="submit" id="submitReturnBtn" class="w-full py-3 bg-orange-600 hover:bg-orange-700 active:bg-orange-800 text-white text-sm font-bold rounded-xl transition">
                <i class="fas fa-undo-alt"></i> Ajukan Pengembalian
            </button>
        </form>
    </div>
    </div>
    
    <div class="lg:col-span-2">
    <?php if (!empty($techReturns)): ?>
    <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Riwayat Pengembalian</h3>
    <div class="space-y-3">
        <?php foreach ($techReturns as $ret): ?>
        <?php
            $retStatusMap = [
                'pending' => ['label' => 'Menunggu Sales', 'badge' => 'bg-amber-100 text-amber-800', 'icon' => '<i class="fas fa-clock"></i>'],
                'sales_approved' => ['label' => 'Menunggu Admin', 'badge' => 'bg-blue-100 text-blue-800', 'icon' => '<i class="fas fa-check"></i>'],
                'admin_received' => ['label' => 'Diterima', 'badge' => 'bg-emerald-100 text-emerald-800', 'icon' => '<i class="fas fa-box"></i>'],
                'rejected' => ['label' => 'Ditolak', 'badge' => 'bg-red-100 text-red-800', 'icon' => '<i class="fas fa-times"></i>'],
            ];
            $rs = $retStatusMap[$ret['status']] ?? $retStatusMap['pending'];
        ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-4">
            <div class="flex items-center justify-between gap-2 mb-2">
                <span class="text-sm font-bold text-gray-800 dark:text-gray-100"><?= htmlspecialchars($ret['project_name']) ?></span>
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold <?= $rs['badge'] ?>"><?= $rs['icon'] ?> <?= $rs['label'] ?></span>
            </div>
            <div class="space-y-1 mb-2">
                <?php foreach ($ret['items'] as $item): ?>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-300"><?= htmlspecialchars($item['material_name']) ?></span>
                    <span class="font-semibold text-gray-800 dark:text-gray-100 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded text-xs">×<?= $item['quantity'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($ret['note'])): ?>
            <p class="text-xs text-gray-500 italic">Catatan: <?= htmlspecialchars($ret['note']) ?></p>
            <?php endif; ?>
            <?php if ($ret['status'] === 'rejected' && !empty($ret['rejection_reason'])): ?>
            <p class="text-xs text-red-500 mt-1">Alasan: <?= htmlspecialchars($ret['rejection_reason']) ?></p>
            <?php endif; ?>
            <p class="text-[10px] text-gray-400 mt-2"><?= date('d M Y, H:i', strtotime($ret['created_at'])) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
        <p class="text-gray-400 text-sm">Belum ada pengembalian material</p>
    </div>
    <?php endif; ?>
    </div>
    </div>
</div>

<?php elseif ($userView === 'administrator'): ?>



<div class="flex gap-1.5 mb-5 bg-gray-100 dark:bg-gray-700/50 p-1 rounded-xl overflow-x-auto">
    <button class="tab-btn flex-1 min-w-0 flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg text-xs font-bold transition bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 shadow-sm" data-tab="admin-review">
        <i class="fas fa-box-open"></i> Sediakan <span class="bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200 px-1.5 py-0.5 rounded-full text-[10px] ml-0.5"><?= count($adminReviewRequests) ?></span>
    </button>
    <button class="tab-btn flex-1 min-w-0 flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg text-xs font-bold transition text-gray-500 dark:text-gray-400" data-tab="admin-do">
        <i class="fas fa-file-pdf"></i> DO <span class="bg-sky-100 text-sky-700 dark:bg-sky-900 dark:text-sky-200 px-1.5 py-0.5 rounded-full text-[10px] ml-0.5"><?= count($adminDoRequests) ?></span>
    </button>
    <button class="tab-btn flex-1 min-w-0 flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg text-xs font-bold transition text-gray-500 dark:text-gray-400" data-tab="admin-history">
        <i class="fas fa-folder-open"></i> History
    </button>
    <button class="tab-btn flex-1 min-w-0 flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg text-xs font-bold transition text-gray-500 dark:text-gray-400" data-tab="admin-returns">
        <i class="fas fa-undo-alt"></i> Pengembalian <?php $adminRetPending = count(array_filter($adminReturns, fn($r) => $r['status'] === 'sales_approved')); if ($adminRetPending > 0): ?><span class="bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded-full text-[10px] ml-0.5"><?= $adminRetPending ?></span><?php endif; ?>
    </button>
</div>


<div class="tab-content" id="admin-review">
    <?php if (count($adminReviewRequests) > 0): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($adminReviewRequests as $req):
            $items = getRequestItems($pdo, $req['id']);
            $projectName = getProjectName($allProjects, $req['project_id']);
        ?>
        
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-indigo-200 dark:border-indigo-700 overflow-hidden">
            <div class="h-1 bg-indigo-500"></div>
            <div class="p-4">
                <div class="flex items-start justify-between gap-2 mb-3">
                    <div>
                        <p class="text-xs text-gray-400 dark:text-gray-500">Request dari</p>
                        <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100"><?= htmlspecialchars($req['requester_name']) ?></h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Project: <span class="font-medium"><?= htmlspecialchars($projectName) ?></span></p>
                    </div>
                    <span class="px-2 py-1 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-lg text-[10px] font-bold">Sales Approved</span>
                </div>
                <div class="space-y-1 mb-3">
                    <p class="text-[11px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider"><i class="fas fa-boxes"></i> Material (<?= count($items) ?> item)</p>
                    <div class="flex flex-wrap gap-1.5">
                        <?php foreach ($items as $item): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded-lg text-[11px] text-gray-600 dark:text-gray-300">
                            <?= htmlspecialchars($item['material_name']) ?> <span class="font-bold text-indigo-600 dark:text-indigo-400">×<?= $item['quantity'] ?></span>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <p class="text-[11px] text-gray-400 dark:text-gray-500 mb-3"><?= date('d M Y H:i', strtotime($req['created_at'])) ?></p>
                <?php if (!empty($req['pickup_date'])): ?>
                <div class="mb-3 px-2.5 py-1.5 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg">
                    <p class="text-[11px] text-amber-700 dark:text-amber-300 font-semibold"><i class="fas fa-calendar-alt mr-1"></i>Pengambilan: <?= date('d M Y', strtotime($req['pickup_date'])) ?></p>
                </div>
                <?php endif; ?>
                <button type="button" class="open-approval-modal w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-bold rounded-xl transition flex items-center justify-center gap-2" data-request-id="<?= $req['id'] ?>">
                    <i class="fas fa-box-open"></i> Sediakan Material
                </button>
            </div>
        </div>

        
        <div id="approvalModal_<?= $req['id'] ?>" class="approval-modal fixed inset-0 z-[9999] hidden" style="display:none;">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm close-approval-modal" data-request-id="<?= $req['id'] ?>"></div>
            <div class="absolute inset-0 flex items-start justify-center overflow-y-auto p-3 sm:p-4 lg:py-8">
                <div class="relative w-full max-w-lg bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-hidden my-auto">
                    
                    <div class="sticky top-0 z-10 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-4 py-3 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100"><?= htmlspecialchars($req['requester_name']) ?></h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Project: <?= htmlspecialchars($projectName) ?></p>
                        </div>
                        <button type="button" class="close-approval-modal w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-500 dark:text-gray-300 transition" data-request-id="<?= $req['id'] ?>"><i class="fas fa-times"></i></button>
                    </div>
                    
                    <div class="p-4 max-h-[80vh] overflow-y-auto">
                        <form class="approval-form space-y-4" data-request-id="<?= $req['id'] ?>">
                            
                            <div class="space-y-2">
                                <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider"><i class="fas fa-tags"></i> Harga Per Item</p>
                                <?php foreach ($items as $item): ?>
                                <div class="bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-xl p-3 item-pricing-row" data-item-id="<?= $item['id'] ?>" data-item-qty="<?= $item['quantity'] ?>">
                                    <div class="flex items-center justify-between mb-2">
                                        <div>
                                            <p class="text-sm font-bold text-gray-800 dark:text-gray-100"><?= htmlspecialchars($item['material_name']) ?></p>
                                            <p class="text-[11px] text-gray-400">Qty dibutuhkan: <span class="font-bold"><?= $item['quantity'] ?></span><?php if (!empty($item['notes'])): ?> · <?= htmlspecialchars($item['notes']) ?><?php endif; ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex gap-1 mb-2">
                                        <button type="button" class="source-toggle flex-1 py-1.5 rounded-lg text-[11px] font-bold transition bg-gray-100 dark:bg-gray-600 text-gray-400 border border-gray-200 dark:border-gray-500" data-source="purchase" data-item="<?= $item['id'] ?>"><i class="fas fa-store"></i> Beli</button>
                                        <button type="button" class="source-toggle flex-1 py-1.5 rounded-lg text-[11px] font-bold transition bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-700" data-source="warehouse" data-item="<?= $item['id'] ?>"><i class="fas fa-warehouse"></i> Gudang</button>
                                    </div>
                                    <input type="hidden" name="item_sources[<?= $item['id'] ?>]" value="warehouse" class="item-source-input" data-item="<?= $item['id'] ?>">

                                    
                                    <div class="warehouse-stock-section mb-2" data-item="<?= $item['id'] ?>">
                                        <label class="block text-[11px] font-bold text-emerald-600 dark:text-emerald-400 mb-1"><i class="fas fa-warehouse"></i> Stok tersedia di gudang</label>
                                        <input type="number" name="item_warehouse_qty[<?= $item['id'] ?>]" min="0" value="<?= $item['quantity'] ?>" inputmode="numeric" class="w-full px-2.5 py-2 bg-white dark:bg-gray-700 border border-emerald-200 dark:border-emerald-700 rounded-lg text-sm text-gray-800 dark:text-gray-100 focus:border-emerald-500 focus:outline-none warehouse-qty-input" data-item="<?= $item['id'] ?>" data-needed="<?= $item['quantity'] ?>">
                                        <div class="warehouse-split-info mt-1.5" data-item="<?= $item['id'] ?>"></div>
                                    </div>

                                    
                                    <div class="store-name-section mb-2 hidden" data-item="<?= $item['id'] ?>">
                                        <label class="block text-[11px] font-bold text-blue-600 dark:text-blue-400 mb-1"><i class="fas fa-store"></i> Pilih Supplier / Toko</label>
                                        <select name="item_store_name[<?= $item['id'] ?>]" class="w-full px-2.5 py-2 bg-white dark:bg-gray-700 border border-blue-200 dark:border-blue-700 rounded-lg text-sm text-gray-800 dark:text-gray-100 focus:border-blue-500 focus:outline-none store-name-select" data-item="<?= $item['id'] ?>">
                                            <option value="">-- Pilih Supplier --</option>
                                        </select>
                                        <input type="hidden" name="item_store_address[<?= $item['id'] ?>]" class="store-address-input" data-item="<?= $item['id'] ?>">
                                    </div>

                                    
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 flex items-center border border-gray-200 dark:border-gray-600 rounded-lg px-2 bg-white dark:bg-gray-700 focus-within:border-blue-500 transition">
                                            <span class="text-xs text-gray-400 mr-1">Rp</span>
                                            <input type="number" name="item_prices[<?= $item['id'] ?>]" min="0" placeholder="0" inputmode="numeric" required class="flex-1 py-2 text-sm bg-transparent text-gray-800 dark:text-gray-100 focus:outline-none item-price-input" data-qty="<?= $item['quantity'] ?>">
                                        </div>
                                        <div class="text-right min-w-[80px]">
                                            <p class="text-[10px] text-gray-400">Total</p>
                                            <p class="text-sm font-bold text-gray-800 dark:text-gray-100 item-total-display">Rp 0</p>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-xl p-3 flex items-center justify-between">
                                    <p class="text-xs font-bold text-blue-700 dark:text-blue-300">Grand Total</p>
                                    <p class="text-sm font-bold text-blue-800 dark:text-blue-200 admin-grand-total">Rp 0</p>
                                </div>
                            </div>

                            
                            <div>
                                <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5"><i class="fas fa-truck"></i> Metode Pengiriman</p>
                                <div class="flex gap-2">
                                    <button type="button" class="delivery-method-toggle flex-1 py-2.5 rounded-xl text-xs font-bold transition border-2 bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 border-purple-300 dark:border-purple-600" data-method="driver" data-request="<?= $req['id'] ?>">
                                        <i class="fas fa-truck"></i> Driver Antar
                                    </button>
                                    <button type="button" class="delivery-method-toggle flex-1 py-2.5 rounded-xl text-xs font-bold transition border-2 bg-gray-100 dark:bg-gray-600 text-gray-400 border-gray-200 dark:border-gray-500" data-method="technician_pickup" data-request="<?= $req['id'] ?>">
                                        <i class="fas fa-walking"></i> Technician Ambil
                                    </button>
                                </div>
                                <input type="hidden" name="delivery_method" value="driver" class="delivery-method-input" />
                            </div>

                            
                            <div class="photo-section" data-request="<?= $req['id'] ?>">
                                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5"><i class="fas fa-camera"></i> Foto Material <span class="photo-required-badge text-red-500">*</span></label>
                                <p class="text-[10px] text-gray-400 mb-2 photo-hint">Wajib foto jika material dari gudang</p>
                                <div id="cameraContainer_<?= $req['id'] ?>" class="bg-gray-100 dark:bg-gray-700 rounded-xl overflow-hidden mb-2">
                                    <video id="cameraVideo_<?= $req['id'] ?>" class="w-full aspect-[4/3] object-cover bg-black" style="display:none;" autoplay playsinline muted></video>
                                    <div id="cameraPlaceholder_<?= $req['id'] ?>" class="w-full aspect-[4/3] flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                                        <svg class="w-10 h-10 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3"/></svg>
                                        <p class="text-xs">Tap tombol untuk foto</p>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" id="openCameraBtn_<?= $req['id'] ?>" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-bold rounded-xl transition flex items-center justify-center gap-1.5"><i class="fas fa-camera"></i> Buka Kamera</button>
                                    <button type="button" id="closeCameraBtn_<?= $req['id'] ?>" class="flex-1 py-2.5 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 text-xs font-bold rounded-xl transition hidden flex items-center justify-center gap-1.5"><i class="fas fa-times"></i> Tutup</button>
                                </div>
                                <button type="button" id="captureBtn_<?= $req['id'] ?>" class="w-full mt-2 py-3 bg-green-600 hover:bg-green-700 active:bg-green-800 text-white text-sm font-bold rounded-xl transition hidden flex items-center justify-center gap-2"><i class="fas fa-camera-retro"></i> AMBIL FOTO</button>
                                <input type="file" name="material_photo" id="photoInput_<?= $req['id'] ?>" class="hidden material-photo-input" accept="image/*" capture="environment" />
                                <div id="photoPreview_<?= $req['id'] ?>" class="mt-2"></div>
                                <div id="photoValidation_<?= $req['id'] ?>" class="mt-2"></div>
                                <canvas id="photoCanvas_<?= $req['id'] ?>" class="hidden"></canvas>
                            </div>

                            
                            <div>
                                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5"><i class="fas fa-sticky-note"></i> Catatan</label>
                                <textarea name="notes" rows="2" placeholder="Catatan (opsional)..." class="w-full px-3.5 py-2.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl text-sm text-gray-800 dark:text-gray-100 focus:border-blue-500 focus:outline-none resize-none"></textarea>
                            </div>

                            
                            <button type="submit" class="approval-submit-btn w-full py-3.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2">
                                <i class="fas fa-check-circle"></i> Sediakan & Kirim ke Driver
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
        <p class="text-gray-400 text-sm">Tidak ada request yang perlu diisi</p>
    </div>
    <?php endif; ?>
</div>



<div class="tab-content hidden" id="admin-do">
    <div class="mb-4 rounded-2xl border border-sky-200 dark:border-sky-800 bg-sky-50 dark:bg-sky-900/20 px-4 py-3">
        <h2 class="text-sm font-bold text-sky-800 dark:text-sky-200"><i class="fas fa-file-pdf"></i> Delivery Order</h2>
        <p class="text-xs text-sky-700 dark:text-sky-300 mt-1">DO otomatis tersedia setelah Admin submit/sediakan material. Klik Preview / Download DO pada request terkait.</p>
    </div>
    <?php if (!empty($adminDoRequests)): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($adminDoRequests as $req): ?>
        <?php
            $items = getRequestItems($pdo, $req['id']);
            $projectName = getProjectName($allProjects, $req['project_id']);
            $sc = $statusLabels[$req['status']] ?? $statusLabels['admin_approved'];
            $doNo = 'DO-' . date('Ymd', strtotime($req['admin_approved_at'] ?: $req['updated_at'] ?: 'now')) . '-' . str_pad((string)$req['id'], 4, '0', STR_PAD_LEFT);
        ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-sky-100 dark:border-sky-800 overflow-hidden">
            <div class="h-1 bg-sky-500"></div>
            <div class="p-4">
                <div class="flex items-start justify-between gap-2 mb-3">
                    <div class="min-w-0">
                        <p class="text-[11px] text-gray-400">No. DO</p>
                        <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100 truncate"><?= htmlspecialchars($doNo) ?></h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate"><?= htmlspecialchars($projectName) ?></p>
                    </div>
                    <span class="shrink-0 text-[10px] font-bold <?= $sc['badge'] ?> px-2 py-0.5 rounded-full"><?= $sc['icon'] ?> <?= $sc['label'] ?></span>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2"><i class="fas fa-user"></i> <?= htmlspecialchars($req['requester_name']) ?></p>
                <div class="flex flex-wrap gap-1.5 mb-4">
                    <?php foreach (array_slice($items, 0, 5) as $item): ?>
                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded-lg text-[11px] text-gray-600 dark:text-gray-300"><?= htmlspecialchars($item['material_name']) ?> <span class="font-bold text-sky-600 dark:text-sky-400">×<?= (int)$item['quantity'] ?></span></span>
                    <?php endforeach; ?>
                    <?php if (count($items) > 5): ?><span class="px-2 py-1 bg-gray-50 dark:bg-gray-700 rounded-lg text-[11px] text-gray-400">+<?= count($items) - 5 ?> item</span><?php endif; ?>
                </div>
                <a href="/dashboard.php?page=delivery-order&request_id=<?= (int)$req['id'] ?>" target="_blank" class="flex items-center justify-center gap-2 w-full py-3 rounded-xl text-white text-sm font-bold transition shadow-md" style="background-color: #0284c7;">
                    <i class="fas fa-file-pdf"></i> Preview / Download DO
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
        <p class="text-gray-400 text-sm">Belum ada DO. DO muncul setelah Admin submit/sediakan material.</p>
    </div>
    <?php endif; ?>
</div>


<div class="tab-content hidden" id="admin-history">
    
    <div class="flex gap-2 mb-4">
        <div class="flex-1 relative">
            <svg class="absolute left-3 top-0 bottom-0 my-auto w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" id="adminHistorySearch" placeholder="Cari project, teknisi, material..." class="w-full pl-9 pr-3 py-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl text-sm text-gray-800 dark:text-gray-100 focus:border-blue-500 focus:outline-none">
        </div>
    </div>

    <?php if (!empty($adminHistoryGrouped)): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3" id="adminHistoryList">
    <?php foreach ($adminHistoryGrouped as $projectId => $group): ?>
    <?php
        $ahCompleted = array_filter($group['requests'], fn($r) => $r['status'] === 'completed');
        $ahRejected = array_filter($group['requests'], fn($r) => $r['status'] === 'rejected');
        $ahActive = array_filter($group['requests'], fn($r) => !in_array($r['status'], ['completed','rejected']));
        $allMaterialNames = [];
        foreach ($group['requests'] as $r) { $itms = getRequestItems($pdo, $r['id']); foreach ($itms as $it) $allMaterialNames[] = strtolower($it['material_name']); }
    ?>
    <div class="admin-history-project bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden"
         data-project-name="<?= htmlspecialchars(strtolower($group['project_name'])) ?>"
         data-materials="<?= htmlspecialchars(strtolower(implode(' ', $allMaterialNames))) ?>"
         data-requester="<?= htmlspecialchars(strtolower(implode(' ', array_unique(array_map(fn($r) => $r['requester_name'], $group['requests']))))) ?>">
        
        <button type="button" class="admin-hist-project-toggle w-full flex items-center justify-between px-4 py-3.5 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                </div>
                <div class="min-w-0 text-left">
                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100 truncate"><?= htmlspecialchars($group['project_name']) ?></h3>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <?php if (!empty($ahCompleted)): ?><span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300"><?= count($ahCompleted) ?> <i class="fas fa-check-circle"></i></span><?php endif; ?>
                <?php if (!empty($ahRejected)): ?><span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300"><?= count($ahRejected) ?> <i class="fas fa-times-circle"></i></span><?php endif; ?>
                <?php if (!empty($ahActive)): ?><span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300"><?= count($ahActive) ?> <i class="fas fa-sync-alt"></i></span><?php endif; ?>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400"><?= count($group['requests']) ?></span>
                <svg class="w-4 h-4 text-gray-400 transition-transform duration-200 admin-hist-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </button>

        
        <div class="admin-hist-content hidden border-t border-gray-100 dark:border-gray-700">
            <?php
            $statusGroups = [
                'active' => ['label' => 'Dalam Proses', 'icon' => '<i class="fas fa-sync-alt"></i>', 'bg' => 'bg-blue-50/80 dark:bg-blue-900/20', 'hover' => 'hover:bg-blue-100/80 dark:hover:bg-blue-900/30', 'text' => 'text-blue-700 dark:text-blue-300', 'badge_bg' => 'bg-blue-100 dark:bg-blue-900/50', 'items' => $ahActive],
                'completed' => ['label' => 'Selesai', 'icon' => '<i class="fas fa-check-circle"></i>', 'bg' => 'bg-emerald-50/80 dark:bg-emerald-900/20', 'hover' => 'hover:bg-emerald-100/80 dark:hover:bg-emerald-900/30', 'text' => 'text-emerald-700 dark:text-emerald-300', 'badge_bg' => 'bg-emerald-100 dark:bg-emerald-900/50', 'items' => $ahCompleted],
                'rejected' => ['label' => 'Ditolak', 'icon' => '<i class="fas fa-times-circle"></i>', 'bg' => 'bg-red-50/80 dark:bg-red-900/20', 'hover' => 'hover:bg-red-100/80 dark:hover:bg-red-900/30', 'text' => 'text-red-700 dark:text-red-300', 'badge_bg' => 'bg-red-100 dark:bg-red-900/50', 'items' => $ahRejected],
            ];
            foreach ($statusGroups as $sgKey => $sg):
                if (empty($sg['items'])) continue;
            ?>
            <div class="border-b border-gray-50 dark:border-gray-700 last:border-b-0">
                <button type="button" class="admin-hist-status-toggle w-full flex items-center justify-between px-4 py-2.5 <?= $sg['bg'] ?> <?= $sg['hover'] ?> transition-colors">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="text-xs"><?= $sg['icon'] ?></span>
                        <span class="text-xs font-semibold <?= $sg['text'] ?>"><?= $sg['label'] ?></span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold <?= $sg['badge_bg'] ?> <?= $sg['text'] ?>"><?= count($sg['items']) ?></span>
                        <svg class="w-3.5 h-3.5 text-gray-400 transition-transform duration-200 admin-hist-status-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </button>
                <div class="admin-hist-status-content hidden divide-y divide-gray-50 dark:divide-gray-700">
                    <?php foreach ($sg['items'] as $req):
                        $items = getRequestItems($pdo, $req['id']);
                        $approval = getRequestApproval($pdo, $req['id']);
                        $sc = $statusLabels[$req['status']] ?? $statusLabels['pending'];
                    ?>
                    <div class="admin-hist-request">
                        
                        <button type="button" class="admin-hist-req-toggle w-full text-left px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <span class="text-sm font-medium text-gray-800 dark:text-gray-100"><?= htmlspecialchars($req['requester_name']) ?></span>
                                <span class="shrink-0 text-[10px] font-bold <?= $sc['badge'] ?> px-2 py-0.5 rounded-full"><?= $sc['icon'] ?> <?= $sc['label'] ?></span>
                            </div>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400"><?= date('d M Y, H:i', strtotime($req['created_at'])) ?></p>
                            <div class="flex flex-wrap gap-1 mt-1.5">
                                <?php foreach ($items as $item): ?>
                                <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md bg-gray-50 dark:bg-gray-700/50 text-[11px] font-medium text-gray-600 dark:text-gray-300 border border-gray-100 dark:border-gray-600"><?= htmlspecialchars($item['material_name']) ?> <span class="text-gray-400">×<?= $item['quantity'] ?></span></span>
                                <?php endforeach; ?>
                            </div>
                        </button>
                        
                        <div class="admin-hist-req-detail hidden border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 px-4 py-3 space-y-3">
                            <?php if ($req['status'] !== 'rejected'): ?>
                            <?php $statusOrder = ['pending','sales_approved','admin_review','admin_approved','driver_pickup','delivered','completed']; $curIdx = array_search($req['status'], $statusOrder); ?>
                            <div class="flex items-center gap-0.5">
                                <?php foreach (['pending','sales_approved','admin_approved','driver_pickup','delivered','completed'] as $step): ?>
                                <div class="flex-1 h-1.5 rounded-full <?= ($curIdx !== false && array_search($step, $statusOrder) <= $curIdx) ? 'bg-emerald-500' : 'bg-gray-200 dark:bg-gray-700' ?>"></div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <div class="bg-white dark:bg-gray-700/50 rounded-lg divide-y divide-gray-100 dark:divide-gray-600 overflow-hidden border border-gray-200 dark:border-gray-600">
                                <?php foreach ($items as $item): ?>
                                <div class="px-3 py-2 flex items-center justify-between">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm text-gray-800 dark:text-gray-100"><?= htmlspecialchars($item['material_name']) ?></p>
                                        <?php if (!empty($item['notes'])): ?><p class="text-[10px] text-gray-400"><?= htmlspecialchars($item['notes']) ?></p><?php endif; ?>
                                    </div>
                                    <div class="text-right shrink-0 ml-3">
                                        <span class="text-sm font-bold text-gray-700 dark:text-gray-200">×<?= $item['quantity'] ?></span>
                                        <?php if (!empty($item['price']) && $item['price'] > 0): ?>
                                        <p class="text-[10px] text-gray-400">Rp <?= number_format((float)$item['price'], 0, ',', '.') ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php $totalPrice = array_sum(array_map(fn($i) => ((float)($i['price'] ?? 0)) * ((int)$i['quantity']), $items));
                                if ($totalPrice > 0): ?>
                                <div class="px-3 py-2 bg-blue-50 dark:bg-blue-900/20 flex items-center justify-between">
                                    <p class="text-xs font-bold text-blue-700 dark:text-blue-300">Total</p>
                                    <p class="text-sm font-bold text-blue-800 dark:text-blue-200">Rp <?= number_format($totalPrice, 0, ',', '.') ?></p>
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($approval && !empty($approval['estimated_delivery'])): ?>
                            <div class="flex items-center gap-2 text-xs text-blue-600 dark:text-blue-400">
                                <span><i class="fas fa-calendar-alt"></i></span> Est. Kirim: <?= date('d M Y, H:i', strtotime($approval['estimated_delivery'])) ?>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($approval['photo_path']) || !empty($req['driver_pickup_photo']) || !empty($req['driver_delivered_photo'])): ?>
                            <div class="flex gap-2 overflow-x-auto">
                                <?php if (!empty($approval['photo_path'])): ?>
                                <div class="shrink-0">
                                    <p class="text-[10px] text-gray-400 mb-1"><i class="fas fa-camera"></i> Material</p>
                                    <img src="./<?= ltrim($approval['photo_path'], '/') ?>" alt="Material" class="h-24 w-24 object-cover rounded-lg border cursor-pointer" onclick="window.open(this.src,'_blank')" />
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($req['driver_pickup_photo'])): ?>
                                <div class="shrink-0">
                                    <p class="text-[10px] text-purple-500 mb-1"><i class="fas fa-truck-loading"></i> Pickup</p>
                                    <img src="./<?= ltrim($req['driver_pickup_photo'], '/') ?>" alt="Pickup" class="h-24 w-24 object-cover rounded-lg border border-purple-200 cursor-pointer" onclick="window.open(this.src,'_blank')" />
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($req['driver_delivered_photo'])): ?>
                                <div class="shrink-0">
                                    <p class="text-[10px] text-green-500 mb-1"><i class="fas fa-box-open"></i> Delivered</p>
                                    <img src="./<?= ltrim($req['driver_delivered_photo'], '/') ?>" alt="Delivered" class="h-24 w-24 object-cover rounded-lg border border-green-200 cursor-pointer" onclick="window.open(this.src,'_blank')" />
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <?php if (in_array($req['status'], ['admin_approved','driver_pickup','delivered','completed'])): ?>
                            <a href="/dashboard.php?page=delivery-order&request_id=<?= (int)$req['id'] ?>" target="_blank" class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 active:bg-sky-800 text-white text-xs font-bold transition">
                                <i class="fas fa-file-pdf"></i> Buat DO
                            </a>
                            <?php endif; ?>

                            <?php if ($req['status'] === 'rejected' && !empty($req['rejection_reason'])): ?>
                            <p class="text-xs text-red-500 dark:text-red-400"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($req['rejection_reason']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
        <p class="text-gray-400 text-sm">Belum ada history</p>
    </div>
    <?php endif; ?>
</div>


<div class="tab-content hidden" id="admin-returns">
    <?php
        $adminReturnsPendingList = array_filter($adminReturns, fn($r) => $r['status'] === 'sales_approved');
        $adminReturnsHistoryList = array_filter($adminReturns, fn($r) => in_array($r['status'], ['admin_received', 'rejected']));
        $adminReturnsPendingTech = array_filter($adminReturns, fn($r) => $r['status'] === 'pending');
    ?>

    <?php if (!empty($adminReturnsPendingList)): ?>
    <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3"><i class="fas fa-box"></i> Siap Diterima</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        <?php foreach ($adminReturnsPendingList as $ret): ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border-2 border-orange-200 dark:border-orange-700 overflow-hidden">
            <div class="h-1 bg-orange-500"></div>
            <div class="p-4">
                <div class="flex items-start justify-between gap-2 mb-3">
                    <div>
                        <p class="text-xs text-gray-400">Pengembalian dari</p>
                        <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100"><?= htmlspecialchars($ret['requester_name']) ?></h3>
                        <p class="text-xs text-gray-500 mt-0.5">Project: <span class="font-medium"><?= htmlspecialchars($ret['project_name']) ?></span></p>
                    </div>
                    <span class="px-2 py-1 bg-orange-50 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300 rounded-lg text-[10px] font-bold">Disetujui Sales</span>
                </div>
                <div class="space-y-1.5 mb-3">
                    <?php foreach ($ret['items'] as $item): ?>
                    <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2">
                        <span class="text-sm text-gray-700 dark:text-gray-200"><?= htmlspecialchars($item['material_name']) ?></span>
                        <span class="font-bold text-gray-800 dark:text-gray-100 bg-white dark:bg-gray-600 px-2 py-0.5 rounded text-xs">×<?= $item['quantity'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($ret['note'])): ?>
                <p class="text-xs text-gray-500 italic mb-3">Catatan: <?= htmlspecialchars($ret['note']) ?></p>
                <?php endif; ?>
                <p class="text-[10px] text-gray-400 mb-3"><?= date('d M Y, H:i', strtotime($ret['created_at'])) ?></p>
                <button type="button" class="admin-receive-return-btn w-full py-3 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-sm font-bold rounded-xl transition flex items-center justify-center gap-2" data-return-id="<?= $ret['id'] ?>">
                    <i class="fas fa-box"></i> Terima Barang & Update RAB
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($adminReturnsPendingTech)): ?>
    <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3"><i class="fas fa-clock"></i> Menunggu Persetujuan Sales</h3>
    <div class="space-y-2 mb-6">
        <?php foreach ($adminReturnsPendingTech as $ret): ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-3">
            <div class="flex items-center justify-between gap-2 mb-1">
                <span class="text-sm font-bold text-gray-800 dark:text-gray-100"><?= htmlspecialchars($ret['requester_name']) ?></span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800"><i class="fas fa-clock"></i> Menunggu Sales</span>
            </div>
            <p class="text-[11px] text-gray-500 truncate"><?= htmlspecialchars($ret['project_name']) ?></p>
            <div class="flex flex-wrap gap-1 mt-1">
                <?php foreach ($ret['items'] as $item): ?>
                <span class="text-[10px] text-gray-400"><?= htmlspecialchars($item['material_name']) ?>×<?= $item['quantity'] ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($adminReturnsHistoryList)): ?>
    <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Riwayat Pengembalian</h3>
    <div class="space-y-2">
        <?php foreach ($adminReturnsHistoryList as $ret): ?>
        <?php
            $rMap = [
                'admin_received' => ['label' => 'Diterima', 'badge' => 'bg-emerald-100 text-emerald-800', 'icon' => '<i class="fas fa-box"></i>'],
                'rejected' => ['label' => 'Ditolak', 'badge' => 'bg-red-100 text-red-800', 'icon' => '<i class="fas fa-times"></i>'],
            ];
            $rs2 = $rMap[$ret['status']] ?? ['label' => $ret['status'], 'badge' => 'bg-gray-100 text-gray-800', 'icon' => ''];
        ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-3">
            <div class="flex items-center justify-between gap-2 mb-1">
                <span class="text-sm font-bold text-gray-800 dark:text-gray-100"><?= htmlspecialchars($ret['requester_name']) ?></span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $rs2['badge'] ?>"><?= $rs2['icon'] ?> <?= $rs2['label'] ?></span>
            </div>
            <p class="text-[11px] text-gray-500 truncate"><?= htmlspecialchars($ret['project_name']) ?> · <?= date('d M Y', strtotime($ret['created_at'])) ?></p>
            <div class="flex flex-wrap gap-1 mt-1">
                <?php foreach ($ret['items'] as $item): ?>
                <span class="text-[10px] text-gray-400"><?= htmlspecialchars($item['material_name']) ?>×<?= $item['quantity'] ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($adminReturnsPendingList) && empty($adminReturnsPendingTech) && empty($adminReturnsHistoryList)): ?>
    <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
        <p class="text-gray-400 text-sm">Belum ada pengembalian material</p>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($userView === 'driver'): ?>



<div class="flex gap-1.5 mb-5 bg-gray-100 dark:bg-gray-700/50 p-1 rounded-xl">
    <button class="view-tab flex-1 flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg text-xs font-bold transition bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 shadow-sm" data-tab="driver-active">
        <i class="fas fa-truck"></i> Antar <span class="bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-200 px-1.5 py-0.5 rounded-full text-[10px] ml-0.5"><?= $driverActiveCount ?></span>
    </button>
    <button class="view-tab flex-1 flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg text-xs font-bold transition text-gray-500 dark:text-gray-400" data-tab="driver-history">
        <i class="fas fa-folder-open"></i> Riwayat
    </button>
</div>


<div class="view-tab-content" id="driver-active">
    <?php if (!empty($driverActiveGrouped)): ?>
    <?php foreach ($driverActiveGrouped as $projectId => $group): ?>
    <div class="mb-5">
        <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2.5 flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <?= htmlspecialchars($group['project_name']) ?>
            <span class="text-gray-300 dark:text-gray-600">(<?= count($group['requests']) ?>)</span>
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <?php foreach ($group['requests'] as $req):
                $items = getRequestItems($pdo, $req['id']);
                $approval = getRequestApproval($pdo, $req['id']);
                $isPickup = $req['status'] === 'admin_approved';
            ?>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border-2 <?= $isPickup ? 'border-emerald-200 dark:border-emerald-700' : 'border-purple-200 dark:border-purple-700' ?> overflow-hidden">
                <div class="h-1 <?= $isPickup ? 'bg-emerald-500' : 'bg-purple-500' ?>"></div>
                <div class="p-4">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Untuk: <?= htmlspecialchars($req['requester_name']) ?></p>
                            <p class="text-[11px] text-gray-400"><?= date('d M Y, H:i', strtotime($req['created_at'])) ?></p>
                        </div>
                        <span class="shrink-0 px-2 py-1 rounded-lg text-[10px] font-bold <?= $isPickup ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300' : 'bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300' ?>">
                            <?= $isPickup ? '<i class="fas fa-box"></i> Pickup' : '<i class="fas fa-truck"></i> Kirim' ?>
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-1 mb-3">
                        <?php foreach ($items as $item): ?>
                        <span class="px-2 py-0.5 <?= $isPickup ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300 border-emerald-100 dark:border-emerald-700' : 'bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-300 border-purple-100 dark:border-purple-700' ?> rounded-md text-[11px] font-medium border">
                            <?= htmlspecialchars($item['material_name']) ?> ×<?= $item['quantity'] ?>
                        </span>
                        <?php endforeach; ?>
                    </div>

                    <?php
                    
                    
                    $purchaseItems = array_filter($items, fn($i) => 
                        (!empty($i['source_type']) && in_array($i['source_type'], ['purchase', 'split'])) 
                        || (isset($i['qty_to_purchase']) && (int)$i['qty_to_purchase'] > 0)
                    );
                    
                    $warehouseItems = [];
                    foreach ($items as $it) {
                        $isPurchaseOnly = !empty($it['source_type']) && $it['source_type'] === 'purchase';
                        $hasWarehouseQty = isset($it['qty_from_warehouse']) && (int)$it['qty_from_warehouse'] > 0;
                        $isDefault = empty($it['source_type']) || $it['source_type'] === 'warehouse';
                        
                        if ($hasWarehouseQty || ($isDefault && !in_array($it, array_values($purchaseItems)))) {
                            $warehouseItems[] = $it;
                        }
                    }
                    
                    $storeGroups = [];
                    foreach ($purchaseItems as $pi) {
                        $store = $pi['store_name'] ?: 'Toko (belum ditentukan)';
                        $storeGroups[$store][] = $pi;
                    }
                    $hasWarehouse = !empty($warehouseItems);
                    $hasPurchase = !empty($purchaseItems);
                    $stepNum = 0;
                    ?>

                    
                    <div class="mb-3 rounded-2xl overflow-hidden shadow-sm" style="border:2px solid #e0e7ff;">
                        <div class="px-4 py-3 flex items-center gap-2.5" style="background:linear-gradient(135deg,#4f46e5,#6366f1);">
                            <div class="w-7 h-7 rounded-lg bg-white/20 flex items-center justify-center">
                                <i class="fas fa-route text-white text-xs"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-white leading-tight">Rute Pengantaran</p>
                                <p class="text-[10px] text-indigo-200"><?= $hasWarehouse && $hasPurchase ? 'Gudang → Toko → Lokasi' : ($hasPurchase ? 'Toko → Lokasi' : 'Gudang → Lokasi') ?></p>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 p-4">

                                <?php if ($hasWarehouse): $stepNum++; ?>
                                <div class="flex gap-3 mb-4">
                                    <div class="flex flex-col items-center">
                                        <div class="w-7 h-7 rounded-full bg-emerald-500 flex items-center justify-center shadow-md shrink-0" style="box-shadow:0 0 0 3px #d1fae5;">
                                            <span class="text-[10px] font-bold text-white"><?= $stepNum ?></span>
                                        </div>
                                        <div class="w-0.5 flex-1 bg-emerald-200 dark:bg-emerald-700 mt-1.5 rounded-full"></div>
                                    </div>
                                    <div class="flex-1 pb-1">
                                        <p class="text-sm font-bold text-emerald-700 dark:text-emerald-300 mb-2"><i class="fas fa-warehouse mr-1"></i>Ambil dari Gudang</p>
                                        <div class="space-y-1.5">
                                            <?php foreach ($warehouseItems as $wi): ?>
                                            <div class="flex items-center gap-2 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg px-3 py-2">
                                                <div class="w-5 h-5 rounded bg-emerald-100 dark:bg-emerald-800 flex items-center justify-center shrink-0">
                                                    <i class="fas fa-cube text-emerald-500 text-[8px]"></i>
                                                </div>
                                                <span class="text-xs text-gray-800 dark:text-gray-200 flex-1 font-medium"><?= htmlspecialchars($wi['material_name']) ?></span>
                                                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/40 px-2 py-0.5 rounded-full"><?= (int)($wi['qty_from_warehouse'] ?: $wi['quantity']) ?></span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php foreach ($storeGroups as $storeName => $storeItems): $stepNum++;
                                    $storeAddr = '';
                                    foreach ($storeItems as $si_check) { if (!empty($si_check['store_address'])) { $storeAddr = $si_check['store_address']; break; } }
                                ?>
                                <div class="flex gap-3 mb-4">
                                    <div class="flex flex-col items-center">
                                        <div class="w-7 h-7 rounded-full bg-amber-500 flex items-center justify-center shadow-md shrink-0" style="box-shadow:0 0 0 3px #fef3c7;">
                                            <span class="text-[10px] font-bold text-white"><?= $stepNum ?></span>
                                        </div>
                                        <div class="w-0.5 flex-1 bg-amber-200 dark:bg-amber-700 mt-1.5 rounded-full"></div>
                                    </div>
                                    <div class="flex-1 pb-1">
                                        <p class="text-sm font-bold text-amber-700 dark:text-amber-300 mb-0.5"><i class="fas fa-store mr-1"></i>Beli di Toko</p>
                                        <p class="text-xs text-amber-600 dark:text-amber-400 mb-1"><i class="fas fa-map-marker-alt mr-1 text-[10px]"></i><?= htmlspecialchars($storeName) ?></p>
                                        <?php if ($storeAddr): ?>
                                        <p class="text-[11px] text-amber-500 dark:text-amber-400/80 mb-1"><?= htmlspecialchars($storeAddr) ?></p>
                                        <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($storeAddr) ?>" target="_blank" class="inline-flex items-center gap-1 text-[10px] font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-2.5 py-1 rounded-full mb-2 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition"><i class="fas fa-map-marked-alt"></i> Buka di Google Maps</a>
                                        <?php endif; ?>
                                        <div class="space-y-1.5">
                                            <?php foreach ($storeItems as $si): ?>
                                            <div class="flex items-center gap-2 bg-amber-50 dark:bg-amber-900/20 rounded-lg px-3 py-2">
                                                <div class="w-5 h-5 rounded bg-amber-100 dark:bg-amber-800 flex items-center justify-center shrink-0">
                                                    <i class="fas fa-shopping-bag text-amber-500 text-[8px]"></i>
                                                </div>
                                                <span class="text-xs text-gray-800 dark:text-gray-200 flex-1 font-medium"><?= htmlspecialchars($si['material_name']) ?></span>
                                                <span class="text-xs font-bold text-amber-600 dark:text-amber-400 bg-amber-100 dark:bg-amber-900/40 px-2 py-0.5 rounded-full"><?= (int)($si['qty_to_purchase'] ?: $si['quantity']) ?></span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>

                                <?php if (!$hasWarehouse && !$hasPurchase): $stepNum++; ?>
                                <div class="flex gap-3 mb-4">
                                    <div class="flex flex-col items-center">
                                        <div class="w-7 h-7 rounded-full bg-emerald-500 flex items-center justify-center shadow-md shrink-0" style="box-shadow:0 0 0 3px #d1fae5;">
                                            <span class="text-[10px] font-bold text-white"><?= $stepNum ?></span>
                                        </div>
                                        <div class="w-0.5 flex-1 bg-emerald-200 dark:bg-emerald-700 mt-1.5 rounded-full"></div>
                                    </div>
                                    <div class="flex-1 pb-1">
                                        <p class="text-sm font-bold text-emerald-700 dark:text-emerald-300 mb-2"><i class="fas fa-warehouse mr-1"></i>Ambil Material</p>
                                        <div class="space-y-1.5">
                                            <?php foreach ($items as $it): ?>
                                            <div class="flex items-center gap-2 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg px-3 py-2">
                                                <div class="w-5 h-5 rounded bg-emerald-100 dark:bg-emerald-800 flex items-center justify-center shrink-0">
                                                    <i class="fas fa-cube text-emerald-500 text-[8px]"></i>
                                                </div>
                                                <span class="text-xs text-gray-800 dark:text-gray-200 flex-1 font-medium"><?= htmlspecialchars($it['material_name']) ?></span>
                                                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/40 px-2 py-0.5 rounded-full"><?= (int)$it['quantity'] ?></span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php $stepNum++; ?>
                                <div class="flex gap-3">
                                    <div class="flex flex-col items-center">
                                        <div class="w-7 h-7 rounded-full bg-blue-500 flex items-center justify-center shadow-md shrink-0" style="box-shadow:0 0 0 3px #dbeafe;">
                                            <span class="text-[10px] font-bold text-white"><?= $stepNum ?></span>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-bold text-blue-700 dark:text-blue-300"><i class="fas fa-flag-checkered mr-1"></i>Antar ke Lokasi</p>
                                        <p class="text-xs text-blue-600 dark:text-blue-400 mt-0.5">
                                            <i class="fas fa-user text-[9px] mr-0.5"></i><?= htmlspecialchars($req['requester_name']) ?>
                                            <span class="text-gray-300 dark:text-gray-600 mx-0.5">•</span>
                                            <?= count($items) ?> item
                                        </p>
                                    </div>
                                </div>
                        </div>
                    </div>
                    <?php if ($approval && !empty($approval['estimated_delivery'])): ?>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3"><i class="fas fa-calendar-alt"></i> Est: <?= date('d M Y, H:i', strtotime($approval['estimated_delivery'])) ?></p>
                    <?php endif; ?>
                    <?php if ($approval && !empty($approval['photo_path'])): ?>
                    <img src="./<?= ltrim($approval['photo_path'], '/') ?>" alt="Material" class="w-full h-32 object-cover rounded-lg border mb-3 cursor-pointer" onclick="window.open(this.src,'_blank')" />
                    <?php endif; ?>

                    <?php if ($isPickup): ?>
                    
                    <form class="driver-pickup-form border-t border-gray-100 dark:border-gray-700 pt-3 mt-3" data-request-id="<?= $req['id'] ?>">
                        <div class="mb-3">
                            <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5"><i class="fas fa-clock"></i> Estimasi Pengiriman</label>
                            <select name="delivery_eta" required class="w-full px-3.5 py-3 bg-white dark:bg-gray-700 border-2 border-gray-200 dark:border-gray-600 rounded-xl text-sm text-gray-800 dark:text-gray-100 focus:border-emerald-500 focus:outline-none driver-eta-select">
                                <option value="">Pilih estimasi waktu...</option>
                                <option value="15">15 menit</option>
                                <option value="30">30 menit</option>
                                <option value="45">45 menit</option>
                                <option value="60">1 jam</option>
                                <option value="90">1.5 jam</option>
                                <option value="120">2 jam</option>
                                <option value="180">3 jam</option>
                                <option value="240">4 jam</option>
                                <option value="300">5 jam</option>
                                <option value="360">6 jam</option>
                            </select>
                        </div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5"><i class="fas fa-camera"></i> Foto Bukti Pickup</label>
                        <div id="driverCamContainer_<?= $req['id'] ?>" class="bg-gray-100 dark:bg-gray-700 rounded-xl overflow-hidden mb-2">
                            <video id="driverVideo_<?= $req['id'] ?>" class="w-full aspect-[4/3] object-cover bg-black" style="display:none;" autoplay playsinline muted></video>
                            <div id="driverPlaceholder_<?= $req['id'] ?>" class="w-full aspect-[4/3] flex items-center justify-center text-gray-400 text-xs">Tap tombol untuk foto</div>
                        </div>
                        <div class="flex gap-2 mb-2">
                            <button type="button" class="driver-open-cam flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-bold rounded-xl transition" data-id="<?= $req['id'] ?>"><i class="fas fa-camera"></i> Buka Kamera</button>
                            <button type="button" class="driver-close-cam flex-1 py-2.5 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 text-xs font-bold rounded-xl transition hidden" data-id="<?= $req['id'] ?>"><i class="fas fa-times"></i> Tutup</button>
                        </div>
                        <button type="button" class="driver-capture w-full py-3 bg-green-600 hover:bg-green-700 active:bg-green-800 text-white text-sm font-bold rounded-xl transition hidden mb-2" data-id="<?= $req['id'] ?>"><i class="fas fa-camera-retro"></i> AMBIL FOTO</button>
                        <input type="file" class="hidden driver-photo-input" data-id="<?= $req['id'] ?>" accept="image/*" capture="environment" />
                        <canvas class="hidden driver-canvas" data-id="<?= $req['id'] ?>"></canvas>
                        <div class="driver-preview" data-id="<?= $req['id'] ?>"></div>
                        <button type="submit" class="w-full mt-2 py-3 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-sm font-bold rounded-xl transition shadow-sm"><i class="fas fa-check-circle"></i> Konfirmasi Pickup</button>
                    </form>
                    <?php else: ?>
                    
                    <?php if (!empty($req['driver_pickup_photo'])): ?>
                    <div class="mb-3">
                        <p class="text-[10px] text-gray-400 mb-1">Foto Pickup:</p>
                        <img src="./<?= ltrim($req['driver_pickup_photo'], '/') ?>" alt="Pickup" class="w-20 h-20 object-cover rounded-lg border cursor-pointer" onclick="window.open(this.src,'_blank')" />
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($req['driver_delivery_eta'])): ?>
                    <div class="mb-3 delivery-eta-countdown bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700 rounded-lg px-3 py-2" data-eta="<?= htmlspecialchars($req['driver_delivery_eta']) ?>">
                        <p class="text-xs font-bold text-purple-700 dark:text-purple-300 eta-text"><i class="fas fa-clock"></i> Menghitung...</p>
                    </div>
                    <?php endif; ?>
                    <form class="driver-deliver-form border-t border-gray-100 dark:border-gray-700 pt-3 mt-3" data-request-id="<?= $req['id'] ?>">
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5"><i class="fas fa-camera"></i> Foto Bukti Pengiriman</label>
                        <div id="deliverCamContainer_<?= $req['id'] ?>" class="bg-gray-100 dark:bg-gray-700 rounded-xl overflow-hidden mb-2">
                            <video id="deliverVideo_<?= $req['id'] ?>" class="w-full aspect-[4/3] object-cover bg-black" style="display:none;" autoplay playsinline muted></video>
                            <div id="deliverPlaceholder_<?= $req['id'] ?>" class="w-full aspect-[4/3] flex items-center justify-center text-gray-400 text-xs">Tap tombol untuk foto</div>
                        </div>
                        <div class="flex gap-2 mb-2">
                            <button type="button" class="deliver-open-cam flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-bold rounded-xl transition" data-id="<?= $req['id'] ?>"><i class="fas fa-camera"></i> Buka Kamera</button>
                            <button type="button" class="deliver-close-cam flex-1 py-2.5 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 text-xs font-bold rounded-xl transition hidden" data-id="<?= $req['id'] ?>"><i class="fas fa-times"></i> Tutup</button>
                        </div>
                        <button type="button" class="deliver-capture w-full py-3 bg-green-600 hover:bg-green-700 active:bg-green-800 text-white text-sm font-bold rounded-xl transition hidden mb-2" data-id="<?= $req['id'] ?>"><i class="fas fa-camera-retro"></i> AMBIL FOTO</button>
                        <input type="file" class="hidden deliver-photo-input" data-id="<?= $req['id'] ?>" accept="image/*" capture="environment" />
                        <canvas class="hidden deliver-canvas" data-id="<?= $req['id'] ?>"></canvas>
                        <div class="deliver-preview" data-id="<?= $req['id'] ?>"></div>
                        <button type="submit" class="w-full mt-2 py-3 bg-green-600 hover:bg-green-700 active:bg-green-800 text-white text-sm font-bold rounded-xl transition shadow-sm"><i class="fas fa-check-circle"></i> Konfirmasi Sudah Diantar</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
        <p class="text-gray-400 text-sm font-medium">Tidak ada tugas pengantaran</p>
        <p class="text-xs text-gray-300 dark:text-gray-500 mt-1">Tugas muncul setelah admin approve</p>
    </div>
    <?php endif; ?>
</div>


<div class="view-tab-content hidden" id="driver-history">
    
    <div class="relative mb-4">
        <svg class="absolute left-3 top-0 bottom-0 my-auto w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" id="driverHistorySearchInput" placeholder="Cari project atau material..." class="w-full pl-9 pr-3 py-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl text-sm text-gray-800 dark:text-gray-100 focus:border-blue-500 focus:outline-none">
    </div>

    <?php if (!empty($driverHistoryGrouped)): ?>
    <div id="driverHistoryList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
    <?php foreach ($driverHistoryGrouped as $projectId => $group): ?>
    <?php
        $dCompletedReqs = array_filter($group['requests'], fn($r) => $r['status'] === 'completed');
        $dDeliveredReqs = array_filter($group['requests'], fn($r) => $r['status'] === 'delivered');
    ?>
    <div class="history-project-group bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden"
         data-project-name="<?= htmlspecialchars(strtolower($group['project_name'])) ?>"
         data-materials="<?= htmlspecialchars(strtolower(implode(' ', array_map(function($r) use ($pdo) { $items = getRequestItems($pdo, $r['id']); return implode(' ', array_map(fn($i) => $i['material_name'], $items)); }, $group['requests'])))) ?>">
        <button type="button" class="history-toggle-btn w-full flex items-center justify-between px-4 py-3.5 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors" data-project="<?= $projectId ?>">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                </div>
                <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100 truncate"><?= htmlspecialchars($group['project_name']) ?></h3>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400"><?= count($group['requests']) ?></span>
                <svg class="w-4 h-4 text-gray-400 transition-transform duration-200 history-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </button>
        <div class="history-content hidden border-t border-gray-100 dark:border-gray-700">
            <?php if (!empty($dCompletedReqs)): ?>
            
            <div class="border-b border-gray-50 dark:border-gray-700 last:border-b-0">
                <button type="button" class="history-status-toggle w-full flex items-center justify-between px-4 py-2.5 bg-emerald-50/80 dark:bg-emerald-900/20 hover:bg-emerald-100/80 dark:hover:bg-emerald-900/30 transition-colors">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="text-xs"><i class="fas fa-check-circle"></i></span>
                        <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">Selesai</span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300"><?= count($dCompletedReqs) ?></span>
                        <svg class="w-3.5 h-3.5 text-emerald-400 transition-transform duration-200 status-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </button>
                <div class="history-status-content hidden divide-y divide-gray-50 dark:divide-gray-700">
                    <?php foreach ($dCompletedReqs as $req): ?>
                    <?php $items = getRequestItems($pdo, $req['id']); ?>
                    <div class="px-4 py-3">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300"><?= htmlspecialchars($req['requester_name']) ?></p>
                            <span class="text-[11px] text-gray-400"><?= date('d M Y, H:i', strtotime($req['created_at'])) ?></span>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach ($items as $item): ?>
                            <span class="text-[10px] text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/50 px-1.5 py-0.5 rounded"><?= htmlspecialchars($item['material_name']) ?>×<?= $item['quantity'] ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($dDeliveredReqs)): ?>
            
            <div class="border-b border-gray-50 dark:border-gray-700 last:border-b-0">
                <button type="button" class="history-status-toggle w-full flex items-center justify-between px-4 py-2.5 bg-amber-50/80 dark:bg-amber-900/20 hover:bg-amber-100/80 dark:hover:bg-amber-900/30 transition-colors">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="text-xs"><i class="fas fa-box"></i></span>
                        <span class="text-xs font-semibold text-amber-700 dark:text-amber-300">Menunggu Konfirmasi</span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300"><?= count($dDeliveredReqs) ?></span>
                        <svg class="w-3.5 h-3.5 text-amber-400 transition-transform duration-200 status-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </button>
                <div class="history-status-content hidden divide-y divide-gray-50 dark:divide-gray-700">
                    <?php foreach ($dDeliveredReqs as $req): ?>
                    <?php $items = getRequestItems($pdo, $req['id']); ?>
                    <div class="px-4 py-3">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300"><?= htmlspecialchars($req['requester_name']) ?></p>
                            <span class="text-[11px] text-gray-400"><?= date('d M Y, H:i', strtotime($req['created_at'])) ?></span>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach ($items as $item): ?>
                            <span class="text-[10px] text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/50 px-1.5 py-0.5 rounded"><?= htmlspecialchars($item['material_name']) ?>×<?= $item['quantity'] ?></span>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-[11px] text-amber-500 mt-1">Menunggu konfirmasi teknisi</p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
        <p class="text-gray-400 text-sm">Belum ada riwayat pengantaran</p>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<div class="text-center py-12 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
    <p class="text-gray-400 text-sm">Akses tidak tersedia untuk role Anda</p>
</div>
<?php endif; ?>
</div>

<style>
@keyframes toast-in { from { opacity:0; transform:scale(0.8); } to { opacity:1; transform:scale(1); } }
@keyframes toast-out { from { opacity:1; transform:scale(1); } to { opacity:0; transform:scale(0.8); } }
@keyframes circle-draw { from { stroke-dashoffset:166; } to { stroke-dashoffset:0; } }
@keyframes check-draw { from { stroke-dashoffset:48; } to { stroke-dashoffset:0; } }
@keyframes x-draw { from { stroke-dashoffset:20; } to { stroke-dashoffset:0; } }
@keyframes circle-fill { from { opacity:0; transform:scale(0); } to { opacity:0.15; transform:scale(1); } }
@keyframes toast-bounce { 0%{transform:scale(0.3);opacity:0} 50%{transform:scale(1.05)} 70%{transform:scale(0.95)} 100%{transform:scale(1);opacity:1} }
.toast-overlay{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.3);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px)}
.toast-card{background:#fff;border-radius:1.25rem;padding:2rem 1.5rem 1.5rem;text-align:center;min-width:260px;max-width:320px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);animation:toast-bounce 0.5s cubic-bezier(0.175,0.885,0.32,1.275)}
.toast-card.closing{animation:toast-out 0.3s ease forwards}
.toast-icon-wrap{width:80px;height:80px;margin:0 auto 1rem;position:relative}
.toast-circle{fill:none;stroke-width:3;stroke-linecap:round;stroke-dasharray:166;stroke-dashoffset:166;animation:circle-draw 0.6s 0.1s cubic-bezier(0.65,0,0.45,1) forwards}
.toast-check{fill:none;stroke:#fff;stroke-width:3.5;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:48;stroke-dashoffset:48;animation:check-draw 0.35s 0.5s cubic-bezier(0.65,0,0.45,1) forwards}
.toast-x-line{fill:none;stroke:#fff;stroke-width:3.5;stroke-linecap:round;stroke-dasharray:20;stroke-dashoffset:20;animation:x-draw 0.3s 0.5s cubic-bezier(0.65,0,0.45,1) forwards}
.toast-bg-fill{transform-origin:center;animation:circle-fill 0.4s 0.4s ease forwards;opacity:0}
.dark .toast-card{background:#1f2937;color:#e5e7eb}
</style>
<script>
function showToast(message, type) {
    type = type || 'info';
    var isMain = (type === 'success' || type === 'error');
    if (!isMain) {
        var colors = { warning: '#eab308', info: '#3b82f6' };
        var icons = { warning: '<i class="fas fa-exclamation-triangle"></i>', info: '<i class="fas fa-info-circle"></i>' };
        var bar = document.createElement('div');
        bar.style.cssText = 'position:fixed;top:1rem;left:1rem;right:1rem;z-index:99999;display:flex;align-items:center;gap:0.75rem;padding:0.75rem 1rem;border-radius:0.75rem;color:#fff;font-size:0.875rem;font-weight:500;box-shadow:0 10px 25px rgba(0,0,0,0.15);transform:translateY(-120%);transition:transform 0.3s;background:' + (colors[type] || colors.info);
        bar.innerHTML = '<span style="font-size:1.2rem">' + (icons[type] || icons.info) + '</span><span style="flex:1">' + message + '</span>';
        document.body.appendChild(bar);
        requestAnimationFrame(function() { bar.style.transform = 'translateY(0)'; });
        setTimeout(function() { bar.style.transform = 'translateY(-120%)'; setTimeout(function() { bar.remove(); }, 300); }, 3000);
        return;
    }
    var overlay = document.createElement('div');
    overlay.className = 'toast-overlay';
    var circleColor = type === 'success' ? '#22c55e' : '#ef4444';
    var fillColor = type === 'success' ? '#22c55e' : '#ef4444';
    var iconSvg = type === 'success'
        ? '<circle class="toast-bg-fill" cx="26" cy="26" r="25" fill="' + fillColor + '"/><circle class="toast-circle" cx="26" cy="26" r="25" stroke="' + circleColor + '"/><path class="toast-check" d="M14 27l8 8 16-16"/>'
        : '<circle class="toast-bg-fill" cx="26" cy="26" r="25" fill="' + fillColor + '"/><circle class="toast-circle" cx="26" cy="26" r="25" stroke="' + circleColor + '"/><line class="toast-x-line" x1="18" y1="18" x2="34" y2="34"/><line class="toast-x-line" x1="34" y1="18" x2="18" y2="34" style="animation-delay:0.6s"/>';
    overlay.innerHTML = '<div class="toast-card"><div class="toast-icon-wrap"><svg viewBox="0 0 52 52" width="80" height="80">' + iconSvg + '</svg></div><p style="font-size:1rem;font-weight:700;color:' + (type === 'success' ? '#16a34a' : '#dc2626') + ';margin-bottom:0.25rem">' + (type === 'success' ? 'Berhasil!' : 'Gagal!') + '</p><p style="font-size:0.875rem;color:#6b7280">' + message + '</p></div>';
    document.body.appendChild(overlay);
    overlay.addEventListener('click', function(e) { if (e.target === overlay) closeToast(); });
    function closeToast() {
        var card = overlay.querySelector('.toast-card');
        if (card) card.classList.add('closing');
        overlay.style.opacity = '0'; overlay.style.transition = 'opacity 0.3s';
        setTimeout(function() { overlay.remove(); }, 300);
    }
    setTimeout(closeToast, 2500);
}

document.querySelectorAll('.view-tab').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var tabName = this.dataset.tab;
        document.querySelectorAll('.view-tab-content').forEach(function(c) { c.classList.add('hidden'); });
        document.querySelectorAll('.view-tab').forEach(function(t) {
            t.classList.remove('bg-white', 'dark:bg-gray-800', 'text-gray-800', 'dark:text-gray-100', 'shadow-sm');
            t.classList.add('text-gray-500', 'dark:text-gray-400');
        });
        var target = document.getElementById(tabName);
        if (target) target.classList.remove('hidden');
        this.classList.remove('text-gray-500', 'dark:text-gray-400');
        this.classList.add('bg-white', 'dark:bg-gray-800', 'text-gray-800', 'dark:text-gray-100', 'shadow-sm');
    });
});

var toggleFormBtn = document.getElementById('toggleFormBtn');
var requestFormSection = document.getElementById('requestFormSection');
var toggleFormIcon = document.getElementById('toggleFormIcon');
if (toggleFormBtn) {
    toggleFormBtn.addEventListener('click', function() {
        var hidden = requestFormSection.classList.contains('hidden');
        requestFormSection.classList.toggle('hidden');
        toggleFormIcon.style.transform = hidden ? 'rotate(180deg)' : '';
    });
}

var addMaterialBtn = document.getElementById('addMaterialBtn');
if (addMaterialBtn) {
    addMaterialBtn.addEventListener('click', function() {
        var container = document.getElementById('materialsContainer');
        var rows = container.querySelectorAll('.material-row');
        var template = rows[0];
        var newRow = template.cloneNode(true);
        newRow.querySelectorAll('input, textarea').forEach(function(el) { el.value = ''; });
        newRow.querySelectorAll('select[name="units[]"]').forEach(function(el) { el.value = 'PCS'; });
        var removeBtn = newRow.querySelector('.remove-material');
        removeBtn.classList.remove('hidden');
        removeBtn.addEventListener('click', function() { newRow.remove(); updateRemoveButtons(); });
        container.appendChild(newRow);
        updateRemoveButtons();
        newRow.querySelector('input[name="material_names[]"]').focus();
    });
}
function updateRemoveButtons() {
    var container = document.getElementById('materialsContainer');
    if (!container) return;
    var rows = container.querySelectorAll('.material-row');
    rows.forEach(function(row) {
        var btn = row.querySelector('.remove-material');
        if (btn) btn.classList.toggle('hidden', rows.length <= 1);
    });
}

var techSearchInput = document.getElementById('techSearchInput');
var techStatusFilter = document.getElementById('techStatusFilter');
var techRequestList = document.getElementById('techRequestList');
var techResultCount = document.getElementById('techResultCount');
function filterTech() {
    if (!techRequestList) return;
    var term = (techSearchInput ? techSearchInput.value : '').toLowerCase().trim();
    var status = techStatusFilter ? techStatusFilter.value : 'all';
    var visible = 0;
    techRequestList.querySelectorAll('.tech-request-card').forEach(function(card) {
        var proj = card.dataset.project || '';
        var mats = card.dataset.materials || '';
        var s = card.dataset.status || '';
        var matchSearch = !term || proj.indexOf(term) >= 0 || mats.indexOf(term) >= 0;
        var matchStatus = status === 'all' || s === status;
        card.style.display = (matchSearch && matchStatus) ? '' : 'none';
        if (matchSearch && matchStatus) visible++;
    });
    if (techResultCount) techResultCount.textContent = visible + ' request';
}
if (techSearchInput) techSearchInput.addEventListener('input', filterTech);
if (techStatusFilter) techStatusFilter.addEventListener('change', filterTech);

document.querySelectorAll('.history-toggle-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var content = this.parentElement.querySelector('.history-content');
        var chevron = this.querySelector('.history-chevron');
        if (content) content.classList.toggle('hidden');
        if (chevron) chevron.style.transform = content && !content.classList.contains('hidden') ? 'rotate(180deg)' : '';
    });
});
document.querySelectorAll('.history-status-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var content = this.nextElementSibling;
        var chevron = this.querySelector('.status-chevron');
        if (content) content.classList.toggle('hidden');
        if (chevron) chevron.style.transform = content && !content.classList.contains('hidden') ? 'rotate(180deg)' : '';
    });
});
var historySearchInput = document.getElementById('historySearchInput');
var driverHistorySearchInput = document.getElementById('driverHistorySearchInput');
function setupHistorySearch(input) {
    if (!input) return;
    input.addEventListener('input', function() {
        var term = this.value.toLowerCase().trim();
        var container = this.closest('.view-tab-content');
        if (!container) return;
        container.querySelectorAll('.history-project-group').forEach(function(group) {
            if (!term) { group.style.display = ''; return; }
            var projectName = group.dataset.projectName || '';
            var materials = group.dataset.materials || '';
            group.style.display = (projectName.indexOf(term) >= 0 || materials.indexOf(term) >= 0) ? '' : 'none';
        });
    });
}
setupHistorySearch(historySearchInput);
setupHistorySearch(driverHistorySearchInput);

document.querySelectorAll('.admin-hist-project-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var content = this.nextElementSibling;
        var chevron = this.querySelector('.admin-hist-chevron');
        if (content) content.classList.toggle('hidden');
        if (chevron) chevron.style.transform = content && !content.classList.contains('hidden') ? 'rotate(180deg)' : '';
    });
});
document.querySelectorAll('.admin-hist-status-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var content = this.nextElementSibling;
        var chevron = this.querySelector('.admin-hist-status-chevron');
        if (content) content.classList.toggle('hidden');
        if (chevron) chevron.style.transform = content && !content.classList.contains('hidden') ? 'rotate(180deg)' : '';
    });
});
document.querySelectorAll('.admin-hist-req-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var detail = this.nextElementSibling;
        if (detail) detail.classList.toggle('hidden');
    });
});

var adminHistSearch = document.getElementById('adminHistorySearch');
function filterAdminHistory() {
    var term = (adminHistSearch ? adminHistSearch.value : '').toLowerCase().trim();
    var projects = document.querySelectorAll('.admin-history-project');
    projects.forEach(function(p) {
        if (!term) { p.style.display = ''; return; }
        var pName = p.dataset.projectName || '';
        var mats = p.dataset.materials || '';
        var req = p.dataset.requester || '';
        p.style.display = (pName.indexOf(term) >= 0 || mats.indexOf(term) >= 0 || req.indexOf(term) >= 0) ? '' : 'none';
    });
}
if (adminHistSearch) adminHistSearch.addEventListener('input', filterAdminHistory);

document.querySelectorAll('.tech-confirm-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id = this.dataset.id;
        showConfirmModal({
            icon: 'confirm',
            title: 'Konfirmasi Penerimaan',
            message: 'Apakah Anda yakin barang sudah diterima dengan baik?',
            confirmText: 'Ya, Sudah Diterima',
            confirmColor: 'bg-emerald-500 hover:bg-emerald-600',
            onConfirm: function() {
                fetch('./app/action/handle-material-request.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=technician_confirm&request_id=' + id
                }).then(function(r) { return r.json(); }).then(function(data) {
                    if (data.success) { showToast('Barang dikonfirmasi diterima!', 'success'); setTimeout(function() { location.reload(); }, 1500); }
                    else showToast(data.message, 'error');
                }).catch(function() { showToast('Terjadi kesalahan', 'error'); });
            }
        });
    });
});

document.querySelectorAll('.tech-self-pickup-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id = this.dataset.id;
        showConfirmModal({
            icon: 'confirm',
            title: 'Konfirmasi Ambil Semua',
            message: 'Konfirmasi bahwa semua material sudah Anda ambil. Request akan ditandai selesai.',
            confirmText: 'Ya, Ambil Semua',
            confirmColor: 'bg-emerald-500 hover:bg-emerald-600',
            onConfirm: function() {
                fetch('./app/action/handle-material-request.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=technician_self_pickup&request_id=' + id
                }).then(function(r) { return r.json(); }).then(function(data) {
                    if (data.success) {
                        showToast(data.message || 'Pengambilan dikonfirmasi', 'success');
                        setTimeout(function() { location.reload(); }, 1200);
                    } else {
                        showToast(data.message || 'Gagal memproses', 'error');
                    }
                }).catch(function() { showToast('Terjadi kesalahan', 'error'); });
            }
        });
    });
});

document.querySelectorAll('.tech-partial-pickup-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var requestId = this.dataset.id;
        var items = [];
        try { items = JSON.parse(this.dataset.items || '[]'); } catch (e) {}
        if (!Array.isArray(items) || items.length === 0) {
            showToast('Data item tidak tersedia', 'error');
            return;
        }

        var rows = items.map(function(item, idx) {
            var qty = Number(item.qty || 0);
            var half = Number(item.half || 1);
            return (
                '<label class="flex items-center gap-2 p-2 rounded-lg bg-gray-50 dark:bg-gray-700/50">' +
                    '<input type="checkbox" class="partial-item-check w-4 h-4" data-idx="' + idx + '" checked />' +
                    '<div class="flex-1 min-w-0">' +
                        '<p class="text-sm font-medium text-gray-800 dark:text-gray-100 truncate">' + (item.name || '-') + '</p>' +
                        '<p class="text-[11px] text-gray-500">Total: ' + qty + '</p>' +
                    '</div>' +
                    '<input type="number" min="1" max="' + qty + '" value="' + Math.min(half, qty) + '" class="partial-item-qty w-16 px-2 py-1.5 border rounded-lg text-sm text-center bg-white dark:bg-gray-700" data-idx="' + idx + '" />' +
                '</label>'
            );
        }).join('');

        var overlay = document.createElement('div');
        overlay.className = 'fixed inset-0 z-[99999] flex items-center justify-center p-4';
        overlay.style.cssText = 'background:rgba(0,0,0,0.5);backdrop-filter:blur(4px);';
        overlay.innerHTML =
            '<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">' +
                '<div class="px-5 pt-5 pb-3 border-b border-gray-100 dark:border-gray-700">' +
                    '<h3 class="text-base font-bold text-gray-800 dark:text-gray-100">Ambil Setengah</h3>' +
                    '<p class="text-xs text-gray-500 mt-1">Pilih barang dan qty yang ingin diambil sekarang.</p>' +
                '</div>' +
                '<div class="p-4 space-y-2 max-h-[55vh] overflow-auto">' + rows + '</div>' +
                '<div class="px-5 pb-5 pt-2 flex gap-2">' +
                    '<button type="button" class="partial-cancel flex-1 py-2.5 rounded-xl text-sm font-semibold bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">Batal</button>' +
                    '<button type="button" class="partial-submit flex-1 py-2.5 rounded-xl text-sm font-semibold text-white bg-amber-500 hover:bg-amber-600">Simpan Pengambilan</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(overlay);

        function closeOverlay() {
            if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
        }
        overlay.querySelector('.partial-cancel').addEventListener('click', closeOverlay);
        overlay.addEventListener('click', function(e) { if (e.target === overlay) closeOverlay(); });

        overlay.querySelectorAll('.partial-item-check').forEach(function(cb) {
            cb.addEventListener('change', function() {
                var idx = this.dataset.idx;
                var qtyInput = overlay.querySelector('.partial-item-qty[data-idx="' + idx + '"]');
                if (qtyInput) qtyInput.disabled = !this.checked;
            });
        });

        overlay.querySelector('.partial-submit').addEventListener('click', function() {
            var selections = [];
            overlay.querySelectorAll('.partial-item-check:checked').forEach(function(cb) {
                var idx = Number(cb.dataset.idx || -1);
                var item = items[idx];
                var qtyInput = overlay.querySelector('.partial-item-qty[data-idx="' + idx + '"]');
                var qty = qtyInput ? Number(qtyInput.value || 0) : 0;
                if (!item || qty <= 0) return;
                qty = Math.min(qty, Number(item.qty || 0));
                if (qty > 0) {
                    selections.push({ item_id: Number(item.id), quantity: qty });
                }
            });
            if (selections.length === 0) {
                showToast('Pilih minimal 1 barang', 'warning');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'technician_partial_pickup');
            formData.append('request_id', requestId);
            selections.forEach(function(s) {
                formData.append('item_ids[]', String(s.item_id));
                formData.append('item_qtys[]', String(s.quantity));
            });

            fetch('./app/action/handle-material-request.php', { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        closeOverlay();
                        showToast(data.message || 'Pengambilan parsial tersimpan', 'success');
                        setTimeout(function() { location.reload(); }, 1200);
                    } else {
                        showToast(data.message || 'Gagal menyimpan', 'error');
                    }
                }).catch(function() { showToast('Terjadi kesalahan', 'error'); });
        });
    });
});

var tabBtns = document.querySelectorAll('.tab-btn');
var tabContents = document.querySelectorAll('.tab-content');
if (tabBtns.length > 0) {
    function activateAdminTab(tabName) {
        tabContents.forEach(function(t) { t.classList.add('hidden'); });
        tabBtns.forEach(function(b) {
            b.classList.remove('bg-white', 'dark:bg-gray-800', 'text-gray-800', 'dark:text-gray-100', 'shadow-sm');
            b.classList.add('text-gray-500', 'dark:text-gray-400');
        });
        var target = document.getElementById(tabName);
        var activeBtn = document.querySelector('.tab-btn[data-tab="' + tabName + '"]');
        if (target) target.classList.remove('hidden');
        if (activeBtn) {
            activeBtn.classList.remove('text-gray-500', 'dark:text-gray-400');
            activeBtn.classList.add('bg-white', 'dark:bg-gray-800', 'text-gray-800', 'dark:text-gray-100', 'shadow-sm');
        }
    }
    tabBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            activateAdminTab(this.dataset.tab);
        });
    });
    var requestedAdminTab = new URLSearchParams(window.location.search).get('tab');
    if (requestedAdminTab && document.getElementById(requestedAdminTab)) {
        activateAdminTab(requestedAdminTab);
    }
}

document.querySelectorAll('.accept-request').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id = this.dataset.id;
        showConfirmModal({
            icon: 'accept',
            title: 'Accept Request',
            message: 'Accept request ini untuk direview dan proses lebih lanjut?',
            confirmText: 'Ya, Accept',
            confirmColor: 'bg-emerald-500 hover:bg-emerald-600',
            onConfirm: function() {
                fetch('./app/action/handle-material-request.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=admin_accept&request_id=' + id
                }).then(function(r) { return r.json(); }).then(function(data) {
                    if (data.success) { showToast('Request diterima!', 'success'); setTimeout(function() { location.reload(); }, 1000); }
                    else showToast(data.message, 'error');
                });
            }
        });
    });
});
document.querySelectorAll('.reject-request').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id = this.dataset.id;
        showConfirmModal({
            icon: 'reject',
            title: 'Tolak Request',
            message: 'Apakah Anda yakin ingin menolak request ini?',
            showInput: true,
            inputPlaceholder: 'Alasan penolakan (opsional)...',
            confirmText: 'Ya, Tolak',
            confirmColor: 'bg-red-500 hover:bg-red-600',
            onConfirm: function(reason) {
                fetch('./app/action/handle-material-request.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=reject&request_id=' + id + '&reason=' + encodeURIComponent(reason || '')
                }).then(function(r) { return r.json(); }).then(function(data) {
                    if (data.success) { showToast('Request ditolak', 'success'); setTimeout(function() { location.reload(); }, 1000); }
                    else showToast(data.message, 'error');
                });
            }
        });
    });
});

function formatCurrencyIDR(value) {
    if (!value || isNaN(value)) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(parseFloat(value));
}

var suppliersData = <?= json_encode($suppliersList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
document.querySelectorAll('.store-name-select').forEach(function(sel) {
    suppliersData.forEach(function(s) {
        var opt = document.createElement('option');
        opt.value = s.name;
        opt.textContent = s.name + (s.address ? ' — ' + s.address.substring(0, 40) + (s.address.length > 40 ? '...' : '') : '');
        opt.dataset.address = s.address || '';
        sel.appendChild(opt);
    });
    sel.addEventListener('change', function() {
        var itemId = this.dataset.item;
        var addrInput = document.querySelector('.store-address-input[data-item="' + itemId + '"]');
        var selected = this.options[this.selectedIndex];
        if (addrInput && selected) addrInput.value = selected.dataset.address || '';
    });
});

function updateWarehouseSplit(itemId) {
    var row = document.querySelector('.item-pricing-row[data-item-id="' + itemId + '"]');
    if (!row) return;
    var needed = parseInt(row.dataset.itemQty) || 0;
    var sourceInput = row.querySelector('.item-source-input[data-item="' + itemId + '"]');
    var source = sourceInput ? sourceInput.value : 'warehouse';
    var warehouseSection = row.querySelector('.warehouse-stock-section[data-item="' + itemId + '"]');
    var storeSection = row.querySelector('.store-name-section[data-item="' + itemId + '"]');
    var splitInfo = row.querySelector('.warehouse-split-info[data-item="' + itemId + '"]');

    if (source === 'purchase') {
        if (warehouseSection) warehouseSection.classList.add('hidden');
        if (storeSection) storeSection.classList.remove('hidden');
        if (splitInfo) splitInfo.innerHTML = '';
    } else {
        if (warehouseSection) warehouseSection.classList.remove('hidden');
        var wqInput = row.querySelector('.warehouse-qty-input[data-item="' + itemId + '"]');
        var available = wqInput ? (parseInt(wqInput.value) || 0) : 0;
        var fromWarehouse = Math.min(needed, available);
        var toPurchase = Math.max(0, needed - available);

        if (splitInfo) {
            if (available >= needed) {
                splitInfo.innerHTML = '<p class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 rounded-lg px-2.5 py-1.5"><i class="fas fa-check-circle"></i> Semua dari gudang (' + fromWarehouse + ' unit)</p>';
                if (storeSection) storeSection.classList.add('hidden');
            } else if (available > 0) {
                splitInfo.innerHTML = '<div class="space-y-1">' +
                    '<p class="text-[11px] font-bold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg px-2.5 py-1.5"><i class="fas fa-exclamation-triangle"></i> Stok gudang kurang! ' + fromWarehouse + ' dari gudang, <span class="text-red-600 dark:text-red-400">' + toPurchase + ' perlu dibeli</span></p>' +
                    '</div>';
                if (storeSection) storeSection.classList.remove('hidden');
            } else {
                splitInfo.innerHTML = '<p class="text-[11px] font-bold text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg px-2.5 py-1.5"><i class="fas fa-times-circle"></i> Stok gudang kosong — semua perlu dibeli (' + needed + ' unit)</p>';
                if (storeSection) storeSection.classList.remove('hidden');
            }
        }
    }
}

function updatePhotoVisibility(form) {
    var sources = form.querySelectorAll('.item-source-input');
    var hasWarehouse = false;
    sources.forEach(function(s) { if (s.value === 'warehouse') hasWarehouse = true; });
    var photoSection = form.querySelector('.photo-section');
    if (photoSection) {
        if (hasWarehouse) {
            photoSection.style.display = '';
            photoSection.querySelector('.photo-required-badge').textContent = '*';
        } else {
            photoSection.style.display = 'none';
        }
    }
}
document.querySelectorAll('.source-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var itemId = this.dataset.item;
        var source = this.dataset.source;
        var siblings = this.parentElement.querySelectorAll('.source-toggle');
        siblings.forEach(function(s) {
            s.classList.remove('bg-blue-50', 'dark:bg-blue-900/30', 'text-blue-700', 'dark:text-blue-300', 'border-blue-200', 'dark:border-blue-700',
                               'bg-emerald-50', 'dark:bg-emerald-900/30', 'text-emerald-700', 'dark:text-emerald-300', 'border-emerald-200', 'dark:border-emerald-700');
            s.classList.add('bg-gray-100', 'dark:bg-gray-600', 'text-gray-400', 'border-gray-200', 'dark:border-gray-500');
        });
        if (source === 'purchase') {
            this.classList.remove('bg-gray-100', 'dark:bg-gray-600', 'text-gray-400', 'border-gray-200', 'dark:border-gray-500');
            this.classList.add('bg-blue-50', 'dark:bg-blue-900/30', 'text-blue-700', 'dark:text-blue-300', 'border-blue-200', 'dark:border-blue-700');
        } else {
            this.classList.remove('bg-gray-100', 'dark:bg-gray-600', 'text-gray-400', 'border-gray-200', 'dark:border-gray-500');
            this.classList.add('bg-emerald-50', 'dark:bg-emerald-900/30', 'text-emerald-700', 'dark:text-emerald-300', 'border-emerald-200', 'dark:border-emerald-700');
        }
        var input = this.closest('form').querySelector('.item-source-input[data-item="' + itemId + '"]');
        if (input) input.value = source;
        updateWarehouseSplit(itemId);
        updatePhotoVisibility(this.closest('form'));
    });
});

document.querySelectorAll('.warehouse-qty-input').forEach(function(input) {
    input.addEventListener('input', function() {
        updateWarehouseSplit(this.dataset.item);
    });
});

document.querySelectorAll('.item-pricing-row').forEach(function(row) {
    var itemId = row.dataset.itemId;
    if (itemId) updateWarehouseSplit(itemId);
});
document.querySelectorAll('.approval-form').forEach(function(f) { updatePhotoVisibility(f); });

document.querySelectorAll('.item-price-input').forEach(function(input) {
    input.addEventListener('input', function() {
        var qty = parseFloat(this.dataset.qty) || 0;
        var price = parseFloat(this.value) || 0;
        var total = qty * price;
        var row = this.closest('.item-pricing-row');
        if (row) {
            var totalEl = row.querySelector('.item-total-display');
            if (totalEl) totalEl.textContent = formatCurrencyIDR(total);
        }
        var form = this.closest('.approval-form');
        if (form) {
            var gt = 0;
            form.querySelectorAll('.item-price-input').forEach(function(pi) {
                gt += (parseFloat(pi.dataset.qty) || 0) * (parseFloat(pi.value) || 0);
            });
            var gtEl = form.querySelector('.admin-grand-total');
            if (gtEl) gtEl.textContent = formatCurrencyIDR(gt);
        }
    });
});

document.querySelectorAll('.open-approval-modal').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var rid = this.dataset.requestId;
        var modal = document.getElementById('approvalModal_' + rid);
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = '';
            document.body.style.overflow = 'hidden';
        }
    });
});
document.querySelectorAll('.close-approval-modal').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var rid = this.dataset.requestId;
        var modal = document.getElementById('approvalModal_' + rid);
        if (modal) {
            if (activeStreams[rid]) {
                activeStreams[rid].getTracks().forEach(function(t) { t.stop(); });
                delete activeStreams[rid];
            }
            modal.classList.add('hidden');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    });
});

var activeStreams = {};

document.querySelectorAll('.approval-form').forEach(function(form) {
    var requestId = form.dataset.requestId;

    var openBtn = document.getElementById('openCameraBtn_' + requestId);
    var closeBtn = document.getElementById('closeCameraBtn_' + requestId);
    var captureBtn = document.getElementById('captureBtn_' + requestId);
    var video = document.getElementById('cameraVideo_' + requestId);
    var placeholder = document.getElementById('cameraPlaceholder_' + requestId);
    var canvas = document.getElementById('photoCanvas_' + requestId);
    var photoInput = document.getElementById('photoInput_' + requestId);
    var previewDiv = document.getElementById('photoPreview_' + requestId);
    var validationDiv = document.getElementById('photoValidation_' + requestId);

    if (openBtn && video) {
        openBtn.addEventListener('click', function() {
            navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } } })
            .then(function(stream) {
                activeStreams[requestId] = stream; video.srcObject = stream; video.style.display = 'block';
                placeholder.style.display = 'none'; openBtn.classList.add('hidden'); closeBtn.classList.remove('hidden'); captureBtn.classList.remove('hidden');
            }).catch(function() {
                navigator.mediaDevices.getUserMedia({ video: true }).then(function(fb) {
                    activeStreams[requestId] = fb; video.srcObject = fb; video.style.display = 'block';
                    placeholder.style.display = 'none'; openBtn.classList.add('hidden'); closeBtn.classList.remove('hidden'); captureBtn.classList.remove('hidden');
                }).catch(function(e2) { showToast('Tidak dapat mengakses kamera: ' + e2.message, 'error'); });
            });
        });
        closeBtn.addEventListener('click', function() { stopCam(requestId, video, placeholder, openBtn, closeBtn, captureBtn); });
        captureBtn.addEventListener('click', function() {
            if (!video.srcObject) return;
            var ctx = canvas.getContext('2d'); canvas.width = video.videoWidth; canvas.height = video.videoHeight; ctx.drawImage(video, 0, 0);
            canvas.toBlob(function(blob) {
                if (!blob) return showToast('Gagal mengambil foto', 'error');
                var ts = new Date().toISOString().replace(/[:.]/g, '-');
                var file = new File([blob], 'material_' + ts + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
                var dt = new DataTransfer(); dt.items.add(file); photoInput.files = dt.files;
                if (previewDiv) { var img = document.createElement('img'); img.src = canvas.toDataURL('image/jpeg', 0.9); img.className = 'w-full h-32 object-cover rounded-xl border-2 border-green-500'; previewDiv.innerHTML = ''; previewDiv.appendChild(img); }
                if (validationDiv) validationDiv.innerHTML = '<p class="text-xs text-green-600 dark:text-green-300 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded-lg px-3 py-2 mt-1"><i class="fas fa-check"></i> Foto berhasil diambil</p>';
                stopCam(requestId, video, placeholder, openBtn, closeBtn, captureBtn);
            }, 'image/jpeg', 0.9);
        });
    }

    var estInput = form.querySelector('.est-delivery-input');
    var estPreview = document.getElementById('deliveryPreview_' + requestId);
    if (estInput && estPreview) {
        estInput.addEventListener('change', function() {
            if (this.value) {
                var d = new Date(this.value);
                estPreview.textContent = d.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }) + ' WIB';
                estPreview.style.display = 'block';
            }
        });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var sources = form.querySelectorAll('.item-source-input');
        var hasWarehouse = false;
        sources.forEach(function(s) { if (s.value === 'warehouse') hasWarehouse = true; });
        if (hasWarehouse) {
            var pi = form.querySelector('.material-photo-input');
            if (!pi || !pi.files || !pi.files[0]) { showToast('Foto material wajib untuk sumber gudang!', 'warning'); return; }
        }
        var formData = new FormData(form);
        formData.append('action', 'save_approval');
        formData.append('request_id', requestId);
        fetch('./app/action/handle-material-request.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) { showToast('Approval berhasil disimpan!', 'success'); setTimeout(function() { location.reload(); }, 1500); }
                else showToast(data.message, 'error');
            }).catch(function() { showToast('Terjadi kesalahan', 'error'); });
    });
});

var returnProjectSelect = document.getElementById('returnProjectSelect');
if (returnProjectSelect) {
    returnProjectSelect.addEventListener('change', function() {
        var projectId = this.value;
        var list = document.getElementById('returnItemsList');
        var loading = document.getElementById('returnItemsLoading');
        var empty = document.getElementById('returnItemsEmpty');
        
        list.innerHTML = '';
        empty.classList.add('hidden');
        
        if (!projectId) {
            list.innerHTML = '<p class="text-xs text-gray-400 italic">Pilih project terlebih dahulu</p>';
            return;
        }
        
        loading.classList.remove('hidden');
        
        fetch('./app/action/handle-material-request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_rab_items', project_id: projectId })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            loading.classList.add('hidden');
            if (!data.success || !data.data || data.data.length === 0) {
                empty.classList.remove('hidden');
                return;
            }
            
            data.data.forEach(function(item) {
                var row = document.createElement('div');
                row.className = 'flex items-center gap-2 p-2.5 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-100 dark:border-gray-600 return-rab-row';
                var sectionLabel = item.section === 'A' ? 'Beli' : 'Gudang';
                var sectionColor = item.section === 'A' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300';
                row.innerHTML = 
                    '<label class="flex items-center gap-2 flex-1 cursor-pointer min-w-0">' +
                        '<input type="checkbox" name="return_checks[]" value="' + item.rab_item_id + '" data-name="' + item.item_name.replace(/"/g, '&quot;') + '" data-max="' + item.max_returnable + '" class="return-item-check w-4 h-4 rounded border-gray-300 text-orange-600 focus:ring-orange-500 shrink-0" />' +
                        '<div class="min-w-0 flex-1">' +
                            '<p class="text-sm font-medium text-gray-800 dark:text-gray-100 truncate">' + item.item_name + '</p>' +
                            '<div class="flex items-center gap-1.5 mt-0.5">' +
                                '<span class="text-[10px] px-1.5 py-0.5 rounded-full font-bold ' + sectionColor + '">' + sectionLabel + '</span>' +
                                '<span class="text-[10px] text-gray-400">Stok RAB: ' + item.current_qty + ' ' + item.unit + '</span>' +
                            '</div>' +
                        '</div>' +
                    '</label>' +
                    '<input type="number" name="return_quantities[]" min="1" max="' + item.max_returnable + '" value="1" disabled class="return-qty-input w-16 px-2 py-1.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg text-sm text-center disabled:opacity-40" />';
                list.appendChild(row);
            });
            
            list.querySelectorAll('.return-item-check').forEach(function(cb) {
                cb.addEventListener('change', function() {
                    var qtyInput = this.closest('.return-rab-row').querySelector('.return-qty-input');
                    qtyInput.disabled = !this.checked;
                    if (this.checked) {
                        qtyInput.focus();
                        qtyInput.required = true;
                    } else {
                        qtyInput.required = false;
                    }
                });
            });
        })
        .catch(function() {
            loading.classList.add('hidden');
            list.innerHTML = '<p class="text-xs text-red-500">Gagal memuat data RAB</p>';
        });
    });
}

var returnForm = document.getElementById('returnForm');
if (returnForm) {
    returnForm.addEventListener('submit', function(e) {
        e.preventDefault();
        var projectId = this.querySelector('[name=return_project_id]').value;
        if (!projectId) { showToast('Pilih project dulu', 'warning'); return; }
        
        var items = [];
        this.querySelectorAll('.return-item-check:checked').forEach(function(cb) {
            var row = cb.closest('.return-rab-row');
            var qty = parseInt(row.querySelector('.return-qty-input').value) || 0;
            var maxQty = parseInt(cb.dataset.max) || 0;
            if (qty > maxQty) qty = maxQty;
            if (qty > 0) {
                items.push({ material_name: cb.dataset.name, quantity: qty, rab_item_id: parseInt(cb.value) });
            }
        });
        
        if (items.length === 0) { showToast('Pilih minimal 1 item untuk dikembalikan', 'warning'); return; }
        var note = this.querySelector('[name=return_note]').value;
        var btn = document.getElementById('submitReturnBtn');
        btn.disabled = true;
        btn.textContent = 'Mengirim...';
        fetch('./app/action/handle-material-request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'submit_return', project_id: projectId, items: items, note: note })
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.success) {
                showToast(data.message || 'Pengembalian berhasil diajukan!', 'success');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                showToast(data.message || 'Gagal mengajukan pengembalian', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-undo-alt"></i> Ajukan Pengembalian';
            }
        }).catch(function() {
            showToast('Terjadi kesalahan', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-undo-alt"></i> Ajukan Pengembalian';
        });
    });
}

document.querySelectorAll('.admin-receive-return-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var returnId = this.dataset.returnId;
        if (!confirm('Konfirmasi terima barang pengembalian ini? RAB akan di-update otomatis.')) return;
        var self = this;
        self.disabled = true;
        self.textContent = 'Memproses...';
        fetch('./app/action/handle-material-request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'admin_receive_return', return_id: returnId })
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.success) {
                showToast(data.message || 'Barang diterima & RAB diupdate!', 'success');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                showToast(data.message || 'Gagal menerima barang', 'error');
                self.disabled = false;
                self.innerHTML = '<i class="fas fa-box"></i> Terima Barang & Update RAB';
            }
        }).catch(function() {
            showToast('Terjadi kesalahan', 'error');
            self.disabled = false;
            self.innerHTML = '<i class="fas fa-box"></i> Terima Barang & Update RAB';
        });
    });
});

document.querySelectorAll('.delivery-method-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var method = this.dataset.method;
        var siblings = this.parentElement.querySelectorAll('.delivery-method-toggle');
        siblings.forEach(function(s) {
            s.classList.remove('bg-purple-50', 'dark:bg-purple-900/30', 'text-purple-700', 'dark:text-purple-300', 'border-purple-300', 'dark:border-purple-600',
                               'bg-teal-50', 'dark:bg-teal-900/30', 'text-teal-700', 'dark:text-teal-300', 'border-teal-300', 'dark:border-teal-600');
            s.classList.add('bg-gray-100', 'dark:bg-gray-600', 'text-gray-400', 'border-gray-200', 'dark:border-gray-500');
        });
        if (method === 'driver') {
            this.classList.remove('bg-gray-100', 'dark:bg-gray-600', 'text-gray-400', 'border-gray-200', 'dark:border-gray-500');
            this.classList.add('bg-purple-50', 'dark:bg-purple-900/30', 'text-purple-700', 'dark:text-purple-300', 'border-purple-300', 'dark:border-purple-600');
        } else {
            this.classList.remove('bg-gray-100', 'dark:bg-gray-600', 'text-gray-400', 'border-gray-200', 'dark:border-gray-500');
            this.classList.add('bg-teal-50', 'dark:bg-teal-900/30', 'text-teal-700', 'dark:text-teal-300', 'border-teal-300', 'dark:border-teal-600');
        }
        var form = this.closest('form');
        var input = form.querySelector('.delivery-method-input');
        if (input) input.value = method;
        var submitBtn = form.querySelector('.approval-submit-btn');
        if (submitBtn) {
            submitBtn.innerHTML = method === 'technician_pickup'
                ? '<i class="fas fa-check-circle"></i> Sediakan & Technician Ambil Sendiri'
                : '<i class="fas fa-check-circle"></i> Sediakan & Kirim ke Driver';
        }
    });
});

function stopCam(id, video, placeholder, openBtn, closeBtn, captureBtn) {
    if (activeStreams[id]) { activeStreams[id].getTracks().forEach(function(t) { t.stop(); }); delete activeStreams[id]; }
    video.srcObject = null; video.style.display = 'none'; placeholder.style.display = 'flex';
    openBtn.classList.remove('hidden'); closeBtn.classList.add('hidden'); captureBtn.classList.add('hidden');
}

var driverStreams = {};
function setupDriverCamera(prefix, formSelector, actionName) {
    document.querySelectorAll(formSelector).forEach(function(form) {
        var reqId = form.dataset.requestId;
        var openBtn = form.querySelector('.' + prefix + '-open-cam');
        var closeBtn = form.querySelector('.' + prefix + '-close-cam');
        var captureBtn = form.querySelector('.' + prefix + '-capture');
        var photoInput = form.querySelector('.' + prefix + '-photo-input');
        var canvas = form.querySelector('.' + prefix + '-canvas');
        var preview = form.querySelector('.' + prefix + '-preview');
        var videoId = prefix === 'driver' ? 'driverVideo_' : 'deliverVideo_';
        var placeholderId = prefix === 'driver' ? 'driverPlaceholder_' : 'deliverPlaceholder_';
        var video = document.getElementById(videoId + reqId);
        var placeholder = document.getElementById(placeholderId + reqId);
        if (!openBtn || !video) return;

        openBtn.addEventListener('click', function() {
            navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } } })
            .then(function(stream) {
                driverStreams[prefix + '_' + reqId] = stream; video.srcObject = stream; video.style.display = 'block';
                placeholder.style.display = 'none'; openBtn.classList.add('hidden'); closeBtn.classList.remove('hidden'); captureBtn.classList.remove('hidden');
            }).catch(function() {
                navigator.mediaDevices.getUserMedia({ video: true }).then(function(fb) {
                    driverStreams[prefix + '_' + reqId] = fb; video.srcObject = fb; video.style.display = 'block';
                    placeholder.style.display = 'none'; openBtn.classList.add('hidden'); closeBtn.classList.remove('hidden'); captureBtn.classList.remove('hidden');
                }).catch(function() { showToast('Tidak dapat mengakses kamera', 'error'); });
            });
        });
        closeBtn.addEventListener('click', function() {
            var key = prefix + '_' + reqId;
            if (driverStreams[key]) { driverStreams[key].getTracks().forEach(function(t) { t.stop(); }); delete driverStreams[key]; }
            video.srcObject = null; video.style.display = 'none'; placeholder.style.display = 'flex';
            openBtn.classList.remove('hidden'); closeBtn.classList.add('hidden'); captureBtn.classList.add('hidden');
        });
        captureBtn.addEventListener('click', function() {
            if (!video.srcObject) return;
            var ctx = canvas.getContext('2d'); canvas.width = video.videoWidth; canvas.height = video.videoHeight; ctx.drawImage(video, 0, 0);
            canvas.toBlob(function(blob) {
                if (!blob) return showToast('Gagal mengambil foto', 'error');
                var ts = new Date().toISOString().replace(/[:.]/g, '-');
                var file = new File([blob], prefix + '_' + ts + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
                var dt = new DataTransfer(); dt.items.add(file); photoInput.files = dt.files;
                if (preview) { var img = document.createElement('img'); img.src = canvas.toDataURL('image/jpeg', 0.9); img.className = 'w-full h-24 object-cover rounded-xl border-2 border-green-500 mt-2'; preview.innerHTML = ''; preview.appendChild(img); }
                closeBtn.click();
            }, 'image/jpeg', 0.9);
        });
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!photoInput.files || !photoInput.files[0]) { showToast('Foto wajib diambil!', 'warning'); return; }
            var formData = new FormData();
            formData.append('action', actionName);
            formData.append('request_id', reqId);
            formData.append('photo', photoInput.files[0]);
            if (actionName === 'driver_pickup') {
                var etaSelect = form.querySelector('.driver-eta-select');
                if (etaSelect) {
                    if (!etaSelect.value) { showToast('Pilih estimasi pengiriman!', 'warning'); return; }
                    formData.append('delivery_eta_minutes', etaSelect.value);
                }
            }
            fetch('./app/action/handle-material-request.php', { method: 'POST', body: formData })
                .then(function(r) { return r.json(); }).then(function(data) {
                    if (data.success) { showToast(data.message, 'success'); setTimeout(function() { location.reload(); }, 1500); }
                    else showToast(data.message, 'error');
                }).catch(function() { showToast('Terjadi kesalahan', 'error'); });
        });
    });
}
setupDriverCamera('driver', '.driver-pickup-form', 'driver_pickup');
setupDriverCamera('deliver', '.driver-deliver-form', 'driver_deliver');

function updateETACountdowns() {
    document.querySelectorAll('.delivery-eta-countdown').forEach(function(el) {
        var eta = el.dataset.eta;
        if (!eta) return;
        var etaDate = new Date(eta);
        var now = new Date();
        var diffMs = etaDate - now;
        var textEl = el.querySelector('.eta-text');
        if (!textEl) return;
        if (diffMs <= 0) {
            textEl.innerHTML = '<i class="fas fa-clock"></i> <span class="text-red-600 dark:text-red-400">Sudah melewati estimasi!</span>';
            el.classList.remove('bg-purple-50', 'dark:bg-purple-900/20', 'border-purple-200', 'dark:border-purple-700');
            el.classList.add('bg-red-50', 'dark:bg-red-900/20', 'border-red-200', 'dark:border-red-700');
            textEl.classList.remove('text-purple-700', 'dark:text-purple-300');
            textEl.classList.add('text-red-700', 'dark:text-red-300');
        } else {
            var totalMins = Math.ceil(diffMs / 60000);
            var label;
            if (totalMins < 60) {
                label = totalMins + ' menit lagi';
            } else {
                var hrs = Math.floor(totalMins / 60);
                var mins = totalMins % 60;
                label = hrs + ' jam' + (mins > 0 ? ' ' + mins + ' menit' : '') + ' lagi';
            }
            textEl.innerHTML = '<i class="fas fa-clock"></i> Estimasi tiba: ' + label;
        }
    });
}
updateETACountdowns();
setInterval(updateETACountdowns, 30000);

window.addEventListener('beforeunload', function() {
    Object.values(activeStreams).forEach(function(s) { s.getTracks().forEach(function(t) { t.stop(); }); });
    Object.values(driverStreams).forEach(function(s) { s.getTracks().forEach(function(t) { t.stop(); }); });
});

function showConfirmModal(opts) {
    var icon = opts.icon || 'confirm';
    var iconHtml = {
        confirm: '<div class="mx-auto w-14 h-14 rounded-full bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center mb-3"><svg class="w-7 h-7 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>',
        accept: '<div class="mx-auto w-14 h-14 rounded-full bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center mb-3"><svg class="w-7 h-7 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>',
        reject: '<div class="mx-auto w-14 h-14 rounded-full bg-red-50 dark:bg-red-900/30 flex items-center justify-center mb-3"><svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg></div>'
    };
    var confirmColor = opts.confirmColor || 'bg-blue-500 hover:bg-blue-600';
    var confirmText = opts.confirmText || 'Ya, Lanjutkan';
    var cancelText = opts.cancelText || 'Batal';

    var overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 z-[99999] flex items-center justify-center p-4';
    overlay.style.cssText = 'background:rgba(0,0,0,0.5);backdrop-filter:blur(4px);animation:cmFadeIn .2s ease';

    var html = '<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden" style="animation:cmSlideUp .25s ease">';
    html += '<div class="px-6 pt-6 pb-4 text-center">';
    html += (iconHtml[icon] || iconHtml.confirm);
    html += '<h3 class="text-base font-bold text-gray-800 dark:text-gray-100 mb-1">' + (opts.title || 'Konfirmasi') + '</h3>';
    html += '<p class="text-sm text-gray-500 dark:text-gray-400">' + (opts.message || '') + '</p>';
    if (opts.showInput) {
        html += '<textarea id="cm-input" rows="2" placeholder="' + (opts.inputPlaceholder || '') + '" class="mt-3 w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl text-sm text-gray-800 dark:text-gray-100 focus:border-blue-500 focus:outline-none resize-none"></textarea>';
    }
    html += '</div>';
    html += '<div class="flex gap-2 px-6 pb-6">';
    html += '<button type="button" class="cm-cancel flex-1 py-2.5 rounded-xl text-sm font-semibold bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition">' + cancelText + '</button>';
    html += '<button type="button" class="cm-confirm flex-1 py-2.5 rounded-xl text-sm font-semibold text-white ' + confirmColor + ' transition">' + confirmText + '</button>';
    html += '</div></div>';

    overlay.innerHTML = html;
    document.body.appendChild(overlay);

    var inputEl = overlay.querySelector('#cm-input');
    if (inputEl) setTimeout(function() { inputEl.focus(); }, 100);

    overlay.querySelector('.cm-cancel').addEventListener('click', function() {
        closeModal();
        if (opts.onCancel) opts.onCancel();
    });
    overlay.querySelector('.cm-confirm').addEventListener('click', function() {
        var val = inputEl ? inputEl.value : null;
        closeModal();
        if (opts.onConfirm) opts.onConfirm(val);
    });
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) { closeModal(); if (opts.onCancel) opts.onCancel(); }
    });

    function closeModal() {
        overlay.style.opacity = '0';
        overlay.style.transition = 'opacity .2s';
        setTimeout(function() { overlay.remove(); }, 200);
    }
}
</script>
<style>
@keyframes cmFadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes cmSlideUp { from { opacity: 0; transform: translateY(20px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }
</style>
