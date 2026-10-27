-- =====================================================================
-- SMARTCAMPUS AI - Consultas de Verificacion y Reporte
-- =====================================================================

USE smartcampus_db;

-- 1. Consulta Principal con JOIN completo (Evidencia de Integridad Referencial)
SELECT 
    i.codIncdencia AS Codigo,
    i.titulo AS Titulo,
    c.nomCateg AS Categoria,
    s.nomSubCatg AS Subcategoria,
    u.campus AS Campus,
    u.pabellon AS Pabellon,
    u.aula_espacio AS Aula,
    rep.nomUsuario AS Reportante,
    IFNULL(tec.nomUsuario, 'Sin asignar') AS Tecnico,
    i.prioridad AS Prioridad,
    i.estado AS Estado,
    i.creadoIncd AS Fecha_Reporte
FROM Incidencias i
JOIN SubCategorias s ON i.idSubCateg = s.idSubCateg
JOIN Categorias c ON s.idCategoria = c.idCategoria
JOIN Ubicaciones u ON i.idUbic = u.idUbic
JOIN Usuarios rep ON i.idReportante = rep.idUsuario
LEFT JOIN Usuarios tec ON i.idTecnico = tec.idUsuario
ORDER BY i.idIncidencia ASC;

-- 2. Trazabilidad del Ciclo de Vida (Historial de Estados de una Incidencia)
SELECT 
    i.codIncdencia AS Incidencia,
    h.estado_anterior AS Estado_Previo,
    h.estado_nuevo AS Estado_Nuevo,
    h.cambiado_por AS Responsable,
    h.nota AS Observacion,
    h.fecha AS Fecha_Hora
FROM Historial_Incidencias h
JOIN Incidencias i ON h.idIncidencia = i.idIncidencia
WHERE i.codIncdencia = 'INC-2026-0001'
ORDER BY h.idHistorial ASC;

-- 3. Resumen Estadístico por Categoría y Estado
SELECT 
    c.nomCateg AS Categoria,
    COUNT(i.idIncidencia) AS Total_Incidencias,
    SUM(CASE WHEN i.estado = 'Resuelta' THEN 1 ELSE 0 END) AS Resueltas,
    SUM(CASE WHEN i.estado != 'Resuelta' THEN 1 ELSE 0 END) AS Pendientes
FROM Categorias c
JOIN SubCategorias s ON c.idCategoria = s.idCategoria
LEFT JOIN Incidencias i ON s.idSubCateg = i.idSubCateg
GROUP BY c.idCategoria, c.nomCateg;
