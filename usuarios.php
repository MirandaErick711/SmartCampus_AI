<?php
/**
 * SmartCampus AI - Gestion de Usuarios y Roles (CRUD Administrador)
 */

require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/auth.php';

requireRole('administrador');

$tituloPagina = 'Gestión de Usuarios y Roles';
$errores = [];

// Procesar nuevo usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = trim($_POST['accion'] ?? '');

    if ($accion === 'crear_usuario') {
        $nomUsuario = trim($_POST['nomUsuario'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $codUsuario = trim($_POST['codUsuario'] ?? '');
        $idRol = (int)($_POST['idRol'] ?? 0);
        $departamento = trim($_POST['departamento'] ?? '');
        $password = trim($_POST['password'] ?? 'demo123');

        if (empty($nomUsuario) || empty($email) || $idRol <= 0) {
            $errores[] = 'El nombre, correo institucional y rol son obligatorios.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO usuarios (idRol, codUsuario, nomUsuario, email, password, departamento, activo)
                    VALUES (:idRol, :cod, :nom, :email, :pass, :dpto, 1)
                ");
                $stmt->execute([
                    ':idRol' => $idRol,
                    ':cod'   => !empty($codUsuario) ? $codUsuario : null,
                    ':nom'   => $nomUsuario,
                    ':email' => $email,
                    ':pass'  => $password,
                    ':dpto'  => $departamento
                ]);
                setFlash('success', "Usuario {$nomUsuario} registrado exitosamente.");
                header("Location: usuarios.php");
                exit();
            } catch (PDOException $e) {
                $errores[] = 'Error al registrar usuario: ' . $e->getMessage();
            }
        }
    }

    if ($accion === 'toggle_estado') {
        $idUserToggle = (int)($_POST['idUsuario'] ?? 0);
        $nuevoEstado = (int)($_POST['activo'] ?? 1) === 1 ? 0 : 1;
        if ($idUserToggle > 0) {
            $stmt = $pdo->prepare("UPDATE usuarios SET activo = :st WHERE idUsuario = :id");
            $stmt->execute([':st' => $nuevoEstado, ':id' => $idUserToggle]);
            setFlash('info', 'Estado del usuario actualizado.');
            header("Location: usuarios.php");
            exit();
        }
    }
}

// Cargar roles disponibles
$stmtRoles = $pdo->query("SELECT * FROM roles ORDER BY idRol");
$roles = $stmtRoles->fetchAll();

// Cargar listado de usuarios
$stmtUsers = $pdo->query("
    SELECT u.*, r.nomRol 
    FROM usuarios u 
    INNER JOIN roles r ON u.idRol = r.idRol 
    ORDER BY u.idUsuario ASC
");
$usuarios = $stmtUsers->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="page-h">
    <div>
        <h1>Gestión de Usuarios y Roles</h1>
        <p>Administración de cuentas de acceso, roles asignados y estados del personal universitario.</p>
    </div>
</div>

<?php if (!empty($errores)): ?>
    <div class="alert-flash alert-danger">
        <i data-lucide="alert-octagon"></i>
        <span><?= implode(' ', $errores) ?></span>
    </div>
<?php endif; ?>

<div class="grid g-21">
    <!-- Tabla de Usuarios -->
    <div class="card">
        <div class="card-h">
            <h3><i data-lucide="users"></i> Usuarios Registrados (<?= count($usuarios) ?>)</h3>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre y Apellidos</th>
                        <th>Correo / Código</th>
                        <th>Rol Asignado</th>
                        <th>Departamento</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td class="mono muted"><?= $u['idUsuario'] ?></td>
                            <td style="font-weight:600"><?= htmlspecialchars($u['nomUsuario']) ?></td>
                            <td>
                                <div><?= htmlspecialchars($u['email']) ?></div>
                                <small class="mono muted"><?= htmlspecialchars($u['codUsuario'] ?? '-') ?></small>
                            </td>
                            <td>
                                <span class="badge chip-p" style="text-transform:capitalize">
                                    <?= htmlspecialchars($u['nomRol']) ?>
                                </span>
                            </td>
                            <td class="muted"><?= htmlspecialchars($u['departamento'] ?? 'UNS') ?></td>
                            <td>
                                <?php if ($u['activo'] == 1): ?>
                                    <span class="badge b-Media">Activo</span>
                                <?php else: ?>
                                    <span class="badge b-Baja">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="usuarios.php" style="display:inline">
                                    <input type="hidden" name="accion" value="toggle_estado">
                                    <input type="hidden" name="idUsuario" value="<?= $u['idUsuario'] ?>">
                                    <input type="hidden" name="activo" value="<?= $u['activo'] ?>">
                                    <button type="submit" class="btn btn-sm" title="Cambiar estado activo/inactivo">
                                        <i data-lucide="<?= $u['activo'] == 1 ? 'user-x' : 'user-check' ?>"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Formulario para Nuevo Usuario -->
    <div class="card">
        <div class="card-h">
            <h3><i data-lucide="user-plus"></i> Registrar Usuario</h3>
        </div>

        <form method="POST" action="usuarios.php">
            <input type="hidden" name="accion" value="crear_usuario">

            <div class="field">
                <label for="nomUsuario">Nombres y Apellidos *</label>
                <input type="text" id="nomUsuario" name="nomUsuario" required placeholder="ej. Carlos Mendoza">
            </div>

            <div class="field">
                <label for="email">Correo Institucional *</label>
                <input type="email" id="email" name="email" required placeholder="ej. carlos.mendoza@smartcampus.edu.pe">
            </div>

            <div class="row row-2">
                <div class="field">
                    <label for="codUsuario">Código</label>
                    <input type="text" id="codUsuario" name="codUsuario" placeholder="ej. u8">
                </div>

                <div class="field">
                    <label for="idRol">Rol *</label>
                    <select id="idRol" name="idRol" required>
                        <option value="">-- Seleccionar --</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['idRol'] ?>"><?= htmlspecialchars($r['nomRol']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="field">
                <label for="departamento">Departamento / Escuela</label>
                <input type="text" id="departamento" name="departamento" placeholder="ej. Ing. de Sistemas">
            </div>

            <div class="field">
                <label for="password">Contraseña Inicial</label>
                <input type="password" id="password" name="password" value="demo123" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top:8px">
                <i data-lucide="check"></i>
                <span>Crear Cuenta</span>
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
