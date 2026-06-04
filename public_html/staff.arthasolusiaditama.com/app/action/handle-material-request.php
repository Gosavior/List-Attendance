<?php
error_reporting(0);
ob_start();
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/socket-notify.php';
require_once __DIR__ . '/../helpers/audit-log.php';

@date_default_timezone_set('Asia/Jakarta');


$stmt = $pdo->prepare("SELECT id, full_name, username, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);


$isDriverDivision = false;
try {
    $stmtDiv = $pdo->prepare("
        SELECT 1 FROM user_divisions ud
        JOIN divisions d ON d.id = ud.division_id
        WHERE ud.user_id = ? AND LOWER(d.name) = 'driver' AND d.is_active = 1
        LIMIT 1
    ");
    $stmtDiv->execute([$_SESSION['user_id']]);
    $isDriverDivision = (bool)$stmtDiv->fetch();
} catch (Exception $e) {}


function getSalesPdo() {
    require __DIR__ . '/../config/database_sales.php';
    return new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}


function salesNotifyPush($type = 'material_request', $message = '', $userIds = null) {
    $url = 'http://localhost:5000/api/notifications/push';
    $payload = ['type' => $type, 'message' => $message];
    if (is_array($userIds) && count($userIds) > 0) {
        $payload['userIds'] = array_map('intval', $userIds);
    }
    $json = json_encode($payload);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Content-Length: ' . strlen($json)],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}


function handlePhotoUpload($fileKey, $prefix, $requestId) {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        $err = isset($_FILES[$fileKey]) ? $_FILES[$fileKey]['error'] : 'no file';
        throw new Exception("Foto wajib diunggah. Error code: {$err}");
    }

    $file_tmp = $_FILES[$fileKey]['tmp_name'];
    $file_size = $_FILES[$fileKey]['size'];
    $file_name_orig = $_FILES[$fileKey]['name'];

    
    if ($file_size > 20 * 1024 * 1024) {
        throw new Exception("Foto terlalu besar. Maksimal 20MB.");
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file_tmp);
    finfo_close($finfo);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) {
        throw new Exception("Format foto tidak valid. Gunakan JPG, PNG, atau WEBP.");
    }

    $image_info = @getimagesize($file_tmp);
    if ($image_info === false) {
        throw new Exception("File bukan gambar valid. Ambil ulang foto.");
    }
    if ($image_info[0] < 320 || $image_info[1] < 240) {
        throw new Exception("Resolusi foto terlalu rendah. Minimum 320x240 pixels.");
    }

    $upload_dir = __DIR__ . '/../../storage/uploads/material_approvals/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0777, true);
        @chmod($upload_dir, 0777);
    }

    $ext = strtolower(pathinfo($file_name_orig, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        throw new Exception("Ekstensi file tidak valid.");
    }

    $file_name = $prefix . '_' . $requestId . '_' . time() . '.' . $ext;
    $real_upload_dir = realpath($upload_dir);
    if ($real_upload_dir === false) {
        throw new Exception("Upload directory tidak ditemukan.");
    }
    $file_path = $real_upload_dir . '/' . $file_name;

    if (!move_uploaded_file($file_tmp, $file_path)) {
        if (!copy($file_tmp, $file_path)) {
            throw new Exception("Gagal mengunggah foto.");
        }
    }

    // Compress image for faster loading
    require_once __DIR__ . '/../helpers/image-compress.php';
    compressUploadedImage($file_path, 1280, 1280, 75);

    return '/storage/uploads/material_approvals/' . $file_name;
}


function getRequestOwner($pdo, $requestId) {
    $stmt = $pdo->prepare("SELECT user_id FROM material_requests WHERE id = ?");
    $stmt->execute([$requestId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


function notifySalesUsers($salesPdo, $staffPdo, $projectId, $title, $message, $link = null) {
    
    $stmt = $salesPdo->prepare("SELECT assigned_to FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($project && $project['assigned_to']) {
        $stmt = $salesPdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, is_read, link, created_at)
            VALUES (?, ?, ?, 'info', 0, ?, NOW())
        ");
        $stmt->execute([$project['assigned_to'], $title, $message, $link]);
    }
    return $project ? intval($project['assigned_to']) : null;
}

try {
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_id']) && isset($_POST['material_names'])) {
        if ($user['role'] !== 'technician') {
            throw new Exception('Unauthorized');
        }

        $project_id = $_POST['project_id'];
        $material_names = $_POST['material_names'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        $units = $_POST['units'] ?? [];
        $notes = $_POST['notes'] ?? [];

        if (empty($project_id) || empty($material_names)) {
            throw new Exception('Project and at least one material is required');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO material_requests (user_id, project_id, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$_SESSION['user_id'], $project_id]);
        $request_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO material_request_items (request_id, material_name, quantity, unit, notes) VALUES (?, ?, ?, ?, ?)");
        foreach ($material_names as $index => $material_name) {
            if (empty($material_name)) continue;
            $unit = !empty($units[$index]) ? strtoupper(trim($units[$index])) : 'PCS';
            $stmt->execute([$request_id, $material_name, $quantities[$index] ?? 1, $unit, $notes[$index] ?? '']);
        }

        $pdo->commit();

        auditLog($pdo, 'material_submit', [
            'target_type' => 'material_request',
            'target_id' => (int)$request_id,
            'details' => ['project_id' => $project_id, 'items' => array_filter($material_names)]
        ]);

        
        try {
            $salesPdo = getSalesPdo();
            $itemList = implode(', ', array_filter($material_names));
            $salesUserId = notifySalesUsers($salesPdo, $pdo, $project_id,
                'Material Request Baru',
                "Technician {$user['full_name']} meminta material: {$itemList}",
                '/requestMaterial'
            );
            
            if ($salesUserId) {
                salesNotifyPush('material_request', "Material request baru dari {$user['full_name']}", [$salesUserId]);
            }
        } catch (Exception $e) {
            
        }

        header('Location: /dashboard.php?page=request-material');
        exit;

    }
    
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_accept') {
        if ($user['role'] !== 'administrator') {
            throw new Exception('Unauthorized');
        }

        $request_id = intval($_POST['request_id']);
        $stmt = $pdo->prepare("UPDATE material_requests SET status = 'admin_review' WHERE id = ? AND status = 'sales_approved'");
        $stmt->execute([$request_id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Request tidak ditemukan atau status tidak valid');
        }

        
        $owner = getRequestOwner($pdo, $request_id);
        if ($owner) {
            socketNotify([$owner['user_id']], 'material_request', 'Material request Anda sedang direview oleh Administrator');
        }

        header('Content-Type: application/json');
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Request accepted untuk review']);
        auditLog($pdo, 'material_accept_review', [
            'target_type' => 'material_request',
            'target_id' => $request_id,
            'target_user_id' => $owner['user_id'] ?? null,
        ]);
        exit;

    }
    
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_approval') {
        if ($user['role'] !== 'administrator') {
            throw new Exception('Unauthorized');
        }

        $request_id = intval($_POST['request_id']);
        $notes = $_POST['notes'] ?? '';
        $estimated_delivery = $_POST['estimated_delivery'] ?? null;
        if ($estimated_delivery) {
            $estimated_delivery = str_replace('T', ' ', $estimated_delivery) . ':00';
        }

        
        $item_prices = $_POST['item_prices'] ?? [];
        $item_sources = $_POST['item_sources'] ?? [];
        $item_warehouse_qty = $_POST['item_warehouse_qty'] ?? [];
        $item_store_name = $_POST['item_store_name'] ?? [];
        $item_store_address = $_POST['item_store_address'] ?? [];

        
        $hasWarehouseItem = false;
        foreach ($item_sources as $src) {
            if ($src === 'warehouse') { $hasWarehouseItem = true; break; }
        }

        $photo_path = null;
        if ($hasWarehouseItem) {
            $photo_path = handlePhotoUpload('material_photo', 'approval', $request_id);
        } elseif (isset($_FILES['material_photo']) && $_FILES['material_photo']['error'] === UPLOAD_ERR_OK) {
            $photo_path = handlePhotoUpload('material_photo', 'approval', $request_id);
        }

        $pdo->beginTransaction();

        
        $totalPrice = 0;
        foreach ($item_prices as $itemId => $price) {
            $itemId = intval($itemId);
            $price = floatval($price);
            $source = (isset($item_sources[$itemId]) && $item_sources[$itemId] === 'warehouse') ? 'warehouse' : 'purchase';

            
            $stmt2 = $pdo->prepare("SELECT quantity FROM material_request_items WHERE id = ? AND request_id = ?");
            $stmt2->execute([$itemId, $request_id]);
            $itemRow = $stmt2->fetch(PDO::FETCH_ASSOC);
            $needed = $itemRow ? intval($itemRow['quantity']) : 0;

            
            $qtyFromWarehouse = null;
            $qtyToPurchase = null;
            $storeName = null;

            if ($source === 'warehouse') {
                $available = isset($item_warehouse_qty[$itemId]) ? intval($item_warehouse_qty[$itemId]) : $needed;
                $qtyFromWarehouse = min($needed, $available);
                $qtyToPurchase = max(0, $needed - $available);
                
                if ($qtyToPurchase > 0 && isset($item_store_name[$itemId])) {
                    $storeName = trim($item_store_name[$itemId]);
                }
                
                if ($qtyFromWarehouse === 0) {
                    $source = 'purchase';
                    $qtyToPurchase = $needed;
                    if (isset($item_store_name[$itemId])) {
                        $storeName = trim($item_store_name[$itemId]);
                    }
                }
            } else {
                
                $qtyFromWarehouse = 0;
                $qtyToPurchase = $needed;
                if (isset($item_store_name[$itemId])) {
                    $storeName = trim($item_store_name[$itemId]);
                }
            }

            $storeAddress = (isset($item_store_address[$itemId]) && trim($item_store_address[$itemId]) !== '') ? trim($item_store_address[$itemId]) : null;
            $stmt = $pdo->prepare("UPDATE material_request_items SET price = ?, source_type = ?, qty_from_warehouse = ?, qty_to_purchase = ?, store_name = ?, store_address = ? WHERE id = ? AND request_id = ?");
            $stmt->execute([$price, $source, $qtyFromWarehouse, $qtyToPurchase, $storeName ?: null, $storeAddress, $itemId, $request_id]);

            if ($itemRow) $totalPrice += $price * $needed;
        }

        
        $stmt = $pdo->prepare("SELECT id FROM material_request_approvals WHERE request_id = ?");
        $stmt->execute([$request_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $stmt = $pdo->prepare("
                UPDATE material_request_approvals
                SET notes = ?, estimated_delivery = ?, total_price = ?, photo_path = ?
                WHERE request_id = ?
            ");
            $stmt->execute([$notes, $estimated_delivery, $totalPrice, $photo_path, $request_id]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO material_request_approvals
                (request_id, approved_by, photo_path, notes, estimated_delivery, total_price)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$request_id, $_SESSION['user_id'], $photo_path, $notes, $estimated_delivery, $totalPrice]);
        }

        
        $delivery_method = isset($_POST['delivery_method']) && $_POST['delivery_method'] === 'technician_pickup' ? 'technician_pickup' : 'driver';

        
        $stmt = $pdo->prepare("UPDATE material_requests SET status = 'admin_approved', admin_approved_by = ?, admin_approved_at = NOW(), delivery_method = ? WHERE id = ? AND status IN ('sales_approved','admin_review')");
        $stmt->execute([$_SESSION['user_id'], $delivery_method, $request_id]);

        $pdo->commit();

        
        try {
            $salesPdo = getSalesPdo();

            
            $stmt = $pdo->prepare("SELECT project_id FROM material_requests WHERE id = ?");
            $stmt->execute([$request_id]);
            $reqData = $stmt->fetch(PDO::FETCH_ASSOC);
            $project_id = $reqData['project_id'];

            
            $stmt = $pdo->prepare("SELECT * FROM material_request_items WHERE request_id = ?");
            $stmt->execute([$request_id]);
            $mrItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            
            $stmt = $salesPdo->prepare("SELECT id FROM rab WHERE project_id = ? AND rab_type = 'nyata' ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$project_id]);
            $rab = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rab) {
                $stmt = $salesPdo->prepare("SELECT ao_number FROM projects WHERE id = ?");
                $stmt->execute([$project_id]);
                $proj = $stmt->fetch(PDO::FETCH_ASSOC);
                $rabNumber = ($proj && !empty($proj['ao_number'])) ? $proj['ao_number'] : ('RAB-MR-' . $project_id);

                $stmt = $salesPdo->prepare("INSERT INTO rab (project_id, rab_number, status, rab_type, created_by) VALUES (?, ?, 'draft', 'nyata', ?)");
                $stmt->execute([$project_id, $rabNumber, $_SESSION['user_id']]);
                $rabId = $salesPdo->lastInsertId();

                $salesPdo->prepare("UPDATE projects SET rab_id = ? WHERE id = ? AND rab_id IS NULL")->execute([$rabId, $project_id]);
            } else {
                $rabId = $rab['id'];
            }

            
            $stmt = $salesPdo->prepare("SELECT section, COALESCE(MAX(item_order), -1) as max_order FROM rab_items WHERE rab_id = ? GROUP BY section");
            $stmt->execute([$rabId]);
            $maxOrders = ['A' => -1, 'B' => -1, 'C' => -1, 'D' => -1];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $maxOrders[$row['section']] = intval($row['max_order']);
            }

            
            foreach ($mrItems as $item) {
                $price = floatval($item['price'] ?? 0);
                $source = $item['source_type'] ?? 'purchase';
                $qty = intval($item['quantity']);
                $name = $item['material_name'];
                $fromWarehouse = isset($item['qty_from_warehouse']) ? intval($item['qty_from_warehouse']) : 0;
                $toPurchase = isset($item['qty_to_purchase']) ? intval($item['qty_to_purchase']) : 0;
                $storeName = $item['store_name'] ?? null;

                
                if ($fromWarehouse === 0 && $toPurchase === 0) {
                    if ($source === 'warehouse') {
                        $fromWarehouse = $qty;
                    } else {
                        $toPurchase = $qty;
                    }
                }

                
                if ($fromWarehouse > 0) {
                    $order = ++$maxOrders['B'];
                    $stmt = $salesPdo->prepare("INSERT INTO rab_items (rab_id, section, item_order, item_name, qty_needed, qty_available, unit, price) VALUES (?, 'B', ?, ?, ?, ?, 'pcs', ?)");
                    $stmt->execute([$rabId, $order, $name, $qty, $fromWarehouse, $price]);
                }

                
                if ($toPurchase > 0) {
                    $order = ++$maxOrders['A'];
                    $storeInfo = $storeName ? ($name . ' (' . $storeName . ')') : $name;
                    $stmt = $salesPdo->prepare("INSERT INTO rab_items (rab_id, section, item_order, item_name, qty, unit, price, store_name) VALUES (?, 'A', ?, ?, ?, 'pcs', ?, ?)");
                    $stmt->execute([$rabId, $order, $storeInfo, $toPurchase, $price, $storeName]);
                }
            }

            
            $stmt = $salesPdo->prepare("SELECT * FROM rab_items WHERE rab_id = ?");
            $stmt->execute([$rabId]);
            $allRabItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $bNames = [];
            foreach ($allRabItems as $ri) {
                if ($ri['section'] === 'B') $bNames[] = strtolower(trim($ri['item_name']));
            }

            $tA = $tBW = $tBB = $tC = $tD = 0;
            foreach ($allRabItems as $ri) {
                $p = floatval($ri['price']);
                switch ($ri['section']) {
                    case 'A':
                        if (!in_array(strtolower(trim($ri['item_name'])), $bNames)) $tA += floatval($ri['qty']) * $p;
                        break;
                    case 'B':
                        $needed = floatval($ri['qty_needed']);
                        $avail = floatval($ri['qty_available']);
                        $tBW += min($needed, $avail) * $p;
                        $tBB += max(0, $needed - $avail) * $p;
                        break;
                    case 'C': $tC += floatval($ri['qty']) * $p; break;
                    case 'D': $tD += floatval($ri['qty']) * $p; break;
                }
            }
            
            // Calculate labor cost total
            $tLabor = 0;
            $stmtLabor = $salesPdo->prepare("SELECT SUM(total_cost) as labor_total FROM rab_labor_cost_items WHERE rab_id = ?");
            $stmtLabor->execute([$rabId]);
            $laborResult = $stmtLabor->fetch(PDO::FETCH_ASSOC);
            if ($laborResult && $laborResult['labor_total']) {
                $tLabor = floatval($laborResult['labor_total']);
            }
            
            $gt = $tA + $tBB + $tC + $tD + $tLabor;
            $salesPdo->prepare("UPDATE rab SET total_section_a=?, total_section_b_warehouse=?, total_section_b_buy=?, total_section_c=?, total_section_d=?, total_labor_cost=?, grand_total=?, updated_at=NOW() WHERE id=?")->execute([$tA, $tBW, $tBB, $tC, $tD, $tLabor, $gt, $rabId]);

        } catch (Exception $rabErr) {
            error_log("RAB Sync Error: " . $rabErr->getMessage());
            
        }

        
        $owner = getRequestOwner($pdo, $request_id);
        if ($owner) {
            if ($delivery_method === 'technician_pickup') {
                socketNotify([$owner['user_id']], 'material_request', 'Material Anda sudah siap. Silakan ambil langsung.');
            } else {
                socketNotify([$owner['user_id']], 'material_request', 'Material Anda telah di-approve oleh Admin. Menunggu Driver.');
            }
        }

        
        if ($delivery_method === 'driver') {
            $stmtDrivers = $pdo->prepare("
                SELECT DISTINCT ud.user_id FROM user_divisions ud
                JOIN divisions d ON d.id = ud.division_id
                JOIN users u ON u.id = ud.user_id
                WHERE LOWER(d.name) = 'driver' AND d.is_active = 1 AND u.is_active = 1
            ");
            $stmtDrivers->execute();
            $drivers = $stmtDrivers->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($drivers)) {
                socketNotify($drivers, 'material_request', 'Ada material baru yang perlu di-pickup dan dikirim.');
            }
        }

        
        try {
            $salesPdo = getSalesPdo();
            $stmtReq = $pdo->prepare("SELECT project_id FROM material_requests WHERE id = ?");
            $stmtReq->execute([$request_id]);
            $reqRow = $stmtReq->fetch(PDO::FETCH_ASSOC);
            if ($reqRow) {
                if ($delivery_method === 'technician_pickup') {
                    notifySalesUsers($salesPdo, $pdo, $reqRow['project_id'],
                        'Material Disediakan oleh Admin',
                        "Admin telah menyediakan material untuk request #{$request_id}. Technician akan ambil sendiri.",
                        '/requestMaterial'
                    );
                    salesNotifyPush('material_request', 'Admin telah menyediakan material (ambil sendiri)');
                } else {
                    notifySalesUsers($salesPdo, $pdo, $reqRow['project_id'],
                        'Material Disediakan oleh Admin',
                        "Admin telah menyediakan material untuk request #{$request_id}. Menunggu driver pickup.",
                        '/requestMaterial'
                    );
                    salesNotifyPush('material_request', 'Admin telah menyediakan material');
                }
            }
        } catch (Exception $e) {   }

        $successMsg = $delivery_method === 'technician_pickup'
            ? 'Approval berhasil! Technician akan mengambil material sendiri dan otomatis masuk ke RAB.'
            : 'Approval berhasil! Material akan dikirim oleh Driver dan otomatis masuk ke RAB.';

        header('Content-Type: application/json');
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => $successMsg]);
        auditLog($pdo, 'material_approve', [
            'target_type' => 'material_request',
            'target_id' => $request_id,
            'details' => ['delivery_method' => $delivery_method ?? '']
        ]);
        exit;

    }
    
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject') {
        if (!in_array($user['role'], ['administrator'])) {
            throw new Exception('Unauthorized');
        }

        $request_id = intval($_POST['request_id']);
        $reason = $_POST['reason'] ?? '';

        $stmt = $pdo->prepare("UPDATE material_requests SET status = 'rejected', rejection_reason = ?, rejected_by = ?, rejected_at = NOW() WHERE id = ?");
        $stmt->execute([$reason, $_SESSION['user_id'], $request_id]);

        
        $owner = getRequestOwner($pdo, $request_id);
        if ($owner) {
            $msg = 'Pengajuan material Anda ditolak';
            if ($reason) $msg .= ': ' . $reason;
            socketNotify([$owner['user_id']], 'material_request', $msg);
        }

        header('Content-Type: application/json');
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Request rejected']);
        auditLog($pdo, 'material_reject', [
            'target_type' => 'material_request',
            'target_id' => $request_id,
            'target_user_id' => $owner['user_id'] ?? null,
            'details' => ['reason' => $reason]
        ]);
        exit;

    }
    
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'driver_pickup') {
        if (!$isDriverDivision) {
            throw new Exception('Unauthorized: hanya divisi Driver');
        }

        $request_id = intval($_POST['request_id']);
        $photo_path = handlePhotoUpload('photo', 'pickup', $request_id);

        
        $eta_minutes = isset($_POST['delivery_eta_minutes']) ? intval($_POST['delivery_eta_minutes']) : 0;
        $delivery_eta = null;
        if ($eta_minutes > 0) {
            $delivery_eta = date('Y-m-d H:i:s', strtotime("+{$eta_minutes} minutes"));
        }

        $stmt = $pdo->prepare("
            UPDATE material_requests 
            SET status = 'driver_pickup', driver_pickup_by = ?, driver_pickup_photo = ?, driver_pickup_at = NOW(), driver_delivery_eta = ?
            WHERE id = ? AND status = 'admin_approved'
        ");
        $stmt->execute([$_SESSION['user_id'], $photo_path, $delivery_eta, $request_id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Request tidak ditemukan atau sudah di-pickup');
        }

        
        $owner = getRequestOwner($pdo, $request_id);
        $notifyIds = [];
        if ($owner) $notifyIds[] = $owner['user_id'];

        $stmtAdmins = $pdo->prepare("SELECT id FROM users WHERE role = 'administrator' AND is_active = 1");
        $stmtAdmins->execute();
        $admins = $stmtAdmins->fetchAll(PDO::FETCH_COLUMN);
        $notifyIds = array_merge($notifyIds, $admins);

        if (!empty($notifyIds)) {
            socketNotify(array_unique($notifyIds), 'material_request', "Driver {$user['full_name']} sudah pickup material. Sedang dalam perjalanan.");
        }

        
        try {
            $stmtReq = $pdo->prepare("SELECT project_id FROM material_requests WHERE id = ?");
            $stmtReq->execute([$request_id]);
            $reqRow = $stmtReq->fetch(PDO::FETCH_ASSOC);
            if ($reqRow) {
                $salesPdo = getSalesPdo();
                notifySalesUsers($salesPdo, $pdo, $reqRow['project_id'],
                    'Driver Pickup Material',
                    "Driver {$user['full_name']} sudah pickup material. Sedang dalam perjalanan.",
                    '/requestMaterial'
                );
                salesNotifyPush('material_request', "Driver sedang mengantar material");
            }
        } catch (Exception $e) {   }

        header('Content-Type: application/json');
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Pickup dikonfirmasi. Material sedang diantar.']);
        exit;

    }
    
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'driver_deliver') {
        if (!$isDriverDivision) {
            throw new Exception('Unauthorized: hanya divisi Driver');
        }

        $request_id = intval($_POST['request_id']);
        $photo_path = handlePhotoUpload('photo', 'delivered', $request_id);

        $stmt = $pdo->prepare("
            UPDATE material_requests 
            SET status = 'delivered', driver_delivered_photo = ?, driver_delivered_at = NOW()
            WHERE id = ? AND status = 'driver_pickup'
        ");
        $stmt->execute([$photo_path, $request_id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Request tidak ditemukan atau status tidak valid');
        }

        
        $owner = getRequestOwner($pdo, $request_id);
        $notifyIds = [];
        if ($owner) $notifyIds[] = $owner['user_id'];

        $stmtAdmins = $pdo->prepare("SELECT id FROM users WHERE role = 'administrator' AND is_active = 1");
        $stmtAdmins->execute();
        $admins = $stmtAdmins->fetchAll(PDO::FETCH_COLUMN);
        $notifyIds = array_merge($notifyIds, $admins);

        if (!empty($notifyIds)) {
            socketNotify(array_unique($notifyIds), 'material_request', "Material telah diterima oleh Technician. Pengiriman selesai.");
        }

        
        try {
            $stmtReq = $pdo->prepare("SELECT project_id FROM material_requests WHERE id = ?");
            $stmtReq->execute([$request_id]);
            $reqRow = $stmtReq->fetch(PDO::FETCH_ASSOC);
            if ($reqRow) {
                $salesPdo = getSalesPdo();
                notifySalesUsers($salesPdo, $pdo, $reqRow['project_id'],
                    'Material Delivered',
                    "Material telah diantar ke lokasi. Menunggu konfirmasi technician.",
                    '/requestMaterial'
                );
                salesNotifyPush('material_request', "Material telah diantar");
            }
        } catch (Exception $e) {   }

        header('Content-Type: application/json');
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Material berhasil dikirim!']);
        exit;
    }
    
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'technician_self_pickup') {
        if ($user['role'] !== 'technician') {
            throw new Exception('Unauthorized');
        }

        $request_id = intval($_POST['request_id']);

        
        $stmt = $pdo->prepare("SELECT user_id, delivery_method FROM material_requests WHERE id = ? AND status = 'admin_approved'");
        $stmt->execute([$request_id]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$req || $req['user_id'] != $_SESSION['user_id']) {
            throw new Exception('Request tidak ditemukan atau bukan milik Anda');
        }
        if ($req['delivery_method'] !== 'technician_pickup') {
            throw new Exception('Request ini akan diantar oleh driver');
        }

        $stmt = $pdo->prepare("UPDATE material_requests SET status = 'completed', completed_by = ?, completed_at = NOW() WHERE id = ? AND status = 'admin_approved'");
        $stmt->execute([$_SESSION['user_id'], $request_id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Gagal mengkonfirmasi');
        }

        
        $stmtAdmins = $pdo->prepare("SELECT id FROM users WHERE role = 'administrator' AND is_active = 1");
        $stmtAdmins->execute();
        $notifyIds = $stmtAdmins->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($notifyIds)) {
            socketNotify(array_unique($notifyIds), 'material_request', "Technician {$user['full_name']} telah mengambil material sendiri. Request selesai.");
        }

        
        try {
            $stmtReq = $pdo->prepare("SELECT project_id FROM material_requests WHERE id = ?");
            $stmtReq->execute([$request_id]);
            $reqRow = $stmtReq->fetch(PDO::FETCH_ASSOC);
            if ($reqRow) {
                $salesPdo = getSalesPdo();
                notifySalesUsers($salesPdo, $pdo, $reqRow['project_id'],
                    'Material Diambil Sendiri',
                    "Technician {$user['full_name']} telah mengambil material sendiri. Request selesai.",
                    '/requestMaterial'
                );
                salesNotifyPush('material_request', "Material diambil sendiri oleh technician");
            }
        } catch (Exception $e) {   }

        header('Content-Type: application/json');
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Material dikonfirmasi sudah diambil! Request selesai.']);
        exit;
    }
    
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'technician_partial_pickup') {
        if (!in_array($user['role'], ['technician', 'staff'])) {
            throw new Exception('Unauthorized');
        }

        $request_id = intval($_POST['request_id'] ?? 0);
        $item_ids = $_POST['item_ids'] ?? [];
        $item_qtys = $_POST['item_qtys'] ?? [];

        if ($request_id <= 0 || !is_array($item_ids) || !is_array($item_qtys) || count($item_ids) === 0) {
            throw new Exception('Data pengambilan tidak valid');
        }
        if (count($item_ids) !== count($item_qtys)) {
            throw new Exception('Data item tidak sinkron');
        }

        $stmt = $pdo->prepare("SELECT user_id, delivery_method, status, project_id FROM material_requests WHERE id = ?");
        $stmt->execute([$request_id]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$req || (int)$req['user_id'] !== (int)$_SESSION['user_id']) {
            throw new Exception('Request tidak ditemukan atau bukan milik Anda');
        }
        if (($req['delivery_method'] ?? '') !== 'technician_pickup') {
            throw new Exception('Request ini tidak menggunakan mode ambil sendiri');
        }
        if (($req['status'] ?? '') !== 'admin_approved') {
            throw new Exception('Status request belum siap untuk diambil');
        }

        // Simpan log pengambilan parsial tanpa mengubah alur status utama request.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS material_request_partial_pickups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                request_id INT NOT NULL,
                user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_request_id (request_id),
                INDEX idx_user_id (user_id),
                CONSTRAINT fk_mrpp_request FOREIGN KEY (request_id) REFERENCES material_requests(id) ON DELETE CASCADE,
                CONSTRAINT fk_mrpp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS material_request_partial_pickup_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                partial_pickup_id INT NOT NULL,
                request_item_id INT NOT NULL,
                material_name VARCHAR(255) NOT NULL,
                quantity INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_partial_pickup_id (partial_pickup_id),
                CONSTRAINT fk_mrppi_partial FOREIGN KEY (partial_pickup_id) REFERENCES material_request_partial_pickups(id) ON DELETE CASCADE,
                CONSTRAINT fk_mrppi_request_item FOREIGN KEY (request_item_id) REFERENCES material_request_items(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $stmtItems = $pdo->prepare("SELECT id, material_name, quantity FROM material_request_items WHERE request_id = ?");
        $stmtItems->execute([$request_id]);
        $requestItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        $itemMap = [];
        foreach ($requestItems as $ri) {
            $itemMap[(int)$ri['id']] = $ri;
        }

        $validated = [];
        for ($i = 0; $i < count($item_ids); $i++) {
            $itemId = intval($item_ids[$i]);
            $qty = intval($item_qtys[$i]);
            if ($itemId <= 0 || $qty <= 0) continue;
            if (!isset($itemMap[$itemId])) continue;
            $maxQty = (int)$itemMap[$itemId]['quantity'];
            if ($maxQty <= 0) continue;
            $qty = min($qty, $maxQty);
            $validated[] = [
                'item_id' => $itemId,
                'material_name' => $itemMap[$itemId]['material_name'],
                'qty' => $qty,
            ];
        }
        if (empty($validated)) {
            throw new Exception('Tidak ada item valid yang dipilih');
        }

        $pdo->beginTransaction();
        $stmtHead = $pdo->prepare("INSERT INTO material_request_partial_pickups (request_id, user_id) VALUES (?, ?)");
        $stmtHead->execute([$request_id, $_SESSION['user_id']]);
        $partialId = (int)$pdo->lastInsertId();

        $stmtDetail = $pdo->prepare("
            INSERT INTO material_request_partial_pickup_items (partial_pickup_id, request_item_id, material_name, quantity)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($validated as $row) {
            $stmtDetail->execute([$partialId, $row['item_id'], $row['material_name'], $row['qty']]);
        }
        $pdo->commit();

        $takenSummary = implode(', ', array_map(function ($r) {
            return $r['material_name'] . ' x' . $r['qty'];
        }, $validated));

        $stmtAdmins = $pdo->prepare("SELECT id FROM users WHERE role = 'administrator' AND is_active = 1");
        $stmtAdmins->execute();
        $adminIds = $stmtAdmins->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($adminIds)) {
            socketNotify(array_unique($adminIds), 'material_request', "Technician {$user['full_name']} ambil parsial: {$takenSummary}");
        }

        try {
            $salesPdo = getSalesPdo();
            notifySalesUsers(
                $salesPdo,
                $pdo,
                (int)$req['project_id'],
                'Pengambilan Material Parsial',
                "Technician {$user['full_name']} mengambil sebagian material: {$takenSummary}",
                '/requestMaterial'
            );
            salesNotifyPush('material_request', "Pengambilan parsial oleh {$user['full_name']}");
        } catch (Exception $e) { }

        auditLog($pdo, 'material_partial_pickup', [
            'target_type' => 'material_request',
            'target_id' => $request_id,
            'details' => ['items' => $validated],
        ]);

        header('Content-Type: application/json');
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Pengambilan parsial berhasil dicatat.']);
        exit;
    }
    
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'technician_confirm') {
        if ($user['role'] !== 'technician') {
            throw new Exception('Unauthorized');
        }

        $request_id = intval($_POST['request_id']);

        
        $stmt = $pdo->prepare("SELECT user_id FROM material_requests WHERE id = ? AND status = 'delivered'");
        $stmt->execute([$request_id]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$req || $req['user_id'] != $_SESSION['user_id']) {
            throw new Exception('Request tidak ditemukan atau bukan milik Anda');
        }

        $stmt = $pdo->prepare("UPDATE material_requests SET status = 'completed', completed_by = ?, completed_at = NOW() WHERE id = ? AND status = 'delivered'");
        $stmt->execute([$_SESSION['user_id'], $request_id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Gagal mengkonfirmasi');
        }

        
        $notifyIds = [];
        $stmtAdmins = $pdo->prepare("SELECT id FROM users WHERE role = 'administrator' AND is_active = 1");
        $stmtAdmins->execute();
        $notifyIds = $stmtAdmins->fetchAll(PDO::FETCH_COLUMN);

        
        $stmtDriver = $pdo->prepare("SELECT driver_pickup_by FROM material_requests WHERE id = ?");
        $stmtDriver->execute([$request_id]);
        $driverRow = $stmtDriver->fetch(PDO::FETCH_ASSOC);
        if ($driverRow && $driverRow['driver_pickup_by']) {
            $notifyIds[] = $driverRow['driver_pickup_by'];
        }

        if (!empty($notifyIds)) {
            socketNotify(array_unique($notifyIds), 'material_request', "Technician {$user['full_name']} mengkonfirmasi material sudah diterima. Request selesai.");
        }

        
        try {
            $stmtReq = $pdo->prepare("SELECT project_id FROM material_requests WHERE id = ?");
            $stmtReq->execute([$request_id]);
            $reqRow = $stmtReq->fetch(PDO::FETCH_ASSOC);
            if ($reqRow) {
                $salesPdo = getSalesPdo();
                notifySalesUsers($salesPdo, $pdo, $reqRow['project_id'],
                    'Material Request Selesai',
                    "Technician {$user['full_name']} telah mengkonfirmasi material diterima. Request selesai.",
                    '/requestMaterial'
                );
                salesNotifyPush('material_request', "Material request selesai");
            }
        } catch (Exception $e) {   }

        header('Content-Type: application/json');
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Barang dikonfirmasi sudah sampai! Request selesai.']);
        exit;
    }

    
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
        $jsonInput = json_decode(file_get_contents('php://input'), true);

        if ($jsonInput && ($jsonInput['action'] ?? '') === 'get_rab_items') {
            $projectId = intval($jsonInput['project_id'] ?? 0);
            if (!$projectId) throw new Exception('Project ID required');

            $salesPdo = getSalesPdo();

            
            $stmt = $salesPdo->prepare("SELECT id FROM rab WHERE project_id = ? AND rab_type = 'nyata' ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$projectId]);
            $rab = $stmt->fetch(PDO::FETCH_ASSOC);

            $rabItems = [];
            if ($rab) {
                
                $stmt = $salesPdo->prepare("
                    SELECT id, section, item_name, 
                           COALESCE(qty, 0) as qty, 
                           COALESCE(qty_needed, 0) as qty_needed, 
                           COALESCE(qty_available, 0) as qty_available, 
                           unit, price
                    FROM rab_items 
                    WHERE rab_id = ? AND section IN ('A', 'B')
                    ORDER BY section, item_order
                ");
                $stmt->execute([$rab['id']]);
                $allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

                
                $retStmt = $pdo->prepare("
                    SELECT mrti.material_name, SUM(mrti.quantity) as returned_qty
                    FROM material_return_items mrti
                    JOIN material_returns mrt ON mrti.return_id = mrt.id
                    WHERE mrt.project_id = ? AND mrt.status != 'rejected'
                    GROUP BY mrti.material_name
                ");
                $retStmt->execute([$projectId]);
                $returnedMap = [];
                while ($r = $retStmt->fetch(PDO::FETCH_ASSOC)) {
                    $returnedMap[strtolower(trim($r['material_name']))] = (int)$r['returned_qty'];
                }

                foreach ($allItems as $item) {
                    $currentQty = $item['section'] === 'A' ? (int)$item['qty'] : (int)$item['qty_needed'];
                    $alreadyReturned = $returnedMap[strtolower(trim($item['item_name']))] ?? 0;
                    
                    if ($currentQty > 0) {
                        $rabItems[] = [
                            'rab_item_id' => (int)$item['id'],
                            'item_name' => $item['item_name'],
                            'section' => $item['section'],
                            'current_qty' => $currentQty,
                            'already_returned' => $alreadyReturned,
                            'max_returnable' => $currentQty,
                            'unit' => $item['unit'] ?: 'pcs',
                        ];
                    }
                }
            }

            header('Content-Type: application/json');
            ob_end_clean();
            echo json_encode(['success' => true, 'data' => $rabItems]);
            exit;
        }

    
        if ($jsonInput && ($jsonInput['action'] ?? '') === 'submit_return') {
            $projectId = intval($jsonInput['project_id'] ?? 0);
            $items = $jsonInput['items'] ?? [];
            $note = trim($jsonInput['note'] ?? '');

            if (!$projectId || empty($items)) {
                throw new Exception('Project dan minimal 1 item wajib diisi');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO material_returns (user_id, project_id, note, status) VALUES (?, ?, ?, 'pending')");
            $stmt->execute([$_SESSION['user_id'], $projectId, $note ?: null]);
            $returnId = $pdo->lastInsertId();

            $insertItem = $pdo->prepare("INSERT INTO material_return_items (return_id, material_name, quantity, notes) VALUES (?, ?, ?, ?)");
            $validItems = [];
            foreach ($items as $item) {
                $matName = trim($item['material_name'] ?? '');
                $qty = intval($item['quantity'] ?? 0);
                if ($matName && $qty > 0) {
                    $insertItem->execute([$returnId, $matName, $qty, $item['notes'] ?? null]);
                    $validItems[] = "{$matName} (x{$qty})";
                }
            }

            if (empty($validItems)) {
                $pdo->rollBack();
                throw new Exception('Minimal 1 item valid');
            }

            $pdo->commit();

            
            try {
                $salesPdo = getSalesPdo();
                $itemList = implode(', ', $validItems);
                $salesUserId = notifySalesUsers($salesPdo, $pdo, $projectId,
                    'Permintaan Pengembalian Material',
                    "Teknisi {$user['full_name']} mengajukan pengembalian material: {$itemList}",
                    '/requestMaterial'
                );
                if ($salesUserId) {
                    salesNotifyPush('material_return', "Pengembalian material baru dari {$user['full_name']}", [$salesUserId]);
                }
            } catch (Exception $e) {   }

            header('Content-Type: application/json');
            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Permintaan pengembalian berhasil dikirim']);
            exit;
        }

        
        if ($jsonInput && ($jsonInput['action'] ?? '') === 'admin_receive_return') {
            if ($user['role'] !== 'administrator') {
                throw new Exception('Hanya administrator yang bisa menerima barang');
            }

            $returnId = intval($jsonInput['return_id'] ?? 0);
            if (!$returnId) throw new Exception('Return ID tidak valid');

            
            $retStmt = $pdo->prepare("SELECT * FROM material_returns WHERE id = ? AND status = 'sales_approved'");
            $retStmt->execute([$returnId]);
            $ret = $retStmt->fetch(PDO::FETCH_ASSOC);
            if (!$ret) throw new Exception('Pengembalian tidak ditemukan atau belum disetujui sales');

            
            $itemStmt = $pdo->prepare("SELECT * FROM material_return_items WHERE return_id = ?");
            $itemStmt->execute([$returnId]);
            $returnItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

            $pdo->beginTransaction();

            
            $pdo->prepare("UPDATE material_returns SET status = 'admin_received', admin_received_by = ?, admin_received_at = NOW() WHERE id = ?")
                ->execute([$_SESSION['user_id'], $returnId]);

            $pdo->commit();

            
            try {
                $salesPdo = getSalesPdo();
                $rabStmt = $salesPdo->prepare("SELECT id, rab_data FROM projects WHERE id = ?");
                $rabStmt->execute([$ret['project_id']]);
                $project = $rabStmt->fetch(PDO::FETCH_ASSOC);

                if ($project && !empty($project['rab_data'])) {
                    $rabData = json_decode($project['rab_data'], true);
                    if ($rabData && isset($rabData['sections'])) {
                        foreach ($returnItems as $retItem) {
                            $itemName = strtolower(trim($retItem['material_name']));
                            $retQty = intval($retItem['quantity']);

                            foreach ($rabData['sections'] as &$section) {
                                if (!isset($section['items'])) continue;
                                foreach ($section['items'] as &$rabItem) {
                                    if (strtolower(trim($rabItem['description'] ?? '')) === $itemName) {
                                        $currentQty = floatval($rabItem['qty'] ?? 0);
                                        $rabItem['qty'] = max(0, $currentQty - $retQty);
                                        $rabItem['totalPrice'] = $rabItem['qty'] * floatval($rabItem['unitPrice'] ?? 0);
                                    }
                                }
                                unset($rabItem);
                                
                                $sectionTotal = 0;
                                foreach ($section['items'] as $si) {
                                    $sectionTotal += floatval($si['totalPrice'] ?? 0);
                                }
                                $section['totalPrice'] = $sectionTotal;
                            }
                            unset($section);
                        }

                        
                        $grandTotal = 0;
                        foreach ($rabData['sections'] as $sec) {
                            $grandTotal += floatval($sec['totalPrice'] ?? 0);
                        }
                        $rabData['grandTotal'] = $grandTotal;

                        $salesPdo->prepare("UPDATE projects SET rab_data = ? WHERE id = ?")
                            ->execute([json_encode($rabData), $ret['project_id']]);
                    }
                }
            } catch (Exception $e) {
                error_log("RAB update error: " . $e->getMessage());
            }

            
            try {
                $techStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                $techStmt->execute([$ret['user_id']]);
                $techName = $techStmt->fetchColumn();

                $salesPdo2 = getSalesPdo();
                $projStmt = $salesPdo2->prepare("SELECT project_name FROM projects WHERE id = ?");
                $projStmt->execute([$ret['project_id']]);
                $projName = $projStmt->fetchColumn();

                
                $http = new \stdClass();
                try {
                    $payload = json_encode([
                        'type' => 'material_return',
                        'message' => "Pengembalian material Anda untuk project \"{$projName}\" telah diterima admin.",
                        'userIds' => [$ret['user_id']]
                    ]);
                    $ch = curl_init('http://localhost:3001/notify');
                    curl_setopt_array($ch, [
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => $payload,
                        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 3
                    ]);
                    curl_exec($ch);
                    curl_close($ch);
                } catch (Exception $e) {   }
            } catch (Exception $e) {   }

            header('Content-Type: application/json');
            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Barang diterima & RAB berhasil diupdate!']);
            exit;
        }
    }

    
    header('Content-Type: application/json');
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid action']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("handle-material-request ERROR: " . $e->getMessage() . " | Action: " . ($_POST['action'] ?? 'submit') . " | User: " . ($user['username'] ?? 'unknown'));
    ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
