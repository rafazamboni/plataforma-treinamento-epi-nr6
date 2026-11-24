(function () {
  'use strict';

  var texto = document.getElementById('nvLegendaTexto');
  if (!texto) { return; }

  var corpo = texto.parentNode;
  if (!corpo) { return; }

  var caixa = document.getElementById('nvLegenda');

  var ultimoTexto = '';
  var linhaAtual = -1;

  function palavraAtual() {
    var ons = texto.querySelectorAll('.p.on');
    return ons.length ? ons[ons.length - 1] : null;
  }

  function topoDaLinha(p) {
    return p.offsetTop - texto.offsetTop;
  }

  function alturaLinha() {
    var h = parseFloat(getComputedStyle(corpo).lineHeight);
    return (h && h > 0) ? h : corpo.clientHeight || 1;
  }

  function acompanhar() {
    if (caixa && caixa.classList.contains('is-falando')) {
      caixa.classList.remove('is-encerrada');
    }

    var vazia = !texto.textContent.trim();
    if (caixa) { caixa.classList.toggle('is-vazia', vazia); }
    corpo.classList.toggle('is-vazia', vazia);
    if (vazia) { ultimoTexto = ''; linhaAtual = -1; corpo.scrollTop = 0; return; }

    if (texto.textContent !== ultimoTexto) {
      ultimoTexto = texto.textContent;
      linhaAtual = -1;
      corpo.scrollTop = 0;
    }

    var p = palavraAtual();
    if (!p) { return; }

    var linha = Math.round(topoDaLinha(p) / alturaLinha());
    if (linha === linhaAtual) { return; }

    linhaAtual = linha;
    corpo.scrollTop = linha * alturaLinha();
  }

  var rodando = true;
  function laco() {
    if (!rodando) { return; }
    acompanhar();
    requestAnimationFrame(laco);
  }
  requestAnimationFrame(laco);

  document.addEventListener('visibilitychange', function () {
    rodando = document.visibilityState === 'visible';
    if (rodando) { requestAnimationFrame(laco); }
  });

  window.addEventListener('resize', function () {
    linhaAtual = -1;
  });
})();
