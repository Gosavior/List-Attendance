<?php
/**
 * Export Summary Presence Data - Monthly attendance summary per employee
 * Accessible by: administrator, direktur
 * 
 * Settings used (from company_settings):
 * - summary_work_days: JSON array of working days [1,2,3,4,5] (1=Mon, 7=Sun). Default: Mon-Fri
 * - summary_break_minutes: Break/lunch duration in minutes. Default: 60
 * - summary_excluded_users: JSON array of user IDs to exclude from summary
 * - summary_auto_present_users: JSON array of user IDs that are always marked as present
 */
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
    echo json_encode(['success' => false, 'message' => 'Library PhpSpreadsheet belum terinstall.']);
    exit;
}
require_once $autoloadPath;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

requireLogin();

// Only admin and direktur can access
$stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$me) { http_response_code(403); die('User tidak ditemukan.'); }

$roleStr = strtolower(trim($me['role'] ?? ''));
if (!in_array($roleStr, ['administrator', 'direktur'])) {
    http_response_code(403);
    die('Hanya administrator dan direktur yang dapat mengakses export ini.');
}

// Parse month parameter
$month = trim($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) { $month = date('Y-m'); }
$start = $month . '-01';
$endOfMonth = date('Y-m-t', strtotime($start));
$today = date('Y-m-d');
// If current month, only count up to today. If past month, count full month.
$end = ($month === date('Y-m')) ? $today : $endOfMonth;
$daysInMonth = (int)date('t', strtotime($start));
$daysToCount = (int)date('j', strtotime($end)); // actual days to calculate
$monthNum = (int)date('n', strtotime($start));
$year = (int)date('Y', strtotime($start));

$monthNames = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
$monthName = $monthNames[$monthNum];

// ============================================================
// Load settings from company_settings
// ============================================================
function detectKeyCol(PDO $pdo): ?string {
    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM company_settings');
        $cols = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { $cols[] = $r['Field']; }
        if (in_array('setting_key', $cols, true)) return 'setting_key';
        if (in_array('setting_name', $cols, true)) return 'setting_name';
    } catch (Throwable $e) {}
    return null;
}

$keyColumn = detectKeyCol($pdo);

function getSetting(PDO $pdo, ?string $keyColumn, string $key, $default = null) {
    if (!$keyColumn) return $default;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM company_settings WHERE {$keyColumn} = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (Throwable $e) { return $default; }
}

// Work hours config
$defaultWorkStart = substr(getSetting($pdo, $keyColumn, 'work_start_time', '08:00:00'), 0, 5);
$defaultWorkEnd = substr(getSetting($pdo, $keyColumn, 'work_end_time', '17:30:00'), 0, 5);

// Summary-specific settings
$summaryWorkDays = json_decode(getSetting($pdo, $keyColumn, 'summary_work_days', '[1,2,3,4,5]'), true);
if (!is_array($summaryWorkDays) || empty($summaryWorkDays)) $summaryWorkDays = [1,2,3,4,5]; // Mon-Fri

$summaryBreakMinutes = (int)getSetting($pdo, $keyColumn, 'summary_break_minutes', '60');
if ($summaryBreakMinutes < 0) $summaryBreakMinutes = 60;

$summaryExcludedUsers = json_decode(getSetting($pdo, $keyColumn, 'summary_excluded_users', '[]'), true);
if (!is_array($summaryExcludedUsers)) $summaryExcludedUsers = [];

$summaryAutoPresentUsers = json_decode(getSetting($pdo, $keyColumn, 'summary_auto_present_users', '[]'), true);
if (!is_array($summaryAutoPresentUsers)) $summaryAutoPresentUsers = [];

// Calculate effective daily work hours: 9 hours total - break = 8 hours effective
$dailyEffectiveMinutes = (9 * 60) - $summaryBreakMinutes;
if ($dailyEffectiveMinutes <= 0) $dailyEffectiveMinutes = 480; // fallback 8 hours

// Determine if a date is a working day based on summary_work_days setting
function isWorkingDay(string $date, array $workDays): bool {
    $dow = (int)date('N', strtotime($date)); // 1=Mon, 7=Sun
    return in_array($dow, $workDays, true);
}

// Get all users (active, exclude customer and excluded users)
$userSql = "SELECT id, full_name, username, role FROM users WHERE is_active = 1 AND role NOT IN ('customer')";
$userParams = [];
if (!empty($summaryExcludedUsers)) {
    $placeholders = implode(',', array_fill(0, count($summaryExcludedUsers), '?'));
    $userSql .= " AND id NOT IN ($placeholders)";
    $userParams = array_map('intval', $summaryExcludedUsers);
}
$userSql .= " ORDER BY full_name ASC, username ASC";
$userStmt = $pdo->prepare($userSql);
$userStmt->execute($userParams);
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all attendance records for the month
$attStmt = $pdo->prepare("SELECT * FROM attendances WHERE attendance_date BETWEEN ? AND ? ORDER BY attendance_date, user_id");
$attStmt->execute([$start, $end]);
$attRows = $attStmt->fetchAll(PDO::FETCH_ASSOC);

// Index attendance by date and user_id
$attendanceData = [];
foreach ($attRows as $att) {
    $dateKey = (string)($att['attendance_date'] ?? '');
    $uid = (int)($att['user_id'] ?? 0);
    if ($dateKey === '' || $uid <= 0) continue;
    if (!isset($attendanceData[$dateKey][$uid])) {
        $attendanceData[$dateKey][$uid] = $att;
    } else {
        $existing = $attendanceData[$dateKey][$uid];
        $existingHasCo = !empty($existing['check_out_time']) && $existing['check_out_time'] !== '0000-00-00 00:00:00';
        $newHasCo = !empty($att['check_out_time']) && $att['check_out_time'] !== '0000-00-00 00:00:00';
        if ($newHasCo && !$existingHasCo) $attendanceData[$dateKey][$uid] = $att;
    }
}

// Get leave data
try {
    $leaveColsStmt = $pdo->query("SHOW COLUMNS FROM leave_requests");
    $leaveCols = array_column($leaveColsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
} catch (Throwable $_e) { $leaveCols = []; }
$leaveTypeSelect = in_array('type', $leaveCols, true) ? 'type' : "'permission' AS type";
$leaveStmt = $pdo->prepare("SELECT user_id, {$leaveTypeSelect}, start_date, end_date FROM leave_requests WHERE status = 'approved' AND NOT (end_date < ? OR start_date > ?)");
$leaveStmt->execute([$start, $end]);
$leaveRows = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);

$leaveData = [];
foreach ($leaveRows as $leave) {
    $uid = (int)($leave['user_id'] ?? 0);
    if ($uid <= 0) continue;
    $s = max(strtotime($leave['start_date']), strtotime($start));
    $e = min(strtotime($leave['end_date']), strtotime($end));
    for ($t = $s; $t <= $e; $t = strtotime('+1 day', $t)) {
        $leaveData[date('Y-m-d', $t)][$uid] = strtolower($leave['type'] ?? 'permission');
    }
}

// Get cuti data
$cutiStmt = $pdo->prepare("SELECT user_id, start_date, end_date FROM cuti_requests WHERE status IN ('approved','admin_approved','manager_approved') AND year = ? AND NOT (end_date < ? OR start_date > ?)");
$cutiStmt->execute([$year, $start, $end]);
$cutiRows = $cutiStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($cutiRows as $cuti) {
    $uid = (int)($cuti['user_id'] ?? 0);
    if ($uid <= 0) continue;
    $s = max(strtotime($cuti['start_date']), strtotime($start));
    $e = min(strtotime($cuti['end_date']), strtotime($end));
    for ($t = $s; $t <= $e; $t = strtotime('+1 day', $t)) {
        $leaveData[date('Y-m-d', $t)][$uid] = 'cuti';
    }
}

// Calculate weekdays and non-working days up to the end date (today or end of month)
$totalWeekdays = 0;
$totalNonWorkingDays = 0;
for ($day = 1; $day <= $daysToCount; $day++) {
    $dateStr = sprintf('%s-%02d', $month, $day);
    if (isWorkingDay($dateStr, $summaryWorkDays)) {
        $totalWeekdays++;
    } else {
        $totalNonWorkingDays++;
    }
}

// Standard office hours (effective hours after break deduction)
$standardOfficeHours = ($totalWeekdays * $dailyEffectiveMinutes) / 60;

// Build summary data per user
$summaryData = [];
foreach ($users as $u) {
    $uid = (int)$u['id'];
    $isAutoPresent = in_array($uid, $summaryAutoPresentUsers, true);

    $row = [
        'name' => trim($u['full_name'] ?: $u['username']),
        'division' => ucfirst($u['role'] ?? ''),
        'days_in_month' => $daysToCount,
        'weekdays' => $totalWeekdays,
        'attend_weekdays' => 0,
        'attend_not_weekdays' => 0,
        'office_hours' => $standardOfficeHours,
        'working_hours_min' => 0,
        'overtime_min' => 0,
        'late_days' => 0,
        'late_minutes' => 0,
        'non_working_days' => $totalNonWorkingDays,
        'leave' => 0,
        'half_day_leave' => 0,
        'sick' => 0,
        'permission' => 0,
        'absent' => 0,
    ];

    for ($day = 1; $day <= $daysToCount; $day++) {
        $dateStr = sprintf('%s-%02d', $month, $day);

        if (!isWorkingDay($dateStr, $summaryWorkDays)) {
            continue; // Non-working day
        }

        // Auto-present users: count as full attendance every working day
        if ($isAutoPresent) {
            $row['attend_weekdays']++;
            $row['working_hours_min'] += $dailyEffectiveMinutes;
            continue;
        }

        $att = $attendanceData[$dateStr][$uid] ?? null;
        $leaveType = $leaveData[$dateStr][$uid] ?? null;
        $status = strtolower(trim((string)($att['status'] ?? '')));

        if ($leaveType === 'cuti' || $status === 'cuti') {
            $row['leave']++;
        } elseif ($leaveType === 'sick' || $status === 'sakit') {
            $row['sick']++;
        } elseif ($leaveType === 'permission' || $status === 'izin') {
            $row['permission']++;
        } elseif ($att && in_array($status, ['hadir', 'terlambat', 'lembur', 'pulang cepat', 'not checked out'])) {
            $row['attend_weekdays']++;

            // Calculate working hours
            $checkIn = $att['check_in_time'] ?? null;
            $checkOut = $att['check_out_time'] ?? null;
            if ($checkIn && $checkOut && $checkIn !== '0000-00-00 00:00:00' && $checkOut !== '0000-00-00 00:00:00') {
                $inTs = strtotime($checkIn);
                $outTs = strtotime($checkOut);
                if ($inTs && $outTs && $outTs > $inTs) {
                    $workedMin = (int)floor(($outTs - $inTs) / 60) - $summaryBreakMinutes;
                    if ($workedMin < 0) $workedMin = 0;
                    $row['working_hours_min'] += $workedMin;
                }
            }

            // Overtime
            $overtimeHours = (float)($att['overtime_hours'] ?? 0);
            if ($overtimeHours > 0) {
                $row['overtime_min'] += (int)round($overtimeHours * 60);
            }

            // Late check
            if ($status === 'terlambat') {
                $row['late_days']++;
                if ($checkIn && $checkIn !== '0000-00-00 00:00:00') {
                    $scheduledStart = strtotime($dateStr . ' ' . $defaultWorkStart . ':00');
                    $checkInTs = strtotime($checkIn);
                    if ($scheduledStart && $checkInTs && $checkInTs > $scheduledStart) {
                        $row['late_minutes'] += (int)ceil(($checkInTs - $scheduledStart) / 60);
                    }
                }
            }

            // Half day leave: check-in from 12:00 or later
            if ($checkIn && $checkIn !== '0000-00-00 00:00:00') {
                $checkInHour = (int)date('H', strtotime($checkIn));
                if ($checkInHour >= 12) {
                    $row['half_day_leave']++;
                }
            }
        } else {
            // Not present on a working day without leave = Absent (Alpha)
            $row['absent']++;
            $row['attend_not_weekdays']++;
        }
    }

    $summaryData[] = $row;
}

// ============================================================
// Create Excel
// ============================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Summary Presence');

// Title rows
$periodStart = '1 ' . $monthName . ' ' . $year;
$periodEnd = $daysToCount . ' ' . $monthName . ' ' . $year;

$sheet->mergeCells('A1:R1');
$sheet->setCellValue('A1', "Summary Presence Data, $periodStart - $periodEnd");
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->mergeCells('A2:R2');
$sheet->setCellValue('A2', 'PT. Artha Solusi Aditama');
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Headers (row 4)
$headers = [
    'NO',
    'Employee Name',
    'Division',
    'Number Of Days',
    'Weekdays',
    'Attend Weekdays',
    'Attend Not Weekdays',
    'Office Hours',
    'Working Hours',
    'Overtime Duration',
    'Late (days)',
    'Late (hours)',
    'Non Working Day',
    'Leave',
    'Half Day Leave',
    'Sick',
    'Permission',
    'Absent',
];

$headerRow = 4;
foreach ($headers as $colIdx => $header) {
    $colLetter = chr(65 + $colIdx);
    $sheet->setCellValue($colLetter . $headerRow, $header);
}

// Header style
$headerRange = 'A' . $headerRow . ':R' . $headerRow;
$sheet->getStyle($headerRange)->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1E3A8A']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
]);

// Data rows
$dataRow = $headerRow + 1;
$no = 1;
foreach ($summaryData as $data) {
    $workingHoursFormatted = intdiv($data['working_hours_min'], 60) . ' Hours ' . ($data['working_hours_min'] % 60) . ' Minutes';
    $overtimeFormatted = intdiv($data['overtime_min'], 60) . ' Hours ' . ($data['overtime_min'] % 60) . ' Minutes';
    $lateHoursFormatted = intdiv($data['late_minutes'], 60) . ' Hours ' . ($data['late_minutes'] % 60) . ' Minutes';

    $sheet->setCellValue('A' . $dataRow, $no);
    $sheet->setCellValue('B' . $dataRow, $data['name']);
    $sheet->setCellValue('C' . $dataRow, $data['division']);
    $sheet->setCellValue('D' . $dataRow, $data['days_in_month']);
    $sheet->setCellValue('E' . $dataRow, $data['weekdays']);
    $sheet->setCellValue('F' . $dataRow, $data['attend_weekdays']);
    $sheet->setCellValue('G' . $dataRow, $data['attend_not_weekdays']);
    $officeHoursH = (int)floor($data['office_hours']);
    $officeHoursM = (int)round(($data['office_hours'] - $officeHoursH) * 60);
    $sheet->setCellValue('H' . $dataRow, $officeHoursH . ' Hours ' . $officeHoursM . ' Minutes');
    $sheet->setCellValue('I' . $dataRow, $workingHoursFormatted);
    $sheet->setCellValue('J' . $dataRow, $overtimeFormatted);
    $sheet->setCellValue('K' . $dataRow, $data['late_days']);
    $sheet->setCellValue('L' . $dataRow, $lateHoursFormatted);
    $sheet->setCellValue('M' . $dataRow, $data['non_working_days']);
    $sheet->setCellValue('N' . $dataRow, $data['leave']);
    $sheet->setCellValue('O' . $dataRow, $data['half_day_leave']);
    $sheet->setCellValue('P' . $dataRow, $data['sick']);
    $sheet->setCellValue('Q' . $dataRow, $data['permission']);
    $sheet->setCellValue('R' . $dataRow, $data['absent']);

    $no++;
    $dataRow++;
}

// Data style
$lastDataRow = $dataRow - 1;
if ($lastDataRow >= $headerRow + 1) {
    $dataRange = 'A' . ($headerRow + 1) . ':R' . $lastDataRow;
    $sheet->getStyle($dataRange)->applyFromArray([
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ]);
    $sheet->getStyle('B' . ($headerRow + 1) . ':C' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
}

// Column widths
$colWidths = ['A'=>5, 'B'=>25, 'C'=>18, 'D'=>12, 'E'=>12, 'F'=>14, 'G'=>16, 'H'=>13, 'I'=>13, 'J'=>14, 'K'=>11, 'L'=>12, 'M'=>14, 'N'=>8, 'O'=>13, 'P'=>8, 'Q'=>12, 'R'=>9];
foreach ($colWidths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// Freeze pane
$sheet->freezePane('A5');

// Output
$filename = 'Summary_Presence_Data_' . $monthName . '_' . $year . '_' . date('Ymd_His') . '.xlsx';

while (ob_get_level() > 0) { @ob_end_clean(); }
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
