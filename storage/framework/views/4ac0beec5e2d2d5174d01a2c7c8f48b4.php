

<?php $__env->startSection('title', 'Panel admin'); ?>

<?php $__env->startSection('content'); ?>
<div class="panel" data-tour="admin-dashboard">
    <h1 style="margin-top:0;">Panel de administración</h1>
    <p class="muted">Gestión multisede: importación de stock y auditoría de movimientos.</p>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:20px 0;">
        <div class="stat-card">
            <div class="stat-value"><?php echo e($productCount); ?></div>
            <div class="stat-label">Productos activos</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo e($movementStats['total'] ?? 0); ?></div>
            <div class="stat-label">Movimientos totales</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo e($movementStats['requisiciones'] ?? 0); ?></div>
            <div class="stat-label">Requisiciones</div>
        </div>
    </div>

    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <a href="<?php echo e(route('admin.import.create')); ?>" class="btn" data-tour="admin-import-btn">Subir ExelMultiSede (.xlsx)</a>
        <a href="<?php echo e(route('admin.movimientos.index')); ?>" class="btn secondary">Ver movimientos (todas las sedes)</a>
        <a href="<?php echo e(route('admin.users.index')); ?>" class="btn secondary">Gestionar usuarios</a>
        <?php if(session('sede_local')): ?>
            <a href="<?php echo e(route('ventas.index')); ?>" class="btn secondary">Ir a Ventas</a>
        <?php else: ?>
            <a href="<?php echo e(route('sede.select')); ?>" class="btn secondary">Elegir sede operativa</a>
        <?php endif; ?>
    </div>

    <?php if($lastImport): ?>
        <p class="muted" style="margin-top:16px;">Última actualización de stock: <?php echo e($lastImport); ?></p>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('head'); ?>
<style>
    .stat-card { background:#eef3fb;border-radius:8px;padding:16px;text-align:center; }
    .stat-value { font-size:1.8rem;font-weight:700;color:var(--blue); }
    .stat-label { font-size:.85rem;color:#555;margin-top:4px; }
</style>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\freyg\OneDrive\Imágenes\CALL CENTER\laravel_app\resources\views\admin\dashboard.blade.php ENDPATH**/ ?>