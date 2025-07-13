<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class SubscriptionCleaner {
    private $db;
    private $conn;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }

    /**
     * Check and clean expired subscriptions
     */
    public function cleanExpiredSubscriptions() {
        try {
            // Begin transaction
            $this->conn->beginTransaction();

            // Debug: Log current time
            $current_time = date('Y-m-d H:i:s');
            error_log("Running cleanup at: " . $current_time);

            // Get stores with expired or no STORE_SETTINGS_PRO subscription
            $stmt = $this->conn->prepare("
                SELECT 
                    s.id, 
                    s.name,
                    s.logo,
                    s.receipt_info,
                    MAX(ss.expired_at) as last_expired_at
                FROM stores s
                LEFT JOIN store_subscriptions ss ON s.id = ss.store_id 
                    AND ss.feature_code = 'STORE_SETTINGS_PRO'
                WHERE (s.logo IS NOT NULL OR s.receipt_info IS NOT NULL)
                    AND (ss.id IS NULL OR ss.expired_at < NOW())
                GROUP BY s.id, s.name, s.logo, s.receipt_info
                HAVING last_expired_at IS NULL OR last_expired_at < NOW()
            ");
            
            $stmt->execute();
            $expired_stores = $stmt->fetchAll();

            // Debug: Log found stores
            error_log("Found " . count($expired_stores) . " stores with expired subscriptions");

            if (!empty($expired_stores)) {
                // Log the cleanup process
                $log_stmt = $this->conn->prepare("
                    INSERT INTO subscription_cleanup_logs 
                    (store_id, store_name, cleaned_fields, cleaned_at, previous_values) 
                    VALUES (?, ?, ?, NOW(), ?)
                ");

                // Update stores to remove pro features
                $update_stmt = $this->conn->prepare("
                    UPDATE stores 
                    SET logo = NULL, 
                        receipt_info = NULL,
                        updated_at = NOW()
                    WHERE id = ?
                ");

                foreach ($expired_stores as $store) {
                    // Debug: Log store details
                    error_log("Processing store: " . $store['id'] . " - " . $store['name']);
                    
                    // Store previous values for logging
                    $previous_values = json_encode([
                        'logo' => $store['logo'],
                        'receipt_info' => $store['receipt_info']
                    ]);

                    // Update store
                    $update_result = $update_stmt->execute([$store['id']]);
                    
                    // Debug: Log update result
                    error_log("Update result for store " . $store['id'] . ": " . ($update_result ? 'Success' : 'Failed'));
                    
                    // Log the cleanup
                    $cleaned_fields = json_encode(['logo', 'receipt_info']);
                    $log_stmt->execute([
                        $store['id'],
                        $store['name'],
                        $cleaned_fields,
                        $previous_values
                    ]);

                    // Delete physical logo file if exists
                    $this->deleteLogoFile($store['id']);
                }
            }

            // Commit transaction
            $this->conn->commit();
            
            // Debug: Log completion
            error_log("Cleanup process completed successfully");
            
            return true;

        } catch (Exception $e) {
            // Rollback on error
            $this->conn->rollBack();
            
            // Log error with details
            error_log("Subscription cleanup error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Delete physical logo file if exists
     */
    private function deleteLogoFile($store_id) {
        $logo_path = __DIR__ . '/../uploads/logos/' . $store_id . '_logo.*';
        $files = glob($logo_path);
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                error_log("Deleted logo file: " . $file);
            }
        }
    }
}

// Create required table if not exists
function createRequiredTables($conn) {
    $sql = "
    CREATE TABLE IF NOT EXISTS subscription_cleanup_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id INT NOT NULL,
        store_name VARCHAR(255) NOT NULL,
        cleaned_fields TEXT NOT NULL,
        previous_values TEXT NOT NULL,
        cleaned_at DATETIME NOT NULL,
        INDEX idx_store_id (store_id),
        INDEX idx_cleaned_at (cleaned_at)
    ) ENGINE=InnoDB;
    ";
    
    $conn->exec($sql);
}

// Run cleaner
if (php_sapi_name() === 'cli') {
    // If running from command line
    $cleaner = new SubscriptionCleaner();
    $db = new Database();
    
    // Create required tables
    createRequiredTables($db->getConnection());
    
    // Run cleanup
    if ($cleaner->cleanExpiredSubscriptions()) {
        echo "Subscription cleanup completed successfully.\n";
    } else {
        echo "Error during subscription cleanup.\n";
        exit(1);
    }
} 