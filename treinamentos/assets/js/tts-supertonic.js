(function (global) {
  'use strict';

  var CDN_TRANSFORMERS = ['/assets/vendor/transformers@4.3.0/transformers.min.js'];
  var TRANSFORMERS_WASM = '/assets/vendor/transformers@4.3.0/ort/';
  var CDN_MP3 = ['/assets/vendor/lamejs@1.2.7/dist/lamejs.js'];
  var ESPEAK_BASE = '/assets/vendor/espeak-ng@1.0.2/dist/';
  var CDN_ESPEAK  = [ESPEAK_BASE + 'espeak-ng.js'];

  var MODELO   = 'onnx-community/Supertonic-TTS-2-ONNX';
  var BASE_VOZ = 'https://huggingface.co/' + MODELO + '/resolve/main/voices/';
  var IDIOMA   = 'pt';

  var MAX_BLOCO = 165;
  var MAX_DURO = 230;
  var MIN_JUNTAR = 80;

  var MS_POR_CHAR_MIN = 46;
  var MS_POR_CHAR_MAX = 145;

  var motor = null;
  var motorQualidade = '';
  var carregando = null;
  var lame = null;

  var espeakFabrica = null;
  var espeakBinario = null;
  var espeakModulo  = null;
  var espeakPreparo = null;

  function importarPrimeiro(urls) {
    var i = 0;
    function tentar() {
      if (i >= urls.length) {
        return Promise.reject(new Error('Não foi possível carregar a biblioteca de voz. Verifique o acesso à internet.'));
      }
      return import( urls[i++]).catch(tentar);
    }
    return tentar();
  }

  function limitar(v, min, max) {
    v = Number(v);
    if (!isFinite(v)) { return min; }
    return Math.max(min, Math.min(max, v));
  }

  function prepararEspeak(onProgresso) {
    if (espeakModulo && espeakFabrica) { return Promise.resolve(true); }
    if (espeakPreparo) { return espeakPreparo; }

    espeakPreparo = importarPrimeiro(CDN_ESPEAK).then(function (mod) {
      espeakFabrica = mod.default || mod;
      if (typeof espeakFabrica !== 'function') { throw new Error('Biblioteca de fonemas inválida.'); }
      return fetch(ESPEAK_BASE + 'espeak-ng.wasm');
    }).then(function (r) {
      if (!r.ok) { throw new Error('Não foi possível baixar o dicionário de pronúncia.'); }
      if (!onProgresso || !r.body || !r.headers.get('content-length')) { return r.arrayBuffer(); }

      var total = parseInt(r.headers.get('content-length'), 10) || 0;
      var lido = 0, pedacos = [], leitor = r.body.getReader();
      function passo() {
        return leitor.read().then(function (res) {
          if (res.done) {
            var buf = new Uint8Array(lido), pos = 0;
            pedacos.forEach(function (p) { buf.set(p, pos); pos += p.length; });
            return buf.buffer;
          }
          pedacos.push(res.value);
          lido += res.value.length;
          onProgresso(total ? Math.min(1, lido / total) : 0, 'pronúncia pt-BR');
          return passo();
        });
      }
      return passo();
    }).then(function (buf) {
      espeakBinario = buf;
      return WebAssembly.compile(buf);
    }).then(function (m) {
      espeakModulo = m;
      espeakPreparo = null;
      return true;
    }).catch(function (e) {
      espeakPreparo = null;
      espeakFabrica = null;
      throw e;
    });

    return espeakPreparo;
  }

  function rodarEspeak(texto) {
    var bytes = new TextEncoder().encode(texto);
    return espeakFabrica({
      arguments: ['--phonout', 'fonemas.txt', '-q', '--ipa=3', '-v', 'pt-br', '-f', 'entrada.txt'],
      locateFile: function (arquivo) { return ESPEAK_BASE + arquivo; },
      wasmBinary: espeakBinario,
      instantiateWasm: function (imports, pronto) {
        WebAssembly.instantiate(espeakModulo, imports).then(function (inst) { pronto(inst, espeakModulo); });
        return {};
      },
      print: function () {},
      printErr: function () {},
      preRun: [function (M) { M.FS.writeFile('entrada.txt', bytes); }]
    }).then(function (M) {
      try { return M.FS.readFile('fonemas.txt', { encoding: 'utf8' }); }
      catch (e) { return ''; }
    });
  }

  var TIE = '\u200D';
  var LIGADURAS = [
    ['a' + TIE + '\u026A', 'I'],
    ['a' + TIE + '\u028A', 'W'],
    ['d' + TIE + 'z',      '\u02A3'],
    ['d' + TIE + '\u0292', '\u02A4'],
    ['e' + TIE + '\u026A', 'A'],
    ['o' + TIE + '\u028A', 'O'],
    ['\u0259' + TIE + '\u028A', 'Q'],
    ['s' + TIE + 's',      'S'],
    ['t' + TIE + 's',      '\u02A6'],
    ['t' + TIE + '\u0283', '\u02A7'],
    ['\u0254' + TIE + '\u026A', 'Y']
  ];

  function aplicarLigaduras(ps) {
    for (var i = 0; i < LIGADURAS.length; i++) {
      ps = ps.split(LIGADURAS[i][0]).join(LIGADURAS[i][1]);
    }
    return ps.split(TIE).join('').split('\u0361').join('').replace(/-/g, '');
  }

  function fonemizarBloco(bloco) {
    return rodarEspeak(bloco).then(function (saida) {
      var ps = String(saida || '').split('\n').join(' ');
      return aplicarLigaduras(ps).replace(/\s+/g, ' ').trim();
    }).catch(function () { return ''; });
  }

  var TROCAS = [
    [/\bn\u00BA\.?/gi, 'n\u00FAmero '],
    [/\bno\.\s*(?=\d)/gi, 'n\u00FAmero '],
    [/\u00A7\u00A7/g, 'par\u00E1grafos '],
    [/\u00A7/g, 'par\u00E1grafo '],
    [/\barts?\./gi, 'artigo '],
    [/\bincs?\./gi, 'inciso '],
    [/\bal\u00EDn\./gi, 'al\u00EDnea '],
    [/\bpar\.\s/gi, 'par\u00E1grafo '],
    [/%/g, ' por cento'],
    [/\bR\$\s*([\d.,]+)/g, '$1 reais'],
    [/\u00BA/g, ''],
    [/\u00AA/g, ''],
    [/&/g, ' e '],
    [/\+/g, ' mais '],
    [/\bNR\s*-\s*(\d+)/g, 'N\u00E9rre $1'],
    [/\bCA\s*-\s*(\d+)/g, 'C\u00E1 $1'],
    [/\s+[\u2013\u2014]\s+/g, ', '],
    [/\u2026/g, '.'],
    [/([A-Z\u00C0-\u00DC]{2,})-(\d)/g, '$1 $2']
  ];

  var PRONUNCIAS = [
    [/\btransfer(e|es)\b/gi, 'transf\u00E9ri'],
    [/\btransferem\b/gi, 'transf\u00E9rem'],
    [/\bhigieniz(e|es)\b/gi, 'higien\u00EDzi'],
    [/\bhigienizem\b/gi, 'higien\u00EDzem'],
    [/\bseque\b/gi, 'séqui'],
    [/\bsequem\b/gi, 's\u00E9quem'],
    [/\blocal\b/gi, 'locáu'],
    [/\bEPI\b/gi, 'êpeí'],
    [/\bNR-6\b/gi, 'êneérre seis'],
    [/\bca\b/gi, 'cêá'],
    [/\bhigienização\b/gi, 'igienização'],
    [/\bindividual\b/gi, 'individuau'],
    [/\bfacial\b/gi, 'faciáu'],
    [/\btransfere\b/gi, 'trânsféri'],
    [/\bexige\b/gi, 'ezige']
  ];

  function aplicarPronuncias(t) {
    var out = String(t || '');
    PRONUNCIAS.forEach(function (par, i) {
      if (!par || !par[0] || !par[0].source || typeof par[1] !== 'string') {
        console.warn('[TrVoz] linha ' + (i + 1) + ' de PRONUNCIAS está fora do formato ' +
                     '[/expressão/gi, \'texto\'] e foi ignorada.');
        return;
      }
      var re = par[0];
      var solto = new RegExp(re.source, re.flags.replace(/g/g, ''));
      out = out.replace(re, function (m) {
        var novo = m.replace(solto, par[1]);
        if (/^[A-Z\u00C0-\u00DC]/.test(m)) {
          novo = novo.charAt(0).toUpperCase() + novo.slice(1);
        }
        return novo;
      });
    });
    return out;
  }

  function normalizarTexto(t) {
    var out = aplicarPronuncias(String(t || ''));
    TROCAS.forEach(function (p) { out = out.replace(p[0], p[1]); });
    return out.replace(/\s{2,}/g, ' ').trim();
  }

  function dividirBlocos(texto) {
    var bruto = normalizarTexto(texto)
      .replace(/[\u2018\u2019]/g, "'")
      .replace(/[\u201C\u201D]/g, '"')
      .replace(/[<>]/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
    if (!bruto) { return []; }

    var frases = bruto.match(/[^.!?\u2026]+[.!?\u2026]*\s*/g) || [bruto];
    var blocos = [];
    var atual = '';

    function fechar(corte) {
      var t = atual.trim();
      if (t !== '') { blocos.push({ t: t, corte: corte || 'frase' }); }
      atual = '';
    }

    frases.forEach(function (frase) {
      var f = frase.trim();
      if (f === '') { return; }

      if (f.length > MAX_DURO) {
        fechar();
        var pedacos = partirEmVirgulas(f, MAX_BLOCO);
        pedacos.forEach(function (p, k) {
          blocos.push({ t: p, corte: k < pedacos.length - 1 ? 'meio' : 'frase' });
        });
        return;
      }

      if (atual === '') { atual = f; return; }
      if ((atual + ' ' + f).length <= MAX_BLOCO ||
          (f.length <= MIN_JUNTAR && (atual + ' ' + f).length <= MAX_DURO)) {
        atual = atual + ' ' + f;
        return;
      }
      fechar();
      atual = f;
    });
    fechar();

    return blocos;
  }

  function partirEmVirgulas(t, teto) {
    var lim = teto || MAX_BLOCO;
    var pedacos = String(t).split(/(?<=[,;:])/);
    var saida = [], buffer = '';
    pedacos.forEach(function (p) {
      if (buffer !== '' && (buffer + p).length > lim) { saida.push(buffer.trim()); buffer = ''; }
      buffer += p;
    });
    if (buffer.trim() !== '') { saida.push(buffer.trim()); }
    if (saida.length === 1 && saida[0].length > lim) {
      var meio = saida[0].lastIndexOf(' ', Math.floor(saida[0].length / 2) + 20);
      if (meio > 20) { saida = [saida[0].slice(0, meio).trim(), saida[0].slice(meio).trim()]; }
    }
    return saida;
  }

  function aparar(amostras, taxa, folgaMs) {
    var limiar = 0.0012;
    var ini = 0, fim = amostras.length - 1;
    while (ini < fim && Math.abs(amostras[ini]) < limiar) { ini++; }
    while (fim > ini && Math.abs(amostras[fim]) < limiar) { fim--; }
    var folga = Math.round(taxa * ((folgaMs == null ? 60 : folgaMs) / 1000));
    ini = Math.max(0, ini - folga);
    fim = Math.min(amostras.length - 1, fim + folga);
    return amostras.subarray(ini, fim + 1);
  }

  function reamostrar(amostras, fator) {
    if (!fator || Math.abs(fator - 1) < 0.005) { return amostras; }
    var destino = Math.max(1, Math.round(amostras.length / fator));
    var saida = new Float32Array(destino);
    for (var i = 0; i < destino; i++) {
      var pos = i * fator;
      var a = Math.floor(pos);
      var b = Math.min(amostras.length - 1, a + 1);
      var f = pos - a;
      saida[i] = amostras[a] * (1 - f) + amostras[b] * f;
    }
    return saida;
  }

  function normalizar(amostras, ganho) {
    var soma = 0;
    for (var i = 0; i < amostras.length; i++) { soma += amostras[i] * amostras[i]; }
    var rms = Math.sqrt(soma / Math.max(1, amostras.length));
    if (rms <= 0.0001) { return amostras; }

    var alvo = 0.11 * (ganho || 1);
    var k = Math.min(6, alvo / rms);
    var teto = 0.94;

    for (var j = 0; j < amostras.length; j++) {
      var v = amostras[j] * k;
      if (v > teto || v < -teto) {
        var sinal = v < 0 ? -1 : 1;
        var excesso = Math.abs(v) - teto;
        v = sinal * (teto + (1 - teto) * Math.tanh(excesso / (1 - teto)));
      }
      amostras[j] = v;
    }
    return amostras;
  }

  function juntar(pecas, taxa, pausaMs) {
    var pFrase = Math.max(0, Math.round((pausaMs || 0) / 1000 * taxa));
    var pMeio = Math.round(taxa * 0.012);

    function vao(i) {
      if (i >= pecas.length - 1) { return 0; }
      return pecas[i].corte === 'meio' ? pMeio : pFrase;
    }

    var total = 0;
    pecas.forEach(function (p, i) { total += p.amostras.length + vao(i); });

    var saida = new Float32Array(total);
    var pos = 0;
    pecas.forEach(function (p, i) {
      saida.set(p.amostras, pos);
      pos += p.amostras.length + vao(i);
    });
    return saida;
  }

  var MAPA_FONEMA = {
    'a': 0, '\u0250': 0, '\u0251': 0, '\u0252': 0, '\u00E6': 0, '\u028C': 0, 'I': 0, 'W': 0,
    'e': 3, '\u025B': 3, '\u0259': 3, '\u025A': 3, 'A': 3,
    'i': 1, '\u026A': 1, '\u0268': 1, 'y': 1, 'j': 1,
    'o': 4, '\u0254': 4, 'O': 4, 'Q': 4, 'Y': 4,
    'u': 2, '\u028A': 2, 'w': 2, '\u026F': 2
  };
  var FECHADAS = 'mbpfv';

  function sequenciaVisemas(fonemas) {
    var seq = [];
    var letras = String(fonemas || '').split('');
    var ultimaVogal = -2;
    for (var i = 0; i < letras.length; i++) {
      var c = letras[i];
      if (c === '\u0303' || c === '\u02C8' || c === '\u02CC' || c === '\u02D0') { continue; }
      if (MAPA_FONEMA[c] !== undefined) {
        if (ultimaVogal === i - 1 && seq.length) { seq[seq.length - 1] = MAPA_FONEMA[c]; }
        else { seq.push(MAPA_FONEMA[c]); }
        ultimaVogal = i;
      } else if (FECHADAS.indexOf(c) >= 0) {
        seq.push(-1);
      }
    }
    if (!seq.length) { seq.push(0); }
    return seq;
  }

  function montarLipsync(amostras, taxa, fonemas) {
    var passo = Math.round(taxa * 0.04);
    var quadros = Math.floor(amostras.length / passo);
    var energia = new Float32Array(quadros);
    var pico = 0;

    for (var q = 0; q < quadros; q++) {
      var soma = 0, ini = q * passo;
      for (var k = 0; k < passo; k++) { var v = amostras[ini + k]; soma += v * v; }
      energia[q] = Math.sqrt(soma / passo);
      if (energia[q] > pico) { pico = energia[q]; }
    }
    if (pico <= 0) { pico = 1; }

    var seq = sequenciaVisemas(fonemas);
    var limiar = pico * 0.08;

    var total = 0;
    for (var a = 0; a < quadros; a++) { if (energia[a] > limiar) { total += energia[a]; } }
    if (total <= 0) { total = 1; }

    var t = [], vi = [], w = [], acumulado = 0, ultimo = -2;

    for (var f = 0; f < quadros; f++) {
      var e = energia[f];
      var indice;
      if (e > limiar) {
        acumulado += e;
        indice = Math.min(seq.length - 1, Math.floor((acumulado / total) * seq.length));
      } else {
        indice = -2;
      }

      var visema = 0, peso = 0;
      if (indice >= 0) {
        var s = seq[indice];
        if (s < 0) { visema = 0; peso = 0.08; }
        else { visema = s; peso = Math.min(1, 0.35 + (e / pico) * 0.75); }
      }

      if (indice !== ultimo || Math.abs(peso - (w[w.length - 1] || 0)) > 0.18) {
        t.push(Math.round((f * passo / taxa) * 1000));
        vi.push(visema);
        w.push(Number(peso.toFixed(2)));
        ultimo = indice;
      }
    }

    t.push(Math.round((amostras.length / taxa) * 1000));
    vi.push(0);
    w.push(0);
    return { t: t, v: vi, w: w };
  }

  function paraMp3(amostras, taxa, kbps) {
    var carregar = lame ? Promise.resolve(lame) : importarPrimeiro(CDN_MP3).then(function (m) {
      lame = (m.default && m.default.Mp3Encoder) ? m.default : m;
      return lame;
    });

    return carregar.then(function (mod) {
      var Encoder = mod.Mp3Encoder;
      if (!Encoder) { throw new Error('Codificador MP3 indisponível.'); }
      var enc = new Encoder(1, taxa, kbps || 96);
      var pcm = new Int16Array(amostras.length);
      for (var i = 0; i < amostras.length; i++) {
        var s = Math.max(-1, Math.min(1, amostras[i]));
        pcm[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
      }
      var pedacos = [];
      for (var j = 0; j < pcm.length; j += 1152) {
        var bloco = enc.encodeBuffer(pcm.subarray(j, Math.min(j + 1152, pcm.length)));
        if (bloco.length) { pedacos.push(new Uint8Array(bloco)); }
      }
      var fim = enc.flush();
      if (fim.length) { pedacos.push(new Uint8Array(fim)); }
      return new Blob(pedacos, { type: 'audio/mpeg' });
    });
  }

  var QUALIDADES = {
    rapida: { rotulo: 'Rápida (4 passos)',        passos: 4,  passadas: 1 },
    alta:   { rotulo: 'Alta (10 passos)',         passos: 10, passadas: 2 },
    maxima: { rotulo: 'Máxima (20 passos)',       passos: 20, passadas: 3 }
  };

  function progressoAgregado(onProgresso) {
    var porArquivo = {};
    return function (p) {
      if (!onProgresso || !p) { return; }
      var nome = p.file || p.name || 'modelo';
      var frac = 0;
      if (typeof p.progress === 'number') { frac = p.progress / 100; }
      else if (p.total) { frac = (p.loaded || 0) / p.total; }
      if (p.status === 'done' || p.status === 'ready') { frac = 1; }
      porArquivo[nome] = Math.min(1, Math.max(0, frac));

      var soma = 0, n = 0;
      Object.keys(porArquivo).forEach(function (k) { soma += porArquivo[k]; n++; });
      onProgresso(n ? soma / n : 0, nome);
    };
  }

  function abrirPipeline(mod, dispositivo, onProgresso) {
    var pipeline = mod.pipeline || (mod.default && mod.default.pipeline);
    if (!pipeline) { throw new Error('Biblioteca de voz inválida (pipeline ausente).'); }
    var envT = mod.env || (mod.default && mod.default.env);
    if (envT && envT.backends && envT.backends.onnx && envT.backends.onnx.wasm) {
      envT.backends.onnx.wasm.wasmPaths = TRANSFORMERS_WASM;
    }
    return pipeline('text-to-speech', MODELO, {
      dtype: 'fp32',
      device: dispositivo,
      progress_callback: progressoAgregado(onProgresso)
    });
  }

  var TrVoz = {

    vozes: {
      F1: 'F1 — feminina 1',
      F2: 'F2 — feminina 2',
      F3: 'F3 — feminina 3',
      F4: 'F4 — feminina 4',
      F5: 'F5 — feminina 5',
      M1: 'M1 — masculina 1',
      M2: 'M2 — masculina 2',
      M3: 'M3 — masculina 3',
      M4: 'M4 — masculina 4',
      M5: 'M5 — masculina 5'
    },

    qualidades: QUALIDADES,

    disponivel: function () { return typeof WebAssembly === 'object'; },

    pronto: function () { return !!motor && !!espeakModulo; },

    faltantes: function () { return {}; },

    preparar: function (onProgresso, qualidade) {
      var q = QUALIDADES[qualidade] ? qualidade : 'alta';
      if (motor && espeakModulo) { motorQualidade = q; return Promise.resolve(motor); }
      if (carregando) { motorQualidade = q; return carregando; }
      if (!this.disponivel()) {
        return Promise.reject(new Error('Este navegador não suporta a geração local de voz.'));
      }
      motorQualidade = q;

      var temWebGpu = !!(global.navigator && global.navigator.gpu);

      carregando = prepararEspeak(onProgresso).then(function () {
        if (motor) { return motor; }
        return importarPrimeiro(CDN_TRANSFORMERS).then(function (mod) {
          if (!temWebGpu) { return abrirPipeline(mod, 'wasm', onProgresso); }
          return abrirPipeline(mod, 'webgpu', onProgresso).catch(function (e) {
            console.warn('[TrVoz] WebGPU indisponível para este modelo, usando WASM:', e);
            return abrirPipeline(mod, 'wasm', onProgresso);
          });
        });
      }).then(function (m) {
        motor = m;
        carregando = null;
        return motor;
      }).catch(function (e) {
        carregando = null;
        throw e;
      });

      return carregando;
    },

    diagnostico: function (texto, cfg) {
      var c = cfg || {};
      return this.preparar(c.onProgressoModelo, c.qualidade).then(function () {
        var blocos = dividirBlocos(texto);
        return blocos.reduce(function (fila, bloco) {
          return fila.then(function (acc) {
            return fonemizarBloco(bloco).then(function (ps) {
              acc.push({ frase: bloco, fonemas: ps });
              return acc;
            });
          });
        }, Promise.resolve([])).then(function (lista) {
          return {
            frases: lista,
            fonemas: lista.map(function (f) { return f.fonemas; }).join(' '),
            desconhecidos: {},
            comVocabulario: false
          };
        });
      });
    },

    fonemas: function (texto) {
      return this.diagnostico(texto).then(function (d) { return d.fonemas; });
    },

    gerar: function (texto, cfg) {
      var c = cfg || {};
      var blocos = dividirBlocos(texto);
      if (!blocos.length) { return Promise.reject(new Error('Texto vazio.')); }

      var voz = String(c.voz || 'F1');
      if (!TrVoz.vozes[voz]) { voz = 'F1'; }

      var tom = limitar(c.tom || 1, 0.85, 1.2);
      var velocidade = limitar(c.velocidade || 1, 0.7, 1.4);
      var pausaMs = c.pausaMs == null ? 260 : limitar(c.pausaMs, 0, 1200);
      var ganho = limitar(c.ganho || 1, 0.5, 1.8);

      var velocidadeModelo = limitar(velocidade / tom, 0.85, 1.15);
      var ajusteFinal = velocidade / (velocidadeModelo * tom);

      var q = QUALIDADES[c.qualidade] || QUALIDADES.alta;
      var passos = q.passos;
      var passadas = q.passadas || 2;
      var embeddings = BASE_VOZ + voz + '.bin';

      var pecas = [];
      var fonemasTotais = [];
      var taxa = 44100;
      var avisos = [];
      var notas = [];

      function pronunciaveis(t) {
        return t.replace(/[^0-9A-Za-zÀ-ÿ]/g, '').length;
      }

      return this.preparar(c.onProgressoModelo, c.qualidade).then(function (m) {

        function passada(texto, refino) {
          return m('<' + IDIOMA + '>' + texto + '</' + IDIOMA + '>', {
            speaker_embeddings: embeddings,
            num_inference_steps: refino,
            speed: velocidadeModelo
          }).then(function (saida) {
            var r = Array.isArray(saida) ? saida[0] : saida;
            var a = r && (r.audio || r.data);
            if (!a || !a.length) { return null; }
            taxa = r.sampling_rate || r.samplingRate || taxa;
            return a;
          }).catch(function () { return null; });
        }

        function duracaoMs(a) { return (a.length / taxa) * 1000; }

        function escolherPassada(texto, tentativas) {
          var chars = pronunciaveis(texto);
          var piso = chars * MS_POR_CHAR_MIN / velocidadeModelo;
          var teto = chars * MS_POR_CHAR_MAX / velocidadeModelo;
          var cands = [];

          function tentar(n) {
            var refino = (n > 0 && n === tentativas - 1)
              ? Math.min(48, Math.round(passos * 1.5))
              : passos;
            return passada(texto, refino).then(function (a) {
              if (a) { cands.push(a); }
              if (n + 1 >= tentativas) { return null; }
              if (cands.length === 1 && tentativas === 2) {
                var d = duracaoMs(cands[0]);
                if (d >= piso && d <= teto * 0.8) { return null; }
              }
              return tentar(n + 1);
            });
          }

          return tentar(0).then(function () {
            if (!cands.length) { return null; }
            var ord = cands.slice().sort(function (a, b) { return a.length - b.length; });
            var dentro = ord.filter(function (a) {
              var d = duracaoMs(a);
              return d >= piso && d <= teto;
            });
            if (dentro.length) { return dentro[Math.floor((dentro.length - 1) / 2)]; }
            if (duracaoMs(ord[0]) > teto) { return ord[0]; }
            return ord[ord.length - 1];
          });
        }

        function sintetizar(texto, corte, profundidade) {
          var tentativas = profundidade === 0 ? passadas : Math.min(passadas, 2);
          return escolherPassada(texto, Math.max(1, tentativas)).then(function (a) {
            var chars = pronunciaveis(texto);
            var piso = chars * MS_POR_CHAR_MIN / velocidadeModelo;
            var ms = a ? (a.length / taxa) * 1000 : 0;
            var curto = !a || ms < piso;

            if (curto && profundidade === 0 && texto.length > 90) {
              var pedacos = partirEmVirgulas(texto, Math.max(60, Math.ceil(texto.length / 2)));
              if (pedacos.length > 1) {
                return pedacos.reduce(function (fila, p, k) {
                  return fila.then(function () {
                    return sintetizar(p, k < pedacos.length - 1 ? 'meio' : corte, 1);
                  });
                }, Promise.resolve());
              }
            }

            if (!a) {
              avisos.push('Sem áudio: “' + texto.slice(0, 60) + '…”');
              return null;
            }
            if (curto) {
              avisos.push('Possível trecho acelerado: “' + texto.slice(0, 60) + '…”');
            } else if (chars >= 40 && ms > chars * MS_POR_CHAR_MAX / velocidadeModelo) {
              avisos.push('Trecho muito longo para o texto — confira se há palavra repetida: “' +
                          texto.slice(0, 60) + '…”');
            }

            var folga = corte === 'meio' ? 8 : 55;
            var limpo = aparar(a, taxa, folga);

            var msUtil = Math.max(0, (limpo.length / taxa) * 1000 - folga * 2);

            pecas.push({
              corte: corte,
              amostras: reamostrar(limpo, tom),
              chars: chars,
              msChar: chars > 0 ? msUtil / chars : 0
            });
            return fonemizarBloco(texto).then(function (ps) {
              if (ps) { fonemasTotais.push(ps); }
            });
          });
        }

        return blocos.reduce(function (fila, b, indice) {
          return fila.then(function () {
            if (c.onProgresso) { c.onProgresso(indice / blocos.length, b.t); }
            return sintetizar(b.t, b.corte, 0);
          });
        }, Promise.resolve());
      }).then(function () {
        var medidas = pecas.filter(function (p) { return p.msChar > 0 && p.chars >= 40; });
        if (medidas.length >= 4) {
          var taxas = medidas.map(function (p) { return p.msChar; })
                             .sort(function (a, b) { return a - b; });
          var mediana = taxas[Math.floor(taxas.length / 2)];
          medidas.forEach(function (p) {
            if (p.msChar < mediana * 0.6) {
              notas.push('Trecho mais rápido que o resto da fala (' +
                         Math.round(p.msChar) + ' contra ' + Math.round(mediana) + ' ms por letra).');
            }
          });
        }
        if (notas.length) { console.warn('[TrVoz] ' + notas.join('\n')); }

        if (!pecas.length) {
          throw new Error(
            'O modelo não devolveu áudio para este texto. Verifique se a fala tem ' +
            'palavras — só pontuação ou símbolos não geram som. Se a voz "' + voz +
            '" foi trocada há pouco, confirme que ela existe no repositório do modelo.'
          );
        }

        var completo = juntar(pecas, taxa, pausaMs);
        completo = reamostrar(completo, ajusteFinal);
        completo = normalizar(completo, ganho);

        var fonemas = fonemasTotais.join(' ');
        var lipsync = montarLipsync(completo, taxa, fonemas);

        return paraMp3(completo, taxa, c.kbps || 96).then(function (blob) {
          if (c.onProgresso) { c.onProgresso(1, ''); }
          return {
            blob: blob,
            duracaoMs: Math.round((completo.length / taxa) * 1000),
            lipsync: lipsync,
            fonemas: fonemas,
            avisos: avisos,
            notas: notas,
            desconhecidos: {}
          };
        });
      });
    },

    textoDoModelo: function (texto) {
      return dividirBlocos(texto).map(function (b) { return b.t; }).join(' | ');
    },

    montarLipsync: montarLipsync
  };

  global.TrVoz = TrVoz;
})(window);
