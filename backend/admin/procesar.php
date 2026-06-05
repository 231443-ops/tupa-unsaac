<?php
/**
 * Procesar trámite - cambiar estado
 * POST /admin/procesar.php
 * Body: {
 *   "tramite_id": N,
 *   "accion": "en_proceso|observar|aprobar|rechazar",
 *   "comentario": "..."
 * }
 * Solo accesible para: admin, jefe, operador
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

$user = getCurrentUser();
$rolesPermitidos = ['admin', 'jefe', 'operador'];

if (!in_array($user['rol'], $rolesPermitidos)) {
    jsonError('Acceso denegado. Requiere rol de administración.', 403);
}

$data = getJsonInput();

// Validar campos requeridos
$errors = validateRequired($data, ['tramite_id', 'accion']);
if (!empty($errors)) {
    jsonError('Datos incompletos', 400, $errors);
}

$tramiteId = (int)$data['tramite_id'];
$accion = sanitizeString($data['accion']);
$comentario = isset($data['comentario']) ? sanitizeString($data['comentario']) : null;

// Mapeo de acciones a estados
$accionesValidas = [
    'en_proceso' => 'en_proceso',
    'observar' => 'observado',
    'aprobar' => 'aprobado',
    'rechazar' => 'rechazado'
];

if (!isset($accionesValidas[$accion])) {
    jsonError('Acción no válida. Use: en_proceso, observar, aprobar, rechazar', 400);
}

$nuevoEstado = $accionesValidas[$accion];

// Validar comentario obligatorio para observar/rechazar
if (in_array($accion, ['observar', 'rechazar']) && empty($comentario)) {
    jsonError('El comentario es obligatorio para observar o rechazar', 400);
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

// Verificar que el trámite existe y pertenece a la dependencia del usuario
$sql = "
    SELECT t.id, t.estado, t.numero_expediente, p.dependencia_id
    FROM tramites t
    INNER JOIN procedimientos p ON t.procedimiento_id = p.id
    WHERE t.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->execute([$tramiteId]);
$tramite = $stmt->fetch();

if (!$tramite) {
    jsonError('Trámite no encontrado', 404);
}

// Verificar permisos sobre el trámite (admin puede todo, otros solo su dependencia)
if ($user['rol'] !== 'admin' && $tramite['dependencia_id'] != $dependenciaId) {
    jsonError('No tiene permisos para procesar este trámite', 403);
}

// Verificar que el trámite no esté finalizado
if (in_array($tramite['estado'], ['aprobado', 'rechazado'])) {
    jsonError('El trámite ya está finalizado y no puede modificarse', 400);
}

// Validar transiciones de estado permitidas
$transicionesPermitidas = [
    'pendiente' => ['en_proceso', 'observado', 'aprobado', 'rechazado'],
    'en_proceso' => ['observado', 'aprobado', 'rechazado'],
    'observado' => ['en_proceso', 'aprobado', 'rechazado']
];

$estadoActual = $tramite['estado'];
if (!in_array($nuevoEstado, $transicionesPermitidas[$estadoActual] ?? [])) {
    jsonError("No se puede cambiar de '$estadoActual' a '$nuevoEstado'", 400);
}

// Restricción: solo jefe o admin pueden aprobar/rechazar
if (in_array($accion, ['aprobar', 'rechazar']) && $user['rol'] === 'operador') {
    jsonError('Solo el jefe o administrador puede aprobar o rechazar trámites', 403);
}

// Iniciar transacción
$conn->beginTransaction();

try {
    // Actualizar estado del trámite
    $fechaFin = in_array($nuevoEstado, ['aprobado', 'rechazado']) ? date('Y-m-d H:i:s') : null;

    $stmt = $conn->prepare("
        UPDATE tramites
        SET estado = ?, observaciones = CONCAT(IFNULL(observaciones, ''), ?), fecha_fin = ?
        WHERE id = ?
    ");
    $observacionConcat = $comentario ? "\n[" . date('Y-m-d H:i') . "] " . $comentario : '';
    $stmt->execute([$nuevoEstado, $observacionConcat, $fechaFin, $tramiteId]);

    // Registrar en historial
    $stmt = $conn->prepare("
        INSERT INTO historial_estados (tramite_id, estado_anterior, estado_nuevo, comentario, usuario_id)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$tramiteId, $estadoActual, $nuevoEstado, $comentario, $user['id']]);

    $conn->commit();

    $accionTexto = [
        'en_proceso' => 'puesto en proceso',
        'observar' => 'observado',
        'aprobar' => 'aprobado',
        'rechazar' => 'rechazado'
    ];

    jsonResponse([
        'tramite_id' => $tramiteId,
        'numero_expediente' => $tramite['numero_expediente'],
        'estado_anterior' => $estadoActual,
        'estado_nuevo' => $nuevoEstado,
        'procesado_por' => $user['nombre']
    ], "Trámite {$accionTexto[$accion]} correctamente");

} catch (Exception $e) {
    $conn->rollBack();
    jsonError('Error al procesar el trámite', 500);
}
