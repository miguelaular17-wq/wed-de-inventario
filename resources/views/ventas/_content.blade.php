<div class="stats-row" data-tour="ventas-stats">
    <div class="stat-chip"><strong>{{ $rows->count() }}</strong> filas visibles</div>
    <div class="stat-chip"><strong>{{ $calculatedCount }}</strong> total calculado</div>
    @if ($rows->total() > $rows->count())
        <div class="stat-chip">Pág. <strong>{{ $rows->currentPage() }}</strong>/{{ $rows->lastPage() }}</div>
    @endif
</div>

<section class="table-section-full" data-tour="ventas-table">
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
                        <td>{{ $row['producto'] }}</td>
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
                                <a href="{{ route('requisicion.form', [
                                    'tipo_reporte' => 'ventas',
                                    'sede_origen' => config('inventario.display.'.$row['op1'], $row['op1']),
                                    'categoria' => $row['categoria'],
                                    'subcategoria' => $row['subcategoria']
                                ]) }}" class="tag req" title="Ir a exportar requisición para este producto y categoría">{{ $acc }}</a>
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
                    <tr><td colspan="{{ 10 + count($sedesStock) }}">Sin datos. Importe el Excel multisede desde el panel admin.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

@include('partials.pagination', ['paginator' => $rows])

<script type="application/json" id="ajax-filter-meta">
@json(['subcategorias' => $subcategorias, 'selectedSubcategoria' => $filters['subcategoria'], 'reqFiltersVisible' => $reqFiltersVisible])
</script>
