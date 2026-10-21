const notificacionesOrdenes = document.getElementById('notificacionesOrdenes');
if (notificacionesOrdenes) {
    const botonNotificaciones = document.getElementById('activarNotificacionesOrdenes');
    const estadoNotificaciones = document.getElementById('estadoNotificacionesOrdenes');
    let ultimaOrdenId = Number.parseInt(notificacionesOrdenes.dataset.sinceId || '0', 10);
    let temporizadorNotificaciones;
    let consultaEnCurso = false;
    const notificacionesDisponibles = 'Notification' in window;

    const actualizarEstadoNotificaciones = (mensaje) => {
        if (estadoNotificaciones) {
            estadoNotificaciones.textContent = mensaje;
        }
    };

    const actualizarInterfazPermiso = () => {
        if (!notificacionesDisponibles || !botonNotificaciones) {
            return;
        }
        if (Notification.permission === 'granted') {
            iniciarConsultaNotificaciones();
        } else if (Notification.permission === 'denied') {
            botonNotificaciones.disabled = true;
            actualizarEstadoNotificaciones('El permiso está bloqueado para este sitio. Cámbialo en el icono junto a la dirección del navegador y vuelve a cargar la página.');
        } else if (!window.isSecureContext) {
            actualizarEstadoNotificaciones('El navegador solo permite notificaciones en HTTPS o al abrir el sistema desde localhost.');
        } else {
            botonNotificaciones.disabled = false;
            actualizarEstadoNotificaciones('Pulsa el botón para que el navegador te pregunte si permites las notificaciones.');
        }
    };

    const mostrarAvisoOrden = (orden) => {
        const tiposOrden = {
            retirar_equipo: 'Retirar equipo',
            instalacion: 'Instalación',
            cambio_domicilio: 'Cambio de domicilio',
            cambio_contrasena: 'Cambio de contraseña',
            reporte: 'Reporte de cliente',
            retiro: 'Retiro',
            recogida: 'Recogida',
            revision: 'Revisión',
        };
        const tipo = tiposOrden[orden.tipo_orden] || 'Nueva orden';
        const aviso = new Notification('Nueva orden asignada', {
            body: `${tipo} · Folio #${orden.id}`,
            tag: `orden-${orden.id}`,
        });
        aviso.addEventListener('click', () => {
            window.focus();
            aviso.close();
            window.location.reload();
        });
    };

    const consultarNuevasOrdenes = async () => {
        if (consultaEnCurso || Notification.permission !== 'granted') {
            return;
        }
        consultaEnCurso = true;
        try {
            const respuesta = await fetch(
                `${notificacionesOrdenes.dataset.endpoint}?after=${encodeURIComponent(ultimaOrdenId)}`,
                { credentials: 'same-origin', cache: 'no-store' },
            );
            if (!respuesta.ok) {
                throw new Error(`HTTP ${respuesta.status}`);
            }
            const datos = await respuesta.json();
            if (!Array.isArray(datos.ordenes)) {
                throw new Error('Respuesta de notificaciones inválida.');
            }
            datos.ordenes.forEach((orden) => {
                const id = Number.parseInt(orden.id, 10);
                if (!Number.isInteger(id) || id <= ultimaOrdenId) {
                    return;
                }
                ultimaOrdenId = id;
                mostrarAvisoOrden(orden);
            });
        } catch (error) {
            console.error('No se pudieron consultar las nuevas órdenes.', error);
            actualizarEstadoNotificaciones('No se pudieron consultar las órdenes. Revisa tu conexión.');
        } finally {
            consultaEnCurso = false;
        }
    };

    const iniciarConsultaNotificaciones = () => {
        if (temporizadorNotificaciones) {
            return;
        }
        if (botonNotificaciones) {
            botonNotificaciones.textContent = 'Notificaciones activas';
            botonNotificaciones.disabled = true;
        }
        actualizarEstadoNotificaciones('Notificaciones activas. Revisa las órdenes aproximadamente cada 15 segundos.');
        consultarNuevasOrdenes();
        temporizadorNotificaciones = window.setInterval(consultarNuevasOrdenes, 15000);
    };

    if (!notificacionesDisponibles || !('fetch' in window)) {
        actualizarEstadoNotificaciones('Este navegador no admite notificaciones del sistema.');
        if (botonNotificaciones) {
            botonNotificaciones.disabled = true;
        }
    } else {
        actualizarInterfazPermiso();
        if (botonNotificaciones && Notification.permission === 'default') {
            botonNotificaciones.addEventListener('click', (event) => {
                event.preventDefault();
                if (!window.isSecureContext) {
                    actualizarEstadoNotificaciones('No se pudo mostrar el permiso: abre el sistema con HTTPS o desde localhost y vuelve a intentarlo.');
                    return;
                }

                let solicitudPermiso;
                try {
                    solicitudPermiso = Notification.requestPermission();
                } catch (error) {
                    console.error('El navegador no pudo solicitar permiso para notificaciones.', error);
                    actualizarEstadoNotificaciones('El navegador no pudo mostrar el permiso. Revisa la configuración de notificaciones del sitio.');
                    return;
                }

                botonNotificaciones.disabled = true;
                actualizarEstadoNotificaciones('Esperando tu respuesta al permiso del navegador...');
                Promise.resolve(solicitudPermiso)
                    .then((permiso) => {
                        if (permiso === 'granted') {
                            iniciarConsultaNotificaciones();
                        } else if (permiso === 'denied') {
                            actualizarEstadoNotificaciones('Se bloqueó el permiso. Cámbialo en el icono junto a la dirección del navegador y vuelve a cargar la página.');
                        } else {
                            actualizarEstadoNotificaciones('No se concedió el permiso. Puedes pulsar el botón para intentarlo otra vez.');
                        }
                    })
                    .catch((error) => {
                        console.error('No se pudo completar la solicitud de permiso de notificaciones.', error);
                        actualizarEstadoNotificaciones('No se pudo solicitar el permiso. Revisa la configuración del navegador y vuelve a intentarlo.');
                    })
                    .finally(() => {
                        botonNotificaciones.disabled = Notification.permission !== 'default';
                    });
            }
        }
    }
}

const filtroOrdenes = document.getElementById('filtroOrdenes');
        if (filtroOrdenes) {
            filtroOrdenes.addEventListener('change', () => {
                document.querySelectorAll('.order-item').forEach((order) => {
                    order.hidden = filtroOrdenes.value !== 'todos' && order.dataset.orderType !== filtroOrdenes.value;
                });
            });
        }
        document.querySelectorAll('.order-status-form').forEach((form) => {
            const estadoSelect = form.querySelector('[data-order-status]');
            const motivoWrapper = form.querySelector('[data-cancel-reason]');
            const motivoInput = form.querySelector('[data-cancel-reason-input]');
            if (!estadoSelect || !motivoWrapper || !motivoInput) {
                return;
            }

            const actualizarMotivo = () => {
                const requiereMotivo = estadoSelect.value === 'cancelado';
                motivoWrapper.hidden = !requiereMotivo;
                motivoInput.required = requiereMotivo;
            };

            estadoSelect.addEventListener('change', actualizarMotivo);
            actualizarMotivo();
        });
        const capturarUbicacion = document.getElementById('capturarUbicacion');
        if (capturarUbicacion) {
            const estadoUbicacion = document.getElementById('estadoUbicacionReporte');
            const latitudReporte = document.getElementById('latitudReporte');
            const longitudReporte = document.getElementById('longitudReporte');
            const precisionObjetivoMetros = 10;
            const tiempoMaximoCapturaMs = 45000;

            capturarUbicacion.addEventListener('click', () => {
                if (!navigator.geolocation) {
                    if (estadoUbicacion) {
                        estadoUbicacion.textContent = 'Este navegador no permite capturar ubicación.';
                    }
                    return;
                }

                capturarUbicacion.disabled = true;
                capturarUbicacion.textContent = 'Buscando señal GPS...';
                if (estadoUbicacion) {
                    estadoUbicacion.textContent = 'Mantén el teléfono quieto y espera a que mejore la precisión.';
                }

                let mejorPosicion = null;
                let capturaFinalizada = false;
                let mensajeSinPosicion = 'No se obtuvo una posición GPS. Revisa el permiso de ubicación e inténtalo al aire libre.';
                let relojCaptura;
                let watchId;

                const finalizarCaptura = () => {
                    if (capturaFinalizada) {
                        return;
                    }
                    capturaFinalizada = true;
                    window.clearTimeout(relojCaptura);
                    if (watchId !== undefined) {
                        navigator.geolocation.clearWatch(watchId);
                    }
                    capturarUbicacion.disabled = false;
                    capturarUbicacion.textContent = 'Capturar ubicación actual';

                    if (!mejorPosicion) {
                        if (estadoUbicacion) {
                            estadoUbicacion.textContent = mensajeSinPosicion;
                        }
                        return;
                    }

                    const precision = mejorPosicion.coords.accuracy.toFixed(1);
                    if (mejorPosicion.coords.accuracy > precisionObjetivoMetros) {
                        if (estadoUbicacion) {
                            estadoUbicacion.textContent = `No se guardó esta lectura: su precisión es ±${precision} m y se requiere ±${precisionObjetivoMetros} m o mejor. Inténtalo al aire libre con el GPS activado.`;
                        }
                        return;
                    }

                    latitudReporte.value = mejorPosicion.coords.latitude.toFixed(7);
                    longitudReporte.value = mejorPosicion.coords.longitude.toFixed(7);
                    if (estadoUbicacion) {
                        estadoUbicacion.textContent = `Ubicación capturada. Precisión aproximada: ±${precision} m.`;
                    }
                };

                try {
                    relojCaptura = window.setTimeout(finalizarCaptura, tiempoMaximoCapturaMs);
                    watchId = navigator.geolocation.watchPosition((position) => {
                        if (capturaFinalizada) {
                            return;
                        }
                        const { latitude, longitude, accuracy } = position.coords;
                        if (
                            !Number.isFinite(latitude)
                            || latitude < -90
                            || latitude > 90
                            || !Number.isFinite(longitude)
                            || longitude < -180
                            || longitude > 180
                            || !Number.isFinite(accuracy)
                            || accuracy < 0
                        ) {
                            return;
                        }

                        if (!mejorPosicion || accuracy < mejorPosicion.coords.accuracy) {
                            mejorPosicion = position;
                            if (estadoUbicacion) {
                                estadoUbicacion.textContent = `Señal GPS recibida. Precisión actual: ±${accuracy.toFixed(1)} m; buscando mejorarla...`;
                            }
                        }

                        if (accuracy <= precisionObjetivoMetros) {
                            finalizarCaptura();
                        }
                    }, (error) => {
                        if (capturaFinalizada) {
                            return;
                        }
                        if (error.code === error.PERMISSION_DENIED) {
                            mensajeSinPosicion = 'No hay permiso de ubicación. Autorízalo en el navegador y vuelve a intentarlo.';
                            finalizarCaptura();
                            return;
                        }

                        if (estadoUbicacion) {
                            estadoUbicacion.textContent = 'Aún no se obtiene señal GPS; espera un momento o inténtalo en un lugar abierto.';
                        }
                    }, { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 });
                } catch (error) {
                    mensajeSinPosicion = 'No se pudo iniciar el GPS. Revisa que la página use HTTPS y vuelve a intentarlo.';
                    finalizarCaptura();
                    console.error('No se pudo iniciar la captura de ubicación:', error);
                }
            });
        }
        const ordenReporteSelect = document.querySelector('#reporteTecnicoForm select[name="orden_id"]');
        const movimientoReporteSelect = document.querySelector('#reporteTecnicoForm select[name="movimiento_reporte_id"]');
        const clienteNombreReporte = document.querySelector('#reporteTecnicoForm input[name="cliente_nombre"]');
        const clienteNumeroReporte = document.querySelector('#reporteTecnicoForm input[name="cliente_numero"]');
        const ciudadReporte = document.querySelector('#reporteTecnicoForm input[name="ciudad"]');
        const domicilioReporte = document.querySelector('#reporteTecnicoForm input[name="domicilio_calles"]');
        const ipClienteReporte = document.querySelector('#reporteTecnicoForm input[name="ip_cliente"]');
        const fotosReportePreview = document.getElementById('fotosReportePreview');
        const reporteTecnicoForm = document.getElementById('reporteTecnicoForm');

        const prepararSelectorFoto = (inputFoto) => {
            inputFoto.addEventListener('change', () => {
                const archivo = inputFoto.files?.[0];
                if (!archivo || !reporteTecnicoForm) {
                    return;
                }

                const siguienteInputFoto = inputFoto.cloneNode();
                inputFoto.replaceWith(siguienteInputFoto);
                inputFoto.hidden = true;
                reporteTecnicoForm.appendChild(inputFoto);

                const elementoFoto = document.createElement('div');
                elementoFoto.className = 'report-photo-item';

                const miniatura = document.createElement('img');
                miniatura.src = URL.createObjectURL(archivo);
                miniatura.alt = `Imagen adjunta: ${archivo.name}`;

                const nombreFoto = document.createElement('span');
                nombreFoto.textContent = archivo.name;

                const quitarFoto = document.createElement('button');
                quitarFoto.type = 'button';
                quitarFoto.className = 'btn btn-secondary report-photo-remove';
                quitarFoto.textContent = 'Quitar';
                quitarFoto.setAttribute('aria-label', `Quitar ${archivo.name}`);
                quitarFoto.addEventListener('click', () => {
                    URL.revokeObjectURL(miniatura.src);
                    inputFoto.remove();
                    elementoFoto.remove();
                });

                elementoFoto.append(miniatura, nombreFoto, quitarFoto);
                fotosReportePreview.appendChild(elementoFoto);
                prepararSelectorFoto(siguienteInputFoto);
            });
        };

        document.querySelectorAll('#reporteTecnicoForm [data-report-photo-input]').forEach(prepararSelectorFoto);

        const completarClienteReporte = (select) => {
            const opcion = select?.selectedOptions[0];
            if (opcion && opcion.value !== '0') {
                clienteNombreReporte.value = opcion.dataset.clienteNombre || '';
                clienteNumeroReporte.value = opcion.dataset.clienteNumero || '';
                ciudadReporte.value = opcion.dataset.ciudad || '';
                domicilioReporte.value = opcion.dataset.direccion || '';
                ipClienteReporte.value = opcion.dataset.ip || '';
            } else {
                clienteNombreReporte.value = '';
                clienteNumeroReporte.value = '';
                ciudadReporte.value = '';
                domicilioReporte.value = '';
                ipClienteReporte.value = '';
            }
        };
        ordenReporteSelect?.addEventListener('change', () => completarClienteReporte(ordenReporteSelect));
        movimientoReporteSelect?.addEventListener('change', () => completarClienteReporte(movimientoReporteSelect));
