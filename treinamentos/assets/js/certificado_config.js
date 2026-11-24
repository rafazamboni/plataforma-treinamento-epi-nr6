(function () {
  'use strict';

  var APRES  = window.TRM_APRES || 0;
  var URL_PV = window.TRC_PREVIA || '';
  var palco  = document.getElementById('cPalco');
  if (!palco) { return; }

  function $(id) { return document.getElementById(id); }

  var campos = Array.prototype.slice.call(document.querySelectorAll('[data-cfg]'));

  function coletar() {
    var cfg = {};
    campos.forEach(function (el) {
      var chave = el.getAttribute('data-cfg');
      if (el.type === 'radio') {
        if (el.checked) { cfg[chave] = el.value; }
      } else if (el.type === 'checkbox') {
        cfg[chave] = el.checked ? 1 : 0;
      } else {
        cfg[chave] = el.value;
      }
    });
    return cfg;
  }

  var faceAtiva = 'frente';
  var pedido = 0;
  var timer = null;

  function aplicarFace() {
    var faces = palco.querySelectorAll('.crt-face');
    var achou = false;
    Array.prototype.forEach.call(faces, function (f) {
      var sim = f.classList.contains('crt-face-' + faceAtiva);
      f.classList.toggle('is-ativa', sim);
      if (sim) { achou = true; }
    });
    if (!achou && faces.length) {
      faceAtiva = 'frente';
      trocarAba('frente');
      if (faces[0]) { faces[0].classList.add('is-ativa'); }
    }
    if (window.certAjustar) { window.certAjustar(palco); }
  }

  function trocarAba(face) {
    Array.prototype.forEach.call(
      document.querySelectorAll('.crt-abas button'),
      function (b) { b.classList.toggle('is-ativa', b.getAttribute('data-face') === face); }
    );
  }

  function desenhar() {
    var meu = ++pedido;
    var corpo = new FormData();
    corpo.append('cfg', JSON.stringify(coletar()));

    palco.classList.add('is-carregando');

    fetch(URL_PV, {
      method: 'POST',
      body: corpo,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) {
      if (!r.ok) { throw new Error('Falha ao montar a prévia (' + r.status + ').'); }
      return r.text();
    }).then(function (html) {
      if (meu !== pedido) { return; }
      palco.innerHTML = html;
      palco.classList.remove('is-carregando');
      aplicarFace();
      palco.classList.toggle('crt-desligado', !$('cAtivo').checked);
      modalAtualizar();
    }).catch(function (e) {
      if (meu !== pedido) { return; }
      palco.classList.remove('is-carregando');
      palco.innerHTML = '<div class="crt-vazio">' + e.message + '</div>';
    });
  }

  function agendar() {
    clearTimeout(timer);
    timer = setTimeout(desenhar, 280);
  }

  function sincronizar() {
    var ativo = $('cAtivo').checked;
    $('cCampos').style.display = ativo ? '' : 'none';

    var propria = $('cCorModo').value === 'propria';
    $('cCoresCampo').classList.toggle('crt-bloco', false);
    $('cCoresCampo').style.opacity = propria ? '1' : '.45';
    $('cCorPrimaria').disabled = !propria;
    $('cCorAcento').disabled = !propria;

    var verso = $('cVerso').checked;
    $('cVersoCampos').style.display = verso ? '' : 'none';
    $('cAbaVerso').disabled = !verso;
    if ($('cModalAbaVerso')) { $('cModalAbaVerso').disabled = !verso; }
    if (!verso && faceAtiva === 'verso') { faceAtiva = 'frente'; trocarAba('frente'); }
    if (!verso && modalFace === 'verso') { modalFace = 'frente'; modalTrocarAba('frente'); }

    var modo = $('cVersoModo').value;
    var areaTexto = $('cVersoTexto').closest('.crt-linha');
    if (areaTexto) { areaTexto.style.display = (modo === 'auto') ? 'none' : ''; }

    var qr = $('cQr').checked;
    ['cQrPosicao', 'cQrTamanho', 'cQrNivel', 'cQrLegenda'].forEach(function (id) {
      var el = $(id);
      if (!el) { return; }
      el.disabled = !qr;
      var campo = el.closest('.crt-campo');
      if (campo) { campo.style.opacity = qr ? '1' : '.45'; }
    });

    var selo = $('cSelo').checked;
    ['cSeloIcone', 'cSeloTexto', 'cSeloTamanho'].forEach(function (id) {
      var el = $(id);
      if (!el) { return; }
      el.disabled = !selo;
      var campo = el.closest('.crt-campo');
      if (campo) { campo.style.opacity = selo ? '1' : '.45'; }
    });

    var logo = $('cLogo').checked;
    ['cLogoTamanho'].forEach(function (id) {
      var el = $(id);
      if (!el) { return; }
      el.disabled = !logo;
      var campo = el.closest('.crt-campo');
      if (campo) { campo.style.opacity = logo ? '1' : '.45'; }
    });

    var localData = $('cLocalData').checked;
    $('cDataExtenso').disabled = !localData;
    $('cCidade').disabled = !localData;

    var marca = $('cMarca').checked;
    ['cMarcaTamanho', 'cMarcaOpacidade', 'cMarcaEspacoH', 'cMarcaEspacoV'].forEach(function (id) {
      var el = $(id);
      if (!el) { return; }
      el.disabled = !marca;
      var campo = el.closest('.crt-campo');
      if (campo) { campo.style.opacity = marca ? '1' : '.45'; }
    });

    $('cNomeTamanhoValor').textContent = $('cNomeTamanho').value + '%';
    $('cQrTamanhoValor').textContent = $('cQrTamanho').value + ' mm';
    $('cMarcaTamanhoValor').textContent = $('cMarcaTamanho').value + '%';
    $('cMarcaOpacidadeValor').textContent = $('cMarcaOpacidade').value + '%';
    $('cMarcaEspacoHValor').textContent = $('cMarcaEspacoH').value + '%';
    $('cMarcaEspacoVValor').textContent = $('cMarcaEspacoV').value + '%';
    $('cLogoTamanhoValor').textContent = $('cLogoTamanho').value + '%';
    $('cEmpresaTamanhoValor').textContent = $('cEmpresaTamanho').value + '%';
    $('cSeloTamanhoValor').textContent = $('cSeloTamanho').value + '%';
    $('cTitulosTamanhoValor').textContent = $('cTitulosTamanho').value + '%';
  }

  campos.forEach(function (el) {
    var evento = (el.tagName === 'INPUT' && (el.type === 'text' || el.type === 'range'))
      || el.tagName === 'TEXTAREA' ? 'input' : 'change';
    el.addEventListener(evento, function () {
      sincronizar();
      agendar();
    });
  });

  $('cAtivo').addEventListener('change', function () {
    sincronizar();
    palco.classList.toggle('crt-desligado', !$('cAtivo').checked);
  });

  Array.prototype.forEach.call(
    document.querySelectorAll('.crt-abas button'),
    function (b) {
      b.addEventListener('click', function () {
        if (b.disabled) { return; }
        faceAtiva = b.getAttribute('data-face');
        trocarAba(faceAtiva);
        aplicarFace();
      });
    }
  );

  var modal       = $('cModal');
  var modalPalco  = $('cModalPalco');
  var modalAbaF   = $('cModalAbaFrente');
  var modalAbaV   = $('cModalAbaVerso');
  var modalFace   = 'frente';

  if (modal && modal.parentNode !== document.body) {
    document.body.appendChild(modal);
  }

  function modalAplicarFace() {
    if (!modalPalco) { return; }
    Array.prototype.forEach.call(modalPalco.querySelectorAll('.crt-face'), function (f) {
      f.classList.toggle('is-ativa', f.classList.contains('crt-face-' + modalFace));
    });
    if (window.certAjustar) { window.certAjustar(modalPalco); }
  }

  function modalTrocarAba(face) {
    [modalAbaF, modalAbaV].forEach(function (b) {
      if (b) { b.classList.toggle('is-ativa', b.getAttribute('data-face') === face); }
    });
  }

  function modalAtualizar() {
    if (!modal || !modal.classList.contains('is-aberto')) { return; }
    modalPalco.innerHTML = palco.innerHTML;
    modalAplicarFace();
  }

  function abrirModal() {
    if (!modal) { return; }
    modalFace = faceAtiva;
    modalTrocarAba(modalFace);
    modalPalco.innerHTML = palco.innerHTML;
    modalAplicarFace();
    modal.classList.add('is-aberto');
    document.body.style.overflow = 'hidden';
  }

  function fecharModal() {
    if (!modal) { return; }
    modal.classList.remove('is-aberto');
    document.body.style.overflow = '';
  }

  var btnAmpliar = $('cAmpliar');
  if (btnAmpliar) { btnAmpliar.addEventListener('click', abrirModal); }

  var btnModalFechar = $('cModalFechar');
  if (btnModalFechar) { btnModalFechar.addEventListener('click', fecharModal); }

  if (modal) {
    modal.addEventListener('click', function (e) {
      if (e.target === modal) { fecharModal(); }
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal && modal.classList.contains('is-aberto')) { fecharModal(); }
  });

  [modalAbaF, modalAbaV].forEach(function (b) {
    if (!b) { return; }
    b.addEventListener('click', function () {
      if (b.disabled) { return; }
      modalFace = b.getAttribute('data-face');
      modalTrocarAba(modalFace);
      modalAplicarFace();
    });
  });

  sincronizar();
  desenhar();

  var btn = $('cSalvar');
  if (!btn || !window.TRC_GERENCIA) { return; }

  btn.addEventListener('click', function () {
    btn.disabled = true;
    btn.style.opacity = '.6';
    btn.style.cursor = 'progress';
    $('cEstado').textContent = 'Salvando…';

    window.trApi('cert_config', {
      id: APRES,
      emitir_certificado: $('cAtivo').checked ? 1 : 0,
      cfg: coletar()
    }).then(function (j) {
      window.trToast(j.msg);
      $('cEstado').textContent = 'Salvo às ' + new Date().toLocaleTimeString('pt-BR').slice(0, 5) + '.';
    }).catch(function (e) {
      window.trToast(e.message, 'erro');
      $('cEstado').textContent = 'Não foi possível salvar.';
    }).then(function () {
      btn.disabled = false;
      btn.style.opacity = '';
      btn.style.cursor = '';
    });
  });
})();
