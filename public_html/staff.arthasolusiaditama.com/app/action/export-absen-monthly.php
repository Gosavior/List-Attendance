<?php
@date_default_timezone_set('Asia/Jakarta');
error_reporting(E_ALL);
ini_set('display_errors', 0);

@ini_set('memory_limit', '256M');
@ini_set('max_execution_time', '120');

require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Library PhpSpreadsheet belum terinstall. Jalankan: composer require phpoffice/phpspreadsheet']);
    exit;
}
require_once $autoloadPath;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

requireLogin();

$stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$me) { http_response_code(403); die('User tidak ditemukan.'); }

$roleStr = strtolower(trim($me['role'] ?? ''));
$isAdmin = $roleStr && preg_match('/admin|administrator/', $roleStr);
$isTechnicianManager = $roleStr === 'technician_manager';
$isTechnician = $roleStr === 'technician';
if (!$isAdmin && !$isTechnicianManager && !$isTechnician) {
    http_response_code(403);
    die('Anda tidak memiliki akses untuk export data absensi.');
}

$month = trim($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) { $month = date('Y-m'); }
$start = $month . '-01';
$end = date('Y-m-t', strtotime($start));
$daysInMonth = (int)date('t', strtotime($start));
$monthNames = [1=>'JANUARI',2=>'FEBRUARI',3=>'MARET',4=>'APRIL',5=>'MEI',6=>'JUNI',7=>'JULI',8=>'AGUSTUS',9=>'SEPTEMBER',10=>'OKTOBER',11=>'NOVEMBER',12=>'DESEMBER'];
$monthNum = (int)date('n', strtotime($start));
$year = date('Y', strtotime($start));
$monthName = $monthNames[$monthNum];
$monthYearLabel = $monthName . ' ' . $year;

function safeSheetName($base, array &$used) {
    $title = trim((string)$base);
    
    $title = str_replace(['\\', '/', '*', '?', ':', '[', ']'], ' ', $title);
    $title = preg_replace('/\s+/', ' ', trim($title));
    if ($title === '') $title = 'User';
    $title = function_exists('mb_substr') ? mb_substr($title, 0, 31) : substr($title, 0, 31);
    $candidate = $title;
    $i = 1;
    while (isset($used[$candidate])) {
        $suffix = ' ' . $i;
        $baseLen = 31 - strlen($suffix);
        $candidate = (function_exists('mb_substr') ? mb_substr($title, 0, $baseLen) : substr($title, 0, $baseLen)) . $suffix;
        $i++;
    }
    $used[$candidate] = true;
    return $candidate;
}

function timeOnlyExport($dt) {
    return ($dt && $dt !== '0000-00-00 00:00:00') ? date('H:i', strtotime($dt)) : '-';
}

function totalHoursWorked($ci, $co) {
    if (!$ci || !$co || $ci === '0000-00-00 00:00:00' || $co === '0000-00-00 00:00:00') return '-';
    $inTs = strtotime($ci);
    $outTs = strtotime($co);
    if (!$inTs || !$outTs || $outTs < $inTs) return '-';
    $diffMin = (int)floor(($outTs - $inTs) / 60);
    return sprintf('%02d:%02d', intdiv($diffMin, 60), $diffMin % 60);
}

function fmtRupiah($amount) {
    return 'Rp ' . number_format((float)$amount, 0, ',', '.');
}

function assetAbsolutePath($rel) {
    $rel = trim((string)$rel);
    if ($rel === '' || strcasecmp($rel, 'null') === 0 || strcasecmp($rel, 'undefined') === 0) return null;
    $rel = ltrim($rel, '/\\');
    $root = realpath(dirname(__DIR__, 2));
    if (!$root) return null;
    if (strpos($rel, 'uploads/') === 0) $rel = 'public/assets/images/' . $rel;
    $full = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
    return file_exists($full) ? $full : null;
}

function addImageToSheet($sheet, $rel, $cell, $height = 80) {
    $path = assetAbsolutePath($rel);
    if (!$path || !@getimagesize($path)) return false;
    try {
        $drawing = new Drawing();
        $drawing->setPath($path);
        $drawing->setCoordinates($cell);
        $drawing->setHeight($height);
        $drawing->setOffsetX(8);
        $drawing->setOffsetY(8);
        $drawing->setWorksheet($sheet);
        return true;
    } catch (Throwable $e) {
        error_log('Export attendance image error: ' . $e->getMessage());
        return false;
    }
}

function detectKeyColumn(PDO $pdo) {
    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM company_settings');
        $cols = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { $cols[] = $r['Field']; }
        if (in_array('setting_key', $cols, true)) return 'setting_key';
        if (in_array('setting_name', $cols, true)) return 'setting_name';
    } catch (Throwable $e) {}
    return null;
}

$keyColumn = detectKeyColumn($pdo);
$workHoursByDay = [];
if ($keyColumn) {
    try {
        $stmtWh = $pdo->query("SELECT {$keyColumn} AS k, setting_value FROM company_settings WHERE {$keyColumn} LIKE 'work_hours_%'");
        while ($r = $stmtWh->fetch(PDO::FETCH_ASSOC)) {
            $idx = (int)str_replace('work_hours_', '', (string)$r['k']);
            $decoded = json_decode((string)$r['setting_value'], true);
            if (is_array($decoded)) $workHoursByDay[$idx] = $decoded;
        }
    } catch (Throwable $e) {}
}
$getScheduledStart = function($date) use ($workHoursByDay) {
    $dayIdx = (int)date('w', strtotime($date));
    $wh = $workHoursByDay[$dayIdx] ?? null;
    if (is_array($wh)) {
        $s = trim((string)($wh['start'] ?? ''));
        if ($s !== '' && strtolower($s) !== 'off' && preg_match('/^\d{2}:\d{2}$/', $s)) return $s;
    }
    return '08:30';
};

$userSql = "SELECT id, username, full_name, role FROM users WHERE is_active = 1 AND role NOT IN ('administrator','direktur','customer')";
$userParams = [];
if ($isTechnicianManager) {
    $userSql .= " AND role IN ('technician','technician_manager')";
} elseif ($isTechnician) {
    $userSql .= " AND id = ?";
    $userParams[] = (int)$_SESSION['user_id'];
}
$userSql .= " ORDER BY CASE WHEN role LIKE '%technician%' THEN 1 WHEN role LIKE '%sales%' THEN 2 ELSE 3 END, full_name ASC, username ASC";
$userStmt = $pdo->prepare($userSql);
$userStmt->execute($userParams);
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

$attendanceStmt = $pdo->prepare("SELECT a.* FROM attendances a WHERE a.attendance_date BETWEEN ? AND ? ORDER BY a.attendance_date, a.user_id");
$attendanceStmt->execute([$start, $end]);
$attendanceRows = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
$attendanceData = [];
foreach ($attendanceRows as $att) {
    $dateKey = (string)($att['attendance_date'] ?? '');
    $uid = (int)($att['user_id'] ?? 0);
    if ($dateKey === '' || $uid <= 0) continue;
    if (!isset($attendanceData[$dateKey][$uid])) { $attendanceData[$dateKey][$uid] = $att; continue; }
    $existing = $attendanceData[$dateKey][$uid];
    $existingHasCo = !empty($existing['check_out_time']) && $existing['check_out_time'] !== '0000-00-00 00:00:00';
    $newHasCo = !empty($att['check_out_time']) && $att['check_out_time'] !== '0000-00-00 00:00:00';
    if ($newHasCo && !$existingHasCo) $attendanceData[$dateKey][$uid] = $att;
}


$requestData = [];
try {
    $reqStmt = $pdo->prepare("SELECT user_id, attendance_date, request_type, reason, requested_check_in_time, requested_check_out_time, missed_checkout_date, created_at FROM attendance_requests WHERE status = 'approved' AND (attendance_date BETWEEN ? AND ? OR missed_checkout_date BETWEEN ? AND ?)");
    $reqStmt->execute([$start, $end, $start, $end]);
    while ($rr = $reqStmt->fetch(PDO::FETCH_ASSOC)) {
        $rUid = (int)($rr['user_id'] ?? 0);
        $rType = $rr['request_type'] ?? 'checkin';
        $rDate = ($rType === 'missed_checkout') ? ($rr['missed_checkout_date'] ?? $rr['attendance_date']) : $rr['attendance_date'];
        if ($rUid > 0 && $rDate) {
            $requestData[$rDate][$rUid] = $rr;
        }
    }
} catch (Throwable $e) {   }


try {
    $leaveColsStmt = $pdo->query("SHOW COLUMNS FROM leave_requests");
    $leaveCols = array_column($leaveColsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
} catch (Throwable $_e) {
    $leaveCols = [];
}
$leaveTypeSelect = in_array('type', $leaveCols, true) ? 'type' : "'permission' AS type";
$leaveStmt = $pdo->prepare("SELECT user_id, {$leaveTypeSelect}, reason, start_date, end_date FROM leave_requests WHERE status = 'approved' AND NOT (end_date < ? OR start_date > ?)");
$leaveStmt->execute([$start, $end]);
$leaveRows = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);
$leaveData = [];
foreach ($leaveRows as $leave) {
    $uid = (int)($leave['user_id'] ?? 0);
    if ($uid <= 0) continue;
    $s = max(strtotime($leave['start_date']), strtotime($start));
    $e = min(strtotime($leave['end_date']), strtotime($end));
    for ($t = $s; $t <= $e; $t = strtotime('+1 day', $t)) $leaveData[date('Y-m-d', $t)][$uid] = $leave;
}

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);
$usedSheetNames = [];
$headers = ['Bulan','Tanggal','Total Jam Masuk','Potongan Absensi','Jam Masuk','Jam Pulang','Foto Masuk','Foto Pulang','Noted'];

$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '2563EB']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];
$normalStyle = [
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];
$alphaStyle = $normalStyle;
$alphaStyle['font'] = ['bold' => true, 'color' => ['rgb' => 'FFFFFF']];
$alphaStyle['fill'] = ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'DC2626']];
$titleStyle = $headerStyle;
$titleStyle['fill'] = ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1E293B']];
$titleStyle['font'] = ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 14];

if (!$users) {
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle('Tidak Ada Data');
    $sheet->setCellValue('A1', 'Tidak ada user/data absensi.');
} else {
    foreach ($users as $u) {
        $uid = (int)$u['id'];
        $fullName = trim((string)($u['full_name'] ?: $u['username']));
        $roleUser = strtolower(trim((string)($u['role'] ?? '')));
        $isUserTechnician = (strpos($roleUser, 'technician') !== false);

        $sheet = $spreadsheet->createSheet();
        
        if ($fullName === '') {
            $fullName = trim((string)($u['username'] ?? ''));
        }
        if ($fullName === '') {
            $fullName = 'User_' . $uid;
        }
        $sheetTabName = safeSheetName($fullName, $usedSheetNames);
        try {
            $sheet->setTitle($sheetTabName);
        } catch (\Throwable $e) {
            error_log('[EXPORT] setTitle error for "' . $sheetTabName . '": ' . $e->getMessage());
            try {
                $sheet->setTitle('User_' . $uid);
            } catch (\Throwable $e2) {
                
            }
        }
        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', $fullName);
        $sheet->getStyle('A1:I1')->applyFromArray($titleStyle);
        $sheet->fromArray($headers, null, 'A2');
        $sheet->getStyle('A2:I2')->applyFromArray($headerStyle);
        $sheet->mergeCells('A3:A' . (2 + $daysInMonth));
        $sheet->setCellValue('A3', $monthYearLabel);
        $sheet->getStyle('A3:A' . (2 + $daysInMonth))->applyFromArray($normalStyle);
        $sheet->getStyle('A3')->getFont()->setBold(true);

        $row = 3;
        for ($day = 1; $day <= $daysInMonth; $day++, $row++) {
            $dateKey = sprintf('%s-%02d', $month, $day);
            $att = $attendanceData[$dateKey][$uid] ?? null;
            $leave = $leaveData[$dateKey][$uid] ?? null;
            $status = strtolower(trim((string)($att['status'] ?? '')));
            $isAlpha = ($status === 'alpha') || (!$att && !$leave);

            $sheet->setCellValue('B'.$row, sprintf('%02d', $day));
            if ($isAlpha) {
                $sheet->mergeCells('C'.$row.':I'.$row);
                $sheet->setCellValue('C'.$row, 'Tidak Hadir tanpa Keterangan');
                $sheet->getStyle('B'.$row.':I'.$row)->applyFromArray($alphaStyle);
            } else {
                $checkIn = $att['check_in_time'] ?? null;
                $checkOut = $att['check_out_time'] ?? null;
                $deduction = '-';
                if ($isUserTechnician && $checkIn && $checkIn !== '0000-00-00 00:00:00') {
                    $scheduledStart = $getScheduledStart($dateKey);
                    $scheduledTs = strtotime($dateKey . ' ' . $scheduledStart . ':00');
                    $checkInTs = strtotime($checkIn);
                    $deduction = ($scheduledTs && $checkInTs && $checkInTs > $scheduledTs)
                        ? fmtRupiah((int)ceil(($checkInTs - $scheduledTs) / 60) * 1500)
                        : fmtRupiah(0);
                }
                $sheet->setCellValue('C'.$row, totalHoursWorked($checkIn, $checkOut));
                $sheet->setCellValue('D'.$row, $deduction);
                $sheet->setCellValue('E'.$row, timeOnlyExport($checkIn));
                $sheet->setCellValue('F'.$row, timeOnlyExport($checkOut));
                $sheet->getStyle('B'.$row.':I'.$row)->applyFromArray($normalStyle);
                if (!empty($att['check_in_photo'])) addImageToSheet($sheet, $att['check_in_photo'], 'G'.$row, 80);
                if (!empty($att['check_out_photo'])) addImageToSheet($sheet, $att['check_out_photo'], 'H'.$row, 80);

                
                $reqRow = $requestData[$dateKey][$uid] ?? null;
                if ($reqRow) {
                    $rType = $reqRow['request_type'] ?? 'checkin';
                    if ($rType === 'missed_checkout') {
                        $noted = 'Request Pulang (' . substr($reqRow['requested_check_out_time'] ?? '', 0, 5) . ') - ' . ($reqRow['reason'] ?? '');
                    } else {
                        $noted = 'Request Masuk (' . substr($reqRow['requested_check_in_time'] ?? '', 0, 5) . ') - ' . ($reqRow['reason'] ?? '');
                    }
                    $sheet->setCellValue('I'.$row, $noted);
                    $sheet->getStyle('I'.$row)->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF6B21'));
                }
            }
            $sheet->getRowDimension($row)->setRowHeight(90);
        }

        foreach (['A'=>18,'B'=>10,'C'=>18,'D'=>22,'E'=>12,'F'=>12,'G'=>20,'H'=>20,'I'=>35] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(2)->setRowHeight(28);
    }
}

$spreadsheet->setActiveSheetIndex(0);
$filename = 'Rekap_Absensi_Per_User_' . $monthName . '_' . $year . '_' . date('Ymd_His') . '.xlsx';

while (ob_get_level() > 0) { @ob_end_clean(); }
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
