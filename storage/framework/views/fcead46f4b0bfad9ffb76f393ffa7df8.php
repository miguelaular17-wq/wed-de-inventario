

<?php $__env->startSection('title', 'Iniciar sesión'); ?>

<?php $__env->startSection('content'); ?>
<div class="panel auth-panel">
    <h1 style="margin-top:0;">Iniciar sesión</h1>
    <p class="muted">Accede a Ventas, Inventario y Requisiciones de tu sede.</p>

    <form method="POST" action="<?php echo e(route('login.store')); ?>">
        <?php echo csrf_field(); ?>
        <div class="auth-field">
            <label for="email">Correo</label>
            <input type="email" id="email" name="email" value="<?php echo e(old('email')); ?>" required autofocus>
        </div>
        <div class="auth-field">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required>
        </div>
        <label class="auth-remember">
            <input type="checkbox" name="remember" value="1"> Recordarme
        </label>
        <button type="submit" class="btn">Entrar</button>
    </form>

    <p class="auth-footer muted">
        ¿No tienes cuenta? <a href="<?php echo e(route('register')); ?>">Regístrate aquí</a>
    </p>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('head'); ?>
<style>
    .auth-panel { max-width: 420px; margin: 40px auto; }
    .auth-field { margin-bottom: 14px; }
    .auth-field label { display: block; font-size: .85rem; margin-bottom: 4px; color: #555; }
    .auth-field input { width: 100%; }
    .auth-remember { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; font-size: .9rem; }
    .auth-footer { margin-top: 20px; text-align: center; }
    .auth-footer a { color: var(--blue); }
</style>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\freyg\OneDrive\Imágenes\CALL CENTER\laravel_app\resources\views/auth/login.blade.php ENDPATH**/ ?>