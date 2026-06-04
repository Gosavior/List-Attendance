<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/audit-log.php';

@date_default_timezone_set('Asia/Jakarta');


$allowedRoles = ['administrator', 'direktur', 'technician_manager'];
if (!in_array(($user['role'] ?? ''), $allowedRoles)) {
    echo '<div class="p-6 text-red-600">Akses ditolak.</div>';
    return;
}

$messages = [];
$errors = [];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    
    if (!isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
        $errors[] = 'Token tidak valid. Silakan muat ulang halaman.';
    }
    
    if (!$errors) {
        if ($action === 'reset_single') {
            $attendanceId = intval($_POST['attendance_id'] ?? 0);
            if ($attendanceId > 0) {
                
                $stmt = $pdo->prepare('SELECT a.*, u.full_name FROM attendances a JOIN users u ON a.user_id = u.id WHERE a.id = ?');
                $stmt->execute([$attendanceId]);
                $att = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($att) {
                    try {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare('DELETE FROM attendance_activities WHERE attendance_id = ?');
                        $stmt->execute([$attendanceId]);
                        $stmt = $pdo->prepare('DELETE FROM attendances WHERE id = ?');
                        $stmt->execute([$attendanceId]);
                        $pdo->commit();
                        $messages[] = "Absensi {$att['full_name']} tanggal {$att['attendance_date']} berhasil direset.";
                        auditLog($pdo, 'reset_attendance', [
                            'target_type' => 'attendance',
                            'target_id' => $attendanceId,
                            'target_user_id' => (int)$att['user_id'],
                            'details' => ['date' => $att['attendance_date'], 'user' => $att['full_name'], 'old_status' => $att['status'] ?? '']
                        ]);
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        $errors[] = 'Gagal reset absensi: ' . $e->getMessage();
                    }
                } else {
                    $errors[] = 'Data absensi tidak ditemukan.';
                }
            }
        } elseif ($action === 'reset_bulk') {
            $resetDate = $_POST['reset_date'] ?? '';
            $resetRole = $_POST['reset_role'] ?? 'all';
            
            if (!$resetDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $resetDate)) {
                $errors[] = 'Tanggal tidak valid.';
            }
            
            if (!$errors) {
                if ($resetRole === 'all') {
                    $stmt = $pdo->prepare('DELETE FROM attendances WHERE attendance_date = ?');
                    $stmt->execute([$resetDate]);
                } else {
                    $stmt = $pdo->prepare('DELETE FROM attendances WHERE attendance_date = ? AND user_id IN (SELECT id FROM users WHERE role = ?)');
                    $stmt->execute([$resetDate, $resetRole]);
                }
                $count = $stmt->rowCount();
                $roleLabel = $resetRole === 'all' ? 'semua role' : ucwords(str_replace('_', ' ', $resetRole));
                $messages[] = "{$count} record absensi tanggal {$resetDate} ({$roleLabel}) berhasil direset.";
                auditLog($pdo, 'bulk_reset_attendance', [
                    'target_type' => 'attendance',
                    'details' => ['date' => $resetDate, 'role' => $resetRole, 'count' => $count]
                ]);
            }
        }
    }
}


$_SESSION['csrf_token'] = bin2hex(random_bytes(32));


$filterDate = $_GET['date'] ?? date('Y-m-d');
$filterRole = $_GET['role'] ?? 'all';


$query = "SELECT a.*, u.full_name, u.role FROM attendances a JOIN users u ON a.user_id = u.id WHERE a.attendance_date = ?";
$params = [$filterDate];

if ($filterRole !== 'all') {
    $query .= " AND u.role = ?";
    $params[] = $filterRole;
}
$query .= " ORDER BY u.full_name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$attendances = $stmt->fetchAll(PDO::FETCH_ASSOC);


$roleStmt = $pdo->query("SELECT DISTINCT role FROM users WHERE role != 'administrator' ORDER BY role");
$roles = $roleStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div style="padding:16px; display:block;">
  <div class="bg-white dark:bg-slate-800" style="border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.1); padding:24px; max-width:900px; margin:0 auto;">
    
    
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
      <div class="flex items-center gap-3">
        <a href="dashboard.php?page=absence&tab=attendance" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
          <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Reset Absensi</h1>
      </div>
    </div>

    
    <?php foreach ($messages as $msg): ?>
      <div class="mb-4 p-3 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-sm">
        <i class="fas fa-check-circle mr-1"></i> <?= htmlspecialchars($msg) ?>
      </div>
    <?php endforeach; ?>
    <?php foreach ($errors as $err): ?>
      <div class="mb-4 p-3 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 text-sm">
        <i class="fas fa-exclamation-circle mr-1"></i> <?= htmlspecialchars($err) ?>
      </div>
    <?php endforeach; ?>

    
    <div class="mb-6 p-4 border border-red-200 dark:border-red-800 rounded-xl bg-red-50 dark:bg-red-900/10">
      <h2 class="text-sm font-semibold text-red-700 dark:text-red-400 mb-3">
        <i class="fas fa-exclamation-triangle mr-1"></i> Reset Massal
      </h2>
      <form method="post" onsubmit="return confirmBulkReset(this);" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="action" value="reset_bulk">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div>
          <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Tanggal</label>
          <input type="date" name="reset_date" value="<?= htmlspecialchars($filterDate) ?>" required
            class="border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Role</label>
          <select name="reset_role" class="border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-3 py-2 text-sm">
            <option value="all">Semua Role</option>
            <?php foreach ($roles as $r): ?>
              <option value="<?= htmlspecialchars($r) ?>"><?= ucwords(str_replace('_', ' ', $r)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700">
          <i class="fas fa-trash mr-1"></i> Reset Semua
        </button>
      </form>
    </div>

    
    <form method="get" class="mb-4 flex flex-wrap items-end gap-3">
      <input type="hidden" name="page" value="attendance-reset">
      <div>
        <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Tanggal</label>
        <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>"
          class="border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Role</label>
        <select name="role" class="border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-3 py-2 text-sm">
          <option value="all" <?= $filterRole === 'all' ? 'selected' : '' ?>>Semua</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= htmlspecialchars($r) ?>" <?= $filterRole === $r ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $r)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700">
        <i class="fas fa-filter mr-1"></i> Filter
      </button>
    </form>

    
    <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">
      Menampilkan <strong><?= count($attendances) ?></strong> record untuk tanggal <strong><?= htmlspecialchars($filterDate) ?></strong>
    </div>

    <?php if (empty($attendances)): ?>
      <div class="text-center py-8 text-gray-400 dark:text-gray-500">
        <i class="fas fa-inbox text-3xl mb-2"></i>
        <p>Tidak ada data absensi untuk tanggal ini.</p>
      </div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-200 dark:border-slate-600 text-left text-gray-600 dark:text-gray-400">
              <th class="py-2 px-2">Nama</th>
              <th class="py-2 px-2">Role</th>
              <th class="py-2 px-2">Status</th>
              <th class="py-2 px-2">Check In</th>
              <th class="py-2 px-2">Check Out</th>
              <th class="py-2 px-2">GPS</th>
              <th class="py-2 px-2 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($attendances as $att): 
              $statusColors = [
                'Hadir' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                'Terlambat' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                'Alpha' => 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400',
                'Izin' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                'Sakit' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
                'Cuti' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
                'Lembur' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
              ];
              $statusClass = $statusColors[$att['status']] ?? 'bg-gray-100 text-gray-700';
              $cin = ($att['check_in_time'] && $att['check_in_time'] !== '0000-00-00 00:00:00') ? date('H:i', strtotime($att['check_in_time'])) : '-';
              $cout = ($att['check_out_time'] && $att['check_out_time'] !== '0000-00-00 00:00:00') ? date('H:i', strtotime($att['check_out_time'])) : '-';
              $hasGps = !empty($att['check_in_lat']) && $att['check_in_lat'] != 0;
            ?>
              <tr class="border-b border-gray-100 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700/50">
                <td class="py-2 px-2 font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($att['full_name']) ?></td>
                <td class="py-2 px-2 text-gray-500 dark:text-gray-400"><?= ucwords(str_replace('_', ' ', $att['role'])) ?></td>
                <td class="py-2 px-2">
                  <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold <?= $statusClass ?>"><?= htmlspecialchars($att['status'] ?? '-') ?></span>
                </td>
                <td class="py-2 px-2 text-gray-700 dark:text-gray-300"><?= $cin ?></td>
                <td class="py-2 px-2 text-gray-700 dark:text-gray-300"><?= $cout ?></td>
                <td class="py-2 px-2">
                  <?php if ($hasGps): ?>
                    <span class="text-green-600 dark:text-green-400" title="<?= $att['check_in_lat'] ?>, <?= $att['check_in_lng'] ?>">
                      <i class="fas fa-map-marker-alt"></i> <i class="fas fa-check"></i>
                    </span>
                  <?php else: ?>
                    <span class="text-gray-400">-</span>
                  <?php endif; ?>
                </td>
                <td class="py-2 px-2 text-center">
                  <form method="post" class="inline" onsubmit="return confirmSingleReset('<?= htmlspecialchars(addslashes($att['full_name'])) ?>');">
                    <input type="hidden" name="action" value="reset_single">
                    <input type="hidden" name="attendance_id" value="<?= $att['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <button type="submit" class="px-2 py-1 text-xs bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded hover:bg-red-200 dark:hover:bg-red-900/50 font-medium">
                      <i class="fas fa-undo mr-1"></i> Reset
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
function confirmSingleReset(name) {
  return confirm('Yakin reset absensi ' + name + '?\n\nUser ini harus absen ulang setelah direset.');
}

function confirmBulkReset(form) {
  const date = form.reset_date.value;
  const role = form.reset_role.options[form.reset_role.selectedIndex].text;
  return confirm('[PERINGATAN] Semua data absensi tanggal ' + date + ' untuk ' + role + ' akan dihapus.\n\nUser harus absen ulang. Lanjutkan?');
}
</script>
