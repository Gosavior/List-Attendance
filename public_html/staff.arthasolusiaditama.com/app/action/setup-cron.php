<?php
 

require_once __DIR__ . '/../config/database.php';

echo "=== Auto Alpha Cron Job Setup ===\n\n";


$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
    echo "[OK] Created logs directory: $logDir\n";
} else {
    echo "[OK] Logs directory exists: $logDir\n";
}


$testLogFile = $logDir . '/test.log';
if (file_put_contents($testLogFile, "Test log entry\n")) {
    echo "[OK] Log writing test successful\n";
    unlink($testLogFile);
} else {
    echo "[ERROR] Log writing test failed\n";
}


try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'administrator'");
    $userCount = $stmt->fetchColumn();
    echo "[OK] Database connection successful\n";
    echo "[INFO] Found $userCount non-admin users\n";
} catch (Exception $e) {
    echo "[ERROR] Database connection failed: " . $e->getMessage() . "\n";
}

echo "\n=== Cron Job Commands ===\n\n";

$cronScript = realpath(__DIR__ . '/auto-alpha-cron.php');

echo "[INFO] Add this to your crontab (crontab -e):\n";
echo "# Auto Alpha Generation - Every midnight\n";
echo "0 0 * * * /usr/bin/php \"$cronScript\" >> /dev/null 2>&1\n\n";

echo "[INFO] For Windows Task Scheduler:\n";
echo "Program: C:\\xampp\\php\\php.exe\n";
echo "Arguments: \"$cronScript\"\n";
echo "Schedule: Daily at 00:00 (midnight)\n\n";

echo "[INFO] Manual web trigger (for testing):\n";
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '/staff.arthasolusiaditama.com';
$webUrl = $protocol . "://" . $host . "/app/action/auto-alpha-cron.php?cron_key=alpha_cron_2025";
echo "$webUrl\n\n";

echo "[INFO] Test run the script now? (y/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim($line) === 'y' || trim($line) === 'Y') {
    echo "\n=== Running Test ===\n";
    include 'auto-alpha-cron.php';
}

echo "\n[OK] Setup complete!\n";
?>