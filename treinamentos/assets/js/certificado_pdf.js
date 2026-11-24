(function () {
  'use strict';

  var PX_POR_MM = 96 / 25.4;
  var ESCALA    = 2.4;
  var QUALIDADE = 0.92;

  function papelMm(folha) {
    var carta = folha.classList.contains('papel-carta');
    var w = carta ? 279.4 : 297, h = carta ? 215.9 : 210;
    return folha.classList.contains('orient-retrato') ? [h, w] : [w, h];
  }

  function esperar(ms) { return new Promise(function (ok) { setTimeout(ok, ms); }); }

  function imagensProntas(raiz) {
    var imgs = Array.prototype.slice.call(raiz.querySelectorAll('img'));
    return Promise.all(imgs.map(function (img) {
      if (img.complete && img.naturalWidth > 0) { return null; }
      return new Promise(function (ok) { img.onload = img.onerror = ok; });
    }));
  }

  function desenhar(folha) {
    var mm  = papelMm(folha);
    var wpx = Math.round(mm[0] * PX_POR_MM);
    var hpx = Math.round(mm[1] * PX_POR_MM);

    var palco = document.createElement('div');
    palco.className = 'cert-pilha';
    palco.style.cssText = 'position:fixed;left:-20000px;top:0;max-width:none;margin:0;width:' + wpx + 'px;';

    var copia = folha.cloneNode(true);
    copia.style.width = wpx + 'px';
    copia.style.height = hpx + 'px';
    copia.style.aspectRatio = 'auto';
    copia.style.boxShadow = 'none';
    copia.style.borderRadius = '0';
    copia.style.margin = '0';

    palco.appendChild(copia);
    document.body.appendChild(palco);

    var pronto = (document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve())
      .then(function () { return imagensProntas(palco); })
      .then(function () {
        if (window.certAjustar) { window.certAjustar(palco); }
        return esperar(60);
      })
      .then(function () {
        return window.htmlToImage.toCanvas(copia, {
          width: wpx, height: hpx, pixelRatio: ESCALA, backgroundColor: '#ffffff'
        });
      });

    var limpar = function () { if (palco.parentNode) { palco.parentNode.removeChild(palco); } };
    return pronto.then(function (c) { limpar(); return { canvas: c, mm: mm }; },
                       function (e) { limpar(); throw e; });
  }

  window.certBaixarPDF = function (nomeArquivo) {
    if (!window.htmlToImage || !window.jspdf || !window.jspdf.jsPDF) {
      return Promise.reject(new Error('Bibliotecas de PDF não carregaram.'));
    }
    var folhas = Array.prototype.slice.call(document.querySelectorAll('.cert-pilha > .cert-folha'));
    if (!folhas.length) { return Promise.reject(new Error('Certificado não encontrado.')); }

    var doc = null;
    var cadeia = Promise.resolve();

    folhas.forEach(function (folha, i) {
      cadeia = cadeia.then(function () { return desenhar(folha); }).then(function (r) {
        var w = r.mm[0], h = r.mm[1];
        var ori = w > h ? 'landscape' : 'portrait';
        if (!doc) {
          doc = new window.jspdf.jsPDF({ orientation: ori, unit: 'mm', format: [w, h], compress: true });
        } else {
          doc.addPage([w, h], ori);
        }
        doc.addImage(r.canvas.toDataURL('image/jpeg', QUALIDADE), 'JPEG', 0, 0, w, h, undefined, 'FAST');
      });
    });

    return cadeia.then(function () {
      doc.setProperties({ title: nomeArquivo.replace(/\.pdf$/i, ''), creator: 'RH Digital' });
      doc.save(nomeArquivo);
      return doc.getNumberOfPages();
    });
  };
})();
