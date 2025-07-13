<?php
// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Session configuration
// Pindahkan pengaturan session ke sini, sebelum session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);

// Set session lifetime ke 8 jam
ini_set('session.gc_maxlifetime', 28800); // 8 jam dalam detik
ini_set('session.cookie_lifetime', 28800);

// Atau jika ingin 1 hari penuh
// ini_set('session.gc_maxlifetime', 86400); // 24 jam dalam detik
// ini_set('session.cookie_lifetime', 86400);

// Pastikan ini dijalankan sebelum session_start()
session_set_cookie_params(28800); // 8 jam

// Opsional: tambahkan ini untuk mencegah session expired saat user aktif
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 28800)) {
    session_unset();     // unset $_SESSION variable
    session_destroy();   // destroy session data
    header("Location: ../index"); // redirect ke login
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time(); // update last activity timestamp

// Jangan set session.save_handler di sini
// session.save_handler dan pengaturan session lainnya harus diset di php.ini

// Base URL
define('BASE_URL', 'http://yourdomain.com');

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');

// Security
define('HASH_COST', 12); // untuk password_hash()
define('TOKEN_EXPIRY', 3600); // 1 jam dalam detik
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 menit dalam detik

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
//session_start(); 

//encrypt
class Encryption{

    /**
    *
    *
    * ----------------------------------------------------
    * @param string
    * @return string
    *
    **/
    public static function safe_b64encode($string='') {
        $data = base64_encode($string);
        $data = str_replace(['+','/','='],['-','_',''],$data);
        return $data;
    }

    /**
    *
    *
    * -------------------------------------------------
    * @param string
    * @return string
    *
    **/
    public static function safe_b64decode($string='') {
        $data = str_replace(['-','_'],['+','/'],$string);
        $mod4 = strlen($data) % 4;
        if ($mod4) {
            $data .= substr('====', $mod4);
        }
        return base64_decode($data);
    }

    /**
    *
    *
    * ------------------------------------------------------------------------------------------
    * @param string
    * @return string
    *
    **/
    public static function encode($value=false){
        if(!$value) return false;
        $iv_size = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($iv_size);
        $crypttext = openssl_encrypt($value, 'aes-256-cbc', 'ayamgoreng', OPENSSL_RAW_DATA, $iv);
        return self::safe_b64encode($iv.$crypttext);
    }

    /**
    *
    *
    * ---------------------------------
    * @param string
    * @return string
    *
    **/
    public static function decode($value=false){
        if(!$value) return false;
        $crypttext = self::safe_b64decode($value);
        $iv_size = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($crypttext, 0, $iv_size);
        $crypttext = substr($crypttext, $iv_size);
        if(!$crypttext) return false;
        $decrypttext = openssl_decrypt($crypttext, 'aes-256-cbc', 'ayamgoreng', OPENSSL_RAW_DATA, $iv);
        return rtrim($decrypttext);
    }
}

/*===== clean input ======*/
function input_data($data)
{
$filter = stripslashes(strip_tags(htmlspecialchars($data,ENT_QUOTES)));
return $filter;
}