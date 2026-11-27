@extends('layouts.app')

@section('title', $titulo)

@php
    $etiquetasFiltro = [
        'cliente' => 'Cliente',
        'metodo' => 'Método de pago',
        'cuenta' => 'Cuenta bancaria',
        'usuario' => 'Usuario',
        'tipo_documento' => 'Tipo de documento',
        'motivo' => 'Motivo',
        'solo_devoluciones' => 'Solo devoluciones',
        'mostrar_total' => 'Mostrar totales',
    ];
    $consulta = array_filter($filtros, fn ($v) => $v !== '' && $v !== null);
@endphp

@push('head')
<style>
.rs-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:14px; }
.rs-tab { padding:7px 12px; border-radius:999px; border:1px solid #c9daf3; background:#fff; color:#1a4480; font-weight:700; font-size:.8rem; text-decoration:none; }
.rs-tab.activo { background:#1a4480; color:#fff; border-color:#1a4480; }
.rs-filtros-card { padding:14px 16px; overflow:hidden; }
.rs-filtros {
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap:12px 14px;
    align-items:end;
}
@media (max-width: 1100px) {
    .rs-filtros { grid-template-columns:repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 780px) {
    .rs-filtros { grid-template-columns:repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 520px) {
    .rs-filtros { grid-template-columns:1fr; }
}
.rs-filtros label {
    display:flex;
    flex-direction:column;
    gap:5px;
    min-width:0;
    font-size:.7rem;
    font-weight:700;
    text-transform:uppercase;
    color:#64748b;
}
.rs-filtros input,
.rs-filtros select {
    width:100%;
    max-width:100%;
    min-width:0;
    box-sizing:border-box;
    min-height:36px;
    padding:7px 10px;
    border:1px solid #cbd5e1;
    border-radius:7px;
    font-size:.86rem;
    font-weight:400;
    text-transform:none;
    color:#0f172a;
    background:#fff;
}
.rs-botones {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    align-items:center;
    grid-column:1 / -1;
    padding-top:2px;
}
.rs-tabla th { white-space:nowrap; }
.rs-tabla td { font-size:.82rem; }
.rs-tabla td.r, .rs-tabla th.r { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
.rs-tabla tr.negativo td { color:#b91c1c; }
.rs-tabla tr.rs-grupo td { background:#1a4480; color:#fff; font-weight:800; font-size:.8rem; letter-spacing:.02em; padding:8px 10px; }
.rs-tabla tr.rs-resumen td { background:#eef4fc; font-weight:700; }
.rs-tabla tr.rs-subtotal td { background:#f1f5f9; font-weight:800; border-top:1px solid #cbd5e1; }
.rs-tabla tfoot td { font-weight:800; background:#f8fafc; }
.rs-detalle { border:0; background:#eef4fc; color:#1a4480; font-weight:800; font-size:.7rem; padding:3px 8px; border-radius:6px; cursor:pointer; }
.rs-abrir { display:inline-block; padding:3px 8px; border-radius:6px; background:#1a4480; color:#fff; font-weight:800; font-size:.7rem; text-decoration:none; white-space:nowrap; }
.rs-abrir:hover { background:#163a6d; color:#fff; }
.rs-caja-link { color:inherit; font-weight:800; text-decoration:underline; text-underline-offset:2px; }
.rs-caja-link:hover { color:#1a4480; }
#rs-modal { border:0; border-radius:12px; padding:18px 20px; width:min(640px, 94vw); }
#rs-modal::backdrop { background:rgba(15, 23, 42, .45); }
#rs-modal table { width:100%; border-collapse:collapse; font-size:.84rem; }
#rs-modal td, #rs-modal th { padding:6px 8px; border-bottom:1px solid #f1f5f9; text-align:left; }
#rs-modal .r { text-align:right; }
</style>
@endpush

@section('content')
<div class="page-header">
    <span class="muted" style="font-size:.82rem;">Tesorería · Reportes de Suite</span>
    <h1 style="margin:4px 0 0;">{{ $titulo }}</h1>
    <p class="lead">Datos de la facturadora (Suite) que sube el sincronizador de cada sede.</p>
</div>

<div class="rs-tabs">
    @foreach($reportes as $clave => $nombre)
        <a class="rs-tab {{ $clave === $reporte ? 'activo' : '' }}"
           href="{{ route('reportes_suite', array_merge(['reporte' => $clave], array_intersect_key($consulta, array_flip(['sede', 'desde', 'hasta'])))) }}">{{ $nombre }}</a>
    @endforeach
</div>

<form method="GET" action="{{ route('reportes_suite', $reporte) }}" class="nomina-card rs-filtros-card" style="margin-bottom:14px;">
    <div class="rs-filtros">
        <label>Sede
            <select name="sede" @disabled(count($sedes) === 1)>
                @if(count($sedes) > 1)<option value="">Todas</option>@endif
                @foreach($sedes as $s)
                    <option value="{{ $s }}" @selected($filtros['sede'] === $s)>{{ $etiquetas[$s] ?? $s }}</option>
                @endforeach
            </select>
        </label>
        <label>Desde <input type="date" name="desde" value="{{ $filtros['desde'] }}" required></label>
        <label>Hasta <input type="date" name="hasta" value="{{ $filtros['hasta'] }}" required></label>
        @foreach($extra as $clave)
            <label>{{ $etiquetasFiltro[$clave] ?? $clave }}
                @if($clave === 'cliente')
                    <input type="search" name="cliente" value="{{ $filtros['cliente'] }}" placeholder="Nombre o cédula">
                @elseif($clave === 'solo_devoluciones')
                    <select name="solo_devoluciones">
                        <option value="">No</option>
                        <option value="1" @selected($filtros['solo_devoluciones'] === '1')>Sí</option>
                    </select>
                @elseif($clave === 'mostrar_total')
                    <select name="mostrar_total">
                        <option value="1" @selected($filtros['mostrar_total'] === '1')>Sí</option>
                        <option value="0" @selected($filtros['mostrar_total'] === '0')>No</option>
                    </select>
                @else
                    <select name="{{ $clave }}">
                        <option value="">Todos</option>
                        @foreach($opciones[$clave] ?? [] as $valor => $texto)
                            <option value="{{ $valor }}" @selected($filtros[$clave] === (string) $valor)>{{ $texto }}</option>
                        @endforeach
                    </select>
                @endif
            </label>
        @endforeach
        <div class="rs-botones">
            <button class="btn primary" type="submit">Ver reporte</button>
            <a class="btn secondary" target="_blank" href="{{ route('reportes_suite', array_merge(['reporte' => $reporte, 'formato' => 'pdf'], $consulta)) }}">PDF</a>
        </div>
    </div>
</form>

@if(! empty($resultado['aviso']))
    <div class="panel" style="padding:16px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;">{{ $resultado['aviso'] }}</div>
@else
    <div class="panel" style="padding:0;">
        <div class="table-wrap" style="max-height:70vh;overflow:auto;">
            <table class="data-table rs-tabla">
                <thead>
                    @php
                        $esCuadre = $reporte === 'cuadre';
                        $colExtra = (! empty($resultado['detalle'])) || $esCuadre;
                    @endphp
                    <tr>
                        @foreach($resultado['columnas'] as [$columna, $alineacion])
                            <th class="{{ $alineacion }}">{{ $columna }}</th>
                        @endforeach
                        @if($colExtra)<th></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($resultado['filas'] as $fila)
                        @php
                            $tipoFila = $fila['tipo'] ?? null;
                            $cuadreId = (int) ($fila['cuadre_id'] ?? 0);
                            $urlCuadre = $cuadreId > 0 ? route('cuadre_caja.show', ['cuadre' => $cuadreId, 'volver' => request()->fullUrl()]) : null;
                        @endphp
                        <tr class="{{ ! empty($fila['negativo']) ? 'negativo' : '' }}{{ $tipoFila === 'grupo' ? ' rs-grupo' : '' }}{{ $tipoFila === 'resumen' ? ' rs-resumen' : '' }}{{ $tipoFila === 'subtotal' ? ' rs-subtotal' : '' }}">
                            @foreach($fila['celdas'] as $i => $celda)
                                <td class="{{ $resultado['columnas'][$i][1] ?? 'l' }}">
                                    @if($urlCuadre && $i === 1 && empty($tipoFila))
                                        <a class="rs-caja-link" href="{{ $urlCuadre }}" title="Abrir arqueo de esta caja">{{ $celda }}</a>
                                    @else
                                        {{ $celda }}
                                    @endif
                                </td>
                            @endforeach
                            @if($colExtra)
                                <td>
                                    @if(! empty($resultado['detalle']) && empty($tipoFila))
                                        <button type="button" class="rs-detalle" data-url="{{ route('reportes_suite.factura', [$fila['sede'], $fila['documento']]) }}" data-doc="{{ $fila['documento'] }}">DETALLE</button>
                                    @elseif($urlCuadre)
                                        <a class="rs-abrir" href="{{ $urlCuadre }}">Abrir</a>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($resultado['columnas']) + ($colExtra ? 1 : 0) }}" class="muted" style="text-align:center;padding:24px;">No hay datos con estos filtros.</td></tr>
                    @endforelse
                </tbody>
                @if($resultado['filas'] && $resultado['totales'])
                    <tfoot>
                        <tr>
                            @foreach($resultado['totales'] as $i => $total)
                                <td class="{{ $resultado['columnas'][$i][1] ?? 'l' }}">{{ $total }}</td>
                            @endforeach
                            @if($colExtra)<td></td>@endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        @if($resultado['total_filas'] > $limite)
            <p class="muted" style="padding:10px 14px;margin:0;font-size:.8rem;color:#b45309;">
                Se muestran {{ number_format($limite) }} de {{ number_format($resultado['total_filas']) }} filas (los totales sí incluyen todas). El PDF las trae todas.
            </p>
        @endif
    </div>
@endif

@if(! empty($resultado['detalle']))
<dialog id="rs-modal">
    <h3 style="margin:0 0 10px;" id="rs-modal-titulo">Factura</h3>
    <div id="rs-modal-cuerpo">Cargando…</div>
    <form method="dialog" style="text-align:right;margin-top:12px;"><button class="btn secondary">Cerrar</button></form>
</dialog>
<script>
(function () {
    const modal = document.getElementById('rs-modal');
    const cuerpo = document.getElementById('rs-modal-cuerpo');
    const num = (n) => Number(n).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const esc = (t) => String(t ?? '').replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
    document.querySelectorAll('.rs-detalle').forEach(b => b.addEventListener('click', async () => {
        document.getElementById('rs-modal-titulo').textContent = 'Factura ' + b.dataset.doc;
        cuerpo.textContent = 'Cargando…';
        modal.showModal();
        try {
            const r = await fetch(b.dataset.url, {headers: {'Accept': 'application/json'}});
            const data = await r.json();
            cuerpo.innerHTML = data.renglones.length
                ? '<table><thead><tr><th>Código</th><th>Producto</th><th class="r">Cant.</th><th class="r">Precio $</th></tr></thead><tbody>'
                    + data.renglones.map(x => `<tr><td>${esc(x.codigo)}</td><td>${esc(x.producto)}</td><td class="r">${num(x.cantidad)}</td><td class="r">${num(x.precio)}</td></tr>`).join('')
                    + '</tbody></table>'
                : '<p class="muted">Sin renglones sincronizados.</p>';
        } catch (e) {
            cuerpo.textContent = 'No se pudo cargar el detalle.';
        }
    }));
})();
</script>
@endif
@endsection
