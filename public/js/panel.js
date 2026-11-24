/* SmartCampus AI · gráficos del panel (Chart.js). Los datos vienen del servidor en #datos-panel. */
(function () {
  'use strict';

  var nodo = document.getElementById('datos-panel');
  if (!nodo) { return; }
  var datos;
  try { datos = JSON.parse(nodo.textContent); } catch (e) { return; }

  var cajas = Array.prototype.slice.call(document.querySelectorAll('.chart-box'));
  function mensaje(texto) {
    cajas.forEach(function (c) { c.innerHTML = ''; var d = document.createElement('div'); d.className = 'empty'; d.textContent = texto; c.appendChild(d); });
  }
  if (!window.Chart) { mensaje('Los gráficos no pudieron cargarse (sin conexión a internet).'); return; }
  if (!datos.total) { mensaje('Aún no hay incidencias para graficar.'); return; }

  Chart.defaults.color = '#9aa3b2';
  Chart.defaults.font.family = "'Inter',sans-serif";
  var rejilla = { color: 'rgba(255,255,255,.06)' };
  var paleta = ['#FFB800', '#00E5FF', '#00FF66', '#B026FF', '#FF2A5F', '#8c94a3', '#ff7a00', '#4d7cff'];
  var coloresEstado = ['#cfd5e0', '#FFB800', '#00E5FF', '#00E5FF', '#00FF66', '#5b6372'];
  var etiquetas = function (serie) { return serie.map(function (s) { return s.etiqueta; }); };
  var valores = function (serie) { return serie.map(function (s) { return s.valor; }); };
  var traslucido = function (colores) { return colores.map(function (c) { return c + '33'; }); };

  var cat = datos.categorias, colCat = cat.map(function (_, i) { return paleta[i % paleta.length]; });
  new Chart(document.getElementById('gr-categoria'), {
    type: 'doughnut',
    data: { labels: etiquetas(cat), datasets: [{ data: valores(cat), backgroundColor: traslucido(colCat), borderColor: colCat, borderWidth: 2, hoverOffset: 8 }] },
    options: { maintainAspectRatio: false, cutout: '64%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true } } } }
  });

  var pab = datos.pabellones;
  new Chart(document.getElementById('gr-pabellon'), {
    type: 'bar',
    data: { labels: etiquetas(pab).map(function (p) { return p.replace('Pabellón de ', 'Pab. ').replace('Pabellón ', 'Pab. '); }),
      datasets: [{ data: valores(pab), backgroundColor: 'rgba(0,255,102,.25)', borderColor: '#00FF66', borderWidth: 2, borderRadius: 8 }] },
    options: { maintainAspectRatio: false, plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: rejilla }, x: { grid: { display: false } } } }
  });

  var est = datos.estados;
  new Chart(document.getElementById('gr-estado'), {
    type: 'bar',
    data: { labels: etiquetas(est), datasets: [{ data: valores(est), backgroundColor: traslucido(coloresEstado), borderColor: coloresEstado, borderWidth: 2, borderRadius: 8 }] },
    options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } },
      scales: { x: { beginAtZero: true, ticks: { precision: 0 }, grid: rejilla }, y: { grid: { display: false } } } }
  });
})();
