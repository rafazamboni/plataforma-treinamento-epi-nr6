<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_once __DIR__ . '/_matriculas.php';
require_once __DIR__ . '/_prova.php';

require_login();

if (!trm_pode_ver()) {
    flash_set('danger', 'Acesso restrito.');
    nv_redirect('/index.php');
}

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);
$ap  = $id > 0 ? tr_apresentacao($pdo, $id) : null;

if (!$ap) {
    flash_set('danger', 'Treinamento não encontrado.');
    nv_redirect(tr_url('index.php'));
}

if (isset($_GET['foto'])) {
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    tr_servir_foto_inscricao($pdo, $id, (int)$_GET['foto'], isset($_GET['diag']));
}

$gerencia = trm_pode_gerenciar();
$temProva = trp_prova_ativa($ap, $pdo);
$qtdQuestoes = trp_total_valido($pdo, $id);

$busca  = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? '');

trm_cancelar_desligados($pdo, $id);

$where  = ['i.apresentacao_id = ?', trm_sql_sem_desligados('i')];
$params = [$id];

if ($busca !== '') {
    $where[] = '(i.nome LIKE ? OR i.cpf LIKE ? OR i.email LIKE ? OR i.filial LIKE ? OR i.cargo LIKE ? OR i.matricula LIKE ?)';
    $like = '%' . $busca . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}
if ($status === 'abertos') {
    $where[] = 'i.status IN ("pendente","em_andamento")';
} elseif (in_array($status, ['pendente', 'em_andamento', 'concluido', 'cancelado'], true)) {
    $where[] = 'i.status = ?';
    $params[] = $status;
}

$st = $pdo->prepare(
    'SELECT DISTINCT i.filial FROM treinamentos_inscricoes i
      WHERE i.apresentacao_id = ? AND i.filial <> "" AND ' . trm_sql_sem_desligados('i') . '
      ORDER BY i.filial'
);
$st->execute([$id]);
$filiais = array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));

$filial = (string)($_GET['filial'] ?? '');
if ($filial !== '' && !in_array($filial, $filiais, true)) { $filial = ''; }
if ($filial !== '') {
    $where[] = 'i.filial = ?';
    $params[] = $filial;
}

$sqlWhere = 'WHERE ' . implode(' AND ', $where);

$exportando = ($_GET['export'] ?? '') === 'csv';
$opcoesPorPagina = [10, 25, 50, 100];
$porPagina = (int)($_GET['por'] ?? 25);
if (!in_array($porPagina, $opcoesPorPagina, true)) { $porPagina = 25; }

$st = $pdo->prepare("SELECT COUNT(*) FROM treinamentos_inscricoes i $sqlWhere");
$st->execute($params);
$totalFiltrado = (int)$st->fetchColumn();
$totalPaginas  = max(1, (int)ceil($totalFiltrado / $porPagina));
$pagina        = min(max(1, (int)($_GET['pag'] ?? 1)), $totalPaginas);
$limite        = $exportando ? '' : ' LIMIT ' . $porPagina . ' OFFSET ' . (($pagina - 1) * $porPagina);

$st = $pdo->prepare(
    "SELECT i.* FROM treinamentos_inscricoes i
     $sqlWhere
     ORDER BY FIELD(i.status,'em_andamento','pendente','concluido','cancelado'), i.nome, i.id$limite"
);
$st->execute($params);
$lista = $st->fetchAll(PDO::FETCH_ASSOC);

if (($_GET['export'] ?? '') === 'csv') {
    $arquivo = 'matriculas-' . tr_slugify((string)$ap['titulo']) . '-' . date('Ymd-Hi') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $arquivo . '"');

    $saida = fopen('php://output', 'w');
    fwrite($saida, "\xEF\xBB\xBF");
    fputcsv($saida, [
        'Matrícula', 'Nome', 'CPF', 'E-mail', 'Cargo', 'Filial', 'Situação', 'Progresso (%)',
        'Slides concluídos', 'Total de slides', 'Tempo dedicado', 'Acessos',
        'Prazo', 'Primeiro acesso', 'Último acesso', 'Conclusão',
        'Prova (%)', 'Prova aprovada', 'Tentativas de prova',
        'Certificado', 'Link individual',
    ], ';');

    foreach ($lista as $r) {
        fputcsv($saida, [
            $r['matricula'] ?? '',
            $r['nome'],
            $r['cpf'],
            $r['email'],
            $r['cargo'],
            $r['filial'],
            trm_status_rotulo((string)$r['status']),
            (int)$r['percentual'],
            (int)$r['slides_vistos'],
            (int)$r['slides_total'],
            trm_tempo_fmt((int)$r['tempo_seg']),
            (int)$r['acessos'],
            $r['prazo'] ? date('d/m/Y', strtotime((string)$r['prazo'])) : '',
            $r['iniciado_em'] ? date('d/m/Y H:i', strtotime((string)$r['iniciado_em'])) : '',
            $r['ultimo_acesso_em'] ? date('d/m/Y H:i', strtotime((string)$r['ultimo_acesso_em'])) : '',
            $r['concluido_em'] ? date('d/m/Y H:i', strtotime((string)$r['concluido_em'])) : '',
            (int)$r['prova_percentual'],
            (int)$r['prova_aprovado'] === 1 ? 'Sim' : 'Não',
            (int)$r['prova_tentativas_feitas'],
            $r['certificado_codigo'],
            trm_link_token((string)$r['token']),
        ], ';');
    }
    fclose($saida);
    exit;
}

$st = $pdo->prepare(
    'SELECT COUNT(*) total,
            SUM(status = "pendente")     pendentes,
            SUM(status = "em_andamento") andamento,
            SUM(status = "concluido")    concluidos,
            SUM(status = "cancelado")    cancelados,
            SUM(email <> "" AND enviado_em IS NULL AND status NOT IN ("cancelado","concluido")) nao_enviados,
            SUM(prova_aprovado = 1) prova_aprovados,
            SUM(prova_tentativas_feitas > 0 AND prova_aprovado = 0) prova_reprovados,
            AVG(percentual) media
       FROM treinamentos_inscricoes i WHERE i.apresentacao_id = ? AND ' . trm_sql_sem_desligados('i')
);
$st->execute([$id]);
$kpi = $st->fetch(PDO::FETCH_ASSOC) ?: [];

$total      = (int)($kpi['total'] ?? 0);
$concluidos = (int)($kpi['concluidos'] ?? 0);
$ativos     = $total - (int)($kpi['cancelados'] ?? 0);
$taxa       = $ativos > 0 ? (int)round(($concluidos / $ativos) * 100) : 0;

$qtdSlides = count(trm_slides_ids($pdo, $id));

require_once __DIR__ . '/../../../app/layout/header.php';
nv_render_header('Matrículas — ' . $ap['titulo']);
?>
<link rel="stylesheet" href="<?= h(tr_url('assets/css/admin.css')) ?>">
<link rel="stylesheet" href="<?= h(tr_url('assets/css/gestao.css')) ?>">
<link rel="stylesheet" href="<?= h(tr_url('assets/css/matriculas.css')) ?>">

<div class="tr-wrap trg-wrap">

  <?php
    $abaAtiva      = 'matriculas';
    $qtdMatriculas = $total;
    require __DIR__ . '/_abas.php';
  ?>

  <?php if ($qtdSlides === 0): ?>
    <div class="trg-aviso">
      <i class="bi bi-exclamation-triangle"></i>
      <div><strong>Nenhum slide cadastrado.</strong> Monte o conteúdo no editor antes de matricular colaboradores.</div>
    </div>
  <?php endif; ?>

  <section class="trg-painel trm-panorama">
    <div class="trm-taxa">
      <div class="trm-taxa-anel" style="--pct:<?= $taxa ?>">
        <b><?= $taxa ?>%</b>
        <small>concluíram</small>
      </div>
      <span class="trm-taxa-leg"><?= $concluidos ?> de <?= max(1, $ativos) ?> matrículas ativas</span>
    </div>

    <div class="trm-numeros">
      <div class="trm-num">
        <b><?= $total ?></b><small>Matriculados</small>
      </div>
      <div class="trm-num">
        <b><?= (int)($kpi['pendentes'] ?? 0) ?></b><small>Não iniciaram</small>
      </div>
      <div class="trm-num">
        <b><?= (int)($kpi['andamento'] ?? 0) ?></b><small>Em andamento</small>
      </div>
      <div class="trm-num">
        <b><?= (int)round((float)($kpi['media'] ?? 0)) ?>%</b><small>Progresso médio</small>
      </div>
      <?php if ($temProva): ?>
        <div class="trm-num">
          <b><?= (int)($kpi['prova_aprovados'] ?? 0) ?></b><small>Aprovados na prova</small>
        </div>
        <?php if ((int)($kpi['prova_reprovados'] ?? 0) > 0): ?>
          <div class="trm-num is-alerta">
            <b><?= (int)$kpi['prova_reprovados'] ?></b><small>Reprovados pendentes</small>
          </div>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ((int)($kpi['nao_enviados'] ?? 0) > 0): ?>
        <div class="trm-num is-alerta">
          <b><?= (int)$kpi['nao_enviados'] ?></b><small>Links não enviados</small>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <div class="tr-toolbar">
    <form class="tr-filtros" method="get" action="">
      <input type="hidden" name="id" value="<?= $id ?>">
      <?php if ($porPagina !== 25): ?><input type="hidden" name="por" value="<?= $porPagina ?>"><?php endif; ?>
      <div class="tr-campo-busca">
        <i class="bi bi-search"></i>
        <input type="text" name="q" class="form-control" placeholder="Buscar por nome, matrícula, CPF ou filial"
               value="<?= h($busca) ?>">
      </div>
      <?php if ($filiais): ?>
      <select name="filial" class="form-select" style="max-width:200px" title="Filtrar por filial">
        <option value="">Todas as filiais</option>
        <?php foreach ($filiais as $f): ?>
          <option value="<?= h($f) ?>" <?= $filial === $f ? 'selected' : '' ?>><?= h($f) ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
      <select name="status" class="form-select" style="max-width:220px">
        <option value="">Todas as situações</option>
        <option value="abertos"      <?= $status === 'abertos'      ? 'selected' : '' ?>>Pendentes (não iniciados + em andamento)</option>
        <option value="pendente"     <?= $status === 'pendente'     ? 'selected' : '' ?>>Não iniciados</option>
        <option value="em_andamento" <?= $status === 'em_andamento' ? 'selected' : '' ?>>Em andamento</option>
        <option value="concluido"    <?= $status === 'concluido'    ? 'selected' : '' ?>>Concluídos</option>
        <option value="cancelado"    <?= $status === 'cancelado'    ? 'selected' : '' ?>>Cancelados</option>
      </select>
      <button type="submit" class="btn btn-outline-secondary">Filtrar</button>
      <?php if ($busca !== '' || $status !== '' || $filial !== ''): ?>
        <a href="<?= h(tr_url('matriculas.php')) ?>?id=<?= $id ?>" class="btn btn-link btn-sm">Limpar</a>
      <?php endif; ?>
    </form>

    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-sm tr-btn-ghost"
         href="<?= h(tr_url('matriculas.php')) ?>?id=<?= $id ?>&export=csv&q=<?= h(rawurlencode($busca)) ?>&status=<?= h(rawurlencode($status)) ?>&filial=<?= h(rawurlencode($filial)) ?>">
        <i class="bi bi-filetype-csv"></i> Exportar
      </a>
      <?php if ($gerencia): ?>
        <button type="button" class="btn btn-sm tr-btn-ghost" id="btnEnviarPendentes">
          <i class="bi bi-envelope-arrow-up"></i> Enviar links pendentes
          <?php if ((int)($kpi['nao_enviados'] ?? 0) > 0): ?>
            <span class="trm-badge"><?= (int)$kpi['nao_enviados'] ?></span>
          <?php endif; ?>
        </button>
        <button type="button" class="btn btn-sm tr-btn-ghost" id="btnLote">
          <i class="bi bi-people"></i> Importar lista
        </button>
        <button type="button" class="btn tr-btn-primaria btn-sm" id="btnNovaMatricula">
          <i class="bi bi-plus-lg"></i> Nova matrícula
        </button>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($gerencia && $lista): ?>
  <div class="trm-barra-sel" id="trmBarraSel" hidden
       style="display:none;align-items:center;gap:12px;flex-wrap:wrap;margin:0 0 10px;padding:8px 12px;border:1px solid var(--bs-border-color,#dee2e6);border-radius:8px;background:var(--bs-light,#f8f9fa)">
    <span><b id="trmSelQtd">0</b> selecionado(s)</span>
    <button type="button" class="btn btn-sm tr-btn-primaria" id="trmReenviarSel">
      <i class="bi bi-envelope-arrow-up"></i> Reenviar link aos selecionados
    </button>
    <button type="button" class="btn btn-sm tr-btn-ghost" id="trmPrazoSel">
      <i class="bi bi-calendar-event"></i> Alterar prazo dos selecionados
    </button>
    <button type="button" class="btn btn-sm btn-link" id="trmSelLimpar">Limpar seleção</button>
    <span class="trm-ajuda" id="trmSelStatus" style="margin-left:auto"></span>
  </div>
  <?php endif; ?>

  <?php if (!$lista): ?>
    <div class="tr-vazio">
      <i class="bi bi-person-plus"></i>
      <p>Nenhum colaborador matriculado<?= ($busca !== '' || $status !== '' || $filial !== '') ? ' com esses filtros' : '' ?>.</p>
      <?php if ($gerencia && $busca === '' && $status === '' && $filial === ''): ?>
        <button type="button" class="btn tr-btn-primaria btn-sm" id="btnNovaMatriculaVazio">Matricular o primeiro</button>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="tr-tabela-wrap">
      <table class="table tr-tabela trm-tabela align-middle">
        <thead>
          <tr>
            <?php if ($gerencia): ?>
              <th style="width:36px">
                <input type="checkbox" class="form-check-input" id="trmSelTodos" title="Selecionar todos desta página">
              </th>
            <?php endif; ?>
            <th>Colaborador</th>
            <th>Lotação</th>
            <th style="min-width:190px">Progresso</th>
            <th>Situação</th>
            <?php if ($temProva): ?><th>Prova</th><?php endif; ?>
            <th>Prazo</th>
            <th>Último acesso</th>
            <th class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($lista as $r): ?>
          <?php
            $pct   = (int)$r['percentual'];
            $link  = trm_link_token((string)$r['token']);
            $atras = !empty($r['prazo']) && $r['prazo'] < date('Y-m-d') && $r['status'] !== 'concluido';
            $externo = $r['status'] === 'concluido' && (string)($r['concluido_origem'] ?? 'plataforma') === 'externo';
          ?>
          <tr data-id="<?= (int)$r['id'] ?>" class="<?= $r['status'] === 'cancelado' ? 'is-cancelada' : '' ?>">
            <?php if ($gerencia): ?>
              <?php
                $semEmail  = trim((string)$r['email']) === '';
                $cancelada = $r['status'] === 'cancelado';
                $bloqSel   = $externo;
                $podeEnvio = !$semEmail && !$cancelada && !$externo;
              ?>
              <td>
                <input type="checkbox" class="form-check-input trm-sel" value="<?= (int)$r['id'] ?>"
                       data-envio="<?= $podeEnvio ? '1' : '0' ?>"
                       <?= $bloqSel ? 'disabled' : '' ?>
                       title="<?= $externo ? 'Concluído em outra plataforma (não entra nas ações em lote)' : ($cancelada ? 'Matrícula cancelada (só o prazo pode ser alterado)' : ($semEmail ? 'Sem e-mail cadastrado (só o prazo pode ser alterado)' : 'Selecionar')) ?>"
                       aria-label="Selecionar <?= h($r['nome']) ?>">
              </td>
            <?php endif; ?>
            <td>
              <div class="trm-pessoa">
                <?php $mat = (string)($r['matricula'] ?? ''); ?>
                <span class="trm-foto">
                  <?php if ($mat !== '' || !empty($r['colaborador_id'])): ?>
                    <img src="<?= h(tr_foto_inscricao_url($id, (int)$r['id'])) ?>" alt="" loading="lazy" onerror="this.remove()">
                  <?php endif; ?>
                  <i class="bi bi-person"></i>
                </span>
                <span class="trm-pessoa-txt">
                  <strong><?= h($r['nome']) ?></strong>
                  <small>
                    <?php if ($mat !== ''): ?>
                      <em class="trm-mat">#<?= h($mat) ?></em>
                    <?php else: ?>
                      <em class="trm-mat is-solta" title="Matrícula anterior ao vínculo com o cadastro">sem vínculo</em>
                    <?php endif; ?>
                    <?php if ((string)$r['email'] !== ''): ?>
                      <?= h($r['email']) ?>
                    <?php else: ?>
                      <span class="trm-sem-email"><i class="bi bi-envelope-slash"></i> sem e-mail</span>
                    <?php endif; ?>
                  </small>
                </span>
              </div>
            </td>
            <td>
              <div class="trm-lotacao">
                <span><?= h($r['cargo'] ?: '—') ?></span>
                <small><?= h($r['filial'] ?: '—') ?></small>
              </div>
            </td>
            <td>
              <div class="tr-mini-barra"><i style="width:<?= $pct ?>%"></i></div>
              <small class="text-muted">
                <?= (int)$r['slides_vistos'] ?> de <?= (int)($r['slides_total'] ?: $qtdSlides) ?> slides
                <?php if ((int)$r['tempo_seg'] > 0): ?>
                  · <?= h(trm_tempo_fmt((int)$r['tempo_seg'])) ?>
                <?php endif; ?>
              </small>
            </td>
            <td>
              <span class="tr-chip trm-chip-<?= h($r['status']) ?>">
                <i class="bi bi-<?= [
                    'pendente'     => 'circle',
                    'em_andamento' => 'hourglass-split',
                    'concluido'    => 'check-lg',
                    'cancelado'    => 'x-lg',
                  ][$r['status']] ?? 'circle' ?>"></i>
                <?= h(trm_status_rotulo((string)$r['status'])) ?>
              </span>
              <?php if ($r['status'] === 'concluido' && (string)($r['concluido_origem'] ?? 'plataforma') === 'externo'): ?>
                <span class="trm-cert" title="Concluído em outra plataforma">
                  <i class="bi bi-file-earmark-pdf"></i> outra plataforma
                </span>
                <?php if ((string)($r['certificado_externo_arquivo'] ?? '') !== ''): ?>
                  <a class="trm-cert" href="<?= h(tr_url('certificado_externo.php')) ?>?id=<?= (int)$r['id'] ?>" target="_blank">
                    ver certificado
                  </a>
                <?php endif; ?>
              <?php elseif ($r['status'] === 'concluido' && (string)$r['certificado_codigo'] !== ''): ?>
                <a class="trm-cert" href="<?= h(trm_link_certificado((string)$r['certificado_codigo'])) ?>" target="_blank"
                   title="Ver certificado"><i class="bi bi-award"></i> certificado</a>
              <?php endif; ?>
            </td>
            <?php if ($temProva): ?>
              <td>
                <?php if ((int)$r['prova_aprovado'] === 1): ?>
                  <span class="trm-nota is-ok"><?= (int)$r['prova_percentual'] ?>%</span>
                  <small class="d-block text-muted"><?= (int)$r['prova_tentativas_feitas'] ?> tentativa(s)</small>
                <?php elseif ((int)$r['prova_tentativas_feitas'] > 0): ?>
                  <span class="trm-nota is-baixa"><?= (int)$r['prova_percentual'] ?>%</span>
                  <small class="d-block text-muted">
                    <?= (int)$r['prova_tentativas_feitas'] ?><?= (int)$ap['prova_tentativas'] > 0 ? ' de ' . (int)$ap['prova_tentativas'] : '' ?> tentativa(s)
                  </small>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
            <?php endif; ?>
            <td>
              <?php if (!empty($r['prazo'])): ?>
                <span class="<?= $atras ? 'trm-atrasado' : '' ?>">
                  <?= h(date('d/m/Y', strtotime((string)$r['prazo']))) ?>
                </span>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($r['ultimo_acesso_em'])): ?>
                <?= h(nv_fmt_dt($r['ultimo_acesso_em'])) ?>
                <small class="d-block text-muted"><?= (int)$r['acessos'] ?> acesso(s)</small>
              <?php else: ?>
                <span class="text-muted">nunca abriu</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <div class="trm-acoes">
                <?php if (!$externo): ?>
                  <button type="button" class="trm-ico trm-copiar" data-link="<?= h($link) ?>" title="Copiar link individual">
                    <i class="bi bi-clipboard"></i>
                  </button>
                <?php endif; ?>
                <?php if ($gerencia): ?>
                  <?php if (!$externo): ?>
                    <button type="button" class="trm-ico trm-enviar" data-id="<?= (int)$r['id'] ?>"
                            data-email="<?= h($r['email']) ?>"
                            title="<?= empty($r['enviado_em']) ? 'Enviar link por e-mail' : 'Reenviar link (enviado ' . h(nv_fmt_dt($r['enviado_em'])) . ')' ?>">
                      <i class="bi bi-<?= empty($r['enviado_em']) ? 'envelope' : 'envelope-check' ?>"></i>
                    </button>
                  <?php endif; ?>
                  <div class="trm-menu">
                    <button type="button" class="trm-ico trm-menu-abrir" title="Mais ações"><i class="bi bi-three-dots"></i></button>
                    <div class="trm-menu-lista">
                      <button type="button" data-acao="prazo" data-id="<?= (int)$r['id'] ?>"
                              data-nome="<?= h($r['nome']) ?>"
                              data-prazo="<?= h((string)($r['prazo'] ?? '')) ?>">
                        <i class="bi bi-calendar-event"></i> Alterar prazo
                      </button>
                      <?php if (!empty($r['colaborador_id'])): ?>
                        <button type="button" data-acao="sincronizar" data-id="<?= (int)$r['id'] ?>">
                          <i class="bi bi-arrow-repeat"></i> Atualizar pelo cadastro
                        </button>
                      <?php endif; ?>
                      <?php if (!$externo): ?>
                      <button type="button" data-acao="token" data-id="<?= (int)$r['id'] ?>">
                        <i class="bi bi-arrow-repeat"></i> Gerar novo link
                      </button>
                      <?php endif; ?>
                      <button type="button" data-acao="reiniciar" data-id="<?= (int)$r['id'] ?>">
                        <i class="bi bi-arrow-counterclockwise"></i> Zerar progresso
                      </button>
                      <?php if ($r['status'] !== 'concluido'): ?>
                        <button type="button" data-acao="externo" data-id="<?= (int)$r['id'] ?>"
                                data-nome="<?= h($r['nome']) ?>">
                          <i class="bi bi-file-earmark-pdf"></i> Marcar como realizado (outra plataforma)
                        </button>
                      <?php endif; ?>
                      <?php if ($temProva && (int)$r['prova_tentativas_feitas'] > 0 && (int)$r['prova_aprovado'] !== 1): ?>
                        <button type="button" data-acao="prova_liberar" data-id="<?= (int)$r['id'] ?>">
                          <i class="bi bi-unlock"></i> Liberar nova tentativa
                        </button>
                      <?php endif; ?>
                      <?php if ($r['status'] === 'cancelado'): ?>
                        <button type="button" data-acao="reativar" data-id="<?= (int)$r['id'] ?>">
                          <i class="bi bi-check2"></i> Reativar matrícula
                        </button>
                      <?php else: ?>
                        <button type="button" data-acao="cancelar" data-id="<?= (int)$r['id'] ?>">
                          <i class="bi bi-slash-circle"></i> Cancelar acesso
                        </button>
                      <?php endif; ?>
                      <button type="button" class="is-perigo" data-acao="excluir" data-id="<?= (int)$r['id'] ?>"
                              data-nome="<?= h($r['nome']) ?>">
                        <i class="bi bi-trash"></i> Excluir matrícula
                      </button>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php
      $urlPag = function (int $p) use ($id, $busca, $status, $filial, $porPagina): string {
          $q = ['id' => $id];
          if ($busca !== '')     { $q['q'] = $busca; }
          if ($status !== '')    { $q['status'] = $status; }
          if ($filial !== '')    { $q['filial'] = $filial; }
          if ($porPagina !== 25) { $q['por'] = $porPagina; }
          if ($p > 1)            { $q['pag'] = $p; }
          return tr_url('matriculas.php') . '?' . http_build_query($q);
      };
      $de  = ($pagina - 1) * $porPagina + 1;
      $ate = min($pagina * $porPagina, $totalFiltrado);

      $janela = [1, $totalPaginas];
      for ($p = $pagina - 2; $p <= $pagina + 2; $p++) {
          if ($p >= 1 && $p <= $totalPaginas) { $janela[] = $p; }
      }
      $janela = array_values(array_unique($janela));
      sort($janela);
    ?>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
      <small class="text-muted">
        Exibindo <b><?= $de ?>–<?= $ate ?></b> de <b><?= $totalFiltrado ?></b> matrícula(s)
      </small>

      <form method="get" action="" class="d-flex align-items-center gap-2 m-0">
        <input type="hidden" name="id" value="<?= $id ?>">
        <?php if ($busca !== ''): ?><input type="hidden" name="q" value="<?= h($busca) ?>"><?php endif; ?>
        <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= h($status) ?>"><?php endif; ?>
        <?php if ($filial !== ''): ?><input type="hidden" name="filial" value="<?= h($filial) ?>"><?php endif; ?>
        <label class="small text-muted mb-0" for="trmPorPagina">Por página</label>
        <select name="por" id="trmPorPagina" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
          <?php foreach ($opcoesPorPagina as $op): ?>
            <option value="<?= $op ?>" <?= $op === $porPagina ? 'selected' : '' ?>><?= $op ?></option>
          <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">OK</button></noscript>
      </form>

      <?php if ($totalPaginas > 1): ?>
        <nav aria-label="Paginação das matrículas">
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= h($urlPag(max(1, $pagina - 1))) ?>" aria-label="Anterior">&laquo;</a>
            </li>
            <?php $anterior = 0; foreach ($janela as $p): ?>
              <?php if ($p - $anterior > 1): ?>
                <li class="page-item disabled"><span class="page-link">…</span></li>
              <?php endif; ?>
              <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
                <a class="page-link" href="<?= h($urlPag($p)) ?>"><?= $p ?></a>
              </li>
              <?php $anterior = $p; ?>
            <?php endforeach; ?>
            <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= h($urlPag(min($totalPaginas, $pagina + 1))) ?>" aria-label="Próxima">&raquo;</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($gerencia): ?>
<div class="tr-modal" id="modalMatricula" aria-hidden="true">
  <div class="tr-modal-caixa">
    <div class="tr-modal-head">
      <span>Matricular colaborador</span>
      <button type="button" class="tr-modal-x" data-fechar="modalMatricula"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="tr-modal-body">

      <div class="trm-campo">
        <label for="mtBusca">Buscar no cadastro</label>
        <div class="trm-busca-campo">
          <i class="bi bi-search"></i>
          <input type="text" class="form-control" id="mtBusca" autocomplete="off"
                 placeholder="Nome, número de matrícula ou CPF">
        </div>
        <small>Só aparecem colaboradores ativos.</small>
      </div>

      <div class="trm-resultados" id="mtResultados">
        <p class="trm-resultados-vazio">Digite ao menos duas letras para buscar.</p>
      </div>

      <div class="trm-selecionado" id="mtSelecionado" hidden>
        <i class="bi bi-person-check-fill"></i>
        <div>
          <strong id="mtSelNome"></strong>
          <small id="mtSelInfo"></small>
        </div>
        <button type="button" class="trm-ico" id="mtLimpar" title="Trocar"><i class="bi bi-x-lg"></i></button>
      </div>

      <div class="trm-campo mt-3">
        <label for="mtPrazo">Prazo para conclusão</label>
        <input type="date" class="form-control" id="mtPrazo">
        <small><?= (int)$ap['prazo_dias'] > 0
          ? 'Em branco usa o padrão de ' . (int)$ap['prazo_dias'] . ' dias.'
          : 'Opcional.' ?></small>
      </div>

      <label class="trm-check mt-2"><input type="checkbox" id="mtEnviar" checked>
        <span>Enviar o link por e-mail ao matricular</span></label>
    </div>
    <div class="tr-modal-pe">
      <button type="button" class="btn tr-btn-ghost" data-fechar="modalMatricula">Cancelar</button>
      <button type="button" class="btn tr-btn-primaria" id="mtSalvar" disabled>Matricular</button>
    </div>
  </div>
</div>

<div class="tr-modal" id="modalLote" aria-hidden="true">
  <div class="tr-modal-caixa" style="max-width:600px">
    <div class="tr-modal-head">
      <span>Matricular em lote</span>
      <button type="button" class="tr-modal-x" data-fechar="modalLote"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="tr-modal-body">
      <p class="trm-ajuda">
        Cole os <b>números de matrícula</b> separados por vírgula. Também aceita
        ponto e vírgula, espaço ou uma por linha. Os dados vêm do cadastro —
        quem não existir ou estiver inativo é apontado ao final.
      </p>
      <textarea class="form-control trm-lote" id="loteTexto" rows="5"
                placeholder="1024, 1130, 1287, 1355"></textarea>

      <div class="trm-campo mt-3">
        <label for="lotePrazo">Prazo para todos</label>
        <input type="date" class="form-control" id="lotePrazo">
      </div>

      <label class="trm-check mt-2"><input type="checkbox" id="loteEnviar" checked>
        <span>Enviar os links por e-mail após matricular</span></label>

      <div class="trm-lote-erros" id="loteErros" hidden></div>
    </div>
    <div class="tr-modal-pe">
      <button type="button" class="btn tr-btn-ghost" data-fechar="modalLote">Cancelar</button>
      <button type="button" class="btn tr-btn-primaria" id="loteConfirmar">Matricular</button>
    </div>
  </div>
</div>

<div class="tr-modal" id="modalPrazo" aria-hidden="true">
  <div class="tr-modal-caixa" style="max-width:420px">
    <div class="tr-modal-head">
      <span>Alterar prazo</span>
      <button type="button" class="tr-modal-x" data-fechar="modalPrazo"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="tr-modal-body">
      <input type="hidden" id="pzId" value="0">
      <input type="hidden" id="pzIds" value="">
      <p class="trm-ajuda" id="pzNome"></p>
      <div class="trm-campo">
        <label for="pzData">Novo prazo</label>
        <input type="date" class="form-control" id="pzData">
        <small>Em branco remove o prazo.</small>
      </div>
    </div>
    <div class="tr-modal-pe">
      <button type="button" class="btn tr-btn-ghost" data-fechar="modalPrazo">Cancelar</button>
      <button type="button" class="btn tr-btn-primaria" id="pzSalvar">Salvar prazo</button>
    </div>
  </div>
</div>

<div class="tr-modal" id="modalExterno" aria-hidden="true">
  <div class="tr-modal-caixa">
    <div class="tr-modal-head">
      <span>Marcar treinamento como realizado</span>
      <button type="button" class="tr-modal-x" data-fechar="modalExterno"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="tr-modal-body">
      <input type="hidden" id="exId" value="0">
      <p class="trm-ajuda" id="exNome"></p>

      <div class="trm-campo">
        <label for="exData">Data em que o colaborador concluiu</label>
        <input type="date" class="form-control" id="exData">
      </div>
      <div class="trm-campo">
        <label for="exObs">Observação (opcional)</label>
        <textarea class="form-control" id="exObs" rows="2" maxlength="255"
                  placeholder="Ex.: realizado na plataforma da Senior em 10/2025"></textarea>
      </div>
      <div class="trm-campo">
        <label for="exArquivo">Certificado (PDF)</label>
        <input type="file" class="form-control" id="exArquivo" accept="application/pdf">
        <small>Opcional, mas recomendado como comprovante.</small>
      </div>
    </div>
    <div class="tr-modal-pe">
      <button type="button" class="btn tr-btn-ghost" data-fechar="modalExterno">Cancelar</button>
      <button type="button" class="btn tr-btn-primaria" id="exSalvar">Marcar como realizado</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
  window.TR_CSRF   = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window.TR_API    = <?= json_encode(tr_url('api.php'), JSON_HEX_TAG) ?>;
  window.TR_BASE   = <?= json_encode(tr_url(), JSON_HEX_TAG) ?>;
  window.TRM_APRES = <?= (int)$id ?>;
</script>
<script src="<?= h(tr_url('assets/js/ui.js')) ?>"></script>
<script src="<?= h(tr_url('assets/js/matriculas.js')) ?>?v=<?= (int)@filemtime(__DIR__ . '/assets/js/matriculas.js') ?>"></script>

<?php require_once __DIR__ . '/../../../app/layout/footer.php'; ?>
