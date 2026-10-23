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

$tiposPermitidos = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
];
$extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
if (!isset($tiposPermitidos[$extension])) {
    http_response_code(415);
    exit('Formato de evidencia no permitido.');
}

try {
    $evidenciaR2 = getReportEvidenceFromR2($nombreArchivo);
    $rutaArchivo = $evidenciaR2 === null ? getLocalReportEvidencePath($nombreArchivo) : null;
} catch (Throwable $error) {
    error_log('No se pudo consultar una evidencia: ' . $error->getMessage());
    http_response_code(500);
    exit('No se pudo consultar la evidencia.');
}

if ($evidenciaR2 === null && $rutaArchivo === null) {
    http_response_code(404);
    exit('La evidencia no está disponible en el almacenamiento configurado.');
}

if ($evidenciaR2 !== null) {
    $mime = (string)($evidenciaR2['ContentType'] ?? '');
    $tamanoArchivo = (int)($evidenciaR2['ContentLength'] ?? 0);
    $etag = (string)($evidenciaR2['ETag'] ?? '');
    if (
        $tiposPermitidos[$extension] !== $mime
        || $tamanoArchivo <= 0
        || $etag === ''
        || preg_match('/[\r\n]/', $etag) === 1
    ) {
        error_log('Los metadatos de una evidencia de R2 no son válidos.');
        http_response_code(415);
        exit('Formato de evidencia no permitido.');
    }
} else {
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($rutaArchivo);
    if ($tiposPermitidos[$extension] !== $mime) {
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
}

header('Cache-Control: private, max-age=86400');
header('ETag: ' . $etag);
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)$tamanoArchivo);
header('Content-Disposition: inline; filename="' . $nombreArchivo . '"');
header('X-Content-Type-Options: nosniff');
if ($evidenciaR2 !== null) {
    $salida = fopen('php://output', 'wb');
    if ($salida === false) {
        error_log('No se pudo abrir la respuesta para transmitir una evidencia de R2.');
        http_response_code(500);
        exit('No se pudo consultar la evidencia.');
    }

    $contenidoR2 = $evidenciaR2['Body']->detach();
    if (stream_copy_to_stream($contenidoR2, $salida) === false) {
        error_log('No se pudo transmitir una evidencia desde R2.');
    }
    fclose($contenidoR2);
    fclose($salida);
} else {
    readfile($rutaArchivo);
}
