<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/avatar.php';
require_once __DIR__ . '/../helpers/socket-notify.php';
require_once __DIR__ . '/../helpers/audit-log.php';

$currentRole = strtolower($_SESSION['role'] ?? '');
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$currentMonth = (int) date('m');
$currentYear = (int) date('Y');


$canSubmit = in_array($currentRole, ['technician', 'hse', 'daily', 'internship', 'driver', 'sales']);
$canApprove = in_array($currentRole, ['administrator']);
$canViewAll = in_array($currentRole, ['administrator', 'direktur']);


try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS fuel_reimbursements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        reimburse_type ENUM('bensin','karcis','lainnya') NOT NULL DEFAULT 'bensin',
        request_date DATE NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        distance_km DECIMAL(10,5) DEFAULT NULL,
        destination VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        proof_path VARCHAR(500) DEFAULT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        admin_decided_by INT DEFAULT NULL,
        admin_decided_at DATETIME DEFAULT NULL,
        reject_reason TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_status (status),
        INDEX idx_request_date (request_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    try {
        $pdo->exec("ALTER TABLE fuel_reimbursements ADD COLUMN reimburse_type ENUM('bensin','karcis','lainnya') NOT NULL DEFAULT 'bensin' AFTER user_id");
    } catch (Exception $e) {
    }
    
    try {
        $pdo->exec("ALTER TABLE fuel_reimbursements MODIFY COLUMN reimburse_type ENUM('bensin','karcis','lainnya') NOT NULL DEFAULT 'bensin'");
    } catch (Exception $e) {
    }
    
    try {
        $pdo->exec("ALTER TABLE fuel_reimbursements MODIFY COLUMN distance_km DECIMAL(10,5) DEFAULT NULL");
    } catch (Exception $e) {
    }
    
    try {
        $pdo->exec("ALTER TABLE fuel_reimbursements ADD COLUMN request_date_end DATE DEFAULT NULL AFTER request_date");
    } catch (Exception $e) {
    }
    
    try {
        $pdo->exec("ALTER TABLE fuel_reimbursements ADD COLUMN request_dates TEXT DEFAULT NULL AFTER request_date_end");
    } catch (Exception $e) {
    }
    
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS fuel_quota_adjustments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_period (user_id, period_start, period_end)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e) {
    }
} catch (Exception $e) {
    
}


$FUEL_DAILY_QUOTA = 15000;
$periodQuota = 0;
$periodUsed = 0;
$periodAvailable = 0;
$lateTodayBensin = false;
$quotaDaysDetail = []; 
$periodStart = '';
$periodEnd = '';
$periodLabel = '';


$hasQuota = $canSubmit && !in_array($currentRole, ['technician', 'hse']);

if ($hasQuota) {
    
    $todayDt = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    $todayDay = (int) $todayDt->format('j');
    $todayStr = $todayDt->format('Y-m-d');
    $yearMonth = $todayDt->format('Y-m');

    if ($todayDay <= 15) {
        $periodStart = $yearMonth . '-01';
        $periodEnd = $yearMonth . '-15';
        $periodLabel = 'Tgl 1-15 ' . $todayDt->format('M Y');
    } else {
        $periodStart = $yearMonth . '-16';
        $periodEnd = $todayDt->format('Y-m-t'); 
        $periodLabel = 'Tgl 16-' . $todayDt->format('t M Y');
    }

    
    $attStmt = $pdo->prepare("
        SELECT attendance_date, status
        FROM attendances
        WHERE user_id = ? AND attendance_date BETWEEN ? AND ?
        ORDER BY attendance_date ASC
    ");
    $attStmt->execute([$currentUserId, $periodStart, $todayStr]);
    $periodAttendance = $attStmt->fetchAll(PDO::FETCH_ASSOC);

    $nonLateDays = 0;
    foreach ($periodAttendance as $att) {
        $isEligibleStatus = in_array(($att['status'] ?? ''), ['Hadir', 'Lembur'], true);
        if ($isEligibleStatus)
            $nonLateDays++;
        $quotaDaysDetail[] = [
            'date' => $att['attendance_date'],
            'status' => $att['status'],
            'late' => !$isEligibleStatus, 
        ];
        if ($att['attendance_date'] === $todayStr && $att['status'] === 'Terlambat') {
            $lateTodayBensin = true;
        }
    }

    $periodQuota = $nonLateDays * $FUEL_DAILY_QUOTA;

    $adjStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM fuel_quota_adjustments WHERE user_id = ? AND period_start = ? AND period_end = ?");
    $adjStmt->execute([$currentUserId, $periodStart, $periodEnd]);
    $periodQuota += (float) $adjStmt->fetchColumn();

    $usedStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM fuel_reimbursements
        WHERE user_id = ? AND reimburse_type = 'bensin'
          AND request_date BETWEEN ? AND ?
          AND status IN ('pending', 'approved')
    ");
    $usedStmt->execute([$currentUserId, $periodStart, $periodEnd]);
    $periodUsed = (float) $usedStmt->fetchColumn();
    $periodAvailable = max(0, $periodQuota - $periodUsed);
}

$calendarDays = [];
if ($hasQuota && $periodStart) {
    $attMap = [];
    foreach ($quotaDaysDetail as $qd) {
        $attMap[$qd['date']] = $qd['status'];
    }
    $calCur = new DateTime($periodStart);
    $calMax = new DateTime(min($periodEnd, date('Y-m-d')));
    while ($calCur <= $calMax) {
        $ds = $calCur->format('Y-m-d');
        $dow = (int) $calCur->format('N');
        $st = $attMap[$ds] ?? null;
        $calendarDays[] = [
            'date' => $ds,
            'day' => (int) $calCur->format('j'),
            'dow' => $dow,
            'status' => $st,
            'ok' => in_array($st, ['Hadir', 'Lembur'], true),
        ];
        $calCur->modify('+1 day');
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($action && $method === 'POST') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Unknown action'];
    try {
        switch ($action) {

            
            case 'submit_reimbursement':
                if (!$canSubmit)
                    throw new Exception('Akses ditolak. Hanya technician, daily, dan internship yang bisa mengajukan.');

                $reimburseType = trim($_POST['reimburse_type'] ?? 'bensin');
                if (!in_array($reimburseType, ['bensin', 'karcis', 'lainnya']))
                    $reimburseType = 'bensin';
                $requestDate = trim($_POST['request_date'] ?? '');
                $requestDateEnd = trim($_POST['request_date_end'] ?? '') ?: null;
                $requestDatesArr = [];

                if ($requestDate) {
                    $endDt = $requestDateEnd ?: $requestDate;
                    $curr = $requestDate;
                    while ($curr <= $endDt) {
                        $requestDatesArr[] = $curr;
                        $curr = date('Y-m-d', strtotime($curr . ' +1 day'));
                    }
                }
                $amount = (float) ($_POST['amount'] ?? 0);
                $distanceKm = !empty($_POST['distance_km']) ? (float) $_POST['distance_km'] : null;
                $destination = trim($_POST['destination'] ?? '');
                $description = trim($_POST['description'] ?? '');

                if (!$requestDate)
                    throw new Exception('Tanggal wajib diisi');
                if ($requestDate > date('Y-m-d'))
                    throw new Exception('Tanggal tidak boleh di masa depan');
                
                if ($reimburseType === 'bensin') {
                    $fourteenDaysAgo = date('Y-m-d', strtotime('-14 days'));
                    if ($requestDate < $fourteenDaysAgo)
                        throw new Exception('Klaim bensin maksimal 14 hari ke belakang');
                }

                if ($requestDateEnd) {
                    if ($requestDateEnd > date('Y-m-d'))
                        throw new Exception('Tanggal akhir tidak boleh di masa depan');
                    if ($requestDateEnd < $requestDate)
                        throw new Exception('Tanggal akhir harus setelah tanggal mulai');
                    if ($requestDateEnd === $requestDate)
                        $requestDateEnd = null; 
                    
                    if ($reimburseType === 'bensin') {
                        $fourteenDaysAgo = date('Y-m-d', strtotime('-14 days'));
                        if ($requestDateEnd < $fourteenDaysAgo)
                            throw new Exception('Tanggal akhir klaim bensin maksimal 14 hari ke belakang');
                    }
                }
                if ($amount <= 0)
                    throw new Exception('Nominal harus lebih dari 0');
                if ($amount > 1000000)
                    throw new Exception('Nominal maksimal Rp 1.000.000 per pengajuan');
                if (!$destination)
                    throw new Exception('Tujuan / keterangan wajib diisi');
                if (strlen($destination) > 255)
                    throw new Exception('Tujuan terlalu panjang (maks 255 karakter)');

                
                if ($reimburseType === 'bensin' && !in_array($currentRole, ['technician', 'hse'])) {
                    $datesToCheck = !empty($requestDatesArr) ? $requestDatesArr : [$requestDate];
                    foreach ($datesToCheck as $checkDate) {
                        $attStmt = $pdo->prepare("SELECT status FROM attendances WHERE user_id = ? AND attendance_date = ? LIMIT 1");
                        $attStmt->execute([$currentUserId, $checkDate]);
                        $attRecord = $attStmt->fetch(PDO::FETCH_ASSOC);
                        if (!$attRecord || !in_array(($attRecord['status'] ?? ''), ['Hadir', 'Lembur'], true)) {
                            $reason = !$attRecord ? 'Tidak ada data absensi' : ($attRecord['status'] === 'Terlambat' ? 'Anda terlambat' : 'Status absensi: ' . $attRecord['status']);
                            throw new Exception($reason . ' pada tanggal ' . date('d/m/Y', strtotime($checkDate)) . '. Reimbursement bensin hanya bisa diajukan untuk hari dimana Anda hadir tepat waktu.');
                        }
                    }
                }

                
                if ($reimburseType === 'bensin' && !in_array($currentRole, ['technician', 'hse'])) {
                    $dailyQuota = 15000;
                    $todayDt2 = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
                    $todayDay2 = (int) $todayDt2->format('j');
                    $ym2 = $todayDt2->format('Y-m');

                    if ($todayDay2 <= 15) {
                        $pStart = $ym2 . '-01';
                        $pEnd = $ym2 . '-15';
                    } else {
                        $pStart = $ym2 . '-16';
                        $pEnd = $todayDt2->format('Y-m-t');
                    }

                    $datesToValidate = !empty($requestDatesArr) ? $requestDatesArr : [$requestDate];
                    $fourteenDaysAgo = date('Y-m-d', strtotime('-14 days'));
                    foreach ($datesToValidate as $vDate) {
                        if ($vDate < $fourteenDaysAgo || $vDate > $todayDt2->format('Y-m-d')) {
                            throw new Exception('Tanggal ' . date('d/m/Y', strtotime($vDate)) . ' di luar batas 14 hari.');
                        }
                    }

                    
                    $attPeriod = $pdo->prepare("SELECT COUNT(*) FROM attendances WHERE user_id = ? AND attendance_date BETWEEN ? AND ? AND status IN ('Hadir','Lembur')");
                    $attPeriod->execute([$currentUserId, $pStart, $todayDt2->format('Y-m-d')]);
                    $nonLateDays2 = (int) $attPeriod->fetchColumn();

                    $pQuota = $nonLateDays2 * $dailyQuota;

                    
                    $adjP = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM fuel_quota_adjustments WHERE user_id = ? AND period_start = ? AND period_end = ?");
                    $adjP->execute([$currentUserId, $pStart, $pEnd]);
                    $pQuota += (float) $adjP->fetchColumn();

                    
                    $usedP = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM fuel_reimbursements WHERE user_id = ? AND reimburse_type = 'bensin' AND request_date BETWEEN ? AND ? AND status IN ('pending','approved')");
                    $usedP->execute([$currentUserId, $pStart, $pEnd]);
                    $pUsed = (float) $usedP->fetchColumn();

                    $pAvail = max(0, $pQuota - $pUsed);

                    if ($amount > $pAvail) {
                        $fmtQuota = number_format($pQuota, 0, ',', '.');
                        $fmtUsed = number_format($pUsed, 0, ',', '.');
                        $fmtAvail = number_format($pAvail, 0, ',', '.');
                        throw new Exception("Kuota bensin periode ini tidak mencukupi. Kuota: Rp $fmtQuota (berdasarkan $nonLateDays2 hari hadir tepat waktu × Rp 15.000), terpakai: Rp $fmtUsed, sisa: Rp $fmtAvail.");
                    }
                }

                
                $proofPath = null;
                if (!empty($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../../storage/uploads/reimbursement/' . $currentUserId . '/';
                    if (!is_dir($uploadDir))
                        mkdir($uploadDir, 0755, true);
                    $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
                    if (!in_array($ext, $allowed))
                        throw new Exception('Format file tidak didukung (jpg, png, gif, webp, pdf)');
                    if ($_FILES['proof']['size'] > 5 * 1024 * 1024)
                        throw new Exception('Ukuran file maksimal 5MB');
                    $fname = $reimburseType . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $destPath = $uploadDir . $fname;
                    move_uploaded_file($_FILES['proof']['tmp_name'], $destPath);

                    // Compress and resize image for faster loading
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) && function_exists('imagecreatefromjpeg')) {
                        $maxWidth = 1200;
                        $maxHeight = 1200;
                        $quality = 75;
                        $srcImage = null;
                        switch ($ext) {
                            case 'jpg': case 'jpeg': $srcImage = @imagecreatefromjpeg($destPath); break;
                            case 'png': $srcImage = @imagecreatefrompng($destPath); break;
                            case 'webp': $srcImage = @imagecreatefromwebp($destPath); break;
                        }
                        if ($srcImage) {
                            $origW = imagesx($srcImage);
                            $origH = imagesy($srcImage);
                            if ($origW > $maxWidth || $origH > $maxHeight) {
                                $ratio = min($maxWidth / $origW, $maxHeight / $origH);
                                $newW = (int) round($origW * $ratio);
                                $newH = (int) round($origH * $ratio);
                                $resized = imagecreatetruecolor($newW, $newH);
                                if ($ext === 'png') {
                                    imagealphablending($resized, false);
                                    imagesavealpha($resized, true);
                                }
                                imagecopyresampled($resized, $srcImage, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
                                imagedestroy($srcImage);
                                $srcImage = $resized;
                            }
                            switch ($ext) {
                                case 'jpg': case 'jpeg': imagejpeg($srcImage, $destPath, $quality); break;
                                case 'png': imagepng($srcImage, $destPath, 8); break;
                                case 'webp': imagewebp($srcImage, $destPath, $quality); break;
                            }
                            imagedestroy($srcImage);
                        }
                    }

                    $proofPath = 'storage/uploads/reimbursement/' . $currentUserId . '/' . $fname;
                } else {
                    throw new Exception('Bukti struk/bon/karcis wajib dilampirkan');
                }

                $requestDatesJson = !empty($requestDatesArr) ? json_encode($requestDatesArr) : null;
                $stmt = $pdo->prepare("INSERT INTO fuel_reimbursements (user_id, reimburse_type, request_date, request_date_end, request_dates, amount, distance_km, destination, description, proof_path, status) VALUES (?,?,?,?,?,?,?,?,?,?,'pending')");
                $stmt->execute([$currentUserId, $reimburseType, $requestDate, $requestDateEnd, $requestDatesJson, $amount, $distanceKm, $destination, $description, $proofPath]);

                
                $staffName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Staff';
                $adminStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'administrator' AND is_active = 1");
                $adminStmt->execute();
                $adminIds = array_column($adminStmt->fetchAll(PDO::FETCH_ASSOC), 'id');
                $typeLabel = match ($reimburseType) { 'karcis' => 'karcis', 'lainnya' => 'lainnya', default => 'bensin'};
                if ($adminIds) {
                    $amtFmt = number_format($amount, 0, ',', '.');
                    socketNotify($adminIds, 'reimbursement_request', "$staffName mengajukan reimbursement $typeLabel Rp $amtFmt ($destination)");
                }

                $response = ['success' => true, 'message' => 'Pengajuan reimbursement berhasil dikirim, menunggu persetujuan Admin'];
                auditLog($pdo, 'reimbursement_submit', [
                    'target_type' => 'reimbursement',
                    'target_id' => (int)$pdo->lastInsertId(),
                    'details' => ['type' => $reimburseType, 'amount' => $amount, 'destination' => $destination]
                ]);
                break;

            
            case 'approve_reimbursement':
                if (!$canApprove)
                    throw new Exception('Akses ditolak');
                $id = (int) ($_POST['reimburse_id'] ?? 0);
                if (!$id)
                    throw new Exception('ID tidak valid');

                $stmt = $pdo->prepare("UPDATE fuel_reimbursements SET status='approved', admin_decided_by=?, admin_decided_at=NOW(), payment_status='paid', payment_date=NOW(), payment_by=? WHERE id=? AND status='pending'");
                $stmt->execute([$currentUserId, $currentUserId, $id]);
                if ($stmt->rowCount() === 0)
                    throw new Exception('Request tidak ditemukan atau sudah diproses');

                
                $row = $pdo->prepare("SELECT user_id, amount, reimburse_type FROM fuel_reimbursements WHERE id=?");
                $row->execute([$id]);
                $info = $row->fetch(PDO::FETCH_ASSOC);
                if ($info) {
                    $amtFmt = number_format($info['amount'], 0, ',', '.');
                    $tLabel = match ($info['reimburse_type'] ?? 'bensin') { 'karcis' => 'karcis', 'lainnya' => 'lainnya', default => 'bensin'};
                    socketNotify([(int) $info['user_id']], 'reimbursement_update', "Reimbursement $tLabel Rp $amtFmt Anda telah DISETUJUI oleh Admin");
                }

                $response = ['success' => true, 'message' => 'Reimbursement disetujui'];
                auditLog($pdo, 'reimbursement_approve', [
                    'target_type' => 'reimbursement',
                    'target_id' => $id,
                    'target_user_id' => $info['user_id'] ?? null,
                    'details' => ['amount' => $info['amount'] ?? 0, 'type' => $info['reimburse_type'] ?? '']
                ]);
                break;

            
            case 'reject_reimbursement':
                if (!$canApprove)
                    throw new Exception('Akses ditolak');
                $id = (int) ($_POST['reimburse_id'] ?? 0);
                $rejectReason = trim($_POST['reject_reason'] ?? '');
                if (!$id)
                    throw new Exception('ID tidak valid');

                $stmt = $pdo->prepare("UPDATE fuel_reimbursements SET status='rejected', admin_decided_by=?, admin_decided_at=NOW(), reject_reason=? WHERE id=? AND status='pending'");
                $stmt->execute([$currentUserId, $rejectReason, $id]);
                if ($stmt->rowCount() === 0)
                    throw new Exception('Request tidak ditemukan atau sudah diproses');

                
                $row = $pdo->prepare("SELECT user_id, reimburse_type FROM fuel_reimbursements WHERE id=?");
                $row->execute([$id]);
                $rejInfo = $row->fetch(PDO::FETCH_ASSOC);
                if ($rejInfo) {
                    $tLabel2 = match ($rejInfo['reimburse_type'] ?? 'bensin') { 'karcis' => 'karcis', 'lainnya' => 'lainnya', default => 'bensin'};
                    socketNotify([(int) $rejInfo['user_id']], 'reimbursement_update', "Reimbursement $tLabel2 Anda ditolak oleh Admin" . ($rejectReason ? ": $rejectReason" : ''));
                }

                $response = ['success' => true, 'message' => 'Reimbursement ditolak'];
                auditLog($pdo, 'reimbursement_reject', [
                    'target_type' => 'reimbursement',
                    'target_id' => $id,
                    'target_user_id' => $rejInfo['user_id'] ?? null,
                    'details' => ['type' => $rejInfo['reimburse_type'] ?? '', 'reason' => $rejectReason]
                ]);
                break;

            
            case 'edit_reimbursement':
                if (!$canApprove)
                    throw new Exception('Akses ditolak');
                $id = (int) ($_POST['reimburse_id'] ?? 0);
                if (!$id)
                    throw new Exception('ID tidak valid');

                $editType = trim($_POST['reimburse_type'] ?? '');
                $editAmount = (float) ($_POST['amount'] ?? 0);
                $editDestination = trim($_POST['destination'] ?? '');
                $editDescription = trim($_POST['description'] ?? '');
                $editDate = trim($_POST['request_date'] ?? '');
                $editDateEnd = trim($_POST['request_date_end'] ?? '') ?: null;
                $editDistance = !empty($_POST['distance_km']) ? (float) $_POST['distance_km'] : null;

                if ($editType && !in_array($editType, ['bensin', 'karcis', 'lainnya']))
                    throw new Exception('Jenis tidak valid');
                if ($editAmount <= 0)
                    throw new Exception('Nominal harus lebih dari 0');
                if ($editAmount > 1000000)
                    throw new Exception('Nominal maksimal Rp 1.000.000');
                if (!$editDestination)
                    throw new Exception('Keterangan wajib diisi');
                if (strlen($editDestination) > 255)
                    throw new Exception('Keterangan terlalu panjang');
                if (!$editDate)
                    throw new Exception('Tanggal wajib diisi');

                if ($editDateEnd === $editDate)
                    $editDateEnd = null;

                
                $editDatesArr = [];
                if ($editDate) {
                    $endDt = $editDateEnd ?: $editDate;
                    $curr = $editDate;
                    while ($curr <= $endDt) {
                        $editDatesArr[] = $curr;
                        $curr = date('Y-m-d', strtotime($curr . ' +1 day'));
                    }
                }
                $editDatesJson = !empty($editDatesArr) ? json_encode($editDatesArr) : null;

                
                $newProofPath = null;
                if (!empty($_FILES['edit_proof']) && $_FILES['edit_proof']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../../storage/uploads/reimbursement/' . $id . '_admin/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $ext = strtolower(pathinfo($_FILES['edit_proof']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
                    if (!in_array($ext, $allowed)) throw new Exception('Format file tidak didukung (jpg, png, gif, webp, pdf)');
                    if ($_FILES['edit_proof']['size'] > 5 * 1024 * 1024) throw new Exception('Ukuran file maksimal 5MB');
                    $fname = 'admin-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                    move_uploaded_file($_FILES['edit_proof']['tmp_name'], $uploadDir . $fname);

                    // Compress image for faster loading
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                        require_once __DIR__ . '/../helpers/image-compress.php';
                        compressUploadedImage($uploadDir . $fname, 1200, 1200, 75);
                    }

                    $newProofPath = 'storage/uploads/reimbursement/' . $id . '_admin/' . $fname;
                }

                if ($newProofPath) {
                    $stmt = $pdo->prepare("UPDATE fuel_reimbursements SET reimburse_type=?, amount=?, destination=?, description=?, request_date=?, request_date_end=?, request_dates=?, distance_km=?, proof_path=?, updated_at=NOW() WHERE id=? AND status IN ('pending','approved')");
                    $stmt->execute([$editType ?: 'bensin', $editAmount, $editDestination, $editDescription, $editDate, $editDateEnd, $editDatesJson, $editDistance, $newProofPath, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE fuel_reimbursements SET reimburse_type=?, amount=?, destination=?, description=?, request_date=?, request_date_end=?, request_dates=?, distance_km=?, updated_at=NOW() WHERE id=? AND status IN ('pending','approved')");
                    $stmt->execute([$editType ?: 'bensin', $editAmount, $editDestination, $editDescription, $editDate, $editDateEnd, $editDatesJson, $editDistance, $id]);
                }
                if ($stmt->rowCount() === 0)
                    throw new Exception('Request tidak ditemukan atau sudah ditolak');

                $response = ['success' => true, 'message' => 'Reimbursement berhasil diupdate'];
                break;

            
            case 'delete_reimbursement':
                if (!$canApprove)
                    throw new Exception('Akses ditolak');
                $id = (int) ($_POST['reimburse_id'] ?? 0);
                if (!$id)
                    throw new Exception('ID tidak valid');

                $stmt = $pdo->prepare("SELECT id, status, user_id FROM fuel_reimbursements WHERE id = ?");
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row)
                    throw new Exception('Data reimbursement tidak ditemukan');

                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("DELETE FROM fuel_reimbursements WHERE id = ?");
                    $stmt->execute([$id]);
                    $pdo->commit();

                    auditLog($pdo, 'delete_reimbursement', [
                        'target_type' => 'fuel_reimbursement',
                        'target_id' => $id,
                        'target_user_id' => $row['user_id'],
                        'details' => ['status' => $row['status'], 'deleted_by' => $currentUserId]
                    ]);

                    $response = ['success' => true, 'message' => 'Reimbursement berhasil dihapus'];
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw new Exception('Gagal menghapus: ' . $e->getMessage());
                }
                break;

            
            case 'adjust_quota':
                if (!$canApprove)
                    throw new Exception('Akses ditolak');
                $targetUserId = (int) ($_POST['target_user_id'] ?? 0);
                $adjustAmount = (float) ($_POST['adjust_amount'] ?? 0);
                $adjustReason = trim($_POST['adjust_reason'] ?? '');

                if (!$targetUserId)
                    throw new Exception('User tidak valid');
                if ($adjustAmount == 0)
                    throw new Exception('Nominal tidak boleh 0');
                if (!$adjustReason)
                    throw new Exception('Alasan wajib diisi');

                
                $adjDt = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
                $adjDay = (int) $adjDt->format('j');
                $adjYm = $adjDt->format('Y-m');
                if ($adjDay <= 15) {
                    $adjPStart = $adjYm . '-01';
                    $adjPEnd = $adjYm . '-15';
                } else {
                    $adjPStart = $adjYm . '-16';
                    $adjPEnd = $adjDt->format('Y-m-t');
                }

                $stmt = $pdo->prepare("INSERT INTO fuel_quota_adjustments (user_id, period_start, period_end, amount, reason, created_by) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$targetUserId, $adjPStart, $adjPEnd, $adjustAmount, $adjustReason, $currentUserId]);

                $uStmt = $pdo->prepare("SELECT full_name FROM users WHERE id=?");
                $uStmt->execute([$targetUserId]);
                $uName = $uStmt->fetchColumn() ?: 'User';
                $fmtAdj = number_format(abs($adjustAmount), 0, ',', '.');
                $response = ['success' => true, 'message' => 'Kuota ' . $uName . ' berhasil ' . ($adjustAmount > 0 ? 'ditambah' : 'dikurangi') . ' Rp ' . $fmtAdj];
                break;

            case 'update_payment_status':
                if (!$canApprove)
                    throw new Exception('Akses ditolak');
                $id = (int) ($_POST['reimburse_id'] ?? 0);
                $paymentStatus = trim($_POST['payment_status'] ?? '');
                if (!$id)
                    throw new Exception('ID tidak valid');
                if (!in_array($paymentStatus, ['unpaid', 'paid']))
                    throw new Exception('Status pembayaran tidak valid');

                $checkStmt = $pdo->prepare("SELECT status FROM fuel_reimbursements WHERE id = ?");
                $checkStmt->execute([$id]);
                $currentStatus = $checkStmt->fetchColumn();
                
                if ($currentStatus !== 'approved')
                    throw new Exception('Hanya reimbursement yang sudah di-approve yang bisa diupdate status pembayarannya');

                if ($paymentStatus === 'paid') {
                    $stmt = $pdo->prepare("UPDATE fuel_reimbursements SET payment_status='paid', payment_date=NOW(), payment_by=? WHERE id=?");
                    $stmt->execute([$currentUserId, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE fuel_reimbursements SET payment_status='unpaid', payment_date=NULL, payment_by=NULL WHERE id=?");
                    $stmt->execute([$id]);
                }

                $response = ['success' => true, 'message' => 'Status pembayaran berhasil diupdate'];
                auditLog($pdo, 'reimbursement_payment_update', [
                    'target_type' => 'reimbursement',
                    'target_id' => $id,
                    'details' => ['payment_status' => $paymentStatus]
                ]);
                break;
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    echo json_encode($response);
    exit;
}




$myRequests = [];
if ($canSubmit) {
    $stmtMy = $pdo->prepare("
        SELECT fr.*, u.full_name as user_name, a.full_name as admin_name, p.full_name as payment_by_name
        FROM fuel_reimbursements fr
        JOIN users u ON fr.user_id = u.id
        LEFT JOIN users a ON fr.admin_decided_by = a.id
        LEFT JOIN users p ON fr.payment_by = p.id
        WHERE fr.user_id = ?
        ORDER BY fr.created_at DESC
        LIMIT 50
    ");
    $stmtMy->execute([$currentUserId]);
    $myRequests = $stmtMy->fetchAll(PDO::FETCH_ASSOC);
}


$myMonthlyTotal = 0;
if ($canSubmit) {
    $stmtMonth = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM fuel_reimbursements WHERE user_id=? AND MONTH(request_date)=? AND YEAR(request_date)=? AND status IN ('pending','approved')");
    $stmtMonth->execute([$currentUserId, $currentMonth, $currentYear]);
    $myMonthlyTotal = (float) $stmtMonth->fetchColumn();
}


$adminStats = ['total_approved' => 0, 'total_pending' => 0, 'total_rejected' => 0, 'count_pending' => 0, 'count_approved' => 0, 'count_all' => 0];
if ($canViewAll) {
    $stmtStats = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN status='approved' THEN amount ELSE 0 END),0) as total_approved,
            COALESCE(SUM(CASE WHEN status='pending' THEN amount ELSE 0 END),0) as total_pending,
            COALESCE(SUM(CASE WHEN status='rejected' THEN amount ELSE 0 END),0) as total_rejected,
            COUNT(CASE WHEN status='pending' THEN 1 END) as count_pending,
            COUNT(CASE WHEN status='approved' THEN 1 END) as count_approved,
            COUNT(*) as count_all
        FROM fuel_reimbursements 
        WHERE MONTH(request_date)=? AND YEAR(request_date)=?
    ");
    $stmtStats->execute([$currentMonth, $currentYear]);
    $adminStats = $stmtStats->fetch(PDO::FETCH_ASSOC) ?: $adminStats;
}


$pendingRequests = [];
if ($canApprove) {
    $stmtPending = $pdo->query("
        SELECT fr.*, u.full_name as user_name, u.role as user_role
        FROM fuel_reimbursements fr
        JOIN users u ON fr.user_id = u.id
        WHERE fr.status = 'pending'
        ORDER BY fr.created_at ASC
    ");
    $pendingRequests = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

    
    foreach ($pendingRequests as &$pr) {
        $prUserId = $pr['user_id'];
        $prDate = $pr['request_date'];
        $prRole = $pr['user_role'] ?? '';

        
        if (in_array($prRole, ['technician', 'hse'])) {
            $pr['att_status'] = '__technician__';
            continue;
        }

        
        $attCheck = $pdo->prepare("SELECT status FROM attendances WHERE user_id = ? AND attendance_date = ? LIMIT 1");
        $attCheck->execute([$prUserId, $prDate]);
        $attRow = $attCheck->fetch(PDO::FETCH_ASSOC);
        $pr['att_status'] = $attRow ? $attRow['status'] : null; 

        
        if (($pr['reimburse_type'] ?? 'bensin') === 'bensin') {
            $prDt = new DateTime($prDate, new DateTimeZone('Asia/Jakarta'));
            $prDay = (int) $prDt->format('j');
            $prYm = $prDt->format('Y-m');
            if ($prDay <= 15) {
                $prPeriodStart = $prYm . '-01';
                $prPeriodEnd = $prYm . '-15';
            } else {
                $prPeriodStart = $prYm . '-16';
                $prPeriodEnd = $prDt->format('Y-m-t');
            }

            
            $prAtt = $pdo->prepare("SELECT COUNT(*) FROM attendances WHERE user_id = ? AND attendance_date BETWEEN ? AND ? AND status IN ('Hadir','Lembur')");
            $prAtt->execute([$prUserId, $prPeriodStart, $prDate]);
            $prNonLate = (int) $prAtt->fetchColumn();

            $prQuota = $prNonLate * 15000;

            
            $prAdj = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM fuel_quota_adjustments WHERE user_id = ? AND period_start = ? AND period_end = ?");
            $prAdj->execute([$prUserId, $prPeriodStart, $prPeriodEnd]);
            $prQuota += (float) $prAdj->fetchColumn();

            
            $prUsed = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM fuel_reimbursements WHERE user_id = ? AND reimburse_type = 'bensin' AND request_date BETWEEN ? AND ? AND status IN ('pending','approved') AND id != ?");
            $prUsed->execute([$prUserId, $prPeriodStart, $prPeriodEnd, $pr['id']]);
            $prUsedAmt = (float) $prUsed->fetchColumn();

            $pr['period_start'] = $prPeriodStart;
            $pr['period_end'] = $prPeriodEnd;
            $pr['period_non_late_days'] = $prNonLate;
            $pr['period_quota'] = $prQuota;
            $pr['period_used'] = $prUsedAmt;
            $pr['period_available'] = max(0, $prQuota - $prUsedAmt);
        }
    }
    unset($pr);
}


$allRequests = [];
$recapData = [];
if ($canViewAll) {
    $stmtAll = $pdo->prepare("
        SELECT fr.*, u.full_name as user_name, u.role as user_role, a.full_name as admin_name, p.full_name as payment_by_name
        FROM fuel_reimbursements fr
        JOIN users u ON fr.user_id = u.id
        LEFT JOIN users a ON fr.admin_decided_by = a.id
        LEFT JOIN users p ON fr.payment_by = p.id
        WHERE MONTH(fr.request_date) = ? AND YEAR(fr.request_date) = ?
        ORDER BY fr.created_at DESC
        LIMIT 200
    ");
    $stmtAll->execute([$currentMonth, $currentYear]);
    $allRequests = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

    
    $stmtRecap = $pdo->prepare("
        SELECT u.id, u.full_name, u.username, u.role,
               COALESCE(SUM(CASE WHEN fr.status = 'approved' THEN fr.amount ELSE 0 END), 0) as approved_amount,
               COALESCE(SUM(CASE WHEN fr.status = 'pending' THEN fr.amount ELSE 0 END), 0) as pending_amount,
               COALESCE(SUM(CASE WHEN fr.status = 'rejected' THEN fr.amount ELSE 0 END), 0) as rejected_amount,
               COUNT(CASE WHEN fr.status = 'approved' THEN 1 END) as approved_count,
               COUNT(CASE WHEN fr.status = 'pending' THEN 1 END) as pending_count
        FROM users u
        LEFT JOIN fuel_reimbursements fr ON fr.user_id = u.id AND MONTH(fr.request_date) = ? AND YEAR(fr.request_date) = ?
        WHERE u.is_active = 1 AND u.role IN ('technician', 'hse', 'daily', 'internship', 'sales', 'driver')
        GROUP BY u.id, u.full_name, u.username, u.role
        HAVING (approved_amount + pending_amount + rejected_amount) > 0
        ORDER BY u.full_name ASC
    ");
    $stmtRecap->execute([$currentMonth, $currentYear]);
    $recapData = $stmtRecap->fetchAll(PDO::FETCH_ASSOC);
}


$quotaOverview = [];
if ($canViewAll) {
    $todayDtQ = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    $todayDayQ = (int) $todayDtQ->format('j');
    $todayStrQ = $todayDtQ->format('Y-m-d');
    $ymQ = $todayDtQ->format('Y-m');

    if ($todayDayQ <= 15) {
        $qPeriodStart = $ymQ . '-01';
        $qPeriodEnd = $ymQ . '-15';
        $qPeriodLabel = 'Tgl 1-15 ' . $todayDtQ->format('M Y');
    } else {
        $qPeriodStart = $ymQ . '-16';
        $qPeriodEnd = $todayDtQ->format('Y-m-t');
        $qPeriodLabel = 'Tgl 16-' . $todayDtQ->format('t M Y');
    }

    
    
    $stmtUsers = $pdo->query("SELECT id, full_name, username, role FROM users WHERE is_active = 1 AND role IN ('daily', 'internship', 'sales', 'driver') ORDER BY full_name ASC");
    $allUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allUsers as $u) {
        $uid = $u['id'];

        
        $attAll = $pdo->prepare("SELECT attendance_date, status FROM attendances WHERE user_id = ? AND attendance_date BETWEEN ? AND ? ORDER BY attendance_date ASC");
        $attAll->execute([$uid, $qPeriodStart, $todayStrQ]);
        $attRows = $attAll->fetchAll(PDO::FETCH_ASSOC);

        $nonLate = 0;
        $totalAtt = 0;
        $lateCount = 0;
        $attDetail = [];
        foreach ($attRows as $a) {
            $totalAtt++;
            $isHadir = in_array(($a['status'] ?? ''), ['Hadir', 'Lembur'], true);
            if ($a['status'] === 'Terlambat')
                $lateCount++;
            if ($isHadir)
                $nonLate++;
            $attDetail[] = ['date' => $a['attendance_date'], 'status' => $a['status'], 'late' => !$isHadir];
        }

        $quota = $nonLate * 15000;

        
        $adjQ = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM fuel_quota_adjustments WHERE user_id = ? AND period_start = ? AND period_end = ?");
        $adjQ->execute([$uid, $qPeriodStart, $qPeriodEnd]);
        $manualAdj = (float) $adjQ->fetchColumn();
        $quota += $manualAdj;

        
        $usedQ = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM fuel_reimbursements WHERE user_id = ? AND reimburse_type = 'bensin' AND request_date BETWEEN ? AND ? AND status IN ('pending','approved')");
        $usedQ->execute([$uid, $qPeriodStart, $qPeriodEnd]);
        $used = (float) $usedQ->fetchColumn();

        $available = max(0, $quota - $used);

        $quotaOverview[] = [
            'user_id' => $uid,
            'full_name' => $u['full_name'] ?: $u['username'],
            'role' => $u['role'],
            'total_attendance' => $totalAtt,
            'non_late_days' => $nonLate,
            'late_days' => $lateCount,
            'quota' => $quota,
            'used' => $used,
            'available' => $available,
            'att_detail' => $attDetail,
            'manual_adj' => $manualAdj,
        ];
    }
}

$monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];


$activeTab = $_GET['tab'] ?? 'reimbursement';
if (!in_array($activeTab, ['reimbursement', 'kuota']))
    $activeTab = 'reimbursement';

if (!$canViewAll)
    $activeTab = 'reimbursement';
?>

<div class="space-y-6">
    
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Reimbursement</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Pengajuan reimburse uang bensin, karcis & lainnya
            </p>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($canSubmit && $activeTab === 'reimbursement'): ?>
                <button onclick="openReimburseModal()"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 transition font-medium text-sm shadow">
                    <i class="fas fa-plus-circle"></i> Ajukan Reimbursement
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($canViewAll): ?>
        
        <div class="flex gap-1 bg-slate-100 dark:bg-slate-700/50 rounded-xl p-1">
            <a href="?page=fuel-reimbursement&tab=reimbursement"
                class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition <?= $activeTab === 'reimbursement' ? 'bg-white dark:bg-slate-800 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' ?>">
                <i class="fas fa-gas-pump"></i> Reimbursement
                <?php if ((int) $adminStats['count_pending'] > 0): ?>
                    <span
                        class="px-2 py-0.5 rounded-full text-xs font-bold bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400"><?= (int) $adminStats['count_pending'] ?></span>
                <?php endif; ?>
            </a>
            <a href="?page=fuel-reimbursement&tab=kuota"
                class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition <?= $activeTab === 'kuota' ? 'bg-white dark:bg-slate-800 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' ?>">
                <i class="fas fa-bolt"></i> Kuota Karyawan
            </a>
        </div>
    <?php endif; ?>

    <?php if ($activeTab === 'reimbursement'): ?>

        <?php if ($canSubmit): ?>
            <div class="grid grid-cols-1 <?= $hasQuota ? 'sm:grid-cols-3' : 'sm:grid-cols-2' ?> gap-4">
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center">
                            <i class="fas fa-gas-pump text-emerald-600 dark:text-emerald-400"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Bulan Ini</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white">Rp
                                <?= number_format($myMonthlyTotal, 0, ',', '.') ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center">
                            <i class="fas fa-file-invoice text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Pengajuan</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white"><?= count($myRequests) ?> <span
                                    class="text-sm font-normal text-gray-500">pengajuan</span></p>
                        </div>
                    </div>
                </div>
                
                <?php if ($hasQuota): ?>
                    <div
                        class="bg-white dark:bg-slate-800 rounded-xl border <?= $periodAvailable > 0 ? 'border-slate-200 dark:border-slate-700' : 'border-red-300 dark:border-red-700' ?> p-5">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-10 h-10 rounded-lg <?= $periodAvailable > 0 ? 'bg-amber-100 dark:bg-amber-900/40' : 'bg-red-100 dark:bg-red-900/40' ?> flex items-center justify-center">
                                <i
                                    class="fas fa-bolt <?= $periodAvailable > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-red-500' ?>"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kuota Bensin Periode
                                    Ini</p>
                                <p
                                    class="text-xl font-bold <?= $periodAvailable > 0 ? 'text-gray-900 dark:text-white' : 'text-red-600 dark:text-red-400' ?>">
                                    Rp <?= number_format($periodAvailable, 0, ',', '.') ?></p>
                                <p class="text-[10px] text-gray-400 mt-0.5">dari Rp <?= number_format($periodQuota, 0, ',', '.') ?>
                                    (<?= count(array_filter($quotaDaysDetail, fn($d) => in_array(($d['status'] ?? ''), ['Hadir', 'Lembur'], true))) ?>
                                    hari ×
                                    15rb)<?= $periodUsed > 0 ? ' · terpakai Rp ' . number_format($periodUsed, 0, ',', '.') : '' ?>
                                </p>
                                <p class="text-[10px] text-gray-400"><?= $periodLabel ?> · Reset tgl
                                    <?= (int) (new DateTime($todayStr))->format('j') <= 15 ? '16' : '1' ?>
                                </p>
                            </div>
                        </div>
                        <?php if (!empty($quotaDaysDetail)): ?>
                            <div class="mt-3 flex flex-wrap gap-1">
                                <?php
                                $dayNames = ['', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
                                foreach ($quotaDaysDetail as $qd):
                                    $dDate = new DateTime($qd['date']);
                                    $dayNum = (int) $dDate->format('N');
                                    $dayLabel = $dayNames[$dayNum] ?? '';
                                    $qdSt = $qd['status'] ?? '';
                                    if (in_array($qdSt, ['Hadir', 'Lembur'], true)) {
                                        $qdC = 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-400';
                                        $qdI = '✓';
                                    } elseif ($qdSt === 'Terlambat') {
                                        $qdC = 'bg-red-100 text-red-500 dark:bg-red-900/20 dark:text-red-400';
                                        $qdI = '✗';
                                    } else {
                                        $qdC = 'bg-orange-100 text-orange-600 dark:bg-orange-900/20 dark:text-orange-400';
                                        $qdI = '—';
                                    }
                                    ?>
                                    <span class="text-[9px] px-1.5 py-0.5 rounded <?= $qdC ?> font-medium">
                                        <?= $dayLabel ?>                     <?= $dDate->format('d') ?>                     <?= $qdI ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif;   ?>
            </div>
        <?php endif; ?>

        <?php if ($canViewAll): ?>
            
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center">
                            <i class="fas fa-clock text-amber-600 dark:text-amber-400 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase tracking-wider">Menunggu ACC</p>
                            <p class="text-lg font-bold text-amber-600 dark:text-amber-400">
                                <?= (int) $adminStats['count_pending'] ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-green-100 dark:bg-green-900/40 flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase tracking-wider">Disetujui</p>
                            <p class="text-lg font-bold text-green-600 dark:text-green-400">
                                <?= (int) $adminStats['count_approved'] ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center">
                            <i class="fas fa-money-bill-wave text-emerald-600 dark:text-emerald-400 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Approved</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">Rp
                                <?= number_format($adminStats['total_approved'], 0, ',', '.') ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center">
                            <i class="fas fa-file-invoice-dollar text-blue-600 dark:text-blue-400 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pending</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">Rp
                                <?= number_format($adminStats['total_pending'], 0, ',', '.') ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($canViewAll && !empty($recapData)): ?>
            
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="p-5 border-b border-slate-200 dark:border-slate-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center">
                                <i class="fas fa-chart-bar text-indigo-500"></i>
                            </div>
                            <div>
                                <h2 class="font-semibold text-gray-900 dark:text-white">Rekap Reimbursement
                                    <?= $monthNames[$currentMonth] ?>         <?= $currentYear ?>
                                </h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Ringkasan per karyawan bulan ini</p>
                            </div>
                        </div>
                        <button type="button"
                            onclick="document.getElementById('reimbRecapTable').classList.toggle('hidden'); this.querySelector('i').classList.toggle('fa-chevron-down'); this.querySelector('i').classList.toggle('fa-chevron-up')"
                            class="px-3 py-1.5 text-sm text-gray-500 hover:text-indigo-600 transition">
                            <i class="fas fa-chevron-up"></i>
                        </button>
                    </div>
                </div>
                <div id="reimbRecapTable">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-slate-700/50 text-left">
                                    <th class="px-5 py-3 font-semibold text-gray-600 dark:text-gray-300">Karyawan</th>
                                    <th class="px-4 py-3 font-semibold text-gray-600 dark:text-gray-300 text-center">Role</th>
                                    <th class="px-4 py-3 font-semibold text-green-600 dark:text-green-400 text-right">Disetujui
                                    </th>
                                    <th class="px-4 py-3 font-semibold text-amber-600 dark:text-amber-400 text-right">Pending
                                    </th>
                                    <th class="px-4 py-3 font-semibold text-gray-600 dark:text-gray-300 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <?php
                                $roleLabels = [
                                    'technician' => 'Teknisi',
                                    'hse' => 'HSE',
                                    'daily' => 'Harian',
                                    'internship' => 'Magang'
                                ];
                                $grandApproved = 0;
                                $grandPending = 0;
                                foreach ($recapData as $emp):
                                    $grandApproved += $emp['approved_amount'];
                                    $grandPending += $emp['pending_amount'];
                                    ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                        <td class="px-5 py-3">
                                            <div class="font-medium text-gray-900 dark:text-white">
                                                <?= htmlspecialchars($emp['full_name'] ?: $emp['username']) ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span
                                                class="text-xs px-2 py-0.5 rounded-full <?= match ($emp['role']) { 'technician' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', 'hse' => 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400', 'daily' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400', 'internship' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400', default => 'bg-gray-100 text-gray-700'} ?>"><?= $roleLabels[$emp['role']] ?? ucfirst($emp['role']) ?></span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <span
                                                class="<?= $emp['approved_amount'] > 0 ? 'font-semibold text-green-600 dark:text-green-400' : 'text-gray-400' ?>">Rp
                                                <?= number_format($emp['approved_amount'], 0, ',', '.') ?></span>
                                            <?php if ($emp['approved_count'] > 0): ?>
                                                <span class="text-xs text-gray-400 ml-1">(<?= $emp['approved_count'] ?>x)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <span
                                                class="<?= $emp['pending_amount'] > 0 ? 'font-semibold text-amber-600 dark:text-amber-400' : 'text-gray-400' ?>">Rp
                                                <?= number_format($emp['pending_amount'], 0, ',', '.') ?></span>
                                            <?php if ($emp['pending_count'] > 0): ?>
                                                <span class="text-xs text-gray-400 ml-1">(<?= $emp['pending_count'] ?>x)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">
                                            Rp <?= number_format($emp['approved_amount'] + $emp['pending_amount'], 0, ',', '.') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="bg-slate-50 dark:bg-slate-700/50 font-bold">
                                    <td class="px-5 py-3 text-gray-900 dark:text-white" colspan="2">Total</td>
                                    <td class="px-4 py-3 text-right text-green-600 dark:text-green-400">Rp
                                        <?= number_format($grandApproved, 0, ',', '.') ?>
                                    </td>
                                    <td class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">Rp
                                        <?= number_format($grandPending, 0, ',', '.') ?>
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-900 dark:text-white">Rp
                                        <?= number_format($grandApproved + $grandPending, 0, ',', '.') ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($pendingRequests) && $canApprove): ?>
            
            <?php
            
            $pendingByUser = [];
            foreach ($pendingRequests as $r) {
                $uid = $r['user_id'];
                $pendingByUser[$uid]['user_name'] = $r['user_name'];
                $pendingByUser[$uid]['user_role'] = $r['user_role'];
                $pendingByUser[$uid]['items'][] = $r;
            }
            ?>
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="p-5 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-orange-100 dark:bg-orange-900/40 flex items-center justify-center">
                            <i class="fas fa-bell text-orange-500"></i>
                        </div>
                        <h2 class="font-semibold text-gray-900 dark:text-white">Menunggu Persetujuan</h2>
                        <span
                            class="px-2 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400"><?= count($pendingRequests) ?></span>
                    </div>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                    <?php
                    $roleLabelsApproval = ['technician' => 'Teknisi', 'hse' => 'HSE', 'daily' => 'Harian', 'internship' => 'Magang'];
                    foreach ($pendingByUser as $uid => $userData):
                        $userItems = $userData['items'];
                        $userTotal = array_sum(array_column($userItems, 'amount'));
                        $userCount = count($userItems);
                    ?>
                    
                    <div class="reimb-pending-group">
                        <button type="button" onclick="this.nextElementSibling.classList.toggle('hidden'); this.querySelector('.reimb-chevron').classList.toggle('rotate-180')"
                            class="w-full flex items-center justify-between p-4 hover:bg-orange-50/50 dark:hover:bg-slate-700/50 transition text-left">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-orange-100 dark:bg-orange-900/40 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-user text-orange-500 text-sm"></i>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900 dark:text-white text-sm flex items-center gap-2">
                                        <?= htmlspecialchars($userData['user_name']) ?>
                                        <span class="text-xs px-2 py-0.5 rounded-full <?= match ($userData['user_role']) { 'technician' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', 'hse' => 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400', 'daily' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400', 'internship' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400', default => 'bg-gray-100 text-gray-700'} ?>"><?= $roleLabelsApproval[$userData['user_role']] ?? ucfirst($userData['user_role']) ?></span>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400"><?= $userCount ?> pengajuan &middot; Total <span class="font-semibold text-emerald-600">Rp <?= number_format($userTotal, 0, ',', '.') ?></span></div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="flex -space-x-2">
                                    <?php $thumbCount = 0; foreach ($userItems as $thumbR): if ($thumbCount >= 3) break; if (!empty($thumbR['proof_path']) && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $thumbR['proof_path'])): ?>
                                        <img src="./<?= htmlspecialchars(ltrim($thumbR['proof_path'], '/')) ?>" loading="lazy" class="w-7 h-7 rounded-md object-cover border-2 border-white dark:border-slate-800" onerror="this.style.display='none'">
                                    <?php $thumbCount++; endif; endforeach; ?>
                                </div>
                                <i class="fas fa-chevron-down reimb-chevron text-gray-400 text-xs transition-transform rotate-180"></i>
                            </div>
                        </button>
                        <div class="border-t border-slate-100 dark:border-slate-700">
                    <?php foreach ($userItems as $r): ?>
                        <div class="p-4 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition" id="reimb-row-<?= $r['id'] ?>">
                            <div class="flex items-start gap-3">
                                
                                <div class="flex-shrink-0">
                                    <?php if (!empty($r['proof_path'])): ?>
                                        <a href="./<?= htmlspecialchars(ltrim($r['proof_path'], '/')) ?>" target="_blank" class="block w-14 h-14 rounded-lg overflow-hidden border border-slate-200 dark:border-slate-600 hover:ring-2 hover:ring-indigo-400 transition shadow-sm">
                                            <?php if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $r['proof_path'])): ?>
                                                <img src="./<?= htmlspecialchars(ltrim($r['proof_path'], '/')) ?>" loading="lazy" class="w-full h-full object-cover" alt="Bukti">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center bg-slate-100 dark:bg-slate-700"><i class="fas fa-file-pdf text-red-400"></i></div>
                                            <?php endif; ?>
                                        </a>
                                    <?php else: ?>
                                        <div class="w-14 h-14 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center border border-slate-200 dark:border-slate-600"><i class="fas fa-receipt text-slate-300"></i></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                                        <?php $rType = $r['reimburse_type'] ?? 'bensin'; ?>
                                        <span class="text-xs px-2 py-0.5 rounded-full <?= match ($rType) { 'karcis' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400', 'lainnya' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400', default => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'} ?>">
                                            <i class="fas <?= match ($rType) { 'karcis' => 'fa-ticket-alt', 'lainnya' => 'fa-receipt', default => 'fa-gas-pump'} ?> mr-0.5"></i><?= match ($rType) { 'karcis' => 'Karcis', 'lainnya' => 'Lainnya', default => 'Bensin'} ?>
                                        </span>
                                        <span class="font-bold text-emerald-600 dark:text-emerald-400 text-sm">Rp <?= number_format($r['amount'], 0, ',', '.') ?></span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">Pending</span>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-300">
                                        <i class="fas fa-calendar-day mr-1 text-gray-400 text-xs"></i><?= date('d M Y', strtotime($r['request_date'])) ?><?php if (!empty($r['request_date_end']) && $r['request_date_end'] !== $r['request_date']): ?> — <?= date('d M Y', strtotime($r['request_date_end'])) ?><?php endif; ?>
                                        <span class="text-gray-400 mx-1">&middot;</span>
                                        <i class="fas fa-map-marker-alt mr-0.5 text-red-400 text-xs"></i><?= htmlspecialchars($r['destination']) ?>
                                        <?php if ($r['distance_km']): ?><span class="text-xs text-gray-400">(<?= rtrim(rtrim(number_format($r['distance_km'], 5), '0'), '.') ?> km)</span><?php endif; ?>
                                    </p>
                                    <?php if (!empty($r['description'])): ?>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"><i class="fas fa-comment-alt mr-1"></i><?= htmlspecialchars($r['description']) ?></p>
                                    <?php endif; ?>

                                    <?php   ?>
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        <?php if (($r['reimburse_type'] ?? 'bensin') !== 'lainnya' && ($r['att_status'] ?? '') !== '__technician__'):
                                            
                                            $attStatus = $r['att_status'] ?? null;
                                            if ($attStatus === null): ?>
                                                <span
                                                    class="inline-flex items-center gap-1 text-[11px] px-2 py-1 rounded-lg bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400 border border-gray-200 dark:border-gray-600">
                                                    <i class="fas fa-question-circle"></i> Absensi
                                                    <?= date('d/m', strtotime($r['request_date'])) ?>: Tidak ada data
                                                </span>
                                            <?php elseif ($attStatus === 'Terlambat'): ?>
                                                <span
                                                    class="inline-flex items-center gap-1 text-[11px] px-2 py-1 rounded-lg bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 border border-red-200 dark:border-red-700 font-bold">
                                                    <i class="fas fa-exclamation-triangle"></i> TERLAMBAT pada
                                                    <?= date('d/m', strtotime($r['request_date'])) ?> — Tidak dapat kuota bensin!
                                                </span>
                                            <?php elseif ($attStatus === 'Alpha'): ?>
                                                <span
                                                    class="inline-flex items-center gap-1 text-[11px] px-2 py-1 rounded-lg bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400 border border-orange-200 dark:border-orange-700 font-bold">
                                                    <i class="fas fa-user-slash"></i> ALPHA (tidak masuk) pada
                                                    <?= date('d/m', strtotime($r['request_date'])) ?> — Tidak dapat kuota!
                                                </span>
                                            <?php elseif (in_array($attStatus, ['Hadir', 'Lembur'], true)): ?>
                                                <span
                                                    class="inline-flex items-center gap-1 text-[11px] px-2 py-1 rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-700">
                                                    <i class="fas fa-check-circle"></i>
                                                    <?= $attStatus === 'Lembur' ? 'Lembur (tepat waktu)' : 'Hadir tepat waktu' ?>
                                                    <?= date('d/m', strtotime($r['request_date'])) ?>
                                                </span>
                                            <?php else: ?>
                                                <span
                                                    class="inline-flex items-center gap-1 text-[11px] px-2 py-1 rounded-lg bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400 border border-gray-200 dark:border-gray-600">
                                                    <i class="fas fa-info-circle"></i> Status: <?= htmlspecialchars($attStatus) ?> pada
                                                    <?= date('d/m', strtotime($r['request_date'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endif;   ?>

                                        <?php if (($r['att_status'] ?? '') === '__technician__'): ?>
                                            <span
                                                class="inline-flex items-center gap-1 text-[11px] px-2 py-1 rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400 border border-blue-200 dark:border-blue-700">
                                                <i class="fas fa-hard-hat"></i> Teknisi — tidak pakai absensi & kuota
                                            </span>
                                        <?php endif; ?>

                                        <?php if (($r['reimburse_type'] ?? 'bensin') === 'bensin' && isset($r['period_quota'])): ?>
                                            <span
                                                class="inline-flex items-center gap-1 text-[11px] px-2 py-1 rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border border-blue-200 dark:border-blue-700">
                                                <i class="fas fa-chart-pie"></i>
                                                Kuota: Rp <?= number_format($r['period_quota'], 0, ',', '.') ?>
                                                (<?= $r['period_non_late_days'] ?> hari × 15rb)
                                                · Terpakai: Rp <?= number_format($r['period_used'], 0, ',', '.') ?>
                                                · Sisa: <strong>Rp <?= number_format($r['period_available'], 0, ',', '.') ?></strong>
                                            </span>
                                            <?php if ($r['amount'] > $r['period_available']): ?>
                                                <span
                                                    class="inline-flex items-center gap-1 text-[11px] px-2 py-1 rounded-lg bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 border border-red-200 dark:border-red-700 font-bold">
                                                    <i class="fas fa-exclamation-circle"></i> Melebihi sisa kuota!
                                                </span>
                                            <?php endif; ?>
                                            <span class="text-[10px] text-gray-400 dark:text-gray-500 self-center">
                                                Periode: <?= date('d/m', strtotime($r['period_start'])) ?> -
                                                <?= date('d/m', strtotime($r['period_end'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="flex flex-col gap-2 flex-shrink-0">
                                    <button
                                        onclick="editReimburse(<?= $r['id'] ?>, <?= htmlspecialchars(json_encode($r['reimburse_type'] ?? 'bensin'), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($r['amount']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($r['destination']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($r['description'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($r['request_date']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($r['request_date_end'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($r['distance_km'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($r['proof_path'] ?? ''), ENT_QUOTES) ?>)"
                                        class="px-4 py-2.5 bg-blue-500 text-white rounded-lg text-sm font-medium hover:bg-blue-600 transition shadow-sm" title="Edit"><i class="fas fa-edit mr-1"></i>Edit</button>
                                    <button onclick="approveReimburse(<?= $r['id'] ?>)"
                                        class="px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition shadow-sm" title="Approve"><i class="fas fa-check mr-1"></i>Setuju</button>
                                    <button onclick="rejectReimburse(<?= $r['id'] ?>)"
                                        class="px-4 py-2.5 bg-red-500 text-white rounded-lg text-sm font-medium hover:bg-red-600 transition shadow-sm" title="Tolak"><i class="fas fa-times mr-1"></i>Tolak</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach;   ?>
                        </div>
                    </div>
                    <?php endforeach;   ?>
                </div>
            </div>
        <?php endif; ?>

        
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="p-5 border-b border-slate-200 dark:border-slate-700">
                <h2 class="font-semibold text-gray-900 dark:text-white">
                    <?= $canViewAll ? 'Semua Reimbursement ' . $monthNames[$currentMonth] . ' ' . $currentYear : 'Riwayat Pengajuan Saya' ?>
                </h2>
            </div>
            <?php
            $displayList = ($canViewAll && !empty($allRequests)) ? $allRequests : $myRequests;
            
            $groupedByDate = [];
            foreach ($displayList as $r) {
                $groupKey = date('Y-m-d', strtotime($r['created_at']));
                $groupedByDate[$groupKey][] = $r;
            }
            ?>
            <?php if (empty($displayList)): ?>
                <div class="p-8 text-center text-gray-400">
                    <i class="fas fa-gas-pump text-3xl mb-3 block"></i>
                    <p>Belum ada pengajuan reimbursement</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                    <?php $groupIdx = 0; foreach ($groupedByDate as $dateKey => $groupItems):
                        $groupTotal = array_sum(array_column($groupItems, 'amount'));
                        $groupCount = count($groupItems);
                        $isExpanded = $groupIdx === 0; 
                    ?>
                        <div class="reimb-group">
                            
                            <button type="button" onclick="this.nextElementSibling.classList.toggle('hidden'); this.querySelector('.reimb-chevron').classList.toggle('rotate-180')"
                                class="w-full flex items-center justify-between p-4 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition text-left">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center flex-shrink-0">
                                        <span class="text-sm font-bold text-slate-600 dark:text-slate-300"><?= date('d', strtotime($dateKey)) ?></span>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-900 dark:text-white text-sm"><?= date('d M Y', strtotime($dateKey)) ?></div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400"><?= $groupCount ?> pengajuan &middot; Total <span class="font-semibold text-emerald-600 dark:text-emerald-400">Rp <?= number_format($groupTotal, 0, ',', '.') ?></span></div>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-down reimb-chevron text-gray-400 text-xs transition-transform <?= $isExpanded ? 'rotate-180' : '' ?>"></i>
                            </button>
                            
                            <div class="<?= $isExpanded ? '' : 'hidden' ?> border-t border-slate-100 dark:border-slate-700">
                                <?php foreach ($groupItems as $r):
                                    $rType2 = $r['reimburse_type'] ?? 'bensin';
                                    $typeBadgeCls = match ($rType2) { 'karcis' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400', 'lainnya' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400', default => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'};
                                    $typeIcon = match ($rType2) { 'karcis' => 'fa-ticket-alt', 'lainnya' => 'fa-receipt', default => 'fa-gas-pump'};
                                    $typeLabel = match ($rType2) { 'karcis' => 'Karcis', 'lainnya' => 'Lainnya', default => 'Bensin'};
                                    $statusBadge = match ($r['status']) {
                                        'pending' => '<span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400"><i class="fas fa-clock mr-0.5"></i>Pending</span>',
                                        'approved' => '<span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400"><i class="fas fa-check-circle mr-0.5"></i>Disetujui</span>',
                                        'rejected' => '<span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400"><i class="fas fa-times-circle mr-0.5"></i>Ditolak</span>',
                                        default => ''
                                    };
                                ?>
                                <div class="flex items-start gap-3 px-4 py-3 ml-4 border-l-2 border-slate-200 dark:border-slate-600 hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition">
                                    
                                    <div class="flex-shrink-0">
                                        <?php if (!empty($r['proof_path'])): ?>
                                            <a href="./<?= htmlspecialchars(ltrim($r['proof_path'], '/')) ?>" target="_blank" class="block w-12 h-12 rounded-lg overflow-hidden border border-slate-200 dark:border-slate-600 hover:ring-2 hover:ring-indigo-400 transition">
                                                <?php if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $r['proof_path'])): ?>
                                                    <img src="./<?= htmlspecialchars(ltrim($r['proof_path'], '/')) ?>" loading="lazy" class="w-full h-full object-cover" alt="Bukti" onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center bg-slate-100 dark:bg-slate-700\'><i class=\'fas fa-file text-slate-400 text-sm\'></i></div>'">
                                                <?php else: ?>
                                                    <div class="w-full h-full flex items-center justify-center bg-slate-100 dark:bg-slate-700"><i class="fas fa-file-pdf text-red-400"></i></div>
                                                <?php endif; ?>
                                            </a>
                                        <?php else: ?>
                                            <div class="w-12 h-12 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center"><i class="fas fa-receipt text-slate-300 text-sm"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <?php if ($canViewAll): ?>
                                                <span class="font-medium text-gray-900 dark:text-white text-sm"><?= htmlspecialchars($r['user_name']) ?></span>
                                            <?php endif; ?>
                                            <span class="inline-flex items-center text-[10px] px-1.5 py-0.5 rounded <?= $typeBadgeCls ?>">
                                                <i class="fas <?= $typeIcon ?> mr-0.5"></i><?= $typeLabel ?>
                                            </span>
                                            <?= $statusBadge ?>
                                        </div>
                                        <div class="flex items-center gap-2 mt-1 text-sm">
                                            <span class="font-semibold text-emerald-600 dark:text-emerald-400">Rp <?= number_format($r['amount'], 0, ',', '.') ?></span>
                                            <span class="text-gray-400">&middot;</span>
                                            <span class="text-gray-600 dark:text-gray-300 truncate"><?= htmlspecialchars($r['destination']) ?></span>
                                            <?php if ($r['distance_km']): ?>
                                                <span class="text-[10px] text-gray-400">(<?= rtrim(rtrim(number_format($r['distance_km'], 5), '0'), '.') ?> km)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex items-center gap-2 mt-0.5 text-[11px] text-gray-400">
                                            <span><i class="fas fa-calendar-day mr-0.5"></i><?= date('d M Y', strtotime($r['request_date'])) ?><?php if (!empty($r['request_date_end']) && $r['request_date_end'] !== $r['request_date']): ?> — <?= date('d M Y', strtotime($r['request_date_end'])) ?><?php endif; ?></span>
                                            <span>&middot;</span>
                                            <span>Diajukan <?= date('H:i', strtotime($r['created_at'])) ?></span>
                                        </div>
                                        <?php if ($r['status'] === 'rejected' && !empty($r['reject_reason'])): ?>
                                            <p class="text-[11px] text-red-500 mt-1"><i class="fas fa-info-circle mr-0.5"></i>Ditolak: <?= htmlspecialchars($r['reject_reason']) ?></p>
                                        <?php endif; ?>
                                        <?php if ($r['status'] === 'approved' && !empty($r['admin_name'])): ?>
                                            <p class="text-[11px] text-green-600 dark:text-green-400 mt-0.5"><i class="fas fa-check mr-0.5"></i>Oleh: <?= htmlspecialchars($r['admin_name']) ?></p>
                                        <?php endif; ?>
                                        <?php if ($r['status'] === 'approved'): ?>
                                            <?php 
                                            $paymentStatus = $r['payment_status'] ?? 'unpaid';
                                            $isPaid = $paymentStatus === 'paid';
                                            ?>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="inline-flex items-center text-[10px] px-2 py-0.5 rounded-full font-semibold <?= $isPaid ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' ?>">
                                                    <i class="fas <?= $isPaid ? 'fa-check-circle' : 'fa-clock' ?> mr-1"></i><?= $isPaid ? 'Sudah Dibayar' : 'Belum Dibayar' ?>
                                                </span>
                                                <?php if ($isPaid && !empty($r['payment_date'])): ?>
                                                    <span class="text-[10px] text-gray-400">pada <?= date('d M Y H:i', strtotime($r['payment_date'])) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($canApprove && $r['status'] === 'approved'): ?>
                                            <div class="flex items-center gap-2 mt-1.5">
                                                <button onclick="editReimburse(<?= $r['id'] ?>, <?= htmlspecialchars(json_encode($r['reimburse_type'] ?? 'bensin'), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($r['amount']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($r['destination']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($r['description'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($r['request_date']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($r['request_date_end'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($r['distance_km'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($r['proof_path'] ?? ''), ENT_QUOTES) ?>)"
                                                    class="px-2.5 py-1 bg-blue-500 text-white rounded text-[11px] font-medium hover:bg-blue-600 transition shadow-sm" title="Edit">
                                                    <i class="fas fa-edit mr-0.5"></i>Edit
                                                </button>
                                                <button onclick="deleteReimburse(<?= $r['id'] ?>)"
                                                    class="px-2.5 py-1 bg-red-500 text-white rounded text-[11px] font-medium hover:bg-red-600 transition shadow-sm" title="Hapus">
                                                    <i class="fas fa-trash mr-0.5"></i>Hapus
                                                </button>
                                                <?php 
                                                $paymentStatus = $r['payment_status'] ?? 'unpaid';
                                                $isPaid = $paymentStatus === 'paid';
                                                ?>
                                                <button onclick="updatePaymentStatus(<?= $r['id'] ?>, '<?= $isPaid ? 'unpaid' : 'paid' ?>')"
                                                    class="px-2.5 py-1 <?= $isPaid ? 'bg-orange-500 hover:bg-orange-600' : 'bg-emerald-500 hover:bg-emerald-600' ?> text-white rounded text-[11px] font-medium transition shadow-sm" title="<?= $isPaid ? 'Tandai Belum Dibayar' : 'Tandai Sudah Dibayar' ?>">
                                                    <i class="fas <?= $isPaid ? 'fa-undo' : 'fa-money-bill-wave' ?> mr-0.5"></i><?= $isPaid ? 'Belum Bayar' : 'Sudah Bayar' ?>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php $groupIdx++; endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    <?php endif;   ?>

    <?php if ($activeTab === 'kuota' && $canViewAll): ?>
        
        <?php if (!empty($quotaOverview)): ?>

            
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <?php
                $totalQuota = array_sum(array_column($quotaOverview, 'quota'));
                $totalUsed = array_sum(array_column($quotaOverview, 'used'));
                $totalAvailable = array_sum(array_column($quotaOverview, 'available'));
                $totalLate = array_sum(array_column($quotaOverview, 'late_days'));
                ?>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center">
                            <i class="fas fa-calculator text-blue-600 dark:text-blue-400 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Kuota</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">Rp
                                <?= number_format($totalQuota, 0, ',', '.') ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center">
                            <i class="fas fa-fire text-amber-600 dark:text-amber-400 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Terpakai</p>
                            <p class="text-lg font-bold text-amber-600 dark:text-amber-400">Rp
                                <?= number_format($totalUsed, 0, ',', '.') ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center">
                            <i class="fas fa-wallet text-emerald-600 dark:text-emerald-400 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Sisa</p>
                            <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">Rp
                                <?= number_format($totalAvailable, 0, ',', '.') ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-red-100 dark:bg-red-900/40 flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-red-500 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Hari Telat
                            </p>
                            <p class="text-lg font-bold text-red-600 dark:text-red-400"><?= $totalLate ?> <span
                                    class="text-sm font-normal text-gray-500">hari</span></p>
                        </div>
                    </div>
                </div>
            </div>

            
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="p-5 border-b border-slate-200 dark:border-slate-700">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center">
                            <i class="fas fa-bolt text-amber-500"></i>
                        </div>
                        <div>
                            <h2 class="font-semibold text-gray-900 dark:text-white">Kuota Bensin Per Karyawan</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Periode <?= $qPeriodLabel ?> · Rp 15.000/hari
                                hadir tepat waktu · <?= count($quotaOverview) ?> karyawan</p>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-700/50 text-left">
                                <th class="px-5 py-3 font-semibold text-gray-600 dark:text-gray-300">Karyawan</th>
                                <th class="px-3 py-3 font-semibold text-gray-600 dark:text-gray-300 text-center">Role</th>
                                <th class="px-3 py-3 font-semibold text-emerald-600 dark:text-emerald-400 text-center">Hadir
                                    Tepat</th>
                                <th class="px-3 py-3 font-semibold text-red-500 text-center">Telat</th>
                                <th class="px-3 py-3 font-semibold text-blue-600 dark:text-blue-400 text-right">Kuota</th>
                                <th class="px-3 py-3 font-semibold text-amber-600 dark:text-amber-400 text-right">Terpakai</th>
                                <th class="px-3 py-3 font-semibold text-emerald-600 dark:text-emerald-400 text-right">Sisa</th>
                                <th class="px-3 py-3 font-semibold text-gray-600 dark:text-gray-300 text-center">Detail Absensi
                                </th>
                                <th class="px-3 py-3 font-semibold text-gray-600 dark:text-gray-300 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            <?php
                            $qRoleLabels2 = ['technician' => 'Teknisi', 'hse' => 'HSE', 'daily' => 'Harian', 'internship' => 'Magang'];
                            $qDayNames2 = ['', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
                            foreach ($quotaOverview as $qo):
                                $pctUsed2 = $qo['quota'] > 0 ? round(($qo['used'] / $qo['quota']) * 100) : 0;
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                    <td class="px-5 py-3">
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            <?= htmlspecialchars($qo['full_name']) ?>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <span
                                            class="text-xs px-2 py-0.5 rounded-full <?= match ($qo['role']) { 'technician' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', 'hse' => 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400', 'daily' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400', 'internship' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400', default => 'bg-gray-100 text-gray-700'} ?>"><?= $qRoleLabels2[$qo['role']] ?? ucfirst($qo['role']) ?></span>
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <span
                                            class="font-bold text-emerald-600 dark:text-emerald-400 text-lg"><?= $qo['non_late_days'] ?></span>
                                        <span class="text-gray-400 text-xs">hari</span>
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <?php if ($qo['late_days'] > 0): ?>
                                            <span
                                                class="font-bold text-red-600 dark:text-red-400 text-lg"><?= $qo['late_days'] ?></span>
                                            <span class="text-gray-400 text-xs">hari</span>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <span class="font-semibold text-blue-600 dark:text-blue-400">Rp
                                            <?= number_format($qo['quota'], 0, ',', '.') ?></span>
                                        <?php if (($qo['manual_adj'] ?? 0) != 0): ?>
                                            <div class="text-[10px] <?= $qo['manual_adj'] > 0 ? 'text-emerald-500' : 'text-red-500' ?>">
                                                <?= $qo['manual_adj'] > 0 ? '+' : '' ?>Rp
                                                <?= number_format($qo['manual_adj'], 0, ',', '.') ?> manual
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <span
                                            class="<?= $qo['used'] > 0 ? 'font-semibold text-amber-600 dark:text-amber-400' : 'text-gray-400' ?>">Rp
                                            <?= number_format($qo['used'], 0, ',', '.') ?></span>
                                        <?php if ($qo['quota'] > 0): ?>
                                            <div class="mt-1 w-full bg-gray-200 dark:bg-gray-600 rounded-full h-1.5">
                                                <div class="h-1.5 rounded-full <?= $pctUsed2 >= 90 ? 'bg-red-500' : ($pctUsed2 >= 60 ? 'bg-amber-500' : 'bg-emerald-500') ?>"
                                                    style="width: <?= min($pctUsed2, 100) ?>%"></div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <span
                                            class="font-bold <?= $qo['available'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' ?>">Rp
                                            <?= number_format($qo['available'], 0, ',', '.') ?></span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex flex-wrap gap-0.5 justify-center">
                                            <?php foreach ($qo['att_detail'] as $ad):
                                                $adDt2 = new DateTime($ad['date']);
                                                $adDayNum2 = (int) $adDt2->format('N');
                                                $adStatus = $ad['status'] ?? '';
                                                if (in_array($adStatus, ['Hadir', 'Lembur'], true)) {
                                                    $adLabel = 'Tepat Waktu';
                                                    $adCls = 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-400';
                                                    $adIcon = '✓';
                                                } elseif ($adStatus === 'Terlambat') {
                                                    $adLabel = 'Terlambat';
                                                    $adCls = 'bg-red-100 text-red-500 dark:bg-red-900/20 dark:text-red-400';
                                                    $adIcon = '✗';
                                                } else {
                                                    $adLabel = 'Alpha (tidak masuk)';
                                                    $adCls = 'bg-orange-100 text-orange-600 dark:bg-orange-900/20 dark:text-orange-400';
                                                    $adIcon = '—';
                                                }
                                                ?>
                                                <span
                                                    title="<?= $qDayNames2[$adDayNum2] ?> <?= $adDt2->format('d/m') ?> — <?= $adLabel ?>"
                                                    class="text-[8px] px-1 py-0.5 rounded <?= $adCls ?> cursor-default">
                                                    <?= $adDt2->format('d') ?>                 <?= $adIcon ?>
                                                </span>
                                            <?php endforeach; ?>
                                            <?php if (empty($qo['att_detail'])): ?>
                                                <span class="text-xs text-gray-400">Belum ada</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <button
                                            onclick="openAdjustQuota(<?= $qo['user_id'] ?>, <?= htmlspecialchars(json_encode($qo['full_name']), ENT_QUOTES) ?>)"
                                            class="px-4 py-2.5 bg-indigo-500 text-white rounded-lg text-sm font-medium hover:bg-indigo-600 transition shadow-sm"
                                            title="Tambah/Kurangi Kuota Manual">
                                            <i class="fas fa-plus-minus mr-1"></i>Atur Kuota
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-50 dark:bg-slate-700/50 font-bold">
                                <td class="px-5 py-3 text-gray-900 dark:text-white" colspan="2">Total
                                    (<?= count($quotaOverview) ?> orang)</td>
                                <td class="px-3 py-3 text-center text-emerald-600 dark:text-emerald-400">
                                    <?= array_sum(array_column($quotaOverview, 'non_late_days')) ?>
                                </td>
                                <td class="px-3 py-3 text-center text-red-500"><?= $totalLate ?></td>
                                <td class="px-3 py-3 text-right text-blue-600 dark:text-blue-400">Rp
                                    <?= number_format($totalQuota, 0, ',', '.') ?>
                                </td>
                                <td class="px-3 py-3 text-right text-amber-600 dark:text-amber-400">Rp
                                    <?= number_format($totalUsed, 0, ',', '.') ?>
                                </td>
                                <td class="px-3 py-3 text-right text-emerald-600 dark:text-emerald-400">Rp
                                    <?= number_format($totalAvailable, 0, ',', '.') ?>
                                </td>
                                <td class="px-3 py-3"></td>
                                <td class="px-3 py-3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-12 text-center">
                <i class="fas fa-bolt text-4xl text-gray-300 mb-3 block"></i>
                <p class="text-gray-500 font-medium">Belum ada data kuota</p>
                <p class="text-sm text-gray-400 mt-1">Data kuota akan muncul setelah ada karyawan dengan absensi di periode ini
                </p>
            </div>
        <?php endif; ?>

    <?php endif;   ?>

</div>


<div id="modalAdjustQuota" style="display:none"
    class="fixed inset-0 z-[80] bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-md p-6">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <span class="w-7 h-7 bg-indigo-100 dark:bg-indigo-900/40 rounded-lg flex items-center justify-center"><i
                    class="fas fa-plus-minus text-indigo-600 dark:text-indigo-400 text-xs"></i></span>
            Tambah Kuota Manual
        </h3>
        <form id="formAdjustQuota" class="space-y-3">
            <input type="hidden" id="adjustQuotaUserId">
            <div>
                <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Karyawan</label>
                <p id="adjustQuotaUserName" class="font-semibold text-gray-900 dark:text-white"></p>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Nominal (Rp)</label>
                <input type="number" id="adjustQuotaAmount" required min="1000" step="1000" placeholder="15000"
                    class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2.5 rounded-lg text-sm">
                <p class="text-[10px] text-gray-400 mt-1">Masukkan nominal positif untuk menambah kuota</p>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Alasan <span
                        class="text-red-500">*</span></label>
                <textarea id="adjustQuotaReason" rows="2" required
                    placeholder="Contoh: Kuota tambahan untuk tugas lapangan"
                    class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2.5 rounded-lg text-sm"></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('modalAdjustQuota').style.display='none'"
                    class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-white rounded-lg text-sm">Batal</button>
                <button type="submit" id="btnAdjustQuota"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition"><i
                        class="fas fa-check mr-1"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>


<div id="modalReimburse"
    class="hidden fixed inset-0 z-[70] bg-black/60 backdrop-blur-sm items-center justify-center p-3 sm:p-4 overflow-y-auto"
    style="display:none">
    <div class="bg-white dark:bg-gray-900 rounded-2xl w-full mx-auto max-h-[95vh] overflow-y-auto shadow-2xl border border-gray-100 dark:border-gray-800"
        style="max-width:560px">
        
        <div class="sticky top-0 z-10 px-5 py-4 rounded-t-2xl"
            style="background:linear-gradient(to right,#059669,#10b981,#34d399)">
            <div class="flex justify-between items-center">
                <h3 class="font-bold text-lg text-white flex items-center gap-2">
                    <span class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center"><i
                            class="fas fa-receipt text-sm"></i></span>
                    Ajukan Reimbursement
                </h3>
                <button onclick="document.getElementById('modalReimburse').style.display='none'"
                    class="w-8 h-8 rounded-lg bg-white/20 hover:bg-white/30 flex items-center justify-center text-white transition">&times;</button>
            </div>
        </div>

        <form id="formReimburse" class="p-5 space-y-4" enctype="multipart/form-data">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Jenis Reimbursement
                    <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-3 gap-3" id="reimburseTypeSelector">
                    <div class="reimb-type-card selected" data-value="bensin" onclick="selectReimburseType('bensin')">
                        <div
                            class="w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center">
                            <i class="fas fa-gas-pump text-amber-600 dark:text-amber-400"></i>
                        </div>
                        <div>
                            <span class="text-sm font-bold text-gray-800 dark:text-white block">Bensin</span>
                            <span class="text-[11px] text-gray-400 leading-tight">Uang bahan bakar</span>
                        </div>
                        <i class="fas fa-check-circle reimb-check text-emerald-500 text-base"></i>
                    </div>
                    <div class="reimb-type-card" data-value="karcis" onclick="selectReimburseType('karcis')">
                        <div
                            class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center">
                            <i class="fas fa-ticket-alt text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <div>
                            <span class="text-sm font-bold text-gray-800 dark:text-white block">Karcis</span>
                            <span class="text-[11px] text-gray-400 leading-tight">Tol, parkir, dll</span>
                        </div>
                        <i class="fas fa-check-circle reimb-check text-emerald-500 text-base"></i>
                    </div>
                    <div class="reimb-type-card" data-value="lainnya" onclick="selectReimburseType('lainnya')">
                        <div
                            class="w-10 h-10 rounded-lg bg-violet-100 dark:bg-violet-900/40 flex items-center justify-center">
                            <i class="fas fa-receipt text-violet-600 dark:text-violet-400"></i>
                        </div>
                        <div>
                            <span class="text-sm font-bold text-gray-800 dark:text-white block">Lainnya</span>
                            <span class="text-[11px] text-gray-400 leading-tight">Reimburse umum</span>
                        </div>
                        <i class="fas fa-check-circle reimb-check text-emerald-500 text-base"></i>
                    </div>
                </div>
                <input type="hidden" name="reimburse_type" id="reimburseTypeInput" value="bensin">
            </div>

            
            <?php if ($hasQuota): ?>
                <div id="quotaBensinInfo"
                    class="p-3 rounded-xl border <?= $periodAvailable > 0 ? 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-700' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-700' ?>">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Sisa kuota bensin periode ini</p>
                            <p
                                class="text-lg font-bold <?= $periodAvailable > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-red-600 dark:text-red-400' ?>">
                                Rp <?= number_format($periodAvailable, 0, ',', '.') ?></p>
                        </div>
                        <div class="text-right text-[10px] text-gray-400 leading-relaxed">
                            <div>Kuota:
                                <?= count(array_filter($quotaDaysDetail, fn($d) => in_array(($d['status'] ?? ''), ['Hadir', 'Lembur'], true))) ?>
                                hari × Rp 15.000 = Rp <?= number_format($periodQuota, 0, ',', '.') ?>
                            </div>
                            <?php if ($periodUsed > 0): ?>
                                <div>Terpakai: Rp <?= number_format($periodUsed, 0, ',', '.') ?></div><?php endif; ?>
                            <div><?= $periodLabel ?> · Reset tgl
                                <?= (int) (new DateTime($todayStr))->format('j') <= 15 ? '16' : '1' ?>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($quotaDaysDetail)): ?>
                        <div class="flex flex-wrap gap-1.5 mt-1">
                            <?php
                            $dayNames = ['', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
                            foreach ($quotaDaysDetail as $qd):
                                $dDate = new DateTime($qd['date']);
                                $dayNum = (int) $dDate->format('N');
                                $dayLabel = $dayNames[$dayNum] ?? '';
                                $dateLabel = $dDate->format('d/m');
                                $qdStatus = $qd['status'] ?? '';
                                if (in_array($qdStatus, ['Hadir', 'Lembur'], true)) {
                                    $qdCls = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
                                    $qdIcon = 'fa-check-circle';
                                    $qdBonus = '(+15rb)';
                                } elseif ($qdStatus === 'Terlambat') {
                                    $qdCls = 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400';
                                    $qdIcon = 'fa-times-circle';
                                    $qdBonus = '';
                                } else {
                                    $qdCls = 'bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400';
                                    $qdIcon = 'fa-minus-circle';
                                    $qdBonus = '';
                                }
                                ?>
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] font-medium <?= $qdCls ?>">
                                    <i class="fas <?= $qdIcon ?>" style="font-size:8px"></i>
                                    <?= $dayLabel ?>             <?= $dateLabel ?>             <?= $qdBonus ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif;   ?>

            
            <div id="datePickerSection">
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Periode <span
                        class="text-red-500">*</span></label>

                <div class="grid grid-cols-2 gap-3" id="simpleDateInputs">
                    <div>
                        <label class="text-[10px] text-gray-500 dark:text-gray-400 font-medium mb-1 block">Dari
                            Tanggal</label>
                        <input type="date" name="request_date" id="inputRequestDate" required
                            min="<?= date('Y-m-d', strtotime('-14 days')) ?>" max="<?= date('Y-m-d') ?>"
                            class="reimb-input !py-2 !px-3">
                    </div>
                    <div>
                        <label class="text-[10px] text-gray-500 dark:text-gray-400 font-medium mb-1 block">Sampai
                            Tanggal</label>
                        <input type="date" name="request_date_end" id="inputRequestDateEnd"
                            min="<?= date('Y-m-d', strtotime('-14 days')) ?>" max="<?= date('Y-m-d') ?>"
                            class="reimb-input !py-2 !px-3">
                    </div>
                </div>
                <p class="text-[10px] text-gray-400 mt-1"><i class="fas fa-info-circle mr-0.5"></i>Maksimal klaim untuk
                    14 hari ke belakang. Kosongkan "Sampai Tanggal" jika hanya 1 hari.</p>
            </div>

            
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5"
                    id="lblDestination">Tujuan Perjalanan <span class="text-red-500">*</span></label>
                <input type="text" name="destination" required maxlength="255" id="inputDestination"
                    placeholder="Contoh: Kantor Client PT ABC, Jl. Sudirman No.10" class="reimb-input">
            </div>

            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Nominal <span
                            class="text-red-500">*</span></label>
                    <div
                        class="relative flex rounded-xl overflow-hidden border border-gray-200 dark:border-gray-600 focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500/15 transition">
                        <span
                            class="flex items-center justify-center px-4 text-base text-gray-500 dark:text-gray-400 font-semibold bg-gray-100 dark:bg-gray-700 select-none">Rp</span>
                        <input type="number" name="amount" required min="1000" max="1000000" step="500"
                            placeholder="50000"
                            class="flex-1 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white p-3 text-base outline-none border-0">
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">Maks. Rp 1.000.000</p>
                </div>
                <div id="distanceField">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Jarak Tempuh (km)
                        <span class="text-xs text-gray-400 font-normal">opsional</span></label>
                    <input type="text" inputmode="decimal" name="distance_km" placeholder="25.5" class="reimb-input"
                        oninput="this.value = this.value.replace(/[^0-9.]/g, '')">
                </div>
            </div>

            
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Keterangan <span
                        class="text-xs text-gray-400 font-normal">opsional</span></label>
                <textarea name="description" rows="2" placeholder="Keterangan tambahan..." class="reimb-input"
                    style="resize:vertical"></textarea>
            </div>

            
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5" id="lblProof">Bukti
                    Struk / Karcis <span class="text-red-500">*</span></label>
                <div class="border-2 border-dashed border-gray-200 dark:border-gray-600 rounded-xl p-4 text-center hover:border-emerald-400 transition cursor-pointer"
                    onclick="document.getElementById('proofFileInput').click()">
                    <i class="fas fa-cloud-upload-alt text-2xl text-gray-300 dark:text-gray-500 mb-1"></i>
                    <p class="text-sm text-gray-500 dark:text-gray-400" id="proofFileName">Klik untuk upload foto/file
                    </p>
                    <p class="text-[11px] text-gray-400 mt-0.5">JPG, PNG, PDF — maks. 5MB</p>
                </div>
                <input type="file" name="proof" id="proofFileInput" required accept="image/*,.pdf" class="hidden"
                    onchange="document.getElementById('proofFileName').textContent = this.files[0]?.name || 'Klik untuk upload foto/file'">
            </div>

            
            <div class="flex justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-800">
                <button type="button" onclick="document.getElementById('modalReimburse').style.display='none'"
                    class="px-5 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-white rounded-xl text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition">Batal</button>
                <button type="submit" id="btnSubmitReimburse"
                    class="px-5 py-2.5 bg-emerald-600 text-white rounded-xl text-sm font-semibold hover:bg-emerald-700 transition shadow-sm"><i
                        class="fas fa-paper-plane mr-1.5"></i>Ajukan</button>
            </div>
        </form>
    </div>
</div>


<div id="modalRejectReimburse" style="display:none"
    class="fixed inset-0 z-[80] bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-md p-6">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-3">Tolak Reimbursement</h3>
        <form id="formRejectReimburse">
            <input type="hidden" id="rejectReimburseId">
            <textarea id="rejectReimburseReason" rows="3" placeholder="Alasan penolakan (opsional)"
                class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2.5 rounded-lg text-sm mb-3"></textarea>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modalRejectReimburse').style.display='none'"
                    class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-white rounded-lg text-sm">Batal</button>
                <button type="submit"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">Tolak</button>
            </div>
        </form>
    </div>
</div>


<div id="modalEditReimburse" style="display:none"
    class="fixed inset-0 z-[80] bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-md p-6">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <span class="w-7 h-7 bg-blue-100 dark:bg-blue-900/40 rounded-lg flex items-center justify-center"><i
                    class="fas fa-edit text-blue-600 dark:text-blue-400 text-xs"></i></span>
            Edit Reimbursement
        </h3>
        <form id="formEditReimburse" class="space-y-3">
            <input type="hidden" id="editReimburseId">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jenis
                    Reimbursement</label>
                <select id="editReimburseType"
                    class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2.5 rounded-lg text-sm">
                    <option value="bensin">Bensin</option>
                    <option value="karcis">Karcis</option>
                    <option value="lainnya">Lainnya</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dari Tanggal</label>
                    <input type="date" id="editReimburseDate" required
                        class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2.5 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sampai
                        Tanggal</label>
                    <input type="date" id="editReimburseDateEnd"
                        class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2.5 rounded-lg text-sm">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nominal (Rp)</label>
                    <input type="number" id="editReimburseAmount" min="1000" max="1000000" step="500" required
                        class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2.5 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jarak (km)</label>
                    <input type="number" step="0.01" id="editReimburseDistance"
                        class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2.5 rounded-lg text-sm">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Keterangan /
                    Tujuan</label>
                <input type="text" id="editReimburseDestination" maxlength="255" required
                    class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2.5 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Deskripsi <span
                        class="text-xs text-gray-400">(opsional)</span></label>
                <textarea id="editReimburseDescription" rows="2"
                    class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2.5 rounded-lg text-sm"></textarea>
            </div>
            
            <div class="border border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-3">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    <i class="fas fa-paperclip mr-1 text-blue-500"></i>Ganti Bukti Struk
                    <span class="text-xs text-gray-400 font-normal">(opsional — kosongkan jika tidak diganti)</span>
                </label>
                <div id="editProofPreviewWrap" class="mb-2 hidden">
                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mb-1">Bukti saat ini:</p>
                    <a id="editProofCurrentLink" href="#" target="_blank"
                        class="inline-flex items-center gap-1.5 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                        <i class="fas fa-file-image"></i>
                        <span id="editProofCurrentName">Lihat Bukti</span>
                    </a>
                </div>
                <div class="flex items-center gap-3">
                    <label for="editProofFileInput"
                        class="cursor-pointer flex items-center gap-2 px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm transition">
                        <i class="fas fa-upload text-blue-500"></i> Pilih File Baru
                    </label>
                    <span id="editProofFileName" class="text-xs text-gray-500 dark:text-gray-400 truncate">Belum ada file dipilih</span>
                </div>
                <input type="file" id="editProofFileInput" accept="image/*,.pdf" class="hidden"
                    onchange="document.getElementById('editProofFileName').textContent = this.files[0]?.name || 'Belum ada file dipilih'">
                <p class="text-[10px] text-gray-400 mt-1">JPG, PNG, PDF — maks. 5MB</p>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('modalEditReimburse').style.display='none'"
                    class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-white rounded-lg text-sm">Batal</button>
                <button type="submit" id="btnEditReimburse"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition"><i
                        class="fas fa-save mr-1"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>

<style>
    .reimb-input {
        width: 100%;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: #1e293b;
        padding: 0.75rem 1rem;
        border-radius: 0.75rem;
        font-size: 1rem;
        transition: all 0.15s;
        outline: none;
    }

    .reimb-input:focus {
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
    }

    .dark .reimb-input {
        border-color: #475569;
        background: #1e293b;
        color: #f1f5f9;
    }

    .dark .reimb-input:focus {
        border-color: #34d399;
        box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.15);
    }

    .reimb-type-card {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.875rem;
        border: 2px solid #e2e8f0;
        border-radius: 0.875rem;
        cursor: pointer;
        transition: all 0.2s;
        position: relative;
        user-select: none;
    }

    .dark .reimb-type-card {
        border-color: #475569;
    }

    .reimb-type-card:hover {
        border-color: #6ee7b7;
        background: #f0fdf4;
    }

    .dark .reimb-type-card:hover {
        border-color: #34d399;
        background: rgba(16, 185, 129, 0.08);
    }

    .reimb-type-card.selected {
        border-color: #10b981;
        background: #ecfdf5;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
    }

    .dark .reimb-type-card.selected {
        border-color: #34d399;
        background: rgba(16, 185, 129, 0.12);
    }

    .reimb-type-card .reimb-check {
        display: none;
        position: absolute;
        top: 8px;
        right: 8px;
    }

    .reimb-type-card.selected .reimb-check {
        display: block;
    }

    .cal-day {
        background: #fff;
        padding: 6px 2px;
        text-align: center;
        position: relative;
        min-height: 36px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        transition: all 0.15s;
    }

    .dark .cal-day {
        background: #1e293b;
    }

    .cal-num {
        font-size: 13px;
        font-weight: 600;
        line-height: 1;
    }

    .cal-dot {
        width: 4px;
        height: 4px;
        border-radius: 50%;
        margin-top: 3px;
    }

    .cal-ok .cal-num {
        color: #059669;
    }

    .dark .cal-ok .cal-num {
        color: #6ee7b7;
    }

    .cal-late .cal-num {
        color: #ef4444;
        text-decoration: line-through;
        opacity: 0.5;
    }

    .cal-alpha .cal-num {
        color: #f97316;
        text-decoration: line-through;
        opacity: 0.5;
    }

    .cal-none .cal-num {
        color: #d1d5db;
    }

    .dark .cal-none .cal-num {
        color: #4b5563;
    }

    .cal-day.cal-selected {
        background: #059669 !important;
        border-radius: 0;
    }

    .cal-day.cal-selected .cal-num {
        color: #fff !important;
    }

    .cal-day.cal-selected .cal-dot {
        background: #fff !important;
    }

    .cal-day.cal-range {
        background: #d1fae5 !important;
    }

    .dark .cal-day.cal-range {
        background: rgba(16, 185, 129, 0.15) !important;
    }

    .cal-day.cal-range .cal-num {
        color: #065f46 !important;
    }

    .dark .cal-day.cal-range .cal-num {
        color: #6ee7b7 !important;
    }

    @media (max-width: 640px) {
        .reimb-type-card {
            padding: 0.625rem;
            gap: 0.5rem;
        }

        .reimb-type-card .w-10 {
            width: 2rem;
            height: 2rem;
            min-width: 2rem;
        }

        .reimb-type-card .w-10 i {
            font-size: 0.75rem;
        }

        .reimb-type-card span.text-sm {
            font-size: 0.75rem;
        }

        .reimb-type-card span.text-\[11px\] {
            font-size: 9px;
        }

        .cal-day {
            min-height: 32px;
            padding: 4px 1px;
        }

        .cal-num {
            font-size: 11px;
        }

        .cal-dot {
            width: 3px;
            height: 3px;
            margin-top: 2px;
        }
    }
</style>

<script>
    const REIMBURSE_API = <?= json_encode(rtrim(BASE_URL, '/') . '/app/pages/fuel-reimbursement.php') ?>;

    function selectReimburseType(type) {
        document.getElementById('reimburseTypeInput').value = type;
        document.querySelectorAll('.reimb-type-card').forEach(c => c.classList.remove('selected'));
        document.querySelector('.reimb-type-card[data-value="' + type + '"]').classList.add('selected');

        const distanceField = document.getElementById('distanceField');
        const lblDest = document.getElementById('lblDestination');
        const inputDest = document.getElementById('inputDestination');
        const lblProof = document.getElementById('lblProof');
        const dateInput = document.getElementById('inputRequestDate');
        const quotaInfo = document.getElementById('quotaBensinInfo');
        const calContainer = document.getElementById('calendarContainer');
        const simpleDateInputs = document.getElementById('simpleDateInputs');

        if (type === 'karcis') {
            distanceField.style.display = 'none';
            distanceField.querySelector('input').value = '';
            lblDest.innerHTML = 'Keterangan Karcis <span class="text-red-500">*</span>';
            inputDest.placeholder = 'Contoh: Tol Tangerang-Merak, Parkir Bandara';
            lblProof.innerHTML = 'Bukti Karcis / Struk <span class="text-red-500">*</span>';
            if (quotaInfo) quotaInfo.style.display = 'none';
        } else if (type === 'lainnya') {
            distanceField.style.display = 'none';
            distanceField.querySelector('input').value = '';
            lblDest.innerHTML = 'Keterangan <span class="text-red-500">*</span>';
            inputDest.placeholder = 'Contoh: Beli alat kantor, Service laptop, dll';
            lblProof.innerHTML = 'Bukti / Nota <span class="text-red-500">*</span>';
            if (quotaInfo) quotaInfo.style.display = 'none';
        } else {
            distanceField.style.display = '';
            lblDest.innerHTML = 'Tujuan Perjalanan <span class="text-red-500">*</span>';
            inputDest.placeholder = 'Contoh: Kantor Client PT ABC, Jl. Sudirman No.10';
            lblProof.innerHTML = 'Bukti Struk Bensin <span class="text-red-500">*</span>';
            if (quotaInfo) quotaInfo.style.display = '';
        }
    }

    function openReimburseModal() {
        document.getElementById('modalReimburse').style.display = 'flex';
        document.getElementById('formReimburse').reset();
        document.getElementById('proofFileName').textContent = 'Klik untuk upload foto/file';
        selectReimburseType('bensin');
    }

    document.getElementById('formReimburse')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitReimburse');
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Mengirim...';
        try {
            const fd = new FormData(this);
            fd.append('action', 'submit_reimbursement');
            const resp = await fetch(REIMBURSE_API, { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await resp.json();
            if (data.success) {
                if (typeof showToast === 'function') showToast(data.message, 'success');
                else alert(data.message);
                document.getElementById('modalReimburse').style.display = 'none';
                setTimeout(() => location.reload(), 800);
            } else {
                if (typeof showToast === 'function') showToast(data.message, 'error');
                else alert(data.message);
            }
        } catch (err) {
            alert('Error: ' + err.message);
        } finally {
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane mr-1.5"></i>Ajukan';
        }
    });

    async function approveReimburse(id) {
        if (!confirm('Setujui reimbursement ini?')) return;
        try {
            const fd = new FormData();
            fd.append('action', 'approve_reimbursement');
            fd.append('reimburse_id', id);
            const resp = await fetch(REIMBURSE_API, { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await resp.json();
            if (data.success) {
                if (typeof showToast === 'function') showToast(data.message, 'success');
                const row = document.getElementById('reimb-row-' + id);
                if (row) row.style.opacity = '0.4';
                setTimeout(() => location.reload(), 800);
            } else {
                if (typeof showToast === 'function') showToast(data.message, 'error');
                else alert(data.message);
            }
        } catch (err) { alert('Error: ' + err.message); }
    }

    function rejectReimburse(id) {
        document.getElementById('rejectReimburseId').value = id;
        document.getElementById('rejectReimburseReason').value = '';
        document.getElementById('modalRejectReimburse').style.display = 'flex';
    }

    document.getElementById('formRejectReimburse')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const id = document.getElementById('rejectReimburseId').value;
        const reason = document.getElementById('rejectReimburseReason').value;
        try {
            const fd = new FormData();
            fd.append('action', 'reject_reimbursement');
            fd.append('reimburse_id', id);
            fd.append('reject_reason', reason);
            const resp = await fetch(REIMBURSE_API, { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await resp.json();
            if (data.success) {
                if (typeof showToast === 'function') showToast(data.message, 'success');
                document.getElementById('modalRejectReimburse').style.display = 'none';
                setTimeout(() => location.reload(), 800);
            } else {
                if (typeof showToast === 'function') showToast(data.message, 'error');
                else alert(data.message);
            }
        } catch (err) { alert('Error: ' + err.message); }
    });

    function editReimburse(id, type, amount, destination, description, reqDate, reqDateEnd, distance, proofPath) {
        document.getElementById('editReimburseId').value = id;
        document.getElementById('editReimburseType').value = type || 'bensin';
        document.getElementById('editReimburseAmount').value = amount;
        document.getElementById('editReimburseDestination').value = destination;
        document.getElementById('editReimburseDescription').value = description || '';
        document.getElementById('editReimburseDate').value = reqDate || '';
        document.getElementById('editReimburseDateEnd').value = reqDateEnd || '';
        document.getElementById('editReimburseDistance').value = distance || '';
        document.getElementById('editProofFileInput').value = '';
        document.getElementById('editProofFileName').textContent = 'Belum ada file dipilih';
        const previewWrap = document.getElementById('editProofPreviewWrap');
        const proofLink = document.getElementById('editProofCurrentLink');
        const proofName = document.getElementById('editProofCurrentName');
        if (proofPath) {
            proofLink.href = proofPath;
            const parts = proofPath.split('/');
            proofName.textContent = parts[parts.length - 1];
            previewWrap.classList.remove('hidden');
        } else {
            previewWrap.classList.add('hidden');
        }
        document.getElementById('modalEditReimburse').style.display = 'flex';
    }

    document.getElementById('formEditReimburse')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('btnEditReimburse');
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Menyimpan...';
        try {
            const fd = new FormData();
            fd.append('action', 'edit_reimbursement');
            fd.append('reimburse_id', document.getElementById('editReimburseId').value);
            fd.append('reimburse_type', document.getElementById('editReimburseType').value);
            fd.append('amount', document.getElementById('editReimburseAmount').value);
            fd.append('destination', document.getElementById('editReimburseDestination').value);
            fd.append('description', document.getElementById('editReimburseDescription').value);
            fd.append('request_date', document.getElementById('editReimburseDate').value);
            fd.append('request_date_end', document.getElementById('editReimburseDateEnd').value);
            fd.append('distance_km', document.getElementById('editReimburseDistance').value);
            const proofFile = document.getElementById('editProofFileInput').files[0];
            if (proofFile) fd.append('edit_proof', proofFile);
            const resp = await fetch(REIMBURSE_API, { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await resp.json();
            if (data.success) {
                if (typeof showToast === 'function') showToast(data.message, 'success');
                document.getElementById('modalEditReimburse').style.display = 'none';
                setTimeout(() => location.reload(), 800);
            } else {
                if (typeof showToast === 'function') showToast(data.message, 'error');
                else alert(data.message);
            }
        } catch (err) { alert('Error: ' + err.message); }
        finally {
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-save mr-1"></i>Simpan';
        }
    });

    function openAdjustQuota(userId, userName) {
        document.getElementById('adjustQuotaUserId').value = userId;
        document.getElementById('adjustQuotaUserName').textContent = userName;
        document.getElementById('adjustQuotaAmount').value = '';
        document.getElementById('adjustQuotaReason').value = '';
        document.getElementById('modalAdjustQuota').style.display = 'flex';
    }

    document.getElementById('formAdjustQuota')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('btnAdjustQuota');
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Menyimpan...';
        try {
            const fd = new FormData();
            fd.append('action', 'adjust_quota');
            fd.append('target_user_id', document.getElementById('adjustQuotaUserId').value);
            fd.append('adjust_amount', document.getElementById('adjustQuotaAmount').value);
            fd.append('adjust_reason', document.getElementById('adjustQuotaReason').value);
            const resp = await fetch(REIMBURSE_API, { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await resp.json();
            if (data.success) {
                if (typeof showToast === 'function') showToast(data.message, 'success');
                document.getElementById('modalAdjustQuota').style.display = 'none';
                setTimeout(() => location.reload(), 800);
            } else {
                if (typeof showToast === 'function') showToast(data.message, 'error');
                else alert(data.message);
            }
        } catch (err) { alert('Error: ' + err.message); }
        finally {
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-check mr-1"></i>Simpan';
        }
    });

    async function deleteReimburse(id) {
        if (!confirm('Apakah Anda yakin ingin menghapus reimbursement ini? Data yang dihapus tidak dapat dikembalikan.')) return;
        try {
            const fd = new FormData();
            fd.append('action', 'delete_reimbursement');
            fd.append('reimburse_id', id);
            const resp = await fetch(REIMBURSE_API, { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await resp.json();
            if (data.success) {
                if (typeof showToast === 'function') showToast(data.message, 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                if (typeof showToast === 'function') showToast(data.message || data.error, 'error');
                else alert(data.message || data.error);
            }
        } catch (err) { alert('Error: ' + err.message); }
    }

    async function updatePaymentStatus(id, newStatus) {
        const confirmMsg = newStatus === 'paid' 
            ? 'Tandai reimbursement ini sebagai SUDAH DIBAYAR?' 
            : 'Tandai reimbursement ini sebagai BELUM DIBAYAR?';
        if (!confirm(confirmMsg)) return;
        try {
            const fd = new FormData();
            fd.append('action', 'update_payment_status');
            fd.append('reimburse_id', id);
            fd.append('payment_status', newStatus);
            const resp = await fetch(REIMBURSE_API, { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await resp.json();
            if (data.success) {
                if (typeof showToast === 'function') showToast(data.message, 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                if (typeof showToast === 'function') showToast(data.message || data.error, 'error');
                else alert(data.message || data.error);
            }
        } catch (err) { alert('Error: ' + err.message); }
    }
</script>