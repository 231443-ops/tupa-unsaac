<?php
/**
 * Funciones de autenticación y sesión
 */

/**
 * Iniciar sesión de forma segura
 */
function initSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_secure' => isset($_SERVER['HTTPS']),
            'use_strict_mode' => true
        ]);
    }
}

/**
 * Verificar si el usuario está autenticado
 * @return bool
 */
function isAuthenticated(): bool {
    initSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Obtener ID del usuario actual
 * @return int|null
 */
function getCurrentUserId(): ?int {
    initSession();
    return $_SESSION['user_id'] ?? null;
}

/**
 * Obtener datos del usuario actual de la sesión
 * @return array|null
 */
function getCurrentUser(): ?array {
    initSession();
    if (!isAuthenticated()) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'nombre' => $_SESSION['user_nombre'] ?? null,
        'email' => $_SESSION['user_email'] ?? null,
        'rol' => $_SESSION['user_rol'] ?? null
    ];
}

/**
 * Establecer datos de usuario en sesión
 * @param array $user Datos del usuario
 */
function setUserSession(array $user): void {
    initSession();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_nombre'] = $user['nombre'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_rol'] = $user['rol'];
}

/**
 * Destruir sesión de usuario
 */
function destroyUserSession(): void {
    initSession();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Requerir autenticación - termina con error si no está autenticado
 */
function requireAuth(): void {
    if (!isAuthenticated()) {
        require_once __DIR__ . '/response.php';
        jsonError('No autorizado. Inicie sesión.', 401);
    }
}
