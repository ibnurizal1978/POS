<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/auth_helper.php';
require_once 'includes/check_session.php';
require_once 'includes/tracking_helper.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: index");
    exit;
}

// Initialize database connection
$db = new Database();
$conn = $db->getConnection();

// Get store_id from session
$store_id = $_SESSION['store_id'];

// Setelah koneksi database dan session dibuat
logPageAccess($conn);

// Get categories
$stmt = $conn->prepare("SELECT * FROM categories WHERE store_id = ? ORDER BY name");
$stmt->execute([$store_id]);
$categories = $stmt->fetchAll();

// Get initial products
$stmt = $conn->prepare("
    SELECT p.*, c.name as category_name, p.photo 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.store_id = ? 
    AND p.is_active = 1
    ORDER BY p.name
    LIMIT 50
");
$stmt->execute([$store_id]);
$products = $stmt->fetchAll();

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'filter_products':
            $categoryId = $_GET['category_id'] ?? 'all';
            $query = "
                SELECT p.*, c.name as category_name, p.photo 
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.store_id = ? 
                AND p.is_active = 1
            ";
            
            if ($categoryId !== 'all') {
                $query .= " AND p.category_id = ?";
                $params = [$store_id, $categoryId];
            } else {
                $params = [$store_id];
            }
            
            $query .= " ORDER BY p.name LIMIT 50";
            
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
            break;
            
        case 'search_products':
            $term = $_GET['term'] ?? '';
            $stmt = $conn->prepare("
                SELECT 
                    p.*,
                    c.name as category_name,
                    u.name as unit_name,
                    p.photo
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN units u ON p.unit_id = u.id
                WHERE p.store_id = ? 
                AND p.is_active = 1
                AND (p.name LIKE ? OR p.barcode LIKE ?)
                ORDER BY p.name ASC
                LIMIT 10
            ");
            
            $searchTerm = "%{$term}%";
            $stmt->execute([$_SESSION['store_id'], $searchTerm, $searchTerm]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($results);
            break;
            
        case 'get_product':
            $barcode = $_GET['barcode'] ?? '';
            $stmt = $conn->prepare("
                SELECT 
                    p.*,
                    c.name as category_name,
                    u.name as unit_name,
                    p.photo
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN units u ON p.unit_id = u.id
                WHERE p.barcode = ? 
                AND p.store_id = ? 
                AND p.is_active = 1
            ");
            
            $stmt->execute([$barcode, $_SESSION['store_id']]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode($product);
            break;
            
        case 'debug_wholesale':
            $productId = $_GET['product_id'] ?? null;
            if ($productId) {
                $stmt = $conn->prepare("
                    SELECT 
                        wp.id as wholesale_id,
                        wp.start_date,
                        wp.end_date,
                        wp.is_active,
                        wpd.min_qty,
                        wpd.price
                    FROM wholesale_prices wp
                    JOIN wholesale_price_details wpd ON wpd.wholesale_id = wp.id
                    WHERE wp.product_id = ?
                    AND wp.store_id = ?
                    ORDER BY wpd.min_qty ASC
                ");
                
                $stmt->execute([$productId, $_SESSION['store_id']]);
                $wholesaleData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'product_id' => $productId,
                    'wholesale_data' => $wholesaleData,
                    'current_date' => date('Y-m-d'),
                    'store_id' => $_SESSION['store_id']
                ]);
                exit;
            }
            break;
        
        case 'process_transaction':
            try {
                // Get raw input and log it
                $raw_input = file_get_contents('php://input');
                error_log("Raw input received: " . $raw_input);
                
                // Check if input is empty
                if (empty($raw_input)) {
                    throw new Exception('No input data received');
                }
                
                // Try to decode JSON
                $data = json_decode($raw_input, true);
                
                // Check for JSON errors
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('JSON decode error: ' . json_last_error_msg());
                }
                
                // Validate data structure
                if (!is_array($data)) {
                    throw new Exception('Invalid data format: not an array');
                }
                
                // Log decoded data
                error_log("Decoded data: " . print_r($data, true));
                
                // Validate required fields
                $required_fields = ['items', 'total', 'paid_amount', 'change_amount'];
                foreach ($required_fields as $field) {
                    if (!isset($data[$field])) {
                        throw new Exception("Missing required field: {$field}");
                    }
                }
                
                // Validate items array
                if (!is_array($data['items']) || empty($data['items'])) {
                    throw new Exception('Items array is empty or invalid');
                }
                
                // Generate invoice number
                $invoice_number = date('Ymd') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
                
                // Start transaction
                $conn->beginTransaction();
                
                try {
                    // Insert transaction header
                    $stmt = $conn->prepare("
                        INSERT INTO transactions (
                            invoice_number, 
                            store_id, 
                            user_id, 
                            total_amount,
                            paid_amount,
                            change_amount,
                            transaction_date,
                            created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    
                    $result = $stmt->execute([
                        $invoice_number,
                        $_SESSION['store_id'],
                        $_SESSION['user_id'],
                        floatval($data['total']),
                        floatval($data['paid_amount']),
                        floatval($data['change_amount'])
                    ]);
                    
                    if (!$result) {
                        throw new Exception('Failed to insert transaction header');
                    }
                    
                    $transaction_id = $conn->lastInsertId();
                    
                    // Insert transaction details
                    $stmt = $conn->prepare("
                        INSERT INTO transaction_details (
                            transaction_id,
                            product_id,
                            quantity,
                            price,
                            subtotal,
                            is_wholesale,
                            created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    foreach ($data['items'] as $item) {
                        $result = $stmt->execute([
                            $transaction_id,
                            $item['id'],
                            intval($item['quantity']),
                            floatval($item['price']),
                            floatval($item['subtotal']),
                            isset($item['is_wholesale']) ? intval($item['is_wholesale']) : 0
                        ]);
                        
                        if (!$result) {
                            throw new Exception('Failed to insert transaction detail');
                        }
                    }
                    
                    // Commit transaction
                    $conn->commit();
                    
                    echo json_encode([
                        'success' => true,
                        'invoice_number' => $invoice_number,
                        'message' => 'Transaction processed successfully'
                    ]);
                    
                } catch (Exception $e) {
                    $conn->rollBack();
                    throw new Exception('Database error: ' . $e->getMessage());
                }
                
            } catch (Exception $e) {
                error_log("Transaction processing error: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to process transaction: ' . $e->getMessage(),
                    'debug' => [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]
                ]);
            }
            exit;
            break;
    }
    exit;
}

function searchProducts($term) {
    $db = new Database();
    $conn = $db->getConnection();
    
    try {
        // Debug log
        error_log("Searching products with term: $term");
        
        $stmt = $conn->prepare("
            SELECT 
                p.*,
                u.name as unit_name,
                wp.id as wholesale_id,
                wp.start_date,
                wp.end_date,
                wpd.min_qty as wholesale_min_qty,
                wpd.price as wholesale_price
            FROM products p
            LEFT JOIN units u ON p.unit_id = u.id
            LEFT JOIN (
                SELECT wp1.*
                FROM wholesale_prices wp1
                WHERE wp1.is_active = 1
                AND CURRENT_DATE BETWEEN wp1.start_date AND wp1.end_date
            ) wp ON wp.product_id = p.id AND wp.store_id = p.store_id
            LEFT JOIN wholesale_price_details wpd ON wpd.wholesale_id = wp.id
            WHERE p.store_id = ? 
            AND p.is_active = 1
            AND (p.name LIKE ? OR p.barcode LIKE ?)
            ORDER BY p.name, wpd.min_qty ASC
            LIMIT 10
        ");

        $stmt->execute([$_SESSION['store_id'], "%$term%", "%$term%"]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group products and find lowest wholesale price
        $products = [];
        foreach ($results as $row) {
            $productId = $row['id'];
            
            // Debug log
            error_log("Processing product ID: $productId");
            error_log("Wholesale data: " . json_encode([
                'wholesale_id' => $row['wholesale_id'],
                'min_qty' => $row['wholesale_min_qty'],
                'price' => $row['wholesale_price'],
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date']
            ]));
            
            if (!isset($products[$productId])) {
                $products[$productId] = $row;
                
                // Initialize wholesale data if exists
                if ($row['wholesale_id']) {
                    $products[$productId]['wholesale'] = [
                        'min_qty' => (int)$row['wholesale_min_qty'],
                        'price' => (float)$row['wholesale_price']
                    ];
                    
                    error_log("Added wholesale data for product $productId: " . 
                        json_encode($products[$productId]['wholesale']));
                }
            } else if ($row['wholesale_id'] && 
                      (!isset($products[$productId]['wholesale']) || 
                       $row['wholesale_min_qty'] < $products[$productId]['wholesale']['min_qty'])) {
                // Update with lower minimum quantity wholesale price
                $products[$productId]['wholesale'] = [
                    'min_qty' => (int)$row['wholesale_min_qty'],
                    'price' => (float)$row['wholesale_price']
                ];
                
                error_log("Updated wholesale data for product $productId: " . 
                    json_encode($products[$productId]['wholesale']));
            }
            
            // Clean up unnecessary fields
            unset($products[$productId]['wholesale_id']);
            unset($products[$productId]['wholesale_min_qty']);
            unset($products[$productId]['wholesale_price']);
            unset($products[$productId]['start_date']);
            unset($products[$productId]['end_date']);
        }
        
        $finalProducts = array_values($products);
        error_log("Final products data: " . json_encode($finalProducts));
        
        return $finalProducts;
    } catch (PDOException $e) {
        error_log("Error in searchProducts: " . $e->getMessage());
        return [];
    }
}

// Function to generate invoice number
function generateInvoiceNumber() {
    $prefix = date('Ymd');
    $random = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    return $prefix . $random;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Transaksi</title>
    <?php include 'includes/components.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .select2-container {
            width: 100% !important;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <!-- Top Bar -->
        <div class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 py-4">
                <div class="flex justify-between items-center">
                    <button onclick="toggleSidebar()" class="text-gray-500 hover:text-gray-700 no-print">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-lg font-semibold text-gray-800">Transaksi</h1>
                    <div class="w-8"></div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="max-w-7xl mx-auto px-4 py-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Kolom Kiri: Cart dan Daftar Produk -->
                <div class="lg:col-span-2">
                    <!-- Cart Panel -->
                    <div class="bg-white rounded-lg shadow-sm mb-6">
                        <div id="items_container" class="divide-y divide-gray-200">
                            <!-- Items will be added here -->
                        </div>
                        <!-- Empty State -->
                        <div id="empty_state" class="p-8 text-center">
                            <div class="mx-auto mb-4">
                                <i class="fas fa-shopping-cart text-2xl text-gray-400"></i>
                            </div>
                            <h3 class="text-gray-500 font-medium">Belum ada item</h3>
                            <p class="text-sm text-gray-400">Pilih produk untuk mulai</p>
                        </div>
                        <!-- Checkout Button -->
                        <div class="p-4 bg-gray-50 border-t">
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-medium text-gray-600">Total</span>
                                <span class="text-xl font-semibold text-gray-800" id="cart_total">Rp 0</span>
                            </div>
                            <button onclick="showPaymentModal()" 
                                    id="checkout_button"
                                    disabled
                                    class="w-full py-3 bg-blue-500 text-white rounded-lg font-medium hover:bg-blue-600 active:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                <i class="fas fa-cash-register mr-2"></i>
                                Selesai (F8)
                            </button>
                        </div>
                    </div>

                    <!-- Products Panel -->
                    <div class="bg-white rounded-lg shadow-sm p-4">
                        <!-- Search Bar dan Barcode Scanner Icon -->
                        <div class="flex gap-2 mb-4">
                            <div class="flex-grow relative">
                                <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search text-gray-400"></i>
                                </div>
                                <input type="text" 
                                       id="product_search" 
                                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Ketik nama produk...">
                            </div>
                            <button onclick="showScannerModal()" 
                                    class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 active:bg-gray-300 transition-colors">
                                <i class="fas fa-barcode"></i>
                            </button>
                        </div>

                        <!-- Product Categories -->
                        <div class="flex overflow-x-auto py-2 mb-4 gap-2">
                            <button onclick="filterProducts('all')" 
                                    class="px-4 py-2 bg-blue-500 text-white rounded-lg whitespace-nowrap hover:bg-blue-600 active:bg-blue-700 transition-colors">
                                Semua
                            </button>
                            <?php foreach ($categories as $category): ?>
                                <button onclick="filterProducts(<?= $category['id'] ?>)"
                                        class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 active:bg-gray-300 transition-colors whitespace-nowrap">
                                    <?= htmlspecialchars($category['name']) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <!-- Products Grid -->
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4" id="products_grid">
                            <?php foreach ($products as $product): ?>
                                <button onclick='addItem(<?= json_encode($product) ?>)'
                                        class="p-4 border rounded-lg text-left transition-all hover:bg-gray-50 active:bg-gray-100 hover:border-gray-300 active:scale-95">
                                    <h3 class="font-medium text-gray-800 mb-1"><?= htmlspecialchars($product['name']) ?></h3>
                                    <div class="text-sm text-gray-500 mb-2">
                                        <?= htmlspecialchars($product['category_name']) ?>
                                    </div>
                                    <div class="text-green-600 font-medium">
                                        Rp <?= number_format($product['selling_price']) ?>
                                    </div>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan: Total Panel (sama seperti sebelumnya) -->
                <div class="lg:col-span-1">
                    <!-- ... existing total panel code ... -->
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Pembayaran</h3>
                <button onclick="hidePaymentModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="mb-4">
                <div class="flex justify-between mb-2">
                    <span class="text-gray-600">Total</span>
                    <span class="text-xl font-semibold text-gray-800" id="payment_total">Rp 0</span>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Bayar</label>
                    <input type="text" 
                           id="paid_amount" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           onkeyup="calculateChange(this.value)"
                           placeholder="0">
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Kembalian</span>
                    <span class="text-lg font-medium text-gray-800" id="change_amount">Rp 0</span>
                </div>
            </div>
            
            <button onclick="processTransaction()" 
                    id="process_button"
                    disabled
                    class="w-full py-3 bg-blue-500 text-white rounded-lg font-medium hover:bg-blue-600 active:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                Proses Pembayaran
            </button>
        </div>
    </div>

    <!-- Scanner Modal -->
    <div id="scannerModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 m-4 max-w-sm w-full">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Scan Barcode</h3>
                <button onclick="stopScanner()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="scanner-container" class="w-full h-48 rounded-lg overflow-hidden bg-gray-100 mb-4">
                <video id="scanner" class="w-full h-full object-cover"></video>
            </div>
            <div class="text-center text-sm text-gray-500">
                Arahkan kamera ke barcode produk
            </div>
        </div>
    </div>

    <!-- Receipt Template -->
    <div id="receipt" class="hidden print-only">
        <!-- ... receipt template ... -->
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://unpkg.com/@zxing/library@latest"></script>
    <script>
        // Deklarasi variabel global untuk items dan total
        let items = [];
        let total = 0;

        // Fungsi untuk update tampilan keranjang
        function updateDisplay() {
            const container = document.getElementById('items_container');
            const emptyState = document.getElementById('empty_state');
            
            // Update total
            total = items.reduce((sum, item) => sum + item.subtotal, 0);
            
            // Update tampilan keranjang
            if (items.length > 0) {
                emptyState.classList.add('hidden');
                container.innerHTML = items.map((item, index) => `
                    <div class="p-4 flex items-center justify-between">
                        <div class="flex-grow">
                            <h3 class="font-medium text-gray-800">${item.name}</h3>
                            <div class="text-sm text-gray-500">
                                Rp ${item.price.toLocaleString()} × ${item.quantity} ${item.unit_name}
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="text-green-600 font-medium">
                                Rp ${item.subtotal.toLocaleString()}
                            </div>
                            <div class="flex items-center gap-2">
                                <button onclick="updateQuantity(${index}, -1)" 
                                        class="p-1 text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span class="w-8 text-center">${item.quantity}</span>
                                <button onclick="updateQuantity(${index}, 1)"
                                        class="p-1 text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button onclick="removeItem(${index})"
                                        class="p-1 text-red-500 hover:text-red-700">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `).join('');
            } else {
                emptyState.classList.remove('hidden');
                container.innerHTML = '';
            }
            
            // Update semua tampilan total
            const totalFormatted = `Rp ${total.toLocaleString()}`;
            
            // Update total di cart
            document.getElementById('cart_total').textContent = totalFormatted;
            
            // Update total di payment modal jika sedang terbuka
            const paymentTotal = document.getElementById('payment_total');
            if (paymentTotal) {
                paymentTotal.textContent = totalFormatted;
            }
            
            // Update status tombol checkout
            document.getElementById('checkout_button').disabled = total === 0;
            
            // Recalculate change jika payment modal sedang terbuka
            const paidAmount = document.getElementById('paid_amount');
            if (paidAmount && paidAmount.value) {
                calculateChange(paidAmount.value);
            }
        }

        // Fungsi untuk menambah item ke keranjang
        function addItem(product) {
            // Cek apakah produk sudah ada di keranjang
            const existingItem = items.find(item => item.id === product.id);
            
            // Ambil data wholesale terbaru
            fetch(`transaction-resto.php?action=debug_wholesale&product_id=${product.id}`)
                .then(res => res.json())
                .then(data => {
                    console.log('Wholesale debug data:', data);
                    
                    // Update wholesale data dari debug
                    const wholesaleData = data.wholesale_data[0];
                    if (wholesaleData && wholesaleData.is_active === 1) {
                        product.wholesale = {
                            min_qty: parseInt(wholesaleData.min_qty),
                            price: parseFloat(wholesaleData.price)
                        };
                        console.log('Updated product wholesale data:', product.wholesale);
                    }
                    
                    // Proses penambahan/update item
                    processAddItem(product, existingItem);
                })
                .catch(err => {
                    console.error('Error fetching wholesale data:', err);
                    // Lanjutkan dengan data yang ada
                    processAddItem(product, existingItem);
                });
        }

        // Fungsi pemrosesan penambahan item setelah mendapat data wholesale terbaru
        function processAddItem(product, existingItem) {
            if (existingItem) {
                console.log('Updating existing item:', existingItem);
                
                if (existingItem.quantity < product.stock) {
                    const newQuantity = existingItem.quantity + 1;
                    
                    // Check wholesale price dengan data terbaru
                    if (product.wholesale) {
                        console.log('Checking wholesale conditions:', {
                            currentQty: newQuantity,
                            minQty: product.wholesale.min_qty,
                            wholesalePrice: product.wholesale.price,
                            normalPrice: product.selling_price
                        });
                        
                        if (newQuantity >= product.wholesale.min_qty) {
                            existingItem.price = parseFloat(product.wholesale.price);
                            existingItem.is_wholesale = true;
                            existingItem.original_price = existingItem.original_price || parseFloat(product.selling_price);
                            console.log('Applied wholesale price:', existingItem.price);
                            showToast(`Harga grosir diterapkan: Rp ${existingItem.price.toLocaleString()}`, 'success');
                        }
                    }
                    
                    existingItem.quantity = newQuantity;
                    existingItem.subtotal = existingItem.quantity * existingItem.price;
                    console.log('Updated item final state:', existingItem);
                } else {
                    showToast('Stok tidak cukup untuk produk ini', 'error');
                    return;
                }
            } else {
                console.log('Creating new item with wholesale check:', {
                    hasWholesale: !!product.wholesale,
                    wholesaleData: product.wholesale
                });
                
                if (product.stock > 0) {
                    let initialPrice = parseFloat(product.selling_price);
                    let isWholesale = false;
                    
                    // Check initial wholesale price dengan data terbaru
                    if (product.wholesale && 1 >= product.wholesale.min_qty) {
                        initialPrice = parseFloat(product.wholesale.price);
                        isWholesale = true;
                        console.log('Applied initial wholesale price:', initialPrice);
                        showToast(`Harga grosir diterapkan: Rp ${initialPrice.toLocaleString()}`, 'success');
                    }
                    
                    const newItem = {
                        id: product.id,
                        name: product.name,
                        price: initialPrice,
                        quantity: 1,
                        stock: product.stock,
                        subtotal: initialPrice,
                        unit_name: product.unit_name,
                        is_wholesale: isWholesale,
                        original_price: parseFloat(product.selling_price),
                        wholesale: product.wholesale
                    };
                    
                    console.log('Created new item:', newItem);
                    items.push(newItem);
                } else {
                    showToast('Produk ini sudah habis', 'error');
                    return;
                }
            }
            
            updateDisplay();
            console.log('Current items after update:', items);
        }

        // Update fungsi updateQuantity untuk menggunakan data wholesale terbaru
        function updateQuantity(index, change) {
            const item = items[index];
            
            // Ambil data wholesale terbaru
            fetch(`transaction-resto.php?action=debug_wholesale&product_id=${item.id}`)
                .then(res => res.json())
                .then(data => {
                    console.log('Wholesale debug data for quantity update:', data);
                    
                    // Update wholesale data dari debug
                    const wholesaleData = data.wholesale_data[0];
                    if (wholesaleData && wholesaleData.is_active === 1) {
                        item.wholesale = {
                            min_qty: parseInt(wholesaleData.min_qty),
                            price: parseFloat(wholesaleData.price)
                        };
                    }
                    
                    // Proses perubahan quantity
                    processQuantityUpdate(index, change, item);
                })
                .catch(err => {
                    console.error('Error fetching wholesale data:', err);
                    // Lanjutkan dengan data yang ada
                    processQuantityUpdate(index, change, item);
                });
        }

        // Fungsi pemrosesan perubahan quantity setelah mendapat data wholesale terbaru
        function processQuantityUpdate(index, change, item) {
            const newQuantity = item.quantity + change;
            
            console.log('Processing quantity update:', {
                item,
                newQuantity,
                change,
                wholesale: item.wholesale
            });
            
            if (newQuantity > 0 && newQuantity <= item.stock) {
                item.quantity = newQuantity;
                
                // Check wholesale price dengan data terbaru
                if (item.wholesale) {
                    console.log('Checking wholesale conditions for quantity update:', {
                        newQuantity,
                        minQty: item.wholesale.min_qty,
                        wholesalePrice: item.wholesale.price
                    });
                    
                    if (newQuantity >= item.wholesale.min_qty && !item.is_wholesale) {
                        item.price = parseFloat(item.wholesale.price);
                        item.is_wholesale = true;
                        showToast(`Harga grosir diterapkan: Rp ${item.price.toLocaleString()}`, 'success');
                    } else if (newQuantity < item.wholesale.min_qty && item.is_wholesale) {
                        item.price = parseFloat(item.original_price);
                        item.is_wholesale = false;
                        showToast(`Harga normal diterapkan: Rp ${item.price.toLocaleString()}`, 'success');
                    }
                }
                
                item.subtotal = item.quantity * item.price;
                updateDisplay();
            } else if (newQuantity <= 0) {
                removeItem(index);
            } else {
                showToast('Stok tidak cukup', 'error');
            }
        }

        // Fungsi untuk hapus item
        function removeItem(index) {
            items.splice(index, 1);
            updateDisplay();
            showToast('Produk dihapus', 'success');
        }

        // Perbaikan Scanner
        let codeReader = null;

        function showScannerModal() {
            document.getElementById('scannerModal').classList.remove('hidden');
            document.getElementById('scannerModal').classList.add('flex');
            initScanner();
        }

        async function initScanner() {
            try {
                codeReader = new ZXing.BrowserMultiFormatReader();
                
                // Request permission explicitly
                const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
                const video = document.getElementById('scanner');
                video.srcObject = stream;

                codeReader.decodeFromVideoDevice(null, 'scanner', (result, err) => {
                    if (result) {
                        // Stop scanning
                        stopScanner();
                        
                        // Get product by barcode
                        fetch(`transaction-resto.php?action=get_product&barcode=${result.text}`)
                            .then(response => response.json())
                            .then(product => {
                                if (product) {
                                    addItem(product);
                                } else {
                                    showToast('Produk tidak ditemukan', 'error');
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                showToast('Terjadi kesalahan saat mencari produk', 'error');
                            });
                    }
                });
            } catch (err) {
                console.error('Error accessing camera:', err);
                showToast('Tidak dapat mengakses kamera. Pastikan Anda mengizinkan akses kamera.', 'error');
            }
        }

        function stopScanner() {
            if (codeReader) {
                codeReader.reset();
                codeReader = null;
            }
            document.getElementById('scannerModal').classList.add('hidden');
            document.getElementById('scannerModal').classList.remove('flex');
        }

        // Fungsi untuk menampilkan toast
        function showToast(message, type = 'error') {
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg ${
                type === 'error' ? 'bg-red-500' : 'bg-green-500'
            } text-white flex items-center`;
            
            const icon = document.createElement('i');
            icon.className = `fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'} mr-2`;
            toast.appendChild(icon);
            
            const text = document.createElement('span');
            text.textContent = message;
            toast.appendChild(text);
            
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.classList.add('opacity-0', 'transition-opacity');
                setTimeout(() => toast.remove(), 300);
            }, 2700);
        }

        // Filter products with loading state
        function filterProducts(categoryId) {
            // Add active state to clicked button
            const buttons = document.querySelectorAll('.overflow-x-auto button');
            buttons.forEach(btn => {
                btn.classList.remove('bg-blue-500', 'text-white');
                btn.classList.add('bg-gray-100', 'text-gray-600');
            });
            event.currentTarget.classList.remove('bg-gray-100', 'text-gray-600');
            event.currentTarget.classList.add('bg-blue-500', 'text-white');

            // Show loading state
            const grid = document.getElementById('products_grid');
            grid.innerHTML = '<div class="col-span-full text-center py-8">Loading...</div>';

            fetch(`transaction-resto.php?action=filter_products&category_id=${categoryId}`)
                .then(response => response.json())
                .then(products => {
                    grid.innerHTML = products.map(product => `
                        <button onclick='addItem(${JSON.stringify(product)})'
                                class="p-4 border rounded-lg text-left transition-all hover:bg-gray-50 active:bg-gray-100 hover:border-gray-300 active:scale-95">
                            <h3 class="font-medium text-gray-800 mb-1">${product.name}</h3>
                            <div class="text-sm text-gray-500 mb-2">
                                ${product.category_name}
                            </div>
                            <div class="text-green-600 font-medium">
                                Rp ${Number(product.selling_price).toLocaleString()}
                            </div>
                        </button>
                    `).join('');
                });
        }

        // Initialize Select2
        $('#product_search').select2({
            ajax: {
                url: 'transaction-resto.php',
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
                                text: item.name,
                                data: item
                            };
                        })
                    };
                },
                cache: true
            },
            minimumInputLength: 1,
            placeholder: 'Ketik nama produk...',
            dropdownParent: $('body'),
            width: '100%'
        }).on('select2:select', function (e) {
            const item = e.params.data.data;
            addItem(item);
            $(this).val(null).trigger('change');
        });

        // Update product search result formatting
        function formatProductResult(product) {
            const photoUrl = product.photo && product.photo !== '' ? product.photo : '';
            const photoHtml = photoUrl 
                ? `<img src="${photoUrl}" alt="${product.name}" class="w-12 h-12 object-cover rounded-lg mr-3">`
                : `<div class="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center text-gray-500 mr-3">
                       <i class="fas fa-image"></i>
                   </div>`;
            
            return $(`
                <div class="flex items-center">
                    ${photoHtml}
                    <div>
                        <div class="font-medium">${product.name}</div>
                        <div class="text-sm text-gray-500">
                            Stok: ${product.stock} • Rp ${Number(product.selling_price).toLocaleString()}
                        </div>
                    </div>
                </div>
            `);
        }

        // Update product search configuration
        $('#product_search').select2({
            ajax: {
                url: 'transaction-resto.php',
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
                        results: data.map(product => ({
                            id: product.id,
                            text: product.name,
                            data: product
                        }))
                    };
                },
                cache: true
            },
            minimumInputLength: 1,
            templateResult: formatProductResult,
            placeholder: 'Cari produk...',
            language: {
                searching: function() {
                    return "Mencari...";
                },
                noResults: function() {
                    return "Produk tidak ditemukan";
                }
            }
        }).on('select2:select', function(e) {
            const product = e.params.data.data;
            addProductToTransaction(product);
        });

        // Payment functions
        function showPaymentModal() {
            document.getElementById('paymentModal').classList.remove('hidden');
            document.getElementById('paymentModal').classList.add('flex');
            document.getElementById('payment_total').textContent = `Rp ${total.toLocaleString()}`;
            document.getElementById('paid_amount').value = '';
            document.getElementById('change_amount').textContent = 'Rp 0';
            document.getElementById('paid_amount').focus();
        }

        function hidePaymentModal() {
            document.getElementById('paymentModal').classList.add('hidden');
            document.getElementById('paymentModal').classList.remove('flex');
        }

        function calculateChange(value) {
            const paid = parseFloat(value.replace(/[^0-9]/g, '')) || 0;
            const change = paid - total;
            document.getElementById('change_amount').textContent = `Rp ${Math.max(0, change).toLocaleString()}`;
            document.getElementById('process_button').disabled = paid < total;
        }

        // Tambahkan fungsi printOptions
        function printOptions(invoice_number) {
            window.location.href = `print?invoice_number=${invoice_number}`;
        }

        // Perbaiki fungsi processTransaction
        async function processTransaction() {
            try {
                // Validasi items
                if (items.length === 0) {
                    showToast('Tidak ada item untuk diproses', 'error');
                    return;
                }

                // Ambil dan validasi pembayaran
                const paidInput = document.getElementById('paid_amount');
                const paidValue = paidInput.value.replace(/[^0-9]/g, '');
                const paid = parseFloat(paidValue);
                const change = paid - total;

                if (isNaN(paid) || paid < total) {
                    showToast('Jumlah pembayaran tidak valid', 'error');
                    return;
                }

                // Siapkan data transaksi
                const transactionData = {
                    items: items.map(item => ({
                        id: parseInt(item.id),
                        quantity: parseInt(item.quantity),
                        price: parseFloat(item.price),
                        subtotal: parseFloat(item.subtotal),
                        is_wholesale: item.is_wholesale ? 1 : 0
                    })),
                    total: parseFloat(total),
                    paid_amount: paid,
                    change_amount: change
                };

                // Debug log
                console.log('Preparing to send transaction data:', {
                    rawData: transactionData,
                    jsonString: JSON.stringify(transactionData)
                });

                // Kirim request dengan body yang valid
                const response = await fetch('transaction-resto.php?action=process_transaction', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(transactionData),
                    credentials: 'same-origin'
                });

                // Debug log response
                console.log('Response received:', {
                    status: response.status,
                    statusText: response.statusText,
                    headers: Object.fromEntries(response.headers.entries())
                });

                // Pastikan response valid
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Invalid response format');
                }

                const result = await response.json();
                console.log('Transaction result:', result);

                if (result.success) {
                    // Proses stock update
                    const stockUpdates = await Promise.all(items.map(item => 
                        fetch('stock-update.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                product_id: parseInt(item.id),
                                quantity: parseInt(item.quantity),
                                type: 'out',
                                description: `Penjualan #${result.invoice_number}`
                            })
                        }).then(res => res.json())
                    ));

                    console.log('Stock updates completed:', stockUpdates);
                    
                    showToast('Transaksi berhasil', 'success');
                    printOptions(result.invoice_number);
                    resetTransaction();
                } else {
                    console.error('Transaction failed:', result);
                    showToast(result.message || 'Gagal memproses transaksi', 'error');
                }
            } catch (error) {
                console.error('Error in processTransaction:', {
                    error: error,
                    message: error.message,
                    stack: error.stack
                });
                showToast(`Terjadi kesalahan: ${error.message}`, 'error');
            }
        }

        // Tambahkan fungsi resetTransaction
        function resetTransaction() {
            items = [];
            total = 0;
            updateDisplay();
            hidePaymentModal();
        }

        // Tambahkan event listener untuk form submit
        document.getElementById('payment_form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            processTransaction();
        });

        // Tambahkan validasi input pembayaran
        document.getElementById('paid_amount')?.addEventListener('input', function(e) {
            const value = e.target.value.replace(/[^0-9]/g, '');
            const numValue = parseInt(value) || 0;
            
            // Format sebagai currency
            e.target.value = new Intl.NumberFormat('id-ID').format(numValue);
            
            // Update tombol process
            const processButton = document.getElementById('process_button');
            if (processButton) {
                processButton.disabled = numValue < total;
            }
            
            // Update kembalian
            calculateChange(numValue);
        });

        // Helper function untuk format currency
        function formatCurrency(amount) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(amount);
        }
    </script>
</body>
</html>