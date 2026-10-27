/**
 * SmartCampus AI - Scripts Generales del Sistema
 * Inicializacion de iconos, menu responsive y acceso rapido de login
 */

// Inicializar iconos de Lucide al terminar de cargar el DOM
document.addEventListener('DOMContentLoaded', function () {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});

// Alternar menu lateral en dispositivos moviles
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    if (sidebar && overlay) {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
    }
}

// Rellenar formulario de login con credenciales de acceso rapido
function setDemo(email, pass) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = pass;
    document.getElementById('loginForm').submit();
}
