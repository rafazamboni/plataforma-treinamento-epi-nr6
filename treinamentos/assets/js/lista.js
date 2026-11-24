(function () {
  'use strict';

  function abrirNova() { window.trAbrirModal('modalNova'); }

  var btnNova = document.getElementById('btnNova');
  if (btnNova) { btnNova.addEventListener('click', abrirNova); }
  ['btnNovaVazio', 'btnNovaVazio2'].forEach(function (id) {
    var b = document.getElementById(id);
    if (b) { b.addEventListener('click', abrirNova); }
  });

  var confirmar = document.getElementById('novaConfirmar');
  if (confirmar) {
    confirmar.addEventListener('click', function () {
      var titulo = (document.getElementById('novaTitulo').value || '').trim();
      if (!titulo) {
        window.trToast('Informe o título da apresentação.', 'aviso');
        return;
      }
      confirmar.disabled = true;
      window.trApi('apres_criar', {
        titulo: titulo,
        subtitulo: (document.getElementById('novaSubtitulo').value || '').trim()
      }).then(function (j) {
        window.location.href = (window.TR_BASE || '') + 'editor.php?id=' + j.id;
      }).catch(function (e) {
        confirmar.disabled = false;
        window.trToast(e.message, 'erro');
      });
    });
  }

  document.addEventListener('click', function (ev) {
    var el;

    el = ev.target.closest('.tr-copiar');
    if (el) {
      var link = el.getAttribute('data-link');
      var copiar = navigator.clipboard
        ? navigator.clipboard.writeText(link)
        : Promise.reject();
      copiar.then(function () {
        el.classList.add('is-copiado');
        setTimeout(function () { el.classList.remove('is-copiado'); }, 1400);
        window.trToast('Link copiado.');
      }).catch(function () {
        var campo = document.createElement('textarea');
        campo.value = link;
        campo.setAttribute('readonly', '');
        campo.style.cssText = 'position:fixed;top:-1000px;opacity:0';
        document.body.appendChild(campo);
        campo.select();
        var deu = false;
        try { deu = document.execCommand('copy'); } catch (e) { deu = false; }
        campo.remove();
        window.trToast(deu ? 'Link copiado.' : 'Não foi possível copiar. Copie da barra do cartão.',
                       deu ? undefined : 'aviso');
      });
      return;
    }

    el = ev.target.closest('.tr-acao-publicar, .tr-acao-publico');
    if (el) {
      var publico = el.getAttribute('data-publico') === '1';
      window.trConfirmar({
        titulo: publico ? 'Encerrar link público' : 'Publicar link',
        texto: publico
          ? 'O link deixará de funcionar para quem estiver fora do sistema. Deseja continuar?'
          : 'A apresentação ficará acessível por link, sem necessidade de login. Deseja continuar?',
        confirmar: publico ? 'Encerrar' : 'Publicar',
        perigo: publico
      }).then(function (ok) {
        if (!ok) { return; }
        return window.trApi('apres_publicar', { id: Number(el.getAttribute('data-id')) })
          .then(function (j) {
            window.trToast(j.msg);
            setTimeout(function () { window.location.reload(); }, 700);
          });
      }).catch(function (e) { window.trToast(e.message, 'erro'); });
      return;
    }

    el = ev.target.closest('.tr-acao-duplicar');
    if (el) {
      if (el.dataset.ocupado === '1') { return; }
      el.dataset.ocupado = '1';
      window.trApi('apres_duplicar', { id: Number(el.getAttribute('data-id')) })
        .then(function (j) {
          window.trToast(j.msg);
          setTimeout(function () {
            window.location.href = (window.TR_BASE || '') + 'editor.php?id=' + j.id;
          }, 600);
        })
        .catch(function (e) {
          el.dataset.ocupado = '';
          window.trToast(e.message, 'erro');
        });
      return;
    }

    el = ev.target.closest('.tr-acao-excluir');
    if (el) {
      window.trConfirmar({
        titulo: 'Excluir apresentação',
        texto: 'A apresentação "' + el.getAttribute('data-titulo') +
               '", seus slides, falas e áudios serão apagados. Esta ação não pode ser desfeita.',
        confirmar: 'Excluir',
        perigo: true
      }).then(function (ok) {
        if (!ok) { return; }
        return window.trApi('apres_excluir', { id: Number(el.getAttribute('data-id')) })
          .then(function (j) {
            window.trToast(j.msg);
            var card = el.closest('.trh-card, .tr-card, tr[data-id]');
            if (!card) { return; }
            card.classList.add('is-saindo');
            setTimeout(function () {
              card.remove();
              if (typeof window.trListaAtualizou === 'function') { window.trListaAtualizou(); }
            }, 260);
          });
      }).catch(function (e) { window.trToast(e.message, 'erro'); });
    }
  });
})();
