<?php
 
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

if ($_SESSION['role'] !== 'administrator') {
    die('Admin only');
}

$results = [];

try {
    
    $cols = $pdo->query("SHOW COLUMNS FROM chat_messages LIKE 'reply_to_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE chat_messages ADD COLUMN reply_to_id INT NULL DEFAULT NULL AFTER message_type");
        $results[] = "[OK] Added reply_to_id column";
    } else {
        $results[] = "⏭️ reply_to_id already exists";
    }

    
    $cols = $pdo->query("SHOW COLUMNS FROM chat_messages LIKE 'forwarded_from_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE chat_messages ADD COLUMN forwarded_from_id INT NULL DEFAULT NULL AFTER reply_to_id");
        $results[] = "[OK] Added forwarded_from_id column";
    } else {
        $results[] = "⏭️ forwarded_from_id already exists";
    }

    
    $cols = $pdo->query("SHOW COLUMNS FROM chat_messages LIKE 'deleted_by_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE chat_messages ADD COLUMN deleted_by_id INT NULL DEFAULT NULL AFTER is_deleted");
        $results[] = "[OK] Added deleted_by_id column";
    } else {
        $results[] = "⏭️ deleted_by_id already exists";
    }

    
    $cols = $pdo->query("SHOW COLUMNS FROM chat_messages LIKE 'deleted_at'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE chat_messages ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL AFTER deleted_by_id");
        $results[] = "[OK] Added deleted_at column";
    } else {
        $results[] = "⏭️ deleted_at already exists";
    }

    
    $stmt = $pdo->prepare("SELECT id FROM chat_rooms WHERE name = 'General' AND type = 'group'");
    $stmt->execute();
    $general = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($general) {
        $roomId = $general['id'];
        $pdo->beginTransaction();
        $pdo->exec("DELETE FROM chat_messages WHERE room_id = $roomId");
        $pdo->exec("DELETE FROM chat_room_members WHERE room_id = $roomId");
        $pdo->exec("DELETE FROM chat_rooms WHERE id = $roomId");
        $pdo->commit();
        $results[] = "[OK] Deleted General room (id: $roomId) and all its data";
    } else {
        $results[] = "⏭️ General room already removed";
    }

    echo "<h2>Chat Features Migration</h2>";
    echo "<pre>" . implode("\n", $results) . "</pre>";
    echo "<p><b>Migration complete!</b></p>";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}
