<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_once __DIR__ . '/_materiais.php';

require_login();
tr_exigir('treinamentos.apresentacoes.ver');

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);
$ap  = $id > 0 ? tr_apresentacao($pdo, $id) : null;

if (!$ap) {
    flash_set('danger', 'Apresentação não encontrada.');
    nv_redirect(tr_url('index.php'));
}

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
    tr_url('player.php') . '?id=' . $id . '&audio=',
    tr_url('player.php') . '?id=' . $id . '&avatar=1'
);

if (!$trDados['slides']) {
    flash_set('warning', 'Esta apresentação ainda não tem slides.');
    nv_redirect(tr_url('editor.php?id=' . $id));
}

tr_registrar_visualizacao($pdo, $id, 'interno', count($trDados['slides']));

$trModo   = 'interno';
$trPing   = tr_url('player.php') . '?id=' . $id . '&ping=1';
$trVoltar = tr_pode('treinamentos.apresentacoes.editar')
    ? tr_url('editor.php?id=' . $id)
    : tr_url('index.php');

$trTreinamento = [
    'previa'      => true,
    'livre'       => true,
    'api'         => '',
    'capa'        => $trVoltar,
    'prova'       => '',
    'temProva'    => false,
    'provaOk'     => false,
    'podeTentar'  => false,
    'aluno'       => 'Pré-visualização',
    'titulo'      => (string)$ap['titulo'],
    'exigeTermo'  => false,
    'certificado' => false,
    'mensagem'    => '',
    'concluido'   => false,
    'retomarEm'   => 0,
    'concluidos'  => [],
    'total'       => count($trDados['slides']),
];

$trApoio = trmat_payload($pdo, $id, tr_url('player.php') . '?id=' . $id . '&apoio=');

require __DIR__ . '/_player_view.php';
