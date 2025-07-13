<?php
require_once '../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // 0. Bersihkan format nomor telepon (hapus - dan tanda kurung)
    $stmt = $conn->prepare("
        UPDATE leads 
        SET phone = REPLACE(
            REPLACE(
                REPLACE(
                    REPLACE(phone, '-', ''),
                    '(', ''
                ),
                ')', ''
            ),
            ' ', ''
        )
    ");
    $stmt->execute();
    echo "Berhasil membersihkan format nomor telepon.\n\n";

    // 1. Hapus nomor telepon yang diawali 021
    $stmt = $conn->prepare("
        DELETE FROM leads 
        WHERE phone NOT LIKE '08%'
    ");
    $deleted = $stmt->execute();
    $deletedCount = $stmt->rowCount();
    echo "Berhasil menghapus {$deletedCount} data dengan nomor telepon yang diawali 021.\n\n";

    // 2. Temukan data duplikat
    $findDuplicates = $conn->query("
        SELECT 
            phone,
            COUNT(*) as count,
            GROUP_CONCAT(id) as duplicate_ids
        FROM leads 
        WHERE phone != ''
        AND phone IS NOT NULL
        GROUP BY phone 
        HAVING COUNT(*) > 1
    ");
    
    $duplicates = $findDuplicates->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicates)) {
        echo "Tidak ditemukan data duplikat.\n";
        exit;
    }
    
    echo "Ditemukan " . count($duplicates) . " nomor telepon duplikat.\n\n";
    
    // 3. Proses penghapusan duplikat
    $conn->beginTransaction();
    
    try {
        foreach ($duplicates as $duplicate) {
            // Ambil semua ID untuk nomor telepon ini
            $ids = explode(',', $duplicate['duplicate_ids']);
            
            // Simpan ID pertama (yang akan dipertahankan)
            $keepId = array_shift($ids);
            
            // Hapus sisanya
            if (!empty($ids)) {
                $deleteIds = implode(',', $ids);
                $stmt = $conn->prepare("DELETE FROM leads WHERE id IN ($deleteIds)");
                $stmt->execute();
                
                echo "Nomor {$duplicate['phone']}: Mempertahankan ID $keepId, menghapus " . 
                     count($ids) . " data duplikat (ID: $deleteIds)\n";
            }
        }
        
        $conn->commit();
        echo "\nBerhasil menghapus data duplikat!\n";
        
    } catch (Exception $e) {
        $conn->rollBack();
        echo "Error saat menghapus duplikat: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 