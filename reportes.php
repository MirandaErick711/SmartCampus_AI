<?php
/**
 * SmartCampus AI - Reportes Operativos y Estadisticas
 */

require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/auth.php';

requireRole(['administrador', 'operador']);

$tituloPagina = 'Reportes y Estadísticas';

// 1. Resumen por Categoria
$stmtCat = $pdo->query("
    SELECT c.nomCateg, COUNT(i.idIncidencia) as total,
           SUM(CASE WHEN i.estado = 'Resuelta' THEN 1 ELSE 0 END) as resueltas,
           SUM(CASE WHEN i.estado != 'Resuelta' THEN 1 ELSE 0 END) as pendientes
    FROM categorias c
    LEFT JOIN subcategorias sc ON c.idCategoria = sc.idCategoria
    LEFT JOIN incidencias i ON sc.idSubCateg = i.idSubCateg
    GROUP BY c.idCategoria
    ORDER BY total DESC
");
$statsCategorias = $stmtCat->fetchAll();

// 2. Rendimiento del Personal Tecnico
$stmtTec = $pdo->query("
    SELECT u.nomUsuario, u.email,
           COUNT(i.idIncidencia) as asignadas,
           SUM(CASE WHEN i.estado = 'En atencion' THEN 1 ELSE 0 END) as en_proceso,
           SUM(CASE WHEN i.estado = 'Resuelta' THEN 1 ELSE 0 END) as concluidas
    FROM usuarios u
    INNER JOIN roles r ON u.idRol = r.idRol
    LEFT JOIN incidencias i ON u.idUsuario = i.idTecnico
    WHERE r.nomRol = 'tecnico'
    GROUP BY u.idUsuario
    ORDER BY concluidas DESC
");
$statsTecnicos = $stmtTec->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="page-h">
    <div>
        <h1>Reportes Operativos y Desempeño</h1>
        <p>Consolidado estadístico de atención por especialidad y carga de trabajo técnico.</p>
    </div>
</div>

<div class="grid g-2">
    <!-- Tabla 1: Reportes por Categoría -->
    <div class="card">
        <div class="card-h">
            <h3><i data-lucide="folder"></i> Incidencias por Categoría</h3>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Categoría</th>
                        <th style="text-align:center">Total</th>
                        <th style="text-align:center">Atendidas</th>
                        <th style="text-align:center">Pendientes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($statsCategorias as $cat): ?>
                        <tr>
                            <td style="font-weight:600"><?= htmlspecialchars($cat['nomCateg']) ?></td>
                            <td style="text-align:center;font-weight:700"><?= $cat['total'] ?></td>
                            <td style="text-align:center;color:var(--g)"><?= $cat['resueltas'] ?></td>
                            <td style="text-align:center;color:var(--a)"><?= $cat['pendientes'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tabla 2: Rendimiento de Técnicos -->
    <div class="card">
        <div class="card-h">
            <h3><i data-lucide="wrench"></i> Asignación a Personal Técnico</h3>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Técnico</th>
                        <th style="text-align:center">Asignadas</th>
                        <th style="text-align:center">En Proceso</th>
                        <th style="text-align:center">Resueltas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($statsTecnicos as $tec): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600"><?= htmlspecialchars($tec['nomUsuario']) ?></div>
                                <small class="muted"><?= htmlspecialchars($tec['email']) ?></small>
                            </td>
                            <td style="text-align:center;font-weight:700"><?= $tec['asignadas'] ?></td>
                            <td style="text-align:center;color:var(--c)"><?= $tec['en_proceso'] ?></td>
                            <td style="text-align:center;color:var(--g)"><?= $tec['concluidas'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
