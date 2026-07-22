<?php
/**
 * recibo.php — Recibo de compra no estilo nota fiscal (sem valor fiscal)
 *
 * Acesse via: recibo.php?codigo=XXXXXXXXX
 * Use o botão "Imprimir / Salvar PDF" para gerar o PDF pelo navegador.
 */

date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/config.php';

$codigo = preg_replace('/[^A-Z0-9]/', '', strtoupper($_GET['codigo'] ?? ''));
$erro   = '';
$pedido = null;

if (empty($codigo)) {
    $erro = 'Código não informado.';
} elseif (!file_exists(__DIR__ . '/pedidos/' . $codigo . '.json')) {
    $erro = 'Pedido não encontrado para o código <strong>' . htmlspecialchars($codigo) . '</strong>.';
} else {
    $pedido = json_decode(file_get_contents(__DIR__ . '/pedidos/' . $codigo . '.json'), true);
}

function esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

if ($pedido) {
    $num_recibo    = str_pad(($pedido['posted_at'] % 999999) + 1, 6, '0', STR_PAD_LEFT);
    $data_emissao  = date('d/m/Y', $pedido['posted_at']);
    $hora_emissao  = date('H:i:s', $pedido['posted_at']);
    $ts_min        = $pedido['posted_at'] + (int)$pedido['prazo_min'] * 86400;
    $ts_max        = $pedido['posted_at'] + (int)$pedido['prazo_max'] * 86400;
    $prazo_fmt     = date('d/m/Y', $ts_min) . ' a ' . date('d/m/Y', $ts_max);
    $amount        = (float)($pedido['amount'] ?? 0);
    $total_fmt     = 'R$&nbsp;' . number_format($amount, 2, ',', '.');

    $nome_loja     = $config['nome_loja']      ?? '';
    $cnpj          = $config['cnpj']           ?? '';
    $endereco_loja = $config['endereco_loja']   ?? '';
    $cidade_orig   = $config['cidade_origem']   ?? '';
    $site_url      = rtrim($config['site_url']  ?? '', '/');

    $dest_nome   = $pedido['destinatario']   ?? '';
    $dest_email  = $pedido['email']          ?? '';
    $dest_rua    = $pedido['rua']            ?? '';
    $dest_num    = $pedido['numero']         ?? '';
    $dest_bairro = $pedido['bairro']         ?? '';
    $dest_cidade = $pedido['cidade_destino'] ?? '';
    $dest_estado = $pedido['estado_destino'] ?? '';
    $dest_cep    = $pedido['cep_destino']    ?? '';
    $produto     = $pedido['produto']        ?? 'Produto';

    $end_linha1  = $dest_rua . ($dest_num ? ', ' . $dest_num : '');
    $link_rastreio = $site_url ? $site_url . '/rastreio.php?codigo=' . rawurlencode($codigo) : '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo <?= esc($codigo) ?><?= isset($nome_loja) ? ' — ' . esc($nome_loja) : '' ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9pt;
            color: #000;
            background: #b0b8c4;
        }

        /* ── Barra de ações (oculta na impressão) ─────────────── */
        .toolbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            background: #1e2533;
            color: #fff;
            padding: 9px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 100;
            font-size: 9pt;
        }

        .toolbar button {
            background: #0a4f9e;
            color: #fff;
            border: none;
            padding: 6px 18px;
            cursor: pointer;
            border-radius: 3px;
            font-size: 9pt;
            font-weight: bold;
        }

        .toolbar button:hover { background: #083e7a; }

        .toolbar a {
            color: #90a8c8;
            text-decoration: none;
            font-size: 9pt;
        }

        .toolbar a:hover { color: #fff; }

        .toolbar-right {
            margin-left: auto;
            color: #666;
            font-size: 8pt;
        }

        /* ── Página A4 ────────────────────────────────────────── */
        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 52px auto 30px;
            padding: 10mm 10mm 12mm;
            background: #fff;
            box-shadow: 0 3px 16px rgba(0,0,0,.35);
        }

        @media print {
            body    { background: #fff; }
            .toolbar { display: none !important; }
            .page   { margin: 0; padding: 8mm; box-shadow: none; }
        }

        /* ── Utilities ────────────────────────────────────────── */
        .lbl {
            font-size: 6pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #444;
            margin-bottom: 2px;
        }

        .val      { font-size: 9pt; }
        .val-sm   { font-size: 8pt; }
        .val-lg   { font-size: 12pt; font-weight: bold; }
        .val-mono { font-family: 'Courier New', Courier, monospace; font-size: 9pt; letter-spacing: 1.5px; }
        .bold     { font-weight: bold; }
        .center   { text-align: center; }
        .right    { text-align: right; }
        .gray     { color: #999; }

        /* ── Cabeçalho ────────────────────────────────────────── */
        .header {
            display: flex;
            border: 1.5px solid #000;
        }

        .empresa {
            flex: 1;
            padding: 7px 10px;
            border-right: 1px solid #000;
        }

        .empresa-nome {
            font-size: 13pt;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .doc-panel {
            width: 62mm;
            display: flex;
            flex-direction: column;
        }

        .doc-titulo {
            background: #1e2533;
            color: #fff;
            text-align: center;
            font-size: 9.5pt;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 7px 4px;
            border-bottom: 1px solid #000;
        }

        .doc-row {
            display: flex;
            flex: 1;
        }

        .doc-row + .doc-row {
            border-top: 1px solid #ccc;
        }

        .doc-cell {
            flex: 1;
            padding: 4px 7px;
        }

        .doc-cell + .doc-cell {
            border-left: 1px solid #ccc;
        }

        /* ── Faixa do código de rastreio (estilo chave NF-e) ──── */
        .faixa-codigo {
            border: 1.5px solid #000;
            border-top: none;
            padding: 4px 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8f8f8;
        }

        .faixa-codigo .lbl { margin: 0; white-space: nowrap; }

        /* ── Seções genéricas ─────────────────────────────────── */
        .section {
            border: 1.5px solid #000;
            border-top: none;
        }

        .section-head {
            background: #e6e9ee;
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 2px 7px;
            border-bottom: 1px solid #000;
        }

        .fields {
            display: flex;
        }

        .fields + .fields {
            border-top: 1px solid #ccc;
        }

        .field {
            padding: 3px 7px;
            flex: 1;
            min-width: 0;
        }

        .field + .field {
            border-left: 1px solid #ccc;
        }

        /* ── Tabela de produtos ───────────────────────────────── */
        .tbl {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }

        .tbl th {
            background: #e6e9ee;
            font-size: 6.5pt;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.3px;
            padding: 3px 6px;
            border-bottom: 1px solid #000;
            border-right: 1px solid #ccc;
            text-align: left;
        }

        .tbl th:last-child { border-right: none; }

        .tbl td {
            padding: 5px 6px;
            border-bottom: 1px solid #e0e0e0;
            border-right: 1px solid #e0e0e0;
            vertical-align: top;
        }

        .tbl td:last-child { border-right: none; }
        .tbl tbody tr:last-child td { border-bottom: none; }

        .tbl tfoot td {
            font-weight: bold;
            font-size: 9.5pt;
            border-top: 1.5px solid #000;
            border-right: 1px solid #ccc;
            border-bottom: none;
            background: #f0f2f5;
            padding: 5px 6px;
        }

        .tbl tfoot td:last-child { border-right: none; }

        /* ── Disclaimer ───────────────────────────────────────── */
        .disclaimer {
            border: 1.5px solid #000;
            border-top: none;
            text-align: center;
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 5px;
            color: #555;
            background: #f8f8f8;
        }

        /* ── Erro ─────────────────────────────────────────────── */
        .erro-box {
            border: 1px solid #c00;
            background: #fff5f5;
            padding: 20px;
            margin-top: 20px;
            text-align: center;
            color: #c00;
        }

        /* ── Mobile (apenas em tela; a impressão mantém o A4) ──── */
        @media screen and (max-width: 820px) {
            body { font-size: 11px; }

            .toolbar {
                flex-wrap: wrap;
                gap: 8px 12px;
                padding: 8px 12px;
            }
            .toolbar-right { width: 100%; margin-left: 0; }

            /* página acompanha a largura da tela */
            .page {
                width: 100%;
                min-height: 0;
                margin: 64px 0 0;
                padding: 12px;
                box-shadow: none;
            }

            /* cabeçalho empilhado */
            .header { flex-direction: column; }
            .empresa { border-right: none; border-bottom: 1px solid #000; }
            .doc-panel { width: 100%; }

            /* faixa do código quebra linha se não couber */
            .faixa-codigo { flex-wrap: wrap; gap: 2px 10px; }

            /* campos lado a lado passam a empilhar */
            .fields { flex-direction: column; }
            .field { flex: 1 1 auto !important; }
            .field + .field { border-left: none; border-top: 1px solid #ccc; }

            /* larguras fixas dos campos deixam de valer no empilhamento */
            .field[style*="flex:0 0"] { flex: 1 1 auto !important; }

            /* tabela de produtos: fonte e espaçamento reduzidos para caber */
            .tbl { font-size: 8pt; }
            .tbl th, .tbl td { padding: 4px; }
        }

        @media screen and (max-width: 480px) {
            .empresa-nome { font-size: 12pt; }
            .doc-titulo { font-size: 9pt; letter-spacing: 1px; }
            .val-mono { letter-spacing: 1px; }
        }
    </style>
</head>
<body>

<?php if ($erro): ?>

<div class="toolbar">
    <a href="index.php">← Voltar</a>
</div>
<div class="page">
    <div class="erro-box"><?= $erro ?></div>
</div>

<?php else: ?>

<div class="toolbar">
    <button onclick="window.print()">⎙&nbsp; Imprimir / Salvar PDF</button>
    <a href="rastreio.php?codigo=<?= esc($codigo) ?>">← Rastreio</a>
    <a href="index.php">Início</a>
    <span class="toolbar-right">Recibo Nº <?= esc($num_recibo) ?> · Série 001</span>
</div>

<div class="page">

    <!-- ══ CABEÇALHO ═══════════════════════════════════════════ -->
    <div class="header">

        <!-- Dados do emitente -->
        <div class="empresa">
            <div class="empresa-nome"><?= esc($nome_loja) ?></div>
            <?php if ($cnpj): ?>
            <div class="lbl" style="margin-top:4px;">CNPJ</div>
            <div class="val"><?= esc($cnpj) ?></div>
            <?php endif; ?>
            <?php if ($endereco_loja): ?>
            <div class="lbl" style="margin-top:5px;">Endereço</div>
            <div class="val-sm"><?= esc($endereco_loja) ?></div>
            <?php else: ?>
            <div class="val-sm gray" style="margin-top:5px;"><?= esc($cidade_orig) ?></div>
            <?php endif; ?>
        </div>

        <!-- Identificação do documento -->
        <div class="doc-panel">
            <div class="doc-titulo">Recibo de Compra</div>
            <div class="doc-row">
                <div class="doc-cell">
                    <div class="lbl">Número</div>
                    <div class="val-lg center"><?= esc($num_recibo) ?></div>
                </div>
                <div class="doc-cell">
                    <div class="lbl">Série</div>
                    <div class="val bold center">001</div>
                </div>
            </div>
            <div class="doc-row">
                <div class="doc-cell">
                    <div class="lbl">Data de Emissão</div>
                    <div class="val bold center"><?= esc($data_emissao) ?></div>
                </div>
                <div class="doc-cell">
                    <div class="lbl">Hora</div>
                    <div class="val center"><?= esc($hora_emissao) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ CÓDIGO DE RASTREIO (estilo chave de acesso NF-e) ════ -->
    <div class="faixa-codigo">
        <span class="lbl">Código de Rastreio dos Correios</span>
        <span class="val-mono bold"><?= esc($codigo) ?></span>
    </div>

    <!-- ══ DESTINATÁRIO ════════════════════════════════════════ -->
    <div class="section">
        <div class="section-head">Destinatário / Comprador</div>
        <div class="fields">
            <div class="field" style="flex:3;">
                <div class="lbl">Nome completo</div>
                <div class="val"><?= esc($dest_nome) ?: '<span class="gray">—</span>' ?></div>
            </div>
            <div class="field" style="flex:3;">
                <div class="lbl">E-mail</div>
                <div class="val"><?= esc($dest_email) ?: '<span class="gray">—</span>' ?></div>
            </div>
            <div class="field" style="flex:2;">
                <div class="lbl">CPF / CNPJ</div>
                <div class="val gray">Não informado</div>
            </div>
        </div>
    </div>

    <!-- ══ ENDEREÇO DE ENTREGA ══════════════════════════════════ -->
    <div class="section">
        <div class="section-head">Endereço de Entrega</div>
        <div class="fields">
            <div class="field" style="flex:4;">
                <div class="lbl">Logradouro</div>
                <div class="val"><?= esc($end_linha1) ?: '<span class="gray">—</span>' ?></div>
            </div>
            <div class="field" style="flex:2;">
                <div class="lbl">Bairro</div>
                <div class="val"><?= esc($dest_bairro) ?: '<span class="gray">—</span>' ?></div>
            </div>
            <div class="field" style="flex:0 0 30mm;">
                <div class="lbl">CEP</div>
                <div class="val"><?= esc($dest_cep) ?: '<span class="gray">—</span>' ?></div>
            </div>
        </div>
        <div class="fields">
            <div class="field" style="flex:3;">
                <div class="lbl">Município</div>
                <div class="val"><?= esc($dest_cidade) ?: '<span class="gray">—</span>' ?></div>
            </div>
            <div class="field" style="flex:0 0 22mm;">
                <div class="lbl">UF</div>
                <div class="val bold center"><?= esc($dest_estado) ?: '<span class="gray">—</span>' ?></div>
            </div>
            <div class="field" style="flex:3;">
                <div class="lbl">País</div>
                <div class="val">Brasil</div>
            </div>
        </div>
    </div>

    <!-- ══ PRODUTOS / SERVIÇOS ══════════════════════════════════ -->
    <div class="section">
        <div class="section-head">Produtos / Serviços</div>
        <table class="tbl">
            <thead>
                <tr>
                    <th style="width:9mm;" class="center">#</th>
                    <th>Descrição do Produto / Serviço</th>
                    <th style="width:14mm;" class="center">Qtd.</th>
                    <th style="width:14mm;" class="center">Unid.</th>
                    <th style="width:32mm;" class="right">Valor Unitário</th>
                    <th style="width:32mm;" class="right">Valor Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="center">01</td>
                    <td><?= esc($produto) ?></td>
                    <td class="center">1</td>
                    <td class="center">UN</td>
                    <td class="right"><?= $total_fmt ?></td>
                    <td class="right bold"><?= $total_fmt ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="right">Total Geral</td>
                    <td class="right"><?= $total_fmt ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- ══ TRANSPORTE ═══════════════════════════════════════════ -->
    <div class="section">
        <div class="section-head">Informações de Transporte / Entrega</div>
        <div class="fields">
            <div class="field" style="flex:2;">
                <div class="lbl">Código de Rastreio</div>
                <div class="val-mono"><?= esc($codigo) ?></div>
            </div>
            <div class="field" style="flex:2;">
                <div class="lbl">Previsão de Entrega</div>
                <div class="val"><?= esc($prazo_fmt) ?></div>
            </div>
            <div class="field">
                <div class="lbl">Origem</div>
                <div class="val"><?= esc($cidade_orig) ?></div>
            </div>
            <div class="field">
                <div class="lbl">Destino</div>
                <div class="val"><?= esc(trim($dest_cidade . ($dest_estado ? ' - ' . $dest_estado : ''))) ?: '<span class="gray">—</span>' ?></div>
            </div>
        </div>
    </div>

    <!-- ══ OBSERVAÇÕES ══════════════════════════════════════════ -->
    <div class="section">
        <div class="section-head">Informações Complementares</div>
        <div class="field" style="min-height:16mm;">
            <div class="lbl" style="margin-bottom:4px;">Observações</div>
            <div class="val-sm">
                Compra realizada em <?= esc($data_emissao) ?> às <?= esc($hora_emissao) ?>.
                <?php if ($link_rastreio): ?>
                Rastreamento disponível em: <?= esc($link_rastreio) ?>.
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ══ DISCLAIMER ═══════════════════════════════════════════ -->
    <div class="disclaimer">
        Este documento não possui valor fiscal · Emitido exclusivamente para controle de compra e acompanhamento de entrega
    </div>

</div>

<?php endif; ?>
</body>
</html>
