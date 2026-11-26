@extends('layouts.app')

@section('title', 'Ficha producto')

@section('content')
@php
    $camposEditados = $efectivo['campos_editados'] ?? [];
    $sync = $efectivo['sync'] ?? [];
    $badge = fn (string $campo) => in_array($campo, $camposEditados, true)
        ? '<span style="margin-left:6px;padding:2px 6px;border-radius:4px;background:#0f766e;color:#fff;font-size:.65rem;font-weight:700;">EDITADO</span>'
        : '<span style="margin-left:6px;padding:2px 6px;border-radius:4px;background:#e2e8f0;color:#475569;font-size:.65rem;font-weight:600;">SYNC</span>';
@endphp
<div class="panel nomina-page">
    <div class="panel-header-flex" style="flex-wrap:wrap;gap:12px;">
        <div>
            <p class="muted" style="margin:0 0 4px;">
                <a href="{{ route('admin.productos.index', ['sede' => $sede]) }}">← Catálogo</a>
            </p>
            <h1 style="margin:0;">{{ $efectivo['nombre'] ?: 'Producto' }}</h1>
            <p class="muted" style="margin:4px 0 0;font-family:ui-monospace,monospace;">
                {{ \App\Support\Sku::principal($producto->codigo) }}
                @if($efectivo['editado'])
                    <span style="margin-left:8px;padding:2px 8px;border-radius:999px;background:#ecfdf5;color:#047857;font-size:.75rem;font-weight:700;">Tiene edición manual</span>
                @else
                    <span style="margin-left:8px;padding:2px 8px;border-radius:999px;background:#f1f5f9;color:#64748b;font-size:.75rem;font-weight:600;">Solo sincronizador</span>
                @endif
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start;">
            @if(!$editando)
                <a class="btn primary" href="{{ route('admin.productos.show', ['id' => $producto->id, 'sede' => $sede, 'editar' => 1]) }}">Editar</a>
            @else
                <a class="btn secondary" href="{{ route('admin.productos.show', ['id' => $producto->id, 'sede' => $sede]) }}">Cancelar</a>
            @endif
            @if($efectivo['editado'])
                <form method="POST" action="{{ route('admin.productos.restaurar_sync', $producto->id) }}" onsubmit="return confirm('¿Borrar la edición manual y volver a mostrar solo lo del sincronizador?')">
                    @csrf
                    <input type="hidden" name="sede" value="{{ $sede }}">
                    <button class="btn secondary" type="submit">Restaurar sync</button>
                </form>
            @endif
        </div>
    </div>

    <p class="muted" style="margin-top:12px;">
        Lo editado se guarda en una tabla aparte. El sincronizador sigue actualizando el original; en pantallas se muestra primero la edición y, si no hay, el dato sync.
    </p>

    @if($editando)
        <form method="POST" action="{{ route('admin.productos.update', $producto->id) }}" class="nomina-card" style="margin-top:16px;">
            @csrf
            <input type="hidden" name="sede" value="{{ $sede }}">
            <h3 style="margin-top:0;">Editar ficha</h3>
            <p class="muted" style="margin-top:0;">Deja vacío un campo para volver a usar el valor del sincronizador.</p>

            <div class="filter-bar" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                <div class="field" style="grid-column:1/-1;">
                    <label>Nombre / descripción</label>
                    <input type="text" name="nombre" value="{{ old('nombre', $efectivo['nombre']) }}">
                    <div class="muted" style="font-size:.75rem;margin-top:4px;">Sync: {{ $sync['nombre'] ?: '—' }}</div>
                </div>
                <div class="field">
                    <label>Categoría</label>
                    <input type="text" name="categoria" value="{{ old('categoria', $efectivo['categoria']) }}">
                    <div class="muted" style="font-size:.75rem;margin-top:4px;">Sync: {{ $sync['categoria'] ?: '—' }}</div>
                </div>
                <div class="field">
                    <label>Subcategoría</label>
                    <input type="text" name="subcategoria" value="{{ old('subcategoria', $efectivo['subcategoria']) }}">
                    <div class="muted" style="font-size:.75rem;margin-top:4px;">Sync: {{ $sync['subcategoria'] ?: '—' }}</div>
                </div>
                <div class="field">
                    <label>Proveedor</label>
                    <input type="text" name="proveedor" value="{{ old('proveedor', $efectivo['proveedor']) }}">
                    <div class="muted" style="font-size:.75rem;margin-top:4px;">Sync: {{ $sync['proveedor'] ?: '—' }}</div>
                </div>
                <div class="field">
                    <label>Precio unidad</label>
                    <input type="number" step="0.01" min="0" name="precio_unidad" value="{{ old('precio_unidad', $efectivo['precio_unidad']) }}">
                    <div class="muted" style="font-size:.75rem;margin-top:4px;">Sync: {{ number_format((float) ($sync['precio_unidad'] ?? 0), 2) }}</div>
                </div>
                <div class="field">
                    <label>Precio mayor</label>
                    <input type="number" step="0.01" min="0" name="precio_mayor" value="{{ old('precio_mayor', $efectivo['precio_mayor']) }}">
                    <div class="muted" style="font-size:.75rem;margin-top:4px;">Sync: {{ number_format((float) ($sync['precio_mayor'] ?? 0), 2) }}</div>
                </div>
                <div class="field">
                    <label>Unidad de manejo (mín. compra al mayor)</label>
                    <input type="number" step="1" min="1" name="lote_inicial" value="{{ old('lote_inicial', $efectivo['lote_inicial']) }}" placeholder="Ej. 12">
                    <div class="muted" style="font-size:.75rem;margin-top:4px;">Cantidad mínima al comprar en catálogo / factura al mayor. Vacío = sin mínimo.</div>
                </div>
                <div class="field" style="grid-column:1/-1;">
                    <label>URL imagen</label>
                    <input type="text" name="url_imagen" value="{{ old('url_imagen', $efectivo['url_imagen']) }}">
                    <div class="muted" style="font-size:.75rem;margin-top:4px;">Sync: {{ $sync['url_imagen'] ?: '—' }}</div>
                </div>
                <div class="field" style="grid-column:1/-1;">
                    <label>Descripción ampliada (solo edición)</label>
                    <textarea name="descripcion_ampliada" rows="4" style="width:100%;">{{ old('descripcion_ampliada', $efectivo['descripcion_ampliada']) }}</textarea>
                </div>
            </div>

            <div style="margin-top:16px;display:flex;gap:8px;">
                <button class="btn primary" type="submit">Guardar edición</button>
                <a class="btn secondary" href="{{ route('admin.productos.show', ['id' => $producto->id, 'sede' => $sede]) }}">Cancelar</a>
            </div>
        </form>
    @else
        <div class="nomina-card" style="margin-top:16px;">
            <h3 style="margin-top:0;">Datos que se muestran</h3>
            <table class="data-table">
                <tbody>
                    <tr>
                        <th style="width:180px;">Nombre</th>
                        <td>{{ $efectivo['nombre'] ?: '—' }} {!! $badge('nombre') !!}</td>
                    </tr>
                    <tr>
                        <th>Categoría</th>
                        <td>{{ $efectivo['categoria'] ?: '—' }} {!! $badge('categoria') !!}</td>
                    </tr>
                    <tr>
                        <th>Subcategoría</th>
                        <td>{{ $efectivo['subcategoria'] ?: '—' }} {!! $badge('subcategoria') !!}</td>
                    </tr>
                    <tr>
                        <th>Proveedor</th>
                        <td>{{ $efectivo['proveedor'] ?: '—' }} {!! $badge('proveedor') !!}</td>
                    </tr>
                    <tr>
                        <th>Precio unidad</th>
                        <td>${{ number_format((float) ($efectivo['precio_unidad'] ?? 0), 2) }} {!! $badge('precio_unidad') !!}</td>
                    </tr>
                    <tr>
                        <th>Precio mayor</th>
                        <td>${{ number_format((float) ($efectivo['precio_mayor'] ?? 0), 2) }} {!! $badge('precio_mayor') !!}</td>
                    </tr>
                    <tr>
                        <th>Unidad de manejo</th>
                        <td>{{ $efectivo['lote_inicial'] ? ((int) $efectivo['lote_inicial']).' und. (mín. compra)' : '—' }} {!! $badge('lote_inicial') !!}</td>
                    </tr>
                    <tr>
                        <th>URL imagen</th>
                        <td style="word-break:break-all;">{{ $efectivo['url_imagen'] ?: '—' }} {!! $badge('url_imagen') !!}</td>
                    </tr>
                    <tr>
                        <th>Descripción ampliada</th>
                        <td>{{ $efectivo['descripcion_ampliada'] ?: '—' }} {!! $badge('descripcion_ampliada') !!}</td>
                    </tr>
                    <tr>
                        <th>Estado</th>
                        <td>{{ $producto->oculto ? 'Oculto' : 'Visible' }} · {{ $producto->activo ? 'Activo' : 'Inactivo' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if($efectivo['editado'])
            <div class="nomina-card" style="margin-top:16px;">
                <h3 style="margin-top:0;">Comparación sync vs edición</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Campo</th>
                            <th>Sincronizador</th>
                            <th>Edición (prioridad)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($camposEditados as $campo)
                            <tr>
                                <td>{{ str_replace('_', ' ', $campo) }}</td>
                                <td class="muted">
                                    @if(in_array($campo, ['precio_unidad', 'precio_mayor'], true))
                                        ${{ number_format((float) ($sync[$campo] ?? 0), 2) }}
                                    @elseif($campo === 'lote_inicial')
                                        —
                                    @else
                                        {{ $sync[$campo] ?? '—' }}
                                    @endif
                                </td>
                                <td>
                                    <strong>
                                        @if(in_array($campo, ['precio_unidad', 'precio_mayor'], true))
                                            ${{ number_format((float) ($efectivo[$campo] ?? 0), 2) }}
                                        @elseif($campo === 'lote_inicial')
                                            {{ (int) ($efectivo[$campo] ?? 0) }} und.
                                        @else
                                            {{ $efectivo[$campo] ?: '—' }}
                                        @endif
                                    </strong>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    <div class="nomina-card" style="margin-top:16px;">
        <h3 style="margin-top:0;">Stock por sede</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sede</th>
                    <th>Existencia</th>
                </tr>
            </thead>
            <tbody>
                @forelse($stocks as $fila)
                    <tr @if(strtoupper($fila->sede) === strtoupper($sede)) style="background:#f0f9ff;" @endif>
                        <td>{{ config('inventario.display.'.$fila->sede, $fila->sede) }}</td>
                        <td>{{ number_format((float) $fila->existencia, 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="muted">Sin stock registrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
