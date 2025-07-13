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
$unit = null;
$id = Encryption::decode($_GET['id']) ?? 0;


// Get unit data
try {
    $db = new Database();
    $conn = $db->getConnection();

    // Setelah koneksi database dan session dibuat
    logPageAccess($conn);
    
    $stmt = $conn->prepare("
        SELECT id, name, description 
        FROM units 
        WHERE id = ? AND store_id = ? LIMIT 1
    ");
    $stmt->execute([$id, $_SESSION['store_id']]);
    $unit = $stmt->fetch();

    if (!$unit) {
        header("Location: units.php");
        exit;
    }
} catch (PDOException $e) {
    header("Location: units.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = input_data($_POST['name']) ?? '';
    $description = input_data($_POST['description']) ?? '';
    $action = input_data($_POST['action']) ?? 'update';
    
    try {
        if ($action === 'delete') {
            // Check if unit is being used in products
            $stmt = $conn->prepare("
                SELECT COUNT(id) as count 
                FROM products 
                WHERE unit_id = ? AND store_id = ? LIMIT 1
            ");
            $stmt->execute([$id, $_SESSION['store_id']]);
            $result = $stmt->fetch();

            if ($result['count'] > 0) {
                $message = 'Satuan ini tidak dapat dihapus karena sedang digunakan oleh produk.';
            } else {
                // Delete unit
                $stmt = $conn->prepare("
                    DELETE FROM units 
                    WHERE id = ? AND store_id = ? LIMIT 1
                ");
                
                if ($stmt->execute([$id, $_SESSION['store_id']])) {
                    header("Location: units.php");
                    exit;
                }
            }
        } else {
            // Update unit
            if (!empty($name)) {
                //check if unit name already exists
                $stmt = $conn->prepare("SELECT COUNT(id) FROM units WHERE store_id = ? AND name = ? AND id != ?");
                $stmt->execute([$_SESSION['store_id'], $name, $id]);
                $count = $stmt->fetchColumn();
                if ($count > 0) {
                    $message = 'Nama satuan sudah ada.';
                }else{
                    $stmt = $conn->prepare("
                        UPDATE units 
                        SET name = ?, description = ?, updated_at = UTC_TIMESTAMP(), user_id = ?
                        WHERE id = ? AND store_id = ? LIMIT 1
                    ");

                    if ($stmt->execute([$name, $description, $_SESSION['user_id'], $id, $_SESSION['store_id']])) {
                        header("Location: units.php");
                        exit;
                    }
                }
            } else {
                $message = 'Nama satuan harus diisi.';
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
    <title>Edit Satuan</title>
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
                    <h1 class="text-lg font-semibold text-gray-800">Edit Satuan</h1>
                    <button class="text-red-500 hover:text-red-600">
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

                <form method="POST" class="space-y-4" id="unitForm">
                    <input type="hidden" name="action" id="formAction" value="update">
                    
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Nama Satuan</label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               required
                               placeholder="Contoh: pcs, box, lusin"
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                               value="<?php echo htmlspecialchars($unit['name']); ?>">
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">Deskripsi (Opsional)</label>
                        <textarea id="description" 
                                  name="description" 
                                  rows="3"
                                  placeholder="Contoh: Satuan per pieces"
                                  class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?php echo htmlspecialchars($unit['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <a href="units" 
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

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 m-4 max-w-sm w-full">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Hapus Satuan</h3>
            <p class="text-gray-500 mb-6">Apakah Anda yakin ingin menghapus satuan ini? Tindakan ini tidak dapat dibatalkan.</p>
            <div class="flex justify-end space-x-3">
                <button onclick="hideDeleteModal()" 
                        class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-500">
                    Batal
                </button>
                <button onclick="deleteUnit()" 
                        class="px-4 py-2 bg-red-500 text-white text-sm font-medium rounded-lg hover:bg-red-600">
                    Hapus
                </button>
            </div>
        </div>
    </div>
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

        function deleteUnit() {
            const form = document.getElementById('unitForm');
            const actionInput = document.getElementById('formAction');
            actionInput.value = 'delete';
            form.submit();
        }

        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideDeleteModal();
            }
        });
    </script>
</body>
</html> 