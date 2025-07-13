<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/check_session.php';
require_once 'includes/auth_helper.php';
require_once 'includes/tracking_helper.php';

// Check role
requireRole(['owner', 'admin']);

$db = new Database();
$conn = $db->getConnection();

// Setelah koneksi database dan session dibuat
logPageAccess($conn);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Kategori Produk</title>
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
                    <h1 class="text-lg font-semibold text-gray-800">Pilih Laporan Penjualan</h1>
                </div>  
            </div>
        </div>
        <div class="container mx-auto px-4 py-8">
            <h1 class="text-2xl font-bold mb-6">Laporan</h1>


    <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Laporan Penjualan -->
        <a href="report-sales" class="block">
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow duration-200">
                <div class="flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4 mx-auto">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                        </path>
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-center mb-2">Penjualan</h2>
                <p class="text-gray-600 text-center text-sm">
                    Laporan transaksi penjualan per periode
                </p>
            </div>
        </a>

        <!-- Laporan Stok -->
        <a href="report-stock" class="block">
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow duration-200">
                <div class="flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4 mx-auto">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4">
                        </path>
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-center mb-2">Stok</h2>
                <p class="text-gray-600 text-center text-sm">
                    Laporan persediaan barang terkini
                </p>
            </div>
        </a>

        <!-- Laporan Kategori -->
        <a href="report-category" class="block">
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow duration-200">
                <div class="flex items-center justify-center w-16 h-16 bg-purple-100 rounded-full mb-4 mx-auto">
                    <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z">
                        </path>
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-center mb-2">Kategori</h2>
                <p class="text-gray-600 text-center text-sm">
                    Laporan per-kategori produk
                </p>
            </div>
        </a>

        <!-- Laporan Kasir -->
        <a href="report-cashier" class="block">
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow duration-200">
                <div class="flex items-center justify-center w-16 h-16 bg-yellow-100 rounded-full mb-4 mx-auto">
                    <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                        </path>
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-center mb-2">Kasir</h2>
                <p class="text-gray-600 text-center text-sm">
                    Laporan penjualan per kasir
                </p>
            </div>
        </a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 