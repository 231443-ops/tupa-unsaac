<?php
/**
 * Consulta pública de trámite por expediente y DNI
 * GET /tramites/consulta-publica.php?expediente=EXP-2026-00001&dni=12345678
 * No requiere autenticación
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/cors.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validator.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Método no permitido', 405);
}

// Validar parámetros
if (!isset($_GET['expediente']) || !isset($_GET['dni'])) {
    jsonError('Se requiere número de expediente y DNI', 400);
}

$expediente = sanitizeString($_GET['expediente']);
$dni = sanitizeString($_GET['dni']);

// Validar formato de expediente
if (!preg_match('/^EXP-\d{4}-\d{5}$/', $expediente)) {
    jsonError('Formato de expediente inválido. Use: EXP-YYYY-NNNNN', 400);
}

// Validar DNI (8 dígitos para Perú)
if (!preg_match('/^\d{8}$/', $dni)) {
    jsonError('DNI debe tener 8 dígitos', 400);
}

$conn = getConnection();
if (!$conn) {
    jsonError('Error de conexión a la base de datos', 500);
}

// Buscar trámite verificando que el DNI coincida con el del usuario
$stmt = $conn->prepare("
    SELECT
        t.numero_expediente,
        t.estado,
        t.fecha_inicio,
        t.fecha_fin,
        p.codigo AS procedimiento_codigo,
        p.nombre AS procedimiento_nombre,
        p.plazo_dias,
        d.nombre AS dependencia
    FROM tramites t
    INNER JOIN usuarios u ON t.usuario_id = u.id
    INNER JOIN procedimientos p ON t.procedimiento_id = p.id
    LEFT JOIN dependencias d ON p.dependencia_id = d.id
    WHERE t.numero_expediente = ? AND u.dni = ?
");
$stmt->execute([$expediente, $dni]);
$tramite = $stmt->fetch();

if (!$tramite) {
    jsonError('Trámite no encontrado. Verifique el expediente y DNI.', 404);
}

// Calcular días transcurridos y restantes
$fechaInicio = new DateTime($tramite['fecha_inicio']);
$hoy = new DateTime();
$diasTranscurridos = $fechaInicio->diff($hoy)->days;
$diasRestantes = max(0, $tramite['plazo_dias'] - $diasTranscurridos);

// Obtener historial (solo estados, sin datos sensibles)
$stmt = $conn->prepare("
    SELECT
        estado_nuevo AS estado,
        comentario,
        created_at AS fecha
    FROM historial_estados
    WHERE tramite_id = (SELECT id FROM tramites WHERE numero_expediente = ?)
    ORDER BY created_at DESC
");
$stmt->execute([$expediente]);
$historial = $stmt->fetchAll();

// Preparar respuesta
$estadoTexto = [
    'pendiente' => 'Pendiente de revisión',
    'en_proceso' => 'En proceso de evaluación',
    'observado' => 'Observado - Requiere subsanación',
    'aprobado' => 'Aprobado',
    'rechazado' => 'Rechazado'
];

jsonResponse([
    'expediente' => $tramite['numero_expediente'],
    'procedimiento' => $tramite['procedimiento_nombre'],
    'dependencia' => $tramite['dependencia'],
    'estado' => $tramite['estado'],
    'estado_descripcion' => $estadoTexto[$tramite['estado']] ?? $tramite['estado'],
    'fecha_inicio' => $tramite['fecha_inicio'],
    'fecha_fin' => $tramite['fecha_fin'],
    'plazo_dias' => $tramite['plazo_dias'],
    'dias_transcurridos' => $diasTranscurridos,
    'dias_restantes' => $diasRestantes,
    'historial' => $historial
], 'Consulta de trámite');
