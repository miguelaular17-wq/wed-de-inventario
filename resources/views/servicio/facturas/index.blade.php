@extends('layouts.app')
@section('title', 'Facturas de taller')
@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Facturas de taller</h1>
            <p class="muted" style="margin:4px 0 0;">
                @if($soloSusFacturas ?? false)
                    Tus facturas de servicio técnico · quincena {{ $quincena['etiqueta'] ?? '' }}
                @else
                    Ventas de servicio técnico{{ $filtroSede ? ' · '.$filtroSede : '' }}
                @endif
            </p>
        </div>
    </div>

    <form method="GET" class="filter-bar" style="margin-top:16px;">
        @if($puedeFiltrarSede)
            <div class="field"><label>Sede</label>
                <select name="sede"><option value="">Todas</option>
                    @foreach($sedes as $sede)<option value="{{ $sede }}" @selected($filtroSede === $sede)>{{ $sede }}</option>@endforeach
                </select>
            </div>
        @endif
        <div class="field"><label>Desde</label>
            <input type="date" name="desde" value="{{ $filtroDesde ?? request('desde') }}">
        </div>
        <div class="field"><label>Hasta</label>
            <input type="date" name="hasta" value="{{ $filtroHasta ?? request('hasta') }}">
        </div>
        @if(($facturas ?? null) && $facturas->total() > 0)
            <div class="field"><label>Pago</label>
                <select name="estado_pago"><option value="">Todos</option>
                    @foreach($estadosPago as $k => $v)<option value="{{ $k }}" @selected(request('estado_pago') === $k)>{{ $v }}</option>@endforeach
                </select>
            </div>
        @endif
        <div class="field field-wide"><label>Buscar</label><input type="text" name="q" value="{{ request('q') }}" placeholder="Nº, cliente o producto"></div>
        <div class="field" style="display:flex;align-items:flex-end;"><button class="btn primary" type="submit">Filtrar</button></div>
    </form>

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead><tr><th>Nº</th><th>Cliente</th><th>Producto</th><th>Total</th><th>Sede</th><th>Fecha</th><th>Vendedor</th></tr></thead>
            <tbody>
                @forelse($ventasSt as $f)
                    <tr>
                        <td><strong>{{ $f->tipo_documento }} {{ $f->numero_documento }}</strong></td>
                        <td>{{ $f->cliente ?: '—' }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($f->descripcion, 40) ?: '—' }}</td>
                        <td>${{ number_format((float) $f->total, 2) }}</td>
                        <td>{{ $f->sede }}</td>
                        <td>{{ \Carbon\Carbon::parse($f->fecha)->format('d/m/Y') }}</td>
                        <td>{{ $f->vendedor }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div style="text-align:center;padding:32px 16px;">
                                <p style="margin:0 0 8px;font-size:1rem;">No hay facturas de servicio técnico en este rango.</p>
                                <p class="muted" style="margin:0;">
                                    Se listan las ventas facturadas a tu código de vendedor (ficha de nómina).
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $ventasSt->links() }}
    </div>

    @if(($facturas ?? null) && $facturas->total() > 0)
        <h2 style="margin:28px 0 12px;font-size:1.05rem;">Registros internos de taller</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Nº</th><th>Cliente</th><th>Descripción</th><th>Total</th><th>Pago</th><th>Sede</th><th>Fecha</th>@if($puedeGestionarFacturas ?? false)<th></th>@endif</tr></thead>
                <tbody>
                    @foreach($facturas as $f)
                        <tr>
                            <td><a href="{{ route('servicio.facturas.show', $f) }}"><strong>{{ $f->codigo() }}</strong></a></td>
                            <td>{{ $f->cliente_nombre }}</td>
                            <td>{{ Str::limit($f->descripcion, 40) ?: '—' }}</td>
                            <td>${{ number_format($f->total, 2) }}</td>
                            <td>{{ $f->etiquetaEstadoPago() }}</td>
                            <td>{{ $f->sede }}</td>
                            <td>{{ $f->fecha?->format('d/m/Y') }}</td>
                            @if($puedeGestionarFacturas ?? false)
                                <td><a class="btn secondary" href="{{ route('servicio.facturas.edit', $f) }}">Editar</a></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $facturas->links() }}
        </div>
    @endif
</div>
@endsection
