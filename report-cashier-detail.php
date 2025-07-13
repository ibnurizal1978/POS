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

// Get parameters
$user_id = isset($_GET['user_id']) ? Encryption::decode($_GET['user_id']) : 0;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Pastikan timezone valid, default ke Asia/Jakarta jika tidak valid
try {
    date_default_timezone_set($_SESSION['timezone'] ?? 'Asia/Jakarta');
} catch (Exception $e) {
    date_default_timezone_set('Asia/Jakarta');
}

// Get user info
$stmt = $conn->prepare("
    SELECT full_name, role 
    FROM users 
    WHERE id = ? AND store_id = ?
");
$stmt->execute([$user_id, $_SESSION['store_id']]);
$user = $stmt->fetch();

if (!$user) {
    die("User tidak ditemukan");
}

// Get transaction details
$stmt = $conn->prepare("
    SELECT 
        t.id,
        t.invoice_number,
        t.created_at,
        t.payment_method,
        t.total_amount,
        p.name as product_name,
        ti.quantity,
        ti.price,
        (ti.price * ti.quantity) as subtotal,
        ((ti.price - p.cost_price) * ti.quantity) as profit
    FROM transactions t
    JOIN transaction_items ti ON ti.transaction_id = t.id
    JOIN products p ON ti.product_id = p.id
    WHERE t.user_id = ? 
    AND t.store_id = ?
    AND DATE(t.created_at) BETWEEN ? AND ?
    ORDER BY t.created_at DESC, p.name ASC
");
$stmt->execute([$user_id, $_SESSION['store_id'], $start_date, $end_date]);
$transactions = $stmt->fetchAll();

// Calculate summary
$total_transactions = 0;
$total_items = 0;
$total_sales = 0;
$total_profit = 0;
$last_invoice = '';

foreach ($transactions as $t) {
    if ($last_invoice != $t['invoice_number']) {
        $total_transactions++;
        $last_invoice = $t['invoice_number'];
    }
    $total_items += $t['quantity'];
    $total_sales += $t['subtotal'];
    $total_profit += $t['profit'];
}

// Handle export Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $data = [];
    
    // Header
    $data[] = ["DETAIL TRANSAKSI - " . strtoupper($user['full_name'])];
    $data[] = [$start_date === $end_date ? 
        'Periode: ' . date('d/m/Y', strtotime($start_date)) : 
        'Periode: ' . date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date))
    ];
    $data[] = []; // Empty row
    $data[] = ['No', 'Tanggal', 'No Invoice', 'Produk', 'Qty', 'Harga', 'Subtotal', 'Pembayaran'];
    
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
            $t['product_name'],
            $t['quantity'],
            $t['price'],
            $t['subtotal'],
            $t['payment_method']
        ];
    }
    
    $xlsx = SimpleXLSXGen::fromArray($data);
    $xlsx->downloadAs('Detail_Transaksi_' . $user['full_name'] . '_' . date('Y-m-d') . '.xlsx');
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Detail Transaksi <?= htmlspecialchars($user['full_name']) ?></title>
    <?php include 'includes/components.php'; ?>
</head>
<body class="bg-gray-100">
    <?php include 'includes/sidebar.php'; ?>

    <div class="lg:ml-64 min-h-screen">
        <!-- Top Bar -->
        <header class="bg-white shadow-sm">
            <div class="px-4 py-3 flex items-center justify-between">
                <div class="flex items-center">
                    <button onclick="history.back()" class="mr-2 text-gray-500 hover:text-gray-700">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </button>
                    <h1 class="text-lg font-bold text-gray-800">
                        Detail Transaksi - <?= htmlspecialchars($user['full_name']) ?>
                        <span class="text-sm font-normal text-gray-500">(<?= ucfirst($user['role']) ?>)</span>
                    </h1>
                </div>
                <!-- <a href="?user_id=<?= $user_id ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&export=excel" 
                   class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600">
                    <i class="fas fa-file-excel mr-2"></i>Export
                </a> -->
            </div>
        </header>

        <!-- Content -->
        <main class="p-4">
            <div class="max-w-7xl mx-auto">
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <!-- Total Transaksi -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-500">Total Transaksi</h3>
                                <p class="text-3xl font-bold text-gray-800 mt-2">
                                    <?= number_format($total_transactions) ?>
                                </p>
                            </div>
                            <div class="bg-blue-100 rounded-full p-3">
                                <i class="fas fa-shopping-cart text-2xl text-blue-500"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Item -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-500">Total Item</h3>
                                <p class="text-3xl font-bold text-gray-800 mt-2">
                                    <?= number_format($total_items) ?>
                                </p>
                            </div>
                            <div class="bg-yellow-100 rounded-full p-3">
                                <i class="fas fa-box text-2xl text-yellow-500"></i>
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

                <!-- Transactions Table -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-800">
                            Detail Transaksi
                        </h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Tanggal
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        No Invoice
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Produk
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Qty
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Harga
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Subtotal
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Pembayaran
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                $current_invoice = '';
                                foreach ($transactions as $t): 
                                    $new_group = $current_invoice !== $t['invoice_number'];
                                    if ($new_group) {
                                        $current_invoice = $t['invoice_number'];
                                    }
                                ?>
                                    <tr class="<?= $new_group ? 'border-t-2 border-gray-200' : '' ?>">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php 
                                            if ($new_group) {
                                                try {
                                                    $date = new DateTime($t['created_at'], new DateTimeZone('UTC'));
                                                    $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
                                                    echo $date->format('d/m/Y H:i');
                                                } catch (Exception $e) {
                                                    echo date('d/m/Y H:i', strtotime($t['created_at']));
                                                }
                                            }
                                            ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= $new_group ? htmlspecialchars($t['invoice_number']) : '' ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= htmlspecialchars($t['product_name']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= number_format($t['quantity']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            Rp <?= number_format($t['price']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            Rp <?= number_format($t['subtotal']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= $new_group ? ucfirst($t['payment_method']) : '' ?>
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

    <?php include 'includes/footer.php'; ?>
</body>
</html> 