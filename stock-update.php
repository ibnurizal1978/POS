<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/auth_helper.php';
require_once 'includes/check_session.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit;
}

// Terima data JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['product_id'], $data['quantity'], $data['type'])) {
    http_response_code(400);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

try {
    $conn->beginTransaction();

    // Update stok produk
    $stmt = $conn->prepare("
        UPDATE products 
        SET stock = CASE 
            WHEN ? = 'IN' THEN stock + ? 
            WHEN ? = 'OUT' THEN stock - ?
            ELSE stock 
        END 
        WHERE id = ? AND store_id = ?
    ");
    $stmt->execute([
        $data['type'], $data['quantity'],
        $data['type'], $data['quantity'],
        $data['product_id'], $_SESSION['store_id']
    ]);

    // Catat history stok
    $stmt = $conn->prepare("
        INSERT INTO stock_history (
            product_id, type, quantity, description, 
            created_by, store_id
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    // Debug values before execute
    error_log("Inserting stock history with values:");
    error_log("product_id: " . $data['product_id']);
    error_log("type: " . $data['type']); 
    error_log("quantity: " . $data['quantity']);
    error_log("description: " . ($data['description'] ?? 'null'));
    error_log("user_id: " . $_SESSION['user_id']);
    error_log("store_id: " . $_SESSION['store_id']);

    $result = $stmt->execute([
        $data['product_id'],
        $data['type'],
        $data['quantity'], 
        $data['description'] ?? null,
        $_SESSION['user_id'],
        $_SESSION['store_id']
    ]);

    if (!$result) {
        error_log("Failed to insert stock history: " . implode(" ", $stmt->errorInfo()));
        throw new Exception("Failed to insert stock history");
    }

    $conn->commit();
    echo json_encode(['success' => true]);
    console.log($stmt); // Debugging

} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 