
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel administrador</title>
    <link rel="stylesheet" href="frontend/assets/css/style.css">
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

            <div class="card stacked-panel">
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

    <script>window.inventoryAdminData = <?php echo json_encode($equipos, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
    <script src="frontend/assets/js/admin.js" defer></script>
</body>
</html>
