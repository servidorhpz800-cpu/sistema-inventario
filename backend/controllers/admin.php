<?php
require_once dirname(__DIR__, 2) . '/includes/session.php';
iniciarSesionPersistente();
require dirname(__DIR__, 2) . '/config/database.php';
require dirname(__DIR__, 2) . '/includes/functions.php';
requireLogin(['admin']);

$mensaje = null;
$tipoMensaje = 'success';
$adminCsrfToken = $_SESSION['admin_csrf_token'] ??= bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_delete_action'])) {
    $accionEliminar = (string)$_POST['admin_delete_action'];
    $idEliminar = (int)($_POST['record_id'] ?? 0);
    $tokenRecibido = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($adminCsrfToken, $tokenRecibido)) {
        $mensaje = 'La solicitud de eliminación no es válida. Recarga la página e inténtalo de nuevo.';
        $tipoMensaje = 'danger';
    } elseif ($idEliminar <= 0) {
        $mensaje = 'El registro seleccionado no es válido.';
        $tipoMensaje = 'danger';
    } else {
        $tabDespuesEliminar = 'inventario';
        $entidadesEliminar = [
            'report' => ['reportes', 'reporte', 'reporte técnico'],
            'order' => ['ordenes', 'orden', 'orden'],
            'equipment' => ['equipos', 'equipo', 'equipo'],
            'client' => ['clientes', 'cliente', 'cliente'],
        ];

        if (!isset($entidadesEliminar[$accionEliminar])) {
            $mensaje = 'El tipo de registro solicitado no se puede eliminar.';
            $tipoMensaje = 'danger';
        } else {
            [$tablaEliminar, $entidadEliminar, $nombreEliminar] = $entidadesEliminar[$accionEliminar];
            $tabsPorEntidad = ['report' => 'reportes', 'order' => 'ordenes', 'equipment' => 'inventario_general', 'client' => 'clientes'];
            $tabDespuesEliminar = $tabsPorEntidad[$accionEliminar];
            $rutasEvidenciaEliminar = [];
            $eliminacionConfirmada = false;

            try {
                $directoriosEvidencia = [];
                if ($accionEliminar === 'report') {
                    $directoriosEvidencia = array_unique([
                        getReportesUploadDirectory(),
                        dirname(__DIR__, 2) . '/uploads/reportes',
                    ]);
                }
                $pdo->beginTransaction();

                if ($accionEliminar === 'report') {
                    $reporteStmt = $pdo->prepare('SELECT foto_url FROM reportes WHERE id = :id LIMIT 1');
                    $reporteStmt->execute(['id' => $idEliminar]);
                    $fotoPrincipal = $reporteStmt->fetchColumn();
                    if ($fotoPrincipal === false) {
                        throw new DomainException('No se encontró el reporte seleccionado.');
                    }
                    if (is_string($fotoPrincipal) && $fotoPrincipal !== '') {
                        $rutasEvidenciaEliminar[] = $fotoPrincipal;
                    }
                    $evidenciasStmt = $pdo->prepare('SELECT foto_url FROM reporte_evidencias WHERE reporte_id = :id');
                    $evidenciasStmt->execute(['id' => $idEliminar]);
                    $rutasEvidenciaEliminar = array_merge($rutasEvidenciaEliminar, $evidenciasStmt->fetchAll(PDO::FETCH_COLUMN));
                }

                $eliminarStmt = $pdo->prepare("DELETE FROM {$tablaEliminar} WHERE id = :id");
                $eliminarStmt->execute(['id' => $idEliminar]);
                if ($eliminarStmt->rowCount() !== 1) {
                    throw new DomainException("No se encontró el {$nombreEliminar} seleccionado.");
                }

                registrarActividad($pdo, 'Admin', 'Eliminar registro', $entidadEliminar, $idEliminar, ucfirst($nombreEliminar) . " eliminado desde administración (ID {$idEliminar}).");
                $pdo->commit();
                $eliminacionConfirmada = true;

                if ($accionEliminar === 'report') {
                    $rutasEvidenciaEliminar = array_unique(array_filter($rutasEvidenciaEliminar, static function ($ruta): bool {
                        return is_string($ruta)
                            && preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,127}\.(?:jpe?g|png|webp)\z/i', basename(str_replace('\\', '/', $ruta))) === 1
                            && preg_match('/^https?:\/\//i', $ruta) !== 1;
                    }));
                    $noSePudieronBorrarEvidencias = false;

                    foreach ($rutasEvidenciaEliminar as $rutaEvidencia) {
                        $nombreArchivoEvidencia = basename(str_replace('\\', '/', $rutaEvidencia));
                        $referenciaEvidencia = $pdo->prepare('SELECT 1 FROM reportes WHERE foto_url = ? UNION ALL SELECT 1 FROM reporte_evidencias WHERE foto_url = ? LIMIT 1');
                        $referenciaEvidencia->execute([$rutaEvidencia, $rutaEvidencia]);
                        if ($referenciaEvidencia->fetchColumn()) {
                            continue;
                        }

                        foreach ($directoriosEvidencia as $directorioEvidencia) {
                            $archivoEvidencia = $directorioEvidencia . DIRECTORY_SEPARATOR . $nombreArchivoEvidencia;
                            if (is_file($archivoEvidencia) && !unlink($archivoEvidencia)) {
                                $noSePudieronBorrarEvidencias = true;
                                error_log('No se pudo borrar el archivo de evidencia: ' . $archivoEvidencia);
                            }
                        }
                    }

                    $mensaje = $noSePudieronBorrarEvidencias
                        ? 'El reporte se eliminó, pero no se pudo borrar uno o más archivos de evidencia. Revisa el registro de errores del servidor.'
                        : 'El reporte y sus evidencias se eliminaron correctamente.';
                } else {
                    $mensaje = ucfirst($nombreEliminar) . ' eliminado correctamente.';
                }
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($eliminacionConfirmada) {
                    $mensaje = $accionEliminar === 'report'
                        ? 'El reporte se eliminó, pero no se pudieron limpiar correctamente sus archivos de evidencia. Revisa el registro de errores del servidor.'
                        : ucfirst($nombreEliminar) . ' eliminado correctamente.';
                } else {
                    $mensaje = $error instanceof DomainException
                        ? $error->getMessage()
                        : "No se pudo eliminar el {$nombreEliminar}. No se guardaron cambios.";
                }
                if (!($error instanceof DomainException)) {
                    error_log('No se pudo eliminar registro desde administración: ' . $error->getMessage());
                }
                $tipoMensaje = $eliminacionConfirmada && $accionEliminar !== 'report' ? 'success' : 'danger';
            }
        }

        if ($tipoMensaje === 'success' || $eliminacionConfirmada) {
            $_SESSION['flash_message'] = ['mensaje' => $mensaje, 'tipo' => $tipoMensaje];
            header('Location: admin.php?tab=' . rawurlencode($tabDespuesEliminar), true, 303);
            exit;
        }
    }
}

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

manejarPostRedirectGet($mensaje, $tipoMensaje);

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
$ordenes = $pdo->query('SELECT o.*, u.nombre AS tecnico FROM ordenes o JOIN usuarios u ON u.id = o.tecnico_id ORDER BY o.created_at DESC')->fetchAll();
$clientes = $pdo->query("SELECT c.*, COUNT(o.id) AS total_ordenes, MAX(o.created_at) AS ultima_visita,
    (SELECT o_ip.ip_asignada FROM ordenes o_ip WHERE o_ip.cliente_id = c.id AND o_ip.ip_asignada IS NOT NULL AND o_ip.ip_asignada <> '' ORDER BY o_ip.created_at DESC, o_ip.id DESC LIMIT 1) AS ip_asignada,
    COALESCE(NULLIF(c.ubicacion_url, ''), (SELECT CONCAT('https://www.google.com/maps?q=', r.latitud, ',', r.longitud)
        FROM reportes r
        LEFT JOIN ordenes orden_reporte ON orden_reporte.id = r.orden_id
        WHERE r.latitud IS NOT NULL
          AND r.longitud IS NOT NULL
          AND (r.cliente_numero = c.numero OR orden_reporte.cliente_id = c.id)
        ORDER BY r.created_at DESC, r.id DESC
        LIMIT 1)) AS ubicacion_url_actual
    FROM clientes c LEFT JOIN ordenes o ON o.cliente_id = c.id
    GROUP BY c.id ORDER BY c.updated_at DESC, c.nombre ASC")->fetchAll();
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
if (!in_array($pestana, ['inventario', 'personal', 'reportes', 'actividad', 'inventario_general', 'ordenes', 'clientes'], true)) {
    $pestana = 'inventario';
}
$resumenReportesClientes = $pestana === 'reportes' ? getResumenReportesClientes($pdo) : [];
?>