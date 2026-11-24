(function () {
  'use strict';

  var APRES = window.TRM_APRES || 0;
  var $ = function (id) { return document.getElementById(id); };

  function recarregar(atraso) {
    setTimeout(function () { window.location.reload(); }, atraso || 900);
  }

  var selCargo = $('troCargoNovo');
  var btnAdd   = $('troAdicionar');

  if (selCargo && btnAdd) {
    selCargo.addEventListener('change', function () {
      btnAdd.disabled = selCargo.value === '';
    });

    btnAdd.addEventListener('click', function () {
      var cargo = selCargo.value;
      if (!cargo) { return; }
      btnAdd.disabled = true;
      window.trApi('cargo_adicionar', { apresentacao_id: APRES, cargo: cargo })
        .then(function (j) {
          window.trToast(j.msg);
          recarregar();
        })
        .catch(function (e) {
          btnAdd.disabled = false;
          window.trToast(e.message, 'erro');
        });
    });
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-acao="cargo_remover"]');
    if (!btn) { return; }
    window.trConfirmar({
      titulo: 'Remover obrigatoriedade',
      texto: 'O cargo "' + btn.getAttribute('data-cargo') + '" deixa de ser obrigatório neste ' +
             'treinamento. Quem já foi matriculado continua matriculado. Continuar?',
      confirmar: 'Remover',
      perigo: true
    }).then(function (sim) {
      if (!sim) { return; }
      window.trApi('cargo_remover', { id: parseInt(btn.getAttribute('data-id'), 10), apresentacao_id: APRES })
        .then(function (j) { window.trToast(j.msg); recarregar(); })
        .catch(function (e) { window.trToast(e.message, 'erro'); });
    });
  });

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-acao="matricular"]');
    if (!btn) { return; }
    btn.disabled = true;
    window.trApi('insc_criar', {
      apresentacao_id: APRES,
      colaborador_id: parseInt(btn.getAttribute('data-colaborador-id'), 10),
      enviar: false
    }).then(function (j) {
      window.trToast(j.msg);
      recarregar();
    }).catch(function (e) {
      btn.disabled = false;
      window.trToast(e.message, 'erro');
    });
  });

  if ($('btnMatricularTodos')) {
    $('btnMatricularTodos').addEventListener('click', function () {
      var self = this;
      window.trConfirmar({
        titulo: 'Matricular todos os pendentes',
        texto: 'Todos os colaboradores pendentes nos cargos obrigatórios serão matriculados agora. Continuar?',
        confirmar: 'Matricular todos'
      }).then(function (sim) {
        if (!sim) { return; }
        self.disabled = true;
        window.trApi('cargo_matricular_todos', { apresentacao_id: APRES })
          .then(function (j) { window.trToast(j.msg); recarregar(1200); })
          .catch(function (e) { self.disabled = false; window.trToast(e.message, 'erro'); });
      });
    });
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-acao="externo"]');
    if (!btn) { return; }
    $('exId').value = '0';
    $('exColaboradorId').value = btn.getAttribute('data-colaborador-id') || '0';
    $('exNome').textContent = btn.getAttribute('data-nome') || '';
    $('exData').value = new Date().toISOString().slice(0, 10);
    $('exObs').value = '';
    $('exArquivo').value = '';
    window.trAbrirModal('modalExterno');
  });

  if ($('exSalvar')) {
    $('exSalvar').addEventListener('click', function () {
      var btn = this;
      var extras = {};
      if ($('exArquivo').files[0]) { extras.arquivo = $('exArquivo').files[0]; }
      btn.disabled = true;
      window.trApi('insc_marcar_externo', {
        apresentacao_id: APRES,
        id: parseInt($('exId').value, 10) || 0,
        colaborador_id: parseInt($('exColaboradorId').value, 10) || 0,
        data_conclusao: $('exData').value,
        observacao: $('exObs').value
      }, extras).then(function (j) {
        window.trToast(j.msg);
        window.trFecharModal('modalExterno');
        recarregar();
      }).catch(function (e) {
        btn.disabled = false;
        window.trToast(e.message, 'erro');
      });
    });
  }
})();
