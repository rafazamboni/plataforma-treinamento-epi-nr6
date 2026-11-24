<?php
if (!isset($trDados) || !is_array($trDados)) {
    http_response_code(500);
    exit;
}
$trModo   = $trModo   ?? 'interno';
$trPing   = $trPing   ?? '';
$trVoltar = $trVoltar ?? '';

$trApoio = (isset($trApoio) && is_array($trApoio))
    ? $trApoio
    : ['materiais' => [], 'referencias' => []];
$trApoio['materiais']   = $trApoio['materiais']   ?? [];
$trApoio['referencias'] = $trApoio['referencias'] ?? [];
$cores    = $trDados['cores'];
$base     = tr_base_url();

$trLogo = (string)($trDados['logo'] ?? '');
if ($trLogo === '' && is_file(tr_logo_empresa_caminho())) {
    $trLogo = tr_url('midia.php?marca=1');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#060b13">
<meta name="robots" content="noindex, nofollow">
<title><?= h($trDados['titulo']) ?></title>

<link href="/assets/vendor/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/assets/vendor/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="/assets/vendor/fonts/css.php?f=Inter" rel="stylesheet">
<link href="<?= h(tr_url_v('assets/css/blocos.css')) ?>" rel="stylesheet">
<link href="<?= h(tr_url_v('assets/css/player.css')) ?>" rel="stylesheet">
<?php if (!empty($trTreinamento)): ?>
<link href="<?= h(tr_url_v('assets/css/abertura.css')) ?>" rel="stylesheet">
<link href="<?= h(tr_url_v('assets/css/treinamento.css')) ?>" rel="stylesheet">
<link href="<?= h(tr_url_v('assets/css/etapa.css')) ?>" rel="stylesheet">
<link href="<?= h(tr_url_v('assets/css/orientacao.css')) ?>" rel="stylesheet">
<?php endif; ?>
<link href="<?= h(tr_url_v('assets/css/apoio.css')) ?>" rel="stylesheet">
<link href="<?= h(tr_url_v('assets/css/mobile.css')) ?>" rel="stylesheet">
<style>
  :root{
    --nv-primaria: <?= h($cores['primaria']) ?>;
    --nv-acento:   <?= h($cores['acento']) ?>;
    --nv-fundo:    <?= h($cores['fundo']) ?>;
  }
</style>

<script type="importmap">
{
  "imports": {
    "three": "/assets/vendor/three@0.180.0/build/three.module.js",
    "three/addons/": "/assets/vendor/three@0.180.0/examples/jsm/",
    "@pixiv/three-vrm": "/assets/vendor/three-vrm@3.5.5/lib/three-vrm.module.min.js"
  }
}
</script>
</head>
<body class="tema-<?= h($trDados['tema']) ?> nv-cine">

<?php if (!empty($trTreinamento)): ?>
<div class="tr-abertura" id="trAbertura" role="presentation" aria-hidden="true">
  <div class="tr-abertura-fundo"></div>
  <div class="tr-abertura-varredura"></div>
  <div class="tr-abertura-conteudo">
    <span class="tr-abertura-selo">Treinamento</span>
    <div class="tr-abertura-marca">
      <span class="tr-abertura-colchete tr-abertura-colchete-esq"></span>
      <h1 class="tr-abertura-titulo"><span>RH</span><span class="tr-abertura-titulo-fino">DIGITAL</span></h1>
      <span class="tr-abertura-colchete tr-abertura-colchete-dir"></span>
    </div>
    <div class="tr-abertura-linha"></div>
    <p class="tr-abertura-rodape">Nórdica Veículos</p>
  </div>
</div>
<script src="<?= h(tr_url_v('assets/js/abertura.js')) ?>"></script>
<?php endif; ?>

<div class="nv-app" id="nvApp">

  <header class="nv-topbar">
    <div class="nv-brand">
      <span class="nv-brand-text">
        <strong id="nvTituloApres"><?= h($trDados['titulo']) ?></strong>
        <small id="nvSubtituloApres"><?= h($trDados['subtitulo']) ?></small>
      </span>
    </div>

    <div class="nv-topbar-actions">
      <?php if ($trApoio['materiais']): ?>
        <button type="button" class="nv-icon-btn nva-btn" id="btnApoio"
                title="Material de apoio deste treinamento">
          <i class="bi bi-paperclip"></i>
          <span class="nva-selo"><?= count($trApoio['materiais']) ?></span>
        </button>
      <?php endif; ?>
      <button type="button" class="nv-icon-btn" id="btnRoteiro" title="Roteiro do slide">
        <i class="bi bi-list-ul"></i>
      </button>
      <button type="button" class="nv-icon-btn" id="btnTela" title="Tela cheia">
        <i class="bi bi-arrows-fullscreen"></i>
      </button>
    </div>
  </header>

  <main class="nv-stage" id="nvStage">

    <div class="nv-palco-col">
      <div class="nv-slide-wrap" id="nvSlideWrap">

        <aside class="nv-quadro" id="nvQuadro" aria-live="polite">
          <div class="nv-quadro-head">
            <i class="bi bi-lightbulb"></i>
            <span id="nvQuadroTitulo">Quadro explicativo</span>
            <button type="button" class="nv-quadro-close" id="nvQuadroClose" title="Fechar">
              <i class="bi bi-x-lg"></i>
            </button>
          </div>
          <div class="nv-quadro-body" id="nvQuadroBody"></div>
        </aside>

        <div class="nv-foco" id="nvFoco" aria-hidden="true">
          <div class="nv-foco-ring" id="nvFocoRing"></div>
          <div class="nv-foco-label" id="nvFocoLabel"></div>
        </div>

        <?php if ($trLogo !== ''): ?>
          <div class="nv-marca-dagua" aria-hidden="true">
            <img src="<?= h($trLogo) ?>" alt="">
          </div>
        <?php endif; ?>

        <div class="nv-instrutor" id="nvInstrutor" data-pos="direita">
          <div class="nv-balao" id="nvBalao"><span id="nvBalaoTexto"></span></div>
          <div class="nv-instrutor-palco">
            <div class="nv-instrutor-3d" id="nvInstrutor3d"></div>
            <div class="nv-instrutor-carregando" id="nvInstrutorCarregando" hidden>
              <span class="anel"></span>
              <span id="nvInstrutorCarregandoTxt">Preparando instrutor…</span>
            </div>
          </div>
          <span class="nv-instrutor-nome"><?= h($trDados['avatar']['nome']) ?></span>
        </div>

        <section class="nv-legenda" id="nvLegenda">
          <span class="nv-legenda-corpo">
            <p class="nv-legenda-texto" id="nvLegendaTexto">Pressione <kbd>Iniciar</kbd> para começar.</p>
          </span>
          <span class="nv-legenda-meta" hidden>
            <span id="nvLegendaNome" hidden></span>
            <span id="nvDialogoContador">—</span>
          </span>
        </section>

      </div>
    </div>

  </main>

  <footer class="nv-controles">
    <div class="nv-controles-linha">

      <div class="nv-controles-esq">
        <button type="button" class="nv-icon-btn" id="btnAnterior" title="Slide anterior">
          <i class="bi bi-skip-start-fill"></i>
        </button>
        <button type="button" class="nv-btn-play" id="btnPlay" title="Reproduzir">
          <i class="bi bi-play-fill"></i>
          <span class="nv-sr" id="btnPlayTxt">Iniciar</span>
        </button>
        <button type="button" class="nv-icon-btn" id="btnProximo" title="Próximo slide">
          <i class="bi bi-skip-end-fill"></i>
        </button>

        <label class="nv-auto" title="Passa para o próximo slide ao terminar as falas">
          <input type="checkbox" id="chkAuto" <?= !empty($trDados['avancoAuto']) ? 'checked' : '' ?>>
          <span>Automático</span>
        </label>

        <span class="nv-div"></span>

        <button type="button" class="nv-icon-btn" id="btnRepetir" title="Repetir esta fala">
          <i class="bi bi-arrow-counterclockwise"></i>
        </button>
        <button type="button" class="nv-icon-btn" id="btnPular" title="Próxima fala">
          <i class="bi bi-fast-forward-fill"></i>
        </button>

        <div class="nv-volume" id="nvVolume">
          <button type="button" class="nv-icon-btn" id="btnMudo" title="Volume">
            <i class="bi bi-volume-up-fill"></i>
          </button>
          <div class="nv-volume-menu" id="nvVolumeMenu" hidden>
            <input type="range" id="cfgVolume" min="0" max="100" step="5" value="100"
                   aria-label="Volume da narração">
            <span id="cfgVolumeVal">100%</span>
          </div>
        </div>
      </div>

      <div class="nv-controles-centro">
        <span class="nv-posicao"><b id="nvContador">0 / 0</b></span>
        <div class="nv-scrub" id="nvScrub" role="group" aria-label="Falas deste slide"></div>
      </div>

      <div class="nv-controles-dir">
        <div class="nv-veloc" id="nvVeloc">
          <button type="button" class="nv-icon-btn nv-veloc-btn" id="btnVeloc" title="Velocidade da narração">
            <span id="btnVelocTxt">1×</span>
          </button>
          <div class="nv-veloc-menu" id="nvVelocMenu" hidden>
            <button type="button" data-veloc="0.75">0,75×</button>
            <button type="button" data-veloc="0.9">0,9×</button>
            <button type="button" data-veloc="1">Normal</button>
            <button type="button" data-veloc="1.15">1,15×</button>
            <button type="button" data-veloc="1.3">1,3×</button>
            <button type="button" data-veloc="1.5">1,5×</button>
          </div>
        </div>

        <button type="button" class="nv-icon-btn" id="btnLegenda" title="Legenda">
          <i class="bi bi-badge-cc-fill"></i>
        </button>
        <button type="button" class="nv-icon-btn" id="btnInstrutor" title="Instrutor">
          <i class="bi bi-person-fill"></i>
        </button>

      </div>
    </div>
  </footer>
</div>

<div class="nv-drawer" id="drawerRoteiro" aria-hidden="true">
  <div class="nv-drawer-head">
    <span><i class="bi bi-list-ul"></i> Roteiro</span>
    <button type="button" class="nv-icon-btn" data-drawer-close="drawerRoteiro"><i class="bi bi-x-lg"></i></button>
  </div>
  <div class="nv-drawer-body">
    <div id="nvRoteiroLista"></div>
    <div class="nvr-bloco" id="nvReferencias" hidden></div>
  </div>
</div>

<div class="nv-drawer" id="drawerApoio" aria-hidden="true">
  <div class="nv-drawer-head">
    <span><i class="bi bi-paperclip"></i> Material de apoio</span>
    <button type="button" class="nv-icon-btn" data-drawer-close="drawerApoio"><i class="bi bi-x-lg"></i></button>
  </div>
  <div class="nv-drawer-body" id="nvApoioLista"></div>
</div>

<div class="nv-backdrop" id="nvBackdrop"></div>
<div class="nv-toast-area" id="nvToastArea"></div>

<script>
  window.TR_APRESENTACAO = <?= json_encode($trDados, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window.TR_PLAYER_OPT = {
    modo: <?= json_encode($trModo, JSON_HEX_TAG) ?>,
    ping: <?= json_encode($trPing, JSON_HEX_TAG) ?>,
    treinamento: <?= json_encode($trTreinamento ?? null, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>
  };
  window.TR_APOIO = <?= json_encode($trApoio, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= h(tr_url_v('assets/js/blocos.js')) ?>"></script>
<script src="<?= h(tr_url_v('assets/js/canvas-visual.js')) ?>"></script>
<script src="<?= h(tr_url_v('assets/js/instrutor-vrm.js')) ?>"></script>
<script src="<?= h(tr_url_v('assets/js/fala.js')) ?>"></script>
<script src="<?= h(tr_url_v('assets/js/dialogo.js')) ?>"></script>
<script src="<?= h(tr_url_v('assets/js/player.js')) ?>"></script>
<script src="<?= h(tr_url_v('assets/js/boot.js')) ?>"></script>
<script src="<?= h(tr_url_v('assets/js/legenda.js')) ?>"></script>
<?php if (!empty($trTreinamento)): ?>
<script src="<?= h(tr_url_v('assets/js/etapa.js')) ?>"></script>
<script src="<?= h(tr_url_v('assets/js/treinamento.js')) ?>"></script>
<script src="<?= h(tr_url_v('assets/js/guia.js')) ?>"></script>
<script src="<?= h(tr_url_v('assets/js/orientacao.js')) ?>"></script>
<?php endif; ?>
<script src="<?= h(tr_url_v('assets/js/apoio.js')) ?>"></script>
<script src="<?= h(tr_url_v('assets/js/tela-giro.js')) ?>"></script>
</body>
</html>
