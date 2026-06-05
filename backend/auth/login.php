<?php
/**
 * Login de usuario
 * POST /auth/login.php
 * Body: { "email": "...", "password": "..." }
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
$errors = validateRequired($data, ['email', 'password']);
if (!empty($errors)) {
    jsonError('Datos incompletos', 400, $errors);
}

$email = sanitizeString($data['email']);
$password = $data['password'];

// Validar formato de email
if (!isValidEmail($email)) {
    jsonError('Formato de email inválido', 400);
}

// Conexión a BD
$conn = getConnection();
if (!$conn) {
    jsonError('Error de conexión a la base de datos', 500);
}

// Buscar usuario
$stmt = $conn->prepare("SELECT id, nombre, email, password, rol, activo FROM usuarios WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

// Verificar usuario existe
if (!$user) {
    jsonError('Credenciales incorrectas', 401);
}

// Verificar usuario activo
if (!$user['activo']) {
    jsonError('Usuario desactivado. Contacte al administrador.', 403);
}

// Verificar password
if (!password_verify($password, $user['password'])) {
    jsonError('Credenciales incorrectas', 401);
}

// Crear sesión
setUserSession([
    'id' => $user['id'],
    'nombre' => $user['nombre'],
    'email' => $user['email'],
    'rol' => $user['rol']
]);

// Respuesta exitosa (sin incluir password)
unset($user['password']);
jsonResponse($user, 'Inicio de sesión exitoso');
