<?php
/**
 * webhook.php — Recebe notificações de venda da plataforma
 *
 * Aceita POST com Content-Type: application/json.
 * Processa apenas pedidos com status "COMPLETED".
 * Salva os dados de entrega em /pedidos/{codigo}.json.
 */

date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// Aceitar apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido. Use POST.']);
    exit;
}

// Ler e decodificar o body JSON
$body = file_get_contents('php://input');
$data = json_decode($body);

if (json_last_error() !== JSON_ERROR_NONE || !$data) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido ou body vazio.']);
    exit;
}

// Ignorar silenciosamente qualquer status diferente de COMPLETED
if (!isset($data->status) || $data->status !== 'COMPLETED') {
    http_response_code(200);
    echo json_encode(['ignored' => true, 'motivo' => 'Status não é COMPLETED']);
    exit;
}

// Parse dos parâmetros da string utm
$utm = [];
if (!empty($data->utm)) {
    parse_str($data->utm, $utm);
}

// tr_codigo é obrigatório
if (empty($utm['tr_codigo'])) {
    http_response_code(400);
    echo json_encode(['error' => 'tr_codigo obrigatório na string utm.']);
    exit;
}

$codigo = strtoupper(trim($utm['tr_codigo']));

// Timestamp de postagem a partir do approvedAt
$posted_at = !empty($data->approvedAt) ? strtotime($data->approvedAt) : time();
if ($posted_at === false || $posted_at <= 0) {
    $posted_at = time();
}

// Cascade de fallbacks para prazo de entrega
$prazo_min = isset($utm['tr_prazo_min']) && $utm['tr_prazo_min'] !== ''
    ? (int) $utm['tr_prazo_min']
    : $config['prazo_min_padrao'];

$prazo_max = isset($utm['tr_prazo_max']) && $utm['tr_prazo_max'] !== ''
    ? (int) $utm['tr_prazo_max']
    : $config['prazo_max_padrao'];

// Cascade de fallbacks para localização do destino
$cidade_destino = !empty($utm['tr_cidade'])  ? $utm['tr_cidade']  :
                  (!empty($utm['utm_city'])  ? $utm['utm_city']   : '');

$estado_destino = !empty($utm['tr_estado'])  ? $utm['tr_estado']  :
                  (!empty($utm['utm_state']) ? $utm['utm_state']  : '');

// Cascade de fallback para produto
$produto = !empty($utm['tr_produto'])
    ? $utm['tr_produto']
    : (isset($data->items->title) ? $data->items->title : '');

// Endereço (opcional)
$cep    = $utm['tr_cep']    ?? '';
$rua    = $utm['tr_rua']    ?? '';
$numero = $utm['tr_numero'] ?? '';
$bairro = $utm['tr_bairro'] ?? '';

// Dados do destinatário
$destinatario = $data->customer->name  ?? '';
$email        = $data->customer->email ?? '';

// Montar estrutura do pedido
$pedido = [
    'codigo'              => $codigo,
    'posted_at'           => $posted_at,
    'destinatario'        => $destinatario,
    'email'               => $email,
    'produto'             => $produto,
    'prazo_min'           => $prazo_min,
    'prazo_max'           => $prazo_max,
    'cidade_destino'      => $cidade_destino,
    'estado_destino'      => $estado_destino,
    'cep_destino'         => $cep,
    'rua'                 => $rua,
    'numero'              => $numero,
    'bairro'              => $bairro,
    'amount'              => isset($data->amount) ? (float) $data->amount : 0,
    'ultimo_status_email' => 'preparacao',
];

// Verificar se a pasta /pedidos/ existe e tem permissão de escrita
$pasta   = __DIR__ . '/pedidos';
$arquivo = $pasta . '/' . $codigo . '.json';

if (!is_dir($pasta)) {
    http_response_code(500);
    echo json_encode(['error' => 'A pasta /pedidos/ não foi encontrada. Crie a pasta no servidor.']);
    exit;
}

if (!is_writable($pasta)) {
    http_response_code(500);
    echo json_encode(['error' => 'Sem permissão de escrita em /pedidos/. Execute: chmod 755 pedidos/']);
    exit;
}

// Salvar (sobrescreve se já existir)
$ok = file_put_contents($arquivo, json_encode($pedido, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

if ($ok === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Falha ao gravar o arquivo do pedido. Verifique permissões.']);
    exit;
}

// Disparar e-mail de confirmação ao cliente
$email_enviado = false;
if (!empty($email) && !empty($config['resend_api_key']) && !empty($config['resend_from'])) {
    require_once __DIR__ . '/mailer.php';
    $email_enviado = enviar_email_rastreio($pedido, $data, $config);
}

// Disparar varredura de status em background (fire-and-forget)
if (!empty($config['cron_token']) && !empty($config['site_url'])) {
    $cron_url = rtrim($config['site_url'], '/') . '/cron.php?token=' . rawurlencode($config['cron_token']);
    $ch = curl_init($cron_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT_MS     => 500,
        CURLOPT_NOSIGNAL       => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    @curl_exec($ch);
    curl_close($ch);
}

http_response_code(200);
echo json_encode(['success' => true, 'codigo' => $codigo, 'email_enviado' => $email_enviado]);
