<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

iniciarSesionPersistente();
requireLogin(['admin', 'tienda', 'tecnico']);

$nombreArchivo = (string)($_GET['archivo'] ?? '');
if (preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,127}\.(?:jpe?g|png|webp)\z/i', $nombreArchivo) !== 1) {
    http_response_code(404);
    exit('Evidencia no encontrada.');
}

try {
    $directorios = array_unique([
        getReportesUploadDirectory(),
        __DIR__ . '/uploads/reportes',
    ]);
    $rutaArchivo = null;
    foreach ($directorios as $directorio) {
        $candidato = $directorio . DIRECTORY_SEPARATOR . $nombreArchivo;
        if (is_file($candidato) && is_readable($candidato)) {
            $rutaArchivo = $candidato;
            break;
        }
    }
} catch (Throwable $error) {
    error_log('No se pudo resolver el almacenamiento de evidencias: ' . $error->getMessage());
    http_response_code(500);
    exit('No se pudo consultar la evidencia.');
}

if ($rutaArchivo === null) {
    http_response_code(404);
    exit('La evidencia no está disponible en el almacenamiento configurado.');
}

$tiposPermitidos = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
];
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($rutaArchivo);
$extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
if (!isset($tiposPermitidos[$extension]) || $tiposPermitidos[$extension] !== $mime) {
    http_response_code(415);
    exit('Formato de evidencia no permitido.');
}

$tamanoArchivo = filesize($rutaArchivo);
$hashArchivo = hash_file('sha256', $rutaArchivo);
if ($tamanoArchivo === false || $hashArchivo === false) {
    error_log('No se pudieron leer los metadatos de una evidencia.');
    http_response_code(500);
    exit('No se pudo consultar la evidencia.');
}

$etag = '"' . $hashArchivo . '"';
header('Cache-Control: private, max-age=86400');
header('ETag: ' . $etag);
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)$tamanoArchivo);
header('Content-Disposition: inline; filename="' . basename($rutaArchivo) . '"');
header('X-Content-Type-Options: nosniff');
readfile($rutaArchivo);
