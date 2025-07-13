<?php
// Pastikan ini di paling atas file
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/auth_helper.php';
require_once 'includes/check_session.php';
require_once 'includes/PHPMailer/PHPMailer.php';
require_once 'includes/PHPMailer/SMTP.php';
require_once 'includes/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Get action from request
$action = $_GET['action'] ?? '';

// Handle AJAX requests first
if ($action === 'send_email') {
    header('Content-Type: application/json');
    ob_clean(); // Clear any output buffers
    
    try {
        $invoice_number = $_GET['invoice_number'] ?? '';
        $email = $_GET['email'] ?? '';
        
        error_log("Processing email request: " . $email);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email tidak valid');
        }

        // Get transaction details
        $db = new Database();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("
            SELECT t.*, u.full_name as cashier_name 
            FROM transactions t
            JOIN users u ON t.user_id = u.id
            WHERE t.invoice_number = ?
        ");
        $stmt->execute([$invoice_number]);
        $transaction = $stmt->fetch();

        // Get store info
        $stmt = $conn->prepare("SELECT * FROM stores WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['store_id']]);
        $store = $stmt->fetch();

        // Get transaction items
        $stmt = $conn->prepare("
            SELECT ti.*, p.name as product_name, u.name as unit_name
            FROM transaction_items ti
            JOIN products p ON ti.product_id = p.id
            LEFT JOIN units u ON p.unit_id = u.id
            WHERE ti.transaction_id = ?
        ");
        $stmt->execute([$transaction['id']]);
        $items = $stmt->fetchAll();

        $mail = new PHPMailer(true);
        
        // Debug settings
        $mail->SMTPDebug = 3; // Enable verbose debug output
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug: $str");
        };
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'mail.ordermatix.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'info@ordermatix.com';
        $mail->Password = 'D0d0lg4rut@888';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;
        $mail->SMTPAuth = true;
        $mail->Timeout = 60;
        $mail->SMTPKeepAlive = true;
      

        // Log SMTP settings
        error_log("SMTP Settings:");
        error_log("Host: " . SMTP_HOST);
        error_log("Username: " . SMTP_USERNAME);
        error_log("Port: " . SMTP_PORT);
        error_log("Security: STARTTLS");
        
        // Recipients
        $mail->setFrom('info@ordermatix.com', $store['name']);
        $mail->addAddress($email);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "Struk Pembelian - {$invoice_number}";
        
        // Generate receipt HTML
        $receipt_html = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='text-align: center;'>{$store['name']}</h2>
                <p style='text-align: center;'>
                    Tanggal: " . date('d/m/Y H:i', strtotime($transaction['created_at'])) . "<br>
                    No. Transaksi: {$invoice_number}<br>
                    Kasir: {$transaction['cashier_name']}
                </p>
                
                <table style='width: 100%; border-top: 1px solid #ddd; border-bottom: 1px solid #ddd; margin: 15px 0; padding: 15px 0;'>
                    <tbody>";
        
        foreach ($items as $item) {
            $receipt_html .= "
                <tr>
                    <td style='padding: 5px 0;'>
                        {$item['product_name']}<br>
                        <small>{$item['quantity']} {$item['unit_name']} × Rp " . number_format($item['price'], 0, ',', '.') . "</small>
                    </td>
                    <td style='text-align: right; padding: 5px 0;'>
                        Rp " . number_format($item['subtotal'], 0, ',', '.') . "
                    </td>
                </tr>";
        }
        
        $receipt_html .= "
                    </tbody>
                </table>
                
                <div style='text-align: right;'>
                    <p>
                        <strong>Total:</strong> Rp " . number_format($transaction['total_amount'], 0, ',', '.') . "<br>
                        Tunai: Rp " . number_format($transaction['paid_amount'], 0, ',', '.') . "<br>
                        Kembali: Rp " . number_format($transaction['change_amount'], 0, ',', '.') . "
                    </p>
                </div>
                
                <div style='text-align: center; margin-top: 20px; font-size: 14px;'>
                    <p>" . nl2br(htmlspecialchars($store['receipt_info'])) . "</p>
                    <p>Terima kasih atas kunjungan Anda</p>
                    <p>Barang yang sudah dibeli tidak dapat dikembalikan</p>
                </div>
            </div>";
        
        $mail->Body = $receipt_html;
        
        $mail->send();
        echo json_encode(['success' => true, 'message' => 'Struk berhasil dikirim ke email']);
        exit;
    } catch (Exception $e) {
        error_log("Error in send_email: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage(),
            'debug' => [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]
        ]);
        exit;
    }
}

// Regular page load below this point
$invoice_number = $_GET['invoice_number'] ?? null;

if (!$invoice_number) {
    header('Location: transaction.php');
    exit;
}

// Get transaction details
$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->prepare("
    SELECT t.*, u.full_name as cashier_name 
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.invoice_number = ?
");
$stmt->execute([$invoice_number]);
$transaction = $stmt->fetch();

//get store info
$stmt = $conn->prepare("SELECT * FROM stores WHERE id = ?");
$stmt->execute([$_SESSION['store_id']]);
$store = $stmt->fetch();

// Get transaction items
$stmt = $conn->prepare("
    SELECT ti.*, p.name as product_name, u.name as unit_name
    FROM transaction_items ti
    JOIN products p ON ti.product_id = p.id
    LEFT JOIN units u ON p.unit_id = u.id
    WHERE ti.transaction_id = ?
");
$stmt->execute([$transaction['id']]);
$items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Struk - <?php echo $invoice_number; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">    
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none;
            }
            .print-only {
                display: block;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Print Options -->
    <div class="no-print container mx-auto px-4 py-8">
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold mb-6 text-center">Opsi Kirim Struk</h2>
            
            <!-- Print Button -->
            <button onclick="printReceipt()" 
                    class="w-full mb-4 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                <i class="fas fa-print mr-2"></i> Cetak Struk
            </button>
            
            <!-- WhatsApp Form -->
            <div class="mt-4">
                <label for="phone" class="block text-sm font-medium text-gray-700">
                    Nomor WhatsApp
                </label>
                <div class="mt-1 flex rounded-md shadow-sm">
                    <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
                        +62
                    </span>
                    <input type="tel" 
                           id="phone" 
                           name="phone" 
                           class="focus:ring-blue-500 focus:border-blue-500 flex-1 block w-full rounded-none rounded-r-md sm:text-sm border-gray-300" 
                           placeholder="8123456789">
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    Masukkan nomor tanpa awalan 0 atau +62
                </p>
                
                <!-- WhatsApp Button -->
                <button onclick="sendWhatsApp()" 
                        class="w-full mt-3 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                    <i class="fab fa-whatsapp mr-2"></i> Kirim via WhatsApp
                </button>
            </div>

            <!-- Email Form -->
            <div class="mt-4">
                <form id="emailForm" onsubmit="return sendEmail(event)">
                    <label for="email" class="block text-sm font-medium text-gray-700">
                        Kirim via Email (akan datang)
                    </label>
                    <!--<div class="mt-1">
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="focus:ring-blue-500 focus:border-blue-500 block w-full rounded-md sm:text-sm border-gray-300" 
                               placeholder="customer@example.com"
                               required>
                    </div>
                    <p class="mt-2 text-sm text-gray-500">
                        Masukkan alamat email untuk menerima struk
                    </p>
                    
                    <button type="submit" 
                            class="w-full mt-3 bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">
                        <i class="fas fa-envelope mr-2"></i> Kirim via Email
                    </button>-->
                </form>
            </div>

            <!-- Back Button -->
            <div class="mt-6 text-center">
                <a href="transaction" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-arrow-left mr-2"></i> Kembali ke Halaman Transaksi
                </a>
            </div>
        </div>
    </div>

    <!-- Receipt Template -->
    <div id="receiptTemplate" class="hidden print-only">
        <div class="text-center p-4">
            <!-- Logo -->
            <img src="<?php echo !empty($store['logo']) ? $store['logo'] : 'assets/img/default-logo.png'; ?>" 
                 alt="Logo Toko" class="mx-auto mb-4 h-16">
            
            <h2 class="text-xl font-bold mb-2"><?php echo $store['name']; ?></h2>
            <p class="text-sm mb-4">Tanggal Transaksi: <?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?></p>
            <p class="text-sm mb-2">No. Transaksi: <?php echo $invoice_number; ?></p>
            <p class="text-sm mb-4">Kasir: <?php echo $transaction['cashier_name']; ?></p>

            <!-- Items -->
            <div class="border-t border-b border-gray-300 py-4 my-4">
                <?php foreach ($items as $item): ?>
                <div class="flex justify-between text-sm mb-1">
                    <div class="text-left">
                        <?php echo $item['product_name']; ?><br>
                        <span class="text-xs">
                            <?php echo $item['quantity'] . ' ' . $item['unit_name'] . ' × ' . 
                                      number_format($item['price'], 0, ',', '.'); ?>
                        </span>
                    </div>
                    <div class="text-right">
                        Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Totals -->
            <div class="text-right space-y-1">
                <p class="font-bold">Total: Rp <?php echo number_format($transaction['total_amount'], 0, ',', '.'); ?></p>
                <p>Tunai: Rp <?php echo number_format($transaction['paid_amount'], 0, ',', '.'); ?></p>
                <p>Kembali: Rp <?php echo number_format($transaction['change_amount'], 0, ',', '.'); ?></p>
            </div>
            
            <div class="mt-8 text-sm">
                <p><?php echo $store['receipt_info']; ?></p>
                <p>Terima kasih atas kunjungan Anda</p>
                <p>Barang yang sudah dibeli tidak dapat dikembalikan</p>
            </div>
        </div>
    </div>

    <script>
        // Print receipt
        function printReceipt() {
            window.print();
        }

        async function sendWhatsApp() {
            const phone = document.getElementById('phone').value;
            if (!phone) {
                alert('Mohon isi nomor WhatsApp');
                return;
            }

            // Buat pesan struk
            const message = document.getElementById('receipt_template').value;
            
            try {
                // Encode data sebagai URL parameters
                const params = new URLSearchParams();
                params.append('phone', phone.trim());
                params.append('message', message.trim());

                console.log('Sending data:', {
                    phone: phone.trim(),
                    message: message.trim()
                });

                const response = await fetch('send-whatsapp.php?' + params.toString(), {
                    method: 'GET'
                });

                // Debug: tampilkan response mentah
                const rawResponse = await response.text();
                console.log('Raw response:', rawResponse);

                // Parse response sebagai JSON
                const result = JSON.parse(rawResponse);
                
                if (result.success) {
                    alert('Struk berhasil dikirim via WhatsApp');
                } else {
                    alert('Gagal mengirim struk: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                alert('Terjadi kesalahan saat mengirim struk. Lihat console untuk detail.');
            }
        }

        async function sendEmail(event) {
            event.preventDefault();
            
            const email = document.getElementById('email').value;
            console.log('Attempting to send email to:', email);
            
            if (!email) {
                showToast('Mohon isi alamat email', 'error');
                return false;
            }

            try {
                console.log('Sending request with email:', email.trim());
                
                const response = await fetch('ajax/send_email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: email.trim(),
                        invoice_number: '<?= $invoice_number ?>'
                    })
                });

                const result = await response.json();
                console.log('Server response:', result);
                
                if (result.success) {
                    showToast(result.message, 'success');
                    document.getElementById('email').value = '';
                } else {
                    showToast(result.message || 'Gagal mengirim email', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Terjadi kesalahan saat mengirim email', 'error');
            }
            
            return false;
        }

        function showToast(message, type = 'error') {
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 z-50 rounded-lg px-4 py-3 shadow-lg transform transition-all duration-300 ${
                type === 'error' ? 'bg-red-500' : 'bg-green-500'
            } text-white`;
            toast.innerHTML = `
                <div class="flex items-center space-x-2">
                    <i class="fas ${type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
    </script>

    <!-- Template pesan -->
    <input type="hidden" id="receipt_template" value="<?= 
        htmlspecialchars(
            "Struk Pembelian\n\n" .
            "*{$store['name']}*\n" .
            date('d/m/Y H:i', strtotime($transaction['created_at'])) . "\n" .
            "No: {$transaction['invoice_number']}\n" .
            "Kasir: {$transaction['cashier_name']}\n\n" .
            implode("\n", array_map(function($item) {
                return "{$item['product_name']}\n" .
                       "{$item['quantity']} {$item['unit_name']} x Rp " . number_format($item['price']) . "\n" .
                       "Subtotal: Rp " . number_format($item['subtotal']) . "\n";
            }, $items)) .
            "\n------------------------\n" .
            "Total: Rp " . number_format($transaction['total_amount']) . "\n" .
            "Bayar: Rp " . number_format($transaction['paid_amount']) . "\n" .
            "Kembali: Rp " . number_format($transaction['change_amount']) . "\n" .
            (!empty($store['receipt_info']) ? "\n------------------------\n{$store['receipt_info']}" : "")
        );
    ?>" />
</body>
</html> 