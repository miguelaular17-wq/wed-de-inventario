<div class="stats-row">
    <div class="stat-chip"><strong>{{ $rows->count() }}</strong> filas visibles</div>
    <div class="stat-chip"><strong>{{ $calculatedCount }}</strong> total calculado</div>
    @if ($rows->total() > $rows->count())
        <div class="stat-chip">Pág. <strong>{{ $rows->currentPage() }}</strong>/{{ $rows->lastPage() }}</div>
    @endif
</div>

<section class="table-section-full">
    <div class="table-wrap table-wrap-full">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th class="col-number">Exist.</th>
                    <th>Categoría</th>
                    <th>Subcat.</th>
                    <th class="col-number">Venta 15d</th>
                    @foreach ($sedesStock as $sedeCol)
                        <th class="col-number">{{ config('inventario.display.'.$sedeCol, $sedeCol) }}</th>
                    @endforeach
                    <th class="col-number">Sugerido</th>
                    <th>OPC</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $tag = $row['req_tag'] ?? '';
                        $rowClass = match($tag) {
                            'req_ok' => 'row-req-ok',
                            'req_parcial' => 'row-req-parcial',
                            'req_insuf' => 'row-req-insuf',
                            default => '',
                        };
                    @endphp
                    <tr class="{{ $rowClass }}">
                        <td class="col-code">{{ $row['cod_centro'] }}</td>
                        <td>
                            <div>{{ $row['producto'] }}</div>
                            @if (!empty($row['manuales_list']))
                                <div class="manual-tags-row" style="margin-top: 4px; display: flex; flex-wrap: wrap; gap: 4px;">
                                    @foreach ($row['manuales_list'] as $m)
                                        <span class="tag {{ $m['pendiente'] ? 'manual' : 'ok' }} tag-sm" style="font-size: 0.7rem; padding: 1px 6px;">
                                            {{ config('inventario.display.'.$m['sede_origen'], $m['sede_origen']) }}: {{ $m['cantidad'] }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="col-number">{{ $row['existencia'] }}</td>
                        <td>{{ $row['categoria'] }}</td>
                        <td>{{ $row['subcategoria'] }}</td>
                        <td class="col-number">{{ $row['venta'] }}</td>
                        @foreach ($sedesStock as $sedeCol)
                            <td class="col-number">{{ $row['stocks'][$sedeCol] ?? 0 }}</td>
                        @endforeach
                        <td class="col-number">{{ $row['sugerido'] ?: '—' }}</td>
                        <td>{{ $row['opc'] ?: '—' }}</td>
                        <td>
                            @php $acc = $row['accion']; @endphp
                            @if ($acc === 'HACER REQUISICION')
                                <button type="button" 
                                    class="tag req btn-hacer-requisicion" 
                                    data-codigo="{{ $row['cod_centro'] }}"
                                    data-producto="{{ e($row['producto']) }}"
                                    data-stocks="{{ json_encode($row['stocks']) }}"
                                    data-excedentes="{{ json_encode($row['excedentes'] ?? []) }}"
                                    data-manuales-list="{{ json_encode($row['manuales_list'] ?? []) }}"
                                    style="border: none; cursor: pointer; font-family: inherit; font-size: inherit;"
                                    title="Hacer requisición de múltiples sedes">
                                    {{ $acc }}
                                </button>
                            @elseif ($acc === 'TIENE EXISTENCIA')
                                <span class="tag ok">{{ $acc }}</span>
                            @elseif ($acc === 'NO TIENE EXISTENCIA')
                                <span class="tag warn">{{ $acc }}</span>
                            @else
                                <span class="tag no">{{ $acc }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 10 + count($sedesStock) }}">No hay productos que cumplan con el criterio de mayor demanda local en este momento.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

@include('partials.pagination', ['paginator' => $rows])

<script type="application/json" id="ajax-filter-meta">
@json(['subcategorias' => $subcategorias, 'selectedSubcategoria' => $filters['subcategoria'], 'reqFiltersVisible' => false])
</script>
