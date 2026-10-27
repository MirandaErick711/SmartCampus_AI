<?php
/**
 * SmartCampus AI - Encabezado y Estructura Base (Header)
 */
require_once __DIR__ . '/auth.php';
requireLogin();

$user = currentUser();
$rol = strtolower($user['nomRol'] ?? 'reportante');
$tituloPagina = $tituloPagina ?? 'SmartCampus AI';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= htmlspecialchars($tituloPagina) ?> · SmartCampus AI</title>
    <link rel="icon" type="image/svg+xml" href="assets/img/logo.svg">

    <!-- Tipografias y librerias via CDN -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <!-- Estilos Oficiales Dark OLED + Neon -->
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .alert-flash {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            font-size: 13.5px;
        }
        .alert-success {
            background: rgba(0, 255, 102, 0.12);
            border: 1px solid var(--g);
            color: var(--g);
        }
        .alert-danger {
            background: rgba(255, 42, 95, 0.12);
            border: 1px solid var(--r);
            color: var(--r);
        }
        .alert-warning {
            background: rgba(255, 184, 0, 0.12);
            border: 1px solid var(--a);
            color: var(--a);
        }
        .alert-info {
            background: rgba(0, 229, 255, 0.12);
            border: 1px solid var(--c);
            color: var(--c);
        }
    </style>
</head>
<body>

<div class="app">
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

    <!-- Barra lateral segun rol -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Contenedor Principal -->
    <main class="main">
        <header class="topbar">
            <button class="btn btn-icon burger" onclick="toggleSidebar()" aria-label="Abrir menu">
                <i data-lucide="menu"></i>
            </button>
            <h2><?= htmlspecialchars($tituloPagina) ?></h2>
            <div class="sp"></div>

            <div class="chip chip-p" style="text-transform:capitalize">
                <i data-lucide="shield"></i>
                <span>Rol: <?= htmlspecialchars($rol) ?></span>
            </div>

            <div class="chip" title="<?= htmlspecialchars($user['email'] ?? '') ?>">
                <i data-lucide="user"></i>
                <span><?= htmlspecialchars($user['nomUsuario'] ?? '') ?></span>
            </div>
        </header>

        <div id="view">
            <?php 
            $flash = getFlash(); 
            if ($flash): 
            ?>
                <div class="alert-flash alert-<?= htmlspecialchars($flash['tipo']) ?>">
                    <i data-lucide="<?= $flash['tipo'] === 'success' ? 'check-circle' : 'alert-circle' ?>"></i>
                    <span><?= htmlspecialchars($flash['mensaje']) ?></span>
                </div>
            <?php endif; ?>
