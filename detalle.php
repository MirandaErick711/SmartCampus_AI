<?php
/**
 * SmartCampus AI - Detalle de Incidencia y Gestion de Estados (CRUD)
 */

require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$user = currentUser();
$rol = strtolower($user['nomRol'] ?? 'reportante');
$idIncidencia = (int)($_GET['id'] ?? 0);

if ($idIncidencia <= 0) {
    setFlash('danger', 'Identificador de incidencia no válido.');
    header("Location: bandeja.php");
    exit();
}

// 1. Obtener la informacion completa de la incidencia
$sqlInc = "
    SELECT 
        i.*,
        u.campus, u.pabellon, u.piso, u.aula_espacio, u.area_tipo,
        rep.nomUsuario as nomReportante, rep.email as emailReportante, rep.departamento as dptoReportante,
        tec.nomUsuario as nomTecnico, tec.email as emailTecnico,
        sc.nomSubCatg as subcategoria,
        c.nomCateg as categoria
    FROM incidencias i
    INNER JOIN usuarios rep ON i.idReportante = rep.idUsuario
    LEFT JOIN usuarios tec ON i.idTecnico = tec.idUsuario
    INNER JOIN ubicaciones u ON i.idUbic = u.idUbic
    INNER JOIN subcategorias sc ON i.idSubCateg = sc.idSubCateg
    INNER JOIN categorias c ON sc.idCategoria = c.idCategoria
    WHERE i.idIncidencia = :id
    LIMIT 1
";
$stmt = $pdo->prepare($sqlInc);
$stmt->execute([':id' => $idIncidencia]);
$inc = $stmt->fetch();

if (!$inc) {
    setFlash('danger', 'La incidencia solicitada no existe.');
    header("Location: bandeja.php");
    exit();
}

// Validacion de acceso por rol: Reportante solo ve sus incidencias
if ($rol === 'reportante' && (int)$inc['idReportante'] !== (int)$user['idUsuario']) {
    setFlash('danger', 'No tiene permisos para visualizar esta incidencia.');
    header("Location: bandeja.php");
    exit();
}

// 2. Procesar Acciones de Gestion de Incidencia (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = trim($_POST['accion'] ?? '');

    // Accion para Operador o Administrador: Asignar Tecnico, Cambiar Prioridad o Estado
    if (($rol === 'operador' || $rol === 'administrador') && $accion === 'actualizar_operador') {
        $nuevoEstado = trim($_POST['estado'] ?? $inc['estado']);
        $nuevaPrioridad = trim($_POST['prioridad'] ?? $inc['prioridad']);
        $nuevoTecnico = !empty($_POST['idTecnico']) ? (int)$_POST['idTecnico'] : null;
        $nota = trim($_POST['nota'] ?? 'Actualización efectuada por operador.');

        // Si se asigna técnico y estaba en Registrada/Revisada, pasar a Asignada
        if ($nuevoTecnico && in_array($nuevoEstado, ['Registrada', 'Revisada'])) {
            $nuevoEstado = 'Asignada';
        }

        try {
            $pdo->beginTransaction();

            $stmtUpd = $pdo->prepare("
                UPDATE incidencias 
                SET estado = :estado, prioridad = :prioridad, idTecnico = :idTecnico 
                WHERE idIncidencia = :id
            ");
            $stmtUpd->execute([
                ':estado'    => $nuevoEstado,
                ':prioridad' => $nuevaPrioridad,
                ':idTecnico' => $nuevoTecnico,
                ':id'        => $idIncidencia
            ]);

            // Registrar en Historial
            $stmtHist = $pdo->prepare("
                INSERT INTO historial_incidencias (idIncidencia, estado_anterior, estado_nuevo, cambiado_por, nota)
                VALUES (:id, :ant, :nuevo, :por, :nota)
            ");
            $stmtHist->execute([
                ':id'    => $idIncidencia,
                ':ant'   => $inc['estado'],
                ':nuevo' => $nuevoEstado,
                ':por'   => $user['nomUsuario'],
                ':nota'  => $nota
            ]);

            $pdo->commit();
            setFlash('success', 'Incidencia actualizada correctamente.');
            header("Location: detalle.php?id={$idIncidencia}");
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlash('danger', 'Error al actualizar: ' . $e->getMessage());
        }
    }

    // Accion para Tecnico: Actualizar estado a En atencion o Resuelta con informe tecnico
    if (($rol === 'tecnico' || $rol === 'administrador') && $accion === 'actualizar_tecnico') {
        $nuevoEstado = trim($_POST['estado'] ?? 'En atencion');
        $informe = trim($_POST['informe_tecnico'] ?? '');
        $nota = trim($_POST['nota'] ?? 'Avance reportado por el técnico asignado.');

        // Manejo de foto de solucion si se sube
        $rutaSolucion = $inc['imagen_solucion'];
        if (isset($_FILES['solucion']) && $_FILES['solucion']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['solucion']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $nombreArchivo = 'sol_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
                $destino = __DIR__ . '/assets/uploads/' . $nombreArchivo;
                if (move_uploaded_file($_FILES['solucion']['tmp_name'], $destino)) {
                    $rutaSolucion = 'assets/uploads/' . $nombreArchivo;
                }
            }
        }

        try {
            $pdo->beginTransaction();

            $stmtUpd = $pdo->prepare("
                UPDATE incidencias 
                SET estado = :estado, informe_tecnico = :informe, imagen_solucion = :solucion 
                WHERE idIncidencia = :id
            ");
            $stmtUpd->execute([
                ':estado'   => $nuevoEstado,
                ':informe'  => !empty($informe) ? $informe : $inc['informe_tecnico'],
                ':solucion' => $rutaSolucion,
                ':id'       => $idIncidencia
            ]);

            // Registrar en Historial
            $stmtHist = $pdo->prepare("
                INSERT INTO historial_incidencias (idIncidencia, estado_anterior, estado_nuevo, cambiado_por, nota)
                VALUES (:id, :ant, :nuevo, :por, :nota)
            ");
            $stmtHist->execute([
                ':id'    => $idIncidencia,
                ':ant'   => $inc['estado'],
                ':nuevo' => $nuevoEstado,
                ':por'   => $user['nomUsuario'],
                ':nota'  => $nota . ($informe ? " | Informe: {$informe}" : "")
            ]);

            $pdo->commit();
            setFlash('success', 'Avance técnico guardado con éxito.');
            header("Location: detalle.php?id={$idIncidencia}");
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlash('danger', 'Error al guardar avance técnico: ' . $e->getMessage());
        }
    }
}

// 3. Cargar lista de tecnicos disponibles para asignacion
$stmtTec = $pdo->query("
    SELECT u.idUsuario, u.nomUsuario, u.email 
    FROM usuarios u 
    INNER JOIN roles r ON u.idRol = r.idRol 
    WHERE r.nomRol = 'tecnico' AND u.activo = 1
");
$tecnicosDisponibles = $stmtTec->fetchAll();

// 4. Cargar Historial de Cambios / Trazabilidad
$stmtHist = $pdo->prepare("
    SELECT * FROM historial_incidencias 
    WHERE idIncidencia = :id 
    ORDER BY fecha ASC
");
$stmtHist->execute([':id' => $idIncidencia]);
$historial = $stmtHist->fetchAll();

$tituloPagina = "Detalle: " . $inc['codIncdencia'];
include __DIR__ . '/includes/header.php';
?>

<div class="page-h">
    <div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
            <span class="mono txt-g" style="font-size:18px;font-weight:700"><?= htmlspecialchars($inc['codIncdencia']) ?></span>
            <span class="badge b-<?= htmlspecialchars($inc['prioridad']) ?>"><?= htmlspecialchars($inc['prioridad']) ?></span>
            <span class="badge e-<?= str_replace(' ', '-', htmlspecialchars($inc['estado'])) ?>"><?= htmlspecialchars($inc['estado']) ?></span>
        </div>
        <h1><?= htmlspecialchars($inc['titulo']) ?></h1>
    </div>
    <div>
        <a href="bandeja.php" class="btn">
            <i data-lucide="arrow-left"></i>
            <span>Volver a la Bandeja</span>
        </a>
    </div>
</div>

<div class="grid g-21">
    <!-- Columna Principal: Datos, Descripcion e Imagenes -->
    <div style="display:flex;flex-direction:column;gap:16px">
        <!-- Tarjeta de Descripcion y Datos Generales -->
        <div class="card">
            <div class="card-h">
                <h3><i data-lucide="file-text"></i> Descripción de la Avería</h3>
                <span class="muted" style="font-size:12px">
                    Registrado el <?= date('d/m/Y H:i', strtotime($inc['creadoIncd'])) ?>
                </span>
            </div>

            <div style="font-size:14px;line-height:1.6;color:#e1e5ee;white-space:pre-line;padding:6px 0">
                <?= nl2br(htmlspecialchars($inc['descripIncidencia'])) ?>
            </div>

            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
                <div>
                    <label class="lbl">Ubicación Física</label>
                    <div style="font-weight:600;margin-top:3px"><?= htmlspecialchars($inc['pabellon']) ?> - <?= htmlspecialchars($inc['aula_espacio']) ?></div>
                    <small class="muted"><?= htmlspecialchars($inc['campus']) ?> &bull; Piso <?= htmlspecialchars($inc['piso']) ?> &bull; Área <?= htmlspecialchars($inc['area_tipo']) ?></small>
                </div>

                <div>
                    <label class="lbl">Categoría y Especialidad</label>
                    <div style="font-weight:600;margin-top:3px"><?= htmlspecialchars($inc['categoria']) ?></div>
                    <small class="muted"><?= htmlspecialchars($inc['subcategoria']) ?></small>
                </div>

                <div>
                    <label class="lbl">Reportado Por</label>
                    <div style="font-weight:600;margin-top:3px"><?= htmlspecialchars($inc['nomReportante']) ?></div>
                    <small class="muted"><?= htmlspecialchars($inc['emailReportante']) ?></small>
                </div>

                <div>
                    <label class="lbl">Técnico Responsable</label>
                    <div style="font-weight:600;margin-top:3px;color:<?= $inc['nomTecnico'] ? 'var(--c)' : 'var(--muted)' ?>">
                        <?= $inc['nomTecnico'] ? htmlspecialchars($inc['nomTecnico']) : 'Sin técnico asignado' ?>
                    </div>
                    <?php if ($inc['emailTecnico']): ?>
                        <small class="muted"><?= htmlspecialchars($inc['emailTecnico']) ?></small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Evidencias Fotograficas -->
        <div class="card">
            <div class="card-h">
                <h3><i data-lucide="image"></i> Evidencias Fotograficas</h3>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:14px">
                <div>
                    <label class="lbl" style="margin-bottom:6px;display:block">Evidencia de Avería (Inicial)</label>
                    <?php if (!empty($inc['imagen_evidencia']) && file_exists(__DIR__ . '/' . $inc['imagen_evidencia'])): ?>
                        <a href="<?= htmlspecialchars($inc['imagen_evidencia']) ?>" target="_blank">
                            <img src="<?= htmlspecialchars($inc['imagen_evidencia']) ?>" alt="Evidencia" style="width:100%;height:180px;object-fit:cover;border-radius:10px;border:1px solid var(--border)">
                        </a>
                    <?php else: ?>
                        <div style="height:140px;background:rgba(255,255,255,0.03);border:1px dashed var(--border);border-radius:10px;display:grid;place-items:center;color:var(--dim)">
                            <span>Sin fotografía de avería</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="lbl" style="margin-bottom:6px;display:block">Fotografía de Solución (Final)</label>
                    <?php if (!empty($inc['imagen_solucion']) && file_exists(__DIR__ . '/' . $inc['imagen_solucion'])): ?>
                        <a href="<?= htmlspecialchars($inc['imagen_solucion']) ?>" target="_blank">
                            <img src="<?= htmlspecialchars($inc['imagen_solucion']) ?>" alt="Solución" style="width:100%;height:180px;object-fit:cover;border-radius:10px;border:1px solid var(--border)">
                        </a>
                    <?php else: ?>
                        <div style="height:140px;background:rgba(255,255,255,0.03);border:1px dashed var(--border);border-radius:10px;display:grid;place-items:center;color:var(--dim)">
                            <span>Sin fotografía de solución</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($inc['informe_tecnico'])): ?>
                <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border)">
                    <label class="lbl">Informe Técnico de Cierre</label>
                    <div style="padding:10px 14px;background:rgba(0,255,102,0.05);border:1px solid rgba(0,255,102,0.2);border-radius:8px;margin-top:6px;font-size:13.5px">
                        <?= nl2br(htmlspecialchars($inc['informe_tecnico'])) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Trazabilidad / Historial de Estados -->
        <div class="card">
            <div class="card-h">
                <h3><i data-lucide="history"></i> Trazabilidad y Línea de Tiempo</h3>
            </div>
            
            <div style="display:flex;flex-direction:column;gap:12px;margin-top:8px">
                <?php foreach ($historial as $h): ?>
                    <div style="display:flex;gap:12px;padding:10px 12px;background:rgba(255,255,255,0.02);border-left:3px solid var(--g);border-radius:6px">
                        <div style="min-width:0;flex:1">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px">
                                <b>Estado: <?= htmlspecialchars($h['estado_nuevo']) ?></b>
                                <small class="muted"><?= date('d/m/Y H:i:s', strtotime($h['fecha'])) ?></small>
                            </div>
                            <div style="font-size:12px;color:var(--muted);margin-top:2px">
                                Actualizado por: <span style="color:#fff"><?= htmlspecialchars($h['cambiado_por']) ?></span>
                            </div>
                            <?php if (!empty($h['nota'])): ?>
                                <div style="font-size:13px;margin-top:4px;color:#d5d9e2">
                                    <?= htmlspecialchars($h['nota']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Columna Lateral: Panel de Acciones segun Rol -->
    <div>
        <!-- Panel Operador / Administrador -->
        <?php if ($rol === 'operador' || $rol === 'administrador'): ?>
            <div class="card" style="margin-bottom:16px;border-color:rgba(255,184,0,0.4)">
                <div class="card-h">
                    <h3 style="color:var(--a)"><i data-lucide="sliders"></i> Gestión Operativa</h3>
                </div>
                <form method="POST" action="detalle.php?id=<?= $idIncidencia ?>">
                    <input type="hidden" name="accion" value="actualizar_operador">

                    <div class="field">
                        <label for="estado">Estado del Ticket</label>
                        <select id="estado" name="estado">
                            <option value="Registrada" <?= $inc['estado'] === 'Registrada' ? 'selected' : '' ?>>Registrada</option>
                            <option value="Revisada" <?= $inc['estado'] === 'Revisada' ? 'selected' : '' ?>>Revisada</option>
                            <option value="Asignada" <?= $inc['estado'] === 'Asignada' ? 'selected' : '' ?>>Asignada</option>
                            <option value="En atencion" <?= $inc['estado'] === 'En atencion' ? 'selected' : '' ?>>En atención</option>
                            <option value="Resuelta" <?= $inc['estado'] === 'Resuelta' ? 'selected' : '' ?>>Resuelta</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="prioridad">Nivel de Prioridad</label>
                        <select id="prioridad" name="prioridad">
                            <option value="Baja" <?= $inc['prioridad'] === 'Baja' ? 'selected' : '' ?>>Baja</option>
                            <option value="Media" <?= $inc['prioridad'] === 'Media' ? 'selected' : '' ?>>Media</option>
                            <option value="Alta" <?= $inc['prioridad'] === 'Alta' ? 'selected' : '' ?>>Alta</option>
                            <option value="Critica" <?= $inc['prioridad'] === 'Critica' ? 'selected' : '' ?>>Crítica</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="idTecnico">Asignar Técnico Responsable</label>
                        <select id="idTecnico" name="idTecnico">
                            <option value="">-- Sin Técnico Asignado --</option>
                            <?php foreach ($tecnicosDisponibles as $t): ?>
                                <option value="<?= $t['idUsuario'] ?>" <?= ((int)$inc['idTecnico'] === (int)$t['idUsuario']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['nomUsuario']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="nota_op">Observación de Operador</label>
                        <textarea id="nota_op" name="nota" rows="2" placeholder="Motivo del cambio de estado o indicación..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-amber btn-block" style="margin-top:6px">
                        <i data-lucide="save"></i>
                        <span>Guardar Cambios</span>
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Panel Tecnico -->
        <?php if ($rol === 'tecnico' || $rol === 'administrador'): ?>
            <div class="card" style="border-color:rgba(0,229,255,0.4)">
                <div class="card-h">
                    <h3 style="color:var(--c)"><i data-lucide="wrench"></i> Atención Técnica</h3>
                </div>
                <form method="POST" action="detalle.php?id=<?= $idIncidencia ?>" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="actualizar_tecnico">

                    <div class="field">
                        <label for="estado_tec">Actualizar Estado Técnico</label>
                        <select id="estado_tec" name="estado">
                            <option value="En atencion" <?= $inc['estado'] === 'En atencion' ? 'selected' : '' ?>>En atención (Iniciando trabajos)</option>
                            <option value="Resuelta" <?= $inc['estado'] === 'Resuelta' ? 'selected' : '' ?>>Resuelta (Trabajos concluidos)</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="informe_tecnico">Informe / Detalles del Servicio</label>
                        <textarea id="informe_tecnico" name="informe_tecnico" rows="3" placeholder="Detalle las acciones realizadas, repuestos reemplazados o pruebas realizadas..."><?= htmlspecialchars($inc['informe_tecnico'] ?? '') ?></textarea>
                    </div>

                    <div class="field">
                        <label for="solucion">Fotografía de Conformidad / Solución</label>
                        <input type="file" id="solucion" name="solucion" accept="image/*">
                    </div>

                    <div class="field">
                        <label for="nota_tec">Comentario para Historial</label>
                        <input type="text" id="nota_tec" name="nota" placeholder="ej. Pruebas de encendido y señal conformes.">
                    </div>

                    <button type="submit" class="btn btn-cyan btn-block" style="margin-top:6px">
                        <i data-lucide="check-square"></i>
                        <span>Registrar Avance Técnico</span>
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Panel Informativo Reportante -->
        <?php if ($rol === 'reportante'): ?>
            <div class="card">
                <div class="card-h">
                    <h3><i data-lucide="info"></i> Estado del Ticket</h3>
                </div>
                <p style="font-size:13px;color:var(--muted)">
                    Su reporte está siendo procesado por el personal de infraestructura y soporte de la UNS. Recibirá actualizaciones en esta misma pantalla conforme cambie de estado.
                </p>
                <div style="margin-top:14px;padding:12px;background:rgba(255,255,255,0.03);border-radius:8px">
                    <div style="font-size:12px;color:var(--muted)">Última actualización:</div>
                    <div style="font-weight:600;margin-top:2px"><?= date('d/m/Y H:i', strtotime($inc['actualizado'])) ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
