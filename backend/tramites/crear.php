<?php
/**
 * Crear nuevo trámite
 * POST /tramites/crear.php
 * Body: { "procedimiento_id": N, "observaciones": "..." (opcional) }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/cors.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/validator.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

requireAuth();

$data = getJsonInput();

// Validar procedimiento_id
if (!isset($data['procedimiento_id']) || !is_numeric($data['procedimiento_id'])) {
    jsonError('El procedimiento_id es requerido', 400);
}

$procedimientoId = (int)$data['procedimiento_id'];
$observaciones = isset($data['observaciones']) ? sanitizeString($data['observaciones']) : null;
$userId = getCurrentUserId();

$conn = getConnection();
if (!$conn) {
    jsonError('Error de conexión a la base de datos', 500);
}

// Verificar que el procedimiento existe y está activo
$stmt = $conn->prepare("SELECT id, nombre FROM procedimientos WHERE id = ? AND activo = 1");
$stmt->execute([$procedimientoId]);
$procedimiento = $stmt->fetch();

if (!$procedimiento) {
    jsonError('Procedimiento no encontrado o inactivo', 404);
}

// Generar número de expediente: EXP-2026-NNNNN
$year = date('Y');
$stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM tramites
    WHERE numero_expediente LIKE ?
");
$stmt->execute(["EXP-{$year}-%"]);
$result = $stmt->fetch();
$siguiente = $result['total'] + 1;
$numeroExpediente = sprintf("EXP-%s-%05d", $year, $siguiente);

// Insertar trámite
$stmt = $conn->prepare("
    INSERT INTO tramites (numero_expediente, usuario_id, procedimiento_id, estado, observaciones)
    VALUES (?, ?, ?, 'pendiente', ?)
");
$result = $stmt->execute([$numeroExpediente, $userId, $procedimientoId, $observaciones]);

if (!$result) {
    jsonError('Error al crear el trámite', 500);
}

$tramiteId = $conn->lastInsertId();

// Registrar en historial
$stmt = $conn->prepare("
    INSERT INTO historial_estados (tramite_id, estado_anterior, estado_nuevo, comentario, usuario_id)
    VALUES (?, NULL, 'pendiente', 'Trámite creado', ?)
");
$stmt->execute([$tramiteId, $userId]);

jsonResponse([
    'id' => $tramiteId,
    'numero_expediente' => $numeroExpediente,
    'procedimiento' => $procedimiento['nombre'],
    'estado' => 'pendiente',
    'fecha_inicio' => date('Y-m-d H:i:s')
], 'Trámite creado exitosamente', 201);
