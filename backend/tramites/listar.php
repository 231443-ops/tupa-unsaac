<?php
/**
 * Listar trámites del usuario autenticado
 * GET /tramites/listar.php
 * Query params:
 *   - estado: filtrar por estado
 *   - page: número de página (default 1)
 *   - limit: resultados por página (default 10)
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

$userId = getCurrentUserId();

$conn = getConnection();
if (!$conn) {
    jsonError('Error de conexión a la base de datos', 500);
}

// Parámetros
$estado = isset($_GET['estado']) ? sanitizeString($_GET['estado']) : null;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;
$offset = ($page - 1) * $limit;

// Query base
$sql = "
    SELECT
        t.id,
        t.numero_expediente,
        t.estado,
        t.observaciones,
        t.fecha_inicio,
        t.fecha_fin,
        p.codigo AS procedimiento_codigo,
        p.nombre AS procedimiento_nombre,
        d.nombre AS dependencia
    FROM tramites t
    INNER JOIN procedimientos p ON t.procedimiento_id = p.id
    LEFT JOIN dependencias d ON p.dependencia_id = d.id
    WHERE t.usuario_id = ?
";

$params = [$userId];

// Filtro por estado
$estadosValidos = ['pendiente', 'en_proceso', 'observado', 'aprobado', 'rechazado'];
if ($estado && in_array($estado, $estadosValidos)) {
    $sql .= " AND t.estado = ?";
    $params[] = $estado;
}

// Contar total
$countSql = str_replace("SELECT \n        t.id,", "SELECT COUNT(*) as total FROM (SELECT t.id", $sql) . ") as sub";
$stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM tramites t WHERE t.usuario_id = ?" . ($estado ? " AND t.estado = ?" : ""));
$stmtCount->execute($estado ? [$userId, $estado] : [$userId]);
$total = $stmtCount->fetch()['total'];

// Ordenar y paginar
$sql .= " ORDER BY t.fecha_inicio DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$tramites = $stmt->fetchAll();

jsonResponse([
    'tramites' => $tramites,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => (int)$total,
        'pages' => ceil($total / $limit)
    ]
], 'Trámites obtenidos');
