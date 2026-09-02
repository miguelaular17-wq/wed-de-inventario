@extends('layouts.app')

@section('title', 'Órdenes de servicio')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Servicio técnico</h1>
            <p class="muted" style="margin:4px 0 0;">
                Órdenes de taller{{ $filtroSede ? ' · '.$filtroSede : '' }}. El inventario de tienda no se toca desde aquí.
            </p>
        </div>
        <a class="btn primary" href="{{ route('servicio.ordenes.create') }}">Nueva orden</a>
    </div>

    <form method="GET" class="filter-bar" style="margin-top:16px;">
        @if($puedeFiltrarSede)
            <div class="field">
                <label>Sede</label>
                <select name="sede">
                    <option value="">Todas</option>
                    @foreach($sedes as $sede)
                        <option value="{{ $sede }}" @selected($filtroSede === $sede)>{{ $sede }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="field">
            <label>Estado</label>
            <select name="estado">
                <option value="">Todos</option>
                @foreach($estados as $key => $label)
                    <option value="{{ $key }}" @selected(request('estado') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field field-wide">
            <label>Buscar</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cliente, teléfono, equipo o serial">
        </div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Orden</th>
                    <th>Cliente</th>
                    <th>Equipo</th>
                    <th>Estado</th>
                    <th>Prioridad</th>
                    <th>Ingreso</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($ordenes as $orden)
                    <tr>
                        <td>
                            <a href="{{ route('servicio.ordenes.show', $orden) }}"><strong>{{ $orden->codigo() }}</strong></a>
                            @if($puedeFiltrarSede)
                                <div class="muted" style="font-size:.75rem;">{{ $orden->sede }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $orden->cliente_nombre }}
                            @if($orden->cliente_telefono)
                                <div class="muted" style="font-size:.75rem;">{{ $orden->cliente_telefono }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $orden->equipo ?: '—' }}
                            @if($orden->serial)
                                <div class="muted" style="font-size:.75rem;">{{ $orden->serial }}</div>
                            @endif
                        </td>
                        <td>{{ $orden->etiquetaEstado() }}
                            @if($orden->excedePresupuesto()) <span title="Excede presupuesto">⚠️</span> @endif
                            @if($orden->transferenciaPendiente()) <span class="muted" style="font-size:.75rem;">· transferencia</span> @endif
                        </td>
                        <td>{{ $orden->etiquetaPrioridad() }}</td>
                        <td>{{ $orden->fecha_ingreso?->format('d/m/Y') }}</td>
                        <td><a class="btn secondary" href="{{ route('servicio.ordenes.edit', $orden) }}">Editar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No hay órdenes con esos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $ordenes->links() }}
    </div>
</div>
@endsection
