

<?php $__env->startSection('title', 'Ventas'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-header">
    <h1>Ventas — <?php echo e($sede); ?></h1>
    <p class="lead">Mostrando <?php echo e($rows->count()); ?> de <?php echo e($calculatedCount); ?> productos calculados.</p>
</div>

<div class="stats-row" data-tour="ventas-stats">
    <div class="stat-chip"><strong><?php echo e($rows->count()); ?></strong> filas visibles</div>
    <div class="stat-chip"><strong><?php echo e($calculatedCount); ?></strong> total calculado</div>
</div>

<form id="filters-form" method="GET" class="filter-bar" data-auto-filter data-auto-filter-delay="350" data-tour="ventas-filters">
    <div class="field field-wide">
        <label for="q">Buscar</label>
        <input type="search" id="q" name="q" value="<?php echo e($filters['q']); ?>" placeholder="Producto o código…" autocomplete="off">
    </div>
    <div class="field">
        <label for="categoria-select">Categoría</label>
        <select name="categoria" id="categoria-select">
            <option value="Ninguno">Todas</option>
            <?php $__currentLoopData = $categorias; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($cat); ?>" <?php if($filters['categoria'] === $cat): echo 'selected'; endif; ?>><?php echo e($cat); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div class="field">
        <label for="subcategoria-select">Subcategoría</label>
        <select name="subcategoria" id="subcategoria-select">
            <option value="Ninguno">Todas</option>
            <?php $__currentLoopData = $subcategorias; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sub): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($sub); ?>" <?php if($filters['subcategoria'] === $sub): echo 'selected'; endif; ?>><?php echo e($sub); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div class="field">
        <label for="accion-select">Acción</label>
        <select name="accion" id="accion-select">
            <?php $__currentLoopData = $accionesCombo; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $acc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($acc); ?>" <?php if($filters['accion'] === $acc): echo 'selected'; endif; ?>><?php echo e($acc === 'Ninguno' ? 'Todas' : $acc); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div class="field req-filter" <?php if(!$reqFiltersVisible): ?> style="display:none" <?php endif; ?>>
        <label for="req_opc">Sede (OPC)</label>
        <select name="req_opc" id="req_opc">
            <option value="Todos">Todos</option>
            <?php $__currentLoopData = $sedesOpc; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($s); ?>" <?php if($filters['req_opc'] === $s): echo 'selected'; endif; ?>><?php echo e($s); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div class="field req-filter" <?php if(!$reqFiltersVisible): ?> style="display:none" <?php endif; ?>>
        <label for="req_color">Estado requisición</label>
        <select name="req_color" id="req_color">
            <?php $__currentLoopData = config('inventario.req_colores'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($c); ?>" <?php if($filters['req_color'] === $c): echo 'selected'; endif; ?>><?php echo e($c); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div class="field">
        <label for="tiempo_pronostico">Pronóstico (días)</label>
        <input type="number" id="tiempo_pronostico" name="tiempo_pronostico" min="1" max="365" value="<?php echo e($tiempoPronostico); ?>">
    </div>
</form>

<section class="table-section-full" data-tour="ventas-table">
    <div class="table-wrap table-wrap-full">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Exist.</th>
                    <th>Categoría</th>
                    <th>Subcat.</th>
                    <th>Venta 15d</th>
                    <?php $__currentLoopData = $sedesStock; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sedeCol): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <th><?php echo e(config('inventario.display.'.$sedeCol, $sedeCol)); ?></th>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <th>Sugerido</th>
                    <th>OPC</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $tag = $row['req_tag'] ?? '';
                        $rowClass = match($tag) {
                            'req_ok' => 'row-req-ok',
                            'req_parcial' => 'row-req-parcial',
                            'req_insuf' => 'row-req-insuf',
                            default => '',
                        };
                    ?>
                    <tr class="<?php echo e($rowClass); ?>">
                        <td><?php echo e($row['cod_centro']); ?></td>
                        <td><?php echo e($row['producto']); ?></td>
                        <td><?php echo e($row['existencia']); ?></td>
                        <td><?php echo e($row['categoria']); ?></td>
                        <td><?php echo e($row['subcategoria']); ?></td>
                        <td><?php echo e($row['venta']); ?></td>
                        <?php $__currentLoopData = $sedesStock; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sedeCol): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <td><?php echo e($row['stocks'][$sedeCol] ?? 0); ?></td>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <td><?php echo e($row['sugerido'] ?: '—'); ?></td>
                        <td><?php echo e($row['opc'] ?: '—'); ?></td>
                        <td>
                            <?php $acc = $row['accion']; ?>
                            <?php if($acc === 'HACER REQUISICION'): ?>
                                <span class="tag req"><?php echo e($acc); ?></span>
                            <?php elseif($acc === 'TIENE EXISTENCIA'): ?>
                                <span class="tag ok"><?php echo e($acc); ?></span>
                            <?php elseif($acc === 'NO TIENE EXISTENCIA'): ?>
                                <span class="tag warn"><?php echo e($acc); ?></span>
                            <?php else: ?>
                                <span class="tag no"><?php echo e($acc); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="<?php echo e(10 + count($sedesStock)); ?>">Sin datos. Importe el Excel multisede desde el panel admin.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
(function () {
    const accion = document.getElementById('accion-select');
    const reqBlocks = document.querySelectorAll('.req-filter');
    const cat = document.getElementById('categoria-select');
    const sub = document.getElementById('subcategoria-select');

    if (accion) {
        accion.addEventListener('change', () => {
            const show = accion.value === 'HACER REQUISICION';
            reqBlocks.forEach(el => el.style.display = show ? '' : 'none');
        });
    }

    if (cat && sub) {
        function updateSubDisabled() {
            sub.disabled = (cat.value === 'Ninguno');
        }
        updateSubDisabled();
        cat.addEventListener('change', updateSubDisabled);
    }

    const since = <?php echo json_encode($stockUpdatedAt, 15, 512) ?>;
    if (!since) return;
    setInterval(async () => {
        try {
            const r = await fetch(<?php echo json_encode(route('ventas.sync'), 15, 512) ?>.replace(/&amp;/g, '&') + '?since=' + encodeURIComponent(since));
            const j = await r.json();
            if (j.changed) location.reload();
        } catch (e) {}
    }, 15000);
})();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\freyg\OneDrive\Imágenes\CALL CENTER\laravel_app\resources\views\ventas\index.blade.php ENDPATH**/ ?>