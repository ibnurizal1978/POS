<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/check_session.php';
require_once 'includes/auth_helper.php';
require_once 'includes/tracking_helper.php';

// Check role
requireRole(['owner', 'admin']);

// Check if user is logged in and has permission (owner or admin)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'owner' && $_SESSION['role'] !== 'admin')) {
    header("Location: index.php");
    exit;
}

// Get users
function getUsers() {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Setelah koneksi database dan session dibuat
    logPageAccess($conn);

    $stmt = $conn->prepare("
        SELECT id, username, full_name, role 
        FROM users 
        WHERE store_id = ? 
        AND is_active = 1
        ORDER BY full_name ASC
    ");
    
    $stmt->execute([$_SESSION['store_id']]);
    return $stmt->fetchAll();
}

$users = getUsers();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Daftar Pengguna</title>
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
                    <h1 class="text-lg font-semibold text-gray-800">Daftar Pengguna</h1>
                    <a href="user-add" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-plus"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Users List -->
        <div class="max-w-7xl mx-auto px-4 py-6">
            <div class="bg-white rounded-lg shadow-sm">
                <div class="divide-y divide-gray-100">
                    <?php if (empty($users)): ?>
                    <div class="p-8 text-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-users text-2xl text-gray-400"></i>
                        </div>
                        <h3 class="text-gray-500 font-medium mb-1">Belum ada pengguna</h3>
                        <p class="text-sm text-gray-400 mb-4">Silahkan tambah pengguna dengan cara klik tombol + diatas</p>
                        <a href="user-add" class="inline-flex items-center text-blue-500 hover:text-blue-600 font-medium">
                            <i class="fas fa-plus mr-2"></i>
                            Tambah Pengguna
                        </a>
                    </div>
                    <?php else: ?>
                    <?php foreach ($users as $user): ?>
                    <div class="p-4 flex items-center justify-between hover:bg-gray-50">
                        <div>
                            <h3 class="font-medium text-gray-800"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                            <div class="text-sm text-gray-500 mt-1">
                                <?php echo htmlspecialchars($user['username']); ?>
                                    <?php if($user['role'] === 'owner' || $user['role'] === 'admin') { ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                            <?php echo $user['role'] ?>
                                        </span>
                                    <?php }else{ ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                            <?php echo $user['role'] ?>
                                        </span>
                                    <?php } ?>
                                <!--<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    <?php //echo ($user['role'] === 'owner' || $user['role'] === 'admin') ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800'; ?>">
                                    <?php //echo $user['role'] === 'staff' ? 'Admin Toko' : 'Kasir'; ?>
                                </span>-->
                            </div>
                        </div>
                        <a href="user-edit?id=<?php echo Encryption::encode($user['id']) ?>" 
                           class="text-blue-500 hover:text-blue-600">
                            <i class="fas fa-edit"></i>
                        </a>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html> 