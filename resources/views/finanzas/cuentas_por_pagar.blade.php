@extends('layouts.app')
@section('title', 'Cuentas por Pagar')

@section('content')
<div class="panel" style="margin-bottom: 16px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <div>
            <h1 style="margin:0 0 6px; color:#9a3412;">Cuentas por Pagar</h1>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            @if(!empty($puedeEditar))
                <button type="button" class="btn primary" onclick="openAbrirCuentaPorPagar()" style="background:#9a3412;">Abrir cuenta por pagar</button>
            @endif
            @if(!auth()->user()->isAuditor())
                <a href="{{ route('finanzas.flujo_caja') }}" class="btn primary">Ir a Flujo de Caja</a>
            @endif
        </div>
    </div>
    @if(session('success'))
        <div style="margin-top:12px; padding:10px 14px; background:#ecfdf5; color:#166534; border:1px solid #a7f3d0; border-radius:8px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div style="margin-top:12px; padding:10px 14px; background:#fef2f2; color:#991b1b; border:1px solid #fecaca; border-radius:8px;">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div style="margin-top:12px; padding:10px 14px; background:#fef2f2; color:#991b1b; border:1px solid #fecaca; border-radius:8px;">
            <ul style="margin:0; padding-left:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

<div class="dashboard-container">
    <div class="panel" style="padding: 0; overflow: hidden; border: 1.5px solid #fed7aa;">
        <div class="table-wrap">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr style="background: #fff7ed;">
                        <th style="width: 100px;">Fecha</th>
                        <th>Beneficiario</th>
                        <th>Tipo Gasto</th>
                        <th>Motivo</th>
                        <th class="col-number" style="text-align: right;">Monto total</th>
                        <th class="col-number" style="text-align: right;">Pagado</th>
                        <th class="col-number" style="text-align: right;">Saldo</th>
                        <th>Estado</th>
                        <th style="text-align: center; width: 160px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cuentas_por_pagar as $cuentaPp)
                        @php
                            $simbolo = strtoupper((string) $cuentaPp->moneda) === 'BS' ? 'Bs. ' : '$';
                        @endphp
                        <tr style="cursor: pointer;" onclick="openHistorialCuentaPorPagar({{ (int) $cuentaPp->id }})">
                            <td>{{ optional($cuentaPp->fecha)->format('Y-m-d') ?? $cuentaPp->fecha }}</td>
                            <td>{{ $cuentaPp->beneficiario ?: '-' }}</td>
                            <td>{{ $cuentaPp->tipo_gasto ?: '-' }}</td>
                            <td>{{ $cuentaPp->motivo ?: '-' }}</td>
                            <td class="col-number" style="text-align: right; font-weight: 500;">{{ $simbolo }}{{ number_format((float) $cuentaPp->monto_total, 2) }}</td>
                            <td class="col-number" style="text-align: right;">{{ $simbolo }}{{ number_format((float) $cuentaPp->monto_pagado, 2) }}</td>
                            <td class="col-number" style="text-align: right; font-weight: 600; color: {{ $cuentaPp->estaAbierta() ? '#c2410c' : '#166534' }};">{{ $simbolo }}{{ number_format((float) $cuentaPp->saldo, 2) }}</td>
                            <td>
                                @if($cuentaPp->estaAbierta())
                                    <span style="background:#ffedd5;color:#9a3412;font-size:0.75rem;padding:2px 8px;border-radius:999px;">Abierta</span>
                                @else
                                    <span style="background:#dcfce7;color:#166534;font-size:0.75rem;padding:2px 8px;border-radius:999px;">Pagada</span>
                                @endif
                            </td>
                            <td style="text-align: center; white-space: nowrap;" onclick="event.stopPropagation();">
                                <button type="button" onclick="openHistorialCuentaPorPagar({{ (int) $cuentaPp->id }})" style="background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; border-radius: 4px; padding: 3px 8px; font-size: 0.8rem; cursor: pointer; margin-right: 4px;">Historial</button>
                                @if($cuentaPp->estaAbierta() && !auth()->user()->isAuditor())
                                    <a href="{{ route('finanzas.flujo_caja', ['pagar_cuenta' => $cuentaPp->id]) }}" style="background: #1a4273; color: white; border: none; border-radius: 4px; padding: 3px 8px; font-size: 0.8rem; cursor: pointer; text-decoration:none; display:inline-block;">Pagar</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align: center; color: #64748b; padding: 16px;">
                                No hay cuentas por pagar. Puedes abrir una aquí (sin egreso) o marcarla al registrar un egreso en Flujo de Caja.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="historialCuentaModal" class="modal-overlay" style="display: none; z-index: 1200;">
    <div class="panel modal-box" style="width: 95%; max-width: 1100px; position: relative; padding: 15px 20px; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); max-height: 95vh; overflow-y: auto;">
        <button type="button" class="modal-close" onclick="closeHistorialCuentaPorPagar()" aria-label="Cerrar" style="position: absolute; right: 15px; top: 15px; background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
        <h3 id="historialCuentaTitulo" style="margin: 0 0 8px; font-size: 1.1rem; color: #9a3412;">Historial</h3>
        <p id="historialCuentaResumen" style="margin: 0 0 12px; color: #64748b; font-size: 0.9rem;"></p>
        <div class="table-wrap">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 100px;">Fecha</th>
                        <th>Origen ➔ Destino (Beneficiario)</th>
                        <th>Tipo Gasto</th>
                        <th>Motivo</th>
                        <th class="col-number" style="text-align: right;">USD</th>
                        <th class="col-number" style="text-align: right;">Tasa Cambio</th>
                        <th class="col-number" style="text-align: right;">Dif. Cambiario</th>
                        <th class="col-number" style="text-align: right;">BS</th>
                        <th class="col-number" style="text-align: right;">Comisión</th>
                    </tr>
                </thead>
                <tbody id="historialCuentaBody"></tbody>
            </table>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top: 14px;">
            <button type="button" onclick="closeHistorialCuentaPorPagar()" style="padding: 8px 16px; background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer;">Cerrar</button>
            @if(!auth()->user()->isAuditor())
                <a id="historialCuentaPagarBtn" href="#" style="padding: 8px 16px; background-color: #1a4273; color: white; border: none; border-radius: 6px; cursor: pointer; text-decoration:none; display:none;">Pagar</a>
            @endif
        </div>
    </div>
</div>

@if(!empty($puedeEditar))
@php
    $sedesAbrirCpp = array_merge(config('inventario.sedes_locales', []), ['Nunes', 'Movistar', 'Depósito', 'Admon', 'Bella vista', 'Jenus']);
@endphp
<div id="abrirCuentaModal" class="modal-overlay" style="display: none; z-index: 1210;">
    <div class="panel modal-box" style="width: 95%; max-width: 560px; position: relative; padding: 18px 22px; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);">
        <button type="button" class="modal-close" onclick="closeAbrirCuentaPorPagar()" aria-label="Cerrar" style="position: absolute; right: 15px; top: 15px; background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
        <h3 style="margin: 0 0 6px; font-size: 1.15rem; color: #9a3412;">Abrir cuenta por pagar</h3>
        <p style="margin: 0 0 14px; color: #64748b; font-size: 0.88rem;">Solo registra el gasto pendiente. No genera egreso ni mueve caja; el pago se hace después desde Flujo de Caja.</p>
        <form method="POST" action="{{ route('finanzas.cuentas_por_pagar.store') }}">
            @csrf
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                    <label style="display:block; margin-bottom:4px; font-weight:500; font-size:0.9rem;">Fecha</label>
                    <input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" required style="width:100%; padding:7px 10px; border:1px solid #cbd5e1; border-radius:6px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:4px; font-weight:500; font-size:0.9rem;">Moneda</label>
                    <select name="moneda" required style="width:100%; padding:7px 10px; border:1px solid #cbd5e1; border-radius:6px; background:white;">
                        <option value="USD" @selected(old('moneda', 'USD') === 'USD')>USD</option>
                        <option value="BS" @selected(old('moneda') === 'BS')>BS</option>
                    </select>
                </div>
                <div style="grid-column: 1 / -1;">
                    <label style="display:block; margin-bottom:4px; font-weight:500; font-size:0.9rem;">Beneficiario</label>
                    <select id="abrir_beneficiario" name="beneficiario" required style="width:100%; padding:7px 10px; border:1px solid #cbd5e1; border-radius:6px; background:white;">
                        <option value="">Seleccione o escriba un beneficiario</option>
                        @foreach(($beneficiarios ?? []) as $nombre)
                            <option value="{{ $nombre }}" @selected(old('beneficiario') === $nombre)>{{ $nombre }}</option>
                        @endforeach
                        @if(old('beneficiario') && ! ($beneficiarios ?? collect())->contains(old('beneficiario')))
                            <option value="{{ old('beneficiario') }}" selected>{{ old('beneficiario') }}</option>
                        @endif
                    </select>
                    <small style="display:block; margin-top:4px; color:#64748b;">Puedes elegir de la lista o escribir uno nuevo.</small>
                </div>
                <div style="grid-column: 1 / -1;">
                    <label style="display:block; margin-bottom:4px; font-weight:500; font-size:0.9rem;">Tipo de gasto</label>
                    <select name="tipo_gasto" required style="width:100%; padding:7px 10px; border:1px solid #cbd5e1; border-radius:6px; background:white;">
                        <option value="">-- Seleccione --</option>
                        @foreach(($tiposGasto ?? []) as $tipo)
                            <option value="{{ $tipo }}" @selected(old('tipo_gasto') === $tipo)>{{ $tipo }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="grid-column: 1 / -1;">
                    <label style="display:block; margin-bottom:4px; font-weight:500; font-size:0.9rem;">Motivo</label>
                    <input type="text" name="motivo" value="{{ old('motivo') }}" maxlength="1000" placeholder="Descripción del gasto" style="width:100%; padding:7px 10px; border:1px solid #cbd5e1; border-radius:6px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:4px; font-weight:500; font-size:0.9rem;">Sede</label>
                    <select name="sede" style="width:100%; padding:7px 10px; border:1px solid #cbd5e1; border-radius:6px; background:white;">
                        <option value="">-- Opcional --</option>
                        @foreach($sedesAbrirCpp as $sedeLocal)
                            <option value="{{ $sedeLocal }}" @selected(old('sede') === $sedeLocal)>{{ $sedeLocal }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; margin-bottom:4px; font-weight:500; font-size:0.9rem;">Monto total</label>
                    <input type="number" name="monto_total" value="{{ old('monto_total') }}" required min="0.01" step="0.01" placeholder="0.00" style="width:100%; padding:7px 10px; border:1px solid #cbd5e1; border-radius:6px;">
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:8px; margin-top: 16px;">
                <button type="button" onclick="closeAbrirCuentaPorPagar()" style="padding: 8px 16px; background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer;">Cancelar</button>
                <button type="submit" style="padding: 8px 16px; background-color: #9a3412; color: white; border: none; border-radius: 6px; cursor: pointer;">Abrir cuenta</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.default.css" rel="stylesheet">
<style>
    #abrirCuentaModal .ts-dropdown { z-index: 1300; }
</style>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
@php
    $cuentasPorPagarJs = ($cuentas_por_pagar ?? collect())->map(function ($cuenta) {
        return [
            'id' => $cuenta->id,
            'fecha' => optional($cuenta->fecha)->format('Y-m-d'),
            'beneficiario' => $cuenta->beneficiario,
            'tipo_gasto' => $cuenta->tipo_gasto,
            'motivo' => $cuenta->motivo,
            'sede' => $cuenta->sede,
            'moneda' => $cuenta->moneda,
            'monto_total' => (float) $cuenta->monto_total,
            'monto_pagado' => (float) $cuenta->monto_pagado,
            'saldo' => (float) $cuenta->saldo,
            'abierta' => $cuenta->estaAbierta(),
            'pagos' => $cuenta->pagos->map(function ($mov) {
                return [
                    'fecha' => $mov->fecha,
                    'banco' => $mov->banco,
                    'titular' => $mov->titular,
                    'banco_receptor' => $mov->banco_receptor,
                    'titular_receptor' => $mov->titular_receptor,
                    'tipo_gasto' => $mov->tipo_gasto,
                    'motivo' => $mov->motivo,
                    'monto_usd' => (float) $mov->monto_usd,
                    'tasa_cambio' => (float) $mov->tasa_cambio,
                    'diferencial_cambiario' => (float) $mov->diferencial_cambiario,
                    'monto_bs' => (float) $mov->monto_bs,
                    'comision' => (float) $mov->comision,
                ];
            })->values(),
        ];
    })->values();
@endphp
<script>
window.CUENTAS_POR_PAGAR = @json($cuentasPorPagarJs);
const pagarCuentaUrl = @json(route('finanzas.flujo_caja'));

function fmtNum(n) {
    const v = Number(n || 0);
    return v ? v.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';
}

window.openHistorialCuentaPorPagar = function(id) {
    const cuenta = (window.CUENTAS_POR_PAGAR || []).find(c => Number(c.id) === Number(id));
    if (!cuenta) return;
    const sim = String(cuenta.moneda).toUpperCase() === 'BS' ? 'Bs. ' : '$';
    document.getElementById('historialCuentaTitulo').textContent = 'Historial — ' + (cuenta.beneficiario || cuenta.motivo || ('Cuenta #' + cuenta.id));
    document.getElementById('historialCuentaResumen').textContent =
        'Total ' + sim + fmtNum(cuenta.monto_total) + ' · Pagado ' + sim + fmtNum(cuenta.monto_pagado) + ' · Saldo ' + sim + fmtNum(cuenta.saldo);
    const body = document.getElementById('historialCuentaBody');
    body.innerHTML = (cuenta.pagos || []).map(mov => {
        const destino = mov.banco_receptor || mov.titular_receptor
            ? `<div style="color:#94a3b8;font-size:1.2rem;">➔</div>
               <div><strong style="color:#10b981;">${mov.banco_receptor || ''}</strong><br><span class="muted" style="font-size:0.85rem;">${mov.titular_receptor || ''}</span></div>`
            : '';
        return `<tr>
            <td>${mov.fecha || '-'}</td>
            <td>
                <div style="display:flex;gap:15px;align-items:center;">
                    <div><strong style="color:var(--blue);">${mov.banco || ''}</strong><br><span class="muted" style="font-size:0.85rem;">${mov.titular || ''}</span></div>
                    ${destino}
                </div>
            </td>
            <td>${mov.tipo_gasto || '-'}</td>
            <td>${mov.motivo || '-'}</td>
            <td class="col-number" style="text-align:right;">${mov.monto_usd ? '$' + fmtNum(mov.monto_usd) : '-'}</td>
            <td class="col-number" style="text-align:right;">${mov.tasa_cambio ? fmtNum(mov.tasa_cambio) : '-'}</td>
            <td class="col-number" style="text-align:right;color:var(--danger);">${mov.diferencial_cambiario ? fmtNum(mov.diferencial_cambiario) : '-'}</td>
            <td class="col-number" style="text-align:right;">${mov.monto_bs ? 'Bs.' + fmtNum(mov.monto_bs) : '-'}</td>
            <td class="col-number" style="text-align:right;">${mov.comision ? fmtNum(mov.comision) : '-'}</td>
        </tr>`;
    }).join('') || `<tr><td colspan="9" style="text-align:center;padding:20px;color:#64748b;">Sin pagos registrados.</td></tr>`;
    const pagarBtn = document.getElementById('historialCuentaPagarBtn');
    if (pagarBtn) {
        if (cuenta.abierta) {
            pagarBtn.style.display = 'inline-block';
            pagarBtn.href = pagarCuentaUrl + '?pagar_cuenta=' + encodeURIComponent(cuenta.id);
        } else {
            pagarBtn.style.display = 'none';
        }
    }
    document.getElementById('historialCuentaModal').style.display = 'flex';
};

window.closeHistorialCuentaPorPagar = function() {
    document.getElementById('historialCuentaModal').style.display = 'none';
};

document.getElementById('historialCuentaModal')?.addEventListener('click', function (e) {
    if (e.target === this) closeHistorialCuentaPorPagar();
});

window.tsAbrirBeneficiario = null;

function initAbrirBeneficiarioSelect() {
    const el = document.getElementById('abrir_beneficiario');
    if (!el || typeof TomSelect === 'undefined' || el.tomselect) return;
    window.tsAbrirBeneficiario = new TomSelect(el, {
        create: true,
        createOnBlur: true,
        persist: true,
        sortField: { field: 'text', direction: 'asc' },
        placeholder: 'Seleccione o escriba un beneficiario',
        maxOptions: null,
        render: {
            option_create: function (data, escape) {
                return '<div class="create">Usar «<strong>' + escape(data.input) + '</strong>»</div>';
            }
        }
    });
}

window.openAbrirCuentaPorPagar = function () {
    const modal = document.getElementById('abrirCuentaModal');
    if (!modal) return;
    modal.style.display = 'flex';
    initAbrirBeneficiarioSelect();
};

window.closeAbrirCuentaPorPagar = function () {
    const modal = document.getElementById('abrirCuentaModal');
    if (modal) modal.style.display = 'none';
};

document.getElementById('abrirCuentaModal')?.addEventListener('click', function (e) {
    if (e.target === this) closeAbrirCuentaPorPagar();
});

document.addEventListener('DOMContentLoaded', function () {
    @if($errors->any() && !empty($puedeEditar))
    openAbrirCuentaPorPagar();
    @endif
});
</script>
@endpush
