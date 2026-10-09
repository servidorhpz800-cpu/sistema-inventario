<?php
require_once dirname(__DIR__, 2) . '/includes/session.php';
iniciarSesionPersistente();
require dirname(__DIR__, 2) . '/config/database.php';
require dirname(__DIR__, 2) . '/includes/functions.php';
requireLogin(['tecnico', 'admin']);

$mensaje = null;
$tipoMensaje = 'success';
$esAdmin = $_SESSION['rol'] === 'admin';
$tecnicoIdSesion = (int)$_SESSION['usuario_id'];

if (!$esAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_equipo_tecnico'])) {
    $movimientoId = (int)($_POST['movimiento_id'] ?? 0);
    $estadoEquipo = $_POST['estado_equipo_tecnico'] ?? 'portado';
    $observaciones = trim($_POST['equipo_observaciones_actualizacion'] ?? '');
    $estadosPermitidos = ['portado', 'utilizado', 'no_utilizado', 'falla', 'devuelto'];

    if (in_array($estadoEquipo, $estadosPermitidos, true)) {
        $movimiento = $pdo->prepare('SELECT equipo_id FROM equipos_tecnico WHERE id = :id AND tecnico_id = :tecnico_id LIMIT 1');
        $movimiento->execute(['id' => $movimientoId, 'tecnico_id' => $tecnicoIdSesion]);
        $movimiento = $movimiento->fetch();

        if ($movimiento) {
            $pdo->prepare('UPDATE equipos_tecnico SET estado = :estado, observaciones = :observaciones WHERE id = :id AND tecnico_id = :tecnico_id')->execute([
                'estado' => $estadoEquipo,
                'observaciones' => $observaciones,
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
    }
}

if (!$esAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_reporte'])) {
    $ordenId = (int)($_POST['orden_id'] ?? 0);
    $ordenId = $ordenId > 0 ? $ordenId : null;
    $movimientoId = (int)($_POST['movimiento_reporte_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $ciudadReporte = trim($_POST['ciudad'] ?? '');
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
        $extensiones = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        for ($indice = 0; $indice < $cantidadFotos; $indice++) {
            $errorFoto = is_array($subidaFotos['error']) ? $subidaFotos['error'][$indice] : $subidaFotos['error'];
            if ($errorFoto === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($errorFoto !== UPLOAD_ERR_OK) {
                $mensaje = in_array($errorFoto, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                    ? 'La imagen supera el límite de carga configurado en el servidor.'
                    : 'No se pudo recibir una imagen. Inténtalo de nuevo.';
                $tipoMensaje = 'danger';
                break;
            }
            $tmpFoto = is_array($subidaFotos['tmp_name']) ? $subidaFotos['tmp_name'][$indice] : $subidaFotos['tmp_name'];
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpFoto);
            if (!isset($extensiones[$mime])) {
                $mensaje = 'Las evidencias deben ser JPG, PNG o WEBP.';
                $tipoMensaje = 'danger';
                break;
            }
            $fotosTemporales[] = ['tmp_name' => $tmpFoto, 'extension' => $extensiones[$mime]];
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

    if ($titulo !== '' && $tipoMensaje === 'success') {
        try {
            $pdo->beginTransaction();

                if ($ordenId !== null) {
                    $ordenEquipo = $pdo->prepare('SELECT equipo_id, cliente_id, cliente_nombre, cliente_numero, ciudad, tipo_orden FROM ordenes WHERE id = :id AND tecnico_id = :tecnico_id LIMIT 1');
                    $ordenEquipo->execute(['id' => $ordenId, 'tecnico_id' => $tecnicoIdSesion]);
                    $datosOrden = $ordenEquipo->fetch();
                    if (!$datosOrden) {
                        throw new RuntimeException('La orden no pertenece al técnico actual.');
                    }
                    if ($movimientoReporte && !empty($movimientoReporte['orden_id']) && (int)$movimientoReporte['orden_id'] !== $ordenId) {
                        throw new RuntimeException('El equipo seleccionado pertenece a otra orden.');
                    }
                    $equipoDeOrden = $datosOrden['equipo_id'];
                    if ($datosOrden['tipo_orden'] !== 'instalacion') {
                        $clienteNombreReporte = trim((string)$datosOrden['cliente_nombre']) ?: $clienteNombreReporte;
                        $clienteNumeroReporte = trim((string)$datosOrden['cliente_numero']) ?: $clienteNumeroReporte;
                    }
                    $ciudadReporte = $ciudadReporte ?: trim((string)$datosOrden['ciudad']);
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
            if ($ciudadReporte === '') {
                throw new RuntimeException('Debes indicar el pueblo o ciudad del servicio.');
            }

            if ($ordenId !== null && $datosOrden['tipo_orden'] === 'instalacion') {
                $ordenClienteUpdate = $pdo->prepare('UPDATE ordenes SET cliente_nombre = :cliente_nombre, cliente_numero = :cliente_numero, ciudad = :ciudad WHERE id = :id AND tecnico_id = :tecnico_id');
                $ordenClienteUpdate->execute([
                    'cliente_nombre' => $clienteNombreReporte,
                    'cliente_numero' => $clienteNumeroReporte,
                    'ciudad' => $ciudadReporte,
                    'id' => $ordenId,
                    'tecnico_id' => $tecnicoIdSesion,
                ]);
                if (!empty($datosOrden['cliente_id'])) {
                    $pdo->prepare('UPDATE clientes SET ciudad = :ciudad WHERE id = :id')->execute([
                        'ciudad' => $ciudadReporte,
                        'id' => (int)$datosOrden['cliente_id'],
                    ]);
                }

                $movimientosClienteUpdate = $pdo->prepare('UPDATE equipos_tecnico SET cliente_nombre = :cliente_nombre, cliente_numero = :cliente_numero WHERE orden_id = :orden_id AND tecnico_id = :tecnico_id');
                $movimientosClienteUpdate->execute([
                    'cliente_nombre' => $clienteNombreReporte,
                    'cliente_numero' => $clienteNumeroReporte,
                    'orden_id' => $ordenId,
                    'tecnico_id' => $tecnicoIdSesion,
                ]);
            }

            $equipoIdReporte = $movimientoReporte
                ? (int)$movimientoReporte['equipo_id']
                : ($equipoDeOrden !== null ? (int)$equipoDeOrden : null);

            $directorioFotos = dirname(__DIR__, 2) . '/uploads/reportes';
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

            $stmt = $pdo->prepare('INSERT INTO reportes (tecnico_id, orden_id, equipo_id, movimiento_id, cliente_nombre, cliente_numero, ciudad, titulo, actividad_realizada, materiales, resultado, descripcion, latitud, longitud, foto_url, estado) VALUES (:tecnico_id, :orden_id, :equipo_id, :movimiento_id, :cliente_nombre, :cliente_numero, :ciudad, :titulo, :actividad_realizada, :materiales, :resultado, :descripcion, :latitud, :longitud, :foto_url, :estado)');
            $stmt->execute([
                'tecnico_id' => $tecnicoIdSesion,
                'orden_id' => $ordenId,
                'equipo_id' => $equipoIdReporte,
                'movimiento_id' => $movimientoReporte ? $movimientoId : null,
                'cliente_nombre' => $clienteNombreReporte,
                'cliente_numero' => $clienteNumeroReporte,
                'ciudad' => $ciudadReporte,
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
                if (is_file(dirname(__DIR__, 2) . '/' . $fotoGuardada)) {
                    unlink(dirname(__DIR__, 2) . '/' . $fotoGuardada);
                }
            }
            error_log('No se pudo guardar el reporte técnico: ' . $error->getMessage());
            $mensaje = 'No se pudo guardar el reporte. No se guardaron cambios; inténtalo de nuevo.';
            $tipoMensaje = 'danger';
        }
    } elseif ($tipoMensaje === 'success') {
        $mensaje = 'Debes completar el título del reporte.';
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