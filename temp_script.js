
function descargarReporteBusqueda() {
    Swal.fire({
        title: 'Selecciona las tablas',
        html: `
            <div style="text-align: left; margin: 15px auto; width: fit-content; display: flex; flex-direction: column; gap: 10px;">
                <label style="cursor: pointer;"><input type="checkbox" id="rep_egresos" value="egreso_realizado" checked style="margin-right: 8px;"> Egresos Realizados</label>
                <label style="cursor: pointer;"><input type="checkbox" id="rep_otros" value="otros_egresos" checked style="margin-right: 8px;"> Otros Egresos (Avances y Cambios)</label>
                <label style="cursor: pointer;"><input type="checkbox" id="rep_traslados" value="traslados" checked style="margin-right: 8px;"> Traslados</label>
                <label style="cursor: pointer;"><input type="checkbox" id="rep_divisas" value="egreso_divisas" checked style="margin-right: 8px;"> Egresos Divisas</label>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Generar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            let selected = [];
            if(document.getElementById('rep_egresos').checked) selected.push('egreso_realizado');
            if(document.getElementById('rep_otros').checked) selected.push('otros_egresos');
            if(document.getElementById('rep_traslados').checked) selected.push('traslados');
            if(document.getElementById('rep_divisas').checked) selected.push('egreso_divisas');
            return selected;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const fDesde = document.getElementById('fecha_desde_input')?.value || '';
            const fHasta = document.getElementById('fecha_hasta_input')?.value || '';
            const txt = document.getElementById('filtro-texto')?.value || '';
            
            let selectedCats = result.value;
            if (selectedCats.length === 0) {
                Swal.fire('Atención', 'Debes seleccionar al menos una tabla', 'warning');
                return;
            }

            let url = '{{ route("finanzas.flujo_caja.reporte") }}?desde=' + encodeURIComponent(fDesde) + '&hasta=' + encodeURIComponent(fHasta);
            if(txt) url += '&q=' + encodeURIComponent(txt);
            url += '&cats=' + encodeURIComponent(selectedCats.join(','));
            
            window.open(url, '_blank');
        }
    });
}
function calcTraslado() {
    const bs = window.parseLocalNumber(document.getElementById('monto_bs')?.value) || 0;
    const bcvInput = document.querySelector('input[data-field="tasa_bcv_usd"]');
    const tasa = window.parseLocalNumber(bcvInput?.value) || 0;
    const usdEl = document.getElementById('monto_usd_traslado');
    if (!usdEl) return;
    if (tasa > 0 && bs > 0) {
        usdEl.value = (bs / tasa).toFixed(2).replace('.', ',');
    } else {
        usdEl.value = '';
    }
}

function toggleTraslados() {
    const val = document.getElementById('categoria_egreso').value;
    const isTraslado = val === 'traslados';
    const isDivisas = val === 'egreso_divisas';
    
    document.getElementById('row_receptor').style.display = isTraslado ? 'flex' : 'none';
    document.getElementById('banco_titular_receptor').required = isTraslado;
    
    document.getElementById('lbl_banco_titular').innerText = isTraslado ? 'Banco Emisor y Titular Emisor' : 'Banco y Titular';
    document.getElementById('lbl_monto_bs').innerText = isTraslado ? 'Monto BS' : 'Monto BS';
    
    document.getElementById('col_monto_usd').style.display = isTraslado ? 'none' : 'block';
    document.getElementById('col_tasa_cambio').style.display = (isTraslado || isDivisas) ? 'none' : 'block';
    document.getElementById('col_monto_bs').style.display = isDivisas ? 'none' : 'block';
    
    document.getElementById('row_diferencial').style.display = (isTraslado || isDivisas) ? 'none' : 'flex';
    document.getElementById('row_tipo_gasto').style.display = isTraslado ? 'none' : 'block';
    document.getElementById('row_traslado_extra').style.display = isTraslado ? 'flex' : 'none';
    
    // Disable fields that would create duplicate POST keys
    const comisionNormal = document.getElementById('comision');
    const comisionTraslado = document.getElementById('comision_traslado');
    const usdNormal = document.getElementById('monto_usd');
    const usdTraslado = document.getElementById('monto_usd_traslado');
    if (comisionNormal) comisionNormal.disabled = (isTraslado || isDivisas);
    if (comisionTraslado) comisionTraslado.disabled = !isTraslado;
    if (usdNormal) usdNormal.disabled = isTraslado;
    if (usdTraslado) usdTraslado.disabled = !isTraslado;
    
    const bsNormal = document.getElementById('monto_bs');
    if (bsNormal) bsNormal.disabled = isDivisas;
    
    // When switching to traslado mode, auto-calc USD if monto_bs already has value
    if (isTraslado) calcTraslado();
    
    document.getElementById('tipo_gasto').required = !isTraslado;
}



document.addEventListener('DOMContentLoaded', function() {

    // Initialize TomSelect for tipo_gasto
    const srcTG = document.getElementById('tipo_gasto');
    const dstTG = document.getElementById('edit_tipo_gasto');
    if (srcTG && dstTG) {
        dstTG.innerHTML = srcTG.innerHTML;
    }
    const tsSettings = {
        create: false,
        sortField: { field: "text", direction: "asc" },
        placeholder: '-- Seleccione un tipo de gasto --',
        maxOptions: null
    };
    if (srcTG) window.tsTipoGasto = new TomSelect("#tipo_gasto", tsSettings);
    if (dstTG) window.tsEditTipoGasto = new TomSelect("#edit_tipo_gasto", tsSettings);

    const tsBankSettings = {
        create: false,
        sortField: { field: "text", direction: "asc" },
        placeholder: '-- Seleccione --',
        maxOptions: null
    };
    if (document.getElementById('banco_titular')) window.tsBancoTitular = new TomSelect("#banco_titular", tsBankSettings);
    if (document.getElementById('banco_titular_receptor')) window.tsBancoTitularReceptor = new TomSelect("#banco_titular_receptor", tsBankSettings);
    if (document.getElementById('beneficiario')) window.tsBeneficiario = new TomSelect("#beneficiario", tsBankSettings);

    // Modal functions
    window.openNuevoEgresoModal = function() {
        document.getElementById('nuevoEgresoModal').style.display = 'flex';
    };
    window.closeNuevoEgresoModal = function() {
        document.getElementById('nuevoEgresoModal').style.display = 'none';
        // Reset multi-comprobante
        multiFiles = [];
        renderCompGrid();
    };

    // ===== MULTI-COMPROBANTE (máx 6) =====
    const MAX_COMP = 6;
    let multiFiles = []; // array of File objects

    window.pasteAreaDisabled = function() {
        return multiFiles.length >= MAX_COMP;
    };

    function renderCompGrid() {
        const grid = document.getElementById('comp-grid');
        const counter = document.getElementById('comp-counter');
        const pasteArea = document.getElementById('paste-area');
        grid.innerHTML = '';
        multiFiles.forEach((f, i) => {
            const wrap = document.createElement('div');
            wrap.style = 'position:relative; width:80px; height:80px;';
            const img = document.createElement('img');
            img.style = 'width:80px; height:80px; object-fit:cover; border-radius:6px; border:1px solid #cbd5e1;';
            if (f.type.startsWith('image/')) {
                const url = URL.createObjectURL(f);
                img.src = url;
            } else {
                img.src = '';
                img.alt = '📄';
                img.style.background = '#f1f5f9';
                wrap.title = f.name;
            }
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.innerHTML = '&times;';
            btn.style = 'position:absolute; top:-6px; right:-6px; width:20px; height:20px; background:#ef4444; color:#fff; border:none; border-radius:50%; cursor:pointer; font-size:13px; line-height:1; display:flex; align-items:center; justify-content:center;';
            btn.onclick = () => { multiFiles.splice(i, 1); syncFileInput(); renderCompGrid(); };
            wrap.appendChild(img);
            wrap.appendChild(btn);
            grid.appendChild(wrap);
        });
        counter.textContent = `${multiFiles.length} / ${MAX_COMP}`;
        const full = multiFiles.length >= MAX_COMP;
        pasteArea.style.opacity = full ? '0.45' : '1';
        pasteArea.style.pointerEvents = full ? 'none' : 'auto';
        document.getElementById('paste-text').textContent = full ? `Límite de ${MAX_COMP} soportes alcanzado` : 'Haz clic o pega (Ctrl+V) para añadir imagen • máx. 6';
    }

    function addFilesToMulti(files) {
        const remaining = MAX_COMP - multiFiles.length;
        const toAdd = Array.from(files).slice(0, remaining);
        toAdd.forEach(f => multiFiles.push(f));
        syncFileInput();
        renderCompGrid();
    }

    function syncFileInput() {
        const dt = new DataTransfer();
        multiFiles.forEach(f => dt.items.add(f));
        document.getElementById('comprobante-input').files = dt.files;
    }

    const pasteArea = document.getElementById('paste-area');
    const fileInput = document.getElementById('comprobante-input');

    pasteArea.addEventListener('click', () => {
        if (!pasteAreaDisabled()) fileInput.click();
    });

    fileInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) addFilesToMulti(this.files);
        this.value = ''; // reset so same file can be re-selected
    });

    document.getElementById('nuevoEgresoModal').addEventListener('paste', (e) => {
        const items = (e.clipboardData || e.originalEvent.clipboardData)?.items;
        if (!items) return;
        for (let i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                const file = items[i].getAsFile();
                if (file) { addFilesToMulti([file]); break; }
            }
        }
    });

    renderCompGrid(); // init counter

    // Calculadora Egreso
    const usdInput = document.getElementById('monto_usd');
    const tasaInput = document.getElementById('tasa_cambio');
    const bsInput = document.getElementById('monto_bs');
    const difInput = document.querySelector('input[name="diferencial_cambiario"]');

    let lastEditedAmount = 'usd';

    function calcular() {
        const usd = window.parseLocalNumber(usdInput.value) || 0;
        const bs = window.parseLocalNumber(bsInput.value) || 0;
        const tasa = window.parseLocalNumber(tasaInput.value) || 0;
        
        if (tasa > 0) {
            if (lastEditedAmount === 'usd') {
                bsInput.value = (usd * tasa).toFixed(2).replace(".", ",");
            } else if (lastEditedAmount === 'bs') {
                usdInput.value = (bs / tasa).toFixed(2).replace(".", ",");
            }
        }
        
        const bcvTasaInput = document.querySelector('input[data-field="tasa_bcv_usd"]');
        if (difInput && bcvTasaInput) {
            const bcv = window.parseLocalNumber(bcvTasaInput.value) || 1;
            const finalUsd = window.parseLocalNumber(usdInput.value) || 0;
            const finalBs = window.parseLocalNumber(bsInput.value) || 0;
            if (bcv > 0) {
                difInput.value = (((finalUsd * bcv) - finalBs) / bcv).toFixed(2).replace(".", ",");
            }
        }
    }

    usdInput.addEventListener('input', function() {
        lastEditedAmount = 'usd';
        calcular();
    });
    
    bsInput.addEventListener('input', function() {
        lastEditedAmount = 'bs';
        calcular();
    });
    
    tasaInput.addEventListener('input', calcular);

    // Auto-calcular USD del traslado cuando cambia monto_bs
    bsInput.addEventListener('input', function() {
        const cat = document.getElementById('categoria_egreso')?.value;
        if (cat === 'traslados') calcTraslado();
    });

    // AJAX Guardado en Vivo
    const editables = document.querySelectorAll('.editable-input');
    
    function updateSums() {
        let bsTc = 0, bsDisp = 0, usdTc = 0, usdDisp = 0;
        document.querySelectorAll('input[data-field="bs_tc"]').forEach(i => bsTc += window.parseLocalNumber(i.value)||0);
        document.querySelectorAll('input[data-field="bs_disponibles"]').forEach(i => bsDisp += window.parseLocalNumber(i.value)||0);
        document.querySelectorAll('input[data-field="usd_tc"]').forEach(i => usdTc += window.parseLocalNumber(i.value)||0);
        document.querySelectorAll('input[data-field="usd_disp"]').forEach(i => usdDisp += window.parseLocalNumber(i.value)||0);
        
        const sumBsTc = document.getElementById('sum_bs_tc');
        if(sumBsTc) sumBsTc.textContent = bsTc.toFixed(2).replace(".", ",");
        
        const sumBsDisp = document.getElementById('sum_bs_disp');
        if(sumBsDisp) sumBsDisp.textContent = bsDisp.toFixed(2).replace(".", ",");
        
        const sumUsdTc = document.getElementById('sum_usd_tc');
        if(sumUsdTc) sumUsdTc.textContent = usdTc.toFixed(2).replace(".", ",");
        
        const sumUsdDisp = document.getElementById('sum_usd_disp');
        if(sumUsdDisp) sumUsdDisp.textContent = usdDisp.toFixed(2).replace(".", ",");
    }
    
    updateSums(); // Init sums

    editables.forEach(input => {
        input.addEventListener('change', function() {
            // Auto calc USD DISP if BS DISPONIBLES changed
            if (this.getAttribute('data-field') === 'bs_disponibles') {
                const tr = this.closest('tr');
                const usdDispInput = tr.querySelector('input[data-field="usd_disp"]');
                const tasaBcvInput = document.querySelector('input[data-field="tasa_bcv_usd"]');
                if (usdDispInput && tasaBcvInput) {
                    const bsDisp = window.parseLocalNumber(this.value) || 0;
                    const tasa = window.parseLocalNumber(tasaBcvInput.value) || 1;
                    const usdDisp = bsDisp / tasa;
                    usdDispInput.value = usdDisp.toFixed(2).replace(".", ",");
                    // Trigger change manually to save usd_disp
                    usdDispInput.dispatchEvent(new Event('change'));
                }
            }

            // Auto calc USD TC if BS TC changed
            if (this.getAttribute('data-field') === 'bs_tc') {
                const tr = this.closest('tr');
                const usdTcInput = tr.querySelector('input[data-field="usd_tc"]');
                const tasaBcvInput = document.querySelector('input[data-field="tasa_bcv_usd"]');
                if (usdTcInput && tasaBcvInput) {
                    const bsTc = window.parseLocalNumber(this.value) || 0;
                    const tasa = window.parseLocalNumber(tasaBcvInput.value) || 1;
                    const usdTc = bsTc / tasa;
                    usdTcInput.value = usdTc.toFixed(2).replace(".", ",");
                    usdTcInput.dispatchEvent(new Event('change'));
                }
            }

            // Auto calc all USD if TASA BCV changed
            if (this.getAttribute('data-field') === 'tasa_bcv_usd') {
                const tasa = window.parseLocalNumber(this.value) || 1;
                document.querySelectorAll('input[data-field="bs_disponibles"]').forEach(bsInput => {
                    const tr = bsInput.closest('tr');
                    const usdDispInput = tr.querySelector('input[data-field="usd_disp"]');
                    if (usdDispInput) {
                        usdDispInput.value = (window.parseLocalNumber(bsInput.value || 0) / tasa).toFixed(2).replace(".", ",");
                        usdDispInput.dispatchEvent(new Event('change'));
                    }
                });
                document.querySelectorAll('input[data-field="bs_tc"]').forEach(bsInput => {
                    const tr = bsInput.closest('tr');
                    const usdTcInput = tr.querySelector('input[data-field="usd_tc"]');
                    if (usdTcInput) {
                        usdTcInput.value = (window.parseLocalNumber(bsInput.value || 0) / tasa).toFixed(2).replace(".", ",");
                        usdTcInput.dispatchEvent(new Event('change'));
                    }
                });
            }

            const id = this.getAttribute('data-id');
            const type = this.getAttribute('data-type');
            const field = this.getAttribute('data-field');
            // Enviar siempre el valor numérico limpio (sin puntos de miles ni coma decimal)
            const value = window.parseLocalNumber(this.value);
            
            updateSums();

            let url = '';
            if (type === 'cuenta') {
                url = `/finanzas/flujo-caja/cuenta/${id}`;
            } else {
                url = `/finanzas/flujo-caja/resumen/${id}`;
            }

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ field: field, value: value })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    this.style.backgroundColor = '#d1e7dd';
                    setTimeout(() => { this.style.backgroundColor = 'transparent'; }, 500);
                } else {
                    alert('Error guardando el campo');
                }
            })
            .catch(err => console.error(err));
        });
    });
});

function handleOcrUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    const btn = document.getElementById('btn-ocr');
    const btnText = document.getElementById('ocr-btn-text');
    const originalText = btnText.innerText;
    
    btn.disabled = true;
    btnText.innerText = "Analizando...";
    btn.style.opacity = "0.7";

    const formData = new FormData();
    formData.append('image', file);
    formData.append('_token', '{{ csrf_token() }}');

    fetch('{{ route("finanzas.ocr_receipt") }}', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btnText.innerText = originalText;
        btn.style.opacity = "1";
        
        if (data.error) {
            alert('Error al leer recibo: ' + (data.error || 'Desconocido'));
            return;
        }

        // Fill form fields
        if (data.fecha) {
            const dateInput = document.querySelector('input[name="fecha"]');
            if (dateInput) dateInput.value = data.fecha;
        }
        
        if (data.referencia) {
            const refInput = document.querySelector('input[name="referencia"]');
            if (refInput) refInput.value = data.referencia;
        }

        // The user specifically requested amounts in BS
        const amount = data.monto_bs ? data.monto_bs : (data.monto_usd ? data.monto_usd : null);
        const montoBsInput = document.querySelector('input[name="monto_bs"]');
        
        if (amount && montoBsInput) {
            montoBsInput.value = Math.abs(amount);
            montoBsInput.dispatchEvent(new Event('input')); // trigger calculations if any
        }
        
        if (data.motivo) {
            const motivoInput = document.querySelector('input[name="motivo"]');
            if (motivoInput) motivoInput.value = data.motivo;
        }

        // Try to pre-select Banco
        if (data.banco_titular_hint) {
            const bancoSelect = document.querySelector('select[name="banco_titular"]');
            if (bancoSelect) {
                const hint = data.banco_titular_hint.toLowerCase();
                Array.from(bancoSelect.options).forEach(opt => {
                    if (opt.text.toLowerCase().includes(hint)) {
                        bancoSelect.value = opt.value;
                    }
                });
            }
        }
        
        // Abrir el modal original
        openNuevoEgresoModal();

        // Reset file input
        event.target.value = '';
    })
    .catch(err => {
        console.error(err);
        btn.disabled = false;
        btnText.innerText = originalText;
        btn.style.opacity = "1";
        alert('Error de conexión o timeout al analizar la imagen.');
    });
}

function handleOcrSaldosUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    const textSpan = document.getElementById('ocr-saldos-text');
    const originalText = textSpan.innerText;
    textSpan.innerText = "Analizando Reporte...";

    const formData = new FormData();
    formData.append('image', file);
    formData.append('_token', '{{ csrf_token() }}');

    fetch('{{ route("finanzas.ocr_saldos") }}', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        textSpan.innerText = originalText;
        if (data.error) {
            alert('Error al leer reporte: ' + data.error);
            event.target.value = '';
            return;
        }

        if (!Array.isArray(data) || data.length === 0) {
            alert('No se encontraron cuentas o saldos en el reporte.');
            event.target.value = '';
            return;
        }

        let updatedCount = 0;
        const tableRows = document.querySelectorAll('.modern-table tbody tr');
        
        data.forEach(item => {
            if (!item.banco || !item.titular || item.bs === undefined || item.bs === null) return;
            
            const bankStr = item.banco.toUpperCase().trim();
            const titStr = item.titular.toUpperCase().trim();
            
            tableRows.forEach(tr => {
                const tdBanco = tr.querySelector('td:nth-child(2)');
                const tdTit = tr.querySelector('td:nth-child(3)');
                if (!tdBanco || !tdTit) return;
                
                // Fuzzy match just in case
                const rowBank = tdBanco.innerText.toUpperCase().trim();
                const rowTit = tdTit.innerText.toUpperCase().trim();
                
                if (rowBank.includes(bankStr) && rowTit.includes(titStr)) {
                    const bsDispInput = tr.querySelector('input[data-field="bs_disponibles"]');
                    if (bsDispInput) {
                        bsDispInput.value = item.bs;
                        // Dispatch event to save via AJAX and trigger USD auto-calc
                        bsDispInput.dispatchEvent(new Event('change')); 
                        updatedCount++;
                    }
                }
            });
        });

        alert(`Se actualizaron exitosamente ${updatedCount} cuentas bancarias.`);
        event.target.value = '';
    })
    .catch(err => {
        console.error(err);
        textSpan.innerText = originalText;
        alert('Error de conexión al analizar el reporte.');
        event.target.value = '';
    });
}

function validarDesglose(event) {
    const chk = document.getElementById('chk_desglose');
    if (chk && chk.checked) {
        const montoBsInput = document.getElementById('monto_bs');
        const montoTotal = window.parseLocalNumber(montoBsInput.value) || 0;
        
        const montosDesglose = document.querySelectorAll('input[name="desglose_monto[]"]');
        let sumaDesglose = 0;
        montosDesglose.forEach(input => {
            sumaDesglose += window.parseLocalNumber(input.value) || 0;
        });

        if (Math.abs(montoTotal - sumaDesglose) > 0.05) {
            alert(`Error: La suma del desglose (Bs. ${sumaDesglose.toLocaleString('es-VE', {minimumFractionDigits:2})}) no coincide con el Monto Bs total (Bs. ${montoTotal.toLocaleString('es-VE', {minimumFractionDigits:2})}).`);
            event.preventDefault();
            return false;
        }
    }
    return true;
}

function toggleDesglose() {
    const chk = document.getElementById('chk_desglose');
    const container = document.getElementById('container_desglose');
    container.style.display = chk.checked ? 'block' : 'none';
    
    if (chk.checked && document.getElementById('lista_desglose').children.length === 0) {
        agregarDesglose();
    }
}

const proveedoresList = [];
// Variables para Desglose
const sedesList = [];

function buildOptions(list, selectedValue) {
    let options = '<option value="">-- Seleccione --</option>';
    list.forEach(item => {
        options += `<option value="${item}" ${item === selectedValue ? 'selected' : ''}>${item}</option>`;
    });
    return options;
}

function buildTipoGastoOptions(selectedValue) {
    const tipos = [
        "001 - COMPRA DE MERCANCIA", "002 - ALQUILER", "003 - NOMINA ADMINISTRATIVA", "004 - NOMINA TIENDA",
        "005 - PUBLICIDAD", "006 - PAPELERIA Y MATERIAL DE OFICINA", "007 - MANTENIMIENTO TIENDA",
        "008 - MANTENIMIENTO OFICINA", "009 - SERVICIOS BASICOS Y CONDOMINIO", "010 - TRANSPORTE",
        "011 - VIATICOS", "012 - ALIMENTACION", "013 - HONORARIOS PROFESIONALES", "014 - IMPUESTOS Y PATENTES",
        "015 - GASTOS DE REPRESENTACION", "016 - SEGUROS", "017 - GASTOS BANCARIOS", "018 - DONACIONES",
        "019 - MATERIAL DE EMPAQUE", "020 - DOTACION DE PERSONAL", "021 - LICENCIAS Y SOFTWARE",
        "090 - PRESTAMO A EMPLEADO", "091 - OTROS EGRESOS", "092 - PAGO DE SERVICIOS ADICIONALES",
        "093 - GASTOS DIRECTIVO", "094 - SALDO MOVISTAR", "095 - TASAS Y CONTRIBUCIONES",
        "097 - INSTALACIONES Y MEJORAS GALPON Y DEPOSITO", "098 - MEJORAS INSTALACIONES TIENDAS",
        "099 - DEVOLUCIONES CLIENTES"
    ];
    let options = '<option value="">-- Tipo Gasto --</option>';
    tipos.forEach(item => {
        options += `<option value="${item}" ${item === selectedValue ? 'selected' : ''}>${item}</option>`;
    });
    return options;
}

async function cargarArchivoDesglose(input) {
    if (!input.files || input.files.length === 0) return;
    
    const file = input.files[0];
    const formData = new FormData();
    formData.append('archivo', file);
    formData.append('_token', document.querySelector('input[name="_token"]').value);

    Swal.fire({
        title: 'Procesando archivo',
        text: 'Leyendo datos y buscando clientes...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const response = await fetch('{{ route("finanzas.parse_desglose") }}', {
            method: 'POST',
            body: formData
        });
        
        const res = await response.json();
        
        if (!res.ok) {
            Swal.fire('Error', res.error || 'Ocurrió un error al procesar el archivo', 'error');
            input.value = '';
            return;
        }

        const data = res.data;
        if (data.length === 0) {
            Swal.fire('Atención', 'No se encontraron registros válidos en el archivo', 'warning');
            input.value = '';
            return;
        }

        // Preguntar por sede y tipo de gasto para aplicar a todos
        const { value: formValues } = await Swal.fire({
            title: 'Datos extraídos correctamente',
            html: `
                <p style="font-size: 14px; color: #475569; margin-bottom: 15px;">Se encontraron <b>${data.length}</b> registros. Selecciona a dónde pertenecen (opcional):</p>
                <div style="text-align: left; display: flex; flex-direction: column; gap: 10px;">
                    <label>Sede (opcional)</label>
                    <select id="swal_sede" class="swal2-select" style="margin: 0; width: 100%; font-size: 14px; padding: 6px;">
                        <option value="">-- Ninguna --</option>
                        ${sedesList.map(s => `<option value="${s}">${s}</option>`).join('')}
                    </select>
                    
                    <label style="margin-top: 10px;">Tipo de Gasto (opcional)</label>
                    <select id="swal_tg" class="swal2-select" style="margin: 0; width: 100%; font-size: 14px; padding: 6px;">
                        <option value="">-- Ninguno --</option>
                        <option value="083 - GASTOS MEDICOS EMPLEADOS">083 - GASTOS MEDICOS EMPLEADOS</option>
                        <option value="002 - IMPUESTO MUNICIPAL (ALCALDIAS)">002 - IMPUESTO MUNICIPAL (ALCALDIAS)</option>
                        <option value="025 - UTILIDADES PERSONAL">025 - UTILIDADES PERSONAL</option>
                        <option value="026 - VACACIONES PERSONAL">026 - VACACIONES PERSONAL</option>
                        <option value="027 - NOMINA LEGAL">027 - NOMINA LEGAL</option>
                        <option value="028 - NOMINA ESPECIAL">028 - NOMINA ESPECIAL</option>
                        <option value="029 - PRESTACIONES SOCIALES">029 - PRESTACIONES SOCIALES</option>
                        <option value="084 - ABONO TIENDA EMPLEADOS">084 - ABONO TIENDA EMPLEADOS</option>
                        <!-- (Se pueden ajustar los principales o dejar que el usuario asigne manualmente en la lista) -->
                        <option value="OTROS">Otros (Seleccionar en la lista principal)</option>
                    </select>
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Aplicar y añadir',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                return {
                    sede: document.getElementById('swal_sede').value,
                    tg: document.getElementById('swal_tg').value
                }
            }
        });

        if (formValues) {
            let tgSelected = formValues.tg === 'OTROS' ? '' : formValues.tg;
            
            data.forEach(row => {
                // Formatear monto bs
                let montoBsFormateado = row.monto.toString().replace('.', ',');
                agregarDesglose(row.cedula, '', montoBsFormateado, formValues.sede, tgSelected);
            });
            
            Swal.fire('Éxito', `Se agregaron ${data.length} personas al desglose.`, 'success');
        }

    } catch (err) {
        console.error(err);
        Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
    }
    
    // Limpiar input
    input.value = '';
}

function agregarDesglose(ced = '', usd = '', bs = '', sede = '', tipoGasto = '') {
    const lista = document.getElementById('lista_desglose');
    const sedeOptions = buildOptions(sedesList, sede);
    const tgOptions = buildTipoGastoOptions(tipoGasto);
    
    // Generar un ID único para los selects
    const uid = Date.now() + Math.floor(Math.random() * 1000);

    const html = `
        <div class="row-desglose" style="display: flex; gap: 8px; margin-bottom: 8px; align-items: center; flex-wrap: wrap; background: white; padding: 10px; border-radius: 6px; border: 1px solid #ccc;">
            <div style="flex: 1 1 100%; display: flex; gap: 8px;">
                <select id="sel_sede_${uid}" name="desglose_sede[]" style="flex: 1; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                    ${sedeOptions}
                </select>
                <select id="sel_tg_${uid}" name="desglose_tipo_gasto[]" style="flex: 2; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                    ${tgOptions}
                </select>
            </div>
            <div style="flex: 1 1 100%; display: flex; gap: 8px; margin-top: 4px;">
                <input type="text" name="desglose_cedula[]" placeholder="Cédula/RIF" value="${ced}" style="flex: 1; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                <input type="text" inputmode="decimal" name="desglose_monto_usd[]" placeholder="Monto USD" value="${usd}" oninput="calcDesgloseRow(this, 'usd')" style="flex: 1; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                <input type="text" inputmode="decimal" name="desglose_monto[]" placeholder="Monto Bs" value="${bs}" oninput="calcDesgloseRow(this, 'bs')" style="flex: 1; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                <button type="button" onclick="this.closest('.row-desglose').remove()" style="padding: 6px 10px; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer; flex-shrink: 0;">&times;</button>
            </div>
        </div>
    `;
    lista.insertAdjacentHTML('beforeend', html);
    
    // Inicializar TomSelect en los nuevos selects
    new TomSelect(`#sel_tg_${uid}`, { create: false, sortField: { field: "text", direction: "asc" }, placeholder: '-- Tipo Gasto --' });
}

function calcDesgloseRow(input, source) {
    const row = input.closest('.row-desglose');
    const inputBs = row.querySelector('input[name="desglose_monto[]"]');
    const inputUsd = row.querySelector('input[name="desglose_monto_usd[]"]');
    const inputTasa = document.getElementById('tasa_cambio') || document.querySelector('input[name="tasa_cambio"]');
    
    let tasa = window.parseLocalNumber(inputTasa.value) || 0;
    if (tasa <= 0) return;
    
    if (source === 'usd') {
        const usd = window.parseLocalNumber(inputUsd.value) || 0;
        inputBs.value = (usd * tasa).toFixed(2).replace('.', ',');
    } else {
        const bs = window.parseLocalNumber(inputBs.value) || 0;
        inputUsd.value = (bs / tasa).toFixed(2).replace('.', ',');
    }
}
function verDesglose(desglose) {
    let tbodyHtml = '';
    let totalBs = 0;
    let totalUsd = 0;
    let hasUsd = desglose.some(item => item.monto_usd > 0);
    desglose.forEach(item => {
        const monto = window.parseLocalNumber(item.monto) || 0;
        const montoUsd = parseFloat(item.monto_usd) || 0;
        totalBs += monto;
        totalUsd += montoUsd;
        tbodyHtml += `
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">${item.cedula || '-'}</td>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">
                    <div style="font-weight: 500;">Sede: ${item.sede || '-'}</div>
                    <div style="font-size: 0.8rem; color: #64748b;">Gasto: ${item.tipo_gasto || '-'}</div>
                </td>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: right; font-weight: 500;">Bs. ${monto.toLocaleString('es-VE', {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                ${hasUsd ? `<td style="padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: right; color: #0284c7;">$ ${montoUsd.toLocaleString('es-VE', {minimumFractionDigits:2, maximumFractionDigits:2})}</td>` : ''}
            </tr>
        `;
    });

    // Add USD header if needed
    const thead = document.querySelector('#modalDesglose thead tr');
    const existingUsdTh = document.getElementById('th-desglose-usd');
    if (hasUsd && !existingUsdTh) {
        const th = document.createElement('th');
        th.id = 'th-desglose-usd';
        th.style = 'padding: 8px; border-bottom: 2px solid #e2e8f0; text-align: right;';
        th.textContent = 'USD';
        thead.appendChild(th);
    } else if (!hasUsd && existingUsdTh) {
        existingUsdTh.remove();
    }
    
    document.getElementById('modalDesgloseBody').innerHTML = tbodyHtml;
    document.getElementById('modalDesgloseTotal').innerText = `Bs. ${totalBs.toLocaleString('es-VE', {minimumFractionDigits:2, maximumFractionDigits:2})}`;
    const usdTotalEl = document.getElementById('modalDesgloseTotalUsd');
    if (hasUsd && usdTotalEl) {
        usdTotalEl.style.display = '';
        usdTotalEl.innerText = `$ ${totalUsd.toLocaleString('es-VE', {minimumFractionDigits:2, maximumFractionDigits:2})}`;
        document.getElementById('row-desglose-usd-total').style.display = '';
    } else if (usdTotalEl) {
        usdTotalEl.style.display = 'none';
        document.getElementById('row-desglose-usd-total').style.display = 'none';
    }
    document.getElementById('modalDesglose').style.display = 'flex';
}

function closeDesgloseModal() {
    document.getElementById('modalDesglose').style.display = 'none';
}

// ===== GALERÍA DE COMPROBANTES =====
function abrirGaleria(urls) {
    const grid = document.getElementById('galeriaGrid');
    grid.innerHTML = '';
    urls.forEach((url, i) => {
        const isImage = /\.(jpg|jpeg|png|gif|webp|bmp)(\?|$)/i.test(url);
        const item = document.createElement('div');
        item.style.cssText = 'position:relative;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;background:#f8fafc;display:flex;flex-direction:column;align-items:center;';
        if (isImage) {
            item.innerHTML = `
                <img src="${url}" style="max-width:100%;max-height:200px;object-fit:contain;cursor:zoom-in;" onclick="window.open('${url}','_blank')">
                <div style="padding:6px;display:flex;gap:6px;">
                    <a href="${url}" download target="_blank" style="font-size:0.8rem;background:#3b82f6;color:white;padding:4px 10px;border-radius:4px;text-decoration:none;">⬇ Descargar</a>
                    <a href="${url}" target="_blank" style="font-size:0.8rem;background:#e0f2fe;color:#0284c7;padding:4px 10px;border-radius:4px;text-decoration:none;">🔍 Ver</a>
                </div>`;
        } else {
            item.innerHTML = `
                <div style="padding:20px;text-align:center;font-size:0.85rem;color:#64748b;">📄 Documento ${i+1}</div>
                <div style="padding:6px;display:flex;gap:6px;">
                    <a href="${url}" download target="_blank" style="font-size:0.8rem;background:#3b82f6;color:white;padding:4px 10px;border-radius:4px;text-decoration:none;">⬇ Descargar</a>
                    <a href="${url}" target="_blank" style="font-size:0.8rem;background:#e0f2fe;color:#0284c7;padding:4px 10px;border-radius:4px;text-decoration:none;">🔍 Ver</a>
                </div>`;
        }
        grid.appendChild(item);
    });
    document.getElementById('modalGaleria').style.display = 'flex';
}

function cerrarGaleria() {
    document.getElementById('modalGaleria').style.display = 'none';
}

// ===== EDITAR EGRESO =====
function abrirEditarEgreso(mov) {
    const m = document.getElementById('modalEditarEgreso');
    const f = document.getElementById('formEditarEgreso');

    // Set action URL
    f.action = '/finanzas/flujo-caja/egreso/' + mov.id;

    // Fill fields
    f.querySelector('[name="fecha"]').value          = mov.fecha || '';
    f.querySelector('[name="referencia"]').value     = mov.referencia || '';
    f.querySelector('[name="monto_usd"]').value      = mov.monto_usd || '';
    f.querySelector('[name="tasa_cambio"]').value    = mov.tasa_cambio || '';
    f.querySelector('[name="monto_bs"]').value       = mov.monto_bs || '';
    f.querySelector('[name="diferencial_cambiario"]').value = mov.diferencial_cambiario || '';
    f.querySelector('[name="comision"]').value       = mov.comision || '';
    f.querySelector('[name="motivo"]').value         = mov.motivo || '';
    f.querySelector('[name="sede"]').value           = mov.sede || '';
    f.querySelector('[name="placa_vehiculo"]').value = mov.placa_vehiculo || '';
    
    // Set value for TomSelect
    if (window.tsEditTipoGasto) {
        window.tsEditTipoGasto.setValue(mov.tipo_gasto || '');
    } else {
        const dstTG = document.getElementById('edit_tipo_gasto');
        if (dstTG) dstTG.value = mov.tipo_gasto || '';
    }

    // Banco titular
    const bancoVal = (mov.banco || '') + '|' + (mov.titular || '') + '|' + (mov.categoria_cuenta || '');
    const bancoSelect = f.querySelector('[name="banco_titular"]');
    if (bancoSelect) {
        // Try exact match first
        let found = false;
        for (let opt of bancoSelect.options) {
            if (opt.value === bancoVal) { opt.selected = true; found = true; break; }
        }
        if (!found) {
            // fallback: search by banco+titular partial
            for (let opt of bancoSelect.options) {
                const parts = opt.value.split('|');
                if (parts[0] === mov.banco && parts[1] === mov.titular) { opt.selected = true; break; }
            }
        }
    }

    // Lógica para Traslados
    const isTraslado = (mov.categoria_egreso === 'traslados');
    
    document.getElementById('row_receptor_edit').style.display = isTraslado ? 'flex' : 'none';
    document.getElementById('banco_titular_receptor_edit').required = isTraslado;
    
    document.getElementById('lbl_banco_titular_edit').innerText = isTraslado ? 'Banco Emisor y Titular Emisor' : 'Banco y Titular';
    document.getElementById('lbl_monto_bs_edit').innerText = isTraslado ? 'Monto' : 'Monto BS';
    
    document.getElementById('col_monto_usd_edit').style.display = isTraslado ? 'none' : 'block';
    document.getElementById('col_tasa_cambio_edit').style.display = isTraslado ? 'none' : 'block';
    const rowDifEdit = document.getElementById('row_diferencial_edit');
    if (rowDifEdit) rowDifEdit.style.display = isTraslado ? 'none' : 'block';
    
    document.getElementById('row_tipo_gasto_edit').style.display = isTraslado ? 'none' : 'block';
    // Para no dar error con TomSelect requerimos el elemento base
    const dstTG = document.getElementById('edit_tipo_gasto');
    if (dstTG) dstTG.required = !isTraslado;

    // Banco receptor
    if (isTraslado && mov.banco_receptor) {
        const bancoReceptorSelect = document.getElementById('banco_titular_receptor_edit');
        if (bancoReceptorSelect) {
            // Se busca match parcial con banco y titular receptor
            for (let opt of bancoReceptorSelect.options) {
                const parts = opt.value.split('|');
                if (parts[0] === mov.banco_receptor && parts[1] === mov.titular_receptor) { 
                    opt.selected = true; 
                    break; 
                }
            }
        }
    } else {
        document.getElementById('banco_titular_receptor_edit').value = '';
    }

    // Existing comprobantes gallery
    const compSection = document.getElementById('editComprobantesActuales');
    compSection.innerHTML = '';
    const allComps = [];
    if (mov.comprobantes && mov.comprobantes.length) {
        mov.comprobantes.forEach(u => allComps.push(u));
    } else if (mov.comprobante_url) {
        allComps.push(mov.comprobante_url);
    }

    allComps.forEach((url, i) => {
        const isImg = /\.(jpg|jpeg|png|gif|webp|bmp)(\?|$)/i.test(url);
        const div = document.createElement('div');
        div.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:8px;padding:8px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;';
        div.innerHTML = `
            ${isImg ? `<img src="${url}" style="width:60px;height:50px;object-fit:cover;border-radius:4px;cursor:pointer;" onclick="window.open('${url}','_blank')">` : `<span style="font-size:1.5rem;">📄</span>`}
            <div style="flex:1;font-size:0.8rem;color:#475569;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${url.split('/').pop()}</div>
            <a href="${url}" target="_blank" style="font-size:0.75rem;color:#0284c7;text-decoration:none;">Ver</a>
            <label style="font-size:0.75rem;color:#dc2626;cursor:pointer;">
                <input type="checkbox" name="comprobantes_eliminar[]" value="${url}"> Eliminar
            </label>`;
        compSection.appendChild(div);
    });
    if (allComps.length === 0) {
        compSection.innerHTML = '<p style="color:#94a3b8;font-size:0.85rem;">Sin comprobantes adjuntos</p>';
    }

    // Desglose
    const chk = document.getElementById('chk_desglose_edit');
    const listaDes = document.getElementById('lista_desglose_edit');
    const containerDes = document.getElementById('container_desglose_edit');
    listaDes.innerHTML = '';
    if (mov.desglose && mov.desglose.length) {
        chk.checked = true;
        containerDes.style.display = 'block';
        mov.desglose.forEach(item => {
            agregarDesgloseEdit(item.cedula, item.monto_usd, item.monto, item.sede, item.tipo_gasto);
        });
    } else {
        chk.checked = false;
        containerDes.style.display = 'none';
    }

    m.style.display = 'flex';
}

function cerrarEditarEgreso() {
    document.getElementById('modalEditarEgreso').style.display = 'none';
}

function toggleDesgloseEdit() {
    const chk = document.getElementById('chk_desglose_edit');
    document.getElementById('container_desglose_edit').style.display = chk.checked ? 'block' : 'none';
}

function agregarDesgloseEdit(ced, monto_usd, monto, sede = '', tipoGasto = '') {
    const lista = document.getElementById('lista_desglose_edit');
    const sedeOptions = buildOptions(sedesList, sede);
    const tgOptions = buildTipoGastoOptions(tipoGasto);

    const uid = Date.now() + Math.floor(Math.random() * 1000);

    const html = `
        <div class="row-desglose" style="display: flex; gap: 8px; margin-bottom: 8px; align-items: center; flex-wrap: wrap; background: white; padding: 10px; border-radius: 6px; border: 1px solid #ccc;">
            <div style="flex: 1 1 100%; display: flex; gap: 8px;">
                <select id="sel_sede_edit_${uid}" name="desglose_sede[]" style="flex: 1; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                    ${sedeOptions}
                </select>
                <select id="sel_tg_edit_${uid}" name="desglose_tipo_gasto[]" style="flex: 2; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                    ${tgOptions}
                </select>
            </div>
            <div style="flex: 1 1 100%; display: flex; gap: 8px; margin-top: 4px;">
                <input type="text" name="desglose_cedula[]" placeholder="Cédula" value="${ced || ''}" style="flex: 1; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                <input type="text" inputmode="decimal" name="desglose_monto_usd[]" placeholder="Monto USD" value="${monto_usd || ''}" oninput="calcDesgloseRow(this, 'usd')" style="flex: 1; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                <input type="text" inputmode="decimal" name="desglose_monto[]" placeholder="Monto Bs" value="${monto || ''}" oninput="calcDesgloseRow(this, 'bs')" style="flex: 1; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                <button type="button" onclick="this.closest('.row-desglose').remove()" style="padding: 6px 10px; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer; flex-shrink: 0;">&times;</button>
            </div>
        </div>`;
    lista.insertAdjacentHTML('beforeend', html);
    
    // Inicializar TomSelect en los nuevos selects
    new TomSelect(`#sel_tg_edit_${uid}`, { create: false, sortField: { field: "text", direction: "asc" }, placeholder: '-- Tipo Gasto --' });
}

function validarDesgloseEdit(event) {
    const chk = document.getElementById('chk_desglose_edit');
    if (chk && chk.checked) {
        const montoBsInput = document.querySelector('#formEditarEgreso [name="monto_bs"]');
        const montoTotal = window.parseLocalNumber(montoBsInput ? montoBsInput.value : 0) || 0;
        let sumaDesglose = 0;
        document.querySelectorAll('#lista_desglose_edit input[name="desglose_monto[]"]').forEach(inp => {
            sumaDesglose += window.parseLocalNumber(inp.value) || 0;
        });
        if (Math.abs(montoTotal - sumaDesglose) > 0.05) {
            alert(`Error: La suma del desglose (Bs. ${sumaDesglose.toFixed(2).replace(".", ",")}) no coincide con el Monto Bs (Bs. ${montoTotal.toFixed(2).replace(".", ",")}).`);
            event.preventDefault();
            return false;
        }
    }
    return true;
}

function abrirVerEgreso(mov) {
    document.getElementById('modalVerEgreso').style.display = 'flex';
    
    document.getElementById('ver_fecha').innerText = mov.fecha || '-';
    document.getElementById('ver_referencia').innerText = mov.referencia || '-';
    
    const bancoStr = (mov.banco || '') + ' - ' + (mov.titular || '');
    document.getElementById('ver_banco').innerText = bancoStr;

    if (mov.banco_receptor) {
        document.getElementById('ver_receptor_container').style.display = 'block';
        document.getElementById('ver_banco_receptor').innerText = (mov.banco_receptor || '') + ' - ' + (mov.titular_receptor || '');
    } else {
        document.getElementById('ver_receptor_container').style.display = 'none';
    }

    document.getElementById('ver_monto_usd').innerText = mov.monto_usd ? '$ ' + window.parseLocalNumber(mov.monto_usd).toFixed(2).replace(".", ",") : '-';
    document.getElementById('ver_monto_bs').innerText = mov.monto_bs ? 'Bs. ' + window.parseLocalNumber(mov.monto_bs).toFixed(2).replace(".", ",") : '-';
    document.getElementById('ver_tasa').innerText = mov.tasa_cambio ? window.parseLocalNumber(mov.tasa_cambio).toFixed(2).replace(".", ",") : '-';
    document.getElementById('ver_dif').innerText = mov.diferencial_cambiario ? '$ ' + window.parseLocalNumber(mov.diferencial_cambiario).toFixed(2).replace(".", ",") : '-';
    document.getElementById('ver_comision').innerText = mov.comision ? 'Bs. ' + window.parseLocalNumber(mov.comision).toFixed(2).replace(".", ",") : '-';
    document.getElementById('ver_tipo_gasto').innerText = mov.tipo_gasto || '-';
    document.getElementById('ver_motivo').innerText = mov.motivo || '-';
    document.getElementById('ver_sede').innerText = mov.sede || '-';
    document.getElementById('ver_placa').innerText = mov.placa_vehiculo || '-';

    // Desglose
    const dgCont = document.getElementById('ver_desglose_container');
    const dgLista = document.getElementById('ver_desglose_lista');
    dgLista.innerHTML = '';
    if (mov.desglose && Array.isArray(mov.desglose) && mov.desglose.length > 0) {
        dgCont.style.display = 'block';
        mov.desglose.forEach(item => {
            dgLista.innerHTML += `
                <div style="display: flex; gap: 10px; margin-bottom: 5px; padding-bottom: 5px; border-bottom: 1px solid #bae6fd;">
                    <div style="flex: 2;"><strong>Beneficiario:</strong> ${item.beneficiario || ''}</div>
                    <div style="flex: 1;"><strong>Cédula:</strong> ${item.cedula || ''}</div>
                    <div style="flex: 1; text-align: right;"><strong>Monto:</strong> Bs. ${item.monto || ''}</div>
                </div>
            `;
        });
    } else {
        dgCont.style.display = 'none';
    }

    // Comprobantes
    const compCont = document.getElementById('ver_comprobantes');
    compCont.innerHTML = '';
    let allComps = [];
    if (mov.comprobantes) allComps = allComps.concat(mov.comprobantes);
    if (mov.comprobante_url && !allComps.includes(mov.comprobante_url)) allComps.push(mov.comprobante_url);
    
    if (allComps.length > 0) {
        allComps.forEach(url => {
            const ext = url.split('.').pop().toLowerCase();
            let el;
            if (ext === 'pdf') {
                el = `<a href="/storage/${url}" target="_blank" style="display:inline-block; padding:10px; background:#f1f5f9; border-radius:6px; border:1px solid #cbd5e1; text-decoration:none; color:#334155; font-weight:500;">📄 Ver PDF</a>`;
            } else {
                el = `<a href="/storage/${url}" target="_blank"><img src="/storage/${url}" style="width:100px; height:100px; object-fit:cover; border-radius:6px; border:1px solid #ccc;"></a>`;
            }
            compCont.innerHTML += el;
        });
    } else {
        compCont.innerHTML = '<span style="color:#94a3b8; font-size:0.85rem;">No hay comprobantes adjuntos</span>';
    }
}

function cerrarVerEgreso() {
    document.getElementById('modalVerEgreso').style.display = 'none';
}

// ===== FILTROS DE EGRESOS =====
function aplicarFiltros() {
    const texto = (document.getElementById('filtro-texto')?.value || '').toLowerCase().trim();
    const cat = document.getElementById('filtro-cat')?.value || '';

    // Map section identifiers
    const sectionMap = {
        'egresos': 'egreso_realizado',
        'otros': 'otros_egresos',
        'traslados': 'traslados',
    };

    // All egreso rows across all 3 tables are marked with data-egreso-cat
    const rows = document.querySelectorAll('tr[data-egreso-cat]');
    let visible = 0, total = rows.length;

    rows.forEach(tr => {
        const rowCat = tr.getAttribute('data-egreso-cat') || '';
        const rowText = tr.textContent.toLowerCase();
        const catMatch = !cat || sectionMap[cat] === rowCat;
        const textMatch = !texto || rowText.includes(texto);
        const show = catMatch && textMatch;
        tr.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    const contador = document.getElementById('filtro-contador');
    if (contador) {
        contador.textContent = texto || cat
            ? `Mostrando ${visible} de ${total} registros`
            : '';
    }
}

function limpiarFiltros() {
    const txt = document.getElementById('filtro-texto');
    const cat = document.getElementById('filtro-cat');
    if (txt) txt.value = '';
    if (cat) cat.value = '';
    aplicarFiltros();
}

