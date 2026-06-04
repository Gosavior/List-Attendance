<?php
@date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/avatar.php';
require_once __DIR__ . '/../helpers/birthday_threads.php';

header('Content-Type: application/json');

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$birthdayUserId = isset($_GET['birthday_user_id']) ? (int)$_GET['birthday_user_id'] : 0;
if ($birthdayUserId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid user']);
    exit;
}

$threadDate = date('Y-m-d');

try {
    $userStmt = $pdo->prepare('SELECT id, username, full_name, avatar, birth_date, gender, is_active FROM users WHERE id = ? LIMIT 1');
    $userStmt->execute([$birthdayUserId]);
    $birthdayUser = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$birthdayUser || (int)$birthdayUser['is_active'] !== 1) {
        echo json_encode(['success' => false, 'error' => 'User not available']);
        exit;
    }

    $isBirthdayToday = false;
    if (!empty($birthdayUser['birth_date'])) {
        $isBirthdayToday = date('m-d', strtotime($birthdayUser['birth_date'])) === date('m-d');
    }

    $thread = loadBirthdayThread($pdo, $birthdayUserId, $threadDate);
    $messages = buildBirthdayThreadTree($thread['messages']);

    $formatTree = function (array $message) use (&$formatTree) {
        $formatted = formatBirthdayMessage($message);
        $formatted['replies'] = array_map($formatTree, $message['replies']);
        return $formatted;
    };

    $formattedMessages = array_map($formatTree, $messages);

    echo json_encode([
        'success' => true,
        'data' => [
            'thread_date' => $threadDate,
            'is_birthday_today' => $isBirthdayToday,
            'birthday_user' => [
                'id' => (int)$birthdayUser['id'],
                'full_name' => $birthdayUser['full_name'] ?? ($birthdayUser['username'] ?? 'Pengguna'),
                'username' => $birthdayUser['username'] ?? null,
                'avatar_url' => getAvatarUrl($birthdayUser)
            ],
            'messages' => $formattedMessages,
            'current_user' => [
                'id' => $userId,
                'name' => $_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'Anda'),
                'username' => $_SESSION['username'] ?? null,
                'avatar_url' => getAvatarUrl([
                    'avatar' => $_SESSION['avatar'] ?? null,
                    'gender' => $_SESSION['gender'] ?? null,
                    'id' => $userId
                ])
            ]
        ]
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
