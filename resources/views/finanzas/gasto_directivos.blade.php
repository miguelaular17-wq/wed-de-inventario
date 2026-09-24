@extends('layouts.app')
@section('title', 'Gasto Directivos')

@section('content')
<div class="panel" style="margin-bottom:16px;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
        <div>
            <h1 style="margin:0 0 6px;color:#1a4273;">Gasto Directivos</h1>
            <p class="muted" style="margin:0;font-size:.9rem;">
                Cobranza de José Leonardo Jerez y María Núñez, más egresos de flujo de caja
                bajo conceptos de directivos (093, 047, 041, 040, 039, 034, 016).
            </p>
        </div>
        <a
            class="btn primary"
            href="{{ route('finanzas.gasto_directivos.reporte', ['desde' => $desde, 'hasta' => $hasta]) }}"
        >
            Descargar PDF
        </a>
    </div>
</div>

<form method="GET" action="{{ route('finanzas.gasto_directivos') }}" class="filter-bar" style="margin-bottom:16px;">
    <div class="field">
        <label>Desde</label>
        <input type="date" name="desde" value="{{ $desde }}" required>
    </div>
    <div class="field">
        <label>Hasta</label>
        <input type="date" name="hasta" value="{{ $hasta }}" required>
    </div>
    <div class="field" style="display:flex;align-items:flex-end;gap:8px;">
        <button class="btn primary" type="submit">Filtrar</button>
        <a class="btn" href="{{ route('finanzas.gasto_directivos') }}">Limpiar</a>
    </div>
</form>

<div class="nomina-kpis" style="margin-bottom:16px;">
    <div class="nomina-kpi"><span>Egresos USD</span><strong>${{ number_format($totales['egresos_usd'], 2) }}</strong></div>
    <div class="nomina-kpi"><span>Egresos Bs</span><strong>Bs {{ number_format($totales['egresos_bs'], 2) }}</strong></div>
    <div class="nomina-kpi"><span>Movimientos</span><strong>{{ number_format($totales['egresos_count']) }}</strong></div>
    <div class="nomina-kpi"><span>Saldo cobranza</span><strong>${{ number_format($totales['cobranza_saldo'], 2) }}</strong></div>
</div>

<div class="panel" style="margin-bottom:16px;">
    <h3 style="margin:0 0 12px;color:#1a4273;">Cobranza — Directivos</h3>
    <div class="table-wrap">
        <table class="data-table" style="width:100%;">
            <thead>
                <tr style="background:#eff6ff;">
                    <th>Código</th>
                    <th>Nombre</th>
                    <th class="col-number" style="text-align:right;">Documentos</th>
                    <th class="col-number" style="text-align:right;">Saldo USD</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($personas as $p)
                    <tr>
                        <td><code>{{ $p['codigo'] }}</code></td>
                        <td><strong>{{ $p['nombre'] }}</strong></td>
                        <td class="col-number" style="text-align:right;">{{ $p['documentos'] }}</td>
                        <td class="col-number" style="text-align:right;font-weight:700;">${{ number_format($p['saldo'], 2) }}</td>
                        <td>
                            <a class="btn secondary" href="{{ route('cobranza.index', ['buscar_cliente' => $p['nombre'], 'mostrar_clientes' => 'personales']) }}">Ver en cobranza</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:#eff6ff;font-weight:700;">
                    <td colspan="3">Total cobranza</td>
                    <td class="col-number" style="text-align:right;">${{ number_format($totales['cobranza_saldo'], 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="panel" style="margin-bottom:16px;">
    <h3 style="margin:0 0 12px;color:#1a4273;">Egresos por concepto ({{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }})</h3>
    <div class="table-wrap">
        <table class="data-table" style="width:100%;">
            <thead>
                <tr style="background:#f0fdfa;">
                    <th>Concepto</th>
                    <th class="col-number" style="text-align:right;">Movimientos</th>
                    <th class="col-number" style="text-align:right;">USD</th>
                    <th class="col-number" style="text-align:right;">Bs</th>
                </tr>
            </thead>
            <tbody>
                @foreach($egresos_por_concepto as $c)
                    <tr @if($c['count'] === 0) style="opacity:.55;" @endif>
                        <td>
                            <strong>{{ $c['codigo'] }}</strong>
                            <span class="muted"> — {{ $c['label'] }}</span>
                        </td>
                        <td class="col-number" style="text-align:right;">{{ $c['count'] }}</td>
                        <td class="col-number" style="text-align:right;font-weight:600;">${{ number_format($c['usd'], 2) }}</td>
                        <td class="col-number" style="text-align:right;">Bs {{ number_format($c['bs'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:#ecfeff;font-weight:700;">
                    <td>Total egresos</td>
                    <td class="col-number" style="text-align:right;">{{ number_format($totales['egresos_count']) }}</td>
                    <td class="col-number" style="text-align:right;">${{ number_format($totales['egresos_usd'], 2) }}</td>
                    <td class="col-number" style="text-align:right;">Bs {{ number_format($totales['egresos_bs'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="panel" style="padding:0;overflow:hidden;">
    <div style="padding:16px 16px 0;">
        <h3 style="margin:0 0 12px;color:#1a4273;">Detalle de egresos</h3>
    </div>
    <div class="table-wrap">
        <table class="data-table" style="width:100%;">
            <thead>
                <tr style="background:#f8fafc;">
                    <th>Fecha</th>
                    <th>Concepto</th>
                    <th>Motivo</th>
                    <th class="col-number" style="text-align:right;">USD</th>
                    <th class="col-number" style="text-align:right;">Bs</th>
                </tr>
            </thead>
            <tbody>
                @forelse($egresos as $mov)
                    <tr>
                        <td>{{ $mov->fecha ? \Carbon\Carbon::parse($mov->fecha)->format('d/m/Y') : '—' }}</td>
                        <td>{{ $mov->tipo_gasto ?: '—' }}</td>
                        <td>{{ $mov->motivo ?: '—' }}</td>
                        <td class="col-number" style="text-align:right;font-weight:600;">${{ number_format((float) $mov->monto_usd, 2) }}</td>
                        <td class="col-number" style="text-align:right;">Bs {{ number_format((float) $mov->monto_bs, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted" style="text-align:center;padding:24px;">
                            No hay egresos de directivos en el rango seleccionado.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
