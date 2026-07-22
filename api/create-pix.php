<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

// Captura a requisição JSON enviada pelo frontend
$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Dados de checkout não recebidos pelo servidor.']);
    exit;
}

$customer = $input['customer'] ?? [];
$shipping = $input['shipping'] ?? [];
$amountCents = $input['amountCents'] ?? 0;
$amount = $amountCents ? ($amountCents / 100) : 0;
$cpf = $customer['documentNumber'] ?? '';

if ($amount <= 0) {
    echo json_encode(['success' => false, 'error' => 'O valor do pedido deve ser maior que zero.']);
    exit;
}

// Constrói o corpo da requisição para a ZuckPay
$payload = [
    'nome' => $customer['name'] ?? 'Cliente WPINK',
    'cpf' => preg_replace('/\D/', '', $cpf),
    'valor' => round($amount, 2),
    'email' => $customer['email'] ?? 'wepinksuplementos@gmail.com',
    'telefone' => isset($customer['phone']) ? preg_replace('/\D/', '', $customer['phone']) : '',
    'descricao' => 'Pedido WPINK - ' . ($customer['name'] ?? 'PIX'),
    'external_id_client' => $input['idempotencyKey'] ?? 'WP-' . time()
];

// Chamada cURL para a API ZuckPay
$url = ZUCKPAY_API_URL . '/conta/v3/pix/qrcode';
$auth = base64_encode(ZUCKPAY_CLIENT_ID . ':' . ZUCKPAY_CLIENT_SECRET);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Basic ' . $auth
]);

$responseRaw = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['success' => false, 'error' => 'Erro de conexão com o gateway de pagamento: ' . $curlError]);
    exit;
}

$response = json_decode($responseRaw, true);

if ($httpCode >= 200 && $httpCode < 300 && isset($response['transactionId'])) {
    $transactionId = $response['transactionId'];
    $pixCode = $response['qrcode'] ?? $response['pix_code'] ?? '';
    
    $qrCodeBase64 = '';
    
    // Tenta baixar a imagem do QR Code retornada pela ZuckPay e converter para base64
    if (!empty($response['qrcode_image'])) {
        $chQr = curl_init($response['qrcode_image']);
        curl_setopt($chQr, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chQr, CURLOPT_TIMEOUT, 5);
        curl_setopt($chQr, CURLOPT_FOLLOWLOCATION, true);
        $qrImageRaw = curl_exec($chQr);
        curl_close($chQr);
        
        if ($qrImageRaw) {
            $qrCodeBase64 = base64_encode($qrImageRaw);
        }
    }
    
    // Fallback: Se não conseguiu o base64, gera via API externa do QR Server
    if (empty($qrCodeBase64) && !empty($pixCode)) {
        $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($pixCode);
        $chQr = curl_init($qrApiUrl);
        curl_setopt($chQr, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chQr, CURLOPT_TIMEOUT, 5);
        $qrImageRaw = curl_exec($chQr);
        curl_close($chQr);
        
        if ($qrImageRaw) {
            $qrCodeBase64 = base64_encode($qrImageRaw);
        }
    }
    
    // Retorna exatamente a estrutura de resposta que o frontend espera
    echo json_encode([
        'success' => true,
        'transactionId' => $transactionId,
        'paymentData' => [
            'qrCode' => $pixCode,
            'qrCodeBase64' => $qrCodeBase64
        ],
        'status' => 'pending'
    ]);
} else {
    $errMsg = $response['message'] ?? $response['error'] ?? 'Erro desconhecido ao gerar o Pix.';
    echo json_encode([
        'success' => false,
        'error' => $errMsg
    ]);
}
?>
