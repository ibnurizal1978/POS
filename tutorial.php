<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/check_session.php';
require_once 'includes/tracking_helper.php';

$db = new Database();
$conn = $db->getConnection();

// Setelah koneksi database dan session dibuat
logPageAccess($conn);
?>


<!DOCTYPE html>
<html lang="id">
<head>

    <title>Tutorial</title>
    <?php include 'includes/components.php'; ?>
</head>
<body class="bg-gray-100">
    <?php include 'includes/sidebar.php'; ?>

    <div class="lg:ml-64 min-h-screen">
        <!-- Top Bar -->
        <header class="bg-white shadow-sm">
            <div class="px-4 py-3 flex items-center justify-between">
                <button onclick="toggleSidebar()" class="text-gray-500 hover:text-gray-700 lg:hidden">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h1 class="text-lg font-semibold text-gray-800">Tutorial</h1>
                <div class="w-8"></div>
            </div>
        </header>

        <!-- Content -->
        <main class="p-4 md:p-6">
            <div class="max-w-4xl mx-auto">
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-2">Video Tutorial</h2>
                    <p class="text-gray-600">Pelajari cara menggunakan aplikasi kasir POS melalui video tutorial berikut:</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Tutorial 1 -->
                    <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow">
                        <div class="p-5">
                            <div class="flex items-center mb-4">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-user-plus text-blue-500"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800">Pendaftaran</h3>
                            </div>
                            <p class="text-gray-600 mb-4">Cara mendaftar dan memulai menggunakan aplikasi kasir POS</p>
                            <a href="https://www.youtube.com/watch?v=LdrBnQ86ZrU" target="_blank" 
                               class="inline-flex items-center text-blue-500 hover:text-blue-600">
                                <i class="fab fa-youtube mr-2"></i>
                                Tonton Video
                            </a>
                        </div>
                    </div>

                    <!-- Tutorial 2 -->
                    <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow">
                        <div class="p-5">
                            <div class="flex items-center mb-4">
                                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-sign-in-alt text-green-500"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800">Login</h3>
                            </div>
                            <p class="text-gray-600 mb-4">Cara login dan mengakses aplikasi kasir POS</p>
                            <a href="https://www.youtube.com/watch?v=jUtnwTKBg9k" target="_blank" 
                               class="inline-flex items-center text-blue-500 hover:text-blue-600">
                                <i class="fab fa-youtube mr-2"></i>
                                Tonton Video
                            </a>
                        </div>
                    </div>

                    <!-- Tutorial 3 -->
                    <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow">
                        <div class="p-5">
                            <div class="flex items-center mb-4">
                                <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-store text-purple-500"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800">Pengaturan Toko</h3>
                            </div>
                            <p class="text-gray-600 mb-4">Cara mengatur informasi dan pengaturan toko Anda</p>
                            <a href="https://www.youtube.com/watch?v=JWkbIkdro9Q" target="_blank" 
                               class="inline-flex items-center text-blue-500 hover:text-blue-600">
                                <i class="fab fa-youtube mr-2"></i>
                                Tonton Video
                            </a>
                        </div>
                    </div>

                    <!-- Tutorial 4 -->
                    <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow">
                        <div class="p-5">
                            <div class="flex items-center mb-4">
                                <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-tags text-yellow-500"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800">Kategori Produk</h3>
                            </div>
                            <p class="text-gray-600 mb-4">Cara mengatur kategori produk di aplikasi</p>
                            <a href="https://www.youtube.com/watch?v=DvlvgwNZdzI" target="_blank" 
                               class="inline-flex items-center text-blue-500 hover:text-blue-600">
                                <i class="fab fa-youtube mr-2"></i>
                                Tonton Video
                            </a>
                        </div>
                    </div>

                    <!-- Tutorial 5 -->
                    <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow">
                        <div class="p-5">
                            <div class="flex items-center mb-4">
                                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-ruler text-red-500"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800">Satuan Barang</h3>
                            </div>
                            <p class="text-gray-600 mb-4">Cara mengatur satuan barang di aplikasi</p>
                            <a href="https://www.youtube.com/watch?v=sLZVoYCROC0" target="_blank" 
                               class="inline-flex items-center text-blue-500 hover:text-blue-600">
                                <i class="fab fa-youtube mr-2"></i>
                                Tonton Video
                            </a>
                        </div>
                    </div>

                    <!-- Tutorial 6 -->
                    <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow">
                        <div class="p-5">
                            <div class="flex items-center mb-4">
                                <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-box text-indigo-500"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800">Pengaturan Produk</h3>
                            </div>
                            <p class="text-gray-600 mb-4">Cara mengelola produk di aplikasi kasir</p>
                            <a href="https://www.youtube.com/watch?v=Y30fjPNLkOM" target="_blank" 
                               class="inline-flex items-center text-blue-500 hover:text-blue-600">
                                <i class="fab fa-youtube mr-2"></i>
                                Tonton Video
                            </a>
                        </div>
                    </div>

                    <!-- Tutorial 7 -->
                    <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow">
                        <div class="p-5">
                            <div class="flex items-center mb-4">
                                <div class="w-10 h-10 bg-pink-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-users text-pink-500"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800">Pengaturan User</h3>
                            </div>
                            <p class="text-gray-600 mb-4">Cara mengatur pengguna dan hak akses</p>
                            <a href="https://www.youtube.com/watch?v=Y2VOCuKT2nk" target="_blank" 
                               class="inline-flex items-center text-blue-500 hover:text-blue-600">
                                <i class="fab fa-youtube mr-2"></i>
                                Tonton Video
                            </a>
                        </div>
                    </div>

                    <!-- Tutorial 8 -->
                    <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow">
                        <div class="p-5">
                            <div class="flex items-center mb-4">
                                <div class="w-10 h-10 bg-teal-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-cash-register text-teal-500"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800">Transaksi Kasir</h3>
                            </div>
                            <p class="text-gray-600 mb-4">Cara melakukan transaksi di aplikasi kasir</p>
                            <a href="https://www.youtube.com/watch?v=J5YTabogPPs" target="_blank" 
                               class="inline-flex items-center text-blue-500 hover:text-blue-600">
                                <i class="fab fa-youtube mr-2"></i>
                                Tonton Video
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html> 