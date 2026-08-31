@extends('layouts.app')

@section('title', 'Equipo · '.$periodo->etiqueta)

@section('content')
@php
    $totalSalarios = (float) $registros->sum('salario_base');
    $totalOtros = (float) $registros->sum('total_otros_ingresos');
    $totalDeducciones = (float) $registros->sum('total_deducciones');
    $totalPagar = (float) $registros->sum('total_pagar');
@endphp
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <a href="{{ route('nomina.equipo.index') }}" class="muted" style="font-size:.82rem;">← Nómina del equipo</a>
            <h1 style="margin:4px 0 0;">Quincena {{ $periodo->etiqueta }}</h1>
            <p class="muted" style="margin:4px 0 0;">Personal a tu cargo · Estado: <strong>{{ $periodo->estado }}</strong></p>
        </div>
    </div>

    <div class="nomina-kpis">
        <div class="nomina-kpi"><span>Personas</span><strong>{{ $registros->count() }}</strong></div>
        <div class="nomina-kpi"><span>Salarios</span><strong>${{ number_format($totalSalarios, 2) }}</strong></div>
        <div class="nomina-kpi"><span>Horas extras</span><strong>${{ number_format($totalOtros, 2) }}</strong></div>
        <div class="nomina-kpi warn"><span>Deducciones sueldo</span><strong>${{ number_format($totalDeducciones, 2) }}</strong></div>
        <div class="nomina-kpi"><span>Nómina a pagar</span><strong>${{ number_format($totalPagar, 2) }}</strong></div>
    </div>

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Salario</th>
                    <th>Horas extras</th>
                    <th>IAS</th>
                    <th>Adelantos</th>
                    <th>Bonificaciones</th>
                    <th>Deducciones</th>
                    <th>Préstamos sueldo</th>
                    <th>Total deducciones</th>
                    <th>Nómina a pagar</th>
                </tr>
            </thead>
            <tbody>
                @forelse($registros as $registro)
                    @php
                        $desglose = $registro->desglose();
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $registro->empleado?->nombre() ?? '—' }}</strong>
                            <div class="muted" style="font-size:.75rem;">{{ $registro->empleado?->nombreSede() }}</div>
                        </td>
                        <td>${{ number_format($registro->salario_base, 2) }}</td>
                        <td>${{ number_format($desglose['horas_extras'] ?? 0, 2) }}</td>
                        <td>${{ number_format($desglose['inasistencias'] ?? 0, 2) }}</td>
                        <td>${{ number_format($desglose['abonos_sueldo'] ?? 0, 2) }}</td>
                        <td>${{ number_format($registro->montoBonificaciones(), 2) }}</td>
                        <td>${{ number_format($registro->montoDeduccionesAjuste(), 2) }}</td>
                        <td>${{ number_format($desglose['prestamos'] ?? 0, 2) }}</td>
                        <td>${{ number_format($registro->total_deducciones, 2) }}</td>
                        <td><strong>${{ number_format($registro->total_pagar, 2) }}</strong></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="muted">Nadie de tu equipo aparece en esta quincena.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
