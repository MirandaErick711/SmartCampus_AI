<?php
// =====================================================================
// SMARTCAMPUS AI - Backend API de Persistencia (Semana 6)
// Sincronizado con nombres de campos exactos del Diagrama ER
// =====================================================================
header('Content-Type: application/json; charset=utf-8');

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'smartcampus_db';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'error' => 'Error de conexión MySQL: ' . $conn->connect_error
    ]);
    exit;
}

$conn->set_charset('utf8mb4');

$action = $_GET['action'] ?? $_POST['action'] ?? 'test';

switch ($action) {
    case 'test':
        echo json_encode([
            'success' => true,
            'message' => 'Conexión a MySQL exitosa',
            'database' => $db
        ]);
        break;

    case 'get_incidencias':
        // Conexión lineal: Incidencias -> SubCategorias -> Categorias
        $sql = "SELECT i.idIncidencia, i.codIncdencia, i.titulo, i.descripIncidencia, i.prioridad, i.estado, 
                       u.campus, u.pabellon, u.aula_espacio,
                       c.nomCateg AS categoria,
                       s.nomSubCatg AS subcategoria,
                       rep.nomUsuario AS reportante,
                       tec.nomUsuario AS tecnico,
                       i.creadoIncd
                FROM Incidencias i
                JOIN Ubicaciones u ON i.idUbic = u.idUbic
                JOIN SubCategorias s ON i.idSubCateg = s.idSubCateg
                JOIN Categorias c ON s.idCategoria = c.idCategoria
                JOIN Usuarios rep ON i.idReportante = rep.idUsuario
                LEFT JOIN Usuarios tec ON i.idTecnico = tec.idUsuario
                ORDER BY i.idIncidencia DESC";
        $res = $conn->query($sql);
        $data = [];
        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'total' => count($data), 'data' => $data]);
        break;

    case 'create_incidencia':
        $titulo          = trim($_POST['titulo'] ?? '');
        $descripcion     = trim($_POST['descripIncidencia'] ?? $_POST['descripcion'] ?? '');
        $idUbic          = intval($_POST['idUbic'] ?? $_POST['ubicacion_id'] ?? 1);
        $idSubCateg      = intval($_POST['idSubCateg'] ?? $_POST['subcategoria_id'] ?? 1);
        $prioridad       = trim($_POST['prioridad'] ?? 'Media');
        $idReportante    = intval($_POST['idReportante'] ?? $_POST['reportante_id'] ?? 1);

        if (empty($titulo) || empty($descripcion)) {
            echo json_encode(['success' => false, 'error' => 'El título y la descripción son obligatorios']);
            exit;
        }

        // Obtener nombre del reportante para el historial (desacoplado sin bucle FK)
        $resRep = $conn->query("SELECT nomUsuario FROM Usuarios WHERE idUsuario = $idReportante");
        $nombreReportante = ($resRep && $row = $resRep->fetch_assoc()) ? $row['nomUsuario'] : 'Usuario del Sistema';

        // Generar código correlativo
        $resCode = $conn->query("SELECT COUNT(*) AS total FROM Incidencias");
        $total = ($resCode->fetch_assoc()['total'] ?? 0) + 1;
        $codIncdencia = 'INC-2026-' . str_pad($total, 4, '0', STR_PAD_LEFT);

        $stmt = $conn->prepare("INSERT INTO Incidencias (codIncdencia, titulo, descripIncidencia, idUbic, idSubCateg, prioridad, estado, idReportante) VALUES (?, ?, ?, ?, ?, ?, 'Registrada', ?)");
        $stmt->bind_param("sssiisi", $codIncdencia, $titulo, $descripcion, $idUbic, $idSubCateg, $prioridad, $idReportante);

        if ($stmt->execute()) {
            $nuevoId = $stmt->insert_id;
            
            // Historial inmutable desacoplado
            $stmtHist = $conn->prepare("INSERT INTO Historial_Incidencias (idIncidencia, estado_anterior, estado_nuevo, cambiado_por, nota) VALUES (?, NULL, 'Registrada', ?, 'Incidencia registrada en el sistema')");
            $stmtHist->bind_param("is", $nuevoId, $nombreReportante);
            $stmtHist->execute();

            echo json_encode([
                'success' => true,
                'message' => 'Incidencia registrada y guardada en MySQL exitosamente',
                'idIncidencia' => $nuevoId,
                'codIncdencia' => $codIncdencia
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
        break;

    case 'get_notificaciones':
        $idUsuario = intval($_GET['idUsuario'] ?? $_GET['usuario_id'] ?? 1);
        $stmt = $conn->prepare("SELECT idNotif, texto, tipo, idIncd, leida, creadoNotf FROM Notificaciones WHERE idUsuario = ? ORDER BY idNotif DESC LIMIT 15");
        $stmt->bind_param("i", $idUsuario);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = [];
        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'total' => count($data), 'data' => $data]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        break;
}

$conn->close();
?>
