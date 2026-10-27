<?php
// =====================================================================
// SMARTCAMPUS AI - Backend API de Persistencia (Semana 6)
// Modelo Acíclico Normalizado (MySQL 8 / MariaDB XAMPP)
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
        // Conexión lineal: incidencias -> subcategorias -> categorias
        $sql = "SELECT i.id, i.codigo, i.titulo, i.descripcion, i.prioridad, i.estado, 
                       u.campus, u.pabellon, u.aula_espacio,
                       c.nombre AS categoria,
                       s.nombre AS subcategoria,
                       rep.nombre AS reportante,
                       tec.nombre AS tecnico,
                       i.creado_en
                FROM incidencias i
                JOIN ubicaciones u ON i.ubicacion_id = u.id
                JOIN subcategorias s ON i.subcategoria_id = s.id
                JOIN categorias c ON s.categoria_id = c.id
                JOIN usuarios rep ON i.reportante_id = rep.id
                LEFT JOIN usuarios tec ON i.tecnico_id = tec.id
                ORDER BY i.id DESC";
        $res = $conn->query($sql);
        $data = [];
        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'total' => count($data), 'data' => $data]);
        break;

    case 'create_incidencia':
        $titulo          = trim($_POST['titulo'] ?? '');
        $descripcion     = trim($_POST['descripcion'] ?? '');
        $ubicacion_id    = intval($_POST['ubicacion_id'] ?? 1);
        $subcategoria_id = intval($_POST['subcategoria_id'] ?? 1);
        $prioridad       = trim($_POST['prioridad'] ?? 'Media');
        $reportante_id   = intval($_POST['reportante_id'] ?? 1);

        if (empty($titulo) || empty($descripcion)) {
            echo json_encode(['success' => false, 'error' => 'El título y la descripción son obligatorios']);
            exit;
        }

        // Obtener nombre del reportante para el historial (sin bucle FK)
        $resRep = $conn->query("SELECT nombre FROM usuarios WHERE id = $reportante_id");
        $nombreReportante = ($resRep && $row = $resRep->fetch_assoc()) ? $row['nombre'] : 'Usuario del Sistema';

        // Generar código correlativo
        $resCode = $conn->query("SELECT COUNT(*) AS total FROM incidencias");
        $total = ($resCode->fetch_assoc()['total'] ?? 0) + 1;
        $codigo = 'INC-2026-' . str_pad($total, 4, '0', STR_PAD_LEFT);

        $stmt = $conn->prepare("INSERT INTO incidencias (codigo, titulo, descripcion, ubicacion_id, subcategoria_id, prioridad, estado, reportante_id) VALUES (?, ?, ?, ?, ?, ?, 'Registrada', ?)");
        $stmt->bind_param("sssiisi", $codigo, $titulo, $descripcion, $ubicacion_id, $subcategoria_id, $prioridad, $reportante_id);

        if ($stmt->execute()) {
            $nuevoId = $stmt->insert_id;
            
            // Historial inmutable desacoplado (cambiado_por guarda el nombre, sin bucle FK)
            $stmtHist = $conn->prepare("INSERT INTO historial_incidencias (incidencia_id, estado_anterior, estado_nuevo, cambiado_por, nota) VALUES (?, NULL, 'Registrada', ?, 'Incidencia registrada en el sistema')");
            $stmtHist->bind_param("is", $nuevoId, $nombreReportante);
            $stmtHist->execute();

            echo json_encode([
                'success' => true,
                'message' => 'Incidencia registrada y persistida en MySQL exitosamente',
                'id' => $nuevoId,
                'codigo' => $codigo
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
        break;

    case 'get_notificaciones':
        $usuario_id = intval($_GET['usuario_id'] ?? 1);
        $stmt = $conn->prepare("SELECT id, texto, tipo, incidencia_id, leida, creado_en FROM notificaciones WHERE usuario_id = ? ORDER BY id DESC LIMIT 15");
        $stmt->bind_param("i", $usuario_id);
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
