<?php
require_once 'auth_helper.php';
?>
<div id="sidebar" class="fixed top-0 left-0 h-full w-64 bg-blue-900 transform -translate-x-full transition-transform duration-300 ease-in-out z-50">
    <div class="flex flex-col h-full">
        <!-- Profile Section -->
        <div class="p-4 bg-blue-800">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-blue-700 flex items-center justify-center">
                    <i class="fas fa-user text-white"></i>
                </div>
                <div>
                    <div class="text-white font-medium"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                    <div class="text-blue-200 text-sm"><?php echo htmlspecialchars($_SESSION['role']); ?></div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto py-4">
            <div class="px-4 space-y-2">
                <a href="dashboard" class="flex items-center space-x-3 text-blue-100 hover:bg-blue-800 p-2 rounded-lg">
                    <i class="fas fa-home w-5"></i>
                    <span>Dashboard</span>
                </a>
                <a href="transaction-resto" class="flex items-center space-x-3 text-blue-100 hover:bg-blue-800 p-2 rounded-lg">
                    <i class="fas fa-cash-register w-5"></i>
                    <span>Transaksi</span>
                </a>
                <a href="orders" class="flex items-center space-x-3 text-blue-100 hover:bg-blue-800 p-2 rounded-lg">
                    <i class="fas fa-shopping-bag w-5"></i>
                    <span>Order</span>
                </a>
                
                <?php if (canAccessMenu('settings')): ?>
                <a href="store-settings" class="flex items-center space-x-3 text-blue-100 hover:bg-blue-800 p-2 rounded-lg">
                    <i class="fas fa-store w-5"></i>
                    <span>Pengaturan</span>
                </a>
                <?php endif; ?>

                <?php if (canAccessMenu('categories')): ?>
                <a href="categories" class="flex items-center space-x-3 text-blue-100 hover:bg-blue-800 p-2 rounded-lg">
                    <i class="fas fa-box w-5"></i>
                    <span>Kategori</span>
                </a>
                <?php endif; ?>

                <?php if (canAccessMenu('units')): ?>
                <a href="units" class="flex items-center space-x-3 text-blue-100 hover:bg-blue-800 p-2 rounded-lg">
                    <i class="fas fa-box w-5"></i>
                    <span>Satuan</span>
                </a>    
                <?php endif; ?>

                <?php if (canAccessMenu('products')): ?>                       
                <a href="products" class="flex items-center space-x-3 text-blue-100 hover:bg-blue-800 p-2 rounded-lg">
                    <i class="fas fa-box w-5"></i>
                    <span>Produk</span>
                </a>
                <?php endif; ?>

                <?php if (canAccessMenu('users')): ?>
                <a href="users" class="flex items-center space-x-3 text-blue-100 hover:bg-blue-800 p-2 rounded-lg">
                    <i class="fas fa-users w-5"></i>
                    <span>User</span>
                </a>
                <?php endif; ?>
                                
                <?php if (canAccessMenu('reports')): ?>
                <a href="reports" class="flex items-center space-x-3 text-blue-100 hover:bg-blue-800 p-2 rounded-lg">
                    <i class="fas fa-chart-bar w-5"></i>
                    <span>Laporan</span>
                </a>
                <?php endif; ?>

                <?php if (canAccessMenu('grosir')): ?>
                <a href="grosir" class="flex items-center space-x-3 text-blue-100 hover:bg-blue-800 p-2 rounded-lg">
                    <i class="fas fa-percent w-5"></i>
                    <span>Harga Grosir</span>
                </a>
                <?php endif; ?>

                <a href="help" class="flex items-center space-x-3 text-blue-100 hover:bg-blue-800 p-2 rounded-lg">
                    <i class="fas fa-question-circle w-5"></i>
                    <span>Bantuan</span>
                </a>
                <a href="tutorial" class="flex items-center space-x-3 text-blue-100 hover:bg-blue-800 p-2 rounded-lg">
                    <i class="fas fa-play-circle w-5"></i>
                    <span>Tutorial</span>
                </a>

            </div>
        </nav>

        <!-- Logout Button -->
        <div class="p-4 border-t border-blue-800">
            <a href="auth/logout" class="flex items-center space-x-3 text-blue-100 hover:bg-blue-800 p-2 rounded-lg">
                <i class="fas fa-sign-out-alt w-5"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>

<!-- Overlay -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black opacity-50 hidden z-40" onclick="toggleSidebar()"></div> 

<script>
    // Tambahkan event listener untuk konfirmasi logout
    document.querySelector('a[href="logout.php"]').addEventListener('click', function(e) {
        e.preventDefault();
        if (confirm('Apakah Anda yakin ingin keluar?')) {
            window.location.href = 'auth/logout';
        }
    });
</script> 