<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/check_session.php';
require_once 'includes/tracking_helper.php';

// Check if user is logged in and has permission (owner or admin)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'owner' && $_SESSION['role'] !== 'admin')) {
    header("Location: index");
    exit;
}

$message = '';
$user = null;
$id = Encryption::decode($_GET['id']) ?? 0;

// Get user data

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Setelah koneksi database dan session dibuat
    logPageAccess($conn);

    $stmt = $conn->prepare("
        SELECT id, username, full_name, role 
        FROM users 
        WHERE id = ? AND store_id = ?
    ");
    $stmt->execute([$id, $_SESSION['store_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        header("Location: users");
        exit;
    }
} catch (PDOException $e) {
    header("Location: users");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = input_data(trim($_POST['username']) ?? '');
    $name = input_data(trim($_POST['name']) ?? '');
    $role = input_data($_POST['role']) ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $action = input_data($_POST['action']) ?? 'update';
    
    try {
        if ($action === 'delete') {
            // Prevent deleting self
            if ($id == $_SESSION['user_id']) {
                $message = 'Anda tidak dapat menghapus akun yang sedang digunakan.';
            } else {
                // Delete user
                $stmt = $conn->prepare("
                    UPDATE users SET is_active = 0 
                    WHERE id = ? AND store_id = ?
                ");
                
                if ($stmt->execute([$id, $_SESSION['store_id']])) {
                    header("Location: users.php");
                    exit;
                }
            }
        } else {
            // Validasi input
            if (empty($username) || empty($name) || empty($role)) {
                $message = 'Nama, username, dan peran harus diisi.';
            } else {
                // Cek username sudah dipakai atau belum (kecuali username sendiri)
                $stmt = $conn->prepare("
                    SELECT COUNT(id) 
                    FROM users 
                    WHERE username = ? AND id != ?
                ");
                $stmt->execute([$username, $id]);
                if ($stmt->fetchColumn() > 0) {
                    $message = 'Username sudah digunakan.';
                } else {
                    // Update user
                    if (!empty($new_password)) {
                        // Validasi password baru
                        if (strlen($new_password) < 6) {
                            $message = 'Password minimal 6 karakter.';
                        } elseif ($new_password !== $confirm_password) {
                            $message = 'Password tidak cocok.';
                        } else {
                            $stmt = $conn->prepare("
                                UPDATE users 
                                SET username = ?, 
                                    full_name = ?, 
                                    role = ?,
                                    password = ?,
                                    updated_at = UTC_TIMESTAMP(),
                                    user_id = ?
                                WHERE id = ? AND store_id = ? LIMIT 1

                            ");
                            
                            if ($stmt->execute([
                                $username,
                                $name,
                                $role,
                                password_hash($new_password, PASSWORD_DEFAULT),
                                $_SESSION['user_id'],
                                $id,
                                $_SESSION['store_id']

                            ])) {
                                header("Location: users.php");
                                exit;
                            }
                        }
                    } else {
                        $stmt = $conn->prepare("
                            UPDATE users 
                            SET username = ?, 
                                full_name = ?, 
                                role = ?
                            WHERE id = ? AND store_id = ? LIMIT 1
                        ");
                        
                        if ($stmt->execute([
                            $username,
                            $name,
                            $role,
                            $id,
                            $_SESSION['store_id']
                        ])) {
                            header("Location: users");
                            exit;
                        }
                    }
                }
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
    <title>Edit Pengguna</title>
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
                    <h1 class="text-lg font-semibold text-gray-800">Edit Pengguna</h1>
                    <?php if ($id != $_SESSION['user_id']): ?>
                    <button onclick="confirmDelete()" class="text-red-500 hover:text-red-600">
                        <i class="fas fa-trash"></i>
                    </button>
                    <?php else: ?>
                    <div class="w-8"></div>
                    <?php endif; ?>
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

                <form method="POST" id="userForm" class="space-y-4">
                    <input type="hidden" id="formAction" name="action" value="update">

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               required
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                               value="<?php echo htmlspecialchars($user['full_name']); ?>">
                    </div>

                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               required
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                               value="<?php echo htmlspecialchars($user['username']); ?>">
                    </div>

                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700">Password Baru (Opsional)</label>
                        <input type="password" 
                               id="new_password" 
                               name="new_password"
                               minlength="6"
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <p class="mt-1 text-sm text-gray-500">Kosongkan jika tidak ingin mengubah password</p>
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Konfirmasi Password Baru</label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password"
                               minlength="6"
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700">Peran</label>
                        <select id="role" 
                                name="role" 
                                required
                                <?php echo $id == $_SESSION['user_id'] ? 'disabled' : ''; ?>
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>
                                Admin Toko
                            </option>
                            <option value="staff" <?php echo $user['role'] === 'staff' ? 'selected' : ''; ?>>
                                Kasir
                            </option>
                        </select>
                        <?php if ($id == $_SESSION['user_id']): ?>
                        <p class="mt-1 text-sm text-gray-500">Anda tidak dapat mengubah peran akun yang sedang digunakan</p>
                        <?php endif; ?>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <a href="users" 
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
            <h3 class="text-lg font-medium text-gray-900 mb-4">Hapus Pengguna</h3>
            <p class="text-gray-500 mb-6">Apakah Anda yakin ingin menghapus pengguna ini? Tindakan ini tidak dapat dibatalkan.</p>
            <div class="flex justify-end space-x-3">
                <button onclick="hideDeleteModal()" 
                        class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-500">
                    Batal
                </button>
                <button onclick="deleteUser()" 
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

        function deleteUser() {
            const form = document.getElementById('userForm');
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