@extends('layouts.app')

@section('title', 'Metas de quincena')

@section('content')
@php
    $fmt = fn ($n, $d = 0) => number_format((float) $n, $d);
    $conteoPorSede = $todas->groupBy('sede')->map->count();
    $sedesMarcador = $puedeMarcar
        ? collect($sedesDisponibles ?? [])
        : $conteoPorSede->keys()->sort()->values();
    $inicioQ = $quincena['inicio']->toDateString();
    $chip = fn (bool $activo, bool $conDatos) => 'display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:999px;font-size:.78rem;font-weight:600;text-decoration:none;border:1px solid '
        .($activo ? '#1a4480' : ($conDatos ? '#bfdbfe' : 'var(--border)')).';background:'
        .($activo ? '#1a4480' : ($conDatos ? '#eff6ff' : '#f8fafc')).';color:'
        .($activo ? '#fff' : ($conDatos ? '#1a4480' : 'var(--muted)')).';';
    $burbuja = fn (bool $activo, bool $conDatos) => 'min-width:1.35rem;height:1.35rem;display:inline-flex;align-items:center;justify-content:center;border-radius:999px;font-size:.72rem;line-height:1;background:'
        .($activo ? '#fff' : ($conDatos ? '#1a4480' : '#cbd5e1')).';color:'.($activo ? '#1a4480' : '#fff').';';
@endphp
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Metas de la quincena</h1>
            <p class="muted" style="margin:4px 0 0;">
                {{ $quincena['etiqueta'] }}
                @if($soloSusMetas)
                    · Productos meta donde eres el responsable.
                @elseif(!empty($soloSuSede))
                    · Solo las metas de tu sede. Asigna el responsable de cada producto.
                @elseif($puedeMarcar)
                    · Marca productos desde Marketing → Sobre Stock
                    @if(!empty($puedeEditarValor)) · Solo la gerente define el valor $/u. de comisión (por unidades vendidas, hasta la meta).@endif
                @else
                    · Productos asignados a tu sede
                    @if(!empty($puedeEditarValor)) · Solo la gerente puede definir el valor $/u. de comisión del responsable.@endif
                @endif
            </p>
        </div>
        @php
            $queryBase = array_filter([
                'inicio' => $inicioQ,
                'q' => ($busqueda ?? '') !== '' ? $busqueda : null,
            ]);
        @endphp
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
            <form method="get" action="{{ route('metas.index') }}" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0;">
                @if($sedeFiltro !== '')
                    <input type="hidden" name="sede" value="{{ $sedeFiltro }}">
                @endif
                <label for="meta-buscar" class="muted" style="font-size:.8rem;font-weight:600;">Buscar</label>
                <input
                    id="meta-buscar"
                    type="search"
                    name="q"
                    value="{{ $busqueda ?? '' }}"
                    placeholder="Producto o responsable…"
                    minlength="2"
                    style="min-width:200px;padding:7px 10px;border-radius:8px;border:1px solid var(--border);background:#fff;font-size:.85rem;"
                >
                <label for="meta-quincena" class="muted" style="font-size:.8rem;font-weight:600;">Quincena</label>
                <select
                    id="meta-quincena"
                    name="inicio"
                    @if(empty($buscaGlobal)) onchange="this.form.submit()" @endif
                    style="padding:7px 10px;border-radius:8px;border:1px solid var(--border);background:#fff;font-size:.85rem;{{ !empty($buscaGlobal) ? 'opacity:.55;' : '' }}"
                    @if(!empty($buscaGlobal)) title="La búsqueda mira todas las quincenas" @endif
                >
                    @foreach($quincenas as $opc)
                        <option
                            value="{{ $opc['inicio'] }}"
                            @selected($opc['inicio'] === $quincena['inicio']->toDateString())
                        >
                            {{ $opc['etiqueta'] }}
                            @if($opc['es_actual']) (actual) @endif
                            · {{ $opc['total'] }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="btn secondary" style="padding:7px 12px;font-size:.82rem;">Buscar</button>
                @if(($busqueda ?? '') !== '')
                    <a href="{{ route('metas.index', array_filter(['inicio' => $inicioQ, 'sede' => $sedeFiltro ?: null])) }}" class="muted" style="font-size:.8rem;">Limpiar</a>
                @endif
            </form>
        </div>
    </div>

    @if(!empty($buscaGlobal))
        <div style="margin-top:14px;padding:12px 14px;border-radius:10px;border:1px solid #bfdbfe;background:#eff6ff;color:#1e3a5f;font-size:.9rem;">
            Búsqueda de <strong>{{ $busqueda }}</strong> en todas las quincenas
            ({{ $filas->count() }} resultado{{ $filas->count() === 1 ? '' : 's' }}).
            La fecha <em>Asignada</em> indica cuándo se marcó la meta.
        </div>
    @elseif(!empty($quincena['auto_anterior']))
        <div style="margin-top:14px;padding:12px 14px;border-radius:10px;border:1px solid #fde68a;background:#fffbeb;color:#92400e;font-size:.9rem;">
            La quincena actual no tiene metas marcadas. Se muestran las de
            <strong>{{ $quincena['etiqueta'] }}</strong>
            ({{ $filas->count() }} productos). Usa el selector para cambiar de período.
            @if($puedeMarcar)
                Para la quincena nueva, vuelve a marcar productos en Sobre Stock.
            @endif
        </div>
    @endif

    @if($sedesMarcador->isNotEmpty())
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;align-items:center;">
            <span class="muted" style="font-size:.78rem;font-weight:600;margin-right:4px;">Sede</span>
            @php $activo = $sedeFiltro === ''; @endphp
            <a href="{{ route('metas.index', $queryBase) }}" style="{{ $chip($activo, true) }}">
                Todas
                <span style="{{ $burbuja($activo, true) }}">{{ $todas->count() }}</span>
            </a>
            @foreach($sedesMarcador as $sede)
                @php
                    $n = (int) ($conteoPorSede[$sede] ?? 0);
                    $activo = $sedeFiltro === $sede;
                @endphp
                <span style="{{ $chip($activo, $n > 0) }}">
                    <a href="{{ route('metas.index', $queryBase + ['sede' => $sede]) }}" title="Ver solo {{ $sede }}" style="display:inline-flex;align-items:center;gap:6px;color:inherit;text-decoration:none;">
                        {{ $sede }}
                        <span style="{{ $burbuja($activo, $n > 0) }}">{{ $n }}</span>
                    </a>
                    <a href="{{ route('metas.reporte_avances', ['inicio' => $inicioQ, 'sede' => $sede]) }}" title="Descargar PDF de avances · {{ $sede }}" style="font-size:.68rem;opacity:.85;color:inherit;">PDF</a>
                </span>
            @endforeach
        </div>
    @endif

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table" id="tabla-metas">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Sede</th>
                    @if(!empty($buscaGlobal))
                        <th>Quincena</th>
                    @endif
                    <th>Asignada</th>
                    <th style="text-align:right;">Cantidad</th>
                    <th style="text-align:right;">Cant. actual</th>
                    <th style="text-align:right;">Vendido</th>
                    <th>Última venta</th>
                    <th>Avance</th>
                    <th style="text-align:right;" title="USD por unidad vendida que se suma a la comisión del responsable">Valor $/u.</th>
                    <th>Responsable</th>
                    @if($puedeMarcar)
                        <th></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($filas as $fila)
                    <tr>
                        <td>
                            <div style="font-family:monospace;font-size:.82rem;color:var(--blue);">{{ \App\Support\Sku::principal($fila['codigo']) }}</div>
                            <strong>{{ $fila['producto'] }}</strong>
                            @if($fila['categoria'])
                                <div class="muted" style="font-size:.75rem;">{{ $fila['categoria'] }}</div>
                            @endif
                        </td>
                        <td>
                            <strong>{{ $fila['sede'] }}</strong>
                            @if(!empty($fila['es_area']))
                                <div class="muted" style="font-size:.72rem;">Área · ventas de su equipo</div>
                            @endif
                        </td>
                        @if(!empty($buscaGlobal))
                            <td style="white-space:nowrap;font-size:.82rem;">
                                {{ $fila['quincena_etiqueta'] ?? '—' }}
                            </td>
                        @endif
                        <td style="white-space:nowrap;{{ empty($fila['asignada_el']) ? 'color:var(--muted);' : '' }}" title="Fecha en que se marcó / asignó la meta">
                            {{ !empty($fila['asignada_el']) ? \Carbon\Carbon::parse($fila['asignada_el'])->format('d/m/Y') : '—' }}
                        </td>
                        <td style="text-align:right;">
                            {{ $fmt($fila['cantidad_inicial']) }} u.
                            @if(($fila['cantidad_arrastre'] ?? 0) > 0)
                                <div class="muted" style="font-size:.72rem;color:#b45309;">
                                    Incluye {{ $fmt($fila['cantidad_arrastre']) }} u. arrastradas
                                    @if(($fila['cantidad_nueva'] ?? 0) > 0)
                                        (+ {{ $fmt($fila['cantidad_nueva']) }} u. nuevas)
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td style="text-align:right;font-weight:600;">
                            {{ $fmt($fila['cantidad_actual']) }} u.
                            @if(!empty($fila['es_area']))
                                <div class="muted" style="font-size:.72rem;font-weight:400;">por vender</div>
                            @endif
                        </td>
                        <td style="text-align:right;color:{{ $fila['vendido'] > 0 ? '#15803d' : 'var(--muted)' }};">
                            {{ $fmt($fila['vendido']) }} u.
                        </td>
                        <td style="white-space:nowrap;{{ empty($fila['ultima_venta']) ? 'color:var(--muted);' : '' }}">
                            {{ !empty($fila['ultima_venta']) ? \Carbon\Carbon::parse($fila['ultima_venta'])->format('d/m/Y') : '—' }}
                        </td>
                        <td>
                            <div style="min-width:110px;">
                                <div class="muted" style="font-size:.75rem;margin-bottom:4px;">{{ $fmt($fila['avance_pct'], 1) }}%</div>
                                <div style="height:8px;background:#e2e8f0;border-radius:999px;overflow:hidden;">
                                    <div style="height:100%;width:{{ min(100, $fila['avance_pct']) }}%;background:{{ $fila['avance_pct'] >= 100 ? '#16a34a' : '#1a4480' }};"></div>
                                </div>
                            </div>
                        </td>
                        <td style="text-align:right;white-space:nowrap;">
                            @if(!empty($puedeEditarValor))
                                <input
                                    type="number"
                                    class="meta-valor"
                                    data-meta-id="{{ $fila['id'] }}"
                                    min="0"
                                    step="0.01"
                                    placeholder="0.00"
                                    value="{{ $fila['valor_unitario'] !== null ? number_format((float) $fila['valor_unitario'], 2, '.', '') : '' }}"
                                    style="width:88px;padding:5px 8px;border-radius:8px;border:1px solid var(--border);text-align:right;font-size:.82rem;"
                                    title="USD por unidad vendida (hasta la meta)"
                                >
                                <div class="muted meta-comision-est" style="font-size:.72rem;margin-top:3px;">
                                    @if(($fila['comision_estimada'] ?? 0) > 0)
                                        ≈ ${{ number_format((float) $fila['comision_estimada'], 2) }}
                                    @else
                                        —
                                    @endif
                                </div>
                            @else
                                @if($fila['valor_unitario'] !== null && (float) $fila['valor_unitario'] > 0)
                                    ${{ number_format((float) $fila['valor_unitario'], 2) }}
                                    @if(($fila['comision_estimada'] ?? 0) > 0)
                                        <div class="muted" style="font-size:.72rem;">≈ ${{ number_format((float) $fila['comision_estimada'], 2) }}</div>
                                    @endif
                                @else
                                    <span class="muted">—</span>
                                @endif
                            @endif
                        </td>
                        <td>
                            @if($soloSusMetas)
                                {{ $fila['responsable_nombre'] ?: '—' }}
                            @else
                            @php
                                $opts = $equiposPorSede[$fila['sede']] ?? [];
                                $asignado = $fila['responsable_empleado_id'] ? (int) $fila['responsable_empleado_id'] : null;
                            @endphp
                            <div class="meta-resp-nombre" style="display:{{ $asignado ? 'flex' : 'none' }};align-items:center;gap:8px;">
                                <strong class="meta-resp-texto" style="font-size:.82rem;">{{ $fila['responsable_nombre'] }}</strong>
                                <button type="button" class="btn secondary meta-resp-cambiar" style="padding:2px 8px;font-size:.72rem;">Cambiar</button>
                            </div>
                            <select
                                class="meta-responsable"
                                data-meta-id="{{ $fila['id'] }}"
                                style="display:{{ $asignado ? 'none' : 'block' }};min-width:180px;padding:6px 8px;border-radius:8px;border:1px solid var(--border);"
                            >
                                <option value="">Sin asignar</option>
                                @if($asignado && ! collect($opts)->contains('id', $asignado))
                                    <option value="{{ $asignado }}" selected>{{ $fila['responsable_nombre'] }}</option>
                                @endif
                                @foreach($opts as $emp)
                                    <option value="{{ $emp['id'] }}" @selected($asignado === (int) $emp['id'])>
                                        {{ $emp['nombre'] }}@if($emp['cargo']) — {{ $emp['cargo'] }}@endif
                                    </option>
                                @endforeach
                            </select>
                            @endif
                        </td>
                        @if($puedeMarcar)
                            <td>
                                <button
                                    type="button"
                                    class="btn secondary meta-quitar"
                                    data-producto-id="{{ $fila['producto_id'] }}"
                                    data-sede="{{ $fila['sede'] }}"
                                    style="padding:4px 8px;font-size:.75rem;"
                                >Quitar</button>
                            </td>
                        @endif
                    </tr>
                @empty
                    @php
                        $cols = 10 + (!empty($buscaGlobal) ? 1 : 0) + ($puedeMarcar ? 1 : 0);
                    @endphp
                    <tr>
                        <td colspan="{{ $cols }}" class="muted" style="text-align:center;padding:28px;">
                            @if(!empty($buscaGlobal))
                                No hay metas de producto o responsable que coincidan con «{{ $busqueda }}».
                            @elseif($sedeFiltro !== '')
                                No hay productos meta de {{ $sedeFiltro }} en esta quincena.
                            @elseif($soloSusMetas)
                                Aún no tienes productos meta asignados en esta quincena.
                            @else
                                No hay productos marcados como meta en esta quincena.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
document.querySelectorAll('.meta-valor').forEach(inp => {
    const guardar = async () => {
        const metaId = inp.dataset.metaId;
        const raw = String(inp.value || '').trim();
        const body = { valor_unitario: raw === '' ? null : Number(raw) };
        try {
            const res = await fetch(`{{ url('/metas') }}/${metaId}/valor`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(body),
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                alert(data.message || 'No se pudo guardar el valor.');
                return;
            }
            if (data.valor_unitario != null) {
                inp.value = Number(data.valor_unitario).toFixed(2);
            } else {
                inp.value = '';
            }
            const est = inp.parentElement.querySelector('.meta-comision-est');
            if (est) {
                const monto = Number(data.comision_estimada || 0);
                est.textContent = monto > 0
                    ? `≈ $${monto.toFixed(2)}`
                    : '—';
            }
        } catch (e) {
            alert('Error de red al guardar el valor.');
        }
    };
    inp.addEventListener('change', guardar);
    inp.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            inp.blur();
        }
    });
});

document.querySelectorAll('.meta-responsable').forEach(sel => {
    sel.addEventListener('change', async () => {
        const metaId = sel.dataset.metaId;
        const body = { responsable_empleado_id: sel.value || null };
        try {
            const res = await fetch(`{{ url('/metas') }}/${metaId}/responsable`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(body),
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                alert(data.message || 'No se pudo asignar el responsable.');
                return;
            }
            const nombre = sel.parentElement.querySelector('.meta-resp-nombre');
            if (data.responsable_empleado_id) {
                nombre.querySelector('.meta-resp-texto').textContent = data.responsable_nombre || '';
                nombre.style.display = 'flex';
                sel.style.display = 'none';
            }
        } catch (e) {
            alert('Error de red al asignar responsable.');
        }
    });
});

document.querySelectorAll('.meta-resp-cambiar').forEach(btn => {
    btn.addEventListener('click', () => {
        const nombre = btn.closest('.meta-resp-nombre');
        const sel = nombre.parentElement.querySelector('.meta-responsable');
        nombre.style.display = 'none';
        sel.style.display = 'block';
        sel.focus();
    });
});

document.querySelectorAll('.meta-quitar').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('¿Quitar este producto de la meta de la quincena?')) return;
        try {
            const res = await fetch(`{{ route('metas.destroy') }}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    producto_id: Number(btn.dataset.productoId),
                    sede: btn.dataset.sede,
                }),
            });
            const data = await res.json();
            if (res.ok && data.success) {
                btn.closest('tr')?.remove();
            } else {
                alert(data.message || 'No se pudo quitar.');
            }
        } catch (e) {
            alert('Error de red al quitar la meta.');
        }
    });
});
</script>
@endpush
