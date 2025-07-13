<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/auth_helper.php';
require_once 'includes/premium_helper.php';
require_once 'includes/check_session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $feature_code = $_POST['feature_code'];
    $package_id = $_POST['package_id'];
    $store_id = $_SESSION['store_id'];
    
    // Ambil informasi paket
    $package = getPackageById($package_id);
    if (!$package) {
        die('Paket tidak valid');
    }
    
    // Di sini implementasikan logika pembayaran
    // Misalnya integrasi dengan payment gateway
    
    // Setelah pembayaran sukses, tambahkan subscription
    $stmt = $conn->prepare("
        INSERT INTO store_subscriptions 
        (store_id, feature_code, package_id, start_date, end_date, status)
        VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), 'active')
    ");
    
    $stmt->execute([
        $store_id, 
        $feature_code,
        $package_id,
        $package['duration_days']
    ]);
    
    // Redirect ke halaman sukses
    header('Location: subscription-success.php');
    exit;
}
?>
ggg