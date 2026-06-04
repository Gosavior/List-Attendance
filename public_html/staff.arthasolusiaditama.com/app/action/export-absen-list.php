<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/auth.php';
require_once '../config/database.php';


if (!isset($_SESSION['user_id'])) {
    die("Anda belum login.");
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) die("User tidak ditemukan.");

$allowed_roles = ['administrator', 'technician_manager'];
if (!in_array($user['role'], $allowed_roles)) {
    die("Akses ditolak.");
}


require __DIR__ . '/../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;


function getPhotoPath($filepath) {
    if (empty($filepath)) return null;
    
    $filepath = ltrim($filepath, '/\\');
    
    if (strpos($filepath, 'storage/') === 0) {
        $baseRoot = realpath(dirname(__DIR__, 2));
        if (!$baseRoot) {
            error_log("Base root not found for storage path");
            return null;
        }
        $fullPath = $baseRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filepath);
        if (file_exists($fullPath)) return $fullPath;
        error_log("File tidak ditemukan (storage): " . $fullPath);
        return null;
    }
    
    if (strpos($filepath, 'public/') === 0) {
        $filepath = substr($filepath, strlen('public/'));
    }
    
    
    $basePublic = realpath(dirname(__DIR__, 2) . '/public');
    if (!$basePublic) {
        error_log("Base public folder tidak ditemukan");
        return null;
    }
    $fullPath = $basePublic . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filepath);
    if (file_exists($fullPath)) return $fullPath;
    error_log("File tidak ditemukan: " . $fullPath);
    return null;
}


$search = trim($_GET['search'] ?? '');
$selectedDate = isset($_GET['date']) && $_GET['date'] ? $_GET['date'] : date('Y-m-d');

if ($search !== '') {
    $stmt = $pdo->prepare("
        SELECT a.*, u.full_name, u.role, u.id as user_id
        FROM attendances a 
        JOIN users u ON a.user_id = u.id 
        WHERE u.full_name LIKE ?
        ORDER BY a.attendance_date DESC
    ");
    $stmt->execute(['%' . $search . '%']);
} else {
    $stmt = $pdo->prepare("
        SELECT a.*, u.full_name, u.role, u.id as user_id
        FROM attendances a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.attendance_date = ?
        ORDER BY a.check_in_time ASC
    ");
    $stmt->execute([$selectedDate]);
}

$attendances = $stmt->fetchAll(PDO::FETCH_ASSOC);


foreach ($attendances as &$att) {
    $stmtAct = $pdo->prepare("SELECT photo_path FROM attendance_activities WHERE attendance_id = ? LIMIT 5");
    $stmtAct->execute([$att['id']]);
    $att['activity_photos'] = $stmtAct->fetchAll(PDO::FETCH_COLUMN);
}
unset($att);


$attIds = array_filter(array_column($attendances, 'id'));
$requestMap = [];
if (!empty($attIds)) {
    $inQuery = implode(',', array_fill(0, count($attIds), '?'));
    try {
        
        $stmtReq = $pdo->prepare("SELECT attendance_id, user_id, attendance_date, request_type, reason, requested_check_in_time, requested_check_out_time, missed_checkout_date, created_at FROM attendance_requests WHERE status = 'approved' AND attendance_id IN ($inQuery)");
        $stmtReq->execute($attIds);
        while ($row = $stmtReq->fetch(PDO::FETCH_ASSOC)) {
            $requestMap[$row['attendance_id']] = $row;
        }
    } catch (Exception $e) {   }
}

foreach ($attendances as &$att) {
    if (empty($requestMap[$att['id']])) {
        try {
            $stmtReq2 = $pdo->prepare("SELECT attendance_id, user_id, attendance_date, request_type, reason, requested_check_in_time, requested_check_out_time, missed_checkout_date, created_at FROM attendance_requests WHERE status = 'approved' AND user_id = ? AND (attendance_date = ? OR missed_checkout_date = ?) LIMIT 1");
            $stmtReq2->execute([$att['user_id'], $att['attendance_date'], $att['attendance_date']]);
            $reqRow = $stmtReq2->fetch(PDO::FETCH_ASSOC);
            if ($reqRow) {
                $requestMap[$att['id']] = $reqRow;
            }
        } catch (Exception $e) {   }
    }
    $att['attendance_request'] = $requestMap[$att['id']] ?? null;
}
unset($att);

function safeSheetName($base, array &$used) {
    $title = preg_replace('/[\\\/*?:\[\]]+/', ' ', trim((string)$base));
    $title = preg_replace('/\s+/', ' ', $title);
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


$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

$headers = [
    "No", "Nama", "Role", "Tanggal", "Jam Masuk", "Jam Pulang", "Status", "Plan", "Catatan", "Request",
    "Foto Masuk", "Foto Pulang",
    "Foto Activity 1", "Foto Activity 2", "Foto Activity 3", "Foto Activity 4", "Foto Activity 5"
];

$headerStyle = [
    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'color' => ['rgb' => '4F81BD']],
    'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
];

$attendancesByUser = [];
foreach ($attendances as $attendance) {
    $userId = (int)($attendance['user_id'] ?? 0);
    $attendancesByUser[$userId][] = $attendance;
}

$usedSheetNames = [];
if (empty($attendancesByUser)) {
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle('Tidak Ada Data');
    $sheet->fromArray($headers, null, 'A1');
    $sheet->getStyle('A1:Q1')->applyFromArray($headerStyle);
    $sheet->setCellValue('A2', 'Tidak ada data absensi.');
} else {
foreach ($attendancesByUser as $userAttendances) {
$firstAttendance = $userAttendances[0] ?? [];
$sheet = $spreadsheet->createSheet();
$sheet->setTitle(safeSheetName($firstAttendance['full_name'] ?? 'User', $usedSheetNames));
$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:Q1')->applyFromArray($headerStyle);


$row = 2;
foreach ($userAttendances as $index => $a) {
    $sheet->setCellValue('A'.$row, $index+1);
    $sheet->setCellValue('B'.$row, $a['full_name']);
    $sheet->setCellValue('C'.$row, ucfirst($a['role']));
    $sheet->setCellValue('D'.$row, $a['attendance_date']);
    $sheet->setCellValue('E'.$row, $a['check_in_time'] ? date('H:i', strtotime($a['check_in_time'])) : '-');
    $sheet->setCellValue('F'.$row, $a['check_out_time'] ? date('H:i', strtotime($a['check_out_time'])) : '-');
    $sheet->setCellValue('G'.$row, $a['status']);
    $sheet->setCellValue('H'.$row, $a['today_plan']);
    $sheet->setCellValue('I'.$row, $a['notes']);

    
    $reqInfo = '';
    if (!empty($a['attendance_request'])) {
        $ar = $a['attendance_request'];
        $reqType = $ar['request_type'] ?? 'checkin';
        if ($reqType === 'missed_checkout') {
            $reqInfo = 'Request Pulang (' . substr($ar['requested_check_out_time'] ?? '', 0, 5) . ') - ' . ($ar['reason'] ?? '');
        } else {
            $reqInfo = 'Request Masuk (' . substr($ar['requested_check_in_time'] ?? '', 0, 5) . ') - ' . ($ar['reason'] ?? '');
        }
        $reqInfo .= ' [' . date('d/m/Y H:i', strtotime($ar['created_at'])) . ']';
    }
    $sheet->setCellValue('J'.$row, $reqInfo);

    
    $sheet->getStyle('A'.$row.':J'.$row)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);

    
    if (!empty($a['check_in_photo'])) {
        $fotoPath = getPhotoPath($a['check_in_photo']);
        if ($fotoPath) {
            try {
                $drawing = new Drawing();
                $drawing->setName('Check In Photo');
                $drawing->setDescription('Foto Masuk');
                $drawing->setPath($fotoPath);
                $drawing->setCoordinates('K'.$row);
                $drawing->setHeight(80);
                $drawing->setOffsetX(10);
                $drawing->setOffsetY(10);
                $drawing->setWorksheet($sheet);
                
                $sheet->getStyle('K'.$row)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
            } catch (Exception $e) {
                error_log("Error foto masuk: " . $e->getMessage());
                $sheet->setCellValue('K'.$row, 'Foto Masuk');
            }
        } else {
            $sheet->setCellValue('K'.$row, 'Foto tidak ditemukan');
        }
    } else {
        $sheet->setCellValue('K'.$row, 'Tidak ada foto');
    }

    
    if (!empty($a['check_out_photo'])) {
        $fotoPath = getPhotoPath($a['check_out_photo']);
        if ($fotoPath) {
            try {
                $drawing = new Drawing();
                $drawing->setName('Check Out Photo');
                $drawing->setDescription('Foto Pulang');
                $drawing->setPath($fotoPath);
                $drawing->setCoordinates('L'.$row);
                $drawing->setHeight(80);
                $drawing->setOffsetX(10);
                $drawing->setOffsetY(10);
                $drawing->setWorksheet($sheet);
                
                $sheet->getStyle('L'.$row)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
            } catch (Exception $e) {
                error_log("Error foto pulang: " . $e->getMessage());
                $sheet->setCellValue('L'.$row, 'Foto Pulang');
            }
        } else {
            $sheet->setCellValue('L'.$row, 'Foto tidak ditemukan');
        }
    } else {
        $sheet->setCellValue('L'.$row, 'Tidak ada foto');
    }

    
    for ($i = 0; $i < 5; $i++) {
        $col = chr(ord('M') + $i); 
        if (!empty($a['activity_photos'][$i])) {
            $fotoPath = getPhotoPath($a['activity_photos'][$i]);
            if ($fotoPath) {
                try {
                    $drawing = new Drawing();
                    $drawing->setName('Activity Photo '.($i+1));
                    $drawing->setDescription('Foto Aktivitas');
                    $drawing->setPath($fotoPath);
                    $drawing->setCoordinates($col.$row);
                    $drawing->setHeight(60);
                    $drawing->setOffsetX(10);
                    $drawing->setOffsetY(10);
                    $drawing->setWorksheet($sheet);
                } catch (Exception $e) {
                    error_log("Error foto activity: " . $e->getMessage());
                    $sheet->setCellValue($col.$row, 'Foto error');
                }
            } else {
                $sheet->setCellValue($col.$row, 'Foto tidak ditemukan');
            }
        } else {
            $sheet->setCellValue($col.$row, 'Tidak ada foto aktivitas');
        }
    }
    

    
    $sheet->getRowDimension($row)->setRowHeight(85);
    $row++;

    
    if (!empty($a['attendance_request'])) {
        $ar = $a['attendance_request'];
        $reqType = $ar['request_type'] ?? 'checkin';
        if ($reqType === 'missed_checkout') {
            $noteText = 'NOTED: Request Pulang - Jam: ' . substr($ar['requested_check_out_time'] ?? '', 0, 5) . ' | Alasan: ' . ($ar['reason'] ?? '-') . ' | Tanggal request: ' . date('d/m/Y H:i', strtotime($ar['created_at']));
        } else {
            $noteText = 'NOTED: Request Masuk - Jam: ' . substr($ar['requested_check_in_time'] ?? '', 0, 5) . ' | Alasan: ' . ($ar['reason'] ?? '-') . ' | Tanggal request: ' . date('d/m/Y H:i', strtotime($ar['created_at']));
        }
        $sheet->mergeCells('A'.$row.':Q'.$row);
        $sheet->setCellValue('A'.$row, $noteText);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF6B21'));
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(25);
        $row++;
    }
}


foreach (range('A', 'J') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}


foreach(['K','L','M','N','O','P','Q'] as $col) {
    $sheet->getColumnDimension($col)->setWidth(20);
}


$sheet->getStyle('A1:Q1')->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);
}
}
$spreadsheet->setActiveSheetIndex(0);


$filename = "Data_Absen_" . date('Ymd_His') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;