(function () {
  'use strict';

  var aberto = null;

  function fechar() {
    if (!aberto) { return; }
    aberto.classList.remove('is-aberto');
    var lista = aberto.querySelector('.trh-menu-lista');
    if (lista) { lista.removeAttribute('style'); }
    var botao = aberto.querySelector('.trh-menu-btn');
    if (botao) { botao.setAttribute('aria-expanded', 'false'); }
    var card = aberto.closest('.trh-card');
    if (card) { card.classList.remove('is-menu-aberto'); }
    aberto = null;
  }

  function abrir(menu) {
    var lista = menu.querySelector('.trh-menu-lista');
    var botao = menu.querySelector('.trh-menu-btn');
    if (!lista || !botao) { return; }

    menu.classList.add('is-aberto');
    botao.setAttribute('aria-expanded', 'true');
    aberto = menu;

    var card = menu.closest('.trh-card');
    if (card) { card.classList.add('is-menu-aberto'); }

    var r = botao.getBoundingClientRect();
    lista.style.visibility = 'hidden';
    var alt = lista.offsetHeight;
    lista.style.visibility = '';

    if (r.bottom + alt + 12 > window.innerHeight) {
      lista.style.top = 'auto';
      lista.style.bottom = 'calc(100% + 5px)';
    }
  }

  document.addEventListener('click', function (ev) {
    var botao = ev.target.closest('.trh-menu-btn');

    if (botao) {
      ev.preventDefault();
      ev.stopPropagation();
      var menu = botao.closest('.trh-menu');
      var estava = menu.classList.contains('is-aberto');
      fechar();
      if (!estava) { abrir(menu); }
      return;
    }

    if (ev.target.closest('.trh-menu-lista')) {
      setTimeout(fechar, 0);
      return;
    }

    fechar();
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') { fechar(); }
  });

  window.addEventListener('resize', fechar);
  window.addEventListener('scroll', fechar, true);

  window.trListaAtualizou = function () {
    var grade = document.querySelector('.trh-grade');
    if (!grade) { return; }
    var cards = grade.querySelectorAll('.trh-card');

    var contador = document.querySelector('[data-tr-total]');
    if (contador) {
      contador.textContent = cards.length === 1 ? '1 treinamento'
                                                : cards.length + ' treinamentos';
    }
    if (cards.length) { return; }

    var vazio = document.getElementById('trhVazioApos');
    if (vazio) {
      grade.remove();
      vazio.hidden = false;
    }
  };
})();
