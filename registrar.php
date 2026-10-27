<?php
/**
 * SmartCampus AI - Registro de Nuevas Incidencias Universitarias
 */

require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/auth.php';

// Control de acceso RBAC: Reportante y Administrador
requireRole(['reportante', 'administrador']);

$tituloPagina = 'Registrar Incidencia';
$user = currentUser();
$errores = [];

// Cargar listas de seleccion desde MySQL
// 1. Ubicaciones
$stmt = $pdo->query("SELECT idUbic, campus, pabellon, piso, aula_espacio FROM ubicaciones ORDER BY campus, pabellon, aula_espacio");
$ubicaciones = $stmt->fetchAll();

// 2. Subcategorias y Categorias
$stmt = $pdo->query("
    SELECT s.idSubCateg, s.nomSubCatg, c.nomCateg 
    FROM subcategorias s 
    INNER JOIN categorias c ON s.idCategoria = c.idCategoria 
    ORDER BY c.nomCateg, s.nomSubCatg
");
$subcategorias = $stmt->fetchAll();

// Procesar envio de formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $idUbic = (int)($_POST['idUbic'] ?? 0);
    $idSubCateg = (int)($_POST['idSubCateg'] ?? 0);
    $prioridad = trim($_POST['prioridad'] ?? 'Media');

    // Validaciones
    if (empty($titulo)) {
        $errores[] = 'El título de la incidencia es obligatorio.';
    }
    if (empty($descripcion)) {
        $errores[] = 'La descripción de la avería es obligatoria.';
    }
    if ($idUbic <= 0) {
        $errores[] = 'Debe seleccionar una ubicación válida.';
    }
    if ($idSubCateg <= 0) {
        $errores[] = 'Debe seleccionar una categoría / subcategoría.';
    }
    if (!in_array($prioridad, ['Baja', 'Media', 'Alta', 'Critica'], true)) {
        $prioridad = 'Media';
    }

    // Manejo de archivo de imagen de evidencia
    $rutaImagen = null;
    if (isset($_FILES['evidencia']) && $_FILES['evidencia']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['evidencia']['tmp_name'];
        $fileName = $_FILES['evidencia']['name'];
        $fileSize = $_FILES['evidencia']['size'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowedExts, true)) {
            $errores[] = 'Formato de imagen no permitido. Utilice JPG, PNG o WEBP.';
        } elseif ($fileSize > 5 * 1024 * 1024) {
            $errores[] = 'La imagen no debe superar los 5 MB.';
        } else {
            $nuevoNombre = 'incd_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
            $destino = __DIR__ . '/assets/uploads/' . $nuevoNombre;
            if (move_uploaded_file($fileTmp, $destino)) {
                $rutaImagen = 'assets/uploads/' . $nuevoNombre;
            } else {
                $errores[] = 'No se pudo guardar la fotografía de evidencia.';
            }
        }
    }

    // Si no hay errores, insertar mediante PDO con transaccion
    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            // Generar codigo autoincremental correlativo anual (ej. INC-2026-0006)
            $stmt = $pdo->query("SELECT MAX(idIncidencia) FROM incidencias");
            $lastId = (int)$stmt->fetchColumn();
            $siguienteNumero = $lastId + 1;
            $codigo = sprintf("INC-%s-%04d", date('Y'), $siguienteNumero);

            // 1. Insertar en tabla Incidencias
            $sqlInc = "
                INSERT INTO incidencias (
                    idReportante, idSubCateg, idUbic, codIncdencia, titulo, 
                    descripIncidencia, prioridad, estado, imagen_evidencia
                ) VALUES (
                    :idReportante, :idSubCateg, :idUbic, :codIncdencia, :titulo, 
                    :descripIncidencia, :prioridad, 'Registrada', :imagen_evidencia
                )
            ";
            $stmtInc = $pdo->prepare($sqlInc);
            $stmtInc->execute([
                ':idReportante'      => $user['idUsuario'],
                ':idSubCateg'        => $idSubCateg,
                ':idUbic'            => $idUbic,
                ':codIncdencia'      => $codigo,
                ':titulo'            => $titulo,
                ':descripIncidencia' => $descripcion,
                ':prioridad'         => $prioridad,
                ':imagen_evidencia'  => $rutaImagen
            ]);

            $idNuevaIncidencia = (int)$pdo->lastInsertId();

            // 2. Insertar en Historial_Incidencias para trazabilidad obligatoria
            $sqlHist = "
                INSERT INTO historial_incidencias (
                    idIncidencia, estado_anterior, estado_nuevo, cambiado_por, nota
                ) VALUES (
                    :idIncidencia, NULL, 'Registrada', :cambiado_por, :nota
                )
            ";
            $stmtHist = $pdo->prepare($sqlHist);
            $stmtHist->execute([
                ':idIncidencia' => $idNuevaIncidencia,
                ':cambiado_por' => $user['nomUsuario'],
                ':nota'         => 'Incidencia registrada exitosamente en el sistema por el reportante.'
            ]);

            $pdo->commit();

            setFlash('success', "Incidencia {$codigo} registrada exitosamente.");
            header("Location: bandeja.php");
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errores[] = "Error al guardar en la base de datos: " . $e->getMessage();
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-h">
    <div>
        <h1>Registrar Nueva Incidencia</h1>
        <p>Complete los datos para generar el reporte de avería o mantenimiento en el campus.</p>
    </div>
    <div>
        <a href="bandeja.php" class="btn">
            <i data-lucide="arrow-left"></i>
            <span>Volver a la Bandeja</span>
        </a>
    </div>
</div>

<?php if (!empty($errores)): ?>
    <div class="alert-flash alert-danger">
        <i data-lucide="alert-octagon"></i>
        <div>
            <b>Por favor corrija los siguientes inconvenientes:</b>
            <ul style="margin-left: 20px; margin-top: 4px;">
                <?php foreach ($errores as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 860px;">
    <form method="POST" action="registrar.php" enctype="multipart/form-data">
        <div class="field">
            <label for="titulo">Título breve de la incidencia *</label>
            <input type="text" id="titulo" name="titulo" placeholder="ej. Proyector multimedia no emite imagen en Lab D" value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>" required maxlength="200">
        </div>

        <div class="row row-2">
            <div class="field">
                <label for="idUbic">Ubicación física (Campus / Pabellón / Aula) *</label>
                <select id="idUbic" name="idUbic" required>
                    <option value="">-- Seleccione Ubicación --</option>
                    <?php 
                    $currentCampus = '';
                    foreach ($ubicaciones as $ub): 
                        if ($ub['campus'] !== $currentCampus) {
                            if ($currentCampus !== '') echo '</optgroup>';
                            $currentCampus = $ub['campus'];
                            echo '<optgroup label="' . htmlspecialchars($currentCampus) . '">';
                        }
                    ?>
                        <option value="<?= $ub['idUbic'] ?>" <?= (isset($_POST['idUbic']) && (int)$_POST['idUbic'] === (int)$ub['idUbic']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ub['pabellon']) ?> - <?= htmlspecialchars($ub['aula_espacio']) ?> (Piso <?= htmlspecialchars($ub['piso']) ?>)
                        </option>
                    <?php endforeach; ?>
                    <?php if ($currentCampus !== '') echo '</optgroup>'; ?>
                </select>
            </div>

            <div class="field">
                <label for="idSubCateg">Categoría y Tipo de Problema *</label>
                <select id="idSubCateg" name="idSubCateg" required>
                    <option value="">-- Seleccione Categoría --</option>
                    <?php 
                    $currentCat = '';
                    foreach ($subcategorias as $sc): 
                        if ($sc['nomCateg'] !== $currentCat) {
                            if ($currentCat !== '') echo '</optgroup>';
                            $currentCat = $sc['nomCateg'];
                            echo '<optgroup label="' . htmlspecialchars($currentCat) . '">';
                        }
                    ?>
                        <option value="<?= $sc['idSubCateg'] ?>" <?= (isset($_POST['idSubCateg']) && (int)$_POST['idSubCateg'] === (int)$sc['idSubCateg']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sc['nomSubCatg']) ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if ($currentCat !== '') echo '</optgroup>'; ?>
                </select>
            </div>
        </div>

        <div class="row row-2">
            <div class="field">
                <label for="prioridad">Nivel de Criticidad Inicial</label>
                <select id="prioridad" name="prioridad">
                    <option value="Baja" <?= (($_POST['prioridad'] ?? '') === 'Baja') ? 'selected' : '' ?>>Baja (No interrumpe actividades)</option>
                    <option value="Media" <?= (($_POST['prioridad'] ?? 'Media') === 'Media') ? 'selected' : '' ?>>Media (Dificultad parcial de aula)</option>
                    <option value="Alta" <?= (($_POST['prioridad'] ?? '') === 'Alta') ? 'selected' : '' ?>>Alta (Afecta clase o sesión en curso)</option>
                    <option value="Critica" <?= (($_POST['prioridad'] ?? '') === 'Critica') ? 'selected' : '' ?>>Crítica (Riesgo eléctrico o estructural)</option>
                </select>
            </div>

            <div class="field">
                <label for="evidencia">Evidencia Fotográfica (Opcional - Máx 5MB)</label>
                <input type="file" id="evidencia" name="evidencia" accept="image/*">
            </div>
        </div>

        <div class="field">
            <label for="descripcion">Descripción detallada del problema *</label>
            <textarea id="descripcion" name="descripcion" placeholder="Describa el comportamiento de la avería, número de equipos afectados o cualquier detalle útil para el personal técnico..." required rows="5"><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:20px;border-top:1px solid var(--border);padding-top:16px">
            <a href="bandeja.php" class="btn">Cancelar</a>
            <button type="submit" class="btn btn-primary">
                <i data-lucide="check"></i>
                <span>Enviar y Registrar Reporte</span>
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>