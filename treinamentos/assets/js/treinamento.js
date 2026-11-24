(function () {
  'use strict';

  var OPT = window.TR_PLAYER_OPT || {};
  var T = OPT.treinamento;
  var APRES = window.TR_APRESENTACAO;
  var player = window.TR_PLAYER;

  if (!T || !APRES || !player) { return; }

  var TOTAL = APRES.slides.length;
  var INTERVALO_MS = 25000;
  var enviados = {};
  var tempoAcumulado = 0;
  var overlayAberto = false;
  var conclusaoRegistrada = !!T.concluido;
  var ultimoEnvio = 0;

  function $(id) { return document.getElementById(id); }

  function aviso(msg, tipo) {
    var area = $('nvToastArea');
    if (!area || !msg) { return; }
    var el = document.createElement('div');
    el.className = 'nv-toast' + (tipo ? ' ' + tipo : '');
    el.textContent = msg;
    area.appendChild(el);
    setTimeout(function () {
      el.style.transition = 'opacity .3s ease';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 320);
    }, 4200);
  }

  function slideBanco(i) {
    var s = APRES.slides[i];
    if (!s) { return 0; }
    return parseInt(String(s.id).replace(/^s/, ''), 10) || 0;
  }

  function cumprida(i) {
    if (enviados[i]) { return true; }
    return window.TrEtapa ? window.TrEtapa.liberado(i) : !!player.concluidos[i];
  }

  function totalCumpridas() {
    var n = 0;
    for (var i = 0; i < TOTAL; i++) { if (cumprida(i)) { n++; } }
    return n;
  }

  function tudoCumprido() { return TOTAL > 0 && totalCumpridas() >= TOTAL; }

  function enviarProgresso(indice, concluido, saindo) {
    if (indice == null || indice < 0) { return; }
    if (T.previa) { return; }

    var corpo = new FormData();
    corpo.append('slide_id', String(slideBanco(indice)));
    corpo.append('slide_indice', String(indice));
    corpo.append('dialogo_max', String((player.ouvidas && player.ouvidas[indice]) || 0));
    corpo.append('concluido', concluido ? '1' : '0');
    corpo.append('tempo_seg', String(tempoAcumulado));
    tempoAcumulado = 0;
    ultimoEnvio = Date.now();

    var url = T.api + 'progresso';

    if (saindo && navigator.sendBeacon) {
      try { navigator.sendBeacon(url, corpo); return; } catch (e) {  }
    }

    fetch(url, {
      method: 'POST',
      body: corpo,
      credentials: 'same-origin',
      keepalive: !!saindo
    }).then(function (r) {
      return r.json();
    }).then(function (j) {
      if (j && j.ok) { pintarBadge(j.percentual); }
    }).catch(function () {  });
  }

  function registrarEtapa(i) {
    if (enviados[i]) { return; }
    enviados[i] = true;
    enviarProgresso(i, true, false);
    pintarBadge();
  }

  setInterval(function () {
    if (document.visibilityState === 'visible') { tempoAcumulado++; }
  }, 1000);

  setInterval(function () {
    if (Date.now() - ultimoEnvio < INTERVALO_MS - 1000) { return; }
    if (player.indice < 0) { return; }
    enviarProgresso(player.indice, cumprida(player.indice), false);
  }, INTERVALO_MS);

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden' && player.indice >= 0) {
      enviarProgresso(player.indice, cumprida(player.indice), true);
    }
  });

  window.addEventListener('pagehide', function () {
    if (player.indice >= 0) {
      enviarProgresso(player.indice, cumprida(player.indice), true);
    }
  });

  function pintarBadge(pct) {
    var v = (pct == null) ? Math.round((totalCumpridas() / Math.max(1, TOTAL)) * 100) : pct;
    if (window.TrEtapa && window.TrEtapa.progresso) { window.TrEtapa.progresso(v); }
  }

  function provaPendente() {
    return !!(T.temProva && T.prova && !T.provaOk);
  }

  function primeiraPendente() {
    for (var i = 0; i < TOTAL; i++) { if (!cumprida(i)) { return i; } }
    return -1;
  }

  function finalizar() {
    if (T.previa) { finalizarPrevia(); return; }

    var faltando = primeiraPendente();
    if (faltando >= 0) {
      aviso('O slide ' + (faltando + 1) + ' ainda tem atividades pendentes.');
      player.irPara(faltando, false);
      return;
    }
    abrirConclusao();
  }

  function finalizarPrevia() {
    if (overlayAberto) { return; }
    overlayAberto = true;
    player.encerrar();

    var fundo = document.createElement('div');
    fundo.className = 'trn-final';
    fundo.innerHTML =
      '<div class="trn-final-caixa" role="dialog" aria-modal="true">' +
        '<div class="trn-final-icone"><i class="bi bi-eye"></i></div>' +
        '<h2>Fim da pré-visualização</h2>' +
        '<p class="trn-final-titulo"></p>' +
        '<p class="trn-final-msg">Esta é a mesma tela que o colaborador vê, ' +
          'sem as travas de avanço. Nada foi registrado.</p>' +
        '<div class="trn-final-acoes">' +
          '<button type="button" class="trn-btn trn-btn-ghost" id="trnRever">' +
            '<i class="bi bi-arrow-counterclockwise"></i> Rever do início</button>' +
          '<a class="trn-btn trn-btn-ok" href="' + (T.capa || '#') + '">' +
            '<i class="bi bi-pencil"></i> Voltar ao editor</a>' +
        '</div>' +
      '</div>';

    document.body.appendChild(fundo);
    fundo.querySelector('.trn-final-titulo').textContent = T.titulo || '';

    $('trnRever').addEventListener('click', function () {
      fundo.remove();
      overlayAberto = false;
      player.irPara(0, false);
    });
  }

  function abrirConclusao() {
    if (overlayAberto) { return; }
    overlayAberto = true;

    player.encerrar();

    var comProva = provaPendente();
    var semTentativas = comProva && T.podeTentar === false;

    var acoes =
      '<button type="button" class="trn-btn trn-btn-ghost" id="trnRever">' +
        '<i class="bi bi-arrow-counterclockwise"></i> Rever conteúdo</button>';

    if (comProva && !semTentativas) {
      acoes += '<a class="trn-btn trn-btn-ok" id="trnProva" href="' + T.prova + '">' +
                 '<i class="bi bi-clipboard-check"></i> Fazer a prova</a>';
    } else if (comProva && semTentativas) {
      acoes += '<a class="trn-btn trn-btn-ok" href="' + T.capa + '">' +
                 '<i class="bi bi-house"></i> Voltar</a>';
    } else if (conclusaoRegistrada) {
      acoes += '<a class="trn-btn trn-btn-ok" href="' + T.capa + '">' +
                 '<i class="bi bi-house"></i> Voltar</a>';
    } else {
      acoes += '<button type="button" class="trn-btn trn-btn-ok" id="trnConcluir">' +
                 '<i class="bi bi-check2-circle"></i> Confirmar conclusão</button>';
    }

    var fundo = document.createElement('div');
    fundo.className = 'trn-final';
    fundo.innerHTML =
      '<div class="trn-final-caixa" role="dialog" aria-modal="true">' +
        '<div class="trn-final-icone"><i class="bi bi-' +
          (comProva ? 'clipboard-check' : 'patch-check-fill') + '"></i></div>' +
        '<h2></h2>' +
        '<p class="trn-final-titulo"></p>' +
        '<p class="trn-final-msg"></p>' +
        '<label class="trn-termo" hidden>' +
          '<input type="checkbox" id="trnTermo">' +
          '<span>Declaro que assisti integralmente a este treinamento e compreendi o seu conteúdo.</span>' +
        '</label>' +
        '<div class="trn-final-erro" hidden></div>' +
        '<div class="trn-final-acoes">' + acoes + '</div>' +
      '</div>';

    document.body.appendChild(fundo);

    fundo.querySelector('h2').textContent = conclusaoRegistrada
      ? 'Revisão concluída'
      : (comProva ? 'Conteúdo concluído' : 'Você concluiu todos os slides');
    fundo.querySelector('.trn-final-titulo').textContent = T.titulo || '';

    if (conclusaoRegistrada) {
      fundo.querySelector('.trn-final-msg').textContent =
        'Este treinamento já constava como concluído no seu histórico.';
    } else if (comProva) {
      fundo.querySelector('.trn-final-msg').textContent = semTentativas
        ? 'Você utilizou todas as tentativas da prova. Procure o RH para liberar uma nova avaliação.'
        : 'Falta apenas a prova final. Se preferir, reveja o conteúdo antes de começar.';
    } else {
      if (T.mensagem) { fundo.querySelector('.trn-final-msg').textContent = T.mensagem; }
      if (T.exigeTermo) { fundo.querySelector('.trn-termo').hidden = false; }
    }

    var erro = fundo.querySelector('.trn-final-erro');

    $('trnRever').addEventListener('click', function () {
      fundo.remove();
      overlayAberto = false;
      player.irPara(0, false);
    });

    var btnOk = $('trnConcluir');
    if (!btnOk) { return; }

    btnOk.addEventListener('click', function () {
      var termo = $('trnTermo');
      if (T.exigeTermo && (!termo || !termo.checked)) {
        erro.hidden = false;
        erro.textContent = 'Marque a declaração para confirmar a conclusão.';
        return;
      }
      erro.hidden = true;
      btnOk.disabled = true;
      btnOk.innerHTML = '<i class="bi bi-hourglass-split"></i> Registrando…';

      enviarProgresso(player.indice, true, false);

      var corpo = new FormData();
      corpo.append('termo', (termo && termo.checked) ? '1' : '0');

      fetch(T.api + 'concluir', { method: 'POST', body: corpo, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j || !j.ok) { throw new Error((j && j.msg) || 'Não foi possível registrar a conclusão.'); }
          conclusaoRegistrada = true;
          mostrarSucesso(fundo, j);
        })
        .catch(function (e) {
          btnOk.disabled = false;
          btnOk.innerHTML = '<i class="bi bi-check2-circle"></i> Confirmar conclusão';
          erro.hidden = false;
          erro.textContent = e.message;
        });
    });
  }

  function mostrarSucesso(fundo, j) {
    var caixa = fundo.querySelector('.trn-final-caixa');
    caixa.classList.add('is-ok');
    caixa.innerHTML =
      '<div class="trn-final-icone is-ok"><i class="bi bi-award-fill"></i></div>' +
      '<h2>Conclusão registrada</h2>' +
      '<p class="trn-final-msg">Seu histórico foi atualizado e o RH já foi notificado.</p>' +
      '<p class="trn-codigo" hidden>Código de validação: <b></b></p>' +
      '<div class="trn-final-acoes">' +
        '<a class="trn-btn trn-btn-ghost" href="' + (j.capa || T.capa) + '">' +
          '<i class="bi bi-house"></i> Voltar</a>' +
        '<a class="trn-btn trn-btn-ok" id="trnCert" target="_blank" hidden>' +
          '<i class="bi bi-award"></i> Ver certificado</a>' +
      '</div>';

    if (j.codigo) {
      var cod = caixa.querySelector('.trn-codigo');
      cod.hidden = false;
      cod.querySelector('b').textContent = j.codigo;
    }
    if (j.certificado) {
      var a = caixa.querySelector('#trnCert');
      a.hidden = false;
      a.href = j.certificado;
    }
  }

  var trocarOriginal = player.aoTrocarSlide;
  player.aoTrocarSlide = function (i) {
    if (player.indice >= 0 && player.indice !== i) {
      enviarProgresso(player.indice, cumprida(player.indice), false);
    }
    trocarOriginal.apply(player, arguments);
  };

  if (window.TrEtapa) {
    window.TrEtapa.iniciar({
      player: player,
      apresentacao: APRES,
      config: APRES.treino || {},
      livre: !!T.livre,
      aluno: T.aluno || '',
      aoLiberar: registrarEtapa,
      aoFinalizar: finalizar,
      aoAvisar: function (msg) { aviso(msg, 'aviso'); }
    });
  }

  (function retomar() {
    if (T.previa) { player._atualizarControles(); return; }

    var jaVistos = T.concluidos || [];
    var k;

    if (conclusaoRegistrada) {
      for (k = 0; k < TOTAL; k++) {
        player.concluidos[k] = true;
        enviados[k] = true;
        if (window.TrEtapa) { window.TrEtapa.dispensar(k); }
      }
    } else {
      jaVistos.forEach(function (i) {
        if (i < 0 || i >= TOTAL) { return; }
        player.concluidos[i] = true;
        enviados[i] = true;
        if (window.TrEtapa) { window.TrEtapa.dispensar(i); }
      });
    }

    pintarBadge();

    var alvo = conclusaoRegistrada ? 0 : Math.min(Math.max(0, T.retomarEm || 0), TOTAL - 1);

    if (alvo > 0) {
      player.irPara(alvo, false);
      aviso('Retomando no slide ' + (alvo + 1) + ' de ' + TOTAL + '.');
      var txt = $('nvLegendaTexto');
      if (txt) {
        txt.innerHTML = 'Você parou aqui. Pressione <kbd>Iniciar</kbd> para continuar de onde parou.';
      }
    } else if (conclusaoRegistrada) {
      aviso('Você já concluiu este treinamento. Esta é uma revisão do conteúdo.');
    }

    player._atualizarControles();
    if (window.TrEtapa) { window.TrEtapa.atualizar(); }
  })();

  enviarProgresso(player.indice >= 0 ? player.indice : 0, cumprida(player.indice), false);

  setInterval(function () {
    if (overlayAberto || conclusaoRegistrada) { return; }
    if (player.indice !== TOTAL - 1 || !tudoCumprido()) { return; }
    var btn = $('trtContinuar');
    if (btn && !btn.classList.contains('trt-pulso')) {
      btn.classList.add('trt-pulso');
      setTimeout(function () { btn.classList.remove('trt-pulso'); }, 1400);
    }
  }, 6000);
})();
