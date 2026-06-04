<?php

require_once __DIR__ . '/../config/database.php';

function logMessage($message, $level = 'INFO')
{
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp][$level] $message\n";

    
    $logFile = __DIR__ . '/../storage/logs/app.log';
    file_put_contents($logFile, $logLine, FILE_APPEND);

    
    if (PHP_SAPI === 'cli') {
        echo $logLine;
    }
}

function runMigrations($pdo) {
    $migrations = [];
    
    try {
        logMessage("=== Starting Database Migration ===");
        
        
        logMessage("Running Migration 1: Time Rules and Company Settings");
        
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS company_settings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT,
                setting_type ENUM('string', 'number', 'time', 'boolean', 'json') DEFAULT 'string',
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        logMessage("[OK] Company settings table created/verified");
        
        
        $timeRules = [
            ['work_start_time', '08:30:00', 'time', 'Normal work start time'],
            ['work_end_time', '17:00:00', 'time', 'Normal work end time'],
            ['late_threshold_minutes', '0', 'number', 'Minutes after work_start_time to be considered late'],
            ['overtime_start_time', '19:00:00', 'time', 'Time when overtime starts'],
            ['weekend_work_enabled', 'false', 'boolean', 'Allow work on weekends'],
            ['auto_alpha_enabled', 'true', 'boolean', 'Enable automatic Alpha generation'],
            ['company_name', 'Artha Solusi Aditama', 'string', 'Company name'],
            ['timezone', 'Asia/Jakarta', 'string', 'Company timezone']
        ];
        
        foreach ($timeRules as $rule) {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO company_settings (setting_key, setting_value, setting_type, description) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute($rule);
        }
        
        logMessage("[OK] Default time rules inserted");
        
        
        logMessage("Running Migration 2: Company Holidays");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS company_holidays (
                id INT PRIMARY KEY AUTO_INCREMENT,
                holiday_date DATE NOT NULL,
                holiday_name VARCHAR(255) NOT NULL,
                holiday_type ENUM('national', 'company', 'religious', 'weekend_override') DEFAULT 'company',
                description TEXT,
                is_recurring BOOLEAN DEFAULT FALSE,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_holiday_date (holiday_date),
                INDEX idx_holiday_type (holiday_type),
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        logMessage("[OK] Company holidays table created");
        
        
        logMessage("Running Migration 3: Attendances Table Updates");
        
        
        $gpsColumns = [
            "ALTER TABLE attendances ADD COLUMN IF NOT EXISTS check_in_latitude DECIMAL(10, 8) NULL AFTER check_in_photo",
            "ALTER TABLE attendances ADD COLUMN IF NOT EXISTS check_in_longitude DECIMAL(11, 8) NULL AFTER check_in_latitude",
            "ALTER TABLE attendances ADD COLUMN IF NOT EXISTS check_out_latitude DECIMAL(10, 8) NULL AFTER check_out_photo",
            "ALTER TABLE attendances ADD COLUMN IF NOT EXISTS check_out_longitude DECIMAL(11, 8) NULL AFTER check_out_latitude",
            "ALTER TABLE attendances ADD COLUMN IF NOT EXISTS location_accuracy FLOAT NULL AFTER check_out_longitude"
        ];
        
        foreach ($gpsColumns as $sql) {
            try {
                $pdo->exec($sql);
            } catch (PDOException $e) {
                
                if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                    logMessage("Warning: " . $e->getMessage());
                }
            }
        }
        
        logMessage("[OK] GPS location columns added to attendances");
        
        
        try {
            $normalizeStmt = $pdo->prepare("
                UPDATE attendances
                SET status = CASE
                    WHEN status IN ('Overtime', 'overtime') THEN 'Lembur'
                    WHEN status = 'Late' THEN 'Terlambat'
                    ELSE status
                END
                WHERE status IN ('Overtime', 'overtime', 'Late')
            ");
            $normalizeStmt->execute();
            $normalizedCount = $normalizeStmt->rowCount();
            logMessage("[OK] Normalized legacy attendance status labels: {$normalizedCount} rows");
        } catch (PDOException $e) {
            logMessage("Warning normalizing legacy status labels: " . $e->getMessage());
        }

        
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM attendances LIKE 'status'");
            $column = $stmt->fetch(PDO::FETCH_ASSOC);

            $statusType = strtolower((string)($column['Type'] ?? ''));
            $needsStatusEnumUpdate =
                strpos($statusType, 'enum(') !== 0 ||
                strpos($statusType, "'libur'") === false ||
                strpos($statusType, "'lembur'") === false ||
                strpos($statusType, "'not checked out'") === false ||
                strpos($statusType, "'overtime'") !== false;

            if ($needsStatusEnumUpdate) {
                $pdo->exec("
                    ALTER TABLE attendances 
                    MODIFY COLUMN status ENUM('Hadir', 'Terlambat', 'Pulang Cepat', 'Alpha', 'Izin', 'Sakit', 'Cuti', 'Libur', 'Lembur', 'Not Checked Out') 
                    DEFAULT 'Hadir'
                ");
                logMessage("[OK] Attendances status enum updated (Lembur as the only overtime label)");
            } else {
                logMessage("[OK] Attendances status enum is already consistent");
            }
        } catch (PDOException $e) {
            logMessage("Warning updating status enum: " . $e->getMessage());
        }
        
        
        logMessage("Running Migration 4: Performance Indexes");
        
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_attendances_user_date ON attendances(user_id, attendance_date)",
            "CREATE INDEX IF NOT EXISTS idx_attendances_date ON attendances(attendance_date)",
            "CREATE INDEX IF NOT EXISTS idx_attendances_status ON attendances(status)",
            "CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)",
            "CREATE INDEX IF NOT EXISTS idx_users_status ON users(status)"
        ];
        
        foreach ($indexes as $sql) {
            try {
                $pdo->exec($sql);
            } catch (PDOException $e) {
                
                if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                    logMessage("Warning: " . $e->getMessage());
                }
            }
        }
        
        logMessage("[OK] Performance indexes created");
        
        
        logMessage("Running Migration 5: Sample Holidays");
        
        $currentYear = date('Y');
        $sampleHolidays = [
            [$currentYear . '-01-01', 'Tahun Baru', 'national', 'Hari libur nasional'],
            [$currentYear . '-08-17', 'Hari Kemerdekaan RI', 'national', 'Hari libur nasional'],
            [$currentYear . '-12-25', 'Hari Raya Natal', 'national', 'Hari libur nasional']
        ];
        
        foreach ($sampleHolidays as $holiday) {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO company_holidays (holiday_date, holiday_name, holiday_type, description) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute($holiday);
        }
        
        logMessage("[OK] Sample holidays added");
        
        logMessage("=== Migration Completed Successfully ===");
        
        return true;
        
    } catch (Exception $e) {
        logMessage("ERROR: Migration failed - " . $e->getMessage());
        logMessage("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}


if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo "<pre>";
}

try {
    
    $pdo = new PDO($dsn, $username, $password, $options);
    logMessage("Database connection established");
    
    
    $success = runMigrations($pdo);
    
    if ($success) {
        
        logMessage("[OK] All migrations applied successfully.");

        if (PHP_SAPI !== 'cli') {
            echo "<br><br>";
            echo "<a href='../pages/holiday-management.php' class='bg-blue-500 text-white px-4 py-2 rounded'>Go to Holiday Management</a>";
        }
    } else {
        logMessage("[ERROR] Migration failed. Please check the errors above.");
    }
    
} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
    if (PHP_SAPI !== 'cli') {
        echo "</pre>";
    }
    exit(1);
}

if (PHP_SAPI !== 'cli') {
    echo "</pre>";
}
?>