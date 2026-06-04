<?php
 


$_logDir = realpath(__DIR__ . '/../../storage/logs');
if (!$_logDir) {
    $_logDir = __DIR__ . '/../../storage/logs';
    @mkdir($_logDir, 0777, true);
}
ini_set('error_log', $_logDir . '/app.log');
ini_set('log_errors', '1');
ini_set('display_errors', '0');

 
function app_log(string $message, string $level = 'INFO'): void {
    $logDir = realpath(__DIR__ . '/../../storage/logs');
    if (!$logDir) {
        return;
    }
    $logFile = $logDir . '/app.log';
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}
