<?php
/**
 * Listar notificaciones del usuario autenticado
 * GET /notificaciones/listar.php
 * Query params:
 *   - solo_no_leidas: 1 para listar únicamente las no leídas
 *   - limit: cantidad máxima (default 20, máx 50)
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
$userId = getCurrentUserId();

$soloNoLeidas = isset($_GET['solo_no_leidas']) && $_GET['solo_no_leidas'] == '1';
$limit = isset($_GET['limit']) ? min(max((int)$_GET['limit'], 1), 50) : 20;

$conn = getConnection();
if (!$conn) {
    jsonError('Error de conexión a la base de datos', 500);
}

// Contador de no leídas
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM notificaciones WHERE usuario_id = ? AND leida = 0");
$stmt->execute([$userId]);
$noLeidas = (int)$stmt->fetch()['total'];

// Listado
$sql = "
    SELECT
        n.id,
        n.tramite_id,
        n.tipo,
        n.titulo,
        n.mensaje,
        n.leida,
        n.created_at,
        t.numero_expediente
    FROM notificaciones n
    LEFT JOIN tramites t ON n.tramite_id = t.id
    WHERE n.usuario_id = ?
";

if ($soloNoLeidas) {
    $sql .= " AND n.leida = 0";
}

$sql .= " ORDER BY n.created_at DESC LIMIT " . $limit;

$stmt = $conn->prepare($sql);
$stmt->execute([$userId]);
$notificaciones = $stmt->fetchAll();

jsonResponse([
    'no_leidas' => $noLeidas,
    'notificaciones' => $notificaciones
], 'Notificaciones obtenidas');
