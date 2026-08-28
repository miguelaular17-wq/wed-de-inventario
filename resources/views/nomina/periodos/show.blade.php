@extends('layouts.app')

@section('title', 'Nómina '.$periodo->etiqueta)

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <a href="{{ route('nomina.periodos.index') }}" class="muted" style="font-size:.82rem;">← Períodos</a>
            <h1 style="margin:4px 0 0;">Quincena {{ $periodo->etiqueta }}</h1>
            <p class="muted" style="margin:4px 0 0;">Período #{{ $periodo->id }} · Estado actual: <strong>{{ $periodo->estado }}</strong></p>
        </div>
        <div>
            @if($periodo->estado === 'ABIERTO')
                <a class="btn primary" href="{{ route('nomina.periodos.calcular.form', $periodo) }}">Calcular nómina</a>
            @elseif($periodo->estado === 'CALCULADO')
                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
                    <form method="POST" action="{{ route('nomina.periodos.revertir', $periodo) }}" onsubmit="return confirm('¿Deshacer este cálculo? Se borran los recibos y se revierten adelantos, faltas, horas extras y descuentos de préstamo. La quincena queda ABIERTA otra vez.')">
                        @csrf
                        <button class="btn" type="submit">Deshacer cálculo</button>
                    </form>
                    <form method="POST" action="{{ route('nomina.periodos.aprobar', $periodo) }}" onsubmit="return confirm('¿Aprobar estos importes? Después de aprobar ya no podrán modificarse.')">
                        @csrf
                        <button class="btn primary" type="submit">Aprobar nómina</button>
                    </form>
                </div>
            @elseif($periodo->estado === 'APROBADO')
                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
                    <form method="POST" action="{{ route('nomina.periodos.revertir', $periodo) }}" onsubmit="return confirm('Esta nómina ya está aprobada. ¿Deshacer el cálculo de todos modos? Volverá a ABIERTA.')">
                        @csrf
                        <button class="btn" type="submit">Deshacer cálculo</button>
                    </form>
                    <form method="POST" action="{{ route('nomina.periodos.pagar', $periodo) }}" onsubmit="return confirm('¿Confirmar que esta nómina fue pagada?')">
                        @csrf
                        <button class="btn primary" type="submit">Marcar como pagada</button>
                    </form>
                </div>
            @elseif($periodo->estado === 'PAGADO')
                <form method="POST" action="{{ route('nomina.periodos.cerrar', $periodo) }}" onsubmit="return confirm('¿Cerrar definitivamente esta quincena? Quedará en modo de solo lectura.')">
                    @csrf
                    <button class="btn primary" type="submit">Cerrar quincena</button>
                </form>
            @else
                <span class="tag ok">CERRADO</span>
            @endif
        </div>
    </div>

    @php
        $totalSalarios = (float) $periodo->registros->sum('salario_base');
        $totalComisiones = (float) $periodo->liquidacionesComision->sum('total_pagar');
        $totalOtros = (float) $periodo->registros->sum('total_otros_ingresos');
        $totalDeducciones = (float) $periodo->registros->sum('total_deducciones');
        $totalPagar = (float) $periodo->registros->sum('total_pagar');
    @endphp

    <div class="nomina-kpis">
        <div class="nomina-kpi"><span>Empleados</span><strong>{{ $periodo->registros->count() }}</strong></div>
        <div class="nomina-kpi"><span>Salarios</span><strong>${{ number_format($totalSalarios, 2) }}</strong></div>
        <div class="nomina-kpi"><span>Comisiones (pago aparte)</span><strong>${{ number_format($totalComisiones, 2) }}</strong></div>
        <div class="nomina-kpi"><span>Horas extras</span><strong>${{ number_format($totalOtros, 2) }}</strong></div>
        <div class="nomina-kpi warn"><span>Deducciones sueldo</span><strong>${{ number_format($totalDeducciones, 2) }}</strong></div>
        <div class="nomina-kpi"><span>Nómina a pagar</span><strong>${{ number_format($totalPagar, 2) }}</strong></div>
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <h3>Ciclo de la quincena</h3>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            @foreach(['ABIERTO', 'CALCULADO', 'APROBADO', 'PAGADO', 'CERRADO'] as $estado)
                @php
                    $actualIndex = array_search($periodo->estado, ['ABIERTO', 'CALCULADO', 'APROBADO', 'PAGADO', 'CERRADO'], true);
                    $estadoIndex = array_search($estado, ['ABIERTO', 'CALCULADO', 'APROBADO', 'PAGADO', 'CERRADO'], true);
                @endphp
                <span class="tag {{ $estadoIndex <= $actualIndex ? 'ok' : '' }}">{{ $estadoIndex + 1 }}. {{ $estado }}</span>
            @endforeach
        </div>
        @if($periodo->estado === 'ABIERTO')
        <p class="muted" style="margin-bottom:0;">Al calcular se toma una foto de los salarios activos y se elige a quién descontar cuotas de préstamo. Las comisiones se liquidan aparte (retención 10%) y se pagan 3 días después del cierre de la quincena.</p>
        @else
            <p class="muted" style="margin-bottom:0;">Los importes, reglas, ventas y egresos 058 utilizados quedaron congelados al calcular.</p>
        @endif
    </div>

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Salario</th>
                    <th>Comisión</th>
                    <th>Horas extras</th>
                    <th>IAS</th>
                    <th>Adelantos</th>
                    <th>Préstamos sueldo</th>
                    <th>Total deducciones</th>
                    <th>Nómina a pagar</th>
                </tr>
            </thead>
            <tbody>
                @forelse($periodo->registros as $registro)
                    @php
                        $desglose = json_decode($registro->observaciones ?: '{}', true) ?: [];
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('nomina.empleados.show', ['empleado' => $registro->empleado, 'tab' => 'nomina']) }}">
                                {{ $registro->empleado->nombre() }}
                            </a>
                        </td>
                        <td>${{ number_format($registro->salario_base, 2) }}</td>
                        <td>
                            <strong>${{ number_format($desglose['liquidacion']['total_pagar'] ?? $registro->total_comisiones, 2) }}</strong>
                            @if(!empty($desglose['comision']['modo']))
                                <div class="muted" style="font-size:.72rem;">{{ $desglose['comision']['modo'] }} · base ${{ number_format($desglose['comision']['base'] ?? 0, 2) }}</div>
                                @if(($desglose['comision']['gastos'] ?? 0) > 0)
                                    <div class="muted" style="font-size:.72rem;">Egresos 058 (solo ST): ${{ number_format($desglose['comision']['gastos'], 2) }}</div>
                                @endif
                                @if(($desglose['comision']['comision_st'] ?? 0) > 0 || ($desglose['comision']['comision_otros'] ?? 0) > 0 || ($desglose['comision']['comision_telefonia'] ?? 0) > 0)
                                    <div class="muted" style="font-size:.72rem;">
                                        ST ${{ number_format($desglose['comision']['comision_st'] ?? 0, 2) }}
                                        · ventas ${{ number_format(($desglose['comision']['comision_telefonia'] ?? 0) + ($desglose['comision']['comision_otros'] ?? 0), 2) }}
                                    </div>
                                @endif
                                @if(!empty($desglose['liquidacion']['fecha_pago']))
                                    <div class="muted" style="font-size:.72rem;">Pago {{ \Carbon\Carbon::parse($desglose['liquidacion']['fecha_pago'])->format('d/m/Y') }}</div>
                                @endif
                            @endif
                        </td>
                        <td>${{ number_format($desglose['horas_extras'] ?? 0, 2) }}</td>
                        <td>${{ number_format($desglose['inasistencias'] ?? 0, 2) }}</td>
                        <td>${{ number_format($desglose['abonos_sueldo'] ?? 0, 2) }}</td>
                        <td>${{ number_format($desglose['prestamos'] ?? 0, 2) }}</td>
                        <td>${{ number_format($registro->total_deducciones, 2) }}</td>
                        <td><strong>${{ number_format($registro->total_pagar, 2) }}</strong></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="muted">
                            La quincena está abierta. Presiona “Calcular nómina” para generar los recibos.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="muted" style="margin-top:8px;">
        Las comisiones no se suman a la nómina porque se pagan después.
        <a href="{{ route('nomina.comisiones.show', $periodo) }}">Ver liquidación de comisiones</a>
        (pago {{ $periodo->fecha_pago_comision?->format('d/m/Y') ?: $periodo->fecha_fin?->copy()->addDays(3)->format('d/m/Y') }}).
    </p>

    <div class="nomina-card" style="margin-top:16px;">
        <h3>Historial del período</h3>
        <table class="data-table">
            <thead><tr><th>Fecha</th><th>Acción</th><th>Usuario</th></tr></thead>
            <tbody>
                @forelse($historial as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $log->accion }}</td>
                        <td>{{ $log->user?->name ?: 'Sistema' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Sin eventos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
