<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
@date_default_timezone_set('Asia/Jakarta');

$stmt = $pdo->prepare("SELECT id, full_name, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$requestId = intval($_GET['request_id'] ?? 0);
if (!$requestId) { echo '<div class="p-6 text-red-600">Request ID tidak valid.</div>'; return; }

$stmt = $pdo->prepare("SELECT mr.*, u.full_name AS requester_name, u.phone AS requester_phone FROM material_requests mr JOIN users u ON u.id = mr.user_id WHERE mr.id = ? AND mr.status IN ('admin_approved','driver_pickup','delivered','completed')");
$stmt->execute([$requestId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$request) { echo '<div class="p-6 text-red-600">Material request belum siap dibuat DO atau tidak ditemukan.</div>'; return; }

$itemStmt = $pdo->prepare("SELECT material_name, quantity, unit, notes FROM material_request_items WHERE request_id = ? ORDER BY id ASC");
$itemStmt->execute([$requestId]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

$project = ['project_name' => 'Project #' . $request['project_id'], 'customer_id' => null, 'customer_name' => '', 'company_name' => '', 'customer_address' => '', 'address' => '', 'phone' => ''];
$addressSaved = false;
$addressError = '';
try {
    require __DIR__ . '/../config/database_sales.php';
    $salesPdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

    $p = $salesPdo->prepare("SELECT p.*, c.name AS master_customer_name, c.company AS master_company_name, c.pic_name AS master_pic_name, c.address AS master_address, c.phone AS master_phone, c.email AS master_email
        FROM projects p
        LEFT JOIN customers c ON c.id = p.customer_id
        WHERE p.id = ? LIMIT 1");
    $p->execute([$request['project_id']]);
    $project = array_merge($project, $p->fetch(PDO::FETCH_ASSOC) ?: []);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_customer_address') {
        $newAddress = trim((string)($_POST['customer_address'] ?? ''));
        $customerId = (int)($project['customer_id'] ?? 0);
        if ($newAddress === '') {
            $addressError = 'Alamat proyek wajib diisi.';
        } elseif ($customerId > 0) {
            $upd = $salesPdo->prepare("UPDATE customers SET address = ? WHERE id = ?");
            $upd->execute([$newAddress, $customerId]);
            $project['master_address'] = $newAddress;
            $project['customer_address'] = $newAddress;
            $addressSaved = true;
        } else {
            $addressError = 'Customer master belum terhubung ke project, alamat belum bisa disimpan otomatis.';
        }
    }
} catch (Exception $e) {
    error_log('Delivery Order customer/project load error: ' . $e->getMessage());
}

$doNumber = 'DO-' . date('Ymd', strtotime($request['admin_approved_at'] ?: $request['updated_at'] ?: 'now')) . '-' . str_pad((string)$requestId, 4, '0', STR_PAD_LEFT);
$doDate = date('d/m/Y', strtotime($request['admin_approved_at'] ?: 'now'));

$customerName = trim($project['master_customer_name'] ?? '') ?: trim($project['master_pic_name'] ?? '') ?: trim($project['customer_name'] ?? '') ?: trim($project['client_name'] ?? '') ?: trim($project['project_name'] ?? '') ?: '-';
$companyName = trim($project['master_company_name'] ?? '') ?: trim($project['company_name'] ?? '') ?: $customerName;
$projectName = $project['project_name'] ?? '-';
$projectAddress = $project['master_address'] ?? ($project['project_address'] ?? ($project['address'] ?? ($project['location'] ?? '')));
$hasAddress = !empty(trim((string)$projectAddress));
$phone = ''; 
$note = 'Material Request #' . $requestId . ' - ' . $projectName;
$payload = [
    'doNumber' => $doNumber,
    'date' => $doDate,
    'note' => $note,
    'companyName' => $companyName ?: '-',
    'customerName' => $customerName ?: '-',
    'projectAddress' => $projectAddress ?: '-',
    'hasAddress' => $hasAddress,
    'phone' => $phone,
    'items' => array_map(fn($i) => ['name' => $i['material_name'], 'quantity' => (int)$i['quantity'], 'unit' => $i['unit'] ?? 'PCS', 'notes' => $i['notes'] ?? ''], $items),
];
?>
<div class="max-w-6xl mx-auto px-4 py-5 sm:px-6">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 mb-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-gray-800 dark:text-gray-100"><i class="fas fa-truck"></i> Delivery Order</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Terhubung ke Material Request #<?= (int)$requestId ?> · <?= htmlspecialchars($projectName) ?></p>
            </div>
            <div class="flex gap-2">
                <a href="/dashboard.php?page=request-material" class="px-4 py-2.5 rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-bold">Kembali</a>
                <button id="downloadDoBtn" class="px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-sm font-bold"><i class="fas fa-download"></i> Download PDF</button>
            </div>
        </div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 mb-4">
        <?php if ($addressSaved): ?>
        <div class="mb-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 px-3 py-2 text-sm text-emerald-700 dark:text-emerald-200">Alamat customer berhasil disimpan ke customer master.</div>
        <?php elseif ($addressError): ?>
        <div class="mb-3 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 px-3 py-2 text-sm text-red-700 dark:text-red-200"><?= htmlspecialchars($addressError) ?></div>
        <?php endif; ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">PT/CV/TOKO</label>
                <input id="doCompanyName" type="text" value="<?= htmlspecialchars($companyName ?: '') ?>" placeholder="Masukkan nama PT/CV/TOKO" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-800 dark:text-gray-100">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Nama Customer</label>
                <input id="doCustomerName" type="text" value="<?= htmlspecialchars($customerName ?: '') ?>" placeholder="Masukkan nama customer" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-800 dark:text-gray-100">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">TELP / HP</label>
                <input id="doPhoneInput" type="text" placeholder="Isi manual nomor telepon/HP" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-800 dark:text-gray-100">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Alamat Proyek</label>
                <textarea id="doAddressInput" readonly rows="2" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm text-gray-800 dark:text-gray-100"><?= htmlspecialchars($projectAddress ?: '') ?></textarea>
            </div>
        </div>
        <?php if (!$hasAddress): ?>
        <form method="POST" class="mt-4 rounded-2xl border border-amber-200 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 p-3">
            <input type="hidden" name="action" value="save_customer_address">
            <label class="block text-xs font-bold text-amber-700 dark:text-amber-200 uppercase mb-1">Alamat customer belum ada. Tambah alamat ke customer master/SCP</label>
            <div class="flex flex-col sm:flex-row gap-2">
                <textarea name="customer_address" required rows="2" placeholder="Masukkan alamat proyek/customer" class="flex-1 px-3 py-2.5 rounded-xl border border-amber-200 dark:border-amber-700 bg-white dark:bg-gray-800 text-sm"></textarea>
                <button type="submit" class="px-4 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold">Simpan Alamat</button>
            </div>
        </form>
        <?php endif; ?>
        <div class="mt-4">
            <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-2">Satuan Item DO</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2" id="unitInputs"></div>
        </div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-3">
        <iframe id="doPreview" title="Preview Delivery Order" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white" style="height:75vh;"></iframe>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
const doData = <?= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, function(ch) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[ch];
    });
}
(function buildUnitInputs() {
    const container = document.getElementById('unitInputs');
    if (!container) return;
    doData.items.forEach((it, idx) => {
        const div = document.createElement('div');
        const itemName = escapeHtml(it.name);
        div.className = 'flex items-center gap-2 bg-gray-50 dark:bg-gray-700/50 rounded-xl px-3 py-2';
        div.innerHTML = '<span class="text-xs text-gray-600 dark:text-gray-300 truncate flex-1" title="' + itemName + '">' + itemName + ' (×' + it.quantity + ')</span>' +
            '<select data-unit-idx="' + idx + '" class="unit-select px-2 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs text-gray-800 dark:text-gray-100 focus:border-blue-500 focus:outline-none">' +
            '<option value="PCS"' + (it.unit === 'PCS' ? ' selected' : '') + '>PCS</option>' +
            '<option value="BOX"' + (it.unit === 'BOX' ? ' selected' : '') + '>BOX</option>' +
            '<option value="UNIT"' + (it.unit === 'UNIT' ? ' selected' : '') + '>UNIT</option>' +
            '<option value="SET"' + (it.unit === 'SET' ? ' selected' : '') + '>SET</option>' +
            '<option value="ROLL"' + (it.unit === 'ROLL' ? ' selected' : '') + '>ROLL</option>' +
            '<option value="MTR"' + (it.unit === 'MTR' ? ' selected' : '') + '>MTR</option>' +
            '<option value="KG"' + (it.unit === 'KG' ? ' selected' : '') + '>KG</option>' +
            '<option value="LTR"' + (it.unit === 'LTR' ? ' selected' : '') + '>LTR</option>' +
            '<option value="BTG"' + (it.unit === 'BTG' ? ' selected' : '') + '>BTG</option>' +
            '<option value="LBR"' + (it.unit === 'LBR' ? ' selected' : '') + '>LBR</option>' +
            '</select>';
        container.appendChild(div);
    });
    container.addEventListener('change', function(e) {
        if (e.target.matches('.unit-select')) {
            const idx = parseInt(e.target.dataset.unitIdx);
            doData.items[idx].unit = e.target.value;
            refreshPreview();
        }
    });
})();

const phoneInput = document.getElementById('doPhoneInput');
if (phoneInput) {
    phoneInput.addEventListener('input', function() {
        doData.phone = this.value.trim() || '-';
        refreshPreview();
    });
}

const companyInput = document.getElementById('doCompanyName');
if (companyInput) {
    companyInput.addEventListener('input', function() {
        doData.companyName = this.value.trim() || '-';
        refreshPreview();
    });
}

const customerInput = document.getElementById('doCustomerName');
if (customerInput) {
    customerInput.addEventListener('input', function() {
        doData.customerName = this.value.trim() || '-';
        refreshPreview();
    });
}

function drawDoPdf() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
    const pageW = 297, margin = 14;
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(0,0,0);
    doc.setLineWidth(0.35);

    doc.setFontSize(18); doc.setFont('helvetica', 'bold');
    doc.text('SURAT JALAN No. ' + doData.doNumber, margin, 18);
    doc.setFontSize(11); doc.setFont('helvetica', 'normal');
    doc.text('Date:', 226, 15); doc.text(doData.date || '-', 242, 15);
    doc.text('Note:', 226, 23); doc.text(doData.note || '-', 242, 23, { maxWidth: 42 });

    const infoX = margin, infoY = 34, infoW = pageW - margin * 2, rowH = 10, labelW = 38;
    doc.rect(infoX, infoY, infoW, rowH * 4);
    for (let i=1;i<4;i++) doc.line(infoX, infoY + rowH*i, infoX + infoW, infoY + rowH*i);
    doc.line(infoX + labelW, infoY, infoX + labelW, infoY + rowH*4);
    const phoneVal = doData.phone || document.getElementById('doPhoneInput').value || '-';
    const infoRows = [['PT/CV/TOKO', doData.companyName], ['NAMA', doData.customerName], ['ALAMAT PROYEK', doData.projectAddress], ['TELP/HP', phoneVal]];
    doc.setFontSize(10);
    infoRows.forEach((r, idx) => {
        const y = infoY + rowH*idx + 6.5;
        doc.setFont('helvetica','bold'); doc.text(r[0], infoX + 3, y);
        doc.setFont('helvetica','normal'); doc.text(String(r[1] || '-'), infoX + labelW + 3, y, { maxWidth: infoW - labelW - 6 });
    });

    const tableY = 84, noW = 18, qtyW = 35, tableW = infoW, descW = tableW - noW - qtyW;
    const visibleRows = Math.max(10, Math.min(16, doData.items.length));
    const tableH = 10 + (visibleRows * 5.6);
    doc.rect(infoX, tableY, tableW, tableH);
    doc.line(infoX + noW, tableY, infoX + noW, tableY + tableH);
    doc.line(infoX + noW + descW, tableY, infoX + noW + descW, tableY + tableH);
    doc.line(infoX, tableY + 10, infoX + tableW, tableY + 10);
    doc.setFont('helvetica','bold'); doc.setFontSize(10);
    doc.text('NO', infoX + noW/2, tableY + 6.5, { align: 'center' });
    doc.text('KETERANGAN', infoX + noW + descW/2, tableY + 6.5, { align: 'center' });
    doc.text('JUMLAH', infoX + noW + descW + qtyW/2, tableY + 6.5, { align: 'center' });
    doc.setFont('helvetica','normal');
    const itemH = 5.6;
    for (let i=1; i<=visibleRows; i++) doc.line(infoX, tableY + 10 + itemH*i, infoX + tableW, tableY + 10 + itemH*i);
    doData.items.forEach((it, idx) => {
        if (idx >= visibleRows) return;
        const y = tableY + 14 + itemH*idx;
        const desc = it.name + (it.notes ? ' - ' + it.notes : '');
        doc.text(String(idx + 1), infoX + noW/2, y, { align: 'center' });
        doc.text(desc, infoX + noW + 3, y, { maxWidth: descW - 6 });
        const qtyText = String(it.quantity || '') + ' ' + (it.unit || 'PCS');
        doc.text(qtyText, infoX + noW + descW + qtyW/2, y, { align: 'center' });
    });
    if (doData.items.length > visibleRows) {
        doc.setFontSize(8);
        doc.text('+' + (doData.items.length - visibleRows) + ' item lainnya mengikuti material request terkait.', infoX + noW + 3, tableY + tableH + 5);
        doc.setFontSize(10);
    }

    const footY = 184;
    doc.setFontSize(11);
    doc.text('Good Received By', 52, footY, { align: 'center' });
    doc.text('Hormat Kami', 238, footY, { align: 'center' });
    doc.setFontSize(10);
    doc.text('Customer Chop & Sign', 52, footY + 36, { align: 'center' });
    doc.text('Chop & Sign', 238, footY + 36, { align: 'center' });
    return doc;
}
function refreshPreview() {
    const doc = drawDoPdf();
    document.getElementById('doPreview').src = doc.output('bloburl');
}
document.getElementById('downloadDoBtn').addEventListener('click', () => drawDoPdf().save(doData.doNumber + '.pdf'));
if (window.jspdf) refreshPreview(); else window.addEventListener('load', refreshPreview);
</script>
