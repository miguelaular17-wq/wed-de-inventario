@extends('layouts.app')

@section('title', 'Ventas por proveedor')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex" style="align-items:flex-start; gap:16px; flex-wrap:wrap;">
        <div style="flex:1; min-width:220px;">
            <p class="muted" style="margin:0 0 4px;">
                <a href="{{ route('reportes.index') }}">Reportes</a> · Ventas por proveedor
            </p>
            <h1 style="margin:0;">Ventas por proveedor</h1>
            <p class="muted" style="margin:4px 0 0;">
                Unidades, ítems y monto a precio de venta en el período, más inventario consolidado actual.
            </p>
        </div>
        @if($filtros['proveedor'] !== '' && $resumen)
            <a
                href="{{ route('reportes.proveedor.excel', $filtros) }}"
                class="btn btn-success"
            >Descargar Excel</a>
        @endif
    </div>

    <form method="GET" action="{{ route('reportes.proveedor') }}" class="nomina-card gerencial-filtros" style="margin-top:16px; display:flex; gap:14px; flex-wrap:wrap; align-items:flex-end;">
        <div class="field" style="flex:1 1 240px; max-width:360px; margin:0;">
            <label for="proveedor">Proveedor</label>
            <select id="proveedor" name="proveedor" required style="width:100%;">
                <option value="">Selecciona un proveedor…</option>
                @foreach($proveedores as $prov)
                    <option value="{{ $prov }}" @selected($filtros['proveedor'] === $prov)>{{ $prov }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="flex:0 1 160px; margin:0;">
            <label for="desde">Desde</label>
            <input id="desde" type="date" name="desde" value="{{ $filtros['desde'] }}" required style="width:100%;">
        </div>
        <div class="field" style="flex:0 1 160px; margin:0;">
            <label for="hasta">Hasta</label>
            <input id="hasta" type="date" name="hasta" value="{{ $filtros['hasta'] }}" required style="width:100%;">
        </div>
        <div class="field" style="flex:0 0 auto; margin:0; display:flex; align-items:flex-end;">
            <button type="submit" class="btn btn-primary">Consultar</button>
        </div>
    </form>

    @if($filtros['proveedor'] === '')
        <p class="muted" style="margin-top:20px;">Elige un proveedor y el rango de fechas para ver el consolidado.</p>
    @else
        <div class="nomina-kpis" style="margin-top:20px;">
            <div class="nomina-kpi">
                <span>Unidades vendidas</span>
                <strong>{{ number_format($resumen['unidades']) }}</strong>
            </div>
            <div class="nomina-kpi">
                <span>Ítems (SKUs) con venta</span>
                <strong>{{ number_format($resumen['items']) }}</strong>
            </div>
            <div class="nomina-kpi">
                <span>Monto venta</span>
                <strong>${{ number_format($resumen['monto'], 2) }}</strong>
            </div>
            <div class="nomina-kpi">
                <span>Inventario consolidado</span>
                <strong>{{ number_format($resumen['inventario']) }}</strong>
            </div>
        </div>

        <div class="table-wrap" style="margin-top:16px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Subcategoría</th>
                        <th style="text-align:right;">Unidades vendidas</th>
                        <th style="text-align:right;">Monto venta</th>
                        <th style="text-align:right;">Inventario</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($filas as $fila)
                        <tr>
                            <td><strong>{{ $fila->codigo }}</strong></td>
                            <td>{{ $fila->producto }}</td>
                            <td>{{ $fila->categoria ?: '—' }}</td>
                            <td>{{ $fila->subcategoria ?: '—' }}</td>
                            <td style="text-align:right;">{{ number_format((int) $fila->unidades_vendidas) }}</td>
                            <td style="text-align:right;">${{ number_format((float) $fila->monto_venta, 2) }}</td>
                            <td style="text-align:right;">{{ number_format((int) $fila->inventario) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="muted" style="text-align:center; padding:24px;">
                                Sin ventas ni inventario para este proveedor en el rango.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($filas->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td colspan="4"><strong>Total</strong></td>
                            <td style="text-align:right;"><strong>{{ number_format($resumen['unidades']) }}</strong></td>
                            <td style="text-align:right;"><strong>${{ number_format($resumen['monto'], 2) }}</strong></td>
                            <td style="text-align:right;"><strong>{{ number_format($resumen['inventario']) }}</strong></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <p class="muted" style="margin-top:8px;">
            Monto = cantidad × precio de venta (devoluciones restan). Inventario = existencia actual sumada de todas las sedes.
        </p>
    @endif
</div>
@endsection
