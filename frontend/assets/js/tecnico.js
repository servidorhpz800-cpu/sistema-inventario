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
