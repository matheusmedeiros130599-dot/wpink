<?php
/**
 * rastreio.php — Página de resultado do rastreio
 *
 * Recebe GET ?codigo=XXXXXX, carrega o JSON do pedido e
 * exibe a linha do tempo com os status já ocorridos.
 */

date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/config.php';

// Sanitizar: apenas letras maiúsculas e números (evita path traversal)
$codigo = preg_replace('/[^A-Z0-9]/', '', strtoupper($_GET['codigo'] ?? ''));

$arquivo = __DIR__ . '/pedidos/' . $codigo . '.json';
$erro    = '';
$pedido  = null;
$eventos = [];
$data_estimada = '';

if (empty($codigo)) {
    $erro = 'Código de rastreio inválido ou não informado.';
} elseif (!file_exists($arquivo)) {
    $erro = 'Nenhum pedido encontrado com o código <strong>' . htmlspecialchars($codigo) . '</strong>.';
}

if (!$erro) {
    $pedido = json_decode(file_get_contents($arquivo), true);

    $posted_at   = (int) $pedido['posted_at'];
    $prazo_min   = (int) $pedido['prazo_min'];
    $prazo_max   = (int) $pedido['prazo_max'];
    $total_horas = ($prazo_min + $prazo_max) / 2 * 24;
    $total_seg   = $total_horas * 3600;

    $horas_decorridas = $total_horas > 0 ? (time() - $posted_at) / 3600 : $total_horas;
    $percentual       = $total_horas > 0 ? ($horas_decorridas / $total_horas) * 100 : 100;

    // Localidades formatadas
    $cidade_orig = $config['cidade_origem'];
    $cidade_dest = trim($pedido['cidade_destino'] ?? '');
    $estado_dest = trim($pedido['estado_destino'] ?? '');
    $local_dest  = $cidade_dest . ($estado_dest ? ' - ' . $estado_dest : '');

    // Definição dos cinco eventos possíveis (limiar em %, offset do timestamp)
    $definicoes = [
        [
            'limiar'    => 0,
            'offset'    => 0.00,
            'tipo'      => 'preparacao',
            'descricao' => 'Pedido em preparação.',
            'local'     => $cidade_orig,
            'de'        => null,
            'para'      => null,
        ],
        [
            'limiar'    => 8,
            'offset'    => 0.08,
            'tipo'      => 'postado',
            'descricao' => 'Objeto postado.',
            'local'     => $cidade_orig,
            'de'        => null,
            'para'      => null,
        ],
        [
            'limiar'    => 15,
            'offset'    => 0.15,
            'tipo'      => 'transferencia',
            'descricao' => 'Objeto em transferência - por favor aguarde.',
            'local'     => $local_dest,
            'de'        => $cidade_orig,
            'para'      => $local_dest,
        ],
        [
            'limiar'    => 75,
            'offset'    => 0.75,
            'tipo'      => 'saiu',
            'descricao' => 'Objeto saiu para entrega ao destinatário.',
            'local'     => $local_dest,
            'de'        => null,
            'para'      => null,
        ],
        [
            'limiar'    => 90,
            'offset'    => 0.90,
            'tipo'      => 'entregue',
            'descricao' => 'Objeto entregue ao destinatário.',
            'local'     => $local_dest,
            'de'        => null,
            'para'      => null,
        ],
    ];

    // Acumular apenas os eventos já ocorridos
    foreach ($definicoes as $def) {
        if ($percentual >= $def['limiar']) {
            $eventos[] = [
                'tipo'      => $def['tipo'],
                'descricao' => $def['descricao'],
                'local'     => $def['local'],
                'de'        => $def['de'],
                'para'      => $def['para'],
                'timestamp' => $posted_at + (int) ($def['offset'] * $total_seg),
            ];
        }
    }

    // Mais recente primeiro (igual aos Correios)
    $eventos = array_reverse($eventos);

    // Data estimada de entrega (prazo_max a partir da postagem)
    $data_estimada = date('d/m/Y', $posted_at + ($prazo_max * 86400));

    // Progresso para a barra (mínimo de 2% para sempre haver preenchimento visível)
    $progress_pct = max(2, min(100, round($percentual)));

    // Evento atual = mais recente (primeiro do array já invertido)
    $evento_atual = $eventos[0] ?? null;
    $entregue     = ($evento_atual['tipo'] ?? '') === 'entregue';
}

// Ícones SVG inline por tipo de status
function icone_svg(string $tipo): string {
    $icones = [
        'preparacao' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="9" y1="13" x2="15" y2="13"/>
                <line x1="9" y1="17" x2="13" y2="17"/>
            </svg>',
        'postado' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                <line x1="12" y1="22.08" x2="12" y2="12"/>
            </svg>',
        'transferencia' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="1" y="3" width="15" height="13"/>
                <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                <circle cx="5.5" cy="18.5" r="2.5"/>
                <circle cx="18.5" cy="18.5" r="2.5"/>
            </svg>',
        'saiu' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="5.5" cy="17.5" r="3.5"/>
                <circle cx="18.5" cy="17.5" r="3.5"/>
                <path d="M15 6h-3L9 10.5H4l1.5 4H9"/>
                <path d="M15 6l3 4.5H22"/>
                <line x1="12" y1="6" x2="12" y2="3"/>
            </svg>',
        'entregue' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>',
    ];
    return $icones[$tipo] ?? '';
}

// Formata o código no padrão "QN 749 955 838 BR" quando bate o padrão dos Correios
function formatar_codigo(string $cod): string {
    if (preg_match('/^([A-Z]{2})(\d{3})(\d{3})(\d{3})([A-Z]{2})$/', $cod, $m)) {
        return "$m[1] $m[2] $m[3] $m[4] $m[5]";
    }
    return $cod;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rastreio <?= htmlspecialchars($codigo) ?> — <?= htmlspecialchars($config['nome_loja']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        :root {
            --cor-primaria: <?= htmlspecialchars($config['cor_primaria']) ?>;
            --cor-secundaria: <?= htmlspecialchars($config['cor_secundaria']) ?>;
        }
    </style>
</head>
<body>

    <header class="topo">
        <div class="topo-inner">
            <span class="topo-menu" aria-hidden="true"><span></span><span></span><span></span></span>
            <?php if (!empty($config['logo_url'])): ?>
                <img src="<?= htmlspecialchars($config['logo_url']) ?>"
                     alt="<?= htmlspecialchars($config['nome_loja']) ?>" class="logo">
            <?php else: ?>
                <span class="nome-loja"><?= htmlspecialchars($config['nome_loja']) ?></span>
            <?php endif; ?>
        </div>
    </header>

    <main class="container">

        <a href="index.php" class="breadcrumb">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Rastreamento
        </a>

        <?php if ($erro): ?>

            <h1 class="titulo-pagina">Rastreamento</h1>

            <div class="card-erro">
                <div class="erro-icone">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <h2>Não foi possível rastrear</h2>
                <p><?= $erro ?></p>
                <a href="index.php" class="btn">Tentar outro código</a>
            </div>

        <?php else: ?>

            <h1 class="titulo-pagina codigo"><?= htmlspecialchars(formatar_codigo($pedido['codigo'])) ?></h1>

            <div class="objeto-tipo">
                <span class="marca" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                        <polyline points="3.29 7 12 12 20.71 7"/><line x1="12" y1="22" x2="12" y2="12"/>
                    </svg>
                </span>
                <span class="rotulo">ENCOMENDA</span>
            </div>

            <?php if ($data_estimada || !empty($pedido['destinatario']) || !empty($pedido['produto'])): ?>
            <div class="objeto-resumo">
                <?php if ($data_estimada): ?>
                <div class="linha">
                    <span class="chave"><?= $entregue ? 'Entregue em' : 'Previsão de entrega' ?></span>
                    <span class="valor destaque"><?= $data_estimada ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($pedido['destinatario'])): ?>
                <div class="linha">
                    <span class="chave">Destinatário</span>
                    <span class="valor"><?= htmlspecialchars($pedido['destinatario']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($pedido['produto'])): ?>
                <div class="linha">
                    <span class="chave">Produto</span>
                    <span class="valor"><?= htmlspecialchars($pedido['produto']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Linha do tempo -->
            <div class="timeline">
                <?php foreach ($eventos as $i => $ev): ?>
                    <div class="timeline-item <?= $i === 0 ? 'pendente' : '' ?>">
                        <div class="timeline-icone">
                            <?= icone_svg($ev['tipo']) ?>
                        </div>
                        <div class="timeline-conteudo">
                            <p class="timeline-status"><?= htmlspecialchars($ev['descricao']) ?></p>

                            <?php if ($ev['tipo'] === 'transferencia' && $ev['de'] && $ev['para']): ?>
                                <p class="timeline-local">
                                    <?= htmlspecialchars($ev['de']) ?>
                                    <span class="seta">&rarr;</span>
                                    <?= htmlspecialchars($ev['para']) ?>
                                </p>
                            <?php elseif (!empty($ev['local'])): ?>
                                <p class="timeline-local"><?= htmlspecialchars($ev['local']) ?></p>
                            <?php endif; ?>

                            <p class="timeline-data"><?= date('d/m/Y', $ev['timestamp']) ?> <?= date('H:i', $ev['timestamp']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

        <div class="rodape-acao">
            <a href="index.php" class="btn btn-secundario">Rastrear outro código</a>
        </div>

    </main>

    <footer class="rodape">
        <p>© <?= date('Y') ?> <?= htmlspecialchars($config['nome_loja']) ?> · Acompanhamento de pedidos</p>
    </footer>

</body>
</html>
