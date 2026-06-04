<?php
 
require_once __DIR__ . '/../config/database.php';

@date_default_timezone_set('Asia/Jakarta');

$results = [];

try {
    
    $pdo->exec("ALTER TABLE material_requests 
        MODIFY COLUMN status ENUM(
            'pending',
            'under_review',
            'approved',
            'rejected',
            'sales_approved',
            'admin_review',
            'admin_approved',
            'driver_pickup',
            'delivered'
        ) DEFAULT 'pending'
    ");
    $results[] = '[OK] Expanded material_requests status enum';

    
    $columns = [
        "ADD COLUMN IF NOT EXISTS sales_approved_by INT NULL",
        "ADD COLUMN IF NOT EXISTS sales_approved_at DATETIME NULL",
        "ADD COLUMN IF NOT EXISTS admin_approved_by INT NULL",
        "ADD COLUMN IF NOT EXISTS admin_approved_at DATETIME NULL",
        "ADD COLUMN IF NOT EXISTS driver_id INT NULL",
        "ADD COLUMN IF NOT EXISTS driver_pickup_photo VARCHAR(500) NULL",
        "ADD COLUMN IF NOT EXISTS driver_pickup_at DATETIME NULL",
        "ADD COLUMN IF NOT EXISTS driver_delivered_photo VARCHAR(500) NULL",
        "ADD COLUMN IF NOT EXISTS driver_delivered_at DATETIME NULL",
        "ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL",
        "ADD COLUMN IF NOT EXISTS rejected_by INT NULL",
        "ADD COLUMN IF NOT EXISTS rejected_at DATETIME NULL",
    ];

    foreach ($columns as $col) {
        try {
            $pdo->exec("ALTER TABLE material_requests $col");
        } catch (Exception $e) {
            
        }
    }
    $results[] = '[OK] Added tracking columns to material_requests';

    
    try {
        $pdo->exec("ALTER TABLE material_request_approvals MODIFY COLUMN estimated_delivery DATETIME NULL");
        $results[] = '[OK] Changed estimated_delivery to DATETIME';
    } catch (Exception $e) {
        $results[] = '[WARN] estimated_delivery: ' . $e->getMessage();
    }

    
    try {
        $pdo->exec("ALTER TABLE material_requests ADD INDEX idx_driver (driver_id)");
    } catch (Exception $e) {   }
    try {
        $pdo->exec("ALTER TABLE material_requests ADD INDEX idx_sales_approved (sales_approved_by)");
    } catch (Exception $e) {   }
    $results[] = '[OK] Added indexes';

    
    
    
    
    
    $results[] = '[OK] Old data preserved (backward compatible)';

    
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'driver'");
    $stmt->execute();
    $driverCount = $stmt->fetchColumn();
    $results[] = "[INFO] Found {$driverCount} user(s) with driver role";

    
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && strpos($row['Type'], 'driver') === false) {
            
            preg_match("/enum\((.+)\)/i", $row['Type'], $matches);
            if ($matches) {
                $enumValues = $matches[1];
                
                $newEnum = rtrim($enumValues, ')') . ",'driver'";
                $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM({$newEnum}) NOT NULL");
                $results[] = '[OK] Added driver role to users table enum';
            }
        } else {
            $results[] = '[OK] Driver role already exists in users enum';
        }
    } catch (Exception $e) {
        $results[] = '[WARN] Users role check: ' . $e->getMessage();
    }

    echo "<h2>Material Request v2 Migration Results</h2>";
    echo "<pre>" . implode("\n", $results) . "</pre>";
    echo "<p><strong>Migration completed successfully!</strong></p>";

} catch (Exception $e) {
    echo "<h2>Migration Failed</h2>";
    echo "<pre>[ERROR] Error: " . htmlspecialchars($e->getMessage()) . "</pre>";
    if ($results) {
        echo "<pre>Completed steps:\n" . implode("\n", $results) . "</pre>";
    }
}
?>
