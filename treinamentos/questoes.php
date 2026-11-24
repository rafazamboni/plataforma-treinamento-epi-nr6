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

$gerencia = trm_pode_gerenciar();
$questoes = trp_questoes($pdo, $id);
$validas  = 0;
foreach ($questoes as $q) { if (trp_questao_valida($q) && (int)$q['ativa'] === 1) { $validas++; } }

$st = $pdo->prepare('SELECT COUNT(*) FROM treinamentos_inscricoes WHERE apresentacao_id = ?');
$st->execute([$id]);
$qtdMatriculas = (int)$st->fetchColumn();

$temProva = (int)$ap['prova_ativa'] === 1;

$estat = trp_estatisticas_questoes($pdo, $id);
$niveisRotulo = ['facil' => 'Fácil', 'medio' => 'Média', 'dificil' => 'Difícil', 'amostra' => 'Poucos dados'];

$st = $pdo->prepare(
    'SELECT COUNT(*) tentativas,
            SUM(aprovado = 1) aprovadas,
            AVG(percentual) media
       FROM treinamentos_tentativas WHERE apresentacao_id = ?'
);
$st->execute([$id]);
$desempenho = $st->fetch(PDO::FETCH_ASSOC) ?: [];

require_once __DIR__ . '/../../../app/layout/header.php';
nv_render_header('Prova — ' . $ap['titulo']);
?>
<link rel="stylesheet" href="<?= h(tr_url('assets/css/admin.css')) ?>">
<link rel="stylesheet" href="<?= h(tr_url('assets/css/matriculas.css')) ?>">
<link rel="stylesheet" href="<?= h(tr_url('assets/css/gestao.css')) ?>">
<link rel="stylesheet" href="<?= h(tr_url('assets/css/questoes.css')) ?>">

<div class="tr-wrap trg-wrap">

  <?php $abaAtiva = 'prova'; $qtdQuestoes = $validas; require __DIR__ . '/_abas.php'; ?>

  <div class="trq-layout">

    <aside class="trq-regras">
      <section class="trg-painel">
        <label class="trm-check trg-destaque">
          <input type="checkbox" id="cfgProvaAtiva" <?= (int)$ap['prova_ativa'] === 1 ? 'checked' : '' ?>>
          <span><b>Aplicar prova ao final</b>
            <small>o treinamento só é concluído com a nota mínima atingida</small></span>
        </label>

        <?php if ((int)$ap['prova_ativa'] === 1 && $validas === 0): ?>
          <div class="trg-aviso is-compacto">
            <i class="bi bi-exclamation-triangle"></i>
            <div>A prova está ativa, mas não há questões válidas. Enquanto isso o
              treinamento conclui só com o conteúdo assistido.</div>
          </div>
        <?php endif; ?>

        <div class="trq-banco">
          <b><?= $validas ?></b>
          <span>questão(ões) prontas para sortear</span>
          <?php if (count($questoes) > $validas): ?>
            <em><?= count($questoes) - $validas ?> inativa(s) ou sem gabarito</em>
          <?php endif; ?>
        </div>

        <h2 class="trg-secao"><i class="bi bi-bullseye"></i> Aprovação</h2>
        <div class="trq-linha">
          <label for="cfgNota">Nota mínima</label>
          <div class="trq-num"><input type="number" id="cfgNota" min="0" max="100"
                 value="<?= (int)$ap['prova_nota_minima'] ?>"><span>%</span></div>
        </div>
        <div class="trq-linha">
          <label for="cfgTentativas">Tentativas <small>0 = ilimitadas</small></label>
          <div class="trq-num"><input type="number" id="cfgTentativas" min="0" max="20"
                 value="<?= (int)$ap['prova_tentativas'] ?>"></div>
        </div>
        <div class="trq-linha">
          <label for="cfgSorteio">Questões por prova <small>0 = todas</small></label>
          <div class="trq-num"><input type="number" id="cfgSorteio" min="0" max="200"
                 value="<?= (int)$ap['prova_qtd_sorteada'] ?>"></div>
        </div>

        <h2 class="trg-secao"><i class="bi bi-shuffle"></i> Aplicação</h2>
        <label class="trm-check"><input type="checkbox" id="cfgEmbaralhar"
          <?= (int)$ap['prova_embaralhar'] === 1 ? 'checked' : '' ?>>
          <span>Embaralhar a cada tentativa <small>questões e alternativas em ordem diferente</small></span></label>
        <label class="trm-check"><input type="checkbox" id="cfgGabarito"
          <?= (int)$ap['prova_mostrar_gabarito'] === 1 ? 'checked' : '' ?>>
          <span>Mostrar o resultado detalhado <small>o que errou e a explicação de cada questão</small></span></label>

        <h2 class="trg-secao"><i class="bi bi-info-circle"></i> Instruções</h2>
        <textarea class="form-control" id="cfgInstrucoes" rows="3" maxlength="600"
                  placeholder="Ex.: Leia com atenção. Você pode revisar o conteúdo antes de começar."><?= h($ap['prova_instrucoes']) ?></textarea>

        <?php if ($gerencia): ?>
          <div class="trg-pe">
            <button type="button" class="btn tr-btn-primaria w-100" id="btnProvaSalvar">Salvar regras</button>
          </div>
        <?php endif; ?>
      </section>

      <?php if ((int)($desempenho['tentativas'] ?? 0) > 0): ?>
        <section class="trg-painel trq-desempenho">
          <h2 class="trg-secao"><i class="bi bi-graph-up"></i> Desempenho</h2>
          <div class="trq-desempenho-num">
            <div><b><?= (int)$desempenho['tentativas'] ?></b><small>Provas feitas</small></div>
            <div><b><?= (int)$desempenho['aprovadas'] ?></b><small>Aprovações</small></div>
            <div><b><?= (int)round((float)($desempenho['media'] ?? 0)) ?>%</b><small>Nota média</small></div>
          </div>
        </section>
      <?php endif; ?>
    </aside>

    <div class="trq-banco-col">
      <div class="trq-banco-topo">
        <h2><i class="bi bi-collection"></i> Banco de questões</h2>
        <?php if ($gerencia): ?>
          <button type="button" class="btn tr-btn-primaria btn-sm" id="btnNovaQuestao">
            <i class="bi bi-plus-lg"></i> Nova questão
          </button>
        <?php endif; ?>
      </div>

  <?php if (!$questoes): ?>
    <div class="tr-vazio">
      <i class="bi bi-question-circle"></i>
      <p>Nenhuma questão cadastrada.</p>
      <?php if ($gerencia): ?>
        <button type="button" class="btn tr-btn-primaria btn-sm" id="btnNovaQuestaoVazio">Criar a primeira questão</button>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="trq-lista" id="trqLista">
      <?php foreach ($questoes as $i => $q): ?>
        <?php $completa = trp_questao_valida($q); ?>
        <article class="trq-card <?= (int)$q['ativa'] === 1 ? '' : 'is-inativa' ?> <?= $completa ? '' : 'is-incompleta' ?>"
                 data-id="<?= (int)$q['id'] ?>">
          <div class="trq-card-topo">
            <span class="trq-ordem"><?= $i + 1 ?></span>
            <span class="trq-tipo"><?= h(trp_tipos()[$q['tipo']] ?? $q['tipo']) ?></span>
            <?php if ((int)$q['peso'] > 1): ?>
              <span class="trq-peso">peso <?= (int)$q['peso'] ?></span>
            <?php endif; ?>
            <?php if ((int)$q['ativa'] !== 1): ?>
              <span class="trq-flag">inativa</span>
            <?php endif; ?>
            <?php if (!$completa): ?>
              <span class="trq-flag is-erro"><i class="bi bi-exclamation-triangle"></i> sem gabarito</span>
            <?php endif; ?>

            <?php if ($gerencia): ?>
              <div class="trq-acoes">
                <button type="button" class="trm-ico trq-mover" data-dir="-1" title="Subir"><i class="bi bi-arrow-up"></i></button>
                <button type="button" class="trm-ico trq-mover" data-dir="1" title="Descer"><i class="bi bi-arrow-down"></i></button>
                <button type="button" class="trm-ico trq-ativar" data-ativa="<?= (int)$q['ativa'] ?>"
                        title="<?= (int)$q['ativa'] === 1 ? 'Desativar' : 'Ativar' ?>">
                  <i class="bi bi-<?= (int)$q['ativa'] === 1 ? 'toggle-on' : 'toggle-off' ?>"></i>
                </button>
                <button type="button" class="trm-ico trq-editar" title="Editar"><i class="bi bi-pencil"></i></button>
                <button type="button" class="trm-ico trq-excluir" title="Excluir"><i class="bi bi-trash"></i></button>
              </div>
            <?php endif; ?>
          </div>

          <p class="trq-enunciado"><?= nl2br(h($q['enunciado'])) ?></p>

          <ul class="trq-alts">
            <?php foreach ($q['alternativas'] as $a): ?>
              <li class="<?= (int)$a['correta'] === 1 ? 'is-correta' : '' ?>">
                <i class="bi bi-<?= (int)$a['correta'] === 1 ? 'check-circle-fill' : 'circle' ?>"></i>
                <?= h($a['texto']) ?>
              </li>
            <?php endforeach; ?>
          </ul>

          <?php if ((string)$q['explicacao'] !== ''): ?>
            <p class="trq-explicacao"><i class="bi bi-lightbulb"></i> <?= h($q['explicacao']) ?></p>
          <?php endif; ?>

          <?php $e = $estat[(int)$q['id']] ?? null; ?>
          <div class="trq-estat">
            <?php if (!$e): ?>
              <span class="trq-estat-vazio"><i class="bi bi-bar-chart"></i> Ainda não respondida</span>
            <?php else: ?>
              <span class="trq-estat-item"><i class="bi bi-people"></i> Respondida <b><?= (int)$e['respondidas'] ?></b> <?= (int)$e['respondidas'] === 1 ? 'vez' : 'vezes' ?></span>
              <span class="trq-estat-item is-acerto"><i class="bi bi-check-circle"></i> <b><?= (int)$e['acertos'] ?></b> acertos (<?= (int)$e['pct_acerto'] ?>%)</span>
              <span class="trq-estat-item is-erro"><i class="bi bi-x-circle"></i> <b><?= (int)$e['erros'] ?></b> erros (<?= (int)$e['pct_erro'] ?>%)</span>
              <span class="trq-nivel is-<?= h($e['nivel']) ?>"
                    title="<?= $e['nivel'] === 'amostra' ? 'Menos de ' . TRP_AMOSTRA_MIN . ' respostas: ainda não dá para classificar' : 'Fácil: 80% ou mais de acerto · Média: 50 a 79% · Difícil: abaixo de 50%' ?>"><?= h($niveisRotulo[$e['nivel']]) ?></span>
              <span class="trq-estat-barra" title="<?= (int)$e['pct_acerto'] ?>% de acerto"><i style="width:<?= (int)$e['pct_acerto'] ?>%"></i></span>
            <?php endif; ?>
          </div>

          <script type="application/json" class="trq-dados"><?= json_encode([
            'id'         => (int)$q['id'],
            'tipo'       => (string)$q['tipo'],
            'enunciado'  => (string)$q['enunciado'],
            'explicacao' => (string)$q['explicacao'],
            'peso'       => (int)$q['peso'],
            'ativa'      => (int)$q['ativa'],
            'alternativas' => array_map(static fn($a) => [
                'texto'   => (string)$a['texto'],
                'correta' => (int)$a['correta'],
            ], $q['alternativas']),
          ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($gerencia): ?>
<div class="tr-modal" id="modalQuestao" aria-hidden="true">
  <div class="tr-modal-caixa" style="max-width:720px">
    <div class="tr-modal-head">
      <span id="questaoTitulo">Nova questão</span>
      <button type="button" class="tr-modal-x" data-fechar="modalQuestao"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="tr-modal-body">
      <input type="hidden" id="qId" value="0">

      <div class="trq-form">
        <div class="trm-campo trm-col-2">
          <label for="qEnunciado">Enunciado *</label>
          <textarea class="form-control" id="qEnunciado" rows="3"
                    placeholder="Escreva a pergunta como ela será exibida ao colaborador."></textarea>
        </div>

        <div class="trm-campo">
          <label for="qTipo">Tipo</label>
          <select class="form-select" id="qTipo">
            <?php foreach (trp_tipos() as $k => $v): ?>
              <option value="<?= h($k) ?>"><?= h($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="trm-campo">
          <label for="qPeso">Peso</label>
          <input type="number" class="form-control" id="qPeso" min="1" max="10" value="1">
          <small>Questões mais importantes podem valer mais.</small>
        </div>

        <div class="trm-campo trm-col-2">
          <label>Alternativas *</label>
          <div class="trq-alts-editor" id="qAlternativas"></div>
          <button type="button" class="btn btn-sm tr-btn-ghost trq-add" id="qAddAlt">
            <i class="bi bi-plus-lg"></i> Adicionar alternativa
          </button>
          <small id="qDicaTipo">Marque a alternativa correta.</small>
        </div>

        <div class="trm-campo trm-col-2">
          <label for="qExplicacao">Explicação (opcional)</label>
          <input type="text" class="form-control" id="qExplicacao" maxlength="600"
                 placeholder="Exibida no resultado, quando o gabarito estiver liberado.">
        </div>

        <div class="trm-campo trm-col-2">
          <label class="trm-check"><input type="checkbox" id="qAtiva" checked>
            <span>Questão ativa <small>questões inativas ficam guardadas no banco, mas não caem na prova</small></span></label>
        </div>
      </div>
    </div>
    <div class="tr-modal-pe">
      <button type="button" class="btn tr-btn-ghost" data-fechar="modalQuestao">Cancelar</button>
      <button type="button" class="btn tr-btn-primaria" id="qSalvar">Salvar questão</button>
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
<script src="<?= h(tr_url('assets/js/questoes.js')) ?>"></script>

<?php require_once __DIR__ . '/../../../app/layout/footer.php'; ?>
