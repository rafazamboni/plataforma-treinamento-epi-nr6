<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_lib.php';

require_login();
tr_exigir('treinamentos.apresentacoes.editar');

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);
$ap  = $id > 0 ? tr_apresentacao($pdo, $id) : null;

if (!$ap) {
    flash_set('danger', 'Apresentação não encontrada.');
    nv_redirect(tr_url('index.php'));
}

if (isset($_GET['avatar'])) {
    $caminho = tr_avatar_caminho($ap);
    if ($caminho === null) { http_response_code(404); exit; }
    tr_enviar_arquivo($caminho);
}

$podePublicar = tr_pode('treinamentos.apresentacoes.publicar');
$podeNarrar   = tr_pode('treinamentos.apresentacoes.narrar');
$podeExportar = tr_pode('treinamentos.apresentacoes.exportar');
$slides       = tr_estrutura($pdo, $id);
$linkPublico  = tr_url_absoluta('ver.php') . '?slug=';

$instrutores  = tr_avatares($pdo, false);
$instrutorAt  = tr_avatar_da_apresentacao($pdo, $ap);
$vozCfg       = tr_voz_config($pdo, $ap);
$vozRotulos   = tr_vozes();

require_once __DIR__ . '/../../../app/layout/header.php';
nv_render_header('Editor — ' . $ap['titulo']);
?>
<link rel="stylesheet" href="<?= h(tr_url('assets/css/admin.css')) ?>">
<link rel="stylesheet" href="<?= h(tr_url('assets/css/blocos.css')) ?>">
<link rel="stylesheet" href="<?= h(tr_url('assets/css/editor.css')) ?>">

<?php  ?>
<script type="importmap">
{
  "imports": {
    "three": "/assets/vendor/three@0.180.0/build/three.module.js",
    "three/addons/": "/assets/vendor/three@0.180.0/examples/jsm/",
    "@pixiv/three-vrm": "/assets/vendor/three-vrm@3.5.5/lib/three-vrm.module.min.js"
  }
}
</script>

<div class="tr-wrap tr-wrap-editor">

  <div class="tr-editor" id="trEditor">

    <aside class="tr-slides" id="drawerSlides">
      <div class="tr-slides-head">
        <span>Slides</span>
        <div class="d-flex gap-1">
          <?php if ($podeNarrar): ?>
            <button type="button" class="tr-mini-btn" id="btnNarrarTudo" title="Gerar a narração de todas as falas, de todos os slides">
              <i class="bi bi-soundwave"></i>
            </button>
          <?php endif; ?>
          <button type="button" class="tr-mini-btn" id="btnAddSlide" title="Adicionar slide">
            <i class="bi bi-plus-lg"></i>
          </button>
          <button type="button" class="tr-mini-btn" id="btnRecolherSlides" title="Recolher a coluna">
            <i class="bi bi-chevron-double-left"></i>
          </button>
        </div>
      </div>
      <div class="tr-narr-progresso" id="narracaoProgressoWrap" hidden>
        <div class="tr-narr-progresso-topo">
          <span id="narracaoProgressoTxt">Gerando narrações… 0/0</span>
          <button type="button" class="tr-mini-btn tr-mini-btn-perigo" id="btnCancelarNarracaoTudo" title="Cancelar geração">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <div class="tr-narr-progresso-barra"><div class="tr-narr-progresso-fill" id="narracaoProgressoFill"></div></div>
      </div>
      <div class="tr-slides-body" id="listaSlides"></div>
      <div class="tr-slides-pe"><b id="contSlides"><?= count($slides) ?></b> slide(s)</div>
    </aside>

    <button type="button" class="tr-slides-abrir" id="btnAbrirSlides" title="Mostrar os slides">
      <i class="bi bi-chevron-double-right"></i>
    </button>

    <section class="tr-palco-col">

      <div class="tr-palco-topo">
        <div class="tr-palco-titulo">
          <a class="tr-mini-btn" href="<?= h(tr_url('index.php')) ?>" title="Voltar para treinamentos">
            <i class="bi bi-arrow-left"></i>
          </a>
          <span class="tr-status tr-status-<?= h($ap['status']) ?>" id="statusAtual"><?= h(ucfirst($ap['status'])) ?></span>
          <span class="tr-salvo" id="trSalvo" hidden><i class="bi bi-check-lg"></i> salvo</span>

          <span class="tr-palco-divisor"></span>

          <span class="tr-slide-num" id="slideCabecalho">Slide</span>
          <input type="text" class="tr-slide-titulo" id="slideTitulo" maxlength="160"
                 placeholder="Título do slide">

          <div class="tr-palco-topo-acoes">
            <button type="button" class="tr-mini-btn" id="trcVerHtml" title="Editar o HTML gerado">
              <i class="bi bi-code-slash"></i>
            </button>
            <button type="button" class="tr-mini-btn tr-mini-btn-perigo" id="btnExcluirSlide" title="Excluir este slide">
              <i class="bi bi-trash"></i>
            </button>

            <span class="tr-palco-divisor"></span>

            <button type="button" class="btn btn-sm tr-btn-ghost" id="btnVerificar"
                    title="Conferir o treinamento antes de publicar">
              <i class="bi bi-clipboard2-check"></i> Verificar
            </button>
            <button type="button" class="btn btn-sm tr-btn-ghost" id="btnDados">
              <i class="bi bi-sliders2"></i> Apresentação
            </button>
            <?php if ($podeExportar): ?>
              <div class="tr-exportar" id="menuExportar">
                <button type="button" class="btn btn-sm tr-btn-ghost" id="btnExportar"
                        aria-haspopup="true" aria-expanded="false"
                        title="Baixar o treinamento em Word, para revisão do conteúdo">
                  <i class="bi bi-file-earmark-word"></i> Exportar
                  <i class="bi bi-chevron-down tr-exportar-seta"></i>
                </button>
                <div class="tr-exportar-lista">
                  <a href="<?= h(tr_url('exportar.php')) ?>?id=<?= $id ?>&amp;modo=simples"
                     data-exportar>
                    <i class="bi bi-file-text"></i>
                    <span><b>Texto e narração</b><em>Só o que aparece na tela e o que é falado</em></span>
                  </a>
                  <a href="<?= h(tr_url('exportar.php')) ?>?id=<?= $id ?>&amp;modo=completo"
                     data-exportar>
                    <i class="bi bi-file-earmark-ruled"></i>
                    <span><b>Roteiro completo</b><em>Com ficha técnica, instrutor e ações</em></span>
                  </a>
                </div>
              </div>
            <?php endif; ?>
            <?php if ($podePublicar): ?>
              <button type="button" class="btn btn-sm tr-btn-ghost" id="btnPublicar" data-publico="<?= (int)$ap['publico'] ?>">
                <i class="bi bi-<?= (int)$ap['publico'] === 1 ? 'globe2' : 'lock' ?>"></i>
                <span><?= (int)$ap['publico'] === 1 ? 'Link público' : 'Publicar' ?></span>
              </button>
            <?php endif; ?>
            <a class="btn btn-sm tr-btn-primaria" href="<?= h(tr_url('player.php')) ?>?id=<?= $id ?>" target="_blank">
              <i class="bi bi-play-fill"></i> Apresentar
            </a>
          </div>
        </div>

        <div class="tr-inserir">
          <span class="tr-inserir-rot">Inserir</span>
        <div class="trc-barra">
          <button type="button" class="trc-inserir" data-inserir="titulo"><i class="bi bi-type-h1"></i> Título</button>
          <button type="button" class="trc-inserir" data-inserir="texto"><i class="bi bi-fonts"></i> Texto</button>
          <button type="button" class="trc-inserir" data-inserir="lista"><i class="bi bi-list-ul"></i> Tópicos</button>
          <button type="button" class="trc-inserir" data-inserir="cartao"><i class="bi bi-square"></i> Cartão</button>
          <button type="button" class="trc-inserir" data-inserir="indicador"><i class="bi bi-graph-up"></i> Indicador</button>
          <button type="button" class="trc-inserir" data-inserir="imagem"><i class="bi bi-image"></i> Imagem</button>
          <button type="button" class="trc-inserir" data-inserir="video"><i class="bi bi-play-btn"></i> Vídeo</button>
          <button type="button" class="trc-inserir" data-inserir="forma"><i class="bi bi-square-fill"></i> Forma</button>
          <button type="button" class="trc-inserir" data-inserir="divisor"><i class="bi bi-dash-lg"></i> Divisor</button>
          <span class="trc-barra-sep"></span>
          <div class="trc-blocos-menu-wrap">
            <button type="button" class="trc-inserir trc-inserir-destaque" id="btnBlocos">
              <i class="bi bi-grid-1x2"></i> Blocos interativos <i class="bi bi-chevron-down"></i>
            </button>
            <div class="trc-blocos-menu" id="trcMenuBlocos" hidden></div>
          </div>
          <span class="trc-barra-sep"></span>

          </div>
        </div>
      </div>

        <div class="trc-fmt" id="trcFmt">
          <button type="button" class="trc-ferr" data-fmt="bold" title="Negrito"><i class="bi bi-type-bold"></i></button>
          <button type="button" class="trc-ferr" data-fmt="italic" title="Itálico"><i class="bi bi-type-italic"></i></button>
          <button type="button" class="trc-ferr" data-fmt="underline" title="Sublinhado"><i class="bi bi-type-underline"></i></button>
          <button type="button" class="trc-ferr" data-fmt="strikeThrough" title="Tachado"><i class="bi bi-type-strikethrough"></i></button>
          <span class="trc-sep"></span>
          <button type="button" class="trc-ferr" data-fmt="tamanho" data-valor="-" title="Diminuir o texto selecionado"><i class="bi bi-dash-lg"></i>A</button>
          <select class="trc-fmt-sel" data-fmt="tamanho-abs" title="Tamanho do texto selecionado">
            <option value="">Tamanho</option>
            <option value="0.7">70%</option>
            <option value="0.85">85%</option>
            <option value="1">100%</option>
            <option value="1.25">125%</option>
            <option value="1.5">150%</option>
            <option value="2">200%</option>
            <option value="2.6">260%</option>
          </select>
          <button type="button" class="trc-ferr" data-fmt="tamanho" data-valor="+" title="Aumentar o texto selecionado"><i class="bi bi-plus-lg"></i>A</button>
          <span class="trc-sep"></span>
          <select class="trc-fmt-sel" data-fmt="bloco" title="Estilo do parágrafo">
            <option value="">Parágrafo</option>
            <option value="h2">Título grande</option>
            <option value="h3">Subtítulo</option>
            <option value="p">Texto normal</option>
          </select>
          <button type="button" class="trc-ferr" data-fmt="insertUnorderedList" title="Lista com marcadores"><i class="bi bi-list-ul"></i></button>
          <button type="button" class="trc-ferr" data-fmt="insertOrderedList" title="Lista numerada"><i class="bi bi-list-ol"></i></button>
          <span class="trc-sep"></span>
          <label class="trc-fmt-cor" title="Cor do texto selecionado">
            <i class="bi bi-palette"></i>
            <input type="color" data-fmt="foreColor" value="#16222f">
          </label>
          <button type="button" class="trc-ferr" data-fmt="removeFormat" title="Limpar formatação"><i class="bi bi-eraser"></i></button>
          <span class="trc-fmt-dica" id="trcFmtDica">Dê um duplo clique num texto do palco para escrever e formatar.</span>
        </div>

      <div class="tr-palco-area" id="painelSlide">
        <div class="trc-area" id="trcArea">
          <div class="trc-holder" id="trcHolder">
            <div class="trc-stage" id="trcStage">
              <div class="trc-instr-guia" id="trcInstrGuia" hidden><span>área do instrutor</span></div>
              <div class="trc-palco" id="trcPalco"></div>
              <div class="trc-legenda-guia" id="trcLegendaGuia"><span>área da legenda</span></div>
              <div class="trc-guias" id="trcGuias"></div>
              <div class="trc-enviando" id="trcEnviando" hidden><span class="trc-anel"></span> enviando imagem…</div>
            </div>
          </div>
        </div>

        <input type="file" id="trcArquivoImagem" accept="image/*" hidden>

        <div id="trcHtmlBox" hidden>
          <label class="tr-label">HTML gerado (edição avançada)</label>
          <textarea id="slideConteudo" class="tr-codigo" spellcheck="false"></textarea>
        </div>
      </div>
    </section>

    <aside class="tr-props">
      <div class="tr-props-abas" role="tablist">
        <button type="button" class="tr-prop-aba is-ativa" data-prop="elemento">
          <i class="bi bi-bounding-box"></i> Elemento
        </button>
        <button type="button" class="tr-prop-aba" data-prop="slide" id="btnCfgSlide">
          <i class="bi bi-easel"></i> Slide
        </button>
        <button type="button" class="tr-prop-aba" data-prop="falas" id="btnFalas">
          <i class="bi bi-mic"></i> Narração
        </button>
      </div>

      <div class="tr-prop-painel is-ativo" data-painel="elemento">
        <div class="trc-inspetor" id="trcInspetor"></div>
      </div>

      <div class="tr-prop-painel" data-painel="slide">
        <div class="tr-cfgbar" id="cfgBar">

        <div class="tr-cfg-bloco">
          <span class="tr-cfg-titulo">Aparência</span>
          <div class="tr-cfg-campos">
            <div class="tr-cfg-campo">
              <label class="tr-label">Transição</label>
              <select class="form-select form-select-sm" id="slideTransicao"></select>
            </div>
            <div class="tr-cfg-campo">
              <label class="tr-label">Fundo</label>
              <select class="form-select form-select-sm" id="slideFundoTipo">
                <option value="tema">Herdar da apresentação</option>
                <option value="solido">Cor sólida</option>
                <option value="gradiente">Gradiente</option>
                <option value="imagem">Imagem</option>
              </select>
            </div>
            <div class="tr-cfg-campo tr-cfg-curto" data-fundo="solido gradiente">
              <label class="tr-label">Cor</label>
              <input type="color" class="form-control form-control-color form-control-sm" id="slideFundoCor" value="#ffffff">
            </div>
            <div class="tr-cfg-campo tr-cfg-curto" data-fundo="gradiente">
              <label class="tr-label">Cor final</label>
              <input type="color" class="form-control form-control-color form-control-sm" id="slideFundoCor2" value="#eef2f7">
            </div>
            <div class="tr-cfg-campo" data-fundo="gradiente">
              <label class="tr-label">Ângulo <b id="slideAnguloVal">160°</b></label>
              <input type="range" class="form-range" id="slideFundoAngulo" min="0" max="360" step="5" value="160">
            </div>
            <div class="tr-cfg-campo tr-cfg-largo" data-fundo="imagem">
              <label class="tr-label">Imagem de fundo</label>
              <input type="file" class="form-control form-control-sm" id="slideFundoImagem" accept="image/*">
              <p class="tr-dica mb-0" id="slideFundoNome"></p>
            </div>
            <div class="tr-cfg-campo" data-fundo="imagem">
              <label class="tr-label">Escurecer <b id="slideEscurecerVal">0%</b></label>
              <input type="range" class="form-range" id="slideFundoEscurecer" min="0" max="85" step="5" value="0">
            </div>
          </div>
        </div>

        <div class="tr-cfg-bloco">
          <span class="tr-cfg-titulo">Cena</span>
          <div class="tr-cfg-campos">
            <div class="tr-cfg-campo tr-cfg-check">
              <label class="tr-check">
                <input type="checkbox" id="slideInstrutorVisivel"><span>Instrutor visível</span>
              </label>
            </div>
            <div class="tr-cfg-campo">
              <label class="tr-label">Posição</label>
              <select class="form-select form-select-sm" id="slidePosicao"></select>
            </div>
            <div class="tr-cfg-campo">
              <label class="tr-label">Conteúdo do slide está</label>
              <select class="form-select form-select-sm" id="slideLadoConteudo"></select>
            </div>
            <div class="tr-cfg-campo">
              <label class="tr-label">Giro do corpo</label>
              <select class="form-select form-select-sm" id="slideGiro"></select>
            </div>
            <div class="tr-cfg-campo">
              <label class="tr-label">Enquadramento</label>
              <select class="form-select form-select-sm" id="slideCamera">
                <option value="herdar">Herdar da apresentação</option>
                <?php foreach (tr_cameras() as $k => $v): ?>
                  <option value="<?= h($k) ?>"><?= h($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="tr-cfg-campo">
              <label class="tr-label">Tamanho <b id="slideEscalaVal">1.00</b></label>
              <input type="range" class="form-range" id="slideEscala" min="0.6" max="1.6" step="0.05" value="1">
            </div>
          </div>
        </div>

        </div>

        <div hidden>
          <select id="slideLayout"></select>
          <input type="color" id="slideCorTexto" value="#16222f">
          <input type="checkbox" id="slideCorTextoAuto" checked>
        </div>
      </div>

      <div class="tr-prop-painel" data-painel="falas">
        <div class="tr-falas-topo">
          <span>Falas deste slide</span>
          <div class="d-flex gap-1">
            <?php if ($podeNarrar): ?>
              <button type="button" class="tr-mini-btn" id="btnNarrarTodas" title="Gerar a narração de todas as falas">
                <i class="bi bi-mic-fill"></i>
              </button>
            <?php endif; ?>
            <button type="button" class="tr-mini-btn" id="btnAddDialogo" title="Adicionar fala">
              <i class="bi bi-plus-lg"></i>
            </button>
          </div>
        </div>
        <p class="tr-falas-dica">
          Clique numa fala para escrever o texto, escolher gesto, olhar e ações — tudo com a
          prévia do instrutor ao lado. Arraste os cartões para mudar a ordem.
        </p>
        <div class="tr-falas-body" id="listaDialogos"></div>
      </div>
    </aside>
  </div>

</div>
<div class="tr-modal tr-modal-fala" id="modalFala" inert>
  <div class="tr-modal-caixa">

    <div class="tr-modal-head mf-head">
      <div class="mf-head-id">
        <i class="bi bi-mic-fill"></i>
        <span>Fala <b id="mfNum">1</b></span>
        <small id="mfSlide"></small>
      </div>
      <div class="mf-head-nav">
        <button type="button" class="tr-mini-btn" id="mfAnterior" title="Fala anterior">
          <i class="bi bi-chevron-up"></i>
        </button>
        <button type="button" class="tr-mini-btn" id="mfProxima" title="Próxima fala">
          <i class="bi bi-chevron-down"></i>
        </button>
        <span class="tr-palco-divisor"></span>
        <button type="button" class="tr-modal-x" data-fechar="modalFala"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>

    <div class="mf-corpo">

      <div class="mf-cfg tr-fala" id="mfFala" data-dialogo-id="">
        <div class="mf-abas" role="tablist">
          <button type="button" class="mf-aba is-ativa" data-mfaba="texto">
            <i class="bi bi-chat-left-text"></i> Texto e voz
          </button>
          <button type="button" class="mf-aba" data-mfaba="instrutor">
            <i class="bi bi-person-arms-up"></i> Instrutor
          </button>
          <button type="button" class="mf-aba" data-mfaba="acoes">
            <i class="bi bi-lightning-charge"></i> Ações no slide
            <em class="mf-aba-cont" id="mfContAcoes"></em>
          </button>
        </div>

        <div class="mf-painel is-ativo" data-mfpainel="texto" id="mfPainelTexto"></div>
        <div class="mf-painel" data-mfpainel="instrutor" id="mfPainelInstrutor"></div>
        <div class="mf-painel" data-mfpainel="acoes" id="mfPainelAcoes"></div>
      </div>

      <aside class="mf-previa">
        <div class="mf-previa-topo">
          <span class="tr-label mb-0">Prévia do instrutor</span>
          <select class="form-select form-select-sm mf-previa-cam" id="mfCamera">
            <?php foreach (tr_cameras() as $k => $v): ?>
              <option value="<?= h($k) ?>"<?= $k === 'mid' ? ' selected' : '' ?>><?= h($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mf-palco" id="mfPalco"></div>
        <p class="mf-status" id="mfStatus"></p>

        <div class="tri-orient-mapa mf-mapa" id="mfMapa"></div>
        <p class="mf-eco" id="mfEco"></p>

        <button type="button" class="btn btn-sm tr-btn-ghost w-100" id="mfRepetir">
          <i class="bi bi-arrow-repeat"></i> Repetir o gesto
        </button>
        <p class="tr-dica mb-0">
          O lado da tela em que o instrutor aparece e onde o conteúdo fica vêm da aba
          <b>Slide</b>. A prévia já usa essa combinação, então o que você vê aqui é
          exatamente o que a turma vai ver.
        </p>
      </aside>
    </div>

    <div class="tr-modal-pe mf-pe">
      <button type="button" class="btn btn-sm tr-btn-ghost tr-btn-perigo" id="mfExcluir">
        <i class="bi bi-trash"></i> Excluir fala
      </button>
      <span class="mf-pe-espaco"></span>
      <span class="mf-pe-nota"><i class="bi bi-check-lg"></i> As alterações são salvas automaticamente</span>
      <button type="button" class="btn btn-sm tr-btn-primaria" data-fechar="modalFala">Concluir</button>
    </div>
  </div>
</div>

<div class="tr-modal" id="modalVerificar" inert>
  <div class="tr-modal-caixa">
    <div class="tr-modal-head">
      <span>Verificação do treinamento</span>
      <button type="button" class="tr-modal-x" data-fechar="modalVerificar"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="tr-modal-body" id="verificarCorpo"></div>
    <div class="tr-modal-pe">
      <button type="button" class="btn tr-btn-ghost" data-fechar="modalVerificar">Fechar</button>
    </div>
  </div>
</div>

<div class="tr-modal" id="modalDados" inert>
  <div class="tr-modal-caixa">
    <div class="tr-modal-head">
      <span>Apresentação</span>
      <button type="button" class="tr-modal-x" data-fechar="modalDados"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="tr-modal-body">
      <div class="tr-linha">
        <div class="tr-campo tr-campo-cheio">
          <label class="tr-label">Título</label>
          <input type="text" class="form-control form-control-sm" id="apTitulo" maxlength="160" value="<?= h($ap['titulo']) ?>">
        </div>
      </div>
      <div class="tr-linha">
        <div class="tr-campo tr-campo-cheio">
          <label class="tr-label">Subtítulo</label>
          <input type="text" class="form-control form-control-sm" id="apSubtitulo" maxlength="220" value="<?= h($ap['subtitulo'] ?? '') ?>">
        </div>
      </div>
      <div class="tr-linha">
        <div class="tr-campo">
          <label class="tr-label">Sigla no topo</label>
          <input type="text" class="form-control form-control-sm" id="apMarca" maxlength="8" value="<?= h($ap['marca']) ?>">
        </div>
        <div class="tr-campo">
          <label class="tr-label">Status</label>
          <select class="form-select form-select-sm" id="apStatus">
            <option value="rascunho"  <?= $ap['status'] === 'rascunho'  ? 'selected' : '' ?>>Rascunho</option>
            <option value="publicado" <?= $ap['status'] === 'publicado' ? 'selected' : '' ?>>Publicado</option>
            <option value="arquivado" <?= $ap['status'] === 'arquivado' ? 'selected' : '' ?>>Arquivado</option>
          </select>
        </div>
        <div class="tr-campo">
          <label class="tr-label">Tema</label>
          <select class="form-select form-select-sm" id="apTema">
            <option value="claro"  <?= $ap['tema'] === 'claro'  ? 'selected' : '' ?>>Claro</option>
            <option value="escuro" <?= $ap['tema'] === 'escuro' ? 'selected' : '' ?>>Escuro</option>
          </select>
        </div>
      </div>

      <div class="tr-linha">
        <div class="tr-campo">
          <label class="tr-label">Cor primária</label>
          <input type="color" class="form-control form-control-color form-control-sm" id="apCorPrimaria" value="<?= h($ap['cor_primaria']) ?>">
        </div>
        <div class="tr-campo">
          <label class="tr-label">Cor de acento</label>
          <input type="color" class="form-control form-control-color form-control-sm" id="apCorAcento" value="<?= h($ap['cor_acento']) ?>">
        </div>
        <div class="tr-campo">
          <label class="tr-label">Fundo padrão</label>
          <input type="color" class="form-control form-control-color form-control-sm" id="apCorFundo" value="<?= h($ap['cor_fundo']) ?>">
        </div>
      </div>

      <div class="tr-linha">
        <div class="tr-campo tr-campo-cheio">
          <label class="tr-label">Logo no topo (PNG ou SVG)</label>
          <input type="file" class="form-control form-control-sm" id="apLogo" accept="image/*">
          <?php if ($ap['logo_arquivo']): ?>
            <p class="tr-dica mb-0">Atual: <?= h($ap['logo_arquivo']) ?></p>
          <?php endif; ?>
        </div>
      </div>

      <div class="tr-linha">
        <div class="tr-campo tr-campo-cheio">
          <label class="tr-label">Endereço do link público</label>
          <input type="text" class="form-control form-control-sm" id="apSlug" maxlength="120" value="<?= h($ap['slug']) ?>">
          <small class="tr-dica d-block mt-1"><?= h($linkPublico) ?><span id="apSlugEcho"><?= h($ap['slug']) ?></span></small>
        </div>
      </div>

      <div class="tr-linha">
        <label class="tr-check">
          <input type="checkbox" id="apAvancoAuto" <?= (int)$ap['avanco_auto'] === 1 ? 'checked' : '' ?>>
          <span>Avançar automaticamente ao fim das falas de cada slide</span>
        </label>
      </div>

      <div class="tr-modal-secao">
        <span class="tr-modal-secao-tit">Quem narra este treinamento</span>
        <p class="tr-dica">
          Trocar o instrutor troca também a voz, a velocidade e o tom da narração.
          Para cadastrar modelos ou ajustar vozes, use
          <a href="<?= h(tr_url('instrutores.php')) ?>" target="_blank">Instrutores e vozes</a>.
        </p>

        <div class="tr-campo" style="max-width:260px">
          <label class="tr-label">Enquadramento padrão</label>
          <select class="form-select form-select-sm" id="apCamera">
            <?php foreach (tr_cameras() as $k => $v): ?>
              <option value="<?= h($k) ?>" <?= (string)$ap['avatar_camera'] === $k ? 'selected' : '' ?>><?= h($v) ?></option>
            <?php endforeach; ?>
          </select>
          <small class="tr-dica d-block mt-1">Usado pelos slides configurados com enquadramento "Herdar da apresentação".</small>
        </div>

        <?php if (!$instrutores): ?>
          <div class="tr-vazio">
            <i class="bi bi-person-badge"></i>
            <p>Nenhum instrutor cadastrado na biblioteca.</p>
            <a class="btn btn-sm tr-btn-primaria" href="<?= h(tr_url('instrutores.php')) ?>" target="_blank">Cadastrar instrutor</a>
          </div>
        <?php else: ?>
          <div class="tr-instr-grade">
            <?php foreach ($instrutores as $inst): ?>
              <?php
                $temModelo = tr_avatar_modelo_caminho($inst) !== null;
                $thumb     = tr_avatar_thumb_caminho($inst);
                $ativo     = $instrutorAt && (int)$instrutorAt['id'] === (int)$inst['id'];
                $vozRot    = $vozRotulos[$inst['voz_nome']] ?? $inst['voz_nome'];
              ?>
              <button type="button"
                      class="tr-instr-card<?= $ativo ? ' is-ativo' : '' ?><?= $temModelo ? '' : ' is-indisponivel' ?>"
                      data-instrutor="<?= (int)$inst['id'] ?>"
                      data-nome="<?= h($inst['nome']) ?>"
                      data-voz-rotulo="<?= h($vozRot) ?>"
                      data-voz="<?= h(json_encode([
                        'voz'        => (string)$inst['voz_nome'],
                        'velocidade' => (float)$inst['voz_velocidade'],
                        'tom'        => (float)$inst['voz_tom'],
                        'pausaMs'    => (int)$inst['voz_pausa_ms'],
                        'ganho'      => (float)$inst['voz_ganho'],
                        'qualidade'  => (string)$inst['voz_qualidade'],
                        'kbps'       => (int)$inst['voz_kbps'],
                      ], JSON_UNESCAPED_UNICODE)) ?>">
                <span class="tr-instr-foto">
                  <?php if ($thumb !== null): ?>
                    <img src="<?= h(tr_url('instrutores.php')) ?>?av=<?= (int)$inst['id'] ?>&amp;thumb=1&amp;v=<?= (int)filemtime($thumb) ?>" alt="">
                  <?php else: ?>
                    <i class="bi bi-person-badge"></i>
                  <?php endif; ?>
                  <?php if ($ativo): ?><em class="tr-instr-selo"><i class="bi bi-check-lg"></i> narrando</em><?php endif; ?>
                </span>
                <b><?= h($inst['nome']) ?></b>
                <small><i class="bi bi-mic"></i> <?= h($vozRot) ?></small>
                <?php if (!$temModelo): ?>
                  <em class="tr-instr-alerta"><i class="bi bi-exclamation-triangle"></i> sem modelo 3D</em>
                <?php endif; ?>
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="tr-modal-pe">
      <button type="button" class="btn tr-btn-ghost" data-fechar="modalDados">Fechar</button>
      <button type="button" class="btn tr-btn-primaria" id="btnSalvarDados">Salvar</button>
    </div>
  </div>
</div>

<script>
  window.TR_CSRF = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window.TR_API  = <?= json_encode(tr_url('api.php'), JSON_HEX_TAG) ?>;
  window.TR_BASE = <?= json_encode(tr_url(), JSON_HEX_TAG) ?>;
  window.TR_APRESENTACAO_ID = <?= $id ?>;
  window.TR_LINK_BASE = <?= json_encode($linkPublico, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window.TR_PODE_NARRAR = <?= $podeNarrar ? 'true' : 'false' ?>;
  window.TR_CAMERA_PADRAO = <?= json_encode((string)$ap['avatar_camera'], JSON_HEX_TAG) ?>;
  window.TR_VOZ = <?= json_encode($vozCfg, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window.TR_AVATAR = <?= json_encode([
      'url'        => tr_avatar_caminho($ap) !== null ? (tr_url('editor.php') . '?id=' . $id . '&avatar=1') : '',
      'nome'       => (string)($instrutorAt['nome']   ?? $ap['avatar_nome']),
      'camera'     => (string)($instrutorAt['camera'] ?? $ap['avatar_camera']),
      'humor'      => (string)($instrutorAt['humor']  ?? $ap['avatar_humor']),
      'orientacao' => tr_orientacao_avatar($instrutorAt),
  ], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window.TR_DADOS = {
    apresentacao: <?= json_encode($ap, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
    slides: <?= json_encode($slides, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>
  };
  window.TR_CAT = {
    transicoes: <?= json_encode(tr_transicoes(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
    layouts:    <?= json_encode(tr_layouts(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
    posicoes:   <?= json_encode(tr_posicoes(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
    bracos:        <?= json_encode(tr_bracos(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
    giros:         <?= json_encode(tr_giros(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
    girosSlide:    <?= json_encode(tr_giros_slide(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
    troncos:       <?= json_encode(tr_troncos(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
    cabecaMovs:    <?= json_encode(tr_cabeca_movs(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
    ladosConteudo: <?= json_encode(tr_lados_conteudo(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
    gestos:     <?= json_encode(tr_gestos(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
    humores:    <?= json_encode(tr_humores(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
    olhares:    <?= json_encode(tr_olhares(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
    cameras:    <?= json_encode(tr_cameras(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
    acoes:      <?= json_encode(tr_tipos_acao(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>
  };
</script>
<script src="<?= h(tr_url_v('assets/js/ui.js')) ?>"></script>
<script src="<?= h(tr_url_v('assets/js/tts-supertonic.js')) ?>"></script>
<script src="<?= h(tr_url_v('assets/js/blocos.js')) ?>"></script>
<script src="<?= h(tr_url_v('assets/js/canvas-visual.js')) ?>"></script>
<script src="<?= h(tr_url_v('assets/js/instrutor-vrm.js')) ?>"></script>
<script src="<?= h(tr_url_v('assets/js/editor.js')) ?>"></script>
<script src="<?= h(tr_url_v('assets/js/editor-canvas.js')) ?>"></script>

<?php require_once __DIR__ . '/../../../app/layout/footer.php'; ?>
