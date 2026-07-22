<?php
/**
 * cron.php — Varredura de pedidos e disparo de e-mails de atualização de status
 *
 * Disparado automaticamente pelo webhook.php a cada venda recebida.
 * Pode também ser chamado via cPanel cron ou cron-job.org:
 *   https://seusite.com/rastreio/cron.php?token=SEU_TOKEN
 */

ignore_user_abort(true);
set_time_limit(120);
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

// Validação de token (ignorada se rodando via CLI)
if (PHP_SAPI !== 'cli') {
    $token_recebido = $_GET['token'] ?? '';
    if (empty($config['cron_token']) || $token_recebido !== $config['cron_token']) {
        http_response_code(403);
        exit('Acesso negado.');
    }
}

$pasta    = __DIR__ . '/pedidos';
$arquivos = glob($pasta . '/*.json') ?: [];

$emails_enviados = 0;
$atualizados     = 0;

foreach ($arquivos as $arquivo) {
    $pedido = json_decode(file_get_contents($arquivo), true);
    if (!$pedido || empty($pedido['codigo'])) {
        continue;
    }

    $ultimo = $pedido['ultimo_status_email'] ?? 'preparacao';

    // Pedido já entregue — não há mais o que fazer
    if ($ultimo === 'entregue') {
        continue;
    }

    $atual = _calcular_status_atual($pedido);

    // Sem mudança de status
    if ($atual === $ultimo) {
        continue;
    }

    // Status avançou — tenta enviar e-mail
    $enviado = false;
    if (!empty($pedido['email']) && !empty($config['resend_api_key'])) {
        $enviado = enviar_email_status($pedido, $atual, $config);
        if ($enviado) {
            $emails_enviados++;
        }
    }

    // Atualiza o JSON independente de ter enviado e-mail ou não
    $pedido['ultimo_status_email'] = $atual;
    file_put_contents($arquivo, json_encode($pedido, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $atualizados++;
}

if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'total_pedidos'   => count($arquivos),
        'atualizados'     => $atualizados,
        'emails_enviados' => $emails_enviados,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────

function _calcular_status_atual(array $pedido): string
{
    $posted_at   = (int) $pedido['posted_at'];
    $prazo_min   = (int) ($pedido['prazo_min'] ?? 5);
    $prazo_max   = (int) ($pedido['prazo_max'] ?? 7);
    $total_horas = ($prazo_min + $prazo_max) / 2 * 24;

    if ($total_horas <= 0) {
        return 'entregue';
    }

    $percentual = ((time() - $posted_at) / 3600) / $total_horas * 100;

    if ($percentual >= 90) return 'entregue';
    if ($percentual >= 75) return 'saiu';
    if ($percentual >= 15) return 'transferencia';
    if ($percentual >= 8)  return 'postado';

    return 'preparacao';
}
