(function (global) {
  'use strict';

  var doc = global.document;
  var player = global.TR_PLAYER;
  if (!player || !player.motor) { return; }

  var motor = player.motor;
  var proximoOriginal = motor.proximo;
  var esperando = null;
  var pendente = false;
  var ignorar = false;

  var ITENS = {
    abas:       '.trb-aba',
    revelar:    '.trb-card',
    checklist:  '.trb-check',
    passos:     '.trb-passo',
    linhaTempo: '.trb-marco'
  };

  var DICA = {
    abas:       'Abrir',
    revelar:    'Abrir',
    checklist:  'Marque este item',
    passos:     'Abra esta etapa',
    linhaTempo: 'Abrir'
  };

  function itensDe(bloco) {
    var sel = ITENS[bloco.getAttribute('data-bloco')];
    return sel ? Array.prototype.slice.call(bloco.querySelectorAll(sel)) : [];
  }

  function blocosDoSlide() {
    var el = player.elsSlides && player.elsSlides[player.indice];
    if (!el) { return []; }
    return Array.prototype.slice.call(el.querySelectorAll('.trb[data-guiado="1"]'));
  }

  function preparar(bloco) {
    if (bloco.__trgPronto) { return; }
    bloco.__trgPronto = true;
    itensDe(bloco).forEach(function (it) {
      if (!it.classList.contains('trg-ok')) { it.classList.add('trg-travado'); }
    });
  }

  function dentroDoItem(item, x, y) {
    var r = item.getBoundingClientRect();
    if (!r.width || !r.height) { return false; }
    return x >= r.left && x <= r.right && y >= r.top && y <= r.bottom;
  }

  doc.addEventListener('click', function (ev) {
    if (!esperando) { return; }
    var item = esperando.item;
    if (ev.target.closest && ev.target.closest('.trg-vez') === item) {
      concluirItem(item, false);
      return;
    }
    if (dentroDoItem(item, ev.clientX, ev.clientY)) { concluirItem(item, true); }
  }, true);

  doc.addEventListener('keydown', function (ev) {
    if (!esperando || (ev.key !== 'Enter' && ev.key !== ' ')) { return; }
    if (!ev.target.closest || ev.target.closest('.trg-vez') !== esperando.item) { return; }
    ev.preventDefault();
    concluirItem(esperando.item, true);
  }, true);

  function prepararSlide() {
    blocosDoSlide().forEach(preparar);
  }

  function pedidoApos(n) {
    var achado = null;
    blocosDoSlide().some(function (bloco) {
      return itensDe(bloco).some(function (it, k) {
        if (it.classList.contains('trg-ok')) { return false; }
        if (Number(it.getAttribute('data-gf') || 0) !== n) { return false; }
        achado = { bloco: bloco, item: it, k: k, f: n };
        return true;
      });
    });
    return achado;
  }

  function segurar(p) {
    esperando = p;
    preparar(p.bloco);

    p.item.classList.remove('trg-travado');
    p.item.classList.add('trg-vez');
    if (p.bloco.getAttribute('data-gauto') !== '1' && !p.item.getAttribute('data-gdica')) {
      p.item.setAttribute('data-gdica', DICA[p.bloco.getAttribute('data-bloco')] || 'Toque aqui');
    }
    doc.body.classList.add('trg-esperando');
    var caixa = p.bloco.closest && p.bloco.closest('.trc-el');
    if (caixa) { caixa.classList.add('trg-foco'); }

    try { p.item.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); } catch (e) {}
    try { doc.dispatchEvent(new CustomEvent('nv:estado')); } catch (e2) {}

    if (global.TrEtapa && global.TrEtapa.atualizar) { global.TrEtapa.atualizar(); }
  }

  function abrirSozinho(p) {
    preparar(p.bloco);

    var grupo = itensDe(p.bloco).filter(function (it, k) {
      return !it.classList.contains('trg-ok') &&
             Number(it.getAttribute('data-gf') || 0) === p.f;
    });
    if (!grupo.length) { grupo = [p.item]; }

    esperando = p;
    grupo.forEach(function (it, k) {
      setTimeout(function () {
        if (esperando !== p) { return; }
        it.classList.remove('trg-travado');
        it.classList.add('trg-vez');
        it.removeAttribute('data-gdica');
        if (global.TrEtapa && global.TrEtapa.atualizar) { global.TrEtapa.atualizar(); }
        setTimeout(function () {
          if (esperando !== p) { return; }
          if (k < grupo.length - 1) {
            it.classList.remove('trg-vez');
            it.classList.add('trg-ok');
            if (acionavel(it)) { try { it.click(); } catch (e) {} }
            avancarLinha(p.bloco);
          } else {
            concluirItem(it, acionavel(it));
          }
        }, 340);
      }, k * 520);
    });
  }

  function acionavel(item) {
    return !!(item.getAttribute('data-bloco-ac') || item.classList.contains('trb-aba'));
  }

  function concluirItem(item, acionar) {
    var p = esperando;
    esperando = null;

    if (acionar) { try { item.click(); } catch (e) {} }

    item.classList.remove('trg-vez', 'trg-travado');
    item.classList.add('trg-ok');
    doc.body.classList.remove('trg-esperando');
    limparFoco();

    avancarLinha(p && p.bloco);
    if (global.TrEtapa && global.TrEtapa.atualizar) { global.TrEtapa.atualizar(); }

    if (motor.pausado || !motor.rodando) { pendente = true; return; }
    setTimeout(function () {
      if (esperando || !motor.rodando) { return; }
      if (motor.pausado) { pendente = true; return; }
      proximoOriginal.call(motor);
    }, 620);
  }

  function limparFoco() {
    Array.prototype.forEach.call(doc.querySelectorAll('.trg-foco'), function (n) {
      n.classList.remove('trg-foco');
    });
  }

  function avancarLinha(bloco) {
    if (!bloco || bloco.getAttribute('data-bloco') !== 'linhaTempo') { return; }
    var lista = bloco.querySelector('ul');
    if (!lista) { return; }
    var abertos = itensDe(bloco).filter(function (it) { return it.classList.contains('trg-ok'); });
    if (!abertos.length) { return; }
    var ru = lista.getBoundingClientRect();
    var rm = abertos[abertos.length - 1].getBoundingClientRect();
    var vertical = bloco.classList.contains('trb-lt-v');
    var total = vertical ? ru.height : ru.width;
    if (!total) { return; }
    var pct = vertical
      ? ((rm.top + rm.height) - ru.top) / total * 100
      : ((rm.left + rm.width) - ru.left) / total * 100;
    bloco.style.setProperty('--trg-prog', Math.max(0, Math.min(100, pct)).toFixed(1) + '%');
  }

  function destravarRestante() {
    blocosDoSlide().forEach(function (bloco) {
      preparar(bloco);
      itensDe(bloco).forEach(function (it) {
        it.classList.remove('trg-travado', 'trg-vez');
        it.classList.add('trg-ok');
      });
      avancarLinha(bloco);
    });
  }

  motor.proximo = function () {
    if (esperando) { return; }

    if (!ignorar) {
      var p = pedidoApos(motor.indice + 1);
      if (p) {
        if (p.bloco.getAttribute('data-gauto') === '1') { abrirSozinho(p); }
        else { segurar(p); }
        return;
      }
      if (motor.indice + 1 >= motor.lista.length) { destravarRestante(); }
    }
    return proximoOriginal.call(motor);
  };

  ['iniciar', 'irPara', 'repetir'].forEach(function (nome) {
    var original = motor[nome];
    if (typeof original !== 'function') { return; }
    motor[nome] = function () {
      ignorar = true;
      esperando = null;
      doc.body.classList.remove('trg-esperando');
      try { return original.apply(this, arguments); }
      finally { ignorar = false; }
    };
  });

  var retomarOriginal = motor.retomar;
  motor.retomar = function () {
    var r = retomarOriginal.apply(this, arguments);
    if (pendente && !esperando) {
      pendente = false;
      setTimeout(function () {
        if (!esperando && motor.rodando && !motor.pausado) { proximoOriginal.call(motor); }
      }, 200);
    }
    return r;
  };

  var pararOriginal = motor.parar;
  motor.parar = function () {
    esperando = null;
    pendente = false;
    doc.body.classList.remove('trg-esperando');
    limparFoco();
    return pararOriginal.apply(this, arguments);
  };

  var irParaOriginal = player.irPara;
  player.irPara = function () {
    esperando = null;
    pendente = false;
    doc.body.classList.remove('trg-esperando');
    limparFoco();
    var r = irParaOriginal.apply(this, arguments);
    prepararSlide();
    return r;
  };

  prepararSlide();

  global.TrGuia = {
    esperando: function () { return !!esperando; },
    destravar: destravarRestante
  };
})(window);
