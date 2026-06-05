<?php
/**
 * Funciones para respuestas JSON estandarizadas
 */

/**
 * Enviar respuesta JSON exitosa
 * @param mixed $data Datos a enviar
 * @param string $message Mensaje opcional
 * @param int $code Código HTTP (default 200)
 */
function jsonResponse($data = null, string $message = 'OK', int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Enviar respuesta JSON de error
 * @param string $message Mensaje de error
 * @param int $code Código HTTP (default 400)
 * @param array $errors Errores adicionales
 */
function jsonError(string $message, int $code = 400, array $errors = []): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');

    $response = [
        'success' => false,
        'message' => $message
    ];

    if (!empty($errors)) {
        $response['errors'] = $errors;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Obtener datos JSON del body de la petición
 * @return array
 */
function getJsonInput(): array {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}
