# SmartCampus AI (frontend standalone)

## Ejecutar en VS Code
1. Abre la carpeta `smartcampus-ai` en VS Code.
2. Instala la extension **Live Server**, clic derecho en `index.html` > *Open with Live Server*.
   (Alternativa: terminal `npm start`, requiere Node.js.)
3. Requiere internet (Tailwind y Chart.js se cargan por CDN).

## Estructura
- `index.html` vista principal
- `css/styles.css` estilos (acento verde #00cc55)
- `js/app.js` servicios (Store/Api con localStorage), motor de IA simulado y vistas

## Migrar a Laravel
Reemplaza los metodos de `Api` (list, save, log) por fetch() a tu API REST.
Para restablecer datos: boton en la pantalla de login o borrar `smartcampus_v1` en localStorage.
