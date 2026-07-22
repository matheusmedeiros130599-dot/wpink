<?php
/**
 * como-usar.php — Guia de instalação e uso (DuttyFy)
 *
 * Página de instruções para o lojista configurar o sistema de rastreio
 * e integrá-lo com a plataforma de vendas DuttyFy.
 */

date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/config.php';

// Monta a URL base real a partir de onde o arquivo está hospedado.
// Assim o lojista já vê o link exato do SEU domínio para copiar.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'www.seusite.com';
$dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/rastreio/')), '/');
$base   = $scheme . '://' . $host . $dir;

$painel_url   = $base . '/admin/';
$webhook_url  = $base . '/webhook.php';
$consulta_url = $base . '/?codigo=QN749955838BR';

// String de parâmetros tr_ de exemplo (padrão do código)
$utm_exemplo = 'tr_codigo=QN749955838BR&tr_prazo_min=5&tr_prazo_max=7&tr_produto=Kit+Presencial+Premium&tr_cidade=São+Paulo&tr_estado=SP&tr_cep=01310-100&tr_rua=Avenida+Paulista&tr_numero=1000&tr_bairro=Bela+Vista';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Como usar o rastreio com a DuttyFy — <?= htmlspecialchars($config['nome_loja']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        :root {
            --cor-primaria: <?= htmlspecialchars($config['cor_primaria']) ?>;
            --cor-secundaria: <?= htmlspecialchars($config['cor_secundaria']) ?>;
        }
    </style>
</head>
<body class="pagina-guia">

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

        <section class="guia-hero">
            <span class="tag">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                Guia de instalação
            </span>
            <h1>Como ativar o rastreio nas suas vendas com a DuttyFy</h1>
            <p>Quando uma venda for aprovada na DuttyFy, ela avisa o seu site, que cria a página de rastreio do cliente sozinho. Você configura <strong>uma vez</strong>, pelo painel, sem precisar mexer em nenhum código. Siga os passos na ordem.</p>
        </section>

        <!-- Como funciona (visão geral) -->
        <div class="fluxo">
            <div class="fluxo-item">
                <div class="fi-ico"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg></div>
                <div class="fi-txt"><strong>Venda aprovada</strong>na DuttyFy</div>
            </div>
            <div class="fluxo-item">
                <div class="fi-ico"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/></svg></div>
                <div class="fi-txt"><strong>O site é avisado</strong>automaticamente</div>
            </div>
            <div class="fluxo-item">
                <div class="fi-ico"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                <div class="fi-txt"><strong>Pedido criado</strong>com os dados da entrega</div>
            </div>
            <div class="fluxo-item">
                <div class="fi-ico"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>
                <div class="fi-txt"><strong>Cliente acompanha</strong>pelo link</div>
            </div>
        </div>

        <div class="passos">

            <!-- PASSO 1 -->
            <section class="passo">
                <div class="passo-cabeca">
                    <span class="passo-num">1</span>
                    <h2 class="passo-titulo">Suba a pasta para a sua hospedagem</h2>
                </div>
                <div class="passo-corpo">
                    <p>No gerenciador de arquivos da sua hospedagem (onde fica o seu domínio), envie a pasta <code>rastreio/</code> inteira para a área pública do site — normalmente a pasta chamada <code>public_html</code>.</p>
                    <p>Depois de subir, marque <strong>duas</strong> pastas com permissão de escrita (no gerenciador, clique com o botão direito na pasta → Permissões → <strong>755</strong>):</p>
                    <ul>
                        <li><code>pedidos/</code> — é onde cada venda fica salva.</li>
                        <li><code>admin/</code> — é onde o painel guarda as suas configurações.</li>
                    </ul>
                    <div class="callout dica">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span>Não sabe dar permissão? Pode pular por agora. Se na hora de salvar o painel aparecer um aviso de permissão, volte aqui e faça este passo.</span>
                    </div>
                </div>
            </section>

            <!-- PASSO 2 -->
            <section class="passo">
                <div class="passo-cabeca">
                    <span class="passo-num">2</span>
                    <h2 class="passo-titulo">Abra o painel e configure a sua loja</h2>
                </div>
                <div class="passo-corpo">
                    <p>Toda a configuração é feita por um painel, pelo navegador. <strong>Você não precisa abrir nenhum arquivo de código.</strong> Acesse o endereço do seu painel:</p>
                    <div class="bloco-codigo">
                        <div class="bc-topo">
                            <span class="bc-rotulo">endereço do painel</span>
                            <button class="btn-copiar" type="button" data-alvo="painel">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                Copiar
                            </button>
                        </div>
<pre id="painel" class="quebra"><?= htmlspecialchars($painel_url) ?></pre>
                    </div>
                    <p>Na primeira vez, entre com a senha <code>admin</code>. Depois preencha os campos da loja e clique em <strong>Salvar configurações</strong>:</p>
                    <ul>
                        <li><strong>Nome da loja</strong>, <strong>cor principal</strong> e <strong>cor secundária</strong> — aparecem no topo das páginas e nos e-mails.</li>
                        <li><strong>Cidade de origem</strong> — de onde os pedidos saem (ex.: <code>São Paulo - SP</code>).</li>
                        <li><strong>Prazo mínimo e máximo</strong> — quantos dias a entrega costuma levar (é só um padrão; a venda pode mandar o prazo dela).</li>
                    </ul>
                    <div class="callout aviso">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <span><strong>Troque a senha logo no primeiro acesso.</strong> Lá embaixo, na seção <strong>Segurança</strong>, escreva uma senha nova e salve. Enquanto você usar a senha <code>admin</code>, o painel mostra um aviso amarelo no topo.</span>
                    </div>
                    <div class="callout dica">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span>Os campos marcados com <strong style="color:#b42318;">*</strong> são obrigatórios. Se algum ficar vazio, o painel avisa e <strong>não perde</strong> o que você já digitou — é só completar e salvar de novo.</span>
                    </div>
                </div>
            </section>

            <!-- PASSO 3 -->
            <section class="passo">
                <div class="passo-cabeca">
                    <span class="passo-num">3</span>
                    <h2 class="passo-titulo">Ligue os e-mails automáticos <span style="font-weight:500;opacity:.7;">(opcional, mas recomendado)</span></h2>
                </div>
                <div class="passo-corpo">
                    <p>Com isso, o cliente recebe um e-mail quando a compra é confirmada e quando o status muda. Tudo de graça, usando um serviço chamado <strong>Resend</strong>. Se você não quiser e-mails, pode pular este passo — o rastreio funciona do mesmo jeito.</p>
                    <p>Para ligar:</p>
                    <ul>
                        <li>Crie uma conta gratuita no <strong>Resend</strong> e pegue a sua <strong>chave de API</strong> (começa com <code>re_</code>).</li>
                        <li>No painel, na seção <strong>E-mail (Resend)</strong>, cole a chave e o <strong>e-mail remetente</strong>.</li>
                        <li>Mais abaixo, na seção <strong>Segurança</strong>, clique em <strong>Gerar</strong> para criar o <strong>token do cron</strong> e salve.</li>
                    </ul>
                    <div class="callout dica">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        <span><strong>Não sabe pegar a chave do Resend?</strong> Veja o passo a passo em vídeo:
                            <a href="https://www.youtube.com/watch?v=61pF0S-TTuk" target="_blank" rel="noopener" style="text-decoration:underline;">assistir no YouTube</a>.
                        </span>
                    </div>
                </div>
            </section>

            <!-- PASSO 4 -->
            <section class="passo">
                <div class="passo-cabeca">
                    <span class="passo-num">4</span>
                    <h2 class="passo-titulo">Cadastre o link de aviso na DuttyFy</h2>
                </div>
                <div class="passo-corpo">
                    <p>Agora você liga o seu site à DuttyFy. Copie o endereço abaixo — é o link que a DuttyFy vai usar para avisar o seu site a cada venda aprovada:</p>
                    <div class="bloco-codigo">
                        <div class="bc-topo">
                            <span class="bc-rotulo">link de aviso (webhook)</span>
                            <button class="btn-copiar" type="button" data-alvo="wh">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                Copiar
                            </button>
                        </div>
<pre id="wh" class="quebra"><?= htmlspecialchars($webhook_url) ?></pre>
                    </div>
                    <p>Dentro da <strong>DuttyFy</strong>, vá em <strong>Webhooks / Integrações</strong> e cadastre um novo:</p>
                    <ul>
                        <li>Cole o <strong>link de aviso</strong> que você copiou acima.</li>
                        <li>Escolha o evento de <strong>venda aprovada</strong>.</li>
                        <li>Se pedir o formato, deixe em <strong>JSON</strong>.</li>
                    </ul>
                    <div class="callout dica">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span>Só vendas aprovadas geram rastreio. Pedido pendente, reembolso, etc. são ignorados sozinhos — você não precisa fazer nada.</span>
                    </div>
                </div>
            </section>

            <!-- PASSO 5 -->
            <section class="passo">
                <div class="passo-cabeca">
                    <span class="passo-num">5</span>
                    <h2 class="passo-titulo">Informe os dados da entrega na venda</h2>
                </div>
                <div class="passo-corpo">
                    <p>Para o rastreio aparecer completo, a venda precisa carregar os dados da entrega. Isso é feito por uns parâmetros que começam com <code>tr_</code>, colados no <strong>campo UTM</strong> do seu produto/checkout na DuttyFy.</p>
                    <p>Copie a linha abaixo e <strong>troque os valores pelos da venda</strong>:</p>
                    <div class="bloco-codigo">
                        <div class="bc-topo">
                            <span class="bc-rotulo">cole isto no campo UTM</span>
                            <button class="btn-copiar" type="button" data-alvo="utm">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                Copiar
                            </button>
                        </div>
<pre id="utm" class="quebra"><?= htmlspecialchars($utm_exemplo) ?></pre>
                    </div>

                    <div class="callout aviso">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <span>Duas regras simples: os nomes são em <strong>minúsculo</strong> e separados por <code>&amp;</code>; e <strong>espaço vira <code>+</code></strong> (ex.: <code>São+Paulo</code>). Um nome digitado errado simplesmente não aparece no rastreio.</span>
                    </div>

                    <p>O que cada parâmetro significa:</p>
                    <div class="tabela-wrap">
                        <table class="tabela-params">
                            <thead>
                                <tr><th>Parâmetro</th><th>Obrigatório</th><th>O que é</th><th>Exemplo</th></tr>
                            </thead>
                            <tbody>
                                <tr><td><code>tr_codigo</code></td><td><span class="obrig sim">Sim</span></td><td>Código de rastreio do objeto</td><td><code>QN749955838BR</code></td></tr>
                                <tr><td><code>tr_prazo_min</code></td><td><span class="obrig nao">Não</span></td><td>Prazo mínimo (dias)</td><td><code>5</code></td></tr>
                                <tr><td><code>tr_prazo_max</code></td><td><span class="obrig nao">Não</span></td><td>Prazo máximo (dias)</td><td><code>7</code></td></tr>
                                <tr><td><code>tr_produto</code></td><td><span class="obrig nao">Não</span></td><td>Nome do produto</td><td><code>Kit+Presencial+Premium</code></td></tr>
                                <tr><td><code>tr_cidade</code></td><td><span class="obrig nao">Não</span></td><td>Cidade de destino</td><td><code>São+Paulo</code></td></tr>
                                <tr><td><code>tr_estado</code></td><td><span class="obrig nao">Não</span></td><td>Estado (sigla)</td><td><code>SP</code></td></tr>
                                <tr><td><code>tr_cep</code></td><td><span class="obrig nao">Não</span></td><td>CEP de destino</td><td><code>01310-100</code></td></tr>
                                <tr><td><code>tr_rua</code></td><td><span class="obrig nao">Não</span></td><td>Rua de destino</td><td><code>Avenida+Paulista</code></td></tr>
                                <tr><td><code>tr_numero</code></td><td><span class="obrig nao">Não</span></td><td>Número</td><td><code>1000</code></td></tr>
                                <tr><td><code>tr_bairro</code></td><td><span class="obrig nao">Não</span></td><td>Bairro</td><td><code>Bela+Vista</code></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="callout dica">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span>Só o <code>tr_codigo</code> é obrigatório. Sem <code>tr_prazo_min/max</code>, o sistema usa o prazo que você pôs no painel; sem <code>tr_produto</code>, ele aproveita o nome do produto da própria venda.</span>
                    </div>
                </div>
            </section>

            <!-- PASSO 6 -->
            <section class="passo">
                <div class="passo-cabeca">
                    <span class="passo-num">6</span>
                    <h2 class="passo-titulo">Mande o link de rastreio para o cliente</h2>
                </div>
                <div class="passo-corpo">
                    <p>Pronto! Assim que a venda é aprovada, o pedido já existe no seu site. Mande para o cliente (e-mail ou WhatsApp) o link abaixo, trocando o código pelo <strong>mesmo</strong> que você usou em <code>tr_codigo</code>:</p>
                    <div class="bloco-codigo">
                        <div class="bc-topo">
                            <span class="bc-rotulo">link do cliente</span>
                            <button class="btn-copiar" type="button" data-alvo="cli">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                Copiar
                            </button>
                        </div>
<pre id="cli" class="quebra"><?= htmlspecialchars($consulta_url) ?></pre>
                    </div>
                    <p>Ele verá a linha do tempo da entrega evoluindo sozinha conforme os dias passam desde a aprovação, com a previsão calculada pelo prazo informado. Se você ligou os e-mails no passo 3, o cliente ainda recebe os avisos automáticos.</p>
                </div>
            </section>

        </div>

        <div class="rodape-acao">
            <a href="admin/" class="btn">Abrir o painel de configuração</a>
        </div>

    </main>

    <footer class="rodape">
        <p>© <?= date('Y') ?> <?= htmlspecialchars($config['nome_loja']) ?> · Acompanhamento de pedidos</p>
    </footer>

    <script>
        document.querySelectorAll('.btn-copiar').forEach(function (botao) {
            botao.addEventListener('click', function () {
                var alvo = document.getElementById(botao.dataset.alvo);
                if (!alvo) return;
                var texto = alvo.innerText;
                navigator.clipboard.writeText(texto).then(function () {
                    var original = botao.innerHTML;
                    botao.classList.add('copiado');
                    botao.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Copiado!';
                    setTimeout(function () {
                        botao.classList.remove('copiado');
                        botao.innerHTML = original;
                    }, 1800);
                });
            });
        });
    </script>

</body>
</html>
