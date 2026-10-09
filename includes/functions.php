<?php

function requireLogin(array $roles): void
{
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: index.php');
        exit;
    }

    if (!in_array($_SESSION['rol'], $roles, true)) {
        header('Location: index.php?error=acceso');
        exit;
    }
}

function registrarActividad(PDO $pdo, string $modulo, string $accion, string $entidad, ?int $entidadId = null, ?string $detalle = null): void
{
    if (!isset($_SESSION['usuario_id'], $_SESSION['nombre'], $_SESSION['rol'])) {
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO actividad_log (usuario_id, usuario_nombre, rol, modulo, accion, entidad, entidad_id, detalle) VALUES (:usuario_id, :usuario_nombre, :rol, :modulo, :accion, :entidad, :entidad_id, :detalle)');
    $stmt->execute([
        'usuario_id' => (int)$_SESSION['usuario_id'],
        'usuario_nombre' => $_SESSION['nombre'],
        'rol' => $_SESSION['rol'],
        'modulo' => $modulo,
        'accion' => $accion,
        'entidad' => $entidad,
        'entidad_id' => $entidadId,
        'detalle' => $detalle,
    ]);
}

function getActividad(PDO $pdo, int $limite = 150): array
{
    $limite = max(1, min($limite, 500));
    $stmt = $pdo->query("SELECT * FROM actividad_log ORDER BY created_at DESC, id DESC LIMIT {$limite}");
    return $stmt->fetchAll();
}

function getUsuarios(PDO $pdo, ?string $rol = null): array
{
    if ($rol) {
        $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE rol = :rol ORDER BY nombre ASC');
        $stmt->execute(['rol' => $rol]);
        return $stmt->fetchAll();
    }

    $stmt = $pdo->query('SELECT * FROM usuarios ORDER BY rol, nombre ASC');
    return $stmt->fetchAll();
}

function getInventario(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT * FROM equipos ORDER BY updated_at DESC');
    return $stmt->fetchAll();
}

function getOrdenes(PDO $pdo, ?int $tecnicoId = null, bool $archivadas = false): array
{
    $filtroArchivo = $archivadas
        ? "o.created_at < CURRENT_DATE AND o.estado <> 'pendiente'"
        : "(o.created_at >= CURRENT_DATE OR o.estado = 'pendiente')";
    $sql = "SELECT o.*, e.tipo, e.marca, e.modelo, e.condicion, u.nombre AS tecnico FROM ordenes o LEFT JOIN equipos e ON e.id = o.equipo_id JOIN usuarios u ON u.id = o.tecnico_id WHERE o.tipo_orden <> 'revision' AND {$filtroArchivo}";

    if ($tecnicoId !== null) {
        $sql .= ' AND o.tecnico_id = :tecnicoId';
        $stmt = $pdo->prepare($sql . ' ORDER BY o.created_at DESC');
        $stmt->execute(['tecnicoId' => $tecnicoId]);
        return $stmt->fetchAll();
    }

    $stmt = $pdo->query($sql . ' ORDER BY o.created_at DESC');
    return $stmt->fetchAll();
}

function getReportes(PDO $pdo, ?int $tecnicoId = null, bool $archivadas = false): array
{
    $filtroArchivo = $archivadas
        ? "r.created_at < CURRENT_DATE AND (o.id IS NULL OR o.estado <> 'pendiente')"
        : "(r.created_at >= CURRENT_DATE OR o.estado = 'pendiente')";
    $sql = "SELECT r.*, u.nombre AS tecnico, o.tipo_orden, o.estado AS orden_estado, COALESCE(NULLIF(TRIM(r.cliente_nombre), ''), o.cliente_nombre) AS cliente_nombre_actual, COALESCE(NULLIF(TRIM(r.cliente_numero), ''), o.cliente_numero) AS cliente_numero_actual, o.calle, o.numero_exterior, o.colonia, o.referencias, o.ubicacion_url, o.ip_asignada, e.tipo AS equipo_tipo, e.marca AS equipo_marca, e.modelo AS equipo_modelo, e.serial AS equipo_serial
        FROM reportes r
        JOIN usuarios u ON u.id = r.tecnico_id
        LEFT JOIN ordenes o ON o.id = r.orden_id
        LEFT JOIN equipos e ON e.id = COALESCE(r.equipo_id, o.equipo_id)
        WHERE {$filtroArchivo}";

    if ($tecnicoId !== null) {
        $stmt = $pdo->prepare($sql . ' AND r.tecnico_id = :tecnicoId ORDER BY r.created_at DESC');
        $stmt->execute(['tecnicoId' => $tecnicoId]);
        $reportes = $stmt->fetchAll();
    } else {
        $stmt = $pdo->query($sql . ' ORDER BY r.created_at DESC');
        $reportes = $stmt->fetchAll();
    }

    if (!$reportes) {
        return [];
    }

    $ids = array_column($reportes, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $fotosStmt = $pdo->prepare("SELECT reporte_id, foto_url FROM reporte_evidencias WHERE reporte_id IN ({$placeholders}) ORDER BY id ASC");
    $fotosStmt->execute($ids);
    $fotosPorReporte = [];
    foreach ($fotosStmt->fetchAll() as $foto) {
        $fotosPorReporte[(int)$foto['reporte_id']][] = $foto['foto_url'];
    }

    foreach ($reportes as &$reporte) {
        $reporte['cliente_nombre'] = $reporte['cliente_nombre_actual'];
        $reporte['cliente_numero'] = $reporte['cliente_numero_actual'];
        unset($reporte['cliente_nombre_actual'], $reporte['cliente_numero_actual']);
        $reporte['fotos'] = $fotosPorReporte[(int)$reporte['id']] ?? [];
        if (!$reporte['fotos'] && !empty($reporte['foto_url'])) {
            $reporte['fotos'][] = $reporte['foto_url'];
        }
    }
    unset($reporte);

    return $reportes;
}

function getResumenReportesClientes(PDO $pdo): array
{
    $clienteNumero = "COALESCE(NULLIF(TRIM(r.cliente_numero), ''), NULLIF(TRIM(o.cliente_numero), ''), '')";
    $clienteNombre = "COALESCE(NULLIF(TRIM(r.cliente_nombre), ''), NULLIF(TRIM(o.cliente_nombre), ''), 'Sin cliente')";
    $clienteClave = "COALESCE(NULLIF(TRIM(r.cliente_numero), ''), NULLIF(TRIM(o.cliente_numero), ''), CONCAT('nombre:', LOWER(TRIM({$clienteNombre}))))";
    $sql = "SELECT {$clienteClave} AS cliente_clave, {$clienteNombre} AS cliente_nombre, {$clienteNumero} AS cliente_numero,
            u.nombre AS tecnico_nombre, LOWER(TRIM(u.nombre)) AS tecnico_clave,
            COUNT(DISTINCT r.id) AS total_reportes_tecnico, MAX(r.created_at) AS ultimo_reporte
        FROM reportes r
        JOIN usuarios u ON u.id = r.tecnico_id
        LEFT JOIN ordenes o ON o.id = r.orden_id
        GROUP BY 1, 2, 3, 4, 5
        ORDER BY cliente_nombre ASC";
    $resumenes = [];
    foreach ($pdo->query($sql)->fetchAll() as $fila) {
        $clave = (string)$fila['cliente_clave'];
        if (!isset($resumenes[$clave])) {
            $resumenes[$clave] = [
                'cliente_clave' => $clave,
                'cliente_nombre' => $fila['cliente_nombre'],
                'cliente_numero' => $fila['cliente_numero'],
                'total_reportes' => 0,
                'tecnicos' => [],
                'tecnicos_clave' => [],
                'conteos_tecnicos' => [],
                'ultimo_reporte' => $fila['ultimo_reporte'],
            ];
        }

        $tecnicoClave = (string)$fila['tecnico_clave'];
        $resumenes[$clave]['total_reportes'] += (int)$fila['total_reportes_tecnico'];
        $resumenes[$clave]['tecnicos'][$tecnicoClave] = $fila['tecnico_nombre'];
        $resumenes[$clave]['tecnicos_clave'][$tecnicoClave] = $tecnicoClave;
        $resumenes[$clave]['conteos_tecnicos'][$tecnicoClave] = ($resumenes[$clave]['conteos_tecnicos'][$tecnicoClave] ?? 0) + (int)$fila['total_reportes_tecnico'];
        if ($fila['ultimo_reporte'] > $resumenes[$clave]['ultimo_reporte']) {
            $resumenes[$clave]['ultimo_reporte'] = $fila['ultimo_reporte'];
        }
    }

    foreach ($resumenes as &$resumen) {
        $resumen['tecnicos'] = implode(', ', $resumen['tecnicos']);
        $resumen['tecnicos_clave'] = implode('|', $resumen['tecnicos_clave']);
    }
    unset($resumen);

    $resumenes = array_values($resumenes);
    usort($resumenes, static function (array $a, array $b): int {
        return $b['total_reportes'] <=> $a['total_reportes'] ?: strcasecmp($a['cliente_nombre'], $b['cliente_nombre']);
    });
    return $resumenes;
}

function getEquiposTecnico(PDO $pdo, ?int $tecnicoId = null): array
{
    $sql = "SELECT DISTINCT e.id, e.tipo, e.marca, e.modelo, e.serial, o.id AS orden_id, o.tipo_orden, o.cliente_nombre FROM ordenes o JOIN equipos e ON e.id = o.equipo_id WHERE o.tipo_orden <> 'revision'";
    if ($tecnicoId !== null) {
        $sql .= ' AND o.tecnico_id = :tecnicoId';
    }
    $stmt = $pdo->prepare($sql . ' ORDER BY e.tipo, e.marca');
    if ($tecnicoId !== null) {
        $stmt->execute(['tecnicoId' => $tecnicoId]);
    } else {
        $stmt->execute();
    }
    return $stmt->fetchAll();
}

function getMovimientosTecnico(PDO $pdo, ?int $tecnicoId = null): array
{
    $sql = 'SELECT et.*, e.tipo, e.marca, e.modelo, e.serial, o.tipo_orden, COALESCE(et.cliente_nombre, o.cliente_nombre) AS cliente_nombre_actual, COALESCE(et.cliente_numero, o.cliente_numero) AS cliente_numero_actual, u.nombre AS tecnico FROM equipos_tecnico et JOIN equipos e ON e.id = et.equipo_id LEFT JOIN ordenes o ON o.id = et.orden_id JOIN usuarios u ON u.id = et.tecnico_id';
    if ($tecnicoId !== null) {
        $sql .= ' WHERE et.tecnico_id = :tecnicoId';
    }
    $stmt = $pdo->prepare($sql . ' ORDER BY et.updated_at DESC');
    if ($tecnicoId !== null) {
        $stmt->execute(['tecnicoId' => $tecnicoId]);
    } else {
        $stmt->execute();
    }
    $movimientos = $stmt->fetchAll();
    foreach ($movimientos as &$movimiento) {
        $movimiento['cliente_nombre'] = $movimiento['cliente_nombre_actual'];
        $movimiento['cliente_numero'] = $movimiento['cliente_numero_actual'];
        unset($movimiento['cliente_nombre_actual'], $movimiento['cliente_numero_actual']);
    }
    unset($movimiento);
    return $movimientos;
}

function badgeClass(string $estado): string
{
    $map = [
        'disponible' => 'success',
        'asignado' => 'primary',
        'en_reparacion' => 'warning',
        'retirado' => 'dark',
        'dado_baja' => 'danger',
        'nuevo' => 'success',
        'revision' => 'warning',
        'medio_uso' => 'info',
        'recogido' => 'secondary',
        'no_serve' => 'danger',
        'danado' => 'danger',
        'dañado' => 'danger',
        'pendiente' => 'warning',
        'en_proceso' => 'primary',
        'completado' => 'success',
        'cancelado' => 'dark',
        'portado' => 'primary',
        'utilizado' => 'success',
        'no_utilizado' => 'secondary',
        'falla' => 'danger',
        'devuelto' => 'info',
    ];

    return $map[$estado] ?? 'secondary';
}
