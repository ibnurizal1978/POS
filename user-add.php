<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/check_session.php';
require_once 'includes/tracking_helper.php';

// Check if user is logged in and has permission (owner or admin)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'owner' && $_SESSION['role'] !== 'admin')) {
    header("Location: index.php");
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = input_data(trim($_POST['username']) ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $name = input_data(trim($_POST['name']) ?? '');
    $role = $_POST['role'] ?? '';
    
    // Validasi input
    if (empty($username) || empty($password) || empty($name) || empty($role)) {
        $message = 'Semua field harus diisi.';
    } elseif ($password !== $confirm_password) {
        $message = 'Password tidak cocok.';
    } elseif (strlen($password) < 6) {
        $message = 'Password minimal 6 karakter.';
    } else {
        try {
            $db = new Database();
            $conn = $db->getConnection();

            // Setelah koneksi database dan session dibuat
            logPageAccess($conn);

            // Cek username sudah dipakai atau belum
            $stmt = $conn->prepare("SELECT COUNT(id) FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $message = 'Username tidak tersedia dalam sistem. Coba gunakan username lainnya.';
            } else {

                // Insert user baru
                $sql = "
                    INSERT INTO users (store_id, username, password, full_name, role, created_at, updated_at, user_id) 
                    VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?)
                ";
                $stmt = $conn->prepare($sql);
                


                if ($stmt->execute([
                    $_SESSION['store_id'],
                    $username,
                    password_hash($password, PASSWORD_DEFAULT),
                    $name,
                    $role,
                    $_SESSION['user_id']
                ])) {
                    header("Location: users");
                    exit;
                }
            }
        } catch (PDOException $e) {
            $message = 'Terjadi kesalahan. Silakan coba lagi.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Tambah Pengguna</title>
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
                    <h1 class="text-lg font-semibold text-gray-800">Tambah Pengguna</h1>
                    <div class="w-8"></div>
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
                        <label for="name" class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               required
                               autocomplete="off"
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                    </div>

                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               required
                               autocomplete="off"
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               required
                               minlength="6"
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <p class="mt-1 text-sm text-gray-500">Minimal 6 karakter</p>
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Konfirmasi Password</label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               required
                               minlength="6"
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700">Peran</label>
                        <select id="role" 
                                name="role" 
                                required
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Pilih Peran</option>
                            <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : ''; ?>>
                                Admin Toko
                            </option>
                            <option value="staff" <?php echo (isset($_POST['role']) && $_POST['role'] === 'staff') ? 'selected' : ''; ?>>
                                Kasir
                            </option>
                        </select>
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
    <?php include 'includes/footer.php'; ?>
</body>
</html> 