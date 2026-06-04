<?php

if (($_GET['token'] ?? '') !== 'debug_asa_2026') {
    http_response_code(403);
    die('Forbidden');
}
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/app/config/database.php';

echo "=== USERS TABLE DEBUG ===\n\n";


$cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC);
echo "Users columns:\n";
foreach ($cols as $c) {
    echo "  - {$c['Field']} ({$c['Type']})\n";
}


echo "\nSample users (first 5):\n";
$rows = $pdo->query("SELECT id, username, full_name, role, is_active FROM users LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  id={$r['id']} | username=" . ($r['username'] ?: '(empty)') . " | full_name=" . ($r['full_name'] ?: '(EMPTY)') . " | role={$r['role']} | active={$r['is_active']}\n";
}


echo "\nvendor/autoload.php: " . (file_exists(__DIR__ . '/vendor/autoload.php') ? "EXISTS" : "MISSING") . "\n";

echo "\n=== END ===\n";
