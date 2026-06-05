<?php
/**
 * Funciones de validación de datos
 */

/**
 * Validar email
 * @param string $email
 * @return bool
 */
function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validar que un campo no esté vacío
 * @param mixed $value
 * @return bool
 */
function isNotEmpty($value): bool {
    if (is_string($value)) {
        return trim($value) !== '';
    }
    return !empty($value);
}

/**
 * Validar longitud mínima
 * @param string $value
 * @param int $min
 * @return bool
 */
function minLength(string $value, int $min): bool {
    return mb_strlen($value) >= $min;
}

/**
 * Validar longitud máxima
 * @param string $value
 * @param int $max
 * @return bool
 */
function maxLength(string $value, int $max): bool {
    return mb_strlen($value) <= $max;
}

/**
 * Sanitizar string
 * @param string $value
 * @return string
 */
function sanitizeString(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

/**
 * Validar campos requeridos
 * @param array $data Datos a validar
 * @param array $required Campos requeridos
 * @return array Errores encontrados
 */
function validateRequired(array $data, array $required): array {
    $errors = [];

    foreach ($required as $field) {
        if (!isset($data[$field]) || !isNotEmpty($data[$field])) {
            $errors[$field] = "El campo '$field' es requerido";
        }
    }

    return $errors;
}
