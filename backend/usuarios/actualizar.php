<?php
/**
 * Actualizar perfil del usuario autenticado
 * POST /usuarios/actualizar.php
 * Body: { "nombre": "...", "email": "...", "password": "..." (opcional) }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/cors.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/validator.php';

setCorsHeaders();

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

// Requiere autenticación
requireAuth();

// Obtener datos del body
$data = getJsonInput();

if (empty($data)) {
    jsonError('No se proporcionaron datos para actualizar', 400);
}

$userId = getCurrentUserId();

// Conexión a BD
$conn = getConnection();
if (!$conn) {
    jsonError('Error de conexión a la base de datos', 500);
}

// Campos a actualizar
$updates = [];
$params = [];

// Actualizar nombre
if (isset($data['nombre'])) {
    $nombre = sanitizeString($data['nombre']);
    if (!minLength($nombre, 2)) {
        jsonError('El nombre debe tener al menos 2 caracteres', 400);
    }
    $updates[] = "nombre = ?";
    $params[] = $nombre;
}

// Actualizar email
if (isset($data['email'])) {
    $email = sanitizeString($data['email']);
    if (!isValidEmail($email)) {
        jsonError('Formato de email inválido', 400);
    }

    // Verificar que el email no esté en uso por otro usuario
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
    $stmt->execute([$email, $userId]);
    if ($stmt->fetch()) {
        jsonError('El email ya está en uso por otro usuario', 409);
    }

    $updates[] = "email = ?";
    $params[] = $email;
}

// Actualizar password (opcional)
if (isset($data['password']) && !empty($data['password'])) {
    $password = $data['password'];
    if (!minLength($password, 6)) {
        jsonError('La contraseña debe tener al menos 6 caracteres', 400);
    }
    $updates[] = "password = ?";
    $params[] = password_hash($password, PASSWORD_DEFAULT);
}

// Verificar que hay algo que actualizar
if (empty($updates)) {
    jsonError('No se proporcionaron campos válidos para actualizar', 400);
}

// Ejecutar actualización
$sql = "UPDATE usuarios SET " . implode(", ", $updates) . " WHERE id = ?";
$params[] = $userId;

$stmt = $conn->prepare($sql);
$result = $stmt->execute($params);

if (!$result) {
    jsonError('Error al actualizar perfil', 500);
}

// Obtener datos actualizados
$stmt = $conn->prepare("SELECT id, nombre, email, rol FROM usuarios WHERE id = ?");
$stmt->execute([$userId]);
$usuario = $stmt->fetch();

// Actualizar sesión con nuevos datos
setUserSession($usuario);

jsonResponse($usuario, 'Perfil actualizado correctamente');
