<?php
/**
 * mailer.php — Disparo de e-mail de confirmação de pedido via Resend
 */

function enviar_email_rastreio(array $pedido, object $payload, array $config): bool
{
    $api_key    = $config['resend_api_key'];
    $from_email = $config['resend_from'];
    $nome_loja  = $config['nome_email'] ?? $config['nome_loja'];
    $cor_btn    = $config['cor_primaria'];
    $site_url   = rtrim($config['site_url'] ?? '', '/');

    $email_to = $pedido['email'] ?? '';
    if (empty($email_to)) {
        return false;
    }

    // Nome do destinatário
    $nome_completo = $pedido['destinatario'] ?? '';
    $partes        = explode(' ', trim($nome_completo));
    $primeiro      = $partes[0] ?: $nome_completo;
    $phone         = $payload->customer->phone ?? '';

    // Código de rastreio formatado (QN 749 955 838 BR)
    $codigo  = $pedido['codigo'];
    $cod_fmt = preg_replace('/^([A-Z]{2})(\d{3})(\d{3})(\d{3})([A-Z]{2})$/', '$1 $2 $3 $4 $5', $codigo);

    // Datas de prazo em português
    $meses  = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
    $ts0    = (int) $pedido['posted_at'];
    $ts_min = $ts0 + (int) $pedido['prazo_min'] * 86400;
    $ts_max = $ts0 + (int) $pedido['prazo_max'] * 86400;
    $prazo  = (int) date('j', $ts_min) . ' de ' . $meses[(int) date('n', $ts_min) - 1]
            . ' a ' . (int) date('j', $ts_max) . ' de ' . $meses[(int) date('n', $ts_max) - 1];

    // Produto e total
    $produto = htmlspecialchars($pedido['produto'] ?? '', ENT_QUOTES, 'UTF-8');
    $amount  = (float) ($payload->amount ?? 0);
    $total   = 'R$ ' . number_format($amount, 2, ',', '.');

    // Link de rastreio
    $link = $site_url ? $site_url . '/rastreio.php?codigo=' . rawurlencode($codigo) : '#';

    // Linhas do endereço de envio
    $linhas_end = [];
    if ($nome_completo)                     $linhas_end[] = htmlspecialchars($nome_completo);
    if ($phone)                             $linhas_end[] = htmlspecialchars($phone);
    if ($email_to)                          $linhas_end[] = htmlspecialchars($email_to);
    if (!empty($pedido['rua'])) {
        $linha_rua = htmlspecialchars($pedido['rua']);
        if (!empty($pedido['numero'])) $linha_rua .= ', ' . htmlspecialchars($pedido['numero']);
        $linhas_end[] = $linha_rua;
    }
    if (!empty($pedido['bairro']))          $linhas_end[] = htmlspecialchars($pedido['bairro']);
    $city_state = htmlspecialchars($pedido['cidade_destino'] ?? '');
    if (!empty($pedido['estado_destino'])) $city_state .= ($city_state ? ', ' : '') . htmlspecialchars($pedido['estado_destino']);
    if ($city_state)                        $linhas_end[] = $city_state;
    if (!empty($pedido['cep_destino']))     $linhas_end[] = htmlspecialchars($pedido['cep_destino']);

    $adr_html = '';
    foreach ($linhas_end as $linha) {
        $adr_html .= '<p style="margin:0;font-size:15px;font-weight:400;color:rgba(22,24,35,0.75)">' . $linha . '</p>'
                   . '<table><tbody><tr><td height="8px"></td></tr></tbody></table>';
    }

    // Valores escapados para uso em atributos HTML / inline CSS
    $v = [
        '{{NOME_LOJA}}'  => htmlspecialchars($nome_loja,  ENT_QUOTES, 'UTF-8'),
        '{{PRIMEIRO}}'   => htmlspecialchars($primeiro,   ENT_QUOTES, 'UTF-8'),
        '{{COD_FMT}}'    => htmlspecialchars($cod_fmt,    ENT_QUOTES, 'UTF-8'),
        '{{PRAZO}}'      => htmlspecialchars($prazo,      ENT_QUOTES, 'UTF-8'),
        '{{PRODUTO}}'    => $produto,
        '{{LINK}}'       => htmlspecialchars($link,       ENT_QUOTES, 'UTF-8'),
        '{{COR_BTN}}'    => htmlspecialchars($cor_btn,    ENT_QUOTES, 'UTF-8'),
        '{{EMAIL_TO}}'   => htmlspecialchars($email_to,   ENT_QUOTES, 'UTF-8'),
        '{{TOTAL}}'      => htmlspecialchars($total,      ENT_QUOTES, 'UTF-8'),
        '{{ADR_HTML}}'   => $adr_html,
        '{{HEADER_LOJA}}'=> _header_loja($config),
    ];

    $html = str_replace(array_keys($v), array_values($v), _template_email('confirmacao', $config));

    $body = json_encode([
        'from'    => $nome_loja . ' <' . $from_email . '>',
        'to'      => [$email_to],
        'subject' => 'Seu pedido foi confirmado! — Código ' . $codigo,
        'html'    => $html,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 10,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $code >= 200 && $code < 300;
}

function enviar_email_status(array $pedido, string $status, array $config): bool
{
    $api_key    = $config['resend_api_key'];
    $from_email = $config['resend_from'];
    $nome_loja  = $config['nome_email'] ?? $config['nome_loja'];
    $cor_btn    = $config['cor_primaria'];
    $site_url   = rtrim($config['site_url'] ?? '', '/');

    $email_to = $pedido['email'] ?? '';
    if (empty($email_to) || empty($api_key) || empty($from_email)) {
        return false;
    }

    $conteudo = [
        'postado' => [
            'titulo'   => 'Seu pedido foi enviado!',
            'mensagem' => 'Ótimas notícias! Seu pedido foi postado e está a caminho. Use o código abaixo para acompanhar a entrega.',
            'assunto'  => 'Seu pedido foi enviado! — Código ',
        ],
        'transferencia' => [
            'titulo'   => 'Pedido em transferência',
            'mensagem' => 'Seu pedido está em rota e sendo transferido em direção ao seu endereço. Por favor aguarde.',
            'assunto'  => 'Pedido em transferência — Código ',
        ],
        'saiu' => [
            'titulo'   => 'Seu pedido saiu para entrega!',
            'mensagem' => 'Seu pedido saiu para entrega ao destinatário. Fique atento, pois ele deve chegar em breve!',
            'assunto'  => 'Pedido saiu para entrega — Código ',
        ],
        'entregue' => [
            'titulo'   => 'Seu pedido foi entregue!',
            'mensagem' => 'Seu pedido foi entregue com sucesso. Esperamos que você aproveite muito!',
            'assunto'  => 'Pedido entregue — Código ',
        ],
    ];

    if (!isset($conteudo[$status])) {
        return false;
    }

    $c = $conteudo[$status];

    $codigo  = $pedido['codigo'];
    $cod_fmt = preg_replace('/^([A-Z]{2})(\d{3})(\d{3})(\d{3})([A-Z]{2})$/', '$1 $2 $3 $4 $5', $codigo);

    $meses  = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
    $ts0    = (int) $pedido['posted_at'];
    $ts_min = $ts0 + (int) $pedido['prazo_min'] * 86400;
    $ts_max = $ts0 + (int) $pedido['prazo_max'] * 86400;
    $prazo  = (int) date('j', $ts_min) . ' de ' . $meses[(int) date('n', $ts_min) - 1]
            . ' a ' . (int) date('j', $ts_max) . ' de ' . $meses[(int) date('n', $ts_max) - 1];

    $nome_completo = $pedido['destinatario'] ?? '';
    $partes        = explode(' ', trim($nome_completo));
    $primeiro      = $partes[0] ?: $nome_completo;

    $amount = (float) ($pedido['amount'] ?? 0);
    $total  = 'R$ ' . number_format($amount, 2, ',', '.');
    $link   = $site_url ? $site_url . '/rastreio.php?codigo=' . rawurlencode($codigo) : '#';

    $v = [
        '{{NOME_LOJA}}'  => htmlspecialchars($nome_loja,  ENT_QUOTES, 'UTF-8'),
        '{{PRIMEIRO}}'   => htmlspecialchars($primeiro,   ENT_QUOTES, 'UTF-8'),
        '{{TITULO}}'     => htmlspecialchars($c['titulo'], ENT_QUOTES, 'UTF-8'),
        '{{MENSAGEM}}'   => htmlspecialchars($c['mensagem'], ENT_QUOTES, 'UTF-8'),
        '{{COD_FMT}}'    => htmlspecialchars($cod_fmt,    ENT_QUOTES, 'UTF-8'),
        '{{PRAZO}}'      => htmlspecialchars($prazo,      ENT_QUOTES, 'UTF-8'),
        '{{TOTAL}}'      => htmlspecialchars($total,      ENT_QUOTES, 'UTF-8'),
        '{{LINK}}'       => htmlspecialchars($link,       ENT_QUOTES, 'UTF-8'),
        '{{COR_BTN}}'    => htmlspecialchars($cor_btn,    ENT_QUOTES, 'UTF-8'),
        '{{EMAIL_TO}}'   => htmlspecialchars($email_to,   ENT_QUOTES, 'UTF-8'),
        '{{HEADER_LOJA}}'=> _header_loja($config),
    ];

    $html = str_replace(array_keys($v), array_values($v), _template_email('status', $config));

    $body = json_encode([
        'from'    => $nome_loja . ' <' . $from_email . '>',
        'to'      => [$email_to],
        'subject' => $c['assunto'] . $codigo,
        'html'    => $html,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 10,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $code >= 200 && $code < 300;
}

/**
 * Escolhe o template conforme o modelo configurado em $config['email_template'].
 * $tipo: 'confirmacao' (e-mail de venda) ou 'status' (atualização de rastreio).
 */
function _template_email(string $tipo, array $config): string
{
    $modelo = $config['email_template'] ?? 'padrao';

    if ($tipo === 'status') {
        return $modelo === 'tiktok'
            ? _email_status_template()
            : _email_status_template_padrao();
    }

    return $modelo === 'tiktok'
        ? _email_html_template()
        : _email_html_template_padrao();
}

/** Cabeçalho do modelo padrão: logo da loja se houver, senão o nome em texto. */
function _header_loja(array $config): string
{
    $nome = $config['nome_email'] ?: ($config['nome_loja'] ?? '');
    $logo = trim($config['logo_url'] ?? '');
    $cor  = $config['cor_primaria'] ?? '#0a4f9e';

    if ($logo !== '') {
        return '<img src="' . htmlspecialchars($logo, ENT_QUOTES, 'UTF-8')
             . '" alt="' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8')
             . '" height="40" style="display:block;height:40px;margin:0 auto;border:0;">';
    }

    return '<span style="font-size:22px;font-weight:700;letter-spacing:.3px;color:'
         . htmlspecialchars($cor, ENT_QUOTES, 'UTF-8') . '">'
         . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '</span>';
}

function _email_html_template_padrao(): string
{
    return <<<'TMPL'
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="light">
  <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
</head>
<body style="margin:0;padding:0;background:#f2f4f7;-webkit-font-smoothing:antialiased;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f4f7;padding:24px 12px;">
<tr><td align="center">
  <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e6e9ee;">

    <!-- Header -->
    <tr><td align="center" style="padding:24px;border-bottom:3px solid {{COR_BTN}};">
      {{HEADER_LOJA}}
    </td></tr>

    <!-- Título + saudação -->
    <tr><td style="padding:28px 32px 4px;">
      <h1 style="margin:0 0 16px;font-size:24px;line-height:1.25;color:#161823;font-weight:700;">Seu pedido foi confirmado!</h1>
      <p style="margin:0 0 12px;font-size:15px;color:#50525a;line-height:1.6;">Olá, {{PRIMEIRO}}!</p>
      <p style="margin:0;font-size:15px;color:#50525a;line-height:1.6;">Recebemos seu pedido e já estamos preparando tudo para o envio. Assim que ele estiver a caminho, você poderá acompanhar a entrega pelo código abaixo.</p>
    </td></tr>

    <!-- Código + prazo -->
    <tr><td style="padding:22px 32px 0;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f8fa;border-radius:10px;">
        <tr><td style="padding:16px 18px;">
          <p style="margin:0 0 6px;font-size:13px;color:#8a92a3;">Código de rastreio</p>
          <p style="margin:0 0 14px;font-size:18px;font-weight:700;color:#161823;letter-spacing:1px;">{{COD_FMT}}</p>
          <p style="margin:0 0 4px;font-size:13px;color:#8a92a3;">Previsão de entrega</p>
          <p style="margin:0;font-size:16px;font-weight:700;color:#161823;">{{PRAZO}}</p>
        </td></tr>
      </table>
    </td></tr>

    <!-- Produto + valor -->
    <tr><td style="padding:18px 32px 0;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="font-size:15px;color:#161823;font-weight:600;">{{PRODUTO}}</td>
          <td align="right" style="font-size:15px;color:#161823;font-weight:700;white-space:nowrap;">{{TOTAL}}</td>
        </tr>
      </table>
    </td></tr>

    <!-- Botão -->
    <tr><td align="center" style="padding:24px 32px 4px;">
      <a href="{{LINK}}" target="_blank" style="display:block;background:{{COR_BTN}};color:#ffffff;text-decoration:none;font-size:16px;font-weight:600;padding:14px 24px;border-radius:10px;">Rastrear meu pedido</a>
    </td></tr>

    <!-- Endereço de envio -->
    <tr><td style="padding:20px 32px 0;">
      <hr style="border:none;border-top:1px solid #eef1f6;margin:0 0 18px;">
      <p style="margin:0 0 10px;font-size:15px;font-weight:600;color:#161823;">Endereço de envio</p>
      {{ADR_HTML}}
    </td></tr>

    <!-- Resumo -->
    <tr><td style="padding:18px 32px 0;">
      <hr style="border:none;border-top:1px solid #eef1f6;margin:0 0 18px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="font-size:15px;color:#50525a;">Total (1 item)</td>
          <td align="right" style="font-size:15px;font-weight:700;color:#161823;">{{TOTAL}}</td>
        </tr>
      </table>
    </td></tr>

    <!-- Footer -->
    <tr><td style="padding:26px 32px 30px;">
      <hr style="border:none;border-top:1px solid #eef1f6;margin:0 0 18px;">
      <p style="margin:0 0 8px;font-size:12px;color:#9aa1b0;line-height:1.6;">Esta mensagem foi enviada para {{EMAIL_TO}} sobre uma compra recente em {{NOME_LOJA}}.</p>
      <p style="margin:0;font-size:12px;color:#9aa1b0;">Mensagem automática. Não é possível responder a este e-mail.</p>
    </td></tr>

  </table>
  <p style="margin:16px 0 0;font-size:12px;color:#aab0bd;">© {{NOME_LOJA}}</p>
</td></tr>
</table>
</body>
</html>
TMPL;
}

function _email_status_template_padrao(): string
{
    return <<<'TMPL'
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="light">
  <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
</head>
<body style="margin:0;padding:0;background:#f2f4f7;-webkit-font-smoothing:antialiased;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f4f7;padding:24px 12px;">
<tr><td align="center">
  <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e6e9ee;">

    <!-- Header -->
    <tr><td align="center" style="padding:24px;border-bottom:3px solid {{COR_BTN}};">
      {{HEADER_LOJA}}
    </td></tr>

    <!-- Título + mensagem -->
    <tr><td style="padding:28px 32px 4px;">
      <h1 style="margin:0 0 16px;font-size:24px;line-height:1.25;color:#161823;font-weight:700;">{{TITULO}}</h1>
      <p style="margin:0 0 12px;font-size:15px;color:#50525a;line-height:1.6;">Olá, {{PRIMEIRO}}!</p>
      <p style="margin:0 0 12px;font-size:15px;color:#50525a;line-height:1.6;">{{MENSAGEM}}</p>
      <p style="margin:0;font-size:15px;color:#50525a;line-height:1.6;">Equipe {{NOME_LOJA}}</p>
    </td></tr>

    <!-- Código + prazo -->
    <tr><td style="padding:22px 32px 0;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f8fa;border-radius:10px;">
        <tr><td style="padding:16px 18px;">
          <p style="margin:0 0 6px;font-size:13px;color:#8a92a3;">Código de rastreio</p>
          <p style="margin:0 0 14px;font-size:18px;font-weight:700;color:#161823;letter-spacing:1px;">{{COD_FMT}}</p>
          <p style="margin:0 0 4px;font-size:13px;color:#8a92a3;">Previsão de entrega</p>
          <p style="margin:0;font-size:16px;font-weight:700;color:#161823;">{{PRAZO}}</p>
        </td></tr>
      </table>
    </td></tr>

    <!-- Botão -->
    <tr><td align="center" style="padding:24px 32px 4px;">
      <a href="{{LINK}}" target="_blank" style="display:block;background:{{COR_BTN}};color:#ffffff;text-decoration:none;font-size:16px;font-weight:600;padding:14px 24px;border-radius:10px;">Rastrear meu pedido</a>
    </td></tr>

    <!-- Footer -->
    <tr><td style="padding:26px 32px 30px;">
      <hr style="border:none;border-top:1px solid #eef1f6;margin:0 0 18px;">
      <p style="margin:0 0 8px;font-size:12px;color:#9aa1b0;line-height:1.6;">Esta mensagem foi enviada para {{EMAIL_TO}} sobre uma compra recente em {{NOME_LOJA}}.</p>
      <p style="margin:0;font-size:12px;color:#9aa1b0;">Mensagem automática. Não é possível responder a este e-mail.</p>
    </td></tr>

  </table>
  <p style="margin:16px 0 0;font-size:12px;color:#aab0bd;">© {{NOME_LOJA}}</p>
</td></tr>
</table>
</body>
</html>
TMPL;
}

function _email_status_template(): string
{
    return <<<'TMPL'
<!doctype html>
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <meta name="x-apple-disable-message-reformatting">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
  <meta name="color-scheme" content="light">
  <meta name="supported-color-schemes" content="light">
  <!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->
  <style>
    #outlook a{padding:0;}
    body{width:100%!important;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;margin:0;padding:0;background-color:#f5f5f5;}
    img{outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;}
    a{color:rgb(135,136,142);}
  </style>
</head>
<body style="margin:0;padding:0;width:100%;word-break:break-word;-webkit-font-smoothing:antialiased;font-family:system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI','Helvetica Neue',Helvetica,Arial,sans-serif;background-color:#f5f5f5;">

<table cellspacing="0" cellpadding="0" width="375" style="width:375px;border:0;margin:0 auto;max-width:375px;min-width:375px;background:#ffffff;outline:none">
<tbody>

<!-- ── Header image + nav bar ── -->
<tr><td>
  <div style="width:375px;padding-bottom:0px">
    <span style="color:#067df7;text-decoration:none;display:block">
      <img alt="" src="https://sf16-sg.tiktokcdn.com/obj/eden-sg/lm_tyj_jln/ljhwZthlaukjlkulzlp/crm_email_header.png" width="375px" height="48px" style="display:block;width:375px;height:48px;vertical-align:middle">
    </span>
    <table cellspacing="0" cellpadding="0" style="width:100%;border:0;margin:0 auto;background:linear-gradient(#000000,#000000);background-color:#000000;outline:none">
      <tbody><tr>
        <td style="margin:0 auto;text-align:center;vertical-align:middle;width:50%">
          <div style="color:#FFFFFF;font-size:15px;font-weight:500;line-height:46px">Pedidos</div>
        </td>
        <td style="color:#f8f8f8;font-size:12px">|</td>
        <td style="margin:0 auto;text-align:center;vertical-align:middle;width:50%">
          <div style="color:#FFFFFF;font-size:15px;font-weight:500;line-height:46px">Rastrear pedido</div>
        </td>
      </tr></tbody>
    </table>
  </div>
</td></tr>

<!-- ── Título e mensagem ── -->
<tr><td>
  <table cellspacing="0" cellpadding="0" style="width:375px;border:0;margin:0 auto;padding-top:32px;outline:none">
    <tbody><tr><td style="margin:0 auto;text-align:center;vertical-align:middle;padding-bottom:24px;padding-left:16px;padding-right:16px">
      <table cellspacing="0" cellpadding="0" style="width:100%;border:0;margin:0 auto;outline:none">
        <tbody>
          <tr><td style="margin:0 auto;text-align:center;vertical-align:middle">
            <p style="margin:0;font-size:32px;font-weight:700;color:#161823;margin-bottom:16px;text-align:left">{{TITULO}}</p>
          </td></tr>
          <tr><td style="margin:0 auto;text-align:left;vertical-align:middle">
            <span style="color:#50525A">Olá, {{PRIMEIRO}}!</span>
            <table><tbody><tr><td height="8px"></td></tr></tbody></table>
            <span style="color:#50525A">{{MENSAGEM}}</span>
            <table><tbody><tr><td height="8px"></td></tr></tbody></table>
            <span style="color:#50525A">Equipe {{NOME_LOJA}}</span>
          </td></tr>
        </tbody>
      </table>
    </td></tr></tbody>
  </table>
</td></tr>

<!-- ── Divisor ── -->
<tr><td>
  <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border:0;margin:0 auto">
    <tbody style="width:100%"><tr style="width:100%"><td style="text-align:center;vertical-align:middle;padding:16px">
      <p style="border-bottom:1px solid rgba(0,0,0,0.05);margin:0"></p>
    </td></tr></tbody>
  </table>
</td></tr>

<!-- ── Código + prazo ── -->
<tr><td>
  <table cellspacing="0" cellpadding="0" style="width:375px;border:0;margin:0 auto;table-layout:fixed;outline:none">
    <tbody>
      <tr><td style="margin:0 auto;text-align:center;vertical-align:middle;width:375px">
        <div style="padding:0 16px;margin-top:16px">
          <div style="text-align:left;margin-bottom:16px;margin-top:16px">
            <span style="font-size:15px;font-weight:400;color:#000;line-height:18px">Código de rastreio: {{COD_FMT}}</span>
          </div>
          <div style="text-align:left">
            <span style="font-size:17px;font-weight:700;color:#000;line-height:22px">Entrega </span>
            <span style="font-size:17px;font-weight:700;color:#000;line-height:22px">{{PRAZO}}</span>
          </div>
        </div>
      </td></tr>

      <!-- nome da loja -->
      <tr><td style="margin-right:auto;text-align:left;vertical-align:middle;padding:12px 16px 0">
        <p style="margin:0;font-size:15px;font-weight:600;color:#000">{{NOME_LOJA}}</p>
      </td></tr>

      <!-- produto + valor -->
      <tr><td style="padding:8px 16px 0">
        <table cellspacing="0" cellpadding="0" style="width:100%;border:0;margin-right:auto;outline:none">
          <tbody><tr>
            <td style="text-align:left;vertical-align:middle">
              <span style="font-size:13px;line-height:17px;font-weight:600;color:#000000">{{TOTAL}}</span>
            </td>
          </tr></tbody>
        </table>
      </td></tr>

      <!-- botão CTA -->
      <tr><td style="margin:0 auto;text-align:center;vertical-align:middle;padding:16px;width:375px">
        <table cellspacing="0" cellpadding="0" style="width:100%;border:0;margin:0 auto;outline:none">
          <tbody><tr>
            <td style="margin:0 auto;text-align:center;vertical-align:middle;height:48px;border-radius:8px;padding:0;background-color:{{COR_BTN}};border-color:{{COR_BTN}}">
              <a href="{{LINK}}" target="_blank" style="color:#067df7;text-decoration:none;display:block">
                <p style="margin:0;font-size:15px;font-weight:500;color:#fff;width:100%;height:44px;line-height:44px">Rastrear meu pedido</p>
              </a>
            </td>
          </tr></tbody>
        </table>
      </td></tr>
    </tbody>
  </table>
</td></tr>

<!-- ── Divisor ── -->
<tr><td>
  <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border:0;margin:0 auto">
    <tbody style="width:100%"><tr style="width:100%"><td style="text-align:center;vertical-align:middle;padding:16px">
      <p style="border-bottom:1px solid rgba(0,0,0,0.05);margin:0"></p>
    </td></tr></tbody>
  </table>
</td></tr>

<!-- ── Footer ── -->
<tr><td>
  <table cellspacing="0" cellpadding="0" style="width:375px;border:0;margin:0 auto;outline:none">
    <tbody><tr><td style="margin:0 auto;text-align:center;vertical-align:middle;padding-top:0px">
      <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border:0;margin:0 auto;background:#F5F5F5;padding:0 16px">
        <tbody style="width:100%">
          <tr><td style="height:24px"></td></tr>
          <tr style="width:100%"><td style="text-align:center;vertical-align:middle;padding:0 24px;color:#4E4F57;font-size:13px">
            Esta mensagem foi enviada para {{EMAIL_TO}} sobre uma compra recente em {{NOME_LOJA}}.
          </td></tr>
          <tr><td style="text-align:center;vertical-align:middle;color:#4E4F57;font-size:13px;padding-top:12px">
            Mensagem automática. Não é possível responder a este e-mail.
          </td></tr>
          <tr><td style="text-align:center;vertical-align:middle;padding:32px 0">
            <img alt="{{NOME_LOJA}}" src="https://p16-ttec-va.ibyteimg.com/tos-maliva-i-acgf4d7es9-us/LOGO.png~tplv-acgf4d7es9-image.image" width="130px" height="23px" style="display:block;width:130px;height:23px;vertical-align:middle;margin:0 auto">
          </td></tr>
        </tbody>
      </table>
    </td></tr></tbody>
  </table>
</td></tr>

</tbody>
</table>

</body>
</html>
TMPL;
}

function _email_html_template(): string
{
    return <<<'TMPL'
<!doctype html>
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <meta name="x-apple-disable-message-reformatting">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
  <meta name="color-scheme" content="light">
  <meta name="supported-color-schemes" content="light">
  <!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->
  <!--[if mso]><style>td,th,div,p,a,h1,h2,h3,h4,h5,h6{font-family:system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI','Helvetica Neue',Helvetica,Arial,sans-serif;mso-line-height-rule:exactly;}</style><![endif]-->
  <style>
    #outlook a{padding:0;}
    body{width:100%!important;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;margin:0;padding:0;background-color:#f5f5f5;}
    img{outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;}
    a{color:rgb(135,136,142);}
  </style>
</head>
<body style="margin:0;padding:0;width:100%;word-break:break-word;-webkit-font-smoothing:antialiased;font-family:system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI','Helvetica Neue',Helvetica,Arial,sans-serif;background-color:#f5f5f5;">

<table cellspacing="0" cellpadding="0" width="375" style="width:375px;border:0;margin:0 auto;max-width:375px;min-width:375px;background:#ffffff;outline:none">
<tbody>

<!-- ── Header image + nav bar ── -->
<tr><td>
  <div style="width:375px;padding-bottom:0px">
    <span style="color:#067df7;text-decoration:none;display:block">
      <img alt="" src="https://sf16-sg.tiktokcdn.com/obj/eden-sg/lm_tyj_jln/ljhwZthlaukjlkulzlp/crm_email_header.png" width="375px" height="48px" style="display:block;width:375px;height:48px;vertical-align:middle">
    </span>
    <table cellspacing="0" cellpadding="0" style="width:100%;border:0;margin:0 auto;background:linear-gradient(#000000,#000000);background-color:#000000;outline:none">
      <tbody><tr>
        <td style="margin:0 auto;text-align:center;vertical-align:middle;width:50%">
          <div style="color:#FFFFFF;font-size:15px;font-weight:500;line-height:46px">Pedidos</div>
        </td>
        <td style="color:#f8f8f8;font-size:12px">|</td>
        <td style="margin:0 auto;text-align:center;vertical-align:middle;width:50%">
          <div style="color:#FFFFFF;font-size:15px;font-weight:500;line-height:46px">Rastrear pedido</div>
        </td>
      </tr></tbody>
    </table>
  </div>
</td></tr>

<!-- ── Status + saudação ── -->
<tr><td>
  <table cellspacing="0" cellpadding="0" style="width:375px;border:0;margin:0 auto;padding-top:32px;outline:none">
    <tbody><tr><td style="margin:0 auto;text-align:center;vertical-align:middle;padding-bottom:24px;padding-left:16px;padding-right:16px">
      <table cellspacing="0" cellpadding="0" style="width:100%;border:0;margin:0 auto;outline:none">
        <tbody>
          <tr><td style="margin:0 auto;text-align:center;vertical-align:middle">
            <p style="margin:0;font-size:32px;font-weight:700;color:#161823;margin-bottom:16px;text-align:left">Seu pedido foi confirmado!</p>
          </td></tr>
          <tr><td style="margin:0 auto;text-align:left;vertical-align:middle">
            <span style="color:#50525A">Olá, {{PRIMEIRO}}!</span>
            <table><tbody><tr><td height="8px"></td></tr></tbody></table>
            <span style="color:#50525A">Seu pedido foi confirmado e está sendo preparado para envio!</span>
            <span style="color:#50525A"> Vamos enviar as informações de rastreio assim que ele estiver a caminho.</span>
            <table><tbody><tr><td height="8px"></td></tr></tbody></table>
            <span style="color:#50525A">Equipe {{NOME_LOJA}}</span>
          </td></tr>
        </tbody>
      </table>
    </td></tr></tbody>
  </table>
</td></tr>

<!-- ── Divisor ── -->
<tr><td>
  <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border:0;margin:0 auto">
    <tbody style="width:100%"><tr style="width:100%"><td style="text-align:center;vertical-align:middle;padding:16px">
      <p style="border-bottom:1px solid rgba(0,0,0,0.05);margin:0"></p>
    </td></tr></tbody>
  </table>
</td></tr>

<!-- ── Info do pedido: código + prazo ── -->
<tr><td>
  <table cellspacing="0" cellpadding="0" style="width:375px;border:0;margin:0 auto;table-layout:fixed;outline:none">
    <tbody>

      <!-- código + prazo -->
      <tr><td style="margin:0 auto;text-align:center;vertical-align:middle;width:375px">
        <div style="padding:0 16px;margin-top:16px">
          <div style="text-align:left;margin-bottom:16px;margin-top:16px">
            <span style="font-size:15px;font-weight:400;color:#000;line-height:18px">Código de rastreio: {{COD_FMT}}</span>
          </div>
          <div style="text-align:left">
            <span style="font-size:17px;font-weight:700;color:#000;line-height:22px">Entrega </span>
            <span style="font-size:17px;font-weight:700;color:#000;line-height:22px">{{PRAZO}}</span>
          </div>
        </div>
      </td></tr>

      <!-- nome da loja (seller) -->
      <tr><td style="margin-right:auto;text-align:left;vertical-align:middle;padding:12px 16px 0">
        <table cellspacing="0" cellpadding="0" style="width:100%;border:0;margin:0 auto;outline:none">
          <tbody><tr>
            <td style="margin-right:auto;text-align:left;vertical-align:middle">
              <p style="margin:0;font-size:15px;font-weight:600;color:#000">{{NOME_LOJA}}</p>
            </td>
          </tr></tbody>
        </table>
      </td></tr>

      <!-- produto + valor -->
      <tr><td style="margin:0 auto;text-align:center;vertical-align:middle">
        <table cellspacing="0" cellpadding="0" style="width:100%;border:0;margin:0 auto;outline:none">
          <tbody><tr><td style="padding:8px 16px 0">
            <table cellspacing="0" cellpadding="0" style="width:100%;border:0;margin:0 auto;outline:none">
              <tbody><tr>
                <td style="margin-right:auto;text-align:left;vertical-align:top">
                  <table cellspacing="0" cellpadding="0" style="width:100%;border:0;margin:0 auto;outline:none">
                    <tbody>
                      <tr><td style="text-align:left;padding-bottom:4px">
                        <p style="margin:0;font-size:13px;font-weight:600;color:#161823;line-height:17px;overflow:hidden;text-overflow:ellipsis">{{PRODUTO}}</p>
                      </td></tr>
                      <tr><td style="height:39px"></td></tr>
                      <tr><td>
                        <table cellspacing="0" cellpadding="0" style="width:100%;border:0;margin-right:auto;outline:none">
                          <tbody><tr>
                            <td style="text-align:left;vertical-align:middle">
                              <span style="font-size:13px;line-height:17px;font-weight:600;color:#000000">{{TOTAL}}</span>
                            </td>
                            <td style="text-align:right;vertical-align:middle">
                              <span style="font-size:12px;color:#000000;line-height:16px;font-weight:600"><span style="padding:0 4px">x</span>1</span>
                            </td>
                          </tr></tbody>
                        </table>
                      </td></tr>
                    </tbody>
                  </table>
                </td>
              </tr></tbody>
            </table>
          </td></tr></tbody>
        </table>
      </td></tr>

      <!-- botão CTA -->
      <tr><td style="margin:0 auto;text-align:center;vertical-align:middle;padding:16px;width:375px">
        <table cellspacing="0" cellpadding="0" style="width:100%;border:0;margin:0 auto;outline:none">
          <tbody><tr>
            <td style="margin:0 auto;text-align:center;vertical-align:middle;height:48px;border-radius:8px;padding:0;background-color:{{COR_BTN}};border-color:{{COR_BTN}}">
              <a href="{{LINK}}" target="_blank" style="color:#067df7;text-decoration:none;display:block">
                <p style="margin:0;font-size:15px;font-weight:500;color:#fff;width:100%;height:44px;line-height:44px">Rastrear meu pedido</p>
              </a>
            </td>
          </tr></tbody>
        </table>
      </td></tr>

    </tbody>
  </table>
</td></tr>

<!-- ── Divisor ── -->
<tr><td>
  <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border:0;margin:0 auto">
    <tbody style="width:100%"><tr style="width:100%"><td style="text-align:center;vertical-align:middle;padding:16px">
      <p style="border-bottom:1px solid rgba(0,0,0,0.05);margin:0"></p>
    </td></tr></tbody>
  </table>
</td></tr>

<!-- ── Endereço de envio ── -->
<tr><td>
  <table cellspacing="0" cellpadding="0" style="width:375px;border:0;margin:0 auto;background-color:#fff;outline:none">
    <tbody><tr><td style="margin:0 auto;text-align:center;vertical-align:middle;padding:16px">
      <table cellspacing="0" cellpadding="0" style="width:100%;border:0;margin:0 auto;outline:none">
        <tbody>
          <tr><td style="margin-right:auto;text-align:left;vertical-align:middle">
            <p style="margin:0;font-size:15px;font-weight:600;color:#161823;padding-bottom:16px">Endereço de envio</p>
          </td></tr>
          <tr><td style="margin-right:auto;text-align:left;vertical-align:middle">
            {{ADR_HTML}}
          </td></tr>
        </tbody>
      </table>
    </td></tr></tbody>
  </table>
</td></tr>

<!-- ── Divisor ── -->
<tr><td>
  <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border:0;margin:0 auto">
    <tbody style="width:100%"><tr style="width:100%"><td style="text-align:center;vertical-align:middle;padding:16px">
      <p style="border-bottom:1px solid rgba(0,0,0,0.05);margin:0"></p>
    </td></tr></tbody>
  </table>
</td></tr>

<!-- ── Resumo do pedido ── -->
<tr><td>
  <table cellspacing="0" cellpadding="0" style="width:375px;border:0;margin:0 auto;line-height:18px;outline:none">
    <tbody><tr><td style="margin:0 auto;text-align:left;vertical-align:middle">
      <p style="padding-left:16px;padding-right:16px;margin-bottom:16px;font-weight:600;font-size:15px">Resumo do pedido</p>
      <table cellspacing="0" cellpadding="0" style="width:100%;border:0;margin:0 auto;table-layout:fixed;font-size:15px;outline:none">
        <tbody>
          <tr style="height:32px">
            <td style="width:16px"></td>
            <td style="text-align:left;vertical-align:middle;width:60%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding-bottom:5px">
              <span style="color:rgba(22,24,35,0.75)">Total (1 item)</span>
            </td>
            <td style="text-align:right;vertical-align:middle;font-weight:bold">
              <span style="color:rgba(22,24,35,0.75)">{{TOTAL}}</span>
            </td>
            <td style="width:16px"></td>
          </tr>
        </tbody>
      </table>
    </td></tr></tbody>
  </table>
</td></tr>

<!-- ── Divisor ── -->
<tr><td>
  <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border:0;margin:0 auto">
    <tbody style="width:100%"><tr style="width:100%"><td style="text-align:center;vertical-align:middle;padding:16px">
      <p style="border-bottom:1px solid rgba(0,0,0,0.05);margin:0"></p>
    </td></tr></tbody>
  </table>
</td></tr>

<!-- ── Footer ── -->
<tr><td>
  <table cellspacing="0" cellpadding="0" style="width:375px;border:0;margin:0 auto;outline:none">
    <tbody><tr><td style="margin:0 auto;text-align:center;vertical-align:middle;padding-top:0px">
      <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border:0;margin:0 auto;background:#F5F5F5;padding:0 16px">
        <tbody style="width:100%">
          <tr><td style="height:24px"></td></tr>
          <tr style="width:100%"><td style="text-align:center;vertical-align:middle;padding:0 24px;color:#4E4F57;font-size:13px">
            Esta mensagem foi enviada para {{EMAIL_TO}} sobre uma compra recente em {{NOME_LOJA}}.
          </td></tr>
          <tr><td style="text-align:center;vertical-align:middle;color:#4E4F57;font-size:13px;padding-top:12px">
            Mensagem automática. Não é possível responder a este e-mail.
          </td></tr>
          <tr><td style="text-align:center;vertical-align:middle;padding:32px 0">
            <img alt="{{NOME_LOJA}}" src="https://p16-ttec-va.ibyteimg.com/tos-maliva-i-acgf4d7es9-us/LOGO.png~tplv-acgf4d7es9-image.image" width="130px" height="23px" style="display:block;width:130px;height:23px;vertical-align:middle;margin:0 auto">
          </td></tr>
        </tbody>
      </table>
    </td></tr></tbody>
  </table>
</td></tr>

</tbody>
</table>

</body>
</html>
TMPL;
}
