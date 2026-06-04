<?php
 
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? '';

try {
    switch ($action) {

        
        
        
        case 'submit':
            if (!verify_csrf($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid. Silakan refresh halaman.']);
                exit;
            }

            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $pageUrl = trim($_POST['page_url'] ?? '');
            $category = $_POST['category'] ?? 'bug';
            $priority = $_POST['priority'] ?? 'medium';

            
            if (empty($title) || empty($description)) {
                echo json_encode(['success' => false, 'message' => 'Judul dan deskripsi wajib diisi.']);
                exit;
            }
            if (strlen($title) > 255) {
                echo json_encode(['success' => false, 'message' => 'Judul maksimal 255 karakter.']);
                exit;
            }
            if (strlen($description) > 5000) {
                echo json_encode(['success' => false, 'message' => 'Deskripsi maksimal 5000 karakter.']);
                exit;
            }

            $validCategories = ['bug', 'feature', 'ui', 'performance', 'security', 'other'];
            $validPriorities = ['low', 'medium', 'high', 'critical'];
            if (!in_array($category, $validCategories)) $category = 'bug';
            if (!in_array($priority, $validPriorities)) $priority = 'medium';

            
            $screenshotPath = null;
            if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['screenshot'];
                $maxSize = 5 * 1024 * 1024; 

                if ($file['size'] > $maxSize) {
                    echo json_encode(['success' => false, 'message' => 'Ukuran file screenshot maksimal 5MB.']);
                    exit;
                }

                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($file['tmp_name']);

                if (!in_array($mimeType, $allowedTypes)) {
                    echo json_encode(['success' => false, 'message' => 'Format file harus JPG, PNG, GIF, atau WebP.']);
                    exit;
                }

                $uploadDir = __DIR__ . '/../../storage/uploads/bug-reports/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'bug_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $targetPath = $uploadDir . $filename;

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    // Compress image for faster loading
                    if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'webp'])) {
                        require_once __DIR__ . '/../helpers/image-compress.php';
                        compressUploadedImage($targetPath, 1280, 1280, 75);
                    }
                    $screenshotPath = 'storage/uploads/bug-reports/' . $filename;
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO bug_reports (user_id, title, description, page_url, category, priority, screenshot_path)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $title, $description, $pageUrl ?: null, $category, $priority, $screenshotPath]);

            echo json_encode(['success' => true, 'message' => 'Laporan bug berhasil dikirim. Terima kasih!']);
            break;

        
        
        
        case 'my_reports':
            $stmt = $pdo->prepare("
                SELECT br.*, COALESCE(u.full_name, br.reporter_name, 'Anonim') as reporter_name,
                       ru.full_name as resolver_name
                FROM bug_reports br
                LEFT JOIN users u ON br.user_id = u.id
                LEFT JOIN users ru ON br.resolved_by = ru.id
                WHERE br.user_id = ?
                ORDER BY br.created_at DESC
            ");
            $stmt->execute([$userId]);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $reports]);
            break;

        
        
        
        case 'all_reports':
            if (!has_role('administrator')) {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
                exit;
            }

            $status = $_GET['status'] ?? '';
            $category = $_GET['category'] ?? '';
            $priority = $_GET['priority'] ?? '';

            $sql = "
                SELECT br.*, COALESCE(u.full_name, br.reporter_name, 'Anonim') as reporter_name, u.username as reporter_username,
                       ru.full_name as resolver_name
                FROM bug_reports br
                LEFT JOIN users u ON br.user_id = u.id
                LEFT JOIN users ru ON br.resolved_by = ru.id
                WHERE 1=1
            ";
            $params = [];

            if ($status && in_array($status, ['open', 'in_progress', 'resolved', 'closed', 'wont_fix'])) {
                $sql .= " AND br.status = ?";
                $params[] = $status;
            }
            if ($category && in_array($category, ['bug', 'feature', 'ui', 'performance', 'security', 'other'])) {
                $sql .= " AND br.category = ?";
                $params[] = $category;
            }
            if ($priority && in_array($priority, ['low', 'medium', 'high', 'critical'])) {
                $sql .= " AND br.priority = ?";
                $params[] = $priority;
            }

            $sql .= " ORDER BY 
                CASE br.priority 
                    WHEN 'critical' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'medium' THEN 3 
                    WHEN 'low' THEN 4 
                END,
                br.created_at DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

            
            $statsStmt = $pdo->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
                    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_count,
                    SUM(CASE WHEN priority = 'critical' AND status IN ('open','in_progress') THEN 1 ELSE 0 END) as critical_active
                FROM bug_reports
            ");
            $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $reports, 'stats' => $stats]);
            break;

        
        
        
        case 'update_status':
            if (!has_role('administrator')) {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
                exit;
            }
            if (!verify_csrf($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid.']);
                exit;
            }

            $reportId = (int)($_POST['report_id'] ?? 0);
            $newStatus = $_POST['status'] ?? '';
            $adminNotes = trim($_POST['admin_notes'] ?? '');

            $validStatuses = ['open', 'in_progress', 'resolved', 'closed', 'wont_fix'];
            if (!$reportId || !in_array($newStatus, $validStatuses)) {
                echo json_encode(['success' => false, 'message' => 'Data tidak valid.']);
                exit;
            }

            $resolvedBy = null;
            $resolvedAt = null;
            if (in_array($newStatus, ['resolved', 'closed', 'wont_fix'])) {
                $resolvedBy = $userId;
                $resolvedAt = date('Y-m-d H:i:s');
            }

            $stmt = $pdo->prepare("
                UPDATE bug_reports 
                SET status = ?, admin_notes = ?, resolved_by = ?, resolved_at = ?
                WHERE id = ?
            ");
            $stmt->execute([$newStatus, $adminNotes ?: null, $resolvedBy, $resolvedAt, $reportId]);

            echo json_encode(['success' => true, 'message' => 'Status laporan berhasil diperbarui.']);
            break;

        
        
        
        case 'delete':
            if (!has_role('administrator')) {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
                exit;
            }
            if (!verify_csrf($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid.']);
                exit;
            }

            $reportId = (int)($_POST['report_id'] ?? 0);
            if (!$reportId) {
                echo json_encode(['success' => false, 'message' => 'ID laporan tidak valid.']);
                exit;
            }

            
            $stmt = $pdo->prepare("SELECT screenshot_path FROM bug_reports WHERE id = ?");
            $stmt->execute([$reportId]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($report && $report['screenshot_path']) {
                $filePath = __DIR__ . '/../../' . $report['screenshot_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            $stmt = $pdo->prepare("DELETE FROM bug_reports WHERE id = ?");
            $stmt->execute([$reportId]);

            echo json_encode(['success' => true, 'message' => 'Laporan berhasil dihapus.']);
            break;

        
        
        
        case 'detail':
            $reportId = (int)($_GET['id'] ?? 0);
            if (!$reportId) {
                echo json_encode(['success' => false, 'message' => 'ID tidak valid.']);
                exit;
            }

            $sql = "
                SELECT br.*, COALESCE(u.full_name, br.reporter_name, 'Anonim') as reporter_name, u.username as reporter_username,
                       u.email as reporter_email, ru.full_name as resolver_name
                FROM bug_reports br
                LEFT JOIN users u ON br.user_id = u.id
                LEFT JOIN users ru ON br.resolved_by = ru.id
                WHERE br.id = ?
            ";
            $params = [$reportId];

            
            if (!has_role('administrator')) {
                $sql .= " AND br.user_id = ?";
                $params[] = $userId;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$report) {
                echo json_encode(['success' => false, 'message' => 'Laporan tidak ditemukan.']);
                exit;
            }

            echo json_encode(['success' => true, 'data' => $report]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
            break;
    }
} catch (PDOException $e) {
    error_log("Bug report error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem. Silakan coba lagi.']);
}
