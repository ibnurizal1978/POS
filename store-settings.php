<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/auth_helper.php';
require_once 'includes/premium_helper.php';
require_once 'includes/check_session.php';
require_once 'includes/tracking_helper.php';

// Cek role user
if (!in_array($_SESSION['role'], ['admin', 'owner'])) {
    header('Location: dashboard');
    exit;
}

// Cek apakah store berlangganan fitur ini
$has_premium = isFeatureSubscribed($_SESSION['store_id'], 'STORE_SETTINGS_PRO');


$db = new Database();
$conn = $db->getConnection();

// Setelah koneksi database dan session dibuat
logPageAccess($conn);

// Ambil data toko
function getStoreData($store_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM stores WHERE id = ?");
    $stmt->execute([$store_id]);
    return $stmt->fetch();
}

// Ambil data toko sebelum memproses form
$store = getStoreData($_SESSION['store_id']);

// Update data toko
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {

        $name = input_data($_POST['name']);
        $address = input_data($_POST['address']);
        $phone = input_data($_POST['phone']);
        $email = input_data($_POST['email']);
        $receipt_info = input_data($_POST['receipt_info']);

        // Handle file upload
        $logo_path = $store['logo'] ?? null; // Default ke logo yang sudah ada atau null
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            // Validasi ukuran file (5MB)
            if ($_FILES['logo']['size'] > 5 * 1024 * 1024) {
                throw new Exception("Ukuran file logo tidak boleh lebih dari 5MB");
            }

            // Validasi tipe file
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $_FILES['logo']['tmp_name']);
            if (!in_array($mime_type, $allowed_types)) {
                throw new Exception("Tipe file harus JPG, PNG, atau GIF");
            }

            // Generate nama file unik
            $extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $extension;
            $upload_path = 'uploads/logos/' . $filename;

            // Buat direktori jika belum ada
            if (!file_exists('uploads/logos')) {
                mkdir('uploads/logos', 0777, true);
            }

            // Pindahkan file
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                // Hapus logo lama jika ada
                if ($store['logo'] && file_exists($store['logo'])) {
                    unlink($store['logo']);
                }
                $logo_path = $upload_path;
            }
        }

        $stmt = $conn->prepare("
            UPDATE stores 
            SET name = ?,
                address = ?,
                phone = ?,
                email = ?,
                logo = ?,
                receipt_info = ?,
                updated_at = UTC_TIMESTAMP()
            WHERE id = ?
        ");

        $stmt->execute([
            $name,
            $address,
            $phone,
            $email,
            $logo_path,
            $receipt_info,
            $_SESSION['store_id']
        ]);

        $success_message = "Data toko berhasil diperbarui";
        
        // Refresh data toko setelah update
        $store = getStoreData($_SESSION['store_id']);
    } catch (PDOException $e) {
        $error_message = "Gagal memperbarui data toko: " . $e->getMessage();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Jika belum ada POST request, pastikan $store sudah diambil
if (!isset($store)) {
    $store = getStoreData($_SESSION['store_id']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Atur Toko - <?= htmlspecialchars($store['name']) ?></title>
    <?php include 'includes/components.php'; ?>
</head>
<body class="bg-gray-100">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <!-- Top Bar -->
        <header class="bg-white shadow-sm">
            <div class="px-4 py-3 flex items-center justify-between">
                <button onclick="toggleSidebar()" class="text-gray-500 hover:text-gray-700 lg:hidden">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h1 class="text-lg font-semibold text-gray-800">Atur Toko</h1>
                <div class="w-8"></div>
            </div>
        </header>

        <!-- Content -->
        <main class="p-4">
            <div class="max-w-3xl mx-auto">
                <?php if (isset($success_message)): ?>
                    <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <form method="POST" class="space-y-6" enctype="multipart/form-data">
                        <!-- Nama Toko -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">
                                Nama Toko <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   value="<?= htmlspecialchars($store['name']) ?>"
                                   required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <!-- Alamat -->
                        <div>
                            <label for="address" class="block text-sm font-medium text-gray-700">
                                Alamat <span class="text-red-500">*</span>
                            </label>
                            <textarea id="address" 
                                      name="address" 
                                      rows="3" 
                                      required
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?= htmlspecialchars($store['address']) ?></textarea>
                        </div>

                        <!-- Telepon -->
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">
                                Nomor Telepon
                            </label>
                            <input type="tel" 
                                   id="phone" 
                                   name="phone" 
                                   value="<?= htmlspecialchars($store['phone']) ?>"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">
                                Email
                            </label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   value="<?= htmlspecialchars($store['email'] ?? '') ?>"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <!-- Logo Toko -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Upload Logo Toko
                            </label>
                        <?php if ($has_premium): ?>    
                            <?php if ($store['logo']): ?>
                                <div class="mt-2 mb-4">
                                    <img src="<?= htmlspecialchars($store['logo'] ?? '') ?>" 
                                         alt="Logo Toko" 
                                         class="h-20 w-auto">
                                </div>
                            <?php endif; ?>
                            <div class="mt-1 flex items-center">
                                <input type="file" 
                                       id="logo" 
                                       name="logo" 
                                       accept="image/*"
                                       class="block w-full text-sm text-gray-500
                                              file:mr-4 file:py-2 file:px-4
                                              file:rounded-md file:border-0
                                              file:text-sm file:font-semibold
                                              file:bg-blue-50 file:text-blue-700
                                              hover:file:bg-blue-100">
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                Format: JPG, PNG, atau GIF. Maksimal 5MB.
                            </p>
                            <?php else: ?> 
                            <div class="text-center p-4">
                                <p class="text-gray-600 mb-4">Mau upload logo toko Anda dan info promo tampil di struk?</p>
                                <a href="premium-payment?feature=STORE_SETTINGS_PRO" 
                                class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-md hover:from-blue-600 hover:to-blue-700">
                                    <i class="fas fa-crown mr-2"></i> Upgrade ke PRO
                                </a>
                            </div>
                        <?php endif; ?>                              
                        </div>                       

                        <!-- Keterangan Struk -->
                        <div>
                            <label for="receipt_info" class="block text-sm font-medium text-gray-700">
                                Keterangan di Struk
                            </label>
                        <?php if ($has_premium): ?>   
                            <textarea id="receipt_info" 
                                      name="receipt_info" 
                                      rows="4" 
                                      placeholder="Contoh: Promo Hari ini: Beli 2 Gratis 1&#10;Follow Instagram: @tokoanda"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                             focus:border-blue-500 focus:ring-blue-500"><?= htmlspecialchars($store['receipt_info'] ?? '') ?></textarea>
                            <p class="mt-1 text-sm text-gray-500">
                                Informasi tambahan yang akan muncul di struk belanja (promo, sosial media, dll)
                            </p>
                            <?php else: ?> 
                            <div class="text-center p-4">
                                <p class="text-gray-600 mb-4">Mau upload logo toko Anda dan info promo tampil di struk?</p>
                                <a href="premium-payment?feature=STORE_SETTINGS_PRO" 
                                class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-md hover:from-blue-600 hover:to-blue-700">
                                    <i class="fas fa-crown mr-2"></i> Upgrade ke PRO
                                </a>
                            </div>
                        <?php endif; ?>                              
                        </div>

                        <!-- Tombol Submit -->
                        <div class="flex justify-end">
                            <button type="submit" 
                                    class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html> 