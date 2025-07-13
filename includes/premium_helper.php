<?php
function isFeatureSubscribed($store_id, $feature_code) {
    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        return false;
    }
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as is_subscribed 
        FROM store_subscriptions 
        WHERE store_id = ? 
        AND feature_code = ?
        AND status = 'active'
        AND NOW() BETWEEN start_date AND end_date
    ");
    
    $stmt->execute([$store_id, $feature_code]);
    $result = $stmt->fetch();
    
    return $result['is_subscribed'] > 0;
}

function getPremiumFeatureInfo($feature_code) {
    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        return false;
    }
    
    $stmt = $conn->prepare("
        SELECT f.*, GROUP_CONCAT(
            CONCAT(
                p.package_name, ':', 
                p.duration_days, ':', 
                p.price, ':', 
                p.url
            )
            ORDER BY p.duration_days
        ) as packages
        FROM premium_features f
        LEFT JOIN subscription_packages p ON f.feature_code = p.feature_code
        WHERE f.feature_code = ?
        GROUP BY f.id
    ");
    
    $stmt->execute([$feature_code]);
    $result = $stmt->fetch();
    
    if ($result) {
        // Parse packages string into array
        $packages = [];
        foreach (explode(',', $result['packages']) as $package) {
            list($name, $days, $price, $url) = explode(':', $package);
            $packages[] = [
                'name' => $name,
                'duration_days' => $days,
                'price' => $price,
                'url' => $url
            ];
        }
        $result['packages'] = $packages;
    }
    
    return $result;
}

function getPackageById($package_id) {
    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        return false;
    }
    
    $stmt = $conn->prepare("
        SELECT * FROM subscription_packages WHERE id = ?
    ");
    
    $stmt->execute([$package_id]);
    return $stmt->fetch();
} 