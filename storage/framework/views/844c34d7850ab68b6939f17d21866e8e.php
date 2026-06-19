

<?php $__env->startSection('title', 'Exportar requisición'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-header">
    <h1>Exportar reporte</h1>
    <p class="lead">Sede <?php echo e($sede); ?> · Los filtros se aplican al instante.</p>
</div>

<div class="stats-row">
    <div class="stat-chip"><strong><?php echo e($totalRequisicion); ?></strong> en requisición</div>
    <div class="stat-chip"><strong id="filtered-count"><?php echo e(max(0, ($filteredCount ?? 0) - count(array_filter($excludeCodes ?? [])))); ?></strong> incluidos</div>
    <div class="stat-chip"><strong id="excluded-count"><?php echo e(count(array_filter($excludeCodes ?? []))); ?></strong> excluidos</div>
</div>

<form id="tipo-form" method="GET" action="<?php echo e(route('requisicion.form')); ?>" class="filter-bar" style="margin-bottom:16px;" data-auto-filter data-auto-filter-delay="0" data-tour="export-type">
    <div class="field field-wide">
        <label for="tipo_reporte">Tipo de reporte</label>
        <select name="tipo_reporte" id="tipo_reporte">
            <option value="ventas" <?php if(($tipoReporte ?? 'ventas') === 'ventas'): echo 'selected'; endif; ?>>Requisición automática (ventas)</option>
            <option value="personalizada" <?php if(($tipoReporte ?? '') === 'personalizada'): echo 'selected'; endif; ?>>Requisición personalizada (manual)</option>
        </select>
    </div>
</form>

<?php if(($tipoReporte ?? 'ventas') === 'ventas'): ?>
    <p class="muted" style="margin:0 0 16px;">
        Genera CSV y <strong>aplica movimiento de stock</strong> al exportar. Clic en un producto para excluirlo del reporte.
    </p>
<?php else: ?>
    <p class="muted" style="margin:0 0 16px;">
        Exporta requisiciones registradas en <a href="<?php echo e(route('inventario.index')); ?>">Inventario</a>. El stock ya se aplicó al confirmar cada línea.
    </p>
<?php endif; ?>

<form id="filters-form" method="GET" action="<?php echo e(route('requisicion.form')); ?>" class="export-filters-form" data-auto-filter data-auto-filter-delay="400" data-auto-filter-preserve="exclude_codes" data-tour="export-filters">
    <input type="hidden" name="tipo_reporte" value="<?php echo e($tipoReporte ?? 'ventas'); ?>">

    <div class="filter-bar filter-row-main <?php if(($tipoReporte ?? 'ventas') === 'personalizada'): ?> filter-row-standalone <?php endif; ?>">
        <div class="field">
            <label for="sede_origen">Sede origen</label>
            <select name="sede_origen" id="sede_origen" <?php if(($tipoReporte ?? 'ventas') === 'ventas'): ?> required <?php endif; ?>>
                <?php if(($tipoReporte ?? 'ventas') === 'personalizada'): ?>
                    <option value="Todas" <?php if($selectedSedeOrigen === 'Todas'): echo 'selected'; endif; ?>>Todas las sedes</option>
                <?php endif; ?>
                <?php $__currentLoopData = $sedesOrigen; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $origen): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e(config('inventario.display.'.$origen, $origen)); ?>" <?php if($selectedSedeOrigen === config('inventario.display.'.$origen, $origen)): echo 'selected'; endif; ?>>
                        <?php echo e(config('inventario.display.'.$origen, $origen)); ?>

                    </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
        <div class="field">
            <label for="categoria">Categoría</label>
            <select name="categoria" id="categoria">
                <option value="Todas" <?php if($selectedCategoria === 'Todas'): echo 'selected'; endif; ?>>Todas</option>
                <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($cat); ?>" <?php if($selectedCategoria === $cat): echo 'selected'; endif; ?>><?php echo e($cat); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
        <div class="field" id="subcategoria-wrap">
            <label for="subcategoria">Subcategoría</label>
            <select name="subcategoria" id="subcategoria" <?php if($selectedCategoria === 'Todas' && !($excluirCategorias ?? false)): ?> disabled <?php endif; ?>>
                <option value="Todas" <?php if($selectedSubcategoria === 'Todas'): echo 'selected'; endif; ?>>Todas</option>
                <?php $__currentLoopData = $subcategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sub): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($sub); ?>" <?php if($selectedSubcategoria === $sub): echo 'selected'; endif; ?>><?php echo e($sub); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
    </div>

    <?php if(($tipoReporte ?? 'ventas') === 'ventas'): ?>
    <div class="filter-options-row">
        <label class="option-toggle">
            <input type="checkbox" name="incluir_parcial" value="1" <?php if(request()->boolean('incluir_parcial')): echo 'checked'; endif; ?>>
            <span class="option-toggle-text">
                <strong>Incluir parciales</strong>
                <span>Agrega requisiciones con stock insuficiente en origen</span>
            </span>
        </label>
    </div>

    <div id="exclude-categories-wrap" class="exclude-panel" <?php if($selectedCategoria !== 'Todas'): ?> style="display:none" <?php endif; ?>>
        <div class="exclude-panel-header <?php echo e(($excluirCategorias ?? false) ? '' : 'is-off'); ?>" id="exclude-panel-header">
            <label class="toggle-switch" title="Activar exclusión por categoría">
                <input type="checkbox" id="chk_excluir_categorias" name="excluir_categorias" value="1"
                    <?php if($excluirCategorias ?? false): echo 'checked'; endif; ?>>
                <span class="toggle-slider"></span>
            </label>
            <div class="exclude-panel-title">
                <strong>Excluir categorías</strong>
                <span>Marca las categorías que no deben salir en el reporte</span>
            </div>
            <span class="exclude-count-badge <?php echo e(count($excludeCategories) ? 'has-items' : ''); ?>" id="exclude-cat-count">
                <?php if(count($excludeCategories)): ?>
                    <?php echo e(count($excludeCategories)); ?> excluida<?php echo e(count($excludeCategories) > 1 ? 's' : ''); ?>

                <?php else: ?>
                    Ninguna
                <?php endif; ?>
            </span>
        </div>
        <div id="exclude-categories-panel" class="category-chip-grid <?php echo e(($excluirCategorias ?? false) ? 'is-open' : ''); ?>">
            <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <label class="category-chip">
                    <input type="checkbox"
                        class="exclude-cat-checkbox"
                        name="exclude_categories[]"
                        value="<?php echo e($cat); ?>"
                        <?php if(in_array($cat, $excludeCategories, true)): echo 'checked'; endif; ?>
                        <?php if(!($excluirCategorias ?? false)): echo 'disabled'; endif; ?>>
                    <span class="chip-face"><?php echo e($cat); ?></span>
                </label>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
    <?php endif; ?>
</form>

<?php if($previewRows->isNotEmpty()): ?>
<div class="panel">
    <?php if(($tipoReporte ?? 'ventas') === 'ventas'): ?>
        <p class="muted" style="margin:0 0 12px;">Productos incluidos — clic para excluir</p>
    <?php else: ?>
        <p class="muted" style="margin:0 0 12px;">Requisiciones manuales incluidas en el reporte</p>
    <?php endif; ?>
    <div class="product-grid" id="preview-list">
        <?php $__currentLoopData = $previewRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php $isExcluded = in_array($row['codigo'], $excludeCodes ?? [], true); ?>
            <article class="product-card <?php echo e($isExcluded ? 'excluded-item' : ''); ?> <?php echo e(($tipoReporte ?? 'ventas') === 'personalizada' ? 'manual-preview' : ''); ?>"
                <?php if(($tipoReporte ?? 'ventas') === 'ventas'): ?>
                data-code="<?php echo e($row['codigo']); ?>"
                <?php endif; ?>
                style="<?php echo e($isExcluded ? 'opacity:.5;border-color:#fca5a5;background:#fef2f2;' : ''); ?>">
                <div class="code"><?php echo e($row['codigo']); ?></div>
                <div class="name"><?php echo e($row['producto']); ?></div>
                <div class="stock-pills">
                    <span class="stock-pill">Origen <strong><?php echo e($row['opc']); ?></strong></span>
                    <span class="stock-pill">Cant. <strong><?php echo e($row['cantidad']); ?></strong></span>
                    <?php if(($tipoReporte ?? 'ventas') === 'personalizada' && ($row['categoria'] ?? '—') !== '—'): ?>
                        <span class="stock-pill"><?php echo e($row['categoria']); ?></span>
                    <?php endif; ?>
                </div>
                <?php if($isExcluded): ?>
                    <span class="tag warn">Excluido</span>
                <?php elseif(($tipoReporte ?? 'ventas') === 'personalizada'): ?>
                    <span class="tag manual">Manual</span>
                <?php endif; ?>
            </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</div>
<?php elseif($previewRows->isEmpty()): ?>
<div class="panel empty-state">
    <?php if(($tipoReporte ?? 'ventas') === 'personalizada' && ($totalRequisicion ?? 0) > 0): ?>
        <p>Hay <strong><?php echo e($totalRequisicion); ?></strong> requisiciones registradas, pero ninguna coincide con los filtros actuales<?php echo e($selectedSedeOrigen !== 'Todas' ? ' (sede origen: '.$selectedSedeOrigen.')' : ''); ?>.</p>
        <p class="muted" style="margin-top:8px;">Prueba seleccionar <strong>Todas las sedes</strong> en el filtro de origen.</p>
    <?php else: ?>
        <p>No hay resultados para estos filtros.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<form method="POST" action="<?php echo e(route('requisicion.export')); ?>" id="export-form">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="tipo_reporte" value="<?php echo e($tipoReporte ?? 'ventas'); ?>">
    <input type="hidden" name="sede_origen" value="<?php echo e($selectedSedeOrigen); ?>">
    <input type="hidden" name="categoria" value="<?php echo e($selectedCategoria); ?>">
    <input type="hidden" name="subcategoria" value="<?php echo e($selectedSubcategoria); ?>">
    <input type="hidden" name="incluir_parcial" value="<?php echo e(request()->boolean('incluir_parcial') ? 1 : 0); ?>">
    <input type="hidden" name="excluir_categorias" id="export-excluir-categorias" value="<?php echo e(($excluirCategorias ?? false) ? 1 : 0); ?>">
    <?php $__currentLoopData = $excludeCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $excludedCategory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <input type="hidden" name="exclude_categories[]" value="<?php echo e($excludedCategory); ?>">
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <div id="exclude-codes-inputs"></div>
    <div class="export-actions" data-tour="export-actions">
        <button type="submit" class="btn btn-lg">
            <?php if(($tipoReporte ?? 'ventas') === 'personalizada'): ?>
                Exportar CSV (manual)
            <?php else: ?>
                Exportar CSV y aplicar movimiento
            <?php endif; ?>
        </button>
        <?php if(($tipoReporte ?? 'ventas') === 'ventas'): ?>
            <button type="button" id="clear-exclusions" class="btn secondary">Limpiar exclusiones</button>
        <?php endif; ?>
    </div>
</form>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const SUB_BY_CAT = <?php echo json_encode($subByCat ?? [], 15, 512) ?>;
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
        const prev = '<?php echo e(addslashes($selectedSubcategoria)); ?>';
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

    let rowDebounce = null;
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
                clearTimeout(rowDebounce);
                rowDebounce = setTimeout(function () { filtersForm.submit(); }, 350);
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
        });
    }

    syncExcludedInputs();
});
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\freyg\OneDrive\Imágenes\CALL CENTER\laravel_app\resources\views\requisicion\export.blade.php ENDPATH**/ ?>