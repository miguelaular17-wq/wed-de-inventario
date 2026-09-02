@extends('layouts.app')

@section('title', 'Garantías / interno')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Garantías / servicio interno</h1>
            <p class="muted" style="margin:4px 0 0;">Registros de garantía e interno{{ $filtroSede ? ' · '.$filtroSede : '' }}</p>
        </div>
        <a class="btn primary" href="{{ route('servicio.reparaciones.create') }}">Nuevo registro</a>
    </div>

    <form method="GET" class="filter-bar" style="margin-top:16px;">
        @if($puedeFiltrarSede)
            <div class="field"><label>Sede</label>
                <select name="sede"><option value="">Todas</option>
                    @foreach($sedes as $sede)<option value="{{ $sede }}" @selected($filtroSede === $sede)>{{ $sede }}</option>@endforeach
                </select>
            </div>
        @endif
        <div class="field"><label>Tipo</label>
            <select name="tipo"><option value="">Todos</option>
                @foreach($tipos as $k => $v)<option value="{{ $k }}" @selected(request('tipo') === $k)>{{ $v }}</option>@endforeach
            </select>
        </div>
        <div class="field"><label>Estado</label>
            <select name="estado"><option value="">Todos</option>
                @foreach($estados as $k => $v)<option value="{{ $k }}" @selected(request('estado') === $k)>{{ $v }}</option>@endforeach
            </select>
        </div>
        <div class="field field-wide"><label>Buscar</label><input type="text" name="q" value="{{ request('q') }}" placeholder="Cliente, producto o comprobante"></div>
        <div class="field" style="display:flex;align-items:flex-end;"><button class="btn primary" type="submit">Filtrar</button></div>
    </form>

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead><tr><th>Tipo</th><th>Producto</th><th>Cliente</th><th>Estado</th><th>Sede</th><th>Fecha</th><th></th></tr></thead>
            <tbody>
                @forelse($reparaciones as $r)
                    <tr>
                        <td>{{ $r->etiquetaTipo() }}</td>
                        <td><a href="{{ route('servicio.reparaciones.show', $r) }}"><strong>{{ $r->producto }}</strong></a><div class="muted" style="font-size:.75rem;">{{ $r->etiquetaCategoria() }}</div></td>
                        <td>{{ $r->cliente_nombre ?: '—' }}</td>
                        <td>{{ $r->etiquetaEstado() }}</td>
                        <td>{{ $r->sede }}</td>
                        <td>{{ $r->created_at?->format('d/m/Y') }}</td>
                        <td><a class="btn secondary" href="{{ route('servicio.reparaciones.edit', $r) }}">Editar</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div style="text-align:center;padding:32px 16px;">
                                <p style="margin:0 0 8px;font-size:1rem;">Aún no hay registros de garantía o servicio interno.</p>
                                <p class="muted" style="margin:0 0 16px;">Los datos del taller anterior no se migran solos; créalos aquí.</p>
                                <a class="btn primary" href="{{ route('servicio.reparaciones.create') }}">+ Nuevo registro</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $reparaciones->links() }}
    </div>
</div>
@endsection
