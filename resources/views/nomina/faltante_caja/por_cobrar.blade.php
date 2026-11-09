@extends('layouts.app')

@section('title', 'Faltantes por cobrar')

@section('content')
@php
    $dinero = fn ($v) => '$'.number_format((float) $v, 2);
    $queryPdf = array_filter($filtros + ['formato' => 'pdf'], fn ($v) => $v !== null && $v !== '' && $v !== 'todos');
@endphp
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Faltantes de caja por cobrar</h1>
            <p class="muted" style="margin:4px 0 0;">
                Lo que falta cobrar: en cuenta (sin decidir) y marcado para descontar en nómina o comisión que aún no se aplica en una quincena.
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="btn secondary" href="{{ route('nomina.faltante_caja.index') }}">← Faltante de caja</a>
            <a class="btn primary" href="{{ route('nomina.faltante_caja.por_cobrar', $queryPdf) }}" target="_blank">Descargar PDF</a>
        </div>
    </div>

    <div class="nomina-kpis" style="margin-top:16px;">
        <div class="nomina-kpi"><span>Personas con deuda</span><strong>{{ $totales['personas'] }}</strong></div>
        <div class="nomina-kpi"><span>En cuenta (sin decidir)</span><strong>{{ $dinero($totales['en_cuenta']) }}</strong></div>
        <div class="nomina-kpi"><span>A descontar nómina</span><strong>{{ $dinero($totales['descontar_nomina']) }}</strong></div>
        <div class="nomina-kpi"><span>A descontar comisión</span><strong>{{ $dinero($totales['descontar_comision']) }}</strong></div>
        <div class="nomina-kpi"><span>Total por cobrar</span><strong style="color:#b91c1c;">{{ $dinero($totales['total']) }}</strong></div>
    </div>

    <form method="GET" class="filter-bar" style="margin-top:16px;">
        <div class="field">
            <label>Buscar persona</label>
            <input type="text" name="q" value="{{ $filtros['q'] }}" placeholder="Nombre o cédula">
        </div>
        <div class="field">
            <label>Sede</label>
            <select name="sede">
                <option value="">Todas</option>
                @foreach($sedes as $sede)
                    <option value="{{ $sede }}" @selected(mb_strtoupper($filtros['sede']) === mb_strtoupper($sede))>{{ $sede }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Estado</label>
            <select name="estado">
                <option value="todos" @selected($filtros['estado'] === 'todos')>Todo lo por cobrar</option>
                <option value="en_cuenta" @selected($filtros['estado'] === 'en_cuenta')>Solo en cuenta (sin decidir)</option>
                <option value="a_descontar" @selected($filtros['estado'] === 'a_descontar')>Solo marcado para descontar</option>
            </select>
        </div>
        <div class="field">
            <label>Registrados hasta</label>
            <input type="date" name="hasta" value="{{ $filtros['hasta'] }}">
        </div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <button class="btn primary" type="submit">Ver reporte</button>
        </div>
    </form>

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Cédula</th>
                    <th>Sede</th>
                    <th style="text-align:right;">En cuenta</th>
                    <th style="text-align:right;">A descontar nómina</th>
                    <th style="text-align:right;">A descontar comisión</th>
                    <th style="text-align:right;">Total por cobrar</th>
                    <th>Desde</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($filas as $i => $fila)
                    @php $empleado = $fila['empleado']; @endphp
                    <tr>
                        <td>
                            <a href="{{ route('nomina.empleados.show', $empleado) }}"><strong>{{ $empleado->nombre() }}</strong></a>
                            <div class="muted" style="font-size:.75rem;">{{ $empleado->nombreCargo() }}</div>
                        </td>
                        <td>{{ $empleado->cedula() ?: '—' }}</td>
                        <td>{{ $empleado->nombreSede() }}</td>
                        <td style="text-align:right;">{{ $dinero($fila['en_cuenta']) }}</td>
                        <td style="text-align:right;">{{ $dinero($fila['descontar_nomina']) }}</td>
                        <td style="text-align:right;">{{ $dinero($fila['descontar_comision']) }}</td>
                        <td style="text-align:right;"><strong style="color:#b91c1c;">{{ $dinero($fila['total']) }}</strong></td>
                        <td style="white-space:nowrap;">{{ $fila['desde']?->format('d/m/Y') }}</td>
                        <td>
                            <button type="button" class="btn secondary" style="padding:4px 10px;font-size:.8rem;"
                                    onclick="const d=document.getElementById('fc-det-{{ $i }}'); d.hidden=!d.hidden;">Detalle ({{ $fila['registros']->count() }})</button>
                        </td>
                    </tr>
                    <tr id="fc-det-{{ $i }}" hidden>
                        <td colspan="9" style="background:#f8fafc;">
                            <table class="data-table" style="margin:0;">
                                <thead>
                                    <tr><th>Fecha</th><th>Motivo</th><th>Estado</th><th>Registró</th><th style="text-align:right;">Monto</th></tr>
                                </thead>
                                <tbody>
                                    @foreach($fila['registros'] as $r)
                                        <tr>
                                            <td style="white-space:nowrap;">{{ $r->fecha?->format('d/m/Y') }}</td>
                                            <td>{{ $r->motivo ?: '—' }}</td>
                                            <td>{{ $r->decision === \App\Models\Nomina\NominaComisionDescuento::DECISION_DESCONTAR ? $r->etiquetaDecision() : 'En cuenta' }}</td>
                                            <td>{{ $r->creador?->name ?? '—' }}</td>
                                            <td style="text-align:right;{{ (float) $r->monto < 0 ? 'color:#15803d;' : '' }}">{{ $dinero($r->monto) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted" style="text-align:center;padding:28px;">No hay faltantes por cobrar con estos filtros.</td></tr>
                @endforelse
            </tbody>
            @if($filas->isNotEmpty())
                <tfoot>
                    <tr>
                        <td colspan="3"><strong>Total</strong></td>
                        <td style="text-align:right;"><strong>{{ $dinero($totales['en_cuenta']) }}</strong></td>
                        <td style="text-align:right;"><strong>{{ $dinero($totales['descontar_nomina']) }}</strong></td>
                        <td style="text-align:right;"><strong>{{ $dinero($totales['descontar_comision']) }}</strong></td>
                        <td style="text-align:right;"><strong style="color:#b91c1c;">{{ $dinero($totales['total']) }}</strong></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
