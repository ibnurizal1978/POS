<?php
function getUserTimezone() {
    // Ambil timezone dari session jika sudah ada
    if (isset($_SESSION['user_timezone'])) {
        return $_SESSION['user_timezone'];
    }
    
    // Default ke Asia/Jakarta jika tidak ada
    return 'Asia/Jakarta';
}

function convertToUTC($datetime, $fromTimezone = null) {
    if (!$fromTimezone) {
        $fromTimezone = getUserTimezone();
    }
    
    $dt = new DateTime($datetime, new DateTimeZone($fromTimezone));
    $dt->setTimezone(new DateTimeZone('UTC'));
    return $dt->format('Y-m-d H:i:s');
}

function convertFromUTC($datetime, $toTimezone = null) {
    if (!$toTimezone) {
        $toTimezone = getUserTimezone();
    }
    
    $dt = new DateTime($datetime, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone($toTimezone));
    return $dt->format('Y-m-d H:i:s');
}

// Format datetime untuk display dengan timezone yang benar
function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    $dt = new DateTime($datetime, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone(getUserTimezone()));
    return $dt->format($format);
} 