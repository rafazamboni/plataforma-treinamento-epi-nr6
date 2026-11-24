(function (global) {
  'use strict';

  var CDN_KOKORO = ['/assets/vendor/kokoro-js@1.2.1/kokoro.web.js'];
  var KOKORO_WASM = '/assets/vendor/kokoro-js@1.2.1/ort/';
  var CDN_MP3 = ['/assets/vendor/lamejs@1.2.7/dist/lamejs.js'];
  var ESPEAK_BASE = '/assets/vendor/espeak-ng@1.0.2/dist/';
  var CDN_ESPEAK  = [ESPEAK_BASE + 'espeak-ng.js'];
  var MODELO = 'onnx-community/Kokoro-82M-v1.0-ONNX';

  var LIMITE_TOKENS = 480;

  var motor = null;
  var motorQualidade = '';
  var carregando = null;
  var lame = null;
  var vocabulario = null;
  var faltantes = {};

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

  function rodarEspeak(texto, idioma) {
    var bytes = new TextEncoder().encode(texto);
    return espeakFabrica({
      arguments: ['--phonout', 'fonemas.txt', '-q', '--ipa=3', '-v', idioma || 'pt-br', '-f', 'entrada.txt'],
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

  var EQUIV = {
    '\u0261': 'g',
    '\u027E': ['\u0279', 'r'],
    '\u0281': ['\u0263', 'x', 'h'],
    '\u0280': ['r', 'h'],
    '\u028E': ['\u028E', 'l' + 'j'],
    '\u0272': ['\u0272', 'n' + 'j'],
    '\u0250': ['\u0259', 'a'],
    '\u0268': ['i'],
    '\u026A': ['i'],
    '\u028A': ['u'],
    '\u025C': ['\u025B', 'e'],
    '\u0264': ['o'],
    '\u0275': ['\u0259', 'o'],
    '\u0258': ['\u0259', 'e'],
    '\u02A7': ['t' + '\u0283'],
    '\u02A4': ['d' + '\u0292'],
    '\u02A6': ['t' + 's'],
    '\u02A3': ['d' + 'z'],
    '\u02B0': [''],
    '\u02B2': [''],
    '\u02D0': [''],
    '\u02D1': [''],
    '\u0329': [''],
    '\u032F': [''],
    '\u031E': [''], '\u0324': [''], '\u0325': [''], '\u033A': [''], '\u033C': ['']
  };

  var NASAL = '\u0303';
  var NASAL_PARA = { 'a': 'a', '\u0250': 'a', '\u00E3': 'a', 'e': 'e', '\u025B': 'e',
                     'i': 'i', 'o': 'o', '\u0254': 'o', 'u': 'u', '\u0259': 'e' };

  var SONDA = ['a', 'i', 'u', 'e', 'o', ',', '.'];

  function leVocabulario(m) {
    var bruto = null;
    try {
      var t = m && m.tokenizer;
      if (!t) { return null; }
      if (typeof t.get_vocab === 'function') { bruto = t.get_vocab(); }
      if (!bruto && t.model && t.model.vocab) { bruto = t.model.vocab; }
      if (!bruto && t.vocab) { bruto = t.vocab; }
      if (!bruto && t.model && t.model.tokens_to_ids) { bruto = t.model.tokens_to_ids; }
    } catch (e) { return null; }
    if (!bruto) { return null; }

    var set = new Set();
    try {
      if (bruto instanceof Map) {
        bruto.forEach(function (v, k) { set.add(String(k)); });
      } else if (Array.isArray(bruto)) {
        bruto.forEach(function (item) {
          if (Array.isArray(item)) { set.add(String(item[0])); }
          else if (item != null && typeof item === 'object') { set.add(String(item.token || item.value || '')); }
          else { set.add(String(item)); }
        });
      } else if (typeof bruto === 'object') {
        Object.keys(bruto).forEach(function (k) { set.add(k); });
      }
    } catch (e) { return null; }

    if (!set.size) { return null; }

    var achou = 0;
    SONDA.forEach(function (c) { if (set.has(c)) { achou++; } });
    if (achou < 4) {
      console.warn('[TrVoz] vocabulário do modelo em formato não reconhecido — o filtro de fonemas fica desligado.');
      return null;
    }
    return set;
  }

  function noVocab(c) {
    if (!vocabulario) { return true; }
    return vocabulario.has(c);
  }

  function ajustarAoVocabulario(ps) {
    var entrada = String(ps || '');
    var saida = '';
    var perdidos = {};

    for (var i = 0; i < entrada.length; i++) {
      var c = entrada[i];

      if (c === NASAL) {
        if (noVocab(NASAL)) { saida += NASAL; continue; }
        var anterior = saida.slice(-1);
        var nasalizada = NASAL_PARA[anterior];
        if (nasalizada !== undefined) {
          var prox = entrada[i + 1] || '';
          saida += (prox === 'p' || prox === 'b' || prox === 'm') ? 'm' : 'n';
        }
        continue;
      }

      if (noVocab(c)) { saida += c; continue; }

      var alt = EQUIV[c];
      if (alt !== undefined) {
        var lista = Array.isArray(alt) ? alt : [alt];
        var usou = false;
        for (var k = 0; k < lista.length; k++) {
          var cand = lista[k];
          if (cand === '') { usou = true; break; }
          var ok = true;
          for (var j = 0; j < cand.length; j++) { if (!noVocab(cand[j])) { ok = false; break; } }
          if (ok) { saida += cand; usou = true; break; }
        }
        if (usou) { continue; }
      }

      perdidos[c] = (perdidos[c] || 0) + 1;
    }

    Object.keys(perdidos).forEach(function (c) { faltantes[c] = (faltantes[c] || 0) + perdidos[c]; });

    var limpo = saida.replace(/\s+/g, ' ').trim();
    if (limpo.replace(/[\s.,!?;:]/g, '') === '' && entrada.trim() !== '') {
      console.warn('[TrVoz] o filtro de vocabulário esvaziou a frase; usando os fonemas originais.');
      return { ps: entrada.replace(/\s+/g, ' ').trim(), perdidos: {} };
    }
    return { ps: limpo, perdidos: perdidos };
  }

  function dividirFrases(texto) {
    var bruto = String(texto || '')
      .replace(/[\u2018\u2019]/g, "'")
      .replace(/[\u201C\u201D]/g, '"')
      .replace(/\u2026/g, '.')
      .replace(/\s+/g, ' ')
      .trim();
    if (!bruto) { return []; }

    var partes = bruto.match(/[^.!?;]+[.!?;]*/g) || [bruto];
    var frases = [];
    partes.forEach(function (p) {
      var t = p.trim();
      if (t === '') { return; }
      if (t.length > 220) {
        var buffer = '';
        t.split(/(?<=,)/).forEach(function (peca) {
          if ((buffer + peca).length > 220 && buffer !== '') { frases.push(buffer.trim()); buffer = ''; }
          buffer += peca;
        });
        if (buffer.trim() !== '') { frases.push(buffer.trim()); }
      } else {
        frases.push(t);
      }
    });
    return frases;
  }

  function fonemizarFrase(frase) {
    return rodarEspeak(frase, 'pt-br').then(function (saida) {
      var linhas = String(saida || '').split('\n')
        .map(function (l) { return l.trim(); })
        .filter(function (l) { return l !== ''; });
      if (!linhas.length) { return ''; }

      var ps = linhas.join(' ');
      var pontuacao = (frase.match(/[.!?;,]\s*$/) || [''])[0].trim().charAt(0) || '.';
      if (pontuacao === ';') { pontuacao = ','; }
      ps = aplicarLigaduras(ps).replace(/\s+/g, ' ').trim();
      if (ps === '') {
        console.warn('[TrVoz] o eSpeak não devolveu fonemas para:', frase);
        return '';
      }
      var ajustado = ajustarAoVocabulario(ps).ps;
      if (ajustado !== '') { ps = ajustado; }
      if (!/[.,!?]$/.test(ps)) { ps += pontuacao; }
      return ps;
    });
  }

  function aparar(amostras, taxa) {
    var limiar = 0.006;
    var ini = 0, fim = amostras.length - 1;
    while (ini < fim && Math.abs(amostras[ini]) < limiar) { ini++; }
    while (fim > ini && Math.abs(amostras[fim]) < limiar) { fim--; }
    var folga = Math.round(taxa * 0.025);
    ini = Math.max(0, ini - folga);
    fim = Math.min(amostras.length - 1, fim + folga);
    return amostras.subarray ? amostras.subarray(ini, fim + 1) : amostras.slice(ini, fim + 1);
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

  function juntar(trechos, taxa, pausaMs) {
    var silencio = Math.max(0, Math.round((pausaMs || 0) / 1000 * taxa));
    var total = 0;
    trechos.forEach(function (t, i) { total += t.length + (i < trechos.length - 1 ? silencio : 0); });
    var saida = new Float32Array(total);
    var pos = 0;
    trechos.forEach(function (t, i) {
      saida.set(t, pos);
      pos += t.length;
      if (i < trechos.length - 1) { pos += silencio; }
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
    rapida:  { rotulo: 'Rápida (menor fidelidade)', webgpu: 'q8',   wasm: 'q8' },
    alta:    { rotulo: 'Alta (recomendada)',        webgpu: 'fp32', wasm: 'q8' },
    maxima:  { rotulo: 'Máxima (bem mais lenta)',   webgpu: 'fp32', wasm: 'fp32' }
  };

  var TrVoz = {

    vozes: {
      pf_dora:  'Dora — feminina (pt-BR)',
      pm_alex:  'Alex — masculina (pt-BR)',
      pm_santa: 'Santa — voz temática de Papai Noel (pt-BR)'
    },

    qualidades: QUALIDADES,

    disponivel: function () { return typeof WebAssembly === 'object'; },

    pronto: function () { return !!motor && !!espeakModulo; },

    faltantes: function () { return Object.assign({}, faltantes); },

    vocabulario: function () {
      return vocabulario ? { simbolos: vocabulario.size, filtrando: true } : { simbolos: 0, filtrando: false };
    },

    preparar: function (onProgresso, qualidade) {
      var q = QUALIDADES[qualidade] ? qualidade : 'alta';
      if (motor && espeakModulo && motorQualidade === q) { return Promise.resolve(motor); }
      if (carregando && motorQualidade === q) { return carregando; }
      if (!this.disponivel()) {
        return Promise.reject(new Error('Este navegador não suporta a geração local de voz.'));
      }

      if (motor && motorQualidade !== q) { motor = null; vocabulario = null; }
      motorQualidade = q;

      var temWebGpu = !!global.navigator.gpu;
      var dtype = temWebGpu ? QUALIDADES[q].webgpu : QUALIDADES[q].wasm;

      carregando = prepararEspeak(onProgresso).then(function () {
        if (motor) { return motor; }
        return importarPrimeiro(CDN_KOKORO).then(function (mod) {
          var KokoroTTS = mod.KokoroTTS || (mod.default && mod.default.KokoroTTS);
          if (!KokoroTTS) { throw new Error('Biblioteca de voz inválida.'); }
          if (mod.env) { mod.env.wasmPaths = KOKORO_WASM; }
          return KokoroTTS.from_pretrained(MODELO, {
            dtype: dtype,
            device: temWebGpu ? 'webgpu' : 'wasm',
            progress_callback: function (p) {
              if (onProgresso && p && p.total) {
                onProgresso(Math.min(1, (p.loaded || 0) / p.total), p.file || '');
              }
            }
          });
        });
      }).then(function (m) {
        motor = m;
        vocabulario = leVocabulario(m);
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
        var frases = dividirFrases(texto);
        var perdidosTotal = {};
        return frases.reduce(function (fila, frase) {
          return fila.then(function (acc) {
            return rodarEspeak(frase, 'pt-br').then(function (saida) {
              var ps = aplicarLigaduras(String(saida || '').split('\n').join(' ')).replace(/\s+/g, ' ').trim();
              var r = ajustarAoVocabulario(ps);
              Object.keys(r.perdidos).forEach(function (k) {
                perdidosTotal[k] = (perdidosTotal[k] || 0) + r.perdidos[k];
              });
              acc.push({ frase: frase, fonemas: r.ps });
              return acc;
            });
          });
        }, Promise.resolve([])).then(function (lista) {
          return {
            frases: lista,
            fonemas: lista.map(function (f) { return f.fonemas; }).join(' '),
            desconhecidos: perdidosTotal,
            comVocabulario: !!vocabulario
          };
        });
      });
    },

    fonemas: function (texto) {
      return this.diagnostico(texto).then(function (d) { return d.fonemas; });
    },

    gerar: function (texto, cfg) {
      var c = cfg || {};
      var frases = dividirFrases(texto);
      if (!frases.length) { return Promise.reject(new Error('Texto vazio.')); }

      var tom = Math.max(0.8, Math.min(1.25, Number(c.tom) || 1));
      var velocidade = Math.max(0.6, Math.min(1.6, Number(c.velocidade) || 1));
      var pausaMs = c.pausaMs == null ? 260 : Math.max(0, Math.min(1200, Number(c.pausaMs)));
      var ganho = Math.max(0.5, Math.min(1.8, Number(c.ganho) || 1));

      var velocidadeModelo = Math.max(0.5, Math.min(2, velocidade / tom));

      var fonemasTotais = [];
      var vazias = 0;
      var desconhecidos = {};
      var trechos = [];
      var taxa = 24000;

      return this.preparar(c.onProgressoModelo, c.qualidade).then(function (m) {
        return frases.reduce(function (fila, frase, indice) {
          return fila.then(function () {
            if (c.onProgresso) { c.onProgresso(indice / frases.length, frase); }
            return fonemizarFrase(frase).then(function (ps) {
              if (!ps) { vazias++; return; }
              fonemasTotais.push(ps);

              var ids = m.tokenizer(ps, { truncation: true }).input_ids;
              var tamanho = (ids.dims && ids.dims[ids.dims.length - 1]) || 0;
              if (tamanho > LIMITE_TOKENS) {
                console.warn('[TrVoz] frase longa demais, o final pode ser cortado:', frase);
              }

              return m.generate_from_ids(ids, {
                voice: c.voz || 'pf_dora',
                speed: velocidadeModelo
              }).then(function (saida) {
                var amostras = saida.audio || saida.data;
                taxa = saida.sampling_rate || saida.samplingRate || taxa;
                amostras = aparar(amostras, taxa);
                amostras = reamostrar(amostras, tom);
                trechos.push(amostras);
              });
            });
          });
        }, Promise.resolve());
      }).then(function () {
        if (!trechos.length) {
          throw new Error(
            'Não foi possível converter o texto em fonemas (' + vazias + ' de ' + frases.length +
            ' frases ficaram vazias). Verifique se o texto tem palavras — só pontuação ou símbolos ' +
            'não geram áudio. Se continuar, use "Conferir pronúncia" no painel de voz para ver ' +
            'o que o conversor devolveu.'
          );
        }

        var completo = juntar(trechos, taxa, pausaMs);
        completo = normalizar(completo, ganho);

        var fonemas = fonemasTotais.join(' ');
        var lipsync = montarLipsync(completo, taxa, fonemas);
        Object.assign(desconhecidos, faltantes);

        return paraMp3(completo, taxa, c.kbps || 96).then(function (blob) {
          if (c.onProgresso) { c.onProgresso(1, ''); }
          return {
            blob: blob,
            duracaoMs: Math.round((completo.length / taxa) * 1000),
            lipsync: lipsync,
            fonemas: fonemas,
            desconhecidos: desconhecidos
          };
        });
      });
    },

    montarLipsync: montarLipsync
  };

  global.TrVoz = TrVoz;
})(window);
