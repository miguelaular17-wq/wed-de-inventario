<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', 'Inventario Multisede'); ?></title>
    <link rel="stylesheet" href="<?php echo e(asset('css/app.css')); ?>">
    <?php if(config('inventario.tutorial_enabled')): ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/onboarding-tour.css')); ?>">
    <?php endif; ?>
    <?php echo $__env->yieldPushContent('head'); ?>
</head>
<body>
<header>
    <div class="wrap">
        <div>
            <strong>Inventario Multisede</strong>
            <?php if(session('sede_local')): ?>
                <span class="badge" data-tour="sede-badge">Sede: <?php echo e(session('sede_local')); ?></span>
            <?php endif; ?>
            <?php if(auth()->guard()->check()): ?>
                <?php if(auth()->user()->isAdmin()): ?>
                    <span class="badge">Admin</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <?php if(auth()->guard()->check()): ?>
                <?php if(auth()->user()->isAdmin()): ?>
                    <nav>
                        <a href="<?php echo e(route('admin.dashboard')); ?>" data-tour="admin-dashboard" class="<?php echo e(request()->routeIs('admin.dashboard') ? 'active' : ''); ?>">Admin</a>
                        <a href="<?php echo e(route('admin.movimientos.index')); ?>" data-tour="admin-movimientos" class="<?php echo e(request()->routeIs('admin.movimientos.*') ? 'active' : ''); ?>">Movimientos</a>
                        <a href="<?php echo e(route('admin.import.create')); ?>" data-tour="admin-import" class="<?php echo e(request()->routeIs('admin.import.*') ? 'active' : ''); ?>">Importar</a>
                        <a href="<?php echo e(route('admin.users.index')); ?>" class="<?php echo e(request()->routeIs('admin.users.*') ? 'active' : ''); ?>">Usuarios</a>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
            <?php if(session('sede_local')): ?>
                <nav data-tour="nav-main">
                    <a href="<?php echo e(route('ventas.index')); ?>" data-tour="nav-ventas" class="<?php echo e(request()->routeIs('ventas.*') ? 'active' : ''); ?>">Ventas</a>
                    <a href="<?php echo e(route('inventario.index')); ?>" data-tour="nav-inventario" class="<?php echo e(request()->routeIs('inventario.*') ? 'active' : ''); ?>">Inventario</a>
                    <a href="<?php echo e(route('requisicion.form')); ?>" data-tour="nav-export" class="<?php echo e(request()->routeIs('requisicion.*') ? 'active' : ''); ?>">Exportar</a>
                </nav>
                <?php if(auth()->guard()->check()): ?>
                    <?php if(auth()->user()->isAdmin()): ?>
                        <a href="<?php echo e(route('admin.sedes.index')); ?>" class="btn secondary" style="padding:6px 12px;font-size:.85rem;">Cambiar sede</a>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
            <?php if(auth()->guard()->guest()): ?>
                <a href="<?php echo e(route('login')); ?>" class="btn secondary" style="padding:6px 12px;font-size:.85rem;">Iniciar sesión</a>
                <a href="<?php echo e(route('register')); ?>" class="btn" style="padding:6px 12px;font-size:.85rem;">Registrarse</a>
            <?php else: ?>
                <?php if(config('inventario.tutorial_enabled')): ?>
                <form method="POST" action="<?php echo e(route('tutorial.restart')); ?>" style="margin:0;">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="btn secondary tour-help-btn" title="Ver tutorial guiado">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Ayuda
                    </button>
                </form>
                <?php endif; ?>
                <span class="badge"><?php echo e(auth()->user()->name); ?></span>
                <form method="POST" action="<?php echo e(route('logout')); ?>">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="btn secondary" style="padding:6px 12px;font-size:.85rem;">Salir</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</header>

<main>
    <?php if(session('status')): ?>
        <div class="success"><?php echo e(session('status')); ?></div>
    <?php endif; ?>
    <?php if($errors->any()): ?>
        <div class="errors">
            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div><?php echo e($error); ?></div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endif; ?>
    <?php echo $__env->yieldContent('content'); ?>
</main>

<script src="<?php echo e(asset('js/auto-filters.js')); ?>"></script>
<?php if(config('inventario.tutorial_enabled')): ?>
<?php if(auth()->guard()->check()): ?>
<script>
window.__TOUR__ = {
    startStep: <?php echo e((int) (auth()->user()->tutorial_step ?? -1)); ?>,
    forceStart: <?php echo e(request()->boolean('tour') ? 'true' : 'false'); ?>,
    isAdmin: <?php echo e(auth()->user()->isAdmin() ? 'true' : 'false'); ?>,
    hasSede: <?php echo e(session('sede_local') ? 'true' : 'false'); ?>,
    currentPage: <?php echo json_encode(optional(request()->route())->getName() ?? '', 15, 512) ?>,
    advanceUrl: <?php echo json_encode(route('tutorial.advance'), 15, 512) ?>,
    completeUrl: <?php echo json_encode(route('tutorial.complete'), 15, 512) ?>,
    routes: {
        ventas: <?php echo json_encode(route('ventas.index'), 15, 512) ?>,
        inventario: <?php echo json_encode(route('inventario.index'), 15, 512) ?>,
        export: <?php echo json_encode(route('requisicion.form'), 15, 512) ?>,
        admin: <?php echo json_encode(route('admin.dashboard'), 15, 512) ?>,
    },
};
</script>
<script src="<?php echo e(asset('js/onboarding-tour.js')); ?>"></script>
<?php endif; ?>
<?php endif; ?>
<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\Users\freyg\OneDrive\Imágenes\CALL CENTER\laravel_app\resources\views\layouts\app.blade.php ENDPATH**/ ?>