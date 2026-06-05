<?php
/**
 * Buscar procedimientos TUPA
 * GET /tupa/procedimientos.php
 * Query params:
 *   - q: texto de búsqueda (nombre, código, descripción)
 *   - categoria: ID de la dependencia/categoría
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/cors.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validator.php';

setCorsHeaders();

// Solo aceptar GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Método no permitido', 405);
}

// Conexión a BD
$conn = getConnection();
if (!$conn) {
    jsonError('Error de conexión a la base de datos', 500);
}

// Parámetros de búsqueda
$busqueda = isset($_GET['q']) ? sanitizeString($_GET['q']) : '';
$categoriaId = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;

// Construir query
$sql = "
    SELECT
        p.id,
        p.codigo,
        p.nombre,
        p.descripcion,
        p.requisitos,
        p.costo,
        p.plazo_dias,
        p.base_legal,
        d.id AS categoria_id,
        d.nombre AS categoria_nombre
    FROM procedimientos p
    LEFT JOIN dependencias d ON p.dependencia_id = d.id
    WHERE p.activo = 1
";

$params = [];

// Filtro por texto
if (!empty($busqueda)) {
    $sql .= " AND (p.nombre LIKE ? OR p.codigo LIKE ? OR p.descripcion LIKE ?)";
    $busquedaLike = "%{$busqueda}%";
    $params[] = $busquedaLike;
    $params[] = $busquedaLike;
    $params[] = $busquedaLike;
}

// Filtro por categoría
if ($categoriaId > 0) {
    $sql .= " AND p.dependencia_id = ?";
    $params[] = $categoriaId;
}

$sql .= " ORDER BY p.nombre ASC";

// Ejecutar consulta
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$procedimientos = $stmt->fetchAll();

jsonResponse([
    'total' => count($procedimientos),
    'procedimientos' => $procedimientos
], 'Procedimientos obtenidos');
