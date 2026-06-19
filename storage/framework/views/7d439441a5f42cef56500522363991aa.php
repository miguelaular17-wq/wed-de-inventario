

<?php $__env->startSection('content'); ?>
<div class="panel" style="max-width:480px;margin:40px auto;">
    <h2>Cambiar sede</h2>
    <p class="muted">Elija la sede con la que desea trabajar.</p>

    <form method="POST" action="<?php echo e(route('user.sede.update')); ?>">
        <?php echo csrf_field(); ?>
        <label for="sede">Sede</label>
        <select name="sede" id="sede" required style="width:100%; margin:8px 0 16px;">
            <option value="">— Seleccione —</option>
            <?php $__currentLoopData = $sedes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($s); ?>" <?php if(auth()->user()->sede === $s): echo 'selected'; endif; ?>><?php echo e($display[$s] ?? $s); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>

        <div>
            <button type="submit" class="btn">Guardar</button>
            <a href="<?php echo e(url('/')); ?>" class="btn secondary" style="margin-left:8px;">Cancelar</a>
        </div>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\freyg\OneDrive\Imágenes\CALL CENTER\laravel_app\resources\views\user\sede.blade.php ENDPATH**/ ?>