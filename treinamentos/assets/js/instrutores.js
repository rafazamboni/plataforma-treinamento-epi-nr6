(function () {
  'use strict';

  var CAT = window.TRI_CAT || { gestos: {}, humores: {} };
  var GERENCIA = !!window.TRI_GERENCIA;
  var URL_PAG = window.TRI_URL;

  var atual = null;
  var preview = null;

  function $(id) { return document.getElementById(id); }
  function esc(t) {
    return String(t == null ? '' : t).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }
  function num(v, casas) { return Number(v).toFixed(casas == null ? 2 : casas).replace('.', ','); }

  document.addEventListener('click', function (ev) {
    var item = ev.target.closest('.tri-item');
    if (!item) { return; }
    selecionar(JSON.parse(item.getAttribute('data-json')), item);
  });

  function selecionar(av, item) {
    atual = av;

    Array.prototype.forEach.call(document.querySelectorAll('.tri-item'), function (b) {
      b.classList.toggle('is-ativo', b === item);
    });

    $('triEditor').hidden = false;

    $('avNome').value = av.nome || '';
    $('avDescricao').value = av.descricao || '';
    $('avAtivo').checked = av.ativo !== 0;
    $('avCamera').value = av.camera || 'upper';
    $('avHumor').value = av.humor || 'neutral';
    $('avArquivoNome').textContent = av.arquivo
      ? 'Atual: ' + av.arquivo
      : 'Nenhum modelo enviado ainda.';

    $('avVoz').value = av.voz_nome || 'pf_dora';
    $('avVelocidade').value = av.voz_velocidade || 1;
    $('avTom').value = av.voz_tom || 1;
    $('avPausa').value = av.voz_pausa_ms || 260;
    $('avGanho').value = av.voz_ganho || 1;
    $('avQualidade').value = av.voz_qualidade || 'alta';
    $('avKbps').value = String(av.voz_kbps || 96);

    $('avMaoDominante').value = av.mao_dominante || 'direita';
    $('avOrientAuto').checked = Number(av.orient_auto == null ? 1 : av.orient_auto) === 1;
    $('avGiroCorpo').value = av.giro_corpo || 'leve';
    $('avGiroQuadril').checked = Number(av.giro_quadril || 0) === 1;
    $('avForcaTronco').value = av.forca_tronco == null ? 1 : av.forca_tronco;
    $('avForcaCabeca').value = av.forca_cabeca == null ? 1 : av.forca_cabeca;
    $('avForcaGesto').value = av.forca_gesto == null ? 1 : av.forca_gesto;
    $('avSuavidade').value = av.suavidade == null ? 1 : av.suavidade;
    $('avOlharCabeca').value = av.olhar_cabeca == null ? 0.35 : av.olhar_cabeca;
    $('avRostoPublico').value = av.rosto_publico == null ? 0.5 : av.rosto_publico;
    $('avAlturaConteudo').value = av.altura_conteudo == null ? 0 : av.altura_conteudo;
    $('avOlharPadrao').value = av.olhar_padrao || 'camera';

    pintarFaixas();
    avisarVoz();
    desenharMapa();

    carregarPreview();
  }

  function novoInstrutor() {
    selecionar({
      id: 0, nome: '', descricao: '', arquivo: '', camera: 'upper', humor: 'neutral',
      voz_nome: 'pf_dora', voz_velocidade: 1, voz_tom: 1, voz_pausa_ms: 260,
      voz_ganho: 1, voz_qualidade: 'alta', voz_kbps: 96, ativo: 1, tem_modelo: false,
      mao_dominante: 'direita', orient_auto: 1, giro_corpo: 'leve', giro_quadril: 0,
      forca_tronco: 1, forca_cabeca: 1, forca_gesto: 1, suavidade: 1,
      olhar_cabeca: 0.35, rosto_publico: 0.5, altura_conteudo: 0, olhar_padrao: 'camera'
    }, null);
    $('triStatus').textContent = 'Dê um nome, salve e depois envie o modelo .vrm.';
    $('avNome').focus();
  }
  if ($('btnNovo')) { $('btnNovo').addEventListener('click', novoInstrutor); }

  function urlModelo() {
    if (!atual || !atual.id || !atual.tem_modelo) { return ''; }
    return URL_PAG + '?av=' + atual.id + '&modelo=1&v=' + Date.now();
  }

  function carregarPreview() {
    var url = urlModelo();

    if (preview) { preview.destruir(); preview = null; }
    $('triPalco').innerHTML = '';

    if (!url) {
      $('triStatus').textContent = atual && atual.id
        ? 'Envie um arquivo .vrm para ver a prévia.'
        : 'Salve o instrutor para poder enviar o modelo.';
      return;
    }

    $('triStatus').textContent = 'Carregando modelo…';
    preview = new window.Instrutor3D($('triPalco'), {
      url: url,
      nome: $('avNome').value,
      camera: $('avCamera').value,
      humor: $('avHumor').value,
      orientacao: cfgOrientacao()
    });

    preview.iniciar(function (frac) {
      $('triStatus').textContent = 'Carregando modelo… ' + Math.round(frac * 100) + '%';
    }).then(function (ok) {
      $('triStatus').textContent = ok ? '' : (preview.erro || 'Não foi possível carregar o modelo.');
      if (ok) {
        renderValidacao(preview.diagnosticar());
        montarGestos();
        aplicarOrientacao();
        preview.olhar($('avOlharTeste').value || $('avOlharPadrao').value || 'camera');
      } else {
        renderValidacao(null, preview.erro || 'Não foi possível carregar o arquivo.');
      }
    });
  }

  if ($('btnRecarregar')) { $('btnRecarregar').addEventListener('click', carregarPreview); }

  ['avCamera', 'avHumor'].forEach(function (id) {
    $(id).addEventListener('change', function () {
      if (!preview || !preview.ativo) { return; }
      if (id === 'avCamera') { preview.camera(this.value); } else { preview.humor(this.value); }
    });
  });

  if ($('btnMiniatura')) {
    $('btnMiniatura').addEventListener('click', function () {
      if (!preview || !preview.ativo) {
        window.trToast('Carregue o modelo antes de gerar a miniatura.', 'aviso');
        return;
      }
      var tela = $('triPalco').querySelector('canvas');
      if (!tela) { window.trToast('Prévia indisponível.', 'erro'); return; }

      var png;
      try { png = tela.toDataURL('image/png'); }
      catch (e) { window.trToast('Não foi possível capturar a prévia.', 'erro'); return; }

      window.trApi('av_thumb', { id: atual.id, png: png })
        .then(function (j) { window.trToast(j.msg); setTimeout(recarregarPagina, 700); })
        .catch(function (e) { window.trToast(e.message, 'erro'); });
    });
  }

  function recarregarPagina() { window.location.reload(); }

  function item(nivel, texto) {
    var icone = nivel === 'erro' ? 'x-octagon-fill'
              : (nivel === 'aviso' ? 'exclamation-triangle-fill' : 'check-circle-fill');
    return '<li class="tri-valida-item is-' + nivel + '">' +
             '<i class="bi bi-' + icone + '"></i><span>' + esc(texto) + '</span></li>';
  }

  function renderValidacao(d, erroCarga) {
    var caixa = $('avValidacao');
    if (erroCarga) {
      caixa.innerHTML = '<ul class="tri-valida-lista">' + item('erro', erroCarga) + '</ul>' +
        '<p class="tr-dica mb-0">No Blender, exporte com o <b>VRM Add-on for Blender</b>. ' +
        'Um arquivo .glb comum não carrega, mesmo renomeado para .vrm.</p>';
      return;
    }
    if (!d) { return; }

    var linhas = '';
    (d.erros || []).forEach(function (e) { linhas += item('erro', e.texto); });
    (d.avisos || []).forEach(function (a) { linhas += item('aviso', a.texto); });
    if (!(d.erros || []).length) {
      linhas += item('ok', 'Esqueleto humanoide e expressões de boca completos.');
    }
    if (d.lookAt && !(d.erros || []).length) {
      linhas += item('ok', 'Look At configurado: as direções de olhar funcionam.');
    }
    caixa.innerHTML = '<ul class="tri-valida-lista">' + linhas + '</ul>';
  }

  function montarGestos() {
    var alvo = $('avGestos');
    alvo.innerHTML =
      Object.keys(CAT.gestos).filter(function (k) { return k; }).map(function (k) {
        return '<button type="button" class="tri-gesto" data-gesto="' + esc(k) + '">' + esc(CAT.gestos[k]) + '</button>';
      }).join('') +
      Object.keys(CAT.humores).map(function (k) {
        return '<button type="button" class="tri-gesto is-humor" data-humor="' + esc(k) + '">' + esc(CAT.humores[k]) + '</button>';
      }).join('');
  }

  $('avGestos').addEventListener('click', function (ev) {
    if (!preview || !preview.ativo) { return; }
    var b = ev.target.closest('[data-gesto]');
    if (b) { testarGesto(b.getAttribute('data-gesto')); return; }
    b = ev.target.closest('[data-humor]');
    if (b) { preview.humor(b.getAttribute('data-humor')); }
  });

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-teste-gesto]');
    if (!b) { return; }
    testarGesto(b.getAttribute('data-teste-gesto'));
  });

  function testarGesto(nome) {
    if (!preview || !preview.ativo) { return; }
    if (!nome) { preview.gesto(null); return; }
    preview.gesto(nome, 8, { braco: $('avGestoBraco').value || 'auto' });
  }

  ['avGestoBraco', 'avOlharTeste'].forEach(function (id) {
    var el = $(id);
    if (!el) { return; }
    el.addEventListener('change', function () {
      if (!preview || !preview.ativo) { return; }
      if (id === 'avOlharTeste') { preview.olhar(this.value); }
      else if (preview._gestoAtual && preview._gestoAtual.nome) { testarGesto(preview._gestoAtual.nome); }
    });
  });

  function cfgOrientacao() {
    return {
      posicao: $('avPosicao').value,
      ladoConteudo: $('avLadoConteudo').value,
      maoDominante: $('avMaoDominante').value,
      autoOrientar: $('avOrientAuto').checked,
      giroCorpo: $('avGiroCorpo').value,
      giroQuadril: $('avGiroQuadril').checked,
      forcaTronco: Number($('avForcaTronco').value),
      forcaCabeca: Number($('avForcaCabeca').value),
      forcaGesto: Number($('avForcaGesto').value),
      olharCabeca: Number($('avOlharCabeca').value),
      rostoPublico: Number($('avRostoPublico').value),
      alturaConteudo: Number($('avAlturaConteudo').value),
      suavidade: Number($('avSuavidade').value)
    };
  }

  function aplicarOrientacao() {
    if (preview && preview.orientacao) { preview.orientacao(cfgOrientacao()); }
  }

  function desenharMapa() {
    var mapa = $('avMapa');
    var eco = $('avOrientEco');
    if (!mapa) { return; }

    var pos = $('avPosicao').value;
    var lado = $('avLadoConteudo').value;
    if (lado === 'auto') {
      if (pos === 'esquerda' || pos === 'centro-esquerda') { lado = 'direita'; }
      else if (pos === 'direita' || pos === 'centro-direita') { lado = 'esquerda'; }
      else { lado = 'centro'; }
    }

    var instrEsq = (pos === 'esquerda' || pos === 'centro-esquerda');
    var instrCentro = (pos === 'centro');
    var celulas;
    if (instrCentro) {
      celulas = [['is-conteudo', 'Conteúdo'], ['is-instrutor', 'Instrutor'], ['is-conteudo', 'Conteúdo']];
    } else if (instrEsq) {
      celulas = [['is-instrutor', 'Instrutor'], ['is-conteudo', 'Conteúdo']];
    } else {
      celulas = [['is-conteudo', 'Conteúdo'], ['is-instrutor', 'Instrutor']];
    }
    mapa.innerHTML = celulas.map(function (c) {
      return '<span class="' + c[0] + '">' + c[1] + '</span>';
    }).join('');

    if (eco) {
      var braco;
      if (lado === 'direita') { braco = 'o braço <b>esquerdo</b> dele'; }
      else if (lado === 'esquerda') { braco = 'o braço <b>direito</b> dele'; }
      else { braco = 'a mão dominante'; }
      var ondeTela = lado === 'centro' ? 'ao centro' : ('à <b>' + lado + '</b> da tela');
      eco.innerHTML = 'Com esta combinação o conteúdo fica ' + ondeTela +
        ', então ao apontar o instrutor usa ' + braco +
        ' e vira o corpo e o olhar para esse lado.';
    }
  }

  ['avPosicao', 'avLadoConteudo'].forEach(function (id) {
    $(id).addEventListener('change', function () {
      desenharMapa();
      if (preview && preview.posicionar) { preview.posicionar($('avPosicao').value); }
      aplicarOrientacao();
    });
  });

  ['avMaoDominante', 'avOrientAuto', 'avGiroCorpo', 'avGiroQuadril', 'avOlharPadrao'].forEach(function (id) {
    $(id).addEventListener('change', function () {
      desenharMapa();
      aplicarOrientacao();
      if (id === 'avOlharPadrao' && preview && preview.ativo && $('avOlharTeste').value === 'camera') {
        preview.olhar(this.value);
      }
    });
  });

  ['avForcaTronco', 'avForcaCabeca', 'avForcaGesto', 'avSuavidade',
   'avOlharCabeca', 'avRostoPublico', 'avAlturaConteudo'].forEach(function (id) {
    $(id).addEventListener('input', function () { pintarFaixas(); aplicarOrientacao(); });
  });

  document.addEventListener('click', function (ev) {
    var aba = ev.target.closest('.tri-aba');
    if (!aba) { return; }
    var nome = aba.getAttribute('data-aba');
    Array.prototype.forEach.call(document.querySelectorAll('.tri-aba'), function (a) {
      a.classList.toggle('is-ativa', a === aba);
    });
    Array.prototype.forEach.call(document.querySelectorAll('.tri-painel'), function (p) {
      p.classList.toggle('is-ativo', p.getAttribute('data-painel') === nome);
    });
  });

  function pintarFaixas() {
    $('avVelVal').textContent = num($('avVelocidade').value);
    $('avTomVal').textContent = num($('avTom').value);
    $('avPausaVal').textContent = parseInt($('avPausa').value, 10) + ' ms';
    $('avGanhoVal').textContent = num($('avGanho').value);

    [['avForcaTronco', 'avForcaTroncoV'], ['avForcaCabeca', 'avForcaCabecaV'],
     ['avForcaGesto', 'avForcaGestoV'], ['avSuavidade', 'avSuavidadeV'],
     ['avOlharCabeca', 'avOlharCabecaV'], ['avRostoPublico', 'avRostoPublicoV'],
     ['avAlturaConteudo', 'avAlturaConteudoV']].forEach(function (par) {
      var campo = $(par[0]), eco = $(par[1]);
      if (campo && eco) { eco.textContent = num(campo.value); }
    });
  }

  ['avVelocidade', 'avTom', 'avPausa', 'avGanho'].forEach(function (id) {
    $(id).addEventListener('input', pintarFaixas);
  });

  function avisarVoz() {
    var aviso = $('avVozAviso');
    if (!aviso) { return; }
    aviso.hidden = false;
    aviso.textContent = 'As vozes vêm do modelo Supertonic e não são específicas de português: ' +
      'o idioma é definido na geração. Ouça o teste antes de vincular ao instrutor.';
  }
  $('avVoz').addEventListener('change', avisarVoz);

  function cfgVoz() {
    return {
      voz: $('avVoz').value,
      velocidade: Number($('avVelocidade').value),
      tom: Number($('avTom').value),
      pausaMs: Number($('avPausa').value),
      ganho: Number($('avGanho').value),
      qualidade: $('avQualidade').value,
      kbps: Number($('avKbps').value),
      onProgressoModelo: function (frac) {
        $('avVozProgresso').style.width = Math.round(frac * 100) + '%';
        $('avVozStatus').textContent = 'Baixando o modelo de voz… ' + Math.round(frac * 100) + '%';
      }
    };
  }

  $('btnPrepararVoz').addEventListener('click', function () {
    var b = this;
    b.disabled = true;
    window.TrVoz.preparar(cfgVoz().onProgressoModelo, $('avQualidade').value)
      .then(function () {
        b.disabled = false;
        $('avVozStatus').textContent = 'Modelo pronto neste navegador.';
        $('avVozProgresso').style.width = '100%';
      })
      .catch(function (e) {
        b.disabled = false;
        $('avVozStatus').textContent = e.message;
        window.trToast(e.message, 'erro');
      });
  });

  $('btnTestarVoz').addEventListener('click', function () {
    var b = this;
    b.disabled = true;
    window.TrVoz.gerar($('avVozTeste').value, cfgVoz())
      .then(function (r) {
        var som = $('avVozAudio');
        som.src = URL.createObjectURL(r.blob);
        som.hidden = false;
        som.play();
        if (preview && preview.ativo && preview.iniciarLipsync) {
          try { preview.iniciarLipsync(som, r.visemas || null); } catch (e) {}
          som.addEventListener('ended', function () {
            try { preview.pararLipsync(); } catch (e) {}
          }, { once: true });
        }
        b.disabled = false;
      })
      .catch(function (e) { b.disabled = false; window.trToast(e.message, 'erro'); });
  });

  var AJ = ['ajFora', 'ajCima', 'ajFrente', 'ajCotovelo', 'ajGiroBraco', 'ajFlexao', 'ajDesvio', 'ajGiro'];

  function ajBase() {
    var defs = (window.Instrutor3D && window.Instrutor3D.DEF_GESTOS) || {};
    return defs[$('ajGesto').value] || {};
  }

  function ajCarregar() {
    var g = ajBase();
    var rep = (window.Instrutor3D || {}).REPOUSO_BRACO ||
      { braco: [0.16, -0.97, 0.14], cotovelo: 0.22, giroBraco: 0.1, pulso: {}, mao: 'relaxada' };
    var b = g.ambos ||
            ($('ajLado').value === 'direito' ? (g.apoio || g.ativo) : (g.ativo || g.apoio)) ||
            g[$('ajLado').value] || {};
    var braco = b.braco || rep.braco;
    var pulso = Object.assign({ flexao: 0, desvio: 0, giro: 0 }, rep.pulso, b.pulso || {});

    $('ajFora').value = braco[0];
    $('ajCima').value = braco[1];
    $('ajFrente').value = braco[2];
    $('ajCotovelo').value = b.cotovelo != null ? b.cotovelo : rep.cotovelo;
    $('ajGiroBraco').value = b.giroBraco != null ? b.giroBraco : rep.giroBraco;
    $('ajFlexao').value = pulso.flexao;
    $('ajDesvio').value = pulso.desvio;
    $('ajGiro').value = pulso.giro;
    if ($('ajMao').options.length) { $('ajMao').value = b.mao || rep.mao || 'relaxada'; }
    ajPintar();
  }

  function ajPintar() {
    AJ.forEach(function (id) {
      var alvo = $(id + 'V');
      if (alvo) { alvo.textContent = num($(id).value); }
    });
  }

  function ajSpec() {
    var lado = $('ajLado').value;
    var spec = {};
    spec[lado] = {
      braco: [Number($('ajFora').value), Number($('ajCima').value), Number($('ajFrente').value)],
      cotovelo: Number($('ajCotovelo').value),
      giroBraco: Number($('ajGiroBraco').value),
      pulso: {
        flexao: Number($('ajFlexao').value),
        desvio: Number($('ajDesvio').value),
        giro: Number($('ajGiro').value)
      },
      mao: $('ajMao').value || 'relaxada'
    };
    return spec;
  }

  function ajAplicar() {
    ajPintar();
    if (preview && preview.ativo && preview.gestoManual) { preview.gestoManual(ajSpec()); }
  }

  if (window.Instrutor3D) {
    $('ajGesto').innerHTML = (window.Instrutor3D.GESTOS || []).map(function (k) {
      return '<option value="' + esc(k) + '">' + esc(CAT.gestos[k] || k) + '</option>';
    }).join('');

    $('ajMao').innerHTML = Object.keys(window.Instrutor3D.MAOS || {}).map(function (k) {
      return '<option value="' + esc(k) + '">' + esc(k) + '</option>';
    }).join('');

    AJ.forEach(function (id) { $(id).addEventListener('input', ajAplicar); });
    $('ajMao').addEventListener('change', ajAplicar);
    $('ajLado').addEventListener('change', function () { ajCarregar(); ajAplicar(); });
    $('ajGesto').addEventListener('change', function () { ajCarregar(); ajAplicar(); });
    ajCarregar();
  }

  $('ajReset').addEventListener('click', function () {
    ajCarregar();
    if (preview && preview.ativo && preview.gestoManual) { preview.gestoManual(ajSpec()); }
  });

  $('ajCopiar').addEventListener('click', function () {
    var lado = $('ajLado').value;
    var s = ajSpec()[lado];
    var texto = $('ajGesto').value + ' — ' + lado + ':\n' +
      JSON.stringify(s, null, 2);
    $('ajSaida').hidden = false;
    $('ajSaida').textContent = texto;
    if (navigator.clipboard) {
      navigator.clipboard.writeText(texto)
        .then(function () { window.trToast('Valores copiados.'); })
        .catch(function () {});
    }
  });

  if ($('btnSalvar')) {
    $('btnSalvar').addEventListener('click', function () {
      if (!atual) { return; }
      var b = this;
      var nome = ($('avNome').value || '').trim();
      if (!nome) {
        window.trToast('Dê um nome ao instrutor.', 'aviso');
        $('avNome').focus();
        return;
      }

      b.disabled = true;
      window.trApi('av_salvar', {
        id: atual.id || 0,
        nome: nome,
        descricao: ($('avDescricao').value || '').trim(),
        camera: $('avCamera').value,
        humor: $('avHumor').value,
        ativo: $('avAtivo').checked ? 1 : 0,
        voz_nome: $('avVoz').value,
        voz_velocidade: Number($('avVelocidade').value),
        voz_tom: Number($('avTom').value),
        voz_pausa_ms: Number($('avPausa').value),
        voz_ganho: Number($('avGanho').value),
        voz_qualidade: $('avQualidade').value,
        voz_kbps: Number($('avKbps').value),

        mao_dominante: $('avMaoDominante').value,
        orient_auto: $('avOrientAuto').checked ? 1 : 0,
        giro_corpo: $('avGiroCorpo').value,
        giro_quadril: $('avGiroQuadril').checked ? 1 : 0,
        forca_tronco: Number($('avForcaTronco').value),
        forca_cabeca: Number($('avForcaCabeca').value),
        forca_gesto: Number($('avForcaGesto').value),
        olhar_cabeca: Number($('avOlharCabeca').value),
        rosto_publico: Number($('avRostoPublico').value),
        altura_conteudo: Number($('avAlturaConteudo').value),
        suavidade: Number($('avSuavidade').value),
        olhar_padrao: $('avOlharPadrao').value
      }).then(function (j) {
        window.trToast(j.msg);
        atual.id = j.id;
        setTimeout(recarregarPagina, 700);
      }).catch(function (e) {
        b.disabled = false;
        window.trToast(e.message, 'erro');
      });
    });
  }

  if ($('avArquivo')) {
    $('avArquivo').addEventListener('change', function () {
      var f = this.files && this.files[0];
      if (!f) { return; }
      if (!atual || !atual.id) {
        window.trToast('Salve o instrutor antes de enviar o modelo.', 'aviso');
        this.value = '';
        return;
      }
      $('avArquivoNome').textContent = 'Enviando ' + f.name + '…';
      window.trApi('av_upload', {}, { id: String(atual.id), arquivo: f })
        .then(function (j) {
          atual.arquivo = j.arquivo;
          atual.tem_modelo = true;
          $('avArquivoNome').textContent = 'Atual: ' + j.arquivo;
          window.trToast(j.msg);
          carregarPreview();
        })
        .catch(function (e) {
          $('avArquivoNome').textContent = '';
          window.trToast(e.message, 'erro');
        })
        .then(function () { $('avArquivo').value = ''; });
    });
  }

  if ($('btnExcluir')) {
    $('btnExcluir').addEventListener('click', function () {
      if (!atual || !atual.id) { return; }
      window.trConfirmar({
        titulo: 'Excluir instrutor',
        texto: 'O modelo e a miniatura serão apagados do servidor.',
        confirmar: 'Excluir',
        perigo: true
      }).then(function (sim) {
        if (!sim) { return; }
        window.trApi('av_excluir', { id: atual.id })
          .then(function (j) { window.trToast(j.msg); setTimeout(recarregarPagina, 700); })
          .catch(function (e) { window.trToast(e.message, 'erro'); });
      });
    });
  }

  var primeiro = document.querySelector('.tri-item');
  if (primeiro) { primeiro.click(); }
})();
