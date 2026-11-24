(function () {
  'use strict';

  var CFG = window.TRP || {};
  var $ = function (id) { return document.getElementById(id); };

  var form   = $('trpForm');
  var erro   = $('trpErro');
  var btn    = $('trpEnviar');
  var conta  = $('trpContagem');
  var inicio = Date.now();
  var enviando = false;

  if (!form || !btn) { return; }

  function questoes() {
    return Array.prototype.slice.call(form.querySelectorAll('.trp-questao'));
  }

  function respostasDe(q) {
    return Array.prototype.map.call(
      q.querySelectorAll('input:checked'),
      function (i) { return parseInt(i.value, 10); }
    );
  }

  function atualizarContagem() {
    var todas = questoes();
    var feitas = todas.filter(function (q) { return respostasDe(q).length > 0; }).length;
    if (conta) {
      conta.innerHTML = '<i class="bi bi-list-check"></i> ' + feitas + ' de ' + todas.length + ' respondidas';
    }
    todas.forEach(function (q) {
      q.classList.toggle('is-respondida', respostasDe(q).length > 0);
    });
  }

  form.addEventListener('change', function () {
    atualizarContagem();
    if (erro) { erro.hidden = true; }
  });
  atualizarContagem();

  window.addEventListener('beforeunload', function (ev) {
    if (enviando) { return; }
    var feitas = questoes().filter(function (q) { return respostasDe(q).length > 0; }).length;
    if (feitas === 0) { return; }
    ev.preventDefault();
    ev.returnValue = '';
  });

  function mostrarErro(msg) {
    if (!erro) { return; }
    erro.hidden = false;
    erro.textContent = msg;
    erro.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  btn.addEventListener('click', function () {
    var todas = questoes();
    var pendentes = todas.filter(function (q) { return respostasDe(q).length === 0; });

    if (pendentes.length) {
      mostrarErro('Responda todas as questões antes de enviar. Faltam ' + pendentes.length + '.');
      pendentes[0].classList.add('is-faltando');
      pendentes[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
      setTimeout(function () { pendentes[0].classList.remove('is-faltando'); }, 2200);
      return;
    }

    var respostas = {};
    todas.forEach(function (q) {
      respostas[q.getAttribute('data-id')] = respostasDe(q);
    });

    enviando = true;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Corrigindo…';

    var corpo = new FormData();
    corpo.append('respostas', JSON.stringify(respostas));
    corpo.append('tempo_seg', String(Math.round((Date.now() - inicio) / 1000)));

    fetch(CFG.api + 'prova', { method: 'POST', body: corpo, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j || !j.ok) { throw new Error((j && j.msg) || 'Não foi possível enviar a prova.'); }
        mostrarResultado(j);
      })
      .catch(function (e) {
        enviando = false;
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send-check"></i> Enviar respostas';
        mostrarErro(e.message);
      });
  });

  function mostrarResultado(j) {
    var cartao = document.querySelector('.tra-cartao');
    if (!cartao) { return; }

    var html =
      '<div class="trp-resultado ' + (j.aprovado ? 'is-aprovado' : 'is-reprovado') + '">' +
        '<div class="trp-res-icone"><i class="bi bi-' +
          (j.aprovado ? 'patch-check-fill' : 'x-octagon-fill') + '"></i></div>' +
        '<div class="trp-res-nota">' + j.percentual + '%</div>' +
        '<h1>' + (j.aprovado ? 'Aprovado' : 'Não atingiu a nota mínima') + '</h1>' +
        '<p class="trp-res-sub">' + j.acertos + ' de ' + j.questoes +
          ' questões corretas · nota mínima ' + j.nota_minima + '%</p>' +
      '</div>';

    if (!j.aprovado) {
      if (j.pode_repetir) {
        html += '<p class="tra-descricao">' +
          (j.restantes === null
            ? 'Você pode refazer a prova quantas vezes precisar. Revise o conteúdo antes de tentar de novo.'
            : 'Você ainda tem ' + j.restantes + ' tentativa(s). Revise o conteúdo antes de tentar de novo.') +
          '</p>';
      } else {
        html += '<p class="tra-descricao">Você utilizou todas as tentativas disponíveis. ' +
          'Procure o RH para liberar uma nova avaliação.</p>';
      }
    }

    if (j.gabarito && j.gabarito.length) {
      html += '<div class="trp-gabarito"><h2><i class="bi bi-list-check"></i> Revisão das respostas</h2>';
      j.gabarito.forEach(function (g, i) {
        var alts = g.alternativas || {};
        html += '<div class="trp-gab-item ' + (g.certa ? 'is-certa' : 'is-errada') + '">' +
          '<div class="trp-gab-topo">' +
            '<i class="bi bi-' + (g.certa ? 'check-circle-fill' : 'x-circle-fill') + '"></i>' +
            '<b>' + (i + 1) + '.</b> <span></span>' +
          '</div>';

        if (!g.certa) {
          var textos = (g.corretas || []).map(function (id) { return alts[id] || ''; })
            .filter(Boolean);
          if (textos.length) {
            html += '<p class="trp-gab-resposta"><b>Resposta correta:</b> <span class="trp-gab-txt"></span></p>';
          }
        }
        if (g.explicacao) {
          html += '<p class="trp-gab-expl"><i class="bi bi-lightbulb"></i> <span class="trp-gab-txt2"></span></p>';
        }
        html += '</div>';
      });
      html += '</div>';
    }

    html += '<div class="tra-acoes">';
    if (j.aprovado) {
      html += '<button type="button" class="tra-btn tra-btn-primaria" id="trpConcluir">' +
                '<i class="bi bi-check2-circle"></i> Concluir treinamento</button>';
    } else if (j.pode_repetir) {
      html += '<a class="tra-btn tra-btn-primaria" href="' + CFG.capa + '&prova=1">' +
                '<i class="bi bi-arrow-repeat"></i> Refazer a prova</a>';
      html += '<a class="tra-btn tra-btn-ghost" href="' + CFG.capa + '&iniciar=1">' +
                '<i class="bi bi-collection-play"></i> Revisar o conteúdo</a>';
    }
    html += '<a class="tra-btn tra-btn-ghost" href="' + CFG.capa + '">' +
              '<i class="bi bi-house"></i> Voltar</a></div>';

    if (j.aprovado && CFG.exigeTermo) {
      html += '<label class="trp-termo"><input type="checkbox" id="trpTermo">' +
              '<span>Declaro que assisti integralmente a este treinamento e compreendi o seu conteúdo.</span></label>';
    }

    html += '<div class="trp-erro" id="trpErro2" hidden></div>';

    cartao.innerHTML = html;
    enviando = true;

    if (j.gabarito && j.gabarito.length) {
      var itens = cartao.querySelectorAll('.trp-gab-item');
      j.gabarito.forEach(function (g, i) {
        var item = itens[i];
        if (!item) { return; }
        item.querySelector('.trp-gab-topo span').textContent = g.enunciado || '';

        var alvo = item.querySelector('.trp-gab-txt');
        if (alvo) {
          var alts = g.alternativas || {};
          alvo.textContent = (g.corretas || []).map(function (id) { return alts[id] || ''; })
            .filter(Boolean).join(' · ');
        }
        var expl = item.querySelector('.trp-gab-txt2');
        if (expl) { expl.textContent = g.explicacao || ''; }
      });
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });

    var btnFim = $('trpConcluir');
    if (btnFim) { btnFim.addEventListener('click', concluir); }
  }

  function concluir() {
    var btnFim = $('trpConcluir');
    var termo  = $('trpTermo');
    var caixa  = $('trpErro2');

    if (CFG.exigeTermo && (!termo || !termo.checked)) {
      if (caixa) {
        caixa.hidden = false;
        caixa.textContent = 'Marque a declaração para concluir o treinamento.';
      }
      return;
    }

    if (caixa) { caixa.hidden = true; }
    btnFim.disabled = true;
    btnFim.innerHTML = '<i class="bi bi-hourglass-split"></i> Registrando…';

    var corpo = new FormData();
    corpo.append('termo', (termo && termo.checked) ? '1' : '0');

    fetch(CFG.api + 'concluir', { method: 'POST', body: corpo, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j || !j.ok) { throw new Error((j && j.msg) || 'Não foi possível registrar a conclusão.'); }
        window.location.href = j.certificado || j.capa || CFG.capa;
      })
      .catch(function (e) {
        btnFim.disabled = false;
        btnFim.innerHTML = '<i class="bi bi-check2-circle"></i> Concluir treinamento';
        if (caixa) { caixa.hidden = false; caixa.textContent = e.message; }
      });
  }
})();
