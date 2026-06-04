<?php

/**
 * Audit Log Helper
 * Mencatat semua aksi admin/user penting ke tabel audit_logs
 */

function auditLog(PDO $pdo, string $action, array $options = []): bool {
    $userId = $options['user_id'] ?? ($_SESSION['user_id'] ?? null);
    $targetUserId = $options['target_user_id'] ?? null;
    $targetType = $options['target_type'] ?? null;
    $targetId = $options['target_id'] ?? null;
    $details = $options['details'] ?? '';
    $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;

    if (is_array($details)) {
        $details = json_encode($details, JSON_UNESCAPED_UNICODE);
    }

    try {
        // Ensure table exists
        static $tableChecked = false;
        if (!$tableChecked) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS audit_logs (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED DEFAULT NULL,
                    action VARCHAR(100) NOT NULL,
                    target_type VARCHAR(50) DEFAULT NULL,
                    target_id INT UNSIGNED DEFAULT NULL,
                    target_user_id INT UNSIGNED DEFAULT NULL,
                    details TEXT DEFAULT NULL,
                    ip_address VARCHAR(45) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_audit_user (user_id),
                    INDEX idx_audit_action (action),
                    INDEX idx_audit_target (target_type, target_id),
                    INDEX idx_audit_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $tableChecked = true;
        }

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, target_type, target_id, target_user_id, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId ? (int)$userId : null,
            $action,
            $targetType,
            $targetId ? (int)$targetId : null,
            $targetUserId ? (int)$targetUserId : null,
            $details ?: null,
            $ipAddress
        ]);
        return true;
    } catch (Exception $e) {
        error_log('[auditLog] Failed: ' . $e->getMessage());
        return false;
    }
}
