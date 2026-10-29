@extends('layouts.app')

@section('title', $modulo['titulo'])

@php
    $dinero = fn ($n) => $n === null ? '—' : '$'.number_format((float) $n, 2, ',', '.');
    $numero = fn ($n) => $n === null ? '—' : number_format((float) $n, 0, ',', '.');
    $pct = fn ($n) => $n === null ? '—' : number_format((float) $n, 1, ',', '.').'%';
    $gruposCruce = $evaluacion['grupos_cruce'] ?? [];
    $nCruce = $clave === 'telefonia' ? max(1, count($gruposCruce)) : 0;
    $porSede = $clave === 'supervisor';
    $columnas = $porSede ? 18 : (15 + ($nCruce * 3));
    $conSelector = in_array($clave, ['telefonia', 'arreglos'], true);
    $efectividad = in_array($clave, ['telefonia', 'ventas', 'arreglos', 'call_center'], true);
    $porEquipo = $clave === 'call_center';
    $referencia = $porEquipo ? 'Equipo' : 'Tienda';
@endphp

@section('content')
<div class="page-header">
    <h1>{{ $modulo['titulo'] }}</h1>
    <p class="lead">
        {{ $evaluacion['desde'] }} al {{ $evaluacion['hasta'] }}.
        @if($clave === 'telefonia')
            El 100% de venta, productos y clientes es lo que vendió la tienda en el período. La nota es 30% efectividad de piso, 50% venta cruzada y 20% producto meta.
        @elseif($porEquipo)
            El 100% de venta, productos y clientes es lo que vendió todo el equipo de call center en el período. La nota es 70% efectividad y 30% producto meta.
        @elseif($efectividad)
            El 100% de venta, productos y clientes es lo que vendió la tienda en el período. La nota es 70% efectividad de piso y 30% producto meta.
        @elseif($porSede)
            Una fila por sede. La nota promedia metas, nivelación, venta cruzada e inventario. El faltante de caja del período se resta si es negativo y se suma si es positivo.
        @else
            La meta es la de la tienda, comparada con lo que vendió cada persona. Cada indicador se topa en 120% para la nota.
        @endif
    </p>
</div>

@include('incentivos._periodo')

<div class="panel inc-panel">
    @if($conSelector && $cruce)
        <div class="inc-cruce-bar">
            <div>
                <span class="muted">{{ $cruce['resumen'] }}</span>
            </div>
            <button type="button" class="btn {{ $cruce['activo'] ? '' : 'secondary' }}" id="abrir-cruce">{{ $cruce['activo'] ? 'Activo' : 'Desactivado' }}</button>
        </div>
    @endif
    <div class="table-wrap inc-wrap {{ $clave === 'telefonia' || $porSede ? 'inc-scroll' : '' }}">
        <table class="inc-table {{ $efectividad ? 'inc-efectividad' : '' }} {{ $clave === 'telefonia' ? 'inc-telefonia' : '' }} {{ $porSede ? 'inc-sede' : '' }}">
            @if($clave !== 'telefonia' && ! $porSede)
                @php
                    $anchos = ['15%', '7%'];
                    foreach (range(1, 3) as $grupo) {
                        array_push($anchos, '7%', '7.2%', '4.2%');
                    }
                    array_push($anchos, '4.5%', '7.5%', '4.2%', '4.6%');
                @endphp
                <colgroup>
                    @foreach($anchos as $ancho)
                        <col style="width:{{ $ancho }}">
                    @endforeach
                </colgroup>
            @endif
            <thead>
                @if($efectividad)
                    <tr>
                        <th class="inc-persona" rowspan="3">Persona</th>
                        <th rowspan="3">Sede</th>
                        <th class="inc-group g-piso" colspan="9">{{ $porEquipo ? 'Efectividad en el equipo' : 'Efectividad en tienda' }}</th>
                        <th class="inc-group g-pmeta" colspan="2" rowspan="2">Productos meta</th>
                        @if($clave === 'telefonia')
                            <th class="inc-group g-cruce" colspan="{{ $nCruce * 3 }}">Efectividad de venta cruzada</th>
                        @endif
                        <th class="inc-group g-nota" colspan="2" rowspan="2">Resultado</th>
                    </tr>
                    <tr>
                        <th class="g-venta" colspan="3">Venta</th>
                        <th class="inc-group g-prod" colspan="3">Productos</th>
                        <th class="inc-group g-cli" colspan="3">Clientes</th>
                        @if($clave === 'telefonia')
                            @foreach($gruposCruce as $grupo)
                                <th class="inc-group g-cruce" colspan="3" title="{{ $grupo['nombre'] }}">{{ $grupo['nombre'] }}</th>
                            @endforeach
                        @endif
                    </tr>
                    <tr>
                        <th class="g-venta">Real</th>
                        <th class="g-venta">{{ $referencia }}</th>
                        <th class="g-venta">%</th>
                        <th class="inc-group g-prod">Real</th>
                        <th class="g-prod">{{ $referencia }}</th>
                        <th class="g-prod">%</th>
                        <th class="inc-group g-cli">Real</th>
                        <th class="g-cli">{{ $referencia }}</th>
                        <th class="g-cli">%</th>
                        <th class="inc-group g-pmeta">Avance</th>
                        <th class="g-pmeta">Estado</th>
                        @if($clave === 'telefonia')
                            @foreach($gruposCruce as $grupo)
                                <th class="inc-group g-cruce">Cruce</th>
                                <th class="g-cruce">Estado</th>
                                <th class="g-cruce">%</th>
                            @endforeach
                        @endif
                        <th class="inc-group g-nota">Nota</th>
                        <th class="g-nota">Incentivo</th>
                    </tr>
                @elseif($porSede)
                    <tr>
                        <th class="inc-persona" rowspan="2">Sede</th>
                        <th class="inc-group g-piso" colspan="9">Efectividad de metas</th>
                        <th class="inc-group g-nivel" rowspan="2">Efectividad de nivelación</th>
                        <th class="inc-group g-falta" rowspan="2">Efectividad de faltante de caja</th>
                        <th class="inc-group g-cruce" rowspan="2">Efectividad de ventas cruzadas</th>
                        <th class="inc-group g-inv" rowspan="2">Efectividad de inventario</th>
                        <th class="inc-group g-pmeta" colspan="2">Productos meta</th>
                        <th class="inc-group g-nota" colspan="2">Resultado</th>
                    </tr>
                    <tr>
                        <th class="g-venta">Real</th>
                        <th class="g-venta">Meta</th>
                        <th class="g-venta">%</th>
                        <th class="inc-group g-prod">Real</th>
                        <th class="g-prod">Meta</th>
                        <th class="g-prod">%</th>
                        <th class="inc-group g-cli">Real</th>
                        <th class="g-cli">Meta</th>
                        <th class="g-cli">%</th>
                        <th class="inc-group g-pmeta">Avance</th>
                        <th class="g-pmeta">Estado</th>
                        <th class="inc-group g-nota">Nota</th>
                        <th class="g-nota">Incentivo</th>
                    </tr>
                @else
                    <tr>
                        <th class="inc-persona" rowspan="2">Persona</th>
                        <th rowspan="2">Sede</th>
                        <th class="inc-group g-venta" colspan="3">Venta</th>
                        <th class="inc-group g-prod" colspan="3">Productos</th>
                        <th class="inc-group g-cli" colspan="3">Clientes</th>
                        <th class="inc-group g-pmeta" colspan="2">Productos meta</th>
                        <th class="inc-group g-nota" colspan="2">Resultado</th>
                    </tr>
                    <tr>
                        <th class="g-venta">Real</th>
                        <th class="g-venta">Meta</th>
                        <th class="g-venta">%</th>
                        <th class="inc-group g-prod">Real</th>
                        <th class="g-prod">Meta</th>
                        <th class="g-prod">%</th>
                        <th class="inc-group g-cli">Real</th>
                        <th class="g-cli">Meta</th>
                        <th class="g-cli">%</th>
                        <th class="inc-group g-pmeta">Avance</th>
                        <th class="g-pmeta">Estado</th>
                        <th class="inc-group g-nota">Nota</th>
                        <th class="g-nota">Incentivo</th>
                    </tr>
                @endif
            </thead>
            <tbody>
                @forelse ($evaluacion['filas'] as $fila)
                    <tr>
                        @if($porSede)
                            <td class="inc-persona">{{ $fila['nombre'] }}</td>
                        @else
                        <td class="inc-persona">
                            {{ $fila['nombre'] }}
                            @if($fila['sin_codigo'])
                                <div class="muted">Sin código de vendedor</div>
                            @endif
                        </td>
                        <td>{{ $fila['sede'] }}</td>
                        @endif
                        <td class="inc-num inc-group g-venta">{{ $dinero($fila['monto']) }}</td>
                        <td class="inc-num g-venta">{{ $dinero($fila['meta_venta']) }}</td>
                        <td class="inc-num g-venta">{{ $pct($fila['pct_venta']) }}</td>
                        <td class="inc-num inc-group g-prod">{{ $numero($fila['unidades']) }}</td>
                        <td class="inc-num g-prod">{{ $numero($fila['meta_productos']) }}</td>
                        <td class="inc-num g-prod">{{ $pct($fila['pct_productos']) }}</td>
                        <td class="inc-num inc-group g-cli">{{ $numero($fila['clientes']) }}</td>
                        <td class="inc-num g-cli">{{ $numero($fila['meta_clientes']) }}</td>
                        <td class="inc-num g-cli">{{ $pct($fila['pct_clientes']) }}</td>
                        @if($porSede)
                            <td class="inc-num inc-group g-nivel" title="De los productos que otra sede tiene con más de 20, cuántos también tiene esta sede. Si aquí está en cero, no cuenta">{{ $pct($fila['nivelacion_pct'] ?? null) }}</td>
                            <td class="inc-num inc-group g-falta {{ ($fila['faltante_pct'] ?? 0) < 0 ? 'falta-neg' : '' }}" title="El total de faltante del período es el 100%. Si la sede tiene faltante, el porcentaje es negativo y se resta">{{ $pct($fila['faltante_pct'] ?? null) }}</td>
                            <td class="inc-num inc-group g-cruce" title="Unidades de las categorías o productos de cruce activos, sobre lo que vendió la sede">{{ $pct($fila['cruce_pct'] ?? null) }}</td>
                            <td class="inc-num inc-group g-inv" title="Productos con existencia que se vendieron en el período">{{ $pct($fila['inventario_pct'] ?? null) }}</td>
                        @endif
                        @php
                            $estadoMeta = $fila['meta_prod_estado'];
                            if ($clave === 'arreglos' && $cruce && ! $cruce['activo']) {
                                $estadoMeta = 'Desactivado';
                            } elseif ($clave === 'arreglos' && $cruce && $cruce['activo'] && $estadoMeta === null) {
                                $estadoMeta = 'Sin meta';
                            }
                            $claseMeta = match ($estadoMeta) {
                                'Cumplida', 'Cumplió' => 'es-on',
                                'No cumplió' => 'es-no',
                                'Desactivado', 'Sin meta' => 'es-off',
                                default => '',
                            };
                        @endphp
                        <td class="inc-num inc-group g-pmeta">
                            {{ $estadoMeta === 'Desactivado' || $fila['meta_prod_total'] === null ? '—' : $fila['meta_prod_cumplidas'].'/'.$fila['meta_prod_total'] }}
                        </td>
                        <td class="g-pmeta">
                            <span class="inc-estado {{ $claseMeta }}">{{ $estadoMeta ?? '—' }}</span>
                        </td>
                        @if($clave === 'telefonia')
                            @foreach($fila['cruces'] as $columna)
                                <td class="inc-num inc-group g-cruce">{{ $numero($columna['unidades']) }}</td>
                                <td class="g-cruce">
                                    <button type="button" class="inc-estado js-abrir-cruce {{ $columna['estado'] === 'Activo' ? 'es-on' : 'es-off' }}">{{ $columna['estado'] }}</button>
                                </td>
                                <td class="inc-num g-cruce">{{ $pct($columna['pct']) }}</td>
                            @endforeach
                        @endif
                        <td class="inc-num inc-group g-nota" @if($clave === 'telefonia') title="Piso {{ $pct($fila['piso_pct']) }} × 30, cruce {{ $pct($fila['cruce_pct']) }} × 50, producto meta {{ $pct($fila['meta_prod_pct']) }} × 20" @elseif($porSede) title="Promedio de metas {{ $pct($fila['metas_pct'] ?? null) }}, nivelación {{ $pct($fila['nivelacion_pct'] ?? null) }}, venta cruzada {{ $pct($fila['cruce_pct'] ?? null) }} e inventario {{ $pct($fila['inventario_pct'] ?? null) }}. Faltante {{ $pct($fila['faltante_pct'] ?? null) }}" @elseif($efectividad) title="Piso {{ $pct($fila['piso_pct']) }} × 70, producto meta {{ $pct($fila['meta_prod_pct']) }} × 30" @endif>{{ $pct($fila['puntuacion']) }}</td>
                        <td class="inc-num g-nota">{{ $fila['tramo'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $columnas }}">{{ $porSede ? 'No hay sedes para evaluar.' : 'No hay personas activas con este cargo.' }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($clave === 'telefonia')
        <p class="muted" style="margin:10px 0 0;">Hay tres categorías. Cada una tiene Cruce, Estado y %, nueve columnas en total. Activa las que faltan en el botón. El porcentaje es la parte de ese trabajador en esa categoría dentro de su tienda, y el promedio de las activas vale el 50% de la nota.</p>
    @elseif($porEquipo)
        <p class="muted" style="margin:10px 0 0;">El porcentaje es la venta de cada trabajador dividida entre lo que vendió todo el call center en el período. Esa efectividad vale 70% de la nota y el producto meta vale 30%.</p>
    @elseif($efectividad)
        <p class="muted" style="margin:10px 0 0;">El porcentaje es la venta de cada trabajador dividida entre lo que vendió la tienda en el período. Ese piso vale 70% de la nota y el producto meta vale 30%.</p>
    @elseif($porSede)
        <p class="muted" style="margin:10px 0 0;">Metas compara lo que vendió la sede con la meta de la tienda. Nivelación toma los productos que otra sede tiene con más de 20 unidades: el porcentaje es cuántos de esos también tiene esta sede. Si aquí está en cero, no suma. Venta cruzada es la parte de las categorías activas. Inventario es la parte de los productos con existencia que se vendieron. Esas cuatro se promedian. El faltante de caja usa el total del período como 100%: si hay faltante se resta y si el monto es positivo se suma.</p>
    @endif
</div>

<style>
    .inc-panel { padding: 14px 8px; max-width: none; }
    .inc-wrap { max-height: calc(100vh - 220px); overflow-x: hidden; overflow-y: auto; }
    .inc-wrap.inc-scroll { overflow-x: auto; }
    .inc-table {
        width: 100%;
        min-width: 0;
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
        letter-spacing: 0;
        text-transform: uppercase;
        text-align: center;
        color: #334155;
    }
    .inc-table thead tr:first-child th { top: 0; height: 32px; }
    .inc-table thead tr:nth-child(2) th { top: 32px; height: 30px; font-weight: 600; color: #64748b; }
    .inc-table.inc-telefonia { width: max-content; min-width: 100%; table-layout: auto; }
    .inc-efectividad thead tr:first-child th { height: 34px; top: 0; }
    .inc-efectividad thead tr:nth-child(2) th { top: 34px; height: 40px; white-space: normal; }
    .inc-efectividad thead tr:nth-child(3) th { top: 74px; height: 28px; font-weight: 600; color: #64748b; }
    .inc-telefonia .g-cruce { min-width: 58px; }
    .inc-kicker { display: block; font-size: .6rem; line-height: 1.1; font-weight: 700; letter-spacing: 0; }
    .inc-table thead .g-piso { background: #eef2ff; color: #312e81; }
    .inc-persona {
        text-align: left;
        width: 15%;
        min-width: 0;
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
    .inc-table thead .g-venta { background: #e8f0fb; color: #1a4480; }
    .inc-table tbody .g-venta { background: #f7faff; }
    .inc-table thead .g-prod { background: #e7f6ee; color: #166534; }
    .inc-table tbody .g-prod { background: #f6fbf8; }
    .inc-table thead .g-cli { background: #fff4e5; color: #9a3412; }
    .inc-table tbody .g-cli { background: #fffaf3; }
    .inc-sede { width: max-content; min-width: 100%; table-layout: auto; }
    .inc-sede .g-nivel,
    .inc-sede .g-falta,
    .inc-sede .g-cruce,
    .inc-sede .g-inv { min-width: 108px; white-space: normal; }
    .inc-table thead .g-nivel { background: #ccfbf1; color: #115e59; }
    .inc-table tbody .g-nivel { background: #f0fdfa; }
    .inc-table thead .g-falta { background: #fee2e2; color: #991b1b; }
    .inc-table tbody .g-falta { background: #fff5f5; }
    .falta-neg { color: #b91c1c; font-weight: 700; }
    .inc-table thead .g-inv { background: #fef3c7; color: #92400e; }
    .inc-table tbody .g-inv { background: #fffbeb; }
    .inc-table thead .g-cruce { background: #f3e8ff; color: #6b21a8; }
    .inc-table tbody .g-cruce { background: #faf7ff; }
    .inc-table thead .g-pmeta { background: #e0f2fe; color: #075985; }
    .inc-table tbody .g-pmeta { background: #f0f9ff; }
    .inc-table thead .g-nota { background: #eef2f7; color: #1e293b; }
    .inc-table tbody .g-nota { background: #f8fafc; font-weight: 600; }
    .inc-table tbody tr:hover .inc-persona { background: #f8fafc; }
    .inc-cruce-bar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
    .inc-on-text { color: #166534; }
    .inc-off-text { color: #64748b; }
    .inc-estado { border: 0; border-radius: 999px; padding: 4px 10px; font-size: .75rem; font-weight: 700; cursor: pointer; }
    .inc-estado.es-on { background: #dcfce7; color: #166534; }
    .inc-estado.es-off { background: #e2e8f0; color: #475569; }
    .inc-estado.es-no { background: #fee2e2; color: #b91c1c; }
    #modal-cruce .modal-box { max-width: 640px; width: 100%; max-height: 86vh; display: flex; flex-direction: column; }
    .inc-tabs { display: flex; gap: 8px; margin: 12px 0; }
    .inc-tab { border: 1px solid var(--border); background: #fff; border-radius: 8px; padding: 8px 12px; cursor: pointer; font-weight: 600; }
    .inc-tab.activo { background: var(--blue); color: #fff; border-color: var(--blue); }
    .inc-buscar { width: 100%; border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; }
    .inc-lista { overflow: auto; max-height: 48vh; border: 1px solid var(--border); border-radius: 10px; }
    .inc-item { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 10px 12px; border-bottom: 1px solid var(--border); }
    .inc-item:last-child { border-bottom: 0; }
    .inc-item small { display: block; color: var(--muted); }
    .inc-toggle { border: 0; border-radius: 999px; padding: 6px 12px; font-weight: 700; cursor: pointer; min-width: 118px; }
    .inc-toggle.on { background: #dcfce7; color: #166534; }
    .inc-toggle.off { background: #e2e8f0; color: #475569; }
</style>

@if($conSelector && $cruce)
<div id="modal-cruce" class="modal-overlay" hidden>
    <div class="panel modal-box" role="dialog" aria-modal="true" aria-labelledby="modal-cruce-titulo">
        <h2 id="modal-cruce-titulo" style="margin:0;">{{ $clave === 'arreglos' ? 'Arreglo' : 'Venta cruzada' }}</h2>
        <p class="muted" style="margin:6px 0 0;">{{ $clave === 'arreglos' ? 'Elige categorías o productos. Activo hace que venta, productos y clientes cuenten solo eso. Desactivado los deja en blanco.' : 'Activa las categorías que quieras medir. Cada una abre Cruce, Estado y %.' }}</p>
        <div class="inc-tabs">
            <button type="button" class="inc-tab activo" data-tab="categoria">Categorías</button>
            <button type="button" class="inc-tab" data-tab="producto">Productos</button>
        </div>
        <input id="cruce-buscar" class="inc-buscar" type="search" placeholder="Buscar categoría">
        <div id="cruce-lista" class="inc-lista"></div>
        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:14px;">
            <button type="button" class="btn secondary" id="cerrar-cruce">Cerrar</button>
        </div>
    </div>
</div>
<script>
(function () {
    const modal = document.getElementById('modal-cruce');
    const lista = document.getElementById('cruce-lista');
    const buscar = document.getElementById('cruce-buscar');
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const urlGuardar = @json(route('incentivos.cruce.guardar', $clave));
    const urlBuscar = @json(route('incentivos.cruce.buscar', $clave));
    let tab = 'categoria';
    let categorias = @json($cruce['categorias']);
    let productos = @json($cruce['productos']);
    let timer = null;
    let cambio = false;

    function etiqueta(activo) { return activo ? 'Activo' : 'Desactivado'; }

    function pintar(items, tipo) {
        lista.innerHTML = '';
        if (!items.length) {
            lista.innerHTML = '<div class="inc-item"><span class="muted">' + (tipo === 'producto' ? 'Busca un producto por nombre o código.' : 'No hay categorías.') + '</span></div>';
            return;
        }
        items.forEach(function (item) {
            const fila = document.createElement('div');
            fila.className = 'inc-item';
            const texto = document.createElement('div');
            const nombre = document.createElement('strong');
            nombre.textContent = item.nombre;
            texto.appendChild(nombre);
            if (item.categoria) {
                const sub = document.createElement('small');
                sub.textContent = item.categoria;
                texto.appendChild(sub);
            } else if (tipo === 'producto') {
                const sub = document.createElement('small');
                sub.textContent = item.clave;
                texto.appendChild(sub);
            }
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'inc-toggle ' + (item.activo ? 'on' : 'off');
            boton.textContent = etiqueta(item.activo);
            boton.addEventListener('click', function () { alternar(tipo, item, boton); });
            fila.appendChild(texto);
            fila.appendChild(boton);
            lista.appendChild(fila);
        });
    }

    function visibles() {
        const q = buscar.value.trim().toUpperCase();
        return categorias.filter(function (item) {
            return q === '' || item.nombre.toUpperCase().indexOf(q) !== -1;
        });
    }

    function mostrar() {
        if (tab === 'categoria') {
            pintar(visibles(), 'categoria');
            return;
        }
        pintar(productos, 'producto');
    }

    function abrir() {
        modal.hidden = false;
        mostrar();
        buscar.focus();
    }

    function cerrar() {
        modal.hidden = true;
        if (cambio) window.location.reload();
    }

    async function alternar(tipo, item, boton) {
        const siguiente = !item.activo;
        boton.disabled = true;
        try {
            const respuesta = await fetch(urlGuardar, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify({ tipo: tipo, clave: item.clave, nombre: item.nombre, activo: siguiente })
            });
            const data = await respuesta.json();
            if (!respuesta.ok) {
                const msg = data.errors ? Object.values(data.errors)[0][0] : 'No se pudo guardar.';
                window.alert(msg);
                return;
            }
            item.activo = data.activo;
            item.nombre = data.nombre || item.nombre;
            boton.textContent = data.etiqueta;
            boton.className = 'inc-toggle ' + (data.activo ? 'on' : 'off');
            cambio = true;
            if (tipo === 'producto') {
                const ya = productos.find(function (p) { return p.clave === item.clave; });
                if (!ya) productos.unshift({ clave: item.clave, nombre: item.nombre, categoria: item.categoria || '', activo: item.activo });
            }
        } catch (e) {
            window.alert('No se pudo guardar.');
        } finally {
            boton.disabled = false;
        }
    }

    document.getElementById('abrir-cruce').addEventListener('click', abrir);
    document.querySelectorAll('.js-abrir-cruce').forEach(function (el) { el.addEventListener('click', abrir); });
    document.getElementById('cerrar-cruce').addEventListener('click', cerrar);
    modal.addEventListener('click', function (e) { if (e.target === modal) cerrar(); });
    document.querySelectorAll('.inc-tab').forEach(function (el) {
        el.addEventListener('click', function () {
            tab = el.dataset.tab;
            document.querySelectorAll('.inc-tab').forEach(function (t) { t.classList.toggle('activo', t === el); });
            buscar.placeholder = tab === 'categoria' ? 'Buscar categoría' : 'Buscar producto por nombre o código';
            buscar.value = '';
            if (tab === 'producto') cargarProductos('');
            else mostrar();
        });
    });
    buscar.addEventListener('input', function () {
        if (tab === 'categoria') { mostrar(); return; }
        clearTimeout(timer);
        timer = setTimeout(function () { cargarProductos(buscar.value.trim()); }, 250);
    });

    async function cargarProductos(q) {
        const respuesta = await fetch(urlBuscar + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
        const data = await respuesta.json();
        productos = data.productos || [];
        pintar(productos, 'producto');
    }
})();
</script>
@endif
@endsection
