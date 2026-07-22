<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$transactionId = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($transactionId)) {
    echo json_encode(['success' => false, 'error' => 'transactionId não fornecido.']);
    exit;
}

// Chamada cURL para a ZuckPay API para obter o status da transação
$url = ZUCKPAY_API_URL . '/conta/v3/pix/status?transactionId=' . urlencode($transactionId);
$auth = base64_encode(ZUCKPAY_CLIENT_ID . ':' . ZUCKPAY_CLIENT_SECRET);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Basic ' . $auth
]);

$responseRaw = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['success' => false, 'error' => 'Erro de conexão com o gateway: ' . $curlError]);
    exit;
}

$response = json_decode($responseRaw, true);

if ($httpCode >= 200 && $httpCode < 300 && isset($response['status'])) {
    $gatewayStatus = strtoupper($response['status']);
    
    // Mapeia o status retornado para o que o frontend espera ('paid' ou 'pending')
    $mappedStatus = $gatewayStatus === 'PAID' ? 'paid' : 'pending';
    
    echo json_encode([
        'success' => true,
        'status' => $mappedStatus
    ]);
} else {
    echo json_encode([
        'success' => true,
        'status' => 'pending',
        'message' => $response['message'] ?? $response['error'] ?? 'Aguardando atualização do gateway.'
    ]);
}
?>
