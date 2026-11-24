(function (global) {
  'use strict';

  function esc(t) {
    return String(t == null ? '' : t)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function clone(o) { return JSON.parse(JSON.stringify(o)); }

  var CAMPO_GUIA = { k: 'f', t: 'fala', r: 'Pedir depois da fala' };
  var CAMPO_GUIADO = { k: 'guiado', t: 'bool', r: 'Passo a passo guiado pela narração' };
  var CAMPO_GUIA_AUTO = { k: 'guiaAuto', t: 'bool', r: 'Abrir sozinho, sem esperar o clique' };

  function attrBloco(d, itens) {
    if (!d || !d.guiado) { return ''; }
    var fs = (itens || []).map(function (o) { return Number(o.f) || 0; })
                          .filter(function (n) { return n > 0; });
    var menor = fs.length ? Math.min.apply(null, fs) : 0;
    return ' data-guiado="1" data-apos="' + menor + '"' +
           (d.guiaAuto ? ' data-gauto="1"' : '');
  }
  function attrItem(d, o, i) {
    if (!d || !d.guiado) { return ''; }
    return ' data-gi="' + i + '" data-gf="' + (Number(o.f) || 0) + '"';
  }

  var ESQUEMA = {
    quiz: {
      rotulo: 'Quiz', icone: 'bi-patch-question',
      dica: 'Quem assiste escolhe uma alternativa e recebe o retorno na hora.',
      padrao: {
        pergunta: 'O que deve ser feito antes de iniciar a operação?',
        opcoes: [
          { t: 'Conferir o checklist de segurança', ok: true },
          { t: 'Iniciar imediatamente', ok: false },
          { t: 'Aguardar autorização verbal', ok: false }
        ],
        feedback: 'O checklist é obrigatório e registra a conferência dos itens críticos.',
        varias: false
      },
      campos: [
        { k: 'pergunta', t: 'area', r: 'Pergunta' },
        { k: 'feedback', t: 'area', r: 'Explicação (aparece após responder)' },
        { k: 'varias', t: 'bool', r: 'Permitir mais de uma correta' }
      ],
      listas: [{
        k: 'opcoes', r: 'Alternativas',
        campos: [{ k: 't', t: 'texto', r: 'Texto' }, { k: 'ok', t: 'bool', r: 'Correta' }],
        novo: { t: 'Nova alternativa', ok: false }
      }]
    },

    revelar: {
      rotulo: 'Clique e revele', icone: 'bi-layers',
      dica: 'Cartões fechados que abrem o detalhe ao serem clicados.',
      padrao: {
        titulo: '',
        itens: [
          { t: 'Equipamento de proteção', d: 'Capacete, óculos e luvas são obrigatórios na área de produção.' },
          { t: 'Sinalização', d: 'A área precisa estar isolada e sinalizada antes do início.' },
          { t: 'Registro', d: 'Toda ocorrência é registrada no sistema no mesmo turno.' }
        ]
      },
      campos: [{ k: 'titulo', t: 'texto', r: 'Título (opcional)' }, CAMPO_GUIADO, CAMPO_GUIA_AUTO],
      listas: [{
        k: 'itens', r: 'Cartões',
        campos: [{ k: 't', t: 'texto', r: 'Frente' }, { k: 'd', t: 'area', r: 'Detalhe revelado' }, CAMPO_GUIA],
        novo: { t: 'Novo cartão', d: 'Detalhe que aparece ao clicar.' }
      }]
    },

    abas: {
      rotulo: 'Abas', icone: 'bi-segmented-nav',
      dica: 'Vários conteúdos no mesmo espaço, alternados por aba.',
      padrao: {
        itens: [
          { t: 'Antes', d: 'O que precisa estar pronto antes de começar.' },
          { t: 'Durante', d: 'Os cuidados enquanto a atividade acontece.' },
          { t: 'Depois', d: 'O fechamento e os registros obrigatórios.' }
        ]
      },
      campos: [CAMPO_GUIADO, CAMPO_GUIA_AUTO],
      listas: [{
        k: 'itens', r: 'Abas',
        campos: [{ k: 't', t: 'texto', r: 'Título' }, { k: 'd', t: 'area', r: 'Conteúdo' }, CAMPO_GUIA],
        novo: { t: 'Nova aba', d: 'Conteúdo da aba.' }
      }]
    },

    passos: {
      rotulo: 'Etapas', icone: 'bi-list-ol',
      dica: 'Sequência numerada — bom para procedimentos.',
      padrao: {
        itens: [
          { t: 'Preparação', d: 'Separe as ferramentas e confira as condições do local.' },
          { t: 'Execução', d: 'Siga o procedimento na ordem descrita.' },
          { t: 'Conferência', d: 'Valide o resultado e registre no sistema.' }
        ]
      },
      campos: [CAMPO_GUIADO, CAMPO_GUIA_AUTO],
      listas: [{
        k: 'itens', r: 'Etapas',
        campos: [{ k: 't', t: 'texto', r: 'Título' }, { k: 'd', t: 'area', r: 'Descrição' }, CAMPO_GUIA],
        novo: { t: 'Nova etapa', d: 'Descrição da etapa.' }
      }]
    },

    checklist: {
      rotulo: 'Checklist', icone: 'bi-check2-square',
      dica: 'Itens que quem assiste marca conforme acompanha.',
      padrao: {
        titulo: 'Antes de começar',
        itens: [
          { t: 'Uniforme e EPI conferidos' },
          { t: 'Ferramentas em bom estado' },
          { t: 'Área sinalizada' }
        ]
      },
      campos: [{ k: 'titulo', t: 'texto', r: 'Título' }, CAMPO_GUIADO, CAMPO_GUIA_AUTO],
      listas: [{
        k: 'itens', r: 'Itens',
        campos: [{ k: 't', t: 'texto', r: 'Texto' }, CAMPO_GUIA],
        novo: { t: 'Novo item' }
      }]
    },

    comparativo: {
      rotulo: 'Certo × errado', icone: 'bi-columns-gap',
      dica: 'Duas colunas contrastando o correto e o que evitar.',
      padrao: {
        aTitulo: 'Faça assim',
        aItens: [{ t: 'Confira o checklist antes de ligar' }, { t: 'Use o EPI completo' }],
        bTitulo: 'Evite',
        bItens: [{ t: 'Improvisar ferramentas' }, { t: 'Pular o registro da ocorrência' }]
      },
      campos: [
        { k: 'aTitulo', t: 'texto', r: 'Título da coluna positiva' },
        { k: 'bTitulo', t: 'texto', r: 'Título da coluna negativa' }
      ],
      listas: [
        { k: 'aItens', r: 'Itens — coluna positiva', campos: [{ k: 't', t: 'texto', r: 'Texto' }], novo: { t: 'Novo item' } },
        { k: 'bItens', r: 'Itens — coluna negativa', campos: [{ k: 't', t: 'texto', r: 'Texto' }], novo: { t: 'Novo item' } }
      ]
    },

    estatisticas: {
      rotulo: 'Indicadores', icone: 'bi-bar-chart-line',
      dica: 'Números de destaque com barra de proporção.',
      padrao: {
        itens: [
          { v: '92%', r: 'Aderência ao procedimento', p: 92 },
          { v: '18', r: 'Ocorrências no trimestre', p: 45 },
          { v: '4,7', r: 'Nota média da equipe', p: 94 }
        ]
      },
      campos: [],
      listas: [{
        k: 'itens', r: 'Indicadores',
        campos: [
          { k: 'v', t: 'texto', r: 'Número' },
          { k: 'r', t: 'texto', r: 'Legenda' },
          { k: 'p', t: 'num', r: 'Barra %', min: 0, max: 100 }
        ],
        novo: { v: '0%', r: 'Novo indicador', p: 50 }
      }]
    },

    alerta: {
      rotulo: 'Aviso', icone: 'bi-exclamation-diamond',
      dica: 'Caixa de destaque para dica, atenção, risco ou boa prática.',
      padrao: { tipo: 'atencao', titulo: 'Atenção', texto: 'Nunca opere o equipamento sem a proteção instalada.' },
      campos: [
        { k: 'tipo', t: 'select', r: 'Tipo', opcoes: { dica: 'Dica', atencao: 'Atenção', perigo: 'Risco', ok: 'Boa prática' } },
        { k: 'titulo', t: 'texto', r: 'Título' },
        { k: 'texto', t: 'area', r: 'Texto' }
      ],
      listas: []
    },

    citacao: {
      rotulo: 'Citação', icone: 'bi-quote',
      dica: 'Depoimento ou frase de destaque.',
      padrao: { texto: 'Segurança não é meta: é condição para o trabalho acontecer.', autor: 'Comitê de Segurança' },
      campos: [{ k: 'texto', t: 'area', r: 'Frase' }, { k: 'autor', t: 'texto', r: 'Autor' }],
      listas: []
    },

    botao: {
      rotulo: 'Botão', icone: 'bi-hand-index-thumb',
      dica: 'Leva a um material de apoio ou formulário externo.',
      padrao: { rotulo: 'Abrir o procedimento completo', url: '', estilo: 'primario' },
      campos: [
        { k: 'rotulo', t: 'texto', r: 'Texto do botão' },
        { k: 'url', t: 'texto', r: 'Endereço (https://…)' },
        { k: 'estilo', t: 'select', r: 'Estilo', opcoes: { primario: 'Sólido', contorno: 'Contorno', suave: 'Suave' } }
      ],
      listas: []
    },

    tabela: {
      rotulo: 'Tabela', icone: 'bi-table',
      dica: 'Use a barra vertical | para separar as colunas.',
      padrao: {
        cab: 'Etapa | Responsável | Prazo',
        linhas: 'Abertura | Supervisor | Início do turno\nConferência | Operador | A cada 2 h\nFechamento | Supervisor | Fim do turno'
      },
      campos: [
        { k: 'cab', t: 'texto', r: 'Cabeçalho (colunas separadas por |)' },
        { k: 'linhas', t: 'area', r: 'Linhas (uma por linha, colunas separadas por |)', linhas: 6 }
      ],
      listas: []
    },

    linhaTempo: {
      rotulo: 'Linha do tempo', icone: 'bi-clock-history',
      dica: 'Marcos em sequência, cada um no seu cartão sobre o trilho.',
      padrao: {
        orient: 'h',
        itens: [
          { t: '2023', d: 'Implantação do novo procedimento.' },
          { t: '2024', d: 'Treinamento de todas as equipes.' },
          { t: '2025', d: 'Auditoria e revisão anual.' }
        ]
      },
      campos: [
        { k: 'orient', t: 'select', r: 'Orientação',
          opcoes: { h: 'Horizontal — cartões lado a lado', v: 'Vertical — lista com trilho' } },
        CAMPO_GUIADO, CAMPO_GUIA_AUTO
      ],
      listas: [{
        k: 'itens', r: 'Marcos',
        campos: [{ k: 't', t: 'texto', r: 'Marco' }, { k: 'd', t: 'area', r: 'Descrição' }, CAMPO_GUIA],
        novo: { t: 'Novo marco', d: 'Descrição do marco.' }
      }]
    }
  };

  var ORDEM = ['quiz', 'revelar', 'abas', 'passos', 'checklist', 'comparativo',
               'estatisticas', 'linhaTempo', 'alerta', 'citacao', 'botao', 'tabela'];

  function tipos() {
    return ORDEM.map(function (k) {
      return { chave: k, rotulo: ESQUEMA[k].rotulo, icone: ESQUEMA[k].icone, dica: ESQUEMA[k].dica };
    });
  }

  function padrao(tipo) {
    var e = ESQUEMA[tipo];
    return e ? clone(e.padrao) : {};
  }

  var ICONE_ALERTA = { dica: 'bi-lightbulb', atencao: 'bi-exclamation-triangle', perigo: 'bi-shield-exclamation', ok: 'bi-check-circle' };

  function html(tipo, dados) {
    var d = dados || {};
    switch (tipo) {

      case 'quiz': {
        var ops = (d.opcoes || []).map(function (o, i) {
          return '<button type="button" class="trb-quiz-op" data-i="' + i + '" data-ok="' + (o.ok ? '1' : '0') + '">' +
                   '<span class="trb-quiz-letra">' + String.fromCharCode(65 + i) + '</span>' +
                   '<span class="trb-quiz-txt">' + esc(o.t) + '</span>' +
                   '<i class="bi trb-quiz-icone"></i>' +
                 '</button>';
        }).join('');
        return '<div class="trb trb-quiz" data-bloco="quiz"' + (d.varias ? ' data-varias="1"' : '') + '>' +
                 '<div class="trb-quiz-topo"><i class="bi bi-patch-question"></i><span>Pergunta</span></div>' +
                 '<p class="trb-quiz-pergunta">' + esc(d.pergunta) + '</p>' +
                 '<div class="trb-quiz-ops">' + ops + '</div>' +
                 (d.feedback ? '<div class="trb-quiz-feedback"><i class="bi bi-info-circle"></i><span>' +
                   esc(d.feedback) + '</span></div>' : '') +
               '</div>';
      }

      case 'revelar': {
        var cards = (d.itens || []).map(function (o, i) {
          return '<button type="button" class="trb-card" data-bloco-ac="revelar"' + attrItem(d, o, i) + '>' +
                   '<span class="trb-card-frente"><span>' + esc(o.t) + '</span>' +
                     '<i class="bi bi-plus-circle"></i></span>' +
                   '<span class="trb-card-verso">' + esc(o.d) + '</span>' +
                 '</button>';
        }).join('');
        return '<div class="trb trb-revelar' + (d.guiado ? ' trb-guiado' : '') + '" data-bloco="revelar"' +
                 attrBloco(d, d.itens) + '>' +
                 (d.titulo ? '<div class="trb-titulo">' + esc(d.titulo) + '</div>' : '') +
                 '<div class="trb-cards">' + cards + '</div>' +
               '</div>';
      }

      case 'abas': {
        var itens = d.itens || [];
        var primeira = d.guiado ? -1 : 0;
        var tabs = itens.map(function (o, i) {
          return '<button type="button" class="trb-aba' + (i === primeira ? ' is-ativa' : '') + '" data-i="' + i + '"' +
                 attrItem(d, o, i) + '>' + esc(o.t) + '</button>';
        }).join('');
        var paineis = itens.map(function (o, i) {
          return '<div class="trb-aba-painel' + (i === primeira ? ' is-ativa' : '') + '" data-i="' + i + '">' +
                 esc(o.d) + '</div>';
        }).join('');
        return '<div class="trb trb-abas' + (d.guiado ? ' trb-guiado' : '') + '" data-bloco="abas"' +
                 attrBloco(d, itens) + '>' +
                 '<div class="trb-abas-barra">' + tabs + '</div>' +
                 '<div class="trb-abas-corpo">' + paineis +
                   (d.guiado ? '<div class="trb-aba-vazio">Abra as abas na ordem pedida pelo instrutor.</div>' : '') +
                 '</div>' +
               '</div>';
      }

      case 'passos': {
        var passos = (d.itens || []).map(function (o, i) {
          return '<li class="trb-passo"' + attrItem(d, o, i) +
                   (d.guiado ? ' role="button" tabindex="0"' : '') + '>' +
                   '<span class="trb-passo-num">' + (i + 1) + '</span>' +
                   '<span class="trb-passo-corpo"><b>' + esc(o.t) + '</b>' +
                     (o.d ? '<small>' + esc(o.d) + '</small>' : '') + '</span>' +
                 '</li>';
        }).join('');
        return '<div class="trb trb-passos' + (d.guiado ? ' trb-guiado' : '') + '" data-bloco="passos"' +
               attrBloco(d, d.itens) + '><ol class="trb-passos-lista">' + passos + '</ol></div>';
      }

      case 'checklist': {
        var itensC = (d.itens || []).map(function (o, i) {
          return '<button type="button" class="trb-check" data-bloco-ac="check"' + attrItem(d, o, i) + '>' +
                   '<i class="bi bi-square"></i><span>' + esc(o.t) + '</span>' +
                 '</button>';
        }).join('');
        return '<div class="trb trb-checklist' + (d.guiado ? ' trb-guiado' : '') + '" data-bloco="checklist"' +
                 attrBloco(d, d.itens) + '>' +
                 (d.titulo ? '<div class="trb-titulo">' + esc(d.titulo) + '</div>' : '') +
                 '<div class="trb-checks">' + itensC + '</div>' +
               '</div>';
      }

      case 'comparativo': {
        function coluna(titulo, itens, modo) {
          var li = (itens || []).map(function (o) {
            return '<li><i class="bi bi-' + (modo === 'ok' ? 'check-lg' : 'x-lg') + '"></i><span>' + esc(o.t) + '</span></li>';
          }).join('');
          return '<div class="trb-comp-col trb-comp-' + modo + '">' +
                   '<div class="trb-comp-head"><i class="bi bi-' +
                     (modo === 'ok' ? 'hand-thumbs-up' : 'hand-thumbs-down') + '"></i>' + esc(titulo) + '</div>' +
                   '<ul>' + li + '</ul></div>';
        }
        return '<div class="trb trb-comparativo" data-bloco="comparativo">' +
                 coluna(d.aTitulo || 'Faça assim', d.aItens, 'ok') +
                 coluna(d.bTitulo || 'Evite', d.bItens, 'nao') +
               '</div>';
      }

      case 'estatisticas': {
        var kpis = (d.itens || []).map(function (o) {
          var p = Math.max(0, Math.min(100, Number(o.p) || 0));
          return '<div class="trb-kpi">' +
                   '<b class="trb-kpi-v">' + esc(o.v) + '</b>' +
                   '<span class="trb-kpi-r">' + esc(o.r) + '</span>' +
                   '<span class="trb-kpi-barra"><i style="width:' + p + '%"></i></span>' +
                 '</div>';
        }).join('');
        return '<div class="trb trb-estatisticas" data-bloco="estatisticas">' + kpis + '</div>';
      }

      case 'alerta': {
        var tipoA = d.tipo || 'atencao';
        return '<div class="trb trb-alerta trb-alerta-' + esc(tipoA) + '" data-bloco="alerta">' +
                 '<i class="bi ' + (ICONE_ALERTA[tipoA] || 'bi-info-circle') + '"></i>' +
                 '<div><b>' + esc(d.titulo) + '</b>' + (d.texto ? '<p>' + esc(d.texto) + '</p>' : '') + '</div>' +
               '</div>';
      }

      case 'citacao':
        return '<figure class="trb trb-citacao" data-bloco="citacao">' +
                 '<i class="bi bi-quote"></i>' +
                 '<blockquote>' + esc(d.texto) + '</blockquote>' +
                 (d.autor ? '<figcaption>' + esc(d.autor) + '</figcaption>' : '') +
               '</figure>';

      case 'botao': {
        var url = String(d.url || '');
        var seguro = /^https?:\/\//i.test(url) ? url : '';
        return '<div class="trb trb-botao-wrap" data-bloco="botao">' +
                 '<a class="trb-botao trb-botao-' + esc(d.estilo || 'primario') + '"' +
                   (seguro ? ' href="' + esc(seguro) + '" target="_blank" rel="noopener"' : ' role="button"') + '>' +
                   '<span>' + esc(d.rotulo) + '</span><i class="bi bi-arrow-up-right"></i>' +
                 '</a>' +
               '</div>';
      }

      case 'tabela': {
        var cols = String(d.cab || '').split('|').map(function (c) { return c.trim(); });
        var thead = cols.length && cols[0] !== ''
          ? '<thead><tr>' + cols.map(function (c) { return '<th>' + esc(c) + '</th>'; }).join('') + '</tr></thead>'
          : '';
        var corpo = String(d.linhas || '').split('\n').filter(function (l) { return l.trim() !== ''; })
          .map(function (l) {
            return '<tr>' + l.split('|').map(function (c) { return '<td>' + esc(c.trim()) + '</td>'; }).join('') + '</tr>';
          }).join('');
        return '<div class="trb trb-tabela" data-bloco="tabela"><table>' + thead +
               '<tbody>' + corpo + '</tbody></table></div>';
      }

      case 'linhaTempo': {
        var marcos = (d.itens || []).map(function (o, i) {
          return '<li class="trb-marco"' + attrItem(d, o, i) +
                   (d.guiado ? ' role="button" tabindex="0"' : '') + '>' +
                   '<span class="trb-marco-ponto"></span>' +
                   '<b>' + esc(o.t) + '</b>' + (o.d ? '<small>' + esc(o.d) + '</small>' : '') +
                 '</li>';
        }).join('');
        return '<div class="trb trb-linha-tempo' +
               (String(d.orient || 'h') === 'v' ? ' trb-lt-v' : '') +
               (d.guiado ? ' trb-guiado' : '') + '" data-bloco="linhaTempo"' +
               attrBloco(d, d.itens) + '><ul>' + marcos + '</ul></div>';
      }

      default:
        return '<div class="trb trb-vazio">Bloco desconhecido.</div>';
    }
  }

  function posicionarIndicador(caixa, aba) {
    var barra = caixa.querySelector('.trb-abas-barra');
    if (!barra || !aba) { return; }
    var rb = barra.getBoundingClientRect();
    var ra = aba.getBoundingClientRect();
    if (!rb.width) { return; }
    barra.style.setProperty('--trb-aba-x', (ra.left - rb.left).toFixed(1) + 'px');
    barra.style.setProperty('--trb-aba-w', ra.width.toFixed(1) + 'px');
    barra.style.setProperty('--trb-aba-op', '1');
  }

  var SEL_INTERATIVO = '[data-bloco-ac], .trb-abas .trb-aba, .trb-quiz-op, .trb-botao';

  function emCena(no) {
    if (!no || !no.closest) { return false; }
    var slide = no.closest('.nv-slide');
    if (slide && !slide.classList.contains('is-ativo')) { return false; }
    var el = no.closest('.trc-el');
    if (!el) { return true; }
    if (el.classList.contains('nv-sumir')) { return false; }
    if (el.classList.contains('nv-oculto') && !el.classList.contains('nv-revelar')) {
      return false;
    }
    return true;
  }

  function alvoDoClique(ev) {
    if (!ev.target || !ev.target.closest) { return null; }

    if (ev.target.closest('.trb') && emCena(ev.target)) { return ev.target; }

    if (!ev.target.closest('.nv-slide, .nv-slide-conteudo, .trc-palco, #trcPalco')) {
      return null;
    }
    if (typeof ev.clientX !== 'number') { return null; }

    var blocos = document.querySelectorAll('.nv-slide.is-ativo .trb, #trcPalco .trb');
    var achado = null;

    Array.prototype.forEach.call(blocos, function (b) {
      if (!emCena(b)) { return; }
      Array.prototype.forEach.call(b.querySelectorAll(SEL_INTERATIVO), function (it) {
        var r = it.getBoundingClientRect();
        if (!r.width || !r.height) { return; }
        if (ev.clientX < r.left || ev.clientX > r.right) { return; }
        if (ev.clientY < r.top || ev.clientY > r.bottom) { return; }
        achado = it;
      });
    });

    if (achado && !global.__trbAvisou) {
      global.__trbAvisou = true;
      console.warn('[TrBlocos] o clique não chegou ao bloco pela árvore do DOM e foi '
        + 'resolvido pela posição. Elemento que recebeu o clique:', ev.target,
        '| item acionado:', achado);
    }
    return achado;
  }

  function ativar(raiz) {
    var alvo = raiz || document;
    if (alvo.__trbLigado) { return; }
    alvo.__trbLigado = true;

    alvo.addEventListener('click', function (ev) {
      var alvoClique = alvoDoClique(ev);
      if (!alvoClique) { return; }
      var tratou = false;

      var card = alvoClique.closest('[data-bloco-ac="revelar"]');
      if (card) {
        card.classList.toggle('is-aberto');
        tratou = true;
      }

      var chk = !tratou && alvoClique.closest('[data-bloco-ac="check"]');
      if (chk) {
        var marcado = chk.classList.toggle('is-marcado');
        var ic = chk.querySelector('i');
        if (ic) { ic.className = marcado ? 'bi bi-check-square-fill' : 'bi bi-square'; }
        tratou = true;
      }

      var aba = !tratou && alvoClique.closest('.trb-abas .trb-aba');
      if (aba) {
        var caixa = aba.closest('.trb-abas');
        var i = aba.dataset.i;
        Array.prototype.forEach.call(caixa.querySelectorAll('.trb-aba'), function (b) {
          b.classList.toggle('is-ativa', b === aba);
        });
        Array.prototype.forEach.call(caixa.querySelectorAll('.trb-aba-painel'), function (p) {
          p.classList.toggle('is-ativa', p.dataset.i === i);
        });
        posicionarIndicador(caixa, aba);
        tratou = true;
      }

      var op = !tratou && alvoClique.closest('.trb-quiz-op');
      if (op) {
        var quiz = op.closest('.trb-quiz');
        var varias = quiz.dataset.varias === '1';
        if (!quiz.classList.contains('is-respondido') || varias) {
          var certo = op.dataset.ok === '1';
          op.classList.add(certo ? 'is-certa' : 'is-errada');
          var icone = op.querySelector('.trb-quiz-icone');
          if (icone) { icone.className = 'bi trb-quiz-icone ' + (certo ? 'bi-check-circle-fill' : 'bi-x-circle-fill'); }

          if (!varias) {
            quiz.classList.add('is-respondido');
            Array.prototype.forEach.call(quiz.querySelectorAll('.trb-quiz-op'), function (b) {
              if (b !== op && b.dataset.ok === '1') { b.classList.add('is-gabarito'); }
            });
          }
          quiz.classList.add('is-mostrando-feedback');
        }
        tratou = true;
      }

      if (tratou) { ev.stopPropagation(); }
    }, true);
  }

  global.TrBlocos = {
    ESQUEMA: ESQUEMA,
    tipos: tipos,
    padrao: padrao,
    html: html,
    ativar: ativar
  };
})(window);
