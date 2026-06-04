<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/audit-log.php';
require_once __DIR__ . '/../helpers/socket-notify.php';

header('Content-Type: application/json');


try {
    $cols = $pdo->query("SHOW COLUMNS FROM tool_permits LIKE 'admin_photo_path'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE tool_permits ADD COLUMN admin_photo_path VARCHAR(500) DEFAULT NULL AFTER photo_proof_path");
    }
} catch (Throwable $e) {   }


function upload_admin_photo($file, int $user_id): ?string {
    if (!$file || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $basePath = __DIR__ . "/../../storage/uploads/tools/admin_verify/" . $user_id . "/";
    if (!is_dir($basePath)) mkdir($basePath, 0775, true);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg');
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed)) return null;
    if ($file['size'] > 10 * 1024 * 1024) return null;
    $name = uniqid("admin_verify_") . "." . $ext;
    $target = $basePath . $name;
    if (!move_uploaded_file($file['tmp_name'], $target)) return null;
    
    if (function_exists('imagecreatefromjpeg') && in_array($ext, ['jpg','jpeg','png'])) {
        $img = ($ext === 'png') ? @imagecreatefrompng($target) : @imagecreatefromjpeg($target);
        if ($img) {
            $w = imagesx($img); $h = imagesy($img);
            if ($w > 1280) { $nh = (int)($h * 1280 / $w); $resized = imagecreatetruecolor(1280, $nh); imagecopyresampled($resized, $img, 0, 0, 0, 0, 1280, $nh, $w, $h); imagedestroy($img); $img = $resized; }
            imagejpeg($img, $target, 75); imagedestroy($img);
        }
    }
    return "storage/uploads/tools/admin_verify/$user_id/$name";
}


$ids = $_POST['ids'] ?? [];
$action = $_POST['action'] ?? '';

if (!empty($ids) && is_array($ids) && in_array($action, ['approve', 'reject'])) {
    
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    if ($role !== 'administrator') {
        echo json_encode(['success'=>false,'message'=>'Unauthorized: bulk action is admin only']);
        exit;
    }
    
    $successCount = 0;
    $failCount = 0;
    $pdo->beginTransaction();
    try {
        foreach ($ids as $rawId) {
            $permitId = (int)$rawId;
            if ($permitId <= 0) { $failCount++; continue; }
            
            $stmt = $pdo->prepare("SELECT tp.*, t.current_status, t.name as tool_name
                                   FROM tool_permits tp
                                   JOIN tools t ON tp.tool_id = t.id
                                   WHERE tp.id = ? AND tp.status = 'pending'");
            $stmt->execute([$permitId]);
            $permit = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$permit) { $failCount++; continue; }
            
            $status = $action === 'approve' ? 'approved' : 'rejected';
            
            
            $pdo->prepare("UPDATE tool_permits SET status=?, approved_at=NOW(), approved_by=? WHERE id=?")
                ->execute([$status, $user_id, $permitId]);
            
            if ($action === 'approve') {
                
                $newStatus = match($permit['permit_type']) {
                    'loan' => 'Loan',
                    'handover' => 'Handover',
                    'return' => 'Ready',
                    'project' => 'Project',
                    default => 'Ready'
                };
                
                
                $pdo->prepare("INSERT INTO tool_status_history (tool_id, from_status, to_status, user_id, notes, photo_proof_path) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$permit['tool_id'], $permit['current_status'], $newStatus, $user_id, $permit['reason'] ?? 'Bulk approve', $permit['photo_proof_path'] ?? null]);
                
                
                $pdo->prepare("UPDATE tools SET current_status=? WHERE id=?")
                    ->execute([$newStatus, $permit['tool_id']]);
            }
            
            $successCount++;
        }
        $pdo->commit();
        $actionLabel = $action === 'approve' ? 'di-approve' : 'di-reject';
        auditLog($pdo, 'bulk_' . $action . '_tool_permit', [
            'target_type' => 'tool_permit',
            'details' => ['count' => $successCount, 'permit_ids' => array_map('intval', $ids)]
        ]);
        echo json_encode(['success'=>true,'message'=>"$successCount permit berhasil $actionLabel" . ($failCount > 0 ? ", $failCount gagal" : '')]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>'Error: ' . $e->getMessage()]);
    }
    exit;
}


$id = (int)($_POST['id'] ?? 0);

if (!$id || !in_array($action, ['approve','reject'])) {
    echo json_encode(['success'=>false,'message'=>'Invalid request']);
    exit;
}


$stmt = $pdo->prepare("SELECT tp.*, t.current_status, t.name as tool_name
                       FROM tool_permits tp
                       JOIN tools t ON tp.tool_id = t.id
                       WHERE tp.id = ?");
$stmt->execute([$id]);
$permit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$permit) {
    echo json_encode(['success'=>false,'message'=>'Permit not found']);
    exit;
}


$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$canApprove = false;


if ($role === 'administrator') {
    
    $canApprove = true;
} else {
    
    
    
    if ($permit['permit_type'] === 'handover' && $permit['to_user_id'] == $user_id) {
        $canApprove = true;
    }
    
    
    if ($permit['permit_type'] === 'return' && $permit['to_user_id'] == $user_id) {
        $canApprove = true;
    }
    
    
    if ($permit['permit_type'] === 'handover') {
        
        $stmt = $pdo->prepare("
            SELECT tp.to_user_id 
            FROM tool_permits tp 
            WHERE tp.tool_id = ? 
            AND tp.status = 'approved' 
            AND tp.permit_type IN ('loan', 'handover')
            ORDER BY tp.id DESC LIMIT 1
        ");
        $stmt->execute([$permit['tool_id']]);
        $currentHolder = $stmt->fetchColumn();
        
        if ($currentHolder == $user_id) {
            $canApprove = true;
        }
    }
}

if (!$canApprove) {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

$status = $action === 'approve' ? 'approved' : 'rejected';


$adminPhotoPath = null;
if ($status === 'approved' && in_array($permit['permit_type'], ['loan', 'handover', 'project'])) {
    $adminPhotoPath = upload_admin_photo($_FILES['admin_photo'] ?? null, $user_id);
}


$stmt = $pdo->prepare("UPDATE tool_permits 
                       SET status=?, approved_at=NOW(), approved_by=?, admin_photo_path=?
                       WHERE id=?");
$stmt->execute([$status, $user_id, $adminPhotoPath, $id]);

if ($status === 'approved') {
    
    $newStatus = '';
    
    switch ($permit['permit_type']) {
        case 'loan':
            $newStatus = 'Loan';
            break;
        case 'handover':
            $newStatus = 'Handover';
            break;
        case 'return':  
            $newStatus = 'Ready';
            break;
        case 'project':
            $newStatus = 'Project';
            break;
        default:
            $newStatus = 'Ready';
    }

    
    $pdo->prepare("INSERT INTO tool_status_history 
          (tool_id, from_status, to_status, user_id, notes, photo_proof_path) 
          VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([
            $permit['tool_id'],
            $permit['current_status'],
            $newStatus,
            $user_id,
            $permit['reason'] ?? 'Pengembalian tool',
            $adminPhotoPath ?: ($permit['photo_proof_path'] ?? null)
        ]);

    
    $pdo->prepare("UPDATE tools SET current_status=? WHERE id=?")
        ->execute([$newStatus, $permit['tool_id']]);
}

auditLog($pdo, $action . '_tool_permit', [
    'target_type' => 'tool_permit',
    'target_id' => $id,
    'target_user_id' => (int)($permit['to_user_id'] ?? 0),
    'details' => ['tool' => $permit['tool_name'] ?? '', 'permit_type' => $permit['permit_type'], 'new_status' => $status]
]);
// Notify the user who requested
$notifyUserId = (int)($permit['from_user_id'] ?? $permit['to_user_id'] ?? 0);
if ($notifyUserId) {
    $msg = $action === 'approve' ? 'Permintaan tools Anda disetujui' : 'Permintaan tools Anda ditolak';
    socketNotify([$notifyUserId], 'tool_action', $msg);
}

echo json_encode(['success'=>true]);
?>