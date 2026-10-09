
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel técnico</title>
    <link rel="stylesheet" href="frontend/assets/css/style.css">
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
                                        <div><span>Equipo</span><?php echo htmlspecialchars((string)($orden['tipo'] ?? 'Sin tipo'), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($orden['marca'] ?: 'Sin marca'); ?></div>
                                        <div><span>Cliente</span><?php echo htmlspecialchars($orden['cliente_nombre'] ?: 'Sin nombre'); ?> · <?php echo htmlspecialchars($orden['cliente_numero'] ?: 'Sin teléfono'); ?></div>
                                        <div><span>Pueblo o ciudad</span><?php echo htmlspecialchars($orden['ciudad'] ?: 'Sin ciudad'); ?></div>
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
                                    <option value="<?php echo (int)$orden['id']; ?>" data-cliente-nombre="<?php echo htmlspecialchars((string)($orden['cliente_nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-cliente-numero="<?php echo htmlspecialchars((string)($orden['cliente_numero'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-ciudad="<?php echo htmlspecialchars((string)($orden['ciudad'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)($orden['tipo'] ?? 'Sin tipo'), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars((string)($orden['tipo_orden'] ?? 'Sin tipo de orden'), ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Módem/equipo utilizado o revisado (opcional)</span>
                            <select name="movimiento_reporte_id">
                                <option value="0">Sin equipo específico</option>
                                <?php foreach ($movimientosTecnico as $movimiento): ?>
                                    <option value="<?php echo (int)$movimiento['id']; ?>" data-cliente-nombre="<?php echo htmlspecialchars($movimiento['cliente_nombre'] ?? '', ENT_QUOTES); ?>" data-cliente-numero="<?php echo htmlspecialchars($movimiento['cliente_numero'] ?? '', ENT_QUOTES); ?>" data-ciudad="<?php echo htmlspecialchars($movimiento['ciudad'] ?? '', ENT_QUOTES); ?>">
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
                                <label>
                                    <span>Pueblo o ciudad</span>
                                    <input type="text" name="ciudad" maxlength="120" required>
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
                            <span>Imágenes de lo realizado</span>
                            <input type="file" name="foto_reporte[]" accept="image/jpeg,image/png,image/webp" capture="environment" multiple>
                        </label>
                        <p class="form-note">Los límites de tamaño y cantidad de imágenes dependen de la configuración del servidor.</p>
                        <input type="hidden" name="latitud" id="latitudReporte">
                        <input type="hidden" name="longitud" id="longitudReporte">
                        <button type="button" id="capturarUbicacion" class="btn btn-secondary">Capturar ubicación actual</button>
                        <p class="form-note">Al enviar el reporte, la ciudad y la ubicación GPS se guardarán en el cliente para facilitar próximas visitas.</p>
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
                                    <option value="<?php echo (int)$movimiento['id']; ?>">
                                        <?php echo htmlspecialchars($movimiento['tipo']); ?> · Orden #<?php echo (int)$movimiento['orden_id']; ?> · <?php echo htmlspecialchars($movimiento['estado']); ?>
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
                        <label>
                            <span>Detalle de la situación</span>
                            <textarea name="equipo_observaciones_actualizacion" placeholder="Indica por qué no se utilizó o qué falla presentó."></textarea>
                        </label>
                        <button type="submit" name="actualizar_equipo_tecnico" class="btn btn-success">Guardar resultado</button>
                    </form>
            </div>
            <?php endif; ?>

            <div class="card equipment-history stacked-panel">
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

            <div class="card stacked-panel">
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
                                        <strong>Pueblo o ciudad:</strong> <?php echo htmlspecialchars($reporte['ciudad'] ?: 'Sin ciudad'); ?><br>
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
    <script src="frontend/assets/js/tecnico.js" defer></script>
</body>
</html>
