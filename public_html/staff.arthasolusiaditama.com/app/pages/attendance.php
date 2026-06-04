<?php

require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/socket-notify.php';
require_once __DIR__ . '/../helpers/audit-log.php';

@date_default_timezone_set('Asia/Jakarta');



try {
  if (isset($_GET['night'])) {
    $_SESSION['abs_mode'] = ($_GET['night'] === '1') ? 'night' : 'day';
  }
} catch (Throwable $e) {   }


function formatIndoDate($dateStr) {
    $ts = strtotime($dateStr);
    $day = date('d', $ts);
    $monthNames = [
        1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'
    ];
    $month = $monthNames[(int)date('n', $ts)];
    $year  = date('Y', $ts);
    return [$day, "$month $year"];
}
function roleLabel($role) { return $role ? ucwords(str_replace('_',' ',$role)) : 'Staff'; }
function ensureDir($dir) { if (!is_dir($dir)) {@mkdir($dir, 0777, true);} }
function saveImage($file, $prefix, $userId, $dateDir, $targetBaseRelDir = null) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return [null, 'Upload gagal'];
    $allowed = ['jpg','jpeg','png','webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return [null, 'Format gambar tidak didukung'];
    
    $randomSuffix = '';
    if (function_exists('random_bytes')) {
      try { $randomSuffix = bin2hex(random_bytes(4)); } catch (Exception $e) { $randomSuffix = substr(md5(uniqid((string)mt_rand(), true)), 0, 8); }
    } else {
      $randomSuffix = substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
    }
    $filename = sprintf('%s_%d_%s_%s.%s', $prefix, $userId, date('Ymd_His'), $randomSuffix, $ext);
    
    $basePath = dirname(__DIR__, 2);
    $basePath = realpath($basePath);
    
    $relDir = $targetBaseRelDir ? (rtrim(str_replace(['\\','//'], '/', $targetBaseRelDir), '/') . '/') : ('uploads/attendance/' . $dateDir . '/');
    $absDir = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relDir);
    ensureDir($absDir);
    $absPath = $absDir . $filename;
    
    
    $max_dim = 800;
    $quality = 75;
    $success = false;
    
    $imgInfo = @getimagesize($file['tmp_name']);
    if ($imgInfo !== false) {
        list($width, $height, $type) = $imgInfo;
        if ($width > 0 && $height > 0) {
            $ratio = $width / $height;
            if ($width > $max_dim || $height > $max_dim) {
                if ($width > $height) {
                    $new_width = $max_dim;
                    $new_height = $max_dim / $ratio;
                } else {
                    $new_height = $max_dim;
                    $new_width = $max_dim * $ratio;
                }
            } else {
                $new_width = $width;
                $new_height = $height;
            }

            $src = null;
            switch ($type) {
                case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($file['tmp_name']); break;
                case IMAGETYPE_PNG:  $src = @imagecreatefrompng($file['tmp_name']); break;
                case IMAGETYPE_WEBP: $src = @imagecreatefromwebp($file['tmp_name']); break;
            }

            if ($src) {
                $dst = imagecreatetruecolor((int)$new_width, (int)$new_height);
                if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP) {
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                    $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
                    imagefilledrectangle($dst, 0, 0, (int)$new_width, (int)$new_height, $transparent);
                }
                
                
                if ($type == IMAGETYPE_JPEG && function_exists('exif_read_data')) {
                    $exif = @exif_read_data($file['tmp_name']);
                    if (!empty($exif['Orientation'])) {
                        switch ($exif['Orientation']) {
                            case 3: $src = imagerotate($src, 180, 0); break;
                            case 6: $src = imagerotate($src, -90, 0); list($new_width, $new_height) = [$new_height, $new_width]; $dst = imagecreatetruecolor((int)$new_width, (int)$new_height); break;
                            case 8: $src = imagerotate($src, 90, 0); list($new_width, $new_height) = [$new_height, $new_width]; $dst = imagecreatetruecolor((int)$new_width, (int)$new_height); break;
                        }
                    }
                }

                imagecopyresampled($dst, $src, 0, 0, 0, 0, (int)$new_width, (int)$new_height, imagesx($src), imagesy($src));

                switch ($type) {
                    case IMAGETYPE_JPEG: $success = imagejpeg($dst, $absPath, $quality); break;
                    case IMAGETYPE_PNG:  $success = imagepng($dst, $absPath, 8); break;
                    case IMAGETYPE_WEBP: $success = imagewebp($dst, $absPath, $quality); break;
                }
                imagedestroy($src);
                imagedestroy($dst);
            }
        }
    }

    if (!$success) {
        if (!move_uploaded_file($file['tmp_name'], $absPath)) return [null, 'Gagal menyimpan file'];
    }
    return [$relDir . $filename, null];
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

  if (!$rel) return '';
  if (preg_match('#^(?:https?:)?//#', $rel) || strpos($rel, 'data:') === 0) {
    return $rel;
  }

  $originalRel = $rel;
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
        'ATTENDANCE missing asset: %s (resolved: %s, request: %s)',
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
function timeOnly($dt) { return $dt ? date('H:i', strtotime($dt)) : '-'; }

function resolveCompanySettingsSchema($pdo) {
  static $schema = null;

  if ($schema !== null) {
    return $schema;
  }

  $schema = [
    'available' => false,
    'key_column' => null,
    'has_setting_type' => false,
    'has_description' => false,
    'has_created_at' => false,
    'has_updated_at' => false,
  ];

  try {
    $stmt = $pdo->query("SHOW COLUMNS FROM company_settings");
    $columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $field = $row['Field'] ?? null;
      if ($field) {
        $columns[] = $field;
      }
    }

    if (!$columns) {
      return $schema;
    }

    $schema['available'] = true;
    if (in_array('setting_key', $columns, true)) {
      $schema['key_column'] = 'setting_key';
    } elseif (in_array('setting_name', $columns, true)) {
      $schema['key_column'] = 'setting_name';
    }

    $schema['has_setting_type'] = in_array('setting_type', $columns, true);
    $schema['has_description'] = in_array('description', $columns, true);
    $schema['has_created_at'] = in_array('created_at', $columns, true);
    $schema['has_updated_at'] = in_array('updated_at', $columns, true);
  } catch (Exception $e) {
    error_log('Attendance schema detection failed for company_settings: ' . $e->getMessage());
  }

  return $schema;
}

function redirectToAttendanceTab() {
  $isNightRequest = ((string)($_POST['override_schedule'] ?? '') === 'night') || ((string)($_GET['night'] ?? '') === '1');
  $target = 'dashboard.php?page=absence&tab=attendance' . ($isNightRequest ? '&night=1' : '&night=0');
  if (!headers_sent()) {
    header('Location: ' . $target);
    exit;
  } else {
    
    
    echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target) . '">';
    echo '<script>window.location.replace(' . json_encode($target) . ');</script>';
    
    $GLOBALS['__attendance_redirect'] = true;
    return; 
  }
}

function canApplyRetrospectiveSickLeave($date, $gracePeriodDays) {
    $dateTimestamp = strtotime($date);
    $today = strtotime(date('Y-m-d'));
    $daysDiff = ($today - $dateTimestamp) / (60 * 60 * 24);
    
    
    return $daysDiff >= 0 && $daysDiff <= $gracePeriodDays;
}

function getAlphaDaysInRange($pdo, $userId, $startDate, $endDate) {
    $alphaDays = [];
    $stmt = $pdo->prepare('SELECT attendance_date, status FROM attendances WHERE user_id = ? AND attendance_date BETWEEN ? AND ? AND status = "Alpha"');
    $stmt->execute([$userId, $startDate, $endDate]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $alphaDays[] = $row['attendance_date'];
    }
    
    return $alphaDays;
}

function createAlphaAttendanceIfMissing($pdo, $userId, $date) {
    try {
        
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM attendances WHERE user_id = ? AND attendance_date = ?');
        $stmt->execute([$userId, $date]);
        $exists = (int)$stmt->fetchColumn() > 0;
        
        if (!$exists) {
            
            $stmt = $pdo->prepare('SELECT lr.*, u.full_name FROM leave_requests lr JOIN users u ON lr.user_id = u.id WHERE lr.user_id = ? AND ? BETWEEN lr.start_date AND lr.end_date AND lr.status = "approved" LIMIT 1');
            $stmt->execute([$userId, $date]);
            $approvedLeave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($approvedLeave) {
                
                $status = $approvedLeave['type'] === 'permission' ? 'Izin' : 'Sakit';
                $notes = $status === 'Sakit' ? ($approvedLeave['reason'] ?: 'Sakit') : ($approvedLeave['reason'] ?: 'Izin');
                
                $stmt = $pdo->prepare('INSERT INTO attendances (user_id, attendance_date, today_plan, check_in_time, check_out_time, check_in_photo, check_out_photo, check_out_location, notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                $stmt->execute([
                    $userId, 
                    $date, 
                    '', 
                    '0000-00-00 00:00:00', 
                    null, 
                    $approvedLeave['proof_path'] ?: '', 
                    null, 
                    '', 
                    $notes, 
                    $status 
                ]);
                return true;
            }
            
            
            $stmt = $pdo->prepare('INSERT INTO attendances (user_id, attendance_date, today_plan, check_in_time, check_out_time, check_in_photo, check_out_photo, check_out_location, notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([
                $userId, 
                $date, 
                '', 
                '0000-00-00 00:00:00', 
                null, 
                '', 
                null, 
                '', 
                'Tidak hadir tanpa keterangan', 
                'Alpha' 
            ]);
            return true;
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

function autoCreateAlphaForAllStaff($pdo, $date) {
    try {
        
        $dayOfWeek = date('w', strtotime($date));
        if ($dayOfWeek == 0 || $dayOfWeek == 6) {
            return false; 
        }

        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role NOT IN ('administrator','direktur') AND is_active = 1");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $created = 0;
        foreach ($users as $user) {
            if (createAlphaAttendanceIfMissing($pdo, $user['id'], $date)) {
                $created++;
            }
        }
        return $created;
    } catch (Exception $e) {
        error_log('Error in autoCreateAlphaForAllStaff: ' . $e->getMessage());
        return false;
    }
}



function detectLeaveConflict($pdo, $userId, $date) {
    
    $stmt = $pdo->prepare("
        SELECT lr.*, u.full_name, u.username
        FROM leave_requests lr
        JOIN users u ON lr.user_id = u.id
        WHERE lr.user_id = ?
        AND lr.status = 'approved'
        AND ? BETWEEN lr.start_date AND lr.end_date
        LIMIT 1
    ");
    $stmt->execute([$userId, $date]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function handleLeaveConflict($pdo, $leaveRequest, $currentDate, $userId) {
    try {
        $leaveId = $leaveRequest['id'];
        $startDate = $leaveRequest['start_date'];
        $endDate = $leaveRequest['end_date'];

        
        if ($startDate === $endDate) {
            $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$leaveId]);

            logLeaveConflictResolution($pdo, $leaveId, $currentDate, 'auto_cancel_single_day', $userId, 'Leave cancelled automatically - user checked in');

            return [
                'action' => 'cancelled',
                'message' => 'Izin Anda untuk hari ini telah dibatalkan karena Anda sudah melakukan check-in.'
            ];
        }
        else {
            $newEndDate = date('Y-m-d', strtotime($currentDate . ' -1 day'));

            if ($newEndDate >= $startDate) {
                $stmt = $pdo->prepare("UPDATE leave_requests SET end_date = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newEndDate, $leaveId]);

                logLeaveConflictResolution($pdo, $leaveId, $currentDate, 'auto_partial_cancel', $userId, 'Leave end date adjusted - user checked in on ' . $currentDate);

                return [
                    'action' => 'adjusted',
                    'message' => 'Izin Anda telah disesuaikan. Sekarang berlaku sampai ' . date('d M Y', strtotime($newEndDate)) . '.'
                ];
            } else {
                $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$leaveId]);

                logLeaveConflictResolution($pdo, $leaveId, $currentDate, 'auto_cancel_edge_case', $userId, 'Leave cancelled - edge case');

                return [
                    'action' => 'cancelled',
                    'message' => 'Izin Anda untuk hari ini telah dibatalkan karena Anda sudah melakukan check-in.'
                ];
            }
        }
    } catch (Exception $e) {
        error_log('Error handling leave conflict: ' . $e->getMessage());
        return [
            'action' => 'error',
            'message' => 'Terjadi kesalahan saat memproses izin Anda.'
        ];
    }
}

function logLeaveConflictResolution($pdo, $leaveId, $conflictDate, $resolutionType, $resolvedBy, $notes) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS leave_conflicts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                leave_request_id INT NOT NULL,
                conflict_date DATE NOT NULL,
                resolution_type ENUM('auto_cancel_single_day', 'auto_partial_cancel', 'auto_cancel_edge_case', 'manual_override', 'keep_leave') NOT NULL,
                resolved_by INT NOT NULL,
                resolved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                notes TEXT,
                FOREIGN KEY (leave_request_id) REFERENCES leave_requests(id),
                FOREIGN KEY (resolved_by) REFERENCES users(id)
            )
        ");

        $stmt = $pdo->prepare("
            INSERT INTO leave_conflicts (leave_request_id, conflict_date, resolution_type, resolved_by, notes)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$leaveId, $conflictDate, $resolutionType, $resolvedBy, $notes]);
    } catch (Exception $e) {
        error_log('Error logging leave conflict resolution: ' . $e->getMessage());
    }
}

function notifyAdminOfLeaveConflict($pdo, $leaveRequest, $conflictDate, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role IN ('administrator','direktur')");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($admins as $admin) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS notifications (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    type VARCHAR(50) NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    message TEXT,
                    is_read BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            ");

            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, created_at)
                VALUES (?, 'leave_conflict', 'Konflik Izin Terdeteksi', ?, NOW())
            ");

            $message = "Konflik deteksi: {$leaveRequest['full_name']} sudah check-in pada {$conflictDate} padahal memiliki izin approved.";
            $stmt->execute([$admin['id'], $message]);
        }
    } catch (Exception $e) {
        error_log('Error notifying admin of leave conflict: ' . $e->getMessage());
    }
}

$today = date('Y-m-d');
$todayOriginal = $today; 

$dayOfWeek = (int)date('w');
$workHoursRow = null;
$companySettingsSchema = resolveCompanySettingsSchema($pdo);
try {
  if (!empty($companySettingsSchema['key_column'])) {
    $keyColumn = $companySettingsSchema['key_column'];
    $whStmt = $pdo->prepare("SELECT setting_value FROM company_settings WHERE {$keyColumn} = ?");
    $whStmt->execute(["work_hours_$dayOfWeek"]);
    $whVal = $whStmt->fetchColumn();
    if ($whVal) $workHoursRow = json_decode($whVal, true);
  }
} catch (Exception $e) {  }

$lateToleranceMinutes = 0;
$earlyLeaveToleranceMinutes = 0;
$overtimeThresholdSetting = null;

try {
  if (!empty($companySettingsSchema['key_column'])) {
    $keyColumn = $companySettingsSchema['key_column'];
    $extraSettingsStmt = $pdo->prepare("SELECT {$keyColumn} AS setting_key, setting_value FROM company_settings WHERE {$keyColumn} IN ('attendance_late_tolerance_minutes', 'attendance_early_leave_tolerance_minutes', 'attendance_overtime_threshold_time')");
    $extraSettingsStmt->execute();

    while ($settingRow = $extraSettingsStmt->fetch(PDO::FETCH_ASSOC)) {
      $settingKey = $settingRow['setting_key'] ?? '';
      $settingValue = trim((string)($settingRow['setting_value'] ?? ''));

      if ($settingKey === 'attendance_late_tolerance_minutes') {
        $lateToleranceMinutes = max(0, min(180, (int)$settingValue));
      } elseif ($settingKey === 'attendance_early_leave_tolerance_minutes') {
        $earlyLeaveToleranceMinutes = max(0, min(180, (int)$settingValue));
      } elseif ($settingKey === 'attendance_overtime_threshold_time') {
        if (preg_match('/^\d{2}:\d{2}$/', $settingValue)) {
          $overtimeThresholdSetting = $settingValue . ':00';
        } elseif (preg_match('/^\d{2}:\d{2}:\d{2}$/', $settingValue)) {
          $overtimeThresholdSetting = $settingValue;
        }
      }
    }
  }
} catch (Exception $e) {  }


$nightStartConf            = '18:00:00'; 
$nightEndConf              = '06:00:00'; 
$nightOpenMinsConf         = 120;        
$nightOnTimeOffsetMinsConf = 60;         
try {
  if (!empty($companySettingsSchema['key_column'])) {
    $keyColumn = $companySettingsSchema['key_column'];
    $ns = $pdo->prepare("SELECT {$keyColumn} AS setting_key, setting_value FROM company_settings WHERE {$keyColumn} IN ('night_shift_start_time','night_shift_end_time','night_checkin_open_mins','night_on_time_offset_minutes')");
    $ns->execute();
    while ($r = $ns->fetch(PDO::FETCH_ASSOC)) {
      $k = $r['setting_key'] ?? '';
      $v = trim((string)($r['setting_value'] ?? ''));
      if ($k === 'night_shift_start_time') {
        
        if (preg_match('/^(\d{2}):(\d{2})(?::\d{2})?$/', $v, $m) && (int)$m[1] >= 12) {
          $nightStartConf = sprintf('%02d:%02d:00', (int)$m[1], (int)$m[2]);
        }
      } elseif ($k === 'night_shift_end_time') {
        
        if (preg_match('/^(\d{2}):(\d{2})(?::\d{2})?$/', $v, $m) && (int)$m[1] <= 11) {
          $nightEndConf = sprintf('%02d:%02d:00', (int)$m[1], (int)$m[2]);
        }
      } elseif ($k === 'night_checkin_open_mins') {
        if (ctype_digit($v)) $nightOpenMinsConf = max(0, min(300, (int)$v));
      } elseif ($k === 'night_on_time_offset_minutes') {
        if (ctype_digit($v)) $nightOnTimeOffsetMinsConf = max(0, min(300, (int)$v));
      }
    }
  }
} catch (Exception $e) { }

$workStartTime = ($workHoursRow && isset($workHoursRow['start']) && $workHoursRow['start'] !== 'off') ? $workHoursRow['start'] . ':00' : '08:30:00';
$WORK_END_TIME = ($workHoursRow && isset($workHoursRow['end']) && $workHoursRow['end'] !== 'off') ? $workHoursRow['end'] . ':00' : '17:30:00';
$baseWorkStartTime = $workStartTime;
$baseWorkEndTime = $WORK_END_TIME;

$ON_TIME_LIMIT = date('H:i:s', strtotime($workStartTime) + ($lateToleranceMinutes * 60));
$OVERTIME_THRESHOLD = $overtimeThresholdSetting ?: date('H:i:s', strtotime($WORK_END_TIME) + 1800);
$OVERTIME_THRESHOLD_DISPLAY = substr($OVERTIME_THRESHOLD, 0, 5);

$SCHEDULED_START_DT = $today . ' ' . $workStartTime;
$SCHEDULED_END_DT = $today . ' ' . $WORK_END_TIME;
$RESOLVED_SHIFT_ID = null;
$IS_CROSS_MIDNIGHT = 0;
$CHECKIN_OPEN_MINS = 60;   
$CHECKIN_CLOSE_MINS = 120; 


$nightOverride = ((string)($_GET['night'] ?? '') === '1')
  || ((string)($_POST['override_schedule'] ?? '') === 'night')
  || (isset($activeTab) && $activeTab === 'night_attendance');



$nowTsForNightWindow = time();
$nightWindowTodayOpenTs = strtotime($todayOriginal . ' ' . $nightStartConf);
$nightWindowTodayCloseTs = strtotime(date('Y-m-d', strtotime($todayOriginal . ' +1 day')) . ' ' . $nightEndConf);
$nightWindowYesterdayOpenTs = strtotime(date('Y-m-d', strtotime($todayOriginal . ' -1 day')) . ' ' . $nightStartConf);
$nightWindowYesterdayCloseTs = strtotime($todayOriginal . ' ' . $nightEndConf);
$isNightAttendanceWindowOpen = (
  ($nightWindowTodayOpenTs && $nightWindowTodayCloseTs && $nowTsForNightWindow >= $nightWindowTodayOpenTs && $nowTsForNightWindow <= $nightWindowTodayCloseTs)
  || ($nightWindowYesterdayOpenTs && $nightWindowYesterdayCloseTs && $nowTsForNightWindow >= $nightWindowYesterdayOpenTs && $nowTsForNightWindow <= $nightWindowYesterdayCloseTs)
);
if ($nightOverride && !$isNightAttendanceWindowOpen) {
  $nightOverride = false;
  $_SESSION['abs_mode'] = 'day';
}



$nightShiftEffectiveDateAdjusted = false;
if ($nightOverride) {
  $currentHour = (int)date('G'); 
  $nightEndHour = (int)substr($nightEndConf, 0, 2); 
  if ($currentHour < $nightEndHour) {
    
    $today = date('Y-m-d', strtotime('-1 day'));
    $nightShiftEffectiveDateAdjusted = true;
  }
}

if ($nightShiftEffectiveDateAdjusted) {
  try {
    if (!empty($companySettingsSchema['key_column'])) {
      $keyColumn = $companySettingsSchema['key_column'];
      $effectiveDayOfWeek = (int)date('w', strtotime($today));
      $whStmt = $pdo->prepare("SELECT setting_value FROM company_settings WHERE {$keyColumn} = ?");
      $whStmt->execute(["work_hours_$effectiveDayOfWeek"]);
      $whVal = $whStmt->fetchColumn();
      if ($whVal) {
        $workHoursRow = json_decode($whVal, true);
        $workStartTime = ($workHoursRow && isset($workHoursRow['start']) && $workHoursRow['start'] !== 'off') ? $workHoursRow['start'] . ':00' : $workStartTime;
        $WORK_END_TIME = ($workHoursRow && isset($workHoursRow['end']) && $workHoursRow['end'] !== 'off') ? $workHoursRow['end'] . ':00' : $WORK_END_TIME;
        $baseWorkStartTime = $workStartTime;
        $baseWorkEndTime = $WORK_END_TIME;
      }
    }
  } catch (Exception $e) { }
}

if ($nightOverride) {
  
  
  
  
  $SCHEDULED_START_DT = $today . ' ' . $nightStartConf;
  $SCHEDULED_END_DT = date('Y-m-d', strtotime($today . ' +1 day')) . ' ' . $nightEndConf;
  $workStartTime = $nightStartConf;
  $WORK_END_TIME = $nightEndConf;
  $IS_CROSS_MIDNIGHT = 1;
  $CHECKIN_OPEN_MINS = $nightOpenMinsConf;
  
  $ON_TIME_LIMIT = date('H:i:s', strtotime($SCHEDULED_START_DT) + ($lateToleranceMinutes * 60));
  $OVERTIME_THRESHOLD = $overtimeThresholdSetting ?: date('H:i:s', strtotime($SCHEDULED_END_DT) + (30 * 60));
  $OVERTIME_THRESHOLD_DISPLAY = substr($OVERTIME_THRESHOLD, 0, 5);
}


if (!isset($user) || empty($user)) {
    if (isset($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {   }
    }
}
$currentUserId = (int)($user['id'] ?? ($_SESSION['user_id'] ?? 0));

try {
  $hasWorkShifts = false;
  $hasPerDayAssign = false;
  $hasNightFlags = false;
  $chk1 = $pdo->query("SHOW TABLES LIKE 'work_shifts'");
  $hasWorkShifts = ($chk1 && $chk1->fetchColumn()) ? true : false;
  $chk2 = $pdo->query("SHOW TABLES LIKE 'user_shift_assignments'");
  $hasPerDayAssign = ($chk2 && $chk2->fetchColumn()) ? true : false;
  $chk3 = $pdo->query("SHOW TABLES LIKE 'user_night_shift_flags'");
  $hasNightFlags = ($chk3 && $chk3->fetchColumn()) ? true : false;

  if ($hasWorkShifts && !empty($currentUserId)) {
    $shift = null;

    
    
    
    if ($nightOverride && $hasNightFlags) {
      $ns = $pdo->prepare("SELECT is_active, custom_start, custom_end FROM user_night_shift_flags WHERE user_id = ? LIMIT 1");
      $ns->execute([$currentUserId]);
      $flag = $ns->fetch(PDO::FETCH_ASSOC) ?: null;
      if ($flag && (int)($flag['is_active'] ?? 0) === 1) {
        $wsNight = $pdo->prepare("SELECT id AS shift_id, code, name, default_start, default_end, cross_midnight, grace_minutes, early_leave_grace, overtime_grace, checkin_open_mins, checkin_close_mins FROM work_shifts WHERE code='NIGHT' LIMIT 1");
        $wsNight->execute();
        $shift = $wsNight->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($shift) {
          $shift['custom_start'] = $flag['custom_start'] ?? null;
          $shift['custom_end'] = $flag['custom_end'] ?? null;
          $shift['shift_date'] = $today;
        }
      }
    }

    
    if (!$shift && $hasPerDayAssign) {
      $stmt = $pdo->prepare("SELECT usa.shift_date, usa.custom_start, usa.custom_end,
                                     ws.id AS shift_id, ws.code, ws.name,
                                     ws.default_start, ws.default_end, ws.cross_midnight,
                                     ws.grace_minutes, ws.early_leave_grace, ws.overtime_grace,
                                     ws.checkin_open_mins, ws.checkin_close_mins
                              FROM user_shift_assignments usa
                              JOIN work_shifts ws ON ws.id = usa.shift_id
                              WHERE usa.user_id = ? AND usa.shift_date = ?
                              LIMIT 1");
      $stmt->execute([$currentUserId, $today]);
      $shift = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if ($shift) {
      
      $sStart = $shift['custom_start'] ?: ($shift['default_start'] ?: $workStartTime);
      $sEnd   = $shift['custom_end']   ?: ($shift['default_end']   ?: $WORK_END_TIME);
      if ((strtoupper((string)($shift['code'] ?? '')) === 'NIGHT') && (!$shift['custom_start'] && !$shift['default_start'])) {
        $sStart = '20:00:00';
      }
      if ((strtoupper((string)($shift['code'] ?? '')) === 'NIGHT') && (!$shift['custom_end'] && !$shift['default_end'])) {
        $sEnd = '05:00:00';
      }

      
      $resolvedStartDT = $today . ' ' . $sStart;
      $resolvedEndDT   = $today . ' ' . $sEnd;
      $cross = (int)($shift['cross_midnight'] ?? 0) === 1;
      if ($cross || strtotime($sEnd) <= strtotime($sStart)) {
        $resolvedEndDT = date('Y-m-d', strtotime($today . ' +1 day')) . ' ' . $sEnd;
      }

      $isResolvedNightShift = strtoupper((string)($shift['code'] ?? '')) === 'NIGHT';

      
      if (!$nightOverride || $isResolvedNightShift) {
        $workStartTime = date('H:i:s', strtotime($resolvedStartDT));
        $WORK_END_TIME = date('H:i:s', strtotime($resolvedEndDT));
      }

      
      if (!$nightOverride || $isResolvedNightShift) {
        $SCHEDULED_START_DT = $resolvedStartDT;
        $SCHEDULED_END_DT = $resolvedEndDT;
      }
      if (isset($shift['shift_id'])) { $RESOLVED_SHIFT_ID = (int)$shift['shift_id']; }
      
      
      $IS_CROSS_MIDNIGHT = ($nightOverride && $cross) ? 1 : 0;

      
      $shiftLateGrace = array_key_exists('grace_minutes', $shift) && $shift['grace_minutes'] !== null ? max(0, (int)$shift['grace_minutes']) : $lateToleranceMinutes;
      $shiftOverGrace = array_key_exists('overtime_grace', $shift) && $shift['overtime_grace'] !== null ? max(0, (int)$shift['overtime_grace']) : 30;
      if (!$nightOverride) {
        $CHECKIN_OPEN_MINS = array_key_exists('checkin_open_mins', $shift) && $shift['checkin_open_mins'] !== null ? max(0, (int)$shift['checkin_open_mins']) : $CHECKIN_OPEN_MINS;
      }
      $CHECKIN_CLOSE_MINS = array_key_exists('checkin_close_mins', $shift) && $shift['checkin_close_mins'] !== null ? max(0, (int)$shift['checkin_close_mins']) : $CHECKIN_CLOSE_MINS;
      $lateToleranceMinutes = $shiftLateGrace;

      
      if (!$nightOverride || $isResolvedNightShift) {
        $ON_TIME_LIMIT = date('H:i:s', strtotime($SCHEDULED_START_DT) + ($shiftLateGrace * 60));
        $OVERTIME_THRESHOLD = date('H:i:s', strtotime($SCHEDULED_END_DT) + ($shiftOverGrace * 60));
        $OVERTIME_THRESHOLD_DISPLAY = substr($OVERTIME_THRESHOLD, 0, 5);
      }
    }
  }
} catch (Throwable $e) {
  
}




$allWorkHours = [];
try {
  if (!empty($companySettingsSchema['key_column'])) {
    $keyColumn = $companySettingsSchema['key_column'];
    $whAllStmt = $pdo->query("SELECT {$keyColumn} AS setting_key, setting_value FROM company_settings WHERE {$keyColumn} LIKE 'work_hours_%'");
    while ($r = $whAllStmt->fetch(PDO::FETCH_ASSOC)) {
      $d = (int)str_replace('work_hours_', '', $r['setting_key']);
      $allWorkHours[$d] = json_decode($r['setting_value'], true);
    }
    }
} catch (Exception $e) {   }


$GRACE_PERIOD_DAYS = 3;


if (!isset($user) || empty($user)) {
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$currentUserId = (int)($user['id'] ?? ($_SESSION['user_id'] ?? $currentUserId ?? 0));
$currentUserName = $user['full_name'] ?? ($user['username'] ?? 'User');
$currentUserRole = $user['role'] ?? '';

$isAdministrator = $currentUserRole === 'administrator';
$isDirektur = $currentUserRole === 'direktur';
$isTechnicianManager = $currentUserRole === 'technician_manager';

$canManageAttendanceOverview = $isAdministrator || $isDirektur;
$canManageManualAttendance = $isAdministrator || $isDirektur;
$canManageWorkHours = $isAdministrator;
$canManageAttendanceReset = $isAdministrator || $isDirektur || $isTechnicianManager;

$attendanceContainerMaxWidth = $canManageAttendanceOverview ? '1080px' : '672px';


$myAttendance = null;
try {
    $stmt = $pdo->prepare('SELECT * FROM attendances WHERE user_id = ? AND attendance_date = ? LIMIT 1');
    $stmt->execute([$currentUserId, $today]);
    $myAttendance = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Exception $e) {   }

function isAttendanceNightShiftRowLocal($row, $nightStart = '18:00:00', $nightEnd = '06:00:00') {
    if (!$row) return false;
    
    
    
    $checkIn = $row['check_in_time'] ?? '';
    $hasRealCheckIn = ($checkIn && $checkIn !== '0000-00-00 00:00:00');
    if (!$hasRealCheckIn) return false; 

    
    
    
    
    if (empty($row['is_cross_midnight']) || (int)$row['is_cross_midnight'] !== 1) {
        return false;
    }

    $checkInTime = date('H:i:s', strtotime($checkIn));
    $nightStartTime = strlen($nightStart) === 5 ? $nightStart . ':00' : $nightStart;
    $nightEndTime = strlen($nightEnd) === 5 ? $nightEnd . ':00' : $nightEnd;

    
    if (strtotime($nightEndTime) <= strtotime($nightStartTime)) {
        return ($checkInTime >= $nightStartTime) || ($checkInTime < $nightEndTime);
    }

    return ($checkInTime >= $nightStartTime) && ($checkInTime < $nightEndTime);
}

$oppositeShiftAttendanceToday = null;
try {
    
    
    
    $oppositeStmt = $pdo->prepare(
        'SELECT * FROM attendances
         WHERE user_id = ? AND attendance_date = ?
         AND check_in_time IS NOT NULL
         AND check_in_time <> "0000-00-00 00:00:00"
         AND status NOT IN ("Alpha")
         AND is_cross_midnight = 1
         LIMIT 1'
    );
    $oppositeStmt->execute([$currentUserId, $todayOriginal]);
    $oppositeShiftAttendanceToday = $oppositeStmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Exception $e) {   }

$errors = [];
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['override_schedule'] ?? '') === 'night' && !$isNightAttendanceWindowOpen) {
    $errors[] = 'Absen malam belum dibuka. Jadwal absen malam hanya ' . substr($nightStartConf, 0, 5) . ' - ' . substr($nightEndConf, 0, 5) . '.';
}


if (isset($_SESSION['flash_messages']) && is_array($_SESSION['flash_messages'])) {
    $messages = array_merge($messages, $_SESSION['flash_messages']);
    unset($_SESSION['flash_messages']);
}


if (isset($_SESSION['flash_errors']) && is_array($_SESSION['flash_errors'])) {
    $errors = array_merge($errors, $_SESSION['flash_errors']);
    unset($_SESSION['flash_errors']);
}



try {
    $alphaCheckKey = '_alpha_created_' . $todayOriginal;
    if (empty($_SESSION[$alphaCheckKey])) {
        autoCreateAlphaForAllStaff($pdo, $todayOriginal);
        $_SESSION[$alphaCheckKey] = true;
    }
} catch (Exception $e) {
    error_log('Error calling autoCreateAlphaForAllStaff: ' . $e->getMessage());
}


$leaveStatuses = ['Izin', 'Sakit', 'Cuti'];
$hasLeaveToday = $myAttendance && in_array($myAttendance['status'] ?? '', $leaveStatuses, true);
$hasAttendanceTrackToday = $myAttendance && !$hasLeaveToday && !empty($myAttendance['check_in_time']) && $myAttendance['check_in_time'] !== '0000-00-00 00:00:00'; 
$hasAlphaToday = $myAttendance && ($myAttendance['status'] ?? '') === 'Alpha'; 
$dayLockedType = $hasLeaveToday ? ($myAttendance['status'] ?? '') : ($hasAttendanceTrackToday ? 'Attendance' : ($hasAlphaToday ? 'Alpha' : ''));


$hasPendingLeaveRequest = false;
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM leave_requests WHERE user_id = ? AND status = "pending"');
    $stmt->execute([$currentUserId]);
    $hasPendingLeaveRequest = ((int)$stmt->fetchColumn()) > 0;
} catch (Exception $e) {   }



$canAccessLeaveTabs = !$hasPendingLeaveRequest;

try {
    
    if (empty($_SESSION['_attendance_requests_table_checked'])) {
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
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            decided_by INT DEFAULT NULL,
            decided_at DATETIME DEFAULT NULL,
            attendance_id INT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_attendance_requests_user_date (user_id, attendance_date),
            INDEX idx_attendance_requests_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        try {
            $pdo->exec("ALTER TABLE attendance_requests ADD COLUMN IF NOT EXISTS requested_check_in_time TIME DEFAULT NULL");
        } catch (Throwable $__ex) {   }
        try {
            $pdo->exec("ALTER TABLE attendance_requests ADD COLUMN IF NOT EXISTS request_type VARCHAR(30) NOT NULL DEFAULT 'checkin'");
        } catch (Throwable $__ex) {   }
        try {
            $pdo->exec("ALTER TABLE attendance_requests ADD COLUMN IF NOT EXISTS requested_check_out_time TIME DEFAULT NULL");
        } catch (Throwable $__ex) {   }
        try {
            $pdo->exec("ALTER TABLE attendance_requests ADD COLUMN IF NOT EXISTS missed_checkout_date DATE DEFAULT NULL");
        } catch (Throwable $__ex) {   }
        $_SESSION['_attendance_requests_table_checked'] = true;
    }
} catch (Exception $e) {
    error_log('Failed ensuring attendance_requests table: ' . $e->getMessage());
}

$hasPendingAttendanceRequest = false;
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM attendance_requests WHERE user_id = ? AND status = "pending"');
    $stmt->execute([$currentUserId]);
    $hasPendingAttendanceRequest = ((int)$stmt->fetchColumn()) > 0;
} catch (Exception $e) {   }



$hasMissedCheckoutPrevDay = false;
$missedCheckoutDate = null;
$missedCheckoutRow = null;

if (!$canManageAttendanceOverview) {
    try {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $missedCheckStmt = $pdo->prepare(
            'SELECT id, attendance_date, check_in_time, check_out_time, status
             FROM attendances
             WHERE user_id = ?
               AND attendance_date = ?
               AND check_in_time IS NOT NULL
               AND check_in_time <> "0000-00-00 00:00:00"
               AND (check_out_time IS NULL OR check_out_time = "0000-00-00 00:00:00")
               AND status NOT IN ("Izin","Sakit","Cuti","Alpha","Libur")
               AND DAYOFWEEK(attendance_date) NOT IN (1, 7)
             LIMIT 1'
        );
        $missedCheckStmt->execute([$currentUserId, $yesterday]);
        $missedCheckoutRow = $missedCheckStmt->fetch(PDO::FETCH_ASSOC);

        if ($missedCheckoutRow) {
            $hasMissedCheckoutPrevDay = true;
            $missedCheckoutDate = $missedCheckoutRow['attendance_date'];

            
            try {
                $arApprStmt = $pdo->prepare(
                    'SELECT COUNT(*) FROM attendance_requests
                     WHERE user_id = ? AND missed_checkout_date = ? AND request_type = "missed_checkout" AND status = "approved"'
                );
                $arApprStmt->execute([$currentUserId, $missedCheckoutDate]);
                if (((int)$arApprStmt->fetchColumn()) > 0) {
                    $hasMissedCheckoutPrevDay = false; 
                }
            } catch (Throwable $e) {   }
        }
    } catch (Exception $e) {   }
}



$activeTab = $_GET['tab'] ?? ($_POST['tab'] ?? 'attendance');
$validTabs = ['attendance', 'attendance_request', 'permission', 'sick_leave', 'admin_manual_attendance', 'save_work_hours', 'reset_night_shift'];
if (!in_array($activeTab, $validTabs)) {
  $activeTab = 'attendance';
}

if ($canManageAttendanceOverview && in_array($activeTab, ['attendance_request', 'permission', 'sick_leave'], true)) {
    $activeTab = 'attendance';
}


$hasStartedAttendance = $hasAttendanceTrackToday;
$isAttendanceComplete = $hasStartedAttendance && !empty($myAttendance['check_out_time']);


if ($hasStartedAttendance && $activeTab === 'permission') {
    $activeTab = 'attendance';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  
  // Only process POST if the tab matches (prevent browser resubmission issues)
  $postedTab = $_POST['tab'] ?? '';
  if ($postedTab !== '' && $postedTab !== $activeTab) {
    // Mismatch: user navigated to different tab but browser resubmitted old POST
    // Ignore the POST data
    goto skip_post_processing;
  }

  $csrfToken = $_POST['csrf_token'] ?? '';
  if ($csrfToken !== '') {
    if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrfToken)) {
      $errors[] = 'Token keamanan tidak valid. Silakan muat ulang halaman dan coba lagi.';
    }
  }

  if (empty($errors) && $activeTab === 'admin_manual_attendance' && $canManageManualAttendance) {
    
    $editAttId = (int)($_POST['edit_attendance_id'] ?? 0);
    if ($editAttId > 0) {
      $editStatus = trim($_POST['edit_status'] ?? '');
      $editNotes = trim($_POST['edit_notes'] ?? '');
      $editCheckIn = trim($_POST['edit_check_in'] ?? '');
      $editCheckOut = trim($_POST['edit_check_out'] ?? '');
      
      $validEditStatuses = ['Hadir','Terlambat','Izin','Sakit','Cuti','Alpha','Lembur','Not Checked Out'];
      if (!in_array($editStatus, $validEditStatuses)) {
        $errors[] = 'Status tidak valid.';
      }
      if (!$errors) {
        $existing = $pdo->prepare('SELECT attendance_date, user_id FROM attendances WHERE id = ?');
        $existing->execute([$editAttId]);
        $existingRow = $existing->fetch(PDO::FETCH_ASSOC);
        if (!$existingRow) {
          $errors[] = 'Record absensi tidak ditemukan.';
        } else {
          $attDate = $existingRow['attendance_date'];
          $cinVal = $editCheckIn ? ($attDate . ' ' . $editCheckIn . ':00') : null;
          $coutVal = $editCheckOut ? ($attDate . ' ' . $editCheckOut . ':00') : null;
          
          
          
          if ($cinVal && in_array($editStatus, ['Hadir', 'Terlambat'])) {
            $editDayOfWeek = (int)date('w', strtotime($attDate));
            $editWorkStart = $workStartTime; 
            try {
              if (!empty($companySettingsSchema['key_column'])) {
                $whStmtEdit = $pdo->prepare("SELECT setting_value FROM company_settings WHERE {$companySettingsSchema['key_column']} = ?");
                $whStmtEdit->execute(["work_hours_$editDayOfWeek"]);
                $whValEdit = $whStmtEdit->fetchColumn();
                if ($whValEdit) {
                  $whRowEdit = json_decode($whValEdit, true);
                  if ($whRowEdit && isset($whRowEdit['start']) && $whRowEdit['start'] !== 'off') {
                    $editWorkStart = $whRowEdit['start'] . ':00';
                  }
                }
              }
            } catch (Exception $e) {   }
            
            $editOnTimeLimit = date('H:i:s', strtotime($editWorkStart) + ($lateToleranceMinutes * 60));
            $editCheckInTime = date('H:i:s', strtotime($cinVal));
            $editStatus = (strtotime($editCheckInTime) <= strtotime($editOnTimeLimit)) ? 'Hadir' : 'Terlambat';
          }
          
          $stmt = $pdo->prepare('UPDATE attendances SET status = ?, notes = ?, check_in_time = COALESCE(?, check_in_time), check_out_time = COALESCE(?, check_out_time), updated_at = CURRENT_TIMESTAMP WHERE id = ?');
          $stmt->execute([$editStatus, $editNotes, $cinVal, $coutVal, $editAttId]);
          auditLog($pdo, 'edit_attendance', [
              'target_type' => 'attendance',
              'target_id' => $editAttId,
              'target_user_id' => (int)$existingRow['user_id'],
              'details' => ['date' => $attDate, 'status' => $editStatus, 'check_in' => $editCheckIn, 'check_out' => $editCheckOut, 'notes' => $editNotes]
          ]);
          $messages[] = 'Absensi berhasil diperbarui.';
        }
      }
    } else {
    
    $manualUserId = (int)($_POST['manual_user_id'] ?? 0);
    $manualDate = $_POST['manual_attendance_date'] ?? '';
    $manualStatus = $_POST['manual_status'] ?? '';
    $manualNotes = trim($_POST['manual_notes'] ?? '');
    $manualCheckInTime = trim($_POST['manual_check_in_time'] ?? '');
    $manualCheckOutTime = trim($_POST['manual_check_out_time'] ?? '');

    if ($manualCheckInTime !== '') {
      $checkInDate = DateTime::createFromFormat('H:i', $manualCheckInTime);
      $isCheckInValid = $checkInDate && $checkInDate->format('H:i') === $manualCheckInTime;
      if (!$isCheckInValid) {
        $errors[] = 'Format jam check-in manual tidak valid.';
        $manualCheckInTime = '';
      }
    }

    if ($manualCheckOutTime !== '') {
      $checkOutDate = DateTime::createFromFormat('H:i', $manualCheckOutTime);
      $isCheckOutValid = $checkOutDate && $checkOutDate->format('H:i') === $manualCheckOutTime;
      if (!$isCheckOutValid) {
        $errors[] = 'Format jam check-out manual tidak valid.';
        $manualCheckOutTime = '';
      }
    }
    
    if (!$manualUserId) $errors[] = 'Karyawan wajib dipilih.';
    if (!$manualDate) $errors[] = 'Tanggal absensi wajib diisi.';
    if (!$manualStatus) $errors[] = 'Status absensi wajib diisi.';
    
    
    $validStatuses = ['Masuk', 'Izin', 'Sakit'];
    if (!in_array($manualStatus, $validStatuses)) {
      $errors[] = 'Status absensi tidak valid.';
    }
    
    
    $manualPhotoPath = '';
    if (!empty($_FILES['manual_photo']) && $_FILES['manual_photo']['error'] === UPLOAD_ERR_OK) {
      
      $folderName = 'Absen Manual'; 
      if ($manualStatus === 'Izin') {
        $folderName = 'permission';
      } elseif ($manualStatus === 'Sakit') {
        $folderName = 'sick leave';
      }

      [$manualPhotoPath, $photoErr] = saveImage(
        $_FILES['manual_photo'],
        'manual_' . $manualUserId,
        $manualUserId,
        $manualDate,
        'storage/uploads/attendance/' . $manualUserId . '/' . $folderName
      );
      if ($photoErr) {
        $errors[] = 'Gagal upload foto: ' . $photoErr;
      }
    }
    
    
    $defaultCheckIn = '0000-00-00 00:00:00';
    $defaultCheckOut = null;
    $defaultStatus = $manualStatus;
    
    if ($manualStatus === 'Masuk') {
      $defaultStatus = 'Hadir'; 
      if ($manualCheckInTime) {
        $defaultCheckIn = $manualDate . ' ' . $manualCheckInTime . ':00';
      }
      if ($manualCheckOutTime) {
        $defaultCheckOut = $manualDate . ' ' . $manualCheckOutTime . ':00';
      }
    } elseif ($manualStatus === 'Izin') {
      $defaultStatus = 'Izin';
      $defaultCheckIn = '0000-00-00 00:00:00';
      $defaultCheckOut = null;
    } elseif ($manualStatus === 'Sakit') {
      $defaultStatus = 'Sakit';
      $defaultCheckIn = '0000-00-00 00:00:00';
      $defaultCheckOut = null;
    }
    if (!$errors) {
      
      $stmt = $pdo->prepare('SELECT status FROM attendances WHERE user_id = ? AND attendance_date = ?');
      $stmt->execute([$manualUserId, $manualDate]);
      $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);
      
      if ($existingRecord && $existingRecord['status'] !== 'Alpha') {
        $errors[] = 'Absensi untuk karyawan dan tanggal tersebut sudah ada.';
      } elseif ($existingRecord && $existingRecord['status'] === 'Alpha') {
        
        $stmt = $pdo->prepare('UPDATE attendances SET today_plan = ?, check_in_time = ?, check_out_time = ?, check_in_photo = ?, check_out_photo = ?, check_out_location = ?, notes = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND attendance_date = ?');
        $stmt->execute([
          '',
          $manualCheckInTime ? ($manualDate . ' ' . $manualCheckInTime . ':00') : '0000-00-00 00:00:00',
          $manualCheckOutTime ? ($manualDate . ' ' . $manualCheckOutTime . ':00') : null,
          $manualPhotoPath ?: '', 
          null, 
          '', 
          $manualNotes,
          $defaultStatus,
          $manualUserId,
          $manualDate
        ]);
        $messages[] = 'Absensi berhasil diperbarui dari Alpha.';
        auditLog($pdo, 'manual_attendance', [
            'target_type' => 'attendance',
            'target_user_id' => $manualUserId,
            'details' => ['date' => $manualDate, 'status' => $defaultStatus, 'check_in' => $manualCheckInTime, 'check_out' => $manualCheckOutTime, 'notes' => $manualNotes, 'from' => 'Alpha']
        ]);
      } else {
        $stmt = $pdo->prepare('INSERT INTO attendances (user_id, attendance_date, today_plan, check_in_time, check_out_time, check_in_photo, check_out_photo, check_out_location, notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([
          $manualUserId,
          $manualDate,
          '',
          $manualCheckInTime ? ($manualDate . ' ' . $manualCheckInTime . ':00') : '0000-00-00 00:00:00',
          $manualCheckOutTime ? ($manualDate . ' ' . $manualCheckOutTime . ':00') : null,
          $manualPhotoPath ?: '', 
          null, 
          '', 
          $manualNotes,
          $defaultStatus
        ]);
        $messages[] = 'Absensi manual berhasil ditambahkan.';
        auditLog($pdo, 'manual_attendance', [
            'target_type' => 'attendance',
            'target_user_id' => $manualUserId,
            'details' => ['date' => $manualDate, 'status' => $defaultStatus, 'check_in' => $manualCheckInTime, 'check_out' => $manualCheckOutTime, 'notes' => $manualNotes, 'from' => 'new']
        ]);
      }
    }
    } 

    
    if (!empty($messages) && empty($errors)) {
      $_SESSION['flash_messages'] = $messages;
      $redirectUrl = 'dashboard.php?page=absence&tab=attendance';
      if (!headers_sent()) {
        header('Location: ' . $redirectUrl);
        exit;
      }
    }
  }
  elseif ($activeTab === 'save_work_hours' && $canManageWorkHours) {
    
    try {
      $settingsSchema = resolveCompanySettingsSchema($pdo);
      $keyColumn = $settingsSchema['key_column'] ?? null;
      if (!$keyColumn) {
        throw new Exception('Kolom setting_key/setting_name tidak ditemukan di company_settings.');
      }

      $upsertCompanySetting = function($settingKey, $settingValue, $settingType, $description) use ($pdo, $settingsSchema, $keyColumn) {
        $insertColumns = [$keyColumn, 'setting_value'];
        $insertValues = ['?', '?'];
        $params = [$settingKey, $settingValue];

        if (!empty($settingsSchema['has_setting_type'])) {
          $insertColumns[] = 'setting_type';
          $insertValues[] = '?';
          $params[] = $settingType;
        }
        if (!empty($settingsSchema['has_description'])) {
          $insertColumns[] = 'description';
          $insertValues[] = '?';
          $params[] = $description;
        }
        if (!empty($settingsSchema['has_created_at'])) {
          $insertColumns[] = 'created_at';
          $insertValues[] = 'NOW()';
        }
        if (!empty($settingsSchema['has_updated_at'])) {
          $insertColumns[] = 'updated_at';
          $insertValues[] = 'NOW()';
        }

        $sql = "INSERT INTO company_settings (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";
        $sql .= " ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        if (!empty($settingsSchema['has_updated_at'])) {
          $sql .= ", updated_at = NOW()";
        }

        $upsertStmt = $pdo->prepare($sql);
        $upsertStmt->execute($params);
      };

      $dayNames = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
      for ($d = 0; $d <= 6; $d++) {
        $start = trim($_POST["wh_start_$d"] ?? '');
        $end   = trim($_POST["wh_end_$d"] ?? '');
        $isOff = isset($_POST["wh_off_$d"]);

        if ($isOff) {
          $val = json_encode(['start' => 'off', 'end' => 'off', 'label' => $dayNames[$d]]);
        } else {
          
          if (!preg_match('/^\d{2}:\d{2}$/', $start)) $start = '08:30';
          if (!preg_match('/^\d{2}:\d{2}$/', $end))   $end   = '17:30';
          $val = json_encode(['start' => $start, 'end' => $end, 'label' => $dayNames[$d]]);
        }

        $upsertCompanySetting("work_hours_$d", $val, 'json', 'Work hours setting for ' . $dayNames[$d]);
        $allWorkHours[$d] = json_decode($val, true);
      }

      $lateToleranceInput = trim((string)($_POST['wh_late_tolerance_minutes'] ?? $lateToleranceMinutes));
      $earlyToleranceInput = trim((string)($_POST['wh_early_leave_tolerance_minutes'] ?? $earlyLeaveToleranceMinutes));
      $overtimeThresholdInput = trim((string)($_POST['wh_overtime_threshold_time'] ?? $OVERTIME_THRESHOLD_DISPLAY));

      $lateToleranceMinutes = max(0, min(180, ctype_digit($lateToleranceInput) ? (int)$lateToleranceInput : 0));
      $earlyLeaveToleranceMinutes = max(0, min(180, ctype_digit($earlyToleranceInput) ? (int)$earlyToleranceInput : 0));

      if (!preg_match('/^\d{2}:\d{2}$/', $overtimeThresholdInput)) {
        $overtimeThresholdInput = date('H:i', strtotime($WORK_END_TIME) + 1800);
      }

      $upsertCompanySetting('attendance_late_tolerance_minutes', (string)$lateToleranceMinutes, 'number', 'Tolerance minutes before marking attendance as late');
      $upsertCompanySetting('attendance_early_leave_tolerance_minutes', (string)$earlyLeaveToleranceMinutes, 'number', 'Tolerance minutes for early checkout');
      $upsertCompanySetting('attendance_overtime_threshold_time', $overtimeThresholdInput . ':00', 'time', 'Checkout threshold time for overtime status');

      
      $nightStartPost = trim((string)($_POST['night_start_time'] ?? substr($nightStartConf, 0, 5)));
      $nightEndPost   = trim((string)($_POST['night_end_time']   ?? substr($nightEndConf,   0, 5)));
      $nightOpenPost  = trim((string)($_POST['night_checkin_open_mins'] ?? (string)$nightOpenMinsConf));

      
      if (!preg_match('/^(\d{2}):(\d{2})$/', $nightStartPost, $nsm) || (int)$nsm[1] < 12 || (int)$nsm[1] > 23) {
        $nightStartPost = '18:00';
      }
      if (!preg_match('/^(\d{2}):(\d{2})$/', $nightEndPost, $nem) || (int)$nem[1] > 11) {
        $nightEndPost = '06:00';
      }
      $nightOpenVal = ctype_digit($nightOpenPost) ? max(0, min(300, (int)$nightOpenPost)) : $nightOpenMinsConf;

      if (!$errors) {
        $upsertCompanySetting('night_shift_start_time', $nightStartPost . ':00', 'time', 'Global night shift start time');
        $upsertCompanySetting('night_shift_end_time',   $nightEndPost   . ':00', 'time', 'Global night shift end time');
        $upsertCompanySetting('night_checkin_open_mins', (string)$nightOpenVal, 'number', 'Check-in open minutes before night shift start');
        
        $nightStartConf    = $nightStartPost . ':00';
        $nightEndConf      = $nightEndPost   . ':00';
        $nightOpenMinsConf = $nightOpenVal;
      }

      $workStartTime = ($allWorkHours[$dayOfWeek]['start'] ?? '08:30') !== 'off' ? ($allWorkHours[$dayOfWeek]['start'] ?? '08:30') . ':00' : '08:30:00';
      $ON_TIME_LIMIT = date('H:i:s', strtotime($workStartTime) + ($lateToleranceMinutes * 60));
      $OVERTIME_THRESHOLD = $overtimeThresholdInput . ':00';
      $OVERTIME_THRESHOLD_DISPLAY = $overtimeThresholdInput;

      $messages[] = 'Jam kerja, pengaturan lembur/toleransi, dan jadwal Night Shift berhasil disimpan.';
      auditLog($pdo, 'update_work_hours', [
          'target_type' => 'company_settings',
          'details' => ['late_tolerance' => $lateToleranceMinutes, 'early_leave_tolerance' => $earlyLeaveToleranceMinutes, 'overtime_threshold' => $overtimeThresholdInput, 'night_start' => $nightStartPost, 'night_end' => $nightEndPost]
      ]);
    } catch (Exception $e) {
      error_log('Error saving work hours settings: ' . $e->getMessage());
      $errors[] = 'Gagal menyimpan jam kerja. Periksa struktur tabel company_settings atau log aplikasi.';
    }
    $activeTab = 'attendance'; 
  }
  elseif ($activeTab === 'reset_night_shift' && $canManageManualAttendance) {
    
    
    
    $resetUserId   = (int)($_POST['reset_night_user_id'] ?? 0);
    $resetDate     = trim($_POST['reset_night_date'] ?? '');

    if (!$resetUserId) {
      $errors[] = 'Pilih karyawan yang ingin di-reset absen malamnya.';
    }
    if (!$resetDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $resetDate)) {
      $errors[] = 'Tanggal tidak valid.';
    }
    
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $allowedDates = [$yesterday, $todayOriginal];
    if ($resetDate && !in_array($resetDate, $allowedDates, true)) {
      $errors[] = 'Reset hanya diizinkan untuk tanggal hari ini atau kemarin (tanggal efektif shift malam).';
    }

    if (!$errors) {
      try {
        
        $stmt = $pdo->prepare('SELECT id, check_in_time, status FROM attendances WHERE user_id = ? AND attendance_date = ? LIMIT 1');
        $stmt->execute([$resetUserId, $resetDate]);
        $resetRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resetRecord) {
          $errors[] = 'Tidak ditemukan record absensi untuk karyawan dan tanggal tersebut.';
        } else {
          
          $isNightRecord = false;
          if (!empty($resetRecord['is_cross_midnight']) && (int)$resetRecord['is_cross_midnight'] === 1) {
            $isNightRecord = true;
          } elseif (in_array($resetRecord['status'] ?? '', ['Alpha'], true)) {
            
            $isNightRecord = true;
          }

          if (!$isNightRecord) {
            $errors[] = 'Record ini bukan absen malam (jam check-in di luar window malam). Gunakan Reset Absensi biasa.';
          } else {
            
            $stmt = $pdo->prepare('UPDATE attendances SET
              status          = "Alpha",
              check_in_time   = "0000-00-00 00:00:00",
              check_out_time  = NULL,
              check_in_photo  = "",
              check_out_photo = NULL,
              check_out_location = "",
              today_plan      = "",
              notes           = "Reset absen malam oleh admin",
              updated_at      = CURRENT_TIMESTAMP
            WHERE id = ?');
            $stmt->execute([$resetRecord['id']]);

            
            $uStmt = $pdo->prepare('SELECT full_name, username FROM users WHERE id = ?');
            $uStmt->execute([$resetUserId]);
            $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
            $uName = htmlspecialchars($uRow['full_name'] ?: ($uRow['username'] ?? 'Karyawan'));
            $messages[] = "Absen malam {$uName} untuk tanggal {$resetDate} berhasil di-reset ke Alpha.";
            auditLog($pdo, 'reset_night_attendance', [
                'target_type' => 'attendance',
                'target_id' => (int)$resetRecord['id'],
                'target_user_id' => $resetUserId,
                'details' => ['date' => $resetDate, 'user' => $uName]
            ]);
          }
        }
      } catch (Exception $e) {
        error_log('Error resetting night shift attendance: ' . $e->getMessage());
        $errors[] = 'Gagal me-reset absen malam: ' . $e->getMessage();
      }
    }
    $activeTab = 'attendance';
  }
  elseif ($activeTab === 'attendance') {
    
    
    if ($oppositeShiftAttendanceToday) {
      $existingIsNightShift = isAttendanceNightShiftRowLocal($oppositeShiftAttendanceToday, $nightStartConf, $nightEndConf);
      if ($existingIsNightShift !== (bool)$nightOverride) {
        $errors[] = $existingIsNightShift
          ? 'Anda sudah absen shift malam untuk tanggal ini. Absen pagi/siang otomatis tidak tersedia.'
          : 'Anda sudah absen shift pagi/siang untuk tanggal ini. Absen malam otomatis tidak tersedia.';
      }
    }

    
    if ($hasLeaveToday) {
      $errors[] = 'Hari ini sudah tercatat sebagai ' . ($myAttendance['status'] ?? '-') . '. Tidak bisa melakukan absen hadir.';
    }

    
    $approvedLeaveToday = detectLeaveConflict($pdo, $currentUserId, $today);
    if ($approvedLeaveToday) {
      $leaveType = $approvedLeaveToday['type'] === 'permission' ? 'Izin' : 'Sakit';
      $errors[] = "Hari ini Anda memiliki {$leaveType} yang sudah disetujui. Tidak bisa melakukan absen hadir. Jika Anda sudah sembuh/bisa masuk kerja, sistem akan otomatis menyesuaikan izin Anda setelah check-in.";
    }

    
    
    
    
    if (!$errors && $hasMissedCheckoutPrevDay) {
      $missedDateFmt = date('d M Y', strtotime($missedCheckoutDate));
      $errors[] = "Anda belum melakukan absen pulang pada tanggal {$missedDateFmt}. Silakan ajukan Request Absensi (tab Request Absensi) untuk mengisi jam pulang terlebih dahulu.";
    }

    if (!$errors && !$hasAttendanceTrackToday) {
      
      $ciLat = floatval($_POST['check_in_latitude'] ?? 0);
      $ciLng = floatval($_POST['check_in_longitude'] ?? 0);
      $ciAcc = floatval($_POST['check_in_accuracy'] ?? 0);
      
      $gpsLogFile = __DIR__ . '/../../storage/logs/gps_debug.log';
      @file_put_contents($gpsLogFile, date('Y-m-d H:i:s') . " [CHECK-IN] user_id={$currentUserId} POST[lat]=" . ($_POST['check_in_latitude'] ?? 'NOT_SET') . " POST[lng]=" . ($_POST['check_in_longitude'] ?? 'NOT_SET') . " POST[loc]=" . ($_POST['check_in_location_name'] ?? 'NOT_SET') . " ciLat={$ciLat} ciLng={$ciLng}\n", FILE_APPEND);
      if ($ciLat != 0 && $ciLng != 0) {
            try {
              $geoStmt = $pdo->query("SELECT latitude, longitude, radius_meters, name FROM geofence_zones WHERE is_active = 1");
              $geoZones = $geoStmt->fetchAll(PDO::FETCH_ASSOC);
              if (!empty($geoZones)) {
                $insideAny = false; $best = null; $bestName = '';
                foreach ($geoZones as $gz) {
                  $latC = floatval($gz['latitude']);
                  $lngC = floatval($gz['longitude']);
                  
                  $dist1 = 6371000 * 2 * asin(sqrt(
                    pow(sin(deg2rad(($latC - $ciLat) / 2)), 2) +
                    cos(deg2rad($ciLat)) * cos(deg2rad($latC)) *
                    pow(sin(deg2rad(($lngC - $ciLng) / 2)), 2)
                  ));
                  
                  $dist2 = 6371000 * 2 * asin(sqrt(
                    pow(sin(deg2rad(($latC - $ciLng) / 2)), 2) +
                    cos(deg2rad($ciLng)) * cos(deg2rad($latC)) *
                    pow(sin(deg2rad(($lngC - $ciLat) / 2)), 2)
                  ));
                  $dist = min($dist1, $dist2);
                  $effRadius = floatval($gz['radius_meters']);
                  
                  $accBuf = max(15.0, min(100.0, $ciAcc));
                  $effRadius += $accBuf;
                  if ($best === null || $dist < $best) { $best = $dist; $bestName = (string)$gz['name']; }
                  if ($dist <= $effRadius) { $insideAny = true; break; }
                }
                
                $dbg = sprintf("[CHECK-IN-VALIDATE] best=%.2fm zone=%s acc=%.1f lat=%.6f lng=%.6f\n", $best ?? -1, $bestName, $ciAcc, $ciLat, $ciLng);
                @file_put_contents($gpsLogFile, date('Y-m-d H:i:s') . ' ' . $dbg, FILE_APPEND);
                if (!$insideAny) {
                  $errors[] = 'Lokasi Anda di luar zona absensi yang diizinkan. Silakan pindah ke area yang sudah ditentukan.';
                }
              }
            } catch (Exception $e) {   }
      }
      
      $todayPlan = trim($_POST['today_plan'] ?? '');
      if ($todayPlan === '') $errors[] = 'Plan hari ini wajib diisi.';
      if (empty($_FILES['check_in_photo']) || $_FILES['check_in_photo']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Foto absen datang wajib diunggah.';
      }
      
      if (!$errors) {
        $nowTs = time();
        $schedStartTs = strtotime($SCHEDULED_START_DT);
        $schedEndTs = strtotime($SCHEDULED_END_DT);
        if ($schedStartTs) {
          $openTs = $schedStartTs - ($CHECKIN_OPEN_MINS * 60);
          
          $closeCandidate = $schedStartTs + ($CHECKIN_CLOSE_MINS * 60);
          $closeTs = $schedEndTs && $schedEndTs > $closeCandidate ? $schedEndTs : $closeCandidate;
          if ($nightOverride) {
            $nightSessionOpenTs = strtotime($today . ' ' . $nightStartConf);
            $nightSessionCloseTs = strtotime(date('Y-m-d', strtotime($today . ' +1 day')) . ' ' . $nightEndConf);
            if ($nowTs < $nightSessionOpenTs || $nowTs > $nightSessionCloseTs) {
              $errors[] = 'Check-in di luar jendela absen malam (' . date('H:i', $nightSessionOpenTs) . ' - ' . date('H:i', $nightSessionCloseTs) . ').';
            }
          } else {
            
            
            $dayOpenTs = strtotime($today . ' 00:00:00');
            $dayCloseTs = strtotime($today . ' 23:59:59');
            if ($nowTs < $dayOpenTs || $nowTs > $dayCloseTs) {
              $errors[] = 'Check-in di luar tanggal absensi hari ini (' . date('H:i', $dayOpenTs) . ' - ' . date('H:i', $dayCloseTs) . ').';
            }
          }
        }
      }

      if (!$errors) {
        [$photoPath, $err] = saveImage(
          $_FILES['check_in_photo'],
          'checkin_' . $currentUserId,
          $currentUserId,
          $today,
          'storage/uploads/attendance/' . $currentUserId . '/absen-masuk-pulang'
        );
        if ($err) $errors[] = $err;
      }
      if (!$errors) {
        $now = date('Y-m-d H:i:s');
        
        
        if ($nightOverride) {
          $scheduledStartTs = strtotime($SCHEDULED_START_DT);
          $lateThresholdTs = $scheduledStartTs + ($lateToleranceMinutes * 60);
          $status = (time() <= $lateThresholdTs) ? 'Hadir' : 'Terlambat';
        } else {
          $status = (strtotime(date('H:i:s')) <= strtotime($ON_TIME_LIMIT)) ? 'Hadir' : 'Terlambat';
        }
        
        
        $ciLatSave = $ciLat != 0 ? $ciLat : null;
        $ciLngSave = $ciLng != 0 ? $ciLng : null;
        $ciLocationName = trim($_POST['check_in_location_name'] ?? '');
        
        
        $attendanceId = null;
        if ($myAttendance) {
          
          $stmt = $pdo->prepare('UPDATE attendances SET today_plan = ?, check_in_time = ?, check_in_photo = ?, check_in_lat = ?, check_in_lng = ?, check_in_location = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND attendance_date = ?');
          $stmt->execute([$todayPlan, $now, $photoPath, $ciLatSave, $ciLngSave, $ciLocationName ?: null, $status, $currentUserId, $today]);
          $attendanceId = $myAttendance['id'];
        } else {
          
          $stmt = $pdo->prepare('INSERT INTO attendances (user_id, attendance_date, today_plan, check_in_time, check_in_photo, check_in_lat, check_in_lng, check_in_location, check_out_location, notes, status) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
          $stmt->execute([$currentUserId, $today, $todayPlan, $now, $photoPath, $ciLatSave, $ciLngSave, $ciLocationName ?: null, '', '', $status]);
          $attendanceId = $pdo->lastInsertId();
        }
        
        $successMessage = 'Absen datang berhasil disimpan.';
        $messages[] = $successMessage;

        
        $leaveConflict = detectLeaveConflict($pdo, $currentUserId, $today);
        if ($leaveConflict) {
          
          $conflictResult = handleLeaveConflict($pdo, $leaveConflict, $today, $currentUserId);

          
          notifyAdminOfLeaveConflict($pdo, $leaveConflict, $today, $currentUserId);

          
          if ($conflictResult['action'] !== 'error') {
            $messages[] = $conflictResult['message'];
          } else {
            $messages[] = 'Peringatan: Terdeteksi konflik dengan izin Anda. Silakan hubungi admin untuk konfirmasi.';
          }
        }

        
        try {
          $stmtSched = $pdo->prepare('UPDATE attendances SET shift_id = ?, scheduled_start = ?, scheduled_end = ?, is_cross_midnight = ? WHERE user_id = ? AND attendance_date = ?');
          $stmtSched->execute([
            $RESOLVED_SHIFT_ID,
            $SCHEDULED_START_DT,
            $SCHEDULED_END_DT,
            $IS_CROSS_MIDNIGHT,
            $currentUserId,
            $today
          ]);
        } catch (Throwable $e) {   }

        $stmt = $pdo->prepare('SELECT * FROM attendances WHERE user_id = ? AND attendance_date = ? LIMIT 1');
        $stmt->execute([$currentUserId, $today]);
        $myAttendance = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        
        
        $hasLeaveToday = $myAttendance && in_array($myAttendance['status'] ?? '', $leaveStatuses, true);
        $hasAttendanceTrackToday = $myAttendance && !$hasLeaveToday && !empty($myAttendance['check_in_time']) && $myAttendance['check_in_time'] !== '0000-00-00 00:00:00';
        $hasAlphaToday = $myAttendance && ($myAttendance['status'] ?? '') === 'Alpha';
        $dayLockedType = $hasLeaveToday ? ($myAttendance['status'] ?? '') : ($hasAttendanceTrackToday ? 'Attendance' : ($hasAlphaToday ? 'Alpha' : ''));
        
  error_log("[ATTENDANCE DEBUG] Check-in SUCCESS, about to redirect. headers_sent=" . (headers_sent() ? 'YES' : 'NO'));
  $_SESSION['flash_messages'] = $messages; 
  redirectToAttendanceTab();
      } 
      } 
      
      error_log("[ATTENDANCE DEBUG] Past check-in block. errors=" . json_encode($errors) . " hasAttendanceTrackToday={$hasAttendanceTrackToday}");
      
      
      
      $stmt = $pdo->prepare('SELECT * FROM attendances WHERE user_id = ? AND attendance_date = ? LIMIT 1');
      $stmt->execute([$currentUserId, $today]);
      $myAttendance = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
      
      
      $hasAttendanceTrackToday = $myAttendance
          && !in_array($myAttendance['status'] ?? '', $leaveStatuses, true)
          && !empty($myAttendance['check_in_time'])
          && $myAttendance['check_in_time'] !== '0000-00-00 00:00:00';
      $isCheckedIn  = $hasAttendanceTrackToday;
      $isCheckedOut = $myAttendance && !empty($myAttendance['check_out_time']) && $myAttendance['check_out_time'] !== '0000-00-00 00:00:00';
      
      
      if ($isCheckedOut) {
        $messages[] = 'Anda sudah melakukan absen pulang hari ini.';
  redirectToAttendanceTab();
      }
      
      
      if (!$errors && $isCheckedIn && !$isCheckedOut) {
      
      $coLat = floatval($_POST['check_out_latitude'] ?? 0);
      $coLng = floatval($_POST['check_out_longitude'] ?? 0);
      $coAcc = floatval($_POST['check_out_accuracy'] ?? 0);
      if ($coLat != 0 && $coLng != 0) {
            try {
              $geoStmt2 = $pdo->query("SELECT latitude, longitude, radius_meters, name FROM geofence_zones WHERE is_active = 1");
              $geoZones2 = $geoStmt2->fetchAll(PDO::FETCH_ASSOC);
              if (!empty($geoZones2)) {
                $insideAny2 = false; $best2 = null; $bestName2 = '';
                foreach ($geoZones2 as $gz2) {
                  $latC = floatval($gz2['latitude']);
                  $lngC = floatval($gz2['longitude']);
                  $distA = 6371000 * 2 * asin(sqrt(
                    pow(sin(deg2rad(($latC - $coLat) / 2)), 2) +
                    cos(deg2rad($coLat)) * cos(deg2rad($latC)) *
                    pow(sin(deg2rad(($lngC - $coLng) / 2)), 2)
                  ));
                  $distB = 6371000 * 2 * asin(sqrt(
                    pow(sin(deg2rad(($latC - $coLng) / 2)), 2) +
                    cos(deg2rad($coLng)) * cos(deg2rad($latC)) *
                    pow(sin(deg2rad(($lngC - $coLat) / 2)), 2)
                  ));
                  $distX = min($distA, $distB);
                  $effRadius2 = floatval($gz2['radius_meters']);
                  $accBuf2 = max(15.0, min(100.0, $coAcc));
                  $effRadius2 += $accBuf2;
                  if ($best2 === null || $distX < $best2) { $best2 = $distX; $bestName2 = (string)$gz2['name']; }
                  if ($distX <= $effRadius2) { $insideAny2 = true; break; }
                }
                $dbg2 = sprintf("[CHECK-OUT-VALIDATE] best=%.2fm zone=%s acc=%.1f lat=%.6f lng=%.6f\n", $best2 ?? -1, $bestName2, $coAcc, $coLat, $coLng);
                @file_put_contents(__DIR__ . '/../../storage/logs/gps_debug.log', date('Y-m-d H:i:s') . ' ' . $dbg2, FILE_APPEND);
                if (!$insideAny2) {
                  $errors[] = 'Lokasi Anda di luar zona absensi yang diizinkan. Silakan pindah ke area yang sudah ditentukan.';
                }
              }
            } catch (Exception $e) {   }
      }
      
      $todayActivities = trim($_POST['today_activities'] ?? '');
      if ($todayActivities === '') {
        $errors[] = 'Deskripsi aktivitas wajib diisi.';
      }
      $activityPhotos = $_FILES['activity_photos'] ?? null;
      $count = 0;
      if ($activityPhotos && is_array($activityPhotos['name'])) {
        foreach ($activityPhotos['name'] as $idx => $name) {
          if ($name !== '' && ($activityPhotos['error'][$idx] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) $count++;
        }
      }
      
      if ($count === 0) {
        $errors[] = 'Minimal 1 foto activity untuk absen pulang.';
      }
      if ($count > 5) {
        $errors[] = 'Maksimal 5 foto activity.';
      }
      
      $checkOutPhotoUploaded = !empty($_FILES['check_out_photo']) && $_FILES['check_out_photo']['error'] === UPLOAD_ERR_OK;
      
      if (!$checkOutPhotoUploaded) {
        $errors[] = 'Foto absen pulang wajib diunggah.';
      }

      if (!$errors) {
        
        [$outPhotoPath, $err2] = saveImage(
          $_FILES['check_out_photo'],
          'checkout_' . $currentUserId,
          $currentUserId,
          $today,
          'storage/uploads/attendance/' . $currentUserId . '/absen-masuk-pulang'
        );
        if ($err2) {
          $errors[] = $err2;
        }
      }
      
      
      if (!$errors) {
        $pdo->beginTransaction();
        try {
          if ($activityPhotos && is_array($activityPhotos['name'])) {
            foreach ($activityPhotos['name'] as $i => $name) {
              if ($name === '') continue;
              if (($activityPhotos['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
              $tmp = [
                'name' => $activityPhotos['name'][$i],
                'type' => $activityPhotos['type'][$i],
                'tmp_name' => $activityPhotos['tmp_name'][$i],
                'error' => $activityPhotos['error'][$i],
                'size' => $activityPhotos['size'][$i],
              ];
              [$actPath, $e3] = saveImage($tmp, 'activity_' . $currentUserId, $currentUserId, $today, 'storage/uploads/attendance/' . $currentUserId . '/activity');
              if ($e3) continue;
              $stmt = $pdo->prepare('INSERT INTO attendance_activities (attendance_id, photo_path, description) VALUES (?,?,?)');
              $stmt->execute([$myAttendance['id'], $actPath, null]);
            }
          }
          $now = date('Y-m-d H:i:s');
          
          
          $coLatSave = $coLat != 0 ? $coLat : null;
          $coLngSave = $coLng != 0 ? $coLng : null;
          
          $stmt = $pdo->prepare('UPDATE attendances SET check_out_time = ?, check_out_photo = ?, check_out_lat = ?, check_out_lng = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
          $updateResult = $stmt->execute([$now, $outPhotoPath, $coLatSave, $coLngSave, $todayActivities, $myAttendance['id']]);
          $rowsAffected = $stmt->rowCount();
          
          if (!$updateResult || $rowsAffected === 0) {
            $errors[] = 'Gagal menyimpan check-out ke database. Silakan coba lagi.';
            $pdo->rollBack();
          } else {
            $pdo->commit();
            
            $successMessage = 'Absen pulang berhasil disimpan.';
            if (!isset($_SESSION['flash_messages'])) {
              $_SESSION['flash_messages'] = [];
            }
            $_SESSION['flash_messages'][] = $successMessage;
            $_SESSION['checkout_success'] = true;
            
            redirectToAttendanceTab();
          }
        } catch (Exception $ex) {
          $pdo->rollBack();
          $errors[] = 'Gagal menyimpan absen pulang: ' . $ex->getMessage();
        }
      }
    }
  }
  elseif ($activeTab === 'attendance_request') {
    if ($hasPendingAttendanceRequest) {
      $errors[] = 'Anda masih memiliki request absensi yang menunggu approval admin.';
    } else {
      $reqRequestType = trim($_POST['request_type'] ?? 'checkin');
      if ($reqRequestType === 'missed_checkout') {
        
        $mcReason = trim($_POST['request_reason'] ?? '');
        $mcCheckOutRaw = trim($_POST['request_check_out_time'] ?? '');
        $mcCheckOutTime = null;
        $mcMissedDate = trim($_POST['request_missed_date'] ?? '');
        if ($mcReason === '') $errors[] = 'Alasan wajib diisi.';
        if ($mcMissedDate === '') {
          $errors[] = 'Tanggal absen pulang terlewat wajib diisi.';
        } else {
          $mcMissedMonth = date('Y-m', strtotime($mcMissedDate));
          $currentMonth = date('Y-m');
          if ($mcMissedMonth !== $currentMonth) {
            $errors[] = 'Request absen pulang hanya dapat diajukan untuk bulan berjalan (' . date('F Y') . ').';
          }
        }
        if ($mcCheckOutRaw !== '') {
          if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $mcCheckOutRaw)) {
            $mcCheckOutTime = strlen($mcCheckOutRaw) === 5 ? $mcCheckOutRaw . ':00' : $mcCheckOutRaw;
          } else { $errors[] = 'Format jam pulang tidak valid (HH:MM).'; }
        } else { $errors[] = 'Jam pulang wajib diisi.'; }
        if (!$errors) {
          try {
            $mcChk = $pdo->prepare('SELECT id,status,check_in_time,check_out_time FROM attendances WHERE user_id=? AND attendance_date=? LIMIT 1');
            $mcChk->execute([$currentUserId, $mcMissedDate]);
            $mcA = $mcChk->fetch(PDO::FETCH_ASSOC);
            if (!$mcA) { $errors[] = 'Tidak ditemukan data absensi untuk tanggal tersebut.'; }
            elseif (!empty($mcA['check_out_time']) && $mcA['check_out_time'] !== '0000-00-00 00:00:00') { $errors[] = 'Absen pulang sudah terisi.'; }
            elseif (empty($mcA['check_in_time']) || $mcA['check_in_time'] === '0000-00-00 00:00:00') { $errors[] = 'Tidak ada absen masuk.'; }
          } catch (Exception $e) { $errors[] = 'Gagal cek data.'; }
        }
        if (!$errors) {
          try {
            $mcIns = $pdo->prepare('INSERT INTO attendance_requests (user_id,attendance_date,reason,requested_check_out_time,missed_checkout_date,request_type,status,created_at) VALUES (?,"'.$todayOriginal.'",?,?,?,"missed_checkout","pending",NOW())');
            $mcIns->execute([$currentUserId, $mcReason, $mcCheckOutTime, $mcMissedDate]);
            // Notify admins
            try {
                $adminIds = $pdo->query("SELECT id FROM users WHERE role IN ('administrator','direktur') AND is_active=1")->fetchAll(PDO::FETCH_COLUMN);
                if ($adminIds) socketNotify($adminIds, 'attendance_request', 'Request absen pulang terlewat baru');
            } catch (Exception $e) {}
            $_SESSION['flash_messages'] = ['Request absen pulang berhasil dikirim dan menunggu approval admin.'];
            redirectToAttendanceTab();
          } catch (Exception $e) { $errors[] = 'Gagal simpan request: ' . $e->getMessage(); }
        }
      } elseif ($hasAttendanceTrackToday || $hasLeaveToday) {
        $errors[] = 'Absensi/request tidak dapat diajukan karena hari ini sudah memiliki data absen atau izin.';
      } else {
        
        $requestDate = $todayOriginal;
        $reqLat = floatval($_POST['request_latitude'] ?? 0);
        $reqLng = floatval($_POST['request_longitude'] ?? 0);
        $reqAcc = floatval($_POST['request_accuracy'] ?? 0);
        $reqLocation = trim($_POST['request_location_name'] ?? '');
        $reqPlan = trim($_POST['request_today_plan'] ?? '');
        $reqReason = trim($_POST['request_reason'] ?? '');
        $reqPhoto = $_FILES['request_photo'] ?? null;

        
        $reqCheckInRaw = trim($_POST['request_check_in_time'] ?? '');
        $reqCheckInTime = null;
        if ($reqCheckInRaw !== '') {
          
          if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $reqCheckInRaw)) {
            $reqCheckInTime = strlen($reqCheckInRaw) === 5 ? $reqCheckInRaw . ':00' : $reqCheckInRaw;
          } else {
            $errors[] = 'Format jam masuk tidak valid (gunakan HH:MM).';
          }
        } else {
          $errors[] = 'Jam masuk yang diinginkan wajib diisi.';
        }

        if ($reqLat == 0 || $reqLng == 0) $errors[] = 'Lokasi GPS wajib diambil.';
        if ($reqPlan === '') $errors[] = 'Plan hari ini wajib diisi.';
        if ($reqReason === '') $errors[] = 'Alasan absen dengan request wajib diisi.';
        if (empty($reqPhoto) || ($reqPhoto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) $errors[] = 'Foto sekarang wajib diambil.';

        if (!$errors) {
          try {
            $stmt = $pdo->prepare('SELECT status FROM attendances WHERE user_id = ? AND attendance_date = ? LIMIT 1');
            $stmt->execute([$currentUserId, $requestDate]);
            $existingAtt = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existingAtt && ($existingAtt['status'] ?? '') !== 'Alpha') {
              $errors[] = 'Data absensi hari ini sudah ada dan tidak bisa dibuat request.';
            }
          } catch (Exception $e) {
            $errors[] = 'Gagal memeriksa data absensi hari ini.';
          }
        }

        if (!$errors) {
          [$requestPhotoPath, $photoErr] = saveImage(
            $reqPhoto,
            'attendance_request_' . $currentUserId,
            $currentUserId,
            $requestDate,
            'storage/uploads/attendance/' . $currentUserId . '/request-absensi'
          );
          if ($photoErr) $errors[] = $photoErr;
        }

        if (!$errors) {
          try {
            $stmt = $pdo->prepare('INSERT INTO attendance_requests (user_id, attendance_date, gps_lat, gps_lng, gps_accuracy, location_name, photo_path, today_plan, reason, requested_check_in_time, request_type, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "checkin", "pending", NOW())');
            $stmt->execute([
              $currentUserId,
              $requestDate,
              $reqLat ?: null,
              $reqLng ?: null,
              $reqAcc ?: null,
              $reqLocation ?: null,
              $requestPhotoPath,
              $reqPlan,
              $reqReason,
              $reqCheckInTime
            ]);
            // Notify admins
            try {
                $adminIds = $pdo->query("SELECT id FROM users WHERE role IN ('administrator','direktur') AND is_active=1")->fetchAll(PDO::FETCH_COLUMN);
                if ($adminIds) socketNotify($adminIds, 'attendance_request', 'Request absensi masuk baru');
            } catch (Exception $e) {}
            $_SESSION['flash_messages'] = ['Request absensi berhasil dikirim dan menunggu approval admin.'];
            redirectToAttendanceTab();
          } catch (Exception $e) {
            $errors[] = 'Gagal menyimpan request absensi: ' . $e->getMessage();
          }
        }
      }
    }
  }
  elseif ($activeTab === 'permission') {
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['tab'] ?? '') !== 'permission') {
            // Skip validation - not a permission form submission
        } elseif ($hasPendingLeaveRequest) {
            $errors[] = 'Tidak dapat mengajukan izin baru. Menunggu approval permintaan sebelumnya.';
        } else {
        
        $permission_type = $_POST['permission_type'] ?? 'range';
        $reason = trim($_POST['permission_reason'] ?? '');
        $proof_file = $_FILES['permission_proof'] ?? null;
        
        if (empty($reason)) $errors[] = 'Alasan izin wajib diisi.';
        if (empty($proof_file) || $proof_file['error'] !== UPLOAD_ERR_OK) $errors[] = 'Bukti izin wajib diunggah.';
        
        
        if ($permission_type === 'single') {
            $start_date = $_POST['permission_single_date'] ?? '';
            $end_date = $start_date;
            if (empty($start_date)) $errors[] = 'Tanggal izin wajib diisi.';
        } else {
            $start_date = $_POST['permission_start_date'] ?? '';
            $end_date = $_POST['permission_end_date'] ?? '';
            if (empty($start_date)) $errors[] = 'Tanggal mulai izin wajib diisi.';
            if (empty($end_date)) $errors[] = 'Tanggal selesai izin wajib diisi.';
        }

        
        if (!$errors) {
            $s = strtotime($start_date);
            $e = strtotime($end_date);
            if ($s === false || $e === false || $s > $e) {
                $errors[] = 'Rentang tanggal izin tidak valid.';
            } else {
                try {
                    $conflict = false;
                    for ($t = $s; $t <= $e; $t = strtotime('+1 day', $t)) {
                        $d = date('Y-m-d', $t);
                        
                        
                        $stmt = $pdo->prepare('SELECT id, status, check_in_time FROM attendances WHERE user_id = ? AND attendance_date = ?');
                        $stmt->execute([$currentUserId, $d]);
                        $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        
                        if ($existingRecord) {
                            $currentStatus = trim($existingRecord['status']);
                            
                            
                            if ($currentStatus !== 'Alpha') {
                                $conflict = true;
                                break;
                            }
                        }
                        
                        
                        if (!$conflict) {
                            $stmt = $pdo->prepare('SELECT COUNT(*) FROM leave_requests WHERE user_id = ? AND ? BETWEEN start_date AND end_date AND status IN ("pending", "approved")');
                            $stmt->execute([$currentUserId, $d]);
                            $leaveCount = (int)$stmt->fetchColumn();
                            if ($leaveCount > 0) { 
                                $conflict = true; 
                                break; 
                            }
                        }
                    }
                    if ($conflict) {
                        $errors[] = 'Terdapat tanggal dalam rentang yang sudah memiliki data absen atau permohonan izin/sakit selain Alpha. Hanya status Alpha yang bisa diubah menjadi Izin.';
                    }
                } catch (Exception $e) {
                    $errors[] = 'Gagal memeriksa data existing untuk tanggal izin.';
                }
            }
        }
        if (!$errors) {
            
            [$proofPath, $err] = saveImage(
                $proof_file,
                'permission_' . $currentUserId . '_' . date('Ymd_His'),
                $currentUserId,
                date('Y-m-d'),
                'storage/uploads/attendance/' . $currentUserId . '/permission'
            );
            
            if ($err) {
                $errors[] = $err;
            } else {
                
                try {
                    $stmt = $pdo->prepare('INSERT INTO leave_requests (user_id, type, reason, start_date, end_date, proof_path, status, created_at) VALUES (?, "permission", ?, ?, ?, ?, "pending", NOW())');
                    $stmt->execute([$currentUserId, $reason, $start_date, $end_date, $proofPath]);
                    $messages[] = 'Permohonan izin dikirim dan menunggu persetujuan admin.';
                    // Notify admins
                    try {
                        $adminIds = $pdo->query("SELECT id FROM users WHERE role IN ('administrator','direktur') AND is_active=1")->fetchAll(PDO::FETCH_COLUMN);
                        if ($adminIds) socketNotify($adminIds, 'leave_request', 'Permohonan izin baru');
                    } catch (Exception $e2) {}
                } catch (Exception $e) {
                    $errors[] = 'Gagal menyimpan permohonan izin: ' . $e->getMessage();
                }
            }
        }
    }
    }
  } elseif ($activeTab === 'sick_leave') {
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['tab'] ?? '') !== 'sick_leave') {
            // Skip validation - not a sick_leave form submission
        } elseif ($hasPendingLeaveRequest) {
            $errors[] = 'Tidak dapat mengajukan sakit baru. Menunggu approval untuk permintaan sebelumnya.';
        } else {
        
        $sick_type = $_POST['sick_type'] ?? 'single';
        $illness = trim($_POST['sick_illness'] ?? '');
        $proof_file = $_FILES['sick_proof'] ?? null;
        
        if (empty($illness)) $errors[] = 'Jenis sakit wajib diisi.';
        if (empty($proof_file) || $proof_file['error'] !== UPLOAD_ERR_OK) $errors[] = 'Bukti sakit wajib diunggah.';
        
        
        if ($sick_type === 'single') {
            $start_date = $_POST['sick_single_date'] ?? '';
            $end_date = $start_date;
            if (empty($start_date)) $errors[] = 'Tanggal sakit wajib diisi.';
        } else {
            $start_date = $_POST['sick_start_date'] ?? '';
            $end_date = $_POST['sick_end_date'] ?? '';
            
            if (empty($start_date) && empty($end_date) && !empty($_POST['sick_single_date'])) {
                $start_date = $_POST['sick_single_date'];
                $end_date = $start_date;
            }
            if (empty($start_date)) $errors[] = 'Tanggal mulai sakit wajib diisi.';
            if (empty($end_date)) $errors[] = 'Tanggal selesai sakit wajib diisi.';
        }

    
    if (!$errors) {
      $s = strtotime($start_date);
      $e = strtotime($end_date);
      if ($s === false || $e === false || $s > $e) {
        $errors[] = 'Rentang tanggal sakit tidak valid.';
      } else {
        try {
          $conflict = false;
          $canRetrospective = true;
          $invalidDates = [];
          
          for ($t = $s; $t <= $e; $t = strtotime('+1 day', $t)) {
            $d = date('Y-m-d', $t);
            
            
            if (!canApplyRetrospectiveSickLeave($d, $GRACE_PERIOD_DAYS)) {
              $canRetrospective = false;
              $invalidDates[] = $d;
              continue;
            }
            
            
            $stmt = $pdo->prepare('SELECT status FROM attendances WHERE user_id = ? AND attendance_date = ?');
            $stmt->execute([$currentUserId, $d]);
            $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingRecord) {
              
              if ($existingRecord['status'] !== 'Alpha') {
                $conflict = true;
                break;
              }
            }
            
            
            if (!$conflict) {
              $stmt = $pdo->prepare('SELECT COUNT(*) FROM leave_requests WHERE user_id = ? AND ? BETWEEN start_date AND end_date AND status IN ("pending", "approved")');
              $stmt->execute([$currentUserId, $d]);
              if ((int)$stmt->fetchColumn() > 0) { $conflict = true; break; }
            }
          }
          
          if (!$canRetrospective) {
            $errors[] = 'Beberapa tanggal melebihi batas waktu grace period ' . $GRACE_PERIOD_DAYS . ' hari: ' . implode(', ', $invalidDates);
          }
          
          if ($conflict) {
            $errors[] = 'Terdapat tanggal dalam rentang yang sudah memiliki data absen atau permohonan izin/sakit selain Alpha. Hanya status Alpha yang bisa diubah menjadi Sakit.';
          }
        } catch (Exception $eX) {
          $errors[] = 'Gagal memeriksa konflik tanggal sakit.';
        }
      }
    }
        
    if (!$errors) {
      
      [$proofPath, $err] = saveImage(
        $proof_file,
        'sick_' . $currentUserId . '_' . date('Ymd_His'),
        $currentUserId,
        date('Y-m-d'),
        'storage/uploads/attendance/' . $currentUserId . '/sick leave'
      );
            
      if ($err) {
        $errors[] = $err;
      } else {
        
        try {
          $stmt = $pdo->prepare('INSERT INTO leave_requests (user_id, type, reason, start_date, end_date, proof_path, status, created_at) VALUES (?, "sick", ?, ?, ?, ?, "pending", NOW())');
          $notesStr = 'Sakit: ' . $illness; 
          $stmt->execute([$currentUserId, $notesStr, $start_date, $end_date, $proofPath]);
          $messages[] = 'Permohonan sakit dikirim dan menunggu persetujuan admin.';
          // Notify admins
          try {
              $adminIds = $pdo->query("SELECT id FROM users WHERE role IN ('administrator','direktur') AND is_active=1")->fetchAll(PDO::FETCH_COLUMN);
              if ($adminIds) socketNotify($adminIds, 'leave_request', 'Permohonan sakit baru');
          } catch (Exception $e2) {}
        } catch (Exception $e) {
          $errors[] = 'Gagal menyimpan permohonan sakit: ' . $e->getMessage();
        }
      }
    }
  }

} 
skip_post_processing:


$myActivities = [];
if ($myAttendance) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM attendance_activities WHERE attendance_id = ? ORDER BY id ASC');
        $stmt->execute([$myAttendance['id']]);
        $myActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {   }
}


try {
    $graceStartDate = date('Y-m-d', strtotime("-{$GRACE_PERIOD_DAYS} days"));
    $currentDate = strtotime($graceStartDate);
    $todayTimestamp = strtotime($todayOriginal);
    
    
    while ($currentDate < $todayTimestamp) {
        $dateStr = date('Y-m-d', $currentDate);
        $dayOfWeek = date('w', $currentDate);
        
        
        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
            createAlphaAttendanceIfMissing($pdo, $currentUserId, $dateStr);
        }
        
        $currentDate = strtotime('+1 day', $currentDate);
    }
} catch (Exception $e) {   }


$recentAlphaDays = [];
try {
    $graceStartDate = date('Y-m-d', strtotime("-{$GRACE_PERIOD_DAYS} days"));
    $stmt = $pdo->prepare('SELECT attendance_date FROM attendances WHERE user_id = ? AND attendance_date BETWEEN ? AND ? AND status = "Alpha" ORDER BY attendance_date DESC');
    $stmt->execute([$currentUserId, $graceStartDate, $todayOriginal]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $recentAlphaDays[] = $row['attendance_date'];
    }
} catch (Exception $e) {   }

list($dayStr, $monthYear) = formatIndoDate($today);

function checkinStatusPill($att) {
    if (!$att) return '';
    if (($att['status'] ?? '') === 'Terlambat') return '<span class="ml-2 inline-flex items-center rounded-full bg-red-500 text-white text-xs px-2 py-0.5 font-semibold">TELAT</span>';
    if (($att['status'] ?? '') === 'Hadir') return '<span class="ml-2 inline-flex items-center rounded-full bg-green-500 text-white text-xs px-2 py-0.5 font-semibold">TEPAT WAKTU</span>';
    return '';
}
function checkoutStatusPill($att, $endTime='17:30:00', $ot='18:00:00', $earlyToleranceMinutes=0) {
    if (!$att || empty($att['check_out_time'])) return '';
    $t = strtotime(date('H:i:s', strtotime($att['check_out_time'])));
  $earlyToleranceSeconds = max(0, (int)$earlyToleranceMinutes) * 60;
  $earlyLimit = strtotime($endTime) - $earlyToleranceSeconds;
    if ($t >= strtotime($ot)) return '<span class="ml-2 inline-flex items-center rounded-full bg-indigo-500 text-white text-xs px-2 py-0.5 font-semibold">Lembur</span>';
  if ($t < $earlyLimit) return '<span class="ml-2 inline-flex items-center rounded-full bg-orange-500 text-white text-xs px-2 py-0.5 font-semibold">Pulang Lebih Awal</span>';
    return '';
}



$isCheckedIn = $hasAttendanceTrackToday;



$isCheckedOut = false;
if ($myAttendance && isset($myAttendance['check_out_time'])) {
    $checkOutValue = $myAttendance['check_out_time'];
    
    $isCheckedOut = !empty($checkOutValue) 
                    && $checkOutValue !== '0000-00-00 00:00:00' 
                    && $checkOutValue !== null;
}




if (!empty($GLOBALS['__attendance_redirect'])) {
    return; 
}


error_log("[ATTENDANCE DEBUG] Reached HTML output section. Method=" . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
?>



  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  
  
  <style>
    * {
      box-sizing: border-box;
    }

    #modalManualAttendance,
    #modalWorkHours,
    #modalEditAttendance,
    #modalResetNightShift {
      background-color: rgba(241, 245, 249, 0.94);
    }

    html.dark #modalManualAttendance,
    html.dark #modalWorkHours,
    html.dark #modalEditAttendance,
    html.dark #modalResetNightShift,
    html[data-theme="dark"] #modalManualAttendance,
    html[data-theme="dark"] #modalWorkHours,
    html[data-theme="dark"] #modalEditAttendance,
    html[data-theme="dark"] #modalResetNightShift {
      background-color: rgba(2, 6, 23, 0.78);
    }

    #modalManualAttendance .attendance-modal-card,
    #modalWorkHours .attendance-modal-card,
    #modalEditAttendance .attendance-modal-card,
    #modalResetNightShift .attendance-modal-card {
      opacity: 1;
    }

    @media (max-width: 414px) {
      #attendance-body {
        padding: 0.5rem !important;
        display: block !important;
      }

      #attendance-container {
        max-width: 100% !important;
        width: 100% !important;
        padding: 1rem !important;
        border-radius: 1rem !important;
        margin: 0 !important;
      }

      #attendance-container h2 {
        font-size: 1.125rem !important;
        margin-bottom: 0.75rem !important;
        line-height: 1.5 !important;
      }

      #attendance-container .text-2xl {
        font-size: 1.125rem !important;
      }

      #attendance-container .text-lg {
        font-size: 1rem !important;
      }

      #attendance-container .text-sm {
        font-size: 0.875rem !important;
      }

      #attendance-container .text-xs {
        font-size: 0.75rem !important;
      }

      #attendance-container .space-y-4 > * + * {
        margin-top: 0.875rem !important;
      }

      #attendance-container .space-y-3 > * + * {
        margin-top: 0.625rem !important;
      }

      #attendance-container input[type="text"],
      #attendance-container input[type="date"],
      #attendance-container input[type="file"],
      #attendance-container textarea,
      #attendance-container select {
        font-size: 1rem !important;
        padding: 0.75rem 1rem !important;
        width: 100% !important;
        box-sizing: border-box !important;
      }

      #attendance-container textarea {
        min-height: 100px !important;
      }

      #attendance-container label {
        font-size: 0.875rem !important;
        margin-bottom: 0.5rem !important;
        display: block !important;
      }

      #attendance-container button:not([onclick*="openCamera"]) {
        font-size: 1rem !important;
        padding: 0.75rem 1.25rem !important;
      }

      #attendance-container .px-6 {
        padding-left: 1.25rem !important;
        padding-right: 1.25rem !important;
      }

      #attendance-container .py-3 {
        padding-top: 0.75rem !important;
        padding-bottom: 0.75rem !important;
      }

      #attendance-container .px-4 {
        padding-left: 1rem !important;
        padding-right: 1rem !important;
      }

      #attendance-container .py-2 {
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
      }

      #attendance-container .p-4 {
        padding: 1rem !important;
      }

      #attendance-container .p-6 {
        padding: 1rem !important;
      }

      #attendance-container .w-20 {
        width: 4.5rem !important;
        min-width: 4.5rem !important;
      }

      #attendance-container .h-20 {
        height: 4.5rem !important;
      }

      #attendance-container .grid-cols-5 {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
      }

      #attendance-container .grid-cols-2 {
        grid-template-columns: repeat(1, minmax(0, 1fr)) !important;
      }

      #attendance-container .gap-4 {
        gap: 1rem !important;
      }

      #attendance-container .gap-3 {
        gap: 0.75rem !important;
      }

      #attendance-container .gap-2 {
        gap: 0.5rem !important;
      }

      #attendance-container .mb-6 {
        margin-bottom: 1.25rem !important;
      }

      #attendance-container .mb-4 {
        margin-bottom: 1rem !important;
      }

      #attendance-container .mb-3 {
        margin-bottom: 0.75rem !important;
      }

      #attendance-container .mb-2 {
        margin-bottom: 0.5rem !important;
      }

      #attendance-container .mb-1 {
        margin-bottom: 0.375rem !important;
      }

      #attendance-container .mt-3 {
        margin-top: 0.75rem !important;
      }

      #attendance-container .mt-2 {
        margin-top: 0.5rem !important;
      }

      #attendance-container .rounded-2xl {
        border-radius: 1rem !important;
      }

      #attendance-container .rounded-xl {
        border-radius: 0.75rem !important;
      }

      #attendance-container .rounded-lg {
        border-radius: 0.625rem !important;
      }

      #attendance-container .rounded-full {
        border-radius: 9999px !important;
      }

      #attendance-container .border-b {
        margin-bottom: 1rem !important;
      }

      #attendance-container .flex-wrap {
        flex-wrap: wrap !important;
      }

      #attendance-container #preview_activities,
      #attendance-container #preview_checkin,
      #attendance-container #preview_checkout {
        margin-top: 0.75rem !important;
      }

      #attendance-container input[type="file"] {
        font-size: 0.875rem !important;
      }
    }

    @media (max-width: 375px) {
      #attendance-body {
        padding: 0.375rem !important;
      }

      #attendance-container {
        max-width: 100% !important;
        width: 100% !important;
        padding: 0.875rem !important;
      }

      #attendance-container h2 {
        font-size: 1rem !important;
      }

      #attendance-container .text-2xl {
        font-size: 1rem !important;
      }

      #attendance-container .text-lg {
        font-size: 0.9375rem !important;
      }

      #attendance-container .text-sm {
        font-size: 0.8125rem !important;
      }

      #attendance-container .text-xs {
        font-size: 0.6875rem !important;
      }

      #attendance-container input[type="text"],
      #attendance-container input[type="date"],
      #attendance-container input[type="file"],
      #attendance-container textarea,
      #attendance-container select {
        font-size: 0.9375rem !important;
        padding: 0.625rem 0.875rem !important;
      }

      #attendance-container textarea {
        min-height: 80px !important;
      }

      #attendance-container button:not([onclick*="openCamera"]) {
        font-size: 0.9375rem !important;
        padding: 0.625rem 1rem !important;
      }

      #attendance-container .w-20 {
        width: 3.5rem !important;
        min-width: 3.5rem !important;
      }

      #attendance-container .h-20 {
        height: 3.5rem !important;
      }

      #attendance-container .p-4 {
        padding: 0.75rem !important;
      }

      #attendance-container .p-6 {
        padding: 0.875rem !important;
      }

      #attendance-container .space-y-4 > * + * {
        margin-top: 0.75rem !important;
      }

      #attendance-container .gap-4 {
        gap: 0.75rem !important;
      }

      #attendance-container .grid-cols-5 {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
      }
    }
  </style>

<div id="attendance-body" style="padding:16px; display:block; min-height:200px;">
  
  <div class="bg-white dark:bg-slate-800" style="border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.1); padding:24px; max-width:<?= htmlspecialchars($attendanceContainerMaxWidth) ?>; margin:0 auto;" id="attendance-container">
    <?php if ($canManageWorkHours || $canManageManualAttendance || $canManageAttendanceReset): ?>
      <div class="mb-6 rounded-xl border border-indigo-100 bg-indigo-50 p-4 dark:border-indigo-900/40 dark:bg-indigo-900/20">
        <div class="mb-3 text-sm font-semibold text-indigo-800 dark:text-indigo-300">Panel Manajemen Absensi</div>
        <div class="flex flex-wrap gap-2">
          <?php if ($canManageWorkHours): ?>
          <button type="button" id="btnOpenWorkHours" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-600 dark:bg-emerald-700 text-white text-sm font-semibold hover:bg-emerald-700 dark:hover:bg-emerald-600">
            <i class="fas fa-clock"></i> Jam Kerja
          </button>
          <?php endif; ?>
          <?php if ($canManageManualAttendance): ?>
          <button type="button" id="btnOpenManualAttendance" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-indigo-600 dark:bg-indigo-700 text-white text-sm font-semibold hover:bg-indigo-700 dark:hover:bg-indigo-600">
            <i class="fas fa-user-plus"></i> Tambah Absensi Manual
          </button>
          <?php endif; ?>
          <?php if ($canManageAttendanceReset): ?>
          <a href="dashboard.php?page=attendance-reset" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-red-600 dark:bg-red-700 text-white text-sm font-semibold hover:bg-red-700 dark:hover:bg-red-600">
            <i class="fas fa-rotate-left"></i> Reset Absensi
          </a>
          <?php endif; ?>
          <?php if ($canManageManualAttendance): ?>
          <button type="button" id="btnOpenResetNightShift" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-violet-600 dark:bg-violet-700 text-white text-sm font-semibold hover:bg-violet-700 dark:hover:bg-violet-600">
            <i class="fas fa-moon"></i> Reset Absen Malam
          </button>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
    
    <?php if ($canManageManualAttendance): ?>
    
    <div id="modalManualAttendance" class="fixed inset-0 items-center justify-center p-4" style="display:none; z-index:2000;">
      <div class="attendance-modal-card bg-white dark:bg-slate-800 rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto" style="box-shadow:0 24px 64px rgba(2, 6, 23, 0.45);">
          <div class="sticky top-0 bg-white dark:bg-slate-800 border-b border-gray-200 dark:border-slate-600 p-4 rounded-t-xl">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold text-indigo-900 dark:text-indigo-300">Absensi Manual</h3>
              <button type="button" id="btnCloseManualAttendance" class="text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-300 text-xl"><i class="fas fa-times"></i></button>
            </div>
          </div>

          <form id="admin_attendance_form" method="post" enctype="multipart/form-data" class="p-4 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
            <input type="hidden" name="tab" value="admin_manual_attendance">

            
            <div>
              <label class="block text-sm font-medium text-indigo-700 dark:text-indigo-400 mb-2">Pilih Karyawan</label>
              <select name="manual_user_id" required class="w-full border border-indigo-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">-- Pilih Karyawan --</option>
                <?php
                
                $stmt = $pdo->query("SELECT id, full_name, username FROM users WHERE role NOT IN ('administrator','direktur') ORDER BY full_name ASC");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                  $name = htmlspecialchars($row['full_name'] ?: $row['username']);
                  echo "<option value='" . (int)$row['id'] . "'>" . $name . "</option>";
                }
                ?>
              </select>
            </div>

            
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-indigo-700 dark:text-indigo-400 mb-2">Tanggal</label>
                <input type="date" name="manual_attendance_date" required class="w-full border border-indigo-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500" max="<?= date('Y-m-d') ?>" />
              </div>
              <div>
                <label class="block text-sm font-medium text-indigo-700 dark:text-indigo-400 mb-2">Status</label>
                <select name="manual_status" required class="w-full border border-indigo-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                  <option value="">-- Pilih --</option>
                  <option value="Masuk">Masuk</option>
                  <option value="Izin">Izin</option>
                  <option value="Sakit">Sakit</option>
                </select>
              </div>
            </div>

            
            <div id="timeFields" class="hidden space-y-3 p-3 bg-gray-50 dark:bg-slate-700 rounded-lg">
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-indigo-700 dark:text-indigo-400 mb-1">Jam Masuk</label>
                  <input type="time" name="manual_check_in_time" class="w-full border border-indigo-300 dark:border-slate-600 dark:bg-slate-600 dark:text-white rounded-lg px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-indigo-700 dark:text-indigo-400 mb-1">Jam Pulang</label>
                  <input type="time" name="manual_check_out_time" class="w-full border border-indigo-300 dark:border-slate-600 dark:bg-slate-600 dark:text-white rounded-lg px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
              </div>
            </div>

            
            <div>
              <label class="block text-sm font-medium text-indigo-700 dark:text-indigo-400 mb-2">Catatan (opsional)</label>
              <textarea name="manual_notes" class="w-full border border-indigo-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder:text-slate-400 rounded-lg px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Alasan atau catatan tambahan" rows="2"></textarea>
            </div>

            
            <div>
              <label class="block text-sm font-medium text-indigo-700 dark:text-indigo-400 mb-2">Foto Bukti (opsional)</label>
              <input type="file" name="manual_photo" accept="image/*" class="w-full border border-indigo-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 dark:file:bg-indigo-900/30 file:text-indigo-700 dark:file:text-indigo-400 hover:file:bg-indigo-100 dark:hover:file:bg-indigo-900/40" />
              <p class="text-xs text-gray-500 dark:text-slate-400 mt-1"><i class="fas fa-camera mr-1"></i>Upload foto bukti absensi (JPG, PNG, max 5MB)</p>
              <div id="photoPreview" class="mt-2 hidden">
                <img id="previewImg" class="max-w-full h-24 object-cover rounded-lg border border-gray-300 dark:border-slate-600" alt="Preview" />
              </div>
            </div>

            
            <div class="sticky bottom-0 bg-white dark:bg-slate-800 border-t border-gray-200 dark:border-slate-600 pt-4 mt-6">
              <div class="flex justify-end gap-3">
                <button type="button" id="btnCancelManualAttendance" class="px-4 py-2 rounded-lg bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-slate-300 hover:bg-gray-300 dark:hover:bg-slate-600 font-medium">
                  Batal
                </button>
                <button type="submit" class="px-6 py-2 rounded-lg bg-indigo-600 dark:bg-indigo-700 text-white font-semibold hover:bg-indigo-700 dark:hover:bg-indigo-600 shadow-md">
                  Simpan Absensi
                </button>
              </div>
            </div>
          </form>
        </div>
    </div>
    <?php endif; ?>

    
    <?php if ($canManageManualAttendance): ?>
    <div id="modalResetNightShift" class="fixed inset-0 items-center justify-center p-4" style="display:none; z-index:2000;">
      <div class="attendance-modal-card bg-white dark:bg-slate-800 rounded-xl w-full max-w-md" style="box-shadow:0 24px 64px rgba(2,6,23,0.45);">
        <div class="sticky top-0 bg-white dark:bg-slate-800 border-b border-gray-200 dark:border-slate-600 p-4 rounded-t-xl">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-violet-900 dark:text-violet-300"><i class="fas fa-moon mr-2"></i>Reset Absen Malam</h3>
            <button type="button" id="btnCloseResetNightShift" class="text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-300 text-xl"><i class="fas fa-times"></i></button>
          </div>
        </div>
        <form method="post" class="p-4 space-y-4">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
          <input type="hidden" name="tab" value="reset_night_shift">

          
          <div class="p-3 rounded-lg bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-800 text-sm text-violet-800 dark:text-violet-300">
            <i class="fas fa-info-circle mr-1"></i>
            Absen malam disimpan pada <strong>tanggal kemarin</strong> sebagai tanggal efektif shift malam.
            Gunakan fitur ini untuk mengembalikan record tersebut ke status <strong>Alpha</strong> sehingga karyawan bisa absen ulang.
          </div>

          
          <div>
            <label class="block text-sm font-medium text-violet-700 dark:text-violet-400 mb-2">Karyawan</label>
            <select name="reset_night_user_id" required class="w-full border border-violet-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-3 py-2 focus:border-violet-500 focus:ring-violet-500">
              <option value="">-- Pilih Karyawan --</option>
              <?php
              $rstStmt = $pdo->query("SELECT id, full_name, username FROM users WHERE role NOT IN ('administrator','direktur') ORDER BY full_name ASC");
              while ($rstRow = $rstStmt->fetch(PDO::FETCH_ASSOC)) {
                $rName = htmlspecialchars($rstRow['full_name'] ?: $rstRow['username']);
                echo "<option value='" . (int)$rstRow['id'] . "'>{$rName}</option>";
              }
              ?>
            </select>
          </div>

          
          <div>
            <label class="block text-sm font-medium text-violet-700 dark:text-violet-400 mb-2">Tanggal Efektif Shift Malam</label>
            <select name="reset_night_date" required class="w-full border border-violet-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-3 py-2 focus:border-violet-500 focus:ring-violet-500">
              <?php
              $yesterday = date('Y-m-d', strtotime('-1 day'));
              $todayFmt  = date('Y-m-d');
              echo "<option value='{$yesterday}' selected>Kemarin — {$yesterday} (paling umum untuk shift malam)</option>";
              echo "<option value='{$todayFmt}'>Hari ini — {$todayFmt}</option>";
              ?>
            </select>
            <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">
              <i class="fas fa-info-circle mr-1"></i>Shift malam yang dimulai malam ini dicatat ke tanggal <strong>kemarin</strong> (ketika diakses setelah tengah malam).
            </p>
          </div>

          
          <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm text-amber-800 dark:text-amber-300">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            Aksi ini akan menghapus data check-in/out karyawan dan mengembalikan status ke <strong>Alpha</strong>. Tidak dapat dibatalkan.
          </div>

          <div class="flex justify-end gap-3 pt-2">
            <button type="button" id="btnCancelResetNightShift" class="px-4 py-2 rounded-lg bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-slate-300 hover:bg-gray-300 dark:hover:bg-slate-600 font-medium">
              Batal
            </button>
            <button type="submit" class="px-5 py-2 rounded-lg bg-violet-600 dark:bg-violet-700 text-white font-semibold hover:bg-violet-700 dark:hover:bg-violet-600 shadow-md"
              onclick="return confirm('Yakin ingin me-reset absen malam karyawan ini? Data check-in/out akan dihapus.')">
              <i class="fas fa-rotate-left mr-1"></i> Reset
            </button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>
    
    
    <?php if ($canManageWorkHours): ?>
    <div id="modalWorkHours" class="fixed inset-0 items-center justify-center p-4" style="display:none; z-index:2000;">
      <div class="attendance-modal-card bg-white dark:bg-slate-800 rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto" style="box-shadow:0 24px 64px rgba(2, 6, 23, 0.45);">
          <div class="sticky top-0 bg-white dark:bg-slate-800 border-b border-gray-200 dark:border-slate-600 p-4 rounded-t-xl">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold text-indigo-900 dark:text-indigo-300"><i class="fas fa-clock mr-2"></i>Pengaturan Jam Kerja</h3>
              <button type="button" id="btnCloseWorkHours" class="text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-300 text-xl"><i class="fas fa-times"></i></button>
            </div>
          </div>
          <form method="post" class="p-4 space-y-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
            <input type="hidden" name="tab" value="save_work_hours">
            <?php
            $dayLabels = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
            for ($d = 0; $d <= 6; $d++):
              $wh = $allWorkHours[$d] ?? ['start' => '08:30', 'end' => '17:30', 'label' => $dayLabels[$d]];
              $isOff = ($wh['start'] === 'off');
            ?>
            <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-slate-700/50" id="whRow<?= $d ?>">
              <div class="w-20 text-sm font-semibold text-indigo-800 dark:text-indigo-300"><?= $dayLabels[$d] ?></div>
              <label class="flex items-center gap-1 text-xs text-gray-500 dark:text-slate-400 cursor-pointer">
                <input type="checkbox" name="wh_off_<?= $d ?>" class="wh-off-toggle rounded" data-day="<?= $d ?>" <?= $isOff ? 'checked' : '' ?>> Libur
              </label>
              <input type="time" name="wh_start_<?= $d ?>" value="<?= $isOff ? '08:30' : htmlspecialchars($wh['start']) ?>" class="wh-time-input flex-1 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-2 py-1 text-sm <?= $isOff ? 'opacity-40' : '' ?>" <?= $isOff ? 'disabled' : '' ?>>
              <span class="text-gray-400 text-xs">s/d</span>
              <input type="time" name="wh_end_<?= $d ?>" value="<?= $isOff ? '17:30' : htmlspecialchars($wh['end']) ?>" class="wh-time-input flex-1 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-2 py-1 text-sm <?= $isOff ? 'opacity-40' : '' ?>" <?= $isOff ? 'disabled' : '' ?>>
            </div>
            <?php endfor; ?>
            <div class="rounded-lg bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-900/40 p-3 space-y-3">
              <div class="text-xs font-semibold text-indigo-800 dark:text-indigo-300 uppercase tracking-wide">Pengaturan Tambahan</div>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                  <label class="block text-xs text-indigo-700 dark:text-indigo-300 mb-1">Toleransi Telat (menit)</label>
                  <input type="number" name="wh_late_tolerance_minutes" min="0" max="180" value="<?= (int)$lateToleranceMinutes ?>" class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-2 py-1.5 text-sm">
                </div>
                <div>
                  <label class="block text-xs text-indigo-700 dark:text-indigo-300 mb-1">Toleransi Pulang Cepat (menit)</label>
                  <input type="number" name="wh_early_leave_tolerance_minutes" min="0" max="180" value="<?= (int)$earlyLeaveToleranceMinutes ?>" class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-2 py-1.5 text-sm">
                </div>
              </div>
              <div>
                <label class="block text-xs text-indigo-700 dark:text-indigo-300 mb-1">Jam Mulai Lembur</label>
                <input type="time" name="wh_overtime_threshold_time" value="<?= htmlspecialchars($OVERTIME_THRESHOLD_DISPLAY) ?>" class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-2 py-1.5 text-sm">
                <p class="text-[11px] text-gray-500 dark:text-slate-400 mt-1">Status Lembur akan muncul jika checkout sama atau lewat jam ini.</p>
              </div>

              
              <div class="mt-2 text-xs font-semibold text-indigo-800 dark:text-indigo-300 uppercase tracking-wide"><i class="fas fa-moon mr-1"></i>Pengaturan Absen Malam</div>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                  <label class="block text-xs text-indigo-700 dark:text-indigo-300 mb-1">Jam Mulai Malam</label>
                  <input type="time" name="night_start_time" value="<?= htmlspecialchars(substr($nightStartConf,0,5)) ?>" class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-2 py-1.5 text-sm">
                </div>
                <div>
                  <label class="block text-xs text-indigo-700 dark:text-indigo-300 mb-1">Jam Selesai Malam</label>
                  <input type="time" name="night_end_time" value="<?= htmlspecialchars(substr($nightEndConf,0,5)) ?>" class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-2 py-1.5 text-sm">
                </div>
                <div>
                  <label class="block text-xs text-indigo-700 dark:text-indigo-300 mb-1">Buka Absen (menit sebelum)</label>
                  <input type="number" min="0" max="300" name="night_checkin_open_mins" value="<?= (int)$nightOpenMinsConf ?>" class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-2 py-1.5 text-sm">
                </div>
              </div>
              <p class="text-[11px] text-gray-500 dark:text-slate-400 mt-1"><i class="fas fa-info-circle mr-1"></i>Maksimal 12 jam. Contoh: 18:00 – 06:00 (12 jam), 20:00 – 06:00 (10 jam).</p>
            </div>
            <div class="sticky bottom-0 bg-white dark:bg-slate-800 border-t border-gray-200 dark:border-slate-600 pt-4 mt-4">
              <div class="flex justify-end gap-3">
                <button type="button" id="btnCancelWorkHours" class="px-4 py-2 rounded-lg bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-slate-300 hover:bg-gray-300 dark:hover:bg-slate-600 font-medium">Batal</button>
                <button type="submit" class="px-6 py-2 rounded-lg bg-emerald-600 dark:bg-emerald-700 text-white font-semibold hover:bg-emerald-700 dark:hover:bg-emerald-600 shadow-md"><i class="fas fa-save mr-1"></i> Simpan</button>
              </div>
            </div>
          </form>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($hasLeaveToday && !$canManageAttendanceOverview): ?>
      <h2 class="text-lg font-semibold mb-3 text-indigo-900 dark:text-indigo-300">Status Hari Ini</h2>
      <div class="border border-indigo-200 dark:border-slate-600 rounded-2xl p-4 bg-indigo-50 dark:bg-indigo-900/20 mb-4">
        <div class="flex items-start gap-4">
          <div class="w-20 h-20 rounded-xl bg-indigo-100 dark:bg-slate-700 flex items-center justify-center overflow-hidden">
            <?php if (!empty($myAttendance['check_in_photo'])): ?>
              <img src="<?= htmlspecialchars(assetUrl($myAttendance['check_in_photo'])) ?>" class="w-full h-full object-cover" alt="Bukti" />
            <?php else: ?>
              <span class="text-xs text-indigo-900 dark:text-indigo-400 font-medium">Bukti</span>
            <?php endif; ?>
          </div>
          <div class="flex-1">
            <div class="text-sm text-indigo-700 dark:text-indigo-400">Status</div>
            <div class="text-lg font-bold text-indigo-900 dark:text-indigo-300"><?= htmlspecialchars($myAttendance['status'] ?? '-') ?></div>
            <div class="text-sm mt-2 text-indigo-800 dark:text-indigo-400">Keterangan: <?= htmlspecialchars($myAttendance['notes'] ?? '-') ?></div>
          </div>
        </div>
      </div>
    <?php else: ?>
    
      
      <?php if (!$canManageAttendanceOverview): ?>
      <?php
        $isNight = $nightOverride;

        
        
        $hasNightAttendanceToday = false;
        $nightSessionDateForTab = ($nowTsForNightWindow >= $nightWindowYesterdayOpenTs && $nowTsForNightWindow <= $nightWindowYesterdayCloseTs)
            ? date('Y-m-d', strtotime($todayOriginal . ' -1 day'))
            : $todayOriginal;
        $nightAttendanceForTab = $myAttendance;

        if ($nightSessionDateForTab !== $today) {
          try {
            $stmtNightTab = $pdo->prepare('SELECT * FROM attendances WHERE user_id = ? AND attendance_date = ? LIMIT 1');
            $stmtNightTab->execute([$currentUserId, $nightSessionDateForTab]);
            $nightAttendanceForTab = $stmtNightTab->fetch(PDO::FETCH_ASSOC) ?: null;
          } catch (Exception $e) {   }
        }

        $hasDayAttendanceToday = $myAttendance
          && !in_array($myAttendance['status'] ?? '', $leaveStatuses, true)
          && !empty($myAttendance['check_in_time'])
          && $myAttendance['check_in_time'] !== '0000-00-00 00:00:00'
          && !isAttendanceNightShiftRowLocal($myAttendance, $nightStartConf, $nightEndConf);

        if ($myAttendance
            && !empty($myAttendance['check_in_time'])
            && $myAttendance['check_in_time'] !== '0000-00-00 00:00:00'
            && isAttendanceNightShiftRowLocal($myAttendance, $nightStartConf, $nightEndConf)) {
          $hasNightAttendanceToday = true;
        }

        
        
        $isNightWindowForTab = $isNightAttendanceWindowOpen;

        
        
        $showDayTab = !$hasNightAttendanceToday;
        if ($isNight && $hasNightAttendanceToday) {
          $showDayTab = false;
        }

        $hasStartedNightAttendanceForTab = $nightAttendanceForTab
          && !in_array($nightAttendanceForTab['status'] ?? '', $leaveStatuses, true)
          && !empty($nightAttendanceForTab['check_in_time'])
          && $nightAttendanceForTab['check_in_time'] !== '0000-00-00 00:00:00';
        $isNightAttendanceCompleteForTab = $hasStartedNightAttendanceForTab
          && !empty($nightAttendanceForTab['check_out_time'])
          && $nightAttendanceForTab['check_out_time'] !== '0000-00-00 00:00:00';

        
        $showNightTab = $isNightWindowForTab && !$hasDayAttendanceToday && (!$hasStartedAttendance || $isNight || ($hasStartedNightAttendanceForTab && !$isNightAttendanceCompleteForTab));
      ?>
      <div class="grid grid-cols-2 sm:flex sm:flex-wrap gap-2 border-b border-gray-200 dark:border-slate-600 mb-6 pb-3">
        <?php if ($showDayTab): ?>
        <a href="dashboard.php?page=absence&tab=attendance&night=0" class="py-2 px-4 text-sm font-medium <?= (!$isNight && $activeTab === 'attendance') ? 'text-indigo-600 dark:text-indigo-400 border-b-2 border-indigo-600 dark:border-indigo-400' : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-300' ?>">
          <i class="fas fa-sun mr-1"></i> Absen Siang
        </a>
        <?php endif; ?>
        <?php if ($showNightTab): ?>
        <a href="dashboard.php?page=absence&tab=attendance&night=1" class="py-2 px-4 text-sm font-medium <?= $isNight ? 'text-indigo-600 dark:text-indigo-400 border-b-2 border-indigo-600 dark:border-indigo-400' : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-300' ?>">
          <i class="fas fa-moon mr-1"></i> Absen Malam
        </a>
        <?php endif; ?>
        <?php if ($hasPendingAttendanceRequest): ?>
          <span class="py-2 px-4 text-sm font-medium text-gray-400 dark:text-slate-500 cursor-not-allowed" title="Request absensi menunggu approval admin">
            Request Absensi (Pending)
          </span>
        <?php elseif (!$hasStartedAttendance && !$hasLeaveToday): ?>
          <a href="dashboard.php?page=absence&tab=attendance_request" class="py-2 px-4 text-sm font-medium <?= $activeTab === 'attendance_request' ? 'text-indigo-600 dark:text-indigo-400 border-b-2 border-indigo-600 dark:border-indigo-400' : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-300' ?>">
            Request Absensi
          </a>
        <?php endif; ?>
        <?php if (!$canAccessLeaveTabs): ?>
          <span class="py-2 px-4 text-sm font-medium text-gray-400 dark:text-slate-500 cursor-not-allowed" title="Menunggu approval untuk permintaan sebelumnya">
            Permission Form (Pending)
          </span>
          <span class="py-2 px-4 text-sm font-medium text-gray-400 dark:text-slate-500 cursor-not-allowed" title="Menunggu approval untuk permintaan sebelumnya">
            Request Sick Leave (Pending)
          </span>
        <?php elseif ($hasLeaveToday): ?>
          <span class="py-2 px-4 text-sm font-medium text-gray-400 dark:text-slate-500 cursor-not-allowed" title="Sedang dalam masa cuti/izin hari ini">
            Permission Form (Disabled)
          </span>
          <span class="py-2 px-4 text-sm font-medium text-gray-400 dark:text-slate-500 cursor-not-allowed" title="Sedang dalam masa cuti/izin hari ini">
            Request Sick Leave (Disabled)
          </span>
        <?php elseif ($hasStartedAttendance): ?>
          
          <a href="dashboard.php?page=absence&tab=sick_leave" class="py-2 px-4 text-sm font-medium <?= $activeTab === 'sick_leave' ? 'text-indigo-600 dark:text-indigo-400 border-b-2 border-indigo-600 dark:border-indigo-400' : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-300' ?>">
            Request Sick Leave
          </a>
        <?php else: ?>
          <a href="dashboard.php?page=absence&tab=permission" class="py-2 px-4 text-sm font-medium <?= $activeTab === 'permission' ? 'text-indigo-600 dark:text-indigo-400 border-b-2 border-indigo-600 dark:border-indigo-400' : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-300' ?>">
            Permission Form
          </a>
          <a href="dashboard.php?page=absence&tab=sick_leave" class="py-2 px-4 text-sm font-medium <?= $activeTab === 'sick_leave' ? 'text-indigo-600 dark:text-indigo-400 border-b-2 border-indigo-600 dark:border-indigo-400' : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-300' ?>">
            Request Sick Leave
          </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    
      <?php if (false && !$canManageAttendanceOverview && $activeTab === 'attendance'): ?>
      <?php
        $label = 'Siang';
        if ($IS_CROSS_MIDNIGHT || (strtotime($WORK_END_TIME) <= strtotime($workStartTime))) {
          $label = 'Malam';
        }
        $startDisp = date('H:i', strtotime($SCHEDULED_START_DT));
        $endDisp = date('H:i', strtotime($SCHEDULED_END_DT));
        $schedTs = strtotime($SCHEDULED_START_DT);
        $openDisp = $schedTs ? date('H:i', $schedTs - ($CHECKIN_OPEN_MINS * 60)) : '';
        $closeDisp = $schedTs ? date('H:i', $schedTs + ($CHECKIN_CLOSE_MINS * 60)) : '';
      ?>
      <div class="mb-4 p-3 rounded-xl border border-indigo-200 dark:border-indigo-900/40 bg-indigo-50 dark:bg-indigo-900/20 flex items-center gap-3">
        <div class="shrink-0 w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center">
          <i class="fas fa-clock text-sm"></i>
        </div>
        <div class="text-sm">
          <div class="font-semibold text-indigo-900 dark:text-indigo-200">Shift Hari Ini: <?= $label ?> (<?= $startDisp ?>–<?= $endDisp ?>)</div>
          <div class="text-indigo-800 dark:text-indigo-300 text-xs">Jendela Check-in: <?= $openDisp ?> – <?= $closeDisp ?> (±<?= (int)$CHECKIN_OPEN_MINS ?>m / +<?= (int)$CHECKIN_CLOSE_MINS ?>m)</div>
        </div>
      </div>
      <?php endif; ?>

    <?php if ($hasPendingLeaveRequest && !$canManageAttendanceOverview): ?>
    <div class="mb-4 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
      <div class="flex items-start gap-3">
        <div class="flex-shrink-0">
          <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
          </svg>
        </div>
        <div>
          <h3 class="text-sm font-semibold text-amber-800 dark:text-amber-400">Menunggu Persetujuan</h3>
          <p class="text-sm text-amber-700 dark:text-amber-300">Anda memiliki permintaan izin/sakit yang sedang menunggu persetujuan admin. Tidak dapat mengajukan permintaan baru hingga permintaan sebelumnya diproses.</p>
        </div>
      </div>
    </div>
    <?php endif; ?>
    
    <?php if ($activeTab === 'attendance'): ?>
      <?php if ($canManageAttendanceOverview): ?>
        
        <h2 class="text-lg font-semibold mb-4 text-indigo-900 dark:text-indigo-300">Absensi Hari Ini — <?= date('d M Y') ?></h2>
        <?php
          $attStmt = $pdo->prepare("
            SELECT a.*, u.full_name, u.username, u.role 
            FROM attendances a 
            JOIN users u ON u.id = a.user_id 
            WHERE u.role NOT IN ('administrator','direktur','customer')
              AND (
                a.attendance_date = CURDATE()
                OR (
                  a.attendance_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                  AND a.is_cross_midnight = 1
                )
              )
            ORDER BY u.full_name ASC
          ");
          $attStmt->execute();
          $todayAttendancesRaw = $attStmt->fetchAll(PDO::FETCH_ASSOC);

          
          
          $yesterdayDate = date('Y-m-d', strtotime($todayOriginal . ' -1 day'));
          $lastNightAttendanceInfo = [];
          $nightStartHourForBadge = (int)substr($nightStartConf, 0, 2);
          $nightEndHourForBadge = (int)substr($nightEndConf, 0, 2);
          foreach ($todayAttendancesRaw as $attRaw) {
            $uid = (int)($attRaw['user_id'] ?? 0);
            if ($uid <= 0) continue;

            $attDate = (string)($attRaw['attendance_date'] ?? '');
            if ($attDate !== $yesterdayDate) continue;

            $ciRaw = (string)($attRaw['check_in_time'] ?? '');
            if ($ciRaw === '' || $ciRaw === '0000-00-00 00:00:00') continue;

            $ciTs = strtotime($ciRaw);
            if (!$ciTs) continue;

            
            
            
            
            if (empty($attRaw['is_cross_midnight']) || (int)$attRaw['is_cross_midnight'] !== 1) {
                continue;
            }

            $checkInTime = date('H:i:s', $ciTs);
            $nightStartTime = strlen($nightStartConf) === 5 ? $nightStartConf . ':00' : $nightStartConf;
            $nightEndTime = strlen($nightEndConf) === 5 ? $nightEndConf . ':00' : $nightEndConf;

            
            $isNight = false;
            if (strtotime($nightEndTime) <= strtotime($nightStartTime)) {
                $isNight = ($checkInTime >= $nightStartTime) || ($checkInTime < $nightEndTime);
            } else {
                $isNight = ($checkInTime >= $nightStartTime) && ($checkInTime < $nightEndTime);
            }
            if (!$isNight) continue;

            if (!isset($lastNightAttendanceInfo[$uid]) || $ciTs > (int)$lastNightAttendanceInfo[$uid]['timestamp']) {
              $lastNightAttendanceInfo[$uid] = [
                'timestamp' => $ciTs,
                'date_label' => date('d M Y', strtotime($attDate)),
                'date_short' => date('d/m', strtotime($attDate)),
                'time' => date('H:i', $ciTs),
              ];
            }
          }

          
          
          $attendanceByUser = [];
          foreach ($todayAttendancesRaw as $attRow) {
            $uid = (int)($attRow['user_id'] ?? 0);
            if ($uid <= 0) continue;

            $newDate = (string)($attRow['attendance_date'] ?? '');
            $newIsToday = ($newDate === $todayOriginal);
            $newCheckInTs = (!empty($attRow['check_in_time']) && $attRow['check_in_time'] !== '0000-00-00 00:00:00')
              ? strtotime($attRow['check_in_time'])
              : strtotime($newDate . ' 00:00:00');

            if (!isset($attendanceByUser[$uid])) {
              $attendanceByUser[$uid] = $attRow;
              continue;
            }

            $existing = $attendanceByUser[$uid];
            $existingDate = (string)($existing['attendance_date'] ?? '');
            $existingIsToday = ($existingDate === $todayOriginal);
            $existingCheckInTs = (!empty($existing['check_in_time']) && $existing['check_in_time'] !== '0000-00-00 00:00:00')
              ? strtotime($existing['check_in_time'])
              : strtotime($existingDate . ' 00:00:00');

            if ($newIsToday && !$existingIsToday) {
              $attendanceByUser[$uid] = $attRow;
              continue;
            }
            if ($newIsToday === $existingIsToday && $newCheckInTs > $existingCheckInTs) {
              $attendanceByUser[$uid] = $attRow;
            }
          }

          $todayAttendances = array_values($attendanceByUser);
          usort($todayAttendances, function($a, $b) {
            $an = strtolower((string)($a['full_name'] ?: $a['username'] ?? ''));
            $bn = strtolower((string)($b['full_name'] ?: $b['username'] ?? ''));
            return $an <=> $bn;
          });

          $allUsersStmt = $pdo->prepare("SELECT id, full_name, username, role FROM users WHERE is_active=1 AND role NOT IN ('administrator','direktur','customer') ORDER BY full_name ASC");
          $allUsersStmt->execute();
          $allUsers = $allUsersStmt->fetchAll(PDO::FETCH_ASSOC);
          $attendedIds = array_map('intval', array_column($todayAttendances, 'user_id'));
          $notAttended = array_filter($allUsers, fn($u) => !in_array((int)$u['id'], $attendedIds, true));

          $statusIcon = [
            'Hadir' => '<i class="fas fa-check-circle text-green-500"></i>', 'Terlambat' => '<i class="fas fa-clock text-amber-500"></i>', 'Not Checked Out' => '<i class="fas fa-exclamation-circle text-red-500"></i>',
            'Izin' => '<i class="fas fa-file-medical-alt text-blue-500"></i>', 'Sakit' => '<i class="fas fa-hospital-user text-purple-500"></i>', 'Alpha' => '<i class="fas fa-times-circle text-red-500"></i>', 'Cuti' => '<i class="fas fa-umbrella-beach text-indigo-500"></i>', 'Lembur' => '<i class="fas fa-dumbbell text-cyan-500"></i>'
          ];
          $statusColor = [
            'Hadir' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
            'Terlambat' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            'Not Checked Out' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            'Izin' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            'Sakit' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
            'Alpha' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
            'Cuti' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
            'Lembur' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
          ];
          $statusLabelMap = [
            'Alpha' => 'Tidak Hadir tanpa Keterangan',
          ];
        ?>
        
        <div class="mb-4 grid grid-cols-2 gap-2 md:grid-cols-4">
          <?php
            $countByStatus = [];
            foreach ($todayAttendances as $a) { $s = $a['status'] ?? 'Alpha'; $countByStatus[$s] = ($countByStatus[$s] ?? 0) + 1; }
            $totalHadir = ($countByStatus['Hadir'] ?? 0) + ($countByStatus['Terlambat'] ?? 0) + ($countByStatus['Not Checked Out'] ?? 0) + ($countByStatus['Lembur'] ?? 0);
            $totalIzin = ($countByStatus['Izin'] ?? 0) + ($countByStatus['Sakit'] ?? 0) + ($countByStatus['Cuti'] ?? 0);
            $totalAlpha = ($countByStatus['Alpha'] ?? 0) + count($notAttended);
          ?>
          <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-3 text-center">
            <div class="text-2xl font-bold text-green-600 dark:text-green-400"><?= $totalHadir ?></div>
            <div class="text-xs text-green-700 dark:text-green-400">Hadir</div>
          </div>
          <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-3 text-center">
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400"><?= $totalIzin ?></div>
            <div class="text-xs text-blue-700 dark:text-blue-400">Izin/Sakit/Cuti</div>
          </div>
          <div class="bg-red-50 dark:bg-red-900/20 rounded-xl p-3 text-center">
            <div class="text-2xl font-bold text-red-600 dark:text-red-400"><?= $totalAlpha ?></div>
            <div class="text-xs text-red-700 dark:text-red-400">Tidak Hadir tanpa Keterangan</div>
          </div>
          <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-3 text-center">
            <div class="text-2xl font-bold text-gray-600 dark:text-gray-400"><?= count($allUsers) ?></div>
            <div class="text-xs text-gray-700 dark:text-gray-400">Total Staff</div>
          </div>
        </div>

        
        <?php
          $groupedList = [];
          foreach ($todayAttendances as $att) {
              $roleKey = $att['role'] ?? 'staff';
              $groupedList[$roleKey]['attended'][] = $att;
          }
          foreach ($notAttended as $nau) {
              $roleKey = $nau['role'] ?? 'staff';
              $groupedList[$roleKey]['not_attended'][] = $nau;
          }
          ksort($groupedList);
        ?>
        <div class="space-y-4">
          <?php if (empty($groupedList)): ?>
            <div class="text-center text-gray-500 dark:text-gray-400 py-8">Belum ada data absensi hari ini</div>
          <?php else: ?>
            <?php foreach ($groupedList as $roleKey => $roleData): ?>
              <div class="bg-gray-50 dark:bg-slate-800/50 rounded-xl p-3 border border-gray-100 dark:border-slate-700">
                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-3 px-2 flex items-center gap-2">
                  <i class="fas fa-users text-indigo-500"></i>
                  <?= htmlspecialchars(roleLabel($roleKey)) ?>
                  <span class="text-xs font-normal bg-gray-200 dark:bg-slate-700 px-2 py-0.5 rounded-full">
                    <?php
                      $roleTotal = count($roleData['attended'] ?? []) + count($roleData['not_attended'] ?? []);
                    ?>
                    <?= $roleTotal ?> Staff
                  </span>
                </h3>
                <div class="space-y-2">
                  <?php if (!empty($roleData['attended'])): ?>
                    <?php foreach ($roleData['attended'] as $att): 
                      $st = $att['status'] ?? 'Alpha';
                      $stLabel = $statusLabelMap[$st] ?? $st;
                      $icon = $statusIcon[$st] ?? '<i class="fas fa-question-circle text-gray-500"></i>';
                      $color = $statusColor[$st] ?? 'bg-gray-100 text-gray-700';
                      $cin = ($att['check_in_time'] && $att['check_in_time'] !== '0000-00-00 00:00:00') ? date('H:i', strtotime($att['check_in_time'])) : '-';
                      $cout = ($att['check_out_time'] && $att['check_out_time'] !== '0000-00-00 00:00:00') ? date('H:i', strtotime($att['check_out_time'])) : '-';
                      $name = htmlspecialchars($att['full_name'] ?: $att['username']);
                      $nightInfo = $lastNightAttendanceInfo[(int)($att['user_id'] ?? 0)] ?? null;
                      $isLastNightAttendance = !empty($nightInfo);
                      $nightBadgeText = '';
                      if ($isLastNightAttendance) {
                        $nightBadgeText = 'Shift Malam ' . $nightInfo['date_label'] . ' ' . $nightInfo['time'];
                      }
                    ?>
                      <div class="flex items-center gap-3 p-2 bg-white dark:bg-slate-700/50 border border-gray-100 dark:border-slate-600 rounded-xl hover:shadow-sm transition">
                        <div class="text-xl w-8 text-center"><?= $icon ?></div>
                        <div class="flex-1 min-w-0">
                          <div class="font-medium text-sm text-gray-900 dark:text-white truncate flex items-center gap-2">
                            <span><?= $name ?></span>
                            <?php if ($isLastNightAttendance): ?>
                              <span class="inline-block text-[10px] px-1.5 py-0.5 rounded-full bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300" title="<?= htmlspecialchars($nightBadgeText) ?>">Malam <?= htmlspecialchars($nightInfo['date_short']) ?> <?= htmlspecialchars($nightInfo['time']) ?></span>
                            <?php endif; ?>
                          </div>
                          <?php if ($isLastNightAttendance): ?>
                            <div class="text-[10px] text-sky-700 dark:text-sky-300 mt-0.5"><?= htmlspecialchars($nightBadgeText) ?></div>
                          <?php endif; ?>
                        </div>
                        <div class="text-right flex-shrink-0">
                          <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold <?= $color ?>"><?= htmlspecialchars($stLabel) ?></span>
                          <?php if ($cin !== '-'): ?>
                            <div class="text-[10px] text-gray-400 mt-0.5"><?= $cin ?> — <?= $cout ?></div>
                          <?php endif; ?>
                        </div>
                        <button type="button" onclick="openEditAttendance(<?= (int)$att['id'] ?>, '<?= htmlspecialchars($name, ENT_QUOTES) ?>', '<?= htmlspecialchars($st, ENT_QUOTES) ?>', '<?= htmlspecialchars($att['notes'] ?? '', ENT_QUOTES) ?>', '<?= $cin ?>', '<?= $cout ?>')" class="ml-1 w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition" title="Edit">
                          <i class="fas fa-pen text-xs"></i>
                        </button>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                  
                  <?php if (!empty($roleData['not_attended'])): ?>
                    <?php foreach ($roleData['not_attended'] as $nau): 
                      $name = htmlspecialchars($nau['full_name'] ?: $nau['username']);
                      $nightInfo = $lastNightAttendanceInfo[(int)($nau['id'] ?? 0)] ?? null;
                      $isLastNightAttendance = !empty($nightInfo);
                      $nightBadgeText = $isLastNightAttendance ? ('Shift Malam ' . $nightInfo['date_label'] . ' ' . $nightInfo['time']) : '';
                    ?>
                      <div class="flex items-center gap-3 p-2 bg-white dark:bg-slate-700/50 border border-gray-100 dark:border-slate-600 rounded-xl opacity-60">
                        <div class="text-xl w-8 text-center"><i class="fas fa-times-circle text-red-500"></i></div>
                        <div class="flex-1 min-w-0">
                          <div class="font-medium text-sm text-gray-900 dark:text-white truncate flex items-center gap-2">
                            <span><?= $name ?></span>
                            <?php if ($isLastNightAttendance): ?>
                              <span class="inline-block text-[10px] px-1.5 py-0.5 rounded-full bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300" title="<?= htmlspecialchars($nightBadgeText) ?>">Malam <?= htmlspecialchars($nightInfo['date_short']) ?> <?= htmlspecialchars($nightInfo['time']) ?></span>
                            <?php endif; ?>
                          </div>
                          <?php if ($isLastNightAttendance): ?>
                            <div class="text-[10px] text-sky-700 dark:text-sky-300 mt-0.5"><?= htmlspecialchars($nightBadgeText) ?></div>
                          <?php endif; ?>
                        </div>
                        <div class="text-right flex-shrink-0">
                          <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">Belum Absen</span>
                        </div>
                        <div class="w-8 ml-1"></div> 
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        
        <div id="modalEditAttendance" class="fixed inset-0 items-center justify-center p-4" style="display:none; z-index:2000;">
          <div class="attendance-modal-card bg-white dark:bg-slate-800 rounded-xl w-full max-w-md p-5" style="box-shadow:0 24px 64px rgba(2, 6, 23, 0.45);">
            <div class="flex justify-between items-center mb-4">
              <h3 class="font-semibold text-gray-900 dark:text-white">Edit Absensi</h3>
              <button type="button" onclick="closeEditAttendanceModal()" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
            </div>
            <div id="editAttName" class="text-sm text-gray-500 dark:text-gray-400 mb-3"></div>
            <form id="formEditAttendance" method="post" class="space-y-3">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
              <input type="hidden" name="tab" value="admin_manual_attendance">
              <input type="hidden" name="edit_attendance_id" id="editAttId">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                <select name="edit_status" id="editAttStatus" required class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-3 py-2 text-sm">
                  <option value="Hadir">Hadir</option>
                  <option value="Terlambat">Terlambat</option>
                  <option value="Izin">Izin</option>
                  <option value="Sakit">Sakit</option>
                  <option value="Cuti">Cuti</option>
                  <option value="Alpha">Alpha</option>
                  <option value="Lembur">Lembur</option>
                </select>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jam Masuk</label>
                  <input type="time" name="edit_check_in" id="editAttCheckIn" class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jam Pulang</label>
                  <input type="time" name="edit_check_out" id="editAttCheckOut" class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-3 py-2 text-sm">
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Catatan</label>
                <textarea name="edit_notes" id="editAttNotes" rows="2" class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-3 py-2 text-sm"></textarea>
              </div>
              <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeEditAttendanceModal()" class="px-4 py-2 bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-white rounded-lg text-sm">Batal</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">Simpan</button>
              </div>
            </form>
          </div>
        </div>

        <script>
        const editAttendanceModal = document.getElementById('modalEditAttendance');
        if (editAttendanceModal && editAttendanceModal.parentElement !== document.body) {
          document.body.appendChild(editAttendanceModal);
        }

        function closeEditAttendanceModal() {
          if (!editAttendanceModal) return;
          editAttendanceModal.style.display = 'none';
          document.body.style.overflow = '';
        }

        if (editAttendanceModal) {
          editAttendanceModal.addEventListener('click', function(e) {
            if (e.target === editAttendanceModal) {
              closeEditAttendanceModal();
            }
          });
        }

        function openEditAttendance(id, name, status, notes, cin, cout) {
          document.getElementById('editAttId').value = id;
          document.getElementById('editAttName').textContent = name;
          document.getElementById('editAttStatus').value = status;
          document.getElementById('editAttNotes').value = notes;
          document.getElementById('editAttCheckIn').value = (cin && cin !== '-') ? cin : '';
          document.getElementById('editAttCheckOut').value = (cout && cout !== '-') ? cout : '';
          if (editAttendanceModal) {
            editAttendanceModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
          }
        }
        </script>
      <?php elseif (!$isCheckedIn): ?>
        <?php if ($hasMissedCheckoutPrevDay && !$canManageAttendanceOverview): ?>
        
        <div class="mb-4 p-4 rounded-2xl border border-red-200 dark:border-red-800 bg-gradient-to-br from-red-50 to-orange-50 dark:from-red-900/20 dark:to-orange-900/20">
          <div class="flex items-start gap-3">
            <div class="w-9 h-9 rounded-xl bg-red-500 text-white flex items-center justify-center flex-shrink-0">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
              <div class="font-semibold text-red-900 dark:text-red-200 mb-1">Absen Pulang Terlewat</div>
              <p class="text-sm text-red-800 dark:text-red-300">
                Anda belum absen pulang pada tanggal <strong><?= date('d M Y', strtotime($missedCheckoutDate)) ?></strong>.
                Anda harus mengajukan <strong>Request Absensi</strong> untuk mengisi jam pulang terlebih dahulu sebelum bisa absen masuk hari ini.
              </p>
              <a href="dashboard.php?page=absence&tab=attendance_request"
                 class="inline-block mt-3 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700 transition-colors">
                <i class="fas fa-paper-plane mr-1"></i> Ajukan Request Absensi
              </a>
            </div>
          </div>
        </div>
        <?php else: ?>
        
        <div class="mb-4">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-9 h-9 rounded-lg bg-indigo-600 dark:bg-indigo-700 text-white flex items-center justify-center">
              <i class="fas fa-sign-in-alt text-sm"></i>
            </div>
            <h2 class="text-lg font-bold text-slate-800 dark:text-white">Absensi Masuk <?= $nightOverride ? '<span class="text-sm font-normal text-slate-500">(Malam)</span>' : '' ?></h2>
          </div>
        </div>
        <form method="post" enctype="multipart/form-data" class="space-y-4">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
          <input type="hidden" name="tab" value="attendance">
          <input type="hidden" name="override_schedule" value="<?= $nightOverride ? 'night' : 'day' ?>">
          <input type="hidden" name="check_in_latitude" id="check_in_latitude" value="">
          <input type="hidden" name="check_in_longitude" id="check_in_longitude" value="">
          <input type="hidden" name="check_in_accuracy" id="check_in_accuracy" value="">
          <input type="hidden" name="check_in_location_name" id="check_in_location_name" value="">
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5"><i class="fas fa-clipboard-list mr-1.5 text-slate-400"></i>Plan Hari Ini</label>
            <input type="text" name="today_plan" required class="w-full border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder:text-slate-400 rounded-lg px-3 py-2.5 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition" placeholder="Rencana pekerjaan hari ini" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5"><i class="fas fa-camera mr-1.5 text-slate-400"></i>Foto Absen Masuk</label>
            <button type="button" onclick="openCamera('check_in')" class="w-full px-5 py-3 rounded-lg bg-indigo-600 dark:bg-indigo-700 text-white font-semibold hover:bg-indigo-700 dark:hover:bg-indigo-600 transition flex items-center justify-center gap-2">
              <i class="fas fa-camera"></i> Ambil Foto
            </button>
            <input type="file" name="check_in_photo" id="check_in_photo" accept="image/*" required onchange="renderSinglePreview(this, 'preview_checkin')" style="display: none;" />
            <div id="preview_checkin" class="mt-2"></div>
          </div>
          <div>
            <button type="submit" class="w-full px-4 py-3 rounded-lg bg-green-600 dark:bg-green-700 text-white font-semibold hover:bg-green-700 dark:hover:bg-green-600 transition flex items-center justify-center gap-2">
              <i class="fas fa-check"></i> Submit Absen Masuk
            </button>
          </div>
        </form>
        <?php endif;   ?>
      <?php elseif (!$isCheckedOut): ?>
        
        <div class="mb-4">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-9 h-9 rounded-lg bg-amber-600 dark:bg-amber-700 text-white flex items-center justify-center">
              <i class="fas fa-sign-out-alt text-sm"></i>
            </div>
            <h2 class="text-lg font-bold text-slate-800 dark:text-white">Activity & Absen Pulang <?= $nightOverride ? '<span class="text-sm font-normal text-slate-500">(Malam)</span>' : '' ?></h2>
          </div>
        </div>
        <form method="post" enctype="multipart/form-data" class="space-y-4">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
          <input type="hidden" name="tab" value="attendance">
          <input type="hidden" name="override_schedule" value="<?= $nightOverride ? 'night' : 'day' ?>">
          <input type="hidden" name="check_out_latitude" id="check_out_latitude" value="">
          <input type="hidden" name="check_out_longitude" id="check_out_longitude" value="">
          <input type="hidden" name="check_out_accuracy" id="check_out_accuracy" value="">
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5"><i class="fas fa-tasks mr-1.5 text-slate-400"></i>Aktivitas Hari Ini</label>
            <textarea name="today_activities" required class="w-full border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder:text-slate-400 rounded-lg px-3 py-2.5 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition" placeholder="Deskripsi aktivitas hari ini" rows="3"></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5"><i class="fas fa-images mr-1.5 text-slate-400"></i>Foto Activity (min 1, max 5)</label>
            <input type="file" name="activity_photos[]" id="activity_photos" accept="image/*" multiple class="block w-full text-sm text-slate-600 dark:text-white dark:bg-slate-700 dark:border-slate-600 border border-slate-300 rounded-lg px-3 py-2 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-slate-100 dark:file:bg-slate-600 file:text-slate-700 dark:file:text-slate-200 hover:file:bg-slate-200 dark:hover:file:bg-slate-500 transition" />
            <div id="preview_activities" class="mt-2 grid grid-cols-5 gap-2"></div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5"><i class="fas fa-camera mr-1.5 text-slate-400"></i>Foto Absen Pulang</label>
            <button type="button" onclick="openCamera('check_out')" class="w-full px-5 py-3 rounded-lg bg-indigo-600 dark:bg-indigo-700 text-white font-semibold hover:bg-indigo-700 dark:hover:bg-indigo-600 transition flex items-center justify-center gap-2">
              <i class="fas fa-camera"></i> Ambil Foto
            </button>
            <input type="file" name="check_out_photo" id="check_out_photo" accept="image/*" onchange="renderSinglePreview(this, 'preview_checkout')" style="display: none;" />
            <div id="preview_checkout" class="mt-2"></div>
            <p class="text-xs text-red-600 dark:text-red-400 mt-1" id="checkout_photo_error" style="display:none;">
              <i class="fas fa-exclamation-triangle mr-1"></i> Foto absen pulang wajib diambil sebelum submit
            </p>
          </div>
          <div>
            <button type="submit" id="submit_checkout_btn" class="w-full px-4 py-3 rounded-lg bg-green-600 dark:bg-green-700 text-white font-semibold hover:bg-green-700 dark:hover:bg-green-600 transition flex items-center justify-center gap-2">
              <i class="fas fa-check-double"></i> Submit & Selesai
            </button>
          </div>
        </form>
      <?php else: ?>
        
        <div class="mb-5">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-lg bg-indigo-600 dark:bg-indigo-700 text-white flex items-center justify-center">
              <i class="fas fa-calendar-check"></i>
            </div>
            <div>
              <div class="text-sm font-medium text-slate-500 dark:text-slate-400"><?= htmlspecialchars($currentUserName) ?> &middot; <?= htmlspecialchars(roleLabel($currentUserRole)) ?></div>
              <div class="text-lg font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($dayStr) ?> <?= htmlspecialchars($monthYear) ?></div>
            </div>
          </div>
        </div>

        <div class="rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
          <!-- Check In -->
          <div class="p-4 bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700">
            <div class="flex items-center gap-4">
              <div class="w-16 h-16 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center overflow-hidden flex-shrink-0">
                <?php if (!empty($myAttendance['check_in_photo'])): ?>
                  <img src="<?= htmlspecialchars(assetUrl($myAttendance['check_in_photo'])) ?>" class="w-full h-full object-cover" alt="Absen Masuk" />
                <?php else: ?>
                  <i class="fas fa-sign-in-alt text-slate-400 text-lg"></i>
                <?php endif; ?>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                  <span class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Masuk</span>
                  <?= checkinStatusPill($myAttendance) ?>
                </div>
                <div class="text-xl font-bold text-slate-800 dark:text-white"><?= htmlspecialchars(timeOnly($myAttendance['check_in_time'])) ?></div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate"><?= htmlspecialchars($myAttendance['today_plan'] ?? '-') ?></div>
              </div>
            </div>
          </div>

          <!-- Activities -->
          <div class="p-4 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-700">
            <div class="flex items-center gap-2 mb-2">
              <i class="fas fa-tasks text-slate-400 text-sm"></i>
              <span class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Aktivitas</span>
            </div>
            <p class="text-sm text-slate-700 dark:text-slate-300 mb-2"><?= htmlspecialchars($myAttendance['notes'] ?? '-') ?></p>
            <?php if (!empty($myActivities)): ?>
            <div class="flex gap-2 overflow-x-auto pb-1">
              <?php foreach ($myActivities as $act): ?>
                <img src="<?= htmlspecialchars(assetUrl($act['photo_path'])) ?>" class="w-14 h-14 object-cover rounded-lg flex-shrink-0 border border-slate-200 dark:border-slate-600" alt="Activity" />
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- Check Out -->
          <div class="p-4 bg-white dark:bg-slate-800">
            <div class="flex items-center gap-4">
              <div class="w-16 h-16 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center overflow-hidden flex-shrink-0">
                <?php if (!empty($myAttendance['check_out_photo'])): ?>
                  <img src="<?= htmlspecialchars(assetUrl($myAttendance['check_out_photo'])) ?>" class="w-full h-full object-cover" alt="Absen Pulang" />
                <?php else: ?>
                  <i class="fas fa-sign-out-alt text-slate-400 text-lg"></i>
                <?php endif; ?>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                  <span class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Pulang</span>
                  <?= checkoutStatusPill($myAttendance, $WORK_END_TIME, $OVERTIME_THRESHOLD, $earlyLeaveToleranceMinutes) ?>
                </div>
                <div class="text-xl font-bold text-slate-800 dark:text-white"><?= htmlspecialchars(timeOnly($myAttendance['check_out_time'])) ?></div>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      
      <?php if (!$canManageAttendanceOverview): ?>
        <div class="mt-6 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-slate-800 dark:to-slate-700">
            <h3 class="text-base font-bold text-gray-800 dark:text-white flex items-center gap-2">
              <i class="fas fa-users text-blue-600 dark:text-blue-400"></i>
              Absensi Hari Ini — <?= date('d M Y') ?>
            </h3>
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Daftar karyawan yang sudah absen hari ini</p>
          </div>
          
          <?php
            
            $techAttStmt = $pdo->prepare("
              SELECT a.*, u.full_name, u.username, u.role 
              FROM attendances a 
              JOIN users u ON u.id = a.user_id 
              WHERE u.role NOT IN ('administrator','direktur','customer')
                AND a.attendance_date = CURDATE()
                AND a.check_in_time IS NOT NULL 
                AND a.check_in_time != '0000-00-00 00:00:00'
              ORDER BY a.check_in_time ASC
            ");
            $techAttStmt->execute();
            $techTodayAttendances = $techAttStmt->fetchAll(PDO::FETCH_ASSOC);
            
            
            $techTotalPresent = count($techTodayAttendances);
            $techOnTime = 0;
            $techLate = 0;
            foreach ($techTodayAttendances as $ta) {
              if (in_array($ta['status'] ?? '', ['Hadir', 'Lembur'])) $techOnTime++;
              if (($ta['status'] ?? '') === 'Terlambat') $techLate++;
            }
          ?>
          
          
          <div class="px-5 py-3 bg-gray-50 dark:bg-slate-800/50 border-b border-gray-100 dark:border-slate-700">
            <div class="flex items-center gap-4 text-sm">
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                <span class="text-gray-600 dark:text-gray-400">Hadir: <strong class="text-gray-900 dark:text-white"><?= $techTotalPresent ?></strong></span>
              </div>
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                <span class="text-gray-600 dark:text-gray-400">Tepat Waktu: <strong class="text-gray-900 dark:text-white"><?= $techOnTime ?></strong></span>
              </div>
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                <span class="text-gray-600 dark:text-gray-400">Terlambat: <strong class="text-gray-900 dark:text-white"><?= $techLate ?></strong></span>
              </div>
            </div>
          </div>
          
          
          <div class="p-4 max-h-96 overflow-y-auto">
            <?php if (empty($techTodayAttendances)): ?>
              <div class="text-center py-8 text-gray-400 dark:text-gray-500">
                <i class="fas fa-inbox text-3xl mb-2"></i>
                <p class="text-sm">Belum ada yang absen hari ini</p>
              </div>
            <?php else: ?>
              <div class="space-y-2">
                <?php foreach ($techTodayAttendances as $ta): 
                  $taStatus = $ta['status'] ?? 'Alpha';
                  $taCheckIn = ($ta['check_in_time'] && $ta['check_in_time'] !== '0000-00-00 00:00:00') ? date('H:i', strtotime($ta['check_in_time'])) : '-';
                  $taCheckOut = ($ta['check_out_time'] && $ta['check_out_time'] !== '0000-00-00 00:00:00') ? date('H:i', strtotime($ta['check_out_time'])) : '-';
                  $taName = htmlspecialchars($ta['full_name'] ?: $ta['username']);
                  
                  $statusColors = [
                    'Hadir' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                    'Terlambat' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                    'Lembur' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
                    'Not Checked Out' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                  ];
                  $statusColor = $statusColors[$taStatus] ?? 'bg-gray-100 text-gray-700';
                  
                  $statusIcons = [
                    'Hadir' => 'fa-check-circle text-green-500',
                    'Terlambat' => 'fa-clock text-amber-500',
                    'Lembur' => 'fa-dumbbell text-cyan-500',
                    'Not Checked Out' => 'fa-exclamation-circle text-red-500'
                  ];
                  $statusIcon = $statusIcons[$taStatus] ?? 'fa-question-circle text-gray-500';
                ?>
                  <div class="flex items-center gap-3 p-3 bg-white dark:bg-slate-700/50 border border-gray-100 dark:border-slate-600 rounded-xl hover:shadow-sm transition">
                    <div class="text-lg w-8 text-center">
                      <i class="fas <?= $statusIcon ?>"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                      <div class="font-medium text-sm text-gray-900 dark:text-white truncate"><?= $taName ?></div>
                      <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        <i class="fas fa-sign-in-alt mr-1"></i><?= $taCheckIn ?>
                        <?php if ($taCheckOut !== '-'): ?>
                          <span class="mx-1">•</span>
                          <i class="fas fa-sign-out-alt mr-1"></i><?= $taCheckOut ?>
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="flex-shrink-0">
                      <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold <?= $statusColor ?>"><?= htmlspecialchars($taStatus) ?></span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    
    <?php elseif ($activeTab === 'attendance_request'): ?>
      <?php if ($hasMissedCheckoutPrevDay): ?>
      <h2 class="text-lg font-semibold mb-3 text-red-900 dark:text-red-300">Request Absen Pulang Terlewat</h2>
      <div class="mb-4 p-4 rounded-2xl border border-red-200 dark:border-red-800 bg-gradient-to-br from-red-50 to-orange-50 dark:from-red-900/20 dark:to-orange-900/20 text-sm text-red-800 dark:text-red-300 shadow-sm">
        <div class="flex items-start gap-3">
          <div class="w-9 h-9 rounded-xl bg-red-500 text-white flex items-center justify-center flex-shrink-0">
            <i class="fas fa-exclamation-triangle"></i>
          </div>
          <div>
            <div class="font-semibold text-red-900 dark:text-red-200 mb-1">Absen Pulang Terlewat</div>
            <p>Anda belum absen pulang pada tanggal <strong><?= date('d M Y', strtotime($missedCheckoutDate)) ?></strong>. Isi form di bawah untuk mengajukan request absen pulang. Setelah admin approve, Anda bisa absen masuk hari ini.</p>
          </div>
        </div>
      </div>
      <?php if ($hasPendingAttendanceRequest): ?>
        <div class="p-4 rounded-2xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 text-sm text-amber-800 dark:text-amber-300">
          <i class="fas fa-hourglass-half mr-2"></i>Request absen pulang Anda sedang menunggu approval admin. Silakan tunggu hingga admin memproses request Anda.
        </div>
      <?php else: ?>
      <form method="post" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
        <input type="hidden" name="tab" value="attendance_request">
        <input type="hidden" name="request_type" value="missed_checkout">
        <input type="hidden" name="request_missed_date" value="<?= htmlspecialchars($missedCheckoutDate) ?>">
        <div>
          <label class="block text-sm text-indigo-700 dark:text-indigo-400 mb-1">Tanggal Absen Pulang Terlewat</label>
          <input type="text" readonly value="<?= date('d M Y', strtotime($missedCheckoutDate)) ?>" class="w-full border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-3 py-2 text-sm bg-slate-100 dark:bg-slate-800 cursor-not-allowed" />
        </div>
        <div>
          <label class="block text-sm text-indigo-700 dark:text-indigo-400 mb-1">
            <i class="fas fa-clock mr-1"></i>Jam Pulang <span class="text-red-500">*</span>
          </label>
          <input type="time" name="request_check_out_time" required
            class="max-w-[160px] border border-indigo-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
            placeholder="17:30" />
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Masukkan jam pulang yang seharusnya tercatat pada tanggal tersebut.</p>
        </div>
        <div>
          <label class="block text-sm text-indigo-700 dark:text-indigo-400 mb-1">Alasan <span class="text-red-500">*</span></label>
          <textarea name="request_reason" required rows="3" class="w-full border border-indigo-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder:text-slate-400 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Jelaskan alasan kenapa lupa absen pulang"></textarea>
        </div>
        <div class="flex justify-end">
          <button type="submit" class="px-4 py-2 rounded-full bg-red-600 dark:bg-red-700 text-white text-sm font-semibold hover:bg-red-700 dark:hover:bg-red-600">
            <i class="fas fa-paper-plane mr-1"></i> Kirim Request Absen Pulang
          </button>
        </div>
      </form>
      <?php endif; ?>

      <?php else: ?>
      <div class="mb-4">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-9 h-9 rounded-lg bg-indigo-600 dark:bg-indigo-700 text-white flex items-center justify-center">
            <i class="fas fa-paper-plane text-sm"></i>
          </div>
          <h2 class="text-lg font-bold text-slate-800 dark:text-white">Request Absensi</h2>
        </div>
        <p class="text-sm text-slate-500 dark:text-slate-400">Form ini untuk karyawan yang lupa absen masuk. Setelah admin approve, data akan otomatis masuk ke sistem.</p>
      </div>

      <?php if ($hasPendingAttendanceRequest): ?>
        <div class="p-4 rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 text-sm text-amber-800 dark:text-amber-300 flex items-center gap-3">
          <i class="fas fa-hourglass-half text-amber-500"></i>
          <span>Request absensi Anda sedang menunggu approval admin.</span>
        </div>
      <?php else: ?>
      <form method="post" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
        <input type="hidden" name="tab" value="attendance_request">
        <input type="hidden" name="request_type" value="checkin">
        <input type="hidden" name="request_latitude" id="request_latitude" value="">
        <input type="hidden" name="request_longitude" id="request_longitude" value="">
        <input type="hidden" name="request_accuracy" id="request_accuracy" value="">
        <input type="hidden" name="request_location_name" id="request_location_name" value="">
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5"><i class="fas fa-map-marker-alt mr-1.5 text-slate-400"></i>Lokasi GPS</label>
          <button type="button" onclick="captureAttendanceRequestLocation()" class="w-full px-4 py-3 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm font-semibold border border-slate-200 dark:border-slate-600 hover:bg-slate-200 dark:hover:bg-slate-600 transition flex items-center justify-center gap-2">
            <i class="fas fa-crosshairs"></i> Ambil Lokasi GPS
          </button>
          <div id="request_location_status" class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">Lokasi belum diambil.</div>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5"><i class="fas fa-camera mr-1.5 text-slate-400"></i>Foto Sekarang</label>
          <button type="button" onclick="openCamera('request')" class="w-full px-4 py-3 rounded-lg bg-indigo-600 dark:bg-indigo-700 text-white text-sm font-semibold hover:bg-indigo-700 dark:hover:bg-indigo-600 transition flex items-center justify-center gap-2">
            <i class="fas fa-camera"></i> Ambil Foto
          </button>
          <input type="file" name="request_photo" id="request_photo" accept="image/*" required onchange="renderSinglePreview(this, 'preview_request')" style="display:none;" />
          <div id="preview_request" class="mt-2"></div>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5"><i class="fas fa-clock mr-1.5 text-slate-400"></i>Jam Masuk <span class="text-red-500">*</span></label>
          <input type="time" name="request_check_in_time" id="request_check_in_time" required
            class="w-full max-w-[200px] border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition" />
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Jam masuk yang seharusnya tercatat.</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5"><i class="fas fa-clipboard-list mr-1.5 text-slate-400"></i>Plan Hari Ini <span class="text-red-500">*</span></label>
          <textarea name="request_today_plan" required rows="2" class="w-full border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder:text-slate-400 rounded-lg px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition" placeholder="Rencana pekerjaan hari ini"></textarea>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5"><i class="fas fa-comment-alt mr-1.5 text-slate-400"></i>Alasan <span class="text-red-500">*</span></label>
          <textarea name="request_reason" required rows="2" class="w-full border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder:text-slate-400 rounded-lg px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition" placeholder="Alasan mengajukan request absensi"></textarea>
        </div>
        <div>
          <button type="submit" class="w-full px-4 py-3 rounded-lg bg-green-600 dark:bg-green-700 text-white font-semibold hover:bg-green-700 dark:hover:bg-green-600 transition flex items-center justify-center gap-2">
            <i class="fas fa-paper-plane"></i> Kirim Request Absensi
          </button>
        </div>
      </form>
      <?php endif; ?>
      <?php endif; ?>

    <?php elseif ($activeTab === 'permission'): ?>
      
      <h2 class="text-lg font-semibold mb-3 text-indigo-900 dark:text-indigo-300">Permission Form</h2>
      <form method="post" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
        <input type="hidden" name="tab" value="permission">
        <div>
          <label class="block text-sm text-indigo-700 dark:text-indigo-400 mb-1">Alasan Izin</label>
          <textarea name="permission_reason" required class="w-full border border-indigo-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder:text-slate-400 rounded-lg px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Jelaskan alasan izin Anda"></textarea>
        </div>
        <div>
          <label class="block text-sm text-indigo-700 dark:text-indigo-400 mb-2">Jenis Pengajuan Izin</label>
          <div class="flex gap-4 mb-3">
            <label class="flex items-center">
              <input type="radio" name="permission_type" value="single" checked class="mr-2 text-indigo-600 focus:ring-indigo-500">
              <span class="text-sm text-slate-700 dark:text-slate-300">1 Hari (Hari Ini)</span>
            </label>
            <label class="flex items-center">
              <input type="radio" name="permission_type" value="range" class="mr-2 text-indigo-600 focus:ring-indigo-500">
              <span class="text-sm text-slate-700 dark:text-slate-300">Rentang Tanggal</span>
            </label>
          </div>
        </div>
        <div id="permission_single_date_container">
          <label class="block text-sm text-indigo-700 dark:text-indigo-400 mb-1">Tanggal Izin</label>
          <input type="date" name="permission_single_date" id="permission_single_date" class="w-full border border-indigo-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500" />
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Pilih tanggal izin yang diinginkan.</p>
        </div>
        <div id="permission_range_container" style="display: none;">
          <label class="block text-sm text-indigo-700 dark:text-indigo-400 mb-1">Rentang Tanggal</label>
          <input type="text" id="permission_date_range" placeholder="Pilih rentang tanggal" class="w-full border border-indigo-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder:text-slate-400 rounded-lg px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500" readonly />
          <input type="hidden" name="permission_start_date" id="permission_start_date" />
          <input type="hidden" name="permission_end_date" id="permission_end_date" />
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Pilih dua tanggal (mulai dan selesai).</p>
        </div>
        <div>
          <label class="block text-sm text-indigo-700 dark:text-indigo-400 mb-1">Bukti Izin</label>
          <input type="file" name="permission_proof" id="permission_proof" accept="image/*" required class="block w-full text-sm text-indigo-700 dark:text-white dark:bg-slate-700 dark:border-slate-600 border border-indigo-300 rounded-lg px-3 py-2 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 dark:file:bg-indigo-900/30 file:text-indigo-700 dark:file:text-indigo-400 hover:file:bg-indigo-100 dark:hover:file:bg-indigo-900/40" />
          <div id="preview_permission" class="mt-2"></div>
        </div>
        <div class="flex justify-end">
          <button type="submit" class="px-4 py-2 rounded-full bg-indigo-600 dark:bg-indigo-700 text-white font-semibold hover:bg-indigo-700 dark:hover:bg-indigo-600">Submit Permohonan Izin</button>
        </div>
      </form>
    
    <?php elseif ($activeTab === 'sick_leave'): ?>
      
      <h2 class="text-lg font-semibold mb-3 text-indigo-900 dark:text-indigo-300">Request Sick Leave</h2>
      
      <?php if (!empty($recentAlphaDays)): ?>
      <div class="mb-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
        <h3 class="text-sm font-medium text-amber-800 dark:text-amber-400 mb-2"><i class="fas fa-lightbulb mr-1"></i> Hari-hari Alpha yang Bisa Diajukan Sakit:</h3>
        <div class="text-sm text-amber-700 dark:text-amber-300">
          <?php foreach ($recentAlphaDays as $alphaDay): ?>
            <span class="inline-block bg-amber-100 dark:bg-amber-900/30 px-2 py-1 rounded mr-2 mb-1"><?= date('d M Y', strtotime($alphaDay)) ?></span>
          <?php endforeach; ?>
        </div>
        <p class="text-xs text-amber-600 dark:text-amber-400 mt-2">
          Anda bisa mengajukan izin sakit untuk tanggal-tanggal di atas (maksimal <?= $GRACE_PERIOD_DAYS ?> hari yang lalu).
        </p>
      </div>
      <?php endif; ?>
      
      <form method="post" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
        <input type="hidden" name="tab" value="sick_leave">
        <div>
          <label class="block text-sm text-indigo-700 dark:text-indigo-400 mb-1">Jenis Sakit</label>
          <input type="text" name="sick_illness" required class="w-full border border-indigo-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder:text-slate-400 rounded-lg px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Jelaskan jenis sakit yang diderita" />
        </div>
        <div>
          <label class="block text-sm text-indigo-700 dark:text-indigo-400 mb-2">Jenis Pengajuan Sakit</label>
          <div class="flex gap-4 mb-3">
            <label class="flex items-center">
              <input type="radio" name="sick_type" value="single" checked class="mr-2 text-indigo-600 focus:ring-indigo-500">
              <span class="text-sm text-slate-700 dark:text-slate-300">1 Hari</span>
            </label>
            <label class="flex items-center">
              <input type="radio" name="sick_type" value="range" class="mr-2 text-indigo-600 focus:ring-indigo-500">
              <span class="text-sm text-slate-700 dark:text-slate-300">Rentang Tanggal</span>
            </label>
          </div>
        </div>
        <div id="sick_single_date_container">
          <label class="block text-sm text-indigo-700 dark:text-indigo-400 mb-1">Tanggal Sakit</label>
          <input type="date" name="sick_single_date" id="sick_single_date" class="w-full border border-indigo-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500" />
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Pilih tanggal sakit. Anda bisa pilih tanggal masa lalu (maksimal <?= $GRACE_PERIOD_DAYS ?> hari) untuk izin sakit retrospektif.
          </p>
        </div>
        <div id="sick_range_container" style="display: none;">
          <label class="block text-sm text-indigo-700 dark:text-indigo-400 mb-1">Rentang Tanggal Sakit</label>
          <input type="text" id="sick_date_range" placeholder="Pilih rentang tanggal sakit" class="w-full border border-indigo-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder:text-slate-400 rounded-lg px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500" readonly />
          <input type="hidden" name="sick_start_date" id="sick_start_date" />
          <input type="hidden" name="sick_end_date" id="sick_end_date" />
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Anda bisa pilih tanggal masa lalu (maksimal <?= $GRACE_PERIOD_DAYS ?> hari) untuk izin sakit retrospektif.
            Hanya hari-hari dengan status Alpha yang bisa diubah menjadi Sakit.
          </p>
        </div>
        <div>
          <label class="block text-sm text-indigo-700 dark:text-indigo-400 mb-1">Bukti Sakit</label>
          <input type="file" name="sick_proof" id="sick_proof" accept="image/*" required class="block w-full text-sm text-indigo-700 dark:text-white dark:bg-slate-700 dark:border-slate-600 border border-indigo-300 rounded-lg px-3 py-2 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 dark:file:bg-indigo-900/30 file:text-indigo-700 dark:file:text-indigo-400 hover:file:bg-indigo-100 dark:hover:file:bg-indigo-900/40" />
          <div id="preview_sick" class="mt-2"></div>
        </div>
        <div class="flex justify-end">
          <button type="submit" class="px-4 py-2 rounded-full bg-indigo-600 dark:bg-indigo-700 text-white font-semibold hover:bg-indigo-700 dark:hover:bg-indigo-600">Submit Permohonan Sakit</button>
        </div>
      </form>
    <?php endif; ?>
    
    <?php endif; ?>
  </div>

  <?php if ($errors): ?>
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($errors as $err): ?>
    showToast(<?= json_encode($err, JSON_UNESCAPED_UNICODE) ?>, 'error');
    <?php endforeach; ?>
  });
  </script>
  <?php endif; ?>
  <?php if ($messages): ?>
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($messages as $msg): ?>
    showToast(<?= json_encode($msg, JSON_UNESCAPED_UNICODE) ?>, 'success');
    <?php endforeach; ?>
  });
  </script>
  <?php endif; ?>

  
  <div id="cameraModal" class="hidden fixed inset-0 bg-black z-50" style="flex-direction: column;">
    
    <div class="absolute top-0 left-0 right-0 z-10 bg-gradient-to-b from-black/60 to-transparent p-4">
      <div class="flex justify-between items-center text-white">
        <h3 class="text-lg font-semibold"><i class="fas fa-camera mr-2"></i>Ambil Foto</h3>
        <button onclick="closeCamera()" class="text-white hover:text-red-400 transition-colors">
          <i class="fas fa-times text-2xl"></i>
        </button>
      </div>
    </div>
    
    
    <div id="locationInfo" class="absolute top-16 left-4 right-4 z-10 bg-black/50 text-white px-3 py-2 rounded-lg text-sm backdrop-blur-sm">
      <div class="flex items-center">
        <i class="fas fa-map-marker-alt mr-2 text-green-400"></i>
        <span>Mendapatkan lokasi GPS...</span>
      </div>
    </div>
    
    
    <div class="flex-1 relative overflow-hidden">
      <video id="video" class="absolute inset-0 w-full h-full object-cover" autoplay playsinline muted></video>
      
      
      <div class="absolute inset-0 pointer-events-none flex items-center justify-center">
        
        <div class="absolute inset-0 opacity-30">
          
          <div class="absolute w-full border-t border-white" style="top: 33.33%"></div>
          <div class="absolute w-full border-t border-white" style="top: 66.66%"></div>
          
          <div class="absolute h-full border-l border-white" style="left: 33.33%"></div>
          <div class="absolute h-full border-l border-white" style="left: 66.66%"></div>
        </div>
        
        
        <div class="w-64 h-64 border-2 border-white border-opacity-70 rounded-lg bg-white/5 backdrop-blur-sm flex items-center justify-center">
          <div class="text-white text-center">
            <i class="fas fa-camera text-3xl mb-2 opacity-70"></i>
            <p class="text-sm opacity-70">Focus Area</p>
          </div>
        </div>
      </div>
    </div>
    
    
    <div class="absolute bottom-0 left-0 right-0 z-10 bg-gradient-to-t from-black/80 to-transparent p-6">
      <div class="flex items-center justify-center space-x-8">
        
        <button onclick="switchCamera()" class="bg-white/20 backdrop-blur-sm hover:bg-white/30 text-white p-4 rounded-full transition-all duration-200 transform hover:scale-105">
          <i class="fas fa-sync-alt text-xl"></i>
        </button>
        
        
        <div class="relative">
          <button 
            onclick="console.log('Capture button onclick triggered'); capturePhoto();" 
            onmousedown="console.log('Capture button mousedown');"
            id="captureBtn" 
            class="bg-white hover:bg-gray-100 w-24 h-24 rounded-full flex items-center justify-center transition-all duration-200 transform hover:scale-105 shadow-xl border-4 border-white/50"
            style="pointer-events: auto !important; cursor: pointer !important; z-index: 1000001 !important;">
            <div class="w-20 h-20 bg-white border-4 border-gray-300 rounded-full flex items-center justify-center">
              <i class="fas fa-camera text-3xl"></i>
            </div>
          </button>
          
          <div class="absolute inset-0 bg-white rounded-full animate-ping opacity-20 pointer-events-none"></div>
        </div>
        
        
        <button onclick="closeCamera()" class="bg-red-500/80 backdrop-blur-sm hover:bg-red-600 text-white p-4 rounded-full transition-all duration-200 transform hover:scale-105">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>
      
      
      <div class="text-center mt-4">
        <p class="text-white text-lg font-medium">Tekan tombol putih besar untuk mengambil foto</p>
        <p class="text-white/70 text-sm mt-1">Pastikan wajah terlihat jelas dalam frame</p>
      </div>
    </div>

    </div>
    
    
    <style>
      button[onclick*="openCamera('check_in')"],
      button[onclick*="openCamera('check_out')"] {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 0.5rem !important;
        font-size: 0 !important; /* Completely hide original text */
        color: transparent !important; /* Make text invisible */
        overflow: hidden !important;
      }
      
      button[onclick*="openCamera('check_in')"]::before,
      button[onclick*="openCamera('check_out')"]::before {
        content: "Ambil Foto Sekarang" !important;
        font-size: 1rem !important;
        font-weight: 600 !important;
        line-height: 1.5 !important;
        white-space: nowrap !important;
        color: white !important; /* Ensure text is white */
        display: inline-block !important;
      }
      
      @media (max-width: 414px) {
        button[onclick*="openCamera('check_in')"],
        button[onclick*="openCamera('check_out')"] {
          padding: 0.875rem 1.5rem !important;
        }
        
        button[onclick*="openCamera('check_in')"]::before,
        button[onclick*="openCamera('check_out')"]::before {
          font-size: 1rem !important;
        }
      }
      
      @media (max-width: 375px) {
        button[onclick*="openCamera('check_in')"],
        button[onclick*="openCamera('check_out')"] {
          padding: 0.75rem 1.25rem !important;
        }
        
        button[onclick*="openCamera('check_in')"]::before,
        button[onclick*="openCamera('check_out')"]::before {
          font-size: 0.9375rem !important;
        }
      }
      
      #cameraModal {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        z-index: 999999 !important;
        background: black !important;
      }
      
      #cameraModal.active {
        display: flex !important;
        flex-direction: column !important;
      }
      
      #video {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        transform: none !important;
        -webkit-transform: none !important;
      }
      
      #video.front-camera {
        transform: scaleX(-1) !important;
        -webkit-transform: scaleX(-1) !important;
      }
      
      #video.back-camera {
        transform: none !important;
        -webkit-transform: none !important;
      }
      
      #cameraModal .camera-controls {
        z-index: 1000000 !important;
        pointer-events: auto !important;
      }
      
      #cameraModal .camera-controls button {
        pointer-events: auto !important;
        cursor: pointer !important;
        touch-action: manipulation !important;
        -webkit-tap-highlight-color: transparent !important;
      }
      
      #captureBtn {
        pointer-events: auto !important;
        cursor: pointer !important;
        touch-action: manipulation !important;
        z-index: 1000001 !important;
        position: relative !important;
        -webkit-tap-highlight-color: transparent !important;
      }
      
      body.camera-active .sidebar,
      body.camera-active nav,
      body.camera-active header,
      body.camera-active .md\\:ml-64 > *:not(#cameraModal) {
        display: none !important;
      }
      
      body.camera-active {
        overflow: hidden !important;
        position: fixed !important;
        width: 100% !important;
        height: 100% !important;
      }
    </style>
  </div>

  <script>
  let currentType = '';
  let stream = null;
  let currentLocation = null;

  async function validateLocationBeforeCapture(location) {
    if (!location || !location.latitude || !location.longitude) {
      return {
        is_valid: false,
        distance_meters: 0,
        allowed_radius: 0,
        zone_name: 'Lokasi tidak tersedia',
        error: 'GPS location not available'
      };
    }
    
    try {
      console.log('[Geofence] Fetching geofence zones...');
      const url = '/../app/action/tracking-get-geofences-standalone.php';
      console.log('[Geofence] URL:', url);
      
      const response = await fetch(url);
      console.log('[Geofence] Response status:', response.status, response.statusText);
      console.log('[Geofence] Response ok:', response.ok);
      
      if (!response.ok) {
        const errorText = await response.text();
        console.error('[Geofence] Server error response:', errorText);
        throw new Error('Failed to fetch geofences: ' + response.status);
      }
      
      const data = await response.json();
      console.log('[Geofence] Data received:', data);
      
      if (!data.success || !data.zones || data.zones.length === 0) {
        console.warn('[Geofence] No active zones found — allowing access');
        return {
          is_valid: true,
          distance_meters: 0,
          allowed_radius: 0,
          zone_name: 'Tidak ada zona aktif (diizinkan)',
          error: null
        };
      }
      
      console.log('[Geofence] Found', data.zones.length, 'active zones');
      
      let closestZone = null;
      let closestDistance = Infinity;
      let isInsideAnyZone = false;
      
      for (const zone of data.zones) {
        if (!zone.is_active) continue;
        
        const distance = calculateDistance(
          location.latitude,
          location.longitude,
          zone.latitude,
          zone.longitude
        );
        
        console.log('[Geofence] Distance to', zone.name + ':', distance.toFixed(2), 'meters (radius:', zone.radius_meters + 'm)');
        
        if (distance < closestDistance) {
          closestDistance = distance;
          closestZone = zone;
        }
        
        const effectiveRadius = zone.radius_meters + (location.accuracy > 20 ? Math.min(location.accuracy * 0.5, 30) : 0);
        if (distance <= effectiveRadius) {
          isInsideAnyZone = true;
          break;
        }
      }
      
      console.log('[Geofence] Validation result:');
      console.log('[Geofence] - Is valid:', isInsideAnyZone);
      console.log('[Geofence] - Closest zone:', closestZone ? closestZone.name : 'none');
      console.log('[Geofence] - Distance:', closestDistance.toFixed(2), 'meters');
      
      return {
        is_valid: isInsideAnyZone,
        distance_meters: closestDistance,
        allowed_radius: closestZone ? closestZone.radius_meters : 0,
        zone_name: closestZone ? closestZone.name : 'Tidak ada lokasi terdekat',
        matched_zone: closestZone
      };
      
    } catch (error) {
      console.error('[Geofence] ========================================');
      console.error('[Geofence] VALIDATION ERROR');
      console.error('[Geofence] Error:', error);
      console.error('[Geofence] Error message:', error.message);
      console.error('[Geofence] Error stack:', error.stack);
      console.error('[Geofence] ========================================');
      
      return {
        is_valid: true,
        distance_meters: 0,
        allowed_radius: 0,
        zone_name: 'Validasi gagal (diizinkan)',
        error: error.message
      };
    }
  }
  

  function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371000; 
    const φ1 = lat1 * Math.PI / 180;
    const φ2 = lat2 * Math.PI / 180;
    const Δφ = (lat2 - lat1) * Math.PI / 180;
    const Δλ = (lon2 - lon1) * Math.PI / 180;
    
    const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
              Math.cos(φ1) * Math.cos(φ2) *
              Math.sin(Δλ/2) * Math.sin(Δλ/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    
    return R * c; 
  }

  function reverseGeocode(lat, lng) {
    const field = document.getElementById('check_in_location_name');
    if (!field) return;
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`, {
      headers: { 'Accept-Language': 'id' }
    })
    .then(r => r.json())
    .then(data => {
      if (data && data.display_name) {
        field.value = data.display_name.substring(0, 250);
      }
    })
    .catch(() => { field.value = `${lat}, ${lng}`; });
  }

  async function getCurrentLocation() {
    return new Promise((resolve, reject) => {
      if (!navigator.geolocation) {
        reject(new Error('Geolocation tidak didukung browser ini'));
        return;
      }

      const options = {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 60000
      };

      navigator.geolocation.getCurrentPosition(
        (position) => {
          const location = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy,
            timestamp: new Date().toISOString()
          };
          resolve(location);
        },
        (error) => {
          let message = 'Error mendapatkan lokasi: ';
          switch (error.code) {
            case error.PERMISSION_DENIED:
              message += 'Akses lokasi ditolak. Mohon izinkan akses lokasi di browser.';
              break;
            case error.POSITION_UNAVAILABLE:
              message += 'Informasi lokasi tidak tersedia.';
              break;
            case error.TIMEOUT:
              message += 'Timeout mendapatkan lokasi.';
              break;
            default:
              message += error.message;
              break;
          }
          reject(new Error(message));
        },
        options
      );
    });
  }

  async function captureAttendanceRequestLocation() {
    const statusEl = document.getElementById('request_location_status');
    if (statusEl) statusEl.textContent = 'Mengambil lokasi GPS...';
    try {
      const loc = await getCurrentLocation();
      const latField = document.getElementById('request_latitude');
      const lngField = document.getElementById('request_longitude');
      const accField = document.getElementById('request_accuracy');
      const nameField = document.getElementById('request_location_name');
      if (latField) latField.value = loc.latitude;
      if (lngField) lngField.value = loc.longitude;
      if (accField) accField.value = loc.accuracy;
      if (nameField) nameField.value = `${loc.latitude}, ${loc.longitude}`;
      if (statusEl) statusEl.textContent = `GPS tersimpan: ${loc.latitude.toFixed(6)}, ${loc.longitude.toFixed(6)} (akurasi ${Math.round(loc.accuracy)}m)`;
    } catch (error) {
      if (statusEl) statusEl.textContent = error.message;
      showToast(error.message, 'error');
    }
  }

  async function openCamera(type) {
    console.log('=== OPEN CAMERA START ===');
    console.log('Opening camera for type:', type);
    currentType = type;
    
    const modal = document.getElementById('cameraModal');
    const video = document.getElementById('video');
    
    console.log('Modal element:', modal);
    console.log('Video element:', video);
    
    if (!modal) {
      console.error('Camera modal not found!');
      showToast('Error: Camera modal tidak ditemukan', 'error');
      return;
    }
    
    console.log('Showing camera modal...');
    modal.classList.remove('hidden');
    modal.classList.add('active');
    modal.style.display = 'flex';
    modal.style.flexDirection = 'column';
    
    document.body.classList.add('camera-active');
    console.log('Body classes:', document.body.className);
    
    document.body.style.overflow = 'hidden';
    
    if (!video) {
      console.error('Video element not found!');
      showToast('Error: Video element tidak ditemukan', 'error');
      closeCamera();
      return;
    }

    try {
      console.log('Requesting camera access...');
      const constraints = {
        video: {
          width: { ideal: 1280 },
          height: { ideal: 720 },
          facingMode: 'environment',
          aspectRatio: { ideal: 16/9 }
        }
      };
      
      console.log('Camera constraints:', constraints);
      stream = await navigator.mediaDevices.getUserMedia(constraints);
      video.srcObject = stream;
      console.log('Camera access successful, stream:', stream);
      
      video.classList.remove('front-camera', 'back-camera');
      video.classList.add('back-camera'); 
      console.log('Added back-camera class to prevent invert');
      console.log('Video CSS classes:', video.className);
      
      const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
      console.log('Is mobile device:', isMobile);
      console.log('Camera facing mode:', 'environment (back camera)');
      
      await new Promise((resolve) => {
        video.onloadedmetadata = () => {
          console.log('Video metadata loaded, dimensions:', video.videoWidth, 'x', video.videoHeight);
          resolve();
        };
      });
      
      try {
        console.log('Getting location...');
        showLocationLoading(true);
        currentLocation = await getCurrentLocation();
        console.log('Location obtained:', currentLocation);
        showLocationLoading(false);
        
        const locationValid = await validateLocationBeforeCapture(currentLocation);
        if (!locationValid.is_valid) {
          const captureBtn = document.getElementById('captureBtn');
          if (captureBtn) {
            captureBtn.disabled = true;
            captureBtn.classList.add('opacity-50', 'cursor-not-allowed');
            captureBtn.title = 'Lokasi di luar radius yang diizinkan';
          }
          
          const locationInfo = document.getElementById('locationInfo');
          if (locationInfo) {
            locationInfo.innerHTML = `
              <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <div class="font-bold mb-2"><i class="fas fa-times-circle mr-1"></i> Lokasi Di Luar Radius!</div>
                <div class="text-sm">
                  <i class="fas fa-map-marker-alt mr-1"></i> Lokasi terdekat: <strong>${locationValid.zone_name}</strong><br>
                  <i class="fas fa-ruler-horizontal mr-1"></i> Jarak Anda: <strong>${Math.round(locationValid.distance_meters)}m</strong><br>
                  <i class="fas fa-check-circle mr-1"></i> Radius maksimal: <strong>${locationValid.allowed_radius}m</strong><br><br>
                  <strong>Anda tidak dapat absen dari lokasi ini.</strong><br>
                  Silakan pindah ke area yang diizinkan atau hubungi admin.
                </div>
              </div>
            `;
          }
          
          console.error('Location validation failed:', locationValid);
        } else {
          console.log('Location valid in zone:', locationValid.zone_name);
          updateLocationDisplay();
        }
        
      } catch (locationError) {
        console.warn('Location failed:', locationError.message);
        showLocationLoading(false);
        currentLocation = null;
        
        const locationInfo = document.getElementById('locationInfo');
        if (locationInfo) {
          locationInfo.innerHTML = `
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded mb-4">
              <div class="font-bold mb-1"><i class="fas fa-exclamation-triangle mr-1"></i> GPS Tidak Tersedia</div>
              <div class="text-sm mb-2">
                ${locationError.message}<br>
                Koordinat lokasi tidak akan tersimpan.
              </div>
              <button type="button" onclick="retryGPS()" class="px-3 py-1 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700">
                <i class="fas fa-redo-alt mr-1"></i> Coba Lagi GPS
              </button>
            </div>
          `;
        }
      }
      
      console.log('=== OPEN CAMERA COMPLETE ===');

    } catch (cameraError) {
      console.error('Camera error:', cameraError);
      closeCamera();
      showToast('Error mengakses kamera: ' + cameraError.message + '. Pastikan browser mengizinkan akses kamera.', 'error');
    }
  }

  function showLocationLoading(show) {
    const existing = document.getElementById('locationLoadingModal');
    if (existing) {
      existing.remove();
    }
    
    if (!show) return;
    
    const modal = document.createElement('div');
    modal.id = 'locationLoadingModal';
    modal.className = 'fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50';
    modal.innerHTML = `
      <div class="bg-white dark:bg-slate-800 rounded-lg p-6 text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
        <p class="text-gray-700 dark:text-slate-300">Mendapatkan lokasi GPS...</p>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-2">Pastikan GPS dan lokasi browser aktif</p>
      </div>
    `;
    document.body.appendChild(modal);
    
    setTimeout(() => {
      showLocationLoading(false);
    }, 10000);
  }

  function updateLocationDisplay() {
    const locationInfo = document.getElementById('locationInfo');
    if (locationInfo) {
      if (currentLocation) {
        locationInfo.innerHTML = `
          <div class="text-sm text-green-600 mb-2">
            <i class="fas fa-map-marker-alt mr-1"></i>
            Lokasi: ${currentLocation.latitude.toFixed(6)}, ${currentLocation.longitude.toFixed(6)}
            <br>
            <i class="fas fa-crosshairs mr-1"></i>
            Akurasi: ${Math.round(currentLocation.accuracy)}m
          </div>
        `;
      } else {
        locationInfo.innerHTML = `
          <div class="text-sm text-orange-600 mb-2">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            Lokasi tidak tersedia (foto tetap bisa diambil)
          </div>
        `;
      }
    }
  }

  async function retryGPS() {
    try {
      showLocationLoading(true);
      currentLocation = await getCurrentLocation();
      showLocationLoading(false);
      console.log('GPS retry succeeded:', currentLocation);
      
      const locationValid = await validateLocationBeforeCapture(currentLocation);
      if (!locationValid.is_valid) {
        const captureBtn = document.getElementById('captureBtn');
        if (captureBtn) {
          captureBtn.disabled = true;
          captureBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
        const locationInfo = document.getElementById('locationInfo');
        if (locationInfo) {
          locationInfo.innerHTML = `
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
              <div class="font-bold mb-2"><i class="fas fa-times-circle mr-1"></i> Lokasi Di Luar Radius!</div>
              <div class="text-sm">
                <i class="fas fa-map-marker-alt mr-1"></i> Lokasi terdekat: <strong>${locationValid.zone_name}</strong><br>
                <i class="fas fa-ruler-horizontal mr-1"></i> Jarak Anda: <strong>${Math.round(locationValid.distance_meters)}m</strong><br>
                <i class="fas fa-check-circle mr-1"></i> Radius maksimal: <strong>${locationValid.allowed_radius}m</strong>
              </div>
            </div>
          `;
        }
      } else {
        const captureBtn = document.getElementById('captureBtn');
        if (captureBtn) {
          captureBtn.disabled = false;
          captureBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
        updateLocationDisplay();
        showToast('GPS berhasil didapatkan!', 'success');
      }
    } catch (err) {
      showLocationLoading(false);
      currentLocation = null;
      showToast('GPS masih gagal: ' + err.message, 'error');
    }
  }

  function closeCamera() {
    const modal = document.getElementById('cameraModal');
    modal.classList.add('hidden');
    modal.classList.remove('active');
    modal.style.display = 'none';
    
    document.body.classList.remove('camera-active');
    
    document.body.style.overflow = '';
    
    if (stream) {
      stream.getTracks().forEach(track => track.stop());
      stream = null;
    }
  }

  
  let currentFacingMode = 'environment';
  async function switchCamera() {
    const video = document.getElementById('video');
    currentFacingMode = currentFacingMode === 'environment' ? 'user' : 'environment';
    
    console.log('Switching camera to:', currentFacingMode);
    
    
    if (stream) {
      stream.getTracks().forEach(track => track.stop());
    }
    
    try {
      
      const constraints = {
        video: {
          width: { ideal: 1280 },
          height: { ideal: 720 },
          facingMode: currentFacingMode,
          aspectRatio: { ideal: 16/9 }
        }
      };
      
      stream = await navigator.mediaDevices.getUserMedia(constraints);
      video.srcObject = stream;
      
      
      video.classList.remove('front-camera', 'back-camera');
      if (currentFacingMode === 'user') {
        video.classList.add('front-camera');
        console.log('Added front-camera class (will flip horizontally)');
        console.log('Front camera: Video will appear mirrored in preview but captured photo will be correct');
      } else {
        video.classList.add('back-camera');
        console.log('Added back-camera class (normal orientation)');
        console.log('Back camera: Video and photo will have normal orientation');
      }
      console.log('Updated video CSS classes:', video.className);
      
    } catch (err) {
      console.error('Error switching camera:', err);
      
      try {
        stream = await navigator.mediaDevices.getUserMedia({ video: true });
        video.srcObject = stream;
        video.classList.remove('front-camera', 'back-camera');
        video.classList.add('back-camera'); 
      } catch (fallbackErr) {
        console.error('Fallback camera error:', fallbackErr);
        video.classList.remove('front-camera', 'back-camera');
        video.classList.add('back-camera'); 
        showToast('Gagal mengganti kamera: ' + fallbackErr.message, 'error');
      }
    }
  }

  async function capturePhoto() {
    console.log('Capture photo clicked, currentType:', currentType);
    
    const video = document.getElementById('video');
    if (!video || !video.videoWidth || !video.videoHeight) {
      console.error('Video not ready:', video);
      showToast('Video belum siap. Tunggu sebentar dan coba lagi.', 'warning');
      return;
    }
    
    if (!currentLocation) {
      console.log('GPS null, trying one more time...');
      try {
        currentLocation = await getCurrentLocation();
        console.log('GPS retry in capturePhoto succeeded:', currentLocation);
      } catch (e) {
        console.warn('GPS retry in capturePhoto failed:', e.message);
      }
    }
    
    console.log('Video dimensions:', video.videoWidth, 'x', video.videoHeight);
    
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    const ctx = canvas.getContext('2d');
    
    
    const isFrontCamera = video.classList.contains('front-camera');
    console.log('Is front camera:', isFrontCamera);
    
    if (isFrontCamera) {
      
      ctx.translate(canvas.width, 0);
      ctx.scale(-1, 1);
      ctx.drawImage(video, 0, 0);
      ctx.setTransform(1, 0, 0, 1, 0, 0); 
      console.log('Photo captured with horizontal flip for front camera');
    } else {
      
      ctx.drawImage(video, 0, 0);
      console.log('Photo captured normally for back camera');
    }
    
    console.log('Photo captured, converting to blob...');
    
    canvas.toBlob(blob => {
      if (!blob) {
        console.error('Failed to create blob');
        showToast('Gagal mengambil foto. Coba lagi.', 'error');
        return;
      }
      
      console.log('Blob created, size:', blob.size);
      
      const file = new File([blob], `${currentType}_photo_${Date.now()}.jpg`, { type: 'image/jpeg' });
      const dt = new DataTransfer();
      dt.items.add(file);
      
      if (currentLocation) {
        console.log('Saving location data:', currentLocation);
        const latField = document.getElementById(`${currentType}_latitude`);
        const lngField = document.getElementById(`${currentType}_longitude`);
        const accField = document.getElementById(`${currentType}_accuracy`);
        
        if (latField) latField.value = currentLocation.latitude;
        if (lngField) lngField.value = currentLocation.longitude;
        if (accField) accField.value = currentLocation.accuracy;
        
      if (currentType === 'request') {
        const requestLocationName = document.getElementById('request_location_name');
        const requestStatus = document.getElementById('request_location_status');
        if (requestLocationName) requestLocationName.value = `${currentLocation.latitude}, ${currentLocation.longitude}`;
        if (requestStatus) requestStatus.textContent = `GPS tersimpan: ${currentLocation.latitude.toFixed(6)}, ${currentLocation.longitude.toFixed(6)} (akurasi ${Math.round(currentLocation.accuracy)}m)`;
      }
      
      if (currentType === 'check_in') {
          reverseGeocode(currentLocation.latitude, currentLocation.longitude);
        }
      } else {
        console.warn('No location data available');
      }
      
      if (currentType === 'check_in') {
        const input = document.getElementById('check_in_photo');
        if (input) {
          input.files = dt.files;
          renderSinglePreview(input, 'preview_checkin');
          console.log('Photo set for check_in');
        }
      } else if (currentType === 'check_out') {
        const input = document.getElementById('check_out_photo');
        if (input) {
          input.files = dt.files;
          renderSinglePreview(input, 'preview_checkout');
          const errorEl = document.getElementById('checkout_photo_error');
          if (errorEl) errorEl.style.display = 'none';
          console.log('Photo set for check_out');
        }
      } else if (currentType === 'request') {
        const input = document.getElementById('request_photo');
        if (input) {
          input.files = dt.files;
          renderSinglePreview(input, 'preview_request');
          console.log('Photo set for attendance request');
        }
      }
      
      console.log('Closing camera...');
      closeCamera();
      
    }, 'image/jpeg', 0.8);
  }

  function renderSinglePreview(input, containerId) {
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    if (input.files && input.files[0]) {
      const img = document.createElement('img');
      img.className = 'w-40 h-40 object-cover rounded-xl shadow-md';
      const reader = new FileReader();
      reader.onload = e => img.src = e.target.result;
      reader.readAsDataURL(input.files[0]);
      container.appendChild(img);
    }
  }
  
  function logToServer(message) {
  }

  (function() {
    const checkoutForm = document.querySelector('textarea[name="today_activities"]')?.closest('form');
    
    if (!checkoutForm) {
      logToServer("Check Out Form Not Found");
      return;
    }
    
    logToServer('[INFO] Checkout form found:', checkoutForm);
    logToServer('Form action:', checkoutForm.action);
    logToServer('Form method:', checkoutForm.method);
    
    const submitBtn = checkoutForm.querySelector('button[type="submit"]');
    if (submitBtn) {
      submitBtn.addEventListener('click', function(e) {
        console.log('Submit button clicked');
        console.log('Button type:', this.type);
        console.log('Form validity:', checkoutForm.checkValidity());
      });
    }
    
    checkoutForm.addEventListener('submit', function(e) {
      console.log('Form submit event triggered');
      
      const activityText = this.querySelector('textarea[name="today_activities"]')?.value.trim();
      const activityPhotos = this.querySelector('input[name="activity_photos[]"]')?.files;
      const checkoutPhoto = this.querySelector('input[name="check_out_photo"]')?.files;
      
      console.log('Form data:', {
        activityText: activityText ? 'OK' : 'EMPTY',
        activityPhotos: activityPhotos?.length || 0,
        checkoutPhoto: checkoutPhoto?.length || 0
      });
      
      const errors = [];
      let firstErrorField = null;
      
      if (!activityText) {
        errors.push('Deskripsi aktivitas wajib diisi');
        const field = this.querySelector('textarea[name="today_activities"]');
        if (field) {
          field.classList.add('border-red-500', 'bg-red-50');
          if (!firstErrorField) firstErrorField = field;
        }
      }
      
      if (!activityPhotos || activityPhotos.length === 0) {
        errors.push('Minimal 1 foto aktivitas wajib diupload');
        const field = this.querySelector('input[name="activity_photos[]"]');
        if (field && field.parentElement) {
          field.parentElement.classList.add('border', 'border-red-500', 'rounded', 'p-2', 'bg-red-50');
          if (!firstErrorField) firstErrorField = field;
        }
      }
      
      if (!checkoutPhoto || checkoutPhoto.length === 0) {
        errors.push('Foto absen pulang wajib diambil. Klik tombol "Ambil Foto Sekarang" terlebih dahulu');
        const field = this.querySelector('#preview_checkout');
        if (field) {
          field.classList.add('border', 'border-red-500', 'rounded', 'p-2', 'bg-red-50');
          field.innerHTML = '<p class="text-red-600 text-sm p-2"><i class="fas fa-exclamation-triangle mr-1"></i> Foto absen pulang belum diambil!</p>';
          if (!firstErrorField) firstErrorField = field;
        }
      }
      
      if (errors.length > 0) {
        e.preventDefault();
        console.error('Validation failed:', errors);
        
        if (firstErrorField) {
          firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        showToast('Form tidak lengkap! Silakan lengkapi: ' + errors.join(', '), 'warning');
        return false;
      }
      
      this.querySelectorAll('.border-red-500, .bg-red-50').forEach(el => {
        el.classList.remove('border-red-500', 'bg-red-50');
      });
      
      console.log('Validation passed!');
      console.log('Attempting to stop GPS tracking...');
      
      if (window.locationTracker) {
        try {
          console.log('LocationTracker found, stopping tracking...');
          window.locationTracker.stopTracking();
          console.log('GPS tracking stopped successfully');
        } catch (error) {
          console.error('Error stopping GPS tracking:', error);
        }
      } else {
        console.warn('LocationTracker not found - GPS may still be running');
      }
      
      const submitBtn = this.querySelector('button[type="submit"]');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Menyimpan...';
        submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
      }
      
      console.log('Submitting checkout form...');
      
      console.log('Form validation passed, submitting to server...');
      
      const formData = new FormData(this);
      console.log('Form data being submitted:');
      for (let [key, value] of formData.entries()) {
        if (value instanceof File) {
          console.log(`  ${key}: [File] ${value.name} (${value.size} bytes)`);
        } else {
          console.log(`  ${key}: ${value}`);
        }
      }
    });
    
    console.log('✅ Validation listener attached successfully');
  })();

  const permTypeRadios = document.querySelectorAll('input[name="permission_type"]');
  const permSingleContainer = document.getElementById('permission_single_date_container');
  const permRangeContainer = document.getElementById('permission_range_container');
  const permSingleDate = document.getElementById('permission_single_date');
  const permRangeEl = document.getElementById('permission_date_range');
  const permStartEl = document.getElementById('permission_start_date');
  const permEndEl = document.getElementById('permission_end_date');

  if (permSingleDate) {
    const today = new Date().toISOString().slice(0, 10);
    permSingleDate.value = today;
  }

  permTypeRadios.forEach(radio => {
    radio.addEventListener('change', function() {
      if (this.value === 'single') {
        permSingleContainer.style.display = 'block';
        permRangeContainer.style.display = 'none';
        if (permRangeEl) permRangeEl.value = '';
        if (permStartEl) permStartEl.value = '';
        if (permEndEl) permEndEl.value = '';
      } else {
        permSingleContainer.style.display = 'none';
        permRangeContainer.style.display = 'block';
        if (permSingleDate) permSingleDate.value = '';
      }
    });
  });

  if (permRangeEl && window.flatpickr) {
    flatpickr(permRangeEl, {
      mode: 'range',
      dateFormat: 'Y-m-d',
      allowInput: false,
      onChange: (selectedDates) => {
        if (selectedDates.length === 1) {
          const d = selectedDates[0];
          const v = d.toISOString().slice(0,10);
          permStartEl.value = v;
          permEndEl.value = v;
        } else if (selectedDates.length === 2) {
          const s = selectedDates[0].toISOString().slice(0,10);
          const e = selectedDates[1].toISOString().slice(0,10);
          permStartEl.value = s;
          permEndEl.value = e;
        }
      },
    });
  }

  const sickTypeRadios = document.querySelectorAll('input[name="sick_type"]');
  const sickSingleContainer = document.getElementById('sick_single_date_container');
  const sickRangeContainer = document.getElementById('sick_range_container');
  const sickSingleDate = document.getElementById('sick_single_date');
  const sickRangeEl = document.getElementById('sick_date_range');
  const sickStartEl = document.getElementById('sick_start_date');
  const sickEndEl = document.getElementById('sick_end_date');

  if (sickSingleDate) {
    const today = new Date().toISOString().slice(0, 10);
    sickSingleDate.value = today;
  }

  sickTypeRadios.forEach(radio => {
    radio.addEventListener('change', function() {
      if (this.value === 'single') {
        sickSingleContainer.style.display = 'block';
        sickRangeContainer.style.display = 'none';
        if (sickRangeEl) sickRangeEl.value = '';
        if (sickStartEl) sickStartEl.value = '';
        if (sickEndEl) sickEndEl.value = '';
      } else {
        sickSingleContainer.style.display = 'none';
        sickRangeContainer.style.display = 'block';
        if (sickSingleDate) sickSingleDate.value = '';
      }
    });
  });

  if (sickRangeEl && window.flatpickr) {
    const gracePeriodDays = <?= $GRACE_PERIOD_DAYS ?>;
    const minDate = new Date();
    minDate.setDate(minDate.getDate() - gracePeriodDays);
    
    flatpickr(sickRangeEl, {
      mode: 'range',
      dateFormat: 'Y-m-d',
      allowInput: false,
      minDate: minDate,
      maxDate: 'today',
      onChange: (selectedDates) => {
        if (selectedDates.length === 1) {
          const d = selectedDates[0];
          const v = d.toISOString().slice(0,10);
          sickStartEl.value = v;
          sickEndEl.value = v;
        } else if (selectedDates.length === 2) {
          const s = selectedDates[0].toISOString().slice(0,10);
          const e = selectedDates[1].toISOString().slice(0,10);
          sickStartEl.value = s;
          sickEndEl.value = e;
        }
      },
    });
  }
  document.getElementById('permission_proof')?.addEventListener('change', function() {
    renderSinglePreview(this, 'preview_permission');
  });

  document.getElementById('sick_proof')?.addEventListener('change', function() {
    renderSinglePreview(this, 'preview_sick');
  });

  document.querySelector('input[name="manual_photo"]')?.addEventListener('change', function() {
    const preview = document.getElementById('photoPreview');
    const img = document.getElementById('previewImg');
    if (this.files && this.files[0]) {
      const reader = new FileReader();
      reader.onload = function(e) {
        img.src = e.target.result;
        preview.classList.remove('hidden');
      };
      reader.readAsDataURL(this.files[0]);
    } else {
      preview.classList.add('hidden');
    }
  });

  const actInput = document.getElementById('activity_photos');
  if (actInput) {
    actInput.addEventListener('change', () => {
      const preview = document.getElementById('preview_activities');
      preview.innerHTML = '';
      if (actInput.files.length > 5) {
        showToast('Maksimal 5 foto.', 'warning');
        actInput.value = '';
        return;
      }
      Array.from(actInput.files).forEach(file => {
        const img = document.createElement('img');
        img.className = 'w-full h-20 object-cover rounded-xl shadow-md';
        const reader = new FileReader();
        reader.onload = e => img.src = e.target.result;
        reader.readAsDataURL(file);
        preview.appendChild(img);
      });
    });
  }

  let attendanceModalOpenCount = 0;
  function ensureAttendanceModalPortal(modalEl) {
    if (!modalEl || modalEl.dataset.portalReady === '1') return;
    document.body.appendChild(modalEl);
    modalEl.dataset.portalReady = '1';
  }

  function lockAttendanceModalScroll() {
    attendanceModalOpenCount += 1;
    document.body.style.overflow = 'hidden';
  }

  function unlockAttendanceModalScroll() {
    attendanceModalOpenCount = Math.max(0, attendanceModalOpenCount - 1);
    if (attendanceModalOpenCount === 0) {
      document.body.style.overflow = '';
    }
  }

  <?php if ($canManageManualAttendance): ?>
  const modalManual = document.getElementById('modalManualAttendance');
  const btnOpenManual = document.getElementById('btnOpenManualAttendance');
  const btnCloseManual = document.getElementById('btnCloseManualAttendance');
  const btnCancelManual = document.getElementById('btnCancelManualAttendance');
  const statusSelect = document.querySelector('select[name="manual_status"]');
  const timeFields = document.getElementById('timeFields');

  ensureAttendanceModalPortal(modalManual);

  function openManualModal() {
    if (!modalManual) return;
    if (modalManual.style.display === 'flex') return;
    modalManual.style.display = 'flex';
    lockAttendanceModalScroll();
  }

  function closeManualModal() {
    if (!modalManual) return;
    if (modalManual.style.display === 'none') return;
    modalManual.style.display = 'none';
    unlockAttendanceModalScroll();
  }
  
  if (btnOpenManual && modalManual) {
    btnOpenManual.addEventListener('click', () => {
      openManualModal();
    });
  }
  
  if (btnCloseManual && modalManual) {
    btnCloseManual.addEventListener('click', () => {
      closeManualModal();
    });
  }
  
  if (btnCancelManual && modalManual) {
    btnCancelManual.addEventListener('click', () => {
      closeManualModal();
    });
  }
  
  if (modalManual) {
    modalManual.addEventListener('click', (e) => {
      if (e.target === modalManual) {
        closeManualModal();
      }
    });
  }
  
  if (statusSelect && timeFields) {
    statusSelect.addEventListener('change', () => {
      if (statusSelect.value === 'Masuk') {
        timeFields.classList.remove('hidden');
      } else {
        timeFields.classList.add('hidden');
      }
    });
  }
  
  const adminFormEl = document.getElementById('admin_attendance_form');
  if (adminFormEl) {
    adminFormEl.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      fetch('<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>', {
        method: 'POST',
        body: formData,
      })
      .then(response => response.text())
      .then(html => {
        adminFormEl.reset();
        closeManualModal();
        timeFields.classList.add('hidden');
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const message = doc.querySelector('.message');
        if (message) {
          const msgEl = document.createElement('div');
          msgEl.className = 'mb-4 p-4 rounded-lg ' + (message.classList.contains('bg-red-100') ? 'bg-red-50 text-red-700 border-red-200' : 'bg-green-50 text-green-700 border-green-200') + ' text-sm whitespace-pre-line';
          msgEl.textContent = message.textContent;
          const container = document.querySelector('.container') || document.body;
          container.insertBefore(msgEl, container.firstChild);

          setTimeout(() => {
            if (msgEl.parentNode) {
              msgEl.parentNode.removeChild(msgEl);
            }
          }, 5000);
        }
      })
      .catch(err => console.error(err));
    });
  }
  <?php endif; ?>

  (function() {
    const modalWH = document.getElementById('modalWorkHours');
    const btnOpenWH = document.getElementById('btnOpenWorkHours');
    const btnCloseWH = document.getElementById('btnCloseWorkHours');
    const btnCancelWH = document.getElementById('btnCancelWorkHours');
    if (!modalWH) return;

    ensureAttendanceModalPortal(modalWH);

    function openWorkHoursModal() {
      if (modalWH.style.display === 'flex') return;
      modalWH.style.display = 'flex';
      lockAttendanceModalScroll();
    }

    function closeWorkHoursModal() {
      if (modalWH.style.display === 'none') return;
      modalWH.style.display = 'none';
      unlockAttendanceModalScroll();
    }

    if (btnOpenWH) btnOpenWH.addEventListener('click', () => openWorkHoursModal());
    if (btnCloseWH) btnCloseWH.addEventListener('click', () => closeWorkHoursModal());
    if (btnCancelWH) btnCancelWH.addEventListener('click', () => closeWorkHoursModal());
    modalWH.addEventListener('click', (e) => { if (e.target === modalWH) closeWorkHoursModal(); });

    document.querySelectorAll('.wh-off-toggle').forEach(cb => {
      cb.addEventListener('change', function() {
        const day = this.dataset.day;
        const row = document.getElementById('whRow' + day);
        const inputs = row.querySelectorAll('.wh-time-input');
        inputs.forEach(inp => {
          inp.disabled = this.checked;
          inp.classList.toggle('opacity-40', this.checked);
        });
      });
    });
  })();

  (function() {
    const modalRNS     = document.getElementById('modalResetNightShift');
    const btnOpenRNS   = document.getElementById('btnOpenResetNightShift');
    const btnCloseRNS  = document.getElementById('btnCloseResetNightShift');
    const btnCancelRNS = document.getElementById('btnCancelResetNightShift');
    if (!modalRNS) return;

    ensureAttendanceModalPortal(modalRNS);

    function openRNSModal() {
      if (modalRNS.style.display === 'flex') return;
      modalRNS.style.display = 'flex';
      lockAttendanceModalScroll();
    }
    function closeRNSModal() {
      if (modalRNS.style.display === 'none') return;
      modalRNS.style.display = 'none';
      unlockAttendanceModalScroll();
    }

    if (btnOpenRNS)   btnOpenRNS.addEventListener('click',   () => openRNSModal());
    if (btnCloseRNS)  btnCloseRNS.addEventListener('click',  () => closeRNSModal());
    if (btnCancelRNS) btnCancelRNS.addEventListener('click', () => closeRNSModal());
    modalRNS.addEventListener('click', (e) => { if (e.target === modalRNS) closeRNSModal(); });
  })();
  
  </script>

  
  <script src="./public/assets/js/location-tracker.js"></script>
  
  
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    console.log('[Attendance] Page loaded, checking GPS tracking status...');
    console.log('[Attendance] window.locationTracker exists:', typeof window.locationTracker !== 'undefined');
    
    <?php if ($hasAttendanceTrackToday && !$isCheckedOut): ?>
    const attendanceId = <?= $myAttendance['id'] ?? 'null' ?>;
    console.log('[Attendance] Has attendance today, checking if should start tracking...');
    console.log('[Attendance] Attendance ID:', attendanceId);
    console.log('[Attendance] Is checked out:', <?= $isCheckedOut ? 'true' : 'false' ?>);
    
    if (attendanceId && typeof window.locationTracker !== 'undefined') {
      console.log('[Attendance] All conditions met, starting GPS tracking...');
      
      setTimeout(function() {
        console.log('[Attendance] Calling locationTracker.startTracking(' + attendanceId + ')...');
        
        window.locationTracker.startTracking(attendanceId)
          .then(function(success) {
            console.log('[Attendance] startTracking promise resolved, success:', success);
            if (success) {
              console.log('[Attendance] GPS tracking started successfully');
              
              const trackingBadge = document.createElement('div');
              trackingBadge.id = 'tracking-status-badge';
              trackingBadge.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-4 py-2 rounded-full shadow-lg flex items-center gap-2 text-sm z-50';
              trackingBadge.innerHTML = '<span class="inline-block w-2 h-2 bg-white rounded-full animate-pulse"></span> GPS Tracking Aktif';
              document.body.appendChild(trackingBadge);
            } else {
              console.warn('[Attendance] GPS tracking could not be started (returned false)');
            }
          })
          .catch(function(error) {
            console.error('[Attendance] GPS tracking error:', error);
            console.error('[Attendance] Error details:', error.message, error.stack);
          });
      }, 1500);
    } else {
      console.warn('[Attendance] Cannot start tracking:');
      console.warn('[Attendance]   - Attendance ID:', attendanceId);
      console.warn('[Attendance]   - locationTracker available:', typeof window.locationTracker !== 'undefined');
    }
    <?php else: ?>
    console.log('[Attendance] Not starting tracking:');
    console.log('[Attendance]   - Has attendance today:', <?= $hasAttendanceTrackToday ? 'true' : 'false' ?>);
    console.log('[Attendance]   - Is checked out:', <?= $isCheckedOut ? 'true' : 'false' ?>);
    <?php endif; ?>

    <?php if ($isCheckedOut): ?>
    console.log('[Attendance] User has checked out, will stop tracking...');
    
    setTimeout(function() {
      if (typeof window.locationTracker !== 'undefined' && window.locationTracker.isTracking) {
        console.log('[Attendance] Calling locationTracker.stopTracking()...');
        
        window.locationTracker.stopTracking()
          .then(function() {
            console.log('[Attendance] GPS tracking stopped successfully');
            
            const badge = document.getElementById('tracking-status-badge');
            if (badge) {
              badge.remove();
            }
            
            console.log('[Attendance] Check-out complete, tracking stopped, summary displayed');
          })
          .catch(function(error) {
            console.error('[Attendance] Stop tracking error:', error);
          });
      } else {
        console.log('[Attendance] Tracking not active or locationTracker not available');
      }
    }, 2000);
    <?php endif; ?>
  });
  </script>
</div>
