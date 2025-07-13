<?php
function calculatePromotion($product_id, $quantity, $unit_price) {
    global $conn;
    
    // Cek apakah ada promo aktif untuk produk ini
    $stmt = $conn->prepare("
        SELECT buy_qty, free_qty
        FROM promotions
        WHERE product_id = ?
        AND status = 'active'
        AND CURDATE() BETWEEN start_date AND end_date
        LIMIT 1
    ");
    $stmt->execute([$product_id]);
    $promo = $stmt->fetch();
    
    if (!$promo) {
        // Tidak ada promo, harga normal
        return [
            'final_quantity' => $quantity,
            'discount_quantity' => 0,
            'total_price' => $quantity * $unit_price,
            'discount_amount' => 0,
            'has_promo' => false
        ];
    }
    
    // Hitung berapa set promo yang didapat
    $promo_sets = floor($quantity / ($promo['buy_qty'] + $promo['free_qty']));
    $remaining_qty = $quantity % ($promo['buy_qty'] + $promo['free_qty']);
    
    // Hitung total barang yang dibayar
    $paid_qty = ($promo_sets * $promo['buy_qty']) + min($remaining_qty, $promo['buy_qty']);
    
    // Hitung total diskon
    $discount_qty = $quantity - $paid_qty;
    $discount_amount = $discount_qty * $unit_price;
    
    return [
        'final_quantity' => $paid_qty,
        'discount_quantity' => $discount_qty,
        'total_price' => $paid_qty * $unit_price,
        'discount_amount' => $discount_amount,
        'has_promo' => true,
        'promo_text' => "Beli {$promo['buy_qty']} Gratis {$promo['free_qty']}"
    ];
}

function calculateDiscount($product_id, $quantity, $total_price) {
    global $conn;
    
    // Cek apakah ada diskon aktif untuk produk ini
    $stmt = $conn->prepare("
        SELECT discount_type, min_qty, discount_value
        FROM discounts
        WHERE product_id = ?
        AND status = 'active'
        AND CURDATE() BETWEEN start_date AND end_date
        AND min_qty <= ?  -- Hanya ambil diskon yang sesuai minimum qty
        ORDER BY min_qty DESC  -- Ambil yang minimum qty terbesar
        LIMIT 1
    ");
    $stmt->execute([$product_id, $quantity]);
    $discount = $stmt->fetch();
    
    if (!$discount) {
        // Tidak ada diskon yang aktif
        return [
            'discount_amount' => 0,
            'final_price' => $total_price,
            'has_discount' => false
        ];
    }
    
    // Hitung diskon
    $discount_amount = 0;
    if ($discount['discount_type'] === 'percentage') {
        $discount_amount = $total_price * ($discount['discount_value'] / 100);
        $discount_text = "Diskon {$discount['discount_value']}%";
    } else {
        $discount_amount = $discount['discount_value'];
        $discount_text = "Diskon Rp " . number_format($discount['discount_value']);
    }
    
    return [
        'discount_amount' => $discount_amount,
        'final_price' => $total_price - $discount_amount,
        'has_discount' => true,
        'discount_text' => $discount_text,
        'min_qty' => $discount['min_qty']
    ];
}

// Function untuk menghitung total dengan promo dan diskon
function calculateFinalPrice($product_id, $quantity, $unit_price) {
    // Hitung promo dulu (beli x gratis y)
    $promo_result = calculatePromotion($product_id, $quantity, $unit_price);
    
    // Hitung diskon berdasarkan hasil setelah promo
    $discount_result = calculateDiscount(
        $product_id, 
        $promo_result['final_quantity'], 
        $promo_result['total_price']
    );
    
    return [
        'original_quantity' => $quantity,
        'final_quantity' => $promo_result['final_quantity'],
        'unit_price' => $unit_price,
        'subtotal' => $quantity * $unit_price,
        'promo_discount' => $promo_result['discount_amount'],
        'has_promo' => $promo_result['has_promo'],
        'promo_text' => $promo_result['promo_text'] ?? null,
        'price_discount' => $discount_result['discount_amount'],
        'has_discount' => $discount_result['has_discount'],
        'discount_text' => $discount_result['discount_text'] ?? null,
        'final_price' => $discount_result['final_price']
    ];
} 