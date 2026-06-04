<?php
@date_default_timezone_set('Asia/Jakarta');

try {
    require_once __DIR__ . '/../auth/auth.php';
    require_once __DIR__ . '/../config/database.php';
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

/** Tabel pendukung List Absen (dev DB sering belum punya schema lengkap). */
function ensureAbsenListSchema(PDO $pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_activities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            attendance_id INT NOT NULL,
            photo_path VARCHAR(500) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_attendance_activities_att (attendance_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            attendance_date DATE NOT NULL,
            gps_lat DECIMAL(10, 8) DEFAULT NULL,
            gps_lng DECIMAL(11, 8) DEFAULT NULL,
            gps_accuracy DECIMAL(10, 2) DEFAULT NULL,
            location_name VARCHAR(500) DEFAULT NULL,
            photo_path VARCHAR(500) DEFAULT NULL,
            today_plan TEXT DEFAULT NULL,
            reason TEXT DEFAULT NULL,
            requested_check_in_time TIME DEFAULT NULL,
            request_type VARCHAR(30) NOT NULL DEFAULT 'checkin',
            requested_check_out_time TIME DEFAULT NULL,
            missed_checkout_date DATE DEFAULT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            decided_by INT DEFAULT NULL,
            decided_at DATETIME DEFAULT NULL,
            attendance_id INT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_attendance_requests_user_date (user_id, attendance_date),
            INDEX idx_attendance_requests_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS leave_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(30) NOT NULL DEFAULT 'permission',
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            reason TEXT DEFAULT NULL,
            proof_path VARCHAR(500) DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_leave_user_dates (user_id, start_date, end_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        error_log('ensureAbsenListSchema: ' . $e->getMessage());
    }
}

if (isset($pdo) && $pdo instanceof PDO) {
    ensureAbsenListSchema($pdo);
}


function assetUrl($rel) {
  static $baseUrlPrefix = null;
  static $projectRoot = null;
  static $loggedMissing = [];

  if ($baseUrlPrefix === null) {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $baseUrlPrefix = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($baseUrlPrefix === '.' || $baseUrlPrefix === '/') {
      $baseUrlPrefix = '';
    }
    $projectRoot = dirname(__DIR__, 2);
  }

  if ($rel === null) {
    return '';
  }

  $rel = trim((string)$rel);
  if ($rel === '' || strcasecmp($rel, 'null') === 0 || strcasecmp($rel, 'undefined') === 0) {
    return '';
  }

  if (preg_match('#^(?:https?:)?//#', $rel) || strpos($rel, 'data:') === 0) {
    return $rel;
  }

  $originalRel = $rel;
  $rel = preg_replace('#^(\./)+#', '', $rel);
  $rel = ltrim($rel, '/');

  if (strpos($rel, 'uploads/') === 0) {
    $rel = 'public/assets/images/' . $rel;
  }

  $normalizedRel = str_replace('\\', '/', $rel);

  if ($projectRoot && (strpos($normalizedRel, 'public/assets/images/') === 0 || strpos($normalizedRel, 'storage/') === 0)) {
    $absolutePath = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedRel);
    if (!isset($loggedMissing[$absolutePath]) && !file_exists($absolutePath)) {
      $loggedMissing[$absolutePath] = true;
      $requestUri = $_SERVER['REQUEST_URI'] ?? '';
      error_log(sprintf(
        'ABSEN-LIST Missing asset: %s (resolved: %s, request: %s)',
        $originalRel,
        $absolutePath,
        $requestUri
      ));
    }
  }

  
  if (strpos($normalizedRel, 'storage/') === 0) {
    return '/serve_image.php?path=' . rawurlencode($normalizedRel);
  }

  $segments = array_map('rawurlencode', array_filter(explode('/', $normalizedRel), 'strlen'));
  $path = implode('/', $segments);

  if ($baseUrlPrefix !== '') {
    return $baseUrlPrefix . '/' . $path;
  }

  return '/' . $path;
}

function resolveAvatarSrc($raw) {
  $fallback = 'public/assets/images/avatar-default.png';
  $normalized = trim((string)$raw);
  if ($normalized === '' || strcasecmp($normalized, 'null') === 0 || strcasecmp($normalized, 'undefined') === 0) {
    return assetUrl($fallback);
  }
  if (preg_match('#^(?:https?:)?//#', $normalized)) {
    return $normalized;
  }
  $normalized = preg_replace('#^(\./)+#', '', $normalized);
  $normalized = ltrim($normalized, '/');
  if ($normalized === '') {
    return assetUrl($fallback);
  }
  $resolved = assetUrl($normalized);
  return $resolved !== '' ? $resolved : assetUrl($fallback);
}
function timeOnly($dt) { return $dt ? date('H:i', strtotime($dt)) : '-'; }

/** Jumlah hari dalam bulan (tanpa ekstensi calendar PHP). */
function daysInMonth($year, $month) {
    $year = max(1970, (int)$year);
    $month = max(1, min(12, (int)$month));
    return (int)date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
}

function dbTableExists($pdo, $tableName) {
    static $cache = [];
    $key = (string)$tableName;
    if (!isset($cache[$key])) {
        $cache[$key] = false;
        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$key]);
            $cache[$key] = (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $cache[$key] = false;
        }
    }
    return $cache[$key];
}

/**
 * Absensi milik user untuk tampilan List Absen.
 * Mencakup absen siang (tanggal hari ini) dan absen malam (attendance_date kemarin, check-in hari ini).
 */
function fetchMyAttendanceForList($pdo, $userId, $forDate = null) {
    $userId = (int)$userId;
    if ($userId <= 0) {
        return [];
    }
    $forDate = $forDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $forDate) ? $forDate : date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime($forDate . ' -1 day'));
    try {
        $stmt = $pdo->prepare("
          SELECT a.*, u.full_name, u.role, u.avatar
          FROM attendances a
          JOIN users u ON u.id = a.user_id
          WHERE a.user_id = ?
          AND (
            a.attendance_date = ?
            OR (
              a.check_in_time IS NOT NULL
              AND a.check_in_time > '1970-01-01 00:00:00'
              AND DATE(a.check_in_time) = ?
            )
            OR (
              a.attendance_date = ?
              AND a.check_in_time IS NOT NULL
              AND a.check_in_time > '1970-01-01 00:00:00'
            )
          )
          ORDER BY
            CASE WHEN a.attendance_date = ? THEN 0 ELSE 1 END,
            a.check_in_time DESC,
            a.id DESC
          LIMIT 15
        ");
        $stmt->execute([$userId, $forDate, $forDate, $yesterday, $forDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('fetchMyAttendanceForList: ' . $e->getMessage());
        return [];
    }
}

/** Izin/cuti disetujui untuk satu tanggal (aman jika tabel belum ada). */
function fetchApprovedLeavesForDate($pdo, $date, $userId = null) {
    if (!dbTableExists($pdo, 'leave_requests')) {
        return [];
    }
    try {
        $sql = "
          SELECT lr.*, u.full_name, u.role, u.avatar,
                 CASE
                   WHEN lr.type = 'permission' THEN 'Izin'
                   WHEN lr.type = 'sick' THEN 'Sakit'
                   WHEN lr.type = 'leave' THEN 'Cuti'
                   ELSE 'Izin'
                 END as status,
                 lr.start_date as attendance_date,
                 NULL as check_in_time,
                 NULL as check_out_time,
                 NULL as check_in_photo,
                 NULL as check_out_photo,
                 NULL as today_plan,
                 NULL as notes
          FROM leave_requests lr
          JOIN users u ON lr.user_id = u.id
          LEFT JOIN attendances a ON a.user_id = lr.user_id AND a.attendance_date = lr.start_date
          WHERE lr.status = 'approved' AND ? BETWEEN lr.start_date AND lr.end_date
          AND a.id IS NULL
        ";
        $params = [$date];
        if ($userId !== null) {
            $sql .= ' AND lr.user_id = ?';
            $params[] = (int)$userId;
        }
        $sql .= ' ORDER BY lr.created_at ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('fetchApprovedLeavesForDate: ' . $e->getMessage());
        return [];
    }
}

/** Petakan role dev (staff/admin) ke role produksi — selaras dengan sidebar & dashboard. */
function resolveAbsenListRole($role) {
    $r = strtolower(trim((string)$role));
    if ($r === 'staff') {
        return 'technician';
    }
    if ($r === 'admin') {
        return 'administrator';
    }
    return $r;
}

function mapStatusClass($status) {
    switch ($status) {
        case 'hadir':
        case 'terlambat':
            return 'absensi-status-present';
        case 'izin':
        case 'sakit':
        case 'cuti':
        case 'not checked out':
            return 'absensi-status-leave';
        case 'alpha':
            return 'absensi-status-alpha';
        default:
            return 'absensi-status-empty';
    }
}

function roleLabel($role) {
    $labels = [
        'administrator' => 'Administrator',
        'admin' => 'Admin',
        'direktur' => 'Direktur',
        'technician_manager' => 'Manager Teknisi',
        'technician' => 'Teknisi',
        'staff' => 'Staff',
        'sales' => 'Sales',
        'hse' => 'HSE',
        'internship' => 'Internship',
        'daily' => 'Daily',
        'driver' => 'Driver',
    ];
    $key = strtolower(trim((string)$role));
    if (isset($labels[$key])) {
        return $labels[$key];
    }
    return $role ? ucwords(str_replace('_', ' ', $role)) : 'Staff';
}
function statusBadge($status) {
    if (!$status) return '<span class="px-2 py-1 rounded-full text-xs bg-gray-300 text-gray-700">-</span>';
    $map = [
        'Hadir' => 'bg-green-100 text-green-700',
        'Terlambat' => 'bg-yellow-100 text-yellow-700',
        'Not Checked Out' => 'bg-orange-100 text-orange-700',
        'Izin' => 'bg-blue-100 text-blue-700',
        'Sakit' => 'bg-blue-200 text-blue-800',
        'Alpha' => 'bg-red-100 text-red-700',
        'Cuti' => 'bg-purple-100 text-purple-700',
        'Lembur' => 'bg-indigo-100 text-indigo-700',
        'No Record' => 'bg-gray-200 text-gray-600'
    ];
    $cls = $map[$status] ?? 'bg-gray-100 text-gray-700';
    return '<span class="px-2 py-1 rounded-full text-xs font-semibold '.$cls.'">'.htmlspecialchars($status).'</span>';
}

function getCompanySettingValue($pdo, $settingKey, $default = '') {
    static $keyColumn = null;
    static $available = null;

    if ($available === null) {
        $available = false;
        try {
            $cols = $pdo->query('SHOW COLUMNS FROM company_settings')->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('setting_key', $cols, true)) {
                $keyColumn = 'setting_key';
                $available = true;
            } elseif (in_array('setting_name', $cols, true)) {
                $keyColumn = 'setting_name';
                $available = true;
            }
        } catch (Throwable $e) { }
    }

    if (!$available || !$keyColumn) return $default;

    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM company_settings WHERE {$keyColumn} = ? LIMIT 1");
        $stmt->execute([$settingKey]);
        $value = $stmt->fetchColumn();
        return ($value !== false && $value !== null && $value !== '') ? (string)$value : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function normalizeTimeValue($value, $default) {
    $value = trim((string)$value);
    if (preg_match('/^([01]\\d|2[0-3]):([0-5]\\d)(?::([0-5]\\d))?$/', $value, $m)) {
        return sprintf('%02d:%02d:00', (int)$m[1], (int)$m[2]);
    }
    return $default;
}

function isNightAttendanceRow($row, $nightStart = '18:00:00', $nightEnd = '06:00:00') {
    if (!empty($row['is_cross_midnight']) && (int)$row['is_cross_midnight'] === 1) return true;

    $scheduledStart = $row['scheduled_start'] ?? null;
    $scheduledEnd = $row['scheduled_end'] ?? null;
    if ($scheduledStart && $scheduledEnd) {
        $startTs = strtotime($scheduledStart);
        $endTs = strtotime($scheduledEnd);
        if ($startTs && $endTs && $endTs > $startTs && date('Y-m-d', $startTs) !== date('Y-m-d', $endTs)) return true;
    }

    $checkIn = $row['check_in_time'] ?? '';
    if ($checkIn && $checkIn !== '0000-00-00 00:00:00') {
        $checkInHour = (int)date('H', strtotime($checkIn));
        $nightStartHour = (int)substr($nightStart, 0, 2);
        $nightEndHour = (int)substr($nightEnd, 0, 2);
        return ($checkInHour >= $nightStartHour) || ($checkInHour < $nightEndHour);
    }

    return false;
}

function buildShiftRoleGroups($attendances, $nightStart = '18:00:00', $nightEnd = '06:00:00') {
    $groups = ['day' => [], 'night' => []];
    foreach ($attendances as $row) {
        $shiftKey = isNightAttendanceRow($row, $nightStart, $nightEnd) ? 'night' : 'day';
        $roleKey = $row['role'] ?? 'staff';
        $groups[$shiftKey][$roleKey][] = $row;
    }
    ksort($groups['day']);
    ksort($groups['night']);
    return $groups;
}

function shiftLabelBadge($shiftKey) {
    if ($shiftKey === 'night') {
        return '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300"><i class="fas fa-moon"></i> Shift Malam</span>';
    }
    return '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"><i class="fas fa-sun"></i> Shift Siang</span>';
}

function shiftSectionHeader($shiftKey, $count) {
    $isNight = $shiftKey === 'night';
    $icon = $isNight ? 'fa-moon text-indigo-400' : 'fa-sun text-amber-500';
    $label = $isNight ? 'Absen Malam' : 'Absen Siang';
    $border = $isNight ? 'border-indigo-300 dark:border-indigo-700 bg-indigo-50/40 dark:bg-indigo-900/10' : 'border-amber-300 dark:border-amber-700 bg-amber-50/40 dark:bg-amber-900/10';
    return '<div class="mt-4 mb-3 rounded-xl border '.$border.' px-3 py-2 flex items-center justify-between"><div class="flex items-center gap-2 font-bold text-slate-800 dark:text-white text-sm"><i class="fas '.$icon.'"></i><span>'.$label.'</span></div><span class="text-xs bg-white/80 dark:bg-slate-800 px-2 py-0.5 rounded-full text-slate-600 dark:text-slate-300">'.$count.' Staff</span></div>';
}

function formatDateRange($start, $end) {
  if (!$start && !$end) return '-';
  if ($start && !$end) return date('d M Y', strtotime($start));
  if (!$start && $end) return date('d M Y', strtotime($end));
  $s = date('d M Y', strtotime($start));
  $e = date('d M Y', strtotime($end));
  return $s === $e ? $s : ($s . ' — ' . $e);
}

/** Kartu daftar absensi (satu baris per record). */
function renderAttendanceCardsList(array $records, $emptyMessage) {
    if (empty($records)) {
        echo '<div class="text-center text-slate-500 dark:text-slate-400 italic bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-6">'
            . htmlspecialchars($emptyMessage) . '</div>';
        return;
    }
    
    echo '<div class="space-y-4">';
    
    // Setup array hari dan bulan untuk format tanggal
    $hari_indo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulan_indo = ['', 'Jun', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    foreach ($records as $a) {
        $isLeave = in_array(trim($a['status'] ?? ''), ['Izin', 'Sakit', 'Cuti', 'Alpha'], true);
        $hasCheckIn = !empty($a['check_in_time']) && $a['check_in_time'] !== '0000-00-00 00:00:00';
        
        // Format Tanggal
        $ts = strtotime($a['attendance_date'] ?? date('Y-m-d'));
        $dateStr = $hari_indo[date('w', $ts)] . ', ' . date('d', $ts) . ' ' . $bulan_indo[date('n', $ts)] . ' ' . date('Y', $ts);

        // Membuat inisial avatar (misal: "Staff User" -> "SU")
        $nameStr = htmlspecialchars($a['full_name'] ?? 'Saya');
        $nameParts = explode(' ', $nameStr);
        $initials = strtoupper(substr($nameParts[0], 0, 1));
        if (count($nameParts) > 1) {
            $initials .= strtoupper(substr($nameParts[1], 0, 1));
        }

        // Kalkulasi Durasi Kerja
        $durationStr = '';
        if ($hasCheckIn && !empty($a['check_out_time']) && $a['check_out_time'] !== '0000-00-00 00:00:00') {
            $inTs = strtotime($a['check_in_time']);
            $outTs = strtotime($a['check_out_time']);
            if ($outTs > $inTs) {
                $diff = $outTs - $inTs;
                $h = floor($diff / 3600);
                $m = floor(($diff % 3600) / 60);
                $durationStr = "{$h} jam {$m} menit";
            }
        }

        // Menentukan warna border kiri berdasarkan status
        $statusLabel = trim($a['status'] ?? '');
        $borderColor = 'border-slate-300 dark:border-slate-600'; // Default
        if (in_array($statusLabel, ['Hadir'])) $borderColor = 'border-emerald-600 dark:border-emerald-500';
        elseif (in_array($statusLabel, ['Terlambat'])) $borderColor = 'border-yellow-500';
        elseif (in_array($statusLabel, ['Izin', 'Sakit', 'Cuti'])) $borderColor = 'border-blue-500';
        elseif (in_array($statusLabel, ['Alpha'])) $borderColor = 'border-red-500';

        ?>
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 border-l-[4px] <?= $borderColor ?> rounded-xl shadow-sm overflow-hidden transition hover:shadow-md">
            
            <div class="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-700">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 shrink-0 rounded-full flex items-center justify-center bg-blue-50 dark:bg-slate-700 text-blue-700 dark:text-blue-300 font-bold text-sm">
                        <?= $initials ?>
                    </div>
                    
                    <div>
                        <div class="font-bold text-slate-800 dark:text-slate-100 text-[15px] leading-tight mb-1">
                            <?= $nameStr ?>
                        </div>
                        <div class="text-[12px] text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                            <i class="far fa-calendar-alt opacity-70"></i> <?= $dateStr ?>
                            <?php if (!empty($a['is_cross_midnight'])): ?>
                                <span class="ml-1 text-indigo-500 font-semibold">(Shift Malam)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center gap-2">
                    <?php if ($durationStr): ?>
                        <div class="hidden sm:flex items-center gap-1.5 px-3 py-1 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-300 rounded-full text-[12px] font-medium border border-indigo-100 dark:border-indigo-800">
                            <i class="far fa-clock"></i> <?= $durationStr ?>
                        </div>
                    <?php endif; ?>
                    <?= statusBadge($statusLabel) ?>
                </div>
            </div>

            <?php if ($isLeave && !$hasCheckIn): ?>
                <div class="p-4 bg-slate-50 dark:bg-slate-800/50">
                    <?php if (!empty($a['leave'])): ?>
                        <div class="text-sm text-slate-700 dark:text-slate-300">
                            <p class="mb-1"><span class="text-slate-400 font-semibold uppercase text-[11px] block">Rentang</span> <?= htmlspecialchars(formatDateRange($a['leave']['start_date'], $a['leave']['end_date'])) ?></p>
                            <?php if (!empty($a['leave']['reason'])): ?>
                                <p><span class="text-slate-400 font-semibold uppercase text-[11px] block">Alasan</span> <?= htmlspecialchars($a['leave']['reason']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-slate-200 dark:divide-slate-700">
                    <div class="p-4 sm:p-5">
                        <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                            <i class="fas fa-sign-in-alt text-emerald-500/70"></i> MASUK
                        </div>
                        <div class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white tracking-tight font-mono leading-none">
                            <?= timeOnly($a['check_in_time'] ?? null) ?>
                        </div>
                    </div>
                    <div class="p-4 sm:p-5">
                        <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                            <i class="fas fa-sign-out-alt text-orange-500/70"></i> PULANG
                        </div>
                        <div class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white tracking-tight font-mono leading-none">
                            <?= timeOnly($a['check_out_time'] ?? null) ?>
                        </div>
                    </div>
                    <div class="p-4 sm:p-5 flex flex-col justify-center">
                        <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                            <i class="fas fa-tasks text-blue-500/70"></i> RENCANA KERJA
                        </div>
                        <div class="text-[14px] text-slate-800 dark:text-slate-300 flex items-start gap-2">
                            <i class="fas fa-check-circle text-slate-300 dark:text-slate-600 mt-1 text-[12px]"></i>
                            <span class="font-medium"><?= htmlspecialchars($a['today_plan'] ?: 'Tidak ada rencana tercatat') ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php 
            $hasPhotos = !empty($a['check_in_photo']) || !empty($a['check_out_photo']) || (!empty($a['leave']['proof_path']) || !empty($a['proof_path']));
            $hasNotes = !empty($a['notes']);
            $hasActivities = !empty($a['activities']);
            
            if ($hasPhotos || $hasNotes || $hasActivities): 
            ?>
            <div class="bg-[#f8f8f6] dark:bg-slate-800/80 border-t border-slate-200 dark:border-slate-700 p-3 px-4 text-[13px] text-slate-600 dark:text-slate-400 flex flex-col gap-3">
                
                <?php if ($hasNotes): ?>
                <div class="flex items-start gap-2">
                    <i class="far fa-comment-dots mt-[3px] text-slate-400"></i>
                    <div><?= htmlspecialchars($a['notes']) ?></div>
                </div>
                <?php endif; ?>

                <?php if ($hasPhotos || $hasActivities): ?>
                <div class="flex flex-wrap gap-2 items-center <?= $hasNotes ? 'pt-2 border-t border-slate-200 dark:border-slate-700/50' : '' ?>">
                    <i class="fas fa-paperclip text-slate-400 mr-1 text-[11px]"></i>
                    <?php if ($isLeave):
                        $proofPath = !empty($a['leave']['proof_path']) ? $a['leave']['proof_path'] : (!empty($a['proof_path'] ?? null) ? $a['proof_path'] : '');
                        if (!empty($proofPath)): ?>
                            <a href="<?= assetUrl($proofPath) ?>" target="_blank" class="block w-8 h-8 rounded border border-slate-300 dark:border-slate-600 hover:opacity-80">
                                <img loading="lazy" src="<?= assetUrl($proofPath) ?>" class="w-full h-full object-cover rounded" alt="Bukti">
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if (!empty($a['check_in_photo'])): ?>
                            <a href="<?= assetUrl($a['check_in_photo']) ?>" target="_blank" class="block w-8 h-8 rounded border border-slate-300 dark:border-slate-600 hover:opacity-80" title="Foto Masuk">
                                <img loading="lazy" src="<?= assetUrl($a['check_in_photo']) ?>" class="w-full h-full object-cover rounded" alt="Masuk">
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($a['check_out_photo'])): ?>
                            <a href="<?= assetUrl($a['check_out_photo']) ?>" target="_blank" class="block w-8 h-8 rounded border border-slate-300 dark:border-slate-600 hover:opacity-80" title="Foto Pulang">
                                <img loading="lazy" src="<?= assetUrl($a['check_out_photo']) ?>" class="w-full h-full object-cover rounded" alt="Pulang">
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($hasActivities): ?>
                        <div class="w-px h-6 bg-slate-300 dark:bg-slate-600 mx-1"></div>
                        <?php foreach ($a['activities'] as $actPhoto): ?>
                            <a href="<?= assetUrl($actPhoto) ?>" target="_blank" class="block w-8 h-8 rounded border border-slate-300 dark:border-slate-600 hover:opacity-80" title="Foto Aktivitas">
                                <img loading="lazy" src="<?= assetUrl($actPhoto) ?>" class="w-full h-full object-cover rounded" alt="Aktivitas">
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>
            <?php endif; ?>

        </div>
        <?php
    }
    echo '</div>';
}

function enrichAttendancesWithActivitiesAndLeaves($pdo, &$attendances) {
    if (empty($attendances)) return;

    $attIds = [];
    $leaveQueries = [];
    
    foreach ($attendances as $k => $a) {
        $status = trim($a['status'] ?? '');
        if (empty($status) || !in_array($status, ['Hadir', 'Terlambat', 'Not Checked Out', 'Izin', 'Sakit', 'Cuti', 'Alpha', 'Lembur', 'No Record'])) {
            $attendances[$k]['status'] = 'Alpha';
        }

        if (!isset($a['id']) || ($attendances[$k]['status'] ?? '') === 'No Record') {
            $attendances[$k]['activities'] = [];
            if (isset($a['type'])) {
                $attendances[$k]['leave'] = [
                    'id' => $a['id'] ?? null,
                    'type' => $a['type'],
                    'start_date' => $a['start_date'] ?? null,
                    'end_date' => $a['end_date'] ?? null,
                    'reason' => $a['reason'] ?? null,
                    'proof_path' => $a['proof_path'] ?? null
                ];
            } else {
                $attendances[$k]['leave'] = null;
            }
            continue;
        }
        $attIds[] = $a['id'];
        
        $st = trim($attendances[$k]['status'] ?? '');
        if (in_array($st, ['Izin','Sakit','Cuti']) && isset($a['user_id']) && isset($a['attendance_date'])) {
            $leaveQueries[] = [
                'user_id' => $a['user_id'],
                'date' => $a['attendance_date'],
                'key' => $k
            ];
        }
    }

    $activitiesMap = [];
    if (!empty($attIds) && dbTableExists($pdo, 'attendance_activities')) {
        $inQuery = implode(',', array_fill(0, count($attIds), '?'));
        try {
            $stmtAct = $pdo->prepare("SELECT attendance_id, photo_path FROM attendance_activities WHERE attendance_id IN ($inQuery)");
            $stmtAct->execute($attIds);
            while ($row = $stmtAct->fetch(PDO::FETCH_ASSOC)) {
                $activitiesMap[$row['attendance_id']][] = $row['photo_path'];
            }
        } catch (Throwable $e) {
            error_log('enrichAttendances activities: ' . $e->getMessage());
        }
    }

    $requestMap = [];
    if (!empty($attIds) && dbTableExists($pdo, 'attendance_requests')) {
        $inQueryReq = implode(',', array_fill(0, count($attIds), '?'));
        try {
            $stmtReq = $pdo->prepare("SELECT attendance_id, id, reason, today_plan, gps_lat, gps_lng, gps_accuracy, location_name, request_type, requested_check_out_time, missed_checkout_date FROM attendance_requests WHERE status = 'approved' AND attendance_id IN ($inQueryReq)");
            $stmtReq->execute($attIds);
            while ($row = $stmtReq->fetch(PDO::FETCH_ASSOC)) {
                $requestMap[$row['attendance_id']] = $row;
            }
        } catch (Throwable $e) {
            error_log('enrichAttendances requests: ' . $e->getMessage());
        }
    }

    foreach ($attendances as $k => &$a) {
        if (isset($a['id'])) {
            $a['activities'] = $activitiesMap[$a['id']] ?? [];
            $a['attendance_request'] = $requestMap[$a['id']] ?? null;
        }
        if (!isset($a['leave'])) {
            $a['leave'] = null;
        }
    }
    unset($a);

    if (!empty($leaveQueries) && dbTableExists($pdo, 'leave_requests')) {
        $userIds = array_values(array_unique(array_column($leaveQueries, 'user_id')));
        $inUsers = implode(',', array_fill(0, count($userIds), '?'));
        
        $dates = array_column($leaveQueries, 'date');
        $minDate = min($dates);
        $maxDate = max($dates);
        
        $params = $userIds;
        $params[] = $maxDate;
        $params[] = $minDate;
        
        try {
        $stmtLeave = $pdo->prepare("
            SELECT id, user_id, type, start_date, end_date, reason, proof_path
            FROM leave_requests
            WHERE user_id IN ($inUsers) AND status = 'approved'
            AND start_date <= ? AND end_date >= ?
            ORDER BY created_at DESC
        ");
        $stmtLeave->execute($params);
        $allLeaves = $stmtLeave->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $allLeaves = [];
            error_log('enrichAttendances leave query: ' . $e->getMessage());
        }
        
        foreach ($leaveQueries as $q) {
            $k = $q['key'];
            $uid = $q['user_id'];
            $date = $q['date'];
            
            foreach ($allLeaves as $l) {
                if ($l['user_id'] == $uid && $l['start_date'] <= $date && $l['end_date'] >= $date) {
                    $attendances[$k]['leave'] = [
                        'id' => $l['id'],
                        'type' => $l['type'],
                        'start_date' => $l['start_date'],
                        'end_date' => $l['end_date'],
                        'reason' => $l['reason'],
                        'proof_path' => $l['proof_path']
                    ];
                    break;
                }
            }
        }
    }
}

if (!isset($user) || !is_array($user)) {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0 && isset($pdo)) {
        $stmtUser = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmtUser->execute([$uid]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: [];
    } else {
        $user = [];
    }
}

$rawRoleStr = strtolower(trim($user['role'] ?? ($_SESSION['role'] ?? '')));
$roleStr = resolveAbsenListRole($rawRoleStr);
$isAdministrator = $roleStr && preg_match('/admin|administrator/', $roleStr);
$isTechnicianManager = $roleStr === 'technician_manager';
$isTechnician = $roleStr === 'technician';
$isDirektur = $roleStr === 'direktur';
$isAdmin = $isAdministrator || $isTechnicianManager || $isDirektur;
$canEditAttendance = $isAdministrator;


$currentUserId = (int)($user['id'] ?? ($_SESSION['user_id'] ?? 0));

$myLeaves = [];
if (!$isAdmin) {
  try {
    $stmt = $pdo->prepare("
      SELECT a.*, u.full_name, u.role, u.avatar
      FROM attendances a
      JOIN users u ON a.user_id = u.id
      WHERE a.user_id = ? AND a.status IN ('Izin','Sakit','Cuti','Alpha')
      ORDER BY a.attendance_date DESC
    ");
    $stmt->execute([$currentUserId]);
    $myLeaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Exception $e) {
    $myLeaves = [];
  }
}
$search = trim($_GET['search'] ?? '');
$selectedDate = isset($_GET['date']) && $_GET['date'] ? $_GET['date'] : date('Y-m-d');
$nightStartConf = normalizeTimeValue(getCompanySettingValue($pdo, 'night_shift_start_time', '18:00:00'), '18:00:00');
$nightEndConf = normalizeTimeValue(getCompanySettingValue($pdo, 'night_shift_end_time', '06:00:00'), '06:00:00');
$viewDetail = isset($_GET['view']) && $_GET['view'] === 'detail';
$viewHistory = isset($_GET['view']) && $_GET['view'] === 'history';
$year = isset($_GET['year']) ? max(1970, (int)$_GET['year']) : (int)date('Y');
$monthParam = trim($_GET['month'] ?? '');
$adminMonthStart = $monthParam ? $monthParam.'-01' : date('Y-m-01');
$adminMonthEnd = date('Y-m-t', strtotime($adminMonthStart));
$monthCounts = []; 
$dayCounts = []; 
$suppress_list = false; 
$searching = false; 
$attendances = []; 

if ($search !== '') {
    try {
        $stmt = $pdo->prepare("
          SELECT a.*, u.full_name, u.role, u.avatar
          FROM attendances a
          JOIN users u ON a.user_id = u.id
          WHERE u.full_name LIKE ?
            AND (
              ? = 1
              OR (? = 1 AND u.role IN ('technician','technician_manager','staff'))
              OR a.user_id = ?
            )
          ORDER BY a.attendance_date DESC, a.check_in_time ASC
        ");
        $stmt->execute(['%' . $search . '%', $isAdministrator ? 1 : 0, $isTechnicianManager ? 1 : 0, $currentUserId]);
        $attendances = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($attendances as &$a) {
            $status = trim($a['status'] ?? '');
            if (empty($status) || !in_array($status, ['Hadir', 'Terlambat', 'Not Checked Out', 'Izin', 'Sakit', 'Cuti', 'Alpha', 'Lembur'])) {
                $a['status'] = 'Alpha';
            }
        }
        unset($a);

        $searching = true;
    } catch (Exception $e) {
        $attendances = [];
        $searching = true;
        error_log("Search query error: " . $e->getMessage());
    }
} elseif (isset($_GET['date'])) {
    
    try {
        $selDateTs = strtotime($selectedDate);
        $todayTs = strtotime(date('Y-m-d'));
        
        if ($selDateTs < $todayTs) {
            $alphaSessionKey = '_alpha_absentlist_' . $selectedDate;
            if (empty($_SESSION[$alphaSessionKey])) {
                
                $stmtUsers = $pdo->prepare("SELECT id FROM users WHERE role NOT IN ('administrator','direktur') AND is_active = 1");
                $stmtUsers->execute();
                $allUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
                foreach ($allUsers as $u) {
                    
                    $chk = $pdo->prepare('SELECT COUNT(*) FROM attendances WHERE user_id = ? AND attendance_date = ?');
                    $chk->execute([$u['id'], $selectedDate]);
                    if ((int)$chk->fetchColumn() === 0) {
                        
                        $lrChk = $pdo->prepare('SELECT id, type, reason, proof_path FROM leave_requests WHERE user_id = ? AND ? BETWEEN start_date AND end_date AND status = "approved" LIMIT 1');
                        $lrChk->execute([$u['id'], $selectedDate]);
                        $approvedLeave = $lrChk->fetch(PDO::FETCH_ASSOC);
                        if ($approvedLeave) {
                            $status = $approvedLeave['type'] === 'permission' ? 'Izin' : 'Sakit';
                            $notes = $status === 'Sakit' ? ($approvedLeave['reason'] ?: 'Sakit') : ($approvedLeave['reason'] ?: 'Izin');
                            $ins = $pdo->prepare('INSERT INTO attendances (user_id, attendance_date, today_plan, check_in_time, check_out_time, check_in_photo, check_out_photo, check_out_location, notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                            $ins->execute([$u['id'], $selectedDate, '', '0000-00-00 00:00:00', null, $approvedLeave['proof_path'] ?: '', null, '', $notes, $status]);
                        } else {
                            
                            $selDayOfWeek = (int)date('w', $selDateTs);
                            $isWeekend = ($selDayOfWeek == 0 || $selDayOfWeek == 6);
                            $alphaNote = $isWeekend ? 'Libur (Weekend)' : 'Tidak hadir tanpa keterangan';
                            $ins = $pdo->prepare('INSERT INTO attendances (user_id, attendance_date, today_plan, check_in_time, check_out_time, check_in_photo, check_out_photo, check_out_location, notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                            $ins->execute([$u['id'], $selectedDate, '', '0000-00-00 00:00:00', null, '', null, '', $alphaNote, 'Alpha']);
                        }
                    }
                }
                $_SESSION[$alphaSessionKey] = true;
            }
        }
    } catch (Exception $e) {
        error_log('absent-list autoCreateAlpha error: ' . $e->getMessage());
    }

    try {
        $stmt = $pdo->prepare("
          SELECT a.*, u.full_name, u.role, u.avatar
          FROM attendances a
          JOIN users u ON a.user_id = u.id
          WHERE a.attendance_date = ?
            AND (
              ? = 1
              OR (? = 1 AND u.role IN ('technician','technician_manager','staff'))
              OR a.user_id = ?
            )
          ORDER BY a.check_in_time ASC
        ");
        $stmt->execute([$selectedDate, $isAdministrator ? 1 : 0, $isTechnicianManager ? 1 : 0, $currentUserId]);
        $attendances = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $leaveRequests = fetchApprovedLeavesForDate($pdo, $selectedDate);
        $attendances = array_merge($attendances, $leaveRequests);
        $searching = true;
    } catch (Exception $e) {
        $attendances = [];
        $searching = true;
        error_log("Date-specific query error: " . $e->getMessage());
    }
} else {
    if (!$isAdmin) {
        try {
        $stmt = $pdo->prepare("
          SELECT a.*, u.full_name, u.role, u.avatar
          FROM attendances a
          JOIN users u ON a.user_id = u.id
          WHERE a.attendance_date = ?
            AND (
              ? = 1
              OR (? = 1 AND u.role IN ('technician','technician_manager','staff'))
              OR a.user_id = ?
            )
          ORDER BY a.check_in_time ASC
        ");
        $stmt->execute([$selectedDate, $isAdministrator ? 1 : 0, $isTechnicianManager ? 1 : 0, $currentUserId]);
        $attendances = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $leaveRequests = fetchApprovedLeavesForDate($pdo, $selectedDate);
        $attendances = array_merge($attendances, $leaveRequests);
        $searching = true;
        } catch (Exception $e) {
            $attendances = [];
            $searching = true;
            error_log('absent-list default query error: ' . $e->getMessage());
        }
    }
}
enrichAttendancesWithActivitiesAndLeaves($pdo, $attendances);

if ($isAdmin && $search === '') {
  try {
    if ($monthParam === '') {
      $stmtMC = $pdo->prepare("SELECT DATE_FORMAT(attendance_date, '%Y-%m') ym, COUNT(*) cnt FROM attendances WHERE YEAR(attendance_date)=? AND status != 'Alpha' GROUP BY ym");
      $stmtMC->execute([$year]);
      while ($r = $stmtMC->fetch(PDO::FETCH_ASSOC)) { $monthCounts[$r['ym']] = (int)$r['cnt']; }
    } else {
        if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) { $monthParam = date('Y-m'); }
        
        
        $leaveUnionSql = dbTableExists($pdo, 'leave_requests')
          ? "  UNION ALL\n  SELECT start_date as attendance_date, COUNT(DISTINCT user_id) as cnt FROM leave_requests WHERE status = 'approved' AND start_date BETWEEN ? AND ? GROUP BY start_date\n"
          : '';
        $stmtDC = $pdo->prepare(
          "SELECT d.attendance_date, SUM(d.cnt) as cnt FROM (\n" .
          "  SELECT attendance_date, COUNT(DISTINCT user_id) as cnt FROM attendances WHERE attendance_date BETWEEN ? AND ? AND status != 'Alpha' GROUP BY attendance_date\n" .
          $leaveUnionSql .
          ") d GROUP BY d.attendance_date ORDER BY d.attendance_date"
        );
        if ($leaveUnionSql !== '') {
            $stmtDC->execute([$adminMonthStart, $adminMonthEnd, $adminMonthStart, $adminMonthEnd]);
        } else {
            $stmtDC->execute([$adminMonthStart, $adminMonthEnd]);
        }
        while ($r = $stmtDC->fetch(PDO::FETCH_ASSOC)) { $dayCounts[$r['attendance_date']] = (int)$r['cnt']; }
    }
  } catch (Exception $e) {
    error_log("Aggregation query error: " . $e->getMessage());
  }
  
  if (isset($_GET['date']) || $search !== '') {
    $suppress_list = false; 
  } else {
    $suppress_list = true;
  }
} else {
  $suppress_list = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_attendance_modal'])) {
    if (!$canEditAttendance) {
        http_response_code(403);
        echo "Unauthorized";
        exit;
    }
    
    $id = intval($_POST['id']);
    $today_plan = trim($_POST['today_plan'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $checkIn = trim($_POST['check_in_time'] ?? '');
    $checkOut = trim($_POST['check_out_time'] ?? '');

    
    $stmtDate = $pdo->prepare("SELECT attendance_date FROM attendances WHERE id = ?");
    $stmtDate->execute([$id]);
    $attRow = $stmtDate->fetch(PDO::FETCH_ASSOC);
    if (!$attRow) { echo "Not found"; exit; }
    $attDate = $attRow['attendance_date'];

    $ciVal = ($checkIn !== '') ? $attDate . ' ' . $checkIn . ':00' : null;
    $coVal = ($checkOut !== '') ? $attDate . ' ' . $checkOut . ':00' : null;

    $pdo->prepare("UPDATE attendances SET today_plan=?, notes=?, status=?, check_in_time=?, check_out_time=? WHERE id=?")
        ->execute([$today_plan, $notes, $status, $ciVal, $coVal, $id]);
    echo "OK";
    exit;
}
?>

<link rel="stylesheet" href="./src/output.css">




<?php if (false) : ?>
<div class="flex justify-center py-20 px-4">
    <div class="max-w-2xl w-full">
      <div class="p-8 rounded-3xl bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 border-2 border-amber-300 dark:border-amber-700 shadow-2xl">
        <div class="flex items-start gap-6">
          <div class="flex-shrink-0">
            <svg class="w-16 h-16 text-amber-600 dark:text-amber-400" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
            </svg>
          </div>
          <div>
            <h3 class="text-2xl font-bold text-amber-900 dark:text-amber-200 mb-4">
              Sedang dalam Pengujian
            </h3>
            <p class="text-lg text-amber-800 dark:text-amber-300 leading-relaxed">
              Jika terdapat masalah dalam absensi mohon hubungi Office.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php else : ?>
<h1 class="font-bold mb-4 text-base sm:text-lg dark:text-white">Daftar Absen Staff</h1>
<form method="get" class="mb-4 flex flex-col sm:flex-row gap-2 sm:items-center">
  <?php if ($isAdmin): ?>
    <input type="hidden" name="page" value="absen-list">
    <input type="text" name="search" placeholder="Cari nama staff..." value="<?= htmlspecialchars($search) ?>" class="border dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder:text-slate-400 rounded px-2 py-1 text-sm w-full sm:w-auto" />
    <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>" class="border dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded px-2 py-1 text-sm w-full sm:w-auto" />
  <?php endif; ?>
  
  <?php if ($isAdmin): ?>
    <?php 
      $exportMonth = '';
      if (!empty($monthParam)) { $exportMonth = $monthParam; }
      elseif (isset($_GET['date'])) { $exportMonth = date('Y-m', strtotime($selectedDate)); }
      else { $exportMonth = date('Y-m'); }
    ?>
    <a href="/app/action/export-absen-monthly.php?month=<?= urlencode($exportMonth) ?>"
     class="px-3 py-1 bg-green-600 dark:bg-green-700 text-white rounded hover:bg-green-700 dark:hover:bg-green-600 transition text-sm w-full sm:w-auto text-center">Download Excel Bulan <?= htmlspecialchars(date('M Y', strtotime($exportMonth . '-01'))) ?></a>
    <a href="/app/action/export-summary-presence.php?month=<?= urlencode($exportMonth) ?>"
     class="px-3 py-1 bg-blue-600 dark:bg-blue-700 text-white rounded hover:bg-blue-700 dark:hover:bg-blue-600 transition text-sm w-full sm:w-auto text-center">Summary Presence <?= htmlspecialchars(date('M Y', strtotime($exportMonth . '-01'))) ?></a>
    <?php if (($isAdministrator ?? false) || ($isDirektur ?? false)): ?>
    <button type="button" onclick="openSummarySettings()" class="px-3 py-1 bg-gray-600 dark:bg-gray-700 text-white rounded hover:bg-gray-700 dark:hover:bg-gray-600 transition text-sm w-full sm:w-auto text-center"><i class="fas fa-cog mr-1"></i>Setting Summary</button>
    <?php endif; ?>
  <?php endif; ?>
</form>

<?php if (($isAdministrator ?? false) || ($isDirektur ?? false)): ?>
<!-- Summary Presence Settings Modal -->
<div id="summarySettingsModal" class="hidden fixed inset-0 z-[80] bg-black/50 items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto mx-auto">
    <div class="flex justify-between items-center mb-4">
      <h3 class="font-bold text-lg text-gray-900 dark:text-white">Setting Summary Presence</h3>
      <button onclick="closeSummarySettings()" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 text-2xl">&times;</button>
    </div>
    <div id="summarySettingsLoading" class="text-center py-8 text-gray-500">Memuat...</div>
    <div id="summarySettingsContent" class="hidden space-y-5">
      <div>
        <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">Hari Kerja</label>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Pilih hari mana saja yang dihitung sebagai hari kerja</p>
        <div class="flex flex-wrap gap-2" id="workDaysCheckboxes">
          <label class="inline-flex items-center gap-1.5 px-3 py-1.5 border rounded-lg cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/20 transition"><input type="checkbox" value="1" class="work-day-cb rounded"> <span class="text-sm">Senin</span></label>
          <label class="inline-flex items-center gap-1.5 px-3 py-1.5 border rounded-lg cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/20 transition"><input type="checkbox" value="2" class="work-day-cb rounded"> <span class="text-sm">Selasa</span></label>
          <label class="inline-flex items-center gap-1.5 px-3 py-1.5 border rounded-lg cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/20 transition"><input type="checkbox" value="3" class="work-day-cb rounded"> <span class="text-sm">Rabu</span></label>
          <label class="inline-flex items-center gap-1.5 px-3 py-1.5 border rounded-lg cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/20 transition"><input type="checkbox" value="4" class="work-day-cb rounded"> <span class="text-sm">Kamis</span></label>
          <label class="inline-flex items-center gap-1.5 px-3 py-1.5 border rounded-lg cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/20 transition"><input type="checkbox" value="5" class="work-day-cb rounded"> <span class="text-sm">Jumat</span></label>
          <label class="inline-flex items-center gap-1.5 px-3 py-1.5 border rounded-lg cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/20 transition"><input type="checkbox" value="6" class="work-day-cb rounded"> <span class="text-sm">Sabtu</span></label>
          <label class="inline-flex items-center gap-1.5 px-3 py-1.5 border rounded-lg cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/20 transition"><input type="checkbox" value="7" class="work-day-cb rounded"> <span class="text-sm">Minggu</span></label>
        </div>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">Durasi Istirahat (menit)</label>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Jam istirahat yang dipotong dari total jam kerja per hari</p>
        <input type="number" id="breakMinutes" min="0" max="180" value="60" class="w-32 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded-lg p-2 text-sm">
        <span class="text-sm text-gray-500 ml-2">menit/hari</span>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">Karyawan yang Tidak Masuk Summary</label>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Pilih karyawan yang tidak akan ditampilkan di export Summary Presence</p>
        <div id="excludedUsersContainer" class="max-h-40 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg p-2 space-y-1"></div>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">Karyawan Auto Hadir (Selalu Present)</label>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Karyawan ini otomatis dihitung hadir setiap hari kerja tanpa perlu absen</p>
        <div id="autoPresentUsersContainer" class="max-h-40 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg p-2 space-y-1"></div>
      </div>
      <div class="flex justify-end gap-3 pt-3 border-t border-gray-200 dark:border-gray-700">
        <button type="button" onclick="closeSummarySettings()" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">Batal</button>
        <button type="button" onclick="saveSummarySettings()" id="saveSummaryBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Simpan</button>
      </div>
    </div>
  </div>
</div>
<script>
let summarySettingsData = null;
function openSummarySettings() {
  const modal = document.getElementById('summarySettingsModal');
  modal.classList.remove('hidden'); modal.classList.add('flex');
  document.getElementById('summarySettingsLoading').classList.remove('hidden');
  document.getElementById('summarySettingsContent').classList.add('hidden');
  fetch('/app/action/summary-presence-settings.php')
    .then(r => r.json())
    .then(data => {
      if (!data.success) { alert(data.message || 'Gagal memuat settings'); return; }
      summarySettingsData = data; renderSummarySettings(data);
      document.getElementById('summarySettingsLoading').classList.add('hidden');
      document.getElementById('summarySettingsContent').classList.remove('hidden');
    }).catch(err => { alert('Error: ' + err); });
}
function closeSummarySettings() {
  const modal = document.getElementById('summarySettingsModal');
  modal.classList.add('hidden'); modal.classList.remove('flex');
}
function renderSummarySettings(data) {
  const s = data.settings;
  document.querySelectorAll('.work-day-cb').forEach(cb => { cb.checked = s.work_days.includes(parseInt(cb.value)); });
  document.getElementById('breakMinutes').value = s.break_minutes;
  const excludedContainer = document.getElementById('excludedUsersContainer');
  excludedContainer.innerHTML = data.users.map(u => {
    const checked = s.excluded_users.includes(u.id) ? 'checked' : '';
    return '<label class="flex items-center gap-2 px-2 py-1 hover:bg-gray-50 dark:hover:bg-gray-800 rounded cursor-pointer"><input type="checkbox" class="excluded-user-cb rounded" value="'+u.id+'" '+checked+'><span class="text-sm text-gray-700 dark:text-gray-300">'+escHtml(u.full_name||u.username)+' <span class="text-xs text-gray-400">('+u.role+')</span></span></label>';
  }).join('');
  const autoContainer = document.getElementById('autoPresentUsersContainer');
  autoContainer.innerHTML = data.users.map(u => {
    const checked = s.auto_present_users.includes(u.id) ? 'checked' : '';
    return '<label class="flex items-center gap-2 px-2 py-1 hover:bg-gray-50 dark:hover:bg-gray-800 rounded cursor-pointer"><input type="checkbox" class="auto-present-cb rounded" value="'+u.id+'" '+checked+'><span class="text-sm text-gray-700 dark:text-gray-300">'+escHtml(u.full_name||u.username)+' <span class="text-xs text-gray-400">('+u.role+')</span></span></label>';
  }).join('');
}
function saveSummarySettings() {
  const btn = document.getElementById('saveSummaryBtn');
  btn.disabled = true; btn.textContent = 'Menyimpan...';
  const workDays = []; document.querySelectorAll('.work-day-cb:checked').forEach(cb => workDays.push(parseInt(cb.value)));
  const breakMinutes = parseInt(document.getElementById('breakMinutes').value) || 60;
  const excludedUsers = []; document.querySelectorAll('.excluded-user-cb:checked').forEach(cb => excludedUsers.push(parseInt(cb.value)));
  const autoPresentUsers = []; document.querySelectorAll('.auto-present-cb:checked').forEach(cb => autoPresentUsers.push(parseInt(cb.value)));
  fetch('/app/action/summary-presence-settings.php', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ work_days: workDays, break_minutes: breakMinutes, excluded_users: excludedUsers, auto_present_users: autoPresentUsers })
  }).then(r => r.json()).then(data => {
    if (data.success) { alert('Settings berhasil disimpan!'); closeSummarySettings(); }
    else { alert(data.message || 'Gagal menyimpan'); }
  }).catch(err => alert('Error: ' + err))
  .finally(() => { btn.disabled = false; btn.textContent = 'Simpan'; });
}
function escHtml(str) { const d = document.createElement('div'); d.textContent = str||''; return d.innerHTML; }
</script>
<?php endif; ?>

<?php if ($searching && !$isAdmin): ?>
  <div class="mb-2 text-sm text-indigo-700 dark:text-indigo-400">Menampilkan seluruh absen untuk nama: <b><?= htmlspecialchars($search) ?></b></div>
<?php elseif (!$isAdmin): ?>
  <div class="mb-2 text-sm text-indigo-700 dark:text-indigo-400">Menampilkan absen untuk tanggal <b><?= htmlspecialchars(date('d M Y', strtotime($selectedDate))) ?></b></div>
<?php elseif ($isAdmin && isset($_GET['date'])): ?>
  <div class="mb-2 text-sm text-indigo-700 dark:text-indigo-400">
    Menampilkan semua staff untuk tanggal <b><?= htmlspecialchars(date('d M Y', strtotime($selectedDate))) ?></b>
    <span class="text-gray-500 dark:text-slate-400">(<?= count($attendances) ?> records)</span>
  </div>
<?php elseif ($isAdmin && $search): ?>
  <div class="mb-2 text-sm text-indigo-700 dark:text-indigo-400">Menampilkan hasil pencarian untuk: <b><?= htmlspecialchars($search) ?></b></div>
<?php endif; ?>

<?php
$cal_year = isset($_GET['cal_year']) ? max(1970, (int)$_GET['cal_year']) : (int)date('Y');
$cal_month = isset($_GET['cal_month']) ? max(1, min(12, (int)$_GET['cal_month'])) : (int)date('n');
$days_in_month = daysInMonth($cal_year, $cal_month);

$currentUserId = (int)($user['id'] ?? 0);
$stmtCal = $pdo->prepare("SELECT attendance_date, status FROM attendances WHERE user_id = ? AND YEAR(attendance_date) = ? AND MONTH(attendance_date) = ?");
$stmtCal->execute([$currentUserId, $cal_year, $cal_month]);
$absentData = [];
while ($row = $stmtCal->fetch(PDO::FETCH_ASSOC)) {
    $absentData[$row['attendance_date']] = strtolower(trim($row['status'] ?? ''));
}
$technicianList = [];
if (!$isAdmin) {
  $stmtTech = $pdo->prepare("
    SELECT u.id, u.full_name, u.role, u.avatar
    FROM users u
    WHERE u.is_active = 1
      AND (
        u.role IN ('technician', 'technician_manager', 'staff')
        OR u.role LIKE '%technician%'
        OR u.role LIKE '%teknisi%'
      )
    ORDER BY u.full_name ASC
  ");
  $stmtTech->execute();
  $technicianList = $stmtTech->fetchAll(PDO::FETCH_ASSOC);
}

/** Non-admin: tampilan utama sudah di kalender + absensi hari ini + riwayat — hindari blok daftar ganda. */
$showLegacyAttendanceCards = $isAdmin && ($search !== '' || isset($_GET['date']));

$myFocusDate = date('Y-m-d');
$myAttendanceRecords = [];
if (!$isAdmin) {
    if (isset($_GET['my_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['my_date'])) {
        $myFocusDate = (string)$_GET['my_date'];
    }
    $myAttendanceRecords = fetchMyAttendanceForList($pdo, $currentUserId, $myFocusDate);
    $myAttendanceRecords = array_merge($myAttendanceRecords, fetchApprovedLeavesForDate($pdo, $myFocusDate, $currentUserId));
    enrichAttendancesWithActivitiesAndLeaves($pdo, $myAttendanceRecords);
}
?>

<style>
.absensi-cal { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:12px; margin-bottom:12px; }
.absensi-cal table { width: 100%; table-layout: fixed; border-collapse: collapse; }
.absensi-cal th, .absensi-cal td { text-align:center; padding:6px; }
.absensi-cal thead th { font-size:12px; color:#64748b; }
.absensi-cal .day { border-radius:10px; min-height:44px; display:flex; align-items:center; justify-content:center; font-weight:600; }
.absensi-status-present { background:#d1fae5; color:#065f46; }
.absensi-status-leave { background:#fef9c3; color:#a16207; }
.absensi-status-alpha { background:#f1f5f9; color:#64748b; }
.absensi-status-empty { background:transparent; color:#94a3b8; }
.absensi-cal .cal-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
.absensi-cal .cal-head .title { font-weight:700; color:#1f2937; }
.absensi-cal select, .absensi-cal button { border:1px solid #e5e7eb; border-radius:8px; padding:4px 8px; font-size:12px; }
.dark .absensi-cal { background:#1e293b; border-color:#475569; }
.dark .absensi-cal thead th { color:#94a3b8; }
.dark .absensi-cal .cal-head .title { color:#f1f5f9; }
.dark .absensi-cal select, .dark .absensi-cal button { background:#334155; border-color:#475569; color:#f1f5f9; }
.dark .absensi-status-present { background:#065f46; color:#d1fae5; }
.dark .absensi-status-leave { background:#a16207; color:#fef9c3; }
.dark .absensi-status-alpha { background:#475569; color:#cbd5e1; }
.dark .absensi-status-empty { color:#64748b; }
.absensi-cal-table-wrap { overflow-x:auto; }
.absensi-cal table { min-width: 360px; }
@media (max-width: 640px) {
  .absensi-cal { padding:10px; }
  .absensi-cal thead th { font-size:11px; }
  .absensi-cal th, .absensi-cal td { padding:4px; }
  .absensi-cal .day { min-height:34px; border-radius:8px; font-size:12px; }
  .absensi-cal .cal-head { gap:8px; flex-direction:column; align-items:flex-start; }
  .absensi-cal .cal-head .title { font-size:14px; }
  .absensi-cal select, .absensi-cal button { font-size:11px; padding:3px 6px; }
}

html[data-theme="dark"] #editModal > div[style*="background:#ffffff"] {
    background: #1e293b !important;
    color: #e2e8f0 !important;
}
html[data-theme="dark"] #editModal form[style*="background:#ffffff"] {
    background: #1e293b !important;
}
html[data-theme="dark"] #editModal input,
html[data-theme="dark"] #editModal select,
html[data-theme="dark"] #editModal textarea {
    background: #0f172a !important;
    color: #f8fafc !important;
    border-color: #334155 !important;
}
html[data-theme="dark"] #editModal label[style*="color:#4b5563"] {
    color: #cbd5e1 !important;
}
html[data-theme="dark"] #editModal span[style*="color:#111827"],
html[data-theme="dark"] #editModal #editUserName {
    color: #f1f5f9 !important;
}
html[data-theme="dark"] #editModal #editUserDate {
    color: #94a3b8 !important;
}
html[data-theme="dark"] #editModal button[style*="background:#f3f4f6"] {
    background: #334155 !important;
    color: #e2e8f0 !important;
}
</style>

<?php if (!$isAdmin): ?>
<div class="absensi-cal">
  <div class="cal-head">
    <div class="title">Kalender Absensi Saya</div>
    <form method="get" class="flex items-center gap-2">
      <input type="hidden" name="page" value="absen-list" />
      <select name="cal_month">
        <?php for ($m=1; $m<=12; $m++): $sel = ($m===$cal_month)?'selected':''; ?>
          <option value="<?= $m ?>" <?= $sel ?>><?= date('F', strtotime("$cal_year-$m-01")) ?></option>
        <?php endfor; ?>
      </select>
      <select name="cal_year">
        <?php $yn = (int)date('Y'); for ($y=$yn-3; $y<=$yn+1; $y++): $sel=($y===$cal_year)?'selected':''; ?>
          <option value="<?= $y ?>" <?= $sel ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
      <button type="submit">Tampilkan</button>
    </form>
  </div>
  <div class="absensi-cal-table-wrap">
  <table>
    <thead>
      <tr>
        <th>Min</th><th>Sen</th><th>Sel</th><th>Rab</th><th>Kam</th><th>Jum</th><th>Sab</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $firstWday = (int)date('w', strtotime("$cal_year-$cal_month-01"));
      $day = 1;
      echo '<tr>';
      for ($i=0; $i<$firstWday; $i++) { echo '<td></td>'; }
      $wday = $firstWday;
      while ($day <= $days_in_month) {
          $dateStr = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $day);
          $status = $absentData[$dateStr] ?? '';
          $cls = mapStatusClass($status);
          $isSelected = (!$isAdmin && isset($myFocusDate) && $dateStr === $myFocusDate);
          $ring = $isSelected ? ' ring-2 ring-indigo-500' : '';
          if ($status !== '') {
              echo '<td><a href="?page=absen-list&amp;cal_year='.$cal_year.'&amp;cal_month='.$cal_month.'&amp;my_date='.$dateStr.'" class="day '.$cls.$ring.' no-underline block" title="Lihat absensi '.$dateStr.'">'.$day.'</a></td>';
          } else {
              echo '<td><a href="?page=absen-list&amp;cal_year='.$cal_year.'&amp;cal_month='.$cal_month.'&amp;my_date='.$dateStr.'" class="day absensi-status-empty'.$ring.' no-underline block" title="Lihat tanggal '.$dateStr.'">'.$day.'</a></td>';
          }
          $day++; $wday++;
          if ($wday === 7 && $day <= $days_in_month) { echo '</tr><tr>'; $wday = 0; }
      }
      if ($wday > 0 && $wday < 7) { for ($i=$wday; $i<7; $i++) echo '<td></td>'; }
      echo '</tr>';
      ?>
    </tbody>
  </table>
  </div>
  <div class="mt-2 text-slate-600 dark:text-slate-400" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;font-size:12px;">
    <div style="display:flex;align-items:center;gap:6px;">
      <span class="absensi-status-present" style="display:inline-block;width:14px;height:14px;border-radius:4px;"></span>
      Hadir (Tepat Waktu/Terlambat)
    </div>
    <div style="display:flex;align-items:center;gap:6px;">
      <span class="absensi-status-leave" style="display:inline-block;width:14px;height:14px;border-radius:4px;"></span>
      Izin/Sakit/Cuti/Not Checked Out
    </div>
    <div style="display:flex;align-items:center;gap:6px;">
      <span class="absensi-status-alpha" style="display:inline-block;width:14px;height:14px;border-radius:4px;"></span>
      Alpha
    </div>
    <div style="display:flex;align-items:center;gap:6px;">
      <span class="absensi-status-empty" style="display:inline-block;width:14px;height:14px;border-radius:4px;border:1px dashed #cbd5e1;"></span>
      Kosong
    </div>
  </div>
</div>

<div class="mt-6 bg-white dark:bg-slate-800 border border-indigo-200 dark:border-indigo-800 rounded-xl p-4 sm:p-6 shadow-sm">
  <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
    <div>
      <div class="font-semibold text-slate-800 dark:text-white flex items-center gap-2">
        <i class="fas fa-user-check text-indigo-500"></i>
        Absensi Saya
      </div>
      <div class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
        <?= htmlspecialchars(date('l, d F Y', strtotime($myFocusDate))) ?>
        <?php if ($myFocusDate !== date('Y-m-d')): ?>
          <a href="?page=absen-list&amp;cal_year=<?= (int)$cal_year ?>&amp;cal_month=<?= (int)$cal_month ?>" class="ml-2 text-indigo-600 dark:text-indigo-400 hover:underline text-xs">← Kembali ke hari ini</a>
        <?php endif; ?>
      </div>
    </div>
    <a href="?page=absence" class="text-xs sm:text-sm px-3 py-1.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition">Buka halaman Absen</a>
  </div>
  <?php
    $myEmptyMsg = ($myFocusDate === date('Y-m-d'))
      ? 'Anda belum absen untuk tanggal ini. Silakan lakukan absen di menu Absen.'
      : 'Tidak ada data absensi pada tanggal ini.';
    renderAttendanceCardsList($myAttendanceRecords, $myEmptyMsg);
  ?>
</div>
<?php endif; ?>

<?php if (!$viewDetail && !$viewHistory && !$isAdmin): ?>
<div class="mt-6 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-6 shadow-sm">
  <div class="flex items-center justify-between mb-4">
    <div class="font-semibold text-slate-800 dark:text-white">Ringkasan Absensi Saya</div>
    <div class="text-sm text-slate-500 dark:text-slate-400">Total Absensi: 
      <?php
      $stmtCount = $pdo->prepare("SELECT COUNT(*) as total FROM attendances WHERE user_id = ? AND status != 'Alpha'");
      $stmtCount->execute([$currentUserId]);
      $totalAbsensi = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
      echo $totalAbsensi;
      ?>
    </div>
  </div>

  <div class="text-center mb-4">
    <a href="?page=absen-list&view=history" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
      </svg>
      Lihat Riwayat Lengkap
    </a>
  </div>
</div>
<?php endif; ?>

<?php if (!$viewDetail && !$viewHistory && !$isAdmin): ?>
<div class="mt-6">
  <div class="flex items-center justify-between mb-4">
    <div class="font-semibold text-slate-800 dark:text-white">Absensi Staff Hari Ini</div>
    <div class="text-sm text-slate-500 dark:text-slate-400"><?= date('l, d F Y') ?></div>
  </div>

  <?php
  $today = date('Y-m-d');
  
  $stmtToday = $pdo->prepare("
    SELECT a.*, u.full_name, u.role, u.avatar
    FROM attendances a
    JOIN users u ON a.user_id = u.id
    WHERE (
        a.attendance_date = ?
        OR (
          a.check_in_time IS NOT NULL
          AND a.check_in_time > '1970-01-01 00:00:00'
          AND DATE(a.check_in_time) = ?
        )
      )
      AND a.status NOT IN ('Alpha')
      AND a.check_in_time IS NOT NULL
      AND a.check_in_time > '1970-01-01 00:00:00'
    ORDER BY a.check_in_time ASC
  ");
  $todayAttendances = [];
  try {
    $stmtToday->execute([$today, $today]);
    $todayAttendances = $stmtToday->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    error_log('absent-list today attendances: ' . $e->getMessage());
  }
  $todayAttendances = array_merge($todayAttendances, fetchApprovedLeavesForDate($pdo, $today));

enrichAttendancesWithActivitiesAndLeaves($pdo, $todayAttendances);
  ?>

  <?php
    
    $dayAttendances = [];
    $nightAttendances = [];
    foreach ($todayAttendances as $a) {
        if (!empty($a['is_cross_midnight'])) {
            $nightAttendances[] = $a;
        } else {
            $dayAttendances[] = $a;
        }
    }

    
    $groupedDay = [];
    foreach ($dayAttendances as $a) {
        $groupedDay[$a['role'] ?? 'staff'][] = $a;
    }
    ksort($groupedDay);

    $groupedNight = [];
    foreach ($nightAttendances as $a) {
        $groupedNight[$a['role'] ?? 'staff'][] = $a;
    }
    ksort($groupedNight);
  ?>
  <?php
  
  function renderShiftCards($grouped, $label, $icon, $borderCls) {
    if (empty($grouped)) return;
    $total = array_sum(array_map('count', $grouped));
    ?>
    <div class="mb-6">
      <div class="flex items-center gap-2 mb-3 px-1">
        <i class="<?= $icon ?> text-lg"></i>
        <span class="font-semibold text-slate-800 dark:text-white text-sm"><?= $label ?></span>
        <span class="text-xs bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300 px-2 py-0.5 rounded-full"><?= $total ?> Staff</span>
      </div>
      <div class="space-y-6 border-l-4 <?= $borderCls ?> pl-4" style="border-left-color: transparent !important; border-left: none !important; padding-left: 0 !important;">
      <?php foreach ($grouped as $roleKey => $roleAtts): ?>
        <div>
          <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-3 flex items-center gap-2">
            <i class="fas fa-users text-indigo-500"></i>
            <?= htmlspecialchars(roleLabel($roleKey)) ?>
            <span class="text-xs bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300 px-2 py-0.5 rounded-full"><?= count($roleAtts) ?></span>
          </h3>
          <div class="space-y-3">
            <?php foreach ($roleAtts as $a):
              $isLeave = in_array(trim($a['status'] ?? ''), ['Izin','Sakit','Cuti','Alpha']);
              $profileUrl = !empty($a['user_id']) ? '?page=profile&user_id=' . (int)$a['user_id'] : '';
              $avatarSrc = resolveAvatarSrc($a['avatar'] ?? '');
            ?>
              <div class="attendance-card bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm p-4 flex items-start gap-4 hover:shadow-md transition<?= $profileUrl ? ' cursor-pointer' : '' ?>"<?= $profileUrl ? ' data-profile-url="' . htmlspecialchars($profileUrl) . '"' : '' ?>>
                <img loading="lazy" src="<?= htmlspecialchars($avatarSrc) ?>" class="w-12 h-12 rounded-full object-cover border border-slate-200 dark:border-slate-600" alt="avatar" />
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-2 mb-2">
                    <div class="min-w-0">
                      <div class="font-semibold text-indigo-900 dark:text-indigo-300 text-sm truncate"><?= htmlspecialchars($a['full_name']) ?></div>
                      <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate"><?= htmlspecialchars(roleLabel($a['role'])) ?> • Hari Ini</div>
                    </div>
                    <?= statusBadge($a['status']) ?>
                  </div>
                  <?php if ($isLeave): ?>
                    <div class="text-sm text-slate-600 dark:text-slate-400">
                      <?php if (!empty($a['leave'])): ?>
                        <div>Rentang: <?= htmlspecialchars(formatDateRange($a['leave']['start_date'], $a['leave']['end_date'])) ?></div>
                        <?php if (!empty($a['leave']['reason'])): ?><div>Alasan: <?= htmlspecialchars($a['leave']['reason']) ?></div><?php endif; ?>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                      <div><div class="text-slate-500 dark:text-slate-400">Masuk</div><div class="font-semibold text-indigo-900 dark:text-indigo-300"><?= timeOnly($a['check_in_time']) ?></div></div>
                      <div><div class="text-slate-500 dark:text-slate-400">Pulang</div><div class="font-semibold text-indigo-900 dark:text-indigo-300"><?= timeOnly($a['check_out_time']) ?></div></div>
                    </div>
                    <div class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                      <div><span class="text-slate-500 dark:text-slate-400">Plan:</span> <?= htmlspecialchars($a['today_plan'] ?: '-') ?></div>
                      <div><span class="text-slate-500 dark:text-slate-400">Catatan:</span> <?= htmlspecialchars($a['notes'] ?: '-') ?></div>
                      <?php if (!empty($a['check_in_lat']) && !empty($a['check_in_lng'])): ?>
                        <div class="mt-1"><span class="text-slate-500 dark:text-slate-400"><i class="fas fa-map-marker-alt text-red-500"></i> Lokasi Masuk:</span>
                          <a href="https://www.google.com/maps?q=<?= $a['check_in_lat'] ?>,<?= $a['check_in_lng'] ?>" target="_blank" rel="noopener" class="text-blue-600 dark:text-blue-400 hover:underline text-xs"><?= htmlspecialchars($a['check_in_location'] ?: number_format($a['check_in_lat'], 5) . ', ' . number_format($a['check_in_lng'], 5)) ?></a></div>
                      <?php endif; ?>
                      <?php if (!empty($a['check_out_lat']) && !empty($a['check_out_lng'])): ?>
                        <div><span class="text-slate-500 dark:text-slate-400"><i class="fas fa-map-marker-alt text-green-500"></i> Lokasi Pulang:</span>
                          <a href="https://www.google.com/maps?q=<?= $a['check_out_lat'] ?>,<?= $a['check_out_lng'] ?>" target="_blank" rel="noopener" class="text-blue-600 dark:text-blue-400 hover:underline text-xs"><?= number_format($a['check_out_lat'], 5) . ', ' . number_format($a['check_out_lng'], 5) ?></a></div>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="flex flex-col gap-2">
                  <?php if ($isLeave):
                    $proofPath = !empty($a['leave']['proof_path']) ? $a['leave']['proof_path'] : (!empty($a['proof_path'] ?? null) ? $a['proof_path'] : '');
                    if (!empty($proofPath)): ?>
                      <a href="<?= assetUrl($proofPath) ?>" target="_blank"><img loading="lazy" src="<?= assetUrl($proofPath) ?>" class="w-16 h-16 object-cover rounded-lg border border-slate-200 dark:border-slate-600" alt="Bukti"></a>
                    <?php endif; ?>
                  <?php else: ?>
                    <?php if (!empty($a['check_in_photo'])): ?><a href="<?= assetUrl($a['check_in_photo']) ?>" target="_blank"><img loading="lazy" src="<?= assetUrl($a['check_in_photo']) ?>" class="w-16 h-16 object-cover rounded border border-slate-200 dark:border-slate-600 bg-slate-100 dark:bg-slate-700" alt="Foto Masuk" onerror="this.style.display='none'"></a><?php endif; ?>
                    <?php if (!empty($a['check_out_photo'])): ?><a href="<?= assetUrl($a['check_out_photo']) ?>" target="_blank"><img loading="lazy" src="<?= assetUrl($a['check_out_photo']) ?>" class="w-16 h-16 object-cover rounded border border-slate-200 dark:border-slate-600 bg-slate-100 dark:bg-slate-700" alt="Foto Pulang" onerror="this.style.display='none'"></a><?php endif; ?>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    </div>
  <?php } ?>

  <?php if (!empty($groupedDay) || !empty($groupedNight)): ?>
    <?php renderShiftCards($groupedDay, 'Absen Siang', 'fas fa-sun text-amber-500', 'border-emerald-400 dark:border-emerald-600'); ?>
    <?php renderShiftCards($groupedNight, 'Absen Malam', 'fas fa-moon text-indigo-400', 'border-indigo-400 dark:border-indigo-600'); ?>
  <?php else: ?>
    <div class="text-center text-slate-500 dark:text-slate-400 italic bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-8">
      Belum ada staff yang absen hari ini.
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($viewDetail || $viewHistory): ?>
<div class="mt-6">
  <div class="flex items-center justify-between mb-4">
    <div class="font-semibold text-slate-800 dark:text-white">
      <?php if ($viewHistory): ?>
        Daftar Izin/Sakit/Cuti/Alpha/Absen Masuk Saya
      <?php else: ?>
        Detail Absensi Hari Ini
      <?php endif; ?>
    </div>
    <a href="?page=absen-list" class="inline-flex items-center gap-2 px-3 py-1 border border-slate-300 dark:border-slate-600 rounded hover:bg-slate-50 dark:hover:bg-slate-700 dark:text-white text-sm">
      ← Kembali ke Ringkasan
    </a>
  </div>

  <?php
  $totalTechnicians = count($technicianList ?? []);
  $presentCount = 0;
  $absentCount = 0;
  
  if ($viewHistory) {
    $stmt = $pdo->prepare("
      SELECT a.*, u.full_name, u.role, u.avatar
      FROM attendances a
      JOIN users u ON a.user_id = u.id
      WHERE a.user_id = ?
      ORDER BY a.attendance_date DESC, a.check_in_time ASC
    ");
    $stmt->execute([$currentUserId]);
    $displayAttendances = $stmt->fetchAll(PDO::FETCH_ASSOC);

enrichAttendancesWithActivitiesAndLeaves($pdo, $displayAttendances);
  } else {
    $today = date('Y-m-d');
    $displayAttendances = fetchMyAttendanceForList($pdo, $currentUserId, $today);
    $displayAttendances = array_merge($displayAttendances, fetchApprovedLeavesForDate($pdo, $today, $currentUserId));
    enrichAttendancesWithActivitiesAndLeaves($pdo, $displayAttendances);
  }
  
  foreach ($displayAttendances as $a) {
    $status = trim($a['status'] ?? '');
    if (in_array($status, ['Hadir', 'Terlambat'])) {
      $presentCount++;
    } elseif (in_array($status, ['Izin', 'Sakit', 'Cuti', 'Not Checked Out', 'Lembur'])) {
      $absentCount++;
    }
  }
  
  $totalRecords = count($displayAttendances);
  
  if (!$isAdmin) {
    $totalIzin = 0;
    $totalSakit = 0;
    
    foreach ($displayAttendances as $a) {
      $status = trim($a['status'] ?? '');
      if ($status === 'Izin') {
        $totalIzin++;
      } elseif ($status === 'Sakit') {
        $totalSakit++;
      }
    }
    
    $workingDays = $presentCount + $absentCount;
    $percentage = $workingDays > 0 ? round(($presentCount / $workingDays) * 100) : 0;
  } else {
    $percentage = round($presentCount / max($totalTechnicians, 1) * 100);
  }
  ?>

  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
    <div class="text-center">
      <div class="text-2xl font-bold text-green-600 dark:text-green-400"><?= $presentCount ?></div>
      <div class="text-sm text-slate-500 dark:text-slate-400">Hadir</div>
    </div>
    <?php if (!$isAdmin): ?>
    <div class="text-center">
      <div class="text-2xl font-bold text-blue-600 dark:text-blue-400"><?= $totalIzin ?></div>
      <div class="text-sm text-slate-500 dark:text-slate-400">Total Izin</div>
    </div>
    <div class="text-center">
      <div class="text-2xl font-bold text-orange-600 dark:text-orange-400"><?= $totalSakit ?></div>
      <div class="text-sm text-slate-500 dark:text-slate-400">Total Sakit</div>
    </div>
    <div class="text-center">
      <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400"><?= $percentage ?>%</div>
      <div class="text-sm text-slate-500 dark:text-slate-400">Persentase Kehadiran</div>
    </div>
    <?php else: ?>
    <div class="text-center">
      <div class="text-2xl font-bold text-red-600 dark:text-red-400"><?= $absentCount ?></div>
      <div class="text-sm text-slate-500 dark:text-slate-400">Tidak Hadir</div>
    </div>
    <div class="text-center">
      <div class="text-2xl font-bold text-blue-600 dark:text-blue-400"><?= $totalTechnicians ?></div>
      <div class="text-sm text-slate-500 dark:text-slate-400">Total Teknisi</div>
    </div>
    <div class="text-center">
      <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400"><?= $percentage ?>%</div>
      <div class="text-sm text-slate-500 dark:text-slate-400">Persentase</div>
    </div>
    <?php endif; ?>
  </div>

  <?php if (count($displayAttendances) > 0): ?>
    <div class="space-y-3">
      <?php foreach ($displayAttendances as $a): ?>
        <?php
          $isLeave = in_array(trim($a['status'] ?? ''), ['Izin','Sakit','Cuti','Alpha']);
          $profileUrl = !empty($a['user_id']) ? '?page=profile&user_id=' . (int)$a['user_id'] : '';
          $avatarSrc = resolveAvatarSrc($a['avatar'] ?? '');
        ?>
        <div class="attendance-card bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm p-4<?= $profileUrl ? ' cursor-pointer' : '' ?>"<?= $profileUrl ? ' data-profile-url="' . htmlspecialchars($profileUrl) . '"' : '' ?>>
          <div class="flex items-start gap-4">
            <img loading="lazy" src="<?= htmlspecialchars($avatarSrc) ?>" class="w-12 h-12 rounded-full object-cover border border-slate-200 dark:border-slate-600" alt="avatar" />
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between gap-2 mb-2">
                <div class="min-w-0">
                  <div class="font-semibold text-indigo-900 dark:text-indigo-300 text-sm truncate"><?= htmlspecialchars($a['full_name']) ?></div>
                  <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                    <?= htmlspecialchars(roleLabel($a['role'])) ?> • <?= htmlspecialchars(date('d M Y', strtotime($a['attendance_date']))) ?>
                  </div>
                </div>
                <?= statusBadge($a['status']) ?>
              </div>

              <?php if ($isLeave): ?>
                <div class="text-sm text-slate-600 dark:text-slate-400">
                  <?php if (!empty($a['leave'])): ?>
                    <div>Rentang: <?= htmlspecialchars(formatDateRange($a['leave']['start_date'], $a['leave']['end_date'])) ?></div>
                    <?php if (!empty($a['leave']['reason'])): ?>
                      <div>Alasan: <?= htmlspecialchars($a['leave']['reason']) ?></div>
                    <?php endif; ?>
                  <?php endif; ?>



                </div>
              <?php else: ?>
                <div class="grid grid-cols-2 gap-4 text-sm">
                  <div>
                    <div class="text-slate-500 dark:text-slate-400">Masuk</div>
                    <div class="font-semibold text-indigo-900 dark:text-indigo-300"><?= timeOnly($a['check_in_time']) ?></div>
                  </div>
                  <div>
                    <div class="text-slate-500 dark:text-slate-400">Pulang</div>
                    <div class="font-semibold text-indigo-900 dark:text-indigo-300"><?= timeOnly($a['check_out_time']) ?></div>
                  </div>
                </div>
                <div class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                  <div><span class="text-slate-500 dark:text-slate-400">Plan:</span> <?= htmlspecialchars($a['today_plan'] ?: '-') ?></div>
                  <div><span class="text-slate-500 dark:text-slate-400">Catatan:</span> <?= htmlspecialchars($a['notes'] ?: '-') ?></div>
                  <?php if (!empty($a['check_in_lat']) && !empty($a['check_in_lng'])): ?>
                    <div class="mt-1">
                      <span class="text-slate-500 dark:text-slate-400"><i class="fas fa-map-marker-alt text-red-500"></i> Lokasi Masuk:</span>
                      <a href="https://www.google.com/maps?q=<?= $a['check_in_lat'] ?>,<?= $a['check_in_lng'] ?>" target="_blank" rel="noopener" class="text-blue-600 dark:text-blue-400 hover:underline text-xs">
                        <?= htmlspecialchars($a['check_in_location'] ?: number_format($a['check_in_lat'], 5) . ', ' . number_format($a['check_in_lng'], 5)) ?>
                      </a>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($a['check_out_lat']) && !empty($a['check_out_lng'])): ?>
                    <div>
                      <span class="text-slate-500 dark:text-slate-400"><i class="fas fa-map-marker-alt text-green-500"></i> Lokasi Pulang:</span>
                      <a href="https://www.google.com/maps?q=<?= $a['check_out_lat'] ?>,<?= $a['check_out_lng'] ?>" target="_blank" rel="noopener" class="text-blue-600 dark:text-blue-400 hover:underline text-xs">
                        <?= number_format($a['check_out_lat'], 5) . ', ' . number_format($a['check_out_lng'], 5) ?>
                      </a>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="flex flex-col gap-2">
              <?php if ($isLeave): ?>
                <?php
                  $proofPath = '';
                  if (!empty($a['leave']['proof_path'])) {
                    $proofPath = $a['leave']['proof_path'];
                  } elseif (!empty($a['proof_path'] ?? null)) {
                    $proofPath = $a['proof_path'];
                  }
                ?>
                <?php if (!empty($proofPath)): ?>
                  <a href="<?= assetUrl($proofPath) ?>" target="_blank">
                    <img loading="lazy" src="<?= assetUrl($proofPath) ?>" class="w-16 h-16 object-cover rounded-lg border border-slate-200 dark:border-slate-600" alt="Bukti">
                  </a>
                <?php endif; ?>
              <?php else: ?>
                <?php if (!empty($a['check_in_photo'])): ?>
                  <a href="<?= assetUrl($a['check_in_photo']) ?>" target="_blank">
                    <img loading="lazy" src="<?= assetUrl($a['check_in_photo']) ?>" class="w-16 h-16 object-cover rounded border border-slate-200 dark:border-slate-600" alt="Foto Masuk">
                  </a>
                <?php endif; ?>
                <?php if (!empty($a['check_out_photo'])): ?>
                  <a href="<?= assetUrl($a['check_out_photo']) ?>" target="_blank">
                    <img loading="lazy" src="<?= assetUrl($a['check_out_photo']) ?>" class="w-16 h-16 object-cover rounded border border-slate-200 dark:border-slate-600" alt="Foto Pulang">
                  </a>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>

          <?php if (!empty($a['activities'])): ?>
            <div class="mt-3">
              <div class="text-sm text-slate-500 dark:text-slate-400 mb-2">Aktivitas:</div>
              <div class="flex flex-wrap gap-2">
                <?php foreach ($a['activities'] as $actPhoto): ?>
                  <a href="<?= assetUrl($actPhoto) ?>" target="_blank">
                    <img loading="lazy" src="<?= assetUrl($actPhoto) ?>" class="w-12 h-12 object-cover rounded border border-blue-100 dark:border-blue-900" />
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="text-center text-slate-500 dark:text-slate-400 italic bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-8">
      <?php if ($viewHistory): ?>
        Belum ada riwayat absensi.
      <?php else: ?>
        Belum ada staff yang absen di tanggal ini.
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($isAdmin && $search === ''): ?>
  <?php if (isset($_GET['debug'])): ?>
    <div class="bg-blue-100 p-2 mb-2 text-xs">
      Navigation Debug: isAdmin=<?= $isAdmin ? 'true' : 'false' ?>, search='<?= $search ?>', monthParam='<?= $monthParam ?>'
    </div>
  <?php endif; ?>
  
  <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 mb-4">
    <?php if ($monthParam === ''): ?>
      <?php if (isset($_GET['debug'])): ?>
        <div class="bg-yellow-100 p-1 mb-2 text-xs">Showing YEAR overview (monthParam is empty)</div>
      <?php endif; ?>
      
      <div class="flex items-center justify-between mb-3">
        <div class="font-semibold text-slate-800 dark:text-white">Rekap Absensi per Bulan (<?= $year ?>)</div>
        <div class="flex items-center gap-2 text-sm">
          <a class="px-2 py-1 border border-slate-300 dark:border-slate-600 dark:text-white rounded hover:bg-slate-50 dark:hover:bg-slate-700" href="?page=absen-list&amp;year=<?= $year-1 ?>">&larr; <?= $year-1 ?></a>
          <a class="px-2 py-1 border border-slate-300 dark:border-slate-600 dark:text-white rounded hover:bg-slate-50 dark:hover:bg-slate-700" href="?page=absen-list&amp;year=<?= $year+1 ?>"><?= $year+1 ?> &rarr;</a>
        </div>
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
        <?php for ($m=1; $m<=12; $m++): $ym = sprintf('%04d-%02d', $year, $m); $cnt = $monthCounts[$ym] ?? 0; ?>
          <a href="?page=absen-list&amp;year=<?= $year ?>&amp;month=<?= $ym ?>" class="block border border-slate-200 dark:border-slate-700 rounded-lg p-3 hover:shadow-md transition bg-white dark:bg-slate-800">
            <div class="text-sm font-semibold text-slate-800 dark:text-white"><?= date('F', strtotime($ym.'-01')) ?></div>
            <div class="text-xs text-slate-500 dark:text-slate-400">Total absen: <b><?= $cnt ?></b></div>
          </a>
        <?php endfor; ?>
      </div>
    <?php else: ?>
      <?php if (isset($_GET['debug'])): ?>
        <div class="bg-green-100 p-1 mb-2 text-xs">
          Showing MONTH overview (monthParam='<?= $monthParam ?>') <br>
          Days count: <?= count($dayCounts) ?> | AdminMonthStart: <?= $adminMonthStart ?>
        </div>
      <?php endif; ?>
      
      <div class="flex items-center justify-between mb-3">
        <div class="font-semibold text-slate-800 dark:text-white">Tanggal di <?= date('F Y', strtotime($adminMonthStart)) ?></div>
        <div class="text-sm">
          <a class="px-2 py-1 border border-slate-300 dark:border-slate-600 dark:text-white rounded hover:bg-slate-50 dark:hover:bg-slate-700" href="?page=absen-list&amp;year=<?= $year ?>">&larr; Kembali ke Bulan</a>
        </div>
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-2">
        <?php 
          $days = (int)date('t', strtotime($adminMonthStart));
          
          if (isset($_GET['debug'])) {
            echo "<div class='col-span-full bg-yellow-100 p-1 text-xs'>Loop will run for $days days in " . date('F Y', strtotime($adminMonthStart)) . "</div>";
          }
          
          for ($d=1; $d<=$days; $d++): 
            $dateStr = date('Y-m-d', strtotime($adminMonthStart.' +'.($d-1).' day')); 
            $cnt = $dayCounts[$dateStr] ?? 0; 
            $isToday = ($dateStr === date('Y-m-d'));
            $cardClass = $cnt > 0 ? 'border border-green-200 bg-green-50 dark:bg-green-900 dark:border-green-700' : 'border bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700';
        ?>
          <a href="?page=absen-list&amp;year=<?= $year ?>&amp;month=<?= $monthParam ?>&amp;fromMonth=<?= $monthParam ?>&amp;date=<?= $dateStr ?>" class="block rounded-lg p-2 hover:shadow transition <?= $cardClass ?> <?= $isToday ? 'ring-2 ring-indigo-200 dark:ring-indigo-700' : '' ?> text-sm">
            <div class="flex items-center justify-between">
              <div class="font-semibold text-slate-800 dark:text-white"><?= date('d M', strtotime($dateStr)) ?></div>
              <?php if ($isToday): ?><div class="text-[11px] text-indigo-700 dark:text-indigo-300">Hari ini</div><?php endif; ?>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
              <?php if ($cnt > 0): ?>
                <span class="text-sm text-slate-800 dark:text-slate-100">Staff: <b class="text-green-700 dark:text-green-300"><?= $cnt ?></b></span>
              <?php else: ?>
                <span class="text-slate-400 dark:text-slate-500">Tidak ada</span>
              <?php endif; ?>
            </div>
          </a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if (!$suppress_list && $isAdmin): ?>
<?php 
if (isset($_GET['date']) && isset($_GET['fromMonth'])): 
  $fromMonth = $_GET['fromMonth'];
?>
  <div class="mb-4">
    <a class="inline-flex items-center gap-2 px-3 py-1 border border-slate-300 dark:border-slate-600 dark:text-white rounded hover:bg-slate-50 dark:hover:bg-slate-700 text-sm" href="?page=absen-list&amp;year=<?= isset($_GET['year'])? (int)$_GET['year'] : (int)date('Y') ?>&amp;month=<?= $fromMonth ?>">
      <span>&larr;</span> Kembali ke <?= date('F Y', strtotime($fromMonth.'-01')) ?>
    </a>
  </div>
<?php endif; ?>

<?php if ($searching): ?>
  <?php
    $groupedAttendances = [];
    foreach ($attendances as $a) {
        $roleKey = $a['role'] ?? 'staff';
        $groupedAttendances[$roleKey][] = $a;
    }
    ksort($groupedAttendances);
  ?>
  <?php if (empty($groupedAttendances)): ?>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 text-center italic text-gray-500 dark:text-gray-400 shadow">Tidak ada data absen ditemukan.</div>
  <?php else: ?>
    <div class="space-y-6">
      <?php foreach ($groupedAttendances as $roleKey => $roleAttendances): ?>
        <div>
          <h3 class="text-md font-bold text-slate-800 dark:text-white mb-2 flex items-center gap-2">
            <i class="fas fa-users text-indigo-500"></i>
            <?= htmlspecialchars(roleLabel($roleKey)) ?>
            <span class="text-xs bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200 px-2 py-0.5 rounded-full">
              <?= count($roleAttendances) ?> Staff
            </span>
          </h3>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-indigo-200 dark:divide-indigo-800 bg-white dark:bg-slate-800 rounded-xl shadow text-xs sm:text-sm">
              <thead>
                <tr class="bg-indigo-100 dark:bg-indigo-900 text-indigo-900 dark:text-indigo-100">
                  <th class="py-2 px-2 sm:px-3 text-left text-xs font-semibold">Nama</th>
                  <th class="py-2 px-2 sm:px-3 text-left text-xs font-semibold">Tanggal</th>
                  <th class="py-2 px-2 sm:px-3 text-left text-xs font-semibold">Jam Masuk</th>
                  <th class="py-2 px-2 sm:px-3 text-left text-xs font-semibold">Jam Pulang</th>
                  <th class="py-2 px-2 sm:px-3 text-left text-xs font-semibold">Status</th>
                  <th class="py-2 px-2 sm:px-3 text-left text-xs font-semibold">Plan</th>
                  <th class="py-2 px-2 sm:px-3 text-left text-xs font-semibold">Catatan</th>
                  <th class="py-2 px-2 sm:px-3 text-left text-xs font-semibold">Foto Masuk</th>
                  <th class="py-2 px-2 sm:px-3 text-left text-xs font-semibold">Foto Pulang</th>
                  <th class="py-2 px-2 sm:px-3 text-left text-xs font-semibold">Foto Activity</th>
                  <th class="py-2 px-2 sm:px-3 text-left text-xs font-semibold">Lokasi Masuk</th>
                  <th class="py-2 px-2 sm:px-3 text-left text-xs font-semibold">Lokasi Pulang</th>
                  <th class="py-2 px-2 sm:px-3 text-left text-xs font-semibold">Bukti Izin/Sakit</th>
                  <?php if ($isAdmin): ?>
                  <th class="py-2 px-2 sm:px-3 text-left text-xs font-semibold">Aksi</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody class="dark:text-slate-300">
                <?php foreach ($roleAttendances as $a): ?>
                <tr class="dark:border-slate-700">
                  <td class="py-2 px-2 sm:px-3"><?= htmlspecialchars($a['full_name']) ?></td>
                  <td class="py-2 px-2 sm:px-3"><?= htmlspecialchars($a['attendance_date']) ?></td>
                  <td class="py-2 px-2 sm:px-3"><?= isset($a['check_in_time']) ? timeOnly($a['check_in_time']) : '-' ?></td>
                  <td class="py-2 px-2 sm:px-3"><?= isset($a['check_out_time']) ? timeOnly($a['check_out_time']) : '-' ?></td>
                  <td class="py-2 px-2 sm:px-3">
                    <?= statusBadge($a['status']) ?>
                    <?php if (!empty($a['attendance_request'])):
                      $reqType = $a['attendance_request']['request_type'] ?? 'checkin';
                      if ($reqType === 'missed_checkout') {
                          $reqLabel = 'Via Request (Pulang)';
                          $reqTitle = 'Absen pulang melalui request admin (lupa absen pulang)';
                      } else {
                          $reqLabel = 'Via Request (Masuk)';
                          $reqTitle = 'Absen masuk melalui request admin';
                      }
                    ?>
                      <span class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300" title="<?= htmlspecialchars($reqTitle) ?>">
                        <i class="fas fa-user-check"></i> <?= htmlspecialchars($reqLabel) ?>
                      </span>
                    <?php endif; ?>
                  </td>
                  <td class="py-2 px-2 sm:px-3"><?= htmlspecialchars($a['today_plan'] ?? '-') ?></td>
                  <td class="py-2 px-2 sm:px-3"><?= htmlspecialchars($a['notes'] ?? '-') ?></td>
                  <td class="py-2 px-2 sm:px-3">
                    <?php if (!empty($a['check_in_photo'])): ?>
                      <a href="<?= assetUrl($a['check_in_photo']) ?>" target="_blank">
                        <img loading="lazy" src="<?= assetUrl($a['check_in_photo']) ?>" alt="" class="w-12 h-12 object-cover rounded" />
                      </a>
                    <?php else: ?>
                      <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                    <?php endif; ?>
                  </td>
                  <td class="py-2 px-2 sm:px-3">
                    <?php if (!empty($a['check_out_photo'])): ?>
                      <a href="<?= assetUrl($a['check_out_photo']) ?>" target="_blank">
                        <img loading="lazy" src="<?= assetUrl($a['check_out_photo']) ?>" alt="" class="w-12 h-12 object-cover rounded" />
                      </a>
                    <?php else: ?>
                      <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                    <?php endif; ?>
                  </td>
                  <td class="py-2 px-2 sm:px-3">
                    <?php if (!empty($a['activities'])): ?>
                      <div class="flex flex-wrap gap-1">
                      <?php foreach ($a['activities'] as $actPhoto): ?>
                        <a href="<?= assetUrl($actPhoto) ?>" target="_blank">
                          <img loading="lazy" src="<?= assetUrl($actPhoto) ?>" class="w-10 h-10 object-cover rounded mb-1" />
                        </a>
                      <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                    <?php endif; ?>
                  </td>
                  <td class="py-2 px-2 sm:px-3">
                    <?php if (!empty($a['check_in_lat']) && !empty($a['check_in_lng'])): ?>
                      <a href="https://www.google.com/maps?q=<?= $a['check_in_lat'] ?>,<?= $a['check_in_lng'] ?>" target="_blank" class="text-red-600 dark:text-red-400 hover:underline text-xs">
                        <i class="fas fa-map-marker-alt mr-1"></i>
                        <?= htmlspecialchars($a['check_in_location'] ?: number_format($a['check_in_lat'], 5) . ', ' . number_format($a['check_in_lng'], 5)) ?>
                      </a>
                    <?php else: ?>
                      <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                    <?php endif; ?>
                  </td>
                  <td class="py-2 px-2 sm:px-3">
                    <?php if (!empty($a['check_out_lat']) && !empty($a['check_out_lng'])): ?>
                      <a href="https://www.google.com/maps?q=<?= $a['check_out_lat'] ?>,<?= $a['check_out_lng'] ?>" target="_blank" class="text-green-600 dark:text-green-400 hover:underline text-xs">
                        <i class="fas fa-map-marker-alt mr-1"></i>
                        <?= number_format($a['check_out_lat'], 5) . ', ' . number_format($a['check_out_lng'], 5) ?>
                      </a>
                    <?php else: ?>
                      <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                    <?php endif; ?>
                  </td>
                  <td class="py-2 px-2 sm:px-3">
                    <?php
                      $proofPath = '';
                      $isLeaveStatus = in_array(trim($a['status'] ?? ''), ['Izin','Sakit','Cuti','Alpha']);
                      if ($isLeaveStatus) {
                        if (!empty($a['leave']['proof_path'])) {
                          $proofPath = $a['leave']['proof_path'];
                        } elseif (!empty($a['proof_path'] ?? null)) {
                          $proofPath = $a['proof_path'];
                        }
                      }
                    ?>
                    <?php if (!empty($proofPath)): ?>
                      <a href="<?= assetUrl($proofPath) ?>" target="_blank">
                        <img loading="lazy" src="<?= assetUrl($proofPath) ?>" alt="Bukti" class="w-12 h-12 object-cover rounded" />
                      </a>
                    <?php elseif ($isLeaveStatus): ?>
                      <span class="text-xs text-gray-400 dark:text-gray-500">Tidak ada bukti</span>
                    <?php endif; ?>
                  </td>
                  <?php if ($canEditAttendance && isset($a['id'])): ?>
                  <td class="py-2 px-2 sm:px-3">
                    <button class="edit-btn text-xs px-2 py-1 bg-yellow-500 hover:bg-yellow-600 text-white rounded"
                      data-id="<?= $a['id'] ?>"
                      data-name="<?= htmlspecialchars($a['full_name'] ?? '', ENT_QUOTES) ?>"
                      data-date="<?= htmlspecialchars($a['attendance_date'] ?? '', ENT_QUOTES) ?>"
                      data-today_plan="<?= htmlspecialchars($a['today_plan'] ?? '', ENT_QUOTES) ?>"
                      data-notes="<?= htmlspecialchars($a['notes'] ?? '', ENT_QUOTES) ?>"
                      data-status="<?= htmlspecialchars($a['status'] ?? '', ENT_QUOTES) ?>"
                      data-check_in="<?= ($a['check_in_time'] && $a['check_in_time'] !== '0000-00-00 00:00:00') ? date('H:i', strtotime($a['check_in_time'])) : '' ?>"
                      data-check_out="<?= ($a['check_out_time'] && $a['check_out_time'] !== '0000-00-00 00:00:00') ? date('H:i', strtotime($a['check_out_time'])) : '' ?>"
                    >Edit</button>
                  </td>
                  <?php elseif ($canEditAttendance): ?>
                  <td class="py-2 px-2 sm:px-3">
                    <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                  </td>
                  <?php endif; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php else: ?>
<?php if ($showLegacyAttendanceCards): ?>
<?php if ($isAdmin && isset($_GET['date']) && $_GET['date'] !== date('Y-m-d')): ?>
  <?php $fromMonth = isset($_GET['fromMonth']) ? preg_replace('/[^0-9\-]/','', $_GET['fromMonth']) : ''; ?>
  <?php if ($fromMonth): ?>
    <div class="mb-3">
      <a class="inline-flex items-center gap-2 px-3 py-1 border border-slate-300 dark:border-slate-600 dark:text-white rounded hover:bg-slate-50 dark:hover:bg-slate-700 text-sm" href="?page=absen-list&amp;year=<?= isset($_GET['year'])? (int)$_GET['year'] : (int)date('Y') ?>&amp;month=<?= $fromMonth ?>">
        &larr; Kembali ke <?= htmlspecialchars(date('F Y', strtotime($fromMonth.'-01'))) ?>
      </a>
    </div>
  <?php endif; ?>
<?php endif; ?>
  <?php
    $groupedAttendancesCards = [];
    foreach ($attendances as $a) {
        $roleKey = $a['role'] ?? 'staff';
        $groupedAttendancesCards[$roleKey][] = $a;
    }
    ksort($groupedAttendancesCards);
  ?>
  <?php if (empty($groupedAttendancesCards)): ?>
    <div class="text-center text-slate-500 dark:text-slate-400 italic">Belum ada staff yang absen di tanggal ini.</div>
  <?php else: ?>
    <div class="space-y-6">
      <?php foreach ($groupedAttendancesCards as $roleKey => $roleAttendances): ?>
        <div>
          <h3 class="text-md font-bold text-slate-800 dark:text-white mb-3 flex items-center gap-2">
            <i class="fas fa-users text-indigo-500"></i>
            <?= htmlspecialchars(roleLabel($roleKey)) ?>
            <span class="text-xs bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200 px-2 py-0.5 rounded-full">
              <?= count($roleAttendances) ?> Staff
            </span>
          </h3>
          <div class="space-y-3">
            <?php foreach ($roleAttendances as $a): ?>
              <?php
                $isLeave = in_array(trim($a['status'] ?? ''), ['Izin','Sakit','Cuti','Alpha']);
              $profileUrl = !empty($a['user_id']) ? '?page=profile&user_id=' . (int)$a['user_id'] : '';
              $avatarSrc = resolveAvatarSrc($a['avatar'] ?? '');
              ?>
              <div class="attendance-card bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm p-3 flex items-start gap-3 hover:shadow-md transition<?= $profileUrl ? ' cursor-pointer' : '' ?>"<?= $profileUrl ? ' data-profile-url="' . htmlspecialchars($profileUrl) . '"' : '' ?>>
                
                <img loading="lazy" src="<?= htmlspecialchars($avatarSrc) ?>" class="w-12 h-12 rounded-full object-cover border border-slate-200 dark:border-slate-600" alt="avatar" />

                
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                      <div class="font-semibold text-indigo-900 dark:text-indigo-300 text-sm truncate"><?= htmlspecialchars($a['full_name']) ?></div>
                      <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate"><?= htmlspecialchars(roleLabel($a['role'])) ?></div>
                    </div>
                    <div class="hidden sm:flex items-center gap-2">
                      <?= statusBadge($a['status']) ?>
                    </div>
                  </div>

                  <?php if ($isLeave): ?>
                    <div class="mt-2 flex flex-col gap-1">
                      <div><?= statusBadge($a['status']) ?></div>
                      <?php if (!empty($a['leave'])): ?>
                        <div class="text-[11px] text-slate-600 dark:text-slate-400"><span class="text-slate-500 dark:text-slate-400">Rentang:</span> <?= htmlspecialchars(formatDateRange($a['leave']['start_date'], $a['leave']['end_date'])) ?></div>
                        <?php if (!empty($a['leave']['reason'])): ?>
                          <div class="text-[11px] text-slate-600 dark:text-slate-400"><span class="text-slate-500 dark:text-slate-400">Alasan:</span> <?= htmlspecialchars($a['leave']['reason']) ?></div>
                        <?php endif; ?>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                  <div class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-3">
                    <div class="text-xs">
                      <div class="text-slate-500 dark:text-slate-400">Masuk</div>
                      <div class="font-semibold text-indigo-900 dark:text-indigo-300"><?= timeOnly($a['check_in_time']) ?></div>
                    </div>
                    <div class="text-xs">
                      <div class="text-slate-500 dark:text-slate-400">Pulang</div>
                      <div class="font-semibold text-indigo-900 dark:text-indigo-300"><?= timeOnly($a['check_out_time']) ?></div>
                    </div>
                    <div class="text-xs md:col-span-1 col-span-2">
                      <div class="text-slate-500 dark:text-slate-400 mb-1">Aktivitas</div>
                      <?php if (!empty($a['activities'])): ?>
                        <div class="flex flex-wrap gap-1">
                          <?php foreach (array_slice($a['activities'],0,6) as $actPhoto): ?>
                            <a href="<?= assetUrl($actPhoto) ?>" target="_blank" title="Activity">
                              <img loading="lazy" src="<?= assetUrl($actPhoto) ?>" class="w-9 h-9 object-cover rounded border border-blue-100 dark:border-blue-900" />
                            </a>
                          <?php endforeach; ?>
                        </div>
                      <?php else: ?>
                        <span class="text-slate-400 dark:text-slate-500">-</span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <?php endif; ?>

                  <div class="mt-2 text-[11px] text-slate-600 dark:text-slate-400"><span class="text-slate-500 dark:text-slate-400">Plan:</span> <?= htmlspecialchars($a['today_plan'] ?: '-') ?></div>
                  <div class="text-[11px] text-slate-600 dark:text-slate-400"><span class="text-slate-500 dark:text-slate-400">Catatan:</span> <?= htmlspecialchars($a['notes'] ?: '-') ?></div>
                  <?php if (!empty($a['check_in_lat']) && !empty($a['check_in_lng'])): ?>
                    <div class="text-[11px] text-slate-600 dark:text-slate-400">
                      <span class="text-slate-500 dark:text-slate-400"><i class="fas fa-map-marker-alt mr-1"></i> Lokasi Masuk:</span>
                      <a href="https://www.google.com/maps?q=<?= $a['check_in_lat'] ?>,<?= $a['check_in_lng'] ?>" target="_blank" class="text-red-600 dark:text-red-400 hover:underline">
                        <?= htmlspecialchars($a['check_in_location'] ?: number_format($a['check_in_lat'], 5) . ', ' . number_format($a['check_in_lng'], 5)) ?>
                      </a>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($a['check_out_lat']) && !empty($a['check_out_lng'])): ?>
                    <div class="text-[11px] text-slate-600 dark:text-slate-400">
                      <span class="text-slate-500 dark:text-slate-400"><i class="fas fa-map-marker-alt mr-1"></i> Lokasi Pulang:</span>
                      <a href="https://www.google.com/maps?q=<?= $a['check_out_lat'] ?>,<?= $a['check_out_lng'] ?>" target="_blank" class="text-green-600 dark:text-green-400 hover:underline">
                        <?= number_format($a['check_out_lat'], 5) . ', ' . number_format($a['check_out_lng'], 5) ?>
                      </a>
                    </div>
                  <?php endif; ?>
                </div>

              <div class="hidden sm:flex flex-col gap-1 ml-2">
                  <?php if ($isLeave): ?>
                    <?php
                      $proofPath = '';
                      if (!empty($a['leave']['proof_path'])) {
                        $proofPath = $a['leave']['proof_path'];
                      } elseif (!empty($a['proof_path'] ?? null)) {
                        $proofPath = $a['proof_path'];
                      }
                    ?>
                    <?php if (!empty($proofPath)): ?>
                      <a href="<?= assetUrl($proofPath) ?>" target="_blank"><img loading="lazy" src="<?= assetUrl($proofPath) ?>" class="w-20 h-20 object-cover rounded-lg border border-slate-200 dark:border-slate-600" alt="Bukti"></a>
                    <?php else: ?>
                      <div class="w-20 h-20 rounded-lg border border-dashed border-slate-200 dark:border-slate-600 text-[10px] text-slate-400 dark:text-slate-500 flex items-center justify-center">Tidak ada<br>bukti</div>
                    <?php endif; ?>
                  <?php else: ?>
                  <?php if (!empty($a['check_in_photo'])): ?>
                    <a href="<?= assetUrl($a['check_in_photo']) ?>" target="_blank"><img loading="lazy" src="<?= assetUrl($a['check_in_photo']) ?>" class="w-14 h-14 object-cover rounded-lg border border-slate-200 dark:border-slate-600" alt="Foto Masuk"></a>
                  <?php else: ?>
                    <div class="w-14 h-14 rounded-lg border border-dashed border-slate-200 dark:border-slate-600 text-[10px] text-slate-400 dark:text-slate-500 flex items-center justify-center">-</div>
                  <?php endif; ?>
                  <?php if (!empty($a['check_out_photo'])): ?>
                    <a href="<?= assetUrl($a['check_out_photo']) ?>" target="_blank"><img loading="lazy" src="<?= assetUrl($a['check_out_photo']) ?>" class="w-14 h-14 object-cover rounded-lg border border-slate-200 dark:border-slate-600" alt="Foto Pulang"></a>
                  <?php else: ?>
                    <div class="w-14 h-14 rounded-lg border border-dashed border-slate-200 dark:border-slate-600 text-[10px] text-slate-400 dark:text-slate-500 flex items-center justify-center">-</div>
                  <?php endif; ?>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.attendance-card[data-profile-url]').forEach(function (card) {
    card.addEventListener('click', function (event) {
      if (event.target.closest('a, button')) {
        return;
      }
      const url = card.getAttribute('data-profile-url');
      if (url) {
        window.location.href = url;
      }
    });
  });
});
</script>

<?php if ($isAdmin): ?>
<?php if ($canEditAttendance): ?>
<div id="editModal" class="fixed inset-0 items-center justify-center z-50 hidden" style="background:rgba(0,0,0,0.45);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px)">
  <div class="rounded-2xl shadow-2xl w-full max-w-md relative overflow-hidden" style="background:#ffffff;color:#0f172a">
    <div class="px-5 py-3 flex items-center justify-between" style="background:#4f46e5">
      <h2 class="text-base font-bold text-white flex items-center gap-2"><i class="fas fa-edit"></i> Edit Absensi</h2>
      <button id="editModalClose" class="text-white/70 hover:text-white transition text-lg">&times;</button>
    </div>
    <div id="editUserInfo" class="px-5 pt-4 pb-2">
      <div class="flex items-center gap-2 text-sm">
        <span id="editUserName" class="font-semibold" style="color:#111827"></span>
        <span style="color:#d1d5db">&bull;</span>
        <span id="editUserDate" class="text-xs" style="color:#6b7280"></span>
      </div>
    </div>
    <form id="editAttendanceForm" class="px-5 pb-5" style="background:#ffffff">
      <input type="hidden" name="edit_attendance_modal" value="1">
      <input type="hidden" name="id" id="edit_id">
      <div class="grid grid-cols-2 gap-3 mb-3">
        <div>
          <label class="block text-xs font-semibold mb-1" style="color:#4b5563">Jam Masuk</label>
          <input type="time" name="check_in_time" id="edit_check_in" class="w-full rounded-lg px-3 py-2 text-sm" style="border:1px solid #d1d5db;background:#fff;color:#111827">
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1" style="color:#4b5563">Jam Pulang</label>
          <input type="time" name="check_out_time" id="edit_check_out" class="w-full rounded-lg px-3 py-2 text-sm" style="border:1px solid #d1d5db;background:#fff;color:#111827">
        </div>
      </div>
      <div class="mb-3">
        <label class="block text-xs font-semibold mb-1" style="color:#4b5563">Status</label>
        <select name="status" id="edit_status" class="w-full rounded-lg px-3 py-2 text-sm" style="border:1px solid #d1d5db;background:#fff;color:#111827">
          <option value="">-</option>
          <option value="Hadir">Hadir</option>
          <option value="Terlambat">Terlambat</option>
          <option value="Not Checked Out">Not Checked Out</option>
          <option value="Izin">Izin</option>
          <option value="Sakit">Sakit</option>
          <option value="Alpha">Alpha</option>
          <option value="Cuti">Cuti</option>
          <option value="Lembur">Lembur</option>
        </select>
      </div>
      <div class="mb-3">
        <label class="block text-xs font-semibold mb-1" style="color:#4b5563">Plan Hari Ini</label>
        <input type="text" name="today_plan" id="edit_today_plan" placeholder="Rencana kerja hari ini" class="w-full rounded-lg px-3 py-2 text-sm" style="border:1px solid #d1d5db;background:#fff;color:#111827">
      </div>
      <div class="mb-3">
        <label class="block text-xs font-semibold mb-1" style="color:#4b5563">Keterangan / Catatan</label>
        <textarea name="notes" id="edit_notes" rows="2" placeholder="Catatan tambahan..." class="w-full rounded-lg px-3 py-2 text-sm resize-none" style="border:1px solid #d1d5db;background:#fff;color:#111827"></textarea>
      </div>
      <div id="editModalAlert" class="text-sm rounded-lg p-2 mb-3" style="display:none;background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0"></div>
      <div class="flex justify-end gap-2">
        <button type="button" id="editCloseBtn" class="px-4 py-2 rounded-lg text-sm font-medium transition" style="background:#f3f4f6;color:#374151">Batal</button>
        <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium transition" style="background:#4f46e5;color:#ffffff">Simpan</button>
      </div>
    </form>
  </div>
</div>

</div>

<script>
document.querySelectorAll('.edit-btn').forEach(btn => {
  btn.onclick = function() {
    document.getElementById('edit_id').value = this.dataset.id;
    document.getElementById('edit_today_plan').value = this.dataset.today_plan;
    document.getElementById('edit_notes').value = this.dataset.notes;
    document.getElementById('edit_status').value = this.dataset.status;
    document.getElementById('edit_check_in').value = this.dataset.check_in || '';
    document.getElementById('edit_check_out').value = this.dataset.check_out || '';
    document.getElementById('editUserName').textContent = this.dataset.name || '';
    document.getElementById('editUserDate').textContent = this.dataset.date || '';
    const m = document.getElementById('editModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
    document.getElementById('editModalAlert').style.display = 'none';
  };
});
document.getElementById('editCloseBtn').onclick =
document.getElementById('editModalClose').onclick = function() {
  const m = document.getElementById('editModal');
  m.classList.add('hidden');
  m.classList.remove('flex');
};

document.getElementById('editAttendanceForm').onsubmit = async function(e) {
  e.preventDefault();
  const form = e.target;
  const data = new FormData(form);
  const btn = form.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.textContent = 'Menyimpan...';
  try {
    const resp = await fetch('', { method: "POST", body: data });
    const text = (await resp.text()).trim();
    if (text === "OK") {
      document.getElementById('editModalAlert').textContent = "Berhasil disimpan!";
      document.getElementById('editModalAlert').style.display = '';
      setTimeout(() => location.reload(), 800);
    } else {
      const alertEl = document.getElementById('editModalAlert');
      alertEl.textContent = text || 'Gagal menyimpan';
      alertEl.style.background = '#fef2f2';
      alertEl.style.color = '#b91c1c';
      alertEl.style.borderColor = '#fecaca';
      alertEl.style.display = '';
    }
  } catch(err) {
    document.getElementById('editModalAlert').textContent = 'Error: ' + err;
    document.getElementById('editModalAlert').style.display = '';
  }
  btn.disabled = false;
  btn.textContent = 'Simpan';
};
</script>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
