<?php
require_once __DIR__ . '/includes/session.php';
iniciarSesionPersistente();
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';
requireLogin(['tecnico', 'admin']);

$mensaje = null;
$tipoMensaje = 'success';
$esAdmin = $_SESSION['rol'] === 'admin';
$tecnicoIdSesion = (int)$_SESSION['usuario_id'];

if (!$esAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_equipo_tecnico'])) {
    $movimientoId = (int)($_POST['movimiento_id'] ?? 0);
    $estadoEquipo = $_POST['estado_equipo_tecnico'] ?? 'portado';
    $observaciones = trim($_POST['equipo_observaciones_actualizacion'] ?? '');
    $clienteNombreMovimiento = trim($_POST['movimiento_cliente_nombre'] ?? '');
    $clienteNumeroMovimiento = trim($_POST['movimiento_cliente_numero'] ?? '');
    $estadosPermitidos = ['portado', 'utilizado', 'no_utilizado', 'falla', 'devuelto'];

    if (in_array($estadoEquipo, $estadosPermitidos, true) && $clienteNombreMovimiento !== '' && $clienteNumeroMovimiento !== '') {
        $movimiento = $pdo->prepare('SELECT equipo_id FROM equipos_tecnico WHERE id = :id AND tecnico_id = :tecnico_id LIMIT 1');
        $movimiento->execute(['id' => $movimientoId, 'tecnico_id' => $tecnicoIdSesion]);
        $movimiento = $movimiento->fetch();

        if ($movimiento) {
            $pdo->prepare('UPDATE equipos_tecnico SET estado = :estado, observaciones = :observaciones, cliente_nombre = :cliente_nombre, cliente_numero = :cliente_numero WHERE id = :id AND tecnico_id = :tecnico_id')->execute([
                'estado' => $estadoEquipo,
                'observaciones' => $observaciones,
                'cliente_nombre' => $clienteNombreMovimiento,
                'cliente_numero' => $clienteNumeroMovimiento,
                'id' => $movimientoId,
                'tecnico_id' => $tecnicoIdSesion,
            ]);

            $inventarioEstado = 'asignado';
            $condicion = null;
            $ubicacion = $_SESSION['nombre'];
            if ($estadoEquipo === 'no_utilizado' || $estadoEquipo === 'devuelto') {
                $inventarioEstado = 'disponible';
                $condicion = 'recogido';
                $ubicacion = 'Tienda';
            } elseif ($estadoEquipo === 'utilizado') {
                $inventarioEstado = 'retirado';
                $ubicacion = 'Cliente';
            } elseif ($estadoEquipo === 'falla') {
                $inventarioEstado = 'en_reparacion';
                $condicion = 'danado';
                $ubicacion = 'Revisión técnica';
            }

            if ($condicion) {
                $update = $pdo->prepare('UPDATE equipos SET estado = :estado, condicion = :condicion, ubicacion = :ubicacion WHERE id = :id');
                $update->execute(['estado' => $inventarioEstado, 'condicion' => $condicion, 'ubicacion' => $ubicacion, 'id' => (int)$movimiento['equipo_id']]);
            } else {
                $update = $pdo->prepare('UPDATE equipos SET estado = :estado, ubicacion = :ubicacion WHERE id = :id');
                $update->execute(['estado' => $inventarioEstado, 'ubicacion' => $ubicacion, 'id' => (int)$movimiento['equipo_id']]);
            }
            registrarActividad($pdo, 'Técnico', 'Actualizar equipo portado', 'movimiento_equipo', $movimientoId, "Movimiento actualizado a {$estadoEquipo}; inventario sincronizado.");
            $mensaje = 'Estado del equipo actualizado e inventario sincronizado.';
        }
    } elseif ($clienteNombreMovimiento === '' || $clienteNumeroMovimiento === '') {
        $mensaje = 'Indica el nombre y número del cliente para actualizar este movimiento.';
        $tipoMensaje = 'danger';
    }
}

if (!$esAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_reporte'])) {
    $ordenId = (int)($_POST['orden_id'] ?? 0);
    $ordenId = $ordenId > 0 ? $ordenId : null;
    $movimientoId = (int)($_POST['movimiento_reporte_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $estadoEquipo = $_POST['estado_equipo'] ?? 'normal';
    $actividad = trim($_POST['actividad_realizada'] ?? '');
    $materiales = trim($_POST['materiales'] ?? '');
    $resultado = trim($_POST['resultado'] ?? '');
    $clienteNombreReporte = trim($_POST['cliente_nombre'] ?? '');
    $clienteNumeroReporte = trim($_POST['cliente_numero'] ?? '');
    $latitud = filter_var($_POST['latitud'] ?? null, FILTER_VALIDATE_FLOAT);
    $longitud = filter_var($_POST['longitud'] ?? null, FILTER_VALIDATE_FLOAT);
    $fotoUrl = null;
    $fotosTemporales = [];
    $fotosGuardadas = [];

    $subidaFotos = $_FILES['foto_reporte'] ?? null;
    if ($subidaFotos !== null) {
        $cantidadFotos = is_array($subidaFotos['name']) ? count($subidaFotos['name']) : 1;
        if ($cantidadFotos > 10) {
            $mensaje = 'Puedes adjuntar hasta 10 imágenes por reporte.';
            $tipoMensaje = 'danger';
        } else {
            $extensiones = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            for ($indice = 0; $indice < $cantidadFotos; $indice++) {
                $errorFoto = is_array($subidaFotos['error']) ? $subidaFotos['error'][$indice] : $subidaFotos['error'];
                if ($errorFoto === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $tmpFoto = is_array($subidaFotos['tmp_name']) ? $subidaFotos['tmp_name'][$indice] : $subidaFotos['tmp_name'];
                $tamanoFoto = is_array($subidaFotos['size']) ? $subidaFotos['size'][$indice] : $subidaFotos['size'];
                if ($errorFoto !== UPLOAD_ERR_OK || $tamanoFoto > 8 * 1024 * 1024) {
                    $mensaje = 'Cada imagen debe pesar menos de 8 MB.';
                    $tipoMensaje = 'danger';
                    break;
                }
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpFoto);
                if (!isset($extensiones[$mime])) {
                    $mensaje = 'Las evidencias deben ser JPG, PNG o WEBP.';
                    $tipoMensaje = 'danger';
                    break;
                }
                $fotosTemporales[] = ['tmp_name' => $tmpFoto, 'extension' => $extensiones[$mime]];
            }
        }
    }

    $movimientoReporte = null;
    if ($movimientoId > 0) {
        $movimientoStmt = $pdo->prepare('SELECT id, equipo_id, orden_id, cliente_nombre, cliente_numero FROM equipos_tecnico WHERE id = :id AND tecnico_id = :tecnico_id LIMIT 1');
        $movimientoStmt->execute(['id' => $movimientoId, 'tecnico_id' => $tecnicoIdSesion]);
        $movimientoReporte = $movimientoStmt->fetch();
        if (!$movimientoReporte) {
            $mensaje = 'El módem seleccionado no pertenece a tu despacho.';
            $tipoMensaje = 'danger';
        } elseif ($ordenId === null && !empty($movimientoReporte['orden_id'])) {
            $ordenId = (int)$movimientoReporte['orden_id'];
        }
    }

    if ($titulo !== '' && $descripcion !== '' && $tipoMensaje === 'success') {
        try {
            $pdo->beginTransaction();

                if ($ordenId !== null) {
                    $ordenEquipo = $pdo->prepare('SELECT equipo_id, cliente_nombre, cliente_numero FROM ordenes WHERE id = :id AND tecnico_id = :tecnico_id LIMIT 1');
                    $ordenEquipo->execute(['id' => $ordenId, 'tecnico_id' => $tecnicoIdSesion]);
                    $datosOrden = $ordenEquipo->fetch();
                    if (!$datosOrden) {
                        throw new RuntimeException('La orden no pertenece al técnico actual.');
                    }
                    if ($movimientoReporte && !empty($movimientoReporte['orden_id']) && (int)$movimientoReporte['orden_id'] !== $ordenId) {
                        throw new RuntimeException('El equipo seleccionado pertenece a otra orden.');
                    }
                    $equipoDeOrden = $datosOrden['equipo_id'];
                    $clienteNombreReporte = trim((string)$datosOrden['cliente_nombre']) ?: $clienteNombreReporte;
                    $clienteNumeroReporte = trim((string)$datosOrden['cliente_numero']) ?: $clienteNumeroReporte;
                } else {
                    $equipoDeOrden = null;
                }

            if ($movimientoReporte && $ordenId === null) {
                $clienteNombreReporte = $clienteNombreReporte ?: trim((string)$movimientoReporte['cliente_nombre']);
                $clienteNumeroReporte = $clienteNumeroReporte ?: trim((string)$movimientoReporte['cliente_numero']);
            }
            if ($clienteNombreReporte === '' || $clienteNumeroReporte === '') {
                throw new RuntimeException('Debes indicar el nombre y número del cliente.');
            }

            $equipoIdReporte = $movimientoReporte
                ? (int)$movimientoReporte['equipo_id']
                : ($equipoDeOrden !== null ? (int)$equipoDeOrden : null);

            $directorioFotos = __DIR__ . '/uploads/reportes';
            if ($fotosTemporales && !is_dir($directorioFotos) && !mkdir($directorioFotos, 0755, true) && !is_dir($directorioFotos)) {
                throw new RuntimeException('No se pudo crear el directorio de evidencias.');
            }
            foreach ($fotosTemporales as $fotoTemporal) {
                $nombreFoto = bin2hex(random_bytes(16)) . '.' . $fotoTemporal['extension'];
                if (!move_uploaded_file($fotoTemporal['tmp_name'], $directorioFotos . '/' . $nombreFoto)) {
                    throw new RuntimeException('No se pudo guardar una de las evidencias.');
                }
                $fotosGuardadas[] = 'uploads/reportes/' . $nombreFoto;
            }
            $fotoUrl = $fotosGuardadas[0] ?? null;

            $stmt = $pdo->prepare('INSERT INTO reportes (tecnico_id, orden_id, equipo_id, movimiento_id, cliente_nombre, cliente_numero, titulo, actividad_realizada, materiales, resultado, descripcion, latitud, longitud, foto_url, estado) VALUES (:tecnico_id, :orden_id, :equipo_id, :movimiento_id, :cliente_nombre, :cliente_numero, :titulo, :actividad_realizada, :materiales, :resultado, :descripcion, :latitud, :longitud, :foto_url, :estado)');
            $stmt->execute([
                'tecnico_id' => $tecnicoIdSesion,
                'orden_id' => $ordenId,
                'equipo_id' => $equipoIdReporte,
                'movimiento_id' => $movimientoReporte ? $movimientoId : null,
                'cliente_nombre' => $clienteNombreReporte,
                'cliente_numero' => $clienteNumeroReporte,
                'titulo' => $titulo,
                'actividad_realizada' => $actividad,
                'materiales' => $materiales,
                'resultado' => $resultado,
                'descripcion' => $descripcion,
                'latitud' => $latitud !== false ? $latitud : null,
                'longitud' => $longitud !== false ? $longitud : null,
                'foto_url' => $fotoUrl,
                'estado' => $estadoEquipo,
            ]);
            $reporteId = (int)$pdo->lastInsertId();
            if ($fotosGuardadas) {
                $evidenciaStmt = $pdo->prepare('INSERT INTO reporte_evidencias (reporte_id, foto_url) VALUES (:reporte_id, :foto_url)');
                foreach ($fotosGuardadas as $fotoGuardada) {
                    $evidenciaStmt->execute(['reporte_id' => $reporteId, 'foto_url' => $fotoGuardada]);
                }
            }

            $equipoIdActualizado = $equipoIdReporte ?? 0;

            if ($movimientoReporte) {
                $estadoMovimiento = $estadoEquipo === 'normal' ? 'utilizado' : 'falla';
                $movimientoUpdate = $pdo->prepare('UPDATE equipos_tecnico SET estado = :estado, observaciones = :observaciones WHERE id = :id AND tecnico_id = :tecnico_id');
                $movimientoUpdate->execute([
                    'estado' => $estadoMovimiento,
                    'observaciones' => $descripcion,
                    'id' => $movimientoId,
                    'tecnico_id' => $tecnicoIdSesion,
                ]);
            }

            if ($equipoIdActualizado > 0) {
                if ($estadoEquipo === 'normal') {
                    $equipoUpdate = $pdo->prepare("UPDATE equipos SET condicion = 'medio_uso', estado = 'retirado', ubicacion = 'Cliente' WHERE id = :id");
                    $equipoUpdate->execute(['id' => $equipoIdActualizado]);
                } elseif ($estadoEquipo === 'no_serve') {
                    $equipoUpdate = $pdo->prepare("UPDATE equipos SET condicion = 'no_serve', estado = 'dado_baja', ubicacion = 'Baja técnica' WHERE id = :id");
                    $equipoUpdate->execute(['id' => $equipoIdActualizado]);
                } else {
                    $equipoUpdate = $pdo->prepare("UPDATE equipos SET condicion = 'danado', estado = 'en_reparacion', ubicacion = 'Revisión técnica' WHERE id = :id");
                    $equipoUpdate->execute(['id' => $equipoIdActualizado]);
                }
            }

            registrarActividad($pdo, 'Técnico', 'Enviar reporte', 'reporte', $reporteId, "Reporte '{$titulo}' enviado con estado {$estadoEquipo}.");
            $pdo->commit();
            $mensaje = 'Reporte enviado; el movimiento y el inventario se actualizaron con el resultado.';
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($fotosGuardadas as $fotoGuardada) {
                if (is_file(__DIR__ . '/' . $fotoGuardada)) {
                    unlink(__DIR__ . '/' . $fotoGuardada);
                }
            }
            error_log('No se pudo guardar el reporte técnico: ' . $error->getMessage());
            $mensaje = 'No se pudo guardar el reporte. No se guardaron cambios; inténtalo de nuevo.';
            $tipoMensaje = 'danger';
        }
    } elseif ($tipoMensaje === 'success') {
        $mensaje = 'Debe completar el título y la descripción del reporte.';
        $tipoMensaje = 'danger';
    }
}

if (!$esAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_orden'])) {
    $ordenId = (int)($_POST['orden_id'] ?? 0);
    $estado = $_POST['estado'] ?? 'completado';
    $pdo->prepare('UPDATE ordenes SET estado = :estado WHERE id = :id AND tecnico_id = :tecnico_id')->execute([
        'estado' => $estado,
        'id' => $ordenId,
        'tecnico_id' => (int)$_SESSION['usuario_id'],
    ]);
    registrarActividad($pdo, 'Técnico', 'Actualizar orden', 'orden', $ordenId, "Orden actualizada a estado {$estado}.");
    $mensaje = 'Estado de la orden actualizado.';
}

$filtroTecnico = $esAdmin ? null : $tecnicoIdSesion;
$ordenes = getOrdenes($pdo, $filtroTecnico);
$reportes = getReportes($pdo, $filtroTecnico);
$equiposTecnico = getEquiposTecnico($pdo, $filtroTecnico);
$movimientosTecnico = getMovimientosTecnico($pdo, $filtroTecnico);
$tecnico = $pdo->prepare('SELECT * FROM usuarios WHERE id = :id LIMIT 1');
$tecnico->execute(['id' => (int)$_SESSION['usuario_id']]);
$tecnico = $tecnico->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel técnico</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-shell">
        <header class="topbar">
            <div class="brand">Compuser | Técnico</div>
            <nav class="nav">
                <a href="admin.php">Admin</a>
                <a href="tienda.php">Tienda</a>
                <a class="active" href="tecnico.php">Técnicos</a>
                <a href="logout.php">Cerrar sesión</a>
            </nav>
            <div class="user-chip"><?php echo htmlspecialchars($_SESSION['nombre']); ?> / <?php echo htmlspecialchars($_SESSION['equipo'] ?: 'Sin equipo'); ?></div>
        </header>

        <div class="container">
            <section class="hero">
                <h1>Panel de operaciones técnicas</h1>
                <p><?php echo $esAdmin ? 'Consulta todas las órdenes, movimientos y reportes registrados por los técnicos.' : 'Consulta tus órdenes, realiza instalaciones o retiros, y comparte reportes del trabajo ejecutado.'; ?></p>
            </section>

            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo htmlspecialchars($tipoMensaje); ?>"><?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>

            <div class="content-grid">
                <div class="card">
                    <div class="orders-toolbar">
                        <h3>Órdenes asignadas</h3>
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
                    <?php if (empty($ordenes)): ?>
                        <p><?php echo $esAdmin ? 'No hay órdenes pendientes por el momento.' : 'No tienes órdenes pendientes por el momento.'; ?></p>
                    <?php else: ?>
                        <div class="order-list">
                            <?php foreach ($ordenes as $orden): ?>
                                <article class="order-item technician-order" data-order-type="<?php echo htmlspecialchars($orden['tipo_orden']); ?>">
                                    <div class="order-item-head">
                                        <div>
                                            <span class="eyebrow">Orden #<?php echo (int)$orden['id']; ?></span>
                                            <h4><?php echo htmlspecialchars(ucfirst($orden['tipo_orden'])); ?></h4>
                                        </div>
                                        <span class="badge <?php echo badgeClass($orden['estado']); ?>"><?php echo htmlspecialchars($orden['estado']); ?></span>
                                    </div>
                                    <div class="order-facts">
                                        <div><span>Equipo</span><?php echo htmlspecialchars($orden['tipo']); ?> · <?php echo htmlspecialchars($orden['marca'] ?: 'Sin marca'); ?></div>
                                        <div><span>Cliente</span><?php echo htmlspecialchars($orden['cliente_nombre'] ?: 'Sin nombre'); ?> · <?php echo htmlspecialchars($orden['cliente_numero'] ?: 'Sin teléfono'); ?></div>
                                        <div><span>Dirección</span><?php echo htmlspecialchars(trim(($orden['calle'] ?: '') . ' ' . ($orden['numero_exterior'] ?: '') . ', ' . ($orden['colonia'] ?: '')) ?: 'Sin domicilio'); ?></div>
                                        <div><span>IP asignada</span><?php echo htmlspecialchars($orden['ip_asignada'] ?: 'Sin IP'); ?></div>
                                    </div>
                                    <?php if (!empty($orden['referencias'])): ?>
                                        <p class="order-description"><strong>Referencias:</strong> <?php echo htmlspecialchars($orden['referencias']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($orden['ubicacion_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($orden['ubicacion_url']); ?>" target="_blank" rel="noopener" class="btn btn-secondary">Abrir ubicación</a>
                                    <?php endif; ?>
                                    <?php if (!empty($orden['descripcion'])): ?><p class="order-description"><strong>Solicitud:</strong> <?php echo htmlspecialchars($orden['descripcion']); ?></p><?php endif; ?>
                                    <?php if (!$esAdmin): ?>
                                    <form method="POST" class="order-status-form">
                                        <input type="hidden" name="orden_id" value="<?php echo (int)$orden['id']; ?>">
                                        <label>
                                            <span>Actualizar estado</span>
                                            <select name="estado">
                                                <option value="pendiente" <?php echo $orden['estado'] === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                                <option value="en_proceso" <?php echo $orden['estado'] === 'en_proceso' ? 'selected' : ''; ?>>En proceso</option>
                                                <option value="completado" <?php echo $orden['estado'] === 'completado' ? 'selected' : ''; ?>>Completado</option>
                                                <option value="cancelado" <?php echo $orden['estado'] === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                                                <option value="danado" <?php echo $orden['estado'] === 'danado' ? 'selected' : ''; ?>>Dañado</option>
                                            </select>
                                        </label>
                                        <button type="submit" name="actualizar_orden" class="btn btn-primary">Guardar estado</button>
                                    </form>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!$esAdmin): ?>
                <div class="card">
                    <h3>Subir reporte técnico</h3>
                    <form method="POST" enctype="multipart/form-data" id="reporteTecnicoForm">
                        <label>
                            <span>Orden relacionada</span>
                            <select name="orden_id">
                                <option value="0">Sin orden específica</option>
                                <?php foreach ($ordenes as $orden): ?>
                                    <option value="<?php echo (int)$orden['id']; ?>" data-cliente-nombre="<?php echo htmlspecialchars($orden['cliente_nombre'] ?? '', ENT_QUOTES); ?>" data-cliente-numero="<?php echo htmlspecialchars($orden['cliente_numero'] ?? '', ENT_QUOTES); ?>"><?php echo htmlspecialchars($orden['tipo']); ?> - <?php echo htmlspecialchars($orden['tipo_orden']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Módem/equipo utilizado o revisado (opcional)</span>
                            <select name="movimiento_reporte_id">
                                <option value="0">Sin equipo específico</option>
                                <?php foreach ($movimientosTecnico as $movimiento): ?>
                                    <option value="<?php echo (int)$movimiento['id']; ?>" data-cliente-nombre="<?php echo htmlspecialchars($movimiento['cliente_nombre'] ?? '', ENT_QUOTES); ?>" data-cliente-numero="<?php echo htmlspecialchars($movimiento['cliente_numero'] ?? '', ENT_QUOTES); ?>">
                                        <?php echo htmlspecialchars($movimiento['tipo']); ?> · <?php echo htmlspecialchars($movimiento['marca'] ?: 'Sin marca'); ?> · <?php echo htmlspecialchars($movimiento['serial'] ?: 'Sin serial'); ?> · <?php echo htmlspecialchars($movimiento['cliente_nombre'] ?: 'Sin cliente'); ?> · <?php echo htmlspecialchars($movimiento['estado']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                            <div class="form-grid">
                                <label>
                                    <span>Nombre del cliente</span>
                                    <input type="text" name="cliente_nombre" maxlength="150" required>
                                </label>
                                <label>
                                    <span>Número del cliente</span>
                                    <input type="tel" name="cliente_numero" maxlength="40" required>
                                </label>
                            </div>
                        <label>
                            <span>Título</span>
                            <input type="text" name="titulo" placeholder="Reporte de instalación / retiro / daño">
                        </label>
                        <label>
                            <span>Estado del equipo</span>
                            <select name="estado_equipo">
                                <option value="normal">Normal</option>
                                <option value="revision">El módem presenta problemas</option>
                                <option value="danado">Dañado</option>
                                <option value="no_serve">No sirve</option>
                            </select>
                        </label>
                        <label>
                            <span>Qué se realizó</span>
                            <textarea name="actividad_realizada" placeholder="Instalación, retiro, revisión de señal, cambio de modem..."></textarea>
                        </label>
                        <label>
                            <span>Materiales utilizados</span>
                            <textarea name="materiales" placeholder="Modem, conectores, cable, caja NAP, etc."></textarea>
                        </label>
                        <label>
                            <span>Resultado de la visita</span>
                            <textarea name="resultado" placeholder="Servicio funcionando, queda pendiente, equipo enviado a revisión..."></textarea>
                        </label>
                        <label>
                            <span>Detalle</span>
                            <textarea name="descripcion" placeholder="Describe la actividad realizada, observaciones, fallas y recomendaciones..."></textarea>
                        </label>
                        <label>
                            <span>Imágenes de lo realizado (máximo 10)</span>
                            <input type="file" name="foto_reporte[]" accept="image/jpeg,image/png,image/webp" capture="environment" multiple>
                        </label>
                        <input type="hidden" name="latitud" id="latitudReporte">
                        <input type="hidden" name="longitud" id="longitudReporte">
                        <button type="button" id="capturarUbicacion" class="btn btn-secondary">Capturar ubicación actual</button>
                        <button type="submit" name="guardar_reporte" class="btn btn-success">Enviar reporte</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!$esAdmin): ?>
            <div class="card">
                    <div class="card-heading">
                        <div>
                            <span class="eyebrow">Equipos despachados por Tienda</span>
                            <h3>Resultado del módem en campo</h3>
                        </div>
                        <span class="step-number">02</span>
                    </div>
                    <p class="form-note">Estos equipos ya fueron asignados por Tienda. Indica si se usaron, devolvieron o presentaron una falla.</p>
                    <form method="POST">
                        <label>
                            <span>Equipo registrado</span>
                            <select name="movimiento_id" required>
                                <option value="">Seleccione</option>
                                <?php foreach ($movimientosTecnico as $movimiento): ?>
                                    <option value="<?php echo (int)$movimiento['id']; ?>" data-cliente-nombre="<?php echo htmlspecialchars($movimiento['cliente_nombre'] ?? '', ENT_QUOTES); ?>" data-cliente-numero="<?php echo htmlspecialchars($movimiento['cliente_numero'] ?? '', ENT_QUOTES); ?>">
                                        <?php echo htmlspecialchars($movimiento['tipo']); ?> · Orden #<?php echo (int)$movimiento['orden_id']; ?> · <?php echo htmlspecialchars($movimiento['cliente_nombre'] ?: 'Sin cliente'); ?> · <?php echo htmlspecialchars($movimiento['estado']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Resultado</span>
                            <select name="estado_equipo_tecnico" required>
                                <option value="portado">Sigue portado</option>
                                <option value="utilizado">Utilizado en cliente</option>
                                <option value="no_utilizado">No utilizado, devolver a tienda</option>
                                <option value="falla">Presentó falla, enviar a revisión</option>
                                <option value="devuelto">Devuelto a tienda</option>
                            </select>
                        </label>
                        <div class="form-grid">
                            <label>
                                <span>Nombre del cliente</span>
                                <input type="text" name="movimiento_cliente_nombre" maxlength="150" required>
                            </label>
                            <label>
                                <span>Número del cliente</span>
                                <input type="tel" name="movimiento_cliente_numero" maxlength="40" required>
                            </label>
                        </div>
                        <label>
                            <span>Detalle de la situación</span>
                            <textarea name="equipo_observaciones_actualizacion" placeholder="Indica por qué no se utilizó o qué falla presentó."></textarea>
                        </label>
                        <button type="submit" name="actualizar_equipo_tecnico" class="btn btn-success">Guardar resultado</button>
                    </form>
            </div>
            <?php endif; ?>

            <div class="card equipment-history" style="margin-top:24px;">
                <div class="orders-toolbar">
                    <h3>Historial de equipos portados</h3>
                    <span class="badge info"><?php echo count($movimientosTecnico); ?> registros</span>
                </div>
                <div class="order-list">
                    <?php foreach ($movimientosTecnico as $movimiento): ?>
                        <article class="order-item">
                            <div class="order-item-head">
                                <div>
                                    <span class="eyebrow">Orden #<?php echo (int)$movimiento['orden_id']; ?></span>
                                    <h4><?php echo htmlspecialchars($movimiento['tipo']); ?><?php echo $movimiento['marca'] ? ' · ' . htmlspecialchars($movimiento['marca']) : ''; ?></h4>
                                </div>
                                <span class="badge <?php echo badgeClass($movimiento['estado']); ?>"><?php echo htmlspecialchars(str_replace('_', ' ', $movimiento['estado'])); ?></span>
                            </div>
                            <p class="order-description"><strong>Cliente:</strong> <?php echo htmlspecialchars($movimiento['cliente_nombre'] ?: 'Sin cliente'); ?> · <?php echo htmlspecialchars($movimiento['cliente_numero'] ?: 'Sin número'); ?></p>
                            <p class="order-description"><?php echo htmlspecialchars($movimiento['observaciones'] ?: 'Sin observaciones'); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card" style="margin-top:24px;">
                <h3>Historial de reportes</h3>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Estado</th>
                                <th>Actividad / resultado</th>
                                <th>Detalle</th>
                                <th>Evidencia</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportes as $reporte): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($reporte['titulo']); ?><br><small>Movimiento <?php echo $reporte['movimiento_id'] ? '#' . (int)$reporte['movimiento_id'] : 'sin despacho'; ?></small></td>
                                    <td><span class="badge <?php echo badgeClass($reporte['estado']); ?>"><?php echo htmlspecialchars($reporte['estado']); ?></span></td>
                                    <td>
                                        <strong>Cliente:</strong> <?php echo htmlspecialchars($reporte['cliente_nombre'] ?: 'Sin cliente'); ?> · <?php echo htmlspecialchars($reporte['cliente_numero'] ?: 'Sin número'); ?><br>
                                        <?php echo htmlspecialchars($reporte['actividad_realizada'] ?: 'Sin actividad detallada'); ?><br>
                                        <strong>Resultado:</strong> <?php echo htmlspecialchars($reporte['resultado'] ?: 'Sin resultado'); ?><br>
                                        <strong>Materiales:</strong> <?php echo htmlspecialchars($reporte['materiales'] ?: 'Ninguno'); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($reporte['descripcion']); ?></td>
                                    <td>
                                        <?php foreach ($reporte['fotos'] as $indiceFoto => $foto): ?><a href="<?php echo htmlspecialchars($foto); ?>" target="_blank" rel="noopener">Ver imagen <?php echo (int)($indiceFoto + 1); ?></a><br><?php endforeach; ?>
                                        <?php if ($reporte['latitud'] !== null && $reporte['longitud'] !== null): ?><a href="https://www.google.com/maps?q=<?php echo urlencode($reporte['latitud'] . ',' . $reporte['longitud']); ?>" target="_blank" rel="noopener">Ver ubicación</a><?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($reporte['created_at']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script>
        const filtroOrdenes = document.getElementById('filtroOrdenes');
        if (filtroOrdenes) {
            filtroOrdenes.addEventListener('change', () => {
                document.querySelectorAll('.order-item').forEach((order) => {
                    order.hidden = filtroOrdenes.value !== 'todos' && order.dataset.orderType !== filtroOrdenes.value;
                });
            });
        }
        const capturarUbicacion = document.getElementById('capturarUbicacion');
        const movimientoEstadoSelect = document.querySelector('select[name="movimiento_id"]');
        const movimientoClienteNombre = document.querySelector('input[name="movimiento_cliente_nombre"]');
        const movimientoClienteNumero = document.querySelector('input[name="movimiento_cliente_numero"]');
        movimientoEstadoSelect?.addEventListener('change', () => {
            const opcion = movimientoEstadoSelect.selectedOptions[0];
            movimientoClienteNombre.value = opcion?.dataset.clienteNombre || '';
            movimientoClienteNumero.value = opcion?.dataset.clienteNumero || '';
        });
        if (capturarUbicacion) {
            capturarUbicacion.addEventListener('click', () => {
                if (!navigator.geolocation) {
                    alert('Este navegador no permite capturar ubicación.');
                    return;
                }
                capturarUbicacion.disabled = true;
                navigator.geolocation.getCurrentPosition((position) => {
                    document.getElementById('latitudReporte').value = position.coords.latitude.toFixed(7);
                    document.getElementById('longitudReporte').value = position.coords.longitude.toFixed(7);
                    capturarUbicacion.textContent = 'Ubicación capturada';
                    capturarUbicacion.disabled = false;
                }, () => {
                    alert('No fue posible obtener la ubicación. Revisa el permiso del navegador.');
                    capturarUbicacion.disabled = false;
                }, { enableHighAccuracy: true, timeout: 10000 });
            });
        }
        const ordenReporteSelect = document.querySelector('#reporteTecnicoForm select[name="orden_id"]');
        const movimientoReporteSelect = document.querySelector('#reporteTecnicoForm select[name="movimiento_reporte_id"]');
        const clienteNombreReporte = document.querySelector('#reporteTecnicoForm input[name="cliente_nombre"]');
        const clienteNumeroReporte = document.querySelector('#reporteTecnicoForm input[name="cliente_numero"]');
        const completarClienteReporte = (select) => {
            const opcion = select?.selectedOptions[0];
            if (opcion && opcion.value !== '0') {
                clienteNombreReporte.value = opcion.dataset.clienteNombre || '';
                clienteNumeroReporte.value = opcion.dataset.clienteNumero || '';
            }
        };
        ordenReporteSelect?.addEventListener('change', () => completarClienteReporte(ordenReporteSelect));
        movimientoReporteSelect?.addEventListener('change', () => completarClienteReporte(movimientoReporteSelect));
    </script>
</body>
</html>
