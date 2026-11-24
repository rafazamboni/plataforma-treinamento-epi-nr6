<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_once __DIR__ . '/_materiais.php';

$pdo  = db();
$slug = trim((string)($_GET['slug'] ?? ''));
$ap   = $slug !== '' ? tr_apresentacao_por_slug($pdo, $slug) : null;

$liberada = $ap
    && (int)$ap['publico'] === 1
    && $ap['status'] === 'publicado'
    && (($ap['modo_acesso'] ?? 'publico') === 'publico');

if (!$liberada) {
    http_response_code(404);
    $base = tr_base_url();
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <meta name="robots" content="noindex, nofollow">
      <title>Apresentação indisponível</title>
      <link href="/assets/vendor/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
      <link href="<?= h($base) ?>/assets/css/player.css" rel="stylesheet">
    </head>
    <body>
      <div class="nv-aviso-cheio">
        <i class="bi bi-slash-circle"></i>
        <strong>Apresentação indisponível</strong>
        <p>O link não existe ou o compartilhamento foi encerrado. Procure o RH para receber um link atualizado.</p>
      </div>
    </body>
    </html>
    <?php
    exit;
}

$id = (int)$ap['id'];

if (isset($_GET['avatar'])) {
    $caminho = tr_avatar_caminho($ap);
    if ($caminho === null) { http_response_code(404); exit; }
    tr_enviar_arquivo($caminho);
}

if (isset($_GET['audio'])) {
    $dialogoId = (int)$_GET['audio'];
    $st = $pdo->prepare(
        'SELECT d.audio_arquivo FROM treinamentos_dialogos d
           JOIN treinamentos_slides s ON s.id = d.slide_id
          WHERE d.id = ? AND s.apresentacao_id = ? LIMIT 1'
    );
    $st->execute([$dialogoId, $id]);
    $arq = (string)$st->fetchColumn();
    if ($arq === '') { http_response_code(404); exit; }
    tr_enviar_arquivo(tr_dir_midia($id) . '/' . $arq);
}

if (isset($_GET['midia'])) {
    $nome = basename((string)$_GET['midia']);
    if (!preg_match('/^(logo|fundo-\d+)\.(png|jpg|jpeg|webp|svg)$/i', $nome)) { http_response_code(404); exit; }
    tr_enviar_arquivo(tr_dir_midia($id) . '/' . $nome);
}

if (isset($_GET['apoio'])) {
    trmat_entregar_por_id($pdo, $id, (int)$_GET['apoio'], !empty($_GET['baixar']));
}

if (isset($_GET['ping']) && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    tr_progresso_visualizacao($pdo, $id, (int)($_POST['slide'] ?? 1), !empty($_POST['concluido']));
    nv_json(['ok' => true]);
}

$trDados = tr_montar_json(
    $pdo,
    $ap,
    tr_url('ver.php') . '?slug=' . rawurlencode($slug) . '&audio=',
    tr_url('ver.php') . '?slug=' . rawurlencode($slug) . '&avatar=1'
);

$trDados['logo'] = $ap['logo_arquivo']
    ? tr_url('ver.php') . '?slug=' . rawurlencode($slug) . '&midia=' . rawurlencode((string)$ap['logo_arquivo'])
    : '';
foreach ($trDados['slides'] as $i => $s) {
    if (strpos($s['estilo'], 'midia.php') !== false) {
        $trDados['slides'][$i]['estilo'] = preg_replace(
            '#url\\(\'[^\']*midia\\.php\\?a=\\d+&f=([^\']+)\'\\)#',
            "url('" . tr_url('ver.php') . '?slug=' . rawurlencode($slug) . "&midia=$1')",
            $s['estilo']
        );
    }
}

tr_registrar_visualizacao($pdo, $id, 'publico', count($trDados['slides']));

$trModo   = 'publico';
$trPing   = tr_url('ver.php') . '?slug=' . rawurlencode($slug) . '&ping=1';
$trVoltar = '';

$trTreinamento = [
    'previa'      => true,
    'livre'       => true,
    'api'         => '',
    'capa'        => '',
    'prova'       => '',
    'temProva'    => false,
    'provaOk'     => false,
    'podeTentar'  => false,
    'aluno'       => '',
    'titulo'      => (string)$ap['titulo'],
    'exigeTermo'  => false,
    'certificado' => false,
    'mensagem'    => (string)($ap['mensagem_conclusao'] ?? ''),
    'concluido'   => false,
    'retomarEm'   => 0,
    'concluidos'  => [],
    'total'       => count($trDados['slides']),
];

$trApoio = trmat_payload($pdo, $id, tr_url('ver.php') . '?slug=' . rawurlencode($slug) . '&apoio=');

require __DIR__ . '/_player_view.php';
