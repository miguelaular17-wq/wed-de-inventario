@extends('layouts.app')

@section('title', 'Bitácora de celulares')

@section('content')
<div class="panel nomina-page" style="max-width:900px;margin:0 auto;">
    <div class="panel-header-flex">
        <div>
            <a href="{{ route('servicio.celulares.hub') }}" class="muted" style="font-size:.82rem;">← Gestión de celulares</a>
            <h1 style="margin:4px 0 0;">Consultar bitácora</h1>
            <p class="muted" style="margin:4px 0 0;">IMEI, serial, teléfono, nº de orden o nombre de cliente.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('servicio.celulares.bitacora') }}" style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;">
        <input type="search" name="q" value="{{ $q }}" placeholder="Ej. 35… / serial / Jorgelis / 10452"
               style="flex:1;min-width:220px;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;">
        <button class="btn primary" type="submit">Buscar</button>
    </form>

    @if($q !== '')
        <div class="table-wrap" style="margin-top:20px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Equipo</th>
                        <th>Identificador</th>
                        <th>Sede actual</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resultados as $equipo)
                        <tr>
                            <td><strong>{{ $equipo->etiqueta() }}</strong></td>
                            <td>{{ $equipo->identificador() }}</td>
                            <td>{{ $equipo->sede_actual ?: '—' }}</td>
                            <td>{{ $equipo->etiquetaEstado() }}</td>
                            <td><a class="btn secondary" href="{{ route('servicio.celulares.show', $equipo) }}">Ver bitácora</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">Sin resultados para «{{ $q }}».</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
