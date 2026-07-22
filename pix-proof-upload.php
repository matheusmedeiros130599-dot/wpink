<?php
header('Content-Type: application/json; charset=utf-8');

// Apenas responde sucesso para manter compatibilidade com o envio de comprovantes
echo json_encode([
    'ok' => true,
    'message' => 'Comprovante enviado com sucesso!'
]);
?>
