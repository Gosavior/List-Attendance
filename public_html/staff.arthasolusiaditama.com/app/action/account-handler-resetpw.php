<?php
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "ERROR: Not authenticated";
    exit;
}

$user_role = strtolower(trim($_SESSION['role'] ?? ''));
$is_admin = preg_match('/admin|administrator|technician_manager|direktur/', $user_role);

if (!$is_admin) {
    http_response_code(403);
    echo "ERROR: Insufficient privileges";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "ERROR: Method not allowed";
    exit;
}

$user_id = trim($_POST['user_id'] ?? '');
$new_password = $_POST['new_password'] ?? '';

if (empty($user_id) || !is_numeric($user_id)) {
    echo "ERROR: Invalid user ID";
    exit;
}

if (empty($new_password)) {
    echo "ERROR: Password cannot be empty";
    exit;
}

if (strlen($new_password) < 6) {
    echo "ERROR: Password must be at least 6 characters";
    exit;
}

try {
    require_once __DIR__ . '/../config/database.php';
    
    $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $target_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$target_user) {
        echo "ERROR: User not found";
        exit;
    }
    
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $update_result = $update_stmt->execute([$hashed_password, $user_id]);
    
    if ($update_result) {
        error_log(sprintf(
            "Password reset: Admin %s (ID: %d) reset password for user %s (ID: %d)",
            $_SESSION['username'] ?? 'unknown',
            $_SESSION['user_id'] ?? 0,
            $target_user['username'],
            $user_id
        ));
        
        try {
            $audit_stmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action, target_user_id, details, created_at) 
                VALUES (?, 'password_reset', ?, ?, NOW())
            ");
            $audit_stmt->execute([
                $_SESSION['user_id'],
                $user_id,
                "Password reset for user: {$target_user['username']}"
            ]);
        } catch (PDOException $e) {
            error_log("Audit log failed: " . $e->getMessage());
        }
        
        echo "OK";
    } else {
        echo "ERROR: Failed to update password";
    }
    
} catch (PDOException $e) {
    error_log("Password reset error: " . $e->getMessage());
    echo "ERROR: Database error - " . $e->getMessage();
} catch (Exception $e) {
    error_log("Password reset exception: " . $e->getMessage());
    echo "ERROR: System error - " . $e->getMessage();
}