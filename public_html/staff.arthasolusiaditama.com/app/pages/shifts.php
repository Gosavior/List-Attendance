<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
@date_default_timezone_set('Asia/Jakarta');


$uid = (int)($_SESSION['user_id'] ?? 0);
$stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
$stmt->execute([$uid]);
$role = $stmt->fetchColumn();
if (!in_array($role, ['administrator','direktur'], true)) {
  http_response_code(403);
  echo '<div class="p-6 text-red-600">Forbidden</div>';
  exit;
}


try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS work_shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    default_start TIME NULL,
    default_end TIME NULL,
    cross_midnight TINYINT(1) DEFAULT 0,
    grace_minutes INT DEFAULT 10,
    early_leave_grace INT DEFAULT 0,
    overtime_grace INT DEFAULT 30,
    checkin_open_mins INT DEFAULT 60,
    checkin_close_mins INT DEFAULT 120,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS user_shift_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    shift_date DATE NOT NULL,
    shift_id INT NOT NULL,
    custom_start TIME NULL,
    custom_end TIME NULL,
    note VARCHAR(255) NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_date (user_id, shift_date),
    INDEX idx_date (shift_date),
    FOREIGN KEY (shift_id) REFERENCES work_shifts(id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("INSERT INTO work_shifts (code, name, default_start, default_end, cross_midnight)
              VALUES ('DAY','Shift Siang','08:30:00','17:30:00',0)
              ON DUPLICATE KEY UPDATE name=VALUES(name)");
  $pdo->exec("INSERT INTO work_shifts (code, name, cross_midnight)
              VALUES ('NIGHT','Shift Malam',1)
              ON DUPLICATE KEY UPDATE name=VALUES(name)");
  
  $pdo->exec("CREATE TABLE IF NOT EXISTS user_night_shift_flags (
    user_id INT PRIMARY KEY,
    is_active TINYINT(1) DEFAULT 0,
    custom_start TIME NULL,
    custom_end TIME NULL,
    note VARCHAR(255) NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {}

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = trim($_POST['action'] ?? 'save');
  if ($action === 'delete') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $date = trim($_POST['shift_date'] ?? '');
    if ($userId && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      try {
        $del = $pdo->prepare('DELETE FROM user_shift_assignments WHERE user_id = ? AND shift_date = ?');
        $del->execute([$userId, $date]);
        $messages[] = 'Penugasan shift dihapus (kembali ke default Siang).';
      } catch (Exception $e) {
        $errors[] = 'Gagal menghapus penugasan: ' . $e->getMessage();
      }
    } else {
      $errors[] = 'Data hapus tidak valid.';
    }
  } elseif ($action === 'toggle_flag') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $isActive = isset($_POST['night_active']) ? 1 : 0;
    $customStart = trim($_POST['custom_start'] ?? '');
    $customEnd = trim($_POST['custom_end'] ?? '');
    $note = trim($_POST['note'] ?? '');
    if (!$userId) { $errors[] = 'Pilih karyawan'; }
    if (!$errors) {
      try {
        $cs = preg_match('/^\d{2}:\d{2}$/', $customStart) ? $customStart.':00' : null;
        $ce = preg_match('/^\d{2}:\d{2}$/', $customEnd) ? $customEnd.':00' : null;
        $up = $pdo->prepare("INSERT INTO user_night_shift_flags (user_id, is_active, custom_start, custom_end, note, updated_by)
                              VALUES (?,?,?,?,?,?)
                              ON DUPLICATE KEY UPDATE is_active=VALUES(is_active), custom_start=VALUES(custom_start), custom_end=VALUES(custom_end), note=VALUES(note), updated_by=VALUES(updated_by)");
        $up->execute([$userId, $isActive, $cs, $ce, $note, $uid]);
        $messages[] = $isActive ? 'Badge shift malam diaktifkan.' : 'Badge shift malam dilepas (kembali ke default).';
      } catch (Exception $e) {
        $errors[] = 'Gagal menyimpan badge: ' . $e->getMessage();
      }
    }
  } else {
    $userId = (int)($_POST['user_id'] ?? 0);
    $date = trim($_POST['shift_date'] ?? '');
    
    $customStart = trim($_POST['custom_start'] ?? '');
    $customEnd = trim($_POST['custom_end'] ?? '');
    $note = trim($_POST['note'] ?? '');

    if (!$userId) $errors[] = 'Pilih karyawan';
    if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $errors[] = 'Tanggal tidak valid';

    try {
      $sidStmt = $pdo->prepare('SELECT id, cross_midnight FROM work_shifts WHERE code = ?');
      $sidStmt->execute(['NIGHT']);
      $ws = $sidStmt->fetch(PDO::FETCH_ASSOC);
      if (!$ws) throw new Exception('Master shift NIGHT tidak ditemukan');

      if (!$errors) {
        
        $cs = preg_match('/^\d{2}:\d{2}$/', $customStart) ? $customStart.':00' : null;
        $ce = preg_match('/^\d{2}:\d{2}$/', $customEnd) ? $customEnd.':00' : null;

        $stmt = $pdo->prepare('INSERT INTO user_shift_assignments (user_id, shift_date, shift_id, custom_start, custom_end, note, created_by)
                               VALUES (?,?,?,?,?,?,?)
                               ON DUPLICATE KEY UPDATE shift_id=VALUES(shift_id), custom_start=VALUES(custom_start), custom_end=VALUES(custom_end), note=VALUES(note), created_by=VALUES(created_by)');
        $stmt->execute([$userId, $date, (int)$ws['id'], $cs, $ce, $note, $uid]);
        $messages[] = 'Penugasan shift malam disimpan.';
      }
    } catch (Exception $e) {
      $errors[] = 'Gagal menyimpan: ' . $e->getMessage();
    }
  }
}


$users = [];
try {
  $uStmt = $pdo->query("SELECT id, full_name, username FROM users WHERE is_active=1 ORDER BY full_name ASC");
  $users = $uStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {}

$recent = [];
try {
  $rStmt = $pdo->query("SELECT usa.*, u.full_name, ws.code, ws.name as shift_name
                        FROM user_shift_assignments usa
                        JOIN users u ON u.id = usa.user_id
                        JOIN work_shifts ws ON ws.id = usa.shift_id
                        ORDER BY usa.shift_date DESC, usa.created_at DESC
                        LIMIT 25");
  $recent = $rStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {}
?>
<div class="p-6">
  <h1 class="text-xl font-bold mb-4">Penugasan Shift</h1>
  <?php foreach ($messages as $m): ?>
    <div class="mb-3 p-3 rounded bg-green-100 text-green-800"><?= htmlspecialchars($m) ?></div>
  <?php endforeach; ?>
  <?php foreach ($errors as $er): ?>
    <div class="mb-3 p-3 rounded bg-red-100 text-red-800"><?= htmlspecialchars($er) ?></div>
  <?php endforeach; ?>

  <form method="post" class="grid gap-4 w-full max-w-3xl">
    <div>
      <label class="block text-sm font-medium mb-1">Karyawan</label>
      <select name="user_id" class="border rounded px-2 py-1 w-full" required>
        <option value="">-- Pilih --</option>
        <?php foreach ($users as $usr): ?>
          <option value="<?= (int)$usr['id'] ?>"><?= htmlspecialchars($usr['full_name'] ?: $usr['username']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex items-center gap-2">
      <input type="checkbox" id="night_active" name="night_active" class="border rounded">
      <label for="night_active" class="text-sm">Aktifkan badge Shift Malam (berlaku terus sampai dilepas)</label>
    </div>
    <input type="hidden" name="action" value="toggle_flag">
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Mulai (Shift Malam)</label>
        <input type="time" name="custom_start" class="border rounded px-2 py-1 w-full" placeholder="20:00">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Selesai (Shift Malam)</label>
        <input type="time" name="custom_end" class="border rounded px-2 py-1 w-full" placeholder="05:00">
      </div>
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Catatan</label>
      <input type="text" name="note" class="border rounded px-2 py-1 w-full" placeholder="Opsional">
    </div>
    <div>
      <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Simpan</button>
      <a href="dashboard.php?page=absen-list" class="ml-2 text-slate-600">Lihat daftar absen</a>
    </div>
  </form>

  <h2 class="text-lg font-semibold mt-8 mb-3">Badge Shift Malam Aktif</h2>
  <div class="overflow-x-auto w-full">
    <table class="min-w-full border">
      <thead class="bg-slate-100">
        <tr>
          <th class="px-3 py-2 text-left">Karyawan</th>
          <th class="px-3 py-2 text-left">Custom</th>
          <th class="px-3 py-2 text-left">Catatan</th>
          <th class="px-3 py-2 text-left">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php
          $active = [];
          try {
            $aStmt = $pdo->query("SELECT f.*, u.full_name FROM user_night_shift_flags f JOIN users u ON u.id=f.user_id WHERE f.is_active=1 ORDER BY u.full_name ASC");
            $active = $aStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
          } catch (Exception $e) {}
        ?>
        <?php if (!$active): ?>
          <tr><td colspan="4" class="px-3 py-4 text-slate-400">Tidak ada badge shift malam aktif</td></tr>
        <?php else: foreach ($active as $r): ?>
          <tr class="border-t">
            <td class="px-3 py-2"><?= htmlspecialchars($r['full_name']) ?></td>
            <td class="px-3 py-2"><?php if ($r['custom_start'] || $r['custom_end']): ?>
              <?= htmlspecialchars(substr((string)$r['custom_start'],0,5)) ?>–<?= htmlspecialchars(substr((string)$r['custom_end'],0,5)) ?>
            <?php else: ?>-
            <?php endif; ?></td>
            <td class="px-3 py-2"><?= htmlspecialchars($r['note'] ?: '-') ?></td>
            <td class="px-3 py-2">
              <form method="post" onsubmit="return confirm('Lepas badge shift malam untuk pengguna ini?');" class="inline">
                <input type="hidden" name="action" value="toggle_flag">
                <input type="hidden" name="user_id" value="<?= (int)$r['user_id'] ?>">
                <button type="submit" class="text-red-600 hover:underline text-xs">Lepas Badge</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
