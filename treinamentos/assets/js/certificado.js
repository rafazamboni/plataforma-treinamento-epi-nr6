(function () {
  'use strict';

  var PASSO   = 0.02;
  var LEGIVEL = 0.62;
  var MINIMO  = 0.42;

  function num(v, padrao) {
    var n = parseFloat(v);
    return isFinite(n) ? n : padrao;
  }

  function cabe(folha) {
    return folha.scrollHeight <= folha.clientHeight + 1;
  }

  function restaurar(folha) {
    folha.style.removeProperty('--cert-dens');
    folha.style.removeProperty('--cert-espaco');
    folha.classList.remove('is-denso');
    folha.removeAttribute('data-ajuste');
    Array.prototype.forEach.call(folha.querySelectorAll('[data-oculto]'), function (el) {
      el.removeAttribute('data-oculto');
      el.style.display = '';
    });
    Array.prototype.forEach.call(folha.querySelectorAll('.cert-verso-mais'), function (el) {
      el.parentNode.removeChild(el);
    });
  }

  function reduzir(folha, dens, esp, de, ate) {
    for (var k = de; k >= ate - 1e-9; k -= PASSO) {
      folha.style.setProperty('--cert-dens', (dens * k).toFixed(3));
      folha.style.setProperty('--cert-espaco', (esp * k).toFixed(3));
      if (cabe(folha)) { return k; }
    }
    return 0;
  }

  function resumirTopicos(folha) {
    var lista = folha.querySelector('.cert-verso-lista');
    if (!lista) { return; }
    var itens = lista.querySelectorAll('.cert-verso-item');
    var mais  = document.createElement('div');
    mais.className = 'cert-verso-mais';
    lista.appendChild(mais);

    var ocultos = 0;
    for (var i = itens.length - 1; i > 0 && !cabe(folha); i--) {
      itens[i].setAttribute('data-oculto', '1');
      itens[i].style.display = 'none';
      ocultos++;
      mais.textContent = '… e mais ' + ocultos + (ocultos === 1 ? ' tópico' : ' tópicos');
    }
    if (!ocultos) { lista.removeChild(mais); }
  }

  function ajustar(folha) {
    if (!folha || folha.clientHeight === 0) { return; }

    restaurar(folha);
    if (cabe(folha)) { return; }

    var cs   = window.getComputedStyle(folha);
    var dens = num(cs.getPropertyValue('--cert-dens'), 1);
    var esp  = num(cs.getPropertyValue('--cert-espaco'), 1);

    var k = reduzir(folha, dens, esp, 1 - PASSO, LEGIVEL);

    if (!k && folha.classList.contains('cert-folha-verso')) {
      folha.classList.add('is-denso');
      k = reduzir(folha, dens, esp, LEGIVEL, MINIMO);
      if (!k) { resumirTopicos(folha); k = MINIMO; }
    } else if (!k) {
      k = reduzir(folha, dens, esp, LEGIVEL - PASSO, MINIMO) || MINIMO;
    }
    folha.setAttribute('data-ajuste', String(Math.round(k * 100)));
  }

  function ajustarTudo(raiz) {
    var folhas = (raiz || document).querySelectorAll('.cert-folha');
    Array.prototype.forEach.call(folhas, ajustar);
  }

  window.certAjustar = ajustarTudo;

  function iniciar() { ajustarTudo(); }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
  } else {
    iniciar();
  }
  if (document.fonts && document.fonts.ready) { document.fonts.ready.then(iniciar); }
  window.addEventListener('load', iniciar);
  window.addEventListener('beforeprint', iniciar);
})();
