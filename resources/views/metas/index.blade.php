@extends('layouts.app')

@section('title', 'Metas de quincena')

@section('content')
@php
    $fmt = fn ($n, $d = 0) => number_format((float) $n, $d);
    $conteoPorSede = $filas->groupBy('sede')->map->count();
    $sedesMarcador = $puedeMarcar
        ? collect($sedesDisponibles ?? [])
        : $conteoPorSede->keys()->sort()->values();
@endphp
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Metas de la quincena</h1>
            <p class="muted" style="margin:4px 0 0;">
                {{ $quincena['etiqueta'] }}
                @if($puedeMarcar)
                    · Marca productos desde Marketing → Sobre Stock
                @else
                    · Productos asignados a tu sede
                @endif
            </p>
        </div>
        @if($puedeMarcar && auth()->user()->canAccess('compras'))
            <a class="btn secondary" href="{{ route('comprador.dashboard', ['tab' => 'sobrestock']) }}">Ir a Sobre Stock</a>
        @endif
    </div>

    <div class="nomina-kpis" style="margin-top:16px;">
        <div class="nomina-kpi"><span>Productos meta</span><strong>{{ $filas->count() }}</strong></div>
        <div class="nomina-kpi"><span>Stock inicial</span><strong>{{ $fmt($filas->sum('cantidad_inicial')) }} u.</strong></div>
        <div class="nomina-kpi"><span>Stock actual</span><strong>{{ $fmt($filas->sum('cantidad_actual')) }} u.</strong></div>
        <div class="nomina-kpi"><span>Vendido (facturas)</span><strong>{{ $fmt($filas->sum('vendido')) }} u.</strong></div>
    </div>

    @if($puedeMarcar && $sedesMarcador->isNotEmpty())
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;align-items:center;">
            <span class="muted" style="font-size:.78rem;font-weight:600;margin-right:4px;">Por sede</span>
            @foreach($sedesMarcador as $sede)
                @php $n = (int) ($conteoPorSede[$sede] ?? 0); @endphp
                <span
                    title="{{ $n }} producto{{ $n === 1 ? '' : 's' }} meta en {{ $sede }}"
                    style="display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:999px;border:1px solid {{ $n > 0 ? '#bfdbfe' : 'var(--border)' }};background:{{ $n > 0 ? '#eff6ff' : '#f8fafc' }};font-size:.78rem;font-weight:600;color:{{ $n > 0 ? '#1a4480' : 'var(--muted)' }};"
                >
                    {{ $sede }}
                    <span style="min-width:1.35rem;height:1.35rem;display:inline-flex;align-items:center;justify-content:center;border-radius:999px;background:{{ $n > 0 ? '#1a4480' : '#cbd5e1' }};color:#fff;font-size:.72rem;line-height:1;">{{ $n }}</span>
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
                    <th style="text-align:right;">Cantidad</th>
                    <th style="text-align:right;">Cant. actual</th>
                    <th style="text-align:right;">Vendido</th>
                    <th>Avance</th>
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
                            <div style="font-family:monospace;font-size:.82rem;color:var(--blue);">{{ $fila['codigo'] }}</div>
                            <strong>{{ $fila['producto'] }}</strong>
                            @if($fila['categoria'])
                                <div class="muted" style="font-size:.75rem;">{{ $fila['categoria'] }}</div>
                            @endif
                        </td>
                        <td><strong>{{ $fila['sede'] }}</strong></td>
                        <td style="text-align:right;">{{ $fmt($fila['cantidad_inicial']) }} u.</td>
                        <td style="text-align:right;font-weight:600;">{{ $fmt($fila['cantidad_actual']) }} u.</td>
                        <td style="text-align:right;color:{{ $fila['vendido'] > 0 ? '#15803d' : 'var(--muted)' }};">
                            {{ $fmt($fila['vendido']) }} u.
                        </td>
                        <td>
                            <div style="min-width:110px;">
                                <div class="muted" style="font-size:.75rem;margin-bottom:4px;">{{ $fmt($fila['avance_pct'], 1) }}%</div>
                                <div style="height:8px;background:#e2e8f0;border-radius:999px;overflow:hidden;">
                                    <div style="height:100%;width:{{ min(100, $fila['avance_pct']) }}%;background:{{ $fila['avance_pct'] >= 100 ? '#16a34a' : '#1a4480' }};"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @php $opts = $equiposPorSede[$fila['sede']] ?? []; @endphp
                            <select
                                class="meta-responsable"
                                data-meta-id="{{ $fila['id'] }}"
                                style="min-width:180px;padding:6px 8px;border-radius:8px;border:1px solid var(--border);"
                            >
                                <option value="">Sin asignar</option>
                                @foreach($opts as $emp)
                                    <option value="{{ $emp['id'] }}" @selected((int) $fila['responsable_empleado_id'] === (int) $emp['id'])>
                                        {{ $emp['nombre'] }}@if($emp['cargo']) — {{ $emp['cargo'] }}@endif
                                    </option>
                                @endforeach
                            </select>
                            @if($opts === [] && $fila['responsable_nombre'])
                                <div class="muted" style="font-size:.75rem;margin-top:4px;">{{ $fila['responsable_nombre'] }}</div>
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
                    <tr>
                        <td colspan="{{ $puedeMarcar ? 8 : 7 }}" class="muted" style="text-align:center;padding:28px;">
                            No hay productos marcados como meta en esta quincena.
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
            }
        } catch (e) {
            alert('Error de red al asignar responsable.');
        }
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
