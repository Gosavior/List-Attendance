<?php
 

@date_default_timezone_set('Asia/Jakarta');


$isCommandLine = php_sapi_name() === 'cli';
$hasValidKey = isset($_GET['cron_key']) && $_GET['cron_key'] === 'alpha_cron_2025';

if (!$isCommandLine && !$hasValidKey) {
    http_response_code(403);
    die('Access denied. This script should run via cron job.');
}

require_once __DIR__ . '/../config/database.php';


function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logFile = __DIR__ . '/../../logs/alpha-cron.log';
    
    
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
    
    if (php_sapi_name() === 'cli') {
        echo "[$timestamp] $message\n";
    }
}

function updateAttendanceStatus($pdo, $date) {
    $updated = 0;
    
    try {
        
        $dayOfWeek = (int)date('w', strtotime($date));
        $workStartTime = '08:30:00'; 
        $lateToleranceMinutes = 0;
        
        try {
            
            $colStmt = $pdo->query("SHOW COLUMNS FROM company_settings");
            $keyColumn = null;
            while ($colRow = $colStmt->fetch(PDO::FETCH_ASSOC)) {
                if (in_array($colRow['Field'], ['setting_key', 'setting_name'])) {
                    $keyColumn = $colRow['Field'];
                    break;
                }
            }
            if ($keyColumn) {
                
                $whStmt = $pdo->prepare("SELECT setting_value FROM company_settings WHERE {$keyColumn} = ?");
                $whStmt->execute(["work_hours_$dayOfWeek"]);
                $whVal = $whStmt->fetchColumn();
                if ($whVal) {
                    $wh = json_decode($whVal, true);
                    if ($wh && isset($wh['start']) && $wh['start'] !== 'off') {
                        $workStartTime = $wh['start'] . ':00';
                    }
                }
                
                $tolStmt = $pdo->prepare("SELECT setting_value FROM company_settings WHERE {$keyColumn} = ?");
                $tolStmt->execute(['attendance_late_tolerance_minutes']);
                $tolVal = $tolStmt->fetchColumn();
                if ($tolVal !== false) {
                    $lateToleranceMinutes = max(0, min(180, (int)$tolVal));
                }
            }
        } catch (Exception $e) {
            logMessage("Warning: Could not read company_settings, using defaults: " . $e->getMessage());
        }
        
        $onTimeLimitTime = date('H:i:s', strtotime($workStartTime) + ($lateToleranceMinutes * 60));
        $lateTime = $date . ' ' . $onTimeLimitTime;
        logMessage("Late cutoff for $date: $lateTime (work_start=$workStartTime, tolerance={$lateToleranceMinutes}min)");
        
        
        $stmt = $pdo->prepare("
            UPDATE attendances 
            SET status = 'Terlambat' 
            WHERE attendance_date = ? 
            AND check_in_time > ? 
            AND check_in_time != '0000-00-00 00:00:00'
            AND status IN ('Hadir', '')
        ");
        $stmt->execute([$date, $lateTime]);
        $lateCount = $stmt->rowCount();
        
        
        $stmt = $pdo->prepare("
            UPDATE attendances 
            SET status = 'Hadir' 
            WHERE attendance_date = ? 
            AND check_in_time <= ? 
            AND check_in_time != '0000-00-00 00:00:00'
            AND status IN ('', 'Terlambat')
        ");
        $stmt->execute([$date, $lateTime]);
        $onTimeCount = $stmt->rowCount();
        
        
        $stmt = $pdo->prepare("
            UPDATE attendances 
            SET status = CASE 
                WHEN status = 'Terlambat' THEN 'Terlambat'
                WHEN check_out_time > ? THEN 'Lembur'
                WHEN check_out_time IS NULL OR check_out_time = '0000-00-00 00:00:00' THEN 'Not Checked Out'
                ELSE status
            END
            WHERE attendance_date = ? 
            AND check_in_time != '0000-00-00 00:00:00'
            AND status NOT IN ('Izin', 'Sakit', 'Cuti', 'Alpha')
        ");
        $overtimeTime = $date . ' 19:00:00';
        $stmt->execute([$overtimeTime, $date]);
        $overtimeCount = $stmt->rowCount();
        
        logMessage("Status updated for $date: $onTimeCount Hadir, $lateCount Terlambat, $overtimeCount Lembur/Belum Checkout");
        
        return $onTimeCount + $lateCount + $overtimeCount;
        
    } catch (Exception $e) {
        logMessage("Error updating attendance status: " . $e->getMessage());
        return 0;
    }
}

function generateAlphaForMissingStaff($pdo, $date) {
    $created = 0;
    
    try {
        
        $stmt = $pdo->prepare("SELECT holiday_name, holiday_type FROM company_holidays WHERE holiday_date = ?");
        $stmt->execute([$date]);
        $holiday = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($holiday) {
            
            logMessage("Date $date is a holiday: {$holiday['holiday_name']} ({$holiday['holiday_type']})");
            $libur_created = generateLiburForMissingStaff($pdo, $date, $holiday['holiday_name']);
            logMessage("Created $libur_created 'Libur' records for holiday: {$holiday['holiday_name']}");
            return $libur_created;
        }
        
        
        logMessage("Processing Alpha generation for $date (not a holiday)");
        
        
        $stmt = $pdo->prepare("
            SELECT id, full_name, role 
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
                    SELECT lr.*, u.full_name 
                    FROM leave_requests lr 
                    JOIN users u ON lr.user_id = u.id 
                    WHERE lr.user_id = ? 
                    AND ? BETWEEN lr.start_date AND lr.end_date 
                    AND lr.status = "approved" 
                    LIMIT 1
                ');
                $stmt->execute([$user['id'], $date]);
                $approvedLeave = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($approvedLeave) {
                    
                    $status = match($approvedLeave['type']) {
                        'permission' => 'Izin',
                        'sick' => 'Sakit',
                        'leave' => 'Cuti',
                        default => 'Izin'
                    };
                    $notes = $approvedLeave['reason'] ?: $status;
                    
                    $stmt = $pdo->prepare('
                        INSERT INTO attendances 
                        (user_id, attendance_date, today_plan, check_in_time, check_out_time, check_in_photo, check_out_photo, check_out_location, notes, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ');
                    $stmt->execute([
                        $user['id'], 
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
                    logMessage("Created {$status} record for {$user['full_name']} on {$date}");
                } else {
                    
                    $stmt = $pdo->prepare('
                        INSERT INTO attendances 
                        (user_id, attendance_date, today_plan, check_in_time, check_out_time, check_in_photo, check_out_photo, check_out_location, notes, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ');
                    $stmt->execute([
                        $user['id'], 
                        $date, 
                        '', 
                        '0000-00-00 00:00:00', 
                        null, 
                        '', 
                        null, 
                        '', 
                        'Tidak hadir tanpa keterangan (Auto-generated)', 
                        'Alpha' 
                    ]);
                    $created++;
                    logMessage("Created Alpha record for {$user['full_name']} on {$date}");
                }
            }
        }
        
        return $created;
        
    } catch (Exception $e) {
        logMessage("Error generating Alpha records: " . $e->getMessage());
        return 0;
    }
}

function generateLiburForMissingStaff($pdo, $date, $holidayName) {
    $created = 0;
    
    try {
        
        $stmt = $pdo->prepare("
            SELECT id, full_name, role 
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
                    "Hari libur: $holidayName", 
                    'Libur'
                ]);
                $created++;
                logMessage("Created Libur record for {$user['full_name']} on {$date} (Holiday: $holidayName)");
            }
        }
        
        return $created;
        
    } catch (Exception $e) {
        logMessage("Error generating Libur records: " . $e->getMessage());
        return 0;
    }
}

function processOvertimeReset($pdo, $currentDate) {
    try {
        
        $yesterday = date('Y-m-d', strtotime($currentDate . ' -1 day'));
        
        $stmt = $pdo->prepare("
            UPDATE attendances 
            SET overtime_hours = CASE 
                WHEN check_out_time > ? AND check_out_time IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, ?, check_out_time) / 60.0
                ELSE 0
            END,
            status = CASE 
                WHEN status = 'Lembur' AND check_out_time <= ? THEN 'Hadir'
                ELSE status
            END
            WHERE attendance_date = ?
            AND status NOT IN ('Alpha', 'Izin', 'Sakit', 'Cuti')
        ");
        
        $normalEndTime = $yesterday . ' 17:30:00';
        $overtimeStartTime = $yesterday . ' 19:00:00';
        
        $stmt->execute([$overtimeStartTime, $normalEndTime, $overtimeStartTime, $yesterday]);
        $processed = $stmt->rowCount();
        
        logMessage("Processed overtime calculation for $yesterday: $processed records updated");
        return $processed;
        
    } catch (Exception $e) {
        logMessage("Error processing overtime reset: " . $e->getMessage());
        return 0;
    }
}


try {
    $currentDate = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $currentTime = date('H:i:s');
    
    logMessage("=== Auto Alpha Cron Job Started ===");
    logMessage("Current Date: $currentDate");
    logMessage("Processing Date: $yesterday");
    logMessage("Current Time: $currentTime");
    
    
    $statusUpdated = updateAttendanceStatus($pdo, $yesterday);
    
    
    $alphaCreated = generateAlphaForMissingStaff($pdo, $yesterday);
    
    
    $overtimeProcessed = processOvertimeReset($pdo, $currentDate);
    
    logMessage("=== Summary ===");
    logMessage("Status Updates: $statusUpdated");
    logMessage("Alpha Records Created: $alphaCreated");
    logMessage("Overtime Records Processed: $overtimeProcessed");
    logMessage("=== Auto Alpha Cron Job Completed ===");
    
} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
}


if (!$isCommandLine) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Auto Alpha cron job completed',
        'date_processed' => $yesterday,
        'alpha_created' => $alphaCreated ?? 0,
        'status_updated' => $statusUpdated ?? 0,
        'overtime_processed' => $overtimeProcessed ?? 0,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>