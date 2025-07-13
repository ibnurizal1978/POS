<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/auth_helper.php';
require_once 'includes/check_session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        // Handle delete action
        if (isset($_POST['action']) && $_POST['action'] === 'delete') {
            $stmt = $conn->prepare("
                DELETE FROM product_discounts 
                WHERE id = ? AND store_id = ?
            ");
            
            $stmt->execute([
                $_POST['id'],
                $_SESSION['store_id']
            ]);

            $_SESSION['success'] = "Diskon berhasil dihapus";
            header('Location: product-discounts');
            exit;
        }

        // Validasi input
        if (empty($_POST['product_id'])) throw new Exception("Produk harus dipilih");
        if (empty($_POST['min_qty'])) throw new Exception("Minimum pembelian harus diisi");
        if ($_POST['min_qty'] < 2) throw new Exception("Minimum pembelian harus lebih dari 1");
        if (empty($_POST['discount_type'])) throw new Exception("Tipe diskon harus dipilih");
        if (empty($_POST['discount_value'])) throw new Exception("Nilai diskon harus diisi");
        if (empty($_POST['start_date'])) throw new Exception("Tanggal mulai harus diisi");
        if (empty($_POST['end_date'])) throw new Exception("Tanggal selesai harus diisi");

        // Validasi nilai diskon
        if ($_POST['discount_type'] === 'percentage' && $_POST['discount_value'] > 100) {
            throw new Exception("Persentase diskon tidak boleh lebih dari 100%");
        }

        // Cek apakah sudah ada diskon untuk produk ini di periode yang sama
        $stmt = $conn->prepare("
            SELECT id FROM product_discounts 
            WHERE product_id = ? 
            AND store_id = ?
            AND (
                (? BETWEEN start_date AND end_date)
                OR
                (? BETWEEN start_date AND end_date)
            )
        ");
        
        $stmt->execute([
            $_POST['product_id'],
            $_SESSION['store_id'],
            $_POST['start_date'],
            $_POST['end_date']
        ]);

        if ($stmt->fetch()) {
            throw new Exception("Sudah ada diskon untuk produk ini di periode yang dipilih");
        }

        // Insert diskon baru
        $stmt = $conn->prepare("
            INSERT INTO product_discounts (
                store_id, product_id, min_qty,
                discount_type, discount_value,
                start_date, end_date, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $_SESSION['store_id'],
            $_POST['product_id'],
            $_POST['min_qty'],
            $_POST['discount_type'],
            $_POST['discount_value'],
            $_POST['start_date'],
            $_POST['end_date']
        ]);

        $_SESSION['success'] = "Diskon berhasil ditambahkan";
        header('Location: product-discounts');
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: product-discounts');
        exit;
    }
}

$_SESSION['error'] = "Metode request tidak valid";
header('Location: product-discounts');
exit; 