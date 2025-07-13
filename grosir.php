<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/auth_helper.php';
require_once 'includes/check_session.php';
require_once 'includes/premium_helper.php';
require_once 'includes/tracking_helper.php';
// Check role
requireRole(['owner', 'admin']);

$has_premium = isFeatureSubscribed($_SESSION['store_id'], 'GROSIR_PRO');

// Function to get wholesale prices
function getWholesalePrices($product_id) {
    $db = new Database();
    $conn = $db->getConnection();

    // Setelah koneksi database dan session dibuat
    logPageAccess($conn);
    
    $store_id = $_SESSION['store_id'];
    
    $stmt = $conn->prepare("
        SELECT wp.*, wpd.min_qty, wpd.price 
        FROM wholesale_prices wp
        JOIN wholesale_price_details wpd ON wpd.wholesale_id = wp.id
        WHERE wp.product_id = ?
        AND wp.store_id = ?
        AND wp.is_active = 1
        AND CURRENT_DATE BETWEEN wp.start_date AND wp.end_date
        ORDER BY wpd.min_qty ASC
    ");
    
    $stmt->execute([$product_id, $store_id]);
    return $stmt->fetchAll();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();
    
    try {
        $conn->beginTransaction();

        $product_id = input_data($_POST['product_id'])  ;
        $start_date = input_data($_POST['start_date']);
        $end_date = input_data($_POST['end_date']);
        $store_id = $_SESSION['store_id'];

        //check apakah product_id ini sedang promo di tanggal antara tanggal mulai dan tanggal selesai
        $stmt = $conn->prepare("
            SELECT product_id 
            FROM wholesale_prices 
            WHERE product_id = ? 
            AND store_id = ? 
            AND (
                (? <= end_date AND ? >= start_date)
            )
        ");
        $stmt->execute([$product_id, $store_id, $start_date, $end_date]);
        $existingPromo = $stmt->fetch();
        if ($existingPromo) {
            throw new Exception("Produk ini sudah memiliki harga grosir aktif dalam rentang tanggal yang dipilih");
        }        

        // Insert wholesale price record
        $stmt = $conn->prepare("
            INSERT INTO wholesale_prices (store_id, user_id, product_id, start_date, end_date)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$store_id, $_SESSION['user_id'], $product_id, $start_date, $end_date]);
        $wholesale_id = $conn->lastInsertId();


        // Insert price details
        $stmt = $conn->prepare("
            INSERT INTO wholesale_price_details (wholesale_id, min_qty, price)
            VALUES (?, ?, ?)
        ");

        foreach ($_POST['quantities'] as $key => $qty) {
            if (!empty($qty) && !empty($_POST['prices'][$key])) {
                $stmt->execute([
                    $wholesale_id,
                    $qty,
                    $_POST['prices'][$key]
                ]);
            }
        }

        $conn->commit();
        $success = "Harga grosir berhasil ditambahkan!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Terjadi kesalahan: " . $e->getMessage();
    }
}

// Handle AJAX request untuk pencarian produk
if (isset($_GET['action']) && $_GET['action'] === 'search_products') {
    $db = new Database(); // Tambahkan ini
    $conn = $db->getConnection(); // Tambahkan ini
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

// Get products for dropdown
$db = new Database();
$conn = $db->getConnection();
$store_id = $_SESSION['store_id'];

$stmt = $conn->prepare("SELECT id, name, selling_price FROM products WHERE store_id = ?");
$stmt->execute([$store_id]);
$products = $stmt->fetchAll();

// Get existing wholesale prices
$stmt = $conn->prepare("
    SELECT wp.*, p.name as product_name, p.selling_price as regular_price
    FROM wholesale_prices wp
    JOIN products p ON p.id = wp.product_id
    WHERE wp.store_id = ?
    ORDER BY wp.created_at DESC
");
$stmt->execute([$store_id]);
$wholesale_prices = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Harga Grosir</title>
    <?php include 'includes/components.php'; ?>

    <!-- jQuery harus dimuat pertama -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Kemudian Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- Terakhir Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>    
</head>
<body class="bg-gray-100">
    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="min-h-screen">
        <!-- Top Bar -->
        <div class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 py-4">
                <div class="flex justify-between items-center">
                    <button onclick="toggleSidebar()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-lg font-semibold text-gray-800">Kelola Harga Grosir</h1>
                    <div></div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="max-w-7xl mx-auto px-4 py-6">
            <!-- Form Section -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">Tambah Harga Grosir Baru</h2>

                <?php if (isset($success)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($has_premium): ?>
                <form method="POST" id="wholesaleForm">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-gray-700 mb-2">Produk</label>
                            <select class="select2-products" name="product_id" required>
                                <option value="">Ketik nama produk...</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2">Tanggal Mulai</label>
                                <input type="date" name="start_date" required 
                                       class="w-full border rounded-lg px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Tanggal Selesai</label>
                                <input type="date" name="end_date" required 
                                       class="w-full border rounded-lg px-3 py-2">
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h3 class="text-lg font-semibold mb-3">Atur Harga Grosir</h3>
                        <div id="priceRules" class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 mb-2">Minimal Qty</label>
                                    <input type="number" name="quantities[]" min="1" 
                                           class="w-full border rounded-lg px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2">Harga per Item</label>
                                    <input type="number" name="prices[]" min="0" 
                                           class="w-full border rounded-lg px-3 py-2">
                                </div>
                            </div>
                        </div>
                        <button type="button" id="addRule" 
                                class="mt-4 text-blue-600 hover:text-blue-800">
                            + Tambah Aturan Harga
                        </button>
                    </div>

                    <div class="mt-6">
                        <button type="submit" 
                                class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                            Simpan Harga Grosir
                        </button>
                    </div>
                </form>
                <?php else: ?> 
                    <div class="mt-6 bg-blue-50 rounded-lg p-4 text-center">
                        <p class="text-gray-600 mb-3">Mau bikin harga berjenjang alias harga grosir? Contoh: Beli 3 harga jadi Rp. xxx, beli 5 harga jadi Rp. yyy</p>
                        <a href="premium-payment.php?feature=GROSIR_PRO" 
                            class="inline-flex items-center px-6 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-md hover:from-blue-600 hover:to-blue-700 shadow-sm">
                            <i class="fas fa-crown mr-2"></i> 
                            Upgrade ke PRO
                        </a>
                    </div>
                <?php endif; ?>                
            </div>

            <!-- List Section -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold mb-4">Daftar Harga Grosir</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Periode</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aturan Harga</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($wholesale_prices as $wp): 
                                $details = getWholesalePrices($wp['product_id']);
                            ?>
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900">
                                            <?php echo htmlspecialchars($wp['product_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            Harga Normal: Rp <?php echo number_format($wp['regular_price'], 0, ',', '.'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php echo date('d/m/Y', strtotime($wp['start_date'])); ?> - 
                                        <?php echo date('d/m/Y', strtotime($wp['end_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <ul class="text-sm">
                                                <?php 
                                                $stmt = $conn->prepare("SELECT min_qty, price FROM wholesale_price_details WHERE wholesale_id = ?");
                                                $stmt->execute([$wp['id']]);
                                                $details = $stmt->fetchAll();
                                                foreach ($details as $detail): ?>
                                                    <li>
                                                        Beli <?php echo htmlspecialchars($detail['min_qty']); ?> 
                                                        Rp <?php echo number_format(htmlspecialchars($detail['price']), 0, ',', '.'); ?>/item
                                                    </li>
                                                <?php endforeach; ?>
                                        </ul>
                                    </td>
                                    <td class="px-6 py-4">
                                        <form action="process-grosir" method="POST" class="inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $wp['id'] ?>">
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

    <?php include 'includes/footer.php'; ?>

    <script>
    $(document).ready(function() {
        // Initialize Select2
        $('.select2-products').select2({
            ajax: {
                url: 'grosir.php',
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
    </script>

    <script>
    document.getElementById('addRule').addEventListener('click', function() {
        const priceRules = document.getElementById('priceRules');
        const newRule = document.createElement('div');
        newRule.className = 'grid grid-cols-2 gap-4';
        newRule.innerHTML = `
            <div>
                <label class="block text-gray-700 mb-2">Minimal Qty</label>
                <input type="number" name="quantities[]" min="1" 
                       class="w-full border rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Harga per Item</label>
                <input type="number" name="prices[]" min="0" 
                       class="w-full border rounded-lg px-3 py-2">
            </div>
        `;
        priceRules.appendChild(newRule);
    });
    </script>
</body>
</html>