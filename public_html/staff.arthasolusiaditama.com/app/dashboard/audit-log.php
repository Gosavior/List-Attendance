<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!in_array($_SESSION['role'] ?? '', ['administrator', 'direktur'])) {
    http_response_code(403);
    exit('Akses ditolak.');
}

// Ensure table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED DEFAULT NULL,
            action VARCHAR(100) NOT NULL,
            target_type VARCHAR(50) DEFAULT NULL,
            target_id INT UNSIGNED DEFAULT NULL,
            target_user_id INT UNSIGNED DEFAULT NULL,
            details TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_audit_user (user_id),
            INDEX idx_audit_action (action),
            INDEX idx_audit_target (target_type, target_id),
            INDEX idx_audit_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {}

// Filters
$filterAction = trim($_GET['action_filter'] ?? '');
$filterUser = (int)($_GET['user_filter'] ?? 0);
$filterDate = trim($_GET['date_filter'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Build query
$where = [];
$params = [];

if ($filterAction) {
    $where[] = "a.action LIKE ?";
    $params[] = "%{$filterAction}%";
}
if ($filterUser > 0) {
    $where[] = "a.user_id = ?";
    $params[] = $filterUser;
}
if ($filterDate) {
    $where[] = "DATE(a.created_at) = ?";
    $params[] = $filterDate;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs a {$whereSQL}");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

// Fetch
$stmt = $pdo->prepare("
    SELECT a.*, u.full_name as actor_name, tu.full_name as target_name
    FROM audit_logs a
    LEFT JOIN users u ON u.id = a.user_id
    LEFT JOIN users tu ON tu.id = a.target_user_id
    {$whereSQL}
    ORDER BY a.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users for filter dropdown
$admins = $pdo->query("SELECT id, full_name, role FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

// Action labels
$actionLabels = [
    'approve_leave_request' => ['Approve Izin/Sakit', 'text-green-700 bg-green-100 dark:text-green-400 dark:bg-green-900/30'],
    'reject_leave_request' => ['Reject Izin/Sakit', 'text-red-700 bg-red-100 dark:text-red-400 dark:bg-red-900/30'],
    'reject_attendance_request' => ['Reject Request Absensi', 'text-red-700 bg-red-100 dark:text-red-400 dark:bg-red-900/30'],
    'approve_attendance_request' => ['Approve Request Absensi', 'text-green-700 bg-green-100 dark:text-green-400 dark:bg-green-900/30'],
    'approve_tool_permit' => ['Approve Tool Permit', 'text-green-700 bg-green-100 dark:text-green-400 dark:bg-green-900/30'],
    'reject_tool_permit' => ['Reject Tool Permit', 'text-red-700 bg-red-100 dark:text-red-400 dark:bg-red-900/30'],
    'bulk_approve_tool_permit' => ['Bulk Approve Tools', 'text-green-700 bg-green-100 dark:text-green-400 dark:bg-green-900/30'],
    'bulk_reject_tool_permit' => ['Bulk Reject Tools', 'text-red-700 bg-red-100 dark:text-red-400 dark:bg-red-900/30'],
    'password_reset' => ['Reset Password', 'text-amber-700 bg-amber-100 dark:text-amber-400 dark:bg-amber-900/30'],
    'loan_request' => ['Peminjaman Tools', 'text-blue-700 bg-blue-100 dark:text-blue-400 dark:bg-blue-900/30'],
    'return_request' => ['Pengembalian Tools', 'text-orange-700 bg-orange-100 dark:text-orange-400 dark:bg-orange-900/30'],
    'project_request' => ['Pengajuan Project', 'text-purple-700 bg-purple-100 dark:text-purple-400 dark:bg-purple-900/30'],
    'handover_request' => ['Handover Tools', 'text-indigo-700 bg-indigo-100 dark:text-indigo-400 dark:bg-indigo-900/30'],
    'project_handover' => ['Handover Project', 'text-indigo-700 bg-indigo-100 dark:text-indigo-400 dark:bg-indigo-900/30'],
    'force_return_tool' => ['Force Return Tools', 'text-red-700 bg-red-100 dark:text-red-400 dark:bg-red-900/30'],
    'assign_apd' => ['Assign APD', 'text-teal-700 bg-teal-100 dark:text-teal-400 dark:bg-teal-900/30'],
    'apd_return_request' => ['Return APD', 'text-orange-700 bg-orange-100 dark:text-orange-400 dark:bg-orange-900/30'],
    'force_return_apd' => ['Force Return APD', 'text-red-700 bg-red-100 dark:text-red-400 dark:bg-red-900/30'],
    'delete_apd' => ['Hapus APD', 'text-red-700 bg-red-100 dark:text-red-400 dark:bg-red-900/30'],
    'login' => ['Login', 'text-slate-700 bg-slate-100 dark:text-slate-300 dark:bg-slate-700'],
    'bulk_return_project' => ['Bulk Return Project', 'text-orange-700 bg-orange-100 dark:text-orange-400 dark:bg-orange-900/30'],
    'force_return_all_project' => ['Force Return All Project', 'text-red-700 bg-red-100 dark:text-red-400 dark:bg-red-900/30'],
    'edit_loan' => ['Edit Peminjaman', 'text-amber-700 bg-amber-100 dark:text-amber-400 dark:bg-amber-900/30'],
    'extend_loan' => ['Perpanjang Peminjaman', 'text-blue-700 bg-blue-100 dark:text-blue-400 dark:bg-blue-900/30'],
];
?>

<div class="al-page">
    <div class="al-header">
        <div class="al-title">
            <i class="fas fa-shield-alt"></i>
            <h1>Audit Log</h1>
        </div>
        <p class="al-subtitle">Riwayat semua aksi user di sistem</p>
    </div>

    <!-- Filters -->
    <form method="GET" action="dashboard.php" class="al-filters">
        <input type="hidden" name="page" value="audit-log">
        <div class="al-filter-row">
            <select name="user_filter" class="al-input">
                <option value="">Semua User</option>
                <?php foreach ($admins as $adm): ?>
                <option value="<?= $adm['id'] ?>" <?= $filterUser === (int)$adm['id'] ? 'selected' : '' ?>><?= htmlspecialchars($adm['full_name']) ?> (<?= ucfirst($adm['role']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <select name="action_filter" class="al-input">
                <option value="">Semua Aksi</option>
                <option value="approve" <?= $filterAction === 'approve' ? 'selected' : '' ?>>Approve</option>
                <option value="reject" <?= $filterAction === 'reject' ? 'selected' : '' ?>>Reject</option>
                <option value="loan_request" <?= $filterAction === 'loan_request' ? 'selected' : '' ?>>Peminjaman Tools</option>
                <option value="return_request" <?= $filterAction === 'return_request' ? 'selected' : '' ?>>Pengembalian Tools</option>
                <option value="project_request" <?= $filterAction === 'project_request' ? 'selected' : '' ?>>Pengajuan Project</option>
                <option value="handover" <?= $filterAction === 'handover' ? 'selected' : '' ?>>Handover</option>
                <option value="force_return" <?= $filterAction === 'force_return' ? 'selected' : '' ?>>Force Return</option>
                <option value="apd" <?= $filterAction === 'apd' ? 'selected' : '' ?>>APD</option>
                <option value="tool" <?= $filterAction === 'tool' ? 'selected' : '' ?>>Tools (Semua)</option>
                <option value="leave" <?= $filterAction === 'leave' ? 'selected' : '' ?>>Leave/Izin</option>
                <option value="attendance" <?= $filterAction === 'attendance' ? 'selected' : '' ?>>Attendance</option>
                <option value="password" <?= $filterAction === 'password' ? 'selected' : '' ?>>Password</option>
                <option value="login" <?= $filterAction === 'login' ? 'selected' : '' ?>>Login</option>
            </select>
            <input type="date" name="date_filter" value="<?= htmlspecialchars($filterDate) ?>" class="al-input">
            <button type="submit" class="al-btn al-btn-primary"><i class="fas fa-search"></i> Filter</button>
            <?php if ($filterAction || $filterUser || $filterDate): ?>
            <a href="dashboard.php?page=audit-log" class="al-btn al-btn-secondary"><i class="fas fa-times"></i> Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Stats -->
    <div class="al-stats">
        <span class="al-stat-item"><?= $totalRows ?> total log</span>
        <span class="al-stat-item">Halaman <?= $page ?>/<?= $totalPages ?></span>
    </div>

    <!-- Table -->
    <?php if (empty($logs)): ?>
    <div class="al-empty">
        <i class="fas fa-clipboard-list"></i>
        <p>Belum ada audit log.</p>
    </div>
    <?php else: ?>
    <div class="al-table-wrap">
        <table class="al-table">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>User</th>
                    <th>Aksi</th>
                    <th>Target</th>
                    <th>Detail</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log):
                    $actionKey = $log['action'] ?? '';
                    $label = $actionLabels[$actionKey] ?? [ucwords(str_replace('_', ' ', $actionKey)), 'text-slate-700 bg-slate-100 dark:text-slate-300 dark:bg-slate-700'];
                    $details = $log['details'] ? json_decode($log['details'], true) : null;
                ?>
                <tr>
                    <td class="al-td-time">
                        <?php
                            $dt = new DateTime($log['created_at'], new DateTimeZone('UTC'));
                            $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));
                        ?>
                        <div><?= $dt->format('d/m/Y') ?></div>
                        <div class="al-time-sub"><?= $dt->format('H:i:s') ?> WIB</div>
                    </td>
                    <td class="al-td-actor"><?= htmlspecialchars($log['actor_name'] ?? 'System') ?></td>
                    <td><span class="al-badge <?= $label[1] ?>"><?= htmlspecialchars($label[0]) ?></span></td>
                    <td class="al-td-target"><?= htmlspecialchars($log['target_name'] ?? '-') ?></td>
                    <td class="al-td-detail">
                        <?php if ($details): ?>
                            <?php foreach ($details as $k => $v): ?>
                                <?php if (!is_array($v)): ?>
                                <span class="al-detail-tag"><?= htmlspecialchars($k) ?>: <?= htmlspecialchars((string)$v) ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="al-detail-empty">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="al-td-ip"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="al-pagination">
        <?php if ($page > 1): ?>
        <a href="dashboard.php?page=audit-log&p=<?= $page-1 ?>&action_filter=<?= urlencode($filterAction) ?>&user_filter=<?= $filterUser ?>&date_filter=<?= urlencode($filterDate) ?>" class="al-page-btn"><i class="fas fa-chevron-left"></i></a>
        <?php endif; ?>
        <?php
        $startP = max(1, $page - 2);
        $endP = min($totalPages, $page + 2);
        for ($i = $startP; $i <= $endP; $i++): ?>
        <a href="dashboard.php?page=audit-log&p=<?= $i ?>&action_filter=<?= urlencode($filterAction) ?>&user_filter=<?= $filterUser ?>&date_filter=<?= urlencode($filterDate) ?>" class="al-page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
        <a href="dashboard.php?page=audit-log&p=<?= $page+1 ?>&action_filter=<?= urlencode($filterAction) ?>&user_filter=<?= $filterUser ?>&date_filter=<?= urlencode($filterDate) ?>" class="al-page-btn"><i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.al-page { max-width: 100%; }
.al-header { margin-bottom: 1.25rem; }
.al-title { display: flex; align-items: center; gap: 0.5rem; }
.al-title i { color: #6366f1; font-size: 1.25rem; }
.al-title h1 { font-size: 1.4rem; font-weight: 700; color: #1e293b; margin: 0; }
.dark .al-title h1 { color: #f1f5f9; }
.al-subtitle { font-size: 0.8rem; color: #64748b; margin-top: 0.25rem; }
.dark .al-subtitle { color: #94a3b8; }

.al-filters { margin-bottom: 1rem; }
.al-filter-row { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
.al-input { padding: 0.5rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; font-size: 0.8rem; background: #fff; color: #1e293b; }
.dark .al-input { background: #1e293b; border-color: #475569; color: #f1f5f9; }

.al-btn { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.5rem 0.875rem; border-radius: 0.5rem; font-size: 0.8rem; font-weight: 600; border: none; cursor: pointer; text-decoration: none; transition: all 0.15s; }
.al-btn-primary { background: #4f46e5; color: #fff; }
.al-btn-primary:hover { background: #4338ca; }
.al-btn-secondary { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.dark .al-btn-secondary { background: #334155; color: #cbd5e1; border-color: #475569; }
.al-btn-secondary:hover { background: #e2e8f0; }

.al-stats { display: flex; gap: 1rem; margin-bottom: 0.75rem; font-size: 0.75rem; color: #64748b; }
.dark .al-stats { color: #94a3b8; }

.al-empty { text-align: center; padding: 3rem; color: #94a3b8; }
.al-empty i { font-size: 2rem; margin-bottom: 0.75rem; display: block; opacity: 0.5; }

.al-table-wrap { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 0.75rem; }
.dark .al-table-wrap { border-color: #334155; }
.al-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
.al-table thead { background: #f8fafc; }
.dark .al-table thead { background: #1e293b; }
.al-table th { padding: 0.625rem 0.75rem; text-align: left; font-weight: 600; color: #475569; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.05em; border-bottom: 1px solid #e2e8f0; }
.dark .al-table th { color: #94a3b8; border-color: #334155; }
.al-table td { padding: 0.625rem 0.75rem; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: top; }
.dark .al-table td { border-color: #1e293b; color: #e2e8f0; }
.al-table tbody tr:hover { background: #f8fafc; }
.dark .al-table tbody tr:hover { background: #0f172a; }

.al-td-time { white-space: nowrap; }
.al-time-sub { font-size: 0.7rem; color: #94a3b8; }
.al-td-actor { font-weight: 500; }
.al-td-target { font-weight: 500; }
.al-td-ip { font-size: 0.7rem; color: #94a3b8; font-family: monospace; }
.al-td-detail { max-width: 250px; }

.al-badge { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 0.375rem; font-size: 0.7rem; font-weight: 600; white-space: nowrap; }
.al-detail-tag { display: inline-block; background: #f1f5f9; color: #475569; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.7rem; margin: 0.125rem; }
.dark .al-detail-tag { background: #334155; color: #cbd5e1; }
.al-detail-empty { color: #cbd5e1; }

.al-pagination { display: flex; justify-content: center; gap: 0.25rem; margin-top: 1rem; }
.al-page-btn { display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 0.375rem; font-size: 0.8rem; text-decoration: none; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; transition: all 0.15s; }
.dark .al-page-btn { background: #1e293b; border-color: #334155; color: #cbd5e1; }
.al-page-btn:hover { background: #e2e8f0; }
.al-page-btn.active { background: #4f46e5; color: #fff; border-color: #4f46e5; }

@media (max-width: 640px) {
    .al-filter-row { flex-direction: column; }
    .al-input, .al-btn { width: 100%; }
    .al-table { font-size: 0.7rem; }
    .al-table th, .al-table td { padding: 0.5rem; }
}
</style>