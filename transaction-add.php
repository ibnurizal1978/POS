<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/auth_helper.php';
require_once 'includes/promotion_helper.php';
session_start();

// Saat menambah item ke keranjang
$product_id = $_POST['product_id'];
$quantity = $_POST['quantity'];
$unit_price = $product['selling_price'];

// Hitung promo dan diskon
$final_result = calculateFinalPrice($product_id, $quantity, $unit_price);

// Simpan ke temporary_cart dengan info promo dan diskon
$stmt = $conn->prepare("
    INSERT INTO temporary_cart (
        session_id, store_id, product_id, quantity, 
        price, discount_qty, discount_amount, 
        has_promo, promo_text,
        has_discount, discount_text
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    session_id(),
    $_SESSION['store_id'],
    $product_id,
    $quantity,
    $unit_price,
    $final_result['original_quantity'] - $final_result['final_quantity'],
    $final_result['promo_discount'] + $final_result['price_discount'],
    $final_result['has_promo'],
    $final_result['promo_text'],
    $final_result['has_discount'],
    $final_result['discount_text']
]); 