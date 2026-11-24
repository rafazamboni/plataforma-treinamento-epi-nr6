(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var APRES = window.TR_APRESENTACAO;
  var OPT = window.TR_PLAYER_OPT || {};
  var CHAVE_PREF = 'tr_pref_player';

  if (!APRES || !APRES.slides || !APRES.slides.length) { return; }

  function toast(msg, tipo) {
    var el = document.createElement('div');
    el.className = 'nv-toast' + (tipo ? ' ' + tipo : '');
    el.textContent = msg;
    $('nvToastArea').appendChild(el);
    setTimeout(function () {
      el.style.transition = 'opacity .3s ease';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 320);
    }, 3800);
  }

  function lerPref() {
    try { return JSON.parse(window.localStorage.getItem(CHAVE_PREF) || '{}') || {}; }
    catch (e) { return {}; }
  }
  function gravarPref(o) {
    try { window.localStorage.setItem(CHAVE_PREF, JSON.stringify(o)); } catch (e) {}
  }

  var pref = lerPref();

  var instrutor = new window.Instrutor3D($('nvInstrutor3d'), {
    url: APRES.avatar.url,
    nome: APRES.avatar.nome,
    camera: APRES.avatar.camera,
    humor: APRES.avatar.humor,
    orientacao: APRES.avatar.orientacao || {}
  });
  if (instrutor.orientacaoBase) { instrutor.orientacaoBase(APRES.avatar.orientacao || {}); }
  if (APRES.avatar.orientacao && APRES.avatar.orientacao.olharPadrao) {
    instrutor.olhar(APRES.avatar.orientacao.olharPadrao);
  }
  instrutor.vincularBalao($('nvBalao'), $('nvBalaoTexto'));

  var fala = new window.Fala();
  fala.configurar({
    rate: pref.rate || 1,
    taxaAudio: pref.rate || 1,
    vozURI: '',
    mudo: !!pref.mudo
  });
  fala.usarInstrutor(instrutor);

  var palco = new window.Palco({
    stage: $('nvSlideWrap'),
    wrap: $('nvSlideWrap'),
    foco: $('nvFoco'),
    focoRing: $('nvFocoRing'),
    focoLabel: $('nvFocoLabel'),
    quadro: $('nvQuadro'),
    quadroTitulo: $('nvQuadroTitulo'),
    quadroBody: $('nvQuadroBody')
  });

  var ultimoPing = 0;
  function enviarProgresso(indice) {
    if (!OPT.ping) { return; }
    var slide = indice + 1;
    if (slide <= ultimoPing) { return; }
    ultimoPing = slide;
    var corpo = new FormData();
    corpo.append('slide', String(slide));
    corpo.append('concluido', slide >= APRES.slides.length ? '1' : '0');
    fetch(OPT.ping, { method: 'POST', body: corpo, credentials: 'same-origin' }).catch(function () {});
  }

  var scrub = $('nvScrub');
  var scrubSegs = [];

  function montarScrub(indice) {
    var slide = APRES.slides[indice] || {};
    var falas = slide.dialogos || [];
    scrub.innerHTML = '';
    scrubSegs = falas.map(function (d, k) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'nv-scrub-seg';
      b.dataset.i = k;
      b.innerHTML = '<i></i>';
      b.addEventListener('click', function () {
        if (!player.irParaFala(k)) {
          toast('Esta parte ainda não foi apresentada. Ouça o slide até o fim para liberar a navegação.', 'aviso');
        }
      });
      scrub.appendChild(b);
      return b.firstChild;
    });
    if (!falas.length) {
      scrub.innerHTML = '<span class="nv-scrub-vazio"></span>';
    }
    marcarTravas();
  }

  function marcarTravas() {
    scrubSegs.forEach(function (fill, k) {
      fill.parentNode.classList.toggle('is-travado', !player.falaLiberada(k));
      fill.parentNode.title = player.falaLiberada(k)
        ? (k + 1) + '. ' + String((APRES.slides[player.indice].dialogos[k] || {}).texto || '').slice(0, 90)
        : 'Disponível depois de ouvir o slide inteiro';
    });
  }

  function pintarScrub(fracao) {
    if (!scrubSegs.length) { return; }
    var f = parseFloat(fracao);
    if (isNaN(f)) { f = 0; }
    f = Math.max(0, Math.min(1, f));
    var atual = (player && player.motor && typeof player.motor.indice === 'number')
      ? player.motor.indice : -1;
    scrubSegs.forEach(function (fill, k) {
      var v = k < atual ? 1 : (k === atual ? f : 0);
      fill.style.width = (v * 100) + '%';
    });
  }

  var progressoFala = { style: {} };
  Object.defineProperty(progressoFala.style, 'width', {
    configurable: true,
    get: function () { return ''; },
    set: function (v) { pintarScrub(parseFloat(v) / 100); }
  });

  var progressoSlides = { style: {} };

  var player = new window.Player({
    apresentacao: APRES,
    instrutor: instrutor,
    fala: fala,
    palco: palco,
    aoTrocarSlide: function (i) { enviarProgresso(i); montarScrub(i); },
    ui: {
      wrap: $('nvSlideWrap'),
      steps: null,
      roteiro: $('nvRoteiroLista'),
      legenda: $('nvLegenda'),
      texto: $('nvLegendaTexto'),
      nome: $('nvLegendaNome'),
      contadorDialogo: $('nvDialogoContador'),
      progressoDialogo: progressoFala,
      contador: $('nvContador'),
      progressoBarra: progressoSlides,
      btnAnterior: $('btnAnterior'),
      btnProximo: $('btnProximo'),
      btnPlay: $('btnPlay'),
      btnPlayTxt: $('btnPlayTxt'),
      btnRepetir: $('btnRepetir'),
      btnPular: $('btnPular')
    }
  });

  if (window.TrBlocos) { window.TrBlocos.ativar(document); }

  var querInstrutor = pref.instrutor3d !== false;

  function marcarBotaoInstrutor(ligado) {
    $('btnInstrutor').classList.toggle('is-ativo', ligado);
    $('btnInstrutor').title = ligado ? 'Ocultar instrutor' : 'Exibir instrutor';
    $('btnInstrutor').querySelector('i').className = ligado ? 'bi bi-person-fill' : 'bi bi-person-slash';
  }

  function carregarInstrutor() {
    if (!APRES.avatar.url) {
      $('nvInstrutor').classList.add('is-oculto');
      return Promise.resolve(false);
    }
    var aviso = $('nvInstrutorCarregando');
    aviso.hidden = false;
    return instrutor.iniciar(function (frac) {
      $('nvInstrutorCarregandoTxt').textContent = 'Preparando instrutor… ' + Math.round(frac * 100) + '%';
    }).then(function (ok) {
      aviso.hidden = true;
      if (!ok) {
        $('nvInstrutor').classList.add('is-oculto');
        toast(instrutor.erro || 'O instrutor 3D não pôde ser carregado neste dispositivo. A narração e os slides continuam normalmente.', 'aviso');
      }
      return ok;
    });
  }

  marcarBotaoInstrutor(querInstrutor);
  if (querInstrutor) { carregarInstrutor(); }
  else { $('nvInstrutor').classList.add('is-oculto'); }

  $('btnInstrutor').addEventListener('click', function () {
    var ligado = pref.instrutor3d === false;
    pref.instrutor3d = ligado;
    gravarPref(pref);
    marcarBotaoInstrutor(ligado);
    if (ligado) {
      $('nvInstrutor').classList.remove('is-oculto');
      if (!instrutor.ativo) { carregarInstrutor(); }
    } else {
      $('nvInstrutor').classList.add('is-oculto');
    }
  });

  $('btnAnterior').addEventListener('click', function () { player.slideAnterior(); });
  $('btnProximo').addEventListener('click', function () { player.proximoSlide(); });
  $('btnPlay').addEventListener('click', function () { player.alternarReproducao(); });
  $('btnRepetir').addEventListener('click', function () { player.repetir(); });
  $('btnPular').addEventListener('click', function () { player.pular(); });
  $('chkAuto').addEventListener('change', function () { player.autoAvancar = this.checked; });
  $('nvQuadroClose').addEventListener('click', function () { palco.fecharQuadro(); });
  player.autoAvancar = $('chkAuto').checked;

  document.addEventListener('keydown', function (ev) {
    if (/^(INPUT|SELECT|TEXTAREA)$/.test(ev.target.tagName || '')) { return; }
    if (ev.key === 'ArrowRight') {
      ev.preventDefault();
      if (player.emReproducao && player.slideConcluido()) { player.pular(); }
      else { player.proximoSlide(); }
    }
    if (ev.key === 'ArrowLeft')  { ev.preventDefault(); player.slideAnterior(); }
    if (ev.key === ' ')          { ev.preventDefault(); player.alternarReproducao(); }
    if (ev.key === 'Escape')     { fecharTudo(); }
  });

  function iconeVolume(v, mudo) {
    if (mudo || v <= 0) { return 'bi bi-volume-mute-fill'; }
    if (v < 0.34) { return 'bi bi-volume-off-fill'; }
    if (v < 0.7)  { return 'bi bi-volume-down-fill'; }
    return 'bi bi-volume-up-fill';
  }

  function aplicarVolume(avisar) {
    var v = pref.volume == null ? 1 : Number(pref.volume);
    var mudo = v <= 0;
    fala.configurar({ volume: v, mudo: mudo });
    pref.mudo = mudo;
    $('cfgVolume').value = Math.round(v * 100);
    $('cfgVolumeVal').textContent = Math.round(v * 100) + '%';
    $('btnMudo').querySelector('i').className = iconeVolume(v, mudo);
    $('btnMudo').classList.toggle('is-ativo', mudo);
    $('btnMudo').title = mudo ? 'Som desligado' : 'Volume ' + Math.round(v * 100) + '%';
    if (avisar && mudo) { toast('Narração sem som. A legenda segue no mesmo ritmo.'); }
  }
  aplicarVolume(false);

  $('btnMudo').addEventListener('click', function (ev) {
    ev.stopPropagation();
    var m = $('nvVolumeMenu');
    if (m.hidden) { m.hidden = false; return; }
    if (pref.volume > 0) {
      pref.volumeAnterior = pref.volume;
      pref.volume = 0;
    } else {
      pref.volume = pref.volumeAnterior || 1;
    }
    gravarPref(pref);
    aplicarVolume(true);
  });
  $('cfgVolume').addEventListener('input', function () {
    pref.volume = Number(this.value) / 100;
    if (pref.volume > 0) { pref.volumeAnterior = pref.volume; }
    gravarPref(pref);
    aplicarVolume(false);
  });
  $('nvVolumeMenu').addEventListener('click', function (ev) { ev.stopPropagation(); });
  document.addEventListener('click', function (ev) {
    if (!ev.target.closest('#nvVolume')) { $('nvVolumeMenu').hidden = true; }
  });

  $('btnTela').addEventListener('click', function () {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen().catch(function () {
        toast('Tela cheia indisponível neste navegador.', 'aviso');
      });
    } else { document.exitFullscreen(); }
  });

  function abrirDrawer(id) {
    fecharTudo();
    $(id).classList.add('is-aberto');
    $(id).setAttribute('aria-hidden', 'false');
    $('nvBackdrop').classList.add('is-ativo');
  }
  function fecharTudo() {
    ['drawerRoteiro'].forEach(function (id) {
      $(id).classList.remove('is-aberto');
      $(id).setAttribute('aria-hidden', 'true');
    });
    $('nvBackdrop').classList.remove('is-ativo');
  }
  $('btnRoteiro').addEventListener('click', function () { abrirDrawer('drawerRoteiro'); });
  $('nvBackdrop').addEventListener('click', fecharTudo);
  Array.prototype.forEach.call(document.querySelectorAll('[data-drawer-close]'), function (b) {
    b.addEventListener('click', fecharTudo);
  });

  var VELOC = [0.75, 0.9, 1, 1.15, 1.3, 1.5];

  function aplicarVelocidade(v, avisar) {
    v = VELOC.indexOf(v) >= 0 ? v : 1;
    fala.configurar({ rate: v, taxaAudio: v });
    pref.rate = v;
    gravarPref(pref);
    $('btnVelocTxt').textContent = (v === 1 ? '1×' : String(v).replace('.', ',') + '×');
    $('btnVeloc').classList.toggle('is-ativo', v !== 1);
    Array.prototype.forEach.call($('nvVelocMenu').children, function (b) {
      b.classList.toggle('is-atual', parseFloat(b.dataset.veloc) === v);
    });
    if (avisar) { toast('Velocidade da narração: ' + $('btnVelocTxt').textContent); }
  }
  aplicarVelocidade(parseFloat(pref.rate) || 1, false);

  $('btnVeloc').addEventListener('click', function (ev) {
    ev.stopPropagation();
    var m = $('nvVelocMenu');
    m.hidden = !m.hidden;
  });
  $('nvVelocMenu').addEventListener('click', function (ev) {
    var b = ev.target.closest('button');
    if (!b) { return; }
    aplicarVelocidade(parseFloat(b.dataset.veloc), true);
    this.hidden = true;
  });
  document.addEventListener('click', function (ev) {
    if (!ev.target.closest('#nvVeloc')) { $('nvVelocMenu').hidden = true; }
  });

  function aplicarLegenda() {
    var ligada = pref.legenda !== false;
    $('nvLegenda').classList.toggle('is-oculta', !ligada);
    $('btnLegenda').classList.toggle('is-ativo', ligada);
    $('btnLegenda').title = ligada ? 'Ocultar legenda' : 'Exibir legenda';
  }
  aplicarLegenda();
  $('btnLegenda').addEventListener('click', function () {
    pref.legenda = pref.legenda === false;
    gravarPref(pref);
    aplicarLegenda();
  });

  var faltamAudios = APRES.slides.some(function (s) {
    return (s.dialogos || []).some(function (d) { return !d.audio; });
  });
  if (faltamAudios && fala.suporte) {
    fala.onVozes = escolherVoz;
    escolherVoz();
  }

  function escolherVoz() {
    var lista = fala.listarVozes();
    if (!lista.length) { return; }
    var ptBR = lista.filter(function (v) { return /^pt[-_]BR/i.test(v.lang); });
    var pt   = lista.filter(function (v) { return /^pt/i.test(v.lang); });
    var alvo = (ptBR[0] || pt[0] || lista[0]).voiceURI;
    fala.configurar({ vozURI: alvo });
  }

  (function () {
    var alvo = document.body;
    var relogio = null;
    var ESPERA = 2600;

    var TOQUE = (window.matchMedia && window.matchMedia('(pointer:coarse)').matches) ||
                ('ontouchstart' in window);

    function menuAberto() {
      return !!(document.querySelector('.nv-drawer.is-aberto') ||
                document.querySelector('.nv-veloc-menu:not([hidden])') ||
                document.querySelector('.nv-volume-menu:not([hidden])'));
    }

    function esperandoInteracao() {
      return document.body.classList.contains('trg-esperando');
    }

    function pedindoClique(alvo) {
      if (!alvo || !alvo.closest) { return false; }
      if (alvo.closest('.trg-vez')) { return true; }
      var bloco = alvo.closest('.trb');
      if (!bloco) { return false; }
      return bloco.classList.contains('is-vez') || !!bloco.querySelector('.trg-vez');
    }

    function precisaDoUsuario() {
      if (TOQUE && esperandoInteracao()) { return false; }
      var e = player.estado();
      return e === 'parado' || e === 'pausado';
    }

    function podeOcultar() {
      if (menuAberto()) { return false; }
      if (TOQUE && esperandoInteracao()) { return true; }
      var e = player.estado();
      return (e === 'tocando' || e === 'transicao');
    }

    function mostrar() {
      alvo.classList.remove('is-chrome-oculto');
      clearTimeout(relogio);
      relogio = setTimeout(function () {
        if (podeOcultar()) { alvo.classList.add('is-chrome-oculto'); }
      }, ESPERA);
    }

    ['pointermove', 'keydown', 'wheel'].forEach(function (ev) {
      document.addEventListener(ev, mostrar, { passive: true });
    });

    ['pointerdown', 'touchstart'].forEach(function (ev) {
      document.addEventListener(ev, function (e) {
        if (pedindoClique(e.target)) { return; }
        mostrar();
      }, { passive: true });
    });

    document.addEventListener('nv:estado', function () {
      if (precisaDoUsuario()) { mostrar(); return; }
      if (TOQUE && esperandoInteracao()) {
        clearTimeout(relogio);
        relogio = setTimeout(function () {
          if (podeOcultar()) { alvo.classList.add('is-chrome-oculto'); }
        }, 400);
      }
    });

    mostrar();
  })();

  player.irPara(0, false);
  player.emReproducao = false;
  player._atualizarControles();
  $('nvLegendaTexto').innerHTML =
    'Pressione <kbd>Iniciar</kbd> para começar. <kbd>←</kbd> <kbd>→</kbd> navegam, <kbd>espaço</kbd> pausa.';

  window.TR_PLAYER = player;
  window.TR_INSTRUTOR = instrutor;
})();
