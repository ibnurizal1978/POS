<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/auth_helper.php';
require_once 'includes/check_session.php';
require_once 'includes/tracking_helper.php';

// Check role
requireRole(['owner', 'admin']);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Initialize database connection
$db = new Database();
$conn = $db->getConnection();

// Get store_id from session
$store_id = $_SESSION['store_id'];

// Setelah koneksi database dan session dibuat
logPageAccess($conn);

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // items per page
$offset = ($page - 1) * $limit;

// Build base query for total count
$countQuery = "
    SELECT COUNT(id) as total 
    FROM products p 
    WHERE p.is_active = 1 AND p.store_id = ?";

// Build base query for data
$query = "
    SELECT p.*, c.name as category_name, u.name as unit_name, pp.photo_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN units u ON p.unit_id = u.id
    LEFT JOIN product_photo pp ON p.id = pp.product_id
    WHERE p.is_active = 1 AND p.store_id = ?";

// Add search condition if search parameter exists
$params = [$store_id];
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $countQuery .= " AND (p.name LIKE ? OR p.barcode LIKE ?)";
    $query .= " AND (p.name LIKE ? OR p.barcode LIKE ?)";
    $params[] = $search;
    $params[] = $search;
}

// Get total records for pagination
$stmt = $conn->prepare($countQuery);
$stmt->execute($params);
$total_records = $stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Add sorting and limit to data query
$query .= " ORDER BY p.name ASC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

// Get products
$stmt = $conn->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Function to get single product with complete info
function getProductDetail($product_id, $store_id) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT 
            p.*,
            c.name as category_name,
            u.name as unit_name,
            pp.photo_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN units u ON p.unit_id = u.id
        LEFT JOIN product_photo pp ON p.id = pp.product_id
        WHERE p.is_active = 1 AND p.id = ? AND p.store_id = ?
    ");
    
    $stmt->execute([$product_id, $store_id]);
    return $stmt->fetch();
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if (isset($_GET['search'])) {
        // Search products
        $search = $_GET['search'];
        
        $stmt = $conn->prepare("
            SELECT 
                p.id,
                p.name,
                p.barcode,
                p.stock,
                p.selling_price,
                p.cost_price,
                u.name as unit_name,
                pp.photo_name
            FROM products p
            LEFT JOIN units u ON p.unit_id = u.id
            LEFT JOIN product_photo pp ON p.id = pp.product_id
            WHERE p.store_id = ? 
            AND p.is_active = 1
            AND (p.name LIKE ? OR p.barcode LIKE ?)
            ORDER BY p.name ASC
            LIMIT 10
        ");
        
        $search_term = '%' . $search . '%';
        $stmt->execute([$store_id, $search_term, $search_term]);
        $results = $stmt->fetchAll();
        
        echo json_encode($results);
        exit;
    } else if (isset($_GET['id'])) {
        // Get single product
        $product = getProductDetail($_GET['id'], $_SESSION['store_id']);
        echo json_encode($product);
        exit;
    }
}

// Get initial products or filtered product
if (isset($_GET['selected_id'])) {
    // Show only selected product
    $stmt = $conn->prepare("
        SELECT p.*, c.name as category_name, u.name as unit_name, pp.photo_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN units u ON p.unit_id = u.id
        LEFT JOIN product_photo pp ON p.id = pp.product_id
        WHERE p.is_active = 1 AND p.id = ? AND p.store_id = ?
    ");
    $stmt->execute([$_GET['selected_id'], $store_id]);
    $products = [$stmt->fetch()];
} else {
    // Products sudah di-query sebelumnya, tidak perlu query ulang
    // $products sudah berisi data dari pagination query
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Produk</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />    
    <?php include 'includes/components.php'; ?>
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
                    <h1 class="text-lg font-semibold text-gray-800">Daftar Produk</h1>
                    <a href="product-add" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-plus"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Products List -->
        <div class="max-w-7xl mx-auto px-4 py-6">
            <div class="bg-white rounded-lg shadow-sm p-4">
                <!-- Product Search -->
                <div>
                    <select id="product_search" class="w-full rounded-lg border-gray-300">
                        <option value="">Ketik nama atau barcode produk...</option>
                    </select>
                </div>
            </div>
            <br/>

            <div class="bg-white rounded-lg shadow-sm">
                <div id="products-container" class="divide-y divide-gray-100">
                    <?php if (empty($products)): ?>
                    <!-- Empty State Message -->
                    <div class="p-8 text-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-box text-2xl text-gray-400"></i>
                        </div>
                        <h3 class="text-gray-500 font-medium mb-1">Belum ada produk</h3>
                        <p class="text-sm text-gray-400 mb-4">Silahkan tambah produk dengan cara klik tombol + diatas</p>
                        <a href="product-add" class="inline-flex items-center text-blue-500 hover:text-blue-600 font-medium">
                            <i class="fas fa-plus mr-2"></i>
                            Tambah Produk
                        </a>
                    </div>
                    <?php else: ?>
                    <?php foreach ($products as $product): ?>
                    <div class="p-4 hover:bg-gray-50">
                        <div class="flex justify-between items-start">
                            <div class="flex items-center space-x-4">
                                <!-- Add photo thumbnail -->
                                <div class="w-16 h-16 flex-shrink-0">
                                    <?php if (!empty($product['photo_name']) && file_exists($product['photo_name'])): ?>
                                    <img 
                                        src="<?php echo htmlspecialchars($product['photo_name']); ?>" 
                                        alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                        class="w-full h-full object-cover rounded-lg"
                                    >
                                    <?php else: ?>
                                    <div class="w-full h-full bg-gray-200 rounded-lg flex items-center justify-center text-gray-500">
                                        <i class="fas fa-image"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div>
                                    <h3 class="font-medium text-gray-800"><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <div class="mt-1 text-sm text-gray-500">
                                        <?php echo htmlspecialchars($product['category_name'] ?? 'Tanpa Kategori'); ?> • 
                                        <?php echo number_format($product['stock'], 0, ",", "."); ?> <?php echo htmlspecialchars($product['unit_name']); ?>
                                    </div>
                                    <?php if (!empty($product['barcode'])): ?>
                                    <div class="mt-1 text-xs text-gray-400">
                                        <i class="fas fa-barcode mr-1"></i>
                                        <?php echo htmlspecialchars($product['barcode']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="text-right">
                                <div class="text-green-600 font-medium">
                                    Rp <?php echo number_format($product['selling_price'], 0, ",", "."); ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    Modal: Rp <?php echo number_format($product['cost_price'], 0, ",", "."); ?>
                                </div>
                                <a href="product-edit.php?id=<?php echo Encryption::encode($product['id']); ?>" 
                                   class="inline-block mt-2 text-blue-500 hover:text-blue-600">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Loading Indicator -->
                <div id="loading" class="hidden p-4 text-center text-gray-500">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span class="ml-2">Memuat...</span>
                </div>
            </div>

            <br/><br/>
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="flex items-center justify-center space-x-1 py-4 bg-white border-t border-gray-200">
                <!-- First Page -->
                <?php if ($page > 1): ?>
                    <a href="?page=1<?= isset($_GET['search']) ? '&search='.$_GET['search'] : '' ?>" 
                       class="px-3 py-2 text-gray-500 bg-white rounded-lg hover:bg-gray-100">
                        <span class="sr-only">First</span>
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                <?php else: ?>
                    <span class="px-3 py-2 text-gray-300 bg-white rounded-lg">
                        <i class="fas fa-angle-double-left"></i>
                    </span>
                <?php endif; ?>

                <!-- Previous Page -->
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page-1 ?><?= isset($_GET['search']) ? '&search='.$_GET['search'] : '' ?>" 
                       class="px-3 py-2 text-gray-500 bg-white rounded-lg hover:bg-gray-100">
                        <span class="sr-only">Previous</span>
                        <i class="fas fa-angle-left"></i>
                    </a>
                <?php else: ?>
                    <span class="px-3 py-2 text-gray-300 bg-white rounded-lg">
                        <i class="fas fa-angle-left"></i>
                    </span>
                <?php endif; ?>

                <!-- Page Numbers -->
                <?php
                $start = max(1, min($page - 2, $total_pages - 4));
                $end = min($total_pages, max(5, $page + 2));
                
                if ($start > 1): ?>
                    <a href="?page=1<?= isset($_GET['search']) ? '&search='.$_GET['search'] : '' ?>" 
                       class="px-3 py-2 text-gray-500 bg-white rounded-lg hover:bg-gray-100">1</a>
                        <?php if ($start > 2): ?>
                            <span class="px-3 py-2 text-gray-500">...</span>
                        <?php endif; ?>
                    <?php endif;

                for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="px-3 py-2 text-white bg-blue-500 rounded-lg"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?><?= isset($_GET['search']) ? '&search='.$_GET['search'] : '' ?>" 
                           class="px-3 py-2 text-gray-500 bg-white rounded-lg hover:bg-gray-100"><?= $i ?></a>
                    <?php endif;
                endfor;

                if ($end < $total_pages): ?>
                    <?php if ($end < $total_pages - 1): ?>
                        <span class="px-3 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=<?= $total_pages ?><?= isset($_GET['search']) ? '&search='.$_GET['search'] : '' ?>" 
                       class="px-3 py-2 text-gray-500 bg-white rounded-lg hover:bg-gray-100"><?= $total_pages ?></a>
                <?php endif; ?>

                <!-- Next Page -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page+1 ?><?= isset($_GET['search']) ? '&search='.$_GET['search'] : '' ?>" 
                       class="px-3 py-2 text-gray-500 bg-white rounded-lg hover:bg-gray-100">
                        <span class="sr-only">Next</span>
                        <i class="fas fa-angle-right"></i>
                    </a>
                <?php else: ?>
                    <span class="px-3 py-2 text-gray-300 bg-white rounded-lg">
                        <i class="fas fa-angle-right"></i>
                    </span>
                <?php endif; ?>

                <!-- Last Page -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $total_pages ?><?= isset($_GET['search']) ? '&search='.$_GET['search'] : '' ?>" 
                       class="px-3 py-2 text-gray-500 bg-white rounded-lg hover:bg-gray-100">
                        <span class="sr-only">Last</span>
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php else: ?>
                    <span class="px-3 py-2 text-gray-300 bg-white rounded-lg">
                        <i class="fas fa-angle-double-right"></i>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://unpkg.com/@zxing/library@latest"></script>
    <script>
        // Wrap everything in a DOMContentLoaded event to ensure elements exist
        document.addEventListener('DOMContentLoaded', function() {
            const $productSearch = $('#product_search');
            
            // Ensure Select2 is fully loaded before initialization
            if (typeof $productSearch.select2 !== 'function') {
                console.error('Select2 not loaded');
                return;
            }

            // Add clear button with safe insertion
            function addClearButton() {
                // Remove any existing clear buttons
                const existingClearButton = document.querySelector('.products-clear-button');
                if (existingClearButton) {
                    existingClearButton.remove();
                }

                // Create new clear button
                const clearButton = document.createElement('button');
                clearButton.className = 'products-clear-button text-center text-gray-600 px-3 py-1 rounded text-sm mt-2 w-full';
                clearButton.innerHTML = 'Tampilkan Semua Produk';
                clearButton.addEventListener('click', function() {
                    window.location.href = 'products.php';
                });

                // Try multiple ways to insert the button
                const container = document.querySelector('.select2-container');
                const searchContainer = document.querySelector('.select2-search');
                
                if (container) {
                    container.after(clearButton);
                } else if (searchContainer) {
                    searchContainer.after(clearButton);
                } else {
                    // Fallback: append to the parent of the select element
                    $productSearch.parent().append(clearButton);
                }
            }

            // Initialize Select2 with error handling
            try {
                $productSearch.select2({
                    ajax: {
                        url: 'products.php',
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return {
                                search: params.term,
                                ajax: 1
                            };
                        },
                        processResults: function (data) {
                            return {
                                results: data.map(function(item) {
                                    return {
                                        id: item.id,
                                        text: `${item.name} (${item.barcode || 'No Barcode'}) - Stok: ${item.stock} ${item.unit_name}`,
                                        data: item
                                    };
                                })
                            };
                        },
                        cache: true
                    },
                    minimumInputLength: 1, // Reduced from 3 to 1
                    placeholder: 'Ketik nama atau barcode produk...',
                    templateResult: function(result) {
                        if (!result.id) { return result.text; }
                        
                        const item = result.data;
                        const photoUrl = item.photo && item.photo !== '' ? item.photo : '';
                        const photoHtml = photoUrl 
                            ? `<img src="${photoUrl}" alt="${item.name}" class="w-10 h-10 object-cover rounded-lg mr-3">`
                            : `<div class="w-10 h-10 bg-gray-200 rounded-lg flex items-center justify-center text-gray-500 mr-3">
                                   <i class="fas fa-image"></i>
                               </div>`;
                        
                        return $(`
                            <div class="flex items-center">
                                ${photoHtml}
                                <div>
                                    <div class="font-medium">${item.name}</div>
                                    <div class="text-sm text-gray-500">
                                        Stok: ${item.stock} • Rp ${Number(item.selling_price).toLocaleString()}
                                    </div>
                                </div>
                            </div>
                        `);
                    }
                }).on('select2:select', function (e) {
                    const selectedProduct = e.params.data.data;
                    
                    // Clear existing products and show only selected product
                    const container = document.getElementById('products-container');
                    container.innerHTML = `
                        <div class="p-4 hover:bg-gray-50">
                            <div class="flex justify-between items-start">
                                <div class="flex items-center space-x-4">
                                    <!-- Add photo thumbnail -->
                                    <div class="w-16 h-16 flex-shrink-0">
                                        ${selectedProduct.photo && selectedProduct.photo !== '' ? `
                                        <img 
                                            src="${selectedProduct.photo}" 
                                            alt="${selectedProduct.name}" 
                                            class="w-full h-full object-cover rounded-lg"
                                        >
                                        ` : `
                                        <div class="w-full h-full bg-gray-200 rounded-lg flex items-center justify-center text-gray-500">
                                            <i class="fas fa-image"></i>
                                        </div>
                                        `}
                                    </div>
                                    
                                    <div>
                                        <h3 class="font-medium text-gray-800">${selectedProduct.name}</h3>
                                        <div class="mt-1 text-sm text-gray-500">
                                            ${selectedProduct.category_name || 'Tanpa Kategori'} • 
                                            ${Number(selectedProduct.stock).toLocaleString()} ${selectedProduct.unit_name}
                                        </div>
                                        ${selectedProduct.barcode ? `
                                        <div class="mt-1 text-xs text-gray-400">
                                            <i class="fas fa-barcode mr-1"></i>
                                            ${selectedProduct.barcode}
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                                
                                <div class="text-right">
                                    <div class="text-green-600 font-medium">
                                        Rp ${Number(selectedProduct.selling_price).toLocaleString()}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        Modal: Rp ${Number(selectedProduct.cost_price).toLocaleString()}
                                    </div>
                                    <a href="product-edit.php?id=<?php echo Encryption::encode('${selectedProduct.id}'); ?>" 
                                       class="inline-block mt-2 text-blue-500 hover:text-blue-600">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Update URL to maintain state on refresh
                    const newUrl = new URL(window.location.href);
                    newUrl.searchParams.set('selected_id', selectedProduct.id);
                    window.history.pushState({}, '', newUrl);
                    
                    // Clear select2 input
                    $(this).val(null).trigger('change');
                });

                // Add clear button after a short delay to ensure Select2 is fully initialized
                setTimeout(addClearButton, 500);
            } catch (error) {
                console.error('Error initializing Select2:', error);
            }

            // Focus handling functions
            function focusSearchInput() {
                setTimeout(function() {
                    const searchField = $('.select2-container--open .select2-search__field')[0];
                    if (searchField) {
                        searchField.focus();
                    }
                }, 10);
            }

            // Event listeners with error handling
            try {
                $(document).on('mouseup', '.select2-container, .select2-selection', function(e) {
                    $productSearch.select2('open');
                    focusSearchInput();
                    e.preventDefault();
                    e.stopPropagation();
                });

                $productSearch.on('select2:opening select2:open', function() {
                    focusSearchInput();
                });

                $(document).on('click', '.select2-search__field', function(e) {
                    e.stopPropagation();
                    this.focus();
                });
            } catch (error) {
                console.error('Error setting up event listeners:', error);
            }
        });
    </script>
    
</body>
</html>