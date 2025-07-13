<?php
require_once '../config/database.php';
require_once '../config/config.php';

session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Cek user berdasarkan username
        $stmt = $conn->prepare("
            SELECT store_id, id, full_name 
            FROM users 
            WHERE username = ?
        ");
        $stmt->execute([$_POST['username']]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new Exception('Username tidak ditemukan');
        }else{
            // Cek user berdasarkan username dan nomor HP
            $stmt2 = $conn->prepare("
                SELECT phone 
                FROM stores 
                WHERE id = ? AND phone = ?
            ");
            $stmt2->execute([$user['store_id'], $_POST['phone']]);
            $user2 = $stmt2->fetch();            

            if (!$user2) {
                throw new Exception('Nomor HP tidak ditemukan');
            }
        }

        // Generate OTP
        $otp = sprintf("%06d", mt_rand(0, 999999));
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // Simpan OTP
        $stmt = $conn->prepare("
            INSERT INTO password_resets (user_id, otp, expires_at, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$user['id'], $otp, $expires]);

        // Di sini idealnya kirim OTP via WhatsApp
        // Untuk demo, kita tampilkan saja
        $success_message = "OTP telah dikirim ke nomor HP Anda. OTP: " . $otp;
        
        // Simpan user_id untuk halaman reset password
        $_SESSION['reset_user_id'] = $user['id'];
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - POS UMKM</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .gradient-background {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);
            animation: gradient 15s ease infinite;
            background-size: 400% 400%;
        }
        .floating {
            animation: floating 3s ease-in-out infinite;
        }
        @keyframes gradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
    </style>    
</head>
<body class="gradient-background min-h-screen">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <h2 class="text-3xl font-bold text-white">
                    Lupa Password?
                </h2>
                <p class="mt-2 text-sm text-white">
                    Masukkan username dan nomor HP Anda
                </p>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="rounded-md bg-green-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">
                                <?= htmlspecialchars($success_message) ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="rounded-md bg-red-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">
                                <?= htmlspecialchars($error_message) ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <form class="mt-8 space-y-6" method="POST">
                <div class="rounded-md shadow-sm space-y-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-white">
                            Username
                        </label>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               required
                               class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="Masukkan username Anda">
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-white">
                            Nomor HP
                        </label>
                        <input type="tel" 
                               id="phone" 
                               name="phone" 
                               required
                               pattern="08[0-9]{6,8}"
                               class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="Contoh: 081234567890">
                    </div>
                </div>

                <div>
                    <button type="submit" 
                            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Kirim OTP
                    </button>
                </div>

                <div class="text-center">
                    <a href="../index" class="font-medium text-blue-200 hover:text-blue-500">
                        Kembali ke halaman login
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>