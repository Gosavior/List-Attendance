<?php

require_once __DIR__ . '/app/config/database.php';

echo "<pre>";
echo "=== Night Shift Settings in company_settings ===\n";
$stmt = $pdo->query("SELECT setting_key, setting_value FROM company_settings WHERE setting_key LIKE 'night%' ORDER BY setting_key");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo str_pad($row['setting_key'], 35) . " = " . $row['setting_value'] . "\n";
}

echo "\n=== Current Server Time ===\n";
echo "Server timezone : " . date_default_timezone_get() . "\n";
echo "Current time    : " . date('Y-m-d H:i:s') . "\n";
echo "Current hour (G): " . date('G') . "\n";

echo "\n=== Night Window Calculation ===\n";
$ns = $pdo->query("SELECT setting_value FROM company_settings WHERE setting_key = 'night_shift_start_time'")->fetchColumn();
$ne = $pdo->query("SELECT setting_value FROM company_settings WHERE setting_key = 'night_shift_end_time'")->fetchColumn();
$ns = $ns ?: '18:00:00';
$ne = $ne ?: '06:00:00';

$nightStartHour = (int)substr($ns, 0, 2);
$nightEndHour   = (int)substr($ne, 0, 2);
$nowHour        = (int)date('G');

echo "night_shift_start_time = $ns (hour: $nightStartHour)\n";
echo "night_shift_end_time   = $ne (hour: $nightEndHour)\n";
echo "crossesMidnight        = " . ($nightEndHour < $nightStartHour ? 'YES' : 'NO') . "\n";
$dur = ($nightEndHour < $nightStartHour) ? ((24 - $nightStartHour) + $nightEndHour) : 0;
echo "nightDurationHours     = $dur\n";
echo "validNightConfig       = " . ($nightEndHour < $nightStartHour && $dur <= 12 ? 'YES' : 'NO') . "\n";
echo "nowHour                = $nowHour\n";
echo "isNightWindowForTab    = ";
if ($nightEndHour < $nightStartHour && $dur <= 12) {
    echo (($nowHour >= $nightStartHour) || ($nowHour < $nightEndHour)) ? "TRUE (will show tab)" : "FALSE (tab hidden)";
} else {
    echo ($nowHour >= $nightStartHour) ? "TRUE (fallback: show tab)" : "FALSE (fallback: tab hidden)";
}
echo "\n</pre>";
echo "<br><a href='#' onclick='fetch(location.href.replace(\"check_night_settings.php\",\"\"))'>Done - delete this file when done</a>";
