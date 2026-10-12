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
            capturarUbicacion.addEventListener('click', () => {
                if (!navigator.geolocation) {
                    alert('Este navegador no permite capturar ubicación.');
                    return;
                }
                capturarUbicacion.disabled = true;
                navigator.geolocation.getCurrentPosition((position) => {
                    document.getElementById('latitudReporte').value = position.coords.latitude.toFixed(7);
                    document.getElementById('longitudReporte').value = position.coords.longitude.toFixed(7);
                    capturarUbicacion.textContent = 'Ubicación capturada';
                    capturarUbicacion.disabled = false;
                }, () => {
                    alert('No fue posible obtener la ubicación. Revisa el permiso del navegador.');
                    capturarUbicacion.disabled = false;
                }, { enableHighAccuracy: true, timeout: 10000 });
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
