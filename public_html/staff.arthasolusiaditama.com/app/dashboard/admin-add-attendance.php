<?php
@date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';


$roleStr = strtolower(trim($user['role'] ?? ($_SESSION['role'] ?? '')));
$isAdmin = $roleStr && preg_match('/admin|administrator/', $roleStr);
if (!$isAdmin) { http_response_code(403); echo "Unauthorized"; exit; }


$stmt = $pdo->query("SELECT id, full_name, role FROM users WHERE is_active=1 AND role NOT IN ('administrator') ORDER BY full_name");
$staffs = $stmt->fetchAll(PDO::FETCH_ASSOC);


$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_attendance'])) {
    $staff_id = (int)($_POST['staff_id'] ?? 0);
    $date = trim($_POST['attendance_date'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $check_in_time = trim($_POST['check_in_time'] ?? '');
    $check_out_time = trim($_POST['check_out_time'] ?? '');
    $today_plan = trim($_POST['today_plan'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $check_in_photo = '';
    
    if (!empty($_FILES['check_in_photo']['name'])) {
        $targetDir = 'public/assets/uploads/Attendance/' . $staff_id . '/';
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $fname = 'admin_' . $staff_id . '_' . date('Ymd_His') . '_' . basename($_FILES['check_in_photo']['name']);
        $targetFile = $targetDir . $fname;
        if (move_uploaded_file($_FILES['check_in_photo']['tmp_name'], $targetFile)) {
            // Compress image for faster loading
            $ext = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                require_once __DIR__ . '/../helpers/image-compress.php';
                compressUploadedImage($targetFile, 1280, 1280, 75);
            }
            $check_in_photo = $targetFile;
        }
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO attendances (user_id, attendance_date, today_plan, check_in_time, check_out_time, check_in_photo, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$staff_id, $date, $today_plan, $check_in_time, $check_out_time ?: null, $check_in_photo, $notes, $status]);
        $success = true;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Admin: Tambah Absen Staff</title>
  <link rel="stylesheet" href="../../src/output.css">
  <script src="../../public/assets/js/toast.js"></script>
</head>
<body class="bg-gray-50">
<div class="max-w-lg mx-auto mt-8 bg-white p-6 rounded-xl shadow">
  <h1 class="font-bold text-lg mb-4">Tambah Absen Staff (Admin)</h1>
  <?php if ($success): ?>
    <script>document.addEventListener('DOMContentLoaded',function(){if(typeof showToast==='function')showToast('Absen berhasil ditambahkan!','success');});</script>
  <?php elseif ($error): ?>
    <script>document.addEventListener('DOMContentLoaded',function(){if(typeof showToast==='function')showToast(<?= json_encode('Gagal: ' . $error) ?>,'error');});</script>
  <?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="space-y-3">
    <input type="hidden" name="add_attendance" value="1">
    <div>
      <label class="block text-sm font-medium mb-1">Staff</label>
      <select name="staff_id" required class="border rounded px-2 py-1 w-full">
        <option value="">- Pilih Staff -</option>
        <?php foreach ($staffs as $s): ?>
          <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['role']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Tanggal Absen</label>
      <input type="date" name="attendance_date" required class="border rounded px-2 py-1 w-full">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Status</label>
      <select name="status" required class="border rounded px-2 py-1 w-full">
        <option value="">-</option>
        <option value="Hadir">Hadir</option>
        <option value="Terlambat">Terlambat</option>
        <option value="Pulang Cepat">Pulang Lebih Awal</option>
        <option value="Izin">Izin</option>
        <option value="Sakit">Sakit</option>
        <option value="Alpha">Alpha</option>
        <option value="Cuti">Cuti</option>
        <option value="Lembur">Lembur</option>
        <option value="Cuti">Cuti</option>
      </select>
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Jam Masuk</label>
      <input type="time" name="check_in_time" class="border rounded px-2 py-1 w-full">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Jam Pulang</label>
      <input type="time" name="check_out_time" class="border rounded px-2 py-1 w-full">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Plan Hari Ini</label>
      <input type="text" name="today_plan" class="border rounded px-2 py-1 w-full">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Catatan</label>
      <textarea name="notes" class="border rounded px-2 py-1 w-full"></textarea>
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Foto Bukti (opsional)</label>
      <input type="file" name="check_in_photo" accept="image/*" class="border rounded px-2 py-1 w-full">
    </div>
    <div class="flex justify-end gap-2 mt-4">
      <button type="submit" class="px-4 py-2 bg-indigo-700 text-white rounded">Simpan</button>
    </div>
  </form>
</div>
</body>
</html>
