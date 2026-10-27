<?php
/**
 * SmartCampus AI - Barra Lateral de Navegacion Dinamica segun Rol
 */
require_once __DIR__ . '/auth.php';

$user = currentUser();
$rol = strtolower($user['nomRol'] ?? 'reportante');
$currentPage = basename($_SERVER['PHP_SELF']);

// Generar iniciales del nombre
$nombres = explode(' ', $user['nomUsuario'] ?? 'Usuario');
$iniciales = '';
foreach (array_slice($nombres, 0, 2) as $n) {
    $iniciales .= strtoupper(substr($n, 0, 1));
}
?>

<aside class="sidebar" id="sidebar">
    <!-- Logotipo y Nombre del Sistema -->
    <div class="brand">
        <div class="brand-logo">
            <img src="assets/img/logo.svg" alt="SmartCampus" style="width:24px;height:24px;">
        </div>
        <div>
            <b>SmartCampus</b>
            <small>UNS</small>
        </div>
    </div>

    <!-- Menu de Navegacion RBAC estricto -->
    <nav class="nav">
        <?php if ($rol === 'administrador' || $rol === 'operador'): ?>
            <a href="dashboard.php" class="<?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>">
                <i data-lucide="layout-dashboard"></i>
                <span>Dashboard</span>
            </a>
        <?php endif; ?>

        <?php if ($rol === 'administrador'): ?>
            <a href="usuarios.php" class="<?= ($currentPage === 'usuarios.php') ? 'active' : '' ?>">
                <i data-lucide="users"></i>
                <span>Usuarios y Roles</span>
            </a>
        <?php endif; ?>

        <?php if ($rol === 'reportante' || $rol === 'administrador'): ?>
            <a href="registrar.php" class="<?= ($currentPage === 'registrar.php') ? 'active' : '' ?>">
                <i data-lucide="plus-circle"></i>
                <span>Registrar Incidencia</span>
            </a>
        <?php endif; ?>

        <a href="bandeja.php" class="<?= ($currentPage === 'bandeja.php' || $currentPage === 'detalle.php') ? 'active' : '' ?>">
            <i data-lucide="inbox"></i>
            <span>Bandeja y Seguimiento</span>
        </a>

        <?php if ($rol === 'administrador' || $rol === 'operador'): ?>
            <a href="ubicaciones.php" class="<?= ($currentPage === 'ubicaciones.php') ? 'active' : '' ?>">
                <i data-lucide="map-pin"></i>
                <span>Ubicaciones</span>
            </a>
            <a href="reportes.php" class="<?= ($currentPage === 'reportes.php') ? 'active' : '' ?>">
                <i data-lucide="bar-chart-3"></i>
                <span>Reportes</span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- Informacion del Usuario y Cierre de Sesion -->
    <div class="side-foot">
        <div class="user-card">
            <div class="avatar av-<?= htmlspecialchars($rol) ?>">
                <?= htmlspecialchars($iniciales) ?>
            </div>
            <div style="min-width:0;flex:1">
                <div class="n" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= htmlspecialchars($user['nomUsuario'] ?? '') ?>">
                    <?= htmlspecialchars($user['nomUsuario'] ?? 'Usuario') ?>
                </div>
                <div class="r" style="text-transform:capitalize">
                    Rol: <?= htmlspecialchars($rol) ?>
                </div>
            </div>
        </div>
        <a href="logout.php" class="btn btn-sm btn-danger btn-block" style="text-decoration:none">
            <i data-lucide="log-out"></i>
            <span>Cerrar Sesión</span>
        </a>
    </div>
</aside>
