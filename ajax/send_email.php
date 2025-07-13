<?php
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/auth_helper.php';
require_once '../includes/check_session.php';
require_once '../includes/PHPMailer/PHPMailer.php';
require_once '../includes/PHPMailer/SMTP.php';
require_once '../includes/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $invoice_number = $input['invoice_number'] ?? '';
    
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
    $mail->SMTPDebug = 3;
    $mail->Debugoutput = function($str, $level) {
        error_log("PHPMailer Debug: $str");
    };
    
    // Server settings
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;
    
    // Recipients
    $mail->setFrom(SMTP_FROM_EMAIL, $store['name']);
    $mail->addAddress($email);
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = "Struk Pembelian - {$invoice_number}";
    
    // Generate receipt HTML
    // ... (rest of the email content generation code) ...
    
    $mail->send();
    echo json_encode(['success' => true, 'message' => 'Struk berhasil dikirim ke email']);
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
} 