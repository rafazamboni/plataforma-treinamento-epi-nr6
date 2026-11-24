(function () {
  'use strict';

  var APRES = window.TRM_APRES || 0;
  var $ = function (id) { return document.getElementById(id); };

  function recarregar(atraso) {
    setTimeout(function () { window.location.reload(); }, atraso || 900);
  }

  if ($('btnProvaSalvar')) {
    $('btnProvaSalvar').addEventListener('click', function () {
      var btn = this;
      btn.disabled = true;

      window.trApi('prova_config', {
        id: APRES,
        prova_ativa: $('cfgProvaAtiva').checked ? 1 : 0,
        prova_nota_minima: parseInt($('cfgNota').value, 10) || 0,
        prova_tentativas: parseInt($('cfgTentativas').value, 10) || 0,
        prova_qtd_sorteada: parseInt($('cfgSorteio').value, 10) || 0,
        prova_embaralhar: $('cfgEmbaralhar').checked ? 1 : 0,
        prova_mostrar_gabarito: $('cfgGabarito').checked ? 1 : 0,
        prova_instrucoes: $('cfgInstrucoes').value
      }).then(function (j) {
        window.trToast(j.msg);
        recarregar();
      }).catch(function (e) {
        btn.disabled = false;
        window.trToast(e.message, 'erro');
      });
    });
  }

  var caixaAlts = $('qAlternativas');

  function tipoAtual() {
    return $('qTipo') ? $('qTipo').value : 'unica';
  }

  function linhaAlternativa(texto, correta, bloqueada) {
    var linha = document.createElement('div');
    linha.className = 'trq-alt-linha';

    var multipla = tipoAtual() === 'multipla';
    linha.innerHTML =
      '<label class="trq-alt-marca" title="Marcar como correta">' +
        '<input type="' + (multipla ? 'checkbox' : 'radio') + '" name="qCorreta">' +
      '</label>' +
      '<input type="text" class="form-control trq-alt-texto" maxlength="500" placeholder="Texto da alternativa">' +
      '<button type="button" class="trm-ico trq-alt-remover" title="Remover"><i class="bi bi-x-lg"></i></button>';

    linha.querySelector('.trq-alt-texto').value = texto || '';
    linha.querySelector('input[name="qCorreta"]').checked = !!correta;

    if (bloqueada) {
      linha.querySelector('.trq-alt-texto').readOnly = true;
      linha.querySelector('.trq-alt-remover').remove();
    }

    linha.querySelector('.trq-alt-texto').addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') {
        ev.preventDefault();
        adicionarAlternativa();
      }
    });

    return linha;
  }

  function adicionarAlternativa(texto, correta) {
    if (!caixaAlts) { return; }
    if (caixaAlts.children.length >= 8) {
      window.trToast('Máximo de 8 alternativas por questão.', 'aviso');
      return;
    }
    var linha = linhaAlternativa(texto, correta, false);
    caixaAlts.appendChild(linha);
    if (texto == null) { linha.querySelector('.trq-alt-texto').focus(); }
  }

  if ($('qAddAlt')) {
    $('qAddAlt').addEventListener('click', function () { adicionarAlternativa(); });
  }

  if (caixaAlts) {
    caixaAlts.addEventListener('click', function (ev) {
      var b = ev.target.closest('.trq-alt-remover');
      if (!b) { return; }
      if (caixaAlts.children.length <= 2) {
        window.trToast('A questão precisa de pelo menos duas alternativas.', 'aviso');
        return;
      }
      b.closest('.trq-alt-linha').remove();
    });
  }

  if ($('qTipo')) {
    $('qTipo').addEventListener('change', function () {
      var tipo = this.value;
      var atuais = lerAlternativas();

      if (tipo === 'vf') {
        montarAlternativas([
          { texto: 'Verdadeiro', correta: atuais[0] ? atuais[0].correta : 1 },
          { texto: 'Falso',      correta: atuais[1] ? atuais[1].correta : 0 }
        ], true);
        $('qAddAlt').hidden = true;
        $('qDicaTipo').textContent = 'Marque se a afirmação do enunciado é verdadeira ou falsa.';
        return;
      }

      $('qAddAlt').hidden = false;
      if (!atuais.length || (atuais.length === 2 && atuais[0].texto === 'Verdadeiro')) {
        atuais = [{ texto: '', correta: 1 }, { texto: '', correta: 0 }, { texto: '', correta: 0 }];
      }
      montarAlternativas(atuais, false);

      $('qDicaTipo').textContent = tipo === 'multipla'
        ? 'Marque todas as alternativas corretas (pelo menos duas).'
        : 'Marque a alternativa correta.';
    });
  }

  function montarAlternativas(lista, bloqueadas) {
    caixaAlts.innerHTML = '';
    (lista || []).forEach(function (a) {
      caixaAlts.appendChild(linhaAlternativa(a.texto, a.correta, bloqueadas));
    });
  }

  function lerAlternativas() {
    if (!caixaAlts) { return []; }
    return Array.prototype.map.call(caixaAlts.querySelectorAll('.trq-alt-linha'), function (l) {
      return {
        texto: (l.querySelector('.trq-alt-texto').value || '').trim(),
        correta: l.querySelector('input[name="qCorreta"]').checked ? 1 : 0
      };
    });
  }

  function abrirNova() {
    $('qId').value = '0';
    $('qEnunciado').value = '';
    $('qExplicacao').value = '';
    $('qPeso').value = '1';
    $('qTipo').value = 'unica';
    $('qAtiva').checked = true;
    $('qAddAlt').hidden = false;
    $('qDicaTipo').textContent = 'Marque a alternativa correta.';
    montarAlternativas([
      { texto: '', correta: 1 }, { texto: '', correta: 0 }, { texto: '', correta: 0 }
    ], false);
    $('questaoTitulo').textContent = 'Nova questão';
    window.trAbrirModal('modalQuestao');
    $('qEnunciado').focus();
  }

  if ($('btnNovaQuestao'))      { $('btnNovaQuestao').addEventListener('click', abrirNova); }
  if ($('btnNovaQuestaoVazio')) { $('btnNovaQuestaoVazio').addEventListener('click', abrirNova); }

  function abrirEdicao(d) {
    $('qId').value = d.id;
    $('qEnunciado').value = d.enunciado || '';
    $('qExplicacao').value = d.explicacao || '';
    $('qPeso').value = d.peso || 1;
    $('qTipo').value = d.tipo || 'unica';
    $('qAtiva').checked = d.ativa !== 0;
    $('qAddAlt').hidden = (d.tipo === 'vf');
    $('qDicaTipo').textContent = d.tipo === 'multipla'
      ? 'Marque todas as alternativas corretas (pelo menos duas).'
      : (d.tipo === 'vf' ? 'Marque se a afirmação do enunciado é verdadeira ou falsa.'
                         : 'Marque a alternativa correta.');
    montarAlternativas(d.alternativas || [], d.tipo === 'vf');
    $('questaoTitulo').textContent = 'Editar questão';
    window.trAbrirModal('modalQuestao');
  }

  if ($('qSalvar')) {
    $('qSalvar').addEventListener('click', function () {
      var btn = this;
      var enunciado = ($('qEnunciado').value || '').trim();
      if (!enunciado) {
        window.trToast('Escreva o enunciado da questão.', 'aviso');
        $('qEnunciado').focus();
        return;
      }

      var alts = lerAlternativas().filter(function (a) { return a.texto !== ''; });
      if (alts.length < 2) {
        window.trToast('Informe pelo menos duas alternativas.', 'aviso');
        return;
      }
      if (!alts.some(function (a) { return a.correta === 1; })) {
        window.trToast('Marque qual alternativa é a correta.', 'aviso');
        return;
      }

      btn.disabled = true;
      window.trApi('prova_questao_salvar', {
        id: parseInt($('qId').value, 10) || 0,
        apresentacao_id: APRES,
        tipo: $('qTipo').value,
        enunciado: enunciado,
        explicacao: ($('qExplicacao').value || '').trim(),
        peso: parseInt($('qPeso').value, 10) || 1,
        ativa: $('qAtiva').checked ? 1 : 0,
        alternativas: alts
      }).then(function (j) {
        window.trToast(j.msg);
        recarregar(700);
      }).catch(function (e) {
        btn.disabled = false;
        window.trToast(e.message, 'erro');
      });
    });
  }

  document.addEventListener('click', function (ev) {
    var card = ev.target.closest('.trq-card');
    if (!card) { return; }
    var qid = parseInt(card.getAttribute('data-id'), 10);
    var el;

    el = ev.target.closest('.trq-editar');
    if (el) {
      var dados = card.querySelector('.trq-dados');
      abrirEdicao(JSON.parse(dados.textContent));
      return;
    }

    el = ev.target.closest('.trq-ativar');
    if (el) {
      var nova = el.getAttribute('data-ativa') === '1' ? 0 : 1;
      window.trApi('prova_questao_ativa', { id: qid, ativa: nova })
        .then(function (j) { window.trToast(j.msg); recarregar(600); })
        .catch(function (e) { window.trToast(e.message, 'erro'); });
      return;
    }

    el = ev.target.closest('.trq-mover');
    if (el) {
      mover(card, parseInt(el.getAttribute('data-dir'), 10));
      return;
    }

    el = ev.target.closest('.trq-excluir');
    if (el) {
      window.trConfirmar({
        titulo: 'Excluir questão',
        texto: 'A questão e suas alternativas serão removidas. As provas já realizadas mantêm o histórico.',
        confirmar: 'Excluir',
        perigo: true
      }).then(function (sim) {
        if (!sim) { return; }
        window.trApi('prova_questao_excluir', { id: qid })
          .then(function (j) { window.trToast(j.msg); recarregar(600); })
          .catch(function (e) { window.trToast(e.message, 'erro'); });
      });
    }
  });

  function mover(card, dir) {
    var lista = $('trqLista');
    if (!lista) { return; }
    var irmao = dir < 0 ? card.previousElementSibling : card.nextElementSibling;
    if (!irmao) { return; }

    if (dir < 0) { lista.insertBefore(card, irmao); }
    else { lista.insertBefore(irmao, card); }

    var ordem = Array.prototype.map.call(lista.querySelectorAll('.trq-card'), function (c) {
      return parseInt(c.getAttribute('data-id'), 10);
    });

    window.trApi('prova_questao_reordenar', { ordem: ordem })
      .then(function () { renumerar(); })
      .catch(function (e) { window.trToast(e.message, 'erro'); });
  }

  function renumerar() {
    Array.prototype.forEach.call(document.querySelectorAll('.trq-card .trq-ordem'), function (n, i) {
      n.textContent = String(i + 1);
    });
  }
})();
