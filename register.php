<?php
require_once 'config/database.php';
require_once 'config/config.php';

// Jika sudah login, redirect ke dashboard
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validasi input
        if (empty($_POST['store_name']) || empty($_POST['name']) || 
            empty($_POST['phone']) || empty($_POST['username']) || 
            empty($_POST['password'])) {
            throw new Exception('Semua field harus diisi');
        }

        $store_name = input_data($_POST['store_name']);
        $address = input_data($_POST['address']);
        $name = input_data($_POST['name']);
        $phone = input_data($_POST['phone']);
        $username = input_data($_POST['username']);
        $password = input_data($_POST['password']);

        // Validasi captcha
        $num1 = Encryption::decode($_POST['num1']);
        $num2 = Encryption::decode($_POST['num2']);
        $captcha = input_data($_POST['captcha']);
        $result = $num1 + $num2;
        if ($captcha != $result) {
            echo "<script>alert('Hasil penjumlahan tidak sesuai'); window.location.href='register';</script>";
            exit();
        }

        // Validasi username unik
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            echo "<script>alert('Username sudah digunakan'); window.location.href='register';</script>";
            exit();
        }

        //check if phone number has been used
        $stmt = $conn->prepare("SELECT id FROM stores WHERE phone = ? LIMIT 1");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            echo "<script>alert('Nomor HP sudah digunakan'); window.location.href='register';</script>";
            exit();
        }

        // Validasi nomor HP
        /*if (!preg_match('/^08[0-9]{8,11}$/', $phone)) {
            echo "<script>alert('Format nomor HP tidak valid');</script>";
        }*/

        $conn->beginTransaction();

        // Buat toko baru
        $stmt = $conn->prepare("
            INSERT INTO stores (name, phone, address, created_at) 
            VALUES (?, ?, ?, UTC_TIMESTAMP())
        ");
        $stmt->execute([
            $store_name,
            $phone,
            $address ?? null
        ]);
        $store_id = $conn->lastInsertId();

        // Buat user owner
        $stmt = $conn->prepare("
            INSERT INTO users (store_id, full_name, username, password, role, created_at) 
            VALUES (?, ?, ?, ?, 'owner', UTC_TIMESTAMP())
        ");
        $stmt->execute([
            $store_id,
            $name,
            $username,
            password_hash($password, PASSWORD_DEFAULT)
        ]);

        $conn->commit();
        
        $success_message = "Pendaftaran berhasil! Silakan login pada link diatas pesan ini.";
        
    } catch (Exception $e) {
        //$conn->rollBack();
        //$error_message = 'Ada kendala pada pendaftaran, silakan coba lagi.';
        //$error_message = $e->getMessage();
    }
}

// Generate captcha numbers
$num1 = rand(1, 9);
$num2 = rand(1, 9);
$captcha_result = $num1 + $num2;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - POS UMKM</title>
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
                    Daftar Toko Baru
                </h2>
                <p class="mt-2 text-sm text-blue-200">
                    Sudah punya akun? 
                    <a href="index" class="font-medium text-white hover:text-blue-500">
                        Login di sini
                    </a>
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
                <!-- Informasi Toko -->
                <div class="rounded-md shadow-sm space-y-4">
                    <div>
                        <label for="store_name" class="block text-sm font-medium text-white">
                            Nama Toko <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               id="store_name" 
                               name="store_name" 
                               required
                               class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="Nama toko Anda">
                    </div>

                    <div>
                        <label for="address" class="block text-sm font-medium text-white">
                            Alamat Toko
                        </label>
                        <textarea id="address" 
                                  name="address"
                                  rows="2"
                                  class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                                  placeholder="Alamat lengkap toko"></textarea>
                    </div>
                </div>

                <!-- Informasi Pemilik -->
                <div class="rounded-md shadow-sm space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-white">
                            Nama Pemilik <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               required
                               class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="Nama lengkap Anda">
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-white">
                            Nomor HP <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               id="phone" 
                               name="phone" 
                               required
                               class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="Contoh: 081234567890">
                    </div>

                    <div>
                        <label for="username" class="block text-sm font-medium text-white">
                            Username <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               required
                               class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="Username untuk login">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-white">
                            Password <span class="text-red-500">*</span>
                        </label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               required
                               minlength="6"
                               class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="Minimal 6 karakter, ada huruf besar, huruf kecil dan angka">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-white">
                        <?php echo $num1; ?> + <?php echo $num2; ?> = <span class="text-red-500">*</span>
                        </label>
                        <input type="number"
                               name="captcha" 
                               required
                               minlength="2"
                               class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="ketik hasil penjumlahan disini">
                    </div>
                    <input type="hidden" name="num1" value="<?php echo Encryption::encode($num1); ?>">
                    <input type="hidden" name="num2" value="<?php echo Encryption::encode($num2); ?>">
                    <input type="hidden" name="captcha_result" value="<?php echo $captcha_result; ?>">
                </div>

                <div>
                    <button type="submit" 
                            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Daftar Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

<script>
        const captchaInput = parseInt(document.querySelector('input[name="captcha"]').value);
    const captchaResult = parseInt(document.querySelector('input[name="captcha_result"]').value);
    
    if (captchaInput !== captchaResult) {
        alert('Hasil penjumlahan tidak sesuai!');
        return;
    }
</script>