<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$transactionId = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($transactionId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'transactionId não fornecido.']);
    exit;
}

$ch = curl_init(ZUCKPAY_API_URL . '/status?transactionId=' . urlencode($transactionId));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: ' . zuckpay_auth()]);

$responseRaw = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$response = json_decode($responseRaw, true);

if (!$curlError && $httpCode >= 200 && $httpCode < 300 && !empty($response['status'])) {
    $status = strtoupper($response['status']);
    // O frontend espera PAID quando o pagamento foi confirmado
    if (!empty($response['confirmed_date']) || in_array($status, ['PAID', 'COMPLETED', 'APPROVED', 'CONFIRMED'], true)) {
        $status = 'PAID';
    }
    echo json_encode(['success' => true, 'status' => $status]);
} else {
    echo json_encode([
        'success' => true,
        'status' => 'PENDING',
        'message' => $curlError ?: ($response['message'] ?? $response['error'] ?? 'Aguardando atualização do gateway.')
    ]);
}
?>
