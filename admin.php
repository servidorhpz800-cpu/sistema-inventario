<?php
require_once __DIR__ . '/includes/session.php';
iniciarSesionPersistente();
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel administrador</title>
    <link rel="stylesheet" href="assets/style.css">
    <script async src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>
    <script async src="https://cdn.jsdelivr.net/npm/@zxing/library@0.21.3/umd/index.min.js"></script>
</head>
<body>
    <div class="page-shell">
        <header class="topbar">
            <div class="brand">Compuser | Admin</div>
            <nav class="nav">
                <a class="active" href="admin.php">Inventario</a>
                <a href="tienda.php">Tienda</a>
                <a href="tecnico.php">Técnicos</a>
                <a href="logout.php">Cerrar sesión</a>
            </nav>
            <div class="user-chip"><?php echo htmlspecialchars($_SESSION['nombre']); ?></div>
        </header>

        <div class="container">
            <section class="hero">
                <h1>Administración central de inventario</h1>
                <p>Control total de equipos, despachos, devoluciones y estados del stock.</p>
            </section>

            <nav class="admin-tabs" aria-label="Secciones de administración">
                <a class="<?php echo $pestana === 'inventario' ? 'active' : ''; ?>" href="admin.php?tab=inventario">Inventario</a>
                <a class="<?php echo $pestana === 'personal' ? 'active' : ''; ?>" href="admin.php?tab=personal">Técnicos y personal</a>
                <a class="<?php echo $pestana === 'reportes' ? 'active' : ''; ?>" href="admin.php?tab=reportes">Reportes técnicos</a>
                <a class="<?php echo $pestana === 'actividad' ? 'active' : ''; ?>" href="admin.php?tab=actividad">Bitácora de cambios</a>
            </nav>

            <div class="stats-grid">
                <div class="stat-card">
                    <span class="label">Inventario registrado</span>
                    <span class="value"><?php echo $stats['total']; ?></span>
                </div>
                <button type="button" class="stat-card stat-card-button" id="toggleAvailableEquipment" aria-expanded="false" aria-controls="availableEquipmentPanel">
                    <span class="label">Stock en tienda</span>
                    <span class="value"><?php echo $stats['disponibles']; ?></span>
                    <span class="stat-action">Ver módems y equipos</span>
                </button>
                <div class="stat-card">
                    <span class="label">Nuevos</span>
                    <span class="value"><?php echo $stats['nuevos']; ?></span>
                </div>
                <div class="stat-card">
                    <span class="label">Medio uso</span>
                    <span class="value"><?php echo $stats['medio_uso']; ?></span>
                </div>
                <div class="stat-card">
                    <span class="label">Dañados / No sirven</span>
                    <span class="value"><?php echo $stats['daniados']; ?></span>
                </div>
            </div>

            <section class="card available-equipment-panel" id="availableEquipmentPanel" hidden>
                <div class="orders-toolbar">
                    <div>
                        <span class="eyebrow">Stock listo para despacho</span>
                        <h3>Equipos disponibles</h3>
                    </div>
                    <span class="badge success"><?php echo $stats['disponibles']; ?> disponibles</span>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Tipo</th><th>Marca</th><th>Modelo</th><th>Serial</th><th>Condición</th><th>Ubicación</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($equiposDisponibles)): ?>
                                <tr><td colspan="6">No hay equipos disponibles en este momento.</td></tr>
                            <?php else: ?>
                                <?php foreach ($equiposDisponibles as $equipoDisponible): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($equipoDisponible['tipo']); ?></td>
                                        <td><?php echo htmlspecialchars($equipoDisponible['marca'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($equipoDisponible['modelo'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($equipoDisponible['serial'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars(str_replace('_', ' ', $equipoDisponible['condicion'])); ?></td>
                                        <td><?php echo htmlspecialchars($equipoDisponible['ubicacion'] ?: 'Tienda'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo htmlspecialchars($tipoMensaje); ?>"><?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>

            <div class="card scanner-panel">
                <div class="card-heading">
                    <div>
                        <span class="eyebrow">Escáner</span>
                        <h3>Buscar equipo por serial o código de barras</h3>
                    </div>
                    <span class="step-number">00</span>
                </div>

                <div class="scanner-box">
                    <label>
                        <span>Código / serial</span>
                        <input type="text" id="barcodeScannerInputAdmin" placeholder="Escanea o escribe el código y presiona Enter">
                    </label>
                    <div class="scanner-actions">
                        <button type="button" id="barcodeSearchButtonAdmin" class="btn btn-primary">Buscar</button>
                        <button type="button" id="barcodeCameraButtonAdmin" class="btn btn-secondary">Escanear cámara</button>
                    </div>
                </div>

                <video id="barcodeVideoAdmin" class="barcode-video" autoplay playsinline muted></video>

                <div id="barcodeResultAdmin" class="barcode-result">
                    <p>Sin búsqueda aún. El sistema buscará automáticamente el equipo por su serial.</p>
                </div>

                <p class="form-note">Si el teléfono no abre la cámara, abre la página con la IP local del equipo, por ejemplo: http://192.168.1.20/compuser y acepta el permiso de cámara.</p>
            </div>

            <?php if ($pestana === 'inventario'): ?>
            <div class="content-grid">
                <div class="card">
                    <h3><?php echo $equipoEditar ? 'Editar equipo' : 'Agregar equipo'; ?></h3>
                    <form method="POST" id="adminEquipmentForm">
                        <?php if ($equipoEditar): ?>
                            <input type="hidden" name="id" value="<?php echo (int)$equipoEditar['id']; ?>">
                        <?php endif; ?>

                        <div class="form-grid">
                            <label>
                                <span>Tipo</span>
                                <select name="tipo" required>
                                    <option value="">Seleccione</option>
                                    <option value="Modem GPON" <?php echo ($equipoEditar && $equipoEditar['tipo'] === 'Modem GPON') ? 'selected' : ''; ?>>Modem GPON</option>
                                    <option value="Modem EPON" <?php echo ($equipoEditar && $equipoEditar['tipo'] === 'Modem EPON') ? 'selected' : ''; ?>>Modem EPON</option>
                                    <option value="Caja de empalme" <?php echo ($equipoEditar && $equipoEditar['tipo'] === 'Caja de empalme') ? 'selected' : ''; ?>>Caja de empalme</option>
                                    <option value="Caja NAP 8" <?php echo ($equipoEditar && $equipoEditar['tipo'] === 'Caja NAP 8') ? 'selected' : ''; ?>>Caja NAP 8</option>
                                    <option value="Caja NAP 16" <?php echo ($equipoEditar && $equipoEditar['tipo'] === 'Caja NAP 16') ? 'selected' : ''; ?>>Caja NAP 16</option>
                                    <option value="Empalmadora" <?php echo ($equipoEditar && $equipoEditar['tipo'] === 'Empalmadora') ? 'selected' : ''; ?>>Empalmadora</option>
                                    <option value="Otros" <?php echo ($equipoEditar && $equipoEditar['tipo'] === 'Otros') ? 'selected' : ''; ?>>Otros</option>
                                </select>
                            </label>

                            <label>
                                <span>Marca</span>
                                <input type="text" name="marca" value="<?php echo htmlspecialchars($equipoEditar['marca'] ?? ''); ?>">
                            </label>

                            <label>
                                <span>Modelo</span>
                                <input type="text" name="modelo" value="<?php echo htmlspecialchars($equipoEditar['modelo'] ?? ''); ?>">
                            </label>

                            <label>
                                <span>Condición</span>
                                <select name="condicion">
                                    <option value="nuevo" <?php echo ($equipoEditar && $equipoEditar['condicion'] === 'nuevo') ? 'selected' : ''; ?>>Nuevo</option>
                                    <option value="medio_uso" <?php echo ($equipoEditar && $equipoEditar['condicion'] === 'medio_uso') ? 'selected' : ''; ?>>Medio uso</option>
                                    <option value="recogido" <?php echo ($equipoEditar && $equipoEditar['condicion'] === 'recogido') ? 'selected' : ''; ?>>Recogido</option>
                                    <option value="no_serve" <?php echo ($equipoEditar && $equipoEditar['condicion'] === 'no_serve') ? 'selected' : ''; ?>>No sirve</option>
                                    <option value="danado" <?php echo ($equipoEditar && $equipoEditar['condicion'] === 'danado') ? 'selected' : ''; ?>>Dañado</option>
                                </select>
                            </label>

                            <label>
                                <span>Estado</span>
                                <select name="estado">
                                    <option value="disponible" <?php echo ($equipoEditar && $equipoEditar['estado'] === 'disponible') ? 'selected' : ''; ?>>Disponible</option>
                                    <option value="asignado" <?php echo ($equipoEditar && $equipoEditar['estado'] === 'asignado') ? 'selected' : ''; ?>>Asignado</option>
                                    <option value="en_reparacion" <?php echo ($equipoEditar && $equipoEditar['estado'] === 'en_reparacion') ? 'selected' : ''; ?>>En reparación</option>
                                    <option value="retirado" <?php echo ($equipoEditar && $equipoEditar['estado'] === 'retirado') ? 'selected' : ''; ?>>Retirado</option>
                                    <option value="dado_baja" <?php echo ($equipoEditar && $equipoEditar['estado'] === 'dado_baja') ? 'selected' : ''; ?>>Dado de baja</option>
                                </select>
                            </label>

                            <label>
                                <span>Ubicación</span>
                                <input type="text" name="ubicacion" value="<?php echo htmlspecialchars($equipoEditar['ubicacion'] ?? ''); ?>">
                            </label>

                            <label>
                                <span>Número serial</span>
                                <div class="serial-scanner-wrap">
                                    <input type="text" name="serial" id="serialInputAdmin" value="<?php echo htmlspecialchars($equipoEditar['serial'] ?? ''); ?>">
                                    <button type="button" class="btn btn-secondary" data-scan-target="serialInputAdmin">Escanear</button>
                                    <input type="file" accept="image/*" capture="environment" class="scan-file-input" data-scan-file-target="serialInputAdmin" hidden>
                                    <button type="button" class="btn btn-secondary scan-file-trigger" data-scan-file-trigger="serialInputAdmin">Desde foto</button>
                                </div>
                            </label>
                        </div>

                        <label>
                            <span>Observaciones</span>
                            <textarea name="observaciones"><?php echo htmlspecialchars($equipoEditar['observaciones'] ?? ''); ?></textarea>
                        </label>

                        <?php if ($equipoEditar): ?>
                            <button type="submit" name="actualizar_equipo" class="btn btn-success">Guardar cambios</button>
                            <a href="admin.php" class="btn btn-secondary">Cancelar</a>
                        <?php else: ?>
                            <button type="submit" name="guardar_equipo" class="btn btn-primary">Agregar equipo</button>
                        <?php endif; ?>
                    </form>
                </div>

            </div>

            <div class="card" style="margin-top:24px;">
                <h3>Inventario general</h3>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Marca</th>
                                <th>Modelo</th>
                                <th>Condición</th>
                                <th>Estado</th>
                                <th>Ubicación</th>
                                <th>Serial</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($equipos as $equipo): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($equipo['tipo']); ?></td>
                                    <td><?php echo htmlspecialchars($equipo['marca'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($equipo['modelo'] ?: '-'); ?></td>
                                    <td><span class="badge <?php echo badgeClass($equipo['condicion']); ?>"><?php echo str_replace('_', ' ', htmlspecialchars($equipo['condicion'])); ?></span></td>
                                    <td><span class="badge <?php echo badgeClass($equipo['estado']); ?>"><?php echo str_replace('_', ' ', htmlspecialchars($equipo['estado'])); ?></span></td>
                                    <td><?php echo htmlspecialchars($equipo['ubicacion'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($equipo['serial'] ?: '-'); ?></td>
                                    <td>
                                        <div class="inline-actions">
                                            <a href="admin.php?edit=<?php echo (int)$equipo['id']; ?>" class="btn btn-secondary">Editar</a>
                                            <a href="admin.php?delete=<?php echo (int)$equipo['id']; ?>" class="btn btn-danger" onclick="return confirm('¿Deseas eliminar este equipo?');">Eliminar</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php elseif ($pestana === 'personal'): ?>
            <div class="card personnel-panel">
                <div class="orders-toolbar">
                    <div>
                        <span class="eyebrow">Personal del sistema</span>
                        <h3>Técnicos y personal</h3>
                    </div>
                    <span class="badge info"><?php echo count($usuarios); ?> usuarios</span>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Rol</th>
                                <th>Equipo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                                    <td><span class="badge <?php echo badgeClass($usuario['rol']); ?>"><?php echo htmlspecialchars($usuario['rol']); ?></span></td>
                                    <td><?php echo htmlspecialchars($usuario['equipo'] ?: 'Sin asignación'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php elseif ($pestana === 'reportes'): ?>
            <div class="card reports-panel">
                <div class="orders-toolbar">
                    <div>
                        <span class="eyebrow">Detalle de operaciones</span>
                        <h3>Reportes enviados por técnicos</h3>
                    </div>
                    <span class="badge info"><?php echo count($reportes); ?> reportes</span>
                </div>
                <p class="form-note">Consulta completa de instalaciones, retiros, recogidas y reportes de clientes. Esta sección es de solo lectura.</p>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Fecha / técnico</th>
                                <th>Cliente / servicio</th>
                                <th>Equipo</th>
                                <th>Trabajo realizado</th>
                                <th>Resultado</th>
                                <th>Evidencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reportes)): ?>
                                <tr><td colspan="6">Todavía no hay reportes técnicos.</td></tr>
                            <?php else: ?>
                                <?php foreach ($reportes as $reporte): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($reporte['created_at']))); ?><br><?php echo htmlspecialchars($reporte['tecnico']); ?><br><span class="badge <?php echo badgeClass($reporte['estado']); ?>"><?php echo htmlspecialchars($reporte['estado']); ?></span></td>
                                        <td><strong><?php echo htmlspecialchars($reporte['titulo']); ?></strong><br><?php echo htmlspecialchars($reporte['cliente_nombre'] ?: 'Sin cliente'); ?> · <?php echo htmlspecialchars($reporte['cliente_numero'] ?: 'Sin número'); ?><br><?php echo htmlspecialchars(ucfirst($reporte['tipo_orden'] ?: 'Sin orden')); ?> · Movimiento <?php echo $reporte['movimiento_id'] ? '#' . (int)$reporte['movimiento_id'] : 'sin despacho'; ?><br><?php echo htmlspecialchars(trim(($reporte['calle'] ?: '') . ' ' . ($reporte['numero_exterior'] ?: '') . ', ' . ($reporte['colonia'] ?: '')) ?: 'Sin domicilio'); ?></td>
                                        <td><?php echo htmlspecialchars(trim(($reporte['equipo_tipo'] ?: '') . ' ' . ($reporte['equipo_marca'] ?: '') . ' ' . ($reporte['equipo_serial'] ?: '')) ?: 'Sin equipo'); ?></td>
                                        <td><?php echo htmlspecialchars($reporte['actividad_realizada'] ?: 'Sin detalle'); ?><br><small><?php echo htmlspecialchars($reporte['descripcion']); ?></small><?php if (!empty($reporte['materiales'])): ?><br><strong>Materiales:</strong> <?php echo htmlspecialchars($reporte['materiales']); ?><?php endif; ?></td>
                                        <td><?php echo htmlspecialchars($reporte['resultado'] ?: 'Sin resultado'); ?></td>
                                        <td><?php foreach ($reporte['fotos'] as $indiceFoto => $foto): ?><a href="<?php echo htmlspecialchars($foto); ?>" target="_blank" rel="noopener">Ver imagen <?php echo (int)($indiceFoto + 1); ?></a><br><?php endforeach; ?><?php if ($reporte['latitud'] !== null && $reporte['longitud'] !== null): ?><a href="https://www.google.com/maps?q=<?php echo urlencode($reporte['latitud'] . ',' . $reporte['longitud']); ?>" target="_blank" rel="noopener">Ver ubicación</a><?php endif; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="orders-toolbar">
                    <div>
                        <span class="eyebrow">Seguimiento de equipos</span>
                        <h3>Resultados registrados en campo</h3>
                    </div>
                    <span class="badge info"><?php echo count($movimientosTecnico); ?> registros</span>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Fecha / técnico</th>
                                <th>Orden / cliente</th>
                                <th>Equipo</th>
                                <th>Resultado</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($movimientosTecnico)): ?>
                                <tr><td colspan="5">Todavía no hay resultados de equipos registrados por técnicos.</td></tr>
                            <?php else: ?>
                                <?php foreach ($movimientosTecnico as $movimiento): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($movimiento['updated_at']))); ?><br><?php echo htmlspecialchars($movimiento['tecnico']); ?></td>
                                        <td><?php echo $movimiento['orden_id'] ? 'Orden #' . (int)$movimiento['orden_id'] : 'Sin orden'; ?><br><?php echo htmlspecialchars($movimiento['cliente_nombre'] ?: 'Sin cliente'); ?> · <?php echo htmlspecialchars($movimiento['cliente_numero'] ?: 'Sin número'); ?></td>
                                        <td><?php echo htmlspecialchars(trim($movimiento['tipo'] . ' ' . ($movimiento['marca'] ?: '') . ' ' . ($movimiento['modelo'] ?: '') . ' ' . ($movimiento['serial'] ?: ''))); ?></td>
                                        <td><span class="badge <?php echo badgeClass($movimiento['estado']); ?>"><?php echo htmlspecialchars(str_replace('_', ' ', $movimiento['estado'])); ?></span></td>
                                        <td><?php echo htmlspecialchars($movimiento['observaciones'] ?: 'Sin observaciones'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="card activity-panel">
                <div class="orders-toolbar">
                    <div>
                        <span class="eyebrow">Auditoría central</span>
                        <h3>Bitácora de cambios</h3>
                    </div>
                    <span class="badge info"><?php echo count($actividad); ?> eventos recientes</span>
                </div>
                <p class="form-note">Registro global de las operaciones realizadas en inventario, tienda y técnicos. Esta información solo está disponible para administradores.</p>
                <div class="table-wrap">
                    <table class="table activity-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Usuario</th>
                                <th>Módulo</th>
                                <th>Acción</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($actividad)): ?>
                                <tr><td colspan="5">Todavía no hay cambios registrados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($actividad as $evento): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($evento['created_at']))); ?></td>
                                        <td><?php echo htmlspecialchars($evento['usuario_nombre']); ?><br><small><?php echo htmlspecialchars($evento['rol']); ?></small></td>
                                        <td><span class="badge info"><?php echo htmlspecialchars($evento['modulo']); ?></span></td>
                                        <td><?php echo htmlspecialchars($evento['accion']); ?></td>
                                        <td><?php echo htmlspecialchars($evento['detalle'] ?: 'Sin detalle'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const inventoryAdmin = <?php echo json_encode($equipos, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const barcodeInputAdmin = document.getElementById('barcodeScannerInputAdmin');
        const barcodeSearchButtonAdmin = document.getElementById('barcodeSearchButtonAdmin');
        const barcodeCameraButtonAdmin = document.getElementById('barcodeCameraButtonAdmin');
        const barcodeVideoAdmin = document.getElementById('barcodeVideoAdmin');
        const barcodeResultAdmin = document.getElementById('barcodeResultAdmin');
        const serialButtonsAdmin = document.querySelectorAll('[data-scan-target]');

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function mostrarEquipoEscaneado(match) {
            if (!match) {
                barcodeResultAdmin.innerHTML = '<div class="barcode-state error">No se encontró ningún equipo con ese código.</div>';
                return;
            }

            barcodeResultAdmin.innerHTML = `
                <div class="barcode-card">
                    <div class="barcode-header">
                        <strong>${escapeHtml(match.tipo || 'Equipo')}</strong>
                        <span class="badge ${match.estado === 'disponible' ? 'success' : 'primary'}">${escapeHtml(String(match.estado || 'sin estado').replace(/_/g, ' '))}</span>
                    </div>
                    <div class="barcode-grid">
                        <div><span>Marca</span><strong>${escapeHtml(match.marca || 'Sin marca')}</strong></div>
                        <div><span>Modelo</span><strong>${escapeHtml(match.modelo || 'Sin modelo')}</strong></div>
                        <div><span>Serial</span><strong>${escapeHtml(match.serial || 'Sin serial')}</strong></div>
                        <div><span>Condición</span><strong>${escapeHtml(String(match.condicion || 'nuevo').replace(/_/g, ' '))}</strong></div>
                        <div><span>Ubicación</span><strong>${escapeHtml(match.ubicacion || 'Sin ubicación')}</strong></div>
                        <div><span>Observaciones</span><strong>${escapeHtml(match.observaciones || 'Sin observaciones')}</strong></div>
                    </div>
                </div>
            `;
        }

        function completarFormularioEquipoAdmin(match) {
            const form = document.getElementById('adminEquipmentForm');
            if (!form) {
                return;
            }

            const setValue = (name, value) => {
                const field = form.querySelector(`[name="${name}"]`);
                if (field) {
                    field.value = value ?? '';
                }
            };

            if (match) {
                setValue('tipo', match.tipo || '');
                setValue('marca', match.marca || '');
                setValue('modelo', match.modelo || '');
                setValue('condicion', match.condicion || 'nuevo');
                setValue('estado', match.estado || 'disponible');
                setValue('ubicacion', match.ubicacion || '');
                setValue('serial', match.serial || '');
                setValue('observaciones', match.observaciones || '');
            } else {
                setValue('serial', barcodeInputAdmin ? barcodeInputAdmin.value.trim() : '');
            }
        }

        function buscarEquipoAdmin(codigo) {
            const rawCode = String(codigo ?? '').trim();
            if (!rawCode) {
                barcodeResultAdmin.innerHTML = '<div class="barcode-state error">Escribe o escanea un código para buscar un equipo.</div>';
                return;
            }

            const parsed = parseScannedValue(rawCode);
            const code = (parsed.serial || parsed.model || rawCode).trim();
            const normalized = code.toLowerCase();
            const match = inventoryAdmin.find((item) => {
                const serial = String(item.serial ?? '').trim();
                return serial.toLowerCase() === normalized || serial.toLowerCase().includes(normalized) || String(item.id) === code;
            });

            mostrarEquipoEscaneado(match);
            completarFormularioEquipoAdmin(match);
        }

        let adminCameraStream = null;
        let adminBarcodeTimer = null;

        function detenerCamaraAdmin() {
            if (adminCameraStream) {
                adminCameraStream.getTracks().forEach((track) => track.stop());
                adminCameraStream = null;
            }

            if (barcodeVideoAdmin) {
                barcodeVideoAdmin.srcObject = null;
                barcodeVideoAdmin.style.display = 'none';
            }

            if (window.Quagga && Quagga._state && Quagga._state.running) {
                Quagga.stop();
            }
        }

        async function iniciarEscaneoAdmin() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                barcodeResultAdmin.innerHTML = '<div class="barcode-state error">Este navegador no permite acceder a la cámara. Usa la IP del equipo y acepta el permiso de cámara, o escribe el código manualmente.</div>';
                return;
            }

            if ('BarcodeDetector' in window) {
                try {
                    if (adminCameraStream) {
                        adminCameraStream.getTracks().forEach((track) => track.stop());
                    }

                    adminCameraStream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'environment' },
                        audio: false,
                    });

                    if (barcodeVideoAdmin) {
                        barcodeVideoAdmin.srcObject = adminCameraStream;
                        barcodeVideoAdmin.style.display = 'block';
                    }

                    const detector = new BarcodeDetector({ formats: ['code_128', 'ean_13', 'ean_8', 'code_39', 'upc_a', 'upc_e', 'codabar'] });
                    const lectura = async () => {
                        if (!barcodeVideoAdmin || !barcodeVideoAdmin.videoWidth || !barcodeVideoAdmin.videoHeight) {
                            return;
                        }

                        try {
                            const barcodes = await detector.detect(barcodeVideoAdmin);
                            if (barcodes && barcodes.length > 0) {
                                const scanned = barcodes[0].rawValue;
                                if (scanned) {
                                    buscarEquipoAdmin(scanned);
                                    detenerCamaraAdmin();
                                    return;
                                }
                            }
                        } catch (error) {
                            console.warn('BarcodeDetector detect failed:', error);
                        }

                        adminBarcodeTimer = setTimeout(lectura, 350);
                    };

                    clearTimeout(adminBarcodeTimer);
                    lectura();
                    barcodeResultAdmin.innerHTML = '<div class="barcode-state ok">Cámara activa. Apunta al código de barras para escanearlo.</div>';
                    return;
                } catch (error) {
                    console.warn('Error BarcodeDetector admin:', error);
                }
            }

            if (window.Quagga) {
                if (barcodeVideoAdmin) {
                    barcodeVideoAdmin.style.display = 'block';
                    barcodeVideoAdmin.setAttribute('playsinline', 'true');
                    barcodeVideoAdmin.setAttribute('muted', 'true');
                }

                Quagga.offDetected();
                Quagga.stop();
                Quagga.init({
                    inputStream: {
                        name: 'Live',
                        type: 'LiveStream',
                        target: barcodeVideoAdmin,
                        constraints: { facingMode: 'environment' },
                    },
                    decoder: {
                        readers: ['code_128_reader', 'ean_reader', 'ean_8_reader', 'code_39_reader', 'upc_reader', 'codabar_reader'],
                    },
                    locate: true,
                }, function (err) {
                    if (err) {
                        barcodeResultAdmin.innerHTML = '<div class="barcode-state error">No se pudo iniciar el escáner de la cámara. Abre la página por la IP del equipo, acepta los permisos de cámara y vuelve a intentarlo. Si no, escribe el código manualmente.</div>';
                        return;
                    }

                    Quagga.start();
                    barcodeResultAdmin.innerHTML = '<div class="barcode-state ok">Cámara activa. Apunta al código de barras para escanearlo.</div>';
                });

                Quagga.onDetected((result) => {
                    const scanned = result && result.codeResult ? result.codeResult.code : null;
                    if (scanned) {
                        buscarEquipoAdmin(scanned);
                        Quagga.stop();
                        detenerCamaraAdmin();
                    }
                });
                return;
            }

            barcodeResultAdmin.innerHTML = '<div class="barcode-state error">Tu navegador no admite escaneo con cámara en esta página. Usa la IP local del equipo y permite la cámara, o escribe el código manualmente.</div>';
        }

        if (barcodeInputAdmin) {
            barcodeInputAdmin.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    buscarEquipoAdmin(barcodeInputAdmin.value);
                }
            });

            barcodeSearchButtonAdmin?.addEventListener('click', () => {
                buscarEquipoAdmin(barcodeInputAdmin.value);
            });

            barcodeCameraButtonAdmin?.addEventListener('click', () => {
                iniciarEscaneoAdmin();
            });
        }

        function activarEscaneoDirectoAdmin(targetInputId) {
            const targetInput = document.getElementById(targetInputId);
            if (!targetInput) {
                return;
            }

            const startDirectScan = async () => {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    const msg = 'Tu navegador no tiene acceso a la cámara. Usa la IP del equipo y acepta el permiso o escribe el serial manualmente.';
                    barcodeResultAdmin.innerHTML = '<div class="barcode-state error">' + escapeHtml(msg) + '</div>';
                    alert(msg);
                    return;
                }

                if ('BarcodeDetector' in window) {
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
                        const preview = document.createElement('video');
                        preview.srcObject = stream;
                        preview.autoplay = true;
                        preview.playsInline = true;
                        preview.muted = true;
                        preview.style.display = 'block';
                        preview.style.width = '100%';
                        preview.style.maxWidth = '420px';
                        preview.style.borderRadius = '12px';
                        preview.style.marginTop = '12px';
                        preview.style.border = '1px solid rgba(37,87,214,0.2)';

                        const container = document.createElement('div');
                        container.appendChild(preview);
                        const existing = document.getElementById('directScannerHolderAdmin');
                        if (existing) {
                            existing.replaceWith(container);
                        } else {
                            const formCard = document.querySelector('.card');
                            if (formCard) {
                                formCard.appendChild(container);
                            }
                        }
                        container.id = 'directScannerHolderAdmin';

                        const detector = new BarcodeDetector({ formats: ['code_128', 'ean_13', 'ean_8', 'code_39', 'upc_a', 'upc_e', 'codabar'] });
                        const read = async () => {
                            try {
                                const barcodes = await detector.detect(preview);
                                if (barcodes && barcodes.length > 0) {
                                    const value = barcodes[0].rawValue;
                                    if (value) {
                                        const parsedValue = aplicarCodigoEscaneadoAdmin(value, targetInput);
                                        targetInput.value = parsedValue;
                                        stream.getTracks().forEach((track) => track.stop());
                                        container.remove();
                                        return;
                                    }
                                }
                            } catch (error) {
                                console.warn('Direct scan failed admin:', error);
                            }
                            setTimeout(read, 350);
                        };
                        read();
                        return;
                    } catch (error) {
                        console.warn('Direct camera error admin:', error);
                    }
                }

                const msg = 'La cámara no está disponible en este navegador. Abre la página con la IP del equipo y acepta el permiso, o escribe el serial manualmente.';
                barcodeResultAdmin.innerHTML = '<div class="barcode-state error">' + escapeHtml(msg) + '</div>';
                alert(msg);
            };

            startDirectScan();
        }

        serialButtonsAdmin.forEach((button) => {
            button.addEventListener('click', () => {
                const targetId = button.getAttribute('data-scan-target');
                if (targetId) {
                    activarEscaneoDirectoAdmin(targetId);
                }
            });
        });

        const serialFileTriggersAdmin = document.querySelectorAll('[data-scan-file-trigger]');
        const serialFileInputsAdmin = document.querySelectorAll('[data-scan-file-target]');

        async function leerCodigoDesdeArchivoAdmin(file, targetInput) {
            if (!file || !targetInput) {
                return;
            }

            const mensajeManual = 'No se pudo leer el código en la imagen. Prueba con otra foto, usa el código del producto o escribe el serial manualmente.';

            try {
                if (window.ZXing && typeof window.ZXing.BrowserMultiFormatReader === 'function') {
                    const codeReader = new window.ZXing.BrowserMultiFormatReader();
                    const url = URL.createObjectURL(file);
                    try {
                        const result = await codeReader.decodeFromImage(undefined, url);
                        const value = result && result.getText ? result.getText() : null;
                        if (value) {
                            targetInput.value = value;
                            barcodeResultAdmin.innerHTML = `<div class="barcode-state ok">Código leído desde la foto: <strong>${escapeHtml(value)}</strong></div>`;
                            return;
                        }
                    } catch (error) {
                        console.warn('ZXing image decode failed admin:', error);
                    } finally {
                        URL.revokeObjectURL(url);
                    }
                }

                if ('BarcodeDetector' in window) {
                    const bitmap = await createImageBitmap(file);
                    const detector = new BarcodeDetector({ formats: ['code_128', 'ean_13', 'ean_8', 'code_39', 'upc_a', 'upc_e', 'codabar'] });
                    const barcodes = await detector.detect(bitmap);
                    if (barcodes && barcodes.length > 0) {
                        const value = barcodes[0].rawValue;
                        if (value) {
                            targetInput.value = value;
                            barcodeResultAdmin.innerHTML = `<div class="barcode-state ok">Código leído desde la foto: <strong>${escapeHtml(value)}</strong></div>`;
                            return;
                        }
                    }
                }

                if (window.Quagga) {
                    const url = URL.createObjectURL(file);
                    const resultado = await new Promise((resolve) => {
                        Quagga.decodeSingle({
                            decoder: {
                                readers: ['code_128_reader', 'ean_reader', 'ean_8_reader', 'code_39_reader', 'upc_reader', 'codabar_reader'],
                            },
                            locate: true,
                            src: url,
                            numOfWorkers: 0,
                        }, function (result) {
                            URL.revokeObjectURL(url);
                            if (result && result.codeResult && result.codeResult.code) {
                                resolve(result.codeResult.code);
                                return;
                            }
                            resolve(null);
                        });
                    });

                    if (resultado) {
                        targetInput.value = resultado;
                        barcodeResultAdmin.innerHTML = `<div class="barcode-state ok">Código leído desde la foto: <strong>${escapeHtml(resultado)}</strong></div>`;
                        return;
                    }
                }

                alert(mensajeManual);
            } catch (error) {
                console.warn('File scan failed admin:', error);
                alert(mensajeManual);
            }
        }

        serialFileTriggersAdmin.forEach((button) => {
            button.addEventListener('click', () => {
                const targetId = button.getAttribute('data-scan-file-trigger');
                if (!targetId) {
                    return;
                }

                const targetInput = document.getElementById(targetId);
                const fileInput = document.querySelector(`[data-scan-file-target="${targetId}"]`);
                if (fileInput) {
                    fileInput.click();
                }
                if (targetInput) {
                    targetInput.focus();
                }
            });
        });

        serialFileInputsAdmin.forEach((fileInput) => {
            fileInput.addEventListener('change', async (event) => {
                const targetId = fileInput.getAttribute('data-scan-file-target');
                const targetInput = document.getElementById(targetId);
                const file = event.target.files && event.target.files[0];
                if (file && targetInput) {
                    await leerCodigoDesdeArchivoAdmin(file, targetInput);
                }
                fileInput.value = '';
            });
        });

        const adminTabs = document.querySelectorAll('.admin-tabs a');
        adminTabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                adminTabs.forEach((item) => item.classList.remove('active'));
                tab.classList.add('active');
            });
        });

        const availableEquipmentToggle = document.getElementById('toggleAvailableEquipment');
        const availableEquipmentPanel = document.getElementById('availableEquipmentPanel');
        if (availableEquipmentToggle && availableEquipmentPanel) {
            availableEquipmentToggle.addEventListener('click', () => {
                const isOpen = availableEquipmentToggle.getAttribute('aria-expanded') === 'true';
                availableEquipmentToggle.setAttribute('aria-expanded', String(!isOpen));
                availableEquipmentPanel.hidden = isOpen;
                if (!isOpen) {
                    availableEquipmentPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        }
    </script>
</body>
</html>
