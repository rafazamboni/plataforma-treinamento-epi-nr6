<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_lib.php';

require_login();
tr_exigir('treinamentos.apresentacoes.ver');

$pdo = db();

if (isset($_GET['av'])) {
    $av = tr_avatar($pdo, (int)$_GET['av']);
    if (!$av) { http_response_code(404); exit; }

    if (isset($_GET['thumb'])) {
        $caminho = tr_avatar_thumb_caminho($av);
        if ($caminho === null) { http_response_code(404); exit; }
        tr_enviar_arquivo($caminho);
    }
    if (isset($_GET['modelo'])) {
        $caminho = tr_avatar_modelo_caminho($av);
        if ($caminho === null) { http_response_code(404); exit; }
        tr_enviar_arquivo($caminho);
    }
    http_response_code(404);
    exit;
}

$gerencia = tr_pode('treinamentos.instrutores.gerenciar')
         || tr_pode('treinamentos.apresentacoes.publicar');

$avatares = tr_avatares($pdo, false);

$usos = [];
foreach ($pdo->query(
    'SELECT avatar_id, COUNT(*) n FROM treinamentos_apresentacoes
      WHERE avatar_id IS NOT NULL GROUP BY avatar_id'
)->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $usos[(int)$r['avatar_id']] = (int)$r['n'];
}

require_once __DIR__ . '/../../../app/layout/header.php';
nv_render_header('Instrutores e vozes');
?>
<link rel="stylesheet" href="<?= h(tr_url('assets/css/admin.css')) ?>">
<link rel="stylesheet" href="<?= h(tr_url('assets/css/instrutores.css')) ?>">

<script type="importmap">
{
  "imports": {
    "three": "/assets/vendor/three@0.180.0/build/three.module.js",
    "three/addons/": "/assets/vendor/three@0.180.0/examples/jsm/",
    "@pixiv/three-vrm": "/assets/vendor/three-vrm@3.5.5/lib/three-vrm.module.min.js"
  }
}
</script>

<div class="tr-wrap tri-wrap">

  <div class="tr-toolbar">
    <div class="tr-migalha">
      <a href="<?= h(tr_url('index.php')) ?>"><i class="bi bi-arrow-left"></i> Treinamentos</a>
      <span>Instrutores e vozes</span>
    </div>
    <?php if ($gerencia): ?>
      <button type="button" class="btn tr-btn-primaria btn-sm" id="btnNovo">
        <i class="bi bi-plus-lg"></i> Novo instrutor
      </button>
    <?php endif; ?>
  </div>

  <p class="tri-intro">
    Cada instrutor guarda o modelo 3D e a voz que fala por ele. No editor do
    treinamento você só escolhe quem narra — velocidade, tom, pausa e qualidade
    vêm junto e ficam iguais em todas as apresentações daquele instrutor.
  </p>

  <div class="tri-layout">

    <aside class="tri-lista" id="triLista">
      <?php if (!$avatares): ?>
        <div class="tri-vazio">
          <i class="bi bi-person-badge"></i>
          <p>Nenhum instrutor cadastrado.</p>
        </div>
      <?php endif; ?>

      <?php foreach ($avatares as $av): ?>
        <?php
          $temModelo = tr_avatar_modelo_caminho($av) !== null;
          $temThumb  = tr_avatar_thumb_caminho($av) !== null;
        ?>
        <button type="button" class="tri-item<?= (int)$av['ativo'] === 1 ? '' : ' is-inativo' ?>"
                data-av="<?= (int)$av['id'] ?>"
                data-json="<?= h(json_encode([
                  'id'             => (int)$av['id'],
                  'nome'           => (string)$av['nome'],
                  'descricao'      => (string)$av['descricao'],
                  'arquivo'        => (string)$av['arquivo'],
                  'camera'         => (string)$av['camera'],
                  'humor'          => (string)$av['humor'],
                  'voz_nome'       => (string)$av['voz_nome'],
                  'voz_velocidade' => (float)$av['voz_velocidade'],
                  'voz_tom'        => (float)$av['voz_tom'],
                  'voz_pausa_ms'   => (int)$av['voz_pausa_ms'],
                  'voz_ganho'      => (float)$av['voz_ganho'],
                  'voz_qualidade'  => (string)$av['voz_qualidade'],
                  'voz_kbps'       => (int)$av['voz_kbps'],
                  'ativo'          => (int)$av['ativo'],
                  'tem_modelo'     => $temModelo,

                  'mao_dominante'   => (string)($av['mao_dominante'] ?? 'direita'),
                  'orient_auto'     => (int)($av['orient_auto'] ?? 1),
                  'giro_corpo'      => (string)($av['giro_corpo'] ?? 'leve'),
                  'giro_quadril'    => (int)($av['giro_quadril'] ?? 0),
                  'forca_tronco'    => (float)($av['forca_tronco'] ?? 1),
                  'forca_cabeca'    => (float)($av['forca_cabeca'] ?? 1),
                  'forca_gesto'     => (float)($av['forca_gesto'] ?? 1),
                  'olhar_cabeca'    => (float)($av['olhar_cabeca'] ?? 0.35),
                  'rosto_publico'   => (float)($av['rosto_publico'] ?? 0.5),
                  'altura_conteudo' => (float)($av['altura_conteudo'] ?? 0),
                  'suavidade'       => (float)($av['suavidade'] ?? 1),
                  'olhar_padrao'    => (string)($av['olhar_padrao'] ?? 'camera'),
                ], JSON_UNESCAPED_UNICODE)) ?>">
          <span class="tri-item-foto">
            <?php if ($temThumb): ?>
              <img src="<?= h(tr_url('instrutores.php')) ?>?av=<?= (int)$av['id'] ?>&amp;thumb=1&amp;v=<?= (int)filemtime(tr_avatar_thumb_caminho($av)) ?>" alt="">
            <?php else: ?>
              <i class="bi bi-person-badge"></i>
            <?php endif; ?>
          </span>
          <span class="tri-item-txt">
            <b><?= h($av['nome']) ?></b>
            <small>
              <?= h(tr_vozes()[$av['voz_nome']] ?? $av['voz_nome']) ?>
            </small>
            <span class="tri-item-tags">
              <?php if (!$temModelo): ?>
                <em class="is-alerta"><i class="bi bi-exclamation-triangle"></i> sem modelo</em>
              <?php endif; ?>
              <?php if ((int)$av['ativo'] !== 1): ?><em>inativo</em><?php endif; ?>
              <?php if (!empty($usos[(int)$av['id']])): ?>
                <em><?= (int)$usos[(int)$av['id']] ?> treinamento(s)</em>
              <?php endif; ?>
            </span>
          </span>
        </button>
      <?php endforeach; ?>
    </aside>

    <section class="tri-editor" id="triEditor" hidden>

      <div class="tri-previa">
        <div class="tri-palco" id="triPalco"></div>
        <div class="tri-palco-status" id="triStatus">Selecione um instrutor.</div>

        <div class="tri-palco-pe">
          <div class="tri-camera">
            <label class="tr-label">Enquadramento</label>
            <select class="form-select form-select-sm" id="avCamera">
              <?php foreach (tr_cameras() as $k => $v): ?>
                <option value="<?= h($k) ?>"><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="tri-camera">
            <label class="tr-label">Expressão</label>
            <select class="form-select form-select-sm" id="avHumor">
              <?php foreach (tr_humores() as $k => $v): ?>
                <option value="<?= h($k) ?>"><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="tri-palco-acoes">
          <button type="button" class="btn btn-sm tr-btn-ghost" id="btnRecarregar">
            <i class="bi bi-arrow-clockwise"></i> Recarregar
          </button>
          <?php if ($gerencia): ?>
            <button type="button" class="btn btn-sm tr-btn-ghost" id="btnMiniatura">
              <i class="bi bi-camera"></i> Gerar miniatura
            </button>
          <?php endif; ?>
        </div>
      </div>

      <div class="tri-controles">
        <div class="tri-abas" role="tablist">
          <button type="button" class="tri-aba is-ativa" data-aba="modelo">Modelo</button>
          <button type="button" class="tri-aba" data-aba="voz">Voz</button>
          <button type="button" class="tri-aba" data-aba="gestos">Gestos</button>
          <button type="button" class="tri-aba" data-aba="orientacao">Orientação</button>
          <button type="button" class="tri-aba" data-aba="ajuste">Ajuste fino</button>
        </div>

        <div class="tri-painel is-ativo" data-painel="modelo">
          <div class="tri-campos">
            <div class="tr-campo tr-campo-cheio">
              <label class="tr-label">Nome do instrutor</label>
              <input type="text" class="form-control form-control-sm" id="avNome" maxlength="80">
            </div>
            <div class="tr-campo tr-campo-cheio">
              <label class="tr-label">Descrição</label>
              <input type="text" class="form-control form-control-sm" id="avDescricao" maxlength="240"
                     placeholder="Como este instrutor deve ser usado.">
            </div>
            <div class="tr-campo tr-campo-cheio">
              <label class="tr-label">Modelo 3D (.vrm, até 60 MB)</label>
              <input type="file" class="form-control form-control-sm" id="avArquivo" accept=".vrm,.glb"
                     <?= $gerencia ? '' : 'disabled' ?>>
              <p class="tr-dica mb-0" id="avArquivoNome"></p>
            </div>
            <div class="tr-campo tr-campo-cheio">
              <label class="tr-check">
                <input type="checkbox" id="avAtivo"><span>Disponível para novos treinamentos</span>
              </label>
            </div>
          </div>

          <div class="tri-valida" id="avValidacao">
            <p class="tr-dica mb-0">
              A validação confere ossos humanoides, expressões de boca, emoções e Look At.
              Ela roda sozinha quando o modelo carrega na prévia.
            </p>
          </div>

          <p class="tr-dica">
            Personagens podem ser criados no
            <a href="https://vroid.com/en/studio" target="_blank" rel="noopener">VRoid Studio</a>
            e exportados em <code>.vrm</code>. No Blender, exporte com o
            <b>VRM Add-on for Blender</b> — um <code>.glb</code> comum não carrega.
          </p>
        </div>

        <div class="tri-painel" data-painel="voz">
          <div class="tri-campos">
            <div class="tr-campo tr-campo-cheio">
              <label class="tr-label">Voz</label>
              <select class="form-select form-select-sm" id="avVoz">
                <?php foreach (tr_vozes() as $k => $v): ?>
                  <option value="<?= h($k) ?>"><?= h($v) ?></option>
                <?php endforeach; ?>
              </select>
              <p class="tr-dica mb-0" id="avVozAviso" hidden></p>
            </div>

            <div class="tr-campo">
              <label class="tr-label">Velocidade <b id="avVelVal">1,00</b></label>
              <input type="range" class="form-range" id="avVelocidade" min="0.7" max="1.4" step="0.05" value="1">
            </div>
            <div class="tr-campo">
              <label class="tr-label">Tom <b id="avTomVal">1,00</b></label>
              <input type="range" class="form-range" id="avTom" min="0.85" max="1.2" step="0.01" value="1">
            </div>
            <div class="tr-campo">
              <label class="tr-label">Pausa entre frases <b id="avPausaVal">260 ms</b></label>
              <input type="range" class="form-range" id="avPausa" min="0" max="900" step="20" value="260">
            </div>
            <div class="tr-campo">
              <label class="tr-label">Volume <b id="avGanhoVal">1,00</b></label>
              <input type="range" class="form-range" id="avGanho" min="0.6" max="1.6" step="0.05" value="1">
            </div>
            <div class="tr-campo">
              <label class="tr-label">Qualidade da síntese</label>
              <select class="form-select form-select-sm" id="avQualidade">
                <?php foreach (tr_voz_qualidades() as $k => $v): ?>
                  <option value="<?= h($k) ?>"><?= h($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="tr-campo">
              <label class="tr-label">Qualidade do MP3</label>
              <select class="form-select form-select-sm" id="avKbps">
                <?php foreach ([64, 96, 128, 160] as $kb): ?>
                  <option value="<?= $kb ?>"><?= $kb ?> kbps</option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="tri-voz-modelo">
            <div class="tri-voz-linha">
              <span id="avVozStatus">O modelo de voz é baixado uma vez neste navegador (~270 MB).</span>
              <button type="button" class="btn btn-sm tr-btn-ghost" id="btnPrepararVoz">
                <i class="bi bi-cloud-arrow-down"></i> Preparar
              </button>
            </div>
            <div class="tri-voz-barra"><i id="avVozProgresso"></i></div>
          </div>

          <div class="tr-campo tr-campo-cheio">
            <label class="tr-label">Texto de teste</label>
            <input type="text" class="form-control form-control-sm" id="avVozTeste"
                   value="Bem-vindo ao RH Digital. Vamos começar o treinamento de hoje.">
          </div>
          <div class="d-flex gap-2 flex-wrap mt-2">
            <button type="button" class="btn btn-sm tr-btn-primaria" id="btnTestarVoz">
              <i class="bi bi-play-circle"></i> Ouvir teste
            </button>
          </div>
          <audio id="avVozAudio" controls class="tri-voz-audio" hidden></audio>
        </div>

        <div class="tri-painel" data-painel="gestos">
          <p class="tr-dica">
            Clique para ver o gesto ou a expressão no modelo ao lado. Os gestos dirigidos ao
            conteúdo trocam de braço sozinhos conforme o lado escolhido na aba Orientação —
            use o seletor abaixo para forçar um braço e conferir os dois.
          </p>
          <div class="tri-campos">
            <div class="tr-campo">
              <label class="tr-label">Braço na prévia</label>
              <select class="form-select form-select-sm" id="avGestoBraco">
                <?php foreach (tr_bracos() as $k => $v): ?>
                  <option value="<?= h($k) ?>"><?= h($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="tr-campo">
              <label class="tr-label">Direção do olhar na prévia</label>
              <select class="form-select form-select-sm" id="avOlharTeste">
                <?php foreach (tr_olhares() as $k => $v): ?>
                  <option value="<?= h($k) ?>"><?= h($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="tri-gestos" id="avGestos"></div>
        </div>

        <div class="tri-painel" data-painel="orientacao">
          <p class="tr-dica">
            Estes valores dizem ao instrutor como se colocar diante do conteúdo. Ele está de
            frente para quem assiste, então o <b>braço direito dele aparece à esquerda da tela</b>:
            com “Automático” ligado, o motor escolhe sozinho o braço, o giro do corpo e a direção
            do olhar de acordo com o lado em que o slide colocar o conteúdo. O que estiver aqui
            vale para todos os treinamentos deste instrutor.
          </p>

          <span class="tri-grupo-titulo">Simulação do palco (só na prévia)</span>
          <div class="tri-orient-mapa" id="avMapa">
            <span class="is-conteudo">Conteúdo</span>
            <span class="is-instrutor">Instrutor</span>
          </div>
          <div class="tri-campos">
            <div class="tr-campo">
              <label class="tr-label">Posição na tela</label>
              <select class="form-select form-select-sm" id="avPosicao">
                <?php foreach (tr_posicoes() as $k => $v): ?>
                  <option value="<?= h($k) ?>"<?= $k === 'direita' ? ' selected' : '' ?>><?= h($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="tr-campo">
              <label class="tr-label">Conteúdo está</label>
              <select class="form-select form-select-sm" id="avLadoConteudo">
                <?php foreach (tr_lados_conteudo() as $k => $v): ?>
                  <option value="<?= h($k) ?>"><?= h($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <p class="tri-orient-eco" id="avOrientEco"></p>

          <div class="tri-grupo">
            <span class="tri-grupo-titulo">Lado e giro do corpo</span>
            <div class="tri-campos">
              <div class="tr-campo tr-campo-cheio">
                <label class="tr-label">Mão dominante</label>
                <select class="form-select form-select-sm" id="avMaoDominante">
                  <?php foreach (tr_maos_dominantes() as $k => $v): ?>
                    <option value="<?= h($k) ?>"><?= h($v) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="tr-campo tr-campo-cheio">
                <label class="tr-check">
                  <input type="checkbox" id="avOrientAuto">
                  <span>Orientar automaticamente pelo lado do conteúdo</span>
                </label>
              </div>
              <div class="tr-campo tr-campo-cheio">
                <label class="tr-label">Giro do corpo para o conteúdo</label>
                <select class="form-select form-select-sm" id="avGiroCorpo">
                  <?php foreach (tr_giros() as $k => $v): ?>
                    <option value="<?= h($k) ?>"><?= h($v) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="tr-campo tr-campo-cheio">
                <label class="tr-check">
                  <input type="checkbox" id="avGiroQuadril">
                  <span>O quadril acompanha o giro (corpo inteiro, não só o tronco)</span>
                </label>
              </div>
            </div>
          </div>

          <div class="tri-grupo">
            <span class="tri-grupo-titulo">Intensidade dos movimentos</span>
            <label class="tri-linha">Tronco <b id="avForcaTroncoV">1,00</b>
              <input type="range" class="form-range" id="avForcaTronco" min="0" max="2" step="0.05" value="1"></label>
            <label class="tri-linha">Cabeça <b id="avForcaCabecaV">1,00</b>
              <input type="range" class="form-range" id="avForcaCabeca" min="0" max="2" step="0.05" value="1"></label>
            <label class="tri-linha">Braços <b id="avForcaGestoV">1,00</b>
              <input type="range" class="form-range" id="avForcaGesto" min="0" max="1.5" step="0.05" value="1"></label>
            <label class="tri-linha">Suavidade <b id="avSuavidadeV">1,00</b>
              <input type="range" class="form-range" id="avSuavidade" min="0.3" max="2.5" step="0.05" value="1"></label>
          </div>

          <div class="tri-grupo">
            <span class="tri-grupo-titulo">Cabeça e olhar</span>
            <label class="tri-linha">Cabeça acompanha o olhar <b id="avOlharCabecaV">0,35</b>
              <input type="range" class="form-range" id="avOlharCabeca" min="0" max="1" step="0.05" value="0.35"></label>
            <label class="tri-linha">Rosto volta ao público <b id="avRostoPublicoV">0,50</b>
              <input type="range" class="form-range" id="avRostoPublico" min="0" max="1" step="0.05" value="0.5"></label>
            <label class="tri-linha">Altura do conteúdo <b id="avAlturaConteudoV">0,00</b>
              <input type="range" class="form-range" id="avAlturaConteudo" min="-1" max="1" step="0.05" value="0"></label>
            <div class="tr-campo tr-campo-cheio">
              <label class="tr-label">Olhar padrão</label>
              <select class="form-select form-select-sm" id="avOlharPadrao">
                <?php foreach (tr_olhares() as $k => $v): ?>
                  <option value="<?= h($k) ?>"><?= h($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <p class="tr-dica mb-0">
              “Cabeça acompanha o olhar” em 0 move só os olhos; em 1 o instrutor vira a cabeça inteira.
              “Rosto volta ao público” é o quanto ele desvira a cabeça quando o corpo está girado
              para o slide — é isso que evita o instrutor falar de costas para a turma.
              “Altura do conteúdo” sobe ou desce o ponto para onde ele olha quando o slide está
              acima ou abaixo da linha dos olhos.
            </p>
          </div>

          <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-sm tr-btn-ghost" data-teste-gesto="apontar">
              <i class="bi bi-hand-index"></i> Testar apontar
            </button>
            <button type="button" class="btn btn-sm tr-btn-ghost" data-teste-gesto="apresentar">
              <i class="bi bi-easel"></i> Testar apresentar
            </button>
            <button type="button" class="btn btn-sm tr-btn-ghost" data-teste-gesto="explicar">
              <i class="bi bi-chat-square-text"></i> Testar explicar
            </button>
            <button type="button" class="btn btn-sm tr-btn-ghost" data-teste-gesto="">
              <i class="bi bi-arrow-counterclockwise"></i> Repouso
            </button>
          </div>
        </div>

        <div class="tri-painel" data-painel="ajuste">
          <p class="tr-dica">
            Mexa nos controles e acompanhe o resultado na prévia, que fica fixa ao lado.
            Ao acertar a pose, use “Copiar valores”.
          </p>

          <div class="tri-campos">
            <div class="tr-campo tr-campo-cheio">
              <label class="tr-label">Gesto</label>
              <select class="form-select form-select-sm" id="ajGesto"></select>
            </div>
            <div class="tr-campo">
              <label class="tr-label">Braço</label>
              <select class="form-select form-select-sm" id="ajLado">
                <option value="esquerdo">Esquerdo</option>
                <option value="direito">Direito</option>
              </select>
            </div>
            <div class="tr-campo">
              <label class="tr-label">Formato da mão</label>
              <select class="form-select form-select-sm" id="ajMao"></select>
            </div>
          </div>

          <div class="tri-grupo">
            <span class="tri-grupo-titulo">Braço — para onde aponta</span>
            <label class="tri-linha">Fora <b id="ajForaV">0,00</b>
              <input type="range" class="form-range" id="ajFora" min="-1" max="1" step="0.02"></label>
            <label class="tri-linha">Cima <b id="ajCimaV">0,00</b>
              <input type="range" class="form-range" id="ajCima" min="-1" max="1" step="0.02"></label>
            <label class="tri-linha">Frente <b id="ajFrenteV">0,00</b>
              <input type="range" class="form-range" id="ajFrente" min="-1" max="1" step="0.02"></label>
          </div>

          <div class="tri-grupo">
            <span class="tri-grupo-titulo">Cotovelo e torção</span>
            <label class="tri-linha">Dobra <b id="ajCotoveloV">0,00</b>
              <input type="range" class="form-range" id="ajCotovelo" min="0" max="2.6" step="0.02"></label>
            <label class="tri-linha">Torção <b id="ajGiroBracoV">0,00</b>
              <input type="range" class="form-range" id="ajGiroBraco" min="-1.6" max="1.6" step="0.02"></label>
          </div>

          <div class="tri-grupo">
            <span class="tri-grupo-titulo">Pulso</span>
            <label class="tri-linha">Flexão <b id="ajFlexaoV">0,00</b>
              <input type="range" class="form-range" id="ajFlexao" min="-1.2" max="1.2" step="0.02"></label>
            <label class="tri-linha">Desvio <b id="ajDesvioV">0,00</b>
              <input type="range" class="form-range" id="ajDesvio" min="-0.9" max="0.9" step="0.02"></label>
            <label class="tri-linha">Palma <b id="ajGiroV">0,00</b>
              <input type="range" class="form-range" id="ajGiro" min="-2" max="2" step="0.02"></label>
          </div>

          <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-sm tr-btn-ghost" id="ajReset">
              <i class="bi bi-arrow-counterclockwise"></i> Voltar ao original
            </button>
            <button type="button" class="btn btn-sm tr-btn-primaria" id="ajCopiar">
              <i class="bi bi-clipboard"></i> Copiar valores
            </button>
          </div>
          <pre class="tri-saida" id="ajSaida" hidden></pre>
        </div>

        <?php if ($gerencia): ?>
          <div class="tri-pe">
            <button type="button" class="btn btn-sm tr-btn-ghost tri-pe-perigo" id="btnExcluir">
              <i class="bi bi-trash"></i> Excluir
            </button>
            <button type="button" class="btn tr-btn-primaria btn-sm" id="btnSalvar">Salvar instrutor</button>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>

<script>
  window.TR_CSRF = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window.TR_API  = <?= json_encode(tr_url('api.php'), JSON_HEX_TAG) ?>;
  window.TR_BASE = <?= json_encode(tr_url(), JSON_HEX_TAG) ?>;
  window.TRI_URL = <?= json_encode(tr_url('instrutores.php'), JSON_HEX_TAG) ?>;
  window.TRI_GERENCIA = <?= $gerencia ? 'true' : 'false' ?>;
  window.TRI_CAT = {
    gestos:  <?= json_encode(tr_gestos(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
    humores: <?= json_encode(tr_humores(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
    posicoes: <?= json_encode(tr_posicoes(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
    lados:    <?= json_encode(tr_lados_conteudo(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>
  };
</script>
<script src="<?= h(tr_url('assets/js/ui.js')) ?>"></script>
<script src="<?= h(tr_url_v('assets/js/instrutor-vrm.js')) ?>"></script>
<script src="<?= h(tr_url('assets/js/tts-supertonic.js')) ?>"></script>
<script src="<?= h(tr_url('assets/js/instrutores.js')) ?>"></script>

<?php require_once __DIR__ . '/../../../app/layout/footer.php'; ?>
