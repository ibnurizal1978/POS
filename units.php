<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/check_session.php';
require_once 'includes/auth_helper.php';
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
$limit = 20; // items per page
$offset = ($page - 1) * $limit;

// Get total records for pagination
$stmt = $conn->prepare("SELECT COUNT(id) as total FROM units WHERE store_id = ?");
$stmt->execute([$store_id]);
$total_records = $stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Get units with limit
$query = "
    SELECT id, name, description 
    FROM units 
    WHERE store_id = ? 
    ORDER BY name ASC 
    LIMIT " . (int)$limit . " 
    OFFSET " . (int)$offset;

$stmt = $conn->prepare($query);
$stmt->execute([$store_id]);
$units = $stmt->fetchAll();

// Remove infinite scroll script and add pagination HTML
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Satuan Produk</title>
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
                    <h1 class="text-lg font-semibold text-gray-800">Satuan Produk</h1>
                    <a href="unit-add" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-plus"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Units List -->
        <div class="max-w-7xl mx-auto px-4 py-6">
            <div class="bg-white rounded-lg shadow-sm">
                <div id="units-container" class="divide-y divide-gray-100">
                    <?php if (empty($units)): ?>
                    <!-- Empty State Message -->
                    <div class="p-8 text-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-ruler text-2xl text-gray-400"></i>
                        </div>
                        <h3 class="text-gray-500 font-medium mb-1">Belum ada data</h3>
                        <p class="text-sm text-gray-400 mb-4">Silahkan tambah data dengan cara klik tombol + diatas</p>
                        <a href="unit-add" class="inline-flex items-center text-blue-500 hover:text-blue-600 font-medium">
                            <i class="fas fa-plus mr-2"></i>
                            Tambah Satuan
                        </a>
                    </div>
                    <?php else: ?>
                    <?php foreach ($units as $unit): ?>
                    <div class="p-4 flex items-center justify-between hover:bg-gray-50">
                        <div>
                            <h3 class="font-medium text-gray-800"><?php echo htmlspecialchars($unit['name']); ?></h3>
                            <?php if (!empty($unit['description'])): ?>
                            <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($unit['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <a href="unit-edit?id=<?php echo Encryption::encode($unit['id']); ?>" 
                           class="text-blue-500 hover:text-blue-600">
                            <i class="fas fa-edit"></i>
                        </a>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="flex items-center justify-center space-x-1 py-4 bg-white border-t border-gray-200">
                    <!-- First Page -->
                    <?php if ($page > 1): ?>
                        <a href="?page=1" class="px-3 py-2 text-gray-500 bg-white rounded-lg hover:bg-gray-100">
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
                        <a href="?page=<?= $page-1 ?>" class="px-3 py-2 text-gray-500 bg-white rounded-lg hover:bg-gray-100">
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
                        <a href="?page=1" class="px-3 py-2 text-gray-500 bg-white rounded-lg hover:bg-gray-100">1</a>
                        <?php if ($start > 2): ?>
                            <span class="px-3 py-2 text-gray-500">...</span>
                        <?php endif; ?>
                    <?php endif;

                    for ($i = $start; $i <= $end; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="px-3 py-2 text-white bg-blue-500 rounded-lg"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?>" class="px-3 py-2 text-gray-500 bg-white rounded-lg hover:bg-gray-100"><?= $i ?></a>
                        <?php endif;
                    endfor;

                    if ($end < $total_pages): ?>
                        <?php if ($end < $total_pages - 1): ?>
                            <span class="px-3 py-2 text-gray-500">...</span>
                        <?php endif; ?>
                        <a href="?page=<?= $total_pages ?>" class="px-3 py-2 text-gray-500 bg-white rounded-lg hover:bg-gray-100"><?= $total_pages ?></a>
                    <?php endif; ?>

                    <!-- Next Page -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page+1 ?>" class="px-3 py-2 text-gray-500 bg-white rounded-lg hover:bg-gray-100">
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
                        <a href="?page=<?= $total_pages ?>" class="px-3 py-2 text-gray-500 bg-white rounded-lg hover:bg-gray-100">
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
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html> 