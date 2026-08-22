@extends('layouts.app')

@section('title', 'Comisiones '.$periodo->etiqueta)

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <a href="{{ route('nomina.comisiones.index') }}" class="muted" style="font-size:.82rem;">← Comisiones</a>
            <h1 style="margin:4px 0 0;">Comisiones {{ $periodo->etiqueta }}</h1>
            <p class="muted" style="margin:4px 0 0;">
                Pago el {{ $periodo->fecha_pago_comision?->format('d/m/Y') ?: $periodo->fecha_fin?->copy()->addDays(3)->format('d/m/Y') }}.
                Fórmula: comisión + abonos − 10% retención − descuentos/préstamos.
            </p>
        </div>
        <a class="btn secondary" href="{{ route('nomina.periodos.show', $periodo) }}">Ver nómina</a>
    </div>

    @php
        $totalBruto = (float) $liquidaciones->sum('comision_total');
        $totalAbonos = (float) $liquidaciones->sum('abonos');
        $totalRetencion = (float) $liquidaciones->sum('retencion');
        $totalDescuentos = (float) $liquidaciones->sum('descuentos') + (float) $liquidaciones->sum('prestamos');
        $totalPagar = (float) $liquidaciones->sum('total_pagar');
    @endphp

    <div class="nomina-kpis">
        <div class="nomina-kpi"><span>Comisión</span><strong>${{ number_format($totalBruto, 2) }}</strong></div>
        <div class="nomina-kpi"><span>Abonos</span><strong>${{ number_format($totalAbonos, 2) }}</strong></div>
        <div class="nomina-kpi warn"><span>Retención 10%</span><strong>${{ number_format($totalRetencion, 2) }}</strong></div>
        <div class="nomina-kpi warn"><span>Descuentos</span><strong>${{ number_format($totalDescuentos, 2) }}</strong></div>
        <div class="nomina-kpi"><span>A pagar</span><strong>${{ number_format($totalPagar, 2) }}</strong></div>
    </div>

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Modo</th>
                    <th>Base telefonía</th>
                    <th>Base otros</th>
                    <th>Comisión</th>
                    <th>Abonos</th>
                    <th>Retención</th>
                    <th>Desc. / préstamos</th>
                    <th>A pagar</th>
                </tr>
            </thead>
            <tbody>
                @forelse($liquidaciones as $liq)
                    <tr>
                        <td>
                            <a href="{{ route('nomina.empleados.show', ['empleado' => $liq->empleado, 'tab' => 'comisiones']) }}">
                                {{ $liq->empleado->nombre() }}
                            </a>
                        </td>
                        <td>{{ $liq->modo }}</td>
                        <td>${{ number_format($liq->base_telefonia, 2) }}</td>
                        <td>${{ number_format($liq->base_otros, 2) }}</td>
                        <td>${{ number_format($liq->comision_total, 2) }}</td>
                        <td>${{ number_format($liq->abonos, 2) }}</td>
                        <td>${{ number_format($liq->retencion, 2) }}</td>
                        <td>${{ number_format((float) $liq->descuentos + (float) $liq->prestamos, 2) }}</td>
                        <td><strong>${{ number_format($liq->total_pagar, 2) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">Esta quincena todavía no tiene liquidaciones. Calcúlala desde Períodos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
