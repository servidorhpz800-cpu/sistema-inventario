<?php
require_once __DIR__ . '/includes/session.php';
iniciarSesionPersistente();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? null) !== 'tecnico') {
    http_response_code(401);
    echo json_encode(['error' => 'Debes iniciar sesión como técnico.']);
    exit;
}

$ultimoId = filter_var($_GET['after'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 0],
]);
if ($ultimoId === false) {
    http_response_code(400);
    echo json_encode(['error' => 'El identificador de orden no es válido.']);
    exit;
}

require __DIR__ . '/config/database.php';

try {
    $stmt = $pdo->prepare('SELECT id, tipo_orden FROM ordenes WHERE tecnico_id = :tecnico_id AND id > :ultimo_id ORDER BY id ASC LIMIT 20');
    $stmt->execute([
        'tecnico_id' => (int)$_SESSION['usuario_id'],
        'ultimo_id' => $ultimoId,
    ]);
    echo json_encode(['ordenes' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    error_log('No se pudieron consultar las notificaciones de órdenes: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'No se pudieron consultar las órdenes.']);
}
