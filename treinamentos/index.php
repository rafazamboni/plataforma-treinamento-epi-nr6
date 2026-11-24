<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_lib.php';

require_login();
tr_exigir('treinamentos.apresentacoes.ver');

$pdo = db();

$temCapa = false;
try {
    $temCapa = (bool)$pdo->query("SHOW COLUMNS FROM treinamentos_apresentacoes LIKE 'capa'")->fetch();
} catch (Throwable $e) {
    $temCapa = false;
}

$busca  = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? '');
$ordem  = (string)($_GET['ordem'] ?? 'recentes');

$where  = [];
$params = [];
if ($busca !== '') {
    $where[] = '(a.titulo LIKE ? OR a.subtitulo LIKE ? OR a.slug LIKE ?)';
    $like = '%' . $busca . '%';
    array_push($params, $like, $like, $like);
}
if (in_array($status, ['rascunho', 'publicado', 'arquivado'], true)) {
    $where[] = 'a.status = ?';
    $params[] = $status;
}
$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$ordens = [
    'recentes'  => 'a.atualizado_em DESC',
    'titulo'    => 'a.titulo ASC',
    'inscritos' => 'qtd_matriculas DESC, a.atualizado_em DESC',
    'atraso'    => 'qtd_atrasados DESC, a.atualizado_em DESC',
];
$sqlOrdem = $ordens[$ordem] ?? $ordens['recentes'];

$st = $pdo->prepare(
    "SELECT a.*,
            (SELECT COUNT(*) FROM treinamentos_slides s WHERE s.apresentacao_id = a.id) AS qtd_slides,
            (SELECT COUNT(*) FROM treinamentos_visualizacoes v WHERE v.apresentacao_id = a.id) AS qtd_views,
            (SELECT COUNT(*) FROM treinamentos_inscricoes i WHERE i.apresentacao_id = a.id) AS qtd_matriculas,
            (SELECT COUNT(*) FROM treinamentos_inscricoes i
              WHERE i.apresentacao_id = a.id AND i.status = 'concluido') AS qtd_concluidos,
            (SELECT COUNT(*) FROM treinamentos_inscricoes i
              WHERE i.apresentacao_id = a.id AND i.status <> 'cancelado'
                AND i.prazo IS NOT NULL AND i.prazo < CURDATE()
                AND i.status <> 'concluido') AS qtd_atrasados
       FROM treinamentos_apresentacoes a
       $sqlWhere
   ORDER BY $sqlOrdem"
);
$st->execute($params);
$lista = $st->fetchAll(PDO::FETCH_ASSOC);

$resumo = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM treinamentos_apresentacoes WHERE status = 'publicado') pub,
        (SELECT COUNT(*) FROM treinamentos_apresentacoes WHERE status = 'rascunho')  rasc,
        (SELECT COUNT(*) FROM treinamentos_inscricoes WHERE status <> 'cancelado')   matric,
        (SELECT COUNT(*) FROM treinamentos_inscricoes WHERE status = 'concluido')    conc,
        (SELECT COUNT(*) FROM treinamentos_inscricoes
          WHERE status NOT IN ('cancelado','concluido')
            AND prazo IS NOT NULL AND prazo < CURDATE())                             atras"
)->fetch(PDO::FETCH_ASSOC) ?: [];

$totMatric = (int)($resumo['matric'] ?? 0);
$totConc   = (int)($resumo['conc'] ?? 0);
$taxa      = $totMatric > 0 ? (int)round(($totConc / $totMatric) * 100) : 0;

$podeCriar    = tr_pode('treinamentos.apresentacoes.criar');
$podeEditar   = tr_pode('treinamentos.apresentacoes.editar');
$podeExcluir  = tr_pode('treinamentos.apresentacoes.excluir');
$podePublicar = tr_pode('treinamentos.apresentacoes.publicar');
$podeRel      = tr_pode('treinamentos.apresentacoes.relatorios');
$podeMatric   = tr_pode('treinamentos.matriculas.ver') || $podeRel;

$baseUrl   = tr_url_absoluta('ver.php') . '?slug=';
$temFiltro = $busca !== '' || $status !== '' || $ordem !== 'recentes';

function trx_iniciais(string $titulo): string
{
    $partes = preg_split('/\s+/u', trim($titulo)) ?: [];
    $out = '';
    foreach ($partes as $p) {
        $p = preg_replace('/[^\p{L}\p{N}]/u', '', $p);
        if ($p === '') {
            continue;
        }
        $out .= mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8');
        if (mb_strlen($out, 'UTF-8') >= 2) {
            break;
        }
    }
    return $out !== '' ? $out : 'T';
}

require_once __DIR__ . '/../../../app/layout/header.php';
nv_render_header('Treinamentos');
?>
<link rel="stylesheet" href="<?= h(tr_url('assets/css/admin.css')) ?>">

<style>
.trx{
  --x-fundo:#f5f6f8;
  --x-sup:#ffffff;
  --x-tinta:#161d26;
  --x-tinta2:#5b6675;
  --x-fraco:#8a93a1;
  --x-linha:#e3e7ec;
  --x-linha2:#eef1f4;
  --x-acc:#14487f;
  --x-acc-fraco:#eaf1f9;
  --x-ok:#17795e;
  --x-ok-fraco:#e6f3ee;
  --x-alerta:#9a5b00;
  --x-alerta-fraco:#fdf3e3;
  --x-perigo:#b42318;
  --x-perigo-fraco:#fdeceb;
  --x-r:10px;
  color:var(--x-tinta);
  font-variant-numeric:tabular-nums;
  overflow:visible;
}
.trx-row,.trx-acts,.trx-main{overflow:visible}
.trx *{box-sizing:border-box}
.trx :focus-visible{outline:2px solid var(--x-acc);outline-offset:2px;border-radius:6px}

.trx-kpi{
  display:flex;align-items:stretch;flex-wrap:wrap;
  background:var(--x-sup);border:1px solid var(--x-linha);
  border-radius:var(--x-r);margin-bottom:14px;overflow:hidden;
}
.trx-kpi > *{
  display:flex;align-items:baseline;gap:7px;
  padding:11px 18px;border-right:1px solid var(--x-linha2);
  text-decoration:none;color:inherit;line-height:1.1;
}
.trx-kpi > *:last-child{border-right:0}
.trx-kpi b{font-size:1.05rem;font-weight:650}
.trx-kpi span{font-size:.795rem;color:var(--x-tinta2)}
.trx-kpi-taxa{gap:10px}
.trx-kpi-barra{
  position:relative;width:64px;height:5px;border-radius:3px;
  background:var(--x-linha);overflow:hidden;align-self:center;
}
.trx-kpi-barra i{position:absolute;inset:0 auto 0 0;background:var(--x-ok);border-radius:3px}
.trx-kpi-alerta{background:var(--x-perigo-fraco);color:var(--x-perigo);margin-left:auto;border-right:0}
.trx-kpi-alerta b{color:var(--x-perigo)}
.trx-kpi-alerta span{color:var(--x-perigo);opacity:.85}
.trx-kpi-alerta:hover{filter:brightness(.97)}

.trx-cmd{
  display:flex;align-items:center;gap:10px;flex-wrap:wrap;
  padding:10px 12px;margin-bottom:14px;
  background:var(--x-sup);border:1px solid var(--x-linha);border-radius:var(--x-r);
}
.trx-busca{position:relative;flex:1 1 260px;min-width:200px}
.trx-busca i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--x-fraco);font-size:.9rem}
.trx-busca input{
  width:100%;height:36px;padding:0 12px 0 32px;font-size:.875rem;
  border:1px solid var(--x-linha);border-radius:8px;background:#fbfcfd;color:var(--x-tinta);
}
.trx-busca input:focus{background:#fff;border-color:var(--x-acc);outline:none;box-shadow:0 0 0 3px var(--x-acc-fraco)}
.trx-abas{display:flex;gap:2px;padding:3px;background:#f1f3f6;border-radius:9px}
.trx-aba{
  padding:6px 12px;font-size:.82rem;border-radius:6px;
  color:var(--x-tinta2);text-decoration:none;white-space:nowrap;
}
.trx-aba:hover{color:var(--x-tinta)}
.trx-aba.is-ativa{background:var(--x-sup);color:var(--x-tinta);font-weight:600;box-shadow:0 1px 2px rgba(22,29,38,.08)}
.trx-sel{
  height:36px;padding:0 30px 0 11px;font-size:.845rem;
  border:1px solid var(--x-linha);border-radius:8px;background:#fbfcfd;color:var(--x-tinta2);
  appearance:none;
  background-image:url("data:image/svg+xml;charset=utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%238a93a1'%3E%3Cpath d='M4 6l4 4 4-4z'/%3E%3C/svg%3E");
  background-repeat:no-repeat;background-position:right 9px center;background-size:14px;
}
.trx-cmd-dir{display:flex;align-items:center;gap:8px;margin-left:auto}
.trx-sep{width:1px;height:24px;background:var(--x-linha)}
.trx-chip{
  display:inline-flex;align-items:center;gap:6px;height:36px;padding:0 12px;
  font-size:.82rem;color:var(--x-tinta2);text-decoration:none;white-space:nowrap;
  border:1px solid var(--x-linha);border-radius:8px;background:var(--x-sup);cursor:pointer;
}
.trx-chip:hover{border-color:#cfd6de;color:var(--x-tinta)}
.trx-chip[aria-pressed="true"]{background:var(--x-acc-fraco);border-color:#c3d7ec;color:var(--x-acc);font-weight:600}
.trx-novo{
  display:inline-flex;align-items:center;gap:7px;height:36px;padding:0 15px;
  font-size:.845rem;font-weight:600;color:#fff;background:var(--x-acc);
  border:1px solid var(--x-acc);border-radius:8px;cursor:pointer;
}
.trx-novo:hover{background:#0f3a68}

.trx-lista-topo{
  display:flex;align-items:center;gap:12px;
  padding:0 4px 8px;font-size:.8rem;color:var(--x-tinta2);
}
.trx-limpar{color:var(--x-acc);text-decoration:none;display:inline-flex;align-items:center;gap:5px}
.trx-limpar:hover{text-decoration:underline}

.trx-lista{background:var(--x-sup);border:1px solid var(--x-linha);border-radius:var(--x-r);overflow:visible}
.trx .trx-lista.trh-grade{display:block;grid-template-columns:none;gap:0;padding:0;margin:0;overflow:visible}
.trx-row{
  display:grid;
  grid-template-columns:124px minmax(0,1fr) 268px auto;
  gap:18px;align-items:center;
  padding:14px 16px;border-top:1px solid var(--x-linha2);
  transition:background .12s ease;
}
.trx-row:first-child{border-top:0;border-radius:var(--x-r) var(--x-r) 0 0}
.trx-row:last-child{border-radius:0 0 var(--x-r) var(--x-r)}
.trx-row:only-child{border-radius:var(--x-r)}
.trx-row:hover{background:#fafbfc}
.trx-row:has(.trx-pop.is-aberto){position:relative;z-index:70}
.trx[data-capas="0"] .trx-capa{display:none}
.trx[data-stats="0"] .trx-stats{display:none}
.trx[data-capas="0"] .trx-row{grid-template-columns:minmax(0,1fr) 268px auto}
.trx[data-stats="0"] .trx-row{grid-template-columns:124px minmax(0,1fr) auto}
.trx[data-capas="0"][data-stats="0"] .trx-row{grid-template-columns:minmax(0,1fr) auto}

.trx-capa{position:relative;width:124px;height:78px;border-radius:8px;overflow:hidden;background:#eef1f4}
.trx-capa img{width:100%;height:100%;object-fit:cover;display:block}
.trx-capa-vazia{
  width:100%;height:100%;display:flex;align-items:center;justify-content:center;
  font-size:1.5rem;font-weight:600;letter-spacing:.02em;color:rgba(255,255,255,.92);
}
.trx-capa-btn{
  position:absolute;inset:0;display:flex;align-items:center;justify-content:center;gap:6px;
  border:0;background:rgba(22,29,38,.62);color:#fff;font-size:.74rem;
  opacity:0;cursor:pointer;transition:opacity .12s ease;
}
.trx-capa:hover .trx-capa-btn,.trx-capa-btn:focus-visible{opacity:1}
.trx-capa.is-enviando .trx-capa-btn{opacity:1}

.trx-main{min-width:0}
.trx-tags{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:5px}
.trx-est{
  display:inline-flex;align-items:center;gap:5px;padding:2px 8px;
  font-size:.71rem;font-weight:600;border-radius:20px;
}
.trx-est::before{content:"";width:5px;height:5px;border-radius:50%;background:currentColor}
.trx-est-publicado{background:var(--x-ok-fraco);color:var(--x-ok)}
.trx-est-rascunho{background:#f1f3f6;color:var(--x-tinta2)}
.trx-est-arquivado{background:#f1f3f6;color:var(--x-fraco)}
.trx-tag{
  display:inline-flex;align-items:center;gap:4px;padding:2px 7px;
  font-size:.71rem;color:var(--x-tinta2);background:#f5f6f8;
  border:1px solid var(--x-linha);border-radius:5px;
}
.trx-titulo{margin:0;font-size:.985rem;font-weight:600;line-height:1.3}
.trx-titulo a{color:var(--x-tinta);text-decoration:none}
.trx-titulo a:hover{color:var(--x-acc)}
.trx-sub{
  margin:3px 0 0;font-size:.825rem;color:var(--x-tinta2);line-height:1.4;
  max-width:62ch;overflow:hidden;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;
}
.trx-meta{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:7px;font-size:.775rem;color:var(--x-fraco)}
.trx-meta span{display:inline-flex;align-items:center;gap:5px}
.trx-aviso{display:inline-flex;align-items:center;gap:5px;padding:1px 7px;border-radius:5px;font-weight:600}
.trx-aviso.is-atraso{background:var(--x-perigo-fraco);color:var(--x-perigo)}
.trx-aviso.is-vazio{background:var(--x-alerta-fraco);color:var(--x-alerta)}
.trx-publico{
  display:inline-flex;align-items:center;gap:6px;margin-top:8px;max-width:100%;
  padding:4px 9px;font-size:.755rem;color:var(--x-tinta2);
  background:#f7f9fb;border:1px solid var(--x-linha);border-radius:6px;cursor:pointer;
}
.trx-publico span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:34ch}
.trx-publico:hover{border-color:#c3d7ec;color:var(--x-acc)}

.trx-stats{border-left:1px solid var(--x-linha2);padding-left:18px}
.trx-nums{display:grid;grid-template-columns:repeat(3,1fr);gap:4px;margin-bottom:9px}
.trx-num{display:block;text-decoration:none;color:inherit;line-height:1.15}
.trx-num b{display:block;font-size:1.1rem;font-weight:650}
.trx-num small{display:block;margin-top:2px;font-size:.715rem;color:var(--x-fraco)}
a.trx-num:hover b{color:var(--x-acc)}
a.trx-num:hover small{color:var(--x-acc)}
.trx-num.is-atraso b{color:var(--x-perigo)}
.trx-prog-top{display:flex;justify-content:space-between;align-items:baseline;font-size:.735rem;color:var(--x-tinta2);margin-bottom:4px}
.trx-prog-top b{font-size:.79rem;font-weight:650;color:var(--x-ok)}
.trx-barra{position:relative;height:5px;border-radius:3px;background:var(--x-linha);overflow:hidden}
.trx-barra i{position:absolute;inset:0 auto 0 0;background:var(--x-ok);border-radius:3px}
.trx-stats-vazio{font-size:.775rem;color:var(--x-fraco)}

.trx-acts{display:flex;align-items:center;gap:6px}
.trx-bt{
  display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 11px;
  font-size:.8rem;color:var(--x-tinta2);text-decoration:none;white-space:nowrap;
  background:var(--x-sup);border:1px solid var(--x-linha);border-radius:7px;cursor:pointer;
}
.trx-bt:hover{border-color:#cfd6de;color:var(--x-tinta)}
.trx-bt.is-prim{background:var(--x-acc);border-color:var(--x-acc);color:#fff;font-weight:600}
.trx-bt.is-prim:hover{background:#0f3a68;color:#fff}
.trx-bt-ico{padding:0;width:32px;justify-content:center}

.trx-pop{position:relative}
.trx-pop-lista{
  position:absolute;right:0;top:calc(100% + 6px);z-index:60;min-width:212px;
  padding:5px;background:var(--x-sup);border:1px solid var(--x-linha);border-radius:9px;
  box-shadow:0 10px 26px rgba(22,29,38,.14);display:none;
}
.trx-pop.is-aberto{z-index:71}
.trx-pop.is-aberto .trx-pop-lista{display:block}
.trx-pop.is-cima .trx-pop-lista{top:auto;bottom:calc(100% + 6px)}
.trx-row.is-frente{position:relative;z-index:70}
.trx-pop-lista button,.trx-pop-lista a{
  display:flex;align-items:center;gap:9px;width:100%;padding:8px 10px;
  font-size:.815rem;color:var(--x-tinta);text-align:left;text-decoration:none;
  background:none;border:0;border-radius:6px;cursor:pointer;
}
.trx-pop-lista button:hover,.trx-pop-lista a:hover{background:#f4f6f8}
.trx-pop-lista .is-perigo{color:var(--x-perigo)}
.trx-pop-lista .is-perigo:hover{background:var(--x-perigo-fraco)}
.trx-pop-div{height:1px;margin:5px 4px;background:var(--x-linha2)}

.trx-vazio{
  padding:56px 24px;text-align:center;background:var(--x-sup);
  border:1px solid var(--x-linha);border-radius:var(--x-r);
}
.trx-vazio i{font-size:1.9rem;color:var(--x-fraco)}
.trx-vazio h2{margin:12px 0 5px;font-size:1.02rem;font-weight:650}
.trx-vazio p{margin:0 auto 16px;max-width:46ch;font-size:.855rem;color:var(--x-tinta2)}

@media (max-width:1180px){
  .trx-row,
  .trx[data-capas="0"] .trx-row,
  .trx[data-stats="0"] .trx-row,
  .trx[data-capas="0"][data-stats="0"] .trx-row{
    grid-template-columns:124px minmax(0,1fr) auto;
    row-gap:12px;
  }
  .trx[data-capas="0"] .trx-row,
  .trx[data-capas="0"][data-stats="0"] .trx-row{grid-template-columns:minmax(0,1fr) auto}
  .trx-stats{grid-column:1/-1;border-left:0;border-top:1px solid var(--x-linha2);padding:12px 0 0}
  .trx-nums{grid-template-columns:repeat(4,minmax(0,120px))}
}
@media (max-width:720px){
  .trx-row,
  .trx[data-capas="0"] .trx-row,
  .trx[data-stats="0"] .trx-row,
  .trx[data-capas="0"][data-stats="0"] .trx-row{grid-template-columns:1fr}
  .trx-capa{width:100%;height:132px}
  .trx-acts{flex-wrap:wrap}
  .trx-cmd-dir{margin-left:0;width:100%}
  .trx-kpi-alerta{margin-left:0}
}
</style>

<div class="tr-wrap trx" id="trxRaiz" data-stats="1" data-capas="1">

  <div class="trx-kpi">
    <div><b><?= (int)($resumo['pub'] ?? 0) ?></b><span>publicados</span></div>
    <div><b><?= (int)($resumo['rasc'] ?? 0) ?></b><span>em rascunho</span></div>
    <div><b><?= $totMatric ?></b><span>matriculados</span></div>
    <div class="trx-kpi-taxa">
      <b><?= $taxa ?>%</b><span>concluíram</span>
      <span class="trx-kpi-barra"><i style="width:<?= $taxa ?>%"></i></span>
    </div>
    <?php if ((int)($resumo['atras'] ?? 0) > 0): ?>
      <a class="trx-kpi-alerta" href="<?= h(tr_url('matriculas.php')) ?>">
        <b><?= (int)$resumo['atras'] ?></b><span>com prazo vencido</span>
      </a>
    <?php endif; ?>
  </div>

  <div class="trx-cmd">
    <form method="get" action="" class="trx-busca" id="trxForm">
      <i class="bi bi-search"></i>
      <input type="text" name="q" placeholder="Buscar por título, subtítulo ou endereço" value="<?= h($busca) ?>">
      <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= h($status) ?>"><?php endif; ?>
      <?php if ($ordem !== 'recentes'): ?><input type="hidden" name="ordem" value="<?= h($ordem) ?>"><?php endif; ?>
    </form>

    <div class="trx-abas" role="group" aria-label="Filtrar por situação">
      <?php
        $abas = ['' => 'Todos', 'publicado' => 'Publicados', 'rascunho' => 'Rascunhos', 'arquivado' => 'Arquivados'];
        foreach ($abas as $k => $rot):
          $url = tr_url('index.php') . '?status=' . rawurlencode($k)
               . ($busca !== '' ? '&q=' . rawurlencode($busca) : '')
               . ($ordem !== 'recentes' ? '&ordem=' . rawurlencode($ordem) : '');
      ?>
        <a class="trx-aba<?= $status === $k ? ' is-ativa' : '' ?>" href="<?= h($url) ?>"><?= h($rot) ?></a>
      <?php endforeach; ?>
    </div>

    <select class="trx-sel" id="trxOrdem" aria-label="Ordenar a lista">
      <option value="recentes"  <?= $ordem === 'recentes'  ? 'selected' : '' ?>>Mais recentes</option>
      <option value="titulo"    <?= $ordem === 'titulo'    ? 'selected' : '' ?>>Título (A–Z)</option>
      <option value="inscritos" <?= $ordem === 'inscritos' ? 'selected' : '' ?>>Mais matriculados</option>
      <option value="atraso"    <?= $ordem === 'atraso'    ? 'selected' : '' ?>>Mais atrasos</option>
    </select>

    <div class="trx-cmd-dir">
      <button type="button" class="trx-chip" id="trxVerStats" aria-pressed="true">
        <i class="bi bi-bar-chart-line"></i> Estatísticas
      </button>
      <button type="button" class="trx-chip" id="trxVerCapas" aria-pressed="true">
        <i class="bi bi-image"></i> Capas
      </button>
      <span class="trx-sep"></span>
      <a class="trx-chip" href="<?= h(tr_url('instrutores.php')) ?>" title="Modelos 3D e vozes de narração">
        <i class="bi bi-person-badge"></i> Instrutores
      </a>
      <?php if ($podeCriar): ?>
        <button type="button" class="trx-novo" id="btnNova">
          <i class="bi bi-plus-lg"></i> Novo treinamento
        </button>
      <?php endif; ?>
    </div>
  </div>

  <div class="trx-lista-topo">
    <span class="trh-contagem" data-tr-total>
      <?= count($lista) === 1 ? '1 treinamento' : count($lista) . ' treinamentos' ?>
    </span>
    <?php if ($temFiltro): ?>
      <a class="trx-limpar" href="<?= h(tr_url('index.php')) ?>"><i class="bi bi-x-lg"></i> Limpar filtros</a>
    <?php endif; ?>
  </div>

  <?php if (!$lista): ?>
    <div class="trx-vazio trh-vazio">
      <i class="bi bi-<?= $temFiltro ? 'search' : 'easel' ?>"></i>
      <h2><?= $temFiltro ? 'Nenhum treinamento com esses filtros' : 'Nenhum treinamento cadastrado' ?></h2>
      <p>
        <?= $temFiltro
          ? 'Troque a situação, mude a busca ou limpe os filtros para ver a lista completa.'
          : 'Um treinamento reúne slides narrados por um instrutor 3D, atividades e uma prova opcional.' ?>
      </p>
      <?php if ($temFiltro): ?>
        <a class="trx-bt" href="<?= h(tr_url('index.php')) ?>">Limpar filtros</a>
      <?php elseif ($podeCriar): ?>
        <button type="button" class="trx-novo" id="btnNovaVazio">Criar o primeiro treinamento</button>
      <?php endif; ?>
    </div>
  <?php else: ?>

    <div class="trx-lista trh-grade" id="trxLista">
      <?php foreach ($lista as $a): ?>
        <?php
          $modo    = $a['modo_acesso'] ?? ((int)$a['publico'] === 1 ? 'publico' : 'interno');
          $matric  = (int)$a['qtd_matriculas'];
          $conc    = (int)$a['qtd_concluidos'];
          $atrasos = (int)$a['qtd_atrasados'];
          $pct     = $matric > 0 ? (int)round(($conc / $matric) * 100) : 0;
          $link    = $baseUrl . $a['slug'];
          $semCont = (int)$a['qtd_slides'] === 0;
          $capa    = $temCapa ? trim((string)($a['capa'] ?? '')) : '';
          $capaUrl = $capa !== '' ? tr_url('uploads/capas/' . rawurlencode($capa)) : '';
          $tons    = ['#22456b', '#3b5a4a', '#5a4b6b', '#6b4f3b', '#2f5a63', '#4a4f5c'];
          $tom     = $tons[(int)$a['id'] % count($tons)];
        ?>
        <article class="trx-row trh-card" data-id="<?= (int)$a['id'] ?>">

          <?php if ($temCapa): ?>
            <div class="trx-capa" data-capa-box="<?= (int)$a['id'] ?>">
              <?php if ($capaUrl !== ''): ?>
                <img src="<?= h($capaUrl) ?>" alt="Capa de <?= h($a['titulo']) ?>" loading="lazy" data-capa-img>
              <?php else: ?>
                <div class="trx-capa-vazia" style="background:<?= h($tom) ?>" data-capa-vazia>
                  <?= h(trx_iniciais((string)$a['titulo'])) ?>
                </div>
              <?php endif; ?>
              <?php if ($podeEditar): ?>
                <button type="button" class="trx-capa-btn trx-capa-trocar" data-id="<?= (int)$a['id'] ?>">
                  <i class="bi bi-camera"></i> <?= $capaUrl !== '' ? 'Trocar' : 'Adicionar capa' ?>
                </button>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <div class="trx-main">
            <div class="trx-tags">
              <span class="trx-est trx-est-<?= h($a['status']) ?>"><?= h(ucfirst((string)$a['status'])) ?></span>
              <?php if ($modo === 'token'): ?>
                <span class="trx-tag"><i class="bi bi-person-badge"></i> Token</span>
              <?php elseif ($modo === 'publico'): ?>
                <span class="trx-tag"><i class="bi bi-globe2"></i> Público</span>
              <?php endif; ?>
              <?php if ((int)($a['prova_ativa'] ?? 0) === 1): ?>
                <span class="trx-tag"><i class="bi bi-clipboard-check"></i> Prova</span>
              <?php endif; ?>
            </div>

            <h3 class="trx-titulo">
              <a href="<?= h(tr_url($podeEditar ? 'editor.php' : 'player.php')) ?>?id=<?= (int)$a['id'] ?>">
                <?= h($a['titulo']) ?>
              </a>
            </h3>
            <?php if (($a['subtitulo'] ?? '') !== ''): ?>
              <p class="trx-sub"><?= h($a['subtitulo']) ?></p>
            <?php endif; ?>

            <div class="trx-meta">
              <span title="Slides"><i class="bi bi-collection"></i> <?= (int)$a['qtd_slides'] ?> slides</span>
              <span title="Acessos"><i class="bi bi-eye"></i> <?= (int)$a['qtd_views'] ?> acessos</span>
              <span title="Última alteração"><i class="bi bi-clock"></i> <?= h(nv_fmt_dt($a['atualizado_em'])) ?></span>
              <?php if ($semCont): ?>
                <span class="trx-aviso is-vazio"><i class="bi bi-exclamation-triangle"></i> Sem slides</span>
              <?php endif; ?>
              <?php if ($atrasos > 0): ?>
                <span class="trx-aviso is-atraso"><i class="bi bi-calendar-x"></i> <?= $atrasos ?> com prazo vencido</span>
              <?php endif; ?>
            </div>

            <?php if ($modo === 'publico' && (int)$a['publico'] === 1): ?>
              <button type="button" class="trx-publico tr-copiar" data-link="<?= h($link) ?>" title="Copiar o link público">
                <i class="bi bi-link-45deg"></i><span><?= h($link) ?></span><i class="bi bi-clipboard"></i>
              </button>
            <?php endif; ?>
          </div>

          <div class="trx-stats">
            <div class="trx-nums">
              <?php if ($podeMatric): ?>
                <a class="trx-num" href="<?= h(tr_url('matriculas.php')) ?>?id=<?= (int)$a['id'] ?>">
                  <b><?= $matric ?></b><small>matriculados</small>
                </a>
              <?php else: ?>
                <div class="trx-num"><b><?= $matric ?></b><small>matriculados</small></div>
              <?php endif; ?>
              <div class="trx-num"><b><?= $conc ?></b><small>concluíram</small></div>
              <div class="trx-num<?= $atrasos > 0 ? ' is-atraso' : '' ?>"><b><?= $atrasos ?></b><small>em atraso</small></div>
            </div>
            <?php if ($matric > 0): ?>
              <div class="trx-prog-top"><span>Conclusão</span><b><?= $pct ?>%</b></div>
              <div class="trx-barra"><i style="width:<?= $pct ?>%"></i></div>
            <?php else: ?>
              <div class="trx-stats-vazio">Nenhum colaborador matriculado</div>
            <?php endif; ?>
          </div>

          <div class="trx-acts">
            <?php if ($podeMatric): ?>
              <a class="trx-bt" href="<?= h(tr_url('matriculas.php')) ?>?id=<?= (int)$a['id'] ?>">
                <i class="bi bi-people"></i> Matrículas
              </a>
            <?php endif; ?>
            <a class="trx-bt" href="<?= h(tr_url('player.php')) ?>?id=<?= (int)$a['id'] ?>" target="_blank">
              <i class="bi bi-play"></i> Ver
            </a>
            <?php if ($podeEditar): ?>
              <a class="trx-bt is-prim" href="<?= h(tr_url('editor.php')) ?>?id=<?= (int)$a['id'] ?>">
                <i class="bi bi-pencil"></i> Editar
              </a>
            <?php endif; ?>

            <div class="trx-pop">
              <button type="button" class="trx-bt trx-bt-ico trx-pop-btn" title="Mais ações"
                      aria-haspopup="true" aria-expanded="false"><i class="bi bi-three-dots"></i></button>
              <div class="trx-pop-lista">
                <?php if ($temCapa && $podeEditar): ?>
                  <div class="trx-pop-div"></div>
                  <button type="button" class="trx-capa-trocar" data-id="<?= (int)$a['id'] ?>">
                    <i class="bi bi-image"></i> <?= $capaUrl !== '' ? 'Trocar imagem de capa' : 'Adicionar imagem de capa' ?>
                  </button>
                  <?php if ($capaUrl !== ''): ?>
                    <button type="button" class="trx-capa-remover" data-id="<?= (int)$a['id'] ?>">
                      <i class="bi bi-eraser"></i> Remover capa
                    </button>
                  <?php endif; ?>
                <?php endif; ?>
                <?php if ($podeEditar || $podePublicar): ?>
                  <div class="trx-pop-div"></div>
                <?php endif; ?>
                <?php if ($podeEditar): ?>
                  <button type="button" class="tr-acao-duplicar" data-id="<?= (int)$a['id'] ?>">
                    <i class="bi bi-files"></i> Duplicar
                  </button>
                <?php endif; ?>
                <?php if ($podePublicar): ?>
                  <button type="button" class="tr-acao-publico"
                          data-id="<?= (int)$a['id'] ?>" data-publico="<?= (int)$a['publico'] ?>">
                    <i class="bi bi-<?= (int)$a['publico'] === 1 ? 'lock' : 'globe2' ?>"></i>
                    <?= (int)$a['publico'] === 1 ? 'Desativar link público' : 'Ativar link público' ?>
                  </button>
                <?php endif; ?>
                <?php if ($podeExcluir): ?>
                  <div class="trx-pop-div"></div>
                  <button type="button" class="is-perigo tr-acao-excluir"
                          data-id="<?= (int)$a['id'] ?>" data-titulo="<?= h($a['titulo']) ?>">
                    <i class="bi bi-trash"></i> Excluir
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="trx-vazio trh-vazio" id="trhVazioApos" hidden>
      <i class="bi bi-easel"></i>
      <h2>A lista ficou vazia</h2>
      <p>Todos os treinamentos desta visão foram removidos. Crie um novo ou revise os filtros.</p>
      <?php if ($podeCriar): ?>
        <button type="button" class="trx-novo" id="btnNovaVazio2">Criar treinamento</button>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($temCapa && $podeEditar): ?>
    <input type="file" id="trxCapaInput" accept="image/png,image/jpeg,image/webp,image/gif" hidden>
  <?php endif; ?>
</div>

<div class="tr-modal" id="modalNova" inert>
  <div class="tr-modal-caixa">
    <div class="tr-modal-head">
      <span>Novo treinamento</span>
      <button type="button" class="tr-modal-x" data-fechar="modalNova"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="tr-modal-body">
      <label class="tr-label">Título</label>
      <input type="text" class="form-control" id="novaTitulo" maxlength="160" placeholder="Ex.: Onboarding — módulo 1">
      <label class="tr-label mt-3">Subtítulo</label>
      <input type="text" class="form-control" id="novaSubtitulo" maxlength="220" placeholder="Opcional">
      <p class="tr-dica mt-3 mb-0">
        Depois de criar você monta os slides no editor, escolhe o instrutor que narra,
        define a capa e matricula os colaboradores.
      </p>
    </div>
    <div class="tr-modal-pe">
      <button type="button" class="btn tr-btn-ghost" data-fechar="modalNova">Cancelar</button>
      <button type="button" class="btn tr-btn-primaria" id="novaConfirmar">Criar treinamento</button>
    </div>
  </div>
</div>

<script>
  window.TR_CSRF = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window.TR_API  = <?= json_encode(tr_url('api.php'), JSON_HEX_TAG) ?>;
  window.TR_BASE = <?= json_encode(tr_url(), JSON_HEX_TAG) ?>;
  window.TR_CAPA_URL = <?= json_encode(tr_url('capa_upload.php'), JSON_HEX_TAG) ?>;
</script>
<script src="<?= h(tr_url('assets/js/ui.js')) ?>"></script>
<script src="<?= h(tr_url('assets/js/lista.js')) ?>"></script>
<script src="<?= h(tr_url('assets/js/inicio.js')) ?>"></script>

<script>
(function () {
  'use strict';
  var raiz = document.getElementById('trxRaiz');
  if (!raiz) return;

  function pref(chave, botao, atributo) {
    if (!botao) return;
    var salvo = null;
    try { salvo = localStorage.getItem(chave); } catch (e) {}
    var ligado = salvo === null ? true : salvo === '1';

    function aplicar() {
      raiz.setAttribute(atributo, ligado ? '1' : '0');
      botao.setAttribute('aria-pressed', ligado ? 'true' : 'false');
    }
    aplicar();

    botao.addEventListener('click', function () {
      ligado = !ligado;
      aplicar();
      try { localStorage.setItem(chave, ligado ? '1' : '0'); } catch (e) {}
    });
  }
  pref('trx.stats', document.getElementById('trxVerStats'), 'data-stats');
  pref('trx.capas', document.getElementById('trxVerCapas'), 'data-capas');

  var sel = document.getElementById('trxOrdem');
  if (sel) {
    sel.addEventListener('change', function () {
      var u = new URL(window.location.href);
      if (sel.value === 'recentes') { u.searchParams.delete('ordem'); }
      else { u.searchParams.set('ordem', sel.value); }
      window.location.href = u.toString();
    });
  }

  function fecharMenus(exceto) {
    raiz.querySelectorAll('.trx-pop.is-aberto').forEach(function (p) {
      if (p === exceto) return;
      p.classList.remove('is-aberto', 'is-cima');
      var b = p.querySelector('.trx-pop-btn');
      if (b) b.setAttribute('aria-expanded', 'false');
      var linha = p.closest('.trx-row');
      if (linha) linha.classList.remove('is-frente');
    });
  }

  function posicionar(pop) {
    var lista = pop.querySelector('.trx-pop-lista');
    if (!lista) return;
    pop.classList.remove('is-cima');
    var caixa = pop.getBoundingClientRect();
    var altura = lista.offsetHeight || 0;
    var abaixo = window.innerHeight - caixa.bottom;
    if (abaixo < altura + 16 && caixa.top > altura + 16) pop.classList.add('is-cima');
  }

  raiz.addEventListener('click', function (ev) {
    var btn = ev.target.closest('.trx-pop-btn');
    if (btn) {
      var pop = btn.closest('.trx-pop');
      var abrir = !pop.classList.contains('is-aberto');
      fecharMenus(pop);
      pop.classList.toggle('is-aberto', abrir);
      btn.setAttribute('aria-expanded', abrir ? 'true' : 'false');
      var linha = pop.closest('.trx-row');
      if (linha) linha.classList.toggle('is-frente', abrir);
      if (abrir) posicionar(pop);
      ev.stopPropagation();
      return;
    }
    if (!ev.target.closest('.trx-pop-lista')) fecharMenus(null);
  });

  window.addEventListener('resize', function () { fecharMenus(null); });
  window.addEventListener('scroll', function () { fecharMenus(null); }, true);
  document.addEventListener('click', function (ev) {
    if (!ev.target.closest('.trx-pop')) fecharMenus(null);
  });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') fecharMenus(null);
  });

  var input = document.getElementById('trxCapaInput');
  if (!input) return;
  var alvo = 0;

  function aviso(msg, erro) {
    if (window.trToast) { window.trToast(msg, erro ? 'erro' : 'ok'); return; }
    if (window.TR && typeof window.TR.toast === 'function') { window.TR.toast(msg); return; }
    if (erro) alert(msg);
  }

  function caixaDe(id) {
    return raiz.querySelector('[data-capa-box="' + id + '"]');
  }

  function pintar(id, url) {
    var box = caixaDe(id);
    if (!box) return;
    var btn = box.querySelector('.trx-capa-trocar');
    var img = box.querySelector('[data-capa-img]');
    var vazio = box.querySelector('[data-capa-vazia]');

    if (url) {
      if (!img) {
        img = document.createElement('img');
        img.setAttribute('data-capa-img', '');
        img.alt = 'Capa do treinamento';
        box.insertBefore(img, box.firstChild);
      }
      img.src = url + (url.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
      if (vazio) vazio.remove();
      if (btn) btn.innerHTML = '<i class="bi bi-camera"></i> Trocar';
    } else {
      if (img) img.remove();
      if (btn) btn.innerHTML = '<i class="bi bi-camera"></i> Adicionar capa';
    }
  }

  raiz.addEventListener('click', function (ev) {
    var trocar = ev.target.closest('.trx-capa-trocar');
    if (trocar) {
      alvo = parseInt(trocar.dataset.id, 10) || 0;
      fecharMenus(null);
      input.value = '';
      input.click();
      return;
    }

    var remover = ev.target.closest('.trx-capa-remover');
    if (remover) {
      var id = parseInt(remover.dataset.id, 10) || 0;
      fecharMenus(null);
      if (!id || !window.confirm('Remover a imagem de capa deste treinamento?')) return;
      var fd = new FormData();
      fd.append('id', id);
      fd.append('acao', 'remover');
      fd.append('csrf', window.TR_CSRF);
      fetch(window.TR_CAPA_URL, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.ok) { aviso(d.erro || 'Não foi possível remover a capa.', true); return; }
          pintar(id, null);
          remover.remove();
          aviso('Capa removida.');
        })
        .catch(function () { aviso('Falha de conexão ao remover a capa.', true); });
    }
  });

  input.addEventListener('change', function () {
    var arq = input.files && input.files[0];
    if (!arq || !alvo) return;
    if (arq.size > 5 * 1024 * 1024) { aviso('A imagem passa de 5 MB. Reduza e tente de novo.', true); return; }

    var box = caixaDe(alvo);
    if (box) box.classList.add('is-enviando');

    var fd = new FormData();
    fd.append('id', alvo);
    fd.append('acao', 'enviar');
    fd.append('csrf', window.TR_CSRF);
    fd.append('capa', arq);

    var id = alvo;
    fetch(window.TR_CAPA_URL, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) { aviso(d.erro || 'Não foi possível salvar a capa.', true); return; }
        pintar(id, d.url);
        aviso('Capa atualizada.');
      })
      .catch(function () { aviso('Falha de conexão ao enviar a imagem.', true); })
      .finally(function () {
        var b = caixaDe(id);
        if (b) b.classList.remove('is-enviando');
        input.value = '';
      });
  });
})();
</script>

<?php require_once __DIR__ . '/../../../app/layout/footer.php'; ?>
