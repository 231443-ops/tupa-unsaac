<?php
/**
 * Reportes y estadísticas
 * GET /admin/reportes.php
 * Query params:
 *   - tipo: resumen|por_estado|por_dependencia|por_mes|tiempos
 *   - fecha_inicio: YYYY-MM-DD (opcional)
 *   - fecha_fin: YYYY-MM-DD (opcional)
 * Solo accesible para: admin, jefe, operador
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/cors.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/validator.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Método no permitido', 405);
}

requireAuth();

$user = getCurrentUser();
$rolesPermitidos = ['admin', 'jefe', 'operador'];

if (!in_array($user['rol'], $rolesPermitidos)) {
    jsonError('Acceso denegado. Requiere rol de administración.', 403);
}

$conn = getConnection();
if (!$conn) {
    jsonError('Error de conexión a la base de datos', 500);
}

// Obtener dependencia del usuario (si no es admin)
$dependenciaId = null;
if ($user['rol'] !== 'admin') {
    $stmt = $conn->prepare("SELECT dependencia_id FROM usuarios WHERE id = ?");
    $stmt->execute([$user['id']]);
    $userData = $stmt->fetch();
    $dependenciaId = $userData['dependencia_id'];
}

// Parámetros
$tipo = isset($_GET['tipo']) ? sanitizeString($_GET['tipo']) : 'resumen';
$fechaInicio = isset($_GET['fecha_inicio']) ? sanitizeString($_GET['fecha_inicio']) : date('Y-01-01');
$fechaFin = isset($_GET['fecha_fin']) ? sanitizeString($_GET['fecha_fin']) : date('Y-m-d');

// Validar fechas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)) {
    jsonError('Formato de fecha inválido. Use YYYY-MM-DD', 400);
}

// Condición de dependencia
$whereDepe = $dependenciaId ? "AND p.dependencia_id = $dependenciaId" : "";

switch ($tipo) {
    case 'resumen':
        // Estadísticas generales
        $stmt = $conn->prepare("
            SELECT
                COUNT(*) as total_tramites,
                SUM(CASE WHEN t.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN t.estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
                SUM(CASE WHEN t.estado = 'observado' THEN 1 ELSE 0 END) as observados,
                SUM(CASE WHEN t.estado = 'aprobado' THEN 1 ELSE 0 END) as aprobados,
                SUM(CASE WHEN t.estado = 'rechazado' THEN 1 ELSE 0 END) as rechazados,
                AVG(CASE WHEN t.fecha_fin IS NOT NULL THEN DATEDIFF(t.fecha_fin, t.fecha_inicio) END) as promedio_dias_resolucion
            FROM tramites t
            INNER JOIN procedimientos p ON t.procedimiento_id = p.id
            WHERE t.fecha_inicio BETWEEN ? AND ?
            $whereDepe
        ");
        $stmt->execute([$fechaInicio, $fechaFin]);
        $resumen = $stmt->fetch();

        // Tasa de aprobación
        $totalFinalizados = $resumen['aprobados'] + $resumen['rechazados'];
        $tasaAprobacion = $totalFinalizados > 0
            ? round(($resumen['aprobados'] / $totalFinalizados) * 100, 2)
            : 0;

        $data = [
            'periodo' => ['inicio' => $fechaInicio, 'fin' => $fechaFin],
            'total_tramites' => (int)$resumen['total_tramites'],
            'por_estado' => [
                'pendientes' => (int)$resumen['pendientes'],
                'en_proceso' => (int)$resumen['en_proceso'],
                'observados' => (int)$resumen['observados'],
                'aprobados' => (int)$resumen['aprobados'],
                'rechazados' => (int)$resumen['rechazados']
            ],
            'metricas' => [
                'tasa_aprobacion' => $tasaAprobacion,
                'promedio_dias_resolucion' => round($resumen['promedio_dias_resolucion'] ?? 0, 1)
            ]
        ];
        break;

    case 'por_estado':
        $stmt = $conn->prepare("
            SELECT t.estado, COUNT(*) as cantidad
            FROM tramites t
            INNER JOIN procedimientos p ON t.procedimiento_id = p.id
            WHERE t.fecha_inicio BETWEEN ? AND ?
            $whereDepe
            GROUP BY t.estado
            ORDER BY cantidad DESC
        ");
        $stmt->execute([$fechaInicio, $fechaFin]);
        $data = $stmt->fetchAll();
        break;

    case 'por_dependencia':
        if ($dependenciaId) {
            jsonError('Este reporte solo está disponible para administradores', 403);
        }
        $stmt = $conn->prepare("
            SELECT
                d.nombre AS dependencia,
                COUNT(*) as total,
                SUM(CASE WHEN t.estado = 'aprobado' THEN 1 ELSE 0 END) as aprobados,
                SUM(CASE WHEN t.estado = 'rechazado' THEN 1 ELSE 0 END) as rechazados,
                SUM(CASE WHEN t.estado NOT IN ('aprobado', 'rechazado') THEN 1 ELSE 0 END) as en_tramite
            FROM tramites t
            INNER JOIN procedimientos p ON t.procedimiento_id = p.id
            LEFT JOIN dependencias d ON p.dependencia_id = d.id
            WHERE t.fecha_inicio BETWEEN ? AND ?
            GROUP BY d.id, d.nombre
            ORDER BY total DESC
        ");
        $stmt->execute([$fechaInicio, $fechaFin]);
        $data = $stmt->fetchAll();
        break;

    case 'por_mes':
        $stmt = $conn->prepare("
            SELECT
                DATE_FORMAT(t.fecha_inicio, '%Y-%m') as mes,
                COUNT(*) as total,
                SUM(CASE WHEN t.estado = 'aprobado' THEN 1 ELSE 0 END) as aprobados,
                SUM(CASE WHEN t.estado = 'rechazado' THEN 1 ELSE 0 END) as rechazados
            FROM tramites t
            INNER JOIN procedimientos p ON t.procedimiento_id = p.id
            WHERE t.fecha_inicio BETWEEN ? AND ?
            $whereDepe
            GROUP BY DATE_FORMAT(t.fecha_inicio, '%Y-%m')
            ORDER BY mes ASC
        ");
        $stmt->execute([$fechaInicio, $fechaFin]);
        $data = $stmt->fetchAll();
        break;

    case 'tiempos':
        // Tiempos promedio de resolución por procedimiento
        $stmt = $conn->prepare("
            SELECT
                p.codigo,
                p.nombre AS procedimiento,
                p.plazo_dias AS plazo_establecido,
                COUNT(*) as total_finalizados,
                AVG(DATEDIFF(t.fecha_fin, t.fecha_inicio)) as promedio_dias,
                MIN(DATEDIFF(t.fecha_fin, t.fecha_inicio)) as minimo_dias,
                MAX(DATEDIFF(t.fecha_fin, t.fecha_inicio)) as maximo_dias,
                SUM(CASE WHEN DATEDIFF(t.fecha_fin, t.fecha_inicio) > p.plazo_dias THEN 1 ELSE 0 END) as fuera_plazo
            FROM tramites t
            INNER JOIN procedimientos p ON t.procedimiento_id = p.id
            WHERE t.fecha_fin IS NOT NULL
            AND t.fecha_inicio BETWEEN ? AND ?
            $whereDepe
            GROUP BY p.id, p.codigo, p.nombre, p.plazo_dias
            ORDER BY promedio_dias DESC
        ");
        $stmt->execute([$fechaInicio, $fechaFin]);
        $resultados = $stmt->fetchAll();

        // Formatear resultados
        $data = array_map(function($row) {
            return [
                'codigo' => $row['codigo'],
                'procedimiento' => $row['procedimiento'],
                'plazo_establecido' => (int)$row['plazo_establecido'],
                'total_finalizados' => (int)$row['total_finalizados'],
                'promedio_dias' => round($row['promedio_dias'], 1),
                'minimo_dias' => (int)$row['minimo_dias'],
                'maximo_dias' => (int)$row['maximo_dias'],
                'fuera_plazo' => (int)$row['fuera_plazo'],
                'cumplimiento' => round((($row['total_finalizados'] - $row['fuera_plazo']) / $row['total_finalizados']) * 100, 1)
            ];
        }, $resultados);
        break;

    default:
        jsonError('Tipo de reporte no válido. Use: resumen, por_estado, por_dependencia, por_mes, tiempos', 400);
}

jsonResponse($data, "Reporte: $tipo");
