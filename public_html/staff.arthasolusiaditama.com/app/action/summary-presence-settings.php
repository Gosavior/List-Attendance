<?php
/**
 * Save/Load Summary Presence Settings
 * Accessible by: administrator, direktur
 * 
 * GET: returns current settings as JSON
 * POST: saves settings
 */
@date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
requireLogin();

$stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);
$roleStr = strtolower(trim($me['role'] ?? ''));

if (!in_array($roleStr, ['administrator', 'direktur'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

// Detect key column
$cols = $pdo->query('SHOW COLUMNS FROM company_settings')->fetchAll(PDO::FETCH_ASSOC);
$keyColumn = 'setting_key';
foreach ($cols as $c) { if ($c['Field'] === 'setting_name') $keyColumn = 'setting_name'; }

// Check if value column exists
$valueColumn = 'setting_value';

function getSetting(PDO $pdo, string $keyCol, string $valCol, string $key, $default = null) {
    $stmt = $pdo->prepare("SELECT $valCol FROM company_settings WHERE $keyCol = ?");
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return $val !== false ? $val : $default;
}

function setSetting(PDO $pdo, string $keyCol, string $valCol, string $key, string $value) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM company_settings WHERE $keyCol = ?");
    $stmt->execute([$key]);
    if ((int)$stmt->fetchColumn() > 0) {
        $pdo->prepare("UPDATE company_settings SET $valCol = ? WHERE $keyCol = ?")->execute([$value, $key]);
    } else {
        $pdo->prepare("INSERT INTO company_settings ($keyCol, $valCol) VALUES (?, ?)")->execute([$key, $value]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Return current settings
    $workDays = json_decode(getSetting($pdo, $keyColumn, $valueColumn, 'summary_work_days', '[1,2,3,4,5]'), true);
    $breakMinutes = (int)getSetting($pdo, $keyColumn, $valueColumn, 'summary_break_minutes', '60');
    $excludedUsers = json_decode(getSetting($pdo, $keyColumn, $valueColumn, 'summary_excluded_users', '[]'), true);
    $autoPresentUsers = json_decode(getSetting($pdo, $keyColumn, $valueColumn, 'summary_auto_present_users', '[]'), true);

    // Get all active users for selection
    $users = $pdo->query("SELECT id, full_name, username, role FROM users WHERE is_active = 1 AND role NOT IN ('customer') ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'settings' => [
            'work_days' => is_array($workDays) ? $workDays : [1,2,3,4,5],
            'break_minutes' => $breakMinutes,
            'excluded_users' => is_array($excludedUsers) ? $excludedUsers : [],
            'auto_present_users' => is_array($autoPresentUsers) ? $autoPresentUsers : [],
        ],
        'users' => $users,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }

    // Validate and save work_days
    if (isset($input['work_days'])) {
        $workDays = array_filter(array_map('intval', (array)$input['work_days']), fn($d) => $d >= 1 && $d <= 7);
        setSetting($pdo, $keyColumn, $valueColumn, 'summary_work_days', json_encode(array_values($workDays)));
    }

    // Validate and save break_minutes
    if (isset($input['break_minutes'])) {
        $breakMin = max(0, min(180, (int)$input['break_minutes']));
        setSetting($pdo, $keyColumn, $valueColumn, 'summary_break_minutes', (string)$breakMin);
    }

    // Save excluded users
    if (isset($input['excluded_users'])) {
        $excluded = array_map('intval', (array)$input['excluded_users']);
        setSetting($pdo, $keyColumn, $valueColumn, 'summary_excluded_users', json_encode($excluded));
    }

    // Save auto-present users
    if (isset($input['auto_present_users'])) {
        $autoPresent = array_map('intval', (array)$input['auto_present_users']);
        setSetting($pdo, $keyColumn, $valueColumn, 'summary_auto_present_users', json_encode($autoPresent));
    }

    echo json_encode(['success' => true, 'message' => 'Settings berhasil disimpan']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Method not allowed']);
