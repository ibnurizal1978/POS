<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/auth_helper.php';
require_once 'includes/check_session.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terima Kasih</title>
    <?php include 'includes/components.php'; ?>
</head>
<body class="bg-gray-100">

    <div class="lg:ml-64 min-h-screen">
        <!-- Top Bar -->
        <header class="bg-white shadow-sm">
            <div class="px-4 py-3 flex items-center justify-between">
                <button onclick="toggleSidebar()" class="text-gray-500 hover:text-gray-700 lg:hidden">
                    &nbsp;
                </button>
                <h1 class="text-lg font-bold text-gray-800">Terima Kasih</h1>
                <div class="w-8"></div>
            </div>
        </header>

        <!-- Content -->
        <main class="p-4">
            <div class="max-w-2xl mx-auto">
                <div class="bg-white rounded-lg shadow-sm p-8 text-center">
                    <div class="mb-6">
                        <i class="fas fa-check-circle text-6xl text-green-500"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">
                        Terima Kasih Atas Pembayaran Anda
                    </h2>
                    <p class="text-gray-600 mb-8">
                        Kami akan segera mengaktifkan fitur yang Anda pilih.
                        Tim kami akan memproses pembayaran Anda secepat mungkin.
                    </p>
                    <img src="assets/img/logo.jpg" alt="Logo" class="w-100"/>
                </div>
            </div>
        </main>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html> 