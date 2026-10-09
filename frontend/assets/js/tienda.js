const inventory = window.inventoryData;
        const barcodeInput = document.getElementById('barcodeScannerInput');
        const barcodeSearchButton = document.getElementById('barcodeSearchButton');
        const barcodeCameraButton = document.getElementById('barcodeCameraButton');
        const barcodeVideo = document.getElementById('barcodeVideo');
        const barcodeResult = document.getElementById('barcodeResult')
            || document.querySelector('#stockEquipmentForm [data-modem-detection-result]');
        const dispatchSelect = document.querySelector('select[name="despacho_equipo_id"]');
        const serialButtons = document.querySelectorAll('[data-scan-target]');
        const serialFileTriggers = document.querySelectorAll('[data-scan-file-trigger]');
        const serialFileInputs = document.querySelectorAll('[data-scan-file-target]');

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function parseScannedValue(rawValue) {
            const text = String(rawValue ?? '').trim();
            if (!text) {
                return { model: '', serial: '' };
            }

            const matchModelSerial = text.match(/(?:MODEL(?:O)?|MODELO)?\s*[:\-]?\s*([A-Za-z0-9\-/]+)\s*(?:[|,;]|\s+)?(?:SN|S\/N|sn)\s*[:\-]?\s*([A-Za-z0-9\-/]+)/i);
            if (matchModelSerial) {
                return {
                    model: (matchModelSerial[1] || '').trim(),
                    serial: (matchModelSerial[2] || '').trim(),
                };
            }

            const matchSerialOnly = text.match(/(?:SN|S\/N|sn)\s*[:\-]?\s*([A-Za-z0-9\-/]+)/i);
            if (matchSerialOnly) {
                return {
                    model: '',
                    serial: (matchSerialOnly[1] || '').trim(),
                };
            }

            const matchModelOnly = text.match(/(?:MODEL(?:O)?|MODELO)\s*[:\-]?\s*([A-Za-z0-9\-/]+)/i);
            if (matchModelOnly) {
                return {
                    model: (matchModelOnly[1] || '').trim(),
                    serial: '',
                };
            }

            return { model: '', serial: '' };
        }

        function aplicarCodigoEscaneado(rawValue, targetInput) {
            const parsed = parseScannedValue(rawValue);
            const serialValue = parsed.serial || rawValue;
            const modelValue = parsed.model || '';

            if (targetInput) {
                const form = targetInput.closest('form');
                const serialField = form ? form.querySelector('[name="serial"]') : null;
                const modelField = form ? form.querySelector('[name="modelo"]') : null;

                if (parsed.serial && serialField) {
                    serialField.value = parsed.serial;
                    serialField.dispatchEvent(new Event('input', { bubbles: true }));
                } else if (targetInput) {
                    targetInput.value = serialValue;
                    targetInput.dispatchEvent(new Event('input', { bubbles: true }));
                }

                if (parsed.model && modelField) {
                    modelField.value = parsed.model;
                }

                if (parsed.serial || parsed.model) {
                    const detalle = [parsed.model ? `modelo: ${parsed.model}` : null, parsed.serial ? `SN: ${parsed.serial}` : null].filter(Boolean).join(' · ');
                    showBarcodeResult(`Código leído: <strong>${escapeHtml(detalle)}</strong>`, false);
                }
            }

            return serialValue;
        }

        function showBarcodeResult(message, isError = false) {
            barcodeResult.innerHTML = `<div class="barcode-state ${isError ? 'error' : 'ok'}">${message}</div>`;
        }

        function buildMatchMarkup(match) {
            if (!match) {
                return '<div class="barcode-state error">No se encontró ningún equipo con ese código.</div>';
            }

            return `
                <div class="barcode-card">
                    <div class="barcode-header">
                        <strong>${escapeHtml(match.tipo || 'Equipo')}</strong>
                        <span class="badge ${match.estado === 'disponible' ? 'success' : 'primary'}">${escapeHtml(String(match.estado || 'sin estado').replace(/_/g, ' '))}</span>
                    </div>
                    <div class="barcode-grid">
                        <div><span>Marca</span><strong>${escapeHtml(match.marca || 'Sin marca')}</strong></div>
                        <div><span>Modelo</span><strong>${escapeHtml(match.modelo || 'Sin modelo')}</strong></div>
                        <div><span>Serial</span><strong>${escapeHtml(match.serial || 'Sin serial')}</strong></div>
                        <div><span>Condición</span><strong>${escapeHtml(String(match.condicion || 'nuevo').replace(/_/g, ' '))}</strong></div>
                        <div><span>Ubicación</span><strong>${escapeHtml(match.ubicacion || 'Sin ubicación')}</strong></div>
                        <div><span>Observaciones</span><strong>${escapeHtml(match.observaciones || 'Sin observaciones')}</strong></div>
                    </div>
                </div>
            `;
        }

        function completarFormularioEquipo(match) {
            const form = document.getElementById('stockEquipmentForm');
            if (!form) {
                return;
            }

            const setValue = (name, value) => {
                const field = form.querySelector(`[name="${name}"]`);
                if (field) {
                    field.value = value ?? '';
                }
            };

            if (match) {
                const tipo = String(match.tipo || '');
                const marca = String(match.marca || '');
                const modelo = String(match.modelo || '');
                const serial = String(match.serial || '');
                const condicion = String(match.condicion || 'nuevo');
                const ubicacion = String(match.ubicacion || 'Tienda');
                const observaciones = String(match.observaciones || '');

                setValue('tipo', tipo);
                setValue('marca', marca);
                setValue('modelo', modelo);
                setValue('serial', serial);
                setValue('condicion', condicion);
                setValue('ubicacion', ubicacion);
                setValue('observaciones', observaciones);
            } else {
                setValue('serial', barcodeInput ? barcodeInput.value.trim() : '');
            }
        }

        function buscarEquipoPorCodigo(codigo) {
            const rawCode = String(codigo ?? '').trim();
            if (!rawCode) {
                showBarcodeResult('Escribe o escanea un código para buscar un equipo.', true);
                return;
            }

            const parsed = parseScannedValue(rawCode);
            const code = (parsed.serial || parsed.model || rawCode).trim();
            const normalized = code.toLowerCase();
            const match = inventory.find((item) => {
                const serial = String(item.serial ?? '').trim();
                return serial.toLowerCase() === normalized || serial.toLowerCase().includes(normalized) || String(item.id) === code;
            });

            if (!match) {
                showBarcodeResult(`No se encontró ningún equipo con el código <strong>${escapeHtml(code)}</strong>.`, true);
                completarFormularioEquipo(null);
                return;
            }

            if (dispatchSelect) {
                dispatchSelect.value = String(match.id);
            }

            barcodeResult.innerHTML = buildMatchMarkup(match);
            completarFormularioEquipo(match);
            if (barcodeInput) {
                barcodeInput.value = code;
            }
        }

        let cameraStream = null;
        let barcodeScannerTimer = null;

        function detenerCamara() {
            if (cameraStream) {
                cameraStream.getTracks().forEach((track) => track.stop());
                cameraStream = null;
            }

            if (barcodeVideo) {
                barcodeVideo.srcObject = null;
                barcodeVideo.style.display = 'none';
            }

            if (window.Quagga && Quagga._state && Quagga._state.running) {
                Quagga.stop();
            }
        }

        async function iniciarEscaneoCamara() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                showBarcodeResult('Este navegador no permite acceder a la cámara. Puedes escribir el código manualmente o abrir la página con la IP del equipo y permitir cámara.', true);
                return;
            }

            if ('BarcodeDetector' in window) {
                try {
                    if (cameraStream) {
                        cameraStream.getTracks().forEach((track) => track.stop());
                    }

                    cameraStream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'environment' },
                        audio: false,
                    });

                    if (barcodeVideo) {
                        barcodeVideo.srcObject = cameraStream;
                        barcodeVideo.style.display = 'block';
                    }

                    const detector = new BarcodeDetector({ formats: ['code_128', 'ean_13', 'ean_8', 'code_39', 'upc_a', 'upc_e', 'codabar'] });
                    const lectura = async () => {
                        if (!barcodeVideo || !barcodeVideo.videoWidth || !barcodeVideo.videoHeight) {
                            return;
                        }

                        try {
                            const barcodes = await detector.detect(barcodeVideo);
                            if (barcodes && barcodes.length > 0) {
                                const scanned = barcodes[0].rawValue;
                                if (scanned) {
                                    buscarEquipoPorCodigo(scanned);
                                    detenerCamara();
                                    return;
                                }
                            }
                        } catch (error) {
                            console.warn('BarcodeDetector detect failed:', error);
                        }

                        barcodeScannerTimer = setTimeout(lectura, 350);
                    };

                    clearTimeout(barcodeScannerTimer);
                    lectura();
                    showBarcodeResult('Cámara activa. Apunta al código de barras para escanearlo.', false);
                    return;
                } catch (error) {
                    console.warn('Error BarcodeDetector:', error);
                }
            }

            if (window.Quagga) {
                if (barcodeVideo) {
                    barcodeVideo.style.display = 'block';
                    barcodeVideo.setAttribute('playsinline', 'true');
                    barcodeVideo.setAttribute('muted', 'true');
                }

                Quagga.offDetected();
                Quagga.stop();
                Quagga.init({
                    inputStream: {
                        name: 'Live',
                        type: 'LiveStream',
                        target: barcodeVideo,
                        constraints: { facingMode: 'environment' },
                    },
                    decoder: {
                        readers: ['code_128_reader', 'ean_reader', 'ean_8_reader', 'code_39_reader', 'upc_reader', 'codabar_reader'],
                    },
                    locate: true,
                }, function (err) {
                    if (err) {
                        showBarcodeResult('No se pudo iniciar el escáner de la cámara. Abre la página por la IP del equipo, acepta los permisos de cámara y vuelve a intentarlo. Si no, escribe el código manualmente.', true);
                        return;
                    }

                    Quagga.start();
                    showBarcodeResult('Cámara activa. Apunta al código de barras para escanearlo.', false);
                });

                Quagga.onDetected((result) => {
                    const scanned = result && result.codeResult ? result.codeResult.code : null;
                    if (scanned) {
                        buscarEquipoPorCodigo(scanned);
                        Quagga.stop();
                        detenerCamara();
                    }
                });
                return;
            }

            showBarcodeResult('Tu navegador no admite escaneo con cámara en esta página. Usa la IP local del equipo y permite la cámara, o escribe el código manualmente.', true);
        }

        if (barcodeInput) {
            barcodeInput.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    buscarEquipoPorCodigo(barcodeInput.value);
                }
            });

            barcodeSearchButton?.addEventListener('click', () => {
                buscarEquipoPorCodigo(barcodeInput.value);
            });

            barcodeCameraButton?.addEventListener('click', () => {
                iniciarEscaneoCamara();
            });
        }

        function activarEscaneoDirecto(targetInputId) {
            const targetInput = document.getElementById(targetInputId);
            if (!targetInput) {
                return;
            }

            const startDirectScan = async () => {
                const permissionMessage = 'La cámara está bloqueada por el navegador. Abre la página con la IP del equipo (por ejemplo http://192.168.100.18/compuser), acepta el permiso de cámara y recarga la página. Si no puedes, escribe el serial manualmente.';

                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    const msg = 'Tu navegador no tiene acceso a la cámara. Usa la IP del equipo y acepta el permiso o escribe el serial manualmente.';
                    showBarcodeResult(msg, true);
                    alert(permissionMessage);
                    return;
                }

                if ('BarcodeDetector' in window) {
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
                        const preview = document.createElement('video');
                        preview.srcObject = stream;
                        preview.autoplay = true;
                        preview.playsInline = true;
                        preview.muted = true;
                        preview.style.display = 'block';
                        preview.style.width = '100%';
                        preview.style.maxWidth = '420px';
                        preview.style.borderRadius = '12px';
                        preview.style.marginTop = '12px';
                        preview.style.border = '1px solid rgba(37,87,214,0.2)';

                        const container = document.createElement('div');
                        container.appendChild(preview);
                        const existing = document.getElementById('directScannerHolder');
                        if (existing) {
                            existing.replaceWith(container);
                        } else {
                            const formCard = document.querySelector('.card');
                            if (formCard) {
                                formCard.appendChild(container);
                            }
                        }
                        container.id = 'directScannerHolder';

                        const detector = new BarcodeDetector({ formats: ['code_128', 'ean_13', 'ean_8', 'code_39', 'upc_a', 'upc_e', 'codabar'] });
                        const read = async () => {
                            try {
                                const barcodes = await detector.detect(preview);
                                if (barcodes && barcodes.length > 0) {
                                    const value = barcodes[0].rawValue;
                                    if (value) {
                                        const parsedValue = aplicarCodigoEscaneado(value, targetInput);
                                        targetInput.value = parsedValue;
                                        targetInput.dispatchEvent(new Event('input', { bubbles: true }));
                                        stream.getTracks().forEach((track) => track.stop());
                                        container.remove();
                                        return;
                                    }
                                }
                            } catch (error) {
                                console.warn('Direct scan failed:', error);
                            }
                            setTimeout(read, 350);
                        };
                        read();
                        return;
                    } catch (error) {
                        console.warn('Direct camera error:', error);
                        showBarcodeResult(permissionMessage, true);
                        alert(permissionMessage);
                    }
                }

                const fallback = 'La cámara no está disponible en este navegador. Usa la IP del equipo y acepta el permiso, o escribe el serial manualmente.';
                showBarcodeResult(fallback, true);
                alert(permissionMessage);
            };

            startDirectScan();
        }

        async function leerCodigoDesdeArchivo(file, targetInput) {
            if (!file || !targetInput) {
                return;
            }

            const mensajeManual = 'No se pudo leer el código en la imagen. Prueba con otra foto, usa el código del producto o escribe el serial manualmente.';

            try {
                if (window.ZXing && typeof window.ZXing.BrowserMultiFormatReader === 'function') {
                    const codeReader = new window.ZXing.BrowserMultiFormatReader();
                    const url = URL.createObjectURL(file);
                    try {
                        const result = await codeReader.decodeFromImage(undefined, url);
                        const value = result && result.getText ? result.getText() : null;
                        if (value) {
                            targetInput.value = value;
                            targetInput.dispatchEvent(new Event('input', { bubbles: true }));
                            showBarcodeResult(`Código leído desde la foto: <strong>${escapeHtml(value)}</strong>`, false);
                            return;
                        }
                    } catch (error) {
                        console.warn('ZXing image decode failed:', error);
                    } finally {
                        URL.revokeObjectURL(url);
                    }
                }

                if ('BarcodeDetector' in window) {
                    const bitmap = await createImageBitmap(file);
                    const detector = new BarcodeDetector({ formats: ['code_128', 'ean_13', 'ean_8', 'code_39', 'upc_a', 'upc_e', 'codabar'] });
                    const barcodes = await detector.detect(bitmap);
                    if (barcodes && barcodes.length > 0) {
                        const value = barcodes[0].rawValue;
                        if (value) {
                            targetInput.value = value;
                            targetInput.dispatchEvent(new Event('input', { bubbles: true }));
                            showBarcodeResult(`Código leído desde la foto: <strong>${escapeHtml(value)}</strong>`, false);
                            return;
                        }
                    }
                }

                if (window.Quagga) {
                    const url = URL.createObjectURL(file);
                    const resultado = await new Promise((resolve, reject) => {
                        Quagga.decodeSingle({
                            decoder: {
                                readers: ['code_128_reader', 'ean_reader', 'ean_8_reader', 'code_39_reader', 'upc_reader', 'codabar_reader'],
                            },
                            locate: true,
                            src: url,
                            numOfWorkers: 0,
                        }, function (result) {
                            URL.revokeObjectURL(url);
                            if (result && result.codeResult && result.codeResult.code) {
                                resolve(result.codeResult.code);
                                return;
                            }
                            reject(new Error('No se encontró un código en la imagen.'));
                        });
                    }).catch(() => null);

                    if (resultado) {
                        targetInput.value = resultado;
                        targetInput.dispatchEvent(new Event('input', { bubbles: true }));
                        showBarcodeResult(`Código leído desde la foto: <strong>${escapeHtml(resultado)}</strong>`, false);
                        return;
                    }
                }

                alert(mensajeManual);
            } catch (error) {
                console.warn('File scan failed:', error);
                alert(mensajeManual);
            }
        }

        serialButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const targetId = button.getAttribute('data-scan-target');
                if (targetId) {
                    activarEscaneoDirecto(targetId);
                }
            });
        });

        serialFileTriggers.forEach((button) => {
            button.addEventListener('click', () => {
                const targetId = button.getAttribute('data-scan-file-trigger');
                if (!targetId) {
                    return;
                }

                const targetInput = document.getElementById(targetId);
                const fileInput = document.querySelector(`[data-scan-file-target="${targetId}"]`);
                if (fileInput) {
                    fileInput.click();
                }
                if (targetInput) {
                    targetInput.focus();
                }
            });
        });

        serialFileInputs.forEach((fileInput) => {
            fileInput.addEventListener('change', async (event) => {
                const targetId = fileInput.getAttribute('data-scan-file-target');
                const targetInput = document.getElementById(targetId);
                const file = event.target.files && event.target.files[0];
                if (file && targetInput) {
                    await leerCodigoDesdeArchivo(file, targetInput);
                }
                fileInput.value = '';
            });
        });

        const tipoOrden = document.getElementById('tipoOrden');
        const clienteRegistrado = document.getElementById('clienteRegistrado');
        const sections = document.querySelectorAll('[data-order-section]');
        const fields = document.querySelectorAll('[data-order-section] input, [data-order-section] textarea');

        function actualizarFormularioOrden() {
            const necesitaCliente = true;
            const necesitaUbicacion = true;

            sections.forEach((section) => {
                const name = section.dataset.orderSection;
                section.hidden = (name === 'cliente' && !necesitaCliente) || (name === 'ubicacion' && !necesitaUbicacion);
            });

            fields.forEach((field) => {
                const section = field.closest('[data-order-section]');
                field.required = !section.hidden && (field.name === 'cliente_nombre' || field.name === 'cliente_numero' || field.name === 'calle' || field.name === 'ciudad');
            });
        }

        if (tipoOrden) {
            tipoOrden.addEventListener('change', actualizarFormularioOrden);
            actualizarFormularioOrden();
        }

        clienteRegistrado?.addEventListener('change', () => {
            const option = clienteRegistrado.selectedOptions[0];
            const form = clienteRegistrado.closest('form');
            if (!option || !form) {
                return;
            }

            for (const [fieldName, dataName] of Object.entries({
                cliente_nombre: 'nombre',
                cliente_numero: 'numero',
                calle: 'calle',
                numero_exterior: 'numeroExterior',
                colonia: 'colonia',
                ciudad: 'ciudad',
                referencias: 'referencias',
                ubicacion_url: 'ubicacionUrl',
            })) {
                const field = form.querySelector(`[name="${fieldName}"]`);
                if (field) {
                    field.value = option.value === '0' ? '' : (option.dataset[dataName] || '');
                }
            }
        });

        const buscarCliente = document.getElementById('buscarCliente');
        const filasClientes = Array.from(document.querySelectorAll('#tablaClientes tr[data-client-search]'));
        const sinClientesFiltrados = document.getElementById('sinClientesFiltrados');
        buscarCliente?.addEventListener('input', () => {
            const termino = buscarCliente.value.trim().toLocaleLowerCase();
            let visibles = 0;
            filasClientes.forEach((fila) => {
                const coincide = fila.dataset.clientSearch.includes(termino);
                fila.hidden = !coincide;
                visibles += coincide ? 1 : 0;
            });
            if (sinClientesFiltrados) {
                sinClientesFiltrados.hidden = visibles > 0 || filasClientes.length === 0;
            }
        });

        const filtroOrdenes = document.getElementById('filtroOrdenes');
        if (filtroOrdenes) {
            filtroOrdenes.addEventListener('change', () => {
                document.querySelectorAll('.order-item').forEach((order) => {
                    order.hidden = filtroOrdenes.value !== 'todos' && order.dataset.orderType !== filtroOrdenes.value;
                });
            });
        }

        const availableEquipmentToggle = document.getElementById('toggleAvailableEquipment');
        const availableEquipmentPanel = document.getElementById('availableEquipmentPanel');
        if (availableEquipmentToggle && availableEquipmentPanel) {
            availableEquipmentToggle.addEventListener('click', () => {
                const isOpen = availableEquipmentToggle.getAttribute('aria-expanded') === 'true';
                availableEquipmentToggle.setAttribute('aria-expanded', String(!isOpen));
                availableEquipmentPanel.hidden = isOpen;
                if (!isOpen) {
                    availableEquipmentPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        }
