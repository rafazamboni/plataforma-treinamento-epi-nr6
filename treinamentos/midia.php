<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_lib.php';

if (isset($_GET['marca'])) {
    tr_enviar_arquivo(tr_logo_empresa_caminho());
}

require_login();
tr_exigir('treinamentos.apresentacoes.ver');

if (isset($_GET['lib'])) {
    $chave = (string)$_GET['lib'];
    if (!array_key_exists($chave, tr_avatares_biblioteca())) { http_response_code(404); exit; }

    if (isset($_GET['thumb'])) {
        $mini = tr_avatar_biblioteca_miniatura($chave);
        if ($mini === null) { http_response_code(404); exit; }
        tr_enviar_arquivo($mini);
    }

    $arq = tr_avatar_biblioteca_arquivo($chave);
    if ($arq === null) { http_response_code(404); exit; }
    tr_enviar_arquivo(tr_dir_avatares() . '/' . $arq);
}

$apId = (int)($_GET['a'] ?? 0);
$nome = basename((string)($_GET['f'] ?? ''));

$padrao = '/^(logo|fundo-\d+|img-\d{8}-\d{6}-[0-9a-f]{6})\.(png|jpg|jpeg|webp|svg)|^vid-\d{8}-\d{6}-[0-9a-f]{6}\.(mp4|webm)$/i';

if ($apId <= 0 || !preg_match($padrao, $nome)) {
    http_response_code(404);
    exit;
}

tr_enviar_arquivo(tr_dir_midia($apId) . '/' . $nome);
