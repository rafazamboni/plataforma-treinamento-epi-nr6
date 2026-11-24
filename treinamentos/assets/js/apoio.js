(function (global) {
  'use strict';

  var A = global.TR_APOIO || null;
  if (!A) { return; }

  var MATS = (A.materiais || []);
  var REFS = (A.referencias || []);

  function $(id) { return document.getElementById(id); }

  var btn = $('btnApoio');
  var drawer = $('drawerApoio');

  if (!MATS.length) {
    if (btn) { btn.remove(); btn = null; }
    if (drawer) { drawer.remove(); drawer = null; }
  } else if (btn) {
    var flut = document.querySelector('.trt-flutuante');
    var roteiro = $('btnRoteiro');
    if (flut) {
      if (roteiro && roteiro.parentNode === flut) { flut.insertBefore(btn, roteiro); }
      else { flut.appendChild(btn); }
    }
    btn.hidden = false;
  }

  function montarMateriais() {
    var alvo = $('nvApoioLista');
    if (!alvo || !MATS.length) { return; }

    alvo.innerHTML = '';

    var intro = document.createElement('p');
    intro.className = 'nva-intro';
    intro.textContent = MATS.length === 1
      ? 'Documento complementar deste treinamento. Pode ser consultado a qualquer momento.'
      : 'Documentos complementares deste treinamento. Podem ser consultados a qualquer momento.';
    alvo.appendChild(intro);

    MATS.forEach(function (m) {
      var a = document.createElement('a');
      a.className = 'nva-item' + (m.destaque ? ' is-destaque' : '');
      a.href = m.url;
      a.target = '_blank';
      a.rel = 'noopener';

      a.innerHTML =
        '<span class="nva-icone"><i class="bi bi-' + (m.icone || 'file-earmark') + '"></i></span>' +
        '<span class="nva-corpo">' +
          '<b></b>' +
          '<small class="nva-desc"></small>' +
          '<span class="nva-meta"></span>' +
        '</span>' +
        '<i class="bi bi-box-arrow-up-right nva-seta"></i>';

      a.querySelector('b').textContent = m.titulo || 'Documento';

      var desc = a.querySelector('.nva-desc');
      if (m.descricao) { desc.textContent = m.descricao; } else { desc.remove(); }

      var meta = [];
      if (m.formato) { meta.push(m.formato); }
      if (m.tamanho) { meta.push(m.tamanho); }
      a.querySelector('.nva-meta').textContent = meta.join(' · ');

      alvo.appendChild(a);

      if (m.baixar) {
        var d = document.createElement('a');
        d.className = 'nva-baixar';
        d.href = m.baixar;
        d.innerHTML = '<i class="bi bi-download"></i> Baixar arquivo';
        alvo.appendChild(d);
      }
    });
  }

  function fechar() {
    if (!drawer) { return; }
    if (drawer.contains(document.activeElement)) { document.activeElement.blur(); }
    drawer.classList.remove('is-aberto');
    drawer.setAttribute('aria-hidden', 'true');
    var fundo = $('nvBackdrop');
    if (fundo && !document.querySelector('.nv-drawer.is-aberto')) {
      fundo.classList.remove('is-ativo');
    }
  }

  function abrir() {
    if (!drawer) { return; }
    var rot = $('drawerRoteiro');
    if (rot) { rot.classList.remove('is-aberto'); rot.setAttribute('aria-hidden', 'true'); }

    drawer.classList.add('is-aberto');
    drawer.setAttribute('aria-hidden', 'false');
    var fundo = $('nvBackdrop');
    if (fundo) { fundo.classList.add('is-ativo'); }
  }

  if (btn && drawer) {
    btn.addEventListener('click', function (ev) {
      ev.preventDefault();
      if (drawer.classList.contains('is-aberto')) { fechar(); } else { abrir(); }
    });

    var fundoEl = $('nvBackdrop');
    if (fundoEl) { fundoEl.addEventListener('click', fechar); }

    Array.prototype.forEach.call(
      drawer.querySelectorAll('[data-drawer-close]'),
      function (b) { b.addEventListener('click', fechar); }
    );

    var btnR = $('btnRoteiro');
    if (btnR) { btnR.addEventListener('click', fechar); }

    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && drawer.classList.contains('is-aberto')) { fechar(); }
    });
  }

  function montarReferencias() {
    var caixa = $('nvReferencias');
    if (!caixa) { return; }

    if (!REFS.length) { caixa.remove(); return; }

    caixa.hidden = false;
    caixa.innerHTML =
      '<button type="button" class="nvr-toggle" aria-expanded="false" aria-controls="nvReferenciasLista">' +
        '<i class="bi bi-journal-bookmark nvr-marca"></i>' +
        '<span class="nvr-rotulo">Referências</span>' +
        '<em class="nvr-qtd"></em>' +
        '<i class="bi bi-chevron-down nvr-seta"></i>' +
      '</button>' +
      '<ol class="nvr-lista" id="nvReferenciasLista" hidden></ol>';

    caixa.querySelector('.nvr-qtd').textContent = String(REFS.length);

    var lista = caixa.querySelector('.nvr-lista');
    REFS.forEach(function (r) {
      var li = document.createElement('li');
      li.className = 'nvr-item';

      if (r.url) {
        var a = document.createElement('a');
        a.href = r.url;
        a.target = '_blank';
        a.rel = 'noopener';
        a.className = 'nvr-link';
        a.textContent = r.texto || r.url;
        var ic = document.createElement('i');
        ic.className = 'bi bi-box-arrow-up-right';
        a.appendChild(document.createTextNode(' '));
        a.appendChild(ic);
        li.appendChild(a);
      } else {
        li.textContent = r.texto || '';
      }

      lista.appendChild(li);
    });

    var alterna = caixa.querySelector('.nvr-toggle');
    alterna.addEventListener('click', function () {
      var aberto = !lista.hidden;
      lista.hidden = aberto;
      alterna.setAttribute('aria-expanded', aberto ? 'false' : 'true');
      caixa.classList.toggle('is-aberto', !aberto);
    });
  }

  function iniciar() {
    montarMateriais();
    montarReferencias();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
  } else {
    iniciar();
  }

  global.TrApoio = {
    abrir: abrir,
    fechar: fechar,
    temMaterial: MATS.length > 0,
    temReferencias: REFS.length > 0
  };
})(window);
