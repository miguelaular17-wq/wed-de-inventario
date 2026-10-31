@extends('layouts.app')

@section('title', 'Ajuste de facturas')

@push('head')
<style>
.fa-filtros { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin-top:14px; }
.fa-filtros .field { display:flex; flex-direction:column; gap:4px; }
.fa-filtros label { font-size:.7rem; font-weight:700; color:#64748b; text-transform:uppercase; }
.fa-filtros input, .fa-filtros select { padding:7px 9px; border:1px solid #cbd5e1; border-radius:8px; min-width:120px; }
.fa-tabla { width:100%; border-collapse:collapse; margin-top:14px; font-size:.88rem; }
.fa-tabla th { text-align:left; font-size:.7rem; text-transform:uppercase; color:#64748b; padding:8px; border-bottom:2px solid #e2e8f0; white-space:nowrap; }
.fa-tabla td { padding:8px; border-bottom:1px solid #f1f5f9; vertical-align:top; }
.fa-tabla tr.fa-fila:hover td { background:#f8fafc; }
.fa-num { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
.fa-chip { display:inline-block; font-size:.7rem; font-weight:700; padding:2px 7px; border-radius:999px; }
.fa-chip.fac { background:#e0f2fe; color:#0369a1; }
.fa-chip.dev { background:#fee2e2; color:#b91c1c; }
.fa-chip.aj { background:#fef3c7; color:#92400e; }
.fa-prod { margin:6px 0 0; padding:0; list-style:none; font-size:.8rem; color:#334155; }
.fa-prod li { padding:2px 0; }
.fa-prod mark { background:#fde68a; padding:0 2px; border-radius:3px; }
</style>
@endpush

@section('content')
@php
    $resaltar = function (?string $texto, ?string $buscado) {
        $texto = e((string) $texto);
        $b = trim((string) $buscado);
        return $b === '' ? $texto : preg_replace('/(' . preg_quote(e($b), '/') . ')/iu', '<mark>$1</mark>', $texto);
    };
@endphp
<div class="panel">
    <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:flex-start;">
        <div>
            <h1 style="margin:0; font-size:1.5rem;">🧾 Ajuste de facturas</h1>
            <p class="muted" style="margin:4px 0 0; max-width:820px;">
                Busca cualquier factura o devolución de las sedes por número, cliente, vendedor o cajera, o por un producto.
                Al abrirla puedes corregir cualquier campo; lo que ajustes queda guardado con su historial y la sincronización de la sede ya no lo pisa.
            </p>
        </div>
    </div>

    @if(session('success'))<div class="alert success" style="margin-top:12px;">✅ {{ session('success') }}</div>@endif
    @unless($hayAjustes)
        <div class="alert error" style="margin-top:12px;">Para poder guardar ajustes falta preparar la base: ejecuta <code>php artisan migrate</code>. Mientras tanto solo puedes buscar y ver.</div>
    @endunless

    <form method="GET" action="{{ route('admin.facturas.index') }}" class="fa-filtros">
        <div class="field" style="flex:1; min-width:220px;">
            <label>Factura, cliente, vendedor o cajera</label>
            <input type="search" name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="Número de factura o nombre">
        </div>
        <div class="field" style="flex:1; min-width:220px;">
            <label>Producto (código o nombre)</label>
            <input type="search" name="producto" value="{{ $filtros['producto'] ?? '' }}" placeholder="Código o parte del nombre">
        </div>
        <div class="field">
            <label>Sede</label>
            <select name="sede">
                <option value="">Todas</option>
                @foreach($sedes as $s)
                    <option value="{{ $s }}" @selected(($filtros['sede'] ?? '') === $s)>{{ config('inventario.display.'.$s, $s) }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Tipo</label>
            <select name="tipo">
                <option value="">FAC y DEV</option>
                <option value="FAC" @selected(($filtros['tipo'] ?? '') === 'FAC')>Facturas</option>
                <option value="DEV" @selected(($filtros['tipo'] ?? '') === 'DEV')>Devoluciones</option>
            </select>
        </div>
        <div class="field">
            <label>Desde</label>
            <input type="date" name="desde" value="{{ $filtros['desde'] ?? '' }}">
        </div>
        <div class="field">
            <label>Hasta</label>
            <input type="date" name="hasta" value="{{ $filtros['hasta'] ?? '' }}">
        </div>
        @if($hayAjustes)
            <label style="display:flex; align-items:center; gap:6px; font-size:.85rem; padding-bottom:8px;">
                <input type="checkbox" name="ajustadas" value="1" @checked(! empty($filtros['ajustadas'])) style="min-width:0;"> Solo ajustadas
            </label>
        @endif
        <button type="submit" class="btn primary">Buscar</button>
        <a href="{{ route('admin.facturas.index') }}" class="btn secondary">Limpiar</a>
    </form>
    <p class="muted" style="font-size:.8rem; margin:8px 0 0;">
        @if($desde || $hasta)
            Mostrando {{ $desde ? 'desde el '.\Carbon\Carbon::parse($desde)->format('d/m/Y') : '' }}{{ $hasta ? ' hasta el '.\Carbon\Carbon::parse($hasta)->format('d/m/Y') : '' }}.
            @if(empty($filtros['desde']) && empty($filtros['hasta']))
                Sin fechas se miran {{ ! empty($filtros['producto']) ? 'los últimos 90 días' : 'los últimos 7 días' }}; pon fechas para buscar más atrás.
            @endif
        @else
            Buscando en todas las fechas.
        @endif
        {{ number_format($facturas->total(), 0, ',', '.') }} documento(s).
    </p>

    <table class="fa-tabla">
        <thead>
            <tr>
                <th>Fecha</th><th>Sede</th><th>Documento</th><th>Cliente</th><th>Vendedor / cajera</th>
                <th class="fa-num">Neto $</th><th class="fa-num">Neto Bs</th><th>Estado</th><th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($facturas as $f)
                @php $lineas = $renglones[$f->sede.'|'.$f->tipo_documento.'|'.$f->numero_documento] ?? collect(); @endphp
                <tr class="fa-fila">
                    <td style="white-space:nowrap;">{{ \Carbon\Carbon::parse($f->fecha)->format('d/m/Y') }}</td>
                    <td>{{ config('inventario.display.'.$f->sede, $f->sede) }}</td>
                    <td style="white-space:nowrap;">
                        <span class="fa-chip {{ $f->tipo_documento === 'DEV' ? 'dev' : 'fac' }}">{{ $f->tipo_documento }}</span>
                        <strong>{!! $resaltar($f->numero_documento, $filtros['q'] ?? '') !!}</strong>
                        @if(! empty($f->ajuste_manual))<span class="fa-chip aj" title="Corregida a mano">Ajustada</span>@endif
                        @if($f->factura_origen)<div class="muted" style="font-size:.75rem;">Origen: {{ $f->factura_origen }}</div>@endif
                    </td>
                    <td>
                        {!! $resaltar($f->cliente, $filtros['q'] ?? '') !!}
                        @if($lineas->isNotEmpty())
                            <ul class="fa-prod">
                                @foreach($lineas as $l)
                                    <li>#{{ $l->item_numero }} · {!! $resaltar($l->codigo_producto, $filtros['producto'] ?? '') !!} · {!! $resaltar($l->nombre_producto, $filtros['producto'] ?? '') !!}
                                        · {{ rtrim(rtrim(number_format((float) $l->cantidad, 4, ',', '.'), '0'), ',') }} und · ${{ number_format((float) ($l->precio_neto ?? $l->precio_venta), 2, ',', '.') }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                    <td>{!! $resaltar($f->vendedor, $filtros['q'] ?? '') !!}@if($f->cajera)<div class="muted" style="font-size:.75rem;">Caja: {!! $resaltar($f->cajera, $filtros['q'] ?? '') !!}</div>@endif</td>
                    <td class="fa-num">{{ number_format((float) $f->total_neto_usd, 2, ',', '.') }}</td>
                    <td class="fa-num">{{ number_format((float) $f->total_neto_bs, 2, ',', '.') }}</td>
                    <td>{{ $f->estado }}</td>
                    <td><a class="btn secondary" href="{{ route('admin.facturas.edit', ['factura' => $f->id, 'volver' => request()->getRequestUri()]) }}">Abrir</a></td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted" style="padding:18px 8px;">No hay documentos con esa búsqueda.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:12px;">{{ $facturas->links() }}</div>
</div>
@endsection
