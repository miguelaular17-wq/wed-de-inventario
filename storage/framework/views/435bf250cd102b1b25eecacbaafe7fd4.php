

<?php $__env->startSection('title', 'Movimientos — Admin'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-header">
    <h1>Movimientos de stock</h1>
    <p class="lead">Historial multisede de requisiciones y traslados · hasta 500 registros más recientes.</p>
</div>

<div class="stats-row">
    <div class="stat-chip"><strong><?php echo e($rows->count()); ?></strong> movimientos visibles</div>
</div>

<form method="GET" class="filter-bar" data-auto-filter data-auto-filter-delay="350">
    <div class="field field-wide">
        <label for="q">Buscar</label>
        <input type="search" id="q" name="q" value="<?php echo e($filters['q']); ?>" placeholder="Código o nombre de producto…" autocomplete="off">
    </div>
    <div class="field">
        <label for="sede">Sede</label>
        <select id="sede" name="sede">
            <option value="">Todas</option>
            <?php $__currentLoopData = $sedes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($s); ?>" <?php if($filters['sede'] === $s): echo 'selected'; endif; ?>><?php echo e(config('inventario.display.'.$s, $s)); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div class="field">
        <label for="tipo">Tipo</label>
        <select id="tipo" name="tipo">
            <option value="">Todos</option>
            <?php $__currentLoopData = $tipos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($t); ?>" <?php if($filters['tipo'] === $t): echo 'selected'; endif; ?>><?php echo e($t); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div class="field">
        <label for="desde">Desde</label>
        <input type="date" id="desde" name="desde" value="<?php echo e($filters['desde']); ?>">
    </div>
    <div class="field">
        <label for="hasta">Hasta</label>
        <input type="date" id="hasta" name="hasta" value="<?php echo e($filters['hasta']); ?>">
    </div>
</form>

<section class="table-section-full">
    <div class="table-wrap table-wrap-full">
        <table class="data-table movements-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Origen → Destino</th>
                    <th>Tipo</th>
                    <th>Cant.</th>
                    <th>Usuario</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td class="cell-nowrap"><?php echo e($row['created_at']); ?></td>
                        <td class="cell-code"><?php echo e($row['codigo']); ?></td>
                        <td class="cell-product" title="<?php echo e($row['producto']); ?>"><?php echo e($row['producto']); ?></td>
                        <td class="cell-route">
                            <span class="route-pill"><?php echo e(config('inventario.display.'.$row['origen'], $row['origen'])); ?></span>
                            <span class="route-arrow">→</span>
                            <span class="route-pill route-pill-dest"><?php echo e(config('inventario.display.'.$row['destino'], $row['destino'])); ?></span>
                        </td>
                        <td>
                            <span class="tag <?php echo e(strtolower($row['tipo']) === 'requisicion' ? 'req' : 'no'); ?>"><?php echo e($row['tipo']); ?></span>
                        </td>
                        <td class="cell-qty"><strong><?php echo e($row['cantidad']); ?></strong></td>
                        <td class="cell-user"><?php echo e($row['usuario']); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="7">Sin movimientos registrados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('head'); ?>
<style>
    .movements-table .cell-nowrap { white-space: nowrap; font-size: .82rem; color: var(--muted); }
    .movements-table .cell-code { font-family: ui-monospace, monospace; font-size: .82rem; white-space: nowrap; }
    .movements-table .cell-product { max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .movements-table .cell-qty { text-align: center; }
    .movements-table .cell-user { white-space: nowrap; font-weight: 500; }
    .movements-table .cell-route { white-space: nowrap; }
    .route-pill {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 6px;
        background: #f1f5f9;
        font-size: .78rem;
        font-weight: 500;
    }
    .route-pill-dest { background: #eef4fc; color: var(--blue); }
    .route-arrow { color: var(--muted); margin: 0 4px; font-size: .85rem; }
</style>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\freyg\OneDrive\Imágenes\CALL CENTER\laravel_app\resources\views\admin\movimientos\index.blade.php ENDPATH**/ ?>