<?php
@date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/avatar.php';
require_once __DIR__ . '/../helpers/birthday_threads.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$birthdayUserId = isset($_POST['birthday_user_id']) ? (int)$_POST['birthday_user_id'] : 0;
$message = trim($_POST['message'] ?? '');
$parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (string)$_POST['parent_id'] : null;

if ($birthdayUserId <= 0 || $message === '') {
    echo json_encode(['success' => false, 'error' => 'Pesan tidak boleh kosong.']);
    exit;
}

if (mb_strlen($message) > 500) {
    echo json_encode(['success' => false, 'error' => 'Pesan terlalu panjang (maksimal 500 karakter).']);
    exit;
}

$threadDate = date('Y-m-d');

try {
    $userStmt = $pdo->prepare('SELECT id, username, full_name, avatar, birth_date, is_active FROM users WHERE id = ? LIMIT 1');
    $userStmt->execute([$birthdayUserId]);
    $birthdayUser = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$birthdayUser || (int)$birthdayUser['is_active'] !== 1) {
        echo json_encode(['success' => false, 'error' => 'User tidak valid.']);
        exit;
    }

    $loggedStmt = $pdo->prepare('SELECT full_name, username, avatar, gender FROM users WHERE id = ? LIMIT 1');
    $loggedStmt->execute([$userId]);
    $loggedUser = $loggedStmt->fetch(PDO::FETCH_ASSOC);

    if (!$loggedUser) {
        echo json_encode(['success' => false, 'error' => 'User tidak ditemukan.']);
        exit;
    }

    $thread = loadBirthdayThread($pdo, $birthdayUserId, $threadDate);
    $messages = $thread['messages'];

    if ($parentId) {
        $parentExists = false;
        foreach ($messages as $existing) {
            if (isset($existing['id']) && (string)$existing['id'] === $parentId) {
                $parentExists = true;
                break;
            }
        }
        if (!$parentExists) {
            echo json_encode(['success' => false, 'error' => 'Komentar yang dibalas tidak ditemukan.']);
            exit;
        }
    }

    $newMessage = [
        'id' => bin2hex(random_bytes(8)),
        'parent_id' => $parentId,
        'sender_id' => $userId,
        'sender_name' => $loggedUser['full_name'] ?? ($loggedUser['username'] ?? 'Pengguna'),
        'sender_username' => $loggedUser['username'] ?? null,
        'sender_avatar' => getAvatarUrl([
            'avatar' => $loggedUser['avatar'] ?? null,
            'gender' => $loggedUser['gender'] ?? null,
            'id' => $userId
        ]),
        'message' => $message,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $messages = appendBirthdayMessage($messages, $newMessage);
    saveBirthdayThread($pdo, $birthdayUserId, $threadDate, $messages);

    echo json_encode([
        'success' => true,
        'message' => formatBirthdayMessage($newMessage)
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
