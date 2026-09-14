@extends('layouts.app')
@section('title', 'Cuentas por Pagar')

@section('content')
<div class="panel" style="margin-bottom: 16px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <div>
            <h1 style="margin:0 0 6px; color:#9a3412;">Cuentas por Pagar</h1>
        </div>
        @if(!auth()->user()->isAuditor())
            <a href="{{ route('finanzas.flujo_caja') }}" class="btn primary">Ir a Flujo de Caja</a>
        @endif
    </div>
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
                                No hay cuentas por pagar. Márcalas al registrar un egreso en Flujo de Caja con el monto total del gasto.
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
@endsection

@push('scripts')
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
</script>
@endpush
