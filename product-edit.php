<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/check_session.php';
require_once 'includes/tracking_helper.php';
require_once 'includes/image_helper.php';

$message = '';
$product = null;
$id = Encryption::decode($_GET['id']) ?? 0;

$db = new Database();
$conn = $db->getConnection();

// Setelah koneksi database dan session dibuat
logPageAccess($conn);

// Get product data
try {
    $stmt = $conn->prepare("

        SELECT a.*, pp.photo_name FROM products a 
        LEFT JOIN product_photo pp ON a.id = pp.product_id 
        WHERE a.id = ? AND a.store_id = ?
    ");
    $stmt->execute([$id, $_SESSION['store_id']]);
    $product = $stmt->fetch();

    if (!$product) {
        header("Location: products.php");
        exit;
    }
} catch (PDOException $e) {
    header("Location: products.php");
    exit;
}

// Get categories for dropdown
$stmt = $conn->prepare("SELECT id, name FROM categories WHERE is_active = 1 AND store_id = ? ORDER BY name");
$stmt->execute([$_SESSION['store_id']]);
$categories = $stmt->fetchAll();

// Get units for dropdown
$stmt = $conn->prepare("SELECT id, name FROM units WHERE is_active = 1 AND store_id = ? ORDER BY name");
$stmt->execute([$_SESSION['store_id']]);
$units = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = input_data($_POST['name']) ?? '';
    $barcode = input_data($_POST['barcode']) ?? '';
    $cost_price = (float) str_replace(['Rp', '.', ','], '', $_POST['cost_price']);
    $selling_price = (float) str_replace(['Rp', '.', ','], '', $_POST['selling_price']);
    $unit_id = input_data($_POST['unit_id']) ?? '';
    $category_id = input_data($_POST['category_id']) ?? '';
    $stock = input_data($_POST['stock']) ?? 0;
    $action = input_data($_POST['action']) ?? 'update';
    
    // Photo upload validation
    $photo_path = $product['photo']; // Retain existing photo by default
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        $max_size = 3 * 1024 * 1024; // 3 MB

        $file_type = $_FILES['photo']['type'];
        $file_size = $_FILES['photo']['size'];

        if (!in_array($file_type, $allowed_types)) {
            $message = 'Hanya file JPG, PNG, JPEG, dan WEBP yang diizinkan.';
        } elseif ($file_size > $max_size) {
            $message = 'Ukuran foto maksimal 3 MB.';
        } else {
            // Create upload directory if not exists
            $upload_dir = 'uploads/products/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            //delete existing photo
            unlink($upload_dir.$product['photo_name']);
            
            // Generate unique filename
            $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $photo_filename = uniqid('product_') . '.' . $file_extension;
            $photo_path = $upload_dir . $photo_filename;

            // Move uploaded file
            $compressed_photo_path = $upload_dir . $photo_filename;
            if (ImageCompressor::compress($_FILES['photo']['tmp_name'], $compressed_photo_path)) {
                $photo_path = $compressed_photo_path;

                //update product_photo
                $stmt = $conn->prepare("UPDATE product_photo SET photo_name = ? WHERE product_id = ? LIMIT 1");
                $stmt->execute([$photo_path, $id]);

            } else {
                $message = 'Gagal mengompresi foto.';
                $photo_path = $product['photo']; // Revert to existing photo
            }
        }
    }
    
    // Rest of the existing code remains the same
    try {
        if ($action === 'delete') {
            // Soft delete product
            $stmt = $conn->prepare("
                UPDATE products SET is_active = 0 
                WHERE id = ? AND store_id = ?
            ");
                
            if ($stmt->execute([$id, $_SESSION['store_id']])) {
                header("Location: products.php");
                exit;
            }
            
        } else {
            if (!empty($name) && !empty($unit_id) && !empty($category_id) && empty($message)) {
                //check if product name already exists
                $stmt = $conn->prepare("SELECT COUNT(id) FROM products WHERE store_id = ? AND name = ? AND id != ?");
                $stmt->execute([$_SESSION['store_id'], $name, $id]);
                $count = $stmt->fetchColumn();
                if ($count > 0) {
                    $message = 'Nama produk sudah ada.';
                }else{
                    $stmt = $conn->prepare("
                        UPDATE products 
                        SET name = ?, 
                            barcode = ?,
                            cost_price = ?,
                            selling_price = ?,
                            unit_id = ?,
                            category_id = ?,
                            stock = ?
                        WHERE id = ? AND store_id = ?
                    ");
                    
                    if ($stmt->execute([
                        $name,
                        $barcode,
                        $cost_price,
                        $selling_price,
                        $unit_id,
                        $category_id,
                        $stock,
                        $id,
                        $_SESSION['store_id']
                    ])) {
                        // Update stock history
                        $stmt = $conn->prepare("
                            INSERT INTO stock_history (product_id, type, quantity, description, created_by, store_id)
                            VALUES (?, 'in', ?, 'Update qty', ?, ?)
                        ");
                        $stmt->execute([$id, $stock, $_SESSION['user_id'], $_SESSION['store_id']]);

                        header("Location: products.php");
                        exit;
                    }
                }
            } else {
                $message = 'Semua field harus diisi kecuali barcode.';
            }
        }
    } catch (PDOException $e) {
        $message = 'Terjadi kesalahan. Silakan coba lagi.';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Edit Produk</title>
    <?php include 'includes/components.php'; ?>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
    </style>
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
                    <h1 class="text-lg font-semibold text-gray-800">Edit Produk</h1>
                    <button onclick="confirmDelete()" class="text-red-500 hover:text-red-600">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Form -->
        <div class="max-w-7xl mx-auto px-4 py-6">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <?php if ($message): ?>
                <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="space-y-4" id="productForm">
                    <input type="hidden" name="action" id="formAction" value="update">
                    
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Nama Produk</label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               required
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                               value="<?php echo htmlspecialchars($product['name']); ?>">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="barcode" class="block text-sm font-medium text-gray-700">Barcode</label>
                            <div class="mt-1 flex rounded-lg shadow-sm">
                                <input type="text" 
                                       id="barcode" 
                                       name="barcode" 
                                       class="flex-1 rounded-l-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                                       value="<?php echo htmlspecialchars($product['barcode'] ?? ''); ?>">
                                <button type="button" 
                                        onclick="startScanner()"
                                        class="inline-flex items-center px-4 py-2 border border-l-0 border-gray-300 rounded-r-lg bg-gray-50 text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-barcode"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label for="category_id" class="block text-sm font-medium text-gray-700">Kategori</label>
                            <select id="category_id" 
                                    name="category_id" 
                                    required
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                    <?php echo ($product['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="cost_price" class="block text-sm font-medium text-gray-700">Harga Modal</label>
                            <div class="mt-1 relative rounded-lg shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">Rp</span>
                                </div>
                                <input type="text" 
                                       id="cost_price" 
                                       name="cost_price" 
                                       required
                                       class="block w-full pl-12 rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                                       value="<?php echo number_format($product['cost_price'], 0, ',', '.'); ?>"
                                       onkeyup="formatNumber(this)">
                            </div>
                        </div>

                        <div>
                            <label for="selling_price" class="block text-sm font-medium text-gray-700">Harga Jual</label>
                            <div class="mt-1 relative rounded-lg shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">Rp</span>
                                </div>
                                <input type="text" 
                                       id="selling_price" 
                                       name="selling_price" 
                                       required
                                       class="block w-full pl-12 rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                                       value="<?php echo number_format($product['selling_price'], 0, ',', '.'); ?>"
                                       onkeyup="formatNumber(this)">
                            </div>
                        </div>

                        <div>
                            <label for="unit_id" class="block text-sm font-medium text-gray-700">Satuan</label>
                            <select id="unit_id" 
                                    name="unit_id" 
                                    required
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Pilih Satuan</option>
                                <?php foreach ($units as $unit): ?>
                                <option value="<?php echo $unit['id']; ?>"
                                    <?php echo ($product['unit_id'] == $unit['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($unit['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="stock" class="block text-sm font-medium text-gray-700">Jumlah Barang</label>
                            <input type="number" 
                                   id="stock" 
                                   name="stock" 
                                   required
                                   min="0"
                                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   value="<?php echo htmlspecialchars($product['stock']); ?>">
                        </div>

                        <div>
                            <label for="photo" class="block text-sm font-medium text-gray-700">Foto Produk</label>
                            <div class="mt-1 flex items-center">
                                <input type="file" 
                                       id="photo" 
                                       name="photo" 
                                       accept=".jpg,.jpeg,.png,.webp" 
                                       class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-full file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-medium hover:file:bg-blue-100">
                                <p class="ml-2 text-xs text-gray-500">Maks. 3 MB (JPG, PNG, JPEG, WEBP)</p>
                            </div>
                            
                            <!-- Image Preview -->
                            <div id="imagePreview" class="mt-2">
                                <?php if (!empty($product['photo_name']) && file_exists($product['photo_name'])): ?>
                                <img 
                                    src="<?php echo htmlspecialchars($product['photo_name']); ?>" 
                                    alt="Foto Produk" 
                                    class="h-32 w-32 object-cover rounded-lg"
                                >
                                <?php else: ?>
                                <div class="h-32 w-32 bg-gray-200 rounded-lg flex items-center justify-center text-gray-500">
                                    <i class="fas fa-image"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <a href="products" 
                           class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-500">
                            Batal
                        </a>
                        <a href="#deleteModal"  
                           onclick="confirmDelete()" 
                           class="px-4 py-2 text-sm bg-red-500 font-medium text-white rounded-lg hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                            Hapus
                        </a>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-500 text-white text-sm font-medium rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 m-4 max-w-sm w-full">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Hapus Produk</h3>
            <p class="text-gray-500 mb-6">Apakah Anda yakin ingin menghapus produk ini? Tindakan ini tidak dapat dibatalkan.</p>
            <div class="flex justify-end space-x-3">
                <button onclick="hideDeleteModal()" 
                        class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-500">
                    Batal
                </button>
                <button onclick="deleteProduct()" 
                        class="px-4 py-2 bg-red-500 text-white text-sm font-medium rounded-lg hover:bg-red-600">
                    Hapus Saja
                </button>
            </div>
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
            <div id="scanner-container">
                <video id="scanner" class="w-full"></video>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/@zxing/library@latest"></script>
    <?php include 'includes/footer.php'; ?>
    <script>

        function confirmDelete() {
            const modal = document.getElementById('deleteModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function hideDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function deleteProduct() {
            const form = document.getElementById('productForm');
            const actionInput = document.getElementById('formAction');
            actionInput.value = 'delete';
            form.submit();
        }

        // Format number with thousand separator
        function formatNumber(input) {
            let value = input.value.replace(/[^\d]/g, '');
            input.value = new Intl.NumberFormat('id-ID').format(value);
            
            input.setAttribute('data-value', value);
        }

        // Barcode Scanner
        let selectedDeviceId;
        const codeReader = new ZXing.BrowserMultiFormatReader();

        function startScanner() {
            const modal = document.getElementById('scannerModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            codeReader.listVideoInputDevices()
                .then(videoInputDevices => {
                    selectedDeviceId = videoInputDevices[0].deviceId;
                    if (videoInputDevices.length > 1) {
                        selectedDeviceId = videoInputDevices[1].deviceId;
                    }
                    
                    codeReader.decodeFromVideoDevice(selectedDeviceId, 'scanner', (result, err) => {
                        if (result) {
                            document.getElementById('barcode').value = result.text;
                            stopScanner();
                        }
                        if (err && !(err instanceof ZXing.NotFoundException)) {
                            console.error(err);
                        }
                    });
                })
                .catch(err => console.error(err));
        }

        function stopScanner() {
            codeReader.reset();
            const modal = document.getElementById('scannerModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Close modals when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideDeleteModal();
            }
        });

        document.getElementById('scannerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                stopScanner();
            }
        });

        // Image Preview
        document.getElementById('photo').addEventListener('change', function(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('imagePreview');
            const previewContainer = preview.querySelector('img') || document.createElement('img');

            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (!previewContainer.parentElement) {
                        previewContainer.className = 'h-32 w-32 object-cover rounded-lg';
                        preview.innerHTML = '';
                        preview.appendChild(previewContainer);
                    }
                    previewContainer.src = e.target.result;
                }
                reader.readAsDataURL(file);
            } else {
                // Reset to existing image or placeholder
                <?php if (!empty($product['photo']) && file_exists($product['photo'])): ?>
                preview.innerHTML = `
                    <img 
                        src="<?php echo htmlspecialchars($product['photo']); ?>" 
                        alt="Foto Produk" 
                        class="h-32 w-32 object-cover rounded-lg"
                    >
                `;
                <?php else: ?>
                preview.innerHTML = `
                    <div class="h-32 w-32 bg-gray-200 rounded-lg flex items-center justify-center text-gray-500">
                        <i class="fas fa-image"></i>
                    </div>
                `;
                <?php endif; ?>
            }
        });
    </script>
</body>
</html>