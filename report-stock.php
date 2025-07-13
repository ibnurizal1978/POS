<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/auth_helper.php';
require_once 'includes/premium_helper.php';
require_once 'includes/SimpleXLSXGen.php';
require_once 'includes/check_session.php';
require_once 'includes/tracking_helper.php';

// Check role
requireRole(['owner', 'admin']);

$db = new Database();
$conn = $db->getConnection();

// Setelah koneksi database dan session dibuat
logPageAccess($conn);

// Cek apakah store berlangganan fitur ini
$has_premium = isFeatureSubscribed($_SESSION['store_id'], 'STOCK_REPORT_PRO');

// Jika tidak premium, batasi tanggal ke hari ini
/*if (!$has_premium) {
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d');
}*/

// Default filter (hari ini)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Query data
$stmt = $conn->prepare("
    SELECT 
        p.id,
        p.barcode,
        p.name as product_name,
        c.name as category_name,
        u.name as unit_name,
        COALESCE((
            SELECT quantity 
            FROM stock_history 
            WHERE product_id = p.id 
            AND DATE(created_at) < ?
            ORDER BY created_at DESC 
            LIMIT 1
        ), 0) as opening_stock,
        COALESCE(SUM(CASE 
            WHEN sh.type = 'in' AND DATE(sh.created_at) BETWEEN ? AND ? 
            THEN sh.quantity 
            ELSE 0 
        END), 0) as stock_in,
        COALESCE(SUM(CASE 
            WHEN sh.type = 'out' AND DATE(sh.created_at) BETWEEN ? AND ? 
            THEN sh.quantity 
            ELSE 0 
        END), 0) as stock_out,
        p.stock as current_stock
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN units u ON p.unit_id = u.id
    LEFT JOIN stock_history sh ON p.id = sh.product_id
    WHERE p.store_id = ?
    GROUP BY p.id
    ORDER BY p.name ASC
");
    
$stmt->execute([
    $start_date,
    $start_date, $end_date,
    $start_date, $end_date,
    $_SESSION['store_id']
]);
$products = $stmt->fetchAll();

// Query untuk ringkasan
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT p.id) as total_products,
        COUNT(DISTINCT CASE WHEN p.stock <= p.min_stock THEN p.id END) as low_stock,
        SUM(CASE WHEN sh.type = 'in' THEN sh.quantity ELSE 0 END) as total_in,
        SUM(CASE WHEN sh.type = 'out' THEN sh.quantity ELSE 0 END) as total_out
    FROM products p
    LEFT JOIN stock_history sh ON p.id = sh.product_id
    WHERE p.store_id = ?
");
$stmt->execute([$_SESSION['store_id']]);
$summary = $stmt->fetch();

// Query untuk daftar produk
$stmt = $conn->prepare("
    SELECT 
        p.id,
        p.barcode,
        p.name as product_name,
        c.name as category_name,
        u.name as unit_name,
        COALESCE((
            SELECT quantity 
            FROM stock_history 
            WHERE product_id = p.id
            ORDER BY created_at DESC 
            LIMIT 1
        ), 0) as opening_stock,
        COALESCE(SUM(CASE 
            WHEN sh.type = 'in'
            THEN sh.quantity 
            ELSE 0 
        END), 0) as stock_in,
        COALESCE(SUM(CASE 
            WHEN sh.type = 'out'
            THEN sh.quantity 
            ELSE 0 
        END), 0) as stock_out,
        p.stock as current_stock,
        p.min_stock
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN units u ON p.unit_id = u.id
    LEFT JOIN stock_history sh ON p.id = sh.product_id
    WHERE p.store_id = ?
    GROUP BY p.id
    ORDER BY p.name ASC
");

$stmt->execute([$_SESSION['store_id']
]);
$products = $stmt->fetchAll();

// Handle export Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $data = [];
    
    // Header
    $data[] = ['LAPORAN STOK BARANG'];
    $data[] = []; // Empty row
    $data[] = ['No', 'Produk', 'Kategori', 'Stok Awal', 'Masuk', 'Keluar', 'Stok Akhir'];
    
    // Data
    $no = 1;
    foreach ($products as $t) {
        $data[] = [
            $no++,
            $t['product_name'],
            $t['category_name'],
            $t['opening_stock'],
            $t['stock_in'],
            $t['stock_out'],
            $t['current_stock']
        ];
    }
    
    $xlsx = SimpleXLSXGen::fromArray($data);
    $xlsx->downloadAs('Laporan_Stok_' . date('Y-m-d') . '.xlsx');
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Laporan Stok</title>
    <?php include 'includes/components.php'; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body class="bg-gray-100">
    <?php include 'includes/sidebar.php'; ?>

    <div class="lg:ml-64 min-h-screen">
        <header class="bg-white shadow-sm">
            <div class="px-4 py-3 flex items-center justify-between">
                <button onclick="toggleSidebar()" class="text-gray-500 hover:text-gray-700 lg:hidden">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h1 class="text-lg font-bold text-gray-800">Laporan Stok</h1>
                <div class="w-8"></div>
            </div>
        </header>

        <main class="p-4">
            <div class="max-w-7xl mx-auto">
                <!-- Filter -->
                <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
                    <?php if ($has_premium): ?>
                    <form method="GET" class="space-y-4 md:space-y-0 md:flex md:space-x-4">
                        <!--<div class="flex-1">
                            <label class="block text-md font-medium text-gray-700 mb-1">Pilih Periode</label>
                            <div class="flex space-x-2">
                                <input type="date" 
                                       name="start_date" 
                                       value="<?= $start_date ?>"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <input type="date" 
                                       name="end_date" 
                                       value="<?= $end_date ?>"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>-->
                        <div class="flex items-end space-x-2">
                           <!-- <button type="submit" 
                                    class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Filter
                            </button>-->
                            <a href="?export=excel&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
                               class="w-full text-center items-center px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                Export ke Excel
                            </a>
                        </div>
                    </form>
                    <?php else: ?>
                        <div class="text-center p-4">
                            <p class="text-gray-600 mb-4">Mau lihat laporan stok barang bisa download ke Excel?</p>
                            <a href="premium-payment.php?feature=STOCK_REPORT_PRO" 
                               class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-md hover:from-blue-600 hover:to-blue-700">
                                <i class="fas fa-crown mr-2"></i> Upgrade ke PRO
                            </a>
                        </div>
                    <?php endif; ?>                    
                </div>

                <!-- Summary Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow-sm p-4">
                        <h3 class="text-md font-medium text-gray-500">Total Produk</h3>
                        <p class="text-2xl font-bold text-gray-800"><?= number_format($summary['total_products']) ?></p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4">
                        <h3 class="text-md font-medium text-gray-500">Stok Menipis</h3>
                        <p class="text-2xl font-bold text-red-600"><?= number_format($summary['low_stock']) ?></p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4">
                        <h3 class="text-md font-medium text-gray-500">Total Item Masuk</h3>
                        <p class="text-2xl font-bold text-green-600">+<?= number_format($summary['total_in']) ?></p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4">
                        <h3 class="text-md font-medium text-gray-500">Total Item Keluar</h3>
                        <p class="text-2xl font-bold text-orange-600"><?= number_format($summary['total_out']) ?></p>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-800">
                            Laporan Stok Barang
                        </h3>
                    </div>                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-md font-medium text-gray-500 uppercase tracking-wider">
                                        Produk
                                    </th>
                                    <th class="px-6 py-3 text-left text-md font-medium text-gray-500 uppercase tracking-wider">
                                        Kategori
                                    </th>
                                    <th class="px-6 py-3 text-left text-md font-medium text-gray-500 uppercase tracking-wider">
                                        Stok Awal
                                    </th>
                                    <th class="px-6 py-3 text-left text-md font-medium text-gray-500 uppercase tracking-wider">
                                        Masuk
                                    </th>
                                    <th class="px-6 py-3 text-left text-md font-medium text-gray-500 uppercase tracking-wider">
                                        Keluar
                                    </th>
                                    <th class="px-6 py-3 text-left text-md font-medium text-gray-500 uppercase tracking-wider">
                                        Stok Akhir
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($products as $p): ?>
                                    <tr class="<?= $p['current_stock'] <= $p['min_stock'] ? 'bg-red-50' : '' ?>">
                                        <td class="px-6 py-4">
                                            <div class="text-md font-medium text-gray-900">
                                                <?= htmlspecialchars($p['product_name']) ?>
                                            </div>
                                            <div class="text-md text-gray-500">
                                                <?= htmlspecialchars($p['barcode']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-md text-gray-500">
                                            <?= htmlspecialchars($p['category_name']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-md text-gray-900">
                                            <?= number_format($p['opening_stock']) ?> <?= $p['unit_name'] ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-md text-green-600">
                                            +<?= number_format($p['stock_in']) ?> <?= $p['unit_name'] ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-md text-orange-600">
                                            -<?= number_format($p['stock_out']) ?> <?= $p['unit_name'] ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-md font-medium <?= $p['current_stock'] <= $p['min_stock'] ? 'text-red-600' : 'text-gray-900' ?>">
                                                <?= number_format($p['current_stock']) ?> <?= $p['unit_name'] ?>
                                            </span>
                                            <?php if ($p['current_stock'] <= $p['min_stock']): ?>
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-md font-medium bg-red-100 text-red-800">
                                                    Stok Menipis
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        flatpickr("input[type=date]", {
            dateFormat: "Y-m-d",
            locale: "id"
        });
    </script>
    <?php include 'includes/footer.php'; ?>
</body>
</html> 