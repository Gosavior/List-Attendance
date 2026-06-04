<?php

require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    http_response_code(403);
    die('Access denied. Administrator only.');
}

$message = '';
$error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_holiday'])) {
            $date = $_POST['holiday_date'];
            $name = trim($_POST['holiday_name']);
            $type = $_POST['holiday_type']; 
            
            $stmt = $pdo->prepare("
                INSERT INTO company_holidays (holiday_date, holiday_name, holiday_type, created_by, created_at) 
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                holiday_name = VALUES(holiday_name),
                holiday_type = VALUES(holiday_type),
                updated_at = NOW()
            ");
            $stmt->execute([$date, $name, $type, $_SESSION['user_id']]);
            
            $message = "Holiday berhasil ditambahkan: $name ($date)";
            
        } elseif (isset($_POST['delete_holiday'])) {
            $holidayId = (int)$_POST['holiday_id'];
            
            $stmt = $pdo->prepare("DELETE FROM company_holidays WHERE id = ?");
            $stmt->execute([$holidayId]);
            
            $message = "Holiday berhasil dihapus";
            
        } elseif (isset($_POST['generate_libur_for_date'])) {
            $date = $_POST['libur_date'];
            $created = generateLiburAttendance($pdo, $date);
            
            $message = "Berhasil membuat status 'Libur' untuk $created staff pada tanggal $date";
            
        } elseif (isset($_POST['bulk_weekend_holidays'])) {
            $year = (int)$_POST['year'];
            $month = (int)$_POST['month'];
            $include_saturday = isset($_POST['include_saturday']);
            $include_sunday = isset($_POST['include_sunday']);
            
            $created = addWeekendHolidays($pdo, $year, $month, $include_saturday, $include_sunday, $_SESSION['user_id']);
            $message = "Berhasil menambahkan $created hari libur weekend untuk " . date('F Y', mktime(0, 0, 0, $month, 1, $year));
        }
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

function generateLiburAttendance($pdo, $date) {
    $created = 0;
    
    $stmt = $pdo->prepare("
        SELECT id, full_name 
        FROM users 
        WHERE role != 'administrator' 
        AND (status IS NULL OR status = 'active')
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM attendances WHERE user_id = ? AND attendance_date = ?');
        $stmt->execute([$user['id'], $date]);
        $exists = (int)$stmt->fetchColumn() > 0;
        
        if (!$exists) {
            $stmt = $pdo->prepare('
                INSERT INTO attendances 
                (user_id, attendance_date, today_plan, check_in_time, check_out_time, check_in_photo, check_out_photo, check_out_location, notes, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                $user['id'], 
                $date, 
                'Libur', 
                '0000-00-00 00:00:00', 
                null, 
                '', 
                null, 
                '', 
                'Hari libur perusahaan', 
                'Libur'
            ]);
            $created++;
        }
    }
    
    return $created;
}

function addWeekendHolidays($pdo, $year, $month, $includeSaturday, $includeSunday, $userId) {
    $created = 0;
    $daysInMonth = (int)date('t', strtotime(sprintf('%04d-%02d-01', (int)$year, (int)$month)));
    
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $dayOfWeek = date('w', strtotime($date)); 
        
        $shouldAdd = false;
        $holidayName = '';
        
        if ($dayOfWeek == 6 && $includeSaturday) {
            $shouldAdd = true;
            $holidayName = 'Libur Sabtu';
        } elseif ($dayOfWeek == 0 && $includeSunday) {
            $shouldAdd = true;
            $holidayName = 'Libur Minggu';
        }
        
        if ($shouldAdd) {
            $stmt = $pdo->prepare("
                INSERT INTO company_holidays (holiday_date, holiday_name, holiday_type, created_by, created_at) 
                VALUES (?, ?, 'weekend_override', ?, NOW())
                ON DUPLICATE KEY UPDATE updated_at = NOW()
            ");
            $stmt->execute([$date, $holidayName, $userId]);
            $created++;
        }
    }
    
    return $created;
}

$stmt = $pdo->prepare("
    SELECT h.*, u.full_name as created_by_name
    FROM company_holidays h
    LEFT JOIN users u ON h.created_by = u.id
    ORDER BY h.holiday_date DESC
");
$stmt->execute();
$holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT * FROM company_holidays 
    WHERE holiday_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY holiday_date ASC
");
$stmt->execute();
$upcomingHolidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id" class="<?= ($_SESSION['theme'] ?? 'light') === 'dark' ? 'dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-mode" content="<?= htmlspecialchars($_SESSION['theme'] ?? 'light') ?>" />
    <title>Manajemen Hari Libur</title>
    <link rel="stylesheet" href="./src/output.css" />
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
</head>
<body class="bg-gray-50 dark:bg-slate-900">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-6xl mx-auto">
            
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-6 mb-6">
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-4"><i class="fas fa-calendar-days mr-2"></i>Manajemen Hari Libur</h1>
                
                <?php if ($message): ?>
                    <script>document.addEventListener('DOMContentLoaded',function(){showToast(<?= json_encode($message) ?>,'success');});</script>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <script>document.addEventListener('DOMContentLoaded',function(){showToast(<?= json_encode($error) ?>,'error');});</script>
                <?php endif; ?>
                
                
                <?php if (!empty($upcomingHolidays)): ?>
                    <div class="bg-blue-100 dark:bg-blue-900/30 border border-blue-400 dark:border-blue-800 text-blue-700 dark:text-blue-400 px-4 py-3 rounded mb-6">
                        <h3 class="font-bold mb-2"><i class="fas fa-gift mr-2"></i>Hari Libur Mendatang (30 Hari Ke Depan):</h3>
                        <ul class="list-disc list-inside">
                            <?php foreach ($upcomingHolidays as $holiday): ?>
                                <li><?= date('d M Y', strtotime($holiday['holiday_date'])) ?> - <?= htmlspecialchars($holiday['holiday_name']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                
                <div class="grid md:grid-cols-2 gap-6 mb-8">
                    <div class="bg-gray-50 dark:bg-slate-700 p-4 rounded-lg">
                        <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white"><i class="fas fa-plus mr-2"></i>Tambah Hari Libur</h2>
                        <form method="post" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-1 text-gray-900 dark:text-gray-200">Tanggal Libur</label>
                                <input type="date" name="holiday_date" required class="w-full border border-gray-300 dark:border-slate-600 rounded px-3 py-2 bg-white dark:bg-slate-600 text-gray-900 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1 text-gray-900 dark:text-gray-200">Nama Libur</label>
                                <input type="text" name="holiday_name" required placeholder="Contoh: Hari Kemerdekaan" class="w-full border border-gray-300 dark:border-slate-600 rounded px-3 py-2 bg-white dark:bg-slate-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1 text-gray-900 dark:text-gray-200">Jenis Libur</label>
                                <select name="holiday_type" class="w-full border border-gray-300 dark:border-slate-600 rounded px-3 py-2 bg-white dark:bg-slate-600 text-gray-900 dark:text-white">
                                    <option value="national">Hari Libur Nasional</option>
                                    <option value="company">Libur Perusahaan</option>
                                    <option value="weekend_override">Override Weekend</option>
                                </select>
                            </div>
                            <button type="submit" name="add_holiday" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded transition">
                                Tambah Libur
                            </button>
                        </form>
                    </div>
                    
                    
                    <div class="bg-gray-50 dark:bg-slate-700 p-4 rounded-lg">
                        <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white"><i class="fas fa-calendar-week mr-2"></i>Bulk Libur Weekend</h2>
                        <form method="post" class="space-y-4">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-sm font-medium mb-1 text-gray-900 dark:text-gray-200">Tahun</label>
                                    <select name="year" class="w-full border border-gray-300 dark:border-slate-600 rounded px-3 py-2 bg-white dark:bg-slate-600 text-gray-900 dark:text-white">
                                        <?php for ($y = date('Y'); $y <= date('Y') + 2; $y++): ?>
                                            <option value="<?= $y ?>"><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1 text-gray-900 dark:text-gray-200">Bulan</label>
                                    <select name="month" class="w-full border border-gray-300 dark:border-slate-600 rounded px-3 py-2 bg-white dark:bg-slate-600 text-gray-900 dark:text-white">
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?= $m ?>" <?= $m == date('n') ? 'selected' : '' ?>>
                                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="flex items-center text-gray-900 dark:text-gray-200">
                                    <input type="checkbox" name="include_saturday" class="mr-2">
                                    Jadikan Sabtu sebagai hari libur
                                </label>
                                <label class="flex items-center text-gray-900 dark:text-gray-200">
                                    <input type="checkbox" name="include_sunday" class="mr-2" checked>
                                    Jadikan Minggu sebagai hari libur
                                </label>
                            </div>
                            <button type="submit" name="bulk_weekend_holidays" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 rounded transition">
                                Buat Libur Weekend
                            </button>
                        </form>
                    </div>
                </div>
                
                
                <div class="bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 p-4 rounded-lg mb-6">
                    <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white"><i class="fas fa-umbrella-beach mr-2"></i>Buat Status "Libur" untuk Tanggal Tertentu</h2>
                    <form method="post" class="flex gap-4 items-end">
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-1 text-gray-900 dark:text-gray-200">Tanggal</label>
                            <input type="date" name="libur_date" required class="w-full border border-gray-300 dark:border-slate-600 rounded px-3 py-2 bg-white dark:bg-slate-600 text-gray-900 dark:text-white">
                        </div>
                        <button type="submit" name="generate_libur_for_date" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded transition">
                            Buat Status Libur
                        </button>
                    </form>
                    <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-2">
                        <i class="fas fa-exclamation-triangle mr-1"></i>Ini akan membuat status "Libur" untuk semua staff yang belum memiliki record absensi pada tanggal tersebut.
                    </p>
                </div>
                
                
                <div class="bg-white dark:bg-slate-800">
                    <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white"><i class="fas fa-list-check mr-2"></i>Daftar Hari Libur</h2>
                    
                    <?php if (!empty($holidays)): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full table-auto">
                                <thead class="bg-gray-100 dark:bg-slate-700">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-gray-900 dark:text-white">Tanggal</th>
                                        <th class="px-4 py-2 text-left text-gray-900 dark:text-white">Nama Libur</th>
                                        <th class="px-4 py-2 text-left text-gray-900 dark:text-white">Jenis</th>
                                        <th class="px-4 py-2 text-left text-gray-900 dark:text-white">Dibuat Oleh</th>
                                        <th class="px-4 py-2 text-left text-gray-900 dark:text-white">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($holidays as $holiday): ?>
                                        <tr class="border-b border-gray-200 dark:border-slate-600">
                                            <td class="px-4 py-2 text-gray-900 dark:text-gray-300"><?= date('d M Y', strtotime($holiday['holiday_date'])) ?></td>
                                            <td class="px-4 py-2 text-gray-900 dark:text-gray-300"><?= htmlspecialchars($holiday['holiday_name']) ?></td>
                                            <td class="px-4 py-2">
                                                <span class="px-2 py-1 text-xs rounded-full 
                                                    <?= $holiday['holiday_type'] === 'national' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' : 
                                                        ($holiday['holiday_type'] === 'company' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300') ?>">
                                                    <?= ucfirst($holiday['holiday_type']) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-gray-900 dark:text-gray-300"><?= htmlspecialchars($holiday['created_by_name'] ?? 'Unknown') ?></td>
                                            <td class="px-4 py-2">
                                                <form method="post" style="display: inline;" onsubmit="return confirm('Yakin ingin menghapus hari libur ini?')">
                                                    <input type="hidden" name="holiday_id" value="<?= $holiday['id'] ?>">
                                                    <button type="submit" name="delete_holiday" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-sm transition">
                                                        <i class="fas fa-trash mr-1"></i>Hapus
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 dark:text-gray-400 italic">Belum ada hari libur yang didefinisikan.</p>
                    <?php endif; ?>
                </div>
                
                <div class="mt-6 text-center">
                    <a href="/dashboard.php?page=absen-list" class="inline-block bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded transition">
                        ← Kembali ke Daftar Absen
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
