(function (global) {
  'use strict';

  var PPM = 155;

  function Fala() {
    this.suporte = typeof global.speechSynthesis !== 'undefined' &&
                   typeof global.SpeechSynthesisUtterance !== 'undefined';
    this.vozes = [];
    this.opcoes = {
      vozURI: '', rate: 1, pitch: 1, volume: 1,
      mudo: false, idioma: 'pt-BR', taxaAudio: 1
    };
    this.instrutor = null;
    this.atual = null;
    this.cacheAudio = {};
    this._carregarVozes();
  }

  Fala.prototype.usarInstrutor = function (instrutor) {
    this.instrutor = instrutor;
  };

  Fala.prototype._carregarVozes = function () {
    if (!this.suporte) { return; }
    var self = this;
    function ler() {
      self.vozes = global.speechSynthesis.getVoices() || [];
      if (self.onVozes) { self.onVozes(self.vozes); }
    }
    ler();
    global.speechSynthesis.onvoiceschanged = ler;
  };

  Fala.prototype.listarVozes = function () {
    var pt = this.vozes.filter(function (v) { return /^pt/i.test(v.lang); });
    return pt.length ? pt.concat(this.vozes.filter(function (v) { return !/^pt/i.test(v.lang); })) : this.vozes;
  };

  Fala.prototype.configurar = function (parcial) {
    Object.assign(this.opcoes, parcial || {});
    if (this._audio) {
      this._audio.volume = this.opcoes.mudo ? 0 : this.opcoes.volume;
      if (parcial && parcial.taxaAudio) { this._audio.playbackRate = parcial.taxaAudio; }
    }
    if (this.atual && this.atual.som) { this.atual.som(); }
  };

  Fala.prototype.contexto = function () {
    if (this.instrutor && this.instrutor.head && this.instrutor.head.audioCtx) {
      return this.instrutor.head.audioCtx;
    }
    if (!this._ctx) {
      var C = global.AudioContext || global.webkitAudioContext;
      this._ctx = new C();
    }
    return this._ctx;
  };

  Fala.prototype.estimarDuracao = function (texto) {
    var t = String(texto || '').trim();
    if (!t) { return 600; }
    var palavras = t.split(/\s+/).length;
    var pausas = (t.match(/[,;:.!?…]/g) || []).length * 180;
    var ms = (palavras / PPM) * 60000 + pausas;
    return Math.max(1200, ms / (this.opcoes.rate || 1));
  };

  Fala.prototype.preparar = function (url) {
    var self = this;
    if (this.cacheAudio[url]) { return Promise.resolve(this.cacheAudio[url]); }
    return fetch(url, { credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) { throw new Error('Áudio indisponível.'); }
        return r.arrayBuffer();
      })
      .then(function (buf) { return self.contexto().decodeAudioData(buf); })
      .then(function (audio) { self.cacheAudio[url] = audio; return audio; });
  };

  Fala.prototype.reproduzir = function (item, cb) {
    cb = cb || {};
    var self = this;
    var texto = String(item.texto || '');
    var duracao = item.duracao || this.estimarDuracao(texto);
    var cancelado = false, pausado = false, finalizado = false;
    var t0 = 0, acumulado = 0, rafId = null, charIndex = 0;
    var audio = null, utter = null, guarda = null;

    function tick() {
      if (cancelado || finalizado) { return; }
      var decorrido = acumulado + (pausado ? 0 : Date.now() - t0);
      var frac = (audio && audio.duration)
        ? (audio.currentTime / audio.duration)
        : Math.min(decorrido / duracao, 0.999);
      if (cb.onProgresso) { cb.onProgresso(Math.max(0, Math.min(1, frac)), charIndex, decorrido); }
      rafId = global.requestAnimationFrame(tick);
    }

    function encerrar() {
      if (finalizado) { return; }
      finalizado = true;
      if (rafId) { global.cancelAnimationFrame(rafId); }
      clearTimeout(guarda);
      if (cb.onProgresso) { cb.onProgresso(1, texto.length, duracao); }
      if (cb.onFim && !cancelado) { cb.onFim(); }
    }

    function iniciarCronometro(d) {
      duracao = d;
      t0 = Date.now();
      if (cb.onInicio) { cb.onInicio(duracao); }
      rafId = global.requestAnimationFrame(tick);
    }

    var controle = {
      duracaoEstimada: duracao,
      cancelar: function () {
        cancelado = true;
        finalizado = true;
        if (rafId) { global.cancelAnimationFrame(rafId); }
        clearTimeout(guarda);
        if (audio) { audio.pause(); audio.src = ''; }
        if (self.instrutor) { self.instrutor.pararLipsync(); }
        if (utter && self.suporte) { try { global.speechSynthesis.cancel(); } catch (e) {} }
      },
      concluir: function () {
        if (finalizado) { return; }
        if (rafId) { global.cancelAnimationFrame(rafId); }
        clearTimeout(guarda);
        if (audio) { audio.pause(); audio.src = ''; }
        if (self.instrutor) { self.instrutor.pararLipsync(); }
        if (utter && self.suporte) { try { global.speechSynthesis.cancel(); } catch (e) {} }
        encerrar();
      },
      pausar: function () {
        if (pausado || finalizado) { return; }
        pausado = true;
        acumulado += Date.now() - t0;
        clearTimeout(guarda);
        if (audio) { audio.pause(); }
        if (self.instrutor) { self.instrutor.pararLipsync(); }
        if (utter && self.suporte) { try { global.speechSynthesis.pause(); } catch (e) {} }
      },
      retomar: function () {
        if (!pausado || finalizado) { return; }
        pausado = false;
        t0 = Date.now();
        if (audio) { audio.play(); }
        if (utter && self.suporte) { try { global.speechSynthesis.resume(); } catch (e) {} }
        if (audio && self.instrutor) {
          self.instrutor.iniciarLipsync(item.lipsync, function () { return audio.currentTime * 1000; });
        }
        if (!audio) { guarda = setTimeout(encerrar, Math.max(0, duracao - acumulado) + (utter ? 2500 : 0)); }
      },

      som: function () {
        if (cancelado || finalizado) { return; }
        var mudo = !!self.opcoes.mudo;
        var vol = mudo ? 0 : (self.opcoes.volume == null ? 1 : Number(self.opcoes.volume));
        var decorrido = acumulado + (pausado ? 0 : Date.now() - t0);

        if (audio) {
          audio.volume = vol;
          audio.muted = mudo;
          if (mudo) { audio.pause(); }
          else if (!pausado && audio.paused) { audio.play().catch(function () {}); }
          return;
        }

        if (mudo) {
          if (utter && self.suporte) {
            try { global.speechSynthesis.cancel(); } catch (e) {}
            utter = null;
            if (self.instrutor) { self.instrutor.pararLipsync(); }
          }
          return;
        }

        if (pausado) { return; }
        if (item.audio) { retomarAudio(decorrido); return; }
        if (self.suporte && texto && !utter) { falar(charIndex); }
      }
    };

    function tocarElemento() {
      if (cancelado || !item.audio || self.opcoes.mudo) { return semAudio(); }
      audio = new Audio(item.audio);
      self._audio = audio;
      audio.volume = self.opcoes.mudo ? 0 : self.opcoes.volume;
      audio.playbackRate = self.opcoes.taxaAudio || 1;
      audio.preservesPitch = true;
      audio.onloadedmetadata = function () {
        if (cancelado) { return; }
        iniciarCronometro((audio.duration * 1000) / (audio.playbackRate || 1));
        if (self.instrutor) {
          self.instrutor.iniciarLipsync(item.lipsync, function () { return audio.currentTime * 1000; });
        }
      };
      audio.onended = function () {
        if (self.instrutor) { self.instrutor.pararLipsync(); }
        encerrar();
      };
      audio.onerror = function () {
        audio = null;
        if (cb.onErro) { cb.onErro('audio'); }
        semAudio();
      };
      audio.play().catch(function () {
        audio = null;
        if (cb.onErro) { cb.onErro('bloqueio'); }
        semAudio();
      });
    }

    function falar(desde) {
      var trecho = desde > 0 ? texto.slice(desde) : texto;
      if (!trecho) { return; }
      try { global.speechSynthesis.cancel(); } catch (e) {}
      utter = new global.SpeechSynthesisUtterance(trecho);
      utter.lang = self.opcoes.idioma;
      utter.rate = self.opcoes.rate;
      utter.pitch = self.opcoes.pitch;
      utter.volume = self.opcoes.volume;
      var v2 = self.vozes.filter(function (v) { return v.voiceURI === self.opcoes.vozURI; })[0];
      if (v2) { utter.voice = v2; utter.lang = v2.lang; }
      utter.onboundary = function (ev) {
        if (typeof ev.charIndex === 'number') { charIndex = desde + ev.charIndex; }
      };
      utter.onend = function () {
        if (self.instrutor) { self.instrutor.pararLipsync(); }
        encerrar();
      };
      utter.onerror = utter.onend;
      if (self.instrutor) { self.instrutor.iniciarLipsync(null, null); }
      global.speechSynthesis.speak(utter);
    }

    function semAudio() {
      if (cancelado) { return; }
      if (self.suporte && !self.opcoes.mudo && texto) {
        iniciarCronometro(self.estimarDuracao(texto));
        guarda = setTimeout(encerrar, duracao + 2500);
        falar(0);
      } else {
        iniciarCronometro(item.duracao || self.estimarDuracao(texto));
        guarda = setTimeout(encerrar, duracao);
      }
    }

    function retomarAudio(decorridoMs) {
      if (cancelado || finalizado || !item.audio) { return; }
      audio = new Audio(item.audio);
      self._audio = audio;
      audio.volume = self.opcoes.mudo ? 0 : self.opcoes.volume;
      audio.playbackRate = self.opcoes.taxaAudio || 1;
      audio.preservesPitch = true;
      audio.onloadedmetadata = function () {
        if (cancelado || finalizado) { return; }
        var seg = (decorridoMs / 1000) * (audio.playbackRate || 1);
        try { audio.currentTime = Math.max(0, Math.min(seg, (audio.duration || 0) - 0.05)); } catch (e) {}
        if (self.instrutor) {
          self.instrutor.iniciarLipsync(item.lipsync, function () { return audio.currentTime * 1000; });
        }
      };
      audio.onended = function () {
        if (self.instrutor) { self.instrutor.pararLipsync(); }
        encerrar();
      };
      audio.onerror = function () { audio = null; };
      audio.play().catch(function () { audio = null; });
    }

    if (item.audio && !this.opcoes.mudo) { tocarElemento(); }
    else { semAudio(); }

    this.atual = controle;
    return controle;
  };

  Fala.prototype.parar = function () {
    if (this.atual) { this.atual.cancelar(); }
    this.atual = null;
    if (this.instrutor) { this.instrutor.pararLipsync(); }
    if (this.suporte) { try { global.speechSynthesis.cancel(); } catch (e) {} }
  };

  global.Fala = Fala;
})(window);
