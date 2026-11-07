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
        'mostrar_total' => 'Mostrar total venta',
    ];
    $consulta = array_filter($filtros, fn ($v) => $v !== '' && $v !== null);
@endphp

@push('head')
<style>
.rs-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:14px; }
.rs-tab { padding:7px 12px; border-radius:999px; border:1px solid #c9daf3; background:#fff; color:#1a4480; font-weight:700; font-size:.8rem; text-decoration:none; }
.rs-tab.activo { background:#1a4480; color:#fff; border-color:#1a4480; }
.rs-filtros { display:grid; grid-template-columns:repeat(auto-fill, minmax(170px, 1fr)); gap:10px; align-items:end; }
.rs-filtros label { display:grid; gap:4px; font-size:.7rem; font-weight:700; text-transform:uppercase; color:#64748b; }
.rs-filtros input, .rs-filtros select { padding:7px 9px; border:1px solid #cbd5e1; border-radius:7px; font-size:.86rem; font-weight:400; text-transform:none; color:#0f172a; background:#fff; }
.rs-botones { display:flex; gap:8px; }
.rs-tabla th { white-space:nowrap; }
.rs-tabla td { font-size:.82rem; }
.rs-tabla td.r, .rs-tabla th.r { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
.rs-tabla tr.negativo td { color:#b91c1c; }
.rs-tabla tfoot td { font-weight:800; background:#f8fafc; }
.rs-detalle { border:0; background:#eef4fc; color:#1a4480; font-weight:800; font-size:.7rem; padding:3px 8px; border-radius:6px; cursor:pointer; }
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

<form method="GET" action="{{ route('reportes_suite', $reporte) }}" class="nomina-card" style="margin-bottom:14px;">
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
                    <tr>
                        @foreach($resultado['columnas'] as [$columna, $alineacion])
                            <th class="{{ $alineacion }}">{{ $columna }}</th>
                        @endforeach
                        @if(! empty($resultado['detalle']))<th>Detalle</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($resultado['filas'] as $fila)
                        <tr class="{{ ! empty($fila['negativo']) ? 'negativo' : '' }}">
                            @foreach($fila['celdas'] as $i => $celda)
                                <td class="{{ $resultado['columnas'][$i][1] ?? 'l' }}">{{ $celda }}</td>
                            @endforeach
                            @if(! empty($resultado['detalle']))
                                <td><button type="button" class="rs-detalle" data-url="{{ route('reportes_suite.factura', [$fila['sede'], $fila['documento']]) }}" data-doc="{{ $fila['documento'] }}">DETALLE</button></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($resultado['columnas']) + 1 }}" class="muted" style="text-align:center;padding:24px;">No hay datos con estos filtros.</td></tr>
                    @endforelse
                </tbody>
                @if($resultado['filas'] && $resultado['totales'])
                    <tfoot>
                        <tr>
                            @foreach($resultado['totales'] as $i => $total)
                                <td class="{{ $resultado['columnas'][$i][1] ?? 'l' }}">{{ $total }}</td>
                            @endforeach
                            @if(! empty($resultado['detalle']))<td></td>@endif
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
