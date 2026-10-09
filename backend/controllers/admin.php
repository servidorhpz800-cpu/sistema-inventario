<?php
require_once dirname(__DIR__, 2) . '/includes/session.php';
iniciarSesionPersistente();
require dirname(__DIR__, 2) . '/config/database.php';
require dirname(__DIR__, 2) . '/includes/functions.php';
requireLogin(['admin']);

$mensaje = null;
$tipoMensaje = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_equipo'])) {
    $tipo = trim($_POST['tipo'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $condicion = $_POST['condicion'] ?? 'nuevo';
    $estado = $_POST['estado'] ?? 'disponible';
    $ubicacion = trim($_POST['ubicacion'] ?? '');
    $serial = trim($_POST['serial'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');

    if ($tipo !== '') {
        $stmt = $pdo->prepare('INSERT INTO equipos (tipo, marca, modelo, condicion, estado, ubicacion, serial, observaciones) VALUES (:tipo, :marca, :modelo, :condicion, :estado, :ubicacion, :serial, :observaciones)');
        $stmt->execute([
            'tipo' => $tipo,
            'marca' => $marca,
            'modelo' => $modelo,
            'condicion' => $condicion,
            'estado' => $estado,
            'ubicacion' => $ubicacion,
            'serial' => $serial,
            'observaciones' => $observaciones,
        ]);
        registrarActividad($pdo, 'Admin', 'Crear equipo', 'equipo', (int)$pdo->lastInsertId(), "Equipo {$tipo} agregado al inventario.");
        $mensaje = 'Equipo agregado correctamente.';
    } else {
        $mensaje = 'El tipo de equipo es obligatorio.';
        $tipoMensaje = 'danger';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_equipo'])) {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('UPDATE equipos SET tipo = :tipo, marca = :marca, modelo = :modelo, condicion = :condicion, estado = :estado, ubicacion = :ubicacion, serial = :serial, observaciones = :observaciones WHERE id = :id');
    $stmt->execute([
        'tipo' => trim($_POST['tipo'] ?? ''),
        'marca' => trim($_POST['marca'] ?? ''),
        'modelo' => trim($_POST['modelo'] ?? ''),
        'condicion' => $_POST['condicion'] ?? 'nuevo',
        'estado' => $_POST['estado'] ?? 'disponible',
        'ubicacion' => trim($_POST['ubicacion'] ?? ''),
        'serial' => trim($_POST['serial'] ?? ''),
        'observaciones' => trim($_POST['observaciones'] ?? ''),
        'id' => $id,
    ]);
    registrarActividad($pdo, 'Admin', 'Actualizar equipo', 'equipo', $id, 'Información del equipo actualizada desde el inventario.');
    $mensaje = 'Equipo actualizado correctamente.';
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare('DELETE FROM equipos WHERE id = :id')->execute(['id' => $id]);
    registrarActividad($pdo, 'Admin', 'Eliminar equipo', 'equipo', $id, 'Equipo eliminado del inventario.');
    $mensaje = 'Equipo eliminado correctamente.';
}

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$equipoEditar = null;
if ($editId > 0) {
    $equipoEditar = $pdo->prepare('SELECT * FROM equipos WHERE id = :id LIMIT 1');
    $equipoEditar->execute(['id' => $editId]);
    $equipoEditar = $equipoEditar->fetch();
}

$equipos = getInventario($pdo);
$equiposDisponibles = array_filter($equipos, fn($equipo) => $equipo['estado'] === 'disponible');
$usuarios = getUsuarios($pdo);
$reportes = getReportes($pdo);
$movimientosTecnico = getMovimientosTecnico($pdo);
$stats = [
    'total' => count($equipos),
    'disponibles' => count(array_filter($equipos, fn($item) => $item['estado'] === 'disponible')),
    'nuevos' => count(array_filter($equipos, fn($item) => $item['condicion'] === 'nuevo')),
    'medio_uso' => count(array_filter($equipos, fn($item) => $item['condicion'] === 'medio_uso')),
    'daniados' => count(array_filter($equipos, fn($item) => $item['condicion'] === 'danado' || $item['condicion'] === 'no_serve')),
];
$actividad = getActividad($pdo);
$pestana = $_GET['tab'] ?? 'inventario';
if (!in_array($pestana, ['inventario', 'personal', 'reportes', 'actividad'], true)) {
    $pestana = 'inventario';
}
$resumenReportesClientes = $pestana === 'reportes' ? getResumenReportesClientes($pdo) : [];
?>