@extends('layouts.app')

@section('title', 'Logs de Sincronización — Admin')

@section('content')
<div class="page-header">
    <h1>Visor de Sincronización</h1>
    <p class="lead">Registros generados por la aplicación de sincronización Python.</p>
</div>

<form method="GET" class="filter-bar" data-auto-filter data-auto-filter-delay="350">
    <div class="field">
        <label for="sede">Sede</label>
        <select id="sede" name="sede">
            <option value="">Todas</option>
            @foreach ($sedes as $s)
                <option value="{{ $s }}" @selected($sede === $s)>{{ config('inventario.display.'.$s, $s) }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="tipo">Tipo</label>
        <select id="tipo" name="tipo">
            <option value="">Todos</option>
            <option value="APERTURA" @selected($tipo === 'APERTURA')>Reporte Inicio de Día (APERTURA)</option>
            <option value="VENTA" @selected($tipo === 'VENTA')>Movimientos en Caja (VENTA)</option>
        </select>
    </div>
    <div class="field" style="flex: 1; text-align: right; display: flex; align-items: flex-end; justify-content: flex-end;">
        <button type="submit" class="btn btn-primary" style="margin-bottom: 2px;">Filtrar</button>
    </div>
</form>

<section class="table-section-full">
    <div class="table-wrap table-wrap-full">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:150px">Fecha / Hora</th>
                    <th style="width:120px">Sede</th>
                    <th style="width:180px">Tipo</th>
                    <th style="width:150px; text-align: right;">Registros Procesados</th>
                    <th>Detalles Adicionales</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="num">
                            <span title="{{ \Carbon\Carbon::parse($log->created_at)->timezone('America/Caracas')->format('Y-m-d H:i:s') }}">
                                {{ \Carbon\Carbon::parse($log->created_at)->timezone('America/Caracas')->diffForHumans() }}
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-sede badge-{{ strtolower($log->sede) }}">{{ $log->sede }}</span>
                        </td>
                        <td>
                            @if($log->tipo === 'APERTURA')
                                <span class="badge" style="background:var(--accent-teal); color:#fff;">Reporte Inicio Día</span>
                            @else
                                <span class="badge" style="background:var(--accent-purple); color:#fff;">Ventas Registradas</span>
                            @endif
                        </td>
                        <td class="num" style="text-align: right; font-weight: 500;">
                            {{ number_format($log->registros_procesados) }}
                        </td>
                        <td class="muted" style="font-size: 0.9em;">
                            @php
                                $meta = json_decode($log->metadata, true) ?: [];
                            @endphp
                            @if($log->tipo === 'APERTURA')
                                Productos omitidos: {{ $meta['omitidos'] ?? 0 }}
                            @else
                                Último ticket/hora: {{ $meta['timestamp'] ?? 'N/A' }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty-state">No hay registros de sincronización disponibles.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<div class="pagination-wrapper" style="margin-top: 1.5rem; display: flex; justify-content: center;">
    {{ $logs->links('pagination::bootstrap-4') }}
</div>
@endsection
