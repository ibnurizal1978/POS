<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/auth_helper.php';
session_start();

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if request is POST and has JSON content
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || 
    !isset($_SERVER['CONTENT_TYPE']) || 
    strpos($_SERVER['CONTENT_TYPE'], 'application/json') === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Get JSON data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['items']) || empty($data['items'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Start transaction
    $conn->beginTransaction();
    
    // Generate invoice number
    $prefix = date('Ymd');
    $random = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    $invoice_number = $prefix . $random;
    
    // Insert transaction
    $stmt = $conn->prepare("
        INSERT INTO transactions (
            store_id, 
            user_id, 
            invoice_number, 
            total_amount, 
            paid_amount, 
            change_amount
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $_SESSION['store_id'],
        $_SESSION['user_id'],
        $invoice_number,
        $data['total'],
        $data['paid_amount'],
        $data['change_amount']
    ]);
    
    $transaction_id = $conn->lastInsertId();
    
    // Insert transaction items and update stock
    $stmt_items = $conn->prepare("
        INSERT INTO transaction_items (
            transaction_id, 
            product_id, 
            quantity, 
            price, 
            subtotal, 
            created_at
        ) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())
    ");
    

    /*$stmt_stock = $conn->prepare("
        UPDATE products 
        SET stock = stock - ? 
        WHERE id = ? AND store_id = ?
    ");*/
    
    foreach ($data['items'] as $item) {
        // Insert transaction item
        $stmt_items->execute([
            $transaction_id,
            $item['id'],
            $item['quantity'],
            $item['price'],
            $item['subtotal']
        ]);
        
        // Update stock
        /*$stmt_stock->execute([
            $item['quantity'],
            $item['id'],
            $_SESSION['store_id']
        ]);*/
    }
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'invoice_number' => $invoice_number
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    if ($conn) {
        $conn->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat memproses transaksi'
    ]);
} 