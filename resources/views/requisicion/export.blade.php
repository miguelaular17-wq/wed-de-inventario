@extends('layouts.app')

@section('title', 'Exportar requisición')

@section('content')
<div class="page-header">
    <h1>Exportar reporte</h1>
    <p class="lead">Sede {{ $sede }} · Los filtros se aplican al instante.</p>
</div>

<div class="stats-row">
    <div class="stat-chip"><strong>{{ $totalRequisicion }}</strong> en requisición</div>
    <div class="stat-chip"><strong id="filtered-count">{{ max(0, ($filteredCount ?? 0) - count(array_filter($excludeCodes ?? []))) }}</strong> incluidos</div>
    <div class="stat-chip"><strong id="excluded-count">{{ count(array_filter($excludeCodes ?? [])) }}</strong> excluidos</div>
</div>

<form id="tipo-form" method="GET" action="{{ route('requisicion.form') }}" class="filter-bar" style="margin-bottom:16px;" data-auto-filter data-auto-filter-delay="0" data-tour="export-type">
    <div class="field field-wide">
        <label for="tipo_reporte">Tipo de reporte</label>
        <select name="tipo_reporte" id="tipo_reporte">
            <option value="ventas" @selected(($tipoReporte ?? 'ventas') === 'ventas')>Requisición automática (ventas)</option>
            <option value="personalizada" @selected(($tipoReporte ?? '') === 'personalizada')>Requisición personalizada (manual)</option>
        </select>
    </div>
</form>

@if(($tipoReporte ?? 'ventas') === 'ventas')
    <p class="muted" style="margin:0 0 16px;">
        Genera CSV y <strong>aplica movimiento de stock</strong> al exportar. Clic en un producto para excluirlo del reporte.
    </p>
@else
    <p class="muted" style="margin:0 0 16px;">
        Exporta requisiciones registradas en <a href="{{ route('inventario.index') }}">Inventario</a>.
        Al descargar el CSV se <strong>aplica el movimiento de stock</strong> (resta en origen, suma en {{ $sede }}).
    </p>
@endif

<form id="filters-form" method="GET" action="{{ route('requisicion.form') }}" class="export-filters-form" data-auto-filter data-auto-filter-delay="400" data-auto-filter-preserve="exclude_codes" data-tour="export-filters">
    <input type="hidden" name="tipo_reporte" value="{{ $tipoReporte ?? 'ventas' }}">

    <div class="filter-bar filter-row-main @if(($tipoReporte ?? 'ventas') === 'personalizada') filter-row-standalone @endif">
        <div class="field">
            <label for="sede_origen">Sede origen</label>
            <select name="sede_origen" id="sede_origen" required>
                <option value="Todas" @selected($selectedSedeOrigen === 'Todas')>Todas las sedes</option>
                @foreach ($sedesOrigen as $origen)
                    <option value="{{ config('inventario.display.'.$origen, $origen) }}" @selected($selectedSedeOrigen === config('inventario.display.'.$origen, $origen))>
                        {{ config('inventario.display.'.$origen, $origen) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="categoria">Categoría</label>
            <select name="categoria" id="categoria">
                <option value="Todas" @selected($selectedCategoria === 'Todas')>Todas</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat }}" @selected($selectedCategoria === $cat)>{{ $cat }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" id="subcategoria-wrap">
            <label for="subcategoria">Subcategoría</label>
            <select name="subcategoria" id="subcategoria" @if($selectedCategoria === 'Todas' && !($excluirCategorias ?? false)) disabled @endif>
                <option value="Todas" @selected($selectedSubcategoria === 'Todas')>Todas</option>
                @foreach ($subcategories as $sub)
                    <option value="{{ $sub }}" @selected($selectedSubcategoria === $sub)>{{ $sub }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if(($tipoReporte ?? 'ventas') === 'ventas')
    <div class="filter-options-row">
        <label class="option-toggle">
            <input type="checkbox" name="incluir_parcial" value="1" @checked(request()->boolean('incluir_parcial'))>
            <span class="option-toggle-text">
                <strong>Incluir parciales</strong>
                <span>Agrega requisiciones con stock insuficiente en origen</span>
            </span>
        </label>
    </div>

    <div id="exclude-categories-wrap" class="exclude-panel" @if($selectedCategoria !== 'Todas') style="display:none" @endif>
        <div class="exclude-panel-header {{ ($excluirCategorias ?? false) ? '' : 'is-off' }}" id="exclude-panel-header">
            <label class="toggle-switch" title="Activar exclusión por categoría">
                <input type="checkbox" id="chk_excluir_categorias" name="excluir_categorias" value="1"
                    @checked($excluirCategorias ?? false)>
                <span class="toggle-slider"></span>
            </label>
            <div class="exclude-panel-title">
                <strong>Excluir categorías</strong>
                <span>Marca las categorías que no deben salir en el reporte</span>
            </div>
            <span class="exclude-count-badge {{ count($excludeCategories) ? 'has-items' : '' }}" id="exclude-cat-count">
                @if(count($excludeCategories))
                    {{ count($excludeCategories) }} excluida{{ count($excludeCategories) > 1 ? 's' : '' }}
                @else
                    Ninguna
                @endif
            </span>
        </div>
        <div id="exclude-categories-panel" class="category-chip-grid {{ ($excluirCategorias ?? false) ? 'is-open' : '' }}">
            @foreach ($categories as $cat)
                <label class="category-chip">
                    <input type="checkbox"
                        class="exclude-cat-checkbox"
                        name="exclude_categories[]"
                        value="{{ $cat }}"
                        @checked(in_array($cat, $excludeCategories, true))
                        @disabled(!($excluirCategorias ?? false))>
                    <span class="chip-face">{{ $cat }}</span>
                </label>
            @endforeach
        </div>
    </div>
    @endif
</form>

@if($previewRows->isNotEmpty())
<div class="panel">
    @if(($tipoReporte ?? 'ventas') === 'ventas')
        <p class="muted" style="margin:0 0 12px;">Productos incluidos — clic para excluir</p>
    @else
        <p class="muted" style="margin:0 0 12px;">Requisiciones manuales incluidas en el reporte</p>
    @endif
    <div class="product-grid" id="preview-list">
        @foreach ($previewRows as $row)
            @php $isExcluded = in_array($row['codigo'], $excludeCodes ?? [], true); @endphp
            <article class="product-card {{ $isExcluded ? 'excluded-item' : '' }} {{ ($tipoReporte ?? 'ventas') === 'personalizada' ? 'manual-preview' : '' }}"
                @if(($tipoReporte ?? 'ventas') === 'ventas')
                data-code="{{ $row['codigo'] }}"
                @endif
                style="{{ $isExcluded ? 'opacity:.5;border-color:#fca5a5;background:#fef2f2;' : '' }}">
                <div class="code">{{ $row['codigo'] }}</div>
                <div class="name">{{ $row['producto'] }}</div>
                <div class="stock-pills">
                    <span class="stock-pill">Origen <strong>{{ $row['opc'] }}</strong></span>
                    <span class="stock-pill">Cant. <strong>{{ $row['cantidad'] }}</strong></span>
                    @if(($tipoReporte ?? 'ventas') === 'personalizada' && ($row['categoria'] ?? '—') !== '—')
                        <span class="stock-pill">{{ $row['categoria'] }}</span>
                    @endif
                </div>
                @if($isExcluded)
                    <span class="tag warn">Excluido</span>
                @elseif(($tipoReporte ?? 'ventas') === 'personalizada')
                    <span class="tag manual">Manual</span>
                @endif
            </article>
        @endforeach
    </div>
</div>
@elseif($previewRows->isEmpty())
<div class="panel empty-state">
    @if(($tipoReporte ?? 'ventas') === 'personalizada' && ($totalRequisicion ?? 0) > 0)
        <p>Hay <strong>{{ $totalRequisicion }}</strong> requisiciones registradas, pero ninguna coincide con los filtros actuales{{ $selectedSedeOrigen !== 'Todas' ? ' (sede origen: '.$selectedSedeOrigen.')' : '' }}.</p>
        <p class="muted" style="margin-top:8px;">Prueba seleccionar <strong>Todas las sedes</strong> en el filtro de origen.</p>
    @else
        <p>No hay resultados para estos filtros.</p>
    @endif
</div>
@endif

<form method="POST" action="{{ route('requisicion.export') }}" id="export-form">
    @csrf
    <input type="hidden" name="tipo_reporte" value="{{ $tipoReporte ?? 'ventas' }}">
    <input type="hidden" name="sede_origen" value="{{ $selectedSedeOrigen }}">
    <input type="hidden" name="categoria" value="{{ $selectedCategoria }}">
    <input type="hidden" name="subcategoria" value="{{ $selectedSubcategoria }}">
    <input type="hidden" name="incluir_parcial" value="{{ request()->boolean('incluir_parcial') ? 1 : 0 }}">
    <input type="hidden" name="excluir_categorias" id="export-excluir-categorias" value="{{ ($excluirCategorias ?? false) ? 1 : 0 }}">
    @foreach ($excludeCategories as $excludedCategory)
        <input type="hidden" name="exclude_categories[]" value="{{ $excludedCategory }}">
    @endforeach
    <div id="exclude-codes-inputs"></div>
    <div class="export-actions" data-tour="export-actions">
        <button type="submit" class="btn btn-lg">
            @if(($tipoReporte ?? 'ventas') === 'personalizada')
                Exportar CSV y aplicar movimiento
            @else
                Exportar CSV y aplicar movimiento
            @endif
        </button>
        @if(($tipoReporte ?? 'ventas') === 'ventas')
            <button type="button" id="clear-exclusions" class="btn secondary">Limpiar exclusiones</button>
        @endif
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const SUB_BY_CAT = @json($subByCat ?? []);
    const categorySelect = document.getElementById('categoria');
    const subcategorySelect = document.getElementById('subcategoria');
    const subcategoryWrap = document.getElementById('subcategoria-wrap');
    const excludeWrap = document.getElementById('exclude-categories-wrap');
    const excludePanel = document.getElementById('exclude-categories-panel');
    const excludeHeader = document.getElementById('exclude-panel-header');
    const excludeCatCount = document.getElementById('exclude-cat-count');
    const chipChecks = document.querySelectorAll('.exclude-cat-checkbox');
    const chkExcluir = document.getElementById('chk_excluir_categorias');
    const filtersForm = document.getElementById('filters-form');
    const exportForm = document.getElementById('export-form');
    const excludedInputs = document.getElementById('exclude-codes-inputs');
    const excludedCount = document.getElementById('excluded-count');
    const filteredCount = document.getElementById('filtered-count');
    const previewCards = document.querySelectorAll('#preview-list .product-card[data-code]');
    const totalRows = previewCards.length;

    function updateExcludeCatCount() {
        if (!excludeCatCount) return;
        const n = document.querySelectorAll('.exclude-cat-checkbox:checked').length;
        excludeCatCount.textContent = n === 0 ? 'Ninguna' : n + ' excluida' + (n > 1 ? 's' : '');
        excludeCatCount.classList.toggle('has-items', n > 0);
    }

    function updateExcludeCategoriesUi() {
        const todas = categorySelect && categorySelect.value === 'Todas';
        if (excludeWrap) excludeWrap.style.display = todas ? '' : 'none';
        if (!todas) {
            if (chkExcluir) chkExcluir.checked = false;
            chipChecks.forEach(c => { c.checked = false; c.disabled = true; });
        }
        const excluirActivo = todas && chkExcluir && chkExcluir.checked;
        if (excludePanel) excludePanel.classList.toggle('is-open', excluirActivo);
        if (excludeHeader) excludeHeader.classList.toggle('is-off', !excluirActivo);
        if (subcategoryWrap) subcategoryWrap.style.display = excluirActivo ? 'none' : '';
        if (subcategorySelect) subcategorySelect.disabled = todas && !excluirActivo;
        chipChecks.forEach(c => { c.disabled = !excluirActivo; });
        updateExcludeCatCount();
    }

    function updateSubcategoryOptionsFromCategory() {
        if (!categorySelect || !subcategorySelect) return;
        subcategorySelect.innerHTML = '';
        const defaultOpt = document.createElement('option');
        defaultOpt.value = 'Todas';
        defaultOpt.textContent = 'Todas';
        subcategorySelect.appendChild(defaultOpt);
        const cat = categorySelect.value;
        if (cat && cat !== 'Todas' && SUB_BY_CAT[cat]) {
            SUB_BY_CAT[cat].forEach(function (s) {
                const o = document.createElement('option');
                o.value = s;
                o.textContent = s;
                subcategorySelect.appendChild(o);
            });
            subcategorySelect.disabled = false;
        } else {
            subcategorySelect.disabled = cat === 'Todas';
        }
        const prev = '{{ addslashes($selectedSubcategoria) }}';
        if (prev && prev !== 'Todas') {
            const opt = Array.from(subcategorySelect.options).find(o => o.value === prev);
            if (opt) opt.selected = true;
        }
        updateExcludeCategoriesUi();
    }

    if (categorySelect) {
        updateSubcategoryOptionsFromCategory();
        categorySelect.addEventListener('change', updateSubcategoryOptionsFromCategory);
    }
    if (chkExcluir) {
        chkExcluir.addEventListener('change', function () {
            if (!chkExcluir.checked) {
                chipChecks.forEach(c => { c.checked = false; });
            }
            updateExcludeCategoriesUi();
            const exportFlag = document.getElementById('export-excluir-categorias');
            if (exportFlag) exportFlag.value = chkExcluir.checked ? '1' : '0';
        });
    }
    chipChecks.forEach(function (chip) {
        chip.addEventListener('change', updateExcludeCatCount);
    });
    updateExcludeCategoriesUi();

    function syncExcludedInputs() {
        excludedInputs.innerHTML = '';
        document.querySelectorAll('#preview-list .product-card.excluded-item').forEach(function (card) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'exclude_codes[]';
            input.value = card.dataset.code;
            excludedInputs.appendChild(input);
        });
        const excluded = document.querySelectorAll('#preview-list .product-card.excluded-item').length;
        if (excludedCount) excludedCount.textContent = excluded;
        if (filteredCount) filteredCount.textContent = totalRows - excluded;
    }

    function copyExcludedToFilters() {
        if (!filtersForm) return;
        filtersForm.querySelectorAll('input[name="exclude_codes[]"]').forEach(e => e.remove());
        exportForm.querySelectorAll('input[name="exclude_codes[]"]').forEach(function (c) {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'exclude_codes[]';
            inp.value = c.value;
            filtersForm.appendChild(inp);
        });
    }

    previewCards.forEach(function (card) {
        card.addEventListener('click', function () {
            card.classList.toggle('excluded-item');
            if (card.classList.contains('excluded-item')) {
                card.style.opacity = '.5';
                card.style.borderColor = '#fca5a5';
                card.style.background = '#fef2f2';
            } else {
                card.style.opacity = '';
                card.style.borderColor = '';
                card.style.background = '';
            }
            syncExcludedInputs();
            if (filtersForm) {
                copyExcludedToFilters();
                
                // Update URL search parameters dynamically without reloading the page
                const url = new URL(window.location.href);
                url.searchParams.delete('exclude_codes[]');
                
                document.querySelectorAll('#preview-list .product-card.excluded-item').forEach(function (c) {
                    url.searchParams.append('exclude_codes[]', c.dataset.code);
                });
                
                history.replaceState(null, '', url.toString());
            }
        });
    });

    const clearBtn = document.getElementById('clear-exclusions');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            previewCards.forEach(function (card) {
                card.classList.remove('excluded-item');
                card.style.opacity = '';
                card.style.borderColor = '';
                card.style.background = '';
            });
            syncExcludedInputs();
            if (excludedCount) excludedCount.textContent = '0';
            if (filteredCount) filteredCount.textContent = totalRows;
            
            if (filtersForm) {
                copyExcludedToFilters();
                
                // Remove exclude_codes from URL dynamically
                const url = new URL(window.location.href);
                url.searchParams.delete('exclude_codes[]');
                history.replaceState(null, '', url.toString());
            }
        });
    }

    syncExcludedInputs();
});
</script>
@endpush
