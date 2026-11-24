(function () {
  'use strict';

  var APRES = window.TRM_APRES || 0;

  function $(id) { return document.getElementById(id); }

  function dados(el, seletor) {
    var tag = el.querySelector(seletor);
    if (!tag) { return {}; }
    try { return JSON.parse(tag.textContent || '{}'); } catch (e) { return {}; }
  }

  function erro(caixa, msg) {
    if (!caixa) { return; }
    if (!msg) { caixa.hidden = true; caixa.textContent = ''; return; }
    caixa.hidden = false;
    caixa.textContent = msg;
  }

  function recarregar() { window.location.reload(); }

  function abrirMaterial(m) {
    m = m || {};
    $('matId').value = m.id || 0;
    $('matTitulo').textContent = m.id ? 'Editar documento de apoio' : 'Novo documento de apoio';
    $('matTituloCampo').value = m.titulo || '';
    $('matDescricao').value = m.descricao || '';
    $('matUrl').value = m.url || '';
    $('matDestaque').checked = m.destaque === 1;
    $('matAtivo').checked = m.id ? m.ativo === 1 : true;
    $('matArquivo').value = '';

    var tipo = m.tipo === 'link' ? 'link' : 'arquivo';
    Array.prototype.forEach.call(document.querySelectorAll('input[name="matTipo"]'), function (r) {
      r.checked = (r.value === tipo);
    });
    alternarTipo();

    $('matArquivoAtual').textContent = m.arquivo
      ? 'Arquivo atual: ' + m.arquivo + '. Envie outro apenas se quiser substituir.'
      : 'Máximo de 30 MB.';

    erro($('matErro'), '');
    window.trAbrirModal('modalMaterial');
  }

  function alternarTipo() {
    var sel = document.querySelector('input[name="matTipo"]:checked');
    var link = sel && sel.value === 'link';
    $('matBlocoArquivo').hidden = link;
    $('matBlocoLink').hidden = !link;
  }

  function salvarMaterial() {
    var btn = $('matSalvar');
    var sel = document.querySelector('input[name="matTipo"]:checked');
    var tipo = sel ? sel.value : 'arquivo';
    var arq = $('matArquivo').files[0] || null;

    erro($('matErro'), '');
    btn.disabled = true;

    var extras = {};
    if (tipo === 'arquivo' && arq) { extras.arquivo = arq; }

    window.trApi('mat_salvar', {
      id: parseInt($('matId').value, 10) || 0,
      apresentacao_id: APRES,
      titulo: $('matTituloCampo').value,
      descricao: $('matDescricao').value,
      tipo: tipo,
      url: $('matUrl').value,
      destaque: $('matDestaque').checked ? 1 : 0,
      ativo: $('matAtivo').checked ? 1 : 0
    }, extras).then(function (j) {
      window.trToast(j.msg);
      recarregar();
    }).catch(function (e) {
      btn.disabled = false;
      erro($('matErro'), e.message);
    });
  }

  function abrirRef(r) {
    r = r || {};
    $('refId').value = r.id || 0;
    $('refTitulo').textContent = r.id ? 'Editar referência' : 'Nova referência';
    $('refTexto').value = r.texto || '';
    $('refUrl').value = r.url || '';
    $('refAtivo').checked = r.id ? r.ativo === 1 : true;
    erro($('refErro'), '');
    window.trAbrirModal('modalRef');
  }

  function salvarRef() {
    var btn = $('refSalvar');
    erro($('refErro'), '');
    btn.disabled = true;

    window.trApi('ref_salvar', {
      id: parseInt($('refId').value, 10) || 0,
      apresentacao_id: APRES,
      texto: $('refTexto').value,
      url: $('refUrl').value,
      ativo: $('refAtivo').checked ? 1 : 0
    }).then(function (j) {
      window.trToast(j.msg);
      recarregar();
    }).catch(function (e) {
      btn.disabled = false;
      erro($('refErro'), e.message);
    });
  }

  function marcado(id) { var e = $(id); return e && e.checked ? 1 : 0; }

  function salvarApostila() {
    var btn = $('apoSalvar');
    btn.disabled = true;

    window.trApi('mat_apostila', {
      apresentacao_id: APRES,
      ativa: marcado('apoAtiva'),
      titulo: $('apoTitulo').value,
      narracao: marcado('apoNarracao'),
      imagens: marcado('apoImagens'),
      questoes: marcado('apoQuestoes'),
      gabarito: marcado('apoGabarito'),
      referencias: marcado('apoReferencias'),
      anotacoes: marcado('apoAnotacoes')
    }).then(function (j) {
      window.trToast(j.msg);
      recarregar();
    }).catch(function (e) {
      btn.disabled = false;
      window.trToast(e.message, 'erro');
    });
  }

  if ($('apoAtiva')) {
    $('apoAtiva').addEventListener('change', function () {
      var op = $('apoOpcoes');
      if (op) { op.hidden = !this.checked; }
      var cx = $('trmtApo');
      if (cx) { cx.classList.toggle('is-on', this.checked); }
    });
  }
  if ($('apoSalvar')) { $('apoSalvar').addEventListener('click', salvarApostila); }

  function chamar(acao, corpo, confirmar) {
    var passo = confirmar ? window.trConfirmar(confirmar) : Promise.resolve(true);
    passo.then(function (ok) {
      if (!ok) { return; }
      window.trApi(acao, corpo).then(function (j) {
        window.trToast(j.msg);
        recarregar();
      }).catch(function (e) { window.trToast(e.message, 'erro'); });
    });
  }

  ['btnNovoMaterial', 'btnNovoMaterialVazio'].forEach(function (b) {
    if ($(b)) { $(b).addEventListener('click', function () { abrirMaterial(null); }); }
  });
  ['btnNovaRef', 'btnNovaRefVazio'].forEach(function (b) {
    if ($(b)) { $(b).addEventListener('click', function () { abrirRef(null); }); }
  });

  if ($('matSalvar')) { $('matSalvar').addEventListener('click', salvarMaterial); }
  if ($('refSalvar')) { $('refSalvar').addEventListener('click', salvarRef); }

  Array.prototype.forEach.call(document.querySelectorAll('input[name="matTipo"]'), function (r) {
    r.addEventListener('change', alternarTipo);
  });

  document.addEventListener('click', function (ev) {
    var alvo = ev.target.closest ? ev.target.closest('button') : null;
    if (!alvo) { return; }

    var card = alvo.closest('.trmt-card');
    var ref  = alvo.closest('.trmt-ref');

    if (card) {
      var m = dados(card, '.trmt-dados');
      var idM = parseInt(card.getAttribute('data-id'), 10) || 0;

      if (alvo.classList.contains('trmt-editar')) { abrirMaterial(m); return; }

      if (alvo.classList.contains('trmt-mover')) {
        chamar('mat_mover', {
          id: idM, apresentacao_id: APRES,
          direcao: parseInt(alvo.getAttribute('data-dir'), 10) || 1
        });
        return;
      }

      if (alvo.classList.contains('trmt-ativar')) {
        chamar('mat_ativar', { id: idM, apresentacao_id: APRES });
        return;
      }

      if (alvo.classList.contains('trmt-excluir')) {
        chamar('mat_excluir', { id: idM, apresentacao_id: APRES }, {
          titulo: 'Excluir documento',
          texto: 'O arquivo será removido do servidor e deixará de aparecer para os colaboradores. Continuar?',
          confirmar: 'Excluir',
          perigo: true
        });
        return;
      }
    }

    if (ref) {
      var r = dados(ref, '.trmt-ref-dados');
      var idR = parseInt(ref.getAttribute('data-id'), 10) || 0;

      if (alvo.classList.contains('trmt-ref-editar')) { abrirRef(r); return; }

      if (alvo.classList.contains('trmt-ref-mover')) {
        chamar('ref_mover', {
          id: idR, apresentacao_id: APRES,
          direcao: parseInt(alvo.getAttribute('data-dir'), 10) || 1
        });
        return;
      }

      if (alvo.classList.contains('trmt-ref-ativar')) {
        chamar('ref_ativar', { id: idR, apresentacao_id: APRES });
        return;
      }

      if (alvo.classList.contains('trmt-ref-excluir')) {
        chamar('ref_excluir', { id: idR, apresentacao_id: APRES }, {
          titulo: 'Excluir referência',
          texto: 'A referência será removida da lista de conteúdo. Continuar?',
          confirmar: 'Excluir',
          perigo: true
        });
      }
    }
  });
})();
