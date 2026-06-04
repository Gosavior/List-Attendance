<?php
 
require_once __DIR__ . '/../auth/auth.php';
requireLogin();


if (!has_role('administrator')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bug_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            page_url VARCHAR(500) DEFAULT NULL,
            category ENUM('bug','feature','ui','performance','security','other') NOT NULL DEFAULT 'bug',
            priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
            status ENUM('open','in_progress','resolved','closed','wont_fix') NOT NULL DEFAULT 'open',
            screenshot_path VARCHAR(500) DEFAULT NULL,
            admin_notes TEXT DEFAULT NULL,
            resolved_by INT DEFAULT NULL,
            resolved_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_user_id (user_id),
            INDEX idx_priority (priority),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo json_encode(['success' => true, 'message' => 'Table bug_reports created successfully']);
} catch (PDOException $e) {
    error_log("Migration error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Migration failed. Check error log.']);
}
