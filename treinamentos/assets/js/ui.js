(function (global) {
  'use strict';

  function area() {
    var el = document.getElementById('trToastArea');
    if (!el) {
      el = document.createElement('div');
      el.id = 'trToastArea';
      el.className = 'tr-toast-area';
      document.body.appendChild(el);
    }
    return el;
  }

  function trToast(msg, tipo) {
    var el = document.createElement('div');
    el.className = 'tr-toast' + (tipo ? ' tr-toast-' + tipo : '');
    el.innerHTML = '<i class="bi bi-' +
      (tipo === 'erro' ? 'exclamation-triangle' : (tipo === 'aviso' ? 'info-circle' : 'check-circle')) +
      '"></i><span></span>';
    el.querySelector('span').textContent = msg;
    area().appendChild(el);
    setTimeout(function () {
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 300);
    }, 3400);
  }

  function trConfirmar(opcoes) {
    return new Promise(function (resolve) {
      var o = opcoes || {};
      var fundo = document.createElement('div');
      fundo.className = 'tr-modal is-aberto tr-modal-confirma';
      fundo.innerHTML =
        '<div class="tr-modal-caixa">' +
          '<div class="tr-modal-head"><span></span></div>' +
          '<div class="tr-modal-body"><p class="tr-confirma-texto"></p></div>' +
          '<div class="tr-modal-pe">' +
            '<button type="button" class="btn tr-btn-ghost" data-r="0"></button>' +
            '<button type="button" class="btn" data-r="1"></button>' +
          '</div>' +
        '</div>';
      fundo.querySelector('.tr-modal-head span').textContent = o.titulo || 'Confirmar';
      fundo.querySelector('.tr-confirma-texto').textContent = o.texto || 'Deseja continuar?';
      var btnNao = fundo.querySelector('[data-r="0"]');
      var btnSim = fundo.querySelector('[data-r="1"]');
      btnNao.textContent = o.cancelar || 'Cancelar';
      btnSim.textContent = o.confirmar || 'Confirmar';
      btnSim.className = 'btn ' + (o.perigo ? 'tr-btn-perigo-solido' : 'tr-btn-primaria');

      function fechar(valor) {
        if (fundo.contains(document.activeElement)) { document.activeElement.blur(); }
        fundo.remove();
        resolve(valor);
      }
      btnNao.addEventListener('click', function () { fechar(false); });
      btnSim.addEventListener('click', function () { fechar(true); });
      fundo.addEventListener('click', function (ev) {
        if (ev.target === fundo) { fechar(false); }
      });
      document.body.appendChild(fundo);
      btnSim.focus();
    });
  }

  function trApi(acao, dados, extras) {
    var corpo = new FormData();
    corpo.append('acao', acao);
    corpo.append('csrf', global.TR_CSRF || '');
    corpo.append('csrf_token', global.TR_CSRF || '');
    corpo.append('dados', JSON.stringify(dados || {}));
    if (extras) {
      Object.keys(extras).forEach(function (k) { corpo.append(k, extras[k]); });
    }
    return fetch(global.TR_API, {
      method: 'POST',
      body: corpo,
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': global.TR_CSRF || ''
      }
    }).then(function (r) {
      return r.json().catch(function () {
        throw new Error(r.status === 419
          ? 'Sessão expirada (419). Recarregue a página e tente de novo.'
          : 'Resposta inválida do servidor (' + r.status + ').');
      }).then(function (j) {
        if (!j || !j.ok) {
          throw new Error((j && j.msg) || 'Não foi possível concluir a operação.');
        }
        return j;
      });
    });
  }

  function trAbrirModal(id) {
    var m = document.getElementById(id);
    if (!m) { return; }
    m.classList.add('is-aberto');
    m.removeAttribute('inert');
    m.removeAttribute('aria-hidden');
    var foco = m.querySelector('input:not([type=hidden]):not([disabled]),select,textarea,button');
    if (foco) { try { foco.focus({ preventScroll: true }); } catch (e) {} }
  }

  function trFecharModal(id) {
    var m = document.getElementById(id);
    if (!m) { return; }
    if (m.contains(document.activeElement)) { document.activeElement.blur(); }
    m.classList.remove('is-aberto');
    m.setAttribute('inert', '');
    m.removeAttribute('aria-hidden');
  }

  document.addEventListener('click', function (ev) {
    var alvo = ev.target.closest('[data-fechar]');
    if (alvo) { trFecharModal(alvo.getAttribute('data-fechar')); return; }
    if (ev.target.classList && ev.target.classList.contains('tr-modal') && ev.target.id) {
      trFecharModal(ev.target.id);
    }
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Escape') { return; }
    var aberto = document.querySelector('.tr-modal.is-aberto:not(.tr-modal-confirma)');
    if (aberto && aberto.id) { trFecharModal(aberto.id); }
  });

  global.trToast = trToast;
  global.trConfirmar = trConfirmar;
  global.trApi = trApi;
  global.trAbrirModal = trAbrirModal;
  global.trFecharModal = trFecharModal;
})(window);
