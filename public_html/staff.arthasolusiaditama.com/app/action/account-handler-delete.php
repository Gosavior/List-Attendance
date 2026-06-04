<?php
session_start();
if (!in_array($_SESSION['role'], ['administrator', 'direktur'])) {
  http_response_code(403); exit('Akses ditolak.');
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/audit-log.php';

$user_id = intval($_POST['user_id'] ?? 0);
if (!$user_id) { http_response_code(400); exit('ID tidak valid'); }


$stmt = $pdo->prepare('SELECT id, avatar FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) { http_response_code(404); exit('User tidak ditemukan'); }


if ($user_id == ($_SESSION['user_id'] ?? 0)) {
    http_response_code(400); exit('Tidak bisa menghapus akun sendiri');
}

$baseDir = realpath(__DIR__ . '/../../');

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

    
    $tables_user_id = [
        'attendances', 'attendance_activities', 'leave_requests',
        'monthly_checks', 'monthly_check_items',
        'schedules', 'schedule_assignees',
        'tool_assignments', 'tool_permits', 'tool_status_history',
        'login_attempts', 'password_reset_requests',
        'bug_reports', 'chat_messages', 'chat_room_members',
        'material_requests', 'material_request_approvals',
        'project_daily_updates',
        'user_divisions', 'user_online_status', 'user_tokens',
        'gps_logs',
    ];

    foreach ($tables_user_id as $table) {
        try {
            $pdo->prepare("DELETE FROM `$table` WHERE user_id = ?")->execute([$user_id]);
        } catch (Exception $e) {
            error_log("Skip deleting from $table (user_id): " . $e->getMessage());
        }
    }

    
    
    try {
        $pdo->prepare("DELETE FROM tool_permits WHERE from_user_id = ?")->execute([$user_id]);
    } catch (Exception $e) {   }

    
    
    try {
        $pdo->prepare("DELETE FROM chat_rooms WHERE created_by = ?")->execute([$user_id]);
    } catch (Exception $e) {   }

    
    $avatarDir = $baseDir . '/storage/uploads/avatar/' . $user_id;
    if (is_dir($avatarDir)) {
        $files = glob($avatarDir . '/*');
        foreach ($files as $f) { if (is_file($f)) @unlink($f); }
        @rmdir($avatarDir);
    }

    
    try {
        $stmt = $pdo->prepare("SELECT photos FROM project_daily_updates WHERE user_id = ?");
        $stmt->execute([$user_id]);
        while ($row = $stmt->fetch()) {
            $photos = json_decode($row['photos'] ?? '[]', true) ?: [];
            foreach ($photos as $ph) {
                $path = $baseDir . '/' . $ph;
                if (file_exists($path)) @unlink($path);
            }
        }
    } catch (Exception $e) {   }

    
    try {
        $stmt = $pdo->prepare("SELECT mr.id FROM material_requests mr WHERE mr.user_id = ?");
        $stmt->execute([$user_id]);
        $mrIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if ($mrIds) {
            $placeholders = implode(',', array_fill(0, count($mrIds), '?'));
            $stmt = $pdo->prepare("SELECT file_path FROM material_request_approvals WHERE request_id IN ($placeholders)");
            $stmt->execute($mrIds);
            while ($row = $stmt->fetch()) {
                if (!empty($row['file_path'])) {
                    $path = $baseDir . '/' . $row['file_path'];
                    if (file_exists($path)) @unlink($path);
                }
            }
            $pdo->prepare("DELETE FROM material_request_items WHERE request_id IN ($placeholders)")->execute($mrIds);
            $pdo->prepare("DELETE FROM material_request_approvals WHERE request_id IN ($placeholders)")->execute($mrIds);
        }
    } catch (Exception $e) {
        error_log("Skip material_request cleanup: " . $e->getMessage());
    }

    
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$user_id]);

    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

    auditLog($pdo, 'delete_user', [
        'target_type' => 'user',
        'target_id' => $user_id,
        'target_user_id' => $user_id,
        'details' => ['deleted_user_id' => $user_id]
    ]);

    echo 'OK';
} catch (PDOException $e) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    http_response_code(500);
    error_log('Delete user error: ' . $e->getMessage());
    echo 'Gagal hapus akun: ' . $e->getMessage();
}
