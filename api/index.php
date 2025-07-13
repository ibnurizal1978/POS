<?php
// Database connection
require '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get the store slug from URL
$store_slug = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// If no slug, redirect to default store or show error
if (empty($store_slug)) {
    header("HTTP/1.0 404 Not Found");
    echo "Store not found";
    exit();
}

// Get store ID from database
try {
    $stmt = $db->prepare("SELECT id FROM stores WHERE slug = :slug LIMIT 1");
    $stmt->bindParam(':slug', $store_slug);
    $stmt->execute();
    
    $store = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($store) {
        // Store the store ID in a constant or session
        define('STORE_ID', $store['id']);
        
        // Include your React app's index.html
        include_once '../ordermatix-catalog/public/index.html';
    } else {
        // Store not found
        header("HTTP/1.0 404 Not Found");
        echo "Store not found";
    }
} catch (PDOException $e) {
    header("HTTP/1.0 500 Internal Server Error");
    echo "Database error: " . $e->getMessage();
}