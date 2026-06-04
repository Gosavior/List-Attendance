<?php
/**
 * Handle Labor Cost Operations
 * 
 * This script handles CRUD operations for labor cost items in RAB
 * Actions: add, update, delete, get_items, recalculate
 */

error_reporting(0);
ob_start();
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';

@date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json');

// Get sales database connection
function getSalesPdo() {
    require __DIR__ . '/../config/database_sales.php';
    return new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

// Recalculate labor cost total for a RAB
function recalculateLaborCost($salesPdo, $rabId) {
    $stmt = $salesPdo->prepare("SELECT SUM(total_cost) as labor_total FROM rab_labor_cost_items WHERE rab_id = ?");
    $stmt->execute([$rabId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $laborTotal = $result && $result['labor_total'] ? floatval($result['labor_total']) : 0;
    
    // Update RAB labor cost and recalculate grand total
    $stmt = $salesPdo->prepare("
        UPDATE rab 
        SET total_labor_cost = ?,
            grand_total = COALESCE(total_section_a, 0) + 
                         COALESCE(total_section_b_warehouse, 0) + 
                         COALESCE(total_section_b_buy, 0) + 
                         COALESCE(total_section_c, 0) + 
                         COALESCE(total_section_d, 0) + ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$laborTotal, $laborTotal, $rabId]);
    
    return $laborTotal;
}

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if (empty($action)) {
        throw new Exception('Action tidak ditemukan');
    }
    
    $salesPdo = getSalesPdo();
    $salesPdo->beginTransaction();
    
    switch ($action) {
        
        case 'add':
            $rabId = intval($_POST['rab_id'] ?? 0);
            $jobDescription = trim($_POST['job_description'] ?? '');
            $workerName = trim($_POST['worker_name'] ?? '');
            $qty = intval($_POST['qty'] ?? 1);
            $unit = trim($_POST['unit'] ?? 'orang');
            $days = intval($_POST['days'] ?? 1);
            $ratePerDay = floatval($_POST['rate_per_day'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');
            
            if ($rabId <= 0) {
                throw new Exception('RAB ID tidak valid');
            }
            
            if (empty($jobDescription)) {
                throw new Exception('Deskripsi pekerjaan wajib diisi');
            }
            
            // Calculate total cost
            $totalCost = $qty * $days * $ratePerDay;
            
            // Get max item order
            $stmt = $salesPdo->prepare("SELECT COALESCE(MAX(item_order), -1) as max_order FROM rab_labor_cost_items WHERE rab_id = ?");
            $stmt->execute([$rabId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $itemOrder = intval($result['max_order']) + 1;
            
            // Insert labor cost item
            $stmt = $salesPdo->prepare("
                INSERT INTO rab_labor_cost_items 
                (rab_id, item_order, job_description, worker_name, qty, unit, days, rate_per_day, total_cost, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$rabId, $itemOrder, $jobDescription, $workerName, $qty, $unit, $days, $ratePerDay, $totalCost, $notes]);
            
            $itemId = $salesPdo->lastInsertId();
            
            // Recalculate labor cost total
            $laborTotal = recalculateLaborCost($salesPdo, $rabId);
            
            $salesPdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Item labor cost berhasil ditambahkan',
                'data' => [
                    'item_id' => $itemId,
                    'total_cost' => $totalCost,
                    'labor_total' => $laborTotal
                ]
            ]);
            break;
            
        
        case 'update':
            $itemId = intval($_POST['item_id'] ?? 0);
            $jobDescription = trim($_POST['job_description'] ?? '');
            $workerName = trim($_POST['worker_name'] ?? '');
            $qty = intval($_POST['qty'] ?? 1);
            $unit = trim($_POST['unit'] ?? 'orang');
            $days = intval($_POST['days'] ?? 1);
            $ratePerDay = floatval($_POST['rate_per_day'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');
            
            if ($itemId <= 0) {
                throw new Exception('Item ID tidak valid');
            }
            
            if (empty($jobDescription)) {
                throw new Exception('Deskripsi pekerjaan wajib diisi');
            }
            
            // Get RAB ID
            $stmt = $salesPdo->prepare("SELECT rab_id FROM rab_labor_cost_items WHERE id = ?");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                throw new Exception('Item tidak ditemukan');
            }
            
            $rabId = $item['rab_id'];
            
            // Calculate total cost
            $totalCost = $qty * $days * $ratePerDay;
            
            // Update labor cost item
            $stmt = $salesPdo->prepare("
                UPDATE rab_labor_cost_items 
                SET job_description = ?, 
                    worker_name = ?, 
                    qty = ?, 
                    unit = ?, 
                    days = ?, 
                    rate_per_day = ?, 
                    total_cost = ?, 
                    notes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$jobDescription, $workerName, $qty, $unit, $days, $ratePerDay, $totalCost, $notes, $itemId]);
            
            // Recalculate labor cost total
            $laborTotal = recalculateLaborCost($salesPdo, $rabId);
            
            $salesPdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Item labor cost berhasil diupdate',
                'data' => [
                    'total_cost' => $totalCost,
                    'labor_total' => $laborTotal
                ]
            ]);
            break;
            
        
        case 'delete':
            $itemId = intval($_POST['item_id'] ?? $_GET['item_id'] ?? 0);
            
            if ($itemId <= 0) {
                throw new Exception('Item ID tidak valid');
            }
            
            // Get RAB ID before deleting
            $stmt = $salesPdo->prepare("SELECT rab_id FROM rab_labor_cost_items WHERE id = ?");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                throw new Exception('Item tidak ditemukan');
            }
            
            $rabId = $item['rab_id'];
            
            // Delete item
            $stmt = $salesPdo->prepare("DELETE FROM rab_labor_cost_items WHERE id = ?");
            $stmt->execute([$itemId]);
            
            // Recalculate labor cost total
            $laborTotal = recalculateLaborCost($salesPdo, $rabId);
            
            $salesPdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Item labor cost berhasil dihapus',
                'data' => [
                    'labor_total' => $laborTotal
                ]
            ]);
            break;
            
        
        case 'get_items':
            $rabId = intval($_GET['rab_id'] ?? 0);
            
            if ($rabId <= 0) {
                throw new Exception('RAB ID tidak valid');
            }
            
            $stmt = $salesPdo->prepare("
                SELECT * FROM rab_labor_cost_items 
                WHERE rab_id = ? 
                ORDER BY item_order ASC, id ASC
            ");
            $stmt->execute([$rabId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get RAB labor cost total
            $stmt = $salesPdo->prepare("SELECT total_labor_cost FROM rab WHERE id = ?");
            $stmt->execute([$rabId]);
            $rab = $stmt->fetch(PDO::FETCH_ASSOC);
            $laborTotal = $rab ? floatval($rab['total_labor_cost']) : 0;
            
            $salesPdo->commit();
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'items' => $items,
                    'labor_total' => $laborTotal
                ]
            ]);
            break;
            
        
        case 'recalculate':
            $rabId = intval($_POST['rab_id'] ?? $_GET['rab_id'] ?? 0);
            
            if ($rabId <= 0) {
                throw new Exception('RAB ID tidak valid');
            }
            
            // Recalculate all item totals
            $stmt = $salesPdo->prepare("
                UPDATE rab_labor_cost_items 
                SET total_cost = qty * days * rate_per_day,
                    updated_at = NOW()
                WHERE rab_id = ?
            ");
            $stmt->execute([$rabId]);
            
            // Recalculate labor cost total
            $laborTotal = recalculateLaborCost($salesPdo, $rabId);
            
            $salesPdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Labor cost berhasil dikalkulasi ulang',
                'data' => [
                    'labor_total' => $laborTotal
                ]
            ]);
            break;
            
        default:
            throw new Exception('Action tidak valid');
    }
    
} catch (Exception $e) {
    if (isset($salesPdo) && $salesPdo->inTransaction()) {
        $salesPdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
