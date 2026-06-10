<?php
/**
 * Marcar notificación como leída
 * POST /notificaciones/marcar-leida.php
 * Body: { "id": N }  -> marca una notificación
 *       { "todas": true } -> marca todas las del usuario
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/cors.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

requireAuth();
$userId = getCurrentUserId();

$data = getJsonInput();

$conn = getConnection();
if (!$conn) {
    jsonError('Error de conexión a la base de datos', 500);
}

if (!empty($data['todas'])) {
    $stmt = $conn->prepare("UPDATE notificaciones SET leida = 1 WHERE usuario_id = ? AND leida = 0");
    $stmt->execute([$userId]);
    jsonResponse(['marcadas' => $stmt->rowCount()], 'Todas las notificaciones marcadas como leídas');
}

if (empty($data['id'])) {
    jsonError('Debe indicar el id de la notificación o todas=true', 400);
}

$notifId = (int)$data['id'];

// Solo puede marcar sus propias notificaciones
$stmt = $conn->prepare("UPDATE notificaciones SET leida = 1 WHERE id = ? AND usuario_id = ?");
$stmt->execute([$notifId, $userId]);

if ($stmt->rowCount() === 0) {
    jsonError('Notificación no encontrada', 404);
}

jsonResponse(['id' => $notifId], 'Notificación marcada como leída');
