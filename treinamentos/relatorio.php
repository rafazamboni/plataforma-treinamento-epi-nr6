<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_lib.php';

require_login();
tr_exigir('treinamentos.apresentacoes.relatorios');

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);
$ap  = $id > 0 ? tr_apresentacao($pdo, $id) : null;

if (!$ap) {
    flash_set('danger', 'Apresentação não encontrada.');
    nv_redirect(tr_url('index.php'));
}

$st = $pdo->prepare(
    'SELECT COUNT(*) AS total,
            SUM(CASE WHEN origem = "interno" THEN 1 ELSE 0 END) AS internos,
            SUM(CASE WHEN origem = "publico" THEN 1 ELSE 0 END) AS publicos,
            SUM(CASE WHEN concluido = 1 THEN 1 ELSE 0 END)      AS concluidos
       FROM treinamentos_visualizacoes
      WHERE apresentacao_id = ?'
);
$st->execute([$id]);
$kpi = $st->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'internos' => 0, 'publicos' => 0, 'concluidos' => 0];

$total = (int)$kpi['total'];
$pctConclusao = $total > 0 ? round(((int)$kpi['concluidos'] / $total) * 100) : 0;

$st = $pdo->prepare(
    'SELECT * FROM treinamentos_visualizacoes
      WHERE apresentacao_id = ?
   ORDER BY atualizado_em DESC
      LIMIT 300'
);
$st->execute([$id]);
$views = $st->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../../app/layout/header.php';
nv_render_header('Relatório — ' . $ap['titulo']);
?>
<link rel="stylesheet" href="<?= h(tr_url('assets/css/admin.css')) ?>">

<div class="tr-wrap">

  <div class="tr-toolbar">
    <div class="tr-migalha">
      <a href="<?= h(tr_url('index.php')) ?>"><i class="bi bi-arrow-left"></i> Apresentações</a>
      <span><?= h($ap['titulo']) ?></span>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-sm tr-btn-ghost" href="<?= h(tr_url('player.php')) ?>?id=<?= $id ?>" target="_blank">
        <i class="bi bi-play-fill"></i> Apresentar
      </a>
      <?php if (tr_pode('treinamentos.apresentacoes.editar')): ?>
        <a class="btn btn-sm tr-btn-ghost" href="<?= h(tr_url('editor.php')) ?>?id=<?= $id ?>">
          <i class="bi bi-pencil"></i> Editar
        </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="tr-kpis">
    <div class="tr-kpi"><b><?= $total ?></b><small>Acessos</small></div>
    <div class="tr-kpi"><b><?= (int)$kpi['internos'] ?></b><small>Internos</small></div>
    <div class="tr-kpi"><b><?= (int)$kpi['publicos'] ?></b><small>Pelo link público</small></div>
    <div class="tr-kpi"><b><?= (int)$kpi['concluidos'] ?></b><small>Concluíram</small></div>
    <div class="tr-kpi"><b><?= $pctConclusao ?>%</b><small>Taxa de conclusão</small></div>
  </div>

  <?php if (!$views): ?>
    <div class="tr-vazio"><i class="bi bi-bar-chart"></i><p>Nenhum acesso registrado até agora.</p></div>
  <?php else: ?>
    <div class="tr-tabela-wrap">
      <table class="table tr-tabela align-middle">
        <thead>
          <tr>
            <th>Origem</th>
            <th>Usuário</th>
            <th>Progresso</th>
            <th>Situação</th>
            <th>Início</th>
            <th>Último acesso</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($views as $v): ?>
          <?php
            $tot = max(1, (int)$v['slides_total']);
            $pct = min(100, round(((int)$v['slide_max'] / $tot) * 100));
          ?>
          <tr>
            <td>
              <span class="tr-chip tr-chip-<?= $v['origem'] === 'publico' ? 'publico' : 'interno' ?>">
                <i class="bi bi-<?= $v['origem'] === 'publico' ? 'globe2' : 'building' ?>"></i>
                <?= $v['origem'] === 'publico' ? 'Público' : 'Interno' ?>
              </span>
            </td>
            <td><?= $v['usuario_id'] ? 'Usuário #' . (int)$v['usuario_id'] : '<span class="text-muted">Visitante</span>' ?></td>
            <td>
              <div class="tr-mini-barra"><i style="width:<?= $pct ?>%"></i></div>
              <small class="text-muted"><?= (int)$v['slide_max'] ?> de <?= (int)$v['slides_total'] ?> slides</small>
            </td>
            <td>
              <?php if ((int)$v['concluido'] === 1): ?>
                <span class="tr-chip tr-chip-ok"><i class="bi bi-check-lg"></i> Concluído</span>
              <?php else: ?>
                <span class="tr-chip"><i class="bi bi-hourglass-split"></i> Em andamento</span>
              <?php endif; ?>
            </td>
            <td><?= h(nv_fmt_dt($v['iniciado_em'])) ?></td>
            <td><?= h(nv_fmt_dt($v['atualizado_em'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../../app/layout/footer.php'; ?>
