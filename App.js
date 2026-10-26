/* =====================================================================
   SMARTCAMPUS AI · Frontend standalone (HTML5 + CSS3 + JS Vanilla ES6+)
   Arquitectura modular lista para migrar a Laravel (MVC + API REST):
     CONFIG · Store · AuditService · NotifService · AuthService ·
     IncidenciaService · UserService · AIService · UIController
   Para conectar el backend: poner CONFIG.USE_API = true y descomentar
   las llamadas fetch() marcadas con "// API:" en cada servicio.
   ===================================================================== */
'use strict';

/* ---------------------------------------------------------------------
   0. CONFIGURACIÓN Y CATÁLOGOS
   --------------------------------------------------------------------- */
const CONFIG = {
  STORAGE_KEY: 'smartcampus_ai_v1',
  SESSION_KEY: 'smartcampus_ai_session_v1',
  API_BASE: '/api',        // Laravel: Route::prefix('api')
  USE_API: false,          // cambiar a true al conectar el backend
  LATENCY: 80,             // simula latencia de red (ms)
  AI_DELAY: 1500,          // duración del análisis IA simulado
  DOMAIN: '@smartcampus.edu.pe'
};

const ESTADOS = ['Registrada', 'En evaluación', 'Asignada', 'En atención', 'Resuelta', 'Cerrada'];
const PRIORIDADES = ['Baja', 'Media', 'Alta', 'Crítica'];
const ROLES = { reportante: 'Reportante', operador: 'Operador', tecnico: 'Técnico', administrador: 'Administrador' };
const AREAS = ['Académica', 'Laboratorio', 'Administrativa', 'Biblioteca', 'Servicios Generales', 'Áreas comunes'];

const CATEGORIAS = {
  'Infraestructura': {
    icon: 'building-2', color: '#FFB800',
    subs: ['Iluminación / Instalaciones Eléctricas', 'Estructura y Acabados', 'Puertas y Ventanas'],
    keywords: ['luminaria', 'foco', 'luz', 'enchufe', 'cable', 'techo', 'puerta', 'tomacorriente', 'interruptor', 'ventana'],
    objetos: ['Luminaria / punto de luz', 'Instalación eléctrica', 'Superficie de techo o pared', 'Marco de puerta']
  },
  'Tecnología': {
    icon: 'monitor-smartphone', color: '#00E5FF',
    subs: ['Equipamiento / Conectividad', 'Software y Sistemas'],
    keywords: ['proyector', 'computadora', 'pc', 'red', 'internet', 'wifi', 'monitor', 'impresora', 'router'],
    objetos: ['Equipo electrónico', 'Puerto / cableado de red', 'Pantalla o proyector', 'Estación de trabajo']
  },
  'Mobiliario': {
    icon: 'armchair', color: '#00FF66',
    subs: ['Mobiliario de Aula / Laboratorio', 'Mobiliario Administrativo'],
    keywords: ['silla', 'carpeta', 'mesa', 'pizarra', 'escritorio', 'estante'],
    objetos: ['Silla o carpeta', 'Superficie de mesa', 'Estructura metálica / madera', 'Pizarra']
  },
  'Servicios': {
    icon: 'sparkles', color: '#B026FF',
    subs: ['Mantenimiento / Limpieza', 'Seguridad y Vigilancia'],
    keywords: ['agua', 'baño', 'limpieza', 'basura', 'seguridad', 'desagüe', 'grifo'],
    objetos: ['Zona de residuos', 'Instalación sanitaria', 'Área común', 'Punto de acceso']
  }
};

const PRIO_KW = {
  'Crítica': ['humo', 'chispa', 'fuego', 'incendio', 'cortocircuito', 'fuga', 'inundacion', 'descarga', 'peligro', 'riesgo', 'emergencia', 'gas', 'expuesto', 'electrocut'],
  'Alta': ['no funciona', 'no enciende', 'no prende', 'sin luz', 'sin internet', 'sin conexion', 'apagon', 'roto', 'rota', 'caido', 'caida', 'urgente', 'no hay', 'danado', 'danada', 'atascado', 'goteras'],
  'Media': ['deteriorada', 'deteriorado', 'lento', 'lenta', 'falla', 'parpadea', 'intermitente', 'desgastado', 'desgastada', 'sucio', 'suelto', 'floja', 'flojo', 'acumulada', 'acumulado']
};

const CAMPUS = [
  { nombre: 'Campus Central', pabellones: [
    { nombre: 'Pabellón A', desc: 'Ingeniería y Sistemas', pisos: 4 },
    { nombre: 'Pabellón B', desc: 'Ciencias y Humanidades', pisos: 4 },
    { nombre: 'Pabellón C', desc: 'Administración y Economía', pisos: 3 } ] },
  { nombre: 'Campus Norte', pabellones: [
    { nombre: 'Pabellón D', desc: 'Laboratorios y Talleres', pisos: 3 },
    { nombre: 'Pabellón E', desc: 'Biblioteca y Servicios', pisos: 2 } ] }
];
const aulasDePiso = (p) => [`Aula ${p}01`, `Aula ${p}02`, `Aula ${p}03`, `Laboratorio ${p}L`];

/* ---------------------------------------------------------------------
   1. UTILIDADES
   --------------------------------------------------------------------- */
const delay = (ms = CONFIG.LATENCY) => new Promise(r => setTimeout(r, ms));
const uid = (p = 'id') => `${p}_${Date.now().toString(36)}${Math.random().toString(36).slice(2, 7)}`;
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const norm = (s) => String(s ?? '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
const slug = (s) => String(s).replace(/\s+/g, '-');
const initials = (n) => n.split(' ').filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
const fmtDT = (iso) => new Date(iso).toLocaleString('es-PE', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
const fmtD = (iso) => new Date(iso).toLocaleDateString('es-PE', { day: '2-digit', month: 'short' });
const fmtT = (iso) => new Date(iso).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
const ago = (d = 0, h = 0) => new Date(Date.now() - (d * 86400000 + h * 3600000)).toISOString();
const matchWord = (text, kw) => new RegExp(`(^|[^a-z0-9])${norm(kw)}(s|es)?([^a-z0-9]|$)`).test(text);
const hashStr = (s) => [...s].reduce((a, c) => (a + c.charCodeAt(0)) % 997, 0);
const download = (name, content, mime) => {
  const blob = new Blob([content], { type: mime });
  const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name;
  document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 1500);
};
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];

function placeholderImg(label, c1, c2) {
  const lines = Array.from({ length: 9 }, (_, i) => `<line x1="0" y1="${i * 50}" x2="640" y2="${i * 50}"/>`).join('');
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="640" height="400" viewBox="0 0 640 400"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="${c1}"/><stop offset="1" stop-color="${c2}"/></linearGradient></defs><rect width="640" height="400" fill="#050507"/><rect width="640" height="400" fill="url(#g)" opacity=".4"/><g stroke="rgba(255,255,255,.09)">${lines}</g><circle cx="320" cy="160" r="46" fill="none" stroke="#fff" stroke-opacity=".6" stroke-width="3"/><circle cx="320" cy="160" r="12" fill="#fff" fill-opacity=".7"/><text x="320" y="260" text-anchor="middle" fill="#fff" font-family="monospace" font-size="24">${label}</text><text x="320" y="292" text-anchor="middle" fill="#fff" fill-opacity=".5" font-family="monospace" font-size="14">FOTO DE DEMOSTRACION</text></svg>`;
  return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
}

function readFile(file) {
  return new Promise((res, rej) => { const fr = new FileReader(); fr.onload = () => res(fr.result); fr.onerror = rej; fr.readAsDataURL(file); });
}
function compressImage(dataUrl, max = 900, q = 0.75) { // reduce peso para localStorage
  return new Promise((res) => {
    const img = new Image();
    img.onload = () => {
      const k = Math.min(1, max / Math.max(img.width, img.height));
      const c = document.createElement('canvas'); c.width = Math.round(img.width * k); c.height = Math.round(img.height * k);
      c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
      res(c.toDataURL('image/jpeg', q));
    };
    img.onerror = () => res(dataUrl);
    img.src = dataUrl;
  });
}

/* ---------------------------------------------------------------------
   2. STORE (persistencia en localStorage + datos semilla)
   --------------------------------------------------------------------- */
class Store {
  static _db = null;

  static seed() {
    const U = (id, nombre, rol, depto, extra = {}) => ({
      id, nombre, email: `${norm(nombre).replace(/[^a-z ]/g, '').trim().replace(/\s+/g, '.')}${CONFIG.DOMAIN}`,
      password: 'demo123', rol, depto, activo: true, creado: ago(60), ...extra
    });
    const usuarios = [
      U('u1', 'Carla Mendoza', 'reportante', 'Estudiante · Ing. de Sistemas'),
      U('u2', 'Luis Ramírez', 'reportante', 'Docente · Ciencias'),
      U('u3', 'Marta Quispe', 'operador', 'Mesa de Ayuda Operativa'),
      U('u4', 'Jorge Salazar', 'tecnico', 'Electricidad e Infraestructura'),
      U('u5', 'Andrea Vega', 'tecnico', 'Redes y Tecnología'),
      U('u6', 'Pedro Huamán', 'tecnico', 'Carpintería y Mobiliario'),
      U('u7', 'Sofía Rojas', 'administrador', 'Dirección de TI'),
      U('u8', 'Rosa Tello', 'tecnico', 'Mantenimiento y Limpieza')
    ];
    // Cada paso del historial: [estado, díasAtrás, horasAtrás, usuarioId, nota]
    const H = (steps) => steps.map(([estado, d, h, u, nota]) => ({ estado, fecha: ago(d, h), usuarioId: u, nota }));
    const ia = (categoria, subcategoria, prioridad, confianza, hits, objetos, validada = true) => ({
      categoria, subcategoria, prioridad, confianza, hits, objetos, validada, modificada: false,
      explicacion: [
        `Se identificaron ${hits.length} palabra(s) clave (${hits.join(', ')}) asociadas a «${categoria}».`,
        `Evidencia fotográfica analizada: ${objetos.join(', ')}.`,
        `Prioridad «${prioridad}» ponderada por urgencia del texto y contexto del área afectada.`
      ]
    });
    const mk = (n, o) => ({
      id: `inc_seed_${n}`, codigo: `INC-2026-${String(n).padStart(4, '0')}`, tecnicoId: null, informeTecnico: '', imagenSolucion: null, ...o
    });
    const incidencias = [
      mk(1, { titulo: 'La luminaria del aula 302 no funciona', descripcion: 'La luminaria principal del aula 302 dejó de encender desde ayer. Los estudiantes no pueden ver bien la pizarra durante la clase de la tarde.',
        campus: 'Campus Central', pabellon: 'Pabellón A', piso: '3', aula: 'Aula 302', area: 'Académica', categoria: 'Infraestructura', subcategoria: 'Iluminación / Instalaciones Eléctricas', prioridad: 'Alta', confianza: 95,
        ia: ia('Infraestructura', 'Iluminación / Instalaciones Eléctricas', 'Alta', 95, ['luminaria'], ['Luminaria / punto de luz', 'Superficie de techo o pared']),
        imagen: placeholderImg('LUMINARIA AULA 302', '#FFB800', '#000'), reportanteId: 'u2', tecnicoId: 'u4', estado: 'En atención', creada: ago(2, 3), actualizada: ago(0, 5),
        historial: H([['Registrada', 2, 3, 'u2', 'Incidencia registrada por el usuario'], ['En evaluación', 2, 1, 'u3', 'Clasificación IA validada'], ['Asignada', 1, 20, 'u3', 'Asignada a Jorge Salazar'], ['En atención', 0, 5, 'u4', 'Técnico inició la reparación']]) }),
      mk(2, { titulo: 'La silla del laboratorio está deteriorada', descripcion: 'Una de las sillas del laboratorio 2L tiene la pata floja y el respaldo roto. Representa riesgo de caída para el estudiante.',
        campus: 'Campus Norte', pabellon: 'Pabellón D', piso: '2', aula: 'Laboratorio 2L', area: 'Laboratorio', categoria: 'Mobiliario', subcategoria: 'Mobiliario de Aula / Laboratorio', prioridad: 'Media', confianza: 91,
        ia: ia('Mobiliario', 'Mobiliario de Aula / Laboratorio', 'Media', 91, ['silla'], ['Silla o carpeta', 'Estructura metálica / madera'], false),
        imagen: placeholderImg('SILLA LAB 2L', '#00FF66', '#000'), reportanteId: 'u1', estado: 'Registrada', creada: ago(0, 6), actualizada: ago(0, 6),
        historial: H([['Registrada', 0, 6, 'u1', 'Incidencia registrada por el usuario']]) }),
      mk(3, { titulo: 'Proyector del aula 201 no enciende', descripcion: 'El proyector del aula 201 no enciende ni muestra señal desde esta mañana. Se requiere para el examen parcial del jueves.',
        campus: 'Campus Central', pabellon: 'Pabellón B', piso: '2', aula: 'Aula 201', area: 'Académica', categoria: 'Tecnología', subcategoria: 'Equipamiento / Conectividad', prioridad: 'Alta', confianza: 93,
        ia: ia('Tecnología', 'Equipamiento / Conectividad', 'Alta', 93, ['proyector'], ['Pantalla o proyector', 'Equipo electrónico']),
        imagen: placeholderImg('PROYECTOR AULA 201', '#00E5FF', '#000'), reportanteId: 'u2', tecnicoId: 'u5', estado: 'Asignada', creada: ago(1, 8), actualizada: ago(0, 20),
        historial: H([['Registrada', 1, 8, 'u2', 'Incidencia registrada por el usuario'], ['En evaluación', 1, 4, 'u3', 'Clasificación IA validada'], ['Asignada', 0, 20, 'u3', 'Asignada a Andrea Vega']]) }),
      mk(4, { titulo: 'Fuga de agua en el baño del segundo piso', descripcion: 'Hay una fuga de agua importante en el baño del segundo piso del pabellón C. El agua se está extendiendo al pasillo y representa riesgo de caídas.',
        campus: 'Campus Central', pabellon: 'Pabellón C', piso: '2', aula: 'Aula 203', area: 'Áreas comunes', categoria: 'Servicios', subcategoria: 'Mantenimiento / Limpieza', prioridad: 'Crítica', confianza: 96,
        ia: ia('Servicios', 'Mantenimiento / Limpieza', 'Crítica', 96, ['agua', 'baño'], ['Instalación sanitaria', 'Área común']),
        imagen: placeholderImg('FUGA DE AGUA BANO', '#FF2A5F', '#000'), reportanteId: 'u1', estado: 'En evaluación', creada: ago(0, 3), actualizada: ago(0, 2),
        historial: H([['Registrada', 0, 3, 'u1', 'Incidencia registrada por el usuario'], ['En evaluación', 0, 2, 'u3', 'Operador evalúa la urgencia']]) }),
      mk(5, { titulo: 'Sin conexión WiFi en el laboratorio de cómputo', descripcion: 'El laboratorio de cómputo 1L no tiene internet ni wifi desde el lunes. Las prácticas de programación están detenidas.',
        campus: 'Campus Norte', pabellon: 'Pabellón D', piso: '1', aula: 'Laboratorio 1L', area: 'Laboratorio', categoria: 'Tecnología', subcategoria: 'Equipamiento / Conectividad', prioridad: 'Alta', confianza: 97,
        ia: ia('Tecnología', 'Equipamiento / Conectividad', 'Alta', 97, ['internet', 'wifi'], ['Puerto / cableado de red', 'Estación de trabajo']),
        imagen: placeholderImg('RED LAB 1L', '#00E5FF', '#B026FF'), reportanteId: 'u2', tecnicoId: 'u5', estado: 'Resuelta', creada: ago(4, 2), actualizada: ago(1, 4),
        informeTecnico: 'Se reemplazó el switch de acceso del laboratorio (puerto 24 dañado) y se reconfiguró el SSID. Pruebas de conectividad en las 30 estaciones: OK.',
        imagenSolucion: placeholderImg('SWITCH REEMPLAZADO', '#00FF66', '#000'),
        historial: H([['Registrada', 4, 2, 'u2', 'Incidencia registrada por el usuario'], ['En evaluación', 3, 20, 'u3', 'Clasificación IA validada'], ['Asignada', 3, 18, 'u3', 'Asignada a Andrea Vega'], ['En atención', 3, 2, 'u5', 'Técnico inició la reparación'], ['Resuelta', 1, 4, 'u5', 'Informe técnico registrado']]) }),
      mk(6, { titulo: 'Cable expuesto con chispas en el pasillo', descripcion: 'Un cable eléctrico expuesto en el pasillo del primer piso está generando chispas cuando alguien pasa cerca. Peligro de cortocircuito.',
        campus: 'Campus Central', pabellon: 'Pabellón A', piso: '1', aula: 'Aula 103', area: 'Áreas comunes', categoria: 'Infraestructura', subcategoria: 'Iluminación / Instalaciones Eléctricas', prioridad: 'Crítica', confianza: 98,
        ia: ia('Infraestructura', 'Iluminación / Instalaciones Eléctricas', 'Crítica', 98, ['cable'], ['Instalación eléctrica', 'Superficie de techo o pared']),
        imagen: placeholderImg('CABLE EXPUESTO', '#FF2A5F', '#FFB800'), reportanteId: 'u1', tecnicoId: 'u4', estado: 'Asignada', creada: ago(0, 8), actualizada: ago(0, 4),
        historial: H([['Registrada', 0, 8, 'u1', 'Incidencia registrada por el usuario'], ['En evaluación', 0, 6, 'u3', 'Prioridad crítica confirmada'], ['Asignada', 0, 4, 'u3', 'Asignada a Jorge Salazar']]) }),
      mk(7, { titulo: 'Pizarra acrílica rota en el aula 104', descripcion: 'La pizarra acrílica del aula 104 tiene una grieta que atraviesa la superficie y no permite escribir con normalidad.',
        campus: 'Campus Central', pabellon: 'Pabellón B', piso: '1', aula: 'Aula 101', area: 'Académica', categoria: 'Mobiliario', subcategoria: 'Mobiliario de Aula / Laboratorio', prioridad: 'Baja', confianza: 88,
        ia: ia('Mobiliario', 'Mobiliario de Aula / Laboratorio', 'Baja', 88, ['pizarra'], ['Pizarra', 'Superficie de mesa']),
        imagen: null, reportanteId: 'u2', tecnicoId: 'u6', estado: 'Cerrada', creada: ago(9), actualizada: ago(5),
        informeTecnico: 'Se reemplazó la pizarra acrílica por una nueva de 2.4 m. Se retiró el material dañado.',
        historial: H([['Registrada', 9, 0, 'u2', 'Incidencia registrada por el usuario'], ['En evaluación', 8, 20, 'u3', 'Clasificación IA validada'], ['Asignada', 8, 18, 'u3', 'Asignada a Pedro Huamán'], ['En atención', 7, 0, 'u6', 'Técnico inició la reparación'], ['Resuelta', 6, 0, 'u6', 'Informe técnico registrado'], ['Cerrada', 5, 0, 'u3', 'Cierre definitivo confirmado por el operador']]) }),
      mk(8, { titulo: 'Basura acumulada en el patio de la biblioteca', descripcion: 'Hay basura acumulada junto a los tachos del patio de la biblioteca desde el fin de semana. Genera mal olor y atrae insectos.',
        campus: 'Campus Norte', pabellon: 'Pabellón E', piso: '1', aula: 'Aula 102', area: 'Biblioteca', categoria: 'Servicios', subcategoria: 'Mantenimiento / Limpieza', prioridad: 'Media', confianza: 89,
        ia: ia('Servicios', 'Mantenimiento / Limpieza', 'Media', 89, ['basura'], ['Zona de residuos', 'Área común'], false),
        imagen: placeholderImg('BASURA PATIO', '#B026FF', '#000'), reportanteId: 'u1', estado: 'Registrada', creada: ago(0, 1), actualizada: ago(0, 1),
        historial: H([['Registrada', 0, 1, 'u1', 'Incidencia registrada por el usuario']]) })
    ];
    const auditoria = [
      [9, 0, 'u7', 'Inicio de sesión', 'Acceso al panel de administración'],
      [9, 0, 'u7', 'Usuario creado', 'Se registró a Rosa Tello como Técnico'],
      [9, 0, 'u2', 'Incidencia registrada', 'INC-2026-0007 · Pizarra acrílica rota en el aula 104'],
      [8, 20, 'u3', 'Incidencia evaluada', 'INC-2026-0007 · Clasificación IA validada'],
      [5, 0, 'u3', 'Estado actualizado', 'INC-2026-0007 · Resuelta → Cerrada'],
      [4, 2, 'u2', 'Incidencia registrada', 'INC-2026-0005 · Sin conexión WiFi en el laboratorio de cómputo'],
      [3, 18, 'u3', 'Técnico asignado', 'INC-2026-0005 · Asignada a Andrea Vega'],
      [1, 4, 'u5', 'Estado actualizado', 'INC-2026-0005 · En atención → Resuelta'],
      [2, 3, 'u2', 'Incidencia registrada', 'INC-2026-0001 · La luminaria del aula 302 no funciona'],
      [1, 20, 'u3', 'Técnico asignado', 'INC-2026-0001 · Asignada a Jorge Salazar'],
      [0, 5, 'u4', 'Estado actualizado', 'INC-2026-0001 · Asignada → En atención'],
      [0, 4, 'u3', 'Técnico asignado', 'INC-2026-0006 · Asignada a Jorge Salazar'],
      [0, 3, 'u1', 'Incidencia registrada', 'INC-2026-0004 · Fuga de agua en el baño del segundo piso'],
      [0, 1, 'u1', 'Incidencia registrada', 'INC-2026-0008 · Basura acumulada en el patio de la biblioteca']
    ].map(([d, h, u, accion, detalle], i) => {
      const us = usuarios.find(x => x.id === u);
      return { id: `a_seed_${i}`, fecha: ago(d, h), usuario: us.nombre, rol: us.rol, accion, detalle };
    }).sort((a, b) => b.fecha.localeCompare(a.fecha));
    const notificaciones = [
      { id: 'n1', para: 'rol:operador', texto: 'Nueva incidencia crítica: Fuga de agua en el baño del segundo piso', tipo: 'critica', incId: 'inc_seed_4', fecha: ago(0, 3), leida: false },
      { id: 'n2', para: 'rol:operador', texto: 'Nueva incidencia registrada: La silla del laboratorio está deteriorada', tipo: 'info', incId: 'inc_seed_2', fecha: ago(0, 6), leida: false },
      { id: 'n3', para: 'user:u4', texto: 'Se te asignó INC-2026-0006 · Cable expuesto con chispas (Crítica)', tipo: 'asignacion', incId: 'inc_seed_6', fecha: ago(0, 4), leida: false },
      { id: 'n4', para: 'user:u2', texto: 'Tu incidencia INC-2026-0001 pasó a «En atención»', tipo: 'estado', incId: 'inc_seed_1', fecha: ago(0, 5), leida: false },
      { id: 'n5', para: 'user:u5', texto: 'Se te asignó INC-2026-0003 · Proyector del aula 201 no enciende', tipo: 'asignacion', incId: 'inc_seed_3', fecha: ago(0, 20), leida: false },
      { id: 'n6', para: 'user:u2', texto: 'Tu incidencia INC-2026-0005 fue marcada como «Resuelta»', tipo: 'estado', incId: 'inc_seed_5', fecha: ago(1, 4), leida: true }
    ];
    return { version: 1, usuarios, incidencias, auditoria, notificaciones, counters: { incidencia: 9 } };
  }

  static load() {
    if (!Store._db) {
      try { const raw = localStorage.getItem(CONFIG.STORAGE_KEY); Store._db = raw ? JSON.parse(raw) : null; } catch (e) { Store._db = null; }
      if (!Store._db || !Store._db.usuarios) { Store._db = Store.seed(); Store.save(); }
    }
    return Store._db;
  }
  static save() {
    try { localStorage.setItem(CONFIG.STORAGE_KEY, JSON.stringify(Store._db)); }
    catch (e) { UIController.toast('Almacenamiento local lleno. Restaura los datos de prueba.', 'error'); }
  }
  static reset() {
    try { localStorage.removeItem(CONFIG.STORAGE_KEY); localStorage.removeItem(CONFIG.SESSION_KEY); } catch (e) {}
    Store._db = null; Store.load();
  }
}

/* ---------------------------------------------------------------------
   3. SERVICIOS (capa lista para reemplazar por fetch() a Laravel)
   --------------------------------------------------------------------- */
class AuditService {
  static log(accion, detalle, user = AuthService.current()) {
    const db = Store.load();
    db.auditoria.unshift({ id: uid('a'), fecha: new Date().toISOString(), usuario: user ? user.nombre : 'Sistema', rol: user ? user.rol : 'sistema', accion, detalle });
    if (db.auditoria.length > 400) db.auditoria.length = 400;
    Store.save();
  }
  static async list() { await delay(); /* API: return (await fetch(`${CONFIG.API_BASE}/auditoria`)).json(); */ return Store.load().auditoria; }
}

class NotifService {
  static push(para, texto, tipo = 'info', incId = null) {
    const db = Store.load();
    db.notificaciones.unshift({ id: uid('n'), para, texto, tipo, incId, fecha: new Date().toISOString(), leida: false });
    if (db.notificaciones.length > 120) db.notificaciones.length = 120;
    Store.save();
  }
  static forUser(u) { return Store.load().notificaciones.filter(n => n.para === `user:${u.id}` || n.para === `rol:${u.rol}`); }
  static unread(u) { return NotifService.forUser(u).filter(n => !n.leida).length; }
  static markRead(id) { const n = Store.load().notificaciones.find(x => x.id === id); if (n) { n.leida = true; Store.save(); } }
  static markAll(u) { NotifService.forUser(u).forEach(n => n.leida = true); Store.save(); }
}

class AuthService {
  static current() {
    let id = null; try { id = localStorage.getItem(CONFIG.SESSION_KEY); } catch (e) {}
    return id ? Store.load().usuarios.find(u => u.id === id && u.activo) || null : null;
  }
  static async login(email, password) {
    await delay();
    // API: const res = await fetch(`${CONFIG.API_BASE}/login`, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({email,password})});
    const u = Store.load().usuarios.find(x => x.email.toLowerCase() === String(email).trim().toLowerCase());
    if (!u || u.password !== password) throw new Error('Credenciales inválidas. Usa una cuenta demo.');
    if (!u.activo) throw new Error('Tu cuenta está desactivada. Contacta al administrador.');
    localStorage.setItem(CONFIG.SESSION_KEY, u.id);
    AuditService.log('Inicio de sesión', `Acceso como ${ROLES[u.rol]}`, u);
    return u;
  }
  static logout() {
    const u = AuthService.current();
    if (u) AuditService.log('Cierre de sesión', 'Sesión finalizada', u);
    try { localStorage.removeItem(CONFIG.SESSION_KEY); } catch (e) {}
  }
  static async switchRole(rol) { // Selector rápido de rol (solo demo)
    await delay(40);
    const u = Store.load().usuarios.find(x => x.rol === rol && x.activo);
    if (!u) throw new Error(`No hay usuarios activos con el rol ${ROLES[rol]}.`);
    const prev = AuthService.current();
    localStorage.setItem(CONFIG.SESSION_KEY, u.id);
    AuditService.log('Cambio rápido de rol (demo)', `${prev ? ROLES[prev.rol] : '—'} → ${ROLES[rol]} (${u.nombre})`, u);
    return u;
  }
}

class UserService {
  static async list() { await delay(); /* API: GET /api/usuarios */ return Store.load().usuarios; }
  static find(id) { return Store.load().usuarios.find(u => u.id === id); }
  static tecnicos() {
    const db = Store.load();
    return db.usuarios.filter(u => u.rol === 'tecnico' && u.activo).map(t => ({
      ...t, activos: db.incidencias.filter(i => i.tecnicoId === t.id && ['Asignada', 'En atención'].includes(i.estado)).length
    }));
  }
  static async create(data, actor) {
    await delay();
    const db = Store.load();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) throw new Error('Correo electrónico inválido.');
    if (!data.email.toLowerCase().endsWith(CONFIG.DOMAIN)) throw new Error(`Usa un correo institucional (${CONFIG.DOMAIN}).`);
    if (db.usuarios.some(u => u.email.toLowerCase() === data.email.toLowerCase() && u.id !== data.id)) throw new Error('Ya existe un usuario con ese correo.');
    if (!data.nombre.trim()) throw new Error('El nombre es obligatorio.');
    if (data.id) { // editar
      const u = db.usuarios.find(x => x.id === data.id);
      Object.assign(u, { nombre: data.nombre.trim(), email: data.email.trim(), rol: data.rol, depto: data.depto.trim() });
      AuditService.log('Usuario editado', `${u.nombre} · ${ROLES[u.rol]}`, actor);
    } else {
      const u = { id: uid('u'), nombre: data.nombre.trim(), email: data.email.trim(), password: 'demo123', rol: data.rol, depto: data.depto.trim() || '—', activo: true, creado: new Date().toISOString() };
      db.usuarios.push(u);
      AuditService.log('Usuario creado', `Se registró a ${u.nombre} como ${ROLES[u.rol]}`, actor);
    }
    Store.save();
  }
  static async setRole(id, rol, actor) {
    await delay(40);
    const u = UserService.find(id); const prev = u.rol; u.rol = rol; Store.save();
    AuditService.log('Rol asignado', `${u.nombre}: ${ROLES[prev]} → ${ROLES[rol]}`, actor);
  }
  static async setActive(id, activo, actor) {
    await delay(40);
    const u = UserService.find(id); u.activo = activo; Store.save();
    AuditService.log(activo ? 'Cuenta activada' : 'Cuenta desactivada', u.nombre, actor);
  }
  static async remove(id, actor) {
    await delay(40);
    const db = Store.load(); const u = UserService.find(id);
    db.usuarios = db.usuarios.filter(x => x.id !== id); Store.save();
    AuditService.log('Usuario eliminado', u.nombre, actor);
  }
}

/* Motor de IA Multimodal SIMULADO (texto + imagen + contexto) */
class AIService {
  static async analyze({ titulo, descripcion, imagen, pabellon, aula, area }) {
    // API: return (await fetch(`${CONFIG.API_BASE}/ia/analizar`, {method:'POST', body: formData})).json();
    await delay(CONFIG.AI_DELAY);
    const text = norm(`${titulo} ${descripcion}`);
    let best = null, bestHits = [];
    for (const [cat, def] of Object.entries(CATEGORIAS)) {
      const hits = def.keywords.filter(k => matchWord(text, k));
      if (hits.length > bestHits.length) { best = cat; bestHits = hits; }
    }
    const matched = !!best;
    if (!matched) best = 'Servicios';
    const def = CATEGORIAS[best];
    const subcategoria = def.subs[0];
    let confianza = matched ? Math.min(98, 72 + bestHits.length * 7 + (imagen ? 8 : 0) + hashStr(text) % 4) : (imagen ? 56 : 44);
    let prioridad = 'Baja', pHits = [];
    for (const nivel of ['Crítica', 'Alta', 'Media']) {
      pHits = PRIO_KW[nivel].filter(k => matchWord(text, k) || text.includes(k) && k.length > 6);
      if (pHits.length) { prioridad = nivel; break; }
    }
    const objetos = [...def.objetos].sort((a, b) => hashStr(a + text) - hashStr(b + text)).slice(0, 2);
    const explicacion = [
      matched
        ? `Texto: se identificaron ${bestHits.length} palabra(s) clave (${bestHits.join(', ')}) asociadas a «${best}».`
        : 'Texto: no se hallaron palabras clave concluyentes; se aplicó la categoría por defecto con baja confianza. Revisa la clasificación manualmente.',
      imagen
        ? `Evidencia visual: la fotografía fue analizada y es coherente con ${objetos.join(' y ').toLowerCase()}.`
        : 'Evidencia visual: no se adjuntó fotografía; el análisis se basa solo en el texto (menor confianza).',
      `Características: ${pabellon ? pabellon + ' · ' + (aula || '—') : 'ubicación sin completar'}${area ? ' · área «' + area + '»' : ''}. ${pHits.length ? 'Urgencia detectada por «' + pHits[0] + '».' : 'Sin términos de urgencia: prioridad baja.'} Prioridad sugerida: ${prioridad}.`
    ];
    return { categoria: best, subcategoria, prioridad, confianza, hits: bestHits, objetos: imagen ? objetos : [], explicacion, validada: false, modificada: false };
  }
}

class IncidenciaService {
  static scope(user) {
    const all = Store.load().incidencias;
    if (user.rol === 'reportante') return all.filter(i => i.reportanteId === user.id);
    if (user.rol === 'tecnico') return all.filter(i => i.tecnicoId === user.id);
    return all;
  }
  static async list(user) { await delay(30); /* API: GET /api/incidencias */ return IncidenciaService.scope(user); }
  static get(id) { return Store.load().incidencias.find(i => i.id === id); }
  static _hist(inc, estado, user, nota) { inc.estado = estado; inc.actualizada = new Date().toISOString(); inc.historial.push({ estado, fecha: inc.actualizada, usuarioId: user.id, nota }); }
  static _can(user, ...roles) { if (!roles.includes(user.rol)) throw new Error('No tienes permisos para esta acción.'); }

  static async create(d, user) {
    await delay();
    IncidenciaService._can(user, 'reportante', 'operador', 'administrador');
    const db = Store.load(); const n = db.counters.incidencia++;
    const now = new Date().toISOString();
    const inc = { id: uid('inc'), codigo: `INC-2026-${String(n).padStart(4, '0')}`, ...d, reportanteId: user.id, tecnicoId: null, estado: 'Registrada', historial: [], informeTecnico: '', imagenSolucion: null, creada: now, actualizada: now };
    inc.historial.push({ estado: 'Registrada', fecha: now, usuarioId: user.id, nota: 'Incidencia registrada por el usuario' });
    db.incidencias.unshift(inc); Store.save();
    AuditService.log('Incidencia registrada', `${inc.codigo} · ${inc.titulo}`, user);
    const crit = inc.prioridad === 'Crítica';
    NotifService.push('rol:operador', `${crit ? 'Nueva incidencia CRÍTICA' : 'Nueva incidencia registrada'}: ${inc.titulo}`, crit ? 'critica' : 'info', inc.id);
    NotifService.push('rol:administrador', `Nueva incidencia ${inc.codigo}: ${inc.titulo}`, 'info', inc.id);
    return inc;
  }
  static async startEvaluation(id, user) {
    await delay(40); IncidenciaService._can(user, 'operador', 'administrador');
    const inc = IncidenciaService.get(id);
    if (inc.estado !== 'Registrada') throw new Error('La incidencia ya fue evaluada.');
    IncidenciaService._hist(inc, 'En evaluación', user, 'Operador inició la evaluación'); Store.save();
    AuditService.log('Estado actualizado', `${inc.codigo} · Registrada → En evaluación`, user);
    NotifService.push(`user:${inc.reportanteId}`, `Tu incidencia ${inc.codigo} está «En evaluación»`, 'estado', inc.id);
    return inc;
  }
  static async validateAI(id, user) {
    await delay(40); IncidenciaService._can(user, 'operador', 'administrador');
    const inc = IncidenciaService.get(id);
    if (inc.ia) inc.ia.validada = true;
    if (inc.estado === 'Registrada') IncidenciaService._hist(inc, 'En evaluación', user, 'Clasificación IA validada por el operador');
    inc.actualizada = new Date().toISOString(); Store.save();
    AuditService.log('Incidencia evaluada', `${inc.codigo} · Clasificación IA validada (${inc.categoria} / ${inc.prioridad})`, user);
    NotifService.push(`user:${inc.reportanteId}`, `Tu incidencia ${inc.codigo} está «En evaluación»`, 'estado', inc.id);
    return inc;
  }
  static async reclassify(id, { categoria, subcategoria, prioridad }, user) {
    await delay(40); IncidenciaService._can(user, 'operador', 'administrador');
    const inc = IncidenciaService.get(id); const old = `${inc.categoria}/${inc.subcategoria}/${inc.prioridad}`;
    Object.assign(inc, { categoria, subcategoria, prioridad });
    if (inc.ia) { inc.ia.modificada = true; inc.ia.validada = false; }
    if (inc.estado === 'Registrada') IncidenciaService._hist(inc, 'En evaluación', user, 'Clasificación ajustada manualmente');
    else inc.historial.push({ estado: inc.estado, fecha: new Date().toISOString(), usuarioId: user.id, nota: 'Clasificación ajustada manualmente' });
    inc.actualizada = new Date().toISOString(); Store.save();
    AuditService.log('Clasificación modificada', `${inc.codigo} · ${old} → ${categoria}/${subcategoria}/${prioridad}`, user);
    return inc;
  }
  static async assign(id, tecnicoId, user) {
    await delay(40); IncidenciaService._can(user, 'operador', 'administrador');
    const inc = IncidenciaService.get(id); const t = UserService.find(tecnicoId);
    if (!t) throw new Error('Selecciona un técnico.');
    if (!['Registrada', 'En evaluación'].includes(inc.estado)) throw new Error('Solo se asigna desde «Registrada» o «En evaluación».');
    if (inc.estado === 'Registrada') IncidenciaService._hist(inc, 'En evaluación', user, 'Evaluación automática previa a la asignación');
    inc.tecnicoId = tecnicoId;
    IncidenciaService._hist(inc, 'Asignada', user, `Asignada a ${t.nombre}`); Store.save();
    AuditService.log('Técnico asignado', `${inc.codigo} · Asignada a ${t.nombre}`, user);
    NotifService.push(`user:${t.id}`, `Se te asignó ${inc.codigo} · ${inc.titulo} (${inc.prioridad})`, inc.prioridad === 'Crítica' ? 'critica' : 'asignacion', inc.id);
    NotifService.push(`user:${inc.reportanteId}`, `Tu incidencia ${inc.codigo} fue asignada a ${t.nombre}`, 'estado', inc.id);
    return inc;
  }
  static async techStart(id, user) {
    await delay(40); IncidenciaService._can(user, 'tecnico');
    const inc = IncidenciaService.get(id);
    if (inc.tecnicoId !== user.id || inc.estado !== 'Asignada') throw new Error('Acción no permitida para esta incidencia.');
    IncidenciaService._hist(inc, 'En atención', user, 'Técnico inició la reparación'); Store.save();
    AuditService.log('Estado actualizado', `${inc.codigo} · Asignada → En atención`, user);
    NotifService.push(`user:${inc.reportanteId}`, `Tu incidencia ${inc.codigo} pasó a «En atención»`, 'estado', inc.id);
    NotifService.push('rol:operador', `${inc.codigo} está en atención (${user.nombre})`, 'info', inc.id);
    return inc;
  }
  static async techResolve(id, informe, imagen, user) {
    await delay(40); IncidenciaService._can(user, 'tecnico');
    const inc = IncidenciaService.get(id);
    if (inc.tecnicoId !== user.id || inc.estado !== 'En atención') throw new Error('Acción no permitida para esta incidencia.');
    if (!informe || informe.trim().length < 10) throw new Error('Escribe el informe técnico de reparación (mínimo 10 caracteres).');
    inc.informeTecnico = informe.trim(); inc.imagenSolucion = imagen || null;
    IncidenciaService._hist(inc, 'Resuelta', user, 'Informe técnico registrado'); Store.save();
    AuditService.log('Estado actualizado', `${inc.codigo} · En atención → Resuelta`, user);
    NotifService.push('rol:operador', `${inc.codigo} fue marcada como «Resuelta» por ${user.nombre}. Pendiente de cierre.`, 'estado', inc.id);
    NotifService.push(`user:${inc.reportanteId}`, `Tu incidencia ${inc.codigo} fue marcada como «Resuelta»`, 'estado', inc.id);
    return inc;
  }
  static async close(id, user) {
    await delay(40); IncidenciaService._can(user, 'operador', 'administrador');
    const inc = IncidenciaService.get(id);
    if (inc.estado !== 'Resuelta') throw new Error('Solo se puede cerrar una incidencia «Resuelta».');
    IncidenciaService._hist(inc, 'Cerrada', user, 'Cierre definitivo confirmado por el operador'); Store.save();
    AuditService.log('Estado actualizado', `${inc.codigo} · Resuelta → Cerrada`, user);
    NotifService.push(`user:${inc.reportanteId}`, `Tu incidencia ${inc.codigo} fue cerrada. ¡Gracias por reportar!`, 'estado', inc.id);
    return inc;
  }
}

/* ---------------------------------------------------------------------
   4. CONTROLADOR DE UI (SPA, vistas y eventos)
   --------------------------------------------------------------------- */
const ALL_ROLES = ['reportante', 'operador', 'tecnico', 'administrador'];
const NAV = [
  { id: 'dashboard', label: 'Dashboard', icon: 'layout-dashboard', roles: ALL_ROLES },
  { id: 'registrar', label: 'Registrar Incidencia', icon: 'scan-line', roles: ['reportante', 'operador', 'administrador'], tag: 'IA' },
  { id: 'bandeja', label: 'Bandeja y Seguimiento', icon: 'inbox', roles: ALL_ROLES },
  { id: 'usuarios', label: 'Usuarios y Roles', icon: 'users', roles: ['administrador'] },
  { id: 'ubicaciones', label: 'Ubicaciones y Categorías', icon: 'map-pin', roles: ['operador', 'administrador'] },
  { id: 'auditoria', label: 'Auditoría y Notificaciones', icon: 'shield-check', roles: ['operador', 'administrador'] },
  { id: 'reportes', label: 'Reportes', icon: 'file-text', roles: ['operador', 'administrador'] }
];
const TITLES = { dashboard: 'Dashboard y Estadísticas', registrar: 'Registrar Incidencia · IA Multimodal', bandeja: 'Bandeja de Incidencias y Seguimiento', usuarios: 'Usuarios y Roles', ubicaciones: 'Ubicaciones y Categorías', auditoria: 'Auditoría y Notificaciones', reportes: 'Reportes' };

class UIController {
  static state = { filters: { q: '', estado: '', prioridad: '', categoria: '', pabellon: '' }, reg: { imagen: null, ai: null, analyzing: false }, solImg: null, charts: [], openId: null, auditQ: '' };

  /* ---------- helpers ---------- */
  static icons() { if (window.lucide) lucide.createIcons(); }
  static user() { return AuthService.current(); }
  static toast(msg, type = 'success') {
    const ic = type === 'error' ? 'alert-triangle' : type === 'info' ? 'info' : 'check-circle';
    const el = document.createElement('div'); el.className = `toast ${type}`;
    el.innerHTML = `<i data-lucide="${ic}"></i><span>${esc(msg)}</span>`;
    $('#toasts').appendChild(el); UIController.icons();
    setTimeout(() => { el.style.opacity = '0'; el.style.transform = 'translateX(30px)'; el.style.transition = '.3s'; setTimeout(() => el.remove(), 320); }, 3600);
  }
  static openModal(html, wide = false) {
    $('#modalRoot').innerHTML = `<div class="modal-back" data-action="modalBackdrop"><div class="modal ${wide ? 'wide' : ''}" role="dialog" aria-modal="true">${html}</div></div>`;
    document.body.style.overflow = 'hidden'; UIController.icons();
  }
  static closeModal() { $('#modalRoot').innerHTML = ''; document.body.style.overflow = ''; UIController.state.openId = null; UIController.state.solImg = null; }
  static confirm(msg, okLabel = 'Confirmar') {
    return new Promise(res => {
      const prev = $('#modalRoot').innerHTML;
      const root = document.createElement('div'); root.className = 'modal-back'; root.style.zIndex = 300;
      root.innerHTML = `<div class="modal" style="max-width:420px;margin-top:12vh"><div class="modal-b"><h3 style="margin-bottom:8px">Confirmar acción</h3><p class="muted">${esc(msg)}</p></div><div class="modal-f"><button class="btn" id="cf-no">Cancelar</button><button class="btn btn-primary" id="cf-ok">${esc(okLabel)}</button></div></div>`;
      document.body.appendChild(root);
      const done = (v) => { root.remove(); res(v); };
      $('#cf-no', root).onclick = () => done(false); $('#cf-ok', root).onclick = () => done(true);
      root.addEventListener('click', e => { if (e.target === root) done(false); });
    });
  }
  static badgePrio(p) { return `<span class="badge b-${p}">${p === 'Crítica' ? '<i data-lucide="flame"></i>' : ''}${p}</span>`; }
  static badgeEstado(e) { return `<span class="badge e-${slug(e)}">${e}</span>`; }
  static avatar(u) { return `<div class="avatar av-${u.rol}" title="${esc(u.nombre)}">${esc(initials(u.nombre))}</div>`; }
  static isLive(i) { return i.prioridad === 'Crítica' && !['Resuelta', 'Cerrada'].includes(i.estado); }

  static timeline(inc, compact = false) {
    const idx = ESTADOS.indexOf(inc.estado);
    return `<div class="timeline ${compact ? 'compact' : ''}">${ESTADOS.map((e, i) => {
      const h = [...inc.historial].reverse().find(x => x.estado === e && x.nota !== 'Clasificación ajustada manualmente');
      const cls = i < idx ? 'done' : i === idx ? 'current' : '';
      return `<div class="tl-step ${cls}"><div class="tl-dot">${i < idx ? '<i data-lucide="check"></i>' : i + 1}</div><div class="tl-txt"><div class="tl-label">${e}</div><div class="tl-date">${h && i <= idx ? fmtD(h.fecha) + ' · ' + new Date(h.fecha).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' }) : 'Pendiente'}</div></div></div>`;
    }).join('')}</div>`;
  }
  static miniProg(inc) { const idx = ESTADOS.indexOf(inc.estado); return `<div class="mini-prog" title="${inc.estado}">${ESTADOS.map((_, i) => `<i class="${i <= idx ? 'on' : ''}"></i>`).join('')}</div>`; }

  /* ---------- arranque / auth ---------- */
  static init() {
    Store.load();
    UIController.bindGlobal();
    window.addEventListener('hashchange', () => UIController.route());
    UIController.boot();
  }
  static boot() { AuthService.current() ? UIController.renderShell() : UIController.renderLogin(); }

  static renderLogin() {
    const users = Store.load().usuarios;
    const demo = ['u1', 'u3', 'u4', 'u7'].map(id => users.find(u => u.id === id)).filter(Boolean);
    $('#root').innerHTML = `
    <div class="login">
      <section class="login-hero">
        <div class="orb" style="width:380px;height:380px;background:var(--g);top:-120px;left:-120px"></div>
        <div class="orb" style="width:340px;height:340px;background:var(--p);bottom:-120px;right:-60px"></div>
        <div class="brand" style="padding:0;position:relative"><div class="brand-logo"><i data-lucide="cpu"></i></div><div><b>SmartCampus</b><small>AI PLATFORM</small></div></div>
        <h1 style="position:relative">Gestión <span>inteligente</span> de incidencias universitarias</h1>
        <p style="position:relative">Reporta, clasifica y resuelve incidencias del campus con un motor de IA multimodal que analiza texto, evidencia fotográfica y contexto para sugerir categoría, prioridad y nivel de confianza.</p>
        <div class="feat" style="position:relative">
          <span class="chip chip-p"><i data-lucide="sparkles"></i>IA multimodal</span><span class="chip"><i data-lucide="git-branch"></i>Timeline de 6 fases</span>
          <span class="chip"><i data-lucide="users"></i>4 roles</span><span class="chip"><i data-lucide="shield-check"></i>Auditoría</span><span class="chip"><i data-lucide="bar-chart-3"></i>Estadísticas</span>
        </div>
      </section>
      <section class="login-form">
        <div class="card login-card" style="padding:26px;border-color:rgba(0,255,102,.25);box-shadow:0 0 40px rgba(0,255,102,.08)">
          <h2 style="font-size:22px;margin-bottom:4px">Iniciar sesión</h2>
          <p class="muted" style="margin-bottom:18px">Accede con tu cuenta institucional</p>
          <form id="loginForm" autocomplete="off">
            <div class="field"><label for="lg-email">Correo institucional</label><input id="lg-email" type="email" required placeholder="usuario${CONFIG.DOMAIN}"></div>
            <div class="field"><label for="lg-pass">Contraseña</label><input id="lg-pass" type="password" required placeholder="••••••••"></div>
            <div id="lg-err" class="txt-r" style="font-size:12px;min-height:18px;margin-bottom:8px"></div>
            <button class="btn btn-primary btn-block" id="lg-btn" type="submit"><i data-lucide="log-in"></i>Ingresar</button>
          </form>
          <div class="lbl" style="margin:20px 0 8px">Cuentas demo · contraseña <span class="txt-g mono">demo123</span></div>
          <div class="demo-acc">${demo.map(u => `<button type="button" data-action="demoLogin" data-id="${u.id}"><b style="font-size:13px">${esc(u.nombre)}</b><small>${ROLES[u.rol]}</small></button>`).join('')}</div>
          <button class="btn btn-sm btn-block" style="margin-top:16px" data-action="resetDemo"><i data-lucide="rotate-ccw"></i>Restaurar Datos de Prueba (Reset Demo)</button>
        </div>
      </section>
    </div>`;
    UIController.icons();
  }

  static async doLogin(email, pass) {
    const btn = $('#lg-btn'); const err = $('#lg-err'); if (btn) btn.disabled = true; if (err) err.textContent = '';
    try { await AuthService.login(email, pass); location.hash = '#/dashboard'; UIController.renderShell(); UIController.toast('Sesión iniciada correctamente'); }
    catch (e) { if (err) err.textContent = e.message; else UIController.toast(e.message, 'error'); if (btn) btn.disabled = false; }
  }

  /* ---------- shell (sidebar + topbar) ---------- */
  static renderShell() {
    const u = UIController.user();
    $('#root').innerHTML = `
    <div class="app">
      <aside class="sidebar" id="sidebar">
        <div class="brand"><div class="brand-logo"><i data-lucide="cpu"></i></div><div><b>SmartCampus</b><small>AI PLATFORM</small></div></div>
        <nav class="nav" id="nav"></nav>
        <div class="side-foot">
          <div class="user-card">${UIController.avatar(u)}<div style="min-width:0"><div class="n">${esc(u.nombre)}</div><div class="r">${ROLES[u.rol]}</div></div></div>
          <button class="btn btn-sm btn-block" data-action="resetDemo"><i data-lucide="rotate-ccw"></i>Reset Demo</button>
          <button class="btn btn-sm btn-block btn-danger" data-action="logout"><i data-lucide="log-out"></i>Cerrar sesión</button>
        </div>
      </aside>
      <div class="overlay" id="overlay" data-action="closeDrawer"></div>
      <div class="main">
        <header class="topbar">
          <button class="btn btn-icon burger" data-action="openDrawer" aria-label="Abrir menú"><i data-lucide="menu"></i></button>
          <h2 id="pageTitle">Dashboard</h2><div class="sp"></div>
          <div class="role-switch" title="Selector rápido de rol (demo)"><label for="roleSel"><i data-lucide="repeat"></i> ROL</label>
            <select id="roleSel" data-on="switchRole">${Object.entries(ROLES).map(([k, v]) => `<option value="${k}" ${u.rol === k ? 'selected' : ''}>${v}</option>`).join('')}</select></div>
          <div class="bell"><button class="btn btn-icon" data-action="toggleNotif" aria-label="Notificaciones"><i data-lucide="bell"></i><span class="count" id="bellCount" hidden>0</span></button>
            <div class="notif-panel" id="notifPanel" hidden></div></div>
          <div class="user-chip-top" style="display:flex;align-items:center;gap:8px">${UIController.avatar(u)}</div>
        </header>
        <main id="view"></main>
      </div>
    </div>`;
    UIController.renderNav(); UIController.refreshBell(); UIController.route();
  }
  static renderNav() {
    const u = UIController.user(); const cur = UIController.currentRoute();
    $('#nav').innerHTML = NAV.filter(n => n.roles.includes(u.rol)).map(n =>
      `<a data-action="nav" data-route="${n.id}" class="${cur === n.id ? 'active' : ''}"><i data-lucide="${n.icon}"></i><span>${n.label}</span>${n.tag ? `<span class="tag">${n.tag}</span>` : ''}</a>`).join('');
    UIController.icons();
  }
  static currentRoute() { return (location.hash.replace('#/', '') || 'dashboard').split('?')[0]; }
  static async route() {
    const u = UIController.user(); if (!u) return UIController.renderLogin();
    let r = UIController.currentRoute();
    const item = NAV.find(n => n.id === r);
    if (!item || !item.roles.includes(u.rol)) { r = 'dashboard'; if (location.hash !== '#/dashboard') { history.replaceState(null, '', '#/dashboard'); } }
    UIController.destroyCharts();
    $('#pageTitle').textContent = TITLES[r];
    UIController.renderNav(); UIController.closeDrawer();
    const view = $('#view'); view.style.animation = 'none'; void view.offsetWidth; view.style.animation = '';
    const fn = { dashboard: 'viewDashboard', registrar: 'viewRegistrar', bandeja: 'viewBandeja', usuarios: 'viewUsuarios', ubicaciones: 'viewUbicaciones', auditoria: 'viewAuditoria', reportes: 'viewReportes' }[r];
    await UIController[fn](u, view);
    UIController.icons(); window.scrollTo({ top: 0 });
  }
  static closeDrawer() { $('#sidebar')?.classList.remove('open'); $('#overlay')?.classList.remove('show'); }

  /* ---------- notificaciones ---------- */
  static refreshBell() {
    const u = UIController.user(); if (!u) return; const c = NotifService.unread(u); const b = $('#bellCount');
    if (b) { b.textContent = c > 9 ? '9+' : c; b.hidden = c === 0; }
    const p = $('#notifPanel'); if (p && !p.hidden) UIController.renderNotif();
  }
  static renderNotif() {
    const u = UIController.user(); const list = NotifService.forUser(u).slice(0, 25);
    const ic = { critica: ['flame', 'var(--r)'], asignacion: ['user-check', 'var(--c)'], estado: ['refresh-cw', 'var(--g)'], info: ['bell-ring', 'var(--a)'] };
    $('#notifPanel').innerHTML = `<div class="notif-h"><b>Notificaciones</b><button class="btn btn-sm" data-action="markAllRead"><i data-lucide="check-check"></i>Marcar leídas</button></div>` +
      (list.length ? list.map(n => { const [i, c] = ic[n.tipo] || ic.info; return `<div class="notif-i ${n.leida ? '' : 'unread'}" data-action="openNotif" data-id="${n.id}" data-inc="${n.incId || ''}"><div class="ic" style="color:${c}"><i data-lucide="${i}"></i></div><div><div class="t">${esc(n.texto)}</div><div class="d">${fmtDT(n.fecha)}</div></div></div>`; }).join('')
        : `<div class="empty"><i data-lucide="bell-off"></i><div>Sin notificaciones</div></div>`);
    UIController.icons();
  }

  /* =================================================================
     VISTA · DASHBOARD
     ================================================================= */
  static async viewDashboard(u, view) {
    const incs = await IncidenciaService.list(u);
    const activas = incs.filter(i => !['Resuelta', 'Cerrada'].includes(i.estado));
    const resueltas = incs.filter(i => ['Resuelta', 'Cerrada'].includes(i.estado));
    const kpis = [
      { l: 'Total incidencias', v: incs.length, s: u.rol === 'reportante' ? 'Reportadas por ti' : u.rol === 'tecnico' ? 'Asignadas a ti' : 'En todo el campus', c: 'var(--g)', i: 'layers' },
      { l: 'Sin asignar', v: incs.filter(i => ['Registrada', 'En evaluación'].includes(i.estado)).length, s: 'Pendientes de gestión', c: 'var(--a)', i: 'hourglass' },
      { l: 'En atención', v: incs.filter(i => ['Asignada', 'En atención'].includes(i.estado)).length, s: 'Con técnico asignado', c: 'var(--c)', i: 'wrench' },
      { l: 'Críticas activas', v: activas.filter(i => i.prioridad === 'Crítica').length, s: 'Requieren acción inmediata', c: 'var(--r)', i: 'flame' },
      { l: 'Resueltas / Cerradas', v: resueltas.length, s: 'Ciclo completado', c: 'var(--g)', i: 'check-circle' },
      { l: 'Tasa de resolución', v: incs.length ? Math.round(resueltas.length / incs.length * 100) : 0, s: '% del total', c: 'var(--p)', i: 'gauge', suf: '%' }
    ];
    const recientes = [...incs].sort((a, b) => b.actualizada.localeCompare(a.actualizada)).slice(0, 5);
    const tecs = UserService.tecnicos();
    const maxT = Math.max(1, ...tecs.map(t => t.activos));
    const crit = activas.filter(i => i.prioridad === 'Crítica');
    view.innerHTML = `
      <div class="page-h"><div><h1>Hola, <span class="txt-g">${esc(u.nombre.split(' ')[0])}</span></h1><p>Panel como <b>${ROLES[u.rol]}</b> · ${new Date().toLocaleDateString('es-PE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}</p></div>
        ${u.rol !== 'tecnico' ? `<button class="btn btn-primary" data-action="nav" data-route="registrar"><i data-lucide="plus"></i>Nueva incidencia</button>` : `<button class="btn btn-cyan" data-action="nav" data-route="bandeja"><i data-lucide="inbox"></i>Ver mis tareas</button>`}</div>
      <div class="grid g-kpi" style="margin-bottom:16px">${kpis.map(k => `<div class="card kpi hover" style="--k:${k.c}"><div class="l">${k.l}<i data-lucide="${k.i}"></i></div><div class="v"><span data-count="${k.v}">0</span>${k.suf || ''}</div><div class="s">${k.s}</div></div>`).join('')}</div>
      <div class="grid g-3" style="margin-bottom:16px">
        <div class="card"><div class="card-h"><h3><i data-lucide="pie-chart"></i>Incidencias por categoría</h3></div><div class="chart-box"><canvas id="chCat"></canvas></div></div>
        <div class="card"><div class="card-h"><h3><i data-lucide="map-pin"></i>Zonas con más incidencias</h3></div><div class="chart-box"><canvas id="chZona"></canvas></div></div>
        <div class="card"><div class="card-h"><h3><i data-lucide="activity"></i>Distribución por estado</h3></div><div class="chart-box"><canvas id="chEst"></canvas></div></div>
      </div>
      <div class="grid g-21">
        <div class="card"><div class="card-h"><h3><i data-lucide="clock"></i>Actividad reciente</h3><button class="btn btn-sm" data-action="nav" data-route="bandeja">Ver todas</button></div>
          ${recientes.length ? recientes.map(i => `<div class="ticket card pc-${i.prioridad}" style="background:rgba(255,255,255,.02);margin-bottom:8px;padding:11px 14px" data-action="openInc" data-id="${i.id}"><div class="thumb" style="width:44px;height:44px">${i.imagen ? `<img src="${i.imagen}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:9px">` : '<i data-lucide="image-off"></i>'}</div><div style="min-width:0"><div class="code mono">${i.codigo}</div><div class="tt" style="margin:0;white-space:nowrap">${esc(i.titulo)}</div></div><div class="side" style="gap:5px">${UIController.badgeEstado(i.estado)}${UIController.isLive(i) ? '<span class="pulse-dot"></span>' : ''}</div></div>`).join('') : `<div class="empty"><i data-lucide="inbox"></i><div>Aún no hay incidencias</div></div>`}
        </div>
        <div style="display:flex;flex-direction:column;gap:16px">
          <div class="card" style="${crit.length ? 'border-color:rgba(255,42,95,.45);box-shadow:var(--glow-r)' : ''}"><div class="card-h"><h3><i data-lucide="siren" style="color:var(--r)"></i>Alertas críticas</h3>${crit.length ? '<span class="pulse-dot"></span>' : ''}</div>
            ${crit.length ? crit.map(i => `<div style="padding:8px 0;border-bottom:1px solid var(--border);cursor:pointer" data-action="openInc" data-id="${i.id}"><div class="mono txt-r" style="font-size:11px;font-weight:700">${i.codigo}</div><div style="font-size:13px">${esc(i.titulo)}</div><div class="muted" style="font-size:11px">${esc(i.pabellon)} · ${esc(i.aula)} · ${i.estado}</div></div>`).join('') : '<div class="muted" style="font-size:13px">Sin alertas críticas activas ✔</div>'}
          </div>
          ${u.rol !== 'reportante' ? `<div class="card"><div class="card-h"><h3><i data-lucide="hard-hat" style="color:var(--c)"></i>Carga de técnicos</h3></div>
            ${tecs.map(t => `<div class="bar-row" style="--bc:var(--c)"><span title="${esc(t.depto)}">${esc(t.nombre.split(' ')[0])} ${esc(t.nombre.split(' ')[1] || '')}</span><div class="bar"><i style="width:${t.activos / maxT * 100}%"></i></div><b>${t.activos}</b></div>`).join('') || '<div class="muted">Sin técnicos activos</div>'}</div>` : ''}
        </div>
      </div>`;
    UIController.animateCounters(view);
    UIController.drawCharts(incs);
  }
  static animateCounters(root) {
    $$('[data-count]', root).forEach(el => {
      const to = +el.dataset.count; const t0 = performance.now(); const dur = 800;
      const step = (t) => { const k = Math.min(1, (t - t0) / dur); el.textContent = Math.round(to * (1 - Math.pow(1 - k, 3))); if (k < 1) requestAnimationFrame(step); };
      requestAnimationFrame(step);
    });
  }
  static destroyCharts() { UIController.state.charts.forEach(c => c.destroy()); UIController.state.charts = []; }
  static drawCharts(incs) {
    if (!window.Chart) { $$('.chart-box').forEach(b => b.innerHTML = '<div class="empty">Chart.js no pudo cargarse (sin conexión al CDN).</div>'); return; }
    Chart.defaults.color = '#9aa3b2'; Chart.defaults.font.family = "'Inter',sans-serif";
    const grid = { color: 'rgba(255,255,255,.06)' };
    const cats = Object.keys(CATEGORIAS);
    const catData = cats.map(c => incs.filter(i => i.categoria === c).length);
    const colors = ['#FFB800', '#00E5FF', '#00FF66', '#B026FF'];
    const S = UIController.state.charts;
    S.push(new Chart($('#chCat'), { type: 'doughnut', data: { labels: cats, datasets: [{ data: catData, backgroundColor: colors.map(c => c + '33'), borderColor: colors, borderWidth: 2, hoverOffset: 8 }] },
      options: { maintainAspectRatio: false, cutout: '64%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true } } } } }));
    const pabs = CAMPUS.flatMap(c => c.pabellones.map(p => p.nombre));
    const zData = pabs.map(p => incs.filter(i => i.pabellon === p).length);
    S.push(new Chart($('#chZona'), { type: 'bar', data: { labels: pabs.map(p => p.replace('Pabellón ', 'Pab. ')), datasets: [{ data: zData, backgroundColor: 'rgba(0,255,102,.25)', borderColor: '#00FF66', borderWidth: 2, borderRadius: 8 }] },
      options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid }, x: { grid: { display: false } } } } }));
    const eData = ESTADOS.map(e => incs.filter(i => i.estado === e).length);
    const eCol = ['#cfd5e0', '#FFB800', '#00E5FF', '#00E5FF', '#00FF66', '#5b6372'];
    S.push(new Chart($('#chEst'), { type: 'bar', data: { labels: ESTADOS, datasets: [{ data: eData, backgroundColor: eCol.map(c => c + '33'), borderColor: eCol, borderWidth: 2, borderRadius: 8 }] },
      options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 }, grid }, y: { grid: { display: false } } } } }));
  }

  /* =================================================================
     VISTA · REGISTRAR INCIDENCIA (IA MULTIMODAL)
     ================================================================= */
  static async viewRegistrar(u, view) {
    UIController.state.reg = { imagen: null, ai: null, analyzing: false };
    view.innerHTML = `
      <div class="page-h"><div><h1>Registrar <span class="txt-p">incidencia</span></h1><p>Describe el problema, adjunta evidencia y deja que SmartCampus AI clasifique y priorice.</p></div></div>
      <form id="regForm" class="grid g-12" novalidate>
        <div class="card">
          <div class="card-h"><h3><i data-lucide="file-pen-line"></i>Datos de la incidencia</h3></div>
          <div class="field"><label for="f-titulo">Título *</label><input id="f-titulo" maxlength="120" placeholder="Ej. La luminaria del aula 302 no funciona"></div>
          <div class="field"><label for="f-desc">Descripción *</label><textarea id="f-desc" rows="5" placeholder="Describe qué ocurre, desde cuándo y qué impacto tiene…"></textarea></div>
          <div class="lbl" style="margin-bottom:8px">Ubicación *</div>
          <div class="row row-2">
            <div class="field"><label for="f-campus">Campus</label><select id="f-campus" data-on="locChange"><option value="">Seleccionar…</option>${CAMPUS.map(c => `<option>${c.nombre}</option>`).join('')}</select></div>
            <div class="field"><label for="f-pab">Pabellón</label><select id="f-pab" data-on="locChange" disabled><option value="">Seleccionar…</option></select></div>
            <div class="field"><label for="f-piso">Piso</label><select id="f-piso" data-on="locChange" disabled><option value="">Seleccionar…</option></select></div>
            <div class="field"><label for="f-aula">Aula / Laboratorio</label><select id="f-aula" disabled><option value="">Seleccionar…</option></select></div>
          </div>
          <div class="field"><label for="f-area">Área afectada *</label><select id="f-area"><option value="">Seleccionar…</option>${AREAS.map(a => `<option>${a}</option>`).join('')}</select></div>
        </div>

        <div style="display:flex;flex-direction:column;gap:16px">
          <div class="card">
            <div class="card-h"><h3><i data-lucide="camera"></i>Evidencia fotográfica</h3><span class="chip">Opcional · mejora la confianza IA</span></div>
            <input type="file" id="f-file" accept="image/*" hidden data-on="fileChosen">
            <div id="previewBox">${UIController.dropzoneHTML()}</div>
          </div>
          <div class="card ai-card">
            <div class="card-h"><h3><i data-lucide="sparkles"></i>Análisis multimodal <span class="badge b-ai">IA</span></h3></div>
            <button type="button" class="btn btn-ai btn-block" id="btnAnalyze" data-action="analyze"><i data-lucide="scan-search"></i>Analizar con SmartCampus AI</button>
            <div id="aiResult" style="margin-top:14px"><div class="muted" style="font-size:12.5px;text-align:center;padding:6px 0">Completa el título y la descripción, luego pulsa el botón para clasificar automáticamente.</div></div>
            <div class="row row-2" style="margin-top:14px">
              <div class="field" style="grid-column:1/-1;margin-bottom:10px"><label for="f-cat">Categoría</label><select id="f-cat" data-on="catChange"><option value="">— sin clasificar —</option>${Object.keys(CATEGORIAS).map(c => `<option>${c}</option>`).join('')}</select></div>
              <div class="field" style="margin:0"><label for="f-sub">Subcategoría</label><select id="f-sub"><option value="">—</option></select></div>
              <div class="field" style="margin:0"><label for="f-prio">Prioridad</label><select id="f-prio"><option value="">—</option>${PRIORIDADES.map(p => `<option>${p}</option>`).join('')}</select></div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-block" id="btnSubmit" style="padding:13px"><i data-lucide="send"></i>Registrar incidencia</button>
        </div>
      </form>`;
    UIController.icons();
  }
  static dropzoneHTML() {
    return `<div class="dropzone" id="dz" data-action="pickFile" tabindex="0"><i data-lucide="upload-cloud"></i><div><b>Arrastra una imagen</b> o haz clic para seleccionar</div><div class="dim" style="font-size:11px;margin-top:4px">JPG, PNG, WEBP · máx. 8 MB</div></div>`;
  }
  static previewHTML(src, scanning = false) {
    return `<div class="scan-wrap ${scanning ? 'scanning' : ''}" id="scanWrap"><img src="${src}" alt="Evidencia de la incidencia"><div class="scan-line"></div><div class="scan-corners"><i></i><i></i><i></i><i></i></div>
      <button type="button" class="btn btn-sm btn-danger rm-img" data-action="removeImg"><i data-lucide="trash-2"></i>Quitar</button></div>`;
  }
  static async handleFile(file) {
    if (!file) return;
    if (!file.type.startsWith('image/')) return UIController.toast('El archivo debe ser una imagen.', 'error');
    if (file.size > 8 * 1024 * 1024) return UIController.toast('La imagen supera los 8 MB.', 'error');
    try {
      const raw = await readFile(file);               // FileReader → Base64
      const img = await compressImage(raw);           // reduce peso para localStorage
      UIController.state.reg.imagen = img;
      $('#previewBox').innerHTML = UIController.previewHTML(img); UIController.icons();
      UIController.toast('Imagen cargada. Lista para el análisis IA.', 'info');
    } catch (e) { UIController.toast('No se pudo leer la imagen.', 'error'); }
  }
  static readForm() {
    const v = (id) => ($(`#${id}`)?.value || '').trim();
    return { titulo: v('f-titulo'), descripcion: v('f-desc'), campus: v('f-campus'), pabellon: v('f-pab'), piso: v('f-piso'), aula: v('f-aula'), area: v('f-area'), categoria: v('f-cat'), subcategoria: v('f-sub'), prioridad: v('f-prio') };
  }
  static fillSubs(cat, selected = '') {
    const sel = $('#f-sub'); if (!sel) return;
    sel.innerHTML = cat ? CATEGORIAS[cat].subs.map(s => `<option ${s === selected ? 'selected' : ''}>${s}</option>`).join('') : '<option value="">—</option>';
  }
  static aiCardHTML(ai) {
    const def = CATEGORIAS[ai.categoria];
    return `<div style="animation:fadeUp .4s ease">
      <div class="conf-wrap"><div class="conf-ring" style="--v:${ai.confianza}"><span>${ai.confianza}%</span></div>
        <div style="min-width:0"><div class="lbl" style="color:#d9a6ff">Nivel de confianza</div>
          <div style="font-size:16px;font-weight:700;margin:2px 0"><i data-lucide="${def.icon}" style="color:${def.color}"></i> ${ai.categoria}</div>
          <div class="muted" style="font-size:12.5px">${esc(ai.subcategoria)}</div>
          <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">${UIController.badgePrio(ai.prioridad)}<span class="badge b-ai"><i data-lucide="brain-circuit"></i>Predicción IA</span></div></div></div>
      ${ai.hits?.length || ai.objetos?.length ? `<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px">${(ai.hits || []).map(h => `<span class="chip chip-p"><i data-lucide="text-cursor"></i>${esc(h)}</span>`).join('')}${(ai.objetos || []).map(o => `<span class="chip chip-p"><i data-lucide="scan-eye"></i>${esc(o)}</span>`).join('')}</div>` : ''}
      <div class="expl">${ai.explicacion.map((t, i) => `<div><i data-lucide="${['file-text', 'image', 'map-pin'][i] || 'info'}"></i><span>${esc(t)}</span></div>`).join('')}</div></div>`;
  }
  static async runAnalysis() {
    const f = UIController.readForm(); const reg = UIController.state.reg;
    if (reg.analyzing) return;
    if (!f.titulo && !f.descripcion) { UIController.toast('Escribe un título o descripción para analizar.', 'error'); $('#f-titulo').focus(); return null; }
    reg.analyzing = true;
    const btn = $('#btnAnalyze'); btn.disabled = true; btn.innerHTML = '<i data-lucide="loader"></i>Analizando…';
    if (reg.imagen) $('#scanWrap')?.classList.add('scanning');
    $('#aiResult').innerHTML = `<div class="ai-loading"><div class="spinner"></div><div style="font-size:13px;font-weight:600">Procesando texto, imagen y contexto…</div><div class="muted" style="font-size:11px">Motor multimodal simulado</div></div>`;
    UIController.icons();
    try {
      const ai = await AIService.analyze({ ...f, imagen: reg.imagen });
      reg.ai = ai;
      $('#f-cat').value = ai.categoria; UIController.fillSubs(ai.categoria, ai.subcategoria); $('#f-prio').value = ai.prioridad;
      $('#aiResult').innerHTML = UIController.aiCardHTML(ai);
      UIController.toast(`Clasificado como ${ai.categoria} · ${ai.confianza}% de confianza`, 'info');
      return ai;
    } finally {
      reg.analyzing = false; $('#scanWrap')?.classList.remove('scanning');
      btn.disabled = false; btn.innerHTML = '<i data-lucide="scan-search"></i>Analizar con SmartCampus AI'; UIController.icons();
    }
  }
  static async submitIncidencia() {
    const u = UIController.user(); const f = UIController.readForm(); const reg = UIController.state.reg;
    const miss = [];
    if (!f.titulo) miss.push('título'); if (!f.descripcion) miss.push('descripción');
    if (!f.campus || !f.pabellon || !f.piso || !f.aula) miss.push('ubicación completa'); if (!f.area) miss.push('área afectada');
    if (miss.length) return UIController.toast(`Completa: ${miss.join(', ')}.`, 'error');
    const btn = $('#btnSubmit'); btn.disabled = true;
    try {
      if (!reg.ai) await UIController.runAnalysis(); // análisis automático si no se ejecutó
      const f2 = UIController.readForm(); const ai = reg.ai;
      if (ai) { ai.modificada = (f2.categoria !== ai.categoria || f2.subcategoria !== ai.subcategoria || f2.prioridad !== ai.prioridad); }
      const inc = await IncidenciaService.create({
        titulo: f2.titulo, descripcion: f2.descripcion, campus: f2.campus, pabellon: f2.pabellon, piso: f2.piso, aula: f2.aula, area: f2.area,
        categoria: f2.categoria || ai.categoria, subcategoria: f2.subcategoria || ai.subcategoria, prioridad: f2.prioridad || ai.prioridad,
        confianza: ai ? ai.confianza : 0, ia: ai, imagen: reg.imagen
      }, u);
      UIController.refreshBell();
      UIController.toast(`Incidencia ${inc.codigo} registrada correctamente`);
      UIController.state.filters = { q: inc.codigo, estado: '', prioridad: '', categoria: '', pabellon: '' };
      location.hash = '#/bandeja';
    } catch (e) { UIController.toast(e.message, 'error'); btn.disabled = false; }
  }

  /* =================================================================
     VISTA · BANDEJA Y SEGUIMIENTO
     ================================================================= */
  static async viewBandeja(u, view) {
    const F = UIController.state.filters;
    const pabs = CAMPUS.flatMap(c => c.pabellones.map(p => p.nombre));
    const title = u.rol === 'reportante' ? 'Mis incidencias' : u.rol === 'tecnico' ? 'Mis tareas asignadas' : 'Bandeja general de incidencias';
    const sub = u.rol === 'reportante' ? 'Busca por código de ticket y revisa la línea de tiempo de 6 fases.' : u.rol === 'tecnico' ? 'Ordenadas por nivel de urgencia.' : 'Filtra, evalúa, asigna y supervisa el ciclo de vida.';
    view.innerHTML = `
      <div class="page-h"><div><h1>${title}</h1><p>${sub}</p></div><span class="chip" id="countChip"></span></div>
      <div class="card" style="margin-bottom:14px"><div class="filters">
        <div class="field span2"><label for="fl-q">Buscar</label><div class="search-box"><i data-lucide="search"></i><input id="fl-q" data-live="filter" data-key="q" placeholder="Código (INC-2026-0001), título o texto…" value="${esc(F.q)}"></div></div>
        <div class="field"><label>Estado</label><select data-on="filter" data-key="estado"><option value="">Todos</option>${ESTADOS.map(e => `<option ${F.estado === e ? 'selected' : ''}>${e}</option>`).join('')}</select></div>
        <div class="field"><label>Prioridad</label><select data-on="filter" data-key="prioridad"><option value="">Todas</option>${PRIORIDADES.map(e => `<option ${F.prioridad === e ? 'selected' : ''}>${e}</option>`).join('')}</select></div>
        <div class="field"><label>Categoría</label><select data-on="filter" data-key="categoria"><option value="">Todas</option>${Object.keys(CATEGORIAS).map(e => `<option ${F.categoria === e ? 'selected' : ''}>${e}</option>`).join('')}</select></div>
        <div class="field"><label>Ubicación</label><select data-on="filter" data-key="pabellon"><option value="">Todas</option>${pabs.map(e => `<option ${F.pabellon === e ? 'selected' : ''}>${e}</option>`).join('')}</select></div>
        <button class="btn btn-sm" data-action="clearFilters" style="height:42px"><i data-lucide="filter-x"></i>Limpiar</button>
      </div></div>
      <div id="ticketList"></div>`;
    await UIController.renderTickets();
    UIController.icons();
  }
  static async renderTickets() {
    const u = UIController.user(); const F = UIController.state.filters; const box = $('#ticketList'); if (!box) return;
    let list = await IncidenciaService.list(u);
    const q = norm(F.q);
    list = list.filter(i => (!F.estado || i.estado === F.estado) && (!F.prioridad || i.prioridad === F.prioridad) && (!F.categoria || i.categoria === F.categoria) && (!F.pabellon || i.pabellon === F.pabellon)
      && (!q || norm(`${i.codigo} ${i.titulo} ${i.descripcion} ${i.aula} ${i.pabellon}`).includes(q)));
    const pw = { 'Crítica': 4, 'Alta': 3, 'Media': 2, 'Baja': 1 };
    list.sort(u.rol === 'tecnico'
      ? (a, b) => (['Resuelta', 'Cerrada'].includes(a.estado) - ['Resuelta', 'Cerrada'].includes(b.estado)) || pw[b.prioridad] - pw[a.prioridad] || a.creada.localeCompare(b.creada)
      : (a, b) => b.actualizada.localeCompare(a.actualizada));
    $('#countChip').innerHTML = `<i data-lucide="list"></i>${list.length} resultado(s)`;
    box.innerHTML = list.length ? list.map(i => {
      const tec = i.tecnicoId ? UserService.find(i.tecnicoId) : null;
      return `<div class="ticket card pc-${i.prioridad}" data-action="openInc" data-id="${i.id}">
        <div class="thumb">${i.imagen ? `<img src="${i.imagen}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:9px">` : '<i data-lucide="image-off"></i>'}</div>
        <div style="min-width:0"><div class="code mono">${i.codigo} ${UIController.isLive(i) ? '<span class="pulse-dot" style="margin-left:6px"></span>' : ''}</div>
          <div class="tt">${esc(i.titulo)}</div>
          <div class="meta">${UIController.badgePrio(i.prioridad)}<span class="chip"><i data-lucide="${CATEGORIAS[i.categoria]?.icon || 'tag'}"></i>${i.categoria}</span><span class="chip"><i data-lucide="map-pin"></i>${esc(i.pabellon)} · ${esc(i.aula)}</span>${tec ? `<span class="chip" style="color:var(--c)"><i data-lucide="hard-hat"></i>${esc(tec.nombre)}</span>` : ''}<span class="dim" style="font-size:11px">${fmtDT(i.creada)}</span></div></div>
        <div class="side">${UIController.badgeEstado(i.estado)}${UIController.miniProg(i)}<button class="btn btn-sm" data-action="openInc" data-id="${i.id}"><i data-lucide="eye"></i>${u.rol === 'operador' || u.rol === 'administrador' ? 'Gestionar' : 'Detalle'}</button></div>
        ${u.rol === 'reportante' ? `<div class="full-tl">${UIController.timeline(i, true)}</div>` : ''}
      </div>`;
    }).join('') : `<div class="card empty"><i data-lucide="search-x"></i><div>No se encontraron incidencias con los filtros actuales.</div></div>`;
    UIController.icons();
  }

  /* ---------- Modal detalle de incidencia ---------- */
  static openIncidencia(id) {
    const u = UIController.user(); const i = IncidenciaService.get(id); if (!i) return;
    UIController.state.openId = id;
    const rep = UserService.find(i.reportanteId); const tec = i.tecnicoId ? UserService.find(i.tecnicoId) : null;
    const canOp = ['operador', 'administrador'].includes(u.rol);
    const isMyTask = u.rol === 'tecnico' && i.tecnicoId === u.id;
    const ia = i.ia;
    const histHTML = [...i.historial].reverse().map(h => { const us = UserService.find(h.usuarioId); return `<div><span class="when">${fmtDT(h.fecha)}</span><span><b>${h.estado}</b> · ${esc(h.nota || '')} <span class="dim">— ${esc(us ? us.nombre : 'Sistema')}</span></span></div>`; }).join('');

    /* Panel de acciones por rol */
    let actions = '';
    if (canOp && ['Registrada', 'En evaluación'].includes(i.estado)) {
      const tecs = UserService.tecnicos();
      actions += `<div class="sec"><h4><i data-lucide="clipboard-check"></i>Evaluar clasificación IA</h4><div class="act-box a">
        <div class="row row-2" style="margin-bottom:10px">
          <div class="field" style="margin:0;grid-column:1/-1"><label>Categoría</label><select id="m-cat" data-on="mCatChange">${Object.keys(CATEGORIAS).map(c => `<option ${c === i.categoria ? 'selected' : ''}>${c}</option>`).join('')}</select></div>
          <div class="field" style="margin:0"><label>Subcategoría</label><select id="m-sub">${CATEGORIAS[i.categoria].subs.map(s => `<option ${s === i.subcategoria ? 'selected' : ''}>${s}</option>`).join('')}</select></div>
          <div class="field" style="margin:0"><label>Prioridad</label><select id="m-prio">${PRIORIDADES.map(p => `<option ${p === i.prioridad ? 'selected' : ''}>${p}</option>`).join('')}</select></div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn btn-primary btn-sm" data-action="validateAI" data-id="${i.id}"><i data-lucide="check-check"></i>Validar clasificación IA</button>
        <button class="btn btn-amber btn-sm" data-action="saveClass" data-id="${i.id}"><i data-lucide="pencil"></i>Guardar modificación manual</button>
        ${i.estado === 'Registrada' ? `<button class="btn btn-sm" data-action="startEval" data-id="${i.id}"><i data-lucide="play"></i>Iniciar evaluación</button>` : ''}</div></div></div>
        <div class="sec"><h4><i data-lucide="user-check"></i>Asignar técnico</h4><div class="act-box">
          ${tecs.length ? `<div class="field"><label>Técnicos disponibles</label><select id="m-tec">${tecs.map(t => `<option value="${t.id}">${esc(t.nombre)} — ${esc(t.depto)} · ${t.activos} ticket(s) activo(s)</option>`).join('')}</select></div>
          <button class="btn btn-cyan" data-action="assignTec" data-id="${i.id}"><i data-lucide="user-plus"></i>Asignar y pasar a «Asignada»</button>` : '<div class="muted">No hay técnicos activos disponibles.</div>'}</div></div>`;
    }
    if (canOp && i.estado === 'Resuelta') {
      actions += `<div class="sec"><h4><i data-lucide="lock"></i>Cierre definitivo</h4><div class="act-box g"><p class="muted" style="margin-bottom:10px;font-size:13px">Revisa el informe del técnico y confirma el cierre del ticket.</p><button class="btn btn-primary" data-action="closeTicket" data-id="${i.id}"><i data-lucide="check-circle"></i>Cerrar ticket (Resuelta → Cerrada)</button></div></div>`;
    }
    if (isMyTask && i.estado === 'Asignada') {
      actions += `<div class="sec"><h4><i data-lucide="wrench"></i>Acción rápida</h4><div class="act-box"><button class="btn btn-cyan" data-action="techStart" data-id="${i.id}"><i data-lucide="play"></i>Iniciar atención (Asignada → En atención)</button></div></div>`;
    }
    if (isMyTask && i.estado === 'En atención') {
      actions += `<div class="sec"><h4><i data-lucide="file-check"></i>Informe técnico de reparación</h4><div class="act-box g">
        <div class="field"><label>Informe *</label><textarea id="m-informe" rows="4" placeholder="Describe el trabajo realizado, materiales usados y pruebas de funcionamiento…"></textarea></div>
        <div class="field"><label>Foto de evidencia de la solución (opcional)</label><input type="file" id="m-fotoSol" accept="image/*" data-on="solFile"><div id="solPrev" style="margin-top:8px"></div></div>
        <button class="btn btn-primary" data-action="techResolve" data-id="${i.id}"><i data-lucide="check"></i>Marcar como Resuelta</button></div></div>`;
    }

    UIController.openModal(`
      <div class="modal-h"><div style="min-width:0"><div class="mono txt-g" style="font-size:12px;font-weight:700">${i.codigo} ${UIController.isLive(i) ? '<span class="pulse-dot" style="margin-left:6px"></span>' : ''}</div><h3>${esc(i.titulo)}</h3></div><button class="btn btn-icon" data-action="closeModal" aria-label="Cerrar"><i data-lucide="x"></i></button></div>
      <div class="modal-b">
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">${UIController.badgeEstado(i.estado)}${UIController.badgePrio(i.prioridad)}<span class="chip"><i data-lucide="${CATEGORIAS[i.categoria]?.icon || 'tag'}"></i>${i.categoria} · ${esc(i.subcategoria)}</span></div>
        <div class="card" style="padding:16px 12px;margin-bottom:16px;background:rgba(255,255,255,.02)">${UIController.timeline(i)}</div>
        <div class="det-grid">
          <div>
            <div class="sec" style="margin-top:0"><h4><i data-lucide="align-left"></i>Descripción</h4><p style="font-size:13.5px;color:#d5dae4">${esc(i.descripcion)}</p></div>
            <div class="sec"><h4><i data-lucide="info"></i>Información</h4><dl class="kv">
              <dt>Ubicación</dt><dd>${esc(i.campus)} › ${esc(i.pabellon)} › Piso ${esc(i.piso)} › ${esc(i.aula)}</dd>
              <dt>Área afectada</dt><dd>${esc(i.area)}</dd><dt>Reportante</dt><dd>${esc(rep ? rep.nombre : '—')} <span class="dim">(${esc(rep ? rep.depto : '')})</span></dd>
              <dt>Técnico</dt><dd>${tec ? esc(tec.nombre) + ' <span class="dim">(' + esc(tec.depto) + ')</span>' : '<span class="dim">Sin asignar</span>'}</dd>
              <dt>Registrada</dt><dd>${fmtDT(i.creada)}</dd><dt>Actualizada</dt><dd>${fmtDT(i.actualizada)}</dd></dl></div>
          </div>
          <div>
            <div class="sec" style="margin-top:0"><h4><i data-lucide="camera"></i>Evidencia</h4>${i.imagen ? `<img class="evidence" src="${i.imagen}" alt="Evidencia">` : '<div class="card empty" style="padding:22px"><i data-lucide="image-off"></i><div>Sin fotografía adjunta</div></div>'}</div>
            ${ia ? `<div class="sec"><h4><i data-lucide="sparkles" style="color:var(--p)"></i>Clasificación IA</h4><div class="card ai-card" style="padding:14px">
              <div class="conf-wrap" style="margin:0 0 10px"><div class="conf-ring" style="--v:${ia.confianza};width:64px;height:64px"><span style="font-size:15px">${ia.confianza}%</span></div>
              <div><div style="font-weight:700">${ia.categoria}</div><div class="muted" style="font-size:12px">${esc(ia.subcategoria)}</div><div style="margin-top:5px;display:flex;gap:5px;flex-wrap:wrap">${UIController.badgePrio(ia.prioridad)}${ia.validada ? '<span class="badge e-Resuelta"><i data-lucide="check"></i>Validada</span>' : ia.modificada ? '<span class="badge e-En-evaluación">Modificada</span>' : '<span class="badge b-ai">Pendiente</span>'}</div></div></div>
              <div class="expl">${ia.explicacion.map(t => `<div><i data-lucide="chevron-right"></i><span>${esc(t)}</span></div>`).join('')}</div></div></div>` : ''}
          </div>
        </div>
        ${i.informeTecnico ? `<div class="sec"><h4><i data-lucide="file-check"></i>Informe técnico</h4><div class="act-box g"><p style="font-size:13.5px">${esc(i.informeTecnico)}</p>${i.imagenSolucion ? `<img class="evidence" style="margin-top:10px;max-height:200px" src="${i.imagenSolucion}" alt="Evidencia de solución">` : ''}</div></div>` : ''}
        ${actions}
        <div class="sec"><h4><i data-lucide="history"></i>Historial</h4><div class="hist">${histHTML}</div></div>
      </div>`, true);
  }
  static async mutate(fn, okMsg) {
    try { await fn(); UIController.toast(okMsg); UIController.refreshBell(); const id = UIController.state.openId; UIController.state.solImg = null; if (id) UIController.openIncidencia(id); await UIController.renderTickets(); }
    catch (e) { UIController.toast(e.message, 'error'); }
  }

  /* =================================================================
     VISTA · USUARIOS Y ROLES (solo admin)
     ================================================================= */
  static async viewUsuarios(u, view) {
    const users = await UserService.list();
    view.innerHTML = `
      <div class="page-h"><div><h1>Usuarios y <span class="txt-p">roles</span></h1><p>Registra usuarios, asigna roles y activa o desactiva cuentas en vivo.</p></div>
        <button class="btn btn-primary" data-action="newUser"><i data-lucide="user-plus"></i>Registrar usuario</button></div>
      <div class="grid g-kpi" style="grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:16px">
        ${Object.entries(ROLES).map(([k, v]) => `<div class="card kpi" style="--k:${{ reportante: 'var(--g)', operador: 'var(--a)', tecnico: 'var(--c)', administrador: 'var(--p)' }[k]}"><div class="l">${v}s</div><div class="v">${users.filter(x => x.rol === k).length}</div><div class="s">${users.filter(x => x.rol === k && x.activo).length} activo(s)</div></div>`).join('')}</div>
      <div class="card"><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Usuario</th><th>Rol</th><th>Departamento / Especialidad</th><th>Activo</th><th>Acciones</th></tr></thead><tbody>
      ${users.map(x => `<tr><td><div style="display:flex;gap:10px;align-items:center">${UIController.avatar(x)}<div><div style="font-weight:600">${esc(x.nombre)} ${x.id === u.id ? '<span class="chip" style="font-size:10px">tú</span>' : ''}</div><div class="muted mono" style="font-size:11px">${esc(x.email)}</div></div></div></td>
        <td><select data-on="setRole" data-id="${x.id}" ${x.id === u.id ? 'disabled title="No puedes cambiar tu propio rol"' : ''}>${Object.entries(ROLES).map(([k, v]) => `<option value="${k}" ${x.rol === k ? 'selected' : ''}>${v}</option>`).join('')}</select></td>
        <td class="muted">${esc(x.depto)}</td>
        <td><label class="switch"><input type="checkbox" data-on="setActive" data-id="${x.id}" ${x.activo ? 'checked' : ''} ${x.id === u.id ? 'disabled' : ''}><span></span></label></td>
        <td><div style="display:flex;gap:6px"><button class="btn btn-sm btn-icon" data-action="editUser" data-id="${x.id}" title="Editar"><i data-lucide="pencil"></i></button>
          <button class="btn btn-sm btn-icon btn-danger" data-action="delUser" data-id="${x.id}" title="Eliminar" ${x.id === u.id ? 'disabled' : ''}><i data-lucide="trash-2"></i></button></div></td></tr>`).join('')}
      </tbody></table></div></div>`;
  }
  static userModal(id) {
    const x = id ? UserService.find(id) : null;
    UIController.openModal(`
      <div class="modal-h"><h3>${x ? 'Editar usuario' : 'Registrar usuario'}</h3><button class="btn btn-icon" data-action="closeModal"><i data-lucide="x"></i></button></div>
      <form id="userForm" data-id="${x ? x.id : ''}"><div class="modal-b">
        <div class="field"><label>Nombre completo *</label><input id="uf-nombre" required value="${esc(x?.nombre || '')}" placeholder="Nombre y apellido"></div>
        <div class="field"><label>Correo institucional *</label><input id="uf-email" type="email" required value="${esc(x?.email || '')}" placeholder="usuario${CONFIG.DOMAIN}"></div>
        <div class="row row-2"><div class="field"><label>Rol *</label><select id="uf-rol">${Object.entries(ROLES).map(([k, v]) => `<option value="${k}" ${(x?.rol || 'reportante') === k ? 'selected' : ''}>${v}</option>`).join('')}</select></div>
        <div class="field"><label>Departamento / Especialidad</label><input id="uf-depto" value="${esc(x?.depto || '')}" placeholder="Ej. Redes y Tecnología"></div></div>
        ${x ? '' : '<p class="muted" style="font-size:12px">Contraseña inicial de demostración: <span class="mono txt-g">demo123</span></p>'}
      </div><div class="modal-f"><button type="button" class="btn" data-action="closeModal">Cancelar</button><button class="btn btn-primary" type="submit"><i data-lucide="save"></i>Guardar</button></div></form>`);
  }

  /* =================================================================
     VISTA · UBICACIONES Y CATEGORÍAS
     ================================================================= */
  static async viewUbicaciones(u, view) {
    const incs = Store.load().incidencias;
    view.innerHTML = `
      <div class="page-h"><div><h1>Ubicaciones y <span class="txt-g">categorías</span></h1><p>Catálogo del campus y taxonomía utilizada por el motor de IA.</p></div></div>
      <div class="grid g-2">
        <div class="card"><div class="card-h"><h3><i data-lucide="building"></i>Campus › Pabellón › Piso › Aula</h3></div>
          ${CAMPUS.map(c => `<details class="tree" open><summary><i data-lucide="school"></i>${c.nombre}<span class="chip" style="margin-left:auto">${c.pabellones.length} pabellones</span></summary><div class="inner">
            ${c.pabellones.map(p => { const n = incs.filter(i => i.pabellon === p.nombre).length;
              return `<details class="tree"><summary><i data-lucide="building-2"></i>${p.nombre} <span class="muted" style="font-weight:400;font-size:12px">· ${p.desc}</span><span class="chip" style="margin-left:auto;${n ? 'color:var(--a)' : ''}">${n} incid.</span></summary><div class="inner">
              ${Array.from({ length: p.pisos }, (_, k) => `<div class="floor"><b>Piso ${k + 1}</b><div class="chips">${aulasDePiso(k + 1).map(a => `<span class="chip">${a}</span>`).join('')}</div></div>`).join('')}</div></details>`; }).join('')}
          </div></details>`).join('')}
        </div>
        <div class="card"><div class="card-h"><h3><i data-lucide="tags"></i>Categorías y subcategorías</h3></div>
          ${Object.entries(CATEGORIAS).map(([n, c]) => `<div class="card" style="margin-bottom:12px;background:rgba(255,255,255,.02);border-color:${c.color}55">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px"><div class="avatar" style="border-color:${c.color};color:${c.color};box-shadow:0 0 14px ${c.color}55"><i data-lucide="${c.icon}"></i></div><div><b style="font-size:15px">${n}</b><div class="muted" style="font-size:12px">${incs.filter(i => i.categoria === n).length} incidencia(s)</div></div></div>
            <div class="lbl" style="margin-bottom:5px">Subcategorías</div><div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px">${c.subs.map(s => `<span class="chip">${s}</span>`).join('')}</div>
            <div class="lbl" style="margin-bottom:5px">Palabras clave del motor IA</div><div style="display:flex;flex-wrap:wrap;gap:6px">${c.keywords.map(k => `<span class="chip chip-p">${k}</span>`).join('')}</div></div>`).join('')}
        </div>
      </div>`;
  }

  /* =================================================================
     VISTA · AUDITORÍA Y NOTIFICACIONES
     ================================================================= */
  static async viewAuditoria(u, view) {
    view.innerHTML = `
      <div class="page-h"><div><h1>Auditoría y <span class="txt-g">notificaciones</span></h1><p>Registro automático de cada acción realizada en la plataforma.</p></div>
        <button class="btn" data-action="toggleNotif"><i data-lucide="bell"></i>Abrir notificaciones</button></div>
      <div class="card"><div class="card-h"><h3><i data-lucide="scroll-text"></i>Historial de auditoría</h3><div class="search-box" style="min-width:min(300px,100%)"><i data-lucide="search"></i><input id="au-q" data-live="auditQ" placeholder="Buscar por usuario, acción o detalle…" value="${esc(UIController.state.auditQ)}"></div></div>
      <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Fecha</th><th>Hora</th><th>Usuario</th><th>Rol</th><th>Acción</th><th>Detalle</th></tr></thead><tbody id="auditBody"></tbody></table></div></div>`;
    await UIController.renderAudit();
  }
  static async renderAudit() {
    const body = $('#auditBody'); if (!body) return; const q = norm(UIController.state.auditQ);
    const list = (await AuditService.list()).filter(a => !q || norm(`${a.usuario} ${a.accion} ${a.detalle} ${a.rol}`).includes(q)).slice(0, 150);
    body.innerHTML = list.length ? list.map(a => `<tr><td class="mono">${new Date(a.fecha).toLocaleDateString('es-PE')}</td><td class="mono muted">${fmtT(a.fecha)}</td><td>${esc(a.usuario)}</td><td><span class="chip">${ROLES[a.rol] || a.rol}</span></td><td><b>${esc(a.accion)}</b></td><td class="muted">${esc(a.detalle)}</td></tr>`).join('')
      : `<tr><td colspan="6" class="empty">Sin registros</td></tr>`;
  }

  /* =================================================================
     VISTA · REPORTES
     ================================================================= */
  static async viewReportes(u, view) {
    const incs = Store.load().incidencias; const total = incs.length;
    const bar = (label, n, color) => `<div class="bar-row" style="--bc:${color}"><span>${label}</span><div class="bar"><i style="width:${total ? n / total * 100 : 0}%"></i></div><b>${n}</b></div>`;
    const resTimes = incs.filter(i => i.historial.some(h => h.estado === 'Resuelta')).map(i => (new Date(i.historial.find(h => h.estado === 'Resuelta').fecha) - new Date(i.creada)) / 3600000);
    const avg = resTimes.length ? (resTimes.reduce((a, b) => a + b, 0) / resTimes.length) : 0;
    const prioCol = { Baja: '#8c94a3', Media: '#00FF66', Alta: '#FFB800', 'Crítica': '#FF2A5F' };
    const estCol = ['#cfd5e0', '#FFB800', '#00E5FF', '#00E5FF', '#00FF66', '#5b6372'];
    view.innerHTML = `
      <div class="page-h"><div><h1>Reportes <span class="txt-g">ejecutivos</span></h1><p>Resumen generado el ${new Date().toLocaleString('es-PE')}</p></div>
        <div class="no-print" style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn" data-action="exportCSV"><i data-lucide="file-spreadsheet"></i>Exportar CSV</button><button class="btn" data-action="exportJSON"><i data-lucide="braces"></i>Exportar JSON</button><button class="btn btn-primary" data-action="printView"><i data-lucide="printer"></i>Imprimir</button></div></div>
      <div class="grid g-kpi" style="grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:16px">
        <div class="card kpi" style="--k:var(--g)"><div class="l">Incidencias totales</div><div class="v">${total}</div><div class="s">Registradas en el sistema</div></div>
        <div class="card kpi" style="--k:var(--r)"><div class="l">Críticas activas</div><div class="v">${incs.filter(i => i.prioridad === 'Crítica' && !['Resuelta', 'Cerrada'].includes(i.estado)).length}</div><div class="s">Atención inmediata</div></div>
        <div class="card kpi" style="--k:var(--c)"><div class="l">Tiempo medio de resolución</div><div class="v">${avg ? avg.toFixed(1) : '—'}<span style="font-size:14px"> h</span></div><div class="s">Desde registro hasta «Resuelta»</div></div>
        <div class="card kpi" style="--k:var(--p)"><div class="l">Confianza media IA</div><div class="v">${total ? Math.round(incs.reduce((a, i) => a + (i.confianza || 0), 0) / total) : 0}%</div><div class="s">${incs.filter(i => i.ia && i.ia.modificada).length} clasificación(es) corregida(s)</div></div></div>
      <div class="grid g-3">
        <div class="card"><div class="card-h"><h3><i data-lucide="tags"></i>Por categoría</h3></div>${Object.entries(CATEGORIAS).map(([n, c]) => bar(n, incs.filter(i => i.categoria === n).length, c.color)).join('')}</div>
        <div class="card"><div class="card-h"><h3><i data-lucide="flame"></i>Por prioridad</h3></div>${PRIORIDADES.slice().reverse().map(p => bar(p, incs.filter(i => i.prioridad === p).length, prioCol[p])).join('')}</div>
        <div class="card"><div class="card-h"><h3><i data-lucide="activity"></i>Por estado</h3></div>${ESTADOS.map((e, k) => bar(e, incs.filter(i => i.estado === e).length, estCol[k])).join('')}</div>
      </div>
      <div class="card" style="margin-top:16px"><div class="card-h"><h3><i data-lucide="table"></i>Detalle de incidencias</h3></div><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Código</th><th>Título</th><th>Categoría</th><th>Prioridad</th><th>Estado</th><th>Ubicación</th><th>Técnico</th></tr></thead><tbody>
      ${incs.map(i => `<tr><td class="mono txt-g">${i.codigo}</td><td>${esc(i.titulo)}</td><td>${i.categoria}</td><td>${UIController.badgePrio(i.prioridad)}</td><td>${UIController.badgeEstado(i.estado)}</td><td class="muted">${esc(i.pabellon)} · ${esc(i.aula)}</td><td class="muted">${esc(UserService.find(i.tecnicoId)?.nombre || '—')}</td></tr>`).join('')}</tbody></table></div></div>`;
  }
  static exportRows() {
    return Store.load().incidencias.map(i => ({
      codigo: i.codigo, titulo: i.titulo, descripcion: i.descripcion, campus: i.campus, pabellon: i.pabellon, piso: i.piso, aula: i.aula, area: i.area,
      categoria: i.categoria, subcategoria: i.subcategoria, prioridad: i.prioridad, estado: i.estado, confianza_ia: i.confianza,
      reportante: UserService.find(i.reportanteId)?.nombre || '', tecnico: UserService.find(i.tecnicoId)?.nombre || '',
      informe_tecnico: i.informeTecnico || '', creada: i.creada, actualizada: i.actualizada, tiene_imagen: !!i.imagen
    }));
  }

  /* =================================================================
     EVENTOS GLOBALES (delegación)
     ================================================================= */
  static bindGlobal() {
    document.addEventListener('click', (e) => {
      const el = e.target.closest('[data-action]'); if (!el) {
        const p = $('#notifPanel'); if (p && !p.hidden && !e.target.closest('.bell')) p.hidden = true; return;
      }
      if (el.dataset.action === 'modalBackdrop') { if (e.target === el) UIController.closeModal(); return; }
      if (el.tagName === 'BUTTON' && el.closest('form') && el.type === 'submit') return;
      const fn = UIController.actions[el.dataset.action]; if (fn) { e.stopPropagation(); fn(el, e); }
    });
    document.addEventListener('change', (e) => { const el = e.target.closest('[data-on]'); if (el) UIController.on[el.dataset.on]?.(el, e); });
    document.addEventListener('input', (e) => { const el = e.target.closest('[data-live]'); if (el) UIController.live[el.dataset.live]?.(el, e); });
    document.addEventListener('submit', (e) => {
      e.preventDefault();
      if (e.target.id === 'loginForm') UIController.doLogin($('#lg-email').value, $('#lg-pass').value);
      if (e.target.id === 'regForm') UIController.submitIncidencia();
      if (e.target.id === 'userForm') UIController.saveUser(e.target);
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { UIController.closeModal(); const p = $('#notifPanel'); if (p) p.hidden = true; UIController.closeDrawer(); }
      if ((e.key === 'Enter' || e.key === ' ') && e.target.id === 'dz') { e.preventDefault(); $('#f-file').click(); }
    });
    // Drag & Drop
    ['dragenter', 'dragover'].forEach(ev => document.addEventListener(ev, (e) => { const dz = e.target.closest?.('#dz'); if (dz) { e.preventDefault(); dz.classList.add('over'); } }));
    document.addEventListener('dragleave', (e) => { const dz = e.target.closest?.('#dz'); if (dz) dz.classList.remove('over'); });
    document.addEventListener('drop', (e) => { const dz = e.target.closest?.('#dz'); if (dz) { e.preventDefault(); dz.classList.remove('over'); UIController.handleFile(e.dataTransfer.files[0]); } });
    window.addEventListener('dragover', e => e.preventDefault()); window.addEventListener('drop', e => e.preventDefault());
  }

  static async saveUser(form) {
    const actor = UIController.user();
    try {
      await UserService.create({ id: form.dataset.id || null, nombre: $('#uf-nombre').value, email: $('#uf-email').value, rol: $('#uf-rol').value, depto: $('#uf-depto').value }, actor);
      UIController.closeModal(); UIController.toast(form.dataset.id ? 'Usuario actualizado' : 'Usuario registrado correctamente'); UIController.route();
    } catch (e) { UIController.toast(e.message, 'error'); }
  }
}

/* ---------- Acciones por clic (data-action) ---------- */
UIController.actions = {
  nav: (el) => { location.hash = `#/${el.dataset.route}`; UIController.closeDrawer(); },
  openDrawer: () => { $('#sidebar').classList.add('open'); $('#overlay').classList.add('show'); },
  closeDrawer: () => UIController.closeDrawer(),
  logout: async () => { if (await UIController.confirm('¿Deseas cerrar la sesión?', 'Cerrar sesión')) { AuthService.logout(); location.hash = ''; UIController.renderLogin(); UIController.toast('Sesión cerrada', 'info'); } },
  demoLogin: (el) => { const u = UserService.find(el.dataset.id); $('#lg-email').value = u.email; $('#lg-pass').value = 'demo123'; UIController.doLogin(u.email, 'demo123'); },
  resetDemo: async () => {
    if (!(await UIController.confirm('Se borrarán todos los datos locales y se restaurarán las incidencias, usuarios y auditoría de prueba.', 'Restaurar'))) return;
    Store.reset(); UIController.state.filters = { q: '', estado: '', prioridad: '', categoria: '', pabellon: '' }; UIController.closeModal(); location.hash = ''; UIController.renderLogin(); UIController.toast('Datos de prueba restaurados', 'info');
  },
  toggleNotif: () => { const p = $('#notifPanel'); p.hidden = !p.hidden; if (!p.hidden) UIController.renderNotif(); },
  markAllRead: () => { NotifService.markAll(UIController.user()); UIController.refreshBell(); UIController.renderNotif(); },
  openNotif: (el) => {
    NotifService.markRead(el.dataset.id); UIController.refreshBell(); $('#notifPanel').hidden = true;
    const inc = el.dataset.inc;
    if (inc && IncidenciaService.get(inc) && IncidenciaService.scope(UIController.user()).some(i => i.id === inc)) {
      UIController.state.filters = { q: '', estado: '', prioridad: '', categoria: '', pabellon: '' };
      if (UIController.currentRoute() !== 'bandeja') { location.hash = '#/bandeja'; setTimeout(() => UIController.openIncidencia(inc), 120); } else UIController.openIncidencia(inc);
    }
  },
  /* registrar */
  pickFile: () => $('#f-file').click(),
  removeImg: (el, e) => { e.stopPropagation(); UIController.state.reg.imagen = null; $('#previewBox').innerHTML = UIController.dropzoneHTML(); $('#f-file').value = ''; UIController.icons(); },
  analyze: () => UIController.runAnalysis(),
  /* bandeja */
  clearFilters: () => { UIController.state.filters = { q: '', estado: '', prioridad: '', categoria: '', pabellon: '' }; UIController.route(); },
  openInc: (el) => UIController.openIncidencia(el.dataset.id),
  closeModal: () => UIController.closeModal(),
  validateAI: (el) => UIController.mutate(() => IncidenciaService.validateAI(el.dataset.id, UIController.user()), 'Clasificación IA validada · incidencia en evaluación'),
  startEval: (el) => UIController.mutate(() => IncidenciaService.startEvaluation(el.dataset.id, UIController.user()), 'Evaluación iniciada'),
  saveClass: (el) => UIController.mutate(() => IncidenciaService.reclassify(el.dataset.id, { categoria: $('#m-cat').value, subcategoria: $('#m-sub').value, prioridad: $('#m-prio').value }, UIController.user()), 'Clasificación actualizada manualmente'),
  assignTec: (el) => UIController.mutate(() => IncidenciaService.assign(el.dataset.id, $('#m-tec')?.value, UIController.user()), 'Técnico asignado · estado «Asignada»'),
  techStart: (el) => UIController.mutate(() => IncidenciaService.techStart(el.dataset.id, UIController.user()), 'Atención iniciada'),
  techResolve: (el) => UIController.mutate(() => IncidenciaService.techResolve(el.dataset.id, $('#m-informe').value, UIController.state.solImg, UIController.user()), 'Incidencia marcada como Resuelta'),
  closeTicket: (el) => UIController.mutate(() => IncidenciaService.close(el.dataset.id, UIController.user()), 'Ticket cerrado definitivamente'),
  /* usuarios */
  newUser: () => UIController.userModal(null),
  editUser: (el) => UIController.userModal(el.dataset.id),
  delUser: async (el) => {
    const x = UserService.find(el.dataset.id);
    if (await UIController.confirm(`¿Eliminar a ${x.nombre}? Esta acción no se puede deshacer.`, 'Eliminar')) { await UserService.remove(x.id, UIController.user()); UIController.toast('Usuario eliminado'); UIController.route(); }
  },
  /* reportes */
  exportCSV: () => {
    const rows = UIController.exportRows(); const cols = Object.keys(rows[0] || { codigo: '' });
    const cell = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    download(`smartcampus_incidencias_${new Date().toISOString().slice(0, 10)}.csv`, '\ufeff' + [cols.join(','), ...rows.map(r => cols.map(c => cell(r[c])).join(','))].join('\r\n'), 'text/csv;charset=utf-8');
    AuditService.log('Reporte exportado', 'Exportación CSV de incidencias'); UIController.toast('CSV exportado');
  },
  exportJSON: () => {
    download(`smartcampus_incidencias_${new Date().toISOString().slice(0, 10)}.json`, JSON.stringify(UIController.exportRows(), null, 2), 'application/json');
    AuditService.log('Reporte exportado', 'Exportación JSON de incidencias'); UIController.toast('JSON exportado');
  },
  printView: () => { AuditService.log('Reporte impreso', 'Vista de reportes enviada a impresión'); window.print(); }
};

/* ---------- Cambios (data-on) ---------- */
UIController.on = {
  switchRole: async (el) => {
    try { await AuthService.switchRole(el.value); UIController.toast(`Ahora ves la plataforma como ${ROLES[el.value]}`, 'info'); UIController.state.filters = { q: '', estado: '', prioridad: '', categoria: '', pabellon: '' }; UIController.renderShell(); }
    catch (e) { UIController.toast(e.message, 'error'); el.value = UIController.user().rol; }
  },
  locChange: (el) => {
    const set = (id, opts, ph = 'Seleccionar…') => { const s = $(`#${id}`); s.innerHTML = `<option value="">${ph}</option>` + opts.map(o => `<option>${o}</option>`).join(''); s.disabled = !opts.length; };
    const campus = CAMPUS.find(c => c.nombre === $('#f-campus').value);
    if (el.id === 'f-campus') { set('f-pab', campus ? campus.pabellones.map(p => p.nombre) : []); set('f-piso', []); set('f-aula', []); }
    if (el.id === 'f-pab') { const p = campus?.pabellones.find(x => x.nombre === el.value); set('f-piso', p ? Array.from({ length: p.pisos }, (_, k) => String(k + 1)) : []); set('f-aula', []); }
    if (el.id === 'f-piso') set('f-aula', el.value ? aulasDePiso(el.value) : []);
  },
  fileChosen: (el) => UIController.handleFile(el.files[0]),
  catChange: (el) => UIController.fillSubs(el.value),
  mCatChange: (el) => { $('#m-sub').innerHTML = CATEGORIAS[el.value].subs.map(s => `<option>${s}</option>`).join(''); },
  filter: (el) => { UIController.state.filters[el.dataset.key] = el.value; UIController.renderTickets(); },
  solFile: async (el) => {
    const f = el.files[0]; if (!f) return;
    if (!f.type.startsWith('image/')) return UIController.toast('El archivo debe ser una imagen.', 'error');
    const img = await compressImage(await readFile(f)); UIController.state.solImg = img;
    $('#solPrev').innerHTML = `<img class="evidence" style="max-height:150px" src="${img}" alt="Vista previa de la solución">`;
  },
  setRole: async (el) => { await UserService.setRole(el.dataset.id, el.value, UIController.user()); UIController.toast('Rol actualizado'); UIController.route(); },
  setActive: async (el) => { await UserService.setActive(el.dataset.id, el.checked, UIController.user()); UIController.toast(el.checked ? 'Cuenta activada' : 'Cuenta desactivada', el.checked ? 'success' : 'info'); }
};
/* ---------- Entradas en vivo (data-live) ---------- */
UIController.live = {
  filter: (el) => { UIController.state.filters[el.dataset.key] = el.value; UIController.renderTickets(); },
  auditQ: (el) => { UIController.state.auditQ = el.value; UIController.renderAudit(); }
};

document.addEventListener('DOMContentLoaded', () => UIController.init());