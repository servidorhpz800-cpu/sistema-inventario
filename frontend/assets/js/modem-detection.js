(() => {
    const fabricantes = [
        { prefix: '48575443', marca: 'Huawei' },
        { prefix: 'TLPK', marca: 'TP-Link' },
        { prefix: 'HWTC', marca: 'Telmex' },
        { prefix: 'ALCL', marca: 'Nokia' },
        { prefix: 'V23', marca: 'V-SOL' },
        { prefix: 'VSOL', marca: 'V-SOL' },
        { prefix: 'GPON', marca: 'V-SOL' },
    ];

    const normalizar = (valor) => String(valor || '').toUpperCase().replace(/[^A-Z0-9]/g, '');

    function detectarFabricante(serial) {
        const codigo = normalizar(serial);
        return fabricantes.find(({ prefix }) => codigo.startsWith(prefix)) || null;
    }

    function actualizarCampoAuto(campo, valor) {
        if (!campo) {
            return;
        }
        campo.value = valor;
        campo.dataset.modemAutofilled = 'true';
    }

    document.querySelectorAll('[data-modem-serial]').forEach((serialInput) => {
        const form = serialInput.closest('form');
        const marcaInput = form?.querySelector('[name="marca"]');
        const tipoSelect = form?.querySelector('[name="tipo"]');
        const resultado = form?.querySelector('[data-modem-detection-result]');

        marcaInput?.addEventListener('input', () => {
            marcaInput.dataset.modemAutofilled = 'false';
        });
        marcaInput?.addEventListener('change', () => {
            marcaInput.dataset.modemAutofilled = 'false';
        });
        tipoSelect?.addEventListener('change', () => {
            tipoSelect.dataset.modemAutofilled = 'false';
        });

        const detectar = (mostrarDesconocido = false) => {
            const codigo = serialInput.value.trim();
            if (!codigo) {
                return;
            }

            const fabricante = detectarFabricante(codigo);
            if (!fabricante) {
                if (marcaInput?.dataset.modemAutofilled === 'true') {
                    marcaInput.value = '';
                    marcaInput.dataset.modemAutofilled = 'false';
                }
                if (tipoSelect?.dataset.modemAutofilled === 'true') {
                    tipoSelect.value = '';
                    tipoSelect.dataset.modemAutofilled = 'false';
                }
                if (mostrarDesconocido && resultado) {
                    resultado.textContent = 'No se reconoció el prefijo del serial. Selecciona el fabricante manualmente.';
                    resultado.dataset.state = 'unknown';
                }
                return;
            }

            actualizarCampoAuto(marcaInput, fabricante.marca);
            if (tipoSelect && Array.from(tipoSelect.options).some((option) => option.value === 'Modem GPON')) {
                actualizarCampoAuto(tipoSelect, 'Modem GPON');
            }
            if (resultado) {
                resultado.textContent = `Fabricante detectado: ${fabricante.marca} · Prefijo ${fabricante.prefix}`;
                resultado.dataset.state = 'detected';
            }
        };

        serialInput.addEventListener('input', () => detectar());
        serialInput.addEventListener('change', () => detectar(true));
        serialInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                detectar(true);
            }
        });
    });
})();
