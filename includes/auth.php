<?php
/**
 * SmartCampus AI - Modulo de Autenticacion y Control de Roles (RBAC)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica si hay una sesion activa de usuario
 */
function isLoggedIn(): bool {
    return isset($_SESSION['usuario']) && is_array($_SESSION['usuario']);
}

/**
 * Obtiene los datos del usuario autenticado
 */
function currentUser(): ?array {
    return $_SESSION['usuario'] ?? null;
}

/**
 * Redirige a index.php si no ha iniciado sesion
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header("Location: index.php");
        exit();
    }
}

/**
 * Verifica si el usuario actual posee uno de los roles permitidos
 */
function hasRole($roles): bool {
    if (!isLoggedIn()) {
        return false;
    }
    $userRol = strtolower(trim($_SESSION['usuario']['nomRol'] ?? ''));
    if (is_array($roles)) {
        $allowed = array_map(function($r) { return strtolower(trim($r)); }, $roles);
        return in_array($userRol, $allowed, true);
    }
    return $userRol === strtolower(trim($roles));
}

/**
 * Exige que el usuario tenga un rol especifico, de lo contrario lo redirige
 */
function requireRole($roles): void {
    requireLogin();
    if (!hasRole($roles)) {
        // Redirige al destino por defecto segun su rol
        $userRol = strtolower(trim($_SESSION['usuario']['nomRol'] ?? ''));
        if ($userRol === 'reportante') {
            header("Location: registrar.php");
        } elseif ($userRol === 'tecnico') {
            header("Location: bandeja.php");
        } else {
            header("Location: dashboard.php");
        }
        exit();
    }
}

/**
 * Establece un mensaje flash para notificaciones posteriores
 */
function setFlash($tipo, $mensaje): void {
    $_SESSION['flash'] = [
        'tipo' => $tipo, // 'success', 'danger', 'warning', 'info'
        'mensaje' => $mensaje
    ];
}

/**
 * Obtiene y limpia el mensaje flash
 */
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
