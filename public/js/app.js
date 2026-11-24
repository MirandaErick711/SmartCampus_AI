/* SmartCampus AI · comportamiento del navegador (sin dependencias).
   Todo el HTML lo genera Laravel (Blade); aquí solo hay interacción:
   cuadros (modales), menú móvil, avisos y confirmaciones. */
(function () {
  'use strict';

  var $ = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

  /* ---------- Íconos (Lucide) ---------- */
  function iconos() {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      try { window.lucide.createIcons(); } catch (e) { /* sin íconos no pasa nada */ }
    }
  }

  /* ---------- Cuadros (modales) ---------- */
  function cerrarCuadros() {
    $$('.modal-back').forEach(function (m) { m.hidden = true; });
    document.body.style.overflow = '';
  }
  function abrirCuadro(nombre) {
    var cuadro = document.getElementById('modal-' + nombre);
    if (!cuadro) { return; }
    cerrarCuadros();
    cuadro.hidden = false;
    document.body.style.overflow = 'hidden';
    var primero = $('input:not([type=hidden]):not([type=checkbox]), select', cuadro);
    if (primero) { setTimeout(function () { primero.focus(); }, 60); }
  }

  document.addEventListener('click', function (e) {
    var abrir = e.target.closest('[data-abrir]');
    if (abrir) { e.preventDefault(); abrirCuadro(abrir.getAttribute('data-abrir')); return; }

    if (e.target.closest('[data-cerrar]')) { e.preventDefault(); cerrarCuadros(); return; }

    // Clic en el fondo oscuro (fuera del cuadro)
    if (e.target.classList && e.target.classList.contains('modal-back')) { cerrarCuadros(); }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { cerrarCuadros(); cerrarMenu(); }
  });

  /* ---------- Menú lateral en móvil ---------- */
  function cerrarMenu() {
    var lateral = document.getElementById('sidebar');
    var fondo = document.getElementById('overlay');
    if (lateral) { lateral.classList.remove('open'); }
    if (fondo) { fondo.classList.remove('show'); }
  }
  var burger = document.getElementById('burger');
  if (burger) {
    burger.addEventListener('click', function () {
      var lateral = document.getElementById('sidebar');
      var fondo = document.getElementById('overlay');
      if (lateral) { lateral.classList.add('open'); }
      if (fondo) { fondo.classList.add('show'); }
    });
  }
  var fondoMenu = document.getElementById('overlay');
  if (fondoMenu) { fondoMenu.addEventListener('click', cerrarMenu); }

  /* ---------- Formularios ---------- */
  // Selectores e interruptores que envían su formulario al cambiar
  document.addEventListener('change', function (e) {
    var el = e.target;
    if (el.matches && el.matches('[data-autosubmit]') && el.form) {
      if (typeof el.form.requestSubmit === 'function') { el.form.requestSubmit(); } else { el.form.submit(); }
    }
  });

  document.addEventListener('submit', function (e) {
    var form = e.target;

    // Confirmación antes de acciones delicadas
    var pregunta = form.getAttribute('data-confirmar');
    if (pregunta && !window.confirm(pregunta)) { e.preventDefault(); return; }

    // Evita enviar dos veces el mismo formulario
    setTimeout(function () {
      $$('button[type=submit]', form).forEach(function (b) { b.disabled = true; });
    }, 0);
  });

  // Si el usuario vuelve con el botón "Atrás", los botones deben estar activos otra vez
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) { $$('button[type=submit]').forEach(function (b) { b.disabled = false; }); }
  });

  /* ---------- Avisos (toasts) ---------- */
  $$('#toasts .toast').forEach(function (t) {
    setTimeout(function () { t.style.transition = 'opacity .4s'; t.style.opacity = '0'; }, 7000);
    setTimeout(function () { if (t.parentNode) { t.parentNode.removeChild(t); } }, 7500);
  });

  /* ---------- Utilidades de DOM (todo con textContent: nada de HTML armado con datos) ---------- */
  function leerJson(id) {
    var nodo = document.getElementById(id);
    if (!nodo) { return null; }
    try { return JSON.parse(nodo.textContent); } catch (e) { return null; }
  }
  function el(etiqueta, clase, texto) {
    var n = document.createElement(etiqueta);
    if (clase) { n.className = clase; }
    if (texto !== undefined && texto !== null) { n.textContent = texto; }
    return n;
  }
  function icono(nombre) {
    var i = document.createElement('i');
    i.setAttribute('data-lucide', nombre);
    return i;
  }
  function llenar(select, items, vacio, valorDe, textoDe) {
    select.innerHTML = '';
    select.appendChild(new Option(vacio, ''));
    items.forEach(function (it, n) { select.appendChild(new Option(textoDe(it, n), valorDe(it, n))); });
  }

  /* ---------- Categoría → subcategorías (reportar y evaluar) ---------- */
  function selectoresCategoria() {
    var cats = leerJson('datos-categorias');
    if (!cats) { return; }
    function subs(selCat, conservar) {
      var destino = document.getElementById(selCat.getAttribute('data-cat'));
      if (!destino) { return; }
      var elegida = conservar !== undefined ? conservar : '';
      var cat = cats.filter(function (c) { return String(c.id) === selCat.value; })[0];
      llenar(destino, cat ? cat.subs : [], '—', function (s) { return s.id; }, function (s) { return s.nombre; });
      destino.value = elegida;
    }
    $$('select[data-cat]').forEach(function (sel) {
      var destino = document.getElementById(sel.getAttribute('data-cat'));
      subs(sel, destino ? destino.getAttribute('data-elegida') || '' : '');
      sel.addEventListener('change', function () { subs(sel, ''); });
    });
  }

  /* ---------- Formulario "Reportar incidencia" ---------- */
  function formularioReporte() {
    var form = document.getElementById('form-reporte');
    if (!form) { return; }
    var arbol = leerJson('datos-ubicaciones') || [];
    var campus = $('#f-campus'), pab = $('#f-pab'), piso = $('#f-piso'), aula = $('#f-aula');

    function reiniciar(sel, vacio) { llenar(sel, [], vacio, function () { return ''; }, function () { return ''; }); sel.disabled = true; }
    function pabellones() { var c = arbol[campus.value]; return c ? c.pabellones : []; }
    function pisos() { var p = pabellones()[pab.value]; return p ? p.pisos : []; }

    llenar(campus, arbol, 'Seleccionar…', function (c, n) { return n; }, function (c) { return c.nombre; });
    campus.addEventListener('change', function () {
      reiniciar(piso, 'Seleccionar…'); reiniciar(aula, 'Seleccionar…');
      var lista = pabellones();
      llenar(pab, lista, 'Seleccionar…', function (p, n) { return n; }, function (p) { return p.nombre; });
      pab.disabled = lista.length === 0;
    });
    pab.addEventListener('change', function () {
      reiniciar(aula, 'Seleccionar…');
      var lista = pisos();
      llenar(piso, lista, 'Seleccionar…', function (p, n) { return n; }, function (p) { return 'Piso ' + p.piso; });
      piso.disabled = lista.length === 0;
    });
    piso.addEventListener('change', function () {
      var p = pisos()[piso.value];
      var lista = p ? p.lugares : [];
      llenar(aula, lista, 'Seleccionar…', function (l) { return l.id; }, function (l) { return l.nombre; });
      aula.disabled = lista.length === 0;
    });

    // Si el servidor devolvió el formulario con errores, se vuelve a elegir la ubicación
    var viejo = form.getAttribute('data-viejo-ubicacion');
    if (viejo) {
      arbol.forEach(function (c, ci) {
        c.pabellones.forEach(function (p, pi) {
          p.pisos.forEach(function (f, fi) {
            if (f.lugares.some(function (l) { return String(l.id) === viejo; })) {
              campus.value = ci; campus.dispatchEvent(new Event('change'));
              pab.value = pi; pab.dispatchEvent(new Event('change'));
              piso.value = fi; piso.dispatchEvent(new Event('change'));
              aula.value = viejo;
            }
          });
        });
      });
    }

    /* --- Foto: elegir, arrastrar, vista previa --- */
    var archivo = $('#f-foto'), caja = $('#foto-caja'), dz = $('#dz');
    var cajaInicial = caja.innerHTML;
    function quitarFoto() {
      archivo.value = '';
      caja.innerHTML = cajaInicial;
      dz = $('#dz'); enlazarDz(); iconos();
    }
    function mostrarFoto() {
      var f = archivo.files && archivo.files[0];
      if (!f) { return; }
      if (['image/jpeg', 'image/png', 'image/webp'].indexOf(f.type) === -1) { avisar('La imagen debe ser JPG, PNG o WEBP.'); archivo.value = ''; return; }
      if (f.size > 8 * 1024 * 1024) { avisar('La imagen supera los 8 MB.'); archivo.value = ''; return; }
      caja.innerHTML = '';
      var envoltorio = el('div', 'scan-wrap'); envoltorio.id = 'scan-wrap';
      var img = document.createElement('img'); img.alt = 'Evidencia de la incidencia'; img.src = URL.createObjectURL(f);
      var esquinas = el('div', 'scan-corners'); for (var k = 0; k < 4; k++) { esquinas.appendChild(document.createElement('i')); }
      var quitar = el('button', 'btn btn-sm btn-danger rm-img'); quitar.type = 'button';
      quitar.appendChild(icono('trash-2')); quitar.appendChild(document.createTextNode('Quitar'));
      quitar.addEventListener('click', quitarFoto);
      envoltorio.appendChild(img); envoltorio.appendChild(el('div', 'scan-line')); envoltorio.appendChild(esquinas); envoltorio.appendChild(quitar);
      caja.appendChild(envoltorio); iconos();
    }
    function enlazarDz() {
      if (!dz) { return; }
      dz.addEventListener('click', function () { archivo.click(); });
      dz.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); archivo.click(); } });
      dz.addEventListener('dragover', function (e) { e.preventDefault(); dz.classList.add('over'); });
      dz.addEventListener('dragleave', function () { dz.classList.remove('over'); });
      dz.addEventListener('drop', function (e) {
        e.preventDefault(); dz.classList.remove('over');
        if (e.dataTransfer && e.dataTransfer.files.length) { archivo.files = e.dataTransfer.files; mostrarFoto(); }
      });
    }
    archivo.addEventListener('change', mostrarFoto);
    enlazarDz();

    /* --- Análisis con IA (vista previa; no guarda nada) --- */
    var boton = $('#btn-analizar'), salida = $('#ia-resultado');
    function tarjetaIA(r) {
      var cont = el('div');
      var cab = el('div', 'conf-wrap');
      var anillo = el('div', 'conf-ring'); anillo.style.setProperty('--v', String(parseInt(r.confianza, 10) || 0));
      anillo.appendChild(el('span', '', (parseInt(r.confianza, 10) || 0) + '%'));
      var info = el('div'); info.style.minWidth = '0';
      var etiqueta = el('div', 'lbl', 'Nivel de confianza'); etiqueta.style.color = '#d9a6ff';
      var cat = el('div', '', r.categoria); cat.style.cssText = 'font-size:16px;font-weight:700;margin:2px 0';
      var sub = el('div', 'muted', r.subcategoria); sub.style.fontSize = '12.5px';
      var marcas = el('div'); marcas.style.cssText = 'margin-top:6px;display:flex;gap:6px;flex-wrap:wrap';
      marcas.appendChild(el('span', 'badge b-' + r.prioridad, r.prioridad));
      var ia = el('span', 'badge b-ai'); ia.appendChild(icono('brain-circuit')); ia.appendChild(document.createTextNode('Predicción IA')); marcas.appendChild(ia);
      [etiqueta, cat, sub, marcas].forEach(function (n) { info.appendChild(n); });
      cab.appendChild(anillo); cab.appendChild(info); cont.appendChild(cab);

      if ((r.hits || []).length || (r.objetos || []).length) {
        var chips = el('div'); chips.style.cssText = 'display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px';
        (r.hits || []).forEach(function (h) { var c = el('span', 'chip chip-p'); c.appendChild(icono('text-cursor')); c.appendChild(document.createTextNode(h)); chips.appendChild(c); });
        (r.objetos || []).forEach(function (o) { var c = el('span', 'chip chip-p'); c.appendChild(icono('scan-eye')); c.appendChild(document.createTextNode(o)); chips.appendChild(c); });
        cont.appendChild(chips);
      }
      var expl = el('div', 'expl');
      (r.explicacion || []).forEach(function (t, i) {
        var f = el('div'); f.appendChild(icono(['file-text', 'image', 'map-pin'][i] || 'info')); f.appendChild(el('span', '', t)); expl.appendChild(f);
      });
      cont.appendChild(expl);
      return cont;
    }
    function estadoBoton(analizando) {
      boton.disabled = analizando;
      boton.innerHTML = '';
      boton.appendChild(icono(analizando ? 'loader' : 'scan-search'));
      boton.appendChild(document.createTextNode(analizando ? 'Analizando…' : 'Analizar con SmartCampus AI'));
      iconos();
    }
    boton.addEventListener('click', function () {
      var titulo = $('#f-titulo').value.trim(), desc = $('#f-desc').value.trim();
      if (!titulo && !desc) { avisar('Escribe un título o descripción para analizar.'); $('#f-titulo').focus(); return; }
      estadoBoton(true);
      var tieneFoto = !!(archivo.files && archivo.files.length);
      var marco = $('#scan-wrap'); if (marco) { marco.classList.add('scanning'); }
      salida.innerHTML = '';
      var carga = el('div', 'ai-loading'); carga.appendChild(el('div', 'spinner'));
      var t1 = el('div', '', 'Procesando texto, imagen y contexto…'); t1.style.cssText = 'font-size:13px;font-weight:600'; carga.appendChild(t1);
      salida.appendChild(carga);

      var token = document.querySelector('meta[name="csrf-token"]');
      var pedido = fetch(form.getAttribute('data-analizar'), {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token ? token.content : '' },
        body: JSON.stringify({ titulo: titulo, descripcion: desc, ubicacion_id: aula.value || null, area_id: $('#f-area').value || null, tiene_foto: tieneFoto })
      }).then(function (r) { return r.json().then(function (d) { if (!r.ok) { throw new Error(d.message || 'No se pudo analizar.'); } return d; }); });
      var minimo = new Promise(function (ok) { setTimeout(ok, 1200); }); // que se aprecie el escaneo

      Promise.all([pedido, minimo]).then(function (res) {
        var r = res[0];
        $('#f-cat').value = r.categoria_id || '';
        $('#f-cat').dispatchEvent(new Event('change'));
        $('#f-sub').value = r.subcategoria_id || '';
        $('#f-prio').value = r.prioridad;
        salida.innerHTML = ''; salida.appendChild(tarjetaIA(r));
      }).catch(function (e) {
        salida.innerHTML = ''; salida.appendChild(el('div', 'muted', e.message || 'No se pudo analizar.'));
      }).then(function () {
        if (marco) { marco.classList.remove('scanning'); }
        estadoBoton(false); iconos();
      });
    });
  }

  /* ---------- Aviso rápido (toast) hecho en el navegador ---------- */
  function avisar(texto) {
    var cont = document.getElementById('toasts');
    if (!cont) { window.alert(texto); return; }
    var t = el('div', 'toast error'); t.setAttribute('role', 'status');
    t.appendChild(icono('alert-triangle')); t.appendChild(el('span', '', texto));
    cont.appendChild(t); iconos();
    setTimeout(function () { if (t.parentNode) { t.parentNode.removeChild(t); } }, 6000);
  }

  /* ---------- Campana de notificaciones ---------- */
  function campana() {
    var cont = document.getElementById('campana');
    if (!cont) { return; }
    var boton = $('#campana-btn'), cuenta = $('#campana-cuenta'), panel = $('#campana-panel');
    var tokenMeta = document.querySelector('meta[name="csrf-token"]');
    var iconos_ = { critica: ['siren', 'var(--r)'], asignacion: ['user-check', 'var(--c)'], estado: ['activity', 'var(--g)'], info: ['info', 'var(--muted)'] };

    function pintarCuenta(n) { cuenta.textContent = n > 99 ? '99+' : String(n); cuenta.hidden = !(n > 0); }
    function pedir(url, metodo) {
      return fetch(url, { method: metodo || 'GET', credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': tokenMeta ? tokenMeta.content : '' } })
        .then(function (r) { if (!r.ok) { throw new Error('http'); } return r.json(); });
    }
    function cargar() {
      return pedir(cont.getAttribute('data-url')).then(function (d) { pintarCuenta(d.no_leidas); return d; });
    }
    function pintarLista(d) {
      panel.innerHTML = '';
      var cab = el('div', 'notif-h'); cab.appendChild(el('b', '', 'Notificaciones'));
      var todas = el('button', 'btn btn-sm'); todas.type = 'button';
      todas.appendChild(icono('check-check')); todas.appendChild(document.createTextNode('Marcar leídas'));
      todas.addEventListener('click', function () {
        pedir(cont.getAttribute('data-base') + '/leer-todas', 'POST').then(cargar).then(pintarLista).catch(function () {});
      });
      cab.appendChild(todas); panel.appendChild(cab);

      if (!d.items.length) {
        var vacio = el('div', 'empty'); vacio.appendChild(icono('bell-off')); vacio.appendChild(el('div', '', 'Sin notificaciones')); panel.appendChild(vacio);
      }
      d.items.forEach(function (n) {
        var fila = el('button', 'notif-i' + (n.leida ? '' : ' unread')); fila.type = 'button';
        fila.style.cssText = 'width:100%;text-align:left;background:none;border-left:0;border-right:0;border-top:0;color:inherit;font:inherit';
        var par = iconos_[n.tipo] || iconos_.info;
        var ic = el('div', 'ic'); ic.style.color = par[1]; ic.appendChild(icono(par[0]));
        var txt = el('div'); txt.appendChild(el('div', 't', n.texto)); txt.appendChild(el('div', 'd', n.fecha));
        fila.appendChild(ic); fila.appendChild(txt);
        fila.addEventListener('click', function () {
          pedir(cont.getAttribute('data-base') + '/' + encodeURIComponent(n.id) + '/leer', 'POST').catch(function () {}).then(function () {
            if (n.url) { window.location.href = n.url; } else { cargar().then(pintarLista); }
          });
        });
        panel.appendChild(fila);
      });
      iconos();
    }
    function cerrar() { panel.hidden = true; boton.setAttribute('aria-expanded', 'false'); }

    pintarCuenta(parseInt(cont.getAttribute('data-inicial'), 10) || 0);
    boton.addEventListener('click', function () {
      if (!panel.hidden) { cerrar(); return; }
      panel.hidden = false; boton.setAttribute('aria-expanded', 'true');
      cargar().then(pintarLista).catch(function () { panel.innerHTML = ''; panel.appendChild(el('div', 'empty', 'No se pudieron cargar las notificaciones.')); });
    });
    document.addEventListener('click', function (e) { if (!panel.hidden && !e.target.closest('#campana')) { cerrar(); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { cerrar(); } });
    setInterval(function () { cargar().then(function (d) { if (!panel.hidden) { pintarLista(d); } }).catch(function () {}); }, 45000);
  }

  /* ---------- Contadores animados y botón Imprimir ---------- */
  function contadores() {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
    $$('[data-count]').forEach(function (nodo) {
      var hasta = parseInt(nodo.getAttribute('data-count'), 10) || 0, t0 = performance.now(), dur = 800;
      function paso(t) {
        var k = Math.min(1, (t - t0) / dur);
        nodo.textContent = String(Math.round(hasta * (1 - Math.pow(1 - k, 3))));
        if (k < 1) { requestAnimationFrame(paso); }
      }
      nodo.textContent = '0';
      requestAnimationFrame(paso);
    });
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-imprimir]')) { e.preventDefault(); window.print(); }
  });

  /* ---------- Inicio ---------- */
  function iniciar() {
    selectoresCategoria();
    formularioReporte();
    campana();
    contadores();
    iconos();
    var inicial = document.getElementById('estado-inicial');
    if (inicial && inicial.getAttribute('data-modal')) { abrirCuadro(inicial.getAttribute('data-modal')); }
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', iniciar); } else { iniciar(); }
})();
