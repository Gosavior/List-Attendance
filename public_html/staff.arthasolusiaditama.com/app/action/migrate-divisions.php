<?php
 

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

try {
    $pdo->beginTransaction();

    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS divisions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT NULL,
            color VARCHAR(7) DEFAULT '#3b82f6',
            is_active TINYINT(1) DEFAULT 1,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_division_name (name),
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_divisions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            division_id INT NOT NULL,
            assigned_by INT NULL,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_user_division (user_id, division_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (division_id) REFERENCES divisions(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    
    $defaults = [
        ['IT', 'Divisi Teknologi Informasi', '#3b82f6'],
        ['Finance & Accounting', 'Divisi Keuangan dan Akuntansi', '#10b981'],
        ['Sales Engineering', 'Divisi Sales Engineering', '#f59e0b'],
        ['Technician', 'Divisi Teknisi', '#8b5cf6'],
        ['Driver', 'Divisi Driver', '#ef4444'],
        ['Safety Officer', 'Divisi Safety Officer', '#06b6d4'],
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO divisions (name, description, color) VALUES (?, ?, ?)");
    foreach ($defaults as $div) {
        $stmt->execute($div);
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Migration divisions berhasil dijalankan.']);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Migration gagal: ' . $e->getMessage()]);
}
