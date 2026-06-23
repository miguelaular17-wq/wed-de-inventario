<div class="stats-row">
    <div class="stat-chip"><strong>{{ $rows->total() }}</strong> visibles</div>
    <div class="stat-chip"><strong id="total-manual-count">{{ $totalManual ?? 0 }}</strong> pendientes de exportar</div>
    @if ($rows->lastPage() > 1)
        <div class="stat-chip">Pág. <strong>{{ $rows->currentPage() }}</strong>/{{ $rows->lastPage() }}</div>
    @endif
</div>

@if($rows->isEmpty())
    <div class="panel empty-state" data-tour="inventario-grid">
        <p>No hay productos con stock en otras sedes para estos filtros.</p>
    </div>
@else
    <div class="product-grid" data-tour="inventario-grid">
        @foreach ($rows as $row)
            @php
                $manualesList = $row['manuales_list'] ?? [];
                $hasManual    = !empty($manualesList);
                $hasPendiente = $row['manual_pendiente'] ?? false;
            @endphp
            <article class="product-card {{ $hasManual ? 'has-manual' : '' }}"
                data-codigo="{{ $row['cod_centro'] }}"
                data-producto="{{ e($row['producto']) }}"
                data-origen-manual="{{ $row['origen_manual'] ?? '' }}"
                data-cantidad-manual="{{ $row['cantidad_manual'] ?? 0 }}"
                data-manuales-list="{{ e(json_encode($manualesList)) }}">
                <div class="code">{{ $row['cod_centro'] }}</div>
                <div class="name">{{ $row['producto'] }}</div>
                <div class="stock-pills">
                    <span class="stock-pill">Local <strong>{{ $row['existencia'] }}</strong></span>
                    @foreach ($sedesStock as $sedeCol)
                        @if(($row['stocks'][$sedeCol] ?? 0) > 0)
                            <span class="stock-pill">{{ config('inventario.display.'.$sedeCol, $sedeCol) }} <strong>{{ $row['stocks'][$sedeCol] }}</strong></span>
                        @endif
                    @endforeach
                </div>
                @if($hasManual)
                    <div class="manual-tags-row">
                        @foreach($manualesList as $m)
                            <span class="tag {{ $m['pendiente'] ? 'manual' : 'ok' }} tag-sm" style="display: inline-flex; align-items: center; gap: 5px;">
                                <span>{{ config('inventario.display.'.$m['sede_origen'], $m['sede_origen']) }}: {{ $m['cantidad'] }}</span>
                                @if($m['pendiente'])
                                    <button type="button" 
                                            class="btn-undo-tag" 
                                            title="Deshacer requisición" 
                                            onclick="event.stopPropagation(); deleteManualRequisition('{{ $row['cod_centro'] }}', '{{ $m['sede_origen'] }}', '{{ config('inventario.display.'.$m['sede_origen'], $m['sede_origen']) }}')" 
                                            style="background:none; border:none; color:rgba(255,255,255,0.7); cursor:pointer; font-size:0.95rem; font-weight:700; padding:0; display:inline-flex; align-items:center; justify-content:center; line-height:1; transition:color 0.2s;">
                                        &times;
                                    </button>
                                @endif
                            </span>
                        @endforeach
                    </div>
                    <span class="muted" style="font-size:0.78rem;">Clic para editar / agregar sede</span>
                @else
                    <span class="muted">Clic para requisitar</span>
                @endif
            </article>
        @endforeach
    </div>
@endif

@include('partials.pagination', ['paginator' => $rows])

<script type="application/json" id="ajax-filter-meta">
@json(['subcategorias' => $subcategorias, 'selectedSubcategoria' => $filters['subcategoria'] ?? 'Ninguno'])
</script>
