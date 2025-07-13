<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/auth_helper.php';
require_once 'includes/check_session.php';
require_once 'includes/tracking_helper.php';

// Check role
requireRole(['owner', 'admin']);

$db = new Database();
$conn = $db->getConnection();

// Setelah koneksi database dan session dibuat
logPageAccess($conn);

// Get invoice number
$invoice = isset($_GET['invoice']) ? Encryption::decode($_GET['invoice']) : '';

// Pastikan timezone valid, default ke Asia/Jakarta jika tidak valid
try {
    date_default_timezone_set($_SESSION['timezone'] ?? 'Asia/Jakarta');
} catch (Exception $e) {
    date_default_timezone_set('Asia/Jakarta');
}

// Get transaction header
$stmt = $conn->prepare("
    SELECT 
        t.*,
        u.full_name as cashier_name
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.id
    WHERE t.invoice_number = ? 
    AND t.store_id = ?
");
$stmt->execute([$invoice, $_SESSION['store_id']]);
$transaction = $stmt->fetch();

if (!$transaction) {
    die("Transaksi tidak ditemukan");
}

// Get transaction items
$stmt = $conn->prepare("
    SELECT 
        ti.*,
        p.name as product_name,
        p.cost_price,
        c.name as category_name,
        ((ti.price - p.cost_price) * ti.quantity) as profit
    FROM transaction_items ti
    JOIN products p ON ti.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE ti.transaction_id = ?
    ORDER BY p.name ASC
");
$stmt->execute([$transaction['id']]);
$items = $stmt->fetchAll();

// Calculate totals
$total_items = 0;
$total_profit = 0;
foreach ($items as $item) {
    $total_items += $item['quantity'];
    $total_profit += $item['profit'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Detail Transaksi #<?= htmlspecialchars($invoice) ?></title>
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
                        Detail Transaksi #<?= htmlspecialchars($invoice) ?>
                    </h1>
                </div>
                <!--<button onclick="window.print()" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                    <i class="fas fa-print mr-2"></i>Print
                </button>-->
            </div>
        </header>

        <!-- Content -->
        <main class="p-4">
            <div class="max-w-7xl mx-auto">
                <!-- Transaction Info -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-800 mb-4">Informasi Transaksi</h3>
                            <div class="space-y-2">
                                <p class="text-sm">
                                    <span class="text-gray-500">Tanggal:</span>
                                    <span class="font-medium">
                                        <?php
                                        try {
                                            $date = new DateTime($transaction['created_at'], new DateTimeZone('UTC'));
                                            $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
                                            echo $date->format('d/m/Y H:i');
                                        } catch (Exception $e) {
                                            echo date('d/m/Y H:i', strtotime($transaction['created_at']));
                                        }
                                        ?>
                                    </span>
                                </p>
                                <p class="text-sm">
                                    <span class="text-gray-500">Kasir:</span>
                                    <span class="font-medium"><?= htmlspecialchars($transaction['cashier_name']) ?></span>
                                </p>
                                <p class="text-sm">
                                    <span class="text-gray-500">Metode Pembayaran:</span>
                                    <span class="font-medium"><?= ucfirst($transaction['payment_method']) ?></span>
                                </p>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium text-gray-800 mb-4">Ringkasan</h3>
                            <div class="space-y-2">
                                <p class="text-sm">
                                    <span class="text-gray-500">Total Item:</span>
                                    <span class="font-medium"><?= number_format($total_items) ?></span>
                                </p>
                                <p class="text-sm">
                                    <span class="text-gray-500">Total Pembayaran:</span>
                                    <span class="font-medium">Rp <?= number_format($transaction['total_amount']) ?></span>
                                </p>
                                <p class="text-sm">
                                    <span class="text-gray-500">Total Profit:</span>
                                    <span class="font-medium">Rp <?= number_format($total_profit) ?></span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-800">Detail Item</h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Produk
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Kategori
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
                                        Profit
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($item['product_name']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($item['category_name'] ?? '-') ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= number_format($item['quantity']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            Rp <?= number_format($item['price']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            Rp <?= number_format($item['price'] * $item['quantity']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            Rp <?= number_format($item['profit']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        Total
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= number_format($total_items) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        -
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        Rp <?= number_format($transaction['total_amount']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        Rp <?= number_format($total_profit) ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>