<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/auth_helper.php';
require_once 'includes/check_session.php';
require_once 'includes/premium_helper.php';

// Cek role user
if (!in_array($_SESSION['role'], ['admin', 'owner'])) {
    header('Location: dashboard');
    exit;
}

// Cek apakah store berlangganan fitur ini
$has_premium = isFeatureSubscribed($_SESSION['store_id'], 'PROMOTIONS_PRO');

$db = new Database();
$conn = $db->getConnection();

// Ambil daftar produk untuk dropdown
$stmt = $conn->prepare("
    SELECT id, name 
    FROM products 
    WHERE store_id = ? 
    ORDER BY name
");
$stmt->execute([$_SESSION['store_id']]);
$products = $stmt->fetchAll();

// Ambil daftar promo aktif
$stmt = $conn->prepare("
    SELECT p.*, pr.name as product_name
    FROM promotions p
    JOIN products pr ON p.product_id = pr.id
    WHERE p.store_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$_SESSION['store_id']]);
$promotions = $stmt->fetchAll();

// Ambil daftar diskon aktif
$stmt = $conn->prepare("
    SELECT d.*, pr.name as product_name
    FROM discounts d
    JOIN products pr ON d.product_id = pr.id
    WHERE d.store_id = ?
    ORDER BY d.created_at DESC
");
$stmt->execute([$_SESSION['store_id']]);
$discounts = $stmt->fetchAll();

// Handle AJAX request untuk pencarian produk
if (isset($_GET['action']) && $_GET['action'] === 'search_products') {
    $search = $_GET['term'] ?? '';
    
    $stmt = $conn->prepare("
        SELECT id, name, selling_price 
        FROM products 
        WHERE store_id = ? 
        AND (
            name LIKE ? 
            OR barcode LIKE ?
        )
        ORDER BY name 
        LIMIT 10
    ");
    
    $searchTerm = "%$search%";
    $stmt->execute([$_SESSION['store_id'], $searchTerm, $searchTerm]);
    $products = $stmt->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode($products);
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Kelola Promo</title>
    <?php include 'includes/components.php'; ?>
    
    <!-- jQuery harus dimuat pertama -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Kemudian Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- Terakhir Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <style>
        .select2-container {
            width: 100% !important;
        }
        .select2-selection {
            height: 38px !important;
            padding-top: 4px;
        }
        .select2-selection__arrow {
            height: 36px !important;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'includes/sidebar.php'; ?>

    <div class="lg:ml-64 min-h-screen">
        <header class="bg-white shadow-sm">
            <div class="px-4 py-3 flex items-center justify-between">
                <button onclick="toggleSidebar()" class="text-gray-500 hover:text-gray-700 lg:hidden">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h1 class="text-lg font-bold text-gray-800">Kelola Promo</h1>
                <div class="w-8"></div>
            </div>
        </header>

        <main class="p-4">
        <?php if ($has_premium): ?>
            <div class="max-w-7xl mx-auto">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                        <span class="block sm:inline"><?= $_SESSION['error'] ?></span>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                        <span class="block sm:inline"><?= $_SESSION['success'] ?></span>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <!-- Buy X Get Y Form -->
                <div id="buyXgetY" class="tab-content">
                    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                        <h2 class="text-lg font-medium mb-4">Tambah Promo Beli X Gratis Y</h2>
                        <form action="process-promotion" method="POST" class="space-y-4">
                            <input type="hidden" name="type" value="promo">
                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Produk
                                    </label>
                                    <select class="select2-products" name="product_id" required>
                                        <option value="">Ketik nama produk...</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Periode Promo
                                    </label>
                                    <div class="flex space-x-2">
                                        <input type="date" name="start_date" required 
                                               class="block w-full rounded-md border-gray-300">
                                        <input type="date" name="end_date" required 
                                               class="block w-full rounded-md border-gray-300">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Beli (X)
                                    </label>
                                    <input type="number" name="buy_qty" required min="1"
                                           class="block w-full pl-12 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Gratis (Y)
                                    </label>
                                    <input type="number" name="free_qty" required min="1"
                                           class="block w-full pl-12 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md">
                                    Simpan Promo
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Daftar Promo Aktif -->
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div class="p-4 border-b border-gray-200">
                            <h3 class="font-medium">Daftar Promo Aktif</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Promo</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Periode</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($promotions as $promo): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($promo['product_name']) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm text-gray-900">
                                                    Beli <?= $promo['buy_qty'] ?> Gratis <?= $promo['free_qty'] ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <?= date('d/m/Y', strtotime($promo['start_date'])) ?> - 
                                                    <?= date('d/m/Y', strtotime($promo['end_date'])) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <form action="process-promotion" method="POST" class="inline">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $promo['id'] ?>">
                                                    <button type="submit" 
                                                            onclick="return confirm('Yakin ingin menghapus promo ini?')"
                                                            class="text-red-600 hover:text-red-900">
                                                        Hapus
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Discount Form -->
                <div id="discount" class="tab-content hidden">
                    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                        <h2 class="text-lg font-medium mb-4">Tambah Diskon</h2>
                        <form action="process-promotion.php" method="POST" class="space-y-4">
                            <input type="hidden" name="type" value="discount">
                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Produk
                                    </label>
                                    <div class="relative">
                                        <input type="text" 
                                               class="product-search block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                               placeholder="Ketik nama produk..."
                                               autocomplete="off"
                                               required>
                                        <input type="hidden" name="product_id" required>
                                        <div class="product-suggestions absolute w-full bg-white mt-1 rounded-md shadow-lg hidden"></div>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Periode Diskon
                                    </label>
                                    <div class="flex space-x-2">
                                        <input type="date" name="start_date" required 
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <input type="date" name="end_date" required 
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Minimal Pembelian
                                    </label>
                                    <input type="number" name="min_qty" required min="1"
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Tipe Diskon
                                    </label>
                                    <select name="discount_type" required 
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="percentage">Persentase (%)</option>
                                        <option value="fixed_amount">Nominal (Rp)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Nilai Diskon
                                    </label>
                                    <input type="number" name="discount_value" required min="0" step="0.01"
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" 
                                        class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                                    Simpan Diskon
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Daftar Diskon Aktif -->
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div class="p-4 border-b border-gray-200">
                            <h3 class="font-medium">Daftar Diskon Aktif</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Diskon</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Min. Qty</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Periode</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($discounts as $discount): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($discount['product_name']) ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?= htmlspecialchars($discount['product_code']) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm text-gray-900">
                                                    <?php if ($discount['discount_type'] === 'percentage'): ?>
                                                        <?= $discount['discount_value'] ?>%
                                                    <?php else: ?>
                                                        Rp <?= number_format($discount['discount_value']) ?>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm text-gray-900">
                                                    <?= $discount['min_qty'] ?> unit
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <?= date('d/m/Y', strtotime($discount['start_date'])) ?> - 
                                                    <?= date('d/m/Y', strtotime($discount['end_date'])) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <form action="process-promotion" method="POST" class="inline">
                                                    <input type="hidden" name="action" value="delete_discount">
                                                    <input type="hidden" name="id" value="<?= $discount['id'] ?>">
                                                    <button type="submit" 
                                                            onclick="return confirm('Yakin ingin menghapus diskon ini?')"
                                                            class="text-red-600 hover:text-red-900">
                                                        Hapus
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?> 
                <div class="text-center p-4">
                    <p class="text-gray-600 mb-4">Mau bisa bikin promo beli X gratis Y, contohnya beli 2 gratis 1?</p>
                    <a href="premium-payment.php?feature=PROMOTIONS_PRO" 
                    class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-md hover:from-blue-600 hover:to-blue-700">
                        <i class="fas fa-crown mr-2"></i> Upgrade ke PRO
                    </a>
                </div>
            <?php endif; ?> 
        </main>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('.select2-products').select2({
                ajax: {
                    url: 'promotions.php',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            action: 'search_products',
                            term: params.term
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.map(function(item) {
                                return {
                                    id: item.id,
                                    text: item.name
                                };
                            })
                        };
                    },
                    cache: true
                },
                minimumInputLength: 3,
                placeholder: 'Ketik nama produk...',
                width: '100%'
            });

            // Initialize date picker
            flatpickr("input[type=date]", {
                dateFormat: "Y-m-d",
                minDate: "today"
            });
        });

        // Tab switching function
        function switchTab(tabId) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            // Show selected tab content
            document.getElementById(tabId).classList.remove('hidden');
            
            // Update tab buttons
            document.querySelectorAll('#promoTabs button').forEach(button => {
                button.classList.remove('border-blue-500', 'text-blue-600');
                button.classList.add('text-gray-500', 'border-transparent');
            });
            
            // Highlight active tab
            event.currentTarget.classList.remove('text-gray-500', 'border-transparent');
            event.currentTarget.classList.add('border-blue-500', 'text-blue-600');
        }
    </script>
    <style>
        .product-suggestions {
            max-height: 200px;
            overflow-y: auto;
            z-index: 50;
        }
        .product-item:hover {
            background-color: #f3f4f6;
        }
    </style>
</body>
</html> 