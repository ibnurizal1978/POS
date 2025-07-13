<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/auth_helper.php';
require_once 'includes/premium_helper.php';
require_once 'includes/check_session.php';

$feature_code = $_GET['feature'] ?? '';

$database = new Database();
$conn = $database->getConnection();

// Ambil info fitur
$stmt = $conn->prepare("
    SELECT feature_name, description 
    FROM premium_features 
    WHERE feature_code = ?
");
$stmt->execute([$feature_code]);
$feature = $stmt->fetch();

// Ambil paket-paket untuk fitur tersebut
$stmt = $conn->prepare("
    SELECT package_name, duration_days, price, url
    FROM subscription_packages 
    WHERE feature_code = ?
    ORDER BY duration_days
");
$stmt->execute([$feature_code]);
$packages = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Upgrade ke Premium</title>
    <?php include 'includes/components.php'; ?>
</head>
<body class="bg-gray-100">
    <?php include 'includes/sidebar.php'; ?>

    <div class="lg:ml-64 min-h-screen">
        <main class="p-4">
            <div class="max-w-3xl mx-auto">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-2xl font-bold mb-6">Upgrade ke <?= htmlspecialchars($feature['feature_name']) ?></h2>
                    
                    <div class="mb-6">
                        <h3 class="font-medium mb-2">Deskripsi Fitur:</h3>
                        <p class="text-gray-600"><?= htmlspecialchars($feature['description']) ?></p>
                    </div>

                    <div class="mb-6">
                        <h3 class="font-medium mb-2">Pilih Paket Langganan:</h3>
                        <div class="grid md:grid-cols-2 gap-4">
                            <?php foreach ($packages as $i => $package): ?>
                                <div class="border rounded-lg p-4 <?= $i === 1 ? 'bg-blue-50 border-blue-200' : '' ?>">
                                    <h4 class="font-bold text-lg mb-2"><?= htmlspecialchars($package['package_name']) ?></h4>
                                    <p class="text-2xl font-bold text-blue-600 mb-2">
                                        Rp <?= number_format($package['price']) ?>
                                    </p>
                                    <p class="text-gray-600 mb-4">
                                        Durasi: <?= $package['duration_days'] ?> hari
                                    </p>
                                    <form method="POST" action="process-payment.php">
                                        <input type="hidden" name="feature_code" value="<?= htmlspecialchars($feature_code) ?>">
                                        <input type="hidden" name="package_id" value="<?= $i + 1 ?>">
                                        <a href="<?= htmlspecialchars($package['url']) ?>" target="_blank"  
                                           class="block w-full px-4 py-2 text-center bg-blue-500 text-white rounded-md hover:bg-blue-600">
                                            Pilih Paket Ini
                                        </a>
                                    </form>
                                    <?php if ($i === 1): ?>
                                        <p class="text-sm text-blue-600 mb-2">💰 Lebih hemat!</p>
                                    <?php endif; ?>                                    
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <a href="dashboard" class="w-full text-center inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-md hover:from-blue-600 hover:to-blue-700">
                        <i class="fas fa-arrow-left mr-2"></i> Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </main>
    </div>
    <?php include 'includes/footer.php'; ?>    
</body>
</html> 