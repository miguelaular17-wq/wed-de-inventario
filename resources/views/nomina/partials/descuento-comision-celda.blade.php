@php
    $monto = round((float) ($monto ?? 0), 2);
    $lineas = array_values($lineas ?? []);
    $titulo = $titulo ?? 'Desc. / préstamos';
    $puede = (bool) ($puede ?? false);
    $periodo = $periodo ?? null;
    $empleado = $empleado ?? null;
@endphp
@if($puede && $periodo && $empleado)
    <span class="com-desc-celda"
          data-empleado-id="{{ $empleado->id }}"
          data-monto="{{ number_format($monto, 2, '.', '') }}"
          data-titulo="{{ $titulo }}"
          data-payload="{{ base64_encode(json_encode($lineas, JSON_UNESCAPED_UNICODE)) }}"
          data-url-opciones="{{ route('nomina.comisiones.deducciones.opciones', [$periodo, $empleado]) }}"
          data-url-anular="{{ route('nomina.comisiones.deducciones.anular', [$periodo, $empleado]) }}"
          data-url-aplicar="{{ route('nomina.comisiones.deducciones.aplicar', [$periodo, $empleado]) }}"
          data-url-rapido="{{ route('nomina.comisiones.descuento_rapido', [$periodo, $empleado]) }}">
        @if(abs($monto) < 0.009)
            <button type="button" class="com-desc-cero" title="Agregar: descuento, faltante o préstamo">${{ number_format($monto, 2) }}</button>
        @else
            <button type="button" class="com-desc-link" title="Ver detalle">${{ number_format($monto, 2) }}</button>
        @endif
    </span>
@else
    @include('nomina.partials.descuento-comentarios', compact('monto', 'lineas', 'titulo'))
@endif

@once
    @push('head')
    <style>
        .com-desc-cero, .com-desc-link {
            background: none; border: 0; padding: 0; margin: 0;
            color: #1d4ed8; font: inherit; font-weight: 700; cursor: pointer;
            text-decoration: underline; text-decoration-style: dotted; text-underline-offset: 3px;
        }
        .com-desc-backdrop {
            display: none; position: fixed; inset: 0; z-index: 420;
            background: rgba(15, 23, 42, .45); align-items: center; justify-content: center; padding: 16px;
        }
        .com-desc-backdrop.is-open { display: flex; }
        .com-desc-modal {
            background: #fff; border-radius: 12px; width: min(520px, 100%); max-height: 85vh;
            overflow: auto; padding: 18px 20px; box-shadow: 0 20px 40px rgba(15,23,42,.2);
        }
        .com-desc-modal h3 { margin: 0 0 12px; font-size: 1.05rem; }
        .com-desc-modal table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .com-desc-modal td { padding: 8px 0; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .com-desc-modal .monto { text-align: right; font-weight: 700; white-space: nowrap; padding-left: 12px; }
        .com-desc-modal .tipo { font-size: .72rem; color: #64748b; text-transform: uppercase; letter-spacing: .03em; }
        .com-desc-modal .acciones { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
        .com-desc-panel {
            display: none; margin-top: 14px; padding-top: 12px; border-top: 1px dashed #cbd5e1;
        }
        .com-desc-panel.is-on { display: block; }
        .com-desc-panel label { display: block; font-size: .75rem; font-weight: 700; color: #64748b; margin: 8px 0 4px; }
        .com-desc-panel select, .com-desc-panel input, .com-desc-panel textarea {
            width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 600;
        }
        .com-desc-panel .hint { font-size: .75rem; color: #64748b; margin: 6px 0 0; }
        .com-desc-anular {
            border: 0; background: #fee2e2; color: #b91c1c; border-radius: 6px;
            padding: 4px 8px; font-size: .72rem; font-weight: 700; cursor: pointer;
        }
    </style>
    @endpush
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('com-desc-modal')) return;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('input[name="_token"]')?.value
            || '';

        const wrap = document.createElement('div');
        wrap.id = 'com-desc-modal';
        wrap.className = 'com-desc-backdrop';
        wrap.innerHTML = `
            <div class="com-desc-modal" role="dialog" aria-modal="true">
                <h3></h3>
                <div class="com-desc-body"></div>
                <div class="acciones">
                    <button type="button" class="btn secondary" data-cerrar>Cerrar</button>
                    <button type="button" class="btn" data-ajustar>Ajustar</button>
                </div>
                <div class="com-desc-panel" data-panel>
                    <label>Tipo de ajuste</label>
                    <p class="hint" style="margin:0 0 8px;">Elige el tipo: descuento (monto aquí), o faltante / préstamo ya cargados en su apartado.</p>
                    <select data-tipo>
                        <option value="descuento">Descuento (escribe el monto)</option>
                        <option value="faltante">Faltante de caja (desde Ajustes → Faltantes)</option>
                        <option value="prestamo">Préstamo (desde Ajustes → Préstamos)</option>
                    </select>
                    <div data-box-descuento>
                        <label>Monto del descuento</label>
                        <input type="number" step="0.01" min="0.01" data-monto-desc placeholder="Ej. 30.00">
                        <label>Motivo</label>
                        <input type="text" data-motivo-desc maxlength="500" placeholder="Motivo del descuento">
                    </div>
                    <div data-box-faltante hidden>
                        <label>Faltante con monto ya cargado</label>
                        <select data-faltante></select>
                        <p class="hint">No se escribe el monto aquí: debe existir en Ajustes → Faltantes.</p>
                    </div>
                    <div data-box-prestamo hidden>
                        <label>Préstamo / cuota con monto ya cargado</label>
                        <select data-prestamo></select>
                        <p class="hint">No se escribe el monto aquí: debe existir en Ajustes → Préstamos.</p>
                    </div>
                    <div class="acciones">
                        <button type="button" class="btn primary" data-aplicar>Aplicar</button>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(wrap);

        const titulo = wrap.querySelector('h3');
        const body = wrap.querySelector('.com-desc-body');
        const panel = wrap.querySelector('[data-panel]');
        const tipoSel = wrap.querySelector('[data-tipo]');
        const boxDesc = wrap.querySelector('[data-box-descuento]');
        const boxFal = wrap.querySelector('[data-box-faltante]');
        const boxPre = wrap.querySelector('[data-box-prestamo]');
        const selFal = wrap.querySelector('[data-faltante]');
        const selPre = wrap.querySelector('[data-prestamo]');
        let celdaActiva = null;

        function fmt(n) {
            return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        function cerrar() {
            wrap.classList.remove('is-open');
            panel.classList.remove('is-on');
            celdaActiva = null;
        }
        function leerPayload(el) {
            try {
                const raw = el.getAttribute('data-payload') || '';
                if (!raw) return [];
                const bytes = Uint8Array.from(atob(raw), function (c) { return c.charCodeAt(0); });
                const parsed = JSON.parse(new TextDecoder('utf-8').decode(bytes));
                return Array.isArray(parsed) ? parsed : [];
            } catch (e) { return []; }
        }
        function pintarLineas(lineas) {
            if (!lineas.length) {
                body.innerHTML = '<p class="muted">Sin conceptos en esta quincena.</p>';
                return;
            }
            body.innerHTML = '<table>' + lineas.map(function (l) {
                const comentario = String(l.comentario || 'Sin comentario').replace(/</g, '&lt;');
                const tipo = String(l.tipo || 'Descuento').replace(/</g, '&lt;');
                const anular = l.id && l.origen
                    ? '<button type="button" class="com-desc-anular" data-origen="' + l.origen + '" data-id="' + l.id + '">Anular</button>'
                    : '';
                return '<tr><td><div class="tipo">' + tipo + '</div>' + comentario + (anular ? '<div style="margin-top:6px;">' + anular + '</div>' : '') + '</td><td class="monto">' + fmt(l.monto) + '</td></tr>';
            }).join('') + '</table>';
        }
        function syncTipo() {
            const t = tipoSel.value;
            boxDesc.hidden = t !== 'descuento';
            boxFal.hidden = t !== 'faltante';
            boxPre.hidden = t !== 'prestamo';
        }
        function actualizarKpis(data) {
            document.querySelectorAll('.nomina-kpi').forEach(function (kpi) {
                const label = (kpi.querySelector('span')?.textContent || '').toLowerCase();
                const strong = kpi.querySelector('strong');
                if (!strong) return;
                const actual = parseFloat(String(strong.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
                if (label.includes('descuento')) strong.textContent = fmt(actual + (data.delta_descuentos || 0));
                if (label.includes('retención') || label.includes('retencion')) strong.textContent = fmt(actual + (data.delta_retencion || 0));
                if (label.includes('a pagar')) strong.textContent = fmt(actual + (data.delta_pagar || 0));
            });
        }
        function refrescarCelda(data) {
            if (!celdaActiva) return;
            const fila = celdaActiva.closest('tr');
            celdaActiva.setAttribute('data-monto', Number(data.monto || 0).toFixed(2));
            celdaActiva.setAttribute('data-payload', btoa(unescape(encodeURIComponent(JSON.stringify(data.lineas || [])))));
            const monto = Number(data.monto || 0);
            if (Math.abs(monto) < 0.009) {
                celdaActiva.innerHTML = '<button type="button" class="com-desc-cero" title="Agregar: descuento, faltante o préstamo">' + fmt(0) + '</button>';
            } else {
                celdaActiva.innerHTML = '<button type="button" class="com-desc-link" title="Ver detalle">' + fmt(monto) + '</button>';
            }
            if (fila) {
                if (data.retencion != null) {
                    const celda = fila.querySelector('[data-col="retencion"]');
                    if (celda) {
                        const hint = celda.querySelector('.muted');
                        celda.textContent = '';
                        celda.appendChild(document.createTextNode(fmt(data.retencion)));
                        if (hint) celda.appendChild(hint);
                    }
                }
                if (data.total_pagar != null) {
                    const celda = fila.querySelector('[data-col="pagar"]');
                    if (celda) {
                        const fuerte = celda.querySelector('strong');
                        if (fuerte) fuerte.textContent = fmt(data.total_pagar);
                        else celda.textContent = fmt(data.total_pagar);
                    }
                }
            }
            actualizarKpis(data);
        }
        function postJson(url, payload) {
            const bodyData = new FormData();
            Object.keys(payload || {}).forEach(function (k) {
                if (payload[k] != null && payload[k] !== '') bodyData.append(k, payload[k]);
            });
            if (csrf) bodyData.append('_token', csrf);
            return fetch(url, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: bodyData,
                credentials: 'same-origin',
            }).then(function (res) {
                return res.json().then(function (data) { return { ok: res.ok, data: data }; });
            });
        }

        wrap.addEventListener('click', function (e) {
            if (e.target === wrap) cerrar();
        });
        wrap.querySelector('[data-cerrar]').addEventListener('click', cerrar);
        function cargarOpciones(prefMonto) {
            if (!celdaActiva) return Promise.resolve();
            syncTipo();
            if (prefMonto != null && Number(prefMonto) > 0) {
                wrap.querySelector('[data-monto-desc]').value = Number(prefMonto).toFixed(2);
                tipoSel.value = 'descuento';
                syncTipo();
            }
            return fetch(celdaActiva.getAttribute('data-url-opciones'), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    const fals = data.faltantes || [];
                    const prest = data.prestamos || [];
                    selFal.innerHTML = fals.length
                        ? fals.map(function (f) {
                            return '<option value="' + f.id + '">$' + Number(f.monto).toFixed(2) + ' · ' + (f.motivo || 'Faltante') + (f.aplicado ? ' (en quincena)' : '') + '</option>';
                        }).join('')
                        : '<option value="">No hay faltantes con monto en Ajustes</option>';
                    selPre.innerHTML = prest.length
                        ? prest.map(function (p) {
                            return '<option value="' + p.id + '">$' + Number(p.monto).toFixed(2) + ' · ' + (p.motivo || 'Préstamo') + (p.aplicado ? ' (en quincena)' : '') + '</option>';
                        }).join('')
                        : '<option value="">No hay préstamos con monto en Ajustes</option>';
                    if (data.lineas) pintarLineas(data.lineas);
                })
                .catch(function () {});
        }

        function abrirModal(celda, conAjuste, prefMonto) {
            celdaActiva = celda;
            titulo.textContent = celda.getAttribute('data-titulo') || 'Desc. / préstamos';
            pintarLineas(leerPayload(celda));
            wrap.classList.add('is-open');
            if (conAjuste) {
                panel.classList.add('is-on');
                cargarOpciones(prefMonto);
            } else {
                panel.classList.remove('is-on');
            }
        }

        wrap.querySelector('[data-ajustar]').addEventListener('click', function () {
            if (!celdaActiva) return;
            panel.classList.toggle('is-on');
            if (!panel.classList.contains('is-on')) return;
            cargarOpciones();
        });
        tipoSel.addEventListener('change', syncTipo);

        wrap.querySelector('[data-aplicar]').addEventListener('click', function () {
            if (!celdaActiva) return;
            const tipo = tipoSel.value;
            const payload = { tipo: tipo };
            if (tipo === 'descuento') {
                payload.monto = wrap.querySelector('[data-monto-desc]').value;
                payload.motivo = wrap.querySelector('[data-motivo-desc]').value;
            } else if (tipo === 'faltante') {
                payload.id = selFal.value;
                if (!payload.id) { alert('No hay faltante con monto. Cárgalo en Ajustes → Faltantes.'); return; }
            } else {
                payload.id = selPre.value;
                if (!payload.id) { alert('No hay préstamo con monto. Cárgalo en Ajustes → Préstamos.'); return; }
            }
            const btn = wrap.querySelector('[data-aplicar]');
            btn.disabled = true;
            postJson(celdaActiva.getAttribute('data-url-aplicar'), payload)
                .then(function (result) {
                    if (!result.ok || !result.data?.ok) throw new Error(result.data?.message || 'No se pudo aplicar.');
                    pintarLineas(result.data.lineas || []);
                    refrescarCelda(result.data);
                    panel.classList.remove('is-on');
                })
                .catch(function (err) { alert(err.message || 'No se pudo aplicar.'); })
                .finally(function () { btn.disabled = false; });
        });

        body.addEventListener('click', function (e) {
            const btn = e.target.closest('.com-desc-anular');
            if (!btn || !celdaActiva) return;
            if (!confirm('¿Anular este concepto de la quincena?')) return;
            btn.disabled = true;
            postJson(celdaActiva.getAttribute('data-url-anular'), {
                origen: btn.getAttribute('data-origen'),
                id: btn.getAttribute('data-id'),
            })
                .then(function (result) {
                    if (!result.ok || !result.data?.ok) throw new Error(result.data?.message || 'No se pudo anular.');
                    pintarLineas(result.data.lineas || []);
                    refrescarCelda(result.data);
                    if (!(result.data.lineas || []).length) cerrar();
                })
                .catch(function (err) { alert(err.message || 'No se pudo anular.'); })
                .finally(function () { btn.disabled = false; });
        });

        document.addEventListener('click', function (e) {
            const link = e.target.closest('.com-desc-link');
            if (link) {
                const celda = link.closest('.com-desc-celda');
                if (!celda) return;
                abrirModal(celda, false);
                return;
            }

            const cero = e.target.closest('.com-desc-cero');
            if (!cero) return;
            const celda = cero.closest('.com-desc-celda');
            if (!celda) return;
            // En $0.00 debe elegir: descuento (monto aquí), faltante o préstamo (desde su apartado).
            abrirModal(celda, true);
        });
    });
    </script>
    @endpush
@endonce
