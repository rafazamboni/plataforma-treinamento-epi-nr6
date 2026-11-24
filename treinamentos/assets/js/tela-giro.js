(function (global) {
  'use strict';

  var ORIENTACAO_EXIGIDA = 'paisagem';
  var LADO_MAXIMO = 860;

  var doc = global.document;
  var caixa = null;
  var bloqueado = false;
  var pronto = false;
  var pausamosAqui = false;

  function ehAparelhoPequeno() {
    var toque = ('ontouchstart' in global) ||
                (global.matchMedia && global.matchMedia('(pointer:coarse)').matches);
    if (!toque) { return false; }
    var t = global.screen || {};
    var menor = Math.min(t.width || 99999, t.height || 99999);
    return menor <= LADO_MAXIMO;
  }

  function orientacaoAtual() {
    var o = global.screen && global.screen.orientation;
    if (o && typeof o.type === 'string') {
      return o.type.indexOf('portrait') === 0 ? 'retrato' : 'paisagem';
    }
    if (global.matchMedia) {
      return global.matchMedia('(orientation:portrait)').matches ? 'retrato' : 'paisagem';
    }
    return (global.innerHeight > global.innerWidth) ? 'retrato' : 'paisagem';
  }

  function precisaGirar() {
    if (!ORIENTACAO_EXIGIDA) { return false; }
    if (!ehAparelhoPequeno()) { return false; }
    return orientacaoAtual() !== ORIENTACAO_EXIGIDA;
  }

  function montar() {
    if (caixa) { return caixa; }
    var deitado = (ORIENTACAO_EXIGIDA === 'paisagem');

    caixa = doc.createElement('div');
    caixa.className = 'nvg';
    caixa.id = 'nvGiro';
    caixa.hidden = true;
    caixa.setAttribute('role', 'alertdialog');
    caixa.setAttribute('aria-live', 'assertive');
    caixa.innerHTML =
      '<div class="nvg-caixa">' +
        '<div class="nvg-palco" aria-hidden="true">' +
          '<span class="nvg-seta"><i class="bi bi-arrow-clockwise"></i></span>' +
          '<span class="nvg-fone"><span class="nvg-tela"><i class="bi bi-play-fill"></i></span></span>' +
        '</div>' +
        '<h2>Gire o aparelho</h2>' +
        '<p>Este treinamento é apresentado em tela ' +
          (deitado ? 'deitada' : 'em pé') + '. Vire o aparelho para a posição ' +
          (deitado ? 'horizontal' : 'vertical') + ' para continuar.</p>' +
        '<small>A narração foi pausada e volta do mesmo ponto assim que a tela girar. ' +
          'Se a tela não girar, verifique o bloqueio de rotação do aparelho.</small>' +
      '</div>';
    doc.body.appendChild(caixa);
    return caixa;
  }

  function pausarNarracao() {
    var p = global.TR_PLAYER;
    if (!p || !p.motor) { return; }
    try {
      if (p.emReproducao && p.motor.rodando && !p.motor.pausado) {
        p.motor.pausar();
        pausamosAqui = true;
        if (p._atualizarControles) { p._atualizarControles(); }
      }
    } catch (e) {  }
  }

  function avisarRetomada() {
    if (!pausamosAqui) { return; }
    pausamosAqui = false;
    try { doc.dispatchEvent(new CustomEvent('nv:estado')); } catch (e) {}
  }

  function anunciar() {
    try {
      doc.dispatchEvent(new CustomEvent('nv:giro', { detail: { bloqueado: bloqueado } }));
    } catch (e) {}
  }

  function bloquear() {
    if (bloqueado) { return; }
    bloqueado = true;
    montar().hidden = false;
    doc.body.classList.add('nvg-bloqueado');
    pausarNarracao();
    anunciar();
  }

  function liberar() {
    if (!bloqueado) { return; }
    bloqueado = false;
    if (caixa) { caixa.hidden = true; }
    doc.body.classList.remove('nvg-bloqueado');
    try { global.dispatchEvent(new Event('resize')); } catch (e) {}
    avisarRetomada();
    anunciar();
  }

  var atrasado = null;
  function avaliar() {
    clearTimeout(atrasado);
    atrasado = setTimeout(function () {
      if (precisaGirar()) { bloquear(); } else { liberar(); }
    }, 120);
  }

  function comecar() {
    if (!ORIENTACAO_EXIGIDA) { pronto = true; anunciar(); return; }
    if (precisaGirar()) { bloquear(); }
    pronto = true;
    anunciar();

    global.addEventListener('resize', avaliar, { passive: true });
    global.addEventListener('orientationchange', avaliar, { passive: true });
    if (global.screen && global.screen.orientation &&
        global.screen.orientation.addEventListener) {
      global.screen.orientation.addEventListener('change', avaliar);
    }
    doc.addEventListener('visibilitychange', function () {
      if (!doc.hidden) { avaliar(); }
    });
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', comecar);
  } else {
    comecar();
  }

  global.TrTelaGiro = {
    bloqueado: function () { return bloqueado; },
    pronto: function () { return pronto; },
    reavaliar: avaliar
  };
})(window);
