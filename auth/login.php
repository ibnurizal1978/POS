<?php
require_once '../config/database.php';
require_once '../config/config.php';

class Login {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function authenticate($username, $password) {
        try {
            // Check login attempts
            if ($this->isUserLocked($_SERVER['REMOTE_ADDR'])) {
                return ['success' => false, 'message' => 'Terlalu banyak percobaan. Silakan coba lagi nanti.'];
            }

            $stmt = $this->conn->prepare("SELECT * FROM users WHERE username = ? OR email = ? AND is_active = 1");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if (!$user['is_active']) {
                    return ['success' => false, 'message' => 'Akun Anda tidak aktif.'];
                }

                //generate store name
                $stmt = $this->conn->prepare("SELECT name FROM stores WHERE id = ?");
                $stmt->execute([$user['store_id']]);
                $store = $stmt->fetch();
                
                // Record successful login
                $this->recordLoginAttempt($user['id'], true);

                // Set session
                $_SESSION['store_name'] = $store['name'];
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['store_id'] = $user['store_id'];
                $_SESSION['name'] = $user['full_name'];
                
                return ['success' => true, 'message' => 'Login berhasil'];
            }

            // Record failed login
            $this->recordLoginAttempt(null, false);
            
            return ['success' => false, 'message' => 'Username atau password salah'];

        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
        }
    }

    private function isUserLocked($ip) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE ip_address = ? 
            AND success = 0 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$ip, LOCKOUT_TIME]);
        $result = $stmt->fetch();
        
        return $result['attempts'] >= MAX_LOGIN_ATTEMPTS;
    }

    private function recordLoginAttempt($user_id, $success) {
        $stmt = $this->conn->prepare("
            INSERT INTO login_attempts (user_id, ip_address, success) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$user_id, $_SERVER['REMOTE_ADDR'], $success]);
    }
}

// Handle login request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = new Login();
    $result = $login->authenticate(
        $_POST['username'] ?? '',
        $_POST['password'] ?? ''
    );
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
} 