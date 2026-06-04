<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!has_role(['administrator', 'direktur', 'technician_manager', 'technician', 'hse', 'internship'])) {
    http_response_code(403);
    echo '<p class="text-red-500 p-4">Forbidden</p>';
    exit;
}

$isAdmin = in_array($_SESSION['role'], ['administrator', 'direktur']);
$canManage = in_array($_SESSION['role'], ['administrator', 'direktur', 'technician_manager']);


$stmt = $pdo->query("SELECT id, full_name, username, role FROM users WHERE is_active = 1 AND role NOT IN ('administrator','direktur') ORDER BY full_name ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);


$mode = 'list';
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
if ($canManage && isset($_GET['action']) && $_GET['action'] === 'create') {
    $mode = 'create';
} elseif ($canManage && $editId > 0) {
    $mode = 'edit';
}


$selectedDate = $_GET['schedule_date'] ?? date('Y-m-d', strtotime('+1 day'));


$grouped = [];
if ($mode === 'list') {
    try {
        $sql = "SELECT s.id, s.destination, s.details, s.schedule_date, s.created_by,
                       au.full_name AS admin_name, u.full_name AS participant, u.id AS participant_id
                FROM schedules s
                LEFT JOIN users au ON au.id = s.created_by
                INNER JOIN schedule_assignees sa ON sa.schedule_id = s.id
                INNER JOIN users u ON u.id = sa.user_id
                WHERE s.schedule_date = ?
                ORDER BY s.id, u.full_name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$selectedDate]);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = $r['id'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'id' => $r['id'], 'destination' => $r['destination'], 'details' => $r['details'],
                    'schedule_date' => $r['schedule_date'], 'admin_name' => $r['admin_name'],
                    'participants' => [], 'participant_ids' => []
                ];
            }
            $grouped[$key]['participants'][] = $r['participant'];
            $grouped[$key]['participant_ids'][] = (int)$r['participant_id'];
        }
    } catch (PDOException $e) { $grouped = []; }
}


$editData = null;
$editAssignees = [];
if ($mode === 'edit' && $editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM schedules WHERE id = ?");
    $stmt->execute([$editId]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editData) { $mode = 'list'; }
    else {
        $rows = $pdo->prepare("SELECT user_id FROM schedule_assignees WHERE schedule_id = ?");
        $rows->execute([$editId]);
        $editAssignees = array_map('intval', $rows->fetchAll(PDO::FETCH_COLUMN));
    }
}


$blockedUserIds = [];
if ($mode === 'create') {
    try {
        $createDate = date('Y-m-d', strtotime('+1 day'));
        $q = $pdo->prepare("SELECT DISTINCT sa.user_id FROM schedule_assignees sa INNER JOIN schedules s ON s.id = sa.schedule_id WHERE s.schedule_date = ?");
        $q->execute([$createDate]);
        $blockedUserIds = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {}
}

$bulan = ['January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April','May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'];
function formatTgl($date) {
    global $bulan;
    return date('d', strtotime($date)) . ' ' . ($bulan[date('F', strtotime($date))] ?? date('F', strtotime($date))) . ' ' . date('Y', strtotime($date));
}
?>

<style>
@keyframes circle-draw { from { stroke-dashoffset:166; } to { stroke-dashoffset:0; } }
@keyframes check-draw { from { stroke-dashoffset:48; } to { stroke-dashoffset:0; } }
@keyframes x-draw { from { stroke-dashoffset:20; } to { stroke-dashoffset:0; } }
@keyframes circle-fill { from { opacity:0; transform:scale(0); } to { opacity:0.15; transform:scale(1); } }
@keyframes toast-bounce { 0%{transform:scale(0.3);opacity:0} 50%{transform:scale(1.05)} 70%{transform:scale(0.95)} 100%{transform:scale(1);opacity:1} }
@keyframes toast-out { to{transform:scale(0.8);opacity:0} }
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
        var icons = { warning: '⚠️', info: 'ℹ️' };
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
</script>

<div class="max-w-5xl mx-auto">

<?php if ($mode === 'list'): ?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl sm:text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fas fa-calendar-check text-white text-lg"></i>
            </div>
            Jadwal Kerja
        </h1>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-1 ml-[52px]">Kelola jadwal kerja harian tim</p>
    </div>
    <?php if ($canManage): ?>
    <a href="dashboard.php?page=schedules&action=create" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition text-sm shadow-sm no-underline">
        <i class="fas fa-plus"></i>
        <span>Buat Jadwal</span>
    </a>
    <?php endif; ?>
</div>


<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 mb-5">
    <form method="GET" class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
        <input type="hidden" name="page" value="schedules">
        <label class="text-sm font-medium text-gray-700 dark:text-slate-300 flex items-center gap-2 flex-shrink-0">
            <i class="fas fa-calendar-day text-blue-500"></i>Lihat Tanggal:
        </label>
        <input type="date" name="schedule_date" value="<?= htmlspecialchars($selectedDate) ?>"
               class="border border-gray-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent w-full sm:w-auto"
               onchange="this.form.submit()">
        <span class="text-sm text-gray-500 dark:text-slate-400"><?= formatTgl($selectedDate) ?></span>
    </form>
</div>


<?php if (empty($grouped)): ?>
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-10 text-center">
    <div class="w-16 h-16 bg-gray-100 dark:bg-slate-700 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-calendar-times text-gray-400 dark:text-slate-500 text-2xl"></i>
    </div>
    <p class="text-gray-500 dark:text-slate-400 font-medium">Tidak ada jadwal pada tanggal ini</p>
    <p class="text-sm text-gray-400 dark:text-slate-500 mt-1">Klik "Buat Jadwal" untuk menambahkan</p>
</div>
<?php else: ?>
<div class="space-y-4">
    <?php foreach ($grouped as $sc): ?>
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="px-4 sm:px-5 py-4">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-3">
                <div class="min-w-0 flex-1">
                    <h3 class="font-bold text-gray-800 dark:text-white text-base sm:text-lg flex items-center gap-2">
                        <i class="fas fa-map-marker-alt text-blue-500 flex-shrink-0"></i>
                        <span class="truncate"><?= htmlspecialchars($sc['destination']) ?></span>
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-slate-400 mt-1 ml-6">
                        <i class="fas fa-user-edit mr-1"></i>Oleh: <?= htmlspecialchars($sc['admin_name'] ?? 'Admin') ?>
                        <span class="mx-2">·</span>
                        <i class="fas fa-calendar mr-1"></i><?= formatTgl($sc['schedule_date']) ?>
                    </p>
                </div>
                <div class="flex gap-2 flex-shrink-0 ml-6 sm:ml-0">
                    <?php if ($canManage): ?>
                    <a href="dashboard.php?page=schedules&edit=<?= (int)$sc['id'] ?>"
                       class="px-3 py-1.5 text-xs font-semibold bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 rounded-lg hover:bg-amber-100 dark:hover:bg-amber-900/40 transition no-underline">
                        <i class="fas fa-pen mr-1"></i>Edit
                    </a>
                    <?php if ($isAdmin): ?>
                    <button onclick="hapusJadwal(<?= (int)$sc['id'] ?>)" class="px-3 py-1.5 text-xs font-semibold bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/40 transition border-0 cursor-pointer">
                        <i class="fas fa-trash-alt mr-1"></i>Hapus
                    </button>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="ml-6">
                <p class="text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide mb-2">
                    <i class="fas fa-users mr-1"></i>Tim (<?= count($sc['participants']) ?>)
                </p>
                <div class="flex flex-wrap gap-1.5">
                    <?php foreach ($sc['participants'] as $p): ?>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 border border-blue-100 dark:border-blue-800/30">
                        <i class="fas fa-user text-blue-400 dark:text-blue-500 mr-1 text-[10px]"></i><?= htmlspecialchars($p) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if (!empty($sc['details'])): ?>
            <div class="ml-6 mt-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg p-3">
                <p class="text-xs text-gray-600 dark:text-slate-400 leading-relaxed"><?= nl2br(htmlspecialchars($sc['details'])) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
function hapusJadwal(id) {
    if (!confirm('Yakin hapus jadwal ini?')) return;
    var fd = new URLSearchParams();
    fd.set('id', id);
    fd.set('csrf', <?= json_encode(csrf_token()) ?>);
    fetch('./app/action/delete_schedule.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:fd.toString()})
        .then(function(r){return r.json();})
        .then(function(j){
            if (j.success) { showToast('Jadwal berhasil dihapus', 'success'); setTimeout(function(){ location.reload(); }, 1500); }
            else showToast(j.message||'Gagal menghapus', 'error');
        })
        .catch(function(){ showToast('Terjadi kesalahan', 'error'); });
}
</script>

<?php elseif ($mode === 'create'): ?>

<div class="mb-6">
    <a href="dashboard.php?page=schedules" class="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 transition no-underline mb-4">
        <i class="fas fa-arrow-left"></i>Kembali ke Daftar Jadwal
    </a>
    <h1 class="text-xl sm:text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-calendar-plus text-white text-lg"></i>
        </div>
        Buat Jadwal Baru
    </h1>
</div>

<form id="createForm" class="space-y-5">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />

    
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 sm:p-5">
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">
            <i class="fas fa-calendar mr-1 text-blue-500"></i>Tanggal Jadwal
        </label>
        <input type="date" name="schedule_date" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required
               class="w-full sm:w-auto border border-gray-300 dark:border-slate-600 rounded-lg px-3 py-2.5 bg-white dark:bg-slate-700 text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
    </div>

    
    <div id="createDestWrap" class="space-y-4">
        <div class="create-dst bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 sm:p-5" data-idx="0">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold text-gray-700 dark:text-slate-300 flex items-center gap-2">
                    <span class="w-7 h-7 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg flex items-center justify-center text-xs font-bold dst-num">1</span>
                    <span class="dst-label">Lokasi #1</span>
                </h3>
                <button type="button" class="text-red-500 hover:text-red-600 text-xs font-medium hidden remove-dst-btn" onclick="removeDestBlock(this)">
                    <i class="fas fa-trash-alt mr-1"></i>Hapus
                </button>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1.5"><i class="fas fa-map-marker-alt mr-1"></i>Nama Lokasi</label>
                    <input type="text" name="destinations[]" placeholder="Contoh: Site Project A - Gedung B"
                           class="w-full border border-gray-300 dark:border-slate-600 rounded-lg px-3 py-2.5 bg-white dark:bg-slate-700 text-gray-800 dark:text-white text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent" required />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-2"><i class="fas fa-users mr-1"></i>Pilih Anggota Tim</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-1 max-h-52 overflow-y-auto p-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700/50">
                        <?php foreach ($users as $u): 
                            $blocked = in_array((int)$u['id'], $blockedUserIds);
                        ?>
                        <label class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-white dark:hover:bg-slate-700 cursor-pointer transition user-label <?= $blocked ? 'opacity-40 pointer-events-none' : '' ?>" data-uid="<?= (int)$u['id'] ?>">
                            <input type="checkbox" name="assignees[0][]" value="<?= (int)$u['id'] ?>" <?= $blocked ? 'disabled' : '' ?>
                                   class="w-4 h-4 text-blue-600 rounded border-gray-300 dark:border-slate-600 focus:ring-blue-500" />
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-800 dark:text-slate-200 truncate"><?= htmlspecialchars($u['full_name']) ?></div>
                                <div class="text-[10px] text-gray-400 dark:text-slate-500"><?= htmlspecialchars($u['role']) ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1.5"><i class="fas fa-sticky-note mr-1"></i>Catatan <span class="text-gray-400">(opsional)</span></label>
                    <textarea name="details[]" rows="2" placeholder="Tulis catatan khusus..."
                              class="w-full border border-gray-300 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-gray-800 dark:text-white text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                </div>
            </div>
        </div>
    </div>

    <button type="button" onclick="addDestBlock()"
            class="w-full py-3 border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-xl text-sm font-medium text-gray-500 dark:text-slate-400 hover:border-blue-400 hover:text-blue-500 transition flex items-center justify-center gap-2 bg-white dark:bg-slate-800">
        <i class="fas fa-plus-circle"></i>Tambah Lokasi Lain
    </button>

    
    <div class="flex flex-col sm:flex-row gap-3">
        <button type="submit" id="createSubmitBtn"
                class="flex-1 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition text-sm flex items-center justify-center gap-2">
            <i class="fas fa-save"></i>Simpan Jadwal
        </button>
        <a href="dashboard.php?page=schedules"
           class="px-6 py-3 bg-gray-200 hover:bg-gray-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-slate-300 font-semibold rounded-xl transition text-sm text-center no-underline">
            Batal
        </a>
    </div>
</form>

<script>
(function(){
    var CSRF = <?= json_encode(csrf_token()) ?>;
    var USERS = <?= json_encode($users, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    var BLOCKED = new Set(<?= json_encode($blockedUserIds) ?>.map(String));
    var createIdx = 1;

    function escHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    window.addDestBlock = function() {
        var wrap = document.getElementById('createDestWrap');
        var div = document.createElement('div');
        div.className = 'create-dst bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 sm:p-5';
        div.setAttribute('data-idx', createIdx);
        var uh = '';
        USERS.forEach(function(u) {
            var bl = BLOCKED.has(String(u.id));
            uh += '<label class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-white dark:hover:bg-slate-700 cursor-pointer transition user-label'+(bl?' opacity-40 pointer-events-none':'')+'" data-uid="'+u.id+'">' +
                '<input type="checkbox" name="assignees['+createIdx+'][]" value="'+u.id+'"'+(bl?' disabled':'')+' class="w-4 h-4 text-blue-600 rounded border-gray-300 dark:border-slate-600 focus:ring-blue-500" />' +
                '<div class="min-w-0"><div class="text-sm font-medium text-gray-800 dark:text-slate-200 truncate">'+escHtml(u.full_name)+'</div>' +
                '<div class="text-[10px] text-gray-400 dark:text-slate-500">'+escHtml(u.role)+'</div></div></label>';
        });
        div.innerHTML = '<div class="flex items-center justify-between mb-4">'+
            '<h3 class="text-sm font-bold text-gray-700 dark:text-slate-300 flex items-center gap-2">'+
            '<span class="w-7 h-7 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg flex items-center justify-center text-xs font-bold dst-num"></span>'+
            '<span class="dst-label"></span></h3>'+
            '<button type="button" class="text-red-500 hover:text-red-600 text-xs font-medium remove-dst-btn" onclick="removeDestBlock(this)">'+
            '<i class="fas fa-trash-alt mr-1"></i>Hapus</button></div>'+
            '<div class="space-y-4">'+
            '<div><label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1.5"><i class="fas fa-map-marker-alt mr-1"></i>Nama Lokasi</label>'+
            '<input type="text" name="destinations[]" placeholder="Contoh: Site Project B" class="w-full border border-gray-300 dark:border-slate-600 rounded-lg px-3 py-2.5 bg-white dark:bg-slate-700 text-gray-800 dark:text-white text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent" required /></div>'+
            '<div><label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-2"><i class="fas fa-users mr-1"></i>Pilih Anggota Tim</label>'+
            '<div class="grid grid-cols-1 sm:grid-cols-2 gap-1 max-h-52 overflow-y-auto p-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700/50">'+uh+'</div></div>'+
            '<div><label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1.5"><i class="fas fa-sticky-note mr-1"></i>Catatan <span class="text-gray-400">(opsional)</span></label>'+
            '<textarea name="details[]" rows="2" placeholder="Tulis catatan khusus..." class="w-full border border-gray-300 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-gray-800 dark:text-white text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea></div></div>';
        wrap.appendChild(div);
        createIdx++;
        renumber();
        syncCb();
    };

    window.removeDestBlock = function(btn) {
        var blk = btn.closest('.create-dst');
        var wrap = document.getElementById('createDestWrap');
        if (blk && wrap.children.length > 1) { blk.remove(); renumber(); syncCb(); }
    };

    function renumber() {
        var all = document.querySelectorAll('#createDestWrap .create-dst');
        all.forEach(function(d, i) {
            var n = d.querySelector('.dst-num'); if (n) n.textContent = i+1;
            var l = d.querySelector('.dst-label'); if (l) l.textContent = 'Lokasi #'+(i+1);
            var r = d.querySelector('.remove-dst-btn'); if (r) r.classList.toggle('hidden', all.length<=1);
        });
    }

    function syncCb() {
        var wrap = document.getElementById('createDestWrap');
        var sel = new Set();
        wrap.querySelectorAll('input[type="checkbox"]:checked').forEach(function(cb){sel.add(cb.value);});
        wrap.querySelectorAll('input[type="checkbox"]').forEach(function(cb){
            if (BLOCKED.has(cb.value)) return;
            var lbl = cb.closest('.user-label');
            if (cb.checked) {
                cb.disabled = false;
                if (lbl) {lbl.classList.remove('opacity-40','pointer-events-none');}
            } else if (sel.has(cb.value)) {
                cb.disabled = true;
                if (lbl) {lbl.classList.add('opacity-40','pointer-events-none');}
            } else {
                cb.disabled = false;
                if (lbl) {lbl.classList.remove('opacity-40','pointer-events-none');}
            }
        });
    }

    document.getElementById('createDestWrap').addEventListener('change', function(e){
        if (e.target.matches('input[type="checkbox"]')) syncCb();
    });

    document.getElementById('createForm').addEventListener('submit', function(e){
        e.preventDefault();
        var form = this;
        var btn = document.getElementById('createSubmitBtn');
        var dateVal = form.querySelector('input[name="schedule_date"]').value.trim();
        if (!dateVal) { showToast('Tanggal harus diisi', 'warning'); return; }
        var blocks = form.querySelectorAll('.create-dst');
        for (var i=0; i<blocks.length; i++) {
            var di = blocks[i].querySelector('input[name="destinations[]"]');
            if (!di.value.trim()) { di.focus(); showToast('Lokasi #'+(i+1)+' harus diisi', 'warning'); return; }
            if (blocks[i].querySelectorAll('input[type="checkbox"]:checked').length===0) {
                showToast('Pilih minimal 1 anggota untuk Lokasi #'+(i+1), 'warning'); return;
            }
        }
        var fd = new FormData();
        fd.append('csrf', CSRF);
        fd.append('schedule_date', dateVal);
        blocks.forEach(function(blk, idx){
            fd.append('destinations[]', blk.querySelector('input[name="destinations[]"]').value);
            fd.append('details[]', blk.querySelector('textarea[name^="details"]').value);
            blk.querySelectorAll('input[type="checkbox"]:checked').forEach(function(cb){
                fd.append('assignees['+idx+'][]', cb.value);
            });
        });
        btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
        fetch('./app/action/save_schedule.php', {method:'POST', body:fd})
            .then(function(r){return r.json();})
            .then(function(j){
                if (j.success) { showToast('Jadwal berhasil disimpan', 'success'); setTimeout(function(){ window.location.href='dashboard.php?page=schedules&schedule_date='+dateVal; }, 1500); }
                else showToast(j.message||'Gagal menyimpan', 'error');
            })
            .catch(function(){ showToast('Terjadi kesalahan', 'error'); })
            .finally(function(){btn.disabled=false; btn.innerHTML='<i class="fas fa-save"></i>Simpan Jadwal';});
    });

    renumber();
})();
</script>

<?php elseif ($mode === 'edit' && $editData): ?>

<div class="mb-6">
    <a href="dashboard.php?page=schedules&schedule_date=<?= htmlspecialchars($editData['schedule_date']) ?>" class="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 transition no-underline mb-4">
        <i class="fas fa-arrow-left"></i>Kembali ke Daftar Jadwal
    </a>
    <h1 class="text-xl sm:text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
        <div class="w-10 h-10 bg-gradient-to-br from-amber-500 to-amber-700 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-calendar-alt text-white text-lg"></i>
        </div>
        Edit Jadwal
    </h1>
</div>

<form id="editForm" class="space-y-5">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
    <input type="hidden" name="id" value="<?= (int)$editData['id'] ?>" />

    
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 sm:p-5">
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">
            <i class="fas fa-calendar mr-1 text-amber-500"></i>Tanggal
        </label>
        <input type="date" name="schedule_date" value="<?= htmlspecialchars($editData['schedule_date']) ?>" required
               class="w-full sm:w-auto border border-gray-300 dark:border-slate-600 rounded-lg px-3 py-2.5 bg-white dark:bg-slate-700 text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent" />
    </div>

    
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 sm:p-5">
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">
            <i class="fas fa-map-marker-alt mr-1 text-amber-500"></i>Lokasi/Tempat
        </label>
        <input type="text" name="destination" value="<?= htmlspecialchars($editData['destination']) ?>" required
               class="w-full border border-gray-300 dark:border-slate-600 rounded-lg px-3 py-2.5 bg-white dark:bg-slate-700 text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent" />
    </div>

    
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 sm:p-5">
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-3">
            <i class="fas fa-users mr-1 text-amber-500"></i>Anggota Tim
        </label>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-1 max-h-64 overflow-y-auto p-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700/50">
            <?php foreach ($users as $u): ?>
            <label class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-white dark:hover:bg-slate-700 cursor-pointer transition">
                <input type="checkbox" name="assignees[]" value="<?= (int)$u['id'] ?>"
                       <?= in_array((int)$u['id'], $editAssignees) ? 'checked' : '' ?>
                       class="w-4 h-4 text-amber-600 rounded border-gray-300 dark:border-slate-600 focus:ring-amber-500" />
                <div class="min-w-0">
                    <div class="text-sm font-medium text-gray-800 dark:text-slate-200 truncate"><?= htmlspecialchars($u['full_name']) ?></div>
                    <div class="text-[10px] text-gray-400 dark:text-slate-500"><?= htmlspecialchars($u['role']) ?></div>
                </div>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 sm:p-5">
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">
            <i class="fas fa-sticky-note mr-1 text-amber-500"></i>Catatan
        </label>
        <textarea name="details" rows="3"
                  class="w-full border border-gray-300 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent"><?= htmlspecialchars($editData['details'] ?? '') ?></textarea>
    </div>

    
    <div class="flex flex-col sm:flex-row gap-3">
        <button type="submit" id="editSubmitBtn"
                class="flex-1 px-6 py-3 bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded-xl transition text-sm flex items-center justify-center gap-2">
            <i class="fas fa-save"></i>Update Jadwal
        </button>
        <a href="dashboard.php?page=schedules&schedule_date=<?= htmlspecialchars($editData['schedule_date']) ?>"
           class="px-6 py-3 bg-gray-200 hover:bg-gray-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-slate-300 font-semibold rounded-xl transition text-sm text-center no-underline">
            Batal
        </a>
    </div>
</form>

<script>
(function(){
    var CSRF = <?= json_encode(csrf_token()) ?>;

    document.getElementById('editForm').addEventListener('submit', function(e){
        e.preventDefault();
        var form = this;
        var btn = document.getElementById('editSubmitBtn');
        var dateVal = form.querySelector('input[name="schedule_date"]').value.trim();
        var dest = form.querySelector('input[name="destination"]').value.trim();
        if (!dateVal || !dest) { showToast('Tanggal dan lokasi harus diisi', 'warning'); return; }
        var checked = form.querySelectorAll('input[name="assignees[]"]:checked');
        if (checked.length===0) { showToast('Pilih minimal 1 anggota', 'warning'); return; }
        var fd = new FormData();
        fd.append('csrf', CSRF);
        fd.append('id', form.querySelector('input[name="id"]').value);
        fd.append('schedule_date', dateVal);
        fd.append('destination', dest);
        fd.append('details', form.querySelector('textarea[name="details"]').value);
        checked.forEach(function(cb){fd.append('assignees[]', cb.value);});
        btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Updating...';
        fetch('./app/action/update_schedule.php', {method:'POST', body:fd})
            .then(function(r){return r.json();})
            .then(function(j){
                if (j.success) { showToast('Jadwal berhasil diupdate', 'success'); setTimeout(function(){ window.location.href='dashboard.php?page=schedules&schedule_date='+dateVal; }, 1500); }
                else showToast(j.message||'Gagal update', 'error');
            })
            .catch(function(){ showToast('Terjadi kesalahan', 'error'); })
            .finally(function(){btn.disabled=false; btn.innerHTML='<i class="fas fa-save"></i>Update Jadwal';});
    });
})();
</script>

<?php endif; ?>

</div>
