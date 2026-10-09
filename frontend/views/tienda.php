
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel tienda</title>
    <link rel="stylesheet" href="frontend/assets/css/style.css">
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
                            <tr><th>Cliente</th><th>Teléfono</th><th>Domicilio</th><th>Pueblo o ciudad</th><th>IP más reciente</th><th>Referencias</th><th>Órdenes</th><th>Última visita</th></tr>
                        </thead>
                        <tbody id="tablaClientes">
                            <?php if (empty($clientes)): ?>
                                <tr><td colspan="8">Los clientes se guardarán aquí al crear su primera orden.</td></tr>
                            <?php else: ?>
                                <?php foreach ($clientes as $cliente): ?>
                                    <?php $busquedaCliente = strtolower(trim($cliente['nombre'] . ' ' . $cliente['numero'] . ' ' . $cliente['calle'] . ' ' . ($cliente['numero_exterior'] ?? '') . ' ' . ($cliente['colonia'] ?? '') . ' ' . ($cliente['ciudad'] ?? ''))); ?>
                                    <tr data-client-search="<?php echo htmlspecialchars($busquedaCliente, ENT_QUOTES); ?>">
                                        <td><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                                        <td><a href="tel:<?php echo htmlspecialchars($cliente['numero']); ?>"><?php echo htmlspecialchars($cliente['numero']); ?></a></td>
                                        <td><?php echo htmlspecialchars(trim($cliente['calle'] . ' ' . ($cliente['numero_exterior'] ?: '') . ', ' . ($cliente['colonia'] ?: ''))); ?><?php if (!empty($cliente['ubicacion_url'])): ?><br><a href="<?php echo htmlspecialchars($cliente['ubicacion_url']); ?>" target="_blank" rel="noopener">Abrir mapa</a><?php endif; ?></td>
                                        <td><?php echo htmlspecialchars($cliente['ciudad'] ?: 'Sin ciudad registrada'); ?></td>
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

            <section class="card data-transfer-panel">
                <div class="card-heading">
                    <div>
                        <span class="eyebrow">Archivos CSV</span>
                        <h3>Importar y exportar información</h3>
                    </div>
                </div>
                <p class="form-note">Descarga el inventario, los reportes técnicos o las instalaciones que aparecen en la vista actual. Los archivos se pueden abrir con Excel.</p>
                <div class="inline-actions">
                    <a class="btn btn-secondary" href="tienda.php?exportar=equipos">Exportar inventario</a>
                    <a class="btn btn-secondary" href="tienda.php?exportar=reportes<?php echo $archivadas ? '&archivo=1' : ''; ?>">Exportar reportes</a>
                    <a class="btn btn-secondary" href="tienda.php?exportar=instalaciones<?php echo $archivadas ? '&archivo=1' : ''; ?>">Exportar instalaciones</a>
                    <a class="btn btn-secondary" href="tienda.php?exportar=clientes">Exportar clientes</a>
                    <a class="btn btn-secondary" href="tienda.php?exportar=plantilla_modems">Descargar plantilla de módems</a>
                </div>
                <hr>
                <form method="POST" enctype="multipart/form-data" class="import-equipment-form">
                    <label>
                        <span>Importar módems existentes al stock</span>
                        <input type="file" name="archivo_modems" accept=".csv,text/csv" required>
                    </label>
                    <button type="submit" name="importar_modems" class="btn btn-primary">Importar CSV</button>
                </form>
                <p class="form-note">El serial (o SN) es obligatorio. Los seriales ya registrados se omiten; los nuevos se agregan como disponibles en Tienda. Si falta el tipo, se usa “Modem GPON”. Límite: 5 MB y 5,000 equipos por archivo.</p>
            </section>

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
                                <select name="marca" data-modem-brand>
                                    <option value="">Seleccione marca</option>
                                    <option value="Huawei">Huawei</option>
                                    <option value="TP-Link">TP-Link</option>
                                    <option value="Telmex">Telmex</option>
                                    <option value="Nokia">Nokia</option>
                                    <option value="V-SOL">V-SOL</option>
                                    <option value="Otros">Otros</option>
                                </select>
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
                                    <input type="text" name="serial" id="serialInputTienda" data-modem-serial autocomplete="off">
                                    <button type="button" class="btn btn-secondary" data-scan-target="serialInputTienda">Escanear</button>
                                    <input type="file" accept="image/*" capture="environment" class="scan-file-input" data-scan-file-target="serialInputTienda" hidden>
                                    <button type="button" class="btn btn-secondary scan-file-trigger" data-scan-file-trigger="serialInputTienda">Desde foto</button>
                                </div>
                                <small class="modem-detection-result" data-modem-detection-result aria-live="polite">Conecta el lector USB y escanea el código en este campo para detectar el fabricante.</small>
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
                                            data-ciudad="<?php echo htmlspecialchars($cliente['ciudad'] ?? '', ENT_QUOTES); ?>"
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
                                <label>
                                    <span>Pueblo o ciudad</span>
                                    <input type="text" name="ciudad" maxlength="120" placeholder="Pueblo o ciudad" required>
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

            <div class="card dispatch-panel stacked-panel">
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

            <div class="card equipment-history stacked-panel">
                <div class="orders-toolbar">
                    <h3>Historial: quién usó cada equipo y dónde</h3>
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
                                <div><span>Pueblo o ciudad</span><?php echo htmlspecialchars($movimiento['ciudad'] ?: 'Ciudad no registrada'); ?></div>
                                <div><span>Ubicación</span><?php echo htmlspecialchars(trim(($movimiento['calle'] ?? '') . ' ' . ($movimiento['numero_exterior'] ?? '') . ' ' . ($movimiento['colonia'] ?? '')) ?: 'Domicilio no registrado'); ?></div>
                            </div>
                            <?php if (!empty($movimiento['observaciones'])): ?><p class="order-description"><?php echo htmlspecialchars($movimiento['observaciones']); ?></p><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card orders-panel stacked-panel">
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
                                <div><span>Pueblo o ciudad</span><?php echo htmlspecialchars($orden['ciudad'] ?: 'Sin ciudad'); ?></div>
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

            <div class="card reports-panel stacked-panel" data-report-filters>
                <div class="orders-toolbar">
                    <div>
                        <span class="eyebrow">Seguimiento técnico</span>
                        <h3>Lo que hicieron los técnicos</h3>
                    </div>
                    <span class="badge info"><?php echo count($reportes); ?> reportes</span>
                </div>
                <p class="form-note">El resumen es histórico y muestra cuántos reportes tiene cada cliente y cuántos envió cada técnico. El detalle respeta la vista diaria o el archivo seleccionado.</p>
                <div class="report-filter-bar">
                    <label>
                        <span>Filtrar por cliente</span>
                        <select data-report-client-filter>
                            <option value="">Todos los clientes</option>
                            <?php foreach ($resumenReportesClientes as $resumenCliente): ?>
                                <?php $etiquetaCliente = $resumenCliente['cliente_nombre'] . ($resumenCliente['cliente_numero'] !== '' ? ' · ' . $resumenCliente['cliente_numero'] : ''); ?>
                                <option value="<?php echo htmlspecialchars($resumenCliente['cliente_clave'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($etiquetaCliente); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Filtrar por técnico</span>
                        <select data-report-technician-filter>
                            <option value="">Todos los técnicos</option>
                            <?php foreach ($tecnicos as $tecnico): ?>
                                <option value="<?php echo htmlspecialchars(strtolower(trim($tecnico['nombre'])), ENT_QUOTES); ?>"><?php echo htmlspecialchars($tecnico['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <span class="badge info" data-report-visible-count aria-live="polite"><?php echo count($reportes); ?> reportes visibles</span>
                </div>
                <div class="report-summary">
                    <div class="orders-toolbar">
                        <div>
                            <span class="eyebrow">Historial por cliente</span>
                            <h3>Frecuencia de reportes y técnicos</h3>
                        </div>
                        <span class="badge info"><?php echo count($resumenReportesClientes); ?> clientes con reportes</span>
                    </div>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr><th>Cliente</th><th>Teléfono</th><th>Total de reportes</th><th>Técnicos que reportaron</th><th>Reporte más reciente</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($resumenReportesClientes)): ?>
                                    <tr><td colspan="5">Todavía no hay reportes asociados a clientes.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($resumenReportesClientes as $resumenCliente): ?>
                                        <tr data-report-filter-row data-report-summary data-report-client="<?php echo htmlspecialchars($resumenCliente['cliente_clave'], ENT_QUOTES); ?>" data-report-technicians="<?php echo htmlspecialchars($resumenCliente['tecnicos_clave'], ENT_QUOTES); ?>" data-report-technician-counts="<?php echo htmlspecialchars(json_encode($resumenCliente['conteos_tecnicos'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES); ?>">
                                            <td><?php echo htmlspecialchars($resumenCliente['cliente_nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($resumenCliente['cliente_numero'] ?: 'Sin número'); ?></td>
                                            <td><span class="badge primary" data-report-count><?php echo (int)$resumenCliente['total_reportes']; ?></span></td>
                                            <td><?php echo htmlspecialchars($resumenCliente['tecnicos'] ?: 'Sin técnico'); ?></td>
                                            <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($resumenCliente['ultimo_reporte']))); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <h3 class="report-detail-heading">Detalle de reportes</h3>
                <div class="order-list">
                    <?php if (empty($reportes)): ?>
                        <p>No hay reportes técnicos registrados todavía.</p>
                    <?php else: ?>
                        <?php foreach ($reportes as $reporte): ?>
                            <?php $clienteClaveReporte = trim((string)$reporte['cliente_numero']) !== '' ? trim((string)$reporte['cliente_numero']) : 'nombre:' . strtolower(trim((string)($reporte['cliente_nombre'] ?: 'Sin cliente'))); ?>
                            <article class="order-item" data-report-filter-row data-report-entry data-report-client="<?php echo htmlspecialchars($clienteClaveReporte, ENT_QUOTES); ?>" data-report-technician="<?php echo htmlspecialchars(strtolower(trim($reporte['tecnico'])), ENT_QUOTES); ?>">
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
    <script>window.inventoryData = <?php echo json_encode($equipos, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
    <script src="frontend/assets/js/report-filters.js" defer></script>
    <script src="frontend/assets/js/modem-detection.js" defer></script>
    <script src="frontend/assets/js/tienda.js" defer></script>
</body>
</html>
