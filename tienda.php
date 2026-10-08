<?php
require_once __DIR__ . '/includes/session.php';
iniciarSesionPersistente();
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';
requireLogin(['tienda', 'admin']);

$mensaje = null;
$tipoMensaje = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_equipo'])) {
    $tipo = trim($_POST['tipo'] ?? '');
    if ($tipo !== '') {
        $stmt = $pdo->prepare('INSERT INTO equipos (tipo, marca, modelo, condicion, estado, ubicacion, serial, observaciones) VALUES (:tipo, :marca, :modelo, :condicion, :estado, :ubicacion, :serial, :observaciones)');
        $stmt->execute([
            'tipo' => $tipo,
            'marca' => trim($_POST['marca'] ?? ''),
            'modelo' => trim($_POST['modelo'] ?? ''),
            'condicion' => $_POST['condicion'] ?? 'nuevo',
            'estado' => $_POST['estado'] ?? 'disponible',
            'ubicacion' => trim($_POST['ubicacion'] ?? 'Tienda'),
            'serial' => trim($_POST['serial'] ?? ''),
            'observaciones' => trim($_POST['observaciones'] ?? ''),
        ]);
        registrarActividad($pdo, 'Tienda', 'Registrar equipo', 'equipo', (int)$pdo->lastInsertId(), "Equipo {$tipo} agregado al stock.");
        $mensaje = 'Equipo registrado en inventario.';
    } else {
        $mensaje = 'Debe indicar el tipo del equipo.';
        $tipoMensaje = 'danger';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_orden'])) {
    $equipoId = null;
    $tecnicoId = (int)($_POST['tecnico_id'] ?? 0);
    $tipoOrden = $_POST['tipo_orden'] ?? 'retiro';
    $tiposPermitidos = ['retiro', 'instalacion', 'recogida', 'reporte'];
    $clienteNombre = trim($_POST['cliente_nombre'] ?? '');
    $clienteNumero = trim($_POST['cliente_numero'] ?? '');
    $clienteId = (int)($_POST['cliente_id'] ?? 0);
    $calle = trim($_POST['calle'] ?? '');
    $ipAsignada = trim($_POST['ip_asignada'] ?? '');
    if ($tecnicoId > 0 && in_array($tipoOrden, $tiposPermitidos, true) && $clienteNombre !== '' && $clienteNumero !== '' && $calle !== '') {
        if ($ipAsignada !== '' && filter_var($ipAsignada, FILTER_VALIDATE_IP) === false) {
            $mensaje = 'La IP asignada no tiene un formato válido.';
            $tipoMensaje = 'danger';
        } else {
            try {
                $pdo->beginTransaction();
                $datosCliente = [
                    'nombre' => $clienteNombre,
                    'numero' => $clienteNumero,
                    'calle' => $calle,
                    'numero_exterior' => trim($_POST['numero_exterior'] ?? ''),
                    'colonia' => trim($_POST['colonia'] ?? ''),
                    'referencias' => trim($_POST['referencias'] ?? ''),
                    'ubicacion_url' => trim($_POST['ubicacion_url'] ?? ''),
                ];

                if ($clienteId > 0) {
                    $clienteStmt = $pdo->prepare('UPDATE clientes SET nombre = :nombre, numero = :numero, calle = :calle, numero_exterior = :numero_exterior, colonia = :colonia, referencias = :referencias, ubicacion_url = :ubicacion_url WHERE id = :id');
                    $clienteStmt->execute($datosCliente + ['id' => $clienteId]);
                    if ($clienteStmt->rowCount() === 0) {
                        $verificarCliente = $pdo->prepare('SELECT id FROM clientes WHERE id = :id LIMIT 1');
                        $verificarCliente->execute(['id' => $clienteId]);
                        if (!$verificarCliente->fetchColumn()) {
                            throw new RuntimeException('El cliente seleccionado ya no existe.');
                        }
                    }
                } else {
                    $buscarCliente = $pdo->prepare('SELECT id FROM clientes WHERE numero = :numero LIMIT 1');
                    $buscarCliente->execute(['numero' => $clienteNumero]);
                    $clienteId = (int)$buscarCliente->fetchColumn();
                    if ($clienteId > 0) {
                        $clienteStmt = $pdo->prepare('UPDATE clientes SET nombre = :nombre, calle = :calle, numero_exterior = :numero_exterior, colonia = :colonia, referencias = :referencias, ubicacion_url = :ubicacion_url WHERE id = :id');
                        $clienteStmt->execute($datosCliente + ['id' => $clienteId]);
                    } else {
                        $clienteStmt = $pdo->prepare('INSERT INTO clientes (nombre, numero, calle, numero_exterior, colonia, referencias, ubicacion_url) VALUES (:nombre, :numero, :calle, :numero_exterior, :colonia, :referencias, :ubicacion_url)');
                        $clienteStmt->execute($datosCliente);
                        $clienteId = (int)$pdo->lastInsertId();
                    }
                }

                $stmt = $pdo->prepare('INSERT INTO ordenes (equipo_id, tecnico_id, cliente_id, cliente_nombre, cliente_numero, calle, numero_exterior, colonia, referencias, ubicacion_url, ip_asignada, tipo_orden, estado, descripcion) VALUES (:equipo_id, :tecnico_id, :cliente_id, :cliente_nombre, :cliente_numero, :calle, :numero_exterior, :colonia, :referencias, :ubicacion_url, :ip_asignada, :tipo_orden, :estado, :descripcion)');
                $stmt->execute([
                    'equipo_id' => $equipoId,
                    'tecnico_id' => $tecnicoId,
                    'cliente_id' => $clienteId,
                    'cliente_nombre' => $clienteNombre,
                    'cliente_numero' => $clienteNumero,
                    'calle' => $calle,
                    'numero_exterior' => $datosCliente['numero_exterior'],
                    'colonia' => $datosCliente['colonia'],
                    'referencias' => $datosCliente['referencias'],
                    'ubicacion_url' => $datosCliente['ubicacion_url'],
                    'ip_asignada' => $ipAsignada !== '' ? $ipAsignada : null,
                    'tipo_orden' => $tipoOrden,
                    'estado' => 'pendiente',
                    'descripcion' => trim($_POST['descripcion'] ?? ''),
                ]);
                $ordenId = (int)$pdo->lastInsertId();
                registrarActividad($pdo, 'Tienda', 'Crear orden', 'orden', $ordenId, "Orden {$tipoOrden} creada para {$clienteNombre} y asignada al técnico.");
                $pdo->commit();
                $mensaje = 'Orden creada y asignada al técnico. El cliente quedó guardado para futuras visitas.';
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('No se pudo crear la orden: ' . $error->getMessage());
                $mensaje = 'No se pudo crear la orden. Verifica los datos del cliente e inténtalo de nuevo.';
                $tipoMensaje = 'danger';
            }
        }
    } else {
        $mensaje = ($clienteNombre === '' || $clienteNumero === '' || $calle === '')
            ? 'Completa el nombre del cliente, teléfono y calle.'
            : ($ipAsignada !== '' && filter_var($ipAsignada, FILTER_VALIDATE_IP) === false ? 'La IP asignada no tiene un formato válido.' : 'Debes seleccionar un técnico.');
        $tipoMensaje = 'danger';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['despachar_equipo'])) {
    $equipoId = (int)($_POST['despacho_equipo_id'] ?? 0);
    $tecnicoId = (int)($_POST['despacho_tecnico_id'] ?? 0);
    $ordenId = (int)($_POST['despacho_orden_id'] ?? 0);
    $observaciones = trim($_POST['despacho_observaciones'] ?? '');
    $clienteNombreMovimiento = null;
    $clienteNumeroMovimiento = null;

    try {
        $pdo->beginTransaction();

        $tecnicoStmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = :id AND rol = 'tecnico' AND activo = 1 LIMIT 1");
        $tecnicoStmt->execute(['id' => $tecnicoId]);
        $tecnicoValido = $tecnicoStmt->fetchColumn();

        $ordenValida = true;
        if ($ordenId > 0) {
            $ordenStmt = $pdo->prepare('SELECT id, cliente_nombre, cliente_numero FROM ordenes WHERE id = :id AND tecnico_id = :tecnico_id LIMIT 1');
            $ordenStmt->execute(['id' => $ordenId, 'tecnico_id' => $tecnicoId]);
            $orden = $ordenStmt->fetch();
            $ordenValida = (bool)$orden;
            if ($orden) {
                $clienteNombreMovimiento = trim((string)$orden['cliente_nombre']) ?: null;
                $clienteNumeroMovimiento = trim((string)$orden['cliente_numero']) ?: null;
            }
        }

        $actualizarEquipo = $pdo->prepare("UPDATE equipos SET estado = 'asignado', ubicacion = :ubicacion WHERE id = :id AND estado = 'disponible'");
        if ($tecnicoValido && $ordenValida) {
            $actualizarEquipo->execute(['ubicacion' => 'Técnico asignado', 'id' => $equipoId]);
        }

        if ($equipoId > 0 && $tecnicoValido && $ordenValida && $actualizarEquipo->rowCount() === 1) {
            $stmt = $pdo->prepare("INSERT INTO equipos_tecnico (equipo_id, tecnico_id, orden_id, cliente_nombre, cliente_numero, estado, observaciones) VALUES (:equipo_id, :tecnico_id, :orden_id, :cliente_nombre, :cliente_numero, 'portado', :observaciones)");
            $stmt->execute([
                'equipo_id' => $equipoId,
                'tecnico_id' => $tecnicoId,
                'orden_id' => $ordenId > 0 ? $ordenId : null,
                'cliente_nombre' => $clienteNombreMovimiento,
                'cliente_numero' => $clienteNumeroMovimiento,
                'observaciones' => $observaciones,
            ]);
            registrarActividad($pdo, 'Tienda', 'Despachar equipo', 'equipo', $equipoId, "Equipo despachado al técnico ID {$tecnicoId}." . ($ordenId > 0 ? " Relacionado con la orden #{$ordenId}." : ''));
            $pdo->commit();
            $mensaje = 'Equipo despachado; ya no está disponible en stock y quedó asignado al técnico.';
        } else {
            $pdo->rollBack();
            $mensaje = !$tecnicoValido
                ? 'Selecciona un técnico activo válido.'
                : (!$ordenValida
                    ? 'La orden seleccionada no pertenece al técnico elegido.'
                    : 'Ese equipo ya no está disponible; actualiza la página y vuelve a intentarlo.');
            $tipoMensaje = 'danger';
        }
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('No se pudo completar el despacho: ' . $error->getMessage());
        $mensaje = 'No se pudo completar el despacho. No se guardaron cambios; inténtalo de nuevo.';
        $tipoMensaje = 'danger';
    }
}

$equipos = getInventario($pdo);
$equiposDisponibles = array_filter($equipos, fn($equipo) => $equipo['estado'] === 'disponible');
$tecnicos = getUsuarios($pdo, 'tecnico');
$archivadas = ($_GET['archivo'] ?? '') === '1';
$ordenes = getOrdenes($pdo, null, $archivadas);
$reportes = getReportes($pdo, null, $archivadas);
$clientes = $pdo->query('SELECT c.*, COUNT(o.id) AS total_ordenes, MAX(o.created_at) AS ultima_visita, (SELECT o_ip.ip_asignada FROM ordenes o_ip WHERE o_ip.cliente_id = c.id AND o_ip.ip_asignada IS NOT NULL AND o_ip.ip_asignada <> \'\' ORDER BY o_ip.created_at DESC, o_ip.id DESC LIMIT 1) AS ip_asignada FROM clientes c LEFT JOIN ordenes o ON o.cliente_id = c.id GROUP BY c.id ORDER BY c.updated_at DESC, c.nombre ASC')->fetchAll();
$equiposEnCampo = $pdo->query("SELECT et.*, e.tipo, e.marca, e.modelo, e.serial, u.nombre AS tecnico, o.id AS orden_numero, o.tipo_orden, COALESCE(et.cliente_nombre, o.cliente_nombre) AS cliente_nombre_actual, COALESCE(et.cliente_numero, o.cliente_numero) AS cliente_numero_actual FROM equipos_tecnico et JOIN equipos e ON e.id = et.equipo_id JOIN usuarios u ON u.id = et.tecnico_id LEFT JOIN ordenes o ON o.id = et.orden_id ORDER BY et.updated_at DESC")->fetchAll();
foreach ($equiposEnCampo as &$movimientoCampo) {
    $movimientoCampo['cliente_nombre'] = $movimientoCampo['cliente_nombre_actual'];
    $movimientoCampo['cliente_numero'] = $movimientoCampo['cliente_numero_actual'];
    unset($movimientoCampo['cliente_nombre_actual'], $movimientoCampo['cliente_numero_actual']);
}
unset($movimientoCampo);
$stats = [
    'total' => count($equipos),
    'disponibles' => count(array_filter($equipos, fn($item) => $item['estado'] === 'disponible')),
    'asignados' => count(array_filter($equipos, fn($item) => $item['estado'] === 'asignado')),
    'pendientes' => count(array_filter($ordenes, fn($item) => $item['estado'] === 'pendiente')),
];
$vistaTienda = $_GET['vista'] ?? 'operacion';
if (!in_array($vistaTienda, ['operacion', 'clientes'], true)) {
    $vistaTienda = 'operacion';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel tienda</title>
    <link rel="stylesheet" href="assets/style.css">
    <script async src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>
    <script async src="https://cdn.jsdelivr.net/npm/@zxing/library@0.21.3/umd/index.min.js"></script>
</head>
<body>
    <div class="page-shell">
        <header class="topbar">
            <div class="brand">Compuser | Tienda</div>
            <nav class="nav">
                <a href="admin.php">Admin</a>
                <a class="active" href="tienda.php">Tienda</a>
                <a href="tecnico.php">Técnicos</a>
                <a href="logout.php">Cerrar sesión</a>
            </nav>
            <div class="user-chip"><?php echo htmlspecialchars($_SESSION['nombre']); ?></div>
        </header>

        <div class="container">
            <section class="hero">
                <h1><?php echo $vistaTienda === 'clientes' ? 'Directorio de clientes' : 'Centro de despacho y registro'; ?></h1>
                <p><?php echo $vistaTienda === 'clientes' ? 'Consulta los datos y domicilios de clientes para reutilizarlos al crear una orden.' : 'Agrega equipos, controla el stock y asigna órdenes a los técnicos.'; ?></p>
            </section>

            <nav class="admin-tabs tienda-tabs" aria-label="Secciones de tienda">
                <a class="<?php echo $vistaTienda === 'operacion' ? 'active' : ''; ?>" href="tienda.php">Operación</a>
                <a class="<?php echo $vistaTienda === 'clientes' ? 'active' : ''; ?>" href="tienda.php?vista=clientes">Clientes</a>
                <?php if ($vistaTienda === 'operacion'): ?>
                    <a class="<?php echo $archivadas ? 'active' : ''; ?>" href="tienda.php?archivo=1">Archivo diario</a>
                <?php endif; ?>
            </nav>

            <?php if ($vistaTienda === 'clientes'): ?>
            <section class="card customer-directory">
                <div class="orders-toolbar">
                    <div>
                        <span class="eyebrow">Directorio de clientes</span>
                        <h3>Clientes registrados</h3>
                    </div>
                    <label class="orders-filter">
                        <span>Buscar cliente</span>
                        <input type="search" id="buscarCliente" placeholder="Nombre, teléfono o domicilio">
                    </label>
                    <span class="badge info"><?php echo count($clientes); ?> clientes</span>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Cliente</th><th>Teléfono</th><th>Domicilio</th><th>IP más reciente</th><th>Referencias</th><th>Órdenes</th><th>Última visita</th></tr>
                        </thead>
                        <tbody id="tablaClientes">
                            <?php if (empty($clientes)): ?>
                                <tr><td colspan="7">Los clientes se guardarán aquí al crear su primera orden.</td></tr>
                            <?php else: ?>
                                <?php foreach ($clientes as $cliente): ?>
                                    <?php $busquedaCliente = strtolower(trim($cliente['nombre'] . ' ' . $cliente['numero'] . ' ' . $cliente['calle'] . ' ' . ($cliente['numero_exterior'] ?? '') . ' ' . ($cliente['colonia'] ?? ''))); ?>
                                    <tr data-client-search="<?php echo htmlspecialchars($busquedaCliente, ENT_QUOTES); ?>">
                                        <td><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                                        <td><a href="tel:<?php echo htmlspecialchars($cliente['numero']); ?>"><?php echo htmlspecialchars($cliente['numero']); ?></a></td>
                                        <td><?php echo htmlspecialchars(trim($cliente['calle'] . ' ' . ($cliente['numero_exterior'] ?: '') . ', ' . ($cliente['colonia'] ?: ''))); ?><?php if (!empty($cliente['ubicacion_url'])): ?><br><a href="<?php echo htmlspecialchars($cliente['ubicacion_url']); ?>" target="_blank" rel="noopener">Abrir mapa</a><?php endif; ?></td>
                                        <td><?php echo htmlspecialchars($cliente['ip_asignada'] ?: 'Sin IP registrada'); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['referencias'] ?: '-'); ?></td>
                                        <td><?php echo (int)$cliente['total_ordenes']; ?></td>
                                        <td><?php echo $cliente['ultima_visita'] ? htmlspecialchars(date('d/m/Y', strtotime($cliente['ultima_visita']))) : '-'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <p id="sinClientesFiltrados" class="form-note" hidden>No hay clientes que coincidan con la búsqueda.</p>
                </div>
            </section>
            <?php else: ?>
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
                    <span class="label">Asignados</span>
                    <span class="value"><?php echo $stats['asignados']; ?></span>
                </div>
                <div class="stat-card">
                    <span class="label">Órdenes pendientes</span>
                    <span class="value"><?php echo $stats['pendientes']; ?></span>
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
                        <h3>Buscar equipo por código de barras</h3>
                    </div>
                    <span class="step-number">00</span>
                </div>

                <div class="scanner-box">
                    <label>
                        <span>Código / serial</span>
                        <input type="text" id="barcodeScannerInput" placeholder="Escanea o escribe el código y presiona Enter">
                    </label>
                    <div class="scanner-actions">
                        <button type="button" id="barcodeSearchButton" class="btn btn-primary">Buscar</button>
                        <button type="button" id="barcodeCameraButton" class="btn btn-secondary">Escanear cámara</button>
                    </div>
                </div>

                <video id="barcodeVideo" class="barcode-video" autoplay playsinline muted></video>

                <div id="barcodeResult" class="barcode-result">
                    <p>Sin búsqueda aún. La cámara o el texto se pueden usar para buscar el equipo por serial.</p>
                </div>

                <p class="form-note">Si tu celular no abre la cámara, usa la IP local del equipo, por ejemplo: http://192.168.1.20/compuser y acepta el permiso de cámara del navegador.</p>
            </div>

            <div class="content-grid">
                <div class="card">
                    <h3>Agregar equipo al stock</h3>
                    <form method="POST" id="stockEquipmentForm">
                        <div class="form-grid">
                            <label>
                                <span>Tipo</span>
                                <select name="tipo" required>
                                    <option value="">Seleccione</option>
                                    <option value="Modem GPON">Modem GPON</option>
                                    <option value="Modem EPON">Modem EPON</option>
                                    <option value="Caja de empalme">Caja de empalme</option>
                                    <option value="Caja NAP 8">Caja NAP 8</option>
                                    <option value="Caja NAP 16">Caja NAP 16</option>
                                    <option value="Empalmadora">Empalmadora</option>
                                    <option value="Otros">Otros</option>
                                </select>
                            </label>
                            <label>
                                <span>Marca</span>
                                <input type="text" name="marca">
                            </label>
                            <label>
                                <span>Modelo</span>
                                <input type="text" name="modelo">
                            </label>
                            <label>
                                <span>Condición</span>
                                <select name="condicion">
                                    <option value="nuevo">Nuevo</option>
                                    <option value="medio_uso">Medio uso</option>
                                    <option value="recogido">Recogido</option>
                                    <option value="no_serve">No sirve</option>
                                    <option value="danado">Dañado</option>
                                </select>
                            </label>
                            <label>
                                <span>Estado</span>
                                <select name="estado">
                                    <option value="disponible">Disponible</option>
                                    <option value="retirado">Retirado</option>
                                    <option value="dado_baja">Dado de baja</option>
                                </select>
                            </label>
                            <label>
                                <span>Ubicación</span>
                                <input type="text" name="ubicacion" value="Tienda">
                            </label>
                            <label>
                                <span>Serial</span>
                                <div class="serial-scanner-wrap">
                                    <input type="text" name="serial" id="serialInputTienda">
                                    <button type="button" class="btn btn-secondary" data-scan-target="serialInputTienda">Escanear</button>
                                    <input type="file" accept="image/*" capture="environment" class="scan-file-input" data-scan-file-target="serialInputTienda" hidden>
                                    <button type="button" class="btn btn-secondary scan-file-trigger" data-scan-file-trigger="serialInputTienda">Desde foto</button>
                                </div>
                            </label>
                        </div>
                        <label>
                            <span>Observaciones</span>
                            <textarea name="observaciones"></textarea>
                        </label>
                        <button type="submit" name="agregar_equipo" class="btn btn-primary">Registrar equipo</button>
                    </form>
                </div>

                <div class="card order-card">
                    <div class="card-heading">
                        <div>
                            <span class="eyebrow">Nueva asignación</span>
                            <h3>Crear orden de trabajo</h3>
                        </div>
                        <span class="step-number">01</span>
                    </div>
                    <form method="POST">
                        <label>
                            <span>Técnico</span>
                            <select name="tecnico_id" required>
                                <option value="">Seleccione</option>
                                <?php foreach ($tecnicos as $tecnico): ?>
                                    <option value="<?php echo (int)$tecnico['id']; ?>"><?php echo htmlspecialchars($tecnico['nombre']); ?> (<?php echo htmlspecialchars($tecnico['equipo'] ?: 'Técnico'); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Tipo de orden</span>
                            <select name="tipo_orden" id="tipoOrden" required>
                                <option value="retiro">Retiro</option>
                                <option value="instalacion">Instalación</option>
                                <option value="recogida">Recogida</option>
                                <option value="reporte">Reporte de cliente</option>
                            </select>
                        </label>
                        <div class="order-context" data-order-section="cliente">
                            <div class="section-label">Datos del cliente</div>
                            <label>
                                <span>Buscar cliente registrado</span>
                                <select name="cliente_id" id="clienteRegistrado">
                                    <option value="0">Nuevo cliente</option>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <option value="<?php echo (int)$cliente['id']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($cliente['nombre'], ENT_QUOTES); ?>"
                                            data-numero="<?php echo htmlspecialchars($cliente['numero'], ENT_QUOTES); ?>"
                                            data-calle="<?php echo htmlspecialchars($cliente['calle'], ENT_QUOTES); ?>"
                                            data-numero-exterior="<?php echo htmlspecialchars($cliente['numero_exterior'] ?? '', ENT_QUOTES); ?>"
                                            data-colonia="<?php echo htmlspecialchars($cliente['colonia'] ?? '', ENT_QUOTES); ?>"
                                            data-referencias="<?php echo htmlspecialchars($cliente['referencias'] ?? '', ENT_QUOTES); ?>"
                                            data-ubicacion-url="<?php echo htmlspecialchars($cliente['ubicacion_url'] ?? '', ENT_QUOTES); ?>"
                                            data-ip="<?php echo htmlspecialchars($cliente['ip_asignada'] ?? '', ENT_QUOTES); ?>"><?php echo htmlspecialchars($cliente['nombre'] . ' · ' . $cliente['numero']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <div class="form-grid">
                                <label>
                                    <span>Cliente</span>
                                    <input type="text" name="cliente_nombre" placeholder="Nombre del cliente">
                                </label>
                                <label>
                                    <span>Teléfono / número</span>
                                    <input type="tel" name="cliente_numero" placeholder="Contacto del cliente">
                                </label>
                            </div>
                        </div>
                        <div class="order-context" data-order-section="ubicacion">
                            <div class="section-label">Ubicación del servicio</div>
                            <p class="form-note">Puedes compartir una ubicación de Maps o capturar la dirección manualmente con calle, número, colonia y referencias.</p>
                            <div class="form-grid">
                                <label>
                                    <span>Calle</span>
                                    <input type="text" name="calle" placeholder="Calle del domicilio" required>
                                </label>
                                <label>
                                    <span>Número</span>
                                    <input type="text" name="numero_exterior" placeholder="Número exterior o S/N">
                                </label>
                                <label>
                                    <span>Colonia</span>
                                    <input type="text" name="colonia" placeholder="Colonia">
                                </label>
                            </div>
                            <label>
                                <span>Ubicación compartida (opcional)</span>
                                <input type="url" name="ubicacion_url" placeholder="Enlace de Google Maps o ubicación compartida">
                            </label>
                            <label>
                                <span>IP asignada al servicio</span>
                                <input type="text" name="ip_asignada" inputmode="decimal" placeholder="Ej. 192.168.1.25">
                            </label>
                            <label>
                                <span>Referencias</span>
                                <textarea name="referencias" placeholder="Entre calles, color de fachada, referencias..."></textarea>
                            </label>
                        </div>
                        <div class="order-context" data-order-section="descripcion">
                            <div class="section-label">Detalle de la orden</div>
                            <label>
                                <span>Qué necesita el técnico</span>
                                <textarea name="descripcion" placeholder="Describe la actividad solicitada..."></textarea>
                            </label>
                        </div>
                        <button type="submit" name="crear_orden" class="btn btn-success">Crear orden</button>
                    </form>
                </div>
            </div>

            <div class="card dispatch-panel" style="margin-top:24px;">
                <div class="card-heading">
                    <div>
                        <span class="eyebrow">Control de inventario en campo</span>
                        <h3>Despachar equipos a técnicos</h3>
                    </div>
                    <span class="step-number">02</span>
                </div>
                <p class="form-note">Despacha varios equipos a un técnico sin relacionarlos obligatoriamente con una sola orden.</p>
                <form method="POST" class="dispatch-form">
                    <label>
                        <span>Técnico</span>
                        <select name="despacho_tecnico_id" required>
                            <option value="">Seleccione el técnico</option>
                            <?php foreach ($tecnicos as $tecnico): ?>
                                <option value="<?php echo (int)$tecnico['id']; ?>"><?php echo htmlspecialchars($tecnico['nombre']); ?> · <?php echo htmlspecialchars($tecnico['equipo'] ?: 'Sin vehículo'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Equipo</span>
                        <select name="despacho_equipo_id" required>
                            <option value="">Seleccione un equipo disponible</option>
                            <?php foreach ($equiposDisponibles as $equipo): ?>
                                <option value="<?php echo (int)$equipo['id']; ?>"><?php echo htmlspecialchars($equipo['tipo']); ?> · <?php echo htmlspecialchars($equipo['marca'] ?: 'Sin marca'); ?> · <?php echo htmlspecialchars($equipo['serial'] ?: 'Sin serial'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Orden relacionada (opcional)</span>
                        <select name="despacho_orden_id">
                            <option value="0">Sin orden específica</option>
                            <?php foreach ($ordenes as $orden): ?>
                                <option value="<?php echo (int)$orden['id']; ?>">#<?php echo (int)$orden['id']; ?> · <?php echo htmlspecialchars(ucfirst($orden['tipo_orden'])); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Observaciones</span>
                        <input type="text" name="despacho_observaciones" placeholder="Ej. Lleva 2 modems y una caja NAP para la ruta">
                    </label>
                    <button type="submit" name="despachar_equipo" class="btn btn-primary">Registrar despacho</button>
                </form>
            </div>

            <div class="card equipment-history" style="margin-top:24px;">
                <div class="orders-toolbar">
                    <h3>Equipos que llevan los técnicos</h3>
                    <span class="badge info"><?php echo count($equiposEnCampo); ?> movimientos</span>
                </div>
                <div class="order-list">
                    <?php foreach ($equiposEnCampo as $movimiento): ?>
                        <article class="order-item">
                            <div class="order-item-head">
                                <div>
                                    <span class="eyebrow"><?php echo htmlspecialchars($movimiento['tecnico']); ?></span>
                                    <h4><?php echo htmlspecialchars($movimiento['tipo']); ?> · <?php echo htmlspecialchars($movimiento['marca'] ?: 'Sin marca'); ?></h4>
                                </div>
                                <span class="badge <?php echo badgeClass($movimiento['estado']); ?>"><?php echo htmlspecialchars(str_replace('_', ' ', $movimiento['estado'])); ?></span>
                            </div>
                            <div class="order-facts">
                                <div><span>Serial</span><?php echo htmlspecialchars($movimiento['serial'] ?: 'Sin serial'); ?></div>
                                <div><span>Orden</span><?php echo $movimiento['orden_numero'] ? '#' . (int)$movimiento['orden_numero'] : 'Sin orden específica'; ?></div>
                                <div><span>Cliente</span><?php echo htmlspecialchars($movimiento['cliente_nombre'] ?: 'Sin cliente'); ?> · <?php echo htmlspecialchars($movimiento['cliente_numero'] ?: 'Sin número'); ?></div>
                            </div>
                            <?php if (!empty($movimiento['observaciones'])): ?><p class="order-description"><?php echo htmlspecialchars($movimiento['observaciones']); ?></p><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card orders-panel" style="margin-top:24px;">
                <div class="orders-toolbar">
                    <h3>Órdenes de trabajo</h3>
                    <label class="orders-filter">
                        <span>Filtrar por tipo</span>
                        <select id="filtroOrdenes">
                            <option value="todos">Todas las órdenes</option>
                            <option value="instalacion">Instalaciones</option>
                            <option value="retiro">Retiros</option>
                            <option value="recogida">Recogidas</option>
                            <option value="reporte">Reportes</option>
                        </select>
                    </label>
                </div>
                <div class="order-list">
                    <?php foreach ($ordenes as $orden): ?>
                        <article class="order-item" data-order-type="<?php echo htmlspecialchars($orden['tipo_orden']); ?>">
                            <div class="order-item-head">
                                <div>
                                    <span class="eyebrow">Orden #<?php echo (int)$orden['id']; ?></span>
                                    <h4><?php echo htmlspecialchars(ucfirst($orden['tipo_orden'])); ?></h4>
                                </div>
                                <span class="badge <?php echo badgeClass($orden['estado']); ?>"><?php echo htmlspecialchars($orden['estado']); ?></span>
                            </div>
                            <div class="order-facts">
                                <div><span>Equipo</span><?php echo $orden['tipo'] ? htmlspecialchars($orden['tipo'] . ' · ' . ($orden['marca'] ?: 'Sin marca')) : 'Se asigna desde despacho'; ?></div>
                                <div><span>Técnico</span><?php echo htmlspecialchars($orden['tecnico']); ?></div>
                                <div><span>Cliente</span><?php echo htmlspecialchars($orden['cliente_nombre'] ?: 'Sin cliente'); ?> · <?php echo htmlspecialchars($orden['cliente_numero'] ?: 'Sin teléfono'); ?></div>
                                <div><span>Dirección</span><?php echo htmlspecialchars(trim(($orden['calle'] ?: '') . ' ' . ($orden['numero_exterior'] ?: '') . ', ' . ($orden['colonia'] ?: '')) ?: 'Sin domicilio'); ?></div>
                            </div>
                            <?php if (!empty($orden['ubicacion_url'])): ?>
                                <a href="<?php echo htmlspecialchars($orden['ubicacion_url']); ?>" target="_blank" rel="noopener" class="btn btn-secondary">Abrir mapa</a>
                            <?php endif; ?>
                            <?php if (!empty($orden['descripcion'])): ?><p class="order-description"><?php echo htmlspecialchars($orden['descripcion']); ?></p><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card reports-panel" style="margin-top:24px;">
                <div class="orders-toolbar">
                    <div>
                        <span class="eyebrow">Seguimiento técnico</span>
                        <h3>Lo que hicieron los técnicos</h3>
                    </div>
                    <span class="badge info"><?php echo count($reportes); ?> reportes</span>
                </div>
                <p class="form-note">Consulta de solo lectura de instalaciones, retiros, recogidas y reportes de clientes.</p>
                <div class="order-list">
                    <?php if (empty($reportes)): ?>
                        <p>No hay reportes técnicos registrados todavía.</p>
                    <?php else: ?>
                        <?php foreach ($reportes as $reporte): ?>
                            <article class="order-item">
                                <div class="order-item-head">
                                    <div>
                                        <span class="eyebrow">Reporte #<?php echo (int)$reporte['id']; ?> · Orden <?php echo $reporte['orden_id'] ? '#' . (int)$reporte['orden_id'] : 'sin orden'; ?> · Movimiento <?php echo $reporte['movimiento_id'] ? '#' . (int)$reporte['movimiento_id'] : 'sin despacho'; ?></span>
                                        <h4><?php echo htmlspecialchars($reporte['titulo']); ?></h4>
                                    </div>
                                    <span class="badge <?php echo badgeClass($reporte['estado']); ?>"><?php echo htmlspecialchars($reporte['estado']); ?></span>
                                </div>
                                <div class="order-facts">
                                    <div><span>Técnico</span><?php echo htmlspecialchars($reporte['tecnico']); ?></div>
                                    <div><span>Servicio</span><?php echo htmlspecialchars(ucfirst($reporte['tipo_orden'] ?: 'Sin orden')); ?></div>
                                    <div><span>Cliente</span><?php echo htmlspecialchars($reporte['cliente_nombre'] ?: 'Sin cliente'); ?> · <?php echo htmlspecialchars($reporte['cliente_numero'] ?: 'Sin número'); ?></div>
                                    <div><span>Equipo</span><?php echo htmlspecialchars(trim(($reporte['equipo_tipo'] ?: '') . ' ' . ($reporte['equipo_marca'] ?: '') . ' ' . ($reporte['equipo_serial'] ?: '')) ?: 'Sin equipo'); ?></div>
                                </div>
                                <p class="order-description"><strong>Actividad:</strong> <?php echo htmlspecialchars($reporte['actividad_realizada'] ?: 'Sin detalle'); ?></p>
                                <p class="order-description"><strong>Resultado:</strong> <?php echo htmlspecialchars($reporte['resultado'] ?: 'Sin resultado'); ?></p>
                                <p class="order-description"><strong>Descripción:</strong> <?php echo htmlspecialchars($reporte['descripcion']); ?></p>
                                <?php if (!empty($reporte['materiales'])): ?><p class="order-description"><strong>Materiales:</strong> <?php echo htmlspecialchars($reporte['materiales']); ?></p><?php endif; ?>
                                <div class="inline-actions">
                                    <?php foreach ($reporte['fotos'] as $indiceFoto => $foto): ?><a href="<?php echo htmlspecialchars($foto); ?>" target="_blank" rel="noopener" class="btn btn-secondary">Ver imagen <?php echo (int)($indiceFoto + 1); ?></a><?php endforeach; ?>
                                    <?php if ($reporte['latitud'] !== null && $reporte['longitud'] !== null): ?><a href="https://www.google.com/maps?q=<?php echo urlencode($reporte['latitud'] . ',' . $reporte['longitud']); ?>" target="_blank" rel="noopener" class="btn btn-secondary">Ver ubicación</a><?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        const inventory = <?php echo json_encode($equipos, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const barcodeInput = document.getElementById('barcodeScannerInput');
        const barcodeSearchButton = document.getElementById('barcodeSearchButton');
        const barcodeCameraButton = document.getElementById('barcodeCameraButton');
        const barcodeVideo = document.getElementById('barcodeVideo');
        const barcodeResult = document.getElementById('barcodeResult');
        const dispatchSelect = document.querySelector('select[name="despacho_equipo_id"]');
        const serialButtons = document.querySelectorAll('[data-scan-target]');
        const serialFileTriggers = document.querySelectorAll('[data-scan-file-trigger]');
        const serialFileInputs = document.querySelectorAll('[data-scan-file-target]');

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function parseScannedValue(rawValue) {
            const text = String(rawValue ?? '').trim();
            if (!text) {
                return { model: '', serial: '' };
            }

            const matchModelSerial = text.match(/(?:MODEL(?:O)?|MODELO)?\s*[:\-]?\s*([A-Za-z0-9\-/]+)\s*(?:[|,;]|\s+)?(?:SN|S\/N|sn)\s*[:\-]?\s*([A-Za-z0-9\-/]+)/i);
            if (matchModelSerial) {
                return {
                    model: (matchModelSerial[1] || '').trim(),
                    serial: (matchModelSerial[2] || '').trim(),
                };
            }

            const matchSerialOnly = text.match(/(?:SN|S\/N|sn)\s*[:\-]?\s*([A-Za-z0-9\-/]+)/i);
            if (matchSerialOnly) {
                return {
                    model: '',
                    serial: (matchSerialOnly[1] || '').trim(),
                };
            }

            const matchModelOnly = text.match(/(?:MODEL(?:O)?|MODELO)\s*[:\-]?\s*([A-Za-z0-9\-/]+)/i);
            if (matchModelOnly) {
                return {
                    model: (matchModelOnly[1] || '').trim(),
                    serial: '',
                };
            }

            return { model: '', serial: '' };
        }

        function aplicarCodigoEscaneado(rawValue, targetInput) {
            const parsed = parseScannedValue(rawValue);
            const serialValue = parsed.serial || rawValue;
            const modelValue = parsed.model || '';

            if (targetInput) {
                const form = targetInput.closest('form');
                const serialField = form ? form.querySelector('[name="serial"]') : null;
                const modelField = form ? form.querySelector('[name="modelo"]') : null;

                if (parsed.serial && serialField) {
                    serialField.value = parsed.serial;
                } else if (targetInput) {
                    targetInput.value = serialValue;
                }

                if (parsed.model && modelField) {
                    modelField.value = parsed.model;
                }

                if (parsed.serial || parsed.model) {
                    const detalle = [parsed.model ? `modelo: ${parsed.model}` : null, parsed.serial ? `SN: ${parsed.serial}` : null].filter(Boolean).join(' · ');
                    showBarcodeResult(`Código leído: <strong>${escapeHtml(detalle)}</strong>`, false);
                }
            }

            return serialValue;
        }

        function showBarcodeResult(message, isError = false) {
            barcodeResult.innerHTML = `<div class="barcode-state ${isError ? 'error' : 'ok'}">${message}</div>`;
        }

        function buildMatchMarkup(match) {
            if (!match) {
                return '<div class="barcode-state error">No se encontró ningún equipo con ese código.</div>';
            }

            return `
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

        function completarFormularioEquipo(match) {
            const form = document.getElementById('stockEquipmentForm');
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
                const tipo = String(match.tipo || '');
                const marca = String(match.marca || '');
                const modelo = String(match.modelo || '');
                const serial = String(match.serial || '');
                const condicion = String(match.condicion || 'nuevo');
                const ubicacion = String(match.ubicacion || 'Tienda');
                const observaciones = String(match.observaciones || '');

                setValue('tipo', tipo);
                setValue('marca', marca);
                setValue('modelo', modelo);
                setValue('serial', serial);
                setValue('condicion', condicion);
                setValue('ubicacion', ubicacion);
                setValue('observaciones', observaciones);
            } else {
                setValue('serial', barcodeInput ? barcodeInput.value.trim() : '');
            }
        }

        function buscarEquipoPorCodigo(codigo) {
            const rawCode = String(codigo ?? '').trim();
            if (!rawCode) {
                showBarcodeResult('Escribe o escanea un código para buscar un equipo.', true);
                return;
            }

            const parsed = parseScannedValue(rawCode);
            const code = (parsed.serial || parsed.model || rawCode).trim();
            const normalized = code.toLowerCase();
            const match = inventory.find((item) => {
                const serial = String(item.serial ?? '').trim();
                return serial.toLowerCase() === normalized || serial.toLowerCase().includes(normalized) || String(item.id) === code;
            });

            if (!match) {
                showBarcodeResult(`No se encontró ningún equipo con el código <strong>${escapeHtml(code)}</strong>.`, true);
                completarFormularioEquipo(null);
                return;
            }

            if (dispatchSelect) {
                dispatchSelect.value = String(match.id);
            }

            barcodeResult.innerHTML = buildMatchMarkup(match);
            completarFormularioEquipo(match);
            if (barcodeInput) {
                barcodeInput.value = code;
            }
        }

        let cameraStream = null;
        let barcodeScannerTimer = null;

        function detenerCamara() {
            if (cameraStream) {
                cameraStream.getTracks().forEach((track) => track.stop());
                cameraStream = null;
            }

            if (barcodeVideo) {
                barcodeVideo.srcObject = null;
                barcodeVideo.style.display = 'none';
            }

            if (window.Quagga && Quagga._state && Quagga._state.running) {
                Quagga.stop();
            }
        }

        async function iniciarEscaneoCamara() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                showBarcodeResult('Este navegador no permite acceder a la cámara. Puedes escribir el código manualmente o abrir la página con la IP del equipo y permitir cámara.', true);
                return;
            }

            if ('BarcodeDetector' in window) {
                try {
                    if (cameraStream) {
                        cameraStream.getTracks().forEach((track) => track.stop());
                    }

                    cameraStream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'environment' },
                        audio: false,
                    });

                    if (barcodeVideo) {
                        barcodeVideo.srcObject = cameraStream;
                        barcodeVideo.style.display = 'block';
                    }

                    const detector = new BarcodeDetector({ formats: ['code_128', 'ean_13', 'ean_8', 'code_39', 'upc_a', 'upc_e', 'codabar'] });
                    const lectura = async () => {
                        if (!barcodeVideo || !barcodeVideo.videoWidth || !barcodeVideo.videoHeight) {
                            return;
                        }

                        try {
                            const barcodes = await detector.detect(barcodeVideo);
                            if (barcodes && barcodes.length > 0) {
                                const scanned = barcodes[0].rawValue;
                                if (scanned) {
                                    buscarEquipoPorCodigo(scanned);
                                    detenerCamara();
                                    return;
                                }
                            }
                        } catch (error) {
                            console.warn('BarcodeDetector detect failed:', error);
                        }

                        barcodeScannerTimer = setTimeout(lectura, 350);
                    };

                    clearTimeout(barcodeScannerTimer);
                    lectura();
                    showBarcodeResult('Cámara activa. Apunta al código de barras para escanearlo.', false);
                    return;
                } catch (error) {
                    console.warn('Error BarcodeDetector:', error);
                }
            }

            if (window.Quagga) {
                if (barcodeVideo) {
                    barcodeVideo.style.display = 'block';
                    barcodeVideo.setAttribute('playsinline', 'true');
                    barcodeVideo.setAttribute('muted', 'true');
                }

                Quagga.offDetected();
                Quagga.stop();
                Quagga.init({
                    inputStream: {
                        name: 'Live',
                        type: 'LiveStream',
                        target: barcodeVideo,
                        constraints: { facingMode: 'environment' },
                    },
                    decoder: {
                        readers: ['code_128_reader', 'ean_reader', 'ean_8_reader', 'code_39_reader', 'upc_reader', 'codabar_reader'],
                    },
                    locate: true,
                }, function (err) {
                    if (err) {
                        showBarcodeResult('No se pudo iniciar el escáner de la cámara. Abre la página por la IP del equipo, acepta los permisos de cámara y vuelve a intentarlo. Si no, escribe el código manualmente.', true);
                        return;
                    }

                    Quagga.start();
                    showBarcodeResult('Cámara activa. Apunta al código de barras para escanearlo.', false);
                });

                Quagga.onDetected((result) => {
                    const scanned = result && result.codeResult ? result.codeResult.code : null;
                    if (scanned) {
                        buscarEquipoPorCodigo(scanned);
                        Quagga.stop();
                        detenerCamara();
                    }
                });
                return;
            }

            showBarcodeResult('Tu navegador no admite escaneo con cámara en esta página. Usa la IP local del equipo y permite la cámara, o escribe el código manualmente.', true);
        }

        if (barcodeInput) {
            barcodeInput.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    buscarEquipoPorCodigo(barcodeInput.value);
                }
            });

            barcodeSearchButton?.addEventListener('click', () => {
                buscarEquipoPorCodigo(barcodeInput.value);
            });

            barcodeCameraButton?.addEventListener('click', () => {
                iniciarEscaneoCamara();
            });
        }

        function activarEscaneoDirecto(targetInputId) {
            const targetInput = document.getElementById(targetInputId);
            if (!targetInput) {
                return;
            }

            const startDirectScan = async () => {
                const permissionMessage = 'La cámara está bloqueada por el navegador. Abre la página con la IP del equipo (por ejemplo http://192.168.100.18/compuser), acepta el permiso de cámara y recarga la página. Si no puedes, escribe el serial manualmente.';

                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    const msg = 'Tu navegador no tiene acceso a la cámara. Usa la IP del equipo y acepta el permiso o escribe el serial manualmente.';
                    showBarcodeResult(msg, true);
                    alert(permissionMessage);
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
                        const existing = document.getElementById('directScannerHolder');
                        if (existing) {
                            existing.replaceWith(container);
                        } else {
                            const formCard = document.querySelector('.card');
                            if (formCard) {
                                formCard.appendChild(container);
                            }
                        }
                        container.id = 'directScannerHolder';

                        const detector = new BarcodeDetector({ formats: ['code_128', 'ean_13', 'ean_8', 'code_39', 'upc_a', 'upc_e', 'codabar'] });
                        const read = async () => {
                            try {
                                const barcodes = await detector.detect(preview);
                                if (barcodes && barcodes.length > 0) {
                                    const value = barcodes[0].rawValue;
                                    if (value) {
                                        const parsedValue = aplicarCodigoEscaneado(value, targetInput);
                                        targetInput.value = parsedValue;
                                        stream.getTracks().forEach((track) => track.stop());
                                        container.remove();
                                        return;
                                    }
                                }
                            } catch (error) {
                                console.warn('Direct scan failed:', error);
                            }
                            setTimeout(read, 350);
                        };
                        read();
                        return;
                    } catch (error) {
                        console.warn('Direct camera error:', error);
                        showBarcodeResult(permissionMessage, true);
                        alert(permissionMessage);
                    }
                }

                const fallback = 'La cámara no está disponible en este navegador. Usa la IP del equipo y acepta el permiso, o escribe el serial manualmente.';
                showBarcodeResult(fallback, true);
                alert(permissionMessage);
            };

            startDirectScan();
        }

        async function leerCodigoDesdeArchivo(file, targetInput) {
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
                            showBarcodeResult(`Código leído desde la foto: <strong>${escapeHtml(value)}</strong>`, false);
                            return;
                        }
                    } catch (error) {
                        console.warn('ZXing image decode failed:', error);
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
                            showBarcodeResult(`Código leído desde la foto: <strong>${escapeHtml(value)}</strong>`, false);
                            return;
                        }
                    }
                }

                if (window.Quagga) {
                    const url = URL.createObjectURL(file);
                    const resultado = await new Promise((resolve, reject) => {
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
                            reject(new Error('No se encontró un código en la imagen.'));
                        });
                    }).catch(() => null);

                    if (resultado) {
                        targetInput.value = resultado;
                        showBarcodeResult(`Código leído desde la foto: <strong>${escapeHtml(resultado)}</strong>`, false);
                        return;
                    }
                }

                alert(mensajeManual);
            } catch (error) {
                console.warn('File scan failed:', error);
                alert(mensajeManual);
            }
        }

        serialButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const targetId = button.getAttribute('data-scan-target');
                if (targetId) {
                    activarEscaneoDirecto(targetId);
                }
            });
        });

        serialFileTriggers.forEach((button) => {
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

        serialFileInputs.forEach((fileInput) => {
            fileInput.addEventListener('change', async (event) => {
                const targetId = fileInput.getAttribute('data-scan-file-target');
                const targetInput = document.getElementById(targetId);
                const file = event.target.files && event.target.files[0];
                if (file && targetInput) {
                    await leerCodigoDesdeArchivo(file, targetInput);
                }
                fileInput.value = '';
            });
        });

        const tipoOrden = document.getElementById('tipoOrden');
        const clienteRegistrado = document.getElementById('clienteRegistrado');
        const sections = document.querySelectorAll('[data-order-section]');
        const fields = document.querySelectorAll('[data-order-section] input, [data-order-section] textarea');

        function actualizarFormularioOrden() {
            const necesitaCliente = true;
            const necesitaUbicacion = true;

            sections.forEach((section) => {
                const name = section.dataset.orderSection;
                section.hidden = (name === 'cliente' && !necesitaCliente) || (name === 'ubicacion' && !necesitaUbicacion);
            });

            fields.forEach((field) => {
                const section = field.closest('[data-order-section]');
                field.required = !section.hidden && (field.name === 'cliente_nombre' || field.name === 'cliente_numero' || field.name === 'calle');
            });
        }

        if (tipoOrden) {
            tipoOrden.addEventListener('change', actualizarFormularioOrden);
            actualizarFormularioOrden();
        }

        clienteRegistrado?.addEventListener('change', () => {
            const option = clienteRegistrado.selectedOptions[0];
            const form = clienteRegistrado.closest('form');
            if (!option || !form) {
                return;
            }

            for (const [fieldName, dataName] of Object.entries({
                cliente_nombre: 'nombre',
                cliente_numero: 'numero',
                calle: 'calle',
                numero_exterior: 'numeroExterior',
                colonia: 'colonia',
                referencias: 'referencias',
                ubicacion_url: 'ubicacionUrl',
            })) {
                const field = form.querySelector(`[name="${fieldName}"]`);
                if (field) {
                    field.value = option.value === '0' ? '' : (option.dataset[dataName] || '');
                }
            }
        });

        const buscarCliente = document.getElementById('buscarCliente');
        const filasClientes = Array.from(document.querySelectorAll('#tablaClientes tr[data-client-search]'));
        const sinClientesFiltrados = document.getElementById('sinClientesFiltrados');
        buscarCliente?.addEventListener('input', () => {
            const termino = buscarCliente.value.trim().toLocaleLowerCase();
            let visibles = 0;
            filasClientes.forEach((fila) => {
                const coincide = fila.dataset.clientSearch.includes(termino);
                fila.hidden = !coincide;
                visibles += coincide ? 1 : 0;
            });
            if (sinClientesFiltrados) {
                sinClientesFiltrados.hidden = visibles > 0 || filasClientes.length === 0;
            }
        });

        const filtroOrdenes = document.getElementById('filtroOrdenes');
        if (filtroOrdenes) {
            filtroOrdenes.addEventListener('change', () => {
                document.querySelectorAll('.order-item').forEach((order) => {
                    order.hidden = filtroOrdenes.value !== 'todos' && order.dataset.orderType !== filtroOrdenes.value;
                });
            });
        }

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
