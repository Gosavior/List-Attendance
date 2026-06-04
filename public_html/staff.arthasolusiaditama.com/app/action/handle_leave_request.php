<?php
@date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/audit-log.php';
require_once __DIR__ . '/../helpers/socket-notify.php';

header('Content-Type: application/json');


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$roleStr = strtolower(trim($_SESSION['role'] ?? ''));
$isAdmin = $roleStr && preg_match('/admin|administrator/', $roleStr);
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

try {
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
    
    try { $pdo->exec("ALTER TABLE attendance_requests ADD COLUMN IF NOT EXISTS requested_check_in_time TIME DEFAULT NULL"); } catch (Throwable $__e) {}
} catch (Throwable $e) { }

$id = $_POST['id'] ?? null;
$action = $_POST['action'] ?? '';
if (!$id || !in_array($action, ['approve','reject'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Bad request']);
    exit;
}

try {
    $source = $_POST['source'] ?? 'leave';
    if ($source === 'attendance_request') {
        $stmt = $pdo->prepare('SELECT ar.*, "attendance_request" AS type, ar.reason, ar.attendance_date AS start_date, ar.attendance_date AS end_date, ar.photo_path AS proof_path, "attendance_request" AS request_source FROM attendance_requests ar WHERE ar.id = ? LIMIT 1');
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare('SELECT lr.*, "leave" AS request_source FROM leave_requests lr WHERE lr.id = ? LIMIT 1');
        $stmt->execute([$id]);
    }
    $req = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$req) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }
    if ($req['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Request already processed']);
        exit;
    }

    if ($action === 'reject') {
        if (($req['request_source'] ?? 'leave') === 'attendance_request') {
            $upd = $pdo->prepare('UPDATE attendance_requests SET status = "rejected", decided_at = NOW(), decided_by = ? WHERE id = ?');
        } else {
            $upd = $pdo->prepare('UPDATE leave_requests SET status = "rejected", decided_at = NOW(), decided_by = ? WHERE id = ?');
        }
        $upd->execute([$_SESSION['user_id'], $id]);
        auditLog($pdo, 'reject_' . ($source === 'attendance_request' ? 'attendance_request' : 'leave_request'), [
            'target_type' => $source === 'attendance_request' ? 'attendance_request' : 'leave_request',
            'target_id' => (int)$id,
            'target_user_id' => (int)$req['user_id'],
            'details' => ['type' => $req['type'] ?? $source, 'reason' => $req['reason'] ?? '']
        ]);
        socketNotify([(int)$req['user_id']], 'leave_action', 'Permintaan Anda ditolak');
        echo json_encode(['success' => true]);
        exit;
    }

    if (($req['request_source'] ?? 'leave') === 'attendance_request') {
        $pdo->beginTransaction();
        try {
            $updReq = $pdo->prepare('UPDATE attendance_requests SET status = "approved", decided_at = NOW(), decided_by = ? WHERE id = ?');
            $updReq->execute([$_SESSION['user_id'], $id]);

            $userId = (int)$req['user_id'];
            $attendanceDate = $req['attendance_date'];
            $notes = trim('REQUEST ABSENSI: ' . ($req['reason'] ?? ''));

            
            $requestType = $req['request_type'] ?? 'checkin';

            if ($requestType === 'missed_checkout') {
                
                
                $missedDate = $req['missed_checkout_date'] ?? null;
                if (!$missedDate) {
                    throw new Exception('Tanggal absen pulang terlewat tidak ditemukan pada request.');
                }

                $requestedCheckOutTime = !empty($req['requested_check_out_time'])
                    ? $req['requested_check_out_time']  
                    : '17:30:00';                       
                $checkOutDateTime = $missedDate . ' ' . $requestedCheckOutTime;

                
                $exists = $pdo->prepare('SELECT id, status, check_in_time, check_out_time FROM attendances WHERE user_id = ? AND attendance_date = ? LIMIT 1');
                $exists->execute([$userId, $missedDate]);
                $existingRecord = $exists->fetch(PDO::FETCH_ASSOC);

                if (!$existingRecord) {
                    throw new Exception('Tidak ditemukan data absensi untuk tanggal ' . $missedDate);
                }

                
                if (!empty($existingRecord['check_out_time']) && $existingRecord['check_out_time'] !== '0000-00-00 00:00:00') {
                    throw new Exception('Absen pulang untuk tanggal tersebut sudah terisi.');
                }

                
                $newNote = "Request Pulang: " . $requestedCheckOutTime . " - " . ($req['reason'] ?? '');
                $stmtAtt = $pdo->prepare('UPDATE attendances SET check_out_time = ?, notes = ?, status = CASE WHEN status = "Not Checked Out" THEN "Hadir" ELSE status END, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmtAtt->execute([
                    $checkOutDateTime,
                    $newNote,
                    $existingRecord['id']
                ]);
                $attendanceId = (int)$existingRecord['id'];

                $pdo->prepare('UPDATE attendance_requests SET attendance_id = ? WHERE id = ?')->execute([$attendanceId, $id]);
                $pdo->commit();
                auditLog($pdo, 'approve_attendance_request', [
                    'target_type' => 'attendance_request',
                    'target_id' => (int)$id,
                    'target_user_id' => $userId,
                    'details' => ['request_type' => $requestType, 'date' => $missedDate, 'checkout_time' => $requestedCheckOutTime]
                ]);
                socketNotify([$userId], 'leave_action', 'Request absen pulang Anda disetujui');
                echo json_encode(['success' => true]);
            } else {
                
                
                
                $requestedTime = !empty($req['requested_check_in_time'])
                    ? $req['requested_check_in_time']          
                    : date('H:i:s');                           
                $checkInDateTime = $attendanceDate . ' ' . $requestedTime;

                
                $onTimeThreshold = strtotime('1970-01-01 09:00:00');
                $requestedTs     = strtotime('1970-01-01 ' . $requestedTime);
                $status = ($requestedTs <= $onTimeThreshold) ? 'Hadir' : 'Terlambat';

                $exists = $pdo->prepare('SELECT id, status FROM attendances WHERE user_id = ? AND attendance_date = ? LIMIT 1');
                $exists->execute([$userId, $attendanceDate]);
                $existingRecord = $exists->fetch(PDO::FETCH_ASSOC);

                if ($existingRecord) {
                    if (($existingRecord['status'] ?? '') !== 'Alpha') {
                        throw new Exception('Data absensi untuk tanggal tersebut sudah ada.');
                    }
                    $stmtAtt = $pdo->prepare('UPDATE attendances SET today_plan = ?, check_in_time = ?, check_in_photo = ?, check_in_lat = ?, check_in_lng = ?, check_in_location = ?, notes = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                    $stmtAtt->execute([
                        $req['today_plan'] ?? '',
                        $checkInDateTime,
                        $req['photo_path'] ?? '',
                        $req['gps_lat'] ?? null,
                        $req['gps_lng'] ?? null,
                        $req['location_name'] ?? null,
                        $notes,
                        $status,
                        $existingRecord['id']
                    ]);
                    $attendanceId = (int)$existingRecord['id'];
                } else {
                    $stmtAtt = $pdo->prepare('INSERT INTO attendances (user_id, attendance_date, today_plan, check_in_time, check_in_photo, check_in_lat, check_in_lng, check_in_location, notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                    $stmtAtt->execute([
                        $userId,
                        $attendanceDate,
                        $req['today_plan'] ?? '',
                        $checkInDateTime,
                        $req['photo_path'] ?? '',
                        $req['gps_lat'] ?? null,
                        $req['gps_lng'] ?? null,
                        $req['location_name'] ?? null,
                        $notes,
                        $status
                    ]);
                    $attendanceId = (int)$pdo->lastInsertId();
                }

                $pdo->prepare('UPDATE attendance_requests SET attendance_id = ? WHERE id = ?')->execute([$attendanceId, $id]);
                $pdo->commit();
                auditLog($pdo, 'approve_attendance_request', [
                    'target_type' => 'attendance_request',
                    'target_id' => (int)$id,
                    'target_user_id' => $userId,
                    'details' => ['request_type' => 'checkin', 'date' => $attendanceDate, 'checkin_time' => $requestedTime, 'status' => $status]
                ]);
                socketNotify([$userId], 'leave_action', 'Request absensi masuk Anda disetujui');
                echo json_encode(['success' => true]);
            }
        } catch (Exception $ex) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal memproses request absensi: ' . $ex->getMessage()]);
        }
        exit;
    }

    
    $pdo->beginTransaction();
    try {
        
        if ($req['status'] === 'pending') {
            $upd = $pdo->prepare('UPDATE leave_requests SET status = "approved", decided_at = NOW(), decided_by = ? WHERE id = ?');
            $upd->execute([$_SESSION['user_id'], $id]);
        }

        $userId = (int)$req['user_id'];
        $type = $req['type']; 
        $status = $type === 'permission' ? 'Izin' : 'Sakit';
        $reason = trim($req['reason'] ?? '');
        $proof = $req['proof_path'] ?? null;
        $s = strtotime($req['start_date']);
        $e = strtotime($req['end_date']);
        if ($s === false || $e === false) { throw new Exception('Tanggal tidak valid'); }
        if ($e < $s) { [$s,$e] = [$e,$s]; }

        $ins = $pdo->prepare('INSERT INTO attendances (user_id, attendance_date, status, notes, check_in_photo) VALUES (?,?,?,?,?)');
        $upd = $pdo->prepare('UPDATE attendances SET status = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND attendance_date = ?');
        $exists = $pdo->prepare('SELECT id, status FROM attendances WHERE user_id = ? AND attendance_date = ?');
        
        for ($t=$s; $t <= $e; $t = strtotime('+1 day', $t)) {
            $d = date('Y-m-d', $t);
            $exists->execute([$userId, $d]);
            $existingRecord = $exists->fetch(PDO::FETCH_ASSOC);
            
            $notes = $status === 'Sakit' ? ($reason ?: 'Sakit') : ($reason ?: 'Izin');
            
            if ($existingRecord) {
                
                if ($existingRecord['status'] === 'Alpha') {
                    $upd->execute([$status, $notes, $userId, $d]);
                }
                
            } else {
                
                $ins->execute([$userId, $d, $status, $notes, $proof]);
            }
        }

        $pdo->commit();
        auditLog($pdo, 'approve_leave_request', [
            'target_type' => 'leave_request',
            'target_id' => (int)$id,
            'target_user_id' => $userId,
            'details' => ['type' => $type, 'status' => $status, 'start_date' => $req['start_date'], 'end_date' => $req['end_date'], 'reason' => $reason]
        ]);
        socketNotify([$userId], 'leave_action', 'Permohonan ' . ($type === 'permission' ? 'izin' : 'sakit') . ' Anda disetujui');
        echo json_encode(['success' => true]);
    } catch (Exception $ex) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memproses: '.$ex->getMessage()]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: '.$e->getMessage()]);
}
?>