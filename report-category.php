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
$has_premium = isFeatureSubscribed($_SESSION['store_id'], 'SALES_REPORT_PRO');

// Default filter (hari ini)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Query untuk ringkasan per kategori
$stmt = $conn->prepare("
    SELECT 
        c.name as category_name,
        COUNT(DISTINCT t.id) as total_transactions,
        SUM(ti.quantity) as total_items,
        SUM(ti.quantity * ti.price) as total_sales,
        SUM((ti.price - p.cost_price) * ti.quantity) as total_profit
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    LEFT JOIN transaction_items ti ON ti.product_id = p.id
    LEFT JOIN transactions t ON t.id = ti.transaction_id
    WHERE t.store_id = ? 
    AND DATE(t.created_at) BETWEEN ? AND ?
    GROUP BY c.id, c.name
    ORDER BY total_sales DESC
");
$stmt->execute([$_SESSION['store_id'], $start_date, $end_date]);
$categories = $stmt->fetchAll();

// Hitung total keseluruhan
$total_sales = 0;
$total_profit = 0;
$total_items = 0;
foreach ($categories as $cat) {
    $total_sales += $cat['total_sales'];
    $total_profit += $cat['total_profit'];
    $total_items += $cat['total_items'];
}

// Handle export Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel' && $has_premium) {
    $data = [];
    
    // Header
    $data[] = ['LAPORAN PENJUALAN PER KATEGORI'];
    $data[] = [$start_date === $end_date ? 
        'Periode: ' . date('d/m/Y', strtotime($start_date)) : 
        'Periode: ' . date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date))
    ];
    $data[] = []; // Empty row
    $data[] = ['No', 'Kategori', 'Jumlah Transaksi', 'Total Item', 'Total Penjualan', 'Total Profit'];
    
    // Data
    $no = 1;
    foreach ($categories as $cat) {
        $data[] = [
            $no++,
            $cat['category_name'],
            $cat['total_transactions'],
            $cat['total_items'],
            $cat['total_sales'],
            $cat['total_profit']
        ];
    }
    
    $xlsx = SimpleXLSXGen::fromArray($data);
    $xlsx->downloadAs('Laporan_Kategori_' . date('Y-m-d') . '.xlsx');
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Laporan Kategori</title>
    <?php include 'includes/components.php'; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body class="bg-gray-100">
    <?php include 'includes/sidebar.php'; ?>

    <div class="lg:ml-64 min-h-screen">
        <!-- Top Bar -->
        <header class="bg-white shadow-sm">
            <div class="px-4 py-3 flex items-center justify-between">
                <button onclick="toggleSidebar()" class="text-gray-500 hover:text-gray-700 lg:hidden">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h1 class="text-lg font-bold text-gray-800">Laporan Penjualan per Kategori</h1>
                <div class="w-8"></div>
            </div>
        </header>

        <!-- Content -->
        <main class="p-4">
            <div class="max-w-7xl mx-auto">
                <!-- Filter -->
                <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
                    <form method="GET" class="space-y-4 md:space-y-0 md:flex md:space-x-4">
                        <div class="flex-1">
                            <label class="block text-md font-medium text-gray-700 mb-1">Pilih Periode</label>
                            <div class="flex space-x-2">
                                <input type="date" name="start_date" value="<?= $start_date ?>"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <input type="date" name="end_date" value="<?= $end_date ?>"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>
                        <div class="flex items-end space-x-2">
                            <button type="submit" 
                                    class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Filter
                            </button>
                            <?php if ($has_premium): ?>
                                <a href="?export=excel&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
                                   class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                    <i class="fas fa-file-excel mr-2"></i>Export
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <!-- Total Item -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-500">Total Item Terjual</h3>
                                <p class="text-3xl font-bold text-gray-800 mt-2">
                                    <?= number_format($total_items) ?>
                                </p>
                            </div>
                            <div class="bg-blue-100 rounded-full p-3">
                                <i class="fas fa-box text-2xl text-blue-500"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Penjualan -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-500">Total Penjualan</h3>
                                <p class="text-3xl font-bold text-gray-800 mt-2">
                                    Rp <?= number_format($total_sales) ?>
                                </p>
                            </div>
                            <div class="bg-green-100 rounded-full p-3">
                                <i class="fas fa-money-bill-wave text-2xl text-green-500"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Profit -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-500">Total Profit</h3>
                                <p class="text-3xl font-bold text-gray-800 mt-2">
                                    Rp <?= number_format($total_profit) ?>
                                </p>
                            </div>
                            <div class="bg-purple-100 rounded-full p-3">
                                <i class="fas fa-chart-line text-2xl text-purple-500"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Categories Table -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-800">
                            Detail Penjualan per Kategori
                        </h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-md font-medium text-gray-900 uppercase tracking-wider">
                                        Kategori
                                    </th>
                                    <th class="px-6 py-3 text-left text-md font-medium text-gray-900 uppercase tracking-wider">
                                        Jumlah Transaksi
                                    </th>
                                    <th class="px-6 py-3 text-left text-md font-medium text-gray-900 uppercase tracking-wider">
                                        Total Item
                                    </th>
                                    <th class="px-6 py-3 text-left text-md font-medium text-gray-900 uppercase tracking-wider">
                                        Total Penjualan
                                    </th>
                                    <th class="px-6 py-3 text-left text-md font-medium text-gray-900 uppercase tracking-wider">
                                        Total Profit
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-md font-medium text-gray-900">
                                            <?= htmlspecialchars($cat['category_name']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-md text-gray-900">
                                            <?= number_format($cat['total_transactions']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-md text-gray-900">
                                            <?= number_format($cat['total_items']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-md text-gray-900">
                                            Rp <?= number_format($cat['total_sales']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-md text-gray-900">
                                            Rp <?= number_format($cat['total_profit']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (!$has_premium): ?>
                    <div class="mt-6 bg-blue-50 rounded-lg p-4 text-center">
                        <p class="text-gray-600 mb-3">Mau export laporan ke Excel agar lebih mudah diolah?</p>
                        <a href="premium-payment?feature=SALES_REPORT_PRO" 
                           class="inline-flex items-center px-6 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-md hover:from-blue-600 hover:to-blue-700 shadow-sm">
                            <i class="fas fa-crown mr-2"></i> Upgrade ke PRO
                        </a>
                    </div>
                <?php endif; ?>
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