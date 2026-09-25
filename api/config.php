<?php
// Configurações do gateway Zuck Pay (credenciais nas env vars do projeto na Vercel)
define('ZUCKPAY_CLIENT_ID', getenv('ZUCKPAY_CLIENT_ID') ?: '');
define('ZUCKPAY_CLIENT_SECRET', getenv('ZUCKPAY_CLIENT_SECRET') ?: '');
define('ZUCKPAY_API_URL', 'https://www.zuckpay.com.br/conta/v3/pix');

function zuckpay_auth() {
    return 'Basic ' . base64_encode(ZUCKPAY_CLIENT_ID . ':' . ZUCKPAY_CLIENT_SECRET);
}

ini_set('display_errors', 0);
error_reporting(E_ALL);
?>
