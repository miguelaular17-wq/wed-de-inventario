@extends('layouts.app')

@section('title', 'Faltante de caja')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Faltante de caja</h1>
            <p class="muted" style="margin:4px 0 0;">
                Quincena {{ $quincena['etiqueta'] }}. Lista de cajeras/cajeros. El monto se descuenta de la comisión al liquidar esa quincena.
            </p>
        </div>
    </div>

    <div class="nomina-kpis">
        <div class="nomina-kpi"><span>Cajeras activas</span><strong>{{ $kpis['cajeras'] }}</strong></div>
        <div class="nomina-kpi"><span>Pendiente</span><strong>${{ number_format($kpis['pendiente'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Del día</span><strong>${{ number_format($kpis['del_dia'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Personas hoy</span><strong>{{ $kpis['personas_hoy'] }}</strong></div>
    </div>

    <form method="GET" class="filter-bar" style="margin-top:16px;">
        <div class="field">
            <label>Fecha del día</label>
            <input type="date" name="fecha" value="{{ $fecha }}">
        </div>
        <div class="field field-wide">
            <label>Buscar cajera</label>
            <input type="text" name="q" value="{{ $q }}" placeholder="Nombre o cédula">
        </div>
        <div class="field" style="display:flex;align-items:flex-end;gap:8px;">
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>
    <div style="margin-top:10px;">
        @include('nomina.partials.excel-quincena', ['excelRoute' => route('nomina.faltante_caja.excel'), 'fecha' => $fecha])
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <h3>Cajeras</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Cédula</th>
                    <th>Sede</th>
                    <th>Pendiente</th>
                    <th>Registrar faltante</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cajeras as $empleado)
                    <tr>
                        <td>
                            <a href="{{ route('nomina.empleados.show', $empleado) }}">
                                <strong>{{ $empleado->nombre() }}</strong>
                            </a>
                            <div class="muted" style="font-size:.75rem;">
                                {{ $empleado->nombreCargo() }}
                                @if($empleado->generaComision())
                                    · con comisión
                                @else
                                    · sin comisión (se aplica en nómina)
                                @endif
                            </div>
                        </td>
                        <td>{{ $empleado->cedula() ?: '—' }}</td>
                        <td>{{ $empleado->nombreSede() }}</td>
                        <td>${{ number_format($pendientesPorEmpleado[$empleado->id] ?? 0, 2) }}</td>
                        <td>
                            <form method="POST" action="{{ route('nomina.faltante_caja.store') }}" class="nomina-inline-form" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                                @csrf
                                <input type="hidden" name="empleado_id" value="{{ $empleado->id }}">
                                <input type="hidden" name="fecha" value="{{ $fecha }}">
                                <input type="hidden" name="q" value="{{ $q }}">
                                <input type="number" step="0.01" min="0.01" name="monto" placeholder="Monto" required style="width:110px;">
                                <input name="motivo" placeholder="Detalle (opcional)" style="min-width:140px;flex:1;">
                                <button class="btn primary" type="submit">Registrar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">
                            @if($q !== '')
                                Ninguna cajera coincide con “{{ $q }}”.
                            @else
                                No hay empleados activos con cargo de cajero/cajera.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="table-wrap" style="margin-top:16px;">
        <h3>Cargados el {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Cédula</th>
                    <th>Monto</th>
                    <th>Estado</th>
                    <th>Usuario</th>
                    <th>Detalle</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($delDia as $item)
                    <tr>
                        <td>
                            <a href="{{ route('nomina.empleados.show', $item->empleado_id) }}">
                                {{ $item->empleado?->nombre() ?? '—' }}
                            </a>
                        </td>
                        <td>{{ $item->empleado?->cedula() ?: '—' }}</td>
                        <td>${{ number_format($item->monto, 2) }}</td>
                        <td>{{ $item->estado }}</td>
                        <td>{{ $item->creador?->name ?: '—' }}</td>
                        <td>{{ $item->motivo ?: '—' }}</td>
                        <td>
                            @if($item->estado === 'PENDIENTE')
                                <form method="POST" action="{{ route('nomina.faltante_caja.cancelar', $item) }}" onsubmit="return confirm('¿Cancelar este faltante de caja?')">
                                    @csrf
                                    <input type="hidden" name="q" value="{{ $q }}">
                                    <button class="btn secondary" type="submit">Cancelar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Sin faltantes de caja en esta fecha.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
