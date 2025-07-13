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

// Query untuk ringkasan per kasir
$stmt = $conn->prepare("
    SELECT 
        u.id as user_id,
        u.full_name as cashier_name,
        u.role,
        COUNT(DISTINCT t.id) as total_transactions,
        COALESCE(SUM(t.total_amount), 0) as total_sales,
        (
            SELECT COALESCE(SUM((ti.price - p.cost_price) * ti.quantity), 0)
            FROM transaction_items ti
            JOIN products p ON ti.product_id = p.id
            JOIN transactions tr ON ti.transaction_id = tr.id
            WHERE tr.user_id = u.id 
            AND tr.store_id = u.store_id
            AND DATE(tr.created_at) BETWEEN ? AND ?
        ) as total_profit,
        (
            SELECT COALESCE(SUM(ti.quantity), 0)
            FROM transaction_items ti
            JOIN transactions tr ON ti.transaction_id = tr.id
            WHERE tr.user_id = u.id 
            AND tr.store_id = u.store_id
            AND DATE(tr.created_at) BETWEEN ? AND ?
        ) as total_items
    FROM users u
    LEFT JOIN transactions t ON t.user_id = u.id 
        AND t.store_id = u.store_id
        AND DATE(t.created_at) BETWEEN ? AND ?
    WHERE u.store_id = ?
    AND u.id IN (
        SELECT DISTINCT user_id 
        FROM transactions 
        WHERE store_id = u.store_id
        AND DATE(created_at) BETWEEN ? AND ?
    )
    GROUP BY u.id, u.full_name, u.role
    ORDER BY total_sales DESC
");

$stmt->execute([
    $start_date, 
    $end_date,
    $start_date, 
    $end_date,
    $start_date, 
    $end_date,
    $_SESSION['store_id'],
    $start_date,
    $end_date
]);

$cashiers = $stmt->fetchAll();
// Hitung total keseluruhan
$total_transactions = 0;
$total_sales = 0;
$total_profit = 0;
foreach ($cashiers as $cashier) {
    $total_transactions += intval($cashier['total_transactions'] ?? 0);
    $total_sales += floatval($cashier['total_sales'] ?? 0);
    $total_profit += floatval($cashier['total_profit'] ?? 0);
}

// Handle export Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel' && $has_premium) {
    $data = [];
    
    // Header
    $data[] = ['LAPORAN PENJUALAN PER KASIR'];
    $data[] = [$start_date === $end_date ? 
        'Periode: ' . date('d/m/Y', strtotime($start_date)) : 
        'Periode: ' . date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date))
    ];
    $data[] = []; // Empty row
    $data[] = ['No', 'Nama Kasir', 'Jumlah Transaksi', 'Total Item', 'Total Penjualan', 'Total Profit'];
    
    // Data
    $no = 1;
    foreach ($cashiers as $cashier) {
        $data[] = [
            $no++,
            $cashier['cashier_name'],
            $cashier['total_transactions'],
            $cashier['total_items'],
            $cashier['total_sales'],
            $cashier['total_profit']
        ];
    }
    
    $xlsx = SimpleXLSXGen::fromArray($data);
    $xlsx->downloadAs('Laporan_Kasir_' . date('Y-m-d') . '.xlsx');
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Laporan Kasir</title>
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
                <h1 class="text-lg font-bold text-gray-800">Laporan Penjualan per Kasir</h1>
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
                    <!-- Total Transaksi -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-500">Total Transaksi</h3>
                                <p class="text-3xl font-bold text-gray-800 mt-2">
                                    <?= number_format(intval($total_transactions)) ?>
                                </p>
                            </div>
                            <div class="bg-blue-100 rounded-full p-3">
                                <i class="fas fa-shopping-cart text-2xl text-blue-500"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Penjualan -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-500">Total Penjualan</h3>
                                <p class="text-3xl font-bold text-gray-800 mt-2">
                                    Rp <?= number_format(floatval($total_sales)) ?>
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
                                    Rp <?= number_format(floatval($total_profit)) ?>
                                </p>
                            </div>
                            <div class="bg-purple-100 rounded-full p-3">
                                <i class="fas fa-chart-line text-2xl text-purple-500"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cashiers Table -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-800">
                            Detail Penjualan per Kasir
                        </h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Nama Kasir
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Jumlah Transaksi
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Total Item
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Total Penjualan
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Total Profit
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($cashiers as $cashier): ?>
                                    <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='report-cashier-detail?user_id=<?= Encryption::encode($cashier['user_id']) ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>'">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600 hover:text-blue-800">



                                            <?= htmlspecialchars($cashier['cashier_name']) ?> 
                                            <span class="text-gray-500">(<?= ucfirst($cashier['role']) ?>)</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= number_format(intval($cashier['total_transactions'] ?? 0)) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= number_format(intval($cashier['total_items'] ?? 0)) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            Rp <?= number_format(floatval($cashier['total_sales'] ?? 0)) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            Rp <?= number_format(floatval($cashier['total_profit'] ?? 0)) ?>
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