<?php
require_once '../config/database.php';
require_once '../config/config.php';

// Konfigurasi
$BATCH_SIZE = 10; // Jumlah record per batch
$ADMIN_PHONE = "6287837531238"; // Ganti dengan nomor WA Anda

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // 1. Ambil leads yang belum dikirimi WA
    $stmt = $conn->prepare("
        SELECT id, phone, name 
        FROM leads 
        WHERE send_status = 0
        LIMIT :limit
    ");
    
    $stmt->bindValue(':limit', $BATCH_SIZE, PDO::PARAM_INT);
    $stmt->execute();
    
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($leads)) {
        echo "Tidak ada leads baru yang perlu dikirim WhatsApp.\n";
        exit;
    }
    
    echo "Ditemukan " . count($leads) . " leads untuk diproses.\n\n";
    
    // 2. Kirim WhatsApp untuk setiap lead
    foreach ($leads as $lead) {
        // Format nomor telepon
        $phone = preg_replace('/[^0-9]/', '', $lead['phone']);
        if (substr($phone, 0, 1) === '0') {
            $phone = '62' . substr($phone, 1);
        }
        //greeting with store name
        $MESSAGE = "Halo " . $lead['name'] . ",
Mau lihat berapa penjualan yang dihasilkan dan berapa sisa stok barang tanpa hitung manual?

Sekarang ada sistem yang bisa bantu Anda tahu berapa hasil penjualan hari ini, minggu ini, bulan ini. Semua tercatat, praktis, nggak perlu hitung manual lagi. Gratis pula!

Mau?
Tinggal buka browser di HP atau komputer, lalu ketik alamat ini untuk daftar gratis:

ordermatix.com/daftar";

        /* INI DI COMMENT KALAU TIDAK MAU KIRIM */
        // Kirim ke lead
        sendWhatsApp($phone, $MESSAGE);

        // Update status pengiriman di database
        $updateStmt = $conn->prepare("
            UPDATE leads 
            SET send_status = 1, 
                send_text = '$MESSAGE',
                delivered_status = 1,
                send_date = NOW() 
            WHERE id = :id

        ");
        $updateStmt->execute(['id' => $lead['id']]);
        /* INI DI CONNECT KALAU TIDAK MAU KIRIM */

        // Delay untuk menghindari rate limiting
        sleep(3);
    }

    //sendWhatsApp($ADMIN_PHONE, $MESSAGE);
    
    echo "\nBerhasil mengirim " . count($leads) . " WhatsApp!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Fungsi untuk mengirim WhatsApp via Fonnte
function sendWhatsApp($phone, $message) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => [
            'target' => $phone,
            'message' => $message,
        ],
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . 'gS6m4yTfhFHehajpFote'
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        throw new Exception("Error sending WhatsApp: " . $err);
    }
    
    $result = json_decode($response, true);
    if (!isset($result['status']) || !$result['status']) {
        throw new Exception("Failed to send WhatsApp: " . ($result['message'] ?? 'Unknown error'));
    }
    
    return true;
}