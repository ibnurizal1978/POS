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
<body>
    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="min-h-screen p-4 md:p-8">
        <!-- Top Bar -->
        <div class="bg-white rounded-lg shadow-sm mb-6 top-bar">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <button onclick="toggleSidebar()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div class="text-right">
                        <div class="text-sm text-gray-500 font-semibold">
                            <?php echo date('d F Y'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Container -->
        <div class="max-w-7xl mx-auto dashboard-container px-4 sm:px-6 lg:px-8 py-8">
            <!-- Store Info -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h1 class="text-3xl font-bold text-[#4a2c2a] mb-4"><?php echo htmlspecialchars($store['name'] ?? 'Toko'); ?></h1>
                <div class="grid grid-cols-2 gap-4">
                    <div class="stat-card bg-[#e6f3ff] p-4 rounded-lg border-l-4 border-blue-500">
                        <div class="text-xs text-blue-700 font-semibold">Transaksi Bulan Ini</div>
                        <div class="font-bold text-blue-900"><?php echo number_format($transactions['total'] ?? 0); ?></div>
                    </div>
                    <div class="stat-card bg-[#e6fff0] p-4 rounded-lg border-l-4 border-green-500">
                        <div class="text-xs text-green-700 font-semibold">Total Penjualan</div>
                        <div class="font-bold text-green-900">Rp <?php echo number_format($transactions['amount'] ?? 0); ?></div>
                    </div>
                </div>
            </div>

            <!-- Menu Grid -->
            <div class="grid grid-cols-3 md:grid-cols-4 gap-4">
                <!-- Transaksi -->
                <a href="transaction" class="menu-item flex flex-col items-center justify-center p-4 hover:bg-white rounded-xl transition-colors">
                    <div class="w-16 h-16 bg-[#ffeaea] rounded-full flex items-center justify-center mb-2 icon-circle">
                        <i class="fas fa-cash-register text-2xl text-red-600"></i>
                    </div>
                    <div class="text-[#4a2c2a] font-medium text-sm mt-1">Transaksi</div>
                </a>

                <!-- Pengaturan Toko -->
                <?php if (canAccessMenu('settings')): ?>
                <a href="store-settings" class="menu-item flex flex-col items-center justify-center p-4 hover:bg-white rounded-xl transition-colors">
                    <div class="w-16 h-16 bg-[#f0f0ff] rounded-full flex items-center justify-center mb-2 icon-circle">
                        <i class="fas fa-store text-2xl text-indigo-600"></i>
                    </div>
                    <div class="text-[#4a2c2a] font-medium text-sm mt-1">Pengaturan</div>
                </a>
                <?php endif; ?>

                <!-- Promo Master -->
                <?php if (canAccessMenu('promo')): ?>
                <a href="promotions" class="menu-item flex flex-col items-center justify-center p-4 hover:bg-white rounded-xl transition-colors">
                    <div class="w-16 h-16 bg-[#ff6600] rounded-full flex items-center justify-center mb-2 icon-circle">
                        <i class="fas fa-tag text-2xl text-indigo-600"></i>
                    </div>
                    <div class="text-[#4a2c2a] font-medium text-sm mt-1">Promo</div>
                </a>
                <?php endif; ?>                

                <!-- Kategori -->
                <?php if (canAccessMenu('categories')): ?>
                <a href="categories" class="menu-item flex flex-col items-center justify-center p-4 hover:bg-white rounded-xl transition-colors">
                    <div class="w-16 h-16 bg-[#e6fff0] rounded-full flex items-center justify-center mb-2 icon-circle">
                        <i class="fas fa-pencil-square text-2xl text-green-600"></i>
                    </div>
                    <div class="text-[#4a2c2a] font-medium text-sm mt-1">Kategori</div>
                </a>
                <?php endif; ?>

                <!-- Satuan -->
                <?php if (canAccessMenu('units')): ?>
                <a href="units" class="menu-item flex flex-col items-center justify-center p-4 hover:bg-white rounded-xl transition-colors">
                    <div class="w-16 h-16 bg-[#fff0e6] rounded-full flex items-center justify-center mb-2 icon-circle">
                        <i class="fas fa-puzzle-piece text-2xl text-orange-600"></i>
                    </div>
                    <div class="text-[#4a2c2a] font-medium text-sm mt-1">Satuan</div>
                </a>     
                <?php endif; ?>

                <!-- Produk -->
                <?php if (canAccessMenu('products')): ?>
                <a href="products" class="menu-item flex flex-col items-center justify-center p-4 hover:bg-white rounded-xl transition-colors">
                    <div class="w-16 h-16 bg-[#f0e6ff] rounded-full flex items-center justify-center mb-2 icon-circle">
                        <i class="fas fa-box text-2xl text-purple-600"></i>
                    </div>
                    <div class="text-[#4a2c2a] font-medium text-sm mt-1">Produk</div>
                </a>
                <?php endif; ?>

                <!-- User -->
                <?php if (canAccessMenu('users')): ?>
                <a href="users" class="menu-item flex flex-col items-center justify-center p-4 hover:bg-white rounded-xl transition-colors">
                    <div class="w-16 h-16 bg-[#e6f3ff] rounded-full flex items-center justify-center mb-2 icon-circle">
                        <i class="fas fa-users text-2xl text-blue-600"></i>
                    </div>
                    <div class="text-[#4a2c2a] font-medium text-sm mt-1">User</div>
                </a>
                <?php endif; ?>

                <!-- Laporan -->
                <?php if (canAccessMenu('reports')): ?>
                <a href="reports" class="menu-item flex flex-col items-center justify-center p-4 hover:bg-white rounded-xl transition-colors">
                    <div class="w-16 h-16 bg-[#fff0f0] rounded-full flex items-center justify-center mb-2 icon-circle">
                        <i class="fas fa-chart-bar text-2xl text-red-600"></i>
                    </div>
                    <div class="text-[#4a2c2a] font-medium text-sm mt-1">Laporan</div>
                </a>
                <?php endif; ?>

                <!-- Bantuan -->
                <a href="help" class="menu-item flex flex-col items-center justify-center p-4 hover:bg-white rounded-xl transition-colors">
                    <div class="w-16 h-16 bg-[#e6fff0] rounded-full flex items-center justify-center mb-2 icon-circle">
                        <i class="fas fa-question-circle text-2xl text-green-600"></i>
                    </div>
                    <div class="text-[#4a2c2a] font-medium text-sm mt-1">Bantuan</div>
                </a>

                <!-- Tutorial -->
                <a href="tutorial" class="menu-item flex flex-col items-center justify-center p-4 hover:bg-white rounded-xl transition-colors">
                    <div class="w-16 h-16 bg-[#fff0e6] rounded-full flex items-center justify-center mb-2 icon-circle">
                        <i class="fas fa-play-circle text-2xl text-orange-600"></i>
                    </div>
                    <div class="text-[#4a2c2a] font-medium text-sm mt-1">Tutorial</div>
                </a>
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>