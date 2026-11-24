<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_once __DIR__ . '/_matriculas.php';
require_once __DIR__ . '/_prova.php';
require_once __DIR__ . '/_certificado_dados.php';

$pdo = db();

$bruto  = (string)($_POST['c'] ?? $_GET['c'] ?? '');
$codigo = strtoupper(trim($bruto));
$codigo = preg_replace('/\s+/', '', $codigo) ?? '';

function trv_pode_tentar(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) { return true; }

    $agora  = time();
    $janela = 600;
    $limite = 20;

    $reg = $_SESSION['trv_tentativas'] ?? ['inicio' => $agora, 'n' => 0];
    if ($agora - (int)$reg['inicio'] > $janela) {
        $reg = ['inicio' => $agora, 'n' => 0];
    }
    $reg['n']++;
    $_SESSION['trv_tentativas'] = $reg;

    return (int)$reg['n'] <= $limite;
}

$estado   = 'formulario';
$insc     = null;
$ap       = null;
$cfg      = null;
$d        = null;

if ($codigo !== '') {
    if (!trv_pode_tentar()) {
        $estado = 'bloqueado';
    } else {
        $insc = trm_inscricao_por_certificado($pdo, $codigo);
        $ap   = $insc ? tr_apresentacao($pdo, (int)$insc['apresentacao_id']) : null;

        if ($insc && $ap && (int)($ap['emitir_certificado'] ?? 1) === 1) {
            $cfg    = trc_cfg($ap);
            $d      = trc_dados($insc, $ap, $cfg);
            $estado = 'valido';
        } else {
            $estado = 'invalido';
            http_response_code(404);
        }
    }
}

$base = tr_base_url();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Validação de certificado</title>
<link href="/assets/vendor/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="/assets/vendor/fonts/css.php?f=Inter" rel="stylesheet">
<style>
  *{box-sizing:border-box}
  body{
    margin:0;min-height:100vh;padding:32px 16px 60px;background:#eef1f6;color:#1f2d3d;
    font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
    -webkit-font-smoothing:antialiased;display:flex;flex-direction:column;align-items:center;
  }
  .trv-marca{display:flex;align-items:center;gap:10px;margin-bottom:22px;color:#5b6c80}
  .trv-marca img{max-height:34px;max-width:150px;object-fit:contain}
  .trv-marca span{font-size:12px;font-weight:700;letter-spacing:.2em;text-transform:uppercase}

  .trv-cartao{
    width:100%;max-width:620px;background:#fff;border-radius:16px;
    box-shadow:0 18px 46px rgba(20,40,70,.13);padding:30px 28px;
  }
  .trv-cartao + .trv-cartao{margin-top:18px}

  .trv-titulo{font-size:21px;font-weight:700;margin:0 0 6px}
  .trv-sub{font-size:14px;line-height:1.6;color:#6b7c8f;margin:0 0 20px}

  .trv-form{display:flex;gap:10px;flex-wrap:wrap}
  .trv-form input{
    flex:1 1 240px;min-width:0;padding:13px 15px;border:1px solid #d8e0ea;border-radius:11px;
    font-size:16px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:.06em;
    text-transform:uppercase;background:#fbfcfe;color:#1f2d3d;
  }
  .trv-form input:focus{outline:0;border-color:#1e3a5f;background:#fff}
  .trv-form button{
    flex:0 0 auto;padding:13px 24px;border:0;border-radius:11px;cursor:pointer;
    background:#1e3a5f;color:#fff;font-size:15px;font-weight:600;
    display:inline-flex;align-items:center;gap:8px;
  }
  .trv-form button:hover{filter:brightness(1.08)}
  .trv-dica{margin:12px 0 0;font-size:12.5px;color:#8593a5}

  .trv-topo{display:flex;align-items:center;gap:14px;margin-bottom:20px}
  .trv-icone{
    flex:0 0 auto;width:52px;height:52px;border-radius:14px;
    display:grid;place-items:center;font-size:27px;
  }
  .trv-ok .trv-icone{background:#e6f6ee;color:#12805c}
  .trv-erro .trv-icone{background:#fdecec;color:#c0392b}
  .trv-alerta .trv-icone{background:#fdf4e3;color:#a06a12}
  .trv-topo h1{font-size:19px;font-weight:700;margin:0}
  .trv-topo p{margin:3px 0 0;font-size:13.5px;color:#6b7c8f}

  .trv-linhas{border-top:1px solid #eef2f7}
  .trv-linha{display:flex;gap:14px;padding:11px 0;border-bottom:1px solid #eef2f7;font-size:14px}
  .trv-linha dt{flex:0 0 40%;margin:0;color:#8593a5}
  .trv-linha dd{flex:1;margin:0;font-weight:600;word-break:break-word}
  .trv-codigo{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:.05em}

  .trv-acoes{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}
  .trv-btn{
    display:inline-flex;align-items:center;gap:8px;text-decoration:none;cursor:pointer;
    padding:12px 20px;border-radius:11px;font-size:14.5px;font-weight:600;
    border:1px solid #dfe5ee;background:#fff;color:#1f2d3d;
  }
  .trv-btn.is-primaria{background:#1e3a5f;border-color:#1e3a5f;color:#fff}
  .trv-btn:hover{filter:brightness(1.06)}

  .trv-selo{
    margin-top:18px;padding:12px 14px;border-radius:11px;background:#f6f9fc;
    font-size:12.5px;line-height:1.6;color:#6b7c8f;
  }
  @media (max-width:520px){
    .trv-linha{flex-direction:column;gap:2px}
    .trv-linha dt{flex:none}
  }
</style>
</head>
<body>

<div class="trv-marca">
  <?php $logo = trc_logo_url(); ?>
  <?php if ($logo !== ''): ?><img src="<?= h($logo) ?>" alt=""><?php endif; ?>
  <span>Validação de certificado</span>
</div>

<?php if ($estado === 'valido'): ?>
  <section class="trv-cartao trv-ok">
    <div class="trv-topo">
      <div class="trv-icone"><i class="bi bi-patch-check-fill"></i></div>
      <div>
        <h1>Certificado autêntico</h1>
        <p>Este código corresponde a uma conclusão registrada no RH Digital.</p>
      </div>
    </div>

    <dl class="trv-linhas">
      <div class="trv-linha"><dt>Nome</dt><dd><?= h($d['nome']) ?></dd></div>
      <?php $cpfM = trm_cpf_mascarado($d['cpf']); ?>
      <?php if ($cpfM !== ''): ?>
        <div class="trv-linha"><dt>CPF</dt><dd><?= h($cpfM) ?></dd></div>
      <?php endif; ?>
      <div class="trv-linha"><dt>Treinamento</dt><dd><?= h($d['curso']) ?></dd></div>
      <?php if ($d['carga'] > 0): ?>
        <div class="trv-linha"><dt>Carga horária</dt><dd><?= h(trc_carga_fmt($d['carga'])) ?></dd></div>
      <?php endif; ?>
      <div class="trv-linha"><dt>Conclusão</dt><dd><?= h($d['data']) ?> às <?= h($d['hora']) ?></dd></div>
      <?php if ($d['tem_prova']): ?>
        <div class="trv-linha">
          <dt>Avaliação</dt>
          <dd>Aprovado com <?= (int)$d['nota'] ?>% (mínimo <?= (int)$d['nota_minima'] ?>%)</dd>
        </div>
      <?php endif; ?>
      <div class="trv-linha">
        <dt>Código</dt><dd class="trv-codigo"><?= h($d['codigo']) ?></dd>
      </div>
    </dl>

    <div class="trv-acoes">
      <a class="trv-btn is-primaria" href="<?= h(tr_url('certificado.php')) ?>?c=<?= h(rawurlencode($d['codigo'])) ?>">
        <i class="bi bi-file-earmark-text"></i> Abrir o certificado
      </a>
      <a class="trv-btn" href="<?= h(tr_url('validar.php')) ?>">
        <i class="bi bi-arrow-repeat"></i> Validar outro código
      </a>
    </div>

    <p class="trv-selo">
      Consulta realizada em <?= h(date('d/m/Y \à\s H:i')) ?>.
      O CPF é exibido parcialmente por proteção de dados pessoais. Em caso de dúvida sobre
      a autenticidade deste documento, procure o setor de Recursos Humanos.
    </p>
  </section>

<?php elseif ($estado === 'invalido' || $estado === 'bloqueado'): ?>
  <section class="trv-cartao <?= $estado === 'bloqueado' ? 'trv-alerta' : 'trv-erro' ?>">
    <div class="trv-topo">
      <div class="trv-icone">
        <i class="bi bi-<?= $estado === 'bloqueado' ? 'hourglass-split' : 'patch-exclamation-fill' ?>"></i>
      </div>
      <div>
        <?php if ($estado === 'bloqueado'): ?>
          <h1>Muitas consultas seguidas</h1>
          <p>Aguarde alguns minutos antes de tentar novamente.</p>
        <?php else: ?>
          <h1>Certificado não localizado</h1>
          <p>Nenhuma conclusão corresponde ao código <b><?= h($codigo) ?></b>.</p>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($estado !== 'bloqueado'): ?>
      <p class="trv-sub">
        Confira se o código foi digitado exatamente como aparece no certificado,
        inclusive os hifens. Se o problema continuar, procure o setor de Recursos Humanos.
      </p>
      <form class="trv-form" method="post" action="<?= h(tr_url('validar.php')) ?>">
        <input type="text" name="c" value="<?= h($codigo) ?>" maxlength="24"
               autocomplete="off" spellcheck="false" placeholder="NV-0000-000-XXXXXX" autofocus>
        <button type="submit"><i class="bi bi-search"></i> Validar</button>
      </form>
    <?php endif; ?>
  </section>

<?php else: ?>
  <section class="trv-cartao">
    <h1 class="trv-titulo">Validar um certificado</h1>
    <p class="trv-sub">
      Digite o código impresso no certificado ou aponte a câmera para o QR Code do documento.
    </p>

    <form class="trv-form" method="post" action="<?= h(tr_url('validar.php')) ?>">
      <input type="text" name="c" maxlength="24" autocomplete="off" spellcheck="false"
             placeholder="NV-0000-000-XXXXXX" autofocus>
      <button type="submit"><i class="bi bi-search"></i> Validar</button>
    </form>

    <p class="trv-dica">
      O código fica no rodapé do certificado, ao lado do QR Code, no formato
      <b>NV-ANO-TREINAMENTO-IDENTIFICADOR</b>.
    </p>
  </section>
<?php endif; ?>

</body>
</html>
