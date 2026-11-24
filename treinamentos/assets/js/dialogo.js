(function (global) {
  'use strict';

  var PAUSA_ENTRE_FALAS = 320;

  function MotorDialogos(deps) {
    this.instrutor = deps.instrutor;
    this.fala = deps.fala;
    this.palco = deps.palco;
    this.ui = deps.ui;
    this.aoIniciarSequencia = deps.aoIniciarSequencia || function () {};
    this.aoTerminarSequencia = deps.aoTerminarSequencia || function () {};
    this.aoTrocarDialogo = deps.aoTrocarDialogo || function () {};

    this.lista = [];
    this.indice = -1;
    this.rodando = false;
    this.pausado = false;
    this.controle = null;
    this.timerPausa = null;
  }

  MotorDialogos.prototype.executarAcao = function (acao) {
    var p = this.palco, i = this.instrutor;
    switch (acao.tipo) {
      case 'revelar':        p.revelar(acao.alvo); break;
      case 'ocultar':        p.ocultar(acao.alvo); break;
      case 'destacar':       p.destacar(acao.alvo); break;
      case 'limpar-destaque':p.limparDestaque(); break;
      case 'foco':           p.foco(acao.alvo, acao.rotulo); break;
      case 'limpar-foco':    p.limparFoco(); break;
      case 'quadro':         p.quadro(acao.titulo, acao.html); break;
      case 'fechar-quadro':  p.fecharQuadro(); break;
      case 'classe':         p.aplicarClasse(acao.alvo, acao.classe, acao.remover); break;
      case 'gesto':          i.gesto(acao.valor, acao.duracao || 3, { braco: acao.braco || 'auto' }); break;
      case 'humor':          i.humor(acao.valor); break;
      case 'olhar':          i.olhar(acao.valor, acao.forca); break;
      case 'balao':          i.balao(acao.valor); break;
      case 'fechar-balao':   i.fecharBalao(); break;
      case 'instrutor':      (acao.valor === 'ocultar' ? i.ocultar() : i.mostrar()); break;
      case 'posicao':        i.posicionar(acao.valor); break;
      case 'giro':           if (i.orientacao) { i.orientacao({ giroCorpo: acao.valor }); } break;
      case 'lado-conteudo':  if (i.orientacao) { i.orientacao({ ladoConteudo: acao.valor }); } break;
      case 'callback':       if (typeof acao.fn === 'function') acao.fn(p, i); break;
    }
  };

  MotorDialogos.prototype._montarLegenda = function (texto) {
    var partes = String(texto || '').split(/(\s+)/);
    var html = '';
    partes.forEach(function (t) {
      html += /^\s+$/.test(t) ? t : '<span class="p">' + t.replace(/[<>&]/g, function (c) {
        return { '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c];
      }) + '</span>';
    });
    this.ui.texto.innerHTML = html;
    this._palavras = this.ui.texto.querySelectorAll('.p');
  };

  MotorDialogos.prototype._progressoLegenda = function (frac) {
    if (!this._palavras || !this._palavras.length) return;
    var limite = Math.floor(frac * this._palavras.length + 0.4);
    for (var k = 0; k < this._palavras.length; k++) {
      this._palavras[k].classList.toggle('on', k < limite);
    }
  };

  MotorDialogos.prototype.iniciar = function (lista) {
    this.parar();
    this.lista = lista || [];
    this.indice = -1;
    if (!this.lista.length) { this.aoTerminarSequencia(); return; }
    this.rodando = true;
    this.aoIniciarSequencia();
    this.proximo();
  };

  MotorDialogos.prototype.proximo = function () {
    if (!this.rodando) return;
    this.indice++;
    if (this.indice >= this.lista.length) {
      this.rodando = false;
      this.instrutor.fecharBalao();
      this.ui.progresso.style.width = '100%';
      this.aoTerminarSequencia();
      return;
    }
    this._executar(this.lista[this.indice]);
  };

  MotorDialogos.prototype._executar = function (d) {
    var self = this;
    var acoes = (d.acoes || []).map(function (a) { return Object.assign({}, a, { _feita: false }); });

    this.instrutor.humor(d.humor || 'neutral');

    var orientFala = {
      braco: d.gestoBraco || (d.gestoEspelhado ? 'espelhar' : 'auto'),
      intensidade: d.gestoIntensidade != null ? d.gestoIntensidade : 1,
      tronco: d.troncoGiro || 'auto',
      cabeca: d.cabecaMov || 'auto'
    };
    this.instrutor.gesto(d.gesto || null, d.gestoDuracao || 3, orientFala);
    this.instrutor.olhar(d.olhar || 'camera', d.olharForca);
    this.instrutor.balao(d.balao || '');
    this._montarLegenda(d.texto || '');
    this.ui.nome.textContent = (this.instrutor.cfg && this.instrutor.cfg.nome) || 'Instrutor';
    this.ui.contador.textContent = (this.indice + 1) + ' de ' + this.lista.length;
    this.ui.progresso.style.width = '0%';
    this.ui.legenda.classList.add('is-falando');
    this.aoTrocarDialogo(this.indice, d);

    acoes.forEach(function (a) {
      if ((a.em || 0) <= 0) { a._feita = true; self.executarAcao(a); }
    });

    this.controle = this.fala.reproduzir(d, {
      onProgresso: function (frac, charIndex) {
        self.ui.progresso.style.width = (frac * 100).toFixed(1) + '%';
        self._progressoLegenda(frac);
        for (var k = 0; k < acoes.length; k++) {
          if (!acoes[k]._feita && frac >= (acoes[k].em || 0)) {
            acoes[k]._feita = true;
            self.executarAcao(acoes[k]);
          }
        }
      },
      onFim: function () {
        self.ui.legenda.classList.remove('is-falando');
        if (d.aposFala) { (d.aposFala || []).forEach(function (a) { self.executarAcao(a); }); }
        self.timerPausa = setTimeout(function () { self.proximo(); }, d.pausa != null ? d.pausa : PAUSA_ENTRE_FALAS);
      }
    });
  };

  MotorDialogos.prototype.pausar = function () {
    if (!this.rodando || this.pausado) return;
    this.pausado = true;
    clearTimeout(this.timerPausa);
    if (this.controle) { this.controle.pausar(); }
    this.ui.legenda.classList.remove('is-falando');
  };

  MotorDialogos.prototype.retomar = function () {
    if (!this.rodando || !this.pausado) return;
    this.pausado = false;
    this.ui.legenda.classList.add('is-falando');
    if (this.controle) { this.controle.retomar(); }
  };

  MotorDialogos.prototype.pular = function () {
    if (!this.rodando) return;
    clearTimeout(this.timerPausa);
    if (this.controle) this.controle.concluir();
  };

  MotorDialogos.prototype.repetir = function () {
    if (!this.lista.length) return;
    var i = Math.min(Math.max(this.indice, 0), this.lista.length - 1);
    clearTimeout(this.timerPausa);
    if (this.controle) this.controle.cancelar();
    var retomando = !this.rodando;
    this.indice = i;
    this.rodando = true;
    this.pausado = false;
    if (retomando) { this.aoIniciarSequencia(); }
    this._executar(this.lista[i]);
  };

  MotorDialogos.prototype.irPara = function (i) {
    if (i < 0 || i >= this.lista.length) return;
    clearTimeout(this.timerPausa);
    if (this.controle) this.controle.cancelar();
    var retomando = !this.rodando;
    this.indice = i - 1;
    this.rodando = true;
    this.pausado = false;
    if (retomando) { this.aoIniciarSequencia(); }
    this.proximo();
  };

  MotorDialogos.prototype.parar = function () {
    clearTimeout(this.timerPausa);
    if (this.controle) this.controle.cancelar();
    this.controle = null;
    this.rodando = false;
    this.pausado = false;
    this.fala.parar();
    this.instrutor.fecharBalao();
    this.ui.legenda.classList.remove('is-falando');
    this.ui.progresso.style.width = '0%';
  };

  global.MotorDialogos = MotorDialogos;
})(window);
