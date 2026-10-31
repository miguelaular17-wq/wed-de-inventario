@extends('layouts.app')

@section('title', 'Factura '.$documento->tipo_documento.' '.$documento->numero_documento)

@push('head')
<style>
.fe-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(190px, 1fr)); gap:10px 14px; margin-top:12px; }
.fe-campo { display:flex; flex-direction:column; gap:4px; }
.fe-campo label { font-size:.7rem; font-weight:700; color:#64748b; text-transform:uppercase; }
.fe-campo input, .fe-tabla input { padding:6px 8px; border:1px solid #cbd5e1; border-radius:6px; font-weight:600; width:100%; min-width:0; }
.fe-campo input.num, .fe-tabla input.num { text-align:right; font-variant-numeric:tabular-nums; }
.fe-campo input.cambiado, .fe-tabla input.cambiado { background:#fef9c3; border-color:#eab308; }
.fe-tabla-wrap { overflow-x:auto; margin-top:10px; }
.fe-tabla { border-collapse:collapse; font-size:.85rem; }
.fe-tabla th { font-size:.68rem; text-transform:uppercase; color:#64748b; padding:6px; white-space:nowrap; border-bottom:2px solid #e2e8f0; text-align:left; }
.fe-tabla td { padding:4px 6px; border-bottom:1px solid #f1f5f9; }
.fe-tabla td.ancho input { min-width:260px; }
.fe-tabla td.medio input { min-width:140px; }
.fe-tabla td.corto input { min-width:100px; }
.fe-tabla tr.aj td:first-child { box-shadow:inset 3px 0 0 #f59e0b; }
.fe-barra { position:sticky; bottom:0; z-index:5; display:flex; gap:10px; align-items:center; justify-content:flex-end; flex-wrap:wrap;
    background:#fff; border-top:1px solid #e2e8f0; padding:12px 0 4px; margin-top:16px; }
.fe-hist { width:100%; border-collapse:collapse; font-size:.82rem; margin-top:8px; }
.fe-hist td, .fe-hist th { padding:6px 8px; border-bottom:1px solid #f1f5f9; text-align:left; vertical-align:top; }
.fe-chip { display:inline-block; font-size:.72rem; font-weight:700; padding:2px 8px; border-radius:999px; background:#fef3c7; color:#92400e; }
</style>
@endpush

@section('content')
@php
    $volverUrl = (is_string($volver) && str_starts_with($volver, '/admin/facturas')) ? $volver : route('admin.facturas.index');
    $ancho = ['nombre_producto', 'cliente', 'motivo_devolucion'];
    $medio = ['codigo_producto', 'vendedor', 'cajera', 'factura_origen', 'fecha'];
    $valor = function ($v, $meta) {
        if ($v === null) return '';
        return match ($meta['tipo']) {
            'fecha' => substr((string) $v, 0, 10),
            'numero' => str_contains((string) $v, '.') ? rtrim(rtrim((string) $v, '0'), '.') : (string) $v,
            default => (string) $v,
        };
    };
    $input = function (string $nombre, string $campo, $v, array $meta) use ($valor) {
        if ($meta['tipo'] === 'bool') {
            return '<input type="hidden" name="'.e($nombre).'" value="0"><input type="checkbox" name="'.e($nombre).'" value="1" '.($v ? 'checked' : '').' style="width:auto;">';
        }
        $type = match ($meta['tipo']) { 'fecha' => 'date', default => 'text' };
        $clase = in_array($meta['tipo'], ['numero', 'entero'], true) ? 'num' : '';
        $modo = $meta['tipo'] === 'numero' ? ' inputmode="decimal"' : ($meta['tipo'] === 'entero' ? ' inputmode="numeric"' : '');
        $val = e($valor($v, $meta));

        return '<input type="'.$type.'" name="'.e($nombre).'" value="'.$val.'" data-original="'.$val.'" class="'.$clase.'"'.$modo.($meta['nulo'] ? '' : ' required').'>';
    };
    $etiqueta = fn ($c) => ucfirst(str_replace('_', ' ', $c));
    $ajustado = ! empty($documento->ajuste_manual) || $renglones->contains(fn ($r) => ! empty($r->ajuste_manual));
@endphp
<div class="panel">
    <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:flex-start;">
        <div>
            <h1 style="margin:0; font-size:1.4rem;">
                {{ $documento->tipo_documento === 'DEV' ? 'Devolución' : 'Factura' }} {{ $documento->numero_documento }}
                · {{ config('inventario.display.'.$documento->sede, $documento->sede) }}
                @if($ajustado)<span class="fe-chip">Ajustada a mano</span>@endif
            </h1>
            <p class="muted" style="margin:4px 0 0;">
                {{ \Carbon\Carbon::parse($documento->fecha)->format('d/m/Y') }} · {{ $renglones->count() }} renglón(es) ·
                Neto ${{ number_format((float) $documento->total_neto_usd, 2, ',', '.') }} / Bs {{ number_format((float) $documento->total_neto_bs, 2, ',', '.') }}
            </p>
        </div>
        <a href="{{ $volverUrl }}" class="btn secondary">← Volver a la búsqueda</a>
    </div>

    @if(session('success'))<div class="alert success" style="margin-top:12px;">✅ {{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert error" style="margin-top:12px;">{{ $errors->first() }}</div>@endif
    @unless($hayAjustes)
        <div class="alert error" style="margin-top:12px;">Para guardar ajustes falta preparar la base: ejecuta <code>php artisan migrate</code>.</div>
    @endunless

    <form method="POST" action="{{ route('admin.facturas.update', $documento->id) }}" id="fe-form">
        @csrf
        @method('PUT')
        <input type="hidden" name="volver" value="{{ $volverUrl }}">

        <h3 style="margin:18px 0 0; font-size:1rem;">Encabezado</h3>
        <p class="muted" style="font-size:.78rem; margin:2px 0 0;">Si cambias la sede, el tipo o el número, sus renglones se mueven con la factura.</p>
        <div class="fe-grid">
            @foreach($camposDoc as $campo => $meta)
                <div class="fe-campo">
                    <label>{{ $etiqueta($campo) }}</label>
                    {!! $input('encabezado['.$campo.']', $campo, $documento->{$campo}, $meta) !!}
                </div>
            @endforeach
        </div>

        <h3 style="margin:22px 0 0; font-size:1rem;">Productos</h3>
        @if($renglones->isEmpty())
            <p class="muted">Esta factura no tiene renglones guardados.</p>
        @else
            <div class="fe-tabla-wrap">
                <table class="fe-tabla">
                    <thead>
                        <tr>
                            @foreach($camposLinea as $campo => $meta)<th>{{ $etiqueta($campo) }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($renglones as $r)
                            <tr class="{{ ! empty($r->ajuste_manual) ? 'aj' : '' }}" title="{{ ! empty($r->ajuste_manual) ? 'Renglón ajustado a mano' : '' }}">
                                @foreach($camposLinea as $campo => $meta)
                                    <td class="{{ in_array($campo, $ancho, true) ? 'ancho' : (in_array($campo, $medio, true) ? 'medio' : 'corto') }}">
                                        {!! $input('renglones['.$r->id.']['.$campo.']', $campo, $r->{$campo}, $meta) !!}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="fe-barra">
            <span class="muted" id="fe-cuenta" style="margin-right:auto;">Sin cambios.</span>
            <a href="{{ $volverUrl }}" class="btn secondary">Cancelar</a>
            <button type="submit" class="btn primary" @disabled(! $hayAjustes)>Guardar ajuste</button>
        </div>
    </form>

    @if($ajustado && $hayAjustes)
        <form method="POST" action="{{ route('admin.facturas.liberar', $documento->id) }}" style="margin-top:8px; text-align:right;"
              onsubmit="return confirm('¿Liberar la factura? En la próxima sincronización de ese día vuelve a quedar como la tenga la sede y se pierden los ajustes.');">
            @csrf
            <button type="submit" class="btn secondary" style="font-size:.82rem;">Liberar: que la sede la vuelva a sincronizar</button>
        </form>
    @endif

    <h3 style="margin:24px 0 0; font-size:1rem;">Historial de ajustes</h3>
    @if($historial->isEmpty())
        <p class="muted">Esta factura no se ha ajustado nunca.</p>
    @else
        <table class="fe-hist">
            <thead><tr><th>Cuándo</th><th>Quién</th><th>Dónde</th><th>Cambios</th></tr></thead>
            <tbody>
                @foreach($historial as $h)
                    <tr>
                        <td style="white-space:nowrap;">{{ \Carbon\Carbon::parse($h->created_at)->format('d/m/Y H:i') }}</td>
                        <td>{{ $h->usuario ?? '—' }}</td>
                        <td>{{ $h->tabla === 'documento' ? 'Encabezado' : 'Renglón '.$h->item_numero }}</td>
                        <td>
                            @foreach((array) json_decode($h->cambios, true) as $campo => [$antes, $despues])
                                <div><strong>{{ $etiqueta($campo) }}:</strong>
                                    <span class="muted" style="text-decoration:line-through;">{{ is_bool($antes) ? ($antes ? 'Sí' : 'No') : ($antes ?? 'vacío') }}</span>
                                    → {{ is_bool($despues) ? ($despues ? 'Sí' : 'No') : ($despues ?? 'vacío') }}</div>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<script>
(function () {
    const form = document.getElementById('fe-form');
    const cuenta = document.getElementById('fe-cuenta');
    const actualizar = () => {
        let n = 0;
        form.querySelectorAll('input[data-original]').forEach((el) => {
            const cambiado = el.value !== el.dataset.original;
            el.classList.toggle('cambiado', cambiado);
            if (cambiado) n++;
        });
        cuenta.textContent = n === 0 ? 'Sin cambios.' : n + ' campo(s) cambiado(s): se marcan en amarillo.';
    };
    form.addEventListener('input', actualizar);
    window.addEventListener('beforeunload', (e) => {
        if (form.dataset.enviando !== '1' && form.querySelector('input.cambiado')) { e.preventDefault(); e.returnValue = ''; }
    });
    form.addEventListener('submit', () => { form.dataset.enviando = '1'; });
})();
</script>
@endsection
