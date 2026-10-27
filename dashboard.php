<?php
/**
 * SmartCampus AI - Dashboard Ejecutivo y Metricas Operativas
 */

require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/auth.php';

// Control de acceso RBAC: Operador y Administrador
requireRole(['administrador', 'operador']);

$tituloPagina = 'Panel de Control (Dashboard)';

// 1. Metricas Generales (KPIs)
$stmt = $pdo->query("SELECT COUNT(*) FROM incidencias");
$totalIncidencias = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM incidencias WHERE estado IN ('Registrada', 'Revisada')");
$pendientes = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM incidencias WHERE estado IN ('Asignada', 'En atencion')");
$enAtencion = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM incidencias WHERE estado = 'Resuelta'");
$resueltas = (int)$stmt->fetchColumn();

// 2. Conteo por Prioridad
$stmt = $pdo->query("
    SELECT prioridad, COUNT(*) as cantidad 
    FROM incidencias 
    GROUP BY prioridad
");
$prioridades = ['Baja' => 0, 'Media' => 0, 'Alta' => 0, 'Critica' => 0];
while ($row = $stmt->fetch()) {
    $prioridades[$row['prioridad']] = (int)$row['cantidad'];
}

// 3. Conteo por Campus
$stmt = $pdo->query("
    SELECT u.campus, COUNT(i.idIncidencia) as cantidad 
    FROM incidencias i 
    INNER JOIN ubicaciones u ON i.idUbic = u.idUbic 
    GROUP BY u.campus
");
$campusData = $stmt->fetchAll();

// 4. Ultimas Incidencias Registradas
$stmt = $pdo->query("
    SELECT 
        i.idIncidencia, i.codIncdencia, i.titulo, i.prioridad, i.estado, i.creadoIncd,
        u.campus, u.pabellon, u.aula_espacio,
        usr.nomUsuario as reportante,
        tec.nomUsuario as tecnico,
        sc.nomSubCatg as subcategoria
    FROM incidencias i
    INNER JOIN usuarios usr ON i.idReportante = usr.idUsuario
    LEFT JOIN usuarios tec ON i.idTecnico = tec.idUsuario
    INNER JOIN ubicaciones u ON i.idUbic = u.idUbic
    INNER JOIN subcategorias sc ON i.idSubCateg = sc.idSubCateg
    ORDER BY i.creadoIncd DESC
    LIMIT 6
");
$ultimasIncidencias = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="page-h">
    <div>
        <h1>Dashboard General de Incidencias</h1>
        <p>Métricas y estado operativo del campus universitario en tiempo real.</p>
    </div>
    <div style="display:flex;gap:10px">
        <a href="bandeja.php" class="btn">
            <i data-lucide="list-filter"></i>
            <span>Ir a Bandeja</span>
        </a>
        <?php if ($rol === 'administrador'): ?>
            <a href="registrar.php" class="btn btn-primary">
                <i data-lucide="plus-circle"></i>
                <span>Nueva Incidencia</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Tarjetas KPI -->
<div class="grid g-kpi" style="grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 22px;">
    <div class="card kpi" style="--k: var(--g)">
        <div class="l">
            <span>Total Reportes</span>
            <i data-lucide="layers"></i>
        </div>
        <div class="v"><?= $totalIncidencias ?></div>
        <div class="s">Registradas en el sistema</div>
    </div>

    <div class="card kpi" style="--k: var(--a)">
        <div class="l">
            <span>Pendientes / Nuevas</span>
            <i data-lucide="clock"></i>
        </div>
        <div class="v"><?= $pendientes ?></div>
        <div class="s">Por evaluar o asignar</div>
    </div>

    <div class="card kpi" style="--k: var(--c)">
        <div class="l">
            <span>En Atención</span>
            <i data-lucide="wrench"></i>
        </div>
        <div class="v"><?= $enAtencion ?></div>
        <div class="s">Con técnico asignado</div>
    </div>

    <div class="card kpi" style="--k: var(--g)">
        <div class="l">
            <span>Resueltas</span>
            <i data-lucide="check-circle-2"></i>
        </div>
        <div class="v"><?= $resueltas ?></div>
        <div class="s">Conforme y verificado</div>
    </div>
</div>

<!-- Gráficas y Distribución -->
<div class="grid g-21" style="margin-bottom: 22px;">
    <div class="card">
        <div class="card-h">
            <h3><i data-lucide="bar-chart-2"></i> Incidencias Recientes</h3>
            <a href="bandeja.php" class="btn btn-sm">Ver todas</a>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Título</th>
                        <th>Ubicación</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ultimasIncidencias)): ?>
                        <tr>
                            <td colspan="6" class="empty">No hay incidencias registradas aún.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ultimasIncidencias as $inc): ?>
                            <tr>
                                <td><b class="mono txt-g"><?= htmlspecialchars($inc['codIncdencia']) ?></b></td>
                                <td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                    <?= htmlspecialchars($inc['titulo']) ?>
                                </td>
                                <td>
                                    <span class="muted" style="font-size:12px">
                                        <?= htmlspecialchars($inc['campus']) ?> &bull; <?= htmlspecialchars($inc['pabellon']) ?>
                                    </span>
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
                                <td>
                                    <a href="detalle.php?id=<?= $inc['idIncidencia'] ?>" class="btn btn-sm">
                                        <i data-lucide="eye"></i>
                                        <span>Detalle</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Panel Lateral: Distribución por Prioridad y Campus -->
    <div class="card">
        <div class="card-h">
            <h3><i data-lucide="pie-chart"></i> Distribución de Criticidad</h3>
        </div>

        <div style="display:flex;flex-direction:column;gap:14px;margin-top:10px">
            <div>
                <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
                    <span>Crítica</span>
                    <b class="txt-r"><?= $prioridades['Critica'] ?></b>
                </div>
                <div style="height:6px;background:rgba(255,255,255,0.06);border-radius:4px;overflow:hidden">
                    <div style="height:100%;background:var(--r);width:<?= $totalIncidencias > 0 ? ($prioridades['Critica'] / $totalIncidencias * 100) : 0 ?>%"></div>
                </div>
            </div>

            <div>
                <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
                    <span>Alta</span>
                    <b class="txt-a"><?= $prioridades['Alta'] ?></b>
                </div>
                <div style="height:6px;background:rgba(255,255,255,0.06);border-radius:4px;overflow:hidden">
                    <div style="height:100%;background:var(--a);width:<?= $totalIncidencias > 0 ? ($prioridades['Alta'] / $totalIncidencias * 100) : 0 ?>%"></div>
                </div>
            </div>

            <div>
                <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
                    <span>Media</span>
                    <b class="txt-g"><?= $prioridades['Media'] ?></b>
                </div>
                <div style="height:6px;background:rgba(255,255,255,0.06);border-radius:4px;overflow:hidden">
                    <div style="height:100%;background:var(--g);width:<?= $totalIncidencias > 0 ? ($prioridades['Media'] / $totalIncidencias * 100) : 0 ?>%"></div>
                </div>
            </div>

            <div>
                <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
                    <span>Baja</span>
                    <b class="muted"><?= $prioridades['Baja'] ?></b>
                </div>
                <div style="height:6px;background:rgba(255,255,255,0.06);border-radius:4px;overflow:hidden">
                    <div style="height:100%;background:var(--muted);width:<?= $totalIncidencias > 0 ? ($prioridades['Baja'] / $totalIncidencias * 100) : 0 ?>%"></div>
                </div>
            </div>
        </div>

        <div style="margin-top:24px;border-top:1px solid var(--border);padding-top:16px">
            <h4 style="font-size:13px;color:var(--muted);margin-bottom:10px;text-transform:uppercase;letter-spacing:0.5px">Incidencias por Campus</h4>
            <?php foreach ($campusData as $c): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;font-size:13px;border-bottom:1px solid rgba(255,255,255,0.04)">
                    <span style="display:flex;align-items:center;gap:6px">
                        <i data-lucide="map-pin" style="width:14px;height:14px;color:var(--g)"></i>
                        <?= htmlspecialchars($c['campus']) ?>
                    </span>
                    <b><?= $c['cantidad'] ?></b>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
