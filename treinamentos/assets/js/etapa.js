(function (global) {
  'use strict';

  var player, APRES, cfg = {}, cb = {}, aluno = '';
  var dispensados = {}, jaLiberou = {};
  var tempoNoSlide = {}, rolouAteFim = {};
  var abasVistas = {}, materiais = {};
  var quizReabrindo = {}, planos = {}, destravados = {};
  var ui = {}, pronto = false, popAberto = false, pctGeral = 0;

  var ACOES = {
    quiz:      { rotulo: 'Responder a pergunta',      curto: 'Responda a pergunta',  icone: 'patch-question' },
    quizOk:    { rotulo: 'Acertar a pergunta',        curto: 'Acerte a pergunta',    icone: 'patch-question' },
    checklist: { rotulo: 'Marcar todos os itens',     curto: 'Marque os itens',      icone: 'check2-square' },
    revelar:   { rotulo: 'Abrir todos os cartões',    curto: 'Abra os cartões',      icone: 'layers' },
    abas:      { rotulo: 'Ver todas as abas',         curto: 'Veja todas as abas',   icone: 'segmented-nav' },
    material:  { rotulo: 'Abrir o material de apoio', curto: 'Abra o material',      icone: 'box-arrow-up-right' },
    narracao:  { rotulo: 'Ouvir a explicação',        curto: 'Ouça a explicação',    icone: 'soundwave' },
    leitura:   { rotulo: 'Ler todo o conteúdo',       curto: 'Role até o fim',       icone: 'arrow-down' },
    tempo:     { rotulo: 'Permanecer no slide',       curto: 'Aguarde um instante',  icone: 'stopwatch' },
    guiado:    { rotulo: 'Concluir o passo a passo',  curto: 'Siga os passos',       icone: 'signpost-2' }
  };

  var INTERATIVOS = ['quiz', 'checklist', 'revelar', 'abas', 'botao'];

  function $(id) { return document.getElementById(id); }
  function elSlide(i) { return player && player.elsSlides ? player.elsSlides[i] : null; }
  function conteudo(i) { var e = elSlide(i); return e ? e.querySelector('.nv-slide-conteudo') : null; }
  function totalSlides() { return APRES.slides.length; }

  function exigenciaDa(i) {
    var e = (APRES.slides[i] || {}).exigencia || 'herdar';
    return e === 'herdar' ? (cfg.interacao || 'todas') : e;
  }
  function falasDe(i) { return ((APRES.slides[i] || {}).dialogos || []).length; }
  function falasOuvidas(i) {
    if (player.concluidos[i]) { return falasDe(i); }
    var k = player.ouvidas[i];
    return (k == null) ? 0 : k + 1;
  }

  function plano(i) {
    if (planos[i]) { return planos[i]; }
    var el = elSlide(i);
    if (!el) { return []; }

    var exig = exigenciaDa(i);
    if (exig === 'nenhuma') { planos[i] = []; return planos[i]; }

    var brutos = Array.prototype.filter.call(el.querySelectorAll('[data-bloco]'), function (b) {
      var t = b.getAttribute('data-bloco');
      if (b.getAttribute('data-gauto') === '1') { return false; }
      if (b.getAttribute('data-guiado') === '1') { return exig !== 'quiz' || t === 'quiz'; }
      if (INTERATIVOS.indexOf(t) < 0) { return false; }
      if (t === 'botao' && !b.querySelector('a[href]')) { return false; }
      if (t === 'abas' && b.querySelectorAll('.trb-aba').length < 2) { return false; }
      if (t === 'quiz' && !b.querySelectorAll('.trb-quiz-op').length) { return false; }
      if (exig === 'quiz' && t !== 'quiz') { return false; }
      return true;
    });

    var falas = falasDe(i);
    var imediato = (cfg.momento === 'imediato') || falas === 0;

    planos[i] = brutos.map(function (b, k) {
      var apos = parseInt(b.getAttribute('data-apos'), 10);
      if (!(apos > 0)) { apos = (imediato || b.getAttribute('data-bloco') === 'quiz') ? 0 : falas; }
      return {
        el: b, tipo: b.getAttribute('data-bloco'), apos: apos, n: k,
        guiado: b.getAttribute('data-guiado') === '1'
      };
    });
    return planos[i];
  }

  function destravado(i, item) {
    if (cfg.livre) { return true; }
    var chave = i + ':' + item.n;
    if (destravados[chave]) { return true; }
    var ok = dispensados[i] || item.apos <= 0 || falasOuvidas(i) >= item.apos;
    if (ok) { destravados[chave] = true; }
    return ok;
  }

  function marcarVisto(el) { el.__trtVisto = true; }

  var ITENS_GUIA = {
    abas: '.trb-aba', revelar: '.trb-card', checklist: '.trb-check',
    passos: '.trb-passo', linhaTempo: '.trb-marco'
  };

  function itensGuiados(b) {
    var sel = ITENS_GUIA[b.getAttribute('data-bloco')];
    return sel ? Array.prototype.slice.call(b.querySelectorAll(sel)) : [];
  }

  function feito(item, i) {
    var b = item.el;

    if (item.guiado) {
      var todos = itensGuiados(b);
      if (!todos.length) { return true; }
      return todos.every(function (o) { return o.classList.contains('trg-ok'); });
    }

    if (item.tipo === 'quiz') {
      var ops = Array.prototype.slice.call(b.querySelectorAll('.trb-quiz-op'));
      var respondeu = ops.some(function (o) {
        return o.classList.contains('is-certa') || o.classList.contains('is-errada');
      });
      if (!cfg.quizAcerto) { return respondeu; }

      var todasCertas = ops.filter(function (o) { return o.dataset.ok === '1'; })
                           .every(function (o) { return o.classList.contains('is-certa'); });
      var semErro = !ops.some(function (o) { return o.classList.contains('is-errada'); });
      var acertou = respondeu && todasCertas && semErro;
      if (respondeu && !acertou) { reabrirQuiz(b, i + ':' + item.n); }
      return acertou;
    }

    if (item.tipo === 'checklist') {
      var itens = b.querySelectorAll('.trb-check');
      return itens.length > 0 &&
             b.querySelectorAll('.trb-check.is-marcado').length >= itens.length;
    }

    if (item.tipo === 'revelar') {
      var cards = Array.prototype.slice.call(b.querySelectorAll('.trb-card'));
      cards.forEach(function (c) { if (c.classList.contains('is-aberto')) { marcarVisto(c); } });
      return cards.length > 0 && cards.every(function (c) { return !!c.__trtVisto; });
    }

    if (item.tipo === 'abas') {
      var chave = i + ':' + item.n;
      if (!abasVistas[chave]) { abasVistas[chave] = {}; }
      var ativa = b.querySelector('.trb-aba.is-ativa');
      if (ativa) { abasVistas[chave][ativa.dataset.i] = true; }
      return Object.keys(abasVistas[chave]).length >= b.querySelectorAll('.trb-aba').length;
    }

    if (item.tipo === 'botao') { return !!materiais[i + ':' + item.n]; }
    return true;
  }

  function contagem(item, i) {
    var b = item.el;
    if (item.guiado) {
      var todos = itensGuiados(b);
      return todos.filter(function (o) { return o.classList.contains('trg-ok'); }).length +
             '/' + todos.length;
    }
    if (item.tipo === 'checklist') {
      return b.querySelectorAll('.trb-check.is-marcado').length + '/' +
             b.querySelectorAll('.trb-check').length;
    }
    if (item.tipo === 'revelar') {
      var cards = Array.prototype.slice.call(b.querySelectorAll('.trb-card'));
      var vistos = cards.filter(function (c) { return !!c.__trtVisto; }).length;
      return vistos + '/' + cards.length;
    }
    if (item.tipo === 'abas') {
      return Object.keys(abasVistas[i + ':' + item.n] || {}).length + '/' +
             b.querySelectorAll('.trb-aba').length;
    }
    return '';
  }

  function acaoDe(item) {
    if (item.guiado) { return ACOES.guiado; }
    if (item.tipo === 'quiz') { return cfg.quizAcerto ? ACOES.quizOk : ACOES.quiz; }
    if (item.tipo === 'botao') { return ACOES.material; }
    return ACOES[item.tipo] || ACOES.quiz;
  }

  function reabrirQuiz(q, chave) {
    if (quizReabrindo[chave]) { return; }
    quizReabrindo[chave] = true;
    q.classList.add('trt-errou');
    setTimeout(function () {
      Array.prototype.forEach.call(q.querySelectorAll('.trb-quiz-op'), function (o) {
        o.classList.remove('is-certa', 'is-errada', 'is-gabarito');
        var ic = o.querySelector('.trb-quiz-icone');
        if (ic) { ic.className = 'bi trb-quiz-icone'; }
      });
      q.classList.remove('is-respondido', 'trt-errou');
      quizReabrindo[chave] = false;
      atualizar();
    }, 2400);
  }

  function pintarBloco(item, i) {
    var b = item.el;
    var aberto = destravado(i, item);
    var ok = aberto && feito(item, i);
    var acao = acaoDe(item);
    var cont = contagem(item, i);

    b.classList.add('trt-bloco');
    b.classList.toggle('is-aguardando', !aberto && item.tipo !== 'quiz');
    b.classList.toggle('is-vez', aberto && !ok && !item.guiado);
    b.classList.toggle('is-guiado', !!item.guiado);
    b.classList.toggle('is-feito', ok);

    var tag = b.__trtTag;
    if (!tag || tag.parentNode !== b) {
      tag = document.createElement('div');
      tag.className = 'trt-tag';
      tag.innerHTML = '<i></i><span></span><b></b>';
      b.insertBefore(tag, b.firstChild);
      b.__trtTag = tag;
    }

    var ic = tag.querySelector('i');
    var tx = tag.querySelector('span');
    var nm = tag.querySelector('b');

    if (!aberto) {
      ic.className = 'bi bi-lock-fill';
      tx.textContent = 'Atividade — o instrutor libera no momento certo';
      nm.textContent = '';
    } else if (!ok) {
      ic.className = 'bi bi-hand-index-thumb-fill';
      tx.textContent = 'Sua vez · ' + acao.curto;
      nm.textContent = cont;
    } else {
      ic.className = 'bi bi-check-circle-fill';
      tx.textContent = 'Concluída';
      nm.textContent = cont;
    }

    var eraTravado = b.__trtTravadoAntes;
    b.__trtTravadoAntes = !aberto;

    if (aberto && !ok && eraTravado === true) {
      b.classList.add('trt-surgiu');
      setTimeout(function () { b.classList.remove('trt-surgiu'); }, 1600);
      avisar('Sua vez: ' + acao.curto.toLowerCase() + '.');
    }

    return { aberto: aberto, ok: ok, acao: acao, detalhe: cont, el: b };
  }

  function requisitosDe(i) {
    if (dispensados[i] || !elSlide(i)) { return []; }
    var reqs = [];

    if (falasDe(i)) {
      reqs.push({
        chave: 'narracao', rotulo: ACOES.narracao.rotulo, curto: ACOES.narracao.curto,
        icone: ACOES.narracao.icone, ok: !!player.concluidos[i], alvo: null
      });
    }

    if (cfg.tempoMin > 0) {
      var gasto = tempoNoSlide[i] || 0;
      reqs.push({
        chave: 'tempo', rotulo: ACOES.tempo.rotulo, curto: ACOES.tempo.curto,
        icone: ACOES.tempo.icone, ok: gasto >= cfg.tempoMin,
        detalhe: Math.max(0, cfg.tempoMin - gasto) + 's', alvo: null
      });
    }

    if (cfg.leitura) {
      var caixa = conteudo(i);
      if (caixa && caixa.scrollHeight > caixa.clientHeight + 12) {
        reqs.push({
          chave: 'leitura', rotulo: ACOES.leitura.rotulo, curto: ACOES.leitura.curto,
          icone: ACOES.leitura.icone, ok: !!rolouAteFim[i], alvo: caixa
        });
      }
    }

    plano(i).forEach(function (item) {
      var s = pintarBloco(item, i);
      reqs.push({
        chave: item.tipo, rotulo: s.acao.rotulo, curto: s.acao.curto,
        icone: s.acao.icone, ok: s.ok, aguardando: !s.aberto,
        detalhe: s.detalhe, alvo: s.el
      });
    });

    return reqs;
  }

  function estado(i) {
    var reqs = requisitosDe(i);
    var pend = reqs.filter(function (r) { return !r.ok; });
    return { requisitos: reqs, pendentes: pend.length, liberado: pend.length === 0 };
  }

  function liberado(i) {
    if (cfg.livre || dispensados[i]) { return true; }
    return estado(i).liberado;
  }

  function montarUI() {
    document.body.classList.add('trt-modo');

    player.autoAvancar = false;
    var chk = $('chkAuto');
    if (chk) { chk.checked = false; }

    var flut = document.createElement('div');
    flut.className = 'trt-flutuante';
    var btnR = $('btnRoteiro');
    var btnT = $('btnTela');
    if (btnR) { flut.appendChild(btnR); }
    if (btnT) { flut.appendChild(btnT); }
    document.body.appendChild(flut);

    var barra = document.createElement('div');
    barra.className = 'trt-barra';
    barra.innerHTML =
      '<div class="trt-narracao" id="trtNarracao"></div>' +
      '<div class="trt-centro">' +
        '<div class="trt-titulo-linha">' +
          '<span class="trt-num" id="trtNum"></span>' +
          '<b class="trt-titulo" id="trtTitulo"></b>' +
        '</div>' +
        '<div class="trt-trilha" id="trtTrilha" role="group" aria-label="Slides"></div>' +
      '</div>' +
      '<div class="trt-fim">' +
        '<button type="button" class="trt-aviso" id="trtAviso" hidden>' +
          '<i class="bi bi-exclamation-circle"></i><span></span></button>' +
        '<button type="button" class="trt-nav" id="trtVoltar" title="Slide anterior">' +
          '<i class="bi bi-chevron-left"></i></button>' +
        '<button type="button" class="trt-continuar" id="trtContinuar">' +
          '<span class="trt-continuar-txt">Continuar</span>' +
          '<i class="bi bi-chevron-right"></i></button>' +
        '<div class="trt-pop" id="trtPop" hidden></div>' +
      '</div>';

    document.body.appendChild(barra);

    var linha = document.querySelector('.nv-controles-linha');
    if (linha) { $('trtNarracao').appendChild(linha); }

    ui.barra = barra;
    ui.num = $('trtNum');
    ui.titulo = $('trtTitulo');
    ui.trilha = $('trtTrilha');
    ui.continuar = $('trtContinuar');
    ui.continuarTxt = barra.querySelector('.trt-continuar-txt');
    ui.voltar = $('trtVoltar');
    ui.aviso = $('trtAviso');
    ui.avisoTxt = ui.aviso.querySelector('span');
    ui.pop = $('trtPop');

    ui.voltar.addEventListener('click', function () { player.slideAnterior(); });
    ui.continuar.addEventListener('click', aoContinuar);
    ui.aviso.addEventListener('click', function (ev) { ev.stopPropagation(); alternarPop(); });

    document.addEventListener('click', function (ev) {
      if (popAberto && !ev.target.closest('.trt-pop') && !ev.target.closest('.trt-aviso')) {
        fecharPop();
      }
    });

    montarTrilha();
    prepararRoteiro();
  }

  function montarTrilha() {
    ui.trilha.innerHTML = '';
    APRES.slides.forEach(function (s, i) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'trt-passo';
      b.setAttribute('aria-label', 'Slide ' + (i + 1));
      b.addEventListener('click', function () { irSeLiberado(i); });
      ui.trilha.appendChild(b);
    });
  }

  function irSeLiberado(i) {
    if (i === player.indice) { return; }
    if (cfg.livre || i < player.indice || dispensados[i] || player.concluidos[i]) {
      player.irPara(i, false);
    } else {
      avisar('Conclua os slides anteriores para chegar até aqui.');
    }
  }

  function prepararRoteiro() {
    var head = document.querySelector('#drawerRoteiro .nv-drawer-head span');
    if (head) { head.innerHTML = '<i class="bi bi-list-ul"></i> Conteúdo do treinamento'; }

    player._montarRoteiro = function () { desenharRoteiro(); };
    player._marcarRoteiro = function (k) {
      try { document.dispatchEvent(new CustomEvent('nv:fala', { detail: { indice: k } })); } catch (e) {}
    };
    desenharRoteiro();
  }

  function desenharRoteiro() {
    var alvo = player.ui && player.ui.roteiro;
    if (!alvo) { return; }
    alvo.innerHTML = '';

    var topo = document.createElement('div');
    topo.className = 'trt-roteiro-topo';
    topo.innerHTML =
      '<div class="trt-roteiro-aluno"><i class="bi bi-person-badge"></i><span></span></div>' +
      '<div class="trt-roteiro-pct"><b></b> concluído</div>' +
      '<div class="trt-roteiro-barra"><i></i></div>';
    topo.querySelector('.trt-roteiro-aluno span').textContent = aluno || '';
    topo.querySelector('.trt-roteiro-pct b').textContent = pctGeral + '%';
    topo.querySelector('.trt-roteiro-barra i').style.width = pctGeral + '%';
    if (!aluno) { topo.querySelector('.trt-roteiro-aluno').remove(); }
    alvo.appendChild(topo);

    APRES.slides.forEach(function (s, i) {
      var feitoSlide = dispensados[i] || (i !== player.indice && player.concluidos[i]);
      var atual = i === player.indice;
      var travado = !feitoSlide && !atual && i > player.indice;

      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'trt-roteiro-item' +
        (atual ? ' is-atual' : '') + (feitoSlide ? ' is-ok' : '') + (travado ? ' is-travado' : '');
      b.innerHTML =
        '<i class="bi bi-' + (feitoSlide ? 'check-circle-fill' : (travado ? 'lock-fill' : 'play-circle')) + '"></i>' +
        '<span class="trt-roteiro-n"></span>' +
        '<span class="trt-roteiro-t"></span>';
      b.querySelector('.trt-roteiro-n').textContent = (i + 1) + '.';
      b.querySelector('.trt-roteiro-t').textContent = s.titulo || 'Slide ' + (i + 1);
      b.addEventListener('click', function () { irSeLiberado(i); });
      alvo.appendChild(b);
    });
  }

  function alternarPop() {
    if (popAberto) { fecharPop(); return; }
    var pend = estado(player.indice).requisitos.filter(function (r) { return !r.ok; });
    if (!pend.length) { return; }

    ui.pop.innerHTML = '<div class="trt-pop-titulo">Para concluir este slide</div>';
    pend.forEach(function (r) {
      var li = document.createElement('button');
      li.type = 'button';
      li.className = 'trt-pop-item' + (r.aguardando ? ' is-aguardando' : '');
      li.innerHTML = '<i class="bi bi-' + (r.aguardando ? 'lock-fill' : r.icone) + '"></i>' +
                     '<span></span>' + (r.detalhe ? '<b></b>' : '');
      li.querySelector('span').textContent = r.aguardando
        ? r.rotulo + ' (durante a explicação)' : r.rotulo;
      if (r.detalhe) { li.querySelector('b').textContent = r.detalhe; }
      if (r.alvo && !r.aguardando) {
        li.classList.add('is-clicavel');
        li.addEventListener('click', function () { fecharPop(); destacar(r.alvo); });
      }
      ui.pop.appendChild(li);
    });
    ui.pop.hidden = false;
    popAberto = true;
  }

  function fecharPop() { ui.pop.hidden = true; popAberto = false; }

  function aoContinuar() {
    var i = player.indice;
    var e = estado(i);

    if (!e.liberado) {
      var pend = e.requisitos.filter(function (r) { return !r.ok; });
      var acionavel = pend.filter(function (r) { return r.alvo && !r.aguardando; });
      if (acionavel.length) { destacar(acionavel[0].alvo); } else { alternarPop(); }
      avisar(pend.length === 1 ? pend[0].curto + ' para continuar.'
                               : 'Faltam ' + pend.length + ' itens neste slide.');
      return;
    }

    if (i >= totalSlides() - 1) { if (cb.aoFinalizar) { cb.aoFinalizar(); } return; }
    player.irPara(i + 1, true);
  }

  function destacar(el) {
    if (!el) { return; }
    try { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}
    el.classList.remove('trt-chamando');
    void el.offsetWidth;
    el.classList.add('trt-chamando');
    setTimeout(function () { el.classList.remove('trt-chamando'); }, 1500);
  }

  function avisar(msg) { if (cb.aoAvisar && msg) { cb.aoAvisar(msg); } }

  function pintarComandosFala(i) {
    var btnPlay = $('btnPlay');
    if (!btnPlay) { return; }

    var temFalas = falasDe(i) > 0;
    var tocando = !!player.emReproducao;
    var terminou = !!player.concluidos[i] && !tocando;
    var icone = btnPlay.querySelector('i');

    btnPlay.disabled = !temFalas;
    btnPlay.classList.toggle('is-rever', terminou);

    if (!temFalas) {
      btnPlay.title = 'Este slide não tem narração';
      return;
    }

    if (terminou) {
      if (icone) { icone.className = 'bi bi-arrow-counterclockwise'; }
      btnPlay.title = 'Rever este slide desde o início';
      var txt = $('btnPlayTxt');
      if (txt) { txt.textContent = 'Rever slide'; }
      return;
    }

    btnPlay.title = tocando
      ? ((player.motor && player.motor.pausado) ? 'Retomar' : 'Pausar')
      : (player.iniciado ? 'Reproduzir este slide' : 'Iniciar');
  }

  function pintar() {
    if (!pronto || player.indice < 0) { return; }

    var i = player.indice;
    var total = totalSlides();
    var e = estado(i);
    var ultimo = i >= total - 1;

    ui.num.textContent = 'Slide ' + (i + 1) + '/' + total;
    ui.titulo.textContent = (APRES.slides[i] || {}).titulo || '';

    Array.prototype.forEach.call(ui.trilha.children, function (b, k) {
      var ok = dispensados[k] || (k !== i && player.concluidos[k]);
      b.classList.toggle('is-atual', k === i);
      b.classList.toggle('is-ok', !!ok && k !== i);
      b.title = (k + 1) + '. ' + ((APRES.slides[k] || {}).titulo || '');
    });

    ui.voltar.disabled = i <= 0;

    pintarComandosFala(i);

    ui.continuar.classList.toggle('is-bloqueado', !e.liberado);
    ui.continuar.classList.toggle('is-final', ultimo && e.liberado);
    ui.continuar.setAttribute('aria-disabled', e.liberado ? 'false' : 'true');
    ui.continuarTxt.textContent = e.liberado ? (ultimo ? 'Finalizar' : 'Continuar') : 'Continuar';

    var pend = cfg.livre ? [] : e.requisitos.filter(function (r) { return !r.ok; });
    if (!pend.length) {
      ui.aviso.hidden = true;
      fecharPop();
    } else {
      var ativa = pend.filter(function (r) { return !r.aguardando; });
      ui.aviso.hidden = false;
      ui.avisoTxt.textContent = ativa.length
        ? (ativa.length === 1 ? ativa[0].curto : ativa.length + ' atividades pendentes')
        : 'Acompanhe a explicação';
      ui.aviso.classList.toggle('is-espera', !ativa.length);
    }

    ui.continuar.classList.toggle('is-chamando', e.liberado && !player.emReproducao);

    if (e.liberado && !jaLiberou[i]) {
      jaLiberou[i] = true;
      ui.continuar.classList.add('trt-pulso');
      setTimeout(function () { ui.continuar.classList.remove('trt-pulso'); }, 1400);
      document.body.classList.remove('is-chrome-oculto');
      if (cb.aoLiberar) { cb.aoLiberar(i); }
    }
  }

  function ligarEscutas() {
    document.addEventListener('click', function (ev) {
      if (!ev.target.closest) { return; }

      var aba = ev.target.closest('.trb-abas .trb-aba');
      if (aba) { registrarAba(aba); }

      var card = ev.target.closest('.trb-revelar .trb-card');
      if (card) { marcarVisto(card); }

      var link = ev.target.closest('.trb-botao-wrap a[href]');
      if (link) {
        var wrap = link.closest('[data-bloco]');
        plano(player.indice).forEach(function (it) {
          if (it.el === wrap) { materiais[player.indice + ':' + it.n] = true; }
        });
      }

      if (ev.target.closest('.trb')) { setTimeout(atualizar, 40); }
    }, true);

    document.addEventListener('scroll', function (ev) {
      var c = ev.target;
      if (!c || !c.classList || !c.classList.contains('nv-slide-conteudo')) { return; }
      if (c.scrollTop + c.clientHeight >= c.scrollHeight - 24) {
        rolouAteFim[player.indice] = true;
        atualizar();
      }
    }, true);

    setInterval(function () {
      if (document.visibilityState !== 'visible' || player.indice < 0) { return; }
      tempoNoSlide[player.indice] = (tempoNoSlide[player.indice] || 0) + 1;
      if (cfg.tempoMin > 0 && tempoNoSlide[player.indice] <= cfg.tempoMin + 1) { pintar(); }
    }, 1000);

    setInterval(function () { if (player.emReproducao) { atualizar(); } }, 900);

    window.addEventListener('resize', atualizar);
  }

  function registrarAba(aba) {
    var caixa = aba.closest('.trb-abas');
    if (!caixa) { return; }
    plano(player.indice).forEach(function (it) {
      if (it.el !== caixa) { return; }
      var chave = player.indice + ':' + it.n;
      if (!abasVistas[chave]) { abasVistas[chave] = {}; }
      abasVistas[chave][aba.dataset.i] = true;
    });
  }

  function atualizar() { if (pronto) { pintar(); } }

  function amarrar() {
    player.podeAvancar = function () {
      return this.indice < totalSlides() - 1 && liberado(this.indice);
    };

    var origProximo = player.proximoSlide;
    player.proximoSlide = function (auto) {
      if (!liberado(this.indice)) {
        if (auto) { avisar('Conclua a atividade deste slide para continuar.'); }
        atualizar();
        return;
      }
      origProximo.call(this, auto);
    };

    var origTrocar = player.aoTrocarSlide;
    player.aoTrocarSlide = function (i) {
      origTrocar.apply(player, arguments);
      fecharPop();
      var c = conteudo(i);
      if (c && c.scrollHeight <= c.clientHeight + 12) { rolouAteFim[i] = true; }
      setTimeout(function () { atualizar(); desenharRoteiro(); }, 80);
    };

    var origControles = player._atualizarControles;
    player._atualizarControles = function () {
      origControles.apply(this, arguments);
      atualizar();
    };
  }

  function iniciar(opcoes) {
    player = opcoes.player;
    APRES = opcoes.apresentacao || global.TR_APRESENTACAO;
    cfg = opcoes.config || {};
    aluno = opcoes.aluno || '';
    cb = {
      aoLiberar: opcoes.aoLiberar,
      aoFinalizar: opcoes.aoFinalizar,
      aoAvisar: opcoes.aoAvisar
    };

    cfg.interacao = cfg.interacao || 'todas';
    cfg.momento = cfg.momento || 'narracao';
    cfg.livre = !!opcoes.livre;
    cfg.tempoMin = Math.max(0, parseInt(cfg.tempoMin, 10) || 0);

    montarUI();
    amarrar();
    ligarEscutas();

    pronto = true;
    atualizar();
  }

  global.TrEtapa = {
    iniciar: iniciar,
    estado: estado,
    liberado: liberado,
    atualizar: atualizar,
    dispensar: function (i) { dispensados[i] = true; jaLiberou[i] = true; },
    progresso: function (pct) {
      pctGeral = Math.max(0, Math.min(100, parseInt(pct, 10) || 0));
      if (pronto) { desenharRoteiro(); }
    }
  };
})(window);
