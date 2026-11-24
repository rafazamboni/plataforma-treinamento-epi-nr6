(function () {
  'use strict';

  function $(id) { return document.getElementById(id); }

  var cpf = $('poCpf');
  if (cpf) {
    var formatar = function (v) {
      v = (v || '').replace(/\D+/g, '').slice(0, 11);
      if (v.length > 9) { return v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4'); }
      if (v.length > 6) { return v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3'); }
      if (v.length > 3) { return v.replace(/(\d{3})(\d{1,3})/, '$1.$2'); }
      return v;
    };

    cpf.addEventListener('input', function () {
      var fim = cpf.selectionStart === cpf.value.length;
      cpf.value = formatar(cpf.value);
      if (fim) {
        try { cpf.setSelectionRange(cpf.value.length, cpf.value.length); } catch (e) {}
      }
    });

    cpf.value = formatar(cpf.value);
    if (!cpf.value) { try { cpf.focus(); } catch (e) {} }
  }

  var form = document.querySelector('.trpo-form');
  if (form) {
    form.addEventListener('submit', function () {
      var b = form.querySelector('button[type="submit"]');
      if (b) {
        b.disabled = true;
        b.innerHTML = '<i class="bi bi-hourglass-split"></i> Entrando…';
        setTimeout(function () { b.disabled = false; }, 8000);
      }
    });
  }

  var filtros = $('poFiltros');
  var lista = $('poLista');
  if (!filtros || !lista) { return; }

  var vazio = $('poSemFiltro');
  var cartoes = Array.prototype.slice.call(lista.querySelectorAll('.trpo-card'));

  function aplicar(f) {
    var visiveis = 0;
    cartoes.forEach(function (c) {
      var g = c.getAttribute('data-grupo');
      var mostra = (f === 'todos') || (g === f);
      c.hidden = !mostra;
      if (mostra) { visiveis++; }
    });
    if (vazio) { vazio.hidden = visiveis > 0; }
  }

  filtros.addEventListener('click', function (ev) {
    var b = ev.target.closest ? ev.target.closest('.trpo-filtro') : null;
    if (!b) { return; }
    Array.prototype.forEach.call(filtros.querySelectorAll('.trpo-filtro'), function (o) {
      o.classList.toggle('is-ativo', o === b);
    });
    aplicar(b.getAttribute('data-f') || 'todos');
  });
})();
