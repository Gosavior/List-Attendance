<?php
 
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

try {
    switch ($action) {

        
        case 'rooms':
            $stmt = $pdo->prepare("
                SELECT 
                    cr.id, cr.name, cr.type, cr.created_at,
                    (SELECT COUNT(*) FROM chat_room_members WHERE room_id = cr.id) as member_count,
                    (SELECT cm.message FROM chat_messages cm WHERE cm.room_id = cr.id AND cm.is_deleted = 0 ORDER BY cm.created_at DESC LIMIT 1) as last_message,
                    (SELECT cm.created_at FROM chat_messages cm WHERE cm.room_id = cr.id AND cm.is_deleted = 0 ORDER BY cm.created_at DESC LIMIT 1) as last_message_at,
                    (SELECT cm.sender_id FROM chat_messages cm WHERE cm.room_id = cr.id AND cm.is_deleted = 0 ORDER BY cm.created_at DESC LIMIT 1) as last_sender_id,
                    (SELECT u.full_name FROM chat_messages cm JOIN users u ON cm.sender_id = u.id WHERE cm.room_id = cr.id AND cm.is_deleted = 0 ORDER BY cm.created_at DESC LIMIT 1) as last_sender_name,
                    (SELECT COUNT(*) FROM chat_messages cm WHERE cm.room_id = cr.id AND cm.is_deleted = 0 AND cm.created_at > COALESCE(crm.last_read_at, '1970-01-01')) as unread_count
                FROM chat_rooms cr
                JOIN chat_room_members crm ON crm.room_id = cr.id AND crm.user_id = ?
                ORDER BY last_message_at DESC, cr.created_at DESC
            ");
            $stmt->execute([$userId]);
            $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            
            foreach ($rooms as &$room) {
                if ($room['type'] === 'direct') {
                    $stmtOther = $pdo->prepare("
                        SELECT u.id, u.full_name, u.username, u.avatar, u.role
                        FROM chat_room_members crm
                        JOIN users u ON crm.user_id = u.id
                        WHERE crm.room_id = ? AND crm.user_id != ?
                        LIMIT 1
                    ");
                    $stmtOther->execute([$room['id'], $userId]);
                    $other = $stmtOther->fetch(PDO::FETCH_ASSOC);
                    $room['other_user'] = $other;
                    if ($other) {
                        $room['display_name'] = $other['full_name'];
                    }
                } else {
                    $room['display_name'] = $room['name'];
                }
            }
            unset($room);
            
            echo json_encode(['success' => true, 'data' => $rooms]);
            break;

        
        case 'messages':
            $roomId = (int)($_GET['room_id'] ?? 0);
            $before = $_GET['before'] ?? null; 
            $limit = min((int)($_GET['limit'] ?? 50), 100);
            
            
            $check = $pdo->prepare("SELECT 1 FROM chat_room_members WHERE room_id = ? AND user_id = ?");
            $check->execute([$roomId, $userId]);
            if (!$check->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Bukan anggota room ini.']);
                exit;
            }

            $sql = "
                SELECT cm.id, cm.room_id, cm.sender_id, cm.message, cm.message_type, cm.file_url, 
                       cm.reply_to_id, cm.forwarded_from_id, cm.is_deleted, cm.created_at,
                       u.full_name as sender_name, u.username as sender_username, u.avatar as sender_avatar, u.role as sender_role,
                       rp.message as reply_message, rp.sender_id as reply_sender_id,
                       ru.full_name as reply_sender_name,
                       fm.message as forwarded_message, fm.sender_id as forwarded_sender_id,
                       fu.full_name as forwarded_sender_name
                FROM chat_messages cm
                JOIN users u ON cm.sender_id = u.id
                LEFT JOIN chat_messages rp ON cm.reply_to_id = rp.id
                LEFT JOIN users ru ON rp.sender_id = ru.id
                LEFT JOIN chat_messages fm ON cm.forwarded_from_id = fm.id
                LEFT JOIN users fu ON fm.sender_id = fu.id
                WHERE cm.room_id = ? AND cm.is_deleted = 0
            ";
            $params = [$roomId];
            if ($before) {
                $sql .= " AND cm.id < ?";
                $params[] = (int)$before;
            }
            $sql .= " ORDER BY cm.created_at DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
            
            
            $markRead = $pdo->prepare("UPDATE chat_room_members SET last_read_at = NOW() WHERE room_id = ? AND user_id = ?");
            $markRead->execute([$roomId, $userId]);

            echo json_encode(['success' => true, 'data' => $messages]);
            break;

        
        case 'send_message':
            $roomId = (int)($_POST['room_id'] ?? 0);
            $message = trim($_POST['message'] ?? '');
            $replyToId = (int)($_POST['reply_to_id'] ?? 0) ?: null;
            
            if (!$roomId || !$message) {
                echo json_encode(['success' => false, 'message' => 'Room dan pesan wajib diisi.']);
                exit;
            }

            
            $check = $pdo->prepare("SELECT 1 FROM chat_room_members WHERE room_id = ? AND user_id = ?");
            $check->execute([$roomId, $userId]);
            if (!$check->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Bukan anggota room ini.']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO chat_messages (room_id, sender_id, message, reply_to_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$roomId, $userId, $message, $replyToId]);
            $msgId = $pdo->lastInsertId();

            
            $stmt = $pdo->prepare("
                SELECT cm.*, u.full_name as sender_name, u.username as sender_username, u.avatar as sender_avatar, u.role as sender_role,
                       rp.message as reply_message, rp.sender_id as reply_sender_id, ru.full_name as reply_sender_name
                FROM chat_messages cm
                JOIN users u ON cm.sender_id = u.id
                LEFT JOIN chat_messages rp ON cm.reply_to_id = rp.id
                LEFT JOIN users ru ON rp.sender_id = ru.id
                WHERE cm.id = ?
            ");
            $stmt->execute([$msgId]);
            $msg = $stmt->fetch(PDO::FETCH_ASSOC);

            
            $pdo->prepare("UPDATE chat_room_members SET last_read_at = NOW() WHERE room_id = ? AND user_id = ?")->execute([$roomId, $userId]);

            echo json_encode(['success' => true, 'data' => $msg]);
            break;

        
        case 'delete_message':
            $messageId = (int)($_POST['message_id'] ?? 0);
            if (!$messageId) {
                echo json_encode(['success' => false, 'message' => 'Message ID wajib.']);
                exit;
            }

            
            $stmt = $pdo->prepare("SELECT cm.*, cr.type FROM chat_messages cm JOIN chat_rooms cr ON cm.room_id = cr.id WHERE cm.id = ? AND cm.is_deleted = 0");
            $stmt->execute([$messageId]);
            $msg = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$msg) {
                echo json_encode(['success' => false, 'message' => 'Pesan tidak ditemukan.']);
                exit;
            }

            
            $isAdmin = in_array($role, ['administrator', 'technician_manager']);
            if ($msg['sender_id'] != $userId && !$isAdmin) {
                echo json_encode(['success' => false, 'message' => 'Tidak diizinkan menghapus pesan ini.']);
                exit;
            }

            $pdo->prepare("UPDATE chat_messages SET is_deleted = 1, deleted_by_id = ?, deleted_at = NOW() WHERE id = ?")
                ->execute([$userId, $messageId]);

            echo json_encode(['success' => true, 'room_id' => (int)$msg['room_id'], 'message_id' => $messageId]);
            break;

        
        case 'forward_message':
            $messageId = (int)($_POST['message_id'] ?? 0);
            $targetRoomId = (int)($_POST['target_room_id'] ?? 0);
            if (!$messageId || !$targetRoomId) {
                echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
                exit;
            }

            
            $stmt = $pdo->prepare("SELECT * FROM chat_messages WHERE id = ? AND is_deleted = 0");
            $stmt->execute([$messageId]);
            $origMsg = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$origMsg) {
                echo json_encode(['success' => false, 'message' => 'Pesan asli tidak ditemukan.']);
                exit;
            }

            
            $check = $pdo->prepare("SELECT 1 FROM chat_room_members WHERE room_id = ? AND user_id = ?");
            $check->execute([$targetRoomId, $userId]);
            if (!$check->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Bukan anggota room tujuan.']);
                exit;
            }

            
            $stmt = $pdo->prepare("INSERT INTO chat_messages (room_id, sender_id, message, forwarded_from_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$targetRoomId, $userId, $origMsg['message'], $messageId]);
            $newMsgId = $pdo->lastInsertId();

            
            $stmt = $pdo->prepare("
                SELECT cm.*, u.full_name as sender_name, u.username as sender_username, u.avatar as sender_avatar, u.role as sender_role,
                       fm.message as forwarded_message, fm.sender_id as forwarded_sender_id, fu.full_name as forwarded_sender_name
                FROM chat_messages cm
                JOIN users u ON cm.sender_id = u.id
                LEFT JOIN chat_messages fm ON cm.forwarded_from_id = fm.id
                LEFT JOIN users fu ON fm.sender_id = fu.id
                WHERE cm.id = ?
            ");
            $stmt->execute([$newMsgId]);
            $msg = $stmt->fetch(PDO::FETCH_ASSOC);

            $pdo->prepare("UPDATE chat_room_members SET last_read_at = NOW() WHERE room_id = ? AND user_id = ?")->execute([$targetRoomId, $userId]);

            echo json_encode(['success' => true, 'data' => $msg]);
            break;

        
        case 'create_dm':
            $targetId = (int)($_POST['target_user_id'] ?? 0);
            if (!$targetId || $targetId === $userId) {
                echo json_encode(['success' => false, 'message' => 'User tidak valid.']);
                exit;
            }

            
            $stmt = $pdo->prepare("
                SELECT cr.id FROM chat_rooms cr
                WHERE cr.type = 'direct'
                AND (SELECT COUNT(*) FROM chat_room_members crm WHERE crm.room_id = cr.id) = 2
                AND EXISTS (SELECT 1 FROM chat_room_members crm WHERE crm.room_id = cr.id AND crm.user_id = ?)
                AND EXISTS (SELECT 1 FROM chat_room_members crm WHERE crm.room_id = cr.id AND crm.user_id = ?)
                LIMIT 1
            ");
            $stmt->execute([$userId, $targetId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                echo json_encode(['success' => true, 'room_id' => (int)$existing['id'], 'existing' => true]);
                exit;
            }

            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO chat_rooms (type, created_by) VALUES ('direct', ?)")->execute([$userId]);
            $roomId = $pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO chat_room_members (room_id, user_id) VALUES (?, ?)");
            $ins->execute([$roomId, $userId]);
            $ins->execute([$roomId, $targetId]);
            $pdo->commit();

            echo json_encode(['success' => true, 'room_id' => (int)$roomId, 'existing' => false]);
            break;

        
        case 'create_group':
            if (!in_array($role, ['administrator', 'technician_manager'])) {
                echo json_encode(['success' => false, 'message' => 'Tidak diizinkan.']);
                exit;
            }
            $name = trim($_POST['name'] ?? '');
            $memberIds = json_decode($_POST['members'] ?? '[]', true);
            if (!$name) {
                echo json_encode(['success' => false, 'message' => 'Nama grup wajib diisi.']);
                exit;
            }

            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO chat_rooms (name, type, created_by) VALUES (?, 'group', ?)")->execute([$name, $userId]);
            $roomId = $pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT IGNORE INTO chat_room_members (room_id, user_id) VALUES (?, ?)");
            $ins->execute([$roomId, $userId]); 
            foreach ($memberIds as $mid) {
                $ins->execute([$roomId, (int)$mid]);
            }
            $pdo->commit();

            echo json_encode(['success' => true, 'room_id' => (int)$roomId]);
            break;

        
        case 'mark_read':
            $roomId = (int)($_POST['room_id'] ?? 0);
            $pdo->prepare("UPDATE chat_room_members SET last_read_at = NOW() WHERE room_id = ? AND user_id = ?")
                ->execute([$roomId, $userId]);
            echo json_encode(['success' => true]);
            break;

        
        case 'online_users':
            $stmt = $pdo->query("
                SELECT u.id, u.full_name, u.username, u.avatar, u.role,
                       COALESCE(uos.is_online, 0) as is_online,
                       uos.last_seen
                FROM users u
                LEFT JOIN user_online_status uos ON u.id = uos.user_id
                WHERE u.is_active = 1
                ORDER BY uos.is_online DESC, u.full_name ASC
            ");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $users]);
            break;

        
        case 'all_users':
            $stmt = $pdo->prepare("
                SELECT u.id, u.full_name, u.username, u.avatar, u.role,
                       COALESCE(uos.is_online, 0) as is_online
                FROM users u
                LEFT JOIN user_online_status uos ON u.id = uos.user_id
                WHERE u.is_active = 1 AND u.id != ?
                ORDER BY u.full_name ASC
            ");
            $stmt->execute([$userId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        
        case 'room_info':
            $roomId = (int)($_GET['room_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT cr.*, 
                    (SELECT COUNT(*) FROM chat_room_members WHERE room_id = cr.id) as member_count
                FROM chat_rooms cr
                JOIN chat_room_members crm ON crm.room_id = cr.id AND crm.user_id = ?
                WHERE cr.id = ?
            ");
            $stmt->execute([$userId, $roomId]);
            $room = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$room) {
                echo json_encode(['success' => false, 'message' => 'Room tidak ditemukan.']);
                exit;
            }
            
            $members = $pdo->prepare("
                SELECT u.id, u.full_name, u.username, u.avatar, u.role,
                       COALESCE(uos.is_online, 0) as is_online
                FROM chat_room_members crm
                JOIN users u ON crm.user_id = u.id
                LEFT JOIN user_online_status uos ON u.id = uos.user_id
                WHERE crm.room_id = ?
                ORDER BY u.full_name ASC
            ");
            $members->execute([$roomId]);
            $room['members'] = $members->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $room]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action tidak valid.']);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
