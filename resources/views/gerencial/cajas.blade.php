@extends('layouts.app')

@section('title', 'Efectividad de caja')

@php
    $dinero = fn ($n) => $n === null ? '—' : '$'.number_format((float) $n, 2, ',', '.');
    $numero = fn ($n) => $n === null ? '—' : number_format((float) $n, 0, ',', '.');
    $pct = fn ($n) => $n === null ? '—' : number_format((float) $n, 1, ',', '.').'%';
@endphp

@section('content')
<div class="page-header">
    <h1>Efectividad de caja</h1>
    <p class="lead">
        {{ $desde }} al {{ $hasta }}.
        El 100% de venta, productos y clientes es lo que vendió la tienda en el período. La nota es 70% efectividad de piso y 30% producto meta.
        El total de faltante de caja del período es el 100%: si hay faltante el porcentaje es negativo y se resta; si es positivo se suma.
    </p>
</div>

@include('incentivos._periodo')

<div class="panel inc-panel">
    <div class="table-wrap inc-wrap">
        <table class="inc-table inc-efectividad">
            <colgroup>
                <col style="width:12%">
                <col style="width:6%">
                @foreach(range(1, 3) as $grupo)
                    <col style="width:5.6%">
                    <col style="width:5.8%">
                    <col style="width:3.6%">
                @endforeach
                <col style="width:4%">
                <col style="width:6%">
                <col style="width:5.2%">
                <col style="width:5.4%">
                <col style="width:3.8%">
                <col style="width:4%">
                <col style="width:4%">
            </colgroup>
            <thead>
                <tr>
                    <th class="inc-persona" rowspan="3">Persona</th>
                    <th rowspan="3">Sede</th>
                    <th class="inc-group g-piso" colspan="9">Efectividad en tienda</th>
                    <th class="inc-group g-pmeta" colspan="2" rowspan="2">Productos meta</th>
                    <th class="inc-group g-falta" colspan="3" rowspan="2">Faltante de caja</th>
                    <th class="inc-group g-nota" colspan="2" rowspan="2">Resultado</th>
                </tr>
                <tr>
                    <th class="g-venta" colspan="3">Venta</th>
                    <th class="inc-group g-prod" colspan="3">Productos</th>
                    <th class="inc-group g-cli" colspan="3">Clientes</th>
                </tr>
                <tr>
                    <th class="g-venta">Real</th>
                    <th class="g-venta">Tienda</th>
                    <th class="g-venta">%</th>
                    <th class="inc-group g-prod">Real</th>
                    <th class="g-prod">Tienda</th>
                    <th class="g-prod">%</th>
                    <th class="inc-group g-cli">Real</th>
                    <th class="g-cli">Tienda</th>
                    <th class="g-cli">%</th>
                    <th class="inc-group g-pmeta">Avance</th>
                    <th class="g-pmeta">Estado</th>
                    <th class="inc-group g-falta">Monto</th>
                    <th class="g-falta">Mes</th>
                    <th class="g-falta">%</th>
                    <th class="inc-group g-nota">Nota</th>
                    <th class="g-nota">Incentivo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($filas as $fila)
                    @php
                        $estadoMeta = $fila['meta_prod_estado'];
                        $claseMeta = match ($estadoMeta) {
                            'Cumplida', 'Cumplió' => 'es-on',
                            'No cumplió' => 'es-no',
                            default => '',
                        };
                    @endphp
                    <tr>
                        <td class="inc-persona">{{ $fila['nombre'] }}</td>
                        <td>{{ $fila['sede'] }}</td>
                        <td class="inc-num inc-group g-venta">{{ $dinero($fila['monto']) }}</td>
                        <td class="inc-num g-venta">{{ $dinero($fila['meta_venta']) }}</td>
                        <td class="inc-num g-venta">{{ $pct($fila['pct_venta']) }}</td>
                        <td class="inc-num inc-group g-prod">{{ $numero($fila['unidades']) }}</td>
                        <td class="inc-num g-prod">{{ $numero($fila['meta_productos']) }}</td>
                        <td class="inc-num g-prod">{{ $pct($fila['pct_productos']) }}</td>
                        <td class="inc-num inc-group g-cli">{{ $numero($fila['clientes']) }}</td>
                        <td class="inc-num g-cli">{{ $numero($fila['meta_clientes']) }}</td>
                        <td class="inc-num g-cli">{{ $pct($fila['pct_clientes']) }}</td>
                        <td class="inc-num inc-group g-pmeta">
                            {{ $fila['meta_prod_total'] === null ? '—' : $fila['meta_prod_cumplidas'].'/'.$fila['meta_prod_total'] }}
                        </td>
                        <td class="g-pmeta">
                            <span class="inc-estado {{ $claseMeta }}">{{ $estadoMeta ?? '—' }}</span>
                        </td>
                        <td class="inc-num inc-group g-falta">{{ $dinero($fila['faltante']) }}</td>
                        <td class="inc-num g-falta">{{ $dinero($fila['faltante_mes']) }}</td>
                        <td class="inc-num g-falta {{ ($fila['faltante_pct'] ?? 0) < 0 ? 'falta-neg' : '' }}">{{ $pct($fila['faltante_pct']) }}</td>
                        <td class="inc-num inc-group g-nota" title="Piso {{ $pct($fila['piso_pct']) }} × 70, producto meta {{ $pct($fila['meta_prod_pct']) }} × 30{{ $fila['faltante_pct'] === null ? '' : ', faltante '.$pct($fila['faltante_pct']) }}">{{ $pct($fila['puntuacion']) }}</td>
                        <td class="inc-num g-nota">—</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="18">No hay cajas para evaluar en el período.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="muted" style="margin:10px 0 0;">El porcentaje de venta, productos y clientes es lo que facturó cada cajera dividido entre lo que vendió su tienda. Ese piso vale 70% y el producto meta 30%. El faltante se mide contra el total del período: un faltante da porcentaje negativo y se resta de la nota; un monto positivo se suma.</p>
</div>

<style>
    .inc-panel { padding: 14px 8px; max-width: none; }
    .inc-wrap { max-height: calc(100vh - 220px); overflow-x: auto; overflow-y: auto; }
    .inc-table {
        width: 100%;
        min-width: 1100px;
        table-layout: fixed;
        border-collapse: separate;
        border-spacing: 0;
        font-size: .78rem;
    }
    .inc-table th,
    .inc-table td {
        padding: 8px 4px;
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
        background: #fff;
        white-space: nowrap;
        overflow: hidden;
    }
    .inc-table thead th {
        position: sticky;
        z-index: 2;
        padding: 7px 12px;
        font-size: .72rem;
        line-height: 1.15;
        font-weight: 700;
        text-transform: uppercase;
        text-align: center;
        color: #334155;
    }
    .inc-efectividad thead tr:first-child th { height: 34px; top: 0; }
    .inc-efectividad thead tr:nth-child(2) th { top: 34px; height: 40px; white-space: normal; }
    .inc-efectividad thead tr:nth-child(3) th { top: 74px; height: 28px; font-weight: 600; color: #64748b; }
    .inc-persona {
        text-align: left;
        position: sticky;
        left: 0;
        z-index: 3;
        white-space: normal;
        overflow: visible;
        box-shadow: 1px 0 0 var(--border);
    }
    .inc-table thead .inc-persona { z-index: 4; }
    .inc-num { text-align: right; font-variant-numeric: tabular-nums; }
    .inc-group { border-left: 2px solid #c5d0e0; }
    .inc-table thead .g-piso { background: #eef2ff; color: #312e81; }
    .inc-table thead .g-venta { background: #e8f0fb; color: #1a4480; }
    .inc-table tbody .g-venta { background: #f7faff; }
    .inc-table thead .g-prod { background: #e7f6ee; color: #166534; }
    .inc-table tbody .g-prod { background: #f6fbf8; }
    .inc-table thead .g-cli { background: #fff4e5; color: #9a3412; }
    .inc-table tbody .g-cli { background: #fffaf3; }
    .inc-table thead .g-pmeta { background: #e0f2fe; color: #075985; }
    .inc-table tbody .g-pmeta { background: #f0f9ff; }
    .inc-table thead .g-falta { background: #fee2e2; color: #991b1b; }
    .inc-table tbody .g-falta { background: #fff5f5; }
    .falta-neg { color: #b91c1c; font-weight: 700; }
    .inc-table thead .g-nota { background: #eef2f7; color: #1e293b; }
    .inc-table tbody .g-nota { background: #f8fafc; font-weight: 600; }
    .inc-table tbody tr:hover .inc-persona { background: #f8fafc; }
    .inc-estado { border: 0; border-radius: 999px; padding: 4px 10px; font-size: .75rem; font-weight: 700; }
    .inc-estado.es-on { background: #dcfce7; color: #166534; }
    .inc-estado.es-no { background: #fee2e2; color: #b91c1c; }
</style>
@endsection
