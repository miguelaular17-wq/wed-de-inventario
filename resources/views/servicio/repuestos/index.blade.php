@extends('layouts.app')

@section('title', 'Repuestos de taller')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Repuestos de taller</h1>
            <p class="muted" style="margin:4px 0 0;">Inventario de servicio técnico{{ $filtroSede ? ' · '.$filtroSede : '' }} (no es el catálogo de tienda).</p>
        </div>
        <a class="btn primary" href="{{ route('servicio.repuestos.create') }}">Agregar repuesto</a>
        <a class="btn" href="{{ route('servicio.repuestos.import') }}">Importar CSV</a>
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
        <div class="field field-wide">
            <label>Buscar</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Nombre, código o categoría">
        </div>
        <div class="field" style="display:flex;align-items:flex-end;gap:8px;">
            <label style="display:flex;align-items:center;gap:6px;">
                <input type="checkbox" name="bajo_stock" value="1" @checked(request()->boolean('bajo_stock'))> Bajo stock
            </label>
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Repuesto</th>
                    <th>Categoría</th>
                    <th>Sede</th>
                    <th>Stock</th>
                    <th>Mín.</th>
                    <th>Costo</th>
                    <th>Venta</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($repuestos as $repuesto)
                    <tr @if($repuesto->bajoStock()) style="background:#fff7ed;" @endif>
                        <td>{{ $repuesto->codigo ?: '—' }}</td>
                        <td><strong>{{ $repuesto->nombre }}</strong></td>
                        <td>{{ $repuesto->etiquetaCategoria() }}</td>
                        <td>{{ $repuesto->sede }}</td>
                        <td>{{ $repuesto->stock }} @if($repuesto->bajoStock()) ⚠️ @endif</td>
                        <td>{{ $repuesto->stock_min }}</td>
                        <td>${{ number_format($repuesto->costo, 2) }}</td>
                        <td>${{ number_format($repuesto->precio_venta, 2) }}</td>
                        <td><a class="btn secondary" href="{{ route('servicio.repuestos.edit', $repuesto) }}">Editar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">Sin repuestos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $repuestos->links() }}
    </div>
</div>
@endsection
