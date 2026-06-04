<?php
require_once __DIR__ . '/../config/database.php';

try {
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS material_requests (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        project_id INT NOT NULL,
        status ENUM('pending', 'under_review', 'approved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (status),
        INDEX (user_id),
        INDEX (project_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    
    $pdo->exec("CREATE TABLE IF NOT EXISTS material_request_items (
        id INT PRIMARY KEY AUTO_INCREMENT,
        request_id INT NOT NULL,
        material_name VARCHAR(255) NOT NULL,
        quantity INT NOT NULL,
        unit VARCHAR(30) DEFAULT 'PCS',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (request_id) REFERENCES material_requests(id) ON DELETE CASCADE,
        INDEX (request_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    
    $pdo->exec("CREATE TABLE IF NOT EXISTS material_request_approvals (
        id INT PRIMARY KEY AUTO_INCREMENT,
        request_id INT NOT NULL,
        approved_by INT NOT NULL,
        photo_path VARCHAR(255),
        notes TEXT,
        estimated_delivery DATE,
        unit_price DECIMAL(15,2),
        quantity_bought INT,
        total_price DECIMAL(15,2),
        remaining_quantity INT,
        remaining_price DECIMAL(15,2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (request_id) REFERENCES material_requests(id) ON DELETE CASCADE,
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY (request_id),
        INDEX (approved_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    echo "Migration completed successfully!";

    
    try {
        $pdo->exec("ALTER TABLE material_request_items ADD COLUMN unit VARCHAR(30) DEFAULT 'PCS' AFTER quantity");
    } catch (Exception $e) {
        
    }
} catch (Exception $e) {
    die("Migration failed: " . $e->getMessage());
}
?>
