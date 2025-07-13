<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/check_session.php';
require_once 'includes/tracking_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = input_data($_POST['name']) ?? '';
    $description = input_data($_POST['description']) ?? '';
    
    if (!empty($name)) {
        try {
            $db = new Database();
            $conn = $db->getConnection();

            // Setelah koneksi database dan session dibuat
            logPageAccess($conn);
            
            //check if category name already exists
            $stmt = $conn->prepare("SELECT COUNT(id) FROM categories WHERE store_id = ? AND name = ?");
            $stmt->execute([$_SESSION['store_id'], $name]);
            $count = $stmt->fetchColumn();
            if ($count > 0) {
                $message = 'Nama kategori sudah ada.';
            }else{
                $stmt = $conn->prepare("
                    INSERT INTO categories (store_id, name, description, created_at, user_id) 
                    VALUES (?, ?, ?, UTC_TIMESTAMP(), ?)
                ");
                

                if ($stmt->execute([$_SESSION['store_id'], $name, $description, $_SESSION['user_id']])) {
                    header("Location: categories.php");
                    exit;
                }
            }
        } catch (PDOException $e) {
            $message =  'Terjadi kesalahan. Silakan coba lagi.';
        }
    } else {
        $message = 'Nama kategori harus diisi.';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Tambah Kategori</title>
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
                    <h1 class="text-lg font-semibold text-gray-800">Tambah Kategori</h1>
                    <div class="w-8"></div> <!-- Spacer for alignment -->
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

                <form method="POST" class="space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Nama Kategori</label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               required
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">Deskripsi (Opsional)</label>
                        <textarea id="description" 
                                  name="description" 
                                  rows="3"
                                  class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <a href="categories" 
                           class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-500">
                            Batal
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
    <?php include 'includes/footer.php'; ?>
</body>
</html> 