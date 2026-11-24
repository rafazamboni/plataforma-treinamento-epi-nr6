(function (global) {
  'use strict';

  var OPT = global.TR_PLAYER_OPT || {};
  var T = OPT.treinamento;
  if (!T) { return; }

  var CHAVE = 'tr_orientacao_v1';

  function $(id) { return document.getElementById(id); }

  function visivel(el) {
    if (!el) { return false; }
    if (el.hidden) { return false; }
    var r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0;
  }

  function achar(sel) {
    var el = document.querySelector(sel);
    return visivel(el) ? el : null;
  }

  function temMaterial() {
    var a = global.TR_APOIO;
    return !!(a && a.materiais && a.materiais.length);
  }

  function temReferencias() {
    var a = global.TR_APOIO;
    return !!(a && a.referencias && a.referencias.length);
  }

  function jaViu() {
    try { return global.localStorage.getItem(CHAVE) === '1'; } catch (e) { return false; }
  }
  function gravarVisto(v) {
    try {
      if (v) { global.localStorage.setItem(CHAVE, '1'); }
      else { global.localStorage.removeItem(CHAVE); }
    } catch (e) {  }
  }

  function montarPassos() {
    var passos = [
      {
        alvo: '#nvSlideWrap', pos: 'centro', icone: 'easel2',
        titulo: 'A tela do treinamento',
        texto: 'Todo o conteúdo aparece nesta área: textos, imagens, exemplos e ' +
               'atividades. Quando o material for maior que a tela, role dentro dela ' +
               'para ver o restante.'
      },
      {
        alvo: '#nvInstrutor', pos: 'esq', icone: 'person-video2',
        titulo: 'Instrutor virtual',
        texto: 'O instrutor apresenta cada slide com narração em áudio. Use fones de ' +
               'ouvido ou mantenha o som do dispositivo ligado para acompanhar a explicação.'
      },
      {
        alvo: '#nvLegenda', pos: 'cima', icone: 'card-text',
        titulo: 'Legenda da narração',
        texto: 'A fala do instrutor também aparece escrita aqui, no mesmo ritmo do áudio. ' +
               'Assim é possível acompanhar mesmo em ambiente barulhento ou sem som.'
      },
      {
        alvo: '#btnPlay', pos: 'cima', icone: 'play-circle',
        titulo: 'Iniciar, pausar e rever',
        texto: 'Este botão inicia a narração do slide. Durante a explicação ele pausa; ' +
               'quando a narração termina, ele passa a repetir o slide desde o começo.',
        extra: 'Atalho: <kbd>espaço</kbd> pausa e retoma.'
      },
      {
        alvo: '#nvVolume', pos: 'cima', icone: 'volume-up',
        titulo: 'Volume da narração',
        texto: 'Ajuste o som ou desligue completamente. Sem áudio o treinamento continua ' +
               'normalmente, acompanhado pela legenda.'
      },
      {
        alvo: '#nvVeloc', pos: 'cima', icone: 'speedometer2',
        titulo: 'Velocidade',
        texto: 'Deixe a explicação mais lenta ou mais rápida, de 0,75× até 1,5×. ' +
               'A legenda acompanha automaticamente a velocidade escolhida.'
      },
      {
        alvo: '#btnLegenda', pos: 'cima', icone: 'badge-cc',
        titulo: 'Mostrar ou ocultar a legenda',
        texto: 'Liga e desliga o texto da narração na parte inferior da tela.'
      },
      {
        alvo: '#btnInstrutor', pos: 'cima', icone: 'person',
        titulo: 'Mostrar ou ocultar o instrutor',
        texto: 'Retira o instrutor da cena e libera a tela inteira para o conteúdo. ' +
               'A narração continua normalmente.'
      },
      {
        alvo: '.trt-centro', pos: 'cima', icone: 'signpost-split',
        titulo: 'Onde você está',
        texto: 'Mostra o slide atual, o título e o total de slides do treinamento. ' +
               'Cada traço da régua é um slide: os concluídos ficam em verde e podem ' +
               'ser revisitados com um clique.'
      },
      {
        alvo: '#trtAviso', pos: 'cima', icone: 'hand-index-thumb', semAlvoOk: true,
        titulo: 'Atividades do slide',
        texto: 'Alguns slides pedem uma ação: responder uma pergunta, marcar itens de ' +
               'uma lista, abrir cartões ou percorrer abas. As atividades são liberadas ' +
               'ao longo da explicação e ficam marcadas como "Sua vez".',
        extra: 'Este indicador mostra o que ainda falta. Clique nele para ver a lista completa.'
      },
      {
        alvo: '.trt-fim', pos: 'cima', icone: 'chevron-double-right',
        titulo: 'Voltar e continuar',
        texto: 'Use <b>Continuar</b> para avançar e a seta ao lado para rever o slide ' +
               'anterior. Enquanto houver atividade pendente, o Continuar fica bloqueado ' +
               'e indica o que falta. No último slide ele muda para <b>Finalizar</b>.'
      },
      {
        alvo: '#btnApoio', pos: 'baixo', icone: 'paperclip',
        titulo: 'Material de apoio',
        texto: 'Este treinamento tem documentos complementares — apostila, manual ' +
               'ou cartilha. Clique no clipe para abrir a lista e consultar quando ' +
               'quiser, sem perder o ponto em que você parou.',
        extra: 'Os documentos abrem em uma nova guia do navegador.'
      },
      {
        alvo: '#btnRoteiro', pos: 'baixo', icone: 'list-ul',
        titulo: 'Conteúdo do treinamento',
        texto: 'Abre a lista completa dos slides com o seu percentual de conclusão. ' +
               'Slides já concluídos podem ser acessados diretamente por ali.',
        extra: temReferencias()
          ? 'No fim da lista fica o item <b>Referências</b>, com as fontes usadas na ' +
            'elaboração do conteúdo.'
          : ''
      },
      {
        alvo: '#btnTela', pos: 'baixo', icone: 'arrows-fullscreen',
        titulo: 'Tela cheia',
        texto: 'Amplia o treinamento para ocupar toda a tela do dispositivo. ' +
               'Pressione <kbd>Esc</kbd> para voltar ao modo normal.'
      }
    ];

    var final = 'Você pode fechar esta página e voltar depois: o treinamento retoma ' +
                'automaticamente no ponto em que você parou.';
    if (T.temProva) {
      final += ' Ao terminar o conteúdo, você fará a prova final — é preciso atingir a ' +
               'nota mínima para concluir o treinamento.';
    }
    if (T.certificado) {
      final += ' Após a conclusão, o certificado fica disponível para consulta e impressão.';
    }

    passos.push({
      alvo: null, pos: 'centro', icone: 'shield-check',
      titulo: 'Seu progresso fica salvo',
      texto: final,
      extra: 'Este link de acesso é pessoal e intransferível.',
      final: true
    });

    return passos.filter(function (p) {
      if (!p.alvo) { return true; }
      if (achar(p.alvo)) { return true; }
      return !!p.semAlvoOk;
    });
  }

  var passos = [], indice = -1, aberto = false;
  var elBloqueio = null, elFoco = null, elCard = null, elAbertura = null;

  function abrirAbertura() {
    if (aberto) { return; }
    aberto = true;
    document.body.classList.remove('is-chrome-oculto');

    elAbertura = document.createElement('div');
    elAbertura.className = 'nvo-abertura';
elAbertura.innerHTML =
  '<div class="nvo-abertura-caixa" role="dialog" aria-modal="true" aria-labelledby="nvoTituloAbertura">' +
    '<span class="nvo-selo"><i class="bi bi-compass"></i> Orientação inicial</span>' +
    '<h2 id="nvoTituloAbertura">Como navegar neste treinamento</h2>' +
    '<label class="nvo-lembrar">' +
      '<input type="checkbox" id="nvoNaoMostrar">' +
      '<span>Não mostrar esta orientação novamente neste dispositivo</span>' +
    '</label>' +
    '<div class="nvo-abertura-acoes">' +
      '<button type="button" class="nvo-btn nvo-btn-ghost" id="nvoPular">' +
        '<i class="bi bi-fast-forward"></i> Pular e ir ao conteúdo</button>' +
      '<button type="button" class="nvo-btn nvo-btn-ok" id="nvoVer">' +
        '<i class="bi bi-compass"></i> Ver orientação (1 min)</button>' +
    '</div>' +
    '<p class="nvo-abertura-rodape">A orientação pode ser reaberta a qualquer momento ' +
      'pelo botão <i class="bi bi-question-lg"></i> no canto superior direito.</p>' +
  '</div>';

    document.body.appendChild(elAbertura);

    $('nvoVer').addEventListener('click', function () {
      gravarVisto(!!($('nvoNaoMostrar') || {}).checked);
      fecharAbertura();
      iniciarTour();
    });
    $('nvoPular').addEventListener('click', function () {
      gravarVisto(!!($('nvoNaoMostrar') || {}).checked);
      fecharAbertura();
      encerrar();
    });

    $('nvoVer').focus();
  }

  function fecharAbertura() {
    if (elAbertura) { elAbertura.remove(); elAbertura = null; }
  }

  function iniciarTour() {
    aberto = true;
    passos = montarPassos();
    if (!passos.length) { encerrar(); return; }

    document.body.classList.remove('is-chrome-oculto');

    elBloqueio = document.createElement('div');
    elBloqueio.className = 'nvo-bloqueio';
    document.body.appendChild(elBloqueio);

    elFoco = document.createElement('div');
    elFoco.className = 'nvo-foco nvo-oculto';
    document.body.appendChild(elFoco);

    elCard = document.createElement('div');
    elCard.className = 'nvo-card';
    elCard.setAttribute('role', 'dialog');
    elCard.setAttribute('aria-modal', 'true');
    elCard.innerHTML =
      '<div class="nvo-card-topo">' +
        '<span class="nvo-passo"></span>' +
        '<button type="button" class="nvo-fechar" id="nvoFechar">' +
          'Pular orientação <i class="bi bi-x-lg"></i></button>' +
      '</div>' +
      '<div class="nvo-card-corpo">' +
        '<div class="nvo-icone"><i></i></div>' +
        '<div class="nvo-card-texto"><h3></h3><p class="nvo-p1"></p><p class="nvo-p2"></p></div>' +
      '</div>' +
      '<div class="nvo-trilho"><i></i></div>' +
      '<div class="nvo-card-pe">' +
        '<button type="button" class="nvo-btn nvo-btn-ghost" id="nvoAnterior">' +
          '<i class="bi bi-chevron-left"></i> Anterior</button>' +
        '<button type="button" class="nvo-btn nvo-btn-ok" id="nvoProximo">' +
          '<span></span> <i class="bi bi-chevron-right"></i></button>' +
      '</div>';
    document.body.appendChild(elCard);

    $('nvoFechar').addEventListener('click', encerrar);
    $('nvoAnterior').addEventListener('click', function () { irPara(indice - 1); });
    $('nvoProximo').addEventListener('click', function () {
      if (indice >= passos.length - 1) { encerrar(); } else { irPara(indice + 1); }
    });

    global.addEventListener('resize', reposicionar);
    document.addEventListener('keydown', aoTeclar, true);

    irPara(0);
  }

  function aoTeclar(ev) {
    if (!elCard) { return; }
    if (ev.key === 'Escape') { ev.preventDefault(); ev.stopPropagation(); encerrar(); return; }
    if (ev.key === 'ArrowRight' || ev.key === 'Enter' || ev.key === ' ') {
      ev.preventDefault(); ev.stopPropagation();
      if (indice >= passos.length - 1) { encerrar(); } else { irPara(indice + 1); }
      return;
    }
    if (ev.key === 'ArrowLeft') {
      ev.preventDefault(); ev.stopPropagation();
      irPara(indice - 1);
    }
  }

  function irPara(i) {
    if (i < 0 || i >= passos.length) { return; }
    indice = i;
    var p = passos[i];

    elCard.querySelector('.nvo-passo').textContent = 'Passo ' + (i + 1) + ' de ' + passos.length;
    elCard.querySelector('.nvo-icone i').className = 'bi bi-' + p.icone;
    elCard.querySelector('h3').textContent = p.titulo;
    elCard.querySelector('.nvo-p1').innerHTML = p.texto;

    var p2 = elCard.querySelector('.nvo-p2');
    if (p.extra) { p2.innerHTML = p.extra; p2.hidden = false; }
    else { p2.innerHTML = ''; p2.hidden = true; }

    elCard.querySelector('.nvo-trilho i').style.width =
      Math.round(((i + 1) / passos.length) * 100) + '%';

    $('nvoAnterior').disabled = (i === 0);
    var prox = $('nvoProximo');
    prox.querySelector('span').textContent = p.final ? 'Começar treinamento' : 'Próximo';
    prox.querySelector('i').className = p.final ? 'bi bi-play-fill' : 'bi bi-chevron-right';

    reposicionar();
  }

  function reposicionar() {
    if (!elCard || indice < 0) { return; }

    var p = passos[indice];
    var alvo = p.alvo ? achar(p.alvo) : null;
    var vw = global.innerWidth, vh = global.innerHeight;
    var margem = 14, folga = 8;

    elCard.classList.remove('is-cima', 'is-baixo', 'is-esq', 'is-dir', 'is-centro');

    if (alvo) {
      var r = alvo.getBoundingClientRect();
      elFoco.classList.remove('nvo-oculto');
      elFoco.style.left = Math.max(2, r.left - folga) + 'px';
      elFoco.style.top = Math.max(2, r.top - folga) + 'px';
      elFoco.style.width = Math.min(vw - 4, r.width + folga * 2) + 'px';
      elFoco.style.height = Math.min(vh - 4, r.height + folga * 2) + 'px';
      if (elBloqueio) { elBloqueio.classList.remove('is-cheio'); }
    } else {
      elFoco.classList.add('nvo-oculto');
      if (elBloqueio) { elBloqueio.classList.add('is-cheio'); }
    }

    if (vw <= 680) { elCard.classList.add('is-centro'); return; }

    var c = elCard.getBoundingClientRect();
    var lado = null, topo = 0, esq = 0;

    if (alvo && p.pos !== 'centro') {
      var b = alvo.getBoundingClientRect();
      var cabe = {
        baixo: b.bottom + margem + c.height <= vh - 8,
        cima: b.top - margem - c.height >= 8,
        dir: b.right + margem + c.width <= vw - 8,
        esq: b.left - margem - c.width >= 8
      };
      var ordem = [p.pos, 'cima', 'baixo', 'dir', 'esq'];
      for (var k = 0; k < ordem.length; k++) {
        if (cabe[ordem[k]]) { lado = ordem[k]; break; }
      }

      if (lado === 'baixo' || lado === 'cima') {
        topo = (lado === 'baixo') ? b.bottom + margem : b.top - margem - c.height;
        esq = b.left + (b.width / 2) - (c.width / 2);
      } else if (lado === 'dir' || lado === 'esq') {
        esq = (lado === 'dir') ? b.right + margem : b.left - margem - c.width;
        topo = b.top + (b.height / 2) - (c.height / 2);
      }
    }

    if (!lado) {
      elCard.classList.add('is-centro');
      elCard.style.left = Math.round((vw - c.width) / 2) + 'px';
      elCard.style.top = Math.round((vh - c.height) / 2) + 'px';
      elCard.style.removeProperty('--nvo-seta');
      return;
    }

    var esqFinal = Math.max(10, Math.min(esq, vw - c.width - 10));
    var topoFinal = Math.max(10, Math.min(topo, vh - c.height - 10));

    elCard.classList.add('is-' + lado);
    elCard.style.left = Math.round(esqFinal) + 'px';
    elCard.style.top = Math.round(topoFinal) + 'px';

    var alvoRect = alvo.getBoundingClientRect();
    if (lado === 'baixo' || lado === 'cima') {
      var sx = alvoRect.left + alvoRect.width / 2 - esqFinal - 7;
      elCard.style.setProperty('--nvo-seta', Math.max(16, Math.min(sx, c.width - 30)) + 'px');
    } else {
      var sy = alvoRect.top + alvoRect.height / 2 - topoFinal - 7;
      elCard.style.setProperty('--nvo-seta', Math.max(16, Math.min(sy, c.height - 30)) + 'px');
    }
  }

  function encerrar() {
    fecharAbertura();
    if (elCard) { elCard.remove(); elCard = null; }
    if (elFoco) { elFoco.remove(); elFoco = null; }
    if (elBloqueio) { elBloqueio.remove(); elBloqueio = null; }
    global.removeEventListener('resize', reposicionar);
    document.removeEventListener('keydown', aoTeclar, true);
    indice = -1;
    aberto = false;
  }

  function montarBotaoAjuda() {
    if ($('nvoAjuda')) { return; }
    var b = document.createElement('button');
    b.type = 'button';
    b.id = 'nvoAjuda';
    b.title = 'Como navegar neste treinamento';
    b.setAttribute('aria-label', 'Como navegar neste treinamento');
    b.innerHTML = '<i class="bi bi-question-lg"></i>';

    var flut = document.querySelector('.trt-flutuante');
    if (flut) {
      b.className = 'nv-icon-btn nvo-ajuda';
      flut.insertBefore(b, flut.firstChild);
    } else {
      b.className = 'nvo-ajuda-solto';
      document.body.appendChild(b);
    }

    b.addEventListener('click', function () {
      if (aberto) { return; }
      abrirAbertura();
    });
  }

  function comecar() {
    montarBotaoAjuda();
    if (T.previa) { return; }
    if (jaViu()) { return; }
    abrirAbertura();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { setTimeout(comecar, 350); });
  } else {
    setTimeout(comecar, 350);
  }

  global.TrOrientacao = {
    abrir: function () { if (!aberto) { abrirAbertura(); } },
    fechar: encerrar,
    esquecer: function () { gravarVisto(false); }
  };
})(window);
