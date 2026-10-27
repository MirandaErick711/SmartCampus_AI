<?php
/**
 * SmartCampus AI - Directorio de Ubicaciones y Aulas
 */

require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/auth.php';

requireRole(['administrador', 'operador']);

$tituloPagina = 'Ubicaciones del Campus';

// Cargar ubicaciones con conteo de incidencias activas
$sql = "
    SELECT 
        u.*,
        COUNT(i.idIncidencia) as total_incidencias,
        SUM(CASE WHEN i.estado IN ('Registrada', 'Revisada', 'Asignada', 'En atencion') THEN 1 ELSE 0 END) as activas
    FROM ubicaciones u
    LEFT JOIN incidencias i ON u.idUbic = i.idUbic
    GROUP BY u.idUbic
    ORDER BY u.campus, u.pabellon, u.aula_espacio
";
$stmt = $pdo->query($sql);
$ubicaciones = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="page-h">
    <div>
        <h1>Directorio de Ubicaciones y Pabellones</h1>
        <p>Espacios académicos, laboratorios y áreas administrativas censadas en la UNS.</p>
    </div>
</div>

<div class="card">
    <div class="card-h">
        <h3><i data-lucide="map-pin"></i> Espacios Físicos Registrados (<?= count($ubicaciones) ?>)</h3>
    </div>

    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Campus</th>
                    <th>Pabellón / Edificio</th>
                    <th>Piso</th>
                    <th>Aula / Laboratorio</th>
                    <th>Tipo de Área</th>
                    <th style="text-align:center">Incidencias Activas</th>
                    <th style="text-align:center">Historial Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ubicaciones as $ub): ?>
                    <tr>
                        <td style="font-weight:600"><?= htmlspecialchars($ub['campus']) ?></td>
                        <td><?= htmlspecialchars($ub['pabellon']) ?></td>
                        <td class="muted">Piso <?= htmlspecialchars($ub['piso']) ?></td>
                        <td><b style="color:var(--g)"><?= htmlspecialchars($ub['aula_espacio']) ?></b></td>
                        <td>
                            <span class="chip"><?= htmlspecialchars($ub['area_tipo']) ?></span>
                        </td>
                        <td style="text-align:center">
                            <?php if ($ub['activas'] > 0): ?>
                                <span class="badge b-Alta"><?= $ub['activas'] ?> activas</span>
                            <?php else: ?>
                                <span class="badge b-Media">Sin averías</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center" class="muted">
                            <?= $ub['total_incidencias'] ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
