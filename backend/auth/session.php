<?php
/**
 * Obtener sesión actual
 * GET /auth/session.php
 */

require_once __DIR__ . '/../helpers/cors.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

setCorsHeaders();

// Solo aceptar GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Método no permitido', 405);
}

// Verificar autenticación
if (!isAuthenticated()) {
    jsonError('No hay sesión activa', 401);
}

// Obtener usuario actual
$user = getCurrentUser();

jsonResponse($user, 'Sesión activa');
