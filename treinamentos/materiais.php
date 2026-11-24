<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_once __DIR__ . '/_matriculas.php';
require_once __DIR__ . '/_prova.php';
require_once __DIR__ . '/_materiais.php';
require_once __DIR__ . '/_apostila.php';

require_login();

if (!trmat_pode_ver()) {
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

if (isset($_GET['baixar'])) {
    trmat_entregar_por_id($pdo, $id, (int)$_GET['baixar'], true);
}
if (isset($_GET['abrir'])) {
    trmat_entregar_por_id($pdo, $id, (int)$_GET['abrir'], false);
}

$gerencia = trmat_pode_gerenciar();

$migrado = true;
try {
    $pdo->query('SELECT 1 FROM treinamentos_materiais LIMIT 1');
    $pdo->query('SELECT 1 FROM treinamentos_referencias LIMIT 1');
} catch (Throwable $e) {
    $migrado = false;
}

$materiais   = $migrado ? trmat_listar($pdo, $id, false) : [];
$referencias = $migrado ? trmat_referencias($pdo, $id, false) : [];

$temApostila = true;
try {
    $pdo->query('SELECT apostila_ativa FROM treinamentos_apresentacoes LIMIT 1');
} catch (Throwable $e) {
    $temApostila = false;
}
$apo = trap_cfg($ap);

$temProva    = trp_prova_ativa($ap, $pdo);
$qtdQuestoes = trp_total_valido($pdo, $id);

$st = $pdo->prepare('SELECT COUNT(*) FROM treinamentos_inscricoes WHERE apresentacao_id = ?');
$st->execute([$id]);
$qtdMatriculas = (int)$st->fetchColumn();

require_once __DIR__ . '/../../../app/layout/header.php';
nv_render_header('Material de apoio — ' . $ap['titulo']);
?>
<link rel="stylesheet" href="<?= h(tr_url('assets/css/admin.css')) ?>">
<link rel="stylesheet" href="<?= h(tr_url('assets/css/matriculas.css')) ?>">
<link rel="stylesheet" href="<?= h(tr_url('assets/css/gestao.css')) ?>">
<link rel="stylesheet" href="<?= h(tr_url('assets/css/materiais.css')) ?>">

<div class="tr-wrap trg-wrap">

  <?php $abaAtiva = 'apoio'; require __DIR__ . '/_abas.php'; ?>

  <?php if (!$migrado): ?>
    <div class="trg-aviso">
      <i class="bi bi-database-exclamation"></i>
      <div>
        <strong>Tabelas ainda não criadas.</strong>
        Execute <code>sql/migration_apoio_portal.sql</code> no phpMyAdmin para
        liberar os documentos de apoio e as referências.
      </div>
    </div>
  <?php else: ?>

  <div class="trmt-layout">

    <section class="trg-painel">
      <div class="trmt-topo">
        <h2 class="trg-secao"><i class="bi bi-paperclip"></i> Documentos de apoio</h2>
        <?php if ($gerencia): ?>
          <button type="button" class="btn btn-sm tr-btn-primaria" id="btnNovoMaterial">
            <i class="bi bi-plus-lg"></i> Novo documento
          </button>
        <?php endif; ?>
      </div>

      <p class="trmt-ajuda">
        Apostilas, manuais, cartilhas, procedimentos e links externos. Quando
        houver ao menos um documento liberado, aparece um ícone de anexo no
        canto superior direito do player e um aviso na orientação inicial.
      </p>

      <?php if (!$temApostila): ?>
        <div class="trg-aviso is-compacto">
          <i class="bi bi-database-exclamation"></i>
          <div>
            <strong>Apostila automática indisponível.</strong>
            Execute <code>sql/migration_apostila.sql</code> no phpMyAdmin para liberar o recurso.
          </div>
        </div>
      <?php else: ?>
        <div class="trmt-apo<?= $apo['ativa'] ? ' is-on' : '' ?>" id="trmtApo">
          <div class="trmt-apo-topo">
            <span class="trmt-apo-ic"><i class="bi bi-journal-text"></i></span>
            <div class="trmt-apo-corpo">
              <b>Apostila automática</b>
              <small>
                Monta um PDF com todo o conteúdo do treinamento — telas, blocos, narração,
                questões e referências — e publica o link junto dos documentos de apoio.
                Não há arquivo para anexar: o PDF é gerado no clique do colaborador, sempre
                com a versão atual do conteúdo.
              </small>
            </div>
            <?php if ($gerencia): ?>
              <label class="trmt-apo-chave">
                <input type="checkbox" id="apoAtiva" <?= $apo['ativa'] ? 'checked' : '' ?>>
                <span>Disponibilizar</span>
              </label>
            <?php else: ?>
              <span class="trmt-flag<?= $apo['ativa'] ? ' is-destaque' : '' ?>">
                <?= $apo['ativa'] ? 'disponível' : 'desligada' ?>
              </span>
            <?php endif; ?>
          </div>

          <div class="trmt-apo-corpo-op" id="apoOpcoes" <?= $apo['ativa'] ? '' : 'hidden' ?>>
            <div class="trm-campo">
              <label for="apoTitulo">Título mostrado ao colaborador</label>
              <input type="text" class="form-control" id="apoTitulo" maxlength="160"
                     value="<?= h($apo['titulo']) ?>"
                     placeholder="Apostila — <?= h($ap['titulo']) ?>"
                     <?= $gerencia ? '' : 'disabled' ?>>
            </div>

            <div class="trmt-apo-checks">
              <label class="trm-check"><input type="checkbox" id="apoNarracao" <?= $apo['narracao'] ? 'checked' : '' ?> <?= $gerencia ? '' : 'disabled' ?>>
                <span>Incluir a narração <small>o que o instrutor fala em cada etapa, em texto</small></span></label>

              <label class="trm-check"><input type="checkbox" id="apoImagens" <?= $apo['imagens'] ? 'checked' : '' ?> <?= $gerencia ? '' : 'disabled' ?>>
                <span>Incluir as imagens das telas</span></label>

              <label class="trm-check"><input type="checkbox" id="apoQuestoes" <?= $apo['questoes'] ? 'checked' : '' ?> <?= $gerencia ? '' : 'disabled' ?>>
                <span>Incluir as questões da prova <small>como exercício de fixação</small></span></label>

              <label class="trm-check"><input type="checkbox" id="apoGabarito" <?= $apo['gabarito'] ? 'checked' : '' ?> <?= $gerencia ? '' : 'disabled' ?>>
                <span>Mostrar o gabarito <small>marca a alternativa correta e a justificativa</small></span></label>

              <label class="trm-check"><input type="checkbox" id="apoReferencias" <?= $apo['referencias'] ? 'checked' : '' ?> <?= $gerencia ? '' : 'disabled' ?>>
                <span>Incluir as referências</span></label>

              <label class="trm-check"><input type="checkbox" id="apoAnotacoes" <?= $apo['anotacoes'] ? 'checked' : '' ?> <?= $gerencia ? '' : 'disabled' ?>>
                <span>Espaço para anotações <small>linhas em branco ao fim de cada etapa</small></span></label>
            </div>

            <div class="trmt-apo-pe">
              <?php if ($apo['ativa']): ?>
                <a class="trmt-abrir" target="_blank" rel="noopener"
                   href="<?= h(tr_url('materiais.php') . '?id=' . $id . '&abrir=-1') ?>">
                  <i class="bi bi-eye"></i> Ver a apostila
                </a>
              <?php endif; ?>
              <?php if ($gerencia): ?>
                <button type="button" class="btn btn-sm tr-btn-primaria" id="apoSalvar">Salvar apostila</button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!$materiais): ?>
        <div class="tr-vazio">
          <i class="bi bi-paperclip"></i>
          <p>Nenhum documento anexado<?= $temApostila && $apo['ativa'] ? ' além da apostila automática' : '' ?>.</p>
          <?php if ($gerencia): ?>
            <button type="button" class="btn tr-btn-primaria btn-sm" id="btnNovoMaterialVazio">
              Anexar o primeiro documento
            </button>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="trmt-lista" id="trmtLista">
          <?php foreach ($materiais as $m): ?>
            <?php
              $semArquivo = $m['tipo'] === 'arquivo' && trmat_caminho($m) === null;
              $link = $m['tipo'] === 'link'
                  ? (string)$m['url']
                  : tr_url('materiais.php') . '?id=' . $id . '&abrir=' . (int)$m['id'];
            ?>
            <article class="trmt-card<?= (int)$m['ativo'] === 1 ? '' : ' is-inativo' ?><?= $semArquivo ? ' is-erro' : '' ?>"
                     data-id="<?= (int)$m['id'] ?>">
              <div class="trmt-icone"><i class="bi bi-<?= h(trmat_icone($m)) ?>"></i></div>

              <div class="trmt-corpo">
                <div class="trmt-linha">
                  <b><?= h($m['titulo']) ?></b>
                  <?php if ((int)$m['destaque'] === 1): ?>
                    <span class="trmt-flag is-destaque"><i class="bi bi-star-fill"></i> destaque</span>
                  <?php endif; ?>
                  <?php if ((int)$m['ativo'] !== 1): ?>
                    <span class="trmt-flag">oculto</span>
                  <?php endif; ?>
                  <?php if ($semArquivo): ?>
                    <span class="trmt-flag is-erro"><i class="bi bi-exclamation-triangle"></i> arquivo ausente</span>
                  <?php endif; ?>
                </div>

                <?php if ((string)$m['descricao'] !== ''): ?>
                  <p class="trmt-desc"><?= h($m['descricao']) ?></p>
                <?php endif; ?>

                <div class="trmt-meta">
                  <span><i class="bi bi-tag"></i> <?= h(trmat_formato_rotulo($m)) ?></span>
                  <?php if (trmat_tamanho_rotulo($m) !== ''): ?>
                    <span><i class="bi bi-hdd"></i> <?= h(trmat_tamanho_rotulo($m)) ?></span>
                  <?php endif; ?>
                  <?php if ($m['tipo'] === 'arquivo' && (string)$m['arquivo_nome'] !== ''): ?>
                    <span class="trmt-arqnome"><i class="bi bi-file-earmark"></i> <?= h($m['arquivo_nome']) ?></span>
                  <?php endif; ?>
                  <?php if (!$semArquivo): ?>
                    <a class="trmt-abrir" href="<?= h($link) ?>" target="_blank" rel="noopener">
                      <i class="bi bi-box-arrow-up-right"></i> Abrir
                    </a>
                  <?php endif; ?>
                </div>
              </div>

              <?php if ($gerencia): ?>
                <div class="trmt-acoes">
                  <button type="button" class="trm-ico trmt-mover" data-dir="-1" title="Subir"><i class="bi bi-arrow-up"></i></button>
                  <button type="button" class="trm-ico trmt-mover" data-dir="1" title="Descer"><i class="bi bi-arrow-down"></i></button>
                  <button type="button" class="trm-ico trmt-ativar" title="<?= (int)$m['ativo'] === 1 ? 'Ocultar' : 'Liberar' ?>">
                    <i class="bi bi-<?= (int)$m['ativo'] === 1 ? 'toggle-on' : 'toggle-off' ?>"></i>
                  </button>
                  <button type="button" class="trm-ico trmt-editar" title="Editar"><i class="bi bi-pencil"></i></button>
                  <button type="button" class="trm-ico trmt-excluir" title="Excluir"><i class="bi bi-trash"></i></button>
                </div>
              <?php endif; ?>

              <script type="application/json" class="trmt-dados"><?= json_encode([
                'id'        => (int)$m['id'],
                'titulo'    => (string)$m['titulo'],
                'descricao' => (string)$m['descricao'],
                'tipo'      => (string)$m['tipo'],
                'url'       => (string)$m['url'],
                'destaque'  => (int)$m['destaque'],
                'ativo'     => (int)$m['ativo'],
                'arquivo'   => (string)$m['arquivo_nome'],
              ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="trg-painel">
      <div class="trmt-topo">
        <h2 class="trg-secao"><i class="bi bi-journal-bookmark"></i> Referências</h2>
        <?php if ($gerencia): ?>
          <button type="button" class="btn btn-sm tr-btn-primaria" id="btnNovaRef">
            <i class="bi bi-plus-lg"></i> Nova referência
          </button>
        <?php endif; ?>
      </div>

      <p class="trmt-ajuda">
        Normas, publicações e fontes usadas na elaboração do conteúdo. Ficam
        no fim da lista de conteúdo do player, num controle expansível. Se a
        referência tiver link, ele abre em uma nova guia.
      </p>

      <?php if (!$referencias): ?>
        <div class="tr-vazio">
          <i class="bi bi-journal-bookmark"></i>
          <p>Nenhuma referência cadastrada.</p>
          <?php if ($gerencia): ?>
            <button type="button" class="btn tr-btn-primaria btn-sm" id="btnNovaRefVazio">
              Cadastrar a primeira referência
            </button>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <ol class="trmt-refs" id="trmtRefs">
          <?php foreach ($referencias as $r): ?>
            <li class="trmt-ref<?= (int)$r['ativo'] === 1 ? '' : ' is-inativo' ?>" data-id="<?= (int)$r['id'] ?>">
              <div class="trmt-ref-corpo">
                <span class="trmt-ref-texto"><?= h($r['texto']) ?></span>
                <?php if ((string)$r['url'] !== ''): ?>
                  <a class="trmt-ref-link" href="<?= h($r['url']) ?>" target="_blank" rel="noopener">
                    <i class="bi bi-link-45deg"></i> <?= h($r['url']) ?>
                  </a>
                <?php endif; ?>
                <?php if ((int)$r['ativo'] !== 1): ?>
                  <span class="trmt-flag">oculta</span>
                <?php endif; ?>
              </div>

              <?php if ($gerencia): ?>
                <div class="trmt-acoes">
                  <button type="button" class="trm-ico trmt-ref-mover" data-dir="-1" title="Subir"><i class="bi bi-arrow-up"></i></button>
                  <button type="button" class="trm-ico trmt-ref-mover" data-dir="1" title="Descer"><i class="bi bi-arrow-down"></i></button>
                  <button type="button" class="trm-ico trmt-ref-ativar" title="<?= (int)$r['ativo'] === 1 ? 'Ocultar' : 'Liberar' ?>">
                    <i class="bi bi-<?= (int)$r['ativo'] === 1 ? 'toggle-on' : 'toggle-off' ?>"></i>
                  </button>
                  <button type="button" class="trm-ico trmt-ref-editar" title="Editar"><i class="bi bi-pencil"></i></button>
                  <button type="button" class="trm-ico trmt-ref-excluir" title="Excluir"><i class="bi bi-trash"></i></button>
                </div>
              <?php endif; ?>

              <script type="application/json" class="trmt-ref-dados"><?= json_encode([
                'id'    => (int)$r['id'],
                'texto' => (string)$r['texto'],
                'url'   => (string)$r['url'],
                'ativo' => (int)$r['ativo'],
              ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </section>
  </div>
  <?php endif; ?>
</div>

<?php if ($gerencia && $migrado): ?>
<div class="tr-modal" id="modalMaterial" aria-hidden="true">
  <div class="tr-modal-caixa" style="max-width:620px">
    <div class="tr-modal-head">
      <span id="matTitulo">Novo documento de apoio</span>
      <button type="button" class="tr-modal-x" data-fechar="modalMaterial"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="tr-modal-body">
      <input type="hidden" id="matId" value="0">

      <div class="trm-campo">
        <label>Origem do documento</label>
        <div class="trg-opcoes is-compacto">
          <label class="trg-opcao"><input type="radio" name="matTipo" value="arquivo" checked>
            <span><b>Arquivo</b><small>PDF, Word, Excel, imagem…</small></span></label>
          <label class="trg-opcao"><input type="radio" name="matTipo" value="link">
            <span><b>Link externo</b><small>endereço de outra página</small></span></label>
        </div>
      </div>

      <div class="trm-campo">
        <label for="matTituloCampo">Título *</label>
        <input type="text" class="form-control" id="matTituloCampo" maxlength="160"
               placeholder="Ex.: Apostila NR-6 — Equipamento de Proteção Individual">
      </div>

      <div class="trm-campo">
        <label for="matDescricao">Descrição (opcional)</label>
        <textarea class="form-control" id="matDescricao" rows="2" maxlength="400"
                  placeholder="Uma linha explicando para que serve o documento."></textarea>
      </div>

      <div class="trm-campo" id="matBlocoArquivo">
        <label for="matArquivo">Arquivo</label>
        <input type="file" class="form-control" id="matArquivo"
               accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg,.webp,.txt,.csv,.zip,.mp4">
        <small id="matArquivoAtual">Máximo de 30 MB.</small>
      </div>

      <div class="trm-campo" id="matBlocoLink" hidden>
        <label for="matUrl">Endereço</label>
        <input type="url" class="form-control" id="matUrl" maxlength="500"
               placeholder="https://...">
      </div>

      <label class="trm-check"><input type="checkbox" id="matDestaque">
        <span>Destacar na lista <small>aparece com realce no painel de material do colaborador</small></span></label>

      <label class="trm-check"><input type="checkbox" id="matAtivo" checked>
        <span>Liberado para o colaborador <small>desmarque para guardar sem publicar</small></span></label>

      <div class="trmt-erro" id="matErro" hidden></div>
    </div>
    <div class="tr-modal-pe">
      <button type="button" class="btn tr-btn-ghost" data-fechar="modalMaterial">Cancelar</button>
      <button type="button" class="btn tr-btn-primaria" id="matSalvar">Salvar documento</button>
    </div>
  </div>
</div>

<div class="tr-modal" id="modalRef" aria-hidden="true">
  <div class="tr-modal-caixa" style="max-width:560px">
    <div class="tr-modal-head">
      <span id="refTitulo">Nova referência</span>
      <button type="button" class="tr-modal-x" data-fechar="modalRef"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="tr-modal-body">
      <input type="hidden" id="refId" value="0">

      <div class="trm-campo">
        <label for="refTexto">Referência *</label>
        <textarea class="form-control" id="refTexto" rows="3" maxlength="600"
                  placeholder="Ex.: BRASIL. Ministério do Trabalho e Emprego. NR-6 — Equipamento de Proteção Individual. Brasília, 2022."></textarea>
        <small>Escreva como deve aparecer para o colaborador.</small>
      </div>

      <div class="trm-campo">
        <label for="refUrl">Link (opcional)</label>
        <input type="url" class="form-control" id="refUrl" maxlength="500" placeholder="https://...">
        <small>Quando preenchido, a referência vira um link que abre em nova guia.</small>
      </div>

      <label class="trm-check"><input type="checkbox" id="refAtivo" checked>
        <span>Exibir para o colaborador</span></label>

      <div class="trmt-erro" id="refErro" hidden></div>
    </div>
    <div class="tr-modal-pe">
      <button type="button" class="btn tr-btn-ghost" data-fechar="modalRef">Cancelar</button>
      <button type="button" class="btn tr-btn-primaria" id="refSalvar">Salvar referência</button>
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
<script src="<?= h(tr_url('assets/js/materiais.js')) ?>"></script>

<?php require_once __DIR__ . '/../../../app/layout/footer.php'; ?>
