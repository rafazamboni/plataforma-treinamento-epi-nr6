(function () {
  'use strict';

  var overlay = document.getElementById('trAbertura');
  if (!overlay) { return; }

  var DURACAO_MS   = 3000;
  var MINIMO_MS    = 2200;
  var ESPERA_FONTE = 700;
  var ESPERA_CALMA = 1500;
  var QUADRO_OK    = 26;
  var QUADROS_BONS = 3;

  var inicio  = 0;
  var comecou = false;
  var jaSaiu  = false;

  function agora() {
    return (window.performance && performance.now) ? performance.now() : Date.now();
  }

  function remover() {
    if (overlay && overlay.parentNode) {
      overlay.parentNode.removeChild(overlay);
    }
    document.removeEventListener('keydown', aoTeclar);
  }

  function sair() {
    if (jaSaiu) { return; }
    jaSaiu = true;
    overlay.classList.add('tr-abertura--sair');
    overlay.setAttribute('aria-hidden', 'true');
    overlay.addEventListener('transitionend', remover, { once: true });
    window.setTimeout(remover, 700);
  }

  function aoTeclar(ev) {
    if (ev.key === 'Escape' || ev.key === 'Enter' || ev.key === ' ') {
      sair();
    }
  }

  function comecar() {
    if (comecou || jaSaiu) { return; }
    comecou = true;
    overlay.classList.add('is-pronta');
    var restante = Math.max(MINIMO_MS, DURACAO_MS - (agora() - (inicio || agora())));
    window.setTimeout(sair, restante);
  }

  function esperarFontes(cb) {
    var feito = false;
    function ok() { if (!feito) { feito = true; cb(); } }
    window.setTimeout(ok, ESPERA_FONTE);
    if (document.fonts && document.fonts.ready && document.fonts.ready.then) {
      document.fonts.ready.then(ok, ok);
    } else {
      ok();
    }
  }

  function esperarQuadroLivre(cb) {
    if (!window.requestAnimationFrame) { cb(); return; }
    var partida = 0, anterior = 0, bons = 0;
    requestAnimationFrame(function passo(t) {
      if (!partida) {
        partida = anterior = t;
        requestAnimationFrame(passo);
        return;
      }
      var dt = t - anterior;
      anterior = t;
      bons = (dt <= QUADRO_OK) ? bons + 1 : 0;
      if (bons >= QUADROS_BONS || (t - partida) >= ESPERA_CALMA) { cb(); return; }
      requestAnimationFrame(passo);
    });
  }

  var btnPular = document.getElementById('trAberturaPular');
  if (btnPular) {
    btnPular.addEventListener('click', function (ev) {
      ev.stopPropagation();
      sair();
    });
  }

  overlay.addEventListener('click', sair);
  document.addEventListener('keydown', aoTeclar);

  function travadoNoGiro() {
    if (document.body && document.body.classList.contains('nvg-bloqueado')) { return true; }
    var t = window.TrTelaGiro;
    if (!t) { return true; }
    if (t.pronto && !t.pronto()) { return true; }
    return !!(t.bloqueado && t.bloqueado());
  }

  function esperarOrientacao(cb) {
    var feito = false;
    var relogio = null;
    var limite = agora() + 2500;

    function ok() {
      if (feito) { return; }
      feito = true;
      document.removeEventListener('nv:giro', verificar);
      clearInterval(relogio);
      cb();
    }
    function verificar() {
      if (!travadoNoGiro()) { ok(); return; }
      if (!window.TrTelaGiro && agora() > limite) { ok(); }
    }

    document.addEventListener('nv:giro', verificar);
    relogio = setInterval(verificar, 250);
    verificar();
  }

  function quandoDomPronto(cb) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', cb, { once: true });
    } else {
      cb();
    }
  }

  quandoDomPronto(function () {
    esperarOrientacao(function () {
      inicio = agora();
      esperarFontes(function () { esperarQuadroLivre(comecar); });
    });
  });
})();
