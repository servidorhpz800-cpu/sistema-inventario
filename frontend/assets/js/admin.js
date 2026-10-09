const inventoryAdmin = window.inventoryAdminData;
        const barcodeInputAdmin = document.getElementById('barcodeScannerInputAdmin');
        const barcodeSearchButtonAdmin = document.getElementById('barcodeSearchButtonAdmin');
        const barcodeCameraButtonAdmin = document.getElementById('barcodeCameraButtonAdmin');
        const barcodeVideoAdmin = document.getElementById('barcodeVideoAdmin');
        const barcodeResultAdmin = document.getElementById('barcodeResultAdmin');
        const serialButtonsAdmin = document.querySelectorAll('[data-scan-target]');

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function mostrarEquipoEscaneado(match) {
            if (!match) {
                barcodeResultAdmin.innerHTML = '<div class="barcode-state error">No se encontró ningún equipo con ese código.</div>';
                return;
            }

            barcodeResultAdmin.innerHTML = `
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

        function completarFormularioEquipoAdmin(match) {
            const form = document.getElementById('adminEquipmentForm');
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
                setValue('tipo', match.tipo || '');
                setValue('marca', match.marca || '');
                setValue('modelo', match.modelo || '');
                setValue('condicion', match.condicion || 'nuevo');
                setValue('estado', match.estado || 'disponible');
                setValue('ubicacion', match.ubicacion || '');
                setValue('serial', match.serial || '');
                setValue('observaciones', match.observaciones || '');
            } else {
                setValue('serial', barcodeInputAdmin ? barcodeInputAdmin.value.trim() : '');
            }
        }

        function buscarEquipoAdmin(codigo) {
            const rawCode = String(codigo ?? '').trim();
            if (!rawCode) {
                barcodeResultAdmin.innerHTML = '<div class="barcode-state error">Escribe o escanea un código para buscar un equipo.</div>';
                return;
            }

            const parsed = parseScannedValue(rawCode);
            const code = (parsed.serial || parsed.model || rawCode).trim();
            const normalized = code.toLowerCase();
            const match = inventoryAdmin.find((item) => {
                const serial = String(item.serial ?? '').trim();
                return serial.toLowerCase() === normalized || serial.toLowerCase().includes(normalized) || String(item.id) === code;
            });

            mostrarEquipoEscaneado(match);
            completarFormularioEquipoAdmin(match);
        }

        let adminCameraStream = null;
        let adminBarcodeTimer = null;

        function detenerCamaraAdmin() {
            if (adminCameraStream) {
                adminCameraStream.getTracks().forEach((track) => track.stop());
                adminCameraStream = null;
            }

            if (barcodeVideoAdmin) {
                barcodeVideoAdmin.srcObject = null;
                barcodeVideoAdmin.style.display = 'none';
            }

            if (window.Quagga && Quagga._state && Quagga._state.running) {
                Quagga.stop();
            }
        }

        async function iniciarEscaneoAdmin() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                barcodeResultAdmin.innerHTML = '<div class="barcode-state error">Este navegador no permite acceder a la cámara. Usa la IP del equipo y acepta el permiso de cámara, o escribe el código manualmente.</div>';
                return;
            }

            if ('BarcodeDetector' in window) {
                try {
                    if (adminCameraStream) {
                        adminCameraStream.getTracks().forEach((track) => track.stop());
                    }

                    adminCameraStream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'environment' },
                        audio: false,
                    });

                    if (barcodeVideoAdmin) {
                        barcodeVideoAdmin.srcObject = adminCameraStream;
                        barcodeVideoAdmin.style.display = 'block';
                    }

                    const detector = new BarcodeDetector({ formats: ['code_128', 'ean_13', 'ean_8', 'code_39', 'upc_a', 'upc_e', 'codabar'] });
                    const lectura = async () => {
                        if (!barcodeVideoAdmin || !barcodeVideoAdmin.videoWidth || !barcodeVideoAdmin.videoHeight) {
                            return;
                        }

                        try {
                            const barcodes = await detector.detect(barcodeVideoAdmin);
                            if (barcodes && barcodes.length > 0) {
                                const scanned = barcodes[0].rawValue;
                                if (scanned) {
                                    buscarEquipoAdmin(scanned);
                                    detenerCamaraAdmin();
                                    return;
                                }
                            }
                        } catch (error) {
                            console.warn('BarcodeDetector detect failed:', error);
                        }

                        adminBarcodeTimer = setTimeout(lectura, 350);
                    };

                    clearTimeout(adminBarcodeTimer);
                    lectura();
                    barcodeResultAdmin.innerHTML = '<div class="barcode-state ok">Cámara activa. Apunta al código de barras para escanearlo.</div>';
                    return;
                } catch (error) {
                    console.warn('Error BarcodeDetector admin:', error);
                }
            }

            if (window.Quagga) {
                if (barcodeVideoAdmin) {
                    barcodeVideoAdmin.style.display = 'block';
                    barcodeVideoAdmin.setAttribute('playsinline', 'true');
                    barcodeVideoAdmin.setAttribute('muted', 'true');
                }

                Quagga.offDetected();
                Quagga.stop();
                Quagga.init({
                    inputStream: {
                        name: 'Live',
                        type: 'LiveStream',
                        target: barcodeVideoAdmin,
                        constraints: { facingMode: 'environment' },
                    },
                    decoder: {
                        readers: ['code_128_reader', 'ean_reader', 'ean_8_reader', 'code_39_reader', 'upc_reader', 'codabar_reader'],
                    },
                    locate: true,
                }, function (err) {
                    if (err) {
                        barcodeResultAdmin.innerHTML = '<div class="barcode-state error">No se pudo iniciar el escáner de la cámara. Abre la página por la IP del equipo, acepta los permisos de cámara y vuelve a intentarlo. Si no, escribe el código manualmente.</div>';
                        return;
                    }

                    Quagga.start();
                    barcodeResultAdmin.innerHTML = '<div class="barcode-state ok">Cámara activa. Apunta al código de barras para escanearlo.</div>';
                });

                Quagga.onDetected((result) => {
                    const scanned = result && result.codeResult ? result.codeResult.code : null;
                    if (scanned) {
                        buscarEquipoAdmin(scanned);
                        Quagga.stop();
                        detenerCamaraAdmin();
                    }
                });
                return;
            }

            barcodeResultAdmin.innerHTML = '<div class="barcode-state error">Tu navegador no admite escaneo con cámara en esta página. Usa la IP local del equipo y permite la cámara, o escribe el código manualmente.</div>';
        }

        if (barcodeInputAdmin) {
            barcodeInputAdmin.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    buscarEquipoAdmin(barcodeInputAdmin.value);
                }
            });

            barcodeSearchButtonAdmin?.addEventListener('click', () => {
                buscarEquipoAdmin(barcodeInputAdmin.value);
            });

            barcodeCameraButtonAdmin?.addEventListener('click', () => {
                iniciarEscaneoAdmin();
            });
        }

        function activarEscaneoDirectoAdmin(targetInputId) {
            const targetInput = document.getElementById(targetInputId);
            if (!targetInput) {
                return;
            }

            const startDirectScan = async () => {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    const msg = 'Tu navegador no tiene acceso a la cámara. Usa la IP del equipo y acepta el permiso o escribe el serial manualmente.';
                    barcodeResultAdmin.innerHTML = '<div class="barcode-state error">' + escapeHtml(msg) + '</div>';
                    alert(msg);
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
                        const existing = document.getElementById('directScannerHolderAdmin');
                        if (existing) {
                            existing.replaceWith(container);
                        } else {
                            const formCard = document.querySelector('.card');
                            if (formCard) {
                                formCard.appendChild(container);
                            }
                        }
                        container.id = 'directScannerHolderAdmin';

                        const detector = new BarcodeDetector({ formats: ['code_128', 'ean_13', 'ean_8', 'code_39', 'upc_a', 'upc_e', 'codabar'] });
                        const read = async () => {
                            try {
                                const barcodes = await detector.detect(preview);
                                if (barcodes && barcodes.length > 0) {
                                    const value = barcodes[0].rawValue;
                                    if (value) {
                                        const parsedValue = aplicarCodigoEscaneadoAdmin(value, targetInput);
                                        targetInput.value = parsedValue;
                                        targetInput.dispatchEvent(new Event('input', { bubbles: true }));
                                        stream.getTracks().forEach((track) => track.stop());
                                        container.remove();
                                        return;
                                    }
                                }
                            } catch (error) {
                                console.warn('Direct scan failed admin:', error);
                            }
                            setTimeout(read, 350);
                        };
                        read();
                        return;
                    } catch (error) {
                        console.warn('Direct camera error admin:', error);
                    }
                }

                const msg = 'La cámara no está disponible en este navegador. Abre la página con la IP del equipo y acepta el permiso, o escribe el serial manualmente.';
                barcodeResultAdmin.innerHTML = '<div class="barcode-state error">' + escapeHtml(msg) + '</div>';
                alert(msg);
            };

            startDirectScan();
        }

        serialButtonsAdmin.forEach((button) => {
            button.addEventListener('click', () => {
                const targetId = button.getAttribute('data-scan-target');
                if (targetId) {
                    activarEscaneoDirectoAdmin(targetId);
                }
            });
        });

        const serialFileTriggersAdmin = document.querySelectorAll('[data-scan-file-trigger]');
        const serialFileInputsAdmin = document.querySelectorAll('[data-scan-file-target]');

        async function leerCodigoDesdeArchivoAdmin(file, targetInput) {
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
                            barcodeResultAdmin.innerHTML = `<div class="barcode-state ok">Código leído desde la foto: <strong>${escapeHtml(value)}</strong></div>`;
                            return;
                        }
                    } catch (error) {
                        console.warn('ZXing image decode failed admin:', error);
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
                            barcodeResultAdmin.innerHTML = `<div class="barcode-state ok">Código leído desde la foto: <strong>${escapeHtml(value)}</strong></div>`;
                            return;
                        }
                    }
                }

                if (window.Quagga) {
                    const url = URL.createObjectURL(file);
                    const resultado = await new Promise((resolve) => {
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
                            resolve(null);
                        });
                    });

                    if (resultado) {
                        targetInput.value = resultado;
                        targetInput.dispatchEvent(new Event('input', { bubbles: true }));
                        barcodeResultAdmin.innerHTML = `<div class="barcode-state ok">Código leído desde la foto: <strong>${escapeHtml(resultado)}</strong></div>`;
                        return;
                    }
                }

                alert(mensajeManual);
            } catch (error) {
                console.warn('File scan failed admin:', error);
                alert(mensajeManual);
            }
        }

        serialFileTriggersAdmin.forEach((button) => {
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

        serialFileInputsAdmin.forEach((fileInput) => {
            fileInput.addEventListener('change', async (event) => {
                const targetId = fileInput.getAttribute('data-scan-file-target');
                const targetInput = document.getElementById(targetId);
                const file = event.target.files && event.target.files[0];
                if (file && targetInput) {
                    await leerCodigoDesdeArchivoAdmin(file, targetInput);
                }
                fileInput.value = '';
            });
        });

        const adminTabs = document.querySelectorAll('.admin-tabs a');
        adminTabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                adminTabs.forEach((item) => item.classList.remove('active'));
                tab.classList.add('active');
            });
        });

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
