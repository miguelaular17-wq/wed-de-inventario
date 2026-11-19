@extends('layouts.app')

@section('title', 'Comisiones')

@push('head')
<style>
.com-acciones { display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end; align-items:center; }
.com-acciones .btn { white-space:nowrap; }
.com-reportes { position:relative; display:inline-block; }
.com-reportes[open] > summary { background:#1e3a8a; color:#fff; }
.com-reportes > summary {
    list-style:none; cursor:pointer;
}
.com-reportes > summary::-webkit-details-marker { display:none; }
.com-reportes-menu {
    position:absolute; right:0; top:calc(100% + 4px); z-index:20;
    min-width:220px; background:#fff; border:1px solid #cbd5e1; border-radius:10px;
    box-shadow:0 12px 30px rgba(15,23,42,.16); padding:6px; display:flex; flex-direction:column; gap:2px;
}
.com-reportes-menu a {
    display:block; padding:8px 10px; border-radius:8px; color:#0f172a; text-decoration:none; font-size:.85rem; font-weight:600;
}
.com-reportes-menu a:hover { background:#eff6ff; color:#1e3a8a; }
.com-reportes-menu .muted { padding:6px 10px; font-size:.78rem; }
.com-modal[hidden] { display: none !important; }
.com-modal {
    position: fixed; inset: 0; z-index: 1800;
    background: rgba(15, 23, 42, .55);
    display: flex; align-items: flex-start; justify-content: center;
    padding: 28px 16px; overflow: auto;
}
.com-modal-card {
    background: #fff; border-radius: 16px; width: min(980px, 100%);
    padding: 18px 20px 16px; box-shadow: 0 24px 60px rgba(0,0,0,.28);
}
.com-modal-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 12px; }
.com-modal-card.com-modal-txt { width: min(920px, 100%); }
.txt-acciones { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 0 0 14px; }
.txt-empresas-main { margin: 0 0 12px; }
.txt-empresas-main .data-table { margin: 0; }
.txt-excluir { margin-top: 8px; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 12px; background: #f8fafc; }
.txt-excluir > summary { cursor: pointer; font-weight: 700; color: #334155; list-style: none; }
.txt-excluir > summary::-webkit-details-marker { display: none; }
.txt-excluir > summary::before { content: '▸ '; color: #64748b; }
.txt-excluir[open] > summary::before { content: '▾ '; }
.txt-excluir-body { margin-top: 12px; }
.txt-filtros { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; margin: 0 0 12px; }
.txt-filtros label { display: flex; flex-direction: column; gap: 4px; font-size: .75rem; font-weight: 700; color: #64748b; min-width: 160px; }
.txt-filtros select { padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 600; color: #0f172a; }
.txt-lista-wrap { max-height: min(42vh, 420px); overflow: auto; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; }
.txt-lista-wrap table { margin: 0; }
.txt-lista-wrap tr[hidden] { display: none !important; }
</style>
@endpush

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Liquidación de comisiones</h1>
        </div>
    </div>

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Quincena</th>
                    <th>Estado nómina</th>
                    <th>Pago comisión</th>
                    <th>Empleados</th>
                    <th>Comisión bruta</th>
                    <th>A pagar</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($periodos as $periodo)
                    @php
                        $puedeReportes = $periodo->estado !== 'ABIERTO' && (int) ($periodo->liquidaciones_comision_count ?? 0) > 0;
                        $puedeTxt = $periodo->estado !== 'ABIERTO';
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('nomina.comisiones.show', $periodo) }}" style="font-weight:700;">{{ $periodo->etiqueta }}</a>
                        </td>
                        <td>{{ $periodo->estado }}</td>
                        <td>{{ $periodo->fecha_pago_comision?->format('d/m/Y') ?: $periodo->fecha_fin?->copy()->addDays(3)->format('d/m/Y') }}</td>
                        <td>{{ $periodo->liquidaciones_comision_count }}</td>
                        <td>${{ number_format($periodo->liquidaciones_comision_sum_comision_total ?? 0, 2) }}</td>
                        <td><strong>${{ number_format($periodo->liquidaciones_comision_sum_total_pagar ?? 0, 2) }}</strong></td>
                        <td>
                            <div class="com-acciones">
                                @if($puedeTxt)
                                    <button class="btn secondary" type="button"
                                            data-abrir-txt="{{ route('nomina.comisiones.txt_modal', $periodo) }}"
                                            title="Al descargar el TXT se marca como PAGADO">TXT</button>
                                @else
                                    <span class="btn secondary" style="opacity:.45;pointer-events:none;" title="Calcula la nómina primero">TXT</span>
                                @endif
                                @if($puedeTxt)
                                    <a class="btn secondary" href="{{ route('nomina.comisiones.diferenciales.create', $periodo) }}" title="Recalcular personas: no pagado o solo diferencia">Ajuste</a>
                                @endif
                                <details class="com-reportes">
                                    <summary class="btn secondary">Reportes</summary>
                                    <div class="com-reportes-menu">
                                        @if($puedeReportes)
                                            <a href="{{ route('nomina.comisiones.relacion', ['periodo' => $periodo, 'formato' => 'zip']) }}">ZIP PDF por sede y área</a>
                                            <a href="{{ route('nomina.comisiones.reporte_sedes', $periodo) }}">Ventas netas por sede</a>
                                            <a href="{{ route('nomina.comisiones.relacion', ['periodo' => $periodo, 'formato' => 'xlsx']) }}">Excel</a>
                                            <a href="{{ route('nomina.comisiones.diferenciales.index', $periodo) }}">Diferenciales / ajustes</a>
                                        @else
                                            <span class="muted">Calcula la nómina para descargar reportes.</span>
                                        @endif
                                    </div>
                                </details>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Todavía no hay quincenas. Ábrelas desde Períodos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="com-modal" id="modal-txt" hidden>
    <div class="com-modal-card com-modal-txt">
        <p class="muted" style="margin:0;">Cargando…</p>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('details.com-reportes').forEach(function (det) {
        det.addEventListener('toggle', function () {
            if (!det.open) return;
            document.querySelectorAll('details.com-reportes').forEach(function (otro) {
                if (otro !== det) otro.open = false;
            });
        });
    });
    document.addEventListener('click', function (event) {
        document.querySelectorAll('details.com-reportes[open]').forEach(function (det) {
            if (!det.contains(event.target)) det.open = false;
        });
    });

    const modalTxt = document.getElementById('modal-txt');

    function cerrarModalTxt() {
        if (modalTxt) modalTxt.hidden = true;
    }

    if (modalTxt) {
        modalTxt.addEventListener('click', function (event) {
            if (event.target === modalTxt) cerrarModalTxt();
        });
        modalTxt.addEventListener('click', function (event) {
            if (event.target && event.target.closest('[data-cerrar-modal]')) {
                event.preventDefault();
                cerrarModalTxt();
            }
        });
    }

    function initTxtBanco() {
        const form = document.getElementById('form-txt-banco');
        if (!form || form.dataset.txtReady === '1') return;
        form.dataset.txtReady = '1';

        const filtroSede = document.getElementById('txt-filtro-sede');
        const filtroCargo = document.getElementById('txt-filtro-cargo');
        const contador = document.getElementById('txt-contador');
        const filas = Array.from(form.querySelectorAll('[data-txt-fila]'));
        const accionTodo = form.getAttribute('action');

        function actualizarContador() {
            const n = form.querySelectorAll('[data-txt-check]:checked:not(:disabled)').length;
            if (contador) contador.textContent = n + ' seleccionado' + (n === 1 ? '' : 's');
        }

        function aplicarFiltros() {
            const sede = (filtroSede && filtroSede.value) || '';
            const cargo = (filtroCargo && filtroCargo.value) || '';
            filas.forEach(function (fila) {
                const okSede = !sede || fila.getAttribute('data-sede') === sede;
                const okCargo = !cargo || fila.getAttribute('data-cargo') === cargo;
                fila.hidden = !(okSede && okCargo);
            });
        }

        function checksVisibles(marcar) {
            filas.forEach(function (fila) {
                if (fila.hidden) return;
                const check = fila.querySelector('[data-txt-check]');
                if (check && !check.disabled) check.checked = marcar;
            });
            actualizarContador();
        }

        function idsSeleccionados(empresaId) {
            const ids = [];
            filas.forEach(function (fila) {
                const check = fila.querySelector('[data-txt-check]');
                if (!check || check.disabled || !check.checked) return;
                if (empresaId != null && String(fila.getAttribute('data-empresa-id') || '0') !== String(empresaId)) return;
                ids.push(check.value);
            });
            return ids;
        }

        function enviarSeleccion(ids) {
            if (!ids.length) {
                alert('No hay personas marcadas para esa descarga.');
                return;
            }
            const tmp = document.createElement('form');
            tmp.method = 'POST';
            tmp.action = accionTodo;
            tmp.style.display = 'none';
            const csrf = form.querySelector('input[name="_token"]');
            if (csrf) {
                const token = document.createElement('input');
                token.type = 'hidden';
                token.name = '_token';
                token.value = csrf.value;
                tmp.appendChild(token);
            }
            ids.forEach(function (id) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'empleados[]';
                input.value = id;
                tmp.appendChild(input);
            });
            document.body.appendChild(tmp);
            tmp.submit();
            tmp.remove();
            setTimeout(function () { window.location.reload(); }, 1200);
        }

        if (filtroSede) filtroSede.addEventListener('change', aplicarFiltros);
        if (filtroCargo) filtroCargo.addEventListener('change', aplicarFiltros);
        form.addEventListener('change', function (event) {
            if (event.target && event.target.matches('[data-txt-check]')) actualizarContador();
        });
        const btnMarcar = document.getElementById('txt-marcar-visibles');
        const btnDesmarcar = document.getElementById('txt-desmarcar-visibles');
        if (btnMarcar) btnMarcar.addEventListener('click', function () { checksVisibles(true); });
        if (btnDesmarcar) btnDesmarcar.addEventListener('click', function () { checksVisibles(false); });
        form.addEventListener('submit', function (event) {
            const n = form.querySelectorAll('[data-txt-check]:checked:not(:disabled)').length;
            if (n < 1) {
                event.preventDefault();
                alert('Selecciona al menos una persona para el TXT.');
                return;
            }
            setTimeout(function () { window.location.reload(); }, 1200);
        });
        document.querySelectorAll('[data-txt-empresa]').forEach(function (boton) {
            boton.addEventListener('click', function () {
                enviarSeleccion(idsSeleccionados(boton.getAttribute('data-txt-empresa')));
            });
        });
        actualizarContador();
    }

    document.querySelectorAll('[data-abrir-txt]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            const url = boton.getAttribute('data-abrir-txt');
            if (!url || !modalTxt) return;
            modalTxt.hidden = false;
            modalTxt.innerHTML = '<div class="com-modal-card com-modal-txt"><p class="muted" style="margin:0;">Cargando…</p></div>';
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } })
                .then(function (res) {
                    if (!res.ok) throw new Error('No se pudo cargar el TXT');
                    return res.text();
                })
                .then(function (html) {
                    modalTxt.innerHTML = html;
                    initTxtBanco();
                })
                .catch(function () {
                    modalTxt.innerHTML = '<div class="com-modal-card com-modal-txt"><p class="muted" style="margin:0;">No se pudo cargar el modal.</p><button class="btn secondary" type="button" data-cerrar-modal>Cerrar</button></div>';
                });
        });
    });
});
</script>
@endpush
