<?php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden";
    exit(1);
}

@date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php'; 

function runStatements(PDO $pdo, array $statements): array {
    $ok = 0; $fail = 0; $errors = [];
    foreach ($statements as $stmt) {
        $sql = trim($stmt);
        if ($sql === '') continue;
        
        $lines = preg_split("/\r?\n/", $sql);
        $clean = [];
        foreach ($lines as $ln) {
            if (preg_match('/^\s*--/', $ln)) continue;
            $clean[] = $ln;
        }
        $sql = trim(implode("\n", $clean));
        if ($sql === '') continue;
        try {
            $pdo->exec($sql);
            $ok++;
        } catch (Throwable $e) {
            $fail++;
            $errors[] = $e->getMessage();
        }
    }
    return [$ok, $fail, $errors];
}


$sqlPayload = <<<'SQL'
-- 1) Master shift types
CREATE TABLE IF NOT EXISTS work_shifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) UNIQUE NOT NULL,
  name VARCHAR(100) NOT NULL,
  default_start TIME NULL,
  default_end TIME NULL,
  cross_midnight TINYINT(1) DEFAULT 0,
  grace_minutes INT DEFAULT 10,
  early_leave_grace INT DEFAULT 0,
  overtime_grace INT DEFAULT 30,
  checkin_open_mins INT DEFAULT 60,
  checkin_close_mins INT DEFAULT 120,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Per-user shift assignments per date
CREATE TABLE IF NOT EXISTS user_shift_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  shift_date DATE NOT NULL,
  shift_id INT NOT NULL,
  custom_start TIME NULL,
  custom_end TIME NULL,
  note VARCHAR(255) NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_date (user_id, shift_date),
  INDEX idx_date (shift_date),
  FOREIGN KEY (shift_id) REFERENCES work_shifts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Optional columns on attendances (safe if already present)
ALTER TABLE attendances
  ADD COLUMN IF NOT EXISTS shift_id INT NULL AFTER user_id,
  ADD COLUMN IF NOT EXISTS scheduled_start DATETIME NULL AFTER shift_id,
  ADD COLUMN IF NOT EXISTS scheduled_end DATETIME NULL AFTER scheduled_start,
  ADD COLUMN IF NOT EXISTS is_cross_midnight TINYINT(1) DEFAULT 0 AFTER scheduled_end,
  ADD INDEX IF NOT EXISTS idx_shift (shift_id);

-- Seed defaults
INSERT INTO work_shifts (code, name, default_start, default_end, cross_midnight, grace_minutes, early_leave_grace, overtime_grace, checkin_open_mins, checkin_close_mins)
VALUES ('DAY','Shift Siang','08:30:00','17:30:00',0,10,10,30,60,120)
ON DUPLICATE KEY UPDATE name=VALUES(name), default_start=VALUES(default_start), default_end=VALUES(default_end);

INSERT INTO work_shifts (code, name, cross_midnight, grace_minutes, overtime_grace, checkin_open_mins, checkin_close_mins)
VALUES ('NIGHT','Shift Malam',1,10,30,120,180)
ON DUPLICATE KEY UPDATE name=VALUES(name), cross_midnight=VALUES(cross_midnight);
SQL;


$statements = [];
$current = '';
$inStr = false; $strQuote = '';
for ($i = 0, $n = strlen($sqlPayload); $i < $n; $i++) {
    $ch = $sqlPayload[$i];
    if ($inStr) {
        if ($ch === $strQuote) {
            
            $next = ($i + 1 < $n) ? $sqlPayload[$i + 1] : '';
            if ($next === $strQuote) { 
                $current .= $ch . $next; $i++;
                continue;
            }
            $inStr = false; $strQuote = '';
        }
        $current .= $ch;
        continue;
    }
    if ($ch === '\'' || $ch === '"') {
        $inStr = true; $strQuote = $ch; $current .= $ch; continue;
    }
    if ($ch === ';') {
        $statements[] = $current;
        $current = '';
        continue;
    }
    $current .= $ch;
}
if (trim($current) !== '') $statements[] = $current;

[$ok, $fail, $errs] = runStatements($pdo, $statements);

echo "[OK] Executed: {$ok}\n";
if ($fail > 0) {
    echo "[WARN] Failed: {$fail}\n";
    foreach ($errs as $emsg) {
        
        echo " - " . preg_replace('/\s+/', ' ', $emsg) . "\n";
    }
}
exit($fail > 0 ? 2 : 0);

