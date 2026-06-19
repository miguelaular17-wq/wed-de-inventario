

<?php $__env->startSection('content'); ?>
<div class="panel">
    <h2>Ver como sede</h2>
    <p class="muted">Seleccione una sede para ver la aplicación con datos de esa sucursal.</p>

    <table>
        <thead>
            <tr><th>Sede</th><th>Acción</th></tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $sedes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($display[$s] ?? $s); ?></td>
                    <td>
                        <form method="POST" action="<?php echo e(route('admin.sedes.use', $s)); ?>">
                            <?php echo csrf_field(); ?>
                            <button class="btn" type="submit">Ver como <?php echo e($display[$s] ?? $s); ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\freyg\OneDrive\Imágenes\CALL CENTER\laravel_app\resources\views/admin/sedes/index.blade.php ENDPATH**/ ?>