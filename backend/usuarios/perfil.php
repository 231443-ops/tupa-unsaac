<?php
/**
 * Obtener perfil del usuario autenticado
 * GET /usuarios/perfil.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/cors.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

setCorsHeaders();

// Solo aceptar GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Método no permitido', 405);
}

// Requiere autenticación
requireAuth();

// Conexión a BD
$conn = getConnection();
if (!$conn) {
    jsonError('Error de conexión a la base de datos', 500);
}

$userId = getCurrentUserId();

// Obtener datos actualizados del usuario
$stmt = $conn->prepare("
    SELECT id, nombre, email, rol, created_at, updated_at
    FROM usuarios
    WHERE id = ? AND activo = 1
");
$stmt->execute([$userId]);
$usuario = $stmt->fetch();

if (!$usuario) {
    jsonError('Usuario no encontrado', 404);
}

jsonResponse($usuario, 'Perfil obtenido');
