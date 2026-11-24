<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_once __DIR__ . '/_matriculas.php';
require_once __DIR__ . '/_prova.php';
require_once __DIR__ . '/_certificado_dados.php';

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

$qtdSlides = max(1, count(trm_slides_ids($pdo, $id)));

if (isset($_GET['previa'])) {
    $in  = json_decode((string)($_POST['cfg'] ?? '[]'), true);
    $cfg = trc_cfg_do_form(trc_cfg($ap), is_array($in) ? $in : []);
    $d   = trc_dados_exemplo($ap, $qtdSlides);

    header('Content-Type: text/html; charset=utf-8');

    echo '<div class="crt-face crt-face-frente is-ativa">', trc_folha($cfg, $d, true), '</div>';

    if ($cfg['verso']) {
        $itens = trc_verso_itens($cfg, $pdo, $id);
        echo '<div class="crt-face crt-face-verso">', trc_verso($cfg, $d, $itens, true), '</div>';
    }
    exit;
}

$gerencia    = trm_pode_gerenciar();
$temProva    = trp_prova_ativa($ap, $pdo);
$qtdQuestoes = trp_total_valido($pdo, $id);

$st = $pdo->prepare('SELECT COUNT(*) FROM treinamentos_inscricoes WHERE apresentacao_id = ?');
$st->execute([$id]);
$qtdMatriculas = (int)$st->fetchColumn();

$st = $pdo->prepare(
    'SELECT nome, certificado_codigo FROM treinamentos_inscricoes
      WHERE apresentacao_id = ? AND status = "concluido" AND certificado_codigo <> ""
      ORDER BY concluido_em DESC LIMIT 1'
);
$st->execute([$id]);
$ultimo = $st->fetch(PDO::FETCH_ASSOC) ?: null;

$cfg    = trc_cfg($ap);
$ativo  = (int)($ap['emitir_certificado'] ?? 1) === 1;

$fontesUrl = trc_fontes_url(array_keys(trc_fontes()));

require_once __DIR__ . '/../../../app/layout/header.php';
nv_render_header('Certificado — ' . $ap['titulo']);
?>
<link rel="stylesheet" href="<?= h(tr_url('assets/css/admin.css')) ?>">
<link rel="stylesheet" href="<?= h(tr_url('assets/css/matriculas.css')) ?>">
<link rel="stylesheet" href="<?= h(tr_url('assets/css/gestao.css')) ?>">
<link rel="stylesheet" href="<?= h(tr_url('assets/css/certificado.css')) ?>">
<link rel="stylesheet" href="<?= h(tr_url('assets/css/certificado_admin.css')) ?>">
<link href="<?= h($fontesUrl) ?>" rel="stylesheet">

<div class="tr-wrap trg-wrap">

  <?php $abaAtiva = 'certificado'; require __DIR__ . '/_abas.php'; ?>

  <div class="crt-grade">

    <div class="crt-form">

      <label class="crt-switch">
        <input type="checkbox" id="cAtivo" <?= $ativo ? 'checked' : '' ?>>
        <span>
          <b>Emitir certificado ao concluir</b>
          <small>o colaborador recebe o link por e-mail e pode imprimir quando quiser</small>
        </span>
      </label>

      <div id="cCampos">

        <section class="crt-bloco">
          <h2><i class="bi bi-aspect-ratio"></i> Papel e disposição</h2>

          <div class="crt-linha">
            <div class="crt-campo">
              <label for="cPapel">Tamanho do papel</label>
              <select id="cPapel" data-cfg="papel">
                <option value="a4"    <?= $cfg['papel'] === 'a4' ? 'selected' : '' ?>>A4 (210 × 297 mm)</option>
                <option value="carta" <?= $cfg['papel'] === 'carta' ? 'selected' : '' ?>>Carta (216 × 279 mm)</option>
              </select>
            </div>
            <div class="crt-campo">
              <label for="cOrient">Orientação</label>
              <select id="cOrient" data-cfg="orientacao">
                <option value="paisagem" <?= $cfg['orientacao'] === 'paisagem' ? 'selected' : '' ?>>Deitado</option>
                <option value="retrato"  <?= $cfg['orientacao'] === 'retrato' ? 'selected' : '' ?>>Em pé</option>
              </select>
            </div>
          </div>

          <div class="crt-linha">
            <div class="crt-campo crt-largo">
              <label>Densidade</label>
              <div class="crt-opcoes is-compacto">
                <?php foreach (trc_densidades() as $k => $v): ?>
                  <label class="crt-opcao">
                    <input type="radio" name="cDensidade" value="<?= h($k) ?>" data-cfg="densidade"
                      <?= $cfg['densidade'] === $k ? 'checked' : '' ?>>
                    <b><?= h($v[0]) ?></b><small><?= h($v[1]) ?></small>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="crt-linha">
            <div class="crt-campo crt-largo">
              <label>Alinhamento do conteúdo</label>
              <div class="crt-opcoes is-compacto">
                <?php foreach (trc_alinhamentos() as $k => $rot): ?>
                  <label class="crt-opcao">
                    <input type="radio" name="cAlinhamento" value="<?= h($k) ?>" data-cfg="alinhamento"
                      <?= $cfg['alinhamento'] === $k ? 'checked' : '' ?>>
                    <b><?= h($rot) ?></b>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </section>

        <section class="crt-bloco">
          <h2><i class="bi bi-palette"></i> Estilo, moldura e fundo</h2>

          <div class="crt-campo crt-largo">
            <label>Estilo</label>
            <div class="crt-opcoes">
              <?php foreach (trc_estilos() as $k => $v): ?>
                <label class="crt-opcao">
                  <input type="radio" name="cEstilo" value="<?= h($k) ?>" data-cfg="estilo"
                    <?= $cfg['estilo'] === $k ? 'checked' : '' ?>>
                  <b><?= h($v[0]) ?></b><small><?= h($v[1]) ?></small>
                </label>
              <?php endforeach; ?>
            </div>
            <small>O estilo define apenas o ornamento. Moldura, alinhamento e tipografia são escolhas separadas.</small>
          </div>

          <div class="crt-linha" style="margin-top:14px">
            <div class="crt-campo crt-largo">
              <label>Moldura</label>
              <div class="crt-opcoes is-compacto">
                <?php foreach (trc_bordas() as $k => $v): ?>
                  <label class="crt-opcao">
                    <input type="radio" name="cBorda" value="<?= h($k) ?>" data-cfg="borda"
                      <?= $cfg['borda'] === $k ? 'checked' : '' ?>>
                    <b><?= h($v[0]) ?></b><small><?= h($v[1]) ?></small>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="crt-linha">
            <div class="crt-campo crt-largo">
              <label>Fundo</label>
              <div class="crt-opcoes is-compacto">
                <?php foreach (trc_fundos() as $k => $v): ?>
                  <label class="crt-opcao">
                    <input type="radio" name="cFundo" value="<?= h($k) ?>" data-cfg="fundo"
                      <?= $cfg['fundo'] === $k ? 'checked' : '' ?>>
                    <b><?= h($v[0]) ?></b><small><?= h($v[1]) ?></small>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="crt-linha">
            <div class="crt-campo">
              <label for="cCorModo">Cores</label>
              <select id="cCorModo" data-cfg="cor_modo">
                <option value="tema"   <?= $cfg['cor_modo'] === 'tema' ? 'selected' : '' ?>>Usar as cores do treinamento</option>
                <option value="propria"<?= $cfg['cor_modo'] === 'propria' ? 'selected' : '' ?>>Definir cores só do certificado</option>
              </select>
            </div>
            <div class="crt-campo" id="cCoresCampo">
              <label>Cores do certificado</label>
              <div class="crt-cores">
                <span class="crt-cor">
                  <input type="color" id="cCorPrimaria" data-cfg="primaria" value="<?= h($cfg['primaria']) ?>"> Primária
                </span>
                <span class="crt-cor">
                  <input type="color" id="cCorAcento" data-cfg="acento" value="<?= h($cfg['acento']) ?>"> Acento
                </span>
              </div>
            </div>
          </div>
        </section>

        <section class="crt-bloco">
          <h2><i class="bi bi-type"></i> Tipografia</h2>

          <div class="crt-linha">
            <div class="crt-campo">
              <label for="cFonteTitulo">Fonte dos títulos</label>
              <select id="cFonteTitulo" data-cfg="fonte_titulo">
                <?php foreach (trc_fontes_texto() as $k => $f): ?>
                  <option value="<?= h($k) ?>" <?= $cfg['fonte_titulo'] === $k ? 'selected' : '' ?>>
                    <?= h($f['rotulo']) ?> — <?= h($f['nota']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="crt-campo">
              <label for="cFonteTexto">Fonte do corpo</label>
              <select id="cFonteTexto" data-cfg="fonte_texto">
                <?php foreach (trc_fontes_texto() as $k => $f): ?>
                  <option value="<?= h($k) ?>" <?= $cfg['fonte_texto'] === $k ? 'selected' : '' ?>>
                    <?= h($f['rotulo']) ?> — <?= h($f['nota']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="crt-campo">
              <label for="cFonteNome">Fonte do nome</label>
              <select id="cFonteNome" data-cfg="fonte_nome">
                <?php foreach (trc_fontes() as $k => $f): ?>
                  <option value="<?= h($k) ?>" <?= $cfg['fonte_nome'] === $k ? 'selected' : '' ?>>
                    <?= h($f['rotulo']) ?> — <?= h($f['nota']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="crt-linha">
            <div class="crt-campo">
              <label>Destaque do nome</label>
              <div class="crt-opcoes is-compacto">
                <?php foreach (trc_nome_estilos() as $k => $v): ?>
                  <label class="crt-opcao">
                    <input type="radio" name="cNomeEstilo" value="<?= h($k) ?>" data-cfg="nome_estilo"
                      <?= $cfg['nome_estilo'] === $k ? 'checked' : '' ?>>
                    <b><?= h($v[0]) ?></b>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="crt-campo">
              <label for="cNomeTamanho">Tamanho do nome</label>
              <div class="crt-slider">
                <input type="range" id="cNomeTamanho" data-cfg="nome_tamanho"
                       min="70" max="160" step="5" value="<?= (int)$cfg['nome_tamanho'] ?>">
                <output id="cNomeTamanhoValor"><?= (int)$cfg['nome_tamanho'] ?>%</output>
              </div>
              <label class="crt-chip" style="margin-top:8px;align-self:flex-start">
                <input type="checkbox" id="cNomeAlta" data-cfg="nome_alta" <?= $cfg['nome_alta'] ? 'checked' : '' ?>>
                <span>Nome em caixa alta</span>
              </label>
            </div>
          </div>
        </section>

        <section class="crt-bloco">
          <h2><i class="bi bi-fonts"></i> Textos</h2>

          <div class="crt-linha">
            <div class="crt-campo">
              <label for="cTitulo">Título</label>
              <input type="text" id="cTitulo" data-cfg="titulo" maxlength="60" value="<?= h($cfg['titulo']) ?>">
            </div>
            <div class="crt-campo">
              <label for="cSubtitulo">Subtítulo</label>
              <input type="text" id="cSubtitulo" data-cfg="subtitulo" maxlength="90" value="<?= h($cfg['subtitulo']) ?>">
            </div>
          </div>

          <div class="crt-linha">
            <div class="crt-campo crt-largo">
              <label for="cTitulosTamanho">Tamanho dos textos (título, subtítulo, nome do treinamento e demais)</label>
              <div class="crt-slider">
                <input type="range" id="cTitulosTamanho" data-cfg="titulos_tamanho"
                       min="70" max="160" step="5" value="<?= (int)$cfg['titulos_tamanho'] ?>">
                <output id="cTitulosTamanhoValor"><?= (int)$cfg['titulos_tamanho'] ?>%</output>
              </div>
              <small>Não afeta o nome do participante, que tem o próprio ajuste de tamanho acima.</small>
            </div>
          </div>

          <div class="crt-linha">
            <div class="crt-campo">
              <label for="cPreambulo">Linha antes do nome</label>
              <input type="text" id="cPreambulo" data-cfg="preambulo" maxlength="90" value="<?= h($cfg['preambulo']) ?>">
              <small>Ex.: “Certificamos que”, “Conferimos a”.</small>
            </div>
            <div class="crt-campo">
              <label for="cEmpresa">Nome da empresa</label>
              <input type="text" id="cEmpresa" data-cfg="empresa" maxlength="90" value="<?= h($cfg['empresa']) ?>">
            </div>
          </div>

          <div class="crt-linha">
            <div class="crt-campo crt-largo">
              <label for="cEmpresaTamanho">Tamanho do nome da empresa (canto superior direito)</label>
              <div class="crt-slider">
                <input type="range" id="cEmpresaTamanho" data-cfg="empresa_tamanho"
                       min="50" max="200" step="5" value="<?= (int)$cfg['empresa_tamanho'] ?>">
                <output id="cEmpresaTamanhoValor"><?= (int)$cfg['empresa_tamanho'] ?>%</output>
              </div>
            </div>
          </div>

          <div class="crt-linha">
            <div class="crt-campo crt-largo">
              <label for="cFrase">Frase de certificação</label>
              <input type="text" id="cFrase" data-cfg="frase" maxlength="400"
                     placeholder="concluiu integralmente o treinamento" value="<?= h($cfg['frase']) ?>">
              <small>Em branco usa a frase padrão. Aceita <code>{nome}</code>, <code>{cpf}</code>,
                <code>{treinamento}</code>, <code>{data}</code>, <code>{carga}</code> e <code>{empresa}</code>.</small>
            </div>
          </div>

          <div class="crt-linha">
            <div class="crt-campo crt-largo">
              <label for="cRodape">Observação de rodapé</label>
              <input type="text" id="cRodape" data-cfg="rodape" maxlength="240"
                     placeholder="Ex.: Treinamento conforme NR-01, item 1.5.3." value="<?= h($cfg['rodape']) ?>">
            </div>
          </div>

          <div class="crt-linha">
            <div class="crt-campo">
              <label for="cCidade">Cidade da emissão</label>
              <input type="text" id="cCidade" data-cfg="cidade" maxlength="60"
                     placeholder="Ex.: Curitiba" value="<?= h($cfg['cidade']) ?>">
            </div>
            <div class="crt-campo">
              <label>Local e data</label>
              <div class="crt-chips">
                <label class="crt-chip">
                  <input type="checkbox" id="cLocalData" data-cfg="local_data" <?= $cfg['local_data'] ? 'checked' : '' ?>>
                  <span>Exibir na folha</span>
                </label>
                <label class="crt-chip">
                  <input type="checkbox" id="cDataExtenso" data-cfg="data_extenso" <?= $cfg['data_extenso'] ? 'checked' : '' ?>>
                  <span>Data por extenso</span>
                </label>
              </div>
            </div>
          </div>
        </section>

        <section class="crt-bloco">
          <h2><i class="bi bi-pen"></i> Assinaturas e selo</h2>

          <div class="crt-linha is-2">
            <div class="crt-campo">
              <label for="cAssina2Nome">Assinatura — nome</label>
              <input type="text" id="cAssina2Nome" data-cfg="assina2_nome" maxlength="90"
                     placeholder="Opcional — deixe em branco para não mostrar" value="<?= h($cfg['assina2_nome']) ?>">
            </div>
            <div class="crt-campo">
              <label for="cAssina2Cargo">Assinatura — cargo</label>
              <input type="text" id="cAssina2Cargo" data-cfg="assina2_cargo" maxlength="90" value="<?= h($cfg['assina2_cargo']) ?>">
            </div>
          </div>

          <div class="crt-linha">
            <div class="crt-campo crt-largo">
              <small>O selo de conclusão aparece no lugar da assinatura padrão, em tamanho maior — configure-o abaixo.</small>
            </div>
          </div>

          <div class="crt-linha">
            <div class="crt-campo">
              <label for="cSeloIcone">Selo</label>
              <select id="cSeloIcone" data-cfg="selo_icone">
                <?php foreach (trc_selos() as $k => $rot): ?>
                  <option value="<?= h($k) ?>" <?= $cfg['selo_icone'] === $k ? 'selected' : '' ?>><?= h($rot) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="crt-campo">
              <label for="cSeloTexto">Texto do selo</label>
              <input type="text" id="cSeloTexto" data-cfg="selo_texto" maxlength="40" value="<?= h($cfg['selo_texto']) ?>">
            </div>
            <div class="crt-campo">
              <label>Exibição</label>
              <label class="crt-chip" style="align-self:flex-start">
                <input type="checkbox" id="cSelo" data-cfg="selo" <?= $cfg['selo'] ? 'checked' : '' ?>>
                <span>Mostrar o selo</span>
              </label>
            </div>
          </div>

          <div class="crt-linha">
            <div class="crt-campo crt-largo">
              <label for="cSeloTamanho">Tamanho do selo</label>
              <div class="crt-slider">
                <input type="range" id="cSeloTamanho" data-cfg="selo_tamanho"
                       min="50" max="200" step="5" value="<?= (int)$cfg['selo_tamanho'] ?>">
                <output id="cSeloTamanhoValor"><?= (int)$cfg['selo_tamanho'] ?>%</output>
              </div>
            </div>
          </div>
        </section>

        <section class="crt-bloco">
          <h2><i class="bi bi-list-check"></i> O que aparece na folha</h2>
          <div class="crt-chips">
            <?php foreach ([
              'cLogo'   => ['logo',        'Logo da empresa'],
              'cMarca'  => ['marca_dagua', 'Marca d’água'],
              'cCodigo' => ['codigo',      'Bloco de validação'],
              'cCpf'    => ['cpf',         'CPF e lotação'],
              'cCarga'  => ['carga',       'Carga horária'],
              'cTempo'  => ['tempo',       'Tempo dedicado'],
              'cSlides' => ['slides',      'Slides concluídos'],
              'cNota'   => ['nota',        'Nota da prova'],
            ] as $inputId => $par): ?>
              <label class="crt-chip">
                <input type="checkbox" id="<?= h($inputId) ?>" data-cfg="<?= h($par[0]) ?>"
                  <?= $cfg[$par[0]] ? 'checked' : '' ?>>
                <span><?= $par[1] ?></span>
              </label>
            <?php endforeach; ?>
          </div>

          <div class="crt-linha" style="margin-top:12px">
            <div class="crt-campo crt-largo">
              <label for="cLogoTamanho">Tamanho da logo (canto superior esquerdo)</label>
              <div class="crt-slider">
                <input type="range" id="cLogoTamanho" data-cfg="logo_tamanho"
                       min="50" max="200" step="5" value="<?= (int)$cfg['logo_tamanho'] ?>">
                <output id="cLogoTamanhoValor"><?= (int)$cfg['logo_tamanho'] ?>%</output>
              </div>
            </div>
          </div>
        </section>

        <section class="crt-bloco">
          <h2><i class="bi bi-droplet"></i> Marca d'água</h2>

          <div class="crt-linha">
            <div class="crt-campo">
              <label for="cMarcaTamanho">Tamanho</label>
              <div class="crt-slider">
                <input type="range" id="cMarcaTamanho" data-cfg="marca_tamanho" min="4" max="24" step="1"
                       value="<?= (int)$cfg['marca_tamanho'] ?>">
                <output id="cMarcaTamanhoValor"><?= (int)$cfg['marca_tamanho'] ?>%</output>
              </div>
            </div>
            <div class="crt-campo">
              <label for="cMarcaOpacidade">Transparência</label>
              <div class="crt-slider">
                <input type="range" id="cMarcaOpacidade" data-cfg="marca_opacidade" min="1" max="30" step="1"
                       value="<?= (int)$cfg['marca_opacidade'] ?>">
                <output id="cMarcaOpacidadeValor"><?= (int)$cfg['marca_opacidade'] ?>%</output>
              </div>
              <small>Quanto menor, mais discreta.</small>
            </div>
          </div>

          <div class="crt-linha">
            <div class="crt-campo">
              <label for="cMarcaEspacoH">Espaçamento horizontal</label>
              <div class="crt-slider">
                <input type="range" id="cMarcaEspacoH" data-cfg="marca_espaco_h" min="2" max="30" step="1"
                       value="<?= (int)$cfg['marca_espaco_h'] ?>">
                <output id="cMarcaEspacoHValor"><?= (int)$cfg['marca_espaco_h'] ?>%</output>
              </div>
            </div>
            <div class="crt-campo">
              <label for="cMarcaEspacoV">Espaçamento vertical</label>
              <div class="crt-slider">
                <input type="range" id="cMarcaEspacoV" data-cfg="marca_espaco_v" min="2" max="30" step="1"
                       value="<?= (int)$cfg['marca_espaco_v'] ?>">
                <output id="cMarcaEspacoVValor"><?= (int)$cfg['marca_espaco_v'] ?>%</output>
              </div>
              <small>As fileiras se alternam sozinhas, tipo tijolos — não precisa configurar isso.</small>
            </div>
          </div>
        </section>

        <section class="crt-bloco">
          <h2><i class="bi bi-qr-code"></i> QR Code de validação</h2>

          <label class="crt-chip" style="margin-bottom:12px">
            <input type="checkbox" id="cQr" data-cfg="qr" <?= $cfg['qr'] ? 'checked' : '' ?>>
            <span>Imprimir o QR Code</span>
          </label>

          <div class="crt-linha">
            <div class="crt-campo">
              <label for="cQrPosicao">Posição</label>
              <select id="cQrPosicao" data-cfg="qr_posicao">
                <?php foreach (trc_qr_posicoes() as $k => $rot): ?>
                  <option value="<?= h($k) ?>" <?= $cfg['qr_posicao'] === $k ? 'selected' : '' ?>><?= h($rot) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="crt-campo">
              <label for="cQrTamanho">Tamanho impresso</label>
              <div class="crt-slider">
                <input type="range" id="cQrTamanho" data-cfg="qr_tamanho" min="14" max="40" step="1"
                       value="<?= (int)$cfg['qr_tamanho'] ?>">
                <output id="cQrTamanhoValor"><?= (int)$cfg['qr_tamanho'] ?> mm</output>
              </div>
              <small>Abaixo de 18 mm, alguns leitores têm dificuldade em papel comum.</small>
            </div>
            <div class="crt-campo">
              <label for="cQrNivel">Tolerância a falhas</label>
              <select id="cQrNivel" data-cfg="qr_nivel">
                <option value="M" <?= $cfg['qr_nivel'] === 'M' ? 'selected' : '' ?>>Média — código menor</option>
                <option value="Q" <?= $cfg['qr_nivel'] === 'Q' ? 'selected' : '' ?>>Alta — recomendado</option>
                <option value="H" <?= $cfg['qr_nivel'] === 'H' ? 'selected' : '' ?>>Máxima — resiste a dobras</option>
              </select>
            </div>
          </div>

          <div class="crt-linha">
            <div class="crt-campo crt-largo">
              <label for="cQrLegenda">Legenda sob o código</label>
              <input type="text" id="cQrLegenda" data-cfg="qr_legenda" maxlength="60" value="<?= h($cfg['qr_legenda']) ?>">
              <small>O código aponta para <code><?= h(tr_url_absoluta('validar.php')) ?></code>,
                onde também é possível digitar o código manualmente.</small>
            </div>
          </div>
        </section>

        <section class="crt-bloco">
          <h2><i class="bi bi-file-earmark-text"></i> Verso — conteúdo programático</h2>

          <label class="crt-switch" style="margin-bottom:14px">
            <input type="checkbox" id="cVerso" data-cfg="verso" <?= $cfg['verso'] ? 'checked' : '' ?>>
            <span>
              <b>Imprimir o verso</b>
              <small>sai como segunda página; imprima em frente e verso para ficar no mesmo papel</small>
            </span>
          </label>

          <div id="cVersoCampos">
            <div class="crt-linha">
              <div class="crt-campo">
                <label for="cVersoTitulo">Título do verso</label>
                <input type="text" id="cVersoTitulo" data-cfg="verso_titulo" maxlength="90" value="<?= h($cfg['verso_titulo']) ?>">
              </div>
              <div class="crt-campo">
                <label for="cVersoModo">Origem do conteúdo</label>
                <select id="cVersoModo" data-cfg="verso_modo">
                  <?php foreach (trc_verso_modos() as $k => $v): ?>
                    <option value="<?= h($k) ?>" <?= $cfg['verso_modo'] === $k ? 'selected' : '' ?>>
                      <?= h($v[0]) ?> — <?= h($v[1]) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="crt-linha">
              <div class="crt-campo crt-largo">
                <label for="cVersoIntro">Texto de abertura</label>
                <input type="text" id="cVersoIntro" data-cfg="verso_intro" maxlength="400"
                       placeholder="Ex.: Conteúdo ministrado na modalidade a distância, com avaliação final."
                       value="<?= h($cfg['verso_intro']) ?>">
              </div>
            </div>

            <div class="crt-linha">
              <div class="crt-campo crt-largo">
                <label for="cVersoTexto">Conteúdo próprio</label>
                <textarea id="cVersoTexto" data-cfg="verso_texto" maxlength="8000"
                          placeholder="# Módulo 1 — Fundamentos&#10;O que é o programa | Definições e base legal&#10;Perfil do público"><?= h($cfg['verso_texto']) ?></textarea>
                <small>
                  Uma linha por tópico. Linha começando com <code>#</code> vira título de seção;
                  use <code>|</code> para separar o tópico da descrição. Só é usado nos modos
                  <b>Manual</b> e <b>Os dois</b>.
                </small>
              </div>
            </div>

            <div class="crt-linha">
              <div class="crt-campo">
                <label for="cVersoColunas">Colunas da lista</label>
                <select id="cVersoColunas" data-cfg="verso_colunas">
                  <option value="1" <?= $cfg['verso_colunas'] === 1 ? 'selected' : '' ?>>Uma coluna</option>
                  <option value="2" <?= $cfg['verso_colunas'] === 2 ? 'selected' : '' ?>>Duas colunas</option>
                </select>
              </div>
              <div class="crt-campo">
                <label>Complementos</label>
                <div class="crt-chips">
                  <label class="crt-chip">
                    <input type="checkbox" id="cVersoNumera" data-cfg="verso_numera" <?= $cfg['verso_numera'] ? 'checked' : '' ?>>
                    <span>Numerar</span>
                  </label>
                  <label class="crt-chip">
                    <input type="checkbox" id="cVersoCarga" data-cfg="verso_carga" <?= $cfg['verso_carga'] ? 'checked' : '' ?>>
                    <span>Carga horária</span>
                  </label>
                  <label class="crt-chip">
                    <input type="checkbox" id="cVersoProva" data-cfg="verso_prova" <?= $cfg['verso_prova'] ? 'checked' : '' ?>>
                    <span>Notas da prova</span>
                  </label>
                </div>
              </div>
            </div>

            <div class="crt-linha">
              <div class="crt-campo crt-largo">
                <label for="cVersoRodape">Rodapé do verso</label>
                <input type="text" id="cVersoRodape" data-cfg="verso_rodape" maxlength="240"
                       placeholder="Ex.: Este anexo é parte integrante do certificado e não tem validade isoladamente."
                       value="<?= h($cfg['verso_rodape']) ?>">
              </div>
            </div>
          </div>
        </section>
      </div>

      <?php if ($gerencia): ?>
        <div class="crt-pe">
          <span class="crt-estado" id="cEstado"></span>
          <span class="crt-espaco"></span>
          <button type="button" class="btn tr-btn-primaria" id="cSalvar">Salvar modelo</button>
        </div>
      <?php endif; ?>
    </div>

    <div class="crt-previa">
      <div class="crt-previa-topo">
        <span><i class="bi bi-eye"></i> Prévia</span>
        <div class="crt-abas">
          <button type="button" class="is-ativa" data-face="frente">Frente</button>
          <button type="button" data-face="verso" id="cAbaVerso" <?= $cfg['verso'] ? '' : 'disabled' ?>>Verso</button>
        </div>
        <span class="crt-espaco"></span>
        <button type="button" class="crt-ampliar" id="cAmpliar">
          <i class="bi bi-arrows-fullscreen"></i> Ampliar
        </button>
        <?php if ($ultimo): ?>
          <a class="btn btn-sm tr-btn-ghost"
             href="<?= h(trm_link_certificado((string)$ultimo['certificado_codigo'])) ?>" target="_blank">
            <i class="bi bi-box-arrow-up-right"></i> Último emitido
          </a>
        <?php endif; ?>
      </div>

      <div class="crt-palco" id="cPalco">
        <div class="crt-vazio">Montando a prévia…</div>
      </div>

      <p class="crt-nota">
        A prévia é gerada pelo mesmo código da folha impressa — inclusive o QR Code.
        Não há PDF armazenado: o arquivo sai pelo diálogo de impressão do navegador.
      </p>
      <p class="crt-nota">
        Validação pública em <code><?= h(tr_url_absoluta('validar.php')) ?></code>,
        por leitura do QR Code ou digitação do código.
      </p>
    </div>
  </div>

  <div class="crt-modal" id="cModal">
    <div class="crt-modal-caixa">
      <button type="button" class="crt-modal-fechar" id="cModalFechar" aria-label="Fechar prévia ampliada">
        <i class="bi bi-x-lg"></i>
      </button>
      <div class="crt-abas" style="margin-bottom:16px">
        <button type="button" class="is-ativa" data-face="frente" id="cModalAbaFrente">Frente</button>
        <button type="button" data-face="verso" id="cModalAbaVerso" <?= $cfg['verso'] ? '' : 'disabled' ?>>Verso</button>
      </div>
      <div id="cModalPalco"></div>
    </div>
  </div>
</div>

<script>
  window.TR_CSRF     = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window.TR_API      = <?= json_encode(tr_url('api.php'), JSON_HEX_TAG) ?>;
  window.TRM_APRES   = <?= (int)$id ?>;
  window.TRC_PREVIA  = <?= json_encode(tr_url('certificado_config.php') . '?previa=1&id=' . $id, JSON_HEX_TAG) ?>;
  window.TRC_GERENCIA= <?= $gerencia ? 'true' : 'false' ?>;
</script>
<script src="<?= h(tr_url('assets/js/ui.js')) ?>"></script>
<script src="<?= h(tr_url('assets/js/certificado.js')) ?>"></script>
<script src="<?= h(tr_url('assets/js/certificado_config.js')) ?>"></script>

<?php require_once __DIR__ . '/../../../app/layout/footer.php'; ?>
