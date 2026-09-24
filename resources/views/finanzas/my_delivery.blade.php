@extends('layouts.app')
@section('title', 'My Delivery')

@section('content')
<div class="panel" style="margin-bottom:16px;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
        <div>
            <h1 style="margin:0 0 6px;color:#0f766e;">My Delivery</h1>
            <p class="muted" style="margin:0;font-size:.9rem;">
                Totales por sede de facturas con producto <strong>SERVICIO MY DELIVERY</strong>.
                Monto en USD neto (con descuento {{ rtrim(rtrim(number_format($descuentoPct ?? 25, 2, '.', ''), '0'), '.') }}%).
            </p>
        </div>
    </div>
</div>

<form method="GET" action="{{ route('finanzas.my_delivery') }}" class="filter-bar" style="margin-bottom:16px;">
    <div class="field">
        <label>Desde</label>
        <input type="date" name="desde" value="{{ $desde }}" required>
    </div>
    <div class="field">
        <label>Hasta</label>
        <input type="date" name="hasta" value="{{ $hasta }}" required>
    </div>
    <div class="field" style="display:flex;align-items:flex-end;">
        <button class="btn primary" type="submit">Filtrar</button>
    </div>
</form>

<div class="nomina-kpis" style="margin-bottom:16px;">
    <div class="nomina-kpi"><span>Facturas</span><strong>{{ number_format($totales['facturas']) }}</strong></div>
    <div class="nomina-kpi"><span>Unidades</span><strong>{{ number_format($totales['unidades'], 2) }}</strong></div>
    <div class="nomina-kpi"><span>Monto USD neto</span><strong>${{ number_format($totales['monto'], 2) }}</strong></div>
    <div class="nomina-kpi"><span>Sedes</span><strong>{{ $porSede->count() }}</strong></div>
</div>

<div class="panel" style="padding:0;overflow:hidden;">
    <div class="table-wrap">
        <table class="data-table" style="width:100%;">
            <thead>
                <tr style="background:#f0fdfa;">
                    <th>Sede</th>
                    <th class="col-number" style="text-align:right;">Cantidad facturas</th>
                    <th class="col-number" style="text-align:right;">Unidades</th>
                    <th class="col-number" style="text-align:right;">Monto USD neto</th>
                </tr>
            </thead>
            <tbody>
                @forelse($porSede as $fila)
                    <tr>
                        <td><strong>{{ config('inventario.display.'.$fila['sede'], $fila['sede']) }}</strong></td>
                        <td class="col-number" style="text-align:right;">{{ number_format($fila['facturas']) }}</td>
                        <td class="col-number" style="text-align:right;">{{ number_format($fila['unidades'], 2) }}</td>
                        <td class="col-number" style="text-align:right;font-weight:600;">${{ number_format($fila['monto'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="muted" style="text-align:center;padding:20px;">
                            No hay facturas My Delivery en el rango {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($porSede->isNotEmpty())
                <tfoot>
                    <tr style="background:#ecfeff;font-weight:700;">
                        <td>Total</td>
                        <td class="col-number" style="text-align:right;">{{ number_format($totales['facturas']) }}</td>
                        <td class="col-number" style="text-align:right;">{{ number_format($totales['unidades'], 2) }}</td>
                        <td class="col-number" style="text-align:right;">${{ number_format($totales['monto'], 2) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
