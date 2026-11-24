(function () {
  'use strict';

  var APRES = window.TRM_APRES || 0;
  function $(id) { return document.getElementById(id); }

  var btn = $('btnConfigSalvar');
  if (!btn) { return; }

  btn.addEventListener('click', function () {
    var modo = document.querySelector('input[name="cfgAcesso"]:checked');
    var inter = document.querySelector('input[name="cfgInteracao"]:checked');

    btn.disabled = true;

    window.trApi('trein_config', {
      id: APRES,
      modo_acesso: modo ? modo.value : 'token',
      interacao_obrigatoria: inter ? inter.value : 'todas',
      interacao_momento: $('cfgMomento') ? $('cfgMomento').value : 'narracao',
      tempo_min_slide: parseInt($('cfgTempoMin').value, 10) || 0,
      quiz_exige_acerto: $('cfgQuizAcerto').checked ? 1 : 0,
      exige_leitura: $('cfgLeitura').checked ? 1 : 0,
      prazo_dias: parseInt($('cfgPrazo').value, 10) || 0,
      carga_horaria_min: parseInt($('cfgCarga').value, 10) || 0,
      descricao: $('cfgDescricao').value,
      mensagem_conclusao: $('cfgMensagem').value,
      email_conclusao: $('cfgEmailRh').value,
      exige_termo: $('cfgTermo').checked ? 1 : 0,
      permite_revisao: $('cfgRevisao').checked ? 1 : 0
    }).then(function (j) {
      window.trToast(j.msg);
      btn.disabled = false;
    }).catch(function (e) {
      btn.disabled = false;
      window.trToast(e.message, 'erro');
    });
  });
})();
