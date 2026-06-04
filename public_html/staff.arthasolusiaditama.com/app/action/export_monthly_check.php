<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    http_response_code(403);
    exit('Akses ditolak.');
}

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}


$techStmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.username
    FROM users u
    WHERE u.role IN ('technician', 'technician_manager')
      AND u.is_active = 1
      AND EXISTS (
          SELECT 1 FROM tool_assignments ta
          JOIN tools t ON t.id = ta.tool_id AND t.tool_type = 'personal'
          WHERE ta.user_id = u.id
      )
    ORDER BY u.full_name
");
$techStmt->execute();
$technicians = $techStmt->fetchAll(PDO::FETCH_ASSOC);


$checkStmt = $pdo->prepare("
    SELECT mc.id, mc.user_id, mc.checked_at, mc.checked_by,
           admin.full_name as checked_by_name
    FROM monthly_checks mc
    LEFT JOIN users admin ON mc.checked_by = admin.id
    WHERE mc.check_month = ?
");
$checkStmt->execute([$month]);
$checks = [];
foreach ($checkStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $checks[$row['user_id']] = $row;
}


$itemStmt = $pdo->prepare("
    SELECT mci.check_id, mci.tool_id, mci.status, mci.notes,
           t.name as tool_name, t.code as tool_code
    FROM monthly_check_items mci
    JOIN tools t ON mci.tool_id = t.id
    JOIN monthly_checks mc ON mci.check_id = mc.id
    WHERE mc.check_month = ?
    ORDER BY t.name
");
$itemStmt->execute([$month]);
$allItems = [];
foreach ($itemStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
    $allItems[$item['check_id']][] = $item;
}


$toolStmt = $pdo->query("SELECT id, name, code FROM tools WHERE tool_type='personal' ORDER BY name");
$allTools = $toolStmt->fetchAll(PDO::FETCH_ASSOC);

$monthLabel = date('F Y', strtotime($month . '-01'));
$filename = 'Monthly_Check_Tools_' . str_replace('-', '_', $month) . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
echo '<head><meta charset="utf-8"><style>';
echo 'table { border-collapse: collapse; } ';
echo 'th, td { border: 1px solid #333; padding: 6px 10px; font-size: 12px; font-family: Arial; } ';
echo 'th { background: #2563eb; color: white; font-weight: bold; text-align: center; } ';
echo '.good { background: #dcfce7; color: #166534; } ';
echo '.repair { background: #fef3c7; color: #92400e; } ';
echo '.missing { background: #fee2e2; color: #b91c1c; } ';
echo '.unchecked { background: #f1f5f9; color: #64748b; font-style: italic; } ';
echo '.header-row { background: #1e40af; color: white; } ';
echo '.section-header { background: #e2e8f0; font-weight: bold; } ';
echo '.finalized { background: #dcfce7; } ';
echo '.not-finalized { background: #fee2e2; } ';
echo '</style></head><body>';


echo '<table>';
echo '<tr><th colspan="5" style="font-size:16px; background:#1e293b;">Pengecekan Tools Bulanan - ' . htmlspecialchars($monthLabel) . '</th></tr>';
echo '<tr><th>No</th><th>Nama Karyawan</th><th>Username</th><th>Status</th><th>Tanggal Finalisasi</th></tr>';

$no = 1;
foreach ($technicians as $tech) {
    $check = $checks[$tech['id']] ?? null;
    $isFinalized = $check && !empty($check['checked_at']);
    $statusClass = $isFinalized ? 'finalized' : 'not-finalized';
    $statusText = $isFinalized ? 'Selesai' : ($check ? 'Belum Selesai' : 'Belum Dimulai');
    $dateText = $isFinalized ? date('d M Y H:i', strtotime($check['checked_at'])) : '-';

    echo '<tr>';
    echo '<td style="text-align:center;">' . $no++ . '</td>';
    echo '<td>' . htmlspecialchars($tech['full_name']) . '</td>';
    echo '<td>' . htmlspecialchars($tech['username']) . '</td>';
    echo '<td class="' . $statusClass . '" style="text-align:center;">' . $statusText . '</td>';
    echo '<td style="text-align:center;">' . $dateText . '</td>';
    echo '</tr>';
}
echo '</table>';

echo '<br><br>';


foreach ($technicians as $tech) {
    $check = $checks[$tech['id']] ?? null;
    $items = $check ? ($allItems[$check['id']] ?? []) : [];
    $itemMap = [];
    foreach ($items as $item) {
        $itemMap[$item['tool_id']] = $item;
    }

    
    $assignStmt = $pdo->prepare("
        SELECT t.id, t.name, t.code
        FROM tools t
        JOIN tool_assignments ta ON ta.tool_id = t.id
        WHERE ta.user_id = ? AND t.tool_type = 'personal'
        ORDER BY t.name
    ");
    $assignStmt->execute([$tech['id']]);
    $techTools = $assignStmt->fetchAll(PDO::FETCH_ASSOC);

    $isFinalized = $check && !empty($check['checked_at']);

    echo '<table>';
    echo '<tr><th colspan="5" style="font-size:14px; background:#1e293b;">' . htmlspecialchars($tech['full_name']) . ' (' . htmlspecialchars($tech['username']) . ')';
    if ($isFinalized) {
        echo ' - Finalisasi: ' . date('d M Y H:i', strtotime($check['checked_at']));
    }
    echo '</th></tr>';
    echo '<tr><th>No</th><th>Nama Tool</th><th>Kode</th><th>Status</th><th>Catatan</th></tr>';

    $toolNo = 1;
    $goodCount = 0;
    $repairCount = 0;
    $missingCount = 0;
    $uncheckedCount = 0;

    foreach ($techTools as $tool) {
        $item = $itemMap[$tool['id']] ?? null;
        $status = $item ? $item['status'] : '';
        $notes = $item ? ($item['notes'] ?? '') : '';
        $statusClass = $status ? strtolower($status) : 'unchecked';
        $statusLabel = $status ?: 'Belum dicek';

        if ($status === 'Good') $goodCount++;
        elseif ($status === 'Repair') $repairCount++;
        elseif ($status === 'Missing') $missingCount++;
        else $uncheckedCount++;

        echo '<tr>';
        echo '<td style="text-align:center;">' . $toolNo++ . '</td>';
        echo '<td>' . htmlspecialchars($tool['name']) . '</td>';
        echo '<td>' . htmlspecialchars($tool['code']) . '</td>';
        echo '<td class="' . $statusClass . '" style="text-align:center;">' . htmlspecialchars($statusLabel) . '</td>';
        echo '<td>' . htmlspecialchars($notes) . '</td>';
        echo '</tr>';
    }

    
    echo '<tr class="section-header">';
    echo '<td colspan="2">Total</td>';
    echo '<td style="text-align:center;">Good: ' . $goodCount . '</td>';
    echo '<td style="text-align:center;">Repair: ' . $repairCount . ' | Missing: ' . $missingCount . '</td>';
    echo '<td>Belum: ' . $uncheckedCount . '</td>';
    echo '</tr>';
    echo '</table><br>';
}

echo '</body></html>';
