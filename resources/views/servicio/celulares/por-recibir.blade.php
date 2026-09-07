@extends('layouts.app')

@section('title', 'Celulares por recibir')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <a href="{{ route('servicio.celulares.hub') }}" class="muted" style="font-size:.82rem;">← Gestión de celulares</a>
            <h1 style="margin:4px 0 0;">📦 Celulares por recibir</h1>
            <p class="muted" style="margin:4px 0 0;">Equipos en tránsito hacia {{ $filtroSede ?: (auth()->user()->scopesServicioToOwnSede() ? auth()->user()->sede : 'cualquier sede') }}.</p>
        </div>
    </div>

    @if($puedeFiltrarSede)
        <form method="GET" class="filter-bar" style="margin-top:16px;">
            <div class="field">
                <label>Sede destino</label>
                <select name="sede" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    @foreach($sedes as $sede)
                        <option value="{{ $sede }}" @selected($filtroSede === $sede)>{{ $sede }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    @endif

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Equipo</th>
                    <th>Orden</th>
                    <th>Desde</th>
                    <th>Cliente</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($ordenes as $orden)
                    <tr>
                        <td>
                            <strong>{{ $orden->equipoCelular?->etiqueta() ?: ($orden->equipo ?: 'Equipo') }}</strong>
                            <div class="muted" style="font-size:.75rem;">{{ $orden->imei ? 'IMEI '.$orden->imei : ($orden->serial ?: '') }}</div>
                        </td>
                        <td><a href="{{ route('servicio.ordenes.show', $orden) }}">{{ $orden->codigo() }}</a></td>
                        <td>{{ $orden->sede_origen_transfer ?: '—' }}</td>
                        <td>{{ $orden->cliente_nombre }}</td>
                        <td>
                            @if($orden->puedeConfirmarRecepcion(auth()->user()))
                                <form method="POST" action="{{ route('servicio.ordenes.confirmar_recepcion', $orden) }}">
                                    @csrf
                                    <button class="btn primary" type="submit">Marcar recibido</button>
                                </form>
                            @else
                                <a class="btn secondary" href="{{ route('servicio.ordenes.show', $orden) }}">Ver</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No hay celulares pendientes de recepción.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
