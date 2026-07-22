<?php
/**
 * admin/index.php — Painel de configuração da loja
 *
 * Edita os mesmos valores do config.php sem precisar abrir o arquivo.
 * O que é salvo aqui vai para admin/data.json (protegido pelo .htaccess)
 * e sobrescreve os padrões definidos no config.php.
 */

// Mantém o admin logado por 8h — evita ser deslogado no meio da edição e
// perder o que foi digitado caso a edição demore mais que os ~24min padrão do PHP.
$session_lifetime = 8 * 60 * 60; // 8 horas em segundos
ini_set('session.gc_maxlifetime', (string) $session_lifetime);
session_set_cookie_params($session_lifetime);
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/../config.php';

$data_file = __DIR__ . '/data.json';

// Campos que o painel pode gravar (whitelist — evita injeção de chaves arbitrárias)
$campos = [
    'nome_loja', 'cor_primaria', 'cor_secundaria', 'logo_url',
    'cidade_origem', 'prazo_min_padrao', 'prazo_max_padrao',
    'resend_api_key', 'resend_from', 'nome_email', 'email_template', 'site_url',
    'cron_token', 'cnpj', 'endereco_loja',
];

function esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/** Confere a senha: usa o hash salvo se existir, senão a senha padrão do config. */
function senha_correta(string $input, array $config, string $data_file): bool {
    if (is_file($data_file)) {
        $d = json_decode(file_get_contents($data_file), true);
        if (!empty($d['admin_password_hash'])) {
            return password_verify($input, $d['admin_password_hash']);
        }
    }
    return hash_equals((string) ($config['admin_password'] ?? ''), $input);
}

/** Indica se ainda está usando a senha padrão (sem hash salvo). */
function usando_senha_padrao(string $data_file): bool {
    if (!is_file($data_file)) return true;
    $d = json_decode(file_get_contents($data_file), true);
    return empty($d['admin_password_hash']);
}

// ── Logout ──────────────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// ── Login ───────────────────────────────────────────────────────────────────
$erro_login = '';
if (($_POST['acao'] ?? '') === 'login') {
    if (senha_correta($_POST['senha'] ?? '', $config, $data_file)) {
        session_regenerate_id(true);
        $_SESSION['admin_ok'] = true;
        $_SESSION['csrf']     = bin2hex(random_bytes(16));
        header('Location: index.php');
        exit;
    }
    $erro_login = 'Senha incorreta.';
}

$logado = !empty($_SESSION['admin_ok']);
if ($logado && empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

// ── Salvar configurações ────────────────────────────────────────────────────
$msg_ok = '';
$msg_erro = '';
// Campos que não podem ficar vazios (rótulo usado na mensagem de erro)
$obrigatorios = [
    'nome_loja'      => 'Nome da loja',
    'cor_primaria'   => 'Cor principal',
    'cor_secundaria' => 'Cor secundária',
    'cidade_origem'  => 'Cidade de origem',
];

if ($logado && ($_POST['acao'] ?? '') === 'salvar') {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $msg_erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $dados = is_file($data_file) ? (json_decode(file_get_contents($data_file), true) ?: []) : [];

        foreach ($campos as $c) {
            $v = trim($_POST[$c] ?? '');
            if ($c === 'prazo_min_padrao' || $c === 'prazo_max_padrao') {
                $v = max(1, (int) $v);
            }
            $dados[$c] = $v;
        }

        // Validação dos campos obrigatórios (reforço no servidor além do required do HTML)
        $faltando = [];
        foreach ($obrigatorios as $campo => $rotulo) {
            if (trim((string) ($dados[$campo] ?? '')) === '') {
                $faltando[] = $rotulo;
            }
        }
        if ($faltando) {
            $msg_erro = 'Preencha os campos obrigatórios: ' . implode(', ', $faltando) . '.';
        }

        // Troca de senha (opcional)
        $nova = $_POST['nova_senha'] ?? '';
        if (!$msg_erro && $nova !== '') {
            if (strlen($nova) < 4) {
                $msg_erro = 'A nova senha precisa ter pelo menos 4 caracteres.';
            } else {
                $dados['admin_password_hash'] = password_hash($nova, PASSWORD_DEFAULT);
            }
        }

        // Reflete os valores enviados de volta no formulário — mesmo se der erro,
        // o que o usuário digitou não se perde (ele só corrige o que faltou).
        $config = array_merge($config, $dados);

        if (!$msg_erro) {
            $ok = file_put_contents(
                $data_file,
                json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            if ($ok === false) {
                $msg_erro = 'Não foi possível salvar. Confira a permissão de escrita na pasta /admin/ (chmod 755).';
            } else {
                $msg_ok = 'Configurações salvas com sucesso.';
            }
        }
    }
}

// Valor atual de um campo para preencher o formulário
function val(string $campo, array $config): string {
    return esc((string) ($config[$campo] ?? ''));
}

$cor_accent = $config['cor_primaria'] ?: '#0a4f9e';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Painel · <?= val('nome_loja', $config) ?: 'Configurações' ?></title>
    <style>
        :root { --accent: <?= esc($cor_accent) ?>; }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: #eef1f6;
            color: #1e2533;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        a { color: var(--accent); }

        /* ── Login ──────────────────────────────────────────── */
        .login-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            background: #fff;
            width: 100%;
            max-width: 360px;
            border-radius: 14px;
            padding: 32px 28px;
            box-shadow: 0 10px 40px rgba(30,37,51,.12);
            text-align: center;
        }

        .login-card .lock {
            width: 52px; height: 52px;
            border-radius: 50%;
            background: var(--accent);
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
        }

        .login-card h1 { font-size: 19px; margin-bottom: 4px; }
        .login-card p  { font-size: 13px; color: #6b7280; margin-bottom: 22px; }

        /* ── Layout do painel ───────────────────────────────── */
        .topbar {
            background: #fff;
            border-bottom: 1px solid #e2e6ee;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .topbar .brand {
            font-weight: 700;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .topbar .brand .dot {
            width: 22px; height: 22px; border-radius: 6px;
            background: var(--accent);
            display: inline-block;
        }

        .topbar .spacer { margin-left: auto; }

        .topbar a.sair {
            font-size: 13px;
            color: #6b7280;
            text-decoration: none;
            padding: 7px 14px;
            border: 1px solid #e2e6ee;
            border-radius: 8px;
        }
        .topbar a.sair:hover { background: #f5f7fb; color: #1e2533; }

        .wrap {
            max-width: 760px;
            margin: 0 auto;
            padding: 22px 20px 60px;
        }

        /* ── Avisos ─────────────────────────────────────────── */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 18px;
            display: flex;
            gap: 9px;
            align-items: flex-start;
        }
        .alert.ok   { background: #e7f6ec; color: #166534; border: 1px solid #bbe8c9; }
        .alert.erro { background: #fdeaea; color: #b42318; border: 1px solid #f6c9c4; }
        .alert.warn { background: #fff7e6; color: #92560a; border: 1px solid #ffe2ad; }

        /* ── Cards de seção ─────────────────────────────────── */
        .card {
            background: #fff;
            border: 1px solid #e2e6ee;
            border-radius: 14px;
            margin-bottom: 18px;
            overflow: hidden;
        }

        .card-head {
            padding: 15px 18px 14px;
            border-bottom: 1px solid #eef1f6;
        }
        .card-head h2 { font-size: 15px; }
        .card-head p  { font-size: 12.5px; color: #8a92a3; margin-top: 2px; }

        .card-body { padding: 6px 18px 18px; }

        /* ── Campos ─────────────────────────────────────────── */
        .field { padding-top: 14px; }
        .field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .field .hint {
            font-size: 12px;
            color: #9aa1b0;
            font-weight: 400;
        }
        .field .obrig { color: #b42318; font-weight: 700; }

        input[type="text"],
        input[type="password"],
        input[type="number"],
        input[type="url"],
        input[type="email"],
        select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d6dbe6;
            border-radius: 9px;
            font-size: 14px;
            font-family: inherit;
            color: #1e2533;
            background: #fbfcfe;
            transition: border-color .15s, box-shadow .15s;
        }
        select { cursor: pointer; height: 42px; }
        input:focus, select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(10,79,158,.12);
            background: #fff;
        }

        .row { display: flex; gap: 14px; flex-wrap: wrap; }
        .row > .field { flex: 1 1 200px; }

        /* cor */
        .cor-field { display: flex; align-items: center; gap: 10px; }
        .cor-field input[type="color"] {
            width: 46px; height: 42px;
            border: 1px solid #d6dbe6;
            border-radius: 9px;
            background: #fff;
            padding: 3px;
            cursor: pointer;
            flex: 0 0 auto;
        }
        .cor-field input[type="text"] { flex: 1; }

        /* input com botão ao lado */
        .input-acao { display: flex; gap: 8px; }
        .input-acao input { flex: 1; }
        .btn-mini {
            border: 1px solid #d6dbe6;
            background: #f5f7fb;
            border-radius: 9px;
            padding: 0 14px;
            font-size: 13px;
            font-weight: 600;
            color: #475067;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn-mini:hover { background: #eaeef6; }

        /* ── Botões ─────────────────────────────────────────── */
        .btn {
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px 22px;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            width: 100%;
        }
        .btn:hover { filter: brightness(.93); }

        .save-bar {
            position: sticky;
            bottom: 0;
            background: linear-gradient(transparent, #eef1f6 28%);
            padding: 14px 0 4px;
        }

        .links-rapidos {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .links-rapidos a {
            font-size: 13px;
            text-decoration: none;
            color: #475067;
            background: #fff;
            border: 1px solid #e2e6ee;
            padding: 8px 14px;
            border-radius: 8px;
        }
        .links-rapidos a:hover { border-color: var(--accent); color: var(--accent); }

        @media (max-width: 480px) {
            .wrap { padding: 16px 14px 50px; }
            .card-body { padding: 4px 14px 16px; }
        }
    </style>
</head>
<body>

<?php if (!$logado): ?>

    <!-- ══ TELA DE LOGIN ═══════════════════════════════════════ -->
    <div class="login-wrap">
        <form class="login-card" method="POST" action="index.php">
            <input type="hidden" name="acao" value="login">
            <div class="lock">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </div>
            <h1>Painel de configuração</h1>
            <p>Digite a senha para continuar</p>

            <?php if ($erro_login): ?>
                <div class="alert erro" style="text-align:left;"><?= esc($erro_login) ?></div>
            <?php endif; ?>

            <div class="field" style="text-align:left; padding-top:0;">
                <input type="password" name="senha" placeholder="Senha de acesso" autofocus required>
            </div>
            <div style="margin-top:16px;">
                <button type="submit" class="btn">Entrar</button>
            </div>
        </form>
    </div>

<?php else: ?>

    <!-- ══ PAINEL ══════════════════════════════════════════════ -->
    <div class="topbar">
        <span class="brand"><span class="dot"></span><?= val('nome_loja', $config) ?: 'Minha Loja' ?></span>
        <span class="spacer"></span>
        <a class="sair" href="index.php?logout=1">Sair</a>
    </div>

    <div class="wrap">

        <div class="links-rapidos">
            <a href="../index.php" target="_blank">🔎 Página de rastreio</a>
            <a href="../como-usar.php" target="_blank">📘 Como usar</a>
        </div>

        <?php if ($msg_ok): ?>
            <div class="alert ok">✔ <?= esc($msg_ok) ?></div>
        <?php endif; ?>
        <?php if ($msg_erro): ?>
            <div class="alert erro">✕ <?= esc($msg_erro) ?></div>
        <?php endif; ?>
        <?php if (usando_senha_padrao($data_file)): ?>
            <div class="alert warn">
                ⚠ Você ainda está usando a senha padrão. Defina uma senha própria no fim da página, em <strong>Segurança</strong>.
            </div>
        <?php endif; ?>

        <form method="POST" action="index.php">
            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>">

            <!-- Identidade visual -->
            <div class="card">
                <div class="card-head">
                    <h2>Identidade da loja</h2>
                    <p>Nome, cores e logo exibidos nas páginas e e-mails</p>
                </div>
                <div class="card-body">
                    <div class="field">
                        <label>Nome da loja <span class="obrig">*</span></label>
                        <input type="text" name="nome_loja" value="<?= val('nome_loja', $config) ?>" placeholder="Minha Loja" required>
                    </div>
                    <div class="row">
                        <div class="field">
                            <label>Cor principal <span class="obrig">*</span> <span class="hint">(botões, títulos)</span></label>
                            <div class="cor-field">
                                <input type="color" value="<?= val('cor_primaria', $config) ?: '#0a4f9e' ?>" oninput="this.nextElementSibling.value=this.value">
                                <input type="text" name="cor_primaria" value="<?= val('cor_primaria', $config) ?>" placeholder="#0a4f9e" oninput="this.previousElementSibling.value=this.value" required>
                            </div>
                        </div>
                        <div class="field">
                            <label>Cor secundária <span class="obrig">*</span> <span class="hint">(faixa do topo)</span></label>
                            <div class="cor-field">
                                <input type="color" value="<?= val('cor_secundaria', $config) ?: '#ffcc00' ?>" oninput="this.nextElementSibling.value=this.value">
                                <input type="text" name="cor_secundaria" value="<?= val('cor_secundaria', $config) ?>" placeholder="#ffcc00" oninput="this.previousElementSibling.value=this.value" required>
                            </div>
                        </div>
                    </div>
                    <div class="field">
                        <label>URL da logo <span class="hint">(opcional — deixe vazio para mostrar o nome)</span></label>
                        <input type="url" name="logo_url" value="<?= val('logo_url', $config) ?>" placeholder="https://...">
                    </div>
                </div>
            </div>

            <!-- Entrega -->
            <div class="card">
                <div class="card-head">
                    <h2>Entrega</h2>
                    <p>Origem dos envios e prazo padrão da linha do tempo</p>
                </div>
                <div class="card-body">
                    <div class="field">
                        <label>Cidade de origem <span class="obrig">*</span></label>
                        <input type="text" name="cidade_origem" value="<?= val('cidade_origem', $config) ?>" placeholder="Belo Horizonte - MG" required>
                    </div>
                    <div class="row">
                        <div class="field">
                            <label>Prazo mínimo <span class="hint">(dias)</span></label>
                            <input type="number" name="prazo_min_padrao" min="1" value="<?= val('prazo_min_padrao', $config) ?: '5' ?>">
                        </div>
                        <div class="field">
                            <label>Prazo máximo <span class="hint">(dias)</span></label>
                            <input type="number" name="prazo_max_padrao" min="1" value="<?= val('prazo_max_padrao', $config) ?: '7' ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- E-mail -->
            <div class="card">
                <div class="card-head">
                    <h2>E-mail (Resend)</h2>
                    <p>Disparo dos e-mails de confirmação e atualização de status</p>
                </div>
                <div class="card-body">
                    <div class="field">
                        <label>Chave de API do Resend
                            <a href="https://www.youtube.com/watch?v=61pF0S-TTuk" target="_blank" rel="noopener" class="hint" style="text-decoration:underline;">▶ como pegar a chave</a>
                        </label>
                        <input type="text" name="resend_api_key" value="<?= val('resend_api_key', $config) ?>" placeholder="re_..." autocomplete="off">
                    </div>
                    <div class="row">
                        <div class="field">
                            <label>E-mail remetente <span class="hint">(verificado no Resend)</span></label>
                            <input type="email" name="resend_from" value="<?= val('resend_from', $config) ?>" placeholder="no-reply@sualoja.com.br">
                        </div>
                        <div class="field">
                            <label>Nome do remetente <span class="hint">(opcional)</span></label>
                            <input type="text" name="nome_email" value="<?= val('nome_email', $config) ?>" placeholder="usa o nome da loja se vazio">
                        </div>
                    </div>
                    <div class="field">
                        <label>Modelo do e-mail <span class="hint">(visual das mensagens enviadas)</span></label>
                        <?php $tpl_atual = $config['email_template'] ?? 'padrao'; ?>
                        <select name="email_template">
                            <option value="padrao" <?= $tpl_atual !== 'tiktok' ? 'selected' : '' ?>>Padrão (com a marca da sua loja)</option>
                            <option value="tiktok" <?= $tpl_atual === 'tiktok' ? 'selected' : '' ?>>TikTok Shop</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>URL do sistema <span class="hint">(usada no botão "Rastrear" dos e-mails)</span></label>
                        <input type="url" name="site_url" value="<?= val('site_url', $config) ?>" placeholder="https://seusite.com/rastreio">
                    </div>
                </div>
            </div>

            <!-- Recibo -->
            <div class="card">
                <div class="card-head">
                    <h2>Dados do recibo</h2>
                    <p>Aparecem no comprovante em PDF (recibo.php)</p>
                </div>
                <div class="card-body">
                    <div class="field">
                        <label>CNPJ <span class="hint">(opcional)</span></label>
                        <input type="text" name="cnpj" value="<?= val('cnpj', $config) ?>" placeholder="12.345.678/0001-90">
                    </div>
                    <div class="field">
                        <label>Endereço da loja <span class="hint">(opcional, uma linha)</span></label>
                        <input type="text" name="endereco_loja" value="<?= val('endereco_loja', $config) ?>" placeholder="Rua das Flores, 100 — Centro — Belo Horizonte, MG">
                    </div>
                </div>
            </div>

            <!-- Segurança -->
            <div class="card">
                <div class="card-head">
                    <h2>Segurança</h2>
                    <p>Token do cron e senha de acesso a este painel</p>
                </div>
                <div class="card-body">
                    <div class="field">
                        <label>Token do cron <span class="hint">(protege o cron.php e habilita os e-mails de status)</span></label>
                        <div class="input-acao">
                            <input type="text" name="cron_token" id="cron_token" value="<?= val('cron_token', $config) ?>" placeholder="clique em Gerar">
                            <button type="button" class="btn-mini" onclick="gerarToken()">Gerar</button>
                        </div>
                    </div>
                    <div class="field">
                        <label>Nova senha do painel <span class="hint">(deixe vazio para manter a atual)</span></label>
                        <input type="password" name="nova_senha" placeholder="••••••••" autocomplete="new-password">
                    </div>
                </div>
            </div>

            <div class="save-bar">
                <button type="submit" class="btn">Salvar configurações</button>
            </div>
        </form>
    </div>

    <script>
        function gerarToken() {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
            let t = '';
            const arr = new Uint32Array(20);
            crypto.getRandomValues(arr);
            for (let i = 0; i < 20; i++) t += chars[arr[i] % chars.length];
            document.getElementById('cron_token').value = t;
        }
    </script>

<?php endif; ?>

</body>
</html>
