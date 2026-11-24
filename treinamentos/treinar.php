<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_once __DIR__ . '/_matriculas.php';
require_once __DIR__ . '/_prova.php';
require_once __DIR__ . '/_materiais.php';

$pdo   = db();
$token = trim((string)($_GET['t'] ?? ''));
$insc  = $token !== '' ? trm_inscricao_por_token($pdo, $token) : null;
$ap    = $insc ? tr_apresentacao($pdo, (int)$insc['apresentacao_id']) : null;

function trm_pagina_aviso(string $titulo, string $texto, string $icone = 'slash-circle', int $http = 404): void
{
    http_response_code($http);
    $base = tr_base_url();
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <meta name="robots" content="noindex, nofollow">
      <title><?= h($titulo) ?></title>
      <link href="/assets/vendor/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
      <link href="/assets/vendor/fonts/css.php?f=Inter" rel="stylesheet">
      <link href="<?= h($base) ?>/assets/css/aluno.css" rel="stylesheet">
    </head>
    <body class="tra-body">
      <div class="tra-aviso">
        <i class="bi bi-<?= h($icone) ?>"></i>
        <h1><?= h($titulo) ?></h1>
        <p><?= h($texto) ?></p>
      </div>
    </body>
    </html>
    <?php
    exit;
}

function trm_chave_prova(int $inscricaoId): string
{
    return 'tr_prova_' . $inscricaoId;
}

if (!$insc || !$ap) {
    trm_pagina_aviso(
        'Link inválido',
        'Este endereço não corresponde a nenhuma matrícula. Confira o link recebido ou procure o RH.',
        'link-45deg'
    );
}

$apId = (int)$ap['id'];

if (trm_insc_desligada($pdo, $insc)) {
    if ((string)($_GET['api'] ?? '') !== '') {
        nv_json(['ok' => false, 'msg' => TRM_MSG_DESLIGADO], 403);
    }
    trm_pagina_aviso('Acesso encerrado', TRM_MSG_DESLIGADO, 'person-x', 403);
}

$externo = $insc['status'] === 'concluido'
    && (string)($insc['concluido_origem'] ?? 'plataforma') === 'externo';

if ($insc['status'] !== 'cancelado') {

    if (isset($_GET['avatar'])) {
        $caminho = tr_avatar_caminho($ap);
        if ($caminho === null) { http_response_code(404); exit; }
        tr_enviar_arquivo($caminho);
    }

    if (isset($_GET['audio'])) {
        $st = $pdo->prepare(
            'SELECT d.audio_arquivo FROM treinamentos_dialogos d
               JOIN treinamentos_slides s ON s.id = d.slide_id
              WHERE d.id = ? AND s.apresentacao_id = ? LIMIT 1'
        );
        $st->execute([(int)$_GET['audio'], $apId]);
        $arq = (string)$st->fetchColumn();
        if ($arq === '') { http_response_code(404); exit; }
        tr_enviar_arquivo(tr_dir_midia($apId) . '/' . $arq);
    }

    if (isset($_GET['midia'])) {
        $nome = basename((string)$_GET['midia']);
        $padraoMidiaToken = '/^(logo|fundo-\d+|img-\d{8}-\d{6}-[0-9a-f]{6})\.(png|jpg|jpeg|webp|svg)$|^vid-\d{8}-\d{6}-[0-9a-f]{6}\.(mp4|webm)$/i';
        if (!preg_match($padraoMidiaToken, $nome)) { http_response_code(404); exit; }
        tr_enviar_arquivo(tr_dir_midia($apId) . '/' . $nome);
    }

    if (isset($_GET['apoio'])) {
        trmat_entregar_por_id($pdo, $apId, (int)$_GET['apoio'], !empty($_GET['baixar']));
    }
}

$api = (string)($_GET['api'] ?? '');
if ($api !== '') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        nv_json(['ok' => false, 'msg' => 'Método não permitido.'], 405);
    }

    if ($externo) {
        if ($api === 'concluir') {
            nv_json(['ok' => true, 'codigo' => '', 'certificado' => '', 'capa' => trm_link_token((string)$insc['token'])]);
        }
        if ($api === 'prova') {
            nv_json(['ok' => false, 'msg' => 'Você já realizou este treinamento anteriormente.'], 403);
        }
        nv_json(['ok' => true]);
    }

    $liberado = trm_validar_acesso($insc, $ap);
    if (!$liberado['ok']) { nv_json(['ok' => false, 'msg' => $liberado['msg']], 403); }

    if ($api === 'progresso') {
        $r = trm_salvar_progresso($pdo, $insc, [
            'slide_id'     => (int)($_POST['slide_id'] ?? 0),
            'slide_indice' => (int)($_POST['slide_indice'] ?? 0),
            'dialogo_max'  => (int)($_POST['dialogo_max'] ?? 0),
            'concluido'    => !empty($_POST['concluido']),
            'tempo_seg'    => (int)($_POST['tempo_seg'] ?? 0),
        ]);
        nv_json(['ok' => true] + $r);
    }

    if ($api === 'prova') {
        if (!trp_prova_ativa($ap, $pdo)) {
            nv_json(['ok' => false, 'msg' => 'Este treinamento não tem prova.'], 422);
        }
        if (!trp_pode_tentar($insc, $ap)) {
            nv_json(['ok' => false, 'msg' => 'Você não tem mais tentativas disponíveis. Procure o RH.'], 403);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        $sessao = $_SESSION[trm_chave_prova((int)$insc['id'])] ?? null;
        if (!$sessao || empty($sessao['ids'])) {
            nv_json(['ok' => false, 'msg' => 'A prova expirou. Abra novamente para recomeçar.'], 409);
        }

        $respostas = json_decode((string)($_POST['respostas'] ?? '[]'), true);
        if (!is_array($respostas)) { $respostas = []; }

        $r = trp_corrigir(
            $pdo, $insc, $ap, $respostas,
            (int)($_POST['tempo_seg'] ?? 0),
            $sessao['ids']
        );

        unset($_SESSION[trm_chave_prova((int)$insc['id'])]);

        nv_json(['ok' => true] + $r);
    }

    if ($api === 'concluir') {
        $r = trm_concluir($pdo, $insc, $ap, !empty($_POST['termo']));
        if (!$r['ok']) { nv_json(['ok' => false, 'msg' => $r['msg']], 422); }
        nv_json([
            'ok'          => true,
            'codigo'      => $r['codigo'],
            'certificado' => ((int)$ap['emitir_certificado'] === 1 && $r['codigo'] !== '')
                ? trm_link_certificado($r['codigo']) : '',
            'capa'        => trm_link_token((string)$insc['token']),
        ]);
    }

    nv_json(['ok' => false, 'msg' => 'Ação desconhecida.'], 400);
}

$liberado = trm_validar_acesso($insc, $ap);
if (!$liberado['ok'] && !($externo && ($liberado['motivo'] ?? '') === 'prazo')) {
    $icones = ['cancelado' => 'person-x', 'prazo' => 'calendar-x', 'arquivado' => 'archive', 'rascunho' => 'hourglass-split'];
    trm_pagina_aviso(
        $liberado['motivo'] === 'prazo' ? 'Prazo encerrado' : 'Treinamento indisponível',
        $liberado['msg'],
        $icones[$liberado['motivo']] ?? 'slash-circle',
        403
    );
}

$slidesIds  = trm_slides_ids($pdo, $apId);
$totalSlide = count($slidesIds);

if ($totalSlide === 0) {
    trm_pagina_aviso(
        'Treinamento em preparação',
        'O conteúdo deste treinamento ainda está sendo montado. Você receberá um aviso quando estiver liberado.',
        'tools',
        503
    );
}

$progresso   = trm_progresso($pdo, (int)$insc['id']);
$indicePorId = array_flip($slidesIds);
$concluidos  = [];
foreach ($progresso as $slideId => $p) {
    if ((int)$p['concluido'] === 1 && isset($indicePorId[$slideId])) {
        $concluidos[] = (int)$indicePorId[$slideId];
    }
}
sort($concluidos);
$vistos = count($concluidos);
$pct    = (int)round(($vistos / $totalSlide) * 100);

$retomarEm = 0;
for ($i = 0; $i < $totalSlide; $i++) {
    if (!in_array($i, $concluidos, true)) { $retomarEm = $i; break; }
    $retomarEm = min($i + 1, $totalSlide - 1);
}

$concluido  = $insc['status'] === 'concluido';
$temProva   = trp_prova_ativa($ap, $pdo);
$conteudoOk = $vistos >= $totalSlide;
$provaOk    = (int)$insc['prova_aprovado'] === 1;
$podeTentar = trp_pode_tentar($insc, $ap);
$emCurso    = !$concluido && $vistos > 0;

if ($concluido && !$externo && (int)$ap['permite_revisao'] !== 1 && isset($_GET['iniciar'])) {
    unset($_GET['iniciar']);
}

$baseCapa = tr_url('treinar.php') . '?t=' . rawurlencode($token);

$apoio = trmat_payload($pdo, $apId, $baseCapa . '&apoio=');

if (isset($_GET['prova']) && $temProva) {

    if (!$conteudoOk) {
        trm_pagina_aviso(
            'Conteúdo pendente',
            'Assista a todo o conteúdo do treinamento antes de fazer a prova.',
            'collection-play',
            403
        );
    }

    if ($provaOk || !$podeTentar) {
        trm_pagina_aviso(
            'Prova encerrada',
            $provaOk
                ? 'Você já foi aprovado nesta prova.'
                : 'Você utilizou todas as tentativas disponíveis. Procure o RH para liberar uma nova.',
            'clipboard-check',
            403
        );
    }

    $montada = trp_montar($pdo, $ap);

    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    $_SESSION[trm_chave_prova((int)$insc['id'])] = ['ids' => $montada['ids'], 'em' => time()];

    $tentativaAtual = (int)$insc['prova_tentativas_feitas'] + 1;
    $limite = (int)$ap['prova_tentativas'];
    $base   = tr_base_url();
    $logo   = $ap['logo_arquivo']
        ? $baseCapa . '&midia=' . rawurlencode((string)$ap['logo_arquivo'])
        : (is_file(tr_logo_empresa_caminho()) ? tr_url('midia.php?marca=1') : '');
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Prova — <?= h($ap['titulo']) ?></title>
    <link href="/assets/vendor/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/vendor/fonts/css.php?f=Inter" rel="stylesheet">
    <link href="<?= h($base) ?>/assets/css/aluno.css" rel="stylesheet">
    <style>
      :root{
        --tra-primaria: <?= h($ap['cor_primaria']) ?>;
        --tra-acento:   <?= h($ap['cor_acento']) ?>;
      }
    </style>
    </head>
    <body class="tra-body">

    <div class="tra-capa">

      <header class="tra-capa-topo">
        <?php if ($logo !== ''): ?>
          <img src="<?= h($logo) ?>" alt="" class="tra-logo">
        <?php else: ?>
          <span class="tra-marca"><?= h($ap['marca']) ?></span>
        <?php endif; ?>
        <span class="tra-selo"><i class="bi bi-clipboard-check"></i> Avaliação final</span>
      </header>

      <main class="tra-cartao">

        <div class="tra-cartao-cabecalho">
          <h1>Prova — <?= h($ap['titulo']) ?></h1>
          <p class="tra-sub"><?= h($insc['nome']) ?></p>
        </div>

        <div class="tra-infos">
          <div class="tra-info">
            <i class="bi bi-list-ol"></i>
            <b><?= count($montada['questoes']) ?></b><small>questões</small>
          </div>
          <div class="tra-info">
            <i class="bi bi-bullseye"></i>
            <b><?= (int)$ap['prova_nota_minima'] ?>%</b><small>nota mínima</small>
          </div>
          <div class="tra-info">
            <i class="bi bi-arrow-repeat"></i>
            <b><?= $limite > 0 ? $tentativaAtual . ' / ' . $limite : $tentativaAtual . 'ª' ?></b>
            <small><?= $limite > 0 ? 'tentativa' : 'tentativas ilimitadas' ?></small>
          </div>
        </div>

        <?php if (trim((string)$ap['prova_instrucoes']) !== ''): ?>
          <p class="tra-descricao"><?= nl2br(h($ap['prova_instrucoes'])) ?></p>
        <?php endif; ?>

        <form id="trpForm" class="trp-form">
          <?php foreach ($montada['questoes'] as $n => $q): ?>
            <fieldset class="trp-questao" data-id="<?= (int)$q['id'] ?>" data-tipo="<?= h($q['tipo']) ?>">
              <legend>
                <span class="trp-num"><?= $n + 1 ?></span>
                <span class="trp-enunciado"><?= nl2br(h($q['enunciado'])) ?></span>
              </legend>
              <?php if ($q['tipo'] === 'multipla'): ?>
                <p class="trp-dica"><i class="bi bi-check2-square"></i> Marque todas as alternativas corretas.</p>
              <?php endif; ?>
              <div class="trp-alts">
                <?php foreach ($q['alternativas'] as $a): ?>
                  <label class="trp-alt">
                    <input type="<?= $q['tipo'] === 'multipla' ? 'checkbox' : 'radio' ?>"
                           name="q<?= (int)$q['id'] ?>" value="<?= (int)$a['id'] ?>">
                    <span><?= h($a['texto']) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </fieldset>
          <?php endforeach; ?>
        </form>

        <div class="trp-erro" id="trpErro" hidden></div>

        <div class="tra-acoes">
          <button type="button" class="tra-btn tra-btn-primaria" id="trpEnviar">
            <i class="bi bi-send-check"></i> Enviar respostas
          </button>
          <a class="tra-btn tra-btn-ghost" href="<?= h($baseCapa) ?>">
            <i class="bi bi-arrow-left"></i> Voltar
          </a>
          <span class="tra-retomada" id="trpContagem"></span>
        </div>

      </main>

      <footer class="tra-rodape">
        Nórdica Veículos — RH Digital · As respostas são corrigidas automaticamente ao enviar.
      </footer>
    </div>

    <script>
      window.TRP = {
        api:  <?= json_encode($baseCapa . '&api=', JSON_HEX_TAG) ?>,
        capa: <?= json_encode($baseCapa, JSON_HEX_TAG) ?>,
        exigeTermo: <?= (int)$ap['exige_termo'] === 1 ? 'true' : 'false' ?>,
        certificado: <?= (int)$ap['emitir_certificado'] === 1 ? 'true' : 'false' ?>,
        total: <?= count($montada['questoes']) ?>
      };
    </script>
    <script src="<?= h($base) ?>/assets/js/prova.js"></script>
    </body>
    </html>
    <?php
    exit;
}

if (isset($_GET['iniciar'])) {

    $trDados = tr_montar_json($pdo, $ap, $baseCapa . '&audio=', $baseCapa . '&avatar=1');
    trm_reescrever_midia($trDados, $ap, $baseCapa);

    trm_registrar_acesso($pdo, $insc, $totalSlide);

    $trModo   = 'treinamento';
    $trPing   = '';
    $trVoltar = '';
    $trTreinamento = [
        'api'         => $baseCapa . '&api=',
        'capa'        => $baseCapa,
        'prova'       => $temProva ? ($baseCapa . '&prova=1') : '',
        'temProva'    => $temProva,
        'provaOk'     => $provaOk,
        'podeTentar'  => $podeTentar,
        'aluno'       => (string)$insc['nome'],
        'titulo'      => (string)$ap['titulo'],
        'exigeTermo'  => (int)$ap['exige_termo'] === 1,
        'certificado' => (int)$ap['emitir_certificado'] === 1,
        'mensagem'    => (string)$ap['mensagem_conclusao'],
        'concluido'   => $concluido,
        'retomarEm'   => $concluido ? 0 : $retomarEm,
        'concluidos'  => $concluidos,
        'total'       => $totalSlide,
    ];

    $trApoio = $apoio;

    require __DIR__ . '/_player_view.php';
    exit;
}

$base    = tr_base_url();
$logo    = $ap['logo_arquivo']
    ? $baseCapa . '&midia=' . rawurlencode((string)$ap['logo_arquivo'])
    : (is_file(tr_logo_empresa_caminho()) ? tr_url('midia.php?marca=1') : '');
$linkIni = $baseCapa . '&iniciar=1';
$linkPrv = $baseCapa . '&prova=1';
$carga   = (int)$ap['carga_horaria_min'];
$prazo   = !empty($insc['prazo']) ? date('d/m/Y', strtotime((string)$insc['prazo'])) : '';
$diasRestantes = $prazo !== ''
    ? (int)floor((strtotime((string)$insc['prazo']) - strtotime(date('Y-m-d'))) / 86400)
    : null;

$provaPendente = $temProva && $conteudoOk && !$provaOk && !$concluido;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h($ap['titulo']) ?></title>
<link href="/assets/vendor/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="/assets/vendor/fonts/css.php?f=Inter" rel="stylesheet">
<link href="<?= h($base) ?>/assets/css/aluno.css" rel="stylesheet">
<style>
  :root{
    --tra-primaria: <?= h($ap['cor_primaria']) ?>;
    --tra-acento:   <?= h($ap['cor_acento']) ?>;
  }
</style>
</head>
<body class="tra-body">

<div class="tra-capa">

  <header class="tra-capa-topo">
    <?php if ($logo !== ''): ?>
      <img src="<?= h($logo) ?>" alt="" class="tra-logo">
    <?php else: ?>
      <span class="tra-marca"><?= h($ap['marca']) ?></span>
    <?php endif; ?>
    <span class="tra-selo"><i class="bi bi-mortarboard-fill"></i> Treinamento corporativo</span>
  </header>

  <main class="tra-cartao">

    <div class="tra-cartao-cabecalho">
      <span class="tra-chip tra-chip-<?= $concluido ? 'ok' : ($emCurso ? 'curso' : 'novo') ?>">
        <i class="bi bi-<?= $concluido ? 'patch-check-fill' : ($emCurso ? 'hourglass-split' : 'play-circle') ?>"></i>
        <?= $externo ? 'Já realizado anteriormente' : ($concluido ? 'Concluído' : ($emCurso ? 'Em andamento' : 'Não iniciado')) ?>
      </span>
      <h1><?= h($ap['titulo']) ?></h1>
      <?php if (($ap['subtitulo'] ?? '') !== ''): ?>
        <p class="tra-sub"><?= h($ap['subtitulo']) ?></p>
      <?php endif; ?>
    </div>

    <div class="tra-aluno">
      <div class="tra-avatar"><?= h(mb_strtoupper(mb_substr((string)$insc['nome'], 0, 1))) ?></div>
      <div>
        <strong><?= h($insc['nome']) ?></strong>
        <small>
          <?= h(trim(implode(' · ', array_filter([
                (string)$insc['cargo'],
                (string)$insc['filial'],
                trm_cpf_mascarado((string)$insc['cpf']),
              ])))) ?>
        </small>
      </div>
    </div>

    <?php if (trim((string)($ap['descricao'] ?? '')) !== ''): ?>
      <p class="tra-descricao"><?= nl2br(h($ap['descricao'])) ?></p>
    <?php endif; ?>

    <div class="tra-infos">
      <div class="tra-info">
        <i class="bi bi-collection-play"></i>
        <b><?= $totalSlide ?></b><small>etapas</small>
      </div>
      <?php if ($carga > 0): ?>
        <div class="tra-info">
          <i class="bi bi-clock-history"></i>
          <b><?= $carga ?> min</b><small>duração estimada</small>
        </div>
      <?php endif; ?>
      <?php if ($temProva): ?>
        <div class="tra-info">
          <i class="bi bi-clipboard-check"></i>
          <b><?= (int)$ap['prova_nota_minima'] ?>%</b><small>nota mínima na prova</small>
        </div>
      <?php endif; ?>
      <?php if ($prazo !== '' && !$externo): ?>
        <div class="tra-info <?= ($diasRestantes !== null && $diasRestantes <= 3 && !$concluido) ? 'is-alerta' : '' ?>">
          <i class="bi bi-calendar-event"></i>
          <b><?= h($prazo) ?></b>
          <small><?= $concluido ? 'prazo' : (($diasRestantes !== null && $diasRestantes >= 0) ? 'faltam ' . $diasRestantes . ' dia(s)' : 'prazo') ?></small>
        </div>
      <?php endif; ?>
      <?php if ((int)$insc['tempo_seg'] > 0): ?>
        <div class="tra-info">
          <i class="bi bi-stopwatch"></i>
          <b><?= h(trm_tempo_fmt((int)$insc['tempo_seg'])) ?></b><small>tempo dedicado</small>
        </div>
      <?php endif; ?>
    </div>

    <?php if (!$externo): ?>
    <div class="tra-progresso">
      <div class="tra-progresso-topo">
        <span>Seu progresso no conteúdo</span>
        <b><?= $pct ?>%</b>
      </div>
      <div class="tra-barra"><i style="width:<?= $pct ?>%"></i></div>
      <small><?= $vistos ?> de <?= $totalSlide ?> etapas concluídas</small>
    </div>
    <?php endif; ?>

    <?php if ($temProva && !$externo): ?>
      <ol class="tra-percurso">
        <li class="<?= $conteudoOk ? 'is-ok' : 'is-atual' ?>">
          <i class="bi bi-<?= $conteudoOk ? 'check-circle-fill' : 'play-circle' ?>"></i>
          <div>
            <strong>Assistir ao conteúdo</strong>
            <span><?= $vistos ?> de <?= $totalSlide ?> etapas</span>
          </div>
        </li>
        <li class="<?= $provaOk ? 'is-ok' : ($conteudoOk ? 'is-atual' : '') ?>">
          <i class="bi bi-<?= $provaOk ? 'check-circle-fill' : 'clipboard-check' ?>"></i>
          <div>
            <strong>Prova final</strong>
            <span>
              <?php if ($provaOk): ?>
                Aprovado com <?= (int)$insc['prova_percentual'] ?>%
              <?php elseif ((int)$insc['prova_tentativas_feitas'] > 0): ?>
                Melhor nota <?= (int)$insc['prova_percentual'] ?>% ·
                <?= (int)$insc['prova_tentativas_feitas'] ?> tentativa(s) usada(s)
              <?php else: ?>
                Nota mínima de <?= (int)$ap['prova_nota_minima'] ?>%
              <?php endif; ?>
            </span>
          </div>
        </li>
        <li class="<?= $concluido ? 'is-ok' : (($conteudoOk && $provaOk) ? 'is-atual' : '') ?>">
          <i class="bi bi-<?= $concluido ? 'check-circle-fill' : 'award' ?>"></i>
          <div>
            <strong>Conclusão</strong>
            <span><?= $concluido ? 'Registrada' : 'Liberada após a aprovação' ?></span>
          </div>
        </li>
      </ol>
    <?php endif; ?>

    <?php if ($externo): ?>
      <div class="tra-conclusao">
        <i class="bi bi-patch-check-fill"></i>
        <div>
          <strong>Você já realizou este treinamento anteriormente</strong>
          <span>
            Este treinamento foi concluído em outra plataforma e o certificado está registrado no RH. Nenhuma ação é necessária.
          </span>
          <span>Este link fica disponível para que você possa refazer o treinamento sempre que necessário.</span>
        </div>
      </div>
    <?php elseif ($concluido): ?>
      <div class="tra-conclusao">
        <i class="bi bi-patch-check-fill"></i>
        <div>
          <strong>Treinamento concluído</strong>
          <span>Registrado em <?= h(date('d/m/Y \à\s H:i', strtotime((string)$insc['concluido_em']))) ?>.</span>
          <?php if ($temProva): ?>
            <span>Aproveitamento na prova: <?= (int)$insc['prova_percentual'] ?>%.</span>
          <?php endif; ?>
          <?php if (trim((string)$ap['mensagem_conclusao']) !== ''): ?>
            <span class="tra-msg-final"><?= nl2br(h($ap['mensagem_conclusao'])) ?></span>
          <?php endif; ?>
        </div>
      </div>
    <?php elseif ($temProva && $conteudoOk && !$provaOk && !$podeTentar): ?>
      <div class="tra-conclusao is-alerta">
        <i class="bi bi-exclamation-octagon-fill"></i>
        <div>
          <strong>Tentativas esgotadas</strong>
          <span>Você utilizou todas as tentativas da prova. Procure o RH para liberar uma nova avaliação.</span>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($apoio['materiais']): ?>
      <div class="tra-apoio">
        <div class="tra-apoio-topo">
          <i class="bi bi-paperclip"></i>
          <span>Material de apoio</span>
          <em><?= count($apoio['materiais']) ?></em>
        </div>
        <?php foreach ($apoio['materiais'] as $m): ?>
          <a class="tra-apoio-item<?= $m['destaque'] ? ' is-destaque' : '' ?>"
             href="<?= h($m['url']) ?>" target="_blank" rel="noopener">
            <span class="tra-apoio-ic"><i class="bi bi-<?= h($m['icone']) ?>"></i></span>
            <span class="tra-apoio-corpo">
              <b><?= h($m['titulo']) ?></b>
              <?php if ($m['descricao'] !== ''): ?>
                <small><?= h($m['descricao']) ?></small>
              <?php endif; ?>
              <em><?= h(trim($m['formato'] . ($m['tamanho'] !== '' ? ' · ' . $m['tamanho'] : ''))) ?></em>
            </span>
            <i class="bi bi-box-arrow-up-right tra-apoio-seta"></i>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($apoio['referencias']): ?>
      <details class="tra-refs">
        <summary>
          <i class="bi bi-journal-bookmark"></i>
          <span>Referências</span>
          <em><?= count($apoio['referencias']) ?></em>
          <i class="bi bi-chevron-down tra-refs-seta"></i>
        </summary>
        <ol>
          <?php foreach ($apoio['referencias'] as $r): ?>
            <li>
              <?php if ($r['url'] !== ''): ?>
                <a href="<?= h($r['url']) ?>" target="_blank" rel="noopener">
                  <?= h($r['texto']) ?> <i class="bi bi-box-arrow-up-right"></i>
                </a>
              <?php else: ?>
                <?= h($r['texto']) ?>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      </details>
    <?php endif; ?>

    <div class="tra-acoes">
      <?php if ($concluido): ?>
        <?php if ((int)$ap['emitir_certificado'] === 1 && (string)$insc['certificado_codigo'] !== ''): ?>
          <a class="tra-btn tra-btn-primaria" href="<?= h(trm_link_certificado((string)$insc['certificado_codigo'])) ?>" target="_blank">
            <i class="bi bi-award"></i> Ver certificado
          </a>
        <?php endif; ?>
        <?php if ($externo || (int)$ap['permite_revisao'] === 1): ?>
          <a class="tra-btn <?= $externo ? 'tra-btn-primaria' : 'tra-btn-ghost' ?>" href="<?= h($linkIni) ?>">
            <i class="bi bi-arrow-repeat"></i> <?= $externo ? 'Refazer o treinamento' : 'Rever o conteúdo' ?>
          </a>
        <?php endif; ?>

      <?php elseif ($provaPendente && $podeTentar): ?>
        <a class="tra-btn tra-btn-primaria" href="<?= h($linkPrv) ?>">
          <i class="bi bi-clipboard-check"></i>
          <?= (int)$insc['prova_tentativas_feitas'] > 0 ? 'Refazer a prova' : 'Fazer a prova final' ?>
        </a>
        <a class="tra-btn tra-btn-ghost" href="<?= h($linkIni) ?>">
          <i class="bi bi-arrow-repeat"></i> Revisar o conteúdo antes
        </a>

      <?php else: ?>
        <a class="tra-btn tra-btn-primaria" href="<?= h($linkIni) ?>">
          <i class="bi bi-<?= $emCurso ? 'play-circle-fill' : 'play-fill' ?>"></i>
          <?= $emCurso ? 'Continuar de onde parei' : 'Iniciar treinamento' ?>
        </a>
        <?php if ($emCurso): ?>
          <span class="tra-retomada">
            <i class="bi bi-bookmark-check"></i>
            Você parou na etapa <?= $retomarEm + 1 ?> de <?= $totalSlide ?>
          </span>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <ul class="tra-orientacoes">
      <li><i class="bi bi-headphones"></i> Use fones de ouvido ou deixe o som do dispositivo ligado, o treinamento é narrado.</li>
      <li><i class="bi bi-bookmark-check"></i> Pode fechar e voltar quando quiser, seu progresso fica salvo.</li>
      <?php if ($apoio['materiais']): ?>
        <li><i class="bi bi-paperclip"></i> Este treinamento tem material de apoio. Dentro do
          treinamento, o ícone de clipe no canto superior direito abre os documentos a
          qualquer momento, sem perder o seu progresso.</li>
      <?php endif; ?>
      <?php if ($temProva): ?>
        <li><i class="bi bi-clipboard-check"></i> Ao final há uma prova. É preciso atingir
          <?= (int)$ap['prova_nota_minima'] ?>% de acertos para concluir o treinamento<?php
          if ((int)$ap['prova_tentativas'] > 0) { echo ', em até ' . (int)$ap['prova_tentativas'] . ' tentativa(s)'; }
          ?>.</li>
      <?php endif; ?>
      <li><i class="bi bi-person-lock"></i> Este link é pessoal e intransferível. Não o repasse a outras pessoas.</li>
    </ul>

  </main>

  <footer class="tra-rodape">
    Nórdica Veículos — RH Digital · Em caso de dúvida, procure o setor de Recursos Humanos.
  </footer>
</div>

</body>
</html>
