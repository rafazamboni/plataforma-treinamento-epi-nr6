<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_once __DIR__ . '/_matriculas.php';
require_once __DIR__ . '/_prova.php';
require_once __DIR__ . '/_certificado_dados.php';

$pdo    = db();
$codigo = strtoupper(trim((string)($_GET['c'] ?? '')));
$insc   = $codigo !== '' ? trm_inscricao_por_certificado($pdo, $codigo) : null;
$ap     = $insc ? tr_apresentacao($pdo, (int)$insc['apresentacao_id']) : null;

if (!$insc || !$ap || (int)($ap['emitir_certificado'] ?? 1) !== 1) {
    $destino = tr_url('validar.php') . ($codigo !== '' ? '?c=' . rawurlencode($codigo) : '');
    header('Location: ' . $destino);
    exit;
}

$cfg = trc_cfg($ap);
$d   = trc_dados($insc, $ap, $cfg);

$verso = $cfg['verso'] ? trc_verso_itens($cfg, $pdo, (int)$ap['id']) : [];
$temVerso = $cfg['verso'] && ($verso || trim((string)$cfg['verso_intro']) !== '');

$fontes = trc_fontes_url([$cfg['fonte_titulo'], $cfg['fonte_texto'], $cfg['fonte_nome']]);

$nomePdf = 'certificado-' . tr_slugify((string)$d['curso']) . '-'
         . preg_replace('/[^A-Za-z0-9\-]+/', '', (string)$d['codigo']) . '.pdf';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Certificado — <?= h($d['nome']) ?></title>
<link href="/assets/vendor/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<?php if ($fontes !== ''): ?>
  <link href="<?= h($fontes) ?>" rel="stylesheet">
<?php endif; ?>
<link href="<?= h(tr_url('assets/css/certificado.css')) ?>" rel="stylesheet">
<style><?= trc_page_css($cfg) ?></style>
</head>
<body class="cert-body">

<div class="cert-barra">
  <span class="cert-barra-info">
    <i class="bi bi-shield-check"></i>
    Código <b><?= h($d['codigo']) ?></b>
  </span>
  <a class="cert-btn" href="<?= h(tr_url('validar.php')) ?>?c=<?= h(rawurlencode($d['codigo'])) ?>">
    <i class="bi bi-patch-check"></i> Validar
  </a>
  <button type="button" class="cert-btn" onclick="window.print()">
    <i class="bi bi-printer"></i> Imprimir
  </button>
  <button type="button" class="cert-btn is-primaria" id="btnPdf" data-arquivo="<?= h($nomePdf) ?>">
    <i class="bi bi-file-earmark-pdf"></i> Baixar PDF
  </button>
</div>

<div class="cert-pilha">
  <?= trc_folha($cfg, $d) ?>
  <?php if ($temVerso): ?>
    <?= trc_verso($cfg, $d, $verso) ?>
  <?php endif; ?>
</div>

<?php if ($temVerso): ?>
  <p class="cert-barra cert-nao-imprime" style="justify-content:center;font-size:13px;color:#6b7c8f">
    No PDF, a frente é a página 1 e o verso é a página 2. Para frente e verso no mesmo papel,
    imprima em <b>&nbsp;duas faces&nbsp;</b>.
  </p>
<?php endif; ?>

<script src="<?= h(tr_url_v('assets/js/html-to-image.js')) ?>" defer></script>
<script src="<?= h(tr_url_v('assets/js/jspdf.umd.min.js')) ?>" defer></script>
<script src="<?= h(tr_url_v('assets/js/certificado.js')) ?>" defer></script>
<script src="<?= h(tr_url_v('assets/js/certificado_pdf.js')) ?>" defer></script>
<script>
  (function () {
    var btn = document.getElementById('btnPdf');
    if (!btn) { return; }
    var rotulo = btn.innerHTML;
    btn.addEventListener('click', function () {
      btn.disabled = true;
      btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Gerando PDF…';
      window.certBaixarPDF(btn.getAttribute('data-arquivo'))
        .catch(function (e) {
          if (window.console) { console.error('Falha ao gerar PDF:', e); }
          alert('Não foi possível gerar o PDF direto neste navegador. '
              + 'Vou abrir a impressão: escolha "Salvar como PDF".');
          window.print();
        })
        .then(function () { btn.disabled = false; btn.innerHTML = rotulo; });
    });
  })();
</script>

</body>
</html>
