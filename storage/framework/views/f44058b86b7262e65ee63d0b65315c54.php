

<?php $__env->startSection('title', 'Usuarios'); ?>

<?php $__env->startSection('content'); ?>
<div class="panel">
    <h1 style="margin-top:0;">Usuarios registrados</h1>
    <p class="muted">Asigne o cambie la sede de cada usuario.</p>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Sede</th>
                    <th>Cambiar sede</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><?php echo e($user->name); ?></td>
                        <td><?php echo e($user->email); ?></td>
                        <td><?php echo e($user->role); ?></td>
                        <td><?php echo e($user->sede ?: '—'); ?></td>
                        <td>
                            <?php if(! $user->isAdmin()): ?>
                                <form method="POST" action="<?php echo e(route('admin.users.update', $user)); ?>" class="filters" style="margin:0;">
                                    <?php echo csrf_field(); ?>
                                    <select name="sede">
                                        <option value="">— Ninguna —</option>
                                        <?php $__currentLoopData = $sedes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($s); ?>" <?php if($user->sede === $s): echo 'selected'; endif; ?>>
                                                <?php echo e(config('inventario.display.'.$s, $s)); ?>

                                            </option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                    <button type="submit" class="btn secondary">Guardar</button>
                                </form>
                            <?php else: ?>
                                <span class="muted">Admin</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\freyg\OneDrive\Imágenes\CALL CENTER\laravel_app\resources\views\admin\users\index.blade.php ENDPATH**/ ?>