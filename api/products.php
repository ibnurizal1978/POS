<?php
header("Content-Type: application/json");

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get store ID from the URL or session
$store_id = defined('STORE_ID') ? STORE_ID : null;

if ($store_id === null) {
    http_response_code(400);
    echo json_encode(["message" => "Store ID not specified"]);
    exit();
}

// Get all products for the store
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['id'])) {
    try {
        $stmt = $db->prepare("
            SELECT p.*, 
                   GROUP_CONCAT(pp.photo_name) as additional_photos
            FROM products p
            LEFT JOIN product_photos pp ON p.id = pp.product_id
            WHERE p.store_id = :store_id
            AND p.is_active = 1
            GROUP BY p.id
        ");
        $stmt->bindParam(':store_id', $store_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $products = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = [
                "id" => $row['id'],
                "product_name" => $row['name'],
                "selling_price" => $row['selling_price'],
                "promo_price" => $row['promo_price'],
                "main_photo" => $row['photo'],
                "additional_photos" => $row['additional_photos'] ? 
                    explode(',', $row['additional_photos']) : 
                    []
            ];
        }
        
        echo json_encode($products);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["message" => "Database error: " . $e->getMessage()]);
    }
    exit();
}

// Get single product
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $product_id = $_GET['id'];
    
    if (!is_numeric($product_id)) {
        http_response_code(400);
        echo json_encode(["message" => "Invalid product ID"]);
        exit();
    }
    
    try {
        $stmt = $db->prepare("
            SELECT p.*, 
                   GROUP_CONCAT(pp.photo_name) as additional_photos
            FROM products p
            LEFT JOIN product_photos pp ON p.id = pp.product_id
            WHERE p.id = :product_id
            AND p.store_id = :store_id
            LIMIT 1
        ");
        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt->bindParam(':store_id', $store_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $response = [
                "id" => $product['id'],
                "product_name" => $product['name'],
                "selling_price" => $product['selling_price'],
                "promo_price" => $product['promo_price'],
                "main_photo" => $product['photo'],
                "additional_photos" => $product['additional_photos'] ? 
                    explode(',', $product['additional_photos']) : 
                    []
            ];
            echo json_encode($response);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Product not found"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["message" => "Database error: " . $e->getMessage()]);
    }
    exit();
}