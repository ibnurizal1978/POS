<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/auth_helper.php';
require_once 'includes/check_session.php';

$db = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle delete action
        if (isset($_POST['action']) && $_POST['action'] === 'delete') {
            if (empty($_POST['id'])) {
                throw new Exception("ID promo tidak valid");
            }

            // Pastikan promo ini milik store yang sedang login
            $stmt = $conn->prepare("
                DELETE FROM wholesale_prices 
                WHERE id = ? AND store_id = ?
            ");
            
            $result = $stmt->execute([
                $_POST['id'],
                $_SESSION['store_id']
            ]);

            if (!$result) {
                throw new Exception("Gagal menghapus promo");
            }

            $_SESSION['success'] = "Promo berhasil dihapus";
            header('Location: grosir');
            exit;
        }

        // Handle add promo
        if (!isset($_POST['type'])) {
            throw new Exception("Tipe promo tidak valid");
        }

        if ($_POST['type'] === 'promo') {
            // Validasi input
            if (empty($_POST['product_id'])) {
                throw new Exception("Produk harus dipilih");
            }
            if (empty($_POST['buy_qty'])) {
                throw new Exception("Jumlah beli harus diisi");
            }
            if (empty($_POST['free_qty'])) {
                throw new Exception("Jumlah gratis harus diisi");
            }
            if (empty($_POST['start_date'])) {
                throw new Exception("Tanggal mulai harus diisi");
            }
            if (empty($_POST['end_date'])) {
                throw new Exception("Tanggal selesai harus diisi");
            }

            //check apakah product_id ini sedang promo di tanggal antara tanggal mulai dan tanggal selesai
            $stmt = $conn->prepare("
                SELECT product_id 
                FROM promotions 
                WHERE product_id = ? 
                AND store_id = ? 
                AND promo_type = 'buy_x_get_y'
                AND (
                    (? <= end_date AND ? >= start_date)
                )
            ");
            
            $stmt->execute([$_POST['product_id'], $_SESSION['store_id'], $_POST['start_date'], $_POST['end_date']]);
            $existingPromo = $stmt->fetch();
            if ($existingPromo) {
                throw new Exception("Produk ini sudah memiliki promo beli X gratis Y dalam rentang tanggal yang dipilih");
            }

            //sukses, insert ke database
            $stmt = $conn->prepare("
                INSERT INTO promotions (
                    store_id, product_id, promo_type, 
                    buy_qty, free_qty, start_date, end_date, status
                ) VALUES (?, ?, 'buy_x_get_y', ?, ?, ?, ?, 'active')
            ");
            
            $result = $stmt->execute([
                $_SESSION['store_id'],
                $_POST['product_id'],
                $_POST['buy_qty'],
                $_POST['free_qty'],
                $_POST['start_date'],
                $_POST['end_date']
            ]);

            if (!$result) {
                $_SESSION['error'] = "Gagal menyimpan promo";
                header('Location: promotions');
                exit;
            }

            $_SESSION['success'] = "Promo berhasil ditambahkan";
            header('Location: promotions');
            exit;
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: promotions');
        exit;
    }
}

// Jika bukan POST request
//$_SESSION['error'] = "Metode request tidak valid";
//header('Location: promotions');
//exit;