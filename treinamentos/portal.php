<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_once __DIR__ . '/_matriculas.php';
require_once __DIR__ . '/_portal.php';

$pdo = db();
trpo_sessao_iniciar();

$destino = tr_url('portal.php');

if ((string)($_GET['acao'] ?? '') === 'sair') {
    trpo_sair();
    header('Location: ' . $destino . '?saiu=1');
    exit;
}

$erro = '';
$cpfDigitado = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();

    $cpfDigitado = trim((string)($_POST['cpf'] ?? ''));

    if (trpo_bloqueado()) {
        $erro = 'Muitas tentativas sem sucesso. Aguarde alguns minutos antes de tentar novamente.';
    } else {
        $r = trpo_autenticar($pdo, $cpfDigitado, (string)($_POST['nascimento'] ?? ''));
        if ($r['ok']) {
            trpo_entrar($r['colab']);
            header('Location: ' . $destino);
            exit;
        }
        $erro = $r['msg'];
    }
}

$colab = trpo_colaborador($pdo);

$logo = is_file(tr_logo_empresa_caminho()) ? tr_url('midia.php?marca=1') : '';

if (!$colab):
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Portal de Treinamentos — Nórdica Veículos</title>
<link href="/assets/vendor/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="/assets/vendor/fonts/css.php?f=Inter" rel="stylesheet">
<link href="<?= h(tr_url('assets/css/aluno.css')) ?>" rel="stylesheet">
<link href="<?= h(tr_url('assets/css/portal.css')) ?>" rel="stylesheet">
</head>
<body class="tra-body trpo-body">

<div class="trpo-acesso">
  <div class="trpo-acesso-caixa">

    <header class="trpo-acesso-topo">
      <?php if ($logo !== ''): ?>
        <img src="<?= h($logo) ?>" alt="Nórdica Veículos">
      <?php else: ?>
        <span class="tra-marca">NÓRDICA VEÍCULOS</span>
      <?php endif; ?>
      <span class="tra-selo"><i class="bi bi-mortarboard-fill"></i> Portal de treinamentos</span>
    </header>

    <div class="trpo-acesso-corpo">
      <h1>Acesse seus treinamentos</h1>
      <p class="trpo-acesso-sub">
        Consulte os treinamentos em que você está matriculado, retome de onde
        parou, acompanhe o seu progresso e baixe os certificados.
      </p>

      <?php if (!empty($_GET['saiu'])): ?>
        <div class="trpo-msg is-ok">
          <i class="bi bi-check-circle"></i>
          <span>Sessão encerrada. Até a próxima!</span>
        </div>
      <?php endif; ?>

      <?php if ($erro !== ''): ?>
        <div class="trpo-msg is-erro">
          <i class="bi bi-exclamation-triangle"></i>
          <span><?= h($erro) ?></span>
        </div>
      <?php endif; ?>

      <form method="post" class="trpo-form" autocomplete="off" novalidate>
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

        <div class="trpo-campo">
          <label for="poCpf">CPF</label>
          <div class="trpo-input">
            <i class="bi bi-person-vcard"></i>
            <input type="text" id="poCpf" name="cpf" inputmode="numeric" maxlength="14"
                   placeholder="000.000.000-00" value="<?= h($cpfDigitado) ?>" required>
          </div>
        </div>

        <div class="trpo-campo">
          <label for="poNasc">Data de nascimento</label>
          <div class="trpo-input">
            <i class="bi bi-calendar-event"></i>
            <input type="date" id="poNasc" name="nascimento"
                   min="1920-01-01" max="<?= h(date('Y-m-d')) ?>" required>
          </div>
        </div>

        <button type="submit" class="tra-btn tra-btn-primaria trpo-entrar">
          <i class="bi bi-box-arrow-in-right"></i> Entrar
        </button>
      </form>

      <ul class="tra-orientacoes trpo-dicas">
        <li><i class="bi bi-shield-lock"></i> O acesso é pessoal. Use o seu CPF e a data de nascimento.</li>
      </ul>
    </div>
  </div>

  <footer class="tra-rodape">
    RH Digital - Nórdica Veículos
  </footer>
</div>

<script src="<?= h(tr_url('assets/js/portal.js')) ?>"></script>
</body>
</html>
<?php
exit;
endif;

$inscricoes = trpo_inscricoes($pdo, $colab);
$resumo     = trpo_resumo($inscricoes);

$certificados = array_values(array_filter($inscricoes, static function ($i) {
    return $i['calc']['concluido'] && $i['calc']['cert'] !== '';
}));

$primeiroNome = trim(explode(' ', trim((string)$colab['nome']))[0] ?? '');
$hora = (int)date('G');
$saudacao = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Meus treinamentos — Nórdica Veículos</title>
<link href="/assets/vendor/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="/assets/vendor/fonts/css.php?f=Inter" rel="stylesheet">
<link href="<?= h(tr_url('assets/css/aluno.css')) ?>" rel="stylesheet">
<link href="<?= h(tr_url('assets/css/portal.css')) ?>" rel="stylesheet">
</head>
<body class="tra-body trpo-body">

<header class="trpo-topo">
  <div class="trpo-topo-in">
    <?php if ($logo !== ''): ?>
      <img class="trpo-logo" src="<?= h($logo) ?>" alt="Nórdica Veículos">
    <?php else: ?>
      <span class="tra-marca">NÓRDICA VEÍCULOS</span>
    <?php endif; ?>

    <span class="trpo-topo-titulo">Portal de treinamentos</span>

    <div class="trpo-topo-user">
      <span class="trpo-avatar"><?= h(mb_strtoupper(mb_substr((string)$colab['nome'], 0, 1))) ?></span>
      <span class="trpo-topo-nome">
        <b><?= h($colab['nome']) ?></b>
        <small><?= h(trim(implode(' · ', array_filter([
          (string)($colab['cargo'] ?? ''),
          (string)($colab['filial'] ?? ''),
        ])))) ?></small>
      </span>
      <a class="trpo-sair" href="<?= h($destino) ?>?acao=sair" title="Sair do portal">
        <i class="bi bi-box-arrow-right"></i><span>Sair</span>
      </a>
    </div>
  </div>
</header>

<main class="trpo-main">

  <section class="trpo-hero">
    <div class="trpo-hero-txt">
      <h1><?= h($saudacao) ?>, <?= h($primeiroNome) ?>.</h1>
      <p>
        <?php if ($resumo['total'] === 0): ?>
          Você ainda não tem treinamentos atribuídos.
        <?php elseif ($resumo['concluidos'] >= $resumo['total']): ?>
          Todos os seus treinamentos estão em dia. Bom trabalho!
        <?php else: ?>
          Você tem <b><?= $resumo['total'] - $resumo['concluidos'] ?></b> treinamento(s) a concluir
          <?php if ($resumo['atrasados'] > 0): ?>
            — sendo <b><?= $resumo['atrasados'] ?></b> com prazo vencido.
          <?php else: ?>.<?php endif; ?>
        <?php endif; ?>
      </p>
    </div>

    <div class="trpo-anel" style="--pct:<?= (int)$resumo['pct'] ?>">
      <b><?= (int)$resumo['pct'] ?>%</b>
      <small>concluído</small>
    </div>
  </section>

  <section class="trpo-kpis">
    <div class="trpo-kpi">
      <i class="bi bi-collection"></i>
      <b><?= (int)$resumo['total'] ?></b><small>Matrículas</small>
    </div>
    <div class="trpo-kpi<?= $resumo['andamento'] > 0 ? ' is-curso' : '' ?>">
      <i class="bi bi-hourglass-split"></i>
      <b><?= (int)$resumo['andamento'] ?></b><small>Em andamento</small>
    </div>
    <div class="trpo-kpi">
      <i class="bi bi-play-circle"></i>
      <b><?= (int)$resumo['pendentes'] ?></b><small>Não iniciados</small>
    </div>
    <div class="trpo-kpi is-ok">
      <i class="bi bi-patch-check-fill"></i>
      <b><?= (int)$resumo['concluidos'] ?></b><small>Concluídos</small>
    </div>
    <?php if ($resumo['atrasados'] > 0): ?>
      <div class="trpo-kpi is-alerta">
        <i class="bi bi-calendar-x"></i>
        <b><?= (int)$resumo['atrasados'] ?></b><small>Prazo vencido</small>
      </div>
    <?php endif; ?>
    <div class="trpo-kpi">
      <i class="bi bi-stopwatch"></i>
      <b><?= h(trm_tempo_fmt((int)$resumo['tempo'])) ?></b><small>Tempo dedicado</small>
    </div>
  </section>

  <?php if (!$inscricoes): ?>
    <section class="trpo-vazio">
      <i class="bi bi-inbox"></i>
      <h2>Nenhum treinamento por aqui</h2>
      <p>Assim que o RH matricular você em um treinamento, ele aparece nesta tela.</p>
    </section>
  <?php else: ?>

    <section class="trpo-secao">
      <div class="trpo-secao-topo">
        <h2><i class="bi bi-mortarboard"></i> Meus treinamentos</h2>
        <div class="trpo-filtros" id="poFiltros">
          <button type="button" class="trpo-filtro is-ativo" data-f="todos">Todos</button>
          <button type="button" class="trpo-filtro" data-f="pendente">A fazer</button>
          <button type="button" class="trpo-filtro" data-f="concluido">Concluídos</button>
        </div>
      </div>

      <div class="trpo-lista" id="poLista">
        <?php foreach ($inscricoes as $i): ?>
          <?php
            [$cls, $rotulo, $icone] = trpo_situacao($i);
            $c = $i['calc'];
            $grupo = $c['concluido'] ? 'concluido' : ($c['cancelado'] ? 'cancelado' : 'pendente');
            $temProva = (int)$i['ap_prova'] === 1;
          ?>
          <article class="trpo-card is-<?= h($cls) ?>" data-grupo="<?= h($grupo) ?>">

            <div class="trpo-card-topo">
              <span class="tra-chip tra-chip-<?= h($cls === 'atraso' ? 'curso' : $cls) ?>">
                <i class="bi bi-<?= h($icone) ?>"></i> <?= h($rotulo) ?>
              </span>
              <?php if ($i['ap_status'] !== 'publicado'): ?>
                <span class="trpo-tag">indisponível</span>
              <?php endif; ?>
              <?php if ((int)$i['ap_carga'] > 0): ?>
                <span class="trpo-tag"><i class="bi bi-clock"></i> <?= (int)$i['ap_carga'] ?> min</span>
              <?php endif; ?>
              <?php if ($temProva): ?>
                <span class="trpo-tag"><i class="bi bi-clipboard-check"></i> com prova</span>
              <?php endif; ?>
            </div>

            <h3><?= h($i['ap_titulo']) ?></h3>
            <?php if ((string)$i['ap_subtitulo'] !== ''): ?>
              <p class="trpo-card-sub"><?= h($i['ap_subtitulo']) ?></p>
            <?php endif; ?>

            <div class="trpo-card-barra">
              <div class="tra-barra"><i style="width:<?= (int)$c['pct'] ?>%"></i></div>
              <span><?= (int)$c['pct'] ?>%</span>
            </div>

            <div class="trpo-card-meta">
              <span><i class="bi bi-collection-play"></i>
                <?= (int)$i['slides_vistos'] ?> de <?= max(1, (int)$i['slides_total']) ?> etapas</span>

              <?php if ((int)$i['tempo_seg'] > 0): ?>
                <span><i class="bi bi-stopwatch"></i> <?= h(trm_tempo_fmt((int)$i['tempo_seg'])) ?></span>
              <?php endif; ?>

              <?php if ($c['concluido'] && !empty($i['concluido_em'])): ?>
                <span><i class="bi bi-calendar-check"></i>
                  Concluído em <?= h(date('d/m/Y', strtotime((string)$i['concluido_em']))) ?></span>
              <?php elseif (!empty($i['prazo'])): ?>
                <span class="<?= $c['atrasado'] ? 'is-alerta' : (($c['dias'] !== null && $c['dias'] <= 3) ? 'is-alerta' : '') ?>">
                  <i class="bi bi-calendar-event"></i>
                  <?php if ($c['atrasado']): ?>
                    Prazo vencido em <?= h(date('d/m/Y', strtotime((string)$i['prazo']))) ?>
                  <?php elseif ($c['dias'] !== null && $c['dias'] === 0): ?>
                    Prazo termina hoje
                  <?php else: ?>
                    Prazo <?= h(date('d/m/Y', strtotime((string)$i['prazo']))) ?>
                    <?php if ($c['dias'] !== null): ?>(<?= (int)$c['dias'] ?> dia(s))<?php endif; ?>
                  <?php endif; ?>
                </span>
              <?php endif; ?>

              <?php if ($temProva && (int)$i['prova_tentativas_feitas'] > 0): ?>
                <span><i class="bi bi-clipboard-data"></i>
                  Prova: <?= (int)$i['prova_percentual'] ?>%
                  <?= (int)$i['prova_aprovado'] === 1 ? '(aprovado)' : '(não aprovado)' ?></span>
              <?php endif; ?>
            </div>

            <div class="trpo-card-acoes">
              <?php if ($c['cancelado']): ?>
                <span class="trpo-nota">Matrícula cancelada. Procure o RH.</span>

              <?php elseif ($i['ap_status'] !== 'publicado'): ?>
                <span class="trpo-nota">Este treinamento não está liberado no momento.</span>

              <?php elseif ($c['atrasado']): ?>
                <span class="trpo-nota">O prazo venceu. Procure o RH para reabrir o acesso.</span>

              <?php elseif ($c['concluido']): ?>
                <?php if ($c['cert'] !== ''): ?>
                  <a class="tra-btn tra-btn-primaria" href="<?= h($c['cert']) ?>" target="_blank" rel="noopener">
                    <i class="bi bi-award"></i> Ver certificado
                  </a>
                <?php endif; ?>
                <?php if ((int)$i['ap_revisao'] === 1): ?>
                  <a class="tra-btn tra-btn-ghost" href="<?= h($c['link']) ?>">
                    <i class="bi bi-arrow-repeat"></i> Rever conteúdo
                  </a>
                <?php endif; ?>

              <?php else: ?>
                <a class="tra-btn tra-btn-primaria" href="<?= h($c['link']) ?>">
                  <i class="bi bi-<?= $c['iniciado'] ? 'play-circle-fill' : 'play-fill' ?>"></i>
                  <?= $c['iniciado'] ? 'Continuar de onde parei' : 'Iniciar treinamento' ?>
                </a>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <p class="trpo-sem-filtro" id="poSemFiltro" hidden>
        Nenhum treinamento nesta situação.
      </p>
    </section>
  <?php endif; ?>

  <?php if ($certificados): ?>
    <section class="trpo-secao">
      <div class="trpo-secao-topo">
        <h2><i class="bi bi-award"></i> Meus certificados</h2>
      </div>

      <div class="trpo-certs">
        <?php foreach ($certificados as $i): ?>
          <a class="trpo-cert" href="<?= h($i['calc']['cert']) ?>" target="_blank" rel="noopener">
            <span class="trpo-cert-ic"><i class="bi bi-patch-check-fill"></i></span>
            <span class="trpo-cert-corpo">
              <b><?= h($i['ap_titulo']) ?></b>
              <small>
                <?= h(date('d/m/Y', strtotime((string)$i['concluido_em']))) ?>
                · código <?= h($i['certificado_codigo']) ?>
              </small>
            </span>
            <i class="bi bi-box-arrow-up-right"></i>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <p class="trpo-identidade">
    <i class="bi bi-person-vcard"></i>
    <?= h($colab['nome']) ?>
    <?php if ((string)($colab['matricula'] ?? '') !== ''): ?>
      · matrícula <?= h($colab['matricula']) ?>
    <?php endif; ?>
    <?php if (trm_cpf_mascarado((string)($colab['cpf'] ?? '')) !== ''): ?>
      · CPF <?= h(trm_cpf_mascarado((string)$colab['cpf'])) ?>
    <?php endif; ?>
  </p>
</main>

<footer class="tra-rodape">
  RH Digital - Nórdica Veículos · Em caso de dúvida, procure o setor de Recursos Humanos.
</footer>

<script src="<?= h(tr_url('assets/js/portal.js')) ?>"></script>
</body>
</html>
