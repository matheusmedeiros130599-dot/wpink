<?php
/**
 * config.php — Configurações da loja
 *
 * Edite os valores abaixo para personalizar o sistema.
 * Este é o único arquivo que você precisa alterar para colocar o sistema no ar.
 */

$config = [

    // Nome exibido no topo das páginas
    'nome_loja' => 'Minha Loja',

    // Cor principal — azul (botões, título do código, ícones, links, foco)
    'cor_primaria' => '#0a4f9e',

    // Cor secundária — amarelo (faixa do topo e linha da timeline)
    'cor_secundaria' => '#ffcc00',

    // URL da logo da loja. Deixe vazio '' para ocultar
    'logo_url' => '',

    // Cidade de origem dos envios (aparece no primeiro evento da linha do tempo)
    'cidade_origem' => 'São Paulo - SP',

    // Prazo padrão de entrega (em dias) — usado quando a plataforma não envia tr_prazo_min/max
    'prazo_min_padrao' => 5,
    'prazo_max_padrao' => 7,

    // -------------------------------------------------------------------------
    // E-mail transacional via Resend (resend.com)
    // Deixe 'resend_api_key' vazio '' para desabilitar o envio de e-mails
    // -------------------------------------------------------------------------

    // Chave de API do Resend (não commitar em repositórios públicos)
    'resend_api_key' => '',

    // E-mail remetente verificado no Resend
    'resend_from' => '',

    // Nome do remetente exibido no e-mail (cabeçalho, assunto, rodapé)
    // Se deixar vazio '', usa o valor de 'nome_loja' acima
    'nome_email' => '',

    // Modelo visual dos e-mails: 'padrao' (com a marca da sua loja) ou 'tiktok'
    'email_template' => 'padrao',

    // URL base do sistema — usada no botão "Rastrear meu pedido" do e-mail
    // Ex: 'https://seusite.com/rastreio'
    'site_url' => '',

    // Token secreto para proteger o endpoint cron.php
    // Gere uma string aleatória, ex: 'xK9mP2qL8nR4vT6w'
    'cron_token' => '',

    // -------------------------------------------------------------------------
    // Dados do emitente — usados no recibo.php
    // Deixe vazio '' para omitir o campo no recibo
    // -------------------------------------------------------------------------

    // CNPJ da empresa. Ex: '12.345.678/0001-90'
    'cnpj' => '',

    // Endereço completo em uma linha. Ex: 'Rua das Flores, 100 — Centro — Belo Horizonte, MG — CEP 30110-000'
    'endereco_loja' => '',

    // -------------------------------------------------------------------------
    // Painel administrativo (/admin/)
    // -------------------------------------------------------------------------

    // Senha padrão de acesso ao painel. Troque-a no primeiro acesso, pelo próprio
    // painel. A nova senha fica salva com hash em admin/data.json (não em texto).
    'admin_password' => 'admin',

];

// -----------------------------------------------------------------------------
// Sobrescreve os valores acima com o que foi salvo pelo painel (admin/data.json).
// Este arquivo permanece intacto: se o JSON sumir ou corromper, o sistema volta
// automaticamente aos valores padrão definidos acima.
// -----------------------------------------------------------------------------
$config_overrides_file = __DIR__ . '/admin/data.json';
if (is_file($config_overrides_file)) {
    $overrides = json_decode(file_get_contents($config_overrides_file), true);
    if (is_array($overrides)) {
        $config = array_merge($config, $overrides);
    }
}
