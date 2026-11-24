<?php
if (!isset($ap)) { return; }

$abas = [
    'matriculas' => ['Matrículas',      'people',           'matriculas.php'],
    'obrigatorio'=> ['Obrigatoriedade', 'briefcase',        'obrigatorios.php'],
    'prova'      => ['Prova',           'patch-question',   'questoes.php'],
    'apoio'      => ['Apoio',           'paperclip',        'materiais.php'],
    'certificado'=> ['Certificado',     'award',            'certificado_config.php'],
    'config'     => ['Configurações',   'sliders',          'configuracao.php'],
];

if (!isset($qtdApoio)) {
    if (!function_exists('trmat_total')) { require_once __DIR__ . '/_materiais.php'; }
    $qtdApoio = trmat_total(db(), (int)$ap['id']) + trmat_total_ref(db(), (int)$ap['id']);
}

if (!isset($qtdObrigatorio)) {
    if (!function_exists('tro_colaboradores_pendentes')) { require_once __DIR__ . '/_obrigatoriedade.php'; }
    try {
        $qtdObrigatorio = count(tro_colaboradores_pendentes(db(), (int)$ap['id']));
    } catch (Throwable $e) {
        $qtdObrigatorio = 0;
    }
}
?>
<header class="trg-cabecalho">

  <div class="trg-topo">
    <a class="trg-voltar" href="<?= h(tr_url('index.php')) ?>" title="Voltar para treinamentos">
      <i class="bi bi-arrow-left"></i>
    </a>

    <div class="trg-id">
      <span class="tr-status tr-status-<?= h($ap['status']) ?>"><?= h(ucfirst($ap['status'])) ?></span>
      <h1><?= h($ap['titulo']) ?></h1>
      <?php if (($ap['subtitulo'] ?? '') !== ''): ?>
        <p><?= h($ap['subtitulo']) ?></p>
      <?php endif; ?>
    </div>

    <div class="trg-topo-acoes">
      <a class="btn btn-sm tr-btn-ghost" href="<?= h(tr_url('player.php')) ?>?id=<?= (int)$ap['id'] ?>" target="_blank">
        <i class="bi bi-play"></i> Pré-visualizar
      </a>
      <?php if (tr_pode('treinamentos.apresentacoes.editar')): ?>
        <a class="btn btn-sm tr-btn-primaria" href="<?= h(tr_url('editor.php')) ?>?id=<?= (int)$ap['id'] ?>">
          <i class="bi bi-pencil"></i> Editar conteúdo
        </a>
      <?php endif; ?>
    </div>
  </div>

  <nav class="trg-abas" aria-label="Seções do treinamento">
    <?php foreach ($abas as $chave => $info): ?>
      <?php
        [$rotulo, $icone, $arquivo] = $info;
        $selo = '';
        if ($chave === 'matriculas' && !empty($qtdMatriculas)) { $selo = (string)(int)$qtdMatriculas; }
        if ($chave === 'prova' && !empty($temProva))           { $selo = (string)(int)($qtdQuestoes ?? 0); }
        if ($chave === 'apoio' && !empty($qtdApoio))           { $selo = (string)(int)$qtdApoio; }
        if ($chave === 'obrigatorio' && !empty($qtdObrigatorio)) { $selo = (string)(int)$qtdObrigatorio; }
      ?>
      <a class="trg-aba<?= $abaAtiva === $chave ? ' is-ativa' : '' ?>"
         href="<?= h(tr_url($arquivo)) ?>?id=<?= (int)$ap['id'] ?>">
        <i class="bi bi-<?= h($icone) ?>"></i>
        <span><?= h($rotulo) ?></span>
        <?php if ($selo !== ''): ?><em><?= h($selo) ?></em><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
</header>

<?php if ((string)$ap['status'] !== 'publicado'): ?>
  <div class="trg-aviso">
    <i class="bi bi-exclamation-triangle"></i>
    <div>
      <strong>Este treinamento ainda não está publicado.</strong>
      Os colaboradores verão um aviso de indisponibilidade ao abrir o link individual.
      Publique-o na tela de treinamentos para liberar o acesso.
    </div>
  </div>
<?php endif; ?>
