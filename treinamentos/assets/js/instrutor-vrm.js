(function (global) {
  'use strict';

  var DEDOS = ['Thumb', 'Index', 'Middle', 'Ring', 'Little'];

  var JUNTAS_DEDO = {
    Thumb:  [['ThumbMetacarpal', 'ThumbProximal'], ['ThumbProximal', 'ThumbIntermediate'], ['ThumbDistal', 'ThumbDistal']],
    Index:  [['IndexProximal', 'IndexProximal'], ['IndexIntermediate', 'IndexIntermediate'], ['IndexDistal', 'IndexDistal']],
    Middle: [['MiddleProximal', 'MiddleProximal'], ['MiddleIntermediate', 'MiddleIntermediate'], ['MiddleDistal', 'MiddleDistal']],
    Ring:   [['RingProximal', 'RingProximal'], ['RingIntermediate', 'RingIntermediate'], ['RingDistal', 'RingDistal']],
    Little: [['LittleProximal', 'LittleProximal'], ['LittleIntermediate', 'LittleIntermediate'], ['LittleDistal', 'LittleDistal']]
  };

  var MAOS = {
    relaxada:  { Thumb: 0.42, Index: 0.38, Middle: 0.46, Ring: 0.54, Little: 0.62, juntas: [1, 1.2, 0.8], abrir: 0.05 },
    aberta:    { Thumb: 0.16, Index: 0.06, Middle: 0.04, Ring: 0.07, Little: 0.12, juntas: [1, 1.0, 0.7], abrir: 0.14 },
    palma:     { Thumb: 0.24, Index: 0.12, Middle: 0.10, Ring: 0.15, Little: 0.22, juntas: [1, 0.95, 0.7], abrir: 0.10 },
    concha:    { Thumb: 0.50, Index: 0.42, Middle: 0.44, Ring: 0.48, Little: 0.54, juntas: [1, 1.25, 0.9], abrir: 0.04 },
    punho:     { Thumb: 0.90, Index: 1.42, Middle: 1.48, Ring: 1.48, Little: 1.42, juntas: [1, 1.25, 0.8], abrir: -0.02 },
    apontando: { Thumb: 0.72, Index: 0.04, Middle: 1.42, Ring: 1.48, Little: 1.44, juntas: [1, 1.25, 0.8], abrir: 0 },
    polegar:   { Thumb: 0.02, Index: 1.46, Middle: 1.48, Ring: 1.48, Little: 1.42, juntas: [1, 1.25, 0.8], abrir: 0 },
    pinca:     { Thumb: 0.80, Index: 0.90, Middle: 0.50, Ring: 0.56, Little: 0.62, juntas: [1, 1.15, 0.9], abrir: 0.05 },
    contando1: { Thumb: 0.88, Index: 0.04, Middle: 1.44, Ring: 1.48, Little: 1.42, juntas: [1, 1.25, 0.8], abrir: 0 },
    contando2: { Thumb: 0.88, Index: 0.04, Middle: 0.06, Ring: 1.48, Little: 1.42, juntas: [1, 1.25, 0.8], abrir: 0.12 },
    contando3: { Thumb: 0.88, Index: 0.04, Middle: 0.06, Ring: 0.10, Little: 1.42, juntas: [1, 1.25, 0.8], abrir: 0.12 },
    contando4: { Thumb: 0.92, Index: 0.04, Middle: 0.04, Ring: 0.06, Little: 0.10, juntas: [1, 1.2, 0.8], abrir: 0.15 },
    contando5: { Thumb: 0.10, Index: 0.03, Middle: 0.02, Ring: 0.04, Little: 0.08, juntas: [1, 1.0, 0.7], abrir: 0.19 }
  };

  var BRACO_REPOUSO = { braco: [0.16, -0.97, 0.14], cotovelo: 0.21, giroBraco: 0.06, pulso: { flexao: 0.04, desvio: 0, giro: 0.49 }, mao: 'relaxada' };

  var POSE_BASE = {
    leftShoulder:  { x: 0, y: 0, z: 0 },
    rightShoulder: { x: 0, y: 0, z: 0 },
    hips:          { x: 0, y: 0, z: 0 },
    spine:         { x: 0, y: 0, z: 0 },
    chest:         { x: 0, y: 0, z: 0 },
    neck:          { x: 0, y: 0, z: 0 },
    head:          { x: 0, y: 0, z: 0 }
  };

  var GIROS = { nenhum: 0, leve: 0.18, moderado: 0.34, tresquartos: 0.58, perfil: 0.95 };

  var GESTOS = {
    acenar: {
      alvo: 'publico',
      ativo: { braco: [0.72, 0.35, 0.6], cotovelo: 0.78, giroBraco: -1.69, pulso: { flexao: 0, desvio: 0, giro: 0.52 }, mao: 'aberta', osc: { desvio: { a: 0.4, hz: 2.1 } } },
      cabeca: { inclinar: -0.04, girar: 0.05 }
    },
    apresentar: {
      alvo: 'conteudo',
      ativo: { braco: [0.45, -0.62, 0.65], cotovelo: 0.72, giroBraco: -0.81, pulso: { flexao: -0.12, desvio: 0, giro: -1.48 }, mao: 'palma' },
      cabeca: { girar: 0.16 },
      tronco: { girar: 0.10 }
    },
    apontar: {
      alvo: 'conteudo',
      ativo: { braco: [0.5, -0.3, 0.81], cotovelo: 0.22, giroBraco: -0.72, pulso: { flexao: 0, desvio: 0, giro: 0.92 }, mao: 'apontando' },
      cabeca: { girar: 0.22 },
      tronco: { girar: 0.12 }
    },
    'apontar-cima': {
      alvo: 'cima',
      ativo: { braco: [0.3, 0.8, 0.52], cotovelo: 0.3, giroBraco: -2.63, pulso: { flexao: 0, desvio: 0, giro: -0.1 }, mao: 'apontando' },
      cabeca: { inclinar: -0.2, girar: 0.12 },
      tronco: { inclinar: -0.05 }
    },
    'apontar-baixo': {
      alvo: 'baixo',
      ativo: { braco: [0.44, -0.74, 0.5], cotovelo: 0.4, giroBraco: -0.28, pulso: { flexao: 0.24, desvio: 0, giro: 0.6 }, mao: 'apontando' },
      cabeca: { inclinar: 0.17, girar: 0.14 },
      tronco: { inclinar: 0.05, girar: 0.07 }
    },
    'apontar-lado': {
      alvo: 'conteudo',
      ativo: { braco: [0.94, -0.14, 0.3], cotovelo: 0.16, giroBraco: -1.02, pulso: { flexao: 0, desvio: 0, giro: 0.38 }, mao: 'apontando' },
      cabeca: { girar: 0.34 },
      tronco: { girar: 0.2 }
    },
    explicar: {
      alvo: 'publico',
      ambos: { braco: [0.24, -0.86, 0.45], cotovelo: 1.09, giroBraco: -0.4, pulso: { flexao: -0.1, desvio: 0, giro: -1.4 }, mao: 'palma', osc: { cotovelo: { a: 0.16, hz: 0.8 }, desvio: { a: 0.1, hz: 0.8 }, dedos: { a: 0.07, hz: 0.6 } } }
    },
    enumerar: {
      alvo: 'publico',
      ativo: { braco: [0.2, -0.88, 0.43], cotovelo: 1.44, giroBraco: 0.41, pulso: { flexao: 0, desvio: 0, giro: -1.51 }, mao: 'contando3', osc: { flexao: { a: 0.2, hz: 1.2 } } },
      apoio: { braco: [0.16, -0.97, 0.14], cotovelo: 0.21, giroBraco: 0.06, pulso: { flexao: 0.04, desvio: 0, giro: 0.49 }, mao: 'relaxada' }
    },
    enfatizar: {
      alvo: 'publico',
      ativo: { braco: [0.26, -0.8, 0.54], cotovelo: 1.07, giroBraco: -0.14, pulso: { flexao: 0, desvio: 0, giro: -0.23 }, mao: 'palma', osc: { flexao: { a: 0.28, hz: 1.5 }, cotovelo: { a: 0.14, hz: 1.5 } } },
      tronco: { girar: 0.06 }
    },
    convidar: {
      alvo: 'publico',
      ativo: { braco: [0.42, -0.66, 0.62], cotovelo: 0.67, giroBraco: -0.78, pulso: { flexao: -0.14, desvio: 0, giro: -1.45 }, mao: 'palma' },
      cabeca: { inclinar: -0.03, girar: 0.1 }
    },
    'abrir-bracos': {
      alvo: 'publico',
      ambos: { braco: [0.72, -0.3, 0.62], cotovelo: 0.36, giroBraco: -1.51, pulso: { flexao: -0.12, desvio: 0, giro: -0.69 }, mao: 'aberta' },
      cabeca: { inclinar: -0.06 },
      tronco: { inclinar: -0.07 }
    },
    'maos-juntas': {
      alvo: 'publico',
      ambos: { braco: [0.18, -0.9, 0.4], cotovelo: 1.61, giroBraco: 0.45, pulso: { flexao: 0, desvio: 0, giro: -0.07 }, mao: 'palma' }
    },
    comparar: {
      alvo: 'publico',
      ativo: { braco: [0.4, -0.78, 0.5], cotovelo: 1.3, giroBraco: -0.35, pulso: { flexao: -0.08, desvio: 0, giro: -1.5 }, mao: 'palma', osc: { cotovelo: { a: 0.22, hz: 0.7 } } },
      apoio: { braco: [0.4, -0.86, 0.46], cotovelo: 1.12, giroBraco: -0.35, pulso: { flexao: -0.08, desvio: 0, giro: -1.5 }, mao: 'palma', osc: { cotovelo: { a: -0.22, hz: 0.7 } } },
      cabeca: { girar: 0.08 }
    },
    parar: {
      alvo: 'publico',
      ativo: { braco: [0.34, -0.5, 0.79], cotovelo: 1.18, giroBraco: -0.62, pulso: { flexao: -0.42, desvio: 0, giro: -0.95 }, mao: 'palma' },
      cabeca: { inclinar: -0.04 }
    },
    ok: {
      alvo: 'publico',
      ativo: { braco: [0.3, -0.66, 0.68], cotovelo: 1.24, giroBraco: -0.52, pulso: { flexao: -0.05, desvio: 0, giro: -1.05 }, mao: 'pinca' },
      cabeca: { inclinar: -0.04, girar: 0.06 }
    },
    silencio: {
      alvo: 'publico',
      ativo: { braco: [0.12, -0.74, 0.42], cotovelo: 2.32, giroBraco: 0.18, pulso: { flexao: -0.12, desvio: 0, giro: -0.58 }, mao: 'apontando' },
      cabeca: { inclinar: -0.03, girar: -0.05 }
    },
    pensar: {
      alvo: 'publico',
      ativo: { braco: [0.16, -0.9, 0.4], cotovelo: 2.27, giroBraco: 0.13, pulso: { flexao: 0.2, desvio: 0, giro: -0.64 }, mao: 'concha' },
      cabeca: { inclinar: -0.04, girar: -0.14, tombar: 0.1 },
      pescoco: { inclinar: 0.05, tombar: 0.06 }
    },
    aprovar: {
      alvo: 'publico',
      ativo: { braco: [0.24, -0.8, 0.55], cotovelo: 1.73, giroBraco: -0.26, pulso: { flexao: 0, desvio: 0, giro: -0.08 }, mao: 'polegar' },
      cabeca: { inclinar: -0.05, girar: 0.06 }
    },
    atencao: {
      alvo: 'publico',
      ativo: { braco: [0.22, -0.7, 0.68], cotovelo: 2.02, giroBraco: -0.44, pulso: { flexao: 0, desvio: 0, giro: 1.03 }, mao: 'apontando', osc: { flexao: { a: 0.12, hz: 1.1 } } },
      cabeca: { inclinar: -0.06 }
    },
    aplaudir: {
      alvo: 'publico',
      ambos: { braco: [0.2, -0.85, 0.49], cotovelo: 1.45, giroBraco: 0.52, pulso: { flexao: 0, desvio: 0, giro: -0.4 }, mao: 'aberta', osc: { desvio: { a: 0.26, hz: 3 } } },
      cabeca: { inclinar: -0.04 }
    },
    agradecer: {
      alvo: 'publico',
      ambos: { braco: [0.16, -0.96, 0.24], cotovelo: 0.18, giroBraco: 0.05, pulso: { flexao: 0, desvio: 0, giro: 0.39 }, mao: 'aberta' },
      cabeca: { inclinar: 0.16 },
      tronco: { inclinar: 0.12 },
      coluna: { inclinar: 0.2 }
    },
    concordar: {
      alvo: 'publico',
      pescoco: { inclinar: 0.04 },
      cabecaOsc: { eixo: 'x', a: 0.19, hz: 1.4 }
    },
    discordar: {
      alvo: 'publico',
      cabecaOsc: { eixo: 'y', a: 0.28, hz: 1.3 }
    },
    'inclinar-cabeca': {
      alvo: 'conteudo',
      cabeca: { inclinar: 0.02, girar: 0.1, tombar: 0.26 },
      pescoco: { tombar: 0.09 }
    },
    'olhar-conteudo': {
      alvo: 'conteudo',
      cabeca: { girar: 0.3, inclinar: 0.02 },
      tronco: { girar: 0.14 }
    }
  };

  var EXPRESSOES = ['happy', 'angry', 'sad', 'relaxed', 'surprised'];
  var VISEMAS = ['aa', 'ih', 'ou', 'ee', 'oh'];

  var ORIENT_PADRAO = {
    posicao: 'direita',
    ladoConteudo: 'auto',
    maoDominante: 'direita',
    autoOrientar: true,
    giroCorpo: 'leve',
    giroQuadril: false,
    forcaTronco: 1,
    forcaCabeca: 1,
    forcaGesto: 1,
    olharCabeca: 0.35,
    rostoPublico: 0.5,
    alturaConteudo: 0,
    suavidade: 1
  };

  function limitar(v, min, max) { return v < min ? min : (v > max ? max : v); }

  function Instrutor3D(el, cfg) {
    this.el = el;
    this.cfg = Object.assign({ url: '', nome: 'Instrutor', camera: 'upper', humor: 'neutral' }, cfg || {});
    this.ativo = false;
    this.erro = '';
    this.poseAlvo = {};
    this.poseAtual = {};
    this.bracosAlvo = null;
    this.esqueleto = null;
    this.cabecaOsc = null;
    this.timerGesto = null;
    this.expressaoAtual = '';
    this.lip = null;
    this.visemaSuave = { aa: 0, ih: 0, ou: 0, ee: 0, oh: 0 };
    this.olharAlvo = 'camera';
    this.olharForca = 1;

    this.orient = Object.assign({}, ORIENT_PADRAO, (cfg && cfg.orientacao) || {});
    this.orientBase = Object.assign({}, this.orient);
    this._gestoAtual = null;
    this._modoCabeca = 'auto';
    this._yaw = 0;
    this._alturaCabeca = 1.4;
    this._resolverOrientacao();
  }

  Instrutor3D.GESTOS = Object.keys(GESTOS);
  Instrutor3D.DEF_GESTOS = GESTOS;
  Instrutor3D.MAOS = MAOS;
  Instrutor3D.GIROS = GIROS;
  Instrutor3D.REPOUSO_BRACO = BRACO_REPOUSO;
  Instrutor3D.EXPRESSOES = EXPRESSOES;
  Instrutor3D.ORIENT_PADRAO = ORIENT_PADRAO;

  Instrutor3D.prototype.orientacao = function (cfg) {
    if (cfg) { Object.assign(this.orient, cfg); }
    this._resolverOrientacao();
    this._aplicarGesto();
    return this.orient;
  };

  Instrutor3D.prototype.orientacaoBase = function (cfg) {
    if (cfg) {
      Object.assign(this.orientBase, cfg);
      Object.assign(this.orient, cfg);
    }
    this._resolverOrientacao();
    this._aplicarGesto();
    return this.orientBase;
  };

  Instrutor3D.prototype._resolverOrientacao = function () {
    var o = this.orient;
    var lado = o.ladoConteudo;

    if (!lado || lado === 'auto') {
      if (o.posicao === 'esquerda' || o.posicao === 'centro-esquerda') { lado = 'direita'; }
      else if (o.posicao === 'direita' || o.posicao === 'centro-direita') { lado = 'esquerda'; }
      else { lado = 'centro'; }
    }

    this._ladoTela = lado;
    this._sinalTela = lado === 'direita' ? 1 : (lado === 'esquerda' ? -1 : 0);
    this._bracoConteudo = lado === 'direita' ? 'left' : (lado === 'esquerda' ? 'right' : null);
    this._bracoDominante = o.maoDominante === 'esquerda' ? 'left' : 'right';
  };

  Instrutor3D.prototype._resolverBraco = function (g, forcado) {
    if (forcado === 'ambos') { return 'ambos'; }
    if (forcado === 'esquerdo') { return 'left'; }
    if (forcado === 'direito') { return 'right'; }

    if (g && g.ambos) { return 'ambos'; }

    var alvo = (g && g.alvo) || 'publico';
    var dirigido = (alvo === 'conteudo');
    var escolha = (this.orient.autoOrientar && dirigido && this._bracoConteudo)
      ? this._bracoConteudo
      : this._bracoDominante;

    if (forcado === 'espelhar') { escolha = escolha === 'left' ? 'right' : 'left'; }
    return escolha;
  };

  Instrutor3D.prototype._sinalGesto = function (ladoAtivo) {
    if (ladoAtivo === 'left') { return 1; }
    if (ladoAtivo === 'right') { return -1; }
    return this._sinalTela || 0;
  };

  Instrutor3D.prototype._giroBaseRad = function (modo) {
    var chave = modo && modo !== 'herdar' ? modo : this.orient.giroCorpo;
    var mag = GIROS[chave];
    if (mag == null) { mag = GIROS.leve; }
    return mag * (this._sinalTela || 0);
  };

  Instrutor3D.prototype.suportado = function () {
    try {
      var c = document.createElement('canvas');
      return !!(global.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl')));
    } catch (e) { return false; }
  };

  Instrutor3D.prototype.iniciar = function (onProgresso) {
    var self = this;
    if (this.ativo) { return Promise.resolve(true); }
    if (!this.cfg.url) { this.erro = 'Nenhum avatar definido.'; return Promise.resolve(false); }
    if (!this.suportado()) { this.erro = 'Este dispositivo não tem WebGL.'; return Promise.resolve(false); }

    return Promise.all([
      import('three'),
      import('three/addons/loaders/GLTFLoader.js'),
      import('@pixiv/three-vrm')
    ]).then(function (mods) {
      var THREE = mods[0];
      var GLTFLoader = mods[1].GLTFLoader;
      var VRM = mods[2];
      self.THREE = THREE;

      var largura = self.el.clientWidth || 300;
      var altura = self.el.clientHeight || 420;

      var renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true, preserveDrawingBuffer: true });
      renderer.setPixelRatio(Math.min(global.devicePixelRatio || 1, 2));
      renderer.setSize(largura, altura, false);
      renderer.outputColorSpace = THREE.SRGBColorSpace;
      self.el.appendChild(renderer.domElement);

      var cena = new THREE.Scene();
      var cam = new THREE.PerspectiveCamera(28, largura / altura, 0.1, 20);

      var luzAmbiente = new THREE.HemisphereLight(0xffffff, 0xdfe7ef, 2.1);
      cena.add(luzAmbiente);
      var luzFrontal = new THREE.DirectionalLight(0xffffff, 1.6);
      luzFrontal.position.set(0.6, 1.6, 1.4);
      cena.add(luzFrontal);
      var luzRecorte = new THREE.DirectionalLight(0xdfeaf5, 0.8);
      luzRecorte.position.set(-1.2, 1.2, -1.0);
      cena.add(luzRecorte);

      self.renderer = renderer;
      self.cena = cena;
      self.cam = cam;
      self.relogio = new THREE.Clock();

      var loader = new GLTFLoader();
      loader.register(function (parser) { return new VRM.VRMLoaderPlugin(parser); });

      return loader.loadAsync(self.cfg.url, function (ev) {
        if (onProgresso && ev && ev.total) { onProgresso(Math.min(1, ev.loaded / ev.total)); }
      }).then(function (gltf) {
        var vrm = gltf.userData.vrm;
        if (!vrm) { throw new Error('O arquivo não é um VRM válido.'); }

        VRM.VRMUtils.removeUnnecessaryVertices(gltf.scene);
        VRM.VRMUtils.combineSkeletons(gltf.scene);
        if (VRM.VRMUtils.rotateVRM0) { VRM.VRMUtils.rotateVRM0(vrm); }

        vrm.scene.traverse(function (o) { o.frustumCulled = false; });
        cena.add(vrm.scene);

        self.vrm = vrm;
        self.aplicarPoseBase();
        self.redimensionar();
        self.humor(self.cfg.humor);
        self.ativo = true;
        self.animar();
        global.addEventListener('resize', function () { self.redimensionar(); });
        return true;
      });
    }).catch(function (e) {
      self.erro = (e && e.message) ? e.message : 'Falha ao carregar o instrutor.';
      self.ativo = false;
      return false;
    });
  };

  Instrutor3D.prototype.ajustarFuga = function () {
    var caixa = this.el && this.el.closest && this.el.closest('.nv-instrutor');
    if (!caixa || !caixa.closest) { return; }
    var palco = caixa.closest('.nv-slide-wrap') ||
                caixa.closest('.nv-stage') ||
                caixa.closest('.nv-slide') ||
                caixa.parentElement;
    if (!palco) { return; }

    caixa.style.setProperty('--nv-instr-fuga-esq', '0px');
    caixa.style.setProperty('--nv-instr-fuga-dir', '0px');

    var rf = caixa.getBoundingClientRect();
    var rs = palco.getBoundingClientRect();
    if (!rf.width || !rs.width) { return; }

    caixa.style.setProperty('--nv-instr-fuga-esq', Math.max(0, rf.left - rs.left).toFixed(0) + 'px');
    caixa.style.setProperty('--nv-instr-fuga-dir', Math.max(0, rs.right - rf.right).toFixed(0) + 'px');
  };

  Instrutor3D.prototype.redimensionar = function () {
    if (!this.renderer) { return; }
    this.ajustarFuga();
    var l = this.el.clientWidth || 300;
    var a = this.el.clientHeight || 420;
    this.renderer.setSize(l, a, false);
    this.cam.aspect = l / a;
    this.cam.updateProjectionMatrix();
    this.enquadrar(this.cfg.camera);
  };

  var BRACO_CONTA = {
    braco: [0.2, -0.88, 0.43], cotovelo: 1.44, giroBraco: 0.41,
    pulso: { flexao: 0, desvio: 0, giro: -1.51 },
    osc: { flexao: { a: 0.14, hz: 1.1 } }
  };
  var BRACO_LADO = {
    braco: [0.16, -0.97, 0.14], cotovelo: 0.21, giroBraco: 0.06,
    pulso: { flexao: 0.04, desvio: 0, giro: 0.49 }, mao: 'relaxada'
  };

  function copiaBraco(base, mao) {
    return {
      braco: base.braco.slice(), cotovelo: base.cotovelo, giroBraco: base.giroBraco,
      pulso: { flexao: base.pulso.flexao, desvio: base.pulso.desvio, giro: base.pulso.giro },
      mao: mao,
      osc: base.osc ? { flexao: { a: base.osc.flexao.a, hz: base.osc.flexao.hz } } : undefined
    };
  }

  (function montarContagem() {
    for (var n = 1; n <= 10; n++) {
      var naFrente = n > 5 ? 5 : n;
      var mov = {
        alvo: 'publico',
        ativo: copiaBraco(BRACO_CONTA, 'contando' + naFrente),
        cabeca: { girar: 0.06 }
      };
      mov.apoio = (n > 5)
        ? copiaBraco(BRACO_CONTA, 'contando' + (n - 5))
        : copiaBraco(BRACO_LADO, 'relaxada');
      GESTOS['enumerar' + n] = mov;
    }
  })();

  Instrutor3D.prototype.enquadrar = function (vista) {
    if (vista && vista !== 'herdar') { this.cfg.camera = vista; }
    if (!this.vrm || !this.cam) { return; }

    var ossoCabeca = this.vrm.humanoid.getNormalizedBoneNode('head');
    var alturaCabeca = 1.4;
    if (ossoCabeca) {
      var p = new this.THREE.Vector3();
      ossoCabeca.getWorldPosition(p);
      alturaCabeca = p.y;
    }
    this._alturaCabeca = alturaCabeca;

    var mapa = {
      full:    { alvo: alturaCabeca * 0.52, altura: alturaCabeca * 1.28, largura: alturaCabeca * 0.95 },
      joelhos: { alvo: alturaCabeca * 0.64, altura: alturaCabeca * 1.00, largura: alturaCabeca * 0.88 },
      mid:     { alvo: alturaCabeca * 0.74, altura: alturaCabeca * 0.82, largura: alturaCabeca * 0.80 },
      upper:   { alvo: alturaCabeca * 0.88, altura: alturaCabeca * 0.52, largura: alturaCabeca * 0.62 },
      busto:   { alvo: alturaCabeca * 0.93, altura: alturaCabeca * 0.40, largura: alturaCabeca * 0.50 },
      head:    { alvo: alturaCabeca * 0.99, altura: alturaCabeca * 0.28, largura: alturaCabeca * 0.30 }
    };
    var v = mapa[this.cfg.camera] || mapa.upper;

    var faixa = this.medirFaixa();
    var aspecto = this.cam.aspect || 1;
    var meioV = (this.cam.fov * Math.PI / 180) / 2;
    var distAltura  = (v.altura / 2) / Math.tan(meioV);
    var meioH = Math.atan(Math.tan(meioV) * faixa.aspecto);
    var distLargura = (v.largura / 2) / Math.tan(meioH);

    var dist = Math.max(distAltura, distLargura) * 1.06;
    this.cam.position.set(0, v.alvo, dist);
    this.cam.lookAt(0, v.alvo, 0);

    var BASE = 1000;
    var desloc = (0.5 - faixa.centro) * BASE;
    if (Math.abs(desloc) < 0.5) {
      this.cam.clearViewOffset();
    } else {
      this.cam.setViewOffset(BASE, BASE / aspecto, desloc, 0, BASE, BASE / aspecto);
    }
    this.cam.updateProjectionMatrix();

    this.posicionarSombra(v, dist, meioV, faixa.aspecto, alturaCabeca);
  };

  Instrutor3D.prototype.medirFaixa = function () {
    var padrao = { centro: 0.5, aspecto: this.cam ? (this.cam.aspect || 1) : 1 };
    if (!this.el || !this.el.getBoundingClientRect) { return padrao; }

    var rt = this.el.getBoundingClientRect();
    var caixa = this.el.closest && this.el.closest('.nv-instrutor');
    var rf = caixa ? caixa.getBoundingClientRect() : rt;
    if (!rt.width || !rf.width || !rf.height) { return padrao; }

    return {
      centro: ((rf.left + rf.width / 2) - rt.left) / rt.width,
      aspecto: rf.width / rf.height
    };
  };

  Instrutor3D.prototype.posicionarSombra = function (v, dist, meioV, aspecto, alturaCabeca) {
    if (!this.el) { return; }

    var altVis = 2 * dist * Math.tan(meioV);
    var larVis = altVis * aspecto;

    var baixoQuadro = v.alvo - altVis / 2;
    var fracao = (0 - baixoQuadro) / altVis;

    var largura = (alturaCabeca * 0.46) / larVis;

    var alvo = (this.el.closest && this.el.closest('.nv-instrutor')) || this.el;
    var visivel = fracao > -0.02 && fracao < 0.6;
    alvo.style.setProperty('--nv-sombra-baixo', (fracao * 100).toFixed(2) + '%');
    alvo.style.setProperty('--nv-sombra-larg', (largura * 100).toFixed(2) + '%');
    alvo.style.setProperty('--nv-sombra-op', visivel ? '1' : '0');
  };

  Instrutor3D.prototype.camera = function (v) { this.enquadrar(v); };

  Instrutor3D.prototype.osso = function (nomes) {
    var humano = this.vrm && this.vrm.humanoid;
    if (!humano) { return null; }
    var lista = Array.isArray(nomes) ? nomes : [nomes];
    for (var i = 0; i < lista.length; i++) {
      var no = null;
      try { no = humano.getNormalizedBoneNode(lista[i]); } catch (e) { no = null; }
      if (no) { return no; }
    }
    return null;
  };

  function eixoDobra(THREE, dirBraco, frente, cima) {
    var e = new THREE.Vector3().crossVectors(dirBraco, frente);
    if (e.lengthSq() < 1e-6) { e.crossVectors(dirBraco, cima); }
    if (e.lengthSq() < 1e-6) { e.set(0, 1, 0); }
    return e.normalize();
  }

  function posDe(no, v) {
    no.updateWorldMatrix(true, false);
    return no.getWorldPosition(v);
  }

  Instrutor3D.prototype.medirEsqueleto = function () {
    var THREE = this.THREE;
    var self = this;
    var a = new THREE.Vector3();
    var b = new THREE.Vector3();
    var c = new THREE.Vector3();

    this.esqueleto = null;

    var quadril = this.osso('hips');
    var cabeca = this.osso('head');
    var ombroE = this.osso('leftUpperArm');
    var ombroD = this.osso('rightUpperArm');
    if (!quadril || !cabeca || !ombroE || !ombroD) { return; }

    var cima = posDe(cabeca, a).clone().sub(posDe(quadril, b)).normalize();
    var lateral = posDe(ombroE, a).clone().sub(posDe(ombroD, b));
    lateral.addScaledVector(cima, -lateral.dot(cima)).normalize();
    var frente = new THREE.Vector3().crossVectors(lateral, cima).normalize();

    var eixos = { cima: cima, lateral: lateral, frente: frente };
    var bracos = {};

    ['left', 'right'].forEach(function (lado) {
      var noBraco = self.osso(lado + 'UpperArm');
      var noAnte  = self.osso(lado + 'LowerArm');
      var noMao   = self.osso(lado + 'Hand');
      if (!noBraco || !noAnte || !noMao) { return; }

      var pOmbro = posDe(noBraco, a).clone();
      var pCotov = posDe(noAnte, b).clone();
      var pPunho = posDe(noMao, c).clone();

      var aBraco = pCotov.clone().sub(pOmbro);
      var aAnte  = pPunho.clone().sub(pCotov);
      if (aBraco.lengthSq() < 1e-9 || aAnte.lengthSq() < 1e-9) { return; }
      aBraco.normalize();
      aAnte.normalize();

      var pIndex  = self.osso(lado + 'IndexProximal');
      var pLittle = self.osso(lado + 'LittleProximal');
      var pMedio  = self.osso(lado + 'MiddleProximal');
      var dMedio  = self.osso([lado + 'MiddleDistal', lado + 'MiddleIntermediate']);

      var aMao, palma, atravessa;
      if (pIndex && pLittle && pMedio && dMedio) {
        atravessa = posDe(pIndex, a).clone().sub(posDe(pLittle, b)).normalize();
        aMao = posDe(dMedio, a).clone().sub(posDe(pMedio, b)).normalize();
        palma = (lado === 'left')
          ? new THREE.Vector3().crossVectors(aMao, atravessa)
          : new THREE.Vector3().crossVectors(atravessa, aMao);
        palma.normalize();
      } else {
        aMao = aAnte.clone();
        palma = frente.clone().negate();
        atravessa = new THREE.Vector3().crossVectors(palma, aMao).normalize();
        if (lado === 'right') { atravessa.negate(); }
      }

      var eixoCotovelo = eixoDobra(THREE, aBraco, frente, cima);

      var qMao = new THREE.Quaternion();
      noMao.updateWorldMatrix(true, false);
      noMao.getWorldQuaternion(qMao);
      var invMao = qMao.clone().invert();

      var eixoDedo = new THREE.Vector3().crossVectors(aMao, palma).normalize().applyQuaternion(invMao).normalize();
      var eixoEspalha = palma.clone().applyQuaternion(invMao).normalize();

      var eixoPolegar = eixoDedo.clone();
      var pPolegar = self.osso([lado + 'ThumbMetacarpal', lado + 'ThumbProximal']);
      var dPolegar = self.osso(lado + 'ThumbDistal');
      if (pPolegar && dPolegar) {
        var dirPol = posDe(dPolegar, a).clone().sub(posDe(pPolegar, b));
        if (dirPol.lengthSq() > 1e-9) {
          dirPol.normalize();
          eixoPolegar = new THREE.Vector3().crossVectors(atravessa, dirPol)
            .normalize().applyQuaternion(invMao).normalize();
        }
      }

      var ossosDedo = {};
      var achados = 0;
      DEDOS.forEach(function (dedo) {
        ossosDedo[dedo] = JUNTAS_DEDO[dedo].map(function (par) {
          var n = self.osso([lado + par[0], lado + par[1]]);
          if (n) { achados++; }
          return n;
        });
      });

      var qrB = new THREE.Quaternion(); noBraco.getWorldQuaternion(qrB);
      var qrA = new THREE.Quaternion(); noAnte.getWorldQuaternion(qrA);
      var qrM = new THREE.Quaternion(); noMao.getWorldQuaternion(qrM);

      bracos[lado] = {
        noBraco: noBraco, noAnte: noAnte, noMao: noMao,
        aBraco: aBraco, aAnte: aAnte, aMao: aMao,
        palma: palma, atravessa: atravessa, eixoCotovelo: eixoCotovelo,
        qRestBraco: qrB, qRestAnte: qrA, qRestMao: qrM,
        eixoDedo: eixoDedo, eixoPolegar: eixoPolegar, eixoEspalha: eixoEspalha,
        ossosDedo: ossosDedo, dedosAchados: achados
      };
    });

    this.esqueleto = { eixos: eixos, bracos: bracos };
  };

  Instrutor3D.prototype._eixosAgora = function () {
    var base = this.esqueleto.eixos;
    if (!this._yaw) { return base; }
    var THREE = this.THREE;
    var q = this._qYaw || (this._qYaw = new THREE.Quaternion());
    q.setFromAxisAngle(base.cima, this._yaw);
    return {
      cima: base.cima,
      lateral: base.lateral.clone().applyQuaternion(q).normalize(),
      frente: base.frente.clone().applyQuaternion(q).normalize()
    };
  };

  Instrutor3D.prototype.vetorGesto = function (v, lado) {
    var e = this._eixosF || this.esqueleto.eixos;
    var fora = (lado === 'right') ? -v[0] : v[0];
    return new this.THREE.Vector3()
      .addScaledVector(e.lateral, fora)
      .addScaledVector(e.cima, v[1])
      .addScaledVector(e.frente, v[2])
      .normalize();
  };

  function montarBase(m, aponta, torce, THREE) {
    var x = aponta.clone().normalize();
    var z = new THREE.Vector3().crossVectors(x, torce);
    if (z.lengthSq() < 1e-8) {
      z.set(x.z, x.x, x.y).cross(x);
    }
    z.normalize();
    var y = new THREE.Vector3().crossVectors(z, x).normalize();
    return m.makeBasis(x, y, z);
  }

  Instrutor3D.prototype.orientar = function (qOut, aponta0, torce0, aponta1, torce1, qRepouso) {
    var THREE = this.THREE;
    var m0 = this._m0 || (this._m0 = new THREE.Matrix4());
    var m1 = this._m1 || (this._m1 = new THREE.Matrix4());
    var qd = this._qd || (this._qd = new THREE.Quaternion());

    montarBase(m0, aponta0, torce0, THREE);
    montarBase(m1, aponta1, torce1, THREE);
    m0.transpose();
    m1.multiply(m0);
    qd.setFromRotationMatrix(m1);
    return qOut.copy(qd).multiply(qRepouso);
  };

  function clonar(o) { return JSON.parse(JSON.stringify(o)); }

  function mesclarBraco(base, extra) {
    var r = clonar(base);
    if (!extra) { return r; }
    Object.keys(extra).forEach(function (k) {
      if (k === 'pulso') { r.pulso = Object.assign({}, r.pulso, extra.pulso); }
      else { r[k] = extra[k]; }
    });
    return r;
  }

  function amplitudeBraco(spec, k) {
    if (k === 1) { return spec; }
    var r = BRACO_REPOUSO;
    function mix(a, b) { return a + (b - a) * k; }

    var braco = spec.braco || r.braco;
    var pulso = Object.assign({}, r.pulso, spec.pulso || {});
    var saida = {
      braco: [mix(r.braco[0], braco[0]), mix(r.braco[1], braco[1]), mix(r.braco[2], braco[2])],
      cotovelo: mix(r.cotovelo, spec.cotovelo != null ? spec.cotovelo : r.cotovelo),
      giroBraco: mix(r.giroBraco, spec.giroBraco != null ? spec.giroBraco : r.giroBraco),
      pulso: {
        flexao: mix(r.pulso.flexao, pulso.flexao || 0),
        desvio: mix(r.pulso.desvio, pulso.desvio || 0),
        giro:   mix(r.pulso.giro, pulso.giro != null ? pulso.giro : r.pulso.giro)
      },
      mao: k < 0.3 ? r.mao : (spec.mao || r.mao)
    };
    if (spec.osc) {
      saida.osc = {};
      Object.keys(spec.osc).forEach(function (nome) {
        var o = spec.osc[nome];
        saida.osc[nome] = { a: (o.a || 0) * k, hz: o.hz || 1 };
      });
    }
    return saida;
  }

  Instrutor3D.prototype._montarBracos = function (g, ladoAtivo, amp) {
    var repouso = clonar(BRACO_REPOUSO);
    var saida = { left: repouso, right: clonar(BRACO_REPOUSO) };
    if (!g) { return saida; }

    var specAmbos = g.ambos || null;
    var specAtivo = g.ativo || g.direito || g.esquerdo || null;
    var specApoio = g.apoio || null;
    if (!specAtivo && (g.esquerdo && g.direito)) { specApoio = g.direito; specAtivo = g.esquerdo; }

    if (specAmbos || ladoAtivo === 'ambos') {
      var base = specAmbos || specAtivo;
      if (!base) { return saida; }
      saida.left = amplitudeBraco(mesclarBraco(BRACO_REPOUSO, base), amp);
      saida.right = amplitudeBraco(mesclarBraco(BRACO_REPOUSO, base), amp);
      return saida;
    }

    if (!specAtivo) { return saida; }
    var outro = ladoAtivo === 'left' ? 'right' : 'left';
    saida[ladoAtivo] = amplitudeBraco(mesclarBraco(BRACO_REPOUSO, specAtivo), amp);
    if (specApoio) { saida[outro] = amplitudeBraco(mesclarBraco(BRACO_REPOUSO, specApoio), amp); }
    return saida;
  };

  Instrutor3D.prototype.bracosDoGesto = function (g) {
    g = g || {};
    return {
      left:  mesclarBraco(BRACO_REPOUSO, g.esquerdo || g.ambos || null),
      right: mesclarBraco(BRACO_REPOUSO, g.direito  || g.ambos || null)
    };
  };

  Instrutor3D.prototype.aplicarPoseBase = function () {
    this.medirEsqueleto();
    this.poseAlvo = clonar(POSE_BASE);
    this.poseAtual = clonar(POSE_BASE);
    this.bracosAlvo = this._montarBracos(null, null, 1);
    this.cabecaOsc = null;

    var self = this;
    Object.keys(this.poseAtual).forEach(function (osso) {
      var no = self.osso(osso);
      if (no) { no.rotation.set(0, 0, 0); }
    });

    this._aplicarGesto();
  };

  Instrutor3D.prototype.gesto = function (nome, seg, opc) {
    clearTimeout(this.timerGesto);
    this.timerGesto = null;

    if (typeof opc === 'boolean') { opc = { braco: opc ? 'espelhar' : 'auto' }; }
    opc = opc || {};

    var g = GESTOS[nome] || null;
    this._gestoAtual = { nome: nome || '', def: g, opc: opc };
    this._aplicarGesto();

    if (g) {
      var self = this;
      this.timerGesto = setTimeout(function () { self.gesto(null, 0, opc); }, (seg || 3) * 1000);
    }
  };

  Instrutor3D.prototype._aplicarGesto = function () {
    var atual = this._gestoAtual || { def: null, opc: {} };
    var g = atual.def;
    var opc = atual.opc || {};
    var o = this.orient;

    var ladoAtivo = this._resolverBraco(g, opc.braco || 'auto');
    var sinal = this._sinalGesto(ladoAtivo);

    var amp = opc.intensidade != null ? Number(opc.intensidade) : 1;
    if (!isFinite(amp)) { amp = 1; }
    amp = limitar(amp * (o.forcaGesto == null ? 1 : o.forcaGesto), 0, 1.6);

    this.poseAlvo = clonar(POSE_BASE);
    this.bracosAlvo = this._montarBracos(g, ladoAtivo, amp);
    this.cabecaOsc = (g && g.cabecaOsc) || null;
    this._modoCabeca = opc.cabeca || 'auto';
    this._ladoAtivo = ladoAtivo;

    var modoTronco = opc.tronco || 'auto';
    var giroBase = this._giroBaseRad(opc.giro);
    var giroGesto = (g && g.tronco && g.tronco.girar || 0) * sinal;

    if (modoTronco === 'nenhum' || modoTronco === 'frente') {
      giroBase = 0; giroGesto = 0;
    } else if (modoTronco === 'publico') {
      giroBase = 0; giroGesto = 0;
    } else if (modoTronco === 'conteudo') {
      var mag = GIROS[opc.giro] != null ? GIROS[opc.giro] : (GIROS[o.giroCorpo] || GIROS.moderado);
      if (!mag) { mag = GIROS.moderado; }
      giroBase = mag * (this._sinalTela || 0);
    }

    var yaw = limitar((giroBase + giroGesto) * (o.forcaTronco == null ? 1 : o.forcaTronco), -1.25, 1.25);

    var fatQuadril = o.giroQuadril ? 0.45 : 0;
    var resto = 1 - fatQuadril;
    this.poseAlvo.hips.y  = yaw * fatQuadril;
    this.poseAlvo.spine.y = yaw * resto * 0.42;
    this.poseAlvo.chest.y = yaw * resto * 0.58;
    this._yawAlvo = yaw;

    var ft = o.forcaTronco == null ? 1 : o.forcaTronco;
    if (g && g.tronco && g.tronco.inclinar) { this.poseAlvo.chest.x += g.tronco.inclinar * ft; }
    if (g && g.coluna && g.coluna.inclinar) { this.poseAlvo.spine.x += g.coluna.inclinar * ft; }
    if (g && g.quadril && g.quadril.inclinar) { this.poseAlvo.hips.x += g.quadril.inclinar * ft; }

    var fc = o.forcaCabeca == null ? 1 : o.forcaCabeca;
    var cab = (g && g.cabeca) || null;
    if (cab) {
      this.poseAlvo.head.x += (cab.inclinar || 0) * fc;
      this.poseAlvo.head.y += (cab.girar || 0) * sinal * fc;
      this.poseAlvo.head.z += (cab.tombar || 0) * sinal * fc;
    }
    var pes = (g && g.pescoco) || null;
    if (pes) {
      this.poseAlvo.neck.x += (pes.inclinar || 0) * fc;
      this.poseAlvo.neck.y += (pes.girar || 0) * sinal * fc;
      this.poseAlvo.neck.z += (pes.tombar || 0) * sinal * fc;
    }

    if (g && g.ombros) {
      this.poseAlvo.leftShoulder = Object.assign({}, this.poseAlvo.leftShoulder, g.ombros.esquerdo || {});
      this.poseAlvo.rightShoulder = Object.assign({}, this.poseAlvo.rightShoulder, g.ombros.direito || {});
    }
  };

  Instrutor3D.prototype.gestoManual = function (spec) {
    clearTimeout(this.timerGesto);
    this.timerGesto = null;
    this._gestoAtual = null;
    var g = spec || {};
    this.poseAlvo = clonar(POSE_BASE);
    this.bracosAlvo = this.bracosDoGesto(g);
    this.cabecaOsc = g.cabecaOsc || null;
    this._modoCabeca = 'fixo';
    this._yawAlvo = 0;
    ['hips', 'spine', 'chest', 'neck'].forEach(function (osso) {
      if (g[osso]) { this.poseAlvo[osso] = Object.assign({}, this.poseAlvo[osso], g[osso]); }
    }, this);
    if (g.cabeca) { this.poseAlvo.head = Object.assign({}, this.poseAlvo.head, g.cabeca); }
  };

  Instrutor3D.prototype.animarBracos = function (t, k) {
    if (!this.esqueleto || !this.bracosAlvo) { return; }
    var THREE = this.THREE;
    var self = this;

    this._eixosF = this._eixosAgora();

    var q  = this._qA || (this._qA = new THREE.Quaternion());
    var qP = this._qB || (this._qB = new THREE.Quaternion());
    var qL = this._qC || (this._qC = new THREE.Quaternion());
    var vA = this._vA || (this._vA = new THREE.Vector3());
    var vB = this._vB || (this._vB = new THREE.Vector3());
    var vC = this._vC || (this._vC = new THREE.Vector3());
    var qDedo = this._qE || (this._qE = new THREE.Quaternion());
    var vDedo = this._vD || (this._vD = new THREE.Vector3());

    function onda(o) {
      return o ? Math.sin(t * (o.hz || 1) * Math.PI * 2) * (o.a || 0) : 0;
    }

    ['left', 'right'].forEach(function (lado) {
      var br = self.esqueleto.bracos[lado];
      var alvo = self.bracosAlvo[lado === 'left' ? 'left' : 'right'];
      if (!br || !alvo) { return; }

      var osc = alvo.osc || {};
      var cotovelo  = Math.max(0, Math.min(2.6, (alvo.cotovelo || 0) + onda(osc.cotovelo)));
      var giroBraco = (alvo.giroBraco || 0) + onda(osc.giroBraco);
      var pulso = alvo.pulso || {};
      var flexao = (pulso.flexao || 0) + onda(osc.flexao);
      var desvio = (pulso.desvio || 0) + onda(osc.desvio);
      var giro   = (pulso.giro || 0) + onda(osc.giro);

      var sLado = (lado === 'right') ? -1 : 1;
      var eixos = self._eixosF;

      var dirBraco = self.vetorGesto(alvo.braco || BRACO_REPOUSO.braco, lado);

      vA.copy(eixoDobra(THREE, dirBraco, eixos.frente, eixos.cima));
      qL.setFromAxisAngle(dirBraco, sLado * giroBraco);
      vA.applyQuaternion(qL).normalize();

      qL.setFromAxisAngle(vA, cotovelo);
      vB.copy(dirBraco).applyQuaternion(qL).normalize();

      var palmaAlvo = vA.clone().multiplyScalar(sLado);
      qL.setFromAxisAngle(vB, sLado * giro);
      palmaAlvo.applyQuaternion(qL).normalize();

      vC.copy(vB);
      qL.setFromAxisAngle(palmaAlvo, sLado * desvio);
      vC.applyQuaternion(qL);
      var eixoFlex = new THREE.Vector3().crossVectors(vC, palmaAlvo).normalize();
      qL.setFromAxisAngle(eixoFlex, flexao);
      vC.applyQuaternion(qL).normalize();

      function porOsso(no, aponta0, torce0, aponta1, torce1, qRest) {
        if (!no || !no.parent) { return; }
        self.orientar(q, aponta0, torce0, aponta1, torce1, qRest);
        no.parent.getWorldQuaternion(qP);
        qP.invert().multiply(q);
        no.quaternion.slerp(qP, k);
      }

      porOsso(br.noBraco, br.aBraco, br.eixoCotovelo, dirBraco, vA, br.qRestBraco);
      porOsso(br.noAnte,  br.aAnte,  br.eixoCotovelo, vB, vA, br.qRestAnte);
      porOsso(br.noMao,   br.aMao,   br.palma,        vC, palmaAlvo, br.qRestMao);

      var forma = MAOS[alvo.mao] || MAOS.relaxada;
      var juntas = forma.juntas || [1, 1.2, 0.8];
      var respirar = Math.sin(t * 0.5 + (lado === 'left' ? 0 : 1.7)) * 0.03;
      var extra = onda(osc.dedos);

      DEDOS.forEach(function (dedo, iDedo) {
        var base = forma[dedo];
        if (base == null) { return; }
        var eixo = (dedo === 'Thumb') ? br.eixoPolegar : br.eixoDedo;
        var espalha = (forma.abrir || 0) * (iDedo - 2) * 0.5;

        br.ossosDedo[dedo].forEach(function (no, j) {
          if (!no) { return; }
          var ang = (base + respirar + extra) * (juntas[j] || 1);
          if (dedo === 'Thumb') { ang *= 0.75; }
          qDedo.setFromAxisAngle(eixo, ang);
          if (j === 0 && espalha) {
            vDedo.copy(br.eixoEspalha);
            qL.setFromAxisAngle(vDedo, espalha);
            qDedo.premultiply(qL);
          }
          no.quaternion.slerp(qDedo, k);
        });
      });
    });
  };

  var OSSOS_ESSENCIAIS = [
    ['hips', 'quadril — base de todos os giros do corpo'],
    ['spine', 'coluna — respiração e inclinação'],
    ['neck', 'pescoço'],
    ['head', 'cabeça — define também o enquadramento da câmera'],
    ['leftUpperArm', 'braço esquerdo'],
    ['rightUpperArm', 'braço direito'],
    ['leftLowerArm', 'antebraço esquerdo'],
    ['rightLowerArm', 'antebraço direito']
  ];
  var OSSOS_DESEJAVEIS = [
    ['chest', 'peito — torções do tronco'],
    ['leftShoulder', 'ombro esquerdo'],
    ['rightShoulder', 'ombro direito'],
    ['leftHand', 'mão esquerda'],
    ['rightHand', 'mão direita'],
    ['leftEye', 'olho esquerdo'],
    ['rightEye', 'olho direito']
  ];

  var VISEMAS_EXIGIDOS = [
    ['aa', 'boca aberta (a)'],
    ['ih', 'boca em i'],
    ['ou', 'boca em u'],
    ['ee', 'boca em e'],
    ['oh', 'boca em o']
  ];
  var EXPRESSOES_DESEJAVEIS = [
    ['happy', 'Alegre'],
    ['sad', 'Séria / pesarosa'],
    ['angry', 'Firme'],
    ['relaxed', 'Acolhedora'],
    ['surprised', 'Preocupada']
  ];

  function temExpressao(gerente, nome) {
    if (!gerente) { return false; }
    try {
      if (typeof gerente.getExpression === 'function' && gerente.getExpression(nome)) { return true; }
    } catch (e) {}
    try {
      if (gerente.expressionMap && gerente.expressionMap[nome]) { return true; }
    } catch (e) {}
    try {
      var lista = gerente.expressions || [];
      for (var i = 0; i < lista.length; i++) {
        var x = lista[i];
        if (x && (x.expressionName === nome || x.name === nome)) { return true; }
      }
    } catch (e) {}
    return false;
  }

  Instrutor3D.prototype.diagnosticar = function () {
    var r = {
      ok: false, erros: [], avisos: [],
      ossos: { presentes: [], faltando: [], opcionaisFaltando: [] },
      expressoes: { presentes: [], faltando: [], emocoesFaltando: [] },
      lookAt: false, meta: {}, malha: {}
    };

    if (!this.vrm) {
      r.erros.push({ chave: 'vrm', texto: 'O arquivo não é um VRM válido. Exporte pelo VRM Add-on for Blender (ou pelo VRoid), não como GLB comum.' });
      return r;
    }

    var self = this;
    var humano = this.vrm.humanoid;

    if (!humano) {
      r.erros.push({ chave: 'humanoid', texto: 'O modelo não tem o esqueleto humanoide do VRM mapeado. Nenhum gesto vai funcionar.' });
    } else {
      OSSOS_ESSENCIAIS.forEach(function (o) {
        var no = null;
        try { no = humano.getNormalizedBoneNode(o[0]); } catch (e) {}
        if (no) { r.ossos.presentes.push(o[0]); }
        else { r.ossos.faltando.push({ nome: o[0], desc: o[1] }); }
      });
      OSSOS_DESEJAVEIS.forEach(function (o) {
        var no = null;
        try { no = humano.getNormalizedBoneNode(o[0]); } catch (e) {}
        if (no) { r.ossos.presentes.push(o[0]); }
        else { r.ossos.opcionaisFaltando.push({ nome: o[0], desc: o[1] }); }
      });
    }

    var gerente = this.vrm.expressionManager;
    if (!gerente) {
      r.erros.push({ chave: 'expressoes', texto: 'O modelo não tem expressões VRM. A boca não vai se mexer durante a narração.' });
    } else {
      VISEMAS_EXIGIDOS.forEach(function (e) {
        if (temExpressao(gerente, e[0])) { r.expressoes.presentes.push(e[0]); }
        else { r.expressoes.faltando.push({ nome: e[0], desc: e[1] }); }
      });
      EXPRESSOES_DESEJAVEIS.forEach(function (e) {
        if (temExpressao(gerente, e[0])) { r.expressoes.presentes.push(e[0]); }
        else { r.expressoes.emocoesFaltando.push({ nome: e[0], desc: e[1] }); }
      });
      if (!temExpressao(gerente, 'blink')) {
        r.avisos.push({ chave: 'blink', texto: 'Sem a expressão "blink": o instrutor não pisca, o que deixa o olhar fixo.' });
      }
    }

    var dedosOk = 0, dedosTotal = 0;
    ['left', 'right'].forEach(function (lado) {
      DEDOS.forEach(function (dedo) {
        JUNTAS_DEDO[dedo].forEach(function (par) {
          dedosTotal++;
          if (self.osso([lado + par[0], lado + par[1]])) { dedosOk++; }
        });
      });
    });
    r.dedos = {
      presentes: dedosOk, total: dedosTotal,
      eixoMedido: !!(this.esqueleto && this.esqueleto.bracos &&
                     (this.esqueleto.bracos.left || this.esqueleto.bracos.right))
    };
    if (dedosOk > 0 && !r.dedos.eixoMedido) {
      r.avisos.push({
        chave: 'dedos-eixo',
        texto: 'Os ossos de dedo existem, mas não foi possível medir o eixo de flexão (falta indicador, mínimo ou médio). As mãos vão ficar retas.'
      });
    }
    if (dedosOk === 0) {
      r.avisos.push({
        chave: 'dedos',
        texto: 'O modelo não tem ossos de dedo. As mãos ficam sempre retas — no Blender, mapeie os ossos de dedo no VRM Add-on para o instrutor apontar, fechar a mão e aplaudir.'
      });
    } else if (dedosOk < dedosTotal * 0.8) {
      r.avisos.push({
        chave: 'dedos-parciais',
        texto: 'Só ' + dedosOk + ' dos ' + dedosTotal + ' ossos de dedo estão mapeados: alguns formatos de mão vão sair incompletos.'
      });
    }

    var temTorcao = !!(this.osso('chest') && this.osso('spine'));
    r.torcao = temTorcao;
    if (!temTorcao) {
      r.avisos.push({
        chave: 'torcao',
        texto: 'Sem os ossos de coluna e peito o instrutor não consegue virar o corpo para o conteúdo: ele vai apenas apontar.'
      });
    }

    r.lookAt = !!this.vrm.lookAt;
    if (!r.lookAt) {
      r.avisos.push({ chave: 'lookAt', texto: 'Sem o Look At configurado: as direções de olhar só movem a cabeça, não os olhos.' });
    }

    try {
      var meta = this.vrm.meta || {};
      r.meta = {
        nome: meta.name || meta.title || '',
        autor: (meta.authors && meta.authors.join(', ')) || meta.author || '',
        versao: meta.metaVersion || meta.specVersion || (this.vrm.meta ? '0.x' : '')
      };
    } catch (e) {}

    var triangulos = 0, malhas = 0, materiais = 0, texturas = {};
    try {
      this.vrm.scene.traverse(function (o) {
        if (!o.isMesh && !o.isSkinnedMesh) { return; }
        malhas++;
        var g = o.geometry;
        if (g) {
          triangulos += g.index ? g.index.count / 3
            : (g.attributes && g.attributes.position ? g.attributes.position.count / 3 : 0);
        }
        var mats = Array.isArray(o.material) ? o.material : [o.material];
        mats.forEach(function (m) {
          if (!m) { return; }
          materiais++;
          if (m.map && m.map.image) {
            texturas[m.map.uuid] = Math.max(m.map.image.width || 0, m.map.image.height || 0);
          }
        });
      });
    } catch (e) {}
    r.malha = {
      triangulos: Math.round(triangulos), malhas: malhas, materiais: materiais,
      texturas: Object.keys(texturas).length,
      maiorTextura: Object.keys(texturas).reduce(function (m, k) { return Math.max(m, texturas[k]); }, 0)
    };
    if (r.malha.triangulos > 180000) {
      r.avisos.push({
        chave: 'peso',
        texto: 'Modelo pesado (' + r.malha.triangulos.toLocaleString('pt-BR') +
               ' triângulos). Pode travar em máquinas mais simples — considere reduzir no Blender.'
      });
    }
    if (r.malha.maiorTextura > 4096) {
      r.avisos.push({ chave: 'textura', texto: 'Há textura de ' + r.malha.maiorTextura + ' px. 2048 px costuma bastar e baixa muito mais rápido.' });
    }

    if (r.ossos.faltando.length) {
      r.erros.push({
        chave: 'ossos',
        texto: 'Faltam ossos humanoides obrigatórios: ' +
               r.ossos.faltando.map(function (o) { return o.nome; }).join(', ') + '.'
      });
    }
    if (r.expressoes.faltando.length) {
      r.erros.push({
        chave: 'visemas',
        texto: 'Faltam expressões de boca: ' +
               r.expressoes.faltando.map(function (e) { return e.nome; }).join(', ') +
               '. Sem elas não há sincronia labial.'
      });
    }
    if (r.ossos.opcionaisFaltando.length) {
      r.avisos.push({
        chave: 'ossos-opc',
        texto: 'Ossos ausentes que limitam alguns gestos: ' +
               r.ossos.opcionaisFaltando.map(function (o) { return o.nome; }).join(', ') + '.'
      });
    }
    if (r.expressoes.emocoesFaltando.length) {
      r.avisos.push({
        chave: 'emocoes',
        texto: 'Expressões de emoção ausentes: ' +
               r.expressoes.emocoesFaltando.map(function (e) { return e.nome; }).join(', ') +
               '. As falas que usarem essas expressões ficam neutras.'
      });
    }

    r.ok = r.erros.length === 0;
    return r;
  };

  Instrutor3D.prototype.miniatura = function (vista) {
    if (!this.ativo || !this.renderer) { return ''; }
    var anterior = this.cfg.camera;
    if (vista) { this.enquadrar(vista); }
    try {
      this.renderer.render(this.cena, this.cam);
      var url = this.renderer.domElement.toDataURL('image/png');
      if (vista && anterior) { this.enquadrar(anterior); }
      return url;
    } catch (e) {
      if (vista && anterior) { this.enquadrar(anterior); }
      return '';
    }
  };

  Instrutor3D.prototype.humor = function (nome) {
    if (!this.ativo || !this.vrm.expressionManager) { return; }
    var alvo = ({
      neutral: '', happy: 'happy', sad: 'sad', angry: 'angry',
      fear: 'surprised', love: 'relaxed', relaxed: 'relaxed', surprised: 'surprised'
    })[nome];
    var self = this;
    EXPRESSOES.forEach(function (e) {
      try { self.vrm.expressionManager.setValue(e, e === alvo ? 0.75 : 0); } catch (err) {}
    });
    this.expressaoAtual = alvo || '';
  };

  Instrutor3D.prototype.olhar = function (direcao, forca) {
    this.olharAlvo = direcao || 'camera';
    this.olharForca = (forca == null || !isFinite(Number(forca))) ? 1 : limitar(Number(forca), 0, 1.5);
  };

  Instrutor3D.prototype._angulosOlhar = function (t) {
    var s = this._sinalTela || 0;
    var alt = this.orient.alturaConteudo || 0;
    var f = this.olharForca == null ? 1 : this.olharForca;
    var r;
    switch (this.olharAlvo) {
      case 'conteudo':
      case 'slide':
        r = s === 0 ? { y: 0, x: 0.12, publico: false }
                    : { y: s * 0.52, x: -alt * 0.28, publico: false };
        break;
      case 'esquerda': r = { y: -0.52, x: 0, publico: false }; break;
      case 'direita':  r = { y: 0.52, x: 0, publico: false }; break;
      case 'cima':     r = { y: 0, x: -0.24, publico: false }; break;
      case 'baixo':    r = { y: 0, x: 0.24, publico: false }; break;
      case 'ombro':    r = { y: (s || 1) * -0.85, x: -0.05, publico: false }; break;
      case 'livre':    r = { y: Math.sin((t || 0) * 0.31) * 0.2, x: Math.sin((t || 0) * 0.23) * 0.07, publico: true }; break;
      default:         r = { y: 0, x: 0, publico: true };
    }
    return { y: r.y * f, x: r.x * f, publico: r.publico };
  };

  Instrutor3D.prototype.iniciarLipsync = function (linha, tempoMs) {
    if (!linha || !linha.t || !linha.t.length) {
      this.lip = { simples: true, tempo: tempoMs, i: 0 };
      return;
    }
    this.lip = { linha: linha, tempo: tempoMs, i: 0 };
  };

  Instrutor3D.prototype.pararLipsync = function () {
    this.lip = null;
  };

  Instrutor3D.prototype._atualizarBoca = function (dt) {
    if (!this.vrm || !this.vrm.expressionManager) { return; }
    var alvos = { aa: 0, ih: 0, ou: 0, ee: 0, oh: 0 };

    if (this.lip) {
      var ms = this.lip.tempo ? this.lip.tempo() : 0;
      if (this.lip.linha) {
        var L = this.lip.linha;
        var i = this.lip.i;
        while (i < L.t.length - 1 && L.t[i + 1] <= ms) { i++; }
        while (i > 0 && L.t[i] > ms) { i--; }
        this.lip.i = i;
        var nome = VISEMAS[L.v[i]] || 'aa';
        alvos[nome] = Math.max(0.12, Math.min(1, L.w[i]));
      } else {
        this.lip.fase = (this.lip.fase || 0) + dt * 7.5;
        var k = Math.floor(this.lip.fase) % VISEMAS.length;
        alvos[VISEMAS[k]] = 0.45 + 0.25 * Math.abs(Math.sin(this.lip.fase * 1.7));
      }
    }

    var self = this;
    VISEMAS.forEach(function (v) {
      var atual = self.visemaSuave[v];
      var alvo = alvos[v];
      var vel = alvo > atual ? 18 : 12;
      self.visemaSuave[v] = atual + (alvo - atual) * Math.min(1, vel * dt);
      try { self.vrm.expressionManager.setValue(v, self.visemaSuave[v]); } catch (e) {}
    });
  };

  Instrutor3D.prototype.animar = function () {
    var self = this;
    var tempoPiscar = 2 + Math.random() * 3;
    var acumulado = 0;

    function quadro() {
      if (!self.ativo) { return; }
      self.raf = global.requestAnimationFrame(quadro);
      var dt = Math.min(0.05, self.relogio.getDelta());
      acumulado += dt;

      var suave = limitar(self.orient.suavidade == null ? 1 : self.orient.suavidade, 0.2, 3);

      var resp = Math.sin(acumulado * 1.15) * 0.012;
      var balanco = Math.sin(acumulado * 0.42) * 0.02;

      var oscCab = self.cabecaOsc
        ? Math.sin(acumulado * (self.cabecaOsc.hz || 1.4) * Math.PI * 2) * (self.cabecaOsc.a || 0)
        : 0;
      var eixoCab = self.cabecaOsc ? (self.cabecaOsc.eixo || 'x') : 'x';

      var ang = self._angulosOlhar(acumulado);
      var modo = self._modoCabeca || 'auto';
      var kCab, thetaY, thetaX;

      if (modo === 'fixo') {
        kCab = 0; thetaY = 0; thetaX = 0;
      } else if (modo === 'acompanhar') {
        thetaY = (self._sinalTela || 0) * 0.52;
        thetaX = -(self.orient.alturaConteudo || 0) * 0.28;
        kCab = Math.max(0.5, self.orient.olharCabeca || 0);
      } else if (modo === 'publico') {
        thetaY = 0; thetaX = 0;
        kCab = Math.max(0.6, self.orient.rostoPublico || 0);
      } else if (modo === 'livre') {
        thetaY = Math.sin(acumulado * 0.33) * 0.18;
        thetaX = Math.sin(acumulado * 0.27) * 0.06;
        kCab = 0.8;
      } else {
        thetaY = ang.y; thetaX = ang.x;
        kCab = ang.publico ? (self.orient.rostoPublico || 0) : (self.orient.olharCabeca || 0);
      }

      if (!self._olhar) { self._olhar = { y: thetaY, x: thetaX, k: kCab }; }
      var kOlhar = Math.min(1, 3.4 * suave * dt);
      self._olhar.y += (thetaY - self._olhar.y) * kOlhar;
      self._olhar.x += (thetaX - self._olhar.x) * kOlhar;
      self._olhar.k += (kCab - self._olhar.k) * kOlhar;
      thetaY = self._olhar.y;
      thetaX = self._olhar.x;
      kCab = self._olhar.k;

      var yawAtual = (self.poseAtual.hips ? self.poseAtual.hips.y : 0)
                   + (self.poseAtual.spine ? self.poseAtual.spine.y : 0)
                   + (self.poseAtual.chest ? self.poseAtual.chest.y : 0);
      self._yaw = yawAtual;

      var exCabY = limitar((thetaY - yawAtual) * kCab, -0.75, 0.75);
      var exCabX = limitar(thetaX * kCab, -0.4, 0.4);

      Object.keys(self.poseAlvo).forEach(function (osso) {
        var no = self.osso(osso);
        if (!no) { return; }
        var alvo = self.poseAlvo[osso];
        var atual = self.poseAtual[osso] || { x: 0, y: 0, z: 0 };
        ['x', 'y', 'z'].forEach(function (eixo) {
          atual[eixo] += ((alvo[eixo] || 0) - atual[eixo]) * Math.min(1, 6 * suave * dt);
        });
        self.poseAtual[osso] = atual;

        var ex = 0, ey = 0, ez = 0;
        if (osso === 'spine') { ex = resp; ey = balanco * 0.5; }
        if (osso === 'head') {
          ey += exCabY;
          ex += exCabX;
          if (oscCab) {
            if (eixoCab === 'x') { ex += oscCab; }
            else if (eixoCab === 'y') { ey += oscCab; }
            else { ez += oscCab; }
          }
        }
        if (osso === 'neck') {
          ey += exCabY * 0.35;
          ex += exCabX * 0.35;
          if (oscCab) {
            if (eixoCab === 'x') { ex += oscCab * 0.35; }
            else if (eixoCab === 'y') { ey += oscCab * 0.35; }
            else { ez += oscCab * 0.35; }
          }
        }
        no.rotation.set(atual.x + ex, atual.y + ey, atual.z + ez);
      });

      self.animarBracos(acumulado, Math.min(1, 9 * suave * dt));

      tempoPiscar -= dt;
      if (tempoPiscar <= 0) {
        try { self.vrm.expressionManager.setValue('blink', 1); } catch (e) {}
        setTimeout(function () {
          try { self.vrm.expressionManager.setValue('blink', 0); } catch (e) {}
        }, 110);
        tempoPiscar = 2.4 + Math.random() * 3.4;
      }

      if (self.vrm.lookAt) {
        var V = self.THREE.Vector3;
        var dist = Math.max(1.2, Math.abs(self.cam.position.z));
        var alvoOlhar = new V(
          Math.sin(ang.y) * dist,
          (self._alturaCabeca || 1.4) - Math.sin(ang.x) * dist,
          Math.cos(ang.y) * dist
        );
        self.vrm.lookAt.target = null;
        try { self.vrm.lookAt.lookAt(alvoOlhar); } catch (e) {}
      }

      self._atualizarBoca(dt);
      self.vrm.update(dt);
      self.renderer.render(self.cena, self.cam);
    }
    quadro();
  };

  Instrutor3D.prototype.parar = function () {
    this.pararLipsync();
  };

  Instrutor3D.prototype.destruir = function () {
    this.ativo = false;
    if (this.raf) { global.cancelAnimationFrame(this.raf); }
    if (this.renderer) {
      this.renderer.dispose();
      if (this.renderer.domElement && this.renderer.domElement.parentNode) {
        this.renderer.domElement.parentNode.removeChild(this.renderer.domElement);
      }
    }
  };

  Instrutor3D.prototype.hospedeiro = function () {
    return (this.el.closest && this.el.closest('.nv-instrutor')) || this.el.parentElement;
  };

  Instrutor3D.prototype.mostrar = function () {
    var host = this.hospedeiro();
    if (host) { host.classList.remove('is-oculto'); }
  };
  Instrutor3D.prototype.ocultar = function () {
    var host = this.hospedeiro();
    if (host) { host.classList.add('is-oculto'); }
    this.pararLipsync();
    this.fecharBalao();
  };

  Instrutor3D.prototype.posicionar = function (pos) {
    var host = this.hospedeiro();
    if (host) { host.dataset.pos = pos || 'direita'; }
    this.orient.posicao = pos || 'direita';
    this._resolverOrientacao();
    this._aplicarGesto();
    this.redimensionar();
  };

  Instrutor3D.prototype.escalar = function (fator) {
    var host = this.hospedeiro();
    if (host) { host.style.setProperty('--tr-instr-escala', fator || 1); }
  };

  Instrutor3D.prototype.vincularBalao = function (elBalao, elTexto) {
    this.elBalao = elBalao;
    this.elBalaoTexto = elTexto;
  };
  Instrutor3D.prototype.balao = function (texto) {
    if (!this.elBalao) { return; }
    if (!texto) { return this.fecharBalao(); }
    this.elBalaoTexto.textContent = texto;
    this.elBalao.classList.add('is-ativo');
  };
  Instrutor3D.prototype.fecharBalao = function () {
    if (this.elBalao) { this.elBalao.classList.remove('is-ativo'); }
  };

  global.Instrutor3D = Instrutor3D;
})(window);
