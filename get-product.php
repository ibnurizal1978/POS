<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/auth_helper.php';
require_once 'includes/check_session.php';

// Debug: Log request method
error_log('Request Method: ' . $_SERVER['REQUEST_METHOD']);

// Debug: Log headers
error_log('Request Headers: ' . print_r(getallheaders(), true));

// Debug: Log raw input
$raw_input = file_get_contents('php://input');
error_log('Raw input: ' . $raw_input);

// Cek apakah pengguna sudah login
if (!isLoggedIn()) {
    http_response_code(401);
    exit;
}

// Parse JSON input
$data = null;
if (!empty($raw_input)) {
    $data = json_decode($raw_input, true);
    error_log('Parsed JSON data: ' . print_r($data, true));
}

// Get ID from various sources
$id = null;
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    error_log('ID from GET: ' . $id);
} elseif (isset($data['id'])) {
    $id = $data['id'];
    error_log('ID from JSON: ' . $id);
} elseif (isset($_POST['id'])) {
    $id = $_POST['id'];
    error_log('ID from POST: ' . $id);
}

// Get barcode from GET
$barcode = $_GET['barcode'] ?? null;
error_log('Barcode: ' . $barcode);
error_log('Final ID: ' . $id);

// Validate input
if (!$barcode && !$id) {
    error_log('Error: No barcode or ID provided');
    http_response_code(400);
    echo json_encode(['error' => 'Either barcode or product ID is required']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Build query
$where = [];
$params = [];

if ($barcode) {
    $where[] = "p.barcode = ?";
    $params[] = $barcode;
}
if ($id) {
    $where[] = "p.id = ?";
    $params[] = $id;
}
$params[] = $_SESSION['store_id'];

$whereClause = implode(" OR ", $where);

$query = "SELECT p.*, u.name as unit_name 
    FROM products p
    LEFT JOIN units u ON p.unit_id = u.id
    WHERE ($whereClause) AND p.store_id = ?";

// Debug: Log final query
error_log('Query: ' . $query);
error_log('Params: ' . print_r($params, true));

$stmt = $conn->prepare($query);
$stmt->execute($params);
$product = $stmt->fetch();

// Debug: Log result
error_log('Query result: ' . print_r($product, true));

// Function to get wholesale price if available
function getWholesalePrice($product_id, $quantity, $conn) {
    error_log("Checking wholesale price for product_id: $product_id, quantity: $quantity");
    
    $stmt = $conn->prepare("
        SELECT wpd.price 
        FROM wholesale_prices wp
        JOIN wholesale_price_details wpd ON wpd.wholesale_id = wp.id
        WHERE wp.product_id = ?
        AND wp.store_id = ?
        AND wp.is_active = 1
        AND CURRENT_DATE BETWEEN wp.start_date AND wp.end_date
        AND wpd.min_qty <= ?
        ORDER BY wpd.min_qty DESC
        LIMIT 1
    ");
    
    $stmt->execute([$product_id, $_SESSION['store_id'], $quantity]);
    $result = $stmt->fetch();
    
    error_log("Wholesale price result: " . print_r($result, true));
    return $result ? $result['price'] : null;
}

if ($product) {
    // Get quantity from request, default to 1 if not provided
    $quantity = isset($_GET['quantity']) ? (int)$_GET['quantity'] : 1;
    error_log("Processing product ID: {$product['id']}, Quantity: $quantity");
    
    // Check for wholesale price
    $wholesale_price = getWholesalePrice($product['id'], $quantity, $conn);
    error_log("Wholesale price found: " . ($wholesale_price ?? 'null'));
    
    // If wholesale price exists, update the product price
    if ($wholesale_price !== null) {
        error_log("Applying wholesale price. Original: {$product['selling_price']}, Wholesale: $wholesale_price");
        $product['original_price'] = $product['selling_price'];
        $product['selling_price'] = $wholesale_price;
        $product['is_wholesale'] = true;
    } else {
        error_log("No wholesale price found, using regular price: {$product['selling_price']}");
        $product['is_wholesale'] = false;
    }
    
    error_log("Final product data: " . print_r($product, true));
    echo json_encode($product);
} else {
    error_log("No product found");
    echo json_encode(null);
}
?> 