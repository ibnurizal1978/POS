<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/check_session.php';
require_once 'includes/tracking_helper.php';

// Get store info and monthly transactions
try {
    $db = new Database();
    $conn = $db->getConnection();

    // Setelah koneksi database dan session dibuat
    logPageAccess($conn);
    
    // Get store info
    $stmt = $conn->prepare("SELECT name FROM stores WHERE id = ?");
    $stmt->execute([$_SESSION['store_id']]);
    $store = $stmt->fetch();

    // Get monthly transactions count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total, SUM(total_amount) as amount 
        FROM transactions 
        WHERE store_id = ? 
        AND MONTH(created_at) = MONTH(CURRENT_DATE())
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute([$_SESSION['store_id']]);
    $transactions = $stmt->fetch();
} catch (PDOException $e) {
    // Handle error
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Dashboard - <?php echo htmlspecialchars($store['name'] ?? 'Toko'); ?></title>
    <?php include 'includes/components.php'; ?>
</head>
<body class="bg-gray-100">
    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="min-h-screen">
        <!-- Top Bar -->
        <div class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <button onclick="toggleSidebar()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div class="text-right">
                        <div class="text-sm text-gray-500">
                            <?php echo date('d F Y'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Store Info dan Menu Grid dibungkus dalam satu container -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8">
            <!-- Store Info -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($store['name'] ?? 'Toko'); ?></h1>
                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <div class="text-sm text-blue-600">Transaksi Bulan Ini</div>
                        <div class="text-1xl font-bold text-blue-800"><?php echo number_format($transactions['total'] ?? 0); ?></div>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <div class="text-sm text-green-600">Total Penjualan</div>
                        <div class="text-1xl font-bold text-green-800">Rp <?php echo number_format($transactions['amount'] ?? 0); ?></div>
                    </div>
                </div>
            </div>

            <!-- Menu Grid dengan background putih -->
            <div class="bg-white rounded-lg shadow-sm p-6"> <!-- Tambah background dan padding -->
                <div class="grid grid-cols-3 gap-4">
                    <!-- Menu items tetap sama seperti sebelumnya -->
                    <!-- Transaksi -->
                    <a href="transaction" class="flex flex-col items-center justify-center p-4 hover:bg-gray-50 rounded-xl transition-colors">
                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mb-2">
                            <i class="fas fa-cash-register text-2xl text-blue-600"></i>
                        </div>
                        <div class="text-gray-700 font-medium text-md mt-1">Transaksi</div>
                    </a>

                    <!-- Order -->
                    <!--<a href="orders" class="flex flex-col items-center justify-center p-4 hover:bg-gray-50 rounded-xl transition-colors">
                        <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mb-2">
                            <i class="fas fa-shopping-bag text-2xl text-purple-600"></i>
                        </div>
                        <div class="text-gray-700 font-medium text-md mt-1">Order</div>
                    </a>-->

                    <!-- Atur Toko -->
                    <?php if (canAccessMenu('settings')): ?>
                    <a href="store-settings" class="flex flex-col items-center justify-center p-4 hover:bg-gray-50 rounded-xl transition-colors">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-2">
                            <i class="fas fa-store text-2xl text-gray-600"></i>
                        </div>
                        <div class="text-gray-700 font-medium text-md mt-1">Pengaturan</div>
                    </a>
                    <?php endif; ?>
                                        
                    <!-- Kategori -->
                    <?php if (canAccessMenu('categories')): ?>
                    <a href="categories" class="flex flex-col items-center justify-center p-4 hover:bg-gray-50 rounded-xl transition-colors">
                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mb-2">
                            <i class="fas fa-pencil-square text-2xl text-purple-600"></i>
                        </div>
                        <div class="text-gray-700 font-medium text-md mt-1">Kategori</div>
                    </a>
                    <?php endif; ?>

                    <!-- Satuan -->
                    <?php if (canAccessMenu('units')): ?>
                    <a href="units" class="flex flex-col items-center justify-center p-4 hover:bg-gray-50 rounded-xl transition-colors">
                        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-2">
                            <i class="fas fa-puzzle-piece text-2xl text-purple-600"></i>
                        </div>
                        <div class="text-gray-700 font-medium text-md mt-1">Satuan</div>
                    </a>     
                    <?php endif; ?>

                    <!-- Produk -->
                    <?php if (canAccessMenu('products')): ?>
                    <a href="products" class="flex flex-col items-center justify-center p-4 hover:bg-gray-50 rounded-xl transition-colors">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-2">
                            <i class="fas fa-box text-2xl text-green-600"></i>
                        </div>
                        <div class="text-gray-700 font-medium text-md mt-1">Produk</div>
                    </a>
                    <?php endif; ?>

                    <!-- User -->
                    <?php if (canAccessMenu('users')): ?>
                    <a href="users" class="flex flex-col items-center justify-center p-4 hover:bg-gray-50 rounded-xl transition-colors">
                        <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mb-2">
                            <i class="fas fa-users text-2xl text-indigo-600"></i>
                        </div>
                        <div class="text-gray-700 font-medium text-md mt-1">User</div>
                    </a>
                    <?php endif; ?>

                    <!-- Promo -->
                    <?php //if (canAccessMenu('promotions')): ?>
                    <!--<a href="promotions.php" class="flex flex-col items-center justify-center p-4 hover:bg-gray-50 rounded-xl transition-colors">
                        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-2">
                            <i class="fas fa-tag text-2xl text-red-600"></i>
                        </div>
                        <div class="text-gray-700 font-medium text-md mt-1">Promo</div>
                    </a>-->
                    <?php //endif; ?>

                    <!-- Laporan -->
                    <?php if (canAccessMenu('reports')): ?>
                    <a href="reports" class="flex flex-col items-center justify-center p-4 hover:bg-gray-50 rounded-xl transition-colors">
                        <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mb-2">
                            <i class="fas fa-chart-bar text-2xl text-yellow-600"></i>
                        </div>
                        <div class="text-gray-700 font-medium text-md mt-1">Laporan</div>
                    </a>
                    <?php endif; ?>

                    <!-- Bantuan -->
                    <a href="help" class="flex flex-col items-center justify-center p-4 hover:bg-gray-50 rounded-xl transition-colors">
                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mb-2">
                            <i class="fas fa-question-circle text-2xl text-blue-600"></i>
                        </div>
                        <div class="text-gray-700 font-medium text-md mt-1">Bantuan</div>
                    </a>

                    <!-- Tutorial -->
                    <a href="tutorial" class="flex flex-col items-center justify-center p-4 hover:bg-gray-50 rounded-xl transition-colors">
                        <div class="w-16 h-16 bg-pink-100 rounded-full flex items-center justify-center mb-2">
                            <i class="fas fa-play-circle text-2xl text-pink-600"></i>
                        </div>
                        <div class="text-gray-700 font-medium text-md mt-1">Tutorial</div>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html> 