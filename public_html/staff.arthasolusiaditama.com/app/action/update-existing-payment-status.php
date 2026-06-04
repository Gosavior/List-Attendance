<?php
require_once '../config/database.php';

header('Content-Type: text/plain');

try {
    $pdo->beginTransaction();
    
    echo "Updating existing approved reimbursements...\n\n";
    
    $stmt = $pdo->prepare("
        UPDATE fuel_reimbursements 
        SET payment_status = 'paid',
            payment_date = admin_decided_at,
            payment_by = admin_decided_by
        WHERE status = 'approved' 
        AND (payment_status IS NULL OR payment_status = 'unpaid')
    ");
    
    $stmt->execute();
    $affected = $stmt->rowCount();
    
    echo "✓ Updated $affected approved reimbursements to 'paid' status\n";
    
    $pdo->commit();
    
    echo "\n===========================================\n";
    echo "Update completed successfully!\n";
    echo "===========================================\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ Update failed: " . $e->getMessage() . "\n";
}
?>
