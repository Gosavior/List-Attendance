<?php

require_once __DIR__ . '/../config/database.php';

try {
    
    $pdo->exec("
        ALTER TABLE attendances 
        ADD COLUMN IF NOT EXISTS overtime_hours DECIMAL(4,2) DEFAULT 0 COMMENT 'Hours worked beyond normal hours'
    ");
    echo "[OK] Added overtime_hours column to attendances table\n";
    
    
    $pdo->exec("
        ALTER TABLE attendances 
        ADD COLUMN IF NOT EXISTS check_in_latitude DECIMAL(10,8) NULL COMMENT 'Check-in GPS latitude',
        ADD COLUMN IF NOT EXISTS check_in_longitude DECIMAL(11,8) NULL COMMENT 'Check-in GPS longitude',
        ADD COLUMN IF NOT EXISTS check_out_latitude DECIMAL(10,8) NULL COMMENT 'Check-out GPS latitude',
        ADD COLUMN IF NOT EXISTS check_out_longitude DECIMAL(11,8) NULL COMMENT 'Check-out GPS longitude',
        ADD COLUMN IF NOT EXISTS location_accuracy FLOAT NULL COMMENT 'GPS accuracy in meters'
    ");
    echo "[OK] Added GPS location columns to attendances table\n";
    
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS company_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            setting_name VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL,
            description TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "[OK] Created company_settings table\n";
    
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS company_holidays (
            id INT PRIMARY KEY AUTO_INCREMENT,
            holiday_date DATE NOT NULL UNIQUE,
            holiday_name VARCHAR(255) NOT NULL,
            holiday_type ENUM('national', 'company', 'weekend_override') DEFAULT 'company',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_holiday_date (holiday_date),
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
    echo "[OK] Created company_holidays table\n";
    
    
    $pdo->exec("
        ALTER TABLE attendances 
        MODIFY COLUMN status ENUM('Hadir', 'Terlambat', 'Pulang Cepat', 'Alpha', 'Izin', 'Sakit', 'Cuti', 'Libur', 'Lembur', 'Not Checked Out') DEFAULT 'Hadir'
    ");
    echo "[OK] Updated attendances status enum (Lembur as the only overtime label)\n";

    
    $stmt = $pdo->prepare("
        UPDATE attendances
        SET status = CASE
            WHEN status IN ('Overtime', 'overtime') THEN 'Lembur'
            WHEN status = 'Late' THEN 'Terlambat'
            ELSE status
        END
        WHERE status IN ('Overtime', 'overtime', 'Late')
    ");
    $stmt->execute();
    $normalizedLegacyStatus = $stmt->rowCount();
    echo "[OK] Normalized legacy status labels for $normalizedLegacyStatus records\n";
    
    
    $timeRules = [
        ['work_start_time', '08:30:00', 'Normal work start time'],
        ['work_end_time', '17:30:00', 'Normal work end time'],
        ['late_threshold', '08:30:00', 'Time after which attendance is marked as late'],
        ['overtime_start', '19:00:00', 'Time after which work is considered overtime'],
        ['office_latitude', '-6.2088', 'Office GPS latitude (Jakarta default)'],
        ['office_longitude', '106.8456', 'Office GPS longitude (Jakarta default)'],
        ['attendance_radius', '100', 'Allowed attendance radius in meters'],
        ['weekend_work_allowed', '0', 'Whether staff can attend on weekends (0=No, 1=Yes)']
    ];
    
    foreach ($timeRules as [$name, $value, $description]) {
        $stmt = $pdo->prepare("
            INSERT INTO company_settings (setting_name, setting_value, description) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value),
            description = VALUES(description)
        ");
        $stmt->execute([$name, $value, $description]);
    }
    echo "[OK] Inserted default time rules configuration\n";
    
    
    $pdo->exec("
        ALTER TABLE attendances 
        ADD INDEX IF NOT EXISTS idx_user_date (user_id, attendance_date),
        ADD INDEX IF NOT EXISTS idx_status (status),
        ADD INDEX IF NOT EXISTS idx_check_times (check_in_time, check_out_time)
    ");
    echo "[OK] Added database indexes for performance\n";
    
    
    echo "\n=== Updating Existing Records ===\n";
    
    
    $stmt = $pdo->prepare("
        UPDATE attendances 
        SET status = 'Terlambat' 
        WHERE TIME(check_in_time) > '08:30:00' 
        AND check_in_time != '0000-00-00 00:00:00'
        AND status NOT IN ('Izin', 'Sakit', 'Cuti', 'Alpha')
        AND attendance_date >= CURDATE() - INTERVAL 30 DAY
    ");
    $stmt->execute();
    $lateUpdated = $stmt->rowCount();
    echo "[OK] Updated $lateUpdated records to 'Terlambat' status\n";
    
    
    $stmt = $pdo->prepare("
        UPDATE attendances 
        SET status = 'Hadir' 
        WHERE TIME(check_in_time) <= '08:30:00' 
        AND check_in_time != '0000-00-00 00:00:00'
        AND status NOT IN ('Izin', 'Sakit', 'Cuti', 'Alpha', 'Terlambat')
        AND attendance_date >= CURDATE() - INTERVAL 30 DAY
    ");
    $stmt->execute();
    $onTimeUpdated = $stmt->rowCount();
    echo "[OK] Updated $onTimeUpdated records to 'Hadir' status\n";
    
    
    $stmt = $pdo->prepare("
        UPDATE attendances 
        SET overtime_hours = CASE 
            WHEN check_out_time > CONCAT(attendance_date, ' 19:00:00') 
            THEN TIMESTAMPDIFF(MINUTE, CONCAT(attendance_date, ' 17:30:00'), check_out_time) / 60.0
            ELSE 0
        END
        WHERE check_out_time IS NOT NULL 
        AND check_out_time != '0000-00-00 00:00:00'
        AND attendance_date >= CURDATE() - INTERVAL 30 DAY
    ");
    $stmt->execute();
    $overtimeUpdated = $stmt->rowCount();
    echo "[OK] Calculated overtime for $overtimeUpdated records\n";
    
    echo "[OK] Database is ready for new time rules system\n";
    echo "[OK] Auto Alpha cron job can now run properly\n";
    echo "[OK] GPS location tracking is prepared\n\n";
    
    
    echo "=== Current Time Rules ===\n";
    $stmt = $pdo->query("SELECT setting_name, setting_value, description FROM company_settings ORDER BY setting_name");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("%-20s: %-15s (%s)\n", $row['setting_name'], $row['setting_value'], $row['description']);
    }
    
} catch (Exception $e) {
    echo "[ERROR] Migration failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>