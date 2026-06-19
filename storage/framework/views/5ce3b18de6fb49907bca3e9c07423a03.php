

<?php $__env->startSection('title', 'Seleccionar sede'); ?>

<?php $__env->startSection('content'); ?>
<div class="panel" style="max-width: 480px; margin: 40px auto;">
    <h1 style="margin-top:0;">Seleccione su sede</h1>
    <p class="muted">Elija la sucursal con la que trabajará en esta sesión.</p>
    <form method="POST" action="<?php echo e(route('sede.store')); ?>">
        <?php echo csrf_field(); ?>
        <label for="sede_local">Sede local</label>
        <select name="sede_local" id="sede_local" required style="width:100%; margin: 8px 0 16px;">
            <option value="">— Seleccione —</option>
            <?php $__currentLoopData = $sedes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sede): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($sede); ?>" <?php if(old('sede_local', auth()->user()?->sede) === $sede): echo 'selected'; endif; ?>>
                    <?php echo e(config('inventario.display.'.$sede, $sede)); ?>

                </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <button type="submit" class="btn">Continuar</button>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\freyg\OneDrive\Imágenes\CALL CENTER\laravel_app\resources\views\sede\select.blade.php ENDPATH**/ ?>