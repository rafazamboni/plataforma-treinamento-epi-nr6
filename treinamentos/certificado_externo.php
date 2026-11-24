<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_once __DIR__ . '/_matriculas.php';

require_login();
if (!trm_pode_ver()) { http_response_code(403); exit; }

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);
$insc = $id > 0 ? trm_inscricao($pdo, $id) : null;

$arquivo = $insc ? (string)($insc['certificado_externo_arquivo'] ?? '') : '';
if (!$insc || $arquivo === '') { http_response_code(404); exit; }

$caminho = tr_dir_midia((int)$insc['apresentacao_id']) . '/' . basename($arquivo);
tr_enviar_arquivo($caminho);
