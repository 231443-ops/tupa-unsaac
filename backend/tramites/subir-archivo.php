<?php
/**
 * Subir archivo adjunto a un trámite
 * POST /tramites/subir-archivo.php
 * Form-data: tramite_id, archivo (file)
 * Tipos permitidos: PDF, JPG, PNG
 * Tamaño máximo: 10MB
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/cors.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

requireAuth();

// Validar tramite_id
if (!isset($_POST['tramite_id']) || !is_numeric($_POST['tramite_id'])) {
    jsonError('El tramite_id es requerido', 400);
}

// Validar archivo
if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por el servidor',
        UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
        UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
        UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal',
        UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo',
        UPLOAD_ERR_EXTENSION => 'Extensión de PHP detuvo la subida'
    ];
    $error = $_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE;
    jsonError($errorMessages[$error] ?? 'Error al subir archivo', 400);
}

$tramiteId = (int)$_POST['tramite_id'];
$userId = getCurrentUserId();
$archivo = $_FILES['archivo'];

// Configuración
$maxSize = 10 * 1024 * 1024; // 10MB
$tiposPermitidos = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png'
];

// Validar tamaño
if ($archivo['size'] > $maxSize) {
    jsonError('El archivo excede el tamaño máximo de 10MB', 400);
}

// Validar tipo MIME
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$tipoMime = finfo_file($finfo, $archivo['tmp_name']);
finfo_close($finfo);

if (!isset($tiposPermitidos[$tipoMime])) {
    jsonError('Tipo de archivo no permitido. Solo PDF, JPG y PNG', 400);
}

$conn = getConnection();
if (!$conn) {
    jsonError('Error de conexión a la base de datos', 500);
}

// Verificar que el trámite existe y pertenece al usuario
$stmt = $conn->prepare("SELECT id, estado FROM tramites WHERE id = ? AND usuario_id = ?");
$stmt->execute([$tramiteId, $userId]);
$tramite = $stmt->fetch();

if (!$tramite) {
    jsonError('Trámite no encontrado o no tiene permisos', 404);
}

// No permitir subir archivos a trámites finalizados
if (in_array($tramite['estado'], ['aprobado', 'rechazado'])) {
    jsonError('No se pueden agregar archivos a trámites finalizados', 400);
}

// Crear directorio de uploads si no existe
$uploadDir = __DIR__ . '/../uploads/tramites/' . $tramiteId . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generar nombre único
$extension = $tiposPermitidos[$tipoMime];
$nombreArchivo = uniqid() . '_' . time() . '.' . $extension;
$rutaDestino = $uploadDir . $nombreArchivo;

// Mover archivo
if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
    jsonError('Error al guardar el archivo', 500);
}

// Registrar en BD
$stmt = $conn->prepare("
    INSERT INTO archivos_tramite (tramite_id, nombre_original, nombre_archivo, tipo_mime, tamanio)
    VALUES (?, ?, ?, ?, ?)
");
$result = $stmt->execute([
    $tramiteId,
    $archivo['name'],
    $nombreArchivo,
    $tipoMime,
    $archivo['size']
]);

if (!$result) {
    unlink($rutaDestino); // Eliminar archivo si falla BD
    jsonError('Error al registrar archivo', 500);
}

$archivoId = $conn->lastInsertId();

jsonResponse([
    'id' => $archivoId,
    'nombre_original' => $archivo['name'],
    'tipo' => $tipoMime,
    'tamanio' => $archivo['size'],
    'tamanio_formateado' => round($archivo['size'] / 1024, 2) . ' KB'
], 'Archivo subido exitosamente', 201);
