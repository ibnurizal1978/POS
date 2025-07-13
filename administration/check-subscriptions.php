<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

$db = new Database();
$conn = $db->getConnection();

echo "=== Checking Store Subscriptions ===\n\n";

// 1. Cek stores yang masih punya logo atau receipt_info
$stmt = $conn->query("
    SELECT id, name, logo, receipt_info 
    FROM stores 
    WHERE logo IS NOT NULL OR receipt_info IS NOT NULL
");
$stores = $stmt->fetchAll();

echo "Stores with logo or receipt_info: " . count($stores) . "\n";
foreach ($stores as $store) {
    echo "Store #{$store['id']}: {$store['name']}\n";
    echo "- Logo: " . ($store['logo'] ? 'Yes' : 'No') . "\n";
    echo "- Receipt Info: " . ($store['receipt_info'] ? 'Yes' : 'No') . "\n";
    
    // 2. Cek subscription untuk setiap store
    $sub_stmt = $conn->prepare("
        SELECT id, end_date 
        FROM store_subscriptions 
        WHERE store_id = ? 
        AND feature_code = 'STORE_SETTINGS_PRO'
        ORDER BY end_date DESC 
        LIMIT 1
    ");

    $sub_stmt->execute([$store['id']]);
    $subscription = $sub_stmt->fetch();
    
    if ($subscription) {
        echo "- Last subscription expired at: {$subscription['end_date']}\n";
        if (strtotime($subscription['end_date']) < time()) {
            echo "*** SHOULD BE CLEANED ***\n";

            
            // 3. Mencoba update langsung
            $update_stmt = $conn->prepare("
                UPDATE stores 
                SET logo = NULL, 
                    receipt_info = NULL,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $result = $update_stmt->execute([$store['id']]);
            
            if ($result) {
                echo "-> Store updated successfully\n";
                
                // Log the cleanup
                $log_stmt = $conn->prepare("
                    INSERT INTO subscription_cleanup_logs 
                    (store_id, store_name, cleaned_fields, cleaned_at, previous_values) 
                    VALUES (?, ?, ?, NOW(), ?)
                ");
                
                $cleaned_fields = json_encode(['logo', 'receipt_info']);
                $previous_values = json_encode([
                    'logo' => $store['logo'],
                    'receipt_info' => $store['receipt_info']
                ]);
                
                $log_stmt->execute([
                    $store['id'],
                    $store['name'],
                    $cleaned_fields,
                    $previous_values
                ]);
                
                echo "-> Cleanup logged\n";
            } else {
                echo "-> Failed to update store\n";
                print_r($update_stmt->errorInfo());
            }
        }
    } else {
        echo "- No subscription found\n";
        echo "*** SHOULD BE CLEANED ***\n";
        
        // Update untuk store tanpa subscription
        $update_stmt = $conn->prepare("
            UPDATE stores 
            SET logo = NULL, 
                receipt_info = NULL,
                updated_at = NOW()
            WHERE id = ?
        ");
        $result = $update_stmt->execute([$store['id']]);
        
        if ($result) {
            echo "-> Store updated successfully\n";
            
            // Log the cleanup
            $log_stmt = $conn->prepare("
                INSERT INTO subscription_cleanup_logs 
                (store_id, store_name, cleaned_fields, cleaned_at, previous_values) 
                VALUES (?, ?, ?, NOW(), ?)
            ");
            
            $cleaned_fields = json_encode(['logo', 'receipt_info']);
            $previous_values = json_encode([
                'logo' => $store['logo'],
                'receipt_info' => $store['receipt_info']
            ]);
            
            $log_stmt->execute([
                $store['id'],
                $store['name'],
                $cleaned_fields,
                $previous_values
            ]);
            
            echo "-> Cleanup logged\n";
        } else {
            echo "-> Failed to update store\n";
            print_r($update_stmt->errorInfo());
        }
    }
    echo "\n";
}

echo "\nDone checking subscriptions.\n"; 