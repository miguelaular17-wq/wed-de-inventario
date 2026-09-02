@extends('layouts.app')
@section('title', 'Facturas de taller')
@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Facturas de taller</h1>
            <p class="muted" style="margin:4px 0 0;">Cobros por servicio{{ $filtroSede ? ' · '.$filtroSede : '' }}</p>
        </div>
        <a class="btn primary" href="{{ route('servicio.facturas.create') }}">Nueva factura</a>
    </div>

    <form method="GET" class="filter-bar" style="margin-top:16px;">
        @if($puedeFiltrarSede)
            <div class="field"><label>Sede</label>
                <select name="sede"><option value="">Todas</option>
                    @foreach($sedes as $sede)<option value="{{ $sede }}" @selected($filtroSede === $sede)>{{ $sede }}</option>@endforeach
                </select>
            </div>
        @endif
        <div class="field"><label>Pago</label>
            <select name="estado_pago"><option value="">Todos</option>
                @foreach($estadosPago as $k => $v)<option value="{{ $k }}" @selected(request('estado_pago') === $k)>{{ $v }}</option>@endforeach
            </select>
        </div>
        <div class="field field-wide"><label>Buscar</label><input type="text" name="q" value="{{ request('q') }}" placeholder="Nº, cliente o descripción"></div>
        <div class="field" style="display:flex;align-items:flex-end;"><button class="btn primary" type="submit">Filtrar</button></div>
    </form>

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead><tr><th>Nº</th><th>Cliente</th><th>Descripción</th><th>Total</th><th>Pago</th><th>Sede</th><th>Fecha</th><th></th></tr></thead>
            <tbody>
                @forelse($facturas as $f)
                    <tr>
                        <td><a href="{{ route('servicio.facturas.show', $f) }}"><strong>{{ $f->codigo() }}</strong></a></td>
                        <td>{{ $f->cliente_nombre }}</td>
                        <td>{{ Str::limit($f->descripcion, 40) ?: '—' }}</td>
                        <td>${{ number_format($f->total, 2) }}</td>
                        <td>{{ $f->etiquetaEstadoPago() }}</td>
                        <td>{{ $f->sede }}</td>
                        <td>{{ $f->fecha?->format('d/m/Y') }}</td>
                        <td><a class="btn secondary" href="{{ route('servicio.facturas.edit', $f) }}">Editar</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div style="text-align:center;padding:32px 16px;">
                                <p style="margin:0 0 8px;font-size:1rem;">Aún no hay facturas de taller.</p>
                                <p class="muted" style="margin:0 0 16px;">Registra aquí los cobros por reparaciones fuera de órdenes.</p>
                                <a class="btn primary" href="{{ route('servicio.facturas.create') }}">+ Nueva factura</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $facturas->links() }}
    </div>
</div>
@endsection
