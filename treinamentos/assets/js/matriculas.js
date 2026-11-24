(function () {
  'use strict';

  var APRES = window.TRM_APRES || 0;
  var $ = function (id) { return document.getElementById(id); };

  function recarregar(atraso) {
    setTimeout(function () { window.location.reload(); }, atraso || 900);
  }

  var Envio = (function () {
    var css =
      '.trmp{position:fixed;inset:0;z-index:2000;display:flex;align-items:center;justify-content:center;padding:16px;' +
        'background:rgba(15,23,42,.55);backdrop-filter:blur(2px);opacity:0;transition:opacity .2s}' +
      '.trmp.is-on{opacity:1}' +
      '.trmp-caixa{width:100%;max-width:440px;background:var(--bs-body-bg,#fff);color:var(--bs-body-color,#212529);' +
        'border-radius:14px;box-shadow:0 20px 50px rgba(0,0,0,.3);padding:26px 24px 20px;text-align:center;' +
        'transform:translateY(12px) scale(.97);transition:transform .25s}' +
      '.trmp.is-on .trmp-caixa{transform:none}' +
      '.trmp-icone{position:relative;width:74px;height:74px;margin:0 auto 14px;display:flex;align-items:center;justify-content:center}' +
      '.trmp-anel{position:absolute;inset:0;border-radius:50%;border:3px solid rgba(13,110,253,.15);border-top-color:var(--bs-primary,#0d6efd);animation:trmpGira 1s linear infinite}' +
      '.trmp-icone i{font-size:30px;color:var(--bs-primary,#0d6efd);animation:trmpVoa 1.4s ease-in-out infinite}' +
      '.trmp.is-ok .trmp-anel{animation:none;border-color:var(--bs-success,#198754)}' +
      '.trmp.is-ok .trmp-icone i{color:var(--bs-success,#198754);animation:trmpPop .4s ease-out}' +
      '.trmp.is-aviso .trmp-anel{animation:none;border-color:var(--bs-warning,#ffc107)}' +
      '.trmp.is-aviso .trmp-icone i{color:var(--bs-warning,#e0a800);animation:trmpPop .4s ease-out}' +
      '.trmp.is-erro .trmp-anel{animation:none;border-color:var(--bs-danger,#dc3545)}' +
      '.trmp.is-erro .trmp-icone i{color:var(--bs-danger,#dc3545);animation:trmpPop .4s ease-out}' +
      '.trmp-titulo{font-size:1.05rem;font-weight:600;margin:0 0 4px}' +
      '.trmp-texto{font-size:.88rem;color:var(--bs-secondary-color,#6c757d);margin:0 0 14px;min-height:1.3em;word-break:break-word}' +
      '.trmp-barra{height:8px;border-radius:8px;background:var(--bs-secondary-bg,#e9ecef);overflow:hidden;position:relative}' +
      '.trmp-barra span{position:absolute;left:0;top:0;bottom:0;width:0;border-radius:8px;background:var(--bs-primary,#0d6efd);transition:width .3s}' +
      '.trmp-barra.is-indef span{width:35%;animation:trmpIndef 1.2s ease-in-out infinite}' +
      '.trmp.is-ok .trmp-barra span{background:var(--bs-success,#198754)}' +
      '.trmp.is-aviso .trmp-barra span{background:var(--bs-warning,#ffc107)}' +
      '.trmp.is-erro .trmp-barra span{background:var(--bs-danger,#dc3545)}' +
      '.trmp-cont{display:flex;justify-content:space-between;gap:8px;font-size:.8rem;margin-top:6px;color:var(--bs-secondary-color,#6c757d)}' +
      '.trmp-cont b{color:var(--bs-body-color,#212529)}' +
      '.trmp-log{list-style:none;margin:12px 0 0;padding:6px;max-height:180px;overflow:auto;text-align:left;font-size:.82rem;' +
        'border:1px solid var(--bs-border-color,#dee2e6);border-radius:8px}' +
      '.trmp-log li{display:flex;align-items:center;gap:8px;padding:3px 4px;animation:trmpEntra .25s ease-out}' +
      '.trmp-log li span{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}' +
      '.trmp-log li small{color:var(--bs-danger,#dc3545);white-space:normal}' +
      '.trmp-log .bi-check-circle-fill{color:var(--bs-success,#198754)}' +
      '.trmp-log .bi-x-circle-fill,.trmp-log .bi-dash-circle-fill{color:var(--bs-danger,#dc3545)}' +
      '.trmp-log .bi-arrow-repeat{color:var(--bs-primary,#0d6efd);display:inline-block;animation:trmpGira 1s linear infinite}' +
      '.trmp-pe{display:flex;justify-content:center;gap:8px;margin-top:16px}' +
      '@keyframes trmpGira{to{transform:rotate(360deg)}}' +
      '@keyframes trmpVoa{0%,100%{transform:translate(0,0) rotate(0)}50%{transform:translate(3px,-5px) rotate(-8deg)}}' +
      '@keyframes trmpPop{0%{transform:scale(.4);opacity:0}70%{transform:scale(1.15)}100%{transform:scale(1);opacity:1}}' +
      '@keyframes trmpIndef{0%{left:-35%}100%{left:100%}}' +
      '@keyframes trmpEntra{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:none}}' +
      '@media (prefers-reduced-motion:reduce){.trmp *{animation-duration:0s!important;animation-iteration-count:1!important}}';

    var el = null, refs = {}, ocupado = false, interromper = false;

    function montar() {
      if (el) { return; }
      var st = document.createElement('style');
      st.textContent = css;
      document.head.appendChild(st);

      el = document.createElement('div');
      el.className = 'trmp';
      el.hidden = true;
      el.setAttribute('role', 'dialog');
      el.setAttribute('aria-modal', 'true');
      el.setAttribute('aria-live', 'polite');
      el.innerHTML =
        '<div class="trmp-caixa">' +
          '<div class="trmp-icone"><span class="trmp-anel"></span><i class="bi bi-envelope-paper"></i></div>' +
          '<p class="trmp-titulo"></p>' +
          '<p class="trmp-texto"></p>' +
          '<div class="trmp-barra"><span></span></div>' +
          '<div class="trmp-cont" hidden><span class="trmp-cont-a"></span><span class="trmp-cont-b"></span></div>' +
          '<ul class="trmp-log" hidden></ul>' +
          '<div class="trmp-pe">' +
            '<button type="button" class="btn btn-sm tr-btn-ghost trmp-parar" hidden>Interromper</button>' +
            '<button type="button" class="btn btn-sm tr-btn-primaria trmp-fechar" hidden>Fechar</button>' +
          '</div>' +
        '</div>';
      document.body.appendChild(el);

      refs.icone  = el.querySelector('.trmp-icone i');
      refs.titulo = el.querySelector('.trmp-titulo');
      refs.texto  = el.querySelector('.trmp-texto');
      refs.barra  = el.querySelector('.trmp-barra');
      refs.fill   = el.querySelector('.trmp-barra span');
      refs.cont   = el.querySelector('.trmp-cont');
      refs.contA  = el.querySelector('.trmp-cont-a');
      refs.contB  = el.querySelector('.trmp-cont-b');
      refs.log    = el.querySelector('.trmp-log');
      refs.parar  = el.querySelector('.trmp-parar');
      refs.fechar = el.querySelector('.trmp-fechar');

      refs.parar.addEventListener('click', function () {
        interromper = true;
        refs.parar.disabled = true;
        refs.parar.textContent = 'Interrompendo…';
      });

      window.addEventListener('beforeunload', function (e) {
        if (ocupado) { e.preventDefault(); e.returnValue = ''; }
      });
    }

    function estado(tipo) {
      el.classList.remove('is-ok', 'is-aviso', 'is-erro');
      var icone = 'bi-envelope-paper';
      if (tipo === 'ok')    { el.classList.add('is-ok');    icone = 'bi-check-circle-fill'; }
      if (tipo === 'aviso') { el.classList.add('is-aviso'); icone = 'bi-exclamation-circle-fill'; }
      if (tipo === 'erro')  { el.classList.add('is-erro');  icone = 'bi-x-circle-fill'; }
      refs.icone.className = 'bi ' + icone;
    }

    function abrir(titulo, texto) {
      montar();
      ocupado = true;
      interromper = false;
      estado('');
      refs.titulo.textContent = titulo || 'Enviando…';
      refs.texto.textContent = texto || '';
      refs.barra.classList.add('is-indef');
      refs.fill.style.width = '';
      refs.cont.hidden = true;
      refs.log.hidden = true;
      refs.log.innerHTML = '';
      refs.parar.hidden = true;
      refs.parar.disabled = false;
      refs.parar.textContent = 'Interromper';
      refs.fechar.hidden = true;
      refs.fechar.onclick = null;
      el.hidden = false;
      requestAnimationFrame(function () { el.classList.add('is-on'); });
    }

    function texto(t) { if (el) { refs.texto.textContent = t || ''; } }

    function fechar() {
      ocupado = false;
      if (!el) { return; }
      el.classList.remove('is-on');
      setTimeout(function () { el.hidden = true; }, 200);
    }

    function concluir(tipo, titulo, txt, aoFechar, autoFechar) {
      ocupado = false;
      estado(tipo);
      refs.titulo.textContent = titulo;
      refs.texto.textContent = txt || '';
      refs.barra.classList.remove('is-indef');
      refs.fill.style.width = '100%';
      refs.parar.hidden = true;
      refs.fechar.hidden = false;
      refs.fechar.onclick = function () { fechar(); if (aoFechar) { aoFechar(); } };
      refs.fechar.focus();
      if (autoFechar) {
        setTimeout(function () { if (!el.hidden) { refs.fechar.onclick(); } }, autoFechar);
      }
    }

    function itemLog(nome) {
      var li = document.createElement('li');
      li.innerHTML = '<i class="bi bi-arrow-repeat"></i><span></span>';
      li.querySelector('span').textContent = nome;
      refs.log.appendChild(li);
      refs.log.scrollTop = refs.log.scrollHeight;
      return li;
    }

    function marcarLog(li, ok, msg) {
      li.querySelector('i').className = 'bi ' + (ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill');
      if (!ok && msg) {
        var sm = document.createElement('small');
        sm.textContent = msg;
        li.querySelector('span').title = msg;
        li.appendChild(sm);
      }
    }

    function lote(itens, opts) {
      opts = opts || {};
      abrir(opts.titulo || 'Enviando links', 'Preparando…');
      var total = itens.length, i = 0, ok = 0, falhas = 0;

      refs.barra.classList.remove('is-indef');
      refs.fill.style.width = '0%';
      refs.cont.hidden = false;
      refs.log.hidden = false;
      refs.parar.hidden = total < 2;

      (opts.extrasLog || []).forEach(function (linha) {
        var li = document.createElement('li');
        li.innerHTML = '<i class="bi bi-dash-circle-fill"></i><span></span>';
        li.querySelector('span').textContent = linha;
        refs.log.appendChild(li);
      });

      function placar() {
        refs.contA.innerHTML = '<b>' + i + '</b> de <b>' + total + '</b>';
        refs.contB.textContent = ok + ' enviado(s)' + (falhas ? ' · ' + falhas + ' falha(s)' : '');
        refs.fill.style.width = (total ? Math.round(i / total * 100) : 100) + '%';
      }
      placar();

      return new Promise(function (resolve) {
        (function proximo() {
          if (i >= total || interromper) {
            resolve({ ok: ok, falhas: falhas, total: total, interrompido: interromper && i < total });
            return;
          }
          var item = itens[i];
          refs.texto.textContent = 'Enviando para ' + item.nome + '…';
          var li = itemLog(item.nome);

          window.trApi('insc_enviar', { id: parseInt(item.id, 10) })
            .then(function () { ok++; marcarLog(li, true); })
            .catch(function (e) { falhas++; marcarLog(li, false, e && e.message); })
            .then(function () { i++; placar(); proximo(); });
        })();
      });
    }

    function fimLote(r, extraTexto) {
      var partes = [r.ok + ' de ' + r.total + ' link(s) enviado(s)'];
      if (r.falhas) { partes.push(r.falhas + ' falha(s)'); }
      if (r.interrompido) { partes.push('envio interrompido'); }
      var txt = partes.join(' · ') + (extraTexto ? '. ' + extraTexto : '.');

      var tipo = r.falhas || r.interrompido || extraTexto ? (r.ok ? 'aviso' : 'erro') : 'ok';
      var titulo = tipo === 'ok' ? 'Envio concluído' : (r.ok ? 'Envio concluído com pendências' : 'Nenhum link enviado');
      refs.texto.textContent = '';
      concluir(tipo, titulo, txt, function () { if (r.ok) { recarregar(200); } }, tipo === 'ok' ? 1800 : 0);
    }

    return { abrir: abrir, texto: texto, fechar: fechar, concluir: concluir, lote: lote, fimLote: fimLote };
  })();

  var escolhido = null;
  var buscaTimer = null;

  function abrirNova() {
    escolhido = null;
    $('mtBusca').value = '';
    $('mtPrazo').value = '';
    $('mtEnviar').checked = true;
    $('mtSelecionado').hidden = true;
    $('mtSalvar').disabled = true;
    $('mtResultados').innerHTML =
      '<p class="trm-resultados-vazio">Digite ao menos duas letras para buscar.</p>';
    window.trAbrirModal('modalMatricula');
    setTimeout(function () { $('mtBusca').focus(); }, 80);
  }

  if ($('btnNovaMatricula'))      { $('btnNovaMatricula').addEventListener('click', abrirNova); }
  if ($('btnNovaMatriculaVazio')) { $('btnNovaMatriculaVazio').addEventListener('click', abrirNova); }

  function pintarResultados(itens) {
    var alvo = $('mtResultados');
    if (!itens.length) {
      alvo.innerHTML = '<p class="trm-resultados-vazio">Nenhum colaborador ativo encontrado.</p>';
      return;
    }
    alvo.innerHTML = '';
    itens.forEach(function (c) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'trm-resultado' + (c.matriculado ? ' is-matriculado' : '');
      b.disabled = !!c.matriculado;
      b.innerHTML =
        '<span class="trm-resultado-mat"></span>' +
        '<span class="trm-resultado-txt"><strong></strong><small></small></span>' +
        (c.matriculado ? '<em>já matriculado</em>' : '<i class="bi bi-plus-circle"></i>');
      b.querySelector('.trm-resultado-mat').textContent = '#' + c.matricula;
      b.querySelector('strong').textContent = c.nome;
      b.querySelector('small').textContent =
        [c.cargo, c.filial, c.email].filter(Boolean).join(' · ') || 'sem cargo ou filial no cadastro';
      b.addEventListener('click', function () { selecionar(c); });
      alvo.appendChild(b);
    });
  }

  function selecionar(c) {
    escolhido = c;
    $('mtSelecionado').hidden = false;
    $('mtSelNome').textContent = '#' + c.matricula + ' — ' + c.nome;
    $('mtSelInfo').textContent =
      [c.cargo, c.filial].filter(Boolean).join(' · ') +
      (c.email ? '' : ' — sem e-mail no cadastro, o link terá de ser copiado');
    $('mtSalvar').disabled = false;
    $('mtResultados').innerHTML = '';
    $('mtBusca').value = '';
  }

  if ($('mtLimpar')) {
    $('mtLimpar').addEventListener('click', function () {
      escolhido = null;
      $('mtSelecionado').hidden = true;
      $('mtSalvar').disabled = true;
      $('mtBusca').focus();
    });
  }

  if ($('mtBusca')) {
    $('mtBusca').addEventListener('input', function () {
      var termo = this.value.trim();
      clearTimeout(buscaTimer);

      if (termo.length < 2) {
        $('mtResultados').innerHTML =
          '<p class="trm-resultados-vazio">Digite ao menos duas letras para buscar.</p>';
        return;
      }

      $('mtResultados').innerHTML = '<p class="trm-resultados-vazio">Buscando…</p>';
      buscaTimer = setTimeout(function () {
        window.trApi('insc_buscar_colab', { apresentacao_id: APRES, termo: termo })
          .then(function (j) { pintarResultados(j.itens || []); })
          .catch(function (e) {
            $('mtResultados').innerHTML = '<p class="trm-resultados-vazio">' + e.message + '</p>';
          });
      }, 280);
    });
  }

  if ($('mtSalvar')) {
    $('mtSalvar').addEventListener('click', function () {
      if (!escolhido) { return; }
      var btn = this;
      var enviar = $('mtEnviar').checked && !!escolhido.email;
      btn.disabled = true;

      if (enviar) {
        window.trFecharModal('modalMatricula');
        Envio.abrir('Matriculando e enviando o link', escolhido.nome + ' · ' + escolhido.email);
      }

      window.trApi('insc_criar', {
        apresentacao_id: APRES,
        colaborador_id: escolhido.id,
        prazo: $('mtPrazo').value || '',
        enviar: $('mtEnviar').checked ? 1 : 0
      }).then(function (j) {
        if (j.link && !j.enviado) { copiar(j.link, 'Link individual copiado.'); }
        if (enviar) {
          if (j.enviado) {
            Envio.concluir('ok', 'Link enviado', j.msg, function () { recarregar(200); }, 1600);
          } else {
            Envio.concluir('aviso', 'Matriculado, mas o e-mail não foi enviado',
              'O link individual foi copiado para entregar manualmente.', function () { recarregar(200); });
          }
          return;
        }
        window.trToast(j.msg);
        recarregar();
      }).catch(function (e) {
        btn.disabled = false;
        if (enviar) {
          Envio.concluir('erro', 'Não foi possível matricular', e.message, function () {
            window.trAbrirModal('modalMatricula');
          });
          return;
        }
        window.trToast(e.message, 'erro');
      });
    });
  }

  if ($('btnLote')) {
    $('btnLote').addEventListener('click', function () {
      $('loteErros').hidden = true;
      window.trAbrirModal('modalLote');
    });
  }

  if ($('loteConfirmar')) {
    $('loteConfirmar').addEventListener('click', function () {
      var btn = this;
      var texto = ($('loteTexto').value || '').trim();
      if (!texto) {
        window.trToast('Informe as matrículas separadas por vírgula.', 'aviso');
        return;
      }

      var enviar = $('loteEnviar').checked;
      btn.disabled = true;
      btn.textContent = 'Matriculando…';
      $('loteErros').hidden = true;

      function mostrarErros(erros) {
        var cx = $('loteErros');
        cx.hidden = false;
        cx.innerHTML = '<b>' + erros.length + ' não puderam ser matriculadas:</b><ul></ul>';
        var ul = cx.querySelector('ul');
        erros.forEach(function (e) {
          var li = document.createElement('li');
          li.textContent = e;
          ul.appendChild(li);
        });
      }

      function liberar() {
        btn.disabled = false;
        btn.textContent = 'Matricular';
      }

      if (enviar) {
        window.trFecharModal('modalLote');
        Envio.abrir('Matriculando em lote', 'Validando as matrículas no cadastro…');
      }

      window.trApi('insc_lote', {
        apresentacao_id: APRES,
        texto: texto,
        prazo: $('lotePrazo').value || '',
        enviar: 0
      }).then(function (j) {
        var erros = j.erros || [];
        var novos = j.novos || [];

        if (!enviar) {
          window.trToast(j.msg);
          if (erros.length) {
            mostrarErros(erros);
            liberar();
            if (j.criados > 0) { setTimeout(recarregar, 6000); }
          } else {
            recarregar(1200);
          }
          return;
        }

        var comEmail = novos.filter(function (n) { return !!n.email; });
        var semEmail = novos.length - comEmail.length;
        var avisos = erros.slice();
        if (semEmail) { avisos.push(semEmail + ' matriculado(s) sem e-mail: copie o link na lista.'); }

        if (!comEmail.length) {
          liberar();
          if (!j.criados) {
            Envio.fechar();
            if (erros.length) { mostrarErros(erros); }
            window.trAbrirModal('modalLote');
            window.trToast(j.msg, 'aviso');
            return;
          }
          Envio.concluir(avisos.length ? 'aviso' : 'ok', j.criados + ' matrícula(s) criada(s)',
            avisos.length ? avisos.join(' ') : 'Nenhum link a enviar.', function () { recarregar(200); });
          return;
        }

        Envio.lote(comEmail, {
          titulo: j.criados + ' matriculado(s) · enviando links',
          extrasLog: erros
        }).then(function (r) {
          liberar();
          var extra = [];
          if (erros.length) { extra.push(erros.length + ' matrícula(s) não entraram (veja a lista)'); }
          if (semEmail)     { extra.push(semEmail + ' sem e-mail'); }
          Envio.fimLote(r, extra.join(' · '));
        });
      }).catch(function (e) {
        liberar();
        if (enviar) {
          Envio.concluir('erro', 'Não foi possível matricular', e.message, function () {
            window.trAbrirModal('modalLote');
          });
          return;
        }
        window.trToast(e.message, 'erro');
      });
    });
  }

  function abrirPrazo(id, nome, prazo) {
    $('pzId').value = id;
    $('pzIds').value = '';
    $('pzNome').textContent = nome;
    $('pzData').value = prazo || '';
    window.trAbrirModal('modalPrazo');
  }

  function abrirPrazoLote(ids) {
    $('pzId').value = 0;
    $('pzIds').value = ids.join(',');
    $('pzNome').textContent = 'O novo prazo será aplicado a ' + ids.length + ' colaborador(es) selecionado(s).';
    $('pzData').value = '';
    window.trAbrirModal('modalPrazo');
  }

  if ($('pzSalvar')) {
    $('pzSalvar').addEventListener('click', function () {
      var btn = this;
      var lote = $('pzIds').value
        ? $('pzIds').value.split(',').map(function (v) { return parseInt(v, 10); }).filter(Boolean)
        : [];
      btn.disabled = true;
      (lote.length
        ? window.trApi('insc_prazo_lote', { apresentacao_id: APRES, ids: lote, prazo: $('pzData').value || '' })
        : window.trApi('insc_prazo', {
            id: parseInt($('pzId').value, 10) || 0,
            prazo: $('pzData').value || ''
          })
      ).then(function (j) {
        window.trToast(j.msg);
        recarregar(900);
      }).catch(function (e) {
        btn.disabled = false;
        window.trToast(e.message, 'erro');
      });
    });
  }

  function abrirExterno(id, nome) {
    $('exId').value = id;
    $('exNome').textContent = nome || '';
    $('exData').value = new Date().toISOString().slice(0, 10);
    $('exObs').value = '';
    $('exArquivo').value = '';
    window.trAbrirModal('modalExterno');
  }

  if ($('exSalvar')) {
    $('exSalvar').addEventListener('click', function () {
      var btn = this;
      var extras = {};
      if ($('exArquivo').files[0]) { extras.arquivo = $('exArquivo').files[0]; }
      btn.disabled = true;
      window.trApi('insc_marcar_externo', {
        id: parseInt($('exId').value, 10) || 0,
        data_conclusao: $('exData').value,
        observacao: $('exObs').value
      }, extras).then(function (j) {
        window.trToast(j.msg);
        window.trFecharModal('modalExterno');
        recarregar();
      }).catch(function (e) {
        btn.disabled = false;
        window.trToast(e.message, 'erro');
      });
    });
  }

  if ($('btnEnviarPendentes')) {
    $('btnEnviarPendentes').addEventListener('click', function () {
      var btn = this;
      window.trConfirmar({
        titulo: 'Enviar links pendentes',
        texto: 'Serão enviados os links individuais de quem ainda não recebeu o e-mail. Continuar?',
        confirmar: 'Enviar'
      }).then(function (sim) {
        if (!sim) { return; }
        btn.disabled = true;
        Envio.abrir('Enviar links pendentes', 'Buscando quem ainda não recebeu…');

        window.trApi('insc_pendentes_envio', { apresentacao_id: APRES })
          .then(function (j) {
            var itens = j.itens || [];
            if (!itens.length) {
              btn.disabled = false;
              Envio.concluir('ok', 'Nada a enviar', 'Todos os links já foram enviados.', null, 1800);
              return;
            }
            return Envio.lote(itens, { titulo: 'Enviando links pendentes' }).then(function (r) {
              btn.disabled = false;
              Envio.fimLote(r);
            });
          })
          .catch(function (e) {
            btn.disabled = false;
            Envio.concluir('erro', 'Não foi possível enviar', e.message);
          });
      });
    });
  }

  (function () {
    var $todos = $('trmSelTodos');
    var $barra = $('trmBarraSel');
    var $btn   = $('trmReenviarSel');
    if (!$barra || !$btn) { return; }

    function itens()    { return Array.prototype.slice.call(document.querySelectorAll('.trm-sel:not(:disabled)')); }
    function marcados() { return itens().filter(function (c) { return c.checked; }); }

    function atualizar() {
      var n = marcados().length, tot = itens().length;
      $('trmSelQtd').textContent = n;
      $barra.hidden = n === 0;
      $barra.style.display = n === 0 ? 'none' : 'flex';
      if ($todos) {
        $todos.checked = tot > 0 && n === tot;
        $todos.indeterminate = n > 0 && n < tot;
      }
    }

    document.addEventListener('change', function (e) {
      if ($todos && e.target === $todos) {
        itens().forEach(function (c) { c.checked = $todos.checked; });
        atualizar();
      } else if (e.target.classList && e.target.classList.contains('trm-sel')) {
        atualizar();
      }
    });

    if ($('trmSelLimpar')) {
      $('trmSelLimpar').addEventListener('click', function () {
        itens().forEach(function (c) { c.checked = false; });
        atualizar();
      });
    }

    if ($('trmPrazoSel')) {
      $('trmPrazoSel').addEventListener('click', function () {
        var ids = marcados().map(function (c) { return parseInt(c.value, 10); });
        if (ids.length) { abrirPrazoLote(ids); }
      });
    }

    $btn.addEventListener('click', function () {
      var todos = marcados();
      var sel = todos.filter(function (c) { return c.getAttribute('data-envio') === '1'; });
      if (!sel.length) {
        window.trToast('Nenhum dos selecionados pode receber o link (sem e-mail ou cancelado).', 'aviso');
        return;
      }
      var ignorados = todos.length - sel.length;

      window.trConfirmar({
        titulo: 'Reenviar links',
        texto: 'Reenviar o link por e-mail para ' + sel.length + ' colaborador(es)?' +
               (ignorados ? ' ' + ignorados + ' selecionado(s) serão ignorados (sem e-mail ou cancelados).' : ''),
        confirmar: 'Enviar'
      }).then(function (sim) {
        if (!sim) { return; }
        $btn.disabled = true;

        var lista = sel.map(function (c) {
          var forte = c.closest('tr').querySelector('.trm-pessoa-txt strong');
          return { id: c.value, nome: forte ? forte.textContent : '#' + c.value };
        });

        Envio.lote(lista, { titulo: 'Reenviando links' }).then(function (r) {
          $btn.disabled = false;
          Envio.fimLote(r);
        });
      });
    });

    atualizar();
  })();

  function copiar(texto, msg) {
    var p = navigator.clipboard ? navigator.clipboard.writeText(texto) : Promise.reject();
    p.then(function () {
      window.trToast(msg || 'Link copiado.');
    }).catch(function () {
      window.prompt('Copie o link individual (Ctrl+C):', texto);
    });
  }

  var menuAberto = null;

  function fecharMenus() {
    if (menuAberto) {
      var dono = menuAberto.__dono;
      menuAberto.classList.remove('is-solto');
      menuAberto.removeAttribute('style');
      if (dono) {
        dono.classList.remove('is-aberto');
        dono.appendChild(menuAberto);
      }
      menuAberto = null;
    }
    Array.prototype.forEach.call(document.querySelectorAll('.trm-menu.is-aberto'), function (m) {
      m.classList.remove('is-aberto');
    });
  }

  function abrirMenu(menu) {
    var lista = menu.querySelector('.trm-menu-lista');
    var botao = menu.querySelector('.trm-menu-abrir');
    if (!lista || !botao) { return; }

    lista.__dono = menu;
    menu.classList.add('is-aberto');

    lista.style.visibility = 'hidden';
    lista.style.top = '0px';
    lista.style.left = '0px';
    lista.classList.add('is-solto');
    document.body.appendChild(lista);

    var r = botao.getBoundingClientRect();
    var alt = lista.offsetHeight;
    var larg = lista.offsetWidth;

    var topo = r.bottom + 5;
    if (topo + alt > window.innerHeight - 10) { topo = Math.max(10, r.top - alt - 5); }

    var esq = Math.min(
      Math.max(10, r.right - larg),
      Math.max(10, window.innerWidth - larg - 10)
    );

    lista.style.top = Math.round(topo) + 'px';
    lista.style.left = Math.round(esq) + 'px';
    lista.style.visibility = '';
    menuAberto = lista;
  }

  window.addEventListener('resize', fecharMenus);
  window.addEventListener('scroll', fecharMenus, true);

  document.addEventListener('click', function (ev) {
    var el;

    el = ev.target.closest('.trm-menu-abrir');
    if (el) {
      ev.stopPropagation();
      var menu = el.closest('.trm-menu');
      var estava = menu.classList.contains('is-aberto');
      fecharMenus();
      if (!estava) { abrirMenu(menu); }
      return;
    }

    if (!ev.target.closest('.trm-menu-lista')) { fecharMenus(); }

    el = ev.target.closest('.trm-copiar');
    if (el) { copiar(el.getAttribute('data-link')); return; }

    el = ev.target.closest('.trm-enviar');
    if (el) {
      var email = el.getAttribute('data-email');
      if (!email) {
        window.trToast('Esta matrícula não tem e-mail. Use "copiar link" e entregue manualmente.', 'aviso');
        return;
      }
      var botaoEnv = el;
      var linha = el.closest('tr');
      var nomeEnv = linha && linha.querySelector('.trm-pessoa-txt strong')
        ? linha.querySelector('.trm-pessoa-txt strong').textContent : '';
      botaoEnv.disabled = true;
      Envio.abrir('Enviando link', (nomeEnv ? nomeEnv + ' · ' : '') + email);
      window.trApi('insc_enviar', { id: parseInt(el.getAttribute('data-id'), 10) })
        .then(function (j) {
          Envio.concluir('ok', 'Link enviado', j.msg, function () { recarregar(200); }, 1500);
        })
        .catch(function (e) {
          botaoEnv.disabled = false;
          Envio.concluir('erro', 'Não foi possível enviar', e.message);
        });
      return;
    }

    el = ev.target.closest('.trm-menu-lista [data-acao]');
    if (!el) { return; }

    fecharMenus();
    var acao = el.getAttribute('data-acao');
    var inscId = parseInt(el.getAttribute('data-id'), 10);

    if (acao === 'prazo') {
      abrirPrazo(inscId, el.getAttribute('data-nome'), el.getAttribute('data-prazo'));
      return;
    }

    if (acao === 'externo') {
      abrirExterno(inscId, el.getAttribute('data-nome'));
      return;
    }

    if (acao === 'sincronizar') {
      window.trApi('insc_sincronizar', { id: inscId })
        .then(function (j) { window.trToast(j.msg); recarregar(900); })
        .catch(function (e) { window.trToast(e.message, 'erro'); });
      return;
    }

    if (acao === 'token') {
      window.trConfirmar({
        titulo: 'Gerar novo link',
        texto: 'O link atual deixará de funcionar imediatamente. Use isto quando o link tiver sido repassado indevidamente.',
        confirmar: 'Gerar novo link'
      }).then(function (sim) {
        if (!sim) { return; }
        window.trApi('insc_token', { id: inscId })
          .then(function (j) { copiar(j.link, j.msg); recarregar(2200); })
          .catch(function (e) { window.trToast(e.message, 'erro'); });
      });
      return;
    }

    if (acao === 'reiniciar') {
      window.trConfirmar({
        titulo: 'Zerar progresso',
        texto: 'Todo o progresso registrado será apagado e o colaborador recomeçará do início. O link continua o mesmo.',
        confirmar: 'Zerar progresso',
        perigo: true
      }).then(function (sim) {
        if (!sim) { return; }
        window.trApi('insc_reiniciar', { id: inscId })
          .then(function (j) { window.trToast(j.msg); recarregar(1200); })
          .catch(function (e) { window.trToast(e.message, 'erro'); });
      });
      return;
    }

    if (acao === 'cancelar' || acao === 'reativar') {
      var cancelar = acao === 'cancelar';
      window.trConfirmar({
        titulo: cancelar ? 'Cancelar acesso' : 'Reativar matrícula',
        texto: cancelar
          ? 'O link individual deixará de funcionar. O progresso registrado é mantido.'
          : 'O colaborador volta a ter acesso pelo mesmo link.',
        confirmar: cancelar ? 'Cancelar acesso' : 'Reativar',
        perigo: cancelar
      }).then(function (sim) {
        if (!sim) { return; }
        window.trApi('insc_status', { id: inscId, status: cancelar ? 'cancelado' : 'reativar' })
          .then(function (j) { window.trToast(j.msg); recarregar(1200); })
          .catch(function (e) { window.trToast(e.message, 'erro'); });
      });
      return;
    }

    if (acao === 'prova_liberar') {
      window.trConfirmar({
        titulo: 'Liberar nova tentativa',
        texto: 'O contador de tentativas da prova será zerado e o colaborador poderá refazê-la.',
        confirmar: 'Liberar'
      }).then(function (sim) {
        if (!sim) { return; }
        window.trApi('prova_liberar', { id: inscId })
          .then(function (j) { window.trToast(j.msg); recarregar(1200); })
          .catch(function (e) { window.trToast(e.message, 'erro'); });
      });
      return;
    }

    if (acao === 'excluir') {
      window.trConfirmar({
        titulo: 'Excluir matrícula',
        texto: 'A matrícula de ' + el.getAttribute('data-nome') +
               ' será removida junto com todo o histórico de progresso. Esta ação não pode ser desfeita.',
        confirmar: 'Excluir',
        perigo: true
      }).then(function (sim) {
        if (!sim) { return; }
        window.trApi('insc_excluir', { id: inscId })
          .then(function (j) { window.trToast(j.msg); recarregar(1000); })
          .catch(function (e) { window.trToast(e.message, 'erro'); });
      });
    }
  });
})();
