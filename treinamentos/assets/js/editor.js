(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var API_ID = window.TR_APRESENTACAO_ID;
  var BASE = window.TR_BASE || '';
  var CAT = window.TR_CAT;
  var ESTADO = window.TR_DADOS;
  var AP = ESTADO.apresentacao;
  var idxSlide = 0;

  function esc(t) {
    return String(t == null ? '' : t)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function opcoes(mapa, sel) {
    return Object.keys(mapa).map(function (k) {
      return '<option value="' + esc(k) + '"' + (String(k) === String(sel) ? ' selected' : '') + '>' +
             esc(mapa[k]) + '</option>';
    }).join('');
  }

  function slideAtual() { return ESTADO.slides[idxSlide] || null; }

  function dialogoPorId(id) {
    var s = slideAtual();
    if (!s) { return null; }
    for (var i = 0; i < s.dialogos.length; i++) {
      if (Number(s.dialogos[i].id) === Number(id)) { return s.dialogos[i]; }
    }
    return null;
  }

  function acaoPorId(d, id) {
    for (var i = 0; i < d.acoes.length; i++) {
      if (Number(d.acoes[i].id) === Number(id)) { return d.acoes[i]; }
    }
    return null;
  }

  var timerSalvo = null;
  function marcarSalvo() {
    var el = $('trSalvo');
    el.hidden = false;
    clearTimeout(timerSalvo);
    timerSalvo = setTimeout(function () { el.hidden = true; }, 1600);
  }

  function salvar(acao, dados, silencioso) {
    return window.trApi(acao, dados).then(function (j) {
      if (silencioso === false) { window.trToast(j.msg); } else { marcarSalvo(); }
      return j;
    }).catch(function (e) {
      window.trToast(e.message, 'erro');
      throw e;
    });
  }

  function medirAltura() {
    var wrap = document.querySelector('.tr-wrap-editor');
    if (!wrap) { return; }
    var topo = wrap.getBoundingClientRect().top + (window.scrollY || 0);
    document.documentElement.style.setProperty('--tr-topo', Math.max(0, Math.round(topo)) + 'px');
  }
  medirAltura();
  window.addEventListener('resize', medirAltura);
  window.addEventListener('load', medirAltura);

  function recolherSlides(recolher) {
    $('trEditor').classList.toggle('is-recolhido', !!recolher);
    try { localStorage.setItem('tr_slides_recolhido', recolher ? '1' : '0'); } catch (e) {}
    document.dispatchEvent(new CustomEvent('tr:layout'));
  }
  if ($('btnRecolherSlides')) {
    $('btnRecolherSlides').addEventListener('click', function () { recolherSlides(true); });
  }
  if ($('btnAbrirSlides')) {
    $('btnAbrirSlides').addEventListener('click', function () { recolherSlides(false); });
  }
  try {
    if (localStorage.getItem('tr_slides_recolhido') === '1') { recolherSlides(true); }
  } catch (e) {}

  function abrirAba(nome) {
    Array.prototype.forEach.call(document.querySelectorAll('.tr-prop-aba'), function (a) {
      a.classList.toggle('is-ativa', a.getAttribute('data-prop') === nome);
    });
    Array.prototype.forEach.call(document.querySelectorAll('.tr-prop-painel'), function (p) {
      p.classList.toggle('is-ativo', p.getAttribute('data-painel') === nome);
    });
    document.dispatchEvent(new CustomEvent('tr:layout'));
  }

  document.addEventListener('click', function (ev) {
    var aba = ev.target.closest('.tr-prop-aba');
    if (aba) { abrirAba(aba.getAttribute('data-prop')); }
  });

  document.addEventListener('tr:elemento-selecionado', function () { abrirAba('elemento'); });

  function renderSlides() {
    var alvo = $('listaSlides');
    if ($('contSlides')) { $('contSlides').textContent = ESTADO.slides.length; }
    if (!ESTADO.slides.length) {
      alvo.innerHTML = '<p class="tr-vazio-mini">Nenhum slide. Use o + para criar o primeiro.</p>';
      return;
    }
    alvo.innerHTML = ESTADO.slides.map(function (s, i) {
      var narrados = s.dialogos.filter(function (d) { return d.voz_modo === 'arquivo' && d.audio_arquivo; }).length;
      var selo = s.dialogos.length
        ? (narrados === s.dialogos.length
            ? '<span class="tr-selo ok" title="Narração completa"><i class="bi bi-mic-fill"></i></span>'
            : '<span class="tr-selo" title="' + narrados + ' de ' + s.dialogos.length + ' falas narradas">' +
              narrados + '/' + s.dialogos.length + '</span>')
        : '';
      return '<div class="tr-item-slide' + (i === idxSlide ? ' is-atual' : '') + '" draggable="true"' +
                 ' data-slide-idx="' + i + '" data-id="' + s.id + '">' +
               '<span class="tr-item-grip" title="Arraste para reordenar"><i class="bi bi-grip-vertical"></i></span>' +
               '<span class="tr-item-num">' + (i + 1) + '</span>' +
               '<div class="tr-item-corpo">' +
                 '<b>' + esc(s.titulo) + '</b>' +
                 '<small>' + s.dialogos.length + ' fala' + (s.dialogos.length === 1 ? '' : 's') + ' ' + selo + '</small>' +
               '</div>' +
             '</div>';
    }).join('');
  }

  function selecionarSlide(i) {
    if (i < 0 || i >= ESTADO.slides.length) { return; }
    if (window.trCanvasFinalizar) { window.trCanvasFinalizar(); }
    idxSlide = i;
    renderSlides();
    preencherSlide();
    renderDialogos();
  }

  function preencherSlide() {
    var s = slideAtual();
    var painel = $('painelSlide');
    if (!s) { painel.classList.add('is-vazio'); return; }
    painel.classList.remove('is-vazio');

    $('slideCabecalho').textContent = 'Slide ' + (idxSlide + 1);
    $('slideTitulo').value = s.titulo || '';
    $('slideConteudo').value = s.conteudo_html || '';
    if (window.trCanvasCarregar) { window.trCanvasCarregar(s.conteudo_html || ''); }

    $('slideLayout').innerHTML = opcoes(CAT.layouts, s.layout);
    $('slideTransicao').innerHTML = opcoes(CAT.transicoes, s.transicao);
    $('slidePosicao').innerHTML = opcoes(CAT.posicoes, s.instrutor_posicao);
    $('slideLadoConteudo').innerHTML = opcoes(CAT.ladosConteudo, s.instrutor_lado_conteudo || 'auto');
    $('slideGiro').innerHTML = opcoes(CAT.girosSlide, s.instrutor_giro || 'herdar');

    $('slideFundoTipo').value = s.fundo_tipo || 'tema';
    $('slideFundoCor').value = s.fundo_cor || '#ffffff';
    $('slideFundoCor2').value = s.fundo_cor2 || AP.cor_fundo || '#eef2f7';
    $('slideFundoAngulo').value = s.fundo_angulo || 160;
    $('slideAnguloVal').textContent = (s.fundo_angulo || 160) + '°';
    $('slideFundoEscurecer').value = s.fundo_escurecer || 0;
    $('slideEscurecerVal').textContent = (s.fundo_escurecer || 0) + '%';
    $('slideFundoNome').textContent = s.fundo_imagem ? 'Atual: ' + s.fundo_imagem : '';

    $('slideCorTextoAuto').checked = !s.cor_texto;
    $('slideCorTexto').value = s.cor_texto || '#16222f';
    $('slideCorTexto').disabled = !s.cor_texto;

    $('slideInstrutorVisivel').checked = Number(s.instrutor_visivel) === 1;
    $('slideCamera').value = s.instrutor_camera || 'herdar';
    $('slideEscala').value = s.instrutor_escala || 1;
    $('slideEscalaVal').textContent = Number(s.instrutor_escala || 1).toFixed(2);

    aplicarVisibilidadeFundo();
    document.dispatchEvent(new CustomEvent('tr:slide-carregado'));
  }

  function aplicarVisibilidadeFundo() {
    var tipo = $('slideFundoTipo').value;
    Array.prototype.forEach.call(document.querySelectorAll('[data-fundo]'), function (el) {
      el.hidden = el.getAttribute('data-fundo').split(' ').indexOf(tipo) < 0;
    });
  }

  function coletarSlide() {
    var s = slideAtual();
    if (!s) { return null; }
    s.titulo = $('slideTitulo').value.trim() || 'Slide';
    s.conteudo_html = $('slideConteudo').value;
    s.layout = $('slideLayout').value;
    s.transicao = $('slideTransicao').value;
    s.fundo_tipo = $('slideFundoTipo').value;
    s.fundo_cor = $('slideFundoCor').value;
    s.fundo_cor2 = $('slideFundoCor2').value;
    s.fundo_angulo = Number($('slideFundoAngulo').value);
    s.fundo_escurecer = Number($('slideFundoEscurecer').value);
    s.cor_texto = $('slideCorTextoAuto').checked ? '' : $('slideCorTexto').value;
    s.instrutor_visivel = $('slideInstrutorVisivel').checked ? 1 : 0;
    s.instrutor_posicao = $('slidePosicao').value;
    s.instrutor_lado_conteudo = $('slideLadoConteudo').value;
    s.instrutor_giro = $('slideGiro').value;
    s.instrutor_camera = $('slideCamera').value;
    s.instrutor_escala = Number($('slideEscala').value);
    return s;
  }

  function salvarSlide() {
    var s = coletarSlide();
    if (!s) { return Promise.resolve(); }
    renderSlides();
    return salvar('slide_salvar', {
      id: s.id, titulo: s.titulo, transicao: s.transicao, layout: s.layout,
      fundo_tipo: s.fundo_tipo, fundo_cor: s.fundo_cor, fundo_cor2: s.fundo_cor2,
      fundo_angulo: s.fundo_angulo, fundo_escurecer: s.fundo_escurecer, cor_texto: s.cor_texto,
      instrutor_visivel: s.instrutor_visivel, instrutor_posicao: s.instrutor_posicao,
      instrutor_escala: s.instrutor_escala, instrutor_camera: s.instrutor_camera,
      instrutor_lado_conteudo: s.instrutor_lado_conteudo, instrutor_giro: s.instrutor_giro,
      conteudo_html: s.conteudo_html
    }).catch(function () {});
  }

  function rotuloElemento(el) {
    if (el.t === 'bloco') {
      var esq = window.TrBlocos && window.TrBlocos.ESQUEMA[el.bloco];
      return '#' + el.id + ' — Bloco: ' + (esq ? esq.rotulo : el.bloco);
    }
    if (el.t === 'texto') {
      var txt = String(el.html || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
      return '#' + el.id + ' — Texto' + (txt ? ': ' + txt.slice(0, 34) + (txt.length > 34 ? '…' : '') : '');
    }
    if (el.t === 'imagem') { return '#' + el.id + ' — Imagem'; }
    if (el.t === 'forma')  { return '#' + el.id + ' — Forma'; }
    return '#' + el.id;
  }

  function subitensDoBloco(bloco, dados) {
    var d = dados || {};
    switch (bloco) {
      case 'passos':
        return (d.itens || []).map(function (it, i) {
          return { sel: '.trb-passo:nth-child(' + (i + 1) + ')', rotulo: 'Etapa ' + (i + 1) + (it.t ? ' — ' + it.t : '') };
        });
      case 'revelar':
        return (d.itens || []).map(function (it, i) {
          return { sel: '.trb-card:nth-child(' + (i + 1) + ')', rotulo: 'Cartão ' + (i + 1) + (it.t ? ' — ' + it.t : '') };
        });
      case 'abas':
        return (d.itens || []).map(function (it, i) {
          return { sel: '.trb-aba[data-i="' + i + '"]', rotulo: 'Aba ' + (i + 1) + (it.t ? ' — ' + it.t : '') };
        });
      case 'checklist':
        return (d.itens || []).map(function (it, i) {
          return { sel: '.trb-check:nth-child(' + (i + 1) + ')', rotulo: 'Item ' + (i + 1) + (it.t ? ' — ' + it.t : '') };
        });
      case 'quiz':
        return (d.opcoes || []).map(function (it, i) {
          return { sel: '.trb-quiz-op[data-i="' + i + '"]', rotulo: 'Alternativa ' + String.fromCharCode(65 + i) + (it.t ? ' — ' + it.t : '') };
        });
      case 'comparativo':
        return (d.aItens || []).map(function (it, i) {
          return { sel: '.trb-comp-ok li:nth-child(' + (i + 1) + ')', rotulo: (d.aTitulo || 'Coluna positiva') + ' ' + (i + 1) + (it.t ? ' — ' + it.t : '') };
        }).concat((d.bItens || []).map(function (it, i) {
          return { sel: '.trb-comp-nao li:nth-child(' + (i + 1) + ')', rotulo: (d.bTitulo || 'Coluna negativa') + ' ' + (i + 1) + (it.t ? ' — ' + it.t : '') };
        }));
      case 'estatisticas':
        return (d.itens || []).map(function (it, i) {
          return { sel: '.trb-kpi:nth-child(' + (i + 1) + ')', rotulo: 'Indicador ' + (i + 1) + (it.r ? ' — ' + it.r : '') };
        });
      case 'linhaTempo':
        return (d.itens || []).map(function (it, i) {
          return { sel: '.trb-marco:nth-child(' + (i + 1) + ')', rotulo: 'Marco ' + (i + 1) + (it.t ? ' — ' + it.t : '') };
        });
      default:
        return [];
    }
  }

  function campoSeletorAlvo(a, filtro) {
    var els = (window.TR_CANVAS && window.TR_CANVAS.els) || [];
    if (filtro) { els = els.filter(filtro); }
    var valorAtual = String(a.alvo || '');
    var m = valorAtual.match(/^#([^\s]+)(?:\s+(.*))?$/);
    var idAtual = m ? m[1] : '';
    var subAtual = m && m[2] ? m[2].trim() : '';
    var reconhecido = idAtual === '' || els.some(function (e) { return e.id === idAtual; });
    var manual = !!a._alvoManual || !reconhecido || !els.length;

    if (manual) {
      return '<div class="tr-campo tr-campo-cheio tr-alvo-picker">' +
               '<label class="tr-label">Seletor</label>' +
               '<input type="text" class="form-control form-control-sm" data-acao-campo="alvo" ' +
                 'value="' + esc(valorAtual) + '" placeholder="#e1">' +
               (els.length
                 ? '<button type="button" class="tr-link-mini" data-alvo-manual-alternar="0">Escolher da lista de elementos</button>'
                 : '') +
             '</div>';
    }

    var elAtual = els.filter(function (e) { return e.id === idAtual; })[0];
    var optsEl = '<option value="">Selecione um elemento…</option>' + els.map(function (e) {
      return '<option value="' + esc(e.id) + '"' + (e.id === idAtual ? ' selected' : '') + '>' + esc(rotuloElemento(e)) + '</option>';
    }).join('');

    var subitens = elAtual && elAtual.t === 'bloco' ? subitensDoBloco(elAtual.bloco, elAtual.dados) : [];
    var optsSub = subitens.length
      ? '<div class="tr-campo"><label class="tr-label">Parte do bloco</label>' +
          '<select class="form-select form-select-sm" data-acao-alvo-sub>' +
            '<option value="">O bloco inteiro</option>' +
            subitens.map(function (si) {
              return '<option value="' + esc(si.sel) + '"' + (si.sel === subAtual ? ' selected' : '') + '>' + esc(si.rotulo) + '</option>';
            }).join('') +
          '</select></div>'
      : '';

    return '<div class="tr-campo tr-campo-cheio tr-alvo-picker">' +
             '<label class="tr-label">Onde agir</label>' +
             '<div class="tr-alvo-picker-linha">' +
               '<div class="tr-campo"><select class="form-select form-select-sm" data-acao-alvo-el>' + optsEl + '</select></div>' +
               optsSub +
             '</div>' +
             '<button type="button" class="tr-link-mini" data-alvo-manual-alternar="1">Digitar o seletor manualmente</button>' +
             '<input type="hidden" data-acao-campo="alvo" value="' + esc(valorAtual) + '">' +
           '</div>';
  }

  var ACOES_PRONTAS = {
    'img-destacar': { rotulo: 'Imagem: aproximar e destacar',
                      tipo: 'classe', valor: 'trc-img-destaque', remover: 0 },
    'img-voltar':   { rotulo: 'Imagem: voltar ao enquadramento normal',
                      tipo: 'classe', valor: 'trc-img-destaque', remover: 1 },
    'vid-tocar':    { rotulo: 'Vídeo: reproduzir',
                      tipo: 'classe', valor: 'trc-video-tocar', remover: 0, alvo: 'video' },
    'vid-parar':    { rotulo: 'Vídeo: pausar',
                      tipo: 'classe', valor: 'trc-video-tocar', remover: 1, alvo: 'video' },
    'vid-reiniciar':{ rotulo: 'Vídeo: recomeçar do início',
                      tipo: 'classe', valor: 'trc-video-reiniciar', remover: 0, alvo: 'video' }
  };

  function tiposUI() {
    var m = {};
    Object.keys(CAT.acoes).forEach(function (k) {
      m[k] = CAT.acoes[k];
      if (k === 'classe') {
        Object.keys(ACOES_PRONTAS).forEach(function (p) { m[p] = ACOES_PRONTAS[p].rotulo; });
      }
    });
    return m;
  }

  function tipoUI(a) {
    var chave = null;
    Object.keys(ACOES_PRONTAS).forEach(function (k) {
      var p = ACOES_PRONTAS[k];
      if (a.tipo === p.tipo && a.valor === p.valor && Number(a.remover || 0) === p.remover) {
        chave = k;
      }
    });
    return chave || a.tipo;
  }

  function aplicarTipoUI(a, escolha) {
    var p = ACOES_PRONTAS[escolha];
    if (p) {
      a.tipo = p.tipo;
      a.valor = p.valor;
      a.remover = p.remover;
    } else {
      a.tipo = escolha;
      if (a.valor === 'trc-img-destaque') { a.valor = ''; a.remover = 0; }
    }
  }

  function campoAcao(a) {
    var tipo = a.tipo;
    var pronta = ACOES_PRONTAS[tipoUI(a)];
    var html = '';

    if (pronta) {
      var tipoAlvo = pronta.alvo || 'imagem';
      var filtro = function (e) { return e.t === tipoAlvo; };
      if (!((window.TR_CANVAS && window.TR_CANVAS.els) || []).some(filtro)) {
        return '<p class="tr-dica tr-campo-cheio">Esta tela ainda não tem ' +
               (tipoAlvo === 'video' ? 'vídeo.' : 'imagem.') + '</p>';
      }
      return campoSeletorAlvo(a, filtro);
    }
    if (['revelar', 'ocultar', 'destacar', 'foco', 'classe'].indexOf(tipo) >= 0) {
      html += campoSeletorAlvo(a);
    }
    if (tipo === 'foco') {
      html += '<div class="tr-campo"><label class="tr-label">Rótulo</label>' +
              '<input type="text" class="form-control form-control-sm" data-acao-campo="rotulo" value="' + esc(a.rotulo) + '"></div>';
    }
    if (tipo === 'quadro') {
      html += '<div class="tr-campo tr-campo-cheio"><label class="tr-label">Título do quadro</label>' +
              '<input type="text" class="form-control form-control-sm" data-acao-campo="titulo" value="' + esc(a.titulo) + '"></div>' +
              '<div class="tr-campo tr-campo-cheio"><label class="tr-label">Conteúdo (HTML)</label>' +
              '<textarea class="form-control form-control-sm tr-codigo-mini" rows="3" data-acao-campo="html_conteudo">' +
              esc(a.html_conteudo) + '</textarea></div>';
    }
    var mapa = null;
    if (tipo === 'gesto')     { mapa = CAT.gestos; }
    if (tipo === 'humor')     { mapa = CAT.humores; }
    if (tipo === 'olhar')     { mapa = CAT.olhares; }
    if (tipo === 'posicao')   { mapa = CAT.posicoes; }
    if (tipo === 'giro')      { mapa = CAT.giros; }
    if (tipo === 'lado-conteudo') { mapa = CAT.ladosConteudo; }
    if (tipo === 'instrutor') { mapa = { mostrar: 'Mostrar', ocultar: 'Ocultar' }; }
    if (mapa) {
      html += '<div class="tr-campo"><label class="tr-label">Valor</label>' +
              '<select class="form-select form-select-sm" data-acao-campo="valor">' + opcoes(mapa, a.valor) + '</select></div>';
    } else if (tipo === 'classe') {
      html += '<div class="tr-campo"><label class="tr-label">' + (tipo === 'classe' ? 'Classe CSS' : 'Texto') + '</label>' +
              '<input type="text" class="form-control form-control-sm" data-acao-campo="valor" value="' + esc(a.valor) + '"></div>';
    }
    if (tipo === 'classe') {
      html += '<div class="tr-campo"><label class="tr-check tr-check-inline">' +
              '<input type="checkbox" data-acao-campo="remover"' + (Number(a.remover) === 1 ? ' checked' : '') + '>' +
              '<span>Remover</span></label></div>';
    }
    return html;
  }

  function palavrasDaFala(texto) {
    return String(texto || '').split(/\s+/).filter(function (p) { return p !== ''; });
  }

  function trilhaMomento(a, d) {
    var palavras = palavrasDaFala(d.texto);
    if (!palavras.length) {
      return '<p class="tr-vazio-mini mb-0">Escreva o texto da fala para marcar o momento.</p>';
    }
    var frac = parseFloat(a.momento) || 0;
    var indice = Math.min(palavras.length - 1, Math.round(frac * palavras.length));
    if (frac <= 0) { indice = -1; }

    var chips = palavras.map(function (p, k) {
      var classe = 'tr-palavra' + (k === indice ? ' is-marcada' : '') + (k < indice ? ' is-antes' : '');
      return '<button type="button" class="' + classe + '" data-palavra="' + k + '" data-total="' + palavras.length + '">' +
             esc(p) + '</button>';
    }).join('');

    var rotulo = indice < 0
      ? 'no início da fala'
      : 'ao dizer <b>' + esc(palavras[indice]) + '</b> (palavra ' + (indice + 1) + ' de ' + palavras.length + ')';

    return '<div class="tr-momento">' +
             '<div class="tr-momento-topo">' +
               '<span class="tr-label mb-0">Disparar</span>' +
               '<span class="tr-momento-eco">' + rotulo + '</span>' +
               '<button type="button" class="tr-mini-btn tr-momento-inicio" data-palavra="-1">' +
                 '<i class="bi bi-skip-backward-fill"></i></button>' +
             '</div>' +
             '<div class="tr-palavras">' + chips + '</div>' +
             '<input type="hidden" data-acao-campo="momento" value="' + frac + '">' +
           '</div>';
  }

  function blocoAcao(a, d) {
    return '<div class="tr-acao" data-acao-id="' + a.id + '">' +
             '<div class="tr-acao-topo">' +
               '<select class="form-select form-select-sm tr-acao-tipo" data-acao-tipo-ui>' +
                 opcoes(tiposUI(), tipoUI(a)) + '</select>' +
               '<input type="hidden" data-acao-campo="tipo" value="' + esc(a.tipo) + '">' +
               '<select class="form-select form-select-sm tr-acao-fase" data-acao-campo="fase">' +
                 opcoes({ durante: 'Durante a fala', apos: 'Após a fala' }, a.fase) + '</select>' +
               '<button type="button" class="tr-mini-btn tr-mini-btn-perigo" data-excluir-acao="' + a.id + '"><i class="bi bi-x-lg"></i></button>' +
             '</div>' +
             '<div class="tr-acao-momento"' + (a.fase === 'apos' ? ' hidden' : '') + '>' +
               trilhaMomento(a, d) +
             '</div>' +
             '<div class="tr-acao-campos">' + campoAcao(a) + '</div>' +
           '</div>';
  }

  function blocoNarracao(d) {
    var temAudio = d.voz_modo === 'arquivo' && d.audio_arquivo;
    var desatualizado = temAudio && d.audio_desatualizado;
    var podeNarrar = window.TR_PODE_NARRAR;

    var estado = temAudio
      ? '<span class="tr-chip ' + (desatualizado ? '' : 'tr-chip-ok') + '">' +
        '<i class="bi bi-' + (desatualizado ? 'exclamation-circle' : 'mic-fill') + '"></i> ' +
        (desatualizado ? 'Texto mudou — regerar' : 'Narrada') + '</span>'
      : '<span class="tr-chip"><i class="bi bi-mic-mute"></i> Sem narração</span>';

    var botoes = '';
    if (podeNarrar) {
      botoes += '<button type="button" class="btn btn-sm ' + (temAudio ? 'tr-btn-ghost' : 'tr-btn-primaria') +
                '" data-narrar="' + d.id + '"><i class="bi bi-soundwave"></i> ' +
                (temAudio ? 'Regerar' : 'Gerar narração') + '</button>';
    }
    if (temAudio) {
      botoes += '<audio controls preload="none" src="' + BASE + 'player.php?id=' + API_ID + '&audio=' + d.id + '"></audio>' +
                '<button type="button" class="btn btn-sm tr-btn-perigo" data-remover-narracao="' + d.id + '">Remover</button>';
    }

    return '<div class="tr-audio">' +
             '<div class="tr-audio-topo"><span class="tr-label mb-0">Voz desta fala</span>' + estado + '</div>' +
             '<div class="tr-audio-linha">' + botoes + '</div>' +
           '</div>';
  }

  function chipSelect(icone, rotulo, campo, html) {
    return '<div class="tr-campo">' +
             '<label class="tr-label"><i class="bi bi-' + icone + '"></i> ' + esc(rotulo) + '</label>' +
             '<select class="form-select form-select-sm" data-fala-campo="' + campo + '">' + html + '</select>' +
           '</div>';
  }

  function interp(v) {
    var n = Number(v);
    return isFinite(n) ? n : 1;
  }

  function resumoFala(d) {
    var t = String(d.texto || '').trim();
    if (!t) { return '<em class="tr-fala-vazia">Fala sem texto</em>'; }
    return esc(t.slice(0, 140)) + (t.length > 140 ? '…' : '');
  }

  function duracaoFala(d) {
    var n = String(d.texto || '').trim().split(/\s+/).filter(Boolean).length;
    if (!n) { return 'vazia'; }
    var seg = Math.max(1, Math.round(n / 2.6));
    return n + ' palavras · ~' + (seg >= 60 ? Math.round(seg / 60) + ' min' : seg + 's');
  }

  function selosFala(d) {
    var out = '';
    var temAudio = d.voz_modo === 'arquivo' && d.audio_arquivo;
    if (temAudio) {
      out += d.audio_desatualizado
        ? '<em class="tr-selinho is-aviso" title="O texto mudou depois da narração"><i class="bi bi-exclamation-circle"></i></em>'
        : '<em class="tr-selinho is-ok" title="Fala narrada"><i class="bi bi-mic-fill"></i></em>';
    } else {
      out += '<em class="tr-selinho" title="Sem narração"><i class="bi bi-mic-mute"></i></em>';
    }
    if (String(d.gesto || '').trim()) {
      out += '<em class="tr-selinho" title="Gesto: ' + esc(CAT.gestos[d.gesto] || d.gesto) + '">' +
             '<i class="bi bi-hand-index-thumb"></i></em>';
    }
    if (d.acoes && d.acoes.length) {
      out += '<em class="tr-selinho" title="' + d.acoes.length + ' ação(ões) no slide">' +
             '<i class="bi bi-lightning-charge"></i>' + d.acoes.length + '</em>';
    }
    return out;
  }

  function cartaoFala(d, i) {
    return '<article class="tr-falinha" draggable="true" data-id="' + d.id + '" data-dialogo-id="' + d.id + '">' +
             '<span class="tr-falinha-grip" title="Arraste para reordenar"><i class="bi bi-grip-vertical"></i></span>' +
             '<span class="tr-falinha-num">' + (i + 1) + '</span>' +
             '<div class="tr-falinha-txt">' +
               '<p class="tr-falinha-resumo">' + resumoFala(d) + '</p>' +
               '<span class="tr-falinha-meta">' +
                 '<span class="tr-falinha-dur">' + esc(duracaoFala(d)) + '</span>' +
                 selosFala(d) +
               '</span>' +
             '</div>' +
             '<div class="tr-falinha-acoes">' +
               '<button type="button" class="tr-mini-btn" data-editar-fala="' + d.id + '" title="Abrir e configurar"><i class="bi bi-pencil"></i></button>' +
               '<button type="button" class="tr-mini-btn tr-mini-btn-perigo" data-excluir-dialogo="' + d.id + '" title="Excluir fala"><i class="bi bi-trash"></i></button>' +
             '</div>' +
           '</article>';
  }

  function publicarFalas() {
    var s = slideAtual();
    window.TR_FALAS_SLIDE = !s ? [] : s.dialogos.map(function (d, i) {
      return { n: i + 1, t: String(d.texto || '').replace(/\s+/g, ' ').trim() };
    });
    if (window.trCanvasInspetor) { window.trCanvasInspetor(); }
  }

  function renderDialogos() {
    var s = slideAtual();
    publicarFalas();
    var alvo = $('listaDialogos');
    if (!s) { alvo.innerHTML = ''; fecharModalFala(); return; }
    if (!s.dialogos.length) {
      alvo.innerHTML = '<p class="tr-vazio-mini">Nenhuma fala neste slide. Use o + para criar a primeira ' +
                       'e depois clique nela para configurar a narração.</p>';
    } else {
      alvo.innerHTML = s.dialogos.map(cartaoFala).join('');
    }
    if (falaAberta && !dialogoPorId(falaAberta)) { fecharModalFala(); }
    else if (falaAberta) { renderModalFala(); }
  }

  function atualizarCartaoFala(d) {
    var cartao = $('listaDialogos').querySelector('.tr-falinha[data-id="' + d.id + '"]');
    if (!cartao) { return; }
    var res = cartao.querySelector('.tr-falinha-resumo');
    if (res) { res.innerHTML = resumoFala(d); }
    var dur = cartao.querySelector('.tr-falinha-dur');
    if (dur) { dur.textContent = duracaoFala(d); }
    var meta = cartao.querySelector('.tr-falinha-meta');
    if (meta) {
      meta.innerHTML = '<span class="tr-falinha-dur">' + esc(duracaoFala(d)) + '</span>' + selosFala(d);
    }
  }

  function elementosDoSlide(s) {
    var caixa = document.createElement('div');
    caixa.innerHTML = String(s.conteudo_html || '');
    var dados = caixa.querySelector('script[data-tr-canvas]');
    if (!dados) { return []; }
    try { return (JSON.parse(dados.textContent || '{}').els) || []; }
    catch (e) { return []; }
  }

  function verificar() {
    var achados = [];

    function anotar(i, nivel, texto) {
      achados.push({ slide: i + 1, nivel: nivel, texto: texto });
    }

    ESTADO.slides.forEach(function (s, i) {
      var els = elementosDoSlide(s);
      var ids = {};
      els.forEach(function (e) { ids[e.id] = e; });

      var falas = s.dialogos || [];
      var revelados = {};
      var alvos = [];

      falas.forEach(function (d) {
        (d.acoes || []).forEach(function (a) {
          var alvo = String(a.alvo || '').trim();
          if (!alvo) { return; }
          alvos.push({ alvo: alvo, tipo: a.tipo });
          if (a.tipo === 'revelar') { revelados[alvo.replace(/^#/, '')] = true; }
        });
      });

      if (!falas.length) {
        anotar(i, 'aviso', 'Slide sem nenhuma fala: ele abre e não explica nada.');
      }

      els.forEach(function (e) {
        if (e.oculto && !revelados[e.id]) {
          anotar(i, 'erro', 'O elemento #' + e.id + ' está marcado como "só aparece quando a fala revelar", ' +
                            'mas nenhuma fala tem ação de revelar apontando para ele.');
        }
      });

      falas.forEach(function (d, k) {
        (d.acoes || []).forEach(function (a) {
          if (a.tipo !== 'classe' || a.valor !== 'trc-img-destaque') { return; }
          var alvo = String(a.alvo || '').replace(/^#/, '').split(/\s/)[0];
          var alvoEl = ids[alvo];
          if (!alvoEl) { return; }
          if (alvoEl.t !== 'imagem') {
            anotar(i, 'erro', 'Na fala ' + (k + 1) + ', a ação de destaque de imagem aponta para ' +
                              '#' + alvo + ', que não é uma imagem.');
          }
        });
      });

      alvos.forEach(function (a) {
        if (a.alvo.charAt(0) !== '#') { return; }
        if (!ids[a.alvo.slice(1)]) {
          anotar(i, 'erro', 'A ação "' + a.tipo + '" aponta para ' + a.alvo + ', que não existe neste slide.');
        }
      });

      els.forEach(function (e) {
        if (e.t !== 'bloco' || !e.dados || !e.dados.guiado) { return; }
        var itens = e.dados.itens || [];
        var usados = {};
        var semPasso = 0;

        itens.forEach(function (it, k) {
          var f = Number(it.f) || 0;
          if (!f) { semPasso++; return; }
          if (f > falas.length) {
            anotar(i, 'erro', 'No bloco #' + e.id + ', o item ' + (k + 1) +
                              ' é pedido depois da fala ' + f + ', que não existe (o slide tem ' +
                              falas.length + ').');
          }
          if (usados[f]) {
            anotar(i, 'aviso', 'No bloco #' + e.id + ', os itens ' + usados[f] + ' e ' + (k + 1) +
                               ' são pedidos na mesma fala: só o primeiro será cobrado ali.');
          } else { usados[f] = k + 1; }
        });

        if (semPasso === itens.length) {
          anotar(i, 'aviso', 'O bloco #' + e.id + ' está marcado como guiado, mas nenhum item ' +
                             'tem fala definida: ele vai abrir de uma vez no fim da narração.');
        } else if (semPasso) {
          anotar(i, 'aviso', 'No bloco #' + e.id + ', ' + semPasso + ' item(ns) não têm fala definida ' +
                             'e só liberam no fim da narração.');
        }
      });

      var semAudio = falas.filter(function (d) {
        return !(d.voz_modo === 'arquivo' && d.audio_arquivo);
      }).length;
      var velhas = falas.filter(function (d) { return d.audio_desatualizado; }).length;
      if (semAudio) {
        anotar(i, 'aviso', semAudio + ' de ' + falas.length + ' fala(s) sem narração gravada.');
      }
      if (velhas) {
        anotar(i, 'aviso', velhas + ' fala(s) com o texto alterado depois da narração — regravar.');
      }
    });

    mostrarVerificacao(achados);
  }

  function mostrarVerificacao(achados) {
    var corpo = $('verificarCorpo');
    if (!corpo) { return; }

    var erros = achados.filter(function (a) { return a.nivel === 'erro'; }).length;
    var avisos = achados.length - erros;

    var html = '<div class="tr-verif-topo">' +
      (achados.length
        ? '<b>' + erros + '</b> problema(s) e <b>' + avisos + '</b> ponto(s) de atenção em ' +
          ESTADO.slides.length + ' slides.'
        : 'Nenhum problema encontrado nos ' + ESTADO.slides.length + ' slides.') +
      '</div>';

    if (achados.length) {
      var porSlide = {};
      achados.forEach(function (a) { (porSlide[a.slide] = porSlide[a.slide] || []).push(a); });
      Object.keys(porSlide).sort(function (a, b) { return a - b; }).forEach(function (n) {
        var s = ESTADO.slides[n - 1] || {};
        html += '<div class="tr-verif-slide"><b>' + n + '. ' + esc(s.titulo || 'Slide ' + n) + '</b>';
        porSlide[n].forEach(function (a) {
          html += '<p class="tr-verif-item is-' + a.nivel + '">' +
                  '<i class="bi bi-' + (a.nivel === 'erro' ? 'x-octagon-fill' : 'exclamation-triangle-fill') + '"></i>' +
                  '<span>' + esc(a.texto) + '</span></p>';
        });
        html += '</div>';
      });
    }

    corpo.innerHTML = html;
    window.trAbrirModal('modalVerificar');
  }

  var falaAberta = 0;
  var mfAba = 'texto';

  function painelTexto(d) {
    return '<label class="mf-rotulo">O que o instrutor fala</label>' +
           '<textarea class="tr-fala-texto mf-texto" rows="5" data-fala-campo="texto" ' +
             'placeholder="Escreva aqui o que o instrutor vai falar nesta linha.">' + esc(d.texto) + '</textarea>' +

           '<div class="mf-grade">' +
             '<label class="tr-mini-campo tr-mini-campo-sel">Pausa depois' +
               '<input type="number" min="0" max="10000" step="20" data-fala-campo="pausa_ms" ' +
                 'value="' + (Number(d.pausa_ms) || 320) + '"></label>' +
           '</div>' +
           '<p class="tr-dica">A pausa é o silêncio antes da próxima fala começar (1000 ms = 1 segundo).</p>' +

           '<div id="mfNarracao">' + blocoNarracao(d) + '</div>';
  }

  function painelInstrutor(d) {
    return '<label class="mf-rotulo">Como o instrutor interpreta</label>' +
           '<div class="tr-linha tr-linha-interp">' +
             chipSelect('emoji-smile', 'Expressão', 'humor', opcoes(CAT.humores, d.humor)) +
             chipSelect('hand-index-thumb', 'Gesto', 'gesto', opcoes(CAT.gestos, d.gesto || '')) +
             chipSelect('eye', 'Olhar', 'olhar', opcoes(CAT.olhares, d.olhar)) +
           '</div>' +

           '<div class="tr-fala-gesto-extra">' +
             '<label class="tr-mini-campo tr-mini-campo-sel">Braço' +
               '<select data-fala-campo="gesto_braco">' + opcoes(CAT.bracos, d.gesto_braco || 'auto') + '</select></label>' +
             '<label class="tr-mini-campo">Duração' +
               '<input type="number" min="1" max="12" step="0.5" data-fala-campo="gesto_duracao" ' +
                 'value="' + (Number(d.gesto_duracao) || 3) + '"><span>s</span></label>' +
             '<label class="tr-mini-campo tr-mini-faixa">Intensidade' +
               '<input type="range" min="0" max="1.5" step="0.05" data-fala-campo="gesto_intensidade" ' +
                 'data-eco="mfEcoGesto" value="' + interp(d.gesto_intensidade) + '">' +
               '<b id="mfEcoGesto">' + interp(d.gesto_intensidade).toFixed(2) + '</b></label>' +
           '</div>' +

           '<label class="mf-rotulo mf-rotulo-sep">Orientação nesta fala</label>' +
           '<div class="mf-grade">' +
             '<label class="tr-mini-campo tr-mini-campo-sel">Giro do tronco' +
               '<select data-fala-campo="tronco_giro">' + opcoes(CAT.troncos, d.tronco_giro || 'auto') + '</select></label>' +
             '<label class="tr-mini-campo tr-mini-campo-sel">Movimento da cabeça' +
               '<select data-fala-campo="cabeca_mov">' + opcoes(CAT.cabecaMovs, d.cabeca_mov || 'auto') + '</select></label>' +
             '<label class="tr-mini-campo tr-mini-faixa">Intensidade do olhar' +
               '<input type="range" min="0" max="1.5" step="0.05" data-fala-campo="olhar_forca" ' +
                 'data-eco="mfEcoOlhar" value="' + interp(d.olhar_forca) + '">' +
               '<b id="mfEcoOlhar">' + interp(d.olhar_forca).toFixed(2) + '</b></label>' +
           '</div>' +
           '<p class="tr-dica">Em <b>Automático</b> o instrutor usa o braço, o giro e o olhar do lado onde o ' +
             'conteúdo está — o que você define na aba <b>Slide</b>. Só troque aqui quando esta fala precisar ' +
             'de algo diferente. A prévia ao lado mostra o resultado na hora.</p>';
  }

  function painelAcoes(d) {
    var n = d.acoes.length;
    return '<div class="mf-acoes-head">' +
             '<label class="mf-rotulo mb-0">O que acontece no slide durante esta fala' +
               (n ? ' <em class="tr-acoes-cont">' + n + '</em>' : '') + '</label>' +
             '<button type="button" class="btn btn-sm tr-btn-ghost" data-add-acao="' + d.id + '">' +
               '<i class="bi bi-plus-lg"></i> Nova ação</button>' +
           '</div>' +
           (n ? d.acoes.map(function (a) { return blocoAcao(a, d); }).join('')
              : '<p class="tr-vazio-mini">Nada acontece no slide durante esta fala. Use <b>Nova ação</b> para ' +
                'revelar elementos, focar, abrir quadros ou comandar o instrutor.</p>');
  }

  function renderModalFala() {
    var d = dialogoPorId(falaAberta);
    var s = slideAtual();
    if (!d || !s) { return; }

    var pos = s.dialogos.findIndex(function (x) { return Number(x.id) === Number(d.id); });
    $('mfNum').textContent = (pos + 1) + ' de ' + s.dialogos.length;
    $('mfSlide').textContent = 'Slide ' + (idxSlide + 1) + ' — ' + (s.titulo || 'sem título');
    $('mfAnterior').disabled = pos <= 0;
    $('mfProxima').disabled = pos >= s.dialogos.length - 1;

    var caixa = $('mfFala');
    caixa.setAttribute('data-dialogo-id', d.id);
    caixa.classList.toggle('tem-gesto', !!String(d.gesto || '').trim());

    $('mfPainelTexto').innerHTML = painelTexto(d);
    $('mfPainelInstrutor').innerHTML = painelInstrutor(d);
    $('mfPainelAcoes').innerHTML = painelAcoes(d);
    $('mfContAcoes').textContent = d.acoes.length ? d.acoes.length : '';

    trocarAbaModal(mfAba);
    desenharMapaFala();
    aplicarPreviaFala(true);
  }

  function abrirModalFala(id) {
    var d = dialogoPorId(id);
    if (!d) { return; }
    falaAberta = Number(id);
    renderModalFala();
    window.trAbrirModal('modalFala');
    iniciarPreviaFala();
  }

  function fecharModalFala() {
    if (!falaAberta) { return; }
    falaAberta = 0;
    pausarPreviaFala();
    window.trFecharModal('modalFala');
  }

  function trocarAbaModal(nome) {
    mfAba = nome;
    Array.prototype.forEach.call(document.querySelectorAll('[data-mfaba]'), function (b) {
      b.classList.toggle('is-ativa', b.getAttribute('data-mfaba') === nome);
    });
    Array.prototype.forEach.call(document.querySelectorAll('[data-mfpainel]'), function (p) {
      p.classList.toggle('is-ativo', p.getAttribute('data-mfpainel') === nome);
    });
  }

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-mfaba]');
    if (b) { trocarAbaModal(b.getAttribute('data-mfaba')); }
  });

  function irParaFalaVizinha(passo) {
    var s = slideAtual();
    if (!s) { return; }
    var pos = s.dialogos.findIndex(function (x) { return Number(x.id) === falaAberta; });
    var alvo = s.dialogos[pos + passo];
    if (!alvo) { return; }
    falaAberta = Number(alvo.id);
    renderModalFala();
  }

  var previaFala = null;
  var previaErro = false;

  function iniciarPreviaFala() {
    var cfg = window.TR_AVATAR || {};
    if (previaErro) { return; }

    if (previaFala) {
      if (!previaFala.ativo && previaFala.vrm) {
        previaFala.ativo = true;
        previaFala.animar();
        previaFala.redimensionar();
      }
      aplicarPreviaFala(true);
      return;
    }
    if (!cfg.url || !window.Instrutor3D) {
      $('mfStatus').textContent = cfg.url
        ? 'Prévia indisponível neste navegador.'
        : 'Este treinamento ainda não tem um instrutor com modelo 3D.';
      return;
    }

    $('mfStatus').textContent = 'Preparando o instrutor…';
    previaFala = new window.Instrutor3D($('mfPalco'), {
      url: cfg.url, nome: cfg.nome, camera: $('mfCamera').value || cfg.camera,
      humor: cfg.humor, orientacao: cfg.orientacao || {}
    });
    if (previaFala.orientacaoBase) { previaFala.orientacaoBase(cfg.orientacao || {}); }

    previaFala.iniciar(function (frac) {
      $('mfStatus').textContent = 'Preparando o instrutor… ' + Math.round(frac * 100) + '%';
    }).then(function (ok) {
      if (!ok) {
        previaErro = true;
        $('mfStatus').textContent = previaFala.erro || 'Não foi possível carregar o instrutor.';
        return;
      }
      $('mfStatus').textContent = '';
      previaFala.redimensionar();
      aplicarPreviaFala(true);
    });
  }

  function pausarPreviaFala() {
    if (previaFala && previaFala.ativo) { previaFala.ativo = false; }
  }

  function orientacaoDaFala() {
    var s = slideAtual() || {};
    var base = (window.TR_AVATAR && window.TR_AVATAR.orientacao) || {};
    return {
      posicao: s.instrutor_posicao || 'direita',
      ladoConteudo: s.instrutor_lado_conteudo || 'auto',
      giroCorpo: (s.instrutor_giro && s.instrutor_giro !== 'herdar')
        ? s.instrutor_giro : (base.giroCorpo || 'leve')
    };
  }

  function aplicarPreviaFala(repetirGesto) {
    if (!previaFala || !previaFala.ativo) { return; }
    var d = dialogoPorId(falaAberta);
    if (!d) { return; }

    previaFala.posicionar(orientacaoDaFala().posicao);
    if (previaFala.orientacao) { previaFala.orientacao(orientacaoDaFala()); }
    previaFala.humor(d.humor || 'neutral');
    previaFala.olhar(d.olhar || 'camera', interp(d.olhar_forca));

    var opc = {
      braco: d.gesto_braco || 'auto',
      intensidade: interp(d.gesto_intensidade),
      tronco: d.tronco_giro || 'auto',
      cabeca: d.cabeca_mov || 'auto'
    };
    if (repetirGesto !== false) {
      previaFala.gesto(d.gesto || null, 20, opc);
    }
  }

  function desenharMapaFala() {
    var mapa = $('mfMapa');
    var eco = $('mfEco');
    if (!mapa) { return; }

    var o = orientacaoDaFala();
    var lado = o.ladoConteudo;
    if (lado === 'auto') {
      if (o.posicao === 'esquerda' || o.posicao === 'centro-esquerda') { lado = 'direita'; }
      else if (o.posicao === 'direita' || o.posicao === 'centro-direita') { lado = 'esquerda'; }
      else { lado = 'centro'; }
    }

    var celulas;
    if (o.posicao === 'centro') {
      celulas = [['is-conteudo', 'Conteúdo'], ['is-instrutor', 'Instrutor'], ['is-conteudo', 'Conteúdo']];
    } else if (o.posicao === 'esquerda' || o.posicao === 'centro-esquerda') {
      celulas = [['is-instrutor', 'Instrutor'], ['is-conteudo', 'Conteúdo']];
    } else {
      celulas = [['is-conteudo', 'Conteúdo'], ['is-instrutor', 'Instrutor']];
    }
    mapa.innerHTML = celulas.map(function (c) {
      return '<span class="' + c[0] + '">' + c[1] + '</span>';
    }).join('');

    if (eco) {
      var d = dialogoPorId(falaAberta) || {};
      var forcado = d.gesto_braco || 'auto';
      var braco;
      if (forcado === 'direito') { braco = 'o braço <b>direito</b> (à esquerda da tela)'; }
      else if (forcado === 'esquerdo') { braco = 'o braço <b>esquerdo</b> (à direita da tela)'; }
      else if (forcado === 'ambos') { braco = '<b>os dois braços</b>'; }
      else if (lado === 'direita') { braco = 'o braço <b>esquerdo</b> (à direita da tela)'; }
      else if (lado === 'esquerda') { braco = 'o braço <b>direito</b> (à esquerda da tela)'; }
      else { braco = 'a mão dominante do instrutor'; }
      eco.innerHTML = 'Conteúdo ' + (lado === 'centro' ? 'ao centro' : ('à <b>' + lado + '</b> da tela') ) +
        ' · ao gesticular ele usa ' + braco + '.';
    }
  }

  if ($('mfCamera')) {
    $('mfCamera').addEventListener('change', function () {
      if (previaFala && previaFala.camera) { previaFala.camera(this.value); }
    });
  }
  if ($('mfRepetir')) {
    $('mfRepetir').addEventListener('click', function () { aplicarPreviaFala(true); });
  }
  if ($('mfExcluir')) {
    $('mfExcluir').addEventListener('click', function () {
      var id = falaAberta;
      if (!id) { return; }
      window.trConfirmar({
        titulo: 'Excluir fala',
        texto: 'A fala, sua narração e suas ações serão removidas.',
        confirmar: 'Excluir', perigo: true
      }).then(function (ok) {
        if (!ok) { return; }
        fecharModalFala();
        return salvar('dialogo_excluir', { id: id }, false)
          .then(recarregar).then(renderDialogos).then(renderSlides);
      }).catch(function () {});
    });
  }
  if ($('mfAnterior')) {
    $('mfAnterior').addEventListener('click', function () { irParaFalaVizinha(-1); });
  }
  if ($('mfProxima')) {
    $('mfProxima').addEventListener('click', function () { irParaFalaVizinha(1); });
  }
  if ($('modalFala')) {
    var obs = new MutationObserver(function () {
      if (falaAberta && !$('modalFala').classList.contains('is-aberto')) {
        falaAberta = 0;
        pausarPreviaFala();
      }
    });
    obs.observe($('modalFala'), { attributes: true, attributeFilter: ['class'] });
  }

  function itemDestino(cont, seletor, y, ignorar) {
    var itens = Array.prototype.filter.call(cont.querySelectorAll(seletor), function (n) {
      return n !== ignorar;
    });
    for (var i = 0; i < itens.length; i++) {
      var r = itens[i].getBoundingClientRect();
      if (y < r.top + r.height / 2) { return itens[i]; }
    }
    return null;
  }

  function arrastavel(cont, seletor, aoSoltar) {
    if (!cont) { return; }
    var arrastado = null;

    cont.addEventListener('dragstart', function (ev) {
      var it = ev.target.closest ? ev.target.closest(seletor) : null;
      if (!it || !cont.contains(it)) { return; }
      arrastado = it;
      cont.classList.add('esta-arrastando');
      setTimeout(function () { if (arrastado) { arrastado.classList.add('is-arrastando'); } }, 0);
      try {
        ev.dataTransfer.effectAllowed = 'move';
        ev.dataTransfer.setData('text/plain', it.getAttribute('data-id') || '');
      } catch (e) {}
    });

    cont.addEventListener('dragover', function (ev) {
      if (!arrastado) { return; }
      ev.preventDefault();
      try { ev.dataTransfer.dropEffect = 'move'; } catch (e) {}
      var alvo = itemDestino(cont, seletor, ev.clientY, arrastado);
      if (alvo === null) { cont.appendChild(arrastado); }
      else if (alvo !== arrastado) { cont.insertBefore(arrastado, alvo); }
    });

    cont.addEventListener('drop', function (ev) { ev.preventDefault(); });

    cont.addEventListener('dragend', function () {
      if (!arrastado) { return; }
      arrastado.classList.remove('is-arrastando');
      cont.classList.remove('esta-arrastando');
      arrastado = null;
      var ids = Array.prototype.map.call(cont.querySelectorAll(seletor), function (n) {
        return Number(n.getAttribute('data-id'));
      });
      aoSoltar(ids);
    });
  }

  arrastavel($('listaSlides'), '.tr-item-slide', function (ids) {
    if (!ids.length) { return; }
    var atual = ESTADO.slides[idxSlide] || null;
    ESTADO.slides.sort(function (a, b) {
      return ids.indexOf(Number(a.id)) - ids.indexOf(Number(b.id));
    });
    idxSlide = Math.max(0, ESTADO.slides.indexOf(atual));
    renderSlides();
    salvar('slide_reordenar', { ordens: ESTADO.slides.map(function (s) { return s.id; }) })
      .catch(function () {});
  });

  arrastavel($('listaDialogos'), '.tr-falinha', function (ids) {
    var s = slideAtual();
    if (!s) { return; }
    s.dialogos.sort(function (a, b) {
      return ids.indexOf(Number(a.id)) - ids.indexOf(Number(b.id));
    });
    renderDialogos();
    renderSlides();
    salvar('dialogo_reordenar', { ordens: s.dialogos.map(function (d) { return d.id; }) })
      .catch(function () {});
  });

  function gravarDialogo(elFala) {
    var d = dialogoPorId(Number(elFala.getAttribute('data-dialogo-id')));
    if (!d) { return; }
    Array.prototype.forEach.call(elFala.querySelectorAll('[data-fala-campo]'), function (c) {
      var nome = c.getAttribute('data-fala-campo');
      d[nome] = c.type === 'checkbox' ? (c.checked ? 1 : 0) : c.value;
    });
    elFala.classList.toggle('tem-gesto', !!String(d.gesto || '').trim());
    atualizarCartaoFala(d);
    desenharMapaFala();
    aplicarPreviaFala(true);

    salvar('dialogo_salvar', {
      id: d.id, texto: d.texto, balao: d.balao || '', humor: d.humor, gesto: d.gesto,
      gesto_duracao: d.gesto_duracao, gesto_espelhado: d.gesto_espelhado,
      gesto_braco: d.gesto_braco || 'auto',
      gesto_intensidade: d.gesto_intensidade != null ? d.gesto_intensidade : 1,
      tronco_giro: d.tronco_giro || 'auto', cabeca_mov: d.cabeca_mov || 'auto',
      olhar_forca: d.olhar_forca != null ? d.olhar_forca : 1,
      olhar: d.olhar, pausa_ms: d.pausa_ms
    }).then(function (j) {
      if (j.audio_desatualizado && !d.audio_desatualizado) {
        d.audio_desatualizado = true;
        if (falaAberta === Number(d.id) && $('mfNarracao')) {
          $('mfNarracao').innerHTML = blocoNarracao(d);
        }
        atualizarCartaoFala(d);
        window.trToast('O texto mudou: gere a narração novamente para esta fala.', 'aviso');
      }
    }).catch(function () {});
  }

  function gravarAcao(elAcao) {
    var elFala = elAcao.closest('.tr-fala');
    var d = dialogoPorId(Number(elFala.getAttribute('data-dialogo-id')));
    var a = acaoPorId(d, Number(elAcao.getAttribute('data-acao-id')));
    if (!a) { return; }
    Array.prototype.forEach.call(elAcao.querySelectorAll('[data-acao-campo]'), function (c) {
      var nome = c.getAttribute('data-acao-campo');
      a[nome] = c.type === 'checkbox' ? (c.checked ? 1 : 0) : c.value;
    });

    var prontaAtual = ACOES_PRONTAS[tipoUI(a)];
    if (prontaAtual) {
      a.valor = prontaAtual.valor;
      a.remover = prontaAtual.remover;
    }
    salvar('acao_salvar', {
      id: a.id, dialogo_id: d.id, momento: parseFloat(a.momento) || 0, fase: a.fase, tipo: a.tipo,
      alvo: a.alvo || '', valor: a.valor || '', rotulo: a.rotulo || '', titulo: a.titulo || '',
      html_conteudo: a.html_conteudo || '', remover: a.remover || 0
    }).catch(function () {});
  }

  function cfgVoz() {
    var v = window.TR_VOZ || {};
    return {
      voz: v.voz || 'pf_dora',
      velocidade: Number(v.velocidade || 1),
      tom: Number(v.tom || 1),
      pausaMs: Number(v.pausaMs || 260),
      ganho: Number(v.ganho || 1),
      qualidade: v.qualidade || 'alta',
      kbps: Number(v.kbps || 96),
      onProgressoModelo: function (frac) {
        var barra = $('vozProgresso');
        var txt = $('vozStatus');
        if (barra) { barra.style.width = Math.round(frac * 100) + '%'; }
        if (txt) { txt.textContent = 'Baixando o modelo de voz… ' + Math.round(frac * 100) + '%'; }
      }
    };
  }

  function narrarFala(dialogoId, texto, botao) {
    if (!String(texto || '').trim()) {
      window.trToast('Escreva o texto da fala antes de gerar a narração.', 'aviso');
      return Promise.resolve();
    }
    if (botao) {
      botao.disabled = true;
      botao.innerHTML = '<i class="bi bi-hourglass-split"></i> Gerando…';
    }
    return window.TrVoz.gerar(texto, cfgVoz())
      .then(function (r) {
        var perdidos = Object.keys(r.desconhecidos || {});
        if (perdidos.length) {
          console.warn('[TrVoz] fonemas fora do vocabulário do modelo:', perdidos.join(' '));
        }
        if (r.avisos && r.avisos.length) {
          console.warn('[TrVoz] ' + r.avisos.join('\n'));
          window.trToast('Narração gerada, mas ' + r.avisos.length +
            ' trecho(s) saíram fora do ritmo esperado. Confira o áudio; ' +
            'quebrar a frase em duas costuma resolver.', 'aviso');
        }
        var arq = new File([r.blob], 'fala-' + dialogoId + '.mp3', { type: 'audio/mpeg' });
        return window.trApi('narrar_salvar', {}, {
          dialogo_id: String(dialogoId),
          duracao_ms: String(r.duracaoMs || 0),
          lipsync: JSON.stringify(r.lipsync || {}),
          arquivo: arq
        });
      })
      .catch(function (e) {
        if (botao) {
          botao.disabled = false;
          botao.innerHTML = '<i class="bi bi-soundwave"></i> Gerar narração';
        }
        window.trToast(e.message, 'erro');
        throw e;
      });
  }

  ['slideTitulo', 'slideLayout', 'slideTransicao', 'slideFundoTipo',
   'slideFundoCor', 'slideFundoCor2', 'slideFundoAngulo', 'slideFundoEscurecer',
   'slideCorTexto', 'slideCorTextoAuto', 'slideInstrutorVisivel', 'slidePosicao',
   'slideLadoConteudo', 'slideGiro',
   'slideCamera', 'slideEscala', 'slideConteudo'].forEach(function (id) {
    $(id).addEventListener('change', function () {
      if (id === 'slideFundoTipo') { aplicarVisibilidadeFundo(); }
      if (id === 'slideCorTextoAuto') { $('slideCorTexto').disabled = this.checked; }
      salvarSlide();
    });
  });
  $('slideFundoAngulo').addEventListener('input', function () { $('slideAnguloVal').textContent = this.value + '°'; });
  $('slideFundoEscurecer').addEventListener('input', function () { $('slideEscurecerVal').textContent = this.value + '%'; });
  $('slideEscala').addEventListener('input', function () { $('slideEscalaVal').textContent = Number(this.value).toFixed(2); });

  $('slideFundoImagem').addEventListener('change', function () {
    var f = this.files && this.files[0];
    var s = slideAtual();
    if (!f || !s) { return; }
    window.trApi('midia_upload', {}, {
      apresentacao_id: String(API_ID), slide_id: String(s.id), tipo: 'fundo', arquivo: f
    }).then(function (j) {
      s.fundo_imagem = j.arquivo;
      s.fundo_tipo = 'imagem';
      $('slideFundoTipo').value = 'imagem';
      $('slideFundoNome').textContent = 'Atual: ' + j.arquivo;
      aplicarVisibilidadeFundo();
      window.trToast(j.msg);
    }).catch(function (e) { window.trToast(e.message, 'erro'); });
  });

  $('btnAddSlide').addEventListener('click', function () {
    salvar('slide_criar', { apresentacao_id: API_ID }, false)
      .then(recarregar)
      .then(function () { selecionarSlide(ESTADO.slides.length - 1); })
      .catch(function () {});
  });

  $('btnExcluirSlide').addEventListener('click', function () {
    var s = slideAtual();
    if (!s) { return; }
    window.trConfirmar({
      titulo: 'Excluir slide',
      texto: 'O slide "' + s.titulo + '", suas falas, narrações e ações serão apagados.',
      confirmar: 'Excluir', perigo: true
    }).then(function (ok) {
      if (!ok) { return; }
      return salvar('slide_excluir', { id: s.id }, false)
        .then(recarregar)
        .then(function () { selecionarSlide(Math.max(0, idxSlide - 1)); });
    }).catch(function () {});
  });

  $('btnAddDialogo').addEventListener('click', function () {
    var s = slideAtual();
    if (!s) { window.trToast('Crie um slide antes de adicionar falas.', 'aviso'); return; }
    salvar('dialogo_criar', { slide_id: s.id }, false)
      .then(recarregar).then(function () {
        var s2 = slideAtual();
        renderDialogos();
        renderSlides();
        if (s2 && s2.dialogos.length) { abrirModalFala(s2.dialogos[s2.dialogos.length - 1].id); }
      }).catch(function () {});
  });

  if ($('btnNarrarTodas')) {
    $('btnNarrarTodas').addEventListener('click', function () {
      var s = slideAtual();
      if (!s || !s.dialogos.length) { window.trToast('Este slide não tem falas.', 'aviso'); return; }
      window.trConfirmar({
        titulo: 'Gerar narração do slide',
        texto: 'As ' + s.dialogos.length + ' falas deste slide serão narradas. Áudios existentes são substituídos.',
        confirmar: 'Gerar'
      }).then(function (ok) {
        if (!ok) { return; }
        var fila = s.dialogos.slice();
        var feitas = 0;
        window.trToast('Gerando ' + fila.length + ' narrações. Mantenha a aba aberta.');
        function proxima() {
          if (!fila.length) {
            window.trToast(feitas + ' narração(ões) gerada(s).');
            return recarregar().then(renderDialogos);
          }
          var d = fila.shift();
          return narrarFala(d.id, d.texto, null)
            .then(function () { feitas++; return proxima(); })
            .catch(function () { return proxima(); });
        }
        return proxima();
      }).catch(function () {});
    });
  }

  if ($('btnNarrarTudo')) {
    var cancelarNarracaoTudo = false;

    function narracaoTudoProgresso(feitas, total) {
      var wrap = $('narracaoProgressoWrap');
      var txt = $('narracaoProgressoTxt');
      var fill = $('narracaoProgressoFill');
      if (!wrap) { return; }
      wrap.hidden = false;
      var pct = total ? Math.round((feitas / total) * 100) : 0;
      if (fill) { fill.style.width = pct + '%'; }
      if (txt) { txt.textContent = 'Gerando narrações… ' + feitas + '/' + total + ' (' + pct + '%)'; }
    }

    function narracaoTudoOcultar() {
      var wrap = $('narracaoProgressoWrap');
      if (wrap) { wrap.hidden = true; }
    }

    $('btnNarrarTudo').addEventListener('click', function () {
      var fila = [];
      var semTexto = 0;
      ESTADO.slides.forEach(function (s) {
        s.dialogos.forEach(function (d) {
          if (String(d.texto || '').trim()) { fila.push(d); } else { semTexto++; }
        });
      });
      if (!fila.length) {
        window.trToast('Nenhuma fala com texto para narrar neste treinamento.', 'aviso');
        return;
      }

      window.trConfirmar({
        titulo: 'Gerar narração de tudo',
        texto: 'As ' + fila.length + ' falas de todos os ' + ESTADO.slides.length + ' slides serão narradas. ' +
               'Áudios já existentes são substituídos.' +
               (semTexto ? ' (' + semTexto + ' fala(s) sem texto serão ignoradas.)' : ''),
        confirmar: 'Gerar tudo'
      }).then(function (ok) {
        if (!ok) { return; }
        cancelarNarracaoTudo = false;
        var total = fila.length, feitas = 0, falhas = 0;
        $('btnNarrarTudo').disabled = true;
        if ($('btnNarrarTodas')) { $('btnNarrarTodas').disabled = true; }
        narracaoTudoProgresso(0, total);
        window.trToast('Gerando ' + total + ' narrações. Mantenha a aba aberta.');

        function proxima() {
          if (cancelarNarracaoTudo || !fila.length) {
            $('btnNarrarTudo').disabled = false;
            if ($('btnNarrarTodas')) { $('btnNarrarTodas').disabled = false; }
            narracaoTudoOcultar();
            window.trToast(
              (cancelarNarracaoTudo ? 'Geração cancelada. ' : '') +
              feitas + ' narração(ões) gerada(s)' + (falhas ? ', ' + falhas + ' com falha' : '') + '.'
            );
            return recarregar().then(renderDialogos);
          }
          var d = fila.shift();
          return narrarFala(d.id, d.texto, null)
            .then(function () { feitas++; })
            .catch(function () { falhas++; })
            .then(function () {
              narracaoTudoProgresso(feitas + falhas, total);
              return proxima();
            });
        }
        return proxima();
      }).catch(function () {});
    });

    if ($('btnCancelarNarracaoTudo')) {
      $('btnCancelarNarracaoTudo').addEventListener('click', function () {
        cancelarNarracaoTudo = true;
      });
    }
  }

  document.addEventListener('click', function (ev) {
    var el;

    el = ev.target.closest('[data-slide-idx]');
    if (el) {
      selecionarSlide(Number(el.getAttribute('data-slide-idx')));
      return;
    }

    el = ev.target.closest('[data-editar-fala]');
    if (el) { abrirModalFala(Number(el.getAttribute('data-editar-fala'))); return; }

    el = ev.target.closest('.tr-falinha');
    if (el && !ev.target.closest('.tr-falinha-acoes')) {
      abrirModalFala(Number(el.getAttribute('data-id')));
      return;
    }

    el = ev.target.closest('[data-excluir-dialogo]');
    if (el) {
      var idEx = Number(el.getAttribute('data-excluir-dialogo'));
      window.trConfirmar({
        titulo: 'Excluir fala',
        texto: 'A fala, sua narração e suas ações serão removidas.',
        confirmar: 'Excluir', perigo: true
      }).then(function (ok) {
        if (!ok) { return; }
        if (falaAberta === idEx) { fecharModalFala(); }
        return salvar('dialogo_excluir', { id: idEx }, false)
          .then(recarregar).then(renderDialogos).then(renderSlides);
      }).catch(function () {});
      return;
    }

    el = ev.target.closest('[data-add-acao]');
    if (el) {
      salvar('acao_salvar', {
        dialogo_id: Number(el.getAttribute('data-add-acao')),
        tipo: 'revelar', fase: 'durante', momento: 0.3, alvo: ''
      }).then(recarregar).then(renderDialogos).catch(function () {});
      return;
    }

    el = ev.target.closest('[data-excluir-acao]');
    if (el) {
      salvar('acao_excluir', { id: Number(el.getAttribute('data-excluir-acao')) })
        .then(recarregar).then(renderDialogos).catch(function () {});
      return;
    }

    el = ev.target.closest('[data-alvo-manual-alternar]');
    if (el) {
      var elAcaoM = el.closest('.tr-acao');
      var elFalaM = el.closest('.tr-fala');
      var dM = dialogoPorId(Number(elFalaM.getAttribute('data-dialogo-id')));
      var aM = acaoPorId(dM, Number(elAcaoM.getAttribute('data-acao-id')));
      if (aM) {
        aM._alvoManual = el.getAttribute('data-alvo-manual-alternar') === '1';
        elAcaoM.querySelector('.tr-acao-campos').innerHTML = campoAcao(aM);
      }
      return;
    }

    el = ev.target.closest('[data-narrar]');
    if (el) {
      var idN = Number(el.getAttribute('data-narrar'));
      var elFala = el.closest('.tr-fala');
      narrarFala(idN, elFala.querySelector('[data-fala-campo="texto"]').value, el)
        .then(function () {
          window.trToast('Narração gerada.');
          return recarregar().then(renderDialogos);
        }).catch(function () {});
      return;
    }

    el = ev.target.closest('[data-remover-narracao]');
    if (el) {
      salvar('narrar_remover', { dialogo_id: Number(el.getAttribute('data-remover-narracao')) }, false)
        .then(recarregar).then(renderDialogos).catch(function () {});
    }
  });

  document.addEventListener('change', function (ev) {
    var elFala = ev.target.closest('.tr-fala');
    if (!elFala) { return; }
    var elAcao = ev.target.closest('.tr-acao');
    if (elAcao) {
      if (ev.target.matches('[data-acao-tipo-ui]') || ev.target.matches('[data-acao-campo="fase"]')) {
        var d = dialogoPorId(Number(elFala.getAttribute('data-dialogo-id')));
        var a = acaoPorId(d, Number(elAcao.getAttribute('data-acao-id')));
        var escolha = elAcao.querySelector('[data-acao-tipo-ui]').value;
        aplicarTipoUI(a, escolha);
        elAcao.querySelector('[data-acao-campo="tipo"]').value = a.tipo;
        a.fase = elAcao.querySelector('[data-acao-campo="fase"]').value;
        elAcao.querySelector('.tr-acao-campos').innerHTML = campoAcao(a);
        elAcao.querySelector('.tr-acao-momento').hidden = (a.fase === 'apos');
      } else if (ev.target.matches('[data-acao-alvo-el]') || ev.target.matches('[data-acao-alvo-sub]')) {
        var d2 = dialogoPorId(Number(elFala.getAttribute('data-dialogo-id')));
        var a2 = acaoPorId(d2, Number(elAcao.getAttribute('data-acao-id')));
        var elSel = elAcao.querySelector('[data-acao-alvo-el]');
        var subSel = elAcao.querySelector('[data-acao-alvo-sub]');
        var idEl = elSel ? elSel.value : '';
        var subVal = subSel ? subSel.value : '';
        a2.alvo = idEl ? ('#' + idEl + (subVal ? ' ' + subVal : '')) : '';
        if (ev.target.matches('[data-acao-alvo-el]')) {
          elAcao.querySelector('.tr-acao-campos').innerHTML = campoAcao(a2);
        } else {
          var hid = elAcao.querySelector('[data-acao-campo="alvo"]');
          if (hid) { hid.value = a2.alvo; }
        }
      }
      gravarAcao(elAcao);
      return;
    }
    if (ev.target.matches('[data-fala-campo]')) { gravarDialogo(elFala); }
  });

  document.addEventListener('input', function (ev) {
    var eco = ev.target.getAttribute && ev.target.getAttribute('data-eco');
    if (!eco) { return; }
    var alvo = document.getElementById(eco);
    if (alvo) { alvo.textContent = Number(ev.target.value).toFixed(2); }
  });

  document.addEventListener('click', function (ev) {
    var alvo = ev.target.closest('[data-palavra]');
    if (!alvo) { return; }
    var elAcao = alvo.closest('.tr-acao');
    var elFala = alvo.closest('.tr-fala');
    if (!elAcao || !elFala) { return; }

    var d = dialogoPorId(Number(elFala.getAttribute('data-dialogo-id')));
    var a = acaoPorId(d, Number(elAcao.getAttribute('data-acao-id')));
    if (!a) { return; }

    var k = Number(alvo.getAttribute('data-palavra'));
    var total = Number(alvo.getAttribute('data-total')) || palavrasDaFala(d.texto).length || 1;
    a.momento = k < 0 ? 0 : Math.min(0.99, k / total);

    elAcao.querySelector('.tr-acao-momento').innerHTML = trilhaMomento(a, d);
    gravarAcao(elAcao);
  });

  document.addEventListener('change', function (ev) {
    if (!ev.target.matches('[data-fala-campo="texto"]')) { return; }
    var elFala = ev.target.closest('.tr-fala');
    var d = dialogoPorId(Number(elFala.getAttribute('data-dialogo-id')));
    if (!d) { return; }
    Array.prototype.forEach.call(elFala.querySelectorAll('.tr-acao'), function (elAcao) {
      var a = acaoPorId(d, Number(elAcao.getAttribute('data-acao-id')));
      if (a) { elAcao.querySelector('.tr-acao-momento').innerHTML = trilhaMomento(a, d); }
    });
  });

  $('btnDados').addEventListener('click', function () { window.trAbrirModal('modalDados'); });
  if ($('btnVerificar')) { $('btnVerificar').addEventListener('click', verificar); }

  (function () {
    var menu = $('menuExportar');
    var botao = $('btnExportar');
    if (!menu || !botao) { return; }

    function fechar() {
      menu.classList.remove('is-aberto');
      botao.setAttribute('aria-expanded', 'false');
    }

    botao.addEventListener('click', function (ev) {
      ev.stopPropagation();
      var abrir = !menu.classList.contains('is-aberto');
      menu.classList.toggle('is-aberto', abrir);
      botao.setAttribute('aria-expanded', abrir ? 'true' : 'false');
    });

    document.addEventListener('click', function (ev) {
      if (!menu.contains(ev.target)) { fechar(); }
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape') { fechar(); }
    });

    Array.prototype.forEach.call(menu.querySelectorAll('[data-exportar]'), function (link) {
      link.addEventListener('click', function (ev) {
        ev.preventDefault();
        fechar();
        var destino = this.getAttribute('href');
        if (window.trCanvasFinalizar) { window.trCanvasFinalizar(); }
        window.trToast('Gerando o documento…');
        salvarSlide().then(function () { window.location.href = destino; });
      });
    });
  })();
  $('apSlug').addEventListener('input', function () { $('apSlugEcho').textContent = this.value; });

  $('apLogo').addEventListener('change', function () {
    var f = this.files && this.files[0];
    if (!f) { return; }
    window.trApi('midia_upload', {}, { apresentacao_id: String(API_ID), tipo: 'logo', arquivo: f })
      .then(function (j) { window.trToast(j.msg); })
      .catch(function (e) { window.trToast(e.message, 'erro'); });
  });

  $('btnSalvarDados').addEventListener('click', function () {
    salvar('apres_salvar', {
      id: API_ID,
      titulo: $('apTitulo').value.trim(),
      subtitulo: $('apSubtitulo').value.trim(),
      marca: $('apMarca').value.trim(),
      slug: $('apSlug').value.trim(),
      status: $('apStatus').value,
      tema: $('apTema').value,
      cor_primaria: $('apCorPrimaria').value,
      cor_acento: $('apCorAcento').value,
      cor_fundo: $('apCorFundo').value,
      avatar_camera: $('apCamera').value,
      avanco_auto: $('apAvancoAuto').checked ? 1 : 0
    }, false).then(function (j) {
      AP.tema = $('apTema').value;
      AP.cor_primaria = $('apCorPrimaria').value;
      AP.cor_acento = $('apCorAcento').value;
      AP.cor_fundo = $('apCorFundo').value;
      window.TR_CAMERA_PADRAO = $('apCamera').value;
      $('apSlug').value = j.slug;
      $('apSlugEcho').textContent = j.slug;
      var chip = $('statusAtual');
      chip.textContent = $('apStatus').selectedOptions[0].textContent;
      chip.className = 'tr-status tr-status-' + $('apStatus').value;
      window.trFecharModal('modalDados');
    }).catch(function () {});
  });

  if ($('btnPublicar')) {
    $('btnPublicar').addEventListener('click', function () {
      var b = $('btnPublicar');
      var ativo = b.getAttribute('data-publico') === '1';
      window.trConfirmar({
        titulo: ativo ? 'Encerrar link público' : 'Publicar link',
        texto: ativo ? 'O link deixará de funcionar fora do sistema.'
                     : 'A apresentação ficará acessível pelo link, sem login, e o status passa a "publicado".',
        confirmar: ativo ? 'Encerrar' : 'Publicar', perigo: ativo
      }).then(function (ok) {
        if (!ok) { return; }
        return salvar('apres_publicar', { id: API_ID }, false).then(function (j) {
          b.setAttribute('data-publico', j.publico ? '1' : '0');
          b.querySelector('i').className = 'bi bi-' + (j.publico ? 'globe2' : 'lock');
          b.querySelector('span').textContent = j.publico ? 'Link público ativo' : 'Publicar link';
          if (j.publico) { window.trToast('Link: ' + window.TR_LINK_BASE + $('apSlug').value); }
        });
      }).catch(function () {});
    });
  }

  function marcarInstrutor(id) {
    Array.prototype.forEach.call(document.querySelectorAll('.tr-instr-card'), function (c) {
      c.classList.toggle('is-ativo', Number(c.getAttribute('data-instrutor')) === Number(id));
    });
  }

  document.addEventListener('click', function (ev) {
    var card = ev.target.closest('.tr-instr-card[data-instrutor]');
    if (!card) { return; }

    var id = Number(card.getAttribute('data-instrutor'));
    if (card.classList.contains('is-indisponivel')) {
      window.trToast('Este instrutor ainda não tem modelo 3D enviado.', 'aviso');
      return;
    }

    salvar('av_usar', { apresentacao_id: API_ID, id: id }, false)
      .then(function () {
        marcarInstrutor(id);
        AP.avatar_id = id;
        ESTADO.apresentacao.avatar_id = id;

        var dados = card.getAttribute('data-voz');
        if (dados) { window.TR_VOZ = JSON.parse(dados); }

        Array.prototype.forEach.call(document.querySelectorAll('.tr-instr-selo'), function (e) {
          e.remove();
        });
        var foto = card.querySelector('.tr-instr-foto');
        if (foto) {
          var selo = document.createElement('em');
          selo.className = 'tr-instr-selo';
          selo.innerHTML = '<i class="bi bi-check-lg"></i> narrando';
          foto.appendChild(selo);
        }
        window.trToast('Instrutor definido: ' + (card.getAttribute('data-nome') || '') + '.');
      })
      .catch(function () {});
  });

  function recarregar() {
    return window.trApi('estrutura', { id: API_ID }).then(function (j) {
      ESTADO.apresentacao = j.apresentacao;
      AP = j.apresentacao;
      ESTADO.slides = j.slides;
      if (idxSlide >= ESTADO.slides.length) { idxSlide = Math.max(0, ESTADO.slides.length - 1); }
      renderSlides();
      preencherSlide();
      return j;
    });
  }

  selecionarSlide(0);
  renderSlides();
})();
