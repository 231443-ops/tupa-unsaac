<?php
/**
 * Logout de usuario
 * POST /auth/logout.php
 */

require_once __DIR__ . '/../helpers/cors.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

setCorsHeaders();

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

// Destruir sesión
destroyUserSession();

jsonResponse(null, 'Sesión cerrada correctamente');
