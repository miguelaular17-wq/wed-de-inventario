@extends('layouts.app')

@section('title', 'Mayor Demanda')

@section('content')
<div class="page-header">
    <h1>Productos con Mayor Demanda — {{ $sede }}</h1>
    <p class="lead">
        Mostrando {{ $rows->count() }} de {{ $calculatedCount }} productos estrella (que esta sede vende más que las otras).
        @if ($rows->lastPage() > 1)
            · Página {{ $rows->currentPage() }}/{{ $rows->lastPage() }}
        @endif
        .
    </p>
</div>

<form id="filters-form" method="GET" class="filter-bar" data-auto-filter data-auto-filter-delay="350" data-auto-filter-target="#ventas-content">
    <div class="field field-wide">
        <label for="q">Buscar</label>
        <input type="search" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Producto o código…" autocomplete="off">
    </div>
    <div class="field">
        <label for="categoria-select">Categoría</label>
        <select name="categoria" id="categoria-select">
            <option value="Ninguno">Todas</option>
            @foreach ($categorias as $cat)
                <option value="{{ $cat }}" @selected($filters['categoria'] === $cat)>{{ $cat }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="subcategoria-select">Subcategoría</label>
        <select name="subcategoria" id="subcategoria-select">
            <option value="Ninguno">Todas</option>
            @foreach ($subcategorias as $sub)
                <option value="{{ $sub }}" @selected($filters['subcategoria'] === $sub)>{{ $sub }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="tiempo_pronostico">Pronóstico (días)</label>
        <input type="number" id="tiempo_pronostico" name="tiempo_pronostico" min="1" max="365" value="{{ $tiempoPronostico }}">
    </div>
</form>

<div id="ventas-content" class="ajax-content">
    @include('ventas.mayor_demanda_content')
</div>

{{-- ======================================================
     MODAL: Requisición de múltiples sedes
     ====================================================== --}}
<div id="modal-requisicion-multi" class="modal-overlay" style="display:none;">
    <div class="panel modal-box modal-box-wide" style="max-width: 600px; width: 100%;">
        <h2 style="margin:0 0 4px;font-size:1.15rem;">Hacer Requisición</h2>
        <p id="modal-producto-info" class="muted" style="margin:0 0 16px;"></p>

        <form id="modal-requisicion-form" method="POST" action="{{ route('inventario.manual.store_batch') }}">
            @csrf
            <input type="hidden" name="codigo" id="modal-req-codigo">
            <input type="hidden" name="producto" id="modal-req-producto">

            <div class="table-wrap" style="margin-bottom: 16px; max-height: 300px; overflow-y: auto;">
                <table class="data-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Sede Origen</th>
                            <th class="col-number">Existencia</th>
                            <th class="col-number">Excedente (Seguro)</th>
                            <th class="col-number" style="width: 120px;">Cantidad a Pedir</th>
                        </tr>
                    </thead>
                    <tbody id="modal-requisicion-rows">
                        <!-- Will be populated dynamically by JS -->
                    </tbody>
                </table>
            </div>

            <div style="display:flex; justify-content: space-between; align-items: center; gap: 10px; margin-top: 16px;">
                <button type="button" class="btn secondary" id="modal-req-cancel" style="margin-bottom:0;">Cerrar</button>
                <button type="submit" class="btn" id="modal-req-submit-btn" style="margin-bottom:0;">Guardar Todo</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const syncInterval = @json((int) config('inventario.sync_interval_ms', 60000));
    const cat = document.getElementById('categoria-select');
    const sub = document.getElementById('subcategoria-select');

    if (cat && sub) {
        function updateSubDisabled() {
            sub.disabled = (cat.value === 'Ninguno');
        }
        updateSubDisabled();
        cat.addEventListener('change', updateSubDisabled);
    }

    let since = @json($stockUpdatedAt);
    const filterForm = document.getElementById('filters-form');
    const contentRoot = document.getElementById('ventas-content');

    const sedesStock = @json($sedesStock);
    const sedesDisplay = @json(
        collect(config('inventario.sedes_stock'))->mapWithKeys(fn($s) => [$s => config('inventario.display.'.$s, $s)])->all()
    );

    const modal = document.getElementById('modal-requisicion-multi');
    const form = document.getElementById('modal-requisicion-form');
    const rowsContainer = document.getElementById('modal-requisicion-rows');
    const cancelBtn = document.getElementById('modal-req-cancel');
    const submitBtn = document.getElementById('modal-req-submit-btn');

    function openRequisitionModal(btn) {
        const codigo = btn.dataset.codigo;
        const producto = btn.dataset.producto;
        const stocks = JSON.parse(btn.dataset.stocks || '{}');
        const excedentes = JSON.parse(btn.dataset.excedentes || '{}');
        const manualesList = JSON.parse(btn.dataset.manualesList || '[]');

        document.getElementById('modal-req-codigo').value = codigo;
        document.getElementById('modal-req-producto').value = producto;
        document.getElementById('modal-producto-info').textContent = producto + ' · ' + codigo;

        rowsContainer.innerHTML = '';

        sedesStock.forEach(function (sede) {
            const stock = parseInt(stocks[sede] || 0);
            const excedente = parseInt(excedentes[sede] || 0);
            
            // Only show sedes that have stock or have an existing requisition
            const existing = manualesList.find(m => m.sede_origen === sede && m.pendiente);
            const currentQty = existing ? existing.cantidad : 0;

            if (stock <= 0 && currentQty <= 0) {
                return; // skip sedes with no stock and no existing requisition
            }

            const tr = document.createElement('tr');
            const label = sedesDisplay[sede] || sede;
            tr.innerHTML = `
                <td><strong>${label}</strong></td>
                <td class="col-number">${stock}</td>
                <td class="col-number">${excedente}</td>
                <td class="col-number">
                    <input type="number" 
                           name="quantities[${sede}]" 
                           class="modal-qty-input" 
                           data-sede="${sede}" 
                           data-excedente="${excedente}" 
                           min="0" 
                           value="${currentQty > 0 ? currentQty : ''}" 
                           placeholder="0"
                           style="width: 80px; text-align: right; padding: 4px 8px; border: 1px solid var(--border-color, #e5e7eb); border-radius: 6px;">
                    <div class="qty-status" style="font-size: 0.72rem; margin-top: 2px; text-align: right; min-height: 14px;"></div>
                </td>
            `;
            rowsContainer.appendChild(tr);

            const input = tr.querySelector('.modal-qty-input');
            const statusDiv = tr.querySelector('.qty-status');

            function updateInputStatus() {
                const val = parseInt(input.value) || 0;
                if (val <= 0) {
                    statusDiv.innerHTML = '';
                    input.style.borderColor = '';
                } else if (val <= excedente) {
                    statusDiv.innerHTML = '<span style="color: #047857;">✓ Seguro</span>';
                    input.style.borderColor = '#047857';
                } else {
                    statusDiv.innerHTML = '<span style="color: #b45309;">⚠️ Afecta demanda</span>';
                    input.style.borderColor = '#b45309';
                }
            }

            input.addEventListener('input', updateInputStatus);
            updateInputStatus(); // init
        });

        if (rowsContainer.children.length === 0) {
            rowsContainer.innerHTML = '<tr><td colspan="4" style="text-align: center; color: var(--text-muted, #6b7280); padding: 20px 0;">Ninguna otra sede tiene existencia disponible para este producto.</td></tr>';
        }

        modal.style.display = 'flex';
    }

    // Event delegation for opening the modal
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-hacer-requisicion');
        if (btn) {
            openRequisitionModal(btn);
        }
    });

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            modal.style.display = 'none';
        });
    }

    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        
        submitBtn.disabled = true;
        const submitBtnText = submitBtn.textContent;
        submitBtn.textContent = 'Guardando…';

        try {
            const r = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!r.ok) {
                const errData = await r.json();
                throw new Error(errData.error || 'Ocurrió un error.');
            }

            const data = await r.json();
            if (data.success) {
                modal.style.display = 'none';
                await reloadContent();
            } else {
                alert(data.error || 'Ocurrió un error al guardar.');
            }
        } catch (err) {
            alert(err.message);
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = submitBtnText;
        }
    });

    async function reloadContent() {
        if (!contentRoot) return;

        const url = new URL(window.location.href);
        contentRoot.classList.add('is-loading');
        try {
            const r = await fetch(url.toString(), {
                headers: {
                    'X-Partial': 'content',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!r.ok) return;
            contentRoot.innerHTML = await r.text();
            if (window.AutoFilter) {
                window.AutoFilter.rebind('#ventas-content');
            }
        } finally {
            contentRoot.classList.remove('is-loading');
        }
    }

    async function syncVentas() {
        if (!since) {
            return;
        }

        const url = new URL(@json(route('ventas.sync')).replace(/&amp;/g, '&'), window.location.origin);
        url.searchParams.set('since', since);
        new FormData(filterForm).forEach((value, key) => url.searchParams.set(key, value));

        try {
            const r = await fetch(url.toString());
            if (!r.ok) {
                return;
            }

            const j = await r.json();
            if (!j.changed) {
                return;
            }

            since = j.updated_at;
            await reloadContent();
        } catch (e) {
            // ignore transient sync issues
        }
    }

    if (since && window.AppSyncPoll) {
        window.AppSyncPoll.start(syncVentas, syncInterval);
    }
})();
</script>
@endpush
