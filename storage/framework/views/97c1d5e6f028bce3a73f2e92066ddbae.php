

<?php $__env->startSection('title', 'Inventario — Requisición personalizada'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-header">
    <h1>Requisición personalizada</h1>
    <p class="lead">Sede <?php echo e($sede); ?> · Productos con stock en otras sucursales. Toca una tarjeta para pedir manualmente.</p>
</div>

<div class="stats-row">
    <div class="stat-chip"><strong><?php echo e($rows->count()); ?></strong> visibles</div>
    <div class="stat-chip"><strong><?php echo e($totalManual ?? 0); ?></strong> manuales registrados</div>
</div>

<form method="GET" class="filter-bar" data-auto-filter data-auto-filter-delay="350" data-tour="inventario-filters">
    <div class="field field-wide">
        <label for="q">Buscar</label>
        <input type="search" id="q" name="q" value="<?php echo e($filters['q'] ?? ''); ?>" placeholder="Código o nombre de producto…" autocomplete="off">
    </div>
    <div class="field">
        <label for="categoria">Categoría</label>
        <select id="categoria" name="categoria">
            <option value="Ninguno">Todas</option>
            <?php $__currentLoopData = $categorias ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($cat); ?>" <?php if(($filters['categoria'] ?? 'Ninguno') === $cat): echo 'selected'; endif; ?>><?php echo e($cat); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div class="field">
        <label for="subcategoria">Subcategoría</label>
        <select id="subcategoria" name="subcategoria">
            <option value="Ninguno">Todas</option>
            <?php $__currentLoopData = $subcategorias ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sub): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($sub); ?>" <?php if(($filters['subcategoria'] ?? 'Ninguno') === $sub): echo 'selected'; endif; ?>><?php echo e($sub); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
</form>

<?php if($rows->isEmpty()): ?>
    <div class="panel empty-state" data-tour="inventario-grid">
        <p>No hay productos con stock en otras sedes para estos filtros.</p>
    </div>
<?php else: ?>
    <div class="product-grid" data-tour="inventario-grid">
        <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <article class="product-card <?php echo e(($row['req_manual'] ?? false) ? 'has-manual' : ''); ?>"
                data-codigo="<?php echo e($row['cod_centro']); ?>"
                data-producto="<?php echo e(e($row['producto'])); ?>"
                data-origen-manual="<?php echo e($row['origen_manual'] ?? ''); ?>"
                data-cantidad-manual="<?php echo e($row['cantidad_manual'] ?? 0); ?>">
                <div class="code"><?php echo e($row['cod_centro']); ?></div>
                <div class="name"><?php echo e($row['producto']); ?></div>
                <div class="stock-pills">
                    <span class="stock-pill">Local <strong><?php echo e($row['existencia']); ?></strong></span>
                    <?php $__currentLoopData = $sedesStock; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sedeCol): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php if(($row['stocks'][$sedeCol] ?? 0) > 0): ?>
                            <span class="stock-pill"><?php echo e(config('inventario.display.'.$sedeCol, $sedeCol)); ?> <strong><?php echo e($row['stocks'][$sedeCol]); ?></strong></span>
                        <?php endif; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
                <?php if($row['req_manual'] ?? false): ?>
                    <span class="tag manual"><?php echo e($row['accion_manual']); ?></span>
                <?php else: ?>
                    <span class="muted">Clic para requisitar</span>
                <?php endif; ?>
            </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
<?php endif; ?>

<div id="modal-manual" class="modal-overlay" style="display:none;">
    <div class="panel modal-box">
        <h2 style="margin:0 0 8px;font-size:1.15rem;">Requisición manual</h2>
        <p id="modal-producto" class="muted" style="margin:0 0 16px;"></p>
        <form method="POST" action="<?php echo e(route('inventario.manual.store')); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="codigo" id="manual-codigo">
            <input type="hidden" name="producto" id="manual-producto">
            <input type="hidden" name="q" value="<?php echo e($filters['q'] ?? ''); ?>">
            <input type="hidden" name="categoria" value="<?php echo e($filters['categoria'] ?? 'Ninguno'); ?>">
            <input type="hidden" name="subcategoria" value="<?php echo e($filters['subcategoria'] ?? 'Ninguno'); ?>">
            <div class="field" style="margin-bottom:12px;">
                <label for="manual-origen">Sede origen</label>
                <select name="sede_origen" id="manual-origen" required>
                    <?php $__currentLoopData = $sedesOrigen; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $orig): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($orig); ?>"><?php echo e(config('inventario.display.'.$orig, $orig)); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="field" style="margin-bottom:12px;">
                <label for="manual-cantidad">Cantidad</label>
                <input type="number" name="cantidad" id="manual-cantidad" min="1" value="1" required>
            </div>
            <div id="manual-metricas" class="metric-box muted">Calculando…</div>
            <div style="display:flex;gap:10px;margin-top:16px;">
                <button type="submit" class="btn">Confirmar</button>
                <button type="button" class="btn secondary" id="manual-cancel" type="button">Cancelar</button>
            </div>
        </form>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
(function () {
    const modal = document.getElementById('modal-manual');
    const metricasUrl = <?php echo json_encode(route('inventario.manual.metricas'), 15, 512) ?>;
    let currentCod = null;

    function updateMetricas() {
        if (!currentCod) return;
        const origen = document.getElementById('manual-origen').value;
        const cant = document.getElementById('manual-cantidad').value || 1;
        const box = document.getElementById('manual-metricas');
        box.textContent = 'Calculando…';
        fetch(metricasUrl + '?codigo=' + encodeURIComponent(currentCod) + '&sede_origen=' + encodeURIComponent(origen) + '&cantidad=' + encodeURIComponent(cant))
            .then(r => r.json())
            .then(j => {
                const m = j.metricas || {};
                let html = 'Stock origen: <strong>' + m.stock + '</strong> · Demanda: <strong>' + m.demanda + '</strong> · Excedente: <strong>' + m.excedente + '</strong>';
                if (j.faltante) {
                    html += '<div class="warn">' + j.mensaje + ' Faltante: ' + j.faltante + '</div>';
                } else if (j.mensaje) {
                    html += '<div class="ok">' + j.mensaje + '</div>';
                }
                box.innerHTML = html;
            }).catch(() => { box.textContent = 'No se pudieron cargar métricas.'; });
    }

    document.querySelectorAll('.product-card').forEach(function (card) {
        card.addEventListener('click', function () {
            currentCod = card.dataset.codigo;
            document.getElementById('manual-codigo').value = card.dataset.codigo;
            document.getElementById('manual-producto').value = card.dataset.producto;
            document.getElementById('modal-producto').textContent = card.dataset.producto + ' · ' + card.dataset.codigo;
            if (card.dataset.origenManual) {
                document.getElementById('manual-origen').value = card.dataset.origenManual;
                document.getElementById('manual-cantidad').value = card.dataset.cantidadManual || 1;
            }
            modal.style.display = 'flex';
            updateMetricas();
        });
    });

    document.getElementById('manual-origen').addEventListener('change', updateMetricas);
    document.getElementById('manual-cantidad').addEventListener('input', updateMetricas);
    document.getElementById('manual-cancel').addEventListener('click', () => { modal.style.display = 'none'; });
    modal.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });
})();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\freyg\OneDrive\Imágenes\CALL CENTER\laravel_app\resources\views\inventario\index.blade.php ENDPATH**/ ?>