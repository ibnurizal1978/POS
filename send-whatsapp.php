<?php
// Matikan error reporting untuk output
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/auth_helper.php';
require_once 'includes/check_session.php';

// Set header untuk JSON response
header('Content-Type: application/json');

try {
    // Get GET data
    $phone = $_GET['phone'] ?? '';
    $message = $_GET['message'] ?? '';

    error_log("Received phone: " . $phone);
    error_log("Received message: " . $message);

    // Validate data
    if (empty($phone) || empty($message)) {
        throw new Exception('Missing required fields (phone or message)');
    }

    // Format phone number
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) === '0') {
        $phone = '62' . substr($phone, 1);
    }

    // Send to Fonnte API
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
        throw new Exception($err);
    }

    $result = json_decode($response, true);
    if (!$result) {
        throw new Exception('Invalid response from API');
    }

    echo json_encode([
        'success' => isset($result['status']) && $result['status'] === true,
        'message' => $result['message'] ?? 'Unknown error',
        'debug' => [
            'api_response' => $result,
            'sent_phone' => $phone,
            'sent_message_length' => strlen($message)
        ]
    ]);

} catch (Exception $e) {
    error_log("WhatsApp Send Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'error_type' => get_class($e),
            'error_line' => $e->getLine()
        ]
    ]);
}