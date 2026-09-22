@extends('layouts.app')

@section('title', 'Deudas del Personal')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Deudas del Personal</h1>
            <p class="muted" style="margin:4px 0 0;">
                Faltantes de caja, préstamos, cobranza marcada como <strong>PERSONAL</strong> y otros descuentos.
            </p>
        </div>
    </div>

    <div class="nomina-kpis">
        <div class="nomina-kpi"><span>Total adeudado</span><strong>${{ number_format($resumen['total_adeudado'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Faltantes de caja</span><strong>${{ number_format($resumen['faltantes_caja'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Préstamos</span><strong>${{ number_format($resumen['prestamos'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Cobranza</span><strong>${{ number_format($resumen['cobranza'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Pagado</span><strong>${{ number_format($resumen['pagado'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Saldo pendiente</span><strong>${{ number_format($resumen['saldo_pendiente'], 2) }}</strong></div>
    </div>
    <p class="muted" style="margin-top:8px;">
        {{ $resumen['personas'] }} persona(s) con deudas
        @if(($resumen['personas_cobranza_personal'] ?? 0) > 0)
            · {{ $resumen['personas_cobranza_personal'] }} con cobranza <strong>PERSONAL</strong>
        @endif
    </p>

    <form method="GET" class="filter-bar" style="margin-top:16px; display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; align-items:end;">
        <div class="field">
            <label>Sede</label>
            <select name="sede">
                <option value="">Todas</option>
                @foreach($sedes as $sede)
                    <option value="{{ $sede['value'] }}" @selected(($filtros['sede'] ?? '') == $sede['value'])>{{ $sede['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Empleado</label>
            <select name="empleado_id">
                <option value="">Todos</option>
                @foreach($empleadosOpciones as $op)
                    <option value="{{ $op['id'] }}" @selected((int)($filtros['empleado_id'] ?? 0) === (int)$op['id'])>{{ $op['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Tipo de deuda</label>
            <select name="tipo">
                <option value="">Todos</option>
                @foreach($tipos as $key => $label)
                    <option value="{{ $key }}" @selected(($filtros['tipo'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Estado</label>
            <select name="estado">
                <option value="">Todos</option>
                @foreach($estados as $key => $label)
                    <option value="{{ $key }}" @selected(($filtros['estado'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Desde</label>
            <input type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}">
        </div>
        <div class="field">
            <label>Hasta</label>
            <input type="date" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}">
        </div>
        <div class="field">
            <label>Monto mín.</label>
            <input type="number" step="0.01" name="monto_min" value="{{ $filtros['monto_min'] ?? '' }}" placeholder="0">
        </div>
        <div class="field">
            <label>Monto máx.</label>
            <input type="number" step="0.01" name="monto_max" value="{{ $filtros['monto_max'] ?? '' }}" placeholder="">
        </div>
        <div class="field field-wide">
            <label>Buscar</label>
            <input type="text" name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="Nombre o cédula">
        </div>
        <div class="field" style="display:flex; gap:8px;">
            <button class="btn primary" type="submit">Filtrar</button>
            <a class="btn" href="{{ route('nomina.deudas.index') }}">Limpiar</a>
        </div>
    </form>

    <div class="nomina-card" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Sede</th>
                    <th class="text-right">Faltante</th>
                    <th class="text-right">Préstamos</th>
                    <th class="text-right">Cobranza</th>
                    <th class="text-right">Otros</th>
                    <th class="text-right">Pagado</th>
                    <th class="text-right">Saldo</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($filas as $fila)
                    <tr>
                        <td>
                            <strong>{{ $fila['nombre'] }}</strong>
                            <div class="muted" style="font-size:.78rem;">{{ $fila['cedula'] }}</div>
                            @if(!empty($fila['cobranza_es_personal']) && $fila['cobranza'] > 0)
                                <span class="tag" style="background:#fef3c7;color:#92400e;font-size:.7rem;">PERSONAL</span>
                            @endif
                        </td>
                        <td>{{ $fila['sede'] ?: '—' }}</td>
                        <td class="text-right">${{ number_format($fila['faltante_caja'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['prestamos'], 2) }}</td>
                        <td class="text-right">
                            ${{ number_format($fila['cobranza'], 2) }}
                            @if(!empty($fila['cobranza_es_personal']) && $fila['cobranza'] > 0)
                                <div class="muted" style="font-size:.7rem;">personal</div>
                            @endif
                        </td>
                        <td class="text-right">${{ number_format($fila['otros'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['pagado'], 2) }}</td>
                        <td class="text-right"><strong>${{ number_format($fila['saldo_pendiente'], 2) }}</strong></td>
                        <td>
                            <a class="btn secondary" href="{{ route('nomina.deudas.show', $fila['empleado_id']) }}">Ver</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="muted">No hay deudas con esos filtros.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
