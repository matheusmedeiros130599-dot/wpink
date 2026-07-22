<?php
/**
 * index.php — Página de consulta de rastreio
 *
 * Exibe formulário para o cliente digitar o código de rastreio.
 */

date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rastreamento — <?= htmlspecialchars($config['nome_loja']) ?></title>
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

        <h1 class="titulo-pagina">Rastreamento</h1>

        <form method="GET" action="rastreio.php" class="caixa-consulta">
            <p class="pergunta">Deseja acompanhar seu objeto?<br>Digite o <strong>código de rastreio</strong> enviado para o seu e-mail.</p>

            <input
                type="text"
                name="codigo"
                placeholder="Ex: QN749955838BR"
                class="input-codigo"
                required
                autocomplete="off"
                autocapitalize="characters"
                spellcheck="false"
            >
            <p class="legenda-input">O código tem 13 caracteres (2 letras, 9 números e 2 letras).</p>

            <div class="acao">
                <button type="submit" class="btn">Consultar</button>
            </div>
        </form>

    </main>

    <footer class="rodape">
        <p>© <?= date('Y') ?> <?= htmlspecialchars($config['nome_loja']) ?> · Acompanhamento de pedidos</p>
    </footer>

</body>
</html>
