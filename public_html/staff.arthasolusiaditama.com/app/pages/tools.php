<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/avatar.php';
require_once __DIR__ . '/../helpers/audit-log.php';

$currentRole = strtolower($_SESSION['role'] ?? '');
$isTechnicianRole = in_array($currentRole, ['technician', 'hse']);


try {
    $cols = $pdo->query("SHOW COLUMNS FROM tool_permits LIKE 'admin_photo_path'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE tool_permits ADD COLUMN admin_photo_path VARCHAR(500) DEFAULT NULL AFTER photo_proof_path");
    }
} catch (Throwable $e) {   }


$_adminRow = $pdo->query("SELECT id FROM users WHERE role IN ('administrator','direktur') AND is_active = 1 ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$ADMIN_USER_ID = $_adminRow ? (int)$_adminRow['id'] : (int)$_SESSION['user_id'];

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($action !== '') {
    
    $ALLOWED_ACTIONS = [
        'list_company_tools',
        'list_my_borrowed_tools',
        'add_company_tool',
        'loan_request',
        'tool_detail',
        'update_tool',
        'list_project_tools',
        'list_personal_tools',
        'export_personal_tools',
        'list_technicians',
        'add_personal_tool',
        'project_request',
        'return_request',
        'handover_request',
        'delete_company_tool',
        'force_return',
        'bulk_return_project',
        'edit_project',
        'force_return_all_project',
        'project_handover',
        'list_apd',
        'add_apd',
        'apd_request',
        'apd_return',
        'apd_force_return',
        'delete_apd',
        'edit_personal_tool',
        'delete_personal_tool',
        'edit_loan',
        'extend_loan',
        'quick_edit_date'
    ];

    if (!in_array($action, $ALLOWED_ACTIONS, true)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => "Unknown action: {$action}",
            'allowed' => $ALLOWED_ACTIONS,
        ]);
        exit;
    }

    
    
    $READ_ONLY_ACTIONS = ['list_company_tools', 'list_my_borrowed_tools', 'list_project_tools', 'list_personal_tools', 'list_technicians', 'tool_detail', 'export_personal_tools', 'list_apd'];
    if ($method === 'POST' && !in_array($action, $READ_ONLY_ACTIONS, true)) {
        $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrfToken !== '' && (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrfToken))) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Token keamanan tidak valid. Silakan muat ulang halaman.']);
            exit;
        }
    }

    try {
        switch ($action) {
            
            case 'list_company_tools':
              $stmt = $pdo->query("
                  SELECT 
                    t.id, t.name, t.code, t.current_status, t.photo_path,
                    lp.to_user_id AS holder_id,
                    hu.full_name AS holder_name,
                    lp.location AS holder_location,
                    lp.start_date AS holder_start_date,
                    lp.end_date AS holder_end_date,
                    pp.reason AS project_name,
                    pp.start_date AS project_start_date,
                    pp.end_date AS project_end_date
                  FROM tools t
                  LEFT JOIN tool_permits lp ON lp.id = (
                    SELECT tp.id
                    FROM tool_permits tp
                    WHERE tp.tool_id = t.id
                    AND tp.status = 'approved'
                    AND tp.permit_type IN ('loan', 'handover', 'project')
                    AND NOT EXISTS (
                      SELECT 1 FROM tool_permits tp2
                      WHERE tp2.tool_id = t.id AND tp2.status = 'approved'
                      AND tp2.permit_type IN ('return', 'force_return')
                      AND tp2.id > tp.id
                    )
                    ORDER BY tp.id DESC LIMIT 1
                  )
                  LEFT JOIN users hu ON hu.id = lp.to_user_id
                  LEFT JOIN tool_permits pp ON pp.id = (
                    SELECT p.id
                    FROM tool_permits p
                    WHERE p.tool_id = t.id AND p.status = 'approved' AND p.permit_type = 'project'
                    AND NOT EXISTS (
                      SELECT 1 FROM tool_permits r
                      WHERE r.tool_id = t.id AND r.status = 'approved'
                      AND r.permit_type IN ('return', 'force_return')
                      AND r.id > p.id
                    )
                    ORDER BY p.id DESC LIMIT 1
                  )
                  WHERE t.tool_type = 'company'
                  ORDER BY t.name ASC
              ");
              $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
              break;

            
            case 'list_my_borrowed_tools':
              $userId = (int)($_GET['user_id'] ?? $_SESSION['user_id']);
              
              $isAdmin = in_array($_SESSION['role'], ['administrator', 'direktur']);
              if (!$isAdmin) {
                  $userId = (int)$_SESSION['user_id'];
              }

              $stmt = $pdo->prepare("
                  SELECT 
                    t.id, t.name, t.code, t.current_status, t.photo_path, t.tool_type,
                    tp.permit_type, tp.reason, tp.location, tp.start_date, tp.end_date,
                    tp.created_at AS loan_created_at,
                    ufrom.full_name AS from_name
                  FROM tools t
                  JOIN tool_permits tp ON tp.tool_id = t.id
                  LEFT JOIN users ufrom ON ufrom.id = tp.from_user_id
                  WHERE tp.to_user_id = ?
                    AND tp.status = 'approved'
                    AND tp.permit_type IN ('loan', 'handover', 'project')
                    AND t.current_status IN ('Loan', 'Handover', 'Project')
                    AND NOT EXISTS (
                      SELECT 1 FROM tool_permits ret
                      WHERE ret.tool_id = tp.tool_id
                        AND ret.status = 'approved'
                        AND ret.permit_type IN ('return', 'force_return')
                        AND ret.id > tp.id
                    )
                    AND tp.id = (
                      SELECT tp2.id FROM tool_permits tp2
                      WHERE tp2.tool_id = t.id
                        AND tp2.status = 'approved'
                        AND tp2.permit_type IN ('loan', 'handover', 'project')
                      ORDER BY tp2.id DESC LIMIT 1
                    )
                  ORDER BY tp.end_date ASC, t.name ASC
              ");
              $stmt->execute([$userId]);
              $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
              break;

            
            case 'add_company_tool':
            require_role(['administrator', 'direktur']);
            $name  = trim($_POST['name'] ?? '');
            $code  = trim($_POST['code'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $quantity = (int)($_POST['quantity'] ?? 1);
            $photo = upload_file($_FILES['photo'] ?? null, 'company');

            if (!$name || !$code) throw new Exception('Nama dan Kode wajib diisi');

            
            $checkCodes = [];
            for ($i = 0; $i < $quantity; $i++) {
                $checkCodes[] = $quantity > 1 ? $code . '-' . ($i + 1) : $code;
            }
            $placeholders = implode(',', array_fill(0, count($checkCodes), '?'));
            $chk = $pdo->prepare("SELECT code FROM tools WHERE code IN ($placeholders) AND tool_type='company'");
            $chk->execute($checkCodes);
            $existing = $chk->fetchAll(PDO::FETCH_COLUMN);
            if ($existing) {
                throw new Exception('Kode sudah digunakan untuk company tool: ' . implode(', ', $existing));
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO tools (name, code, tool_type, photo_path, current_status, condition_notes)
                VALUES (?, ?, 'company', ?, 'Ready', ?)
            ");
            
            for ($i = 0; $i < $quantity; $i++) {
                $uniqueCode = $quantity > 1 ? $code . '-' . ($i + 1) : $code;
                $stmt->execute([$name, $uniqueCode, $photo, $notes]);
            }
            
            $pdo->commit();
            $response = ['success' => true, 'message' => $quantity . ' tools berhasil ditambahkan'];
            break;

            
            case 'loan_request':
              $toolId = (int)($_POST['tool_id'] ?? 0);
              
              $canPickUser = in_array($_SESSION['role'], ['administrator', 'direktur', 'internship']);
              $toUserId = $canPickUser 
                  ? (int)($_POST['to_user_id'] ?? 0) 
                  : (int)$_SESSION['user_id'];
              $purpose = trim($_POST['purpose'] ?? '');
              $location = trim($_POST['location'] ?? '');
              $startDate = $_POST['start_date'] ?? '';
              $endDate = $_POST['end_date'] ?? '';
              
              if (!$toolId || !$toUserId || !$purpose || !$location || !$startDate || !$endDate) {
                  throw new Exception('Data tidak lengkap');
              }
              
              
              if (strtotime($endDate) <= strtotime($startDate)) {
                  throw new Exception('Jatuh tempo harus setelah tanggal mulai pinjam');
              }

              $stmt = $pdo->prepare("SELECT current_status FROM tools WHERE id=? AND tool_type='company'");
              $stmt->execute([$toolId]);
              $status = $stmt->fetchColumn();

              
              if ($status !== 'Ready') {
                  throw new Exception('Tools tidak tersedia untuk dipinjam. Status saat ini: ' . $status);
              }

              
              $photoPath = upload_tool_file($_FILES['proof_photo'] ?? null, 'loan', $_SESSION['user_id']);

              
              $isAdmin = in_array($_SESSION['role'], ['administrator', 'direktur']);
              $permitStatus = $isAdmin ? 'approved' : 'pending';
              
              $stmt = $pdo->prepare("
                  INSERT INTO tool_permits (permit_type, tool_id, from_user_id, to_user_id, status, 
                                          reason, start_date, end_date, photo_proof_path, location" . ($isAdmin ? ", approved_at, approved_by" : "") . ")
                  VALUES ('loan', ?, ?, ?, ?, ?, ?, ?, ?, ?" . ($isAdmin ? ", NOW(), ?" : "") . ")
              ");
              $params = [$toolId, $_SESSION['user_id'], $toUserId, $permitStatus, $purpose, $startDate, $endDate, $photoPath, $location];
              if ($isAdmin) $params[] = $_SESSION['user_id'];
              $stmt->execute($params);

              
              if ($isAdmin) {
                  $pdo->prepare("UPDATE tools SET current_status='Loan' WHERE id=?")->execute([$toolId]);
                  $pdo->prepare("INSERT INTO tool_status_history (tool_id, from_status, to_status, user_id, notes) VALUES (?, 'Ready', 'Loan', ?, ?)")
                      ->execute([$toolId, $_SESSION['user_id'], $purpose]);
              }

              $response = ['success' => true, 'message' => $isAdmin ? 'Loan berhasil, tools langsung dipinjamkan' : 'Permintaan pinjam berhasil dibuat, menunggu approval'];
              auditLog($pdo, 'loan_request', [
                  'target_type' => 'tool',
                  'target_id' => $toolId,
                  'target_user_id' => $toUserId,
                  'details' => ['purpose' => $purpose, 'location' => $location, 'status' => $permitStatus]
              ]);
              break;

            
            case 'tool_detail':
                $toolId = (int)($_GET['tool_id'] ?? 0);
                if (!$toolId) throw new Exception('Tool tidak valid');

                $st = $pdo->prepare("SELECT * FROM tools WHERE id=?");
                $st->execute([$toolId]);
                $tool = $st->fetch(PDO::FETCH_ASSOC);
                if (!$tool) throw new Exception('Tools tidak ditemukan');

                $holder = null;
                if (in_array($tool['current_status'], ['Loan','Handover','Project'], true)) {
                    $st2 = $pdo->prepare("
                      SELECT p.*, u.full_name, p.location 
                      FROM tool_permits p 
                      JOIN users u ON u.id=p.to_user_id
                      WHERE p.tool_id=? AND p.status='approved'
                      AND p.permit_type IN ('loan', 'handover', 'project')
                      ORDER BY p.id DESC LIMIT 1
                    ");
                    $st2->execute([$toolId]);
                    $holder = $st2->fetch(PDO::FETCH_ASSOC) ?: null;
                }

                
                $isAdmin = in_array(($_SESSION['role'] ?? ''), ['administrator', 'direktur']);
                if ($isAdmin) {
                    $st3 = $pdo->prepare("
                        SELECT h.*, u.full_name 
                        FROM tool_status_history h
                        LEFT JOIN users u ON h.user_id=u.id
                        WHERE h.tool_id=? ORDER BY h.created_at DESC
                        LIMIT 30
                    ");
                    $st3->execute([$toolId]);
                } else {
                    $st3 = $pdo->prepare("
                        SELECT h.*, u.full_name 
                        FROM tool_status_history h
                        LEFT JOIN users u ON h.user_id=u.id
                        WHERE h.tool_id=? AND h.user_id=?
                        ORDER BY h.created_at DESC
                        LIMIT 30
                    ");
                    $st3->execute([$toolId, $_SESSION['user_id']]);
                }
                $history = $st3->fetchAll(PDO::FETCH_ASSOC);

                
                $permitChain = [];
                if ($isAdmin) {
                    $st4 = $pdo->prepare("
                        SELECT tp.id, tp.permit_type, tp.status, tp.reason, tp.location,
                               tp.start_date, tp.end_date, tp.created_at, tp.approved_at,
                               tp.photo_proof_path, tp.admin_photo_path,
                               ufrom.full_name AS from_name, uto.full_name AS to_name,
                               approver.full_name AS approved_by_name
                        FROM tool_permits tp
                        LEFT JOIN users ufrom ON ufrom.id = tp.from_user_id
                        LEFT JOIN users uto ON uto.id = tp.to_user_id
                        LEFT JOIN users approver ON approver.id = tp.approved_by
                        WHERE tp.tool_id = ?
                        ORDER BY tp.id DESC
                        LIMIT 50
                    ");
                    $st4->execute([$toolId]);
                    $permitChain = $st4->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $st4 = $pdo->prepare("
                        SELECT tp.id, tp.permit_type, tp.status, tp.reason, tp.location,
                               tp.start_date, tp.end_date, tp.created_at, tp.approved_at,
                               tp.photo_proof_path, tp.admin_photo_path,
                               ufrom.full_name AS from_name, uto.full_name AS to_name,
                               approver.full_name AS approved_by_name
                        FROM tool_permits tp
                        LEFT JOIN users ufrom ON ufrom.id = tp.from_user_id
                        LEFT JOIN users uto ON uto.id = tp.to_user_id
                        LEFT JOIN users approver ON approver.id = tp.approved_by
                        WHERE tp.tool_id = ? AND (tp.from_user_id = ? OR tp.to_user_id = ?)
                        ORDER BY tp.id DESC
                        LIMIT 50
                    ");
                    $st4->execute([$toolId, $_SESSION['user_id'], $_SESSION['user_id']]);
                    $permitChain = $st4->fetchAll(PDO::FETCH_ASSOC);
                }

                $response = ['tool' => $tool, 'holder' => $holder, 'history' => $history, 'permits' => $permitChain];
                break;

            
            case 'update_tool':
                require_role(['administrator', 'direktur']);
                $toolId = (int)($_POST['tool_id'] ?? 0);
                if (!$toolId) throw new Exception('Tool ID tidak valid');

                $st = $pdo->prepare("SELECT * FROM tools WHERE id=?");
                $st->execute([$toolId]);
                $tool = $st->fetch(PDO::FETCH_ASSOC);
                if (!$tool) throw new Exception('Tool tidak ditemukan');

                $name = trim($_POST['name'] ?? '');
                $code = trim($_POST['code'] ?? '');
                $conditionNotes = trim($_POST['condition_notes'] ?? '');
                $newStatus = trim($_POST['current_status'] ?? '');
                $allowedStatuses = ['Ready','Loan','Handover','Project','Good','Repair','Missing'];

                if ($name === '') throw new Exception('Nama tools wajib diisi');
                if ($code === '') throw new Exception('Kode tools wajib diisi');
                if ($newStatus && !in_array($newStatus, $allowedStatuses, true)) {
                    throw new Exception('Status tidak valid');
                }

                
                if ($code !== $tool['code']) {
                    $chk = $pdo->prepare("SELECT id FROM tools WHERE code = ? AND tool_type = ? AND id != ?");
                    $chk->execute([$code, $tool['tool_type'], $toolId]);
                    if ($chk->fetch()) {
                        throw new Exception('Kode "' . $code . '" sudah digunakan oleh tool lain');
                    }
                }

                
                $photoPath = $tool['photo_path'];
                if (!empty($_FILES['photo']['tmp_name'])) {
                    $photoPath = upload_file($_FILES['photo'] ?? null, $tool['tool_type']);
                }

                $oldStatus = $tool['current_status'];
                $finalStatus = $newStatus ?: $oldStatus;

                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    UPDATE tools SET name=?, code=?, condition_notes=?, current_status=?, photo_path=?, updated_at=NOW()
                    WHERE id=?
                ");
                $stmt->execute([$name, $code, $conditionNotes, $finalStatus, $photoPath, $toolId]);

                
                if ($finalStatus !== $oldStatus) {
                    $pdo->prepare("
                        INSERT INTO tool_status_history (tool_id, from_status, to_status, user_id, notes)
                        VALUES (?, ?, ?, ?, ?)
                    ")->execute([$toolId, $oldStatus, $finalStatus, $_SESSION['user_id'], 'Admin update: ' . $conditionNotes]);
                }

                $pdo->commit();
                $response = ['success' => true, 'message' => 'Tool berhasil diperbarui'];
                break;


          
          case 'handover_request':
              $toolId = (int)($_POST['tool_id'] ?? 0);
              
              $toUserId = (in_array($_SESSION['role'], ['administrator', 'direktur'])) 
                  ? (int)($_POST['to_user_id'] ?? 0) 
                  : (int)$_SESSION['user_id'];
              $purpose = trim($_POST['purpose'] ?? '');
              $location = trim($_POST['location'] ?? '');
              
              if (!$toolId || !$toUserId || !$purpose || !$location) {
                  throw new Exception('Data tidak lengkap');
              }

              
              $stmt = $pdo->prepare("
                  SELECT t.current_status, tp.to_user_id as current_holder_id, u.full_name as current_holder_name
                  FROM tools t 
                  JOIN tool_permits tp ON t.id = tp.tool_id 
                  JOIN users u ON tp.to_user_id = u.id
                  WHERE t.id = ? 
                  AND tp.status = 'approved' 
                  AND tp.permit_type IN ('loan', 'handover', 'project')
                  ORDER BY tp.id DESC LIMIT 1
              ");
              $stmt->execute([$toolId]);
              $current = $stmt->fetch(PDO::FETCH_ASSOC);

              if (!$current) {
                  throw new Exception('Tools tidak sedang dipinjam oleh siapa pun');
              }

              
              if ((int)$current['current_holder_id'] === (int)$_SESSION['user_id']) {
                  throw new Exception('Anda sudah memegang tools ini. Gunakan fitur return untuk mengembalikan.');
              }

              if (!in_array($current['current_status'], ['Loan', 'Handover', 'Project'], true)) {
                  throw new Exception('Tools harus dalam status Loan, Handover, atau Project untuk handover');
              }

              
              $isAdmin = in_array($_SESSION['role'], ['administrator', 'direktur']);
              $permitStatus = $isAdmin ? 'approved' : 'pending';

              
              $photoPath = null;
              if (!$isAdmin) {
                  $photoPath = upload_tool_file($_FILES['proof_photo'] ?? null, 'handover', $_SESSION['user_id']);
              }
              
              $stmt = $pdo->prepare("
                  INSERT INTO tool_permits (permit_type, tool_id, from_user_id, to_user_id, status, reason, photo_proof_path, location" . ($isAdmin ? ", approved_at, approved_by" : "") . ")
                  VALUES ('handover', ?, ?, ?, ?, ?, ?, ?" . ($isAdmin ? ", NOW(), ?" : "") . ")
              ");
              $params = [$toolId, $current['current_holder_id'], $toUserId, $permitStatus, $purpose, $photoPath, $location];
              if ($isAdmin) $params[] = $_SESSION['user_id'];
              $stmt->execute($params);

              
              if ($isAdmin) {
                  $pdo->prepare("UPDATE tools SET current_status='Handover' WHERE id=?")->execute([$toolId]);
                  $pdo->prepare("INSERT INTO tool_status_history (tool_id, from_status, to_status, user_id, notes) VALUES (?, ?, 'Handover', ?, ?)")
                      ->execute([$toolId, $current['current_status'], $_SESSION['user_id'], $purpose]);
              }

              $response = [
                  'success' => true, 
                  'message' => $isAdmin 
                      ? 'Handover berhasil, tools langsung dipindahkan ke teknisi tujuan' 
                      : 'Permintaan handover dikirim ke ' . $current['current_holder_name'] . ', menunggu approval'
              ];
              auditLog($pdo, 'handover_request', [
                  'target_type' => 'tool',
                  'target_id' => $toolId,
                  'target_user_id' => $toUserId,
                  'details' => ['from_user' => $current['current_holder_name'], 'location' => $location, 'purpose' => $purpose, 'status' => $permitStatus]
              ]);
              break;



            
            case 'list_project_tools':
              $stmt = $pdo->query("
                SELECT t.id, t.name, t.code, t.photo_path, t.current_status,
                  (SELECT p.to_user_id FROM tool_permits p WHERE p.tool_id=t.id AND p.status='approved' AND p.permit_type IN ('loan','handover','project')
                    AND NOT EXISTS (SELECT 1 FROM tool_permits r WHERE r.tool_id=t.id AND r.status='approved' AND r.permit_type IN ('return','force_return') AND r.id > p.id)
                    ORDER BY p.id DESC LIMIT 1) AS holder_id,
                  (SELECT u.full_name FROM users u JOIN tool_permits p2 ON u.id=p2.to_user_id WHERE p2.tool_id=t.id AND p2.status='approved' AND p2.permit_type IN ('loan','handover','project')
                    AND NOT EXISTS (SELECT 1 FROM tool_permits r2 WHERE r2.tool_id=t.id AND r2.status='approved' AND r2.permit_type IN ('return','force_return') AND r2.id > p2.id)
                    ORDER BY p2.id DESC LIMIT 1) AS holder_name,
                  (SELECT p3.location FROM tool_permits p3 WHERE p3.tool_id=t.id AND p3.status='approved' AND p3.permit_type IN ('loan','handover','project')
                    AND NOT EXISTS (SELECT 1 FROM tool_permits r3 WHERE r3.tool_id=t.id AND r3.status='approved' AND r3.permit_type IN ('return','force_return') AND r3.id > p3.id)
                    ORDER BY p3.id DESC LIMIT 1) AS holder_location,
                  (SELECT p4.reason FROM tool_permits p4 WHERE p4.tool_id=t.id AND p4.status='approved' AND p4.permit_type='project'
                    AND NOT EXISTS (SELECT 1 FROM tool_permits r4 WHERE r4.tool_id=t.id AND r4.status='approved' AND r4.permit_type IN ('return','force_return') AND r4.id > p4.id)
                    ORDER BY p4.id DESC LIMIT 1) AS project_name,
                  (SELECT p5.start_date FROM tool_permits p5 WHERE p5.tool_id=t.id AND p5.status='approved' AND p5.permit_type='project'
                    AND NOT EXISTS (SELECT 1 FROM tool_permits r5 WHERE r5.tool_id=t.id AND r5.status='approved' AND r5.permit_type IN ('return','force_return') AND r5.id > p5.id)
                    ORDER BY p5.id DESC LIMIT 1) AS project_start_date,
                  (SELECT p6.end_date FROM tool_permits p6 WHERE p6.tool_id=t.id AND p6.status='approved' AND p6.permit_type='project'
                    AND NOT EXISTS (SELECT 1 FROM tool_permits r6 WHERE r6.tool_id=t.id AND r6.status='approved' AND r6.permit_type IN ('return','force_return') AND r6.id > p6.id)
                    ORDER BY p6.id DESC LIMIT 1) AS project_end_date
                FROM tools t
                WHERE t.tool_type='company'
                ORDER BY t.name ASC
              ");
              $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
              $response = $all;
              break;

            
            case 'list_personal_tools':
                $techId = (int)($_GET['technician_id'] ?? $_SESSION['user_id']);
                $st = $pdo->prepare("
                    SELECT t.id, t.name, t.code, t.current_status, t.photo_path, t.condition_notes
                    FROM tools t 
                    JOIN tool_assignments a ON a.tool_id=t.id 
                    WHERE a.user_id=? AND t.tool_type='personal'
                    ORDER BY t.name ASC
                ");
                $st->execute([$techId]);
                $response = $st->fetchAll(PDO::FETCH_ASSOC);
                break;

            
            case 'export_personal_tools':
                require_role(['administrator', 'direktur']);
                
                
                $stmt = $pdo->query("
                    SELECT 
                        u.id as user_id,
                        u.full_name,
                        u.role,
                        t.id as tool_id,
                        t.name as tool_name,
                        t.code as tool_code,
                        t.current_status,
                        t.condition_notes
                    FROM users u
                    LEFT JOIN tool_assignments ta ON ta.user_id = u.id
                    LEFT JOIN tools t ON t.id = ta.tool_id AND t.tool_type = 'personal'
                    WHERE u.role IN ('technician', 'hse', 'sales', 'daily') AND u.is_active = 1
                    ORDER BY u.full_name ASC, t.name ASC
                ");
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                
                $grouped = [];
                foreach ($data as $row) {
                    $userId = $row['user_id'];
                    if (!isset($grouped[$userId])) {
                        $grouped[$userId] = [
                            'full_name' => $row['full_name'],
                            'role' => $row['role'],
                            'tools' => []
                        ];
                    }
                    if ($row['tool_id']) {
                        $grouped[$userId]['tools'][] = [
                            'name' => $row['tool_name'],
                            'code' => $row['tool_code'],
                            'status' => $row['current_status'],
                            'notes' => $row['condition_notes']
                        ];
                    }
                }
                
                
                header('Content-Type: text/csv; charset=UTF-8');
                header('Content-Disposition: attachment; filename="Tools_Personal_' . date('Y-m-d') . '.csv"');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                $output = fopen('php://output', 'w');
                
                fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
                
                
                fputcsv($output, ['Nama Karyawan', 'Role', 'Nama Tool', 'Kode Tool', 'Status', 'Keterangan']);
                
                
                foreach ($grouped as $user) {
                    if (empty($user['tools'])) {
                        fputcsv($output, [$user['full_name'], ucfirst($user['role']), '-', '-', '-', 'Tidak ada tools']);
                    } else {
                        foreach ($user['tools'] as $tool) {
                            fputcsv($output, [
                                $user['full_name'],
                                ucfirst($user['role']),
                                $tool['name'],
                                $tool['code'],
                                $tool['status'],
                                $tool['notes'] ?: '-'
                            ]);
                        }
                    }
                }
                
                fclose($output);
                exit;

            
            case 'list_technicians':
                $st = $pdo->query("
                    SELECT id, full_name, role, COALESCE(avatar, photo) AS avatar
                    FROM users 
                    WHERE role IN ('technician', 'hse', 'sales', 'daily') AND is_active=1
                    ORDER BY full_name ASC
                ");
                $response = $st->fetchAll(PDO::FETCH_ASSOC);
                break;

            
            case 'add_personal_tool':
                require_role(['administrator', 'direktur', 'hse']);
                $name  = trim($_POST['name'] ?? '');
                $code  = trim($_POST['code'] ?? '');
                $notes = trim($_POST['notes'] ?? '');
                $assignType = $_POST['assign_type'] ?? 'single'; 
                $assignTo = (int)($_POST['assign_to'] ?? 0);
                $photo = upload_file($_FILES['photo'] ?? null, 'personal');

                if (!$name || !$code) throw new Exception('Nama dan Kode wajib diisi');

                $pdo->beginTransaction();
                $ins = $pdo->prepare("
                    INSERT INTO tools (name, code, tool_type, photo_path, current_status, condition_notes)
                    VALUES (?, ?, 'personal', ?, 'Good', ?)
                ");
                $ia = $pdo->prepare("INSERT INTO tool_assignments (tool_id, user_id, assigned_by) VALUES (?, ?, ?)");

                if ($assignType === 'all') {
                    
                    $techs = $pdo->query("SELECT id FROM users WHERE role IN ('technician','hse','sales','daily') AND is_active=1")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($techs as $uid) {
                        $ins->execute([$name, $code, $photo, $notes]);
                        $newToolId = (int)$pdo->lastInsertId();
                        $ia->execute([$newToolId, (int)$uid, $_SESSION['user_id']]);
                    }
                } elseif ($assignTo) {
                    $ins->execute([$name, $code, $photo, $notes]);
                    $toolId = (int)$pdo->lastInsertId();
                    $ia->execute([$toolId, $assignTo, $_SESSION['user_id']]);
                }

                $pdo->commit();
                $response = ['success'=>true, 'message'=>'Personal tool berhasil ditambahkan'];
                break;

            
            case 'edit_personal_tool':
                require_role(['administrator', 'direktur', 'hse']);
                $toolId = (int)($_POST['tool_id'] ?? 0);
                if (!$toolId) throw new Exception('Tool ID tidak valid');

                $st = $pdo->prepare("SELECT * FROM tools WHERE id=? AND tool_type='personal'");
                $st->execute([$toolId]);
                $tool = $st->fetch(PDO::FETCH_ASSOC);
                if (!$tool) throw new Exception('Tool tidak ditemukan');

                $name = trim($_POST['name'] ?? '');
                $code = trim($_POST['code'] ?? '');
                $notes = trim($_POST['condition_notes'] ?? '');
                $newStatus = trim($_POST['current_status'] ?? '');
                $allowedStatuses = ['Good','Repair','Missing'];

                if (!$name || !$code) throw new Exception('Nama dan Kode wajib diisi');
                if ($newStatus && !in_array($newStatus, $allowedStatuses, true)) {
                    throw new Exception('Status tidak valid');
                }

                $photoPath = $tool['photo_path'];
                if (!empty($_FILES['photo']['tmp_name'])) {
                    $photoPath = upload_file($_FILES['photo'] ?? null, 'personal');
                }

                $oldStatus = $tool['current_status'];
                $finalStatus = $newStatus ?: $oldStatus;

                $pdo->beginTransaction();
                $pdo->prepare("UPDATE tools SET name=?, code=?, condition_notes=?, current_status=?, photo_path=?, updated_at=NOW() WHERE id=?")
                    ->execute([$name, $code, $notes, $finalStatus, $photoPath, $toolId]);

                if ($finalStatus !== $oldStatus) {
                    $pdo->prepare("INSERT INTO tool_status_history (tool_id, from_status, to_status, user_id, notes) VALUES (?,?,?,?,?)")
                        ->execute([$toolId, $oldStatus, $finalStatus, $_SESSION['user_id'], 'Admin update: ' . $notes]);
                }
                $pdo->commit();
                $response = ['success' => true, 'message' => 'Personal tool berhasil diperbarui'];
                break;

            
            case 'delete_personal_tool':
                require_role(['administrator', 'direktur', 'hse']);
                $toolId = (int)($_POST['tool_id'] ?? 0);
                if (!$toolId) throw new Exception('Tool ID tidak valid');

                $st = $pdo->prepare("SELECT id FROM tools WHERE id=? AND tool_type='personal'");
                $st->execute([$toolId]);
                if (!$st->fetch()) throw new Exception('Tool tidak ditemukan');

                $pdo->beginTransaction();
                try {
                    $pdo->prepare("DELETE FROM tool_assignments WHERE tool_id=?")->execute([$toolId]);
                    $pdo->prepare("DELETE FROM tool_status_history WHERE tool_id=?")->execute([$toolId]);
                    $pdo->prepare("DELETE FROM monthly_check_items WHERE tool_id=?")->execute([$toolId]);
                    $pdo->prepare("DELETE FROM tools WHERE id=? AND tool_type='personal'")->execute([$toolId]);
                    $pdo->commit();
                    $response = ['success' => true, 'message' => 'Personal tool berhasil dihapus'];
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw new Exception('Gagal menghapus: ' . $e->getMessage());
                }
                break;

            
            case 'return_request':
                $toolId = (int)($_POST['tool_id'] ?? 0);
                $returnDatetime = $_POST['return_datetime'] ?? '';
                
                if (!$toolId || !$returnDatetime) throw new Exception('Data tidak lengkap');
                
                
                $photoPath = upload_tool_file($_FILES['return_photo'] ?? null, 'return', $_SESSION['user_id']);
                
                
                $stmt = $pdo->prepare("
                    SELECT t.current_status, tp.to_user_id 
                    FROM tools t 
                    JOIN tool_permits tp ON t.id = tp.tool_id 
                    WHERE t.id = ? 
                    AND tp.status = 'approved' 
                    AND tp.permit_type IN ('loan', 'handover', 'project')
                    ORDER BY tp.id DESC LIMIT 1
                ");
                $stmt->execute([$toolId]);
                $current = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$current || (int)$current['to_user_id'] !== (int)$_SESSION['user_id']) {
                    throw new Exception('Anda tidak memegang tools ini');
                }

                if (!in_array($current['current_status'], ['Loan', 'Handover', 'Project'], true)) {
                    throw new Exception('Tools harus dalam status Loan, Handover, atau Project untuk dikembalikan');
                }

                
                $stmt = $pdo->prepare("
                    SELECT id FROM tool_permits 
                    WHERE tool_id = ? AND permit_type = 'return' AND status = 'pending'
                    LIMIT 1
                ");
                $stmt->execute([$toolId]);
                if ($stmt->fetch()) {
                    throw new Exception('Sudah ada permintaan return yang menunggu approval untuk tools ini');
                }

                
                $stmt = $pdo->prepare("
                    INSERT INTO tool_permits (permit_type, tool_id, from_user_id, to_user_id, status, photo_proof_path, reason, created_at)
                    VALUES ('return', ?, ?, ?, 'pending', ?, 'Pengembalian tools ke PT. Artha Solusi Aditama', ?)
                ");
                $stmt->execute([$toolId, $_SESSION['user_id'], $ADMIN_USER_ID, $photoPath, $returnDatetime]);

                $response = ['success' => true, 'message' => 'Permintaan return telah dikirim, menunggu approval admin'];
                auditLog($pdo, 'return_request', [
                    'target_type' => 'tool',
                    'target_id' => $toolId,
                    'details' => ['return_datetime' => $returnDatetime]
                ]);
                break;

            
            case 'force_return':
                require_role(['administrator', 'direktur']);
                $toolId = (int)($_POST['tool_id'] ?? 0);
                
                if (!$toolId) throw new Exception('Tool ID tidak valid');
                
                
                $stmt = $pdo->prepare("
                    SELECT t.current_status, tp.to_user_id, u.full_name as holder_name
                    FROM tools t 
                    JOIN tool_permits tp ON t.id = tp.tool_id 
                    LEFT JOIN users u ON tp.to_user_id = u.id
                    WHERE t.id = ? 
                    AND tp.status = 'approved' 
                    AND tp.permit_type IN ('loan', 'handover', 'project')
                    ORDER BY tp.id DESC LIMIT 1
                ");
                $stmt->execute([$toolId]);
                $current = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$current || !in_array($current['current_status'], ['Loan', 'Handover', 'Project'], true)) {
                  throw new Exception('Tools tidak sedang dipinjam atau dalam status yang salah');
                }

                
                $pdo->beginTransaction();
                
                try {
                    
                    $stmt = $pdo->prepare("UPDATE tools SET current_status = 'Ready' WHERE id = ?");
                    $stmt->execute([$toolId]);
                    
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO tool_permits (permit_type, tool_id, from_user_id, to_user_id, status, created_at, approved_at)
                        VALUES ('force_return', ?, ?, ?, 'approved', ?, ?)
                    ");
                    $currentTime = date('Y-m-d H:i:s');
                    $stmt->execute([$toolId, $current['to_user_id'], $ADMIN_USER_ID, $currentTime, $currentTime]);
                    
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO tool_status_history (tool_id, from_status, to_status, user_id, notes)
                        VALUES (?, ?, 'Ready', ?, 'Force returned by administrator')
                    ");
                    $stmt->execute([$toolId, $current['current_status'], $_SESSION['user_id']]);
                    
                    $pdo->commit();
                    $response = ['success' => true, 'message' => 'Return paksa berhasil. Tools dikembalikan ke PT. Artha Solusi Aditama'];
                    auditLog($pdo, 'force_return_tool', [
                        'target_type' => 'tool',
                        'target_id' => $toolId,
                        'target_user_id' => $current['to_user_id'],
                        'details' => ['holder_name' => $current['holder_name'], 'old_status' => $current['current_status']]
                    ]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw new Exception('Gagal memproses return paksa: ' . $e->getMessage());
                }
                break;

              
              case 'delete_company_tool':
                  require_role(['administrator', 'direktur']);
                  $toolId = (int)($_POST['tool_id'] ?? 0);
                  
                  if (!$toolId) throw new Exception('Tool ID tidak valid');
                  
                  $pdo->beginTransaction();
                  
                  try {
                      
                      $stmt = $pdo->prepare("DELETE FROM tool_assignments WHERE tool_id = ?");
                      $stmt->execute([$toolId]);
                      
                      
                      $stmt = $pdo->prepare("DELETE FROM tool_status_history WHERE tool_id = ?");
                      $stmt->execute([$toolId]);
                      
                      
                      $stmt = $pdo->prepare("DELETE FROM tool_permits WHERE tool_id = ?");
                      $stmt->execute([$toolId]);
                      
                      
                      $stmt = $pdo->prepare("DELETE FROM monthly_check_items WHERE tool_id = ?");
                      $stmt->execute([$toolId]);
                      
                      
                      $stmt = $pdo->prepare("DELETE FROM tools WHERE id = ?");
                      $stmt->execute([$toolId]);
                      
                      $pdo->commit();
                      $response = ['success' => true, 'message' => 'Tool berhasil dihapus'];
                  } catch (Exception $e) {
                      $pdo->rollBack();
                      throw new Exception('Gagal menghapus tool: ' . $e->getMessage());
                  }
                  break;

                
                case 'project_request':
                    $ids = $_POST['tool_ids'] ?? [];
                    $picName = trim($_POST['pic_name'] ?? '');
                    $location = trim($_POST['location'] ?? '');
                    $startDate = date('Y-m-d H:i:s');
                    $endDate = str_replace('T', ' ', $_POST['end_date'] ?? '');

                    if (!is_array($ids) || !$ids || !$picName || !$location || !$endDate) {
                        throw new Exception('Data tidak lengkap. Pastikan semua field terisi.');
                    }

                    
                    $photoPaths = [];
                    if (isset($_FILES['proof_photos']) && is_array($_FILES['proof_photos']['tmp_name'])) {
                        foreach ($_FILES['proof_photos']['tmp_name'] as $i => $tmp) {
                            $singleFile = [
                                'name' => $_FILES['proof_photos']['name'][$i],
                                'type' => $_FILES['proof_photos']['type'][$i],
                                'tmp_name' => $tmp,
                                'error' => $_FILES['proof_photos']['error'][$i],
                                'size' => $_FILES['proof_photos']['size'][$i],
                            ];
                            $p = upload_tool_file($singleFile, 'project', $_SESSION['user_id']);
                            if ($p) $photoPaths[] = $p;
                        }
                    }
                    $photoPath = !empty($photoPaths) ? json_encode($photoPaths) : null;

                    $isAdmin = in_array(($_SESSION['role'] ?? ''), ['administrator', 'direktur']);
                    $isDailyInternship = in_array(($_SESSION['role'] ?? ''), ['daily', 'internship']);

                    
                    if ($isAdmin && !empty($_POST['to_user_id'])) {
                        $toUserId = (int)$_POST['to_user_id'];
                    } elseif ($isDailyInternship) {
                        $toUserId = !empty($_POST['to_user_id']) ? (int)$_POST['to_user_id'] : 0;
                        if (!$toUserId) {
                            throw new Exception('Wajib memilih teknisi penanggung jawab tools');
                        }
                        
                        $chkTech = $pdo->prepare("SELECT role FROM users WHERE id = ? AND is_active = 1");
                        $chkTech->execute([$toUserId]);
                        $techRole = $chkTech->fetchColumn();
                        if (!in_array($techRole, ['technician', 'technician_manager'])) {
                            throw new Exception('User yang dipilih bukan teknisi');
                        }
                    } else {
                        $toUserId = (int)($_SESSION['user_id'] ?? 0);
                    }

                    
                    $stmtStatus = $pdo->prepare("SELECT current_status FROM tools WHERE id = ? AND tool_type = 'company'");
                    foreach ($ids as $tid) {
                        $tid = (int)$tid;
                        if ($tid <= 0) continue;
                        $stmtStatus->execute([$tid]);
                        $toolStatus = $stmtStatus->fetchColumn();
                        if ($toolStatus !== 'Ready') {
                            throw new Exception('Salah satu tools yang dipilih tidak dalam status Ready dan tidak dapat diajukan untuk project');
                        }
                    }

                    $ins = $pdo->prepare("
                      INSERT INTO tool_permits (permit_type, tool_id, from_user_id, to_user_id, status, reason, location, start_date, end_date, photo_proof_path)
                      VALUES ('project', ?, ?, ?, 'pending', ?, ?, ?, ?, ?)
                    ");

                    foreach ($ids as $tid) {
                        $tid = (int)$tid;
                        if ($tid > 0) {
                            $ins->execute([$tid, $ADMIN_USER_ID, $toUserId, $picName, $location, $startDate, $endDate, $photoPath]);
                        }
                    }

                    $response = ['success'=>true, 'message'=>'Pengajuan project dikirim ke administrator'];
                    auditLog($pdo, 'project_request', [
                        'target_type' => 'tool',
                        'target_user_id' => $toUserId,
                        'details' => ['tool_ids' => $ids, 'pic_name' => $picName, 'location' => $location, 'end_date' => $endDate]
                    ]);
                    break;

            
            case 'bulk_return_project':
                $returnDatetime = $_POST['return_datetime'] ?? '';
                if (!$returnDatetime) throw new Exception('Tanggal pengembalian wajib diisi');
                
                $photoPath = upload_tool_file($_FILES['return_photo'] ?? null, 'return', $_SESSION['user_id']);

                $isAdmin = in_array($_SESSION['role'], ['administrator', 'direktur']);
                $singleToolId = (int)($_POST['single_tool_id'] ?? 0);

                if ($singleToolId > 0) {
                    
                    
                    $stmtLatest = $pdo->prepare("SELECT to_user_id FROM tool_permits WHERE tool_id = ? AND status = 'approved' AND permit_type IN ('loan','handover','project') ORDER BY id DESC LIMIT 1");
                    $stmtLatest->execute([$singleToolId]);
                    $latestHolder = $stmtLatest->fetch(PDO::FETCH_ASSOC);

                    if (!$isAdmin && (!$latestHolder || (int)$latestHolder['to_user_id'] !== (int)$_SESSION['user_id'])) {
                        throw new Exception('Anda tidak memegang tools ini');
                    }

                    
                    $stmtCheck = $pdo->prepare("SELECT id FROM tool_permits WHERE tool_id = ? AND permit_type = 'return' AND status = 'pending' LIMIT 1");
                    $stmtCheck->execute([$singleToolId]);
                    if ($stmtCheck->fetch()) throw new Exception('Sudah ada permintaan return yang menunggu approval untuk tools ini');

                    $permitStatus = $isAdmin ? 'approved' : 'pending';
                    $pdo->beginTransaction();
                    try {
                        $ins = $pdo->prepare("
                            INSERT INTO tool_permits (permit_type, tool_id, from_user_id, to_user_id, status, photo_proof_path, reason, created_at" . ($isAdmin ? ", approved_at, approved_by" : "") . ")
                            VALUES ('return', ?, ?, ?, ?, ?, 'Return project tool ke PT. Artha Solusi Aditama', ?" . ($isAdmin ? ", NOW(), ?" : "") . ")
                        ");
                        $params = [$singleToolId, $_SESSION['user_id'], $ADMIN_USER_ID, $permitStatus, $photoPath, $returnDatetime];
                        if ($isAdmin) $params[] = $_SESSION['user_id'];
                        $ins->execute($params);

                        if ($isAdmin) {
                            $pdo->prepare("UPDATE tools SET current_status = 'Ready' WHERE id = ?")->execute([$singleToolId]);
                            $pdo->prepare("INSERT INTO tool_status_history (tool_id, from_status, to_status, user_id, notes) VALUES (?, 'Project', 'Ready', ?, 'Return project tool')")
                                ->execute([$singleToolId, $_SESSION['user_id']]);
                        }
                        $pdo->commit();
                        $response = [
                            'success' => true,
                            'message' => $isAdmin ? 'Tools berhasil dikembalikan' : 'Permintaan return telah dikirim, menunggu approval admin'
                        ];
                        auditLog($pdo, 'return_request', [
                            'target_type' => 'tool',
                            'target_id' => $singleToolId,
                            'details' => ['return_datetime' => $returnDatetime, 'status' => $permitStatus, 'source' => 'bulk_return_single']
                        ]);
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        throw new Exception('Gagal memproses return: ' . $e->getMessage());
                    }
                    break;
                }

                
                
                $selectedIds = $_POST['tool_ids'] ?? [];
                if (!is_array($selectedIds)) $selectedIds = [];
                $selectedIds = array_map('intval', array_filter($selectedIds));

                $stmt = $pdo->query("SELECT id, current_status FROM tools WHERE current_status = 'Project' AND tool_type = 'company'");
                $allProjectTools = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($allProjectTools)) throw new Exception('Tidak ada tools dalam status Project');

                
                $projectTools = [];
                $stmtLatest = $pdo->prepare("SELECT to_user_id FROM tool_permits WHERE tool_id = ? AND status = 'approved' AND permit_type IN ('loan','handover','project') ORDER BY id DESC LIMIT 1");
                foreach ($allProjectTools as $pt) {
                  $stmtLatest->execute([$pt['id']]);
                  $latest = $stmtLatest->fetch(PDO::FETCH_ASSOC);
                  if ($isAdmin || ($latest && (int)$latest['to_user_id'] === (int)$_SESSION['user_id'])) {
                    $projectTools[] = $pt;
                  }
                }

                if (empty($projectTools)) throw new Exception('Tidak ada tools project yang dapat dikembalikan oleh Anda');

                
                if (!empty($selectedIds)) {
                    $allowedIds = array_column($projectTools, 'id');
                    $projectTools = array_filter($projectTools, fn($t) => in_array((int)$t['id'], $selectedIds));
                    $projectTools = array_values($projectTools);
                    if (empty($projectTools)) throw new Exception('Tools yang dipilih tidak valid atau bukan milik Anda');
                }

                
                $toolIds = array_column($projectTools, 'id');
                $placeholders = implode(',', array_fill(0, count($toolIds), '?'));
                $stmtCheck = $pdo->prepare("SELECT tool_id FROM tool_permits WHERE tool_id IN ($placeholders) AND permit_type = 'return' AND status = 'pending'");
                $stmtCheck->execute($toolIds);
                $pendingReturns = $stmtCheck->fetchAll(PDO::FETCH_COLUMN);

                
                $projectTools = array_filter($projectTools, fn($t) => !in_array($t['id'], $pendingReturns));
                $projectTools = array_values($projectTools);

                if (empty($projectTools)) throw new Exception('Semua tools project sudah memiliki permintaan return yang menunggu approval');
                $permitStatus = $isAdmin ? 'approved' : 'pending';
                
                $pdo->beginTransaction();
                try {
                    $ins = $pdo->prepare("
                        INSERT INTO tool_permits (permit_type, tool_id, from_user_id, to_user_id, status, photo_proof_path, reason, created_at" . ($isAdmin ? ", approved_at, approved_by" : "") . ")
                        VALUES ('return', ?, ?, ?, ?, ?, 'Bulk return project tools ke PT. Artha Solusi Aditama', ?" . ($isAdmin ? ", NOW(), ?" : "") . ")
                    ");
                    
                    foreach ($projectTools as $tool) {
                        $params = [$tool['id'], $_SESSION['user_id'], $ADMIN_USER_ID, $permitStatus, $photoPath, $returnDatetime];
                        if ($isAdmin) $params[] = $_SESSION['user_id'];
                        $ins->execute($params);
                        
                        if ($isAdmin) {
                            $pdo->prepare("UPDATE tools SET current_status = 'Ready' WHERE id = ?")->execute([$tool['id']]);
                            $pdo->prepare("INSERT INTO tool_status_history (tool_id, from_status, to_status, user_id, notes) VALUES (?, 'Project', 'Ready', ?, 'Bulk return project tools')")
                                ->execute([$tool['id'], $_SESSION['user_id']]);
                        }
                    }
                    
                    $pdo->commit();
                    $count = count($projectTools);
                    $response = [
                        'success' => true, 
                        'message' => $isAdmin 
                            ? "$count tools berhasil dikembalikan dari project" 
                            : "Permintaan return $count tools telah dikirim, menunggu approval admin"
                    ];
                    auditLog($pdo, 'bulk_return_project', [
                        'target_type' => 'tool',
                        'details' => ['count' => $count, 'tool_ids' => array_column($projectTools, 'id'), 'status' => $permitStatus]
                    ]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw new Exception('Gagal memproses bulk return: ' . $e->getMessage());
                }
                break;
            
            case 'edit_project':
                require_role(['administrator', 'direktur']);
                $toolId = (int)($_POST['tool_id'] ?? 0);
                $picName = trim($_POST['pic_name'] ?? '');
                $location = trim($_POST['location'] ?? '');
                $startDate = str_replace('T', ' ', $_POST['start_date'] ?? '') . ':00';
                $endDate = str_replace('T', ' ', $_POST['end_date'] ?? '') . ':00';
                $technicianId = (int)($_POST['technician_id'] ?? 0);

                if (!$toolId || !$picName || !$location || !$startDate || !$endDate || !$technicianId) {
                    throw new Exception('Data tidak lengkap');
                }

                
                $stmtTech = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1");
                $stmtTech->execute([$technicianId]);
                if (!$stmtTech->fetch()) throw new Exception('Teknisi tidak valid');

                
                $stmt = $pdo->prepare("
                    SELECT id FROM tool_permits
                    WHERE tool_id = ? AND status = 'approved' AND permit_type = 'project'
                    ORDER BY id DESC LIMIT 1
                ");
                $stmt->execute([$toolId]);
                $permit = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$permit) throw new Exception('Tidak ada permit project aktif untuk tool ini');

                $pdo->prepare("
                    UPDATE tool_permits SET reason = ?, location = ?, start_date = ?, end_date = ?, to_user_id = ?
                    WHERE id = ?
                ")->execute([$picName, $location, $startDate, $endDate, $technicianId, $permit['id']]);

                $response = ['success' => true, 'message' => 'Data project berhasil diperbarui'];
                break;

            
            case 'edit_loan':
                $toolId = (int)($_POST['tool_id'] ?? 0);
                $purpose = trim($_POST['purpose'] ?? '');
                $location = trim($_POST['location'] ?? '');
                $startDate = $_POST['start_date'] ?? '';
                $endDate = $_POST['end_date'] ?? '';
                $toUserId = (int)($_POST['to_user_id'] ?? 0);

                if (!$toolId || !$purpose || !$location || !$startDate || !$endDate) {
                    throw new Exception('Data tidak lengkap');
                }
                if (strtotime($endDate) <= strtotime($startDate)) {
                    throw new Exception('Jatuh tempo harus setelah tanggal mulai pinjam');
                }

                
                $stmt = $pdo->prepare("
                    SELECT p.id, p.to_user_id FROM tool_permits p
                    WHERE p.tool_id = ? AND p.status = 'approved' AND p.permit_type IN ('loan', 'project', 'handover')
                    AND NOT EXISTS (
                        SELECT 1 FROM tool_permits p2 WHERE p2.tool_id = p.tool_id AND p2.status = 'approved'
                        AND p2.permit_type IN ('return','force_return') AND p2.id > p.id
                    )
                    ORDER BY p.id DESC LIMIT 1
                ");
                $stmt->execute([$toolId]);
                $permit = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$permit) throw new Exception('Tidak ada peminjaman aktif untuk tool ini');

                
                $isAdmin = in_array($_SESSION['role'], ['administrator', 'direktur']);
                if (!$isAdmin && (int)$permit['to_user_id'] !== (int)$_SESSION['user_id']) {
                    throw new Exception('Anda tidak memiliki akses untuk mengedit peminjaman ini');
                }

                
                $updateUserId = ($isAdmin && $toUserId) ? $toUserId : (int)$permit['to_user_id'];

                $startDateFmt = str_replace('T', ' ', $startDate);
                if (strlen($startDateFmt) === 16) $startDateFmt .= ':00';
                $endDateFmt = str_replace('T', ' ', $endDate);
                if (strlen($endDateFmt) === 16) $endDateFmt .= ':00';

                $pdo->prepare("
                    UPDATE tool_permits SET reason = ?, location = ?, start_date = ?, end_date = ?, to_user_id = ?
                    WHERE id = ?
                ")->execute([$purpose, $location, $startDateFmt, $endDateFmt, $updateUserId, $permit['id']]);

                $response = ['success' => true, 'message' => 'Data peminjaman berhasil diperbarui'];
                auditLog($pdo, 'edit_loan', [
                    'target_type' => 'tool',
                    'target_id' => $toolId,
                    'target_user_id' => $updateUserId,
                    'details' => ['purpose' => $purpose, 'location' => $location, 'start_date' => $startDateFmt, 'end_date' => $endDateFmt]
                ]);
                break;

            
            case 'quick_edit_date':
                require_role(['administrator', 'direktur']);
                $toolId = (int)($_POST['tool_id'] ?? 0);
                $newEndDate = $_POST['new_end_date'] ?? '';

                if (!$toolId || !$newEndDate) {
                    throw new Exception('Data tidak lengkap');
                }

                
                $stmt = $pdo->prepare("
                    SELECT p.id, p.start_date FROM tool_permits p
                    WHERE p.tool_id = ? AND p.status = 'approved' AND p.permit_type IN ('loan', 'handover', 'project')
                    AND NOT EXISTS (
                        SELECT 1 FROM tool_permits p2 WHERE p2.tool_id = p.tool_id AND p2.status = 'approved'
                        AND p2.permit_type IN ('return','force_return') AND p2.id > p.id
                    )
                    ORDER BY p.id DESC LIMIT 1
                ");
                $stmt->execute([$toolId]);
                $permit = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$permit) throw new Exception('Tidak ada peminjaman aktif untuk tool ini');

                $endDateFmt = str_replace('T', ' ', $newEndDate);
                if (strlen($endDateFmt) === 16) $endDateFmt .= ':00';

                if ($permit['start_date'] && strtotime($endDateFmt) <= strtotime($permit['start_date'])) {
                    throw new Exception('Jatuh tempo harus setelah tanggal mulai pinjam');
                }

                $pdo->prepare("UPDATE tool_permits SET end_date = ? WHERE id = ?")->execute([$endDateFmt, $permit['id']]);

                $response = ['success' => true, 'message' => 'Tanggal pengembalian berhasil diperbarui'];
                break;

            
            case 'extend_loan':
                $toolId = (int)($_POST['tool_id'] ?? 0);
                $newEndDate = $_POST['new_end_date'] ?? '';

                if (!$toolId || !$newEndDate) {
                    throw new Exception('Data tidak lengkap');
                }

                
                $stmt = $pdo->prepare("
                    SELECT p.id, p.to_user_id, p.end_date, p.permit_type FROM tool_permits p
                    WHERE p.tool_id = ? AND p.status = 'approved' AND p.permit_type IN ('loan', 'project', 'handover')
                    AND NOT EXISTS (
                        SELECT 1 FROM tool_permits p2 WHERE p2.tool_id = p.tool_id AND p2.status = 'approved'
                        AND p2.permit_type IN ('return','force_return') AND p2.id > p.id
                    )
                    ORDER BY p.id DESC LIMIT 1
                ");
                $stmt->execute([$toolId]);
                $permit = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$permit) throw new Exception('Tidak ada peminjaman aktif untuk tool ini');

                
                require_role(['administrator', 'direktur']);

                $newEndFmt = str_replace('T', ' ', $newEndDate);
                if (strlen($newEndFmt) === 16) $newEndFmt .= ':00';

                if (strtotime($newEndFmt) <= strtotime($permit['end_date'])) {
                    throw new Exception('Tanggal baru harus setelah jatuh tempo saat ini (' . date('d M Y H:i', strtotime($permit['end_date'])) . ')');
                }

                $pdo->prepare("UPDATE tool_permits SET end_date = ? WHERE id = ?")->execute([$newEndFmt, $permit['id']]);

                
                $currentToolStatus = $pdo->prepare("SELECT current_status FROM tools WHERE id = ?");
                $currentToolStatus->execute([$toolId]);
                $toolStatus = $currentToolStatus->fetchColumn() ?: 'Loan';
                $pdo->prepare("
                    INSERT INTO tool_status_history (tool_id, from_status, to_status, user_id, notes)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$toolId, $toolStatus, $toolStatus, $_SESSION['user_id'], 'Perpanjang pinjaman s/d ' . date('d M Y H:i', strtotime($newEndFmt))]);

                $response = ['success' => true, 'message' => 'Peminjaman berhasil diperpanjang'];
                auditLog($pdo, 'extend_loan', [
                    'target_type' => 'tool',
                    'target_id' => $toolId,
                    'details' => ['new_end_date' => $newEndFmt, 'old_end_date' => $permit['end_date']]
                ]);
                break;

            
            case 'force_return_all_project':
                require_role(['administrator', 'direktur']);

                
                $stmt = $pdo->query("SELECT id, current_status FROM tools WHERE current_status = 'Project' AND tool_type = 'company'");
                $projectTools = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($projectTools)) throw new Exception('Tidak ada tools dalam status Project');

                $pdo->beginTransaction();
                try {
                    foreach ($projectTools as $tool) {
                        
                        $pdo->prepare("UPDATE tools SET current_status = 'Ready' WHERE id = ?")->execute([$tool['id']]);

                        
                        $currentTime = date('Y-m-d H:i:s');
                        $pdo->prepare("
                            INSERT INTO tool_permits (permit_type, tool_id, from_user_id, to_user_id, status, created_at, approved_at, approved_by)
                            VALUES ('force_return', ?, ?, ?, 'approved', ?, ?, ?)
                        ")->execute([$tool['id'], $_SESSION['user_id'], $ADMIN_USER_ID, $currentTime, $currentTime, $_SESSION['user_id']]);

                        
                        $pdo->prepare("
                            INSERT INTO tool_status_history (tool_id, from_status, to_status, user_id, notes)
                            VALUES (?, 'Project', 'Ready', ?, 'Force return all project by administrator')
                        ")->execute([$tool['id'], $_SESSION['user_id']]);
                    }
                    $pdo->commit();
                    $count = count($projectTools);
                    $response = ['success' => true, 'message' => "$count tools berhasil di-return paksa dari project"];
                    auditLog($pdo, 'force_return_all_project', [
                        'target_type' => 'tool',
                        'details' => ['count' => $count, 'tool_ids' => array_column($projectTools, 'id')]
                    ]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw new Exception('Gagal memproses force return all project: ' . $e->getMessage());
                }
                break;

            
            case 'project_handover':
                $toolId      = (int)($_POST['tool_id'] ?? 0);
                $isAdmin     = in_array($_SESSION['role'], ['administrator', 'direktur']);
                
                $toUserId    = $isAdmin
                    ? (int)($_POST['to_user_id'] ?? 0)
                    : (int)($_SESSION['user_id']);
                $purpose     = trim($_POST['purpose'] ?? '');
                $picName     = trim($_POST['pic_name'] ?? '');
                $location    = trim($_POST['location'] ?? '');
                $projectName = trim($_POST['project_name'] ?? '');

                if (!$toolId || !$toUserId || !$purpose || !$picName || !$location || !$projectName) {
                    throw new Exception('Data tidak lengkap. Nama Project, Lokasi, PIC, dan Tujuan Handover wajib diisi.');
                }

                
                $stmt = $pdo->prepare("
                    SELECT tp.to_user_id AS current_holder_id, u.full_name AS current_holder_name, t.current_status
                    FROM tools t
                    JOIN tool_permits tp ON t.id = tp.tool_id
                    JOIN users u ON tp.to_user_id = u.id
                    WHERE t.id = ? AND tp.status = 'approved' AND tp.permit_type IN ('loan','handover','project')
                    ORDER BY tp.id DESC LIMIT 1
                ");
                $stmt->execute([$toolId]);
                $current = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$current) throw new Exception('Tools tidak sedang dipegang oleh siapapun');
                if ($current['current_status'] !== 'Project') throw new Exception('Handover project hanya untuk tools berstatus Project');

                
                if (!$isAdmin && (int)$current['current_holder_id'] === (int)$_SESSION['user_id']) {
                    throw new Exception('Anda sudah memegang tools ini. Gunakan tombol Return untuk mengembalikan.');
                }

                
                if ((int)$current['current_holder_id'] === (int)$toUserId) {
                    throw new Exception('Teknisi tujuan sudah memegang tools ini.');
                }

                
                $photoPath = upload_tool_file($_FILES['proof_photo'] ?? null, 'handover', $_SESSION['user_id']);

                $permitStatus = $isAdmin ? 'approved' : 'pending';

                $pdo->beginTransaction();
                try {
                    
                    $reasonText = "PIC: {$picName} | Project: {$projectName} | Tujuan: {$purpose}";
                    $stmt = $pdo->prepare("
                        INSERT INTO tool_permits (permit_type, tool_id, from_user_id, to_user_id, status, reason, location, photo_proof_path" . ($isAdmin ? ", approved_at, approved_by" : "") . ")
                        VALUES ('handover', ?, ?, ?, ?, ?, ?, ?" . ($isAdmin ? ", NOW(), ?" : "") . ")
                    ");
                    $params = [$toolId, $current['current_holder_id'], $toUserId, $permitStatus, $reasonText, $location, $photoPath];
                    if ($isAdmin) $params[] = $_SESSION['user_id'];
                    $stmt->execute($params);

                    
                    if ($isAdmin) {
                        $pdo->prepare("UPDATE tools SET current_status='Project' WHERE id=?")->execute([$toolId]);
                        $pdo->prepare("INSERT INTO tool_status_history (tool_id, from_status, to_status, user_id, notes) VALUES (?, ?, 'Project', ?, ?)")
                            ->execute([$toolId, $current['current_status'], $_SESSION['user_id'], $reasonText]);
                    }

                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw new Exception('Gagal memproses handover: ' . $e->getMessage());
                }

                $response = [
                    'success' => true,
                    'message' => $isAdmin
                        ? 'Handover berhasil, tools langsung dipindahkan ke teknisi tujuan'
                        : 'Permintaan handover project dikirim ke ' . $current['current_holder_name'] . ', menunggu approval.'
                ];
                auditLog($pdo, 'project_handover', [
                    'target_type' => 'tool',
                    'target_id' => $toolId,
                    'target_user_id' => $toUserId,
                    'details' => ['from_user' => $current['current_holder_name'], 'location' => $location, 'project_name' => $projectName, 'status' => $permitStatus]
                ]);
                break;

            
            case 'list_apd':
              $stmt = $pdo->query("
                  SELECT 
                    t.id, t.name, t.code, t.current_status, t.photo_path,
                    (
                      SELECT tp.to_user_id 
                      FROM tool_permits tp 
                      WHERE tp.tool_id = t.id 
                      AND tp.status = 'approved' 
                      AND tp.permit_type IN ('loan', 'handover')
                      AND NOT EXISTS (
                        SELECT 1 FROM tool_permits r WHERE r.tool_id = t.id AND r.status = 'approved' AND r.permit_type IN ('return','force_return','apd_return') AND r.id > tp.id
                      )
                      ORDER BY tp.id DESC LIMIT 1
                    ) AS holder_id,
                    (
                      SELECT u.full_name 
                      FROM tool_permits tp 
                      JOIN users u ON u.id = tp.to_user_id
                      WHERE tp.tool_id = t.id 
                      AND tp.status = 'approved' 
                      AND tp.permit_type IN ('loan', 'handover')
                      AND NOT EXISTS (
                        SELECT 1 FROM tool_permits r WHERE r.tool_id = t.id AND r.status = 'approved' AND r.permit_type IN ('return','force_return','apd_return') AND r.id > tp.id
                      )
                      ORDER BY tp.id DESC LIMIT 1
                    ) AS holder_name,
                    (
                      SELECT tp.location 
                      FROM tool_permits tp 
                      WHERE tp.tool_id = t.id 
                      AND tp.status = 'approved' 
                      AND tp.permit_type IN ('loan', 'handover')
                      AND NOT EXISTS (
                        SELECT 1 FROM tool_permits r WHERE r.tool_id = t.id AND r.status = 'approved' AND r.permit_type IN ('return','force_return','apd_return') AND r.id > tp.id
                      )
                      ORDER BY tp.id DESC LIMIT 1
                    ) AS holder_location,
                    (
                      SELECT tp.start_date 
                      FROM tool_permits tp 
                      WHERE tp.tool_id = t.id 
                      AND tp.status = 'approved' 
                      AND tp.permit_type IN ('loan', 'handover')
                      AND NOT EXISTS (
                        SELECT 1 FROM tool_permits r WHERE r.tool_id = t.id AND r.status = 'approved' AND r.permit_type IN ('return','force_return','apd_return') AND r.id > tp.id
                      )
                      ORDER BY tp.id DESC LIMIT 1
                    ) AS holder_start_date,
                    (
                      SELECT tp.end_date 
                      FROM tool_permits tp 
                      WHERE tp.tool_id = t.id 
                      AND tp.status = 'approved' 
                      AND tp.permit_type IN ('loan', 'handover')
                      AND NOT EXISTS (
                        SELECT 1 FROM tool_permits r WHERE r.tool_id = t.id AND r.status = 'approved' AND r.permit_type IN ('return','force_return','apd_return') AND r.id > tp.id
                      )
                      ORDER BY tp.id DESC LIMIT 1
                    ) AS holder_end_date,
                    (
                      SELECT ap.full_name 
                      FROM tool_permits tp 
                      JOIN users ap ON ap.id = tp.approved_by
                      WHERE tp.tool_id = t.id 
                      AND tp.status = 'approved' 
                      AND tp.permit_type IN ('loan', 'handover')
                      AND NOT EXISTS (
                        SELECT 1 FROM tool_permits r WHERE r.tool_id = t.id AND r.status = 'approved' AND r.permit_type IN ('return','force_return','apd_return') AND r.id > tp.id
                      )
                      ORDER BY tp.id DESC LIMIT 1
                    ) AS approved_by_name,
                    (
                      SELECT tp.reason 
                      FROM tool_permits tp 
                      WHERE tp.tool_id = t.id 
                      AND tp.status = 'approved' 
                      AND tp.permit_type IN ('loan', 'handover')
                      AND NOT EXISTS (
                        SELECT 1 FROM tool_permits r WHERE r.tool_id = t.id AND r.status = 'approved' AND r.permit_type IN ('return','force_return','apd_return') AND r.id > tp.id
                      )
                      ORDER BY tp.id DESC LIMIT 1
                    ) AS purpose
                  FROM tools t
                  WHERE t.tool_type = 'apd'
                  ORDER BY t.name ASC
              ");
              $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
              break;

            case 'add_apd':
              require_role(['administrator', 'direktur', 'hse']);
              $name  = trim($_POST['name'] ?? '');
              $code  = trim($_POST['code'] ?? '');
              $notes = trim($_POST['notes'] ?? '');
              $quantity = (int)($_POST['quantity'] ?? 1);
              $photo = upload_file($_FILES['photo'] ?? null, 'apd');

              if (!$name || !$code) throw new Exception('Nama dan Kode APD wajib diisi');

              $pdo->beginTransaction();
              $stmt = $pdo->prepare("
                  INSERT INTO tools (name, code, tool_type, photo_path, current_status, condition_notes)
                  VALUES (?, ?, 'apd', ?, 'Ready', ?)
              ");
              for ($i = 0; $i < $quantity; $i++) {
                  $uniqueCode = $quantity > 1 ? $code . '-' . ($i + 1) : $code;
                  $stmt->execute([$name, $uniqueCode, $photo, $notes]);
              }
              $pdo->commit();
              $response = ['success' => true, 'message' => $quantity . ' APD berhasil ditambahkan'];
              break;

            case 'apd_request':
              $toolId = (int)($_POST['tool_id'] ?? 0);
              $toUserId = in_array($_SESSION['role'], ['administrator', 'direktur', 'hse'])
                  ? (int)($_POST['to_user_id'] ?? 0)
                  : (int)$_SESSION['user_id'];
              $purpose = trim($_POST['purpose'] ?? 'Assign APD');
              $location = trim($_POST['location'] ?? '');
              $startDate = trim($_POST['start_date'] ?? '');
              $endDate = trim($_POST['end_date'] ?? '');

              if (!$toolId || !$toUserId || !$location || !$purpose) {
                  throw new Exception('Data tidak lengkap. Lokasi dan Keperluan wajib diisi.');
              }

              $stmt = $pdo->prepare("SELECT current_status FROM tools WHERE id=? AND tool_type='apd'");
              $stmt->execute([$toolId]);
              $status = $stmt->fetchColumn();
              if (!in_array($status, ['Ready', 'Assigned'], true)) {
                  throw new Exception('APD tidak tersedia. Status saat ini: ' . $status);
              }

              $isAdmin = in_array($_SESSION['role'], ['administrator', 'direktur', 'hse']);
              if (!$isAdmin) {
                  throw new Exception('Hanya admin/HSE yang bisa assign APD');
              }

              $photoPath = upload_tool_file($_FILES['proof_photo'] ?? null, 'apd_loan', $_SESSION['user_id']);

              $stmt = $pdo->prepare("
                  INSERT INTO tool_permits (permit_type, tool_id, from_user_id, to_user_id, status,
                                          reason, start_date, end_date, location, photo_proof_path, approved_at, approved_by)
                  VALUES ('loan', ?, ?, ?, 'approved', ?, ?, ?, ?, ?, NOW(), ?)
              ");
              $stmt->execute([$toolId, $_SESSION['user_id'], $toUserId, $purpose, $startDate ?: null, $endDate ?: null, $location, $photoPath, $_SESSION['user_id']]);

              $pdo->prepare("UPDATE tools SET current_status='Loan' WHERE id=?")->execute([$toolId]);
              $pdo->prepare("INSERT INTO tool_status_history (tool_id, from_status, to_status, user_id, notes) VALUES (?, ?, 'Loan', ?, ?)")
                  ->execute([$toolId, $status, $_SESSION['user_id'], $purpose]);

              auditLog($pdo, 'assign_apd', [
                  'target_type' => 'tool',
                  'target_id' => $toolId,
                  'target_user_id' => $toUserId,
                  'details' => ['purpose' => $purpose, 'location' => $location]
              ]);

              $response = ['success' => true, 'message' => 'APD berhasil di-assign ke teknisi'];
              break;

            case 'apd_return':
              $toolId = (int)($_POST['tool_id'] ?? 0);
              $returnDatetime = trim($_POST['return_datetime'] ?? '');
              if (!$toolId || !$returnDatetime) throw new Exception('Data tidak lengkap');

              $photoPath = upload_tool_file($_FILES['return_photo'] ?? null, 'apd_return', $_SESSION['user_id']);
              if (!$photoPath) throw new Exception('Foto bukti pengembalian APD wajib diupload');

              $stmt = $pdo->prepare("
                  SELECT t.current_status, tp.to_user_id
                  FROM tools t
                  JOIN tool_permits tp ON t.id = tp.tool_id
                  WHERE t.id = ? AND t.tool_type = 'apd'
                  AND tp.status = 'approved' AND tp.permit_type IN ('loan', 'handover')
                  ORDER BY tp.id DESC LIMIT 1
              ");
              $stmt->execute([$toolId]);
              $current = $stmt->fetch(PDO::FETCH_ASSOC);

              if (!$current || (int)$current['to_user_id'] !== (int)$_SESSION['user_id']) {
                  throw new Exception('Anda tidak memegang APD ini');
              }
              if ($current['current_status'] !== 'Loan' && $current['current_status'] !== 'Handover') {
                  throw new Exception('APD harus dalam status Loan/Handover untuk dikembalikan');
              }

              $stmt = $pdo->prepare("SELECT id FROM tool_permits WHERE tool_id = ? AND permit_type = 'return' AND status = 'pending' LIMIT 1");
              $stmt->execute([$toolId]);
              if ($stmt->fetch()) throw new Exception('Sudah ada permintaan return APD yang menunggu approval');

              $stmt = $pdo->prepare("
                  INSERT INTO tool_permits (permit_type, tool_id, from_user_id, to_user_id, status, photo_proof_path, reason, created_at)
                  VALUES ('return', ?, ?, ?, 'pending', ?, 'Pengembalian APD', ?)
              ");
              $stmt->execute([$toolId, $_SESSION['user_id'], $ADMIN_USER_ID, $photoPath, $returnDatetime]);
              $response = ['success' => true, 'message' => 'Permintaan return APD dikirim, menunggu approval admin'];
              auditLog($pdo, 'apd_return_request', [
                  'target_type' => 'tool',
                  'target_id' => $toolId,
                  'details' => ['return_datetime' => $returnDatetime]
              ]);
              break;

            case 'apd_force_return':
              require_role(['administrator', 'direktur', 'hse']);
              $toolId = (int)($_POST['tool_id'] ?? 0);
              if (!$toolId) throw new Exception('Tool ID required');

              $stmt = $pdo->prepare("SELECT current_status FROM tools WHERE id=? AND tool_type='apd'");
              $stmt->execute([$toolId]);
              $oldStatus = $stmt->fetchColumn();
              if (!$oldStatus) throw new Exception('APD tidak ditemukan');
              if ($oldStatus === 'Ready') throw new Exception('APD sudah dalam status Ready');

              $pdo->prepare("UPDATE tools SET current_status='Ready' WHERE id=?")->execute([$toolId]);
              $pdo->prepare("INSERT INTO tool_status_history (tool_id, from_status, to_status, user_id, notes) VALUES (?, ?, 'Ready', ?, 'Force return APD by admin')")
                  ->execute([$toolId, $oldStatus, $_SESSION['user_id']]);
              auditLog($pdo, 'force_return_apd', [
                  'target_type' => 'tool',
                  'target_id' => $toolId,
                  'details' => ['old_status' => $oldStatus]
              ]);
              $response = ['success' => true, 'message' => 'APD berhasil dikembalikan paksa'];
              break;

            case 'delete_apd':
              require_role(['administrator', 'direktur', 'hse']);
              $toolId = (int)($_POST['tool_id'] ?? 0);
              if (!$toolId) throw new Exception('Tool ID required');

              $pdo->beginTransaction();
              $pdo->prepare("DELETE FROM tool_permits WHERE tool_id = ?")->execute([$toolId]);
              $pdo->prepare("DELETE FROM tool_status_history WHERE tool_id = ?")->execute([$toolId]);
              $pdo->prepare("DELETE FROM tools WHERE id = ? AND tool_type = 'apd'")->execute([$toolId]);
              $pdo->commit();
              auditLog($pdo, 'delete_apd', [
                  'target_type' => 'tool',
                  'target_id' => $toolId,
                  'details' => ['action' => 'delete_apd']
              ]);
              $response = ['success' => true, 'message' => 'APD berhasil dihapus'];
              break;
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;

    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}


function upload_file($file, string $subdir): ?string {
    if (!$file || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $basePath = __DIR__ . "/../../public/assets/uploads/tools/" . $subdir . "/";
    if (!is_dir($basePath)) mkdir($basePath, 0775, true);

    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $name = uniqid("tool_") . ".jpg"; 
    $target = $basePath . $name;

    if (!move_uploaded_file($file['tmp_name'], $target)) return null;

    
    compress_uploaded_image($target, 1280, 75);

    return "./public/assets/uploads/tools/$subdir/$name";
}

 
function upload_tool_file($file, string $subdir, int $user_id): ?string {
    if (!$file || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    
    $basePath = __DIR__ . "/../../storage/uploads/tools/" . $subdir . "/" . $user_id . "/";
    if (!is_dir($basePath)) mkdir($basePath, 0775, true);

    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $name = uniqid("tool_") . ".jpg"; 
    $target = $basePath . $name;

    if (!move_uploaded_file($file['tmp_name'], $target)) return null;

    
    compress_uploaded_image($target, 1280, 75);

    return "storage/uploads/tools/$subdir/$user_id/$name";
}

 
function compress_uploaded_image(string $path, int $maxWidth = 1280, int $quality = 75): bool {
    if (!file_exists($path) || !function_exists('imagecreatefromjpeg')) return false;
    
    $info = @getimagesize($path);
    if (!$info) return false;
    
    [$origW, $origH, $type] = $info;
    
    
    if ($origW <= $maxWidth && filesize($path) < 307200) return true;
    
    switch ($type) {
        case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($path); break;
        case IMAGETYPE_PNG:  $img = @imagecreatefrompng($path); break;
        case IMAGETYPE_WEBP: $img = @imagecreatefromwebp($path); break;
        case IMAGETYPE_GIF:  $img = @imagecreatefromgif($path); break;
        default: return false;
    }
    if (!$img) return false;
    
    $w = $origW; $h = $origH;
    if ($w > $maxWidth) {
        $h = intval($h * $maxWidth / $w);
        $w = $maxWidth;
        $resized = imagecreatetruecolor($w, $h);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $w, $h, $origW, $origH);
        imagedestroy($img);
        $img = $resized;
    }
    
    $result = imagejpeg($img, $path, $quality);
    imagedestroy($img);
    return $result;
}
?>



<style>
.tools-sticky-bg {
  background-color: #ffffff !important;
}
html[data-theme="dark"] .tools-sticky-bg {
  background-color: #0f172a !important;
}

#btnSubmitProject:disabled {
  background-color: #c084fc !important;
  color: #ffffff !important;
  cursor: not-allowed;
  opacity: 0.7;
}
#btnSubmitProject:not(:disabled):hover {
  background-color: #9333ea;
  cursor: pointer;
}

.fixed.inset-0[id^="modal"] {
  -webkit-overflow-scrolling: touch;
  overscroll-behavior: contain;
}
.fixed.inset-0[id^="modal"] button,
.fixed.inset-0[id^="modal"] input,
.fixed.inset-0[id^="modal"] select,
.fixed.inset-0[id^="modal"] textarea,
.fixed.inset-0[id^="modal"] a {
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
  cursor: pointer;
}
.fixed.inset-0[id^="modal"] button[type="submit"],
.fixed.inset-0[id^="modal"] button[type="button"] {
  min-height: 44px;
  position: relative;
  z-index: 1;
}
@media (max-width: 768px) {
  .fixed.inset-0[id^="modal"] > div {
    max-height: 85vh;
    max-height: 85dvh;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
    margin: auto;
  }
  .fixed.inset-0[id^="modal"] {
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
    align-items: flex-start;
    padding-top: 2rem;
    padding-bottom: 2rem;
  }
}

html[data-theme="dark"] #modalLoan,
html[data-theme="dark"] #modalLoan .modal-content,
html[data-theme="dark"] #modalLoan > div > div {
  background-color: #1e293b !important;
  color: #e2e8f0 !important;
  border-color: #334155 !important;
}

html[data-theme="dark"] #modalLoan input,
html[data-theme="dark"] #modalLoan textarea,
html[data-theme="dark"] #modalLoan select {
  background-color: #0f172a !important;
  color: #f8fafc !important;
  border-color: #334155 !important;
}

html[data-theme="dark"] #modalLoan label {
  color: #cbd5e1 !important;
}

html[data-theme="dark"] #modalLoan .text-gray-500 {
  color: #94a3b8 !important;
}

html[data-theme="dark"] #modalLoan button[data-close],
html[data-theme="dark"] #modalLoan .px-4.py-2.bg-gray-200 {
  background-color: #334155 !important;
  color: #e2e8f0 !important;
}

html[data-theme="dark"] #modalLoan .px-4.py-2.bg-gray-200:hover {
  background-color: #475569 !important;
}

@media (prefers-color-scheme: dark) {
  html:not([data-theme="dark"]) {
    background-color: #ffffff;
    color-scheme: light;
  }

  html:not([data-theme="dark"]) body {
    background-color: #ffffff;
    color: #111827;
  }

  html:not([data-theme="dark"]) .bg-gray-100,
  html:not([data-theme="dark"]) .dark\:bg-gray-800,
  html:not([data-theme="dark"]) .dark\:bg-gray-900 {
    background-color: #f3f4f6 !important;
  }

  html:not([data-theme="dark"]) .text-gray-700,
  html:not([data-theme="dark"]) .dark\:text-gray-200,
  html:not([data-theme="dark"]) .text-gray-900,
  html:not([data-theme="dark"]) .text-gray-600,
  html:not([data-theme="dark"]) .dark\:text-gray-300 {
    color: #111827 !important;
  }

  html:not([data-theme="dark"]) .tab-btn {
    background-color: #ffffff !important;
    border-color: #e5e7eb !important;
  }
}
</style>


<script>
  const TOOLS_API_URL = <?= json_encode(rtrim(BASE_URL, '/') . '/app/pages/tools.php') ?>;
  const CURRENT_USER_ID = <?= (int)($_SESSION['user_id'] ?? 0) ?>;
  const CURRENT_ROLE = "<?= htmlspecialchars($_SESSION['role'] ?? '', ENT_QUOTES) ?>";
</script>


<div id="toolsTabBarAnchor" style="height:0"></div>
<div id="toolsTabBar" class="tools-sticky-bg" style="padding-bottom:4px; padding-top:8px; background-color: #ffffff;">
  <div class="border-b border-gray-200 dark:border-gray-700">
    <nav class="flex gap-2 flex-wrap" role="tablist">
      <button data-tab="tools" class="tab-btn px-4 py-2 rounded-t-lg text-sm font-medium bg-white dark:bg-gray-900 border border-b-0">Alat</button>
      <button data-tab="apd" class="tab-btn px-4 py-2 rounded-t-lg text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100">APD</button>
      <button data-tab="personal" class="tab-btn px-4 py-2 rounded-t-lg text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100">Alat Personal</button>
    </nav>
  </div>
</div>
<script>
(function(){
  var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  var bg = isDark ? '#0f172a' : '#ffffff';
  var tabBar = document.getElementById('toolsTabBar');
  if (tabBar) tabBar.style.backgroundColor = bg;
  var headers = document.querySelectorAll('.tools-section-header');
  for (var i = 0; i < headers.length; i++) {
    headers[i].style.backgroundColor = bg;
  }
})();
</script>


<section id="tab-tools" class="space-y-4">
  <!-- My Borrowed Tools Section -->
  <div id="myBorrowedSection" class="mb-4">
    <div class="bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 border border-amber-200 dark:border-amber-700/50 rounded-xl p-4">
      <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-800/40 flex items-center justify-center">
            <i class="fas fa-hand-holding text-amber-600 dark:text-amber-400 text-sm"></i>
          </div>
          <h3 class="font-semibold text-amber-900 dark:text-amber-200 text-sm">Tools yang Sedang Anda Pinjam</h3>
          <span id="myBorrowedCount" class="text-xs font-bold bg-amber-200 dark:bg-amber-700 text-amber-800 dark:text-amber-200 px-2 py-0.5 rounded-full">0</span>
        </div>
        <button id="toggleMyBorrowed" class="text-xs text-amber-600 dark:text-amber-400 hover:underline">
          <i class="fas fa-chevron-down" id="myBorrowedChevron"></i>
        </button>
      </div>
      <div id="myBorrowedBody">
        <div id="myBorrowedEmpty" class="hidden text-sm text-amber-700 dark:text-amber-300 italic py-2">
          <i class="fas fa-check-circle mr-1"></i> Anda tidak sedang meminjam tools apapun.
        </div>
        <div id="myBorrowedList" class="overflow-x-auto">
          <table class="min-w-full text-sm text-left">
            <thead class="bg-amber-100/60 dark:bg-amber-800/30">
              <tr class="text-amber-800 dark:text-amber-200 text-xs uppercase">
                <th class="px-3 py-2">Tools</th>
                <th class="px-3 py-2">Kode</th>
                <th class="px-3 py-2">Tipe</th>
                <th class="px-3 py-2">Lokasi</th>
                <th class="px-3 py-2">Jatuh Tempo</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Aksi</th>
              </tr>
            </thead>
            <tbody id="tblMyBorrowed" class="divide-y divide-amber-100 dark:divide-amber-800/30"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="tools-section-header tools-sticky-bg py-3" style="background-color: #ffffff;">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <div class="text-xl font-semibold">Alat</div>
      <div class="flex flex-wrap items-center gap-2">
        <input id="searchTools" type="text" placeholder="Cari alat / peminjam..."
               class="px-3 py-2 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm w-64">
        <button id="btnBulkReturnProject"
                class="hidden inline-flex items-center gap-2 px-4 py-2 bg-orange-600 text-white rounded-lg shadow hover:bg-orange-700 transition-colors font-medium text-sm"
                style="background-color:#f97316;color:#ffffff">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
          Kembalikan Semua Alat
        </button>
        <?php if (in_array(($_SESSION['role'] ?? ''), ['administrator', 'direktur'])): ?>
        <button id="btnForceReturnAllProject"
                class="hidden px-4 py-2 bg-red-600 text-white rounded-lg shadow hover:bg-red-700">
          Kembalikan Paksa Semua
        </button>
        <?php endif; ?>
        <button id="btnSubmitProject"
                class="px-4 py-2 bg-purple-600 text-white rounded-lg shadow hover:bg-purple-700"
                style="opacity:1;"
                disabled>
          Ajukan Peminjaman
        </button>
        <?php if (in_array(($_SESSION['role'] ?? ''), ['administrator', 'direktur'])): ?>
        <button id="btnAddCompanyTool"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700">
          + Tambah Alat
        </button>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm text-left">
      <thead class="bg-gray-100 dark:bg-gray-800">
        <tr class="text-gray-700 dark:text-gray-200">
          <th class="px-4 py-2 w-10"><input type="checkbox" id="checkAllProject"></th>
          <th class="px-4 py-2">Nama Alat</th>
          <th class="px-4 py-2">Kode Alat</th>
          <th class="px-4 py-2">Foto</th>
          <th class="px-4 py-2">Aksi</th>
          <th class="px-4 py-2">Status</th>
        </tr>
      </thead>
      <tbody id="tblTools" class="divide-y divide-gray-200 dark:divide-gray-700"></tbody>
    </table>
  </div>
</section>


<section id="tab-apd" class="space-y-4 hidden">
  <div class="tools-section-header tools-sticky-bg py-3" style="background-color: #ffffff;">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <div class="text-xl font-semibold">Permintaan APD</div>
      <div class="flex items-center gap-2">
        <input id="searchApd" type="text" placeholder="Cari APD..."
               class="px-3 py-2 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm w-64">
        <?php if (in_array(($_SESSION['role'] ?? ''), ['administrator', 'direktur', 'hse'])): ?>
        <button id="btnAddApd"
                class="px-4 py-2 bg-teal-600 text-white rounded-lg shadow hover:bg-teal-700"
                style="background-color:#0d9488;color:#fff;">
          + Tambah APD
        </button>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm text-left">
      <thead class="bg-gray-100 dark:bg-gray-800">
        <tr class="text-gray-700 dark:text-gray-200 text-center uppercase">
          <th class="px-4 py-2">NAMA APD</th>
          <th class="px-4 py-2">KODE</th>
          <th class="px-4 py-2">DIPINJAM OLEH</th>
          <th class="px-4 py-2">AKSI</th>
          <th class="px-4 py-2">STATUS</th>
        </tr>
      </thead>
      <tbody id="tblApd" class="divide-y divide-gray-200 dark:divide-gray-700"></tbody>
    </table>
  </div>
</section>


<section id="tab-personal" class="space-y-4 hidden">
  <div class="tools-section-header tools-sticky-bg py-3" style="background-color: #ffffff;">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <div class="text-xl font-semibold">Alat Personal</div>
      <div class="flex items-center gap-2">
        <?php if (in_array(($_SESSION['role'] ?? ''), ['administrator', 'direktur', 'hse'])): ?>
        <button id="btnExportPersonalTools"
                class="px-4 py-2 bg-emerald-600 text-white rounded-lg shadow hover:bg-emerald-700 flex items-center gap-2">
          <i class="fas fa-file-excel"></i> Ekspor Excel
        </button>
        <button id="btnAddPersonalTool"
                class="px-4 py-2 bg-green-600 text-white rounded-lg shadow hover:bg-green-700">
          + Tambah Alat Personal
        </button>
        <?php endif; ?>
        <a href="dashboard.php?page=check-monthly-tools"
         class="px-4 py-2 text-white rounded-lg shadow hover:bg-amber-700"
         style="background-color:#d97706;">
        Cek Bulanan Alat
       </a>
    </div>
  </div>

  <div class="flex items-center gap-2 mt-2">
    <input id="searchTechnician" type="text" placeholder="Cari Teknisi..."
           class="px-3 py-2 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm w-64">
  </div>
  </div>

  <div id="gridTechnicians" class="grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4"></div>
</section>


<div id="modalDetail" class="hidden fixed inset-0 z-[70] bg-black/50 items-center justify-center p-4 sm:p-6 overflow-y-auto">
  <div class="bg-white dark:bg-gray-900 rounded-2xl w-full max-w-3xl shadow-xl mx-auto my-8 max-h-[90vh] flex flex-col">
    <div class="flex justify-between items-center px-5 pt-5 pb-3 shrink-0">
      <h3 class="font-semibold text-gray-900 dark:text-white text-base">Detail Alat</h3>
      <button data-close="modalDetail" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400 hover:text-gray-600 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div id="detailBody" class="px-5 pb-5 overflow-y-auto flex-1"></div>
  </div>
</div>

<div id="modalAddCompany" class="hidden fixed inset-0 z-[70] bg-black/50 items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-lg p-6">
    <div class="flex justify-between items-center mb-3">
      <h3 class="font-semibold">Tambah Alat Perusahaan</h3>
      <button data-close="modalAddCompany" class="text-gray-500">&times;</button>
    </div>
    <form id="formAddCompany" class="space-y-3">
        <input name="quantity" type="number" placeholder="Jumlah" min="1" value="1" class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
        <input name="name" type="text" placeholder="Nama Alat" class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
        <input name="code" type="text" placeholder="Code Alat" class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
        <textarea name="notes" placeholder="Detail/Kondisi" class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded"></textarea>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Foto Alat <span class="text-xs text-gray-400 font-normal">(opsional)</span></label>
          <input name="photo" type="file" accept="image/*" class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 dark:file:bg-blue-900/30 dark:file:text-blue-300 hover:file:bg-blue-100">
        </div>
        <div class="flex justify-end gap-2">
            <button type="button" data-close="modalAddCompany" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded">Batal</button>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Simpan</button>
        </div>
    </form>
  </div>
</div>

<div id="modalAddPersonal" class="hidden fixed inset-0 z-[70] bg-black/50 items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-lg p-6">
    <div class="flex justify-between items-center mb-3">
      <h3 class="font-semibold">Tambah Alat Personal</h3>
      <button data-close="modalAddPersonal" class="text-gray-500">&times;</button>
    </div>
    <form id="formAddPersonal" class="space-y-3">
      <input name="name" type="text" placeholder="Nama Alat" class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
      <input name="code" type="text" placeholder="Code Alat" class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
      <textarea name="notes" placeholder="Detail/Kondisi" class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded"></textarea>
      <select name="assign_type" class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded">
        <option value="single">Untuk 1 teknisi</option>
        <option value="all">Untuk semua teknisi</option>
      </select>
      <select name="assign_to" id="assignToSelect" class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded"><option value="">-- pilih teknisi --</option></select>
      <div class="flex justify-end gap-2">
        <button type="button" data-close="modalAddPersonal" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded">Batal</button>
        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded">Simpan</button>
      </div>
    </form>
  </div>
</div>

<div id="modalTechTools" class="hidden fixed inset-0 z-[70] bg-black/50 items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-3xl p-6 max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
    <div class="flex justify-between items-center mb-3">
      <h3 id="techToolsTitle" class="font-semibold">Alat Teknisi</h3>
      <div class="flex items-center gap-2">
        <?php if (in_array(($_SESSION['role'] ?? ''), ['administrator', 'direktur'])): ?>
        <button id="btnAssignApdToTech" type="button" onclick="event.stopPropagation(); window._assignApdClick && window._assignApdClick()" class="px-3 py-1.5 bg-teal-600 text-white text-xs rounded-lg hover:bg-teal-700 flex items-center gap-1" style="background-color:#0d9488;color:#fff;">
          <svg class="w-3.5 h-3.5 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
          <span class="pointer-events-none">APD</span>
        </button>
        <?php endif; ?>
        <button data-close="modalTechTools" class="text-gray-500 text-xl">&times;</button>
      </div>
    </div>
    
    <div class="mb-4">
      <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Alat Personal</h4>
      <div class="overflow-x-auto">
      <table class="min-w-full text-sm text-left">
        <thead class="bg-gray-100 dark:bg-gray-800">
          <tr class="text-gray-700 dark:text-gray-200">
            <th class="px-4 py-2">Nama</th>
            <th class="px-4 py-2">Kode</th>
            <th class="px-4 py-2">Detail</th>
            <th class="px-4 py-2"></th>
          </tr>
        </thead>
        <tbody id="tblTechTools" class="divide-y divide-gray-200 dark:divide-gray-700"></tbody>
      </table>
      </div>
    </div>
    
    <div id="techApdSection">
      <div class="flex items-center justify-between mb-2">
        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">APD (Alat Pelindung Diri)</h4>
      </div>
      <div class="overflow-x-auto">
      <table class="min-w-full text-sm text-left">
        <thead class="bg-teal-50 dark:bg-teal-900/30">
          <tr class="text-gray-700 dark:text-gray-200">
            <th class="px-4 py-2">Nama</th>
            <th class="px-4 py-2">Kode</th>
            <th class="px-4 py-2">Status</th>
          </tr>
        </thead>
        <tbody id="tblTechApd" class="divide-y divide-gray-200 dark:divide-gray-700"></tbody>
      </table>
      </div>
      <p id="techApdEmpty" class="hidden text-sm text-gray-400 italic mt-2 px-1">Tidak ada APD yang dipinjam.</p>
    </div>
  </div>
</div>


<div id="modalEditPersonal" class="hidden fixed inset-0 z-[80] bg-black/50 items-center justify-center p-4 overflow-y-auto" style="z-index:80">
  <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
    <div class="flex justify-between items-center mb-4">
      <h3 class="font-semibold text-gray-900 dark:text-white text-base">Edit Alat Personal</h3>
      <button data-close="modalEditPersonal" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400 hover:text-gray-600 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="formEditPersonal" class="space-y-3">
      <input type="hidden" name="tool_id" id="editPersToolId">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Alat <span class="text-red-500">*</span></label>
        <input type="text" name="name" id="editPersName" required class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2.5 rounded-lg text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Kode Alat <span class="text-red-500">*</span></label>
        <input type="text" name="code" id="editPersCode" required class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2.5 rounded-lg text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
        <select name="current_status" id="editPersStatus" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2.5 rounded-lg text-sm">
          <option value="Good">Good</option>
          <option value="Repair">Repair</option>
          <option value="Missing">Missing</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Detail / Kondisi</label>
        <textarea name="condition_notes" id="editPersNotes" rows="2" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2.5 rounded-lg text-sm" style="resize:vertical"></textarea>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Foto <span class="text-xs text-gray-400 font-normal">(opsional)</span></label>
        <input type="file" name="photo" accept="image/*" class="w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700">
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" data-close="modalEditPersonal" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">Batal</button>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Simpan</button>
      </div>
    </form>
  </div>
</div>


<div id="modalLoan" class="hidden fixed inset-0 z-[70] bg-black/50 items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
    <div class="flex justify-between items-center mb-3">
      <h3 class="font-semibold">Form Peminjaman</h3>
      <button data-close="modalLoan" class="text-gray-500">&times;</button>
    </div>
    <form id="formLoan" class="space-y-3">
      <input type="hidden" name="tool_id" id="loanToolId">
      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Pilih PIC / Teknisi:</label>
        <select name="to_user_id" id="loanToUser" class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
          <option value="">-- Pilih PIC / Teknisi --</option>
        </select>
      </div>
      <input type="text" name="purpose" placeholder="Tujuan Peminjaman"
              class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
      <div class="flex gap-2">
        <div class="w-1/2">
          <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Mulai Pinjam</label>
          <input type="datetime-local" name="start_date" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded text-sm" required>
        </div>
        <div class="w-1/2">
          <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Jatuh Tempo</label>
          <input type="datetime-local" name="end_date" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded text-sm" required>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Lokasi Project:</label>
        <input type="text" name="location" placeholder="Lokasi (cth: Gudang Utama)"
               class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Upload Foto Bukti <span class="text-xs text-gray-400 font-normal">(opsional)</span></label>
        <input type="file" name="photo" accept="image/*" class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 dark:file:bg-blue-900/30 dark:file:text-blue-300 hover:file:bg-blue-100">
      </div>
      <div class="flex justify-end gap-2">
        <button type="button" data-close="modalLoan" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded">Batal</button>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Ajukan</button>
      </div>
    </form>
  </div>
</div>


<div id="modalReturn" class="hidden fixed inset-0 z-[70] bg-black/50 items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
    <div class="flex justify-between items-center mb-3">
      <h3 class="font-semibold">Form Pengembalian</h3>
      <button data-close="modalReturn" class="text-gray-500">&times;</button>
    </div>
    <form id="formReturn" class="space-y-3">
      <input type="hidden" name="tool_id" id="returnToolId">
      <div class="flex gap-2">
        <input type="datetime-local" name="return_datetime" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Upload Foto Bukti <span class="text-xs text-gray-400 font-normal">(opsional)</span></label>
        <input type="file" name="photos[]" accept="image/*" multiple class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 dark:file:bg-blue-900/30 dark:file:text-blue-300 hover:file:bg-blue-100">
        <p class="text-xs text-gray-400 mt-1">Bisa lebih dari 1 foto</p>
      </div>
      <div class="flex justify-end gap-2">
        <button type="button" data-close="modalReturn" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded">Batal</button>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Ajukan Pengembalian</button>
      </div>
    </form>
  </div>
</div>


<div id="modalHandover" class="hidden fixed inset-0 z-[70] bg-black/50 items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
    <div class="flex justify-between items-center mb-3">
      <h3 class="font-semibold">Form Serah Terima</h3>
      <button data-close="modalHandover" class="text-gray-500">&times;</button>
    </div>
    
    
    
    <form id="formHandover" class="space-y-3">
      <input type="hidden" name="tool_id" id="handoverToolId">
      
      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Pilih Staff Tujuan:</label>
        <select name="to_user_id" id="handoverToUser" class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
          <option value="">-- Pilih Teknisi --</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Lokasi Tools Dibawa Ke:</label>
        <input type="text" name="location" id="handoverLocation" placeholder="Lokasi tools (cth: Gudang Utama, Site Project A)"
               class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
      </div>
      
      <textarea name="purpose" placeholder="Tujuan / Alasan Serah Terima"
                class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required
                id="handoverPurpose"></textarea>

      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Upload Foto Bukti <span class="text-xs text-gray-400 font-normal">(opsional)</span></label>
        <input type="file" name="photos[]" accept="image/*" multiple class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 dark:file:bg-blue-900/30 dark:file:text-blue-300 hover:file:bg-blue-100">
        <p class="text-xs text-gray-400 mt-1">Bisa lebih dari 1 foto</p>
      </div>
      <div class="flex justify-end gap-2">
        <button type="button" data-close="modalHandover" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded">Batal</button>
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded">Ajukan Serah Terima</button>
      </div>
    </form>
  </div>
</div>


<div id="modalProject" class="hidden fixed inset-0 z-[70] bg-black/50 items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
    <div class="flex justify-between items-center mb-3">
      <h3 class="font-semibold">Form Pengajuan Peminjaman</h3>
      <button data-close="modalProject" class="text-gray-500">&times;</button>
    </div>
    <form id="formProject" class="space-y-3">
      <input type="hidden" name="tool_ids" id="projectToolIds">
      
      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">PIC / Penanggung Jawab Tools:</label>
        <input type="text" name="pic_name" placeholder="Nama Penanggung Jawab" 
               class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
      </div>
      
      <div class="flex gap-2">
        <div class="w-1/2">
          <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Mulai:</label>
          <input type="datetime-local" name="start_date" id="projectStartDate" class="w-full border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white p-2 rounded text-sm" readonly>
        </div>
        <div class="w-1/2">
          <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Jatuh Tempo: <span class="text-red-500">*</span></label>
          <input type="datetime-local" name="end_date" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded text-sm" required>
        </div>
      </div>
      
      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Lokasi Project / Alat dibawa ke:</label>
        <input type="text" name="location" placeholder="Lokasi Tools / Project (cth: Pax Ocean)"
               class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
      </div>

      <?php if (in_array(($_SESSION['role'] ?? ''), ['administrator', 'direktur'])): ?>
      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Pilih Teknisi Tujuan (opsional):</label>
        <select name="to_user_id" id="projectToUser" class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded">
          <option value="">-- Pilih Teknisi --</option>
          <?php
            $techs = $pdo->query("SELECT id, full_name, role FROM users WHERE role IN ('technician','hse','technician_manager','sales') AND is_active=1 ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($techs as $t) {
              $suffix = ($t['role'] === 'sales') ? ' (Sales)' : '';
              echo '<option value="' . (int)$t['id'] . '">' . htmlspecialchars($t['full_name'] . $suffix, ENT_QUOTES) . '</option>';
            }
          ?>
        </select>
      </div>
      <?php elseif (in_array(($_SESSION['role'] ?? ''), ['daily', 'internship'])): ?>
      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Pilih Teknisi Penanggung Jawab <span class="text-red-500">*</span></label>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1.5">Tools akan tercatat atas nama teknisi yang dipilih</p>
        <select name="to_user_id" id="projectToUser" class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
          <option value="">-- Pilih Teknisi --</option>
          <?php
            $techs = $pdo->query("SELECT id, full_name, role FROM users WHERE role IN ('technician','hse','technician_manager','sales') AND is_active=1 ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($techs as $t) {
              $suffix = ($t['role'] === 'sales') ? ' (Sales)' : '';
              echo '<option value="' . (int)$t['id'] . '">' . htmlspecialchars($t['full_name'] . $suffix, ENT_QUOTES) . '</option>';
            }
          ?>
        </select>
      </div>
      <?php endif; ?>

      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Upload Foto Bukti <span class="text-xs text-gray-400 font-normal">(opsional)</span></label>
        <input type="file" name="photos[]" accept="image/*" multiple class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 dark:file:bg-blue-900/30 dark:file:text-blue-300 hover:file:bg-blue-100">
        <p class="text-xs text-gray-400 mt-1">Bisa lebih dari 1 foto</p>
      </div>

      <div class="flex justify-end gap-2">
        <button type="button" data-close="modalProject" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded">Batal</button>
        <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded">Ajukan Peminjaman</button>
      </div>
    </form>
  </div>
</div>


<div id="modalBulkReturn" class="hidden fixed inset-0 z-[70] bg-black/50 items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white dark:bg-gray-900 rounded-2xl w-full max-w-lg shadow-2xl mx-auto max-h-[90vh] overflow-y-auto">
    <div class="flex justify-between items-center px-5 pt-5 pb-3 border-b border-gray-100 dark:border-gray-800">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-orange-100 dark:bg-orange-900/40 flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
        </div>
        <div>
          <h3 class="font-semibold text-base text-gray-900 dark:text-white">Kembalikan Alat</h3>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Pilih alat yang akan dikembalikan</p>
        </div>
      </div>
      <button data-close="modalBulkReturn" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400 hover:text-gray-600 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="px-5 pt-4 pb-2">
      
      <div class="relative mb-3">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
        <input type="text" id="bulkReturnSearch" placeholder="Cari alat, PIC, lokasi..." class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-orange-400 focus:border-orange-400 outline-none transition">
      </div>
      
      <div class="flex items-center justify-between mb-2 px-1">
        <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
          <input type="checkbox" id="bulkReturnSelectAll" class="rounded border-gray-300 text-orange-500 focus:ring-orange-400">
          <span class="text-gray-700 dark:text-gray-300 font-medium">Pilih Semua</span>
        </label>
        <span id="bulkReturnSelectedCount" class="text-xs font-medium text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-900/30 px-2.5 py-1 rounded-full"></span>
      </div>
      
      <div id="bulkReturnToolList" class="mb-4 max-h-60 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-xl divide-y divide-gray-100 dark:divide-gray-800 text-sm"></div>
    </div>
    <form id="formBulkReturn" class="px-5 pb-5 space-y-3 border-t border-gray-100 dark:border-gray-800 pt-4">
      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Tanggal Pengembalian <span class="text-red-500">*</span></label>
        <input type="datetime-local" name="return_datetime" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2.5 rounded-lg text-sm" required>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Upload Foto Bukti <span class="text-xs text-gray-400 font-normal">(opsional)</span></label>
        <input type="file" name="photos[]" accept="image/*" multiple class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 dark:file:bg-blue-900/30 dark:file:text-blue-300 hover:file:bg-blue-100">
        <p class="text-xs text-gray-400 mt-1">Bisa lebih dari 1 foto</p>
      </div>
      <div class="flex justify-end gap-2 pt-3">
        <button type="button" data-close="modalBulkReturn" class="px-4 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-white rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors text-sm font-medium">Batal</button>
        <button id="btnBulkReturnSubmit" type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-orange-500 text-white rounded-lg shadow hover:bg-orange-600 transition-colors font-medium text-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
          Kembalikan Terpilih
        </button>
      </div>
    </form>
  </div>
</div>


<div id="modalEditProject" class="hidden fixed inset-0 z-[70] bg-black/50 items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
    <div class="flex justify-between items-center mb-3">
      <h3 class="font-semibold">Edit Data Peminjaman</h3>
      <button data-close="modalEditProject" class="text-gray-500">&times;</button>
    </div>
    <form id="formEditProject" class="space-y-3">
      <input type="hidden" name="tool_id" id="editProjectToolId">
      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Teknisi / Pemegang:</label>
        <select name="technician_id" id="editProjectTechnician" class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded text-sm" required>
          <option value="">-- Pilih Teknisi --</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Nama Project / PIC:</label>
        <input type="text" name="pic_name" id="editProjectPicName" placeholder="Nama Penanggung Jawab"
               class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Lokasi Project:</label>
        <input type="text" name="location" id="editProjectLocation" placeholder="Lokasi Project"
               class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
      </div>
      <div class="flex gap-2">
        <div class="w-1/2">
          <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Mulai:</label>
          <input type="datetime-local" name="start_date" id="editProjectStartDate" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded text-sm" required>
        </div>
        <div class="w-1/2">
          <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Jatuh Tempo:</label>
          <input type="datetime-local" name="end_date" id="editProjectEndDate" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded text-sm" required>
        </div>
      </div>
      <div class="flex justify-end gap-2">
        <button type="button" data-close="modalEditProject" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded">Batal</button>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>


<div id="modalProjectHandover" class="hidden fixed inset-0 z-[70] bg-black/50 items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
    <div class="flex justify-between items-center mb-4">
      <h3 class="font-semibold text-gray-900 dark:text-white">Serah Terima Alat Project</h3>
      <button data-close="modalProjectHandover" class="text-gray-500 hover:text-gray-700">&times;</button>
    </div>

    
    <div id="projectHandoverToolInfo" class="mb-4 text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-3 rounded-lg text-gray-800 dark:text-gray-200">
      <div class="font-medium text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider mb-1">Informasi Alat</div>
      <div id="projectHandoverToolInfoText"></div>
    </div>

    <form id="formProjectHandover" class="space-y-3">
      <input type="hidden" name="tool_id" id="projectHandoverToolId">

      
      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Nama Project <span class="text-red-500">*</span></label>
        <input type="text" name="project_name" id="projHandoverProjectName"
               placeholder="Nama project / nama pekerjaan"
               class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
      </div>

      
      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Lokasi Project <span class="text-red-500">*</span></label>
        <input type="text" name="location" id="projHandoverLocation"
               placeholder="Lokasi project (cth: Pax Ocean)"
               class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
      </div>

      
      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">PIC / Penanggung Jawab Peminjaman <span class="text-red-500">*</span></label>
        <input type="text" name="pic_name" id="projHandoverPicName"
               placeholder="Nama penanggung jawab peminjaman alat"
               class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
      </div>

      
      <?php if (in_array(($_SESSION['role'] ?? ''), ['administrator', 'direktur'])): ?>
      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Teknisi Tujuan <span class="text-red-500">*</span></label>
        <select name="to_user_id" id="projectHandoverToUser" class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
          <option value="">-- Pilih Teknisi --</option>
        </select>
      </div>
      <?php else: ?>
      <input type="hidden" name="to_user_id" id="projectHandoverToUser" value="">
      <?php endif; ?>

      
      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Tujuan / Alasan Serah Terima <span class="text-red-500">*</span></label>
        <textarea name="purpose" id="projHandoverPurpose" rows="3"
                  placeholder="Jelaskan tujuan atau alasan serah terima..."
                  class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required></textarea>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Upload Foto Bukti <span class="text-xs text-gray-400 font-normal">(opsional)</span></label>
        <input type="file" name="photos[]" accept="image/*" multiple class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 dark:file:bg-blue-900/30 dark:file:text-blue-300 hover:file:bg-blue-100">
        <p class="text-xs text-gray-400 mt-1">Bisa lebih dari 1 foto</p>
      </div>

      <div class="flex justify-end gap-2 pt-1">
        <button type="button" data-close="modalProjectHandover" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded">Batal</button>
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Ajukan Serah Terima</button>
      </div>
    </form>
  </div>
</div>


<div id="modalAddApd" class="hidden fixed inset-0 z-[70] bg-black/50 items-center justify-center p-4 sm:p-6">
  <div class="bg-white dark:bg-gray-900 rounded-2xl w-full max-w-md shadow-xl mx-auto max-h-[90vh] overflow-y-auto">
    <div class="flex justify-between items-center px-5 pt-5 pb-3">
      <h3 class="font-semibold text-gray-900 dark:text-white text-base">Tambah APD</h3>
      <button data-close="modalAddApd" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400 hover:text-gray-600 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="formAddApd" class="px-5 pb-5 space-y-3">
      <div>
        <label class="block text-sm font-medium mb-1">Nama APD <span class="text-red-500">*</span></label>
        <input name="name" required class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded p-2">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Kode APD <span class="text-red-500">*</span></label>
        <input name="code" required class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded p-2">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Jumlah</label>
        <input name="quantity" type="number" min="1" value="1" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded p-2">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Foto</label>
        <input name="photo" type="file" accept="image/*" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded p-2">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Catatan</label>
        <textarea name="notes" rows="2" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded p-2"></textarea>
      </div>
      <div class="flex justify-end gap-2 pt-1">
        <button type="button" data-close="modalAddApd" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded">Batal</button>
        <button type="submit" class="px-4 py-2 bg-teal-600 text-white rounded hover:bg-teal-700" style="background-color:#0d9488;color:#fff;">Simpan</button>
      </div>
    </form>
  </div>
</div>


<div id="modalApdLoan" class="hidden fixed inset-0 z-[70] bg-black/50 items-center justify-center p-4 sm:p-6">
  <div class="bg-white dark:bg-gray-900 rounded-2xl w-full max-w-md shadow-xl mx-auto max-h-[90vh] overflow-y-auto">
    <div class="flex justify-between items-center px-5 pt-5 pb-3">
      <h3 class="font-semibold text-gray-900 dark:text-white text-base">Pinjam APD</h3>
      <button data-close="modalApdLoan" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400 hover:text-gray-600 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="formApdLoan" class="px-5 pb-5 space-y-3">
      <input type="hidden" name="tool_id" id="apdLoanToolId">
      <?php if (in_array(($_SESSION['role'] ?? ''), ['administrator', 'direktur', 'hse'])): ?>
      <div>
        <label class="block text-sm font-medium mb-1">Pinjamkan ke <span class="text-red-500">*</span></label>
        <select name="to_user_id" id="apdLoanToUser" required class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded p-2">
          <option value="">-- Pilih Staff --</option>
        </select>
      </div>
      <?php endif; ?>
      <div>
        <label class="block text-sm font-medium mb-1">Lokasi APD Dibawa Ke <span class="text-red-500">*</span></label>
        <input name="location" id="apdLoanLocation" required placeholder="Lokasi (cth: Gudang Utama, Site Project A)" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded p-2">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Keperluan <span class="text-red-500">*</span></label>
        <input name="purpose" required placeholder="Keperluan APD" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded p-2">
      </div>
      <div class="grid grid-cols-2 gap-2">
        <div>
          <label class="block text-sm font-medium mb-1">Mulai <span class="text-red-500">*</span></label>
          <input name="start_date" type="datetime-local" id="apdStartDate" required class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded p-2">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Selesai <span class="text-red-500">*</span></label>
          <input name="end_date" type="datetime-local" id="apdEndDate" required class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded p-2">
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Foto Bukti</label>
        <input name="proof_photo" type="file" accept="image/*" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded p-2">
      </div>
      <div class="flex justify-end gap-2 pt-1">
        <button type="button" data-close="modalApdLoan" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded">Batal</button>
        <button type="submit" class="px-4 py-2 bg-teal-600 text-white rounded hover:bg-teal-700" style="background-color:#0d9488;color:#fff;">Ajukan</button>
      </div>
    </form>
  </div>
</div>


<div id="modalAssignApd" class="hidden fixed inset-0 z-[70] bg-black/50 items-center justify-center p-4 sm:p-6">
  <div class="bg-white dark:bg-gray-900 rounded-2xl w-full max-w-md shadow-xl mx-auto max-h-[90vh] overflow-y-auto">
    <div class="flex justify-between items-center px-5 pt-5 pb-3">
      <h3 class="font-semibold text-gray-900 dark:text-white text-base">Tambah APD ke Teknisi</h3>
      <button data-close="modalAssignApd" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400 hover:text-gray-600 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="formAssignApd" class="px-5 pb-5 space-y-3">
      <input type="hidden" name="to_user_id" id="assignApdUserId">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Teknisi</label>
        <input type="text" id="assignApdUserName" readonly class="w-full border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2.5 text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pilih APD <span class="text-red-500">*</span></label>
        <select name="tool_id" id="assignApdSelect" required class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded-lg p-2.5 text-sm">
          <option value="">-- Pilih APD yang tersedia --</option>
        </select>
        <p class="text-xs text-gray-400 mt-1">Hanya APD dengan status Ready yang ditampilkan</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Keterangan</label>
        <input type="text" name="purpose" placeholder="Keterangan (opsional)" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded-lg p-2.5 text-sm">
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" data-close="modalAssignApd" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">Batal</button>
        <button type="submit" class="px-4 py-2 bg-teal-600 text-white rounded-lg text-sm hover:bg-teal-700" style="background-color:#0d9488;color:#fff;">Assign APD</button>
      </div>
    </form>
  </div>
</div>
<div id="modalApdReturn" class="hidden fixed inset-0 z-[100] bg-black/50 items-center justify-center p-4 sm:p-6" style="z-index:100 !important;">
  <div class="bg-white dark:bg-gray-900 rounded-2xl w-full max-w-md shadow-xl mx-auto max-h-[90vh] overflow-y-auto">
    <div class="flex justify-between items-center px-5 pt-5 pb-3">
      <h3 class="font-semibold text-gray-900 dark:text-white text-base">Kembalikan APD</h3>
      <button data-close="modalApdReturn" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400 hover:text-gray-600 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="formApdReturn" class="px-5 pb-5 space-y-3">
      <input type="hidden" name="tool_id" id="apdReturnToolId">
      <div>
        <label class="block text-sm font-medium mb-1">Tanggal Pengembalian <span class="text-red-500">*</span></label>
        <input name="return_datetime" type="datetime-local" required class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded p-2">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Foto Bukti <span class="text-red-500">*</span></label>
        <div class="hidden md:block">
          <input type="file" name="return_photo" id="apd_return_photo_desktop" accept="image/*" required
                 class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded p-2 w-full">
        </div>
        <div class="md:hidden flex gap-2">
          <button type="button" class="apd-return-cam-btn px-3 py-2 bg-blue-500 text-white rounded text-sm" data-source="camera">Kamera</button>
          <button type="button" class="apd-return-cam-btn px-3 py-2 bg-green-500 text-white rounded text-sm" data-source="gallery">Galeri</button>
          <button type="button" class="apd-return-cam-btn px-3 py-2 bg-gray-500 text-white rounded text-sm" data-source="file" style="background-color:#6b7280;color:#fff;">File</button>
        </div>
        <div id="apdReturnPhotoPreview" class="hidden mt-2">
          <img src="" class="w-32 h-32 object-cover rounded border border-gray-300 dark:border-gray-600">
        </div>
      </div>
      <div class="flex justify-end gap-2 pt-1">
        <button type="button" data-close="modalApdReturn" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded">Batal</button>
        <button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600">Kembalikan</button>
      </div>
    </form>
  </div>
</div>


<script src="./public/assets/js/tools.js?v=<?= @filemtime(__DIR__ . '/../../public/assets/js/tools.js') ?: time() ?>"></script>

<script>
// My Borrowed Tools functionality
(function() {
  const API = (typeof TOOLS_API_URL !== 'undefined') ? TOOLS_API_URL : 'app/pages/tools.php';
  const tblMyBorrowed = document.getElementById('tblMyBorrowed');
  const myBorrowedCount = document.getElementById('myBorrowedCount');
  const myBorrowedEmpty = document.getElementById('myBorrowedEmpty');
  const myBorrowedList = document.getElementById('myBorrowedList');
  const myBorrowedSection = document.getElementById('myBorrowedSection');
  const toggleBtn = document.getElementById('toggleMyBorrowed');
  const chevron = document.getElementById('myBorrowedChevron');
  const myBorrowedBody = document.getElementById('myBorrowedBody');

  let isCollapsed = false;

  if (toggleBtn) {
    toggleBtn.addEventListener('click', function() {
      isCollapsed = !isCollapsed;
      if (myBorrowedBody) myBorrowedBody.style.display = isCollapsed ? 'none' : '';
      if (chevron) chevron.className = isCollapsed ? 'fas fa-chevron-right' : 'fas fa-chevron-down';
    });
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  }

  function loadMyBorrowedTools() {
    if (!tblMyBorrowed) return;
    fetch(API + '?action=list_my_borrowed_tools')
      .then(r => r.json())
      .then(data => {
        const tools = Array.isArray(data) ? data : [];
        myBorrowedCount.textContent = tools.length;

        if (tools.length === 0) {
          myBorrowedEmpty.classList.remove('hidden');
          myBorrowedList.classList.add('hidden');
          return;
        }

        myBorrowedEmpty.classList.add('hidden');
        myBorrowedList.classList.remove('hidden');
        tblMyBorrowed.innerHTML = '';

        tools.forEach(t => {
          const endDate = t.end_date ? new Date(t.end_date) : null;
          const now = new Date();
          const isOverdue = endDate && endDate < now;
          const permitLabel = t.permit_type === 'project' ? 'Project' : t.permit_type === 'handover' ? 'Handover' : 'Loan';
          const typeLabel = t.tool_type === 'apd' ? 'APD' : t.tool_type === 'personal' ? 'Personal' : 'Company';

          let statusHtml = '';
          if (isOverdue) {
            statusHtml = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300"><i class="fas fa-exclamation-triangle mr-1"></i>Terlambat</span>';
          } else {
            statusHtml = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">' + escapeHtml(permitLabel) + '</span>';
          }

          const tr = document.createElement('tr');
          tr.className = isOverdue ? 'bg-red-50/50 dark:bg-red-900/10' : '';
          tr.innerHTML = `
            <td class="px-3 py-2 font-medium text-gray-900 dark:text-white">${escapeHtml(t.name)}</td>
            <td class="px-3 py-2 text-gray-600 dark:text-gray-300 font-mono text-xs">${escapeHtml(t.code)}</td>
            <td class="px-3 py-2"><span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">${typeLabel}</span></td>
            <td class="px-3 py-2 text-gray-600 dark:text-gray-300 text-xs">${escapeHtml(t.location || '-')}</td>
            <td class="px-3 py-2 text-xs ${isOverdue ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-600 dark:text-gray-300'}">${endDate ? formatDate(t.end_date) : '-'}</td>
            <td class="px-3 py-2">${statusHtml}</td>
            <td class="px-3 py-2">
              <button data-return-id="${t.id}" class="my-borrowed-return-btn px-2 py-1 bg-yellow-500 hover:bg-yellow-600 text-white text-xs rounded transition-colors">
                <i class="fas fa-undo mr-1"></i>Return
              </button>
            </td>
          `;
          tblMyBorrowed.appendChild(tr);
        });

        // Attach return button events
        tblMyBorrowed.querySelectorAll('.my-borrowed-return-btn').forEach(btn => {
          btn.addEventListener('click', function() {
            const toolId = this.dataset.returnId;
            const returnModal = document.getElementById('modalReturn');
            const returnToolId = document.getElementById('returnToolId');
            if (returnToolId) returnToolId.value = toolId;
            if (returnModal) {
              returnModal.classList.add('flex');
              returnModal.classList.remove('hidden');
              document.documentElement.style.overflow = 'hidden';
            }
          });
        });
      })
      .catch(err => {
        console.error('Failed to load my borrowed tools:', err);
        if (myBorrowedEmpty) {
          myBorrowedEmpty.textContent = 'Gagal memuat data pinjaman.';
          myBorrowedEmpty.classList.remove('hidden');
        }
        if (myBorrowedList) myBorrowedList.classList.add('hidden');
      });
  }

  // Load on page ready
  loadMyBorrowedTools();

  // Refresh after tool actions
  window._refreshMyBorrowedTools = loadMyBorrowedTools;

  // Hook into existing refresh mechanism
  const origRefresh = window.refreshAllToolsData;
  window.refreshAllToolsData = function() {
    if (origRefresh) origRefresh();
    loadMyBorrowedTools();
  };
})();
</script>

<script>
(function() {
  var tabBar = document.getElementById('toolsTabBar');
  var anchor = document.getElementById('toolsTabBarAnchor');
  if (!tabBar || !anchor) return;

  var fixedHeader = document.querySelector('header');
  function getHeaderH() { return fixedHeader ? fixedHeader.offsetHeight : 60; }

  var isTabFixed = false;
  var isSectionFixed = false;
  var tabBarH = 0;
  var activeSectionHeader = null;
  var sidebarW = 256;

  function getActiveSection() {
    var tabs = ['tools','apd','personal'];
    for (var i = 0; i < tabs.length; i++) {
      var sec = document.getElementById('tab-' + tabs[i]);
      if (sec && !sec.classList.contains('hidden')) return sec;
    }
    return null;
  }

  function getThemeBg() {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    return isDark ? '#0f172a' : '#ffffff';
  }

  function applyFixed(el, topPx, zIndex) {
    el.style.position = 'fixed';
    el.style.top = topPx + 'px';
    el.style.left = (window.innerWidth >= 768 ? sidebarW : 0) + 'px';
    el.style.right = '0';
    el.style.zIndex = String(zIndex);
    el.style.backgroundColor = getThemeBg();
    el.style.paddingLeft = '24px';
    el.style.paddingRight = '24px';
    el.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
  }

  function removeFixed(el) {
    el.style.position = '';
    el.style.top = '';
    el.style.left = '';
    el.style.right = '';
    el.style.zIndex = '';
    el.style.backgroundColor = '';
    el.style.paddingLeft = '';
    el.style.paddingRight = '';
    el.style.boxShadow = '';
  }

  function onScroll() {
    var headerH = getHeaderH();
    var anchorRect = anchor.getBoundingClientRect();
    tabBarH = tabBar.offsetHeight;

    if (anchorRect.top <= headerH) {
      if (!isTabFixed) {
        isTabFixed = true;
        applyFixed(tabBar, headerH, 35);
        anchor.style.height = tabBarH + 'px';
      }
    } else {
      if (isTabFixed) {
        isTabFixed = false;
        removeFixed(tabBar);
        anchor.style.height = '0';
      }
    }

    var section = getActiveSection();
    var sectionHeader = section ? section.querySelector('.tools-section-header') : null;

    if (activeSectionHeader && activeSectionHeader !== sectionHeader) {
      removeFixed(activeSectionHeader);
      var prevPH = activeSectionHeader.previousElementSibling;
      if (prevPH && prevPH.classList.contains('section-header-ph')) {
        prevPH.style.height = '0';
      }
      isSectionFixed = false;
    }
    activeSectionHeader = sectionHeader;
    if (!sectionHeader) return;

    var placeholder = sectionHeader.previousElementSibling;
    if (!placeholder || !placeholder.classList.contains('section-header-ph')) {
      placeholder = document.createElement('div');
      placeholder.className = 'section-header-ph';
      placeholder.style.height = '0';
      sectionHeader.parentNode.insertBefore(placeholder, sectionHeader);
    }

    var fixTop = headerH + (isTabFixed ? tabBarH : 0);
    var refEl = isSectionFixed ? placeholder : sectionHeader;
    var refRect = refEl.getBoundingClientRect();
    var sectionHeaderH = sectionHeader.offsetHeight;

    if (refRect.top <= fixTop) {
      if (!isSectionFixed) {
        isSectionFixed = true;
        applyFixed(sectionHeader, fixTop, 25);
        placeholder.style.height = sectionHeaderH + 'px';
      }
    } else {
      if (isSectionFixed) {
        isSectionFixed = false;
        removeFixed(sectionHeader);
        placeholder.style.height = '0';
      }
    }
  }

  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', function() {
    if (isTabFixed) {
      tabBar.style.left = (window.innerWidth >= 768 ? sidebarW : 0) + 'px';
      tabBar.style.backgroundColor = getThemeBg();
    }
    if (isSectionFixed && activeSectionHeader) {
      activeSectionHeader.style.left = (window.innerWidth >= 768 ? sidebarW : 0) + 'px';
      activeSectionHeader.style.backgroundColor = getThemeBg();
    }
  }, { passive: true });

  // Update sticky bg when theme changes
  var themeObserver = new MutationObserver(function(mutations) {
    mutations.forEach(function(m) {
      if (m.attributeName === 'data-theme') {
        var bg = getThemeBg();
        if (isTabFixed) tabBar.style.backgroundColor = bg;
        if (isSectionFixed && activeSectionHeader) activeSectionHeader.style.backgroundColor = bg;
      }
    });
  });
  themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

  var observer = new MutationObserver(function() {
    if (activeSectionHeader) {
      removeFixed(activeSectionHeader);
      var ph = activeSectionHeader.previousElementSibling;
      if (ph && ph.classList.contains('section-header-ph')) ph.style.height = '0';
      isSectionFixed = false;
      activeSectionHeader = null;
    }
    requestAnimationFrame(onScroll);
  });
  ['tab-tools','tab-apd','tab-personal'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) observer.observe(el, { attributes: true, attributeFilter: ['class'] });
  });

  setTimeout(onScroll, 100);
  console.log('Sticky scroll handler initialized', { tabBar: !!tabBar, anchor: !!anchor, headerH: getHeaderH() });
})();

document.getElementById('btnExportPersonalTools')?.addEventListener('click', function() {
  window.location.href = 'dashboard.php?page=tools&action=export_personal_tools';
});
</script>
