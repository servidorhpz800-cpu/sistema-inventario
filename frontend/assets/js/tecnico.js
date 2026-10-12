const filtroOrdenes = document.getElementById('filtroOrdenes');
        if (filtroOrdenes) {
            filtroOrdenes.addEventListener('change', () => {
                document.querySelectorAll('.order-item').forEach((order) => {
                    order.hidden = filtroOrdenes.value !== 'todos' && order.dataset.orderType !== filtroOrdenes.value;
                });
            });
        }
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
        const agregarFotoReporte = document.getElementById('agregarFotoReporte');
        const fotosReportePreview = document.getElementById('fotosReportePreview');
        let cantidadFotosReporte = 0;

        agregarFotoReporte?.addEventListener('click', () => {
            const inputFoto = document.createElement('input');
            inputFoto.type = 'file';
            inputFoto.name = 'foto_reporte[]';
            inputFoto.accept = 'image/jpeg,image/png,image/webp';
            inputFoto.capture = 'environment';
            inputFoto.hidden = true;
            document.getElementById('reporteTecnicoForm').appendChild(inputFoto);

            inputFoto.addEventListener('change', () => {
                const archivo = inputFoto.files?.[0];
                if (!archivo) {
                    inputFoto.remove();
                    return;
                }

                cantidadFotosReporte++;
                const elementoFoto = document.createElement('div');
                elementoFoto.className = 'report-photo-item';

                const miniatura = document.createElement('img');
                miniatura.src = URL.createObjectURL(archivo);
                miniatura.alt = `Imagen adjunta ${cantidadFotosReporte}`;

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
                    cantidadFotosReporte--;
                    agregarFotoReporte.textContent = cantidadFotosReporte > 0 ? 'Subir otra imagen' : 'Subir imagen';
                });

                elementoFoto.append(miniatura, nombreFoto, quitarFoto);
                fotosReportePreview.appendChild(elementoFoto);
                agregarFotoReporte.textContent = 'Subir otra imagen';
            });

            inputFoto.click();
        });

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

        const reporteForm = document.getElementById('reporteTecnicoForm');
        const firmaCanvas = document.getElementById('firmaClienteCanvas');
        const firmaInput = document.getElementById('firmaClienteInput');
        const limpiarFirma = document.getElementById('limpiarFirmaCliente');
        const firmaEstado = document.getElementById('firmaClienteEstado');
        const firmaContexto = firmaCanvas?.getContext('2d');
        let firmaTrazada = false;
        let dibujandoFirma = false;

        if (firmaCanvas && firmaInput && firmaContexto) {
            firmaContexto.lineWidth = 5;
            firmaContexto.lineCap = 'round';
            firmaContexto.lineJoin = 'round';
            firmaContexto.strokeStyle = '#172b4d';
            firmaContexto.fillStyle = '#172b4d';

            const puntoFirma = (evento) => {
                const limites = firmaCanvas.getBoundingClientRect();
                return {
                    x: (evento.clientX - limites.left) * (firmaCanvas.width / limites.width),
                    y: (evento.clientY - limites.top) * (firmaCanvas.height / limites.height),
                };
            };

            firmaCanvas.addEventListener('pointerdown', (evento) => {
                evento.preventDefault();
                dibujandoFirma = true;
                firmaCanvas.setPointerCapture(evento.pointerId);
                const punto = puntoFirma(evento);
                firmaContexto.beginPath();
                firmaContexto.arc(punto.x, punto.y, firmaContexto.lineWidth / 2, 0, Math.PI * 2);
                firmaContexto.fill();
                firmaContexto.beginPath();
                firmaContexto.moveTo(punto.x, punto.y);
                firmaTrazada = true;
                firmaEstado.textContent = 'Firma capturada.';
            });
            firmaCanvas.addEventListener('pointermove', (evento) => {
                if (!dibujandoFirma) {
                    return;
                }
                evento.preventDefault();
                const punto = puntoFirma(evento);
                firmaContexto.lineTo(punto.x, punto.y);
                firmaContexto.stroke();
                firmaTrazada = true;
                firmaEstado.textContent = 'Firma capturada.';
            });
            const terminarTrazoFirma = () => {
                dibujandoFirma = false;
            };
            firmaCanvas.addEventListener('pointerup', terminarTrazoFirma);
            firmaCanvas.addEventListener('pointercancel', terminarTrazoFirma);
            firmaCanvas.addEventListener('lostpointercapture', terminarTrazoFirma);

            limpiarFirma?.addEventListener('click', () => {
                firmaContexto.clearRect(0, 0, firmaCanvas.width, firmaCanvas.height);
                firmaInput.value = '';
                firmaTrazada = false;
                firmaEstado.textContent = 'La firma es obligatoria.';
            });

            reporteForm?.addEventListener('submit', (evento) => {
                if (!firmaTrazada) {
                    evento.preventDefault();
                    firmaEstado.textContent = 'Pide al cliente que firme antes de enviar el reporte.';
                    firmaCanvas.focus();
                    return;
                }
                firmaInput.value = firmaCanvas.toDataURL('image/png');
            });
        }
