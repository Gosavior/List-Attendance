<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/avatar.php';

$currentRole = strtolower($_SESSION['role'] ?? '');
$isTechnicianRole = $currentRole === 'technician';


$_adminRow = $pdo->query("SELECT id FROM users WHERE role = 'administrator' AND is_active = 1 ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$ADMIN_USER_ID = $_adminRow ? (int)$_adminRow['id'] : (int)$_SESSION['user_id'];

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($action !== '') {
    
    $ALLOWED_ACTIONS = [
        'list_company_tools',
        'add_company_tool',
        'loan_request',
        'tool_detail',
        'list_project_tools',
        'list_personal_tools',
        'list_technicians',
        'add_personal_tool',
        'project_request',
        'return_request',
        'handover_request',
        'delete_company_tool',
        'force_return'
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

    try {
        switch ($action) {
            
            case 'list_company_tools':
              $stmt = $pdo->query("
                  SELECT 
                    t.id, t.name, t.code, t.current_status, t.photo_path,
                    (
                      SELECT tp.to_user_id 
                      FROM tool_permits tp 
                      WHERE tp.tool_id = t.id 
                      AND tp.status = 'approved' 
                      AND tp.permit_type IN ('loan', 'handover')
                      AND (tp.approved_at IS NOT NULL OR tp.status = 'approved')
                      ORDER BY tp.id DESC LIMIT 1
                    ) AS holder_id,
                    (
                      SELECT u.full_name 
                      FROM tool_permits tp 
                      JOIN users u ON u.id = tp.to_user_id
                      WHERE tp.tool_id = t.id 
                      AND tp.status = 'approved' 
                      AND tp.permit_type IN ('loan', 'handover')
                      AND (tp.approved_at IS NOT NULL OR tp.status = 'approved')
                      ORDER BY tp.id DESC LIMIT 1
                    ) AS holder_name
                  FROM tools t
                  WHERE t.tool_type = 'company'
                  ORDER BY t.created_at DESC
              ");
              $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
              break;

            
            case 'add_company_tool':
            require_role('administrator');
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
              if (!$isTechnicianRole) {
                  throw new Exception('Hanya teknisi yang diperbolehkan meminjam tools');
              }
              
              $toolId = (int)($_POST['tool_id'] ?? 0);
              $purpose = trim($_POST['purpose'] ?? '');
              $startDate = $_POST['start_date'] ?? '';
              $endDate = $_POST['end_date'] ?? '';
              
              if (!$toolId || !$purpose || !$startDate || !$endDate) {
                  throw new Exception('Data tidak lengkap');
              }

              $stmt = $pdo->prepare("SELECT current_status FROM tools WHERE id=? AND tool_type='company'");
              $stmt->execute([$toolId]);
              $status = $stmt->fetchColumn();

              
              if ($status !== 'Ready') {
                  throw new Exception('Tools tidak tersedia untuk dipinjam. Status saat ini: ' . $status);
              }

              
              $photoPath = upload_tool_file($_FILES['proof_photo'] ?? null, 'loan', $_SESSION['user_id']);

              
              $stmt = $pdo->prepare("
                  INSERT INTO tool_permits (permit_type, tool_id, from_user_id, to_user_id, status, 
                  reason, start_date, end_date, photo_proof_path)
                  VALUES ('loan', ?, ?, ?, 'pending', ?, ?, ?, ?)
              ");
              $stmt->execute([$toolId, $ADMIN_USER_ID, $_SESSION['user_id'], $purpose, $startDate, $endDate, $photoPath]);

              $response = ['success' => true, 'message' => 'Permintaan pinjam dikirim, menunggu approval administrator'];
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
                        SELECT p.*, u.full_name 
                        FROM tool_permits p 
                        JOIN users u ON u.id=p.to_user_id
                        WHERE p.tool_id=? AND p.status='approved'
                        AND p.permit_type IN ('loan', 'handover')
                        ORDER BY p.id DESC LIMIT 1
                    ");
                    $st2->execute([$toolId]);
                    $holder = $st2->fetch(PDO::FETCH_ASSOC) ?: null;
                }

                $st3 = $pdo->prepare("
                    SELECT h.*, u.full_name 
                    FROM tool_status_history h
                    LEFT JOIN users u ON h.user_id=u.id
                    WHERE h.tool_id=? ORDER BY h.created_at DESC
                    LIMIT 30
                ");
                $st3->execute([$toolId]);
                $history = $st3->fetchAll(PDO::FETCH_ASSOC);

                $response = ['tool' => $tool, 'holder' => $holder, 'history' => $history];
                break;



          
          case 'handover_request':
        if (!$isTechnicianRole) {
          throw new Exception('Hanya teknisi yang dapat menerima handover tools');
        }
              $toolId = (int)($_POST['tool_id'] ?? 0);
              $toUserId = (int)($_POST['to_user_id'] ?? 0);
              $purpose = trim($_POST['purpose'] ?? '');
              
              if (!$toolId || !$toUserId || !$purpose) {
                  throw new Exception('Data tidak lengkap');
              }

              
              $stmt = $pdo->prepare("
                  SELECT t.current_status, tp.to_user_id as current_holder_id, u.full_name as current_holder_name
                  FROM tools t 
                  JOIN tool_permits tp ON t.id = tp.tool_id 
                  JOIN users u ON tp.to_user_id = u.id
                  WHERE t.id = ? 
                  AND tp.status = 'approved' 
                  AND tp.permit_type IN ('loan', 'handover')
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

              if ($current['current_status'] !== 'Loan' && $current['current_status'] !== 'Handover') {
                  throw new Exception('Tools harus dalam status Loan atau Handover untuk handover');
              }

              
              $stmt = $pdo->prepare("
                  INSERT INTO tool_permits (permit_type, tool_id, from_user_id, to_user_id, status, reason)
                  VALUES ('handover', ?, ?, ?, 'pending', ?)
              ");
              $stmt->execute([$toolId, $current['current_holder_id'], $_SESSION['user_id'], $purpose]);

              $response = [
                  'success' => true, 
                  'message' => 'Permintaan handover dikirim ke ' . $current['current_holder_name'] . ', menunggu approval'
              ];
              break;



            
            case 'list_project_tools':
                $stmt = $pdo->query("
                    SELECT id, name, code, photo_path, current_status
                    FROM tools 
                    WHERE tool_type='company'
                    ORDER BY created_at DESC
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
                    ORDER BY t.created_at DESC
                ");
                $st->execute([$techId]);
                $response = $st->fetchAll(PDO::FETCH_ASSOC);
                break;

            
            case 'list_technicians':
                $st = $pdo->query("
                    SELECT id, full_name, COALESCE(avatar, photo) AS avatar
                    FROM users 
                    WHERE role='technician' AND is_active=1
                    ORDER BY full_name ASC
                ");
                $response = $st->fetchAll(PDO::FETCH_ASSOC);
                break;

            
            case 'add_personal_tool':
                require_role('administrator');
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
                    
                    $techs = $pdo->query("SELECT id FROM users WHERE role IN ('technician','sales','daily') AND is_active=1")->fetchAll(PDO::FETCH_COLUMN);
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

            
            
            case 'return_request':
                $toolId = (int)($_POST['tool_id'] ?? 0);
                $returnDatetime = $_POST['return_datetime'] ?? '';
                
                if (!$toolId || !$returnDatetime) throw new Exception('Data tidak lengkap');
                
                
                $photoPath = upload_tool_file($_FILES['return_photo'] ?? null, 'return', $_SESSION['user_id']);
                
                if (!$photoPath) throw new Exception('Foto bukti pengembalian wajib diupload');
                
                
                $stmt = $pdo->prepare("
                    SELECT t.current_status, tp.to_user_id 
                    FROM tools t 
                    JOIN tool_permits tp ON t.id = tp.tool_id 
                    WHERE t.id = ? 
                    AND tp.status = 'approved' 
                    AND tp.permit_type IN ('loan', 'handover')
                    ORDER BY tp.id DESC LIMIT 1
                ");
                $stmt->execute([$toolId]);
                $current = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$current || (int)$current['to_user_id'] !== (int)$_SESSION['user_id']) {
                    throw new Exception('Anda tidak memegang tools ini');
                }

                if ($current['current_status'] !== 'Loan' && $current['current_status'] !== 'Handover') {
                    throw new Exception('Tools harus dalam status Loan atau Handover untuk dikembalikan');
                }

                
                $pdo->beginTransaction();
                
                try {
                    
                    $stmt = $pdo->prepare("UPDATE tools SET current_status = 'Ready' WHERE id = ?");
                    $stmt->execute([$toolId]);
                    
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO tool_permits (permit_type, tool_id, from_user_id, to_user_id, status, photo_proof_path, created_at, approved_at)
                        VALUES ('return', ?, ?, 1, 'approved', ?, ?, ?)
                    ");
                    $stmt->execute([$toolId, $_SESSION['user_id'], $photoPath, $returnDatetime, $returnDatetime]);
                    
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO tool_status_history (tool_id, from_status, to_status, user_id, notes, photo_proof_path)
                        VALUES (?, ?, 'Ready', ?, 'Returned to PT. Artha Solusi Aditama', ?)
                    ");
                    $stmt->execute([$toolId, $current['current_status'], $_SESSION['user_id'], $photoPath]);
                    
                    $pdo->commit();
                    $response = ['success' => true, 'message' => 'Tools berhasil dikembalikan ke PT. Artha Solusi Aditama'];
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw new Exception('Gagal memproses pengembalian: ' . $e->getMessage());
                }
                break;

            
            case 'force_return':
                require_role('administrator');
                $toolId = (int)($_POST['tool_id'] ?? 0);
                
                if (!$toolId) throw new Exception('Tool ID tidak valid');
                
                
                $stmt = $pdo->prepare("
                    SELECT t.current_status, tp.to_user_id, u.full_name as holder_name
                    FROM tools t 
                    JOIN tool_permits tp ON t.id = tp.tool_id 
                    WHERE t.id = ? 
                    AND tp.status = 'approved' 
                    AND tp.permit_type IN ('loan', 'handover')
                    ORDER BY tp.id DESC LIMIT 1
                ");
                $stmt->execute([$toolId]);
                $current = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$current || ($current['current_status'] !== 'Loan' && $current['current_status'] !== 'Handover')) {
                    throw new Exception('Tools tidak sedang dipinjam atau dalam status yang salah');
                }

                
                $pdo->beginTransaction();
                
                try {
                    
                    $stmt = $pdo->prepare("UPDATE tools SET current_status = 'Ready' WHERE id = ?");
                    $stmt->execute([$toolId]);
                    
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO tool_permits (permit_type, tool_id, from_user_id, to_user_id, status, created_at, approved_at)
                        VALUES ('force_return', ?, ?, 1, 'approved', ?, ?)
                    ");
                    $currentTime = date('Y-m-d H:i:s');
                    $stmt->execute([$toolId, $current['to_user_id'], $currentTime, $currentTime]);
                    
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO tool_status_history (tool_id, from_status, to_status, user_id, notes)
                        VALUES (?, ?, 'Ready', ?, 'Force returned by administrator')
                    ");
                    $stmt->execute([$toolId, $current['current_status'], $_SESSION['user_id']]);
                    
                    $pdo->commit();
                    $response = ['success' => true, 'message' => 'Return paksa berhasil. Tools dikembalikan ke PT. Artha Solusi Aditama'];
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw new Exception('Gagal memproses return paksa: ' . $e->getMessage());
                }
                break;

              
              case 'delete_company_tool':
                  require_role('administrator');
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
          if (!$isTechnicianRole) {
            throw new Exception('Hanya teknisi yang dapat mengajukan project tools');
          }
                    $ids = $_POST['tool_ids'] ?? [];
                    $picName = trim($_POST['pic_name'] ?? '');
                    $startDate = $_POST['start_date'] ?? '';
                    $endDate = $_POST['end_date'] ?? '';
                    
                    if (!is_array($ids) || !$ids || !$picName || !$startDate || !$endDate) {
                        throw new Exception('Data tidak lengkap. Pastikan semua field terisi.');
                    }
                    
                    
                    $photoPath = upload_tool_file($_FILES['proof_photo'] ?? null, 'project', $_SESSION['user_id']);
                    
                    if (!$photoPath) {
                        throw new Exception('Foto bukti wajib diupload');
                    }
                    
                    $ins = $pdo->prepare("
                        INSERT INTO tool_permits (permit_type, tool_id, from_user_id, to_user_id, status, reason, start_date, end_date, photo_proof_path)
                        VALUES ('project', ?, 1, ?, 'pending', ?, ?, ?, ?)
                    ");
                    
                    foreach ($ids as $tid) {
                        $tid = (int)$tid;
                        if ($tid > 0) {
                            $ins->execute([$tid, $_SESSION['user_id'], $picName, $startDate, $endDate, $photoPath]);
                        }
                    }
                    
                    $response = ['success'=>true, 'message'=>'Pengajuan project dikirim ke administrator'];
                    break;
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;

    } catch (Exception $e) {
        http_response_code(400);
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
html[data-theme="dark"] #modalLoan input,
html[data-theme="dark"] #modalLoan textarea,
html[data-theme="dark"] #modalLoan select,
html[data-theme="dark"] #modalLoan label {
  background-color: #ffffff !important;
  color: #111827 !important;
  border-color: #cbd5f5 !important;
}

html[data-theme="dark"] #modalLoan .text-gray-500 {
  color: #64748b !important;
}

html[data-theme="dark"] #modalLoan button[data-close],
html[data-theme="dark"] #modalLoan .px-4.py-2.bg-gray-200 {
  background-color: #e2e8f0 !important;
  color: #111827 !important;
}

html[data-theme="dark"] #modalLoan .px-4.py-2.bg-gray-200:hover {
  background-color: #cbd5f5 !important;
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


<div class="mb-4 border-b border-gray-200 dark:border-gray-700">
  <nav class="flex gap-2" role="tablist">
    <button data-tab="company" class="tab-btn px-4 py-2 rounded-t-lg text-sm font-medium bg-white dark:bg-gray-900 border border-b-0">Company Tools</button>
    <button data-tab="project" class="tab-btn px-4 py-2 rounded-t-lg text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100">Project Tools</button>
    <button data-tab="personal" class="tab-btn px-4 py-2 rounded-t-lg text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100">Personal Tools</button>
  </nav>
</div>


<section id="tab-company" class="space-y-4">
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <div class="text-xl font-semibold">Company Tools</div>
    <div class="flex items-center gap-2">
      <input id="searchCompany" type="text" placeholder="Search Tools..."
             class="px-3 py-2 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm w-64">
      <?php if (($_SESSION['role'] ?? '') === 'administrator'): ?>
      <button id="btnAddCompanyTool"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700">
        + Add Company Tool
      </button>
      <?php endif; ?>
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm text-left">
      <thead class="bg-gray-100 dark:bg-gray-800">
        <tr class="text-gray-700 dark:text-gray-200">
          <th class="px-4 py-2">Tools Name</th>
          <th class="px-4 py-2">Code Tools</th>
          <th class="px-4 py-2">Status Tools</th>
          <th class="px-4 py-2"></th>
        </tr>
      </thead>
      <tbody id="tblCompany" class="divide-y divide-gray-200 dark:divide-gray-700"></tbody>
    </table>
  </div>
</section>


<section id="tab-project" class="space-y-4 hidden">
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <div class="text-xl font-semibold">Pengajuan Project</div>
    <div class="flex items-center gap-2">
      <input id="searchProject" type="text" placeholder="Search Tools..."
             class="px-3 py-2 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm w-64">
      <button id="btnSubmitProject"
              class="px-4 py-2 bg-purple-600 text-white rounded-lg shadow hover:bg-purple-700 disabled:opacity-50"
              disabled>
        Ajukan Project
      </button>
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm text-left">
      <thead class="bg-gray-100 dark:bg-gray-800">
        <tr class="text-gray-700 dark:text-gray-200">
          <th class="px-4 py-2 w-10"><input type="checkbox" id="checkAllProject"></th>
          <th class="px-4 py-2">Tools Name</th>
          <th class="px-4 py-2">Code Tools</th>
          <th class="px-4 py-2">Foto</th>
        </tr>
      </thead>
      <tbody id="tblProject" class="divide-y divide-gray-200 dark:divide-gray-700"></tbody>
    </table>
  </div>
</section>


<section id="tab-personal" class="space-y-4 hidden">
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <div class="text-xl font-semibold">Personal Tools</div>
    <div class="flex items-center gap-2">
      <?php if (($_SESSION['role'] ?? '') === 'administrator'): ?>
      <button id="btnAddPersonalTool"
              class="px-4 py-2 bg-green-600 text-white rounded-lg shadow hover:bg-green-700">
        + Add Tools Personal
      </button>
      <?php endif; ?>
      <a href="dashboard.php?page=check-monthly-tools"
         class="px-4 py-2 bg-amber-600 text-white rounded-lg shadow hover:bg-amber-700">
        Monthly Check Tools
      </a>
    </div>
  </div>

  <div class="flex items-center gap-2">
    <input id="searchTechnician" type="text" placeholder="Search Teknisi..."
           class="px-3 py-2 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm w-64">
  </div>

  <div id="gridTechnicians" class="grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4"></div>
</section>


<div id="modalDetail" class="hidden fixed inset-0 z-[70] bg-black/50 items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-2xl p-6">
    <div class="flex justify-between items-center mb-3">
      <h3 class="font-semibold">Detail Tool</h3>
      <button data-close="modalDetail" class="text-gray-500">✕</button>
    </div>
    <div id="detailBody"></div>
  </div>
</div>

<div id="modalAddCompany" class="hidden fixed inset-0 z-[70] bg-black/50 items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-lg p-6">
    <div class="flex justify-between items-center mb-3">
      <h3 class="font-semibold">Tambah Company Tool</h3>
      <button data-close="modalAddCompany" class="text-gray-500">✕</button>
    </div>
    <form id="formAddCompany" class="space-y-3">
        <input name="quantity" type="number" placeholder="Jumlah" min="1" value="1" class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
        <input name="photo" type="file" accept="image/*" class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded">
        <input name="name" type="text" placeholder="Nama Alat" class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
        <input name="code" type="text" placeholder="Code Alat" class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
        <textarea name="notes" placeholder="Detail/Kondisi" class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded"></textarea>
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
      <h3 class="font-semibold">Tambah Personal Tool</h3>
      <button data-close="modalAddPersonal" class="text-gray-500">✕</button>
    </div>
    <form id="formAddPersonal" class="space-y-3">
      <input name="photo" type="file" accept="image/*" capture="environment" class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded">
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
  <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-3xl p-6">
    <div class="flex justify-between items-center mb-3">
      <h3 id="techToolsTitle" class="font-semibold">Tools Teknisi</h3>
      <button data-close="modalTechTools" class="text-gray-500">✕</button>
    </div>
    <div class="overflow-x-auto">
    <table class="min-w-full text-sm text-left">
      <thead class="bg-gray-100 dark:bg-gray-800">
        <tr class="text-gray-700 dark:text-gray-200">
          <th class="px-4 py-2">Name</th>
          <th class="px-4 py-2">Code</th>
          <th class="px-4 py-2">Detail</th>
          <th class="px-4 py-2"></th>
        </tr>
      </thead>
      <tbody id="tblTechTools" class="divide-y divide-gray-200 dark:divide-gray-700"></tbody>
    </table>
    </div>
  </div>
</div>


<div id="modalLoan" class="hidden fixed inset-0 z-[70] bg-black/50 items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
    <div class="flex justify-between items-center mb-3">
      <h3 class="font-semibold">Form Peminjaman</h3>
      <button data-close="modalLoan" class="text-gray-500">✕</button>
    </div>
    <form id="formLoan" class="space-y-3">
      <input type="hidden" name="tool_id" id="loanToolId">
      <input type="text" name="purpose" placeholder="Tujuan Peminjaman"
             class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
      <div class="flex gap-2">
        <input type="date" name="start_date" class="w-1/2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
        <input type="date" name="end_date" class="w-1/2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
      </div>
      <label class="block text-gray-900 dark:text-white">Upload Foto Bukti:</label>
      <input type="file" name="proof_photo" accept="image/*" capture="environment"
             class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded">
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
      <button data-close="modalReturn" class="text-gray-500">✕</button>
    </div>
    <form id="formReturn" class="space-y-3">
      <input type="hidden" name="tool_id" id="returnToolId">
      <div class="flex gap-2">
        <input type="datetime-local" name="return_datetime" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
      </div>
      <label class="block text-gray-900 dark:text-white">Upload Foto Bukti Pengembalian:</label>
      
      <div class="hidden md:flex flex-col gap-2">
        <input type="file" name="return_photo" id="return_photo_desktop" accept="image/*" class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded p-2 w-full"/>
        <p class="text-xs text-gray-500 dark:text-gray-400">Klik untuk memilih file dari komputer</p>
      </div>
      
      <div class="md:hidden flex flex-col gap-2">
        <div class="flex gap-2 mb-2">
          <button type="button" class="camera-btn px-3 py-2 bg-blue-500 text-white rounded" data-source="camera">Kamera</button>
          <button type="button" class="camera-btn px-3 py-2 bg-green-500 text-white rounded" data-source="gallery">Galeri</button>
          <button type="button" class="camera-btn px-3 py-2 bg-gray-500 text-white rounded" data-source="file">File</button>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">Pilih sumber foto untuk upload</p>
      </div>
      
      <div id="returnPhotoPreview" class="hidden">
        <img src="" class="w-32 h-32 object-cover rounded border border-gray-300 dark:border-gray-600">
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
      <h3 class="font-semibold">Form Handover</h3>
      <button data-close="modalHandover" class="text-gray-500">��</button>
    </div>
    
    
    
    <form id="formHandover" class="space-y-3">
      <input type="hidden" name="tool_id" id="handoverToolId">
      
      <select name="to_user_id" id="handoverToUser" class="hidden">
        <option value="">-- Pilih Teknisi Lain --</option>
      </select>
      
      <textarea name="purpose" placeholder="Tujuan Handover" class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required></textarea>
      <div class="flex justify-end gap-2">
        <button type="button" data-close="modalHandover" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded">Batal</button>
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded">Ajukan Handover</button>
      </div>
    </form>
  </div>
</div>


<div id="modalProject" class="hidden fixed inset-0 z-[70] bg-black/50 items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
    <div class="flex justify-between items-center mb-3">
      <h3 class="font-semibold">Form Pengajuan Project</h3>
      <button data-close="modalProject" class="text-gray-500">✕</button>
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
          <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Tanggal Mulai:</label>
          <input type="date" name="start_date" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
        </div>
        <div class="w-1/2">
          <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Tanggal Selesai:</label>
          <input type="date" name="end_date" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2 rounded" required>
        </div>
      </div>
      
      <div>
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">Upload Bukti Foto:</label>
        
        <div class="hidden md:flex flex-col gap-2">
          <input type="file" name="proof_photo" id="proof_photo_desktop" accept="image/*" class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded p-2 w-full" required/>
          <p class="text-xs text-gray-500 dark:text-gray-400">Klik untuk memilih file dari komputer</p>
        </div>
        
        <div class="md:hidden flex flex-col gap-2">
          <div class="flex gap-2 mb-2">
            <button type="button" class="camera-btn px-3 py-2 bg-blue-500 text-white rounded" data-source="camera">Kamera</button>
            <button type="button" class="camera-btn px-3 py-2 bg-green-500 text-white rounded" data-source="gallery">Galeri</button>
            <button type="button" class="camera-btn px-3 py-2 bg-gray-500 text-white rounded" data-source="file">File</button>
          </div>
          <p class="text-xs text-gray-500 dark:text-gray-400">Pilih sumber foto untuk upload</p>
        </div>
        
        <div id="projectPhotoPreview" class="hidden mt-2">
          <img src="" class="w-32 h-32 object-cover rounded border border-gray-300 dark:border-gray-600">
        </div>
      </div>
      
      <div class="flex justify-end gap-2">
        <button type="button" data-close="modalProject" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded">Batal</button>
        <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded">Ajukan Project</button>
      </div>
    </form>
  </div>
</div>


<script src="./public/assets/js/tools.js?v=<?= @filemtime(__DIR__ . '/../../public/assets/js/tools.js') ?: time() ?>"></script>