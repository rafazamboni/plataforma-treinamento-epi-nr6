<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_once __DIR__ . '/_matriculas.php';
require_once __DIR__ . '/_obrigatoriedade.php';

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

    $colab = trm_colab($pdo, (int)$_GET['foto']);
    $caminho = null;
    if ($colab) {
        try {
            $cargosObrig = array_map(static function ($c) { return $c['cargo']; }, tro_cargos_do_treinamento($pdo, $id));
            $permitido = isset($colab['cargo'])
                ? in_array($colab['cargo'], $cargosObrig, true)
                : in_array((int)$colab['id'], array_map('intval', array_column(tro_colaboradores_pendentes($pdo, $id), 'id')), true);
        } catch (Throwable $e) {
            $permitido = false;
        }
        if ($permitido) {
            $tentados = [];
            $caminho = tr_foto_colaborador_caminho((string)($colab['foto_path'] ?? ''), $tentados);

            if (isset($_GET['diag']) && function_exists('is_admin_user') && is_admin_user()) {
                header('Content-Type: text/plain; charset=UTF-8');
                echo 'colaborador: ' . $colab['nome'] . "\n";
                echo 'foto_path: ' . var_export($colab['foto_path'] ?? null, true) . "\n";
                echo 'resolvido: ' . ($caminho ?? '(nenhum)') . "\n";
                echo "testados:\n  " . implode("\n  ", $tentados) . "\n";
                exit;
            }
        }
    }
    if ($caminho === null) { http_response_code(404); exit; }
    tr_enviar_arquivo($caminho);
}

$gerencia = trm_pode_gerenciar();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['acao'] ?? '') === 'salvar_situacoes') {
    if (!$gerencia) {
        flash_set('danger', 'Sem permissão para alterar as configurações.');
    } elseif (!hash_equals((string)csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        flash_set('danger', 'Sessão expirada. Tente novamente.');
    } else {
        try {
            $cods = isset($_POST['codigos']) && is_array($_POST['codigos']) ? $_POST['codigos'] : [];
            tro_situacoes_salvar($pdo, $id, $cods);
            flash_set('success', 'Configurações salvas.');
        } catch (Throwable $e) {
            flash_set('danger', 'Não foi possível salvar. Confirme que o arquivo migration_obrigatoriedade_situacoes.sql foi executado.');
        }
    }
    nv_redirect(tr_url('obrigatorios.php') . '?id=' . $id);
}

$st = $pdo->prepare('SELECT COUNT(*) FROM treinamentos_inscricoes WHERE apresentacao_id = ?');
$st->execute([$id]);
$qtdMatriculas = (int)$st->fetchColumn();

$temTabela = false;
try {
    $pdo->query('SELECT 1 FROM treinamentos_cargos_obrigatorios LIMIT 1');
    $temTabela = true;
} catch (Throwable $e) {
    $temTabela = false;
}

$temSituacoes = false;
try {
    $pdo->query('SELECT 1 FROM treinamentos_obrigatorios_situacoes LIMIT 1');
    $temSituacoes = true;
} catch (Throwable $e) {
    $temSituacoes = false;
}

$situacoes         = [];
$situacoesIgnoradas = [];
$cargos            = [];
$pendentes         = [];
$cargosDisponiveis = [];
$qtdObrigatorio    = 0;

if ($temTabela) {
    if ($temSituacoes) {
        $situacoes          = tro_situacoes_disponiveis($pdo, $id);
        $situacoesIgnoradas = tro_situacoes_ignoradas($pdo, $id);
    }
    $cargos            = tro_cargos_do_treinamento($pdo, $id);
    $pendentes         = tro_colaboradores_pendentes($pdo, $id);
    $qtdObrigatorio    = count($pendentes);
    $jaAdicionados     = array_map(static function ($c) { return $c['cargo']; }, $cargos);
    $cargosDisponiveis = array_values(array_diff(tro_cargos_disponiveis($pdo), $jaAdicionados));
}

require_once __DIR__ . '/../../../app/layout/header.php';
nv_render_header('Obrigatoriedade — ' . $ap['titulo']);
?>
<link rel="stylesheet" href="<?= h(tr_url('assets/css/admin.css')) ?>">
<link rel="stylesheet" href="<?= h(tr_url('assets/css/gestao.css')) ?>">
<link rel="stylesheet" href="<?= h(tr_url('assets/css/matriculas.css')) ?>">
<style>
  .tro-add{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:10px 0 16px}
  .tro-add .form-select{max-width:340px}
</style>

<div class="tr-wrap trg-wrap">

  <?php $abaAtiva = 'obrigatorio'; require __DIR__ . '/_abas.php'; ?>

  <?php if (!$temTabela): ?>
    <div class="trg-aviso">
      <i class="bi bi-exclamation-triangle"></i>
      <div>
        <strong>Recurso ainda não instalado.</strong>
        Rode o arquivo <code>migration_obrigatoriedade.sql</code> no banco de dados
        para liberar a obrigatoriedade por cargo.
      </div>
    </div>
  <?php else: ?>

  <section class="trg-painel">
    <h2 class="trg-secao"><i class="bi bi-briefcase"></i> Cargos obrigatórios</h2>
    <p class="trm-ajuda">
      Colaboradores ativos nos cargos abaixo entram automaticamente na lista de
      pendentes deste treinamento — inclusive quem for admitido depois — até
      que sejam matriculados.
    </p>

    <?php if ($situacoesIgnoradas): ?>
      <p class="trm-ajuda">
        <i class="bi bi-funnel"></i> Não entram nesta lista, por configuração:
        <strong><?= h(implode(', ', array_map(static function ($r) {
            return $r['status_descricao'] !== '' ? $r['status_descricao'] : 'código ' . $r['status_codigo'];
        }, $situacoesIgnoradas))) ?></strong>.
      </p>
    <?php endif; ?>

    <?php if ($gerencia): ?>
      <div class="tro-add">
        <select class="form-select" id="troCargoNovo">
          <option value="">Selecione um cargo do cadastro…</option>
          <?php foreach ($cargosDisponiveis as $c): ?>
            <option value="<?= h($c) ?>"><?= h($c) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="button" class="btn tr-btn-primaria btn-sm" id="troAdicionar" disabled>
          <i class="bi bi-plus-lg"></i> Adicionar cargo
        </button>
        <button type="button" class="btn btn-sm tr-btn-ghost ms-auto" id="troConfig">
          <i class="bi bi-gear"></i> Configurações
          <?php if ($situacoesIgnoradas): ?><span class="trm-badge"><?= count($situacoesIgnoradas) ?></span><?php endif; ?>
        </button>
      </div>
    <?php endif; ?>

    <?php if (!$cargos): ?>
      <div class="tr-vazio">
        <i class="bi bi-briefcase"></i>
        <p>Nenhum cargo tornado obrigatório ainda.</p>
      </div>
    <?php else: ?>
      <div class="tr-tabela-wrap">
        <table class="table tr-tabela align-middle">
          <thead>
            <tr>
              <th>Cargo</th>
              <th>Colaboradores ativos</th>
              <th>Matriculados</th>
              <th>Concluídos</th>
              <th>Pendentes</th>
              <?php if ($gerencia): ?><th class="text-end">Ações</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($cargos as $c): ?>
            <?php $pend = max(0, (int)$c['total_cargo'] - (int)$c['matriculados']); ?>
            <tr>
              <td><strong><?= h($c['cargo']) ?></strong></td>
              <td><?= (int)$c['total_cargo'] ?></td>
              <td><?= (int)$c['matriculados'] ?></td>
              <td><?= (int)$c['concluidos'] ?></td>
              <td><?= $pend > 0 ? '<span class="trm-badge">' . $pend . '</span>' : '0' ?></td>
              <?php if ($gerencia): ?>
                <td class="text-end">
                  <button type="button" class="trm-ico is-perigo" data-acao="cargo_remover"
                          data-id="<?= (int)$c['id'] ?>" data-cargo="<?= h($c['cargo']) ?>"
                          title="Remover obrigatoriedade">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="trg-painel">
    <h2 class="trg-secao"><i class="bi bi-person-plus"></i> Colaboradores pendentes</h2>

    <?php if (!$pendentes): ?>
      <div class="tr-vazio">
        <i class="bi bi-check2-circle"></i>
        <p>
          <?= $cargos
              ? 'Ninguém pendente no momento — todo mundo desses cargos já foi matriculado.'
              : 'Adicione ao menos um cargo obrigatório para ver pendências aqui.' ?>
        </p>
      </div>
    <?php else: ?>
      <?php if ($gerencia): ?>
        <div class="tr-toolbar">
          <span class="trm-ajuda"><?= count($pendentes) ?> colaborador(es) pendente(s)</span>
          <button type="button" class="btn tr-btn-primaria btn-sm" id="btnMatricularTodos">
            <i class="bi bi-people-fill"></i> Matricular todos os pendentes
          </button>
        </div>
      <?php endif; ?>

      <div class="tr-tabela-wrap">
        <table class="table tr-tabela align-middle">
          <thead>
            <tr>
              <th>Colaborador</th>
              <th>Cargo</th>
              <th>Filial</th>
              <?php if ($gerencia): ?><th class="text-end">Ações</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($pendentes as $c): ?>
            <tr>
              <td>
                <div class="trm-pessoa">
                  <span class="trm-foto">
                    <img src="<?= h(tr_url('obrigatorios.php')) ?>?id=<?= $id ?>&foto=<?= (int)$c['id'] ?>"
                         alt="" loading="lazy" onerror="this.remove()">
                    <i class="bi bi-person"></i>
                  </span>
                  <span class="trm-pessoa-txt">
                    <strong><?= h($c['nome']) ?></strong>
                    <small>
                      <?php if ((string)$c['matricula'] !== ''): ?>
                        <em class="trm-mat">#<?= h($c['matricula']) ?></em>
                      <?php endif; ?>
                      <?php if ($c['email']): ?>
                        <?= h($c['email']) ?>
                      <?php else: ?>
                        <span class="trm-sem-email"><i class="bi bi-envelope-slash"></i> sem e-mail</span>
                      <?php endif; ?>
                    </small>
                  </span>
                </div>
              </td>
              <td><?= h($c['cargo'] ?: '—') ?></td>
              <td><?= h($c['filial'] ?: '—') ?></td>
              <?php if ($gerencia): ?>
                <td class="text-end">
                  <div class="trm-acoes">
                    <button type="button" class="btn btn-sm tr-btn-ghost" data-acao="matricular"
                            data-colaborador-id="<?= (int)$c['id'] ?>" data-nome="<?= h($c['nome']) ?>">
                      Matricular
                    </button>
                    <button type="button" class="btn btn-sm tr-btn-ghost" data-acao="externo"
                            data-colaborador-id="<?= (int)$c['id'] ?>" data-nome="<?= h($c['nome']) ?>">
                      Já realizou (outra plataforma)
                    </button>
                  </div>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <?php endif; ?>
</div>

<?php if ($gerencia && $temTabela): ?>
<dialog id="troDialogConfig" style="border:0;border-radius:12px;padding:0;width:92%;max-width:560px;box-shadow:0 10px 40px rgba(0,0,0,.25)">
  <form method="post" action="<?= h(tr_url('obrigatorios.php')) ?>?id=<?= (int)$id ?>" style="margin:0">
    <input type="hidden" name="acao" value="salvar_situacoes">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

    <div class="tr-modal-head">
      <span>Configurações — situações ignoradas</span>
      <button type="button" class="tr-modal-x" data-troconfig-fechar><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="tr-modal-body">
      <?php if (!$temSituacoes): ?>
        <div class="trg-aviso">
          <i class="bi bi-exclamation-triangle"></i>
          <div>Rode o arquivo <code>migration_obrigatoriedade_situacoes.sql</code> no banco para usar esta configuração.</div>
        </div>
      <?php else: ?>
        <p class="trm-ajuda">
          Marque as situações de colaborador que <b>não devem aparecer</b> na obrigatoriedade
          <b>deste treinamento</b> (contadores e lista de pendentes), como aposentado por invalidez.
          Quem já está matriculado continua nas matrículas.
        </p>
        <div style="max-height:340px;overflow:auto;border:1px solid var(--bs-border-color,#dee2e6);border-radius:8px;padding:6px 12px">
          <?php foreach ($situacoes as $sit): ?>
            <label class="trm-check" style="display:flex;gap:8px;align-items:center;padding:6px 0">
              <input type="checkbox" name="codigos[]" value="<?= h($sit['codigo']) ?>" <?= $sit['ignorada'] ? 'checked' : '' ?>>
              <span>
                <?= h($sit['descricao'] !== '' ? $sit['descricao'] : 'Sem descrição') ?>
                <small class="text-muted">· código <?= h($sit['codigo']) ?> ·
                  <?= $sit['total'] > 0 ? (int)$sit['total'] . ' colaborador(es) ativo(s)' : 'nenhum colaborador ativo' ?></small>
              </span>
            </label>
          <?php endforeach; ?>
          <?php if (!$situacoes): ?>
            <p class="trm-ajuda m-0">Nenhuma situação encontrada no cadastro.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="tr-modal-pe">
      <button type="button" class="btn tr-btn-ghost" data-troconfig-fechar>Cancelar</button>
      <?php if ($temSituacoes): ?><button type="submit" class="btn tr-btn-primaria">Salvar</button><?php endif; ?>
    </div>
  </form>
</dialog>
<script>
  (function () {
    var dlg = document.getElementById('troDialogConfig');
    var abrir = document.getElementById('troConfig');
    if (!dlg || !abrir || typeof dlg.showModal !== 'function') { return; }
    abrir.addEventListener('click', function () { dlg.showModal(); });
    dlg.addEventListener('click', function (e) {
      if (e.target === dlg || e.target.closest('[data-troconfig-fechar]')) { dlg.close(); }
    });
  })();
</script>

<div class="tr-modal" id="modalExterno" aria-hidden="true">
  <div class="tr-modal-caixa">
    <div class="tr-modal-head">
      <span>Marcar treinamento como realizado</span>
      <button type="button" class="tr-modal-x" data-fechar="modalExterno"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="tr-modal-body">
      <input type="hidden" id="exId" value="0">
      <input type="hidden" id="exColaboradorId" value="0">
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
<script src="<?= h(tr_url('assets/js/obrigatorios.js')) ?>"></script>

<?php require_once __DIR__ . '/../../../app/layout/footer.php'; ?>
