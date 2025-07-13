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

// Jika tidak premium, batasi tanggal ke hari ini. sementara yang premium hanya download ke excel saja
/*if (!$has_premium) {
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d');
}*/

// Default filter (hari ini)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Query untuk ringkasan
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        SUM(total_amount) as total_sales,
        SUM(
            (
                SELECT SUM((td.price - p.cost_price) * td.quantity)
                FROM transaction_items td
                JOIN products p ON td.product_id = p.id
                WHERE td.transaction_id = t.id
            )
        ) as total_profit
    FROM transactions t 
    WHERE t.store_id = ? 
    AND DATE(t.created_at) BETWEEN ? AND ?
");
$stmt->execute([$_SESSION['store_id'], $start_date, $end_date]);
$summary = $stmt->fetch();

// Query untuk daftar transaksi
$stmt = $conn->prepare("
    SELECT t.*, u.full_name as cashier_name
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.id
    WHERE t.store_id = ? 
    AND DATE(t.created_at) BETWEEN ? AND ?
    ORDER BY t.created_at DESC
");
$stmt->execute([$_SESSION['store_id'], $start_date, $end_date]);
$transactions = $stmt->fetchAll();

// Handle export Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $data = [];
    
    // Header
    $data[] = ['LAPORAN PENJUALAN'];
    $data[] = [$start_date === $end_date ? 
        'Transaksi hari ini (' . date('d/m/Y', strtotime($start_date)) . ')' :
        'Transaksi dari ' . date('d/m/Y', strtotime($start_date)) . ' hingga ' . date('d/m/Y', strtotime($end_date))
    ];
    $data[] = []; // Empty row
    $data[] = ['No', 'Tanggal', 'No Transaksi', 'Total', 'Pembayaran', 'Kasir'];
    
    // Data
    $no = 1;
    foreach ($transactions as $t) {
        $data[] = [
            $no++,
            (function($datetime) {
                try {
                    $date = new DateTime($datetime, new DateTimeZone('UTC'));
                    $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
                    return $date->format('d/m/Y H:i');
                } catch (Exception $e) {
                    return date('d/m/Y H:i', strtotime($datetime));
                }
            })($t['created_at']),
            $t['invoice_number'],
            $t['total_amount'],
            $t['payment_method'],
            $t['cashier_name']
        ];
    }
    
    $xlsx = SimpleXLSXGen::fromArray($data);
    $xlsx->downloadAs('Laporan_Penjualan_' . date('Y-m-d') . '.xlsx');
    exit;
}

// Pastikan timezone valid, default ke Asia/Jakarta jika tidak valid
try {
    date_default_timezone_set($_SESSION['timezone'] ?? 'Asia/Jakarta');
} catch (Exception $e) {
    date_default_timezone_set('Asia/Jakarta');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title><?= $_SESSION['timezone'] ?> - Laporan Penjualan</title>
    <?php include 'includes/components.php'; ?>
    <!-- Tambahkan Flatpickr untuk date picker -->
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
                <h1 class="text-lg font-bold text-gray-800">Laporan Penjualan</h1>
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
                                <input type="date" 
                                        name="start_date" 
                                        value="<?= $start_date ?>"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <input type="date" 
                                        name="end_date" 
                                        value="<?= $end_date ?>"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" 
                                    class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Summary Cards - Updated Layout -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

                    <?php if ($has_premium): ?>
                        <div class="mt-4">
                            <a href="?export=excel&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
                               class="inline-block w-full text-center items-center px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                <i class="fas fa-file-excel mr-2"></i>
                                Export ke Excel
                            </a>
                        </div>
                    <?php else: ?> 
                        <div class="mt-6 bg-blue-50 rounded-lg p-4 text-center">
                            <p class="text-gray-600 mb-3">Mau Lihat laporan penjualan dalam format MS Excel agar lebih mudah dilihat dan diolah?</p>
                            <a href="premium-payment?feature=SALES_REPORT_PRO" 
                               class="inline-flex items-center px-6 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-md hover:from-blue-600 hover:to-blue-700 shadow-sm">
                                <i class="fas fa-crown mr-2"></i> 
                                Upgrade ke PRO
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Total Transaksi -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-500">Total Transaksi</h3>
                                <p class="text-3xl font-bold text-gray-800 mt-2">
                                    <?= number_format($summary['total_transactions'] ?? 0) ?>
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
                                    Rp <?= number_format($summary['total_sales'] ?? 0) ?>
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
                                    Rp <?= number_format($summary['total_profit'] ?? 0) ?>
                                </p>
                            </div>
                            <div class="bg-purple-100 rounded-full p-3">
                                <i class="fas fa-chart-line text-2xl text-purple-500"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transactions Table -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-800">
                            <?php if ($start_date === $end_date): ?>
                                Transaksi hari ini (<?= date('d/m/Y', strtotime($start_date)) ?>)
                            <?php else: ?>
                                Transaksi dari <?= date('d/m/Y', strtotime($start_date)) ?> hingga <?= date('d/m/Y', strtotime($end_date)) ?>
                            <?php endif; ?>
                        </h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-md font-medium text-gray-500 uppercase tracking-wider">
                                        Tanggal
                                    </th>
                                    <th class="px-6 py-3 text-left text-md font-medium text-gray-500 uppercase tracking-wider">
                                        No Transaksi
                                    </th>
                                    <th class="px-6 py-3 text-left text-md font-medium text-gray-500 uppercase tracking-wider">
                                        Total
                                    </th>
                                    <th class="px-6 py-3 text-left text-md font-medium text-gray-500 uppercase tracking-wider">
                                        Pembayaran
                                    </th>
                                    <th class="px-6 py-3 text-left text-md font-medium text-gray-500 uppercase tracking-wider">
                                        Kasir
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($transactions as $t): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-md text-gray-500">
                                            <?php
                                            try {
                                                $date = new DateTime($t['created_at'], new DateTimeZone('UTC'));
                                                $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
                                                echo $date->format('d/m/Y H:i');
                                            } catch (Exception $e) {
                                                echo date('d/m/Y H:i', strtotime($t['created_at']));
                                            }
                                            ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-md font-medium text-gray-900">
                                            <a href="report-sales-detail?invoice=<?= Encryption::encode($t['invoice_number']) ?>">
                                                <?= htmlspecialchars($t['invoice_number']) ?>
                                            </a>   
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-md text-gray-900">
                                            Rp <?= number_format($t['total_amount'] ?? 0) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-md text-gray-500">
                                            <?= ucfirst($t['payment_method'] ?? '-') ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-md text-gray-500">
                                            <?= htmlspecialchars($t['cashier_name'] ?? '-') ?>
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
        // Inisialisasi date picker
        flatpickr("input[type=date]", {
            dateFormat: "Y-m-d",
            locale: "id"
        });
    </script>
    <?php include 'includes/footer.php'; ?>
</body>
</html> 