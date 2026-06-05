<?php
/**
 * Listar categorías (dependencias) activas
 * GET /tupa/categorias.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/cors.php';
require_once __DIR__ . '/../helpers/response.php';

setCorsHeaders();

// Solo aceptar GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Método no permitido', 405);
}

// Conexión a BD
$conn = getConnection();
if (!$conn) {
    jsonError('Error de conexión a la base de datos', 500);
}

// Obtener categorías activas
$stmt = $conn->prepare("
    SELECT id, nombre, codigo, descripcion
    FROM dependencias
    WHERE activo = 1
    ORDER BY nombre ASC
");
$stmt->execute();
$categorias = $stmt->fetchAll();

jsonResponse($categorias, 'Categorías obtenidas');
