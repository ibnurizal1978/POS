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

$db = new Database();
$conn = $db->getConnection();

// Setelah koneksi database dan session dibuat
logPageAccess($conn);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Order</title>
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
                    <h1 class="text-lg font-semibold text-gray-800">Order</h1>
                    <a href="#">
                        
                    </a>
                </div>
            </div>
        </div>

        <!-- Categories List -->
        <div class="max-w-7xl mx-auto px-4 py-6">
            <div class="bg-white rounded-lg shadow-sm">
                <div id="categories-container" class="divide-y divide-gray-100">
                    
                    <!-- Empty State Message -->
                    <div class="p-8 text-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-shopping-bag text-2xl text-gray-400"></i>
                        </div>
                        <h3 class="text-gray-500 text-lg font-medium mb-1">Akan Datang Segera Nih...</h3>
                        <p class="text-md text-gray-400 mb-4">Pelanggan Anda bisa order langsung dari aplikasi ini dan pesanan mereka akan muncul di menu ini.<br/><br/>Nantikan fitur ini ya :)</p>
                    </div>
                </div>
                
                <!-- Loading Indicator -->
                <div id="loading" class="hidden p-4 text-center text-gray-500">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span class="ml-2">Memuat...</span>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script>
        // Infinite scroll
        let page = 1;
        let loading = false;
        let noMoreData = false;
        const container = document.getElementById('categories-container');
        const loadingIndicator = document.getElementById('loading');

        function loadMoreCategories() {
            if (loading || noMoreData) return;
            
            loading = true;
            page++;
            loadingIndicator.classList.remove('hidden');

            fetch(`categories.php?page=${page}&ajax=1`)
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        noMoreData = true;
                        loadingIndicator.classList.add('hidden');
                        return;
                    }

                    data.forEach(category => {
                        const div = document.createElement('div');
                        div.className = 'p-4 flex items-center justify-between hover:bg-gray-50';
                        div.innerHTML = `
                            <div>
                                <h3 class="font-medium text-gray-800">${category.name}</h3>
                                ${category.description ? `<p class="text-sm text-gray-500 mt-1">${category.description}</p>` : ''}
                            </div>
                            <a href="category-edit.php?id=${category.id}" class="text-blue-500 hover:text-blue-600">
                                <i class="fas fa-edit"></i>
                            </a>
                        `;
                        container.appendChild(div);
                    });

                    loading = false;
                    loadingIndicator.classList.add('hidden');
                })
                .catch(error => {
                    console.error('Error:', error);
                    loading = false;
                    loadingIndicator.classList.add('hidden');
                });
        }

        // Detect when user scrolls near bottom
        window.addEventListener('scroll', () => {
            if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 500) {
                loadMoreCategories();
            }
        });
    </script>
</body>
</html> 