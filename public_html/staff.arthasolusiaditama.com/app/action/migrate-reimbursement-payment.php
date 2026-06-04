<?php
require_once '../config/database.php';

header('Content-Type: text/plain');

try {
    $pdo->beginTransaction();
    
    echo "Starting Reimbursement Payment Status Migration...\n\n";
    
    echo "Step 1: Adding payment_status and payment_date columns...\n";
    
    try {
        $pdo->exec("ALTER TABLE fuel_reimbursements 
                    ADD COLUMN payment_status ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid' AFTER status");
        echo "✓ Added payment_status column\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "✓ payment_status column already exists\n";
        } else {
            throw $e;
        }
    }
    
    try {
        $pdo->exec("ALTER TABLE fuel_reimbursements 
                    ADD COLUMN payment_date DATETIME DEFAULT NULL AFTER payment_status");
        echo "✓ Added payment_date column\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "✓ payment_date column already exists\n";
        } else {
            throw $e;
        }
    }
    
    try {
        $pdo->exec("ALTER TABLE fuel_reimbursements 
                    ADD COLUMN payment_by INT DEFAULT NULL AFTER payment_date");
        echo "✓ Added payment_by column\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "✓ payment_by column already exists\n";
        } else {
            throw $e;
        }
    }
    
    echo "\nStep 2: Initializing payment status for existing records...\n";
    
    $stmt = $pdo->exec("UPDATE fuel_reimbursements 
                        SET payment_status = 'unpaid' 
                        WHERE payment_status IS NULL OR payment_status = ''");
    echo "✓ Updated existing records\n";
    
    $pdo->commit();
    
    echo "\n===========================================\n";
    echo "Migration completed successfully!\n";
    echo "===========================================\n\n";
    echo "Summary:\n";
    echo "- Added payment_status column (unpaid/paid)\n";
    echo "- Added payment_date column\n";
    echo "- Added payment_by column\n";
    echo "- Initialized existing records to 'unpaid'\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
}
?>
