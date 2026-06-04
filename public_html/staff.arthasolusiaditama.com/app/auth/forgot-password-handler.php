<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../config/database.php';
    
    $username_or_email = trim($_POST['username_or_email'] ?? '');
    
    if (empty($username_or_email)) {
        echo "ERROR: Username atau email harus diisi";
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, username, email, full_name, role 
            FROM users 
            WHERE (username = ? OR email = ?) AND is_active = 1
        ");
        $stmt->execute([$username_or_email, $username_or_email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO password_reset_requests (user_id, status, created_at) 
                    VALUES (?, 'pending', NOW())
                    ON DUPLICATE KEY UPDATE status = 'pending', created_at = NOW(), processed_at = NULL, processed_by = NULL
                ");
                $stmt->execute([$user['id']]);
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), "doesn't exist") !== false) {
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS password_reset_requests (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id INT NOT NULL,
                            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                            processed_at DATETIME NULL,
                            processed_by INT NULL,
                            notes TEXT NULL,
                            UNIQUE KEY unique_user_pending (user_id, status),
                            KEY idx_status (status),
                            KEY idx_created (created_at),
                            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                            FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO password_reset_requests (user_id, status, created_at) 
                        VALUES (?, 'pending', NOW())
                    ");
                    $stmt->execute([$user['id']]);
                }
            }
            
            error_log(sprintf(
                "Password reset request created for user: %s (ID: %d). Waiting for admin approval.",
                $user['username'],
                $user['id']
            ));
            
            echo "OK|request_created";
            
        } else {
            error_log("Password reset attempt for non-existent user: {$username_or_email}");
            echo "OK|request_created";
        }
        
    } catch (Exception $e) {
        error_log("Forgot password error: " . $e->getMessage());
        echo "ERROR: " . $e->getMessage();
    }
}
