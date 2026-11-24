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

$gerencia    = trm_pode_gerenciar();
$temProva    = trp_prova_ativa($ap, $pdo);
$qtdQuestoes = trp_total_valido($pdo, $id);

$st = $pdo->prepare('SELECT COUNT(*) FROM treinamentos_inscricoes WHERE apresentacao_id = ?');
$st->execute([$id]);
$qtdMatriculas = (int)$st->fetchColumn();

require_once __DIR__ . '/../../../app/layout/header.php';
nv_render_header('Configurações — ' . $ap['titulo']);
?>
<link rel="stylesheet" href="<?= h(tr_url('assets/css/admin.css')) ?>">
<link rel="stylesheet" href="<?= h(tr_url('assets/css/matriculas.css')) ?>">
<link rel="stylesheet" href="<?= h(tr_url('assets/css/gestao.css')) ?>">

<div class="tr-wrap trg-wrap">

  <?php $abaAtiva = 'config'; require __DIR__ . '/_abas.php'; ?>

  <section class="trg-painel">
    <h2 class="trg-secao"><i class="bi bi-shield-lock"></i> Acesso e prazo</h2>
    <div class="trm-config-grade">
      <div class="trm-campo trm-col-2">
        <label>Forma de acesso</label>
        <div class="trm-radios">
          <label><input type="radio" name="cfgAcesso" value="token" <?= $ap['modo_acesso'] === 'token' ? 'checked' : '' ?>>
            <span><b>Token individual</b><small>cada colaborador recebe um link pessoal e o progresso é registrado</small></span></label>
          <label><input type="radio" name="cfgAcesso" value="interno" <?= $ap['modo_acesso'] === 'interno' ? 'checked' : '' ?>>
            <span><b>Somente interno</b><small>visível apenas para usuários do sistema, sem link externo</small></span></label>
          <label><input type="radio" name="cfgAcesso" value="publico" <?= $ap['modo_acesso'] === 'publico' ? 'checked' : '' ?>>
            <span><b>Link público</b><small>qualquer pessoa com o link assiste, sem identificação</small></span></label>
        </div>
      </div>

      <div class="trm-campo">
        <label for="cfgCarga">Duração estimada (minutos)</label>
        <input type="number" class="form-control" id="cfgCarga" min="0" max="600"
               value="<?= (int)$ap['carga_horaria_min'] ?>">
      </div>

      <div class="trm-campo">
        <label for="cfgPrazo">Prazo padrão (dias após a matrícula)</label>
        <input type="number" class="form-control" id="cfgPrazo" min="0" max="365"
               value="<?= (int)$ap['prazo_dias'] ?>">
        <small>0 = sem prazo.</small>
      </div>

      <div class="trm-campo trm-col-2">
        <label for="cfgDescricao">Descrição exibida ao colaborador</label>
        <textarea class="form-control" id="cfgDescricao" rows="3"
                  placeholder="Explique em poucas linhas o objetivo do treinamento."><?= h($ap['descricao'] ?? '') ?></textarea>
      </div>

      <div class="trm-campo trm-col-2">
        <label for="cfgMensagem">Mensagem de conclusão</label>
        <textarea class="form-control" id="cfgMensagem" rows="2" maxlength="400"
                  placeholder="Exibida na tela final e no e-mail de confirmação."><?= h($ap['mensagem_conclusao']) ?></textarea>
      </div>

      <div class="trm-campo trm-col-2">
        <label for="cfgEmailRh">Avisar por e-mail a cada conclusão</label>
        <input type="text" class="form-control" id="cfgEmailRh" maxlength="255"
               placeholder="rh@empresa.com.br; treinamento@empresa.com.br"
               value="<?= h($ap['email_conclusao']) ?>">
        <small>Separe vários endereços por ponto e vírgula.</small>
      </div>

      <div class="trm-campo trm-col-2">
        <label>Conclusão de cada slide</label>
        <div class="trm-radios">
          <label><input type="radio" name="cfgInteracao" value="todas" <?= $ap['interacao_obrigatoria'] === 'todas' ? 'checked' : '' ?>>
            <span><b>Interação completa</b><small>quiz, checklist, cartões, abas e materiais de apoio precisam ser usados antes de liberar o próximo slide</small></span></label>
          <label><input type="radio" name="cfgInteracao" value="quiz" <?= $ap['interacao_obrigatoria'] === 'quiz' ? 'checked' : '' ?>>
            <span><b>Somente perguntas</b><small>apenas os quizzes são obrigatórios; os demais blocos ficam livres</small></span></label>
          <label><input type="radio" name="cfgInteracao" value="nenhuma" <?= $ap['interacao_obrigatoria'] === 'nenhuma' ? 'checked' : '' ?>>
            <span><b>Só a narração</b><small>avança assim que a explicação do slide termina</small></span></label>
        </div>
      </div>

      <div class="trm-campo">
        <label for="cfgMomento">Quando a atividade aparece</label>
        <select class="form-select" id="cfgMomento">
          <option value="narracao" <?= ($ap['interacao_momento'] ?? 'narracao') === 'narracao' ? 'selected' : '' ?>>
            Durante a explicação — o bloco fica travado até o narrador chegar nele
          </option>
          <option value="imediato" <?= ($ap['interacao_momento'] ?? 'narracao') === 'imediato' ? 'selected' : '' ?>>
            Desde o início do slide — tudo liberado de uma vez
          </option>
        </select>
        <small>Para prender um bloco a uma fala específica, use <code>data-apos="3"</code> no HTML do slide.</small>
      </div>

      <div class="trm-campo">
        <label for="cfgTempoMin">Permanência mínima por slide (segundos)</label>
        <input type="number" class="form-control" id="cfgTempoMin" min="0" max="600"
               value="<?= (int)$ap['tempo_min_slide'] ?>">
        <small>0 desliga. Evita que o colaborador passe correndo pelos slides.</small>
      </div>

      <div class="trm-campo">
        <label>Regras adicionais</label>
        <label class="trm-check"><input type="checkbox" id="cfgQuizAcerto" <?= (int)$ap['quiz_exige_acerto'] === 1 ? 'checked' : '' ?>>
          <span>Exigir acerto nos quizzes <small>ao errar, a pergunta é reaberta para nova tentativa</small></span></label>
        <label class="trm-check"><input type="checkbox" id="cfgLeitura" <?= (int)$ap['exige_leitura'] === 1 ? 'checked' : '' ?>>
          <span>Exigir leitura completa <small>quando o conteúdo não cabe na tela, é preciso rolar até o fim</small></span></label>
      </div>

      <div class="trm-campo trm-col-2">
        <label class="trm-check"><input type="checkbox" id="cfgTermo" <?= (int)$ap['exige_termo'] === 1 ? 'checked' : '' ?>>
          <span>Exigir declaração de conclusão ao final <small>o colaborador confirma que assistiu ao conteúdo; fica registrado com data e IP</small></span></label>

        <label class="trm-check"><input type="checkbox" id="cfgRevisao" <?= (int)$ap['permite_revisao'] === 1 ? 'checked' : '' ?>>
          <span>Permitir revisão depois de concluído</span></label>
      </div>
    </div>

    <?php if ($gerencia): ?>
      <div class="trg-pe">
        <button type="button" class="btn tr-btn-primaria" id="btnConfigSalvar">Salvar configurações</button>
      </div>
    <?php endif; ?>
  </section>
</div>

<script>
  window.TR_CSRF   = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window.TR_API    = <?= json_encode(tr_url('api.php'), JSON_HEX_TAG) ?>;
  window.TR_BASE   = <?= json_encode(tr_url(), JSON_HEX_TAG) ?>;
  window.TRM_APRES = <?= (int)$id ?>;
</script>
<script src="<?= h(tr_url('assets/js/ui.js')) ?>"></script>
<script src="<?= h(tr_url('assets/js/configuracao.js')) ?>"></script>

<?php require_once __DIR__ . '/../../../app/layout/footer.php'; ?>
