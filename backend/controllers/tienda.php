<?php
require_once dirname(__DIR__, 2) . '/includes/session.php';
iniciarSesionPersistente();
require dirname(__DIR__, 2) . '/config/database.php';
require dirname(__DIR__, 2) . '/includes/functions.php';
requireLogin(['tienda', 'admin']);

$mensaje = null;
$tipoMensaje = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['importar_modems'])) {
    $archivo = $_FILES['archivo_modems'] ?? null;
    if (is_array($archivo) && in_array(($archivo['error'] ?? UPLOAD_ERR_OK), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        $mensaje = 'El archivo CSV supera el límite de carga permitido por el servidor.';
        $tipoMensaje = 'danger';
    } elseif (!is_array($archivo) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $mensaje = 'Selecciona un archivo CSV válido para importar.';
        $tipoMensaje = 'danger';
    } elseif ((int)$archivo['size'] > 5 * 1024 * 1024) {
        $mensaje = 'El archivo CSV no puede superar 5 MB.';
        $tipoMensaje = 'danger';
    } elseif (strtolower(pathinfo((string)$archivo['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $mensaje = 'El archivo debe tener formato CSV.';
        $tipoMensaje = 'danger';
    } else {
        $handle = fopen((string)$archivo['tmp_name'], 'rb');
        if ($handle === false) {
            $mensaje = 'No se pudo leer el archivo CSV.';
            $tipoMensaje = 'danger';
        } else {
            $importacionIniciada = false;
            try {
                $primeraLinea = fgets($handle);
                if ($primeraLinea === false) {
                    throw new InvalidArgumentException('El archivo CSV está vacío.');
                }

                $columnasComa = str_getcsv($primeraLinea, ',');
                $columnasPuntoComa = str_getcsv($primeraLinea, ';');
                $delimitador = count($columnasPuntoComa) > count($columnasComa) ? ';' : ',';
                $encabezados = str_getcsv($primeraLinea, $delimitador);
                if (isset($encabezados[0])) {
                    $encabezados[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$encabezados[0]);
                }

                $normalizarEncabezado = static function (string $encabezado): string {
                    $encabezado = strtolower(trim($encabezado));
                    $encabezado = strtr($encabezado, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
                    return preg_replace('/[^a-z0-9]/', '', $encabezado) ?? '';
                };
                $aliasColumnas = [
                    'tipo' => ['tipo', 'equipo', 'tipoequipo'],
                    'marca' => ['marca'],
                    'modelo' => ['modelo'],
                    'serial' => ['serial', 'sn', 'serie', 'numerodeserie', 'serialnumber'],
                    'condicion' => ['condicion', 'estadofisico'],
                    'ubicacion' => ['ubicacion', 'lugar'],
                    'observaciones' => ['observaciones', 'notas', 'comentarios'],
                ];
                $indices = [];
                foreach ($encabezados as $indice => $encabezado) {
                    $clave = $normalizarEncabezado((string)$encabezado);
                    foreach ($aliasColumnas as $campo => $alias) {
                        if (in_array($clave, $alias, true)) {
                            $indices[$campo] = $indice;
                            break;
                        }
                    }
                }
                if (!isset($indices['serial'])) {
                    throw new InvalidArgumentException('El CSV debe incluir una columna serial (o SN). Descarga la plantilla para ver el formato.');
                }

                $pdo->beginTransaction();
                $importacionIniciada = true;
                $serialesConocidos = [];
                foreach ($pdo->query("SELECT serial FROM equipos WHERE serial IS NOT NULL AND serial <> ''")->fetchAll(PDO::FETCH_COLUMN) as $serialExistente) {
                    $serialesConocidos[strtoupper(trim((string)$serialExistente))] = true;
                }
                $insertarEquipo = $pdo->prepare("INSERT INTO equipos (tipo, marca, modelo, condicion, estado, ubicacion, serial, observaciones) VALUES (:tipo, :marca, :modelo, :condicion, 'disponible', :ubicacion, :serial, :observaciones)");
                $agregados = 0;
                $duplicados = 0;
                $filasInvalidas = 0;
                $numeroFila = 1;

                while (($fila = fgetcsv($handle, null, $delimitador)) !== false) {
                    $numeroFila++;
                    if (count($fila) === 1 && trim((string)$fila[0]) === '') {
                        continue;
                    }
                    if ($numeroFila > 5001) {
                        throw new InvalidArgumentException('El archivo supera el límite de 5,000 equipos por importación.');
                    }

                    $valor = static function (string $campo) use ($indices, $fila): string {
                        return isset($indices[$campo]) ? trim((string)($fila[$indices[$campo]] ?? '')) : '';
                    };
                    $serial = $valor('serial');
                    $claveSerial = strtoupper($serial);
                    if ($serial === '' || strlen($serial) > 120) {
                        $filasInvalidas++;
                        continue;
                    }
                    if (isset($serialesConocidos[$claveSerial])) {
                        $duplicados++;
                        continue;
                    }

                    $condicion = strtolower(strtr($valor('condicion'), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']));
                    $condicion = str_replace([' ', '-'], '_', $condicion);
                    if ($condicion === 'no_sirve') {
                        $condicion = 'no_serve';
                    }
                    $condicionesPermitidas = ['nuevo', 'medio_uso', 'recogido', 'no_serve', 'danado'];
                    if ($condicion === '') {
                        $condicion = 'nuevo';
                    }
                    $tipo = $valor('tipo') ?: 'Modem GPON';
                    $marca = $valor('marca');
                    $modelo = $valor('modelo');
                    $ubicacion = $valor('ubicacion') ?: 'Tienda';
                    $observaciones = $valor('observaciones');
                    if (!in_array($condicion, $condicionesPermitidas, true)
                        || strlen($tipo) > 100
                        || strlen($marca) > 100
                        || strlen($modelo) > 120
                        || strlen($ubicacion) > 120) {
                        $filasInvalidas++;
                        continue;
                    }

                    $insertarEquipo->execute([
                        'tipo' => $tipo,
                        'marca' => $marca,
                        'modelo' => $modelo,
                        'condicion' => $condicion,
                        'ubicacion' => $ubicacion,
                        'serial' => $serial,
                        'observaciones' => $observaciones,
                    ]);
                    $serialesConocidos[$claveSerial] = true;
                    $agregados++;
                }

                if ($agregados > 0) {
                    registrarActividad($pdo, 'Tienda', 'Importar equipos', 'equipo', null, "{$agregados} equipos importados desde CSV; {$duplicados} duplicados omitidos y {$filasInvalidas} filas inválidas.");
                }
                $pdo->commit();
                $importacionIniciada = false;
                $mensaje = "Importación completada: {$agregados} agregados, {$duplicados} seriales duplicados omitidos y {$filasInvalidas} filas inválidas.";
            } catch (Throwable $error) {
                if ($importacionIniciada && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('No se pudo importar el CSV de equipos: ' . $error->getMessage());
                $mensaje = $error instanceof InvalidArgumentException
                    ? $error->getMessage()
                    : 'No se pudo completar la importación. No se guardaron cambios; revisa el archivo e inténtalo de nuevo.';
                $tipoMensaje = 'danger';
            } finally {
                fclose($handle);
            }
        }
    }
}

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
    $ciudad = trim($_POST['ciudad'] ?? '');
    $ipAsignada = trim($_POST['ip_asignada'] ?? '');
    if ($tecnicoId > 0 && in_array($tipoOrden, $tiposPermitidos, true) && $clienteNombre !== '' && $clienteNumero !== '' && $calle !== '' && $ciudad !== '') {
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
                    'ciudad' => $ciudad,
                    'referencias' => trim($_POST['referencias'] ?? ''),
                    'ubicacion_url' => trim($_POST['ubicacion_url'] ?? ''),
                ];

                if ($clienteId > 0) {
                    $clienteStmt = $pdo->prepare('UPDATE clientes SET nombre = :nombre, numero = :numero, calle = :calle, numero_exterior = :numero_exterior, colonia = :colonia, ciudad = :ciudad, referencias = :referencias, ubicacion_url = :ubicacion_url WHERE id = :id');
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
                        $clienteStmt = $pdo->prepare('UPDATE clientes SET nombre = :nombre, calle = :calle, numero_exterior = :numero_exterior, colonia = :colonia, ciudad = :ciudad, referencias = :referencias, ubicacion_url = :ubicacion_url WHERE id = :id');
                        $clienteStmt->execute($datosCliente + ['id' => $clienteId]);
                    } else {
                        $clienteStmt = $pdo->prepare('INSERT INTO clientes (nombre, numero, calle, numero_exterior, colonia, ciudad, referencias, ubicacion_url) VALUES (:nombre, :numero, :calle, :numero_exterior, :colonia, :ciudad, :referencias, :ubicacion_url)');
                        $clienteStmt->execute($datosCliente);
                        $clienteId = (int)$pdo->lastInsertId();
                    }
                }

                $stmt = $pdo->prepare('INSERT INTO ordenes (equipo_id, tecnico_id, cliente_id, cliente_nombre, cliente_numero, calle, numero_exterior, colonia, ciudad, referencias, ubicacion_url, ip_asignada, tipo_orden, estado, descripcion) VALUES (:equipo_id, :tecnico_id, :cliente_id, :cliente_nombre, :cliente_numero, :calle, :numero_exterior, :colonia, :ciudad, :referencias, :ubicacion_url, :ip_asignada, :tipo_orden, :estado, :descripcion)');
                $stmt->execute([
                    'equipo_id' => $equipoId,
                    'tecnico_id' => $tecnicoId,
                    'cliente_id' => $clienteId,
                    'cliente_nombre' => $clienteNombre,
                    'cliente_numero' => $clienteNumero,
                    'calle' => $calle,
                    'numero_exterior' => $datosCliente['numero_exterior'],
                    'colonia' => $datosCliente['colonia'],
                    'ciudad' => $datosCliente['ciudad'],
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
        $mensaje = ($clienteNombre === '' || $clienteNumero === '' || $calle === '' || $ciudad === '')
            ? 'Completa el nombre del cliente, teléfono, calle y pueblo o ciudad.'
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
$tipoExportacion = $_GET['exportar'] ?? '';
if (in_array($tipoExportacion, ['equipos', 'reportes', 'instalaciones', 'clientes', 'plantilla_modems'], true)) {
    $nombreArchivo = 'compuser-' . $tipoExportacion . '-' . date('Y-m-d') . '.csv';
    $filasExportacion = [];
    switch ($tipoExportacion) {
        case 'equipos':
            $encabezadosExportacion = ['ID', 'Tipo', 'Marca', 'Modelo', 'Serial', 'Condición', 'Estado', 'Ubicación', 'Observaciones', 'Creado', 'Actualizado'];
            foreach ($pdo->query('SELECT id, tipo, marca, modelo, serial, condicion, estado, ubicacion, observaciones, created_at, updated_at FROM equipos ORDER BY id ASC')->fetchAll() as $equipo) {
                $filasExportacion[] = array_values($equipo);
            }
            break;
        case 'reportes':
            $encabezadosExportacion = ['ID', 'Fecha', 'Técnico', 'Orden', 'Tipo de orden', 'Estado reporte', 'Cliente', 'Teléfono', 'Pueblo o ciudad', 'Equipo', 'Marca', 'Modelo', 'Serial', 'Título', 'Actividad', 'Materiales', 'Resultado', 'Descripción', 'Latitud', 'Longitud', 'Evidencias'];
            foreach ($reportes as $reporte) {
                $filasExportacion[] = [
                    $reporte['id'], $reporte['created_at'], $reporte['tecnico'], $reporte['orden_id'], $reporte['tipo_orden'],
                    $reporte['estado'], $reporte['cliente_nombre'], $reporte['cliente_numero'], $reporte['ciudad'], $reporte['equipo_tipo'],
                    $reporte['equipo_marca'], $reporte['equipo_modelo'], $reporte['equipo_serial'], $reporte['titulo'],
                    $reporte['actividad_realizada'], $reporte['materiales'], $reporte['resultado'], $reporte['descripcion'],
                    $reporte['latitud'], $reporte['longitud'], implode(' | ', $reporte['fotos']),
                ];
            }
            break;
        case 'instalaciones':
            $encabezadosExportacion = ['ID orden', 'Fecha', 'Estado', 'Técnico', 'Cliente', 'Teléfono', 'Calle', 'Número', 'Colonia', 'Pueblo o ciudad', 'Referencias', 'Ubicación', 'IP asignada', 'Equipo', 'Marca', 'Modelo', 'Serial', 'Descripción'];
            foreach ($ordenes as $orden) {
                if ($orden['tipo_orden'] !== 'instalacion') {
                    continue;
                }
                $filasExportacion[] = [
                    $orden['id'], $orden['created_at'], $orden['estado'], $orden['tecnico'], $orden['cliente_nombre'],
                    $orden['cliente_numero'], $orden['calle'], $orden['numero_exterior'], $orden['colonia'], $orden['ciudad'], $orden['referencias'],
                    $orden['ubicacion_url'], $orden['ip_asignada'], $orden['tipo'], $orden['marca'], $orden['modelo'],
                    $orden['serial'], $orden['descripcion'],
                ];
            }
            break;
        case 'clientes':
            $encabezadosExportacion = ['ID', 'Cliente', 'Teléfono', 'Calle', 'Número', 'Colonia', 'Pueblo o ciudad', 'Referencias', 'Ubicación', 'IP más reciente', 'Total de órdenes', 'Última visita'];
            $clientesExportacion = $pdo->query("SELECT c.id, c.nombre, c.numero, c.calle, c.numero_exterior, c.colonia, c.ciudad, c.referencias, c.ubicacion_url, (SELECT o_ip.ip_asignada FROM ordenes o_ip WHERE o_ip.cliente_id = c.id AND o_ip.ip_asignada IS NOT NULL AND o_ip.ip_asignada <> '' ORDER BY o_ip.created_at DESC, o_ip.id DESC LIMIT 1) AS ip_asignada, COUNT(o.id) AS total_ordenes, MAX(o.created_at) AS ultima_visita FROM clientes c LEFT JOIN ordenes o ON o.cliente_id = c.id GROUP BY c.id ORDER BY c.updated_at DESC, c.nombre ASC")->fetchAll();
            foreach ($clientesExportacion as $cliente) {
                $filasExportacion[] = array_values($cliente);
            }
            break;
        case 'plantilla_modems':
            $nombreArchivo = 'plantilla-importacion-modems.csv';
            $encabezadosExportacion = ['tipo', 'marca', 'modelo', 'serial', 'condicion', 'ubicacion', 'observaciones'];
            $filasExportacion[] = ['Modem GPON', '', '', '', 'nuevo', 'Tienda', ''];
            break;
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('X-Content-Type-Options: nosniff');
    $salidaCsv = fopen('php://output', 'wb');
    if ($salidaCsv === false) {
        throw new RuntimeException('No se pudo generar el archivo CSV.');
    }
    fwrite($salidaCsv, "\xEF\xBB\xBF");
    fputcsv($salidaCsv, $encabezadosExportacion, ';', '"', '');
    foreach ($filasExportacion as $filaExportacion) {
        $filaSegura = array_map(static function ($valor): string {
            $valor = (string)($valor ?? '');
            return preg_match('/^[\s]*[=+\-@]/u', $valor) ? "'" . $valor : $valor;
        }, $filaExportacion);
        fputcsv($salidaCsv, $filaSegura, ';', '"', '');
    }
    fclose($salidaCsv);
    exit;
}
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
$resumenReportesClientes = $vistaTienda === 'operacion' ? getResumenReportesClientes($pdo) : [];
?>