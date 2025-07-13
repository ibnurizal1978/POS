<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Check if store exists
    $stmt = $conn->prepare("SELECT id FROM stores WHERE name = 'Toko Demo'");
    $stmt->execute();
    $store = $stmt->fetch();

    if (!$store) {
        // Insert store first
        $stmt = $conn->prepare("
            INSERT INTO stores (name, address, phone, email) 
            VALUES ('Toko Demo', 'Alamat Toko Demo', '08123456789', 'demo@tokodemo.com')
        ");
        $stmt->execute();
        $storeId = $conn->lastInsertId();
    } else {
        $storeId = $store['id'];
    }

    // Check if user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = 'toko'");
    $stmt->execute();
    $user = $stmt->fetch();

    if (!$user) {
        // Create password hash
        $password = "Demo123";
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        $stmt = $conn->prepare("
            INSERT INTO users (
                username, 
                email, 
                password, 
                full_name, 
                role, 
                store_id, 
                is_active, 
                email_verified
            ) VALUES (
                'toko',
                'demo@tokodemo.com',
                :password,
                'Admin Toko',
                'owner',
                :store_id,
                1,
                1
            )
        ");
        
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':store_id', $storeId);
        $stmt->execute();

        echo "User berhasil dibuat!<br>";
        echo "Username: toko<br>";
        echo "Password: Demo123<br>";
    } else {
        // Update existing user's password
        $password = "Demo123";
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
            UPDATE users 
            SET password = :password,
                is_active = 1,
                email_verified = 1
            WHERE username = 'toko'
        ");
        
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->execute();

        echo "Password user 'toko' berhasil direset!<br>";
        echo "Username: toko<br>";
        echo "Password: Demo123<br>";
    }

    echo "<br>Silakan kembali ke <a href='index.php'>halaman login</a>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 