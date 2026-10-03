@extends('layouts.app')

@section('title', 'Nómina '.$periodo->etiqueta)

@push('head')
<style>
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
.com-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
.com-tabs .btn.is-on { background: #1e3a8a; color: #fff; }
[data-ajuste-panel][hidden] { display: none !important; }
.com-alta { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; margin-bottom: 12px; }
.com-alta-grid { display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end; }
.com-alta-grid label { display: flex; flex-direction: column; gap: 4px; font-size: .75rem; font-weight: 700; color: #64748b; min-width: 120px; }
.com-alta-grid label.com-alta-empleado { position: relative; flex: 1; min-width: 220px; }
.com-alta-grid input, .com-alta-grid select { font-weight: 600; color: #0f172a; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px; }
.com-alta-lista { position: absolute; z-index: 5; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; margin-top: 4px; max-height: 220px; overflow: auto; box-shadow: 0 12px 30px rgba(15,23,42,.12); }
.com-alta-lista button { display: block; width: 100%; text-align: left; background: #fff; border: 0; border-bottom: 1px solid #f1f5f9; padding: 8px 10px; cursor: pointer; }
.com-alta-lista button:hover { background: #eff6ff; }
</style>
@endpush

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
                    <form method="POST" action="{{ route('nomina.periodos.recalcular', $periodo) }}" onsubmit="return confirm('¿Recalcular esta quincena con los datos actuales?')">
                        @csrf
                        <button class="btn" type="submit">Recalcular</button>
                    </form>
                    <form method="POST" action="{{ route('nomina.periodos.aprobar', $periodo) }}" onsubmit="return confirm('¿Cerrar esta nómina? Los importes quedan fijos.')">
                        @csrf
                        <button class="btn primary" type="submit">Cerrar nómina</button>
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
            @if($periodo->estado !== 'ABIERTO' && $periodo->registros->isNotEmpty())
                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;margin-top:8px;">
                    <a class="btn primary" href="{{ route('nomina.periodos.relacion', $periodo) }}">Descargar relación PDF</a>
                    <a class="btn" href="{{ route('nomina.periodos.relacion', ['periodo' => $periodo, 'formato' => 'xlsx']) }}">Descargar Excel</a>
                    <a class="btn" href="{{ route('nomina.periodos.relacion', ['periodo' => $periodo, 'formato' => 'zip']) }}">ZIP PDF por sede y área</a>
                </div>
            @endif
        </div>
    </div>

    @php
        $totalSalarios = (float) $periodo->registros->sum('salario_base');
        $totalComisiones = (float) $periodo->liquidacionesComision->sum('total_pagar');
        $totalOtros = (float) $periodo->registros->sum('total_otros_ingresos');
        $totalDeducciones = (float) $periodo->registros->sum('total_deducciones');
        $totalPagar = (float) $periodo->registros->sum('total_pagar');
        $sedeArea = app(\App\Services\Nomina\PayrollSedeAreaTotals::class);
    @endphp

    <div class="nomina-kpis">
        <div class="nomina-kpi"><span>Empleados</span><strong>{{ $periodo->registros->count() }}</strong></div>
        <div class="nomina-kpi"><span>Salarios</span><strong>${{ number_format($totalSalarios, 2) }}</strong></div>
        <div class="nomina-kpi"><span>Comisiones (pago aparte)</span><strong>${{ number_format($totalComisiones, 2) }}</strong></div>
        <div class="nomina-kpi"><span>Horas extras</span><strong>${{ number_format($totalOtros, 2) }}</strong></div>
        <div class="nomina-kpi warn"><span>Deducciones sueldo</span><strong>${{ number_format($totalDeducciones, 2) }}</strong></div>
        <div class="nomina-kpi"><span>Nómina a pagar</span><strong>${{ number_format($totalPagar, 2) }}</strong></div>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px;">
        <button class="btn" type="button" data-abrir-modal="modal-ajustes">Ajustes de la quincena</button>
        <button class="btn" type="button" data-abrir-modal="modal-sedes">Totales por sede y área</button>
        @if($periodo->estado !== 'ABIERTO')
            <button class="btn" type="button" data-abrir-modal="modal-txt">TXT del banco</button>
        @endif
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
        <p class="muted" style="margin-bottom:0;">Al calcular se toma una foto de los salarios activos. Las cuotas de préstamo salen de lo que armaste en <a href="{{ route('nomina.prestamos.index') }}">Préstamos</a> (nómina o comisión). Las comisiones se liquidan aparte (retención 10%) y se pagan 3 días después del cierre de la quincena.</p>
        @else
            <p class="muted" style="margin-bottom:0;">Los importes, reglas, ventas y egresos 058 utilizados quedaron congelados al calcular.</p>
        @endif
    </div>

    @include('nomina.partials.empleado-tabla-buscador', ['target' => 'tabla-nomina-periodo'])

    <div class="table-wrap" style="margin-top:8px;">
        <table class="data-table" id="tabla-nomina-periodo">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Salario</th>
                    <th>Comisión</th>
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
                @forelse($periodo->registros as $registro)
                    @php
                        $desglose = $registro->desglose();
                        $lineasDesc = $registro->lineasDescuentoTotal();
                    @endphp
                    <tr data-grupo-clave="{{ $sedeArea->grupoDeEmpleado($registro->empleado)['clave'] }}" data-empleado-buscar="{{ mb_strtolower(trim(implode(' ', array_filter([
                        $registro->empleado->nombre(),
                        $registro->empleado->cedula(),
                        $registro->empleado->nombreSede(),
                        $registro->empleado->nombreCargo(),
                    ])))) }}">
                        <td>
                            <a href="{{ route('nomina.empleados.show', ['empleado' => $registro->empleado, 'tab' => 'nomina']) }}">
                                {{ $registro->empleado->nombre() }}
                            </a>
                        </td>
                        <td>
                            ${{ number_format($registro->salario_base, 2) }}
                            @if(!empty($desglose['salario_prorrateado']))
                                <div class="muted" style="font-size:.72rem;">
                                    {{ (int) ($desglose['dias_trabajados'] ?? 0) }} día(s)
                                    · ${{ number_format($desglose['valor_dia'] ?? 0, 2) }}/día
                                    @if(!empty($desglose['salario_desde']))
                                        · desde {{ \Carbon\Carbon::parse($desglose['salario_desde'])->format('d/m/Y') }}
                                    @endif
                                </div>
                            @endif
                        </td>
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
                        <td>
                            @include('nomina.partials.descuento-comentarios', [
                                'monto' => $desglose['inasistencias'] ?? 0,
                                'lineas' => collect($lineasDesc)->where('grupo', 'inasistencia')->values()->all(),
                                'titulo' => 'Inasistencias',
                            ])
                        </td>
                        <td>
                            @include('nomina.partials.descuento-comentarios', [
                                'monto' => $desglose['abonos_sueldo'] ?? 0,
                                'lineas' => collect($lineasDesc)->where('grupo', 'adelanto')->values()->all(),
                                'titulo' => 'Adelantos',
                            ])
                        </td>
                        <td>${{ number_format($registro->montoBonificaciones(), 2) }}</td>
                        <td>
                            @include('nomina.partials.descuento-comentarios', [
                                'monto' => $registro->montoDeduccionesAjuste(),
                                'lineas' => collect($lineasDesc)->whereIn('grupo', ['deduccion', 'mercancia', 'faltante_caja'])->values()->all(),
                                'titulo' => 'Deducciones',
                            ])
                        </td>
                        <td>
                            @include('nomina.partials.descuento-comentarios', [
                                'monto' => $desglose['prestamos'] ?? 0,
                                'lineas' => collect($lineasDesc)->where('grupo', 'prestamo')->values()->all(),
                                'titulo' => 'Préstamos',
                            ])
                        </td>
                        <td>
                            @include('nomina.partials.descuento-comentarios', [
                                'monto' => $registro->total_deducciones,
                                'lineas' => $lineasDesc,
                                'titulo' => 'Total deducciones',
                            ])
                        </td>
                        <td><strong>${{ number_format($registro->total_pagar, 2) }}</strong></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="muted">
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

</div>

@php
    $movimientos = $movimientos ?? [
        'extras' => collect(),
        'ias' => collect(),
        'adelantos' => collect(),
        'bonos' => collect(),
        'deducciones' => collect(),
        'mercancia' => collect(),
        'faltantes' => collect(),
        'otras' => collect(),
    ];
    $puedeAjustar = ! in_array($periodo->estado, ['PAGADO', 'CERRADO'], true);
@endphp

<div class="com-modal" id="modal-ajustes" hidden>
    <div class="com-modal-card">
        <div class="com-modal-head">
            <div>
                <h3 style="margin:0;">Ajustes de la quincena</h3>
                <p class="muted" style="margin:4px 0 0;">Marca lo que se queda y pulsa Aplicar. Lo que desmarques sale de esta quincena. Arriba de cada lista puedes registrar uno nuevo.</p>
            </div>
            <button class="btn secondary" type="button" data-cerrar-modal>Cerrar</button>
        </div>
        <div class="com-tabs">
            <button type="button" class="btn is-on" data-ajuste-tab="extras">Horas extras</button>
            <button type="button" class="btn" data-ajuste-tab="ias">IAS</button>
            <button type="button" class="btn" data-ajuste-tab="adelantos">Adelantos</button>
            <button type="button" class="btn" data-ajuste-tab="bonos">Bonificaciones</button>
            <button type="button" class="btn" data-ajuste-tab="deducciones">Deducciones</button>
        </div>
        @if($puedeAjustar)
            <div data-ajuste-panel="extras">
                @include('nomina.periodos._movimiento_alta', ['periodo' => $periodo, 'concepto' => 'extras'])
            </div>
            <div data-ajuste-panel="ias" hidden>
                @include('nomina.periodos._movimiento_alta', ['periodo' => $periodo, 'concepto' => 'ias'])
            </div>
            <div data-ajuste-panel="adelantos" hidden>
                @include('nomina.periodos._movimiento_alta', ['periodo' => $periodo, 'concepto' => 'adelanto'])
            </div>
            <div data-ajuste-panel="bonos" hidden>
                @include('nomina.periodos._movimiento_alta', ['periodo' => $periodo, 'concepto' => 'bono'])
            </div>
            <div data-ajuste-panel="deducciones" hidden>
                @include('nomina.periodos._movimiento_alta', ['periodo' => $periodo, 'concepto' => 'deduccion'])
            </div>
        @endif
        <form method="POST" action="{{ route('nomina.periodos.movimientos.aplicar', $periodo) }}" onsubmit="return confirm('¿Aplicar esta selección? Lo que no esté marcado se quita de la quincena.')">
            @csrf
            <input type="hidden" name="tab" id="periodo-ajuste-tab" value="extras">
            <div data-ajuste-panel="extras">
                @include('nomina.comisiones._ajuste_tabla', [
                    'filas' => $movimientos['extras'],
                    'campo' => 'extra_ids',
                    'puede' => $puedeAjustar,
                    'etiqueta' => fn ($fila) => trim(number_format((float) $fila->horas, 2).' '.($fila->etiquetaUnidad() ?? 'horas').($fila->motivo ? ' · '.$fila->motivo : '')),
                ])
            </div>
            <div data-ajuste-panel="ias" hidden>
                @include('nomina.comisiones._ajuste_tabla', [
                    'filas' => $movimientos['ias'],
                    'campo' => 'ias_ids',
                    'puede' => $puedeAjustar,
                    'etiqueta' => fn ($fila) => trim(number_format((float) $fila->cantidad, 2).' día(s)'.($fila->motivo ? ' · '.$fila->motivo : '')),
                ])
            </div>
            <div data-ajuste-panel="adelantos" hidden>
                @include('nomina.comisiones._ajuste_tabla', [
                    'filas' => $movimientos['adelantos'],
                    'campo' => 'adelanto_ids',
                    'puede' => $puedeAjustar,
                    'etiqueta' => fn ($fila) => $fila->motivo ?: 'Adelanto',
                ])
            </div>
            <div data-ajuste-panel="bonos" hidden>
                @include('nomina.comisiones._ajuste_tabla', [
                    'filas' => $movimientos['bonos'],
                    'campo' => 'bono_ids',
                    'puede' => $puedeAjustar,
                    'etiqueta' => fn ($fila) => $fila->motivo ?: 'Bonificación',
                ])
            </div>
            <div data-ajuste-panel="deducciones" hidden>
                @include('nomina.comisiones._ajuste_tabla', [
                    'filas' => $movimientos['deducciones']->concat($movimientos['mercancia'])->concat($movimientos['faltantes'])->concat($movimientos['otras']),
                    'campo' => null,
                    'puede' => $puedeAjustar,
                    'etiqueta' => fn ($fila) => $fila->motivo ?: 'Descuento',
                    'campoDe' => function ($fila) {
                        if ($fila instanceof \App\Models\Nomina\NominaEmpleadoAjuste) return 'deduccion_ids';
                        if ($fila instanceof \App\Models\Nomina\NominaDescuentoMercancia) return 'mercancia_ids';
                        if ($fila instanceof \App\Models\Nomina\NominaComisionDescuento) return 'faltante_ids';
                        return 'otra_ids';
                    },
                ])
            </div>
            @if($puedeAjustar)
                <div style="margin-top:12px;">
                    <button class="btn primary" type="submit">Aplicar selección</button>
                </div>
            @endif
        </form>
    </div>
</div>

<div class="com-modal" id="modal-sedes" hidden>
    <div class="com-modal-card">
        <div class="com-modal-head">
            <h3 style="margin:0;">Totales por sede y área</h3>
            <button class="btn secondary" type="button" data-cerrar-modal>Cerrar</button>
        </div>
        @include('nomina.partials.totales-sede-area', [
            'totalesPorGrupo' => $totalesPorGrupo ?? collect(),
            'tasaBcv' => $tasaBcv ?? 0,
            'tasaBcvEtiqueta' => 'Tasa BCV del cierre ('.($periodo->fecha_fin?->format('d/m/Y') ?: '—').')',
            'filtroTargets' => ['tabla-nomina-periodo'],
            'pdfRoute' => $periodo->estado !== 'ABIERTO' ? route('nomina.periodos.reporte_sedes', $periodo) : null,
            'pdfPorGrupo' => $periodo->estado !== 'ABIERTO' ? route('nomina.periodos.relacion', $periodo) : null,
            'mostrarTitulo' => false,
        ])
        @if(($totalesPorGrupo ?? collect())->isEmpty())
            <p class="muted">Todavía no hay totales por sede o área en esta quincena.</p>
        @endif
    </div>
</div>

@if($periodo->estado !== 'ABIERTO')
<div class="com-modal" id="modal-txt" hidden>
    <div class="com-modal-card">
        <div class="com-modal-head">
            <div>
                <h3 style="margin:0;">Archivo para el banco</h3>
                <p class="muted" style="margin:4px 0 0;">Los que tienen empresa se descargan. Los que no, hay que asignarla en la ficha. Tasa BCV del cierre ({{ $periodo->fecha_fin?->format('d/m/Y') }}): <strong>{{ number_format($tasaBcv, 2) }}</strong>.</p>
            </div>
            <button class="btn secondary" type="button" data-cerrar-modal>Cerrar</button>
        </div>
        <table class="data-table">
            <thead><tr><th>Empresa</th><th>Empleados</th><th>Nómina USD</th><th>Nómina Bs</th><th></th></tr></thead>
            <tbody>
                @forelse($bancoPorEmpresa as $fila)
                    @if($fila->empresa)
                        <tr>
                            <td>
                                <strong>{{ $fila->empresa->codigo }}</strong>
                                <div class="muted" style="font-size:.78rem;">{{ $fila->empresa->nombre }}</div>
                            </td>
                            <td>{{ $fila->empleados }}</td>
                            <td>${{ number_format($fila->usd, 2) }}</td>
                            <td>Bs {{ number_format($fila->usd * $tasaBcv, 2) }}</td>
                            <td style="text-align:right;">
                                <a class="btn primary" href="{{ route('nomina.periodos.banco', [$periodo, $fila->empresa]) }}">Descargar TXT</a>
                            </td>
                        </tr>
                    @else
                        <tr>
                            <td colspan="5"><strong>Sin empresa</strong> · {{ $fila->empleados }} persona(s) · ${{ number_format($fila->usd, 2) }}</td>
                        </tr>
                        @foreach(($fila->personas ?? collect()) as $persona)
                            <tr>
                                <td style="padding-left:24px;">
                                    <a href="{{ route('nomina.empleados.show', ['empleado' => $persona->id, 'tab' => 'nomina']) }}">{{ $persona->nombre }}</a>
                                    <span class="muted">· {{ $persona->cedula !== '' ? $persona->cedula : 'Sin cédula' }}</span>
                                </td>
                                <td>1</td>
                                <td>${{ number_format($persona->usd ?? 0, 2) }}</td>
                                <td>Bs {{ number_format(($persona->usd ?? 0) * $tasaBcv, 2) }}</td>
                                <td class="muted">No se descarga</td>
                            </tr>
                        @endforeach
                    @endif
                @empty
                    <tr><td colspan="5" class="muted">No hay recibos calculados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-abrir-modal]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            const modal = document.getElementById(boton.getAttribute('data-abrir-modal'));
            if (modal) modal.hidden = false;
        });
    });
    document.querySelectorAll('[data-cerrar-modal]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            const modal = boton.closest('.com-modal');
            if (modal) modal.hidden = true;
        });
    });
    document.querySelectorAll('.com-modal').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) modal.hidden = true;
        });
    });
    function activarAjuste(nombre) {
        document.querySelectorAll('[data-ajuste-tab]').forEach(function (otro) {
            otro.classList.toggle('is-on', otro.getAttribute('data-ajuste-tab') === nombre);
        });
        document.querySelectorAll('[data-ajuste-panel]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-ajuste-panel') !== nombre;
        });
        const tab = document.getElementById('periodo-ajuste-tab');
        if (tab) tab.value = nombre;
    }
    document.querySelectorAll('[data-ajuste-tab]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            activarAjuste(boton.getAttribute('data-ajuste-tab'));
        });
    });
    const tabInicial = new URLSearchParams(window.location.search).get('ajuste');
    if (tabInicial && document.getElementById('modal-ajustes')) {
        document.getElementById('modal-ajustes').hidden = false;
        activarAjuste(tabInicial);
    }
    document.querySelectorAll('form.com-alta').forEach(function (form) {
        const campo = form.querySelector('input[name="empleado_nombre"]');
        const idCampo = form.querySelector('input[name="empleado_id"]');
        const lista = form.querySelector('.com-alta-lista');
        const url = form.getAttribute('data-buscar-empleados');
        let timer = null;
        if (!campo || !lista || !url) return;
        campo.addEventListener('input', function () {
            idCampo.value = '';
            campo.setCustomValidity('');
            clearTimeout(timer);
            const texto = campo.value.trim();
            if (texto.length < 2) {
                lista.hidden = true;
                lista.innerHTML = '';
                return;
            }
            timer = setTimeout(function () {
                fetch(url + '?q=' + encodeURIComponent(texto), { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (filas) {
                        lista.innerHTML = '';
                        if (!filas.length) {
                            lista.hidden = true;
                            return;
                        }
                        filas.forEach(function (fila) {
                            const boton = document.createElement('button');
                            boton.type = 'button';
                            boton.textContent = fila.nombre + (fila.cedula ? ' · ' + fila.cedula : '') + (fila.sede ? ' · ' + fila.sede : '');
                            boton.addEventListener('click', function () {
                                idCampo.value = fila.id;
                                campo.value = fila.nombre;
                                lista.hidden = true;
                            });
                            lista.appendChild(boton);
                        });
                        lista.hidden = false;
                    })
                    .catch(function () { lista.hidden = true; });
            }, 250);
        });
        form.addEventListener('submit', function (event) {
            if (!idCampo.value) {
                event.preventDefault();
                campo.focus();
                campo.setCustomValidity('Elige una persona de la lista.');
                campo.reportValidity();
            }
        });
    });
});
</script>
@endpush
