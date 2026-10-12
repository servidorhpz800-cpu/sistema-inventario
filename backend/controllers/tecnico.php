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
        $tecnicosEquipo = getTecnicosMismoVehiculo($pdo, $tecnicoIdSesion);
        $marcadoresTecnicos = implode(', ', array_fill(0, count($tecnicosEquipo), '?'));
        $movimiento = $pdo->prepare("SELECT equipo_id FROM equipos_tecnico WHERE id = ? AND tecnico_id IN ({$marcadoresTecnicos}) LIMIT 1");
        $movimiento->execute(array_merge([$movimientoId], $tecnicosEquipo));
        $movimiento = $movimiento->fetch();

        if ($movimiento) {
            $pdo->prepare("UPDATE equipos_tecnico SET estado = ?, observaciones = ? WHERE id = ? AND tecnico_id IN ({$marcadoresTecnicos})")->execute(
                array_merge([$estadoEquipo, $observaciones, $movimientoId], $tecnicosEquipo)
            );

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
    $domicilioCalles = trim($_POST['domicilio_calles'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $ciudadReporte = trim($_POST['ciudad'] ?? '');
    $estadoEquipo = $_POST['estado_equipo'] ?? 'normal';
    $actividad = trim($_POST['actividad_realizada'] ?? '');
    $materiales = trim($_POST['materiales'] ?? '');
    $resultado = trim($_POST['resultado'] ?? '');
    $totalOriginal = trim((string)($_POST['total'] ?? ''));
    $saldoOriginal = trim((string)($_POST['saldo'] ?? ''));
    $nombreServicio = trim($_POST['nombre_servicio'] ?? '');
    $planCliente = trim($_POST['plan_cliente'] ?? '');
    $ipCliente = trim($_POST['ip_cliente'] ?? '');
    $total = null;
    $saldo = null;
    $clienteNombreReporte = trim($_POST['cliente_nombre'] ?? '');
    $clienteNumeroReporte = trim($_POST['cliente_numero'] ?? '');
    $latitudOriginal = trim((string)($_POST['latitud'] ?? ''));
    $longitudOriginal = trim((string)($_POST['longitud'] ?? ''));
    $latitud = $latitudOriginal !== '' ? filter_var($latitudOriginal, FILTER_VALIDATE_FLOAT) : null;
    $longitud = $longitudOriginal !== '' ? filter_var($longitudOriginal, FILTER_VALIDATE_FLOAT) : null;
    $ubicacionUrlReporte = null;
    if ($latitudOriginal !== '' || $longitudOriginal !== '') {
        if (
            $latitud === false
            || $longitud === false
            || $latitud === null
            || $longitud === null
            || $latitud < -90
            || $latitud > 90
            || $longitud < -180
            || $longitud > 180
        ) {
            $mensaje = 'Las coordenadas de ubicación no son válidas. Captura la ubicación de nuevo.';
            $tipoMensaje = 'danger';
        } else {
            $ubicacionUrlReporte = 'https://www.google.com/maps?q=' . rawurlencode($latitud . ',' . $longitud);
        }
    }
    foreach (['total' => $totalOriginal, 'saldo' => $saldoOriginal] as $campoMonto => $montoOriginal) {
        if ($montoOriginal === '') {
            continue;
        }
        if (preg_match('/\A\d{1,10}(?:\.\d{1,2})?\z/', $montoOriginal) !== 1) {
            $mensaje = 'El total y el saldo deben ser cantidades válidas, sin negativos y con máximo dos decimales.';
            $tipoMensaje = 'danger';
            break;
        }
        if ($campoMonto === 'total') {
            $total = $montoOriginal;
        } else {
            $saldo = $montoOriginal;
        }
    }
    if ($ipCliente !== '' && filter_var($ipCliente, FILTER_VALIDATE_IP) === false) {
        $mensaje = 'La dirección IP no es válida.';
        $tipoMensaje = 'danger';
    }
    $fotoUrl = null;
    $fotosTemporales = [];
    $fotosGuardadas = [];
    $directorioFotos = null;

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
    $tecnicosEquipoReporte = getTecnicosMismoVehiculo($pdo, $tecnicoIdSesion);
    $marcadoresTecnicosReporte = implode(', ', array_fill(0, count($tecnicosEquipoReporte), '?'));
    if ($movimientoId > 0) {
        $movimientoStmt = $pdo->prepare("SELECT id, equipo_id, orden_id, cliente_nombre, cliente_numero FROM equipos_tecnico WHERE id = ? AND tecnico_id IN ({$marcadoresTecnicosReporte}) LIMIT 1");
        $movimientoStmt->execute(array_merge([$movimientoId], $tecnicosEquipoReporte));
        $movimientoReporte = $movimientoStmt->fetch();
        if (!$movimientoReporte) {
            $mensaje = 'El módem seleccionado no pertenece a tu despacho.';
            $tipoMensaje = 'danger';
        } elseif ($ordenId === null && !empty($movimientoReporte['orden_id'])) {
            $ordenId = (int)$movimientoReporte['orden_id'];
        }
    }

    if ($domicilioCalles !== '' && $tipoMensaje === 'success') {
        try {
            $pdo->beginTransaction();
            $datosOrden = null;

                if ($ordenId !== null) {
                    $ordenEquipo = $pdo->prepare("SELECT equipo_id, cliente_id, cliente_nombre, cliente_numero, ciudad, tipo_orden FROM ordenes WHERE id = ? AND tecnico_id IN ({$marcadoresTecnicosReporte}) LIMIT 1");
                    $ordenEquipo->execute(array_merge([$ordenId], $tecnicosEquipoReporte));
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

            $clienteIdReporte = (int)($datosOrden['cliente_id'] ?? 0);
            if ($clienteIdReporte === 0) {
                $clientePorNumero = $pdo->prepare('SELECT id FROM clientes WHERE numero = :numero LIMIT 1');
                $clientePorNumero->execute(['numero' => $clienteNumeroReporte]);
                $clienteIdReporte = (int)$clientePorNumero->fetchColumn();
            }

            if ($ordenId !== null && $datosOrden['tipo_orden'] === 'instalacion') {
                $ordenClienteUpdate = $pdo->prepare("UPDATE ordenes SET cliente_nombre = ?, cliente_numero = ? WHERE id = ? AND tecnico_id IN ({$marcadoresTecnicosReporte})");
                $ordenClienteUpdate->execute(array_merge([$clienteNombreReporte, $clienteNumeroReporte, $ordenId], $tecnicosEquipoReporte));

                $movimientosClienteUpdate = $pdo->prepare("UPDATE equipos_tecnico SET cliente_nombre = ?, cliente_numero = ? WHERE orden_id = ? AND tecnico_id IN ({$marcadoresTecnicosReporte})");
                $movimientosClienteUpdate->execute(array_merge([$clienteNombreReporte, $clienteNumeroReporte, $ordenId], $tecnicosEquipoReporte));
            }

            if ($ordenId !== null) {
                $ordenUbicacionUpdate = $pdo->prepare("UPDATE ordenes SET ciudad = ?, ubicacion_url = COALESCE(?, ubicacion_url) WHERE id = ? AND tecnico_id IN ({$marcadoresTecnicosReporte})");
                $ordenUbicacionUpdate->execute(array_merge([$ciudadReporte, $ubicacionUrlReporte, $ordenId], $tecnicosEquipoReporte));
            }

            if ($clienteIdReporte > 0) {
                $clienteUbicacionUpdate = $pdo->prepare('UPDATE clientes SET ciudad = :ciudad, ubicacion_url = COALESCE(:ubicacion_url, ubicacion_url) WHERE id = :id');
                $clienteUbicacionUpdate->execute([
                    'ciudad' => $ciudadReporte,
                    'ubicacion_url' => $ubicacionUrlReporte,
                    'id' => $clienteIdReporte,
                ]);
            }

            $equipoIdReporte = $movimientoReporte
                ? (int)$movimientoReporte['equipo_id']
                : ($equipoDeOrden !== null ? (int)$equipoDeOrden : null);

            $directorioFotos = getReportesUploadDirectory();
            if ($fotosTemporales && !is_dir($directorioFotos) && !mkdir($directorioFotos, 0755, true) && !is_dir($directorioFotos)) {
                throw new RuntimeException('No se pudo crear el directorio de evidencias.');
            }
            foreach ($fotosTemporales as $fotoTemporal) {
                $nombreFoto = bin2hex(random_bytes(16)) . '.' . $fotoTemporal['extension'];
                if (!move_uploaded_file($fotoTemporal['tmp_name'], $directorioFotos . '/' . $nombreFoto)) {
                    throw new RuntimeException('No se pudo guardar una de las evidencias.');
                }
                $fotosGuardadas[] = $nombreFoto;
            }
            $fotoUrl = $fotosGuardadas[0] ?? null;

            $stmt = $pdo->prepare('INSERT INTO reportes (tecnico_id, orden_id, equipo_id, movimiento_id, cliente_nombre, cliente_numero, ciudad, domicilio_calles, total, saldo, nombre_servicio, plan_cliente, ip_cliente, actividad_realizada, materiales, resultado, descripcion, latitud, longitud, foto_url, estado) VALUES (:tecnico_id, :orden_id, :equipo_id, :movimiento_id, :cliente_nombre, :cliente_numero, :ciudad, :domicilio_calles, :total, :saldo, :nombre_servicio, :plan_cliente, :ip_cliente, :actividad_realizada, :materiales, :resultado, :descripcion, :latitud, :longitud, :foto_url, :estado)');
            $stmt->execute([
                'tecnico_id' => $tecnicoIdSesion,
                'orden_id' => $ordenId,
                'equipo_id' => $equipoIdReporte,
                'movimiento_id' => $movimientoReporte ? $movimientoId : null,
                'cliente_nombre' => $clienteNombreReporte,
                'cliente_numero' => $clienteNumeroReporte,
                'ciudad' => $ciudadReporte,
                'domicilio_calles' => $domicilioCalles,
                'total' => $total,
                'saldo' => $saldo,
                'nombre_servicio' => $nombreServicio !== '' ? $nombreServicio : null,
                'plan_cliente' => $planCliente !== '' ? $planCliente : null,
                'ip_cliente' => $ipCliente !== '' ? $ipCliente : null,
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
                $movimientoUpdate = $pdo->prepare("UPDATE equipos_tecnico SET estado = ?, observaciones = ? WHERE id = ? AND tecnico_id IN ({$marcadoresTecnicosReporte})");
                $movimientoUpdate->execute(array_merge([$estadoMovimiento, $descripcion, $movimientoId], $tecnicosEquipoReporte));
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

            registrarActividad($pdo, 'Técnico', 'Enviar reporte', 'reporte', $reporteId, "Reporte de domicilio '{$domicilioCalles}' enviado con estado {$estadoEquipo}.");
            $pdo->commit();
            $mensaje = 'Reporte enviado; el movimiento y el inventario se actualizaron con el resultado.';
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach (array_merge($fotosGuardadas, $firmaGuardada !== null ? [$firmaGuardada] : []) as $archivoGuardado) {
                $rutaFotoGuardada = $directorioFotos !== null
                    ? $directorioFotos . DIRECTORY_SEPARATOR . basename($archivoGuardado)
                    : null;
                if ($rutaFotoGuardada !== null && is_file($rutaFotoGuardada)) {
                    unlink($rutaFotoGuardada);
                }
            }
            error_log('No se pudo guardar el reporte técnico: ' . $error->getMessage());
            $mensaje = 'No se pudo guardar el reporte. No se guardaron cambios; inténtalo de nuevo.';
            $tipoMensaje = 'danger';
        }
    } elseif ($tipoMensaje === 'success') {
        $mensaje = 'Debes completar el domicilio o las calles del servicio.';
        $tipoMensaje = 'danger';
    }
}

if (!$esAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_orden'])) {
    $ordenId = (int)($_POST['orden_id'] ?? 0);
    $estado = (string)($_POST['estado'] ?? '');
    $motivoCancelacion = trim((string)($_POST['motivo_cancelacion'] ?? ''));
    $estadosPermitidos = ['pendiente', 'en_proceso', 'completado', 'cancelado', 'danado'];

    if (!in_array($estado, $estadosPermitidos, true)) {
        $mensaje = 'El estado seleccionado no es válido.';
        $tipoMensaje = 'danger';
    } elseif ($estado === 'cancelado' && $motivoCancelacion === '') {
        $mensaje = 'Escribe el motivo para poder cancelar la orden.';
        $tipoMensaje = 'danger';
    } elseif (preg_match_all('/./us', $motivoCancelacion, $motivoCaracteres) === false || count($motivoCaracteres[0]) > 2000) {
        $mensaje = 'El motivo de cancelación no puede exceder 2000 caracteres.';
        $tipoMensaje = 'danger';
    } else {
        $tecnicosEquipoOrden = getTecnicosMismoVehiculo($pdo, $tecnicoIdSesion);
        $marcadoresTecnicosOrden = implode(', ', array_fill(0, count($tecnicosEquipoOrden), '?'));
        $ordenStmt = $pdo->prepare("SELECT id FROM ordenes WHERE id = ? AND tecnico_id IN ({$marcadoresTecnicosOrden}) LIMIT 1");
        $ordenStmt->execute(array_merge([$ordenId], $tecnicosEquipoOrden));
        if (!$ordenStmt->fetchColumn()) {
            $mensaje = 'No se encontró la orden asignada.';
            $tipoMensaje = 'danger';
        } else {
            if ($estado === 'cancelado') {
                $pdo->prepare("UPDATE ordenes SET estado = ?, motivo_cancelacion = ? WHERE id = ? AND tecnico_id IN ({$marcadoresTecnicosOrden})")->execute(
                    array_merge([$estado, $motivoCancelacion, $ordenId], $tecnicosEquipoOrden)
                );
            } else {
                $pdo->prepare("UPDATE ordenes SET estado = ? WHERE id = ? AND tecnico_id IN ({$marcadoresTecnicosOrden})")->execute(
                    array_merge([$estado, $ordenId], $tecnicosEquipoOrden)
                );
            }
            $detalle = "Orden actualizada a estado {$estado}.";
            if ($estado === 'cancelado') {
                $detalle .= " Motivo: {$motivoCancelacion}";
            }
            registrarActividad($pdo, 'Técnico', 'Actualizar orden', 'orden', $ordenId, $detalle);
            $mensaje = 'Estado de la orden actualizado.';
        }
    }
}

manejarPostRedirectGet($mensaje, $tipoMensaje);

$filtroTecnico = $esAdmin ? null : $tecnicoIdSesion;
$ordenes = getOrdenes($pdo, $filtroTecnico);
$reportes = getReportes($pdo, $filtroTecnico);
$equiposTecnico = getEquiposTecnico($pdo, $filtroTecnico);
$movimientosTecnico = getMovimientosTecnico($pdo, $filtroTecnico);
$tecnico = $pdo->prepare('SELECT * FROM usuarios WHERE id = :id LIMIT 1');
$tecnico->execute(['id' => (int)$_SESSION['usuario_id']]);
$tecnico = $tecnico->fetch();
?>