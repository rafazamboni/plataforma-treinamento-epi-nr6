(function (global) {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };

  var GRADE = 0.5;
  var IMA = 1.2;

  var MODELOS = {
    titulo: {
      rotulo: 'Título', icone: 'bi-type-h1',
      novo: function () {
        return {
          t: 'texto', x: 8, y: 12, w: 60, h: 16,
          html: '<div class="trc-eyebrow">Seção</div><h2>Título do slide</h2>',
          s: { fs: 100, ta: 'left', va: 'center' }
        };
      }
    },
    texto: {
      rotulo: 'Texto', icone: 'bi-fonts',
      novo: function () {
        return {
          t: 'texto', x: 8, y: 34, w: 46, h: 18,
          html: '<p>Escreva aqui a explicação que aparece no slide.</p>',
          s: { fs: 100, ta: 'left', va: 'start' }
        };
      }
    },
    lista: {
      rotulo: 'Tópicos', icone: 'bi-list-ul',
      novo: function () {
        return {
          t: 'texto', x: 8, y: 34, w: 46, h: 30,
          html: '<ul><li>Primeiro ponto</li><li>Segundo ponto</li><li>Terceiro ponto</li></ul>',
          s: { fs: 100, ta: 'left', va: 'start' }
        };
      }
    },
    cartao: {
      rotulo: 'Cartão', icone: 'bi-square',
      novo: function () {
        return {
          t: 'texto', x: 8, y: 40, w: 30, h: 26,
          html: '<h3>Título do cartão</h3><p>Uma linha de apoio explicando o item.</p>',
          s: { fs: 100, ta: 'left', va: 'center', bg: '#FFFFFF', op: 96, pad: 3.5, br: 16, sh: 2,
               vis: 'elevado', hov: 'elevar' }
        };
      }
    },
    indicador: {
      rotulo: 'Indicador', icone: 'bi-graph-up',
      novo: function () {
        return {
          t: 'texto', x: 8, y: 40, w: 24, h: 22,
          html: '<div class="trc-kpi-num">92%</div><div class="trc-kpi-txt">de aderência</div>',
          s: { fs: 100, ta: 'center', va: 'center', bg: '#FFFFFF', op: 96, pad: 2.5, br: 16, sh: 1,
               vis: 'papel', hov: 'elevar' }
        };
      }
    },
    imagem: {
      rotulo: 'Imagem', icone: 'bi-image',
      novo: function (extra) {
        return {
          t: 'imagem', x: 55, y: 20, w: 34, h: 40,
          src: (extra && extra.src) || '',
          s: { fit: 'cover', br: 14, sh: 1, anim: 'zoom' }
        };
      }
    },
    video: {
      rotulo: 'Vídeo', icone: 'bi-play-btn',
      novo: function (extra) {
        return {
          t: 'video', x: 55, y: 20, w: 34, h: 40,
          src: (extra && extra.src) || '',
          s: {
            fit: 'cover', br: 14, sh: 1, anim: 'zoom',
            vMudo: 1, vLoop: 0, vInicio: 'auto', vVel: 1
          }
        };
      }
    },
    forma: {
      rotulo: 'Forma', icone: 'bi-square-fill',
      novo: function () {
        return { t: 'forma', x: 8, y: 70, w: 30, h: 10, s: { bg: '#1F93A6', op: 100, br: 12 } };
      }
    },
    divisor: {
      rotulo: 'Divisor', icone: 'bi-dash-lg',
      novo: function () {
        return { t: 'forma', x: 8, y: 30, w: 22, h: 0.9, s: { bg: '#1F93A6', op: 100, br: 4 } };
      }
    }
  };

  function esc(t) {
    return String(t == null ? '' : t)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function num(v, p) { var n = parseFloat(v); return isNaN(n) ? p : n; }
  function limitar(v, a, b) { return Math.max(a, Math.min(b, v)); }
  function arred(v) { return Math.round(v / GRADE) * GRADE; }

  function limparHtml(html) {
    var caixa = document.createElement('div');
    caixa.innerHTML = String(html || '');
    Array.prototype.forEach.call(caixa.querySelectorAll('script,style,iframe,object,embed,link'), function (n) {
      n.remove();
    });
    Array.prototype.forEach.call(caixa.querySelectorAll('*'), function (n) {
      Array.prototype.slice.call(n.attributes).forEach(function (a) {
        var nome = a.name.toLowerCase();
        if (nome.indexOf('on') === 0) { n.removeAttribute(a.name); }
        if (nome === 'contenteditable' || nome === 'spellcheck') { n.removeAttribute(a.name); }
      });
    });
    return caixa.innerHTML;
  }

  function estiloDoElemento(el) {
    if (global.TrCanvasVisual) { return global.TrCanvasVisual.estilo(el); }
    var s = el.s || {};
    var css = 'left:' + el.x + '%;top:' + el.y + '%;width:' + el.w + '%;height:' + el.h + '%;';
    css += 'z-index:' + (el.z || 1) + ';';
    if (s.bg) { css += 'background:' + s.bg + ';'; }
    if (s.cor) { css += 'color:' + s.cor + ';'; }
    if (s.fs && s.fs !== 100) { css += '--trc-fs:' + (s.fs / 100) + ';'; }
    if (s.ta) { css += 'text-align:' + s.ta + ';'; }
    if (s.va) { css += 'justify-content:' + (s.va === 'center' ? 'center' : (s.va === 'end' ? 'flex-end' : 'flex-start')) + ';'; }
    if (s.br != null) { css += 'border-radius:' + s.br + 'px;'; }
    return css;
  }

  function classesDoElemento(el, marcaOculto) {
    if (global.TrCanvasVisual) { return global.TrCanvasVisual.classeCompleta(el, marcaOculto); }
    return 'trc-el trc-' + el.t + (el.oculto ? ' ' + (marcaOculto || 'nv-oculto') : '');
  }

  function interiorDoElemento(el, urlImagem) {
    if (el.t === 'bloco') {
      return (global.TrBlocos && global.TrBlocos.html)
        ? global.TrBlocos.html(el.bloco, el.dados || {})
        : '';
    }
    if (el.t === 'imagem') {
      var src = el.src ? urlImagem(el.src) : '';
      return src
        ? '<img src="' + esc(src) + '" alt="' + esc(el.alt || '') + '" style="object-fit:' +
          esc((el.s && el.s.fit) || 'cover') + '">'
        : '<span class="trc-sem-img"><i class="bi bi-image"></i></span>';
    }
    if (el.t === 'video') {
      var vsrc = el.src ? urlImagem(el.src) : '';
      if (!vsrc) { return '<span class="trc-sem-img"><i class="bi bi-play-btn"></i></span>'; }
      var v = el.s || {};
      return '<video src="' + esc(vsrc) + '" playsinline preload="metadata"' +
             (v.vMudo ? ' muted' : '') +
             (v.vLoop ? ' loop' : '') +
             ' style="object-fit:' + esc(v.fit || 'cover') + '"></video>';
    }
    if (el.t === 'forma') { return ''; }
    return el.html || '';
  }

  function Canvas(cfg) {
    this.cfg = cfg || {};
    this.els = [];
    this.sel = null;
    this.palco = $('trcPalco');
    this.guias = $('trcGuias');
    this.inspetor = $('trcInspetor');
    this.area = $('trcArea');
    this.textarea = $('slideConteudo');
    this.editando = null;
    this._range = null;
    this._timer = null;
    this._silencio = false;
    this._montarEventos();
  }

  Canvas.prototype.urlImagem = function (arquivo) {
    if (/^https?:|^data:/.test(arquivo)) { return arquivo; }
    return this.cfg.base + 'midia.php?a=' + this.cfg.apresentacao + '&f=' + encodeURIComponent(arquivo);
  };

  Canvas.prototype.carregar = function (html) {
    if (this.editando) {
      if (this.editando.no) { this.editando.no.classList.remove('is-editando'); }
      this.editando = null;
      this._range = null;
    }
    clearTimeout(this._timer);
    this.els = [];
    this.sel = null;
    var texto = String(html || '');

    var caixa = document.createElement('div');
    caixa.innerHTML = texto;
    var dados = caixa.querySelector('script[data-tr-canvas]');

    if (dados) {
      try {
        var j = JSON.parse(dados.textContent || '{}');
        if (j && j.els && j.els.length) { this.els = j.els; }
      } catch (e) { this.els = []; }
    } else if (texto.trim() !== '') {
      this.els = [{
        id: 'e1', t: 'texto', x: 6, y: 10, w: 60, h: 70, z: 1,
        html: limparHtml(texto), s: { fs: 100, ta: 'left', va: 'start' }, legado: true
      }];
    }

    this.renderizar();
    this.selecionar(null);
    return this.els.length;
  };

  Canvas.prototype.novoId = function () {
    var n = 1;
    var usados = {};
    this.els.forEach(function (e) { usados[e.id] = 1; });
    while (usados['e' + n]) { n++; }
    return 'e' + n;
  };

  Canvas.prototype.paraHtml = function () {
    var self = this;
    if (!this.els.length) { return ''; }
    var corpo = this.els.map(function (el) {
      var classes = classesDoElemento(el, 'nv-oculto');
      var extras = global.TrCanvasVisual ? global.TrCanvasVisual.atributos(el) : '';
      return '<div class="' + classes + '" id="' + esc(el.id) + '"' + extras +
             ' style="' + esc(estiloDoElemento(el)) + '">' +
             interiorDoElemento(el, function (a) { return self.urlImagem(a); }) + '</div>';
    }).join('\n');

    var json = JSON.stringify({ v: 1, els: this.els });
    return '<script type="application/json" data-tr-canvas>' + json.replace(/<\//g, '<\\/') + '</' + 'script>\n' +
           '<div class="trc-palco">\n' + corpo + '\n</div>';
  };

  Canvas.prototype.sincronizar = function (imediato) {
    var self = this;
    if (this._silencio) { return; }
    clearTimeout(this._timer);
    var envia = function () {
      self.textarea.value = self.paraHtml();
      self.textarea.dispatchEvent(new Event('change', { bubbles: true }));
    };
    if (imediato) { envia(); } else { this._timer = setTimeout(envia, 500); }
  };

  Canvas.prototype.renderizar = function () {
    var self = this;
    this.palco.innerHTML = '';
    this.els.slice().sort(function (a, b) { return (a.z || 1) - (b.z || 1); }).forEach(function (el) {
      var d = document.createElement('div');
      d.className = classesDoElemento(el, 'is-oculto');
      if (el.t === 'imagem' && global.TrCanvasVisual) {
        if (global.TrCanvasVisual.atributos(el)) { d.setAttribute('data-giro-quarto', '1'); }
        else { d.removeAttribute('data-giro-quarto'); }
      }
      d.dataset.id = el.id;
      d.setAttribute('style', estiloDoElemento(el));
      d.innerHTML = interiorDoElemento(el, function (a) { return self.urlImagem(a); });
      if (el.t === 'texto') { d.dataset.editavel = '1'; }
      if (el.t === 'bloco') { d.dataset.bloco = el.bloco || ''; }
      ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'].forEach(function (dir) {
        var h = document.createElement('i');
        h.className = 'trc-alca trc-alca-' + dir;
        h.dataset.dir = dir;
        d.appendChild(h);
      });
      var rot = document.createElement('span');
      rot.className = 'trc-etiqueta';
      rot.textContent = '#' + el.id;
      d.appendChild(rot);
      self.palco.appendChild(d);
    });
    this._marcarSelecao();
  };

  Canvas.prototype.no = function (id) {
    return this.palco.querySelector('.trc-el[data-id="' + id + '"]');
  };

  Canvas.prototype.el = function (id) {
    for (var i = 0; i < this.els.length; i++) { if (this.els[i].id === id) { return this.els[i]; } }
    return null;
  };

  Canvas.prototype._marcarSelecao = function () {
    var self = this;
    Array.prototype.forEach.call(this.palco.children, function (n) {
      n.classList.toggle('is-sel', n.dataset.id === self.sel);
    });
  };

  Canvas.prototype.selecionar = function (id) {
    this.sel = id;
    this._marcarSelecao();
    this.montarInspetor();
    this.atualizarBarraFormato();
    if (id) {
      try { document.dispatchEvent(new CustomEvent('tr:elemento-selecionado')); } catch (e) {}
    }
  };

  Canvas.prototype.montarInspetor = function () {
    var el = this.sel ? this.el(this.sel) : null;
    var i = this.inspetor;
    if (!el) {
      i.innerHTML = '<p class="trc-vazio">Selecione um elemento no palco para editar posição, tamanho e aparência. ' +
                    'Use a barra acima para inserir novos elementos.</p>';
      return;
    }
    var s = el.s || {};
    var eTexto = el.t === 'texto';
    var eImagem = el.t === 'imagem';
    var eBloco = el.t === 'bloco';
    var eVideo = el.t === 'video';
    var esquema = eBloco && global.TrBlocos ? global.TrBlocos.ESQUEMA[el.bloco] : null;

    var h = '';
    h += '<div class="trc-insp-head"><b>' +
         (eBloco ? (esquema ? esquema.rotulo : 'Bloco')
                 : (eImagem ? 'Imagem' : (el.t === 'forma' ? 'Forma' : 'Texto'))) +
         '</b><code>#' + esc(el.id) + '</code></div>';

    if (eBloco) { h += formBloco(el, esquema); }

    h += '<div class="trc-grid4">' +
         campoNum('x', 'X %', el.x) + campoNum('y', 'Y %', el.y) +
         campoNum('w', 'L %', el.w) + campoNum('h', 'A %', el.h) +
         '</div>';

    if (eTexto || eBloco) {
      h += '<div class="trc-insp-linha">' +
           '<label class="trc-mini">Tamanho do bloco inteiro</label>' +
           '<input type="range" class="form-range" data-campo="fs" min="60" max="220" step="5" value="' + (s.fs || 100) + '">' +
           '</div>';
    }
    if (eTexto) {
      h += '<div class="trc-insp-linha trc-btn-linha" data-grupo="ta">' +
           btn('ta', 'left', 'bi-text-left') + btn('ta', 'center', 'bi-text-center') +
           btn('ta', 'right', 'bi-text-right') +
           '<span class="trc-sep"></span>' +
           btn('va', 'start', 'bi-align-top') + btn('va', 'center', 'bi-align-middle') +
           btn('va', 'end', 'bi-align-bottom') +
           '</div>';
    }

    if (eVideo) {
      h += '<div class="trc-secao"><span>Vídeo</span></div>';
      h += '<div class="trc-insp-linha trc-btn-linha">' +
           btn('fit', 'cover', 'bi-aspect-ratio', 'Preencher') +
           btn('fit', 'contain', 'bi-bounding-box', 'Encaixar') +
           '<button type="button" class="trc-ferr" id="trcTrocarVid" title="Trocar vídeo">' +
             '<i class="bi bi-upload"></i></button>' +
           '</div>';

      h += '<div class="trc-insp-linha"><label class="trc-mini">Quando começa</label>' +
           seletor('vInicio', {
             'auto': 'Ao abrir a tela',
             'fala': 'Quando a fala pedir',
             'visivel': 'Quando o elemento for revelado'
           }, s.vInicio || 'auto') + '</div>';

      h += '<div class="trc-insp-linha"><label class="trc-mini">Ao terminar</label>' +
           seletor('vLoop', { 0: 'Parar no último quadro', 1: 'Repetir sem parar' },
                   s.vLoop ? 1 : 0) + '</div>';

      h += '<div class="trc-insp-linha"><label class="trc-mini">Áudio</label>' +
           seletor('vMudo', { 1: 'Sem som (recomendado)', 0: 'Com som do arquivo' },
                   s.vMudo == null ? 1 : (s.vMudo ? 1 : 0)) + '</div>';

      h += '<div class="trc-grid2">' +
           campoRange('vVel', 'Velocidade', s.vVel == null ? 1 : s.vVel, 0.25, 2, 0.05) +
           campoRange('vIni', 'Começar em (s)', s.vIni == null ? 0 : s.vIni, 0, 60, 0.5) +
           '</div>';
    }

    if (eImagem) {
      h += '<div class="trc-insp-linha trc-btn-linha">' +
           btn('fit', 'cover', 'bi-aspect-ratio', 'Preencher') +
           btn('fit', 'contain', 'bi-bounding-box', 'Encaixar') +
           '<button type="button" class="trc-ferr" id="trcTrocarImg" title="Trocar imagem"><i class="bi bi-upload"></i></button>' +
           '</div>';

      h += '<div class="trc-secao"><span>Enquadramento</span></div>';
      h += '<div class="trc-grid3">' +
           campoRange('zoom', 'Aproximação', s.zoom == null ? 1 : s.zoom, 1, 4, 0.05) +
           campoRange('focoX', 'Foco horizontal', s.focoX == null ? 50 : s.focoX, 0, 100, 1) +
           campoRange('focoY', 'Foco vertical', s.focoY == null ? 50 : s.focoY, 0, 100, 1) +
           '</div>';
      h += '<div class="trc-insp-linha trc-btn-linha">' +
           btn('giro', 0, 'bi-arrow-up', '0°') +
           btn('giro', 90, 'bi-arrow-clockwise', '90°') +
           btn('giro', 180, 'bi-arrow-down', '180°') +
           btn('giro', 270, 'bi-arrow-counterclockwise', '270°') +
           '</div>';

      h += '<div class="trc-secao"><span>Destaque durante a fala</span></div>';
      h += '<div class="trc-grid3">' +
           campoRange('zoom2', 'Aproximação', s.zoom2 == null ? (s.zoom == null ? 1.6 : s.zoom) : s.zoom2, 1, 4, 0.05) +
           campoRange('focoX2', 'Foco horizontal', s.focoX2 == null ? 50 : s.focoX2, 0, 100, 1) +
           campoRange('focoY2', 'Foco vertical', s.focoY2 == null ? 50 : s.focoY2, 0, 100, 1) +
           '</div>';
      h += '<div class="trc-insp-linha trc-btn-linha">' +
           btn('giro2', 0, 'bi-arrow-up', '0°') +
           btn('giro2', 90, 'bi-arrow-clockwise', '90°') +
           btn('giro2', 180, 'bi-arrow-down', '180°') +
           btn('giro2', 270, 'bi-arrow-counterclockwise', '270°') +
           '</div>';
      h += '<div class="trc-grid4">' +
           campoNum('x2', 'X %', s.x2 == null ? el.x : s.x2) +
           campoNum('y2', 'Y %', s.y2 == null ? el.y : s.y2) +
           campoNum('w2', 'L %', s.w2 == null ? el.w : s.w2) +
           campoNum('h2', 'A %', s.h2 == null ? el.h : s.h2) +
           '</div>';
      h += '<div class="trc-insp-linha trc-btn-linha">' +
           '<button type="button" class="trc-ferr trc-ferr-larga" data-acao="mov-atual" ' +
             'title="Copiar a posição e o tamanho atuais para o destaque">' +
             '<i class="bi bi-bounding-box-circles"></i> Usar a posição atual</button>' +
           '<button type="button" class="trc-ferr trc-ferr-larga" data-acao="mov-limpar" ' +
             'title="Destaque sem deslocamento: a imagem fica onde está">' +
             '<i class="bi bi-x-circle"></i> Sem mover</button>' +
           '</div>';

      h += '<div class="trc-grid2">' +
           campoRange('imgDur', 'Tempo da transição (s)', s.imgDur == null ? 1.1 : s.imgDur, 0.3, 3, 0.1) +
           campoRange('voltaSeg', 'Voltar sozinha após (s)', s.voltaSeg == null ? 0 : s.voltaSeg, 0, 20, 0.5) +
           '</div>';
      h += '<div class="trc-insp-linha trc-btn-linha">' +
           '<button type="button" class="trc-ferr trc-ferr-larga" data-acao="previa-img" ' +
             'title="Alternar entre repouso e destaque">' +
             '<i class="bi bi-play-fill"></i> Ver o destaque</button>' +
           '</div>';
    }

    h += '<div class="trc-insp-linha trc-cores">' +
         '<label class="trc-mini">Fundo</label>' +
         '<input type="color" class="form-control form-control-color form-control-sm" data-campo="bg" value="' + (s.bg || '#FFFFFF') + '">' +
         '<label class="trc-check"><input type="checkbox" data-campo="bg-on"' + (s.bg ? ' checked' : '') + '><span>usar</span></label>' +
         (eTexto ? '<label class="trc-mini">Texto</label>' +
           '<input type="color" class="form-control form-control-color form-control-sm" data-campo="cor" value="' + (s.cor || '#16222F') + '">' +
           '<label class="trc-check"><input type="checkbox" data-campo="cor-on"' + (s.cor ? ' checked' : '') + '><span>usar</span></label>' : '') +
         '</div>';

    h += '<div class="trc-grid3">' +
         campoRange('br', 'Cantos', s.br == null ? 0 : s.br, 0, 60, 1) +
         campoRange('sh', 'Sombra', s.sh || 0, 0, 3, 1) +
         campoRange('op', 'Opacidade', s.op == null ? 100 : s.op, 20, 100, 5) +
         '</div>';

    var CV = global.TrCanvasVisual;
    if (CV) {
      h += '<div class="trc-secao"><span>Acabamento</span></div>';

      if (eTexto || eBloco) {
        h += '<div class="trc-insp-linha">' +
             '<label class="trc-mini">Espaçamento interno</label>' +
             '<input type="range" data-campo="pad" min="0" max="10" step="0.5" value="' +
               (s.pad == null ? 0 : s.pad) + '">' +
             '</div>';
      }

      h += '<div class="trc-insp-linha"><label class="trc-mini">Visual do bloco</label>' +
           seletor('vis', CV.VISUAIS, s.vis || '') + '</div>';

      h += '<div class="trc-insp-linha"><label class="trc-mini">Animação de entrada</label>' +
           seletor('anim', CV.ANIMACOES, s.anim || '') + '</div>';

      if (s.anim) {
        h += '<div class="trc-insp-linha trc-btn-linha">' +
             '<label class="trc-num trc-num-range" style="flex:1"><span>Atraso</span>' +
               '<input type="range" data-campo="animd" min="0" max="3" step="0.1" value="' +
                 (s.animd == null ? 0 : s.animd) + '"></label>' +
             '<button type="button" class="trc-ferr" data-acao="previa" title="Ver a animação">' +
               '<i class="bi bi-play-fill"></i></button>' +
             '</div>';
      }

      h += '<div class="trc-insp-linha"><label class="trc-mini">Ao passar o mouse</label>' +
           seletor('hov', CV.HOVER, s.hov || '') + '</div>';
    }

    h += '<label class="trc-check trc-check-larga"><input type="checkbox" data-campo="oculto"' +
         (el.oculto ? ' checked' : '') + '>' +
         '<span>Só aparece quando a fala revelar (<code>#' + esc(el.id) + '</code>)</span></label>';

    h += '<div class="trc-insp-linha trc-btn-linha">' +
         '<button type="button" class="trc-ferr" data-acao="frente" title="Trazer para frente"><i class="bi bi-front"></i></button>' +
         '<button type="button" class="trc-ferr" data-acao="tras" title="Enviar para trás"><i class="bi bi-back"></i></button>' +
         '<button type="button" class="trc-ferr" data-acao="duplicar" title="Duplicar"><i class="bi bi-copy"></i></button>' +
         '<button type="button" class="trc-ferr trc-ferr-perigo" data-acao="excluir" title="Excluir"><i class="bi bi-trash"></i></button>' +
         '</div>';

    i.innerHTML = h;

    function formBloco(el, esq) {
      if (!esq) { return '<p class="trc-vazio">Bloco não reconhecido.</p>'; }
      var d = el.dados || (el.dados = {});
      var out = '<p class="trc-vazio trc-dica-bloco">' + esc(esq.dica || '') + '</p>';

      (esq.campos || []).forEach(function (c) {
        out += '<div class="trc-insp-linha"><label class="trc-mini">' + esc(c.r) + '</label>' +
               entradaBloco(c, d[c.k], 'data-bd="' + esc(c.k) + '"') + '</div>';
      });

      (esq.listas || []).forEach(function (L) {
        var itens = d[L.k] || (d[L.k] = []);
        out += '<div class="trc-lista"><div class="trc-lista-head"><span class="trc-mini">' + esc(L.r) + '</span>' +
               '<button type="button" class="trc-ferr" data-bl-add="' + esc(L.k) + '" title="Adicionar">' +
               '<i class="bi bi-plus-lg"></i></button></div>';
        itens.forEach(function (item, i) {
          out += '<div class="trc-lista-item"><div class="trc-lista-item-topo">' +
                 '<span class="trc-lista-num">' + (i + 1) + '</span>' +
                 '<button type="button" class="trc-ferr" data-bl-mov="cima" data-bl="' + esc(L.k) + '" data-bi="' + i + '"><i class="bi bi-chevron-up"></i></button>' +
                 '<button type="button" class="trc-ferr" data-bl-mov="baixo" data-bl="' + esc(L.k) + '" data-bi="' + i + '"><i class="bi bi-chevron-down"></i></button>' +
                 '<button type="button" class="trc-ferr trc-ferr-perigo" data-bl-del="' + esc(L.k) + '" data-bi="' + i + '"><i class="bi bi-x-lg"></i></button>' +
                 '</div>';
          (L.campos || []).forEach(function (c) {
            out += entradaBloco(c, item[c.k],
              'data-bl="' + esc(L.k) + '" data-bi="' + i + '" data-bk="' + esc(c.k) + '"', c.r);
          });
          out += '</div>';
        });
        out += '</div>';
      });
      return out;
    }

    function entradaBloco(c, valor, attrs, rotuloInline) {
      var v = valor == null ? '' : valor;
      if (c.t === 'bool') {
        return '<label class="trc-check"><input type="checkbox" ' + attrs +
               (v ? ' checked' : '') + '><span>' + esc(c.r) + '</span></label>';
      }
      var rot = rotuloInline ? '<span class="trc-mini trc-mini-inline">' + esc(c.r) + '</span>' : '';
      if (c.t === 'area') {
        return rot + '<textarea class="trc-entrada" rows="' + (c.linhas || 2) + '" ' + attrs + '>' + esc(v) + '</textarea>';
      }
      if (c.t === 'fala') {
        var falas = global.TR_FALAS_SLIDE || [];
        var ops = '<option value="0"' + (!Number(v) ? ' selected' : '') + '>— sem passo guiado —</option>';
        falas.forEach(function (f) {
          ops += '<option value="' + f.n + '"' + (Number(v) === f.n ? ' selected' : '') + '>' +
                 f.n + '. ' + esc(String(f.t || '').slice(0, 46)) + '</option>';
        });
        if (Number(v) > falas.length) {
          ops += '<option value="' + esc(v) + '" selected>' + esc(v) +
                 '. (fala não existe mais)</option>';
        }
        return rot + '<select class="trc-entrada" ' + attrs + '>' + ops + '</select>';
      }
      if (c.t === 'num') {
        return rot + '<input type="number" class="trc-entrada" min="' + (c.min || 0) + '" max="' + (c.max || 100) +
               '" ' + attrs + ' value="' + esc(v) + '">';
      }
      if (c.t === 'select') {
        var ops = Object.keys(c.opcoes || {}).map(function (k) {
          return '<option value="' + esc(k) + '"' + (String(k) === String(v) ? ' selected' : '') + '>' +
                 esc(c.opcoes[k]) + '</option>';
        }).join('');
        return rot + '<select class="trc-entrada" ' + attrs + '>' + ops + '</select>';
      }
      return rot + '<input type="text" class="trc-entrada" ' + attrs + ' value="' + esc(v) + '">';
    }

    function seletor(campo, mapa, valor) {
      var ops = Object.keys(mapa).map(function (k) {
        return '<option value="' + esc(k) + '"' + (String(k) === String(valor) ? ' selected' : '') + '>' +
               esc(mapa[k]) + '</option>';
      }).join('');
      return '<select class="trc-entrada" data-campo="' + campo + '">' + ops + '</select>';
    }

    function campoNum(campo, rot, valor) {
      return '<label class="trc-num"><span>' + rot + '</span>' +
             '<input type="number" step="0.5" data-campo="' + campo + '" value="' + (Math.round(valor * 10) / 10) + '"></label>';
    }
    function campoRange(campo, rot, valor, min, max, passo) {
      return '<label class="trc-num trc-num-range"><span>' + rot + '</span>' +
             '<input type="range" data-campo="' + campo + '" min="' + min + '" max="' + max +
             '" step="' + passo + '" value="' + valor + '"></label>';
    }
    function btn(campo, valor, icone, titulo) {
      var ativo = (s[campo] || (campo === 'ta' ? 'left' : campo === 'va' ? 'start' : 'cover')) === valor;
      return '<button type="button" class="trc-ferr' + (ativo ? ' is-ativo' : '') + '" data-campo="' + campo +
             '" data-valor="' + valor + '"' + (titulo ? ' title="' + titulo + '"' : '') + '><i class="bi ' + icone + '"></i></button>';
    }
  };

  Canvas.prototype.inserir = function (chave, extra) {
    var modelo = MODELOS[chave];
    if (!modelo) { return; }
    var el = modelo.novo(extra);
    el.id = this.novoId();
    el.z = this.els.reduce(function (m, e) { return Math.max(m, e.z || 1); }, 0) + 1;
    while (this.els.some(function (e) { return e.x === el.x && e.y === el.y; })) {
      el.x = limitar(el.x + 3, 0, 90); el.y = limitar(el.y + 3, 0, 90);
    }
    this.els.push(el);
    this.renderizar();
    this.selecionar(el.id);
    this.sincronizar(true);
  };

  Canvas.prototype.inserirBloco = function (tipo) {
    if (!global.TrBlocos || !global.TrBlocos.ESQUEMA[tipo]) { return; }
    var el = {
      t: 'bloco', bloco: tipo, dados: global.TrBlocos.padrao(tipo),
      x: 8, y: 26, w: 52, h: 42, s: { fs: 100, va: 'start' }
    };
    if (tipo === 'alerta' || tipo === 'citacao' || tipo === 'botao') { el.h = 16; el.w = 46; }
    if (tipo === 'estatisticas' || tipo === 'linhaTempo') { el.h = 24; el.w = 60; }
    el.id = this.novoId();
    el.z = this.els.reduce(function (m, e) { return Math.max(m, e.z || 1); }, 0) + 1;
    this.els.push(el);
    this.renderizar();
    this.selecionar(el.id);
    this.sincronizar(true);
  };

  Canvas.prototype.repintar = function (id) {
    var el = this.el(id);
    var no = this.no(id);
    if (!el || !no) { return; }
    var self = this;
    var guardar = [];
    Array.prototype.forEach.call(no.childNodes, function (n) {
      if (n.classList && (n.classList.contains('trc-alca') || n.classList.contains('trc-etiqueta'))) { guardar.push(n); }
    });
    no.innerHTML = interiorDoElemento(el, function (a) { return self.urlImagem(a); });
    guardar.forEach(function (n) { no.appendChild(n); });
    no.setAttribute('style', estiloDoElemento(el));
  };

  Canvas.prototype.aplicarCampoBloco = function (entrada) {
    var el = this.el(this.sel);
    if (!el || el.t !== 'bloco') { return; }
    el.dados = el.dados || {};
    var valor = entrada.type === 'checkbox' ? entrada.checked
              : (entrada.type === 'number' ? num(entrada.value, 0) : entrada.value);

    if (entrada.dataset.bd) {
      el.dados[entrada.dataset.bd] = valor;
    } else if (entrada.dataset.bl) {
      var lista = el.dados[entrada.dataset.bl] || (el.dados[entrada.dataset.bl] = []);
      var item = lista[Number(entrada.dataset.bi)];
      if (!item) { return; }
      item[entrada.dataset.bk] = valor;
    } else { return; }

    this.repintar(el.id);
    this.sincronizar();
  };

  Canvas.prototype.listaBloco = function (acao, chave, indice, direcao) {
    var el = this.el(this.sel);
    if (!el || el.t !== 'bloco' || !global.TrBlocos) { return; }
    var esq = global.TrBlocos.ESQUEMA[el.bloco];
    if (!esq) { return; }
    var def = (esq.listas || []).filter(function (L) { return L.k === chave; })[0];
    if (!def) { return; }
    el.dados = el.dados || {};
    var lista = el.dados[chave] || (el.dados[chave] = []);

    if (acao === 'add') {
      lista.push(JSON.parse(JSON.stringify(def.novo)));
    } else if (acao === 'del') {
      if (lista.length <= 1) { return; }
      lista.splice(indice, 1);
    } else if (acao === 'mov') {
      var destino = direcao === 'cima' ? indice - 1 : indice + 1;
      if (destino < 0 || destino >= lista.length) { return; }
      lista.splice(destino, 0, lista.splice(indice, 1)[0]);
    }
    this.repintar(el.id);
    this.montarInspetor();
    this.sincronizar(true);
  };

  Canvas.prototype.remover = function (id) {
    this.encerrarEdicao(true);
    this.els = this.els.filter(function (e) { return e.id !== id; });
    this.sel = null;
    this.renderizar();
    this.selecionar(null);
    this.sincronizar(true);
  };

  Canvas.prototype.duplicar = function (id) {
    var o = this.el(id);
    if (!o) { return; }
    var c = JSON.parse(JSON.stringify(o));
    c.id = this.novoId();
    c.x = limitar(c.x + 3, 0, 95);
    c.y = limitar(c.y + 3, 0, 95);
    c.z = this.els.reduce(function (m, e) { return Math.max(m, e.z || 1); }, 0) + 1;
    this.els.push(c);
    this.renderizar();
    this.selecionar(c.id);
    this.sincronizar(true);
  };

  Canvas.prototype.ordenar = function (id, frente) {
    var el = this.el(id);
    if (!el) { return; }
    var zs = this.els.map(function (e) { return e.z || 1; });
    el.z = frente ? Math.max.apply(null, zs) + 1 : Math.min.apply(null, zs) - 1;
    this.renderizar();
    this._marcarSelecao();
    this.sincronizar();
  };

  Canvas.prototype._guias = function (el) {
    var linhas = [];
    var self = this;
    var cx = el.x + el.w / 2, cy = el.y + el.h / 2;
    if (Math.abs(cx - 50) < IMA) { el.x = 50 - el.w / 2; linhas.push(['v', 50]); }
    if (Math.abs(cy - 50) < IMA) { el.y = 50 - el.h / 2; linhas.push(['h', 50]); }
    this.els.forEach(function (o) {
      if (o.id === el.id) { return; }
      [[o.x, 'x'], [o.x + o.w, 'x']].forEach(function (p) {
        if (Math.abs(el.x - p[0]) < IMA) { el.x = p[0]; linhas.push(['v', p[0]]); }
        if (Math.abs(el.x + el.w - p[0]) < IMA) { el.x = p[0] - el.w; linhas.push(['v', p[0]]); }
      });
      [[o.y, 'y'], [o.y + o.h, 'y']].forEach(function (p) {
        if (Math.abs(el.y - p[0]) < IMA) { el.y = p[0]; linhas.push(['h', p[0]]); }
        if (Math.abs(el.y + el.h - p[0]) < IMA) { el.y = p[0] - el.h; linhas.push(['h', p[0]]); }
      });
    });
    self.guias.innerHTML = linhas.map(function (l) {
      return l[0] === 'v'
        ? '<i class="trc-guia trc-guia-v" style="left:' + l[1] + '%"></i>'
        : '<i class="trc-guia trc-guia-h" style="top:' + l[1] + '%"></i>';
    }).join('');
  };

  Canvas.prototype._limparGuias = function () { this.guias.innerHTML = ''; };

  Canvas.prototype.editarTexto = function (no, selecionarTudo) {
    var self = this;
    var el = this.el(no.dataset.id);
    if (!el || el.t !== 'texto') { return; }
    if (this.editando && this.editando.no === no) { return; }
    if (this.editando) {
      this.encerrarEdicao();
      no = this.no(el.id);
      if (!no) { return; }
    }

    var corpo = document.createElement('div');
    corpo.className = 'trc-editor';
    corpo.contentEditable = 'true';
    corpo.spellcheck = true;
    corpo.innerHTML = el.html || '';

    Array.prototype.slice.call(no.childNodes).forEach(function (n) {
      if (!(n.classList && (n.classList.contains('trc-alca') || n.classList.contains('trc-etiqueta')))) { n.remove(); }
    });
    no.insertBefore(corpo, no.firstChild);
    no.classList.add('is-editando');

    this.editando = { no: no, corpo: corpo, id: el.id };
    corpo.focus();

    var sel = document.getSelection();
    var r = document.createRange();
    r.selectNodeContents(corpo);
    sel.removeAllRanges();
    sel.addRange(r);
    if (!selecionarTudo) { sel.collapseToEnd(); }
    this.guardarSelecao();

    corpo.addEventListener('focusout', function (ev) {
      var destino = ev.relatedTarget;
      if (destino && destino.closest && destino.closest('#trcFmt')) { return; }
      self.encerrarEdicao();
    });
    corpo.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape') { ev.preventDefault(); self.encerrarEdicao(); return; }
      ev.stopPropagation();
    });
    ['keyup', 'mouseup', 'input'].forEach(function (evt) {
      corpo.addEventListener(evt, function () {
        self.guardarSelecao();
        if (evt === 'input') { self.gravarTextoEditado(false); }
        self.atualizarBarraFormato();
      });
    });

    this.atualizarBarraFormato();
  };

  Canvas.prototype.guardarSelecao = function () {
    if (!this.editando) { return; }
    var sel = document.getSelection();
    if (!sel || !sel.rangeCount) { return; }
    var r = sel.getRangeAt(0);
    if (this.editando.corpo.contains(r.commonAncestorContainer)) { this._range = r.cloneRange(); }
  };

  Canvas.prototype.restaurarSelecao = function () {
    if (!this.editando || !this._range) { return; }
    var sel = document.getSelection();
    sel.removeAllRanges();
    sel.addRange(this._range);
  };

  Canvas.prototype.gravarTextoEditado = function (imediato) {
    if (!this.editando) { return; }
    var el = this.el(this.editando.id);
    if (!el) { return; }
    el.html = limparHtml(this.editando.corpo.innerHTML);
    this.sincronizar(imediato === true);
  };

  Canvas.prototype.encerrarEdicao = function (semRender) {
    if (!this.editando) { return; }
    var no = this.editando.no;
    this.gravarTextoEditado(true);
    this.editando = null;
    this._range = null;
    if (no) { no.classList.remove('is-editando'); }
    if (!semRender) {
      this.renderizar();
      this._marcarSelecao();
    }
    this.atualizarBarraFormato();
  };

  Canvas.prototype.formatar = function (cmd, valor) {
    var el;
    if (!this.editando) {
      el = this.sel ? this.el(this.sel) : null;
      if (!el || el.t !== 'texto') {
        if (global.trToast) { global.trToast('Selecione um bloco de texto no palco (duplo clique para escrever).', 'aviso'); }
        return;
      }
      this.editarTexto(this.no(el.id), true);
      if (!this.editando) { return; }
    }

    this.editando.corpo.focus();
    this.restaurarSelecao();
    try { document.execCommand('styleWithCSS', false, true); } catch (e) {}

    if (cmd === 'tamanho') {
      envolverSelecao('font-size:' + (valor === '+' ? '1.18em' : '0.85em'), false);
    } else if (cmd === 'tamanho-abs') {
      if (valor) { envolverSelecao('font-size:' + valor + 'em', true); }
    } else if (cmd === 'bloco') {
      if (valor) { try { document.execCommand('formatBlock', false, valor); } catch (e2) {} }
    } else if (cmd === 'foreColor') {
      try { document.execCommand('foreColor', false, valor); } catch (e3) {}
    } else {
      try { document.execCommand(cmd, false, null); } catch (e4) {}
    }

    this.guardarSelecao();
    this.gravarTextoEditado(true);
    this.atualizarBarraFormato();
  };

  function envolverSelecao(css, limparInterno) {
    var sel = document.getSelection();
    if (!sel || !sel.rangeCount) { return false; }
    var r = sel.getRangeAt(0);
    if (r.collapsed) { return false; }

    var frag = r.extractContents();
    if (limparInterno) {
      Array.prototype.forEach.call(frag.querySelectorAll('[style]'), function (n) { n.style.fontSize = ''; });
    }
    var span = document.createElement('span');
    span.setAttribute('style', css);
    span.appendChild(frag);
    r.insertNode(span);

    sel.removeAllRanges();
    var novo = document.createRange();
    novo.selectNodeContents(span);
    sel.addRange(novo);
    return true;
  }

  Canvas.prototype.atualizarBarraFormato = function () {
    var barra = $('trcFmt');
    if (!barra) { return; }
    var editando = !!this.editando;
    var elTexto = this.sel && this.el(this.sel) && this.el(this.sel).t === 'texto';
    barra.classList.toggle('is-inativa', !editando && !elTexto);

    var dica = $('trcFmtDica');
    if (dica) {
      dica.textContent = editando
        ? 'Selecione o trecho e aplique a formatação.'
        : (elTexto ? 'Duplo clique no bloco para escrever, ou clique num botão para editar tudo.'
                   : 'Dê um duplo clique num texto do palco para escrever e formatar.');
    }

    ['bold', 'italic', 'underline', 'strikeThrough', 'insertUnorderedList', 'insertOrderedList'].forEach(function (c) {
      var b = barra.querySelector('[data-fmt="' + c + '"]');
      if (!b) { return; }
      var on = false;
      if (editando) { try { on = document.queryCommandState(c); } catch (e) { on = false; } }
      b.classList.toggle('is-ativo', !!on);
    });
  };

  Canvas.prototype.aplicarCampo = function (campo, entrada) {
    var self = this;
    var el = this.el(this.sel);
    if (!el) { return; }
    el.s = el.s || {};

    if (campo === 'x' || campo === 'y' || campo === 'w' || campo === 'h') {
      el[campo] = limitar(num(entrada.value, el[campo]), -10, 200);
    } else if (campo === 'oculto') {
      el.oculto = entrada.checked;
    } else if (campo === 'bg-on') {
      el.s.bg = entrada.checked ? (this.inspetor.querySelector('[data-campo="bg"]').value || '#FFFFFF') : null;
    } else if (campo === 'cor-on') {
      el.s.cor = entrada.checked ? (this.inspetor.querySelector('[data-campo="cor"]').value || '#16222F') : null;
    } else if (campo === 'bg') {
      var ligado = this.inspetor.querySelector('[data-campo="bg-on"]');
      if (ligado) { ligado.checked = true; }
      el.s.bg = entrada.value;
    } else if (campo === 'cor') {
      var lig2 = this.inspetor.querySelector('[data-campo="cor-on"]');
      if (lig2) { lig2.checked = true; }
      el.s.cor = entrada.value;
    } else if (campo === 'vis' || campo === 'anim' || campo === 'hov' || campo === 'vInicio') {
      el.s[campo] = entrada.value;
      var noC = this.no(el.id);
      if (noC) {
        noC.setAttribute('style', estiloDoElemento(el));
        noC.className = classesDoElemento(el, 'is-oculto');
      }
      if (campo === 'anim') { this.montarInspetor(); }
      this.sincronizar();
      return;
    } else {
      el.s[campo] = num(entrada.value, 0);
    }

    if (el.t === 'video' && ['vLoop', 'vMudo', 'fit'].indexOf(campo) >= 0) {
      var noV = this.no(el.id);
      if (noV) { noV.innerHTML = interiorDoElemento(el, function (a) { return self.urlImagem(a); }); }
    }

    var no = this.no(el.id);
    if (no) {
      no.setAttribute('style', estiloDoElemento(el));
      no.classList.toggle('is-oculto', !!el.oculto);
      if (el.t === 'imagem') {
        var img = no.querySelector('img');
        if (img) { img.style.objectFit = el.s.fit || 'cover'; }
      }
    }
    this.sincronizar();
  };

  Canvas.prototype._montarEventos = function () {
    var self = this;

    this.palco.addEventListener('pointerdown', function (ev) {
      var no = ev.target.closest('.trc-el');
      if (!no) { self.encerrarEdicao(); self.selecionar(null); return; }
      if (no.classList.contains('is-editando')) { return; }
      if (self.editando) { self.encerrarEdicao(); no = self.no(no.dataset.id) || no; }

      var id = no.dataset.id;
      self.selecionar(id);
      var el = self.el(id);
      if (!el) { return; }

      var alca = ev.target.classList.contains('trc-alca') ? ev.target.dataset.dir : '';
      var caixa = self.palco.getBoundingClientRect();
      var ini = { x: ev.clientX, y: ev.clientY, ex: el.x, ey: el.y, ew: el.w, eh: el.h };
      ev.preventDefault();
      try { no.setPointerCapture(ev.pointerId); } catch (x) {}
      no.classList.add('is-movendo');

      function mover(e) {
        var dx = ((e.clientX - ini.x) / caixa.width) * 100;
        var dy = ((e.clientY - ini.y) / caixa.height) * 100;

        if (!alca) {
          el.x = arred(limitar(ini.ex + dx, -10, 105 - ini.ew));
          el.y = arred(limitar(ini.ey + dy, -10, 105 - ini.eh));
        } else {
          if (alca.indexOf('e') >= 0) { el.w = arred(limitar(ini.ew + dx, 2, 110 - el.x)); }
          if (alca.indexOf('s') >= 0) { el.h = arred(limitar(ini.eh + dy, 1, 110 - el.y)); }
          if (alca.indexOf('w') >= 0) {
            var nw = arred(limitar(ini.ew - dx, 2, ini.ex + ini.ew + 10));
            el.x = arred(ini.ex + ini.ew - nw); el.w = nw;
          }
          if (alca.indexOf('n') >= 0) {
            var nh = arred(limitar(ini.eh - dy, 1, ini.ey + ini.eh + 10));
            el.y = arred(ini.ey + ini.eh - nh); el.h = nh;
          }
        }
        self._guias(el);
        no.setAttribute('style', estiloDoElemento(el));
        self.montarInspetor();
      }

      function soltar(e) {
        no.classList.remove('is-movendo');
        self._limparGuias();
        self.palco.removeEventListener('pointermove', mover);
        self.palco.removeEventListener('pointerup', soltar);
        self.palco.removeEventListener('pointercancel', soltar);
        try { no.releasePointerCapture(e.pointerId); } catch (x) {}
        self.sincronizar(true);
      }

      self.palco.addEventListener('pointermove', mover);
      self.palco.addEventListener('pointerup', soltar);
      self.palco.addEventListener('pointercancel', soltar);
    });

    this.palco.addEventListener('dblclick', function (ev) {
      var no = ev.target.closest('.trc-el');
      if (!no || no.dataset.editavel !== '1') { return; }
      self.editarTexto(no);
    });

    document.addEventListener('keydown', function (ev) {
      if (!self.sel || self.editando) { return; }
      var alvo = ev.target;
      if (alvo && (alvo.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(alvo.tagName || ''))) { return; }
      var el = self.el(self.sel);
      if (!el) { return; }
      var passo = ev.shiftKey ? 5 : GRADE;
      var usou = true;
      if (ev.key === 'ArrowLeft')  { el.x = arred(el.x - passo); }
      else if (ev.key === 'ArrowRight') { el.x = arred(el.x + passo); }
      else if (ev.key === 'ArrowUp')    { el.y = arred(el.y - passo); }
      else if (ev.key === 'ArrowDown')  { el.y = arred(el.y + passo); }
      else if (ev.key === 'Delete' || ev.key === 'Backspace') { self.remover(el.id); return; }
      else if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 'd') { ev.preventDefault(); self.duplicar(el.id); return; }
      else { usou = false; }
      if (usou) {
        ev.preventDefault();
        var no = self.no(el.id);
        if (no) { no.setAttribute('style', estiloDoElemento(el)); }
        self.montarInspetor();
        self.sincronizar();
      }
    });

    function campoInspetor(ev) {
      if (!self.sel) { return; }
      var alvo = ev.target;
      if (alvo.dataset.bd || alvo.dataset.bl) { self.aplicarCampoBloco(alvo); return; }
      if (alvo.dataset.campo) { self.aplicarCampo(alvo.dataset.campo, alvo); }
    }
    this.inspetor.addEventListener('input', campoInspetor);
    this.inspetor.addEventListener('change', campoInspetor);
    this.inspetor.addEventListener('click', function (ev) {
      var b = ev.target.closest('button');
      if (!b || !self.sel) { return; }
      var el = self.el(self.sel);

      if (b.dataset.blAdd) { self.listaBloco('add', b.dataset.blAdd); return; }
      if (b.dataset.blDel) { self.listaBloco('del', b.dataset.blDel, Number(b.dataset.bi)); return; }
      if (b.dataset.blMov) { self.listaBloco('mov', b.dataset.bl, Number(b.dataset.bi), b.dataset.blMov); return; }

      if (b.dataset.acao === 'mov-atual') {
        el.s.x2 = el.x; el.s.y2 = el.y; el.s.w2 = el.w; el.s.h2 = el.h;
        self.montarInspetor(); self.renderizar(); self._marcarSelecao(); self.sincronizar();
        return;
      }
      if (b.dataset.acao === 'mov-limpar') {
        delete el.s.x2; delete el.s.y2; delete el.s.w2; delete el.s.h2;
        self.montarInspetor(); self.renderizar(); self._marcarSelecao(); self.sincronizar();
        return;
      }
      if (b.dataset.acao === 'previa-img') {
        var noI = self.no(el.id);
        if (noI) {
          noI.classList.toggle('trc-img-destaque');
          b.querySelector('i').className = noI.classList.contains('trc-img-destaque')
            ? 'bi bi-arrow-counterclockwise' : 'bi bi-play-fill';
        }
        return;
      }
      if (b.dataset.acao === 'previa') {
        var noP = self.no(el.id);
        if (noP) {
          noP.classList.remove('trv-previa');
          void noP.offsetWidth;
          noP.classList.add('trv-previa');
        }
        return;
      }
      if (b.dataset.acao) {
        if (b.dataset.acao === 'excluir') { self.remover(el.id); }
        else if (b.dataset.acao === 'duplicar') { self.duplicar(el.id); }
        else { self.ordenar(el.id, b.dataset.acao === 'frente'); }
        return;
      }
      if (b.dataset.campo) {
        el.s = el.s || {};
        el.s[b.dataset.campo] = b.dataset.valor;
        self.renderizar();
        self.montarInspetor();
        self.sincronizar();
      }
      if (b.id === 'trcTrocarImg') { self.cfg.pedirImagem(el.id); }
      if (b.id === 'trcTrocarVid') { self.cfg.pedirVideo(el.id); }
    });

    var barra = $('trcFmt');
    if (barra) {
      barra.addEventListener('mousedown', function (ev) {
        if (ev.target.closest('button')) { ev.preventDefault(); }
      });
      barra.addEventListener('click', function (ev) {
        var b = ev.target.closest('button[data-fmt]');
        if (!b) { return; }
        self.formatar(b.dataset.fmt, b.dataset.valor || '');
      });
      barra.addEventListener('change', function (ev) {
        var alvo = ev.target.closest('[data-fmt]');
        if (!alvo || alvo.tagName === 'BUTTON') { return; }
        self.formatar(alvo.dataset.fmt, alvo.value);
        if (alvo.tagName === 'SELECT') { alvo.value = ''; }
      });
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-inserir]'), function (b) {
      b.addEventListener('click', function () {
        var chave = b.dataset.inserir;
        if (chave === 'imagem') { self.cfg.pedirImagem(null); }
        else if (chave === 'video') { self.cfg.pedirVideo(null); }
        else { self.inserir(chave); }
      });
    });

    var menu = $('trcMenuBlocos');
    var btnMenu = $('btnBlocos');
    if (menu && btnMenu && global.TrBlocos) {
      menu.innerHTML = global.TrBlocos.tipos().map(function (t) {
        return '<button type="button" class="trc-bloco-op" data-inserir-bloco="' + t.chave + '">' +
                 '<i class="bi ' + t.icone + '"></i>' +
                 '<span><b>' + t.rotulo + '</b><small>' + t.dica + '</small></span>' +
               '</button>';
      }).join('');
      btnMenu.addEventListener('click', function (ev) {
        ev.stopPropagation();
        menu.hidden = !menu.hidden;
        btnMenu.classList.toggle('is-ativo', !menu.hidden);
      });
      menu.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-inserir-bloco]');
        if (!b) { return; }
        self.inserirBloco(b.dataset.inserirBloco);
        menu.hidden = true;
        btnMenu.classList.remove('is-ativo');
      });
      document.addEventListener('click', function (ev) {
        if (menu.hidden) { return; }
        if (!ev.target.closest('#trcMenuBlocos') && !ev.target.closest('#btnBlocos')) {
          menu.hidden = true;
          btnMenu.classList.remove('is-ativo');
        }
      });
    }

    this.area.addEventListener('pointerdown', function (ev) {
      if (!ev.target.closest('.trc-el') && !ev.target.closest('#trcInspetor')) {
        self.encerrarEdicao();
        self.selecionar(null);
      }
    });
  };

  var MAPA_CAM = {
    full:    { alvo: 0.52, altura: 1.28, largura: 0.95 },
    joelhos: { alvo: 0.64, altura: 1.00, largura: 0.88 },
    mid:     { alvo: 0.74, altura: 0.82, largura: 0.80 },
    upper:   { alvo: 0.88, altura: 0.52, largura: 0.62 },
    busto:   { alvo: 0.93, altura: 0.40, largura: 0.50 },
    head:    { alvo: 0.99, altura: 0.28, largura: 0.30 }
  };

  var SILHUETA =
    'M50,13.5' +
    'C54.7,13.5 57.6,17.1 57.6,22.1' +
    'C57.6,26.4 55.7,29.6 53.5,30.7' +
    'L53.2,34.2' +
    'C61,35.2 65.4,38.4 67,43.6' +
    'L69.4,78.6' +
    'C69.8,82.6 64.6,83 64.2,79.2' +
    'L62.4,48' +
    'C61.4,54 61,61 61.6,67' +
    'C62,71 63,73 63.4,77' +
    'L60,98L58.6,121' +
    'C58.8,124 53.8,124.4 53.4,121.6' +
    'L52.4,98L50.6,75L49.4,75L47.6,98L46.6,121.6' +
    'C46.2,124.4 41.2,124 41.4,121' +
    'L40,98L36.6,77' +
    'C37,73 38,71 38.4,67' +
    'C39,61 38.6,54 37.6,48' +
    'L35.8,79.2' +
    'C35.4,83 30.2,82.6 30.6,78.6' +
    'L33,43.6' +
    'C34.6,38.4 39,35.2 46.8,34.2' +
    'L46.5,30.7' +
    'C44.3,29.6 42.4,26.4 42.4,22.1' +
    'C42.4,17.1 45.3,13.5 50,13.5Z';

  function atualizarGuiaInstrutor() {
    var guia = $('trcInstrGuia');
    var visivel = $('slideInstrutorVisivel');
    var pos = $('slidePosicao');
    var cam = $('slideCamera');
    var escala = $('slideEscala');
    if (!guia || !visivel) { return; }

    guia.hidden = !visivel.checked;
    guia.dataset.pos = (pos && pos.value) || 'direita';
    if (guia.hidden) { return; }

    var vista = (cam && cam.value) || 'herdar';
    if (vista === 'herdar') { vista = global.TR_CAMERA_PADRAO || 'upper'; }
    var v = MAPA_CAM[vista] || MAPA_CAM.upper;

    guia.style.setProperty('--trc-instr-escala', Number(escala && escala.value) || 1);

    var larguraPx = guia.clientWidth || 1;
    var alturaPx = guia.clientHeight || 1;
    var proporcao = larguraPx / alturaPx;

    var altVis = Math.max(v.altura, v.largura / proporcao) * 1.06;
    var larVis = altVis * proporcao;
    var topo = (1.25 - (v.alvo + altVis / 2)) * 100;

    var vb = (50 - larVis * 50) + ' ' + topo + ' ' + (larVis * 100) + ' ' + (altVis * 100);
    var rotulo = cam && cam.options[cam.selectedIndex] ? cam.options[cam.selectedIndex].text : 'instrutor';

    guia.innerHTML =
      '<svg viewBox="' + vb + '" preserveAspectRatio="xMidYMid slice" aria-hidden="true">' +
        '<rect class="trc-moldura" x="' + (50 - larVis * 50) + '" y="' + topo + '" width="' +
          (larVis * 100) + '" height="' + (altVis * 100) + '"></rect>' +
        '<path class="trc-sil" d="' + SILHUETA + '"></path>' +
      '</svg>' +
      '<span>' + rotulo + '</span>';
  }

  function ajustarPalco() {
    var holder = $('trcHolder');
    var stage = $('trcStage');
    if (!holder || !stage) { return; }
    var w = holder.clientWidth;
    var h = holder.clientHeight;
    if (w < 40) { return; }
    var largura = w;
    if (h > 40) { largura = Math.min(w, h * 16 / 9); }
    largura = Math.max(280, Math.floor(largura));
    stage.style.width = largura + 'px';
    stage.style.height = Math.round(largura * 9 / 16) + 'px';
    atualizarGuiaInstrutor();
  }

  function iniciar() {
    if (!$('trcPalco') || !$('slideConteudo')) { return; }

    var entradaImg = $('trcArquivoImagem');
    var alvoImagem = null;
    var midiaTipo = 'imagem';

    var canvas = new Canvas({
      base: global.TR_BASE || '',
      apresentacao: global.TR_APRESENTACAO_ID,
      pedirImagem: function (id) {
        midiaTipo = 'imagem';
        alvoImagem = id;
        entradaImg.accept = 'image/png,image/jpeg,image/webp,image/svg+xml';
        entradaImg.value = '';
        entradaImg.click();
      },
      pedirVideo: function (id) {
        midiaTipo = 'video';
        alvoImagem = id;
        entradaImg.accept = 'video/mp4,video/webm';
        entradaImg.value = '';
        entradaImg.click();
      }
    });

    entradaImg.addEventListener('change', function () {
      var arq = this.files && this.files[0];
      if (!arq) { return; }
      $('trcEnviando').hidden = false;

      global.trApi('midia_upload', {}, {
        apresentacao_id: String(global.TR_APRESENTACAO_ID),
        tipo: midiaTipo,
        arquivo: arq
      }).then(function (j) {
        $('trcEnviando').hidden = true;
        if (alvoImagem) {
          var el = canvas.el(alvoImagem);
          if (el) { el.src = j.arquivo; canvas.renderizar(); canvas.selecionar(el.id); canvas.sincronizar(true); }
        } else {
          canvas.inserir(midiaTipo, { src: j.arquivo });
        }
      }).catch(function (e) {
        $('trcEnviando').hidden = true;
        global.trToast(e.message || 'Falha no envio do arquivo.', 'erro');
      }).then(function () { entradaImg.value = ''; midiaTipo = 'imagem'; });
    });

    var btnHtml = $('trcVerHtml');
    if (btnHtml) {
      btnHtml.addEventListener('click', function () {
        var area = $('trcHtmlBox');
        area.hidden = !area.hidden;
        this.classList.toggle('is-ativo', !area.hidden);
        ajustarPalco();
      });
    }
    $('slideConteudo').addEventListener('input', function () { canvas._manual = true; });
    $('slideConteudo').addEventListener('blur', function () {
      if (canvas._manual) { canvas._manual = false; canvas.carregar(this.value); }
    });

    ['slideInstrutorVisivel', 'slidePosicao', 'slideCamera', 'slideEscala'].forEach(function (id) {
      var e = $(id);
      if (e) {
        e.addEventListener('change', atualizarGuiaInstrutor);
        e.addEventListener('input', atualizarGuiaInstrutor);
      }
    });
    document.addEventListener('tr:slide-carregado', atualizarGuiaInstrutor);
    document.addEventListener('tr:layout', function () { setTimeout(ajustarPalco, 60); });

    if (global.ResizeObserver) {
      new global.ResizeObserver(ajustarPalco).observe($('trcHolder'));
    }
    global.addEventListener('resize', ajustarPalco);
    ajustarPalco();

    global.TR_CANVAS = canvas;
    global.trCanvasCarregar = function (html) { canvas.carregar(html); ajustarPalco(); };
    global.trCanvasFinalizar = function () { canvas.encerrarEdicao(); };
    global.trCanvasInspetor = function () { canvas.montarInspetor(); };

    canvas.carregar($('slideConteudo').value || '');
    canvas.atualizarBarraFormato();
    ajustarPalco();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
  } else {
    iniciar();
  }
})(window);
