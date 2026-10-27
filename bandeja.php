<?php
/**
 * SmartCampus AI - Bandeja de Incidencias y Seguimiento de Tickets
 */

require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$tituloPagina = 'Bandeja y Seguimiento';
$user = currentUser();
$rol = strtolower($user['nomRol'] ?? 'reportante');

// Filtros opcionales para Operador y Administrador
$filtroEstado = trim($_GET['estado'] ?? '');
$filtroPrioridad = trim($_GET['prioridad'] ?? '');
$busqueda = trim($_GET['q'] ?? '');

// Construir consulta SQL segun el rol (RBAC)
$sql = "
    SELECT 
        i.idIncidencia, i.codIncdencia, i.titulo, i.prioridad, i.estado, i.creadoIncd, i.imagen_evidencia,
        u.campus, u.pabellon, u.aula_espacio,
        usr.nomUsuario as reportante,
        tec.nomUsuario as tecnico,
        sc.nomSubCatg as subcategoria,
        c.nomCateg as categoria
    FROM incidencias i
    INNER JOIN usuarios usr ON i.idReportante = usr.idUsuario
    LEFT JOIN usuarios tec ON i.idTecnico = tec.idUsuario
    INNER JOIN ubicaciones u ON i.idUbic = u.idUbic
    INNER JOIN subcategorias sc ON i.idSubCateg = sc.idSubCateg
    INNER JOIN categorias c ON sc.idCategoria = c.idCategoria
    WHERE 1=1
";

$params = [];

// Restriccion por rol
if ($rol === 'reportante') {
    $sql .= " AND i.idReportante = :idUser";
    $params[':idUser'] = $user['idUsuario'];
} elseif ($rol === 'tecnico') {
    $sql .= " AND i.idTecnico = :idUser";
    $params[':idUser'] = $user['idUsuario'];
} else {
    // Operador y Administrador pueden aplicar filtros
    if (!empty($filtroEstado)) {
        $sql .= " AND i.estado = :estado";
        $params[':estado'] = $filtroEstado;
    }
    if (!empty($filtroPrioridad)) {
        $sql .= " AND i.prioridad = :prioridad";
        $params[':prioridad'] = $filtroPrioridad;
    }
    if (!empty($busqueda)) {
        $sql .= " AND (i.titulo LIKE :q OR i.codIncdencia LIKE :q OR u.pabellon LIKE :q)";
        $params[':q'] = "%{$busqueda}%";
    }
}

$sql .= " ORDER BY i.creadoIncd DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$incidencias = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="page-h">
    <div>
        <h1>Bandeja de Incidencias</h1>
        <p>
            <?php if ($rol === 'reportante'): ?>
                Historial y estado de las incidencias reportadas por su cuenta.
            <?php elseif ($rol === 'tecnico'): ?>
                Órdenes de servicio y tareas asignadas para su atención técnica.
            <?php else: ?>
                Seguimiento global, auditoría y despacho de averías en el campus.
            <?php endif; ?>
        </p>
    </div>
    <div>
        <?php if ($rol === 'reportante' || $rol === 'administrador'): ?>
            <a href="registrar.php" class="btn btn-primary">
                <i data-lucide="plus-circle"></i>
                <span>Nuevo Reporte</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filtros de búsqueda (Disponibles para Operador y Admin) -->
<?php if ($rol === 'operador' || $rol === 'administrador'): ?>
    <div class="card" style="margin-bottom: 20px; padding: 14px 18px;">
        <form method="GET" action="bandeja.php" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
            <div class="field" style="flex:2;min-width:200px;margin-bottom:0">
                <label for="q">Buscar por código, título o pabellón</label>
                <input type="text" id="q" name="q" placeholder="ej. INC-2026-0001, proyector, Sistemas..." value="<?= htmlspecialchars($busqueda) ?>">
            </div>

            <div class="field" style="flex:1;min-width:140px;margin-bottom:0">
                <label for="estado">Estado</label>
                <select id="estado" name="estado">
                    <option value="">Todos los estados</option>
                    <option value="Registrada" <?= $filtroEstado === 'Registrada' ? 'selected' : '' ?>>Registrada</option>
                    <option value="Revisada" <?= $filtroEstado === 'Revisada' ? 'selected' : '' ?>>Revisada</option>
                    <option value="Asignada" <?= $filtroEstado === 'Asignada' ? 'selected' : '' ?>>Asignada</option>
                    <option value="En atencion" <?= $filtroEstado === 'En atencion' ? 'selected' : '' ?>>En atención</option>
                    <option value="Resuelta" <?= $filtroEstado === 'Resuelta' ? 'selected' : '' ?>>Resuelta</option>
                </select>
            </div>

            <div class="field" style="flex:1;min-width:140px;margin-bottom:0">
                <label for="prioridad">Prioridad</label>
                <select id="prioridad" name="prioridad">
                    <option value="">Todas</option>
                    <option value="Baja" <?= $filtroPrioridad === 'Baja' ? 'selected' : '' ?>>Baja</option>
                    <option value="Media" <?= $filtroPrioridad === 'Media' ? 'selected' : '' ?>>Media</option>
                    <option value="Alta" <?= $filtroPrioridad === 'Alta' ? 'selected' : '' ?>>Alta</option>
                    <option value="Critica" <?= $filtroPrioridad === 'Critica' ? 'selected' : '' ?>>Crítica</option>
                </select>
            </div>

            <div style="display:flex;gap:8px">
                <button type="submit" class="btn">
                    <i data-lucide="filter"></i>
                    <span>Filtrar</span>
                </button>
                <?php if (!empty($filtroEstado) || !empty($filtroPrioridad) || !empty($busqueda)): ?>
                    <a href="bandeja.php" class="btn btn-sm">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- Listado de Incidencias -->
<div class="card">
    <div class="card-h">
        <h3>
            <i data-lucide="list"></i> 
            <span>Listado de Incidencias (<?= count($incidencias) ?>)</span>
        </h3>
    </div>

    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Título e Incidencia</th>
                    <th>Ubicación</th>
                    <th>Reportado Por</th>
                    <th>Técnico Asignado</th>
                    <th>Criticidad</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                    <th style="text-align:center">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($incidencias)): ?>
                    <tr>
                        <td colspan="9" class="empty">
                            <i data-lucide="inbox"></i>
                            <p>No se encontraron incidencias que coincidan con los criterios.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($incidencias as $inc): ?>
                        <tr>
                            <td>
                                <b class="mono txt-g"><?= htmlspecialchars($inc['codIncdencia']) ?></b>
                            </td>
                            <td style="max-width:280px">
                                <div style="font-weight:600;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= htmlspecialchars($inc['titulo']) ?>">
                                    <?= htmlspecialchars($inc['titulo']) ?>
                                </div>
                                <small class="muted"><?= htmlspecialchars($inc['subcategoria']) ?></small>
                            </td>
                            <td>
                                <div style="font-weight:500"><?= htmlspecialchars($inc['pabellon']) ?></div>
                                <small class="muted"><?= htmlspecialchars($inc['campus']) ?> &bull; <?= htmlspecialchars($inc['aula_espacio']) ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($inc['reportante']) ?>
                            </td>
                            <td>
                                <?php if ($inc['tecnico']): ?>
                                    <span style="color:var(--c);font-weight:500;display:flex;align-items:center;gap:4px">
                                        <i data-lucide="user-check" style="width:14px;height:14px"></i>
                                        <?= htmlspecialchars($inc['tecnico']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="dim" style="font-style:italic">Sin asignar</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge b-<?= htmlspecialchars($inc['prioridad']) ?>">
                                    <?= htmlspecialchars($inc['prioridad']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge e-<?= str_replace(' ', '-', htmlspecialchars($inc['estado'])) ?>">
                                    <?= htmlspecialchars($inc['estado']) ?>
                                </span>
                            </td>
                            <td class="muted" style="white-space:nowrap;font-size:12px">
                                <?= date('d/m/Y H:i', strtotime($inc['creadoIncd'])) ?>
                            </td>
                            <td style="text-align:center">
                                <a href="detalle.php?id=<?= $inc['idIncidencia'] ?>" class="btn btn-sm">
                                    <i data-lucide="eye"></i>
                                    <span>Ver Detalle</span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
