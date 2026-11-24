<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_once __DIR__ . '/_matriculas.php';

require_login();

$pdo = db();
$uid = (int)current_user_id();

$ehAdmin = is_admin_user();

if (!$ehAdmin && !has_permission($pdo, 'instrucoes.acompanhamento.menu')) {
    if (nv_wants_json()) {
        nv_json(['ok' => false, 'msg' => 'Sem permissão.'], 403);
    }
    flash_set('danger', 'Acesso restrito.');
    nv_redirect('/index.php');
}

$podeReenviar = $ehAdmin || has_permission($pdo, 'instrucoes.acompanhamento.reenviar');
$podeContato  = $ehAdmin || has_permission($pdo, 'instrucoes.acompanhamento.ver_contato');

$filiais = [];
if ($ehAdmin) {
    foreach ($pdo->query('SELECT id, nome FROM filiais ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $filiais[(int)$f['id']] = (string)$f['nome'];
    }
} else {
    $ids = array_values(array_unique(array_filter(array_map(
        static fn($f) => (int)($f['id'] ?? 0),
        user_filiais($pdo, $uid) ?: []
    ))));
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT id, nome FROM filiais WHERE id IN ($in) ORDER BY nome");
        $st->execute($ids);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $filiais[(int)$f['id']] = (string)$f['nome'];
        }
    }
}
$semFilial = !$ehAdmin && !$filiais;

function tra_escopo(array $filiais, bool $ehAdmin, int $filialSel = 0): array
{
    $where = [trm_sql_sem_desligados('i')];
    $params = [];

    if ($filialSel > 0 && isset($filiais[$filialSel])) {
        $where[] = '(c.filial_id = ? OR (c.id IS NULL AND i.filial = ?))';
        array_push($params, $filialSel, $filiais[$filialSel]);
    } elseif (!$ehAdmin) {
        if (!$filiais) {
            $where[] = '1 = 0';
        } else {
            $ids   = array_keys($filiais);
            $nomes = array_values($filiais);
            $in1 = implode(',', array_fill(0, count($ids), '?'));
            $in2 = implode(',', array_fill(0, count($nomes), '?'));
            $where[] = "(c.filial_id IN ($in1) OR (c.id IS NULL AND i.filial IN ($in2)))";
            $params = array_merge($params, $ids, $nomes);
        }
    }

    return [$where, $params];
}

const TRA_FROM = 'FROM treinamentos_inscricoes i
                  JOIN treinamentos_apresentacoes a ON a.id = i.apresentacao_id
             LEFT JOIN colaboradores c ON c.id = i.colaborador_id';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();

    $acao  = (string)($_POST['acao'] ?? '');
    $dados = json_decode((string)($_POST['dados'] ?? '{}'), true);
    if (!is_array($dados)) { $dados = []; }

    if ($acao !== 'reenviar') {
        nv_json(['ok' => false, 'msg' => 'Ação desconhecida.'], 400);
    }
    if (!$podeReenviar) {
        nv_json(['ok' => false, 'msg' => 'Sem permissão.'], 403);
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($dados['ids'] ?? [])))));
    if (!$ids) {
        nv_json(['ok' => false, 'msg' => 'Nenhuma matrícula selecionada.'], 422);
    }
    if (count($ids) > 200) {
        nv_json(['ok' => false, 'msg' => 'Selecione no máximo 200 matrículas por vez.'], 422);
    }

    [$w, $p] = tra_escopo($filiais, $ehAdmin);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $w[] = "i.id IN ($in)";
    $p = array_merge($p, $ids);

    $st = $pdo->prepare('SELECT i.* ' . TRA_FROM . ' WHERE ' . implode(' AND ', $w));
    $st->execute($p);
    $linhas = $st->fetchAll(PDO::FETCH_ASSOC);

    $ok = 0; $falhas = 0; $ignorados = count($ids) - count($linhas);
    $aps = [];

    foreach ($linhas as $insc) {
        if ($insc['status'] === 'cancelado' || trim((string)$insc['email']) === '') {
            $ignorados++;
            continue;
        }
        $apId = (int)$insc['apresentacao_id'];
        if (!array_key_exists($apId, $aps)) {
            $aps[$apId] = tr_apresentacao($pdo, $apId);
        }
        if (!$aps[$apId]) { $ignorados++; continue; }

        $reenvio = !empty($insc['enviado_em']);
        if (!trm_email_convite($pdo, $insc, $aps[$apId], $reenvio)) {
            $falhas++;
            continue;
        }
        $up = $pdo->prepare(
            'UPDATE treinamentos_inscricoes SET enviado_em = NOW(), envios = envios + 1 WHERE id = ? LIMIT 1'
        );
        $up->execute([(int)$insc['id']]);
        trm_log($pdo, (int)$insc['id'], $reenvio ? 'link_reenviado' : 'link_enviado', (string)$insc['email']);
        $ok++;
    }

    if ($ok === 0) {
        nv_json(['ok' => false, 'msg' => $falhas > 0
            ? 'Não foi possível enviar o e-mail.'
            : 'Nenhuma matrícula apta ao envio (canceladas ou sem e-mail).'], 422);
    }

    $msg = $ok === 1 ? 'Link enviado por e-mail.' : $ok . ' links enviados por e-mail.';
    if ($falhas > 0)    { $msg .= ' ' . $falhas . ' falha(s) no envio.'; }
    if ($ignorados > 0) { $msg .= ' ' . $ignorados . ' ignorada(s).'; }

    nv_json(['ok' => true, 'msg' => $msg, 'enviados' => $ok]);
}

trm_cancelar_desligados($pdo);

$fTrein  = (int)($_GET['t'] ?? 0);
$fFilial = (int)($_GET['f'] ?? 0);
if (!isset($filiais[$fFilial])) { $fFilial = 0; }
$fStatus = (string)($_GET['s'] ?? '');
if (!in_array($fStatus, ['', 'pendente', 'em_andamento', 'concluido', 'atrasado', 'cancelado'], true)) { $fStatus = ''; }
$busca   = trim((string)($_GET['q'] ?? ''));
$pagina  = max(1, (int)($_GET['p'] ?? 1));
$porPag  = 50;

function tra_url(array $atual, array $troca = []): string
{
    $q = array_filter(array_merge($atual, $troca), static fn($v) => $v !== '' && $v !== 0 && $v !== null);
    return tr_url('acompanhamento.php') . ($q ? '?' . http_build_query($q) : '');
}
$filtrosAtuais = ['t' => $fTrein, 'f' => $fFilial, 's' => $fStatus, 'q' => $busca];

[$wBase, $pBase] = tra_escopo($filiais, $ehAdmin, $fFilial);

$treinamentos = [];
if (!$semFilial) {
    $st = $pdo->prepare(
        'SELECT a.id, a.titulo, a.status AS ap_status,
                COUNT(*) total,
                SUM(i.status = "concluido") concluidos,
                SUM(i.status IN ("pendente","em_andamento") AND i.prazo IS NOT NULL AND i.prazo < CURDATE()) atrasados
         ' . TRA_FROM . '
         WHERE i.status <> "cancelado"' . ($wBase ? ' AND ' . implode(' AND ', $wBase) : '') . '
         GROUP BY a.id, a.titulo, a.status
         ORDER BY a.titulo'
    );
    $st->execute($pBase);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $treinamentos[(int)$r['id']] = $r;
    }
}
$apSel = ($fTrein > 0) ? tr_apresentacao($pdo, $fTrein) : null;
if ($fTrein > 0 && !$apSel) { $fTrein = 0; $filtrosAtuais['t'] = 0; }

$wKpi = $wBase; $pKpi = $pBase;
if ($fTrein > 0) { $wKpi[] = 'i.apresentacao_id = ?'; $pKpi[] = $fTrein; }

$kpi = [];
if (!$semFilial) {
    $st = $pdo->prepare(
        'SELECT COUNT(*) total,
                SUM(i.status = "pendente") pendentes,
                SUM(i.status = "em_andamento") andamento,
                SUM(i.status = "concluido") concluidos,
                SUM(i.status IN ("pendente","em_andamento") AND i.prazo IS NOT NULL AND i.prazo < CURDATE()) atrasados,
                SUM(i.email = "") sem_email,
                AVG(i.percentual) media
         ' . TRA_FROM . '
         WHERE i.status <> "cancelado"' . ($wKpi ? ' AND ' . implode(' AND ', $wKpi) : '')
    );
    $st->execute($pKpi);
    $kpi = $st->fetch(PDO::FETCH_ASSOC) ?: [];
}
$kTotal = (int)($kpi['total'] ?? 0);
$kConc  = (int)($kpi['concluidos'] ?? 0);
$taxa   = $kTotal > 0 ? (int)round($kConc / $kTotal * 100) : 0;

$wLista = $wKpi; $pLista = $pKpi;
switch ($fStatus) {
    case 'pendente':
    case 'em_andamento':
    case 'concluido':
    case 'cancelado':
        $wLista[] = 'i.status = ?'; $pLista[] = $fStatus; break;
    case 'atrasado':
        $wLista[] = 'i.status IN ("pendente","em_andamento") AND i.prazo IS NOT NULL AND i.prazo < CURDATE()'; break;
    default:
        $wLista[] = 'i.status <> "cancelado"';
}
if ($busca !== '') {
    $wLista[] = '(i.nome LIKE ? OR i.matricula LIKE ? OR i.cargo LIKE ? OR i.filial LIKE ?)';
    $like = '%' . $busca . '%';
    array_push($pLista, $like, $like, $like, $like);
}
$sqlW = ' WHERE ' . implode(' AND ', $wLista);

$totalLista = 0; $lista = [];
if (!$semFilial) {
    $st = $pdo->prepare('SELECT COUNT(*) ' . TRA_FROM . $sqlW);
    $st->execute($pLista);
    $totalLista = (int)$st->fetchColumn();

    $paginas = max(1, (int)ceil($totalLista / $porPag));
    $pagina  = min($pagina, $paginas);
    $offset  = ($pagina - 1) * $porPag;

    $st = $pdo->prepare(
        'SELECT i.id, i.apresentacao_id, i.nome, i.matricula, i.email, i.cargo, i.filial, i.status,
                i.percentual, i.slides_vistos, i.slides_total, i.tempo_seg, i.acessos, i.prazo,
                i.ultimo_acesso_em, i.enviado_em, i.envios, i.certificado_codigo, i.concluido_em,
                i.prova_percentual, i.prova_aprovado, i.prova_tentativas_feitas,
                a.titulo
         ' . TRA_FROM . $sqlW . '
         ORDER BY FIELD(i.status,"em_andamento","pendente","concluido","cancelado"),
                  (i.prazo IS NULL), i.prazo, i.nome
         LIMIT ' . $porPag . ' OFFSET ' . $offset
    );
    $st->execute($pLista);
    $lista = $st->fetchAll(PDO::FETCH_ASSOC);
}
$paginas = max(1, (int)ceil($totalLista / $porPag));
$hoje = date('Y-m-d');

$mostrarProva = false;
foreach ($lista as $r) {
    if ((int)$r['prova_tentativas_feitas'] > 0) { $mostrarProva = true; break; }
}

require_once __DIR__ . '/../../../app/layout/header.php';
nv_render_header('Acompanhamento de treinamentos');
?>
<link rel="stylesheet" href="<?= h(tr_url('assets/css/admin.css')) ?>">
<link rel="stylesheet" href="<?= h(tr_url('assets/css/gestao.css')) ?>">
<link rel="stylesheet" href="<?= h(tr_url('assets/css/matriculas.css')) ?>">
<style>
.tra{display:flex;flex-direction:column;gap:16px;padding:4px 0 40px}

.tra-treinos{background:#fff;border:1px solid var(--tr-borda);border-radius:var(--tr-radius);padding:12px 14px 0}
.tra-treinos-head{display:flex;align-items:center;gap:12px;padding-bottom:10px}
.tra-treinos-head .trg-secao{margin:0;flex:0 0 auto;white-space:nowrap}
.tra-treinos-head input{font-size:13px;max-width:230px;margin-left:auto}
.tra-lista-wrap{position:relative;border-top:1px solid var(--tr-borda)}
.tra-lista-wrap::after{content:'';position:absolute;top:0;right:0;bottom:0;width:32px;background:linear-gradient(90deg,rgba(255,255,255,0),#fff 75%);pointer-events:none}
.tra-lista{display:flex;overflow-x:auto;scrollbar-width:none}
.tra-lista::-webkit-scrollbar{display:none}
.tra-item{flex:0 0 auto;display:flex;align-items:center;gap:8px;padding:11px 16px;font-size:13px;font-weight:600;color:var(--tr-txt-dim);text-decoration:none;white-space:nowrap;border-bottom:2px solid transparent}
.tra-item:hover{color:var(--tr-txt);background:var(--tr-bg-suave)}
.tra-item.is-ativo{color:var(--tr-acento);border-bottom-color:var(--tr-acento)}
.tra-item.is-todos{font-weight:700}
.tra-item-nome{max-width:220px;overflow:hidden;text-overflow:ellipsis}
.tra-item-tag{font-size:10px;font-weight:600;color:var(--tr-txt-dim);background:#eef2f7;border-radius:5px;padding:1px 6px}
.tra-item-pct{font-size:11.5px;font-weight:600;color:var(--tr-txt-dim)}
.tra-item.is-ativo .tra-item-pct{color:var(--tr-acento)}
.tra-item-badge{font-size:10.5px;font-weight:700;color:var(--tr-txt-dim);background:#eef2f7;border-radius:99px;padding:1px 7px}
.tra-item.is-ativo .tra-item-badge{background:#e4edf9;color:var(--tr-acento)}
.tra-item-alerta{font-size:10.5px;font-weight:700;color:#fff;background:var(--tr-perigo);border-radius:99px;padding:1px 7px;min-width:18px;text-align:center}
.tra-vazio-lat{padding:10px 6px;font-size:12.5px;color:var(--tr-txt-dim)}
@media (max-width:767px){
  .tra-treinos-head{flex-wrap:wrap}
  .tra-treinos-head input{margin-left:0;max-width:none;width:100%}
}
.tra-principal{display:flex;flex-direction:column;gap:16px;min-width:0}
.tra-selecionado{display:flex;align-items:center;gap:10px;font-size:14px}
.tra-selecionado i{color:var(--tr-acento);font-size:17px}
.tra-selecionado b{font-weight:600}
.tra-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.tra-toolbar .tr-filtros{display:flex;align-items:center;gap:10px;flex-wrap:wrap;width:100%}
.tra-toolbar .tr-campo-busca{flex:1 1 320px;min-width:220px}
.tra-toolbar .tr-campo-busca input{width:100%;min-width:0}
.tra-toolbar .form-select{max-width:220px}
.tra-lote{display:none;align-items:center;gap:10px;background:#f1f6fc;border:1px solid #c9d8ea;border-radius:10px;padding:8px 12px;font-size:13px}
.tra-lote.is-visivel{display:flex}
.tra-lote b{font-weight:600}
.tr-tabela-wrap{background:#fff;border:1px solid var(--tr-borda);border-radius:var(--tr-radius);overflow:hidden}
.tra-tab td,.tra-tab th{vertical-align:middle}
.tra-tab thead th{position:sticky;top:0;z-index:1;background:#fafbfc;color:var(--tr-txt-dim);font-size:12px;text-transform:uppercase;letter-spacing:.02em;font-weight:600;border-bottom:1px solid var(--tr-borda)}
.tra-tab tbody tr{transition:background .1s ease}
.tra-tab tbody tr:hover{background:var(--tr-bg-suave)}
.tra-tab tbody tr.is-cancelada:hover{background:#f7f7f7}
.tra-tab .tra-ck{width:34px}
.tra-tab input[type=checkbox]{width:15px;height:15px;accent-color:var(--tr-navy);cursor:pointer}
.tra-trein{font-size:12.5px;max-width:220px;line-height:1.35}
.tra-env{font-size:11px;color:var(--tr-txt-dim);display:block}
.tra-pag{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 14px;border-top:1px solid var(--tr-borda);font-size:12.5px;color:var(--tr-txt-dim)}
.tra-pag .pagination{margin:0}
.tra-vazio-lat{padding:20px 12px;text-align:center;font-size:12.5px;color:var(--tr-txt-dim)}
</style>

<?php if ($semFilial): ?>
  <div class="trm-alerta">
    <i class="bi bi-building-exclamation"></i>
    <div>
      <strong>Usuário sem filial vinculada</strong>
      Sua conta não está associada a nenhuma filial. Solicite ao RH o vínculo para acompanhar os treinamentos.
    </div>
  </div>
<?php else: ?>

<div class="tra">

  <section class="tra-treinos">
    <div class="tra-treinos-head">
      <div class="trg-secao"><i class="bi bi-mortarboard"></i> Treinamentos</div>
      <input type="search" class="form-control form-control-sm" id="traBuscaTrein" placeholder="Filtrar treinamentos">
    </div>
    <div class="tra-lista" id="traListaTrein">
      <a class="tra-item is-todos <?= $fTrein === 0 ? 'is-ativo' : '' ?>" href="<?= h(tra_url($filtrosAtuais, ['t' => 0, 'p' => 0])) ?>">
        <div class="tra-item-tit"><span>Todos os treinamentos</span><em><?= count($treinamentos) ?></em></div>
      </a>
      <?php if (!$treinamentos): ?>
        <div class="tra-vazio-lat">Nenhum treinamento com inscrições nas suas filiais.</div>
      <?php endif; ?>
      <?php foreach ($treinamentos as $tid => $t):
        $tt  = (int)$t['total'];
        $tc  = (int)$t['concluidos'];
        $ta  = (int)$t['atrasados'];
        $pct = $tt > 0 ? (int)round($tc / $tt * 100) : 0;
      ?>
        <a class="tra-item <?= $fTrein === $tid ? 'is-ativo' : '' ?>" data-titulo="<?= h(mb_strtolower((string)$t['titulo'])) ?>"
           title="<?= h($t['titulo']) ?>" href="<?= h(tra_url($filtrosAtuais, ['t' => $tid, 'p' => 0])) ?>">
          <div class="tra-item-tit">
            <span><?= h($t['titulo']) ?></span>
            <?php if ($t['ap_status'] === 'arquivado'): ?><em>arquivado</em><?php endif; ?>
          </div>
          <div class="tra-item-barra"><i style="width:<?= $pct ?>%"></i></div>
          <div class="tra-item-meta">
            <span><?= $tt ?> inscrito<?= $tt === 1 ? '' : 's' ?></span>
            <span><?= $pct ?>% concluído</span>
            <?php if ($ta > 0): ?><span class="is-alerta"><?= $ta ?> atrasado<?= $ta === 1 ? '' : 's' ?></span><?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="tra-principal">

    <?php if ($apSel): ?>
      <div class="tra-selecionado">
        <i class="bi bi-journal-check"></i>
        <b><?= h($apSel['titulo']) ?></b>
        <a class="btn btn-link btn-sm" href="<?= h(tra_url($filtrosAtuais, ['t' => 0, 'p' => 0])) ?>">ver todos</a>
      </div>
    <?php endif; ?>

    <section class="trg-painel trm-panorama">
      <div class="trm-taxa">
        <div class="trm-taxa-anel" style="--pct:<?= $taxa ?>">
          <b><?= $taxa ?>%</b>
          <small>concluíram</small>
        </div>
        <span class="trm-taxa-leg"><?= $kConc ?> de <?= $kTotal ?> matrículas</span>
      </div>
      <div class="trm-numeros">
        <div class="trm-num"><b><?= $kTotal ?></b><small>Inscritos</small></div>
        <div class="trm-num"><b><?= (int)($kpi['pendentes'] ?? 0) ?></b><small>Não iniciaram</small></div>
        <div class="trm-num"><b><?= (int)($kpi['andamento'] ?? 0) ?></b><small>Em andamento</small></div>
        <div class="trm-num"><b><?= (int)round((float)($kpi['media'] ?? 0)) ?>%</b><small>Progresso médio</small></div>
        <div class="trm-num <?= (int)($kpi['atrasados'] ?? 0) > 0 ? 'is-alerta' : '' ?>">
          <b><?= (int)($kpi['atrasados'] ?? 0) ?></b><small>Prazo vencido</small>
        </div>
        <?php if ((int)($kpi['sem_email'] ?? 0) > 0): ?>
          <div class="trm-num is-alerta"><b><?= (int)$kpi['sem_email'] ?></b><small>Sem e-mail</small></div>
        <?php endif; ?>
      </div>
    </section>

    <div class="tra-toolbar">
      <form class="tr-filtros" method="get" action="">
        <?php if ($fTrein > 0): ?><input type="hidden" name="t" value="<?= $fTrein ?>"><?php endif; ?>
        <div class="tr-campo-busca">
          <i class="bi bi-search"></i>
          <input type="text" name="q" class="form-control" placeholder="Nome, matrícula, cargo ou filial" value="<?= h($busca) ?>">
        </div>
        <?php if (count($filiais) > 1): ?>
          <select name="f" class="form-select">
            <option value="">Todas as filiais</option>
            <?php foreach ($filiais as $fid => $fnome): ?>
              <option value="<?= $fid ?>" <?= $fFilial === $fid ? 'selected' : '' ?>><?= h($fnome) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
        <select name="s" class="form-select">
          <option value="">Todas as situações</option>
          <option value="pendente"     <?= $fStatus === 'pendente'     ? 'selected' : '' ?>>Não iniciados</option>
          <option value="em_andamento" <?= $fStatus === 'em_andamento' ? 'selected' : '' ?>>Em andamento</option>
          <option value="concluido"    <?= $fStatus === 'concluido'    ? 'selected' : '' ?>>Concluídos</option>
          <option value="atrasado"     <?= $fStatus === 'atrasado'     ? 'selected' : '' ?>>Prazo vencido</option>
          <option value="cancelado"    <?= $fStatus === 'cancelado'    ? 'selected' : '' ?>>Cancelados</option>
        </select>
        <button type="submit" class="btn btn-outline-secondary">Filtrar</button>
        <?php if ($busca !== '' || $fStatus !== '' || $fFilial > 0): ?>
          <a href="<?= h(tra_url(['t' => $fTrein])) ?>" class="btn btn-link btn-sm">Limpar</a>
        <?php endif; ?>
      </form>
    </div>

    <?php if ($podeReenviar): ?>
      <div class="tra-lote" id="traLote">
        <i class="bi bi-check2-square"></i>
        <span><b id="traLoteQtd">0</b> selecionada(s)</span>
        <button type="button" class="btn btn-sm tr-btn-primaria ms-auto" id="traLoteEnviar">
          <i class="bi bi-envelope-arrow-up"></i> Reenviar links por e-mail
        </button>
        <button type="button" class="btn btn-sm tr-btn-ghost" id="traLoteLimpar">Limpar</button>
      </div>
    <?php endif; ?>

    <?php if (!$lista): ?>
      <div class="tr-vazio">
        <i class="bi bi-people"></i>
        <p>Nenhuma matrícula encontrada<?= ($busca !== '' || $fStatus !== '' || $fFilial > 0) ? ' com esses filtros' : '' ?>.</p>
      </div>
    <?php else: ?>
      <div class="tr-tabela-wrap">
        <div class="table-responsive">
          <table class="table tr-tabela trm-tabela tra-tab">
            <thead>
              <tr>
                <?php if ($podeReenviar): ?>
                  <th class="tra-ck"><input type="checkbox" id="traCkTodos" title="Selecionar todos"></th>
                <?php endif; ?>
                <th>Colaborador</th>
                <?php if ($fTrein === 0): ?><th>Treinamento</th><?php endif; ?>
                <th>Lotação</th>
                <th style="min-width:170px">Progresso</th>
                <th>Situação</th>
                <?php if ($mostrarProva): ?><th>Prova</th><?php endif; ?>
                <th>Prazo</th>
                <th>Último acesso</th>
                <?php if ($podeReenviar): ?><th class="text-end">Link</th><?php endif; ?>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($lista as $r):
              $pct    = (int)$r['percentual'];
              $mat    = (string)($r['matricula'] ?? '');
              $email  = trim((string)$r['email']);
              $cancel = $r['status'] === 'cancelado';
              $apto   = !$cancel && $email !== '';
              $atras  = !empty($r['prazo']) && $r['prazo'] < $hoje && in_array($r['status'], ['pendente', 'em_andamento'], true);
            ?>
              <tr class="<?= $cancel ? 'is-cancelada' : '' ?>">
                <?php if ($podeReenviar): ?>
                  <td class="tra-ck">
                    <input type="checkbox" class="tra-ck-item" value="<?= (int)$r['id'] ?>" <?= $apto ? '' : 'disabled' ?>>
                  </td>
                <?php endif; ?>
                <td>
                  <div class="trm-pessoa">
                    <span class="trm-foto">
                      <?php if ($mat !== '' && function_exists('nv_colab_foto_url')): ?>
                        <img src="<?= h(nv_colab_foto_url($mat)) ?>" alt="" loading="lazy" onerror="this.remove()">
                      <?php endif; ?>
                      <i class="bi bi-person"></i>
                    </span>
                    <span class="trm-pessoa-txt">
                      <strong><?= h($r['nome']) ?></strong>
                      <small class="d-block">
                        <?php if ($mat !== ''): ?><em class="trm-mat">#<?= h($mat) ?></em><?php endif; ?>
                        <?php if ($email === ''): ?>
                          <span class="trm-sem-email"><i class="bi bi-envelope-slash"></i> sem e-mail</span>
                        <?php elseif ($podeContato): ?>
                          <span class="text-muted"><?= h($email) ?></span>
                        <?php endif; ?>
                      </small>
                    </span>
                  </div>
                </td>
                <?php if ($fTrein === 0): ?>
                  <td><div class="tra-trein"><?= h($r['titulo']) ?></div></td>
                <?php endif; ?>
                <td>
                  <div class="trm-lotacao">
                    <span><?= h($r['cargo'] ?: '—') ?></span>
                    <small><?= h($r['filial'] ?: '—') ?></small>
                  </div>
                </td>
                <td>
                  <div class="tr-mini-barra"><i style="width:<?= $pct ?>%"></i></div>
                  <small class="text-muted">
                    <?= $pct ?>% · <?= (int)$r['slides_vistos'] ?>/<?= (int)$r['slides_total'] ?> slides
                    <?php if ((int)$r['tempo_seg'] > 0): ?> · <?= h(trm_tempo_fmt((int)$r['tempo_seg'])) ?><?php endif; ?>
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
                  <?php if ($r['status'] === 'concluido' && (string)$r['certificado_codigo'] !== ''): ?>
                    <a class="trm-cert" href="<?= h(trm_link_certificado((string)$r['certificado_codigo'])) ?>" target="_blank" rel="noopener"
                       title="Ver certificado"><i class="bi bi-award"></i> certificado</a>
                  <?php elseif ($r['status'] === 'concluido' && !empty($r['concluido_em'])): ?>
                    <small class="d-block text-muted"><?= h(nv_fmt_dt($r['concluido_em'])) ?></small>
                  <?php endif; ?>
                </td>
                <?php if ($mostrarProva): ?>
                  <td>
                    <?php if ((int)$r['prova_aprovado'] === 1): ?>
                      <span class="trm-nota is-ok"><?= (int)$r['prova_percentual'] ?>%</span>
                    <?php elseif ((int)$r['prova_tentativas_feitas'] > 0): ?>
                      <span class="trm-nota is-baixa"><?= (int)$r['prova_percentual'] ?>%</span>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                    <?php if ((int)$r['prova_tentativas_feitas'] > 0): ?>
                      <small class="d-block text-muted"><?= (int)$r['prova_tentativas_feitas'] ?> tentativa(s)</small>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
                <td>
                  <?php if (!empty($r['prazo'])): ?>
                    <span class="<?= $atras ? 'trm-atrasado' : '' ?>"><?= h(date('d/m/Y', strtotime((string)$r['prazo']))) ?></span>
                    <?php if ($atras): ?><small class="d-block trm-atrasado">vencido</small><?php endif; ?>
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
                <?php if ($podeReenviar): ?>
                  <td class="text-end">
                    <?php if ($apto): ?>
                      <button type="button" class="trm-ico tra-enviar" data-id="<?= (int)$r['id'] ?>"
                              data-nome="<?= h($r['nome']) ?>"
                              title="<?= empty($r['enviado_em']) ? 'Enviar link por e-mail' : 'Reenviar link por e-mail' ?>">
                        <i class="bi bi-<?= empty($r['enviado_em']) ? 'envelope' : 'envelope-arrow-up' ?>"></i>
                      </button>
                    <?php endif; ?>
                    <?php if (!empty($r['enviado_em'])): ?>
                      <span class="tra-env" title="<?= (int)$r['envios'] ?> envio(s)">enviado <?= h(nv_fmt_date($r['enviado_em'])) ?></span>
                    <?php elseif ($apto): ?>
                      <span class="tra-env">não enviado</span>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="tra-pag">
          <span><?= $totalLista ?> matrícula<?= $totalLista === 1 ? '' : 's' ?></span>
          <?php if ($paginas > 1): ?>
            <nav>
              <ul class="pagination pagination-sm">
                <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                  <a class="page-link" href="<?= h(tra_url($filtrosAtuais, ['p' => $pagina - 1])) ?>"><i class="bi bi-chevron-left"></i></a>
                </li>
                <?php
                  $ini = max(1, $pagina - 2);
                  $fim = min($paginas, $pagina + 2);
                  for ($n = $ini; $n <= $fim; $n++):
                ?>
                  <li class="page-item <?= $n === $pagina ? 'active' : '' ?>">
                    <a class="page-link" href="<?= h(tra_url($filtrosAtuais, ['p' => $n])) ?>"><?= $n ?></a>
                  </li>
                <?php endfor; ?>
                <li class="page-item <?= $pagina >= $paginas ? 'disabled' : '' ?>">
                  <a class="page-link" href="<?= h(tra_url($filtrosAtuais, ['p' => $pagina + 1])) ?>"><i class="bi bi-chevron-right"></i></a>
                </li>
              </ul>
            </nav>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>
<?php endif; ?>

<script>
  window.TR_CSRF = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window.TR_API  = <?= json_encode(tr_url('acompanhamento.php'), JSON_HEX_TAG) ?>;
</script>
<script src="<?= h(tr_url('assets/js/ui.js')) ?>"></script>
<script>
(function () {
  'use strict';

  var busca = document.getElementById('traBuscaTrein');
  if (busca) {
    busca.addEventListener('input', function () {
      var termo = busca.value.trim().toLowerCase();
      document.querySelectorAll('#traListaTrein .tra-item[data-titulo]').forEach(function (a) {
        a.style.display = (!termo || a.dataset.titulo.indexOf(termo) !== -1) ? '' : 'none';
      });
    });
  }

  function reenviar(ids, texto, btn) {
    return window.trConfirmar({
      titulo: 'Reenviar link',
      texto: texto,
      confirmar: 'Enviar'
    }).then(function (sim) {
      if (!sim) { return; }
      if (btn) { btn.disabled = true; }
      return window.trApi('reenviar', { ids: ids })
        .then(function (j) {
          window.trToast(j.msg);
          setTimeout(function () { window.location.reload(); }, 1300);
        })
        .catch(function (e) {
          if (btn) { btn.disabled = false; }
          window.trToast(e.message, 'erro');
        });
    });
  }

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('.tra-enviar');
    if (!b) { return; }
    reenviar([parseInt(b.dataset.id, 10)],
      'Enviar o link de acesso para o e-mail de ' + b.dataset.nome + '?', b);
  });

  var lote    = document.getElementById('traLote');
  var qtd     = document.getElementById('traLoteQtd');
  var todos   = document.getElementById('traCkTodos');
  var itens   = Array.prototype.slice.call(document.querySelectorAll('.tra-ck-item:not(:disabled)'));

  function selecionados() {
    return itens.filter(function (c) { return c.checked; }).map(function (c) { return parseInt(c.value, 10); });
  }
  function atualizar() {
    if (!lote) { return; }
    var n = selecionados().length;
    qtd.textContent = n;
    lote.classList.toggle('is-visivel', n > 0);
    if (todos) {
      todos.checked = n > 0 && n === itens.length;
      todos.indeterminate = n > 0 && n < itens.length;
    }
  }
  itens.forEach(function (c) { c.addEventListener('change', atualizar); });
  if (todos) {
    todos.disabled = itens.length === 0;
    todos.addEventListener('change', function () {
      itens.forEach(function (c) { c.checked = todos.checked; });
      atualizar();
    });
  }
  var limpar = document.getElementById('traLoteLimpar');
  if (limpar) {
    limpar.addEventListener('click', function () {
      itens.forEach(function (c) { c.checked = false; });
      atualizar();
    });
  }
  var enviarLote = document.getElementById('traLoteEnviar');
  if (enviarLote) {
    enviarLote.addEventListener('click', function () {
      var ids = selecionados();
      if (!ids.length) { return; }
      reenviar(ids, 'Enviar o link de acesso por e-mail para ' + ids.length + ' colaborador(es) selecionado(s)?', enviarLote);
    });
  }
})();
</script>

<?php require_once __DIR__ . '/../../../app/layout/footer.php'; ?>
