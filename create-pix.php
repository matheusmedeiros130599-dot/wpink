<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

function fail($code, $msg) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

if (ZUCKPAY_CLIENT_ID === '' || ZUCKPAY_CLIENT_SECRET === '') {
    fail(500, 'Gateway de pagamento não configurada.');
}

// Captura a requisição JSON enviada pelo frontend
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    fail(400, 'Dados de checkout não recebidos pelo servidor.');
}

$customer = $input['customer'] ?? [];
$items = $input['items'] ?? [];
$amountCents = (int) ($input['amountCents'] ?? 0);
$cpf = preg_replace('/\D/', '', $customer['documentNumber'] ?? '');

if ($amountCents <= 0) {
    fail(400, 'O valor do pedido deve ser maior que zero.');
}

$descricao = implode(', ', array_map(function ($item) {
    return $item['title'] ?? 'Produto';
}, $items));

// Zuck Pay: valor em reais (float), a idempotência vem do external_id_client
$payload = [
    'nome' => $customer['name'] ?? 'Cliente',
    'cpf' => $cpf,
    'valor' => round($amountCents / 100, 2),
    'email' => $customer['email'] ?? 'cliente@email.com',
    'telefone' => preg_replace('/\D/', '', $customer['phone'] ?? ''),
    'descricao' => substr($descricao !== '' ? $descricao : 'Produto', 0, 250),
    'external_id_client' => ($input['idempotencyKey'] ?? ('PED-' . time())) . '-' . $amountCents
];

// Repassa os parâmetros de rastreio, quando enviados pelo frontend
$trackingKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'src', 'sck',
    'fbclid', 'fbc', 'fbp', 'gclid', 'wbraid', 'gbraid', 'ttclid', 'kclid', 'click_id'];
$utm = is_array($input['utm'] ?? null) ? $input['utm'] : [];
foreach ($trackingKeys as $key) {
    if (!empty($utm[$key]) && is_scalar($utm[$key])) {
        $payload[$key] = (string) $utm[$key];
    }
}

$ch = curl_init(ZUCKPAY_API_URL . '/qrcode');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: ' . zuckpay_auth()
]);

$responseRaw = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    fail(502, 'Erro de conexão com o gateway de pagamento: ' . $curlError);
}

$response = json_decode($responseRaw, true);
$pixCode = $response['qrcode'] ?? ($response['pix_code'] ?? '');

if ($httpCode >= 200 && $httpCode < 300 && !empty($response['transactionId']) && $pixCode !== '') {
    // Mesma estrutura de resposta que o frontend já espera
    echo json_encode([
        'success' => true,
        'transactionId' => $response['transactionId'],
        'paymentData' => [
            'qrCode' => $pixCode,
            'qrCodeBase64' => ''
        ],
        'status' => $response['status'] ?? 'PENDING'
    ]);
} else {
    fail($httpCode >= 400 ? $httpCode : 502, $response['message'] ?? $response['error'] ?? 'Erro desconhecido ao gerar o Pix.');
}
?>
