<?php
 

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../helpers/avatar.php';

header('Content-Type: application/json');

$role = $_SESSION['role'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;
$canManage = in_array($role, ['administrator', 'technician_manager']);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        
        case 'list':
            $stmt = $pdo->query("
                SELECT d.*, 
                    (SELECT COUNT(*) FROM user_divisions ud WHERE ud.division_id = d.id) as member_count,
                    u.full_name as creator_name
                FROM divisions d
                LEFT JOIN users u ON d.created_by = u.id
                WHERE d.is_active = 1
                ORDER BY d.name ASC
            ");
            $divisions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $divisions]);
            break;

        
        case 'members':
            $divisionId = (int)($_GET['division_id'] ?? $_POST['division_id'] ?? 0);
            if ($divisionId <= 0) {
                throw new Exception('Division ID tidak valid.');
            }
            $stmt = $pdo->prepare("
                SELECT u.id, u.full_name, u.username, u.email, u.role, u.avatar, u.gender,
                       ud.assigned_at, assigner.full_name as assigned_by_name
                FROM user_divisions ud
                JOIN users u ON ud.user_id = u.id
                LEFT JOIN users assigner ON ud.assigned_by = assigner.id
                WHERE ud.division_id = ?
                ORDER BY u.full_name ASC
            ");
            $stmt->execute([$divisionId]);
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($members as &$_m) {
                $_m['avatar_url'] = getAvatarUrl($_m);
            }
            unset($_m);
            echo json_encode(['success' => true, 'data' => $members]);
            break;

        
        case 'create':
            if (!$canManage) {
                throw new Exception('Anda tidak memiliki akses untuk membuat divisi.');
            }
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $color = trim($_POST['color'] ?? '#3b82f6');
            if ($name === '') {
                throw new Exception('Nama divisi tidak boleh kosong.');
            }
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                $color = '#3b82f6';
            }
            $stmt = $pdo->prepare("INSERT INTO divisions (name, description, color, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $description, $color, $userId]);
            echo json_encode(['success' => true, 'message' => 'Divisi berhasil dibuat.', 'id' => $pdo->lastInsertId()]);
            break;

        
        case 'update':
            if (!$canManage) {
                throw new Exception('Anda tidak memiliki akses untuk mengubah divisi.');
            }
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $color = trim($_POST['color'] ?? '#3b82f6');
            if ($id <= 0 || $name === '') {
                throw new Exception('Data tidak valid.');
            }
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                $color = '#3b82f6';
            }
            $stmt = $pdo->prepare("UPDATE divisions SET name = ?, description = ?, color = ? WHERE id = ?");
            $stmt->execute([$name, $description, $color, $id]);
            echo json_encode(['success' => true, 'message' => 'Divisi berhasil diperbarui.']);
            break;

        
        case 'delete':
            if (!$canManage) {
                throw new Exception('Anda tidak memiliki akses untuk menghapus divisi.');
            }
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('Division ID tidak valid.');
            }
            $stmt = $pdo->prepare("UPDATE divisions SET is_active = 0 WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Divisi berhasil dihapus.']);
            break;

        
        case 'assign':
            if (!$canManage) {
                throw new Exception('Anda tidak memiliki akses.');
            }
            $divisionId = (int)($_POST['division_id'] ?? 0);
            $targetUserId = (int)($_POST['user_id'] ?? 0);
            if ($divisionId <= 0 || $targetUserId <= 0) {
                throw new Exception('Data tidak valid.');
            }
            $stmt = $pdo->prepare("INSERT IGNORE INTO user_divisions (user_id, division_id, assigned_by) VALUES (?, ?, ?)");
            $stmt->execute([$targetUserId, $divisionId, $userId]);
            echo json_encode(['success' => true, 'message' => 'User berhasil ditambahkan ke divisi.']);
            break;

        
        case 'unassign':
            if (!$canManage) {
                throw new Exception('Anda tidak memiliki akses.');
            }
            $divisionId = (int)($_POST['division_id'] ?? 0);
            $targetUserId = (int)($_POST['user_id'] ?? 0);
            if ($divisionId <= 0 || $targetUserId <= 0) {
                throw new Exception('Data tidak valid.');
            }
            $stmt = $pdo->prepare("DELETE FROM user_divisions WHERE user_id = ? AND division_id = ?");
            $stmt->execute([$targetUserId, $divisionId]);
            echo json_encode(['success' => true, 'message' => 'User berhasil dihapus dari divisi.']);
            break;

        
        case 'all_users':
            $stmt = $pdo->query("SELECT id, full_name, username, role FROM users WHERE is_active = 1 ORDER BY full_name ASC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $users]);
            break;

        
        case 'user_divisions':
            $targetId = (int)($_GET['user_id'] ?? $userId);
            $stmt = $pdo->prepare("
                SELECT d.id, d.name, d.color, d.description
                FROM user_divisions ud
                JOIN divisions d ON ud.division_id = d.id
                WHERE ud.user_id = ? AND d.is_active = 1
                ORDER BY d.name ASC
            ");
            $stmt->execute([$targetId]);
            $divs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $divs]);
            break;

        default:
            throw new Exception('Action tidak dikenali.');
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
