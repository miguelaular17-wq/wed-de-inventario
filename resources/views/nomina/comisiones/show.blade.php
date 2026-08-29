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
                    <th>Ventas</th>
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
                        <td>
                            ${{ number_format($liq->totalVentas(), 2) }}
                            @if($liq->modo === \App\Models\Nomina\NominaEmpleado::COMISION_SUPERVISOR_SEDE)
                                <div class="muted" style="font-size:.72rem;">Sede {{ $liq->empleado->nombreSede() }}</div>
                            @elseif($liq->modo === \App\Models\Nomina\NominaEmpleado::COMISION_SUPERVISOR_EQUIPO)
                                <div class="muted" style="font-size:.72rem;">Ventas del equipo</div>
                            @endif
                        </td>
                        @if(in_array($liq->modo, [\App\Models\Nomina\NominaEmpleado::COMISION_SUPERVISOR_SEDE, \App\Models\Nomina\NominaEmpleado::COMISION_SUPERVISOR_EQUIPO], true))
                            <td class="muted">—</td>
                            <td class="muted">—</td>
                        @else
                            <td>${{ number_format($liq->base_telefonia, 2) }}</td>
                            <td>${{ number_format($liq->base_otros, 2) }}</td>
                        @endif
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

    @if($periodo->estado !== 'ABIERTO')
    <div class="nomina-card" style="margin-top:16px;">
        <h3>Archivo para el banco</h3>
        <p class="muted">Un TXT por empresa, igual que en nómina. Tasa BCV hoy: <strong>{{ number_format($tasaBcv, 2) }}</strong>.</p>
        <table class="data-table">
            <thead><tr><th>Empresa</th><th>Empleados</th><th>Comisión USD</th><th>Comisión Bs</th><th></th></tr></thead>
            <tbody>
                @forelse($bancoPorEmpresa as $fila)
                    <tr>
                        <td>
                            @if($fila->empresa)
                                <strong>{{ $fila->empresa->codigo }}</strong>
                                <div class="muted" style="font-size:.78rem;">{{ $fila->empresa->nombre }}</div>
                            @else
                                <span class="muted">Sin empresa asignada</span>
                            @endif
                        </td>
                        <td>{{ $fila->empleados }}</td>
                        <td>${{ number_format($fila->usd, 2) }}</td>
                        <td>Bs {{ number_format($fila->usd * $tasaBcv, 2) }}</td>
                        <td style="text-align:right;">
                            @if($fila->empresa)
                                <a class="btn primary" href="{{ route('nomina.comisiones.banco', [$periodo, $fila->empresa]) }}">Descargar TXT</a>
                            @else
                                <span class="muted">Asigna empresa en la ficha</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No hay comisiones calculadas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
