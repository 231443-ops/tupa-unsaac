<?php
/**
 * Registro de usuario
 * POST /auth/register.php
 * Body: { "nombre": "...", "email": "...", "password": "..." }
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

// Obtener datos del body
$data = getJsonInput();

// Validar campos requeridos
$errors = validateRequired($data, ['nombre', 'email', 'password']);
if (!empty($errors)) {
    jsonError('Datos incompletos', 400, $errors);
}

$nombre = sanitizeString($data['nombre']);
$email = sanitizeString($data['email']);
$password = $data['password'];

// Validaciones
if (!isValidEmail($email)) {
    jsonError('Formato de email inválido', 400);
}

if (!minLength($nombre, 2)) {
    jsonError('El nombre debe tener al menos 2 caracteres', 400);
}

if (!minLength($password, 6)) {
    jsonError('La contraseña debe tener al menos 6 caracteres', 400);
}

// Conexión a BD
$conn = getConnection();
if (!$conn) {
    jsonError('Error de conexión a la base de datos', 500);
}

// Verificar email único
$stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    jsonError('El email ya está registrado', 409);
}

// Hashear password
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Insertar usuario
$stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, 'usuario')");
$result = $stmt->execute([$nombre, $email, $passwordHash]);

if (!$result) {
    jsonError('Error al registrar usuario', 500);
}

$userId = $conn->lastInsertId();

// Crear sesión automáticamente
setUserSession([
    'id' => $userId,
    'nombre' => $nombre,
    'email' => $email,
    'rol' => 'usuario'
]);

jsonResponse([
    'id' => $userId,
    'nombre' => $nombre,
    'email' => $email,
    'rol' => 'usuario'
], 'Usuario registrado exitosamente', 201);
