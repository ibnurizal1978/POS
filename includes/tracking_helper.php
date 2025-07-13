<?php

function logPageAccess($conn) {
    // Skip jika request AJAX atau asset
    if (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
        preg_match('/\.(css|js|jpg|jpeg|png|gif|ico)$/', $_SERVER['REQUEST_URI'])
    ) {
        return;
    }
    
    // Dapatkan nama file tanpa path dan extension
    $page = basename($_SERVER['PHP_SELF'], '.php');
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO page_access_logs (store_id, user_id, page, accessed_at) 
            VALUES (?, ?, ?, UTC_TIMESTAMP())
        ");
        
        $stmt->execute([
            $_SESSION['store_id'] ?? 0,
            $_SESSION['user_id'] ?? 0,
            $page
        ]);
    } catch (Exception $e) {
        // Silent fail - jangan ganggu user experience
        error_log("Failed to log page access: " . $e->getMessage());
    }
} 