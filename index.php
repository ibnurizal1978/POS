<?php
require_once 'config/database.php';
require_once 'config/config.php';

session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard");
    exit;
}

// Handle login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = input_data($_POST['username']) ?? '';
    $password = input_data($_POST['password']) ?? '';

    /*if (isset($_POST['timezone'])) {
        $_SESSION['user_timezone'] = $_POST['timezone'];
    } else {
        $_SESSION['user_timezone'] = 'Asia/Jakarta'; // default
    }*/

    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $stmt = $conn->prepare("SELECT id, full_name, username, password, role, store_id FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {

            //generate store name
            $stmt = $conn->prepare("SELECT name FROM stores WHERE id = ?");
            $stmt->execute([$user['store_id']]);
            $store = $stmt->fetch();

            //update table users to set timezone
            $timezone = input_data($_POST['timezoneoffset']);
            $stmt = $conn->prepare("UPDATE users SET timezone = ? WHERE id = ?");
            $stmt->execute([$timezone, $user['id']]);


            // Set session
            $_SESSION['store_name'] = $store['name'];
            $_SESSION['name'] = $user['full_name'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['timezone'] = $timezone;

            $_SESSION['store_id'] = $user['store_id'];
            
            // Redirect to dashboard
            header("Location: dashboard.php");
            exit;
        } else {
            $error = 'Username atau password salah';
        }
    } catch (PDOException $e) {
        $error = 'Terjadi kesalahan sistem';
    }
}
?>

<script>
// Deteksi timezone user
function detectTimezone() {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
}

document.addEventListener('DOMContentLoaded', function() {
    // Tambahkan hidden input untuk timezone
    var form = document.querySelector('form');
    var timezoneInput = document.createElement('input');
    timezoneInput.type = 'hidden';
    timezoneInput.name = 'timezone';
    timezoneInput.value = detectTimezone();
    form.appendChild(timezoneInput);
});
</script>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- FAVICON -->
    <link rel="apple-touch-icon" sizes="57x57" href="assets/img/favicon/apple-icon-57x57.png">
    <link rel="apple-touch-icon" sizes="60x60" href="assets/img/favicon/apple-icon-60x60.png">
    <link rel="apple-touch-icon" sizes="72x72" href="assets/img/favicon/apple-icon-72x72.png">
    <link rel="apple-touch-icon" sizes="76x76" href="assets/img/favicon/apple-icon-76x76.png">
    <link rel="apple-touch-icon" sizes="114x114" href="assets/img/favicon/apple-icon-114x114.png">
    <link rel="apple-touch-icon" sizes="120x120" href="assets/img/favicon/apple-icon-120x120.png">
    <link rel="apple-touch-icon" sizes="144x144" href="assets/img/favicon/apple-icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="assets/img/favicon/apple-icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/img/favicon/apple-icon-180x180.png">
    <link rel="icon" type="image/png" sizes="192x192"  href="assets/img/favicon/android-icon-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="assets/img/favicon/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicon/favicon-16x16.png">
    <link rel="manifest" href="assets/img/favicon/manifest.json">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="msapplication-TileImage" content="assets/img/favicon/ms-icon-144x144.png">
    <meta name="theme-color" content="#ffffff">

    <title>Login - Nama Aplikasi</title>
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

    <!-- Main Content -->
    <main class="min-h-screen flex flex-col items-center justify-center px-4 py-16">
        <div class="w-full max-w-md">
            <!-- Card Container -->
            <div class="bg-white/10 backdrop-blur-lg rounded-3xl p-8 shadow-2xl">

                <!-- Error Message -->
                <?php if ($error): ?>
                <div class="mb-4 p-4 bg-red-500/50 backdrop-blur-sm text-white rounded-xl text-sm text-center">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <!-- Logo Container -->
                <div class="flex flex-col items-center mb-8">
                    <div class="w-24 h-24 bg-white rounded-full p-4 shadow-xl mb-4 floating">
                        <img src="assets/img/favicon/android-icon-192x192.png" alt="Logo" class="w-full h-full object-contain"/>
                    </div>
                    <h1 class="text-white text-2xl font-bold">Selamat Datang</h1>
                    <p class="text-blue-200 text-sm mt-2">Silakan masuk ke akun Anda</p>
                </div>

                <!-- Login Form -->
                <form method="POST" action="" class="space-y-6">
                    <script type="text/javascript">
			            tzo = - new Date().getTimezoneOffset()*60;
			            document.write('<input type="hidden" value="'+detectTimezone()+'" name="timezoneoffset">');
			        </script>
                    <!-- Username Input -->
                    <div class="space-y-2">
                        <label class="block text-blue-100 text-sm font-medium">Username</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-4 flex items-center">
                                <i class="fas fa-user text-blue-300"></i>
                            </span>
                            <input 
                                type="text"
                                name="username"
                                class="w-full pl-12 pr-4 py-3 rounded-xl bg-white/90 border border-blue-200/20 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent backdrop-blur-sm"
                                placeholder="Masukkan username"
                                value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                            />
                        </div>
                    </div>

                    <!-- Password Input -->
                    <div class="space-y-2">
                        <label class="block text-blue-100 text-sm font-medium">Password</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-4 flex items-center">
                                <i class="fas fa-lock text-blue-300"></i>
                            </span>
                            <input 
                                type="password"
                                name="password"
                                id="password"
                                class="w-full pl-12 pr-12 py-3 rounded-xl bg-white/20 border border-blue-200/20 text-black placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent backdrop-blur-sm bg-white"
                                placeholder="Masukkan password"
                                autocomplete="current-password"
                            />
                            <button 
                                type="button" 
                                id="togglePassword"
                                class="absolute inset-y-0 right-4 flex items-center text-blue-600 hover:text-blue-800 focus:outline-none"
                            >
                                <i class="fas fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center justify-between">
                        <a href="auth/forgot-password" class="text-sm text-blue-200 hover:text-white transition duration-300">
                            Lupa Password?
                        </a>
                    </div>

                    <!-- Login Button -->
                    <button 
                        type="submit" id="loginBtn"
                        class="w-full bg-blue-500 hover:bg-blue-600 text-white font-semibold py-3 px-4 rounded-xl transition duration-300 ease-in-out transform hover:scale-[1.02] active:scale-[0.98] shadow-lg flex items-center justify-center space-x-2"
                    >
                        <span>Masuk</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </form>

                <!-- Register Link -->
                <div class="mt-8 text-center">
                    <p class="text-blue-200 text-sm">
                        Belum punya akun? 
                        <a href="register" class="font-medium text-white hover:underline transition duration-300">
                            Daftar Sekarang
                        </a>
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-8 text-center">
                <p class="text-blue-200 text-xs">
                    &copy; 2024 Nama Aplikasi. All rights reserved.
                </p>
            </div>
        </div>
    </main>

    <!-- JavaScript untuk waktu -->
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        });
    </script>
</body>
</html>

<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/disabledButton.js"></script>