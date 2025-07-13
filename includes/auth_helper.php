<?php

// Fungsi untuk mengecek apakah user sudah login
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Fungsi untuk mengecek role user
function hasRole($allowed_roles) {
    if (!isLoggedIn()) return false;
    
    // Jika parameter adalah string, ubah ke array
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    
    return in_array($_SESSION['role'], $allowed_roles);
}

// Fungsi untuk redirect jika tidak punya akses
function requireRole($allowed_roles) {
    if (!hasRole($allowed_roles)) {
        header("Location: dashboard");
        exit;
    }
}

// Daftar menu yang bisa diakses per role
function getAccessibleMenus($role) {
    $menus = [];
    
    switch ($role) {
        case 'owner':
        case 'admin':
            // Admin dan owner bisa akses semua menu
            $menus = [
                'dashboard' => true,
                'products' => true,
                'categories' => true,
                'units' => true,
                'users' => true,
                'transactions' => true,
                'orders' => true,
                'reports' => true,
                'promotions' => true,
                'settings' => true,
                'grosir' => true,
                'product-discounts' => true,
                'promo' => true
            ];
            break;
            
        case 'cashier':
        case 'staff':
            // Kasir hanya bisa akses menu tertentu
            $menus = [
                'dashboard' => true,
                'transactions' => true,
                'orders' => true
            ];
            break;
            
        default:
            $menus = [];
    }
    
    return $menus;
}

// Fungsi untuk mengecek apakah menu tertentu bisa diakses
function canAccessMenu($menu) {
    if (!isLoggedIn()) return false;
    
    $accessible_menus = getAccessibleMenus($_SESSION['role']);
    return isset($accessible_menus[$menu]) && $accessible_menus[$menu];
} 