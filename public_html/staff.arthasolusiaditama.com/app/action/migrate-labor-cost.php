<?php
/**
 * Migration Script: Add Labor Cost to RAB System
 * 
 * This script adds labor cost functionality to the RAB (Rencana Anggaran Biaya) system.
 * It creates a new table for labor cost items and adds necessary columns to the RAB table.
 * 
 * Run this script once to update the database schema.
 */

require_once '../config/database_sales.php';

// Create MySQLi connection
$conn_sales = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn_sales->connect_error) {
    die(json_encode([
        'success' => false,
        'error' => 'Connection failed: ' . $conn_sales->connect_error
    ]));
}

$conn_sales->set_charset('utf8mb4');

header('Content-Type: text/plain');

try {
    // Start transaction
    $conn_sales->begin_transaction();
    
    echo "Starting Labor Cost Migration...\n\n";
    
    // 1. Add labor cost columns to RAB table
    echo "Step 1: Adding labor cost columns to RAB table...\n";
    
    $alterRabTable = "
        ALTER TABLE rab 
        ADD COLUMN IF NOT EXISTS total_labor_cost DECIMAL(15,2) DEFAULT 0.00 AFTER total_section_d,
        ADD COLUMN IF NOT EXISTS labor_cost_notes TEXT AFTER total_labor_cost
    ";
    
    if ($conn_sales->query($alterRabTable)) {
        echo "✓ Successfully added labor cost columns to RAB table\n\n";
    } else {
        throw new Exception("Failed to alter RAB table: " . $conn_sales->error);
    }
    
    // 2. Create labor cost items table
    echo "Step 2: Creating labor cost items table...\n";
    
    $createLaborCostTable = "
        CREATE TABLE IF NOT EXISTS rab_labor_cost_items (
            id INT PRIMARY KEY AUTO_INCREMENT,
            rab_id INT NOT NULL,
            item_order INT DEFAULT 0,
            job_description VARCHAR(255) NOT NULL,
            worker_name VARCHAR(255),
            qty INT DEFAULT 1,
            unit VARCHAR(50) DEFAULT 'orang',
            days INT DEFAULT 1,
            rate_per_day DECIMAL(15,2) DEFAULT 0.00,
            total_cost DECIMAL(15,2) DEFAULT 0.00,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (rab_id) REFERENCES rab(id) ON DELETE CASCADE,
            INDEX idx_rab_id (rab_id),
            INDEX idx_item_order (item_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    if ($conn_sales->query($createLaborCostTable)) {
        echo "✓ Successfully created rab_labor_cost_items table\n\n";
    } else {
        throw new Exception("Failed to create labor cost items table: " . $conn_sales->error);
    }
    
    // 3. Update existing RAB records to initialize labor cost to 0
    echo "Step 3: Initializing labor cost for existing RAB records...\n";
    
    $updateExistingRab = "
        UPDATE rab 
        SET total_labor_cost = 0.00 
        WHERE total_labor_cost IS NULL
    ";
    
    if ($conn_sales->query($updateExistingRab)) {
        $affectedRows = $conn_sales->affected_rows;
        echo "✓ Updated $affectedRows existing RAB records\n\n";
    } else {
        throw new Exception("Failed to update existing RAB records: " . $conn_sales->error);
    }
    
    // 4. Update grand_total calculation to include labor cost
    echo "Step 4: Recalculating grand totals with labor cost...\n";
    
    $recalculateGrandTotal = "
        UPDATE rab 
        SET grand_total = COALESCE(total_section_a, 0) + 
                         COALESCE(total_section_b_warehouse, 0) + 
                         COALESCE(total_section_b_buy, 0) + 
                         COALESCE(total_section_c, 0) + 
                         COALESCE(total_section_d, 0) + 
                         COALESCE(total_labor_cost, 0)
    ";
    
    if ($conn_sales->query($recalculateGrandTotal)) {
        echo "✓ Recalculated grand totals for all RAB records\n\n";
    } else {
        throw new Exception("Failed to recalculate grand totals: " . $conn_sales->error);
    }
    
    // Commit transaction
    $conn_sales->commit();
    
    echo "===========================================\n";
    echo "Migration completed successfully!\n";
    echo "===========================================\n\n";
    echo "Summary:\n";
    echo "- Added total_labor_cost and labor_cost_notes columns to RAB table\n";
    echo "- Created rab_labor_cost_items table for labor cost details\n";
    echo "- Initialized labor cost for existing RAB records\n";
    echo "- Updated grand_total calculation formula\n\n";
    echo "Next steps:\n";
    echo "1. Update RAB forms to include labor cost input\n";
    echo "2. Update RAB calculation logic in handle-material-request.php\n";
    echo "3. Create UI for managing labor cost items\n";
    
    echo json_encode([
        'success' => true,
        'message' => 'Labor cost migration completed successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn_sales->rollback();
    
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn_sales->close();
?>
