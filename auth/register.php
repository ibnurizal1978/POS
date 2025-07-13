<?php
require_once '../config/database.php';
require_once '../config/config.php';

class Register {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function createUser($username, $email, $password, $fullName) {
        try {
            // Validate input
            if (!$this->validateInput($username, $email, $password)) {
                return ['success' => false, 'message' => 'Input tidak valid'];
            }

            // Check existing username/email
            if ($this->userExists($username, $email)) {
                return ['success' => false, 'message' => 'Username atau email sudah terdaftar'];
            }

            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT, ['cost' => HASH_COST]);
            
            // Generate verification token
            $verificationToken = bin2hex(random_bytes(32));

            // Insert user
            $stmt = $this->conn->prepare("
                INSERT INTO users (username, email, password, full_name, verification_token)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $email, $hashedPassword, $fullName, $verificationToken]);

            // Send verification email
            $this->sendVerificationEmail($email, $verificationToken);

            return ['success' => true, 'message' => 'Registrasi berhasil. Silakan cek email Anda untuk verifikasi.'];

        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
        }
    }

    private function validateInput($username, $email, $password) {
        return (
            strlen($username) >= 3 &&
            filter_var($email, FILTER_VALIDATE_EMAIL) &&
            strlen($password) >= 8
        );
    }

    private function userExists($username, $email) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM users 
            WHERE username = ? OR email = ?
        ");
        $stmt->execute([$username, $email]);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    }

    private function sendVerificationEmail($email, $token) {
        $verificationLink = BASE_URL . "/auth/verify-email?token=" . $token;
        
        // Implement your email sending logic here
        // Gunakan PHPMailer atau library email lainnya
    }
}

// Handle registration request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $register = new Register();
    $result = $register->createUser(
        $_POST['username'] ?? '',
        $_POST['email'] ?? '',
        $_POST['password'] ?? '',
        $_POST['full_name'] ?? ''
    );
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
} 