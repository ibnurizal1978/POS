<?php
require_once '../config/database.php';
require_once '../config/config.php';

class PasswordReset {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function requestReset($email) {
        try {
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if (!$stmt->fetch()) {
                return ['success' => false, 'message' => 'Email tidak ditemukan'];
            }

            // Generate token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + TOKEN_EXPIRY);

            // Save reset request
            $stmt = $this->conn->prepare("
                INSERT INTO password_resets (email, token, expires_at)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$email, $token, $expires]);

            // Send email
            $this->sendResetEmail($email, $token);

            return ['success' => true, 'message' => 'Instruksi reset password telah dikirim ke email Anda'];

        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
        }
    }

    private function sendResetEmail($email, $token) {
        $resetLink = BASE_URL . "/auth/reset-password?token=" . $token;
        
        // Implement your email sending logic here
        // Gunakan PHPMailer atau library email lainnya
    }
}

// Handle forgot password request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reset = new PasswordReset();
    $result = $reset->requestReset($_POST['email'] ?? '');
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
} 