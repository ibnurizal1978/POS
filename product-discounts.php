<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/auth_helper.php';
require_once 'includes/check_session.php';
require_once 'includes/premium_helper.php';

// Check role
requireRole(['owner', 'admin']);

$db = new Database();
$conn = $db->getConnection();

// Ambil daftar produk untuk dropdown
$stmt = $conn->prepare("
    SELECT id, name, selling_price 
    FROM products 
    WHERE store_id = ? 
    ORDER BY name
");
$stmt->execute([$_SESSION['store_id']]);
$products = $stmt->fetchAll();

// Ambil daftar diskon aktif
$stmt = $conn->prepare("
    SELECT d.*, p.name as product_name, p.selling_price
    FROM product_discounts d
    JOIN products p ON d.product_id = p.id
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
        AND (name LIKE ? OR barcode LIKE ?)
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
    <title>Kelola Diskon Produk</title>
    <?php include 'includes/components.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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
                <h1 class="text-lg font-bold text-gray-800">Kelola Diskon Produk</h1>
                <div class="w-8"></div>
            </div>
        </header>

        <main class="p-4">
            <div class="max-w-7xl mx-auto">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        <?= $_SESSION['error'] ?>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                        <?= $_SESSION['success'] ?>
                        <?php unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <!-- Form Tambah Diskon -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h2 class="text-lg font-medium mb-4">Tambah Diskon Produk</h2>
                    <form action="process-discount.php" method="POST" class="space-y-4">
                        <div class="grid md:grid-cols-2 gap-4">
                            <!-- Produk -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Produk
                                </label>
                                <select class="select2-products" name="product_id" required>
                                    <option value="">Ketik nama produk...</option>
                                </select>
                            </div>

                            <!-- Minimum Pembelian -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Minimum Pembelian (Qty)
                                </label>
                                <input type="number" name="min_qty" required min="2"
                                       class="block w-full rounded-md border-gray-300">
                            </div>

                            <!-- Tipe Diskon -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Tipe Diskon
                                </label>
                                <select name="discount_type" id="discountType" required 
                                        class="block w-full rounded-md border-gray-300"
                                        onchange="toggleDiscountInput()">
                                    <option value="nominal">Nominal (Rp)</option>
                                    <option value="percentage">Persentase (%)</option>
                                </select>
                            </div>

                            <!-- Nilai Diskon -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Nilai Diskon
                                </label>
                                <input type="number" name="discount_value" required
                                       id="discountValue"
                                       class="block w-full rounded-md border-gray-300">
                                <p class="mt-1 text-sm text-gray-500" id="discountHelp">
                                    Masukkan nilai dalam Rupiah
                                </p>
                            </div>

                            <!-- Periode -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Periode Diskon
                                </label>
                                <div class="grid grid-cols-2 gap-4">
                                    <input type="date" name="start_date" required 
                                           class="block w-full rounded-md border-gray-300"
                                           placeholder="Mulai">
                                    <input type="date" name="end_date" required 
                                           class="block w-full rounded-md border-gray-300"
                                           placeholder="Selesai">
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                                Simpan Diskon
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tabel Daftar Diskon -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="p-4 border-b border-gray-200">
                        <h2 class="text-lg font-medium">Daftar Diskon Aktif</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Min. Qty</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Diskon</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Periode</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
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
                                                Harga Normal: Rp <?= number_format($discount['selling_price']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= $discount['min_qty'] ?> pcs
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php if ($discount['discount_type'] === 'percentage'): ?>
                                                <?= $discount['discount_value'] ?>%
                                            <?php else: ?>
                                                Rp <?= number_format($discount['discount_value']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= date('d/m/Y', strtotime($discount['start_date'])) ?> - 
                                            <?= date('d/m/Y', strtotime($discount['end_date'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php 
                                            $now = new DateTime();
                                            $start = new DateTime($discount['start_date']);
                                            $end = new DateTime($discount['end_date']);
                                            $status = ($now >= $start && $now <= $end) ? 'active' : 'inactive';
                                            ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                         <?= $status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                <?= $status === 'active' ? 'Aktif' : 'Nonaktif' ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <form action="process-discount.php" method="POST" class="inline">
                                                <input type="hidden" name="action" value="delete">
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
        </main>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        $(document).ready(function() {
            $('.select2-products').select2({
                ajax: {
                    url: 'product-discounts.php',
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
                placeholder: 'Ketik nama produk...'
            });

            flatpickr("input[type=date]", {
                dateFormat: "Y-m-d",
                minDate: "today"
            });
        });

        function toggleDiscountInput() {
            const type = document.getElementById('discountType').value;
            const help = document.getElementById('discountHelp');
            
            if (type === 'percentage') {
                help.textContent = 'Masukkan nilai dalam persen (0-100)';
                document.getElementById('discountValue').max = 100;
            } else {
                help.textContent = 'Masukkan nilai dalam Rupiah';
                document.getElementById('discountValue').removeAttribute('max');
            }
        }
    </script>
</body>
</html> 