

<?php $__env->startSection('title', 'Registro'); ?>

<?php $__env->startSection('content'); ?>
<div class="panel auth-panel">
    <h1 style="margin-top:0;">Crear cuenta</h1>
    <p class="muted">Regístrate para usar el inventario de tu sede.</p>

    <form method="POST" action="<?php echo e(route('register.store')); ?>">
        <?php echo csrf_field(); ?>
        <div class="auth-field">
            <label for="name">Nombre</label>
            <input type="text" id="name" name="name" value="<?php echo e(old('name')); ?>" required autofocus>
        </div>
        <div class="auth-field">
            <label for="email">Correo</label>
            <input type="email" id="email" name="email" value="<?php echo e(old('email')); ?>" required>
        </div>
        <div class="auth-field">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required minlength="6">
        </div>
        <div class="auth-field">
            <label for="password_confirmation">Confirmar contraseña</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required>
        </div>
        <div class="auth-field">
            <label for="sede">Sede</label>
            <select id="sede" name="sede" required style="width:100%;">
                <option value="">— Seleccione —</option>
                <?php $__currentLoopData = $sedes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($s); ?>" <?php if(old('sede') === $s): echo 'selected'; endif; ?>>
                        <?php echo e(config('inventario.display.'.$s, $s)); ?>

                    </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
        <button type="submit" class="btn">Registrarme</button>
    </form>

    <p class="auth-footer muted">
        ¿Ya tienes cuenta? <a href="<?php echo e(route('login')); ?>">Inicia sesión</a>
    </p>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('head'); ?>
<style>
    .auth-panel { max-width: 420px; margin: 40px auto; }
    .auth-field { margin-bottom: 14px; }
    .auth-field label { display: block; font-size: .85rem; margin-bottom: 4px; color: #555; }
    .auth-field input, .auth-field select { width: 100%; }
    .auth-footer { margin-top: 20px; text-align: center; }
    .auth-footer a { color: var(--blue); }
</style>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\freyg\OneDrive\Imágenes\CALL CENTER\laravel_app\resources\views\auth\register.blade.php ENDPATH**/ ?>