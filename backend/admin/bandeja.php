<?php
/**
 * Bandeja de trámites pendientes de la oficina
 * GET /admin/bandeja.php
 * Query params:
 *   - estado: filtrar por estado (pendiente, en_proceso, observado)
 *   - page: número de página
 *   - limit: resultados por página
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

    if (!$dependenciaId) {
        jsonError('Usuario no asignado a ninguna dependencia', 400);
    }
}

// Parámetros
$estado = isset($_GET['estado']) ? sanitizeString($_GET['estado']) : null;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 20;
$offset = ($page - 1) * $limit;

// Query base
$sql = "
    SELECT
        t.id,
        t.numero_expediente,
        t.estado,
        t.observaciones,
        t.fecha_inicio,
        u.nombre AS solicitante_nombre,
        u.dni AS solicitante_dni,
        p.codigo AS procedimiento_codigo,
        p.nombre AS procedimiento_nombre,
        p.plazo_dias,
        d.nombre AS dependencia,
        DATEDIFF(NOW(), t.fecha_inicio) AS dias_transcurridos
    FROM tramites t
    INNER JOIN usuarios u ON t.usuario_id = u.id
    INNER JOIN procedimientos p ON t.procedimiento_id = p.id
    LEFT JOIN dependencias d ON p.dependencia_id = d.id
    WHERE t.estado NOT IN ('aprobado', 'rechazado')
";

$params = [];

// Filtrar por dependencia (si no es admin)
if ($dependenciaId) {
    $sql .= " AND p.dependencia_id = ?";
    $params[] = $dependenciaId;
}

// Filtro por estado
$estadosValidos = ['pendiente', 'en_proceso', 'observado'];
if ($estado && in_array($estado, $estadosValidos)) {
    $sql .= " AND t.estado = ?";
    $params[] = $estado;
}

// Contar total
$countSql = "SELECT COUNT(*) as total FROM tramites t
             INNER JOIN procedimientos p ON t.procedimiento_id = p.id
             WHERE t.estado NOT IN ('aprobado', 'rechazado')";
if ($dependenciaId) {
    $countSql .= " AND p.dependencia_id = ?";
}
if ($estado && in_array($estado, $estadosValidos)) {
    $countSql .= " AND t.estado = ?";
}
$stmtCount = $conn->prepare($countSql);
$stmtCount->execute($dependenciaId ? ($estado ? [$dependenciaId, $estado] : [$dependenciaId]) : ($estado ? [$estado] : []));
$total = $stmtCount->fetch()['total'];

// Ordenar por prioridad: más antiguos primero (FIFO)
$sql .= " ORDER BY t.fecha_inicio ASC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$tramites = $stmt->fetchAll();

// Agregar indicador de urgencia
foreach ($tramites as &$tramite) {
    $diasRestantes = $tramite['plazo_dias'] - $tramite['dias_transcurridos'];
    $tramite['dias_restantes'] = max(0, $diasRestantes);
    $tramite['urgente'] = $diasRestantes <= 3;
    $tramite['vencido'] = $diasRestantes < 0;
}

// Resumen por estado
$stmt = $conn->prepare("
    SELECT t.estado, COUNT(*) as total
    FROM tramites t
    INNER JOIN procedimientos p ON t.procedimiento_id = p.id
    WHERE t.estado NOT IN ('aprobado', 'rechazado')
    " . ($dependenciaId ? "AND p.dependencia_id = ?" : "") . "
    GROUP BY t.estado
");
$stmt->execute($dependenciaId ? [$dependenciaId] : []);
$resumen = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

jsonResponse([
    'tramites' => $tramites,
    'resumen' => [
        'pendiente' => (int)($resumen['pendiente'] ?? 0),
        'en_proceso' => (int)($resumen['en_proceso'] ?? 0),
        'observado' => (int)($resumen['observado'] ?? 0)
    ],
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => (int)$total,
        'pages' => ceil($total / $limit)
    ]
], 'Bandeja de trámites');
