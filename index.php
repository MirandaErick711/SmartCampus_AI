<?php
/**
 * SmartCampus AI - Portal de Inicio de Sesion (Login)
 * Autenticacion mediante PDO contra MySQL
 */

require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/auth.php';

// Si ya tiene sesion activa, redirigir segun su rol
if (isLoggedIn()) {
    $rol = strtolower($_SESSION['usuario']['nomRol'] ?? '');
    if ($rol === 'reportante') {
        header("Location: registrar.php");
    } elseif ($rol === 'tecnico') {
        header("Location: bandeja.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

$error = '';

// Procesar formulario de inicio de sesion o acceso rapido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Por favor ingrese su correo institucional y contraseña.';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT u.idUsuario, u.codUsuario, u.nomUsuario, u.email, u.password, u.activo, r.nomRol 
                FROM usuarios u 
                INNER JOIN roles r ON u.idRol = r.idRol 
                WHERE (u.email = :email OR u.codUsuario = :cod) AND u.activo = 1 
                LIMIT 1
            ");
            $stmt->execute([
                ':email' => $email,
                ':cod'   => $email
            ]);
            $user = $stmt->fetch();

            if ($user && ($password === $user['password'] || password_verify($password, $user['password']))) {
                // Iniciar sesion exitosa
                $_SESSION['usuario'] = [
                    'idUsuario'  => $user['idUsuario'],
                    'codUsuario' => $user['codUsuario'],
                    'nomUsuario' => $user['nomUsuario'],
                    'email'      => $user['email'],
                    'nomRol'     => $user['nomRol']
                ];

                setFlash('success', '¡Bienvenido(a), ' . $user['nomUsuario'] . '!');

                $rol = strtolower($user['nomRol']);
                if ($rol === 'reportante') {
                    header("Location: registrar.php");
                } elseif ($rol === 'tecnico') {
                    header("Location: bandeja.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit();
            } else {
                $error = 'Credenciales no válidas. Verifique su correo y contraseña.';
            }
        } catch (PDOException $e) {
            $error = 'Error de conexión con la base de datos: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Acceso al Sistema · SmartCampus AI</title>
    <link rel="icon" type="image/svg+xml" href="assets/img/logo.svg">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0/dist/umd/lucide.min.js"></script>
    <link rel="stylesheet" href="assets/css/styles.css">

    <!-- Scripts generales del sistema -->
    <script src="assets/js/main.js" defer></script>
    <style>
        .demo-btn {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px;
            text-align: left;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
            color: #fff;
        }
        .demo-btn:hover {
            border-color: var(--g);
            background: rgba(0, 255, 102, 0.05);
            transform: translateY(-2px);
        }
        .demo-btn b {
            display: block;
            font-size: 13px;
        }
        .demo-btn span {
            display: block;
            font-size: 11px;
            color: var(--muted);
        }
    </style>
</head>
<body>

<div class="login">
    <!-- Panel Izquierdo / Presentacion Oficial -->
    <div class="login-hero">
        <div class="orb" style="width:380px;height:380px;background:#00FF66;top:-10%;left:-10%"></div>
        <div class="orb" style="width:320px;height:320px;background:#B026FF;bottom:5%;right:5%"></div>

        <div style="display:flex;align-items:center;gap:12px">
            <div class="brand-logo" style="width:48px;height:48px;">
                <img src="assets/img/logo.svg" alt="SmartCampus" style="width:30px;height:30px;">
            </div>
            <div>
                <b style="font-size:22px;letter-spacing:0.5px">SmartCampus AI</b>
                <small style="color:var(--g);font-weight:700;letter-spacing:1.5px;display:block">UNS</small>
            </div>
        </div>

        <h1>Gestión inteligente de <span>incidencias de campus</span></h1>
        <p>
            Plataforma universitaria para el reporte, asignación y resolución de averías en infraestructura, laboratorios y servicios de la Universidad Nacional del Santa.
        </p>

        <div class="feat">
            <div class="chip"><i data-lucide="building-2"></i> Campus I y II</div>
            <div class="chip"><i data-lucide="shield-check"></i> Control por Roles</div>
            <div class="chip"><i data-lucide="wrench"></i> Soporte y Mantenimiento</div>
            <div class="chip"><i data-lucide="activity"></i> Trazabilidad en Tiempo Real</div>
        </div>
    </div>

    <!-- Panel Derecho / Formulario de Autenticacion -->
    <div class="login-form">
        <div class="card login-card">
            <div style="margin-bottom:20px;text-align:center">
                <h3 style="font-size:18px;font-weight:700">Iniciar Sesión</h3>
                <p class="muted" style="font-size:13px;margin-top:4px">Acceda con sus credenciales institucionales</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert-flash alert-danger" style="margin-bottom:16px;padding:10px 12px;font-size:13px">
                    <i data-lucide="alert-triangle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="index.php" id="loginForm">
                <div class="field">
                    <label for="email">Correo Institucional o Código</label>
                    <input type="text" id="email" name="email" placeholder="ej. maycol.garcia@smartcampus.edu.pe" required autocomplete="username">
                </div>

                <div class="field">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top:8px">
                    <i data-lucide="log-in"></i>
                    <span>Ingresar al Sistema</span>
                </button>
            </form>

            <div style="margin-top:24px;border-top:1px solid var(--border);padding-top:16px">
                <label class="lbl" style="display:block;margin-bottom:10px">Acceso Rápido por Perfil de Usuario:</label>
                <div class="demo-acc">
                    <button type="button" class="demo-btn" onclick="setDemo('maycol.garcia@smartcampus.edu.pe', 'demo123')">
                        <b style="color:var(--g)">Maycol García</b>
                        <span>Rol: Reportante (Estudiante)</span>
                    </button>
                    <button type="button" class="demo-btn" onclick="setDemo('jorge.avila@smartcampus.edu.pe', 'demo123')">
                        <b style="color:var(--a)">Jorge Ávila</b>
                        <span>Rol: Operador (Mesa de Ayuda)</span>
                    </button>
                    <button type="button" class="demo-btn" onclick="setDemo('erick.miranda@smartcampus.edu.pe', 'demo123')">
                        <b style="color:var(--c)">Erick Miranda</b>
                        <span>Rol: Técnico (Mantenimiento)</span>
                    </button>
                    <button type="button" class="demo-btn" onclick="setDemo('angel.rojas@smartcampus.edu.pe', 'demo123')">
                        <b style="color:var(--p)">Ángel Rojas</b>
                        <span>Rol: Administrador (TI)</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
