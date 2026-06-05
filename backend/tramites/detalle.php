<?php
/**
 * Detalle de un trámite con historial de estados
 * GET /tramites/detalle.php?id=N
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/cors.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Método no permitido', 405);
}

requireAuth();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    jsonError('ID de trámite requerido', 400);
}

$tramiteId = (int)$_GET['id'];
$userId = getCurrentUserId();
$userRol = getCurrentUser()['rol'];

$conn = getConnection();
if (!$conn) {
    jsonError('Error de conexión a la base de datos', 500);
}

// Obtener trámite
$sql = "
    SELECT
        t.id,
        t.numero_expediente,
        t.estado,
        t.observaciones,
        t.fecha_inicio,
        t.fecha_fin,
        t.usuario_id,
        p.codigo AS procedimiento_codigo,
        p.nombre AS procedimiento_nombre,
        p.descripcion AS procedimiento_descripcion,
        p.requisitos,
        p.costo,
        p.plazo_dias,
        p.base_legal,
        d.nombre AS dependencia
    FROM tramites t
    INNER JOIN procedimientos p ON t.procedimiento_id = p.id
    LEFT JOIN dependencias d ON p.dependencia_id = d.id
    WHERE t.id = ?
";

$stmt = $conn->prepare($sql);
$stmt->execute([$tramiteId]);
$tramite = $stmt->fetch();

if (!$tramite) {
    jsonError('Trámite no encontrado', 404);
}

// Verificar permisos (solo el dueño o admin/tramitador pueden ver)
if ($tramite['usuario_id'] != $userId && !in_array($userRol, ['admin', 'tramitador'])) {
    jsonError('No tiene permisos para ver este trámite', 403);
}

// Obtener historial de estados
$stmt = $conn->prepare("
    SELECT
        h.estado_anterior,
        h.estado_nuevo,
        h.comentario,
        h.created_at,
        u.nombre AS usuario_nombre
    FROM historial_estados h
    LEFT JOIN usuarios u ON h.usuario_id = u.id
    WHERE h.tramite_id = ?
    ORDER BY h.created_at DESC
");
$stmt->execute([$tramiteId]);
$historial = $stmt->fetchAll();

// Obtener archivos adjuntos
$stmt = $conn->prepare("
    SELECT id, nombre_original, tipo_mime, tamanio, created_at
    FROM archivos_tramite
    WHERE tramite_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$tramiteId]);
$archivos = $stmt->fetchAll();

// Remover usuario_id de la respuesta
unset($tramite['usuario_id']);

jsonResponse([
    'tramite' => $tramite,
    'historial' => $historial,
    'archivos' => $archivos
], 'Detalle de trámite obtenido');
