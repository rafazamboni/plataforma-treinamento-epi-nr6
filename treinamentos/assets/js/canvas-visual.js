(function (global) {
  'use strict';

  var VISUAIS = {
    '':          'Sem moldura',
    'plano':     'Plano',
    'papel':     'Papel',
    'elevado':   'Elevado',
    'vidro':     'Vidro',
    'contorno':  'Contorno',
    'suave':     'Suave (cor de acento)',
    'gradiente': 'Gradiente',
    'faixa':     'Faixa lateral',
    'topo':      'Faixa superior',
    'neon':      'Neon'
  };

  var ANIMACOES = {
    '':          'Nenhuma',
    'surgir':    'Surgir',
    'subir':     'Subir',
    'descer':    'Descer',
    'esquerda':  'Entrar pela esquerda',
    'direita':   'Entrar pela direita',
    'zoom':      'Aproximar',
    'brotar':    'Brotar',
    'girar':     'Girar',
    'desfocar':  'Sair do desfoque',
    'flutuar':   'Flutuar (contínuo)',
    'pulsar':    'Pulsar (contínuo)'
  };

  var HOVER = {
    '':          'Nenhum',
    'elevar':    'Elevar',
    'crescer':   'Crescer',
    'borda':     'Contorno de destaque',
    'luz':       'Brilho passante',
    'inclinar':  'Inclinar',
    'afundar':   'Afundar'
  };

  var CONTINUAS = { flutuar: 1, pulsar: 1 };

  function r2(n) { return Math.round(n * 100) / 100; }
  function num(v, p) { var n = parseFloat(v); return isNaN(n) ? p : n; }

  function corComOpacidade(hex, op) {
    var h = String(hex || '').trim().replace(/^#/, '');
    if (h.length === 3) { h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2]; }
    if (!/^[0-9a-fA-F]{6}$/.test(h)) { return hex; }
    var n = parseInt(h, 16);
    return 'rgba(' + ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' +
           (Math.max(0, Math.min(100, num(op, 100))) / 100) + ')';
  }

  function padding(el, fs) {
    var pad = num((el.s || {}).pad, 0);
    if (!(pad > 0)) { return ''; }
    var base = 1.05 * fs;
    var padEm = pad / base;
    var largEm = (num(el.w, 20)) / base;
    var altEm = (num(el.h, 10)) * 0.5625 / base;
    var py = Math.max(0.15, Math.min(padEm, altEm * 0.20));
    var px = Math.max(0.15, Math.min(padEm, largEm * 0.14));
    return 'padding:' + r2(py) + 'em ' + r2(px) + 'em;';
  }

  function estilo(el) {
    var s = el.s || {};
    var fs = num(s.fs, 100) / 100;
    var vis = s.vis || '';
    var op = s.op == null ? 100 : num(s.op, 100);

    var css = 'left:' + el.x + '%;top:' + el.y + '%;width:' + el.w + '%;height:' + el.h + '%;';
    css += 'z-index:' + (el.z || 1) + ';';

    if (s.bg) {
      var fundo = op < 100 ? corComOpacidade(s.bg, op) : s.bg;
      css += (vis ? '--trv-bg:' : 'background:') + fundo + ';';
    } else if (op < 100) {
      css += 'opacity:' + (op / 100) + ';';
    }

    if (s.cor) { css += 'color:' + s.cor + ';'; }
    if (s.fs && Number(s.fs) !== 100) { css += '--trc-fs:' + fs + ';'; }
    if (s.ta) { css += 'text-align:' + s.ta + ';'; }
    if (s.va) {
      css += 'justify-content:' +
             (s.va === 'center' ? 'center' : (s.va === 'end' ? 'flex-end' : 'flex-start')) + ';';
    }

    css += padding(el, fs);

    if (s.br != null && s.br !== '') {
      var br = num(s.br, 0);
      css += 'border-radius:' + br + 'px;';
      if (br > 0) { css += 'border-radius:' + r2(br / 8.4) + 'cqw;'; }
    }
    if (s.bcor) { css += 'border:2px solid ' + s.bcor + ';'; }

    var sh = num(s.sh, 0);
    if (vis) {
      css += '--trv-elev:' + (s.sh == null ? 2 : sh) + ';';
    } else if (sh > 0) {
      css += 'box-shadow:0 ' + r2(sh * 1.2) + 'px ' + r2(sh * 3) + 'px rgba(15,32,48,.06),' +
             '0 ' + r2(sh * 5) + 'px ' + r2(sh * 15) + 'px rgba(15,32,48,.' + (8 + sh * 4) + ');';
    }

    var temMov = ['x2', 'y2', 'w2', 'h2'].some(function (k) { return s[k] != null; });
    var giroBase = s.rot ? ' rotate(' + s.rot + 'deg)' : '';

    if (temMov && num(el.w, 0) > 0 && num(el.h, 0) > 0) {
      var mx = num(s.x2, el.x), my = num(s.y2, el.y);
      var mw = num(s.w2, el.w), mh = num(s.h2, el.h);
      css += 'transform-origin:0 0;';
      css += '--trc-base:' + (giroBase ? giroBase.trim() : 'none') + ';';
      css += '--trc-mov:translate(' + r2((mx - el.x) / el.w * 100) + '%,'
                                    + r2((my - el.y) / el.h * 100) + '%)'
           + ' scale(' + r2(mw / el.w) + ',' + r2(mh / el.h) + ')' + giroBase + ';';
    } else if (s.rot) {
      css += 'transform:rotate(' + s.rot + 'deg);';
    }

    if (el.t === 'imagem') {
      css += '--trc-img-zoom:' + r2(num(s.zoom, 1)) + ';';
      css += '--trc-img-giro:' + (num(s.giro, 0)) + 'deg;';
      css += '--trc-img-x:' + r2(num(s.focoX, 50)) + '%;';
      css += '--trc-img-y:' + r2(num(s.focoY, 50)) + '%;';
      css += '--trc-img-zoom2:' + r2(num(s.zoom2, num(s.zoom, 1))) + ';';
      css += '--trc-img-giro2:' + (num(s.giro2, num(s.giro, 0))) + 'deg;';
      css += '--trc-img-x2:' + r2(num(s.focoX2, num(s.focoX, 50))) + '%;';
      css += '--trc-img-y2:' + r2(num(s.focoY2, num(s.focoY, 50))) + '%;';
      css += '--trc-img-dur:' + r2(num(s.imgDur, 1.1)) + 's;';
    }
    if (num(s.animd, 0) > 0) { css += '--trv-atraso:' + r2(num(s.animd, 0)) + 's;'; }
    return css;
  }

  function seguro(v) { return String(v || '').replace(/[^a-zA-Z0-9_-]/g, ''); }

  function classes(el) {
    var s = el.s || {};
    var out = [];
    if (s.vis && VISUAIS[s.vis] !== undefined) { out.push('trv-vis-' + seguro(s.vis)); }
    if (s.anim && ANIMACOES[s.anim] !== undefined) { out.push('trv-anim-' + seguro(s.anim)); }
    if (s.hov && HOVER[s.hov] !== undefined) { out.push('trv-hov-' + seguro(s.hov)); }
    if (s.anim && CONTINUAS[s.anim]) { out.push('trv-continua'); }
    if (['x2', 'y2', 'w2', 'h2'].some(function (k) { return s[k] != null; })) {
      out.push('trc-movel');
    }
    return out.join(' ');
  }

  function atributos(el) {
    var s = el.s || {};
    var out = '';
    var q = (num(s.giro, 0) % 180) !== 0 || (num(s.giro2, 0) % 180) !== 0;
    if (el.t === 'imagem' && q) { out += ' data-giro-quarto="1"'; }
    if (num(s.voltaSeg, 0) > 0) { out += ' data-volta="' + r2(num(s.voltaSeg, 0)) + '"'; }

    if (el.t === 'video') {
      out += ' data-vinicio="' + seguro(s.vInicio || 'auto') + '"';
      out += ' data-vvel="' + r2(num(s.vVel, 1)) + '"';
      if (num(s.vIni, 0) > 0) { out += ' data-vseg="' + r2(num(s.vIni, 0)) + '"'; }
    }
    return out;
  }

  function classeCompleta(el, marcaOculto) {
    var c = 'trc-el trc-' + seguro(el.t || 'texto');
    if (el.oculto) { c += ' ' + (marcaOculto || 'nv-oculto'); }
    if (el.classe) { c += ' ' + String(el.classe).replace(/[^a-zA-Z0-9 _-]/g, ''); }
    var extra = classes(el);
    if (extra) { c += ' ' + extra; }
    return c;
  }

  function aplicar(raiz) {
    var alvo = raiz || document;
    if (!alvo.querySelectorAll) { return; }
    var palcos = alvo.querySelectorAll('.trc-palco');
    Array.prototype.forEach.call(palcos, function (palco) {
      var pai = palco.parentNode;
      var script = pai ? pai.querySelector('script[data-tr-canvas]') : null;
      if (!script) { return; }
      var dados;
      try { dados = JSON.parse(script.textContent || '{}'); } catch (e) { return; }
      if (!dados || !dados.els || !dados.els.length) { return; }

      dados.els.forEach(function (el) {
        if (!el || !el.id) { return; }
        var no = palco.querySelector('.trc-el[id="' + seguro(el.id) + '"]');
        if (!no) { return; }
        no.setAttribute('style', estilo(el));
        no.className = classeCompleta(el);
        no.removeAttribute('data-giro-quarto');
        no.removeAttribute('data-volta');
        var extras = atributos(el);
        if (extras.indexOf('data-giro-quarto') >= 0) { no.setAttribute('data-giro-quarto', '1'); }
        var mv = extras.match(/data-volta="([\d.]+)"/);
        if (mv) { no.setAttribute('data-volta', mv[1]); }
      });
    });
  }

  global.TrCanvasVisual = {
    VISUAIS: VISUAIS,
    ANIMACOES: ANIMACOES,
    HOVER: HOVER,
    CONTINUAS: CONTINUAS,
    estilo: estilo,
    classes: classes,
    classeCompleta: classeCompleta,
    atributos: atributos,
    corComOpacidade: corComOpacidade,
    aplicar: aplicar
  };
})(window);
