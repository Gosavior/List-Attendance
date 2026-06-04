<?php
require_once __DIR__ . '/../../auth/auth.php';
requireLogin();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/audit-log.php';
require_once __DIR__ . '/../../helpers/socket-notify.php';

header('Content-Type: application/json');

$currentUserId = (int)$_SESSION['user_id'];
$currentRole = $_SESSION['role'] ?? '';
$action = $_REQUEST['action'] ?? '';

// Helper: send JSON response
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Helper: create notification
function createTaskNotification($pdo, $taskId, $userId, $type, $message) {
    if ($userId == $_SESSION['user_id']) return; // don't notify self
    $stmt = $pdo->prepare("INSERT INTO task_notifications (task_id, user_id, type, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$taskId, $userId, $type, $message]);
    // Real-time notification via Socket.IO
    socketNotify([$userId], 'task_' . $type, $message);
}

// Helper: log task activity
function logTaskActivity($pdo, $taskId, $userId, $action, $oldValue = null, $newValue = null) {
    $stmt = $pdo->prepare("INSERT INTO task_activity_log (task_id, user_id, action, old_value, new_value) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$taskId, $userId, $action, $oldValue, $newValue]);
}

switch ($action) {

    // ==================== GET TASKS ====================
    case 'get_tasks':
        $status = $_GET['status'] ?? null;
        $assignee = $_GET['assignee'] ?? null;
        $priority = $_GET['priority'] ?? null;
        $search = $_GET['search'] ?? null;

        $sql = "SELECT t.*, 
                    u1.full_name AS creator_name, u1.avatar AS creator_avatar,
                    u2.full_name AS assignee_name, u2.avatar AS assignee_avatar,
                    (SELECT COUNT(*) FROM task_comments WHERE task_id = t.id) AS comment_count,
                    (SELECT COUNT(*) FROM task_attachments WHERE task_id = t.id) AS attachment_count
                FROM tasks t
                LEFT JOIN users u1 ON t.created_by = u1.id
                LEFT JOIN users u2 ON t.assigned_to = u2.id
                WHERE 1=1";
        $params = [];

        if ($status) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }
        if ($assignee) {
            $sql .= " AND t.assigned_to = ?";
            $params[] = (int)$assignee;
        }
        if ($priority) {
            $sql .= " AND t.priority = ?";
            $params[] = $priority;
        }
        if ($search) {
            $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql .= " ORDER BY FIELD(t.priority, 'urgent', 'high', 'medium', 'low'), t.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse(['tasks' => $tasks]);
        break;

    // ==================== GET SINGLE TASK ====================
    case 'get_task':
        $taskId = (int)($_GET['id'] ?? 0);
        if (!$taskId) jsonResponse(['error' => 'ID task tidak valid'], 400);

        $stmt = $pdo->prepare("SELECT t.*, 
                    u1.full_name AS creator_name, u1.avatar AS creator_avatar,
                    u2.full_name AS assignee_name, u2.avatar AS assignee_avatar
                FROM tasks t
                LEFT JOIN users u1 ON t.created_by = u1.id
                LEFT JOIN users u2 ON t.assigned_to = u2.id
                WHERE t.id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) jsonResponse(['error' => 'Task tidak ditemukan'], 404);

        // Get comments
        $stmt = $pdo->prepare("SELECT tc.*, u.full_name, u.avatar FROM task_comments tc JOIN users u ON tc.user_id = u.id WHERE tc.task_id = ? ORDER BY tc.created_at ASC");
        $stmt->execute([$taskId]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get attachments
        $stmt = $pdo->prepare("SELECT ta.*, u.full_name FROM task_attachments ta JOIN users u ON ta.user_id = u.id WHERE ta.task_id = ? ORDER BY ta.created_at DESC");
        $stmt->execute([$taskId]);
        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get activity log
        $stmt = $pdo->prepare("SELECT tal.*, u.full_name FROM task_activity_log tal JOIN users u ON tal.user_id = u.id WHERE tal.task_id = ? ORDER BY tal.created_at DESC LIMIT 20");
        $stmt->execute([$taskId]);
        $activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse(['task' => $task, 'comments' => $comments, 'attachments' => $attachments, 'activity' => $activity]);
        break;

    // ==================== CREATE TASK ====================
    case 'create_task':
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
        $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;

        if (!$title) jsonResponse(['error' => 'Judul task wajib diisi'], 400);
        if (!in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
            jsonResponse(['error' => 'Priority tidak valid'], 400);
        }
        if ($deadline && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
            jsonResponse(['error' => 'Format deadline tidak valid'], 400);
        }

        $stmt = $pdo->prepare("INSERT INTO tasks (title, description, priority, created_by, assigned_to, deadline) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $priority, $currentUserId, $assignedTo, $deadline]);
        $taskId = (int)$pdo->lastInsertId();

        logTaskActivity($pdo, $taskId, $currentUserId, 'created');

        if ($assignedTo) {
            $creatorName = $_SESSION['full_name'] ?? 'Someone';
            createTaskNotification($pdo, $taskId, $assignedTo, 'assigned', "$creatorName menugaskan task \"$title\" kepada Anda");
            logTaskActivity($pdo, $taskId, $currentUserId, 'assigned', null, (string)$assignedTo);
        }

        auditLog($pdo, 'task_created', [
            'target_type' => 'task',
            'target_id' => $taskId,
            'details' => ['title' => $title, 'assigned_to' => $assignedTo]
        ]);

        jsonResponse(['success' => true, 'task_id' => $taskId, 'message' => 'Task berhasil dibuat']);
        break;

    // ==================== UPDATE TASK ====================
    case 'update_task':
        $taskId = (int)($_POST['task_id'] ?? 0);
        if (!$taskId) jsonResponse(['error' => 'ID task tidak valid'], 400);

        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$task) jsonResponse(['error' => 'Task tidak ditemukan'], 404);

        $title = trim($_POST['title'] ?? $task['title']);
        $description = trim($_POST['description'] ?? $task['description']);
        $priority = $_POST['priority'] ?? $task['priority'];
        $assignedTo = isset($_POST['assigned_to']) ? (!empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null) : $task['assigned_to'];
        $deadline = isset($_POST['deadline']) ? (!empty($_POST['deadline']) ? $_POST['deadline'] : null) : $task['deadline'];

        if (!$title) jsonResponse(['error' => 'Judul task wajib diisi'], 400);

        $stmt = $pdo->prepare("UPDATE tasks SET title = ?, description = ?, priority = ?, assigned_to = ?, deadline = ? WHERE id = ?");
        $stmt->execute([$title, $description, $priority, $assignedTo, $deadline, $taskId]);

        logTaskActivity($pdo, $taskId, $currentUserId, 'updated');

        // Notify if assignment changed
        if ($assignedTo && $assignedTo != $task['assigned_to']) {
            $creatorName = $_SESSION['full_name'] ?? 'Someone';
            createTaskNotification($pdo, $taskId, $assignedTo, 'assigned', "$creatorName menugaskan task \"$title\" kepada Anda");
            logTaskActivity($pdo, $taskId, $currentUserId, 'assigned', (string)$task['assigned_to'], (string)$assignedTo);
        }

        jsonResponse(['success' => true, 'message' => 'Task berhasil diupdate']);
        break;

    // ==================== CHANGE STATUS ====================
    case 'change_status':
        $taskId = (int)($_POST['task_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';

        if (!$taskId) jsonResponse(['error' => 'ID task tidak valid'], 400);
        if (!in_array($newStatus, ['todo', 'in_progress', 'review', 'done'])) {
            jsonResponse(['error' => 'Status tidak valid'], 400);
        }

        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$task) jsonResponse(['error' => 'Task tidak ditemukan'], 404);

        $completedAt = ($newStatus === 'done') ? date('Y-m-d H:i:s') : null;

        $stmt = $pdo->prepare("UPDATE tasks SET status = ?, completed_at = ? WHERE id = ?");
        $stmt->execute([$newStatus, $completedAt, $taskId]);

        logTaskActivity($pdo, $taskId, $currentUserId, 'status_changed', $task['status'], $newStatus);

        // Notify creator and assignee about status change
        $changerName = $_SESSION['full_name'] ?? 'Someone';
        $statusLabels = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'review' => 'Review', 'done' => 'Done'];
        $msg = "$changerName mengubah status task \"{$task['title']}\" menjadi {$statusLabels[$newStatus]}";

        if ($task['created_by'] != $currentUserId) {
            createTaskNotification($pdo, $taskId, $task['created_by'], 'status_changed', $msg);
        }
        if ($task['assigned_to'] && $task['assigned_to'] != $currentUserId) {
            createTaskNotification($pdo, $taskId, $task['assigned_to'], 'status_changed', $msg);
        }

        jsonResponse(['success' => true, 'message' => 'Status berhasil diubah']);
        break;

    // ==================== DELETE TASK ====================
    case 'delete_task':
        $taskId = (int)($_POST['task_id'] ?? 0);
        if (!$taskId) jsonResponse(['error' => 'ID task tidak valid'], 400);

        // Only admin/direktur or task creator can delete
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$task) jsonResponse(['error' => 'Task tidak ditemukan'], 404);

        if ($task['created_by'] != $currentUserId && !in_array($currentRole, ['administrator', 'direktur'])) {
            jsonResponse(['error' => 'Anda tidak memiliki izin untuk menghapus task ini'], 403);
        }

        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);

        auditLog($pdo, 'task_deleted', [
            'target_type' => 'task',
            'target_id' => $taskId,
            'details' => ['title' => $task['title']]
        ]);

        jsonResponse(['success' => true, 'message' => 'Task berhasil dihapus']);
        break;

    // ==================== ADD COMMENT ====================
    case 'add_comment':
        $taskId = (int)($_POST['task_id'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if (!$taskId) jsonResponse(['error' => 'ID task tidak valid'], 400);
        if (!$comment) jsonResponse(['error' => 'Komentar tidak boleh kosong'], 400);

        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$task) jsonResponse(['error' => 'Task tidak ditemukan'], 404);

        $stmt = $pdo->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)");
        $stmt->execute([$taskId, $currentUserId, $comment]);

        logTaskActivity($pdo, $taskId, $currentUserId, 'commented');

        // Notify task creator and assignee
        $commenterName = $_SESSION['full_name'] ?? 'Someone';
        $msg = "$commenterName berkomentar di task \"{$task['title']}\"";

        if ($task['created_by'] != $currentUserId) {
            createTaskNotification($pdo, $taskId, $task['created_by'], 'commented', $msg);
        }
        if ($task['assigned_to'] && $task['assigned_to'] != $currentUserId && $task['assigned_to'] != $task['created_by']) {
            createTaskNotification($pdo, $taskId, $task['assigned_to'], 'commented', $msg);
        }

        jsonResponse(['success' => true, 'message' => 'Komentar berhasil ditambahkan']);
        break;

    // ==================== DELETE COMMENT ====================
    case 'delete_comment':
        $commentId = (int)($_POST['comment_id'] ?? 0);
        if (!$commentId) jsonResponse(['error' => 'ID komentar tidak valid'], 400);

        $stmt = $pdo->prepare("SELECT * FROM task_comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$comment) jsonResponse(['error' => 'Komentar tidak ditemukan'], 404);

        if ($comment['user_id'] != $currentUserId && !in_array($currentRole, ['administrator', 'direktur'])) {
            jsonResponse(['error' => 'Anda tidak memiliki izin'], 403);
        }

        $stmt = $pdo->prepare("DELETE FROM task_comments WHERE id = ?");
        $stmt->execute([$commentId]);

        jsonResponse(['success' => true, 'message' => 'Komentar berhasil dihapus']);
        break;

    // ==================== UPLOAD ATTACHMENT ====================
    case 'upload_attachment':
        $taskId = (int)($_POST['task_id'] ?? 0);
        if (!$taskId) jsonResponse(['error' => 'ID task tidak valid'], 400);

        $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        if (!$stmt->fetch()) jsonResponse(['error' => 'Task tidak ditemukan'], 404);

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['error' => 'File tidak valid atau gagal diupload'], 400);
        }

        $file = $_FILES['file'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxSize) {
            jsonResponse(['error' => 'Ukuran file maksimal 10MB'], 400);
        }

        $allowed = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','ppt','pptx','txt','zip','rar'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            jsonResponse(['error' => 'Tipe file tidak diizinkan'], 400);
        }

        $dir = __DIR__ . '/../../../storage/uploads/tasks/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $fileName = 'task_' . $taskId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $filePath = 'storage/uploads/tasks/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $dir . $fileName)) {
            jsonResponse(['error' => 'Gagal menyimpan file'], 500);
        }

        // Compress image attachments for faster loading
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            require_once __DIR__ . '/../../helpers/image-compress.php';
            compressUploadedImage($dir . $fileName, 1280, 1280, 75);
        }

        $stmt = $pdo->prepare("INSERT INTO task_attachments (task_id, user_id, file_name, file_path, file_size, file_type) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$taskId, $currentUserId, $file['name'], $filePath, $file['size'], $file['type']]);

        logTaskActivity($pdo, $taskId, $currentUserId, 'attachment_added', null, $file['name']);

        jsonResponse(['success' => true, 'message' => 'File berhasil diupload']);
        break;

    // ==================== DELETE ATTACHMENT ====================
    case 'delete_attachment':
        $attachmentId = (int)($_POST['attachment_id'] ?? 0);
        if (!$attachmentId) jsonResponse(['error' => 'ID attachment tidak valid'], 400);

        $stmt = $pdo->prepare("SELECT * FROM task_attachments WHERE id = ?");
        $stmt->execute([$attachmentId]);
        $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$attachment) jsonResponse(['error' => 'Attachment tidak ditemukan'], 404);

        if ($attachment['user_id'] != $currentUserId && !in_array($currentRole, ['administrator', 'direktur'])) {
            jsonResponse(['error' => 'Anda tidak memiliki izin'], 403);
        }

        // Delete physical file
        $fullPath = __DIR__ . '/../../../' . $attachment['file_path'];
        if (file_exists($fullPath)) unlink($fullPath);

        $stmt = $pdo->prepare("DELETE FROM task_attachments WHERE id = ?");
        $stmt->execute([$attachmentId]);

        logTaskActivity($pdo, $attachment['task_id'], $currentUserId, 'attachment_removed', $attachment['file_name'], null);

        jsonResponse(['success' => true, 'message' => 'Attachment berhasil dihapus']);
        break;

    // ==================== GET USERS (for assignment dropdown) ====================
    case 'get_users':
        $stmt = $pdo->query("SELECT id, full_name, role, avatar FROM users WHERE is_active = 1 ORDER BY full_name");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(['users' => $users]);
        break;

    // ==================== GET NOTIFICATIONS ====================
    case 'get_task_notifications':
        $stmt = $pdo->prepare("SELECT tn.*, t.title AS task_title FROM task_notifications tn JOIN tasks t ON tn.task_id = t.id WHERE tn.user_id = ? ORDER BY tn.created_at DESC LIMIT 20");
        $stmt->execute([$currentUserId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM task_notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$currentUserId]);
        $unreadCount = (int)$stmt->fetchColumn();

        jsonResponse(['notifications' => $notifications, 'unread_count' => $unreadCount]);
        break;

    // ==================== MARK NOTIFICATION READ ====================
    case 'mark_notification_read':
        $notifId = (int)($_POST['notification_id'] ?? 0);
        if ($notifId) {
            $stmt = $pdo->prepare("UPDATE task_notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$notifId, $currentUserId]);
        } else {
            // Mark all as read
            $stmt = $pdo->prepare("UPDATE task_notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$currentUserId]);
        }
        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Action tidak valid'], 400);
}
