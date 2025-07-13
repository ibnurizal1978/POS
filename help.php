<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/check_session.php';
require_once 'includes/tracking_helper.php';

$db = new Database();
$conn = $db->getConnection();

// Setelah koneksi database dan session dibuat
logPageAccess($conn);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {


        $subject = input_data($_POST['subject']);
        $description = input_data($_POST['description']);
        $store_id = $_SESSION['store_id'];

        // Validasi file upload
        $screenshot = null;
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png'];
            $filename = $_FILES['screenshot']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                throw new Exception('File harus berupa gambar (JPG, JPEG, PNG)');
            }
            
            // Generate unique filename
            $newFilename = uniqid() . '.' . $ext;
            $uploadPath = 'uploads/screenshots/' . $newFilename;
            
            // Buat direktori jika belum ada
            if (!file_exists('uploads/screenshots')) {
                mkdir('uploads/screenshots', 0777, true);
            }
            
            // Pindahkan file
            if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $uploadPath)) {
                $screenshot = $newFilename;
            }
        }
        
        // Insert ke database
        $stmt = $conn->prepare("
            INSERT INTO help_tickets (store_id, user_id, subject, description, screenshot)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['store_id'],
            $_SESSION['user_id'],
            $subject,
            $description,
            $screenshot
        ]);
        
        $success_message = "Tiket bantuan berhasil dikirim. Kami akan segera menanggapi.";
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Ambil riwayat tiket
$stmt = $conn->prepare("
    SELECT ht.*, u.username as user_name 
    FROM help_tickets ht
    JOIN users u ON ht.user_id = u.id
    WHERE ht.store_id = ?
    ORDER BY ht.created_at DESC
");
$stmt->execute([$_SESSION['store_id']]);
$tickets = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Bantuan</title>
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
                <h1 class="text-lg font-semibold text-gray-800">Bantuan</h1>
                <div class="w-8"></div>
            </div>
        </header>

        <!-- Content -->
        <main class="p-4">
            <div class="max-w-4xl mx-auto">
                <?php if (isset($success_message)): ?>
                    <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <!-- Form Bantuan -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h2 class="text-lg font-medium mb-4">Kirim Tiket Bantuan</h2>
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <!-- Subject -->
                        <div>
                            <label for="subject" class="block text-sm font-medium text-gray-700">
                                Subjek <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   id="subject" 
                                   name="subject" 
                                   required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <!-- Description -->
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">
                                Deskripsi Kendala <span class="text-red-500">*</span>
                            </label>
                            <textarea id="description" 
                                    name="description" 
                                    rows="4" 
                                    required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                        </div>

                        <!-- Screenshot -->
                        <div>
                            <label for="screenshot" class="block text-sm font-medium text-gray-700">
                                Screenshot (Opsional)
                            </label>
                            <input type="file" 
                                   id="screenshot" 
                                   name="screenshot"
                                   accept="image/*"
                                   class="mt-1 block w-full text-sm text-gray-500
                                          file:mr-4 file:py-2 file:px-4
                                          file:rounded-md file:border-0
                                          file:text-sm file:font-medium
                                          file:bg-blue-50 file:text-blue-700
                                          hover:file:bg-blue-100">
                            <p class="mt-1 text-sm text-gray-500">
                                Format yang didukung: JPG, JPEG, PNG
                            </p>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end">
                            <button type="submit" 
                                    class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Kirim Tiket
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Riwayat Tiket -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-lg font-medium mb-4">Riwayat Tiket</h2>
                    <?php if (count($tickets) > 0): ?>
                        <div class="space-y-4">
                            <?php foreach ($tickets as $ticket): ?>
                                <div class="border rounded-lg p-4">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h3 class="font-medium"><?= htmlspecialchars($ticket['subject']) ?></h3>
                                            <p class="text-sm text-gray-500">
                                                Dibuat oleh: <?= htmlspecialchars($ticket['user_name']) ?>
                                                pada <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
                                            </p>
                                        </div>
                                        <span class="px-2 py-1 text-xs rounded-full <?= 
                                            $ticket['status'] === 'open' ? 'bg-yellow-100 text-yellow-800' : 
                                            ($ticket['status'] === 'process' ? 'bg-blue-100 text-blue-800' : 
                                            'bg-green-100 text-green-800') 
                                        ?>">
                                            <?= ucfirst($ticket['status']) ?>
                                        </span>
                                    </div>
                                    <p class="mt-2 text-gray-700"><?= nl2br(htmlspecialchars($ticket['description'])) ?></p>
                                    <?php if ($ticket['screenshot']): ?>
                                        <div class="mt-2">
                                            <a href="uploads/screenshots/<?= htmlspecialchars($ticket['screenshot']) ?>" 
                                               target="_blank"
                                               class="text-sm text-blue-500 hover:underline">
                                                Lihat Screenshot
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-4">Belum ada tiket bantuan</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html> 