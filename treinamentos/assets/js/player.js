(function (global) {
  'use strict';

  function Palco(refs) {
    this.stage = refs.stage;
    this.wrap = refs.wrap;
    this.foco_ = refs.foco;
    this.focoRing = refs.focoRing;
    this.focoLabel = refs.focoLabel;
    this.quadro_ = refs.quadro;
    this.quadroTitulo = refs.quadroTitulo;
    this.quadroBody = refs.quadroBody;
    this.slideAtual = null;
    this._destacados = [];
    var self = this;
    global.addEventListener('resize', function () { self._reposicionarFoco(); });
  }

  Palco.prototype.definirSlide = function (el) {
    this.limparFoco();
    this.fecharQuadro();
    this._destacados = [];
    this.slideAtual = el;
  };

  Palco.prototype.buscar = function (seletor) {
    if (!seletor) return [];
    if (seletor === ':slide') return this.slideAtual ? [this.slideAtual] : [];
    var raiz = this.slideAtual || this.wrap;
    return Array.prototype.slice.call(raiz.querySelectorAll(seletor));
  };

  Palco.prototype.revelar = function (seletor) {
    this.buscar(seletor).forEach(function (el) {
      el.classList.remove('nv-sumir');
      el.classList.add('nv-revelar');
      if (el.classList.contains('trc-video') &&
          el.getAttribute('data-vinicio') === 'visivel') {
        comandarVideo(el, 'reiniciar');
      }
      if (el.classList.contains('nv-barra')) el.classList.add('is-visivel');
    });
  };

  Palco.prototype.ocultar = function (seletor) {
    this.buscar(seletor).forEach(function (el) {
      el.classList.remove('nv-revelar');
      el.classList.add('nv-sumir');
    });
  };

  Palco.prototype.destacar = function (seletor) {
    this.limparDestaque();
    var self = this;
    this.buscar(seletor).forEach(function (el) {
      el.classList.add('nv-destaque');
      self._destacados.push(el);
    });
  };

  Palco.prototype.limparDestaque = function () {
    this._destacados.forEach(function (el) { el.classList.remove('nv-destaque'); });
    this._destacados = [];
  };

  function comandarVideo(el, acao) {
    var v = el.tagName === 'VIDEO' ? el : el.querySelector('video');
    if (!v) { return; }

    var vel = parseFloat(el.getAttribute('data-vvel') || '1');
    if (vel > 0) { v.playbackRate = vel; }

    if (acao === 'parar') { try { v.pause(); } catch (e) {} return; }

    if (acao === 'reiniciar') {
      try { v.currentTime = parseFloat(el.getAttribute('data-vseg') || '0'); } catch (e) {}
    } else if (v.currentTime === 0) {
      var ini = parseFloat(el.getAttribute('data-vseg') || '0');
      if (ini > 0) { try { v.currentTime = ini; } catch (e) {} }
    }

    var p = v.play();
    if (p && p.catch) {
      p.catch(function () {
        v.muted = true;
        try { v.play(); } catch (e) {}
      });
    }
  }

  function pararVideosFora(slideAtual) {
    Array.prototype.forEach.call(document.querySelectorAll('.trc-video video'), function (v) {
      var slide = v.closest ? v.closest('.nv-slide') : null;
      if (slide !== slideAtual) { try { v.pause(); } catch (e) {} }
    });
  }

  Palco.prototype.aplicarClasse = function (seletor, classe, remover) {
    this.buscar(seletor).forEach(function (el) {
      if (classe === 'trc-video-tocar') {
        comandarVideo(el, remover ? 'parar' : 'tocar');
        el.classList[remover ? 'remove' : 'add'](classe);
        return;
      }
      if (classe === 'trc-video-reiniciar') {
        comandarVideo(el, 'reiniciar');
        return;
      }

      el.classList[remover ? 'remove' : 'add'](classe);

      if (classe !== 'trc-img-destaque') { return; }
      clearTimeout(el._voltaDestaque);
      var seg = parseFloat(el.getAttribute('data-volta') || '0');
      if (remover || !(seg > 0)) { return; }
      el._voltaDestaque = setTimeout(function () {
        el.classList.remove('trc-img-destaque');
      }, seg * 1000);
    });
  };

  Palco.prototype.foco = function (seletor, rotulo) {
    var el = this.buscar(seletor)[0];
    if (!el) return this.limparFoco();
    this._focoEl = el;
    this._focoRotulo = rotulo || '';
    this._reposicionarFoco();
    this.foco_.classList.add('is-ativo');
  };

  Palco.prototype._reposicionarFoco = function () {
    if (!this._focoEl) return;
    var r = this._focoEl.getBoundingClientRect();
    var b = this.stage.getBoundingClientRect();
    var pad = 12;
    var top = r.top - b.top - pad;
    var left = r.left - b.left - pad;
    this.focoRing.style.top = top + 'px';
    this.focoRing.style.left = left + 'px';
    this.focoRing.style.width = (r.width + pad * 2) + 'px';
    this.focoRing.style.height = (r.height + pad * 2) + 'px';
    if (this._focoRotulo) {
      this.focoLabel.textContent = this._focoRotulo;
      this.focoLabel.style.display = '';
      this.focoLabel.style.left = left + 'px';
      this.focoLabel.style.top = Math.max(4, top - 26) + 'px';
    } else {
      this.focoLabel.style.display = 'none';
    }
  };

  Palco.prototype.limparFoco = function () {
    this.foco_.classList.remove('is-ativo');
    this._focoEl = null;
  };

  Palco.prototype.quadro = function (titulo, html) {
    this.quadroTitulo.textContent = titulo || 'Quadro explicativo';
    this.quadroBody.innerHTML = html || '';
    this.quadro_.classList.add('is-ativo');
  };

  Palco.prototype.fecharQuadro = function () {
    this.quadro_.classList.remove('is-ativo');
  };

  function Player(opcoes) {
    this.apresentacao = opcoes.apresentacao;
    this.instrutor = opcoes.instrutor;
    this.fala = opcoes.fala;
    this.palco = opcoes.palco;
    this.ui = opcoes.ui;
    this.indice = -1;
    this.autoAvancar = true;
    this.aoTrocarSlide = opcoes.aoTrocarSlide || function () {};
    this.emReproducao = false;
    this.visitados = {};
    this.concluidos = {};
    this.ouvidas = {};
    this._trocando = false;
    this._aguardandoAuto = false;

    var self = this;
    this.motor = new global.MotorDialogos({
      instrutor: this.instrutor,
      fala: this.fala,
      palco: this.palco,
      ui: {
        legenda: opcoes.ui.legenda,
        texto: opcoes.ui.texto,
        nome: opcoes.ui.nome,
        contador: opcoes.ui.contadorDialogo,
        progresso: opcoes.ui.progressoDialogo
      },
      aoIniciarSequencia: function () {
        self.emReproducao = true;
        self._mostrarLegenda();
        self._atualizarControles();
      },
      aoTerminarSequencia: function () {
        self.emReproducao = false;
        self._ocultarLegendaEmBreve();
        self.concluidos[self.indice] = true;
        if (self.autoAvancar && self.indice < self.apresentacao.slides.length - 1) {
          self._aguardandoAuto = true;
          self._atualizarControles();
          self._timerAuto = setTimeout(function () {
            self._aguardandoAuto = false;
            self.proximoSlide(true);
          }, 900);
          return;
        }
        self._atualizarControles();
        if (self.indice === self.apresentacao.slides.length - 1) {
          self.ui.textoFinal && self.ui.textoFinal();
        }
      },
      aoTrocarDialogo: function (i) {
        var maior = self.ouvidas[self.indice];
        if (maior == null || i > maior) { self.ouvidas[self.indice] = i; }
        self._marcarRoteiro(i);
        self._atualizarControles();
      }
    });

    this._montarSlides();
    this._montarPassos();
  }

  Player.prototype._montarSlides = function () {
  var self = this;

  if (this.elsSlides) {
    this.elsSlides.forEach(function (el) { el.remove(); });
  }

  var ancora = this.ui.wrap.firstElementChild;

  this.elsSlides = this.apresentacao.slides.map(function (s, i) {
    var div = document.createElement('section');
    div.className = 'nv-slide t-' + (s.transicao || 'fade') + ' lay-' + (s.layout || 'esquerda');
    div.id = 'slide-' + (s.id || i);
    div.setAttribute('data-indice', i);
    if (!s.instrutor || s.instrutor.visivel !== false) { div.classList.add('com-instrutor'); }
    if (s.estilo) { div.setAttribute('style', s.estilo); }
    if (s.corTexto) { div.style.setProperty('--nv-slide-texto', s.corTexto); div.classList.add('cor-propria'); }
    if (s.escurecer) { div.style.setProperty('--nv-slide-veu', (s.escurecer / 100)); div.classList.add('com-veu'); }
    div.innerHTML = '<div class="nv-slide-conteudo">' + (s.conteudo || '') + '</div>';
    if (global.TrCanvasVisual) { global.TrCanvasVisual.aplicar(div); }
    self.ui.wrap.insertBefore(div, ancora);
    return div;
  });
};

  Player.prototype._montarPassos = function () {
    var self = this;
    if (!this.ui.steps) { return; }
    this.ui.steps.innerHTML = '';
    this.apresentacao.slides.forEach(function (s, i) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'nv-step';
      b.title = (i + 1) + '. ' + (s.titulo || 'Slide ' + (i + 1));
      b.addEventListener('click', function () { self.irPara(i, true); });
      self.ui.steps.appendChild(b);
    });
  };

  Player.prototype._atualizarPassos = function () {
    var self = this;
    if (!this.ui.steps) { return; }
    Array.prototype.forEach.call(this.ui.steps.children, function (b, i) {
      b.classList.toggle('is-atual', i === self.indice);
      b.classList.toggle('is-vista', !!self.visitados[i] && i !== self.indice);
    });
  };

  Player.prototype.estado = function () {
    if (this._trocando || this._aguardandoAuto) { return 'transicao'; }
    if (this.emReproducao) { return this.motor.pausado ? 'pausado' : 'tocando'; }
    return 'parado';
  };

  Player.prototype.slideConcluido = function (i) {
    return !!this.concluidos[i == null ? this.indice : i];
  };

  Player.prototype.podeAvancar = function () {
    return this.indice < this.apresentacao.slides.length - 1 && this.slideConcluido();
  };

  Player.prototype.falaLiberada = function (k) {
    if (this.slideConcluido()) { return true; }
    return k <= (this.ouvidas[this.indice] || 0);
  };

  Player.prototype._mostrarLegenda = function () {
    clearTimeout(this._timerLegenda);
    this._timerLegenda = null;
    if (this.ui.legenda) { this.ui.legenda.classList.remove('is-encerrada'); }
  };

  Player.prototype._ocultarLegendaEmBreve = function () {
    var self = this;
    clearTimeout(this._timerLegenda);
    if (!this.ui.legenda) { return; }
    this._timerLegenda = setTimeout(function () {
      if (self.emReproducao) { return; }
      self.ui.legenda.classList.add('is-encerrada');
    }, 2000);
  };

  Player.prototype._atualizarControles = function () {
    try { document.dispatchEvent(new CustomEvent('nv:estado')); } catch (e) {}
    var total = this.apresentacao.slides.length;
    var tocando = this.emReproducao;
    var slideAtual = this.apresentacao.slides[this.indice];
    var temFalas = !!(slideAtual && (slideAtual.dialogos || []).length);
    var concluido = this.slideConcluido();

    this.ui.btnAnterior.disabled = this.indice <= 0;
    this.ui.btnProximo.disabled = !this.podeAvancar();
    this.ui.btnProximo.title = this.indice >= total - 1
      ? 'Último slide'
      : (concluido ? 'Próximo slide' : 'Disponível ao final da narração deste slide');

    this.ui.btnRepetir.disabled = !temFalas;
    this.ui.btnPular.disabled = !(tocando && concluido);
    this.ui.btnPular.title = concluido
      ? 'Próxima fala'
      : 'Disponível depois de ouvir o slide inteiro';

    this.ui.btnProximo.classList.toggle('is-chamando',
      concluido && !tocando && !this.autoAvancar && this.indice < total - 1);

    this.ui.contador.textContent = (this.indice + 1) + ' / ' + total;
    this.ui.progressoBarra.style.width = (((this.indice + 1) / total) * 100) + '%';
    this.ui.btnPlayTxt.textContent = tocando
      ? (this.motor.pausado ? 'Retomar' : 'Pausar')
      : (this.iniciado ? 'Reproduzir slide' : 'Iniciar');
    this.ui.btnPlay.querySelector('i').className = (tocando && !this.motor.pausado)
      ? 'bi bi-pause-fill' : 'bi bi-play-fill';
    this.ui.btnPlay.title = this.ui.btnPlayTxt.textContent;
  };

  Player.prototype.irPara = function (i, reproduzir) {
    if (i < 0 || i >= this.apresentacao.slides.length) return;
    clearTimeout(this._timerAuto);
    this._aguardandoAuto = false;
    this._trocando = true;
    this.motor.parar();
    this.emReproducao = false;

    var slide = this.apresentacao.slides[i];
    var el = this.elsSlides[i];

    this.elsSlides.forEach(function (e) { e.classList.remove('is-ativo'); });
    el.classList.remove('is-ativo');
    void el.offsetWidth;
    el.classList.add('is-ativo');

    Array.prototype.forEach.call(el.querySelectorAll('.nv-oculto'), function (n) {
      n.classList.remove('nv-revelar', 'nv-sumir');
    });
    Array.prototype.forEach.call(el.querySelectorAll('.nv-destaque'), function (n) {
      n.classList.remove('nv-destaque');
    });
    Array.prototype.forEach.call(el.querySelectorAll('.nv-barra'), function (n) {
      n.classList.remove('is-visivel');
    });

    this.indice = i;
    this.visitados[i] = true;
    this.palco.definirSlide(el);

    var cfgI = slide.instrutor || {};
    el.classList.toggle('com-instrutor', cfgI.visivel !== false);
    if (cfgI.visivel === false) {
      this.instrutor.ocultar();
    } else {
      this.instrutor.mostrar();
      this.instrutor.posicionar(cfgI.posicao || 'direita');
      if (this.instrutor.orientacao) {
        var base = this.instrutor.orientBase || {};
        this.instrutor.orientacao({
          posicao: cfgI.posicao || 'direita',
          ladoConteudo: cfgI.ladoConteudo || 'auto',
          giroCorpo: (cfgI.giro && cfgI.giro !== 'herdar') ? cfgI.giro : (base.giroCorpo || 'leve')
        });
      }
      if (this.instrutor.escalar) { this.instrutor.escalar(cfgI.escala || 1); }
      if (this.instrutor.camera) { this.instrutor.camera(cfgI.camera); }
    }
    this.instrutor.humor(this.apresentacao.avatar ? this.apresentacao.avatar.humor : 'neutral');

    this.ui.contadorDialogo.textContent = '—';
    this.ui.progressoDialogo.style.width = '0%';

    this._mostrarLegenda();
    pararVideosFora(null);

    Array.prototype.forEach.call(document.querySelectorAll('.trc-img-destaque'), function (n) {
      clearTimeout(n._voltaDestaque);
      n.classList.remove('trc-img-destaque');
    });
    this.ui.texto.textContent = '';
    if (this.ui.legenda) { this.ui.legenda.classList.remove('is-falando'); }
    this._montarRoteiro();
    this._atualizarPassos();
    this._atualizarControles();

    if (!(slide.dialogos || []).length) { this.concluidos[i] = true; }

    var el = this.elsSlides[i];
    if (el) {
      Array.prototype.forEach.call(el.querySelectorAll('.trc-video'), function (n) {
        if (n.getAttribute('data-vinicio') === 'auto') { comandarVideo(n, 'reiniciar'); }
      });
    }

    this.aoTrocarSlide(i, this.apresentacao.slides[i]);
    if (reproduzir !== false) { this.reproduzirSlide(); }
    else { this.ui.texto.textContent = 'Pressione Reproduzir para ouvir a explicação deste slide.'; }

    this._trocando = false;
    this._atualizarControles();
  };

  Player.prototype.reproduzirSlide = function () {
    var slide = this.apresentacao.slides[this.indice];
    if (!slide) return;
    this.iniciado = true;
    this.motor.iniciar(slide.dialogos || []);
    this._atualizarControles();
  };

  Player.prototype.proximoSlide = function (auto) {
    if (!auto && !this.podeAvancar()) { return; }
    this.irPara(this.indice + 1, true);
  };

  Player.prototype.slideAnterior = function () {
    this.irPara(this.indice - 1, true);
  };

  Player.prototype.alternarReproducao = function () {
    if (this.indice < 0) { this.irPara(0, true); return; }
    if (!this.emReproducao) { this.reproduzirSlide(); return; }
    if (this.motor.pausado) this.motor.retomar(); else this.motor.pausar();
    this._atualizarControles();
  };

  Player.prototype.pular = function () {
    if (!this.slideConcluido()) { return; }
    this.motor.pular();
  };

  Player.prototype.repetir = function () {
    if (!this.motor.lista.length) { this.reproduzirSlide(); return; }
    this.motor.repetir();
    this._atualizarControles();
  };

  Player.prototype.irParaFala = function (i) {
    if (!this.falaLiberada(i)) { return false; }
    if (!this.motor.lista.length) { this.reproduzirSlide(); }
    this.motor.irPara(i);
    this._atualizarControles();
    return true;
  };

  Player.prototype.encerrar = function () {
    clearTimeout(this._timerAuto);
    this.motor.parar();
    this.emReproducao = false;
    this._atualizarControles();
  };

  Player.prototype._montarRoteiro = function () {
    var self = this;
    var slide = this.apresentacao.slides[this.indice];
    var alvo = this.ui.roteiro;
    alvo.innerHTML = '';
    if (!slide) return;
    var bloco = document.createElement('div');
    bloco.className = 'nv-roteiro-slide';
    bloco.innerHTML = '<b>' + (this.indice + 1) + '. ' + (slide.titulo || 'Slide') + '</b>';
    (slide.dialogos || []).forEach(function (d, i) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'nv-roteiro-item';
      b.dataset.i = i;
      b.textContent = (i + 1) + '. ' + (d.texto || '').slice(0, 110);
      b.addEventListener('click', function () { self.irParaFala(i); });
      bloco.appendChild(b);
    });
    alvo.appendChild(bloco);
  };

  Player.prototype._marcarRoteiro = function (i) {
    try { document.dispatchEvent(new CustomEvent('nv:fala', { detail: { indice: i } })); } catch (e) {}
    var self = this;
    Array.prototype.forEach.call(this.ui.roteiro.querySelectorAll('.nv-roteiro-item'), function (b) {
      var k = Number(b.dataset.i);
      b.classList.toggle('is-atual', k === i);
      b.classList.toggle('is-travado', !self.falaLiberada(k));
    });
  };

  global.Palco = Palco;
  global.Player = Player;
})(window);
