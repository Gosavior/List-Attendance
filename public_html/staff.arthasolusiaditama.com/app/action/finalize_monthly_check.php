<?php

require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Invalid request method";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}


if ($_SESSION['role'] !== 'administrator') {
    $_SESSION['error_message'] = "Access denied";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

try {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $month = $_POST['month'] ?? date('Y-m');
    
    if (!$user_id) {
        $_SESSION['error_message'] = "User ID is required";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    
    $check_stmt = $pdo->prepare("
        SELECT id FROM monthly_checks 
        WHERE user_id = ? AND check_month = ?
    ");
    $check_stmt->execute([$user_id, $month]);
    $monthly_check = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$monthly_check) {
        $_SESSION['error_message'] = "Monthly check record not found";
        header("Location: ../../dashboard.php?page=check-monthly-tools&user_id=$user_id&month=$month");
        exit;
    }
    
    
    $update_stmt = $pdo->prepare("
        UPDATE monthly_checks SET checked_at = NOW() WHERE id = ?
    ");
    $update_stmt->execute([$monthly_check['id']]);
    
    $_SESSION['success_message'] = "Pengecekan bulanan berhasil diselesaikan!";
    header("Location: ../../dashboard.php?page=check-monthly-tools&user_id=$user_id&month=$month");
    exit;
    
} catch (Exception $e) {
    error_log("Monthly tools finalize error: " . $e->getMessage());
    $_SESSION['error_message'] = "Terjadi kesalahan: " . $e->getMessage();
    
    $user_id = (int)($_POST['user_id'] ?? 0);
    $month = $_POST['month'] ?? date('Y-m');
    header("Location: ../../dashboard.php?page=check-monthly-tools&user_id=$user_id&month=$month");
    exit;
}
?>