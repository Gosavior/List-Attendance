<?php
 
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = new PDO(
    "mysql:host=145.79.8.194;dbname=arth_Staff;charset=utf8mb4",
    "arth_Staff_database",
    "Info-asa1.com",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
echo "Connected\n";


$pdo->exec("
    CREATE TABLE IF NOT EXISTS chat_rooms (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NULL,
        type ENUM('direct','group') DEFAULT 'direct',
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_type (type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "chat_rooms table created\n";


$pdo->exec("
    CREATE TABLE IF NOT EXISTS chat_room_members (
        id INT PRIMARY KEY AUTO_INCREMENT,
        room_id INT NOT NULL,
        user_id INT NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_read_at TIMESTAMP NULL,
        UNIQUE KEY uk_room_user (room_id, user_id),
        INDEX idx_user (user_id),
        FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "chat_room_members table created\n";


$pdo->exec("
    CREATE TABLE IF NOT EXISTS chat_messages (
        id INT PRIMARY KEY AUTO_INCREMENT,
        room_id INT NOT NULL,
        sender_id INT NOT NULL,
        message TEXT NOT NULL,
        message_type ENUM('text','image','file') DEFAULT 'text',
        file_url VARCHAR(500) NULL,
        is_deleted TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_room_created (room_id, created_at),
        FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "chat_messages table created\n";


$pdo->exec("
    CREATE TABLE IF NOT EXISTS user_online_status (
        user_id INT PRIMARY KEY,
        is_online TINYINT(1) DEFAULT 0,
        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        socket_id VARCHAR(100) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "user_online_status table created\n";


$stmt = $pdo->prepare("SELECT id FROM chat_rooms WHERE name = 'General' AND type = 'group' LIMIT 1");
$stmt->execute();
if (!$stmt->fetch()) {
    $pdo->exec("INSERT INTO chat_rooms (name, type) VALUES ('General', 'group')");
    $generalId = $pdo->lastInsertId();
    
    $users = $pdo->query("SELECT id FROM users WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
    $insertMember = $pdo->prepare("INSERT IGNORE INTO chat_room_members (room_id, user_id) VALUES (?, ?)");
    foreach ($users as $uid) {
        $insertMember->execute([$generalId, $uid]);
    }
    echo "General chat room created with " . count($users) . " members\n";
} else {
    echo "General chat room already exists\n";
}

echo "DONE\n";
